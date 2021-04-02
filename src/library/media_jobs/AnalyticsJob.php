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
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\media_jobs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\processors\PageProcessor;
use seekquarry\yioop\models\CrawlModel;
use seekquarry\yioop\models\ImpressionModel;
use seekquarry\yioop\models\MachineModel;
use seekquarry\yioop\models\PhraseModel;

/**
 * A media job used to periodically calculate summary statistics about
 * group, thread, page, and query impressions.
 */
class AnalyticsJob extends MediaJob
{
    /**
     * Time in current epoch when analytics last updated
     * @var int
     */
    public $update_time;
    /**
     * Used to get statistics from DBMS about wiki and thread views
     *
     * @var object
     */
    public $impression_model;
    /**
     *  Used to get crawl statistics about the number of various HTTP response
     * requests seen during a crawl
     *
     * @var object
     */
    public $phrase_model;
    /**
     * Used to determine which queue servers are available and which might
     * have information about a crawl
     *
     * @var object
     */
    public $machine_model;
    /**
     * Used to get crawl seed info
     *
     * @var object
     */
    public $crawl_model;
    /**
     * For size and time distributions the number of times the miminal
     * recorded interval (DOWNLOAD_SIZE_INTERVAL for size) to check for
     * pages with that size/download time
     */
    const NUM_TIMES_INTERVAL = 50;
    /**
     * While computing the statistics page, number of seconds until a
     * page refresh and save of progress so far occurs
     */
    const STATISTIC_REFRESH_RATE = C\ANALYTICS_UPDATE_INTERVAL/2;
    /**
     * Initializes the time of last analytics update
     */
    public function init()
    {
        $this->update_time = 0;
        $this->name_server_does_client_tasks = true;
        $this->name_server_does_client_tasks_only = true;
        $this->impression_model = new ImpressionModel();
        $this->phrase_model = new PhraseModel();
        $this->machine_model = new MachineModel();
        $this->crawl_model = new CrawlModel();
        PageProcessor::initializeIndexedFileTypes();
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
        if ($delta > C\ANALYTICS_UPDATE_INTERVAL) {
            $this->update_time = $time;
            L\crawlLog("Performing analytics update");
            return true;
        }
        L\crawlLog("Time since last update not exceeded, skipping analytics".
            " update");
        return false;
    }
    /**
     * For now analytics update is only done on name server as Yioop
     * currently only supports one DBMS at a time.
     */
    public function nondistributedTasks()
    {
        $this->doTasks([]);
    }
    /**
     * Calls ImpressionModel to actually calculate various impression totals
     * since the last update
     *
     * @param array $tasks array of info that came from getTasks
     * (in this nothing)
     */
    public function doTasks($tasks)
    {
        $this->computeCrawlStatistics();
        $this->impression_model->computeStatistics();
    }
    /**
     * Runs the queries neccessary to determine httpd code distribution,
     * filetype distribution, num hosts, language distribution,
     * os distribution, server distribution, site distribution, file size
     * distribution, download time distribution, etc for a web crawl
     * for which statistics have been requested but not yet computed.
     * If these queries take too long it saves partial results and returns.
     *
     * @param array &$data associative array which will have all the statistics
     *     data collected.
     */
    public function computeCrawlStatistics()
    {
        $stats_requests =
            glob(C\CRAWL_DIR."/cache/pre_" .
            self::statistics_base_name . "*.txt");
        if (empty($stats_requests)) {
            L\crawlLog("No statistics for particular crawls requested");
            return;
        }
        $pre_stats_file = $stats_requests[0];
        $stats_file = str_replace("pre_", "", $pre_stats_file);
        $data = unserialize(file_get_contents($pre_stats_file));
        if (empty($data["TIMESTAMP"])) {
            L\crawlLog("Request " . $pre_stats_file . " could not be ".
                "processed. Deleting request file.");
            unlink($pre_stats_file);
            return;
        }
        L\crawlLog("Starting to compute statistics for timestamp index ".
            $data["TIMESTAMP"]);
        $machine_urls = $this->machine_model->getQueueServerUrls();
        $seed_info = $this->crawl_model->getCrawlSeedInfo($data["TIMESTAMP"],
            $machine_urls);
        $num_machines = count($machine_urls);
        if ($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = [];
        }
        $queries = [
            "CODE" => [100, 101, 102, 103, 122, 200, 201, 202, 203, 204,
                205, 206, 207, 208, 226, 301, 302, 303, 304, 305, 306, 307,
                308, 400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410,
                411, 412, 413, 414, 415, 416, 417, 418, 420, 422, 423, 424,
                425, 426, 428, 429, 431, 444, 449, 450, 499, 500, 501, 502,
                503, 504, 505, 506, 507, 508, 509, 510, 511, 598, 599],
            "FILETYPE" => PageProcessor::$indexed_file_types,
            "HOST" => ["all"],
            "LANG" => [ 'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar',
                'as', 'av', 'ay', 'az', 'ba', 'be', 'bg', 'bh', 'bi', 'bm',
                'bn', 'bo', 'br', 'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs',
                'cu', 'cv', 'cy', 'da', 'de', 'dv', 'dz', 'ee', 'el', 'en',
                'eo', 'es', 'et', 'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr',
                'fy', 'ga', 'gd', 'gl', 'gn', 'gu', 'gv', 'ha', 'he', 'hi',
                'ho', 'hr', 'ht', 'hu', 'hy', 'hz', 'ia', 'id', 'ie', 'ig',
                'ii', 'ik', 'in', 'io', 'is', 'it', 'iu', 'iw', 'ja', 'ji',
                'jv', 'jw', 'ka', 'kg', 'ki', 'kj', 'kk', 'kl', 'km', 'kn',
                'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky', 'la', 'lb', 'lg',
                'li', 'ln', 'lo', 'lt', 'lu', 'lv', 'mg', 'mh', 'mi', 'mk',
                'ml', 'mn', 'mo', 'mr', 'ms', 'mt', 'my', 'na', 'nb', 'nd',
                'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny', 'oc', 'oj',
                'om', 'or', 'os', 'pa', 'pi', 'pl', 'ps', 'pt', 'qu', 'rm',
                'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd', 'se', 'sg', 'sh',
                'si', 'sk', 'sl', 'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st',
                'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti', 'tk', 'tl',
                'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur',
                'uz', 've', 'vi', 'vo', 'wa', 'wo', 'xh', 'yi', 'yo', 'za',
                'zh', 'zu'],
            "MEDIA" => ["image", "text", "video"],
            "OS" => ["asianux", "centos", "clearos", "debian", "fedora",
                "freebsd", "gentoo", "linux", "netware", "solaris", "sunos",
                "ubuntu", "unix"],
            "SERVER" => ["aolserver", "apache", "bigip", "boa", "caudium",
                "cherokee", "gws", "goahead-webs", "httpd", "iis",
                "ibm_http_server", "jetty", "lighttpd", "litespeed",
                "microsoft-iis", "nginx", "resin", "server", "sun-java-system",
                "thttpd", "tux", "virtuoso", "webrick", "yaws", "yts",
                "zeus", "zope"],
            "SITE" => [".aero", ".asia", ".biz", ".cat", ".com", ".coop",
                ".edu", ".gov", ".info", ".int", ".jobs", ".mil", ".mobi",
                ".museum", ".name", ".net", ".org", ".pro", ".tel", ".travel",
                ".xxx", ".ac", ".ad", ".ae", ".af", ".ag", ".ai", ".al", ".am",
                ".ao", ".aq", ".ar", ".as", ".at", ".au", ".aw", ".ax", ".az",
                ".ba", ".bb", ".bd", ".be", ".bf", ".bg", ".bh", ".bi", ".bj",
                ".bm", ".bn", ".bo", ".br", ".bs", ".bt", ".bw", ".by", ".bz",
                ".ca", ".cc", ".cd", ".cf", ".cg", ".ch", ".ci", ".ck", ".cl",
                ".cm", ".cn", ".co", ".cr", ".cu", ".cv", ".cx", ".cy", ".cz",
                ".de", ".dj", ".dk", ".dm", ".do", ".dz", ".ec", ".ee", ".eg",
                ".er", ".es", ".et", ".eu", ".fi", ".fj", ".fk", ".fm", ".fo",
                ".fr", ".ga", ".gd", ".ge", ".gf", ".gg", ".gh", ".gi", ".gl",
                ".gm", ".gn", ".gp", ".gq", ".gr", ".gs", ".gt", ".gu", ".gw",
                ".gy", ".hk", ".hm", ".hn", ".hr", ".ht", ".hu", ".id", ".ie",
                ".il", ".im", ".in", ".io", ".iq", ".ir", ".is", ".it", ".je",
                ".jm", ".jo", ".jp", ".ke", ".kg", ".kh", ".ki", ".km", ".kn",
                ".kp", ".kr", ".kw", ".ky", ".kz", ".la", ".lb", ".lc", ".li",
                ".lk", ".lr", ".ls", ".lt", ".lu", ".lv", ".ly", ".ma", ".mc",
                ".md", ".me", ".mg", ".mh", ".mk", ".ml", ".mm", ".mn", ".mo",
                ".mp", ".mq", ".mr", ".ms", ".mt", ".mu", ".mv", ".mw", ".mx",
                ".my", ".mz", ".na", ".nc", ".ne", ".nf", ".ng", ".ni", ".nl",
                ".no", ".np", ".nr", ".nu", ".nz", ".om", ".pa", ".pe", ".pf",
                ".pg", ".ph", ".pk", ".pl", ".pm", ".pn", ".pr", ".ps", ".pt",
                ".pw", ".py", ".qa", ".re", ".ro", ".rs", ".ru", ".rw", ".sa",
                ".sb", ".sc", ".sd", ".se", ".sg", ".sh", ".si", ".sk", ".sl",
                ".sm", ".sn", ".so", ".sr", ".ss", ".st", ".sv", ".sy", ".sz",
                ".tc", ".td", ".tf", ".tg", ".th", ".tj", ".tk", ".tl", ".tm",
                ".tn", ".to", ".tr", ".tt", ".tv", ".tw", ".tz", ".ua", ".ug",
                ".uk", ".us", ".uy", ".uz", ".va", ".vc", ".ve", ".vg", ".vi",
                ".vn", ".vu", ".wf", ".ws", ".ye", ".za", ".zm", ".zw" ],
        ];
        for ($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["SIZE"][] = $i * C\DOWNLOAD_SIZE_INTERVAL;
        }
        for ($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["TIME"][] = $i * C\DOWNLOAD_TIME_INTERVAL;
        }
        for ($i = 0; $i <= self::NUM_TIMES_INTERVAL; $i++) {
            $queries["DNS"][] = $i * C\DOWNLOAD_TIME_INTERVAL;
        }
        for ($i = 0; $i <=max(
            $seed_info["general"]['max_links_to_extract'] ?? 0,
            C\MAX_LINKS_TO_EXTRACT); $i++) {
            $queries["NUMLINKS"][] = $i;
        }
        $date = date("Y");
        for ($i = 1969; $i <= $date; $i++) {
            $queries["MODIFIED"][] = $i;
        }
        $sort_fields = ["CODE", "FILETYPE", "LANG", "MEDIA", "OS",
            "SERVER", "SITE"];
        $time = time();
        if (isset($data["UNFINISHED"])) {
            unset($data["UNFINISHED"]);
        }
        foreach ($queries as $group_description => $query_group) {
            $total = 0;
            foreach ($query_group as $query) {
                L\crawlTimeoutLog("Processing crawl statistics about ".
                    "$group_description $query");
                if (isset($data["SEEN"][$group_description][$query])) {
                    if (isset($data[$group_description]["DATA"][$query])) {
                        $total += $data[$group_description]["DATA"][$query];
                    }
                    continue;
                }
                $count =
                    $this->countQuery(strtolower($group_description)
                        .":".$query, $data["TIMESTAMP"], $machine_urls);
                $data["SEEN"][$group_description][$query] = true;
                if ($count >= 0) {
                    $data[$group_description]["DATA"][$query] = $count;
                    $total += $count;
                }
                if (time() - $time > self::STATISTIC_REFRESH_RATE) {
                    $data["UNFINISHED"] = true;
                    break 2;
                }
            }
            if (isset($data[$group_description]["DATA"])) {
                if (in_array($group_description, $sort_fields)) {
                    arsort($data[$group_description]["DATA"]);
                }
                $data[$group_description]["TOTAL"] = $total;
            }
        }
        $data["OS"]["DATA"]["windows"] = 0;
        if (isset($data["SERVER"]["DATA"]["iis"])) {
            $data["OS"]["DATA"]["windows"] = $data["SERVER"]["DATA"]["iis"];
        }
        if (isset($data["SERVER"]["DATA"]["microsoft-iis"])) {
            $data["OS"]["DATA"]["windows"] +=
                $data["SERVER"]["DATA"]["microsoft-iis"];
        }
        arsort($data["OS"]["DATA"]);
        if (empty($data["UNFINISHED"])) {
            unlink($pre_stats_file);
            file_put_contents($stats_file, serialize($data));
            chmod($stats_file, 0777);
            L\crawlLog("Done computing crawl statistics in " . $stats_file);
        } else {
            file_put_contents($pre_stats_file, serialize($data));
            chmod($pre_stats_file, 0777);
            L\crawlLog("Saving partial crawl statistics in " . $pre_stats_file);
        }
        return $data;
    }
    /**
     * Performs the provided $query of a web crawl (potentially distributed
     * across queue servers). Returns the count of the number of results that
     * would be returned by that query.
     *
     * @param string $query to use and count the results of
     * @param string $index_timestamp timestamp of index to compute query
     *      count for
     * @param array $machine_urls queue servers on which the count is to be
     *      computed
     * @return int number of results that would be returned by the given query
     */
    public function countQuery($query, $index_timestamp, $machine_urls)
    {
        $results = $this->phrase_model->getPhrasePageResults(
            "$query i:$index_timestamp", 0,
            1, true, null, false, 0, $machine_urls);
        return (isset($results["TOTAL_ROWS"])) ? $results["TOTAL_ROWS"] : -1;
    }
}
