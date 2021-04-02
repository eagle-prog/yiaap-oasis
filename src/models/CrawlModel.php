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
namespace seekquarry\yioop\models;

use seekquarry\yioop\controllers\SearchController;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexArchiveBundle;
use seekquarry\yioop\library\IndexManager;
use seekquarry\yioop\library\UrlParser;

/** used to prevent cache page requests from being logged*/
if (!C\nsdefined("POST_PROCESSING") && !empty($_SERVER["NO_LOGGING"])) {
    $_SERVER["NO_LOGGING"] = true;
}
/**
 * This is class is used to handle getting/setting crawl parameters, CRUD
 * operations on current crawls, start, stop, status of crawls,
 * getting cache files out of crawls, determining
 * what is the default index to be used, marshalling/unmarshalling crawl mixes,
 * and handling data from suggest-a-url forms
 *
 * @author Chris Pollett
 */
class CrawlModel extends ParallelModel
{
    /**
     * Used to map between search crawl mix form variables and database columns
     * @var array
     */
    public $search_table_column_map = ["name"=>"NAME", "owner_id"=>"OWNER_ID"];
    /**
     * File to be used to store suggest-a-url form data
     * @var string
     */
    public $suggest_url_file;
    /**
     * {@inheritDoc}
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    public function __construct($db_name = C\DB_NAME, $connect = true)
    {
        $this->suggest_url_file = C\WORK_DIRECTORY."/data/suggest_url.txt";
        parent::__construct($db_name, $connect);
    }
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args = null)
    {
        return "CRAWL_MIXES";
    }
    /**
     * {@inheritDoc}
     *
     * @param array $row row as retrieved from database query
     * @param mixed $args additional arguments that might be used by this
     *      callback. In this case, should be a boolean flag that says whether
     *      or not to add information about the components of the crawl mix
     * @return array $row after callback manipulation
     */
    public function rowCallback($row, $args)
    {
        if ($args) {
            $mix = $this->getCrawlMix($row['TIMESTAMP'], true);
            $row['FRAGMENTS'] = $mix['FRAGMENTS'];
        }
        return $row;
    }

    /**
     * Gets the cached version of a web page from the machine on which it was
     * fetched.
     *
     * Complete cached versions of web pages live on a fetcher machine for
     * pre version 2.0 indexes. For these version, the queue server machine
     * typically only maintains summaries.
     * This method makes a REST request of a fetcher machine for a cached page
     * and get the results back.
     *
     * @param string $machine the ip address of domain name of the machine the
     *     cached page lives on
     * @param string $machine_uri the path from document root on $machine where
     *     the yioop scripts live
     * @param int $partition the partition in the WebArchiveBundle the page is
     *      in
     * @param int $offset the offset in bytes into the WebArchive partition in
     *     the WebArchiveBundle at which the cached page lives.
     * @param string $crawl_time the timestamp of the crawl the cache page is
     *     from
     * @param int $instance_num which fetcher instance for the particular
     *     fetcher crawled the page (if more than one), false otherwise
     * @return array page data of the cached page
     */
    public function getCacheFile($machine, $machine_uri, $partition,
        $offset, $crawl_time, $instance_num = false)
    {
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        if ($machine == '::1') { //IPv6 :(
            $machine = "[::1]";
            //used if the fetching and queue serving were on the same machine
        }
        // we assume all machines use the same scheme & port of the name server
        $port = UrlParser::getPort(C\NAME_SERVER);
        $scheme = UrlParser::getScheme(C\NAME_SERVER);
        $request = "$scheme://$machine:$port$machine_uri?c=archive&a=cache&".
            "time=$time&session=$session&partition=$partition&offset=$offset".
            "&crawl_time=$crawl_time";
        if ($instance_num !== false) {
            $request .= "&instance_num=$instance_num";
        }
        $tmp = FetchUrl::getPage($request);
        $page = @unserialize(base64_decode($tmp));
        $page['REQUEST'] = $request;

        return $page;
    }
    /**
     * Gets the name (aka timestamp) of the current index archive to be used to
     * handle search queries
     *
     * @return string the timestamp of the archive
     */
    public function getCurrentIndexDatabaseName()
    {
        $db = $this->db;
        $sql = "SELECT CRAWL_TIME FROM CURRENT_WEB_INDEX";
        $result = $db->execute($sql);
        $row =  $db->fetchArray($result);
        return $row['CRAWL_TIME'] ?? false;
    }
    /**
     * Sets the IndexArchive that will be used for search results
     *
     * @param $timestamp  the timestamp of the index archive. The timestamp is
     *      when the crawl was started. Currently, the timestamp appears as
     *      substring of the index archives directory name
     */
    public function setCurrentIndexDatabaseName($timestamp)
    {
        $db = $this->db;
        $db->execute("DELETE FROM CURRENT_WEB_INDEX");
        $sql = "INSERT INTO CURRENT_WEB_INDEX VALUES ( ? )";
        $db->execute($sql, [$timestamp]);
    }
    /**
     * Returns all the files in $dir or its subdirectories with modified times
     * more recent than timestamp. The file which have
     * in their path or name a string in the $excludes array will be exclude
     *
     * @param string $dir a directory to traverse
     * @param int $timestamp used to check modified times against
     * @param array $excludes an array of path substrings tot exclude
     * @return array of file structs consisting of name, modified time and
     *     size.
     */
    public function getDeltaFileInfo($dir, $timestamp, $excludes)
    {
        $dir_path_len = strlen($dir) + 1;
        $files = $this->db->fileInfoRecursive($dir, true);
        $names = [];
        $results = [];
        foreach ($files as $file) {
            $file["name"] = substr($file["name"], $dir_path_len);
            if ($file["modified"] > $timestamp && $file["name"] !="") {
                $flag = true;
                foreach ($excludes as $exclude) {
                    if (stristr($file["name"], $exclude)) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    $results[$file["name"]] = $file;
                }
            }
        }
        $results = array_values($results);
        return $results;
    }
    /**
     * Gets a list of all mixes of available crawls
     *
     * @param int $user_id user that we are getting a list of mixes for
     *      We have disabled mix sharing so for now this is all mixes
     * @param bool $with_components if false then don't load the factors
     *     that make up the crawl mix, just load the name of the mixes
     *     and their timestamps; otherwise, if true loads everything
     * @return array list of available crawls
     */
    public function getMixList($user_id, $with_components = false)
    {
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES ";
        /* to add back user_id for mix handling use: WHERE OWNER_ID = ?
           then change the $result command to:
           $result = $this->db->execute($sql, [$user_id]);
        */
        if (intval($user_id) != $user_id) {
            return false; //keep postgres error log cleaner by doing check
        }
        $result = $this->db->execute($sql);
        $rows = [];
        while ($row = $this->db->fetchArray($result)) {
            if ($with_components) {
                $mix = $this->getCrawlMix($row['TIMESTAMP'], true);
                $row['FRAGMENTS'] = $mix['FRAGMENTS'];
            }
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * Retrieves the weighting component of the requested crawl mix
     *
     * @param string $timestamp of the requested crawl mix
     * @param bool $just_components says whether to find the mix name or
     *     just the components array.
     * @return array the crawls and their weights that make up the
     *     requested crawl mix.
     */
    public function getCrawlMix($timestamp, $just_components = false)
    {
        $db = $this->db;
        if (!$just_components) {
            $sql = "SELECT TIMESTAMP, NAME, OWNER_ID, PARENT FROM CRAWL_MIXES ".
                "WHERE TIMESTAMP = ?";
            $result = $db->execute($sql, [$timestamp]);
            $mix =  $db->fetchArray($result);
        } else {
            $mix = [];
        }
        $sql = "SELECT FRAGMENT_ID, RESULT_BOUND".
            " FROM MIX_FRAGMENTS WHERE ".
            " TIMESTAMP = ?";
        $result = $db->execute($sql, [$timestamp]);
        $mix['FRAGMENTS'] = [];
        while ($row = $db->fetchArray($result)) {
            $mix['FRAGMENTS'][$row['FRAGMENT_ID']]['RESULT_BOUND'] =
                $row['RESULT_BOUND'];
        }
        $sql = "SELECT CRAWL_TIMESTAMP, WEIGHT, DIRECTION, KEYWORDS ".
            " FROM MIX_COMPONENTS WHERE ".
            " TIMESTAMP=:timestamp AND FRAGMENT_ID=:fragment_id";
        $params = [":timestamp" => $timestamp];
        foreach ($mix['FRAGMENTS'] as $fragment_id => $data) {
            $params[":fragment_id"] = $fragment_id;
            $result = $db->execute($sql, $params);
            $mix['COMPONENTS'] = [];
            $count = 0;
            if ($result) {
                while ($row =  $db->fetchArray($result)) {
                    $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'][$count] =$row;
                    $count++;
                }
            } else {
                break;
            }
        }
        return $mix;
    }
    /**
     * Returns the timestamp associated with a mix name;
     *
     * @param string $mix_name name to lookup
     * @return mixed timestamp associated with name if exists false otherwise
     */
    public function getCrawlMixTimestamp($mix_name)
    {
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " NAME= ?";
        $result = $db->execute($sql, [$mix_name]);
        $mix =  $db->fetchArray($result);
        if (isset($mix["TIMESTAMP"])) {
            return $mix["TIMESTAMP"];
        }
        return false;
    }
    /**
     * Returns whether the supplied timestamp corresponds to a crawl mix
     *
     * @param string $timestamp of the requested crawl mix
     *
     * @return bool true if it does; false otherwise
     */
    public function isCrawlMix($timestamp)
    {
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " TIMESTAMP = ?";
        $result = $db->execute($sql, [$timestamp]);
        if ($result) {
            if ($mix = $db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }
    /**
     * Returns whether there is a mix with the given $timestamp that $user_id
     * owns. Currently mmix ownership is ignored and this is set to always
     * return true;
     *
     * @param string $timestamp to see if exists
     * @param string $user_id id of would be owner
     *
     * @return bool true if owner; false otherwise
     */
    public function isMixOwner($timestamp, $user_id)
    {
        return true;
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " TIMESTAMP = ? and OWNER_ID = ?";
        if (intval($user_id) != $user_id) {
            return false;
        }
        $result = $db->execute($sql, [$timestamp, $user_id]);
        if ($result) {
            if ($mix = $db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }
    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array representing the crawl mix object
     */
    public function setCrawlMix($mix)
    {
        $db = $this->db;
        //although maybe slower, we first get rid of any old data
        $timestamp = $mix['TIMESTAMP'];
        $this->deleteCrawlMix($timestamp);
        //next we store the new data
        $sql = "INSERT INTO CRAWL_MIXES VALUES (?, ?, ?, ?)";
        $db->execute($sql, [$timestamp, $mix['NAME'], $mix['OWNER_ID'],
            $mix['PARENT']]);
        $fid = 0;
        foreach ($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $sql = "INSERT INTO MIX_FRAGMENTS VALUES (?, ?, ?)";
            $db->execute($sql, [$timestamp, $fid,
                $fragment_data['RESULT_BOUND']]);
            foreach ($fragment_data['COMPONENTS'] as $component) {
                $sql = "INSERT INTO MIX_COMPONENTS(TIMESTAMP,
                    FRAGMENT_ID, CRAWL_TIMESTAMP, WEIGHT, DIRECTION, KEYWORDS)
                    VALUES (?, ?, ?, ?, ?, ?)";
                $db->execute($sql, [$timestamp, $fid,
                    $component['CRAWL_TIMESTAMP'], $component['WEIGHT'],
                    $component['DIRECTION'], $component['KEYWORDS']]);
            }
            $fid++;
        }
    }
    /**
     * Deletes from the DB the crawl mix ans its associated components and
     * fragments
     *
     * @param int $timestamp of the mix to delete
     */
    public function deleteCrawlMix($timestamp)
    {
        $sql = "DELETE FROM CRAWL_MIXES WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
        $sql = "DELETE FROM MIX_FRAGMENTS WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
        $sql = "DELETE FROM MIX_COMPONENTS WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
    }
    /**
     * Deletes the archive iterator and savepoint files created during the
     * process of iterating through a crawl mix.
     *
     * @param int $timestamp The timestamp of the crawl mix
     */
    public function deleteCrawlMixIteratorState($timestamp)
    {
        L\setLocaleObject(L\getLocaleTag());
        $search_controller = new SearchController();
        $search_controller->clearQuerySavepoint($timestamp);

        $archive_dir = C\WORK_DIRECTORY . "/schedules/".
            self::name_archive_iterator . $timestamp;
        if (file_exists($archive_dir)) {
            $this->db->unlinkRecursive($archive_dir);
        }
    }
    /**
     * Returns the initial sites that a new crawl will start with along with
     * crawl parameters such as crawl order, allowed and disallowed crawl sites
     * @param bool $use_default whether or not to use the Yioop! default
     *     crawl.ini file rather than the one created by the user.
     * @return array  the first sites to crawl during the next crawl
     *     restrict_by_url, allowed, disallowed_sites
     */
    public function getSeedInfo($use_default = false)
    {
        if (file_exists(C\WORK_DIRECTORY."/crawl.ini") && !$use_default) {
            $info = L\parse_ini_with_fallback(C\WORK_DIRECTORY . "/crawl.ini");
        } else {
            $info = L\parse_ini_with_fallback(
                C\BASE_DIR . "/configs/default_crawl.ini");
        }
        return $info;
    }
    /**
     * Writes a crawl.ini file with the provided data to the user's
     * WORK_DIRECTORY
     *
     * @param array $info an array containing information about the crawl
     */
    public function setSeedInfo($info)
    {
        if (!isset($info['general']['crawl_index'])) {
            $info['general']['crawl_index']='12345678';
        }
        if (!isset($info['general']['channel'])) {
            $info['general']['channel']='0';
        }
        if (!isset($info["general"]["arc_dir"])) {
            $info["general"]["arc_dir"] = "";
        }
        if (!isset($info["general"]["arc_type"])) {
            $info["general"]["arc_type"] = "";
        }
        if (!isset($info["general"]["cache_pages"])) {
            $info["general"]["cache_pages"] = true;
        }
        if (!isset($info["general"]["summarizer_option"])) {
            $info["general"]["summarizer_option"] = "";
        }
        $n = [];
        $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK *****
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009 - 2021  Chris Pollett chris@pollett.org
;
;  This program is free software: you can redistribute it and/or modify
;  it under the terms of the GNU General Public License as published by
;  the Free Software Foundation, either version 3 of the License, or
;  (at your option) any later version.
;
;  This program is distributed in the hope that it will be useful,
;  but WITHOUT ANY WARRANTY; without even the implied warranty of
;  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;  GNU General Public License for more details.
;
;  You should have received a copy of the GNU General Public License
;  along with this program.  If not, see <https://www.gnu.org/licenses/>.
;  ***** END LICENSE BLOCK *****
;
; crawl.ini
;
; Crawl configuration file
;
EOT;
        $info['general']['page_range_request'] ??= C\PAGE_RANGE_REQUEST;
        $info['general']['max_depth'] ??= -1;
        $info['general']['repeat_type'] ??= -1;
        $info['general']['sleep_start'] ??= "00:00";
        if (!isset($info['general']['sleep_duration'])) {
            $info['general']['sleep_start'] = -1;
        }
        $info['general']['robots_txt'] ??= C\ALWAYS_FOLLOW_ROBOTS;
        $info['general']['page_recrawl_frequency'] ??= C\PAGE_RECRAWL_FREQUENCY;
        $info['general']['max_description_len'] ??= C\MAX_DESCRIPTION_LEN;
        $info['general']['max_links_to_extract'] ??= C\MAX_LINKS_TO_EXTRACT;
        $n[] = '[general]';
        $n[] = "crawl_order = '" . $info['general']['crawl_order'] . "';";
        $n[] = "summarizer_option = '" .
            $info['general']['summarizer_option'] . "';";
        $n[] = "crawl_type = '" . $info['general']['crawl_type'] . "';";
        $n[] = "max_depth = '" . $info['general']['max_depth'] . "';";
        $n[] = "repeat_type = '" . $info['general']['repeat_type'] . "';";
        $n[] = "sleep_start = '" . $info['general']['sleep_start'] . "';";
        $n[] = "sleep_duration = '" . $info['general']['sleep_duration'] . "';";
        $n[] = "robots_txt = '" . $info['general']['robots_txt'] . "';";
        $n[] = "crawl_index = '" . $info['general']['crawl_index'] . "';";
        $n[] = "channel = '" . $info['general']['channel'] . "';";
        $n[] = 'arc_dir = "' . $info["general"]["arc_dir"] . '";';
        $n[] = 'arc_type = "' . $info["general"]["arc_type"] . '";';
        $n[] = "page_recrawl_frequency = '".
            $info['general']['page_recrawl_frequency']."';";
        $n[] = "page_range_request = '".
            $info['general']['page_range_request']."';";
        $n[] = "max_description_len = '".
            $info['general']['max_description_len']."';";
        $n[] = "max_links_to_extract = '".
            $info['general']['max_links_to_extract']."';";
        $bool_string =
            ($info['general']['cache_pages']) ? "true" : "false";
        $n[] = "cache_pages = $bool_string;";
        $bool_string =
            ($info['general']['restrict_sites_by_url']) ? "true" : "false";
        $n[] = "restrict_sites_by_url = $bool_string;";
        $n[] = "";

        $n[] = "[indexed_file_types]";
        if (isset($info["indexed_file_types"]['extensions'])) {
            foreach ($info["indexed_file_types"]['extensions'] as $extension) {
                $n[] = "extensions[] = '$extension';";
            }
        }
        $n[] = "";

        $n[] = "[active_classifiers]";
        if (isset($info['active_classifiers']['label'])) {
            foreach ($info['active_classifiers']['label'] as $label) {
                $n[] = "label[] = '$label';";
            }
        }
        $n[] = "";

        $n[] = "[active_rankers]";
        if (isset($info['active_rankers']['label'])) {
            foreach ($info['active_rankers']['label'] as $label) {
                $n[] = "label[] = '$label';";
            }
        }
        $n[] = "";

        $site_types =
            ['allowed_sites' => 'url', 'disallowed_sites' => 'url',
                'seed_sites' => 'url', 'page_rules'=>'rule'];
        foreach ($site_types as $type => $field) {
            $n[] = "[$type]";
            if (isset($info[$type][$field])) {
                foreach ($info[$type][$field] as $field_value) {
                    $n[] = $field . "[] = '$field_value';";
                }
            }
            $n[]="";
        }
        $n[] = "[indexing_plugins]";
        if (isset($info["indexing_plugins"]['plugins'])) {
            foreach ($info["indexing_plugins"]['plugins'] as $plugin) {
                if ($plugin == "") {
                    continue;
                }
                $n[] = "plugins[] = '$plugin';";
            }
        }
        $out = implode("\n", $n);
        $out .= "\n";
        file_put_contents(C\WORK_DIRECTORY."/crawl.ini", $out);
    }
    /**
     * Returns the crawl parameters that were used during a given crawl
     *
     * @param string $timestamp timestamp of the crawl to load the crawl
     *     parameters of
     * @return array  the first sites to crawl during the next crawl
     *     restrict_by_url, allowed, disallowed_sites
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     */
    public function getCrawlSeedInfo($timestamp,  $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            /* seed info should be same amongst all queue servers that have it--
               only start schedule differs -- however, not all queue servers
               necessarily have the same crawls. Thus, we still query all
               machines in case only one has it.
             */
            $a_list = $this->execMachines("getCrawlSeedInfo",
                $machine_urls, serialize($timestamp));
            if (is_array($a_list)) {
                foreach ($a_list as $elt) {
                    $seed_info = unserialize(L\webdecode(
                        $elt[self::PAGE]));
                    // first crawl with timestamp gets to say what params are
                    if (isset($seed_info['general'])) {
                        break;
                    }
                }
            }
            return $seed_info;
        }
        $dir = C\CRAWL_DIR . '/cache/' . self::index_data_base_name .
            $timestamp;
        $is_double_index = file_exists($dir) ? false : true;
        if ($is_double_index) {
            $dir = C\CRAWL_DIR . '/cache/' . self::double_index_base_name .
                $timestamp;
        }
        $seed_info = null;
        $index_bundle_class = C\NS_LIB . ($is_double_index  ?
            "DoubleIndexBundle" : "IndexArchiveBundle");
        if (file_exists($dir)) {
            $info = $index_bundle_class::getArchiveInfo($dir);
            if (!isset($info['DESCRIPTION']) ||
                $info['DESCRIPTION'] == null ||
                strstr($info['DESCRIPTION'], "Archive created")) {
                return $seed_info;
            }
            $index_info = unserialize($info['DESCRIPTION']);
            $general_params = ["restrict_sites_by_url" =>
                [self::RESTRICT_SITES_BY_URL, false],
                "crawl_type" => [self::CRAWL_TYPE, self::WEB_CRAWL],
                "channel" => [self::CHANNEL, 0],
                "crawl_index" => [self::CRAWL_INDEX, ''],
                "crawl_order" => [self::CRAWL_ORDER, self::PAGE_IMPORTANCE],
                "max_depth" => [self::MAX_DEPTH, -1],
                "repeat_type" => [self::REPEAT_TYPE, -1],
                "sleep_start" => [self::SLEEP_START, -1],
                "sleep_duration" => [self::SLEEP_DURATION, "00:00"],
                "robots_txt" => [self::ROBOTS_TXT, C\ALWAYS_FOLLOW_ROBOTS],
                "summarizer_option" => [self::SUMMARIZER_OPTION,
                    self::BASIC_SUMMARIZER],
                "arc_dir" => [self::ARC_DIR, ''],
                "arc_type" => [self::ARC_TYPE, ''],
                "cache_pages" => [self::CACHE_PAGES, true],
                "page_recrawl_frequency" => [self::PAGE_RECRAWL_FREQUENCY, -1],
                "page_range_request" => [self::PAGE_RANGE_REQUEST,
                    C\PAGE_RANGE_REQUEST],
                "max_description_len" => [self::MAX_DESCRIPTION_LEN,
                    C\MAX_DESCRIPTION_LEN],
                "max_links_to_extract" => [self::MAX_LINKS_TO_EXTRACT,
                    C\MAX_LINKS_TO_EXTRACT],
            ];
            foreach ($general_params as $param => $info) {
                $seed_info['general'][$param] = (isset($index_info[$info[0]])) ?
                    $index_info[$info[0]] : $info[1];
            }
            $site_types = [
                "allowed_sites" => [self::ALLOWED_SITES, "url"],
                "disallowed_sites" => [self::DISALLOWED_SITES, "url"],
                "seed_sites" => [self::TO_CRAWL, "url"],
                "page_rules" => [self::PAGE_RULES, "rule"],
                "indexed_file_types" => [self::INDEXED_FILE_TYPES,
                    "extensions"],
            ];
            foreach ($site_types as $type => $info) {
                if (isset($index_info[$info[0]])) {
                    $tmp = & $index_info[$info[0]];
                } else {
                    $tmp = [];
                }
                $seed_info[$type][$info[1]] =  $tmp;
            }
            if (isset($index_info[self::INDEXING_PLUGINS])) {
                $seed_info['indexing_plugins']['plugins'] =
                    $index_info[self::INDEXING_PLUGINS];
            }
            if (isset($index_info[self::INDEXING_PLUGINS_DATA])) {
                $seed_info['indexing_plugins']['plugins_data'] =
                    $index_info[self::INDEXING_PLUGINS_DATA];
            }
        }
        return $seed_info;
    }
    /**
     * Changes the crawl parameters of an existing crawl (can be while crawling)
     * Not all fields are allowed to be updated
     *
     * @param string $timestamp timestamp of the crawl to change
     * @param array $new_info the new parameters
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function setCrawlSeedInfo($timestamp, $new_info,
        $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $channel = $this->getChannel($timestamp);
            $params = [$timestamp, $new_info];
            $network_filename = C\CRAWL_DIR . "/schedules/" .
                "$channel-network_status.txt";
            if (file_exists($network_filename)) {
                $net_status = unserialize(file_get_contents($network_filename));
                $net_status[self::SLEEP_START] =
                    $new_info['general']["sleep_start"] ?? "00:00";
                $net_status[self::SLEEP_DURATION] =
                    $new_info['general']["sleep_duration"] ?? "-1";
                file_put_contents($network_filename, serialize($net_status));
            }
            $this->execMachines("setCrawlSeedInfo",
                $machine_urls, serialize($params));
            return;
        }
        $pre_dir = C\CRAWL_DIR . '/cache/' . self::index_data_base_name .
            $timestamp;
        $dir = (file_exists($pre_dir)) ? $pre_dir : C\CRAWL_DIR . '/cache/' .
            self::double_index_base_name . $timestamp;
        $class_name = ($dir == $pre_dir) ? C\NS_LIB . "IndexArchiveBundle" :
            C\NS_LIB . "DoubleIndexBundle";
        if (file_exists($dir)) {
            $info = $class_name::getArchiveInfo($dir);
            $index_info = unserialize($info['DESCRIPTION']);
            if (isset($new_info['general']["restrict_sites_by_url"])) {
                $index_info[self::RESTRICT_SITES_BY_URL] =
                    $new_info['general']["restrict_sites_by_url"];
            }
            if (isset($new_info['general']["repeat_type"])) {
                $index_info[self::REPEAT_TYPE] =
                    $new_info['general']["repeat_type"];
            }
            if (isset($new_info['general']["sleep_start"])) {
                $index_info[self::SLEEP_START] =
                    $new_info['general']["sleep_start"];
            }
            if (isset($new_info['general']["sleep_duration"])) {
                $index_info[self::SLEEP_DURATION] =
                    $new_info['general']["sleep_duration"];
            }
            $updatable_site_info = [
                "allowed_sites" => [self::ALLOWED_SITES, 'url'],
                "disallowed_sites" => [self::DISALLOWED_SITES, 'url'],
                "seed_sites" => [self::TO_CRAWL, "url"],
                "page_rules" => [self::PAGE_RULES, 'rule'],
                "indexed_file_types" => [self::INDEXED_FILE_TYPES,
                    "extensions"],
                "active_classifiers" => [self::ACTIVE_CLASSIFIERS, 'label'],
                "active_rankers" => [self::ACTIVE_RANKERS, 'label'],
            ];
            foreach ($updatable_site_info as $type => $type_info) {
                if (isset($new_info[$type][$type_info[1]])) {
                    $index_info[$type_info[0]] =
                        $new_info[$type][$type_info[1]];
                }
            }
            if (isset($new_info['indexing_plugins']['plugins'])) {
                $index_info[self::INDEXING_PLUGINS] =
                    $new_info['indexing_plugins']['plugins'];
            }
            $info['DESCRIPTION'] = serialize($index_info);
            $class_name::setArchiveInfo($dir, $info);
        }
    }
    /**
     *  Gets the channel of the crawl with the given timestamp
     *
     * @param int $timestamp of crawl to get channel for
     * @return int $channel used by that crawl
     */
    public function getChannel($timestamp)
    {
        $seed_info = $this->getCrawlSeedInfo($timestamp);
        $channel = (empty($seed_info['channel'])) ? 0 : $seed_info['channel'];
        return $channel;
    }
    /**
     * Returns an array of urls which were stored via the suggest-a-url
     * form in suggest_view.php
     *
     * @return array urls that have been suggested
     */
    public function getSuggestSites()
    {
        $suggest_file = $this->suggest_url_file;
        if (file_exists($suggest_file)) {
            $urls = file($suggest_file);
        } else {
            $urls = [];
        }
        return $urls;
    }
    /**
     * Add new distinct urls to those already saved in the suggest_url_file
     * If the supplied url is not new or the file size
     * exceeds MAX_SUGGEST_URL_FILE_SIZE then it is not added.
     *
     * @param string $url to add
     * @return string true if the url was added or already existed
     *     in the file; false otherwise
     */
    public function appendSuggestSites($url)
    {
        $suggest_file = $this->suggest_url_file;
        $suggest_size = strlen($url);
        if (file_exists($suggest_file)) {
            $suggest_size += filesize($suggest_file);
        } else {
            $this->clearSuggestSites();
        }
        if ($suggest_size < C\MAX_SUGGEST_URL_FILE_SIZE) {
            $urls = file($suggest_file);
            $urls[] = $url;
            $urls = array_unique($urls);
            $out_string = "";
            $delim = "";
            foreach ($urls as $url) {
                $trim_url = trim($url);
                if (strlen($trim_url) > 0) {
                    $out_string .= $delim . $trim_url;
                    $delim = "\n";
                }
            }
            file_put_contents($suggest_file, $out_string, LOCK_EX);
            return true;
        }
        return false;
    }
    /**
     * Resets the suggest_url_file to be the empty file
     */
    public function clearSuggestSites()
    {
        file_put_contents($this->suggest_url_file, "", LOCK_EX);
    }
    /**
     * Get a description associated with a Web Crawl or Crawl Mix
     *
     * @param int $timestamp of crawl or mix in question
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     * @return array associative array containing item DESCRIPTION
     */
    public function getInfoTimestamp($timestamp, $machine_urls = null)
    {
        $is_mix = $this->isCrawlMix($timestamp);
        $info = [];
        if ($is_mix) {
            $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
                " TIMESTAMP=?";
            $result = $this->db->execute($sql, [$timestamp]);
            $mix =  $this->db->fetchArray($result);
            $info['TIMESTAMP'] = $timestamp;
            $info['DESCRIPTION'] = $mix['NAME'];
            $info['IS_MIX'] = true;
        } else {
            if ($machine_urls != null && is_array($machine_urls) &&
                !$this->isSingleLocalhost($machine_urls, $timestamp)) {
                $cache_file = C\CRAWL_DIR . "/cache/" .
                    self::network_base_name . $timestamp . ".txt";
                if (file_exists($cache_file)) {
                    $old_info = unserialize(file_get_contents($cache_file));
                }
                if (isset($old_info) && filemtime($cache_file)
                    + 6 * C\ONE_MINUTE > time()) {
                    return $old_info;
                }
                $info = [];
                if (isset($old_info["MACHINE_URLS"])) {
                    $info["MACHINE_URLS"] = $old_info["MACHINE_URLS"];
                    $machine_urls = array_intersect(
                        $old_info["MACHINE_URLS"], $machine_urls);
                } else {
                    $info["MACHINE_URLS"] = $machine_urls;
                }
                $info_lists = $this->execMachines("getInfoTimestamp",
                    $machine_urls, serialize($timestamp));
                $info['DESCRIPTION'] = "";
                $info["COUNT"] = 0;
                $info['VISITED_URLS_COUNT'] = 0;
                set_error_handler(null);
                foreach ($info_lists as $info_list) {
                    $a_info = @unserialize(L\webdecode(
                        $info_list[self::PAGE]));
                    if (isset($a_info['DESCRIPTION'])) {
                        $info['DESCRIPTION'] = $a_info['DESCRIPTION'];
                    }
                    if (isset($a_info['VISITED_URLS_COUNT'])) {
                        $info['VISITED_URLS_COUNT'] +=
                            $a_info['VISITED_URLS_COUNT'];
                    }
                    if (isset($a_info['COUNT'])) {
                        $info['COUNT'] += $a_info['COUNT'];
                    }
                }
                set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                file_put_contents($cache_file, serialize($info));
                return $info;
            }
            $pre_dir = C\CRAWL_DIR . '/cache/' . self::index_data_base_name .
                $timestamp;
            $dir = (file_exists($pre_dir)) ? $pre_dir : C\CRAWL_DIR .
                '/cache/' . self::double_index_base_name . $timestamp;
            $class_name = ($dir == $pre_dir) ? C\NS_LIB . "IndexArchiveBundle" :
                C\NS_LIB . "DoubleIndexBundle";
            if (file_exists($dir)) {
                $info = $class_name::getArchiveInfo($dir);
                if (isset($info['DESCRIPTION'])) {
                    $tmp = unserialize($info['DESCRIPTION']);
                    $info['DESCRIPTION'] = isset($tmp['DESCRIPTION']) ?
                        $tmp['DESCRIPTION'] : "";
                }
                $info['COUNT'] = empty($info['QUERY_COUNT']) ?
                    $info['COUNT'] : $info['QUERY_COUNT'];
                $info['VISITED_URLS_COUNT'] =
                    empty($info['QUERY_VISITED_URLS_COUNT']) ?
                    $info['VISITED_URLS_COUNT'] :
                    $info['QUERY_VISITED_URLS_COUNT'];
            }
        }
        return $info;
    }
    /**
     * Deletes the crawl with the supplied timestamp if it exists. Also
     * deletes any crawl mixes making use of this crawl
     *
     * @param string $timestamp a Unix timestamp
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function deleteCrawl($timestamp, $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            //get rid of cache info on Name machine
            $mask = C\CRAWL_DIR . "/cache/" . self::network_crawllist_base_name.
                "*.txt";
            array_map("unlink", glob($mask));
            $delete_files = [
                C\CRAWL_DIR . "/cache/" . self::network_base_name .
                    "$timestamp.txt",
                C\CRAWL_DIR . "/cache/" . self::statistics_base_name .
                    "$timestamp.txt"
            ];
            foreach ($delete_files as $delete_file) {
                if (file_exists($delete_file)) {
                    unlink($delete_file);
                }
            }
            if (!in_array(C\NAME_SERVER, $machine_urls)) {
                array_unshift($machine_urls, C\NAME_SERVER);
            }
            //now get rid of files on queue servers
            $this->execMachines("deleteCrawl",
                $machine_urls, serialize($timestamp));
            return;
        }
        $delete_dirs = [
            C\CRAWL_DIR . '/cache/' . self::double_index_base_name . $timestamp,
            C\CRAWL_DIR . '/cache/' . self::index_data_base_name . $timestamp,
            C\CRAWL_DIR . '/schedules/' . self::index_data_base_name .
                $timestamp,
            C\CRAWL_DIR . '/schedules/' . self::schedule_data_base_name .
                $timestamp,
            C\CRAWL_DIR . '/schedules/' . self::robot_data_base_name .
                $timestamp,
            C\CRAWL_DIR . '/schedules/' . self::name_archive_iterator .
                $timestamp,
        ];
        foreach ($delete_dirs as $delete_dir) {
            if (file_exists($delete_dir)) {
                $this->db->unlinkRecursive($delete_dir, true);
            }
        }
        $save_point_files = glob(C\CRAWL_DIR.'/schedules/'.self::save_point.
            $timestamp . "*.txt");
        foreach ($save_point_files as $save_point_file) {
            @unlink($save_point_file);
        }
        $sql = "SELECT DISTINCT TIMESTAMP FROM MIX_COMPONENTS WHERE ".
            " CRAWL_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        while ($row = $this->db->fetchArray($result)) {
            if (!empty($row['TIMESTAMP'])) {
                $this->deleteCrawlMix($row['TIMESTAMP']);
            }
        }
        $current_timestamp = $this->getCurrentIndexDatabaseName();
        if ($current_timestamp == $timestamp) {
            $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        }
    }
    /**
     * Clears several memory and file caches related to crawls and
     * networking.
     */
    public function clearCrawlCaches()
    {
        $masks = [self::network_crawllist_base_name,
            self::network_base_name];
        foreach ($masks as $mask) {
            $mask = C\CRAWL_DIR . "/cache/" . $mask . "*.txt";
            array_map("unlink", glob($mask));
        }
        FetchUrl::$local_ip_cache = [];
        $local_ip_cache_file = C\CRAWL_DIR . "/temp/" .
            self::local_ip_cache_file;
        if (file_exists($local_ip_cache_file)) {
            unlink($local_ip_cache_file);
        }
    }
    /**
     * Used to send a message to the queue servers to start a crawl
     *
     * @param array $crawl_params has info like the time of the crawl,
     *      whether starting a new crawl or resuming an old one, etc.
     * @param array $seed_info what urls to crawl, etc as from the crawl.ini
     *      file
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param int $num_fetchers number of fetchers on machine to start.
     *      This parameter and $channel are used to start the daemons
     *      running on the machines if they aren't already running
     */
    public function sendStartCrawlMessage($crawl_params, $seed_info = null,
        $machine_urls = null, $num_fetchers = 0)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $params = [$crawl_params, $seed_info];
            $crawl_time = $crawl_params[self::CRAWL_TIME];
            $sleep_start = $crawl_params[self::SLEEP_START] ?? "00:00";
            $sleep_duration = $crawl_params[self::SLEEP_DURATION] ?? -1;
            file_put_contents(C\CRAWL_DIR . "/schedules/" .
                "{$crawl_params[self::CHANNEL]}-network_status.txt",
                serialize([self::CRAWL_TIME => $crawl_time,
                self::SLEEP_START => $sleep_start,
                self::SLEEP_DURATION => $sleep_duration]));
            $this->execMachines("sendStartCrawlMessage",
                $machine_urls, serialize($params), 0, true);
            return true;
        }
        $channel = (empty($crawl_params[self::CHANNEL])) ? 0 :
            $crawl_params[self::CHANNEL];
        $statuses = CrawlDaemon::statuses();
        if ($statuses == [] && $channel != -1) {
            if(!$this->startQueueServerFetchers($channel,
                $num_fetchers)) {
                return false;
            }
        }
        $info_string = serialize($crawl_params);
        file_put_contents(
            C\CRAWL_DIR . "/schedules/$channel-QueueServerMessages.txt",
            $info_string);
        chmod(C\CRAWL_DIR . "/schedules/$channel-QueueServerMessages.txt",
            0777);
        if ($seed_info != null) {
            $scheduler_info[self::HASH_SEEN_URLS] = [];
            $sites = $seed_info['seed_sites']['url'];
            $sites = array_filter($sites, function($site) {
                return $site[0] != "#"; //ignore comments in file
            });
            $num_sites = count($sites);
            // use high 3 bytes for integer weight, 1 byte to remember depth
            $common_start_weight = floor(((1 << 24) - 1) / max($num_sites, 1));
            foreach ($sites as $site) {
                $site_parts = preg_split("/\s+/", $site);
                if (strlen($site_parts[0]) > 0) {
                    if ($crawl_params[self::CRAWL_ORDER] ==
                        self::PAGE_IMPORTANCE) {
                        $scheduler_info[self::TO_CRAWL][] =
                            [$site_parts[0], $common_start_weight];
                    } else {
                        $scheduler_info[self::TO_CRAWL][] =
                            [$site_parts[0], 0];
                    }
                }
            }
            $scheduler_string = "\n" . L\webencode(
                gzcompress(serialize($scheduler_info)));
            file_put_contents(C\CRAWL_DIR . "/schedules/$channel-" .
                self::schedule_start_name, $scheduler_string);
        }
        return true;
    }
    /**
     * Used to start QueueServers and Fetchers on current machine when
     * it is detected that someone tried to start a crawl but hadn't
     * started any queue servers or fetchers.
     *
     * @param int $channel channel of crawl to start
     * @param int $num_fetchers the number of fetchers on the current machine
     * @return bool whether any processes were started
     */
    function startQueueServerFetchers($channel, $num_fetchers)
    {
        $db = $this->db;
        $success = false;
        $machine_name = "NAME_SERVER";
        if (C\NAME_SERVER == C\BASE_URL && $channel == 0 &&
            $num_fetchers == 0) {
            $sql = "SELECT NAME, CHANNEL, NUM_FETCHERS FROM MACHINE" .
                " WHERE URL='" . C\BASE_URL . "' OR URL='BASE_URL'";
            $result = $db->execute($sql);
            if ($result) {
                $row = $db->fetchArray($result);
                $machine_name = $row['NAME'];
                $channel = intval($row['CHANNEL']);
                $num_fetchers = intval($row['NUM_FETCHERS']);
            }
        }
        $sql = "INSERT INTO ACTIVE_PROCESS VALUES (?, ?, ?)";
        /* This method was supposed to be called if nothing is running
           so shouldn't do anything if not starting any fetchers.
           Checking channel >= handles mirror mode. Even if multi channels
           on a given machine, use only one queue server.
         */
        if ($channel >= 0 && $num_fetchers > 0) {
            CrawlDaemon::start("QueueServer", "$channel", self::INDEXER, 0);
            CrawlDaemon::start("QueueServer", "$channel", self::SCHEDULER, -1);
            $db->execute($sql, [$machine_name, 0, "QueueServer"]);
            $success = true;
        }
        for ($id = 0; $id < $num_fetchers; $id++) {
            $db->execute($sql, [$machine_name, $id, "Fetcher"]);
            CrawlDaemon::start("Fetcher", "$id-$channel", "$channel", -1);
            $success = true;
        }
        sleep(5);
        return $success;
    }
    /**
     * Used to send a message to the queue servers to stop a crawl
     * @param $channel of crawl to stop
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function sendStopCrawlMessage($channel, $machine_urls = null)
    {
         if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)){
            $net_stat_file = C\CRAWL_DIR . "/schedules/network_status.txt";
            if (file_exists($net_stat_file)) {
                @unlink($net_stat_file);
            }
            $this->execMachines("sendStopCrawlMessage", $machine_urls,
                $channel);
            return;
        }
        $info = [];
        $info[self::STATUS] = "STOP_CRAWL";
        $info_string = serialize($info);
        file_put_contents(C\CRAWL_DIR . "/schedules/$channel-" .
            "QueueServerMessages.txt", $info_string);
    }
    /**
     * Gets a list of all index archives of crawls that have been conducted
     *
     * @param bool $return_arc_bundles whether index bundles used for indexing
     *     arc or other archive bundles should be included in the lsit
     * @param bool $return_recrawls whether index archive bundles generated as
     *     a result of recrawling should be included in the result
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param bool $cache whether to try to get/set the data to a cache file
     *
     * @return array available IndexArchiveBundle directories and
     *     their meta information this meta information includes the time of
     *     the crawl, its description, the number of pages downloaded, and the
     *     number of partitions used in storing the inverted index
     */
    public function getCrawlList($return_arc_bundles = false,
        $return_recrawls = false, $machine_urls = null, $cache = false)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $arg = ($return_arc_bundles && $return_recrawls) ? 3 :
                (($return_recrawls) ? 2 : (($return_arc_bundles) ? 1 : 0));
            $cache_file = C\CRAWL_DIR . "/cache/" .
                self::network_crawllist_base_name . "$arg.txt";
            if ($cache && file_exists($cache_file) && filemtime($cache_file)
                + 300 > time()) {
                return unserialize(file_get_contents($cache_file));
            }
            $list_strings = $this->execMachines("getCrawlList",
                $machine_urls, $arg);
            $list = $this->aggregateCrawlList($list_strings);
            if ($cache) {
                file_put_contents($cache_file, serialize($list));
            }
            return $list;
        }
        $list = [];
        $dirs = glob(C\CRAWL_DIR . '/cache/{' . self::index_data_base_name .
            ',' . self::double_index_base_name . '}*',
            GLOB_ONLYDIR | GLOB_BRACE);
        $feed_dir = C\CRAWL_DIR . '/cache/' . self::feed_index_data_base_name;
        foreach ($dirs as $dir) {
            $crawl = [];
            if ($dir != $feed_dir) {
                preg_match('/(' .self::index_data_base_name .
                    '|'. self::double_index_base_name .
                    ')(\d+)$/', $dir, $matches);
                $bundle_class_name = (!empty($matches[1][0] == 'D') &&
                    $matches[1][0] == 'D') ?
                    C\NS_LIB . "DoubleIndexBundle" :
                    C\NS_LIB . "IndexArchiveBundle";
                $crawl['CRAWL_TIME'] = $matches[2] ?? 0;
            } else {
                $bundle_class_name = C\NS_LIB . "IndexArchiveBundle";
                $crawl['CRAWL_TIME'] = self::FEED_CRAWL_TIME;
            }
            $info = $bundle_class_name::getArchiveInfo($dir);
            if (isset($info['DESCRIPTION'])) {
                set_error_handler(null);
                $index_info = @unserialize($info['DESCRIPTION']);
                set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            } else {
                $index_info = [];
                $index_info['DESCRIPTION'] = "ERROR!! $dir<br />" .
                    print_r($info, true);
            }
            $crawl['DESCRIPTION'] = "";
            if (!$return_recrawls &&
                isset($index_info[self::CRAWL_TYPE]) &&
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                continue;
            } elseif ($return_recrawls  &&
                isset($index_info[self::CRAWL_TYPE]) &&
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL
                && empty($index_info[self::ARC_TYPE])) {
                $crawl['DESCRIPTION'] = "RECRAWL::";
            }
            $sched_path = C\CRAWL_DIR . '/schedules/'.
                self::schedule_data_base_name . $crawl['CRAWL_TIME'];
            $crawl['RESUMABLE'] = false;
            if (is_dir($sched_path)) {
                $sched_dir = opendir($sched_path);
                while (($name = readdir($sched_dir)) !==  false) {
                    $sub_path = "$sched_path/$name";
                    if (!is_dir($sub_path) || $name == '.' ||
                        $name == '..') {
                        continue;
                    }
                    $sub_dir = opendir($sub_path);
                    $i = 0;
                    while (($sub_name=readdir($sub_dir)) !== false && $i < 5) {
                        if ($sub_name[0] == 'A' && $sub_name[1] == 't') {
                            $crawl['RESUMABLE'] = true;
                            break 2;
                        }
                    }
                    closedir($sub_dir);
                }
                closedir($sched_dir);
            }
            if (isset($index_info['DESCRIPTION'])) {
                $crawl['DESCRIPTION'] .= $index_info['DESCRIPTION'];
            }
            $crawl['VISITED_URLS_COUNT'] =
                isset($info['VISITED_URLS_COUNT']) ?
                $info['VISITED_URLS_COUNT'] : 0;
            $crawl['COUNT'] = (isset($info['COUNT'])) ? $info['COUNT'] : 0;
            if (!empty($info['QUERY_COUNT'])) {
                $crawl['QUERY_VISITED_URLS_COUNT'] =
                    isset($info['QUERY_VISITED_URLS_COUNT']) ?
                    $info['QUERY_VISITED_URLS_COUNT'] : 0;
                $crawl['QUERY_COUNT'] = (isset($info['QUERY_COUNT'])) ?
                    $info['QUERY_COUNT'] : 0;
            }
            $crawl['NUM_DOCS_PER_PARTITION'] =
                (isset($info['NUM_DOCS_PER_PARTITION'])) ?
                $info['NUM_DOCS_PER_PARTITION'] : 0;
            $crawl['WRITE_PARTITION'] =
                (isset($info['WRITE_PARTITION'])) ?
                $info['WRITE_PARTITION'] : 0;
            $list[] = $crawl;
        }
        if ($return_arc_bundles) {
            $dirs = glob(C\CRAWL_DIR.'/archives/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $crawl = [];
                $crawl['CRAWL_TIME'] = crc32($dir);
                $crawl['DESCRIPTION'] = "ARCFILE::";
                $crawl['ARC_DIR'] = $dir;
                $ini_file = "$dir/arc_description.ini";
                if (!file_exists($ini_file)) {
                    continue;
                } else {
                    $ini = L\parse_ini_with_fallback($ini_file);
                    $crawl['ARC_TYPE'] = $ini['arc_type'];
                    $crawl['DESCRIPTION'] .= $ini['description'];
                }
                $crawl['VISITED_URLS_COUNT'] = 0;
                $crawl['COUNT'] = 0;
                $crawl['NUM_DOCS_PER_PARTITION'] = 0;
                $crawl['WRITE_PARTITION'] = 0;
                $list[] = $crawl;
            }
        }
        return $list;
    }
    /**
     * When @see getCrawlList() is used in a multi-queue server this method
     * used to integrate the crawl lists received by the different machines
     *
     * @param array $list_strings serialized crawl list data from different
     * queue servers
     * @param string $data_field field of $list_strings to use for data
     * @return array list of crawls and their meta data
     */
    public function aggregateCrawlList($list_strings, $data_field = null)
    {
        set_error_handler(null);
        $pre_list = [];
        foreach ($list_strings as $list_string) {
            $a_list = @unserialize(L\webdecode(
                $list_string[self::PAGE]));
            if ($data_field != null) {
                $a_list = $a_list[$data_field] ?? false;
            }
            if (is_array($a_list)) {
                foreach ($a_list as $elt) {
                    $timestamp = $elt['CRAWL_TIME'];
                    if (!isset($pre_list[$timestamp])) {
                        $pre_list[$timestamp] = $elt;
                    } else {
                        if (isset($elt["DESCRIPTION"]) &&
                            $elt["DESCRIPTION"] != "") {
                            $pre_list[$timestamp]["DESCRIPTION"] =
                                $elt["DESCRIPTION"];
                        }
                        $pre_list[$timestamp]["VISITED_URLS_COUNT"] +=
                            $elt["VISITED_URLS_COUNT"];
                        $pre_list[$timestamp]["COUNT"] +=
                            $elt["COUNT"];
                        if (!empty($elt["QUERY_COUNT"])) {
                            $pre_list[$timestamp]["QUERY_VISITED_URLS_COUNT"] +=
                                $elt["QUERY_VISITED_URLS_COUNT"];
                            $pre_list[$timestamp]["QUERY_COUNT"] +=
                                $elt["QUERY_COUNT"];
                        }
                        if (isset($elt['RESUMABLE'])) {
                            $pre_list[$timestamp]['RESUMABLE'] |=
                                $elt['RESUMABLE'];
                        }
                    }
                }
            }
        }
        $list = array_values($pre_list);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        return $list;
    }
    /**
     * Determines if the length of time since any of the fetchers has spoken
     * with any of the queue servers has exceeded CRAWL_TIMEOUT. If so,
     * typically the caller of this method would do something such as officially
     * stop the crawl.
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return bool whether the current crawl is stalled or not
     */
    public function crawlStalled($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $outputs = $this->execMachines("crawlStalled", $machine_urls);
            return $this->aggregateStalled($outputs);
        }
        $stat_prefix = C\CRAWL_DIR . "/schedules/";
        $stat_prefix_len = strlen($stat_prefix);
        $stat_suffix = "-crawl_status.txt";
        $stat_suffix_len = strlen($stat_suffix);
        $stat_files = glob("$stat_prefix*$stat_suffix");
        $statuses = [];
        foreach ($stat_files as $stat_file) {
            $channel = substr($stat_file, $stat_prefix_len, -$stat_suffix_len);
            $crawl_status = unserialize(file_get_contents($stat_file));
            $non_repeating = (empty($crawl_status["REPEAT_TYPE"]) ||
                intval($crawl_status["REPEAT_TYPE"]) < 0);
            /* crawl_status file will be updated any time data sent from
               fetcher via FetchController update method.
               If no new data has arrived for CRAWL_TIMEOUT amount of time
               assume crawl not active */
            if ($non_repeating && filemtime($stat_file) +
                    C\CRAWL_TIMEOUT < time()) {
                $statuses[$channel] = true;
            }
        }
        return $statuses;
    }
    /**
     * When @see crawlStalled() is used in a multi-queue server this method
     * used to integrate the stalled information received by the different
     * machines
     *
     * @param array $stall_statuses contains web encoded serialized data one
     * one field of which has the boolean data concerning stalled statis
     *
     * @param string $data_field field of $stall_statuses to use for data
     *     if null then each element of $stall_statuses is a wen encoded
     *     serialized boolean
     * @return array
     */
    public function aggregateStalled($stall_statuses, $data_field = null)
    {
        set_error_handler(null);
        $out_stalled = [];
        foreach ($stall_statuses as $status) {
            $stall_status = @unserialize(L\webdecode($status[self::PAGE]));
            if ($data_field !== null) {
                $stall_status = $stall_status[$data_field] ?? false;
            }
            if ($stall_status) {
                foreach ($stall_status  as $channel => $channel_status) {
                    if (isset($out_stalled[$channel])) {
                        $out_stalled[$channel] = $channel_status &&
                            $out_stalled[$channel];
                    } else {
                        $out_stalled[$channel] = $channel_status;
                    }
                }
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        return $out_stalled;
    }
    /**
     * Returns data about current crawl such as DESCRIPTION, TIMESTAMP,
     * peak memory of various processes, most recent fetcher, most recent
     * urls, urls seen, urls visited, etc.
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     *     on which the crawl is being conducted
     * @return array associative array of the said data
     */
    public function crawlStatus($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $status_strings = $this->execMachines("crawlStatus", $machine_urls);
            return $this->aggregateStatuses($status_strings);
        }
        $stat_prefix = C\CRAWL_DIR . "/schedules/";
        $stat_prefix_len = strlen($stat_prefix);
        $stat_suffix = "-crawl_status.txt";
        $stat_suffix_len = strlen($stat_suffix);
        $stat_files = glob("$stat_prefix*$stat_suffix");
        $data = [];
        $statuses = [];
        foreach ($stat_files as $stat_file) {
            $channel = substr($stat_file, $stat_prefix_len, -$stat_suffix_len);
            $data[$channel] = [];
            $crawl_status =
                @unserialize(file_get_contents($stat_file));
            if (empty($crawl_status)) {
                unlink($stat_file);
                continue;
            }
            $schedule_status_file = C\CRAWL_DIR .
                "/schedules/$channel-schedule_status.txt";
            $schedule_status_exists = file_exists($schedule_status_file);
            if ($schedule_status_exists) {
                $schedule_status = @unserialize(file_get_contents(
                    $schedule_status_file));
                if (isset($schedule_status[self::TYPE]) &&
                    $schedule_status[self::TYPE] == self::SCHEDULER) {
                    $data[$channel]['SCHEDULER_PEAK_MEMORY'] =
                        isset($schedule_status[self::MEMORY_USAGE]) ?
                        $schedule_status[self::MEMORY_USAGE] : 0;
                }
            }
            $data[$channel] = (isset($crawl_status)
                && is_array($crawl_status)) ?
                array_merge($data[$channel], $crawl_status) : $data[$channel];
            if (isset($data[$channel]['VISITED_COUNT_HISTORY']) &&
                count($data[$channel]['VISITED_COUNT_HISTORY']) > 1) {
                $recent = array_shift($data[$channel]['VISITED_COUNT_HISTORY']);
                $data[$channel]["MOST_RECENT_TIMESTAMP"] = $recent[0];
                $oldest = array_pop($data[$channel]['VISITED_COUNT_HISTORY']);
                unset($data[$channel]['VISITED_COUNT_HISTORY']);
                $change_in_time_hours = floatval(time() - $oldest[0]) /
                    floatval(C\ONE_HOUR);
                $change_in_urls = $recent[1] - $oldest[1];
                $data[$channel]['VISITED_URLS_COUNT_PER_HOUR'] =
                    ($change_in_time_hours > 0) ?
                    $change_in_urls/$change_in_time_hours : 0;
            } else {
                $data[$channel]['VISITED_URLS_COUNT_PER_HOUR'] = 0;
            }
        }
        return $data;
    }
    /**
     * When @see crawlStatus() is used in a multi-queue server this method
     * used to integrate the status information received by the different
     * machines
     *
     * @param array $status_strings
     * @param string $data_field field of $status_strings to use for data
     * @return array associative array of DESCRIPTION, TIMESTAMP,
     * peak memory of various processes, most recent fetcher, most recent
     * urls, urls seen, urls visited, etc.
     */
    public function aggregateStatuses($status_strings, $data_field = null)
    {
        $status = [];
        $init_channel_status = [
            'WEBAPP_PEAK_MEMORY' => 0,
            'FETCHER_PEAK_MEMORY' => 0,
            'QUEUE_PEAK_MEMORY' => 0,
            'SCHEDULER_PEAK_MEMORY' => 0,
            'COUNT' => 0,
            'VISITED_URLS_COUNT' => 0,
            'VISITED_URLS_COUNT_PER_HOUR' => 0,
            'MOST_RECENT_TIMESTAMP' => 0,
            'DESCRIPTION' => "",
            'MOST_RECENT_FETCHER' => "",
            'MOST_RECENT_URLS_SEEN' => [],
            'CRAWL_TIME' => 0
        ];
        set_error_handler(null);
        foreach ($status_strings as $status_string) {
            $a_status = @unserialize(L\webdecode(
                    $status_string[self::PAGE]));
            if ($data_field != null) {
                $a_status = $a_status[$data_field] ?? false;
            }
            $count_fields = ["COUNT", "VISITED_URLS_COUNT_PER_HOUR",
                "VISITED_URLS_COUNT"];
            if (empty($a_status)) {
                continue;
            }
            foreach ($a_status as $channel => $a_status_data) {
                if (empty($status[$channel])) {
                    $status[$channel] = $init_channel_status;
                }
                foreach ($count_fields as $field) {
                    if (isset($a_status_data[$field])) {
                        $status[$channel][$field] += $a_status_data[$field];
                    }
                }
                if (isset($a_status_data["CRAWL_TIME"]) &&
                    $a_status_data["CRAWL_TIME"] >=
                    $status[$channel]['CRAWL_TIME']) {
                    $status[$channel]['CRAWL_TIME'] =
                        $a_status_data["CRAWL_TIME"];
                    $text_fields = ["DESCRIPTION", "MOST_RECENT_FETCHER"];
                    foreach ($text_fields as $field) {
                        if (isset($a_status_data[$field])) {
                            if ($status[$channel][$field] == "" ||
                                in_array($status[$channel][$field],
                                    ["BEGIN_CRAWL", "RESUME_CRAWL"])) {
                                $status[$channel][$field] =
                                    $a_status_data[$field];
                            }
                        }
                    }
                }
                if (isset($a_status_data["MOST_RECENT_TIMESTAMP"]) &&
                    $status[$channel]["MOST_RECENT_TIMESTAMP"] <=
                        $a_status_data["MOST_RECENT_TIMESTAMP"]) {
                    $status[$channel]["MOST_RECENT_TIMESTAMP"] =
                        $a_status_data["MOST_RECENT_TIMESTAMP"];
                    if (isset($a_status_data['MOST_RECENT_URLS_SEEN'])) {
                        $status[$channel]['MOST_RECENT_URLS_SEEN'] =
                            $a_status_data['MOST_RECENT_URLS_SEEN'];
                    }
                }
                $memory_fields = ["WEBAPP_PEAK_MEMORY", "FETCHER_PEAK_MEMORY",
                    "QUEUE_PEAK_MEMORY", "SCHEDULER_PEAK_MEMORY"];
                foreach ($memory_fields as $field) {
                    $a_status_data[$field] = (isset($a_status_data[$field])) ?
                        $a_status_data[$field] : 0;
                    $status[$channel][$field] =
                        max($status[$channel][$field], $a_status_data[$field]);
                }
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        return $status;
    }
    /**
     * This method is used to reduce the number of network requests
     * needed by the crawlStatus method of admin_controller. It returns
     * an array containing the results of the @see crawlStalled
     * @see crawlStatus and @see getCrawlList methods
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param bool $use_cache whether to try to use a cached version of the
     *      the crawl info or to always recompute it.
     * @return array containing three components one for each of the three
     *     kinds of results listed above
     */
    public function combinedCrawlInfo($machine_urls = null, $use_cache = false)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $combined_crawl_info_file = C\WORK_DIRECTORY .
                "/cache/combined_crawl_info.txt";
            if ($use_cache && file_exists($combined_crawl_info_file) &&
                filemtime($combined_crawl_info_file) + C\MIN_QUERY_CACHE_TIME <
                time()) {
                return unserialize(file_get_contents(
                    $combined_crawl_info_file));
            }
            $combined_strings =
                $this->execMachines("combinedCrawlInfo", $machine_urls);
            $combined = [];
            $combined[] = $this->aggregateStalled($combined_strings,
                0);
            $combined[] = $this->aggregateStatuses($combined_strings,
                1);
            $combined[] = $this->aggregateCrawlList($combined_strings,
                2);
            if (!file_exists($combined_crawl_info_file) ||
                filemtime($combined_crawl_info_file) + C\MIN_QUERY_CACHE_TIME >
                time()) {
                file_put_contents($combined_crawl_info_file,
                    serialize($combined));
            }
            return $combined;
        }
        $combined = [];
        $combined[] = $this->crawlStalled();
        $combined[] = $this->crawlStatus();
        $combined[] = $this->getCrawlList(false, true);
        return $combined;
    }
    /**
     * Add the provided urls to the schedule directory of URLs that will
     * be crawled
     *
     * @param string $timestamp Unix timestamp of crawl to add to schedule of
     * @param array $inject_urls urls to be added to the schedule of
     *     the active crawl
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function injectUrlsCurrentCrawl($timestamp, $inject_urls,
        $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $this->execMachines("injectUrlsCurrentCrawl", $machine_urls,
                serialize(array($timestamp, $inject_urls)));
            return;
        }

        $dir = C\CRAWL_DIR."/schedules/".
            self::schedule_data_base_name. $timestamp;
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $day = floor($timestamp/C\ONE_DAY) - 1;
            /* want before all other schedules,
               execute next */
        $dir .= "/$day";
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $count = count($inject_urls);
        if ($count > 0) {
            $now = time();
            $schedule_data = [];
            $schedule_data[self::SCHEDULE_TIME] =
                $timestamp;
            $schedule_data[self::TO_CRAWL] = [];
            for ($i = 0; $i < $count; $i++) {
                $url = $inject_urls[$i];
                $hash = L\crawlHash($now.$url);
                $schedule_data[self::TO_CRAWL][] =
                    [$url, 1, $hash];
            }
            $data_string = L\webencode(
                gzcompress(serialize($schedule_data)));
            $data_hash = L\crawlHash($data_string);
            file_put_contents($dir."/At1From127-0-0-1".
                "WithHash$data_hash.txt", $data_string);
            return true;
        }
        return false;
    }
    /**
     * Computes for each word in an array of words a count of the total number
     * of times it occurs in this crawl model's default index.
     *
     * @param array $words words to find the counts for
     * @param array $machine_urls machines to invoke this command on
     * @return array associative array of word => counts
     */
     public function countWords($words, $machine_urls = null)
     {
         if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
             $count_strings = $this->execMachines("countWords", $machine_urls,
                serialize(array($words, $this->index_name)));
             $word_counts = [];
             foreach ($count_strings as $count_string) {
                 $a_word_counts = unserialize(L\webdecode(
                        $count_string[self::PAGE]));
                 if (is_array($a_word_counts)) {
                     foreach ($a_word_counts as $word => $count) {
                         $word_counts[$word] = (isset($word_counts[$word])) ?
                            $word_counts[$word] + $count : $count;
                     }
                 }
             }
             return $word_counts;
         }
         $index_archive = IndexManager::getIndex($this->index_name);
         $hashes = [];
         $lookup = [];
         foreach ($words as $word) {
             $tmp = L\crawlHash($word);
             $hashes[] = $tmp;
             $lookup[$tmp] = $word;
         }
         $word_key_counts =
            $index_archive->countWordKeys($hashes);
         $phrases = [];
         $word_counts = [];
         if (is_array($word_key_counts) && count($word_key_counts) > 0) {
             foreach ($word_key_counts as $word_key =>$count) {
                 $word_counts[$lookup[$word_key]] = $count;
             }
         }
         return $word_counts;
     }
}
