<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2021  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org (initial MediaJob class
 *      and subclasses based on work of Pooja Mishra for her master's)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\media_jobs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\models as M;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\models\GroupModel;
use seekquarry\yioop\controllers\CrawlController;

/**
 * A media job to download
 */
class TrendingHighlightsJob extends MediaJob
{
    /**
     * Mamimum number of trending values to download in one try
     */
    const MAX_VALUES_ONE_GO = 100;
    /**
     * Time in current epoch when feeds last updated
     * @var int
     */
    public $update_time;
    /**
     * Datasource object used to run db queries related to fes items
     * (for storing and updating them)
     * @var object
     */
    public $db;
    /**
     * Initializes the last update time to far in the past so, feeds will get
     * immediately updated. Sets up connect to DB to store feeds items, and
     * makes it so the same media job runs both on name server and client
     * Media Updaters
     */
    public function init()
    {
        $this->update_time = 0;
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
        if(!empty($this->controller)) {
            $group_model = $this->controller->model("group");
        } else {
            $group_model = new GroupModel();
        }
        $this->group_model = $group_model;
        C\nsconddefine("TRENDING_UPDATE_INTERVAL", C\ONE_HOUR);
    }
    /**
     * Only update if its been more than an hour since the last update
     *
     * @return bool whether its been an hour since the last update
     */
    public function checkPrerequisites()
    {
        $time = time();
        $something_updated = false;
        $delta = $time - $this->update_time;
        if ($delta > C\TRENDING_UPDATE_INTERVAL) {
            $this->update_time = $time;
            L\crawlLog("Performing trending value update");
            return true;
        }
        L\crawlLog("Time since last update not exceeded, skipping trending value
            update");
        return false;
    }
    /**
     * For now trending values only computed on Namesevers (as specific
     * to the database there) so is done in prepareTasks
     */
    public function nondistributedTasks()
    {
        $this->prepareTasks();
    }
    /**
     *
     */
    public function prepareTasks()
    {
        $db = $this->db;
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE (TYPE='trending_value')";
        $result = $db->execute($sql);
        $trending_value_sites = [];
        while ($trending_value_site = $db->fetchArray($result)) {
            $aux_parts = explode("###",
                html_entity_decode($trending_value_site['AUX_INFO'],
                ENT_QUOTES));
            if (!isset($aux_parts[6])) {
                continue;
            }
            $trending_value_site['CATEGORY_GROUP'] = $aux_parts[5];
            $trending_value_site['TREND_VALUE_REGEX'] = $aux_parts[6];
            $trending_value_sites[] = $trending_value_site;
        }
        $this->tasks = $trending_value_sites;
        L\crawlLog("----This media updater is responsible for the trending ".
            "value_sites");
        $i = 1;
        foreach ($trending_value_sites as $trending_value_site) {
            L\crawlLog("----  $i. " . $trending_value_site["NAME"]);
            $i++;
        }
        $num_trending_sites = count($trending_value_sites);
        $trending_values_one_go = self::MAX_VALUES_ONE_GO;
        $limit = 0;
        while ($limit < $num_trending_sites) {
            $trending_values_batch = array_slice($trending_value_sites, $limit,
                $trending_values_one_go);
            $this->updateTrendingValuesOneGo($trending_values_batch);
            $limit += $trending_values_one_go;
        }
        L\crawlLog("----Computing Landing Page Highlights");
        $this->computeLandingHighlights();
    }
    /**
     * For each of a supplied list of podcast associative arrays,
     * downloads the non-expired media for that podcast to the wiki folder
     * specified.
     *
     * @param array $trending_sites an array of associative arrays of info
     *      about values to download
     * @param int $age oldest age items to consider for download
     * @param boolean $test_mode if true then rather then updating items in
     *  wiki, returns as a string summarizing the results of the downloads
     *  that would occur as part of updating the podcast
     * @return mixed either true, or if $test_mode is true then the results
     *      as a string of the operations involved in downloading the podcasts
     */
    public function updateTrendingValuesOneGo($trending_sites,
        $age = C\ONE_WEEK, $test_mode = false)
    {
        $db =$this->db;
        $test_results = "";
        $log_function = function ($msg, $log_tag = "pre class='source-test'")
            use (&$test_results, $test_mode) {
            $close_tag= preg_split("/\s+/",$log_tag)[0];
            if ($test_mode) {
                $test_results .= "<$log_tag>$msg</$close_tag>\n";
            } else {
                L\crawlLog($msg);
            }
        };
        $trending_sites = FetchUrl::getPages($trending_sites, false, 0, null,
            "SOURCE_URL", CrawlConstants::PAGE, false, null, true);
        $insert_sql = "INSERT INTO TRENDING_TERM (TERM, OCCURRENCES, " .
            "UPDATE_PERIOD, TIMESTAMP, LANGUAGE, CATEGORY) VALUES " .
            "(?, ?, ?, ?, ?, ?)";
        $time = time();
        foreach ($trending_sites as $trending_site) {
            $language = $trending_site['LANGUAGE'];
            $category = $trending_site['CATEGORY'];
            if (empty($trending_site[CrawlConstants::PAGE])) {
                $log_function(
                    "...No data in {$trending_site['NAME']} skipping.", "h3");
                continue;
            }
            $log_function("----Updating {$trending_site['NAME']}.", "h3");
            // strip namespaces
            $regex = $trending_site['TREND_VALUE_REGEX'];
            $page = $trending_site[CrawlConstants::PAGE];
            if (preg_match_all($regex, $page, $matches)) {
                $trending_matches = [];
                if (isset($matches[2])) {
                    $trending_matches = array_combine($matches[1], $matches[2]);
                } else if(isset($matches[1])) {
                    $trending_matches =[[$trending_site['CATEGORY_GROUP']
                        => $matches[1]]];
                }
                foreach ($trending_matches as $name => $value) {
                    $db->execute($insert_sql, [$name, $value,
                        C\TRENDING_UPDATE_INTERVAL,
                        $time, $language, $category]);
                }
            } else {
                $log_function("----could not parse an values from page.");
            }
        }
        return ($test_mode) ? $test_results : true;
    }
    /**
     * Computes arrays of data used to display the news and trending highlights
     * on the search landing page. Saves these precomputed arrays to
     * C\WORK_DIRECTORY . "/cache/trends" under a file for each locale
     */
    public function computeLandingHighlights()
    {
        $locale_model = new M\LocaleModel();
        $source_model = new M\SourceModel();
        $subsearches =  $source_model->getSubsearches();
        $phrase_model = new M\PhraseModel();
        $crawl_model = new M\CrawlModel();
        $machine_model = new M\MachineModel();
        $verticals_model = new M\SearchverticalsModel;
        $trending_model = new M\TrendingModel;
        $locale_list = $locale_model->getLocaleList();
        $locale = C\DEFAULT_LOCALE;
        $locale_major = (explode("-", $locale))[0];
        $trend_dir = C\WORK_DIRECTORY . "/cache/trends";
        foreach ($locale_list as $language) {
            $locale_tag = $language['LOCALE_TAG'];
            $locale_tag_major = (explode("-", $locale_tag))[0];
            $highlights_file = "$trend_dir/highlights_$locale_tag.txt";
            $num_random_trends = 9;
            $highlights = [];
            foreach ($subsearches as $subsearch) {
                if (preg_match("/highlight:(\d+)\b/ui",
                    $subsearch["DEFAULT_QUERY"] ?? "", $highlight_match)) {
                    $subsearch['PRIORITY'] = (empty($highlight_match[1])) ? 0 :
                        floatval($highlight_match[1]);
                    if (preg_match("/trending:(\w+):(\w+)\b/ui",
                        $subsearch["DEFAULT_QUERY"], $trending_parts)) {
                        $subsearch['CATEGORY'] = $trending_parts[1] ?? "";
                        if (empty($trending_parts[2])) {
                            $subsearch['ORDER'] = "SCORE_DESC";
                        } else {
                            $subsearch['ORDER'] = $trending_parts[2];
                        }
                    }
                    $highlights[] = $subsearch;
                }
            }
            usort($highlights, function($a, $b) {
                if ($a['PRIORITY'] == $b['PRIORITY']) {
                    return 0;
                }
                return ($a['PRIORITY'] > $b['PRIORITY']) ? -1 : 1;
            });
            $landing_highlight_data = [];
            $queue_servers = $machine_model->getQueueServerUrls();
            foreach ($highlights as $highlight) {
                if (!empty($highlight['CATEGORY'])) {
                    $landing_highlight_data[] = [
                        "NAME" => $phrase_model->translateDb(
                            $highlight['LOCALE_STRING'], $locale_tag),
                        'TYPE' => 'random_terms',
                        'CATEGORY' => $highlight['CATEGORY'],
                        "ORDER" => $subsearch['ORDER'],
                        'DATA' => $trending_model->randomTrends(
                            $locale_tag, $highlight['CATEGORY'],
                            $num_random_trends)
                    ];
                } else {
                    $query = preg_replace("/trending:\w+|highlight:\w+/ui",
                        '', $highlight['DEFAULT_QUERY']);
                    if (preg_match("/\bm:(\d+)\b/",
                        $highlight['INDEX_IDENTIFIER'], $matches)) {
                        $mix = $crawl_model->getCrawlMix($matches[1]);
                        $query = $phrase_model->rewriteMixQuery($query, $mix);
                    } else {
                        $query = ($highlight['INDEX_IDENTIFIER'] == -1) ?
                            "site:all" : $highlight['INDEX_IDENTIFIER'] . " " .
                            $query;
                    }
                    $query = preg_replace('/lang:default-major/ui', "lang:" .
                        $locale_tag_major, $query);
                    $query = preg_replace('/lang:default/u',
                        "lang:" . $locale_tag,
                        $query);
                    $query_results = $phrase_model->getPhrasePageResults(
                        $query, 0, 5, false, $verticals_model, true, 0,
                        $queue_servers);
                    $pages = $query_results['PAGES'] ?? [];
                    $landing_highlight_data[] = [
                        "FOLDER_NAME" => $highlight['FOLDER_NAME'],
                        "NAME" => $phrase_model->translateDb(
                            $highlight['LOCALE_STRING'], $locale_tag),
                        'TYPE' => 'feed',
                        'DATA' => $pages
                    ];
                }
            }
            set_error_handler(null);
            if (!file_exists($trend_dir)) {
                @mkdir($trend_dir);
                @chmod($trend_dir, 0777);
            }
            file_put_contents($highlights_file,
                serialize($landing_highlight_data));
            @chmod($highlights_file, 0777);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        }
    }
}
