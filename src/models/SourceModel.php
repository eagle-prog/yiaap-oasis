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
 * @author Chris Pollett chris@pollett.orgs
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;

/** For getLocaleTag*/
require_once __DIR__.'/../library/LocaleFunctions.php';
/**
 * Used to manage data related to video, news, and other search sources
 * Also, used to manage data about available subsearches seen in SearchView
 *
 * @author Chris Pollett
 */
class SourceModel extends ParallelModel
{
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * As SourceModel is used for both media sources and subsearches.
     * The underlying table might be MEDIA_SOURCE or it might be SUBSEARCH.
     * The $args variable is a string which is assumed to say which.
     *
     * @param string $args if is "SUBSEARCH" then the SUBSEARCH table will
     *     be used by getRows rather than MEDIA_SOURCE.
     * @return string which table to use
     */
    public function fromCallback($args = null)
    {
        if ($args == "SUBSEARCH") {
            return "SUBSEARCH";
        }
        return "MEDIA_SOURCE";
    }
    /**
     * Returns a list of media sources such as (video, rss sites) and their
     * URL and thumb url formats, etc
     *
     * @param string $source_type the particular kind of media source to return
     *     for example, video

     * @return array a list of web sites which are either video or news sites
     */
    public function getMediaSources($source_type = "")
    {
        $db = $this->db;
        $sources = [];
        $params = [];
        $sql = "SELECT M.* FROM MEDIA_SOURCE M";
        if (!empty($source_type)) {
            $sql .= " WHERE TYPE=:type";
            $params = [":type" => $source_type];
        }
        $result = $db->execute($sql, $params);
        while ($row = $db->fetchArray($result)) {
            $sources[] = $row;
        }
        return $sources;
    }
    /**
     * Returns the media categories of the search sources that have been
     * stored
     *
     * @param array $exclude_categories
     * @param string $type
     *
     * @return array of arrays distinct ["NAME" => media category,
     *  "TYPE" => source_type]
     */
    public function getMediaCategories($exclude_categories = [], $type = "")
    {
        $db = $this->db;
        $sql = "SELECT DISTINCT CATEGORY, TYPE, TIMESTAMP FROM MEDIA_SOURCE ";
        if (!empty($type)) {
            $sql .= " WHERE TYPE=:type";
            $result = $db->execute($sql, [":type" => $type]);
        } else {
            $sql .= " WHERE NOT TYPE='feed_podcast'";
            $result = $db->execute($sql);
        }
        $categories = [];
        while ($row = $db->fetchArray($result)) {
            $category = $row['CATEGORY'];
            $type = $row['TYPE'];
            $timestamp = $row['TIMESTAMP'];
            if (!in_array($category, $exclude_categories)) {
                $categories[] = ["NAME" => $category, "TYPE" => $type,
                    "TIMESTAMP" => $timestamp];
            }
        }
        return $categories;
    }
    /**
     * Return the media source by the name of the source
     * @param string $timestamp of the media source to look up
     * @return array associative array with SOURCE_NAME, TYPE, SOURCE_URL,
     *     AUX_INFO, and LANGUAGE
     */
    public function getMediaSource($timestamp)
    {
        $db = $this->db;
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE TIMESTAMP = ?";
        $result = $db->execute($sql, [$timestamp]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
   /**
    * Receives a request to get machine data for an array of hashes of urls
    * @return a list of urls of machines used by this instance of yioop for
    *   crawling
    */
    public function getMachineHashUrls()
    {
        $db = $this->db;
        $machines = [];
        $sql = "SELECT DISTINCT URL FROM MACHINE";
        $result = $db->execute($sql);
        $machine_hashes = [];
        while ($row = $db->fetchArray($result)) {
            if ($machines[$i]['URL'] == "BASE_URL") {
                $machines[$i]['URL'] = C\BASE_URL;
            }
            $machine_hashes[] = L\crawlHash($row['URL']);
        }
        return $machine_hashes;
    }
    /**
     * Used to add a new video, rss, html news, or other sources to Yioop
     *
     * @param string $name user-friendly name for media source, for example,
     *  New York Times
     * @param string $source_type whether rss, html, json, regex, feed podcast
     *      scrape podcast
     * @param string $source_category whether news, weather, etc.
     * @param string $source_url url regex of resource (video) or actual
     *     resource (rss). Not quite a real regex you add {} to the
     *     location in the url where the name of the particular video
     *     should go http://www.youtube.com/watch?v={}&
     *     (anything after & is ignored, so between = and & will be matched
     *     as the name of a video)
     * @param string $aux_info
     *      xpaths or regex to scrape news items or podcast feeds
     * @param string $language the locale tag for the media source (rss)
     */
    public function addMediaSource($name, $source_type, $source_category,
        $source_url, $aux_info,
        $language = C\DEFAULT_LOCALE)
    {
        $db = $this->db;
        $sql = "INSERT INTO MEDIA_SOURCE VALUES (?,?,?,?,?,?,?)";
        $db->execute($sql, [time(), $name, $source_type, $source_category,
            $source_url, $aux_info, $language]);
    }
    /**
     * Used to update the fields stored in a MEDIA_SOURCE row according to
     * an array holding new values
     *
     * @param array $source_info updated values for a MEDIA_SOURCE row
     */
    public function updateMediaSource($source_info)
    {
        $timestamp = $source_info['TIMESTAMP'];
        unset($source_info['TIMESTAMP']);
        unset($source_info['NAME']);
        $sql = "UPDATE MEDIA_SOURCE SET ";
        $comma ="";
        $params = [];
        foreach ($source_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE TIMESTAMP=?";
        $params[] = $timestamp;
        $this->db->execute($sql, $params);
    }
    /**
     * Deletes the media source whose id is the given timestamp
     *
     * @param int $timestamp of media source to be deleted
     */
    public function deleteMediaSource($timestamp)
    {
        $sql = "DELETE FROM MEDIA_SOURCE WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
    }
    /**
     * Returns a list of the subsearches used by the current Yioop instances
     * including their names translated to the current locale
     *
     * @return array associative array containing subsearch info name in locale,
     *    folder name, index, number of results per page
     */
    public function getSubsearches()
    {
        $subsearches = [];
        $db = $this->db;
        $locale_tag = L\getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = ? " . $db->limitOffset(1);
        $result = $db->execute($sql, [$locale_tag]);
        if (!$result) {
            return $subsearches;
        }
        $row = $db->fetchArray($result);
        if (!$row) {
            return $subsearches;
        }
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT S.LOCALE_STRING AS LOCALE_STRING, ".
            "S.FOLDER_NAME AS FOLDER_NAME, ".
            " S.PER_PAGE AS PER_PAGE, ".
            " S.DEFAULT_QUERY AS DEFAULT_QUERY, ".
            " S.INDEX_IDENTIFIER AS INDEX_IDENTIFIER, ".
            " T.TRANSLATION_ID AS TRANSLATION_ID FROM ".
            " SUBSEARCH S, TRANSLATION T WHERE  ".
            " T.IDENTIFIER_STRING = S.LOCALE_STRING";
        $i = 0;
        $result = $db->execute($sql);
        $sub_sql = "SELECT TRANSLATION AS SUBSEARCH_NAME ".
            "FROM TRANSLATION_LOCALE ".
            " WHERE TRANSLATION_ID=? AND LOCALE_ID=? " . $db->limitOffset(1);
            // maybe do left join at some point
        while ($subsearches[$i] = $db->fetchArray($result)) {
            $id = $subsearches[$i]["TRANSLATION_ID"];
            $result_sub =  $db->execute($sub_sql, [$id, $locale_id]);
            $translate = false;
            if ($result_sub) {
                $translate = $db->fetchArray($result_sub);
            }
            if ($translate) {
                $subsearches[$i]['SUBSEARCH_NAME'] =
                    $translate['SUBSEARCH_NAME'];
            } else {
                $subsearches[$i]['SUBSEARCH_NAME'] = $this->translateDb(
                    $subsearches[$i]['LOCALE_STRING'], C\DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($subsearches[$i]); //last one will be null
        return $subsearches;
    }
    /**
     * Return the media source by the name of the source
     * @param string $folder_name
     * @return array
     */
    public function getSubsearch($folder_name)
    {
        $db = $this->db;
        $sql = "SELECT * FROM SUBSEARCH WHERE FOLDER_NAME = ?";
        $result = $db->execute($sql, [$folder_name]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Given the folder name for a subsearch and a locale tag return the
     * natural language name in that for the subsearch
     *
     * @param string $folder_name of subsearch want to look up
     * @param string $locale_tag of language want human understandable
     *      subsearch name
     * @return string natural language name of subsearch
     */
    public function getSubsearchName($folder_name, $locale_tag)
    {
        $subsearch = $this->getSubsearch($folder_name);
        if (isset($subsearch['LOCALE_STRING'])) {
            return $this->translateDb($subsearch['LOCALE_STRING'], $locale_tag);
        }
        return $folder_name;
    }
    /**
     * Adds a new subsearch to the list of subsearches. This are displayed
     * at the top od the Yioop search pages.
     *
     * @param string $folder_name name of subsearch in terms of urls
     *     (not translated name that appears in the subsearch bar)
     * @param string $index_identifier timestamp of crawl or mix to be
     *     used for results of subsearch
     * @param int $per_page number of search results per page when this
     *     subsearch is used
     * @param string $default_query query to use when using subsearch if no
     *      query provided by user. For example, for image search this might
     *      be the empty query, for news it might be lang:default to get
     *      all the news for the default locale.
     */
    public function addSubsearch($folder_name, $index_identifier, $per_page,
        $default_query = "")
    {
        $db = $this->db;
        $locale_string = "db_subsearch_" . $folder_name;
        $sql = "INSERT INTO SUBSEARCH VALUES (?, ?, ?, ?, ?)";
        $db->execute($sql, [$locale_string, $folder_name, $index_identifier,
            $per_page, $default_query]);
        $sql = "INSERT INTO TRANSLATION VALUES (?, ?)";
        $db->execute($sql, [time(), $locale_string]);
    }
    /**
     * Used to update the fields stored in a SUBSEARCH row according to
     * an array holding new values
     *
     * @param array $search_info updated values for a SUBSEARCH row
     */
    public function updateSubsearch($search_info)
    {
        $folder_name = $search_info['FOLDER_NAME'];
        unset($search_info['FOLDER_NAME']);
        $sql = "UPDATE SUBSEARCH SET ";
        $comma ="";
        $params = [];
        foreach ($search_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE FOLDER_NAME=?";
        $params[] = $folder_name;
        $this->db->execute($sql, $params);
    }
    /**
     * Deletes a subsearch from the subsearch table and removes its
     * associated translations
     *
     * @param string $folder_name of subsearch to delete
     */
    public function deleteSubsearch($folder_name)
    {
        $db = $this->db;
        $locale_string = "db_subsearch_" . $folder_name;
        $sql = "SELECT * FROM TRANSLATION WHERE IDENTIFIER_STRING = ?";
        $result = $db->execute($sql, [$locale_string]);
        if (isset($result)) {
            $row = $db->fetchArray($result);
            if (isset($row["TRANSLATION_ID"])) {
                $sql = "DELETE FROM TRANSLATION_LOCALE WHERE ".
                    "TRANSLATION_ID=?";
                $db->execute($sql, [$row["TRANSLATION_ID"]]);
            }
        }
        $sql = "DELETE FROM SUBSEARCH WHERE FOLDER_NAME=?";
        $db->execute($sql, [$folder_name]);
        $sql = "DELETE FROM TRANSLATION WHERE IDENTIFIER_STRING = ?";
        $db->execute($sql, [$locale_string]);
    }
    /**
     * Used to delete any feed data  (IndexDataFeed bundle) and trending
     * data in this Yioop installation.
     *
     * @param array $machine_urls a list of machines which are running
     * MediaUpdaters for this instance of Yioop. If empty assume is just
     * the Name Server
     */
    public function clearFeedData($machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls)) {
            $this->execMachines("clearFeedData", $machine_urls);
            return;
        }
        $db = $this->db;
        $sql = "DELETE FROM TRENDING_TERM";
        $db->execute($sql);
        $feed_dir = C\CRAWL_DIR . '/cache/' . self::feed_index_data_base_name;
        $db->unlinkRecursive($feed_dir);
    }
}
