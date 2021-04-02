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
use seekquarry\yioop\library\FeedArchiveBundle;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\processors\ImageProcessor;

/**
 * A media job to download and index feeds from various search sources (RSS,
 * HTML scraper, etc). Idea is that this job runs once an hour to get the
 * latest news, movies, weather from those sources.
 */
class FeedsUpdateJob extends MediaJob
{

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
     * The FeedArchiveBundle to put feed items into periodically
     * @var FeedArchiveBundle
     */
    public $index_archive;
    /**
     * News Feed Items found from the current feed
     * @var array
     */
    public $found_items;
    /**
     * Used to keep track of image urls of thumbnails to download
     * for feed items
     * @var array
     */
    public $media_urls;
    /**
     * Mamimum number of feeds to download in one try
     */
    const MAX_FEEDS_ONE_GO = 100;
    /**
     * Mamimum number of thumb_urls to download in one try
     */
    const MAX_THUMBS_ONE_GO = 100;
   /**
     * how long in seconds before a feed item expires
     */
    const OLD_ITEM_TIME = 4 * C\ONE_WEEK;
    /**
     * Initializes the last update time to far in the past so, feeds will get
     * immediately updated. Sets up connect to DB to store feeds items, and
     * makes it so the same media job runs both on name server and client
     * Media Updaters
     */
    public function init()
    {
        $this->update_time = 0;
        $this->media_urls = [];
        $this->name_server_does_client_tasks = true;
        $this->name_server_does_client_tasks_only = true;
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
        $this->getFeedArchive();
        C\nsconddefine("FEEDS_UPDATE_INTERVAL", C\ONE_HOUR);
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
        if ($delta > C\FEEDS_UPDATE_INTERVAL) {
            $this->update_time = $time;
            L\crawlLog("Performing medias feeds update");
            return true;
        }
        L\crawlLog("Time since last update not exceeded, skipping feed
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
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE (TYPE='rss'
             OR TYPE='html' OR TYPE='json' OR TYPE='regex')";
        $result = $db->execute($sql);
        $feeds = [];
        while ($feed = $db->fetchArray($result)) {
            $this->parseFeedAuxInfo($feed);
            $feeds[] = $feed;
        }
        $this->tasks = $feeds;
        $this->doTasks($feeds);
    }
    /**
     * For each feed source downloads the feeds, checks which items are
     * new, and makes an array of them. Then calls the method to add these
     * items to both the IndexArchiveBundle for feeds
     *
     * @param array $tasks array of feed info (url to download, paths to
     *  extract etc)
     */
    public function doTasks($tasks)
    {
        if (!is_array($tasks)) {
            L\crawlLog(
                "----This media updater is NOT responsible for any feeds!");
            return;
        }
        $feeds = $tasks;
        L\crawlLog("----This media updater is responsible for the feeds:");
        $i = 1;
        foreach ($feeds as $feed) {
            L\crawlLog("----  $i. " . $feed["NAME"]);
            $i++;
        }
        $num_feeds = count($feeds);
        $feeds_one_go = self::MAX_FEEDS_ONE_GO;
        $limit = 0;
        $this->found_items = [];
        while ($limit < $num_feeds) {
            $feeds_batch = array_slice($feeds, $limit, $feeds_one_go);
            $this->updateFoundItemsOneGo($feeds_batch, self::OLD_ITEM_TIME);
            $limit += $feeds_one_go;
        }
        $this->addFoundItemsShard(self::OLD_ITEM_TIME);
        L\crawlLog("----Downloading Media Urls");
        $thumb_sites = [];
        for($i = 0; $i < count($this->media_urls); $i++) {
            if (count($thumb_sites) > self::MAX_THUMBS_ONE_GO) {
                $this->getThumbs($thumb_sites);
                $thumb_sites = [];
            }
            if (!file_exists($this->media_urls[$i][self::FILE_NAME])) {
                $thumb_sites[] = $this->media_urls[$i];
            }
        }
        if (count($thumb_sites) > 0) {
            $this->getThumbs($thumb_sites);
        }
        L\crawlLog("----Clearing Media Urls from Update");
        $this->media_urls = [];
    }
    /**
     * Download images and create thumbnails for a list of image urls.
     *
     * @param array $thumb_sites array of arrays. The sub-array should contain
     *  a field CrawlConstants::THUMB_URL with url to download.
     *  After download the thumb_nail is saved in the file
     * CrawlConstants::FILE_NAME.
     */
    private function getThumbs($thumb_sites)
    {
        $thumb_sites = FetchUrl::getPages($thumb_sites, false,
            C\PAGE_RANGE_REQUEST, null, self::THUMB_URL, self::DATA,
            true);
        foreach ($thumb_sites as $thumb_site) {
            if (!empty($thumb_site[self::DATA])) {
                set_error_handler(null);
                $image = @imagecreatefromstring($thumb_site[self::DATA]);
                $thumb_string = ImageProcessor::createThumb($image);
                set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                if ($thumb_string) {
                    file_put_contents($thumb_site[self::FILE_NAME],
                        $thumb_string);
                }
            }
        }
    }
    /**
     * Handles the request to get the  array of feed sources which hash to
     * a particular value i.e. match with the index of requesting machine's
     * hashed url/name from array of available machines hash
     *
     * @param int $machine_id id of machine making request for feeds
     * @param array $data not used but inherited from the base MediaJob
     *      class as a parameter (so will alwasys be null in this case)
     * @return array of feed urls and paths to extract from them
     */
    public function getTasks($machine_id, $data = null)
    {
        $parent = $this->controller;
        if (!$parent) {
            return;
        }
        $source_model = $parent->model("source");
        $current_machine = $machine_id;
        $machine_hashes = $source_model->getMachineHashUrls();
        $machine_index_match = array_search($current_machine, $machine_hashes);
        if ($machine_index_match === false) {
            return [];
        }
        $num_machines = count($machine_hashes);
        $pre_feeds = $source_model->getMediaSources("rss");
        $pre_feeds = array_merge($pre_feeds,
            $source_model->getMediaSources("html"));
        $pre_feeds = array_merge($pre_feeds,
            $source_model->getMediaSources("json"));
        $pre_feeds = array_merge($pre_feeds,
            $source_model->getMediaSources("regex"));
        if (!$pre_feeds) {
            return false;
        }
        $feeds = [];
        foreach ($pre_feeds as $pre_feed) {
            if (!isset($pre_feed['NAME'])) {
                continue;
            }
            $hash_int = unpack("N", L\crawlHash($pre_feed['NAME']));
            if (!isset($hash_int[1])) {
                continue;
            }
            $hash_index = ($hash_int[1]) % $num_machines;
            if ($machine_index_match != $hash_index) {
                continue;
            }
            $this->parseFeedAuxInfo($pre_feed);
            $feeds[] = $pre_feed;
        }
        return $feeds;
    }
    /**
     * Downloads one batch of $feeds_one_go feed items for @see updateFeedItems
     * For each feed source downloads the feeds, checks which items are
     * not in the database, adds them. This method does not update
     * the inverted index shard.
     *
     * @param array $feeds list of feeds to download
     * @param int $age how many seconds old records should be ignored
     * @param boolean $test_mode if true then rather then update items in
     *  database, returns as a string the found feed items for the given feeds
     * @return mixed either true, or if $test_mode is true then the results
     *      as a string of downloading the feeds and extracting the feed items
     */
    public function updateFoundItemsOneGo($feeds, $age = C\ONE_WEEK,
        $test_mode = false)
    {
        $test_results = "";
        $log_function = function ($msg, $log_tag = "pre class='source-test'")
            use (&$test_results, $test_mode) {
            $close_tag = preg_split("/\s+/",$log_tag)[0];
            if ($test_mode) {
                $test_results .= "<$log_tag>$msg</$close_tag>\n";
            } else {
                L\crawlLog($msg);
            }
        };
        //initialize xlink image folder
        $base_dir = C\APP_DIR . "/resources";
        if (!file_exists($base_dir)) {
            mkdir($base_dir);
            chmod($base_dir, 0777);
        }
        $subfolder = L\crawlHash(
            'group' . C\PUBLIC_GROUP_ID . C\AUTH_KEY);
        $prefix_folder = substr($subfolder, 0, 3);
        $subfolder = "t" . $subfolder;
        $date = date('Y-m-d');
        $sep = "";
        foreach ([$prefix_folder, $subfolder, $date]
            as $add_folder) {
            $base_dir .= "/$add_folder";
            if (!file_exists($base_dir)) {
                mkdir($base_dir);
                chmod($base_dir, 0777);
            }
        }
        // now get feed items
        $image_url = "feed://$date/";
        $feeds = FetchUrl::getPages($feeds, false, 0, null, "SOURCE_URL",
            CrawlConstants::PAGE, true, null, true);
        $sql = "UPDATE MEDIA_SOURCE SET LANGUAGE=? WHERE TIMESTAMP=?";
        $db = $this->db;
        libxml_use_internal_errors(true);
        foreach ($feeds as $feed) {
            if (empty($feed[CrawlConstants::PAGE])) {
                $log_function("...No data in {$feed['NAME']} feed skipping.",
                    "h2");
                continue;
            }
            $log_function("----Updating {$feed['NAME']}.", "h2");
            $log_function("...Checking encoding", "h3");
            L\convertUtf8IfNeeded($feed, CrawlConstants::PAGE,
                CrawlConstants::ENCODING, $log_function);
            if ($test_mode) {
                $log_function("...Downloaded Feed Data was:",
                    "h3");
                $feed_contents = $feed[CrawlConstants::PAGE];
                if (in_array($feed['TYPE'], ['html', 'rss'])) {
                    $out_dom = L\getDomFromString($feed_contents);
                    $feed_contents =  $out_dom->saveHTML();
                    $feed_contents = "<textarea disabled='disabled'>" .
                        htmlentities($feed_contents) . "</textarea>";
                } else if ($feed['TYPE'] == 'json') {
                    $json_object = json_decode($feed_contents);
                    $feed_contents = json_encode($json_object,
                        JSON_PRETTY_PRINT);
                    $feed_contents = htmlentities($feed_contents);
                } else {
                    $feed_contents = htmlentities($feed_contents);
                }
                $log_function($feed_contents);
            }
            $is_html = ($feed['TYPE'] == 'html') ? true : false;
            $is_json = ($feed['TYPE'] == 'json') ? true : false;
            $is_regex = ($feed['TYPE'] == 'regex') ? true : false;
            if (!$is_regex) {
                $log_function("Making dom object from feed.", "h3");
                $dom = new \DOMDocument();
            }
            if ($is_json) {
                $json_decode = json_decode($feed[CrawlConstants::PAGE], true);
                $page = "<html><body>".
                    $this->convertJsonDecodeToTags($json_decode) .
                    "</body></html>";
                $is_html = true;
            } else if ($is_regex) {
                $page = $feed[CrawlConstants::PAGE];
            } else {
                //strip namespaces
                $page = preg_replace('@<(/?)(\w+\s*)\:@u', '<$1',
                    $feed[CrawlConstants::PAGE]);
                if (empty($page)) {
                    $page = $feed[CrawlConstants::PAGE];
                }
            }
            if (isset($feed['IMAGE_XPATH']) && !$is_regex) {
                $feed['IMAGE_XPATH'] = preg_replace('@/(\s*\w+\s*)\:@u', '/',
                    $feed['IMAGE_XPATH']);
                if (empty($feed['IMAGE_XPATH'])) {
                    $feed['IMAGE_XPATH'] = $feed['IMAGE_XPATH'];
                }
            }
            if ($is_html) {
                $page = preg_replace("@<script@", "<hscript", $page);
                $page = preg_replace("@</script@", "</hscript", $page);
                @$dom->loadHTML($page);
            } else if (!$is_regex) {
                /*
                    We parse using loadHTML as less strict. loadHTML
                    auto-closes link tags immediately after open link
                    so to avoid this we replace link with xlink
                 */
                $page = preg_replace("@<link@", "<slink", $page);
                $page = preg_replace("@</link@", "</slink", $page);
                $page = preg_replace("@pubDate@i", "pubdate", $page);
                $page = preg_replace("@&lt;@", "<", $page);
                $page = preg_replace("@&gt;@", ">", $page);
                $page = preg_replace("@<!\[CDATA\[(.+?)\]\]>@s", '$1', $page);
                // we also need a hack to make UTF-8 work correctly
                $dom = L\getDomFromString($page);
            }
            $log_function("----...done. Extracting info about whole feed.",
                "h3");
            $lang = "";
            if (!in_array($feed['TYPE'], ['html', 'regex']) &&
                empty($feed["LANGUAGE"])) {
                $languages = $dom->getElementsByTagName('language');
                if ($languages && is_object($languages) &&
                    is_object($languages->item(0))) {
                    $lang = $languages->item(0)->textContent;
                    $db->execute($sql, [$lang, $feed['TIMESTAMP']]);
                }
            } else if (!empty($feed["LANGUAGE"])) {
                $lang = $feed["LANGUAGE"];
            } else {
                $lang = C\DEFAULT_LOCALE;
            }
            $log_function("----...Language is $lang. Getting " .
                "channel, finding nodes.", "h3");
            if ($is_html) {
                $query = $feed['CHANNEL_PATH'];
                $query = preg_replace("/script/u", "hscript", $query);
                $sub_dom = $this->getTags($dom, $query);
                if (!$sub_dom) {
                    $log_function("----... Scraper couldn't parse channel".
                        " path so bailing on this feed.", "h3");
                    continue;
                } else {
                    $log_function("----...Channel scraped.", "h3");
                }
                $query = $feed['ITEM_PATH'];
                $query = preg_replace("/script/u", "hscript", $query);
                $nodes = $this->getTags($sub_dom[0], $query);
                $item_elements = ["title" => $feed['TITLE_PATH'],
                    "description" => $feed['DESCRIPTION_PATH'],
                    "link" => $feed['LINK_PATH']];
            } else if ($is_regex) {
                preg_match($feed['CHANNEL_PATH'], $page, $matches);
                if ($test_mode) {
                    $log_function("----Scraped channel is:", "h3");
                }
                $channel = "";
                $nodes = [];
                if (!empty($matches[1])) {
                    if ($test_mode) {
                        $log_function($matches[1]);
                    }
                    $nodes = preg_split($feed['ITEM_PATH'], $matches[1]);
                }
                if (empty($nodes)) {
                    $log_function("----... Scraper couldn't parse channel" .
                        " path so bailing on this feed.", "h3");
                    continue;
                }
                $log_function("----...Items scraped from channel, " .
                    "getting info.", "h3");
                $item_elements = ["title" => $feed['TITLE_PATH'],
                    "description" => $feed['DESCRIPTION_PATH'],
                    "link" => $feed['LINK_PATH']];
            } else {
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
            }
            $log_function("----...done extracting info. Check for new feed ".
                "items in {$feed['NAME']}.", "h3");
            if ($test_mode) {
                $log_function("----...Found following items...", "h3");
                $log_function("<hr />");
            }
            $num_added = 0;
            $num_seen = 0;
            $base_dir = C\APP_DIR . "/resources";
            $folder = L\crawlHash(
                'group' . C\PUBLIC_GROUP_ID . C\AUTH_KEY);
            $prefix = substr($folder, 0, 3);
            foreach ($nodes as $node) {
                $item = [];
                $unique_fields = ['link'];
                foreach ($item_elements as $db_element => $feed_element) {
                    if (!$test_mode) {
                        L\crawlTimeoutLog(
                            "----still adding feed items to index.");
                    }
                    if ($db_element == "link" && substr($feed_element, 0, 4)
                        == "http") {
                        $unique_fields = ['title', 'description'];
                        $element_text = $feed_element;
                        $item[$db_element] = strip_tags($element_text);
                        continue;
                    }
                    if ($is_html) {
                        $tag_nodes = $this->getTags($node, $feed_element);
                        if (!isset($tag_nodes[0])) {
                            $tag_node = null;
                        } else {
                            $tag_node = $tag_nodes[0];
                        }
                        $element_text = (is_object($tag_node)) ?
                            $tag_node->textContent: "";
                    } else if ($is_regex) {
                        preg_match($feed_element, $node, $matches);
                        $tag_node = null;
                        $element_text =
                            (empty($matches[1])) ? "" : $matches[1];
                    } else {
                        if (!is_array($feed_element)) {
                            $feed_element = [$feed_element];
                        }
                        foreach ($feed_element as $tag_name) {
                            $tag_node = $node->getElementsByTagName(
                                    $tag_name)->item(0);
                            $element_text = (is_object($tag_node)) ?
                                $tag_node->nodeValue: "";
                            if ($element_text) {
                                break;
                            }
                        }
                    }
                    if ($db_element == "link" && !empty($tag_node) &&
                        !empty($tag_node->documentElement) &&
                        ($element_text == "" || ($is_html && !$is_json))) {
                        if ($is_html) {
                            $element_text =
                               $tag_node->documentElement->getAttribute("href");
                        } else {
                            $element_text = $tag_node->getAttribute("href");
                        }
                        $element_text = UrlParser::canonicalLink($element_text,
                            $feed["SOURCE_URL"]);
                    }
                    if ($db_element == "link" && $tag_node && $is_json) {
                        $element_text = UrlParser::canonicalLink($element_text,
                            $feed["SOURCE_URL"]);
                    }
                    $item[$db_element] = strip_tags($element_text);
                }
                $item['image_link'] = "";
                if (!empty($feed['IMAGE_XPATH'])) {
                    if ($feed['IMAGE_XPATH'][0]=="^") {
                        $is_channel_image = true;
                        $image_xpath = substr($feed['IMAGE_XPATH'], 1);
                    } else {
                        $is_channel_image = false;
                        $image_xpath = $feed['IMAGE_XPATH'];
                    }
                    if ($is_html) {
                        if ($is_channel_image) {
                            $dom_xpath = new \DOMXPath($sub_dom);
                        } else {
                            $dom_xpath = new \DOMXPath($node);
                        }
                        $image_nodes =
                            $dom_xpath->evaluate($image_xpath);
                        if ($image_nodes && $image_nodes->item(0)) {
                            $item['image_link'] =
                                $image_nodes->item(0)->nodeValue;
                        }
                    } else if ($is_regex) {
                        preg_match($feed['IMAGE_XPATH'], $node, $matches);
                        $item['image_link'] = (empty($matches[1])) ? "" :
                            $matches[1];
                    } else {
                        $dom_xpath = new \DOMXPath($dom);
                        if ($is_channel_image) {
                            $query = $image_xpath;
                        } else {
                            $query = $node->getNodePath() . $image_xpath;
                        }
                        libxml_clear_errors();
                        $image_nodes = $dom_xpath->evaluate($query);
                        if ($image_nodes && $image_nodes->item(0)) {
                            $item['image_link'] =
                                $image_nodes->item(0)->nodeValue;
                        }
                    }
                    if (!empty($item['image_link'])) {
                        $item['image_link'] = UrlParser::canonicalLink(
                            $item['image_link'], $feed["SOURCE_URL"]);
                        $image_hash = L\crawlHash($item['image_link']);
                        $image_link = $item['image_link'];
                        if (!$test_mode) {
                            $item['image_link'] = $image_url . $image_hash;
                        }
                    }
                }
                if (!empty($item['link']) && !empty($item['title']) &&
                    !empty($item_elements['link'])
                    && $item['link'] == $item_elements['link']) {
                    $item['link'] .= "#" . L\crawlHash($item['title']);
                }
                if ($test_mode) {
                    foreach ($item as $field_name => $field_value) {

                        if (is_array($field_value)) {
                            $log_function("<div><b>$field_name</b>: " .
                                print_r($field_value, true) . "</div>");
                        } else if (stristr($field_name, "link") !== false) {
                            $log_function("<div><b>$field_name</b>: <a href='" .
                                "$field_value'>$field_value</a></div>");
                        } else {
                            $log_function("<div><b>$field_name</b>: " .
                                "$field_value</div>");
                        }
                    }
                    $log_function("<hr />");
                    $did_add = true;
                } else {
                    $did_add = $this->addFeedItemIfNew($item, $feed['NAME'],
                        $lang, $age, $unique_fields);
                }
                if ($did_add) {
                    if (!empty($item['image_link'])) {
                        $image_path = "$base_dir/$prefix/t$folder/".
                            "$date/$image_hash.jpg";
                        $this->media_urls[] = [
                            self::FILE_NAME => $image_path,
                            self::THUMB_URL => $image_link];
                    }
                    $num_added++;
                }
                $num_seen++;
            }
            $log_function("----...added $num_added feed items of $num_seen ".
                "on rss page.\n Done Processing {$feed['NAME']}.");
        }
        return ($test_mode) ? $test_results : true;
    }
    /**
     * Returns an array of DOMDocuments for the nodes that match an xpath
     * query on $dom, a DOMDocument
     *
     * @param DOMDocument $dom document to run xpath query on
     * @param string $query xpath query to run
     * @return array of DOMDocuments one for each node matching the
     *  xpath query in the orginal DOMDocument
     */
    public function getTags($dom, $query)
    {
        $nodes = [];
        $dom_xpath = new \DOMXPath($dom);
        if (!$dom_xpath) {
            return [];
        }
        $tags = $dom_xpath->query($query);
        if (empty($tags) || $tags->length == 0) {
            if(!empty($dom->textContent)) {
                $redom = new \DOMDocument;
                @$redom->loadHTML($dom->textContent);
                $dom_xpath = new \DOMXPath($dom);
                if (!$dom_xpath) {
                    return [];
                }
                $tags = $dom_xpath->query($query);
            }
            if (empty($tags) || $tags->length == 0) {
                return [];
            }
        }
        $i = 0;
        while ($item = $tags->item($i)) {
            $tmp_dom = new \DOMDocument;
            $tmp_dom->formatOutput = true;
            $node = $tmp_dom->importNode($item, true);
            $tmp_dom->appendChild($node);
            $nodes[] = $tmp_dom;
            $i++;
        }
        return $nodes;
    }
    /**
     * Copies all feeds items newer than $age to a new shard, then deletes
     * old index shard and database entries older than $age. Finally sets copied
     * shard to be active. If this method is going to take max_execution_time/2
     * it returns false, so an additional job can be schedules; otherwise
     * it returns true
     * @param int $age how many seconds old records should be deleted
     *
     * @return bool whether job executed to complete
     */
    public function addFoundItemsShard($age)
    {
        $items = $this->found_items;
        $pre_feeds = $this->tasks;
        if (!$pre_feeds) {
            return false;
        }
        $feeds = [];
        foreach ($pre_feeds as $pre_feed) {
            if (!isset($pre_feed['NAME'])) {
                continue;
            }
            $feeds[$pre_feed['NAME']] = $pre_feed;
        }
        $time = time();
        $tmp_shard_name = C\WORK_DIRECTORY . "/data/tmp_index";
        $tmp_shard = new IndexShard($tmp_shard_name);
        $num_sites = 0;
        if (!$tmp_shard) {
            return false;
        }
        $db = $this->db;
        $completed = true;
        L\crawlLog("----.. Creating inverted index of new items.");
        $i = 0;
        $term_counts = [];
        $seen_url_count = 0;
        usort($items, function($i1, $i2) {
            $pub1 = (empty($i1['PUBDATE'])) ? time() :
                $i1['PUBDATE'];
            $pub2 = (empty($i2['PUBDATE'])) ? time() :
                $i2['PUBDATE'];
            if ($pub1 == $pub2) {
                return 0;
            }
            return ($pub1 < $pub2) ? -1 : 1;
        });
        /* A single shard holds around NUM_DOCS_PER_GENERATION items. We cut
            it off at 0.9 of this value just to be safe.
         */
        $limit = 0.9 * C\NUM_DOCS_PER_GENERATION;
        foreach ($items as $item) {
            L\crawlTimeoutLog(
                "----..have added %s items to new item index.", $i);
            $i++;
            if (!isset($item['SOURCE_NAME'])) {
                continue;
            }
            $source_name = $item['SOURCE_NAME'];
            if (isset($feeds[$source_name])) {
                $lang = $feeds[$source_name]['LANGUAGE'];
                $media_category = $feeds[$source_name]['CATEGORY'];
                $source_stop_regex =
                    $feeds[$source_name]['TRENDING_STOP_REGEX'];
            } else {
                $lang = "";
                $media_category = "news";
                $source_stop_regex = "";
            }
            $phrase_string = $item["TITLE"] . " " . $item["DESCRIPTION"];
            $word_and_qa_lists = PhraseParser::extractPhrasesInLists(
                $phrase_string, $lang);
            $raw_guid = L\unbase64Hash($item["GUID"]);
            $doc_keys = L\crawlHash($item["LINK"], true) .
                $raw_guid . "d". substr(L\crawlHash(
                UrlParser::getHost($item["LINK"]) . "/", true), 1);
            $meta_ids = $this->calculateMetas($lang, $item['PUBDATE'],
                $source_name, $item["GUID"], $media_category);
            $len = strlen($phrase_string);
            $word_list = $word_and_qa_lists["WORD_LIST"];
            if (PhraseParser::computeSafeSearchScore($word_list, $len,
                $item["LINK"]) < 0.012) {
                $meta_ids[] = "safe:true";
                $meta_ids[] = "safe:all";
            } else {
                $meta_ids[] = "safe:false";
                $meta_ids[] = "safe:all";
            }
            $tmp_shard->addDocumentWords($doc_keys,
                self::NEEDS_OFFSET_FLAG, $word_and_qa_lists["WORD_LIST"],
                $meta_ids, true, false);
            $this->updateTrendingTermCounts($term_counts, $phrase_string,
                $word_and_qa_lists["WORD_LIST"], $media_category,
                $source_name, $lang,
                $item['PUBDATE'], $source_stop_regex);
            $seen_url_count += 1;
            $page = [];
            $page[self::TITLE] = $item['TITLE'];
            $page[self::DESCRIPTION] = $item['DESCRIPTION'];
            $page[self::URL] = $item['LINK'];
            $page[self::HASH] = $item['GUID'];
            $page[self::SOURCE_NAME] = $item['SOURCE_NAME'];
            $page[self::IMAGE_LINK] = $item['IMAGE_LINK'];
            $page[self::PUBDATE] = $item['PUBDATE'];
            $seen_sites[] = $page;
            if ($seen_url_count > $limit) {
                L\crawlLog("----..found more than $limit items. ".
                    "Saving to bundle first.");
                $this->addFoundItemsInvertedIndex($tmp_shard, $seen_sites,
                    $seen_url_count);
                $tmp_shard = new IndexShard($tmp_shard_name);
                $seen_url_count = 0;
            }
        }
        if ($seen_url_count > 0) {
            $this->addFoundItemsInvertedIndex($tmp_shard, $seen_sites,
                $seen_url_count);
        }
        unset($term_counts['seen']);
        $this->addTermCountsTrendingTable($db, $term_counts);
        set_error_handler(null);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    }
    /**
     * Helper method for addFoundItemsShard(). Checks if the current
     * shard is full or not and adds items to it.
     * @param IndexShard $tmp_shard a temporary shard holding all necessary
     *  information
     * @param array $seen_sites of the sites and their corresponding hash
     * @param int $seen_url_count of how many sites have been seen before
     *  committing data to one shard
     *
     * @return bool whether job executed to complete
     */
    public function addFoundItemsInvertedIndex($tmp_shard, $seen_sites,
            $seen_url_count) {
        L\crawlLog("----..adding items to IndexArchiveBundle");
        // 1. check if index shard is full or not. if it is, new gen
        $index_archive = $this->getFeedArchive();
        $generation = $index_archive->initGenerationToAdd(
            $tmp_shard->num_docs);
        $summary_offsets = [];
        if (!empty($seen_sites)) {
            // 2. add pages, get summary_offset
            $index_archive->addPagesAndSeenKeys($generation,
                self::SUMMARY_OFFSET, self::HASH, $seen_sites, $seen_url_count);
            foreach ($seen_sites as $site) {
                $site_url = str_replace('|', "%7C", $site[self::URL]);
                $host = UrlParser::getHost($site_url);
                $raw_guid = L\unbase64Hash($site[self::HASH]);
                $hash = L\crawlHash($site[self::URL], true) .
                    $raw_guid . "d". substr(L\crawlHash(
                    UrlParser::getHost($site[self::URL]) . "/", true), 1);
                $summary_offsets[$hash] = $site[self::SUMMARY_OFFSET];
            }
            unset($seen_sites);
        }
        $tmp_string = $tmp_shard->save(true, true);
        $tmp_shard = IndexShard::load("feed_data", $tmp_string);
        if (!empty($summary_offsets)) {
            $tmp_shard->changeDocumentOffsets($summary_offsets);
            $index_archive->addIndexData($tmp_shard);
        }
        $index_archive->forceSave();
        $tmp_shard_name = C\WORK_DIRECTORY . "/data/tmp_index";
        if (file_exists($tmp_shard_name)) {
            unlink($tmp_shard_name);
        }
        unset($tmp_shard);
    }
    /**
     * Updates trending term counts based on the string from the current
     * feed item.
     *
     * @param array &$term_counts lang => [term => occurrences]
     * @param string $source_phrase original non-stemmed phrase from feed
     *      item to adjust $term_counts with. Used to remember non-stemmed
     *      terms. We assume we have already extracted position lists from
     * @param array $word_or_phrase_list associate array of
     *      stemmed_word_or_phrase => positions in feed item of where occurs
     * @param string $media_category of feed source the item case from. We
     *      trending counts grouped by media category
     * @param string $source_name of feed source the item case from. We exclude
     *      from counts the name of the feed source
     * @param string $lang locale_tag for this feed item
     * @param int $pubdate timestamp when string was published (used in
     *      weighting)
     * @param string $source_stop_regex a regex to remove terms which occur
     *      frequently for this paricular source
     */
    public function updateTrendingTermCounts(&$term_counts, $source_phrase,
        $word_or_phrase_list, $media_category, $source_name, $lang, $pubdate,
        $source_stop_regex = "")
    {
        $time = time();
        if ($time - $pubdate > C\ONE_DAY) {
            return;
        }
        $time_weight = max(ceil(($time - $pubdate)/C\ONE_HOUR), 1);
        $tokenizer = PhraseParser::getTokenizer($lang);
        $stop_words = (empty($tokenizer::$stop_words)) ? [] :
            $tokenizer::$stop_words;
        $composites = [];
        foreach ($word_or_phrase_list as $term => $positions) {
            if (strpos($term, "-") !== false) {
                $sub_terms = explode("-", $term);
                $composites += $sub_terms;
            }
        }
        foreach($word_or_phrase_list as $term => $positions) {
            if ((!empty($source_stop_regex) &&
                preg_match($source_stop_regex, $term)) ||
                !is_string($term) || $term == "") {
                continue;
            }
            $occurrences = count($positions);
            $weight = substr_count($term, "-");
            if (!empty($term_counts['seen'][$term])) {
                $term = $term_counts['seen'][$term];
                if (empty($term_counts[$lang][$media_category][$term])) {
                    if (empty($term_counts[$lang])) {
                        $term_counts[$lang] = [];
                    }
                    if (empty($term_counts[$lang][$media_category])) {
                        $term_counts[$lang][$media_category] = [];
                    }
                    $term_counts[$lang][$media_category][$term] = 0;
                }
                $term_counts[$lang][$media_category][$term] +=
                    ($weight * $occurrences) / $time_weight;
            } else if (strpos($term, " ") === false &&
                !in_array($term, $composites) &&
                !in_array($term[0], ["#", "_", "-"] ) &&
                !in_array($term, $stop_words) &&
                substr($term, 0, 4) != "http") {
                $original_term = $term;
                if (preg_match("/\b$term".'[\w|\-]*\b/ui', $source_phrase,
                    $match)) {
                    $term = $match[0];
                    if (!empty($source_stop_regex) &&
                        preg_match($source_stop_regex, $term)) {
                        continue;
                    }
                } else {
                    $term = str_replace("-", " ", $term);
                    if( mb_stristr($source_name, $term) !== false) {
                        continue;
                    }
                    if (($pre_term = substr($term, -6)) == "\_pos\_s") {
                        $term = $pre_term . "\s*(\'|\&apos\;)\s+";
                    }
                    if (preg_match("/\b$term".'[\w|\-]*\b/ui', $source_phrase,
                        $match)) {
                        $term = $match[0];
                    } else {
                        $trunc_term = mb_substr($term, 0, -1);
                        $trunc_original = mb_substr($original_term, 0, -1);
                        if (preg_match("/\b$trunc_term".'[\w|\-]*\b/ui',
                            $source_phrase, $match)) {
                            $term = $match[0];
                        } else if (preg_match(
                            "/\b$trunc_original".'[\w|\-]*\b/ui',
                            $source_phrase, $match)) {
                            $term = $match[0];
                        } else {
                            continue;
                        }
                    }
                    if (!empty($source_stop_regex) &&
                        preg_match($source_stop_regex, $term)) {
                        continue;
                    }
                }
                $lower_term = mb_strtolower($term);
                $lower_parts = preg_split("/\s+|\-/ui", $lower_term);
                $uncommon = false;
                foreach ($lower_parts as $lower_part) {
                    if (!empty($lower_part) &&
                        !in_array($lower_part, $stop_words)) {
                        $uncommon = true;
                        break;
                    }
                }
                if ($uncommon) {
                    if (empty($term_counts[$lang][$media_category][$term])) {
                        if (empty($term_counts[$lang])) {
                            $term_counts[$lang] = [];
                        }
                        if (empty($term_counts[$lang][$media_category])) {
                            $term_counts[$lang][$media_category] = [];
                        }
                        $term_counts[$lang][$media_category][$term] = 0;
                    }
                    $term_counts['seen'][$original_term] = $term;
                    if (!empty($term_counts['seen'][$lower_term])) {
                        $term = $term_counts['seen'][$lower_term];
                        if (empty($term_counts[$lang][$media_category][$term])){
                            $term_counts[$lang][$media_category][$term] = 0;
                        }
                    }
                    $term_counts['seen'][$lower_term] = $term;
                    $term_counts[$lang][$media_category][$term] +=
                        ($weight * $occurrences) / $time_weight;
                }
            }
        }
    }
    /**
     * Updates TRENDING_TERM, hourly, daily, and weekly top term occurrences.
     * Removes entries older than a week
     *
     * @param resource $db handle to database with TRENDING_TERM table
     * @param array $term_counts for the most recent update of the
     *      feed index, it should be an array [$lang => [$term => $occurences]]
     *      for the top NUM_TRENDING terms per language
     */
    public function addTermCountsTrendingTable($db, $term_counts)
    {
        $time = time();
        $update_intervals = [
            C\ONE_HOUR => C\ONE_DAY,
            C\ONE_DAY => C\ONE_WEEK,
            C\ONE_WEEK => C\ONE_MONTH,
            C\ONE_MONTH => C\ONE_YEAR
        ];
        $reset_interval_sql = "DELETE FROM TRENDING_TERM WHERE " .
            "UPDATE_PERIOD = ? AND LANGUAGE = ? AND ".
            "CATEGORY = ? AND TIMESTAMP >= ? ";
        $interval_sql = "SELECT TERM, SUM(OCCURRENCES) AS OCCURRENCES ".
            "FROM TRENDING_TERM WHERE UPDATE_PERIOD = ? AND " .
            "TIMESTAMP >= ? AND LANGUAGE = ? AND CATEGORY = ? GROUP BY TERM ".
            "ORDER BY OCCURRENCES DESC ".
            $db->limitOffset(C\NUM_TRENDING);
        $insert_sql = "INSERT INTO TRENDING_TERM (TERM, OCCURRENCES, " .
            "UPDATE_PERIOD, TIMESTAMP, LANGUAGE, CATEGORY) VALUES ".
            "(?, ?, ?, ?, ?, ?)";
        $cull_sql = "DELETE FROM TRENDING_TERM WHERE UPDATE_PERIOD = ? " .
            " AND TIMESTAMP < ?";
        $have_culled = [];
        foreach ($term_counts as $lang => $category_term_occurrences) {
            L\crawlLog("Updating Language $lang trending terms");
            foreach ($category_term_occurrences as $category =>
                $term_occurrences) {
                /* Sort the word occurrence list by occurrence
                   and insert into database.
                 */
                $num_inserted = 0;
                arsort($term_occurrences);
                foreach ($term_occurrences as $term => $occurrences) {
                    $db->execute($insert_sql, [$term, $occurrences, C\ONE_HOUR,
                        $time, $lang, $category]);
                    $num_inserted++;
                    if ($num_inserted >= C\NUM_TRENDING) {
                        break;
                    }
                }
                foreach ($update_intervals as $sub_interval => $interval) {
                    $interval_start = $time - $interval;
                    $recent_timestamp = floor($interval_start/$interval) *
                        $interval;
                    $db->execute($reset_interval_sql,
                        [$interval, $lang, $category, $recent_timestamp]);
                    $result = $db->execute($interval_sql,
                        [$sub_interval, $recent_timestamp, $lang, $category]);
                    while ($interval_info = $db->fetchArray($result)) {
                        $db->execute($insert_sql, [$interval_info['TERM'],
                            $interval_info['OCCURRENCES'], $interval,
                            $recent_timestamp, $lang, $category]);
                    }
                    if (empty($have_culled[$sub_interval])) {
                        $db->execute($cull_sql, [$sub_interval,
                            $recent_timestamp]);
                        $have_culled[$sub_interval] = true;
                    }
                }
            }
        }
    }
    /**
     * Adds $item to feed index bundle if it isn't already there
     *
     * @param array $item data from a single feed item
     * @param string $source_name string name of the feed $item was found
     * on
     * @param string $lang locale-tag of the feed
     * @param int $age how many seconds old records should be ignored
     * @param array if $item['guid'] is not set then hash these fields to
     *      produce a unique identifier
     * @return bool whether an item was added
     */
    public function addFeedItemIfNew($item, $source_name, $lang, $age,
        $unique_fields)
    {
        if (empty($item["link"]) || empty($item["title"]) ||
            empty($item["description"]) ||
            strlen($item["link"]) > C\MAX_URL_LEN) {
            return false;
        }
        $out_item = [];
        $out_item["TITLE"] = substr($item["title"], 0, C\TITLE_LEN);
        $out_item["DESCRIPTION"] = substr($item["description"], 0,
            C\MAX_GROUP_POST_LEN);
        $out_item["LINK"] = $item["link"];
        if (empty($item["guid"])) {
            $hash_string = "";
            foreach ($unique_fields as $field) {
                $hash_string .= $item[$field];
            }
            $out_item["GUID"] = L\crawlHash($hash_string);
        } else {
            $out_item["GUID"] = L\crawlHash($item["guid"]);
        }
        if (!isset($item["image_link"]) ||
            strlen($item["image_link"]) > C\MAX_URL_LEN) {
            $out_item["IMAGE_LINK"] = "";
        } else {
            $out_item["IMAGE_LINK"] = $item["image_link"];
        }
        if (!isset($item["pubdate"]) || $item["pubdate"] == "") {
            $out_item["PUBDATE"] = time();
        } else {
            $out_item["PUBDATE"] = strtotime($item["pubdate"]);
            if ($out_item["PUBDATE"] < 0) {
                $out_item["PUBDATE"] = time();
            }
        }
        if (time() - $out_item["PUBDATE"] > $age) {
            return false;
        }
        $index_archive = $this->getFeedArchive();
        if ($index_archive->contains($out_item["GUID"])) {
            return false;
        }
        $out_item['SOURCE_NAME'] = $source_name;
        $this->found_items[] = $out_item;
        return true;
    }
    /**
     * Used to calculate the meta words for RSS feed items
     *
     * @param string $lang the locale_tag of the feed item
     * @param int $pubdate UNIX timestamp publication date of item
     * @param string $source_name the name of the feed
     * @param string $guid the guid of the item
     * @param string $media_category determines what media: metas to inject.
     *      Default is news.
     *
     * @return array $meta_ids meta words found
     */
    public function calculateMetas($lang, $pubdate, $source_name, $guid,
        $media_category = "news")
    {
        $meta_ids = ["media:$media_category", "media:$media_category:" .
            urlencode( mb_strtolower($source_name)),
            "guid:" . strtolower($guid)];
        $meta_ids[] = 'date:' . date('Y', $pubdate);
        $meta_ids[] = 'date:' . date('Y-m', $pubdate);
        $meta_ids[] = 'date:' . date('Y-m-d', $pubdate);
        $meta_ids[] = 'date:' . date('Y-m-d-H', $pubdate);
        $meta_ids[] = 'date:' . date('Y-m-d-H-i', $pubdate);
        $meta_ids[] = 'date:' . date('Y-m-d-H-i-s', $pubdate);
        if ($lang != "") {
            $lang_parts = explode("-", $lang);
            $meta_ids[] = 'lang:' . $lang_parts[0];
            if (isset($lang_parts[1])) {
                $meta_ids[] = 'lang:' . $lang;
            }
        }
        return $meta_ids;
    }
    /**
     * Converts the results of an associative array coming from a
     * json_decode'd string to an HTML string where the json field
     * have become tags prefixed with "json". This can then be handled
     * in the rest of the feeds updater like an HTML feed.
     *
     * @param array $json_decode associative array coming from a
     *  json_decode'd string
     * @return string result of converting array to an html string
     */
    public function convertJsonDecodeToTags($json_decode)
    {
        $out = "";
        if (is_array($json_decode)) {
            foreach ($json_decode as $key => $value) {
                $tag_name = $key;
                $attributes = "";
                if (is_int($tag_name)) {
                    $tag_name = "item";
                    $attributes = "data-number='$key'";
                }
                /* this is to avoid name collisions with html tag names when
                   we convert json to html and dom it
                 */
                $out .= "\n<json$tag_name $attributes>\n";
                $out .= $this->convertJsonDecodeToTags($value);
                $out .= "\n</json$tag_name>\n";
            }
        } else {
            $out = $json_decode;
        }
        return $out;
    }
    /**
     * Information about how to parse non-rss and atom feeds is stored in the
     * MEDIA_SOURCE table in the AUX_INFO column. When a feed is read from
     * this table this method is used to parse this column into additional
     * fields which are easier to use for manipulating feed data. Example feed
     * types for which this parsing is readed are html, json and regex feeds.
     * In the case of an rss or atom feed this method assumes the AUX_INFO field
     * just contains an xpath expression for finding a feed_item's image, and
     * so just parses the AUX_INFO field into an IMAGE_XPATH field.
     *
     * @param array &$feed associative array of data about one particular feed
     */
    public function parseFeedAuxInfo(&$feed)
    {
        $aux_parts = explode("###",
                html_entity_decode($feed['AUX_INFO'], ENT_QUOTES));
        if (in_array($feed['TYPE'], ['html', 'json', 'regex'])) {
            list($feed['CHANNEL_PATH'], $feed['ITEM_PATH'],
            $feed['TITLE_PATH'], $feed['DESCRIPTION_PATH'],
            $feed['LINK_PATH']) = $aux_parts;
            $offset = 5;
        } elseif ($feed['TYPE'] == 'rss') {
            $offset = 0;
        }
        $feed['IMAGE_XPATH'] = $aux_parts[$offset] ?? "";
        $feed['TRENDING_STOP_REGEX'] = $aux_parts[$offset + 1] ?? "";
        if ($feed['TYPE'] == 'json') {
            /* this is to avoid name collisions with html tag names when
               we convert json to html and dom it. The making of tags
               with the json prefix is done in convertJsonDecodeToTags.
               Here we are making our xpaths compatible with this
             */
            foreach (['CHANNEL_PATH', 'ITEM_PATH',
                'TITLE_PATH', 'DESCRIPTION_PATH', 'LINK_PATH',
                'IMAGE_XPATH'] as $component) {
                $xpath = $feed[$component];
                $xpath_parts = explode("/", $xpath);
                $num_parts = count($xpath_parts);
                for ($j = 0; $j < $num_parts; $j++) {
                    if ($xpath_parts[$j] != "") {
                        $xpath_parts[$j] = "json" . $xpath_parts[$j];
                    }
                }
                $feed[$component] = implode("/", $xpath_parts);
            }
        }
    }
    /**
     * Sets the value of $this->index_archive to point to
     * the FeedArchiveBundle associated to feeds on this instance of Yioop
     */
    public function getFeedArchive()
    {
        $dir = C\CRAWL_DIR . '/cache/' . self::feed_index_data_base_name;
        if (!file_exists($dir) || empty($this->index_archive)) {
            $info['DESCRIPTION'] = "feed";
            $this->index_archive = new FeedArchiveBundle($dir, false,
                serialize($info), C\NUM_DOCS_PER_GENERATION);
            $this->db->setWorldPermissionsRecursive($dir);
        }
        return $this->index_archive;
    }
}
