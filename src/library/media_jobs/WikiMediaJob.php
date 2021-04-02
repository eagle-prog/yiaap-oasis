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
class WikiMediaJob extends MediaJob
{
    /**
     * how long in seconds before a feed item expires
     */
    const ITEM_EXPIRES_TIME = C\ONE_WEEK;
    /**
     * Mamimum number of feeds to download in one try
     */
    const MAX_PODCASTS_ONE_GO = 100;
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
        $this->name_server_does_client_tasks = true;
        $this->name_server_does_client_tasks_only = true;
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
        if(!empty($this->controller)) {
            $group_model = $this->controller->model("group");
        } else {
            $group_model = new GroupModel();
        }
        $this->group_model = $group_model;
        C\nsconddefine("WIKI_UPDATE_INTERVAL", C\ONE_DAY);
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
        if ($delta > C\WIKI_UPDATE_INTERVAL) {
            $this->update_time = $time;
            L\crawlLog("Performing media podcasts update");
            return true;
        }
        L\crawlLog("Time since last update not exceeded, skipping wiki
            update");
        return false;
    }
    /**
     * Get the media sources from the local database and use those to run the
     * the same task as in the distributed setting
     */
    public function nondistributedTasks()
    {
        $db = $this->db;
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE (TYPE='feed_podcast' OR ".
            "TYPE='scrape_podcast')";
        $result = $db->execute($sql);
        $podcasts = [];
        while ($podcast = $db->fetchArray($result)) {
            $this->parsePodcastAuxInfo($podcast);
            if (isset($podcast["PREVIOUSLY_DOWNLOADED"])) {
                $podcasts[] = $podcast;
            }
        }
        $this->tasks = $podcasts;
        $this->doTasks($podcasts);
    }
    /**
     * For each feed source downloads the feeds, checks which items are
     * not in the database, adds them. Then calls the method to rebuild the
     * inverted index shard for feeds
     *
     * @param array $tasks array of feed info (url to download, paths to
     *  extract etc)
     */
    public function doTasks($tasks)
    {
        if (!is_array($tasks)) {
            L\crawlLog(
                "----This media updater is NOT responsible for any podcasts!");
            return;
        }
        $podcasts = $tasks;
        L\crawlLog("----This media updater is responsible for the podcasts:");
        $i = 1;
        foreach ($podcasts as $podcast) {
            L\crawlLog("----  $i. " . $podcast["NAME"]);
            $i++;
        }
        $num_podcasts = count($podcasts);
        $podcasts_one_go = self::MAX_PODCASTS_ONE_GO;
        $limit = 0;
        while ($limit < $num_podcasts) {
            $podcasts_batch = array_slice($podcasts, $limit,
                $podcasts_one_go);
            $this->updatePodcastsOneGo($podcasts_batch);
            $limit += $podcasts_one_go;
        }
    }
    /**
     * Used to fill in details for an associative arrays containing the
     * details of a  Wiki feed and scrape podcast which should be examined
     * to see if new items should be downloaded to wiki pages. As part of
     * processing expired feed items for the given wiki might be deleted.
     *
     * @param array &$podcast after running will contain an associative
     *  array of details about a particular podcast. The input podcast
     *  is assumed to have at least the NAME, WIKI_PAGE, AUX_PATH, and CATEGORY
     *  fields filled in. The latter with the time in seconds till item
     *  expires. If successful the MAX_AGE (which is esseentially the value
     *  the CATEGORY field), WIKI_FILE_PATTERN, WIKI_PAGE_FOLDERS, and
     *  PREVIOUSLY_DOWNLOADED folders will be filled in.
     * @param boolean $test_mode if true then does not cull expired feed items
     *     from disk, but will return previously downloaded as if it had.
     */
    public function parsePodcastAuxInfo(&$podcast, $test_mode = false)
    {
        $locale_tag = $podcast["LANGUAGE"];
        $aux_parts = explode("###",
                html_entity_decode($podcast['AUX_INFO'], ENT_QUOTES));
        list($podcast['AUX_URL_XPATH'], , , , $podcast['LINK_XPATH'],
            $podcast['WIKI_PAGE']) = $aux_parts;
        $podcast['MAX_AGE'] = (empty($podcast['CATEGORY'])) ?
            self::ITEM_EXPIRES_TIME : $podcast['CATEGORY'];
        $group_model = $this->group_model;
        list($group_id, $page_id, $sub_path, $podcast["WIKI_FILE_PATTERN"]) =
            $group_model->getGroupIdPageIdSubPathFromName($podcast['WIKI_PAGE'],
            $locale_tag);
        $podcast["WIKI_PAGE_FOLDERS"] =
            $group_model->getGroupPageResourcesFolders($group_id, $page_id,
            $sub_path, true);
        if (empty($podcast["WIKI_PAGE_FOLDERS"][1])) {
            return;
        }
        $podcast["PREVIOUSLY_DOWNLOADED"] = [];
        list($resource_folder, $thumb_folder,) = $podcast["WIKI_PAGE_FOLDERS"];
        $podcast_download_file = $thumb_folder . "/" .
            L\crawlHash($podcast["NAME"]) . ".txt";
        if (file_exists($podcast_download_file)) {
            $podcast["PREVIOUSLY_DOWNLOADED"] = unserialize(file_get_contents(
                $podcast_download_file));
            $previously_downloaded = [];
            $max_age = $podcast["MAX_AGE"];
            $time = time();
            foreach ($podcast["PREVIOUSLY_DOWNLOADED"] as $guid => $item) {
                if ($max_age < 0||$item["SAVE_TIMESTAMP"] + $max_age > $time) {
                    $previously_downloaded[$guid] = $item;
                } else if (!$test_mode) {
                    $group_model->deleteResource($item['FILENAME'], $group_id,
                        $page_id, $sub_path);
                }
            }
            $podcast["PREVIOUSLY_DOWNLOADED"] = $previously_downloaded;
        }
    }
    /**
     * For each of a supplied list of podcast associative arrays,
     * downloads the non-expired media for that podcast to the wiki folder
     * specified.
     *
     * @param array &$podcast an array of associative arrays of info about
     *  how and where to download podcasts to
     * @param int $age oldest age items to consider for download
     * @param boolean $test_mode if true then rather then updating items in
     *  wiki, returns as a string summarizing the results of the downloads
     *  that would occur as part of updating the podcast
     * @return mixed either true, or if $test_mode is true then the results
     *      as a string of the operations involved in downloading the podcasts
     */
    public function updatePodcastsOneGo($podcasts, $age = C\ONE_WEEK,
        $test_mode = false)
    {
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
        $podcasts = FetchUrl::getPages($podcasts, false, 0, null, "SOURCE_URL",
            CrawlConstants::PAGE, false, null, true);
        foreach ($podcasts as $podcast) {
            if (empty($podcast[CrawlConstants::PAGE])) {
                $log_function(
                    "...No data in {$podcast['NAME']} feed skipping.", "h3");
                continue;
            }
            $log_function("----Updating {$podcast['NAME']}.", "h3");
            // strip namespaces
            $page = preg_replace('@<(/?)(\w+\s*)\:@u', '<$1',
                $podcast[CrawlConstants::PAGE]);
            if (!empty($page)) {
                $podcast[CrawlConstants::PAGE] = $page;
            }
            $mime_type = (empty($podcast[CrawlConstants::TYPE])) ?
                "application/xml" : $podcast[CrawlConstants::TYPE];
            $is_html = preg_match("/html/i", $mime_type);
            if ($test_mode) {
                $log_function("...Downloaded Podcast Data was:",
                    "h3");
                $out_dom = new \DOMDocument('1.0');
                $out_dom->preserveWhiteSpace = false;
                $out_dom->formatOutput = true;
                if ($is_html) {
                    set_error_handler(null);
                    @$out_dom->loadHTML($podcast[CrawlConstants::PAGE]);
                    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                    $podcast_contents = $out_dom->saveHTML();
                } else {
                    set_error_handler(null);
                    @$out_dom->loadXML($podcast[CrawlConstants::PAGE]);
                    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                    $podcast_contents = $out_dom->saveXML();
                }
                $podcast_contents = htmlentities($podcast_contents);
                $log_function($podcast_contents);
            }
            if ($is_html && !empty($podcast['LINK_XPATH'])) {
                list($num_added, $test_info) =
                    $this->processHtmlPodcast($podcast, $age, $test_mode);
            } else if (!$is_html) {
                list($num_added, $test_info) =
                    $this->processFeedPodcast($podcast, $age, $test_mode);
            } else {
                $num_added = 0;
                $test_info = "";
            }
            $test_results .= $test_info;
            if ($num_added > 0 && !$test_mode) {
                $podcast_download_file = $podcast["WIKI_PAGE_FOLDERS"][1] .
                    "/" . L\crawlHash($podcast["NAME"]) . ".txt";
                file_put_contents($podcast_download_file,
                    serialize($podcast["PREVIOUSLY_DOWNLOADED"]));
            } else if ($num_added <= 0) {
                $log_function("----could not parse any podcasts from page.");
            }
        }
        return ($test_mode) ? $test_results : true;
    }
    /**
     * Used to download the media item associated with an HTML scrape podcast
     *
     * @param array &$podcast associative array containing info about the
     *  location, how to handle, and where to download the podcast
     * @param int $age max age of an the media item to be considered for
     *  download
     * @param boolean $test_mode if true then rather then updating items in
     *  wiki, returns as a string summarizing the results of the downloads
     *  that would occur as part of updating the podcast
     * @return array [whether item downloaded, test_mode_info_string if
     *      applicable or "" otherwise]
     */
    public function processHtmlPodcast(&$podcast, $age, $test_mode = false)
    {
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
        $page = $podcast[CrawlConstants::PAGE];
        $dom = L\getDomFromString($page);
        $source_url = $podcast["SOURCE_URL"];
        if (!empty($podcast['AUX_URL_XPATH'])) {
            $sub_aux_xpaths = explode("\n", $podcast['AUX_URL_XPATH']);
            $log_function("...Processing the following AUX PATHS:", "h3");
            $log_function(print_r($sub_aux_xpaths, true));
            foreach ($sub_aux_xpaths as $aux_xpath) {
                $aux_url = $this->getLinkFromQueryPage($aux_xpath,
                    $page, $dom, $source_url);
                if (empty($aux_url)) {
                    $log_function("Downloading aux_url for xpath $aux_xpath ".
                        "was empty, bailing...", "h3");
                    break;
                }
                $log_function("Downloading aux_url $aux_url", "h3");
                $page = FetchUrl::getPage($aux_url);
                if ($test_mode) {
                    $log_function("...Downloaded Aux Data was:",
                        "h3");
                    $out_dom = new \DOMDocument('1.0');
                    $out_dom->preserveWhiteSpace = false;
                    $out_dom->formatOutput = true;
                    set_error_handler(null);
                    @$out_dom->loadHTML($page);
                    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                    $aux_contents = $out_dom->saveHTML();
                    $aux_contents = htmlentities($aux_contents);
                    $log_function($aux_contents);
                }
                $dom = L\getDomFromString($page);
            }
        }
        $url = $this->getLinkFromQueryPage($podcast['LINK_XPATH'], $page, $dom,
             $source_url);
        if (empty($aux_url)) {
            $aux_url = $url;
        }
        $log_function("----...done extracting info. Check for new ".
            "podcast item in {$podcast['NAME']}.", "h3");
        if ($test_mode) {
            $log_function("----...Found following item...", "h3");
        }
        $item = ['guid' => L\crawlHash($url), 'link' => $url,
            'pubdate' => time(), 'title' => $aux_url];
        if ($test_mode) {
            $log_function(print_r($item, true));
            $did_add = true;
        } else {
            $did_add = $this->downloadPodcastItemIfNew($item, $podcast,
                $age);
        }
        if ($did_add) {
            return [1, $test_results];
        }
    }
    /**
     * Used to extract a URL from a pagee either as a string of in dom form
     * and to canonicalize it based on a starting url.
     *
     * @param string $xpath either an xpath to look into a dom object or
     *      a regex to search a page as a string
     * @param string $page source page to search in as a string
     * @param string $dom source page as a dom object
     * @param string $source_url url to use to canonicalize an incomplete
     *  url if the extraction only produces part of a url
     * @return string desired url link
     */
    public function getLinkFromQueryPage($xpath, $page, $dom, $source_url)
    {
        $dom_xpath = new \DOMXPath($dom);
        set_error_handler(null);
        $nodes = @$dom_xpath->evaluate($xpath);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        $url = false;
        if ($nodes === false) {
            $regex_json_parts = explode("json|", $xpath);
            set_error_handler(null);
            @preg_match_all(trim($regex_json_parts[0]), $page, $matches);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            if (!empty($matches[1][0])) {
                $url = $matches[1][0];
                if (!empty($regex_json_parts[1])) {
                    $json_data = json_decode(trim($url, ";"), true);
                    $play_list_path = explode("|", $regex_json_parts[1]);
                    foreach ($play_list_path as $path) {
                        if (!empty($json_data[$path])) {
                            $json_data = $json_data[$path];
                        }
                    }
                    $url = $json_data;
                } else if (isset($regex_json_parts[1]) && $url[0] == "\"") {
                    $url = json_decode($url);
                }
            }
        } else if ($nodes && $nodes->item(0)) {
            $node_item = $nodes->item(0);
            if (in_array($node_item->nodeName, ["a", "meta", "link"])) {
                $href = $node_item->attributes->getNamedItem("href");
                if(!empty($href)) {
                    $url = $href->nodeValue;
                }
                if (!$url && $node_item->nodeName == 'meta') {
                    $content = $node_item->attributes->getNamedItem("content");
                    if(!empty($content)) {
                        $url = $content->nodeValue;
                    }
                }
            } else if (in_array($node_item->nodeName, ["video", "audio"])) {
                $src = $node_item->attributes->getNamedItem("src");
                if(!empty($src)) {
                    $url = $src->nodeValue;
                }
            }
            if (!$url) {
                $url = $nodes->item(0)->nodeValue;
            }
        }
        if ($url) {
            $url = UrlParser::canonicalLink(
                urldecode(html_entity_decode($url)), $source_url);
        }
        return $url;
    }
    /**
     * Processes the page contents of one podcast feed. Determines which
     * podcast files on that page are fresh and if a podcast is fresh downloads
     * it and moves it to the appropariate wiki folder.
     *
     * @param array &$podcast associative array containing page data
     *  for a podcast feed page (not the video or audio files of a particular
     *  podcast on that page) together with rules for how to process it
     * @param int $age how many seconds ago is still considered a recent
     *  enough podcast to process
     * @param boolean $test_mode if true then rather then updating items in
     *  wiki, returns as a string summarizing the results of the downloads
     *  that would occur as part of updating the podcast
     * @return mixed either true, or if $test_mode is true then the results
     *      as a string of the operations involved in downloading the podcasts
     */
    public function processFeedPodcast(&$podcast, $age, $test_mode = false)
    {
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
        $page = $podcast[CrawlConstants::PAGE];
        $page = preg_replace("@<link@", "<slink", $page);
        $page = preg_replace("@</link@", "</slink", $page);
        $page = preg_replace("@pubDate@i", "pubdate", $page);
        $page = preg_replace("@&lt;@", "<", $page);
        $page = preg_replace("@&gt;@", ">", $page);
        $page = preg_replace("@<!\[CDATA\[(.+?)\]\]>@s", '$1', $page);
        // we also need a hack to make UTF-8 work correctly
        $dom = L\getDomFromString($page);
        $dom->encoding = 'UTF-8';
        $nodes = $dom->getElementsByTagName('item');
        // see above comment on why slink rather than link
        $item_elements = ["title" => "title",
            "description" => "description", "link" =>"slink",
            "guid" => "guid", "pubdate" => "pubdate"];
        if ($nodes->length == 0) {
            // maybe we're dealing with atom rather than rss
            $nodes = $dom->getElementsByTagName('entry');
            $item_elements = [
                "title" => "title",
                "description" => ["summary", "content"],
                "link" => "slink", "guid" => "id",
                "pubdate" => "updated"];
        }
        if (!empty($podcast['LINK_XPATH'])) {
            $item_elements['link'] = $podcast['LINK_XPATH'];
        }
        $log_function("----...done extracting info. Check for new ".
            "podcast items in {$podcast['NAME']}.", "h3");
        if ($test_mode) {
            $log_function("----...Found following items...", "h3");
        }
        $num_added = 0;
        $num_seen = 0;
        foreach ($nodes as $node) {
            $item = [];
            foreach ($item_elements as $db_element => $podcast_element) {
                if (!$test_mode) {
                    L\crawlTimeoutLog(
                        "----still adding podcast items to index.");
                }
                if ($db_element == "link" && substr($podcast_element, 0, 4)
                    == "http") {
                    $element_text = $podcast_element;
                    $item[$db_element] = strip_tags($element_text);
                    continue;
                }
                if (!is_array($podcast_element)) {
                    $podcast_element = [$podcast_element];
                }
                foreach ($podcast_element as $tag_name) {
                    $tag_node = $node->getElementsByTagName(
                            $tag_name)->item(0);
                    $element_text = (is_object($tag_node)) ?
                        $tag_node->nodeValue: "";
                    if ($element_text) {
                        break;
                    }
                }
                if ($db_element == "link" && $tag_node &&
                    empty($element_text)) {
                    $element_text = $tag_node->getAttribute("href");
                    if (empty($element_text)) {
                        $element_text = $tag_node->getAttribute("url");
                    }
                    if (empty($element_text)) {
                        $element_text = $tag_node->getAttribute("src");
                    }
                    $element_text = UrlParser::canonicalLink($element_text,
                        $podcast["SOURCE_URL"]);
                }
                $item[$db_element] = strip_tags($element_text);
            }
            if (!empty($item['link']) && !empty($item['title']) &&
                !empty($item_elements['link'])
                && $item['link'] == $item_elements['link']) {
                $item['link'] .= "#" . L\crawlHash($item['title']);
            }
            if (empty($item['guid'])) {
                $item['guid'] = L\crawlHash($item['link'] . $item['title']);
            }
            if ($test_mode) {
                $log_function(print_r($item, true));
                $did_add = true;
            } else {
                $did_add = $this->downloadPodcastItemIfNew($item, $podcast,
                    $age);
            }
            if ($did_add) {
                $num_added++;
            }
            $num_seen++;
        }
        return [$num_added, $test_results];
    }
    /**
     * Given a podcast item from a podcast feed page determines if it has
     * been downloaded or not and if not whether it is recent enough to
     * download. If it is recent enough, it scrapes the file to download
     * and downloads any other intermediate files need to find the
     * file to download, then finally downloads this podcast item. If the
     * podcast item is built out of multiple videos, it concatenates them
     * and makes a single video. It then moves the podcast item to the
     * appropriate wiki folder.
     *
     * @param array $item an associative array about one item on a podcast
     *  feed page
     * @param array &$podcast a reference to an associate array of the podcast
     *  feed the item is from. This is used for the language etc of the item
     *  and is also used to store updates to what podcasts have already been
     *  downloaded
     * @param int $age how many seconds ago is still considered a recent
     *  enough podcast to process
     * @return bool whether downloaded or not.
     */
    public function downloadPodcastItemIfNew($item, &$podcast, $age)
    {
        $group_model = $this->group_model;
        $controller = new CrawlController(); //only need for clean() method
        $pubdate = (empty($item['pubdate'])) ? time():
            (is_int($item['pubdate']) ? $item['pubdate'] :
            strtotime($item['pubdate']));
        if ($pubdate + $age < time()) {
            L\crawlLog("Podcast Item: {$item['title']} is too old skipping...");
            return false;
        }
        if (key_exists($item['guid'], $podcast["PREVIOUSLY_DOWNLOADED"])) {
            L\crawlLog("Podcast Item: {$item['title']} already downloaded, ".
                "skipping...");
            return false;
        }
        $type = UrlParser::getDocumentType($item['link']);
        $file_name = UrlParser::getDocumentFilename($item['link']);
        if (empty($file_name)) {
            $file_name = date("Y-m-d-H-i");
        }
        if (!empty($type)) {
            $file_name .= ".$type";
        }
        list($group_id, $page_id, $sub_path, $file_pattern) =
            $group_model->getGroupIdPageIdSubPathFromName($podcast['WIKI_PAGE'],
            $podcast['LANGUAGE']);
        $file_name = $this->makeFileNamePattern($file_name, $file_pattern,
            substr($item['title'], 0, C\NAME_LEN), $pubdate);
        $file_name = $controller->clean($file_name, "file_name");
        $type = UrlParser::getDocumentType($file_name);
        if (!empty($item['link'])) {
            $data = $this->downloadPodcastItem($item['link'], $type);
            if ($data) {
                $group_model->copyFileToGroupPageResource("", $file_name,
                    L\mimeType($file_name), $group_id, $page_id, $sub_path,
                    $data);
                $podcast["PREVIOUSLY_DOWNLOADED"][$item['guid']] = [
                    "FILENAME" => $file_name,
                    "SAVE_TIMESTAMP" => time()
                ];
                return true;
            }
        }
        return false;
    }
    /**
     * Helper method to @see downloadPodcastItemIfNew called when it is known
     * that a podcast item should be downloaded. It downloads a podcast item.
     * If the podcast item is an intermediate file pointing to several
     * items to download such as video. It downloads these and concatenates
     * them to makes a single video.
     *
     * @param string $url of podcast item to download
     * @param string $type file type of podcast item
     * @param array $audiolist_urls an array of audio urls to download
     *      if this has already been obtained
     * @return string with podcast item if successful or false otherwise
     */
    public function downloadPodcastItem($url, $type = "mp4",
        $audiolist_urls =[])
    {
        L\crawlLog("Downloading ...$url");
        $data = $this->getPage($url);
        if (substr($data, 0, 6) != "#EXTM3") {
            return $data;
        }
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        if (!$this->makeFolder($convert_folder)) {
            return false;
        }
        $convert_folder .= "/" . L\crawlHash($url);
        if (!$this->makeFolder($convert_folder)) {
            return false;
        }
        if (in_array($type, ['mp4', 'm4v', 'mov', 'avi']) &&
            preg_match('/\#EXT\-X\-MEDIA\s*\:\s*TYPE\s*=\s*AUDIO[^\n]+/', $data,
            $audio_match)) {
            $audio_line = $audio_match[0];
            if (preg_match("/URI\s*\=\s*[\"\']([^\"\']+)[\"\']/i", $audio_line,
                $audio_uri_match)) {
                $audio_url = $audio_uri_match[1];
                if (preg_match("/GROUP-ID\s*\=\s*[\"\']([^\"\']+)[\"\']/i",
                    $audio_line, $audio_group_id_match)) {
                    $group_id = $audio_group_id_match[1];
                }
                if (!empty($group_id) &&
                    !empty($audio_url = $audio_uri_match[1])) {
                    $audio_url = UrlParser::canonicalLink($audio_url, $url);
                    $audio_data = $this->getPage($audio_url);
                    $audiolist_urls = [];
                    $lines = preg_split('/(\r)?\n/', $audio_data);
                    $num_lines = count($lines);
                    for ($i = 0; $i  < $num_lines; $i++) {
                        if (stristr($lines[$i], "#EXTINF") &&
                            !empty($lines[$i + 1])) {
                            $audiolist_urls[] = UrlParser::canonicalLink(
                                trim($lines[$i + 1]), $audio_url);
                        }
                    }
                }
            }
        }
        if (empty($audiolist_urls)) {
            $group_id = "";
        }
        if (stripos($data, "#EXT-X-STREAM-INF") !== false) {
            $lines = preg_split('/(\r)?\n/', $data);
            $max_url = "";
            $max_res = 0;
            $num_lines = count($lines);
            for ($i = 0; $i  < $num_lines; $i++) {
                if (preg_match('/\s*\#EXT\-X\-STREAM\-INF(.*)' .
                    preg_quote($group_id) . '.*/i', $lines[$i],
                    $matches)) {
                    if (empty($max_url) && !empty($lines[$i + 1])) {
                        $max_url = $lines[$i + 1];
                    }
                }
                if (!empty($matches[1]) &&
                    preg_match('/RESOLUTION\=(.+)x(.+)/', $matches[1],
                    $sub_matches)) {
                    $width = intval($sub_matches[1]);
                    $height = intval($sub_matches[2]);
                    $res = $width * $height;
                    if ($max_res < $res && !empty($lines[$i + 1])) {
                        $max_url = $lines[$i + 1];
                        $max_res = $res;
                    }
                }
            }
            if (empty($max_url)) {
                return false;
            }
            $max_url = UrlParser::canonicalLink($max_url, $url);
            return $this->downloadPodcastItem($max_url, $type,
                $audiolist_urls);
        }
        $segments_txt_file = $convert_folder . "/segments.txt";
        file_put_contents($segments_txt_file, "");
        // find the video segments file for today's news
        $seen = [];
        $playlists = preg_split("/\#EXT\-X\-MEDIA\-SEQUENCE\s*\:\s*\d*/",
            $data);
        if (count($playlists) > 1) {
            array_shift($playlists);
        }
        $playlist = $playlists[0];
        //get any encryption file if needed
        preg_match_all('/\#EXT\-X\-KEY\:METHOD\=AES\-128,URI\=\"(.*)\"/',
            $playlist, $key_file_matches);
        if (!empty($key_file_matches[1][0])) {
            $key_file = $key_file_matches[1][0];
            $key_url = UrlParser::canonicalLink($key_file, $url);
            L\crawlLog("Downloading encrypt key url... $key_url");
            $key = FetchUrl::getPage($key_url);
        }
        // Now get playlist urls to download
        $playlist_urls = [];
        $lines = preg_split('/(\r)?\n/', $playlist);
        $num_lines = count($lines);
        for ($i = 0; $i  < $num_lines; $i++) {
            if (stristr($lines[$i], "#EXTINF") && !empty($lines[$i + 1])) {
                $playlist_urls[] = trim($lines[$i + 1]);
            }
        }
        $i = 0;
        foreach ($playlist_urls as $playlist_url) {
            $play_name = UrlParser::getDocumentFilename($playlist_url);
            $file_name = $play_name . "." . UrlParser::getDocumentType(
                $playlist_url);
            if (!file_exists("$convert_folder/$file_name")) {
                $item_url = UrlParser::canonicalLink($playlist_url, $url);
                L\crawlLog("Downloading item url... $item_url");
                $data = $this->getPage($item_url);
                if (!empty($key)) {
                    $data = openssl_decrypt($data, "AES-128-CBC", $key,
                        OPENSSL_RAW_DATA);
                }
                $download_item_name = "$convert_folder/$file_name";
                L\crawlLog("...Writing downloaded file to $download_item_name");
                file_put_contents($download_item_name, $data);
                chmod($download_item_name, 0777);
                if (!empty($audiolist_urls[$i])) {
                    $audio_url = $audiolist_urls[$i];
                    $audio_name = UrlParser::getDocumentFilename($audio_url) .
                        "." . UrlParser::getDocumentType($audio_url);
                    $download_audio_name = "$convert_folder/audio-$audio_name";
                    if (!file_exists($download_audio_name)) {
                        L\crawlLog("Downloading audio url... $audio_url");
                        $audio_data = $this->getPage($audio_url);
                        if (!empty($key)) {
                            $audio_data  = openssl_decrypt($audio_data ,
                                "AES-128-CBC", $key, OPENSSL_RAW_DATA);
                        }
                        L\crawlLog("...Writing downloaded file to " .
                            "$download_audio_name");
                        file_put_contents($download_audio_name, $audio_data);
                        chmod($download_audio_name, 0777);
                    }
                    if (file_exists($download_item_name) &&
                        file_exists($download_audio_name)) {
                        $combine_name = "$convert_folder/".
                            "combine-$play_name.mp4";
                        $combine_ffmpeg = "ffmpeg -i $download_item_name " .
                            " -i $download_audio_name -c:v copy -c:a aac ".
                            "-strict experimental $combine_name";
                        L\crawlLog("...Combinining audio and video to  " .
                            "$combine_name with command\n $combine_ffmpeg");
                        exec($combine_ffmpeg);
                        chmod($combine_name, 0777);
                        $file_name = "combine-$play_name.mp4";
                    }
                }
            }
            file_put_contents($segments_txt_file,
                "file '$convert_folder/$file_name'\n", FILE_APPEND);
            $i++;
        }
        $out_file = "$convert_folder/out.$type";
        $ffmpeg = "ffmpeg -f concat -safe 0 -i $segments_txt_file " .
            " -c copy $out_file";
        L\crawlLog($ffmpeg);
        exec($ffmpeg);
        chmod($out_file, 0777);
        $data = file_get_contents($out_file);
        $model = new GroupModel();
        $model->db->unlinkRecursive($convert_folder);
        return $data;
    }
    /**
     * Used to construct a filename for a downloaded podcast item suitable
     * to be used when stored in a wiki page's resource folder
     *
     * @param string $file_name name of file
     * @param string $file_pattern string which can contain %F for previous
     *   filename, %T for title, and date %date_command, for example,
     *   %Y for year,  %m for month, %d for day, etc. These will be substituted
     *   with their values when wriitng out the wiki name for the downloaded
     *   podcast item.
     * @param string $title a title string for wiki item
     * @param int $pubdate when the wiki item was published as a Unix timestamp.
     *  The value of this is used when computing values for the $file_pattern
     * @return string output filename for wiki item
     */
    private function makeFileNamePattern($file_name, $file_pattern,
        $title = "", $pubdate = null)
    {
        $translates = ["'" => "", "\"" => "", "/" => "-", "|" => "-"];
        $file_name_parts = explode("?", $file_name);
        $file_name = strtr(basename($file_name_parts[0]), $translates);
        $title = strtr($title, $translates);
        if (empty($file_pattern)) {
            return $file_name;
        }
        if (!$pubdate) {
            $pubdate = time();
        }
        $pattern_parts = preg_split('/(\%\w)/u', $file_pattern, -1,
            PREG_SPLIT_DELIM_CAPTURE);
        $out_name = "";
        foreach ($pattern_parts as $pattern_part) {
            if (!empty($pattern_part[0]) && $pattern_part[0] == '%') {
                if ($pattern_part[1] == 'F') {
                    $out_name .= $file_name;
                } else if ($pattern_part[1] == 'T') {
                    $out_name .= $title;
                } else {
                    $out_name .= date($pattern_part[1], $pubdate);
                }
            } else {
                $out_name .= $pattern_part;
            }
        }
        //just in case the name came from a url with extra garbbage
        return $out_name;
    }
    /**
     * Makes a directory in a way compatible with yioop's error handling.
     *
     * @param string $folder name of directory/folder to create.
     * @return boolean whether directory was created
     */
    private function makeFolder($folder)
    {
        if (!file_exists($folder)) {
            set_error_handler(null);
            @mkdir($folder);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            if (!file_exists($folder)) {
                L\crawlLog("----Unable to create folder. Bailing!");
                return false;
            }
        }
        return true;
    }
    /**
     * Downloads the internet page with the give url.
     *
     * @param $url The url want to download
     * @return string contents of downloaded page
     */
    private function getPage($url)
    {
        return FetchUrl::getPage($url, null, false, null,
            4 * C\SINGLE_PAGE_TIMEOUT);
    }
}
