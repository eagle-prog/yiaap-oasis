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
namespace seekquarry\yioop\executables;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\archive_bundle_iterators as A;
use seekquarry\yioop\library\BloomFilterFile;
use seekquarry\yioop\library\Classifiers;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\FetchGitRepositoryUrls;
use seekquarry\yioop\library\PageRuleParser;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\processors\PageProcessor;
use seekquarry\yioop\library\processors\ImageProcessor;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\WebArchiveBundle;

if (php_sapi_name() != 'cli' ||
    defined("seekquarry\\yioop\\configs\\IS_OWN_WEB_SERVER")) {
    echo "BAD REQUEST"; exit();
}
/** for L\crawlHash and L\crawlLog and Yioop constants*/
require_once __DIR__ . "/../library/Utility.php";
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
ini_set("memory_limit", C\FETCHER_MEMORY_LIMIT);
/** To guess language based on page encoding */
require_once __DIR__ . "/../library/LocaleFunctions.php";
/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/**
 * This class is responsible for fetching web pages for the
 * SeekQuarry/Yioop search engine
 *
 * Fetcher periodically queries the queue server asking for web pages to fetch.
 * It gets at most MAX_FETCH_SIZE many web pages from the queue_server in one
 * go. It then fetches these  pages. Pages are fetched in batches of
 * NUM_MULTI_CURL_PAGES many pages. Each SEEN_URLS_BEFORE_UPDATE_SCHEDULER many
 * downloaded pages (not including robot pages), the fetcher sends summaries
 * back to the machine on which the queue_server lives. It does this by making a
 * request of the web server on that machine and POSTs the data to the
 * yioop web app. This data is handled by the FetchController class. The
 * summary data can include up to four things: (1) robot.txt data, (2) summaries
 * of each web page downloaded in the batch, and (3)a list of future urls to add
 * to the to-crawl queue.
 *
 * @author Chris Pollett
 */
class Fetcher implements CrawlConstants
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    public $db;
    /**
     * Urls or IP address of the web_server used to administer this instance
     * of yioop. Used to figure out available queue_servers to contact
     * for crawling data
     *
     * @var array
     */
    public $name_server;
    /**
     * Array of Urls or IP addresses of the queue_servers to get sites to crawl
     * from
     * @var array
     */
    public $queue_servers;
    /**
     * Index into $queue_servers of the server get schedule from (or last one
     * we got the schedule from)
     * @var int
     */
    public $current_server;
    /**
     * An associative array of (mimetype => name of processor class to handle)
     * pairs.
     * @var array
     */
    public $page_processors;
    /**
     * An associative array of (page processor => array of
     * indexing plugin name associated with the page processor). It is used
     * to determine after a page is processed which plugins'
     * pageProcessing($page, $url) method should be called
     * @var array
     */
    public $plugin_processors;
    /**
     * Hash used to keep track of whether $plugin_processors info needs to be
     * changed
     * @var string
     */
    public $plugin_hash;
    /**
     * Says whether the $allowed_sites array is being used or not
     * @var bool
     */
    public $restrict_sites_by_url;
    /**
     * List of file extensions supported for the crawl
     * @var array
     */
    public $indexed_file_types;
    /**
     * List of all known file extensions including those not used for crawl
     * @var array
     */
    public $all_file_types;
    /**
     * Web-sites that crawler can crawl. If used, ONLY these will be crawled
     * @var array
     */
    public $allowed_sites;
    /**
     * An array of Bloom filters which if non empty will be used to
     * restrict discovered url links. Namely, if the domain of a url is not in
     * any of the filters and is not of thhe form of a company level domain
     * (cld) or www.cld then it will be pruned. Here a company level domain
     * is of the form some_name.tld where tld is a top level domain
     * or the form some_name.country_level_tld.tld if the the tld is that of a
     * country. So for site.somewhere.jp, somewhere.jp would be the cld; for
     * site.somewhere.co.jp, somewhere.co.jp would be the cld.
     * @var array
     */
    public $domain_filters;
    /**
     * Web-sites that the crawler must not crawl
     * @var array
     */
    public $disallowed_sites;
    /**
     * Microtime used to look up cache $allowed_sites and $disallowed_sites
     * filtering data structures
     * @var int
     */
    public $allow_disallow_cache_time;
    /**
     * Holds the parsed page rules which will be applied to document summaries
     * before finally storing and indexing them
     * @var array
     */
    public $page_rule_parser;
    /**
     * WebArchiveBundle used to store complete web pages and auxiliary data
     * @var object
     */
    public $web_archive;
    /**
     * Timestamp of the current crawl
     * @var int
     */
    public $crawl_time;
    /**
     * The last time the name server was checked for a crawl time
     * @var int
     */
    public $check_crawl_time;
    /**
     * For a web crawl only the number of web pages to download in one
     * go. The constant SLOW_START can be used to reduce this
     * number from C\NUM_MULTI_CURL_PAGES for the first hour of a crawl
     * @var int
     */
    public $num_multi_curl;
    /**
     * Used to check if we are currently operaing in slow star mode
     * @var bool
     */
    public $slow_start_mode;
    /**
     * Contains the list of web pages to crawl from a queue_server
     * @var array
     */
    public $to_crawl;
    /**
     * Contains the list of web pages to crawl that failed on first attempt
     * (we give them one more try before bailing on them)
     * @var array
     */
    public $to_crawl_again;
    /**
     * Summary information for visited sites that the fetcher hasn't sent to
     * a queue_server yet
     * @var array
     */
    public $found_sites;
    /**
     * Timestamp from a queue_server of the current schedule of sites to
     * download. This is sent back to the server once this schedule is completed
     * to help the queue server implement crawl-delay if needed.
     * @var int
     */
    public $schedule_time;
    /**
     * The sum of the number of words of all the page descriptions for the
     * current crawl. This is used in computing document statistics.
     * @var int
     */
    public $sum_seen_site_description_length;
    /**
     * The sum of the number of words of all the page titles for the current
     * crawl. This is used in computing document statistics.
     * @var int
     */
    public $sum_seen_title_length;
    /**
     * The sum of the number of words in all the page links for the current
     * crawl. This is used in computing document statistics.
     * @var int
     */
    public $sum_seen_site_link_length;
    /**
     * Number of sites crawled in the current crawl
     * @var int
     */
    public $num_seen_sites;
    /**
     * Channel that queue server listens to messages for
     * @var int
     */
    public $channel;
    /**
     * Stores the name of the ordering used to crawl pages. This is used in a
     * switch/case when computing weights of urls to be crawled before sending
     * these new urls back to a queue_server.
     * @var string
     */
    public $crawl_order;
    /**
     * Maximum depth fetcher should extract need seed urls to
     * @var int
     */
    public $max_depth;
    /**
     * Stores the name of the summarizer used for crawling.
     * Possible values are self::BASIC, self::GRAPH_BASED_SUMMARIZER,
     * self::CENTROID_SUMMARIZER and self::CENTROID_WEIGHTED_SUMMARIZER
     * @var string
     */
    public $summarizer_option;
    /**
     * Indicates the kind of crawl being performed: self::WEB_CRAWL indicates
     * a new crawl of the web; self::ARCHIVE_CRAWL indicates a crawl of an
     * existing web archive
     * @var string
     */
    public $crawl_type;
    /**
     * For an archive crawl, holds the name of the type of archive being
     * iterated over (this is the class name of the iterator, without the word
     * 'Iterator')
     * @var string
     */
    public $arc_type;
    /**
     * For a non-web archive crawl, holds the path to the directory that
     * contains the archive files and their description (web archives have a
     * different structure and are already distributed across machines and
     * fetchers)
     * @var string
     */
    public $arc_dir;
    /**
     * If an web archive crawl (i.e. a re-crawl) is active then this field
     * holds the iterator object used to iterate over the archive
     * @var object
     */
    public $archive_iterator;
    /**
     * Keeps track of whether during the recrawl we should notify a
     * queue_server scheduler about our progress in mini-indexing documents
     * in the archive
     * @var bool
     */
    public $recrawl_check_scheduler;
    /**
     * If the crawl_type is self::ARCHIVE_CRAWL, then crawl_index is the
     * timestamp of the existing archive to crawl
     * @var string
     */
    public $crawl_index;
    /**
     * Whether to cache pages or just the summaries
     * @var bool
     */
    public $cache_pages;
    /**
     * Which fetcher instance we are (if fetcher run as a job and more that one)
     * @var string
     */
    public $fetcher_num;
    /**
     * Maximum number of bytes to download of a webpage
     * @var int
     */
    public $page_range_request;
    /**
     * Max number of chars to extract for description from a page to index.
     * Only words in the description are indexed.
     * @var int
     */
    public $max_description_len;
    /**
     * Maximum number of urls to extract from a single document
     * @var int
     */
    public $max_links_to_extract;
    /**
     * An array to keep track of hosts which have had a lot of http errors
     * @var array
     */
    public $hosts_with_errors;
    /**
     * When processing recrawl data this says to assume the data has
     * already had its inks extracted into a field and so this doesn't
     * have to be done in a separate step
     *
     * @var bool
     */
    public $no_process_links;
    /**
     * Maximum number of bytes which can be uploaded to the current
     * queue server's web app in one go
     *
     * @var int
     */
    public $post_max_size;
    /**
     * Fetcher must wait at least this long between multi-curl requests.
     * The value below is dynamically determined but is at least as large
     * as MINIMUM_FETCH_LOOP_TIME
     * @var int
     */
    public $minimum_fetch_loop_time;
    /**
     * Contains which classifiers to use for the current crawl
     * Classifiers can be used to label web documents with a meta word
     * if the classifiers threshold is met
     * @var array
     */
    public $active_classifiers;
    /**
     * Contains an array of scrapers used to extract the import content
     * from particular kind of HTML pages, for example, pages generated by
     * a particular content management system.
     * @var array
     */
    public $scrapers;
    /**
     * Contains which classifiers to use for the current crawl
     * that are being used to rank web documents. The score that the
     * classifier gives to a document is used for this ranking purposes
     * @var array
     */
    public $active_rankers;
    /**
     * To keep track of total number of Git internal urls
     *
     * @var int
     */
    public $total_git_urls;
    /**
     * To store all the internal git urls fetched
     *
     * @var array
     */
    public $all_git_urls;
    /**
     * To map programming languages with their extensions
     *
     * @var array
     */
    public $programming_language_extension;
    /**
     * If this is not null and a .onion url is detected then this url will
     * used as a proxy server to download the .onion url
     * @var string
     */
    public $tor_proxy;
    /**
     * an array of proxy servers to use rather than to directly download web
     * pages from the current machine. If is the empty array, then we just
     * directly download from the current machine
     *
     * @var array
     */
    public $proxy_servers;
    /**
     * Holds the value of a debug message that might have been sent from
     * the command line during the current execution of loop();
     * @var string
     */
    public $debug;
    /**
     * Before receiving any data from a queue server's web app this is
     * the default assumed post_max_size in bytes
     */
    const DEFAULT_POST_MAX_SIZE = 2000000;

    /**
     * constant indicating Git repository
     */
    const REPOSITORY_GIT = 'git';
    /**
     * constant indicating Git repository
     */
    const GIT_URL_CONTINUE = '@@@@';
    /**
     * An indicator to tell no actions to be taken
     */
    const INDICATOR_NONE = 'none';
    /**
     * A indicator to represent next position after the access code in Git
     * tree object
     */
     const HEX_NULL_CHARACTER = "\x00";
    /**
     * Domain Filter Glob pattern for Bloom filters used to specify allowed
     * domains. (Only specifies if some filter file of this type exists)
     */
     const DOMAIN_FILTER_GLOB = C\WORK_DIRECTORY . "/data/domain_filters/*.ftr";
    /**
     * Sets up the field variables so that crawling can begin
     */
    public function __construct()
    {
        $this->processors = [];
        PageProcessor::initializeIndexedFileTypes($this->processors);
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS) . "Manager";
        $this->db = new $db_class();
        // initially same only one queueserver and is same as name server
        $this->name_server = C\NAME_SERVER;
        $this->queue_servers = [C\NAME_SERVER];
        $this->current_server = 0;
        $this->page_processors = PageProcessor::$mime_processor;
        $this->indexed_file_types = PageProcessor::$indexed_file_types;
        $this->all_file_types = PageProcessor::$indexed_file_types;
        $this->scrapers = [];
        $this->plugin_hash = "";
        $this->restrict_sites_by_url = false;
        $this->allowed_sites = [];
        $this->disallowed_sites = [];
        $this->domain_filters = [];
        $this->allow_disallow_cache_time = microtime(true);
        $this->page_rule_parser = null;
        $this->hosts_with_errors = [];
        $this->web_archive = null;
        $this->crawl_time = null;
        $this->channel = 0;
        $this->sleep_start = "00:00";
        $this->sleep_duration = -1;
        $this->check_crawl_time = null;
        $this->schedule_time = null;
        $this->crawl_type = self::WEB_CRAWL;
        $this->crawl_index = null;
        $this->recrawl_check_scheduler = false;
        $this->to_crawl = [];
        $this->to_crawl_again = [];
        $this->found_sites = [];
        $this->found_sites[self::CACHE_PAGE_VALIDATION_DATA] = [];
        $this->page_range_request = C\PAGE_RANGE_REQUEST;
        $this->max_description_len = C\MAX_DESCRIPTION_LEN;
        $this->max_links_to_extract = C\MAX_LINKS_TO_EXTRACT;
        $this->fetcher_num = false;
        $this->sum_seen_title_length = 0;
        $this->sum_seen_description_length = 0;
        $this->sum_seen_site_link_length = 0;
        $this->num_seen_sites = 0;
        $this->no_process_links = false;
        $this->cache_pages = true;
        $this->post_max_size = self::DEFAULT_POST_MAX_SIZE;
        $this->minimum_fetch_loop_time = C\MINIMUM_FETCH_LOOP_TIME;
        $this->active_classifiers = [];
        $this->active_rankers = [];
        $this->tor_proxy = "";
        $this->proxy_servers = [];
        $ip_array = gethostbynamel(gethostname());
        $this->total_git_urls = 0;
        $this->all_git_urls = [];
        $this->programming_language_extension = ['java' => 'java',
            'py' => 'py'];
        //we will get the correct crawl order from a queue_server
        $this->crawl_order = self::PAGE_IMPORTANCE;
        $this->max_depth = -1;
        $this->summarizer_option = self::BASIC_SUMMARIZER;
        $this->debug = "";
        $this->num_multi_curl = C\NUM_MULTI_CURL_PAGES;
    }
    /**
     * Return the fetcher's copy of a page processor for the given
     * mimetype.
     *
     * @param string $type mimetype want a processor for
     * @return object a page processor for that mime type of false if
     *      that mimetype can't be handled
     */
    public function pageProcessor($type)
    {
        static $processor_cache = [];
        static $plugin_hash = "";
        if ($plugin_hash != $this->plugin_hash) {
            $processor_cache = [];
            $plugin_hash = $this->plugin_hash;
        }
        if (isset($processor_cache[$type])) {
            return $processor_cache[$type];
        }
        if (isset($this->page_processors[$type])) {
            $page_processor = C\NS_PROCESSORS . $this->page_processors[$type];
            $text_processor = C\NS_PROCESSORS . "TextProcessor";
        } else {
            $processor_cache[$type] = false;
            return false;
        }
        if (isset($this->plugin_processors[$page_processor])) {
            $processor_cache[$type] = new $page_processor(
                $this->plugin_processors[$page_processor],
                $this->max_description_len, -1, $this->summarizer_option);
        } else {
            $processor_cache[$type] = new $page_processor([],
                $this->max_description_len, -1, $this->summarizer_option);
        }
        if (L\generalIsA($page_processor, $text_processor)) {
            $processor_cache[$type]->text_data = true;
        } else {
            $processor_cache[$type]->text_data = false;
        }
        return $processor_cache[$type];
    }
    /**
     * This is the function that should be called to get the fetcher to start
     * fetching. Calls init to handle the command-line arguments then enters
     * the fetcher's main loop
     */
    public function start()
    {
        global $argv;
        $this->channel = (empty($argv[3])) ? 0 : min(C\MAX_CHANNELS,
            abs(intval($argv[3])));
        if (isset($argv[2]) ) {
            $arg2_parts = explode("-", $argv[2]);
            if (count($arg2_parts) > 1) {
                list($fetcher_num, $channel,) = $arg2_parts;
                $this->channel = abs(intval($channel));
                $this->fetcher_num = $fetcher_num;
                unset($argv[3]);
            } else {
                $this->fetcher_num = intval($argv[2]);
                $argv[2] = $argv[2] . "-" . $this->channel;
            }
        } else {
            $this->fetcher_num = 0;
            $argv[2] = "0-0";
        }
        CrawlDaemon::init($argv, "Fetcher");
        $fetcher_name = $this->fetcher_num .
            "-" . $this->channel . "-Fetcher";
        L\crawlLog("\n\nInitialize logger..", $fetcher_name,
            true);
        L\crawlLog($this->fetcher_num . "-Fetcher using channel " .
            $this->channel);
        $this->loop();
    }
    /**
     * Main loop for the fetcher.
     *
     * Checks for stop message, checks queue server if crawl has changed and
     * for new pages to crawl. Loop gets a group of next pages to crawl if
     * there are pages left to crawl (otherwise sleep 5 seconds). It downloads
     * these pages, deduplicates them, and updates the found site info with the
     * result before looping again.
     */
    public function loop()
    {
        static $total_idle_time = 0;
        static $total_idle_previous_hour = 0;
        static $total_idle_two_hours = 0;
        static $oldest_record_time = 0;
        static $last_record_time = 0;
        L\crawlLog("In Fetch Loop");
        L\crawlLog("PHP Version in use: " . phpversion());
        $prefix = $this->fetcher_num . "-";
        if (!file_exists(C\CRAWL_DIR . "/{$prefix}temp")) {
            mkdir(C\CRAWL_DIR . "/{$prefix}temp");
        }
        $info[self::STATUS] = self::CONTINUE_STATE;
        $local_archives = [""];
        while (CrawlDaemon::processHandler()) {
            $start_time = microtime(true);
            $info = [];
            $fetcher_message_file = C\CRAWL_DIR.
                "/schedules/{$prefix}FetcherMessages.txt";
            if (file_exists($fetcher_message_file)) {
                $info = unserialize(file_get_contents($fetcher_message_file));
                if (isset($info[self::DEBUG])) {
                    $this->debug = $info[self::DEBUG];
                    unlink($message_file);
                    continue;
                }
                unlink($fetcher_message_file);
                if (isset($info[self::STATUS]) &&
                    $info[self::STATUS] == self::STOP_STATE) {
                    continue;
                }
            }
            $switch_fetch_or_no_current = $this->checkCrawlTime();
            if (($wake_up =
                L\checkTimeInterval($this->sleep_start,
                $this->sleep_duration)) > 0) {
                L\crawlLog("MAIN LOOP CASE 0 --".
                    " FETCHER IS IN QUIESCENT/SLEEP MODE!!");
                L\crawlLog("Current time is: " . date('r'));
                L\crawlLog("Will wake up at " . date('r', $wake_up));
                L\crawlLog("Current Crawl Timestamp: " .
                    $this->crawl_time);
                sleep($this->minimum_fetch_loop_time);
                continue;
            } else if ($switch_fetch_or_no_current) {  /* case (1) */
                L\crawlLog("MAIN LOOP CASE 1 --".
                    " SWITCH CRAWL OR NO CURRENT CRAWL");
                $info[self::CRAWL_TIME] = $this->crawl_time;
                if ($info[self::CRAWL_TIME] == 0) {
                    $info[self::STATUS] = self::NO_DATA_STATE;
                    $this->to_crawl = [];
                    $this->sleep_start = "00:00";
                    $this->duration = "-1";
                } else {
                   L\crawlLog("Crawl time is now " . $this->crawl_time);
                }
            } else if ($this->crawl_type == self::ARCHIVE_CRAWL &&
                    $this->arc_type != "WebArchiveBundle" &&
                    $this->arc_type != "") { /* case (2) */
                // An archive crawl with data coming from the name server.
                L\crawlLog(
                    "MAIN LOOP CASE 2 -- ARCHIVE SCHEDULER (NOT RECRAWL)");
                $info = $this->checkArchiveScheduler();
                if ($info === false) {
                    L\crawlLog("No Archive Schedule Data...".
                        " will try again in ".C\FETCH_SLEEP_TIME." seconds.");
                    sleep(C\FETCH_ONE_TIME);
                    $total_idle_time +=  C\FETCH_SLEEP_TIME/C\ONE_HOUR;
                    continue;
                }
            } else if ($this->crawl_time > 0) { /* case (3) */
                // Either a web crawl or a recrawl of a previous web crawl.
                if ($this->crawl_type == self::ARCHIVE_CRAWL) {
                    L\crawlLog("MAIN LOOP CASE 3 -- RECRAWL SCHEDULER");
                } else {
                    L\crawlLog("MAIN LOOP CASE 4 -- WEB SCHEDULER");
                }
                $info = $this->checkScheduler();
                if ($info === false) {
                    L\crawlLog("Cannot connect to name server...".
                        " will try again in ".C\FETCH_SLEEP_TIME." seconds.");
                    sleep(C\FETCH_SLEEP_TIME);
                    $total_idle_time +=  C\FETCH_SLEEP_TIME/C\ONE_HOUR;
                    continue;
                }
                $this->updateDomainFilters();
            } else {
                L\crawlLog("MAIN LOOP CASE 5 -- NO CURRENT CRAWL");
                $info[self::STATUS] = self::NO_DATA_STATE;
            }
            $record_time = time();
            $diff_hours = ($record_time - $oldest_record_time)/C\ONE_HOUR;
            $diff_hours = ($diff_hours <= 0) ? 1 : $diff_hours;
            $idle_per_hour =
                ($total_idle_time - $total_idle_two_hours) /
                $diff_hours;
            L\crawlLog("Percent idle time per hour: " .
                number_format($idle_per_hour * 100, 2) ."%");
            if ($record_time > $last_record_time + C\ONE_HOUR) {
                $total_idle_two_hours = $total_idle_previous_hour;
                $total_idle_previous_hour = $total_idle_time;
                $oldest_record_time = $last_record_time;
                if ($oldest_record_time == 0) {
                    $oldest_record_time = $record_time;
                }
                $last_record_time = $record_time;
            }
            /* case (2), case (3) might have set info without
               $info[self::STATUS] being set
             */
            if (!isset($info[self::STATUS])) {
                if ($info === true) {
                    $info = [];
                }
                $info[self::STATUS] = self::CONTINUE_STATE;
            }
            if ($info[self::STATUS] == self::NO_DATA_STATE) {
                L\crawlLog("No data. Sleeping...");
                sleep(C\FETCH_SLEEP_TIME);
                $total_idle_time += C\FETCH_SLEEP_TIME/C\ONE_HOUR;
                continue;
            }
            $tmp_base_name = (isset($info[self::CRAWL_TIME])) ?
                C\CRAWL_DIR."/cache/{$prefix}" . self::archive_base_name .
                    $info[self::CRAWL_TIME] : "";
            if (isset($info[self::CRAWL_TIME]) && ($this->web_archive == null ||
                    $this->web_archive->dir_name != $tmp_base_name)) {
                if (isset($this->web_archive->dir_name)) {
                    L\crawlLog("Old name: " . $this->web_archive->dir_name);
                }
                if (is_object($this->web_archive)) {
                    $this->web_archive = null;
                }
                $this->to_crawl_again = [];
                $this->found_sites = [];
                L\garbageCollect();
                $this->web_archive = new WebArchiveBundle($tmp_base_name,
                    false);
                $this->crawl_time = $info[self::CRAWL_TIME];
                $this->sum_seen_title_length = 0;
                $this->sum_seen_description_length = 0;
                $this->sum_seen_site_link_length = 0;
                $this->num_seen_sites = 0;
                L\crawlLog("New name: ".$this->web_archive->dir_name);
                L\crawlLog("Switching archive...");
                if (!isset($info[self::ARC_DATA])) {
                    continue;
                }
            }
            if (empty($this->web_archive)) {
                L\crawlLog("Fetcher web_archive empty skipping page download");
            } else {
                switch ($this->crawl_type) {
                    case self::WEB_CRAWL:
                        $downloaded_pages = $this->downloadPagesWebCrawl();
                        break;
                    case self::ARCHIVE_CRAWL:
                        if (isset($info[self::ARC_DATA])) {
                            $downloaded_pages = $info[self::ARC_DATA];
                        } else {
                            $downloaded_pages =
                                $this->downloadPagesArchiveCrawl();
                        }
                        break;
                }
                if (isset($downloaded_pages["NO_PROCESS"])) {
                    unset($downloaded_pages["NO_PROCESS"]);
                    $summarized_site_pages = array_values($downloaded_pages);
                    $this->no_process_links = true;
                } else {
                    $summarized_site_pages =
                        $this->processFetchPages($downloaded_pages);
                    $this->no_process_links = false;
                }
                L\crawlLog("Number of summarized pages ".
                    count($summarized_site_pages));
                $force_send = (isset($info[self::END_ITERATOR]) &&
                    $info[self::END_ITERATOR]) ? true : false;
                $this->updateFoundSites($summarized_site_pages, $force_send);
            }
            $sleep_time = max(0, ceil($this->minimum_fetch_loop_time
                - L\changeInMicrotime($start_time)));
            if ($sleep_time > 0) {
                L\crawlLog(
                    "Ensure minimum loop time by sleeping..." . $sleep_time);
                $total_idle_time += $sleep_time/C\ONE_HOUR;
                sleep($sleep_time);
            }
            if (!empty($this->debug)) {
                L\crawlLog("Debug Message: {$this->debug} has been processed");
                $this->debug = "";
            }
        }//end while
        L\crawlLog("Fetcher shutting down!!");
    }
    /**
     * Updates the array of domain filters currently loaded into memory based
     * on which BloomFilterFiles are present in
     * WORK_DIRECTORY/data/domain_filters and if they have changed since the
     * current in-memory filters were loaded
     */
    public function updateDomainFilters()
    {
        L\crawlLog("Checking for Updates to Domain Filters");
        $filter_names = glob(self::DOMAIN_FILTER_GLOB);
        $current_filters  = array_keys($this->domain_filters);
        foreach ($current_filters as $filter) {
            if (!in_array($filter, $filter_names)) {
                unset($this->domain_filters[$filter_name]);
                L\crawlLog("Filter: " . $filter_name . " no longer exists, ".
                    "so removing it from active filters");
            }
        }
        $updates = [];
        foreach ($filter_names as $filter_name) {
            if (empty($this->domain_filters[$filter_name])) {
                L\crawlLog("New Filter: " . $filter_name .
                    " detected, so adding to filter list!");
                $updates[] = $filter_name;
                continue;
            }
            if ($this->domain_filters[$filter_name]["MODIFIED_TIME"] !=
                filemtime($filter_name)) {
                $updates[] = $filter_name;
                L\crawlLog("Filter: " . $filter_name ." changed, so updating!");
            }
        }
        foreach ($updates as $filter_name) {
            $this->domain_filters[$filter_name]["MODIFIED_TIME"] =
                filemtime($filter_name);
            $this->domain_filters[$filter_name]["FILTER"] =
                BloomFilterFile::load($filter_name);
            if (empty($this->domain_filters[$filter_name]["FILTER"])) {
                L\crawlLog("Problem Loading Filter: " . $filter_name .
                    ", so disabling!");
                unset($this->domain_filters[$filter_name]);
            }
        }
    }
    /**
     * Get a list of urls from the current fetch batch provided by the queue
     * server. Then downloads these pages. Finally, reschedules, if
     * possible, pages that did not successfully get downloaded.
     *
     * @return array an associative array of web pages and meta data
     * fetched from the internet
     */
    public function downloadPagesWebCrawl()
    {
        static $total_downloads_succeeded = 0;
        static $total_downloads_previous_hour = 0;
        static $total_downloads_two_hours = 0;
        static $last_record_time = 0;
        static $oldest_record_time = 0;
        $start_time = microtime(true);
        $can_schedule_again = false;
        if (count($this->to_crawl) > 0)  {
            $can_schedule_again = true;
        }
        $sites = $this->getFetchSites();
        L\crawlLog("Done getting list of ".count($sites)." to download...");
        if (!$sites) {
            L\crawlLog("No seeds to fetch...");
            sleep(max(0, ceil($this->minimum_fetch_loop_time
                - L\changeInMicrotime($start_time))));
            return [];
        }
        $prefix = $this->fetcher_num . "-";
        $tmp_dir = C\CRAWL_DIR . "/{$prefix}temp";
        $filtered_sites = [];
        $site_pages = [];
        foreach ($sites as $site) {
            $hard_coded_parts = explode("###!", $site[self::URL]);
            if (count($hard_coded_parts) > 1) {
                if (!isset($hard_coded_parts[2])) $hard_coded_parts[2] = "";
                $site[self::URL] = $hard_coded_parts[0];
                $title = urldecode($hard_coded_parts[1]);
                $description = urldecode($hard_coded_parts[2]);
                $site[self::PAGE] = "<html><head><title>{$title}".
                    "</title></head><body><h1>{$title}</h1>".
                    "<p>{$description}</p></body></html>";
                $site[self::HTTP_CODE] = 200;
                $site[self::TYPE] = "text/html";
                $site[self::ENCODING] = "UTF-8";
                $site[self::IP_ADDRESSES] = ["0.0.0.0"];
                $site[self::TIMESTAMP] = time();
                $site_pages[] = $site;
            } else {
                $filtered_sites[] = $site;
            }
        }
        $site_pages = array_merge($site_pages,
            FetchUrl::getPages($filtered_sites, true,
                $this->page_range_request, $tmp_dir, self::URL, self::PAGE,
                false, null, false, $this->tor_proxy, $this->proxy_servers) );
        L\crawlLog("..getPages call complete..");
        for ($j = 0; $j < count($site_pages); $j++) {
            if (isset($site_pages[$j][self::REPOSITORY_TYPE])) {
                $git_repository_url = $site_pages[$j][self::URL];
                $git_compressed_content = FetchGitRepositoryUrls::getGitdata(
                    $git_repository_url);
                $git_uncompressed_content = gzuncompress(
                    $git_compressed_content);
                $length = strlen ($git_uncompressed_content);
                $git_hash_end = strpos($git_uncompressed_content,
                    self::HEX_NULL_CHARACTER);
                $git_uncompressed_content = substr($git_uncompressed_content,
                    $git_hash_end + 1, $length);
                $site_pages[$j][self::PAGE] = $git_uncompressed_content;
                $mime_type = UrlParser::guessMimeTypeFromFileName(
                    $site_pages[$j][self::FILE_NAME]);
                if ($mime_type == "text/xml") {
                    $mime_type = "text/gitxml";
                }
                $site_pages[$j][self::TYPE] = $mime_type;
            }
        }
        list($downloaded_pages, $schedule_again_pages) =
            $this->reschedulePages($site_pages);
        $total_downloads_succeeded += count($downloaded_pages);
        if ($can_schedule_again == true) {
            //only schedule to crawl again on fail sites without crawl-delay
            L\crawlLog("  Scheduling again..");
            foreach ($schedule_again_pages as $schedule_again_page) {
                if (isset($schedule_again_page[self::CRAWL_DELAY]) &&
                    $schedule_again_page[self::CRAWL_DELAY] == 0) {
                    $tmp_again =
                        [$schedule_again_page[self::URL],
                            $schedule_again_page[self::WEIGHT],
                            $schedule_again_page[self::CRAWL_DELAY]
                        ];
                    if (!empty($schedule_again_page[self::NO_RANGE])) {
                        $tmp_again[self::NO_RANGE] = true;
                    }
                    $this->to_crawl_again[] = $tmp_again;
                }
                L\crawlLog("....reschedule count:".
                    count($this->to_crawl_again));
            }
            L\crawlLog("....done.");
        }
        $record_time = time();
        $diff_hours = ($record_time - $oldest_record_time)/C\ONE_HOUR;
        $diff_hours = ($diff_hours <= 0) ? 1 : $diff_hours;
        $downloads_per_hour =
            ($total_downloads_succeeded - $total_downloads_two_hours) /
            $diff_hours;
        L\crawlLog("...Downloads per hours: " . $downloads_per_hour);
        if ($record_time > $last_record_time + C\ONE_HOUR) {
            $total_downloads_two_hours = $total_downloads_previous_hour;
            $total_downloads_previous_hour = $total_downloads_succeeded;
            $oldest_record_time = $last_record_time;
            if ($oldest_record_time == 0) {
                $oldest_record_time = $record_time;
            }
            $last_record_time = $record_time;
        }
        L\crawlLog("Downloading complete");
        return $downloaded_pages;
    }
    /**
     * Extracts NUM_MULTI_CURL_PAGES from the curent Archive Bundle that is
     * being recrawled.
     *
     * @return array an associative array of web pages and meta data from
     *     the archive bundle being iterated over
     */
    public function downloadPagesArchiveCrawl()
    {
        $prefix = $this->fetcher_num . "-";
        $arc_name = "$prefix" . self::archive_base_name . $this->crawl_index;
        $base_name = C\CRAWL_DIR . "/cache/$arc_name";
        $pages = [];
        if (!isset($this->archive_iterator->iterate_timestamp) ||
            $this->archive_iterator->iterate_timestamp != $this->crawl_index ||
            $this->archive_iterator->result_timestamp != $this->crawl_time) {
            if (!file_exists($base_name)){
                L\crawlLog("!!Fetcher web archive $arc_name  does not exist.");
                L\crawlLog("  Only fetchers involved in original crawl will ");
                L\crawlLog("  participate in a web archive recrawl!!");
                return $pages;
            } else {
                L\crawlLog("Initializing Web Archive Bundle Iterator.");
                $this->archive_iterator =
                    new A\WebArchiveBundleIterator($prefix, $this->crawl_index,
                        $this->crawl_time);
                if ($this->archive_iterator == null) {
                    L\crawlLog("Error creating archive iterator!!");
                    return $pages;
                }
            }
        }
        if (!$this->archive_iterator->end_of_iterator) {
            L\crawlLog("Getting pages from archive iterator...");
            $pages = $this->archive_iterator->nextPages(C\NUM_MULTI_CURL_PAGES);
            L\crawlLog("...pages get complete.");
        }
        return $pages;
    }
    /**
     * Deletes any crawl web archive bundles not in the provided array of crawls
     *
     * @param array $still_active_crawls those crawls which should not
     * be deleted, so all others will be deleted
     * @see loop()
     */
    public function deleteOldCrawls(&$still_active_crawls)
    {
        $prefix = $this->fetcher_num . "-";
        $dirs = glob(C\CRAWL_DIR.'/cache/*', GLOB_ONLYDIR);
        $full_base_name = $prefix . self::archive_base_name;
        foreach ($dirs as $dir) {
            if (strlen(
                $pre_timestamp = strstr($dir, $full_base_name)) > 0) {
                $time = substr($pre_timestamp,
                    strlen($full_base_name));
                if (!in_array($time, $still_active_crawls) ){
                    $this->db->unlinkRecursive($dir);
                }
            }
        }
        $files = glob(C\CRAWL_DIR . '/schedules/*');
        $names = [self::fetch_batch_name, self::fetch_crawl_info,
            self::fetch_closed_name, self::schedule_name,
            self::fetch_archive_iterator, self::save_point];
        foreach ($files as $file) {
            $timestamp = "";
            foreach ($names as $name) {
                $full_name = $prefix . $name;
                if (strlen(
                    $pre_timestamp = strstr($file, $full_name)) > 0) {
                    $timestamp =  substr($pre_timestamp,
                        strlen($full_name), 10);
                    break;
                }
            }
            if ($timestamp !== "" &&
                !in_array($timestamp, $still_active_crawls)) {
                if (is_dir($file)) {
                    $this->db->unlinkRecursive($file);
                } else {
                    unlink($file);
                }
            }
        }
    }
    /**
     * Makes a request of the name server machine to get the timestamp of the
     * currently running crawl to see if it changed
     *
     * If the timestamp has changed save the rest of the current fetch batch,
     * then load any existing fetch from the new crawl; otherwise, set the crawl
     * to empty. Also, handles deleting old crawls on this fetcher machine
     * based on a list of current crawls on the name server.
     *
     * @return bool true if loaded a fetch batch due to time change
     */
    public function checkCrawlTime()
    {
        static $saved_crawl_times = [];
        $name_server = $this->name_server;
        $start_time = microtime(true);
        $time = time();
        $this->check_crawl_time = $time;
        $session = md5($time . C\AUTH_KEY);
        $prefix = $this->fetcher_num . "-" . $this->channel . "-";
        $robot_instance = $prefix . C\ROBOT_INSTANCE;
        $time_change = false;
        $crawl_time = !is_null($this->crawl_time) ? $this->crawl_time : 0;
        if ($crawl_time > 0) {
            L\crawlLog("Checking name server:");
            L\crawlLog(
                "  $name_server to see if active crawl time has changed.");
        } else {
            L\crawlLog("Checking name server:");
            L\crawlLog("  $name_server to see if should start crawling");
        }
        $request =
            $name_server . "?c=fetch&a=crawlTime&time=$time&session=$session" .
            "&robot_instance=" . $robot_instance . "&machine_uri=" . C\WEB_URI .
            "&crawl_time=$crawl_time";
        L\crawlLog("Request was:" . $request);
        $info_string = FetchUrl::getPage($request, null, true);
        L\crawlLog("Start of response was:" . substr($info_string, 0, 256));
        $info = @unserialize(trim($info_string));
        if (isset($info[self::SAVED_CRAWL_TIMES])) {
            if (array_diff($info[self::SAVED_CRAWL_TIMES], $saved_crawl_times)
                != [] ||
                array_diff($saved_crawl_times, $info[self::SAVED_CRAWL_TIMES])
                != []) {
                $saved_crawl_times = $info[self::SAVED_CRAWL_TIMES];
                $this->deleteOldCrawls($saved_crawl_times);
            }
        }
        $check_cull_fields = [
            self::RESTRICT_SITES_BY_URL => "restrict_sites_by_url",
            self::ALLOWED_SITES => "allowed_sites",
            self::DISALLOWED_SITES => "disallowed_sites"];
        $cull_now_non_crawlable = false;
        foreach ($check_cull_fields as $info_field => $field) {
            if (isset($info[$info_field])) {
                if (!isset($this->$field) || $this->$field !=
                    $info[$info_field]) {
                    $cull_now_non_crawlable = true;
                }
                $this->$field = $info[$info_field];
            }
        }
        if ($cull_now_non_crawlable) {
            L\crawlLog("Allowed/Disallowed Urls have changed");
            L\crawlLog("Checking if urls in to crawl lists need to be culled");
            $this->cullNoncrawlableSites();
        }
        if (isset($info[self::CRAWL_TIME])
            && ($info[self::CRAWL_TIME] != $this->crawl_time
            || $info[self::CRAWL_TIME] == 0)) {
            if ($info[self::CRAWL_TIME] > 0) {
                L\crawlLog("New Crawl Time Found: {$info[self::CRAWL_TIME]}");
            } else {
                L\crawlLog("Crawl Time Changing to 0 -- No Crawl");
            }
            $dir = C\CRAWL_DIR . "/schedules";
            $time_change = true;
            /* Zero out the crawl. If haven't done crawl before, then scheduler
               will be called */
            $this->to_crawl = [];
            $this->to_crawl_again = [];
            $this->found_sites = [];
            if (isset($info[self::QUEUE_SERVERS])) {
                $count_servers = count($info[self::QUEUE_SERVERS]);
                if (!isset($this->queue_servers) ||
                    count($this->queue_servers) != $count_servers) {
                    L\crawlLog("New Queue Server List:");
                    $server_num = 0;
                    foreach ($info[self::QUEUE_SERVERS] as $server) {
                        $server_num++;
                        L\crawlLog("($server_num) $server");
                    }
                }
                $this->queue_servers = $info[self::QUEUE_SERVERS];
                if (!isset($this->current_server) ||
                    $this->current_server > $count_servers) {
                    /*
                        prevent all fetchers from initially contacting same
                        queue servers
                    */
                    $this->current_server = rand(0, $count_servers - 1);
                }
            }
            if ($this->crawl_time > 0) {
                file_put_contents("$dir/$prefix" . self::fetch_closed_name .
                    "{$this->crawl_time}.txt", "1");
            }
            /* Update the basic crawl info, so that we can decide between going
               to a queue server for a schedule or to the name server for
               archive data. */
            $this->crawl_time = $info[self::CRAWL_TIME];
            if (!empty($info[self::SLOW_START])) {
                $this->num_multi_curl = min(C\NUM_MULTI_CURL_PAGES,
                    max(1, intval($info[self::SLOW_START])));
                $this->slow_start_mode = true;
                L\crawlLog("!!!Setting slow start num multi curl pages to:".
                    $this->num_multi_curl . " for first hour of crawl!!!");
            }
            if ($this->crawl_time > 0 && isset($info[self::ARC_DIR]) ) {
                $this->crawl_type = $info[self::CRAWL_TYPE];
                $this->arc_dir = $info[self::ARC_DIR];
                $this->arc_type = $info[self::ARC_TYPE];
            } else {
                $this->crawl_type = self::WEB_CRAWL;
                $this->arc_dir = '';
                $this->arc_type = '';
            }
            $this->setCrawlParamsFromArray($info);
            // Load any batch that might exist for changed-to crawl
            if (file_exists("$dir/$prefix" . self::fetch_crawl_info.
                "{$this->crawl_time}.txt") && file_exists(
                "$dir/$prefix" . self::fetch_batch_name .
                    "{$this->crawl_time}.txt")) {
                L\crawlLog("Loading old batches for ".
                    "{$this->crawl_time}.");
                $info = unserialize(file_get_contents(
                    "$dir/$prefix" . self::fetch_crawl_info.
                        "{$this->crawl_time}.txt"));
                $this->setCrawlParamsFromArray($info);
                unlink("$dir/$prefix" . self::fetch_crawl_info.
                    "{$this->crawl_time}.txt");
                $this->to_crawl = unserialize(file_get_contents(
                    "$dir/$prefix".
                        self::fetch_batch_name."{$this->crawl_time}.txt"));
                unlink("$dir/$prefix".self::fetch_batch_name.
                    "{$this->crawl_time}.txt");
                if (file_exists("$dir/$prefix".self::fetch_closed_name .
                    "{$this->crawl_time}.txt")) {
                    unlink("$dir/$prefix".self::fetch_closed_name .
                        "{$this->crawl_time}.txt");
                } else {
                    $update_num = C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER;
                    L\crawlLog("Fetch on crawl {$this->crawl_time} was not ".
                        "halted properly.");
                    L\crawlLog("  Dumping $update_num from old fetch ".
                        "to try to make a clean re-start.");
                    $count = count($this->to_crawl);
                    if ($count > C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER) {
                        $this->to_crawl = array_slice($this->to_crawl,
                            C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER);
                    } else {
                        $this->to_crawl = [];
                    }
                }
            }
            if (L\generalIsA(C\NS_ARCHIVE . $this->arc_type . "Iterator",
                C\NS_ARCHIVE . "TextArchiveBundleIterator")) {
                $result_dir = C\WORK_DIRECTORY . "/schedules/" .
                    $prefix . self::fetch_archive_iterator . $this->crawl_time;
                $iterator_name = C\NS_ARCHIVE . $this->arc_type . "Iterator";
                $this->archive_iterator = new $iterator_name(
                    $info[self::CRAWL_INDEX],
                    false, $this->crawl_time, $result_dir);
                $this->db->setWorldPermissionsRecursive($result_dir);
            }
        }
        L\crawlLog("End Name Server Check");
        if ($time_change) {
            L\crawlLog("Crawl time has changed!");
        } else {
            L\crawlLog("Crawl time stayed same!");
        }
        return $time_change;
    }
    /**
     * Get status, current crawl, crawl order, and new site information from
     * the queue_server.
     *
     * @return mixed array or bool. If we are doing
     *     a web crawl and we still have pages to crawl then true, if the
     *     scheduler page fails to download then false, otherwise, returns
     *     an array of info from the scheduler.
     */
    public function checkScheduler()
    {
        $prefix = $this->fetcher_num . "-" . $this->channel . "-";
        $info = [];
        $to_crawl_count = count($this->to_crawl);
        $to_crawl_again_count = count($this->to_crawl_again);
        if ($this->recrawl_check_scheduler) {
            L\crawlLog("Archive Crawl checking ... Recrawl.");
        }
        if ((count($this->to_crawl) > 0 || count($this->to_crawl_again) > 0) &&
           (!$this->recrawl_check_scheduler)) {
            L\crawlLog("  Current to crawl count:" . $to_crawl_count);
            L\crawlLog("  Current to crawl try again count:".
                $to_crawl_again_count);
            L\crawlLog("So not checking scheduler.");
            return true;
        }
        $this->current_server = ($this->current_server + 1)
            % count($this->queue_servers);
        $this->recrawl_check_scheduler = false;
        $queue_server = $this->queue_servers[$this->current_server];
        L\crawlLog("Checking  $queue_server for a new schedule.");
        // hosts with error counts cleared with each schedule
        $this->hosts_with_errors = [];
        $start_time = microtime(true);
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $request =
            $queue_server . "?c=fetch&a=schedule&time=$time&session=$session".
            "&robot_instance=" . $prefix . C\ROBOT_INSTANCE.
            "&machine_uri=" . C\WEB_URI . "&crawl_time=" . $this->crawl_time .
            "&check_crawl_time=" . $this->check_crawl_time;
        $info_string = FetchUrl::getPage($request, null, true);
        L\crawlLog("Making schedule request: " . $request);
        if ($info_string === false) {
            L\crawlLog("The request failed!!!!");
            return false;
        }
        $info_string = trim($info_string);
        $tok = strtok($info_string, "\n");
        $decode_token = base64_decode($tok);
        L\crawlLog("First 256 bytes of first token in response:");
        L\crawlLog(substr($decode_token, 0, 256));
        if ($decode_token[0] != 'a' && $decode_token[1] != ':') {
            L\crawlLog("Error in decoding response, request failed!!!!");
            return false;
        }
        $info = unserialize($decode_token);
        $this->setCrawlParamsFromArray($info);
        if (isset($info[self::SITES])) {
            $tok = strtok("\n"); //skip meta info
            $this->to_crawl = [];
            while($tok !== false) {
                $string = base64_decode($tok);
                $weight = L\unpackFloat(substr($string, 0 , 4));
                $delay = L\unpackInt(substr($string, 4 , 4));
                $url = substr($string, 8);
                $this->to_crawl[] = [$url, $weight, $delay];
                $tok = strtok("\n");
            }
            $dir = C\CRAWL_DIR . "/schedules";
            file_put_contents("$dir/$prefix".
                self::fetch_batch_name . "{$this->crawl_time}.txt",
                serialize($this->to_crawl));
            $this->db->setWorldPermissionsRecursive("$dir/$prefix".
                self::fetch_batch_name . "{$this->crawl_time}.txt");
            unset($info[self::SITES]);
            file_put_contents("$dir/$prefix".
                self::fetch_crawl_info . "{$this->crawl_time}.txt",
                serialize($info));
        }
        L\crawlLog("Time to check Scheduler " .
            L\changeInMicrotime($start_time));
        return $info;
    }
    /**
     * During an archive crawl this method is used to get from the name server
     * a collection of pages to process. The fetcher will later process these
     * and send summaries to various queue_servers.
     *
     * @return array containing archive page data
     */
    public function checkArchiveScheduler()
    {
        $start_time = microtime(true);
        /*
            It's still important to switch queue servers, so that we send new
            data to each server each time we fetch
            new data from the name server.
        */
        $this->selectCurrentServerAndUpdateIfNeeded(false);
        $chunk = false;
        if (L\generalIsA(C\NS_ARCHIVE . $this->arc_type . "Iterator",
            C\NS_ARCHIVE . "TextArchiveBundleIterator")) {
            $archive_iterator = $this->archive_iterator;
            $chunk = true;
            $info = [];
            $max_offset = A\TextArchiveBundleIterator::BUFFER_SIZE +
                A\TextArchiveBundleIterator::MAX_RECORD_SIZE;
            if ($archive_iterator->buffer_fh &&
                $archive_iterator->current_offset < $max_offset) {
                L\crawlLog("Local Iterator Offset: ".
                    $archive_iterator->current_offset);
                L\crawlLog("Local Max Offset: ". $max_offset);
                $info[self::ARC_DATA] =
                    $archive_iterator->nextPages(C\ARCHIVE_BATCH_SIZE);
                L\crawlLog("Time to get archive data from local buffer ".
                    L\changeInMicrotime($start_time));
            }
            if ($archive_iterator->buffer_fh
                && $archive_iterator->current_offset < $max_offset ) {
                return $info;
            }
            if (isset($info[self::ARC_DATA]) && count($info[self::ARC_DATA])>0){
                $arc_data = $info[self::ARC_DATA];
            }
            L\crawlLog("Done processing Local Buffer, requesting more data...");
        }
        L\crawlLog("Fetching Archive data from name server with request:");
        $name_server = $this->name_server;
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $prefix = $this->fetcher_num . "-" . $this->channel . "-";
        $request =
            $name_server."?c=fetch&a=archiveSchedule&time=$time".
            "&session=$session&robot_instance=" . $prefix . C\ROBOT_INSTANCE.
            "&machine_uri=" . C\WEB_URI . "&crawl_time=" . $this->crawl_time .
            "&check_crawl_time=" . $this->check_crawl_time;
        L\crawlLog($request);
        $response_string = FetchUrl::getPage($request, null, true);
        if ($response_string === false) {
            L\crawlLog("Request failed!");
            return false;
        }
        if ($response_string) {
            $info = @unserialize($response_string);
        } else {
            $info = [];
            $info[self::STATUS] = self::NO_DATA_STATE;
        }
        $this->setCrawlParamsFromArray($info);
        if (isset($info[self::DATA])) {
            /* Unpack the archive data and return it in the $info array; also
                write a copy to disk in case something goes wrong. */
            $pages = unserialize(gzuncompress(L\webdecode($info[self::DATA])));
            if ($chunk) {
                if (isset($pages[self::ARC_DATA]) ) {
                    if (isset($pages[self::INI])) {
                        $archive_iterator->setIniInfo($pages[self::INI]);
                    }
                    if ($pages[self::ARC_DATA]) {
                        $archive_iterator->makeBuffer($pages[self::ARC_DATA]);
                    }
                    if (isset($pages[self::HEADER]) &&
                        is_array($pages[self::HEADER]) &&
                        $pages[self::HEADER] != []) {
                        $archive_iterator->header = $pages[self::HEADER];
                    }
                    if (!$pages[self::START_PARTITION]) {
                        $archive_iterator->nextPages(1);
                    }
                    if (isset($pages[self::PARTITION_NUM])) {
                        L\crawlLog("  Done get data".
                            " from file {$pages[self::PARTITION_NUM]}");
                    }
                    if (isset($pages[self::NUM_PARTITIONS])) {
                        L\crawlLog(
                            "  of {$pages[self::NUM_PARTITIONS]} files.");
                    }
                }
                if (isset($arc_data)) {
                    $info[self::ARC_DATA] = $arc_data;
                }
            } else {
                $info[self::ARC_DATA] = $pages;
            }
        } else if (isset($info['ARCHIVE_BUNDLE_ERROR'])) {
            L\crawlLog("  ".$info['ARCHIVE_BUNDLE_ERROR']);
        }
        L\crawlLog("Time to fetch archive data from name server ".
            L\changeInMicrotime($start_time));
        return $info;
    }
    /**
     * Function to check if memory for this fetcher instance is getting low
     * relative to what the system will allow.
     *
     * @return bool whether available memory is getting low
     */
    public function exceedMemoryThreshold()
    {
        return memory_get_usage() > (L\metricToInt(
            ini_get("memory_limit")) * C\MEMORY_FILL_FACTOR);
    }
    /**
     * At least once, and while memory is low selects next server and send
     * any fetcher data we have to it.
     *
     * @param bool $at_least_current_server whether to send to the site info
     *     to at least one queue server or to send only if memory is above
     *     threshold. Only in later casee is next server advanced.
     */
    public function selectCurrentServerAndUpdateIfNeeded(
        $at_least_current_server)
    {
        $i = 0;
        $num_servers = count($this->queue_servers);
        /*  Make sure no queue server starves if to crawl data available.
            Try to keep memory foot print smaller.
         */
        $cs = $this->current_server;
        do {
            if ($at_least_current_server ||
                (isset($this->found_sites[self::TO_CRAWL][$cs]) &&
                count($this->found_sites[self::TO_CRAWL][$cs]) > 0)) {
                $this->updateScheduler($cs, $at_least_current_server);
                $at_least_current_server = false;
            }
            $cs = ($cs + 1) % $num_servers;
            $i++;
        } while($this->exceedMemoryThreshold() && $i < $num_servers);
    }
    /**
     * Sets parameters for fetching based on provided info struct
     * ($info typically would come from the queue server)
     *
     * @param array &$info struct with info about the kind of crawl, timestamp
     * of index, crawl order, etc.
     */
    public function setCrawlParamsFromArray(&$info)
    {
        /* QUEUE_SERVERS and CURRENT_SERVER might not be set if info came
            from a queue_server rather than from name server
         */
        if (isset($info[self::QUEUE_SERVERS])) {
            $this->queue_servers = $info[self::QUEUE_SERVERS];
        } else {
            $info[self::QUEUE_SERVERS] = $this->queue_servers;
        }
        if (!isset($info[self::CURRENT_SERVER])) {
            $info[self::CURRENT_SERVER] = $this->current_server;
        }
        $update_fields = [
            self::ALLOWED_SITES => 'allowed_sites',
            self::CACHE_PAGES => 'cache_pages',
            self::CRAWL_INDEX => "crawl_index",
            self::CRAWL_ORDER => 'crawl_order',
            self::MAX_DEPTH => 'max_depth',
            self::SLEEP_START => 'sleep_start',
            self::SLEEP_DURATION => 'sleep_duration',
            self::CRAWL_TYPE => "crawl_type",
            self::DISALLOWED_SITES => 'disallowed_sites',
            self::INDEXED_FILE_TYPES => 'indexed_file_types',
            self::MINIMUM_FETCH_LOOP_TIME => 'minimum_fetch_loop_time',
            self::PROXY_SERVERS => 'proxy_servers',
            self::RESTRICT_SITES_BY_URL => 'restrict_sites_by_url',
            self::SUMMARIZER_OPTION => "summarizer_option",
            self::TOR_PROXY => 'tor_proxy'];
        $check_cull_fields = ["restrict_sites_by_url", "allowed_sites",
            "disallowed_sites"];
        $cull_now_non_crawlable = false;
        foreach ($update_fields as $info_field => $field) {
            if (isset($info[$info_field])) {
                if (in_array($info_field, $check_cull_fields) &&
                    (!isset($this->$field) || $this->$field !=
                    $info[$info_field]) ) {
                    $cull_now_non_crawlable = true;
                }
                $this->$field = $info[$info_field];
            }
        }
        L\crawlLog("Minimum fetch loop time is: " .
            $this->minimum_fetch_loop_time . " seconds");
        if ($cull_now_non_crawlable) {
            L\crawlLog("Allowed/Disallowed Urls have changed");
            L\crawlLog("Checking if urls in to crawl lists need to be culled");
            $this->cullNoncrawlableSites();
        }
        if (!empty($info[self::ACTIVE_CLASSIFIERS_DATA])){
            $this->active_classifiers = isset($info[self::ACTIVE_CLASSIFIERS])
                 && is_array( $info[self::ACTIVE_CLASSIFIERS]) ?
                $info[self::ACTIVE_CLASSIFIERS] : [];
            $this->active_rankers = isset($info[self::ACTIVE_RANKERS])
                 && is_array($info[self::ACTIVE_RANKERS]) ?
                $info[self::ACTIVE_RANKERS] : [];
            /*
               The classifier data is set by the fetch controller for each
               active classifier, and is a compressed, serialized structure
               containing all of the objects needed for classification.
             */
            $classifiers_data = $info[self::ACTIVE_CLASSIFIERS_DATA];
            $this->classifiers = [];
            foreach ($classifiers_data as $label => $classifier_data) {
                if ($classifier_data) {
                    $classifier = Classifier::newClassifierFromData(
                        $classifier_data);
                    $this->classifiers[] = $classifier;
                    L\crawlLog("Loading '{$label}' classifier/ranker.");
                    if (in_array($label, $this->active_classifiers)) {
                        L\crawlLog("  Using '{$label}' as a classifier.");
                    }
                    if (in_array($label, $this->active_rankers)) {
                        L\crawlLog("  Using '{$label}' as a ranker.");
                    }
                } else {
                    L\crawlLog("Skipping classifier '{$label}'; missing ".
                        "finalized data.");
                }
            }
        }
        if (isset($info[self::PAGE_RULES]) ){
            $rule_string = implode("\n", $info[self::PAGE_RULES]);
            $rule_string = html_entity_decode($rule_string, ENT_QUOTES);
            $this->page_rule_parser =
                new PageRuleParser($rule_string);
        }
        if (isset($info[self::INDEXING_PLUGINS]) &&
            $this->plugin_hash !=
            L\crawlHash(serialize($info[self::INDEXING_PLUGINS]) )) {
            $this->plugin_hash =
                L\crawlHash(serialize($info[self::INDEXING_PLUGINS]));
            $this->plugin_processors = [];
            foreach ($info[self::INDEXING_PLUGINS] as $plugin) {
                if (empty($plugin)) {
                    continue;
                }
                $plugin_name = C\NS_PLUGINS . $plugin . "Plugin";
                $processors = $plugin_name::getProcessors();
                $plugin_object = new $plugin_name();
                if (method_exists($plugin_name, "setConfiguration") &&
                    isset($info[self::INDEXING_PLUGINS_DATA][$plugin])) {
                    $plugin_object->setConfiguration(
                        $info[self::INDEXING_PLUGINS_DATA][$plugin]);
                }
                foreach ($processors as $processor) {
                    $this->plugin_processors[C\NS_PROCESSORS .
                        $processor][$plugin_name] = $plugin_object;
                }
            }
            foreach ($this->indexed_file_types as $file_type) {
                $processor = C\NS_PROCESSORS . ucfirst($file_type)."Processor";
                $processor_path = C\BASE_DIR . "/library/processors/".
                    ucfirst($file_type)."Processor.php";
                if (!class_exists($processor)) {
                    continue;
                }
                if (!isset($this->plugin_processors[$processor])) {
                    $this->plugin_processors[$processor] = [];
                }
                $parent_processor = $processor;
                while(($parent_processor =
                    get_parent_class($parent_processor)) &&
                    $parent_processor != C\NS_PROCESSORS . "PageProcessor") {
                    if (isset($this->plugin_processors[$parent_processor])) {
                        $this->plugin_processors[$processor] +=
                            $this->plugin_processors[$parent_processor];
                    }
                }
            }
            foreach ($this->plugin_processors as $processor => $plugins) {
                $this->plugin_processors[$processor] = array_values($plugins);
            }
        }
        if (!empty($info[self::SCRAPERS]) ) {
            $this->scrapers = unserialize(base64_decode($info[self::SCRAPERS]));
        }
        if (isset($info[self::POST_MAX_SIZE]) && ($this->post_max_size >
            $info[self::POST_MAX_SIZE] || !$this->post_max_size) ) {
            $this->post_max_size = $info[self::POST_MAX_SIZE];
        }
        if (isset($info[self::SCHEDULE_TIME])) {
              $this->schedule_time = $info[self::SCHEDULE_TIME];
        }
        if (isset($info[self::PAGE_RANGE_REQUEST])) {
            $this->page_range_request = $info[self::PAGE_RANGE_REQUEST];
        }
        if (isset($info[self::MAX_DESCRIPTION_LEN])) {
            $this->max_description_len = $info[self::MAX_DESCRIPTION_LEN];
        }
        if (isset($info[self::MAX_LINKS_TO_EXTRACT])) {
            $this->max_links_to_extract = $info[self::MAX_LINKS_TO_EXTRACT];
        }
    }
    /**
     * Prepare an array of up to NUM_MULTI_CURL_PAGES' worth of sites to be
     * downloaded in one go using the to_crawl array. Delete these sites
     * from the to_crawl array.
     *
     * @return array sites which are ready to be downloaded
     */
    public function getFetchSites()
    {
        $web_archive = $this->web_archive;
        $start_time = microtime(true);
        $seeds = [];
        $delete_indices = [];
        $num_items = count($this->to_crawl);
        if ($num_items > 0) {
            $crawl_source = & $this->to_crawl;
            $to_crawl_flag = true;
        } else {
            L\crawlLog("...Trying to crawl sites which failed the first time");
            $num_items = count($this->to_crawl_again);
            $crawl_source = & $this->to_crawl_again;
            $to_crawl_flag = false;
        }
        reset($crawl_source);
        if (!empty($this->slow_start_mode) &&
            time() > $this->crawl_time + C\ONE_HOUR) {
            $this->slow_start_mode = false;
            if ($this->num_multi_curl < C\NUM_MULTI_CURL_PAGES) {
                $this->num_multi_curl = C\NUM_MULTI_CURL_PAGES;
            }
            L\crawlLog("!!!Turning off slow start multi curl parameter!!!");
        }
        if ($num_items > $this->num_multi_curl) {
            $num_items = $this->num_multi_curl;
        }
        //DNS lookups take longer so try to get fewer in one go
        $num_ip_lookups = max($num_items/3, 2);
        $i = 0;
        $ip_lookup_cnt = 0;
        foreach ($crawl_source as $site_key => $site_value) {
            if ($i >= $num_items || $ip_lookup_cnt >= $num_ip_lookups) {
                break;
            }
            $delete_indices[] = $site_key;
            if ($site_value[0] != self::DUMMY) {
                $host = UrlParser::getHost($site_value[0]);
                if (!strpos($site_value[0], "###")) {
                    $ip_lookup_cnt++;
                }
                // only download if host doesn't seem congested
                if (!isset($this->hosts_with_errors[$host]) ||
                    $this->hosts_with_errors[$host] <
                        C\DOWNLOAD_ERROR_THRESHOLD) {
                    $url_to_check = $site_value[0];
                    $extension = UrlParser::getDocumentType($url_to_check);
                    $repository_indicator = FetchGitRepositoryUrls::
                        checkForRepository($extension);
                    if ($repository_indicator == self::REPOSITORY_GIT) {
                        $git_internal_urls = FetchGitRepositoryUrls::
                            setGitRepositoryUrl($url_to_check, $i, $seeds,
                                $repository_indicator, $site_value,
                                    $this->total_git_urls, $this->all_git_urls);
                        $i = $git_internal_urls['position'];
                        $git_url_index = $git_internal_urls['index'];
                        $seeds = $git_internal_urls['seeds'];
                        $repository_indicator = $git_internal_urls['indicator'];
                        $this->total_git_urls = $git_internal_urls['count'];
                        $this->all_git_urls = $git_internal_urls['all'];
                    } else {
                        $seeds[$i][self::URL] = $site_value[0];
                        $seeds[$i][self::WEIGHT] = $site_value[1];
                        $seeds[$i][self::CRAWL_DELAY] = $site_value[2];
                        if (!empty($site_value[self::NO_RANGE])) {
                            $seeds[$i][self::NO_RANGE] = true;
                        }
                    }
                    /*
                      Crawl delay is only used in scheduling on the QueueServer.
                      On the Fetcher, we only use crawl-delay to determine
                      if we will give a page a second try if it doesn't
                      download the first time
                    */
                    if (UrlParser::getDocumentFilename($seeds[$i][self::URL]) .
                        "." . UrlParser::getDocumentType($seeds[$i][self::URL])
                        == "robots.txt") {
                        $seeds[$i][self::ROBOT_PATHS] = [
                            self::ALLOWED_SITES => [],
                            self::DISALLOWED_SITES => []];
                    }
                    $i++;
                }
            } else {
                break;
            }
        } //end for
        foreach ($delete_indices as $delete_index) {
            $git_set = false;
            if ($to_crawl_flag == true) {
                $extension = UrlParser::getDocumentType(
                    $this->to_crawl[$delete_index][0]);
                $repository_type = FetchGitRepositoryUrls::checkForRepository(
                    $extension);
                if ($repository_type != self::REPOSITORY_GIT) {
                    unset($this->to_crawl[$delete_index]);
                }
            } else {
                $extension = UrlParser::getDocumentType(
                    $this->to_crawl_again[$delete_index][0]);
                $repository_type = FetchGitRepositoryUrls::checkForRepository(
                    $extension);
                unset($this->to_crawl_again[$delete_index]);
            }
            if ($repository_type == self::REPOSITORY_GIT) {
                if (!$git_set) {
                    $next_url_start = $url_to_check . self::GIT_URL_CONTINUE.
                        $git_url_index;
                    $git_set = true;
                    $this->to_crawl[$delete_index][0] = $next_url_start;
                }
                if ($repository_indicator == self::INDICATOR_NONE) {
                    unset($this->to_crawl[$delete_index]);
                }
            }
        }
        L\crawlLog("Fetch url list to download time ".
            L\changeInMicrotime($start_time));
        return $seeds;
    }
    /**
     * Sorts out pages for which no content was downloaded so that they can be
     * scheduled to be crawled again.
     *
     * @param array &$site_pages pages to sort
     * @return an array conisting of two array downloaded pages and
     * not downloaded pages.
     */
    public function reschedulePages(&$site_pages)
    {
        $start_time = microtime(true);
        $downloaded = [];
        $not_downloaded = [];
        $time = time();
        foreach ($site_pages as $site) {
            if ( (isset($site[self::ROBOT_PATHS]) || isset($site[self::PAGE]))
                && (is_numeric($site[self::HTTP_CODE] ) &&
                $site[self::HTTP_CODE] > 0 && $site[self::HTTP_CODE] != 416) ) {
                $downloaded[] = $site;
            } else if (substr($site[self::URL], -10) == "robots.txt"  &&
                $time - $this->crawl_time > C\ONE_DAY) {
                /*
                    Assume slow to respond robots sites after the first day
                    of crawling are probably spammy (slow to respond and
                    low value content, sucking up lots of reschedule
                    resources), so don't reschedule or bother trying to crawl
                    the rest of the site. If have allowed recrawl after x time
                    set, then after x time if the site is legit we will
                    probably find and try it again.
                 */
                L\crawlLog("Slow to respond robots.txt at: ". $site[self::URL] .
                    " so blocking rather than rescheduling.");
                $site[self::HTTP_CODE] = 408;
                $site[self::PAGE] = "User-agent: *\nDisallow: /\n";
                $site[self::TYPE] = "text/robot";
                $downloaded[] = $site;
            } else {
                if ($site[self::HTTP_CODE] == 416) {
                    L\crawlLog("Range request not satisfiable...");
                    $site[self::NO_RANGE] = true;
                }
                L\crawlLog("Rescheduling ". $site[self::URL]);
                $not_downloaded[] = $site;
            }
        }
        L\crawlLog("  Sort downloaded/not downloaded ".
            L\changeInMicrotime($start_time));
        return [$downloaded, $not_downloaded];
    }
    /**
     * Processes an array of downloaded web pages with the appropriate page
     * processor.
     *
     * Summary data is extracted from each non robots.txt file in the array.
     * Disallowed paths and crawl-delays are extracted from robots.txt files.
     *
     * @param array $site_pages a collection of web pages to process
     * @return array summary data extracted from these pages
     */
    public function processFetchPages($site_pages)
    {
        $page_processors = $this->page_processors;
        L\crawlLog("Start process pages... Current Memory:" .
            memory_get_usage());
        $start_time = microtime(true);
        $prefix = $this->fetcher_num . "-";
        $stored_site_pages = [];
        $summarized_site_pages = [];
        $num_items = $this->web_archive->count;
        $found_thumb_urls = false;
        $i = 0;
        foreach ($site_pages as $site) {
            $response_code = $site[self::HTTP_CODE];
            $was_error = false;
            if ($response_code < 200 || $response_code >= 300) {
                L\crawlLog($site[self::URL] . " response code $response_code");
                $host = UrlParser::getHost($site[self::URL]);
                if (!isset($this->hosts_with_errors[$host])) {
                    $this->hosts_with_errors[$host] = 0;
                }
                if (($response_code >= 400 && $response_code != 404) ||
                    $response_code < 100) {
                    // < 100 will capture failures to connect which are returned
                    // as strings
                    $was_error = true;
                    $this->hosts_with_errors[$host]++;
                }
                /* we print out errors to std output. We still go ahead and
                   process the page. Maybe it is a cool error page, also
                   this makes sure we don't crawl it again
                */
            }
            // text/robot is my made up mimetype for robots.txt files
            $was_robot_error = false;
            if (isset($site[self::ROBOT_PATHS])) {
                if (!$was_error) {
                    $type = "text/robot";
                } else {
                    $type = $site[self::TYPE];
                    if ($response_code != 404) {
                        /*
                            disallow crawling if robots.txt has any error other
                            than not found
                         */
                        $was_robot_error = true;
                        $site[self::ROBOT_PATHS][
                            self::DISALLOWED_SITES][] = "/";
                     }
                }
            } else if (isset($site[self::FILE_NAME])) {
                $extension = UrlParser::getDocumentType($site[self::FILE_NAME]);
                if ($extension ==
                    $this->programming_language_extension['java']) {
                    $type = "text/java";
                } else if ($extension ==
                    $this->programming_language_extension['py']) {
                    $type = "text/py";
                } else {
                    $type = $site[self::TYPE];
                }
            } else {
                $type = $site[self::TYPE];
            }
            $handled = false;
            /*deals with short URLs and directs them to the original link
              for robots.txt don't want to introduce stuff that can be
              mis-parsed (we follow redirects in this case anyway) */
            if (isset($site[self::LOCATION]) &&
                count($site[self::LOCATION]) > 0
                && strcmp($type, "text/robot") != 0) {
                array_unshift($site[self::LOCATION], $site[self::URL]);
                $tmp_loc = array_pop($site[self::LOCATION]);
                $tmp_loc = UrlParser::canonicalLink(
                    $tmp_loc, $site[self::URL]);
                $site[self::LOCATION] = array_push($site[self::LOCATION],
                    $tmp_loc);
                $doc_info = [];
                $doc_info[self::LINKS][$tmp_loc] =
                    "location:" . $site[self::URL];
                $doc_info[self::LOCATION] = true;
                $doc_info[self::DESCRIPTION] = $site[self::URL]." => ".
                        $tmp_loc;
                $doc_info[self::PAGE] = $doc_info[self::DESCRIPTION];
                $doc_info[self::TITLE] = $site[self::URL];
                $text_data = true;
                if (!isset($site[self::ENCODING])) {
                    $site[self::ENCODING] = "UTF-8";
                }
                $handled = true;
            }
            if (!$handled) {
                $processor = $this->pageProcessor($type);
                if (!$processor) {
                    L\crawlLog("No page processor for mime type: ".$type);
                    L\crawlLog("Not processing: " . $site[self::URL]);
                    continue;
                }
                $text_data = $processor->text_data;
            }
            $original_links = [];
            if (isset($site[self::PAGE]) && !$handled) {
                if ($text_data) {
                    L\convertUtf8IfNeeded($site, self::PAGE, self::ENCODING,
                        C\NS_LIB . "crawlLog");
                }
                $page_processor = get_class($processor);
                L\crawlLog("  Using Processor..." . substr($page_processor,
                    strlen(C\NS_PROCESSORS)) . " for " . $site[self::URL]);
                if (isset($site[self::REPOSITORY_TYPE]) &&
                    $site[self::REPOSITORY_TYPE] == self::REPOSITORY_GIT) {
                    $tmp_url_store = $site[self::URL];
                    $site[self::URL] = $site[self::FILE_NAME];
                }
                if (L\generalIsA($page_processor, C\NS_PROCESSORS.
                    "HtmlProcessor")) {
                    $processor->scrapers = $this->scrapers;
                }
                if (in_array($site[self::TYPE], ['text/html', 'text/plain',
                    'application/xhtml+xml', 'application/xml', 'text/xml'] )) {
                    if (!mb_check_encoding($site[self::PAGE], "UTF-8")) {
                        $site[self::PAGE] =
                            mb_convert_encoding($site[self::PAGE], "UTF-8");
                    }
                }
                $page = $site[self::PAGE];
                $empty_image = false;
                if (L\generalIsA($page_processor, C\NS_PROCESSORS.
                    "ImageProcessor")) {
                    if (!empty($site[self::CONTENT_SIZE]) &&
                        !empty($site[self::SIZE]) && $site[self::CONTENT_SIZE] >
                        $site[self::SIZE]) {
                        $page = "";
                        $empty_image = true;
                    }
                }
                if ($empty_image) {
                    $doc_info = null;
                } else {
                    $doc_info = $processor->handle($page,
                        $site[self::URL]);
                }
                if (C\FETCHER_PROCESS_DELAY > 0 ) {
                    usleep(C\FETCHER_PROCESS_DELAY);
                }
                if (isset($site[self::REPOSITORY_TYPE]) &&
                    $site[self::REPOSITORY_TYPE] == self::REPOSITORY_GIT) {
                    $site[self::URL] = $tmp_url_store;
                }
                if (!$doc_info) {
                    L\crawlLog("  Processing Yielded No Data For: ".
                        $site[self::URL]);
                }
                if ($page_processor != C\NS_PROCESSORS . "RobotProcessor" &&
                    !isset($doc_info[self::JUST_METAS])) {
                    if (!empty($doc_info[self::LINKS])) {
                        $site[self::CLD_IN_COMMON] = UrlParser::
                            countCompanyLevelDomainsInCommonDetectFarm(
                            $site[self::URL], array_keys(
                            $doc_info[self::LINKS]));
                        if ($site[self::CLD_IN_COMMON] < 0 &&
                            !C\nsdefined("KEEP_SPAM_DOMAINS") ) {
                            $doc_info[self::LINKS] = [];
                        } else {
                            $this->pruneLinks($doc_info, self::LINKS,
                                $start_time);
                            $site[self::CLD_IN_COMMON] = UrlParser::
                                countCompanyLevelDomainsInCommonDetectFarm(
                                $site[self::URL], array_keys(
                                $doc_info[self::LINKS]));
                        }
                    }
                }
            } else if (!$handled) {
                $doc_info = false;
            }
            if (empty($site[self::CLD_IN_COMMON])) {
                $site[self::CLD_IN_COMMON] = 0;
            }
            $not_loc = true;
            if ($doc_info) {
                $site[self::DOC_INFO] =  $doc_info;
                if (isset($doc_info[self::LOCATION])) {
                    $site[self::HASH] = L\crawlHash(
                        L\crawlHash($site[self::URL], true). "LOCATION", true);
                        $not_loc = false;
                }
                $site[self::ROBOT_INSTANCE] = $prefix . C\ROBOT_INSTANCE;
                if (!is_dir(C\CRAWL_DIR . "/cache")) {
                    mkdir(C\CRAWL_DIR . "/cache");
                    $htaccess = "Options None\nphp_flag engine off\n";
                    file_put_contents(CRAWL_DIR."/cache/.htaccess",
                        $htaccess);
                }
                if ($type == "text/robot" &&
                    isset($doc_info[self::PAGE])) {
                        $site[self::PAGE] = $doc_info[self::PAGE];
                }
                if ($text_data) {
                    if (isset($doc_info[self::PAGE])) {
                        $site[self::PAGE] = $doc_info[self::PAGE];
                    } else {
                        $site[self::PAGE] = null;
                    }
                    if ($not_loc) {
                        $content =
                            $doc_info[self::DESCRIPTION];
                        $site[self::HASH] = FetchUrl::computePageHash(
                            $content);
                    }
                } else {
                    $site[self::HASH] = FetchUrl::computePageHash(
                        $site[self::PAGE]);
                }
                if (isset($doc_info[self::WORD_CLOUD])) {
                    $site[self::WORD_CLOUD] = $doc_info[self::WORD_CLOUD];
                } else {
                    $site[self::WORD_CLOUD] = null;
                }
                if (isset($doc_info[self::CRAWL_DELAY])) {
                    $site[self::CRAWL_DELAY] = $doc_info[self::CRAWL_DELAY];
                }
                if (isset($doc_info[self::ROBOT_PATHS]) && !$was_error) {
                    $site[self::ROBOT_PATHS] = $doc_info[self::ROBOT_PATHS];
                }
                if (!isset($site[self::ROBOT_METAS])) {
                    $site[self::ROBOT_METAS] = [];
                }
                if (isset($doc_info[self::ROBOT_METAS])) {
                    $site[self::ROBOT_METAS] = array_merge(
                        $site[self::ROBOT_METAS], $doc_info[self::ROBOT_METAS]);
                }
                //here's where we enforce NOFOLLOW
                if (in_array("NOFOLLOW", $site[self::ROBOT_METAS]) ||
                    in_array("NONE", $site[self::ROBOT_METAS])) {
                    $site[self::DOC_INFO][self::LINKS] = [];
                }
                if (isset($doc_info[self::AGENT_LIST])) {
                    $site[self::AGENT_LIST] = $doc_info[self::AGENT_LIST];
                }
                if (isset($doc_info[self::IS_FEED])) {
                    $site[self::IS_FEED] = $doc_info[self::IS_FEED];
                }
                $this->copySiteFields($i, $site, $summarized_site_pages,
                    $stored_site_pages);
                $summarized_site_pages[$i][self::URL] =
                    strip_tags($site[self::URL]);
                if (isset($site[self::REPOSITORY_TYPE]) &&
                    $site[self::REPOSITORY_TYPE] == self::REPOSITORY_GIT) {
                     $summarized_site_pages[$i][self::TITLE] =
                         $site[self::FILE_NAME];
                } else if (isset($site[self::DOC_INFO][self::TITLE])) {
                     $summarized_site_pages[$i][self::TITLE] = strip_tags(
                         $site[self::DOC_INFO][self::TITLE]);
                    // stripping html to be on the safe side
                } else {
                    $summarized_site_pages[$i][self::TITLE] =
                        strip_tags($site[self::URL]);
                }
                if (!isset($site[self::REPOSITORY_TYPE])) {
                    if ($was_robot_error) {
                        $site[self::DOC_INFO][self::DESCRIPTION] =
                            "There was an HTTP error in trying to download ".
                            "this robots.txt file, so all paths to this site ".
                            "were disallowed by Yioop.\n".
                            $site[self::DOC_INFO][self::DESCRIPTION];
                    }
                    $summarized_site_pages[$i][self::DESCRIPTION] =
                        strip_tags($site[self::DOC_INFO][self::DESCRIPTION]);
                } else {
                    $summarized_site_pages[$i][self::DESCRIPTION] =
                        $site[self::DOC_INFO][self::DESCRIPTION];
                }
                if (isset($site[self::DOC_INFO][self::JUST_METAS]) ||
                    isset($site[self::ROBOT_PATHS])) {
                    $summarized_site_pages[$i][self::JUST_METAS] = true;
                }
                if (isset($site[self::DOC_INFO][self::META_WORDS])) {
                    if (!isset($summarized_site_pages[$i][self::META_WORDS])) {
                        $summarized_site_pages[$i][self::META_WORDS] =
                            $site[self::DOC_INFO][self::META_WORDS];
                    } else {
                        $summarized_site_pages[$i][self::META_WORDS] =
                            array_merge(
                                $summarized_site_pages[$i][self::META_WORDS],
                                $site[self::DOC_INFO][self::META_WORDS]);
                    }
                }
                if (isset($site[self::DOC_INFO][self::LANG])) {
                    if ($site[self::DOC_INFO][self::LANG] == 'en' &&
                        $site[self::ENCODING] != "UTF-8") {
                        $site[self::DOC_INFO][self::LANG] =
                            L\guessLangEncoding($site[self::ENCODING]);
                    }
                    $summarized_site_pages[$i][self::LANG] =
                        $site[self::DOC_INFO][self::LANG];
                }
                if (isset($site[self::DOC_INFO][self::LINKS])) {
                    $summarized_site_pages[$i][self::LINKS] =
                        $site[self::DOC_INFO][self::LINKS];
                }
                if (isset($site[self::DOC_INFO][self::WORD_CLOUD])) {
                    $summarized_site_pages[$i][self::WORD_CLOUD] =
                        $site[self::DOC_INFO][self::WORD_CLOUD];
                }
                if (isset($site[self::DOC_INFO][self::THUMB])) {
                    $summarized_site_pages[$i][self::THUMB] =
                        $site[self::DOC_INFO][self::THUMB];
                }
                if (isset($site[self::DOC_INFO][self::SUBDOCS])) {
                    $this->processSubdocs($i, $site, $summarized_site_pages,
                       $stored_site_pages);
                }
                if (isset($summarized_site_pages[$i][self::LINKS])) {
                    $summarized_site_pages[$i][self::LINKS] =
                        UrlParser::cleanRedundantLinks(
                            $summarized_site_pages[$i][self::LINKS],
                            $summarized_site_pages[$i][self::URL]);
                }
                if (!empty($this->classifiers)) {
                    Classifier::labelPage($summarized_site_pages[$i],
                        $this->classifiers, $this->active_classifiers,
                        $this->active_rankers);
                }
                if ($this->page_rule_parser != null) {
                    $this->page_rule_parser->executeRuleTrees(
                        $summarized_site_pages[$i]);
                }
                if (!empty($summarized_site_pages[$i][self::THUMB_URL]) &&
                    empty($summarized_site_pages[$i][self::THUMB])) {
                    $found_thumb_urls = true;
                }
                $metas = isset($summarized_site_pages[$i][self::ROBOT_METAS]) ?
                    $summarized_site_pages[$i][self::ROBOT_METAS] : [];
                if (array_intersect($metas,
                    ["NOARCHIVE", "NOINDEX", "JUSTFOLLOW", "NONE"]) != []) {
                    $stored_site_pages[$i] = false;
                }
                $stored_site_pages[$i][self::INDEX] = $i;
                $i++;
            }
        } // end for
        $num_pages = count($stored_site_pages);
        $filter_stored = array_filter($stored_site_pages);
        if (C\DEFAULT_CRAWL_FORMAT < 2 &&
            $num_pages > 0 && $this->cache_pages) {
            $cache_page_partition = $this->web_archive->addPages(
                self::OFFSET, $filter_stored);
        } else if ($num_pages > 0) {
            // In newer format fetcher archive only counts num cache pages
            $this->web_archive->addCount(count($filter_stored));
        }
        for ($i = 0; $i < $num_pages; $i++) {
            $summarized_site_pages[$i][self::INDEX] = $num_items + $i;
        }
        foreach ($filter_stored as $stored) {
            if (!isset($stored[self::INDEX]) ) {
                if (C\DEFAULT_CRAWL_FORMAT >= 2) {
                    $summarized_site_pages[$i][self::PAGE] = "";
                }
                continue;
            }
            $i = $stored[self::INDEX];
            if (C\DEFAULT_CRAWL_FORMAT < 2) {
                if (isset($stored[self::OFFSET])) {
                    $summarized_site_pages[$i][self::OFFSET] =
                        $stored[self::OFFSET];
                    $summarized_site_pages[$i][self::CACHE_PAGE_PARTITION] =
                        $cache_page_partition;
                }
            }
        }
        if ($this->crawl_type == self::WEB_CRAWL && $found_thumb_urls) {
            $this->getPageThumbs($summarized_site_pages);
        }
        L\crawlLog("  Process pages time: ".L\changeInMicrotime($start_time).
             " Current Memory: ".memory_get_usage());
        return $summarized_site_pages;
    }
    /**
     * Adds thumbs for websites with a self::THUMB_URL field by downloading the
     *  linked to images and making a thumb from it.
     *
     * @param array &$sites associative array of web sites information to add
     *  thumbs for. At least one site in the array should have a
     *  self::THUMB_URL field that we want have the thumb of
     */
    public function getPageThumbs(&$sites)
    {
        L\crawlLog("Getting Thumbs for Downloaded Pages...");
        $num_sites = count($sites);
        $thumb_sites = [];
        for ($i = 0; $i < $num_sites; $i++) {
            $thumb_sites[$i] = [];
            if (!empty($sites[$i][self::THUMB_URL])
                && empty($sites[$i][self::THUMB])) {
                $thumb_sites[$i][self::THUMB_URL] = $sites[$i][self::THUMB_URL];
            }
        }
        $thumb_sites = FetchUrl::getPages($thumb_sites, false,
            $this->page_range_request, null, self::THUMB_URL, self::DATA, true);
        for ($i = 0; $i < $num_sites; $i++) {
            if (!empty($thumb_sites[$i][self::DATA])) {
                set_error_handler(null);
                $image = @imagecreatefromstring($thumb_sites[$i][self::DATA]);
                $thumb_string = ImageProcessor::createThumb($image);
                set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                if ($thumb_string) {
                    $sites[$i][self::THUMB] = 'data:image/jpeg;base64,' .
                        base64_encode($thumb_string);
                }
            }
        }
    }
    /**
     * Used to remove from the to_crawl urls those that are no longer crawlable
     * because the allowed and disallowed sites have changed.
     */
    public function cullNoncrawlableSites()
    {
        $count = count($this->to_crawl);
        $count_again = count($this->to_crawl_again);
        L\crawlLog("Culling noncrawlable urls after change in crawl ".
            "parameters; To Crawl Count: $count, To Crawl Again: ".
            "$count_again");
        $start_time = microtime(true);
        $k = 0;
        for ($i = 0; $i < $count; $i++) {
            L\crawlTimeoutLog("..still culling to crawl urls. Examining ".
                "location %s in queue of %s.", $i, $count);
            if (empty($this->to_crawl[$i][0])) {
                unset($this->to_crawl[$i]);
                $k++;
            } else {
                list($url, ) = $this->to_crawl[$i];
                if (!$this->allowedToCrawlSite($url) ||
                    $this->disallowedToCrawlSite($url)) {
                    unset($this->to_crawl[$i]);
                    $k++;
                }
            }
        }
        $this->allow_disallow_cache_time = microtime(true);
        for ($i = 0; $i < $count_again; $i++) {
            L\crawlTimeoutLog("..still culling to crawl again urls. Examining ".
                "location %s in queue of %s.", $i, $count);
            if (empty($this->to_crawl_again[$i][0])) {
                unset($this->to_crawl_again[$i]);
                $k++;
            } else {
                list($url, ) = $this->to_crawl_again[$i];
                if (!$this->allowedToCrawlSite($url) ||
                    $this->disallowedToCrawlSite($url)) {
                    unset($this->to_crawl_again[$i]);
                    $k++;
                }
            }
        }
        L\crawlLog("...Removed $k cullable URLS  from to crawl lists in time: ".
            L\changeInMicrotime($start_time));
    }
    /**
     * Checks if url belongs to a list of sites that are allowed to be
     * crawled and that the file type is crawlable
     *
     * @param string $url url to check
     * @return bool whether is allowed to be crawled or not
     */
    public function allowedToCrawlSite($url)
    {
        $doc_type = UrlParser::getDocumentType($url);
        if (!in_array($doc_type, $this->all_file_types)) {
            $doc_type = "unknown";
        }
        if (!in_array($doc_type, $this->indexed_file_types)) {
            return false;
        }
        if ($this->restrict_sites_by_url) {
           return UrlParser::urlMemberSiteArray($url, $this->allowed_sites,
                "a" . $this->allow_disallow_cache_time);
        }
        return true;
    }
    /**
     * Checks if url belongs to a list of sites that aren't supposed to be
     * crawled
     *
     * @param string $url url to check
     * @return bool whether is shouldn't be crawled
     */
    public function disallowedToCrawlSite($url)
    {
        return UrlParser::urlMemberSiteArray($url, $this->disallowed_sites,
            "d" . $this->allow_disallow_cache_time);
    }
    /**
     * This method attempts to cull from the doc_info struct the
     * best $this->max_links_to_extract. Currently, this is done by first
     * removing links of filetype or sites the crawler is forbidden from crawl.
     * Then a crude estimate of the information contained in the links test:
     * strlen(gzip(text)) is used to extract the best remaining links.
     *
     * @param array &$doc_info a string with a CrawlConstants::LINKS subarray
     * This subarray in turn contains url => text pairs.
     * @param string $field field for links default is CrawlConstants::LINKS
     * @param int $member_cache_time says how long allowed and disallowed url
     *      info should be caches by urlMemberSiteArray
     */
    public function pruneLinks(&$doc_info, $field = CrawlConstants::LINKS,
        $member_cache_time = 0)
    {
        if (!isset($doc_info[$field])) {
            return;
        }
        $links = [];
        $allowed_name = "a" . $member_cache_time;
        $disallowed_name = "d" . $member_cache_time;
        if (!empty($this->domain_filters)) {
            $filters = array_column($this->domain_filters, "FILTER");
        }
        foreach ($doc_info[$field] as $url => $url_info) {
            $filename = UrlParser::getDocumentFilename($url);
            if (substr(UrlParser::guessMimeTypeFromFileName($filename), 0, 5)
                != "image" && !empty($filters) &&
                UrlParser::cullByDomainFilter($url, $filters)) {
                L\crawlLog("$url not in domain filter, removing link!");
                continue;
            }
            $doc_type = UrlParser::getDocumentType($url);
            if (!in_array($doc_type, $this->all_file_types)) {
                $doc_type = "unknown";
            }
            if (!in_array($doc_type, $this->indexed_file_types)) {
                continue;
            }
            if ($this->restrict_sites_by_url) {
                if (!UrlParser::urlMemberSiteArray($url, $this->allowed_sites,
                    $allowed_name)) {
                    continue;
                }
            }
            if (UrlParser::urlMemberSiteArray($url, $this->disallowed_sites,
                $disallowed_name)) {
                continue;
            }
            $links[$url] = $url_info;
        }
        $doc_info[$field] = UrlParser::pruneLinks($links,
            $this->max_links_to_extract);
    }
    /**
     * Copies fields from the array of site data to the $i indexed
     * element of the $summarized_site_pages and $stored_site_pages array
     *
     * @param int $i index to copy to
     * @param array $site web page info to copy
     * @param array &$summarized_site_pages array of summaries of web pages
     * @param array &$stored_site_pages array of cache info of web pages
     */
    public function copySiteFields($i, $site,
        &$summarized_site_pages, &$stored_site_pages)
    {
        $stored_fields = [self::URL, self::HEADER, self::PAGE];
        $summary_fields = [self::IP_ADDRESSES, self::IS_FEED, self::WEIGHT,
            self::TIMESTAMP, self::TYPE, self::ENCODING, self::HTTP_CODE,
            self::HASH, self::SERVER, self::SERVER_VERSION,
            self::OPERATING_SYSTEM, self::MODIFIED, self::ROBOT_INSTANCE,
            self::LOCATION, self::SIZE, self::TOTAL_TIME, self::DNS_TIME,
            self::ROBOT_PATHS, self::CRAWL_DELAY,
            self::AGENT_LIST, self::ROBOT_METAS, self::WARC_ID,
            self::CACHE_PAGE_VALIDATORS, self::CLD_IN_COMMON];
        foreach ($summary_fields as $field) {
            if (isset($site[$field])) {
                if (C\DEFAULT_CRAWL_FORMAT < 2) {
                    $stored_site_pages[$i][$field] = $site[$field];
                }
                $summarized_site_pages[$i][$field] = $site[$field];
            }
        }
        foreach ($stored_fields as $field) {
            if (isset($site[$field])) {
                if (C\DEFAULT_CRAWL_FORMAT < 2) {
                    $stored_site_pages[$i][$field] = $site[$field];
                } else {
                    $summarized_site_pages[$i][$field] = $site[$field];
                }
            }
        }
        $copy_doc_infos = array_diff(array_keys($site[self::DOC_INFO]),
            $summary_fields, $stored_fields);
        foreach ($copy_doc_infos as $field) {
            $summarized_site_pages[$i][$field] = $site[self::DOC_INFO][$field];
        }
    }
    /**
     * The pageProcessing method of an IndexingPlugin generates
     * a self::SUBDOCS array of additional "micro-documents" that
     * might have been in the page. This methods adds these
     * documents to the summaried_size_pages and stored_site_pages
     * arrays constructed during the execution of processFetchPages()
     *
     * @param int &$i index to begin adding subdocs at
     * @param array $site web page that subdocs were from and from
     *     which some subdoc summary info is copied
     * @param array &$summarized_site_pages array of summaries of web pages
     * @param array &$stored_site_pages array of cache info of web pages
     */
    public function processSubdocs(&$i, $site,
        &$summarized_site_pages, &$stored_site_pages)
    {
        $subdocs = $site[self::DOC_INFO][self::SUBDOCS];
        foreach ($subdocs as $subdoc) {
            $i++;
            $this->copySiteFields($i, $site, $summarized_site_pages,
                $stored_site_pages);
            $summarized_site_pages[$i][self::URL] =
                strip_tags($site[self::URL]);
            $summarized_site_pages[$i][self::TITLE] =
                strip_tags($subdoc[self::TITLE]);
            $summarized_site_pages[$i][self::DESCRIPTION] =
                strip_tags($subdoc[self::DESCRIPTION]);
            if (isset($site[self::JUST_METAS])) {
                $summarized_site_pages[$i][self::JUST_METAS] = true;
            }
            if (isset($subdoc[self::LANG])) {
                $summarized_site_pages[$i][self::LANG] =
                    $subdoc[self::LANG];
            }
            if (isset($subdoc[self::LINKS])) {
                $summarized_site_pages[$i][self::LINKS] =
                    $subdoc[self::LINKS];
            }
            if (isset($subdoc[self::SUBDOCTYPE])) {
                $summarized_site_pages[$i][self::SUBDOCTYPE] =
                    $subdoc[self::SUBDOCTYPE];
            }
        }
    }
    /**
     * Updates the $this->found_sites array with data from the most recently
     * downloaded sites. This means updating the following sub arrays:
     * the self::ROBOT_PATHS, self::TO_CRAWL. It checks if there are still
     * more urls to crawl or if self::SEEN_URLS has grown larger than
     * SEEN_URLS_BEFORE_UPDATE_SCHEDULER. If so, a mini index is built and,
     * the queue server is called with the data.
     *
     * @param array $sites site data to use for the update
     * @param bool $force_send whether to force send data back to queue_server
     *     or rely on usual thresholds before sending
     */
    public function updateFoundSites($sites, $force_send = false)
    {
        $start_time = microtime(true);
        L\crawlLog("  Updating Found Sites Array...");
        for ($i = 0; $i < count($sites); $i++) {
            $site = $sites[$i];
            if (!isset($site[self::URL])) {
                continue;
            }
            $host = UrlParser::getHost($site[self::URL]);
            if (isset($site[self::ROBOT_PATHS])) {
                if ($site[self::IP_ADDRESSES] == ["0.0.0.0"]) {
                    /* probably couldn't find site so this will block
                        from crawl
                    */
                    $site[self::ROBOT_PATHS][self::DISALLOWED_SITES] =
                        ["/"];
                }
                //set same robots.txt for all redirects went through
                $locations = [$host];
                if (!empty($site[self::LOCATION])) {
                    $locations = array_merge($locations, $site[self::LOCATION]);
                }
                foreach ($locations as $location) {
                    $h = UrlParser::getHost($location);
                    $this->found_sites[self::ROBOT_TXT][$h][self::IP_ADDRESSES]=
                        $site[self::IP_ADDRESSES];
                    $this->found_sites[self::ROBOT_TXT][$h][self::ROBOT_PATHS] =
                        $site[self::ROBOT_PATHS];
                    if (isset($site[self::CRAWL_DELAY])) {
                        $this->found_sites[self::ROBOT_TXT][$h][
                            self::CRAWL_DELAY] = $site[self::CRAWL_DELAY];
                    }
                }
                if (isset($site[self::LINKS])
                    && $this->crawl_type == self::WEB_CRAWL) {
                    $num_links = count($site[self::LINKS]);
                    //robots pages might have sitemaps links on them
                    //which we want to crawl
                    $link_urls = array_values($site[self::LINKS]);
                    $this->addToCrawlSites($link_urls,
                        $site[self::WEIGHT], $site[self::HASH],
                        $site[self::URL], $site[self::CLD_IN_COMMON], true);
                }
                $this->found_sites[self::SEEN_URLS][] = $site;
            } else {
                $this->found_sites[self::SEEN_URLS][] = $site;
                if (isset($site[self::LINKS])
                    && $this->crawl_type == self::WEB_CRAWL) {
                    if (!isset($this->found_sites[self::TO_CRAWL])) {
                        $this->found_sites[self::TO_CRAWL] = [];
                    }
                    $link_urls = array_keys($site[self::LINKS]);
                    $this->addToCrawlSites($link_urls, $site[self::WEIGHT],
                        $site[self::HASH],
                        $site[self::URL], $site[self::CLD_IN_COMMON]);
                }
            } //end else
            //Add cache page validation data
            if (isset($site[self::CACHE_PAGE_VALIDATORS])) {
                $this->found_sites[self::CACHE_PAGE_VALIDATION_DATA][] =
                    [$site[self::URL], $site[self::CACHE_PAGE_VALIDATORS]];
            }
            if (isset($this->hosts_with_errors[$host]) &&
                $this->hosts_with_errors[$host] > C\DOWNLOAD_ERROR_THRESHOLD) {
                $this->found_sites[self::ROBOT_TXT][$host][
                    self::CRAWL_DELAY] = C\ERROR_CRAWL_DELAY;
                L\crawlLog("setting crawl delay $host");
            }
            if (isset($this->found_sites[self::TO_CRAWL])) {
                $this->found_sites[self::TO_CRAWL] =
                    array_filter($this->found_sites[self::TO_CRAWL]);
            }
            if (isset($site[self::INDEX])) {
                $site_index = $site[self::INDEX];
            } else {
                $site_index = "[LINK]";
            }
            $subdoc_info = "";
            if (isset($site[self::SUBDOCTYPE])) {
                $subdoc_info = "(Subdoc: {$site[self::SUBDOCTYPE]})";
            }
            /* for log file get rid of non-utf-8 characters
               that latter make it hard to view the log
             */
            L\crawlLog($site_index . ". $subdoc_info ".
                iconv("UTF-8", "ISO-8859-1//IGNORE", $site[self::URL]));
        } // end for
        L\crawlLog("  Done Update Found Sites Array Time ".
            L\changeInMicrotime($start_time));
        $seen_urls_before_update_scheduler = (!empty($this->slow_start_mode)) ?
            max(1, C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER/4) :
            C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER;
        if ($force_send || ($this->crawl_type == self::WEB_CRAWL &&
            count($this->to_crawl) <= 0 && count($this->to_crawl_again) <= 0) ||
                (isset($this->found_sites[self::SEEN_URLS]) &&
                count($this->found_sites[self::SEEN_URLS]) >
                $seen_urls_before_update_scheduler) ||
                ($this->archive_iterator &&
                $this->archive_iterator->end_of_iterator) ||
                    $this->exceedMemoryThreshold() ) {
            $start_time = microtime(true);
            L\crawlLog("  Start Update Server ");
            $this->selectCurrentServerAndUpdateIfNeeded(true);
            L\crawlLog("  Update Server Time " .
                L\changeInMicrotime($start_time));
        }
    }
    /**
     * Used to add a set of links from a web page to the array of sites which
     * need to be crawled.
     *
     * @param array $link_urls an array of urls to be crawled
     * @param int $old_weight_pair the weight and depth of the web page
     *      the links came from (high 3 bytes for former low byte latter)
     * @param string $site_hash a hash of the web_page on which the link was
     *     found, for use in deduplication
     * @param string $old_url url of page where links came from
     * @param int $num_common number of company level domains in common between
     *      $link_urls and $old_url
     * @param bool $from_sitemap whether the links are coming from a sitemap
     */
    public function addToCrawlSites($link_urls, $old_weight_pair, $site_hash,
        $old_url, $num_common, $from_sitemap = false)
    {
        $num_links = count($link_urls);
        $old_cld = UrlParser::getCompanyLevelDomain($old_url);
        $old_url_scheme = UrlParser::getScheme($old_url);
        if ($num_links == 0) {
            if ($num_common < 0) {
                /* see QueueServer crawl delay code. If a host has no crawl
                   delay then, the cld host is checked for a crawl delay and
                   that is taken as the crawl delay value
                 */
                $this->found_sites[self::ROBOT_TXT][$old_url_scheme .
                    "://" . $old_cld][self::CRAWL_DELAY] = C\ERROR_CRAWL_DELAY;
                L\crawlLog(
                    "$old_cld seems to be a link farm, crawl delaying!!!!");
            }
            return;
        }
        // decode 3 bytes weight 1 byte depth
        list($old_weight, $old_depth) = L\decodeQueueWeightInfo(
            $old_weight_pair, $this->crawl_order);
        if ($this->max_depth >= 0 && $old_depth + 1 > $this->max_depth) {
            return; // reached max depth so don't add any urls
        }
        $sitemap_link_weight = 0.25;
        $num_queue_servers = count($this->queue_servers);
        $is_link_farm = false;
        $common_weight = 1;
        $common_weight_info = L\encodeQueueWeightInfo($common_weight,
            $old_depth + 1, self::PAGE_IMPORTANCE);
        if ($from_sitemap) {
            $square_factor = 0;
            for ($i = 1; $i <= $num_links; $i++) {
                $square_factor += 1/($i*$i);
            }
            $square_factor = ceil($square_factor);
        } else if ($this->crawl_order != self::BREADTH_FIRST) {
            // link farm detected!
            if ($num_common < 0 && !C\nsdefined("KEEP_SPAM_DOMAINS")) {
                $link_urls = [];
                $num_common = 0;
                $num_links = 0;
                $is_link_farm = true;
            }
            $num_different = $num_links - $num_common;
            if ($num_common > 0 && $num_different > 0) {
                $common_weight = (int)floor($old_weight/(2 * $num_links));
                $common_weight_info = L\encodeQueueWeightInfo($common_weight,
                    $old_depth + 1, self::PAGE_IMPORTANCE);
            }
            $remaining_weight = $old_weight - $common_weight * $num_common;
            if ($num_different > 0 ) {
                $different_weight =
                    (int)floor($remaining_weight/$num_different);
                    //favour links between different company level domains
                $different_weight_info = L\encodeQueueWeightInfo(
                    $different_weight, $old_depth + 1, self::PAGE_IMPORTANCE);
            }
        }
        if (empty($common_weight_info)) {
            $common_weight_info = L\encodeQueueWeightInfo(
                $num_links, $old_depth + 1, self::PAGE_IMPORTANCE);
        }
        if (empty($different_weight_info)) {
            $different_weight_info = L\encodeQueueWeightInfo(
                $num_links, $old_depth + 1, self::PAGE_IMPORTANCE);
        }
        if ($is_link_farm) {
            /* see QueueServer crawl delay code. If a host has no crawl
               delay then, the cld host is checked for a crawl delay and that
               is taken as the crawl delay value
             */
            $this->found_sites[self::ROBOT_TXT][$old_url_scheme .
                "://" . $old_cld][self::CRAWL_DELAY] = C\ERROR_CRAWL_DELAY;
            L\crawlLog("$old_cld seems to be a link farm, crawl delaying!!!!");
        }
        for ($i = 0; $i < $num_links; $i++) {
            $url = $link_urls[$i];
            if (strlen($url) > 0) {
                $part = L\calculatePartition($url, $num_queue_servers,
                    C\NS_LIB . "UrlParser::getHost");
                if ($this->crawl_order == self::BREADTH_FIRST) {
                    $weight_info = L\encodeQueueWeightInfo(0, $old_depth + 1,
                        self::BREADTH_FIRST);
                    $this->found_sites[self::TO_CRAWL][$part][] =
                        [$url, $weight_info, $site_hash . $i];
                } else if ($from_sitemap) {
                    $weight_info = L\encodeQueueWeightInfo(
                        (int)floor($old_weight * $sitemap_link_weight /
                        (($i + 1)*($i + 1) * $square_factor)), $old_depth + 1,
                        self::PAGE_IMPORTANCE);
                    $this->found_sites[self::TO_CRAWL][$part][] =
                        [$url, $weight_info, $site_hash . $i];
                } else { //page importance and default case
                    $cld = UrlParser::getCompanyLevelDomain($url);
                    if (strcmp($old_cld, $cld) == 0) {
                        $this->found_sites[self::TO_CRAWL][$part][] =
                            [$url, $common_weight_info, $site_hash . $i];
                    } else {
                        $this->found_sites[self::TO_CRAWL][$part][] =
                            [$url, $different_weight_info, $site_hash . $i];
                    }
                }
            }
        }
    }
    /**
     * Updates the queue_server about sites that have been crawled.
     *
     * This method is called if there are currently no more sites to crawl or
     * if SEEN_URLS_BEFORE_UPDATE_SCHEDULER many pages have been processed. It
     * creates a inverted index of the non robot pages crawled and then
     * compresses and does a post request to send the page summary data, robot
     * data, to crawl url data, and inverted index back to the server. In the
     * event that the server doesn't acknowledge it loops and tries again after
     * a delay until the post is successful. At this point, memory for this data
     * is freed.
     *
     * @param string $server index of queue server to update
     * @param bool $send_robots whether to send robots.txt data if present
     */
    public function updateScheduler($server, $send_robots = false)
    {
        $queue_server = $this->queue_servers[$server];
        L\crawlLog("Updating machine: " . $queue_server);
        $prefix = $this->fetcher_num . "-" . $this->channel . "-";
        if (count($this->to_crawl) <= 0) {
            $schedule_time = $this->schedule_time;
        }
        /*
            In what follows as we generate post data we delete stuff
            from $this->found_sites, to try to minimize our memory
            footprint.
         */
        $byte_counts = ["TOTAL" => 0, "ROBOT" => 0, "SCHEDULE" => 0,
            "INDEX" => 0, "CACHE_PAGE_VALIDATION" => 0];
        $post_data = ['c'=>'fetch', 'a'=>'update',
            'crawl_time' => $this->crawl_time, 'machine_uri' => C\WEB_URI,
            'robot_instance' => $prefix . C\ROBOT_INSTANCE, 'data' => '',
            'check_crawl_time' => $this->check_crawl_time,
            'crawl_type' => $this->crawl_type];
        //handle robots.txt data
        if (isset($this->found_sites[self::ROBOT_TXT]) && $send_robots) {
            $data = L\webencode(
                gzcompress(serialize($this->found_sites[self::ROBOT_TXT])));
            unset($this->found_sites[self::ROBOT_TXT]);
            $bytes_robot = strlen($data);
            $post_data['data'] .= $data;
            L\crawlLog("..." . $bytes_robot . " bytes of robot data");
            $byte_counts["TOTAL"] += $bytes_robot;
            $byte_counts["ROBOT"] = $bytes_robot;
        }
        //handle cache validation data
        if (isset($this->found_sites[self::CACHE_PAGE_VALIDATION_DATA])) {
            $cache_page_validation_data = L\webencode(
                gzcompress(serialize(
                    $this->found_sites[self::CACHE_PAGE_VALIDATION_DATA])));
            unset($this->found_sites[self::CACHE_PAGE_VALIDATION_DATA]);
            $bytes_cache_page_validation = strlen($cache_page_validation_data);
            $post_data['data'] .= $cache_page_validation_data;
            L\crawlLog("..." . $bytes_cache_page_validation .
                " bytes of cache page validation data");
            $byte_counts["TOTAL"] += $bytes_cache_page_validation;
            $byte_counts["CACHE_PAGE_VALIDATION"] =
                $bytes_cache_page_validation;
        }
        //handle schedule data
        $schedule_data = [];
        if (isset($this->found_sites[self::TO_CRAWL][$server])) {
            $schedule_data[self::TO_CRAWL] = &
                $this->found_sites[self::TO_CRAWL][$server];
        }
        unset($this->found_sites[self::TO_CRAWL][$server]);
        $seen_cnt = 0;
        if (isset($this->found_sites[self::SEEN_URLS]) &&
            ($seen_cnt = count($this->found_sites[self::SEEN_URLS])) > 0 ) {
            $hash_seen_urls = [];
            foreach ($this->found_sites[self::SEEN_URLS] as $site) {
                $hash_seen_urls[] =
                    L\crawlHash($site[self::URL], true);
            }
            $schedule_data[self::HASH_SEEN_URLS] = $hash_seen_urls;
            unset($hash_seen_urls);
        }
        if (!empty($schedule_data)) {
            if (isset($schedule_time)) {
                $schedule_data[self::SCHEDULE_TIME] = $schedule_time;
            }
            $data = L\webencode(gzcompress(serialize($schedule_data)));
            $post_data['data'] .= $data;
            $bytes_schedule = strlen($data);
            L\crawlLog("..." . $bytes_schedule . " bytes of schedule data");
            $byte_counts["TOTAL"] += $bytes_schedule;
            $byte_counts["SCHEDULE"] = $bytes_schedule;
            $byte_counts["SCHEDULE_TIME"] = $this->schedule_time;
        }
        unset($schedule_data);
        if ($seen_cnt > 0 || !empty(
            $this->found_sites[self::LINK_SEEN_URLS][$server])) {
            $compress_urls = $this->compressAndUnsetSeenUrls($server);
            $len_urls =  strlen($compress_urls);
            L\crawlLog("...Finish Compressing seen URLs.");
            $out_string = L\packInt($len_urls) . $compress_urls;
            unset($compress_urls);
            L\garbageCollect();
            $data = L\webencode($out_string);
            L\crawlLog(".....web encode result.");
                // don't compress index data
            $post_data['data'] .= $data;
            unset($out_string);
            $bytes_index = strlen($data);
            L\crawlLog("..." . $bytes_index . " bytes of index data");
            $byte_counts["TOTAL"] += $bytes_index;
            $byte_counts["INDEX"] = $bytes_index;
        }
        if ($byte_counts["TOTAL"] <= 0) {
            L\crawlLog("No data to send aborting update scheduler...");
            return;
        }
        L\crawlLog("...");
        //try to send to queue server
        $this->uploadCrawlData($queue_server, $byte_counts, $post_data);
        unset($post_data);
        L\crawlLog("...  Current Memory:" . memory_get_usage());
        if ($this->crawl_type == self::WEB_CRAWL) {
            $dir = C\CRAWL_DIR . "/schedules";
            file_put_contents("$dir/$prefix" . self::fetch_batch_name .
                "{$this->crawl_time}.txt", serialize($this->to_crawl));
            $this->db->setWorldPermissionsRecursive("$dir/$prefix" .
                self::fetch_batch_name . "{$this->crawl_time}.txt");
        }
    }
    /**
     * Computes a string of compressed urls from the seen urls and extracted
     * links destined for the current queue server. Then unsets these
     * values from $this->found_sites
     *
     * @param int $server index of queue server to compress and unset urls for
     * @return string of compressed urls
     */
    public function compressAndUnsetSeenUrls($server)
    {
        $num_queue_servers = count($this->queue_servers);
        $compress_urls = "";
        if (!isset($this->found_sites[self::LINK_SEEN_URLS][$server])) {
            $this->found_sites[self::LINK_SEEN_URLS][$server] = [];
        }
        if (isset($this->found_sites[self::SEEN_URLS]) &&
            is_array($this->found_sites[self::SEEN_URLS])) {
            $this->found_sites[self::SEEN_URLS] =
                array_merge($this->found_sites[self::SEEN_URLS],
                $this->found_sites[self::LINK_SEEN_URLS][$server]);
        } else {
            $this->found_sites[self::SEEN_URLS] =
                $this->found_sites[self::LINK_SEEN_URLS][$server];
        }
        $this->found_sites[self::LINK_SEEN_URLS][$server] = [];
        if (isset($this->found_sites[self::SEEN_URLS])) {
            $num_seen = count($this->found_sites[self::SEEN_URLS]);
            $i = 0;
            while($this->found_sites[self::SEEN_URLS] != []) {
                L\crawlTimeoutLog("..compressing seen url %s of %s",
                    $i, $num_seen);
                $i++;
                $site = array_shift($this->found_sites[self::SEEN_URLS]);
                if (empty($site[self::LANG])) {
                    $lang = L\guessLocaleFromString($site[self::DESCRIPTION]);
                } else {
                    $lang = $site[self::LANG];
                }
                $not_link = (empty($site[self::TYPE]) ||
                    $site[self::TYPE] != 'link');
                if ($not_link) {
                    $phrase_string = ($site[self::TITLE] ?? "") . " " .
                        ($site[self::DESCRIPTION] ?? "");
                    $word_and_qa_lists =
                        PhraseParser::extractPhrasesInLists($phrase_string,
                        $lang);
                }
                if (isset($word_and_qa_lists['QUESTION_ANSWER_LIST'])) {
                    $site[self::QUESTION_ANSWERS] =
                        $word_and_qa_lists['QUESTION_ANSWER_LIST'];
                }
                if ($this->crawl_type == self::ARCHIVE_CRAWL &&
                    isset($this->archive_iterator)) {
                    $site[self::DOC_RANK] =
                        $this->archive_iterator->weight($site);
                }
                if (!isset($site[self::ROBOT_METAS]) ||
                 !in_array("JUSTFOLLOW", $site[self::ROBOT_METAS]) ||
                 isset($site[self::JUST_METAS])) {
                    $site_string = gzcompress(serialize($site));
                    $compress_urls .= L\packInt(strlen($site_string)) .
                        $site_string;
                } else {
                    L\crawlLog("..filtering " . $site[self::URL] .
                        " because of JUSTFOLLOW.");
                    continue;
                }
                $site_url = $site[self::URL];
                $host = UrlParser::getHost($site_url);
                // extract link seen urls for this if not a link
                if (!$this->no_process_links && (empty($site[self::TYPE]) ||
                    $site[self::TYPE] != 'link') &&
                    !isset($site[self::JUST_METAS]) &&
                    !isset($this->programming_language_extension[$lang])) {
                    $tokenizer = PhraseParser::getTokenizer($lang);
                    $has_stopwords_remover = !empty($tokenizer) &&
                        method_exists($tokenizer, "stopwordsRemover");
                    $is_feed_link = (!empty($site[self::IS_FEED])) ?
                        true : false;
                    foreach ($site[self::LINKS] as $url => $url_info) {
                        /* this mysterious check means won't index links from
                          robots.txt. Sitemap will still be in TO_CRAWL,
                          but that's  done elsewhere
                         */
                        L\crawlTimeoutLog("..storing link info for seen url".
                            " %s of %s", $i, $num_seen);
                        if (strlen($url) == 0 || is_numeric($url)) {
                            continue;
                        }
                        $link_host = UrlParser::getHost($url);
                        if (strlen($link_host) == 0) {
                            continue;
                        }
                        if (is_array($url_info)) {
                            $link_title = (empty($url_info[self::TITLE])) ?
                                $url : $url_info[self::TITLE];
                            $link_text = (empty($url_info[self::DESCRIPTION])) ?
                                UrlParser::extractTextFromUrl($url) :
                                $url_info[self::DESCRIPTION];
                        } else {
                            $link_text = $url_info;
                            $link_title = $url;
                            $url_info = [
                                self::TITLE => $link_title,
                                self::DESCRIPTION => $link_text,
                            ];
                        }
                        $part_num = L\calculatePartition($link_host,
                            $num_queue_servers);
                        $link_type = UrlParser::getDocumentType($url);
                        $is_image_link = (in_array($link_type,
                            PageProcessor::$image_types));
                        $summary = [];
                        $this->found_sites[self::LINK_SEEN_URLS][$part_num] ??=
                            [];
                        $elink_flag = ($link_host != $host) ? true : false;
                        $link_text = strip_tags($link_text);
                        if ($has_stopwords_remover) {
                            $useful_text = $tokenizer->stopwordsRemover(
                                $link_text);
                        } else {
                            $useful_text = $link_text;
                        }
                        $useful_text = preg_replace("/\.\.|\s/u", "",
                            $useful_text);
                        if (mb_strlen($useful_text) < C\MIN_LINKS_TEXT_CHARS) {
                            continue;
                        }
                        $ref = ($elink_flag) ? "eref" : "iref";
                        $url = str_replace('|', "%7C", $url);
                        $link_id = "url|" . $url . "|text|" .
                            urlencode($link_text) . "|$ref|" . $site_url;
                        $elink_flag_string = ($elink_flag) ? "e" : "i";
                        $link_keys = L\crawlHash($url, true) .
                            L\crawlHash($link_id, true) .
                            $elink_flag_string .
                            substr(L\crawlHash($host . "/", true), 1);
                        $summary[self::URL] =  $link_id;
                        $summary[self::TITLE] = $link_title;
                            // stripping html to be on the safe side
                        $summary[self::DESCRIPTION] =  $link_text;
                        $summary[self::TIMESTAMP] =  $site[self::TIMESTAMP];
                        $summary[self::ENCODING] =
                            (empty($site[self::ENCODING])) ?
                            'UTF-8' : $site[self::ENCODING];
                        $summary[self::HASH] =  $link_id;
                        $summary[self::TYPE] = "link";
                        $summary[self::HTTP_CODE] = $link_keys;
                        $summary[self::LANG] = $lang;
                        $this->found_sites[self::LINK_SEEN_URLS][$part_num][] =
                            $summary;
                    }
                }
            }
            unset($this->found_sites[self::SEEN_URLS]);
            $this->num_seen_sites += $num_seen;
        }
        return $compress_urls;
    }
    /**
     * Sends to crawl, robot, and index data to the current queue server.
     * If this data is more than post_max_size, it splits it into chunks
     * which are then reassembled by the queue server web app before being
     * put into the appropriate schedule sub-directory.
     *
     * @param string $queue_server url of the current queue server
     * @param array $byte_counts has four fields: TOTAL, ROBOT, SCHEDULE,
     *     INDEX. These give the number of bytes overall for the
     *     'data' field of $post_data and for each of these components.
     * @param array $post_data data to be uploaded to the queue server web app
     */
    public function uploadCrawlData($queue_server, $byte_counts, &$post_data)
    {
        static $total_upload_bytes = 0;
        static $total_uploads_previous_hour = 0;
        static $total_uploads_two_hours = 0;
        static $last_record_time = 0;
        static $oldest_record_time = 0;
        $post_data['fetcher_peak_memory'] = memory_get_peak_usage();
        $post_data['byte_counts'] = L\webencode(serialize($byte_counts));
        $len = strlen($post_data['data']);
        $max_len = $this->post_max_size - 10 * 1024; // non-data post vars < 10K
        $post_data['num_parts'] = ceil($len/$max_len);
        $num_parts = $post_data['num_parts'];
        $data = & $post_data['data'];
        unset($post_data['data']);
        $post_data['hash_data'] = L\crawlHash($data);
        $offset = 0;
        for ($i = 1; $i <= $num_parts; $i++) {
            $time = time();
            $session = md5($time . C\AUTH_KEY);
            $post_data['time'] = $time;
            $post_data['session'] = $session;
            $post_data['part'] = substr($data, $offset, $max_len);
            $post_data['hash_part'] = L\crawlHash($post_data['part']);
            $post_data['current_part'] = $i;
            $offset += $max_len;
            $part_len = strlen($post_data['part']);
            L\crawlLog("Sending Queue Server Part $i of $num_parts...");
            L\crawlLog("...sending about $part_len bytes.");
            $sleep = false;
            do {
                if ($sleep == true) {
                    L\crawlLog("Trouble sending to the scheduler at url:");
                    L\crawlLog($queue_server);
                    if (empty($info_string)) {
                        L\crawlLog("Response from server was empty");
                        L\crawlLog("Here is what was posted:");
                        L\crawlLog(print_r($post_data, true));
                    } else {
                        L\crawlLog("Response was:");
                        L\crawlLog("$info_string");
                    }
                    $info = @unserialize($info_string);
                    $time = time();
                    $session = md5($time . C\AUTH_KEY);
                    $post_data['time'] = $time;
                    $post_data['session'] = $session;
                    if (isset($info[self::STATUS]) &&
                        $info[self::STATUS] == self::REDO_STATE) {
                        L\crawlLog(
                            "Server requested last item to be re-sent...");
                        if (isset($info[self::SUMMARY])) {
                            L\crawlLog($info[self::SUMMARY]);
                        }
                        L\crawlLog("Trying again in " . C\FETCH_SLEEP_TIME
                            . " seconds...");
                    } else {
                        L\crawlLog("Trying again in " . C\FETCH_SLEEP_TIME
                            ." seconds. You might want");
                        L\crawlLog("to check the queue server url and server");
                        L\crawlLog("key. Queue Server post_max_size is:".
                            $this->post_max_size);
                    }
                    if ($i == 1 && !C\nsdefined('FORCE_SMALL') &&
                        $this->post_max_size > 1000000) {
                        /* maybe server has limited memory
                           and two high a post_max_size
                         */
                        L\crawlLog("Using smaller post size to see if helps");
                        C\nsdefine('FORCE_SMALL', true);
                        $this->post_max_size = 1000000;
                        $info[self::POST_MAX_SIZE] = 1000001;
                        /* set to small value before try again.
                         */
                    }
                    sleep(C\FETCH_SLEEP_TIME);
                }
                $sleep = true;
                $info_string = FetchUrl::getPage($queue_server, $post_data,
                    true);
                $info = unserialize(trim($info_string));
                if (isset($info[self::LOGGING])) {
                    L\crawlLog("Messages from Fetch Controller:");
                    L\crawlLog($info[self::LOGGING]);
                }
                if (isset($info[self::POST_MAX_SIZE]) &&
                    $this->post_max_size > $info[self::POST_MAX_SIZE]) {
                    if (!C\nsdefined('FORCE_SMALL')) {
                        L\crawlLog("post_max_size has changed was ".
                            "{$this->post_max_size}. Now is ".
                            $info[self::POST_MAX_SIZE].".");
                        $this->post_max_size = $info[self::POST_MAX_SIZE];
                    } else {
                        L\crawlLog(
                            "...Using Force Small Rule on Server Posting");
                    }
                    if ($max_len > $this->post_max_size) {
                        L\crawlLog("Restarting upload...");
                        if (isset($post_data["resized_once"])) {
                            L\crawlLog("Restart failed");
                            return;
                        }
                        $post_data['data'] = $data;
                        $post_data["resized_once"] = true;
                        return $this->uploadCrawlData(
                            $queue_server, $byte_counts, $post_data);
                    }
                }
            } while(!isset($info[self::STATUS]) ||
                $info[self::STATUS] != self::CONTINUE_STATE);
            if (!empty($info['REPEAT_TIME']) && !empty($this->schedule_time)
                && $info['REPEAT_TIME'] > $this->schedule_time) {
                /*  we are crawling using a double index archive and it has
                    swapped.
                */
                L\crawlLog("Queue Server Double Index Archive Swapped...");
                L\crawlLog("...deleting rest of to crawl batch");
                $this->to_crawl = [];
                $this->to_crawl_again = [];
                $this->found_sites = [];
            }
            L\crawlLog("Queue Server info response code: ".$info[self::STATUS]);
            L\crawlLog("Queue Server's crawl time is: " .
                $info[self::CRAWL_TIME]);
            L\crawlLog("Web Server peak memory usage: ".
                $info[self::MEMORY_USAGE]);
            L\crawlLog("This fetcher peak memory usage: ".
                memory_get_peak_usage());
        }
        L\crawlLog(
            "Updated Queue Server, sent approximately" .
            " {$byte_counts['TOTAL']} bytes:");
        $total_upload_bytes += $byte_counts['TOTAL'];
        $record_time = time();
        $diff_hours = ($record_time - $oldest_record_time)/C\ONE_HOUR;
        $diff_hours = ($diff_hours <= 0) ? 1 : $diff_hours;
        $uploads_per_hour =
            ($total_upload_bytes - $total_uploads_two_hours) /
            $diff_hours;
        L\crawlLog("...  Upload bytes per hours: " . $uploads_per_hour);
        if ($record_time > $last_record_time + C\ONE_HOUR) {
            $total_uploads_two_hours = $total_uploads_previous_hour;
            $total_uploads_previous_hour = $total_upload_bytes;
            $oldest_record_time = $last_record_time;
            if ($oldest_record_time == 0) {
                $oldest_record_time = $record_time;
            }
            $last_record_time = $record_time;
        }
    }
}
/*
 * Instantiate and runs the Fetcher
 */
$fetcher =  new Fetcher();
$fetcher->start();
