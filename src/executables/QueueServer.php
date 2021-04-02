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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\IndexArchiveBundle;
use seekquarry\yioop\library\Join;
use seekquarry\yioop\library\processors\PageProcessor;
use seekquarry\yioop\library\DoubleIndexBundle;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\WebQueueBundle;

if (!defined("seekquarry\\yioop\\configs\\UNIT_TEST_MODE")) {
    if (php_sapi_name() != 'cli' ||
        defined("seekquarry\\yioop\\configs\\IS_OWN_WEB_SERVER")) {
        echo "BAD REQUEST"; exit();
    }
}
/**  For crawlHash function and Yioop constants */
require_once __DIR__ . "/../library/Utility.php";
if (!C\PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}
ini_set("memory_limit", C\QUEUE_SERVER_MEMORY_LIMIT);
/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/**
 * Command line program responsible for managing Yioop crawls.
 *
 * It maintains a queue of urls that are going to be scheduled to be seen.
 * It also keeps track of what has been seen and robots.txt info.
 * Its last responsibility is to create and populate the IndexArchiveBundle
 * that is used by the search front end.
 *
 * @author Chris Pollett
 */
class QueueServer implements CrawlConstants, Join
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    public $db;
    /**
     * Web-sites that crawler can crawl. If used, ONLY these will be crawled
     * @var array
     */
    public $allowed_sites;
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
     * Timestamp of lst time download from site quotas were cleared
     * @var int
     */
    public $quota_clear_time;
    /**
     * Web-sites that have an hourly crawl quota
     * @var array
     */
    public $quota_sites;
    /**
     * Cache of array_keys of $quota_sites
     * @var array
     */
    public $quota_sites_keys;
    /**
     * Channel that queue server listens to messages for
     * @var int
     */
    public $channel;
    /**
     * Controls whether a repeating crawl (negative man no) is being done
     * and if so its frequency in second
     * @var int
     */
    public $repeat_type;
    /**
     * If a crawl quiescent period is being used with the crawl, then
     * this stores the time of day at which that period starts
     * @var string
     */
    public $sleep_start;
    /**
     * If a crawl quiescent period is being used with the crawl, then
     * this sproperty will be positive and indicate the number of seconds
     * duration for the quiescent period.
     * @var string
     */
    public $sleep_duration;
    /**
     * Constant saying the method used to order the priority queue for the crawl
     * @var string
     */
    public $crawl_order;
    /**
     * Constant saying the depth from the seeds crawl can go to
     * @var string
     */
    public $max_depth;
    /**
     * One of a fixed set of values which are used to control to what extent
     * Yioop follows robots.txt files: ALWAYS_FOLLOW_ROBOTS,
     * ALLOW_LANDING_ROBOTS, IGNORE_ROBOTS
     * @var int
     */
    public $robot_txt;
    /**
     * Stores the name of the summarizer used for crawling.
     * Possible values are Basic and Centroid
     * @var string
     */
    public $summarizer_option;
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
     * Number of days between resets of the page url filter
     * If nonpositive, then never reset filter
     * @var int
     */
    public $page_recrawl_frequency;
    /**
     * Indicates the kind of crawl being performed: self::WEB_CRAWL indicates
     * a new crawl of the web; self::ARCHIVE_CRAWL indicates a crawl of an
     * existing web archive
     * @var string
     */
    public $crawl_type;
    /**
     * If the crawl_type is self::ARCHIVE_CRAWL, then crawl_index is the
     * timestamp of the existing archive to crawl
     * @var string
     */
    public $crawl_index;
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
     * Used in schedules to tell the fetcher whether or not to cache pages
     * @var bool
     */
    public $cache_pages;
    /**
     * Used to add page rules to be applied to downloaded pages to schedules
     * that the fetcher will use (and hence apply the page )
     * @var array
     */
    public $page_rules;
    /**
     * Holds the WebQueueBundle for the crawl. This bundle encapsulates
     * the priority queue of urls that specifies what to crawl next
     * @var object
     */
    public $web_queue;
    /**
     * Holds the IndexArchiveBundle for the current crawl. This encapsulates
     * the inverted index word-->documents for the crawls as well as document
     * summaries of each document.
     * @var object
     */
    public $index_archive;
    /**
     * The timestamp of the current active crawl
     * @var int
     */
    public $crawl_time;
    /**
     * This is a list of hosts whose robots.txt file had a Crawl-delay directive
     * and which we have produced a schedule with urls for, but we have not
     * heard back from the fetcher who was processing those urls. Hosts on
     * this list will not be scheduled for more downloads until the fetcher
     * with earlier urls has gotten back to the queue server.
     * @var array
     */
    public $waiting_hosts;
    /**
     * IP address as a string of the fetcher that most recently spoke with the
     * queue server.
     * @var string
     */
    public $most_recent_fetcher;
    /**
     * Last time index was saved to disk
     * @var int
     */
    public $last_index_save_time;
    /**
     * flags for whether the index has data to be written to disk
     * @var int
     */
     public $index_dirty;
    /**
     * This keeps track of the time the current archive info was last modified
     * This way the queue server knows if the user has changed the crawl
     * parameters during the crawl.
     * @var int
     */
    public $archive_modified_time;

    /**
     * This is a list of indexing_plugins which might do
     * post processing after the crawl. The plugins postProcessing function
     * is called if it is selected in the crawl options page.
     * @var array
     */
    public $indexing_plugins;

    /**
     * This is a array of crawl parameters for indexing_plugins which might do
     * post processing after the crawl.
     * @var array
     */
    public $indexing_plugins_data;
    /**
     * This is a list of hourly (timestamp, number_of_urls_crawled) statistics
     * @var array
     */
    public $hourly_crawl_data;
    /**
     * Used to say what kind of queue server this is (one of BOTH, INDEXER,
     * SCHEDULER)
     * @var mixed
     */
    public $server_type;
    /**
     * String used to describe this kind of queue server (Indexer, Scheduler,
     * etc. in the log files.
     * @var string
     */
    public $server_name;
    /**
     * String used for naming log files and for naming the processes which run
     * related to the queue server
     * @var string
     */
    public $process_name;
    /**
     * Holds the value of a debug message that might have been sent from
     * the command line during the current execution of loop();
     * @var string
     */
    public $debug;
    /**
     * A mapping between class field names and parameters which might
     * be sent to a queue server via an info associative array.
     * @var array
     */
    public static $info_parameter_map = [
        "crawl_order" => self::CRAWL_ORDER,
        "crawl_type" => self::CRAWL_TYPE,
        "crawl_index" => self::CRAWL_INDEX,
        "cache_pages" => self::CACHE_PAGES,
        "page_range_request" => self::PAGE_RANGE_REQUEST,
        "max_depth" => self::MAX_DEPTH,
        "repeat_type" => self::REPEAT_TYPE,
        "sleep_start" => self::SLEEP_START,
        "sleep_duration" => self::SLEEP_DURATION,
        "robots_txt" => self::ROBOTS_TXT,
        "max_description_len" => self::MAX_DESCRIPTION_LEN,
        "max_links_to_extract" => self::MAX_LINKS_TO_EXTRACT,
        "page_recrawl_frequency" => self::PAGE_RECRAWL_FREQUENCY,
        "indexed_file_types" => self::INDEXED_FILE_TYPES,
        "restrict_sites_by_url" => self::RESTRICT_SITES_BY_URL,
        "allowed_sites" => self::ALLOWED_SITES,
        "disallowed_sites" => self::DISALLOWED_SITES,
        "page_rules" => self::PAGE_RULES,
        "indexing_plugins" => self::INDEXING_PLUGINS,
        "indexing_plugins_data" => self::INDEXING_PLUGINS_DATA,
    ];
    /**
     * Creates a Queue Server Daemon
     */
    public function __construct()
    {
        PageProcessor::initializeIndexedFileTypes();
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS) . "Manager";
        $this->db = new $db_class();
        $this->indexed_file_types = PageProcessor::$indexed_file_types;
        $this->all_file_types = PageProcessor::$indexed_file_types;
        $this->most_recent_fetcher = "No Fetcher has spoken with me";
        //the next values will be set for real in startCrawl
        $this->crawl_order = self::PAGE_IMPORTANCE;
        $this->summarizer_option = self::CENTROID_SUMMARIZER;
        $this->restrict_sites_by_url = true;
        $this->allowed_sites = [];
        $this->disallowed_sites = [];
        $this->allow_disallow_cache_time = microtime(true);
        $this->quota_sites = [];
        $this->quota_sites_keys = [];
        $this->quota_clear_time = time();
        $this->page_rules = [];
        $this->last_index_save_time = 0;
        $this->index_dirty = false;
        $this->hourly_crawl_data = [];
        $this->archive_modified_time = 0;
        $this->crawl_time = 0;
        $this->channel = 0;
        $this->repeat_type = -1;
        $this->sleep_start = "00:00";
        $this->sleep_duration = "-1";
        $this->robots_txt = C\ALWAYS_FOLLOW_ROBOTS;
        $this->cache_pages = true;
        $this->page_recrawl_frequency = C\PAGE_RECRAWL_FREQUENCY;
        $this->page_range_request = C\PAGE_RANGE_REQUEST;
        $this->max_description_len = C\MAX_DESCRIPTION_LEN;
        $this->max_links_to_extract = C\MAX_LINKS_TO_EXTRACT;
        $this->server_type = self::BOTH;
        $this->indexing_plugins = [];
        $this->indexing_plugins_data = [];
        $this->waiting_hosts = [];
        $this->server_name = "IndexerAndScheduler";
        $this->process_name = "0-QueueServer";
        $this->debug = "";
    }
    /**
     * This is the function that should be called to get the queue server
     * to start. Calls init to handle the command line arguments then enters
     * the queue server's main loop
     */
    public function start()
    {
        global $argv;
        if (isset($argv[1]) && $argv[1] == "start") {
            if (isset($argv[2]) && in_array($argv[2],
                [self::INDEXER, self::SCHEDULER]) ) {
                $this->channel = (empty($argv[3])) ? 0 : min(C\MAX_CHANNELS,
                    abs(intval($argv[3])));
                $argv[3] = $argv[2];
                $argv[2] = $this->channel;
                // 3 indicates force start
                CrawlDaemon::init($argv, "QueueServer", 3);
            } else {
                $this->channel = (empty($argv[2])) ? 0 : min(C\MAX_CHANNELS,
                    abs(intval($argv[2])));
                $argv[2] = $this->channel;
                $argv[3] = self::INDEXER;
                CrawlDaemon::init($argv, "QueueServer", 0);
                $argv[3] = self::SCHEDULER;
                CrawlDaemon::init($argv, "QueueServer", 2);
            }
        } else {
            $this->channel = (empty($argv[2])) ? 0 : min(C\MAX_CHANNELS,
                abs(intval($argv[2])));
            CrawlDaemon::init($argv, "QueueServer", "{$this->channel}");
        }
        $this->process_name = $this->channel . "-QueueServer";
        $this->crawl_status_file_name =
            C\CRAWL_DIR . "/schedules/{$this->channel}-crawl_status.txt";
        L\crawlLog("\n\nInitialize logger..", $this->process_name, true);
        $this->server_name = "IndexerAndScheduler";
        if (isset($argv[3]) && $argv[1] == "child" &&
            in_array($argv[3], [self::INDEXER, self::SCHEDULER])) {
            $this->server_type = $argv[3];
            $this->server_name = $argv[3];
            L\crawlLog($this->server_name . " logging started.");
        }
        L\crawlLog($this->server_name . " using channel " . $this->channel);
        $remove = false;
        $old_message_names = ["QueueServerMessages.txt",
            "SchedulerMessages.txt", "crawl_status.txt",
            "schedule_status.txt"];
        foreach ($old_message_names as $name) {
            if (file_exists(C\CRAWL_DIR."/schedules/{$this->channel}-$name")) {
                @unlink(C\CRAWL_DIR . "/schedules/{$this->channel}-$name");
                $remove = true;
            }
            if (file_exists(C\CRAWL_DIR."/schedules/$name")) {
                @unlink(C\CRAWL_DIR . "/schedules/$name");
                $remove = true;
            }
        }
        if ($remove == true) {
            L\crawlLog("Remove old messages..", $this->process_name);
        }
        $this->loop();
    }
    /**
     * Main runtime loop of the queue server.
     *
     * Loops until a stop message received, check for start, stop, resume
     * crawl messages, deletes any WebQueueBundle for which an
     * IndexArchiveBundle does not exist. Processes
     */
    public function loop()
    {
        static $total_idle_time = 0;
        static $total_idle_previous_hour = 0;
        static $total_idle_two_hours = 0;
        static $oldest_record_time = 0;
        static $last_record_time = 0;
        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
        L\crawlLog("In queue loop!! {$this->server_name}", $this->process_name);
        if ($this->isAIndexer()) {
            $this->deleteOrphanedBundles();
        }
        L\crawlLog("PHP Version in use: " . phpversion());
        while (CrawlDaemon::processHandler()) {
            if ($this->isOnlyIndexer()) {
                $_SERVER["NO_ROTATE_LOGS"] = ((floor(time() / 30) % 2) == 0) ?
                    true : false;
            }
            if ($this->isOnlyScheduler()) {
                $_SERVER["NO_ROTATE_LOGS"] = ((floor(time() / 30) % 2) == 0) ?
                    false : true;
            }
            L\crawlLog("{$this->server_name} peak memory usage so far: ".
                memory_get_peak_usage()."!!");
            $record_time = time();
            $diff_hours = ($record_time - $oldest_record_time)/C\ONE_HOUR;
            $diff_hours = ($diff_hours <= 0) ? 1 : $diff_hours;
            $idle_per_hour =
                ($total_idle_time - $total_idle_two_hours) /
                $diff_hours;
            L\crawlLog("{$this->server_name} percent idle time per hour: " .
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
            $info = $this->handleAdminMessages($info);
            $this->checkBothProcessesRunning($info);
            if (!empty($info[self::STATUS])) {
                if ($info[self::STATUS] == self::WAITING_START_MESSAGE_STATE) {
                    L\crawlLog("{$this->server_name} is waiting".
                        " for start message\n");
                    sleep(C\QUEUE_SLEEP_TIME);
                    $total_idle_time += C\QUEUE_SLEEP_TIME/C\ONE_HOUR;
                    continue;
                }
                if ($info[self::STATUS] == self::STOP_STATE) {
                    continue;
                }
            }
            L\crawlLog("{$this->server_name} active crawl is ".
                "{$this->crawl_time}.");
            if ($this->isAScheduler() && $this->crawl_type == self::WEB_CRAWL) {
                L\crawlLog("Current queue size is:".
                    $this->web_queue->to_crawl_queue->count);
                if (($wake_up =
                    L\checkTimeInterval($this->sleep_start,
                    $this->sleep_duration)) > 0) {
                    L\crawlLog("SM: CRAWL IN QUIESCENT/SLEEP MODE!!");
                    L\crawlLog("SM: Will continue processing crawl data, but");
                    L\crawlLog("SM: fetchers will stop downloading.");
                    L\crawlLog("SM: Touching crawl_status file to keep crawl ".
                        "from timing our due to fetcher inactivity.");
                    if (file_exists($this->crawl_status_file_name)) {
                        touch($this->crawl_status_file_name, time());
                    }
                    L\crawlLog("SM: Current time is: " . date('r'));
                    L\crawlLog("SM: Will wake up at " . date('r', $wake_up));
                }
            }
            $start_loop_time = time();
            //check and update if necessary the crawl params of current crawl
            $this->checkUpdateCrawlParameters();
            /* if doing a crawl that repeats, check to see if we should swap
              between active and last crawl
             */
            $this->checkRepeatingCrawlSwap();
            $this->updateMostRecentFetcher();
            $this->processCrawlData();
            $time_diff = time() - $start_loop_time;
            if ($time_diff < C\QUEUE_SLEEP_TIME) {
                L\crawlLog("Sleeping...");
                sleep(C\QUEUE_SLEEP_TIME - $time_diff);
                $total_idle_time += (C\QUEUE_SLEEP_TIME - $time_diff) /
                    C\ONE_HOUR;
            }
        }
        L\crawlLog("{$this->server_name} shutting down!!");
    }
    /**
     * Checks to make sure both the indexer process and the scheduler
     * processes are running and if not restart the stopped process
     * @param array $info information about queue server state used to
     *      determine if a crawl is active.
     */
    public function checkBothProcessesRunning($info)
    {
        /* This function doesn't do anything if this process
            is both an indexex and a scheduler as then the fact
            that we're here means both processes running
         */
        if ($this->isOnlyScheduler()) {
            $this->checkProcessRunning(self::INDEXER, $info);
        } else if ($this->isOnlyIndexer()) {
            $this->checkProcessRunning(self::SCHEDULER, $info);
        }
    }
    /**
     * Checks to make sure the given process (either Indexer or Scheduler) is
     * running, and if not, restart it.
     * @param string $process should be either self::INDEXER or self::SCHEDULER
     * @param array $info information about queue server state used to
     *      determine if a crawl is active.
     */
    public function checkProcessRunning($process, $info)
    {
        static $first_check;
        static $last_check;
        $time = time();
        if ($this->debug != "NOT_RUNNING") {
            if (!isset($first_check)) {
                $first_check = $time;
                $last_check = $time;
            }
            if ($time - $last_check < C\LOG_TIMEOUT ||
                $time - $first_check < C\PROCESS_TIMEOUT ) {
                return;
            }
        }
        $last_check = $time;
        L\crawlLog("Checking if both processes still running ...");
        $lines_to_check = C\LOG_LINES_TO_RESTART;
            //about 20-30 minutes of log data
        $lines = L\tail(C\LOG_DIR . "/" . $this->process_name . ".log",
            $lines_to_check);
        $time = time(); // just in case took time to compute lines
        L\crawlLog("...Got " . $this->process_name . ".log lines");
        if ($this->debug != "NOT_RUNNING" && count($lines) < $lines_to_check) {
            L\crawlLog("...Too few log lines to check if both processes " .
                "running.  Assume still running.");
            return;
        }
        $filters = ($process == self::INDEXER) ? ["Indexer", "merg",
            "index shard"] : ["Scheduler"];
        $process_lines = L\lineFilter($lines, $filters);
        L\crawlLog("...Filtered " . $this->process_name . ".log lines");
        $num_lines = count($process_lines);
        $last_process_timestamp = $time;
        // err on the side of caution in assuming process dead
        if (isset($process_lines[$num_lines - 1])) {
            $timestamp =
                L\logLineTimestamp($process_lines[$num_lines - 1]);
            L\crawlLog("...Timestamp of last processed line: ".
                $timestamp);
            if (is_numeric($timestamp) && $time > $timestamp) {
                /*
                   Note if 0 then we have seen LOG_LINES_TO_RESTART lines
                   with no message from other process
                 */
                $last_process_timestamp = $timestamp;
                L\crawlLog("...seems to be a valid timestep, so using.");
            } else { //hopefully doesn't occur, maybe log file garbled
                L\crawlLog("...invalid timestep, so using current time.");
            }
        }
        L\crawlLog("...difference last timestamp and current time ".
            ($time - $last_process_timestamp));
        if ($this->debug != "NOT_RUNNING" &&
            $time - $last_process_timestamp < C\PROCESS_TIMEOUT ) {
            L\crawlLog("...done check. Both processes still running.");
            return;
        }
        $this->debug = "";
        L\crawlLog( "$process seems to have died restarting...");
        $process_lines = array_slice($process_lines, -10);
        $time_string = date("r", time());
        $out_msg = "[$time_string] $process died. Last log lines were:\n";
        foreach ($process_lines as $line) {
            $out_msg .= "!!!!$line\n";
        }
        if (empty($process_lines)) {
            $out_msg .= "!!!!No messages seen this log file!\n";
            $out_msg .= "!!!!$process must have died before: " .
                L\logLineTimestamp($lines[0]);
        }
        $error_log = C\CRASH_LOG_NAME;
        L\crawlLog( "!!!!Writing to $error_log " .
            "crash message about $process...");
        if (!file_exists($error_log) || filesize($error_log) >
            C\MAX_LOG_FILE_SIZE) {
            file_put_contents($error_log, $out_msg);
        } else {
            file_put_contents($error_log, $out_msg, FILE_APPEND);
        }
        $init_args = ["QueueServer.php", "start", "{$this->channel}", $process];
        L\crawlLog( "!!!!Writing to $error_log ".
            "crash message about $process...");
        CrawlDaemon::init($init_args, "QueueServer", -3);
        if ($info[self::STATUS] != self::WAITING_START_MESSAGE_STATE) {
            L\crawlLog("Sleeping before sending restart message other process");
            sleep(2 * C\QUEUE_SLEEP_TIME);
            $crawl_params = [];
            $crawl_params[self::STATUS] = "RESUME_CRAWL";
            $crawl_params[self::CRAWL_TIME] = $this->crawl_time;
            $crawl_params[self::CRAWL_TYPE] = $this->crawl_type;
            $crawl_params[self::REPEAT_TYPE] = $this->repeat_type;
            $crawl_params[self::SLEEP_START] = $this->sleep_start;
            $crawl_params[self::SLEEP_DURATION] = $this->sleep_duration;
            $crawl_params[self::CHANNEL] = $this->channel;
            $info_string = serialize($crawl_params);
            $process_message_file = C\CRAWL_DIR . "/schedules/" .
                $this->process_name . "Messages.txt";
            file_put_contents($process_message_file, $info_string);
            chmod(C\CRAWL_DIR . "/schedules/" . $this->process_name .
                "Messages.txt", 0777);
        }
    }
    /**
     * Check for a repeating crawl whether it is time to swap between
     * the active and search crawls.
     *
     * @return bool true if the time to swap has come
     */
    public function checkRepeatingCrawlSwap()
    {
        L\crawlLog("Repeating Crawl Check for Swap...");
        if (empty($this->repeat_type) || $this->repeat_type <= 0) {
            L\crawlLog("SW...not a repeating crawl, no swap needed," .
                "continuing crawl.");
            return;
        }
        $crawl_time = $this->crawl_time;
        $start_swap_file = C\CRAWL_DIR . "/schedules/StartSwap" .
            $this->crawl_time . ".txt";
        $finish_swap_file = C\CRAWL_DIR . "/schedules/FinishSwap" .
            $this->crawl_time . ".txt";
        if ($this->isAScheduler() && file_exists($start_swap_file) &&
            !file_exists($finish_swap_file)) {
            L\crawlLog("SW...performing scheduler swap activities.");
            // Delete everything associated with the queue
            $delete_files = [
                C\CRAWL_DIR . "/cache/" . self::network_base_name .
                    "$crawl_time.txt",
                C\CRAWL_DIR . "/cache/" . self::statistics_base_name .
                    "$crawl_time.txt"
            ];
            foreach ($delete_files as $delete_file) {
                if (file_exists($delete_file)) {
                    unlink($delete_file);
                }
            }
            $delete_dirs = [
                C\CRAWL_DIR . '/cache/' . self::queue_base_name . $crawl_time,
                C\CRAWL_DIR . '/schedules/' . self::index_data_base_name .
                    $crawl_time,
                C\CRAWL_DIR . '/schedules/' . self::schedule_data_base_name .
                    $crawl_time,
                C\CRAWL_DIR . '/schedules/' . self::robot_data_base_name .
                    $crawl_time,
                C\CRAWL_DIR . '/schedules/' . self::name_archive_iterator .
                    $crawl_time,
            ];
            foreach ($delete_dirs as $delete_dir) {
                if (file_exists($delete_dir)) {
                    $this->db->unlinkRecursive($delete_dir, true);
                }
            }
            $save_point_files = glob(C\CRAWL_DIR . '/schedules/' .
                self::save_point . $crawl_time . "*.txt");
            foreach ($save_point_files as $save_point_file) {
                @unlink($save_point_file);
            }
            $this->waiting_hosts = [];
            $this->initializeWebQueue();
            $dir_name = C\CRAWL_DIR . '/cache/' . self::double_index_base_name .
                $this->crawl_time;
            DoubleIndexBundle::getStartSchedule($dir_name, $this->channel);
            file_put_contents($finish_swap_file, time());
            unlink($start_swap_file);
            L\crawlLog("SW...done scheduler swap activities!!");
            return;
        }
        if ($this->isAIndexer() && ($this->index_archive->swapTimeReached()
            || $this->debug == 'FORCE_SWAP')) {
            if (!file_exists($start_swap_file) &&
                !file_exists($finish_swap_file)) {
                L\crawlLog("SW...swapping live and search crawl!!");
                L\crawlLog("SW...writing StartSwap file for scheduler !!");
                L\crawlLog("SW...indexer waits for scheduler to do swap");
                file_put_contents($start_swap_file, time());
            }
            if (!file_exists($start_swap_file) &&
                file_exists($finish_swap_file)) {
                L\crawlLog("SW...indexer performing swap activities");
                $this->index_archive->swapActiveBundle();
                unlink($finish_swap_file);
                    L\crawlLog("SW...done indexer swap activities!!");
            }
            if ($this->debug == 'FORCE_SWAP') {
                $this->debug = "";
            }
            return;
        }
        L\crawlLog("...no swap needed continuing current crawl");
    }
    /**
     * Main body of queue server loop where indexing, scheduling,
     * robot file processing is done.
     *
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    public function processCrawlData($blocking = false)
    {
        L\crawlLog("{$this->server_name} Entering Process Crawl Data Method ");
        if ($this->isAIndexer()) {
            /* check for orphaned queue bundles
               also check to make sure indexes closed properly for stopped
               crawls
             */
            $this->deleteOrphanedBundles();
            $this->processIndexData($blocking);
            $info = $this->index_archive->getArchiveInfo(
                $this->index_archive->dir_name);
            $count = $info['COUNT'] ?? 0;
            if (time() - $this->last_index_save_time > C\FORCE_SAVE_TIME
                && $count > 0) {
                L\crawlLog("Periodic Index Save... \n");
                $start_time = microtime(true);
                $this->indexSave();
                L\crawlLog("... Save time" . L\changeInMicrotime($start_time));
            }
        }
        if ($this->isAScheduler()) {
            $info = [];
            $info[self::TYPE] = $this->server_type;
            $info[self::MEMORY_USAGE] = memory_get_peak_usage();
            file_put_contents(C\CRAWL_DIR .
                "/schedules/{$this->channel}-schedule_status.txt",
                serialize($info));
        }
        switch ($this->crawl_type) {
            case self::WEB_CRAWL:
                if ($this->isOnlyIndexer()) {
                    return;
                }
                $this->processRobotUrls();
                if (C\USE_ETAG_EXPIRES) {
                    $this->processEtagExpires();
                }
                $count = $this->web_queue->to_crawl_queue->count;
                $max_links = max(C\MAX_LINKS_TO_EXTRACT,
                    $this->max_links_to_extract);
                if ($count < C\NUM_URLS_QUEUE_RAM -
                    C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER * $max_links) {
                    $this->processQueueUrls();
                }
                if ($count > 0) {
                    $top = $this->web_queue->peekQueue();
                    list($top_weight, $top_depth) =
                        L\decodeQueueWeightInfo($top[1], $this->crawl_order);
                    L\crawlLog("Top (Weight, Depth) in Queue: " .
                        "($top_weight, $top_depth)");
                    if ($this->crawl_order == self::PAGE_IMPORTANCE &&
                        $top_weight < C\MIN_QUEUE_WEIGHT) {
                        L\crawlLog("Normalizing Weights!!\n");
                        $this->web_queue->normalize();
                        /* this will undercount the weights of URLS
                           from fetcher data that have not completed
                         */
                     }
                    /*
                        only produce fetch batch if the last one has already
                        been taken by some fetcher
                     */
                    if (!file_exists(
                        C\CRAWL_DIR . "/schedules/" . self::schedule_name.
                        $this->crawl_time . ".txt")) {
                            $this->produceFetchBatch();
                    }
                }
                break;
            case self::ARCHIVE_CRAWL:
                if ($this->isAIndexer()) {
                    $this->processRecrawlRobotUrls();
                    if (!file_exists(C\CRAWL_DIR . "/schedules/".
                        self::schedule_name . $this->crawl_time . ".txt")) {
                        $this->writeArchiveCrawlInfo();
                    }
                }
                break;
        }
    }
    /**
     * Used to check if the current queue server process is acting a
     * url scheduler for fetchers
     *
     * @return bool whether it is or not
     */
    public function isAScheduler()
    {
        return strcmp($this->server_type, self::SCHEDULER) == 0 ||
            strcmp($this->server_type, self::BOTH) == 0;
    }
    /**
     * Used to check if the current queue server process is acting a
     * indexer of data coming from fetchers
     *
     * @return bool whether it is or not
     */
    public function isAIndexer()
    {
        return strcmp($this->server_type, self::INDEXER) == 0 ||
            strcmp($this->server_type, self::BOTH) == 0;
    }
    /**
     * Used to check if the current queue server process is acting only as a
     * indexer of data coming from fetchers (and not some other activity
     * like scheduler as well)
     *
     * @return bool whether it is or not
     */
    public function isOnlyIndexer()
    {
        return strcmp($this->server_type, self::INDEXER) == 0;
    }
    /**
     * Used to check if the current queue server process is acting only as a
     * indexer of data coming from fetchers (and not some other activity
     * like indexer as well)
     *
     * @return bool whether it is or not
     */
    public function isOnlyScheduler()
    {
        return strcmp($this->server_type, self::SCHEDULER) == 0;
    }
    /**
     * Used to write info about the current recrawl to file as well as to
     * process any recrawl data files received
     */
    public function writeArchiveCrawlInfo()
    {
        $schedule_time = time();
        $first_line = $this->calculateScheduleMetaInfo($schedule_time);
        $fh = fopen(C\CRAWL_DIR."/schedules/".self::schedule_name.
            $this->crawl_time.".txt", "wb");
        fwrite($fh, $first_line);
        fclose($fh);
        $schedule_dir =
            C\CRAWL_DIR."/schedules/".
                self::schedule_data_base_name.$this->crawl_time;
        $this->processDataFile($schedule_dir, "processRecrawlDataArchive");
    }
    /**
     * Even during a recrawl the fetcher may send robot data to the
     * queue server. This function prints a log message and calls another
     * function to delete this useless robot file.
     */
    public function processRecrawlRobotUrls()
    {
        L\crawlLog("Checking for robots.txt files to process...");
        $robot_dir =
            C\CRAWL_DIR."/schedules/".
                self::robot_data_base_name . $this->crawl_time;
        $this->processDataFile($robot_dir, "processRecrawlRobotArchive");
        L\crawlLog("done. ");
    }
    /**
     * Even during a recrawl the fetcher may send robot data to the
     * queue server. This function delete the passed robot file.
     *
     * @param string $file robot file to delete
     */
    public function processRecrawlRobotArchive($file)
    {
        L\crawlLog("Deleting unneeded robot schedule files");
        unlink($file);
    }
    /**
     * Used to get a data archive file (either during a normal crawl or a
     * recrawl). After uncompressing this file (which comes via the web server
     * through fetch_controller, from the fetcher), it sets which fetcher
     * sent it and also returns the sites contained in it.
     *
     * @param string $file name of archive data file
     * @return array sites contained in the file from the fetcher
     */
    public function getDataArchiveFileData($file)
    {
        L\crawlLog("Processing File: $file");
        $decode = file_get_contents($file);
        $decode = L\webdecode($decode);
        set_error_handler(null);
        $decode = @gzuncompress($decode);
        $sites = @unserialize($decode);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        return $sites;
    }
    /**
     * Processes fetcher data file information during a recrawl
     *
     * @param String $file a file which contains the info to process
     */
    public function processRecrawlDataArchive($file)
    {
        $sites = $this->getDataArchiveFileData($file);
        unlink($file);
        $this->writeCrawlStatus($sites);
    }
    /**
     * Handles messages passed via files to the QueueServer.
     *
     * These files are typically written by the CrawlDaemon::init()
     * when QueueServer is run using command-line argument
     *
     * @param array $info associative array with info about current state of
     *     queue server
     * @return array an updates version $info reflecting changes that occurred
     *     during the handling of the admin messages files.
     */
    public function handleAdminMessages($info)
    {
        $old_info = $info;
        $is_scheduler = $this->isOnlyScheduler();
        $message_file = C\CRAWL_DIR . "/schedules/" . $this->process_name .
            "Messages.txt";
        L\crawlLog("Check for queue server messages in " . $message_file .
            "...");
        if (file_exists($message_file)) {
            $info = unserialize(file_get_contents($message_file));
            if (isset($info[self::DEBUG])) {
                $this->debug = $info[self::DEBUG];
                L\crawlLog("The following debug message found: ". $this->debug);
                unlink($message_file);
                return $old_info;
            }
            if (empty($info[$this->server_type])) {
                $info[$this->server_type] = true;
                if ($this->server_type == self::BOTH ||
                  (isset($info[self::INDEXER]) && isset($info[self::SCHEDULER]))
                  ) {
                    unlink($message_file);
                } else {
                    file_put_contents($message_file, serialize($info), LOCK_EX);
                }
            } else {
                return $old_info;
            }
            switch ($info[self::STATUS])
            {
                case "NEW_CRAWL":
                   if ($old_info[self::STATUS] == self::CONTINUE_STATE) {
                        if (!$is_scheduler) {
                        L\crawlLog("Stopping previous crawl before start".
                            " new crawl!");
                        } else {
                        L\crawlLog(
                            "Scheduler stopping for previous crawl before".
                            " new crawl!");
                        }
                        $this->stopCrawl();
                    }
                    $this->startCrawl($info);
                    $info[self::STATUS] == self::CONTINUE_STATE;
                    if (!$is_scheduler) {
                        L\crawlLog("Starting new crawl. Timestamp:" .
                            $this->crawl_time);
                        if ($this->crawl_type == self::WEB_CRAWL) {
                            L\crawlLog("Performing a web crawl!");
                            L\crawlLog("robots.txt behavior is: ");
                            if ($this->robots_txt == C\ALWAYS_FOLLOW_ROBOTS) {
                                L\crawlLog("  Always follow robots.txt");
                            } else if ($this->robots_txt ==
                                C\ALLOW_LANDING_ROBOTS) {
                                L\crawlLog("  Allow landing pages.");
                            } else {
                                L\crawlLog("  Ignore robots.txt.");
                            }
                        } else {
                            L\crawlLog("Performing an archive crawl of " .
                                "archive with timestamp " .
                                $this->crawl_index);
                        }
                        $this->writeAdminMessage("BEGIN_CRAWL");
                    } else {
                        L\crawlLog("Scheduler started for new crawl");
                    }
                break;
                case "STOP_CRAWL":
                    if (!$is_scheduler) {
                        L\crawlLog(
                            "Stopping crawl !! This involves multiple steps!");
                    } else {
                        L\crawlLog("Scheduler received stop message!");
                    }
                    $this->stopCrawl();
                    if (file_exists($this->crawl_status_file_name)) {
                        unlink($this->crawl_status_file_name);
                    }
                    $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                    if (!$is_scheduler) {
                        L\crawlLog("Crawl has been successfully stopped!!");
                    } else {
                        L\crawlLog("Scheduler has been successfully stopped!!");
                    }
                break;
                case "RESUME_CRAWL":
                    if (isset($info[self::CRAWL_TIME]) &&
                        (file_exists(C\CRAWL_DIR . '/cache/'.
                        self::queue_base_name . $info[self::CRAWL_TIME]) ||
                        $info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) ) {
                        if ($old_info[self::STATUS] == self::CONTINUE_STATE) {
                            if (!$is_scheduler) {
                                L\crawlLog("Resuming old crawl...".
                                    " Stopping current crawl first!");
                            } else {
                                L\crawlLog("Sheduler resuming old crawl...".
                                    " Stopping scheduling current first!");
                            }
                            $this->stopCrawl();
                        }
                        $this->startCrawl($info);
                        $info[self::STATUS] == self::CONTINUE_STATE;
                        if (!$is_scheduler) {
                            L\crawlLog("Resuming crawl");
                            $this->writeAdminMessage("RESUME_CRAWL");
                        } else {
                            L\crawlLog("Scheduler Resuming crawl");
                        }
                    } else if (!$is_scheduler) {
                        $msg = "Restart failed!!!  ";
                        if (!isset($info[self::CRAWL_TIME])) {
                            $msg .=
                                "crawl time of crawl to restart not given\n";
                        }
                        if (!file_exists(C\CRAWL_DIR.'/cache/'.
                            self::queue_base_name . $info[self::CRAWL_TIME])) {
                            $msg .= "bundle for crawl restart doesn't exist\n";
                        }
                        $info["MESSAGE"] = $msg;
                        $this->writeAdminMessage($msg);
                        L\crawlLog($msg);
                        $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                    }
                break;
            }
        } else {
            L\crawlLog("... No message file found.");
        }
        return $info;
    }
    /**
     * Used to stop the currently running crawl gracefully so that it can
     * be restarted. This involved writing the queue's contents back to
     * schedules, making the crawl's dictionary all the same tier and running
     * any indexing_plugins.
     */
    public function stopCrawl()
    {
        if ($this->isAScheduler()) {
            $this->dumpQueueToSchedules();
            //Write B-Tree root node to disk before before exiting
            if (C\USE_ETAG_EXPIRES &&
                isset($this->web_queue) && $this->web_queue != null &&
                is_object($this->web_queue->etag_btree)) {
                $this->web_queue->etag_btree->writeRoot();
            }
        }
        if ($this->isAIndexer()) {
            $this->shutdownDictionary();
            //Calling post processing function if the processor is
            //selected in the crawl options page.
            $this->runPostProcessingPlugins();
        }
        $close_file = C\CRAWL_DIR . '/schedules/' . self::index_closed_name .
            $this->crawl_time . ".txt";
        if (!file_exists($close_file) &&
            strcmp($this->server_type, self::BOTH) != 0) {
            file_put_contents($close_file, "2");
            $start_time = microtime(true);
            do {
                if ($this->isAIndexer()) {
                    L\crawlLog("Indexer waiting for Scheduler to stop.");
                } else {
                    L\crawlLog("Scheduler waiting for Indexer to stop.");
                }
                sleep(C\QUEUE_SLEEP_TIME);
                $contents = trim(file_get_contents($close_file));
            } while(file_exists($close_file) && strcmp($contents, "1") != 0 );
        } else {
            file_put_contents($close_file, "1");
        }
    }
    /**
     * Used to write an admin crawl status message during a start or stop
     * crawl.
     *
     * @param string $message to write into crawl_status.txt this will show
     *     up in the web crawl status element.
     */
    public function writeAdminMessage($message)
    {
        $crawl_status = [];
        $crawl_status['MOST_RECENT_FETCHER'] = "";
        $crawl_status['MOST_RECENT_URLS_SEEN'] = [];
        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
        $crawl_status['REPEAT_TYPE'] = (!isset($this->repeat_type)) ?
            -1 : $this->repeat_type;
        $crawl_status['SLEEP_START'] = (!isset($this->sleep_start)) ?
            "00:00" : $this->sleep_start;
        $crawl_status['SLEEP_DURATION'] = (!isset($this->sleep_duration)) ?
            "-1" : $this->sleep_duration;
        //value might be 0 so don't use empty use !isset
        $crawl_status['MAX_DEPTH'] = (!isset($this->max_depth)) ?
            -1 : $this->max_depth;
        $crawl_status['ROBOTS_TXT'] = (!isset($this->robots_txt)) ?
            C\ALWAYS_FOLLOW_ROBOTS : $this->robots_txt;
        $crawl_status['COUNT'] = 0;
        $crawl_status['DESCRIPTION'] = $message;
        file_put_contents($this->crawl_status_file_name,
            serialize($crawl_status));
        chmod($this->crawl_status_file_name, 0777);
    }
    /**
     * When a crawl is being shutdown, this function is called to write
     * the contents of the web queue bundle back to schedules. This allows
     * crawls to be resumed without losing urls. This function can also be
     * called if the queue gets clogged to reschedule its contents for a later
     * time.
     *
     * @param bool $for_reschedule if the call was to reschedule the urls
     *     to be crawled at a later time as opposed to being used to
     *     save the urls because the crawl is being halted.
     */
    public function dumpQueueToSchedules($for_reschedule = false)
    {
        if (!$for_reschedule) {
            $this->writeAdminMessage("SHUTDOWN_QUEUE");
        }
        if (!isset($this->web_queue->to_crawl_queue)) {
            L\crawlLog("DQ URL queue appears to be empty or null");
            return;
        }
        L\crawlLog("DQ Writing queue contents back to schedules...");
        $dir = C\CRAWL_DIR . "/schedules/" . self::schedule_data_base_name .
            $this->crawl_time;
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $now = time();
        if ($for_reschedule) {
            $day = floor($now/C\ONE_DAY);
            $note_string = "Reschedule";
        } else {
            $day = floor($this->crawl_time/C\ONE_DAY) - 1;
                //want before all other schedules, so will be reloaded first
            $note_string = "";
        }
        $dir .= "/$day";
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        //get rid of previous restart attempts, if present
        if (!$for_reschedule) {
            $this->db->unlinkRecursive($dir, false);
        }
        $count = $this->web_queue->to_crawl_queue->count;
        $old_time = 1;
        $schedule_data = [];
        $schedule_data[self::SCHEDULE_TIME] = $this->crawl_time;
        $schedule_data[self::TO_CRAWL] = [];
        $fh = $this->web_queue->openUrlArchive();
        for ($time = 1; $time < $count; $time++) {
            L\crawlTimeoutLog("DQ..have written %s urls of %s urls so far",
                $time, $count);
            $tmp =  $this->web_queue->peekQueue($time, $fh);
            list($url, $weight, , ) = $tmp;
            // if queue error skip
            if ($tmp === false || strcmp($url, "LOOKUP ERROR") == 0) {
                continue;
            }
            /* for fetcher hash is  a hash of link_num . hash_of_page_link_on
             * in the case below. Either the url or hash can be used to
             * determine if the page has been seen. In the case, of a dump
             * we choose hash to be something so only url affects whether
             * dedup.
             */
            $hash = L\crawlHash($now . $url);
            if ($for_reschedule) {
                $schedule_time = $time + $now;
            } else {
                $schedule_time = $time;
            }
            $schedule_data[self::TO_CRAWL][] = [$url, $weight, $hash];
            if ($time - $old_time >= C\MAX_FETCH_SIZE) {
                if (count($schedule_data[self::TO_CRAWL]) > 0) {
                    $data_string = L\webencode(
                        gzcompress(serialize($schedule_data)));
                    $data_hash = L\crawlHash($data_string);
                    file_put_contents($dir . "/At" . $schedule_time .
                        "From127-0-0-1" . $note_string .
                        "WithHash$data_hash.txt", $data_string);
                    $data_string = "";
                    $schedule_data[self::TO_CRAWL] = [];
                }
                $old_time = $time;
            }
        }
        $this->web_queue->closeUrlArchive($fh);
        if (count($schedule_data[self::TO_CRAWL]) > 0) {
            $data_string = L\webencode(gzcompress(serialize($schedule_data)));
            $data_hash = L\crawlHash($data_string);
            if ($for_reschedule) {
                $schedule_time = $time + $now;
            } else {
                $schedule_time = $time;
            }
            file_put_contents($dir."/At" . $schedule_time . "From127-0-0-1".
                $note_string . "WithHash$data_hash.txt", $data_string);
        }
        $this->db->setWorldPermissionsRecursive(C\CRAWL_DIR .
            '/cache/'. self::queue_base_name . $this->crawl_time);
        $this->db->setWorldPermissionsRecursive($dir);
    }
    /**
     * During crawl shutdown, this function is called to do a final save and
     * merge of the crawl dictionary, so that it is ready to serve queries.
     */
    public function shutdownDictionary()
    {
        $this->writeAdminMessage("SHUTDOWN_DICTIONARY");
        $is_double_index = L\generalIsA($this->index_archive, C\NS_LIB .
            "DoubleIndexBundle");
        if (is_object($this->index_archive)) {
            $this->index_archive->stopIndexingBundle();
        }
        $base_name = ($is_double_index) ? self::double_index_base_name :
            self::index_data_base_name;
        $this->db->setWorldPermissionsRecursive(C\CRAWL_DIR . '/cache/'.
            $base_name . $this->crawl_time);
    }
    /**
     * During crawl shutdown this is called to run any post processing plugins
     */
    public function runPostProcessingPlugins()
    {
        if (isset($this->indexing_plugins)) {
            L\crawlLog("Post Processing....");
            $this->writeAdminMessage("SHUTDOWN_RUNPLUGINS");
            L\crawlLog("... Wrote Run Plugin shutdown Message");
            $num_plugins = count($this->indexing_plugins);
            if ($num_plugins > 0) {
                L\crawlLog("... Will now shutdown $num_plugins plugins.");
            }
            foreach ($this->indexing_plugins as $plugin) {
                if (empty($plugin)) {
                    continue;
                }
                $plugin_instance_name =
                    lcfirst($plugin)."Plugin";
                $plugin_name = C\NS_PLUGINS . $plugin . "Plugin";
                $this->$plugin_instance_name =
                    new $plugin_name();
                if (method_exists($plugin_name, "setConfiguration") &&
                    isset($this->indexing_plugins_data[$plugin])) {
                    $this->$plugin_instance_name->setConfiguration(
                        $this->indexing_plugins_data[$plugin]);
                }
                if ($this->$plugin_instance_name) {
                    L\crawlLog(
                        "... executing $plugin_instance_name");
                    $this->$plugin_instance_name->
                        postProcessing($this->crawl_time);
                }
            }
        }
    }
    /**
     * Saves the index_archive and, in particular, its current shard to disk
     */
    public function indexSave()
    {
        $this->last_index_save_time = time();
        if (isset($this->index_archive) && $this->index_dirty) {
            $this->index_archive->forceSave();
            $base_name = (L\generalIsA($this->index_archive, C\NS_LIB .
                "DoubleIndexBundle")) ? self::double_index_base_name :
                self::index_data_base_name;
            $this->index_dirty = false;
            // chmod so apache can also write to these directories
            $this->db->setWorldPermissionsRecursive(
                C\CRAWL_DIR . '/cache/' . $base_name . $this->crawl_time);
        }
    }
    /**
     * Begins crawling base on time, order, restricted site $info
     * Setting up a crawl involves creating a queue bundle and an
     * index archive bundle
     *
     * @param array $info parameter for the crawl
     */
    public function startCrawl($info)
    {
        //to get here we at least have to have a crawl_time
        $this->crawl_time = $info[self::CRAWL_TIME];
        $try_to_set_from_old_index = [];
        $update_disallow = false;
        foreach (self::$info_parameter_map as $index_field => $info_field) {
            if (isset($info[$info_field])) {
                if ($index_field == "disallowed_sites") {
                    $update_disallow = true;
                }
                $this->$index_field = $info[$info_field];
            } else {
                array_push($try_to_set_from_old_index,  $index_field);
            }
        }
        /* We now do further processing or disallowed sites to see if any
           of them are really quota sites. We want both indexer and scheduler
           to be aware of the changes as the indexer is responsible for
           storing the values persistently into the IndexArchiveBundle
           in case we need to resume a crawl.
         */
        if ($update_disallow == true) {
            $this->updateDisallowedQuotaSites();
        }
        $this->waiting_hosts = [];
        $this->initializeWebQueue();
        $this->initializeIndexBundle($info, $try_to_set_from_old_index);
    }
    /**
     * Function used to set up an indexer's IndexArchiveBundle or
     * DoubleIndexBundle according to the current crawl parameters or
     * the values stored in an existing bundle.
     *
     * @param array $info if initializing a new crawl this should contain
     *      the crawl parameters
     * @param array $try_to_set_from_old_index parameters of the crawl
     *      to try to set from values already stored in archive info,
     *      other parameters are assumed to have been updated since.
     */
    public function initializeIndexBundle($info = [],
        $try_to_set_from_old_index = null)
    {
        if ($try_to_set_from_old_index === null) {
            $try_to_set_from_old_index = array_keys(self::$info_parameter_map);
        }
        if(empty($this->repeat_type) || $this->repeat_type <= 0) {
            $class_name = C\NS_LIB . "IndexArchiveBundle";
            $dir = C\CRAWL_DIR . '/cache/' . self::index_data_base_name .
                $this->crawl_time;
        } else {
            $class_name = C\NS_LIB . "DoubleIndexBundle";
            $dir = C\CRAWL_DIR . '/cache/' . self::double_index_base_name .
                $this->crawl_time;
        }
        $archive_exists = false;
        if (file_exists($dir)) {
            $archive_info = $class_name::getArchiveInfo($dir);
            $index_info = unserialize($archive_info['DESCRIPTION']);
            foreach ($try_to_set_from_old_index as $index_field) {
                if (isset($index_info[self::$info_parameter_map[$index_field]])
                    ) {
                    $this->$index_field =
                        $index_info[self::$info_parameter_map[$index_field]];
                }
            }
            $archive_exists = true;
        }
        if ($this->isAIndexer()) {
            if ($archive_exists) {
                $this->last_index_save_time = 0;
                $this->index_archive = new $class_name($dir, false, null,
                    C\NUM_DOCS_PER_GENERATION, false);
                $this->last_index_save_time = time();
                $sites = [];
                /* write crawl status immediately on restart so can see counts
                   without having to wait till first indexed data handled
                   (might take a while if merging dictionary)
                 */
                $this->writeCrawlStatus($sites);
            } else if (!empty($this->repeat_type) && $this->repeat_type > 0) {
                $this->index_archive = new $class_name($dir, false,
                    serialize($info), C\NUM_DOCS_PER_GENERATION, false,
                    $this->repeat_type);
            } else {
                $this->index_archive = new $class_name($dir, false,
                    serialize($info), C\NUM_DOCS_PER_GENERATION, false);
            }
            if (file_exists(C\CRAWL_DIR . '/schedules/' .
                self::index_closed_name . $this->crawl_time. " .txt")) {
                unlink(C\CRAWL_DIR . '/schedules/' . self::index_closed_name.
                    $this->crawl_time . ".txt");
            }
            $this->db->setWorldPermissionsRecursive($dir);
        }
        //Get modified time of initial setting of crawl params
        $this->archive_modified_time =
            $class_name::getParamModifiedTime($dir);
    }
    /**
     * This is called whenever the crawl options are modified to parse
     * from the disallowed sites, those sites of the format:
     * site#quota
     * where quota is the number of urls allowed to be downloaded in an hour
     * from the site. These sites are then deleted from disallowed_sites
     * and added to $this->quota sites. An entry in $this->quota_sites
     * has the format: $quota_site => [$quota, $num_urls_downloaded_this_hr]
     */
    public function updateDisallowedQuotaSites()
    {
        $num_sites = count($this->disallowed_sites);
        $active_quota_sites = [];
        for ($i = 0; $i < $num_sites; $i++) {
            $site_parts = explode("#", $this->disallowed_sites[$i]);
            if (count($site_parts) > 1) {
                $quota = intval(array_pop($site_parts));
                if ($quota <= 0) {
                    continue;
                }
                $this->disallowed_sites[$i] = false;
                $quota_site = implode("#", $site_parts);
                $active_quota_sites[] = $quota_site;
                if (isset($this->quota_sites[$quota_site])) {
                    $this->quota_sites[$quota_site] = [$quota,
                        $this->quota_sites[$quota_site][1]];
                } else {
                    //($quota, $num_scheduled_this_last_hour)
                    $this->quota_sites[$quota_site] = [$quota, 0];
                }
            }
        }
        foreach ($this->quota_sites as $site => $info) {
            if (!in_array($site, $active_quota_sites)) {
                $this->quota_sites[$site] = false;
            }
        }
        $this->disallowed_sites = array_filter($this->disallowed_sites);
        $this->quota_sites = array_filter($this->quota_sites);
        $this->quota_sites_keys = array_keys($this->quota_sites);
    }
    /**
     * This method sets up a WebQueueBundle according to the current crawl
     * order so that it can receive urls and prioritize them.
     */
    public function initializeWebQueue()
    {
        $min_or_max = ($this->crawl_order == self::BREADTH_FIRST) ?
            self::MIN : self::MAX;
        if ($this->isAScheduler()) {
            if ($this->crawl_type == self::WEB_CRAWL ||
                !isset($this->crawl_type)) {
                $this->web_queue = new WebQueueBundle(
                    C\CRAWL_DIR . '/cache/' . self::queue_base_name.
                    $this->crawl_time, C\URL_FILTER_SIZE,
                        C\NUM_URLS_QUEUE_RAM, $min_or_max);
            } else {
                $this->web_queue = null;
            }
            // chmod so web server can also write to these directories
            if ($this->crawl_type == self::WEB_CRAWL) {
                $this->db->setWorldPermissionsRecursive(
                   C\CRAWL_DIR . '/cache/' . self::queue_base_name .
                   $this->crawl_time);
            }
        } else {
            $this->index_archive = null;
        }
        L\garbageCollect(); // garbage collect old crawls
    }
    /**
     * Delete all the urls from the web queue does not affect filters
     */
    public function clearWebQueue()
    {
        $count = $this->web_queue->to_crawl_queue->count;
        $fh = $this->web_queue->openUrlArchive();
        for ($i = $count; $i > 0; $i--) {
            L\crawlTimeoutLog("CW..Scheduler: Removing least url %s of %s ".
                "from queue.", ($count - $i), floor($count/2));
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            if ($url) {
                $this->web_queue->removeQueue($url);
            }
        }
        $this->web_queue->closeUrlArchive($fh);
    }
    /**
     * Checks to see if the parameters by which the active crawl are being
     * conducted have been modified since the last time the values were put
     * into queue server field variables. If so, it updates the values to
     * to their new values
     */
    public function checkUpdateCrawlParameters()
    {
        L\crawlLog("Check for update in crawl parameters...");
        $index_archive_class = C\NS_LIB . (($this->repeat_type > 0 ) ?
            "DoubleIndexBundle" : "IndexArchiveBundle");
        $base_name = ($this->repeat_type > 0 ) ? self::double_index_base_name :
            self::index_data_base_name;
        $dir = C\CRAWL_DIR .'/cache/' . $base_name . $this->crawl_time;
        $modified_time = $index_archive_class::getParamModifiedTime($dir);
        if ($this->archive_modified_time == $modified_time) {
            L\crawlLog("...none.");
            return;
        }
        $updatable_info = self::$info_parameter_map;
        unset($updatable_info["crawl_order"], $updatable_info["crawl_type"],
            $updatable_info["crawl_index"], $updatable_info["crawl_pages"]);
        $keys = array_keys($updatable_info);
        $archive_info = $index_archive_class::getArchiveInfo($dir);
        $index_info = unserialize($archive_info['DESCRIPTION']);
        $check_cull_fields = ["restrict_sites_by_url", "allowed_sites",
            "disallowed_sites"];
        $cull_now_non_crawlable = false;
        $update_disallow = false;
        foreach ($keys as $index_field) {
            if (isset($index_info[$updatable_info[$index_field]]) ) {
                if ($index_field == "disallowed_sites") {
                    $update_disallow = true;
                }
                if (in_array($index_field, $check_cull_fields) &&
                    (!isset($this->$index_field) ||
                    $this->$index_field !=
                    $index_info[$updatable_info[$index_field]]) ) {
                    $cull_now_non_crawlable = true;
                }
                $this->$index_field =
                    $index_info[$updatable_info[$index_field]];
                if ($this->isOnlyScheduler()) {
                    L\crawlLog("Scheduler Updating ...$index_field.");
                } else {
                    L\crawlLog("Updating ...$index_field.");
                }
            }
        }
        /* We now do further processing or disallowed sites to see if any
           of them are really quota sites. We want both indexer and scheduler
           to be aware of the changes as the indexer is responsible for
           storing the values persistently into the IndexArchiveBundle
           in case we need to resume a crawl.
         */
        if ($update_disallow == true) {
            $this->updateDisallowedQuotaSites();
        }
        if ($this->isAScheduler() && $cull_now_non_crawlable
            && $this->crawl_type != self::ARCHIVE_CRAWL) {
            L\crawlLog("Scheduler: Allowed/Disallowed Urls have changed");
            L\crawlLog("Scheduler: Checking if urls in queue need" .
                " to be culled");
            $this->cullNoncrawlableSites();
        }
        $this->archive_modified_time = $modified_time;
    }
    /**
     * Delete all the queue bundles and schedules that don't have an
     * associated index bundle as this means that crawl has been deleted.
     */
    public function deleteOrphanedBundles()
    {
        $dirs = glob(C\CRAWL_DIR . '/cache/*', GLOB_ONLYDIR);
        $num_dirs = count($dirs);
        $living_stamps = [];
        $dir_cnt = 0;
        foreach ($dirs as $dir) {
            $dir_cnt++;
            L\crawlTimeoutLog("..Indexer looking through directory %s of %s".
                " to see if orphaned", $dir_cnt, $num_dirs);
            if (strlen(
                $pre_timestamp = strstr($dir, self::queue_base_name)) > 0) {
                $timestamp =
                    substr($pre_timestamp, strlen(self::queue_base_name));
                if (!file_exists(C\CRAWL_DIR . '/cache/' .
                    self::index_data_base_name . $timestamp) &&
                    !file_exists(C\CRAWL_DIR . '/cache/' .
                        self::double_index_base_name . $timestamp)) {
                    $this->db->unlinkRecursive($dir, true);
                }
            }
            $is_double = true;
            $pre_timestamp = strstr($dir, self::double_index_base_name);
            $name_len = strlen( self::double_index_base_name);
            if (empty($pre_timestamp)) {
                $pre_timestamp = strstr($dir, self::index_data_base_name);
                $name_len = strlen(self::index_data_base_name);
                $is_double = false;
            }
            if (strlen($pre_timestamp) > 0) {
                $timestamp = substr($pre_timestamp, $name_len);
                $close_file = C\CRAWL_DIR.'/schedules/'.
                    self::index_closed_name . $timestamp . ".txt";
                $close_exists = file_exists($close_file);
                $state = "1";
                if (!$is_double && $close_exists) {
                    $state = trim(file_get_contents($close_file));
                }
                if (!$is_double &&
                    (!$close_exists || strcmp($state, "1") != 0) &&
                    $this->crawl_time != $timestamp) {
                    L\crawlLog("Properly closing Index with Timestamp ".
                        $timestamp. ".");
                    $index_archive = new IndexArchiveBundle($dir, false, null,
                        C\NUM_DOCS_PER_GENERATION, false);
                    //do a fast merge all
                    $index_archive->dictionary->mergeAllTiers(null, -1, true);
                    L\crawlLog("... index fast merge all tiers completed!");
                    touch($this->crawl_status_file_name, time());
                    file_put_contents($close_file, "1");
                }
                $living_stamps[] = $timestamp;
            }
        }
        $files = glob(C\CRAWL_DIR . '/schedules/*');
        $file_cnt = 0;
        $crawl_prefixes = [self::schedule_data_base_name,
            self::double_index_base_name,
            self::etag_expires_data_base_name,
            self::index_data_base_name, self::robot_data_base_name,
            self::name_archive_iterator, self::fetch_archive_iterator,
            self::schedule_name, self::index_closed_name,
                self::save_point];
        $prefix_pattern = "/(".implode("|", $crawl_prefixes) .")(\d{10})/";
        foreach ($files as $file) {
            $file_cnt++;
            L\crawlTimeoutLog("..Indexer still deleting %s of %s orphaned " .
                "files", $file_cnt, $file_cnt);
            if (preg_match($prefix_pattern, $file, $matches)) {
                if (!empty($matches[2]) &&
                    !in_array($matches[2], $living_stamps)) {
                    if (is_dir($file)) {
                        $this->db->unlinkRecursive($file, true);
                    } else {
                        unlink($file);
                    }
                }
            }
        }
    }
    /**
     * This is a callback method that IndexArchiveBundle will periodically
     * call when it processes a method that take a long time. This
     * allows for instance continued processing of index data while say
     * a dictionary merge is being performed.
     */
    public function join()
    {
        /*
           touch crawl_status so it doesn't stop the crawl right
           after it just restarted
         */
        touch($this->crawl_status_file_name, time());
        L\crawlLog("Rejoin Crawl {{{{");
        $this->processCrawlData(true);
        L\crawlLog("}}}} End Rejoin Crawl");
    }
    /**
     * Generic function used to process Data, Index, and Robot info schedules
     * Finds the first file in the the direcotry of schedules of the given
     * type, and calls the appropriate callback method for that type.
     *
     * @param string $base_dir directory for of schedules
     * @param string $callback_method what method should be called to handle
     *     a schedule
     * @param boolean $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    public function processDataFile($base_dir, $callback_method,
        $blocking = false)
    {
        $dirs = glob($base_dir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.txt');
            if (isset($old_dir)) {
                L\crawlLog("Deleting $old_dir\n");
                $this->db->unlinkRecursive($old_dir);
                /* The idea is that only go through outer loop more than once
                   if earlier data directory empty.
                   Note: older directories should only have data dirs or
                   deleting like this might cause problems!
                 */
            }
            foreach ($files as $file) {
                $path_parts = pathinfo($file);
                $base_name = $path_parts['basename'];
                $len_name =  strlen(self::data_base_name);
                $file_root_name = substr($base_name, 0, $len_name);
                if (strcmp($file_root_name, self::data_base_name) == 0) {
                    $this->$callback_method($file, $blocking);
                    return;
                }
            }
            $old_dir = $dir;
        }
    }
    /**
     * Determines the most recent fetcher that has spoken with the
     * web server of this queue server and stored the result in the
     * field variable most_recent_fetcher
     */
    public function updateMostRecentFetcher()
    {
        $robot_table_name = C\CRAWL_DIR . "/" . $this->channel . "-" .
            self::robot_table_name;
        L\crawlLog("Updating most recent fetcher...");
        if (file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
            if (!is_array($robot_table)) {
                L\crawlLog("... couldn't unserialize robot table correctly.");
                return;
            }
            $recent = 0 ;
            foreach ($robot_table as $robot_name => $robot_data) {
                if ($robot_data[2] > $recent) {
                    $this->most_recent_fetcher =  $robot_name;
                    $recent = $robot_data[2];
                }
            }
            L\crawlLog("... Most recent fetcher was: " .
                $this->most_recent_fetcher);
        } else {
            L\crawlLog("... couldn't find robot table.");
        }
    }
    /**
     * Sets up the directory to look for a file of unprocessed
     * index archive data from fetchers then calls the function
     * processDataFile to process the oldest file found
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    public function processIndexData($blocking)
    {
        L\crawlLog("Indexer: Checking for index data files to process...");
        $index_dir = C\CRAWL_DIR . "/schedules/".
            self::index_data_base_name . $this->crawl_time;
        $this->processDataFile($index_dir, "processIndexArchive", $blocking);
        L\crawlLog("Indexer: done index data check and process.");
    }
    /**
     * Adds the summary and index data in $file to summary bundle and word index
     *
     * @param string $file containing web pages summaries
     * @param bool $blocking this method might be called by the indexer
     *     subcomponent when a merge tier phase is ongoing to allow for
     *     other processing to occur. If so, we don't want a regress
     *     where the indexer calls this code calls the indexer etc. If
     *     the blocking flag is set then the indexer subcomponent won't
     *     be called
     */
    public function processIndexArchive($file, $blocking)
    {
        static $blocked = false;
        if ($blocking && $blocked) {
            L\crawlLog("Indexer waiting for merge tiers to ".
                "complete before write partition.");
            return;
        }
        if (!$blocking) {
            $blocked = false;
        }
        L\crawlLog(
            "{$this->server_name} is starting to process index data,".
            " memory usage: " . memory_get_usage() . "...");
        L\crawlLog("Indexer: Processing index data in $file...");
        $start_time = microtime(true);
        $start_total_time = microtime(true);
        $pre_sites_and_index = L\webdecode(file_get_contents($file));
        $len_urls = L\unpackInt(substr($pre_sites_and_index, 0, 4));
        $seen_urls_string = substr($pre_sites_and_index, 4, $len_urls);
        $sites[self::SEEN_URLS] = [];
        $pos = 0;
        $num = 0;
        $bad = false;
        $max_batch_sites_and_links = C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER *
            (max(C\MAX_LINKS_TO_EXTRACT, $this->max_links_to_extract) + 1);
        while($pos < $len_urls && $num <= $max_batch_sites_and_links) {
            L\crawlTimeoutLog("..Indexer still processing index data at " .
                "position %s of out of %s", $pos, $len_urls);
            $len_site = L\unpackInt(substr($seen_urls_string, $pos, 4));
            if ($len_site > 2 * $this->page_range_request) {
                L\crawlLog("Indexer: Site string too long, $len_site,".
                    " data file may be corrupted? Skip rest.");
                $bad = true;
                break;
            }
            $pos += 4;
            $site_string = substr($seen_urls_string, $pos, $len_site);
            $pos += strlen($site_string);
            $tmp = unserialize(gzuncompress($site_string));
            if (!$tmp  || !is_array($tmp)) {
                L\crawlLog("Compressed array null,".
                    " data file may be corrupted? Skip rest.");
                $bad = true;
                break;
            }
            $sites[self::SEEN_URLS][] = $tmp;
            $num++;
        }
        if ($num > $max_batch_sites_and_links *
            C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER || $bad) {
            L\crawlLog("Index data file len_urls was $len_urls num was $num, ".
                "may be corrupt so skipping this file.");
            L\crawlLog("Indexer Done Index Processing File: $file. " .
                "Total time: " . L\changeInMicrotime($start_total_time));
            unlink($file);
            return;
        }
        L\crawlLog("A. Indexer unpack index data, init local shard time: " .
            L\changeInMicrotime($start_time));
        $start_time = microtime(true);
        //do deduplication of summaries
        if (isset($sites[self::SEEN_URLS]) &&
            count($sites[self::SEEN_URLS]) > 0) {
            $seen_sites = $sites[self::SEEN_URLS];
            $seen_sites = array_values($seen_sites);
            unset($sites[self::SEEN_URLS]);
            $num_seen = count($seen_sites);
            L\crawlLog("Indexer: SEEN_URLS array had $num_seen sites ".
                "(including links).");
        } else {
            $num_seen = 0;
        }
        $generation = $this->index_archive->initGenerationToAdd(
                $num_seen, $this, $blocking);
        if ($generation == -1) {
            L\crawlLog("Indexer waiting for merge tiers to ".
                "complete before write partition. A");
            $blocked = true;
            // In this case if we block, will end up reprocess file
            return; /* if don't return here can process rest of
                method */
        }
        $visited_urls_count = 0;
        $recent_urls_count = 0;
        $recent_urls = [];
        for ($i = 0; $i < $num_seen; $i++) {
            if ($seen_sites[$i][self::TYPE] != "link") {
                $seen_sites[$i][self::HASH_URL] =
                    L\crawlHash($seen_sites[$i][self::URL], true);
                $seen_sites[$i][self::IS_DOC] = true;
                $visited_urls_count++;
                array_push($recent_urls, $seen_sites[$i][self::URL]);
                if ($recent_urls_count >= C\NUM_RECENT_URLS_TO_DISPLAY) {
                    array_shift($recent_urls);
                }
                $recent_urls_count++;
            }
        }
        if (!empty($seen_sites)) {
            $this->index_archive->addPages($generation, self::SUMMARY_OFFSET,
                $seen_sites, $visited_urls_count);
            unset($seen_sites);
        }
        L\crawlLog("B. Store summaries memory usage: ". memory_get_usage() .
            " time: " . L\changeInMicrotime($start_time));
        if (isset($recent_urls)) {
            $sites[self::RECENT_URLS] = $recent_urls;
            $this->writeCrawlStatus($sites);
        }
        L\crawlLog("Indexer Done Index Processing File: $file. Total time: ".
            L\changeInMicrotime($start_total_time));
        if (file_exists($file)) {
            //Haven't tracked down yet, but can try to delete twice giving warn
            unlink($file);
        }
    }
    /**
     * Checks how old the oldest robot data is and dumps if older then a
     * threshold, then sets up the path to the robot schedule directory
     * and tries to process a file of robots.txt robot paths data from there
     */
    public function processRobotUrls()
    {
        if (!isset($this->web_queue) ) {
            return;
        }
        L\crawlLog("Scheduler Checking age of robot data in queue server ");
        if ($this->web_queue->getRobotTxtAge() > C\CACHE_ROBOT_TXT_TIME) {
            $this->deleteRobotData();
            L\crawlLog("Scheduler: Deleting DNS Cache data..");
            $message = $this->web_queue->emptyDNSCache();
            L\crawlLog("Scheduler: Delete task responded:\n $message");
        } else {
            L\crawlLog("Scheduler: ... less than max age\n");
            L\crawlLog("Scheduler: Number of Crawl-Delayed Hosts: ".floor(count(
                $this->waiting_hosts)/2));
        }
        L\crawlLog("Scheduler: Checking for robots.txt files to process...");
        $robot_dir = C\CRAWL_DIR . "/schedules/" .
            self::robot_data_base_name . $this->crawl_time;
        $this->processDataFile($robot_dir, "processRobotArchive");
        L\crawlLog("Scheduler done robot check and process. ");
    }
    /**
     * Reads in $file of robot data adding host-paths to the disallowed
     * robot filter and setting the delay in the delay filter of
     * crawled delayed hosts
     * @param string $file file to read of robot data, is removed after
     *     processing
     */
    public function processRobotArchive($file)
    {
        L\crawlLog("Scheduler Processing Robots data in $file");
        $start_time = microtime(true);
        $sites = unserialize(gzuncompress(
            L\webdecode(file_get_contents($file))));
        L\crawlLog("Scheduler done decompressing robot file");
        if (isset($sites)) {
            $num_sites = count($sites);
            $i = 0;
            foreach ($sites as $robot_host => $robot_info) {
                L\crawlTimeoutLog("..Scheduler processing robot item %s of %s.",
                    $i, $num_sites);
                $i++;
                $this->web_queue->addGotRobotTxtFilter($robot_host);
                $scheme = UrlParser::getScheme($robot_host);
                if ($scheme == "gopher") {
                    $robot_url = $robot_host . "/0/robots.txt";
                } else {
                    $robot_url = $robot_host . "/robots.txt";
                }
                if ($this->web_queue->containsUrlQueue($robot_url)) {
                    L\crawlLog("Scheduler Removing $robot_url from queue");
                    $this->web_queue->removeQueue($robot_url);
                }
                if (isset($robot_info[self::CRAWL_DELAY])) {
                    $this->web_queue->setCrawlDelay($robot_host,
                        $robot_info[self::CRAWL_DELAY]);
                }
                if (isset($robot_info[self::ROBOT_PATHS])) {
                    $this->web_queue->addRobotPaths($robot_host,
                        $robot_info[self::ROBOT_PATHS]);
                }
                if (isset($robot_info[self::IP_ADDRESSES])) {
                    $final_ip = array_pop($robot_info[self::IP_ADDRESSES]);
                    $this->web_queue->addDNSCache($robot_host, $final_ip);
                }
            }
        }
        L\crawlLog("Scheduler Robot time: ".
            L\changeInMicrotime($start_time)."\n");
        L\crawlLog("Scheduler Done Robots Processing File: $file");
        unlink($file);
    }
    /**
     * Process cache page validation data files sent by Fetcher
     */
    public function processEtagExpires()
    {
        L\crawlLog("Scheduler Checking for etag expires http header data");
        $etag_expires_dir = C\CRAWL_DIR."/schedules/".
            self::etag_expires_data_base_name.$this->crawl_time;
        $this->processDataFile($etag_expires_dir,
            "processEtagExpiresArchive");
        L\crawlLog("Scheduler done etag check and process.");
    }
    /**
     * Processes a cache page validation data file. Extracts key-value pairs
     * from the file and inserts into the B-Tree used for storing cache
     * page validation data.
     * @param string $file is the cache page validation data file written by
     * Fetchers.
     */
    public function processEtagExpiresArchive($file)
    {
        L\crawlLog("Scheduler Processing etag expires http header" .
            " data in $file");
        $start_time = microtime(true);
        $etag_expires_data =
            unserialize(gzuncompress(L\webdecode(file_get_contents($file))));
        L\crawlLog("Scheduler Done uncompressing etag data.".
            " Starting to add to btree");
        $num_entries = count($etag_expires_data);
        $i = 0;
        foreach ($etag_expires_data as $data) {
            L\crawlTimeoutLog(
                "..Scheduler still etag processing on item %s of %s.",
                $i, $num_entries);
            $i++;
            $link = $data[0];
            $value = $data[1];
            $key = L\crawlHash($link, true);
            $entry = [$key, $value];
            $this->web_queue->etag_btree->insert($entry);
        }
        L\crawlLog(" time: ". L\changeInMicrotime($start_time)."\n");
        L\crawlLog("Scheduler Done processing etag expires http".
            " header data file: $file");
        unlink($file);
    }
    /**
     * Deletes all Robot informations stored by the QueueServer.
     *
     * This function is called roughly every CACHE_ROBOT_TXT_TIME.
     * It forces the crawler to redownload robots.txt files before hosts
     * can be continued to be crawled. This ensures if the cache robots.txt
     * file is never too old. Thus, if someone changes it to allow or disallow
     * the crawler it will be noticed reasonably promptly.
     */
    public function deleteRobotData()
    {
        L\crawlLog("... Scheduler: unlinking robot schedule files ...");

        $robot_schedules = C\CRAWL_DIR.'/schedules/'.
            self::robot_data_base_name.$this->crawl_time;
        $this->db->unlinkRecursive($robot_schedules, true);

        L\crawlLog("... Scheduler: resetting robot data files ...");
        $message = $this->web_queue->emptyRobotData();
        L\crawlLog("... Scheduler: resetting robot task answered:\n$message\n");
        L\crawlLog("...Scheduler: Clearing Waiting Hosts");
        $this->waiting_hosts = [];
    }
    /**
     * Checks for a new crawl file or a schedule data for the current crawl and
     * if such a exists then processes its contents adding the relevant urls to
     * the priority queue
     */
    public function processQueueUrls()
    {
        L\crawlLog("Scheduler Start checking for new URLs data memory usage: ".
            memory_get_usage());
        $start_schedule_filename = C\CRAWL_DIR . "/schedules/" .
            $this->channel . "-" . self::schedule_start_name;
        if (file_exists($start_schedule_filename)) {
            if ($this->repeat_type > 0) {
                $dir_name = C\CRAWL_DIR . '/cache/' .
                    self::double_index_base_name . $this->crawl_time;
                DoubleIndexBundle::setStartSchedule($dir_name, $this->channel);
            }
            L\crawlLog("Scheduler Start schedule urls" .
                $start_schedule_filename);
            $this->processDataArchive($start_schedule_filename);
        }
        $schedule_dir = C\CRAWL_DIR."/schedules/" .
            self::schedule_data_base_name . $this->crawl_time;
        $this->processDataFile($schedule_dir, "processDataArchive");
        L\crawlLog("done.");
    }
    /**
     * Process a file of to-crawl urls adding to or adjusting the weight in
     * the PriorityQueue of those which have not been seen. Also
     * updates the queue with seen url info
     *
     * @param string $file containing serialized to crawl and seen url info
     */
    public function processDataArchive($file)
    {
        $sites = $this->getDataArchiveFileData($file);
        if (isset($sites[self::TO_CRAWL]) &&
            count($sites[self::TO_CRAWL]) > C\MAX_FETCH_SIZE) {
            L\crawlLog("...New URLS file has too many to crawl sites ...");
            L\crawlLog("...Splitting first into smaller files ".
                "before processing...");
            $left_over = array_slice($sites[self::TO_CRAWL], C\MAX_FETCH_SIZE);
            $sites[self::TO_CRAWL] = array_slice($sites[self::TO_CRAWL], 0,
                C\MAX_FETCH_SIZE);
            $this->dumpBigScheduleToSmall($left_over);
            unset($left_over);
        }
        L\crawlLog("...Scheduler Updating Delayed Hosts Array Queue ...");
        $start_time = microtime(true);
        if (isset($sites[self::SCHEDULE_TIME])) {
            if (isset($this->waiting_hosts[$sites[self::SCHEDULE_TIME]])) {
                $delayed_hosts =
                    $this->waiting_hosts[$sites[self::SCHEDULE_TIME]];
                unset($this->waiting_hosts[$sites[self::SCHEDULE_TIME]]);
                foreach ($delayed_hosts as $hash_host) {
                    unset($this->waiting_hosts[$hash_host]);
                        //allows crawl-delayed host to be scheduled again
                }
                L\crawlLog("Scheduler done removing host delayed for schedule ".
                    $sites[self::SCHEDULE_TIME]);
                $now = time(); /* no schedule should take more than one hour
                    on the other hand schedule data might be waiting for days
                    before being processed. So we clear out waiting hosts
                    that have waited more than an hour
                */
                foreach ($this->waiting_hosts as $key => $value) {
                    L\crawlTimeoutLog(
                        "..still scheduler removing waiting hosts..");
                    if (is_array($value)) {
                        if (intval($key) + C\ONE_HOUR < $now) {
                            unset($this->waiting_hosts[$key]);
                        }
                    } else {
                        if (intval($value) + C\ONE_HOUR < $now) {
                            unset($this->waiting_hosts[$key]);
                        }
                    }
                }
            }
        }
        L\crawlLog(" time: ". L\changeInMicrotime($start_time));
        L\crawlLog("...Remove Seen Urls Queue...");
        $start_time = microtime(true);
        if (isset($sites[self::HASH_SEEN_URLS])) {
            $cnt = 0;
            foreach ($sites[self::HASH_SEEN_URLS] as $hash_url) {
                if ($this->web_queue->lookupHashTable($hash_url)) {
                    L\crawlLog("Scheduler: Removing hash ".
                        base64_encode($hash_url).
                        " from Queue");
                    $this->web_queue->removeQueue($hash_url, true);
                }
            }
        }
        L\crawlLog(" time: " . L\changeInMicrotime($start_time));
        L\crawlLog("... Scheduler Queueing To Crawl ...");
        $start_time = microtime(true);
        if (isset($sites[self::TO_CRAWL])) {
            L\crawlLog("A.. Scheduler: Queue delete previously  ".
                "seen urls from add set");
            $to_crawl_sites = $sites[self::TO_CRAWL];
            //delete seen urls from to_crawl_Sites
            $this->web_queue->differenceSeenUrls($to_crawl_sites, [0, 2]);
            L\crawlLog(" time: " . L\changeInMicrotime($start_time));
            L\crawlLog("B.. Scheduler: Queue insert unseen robots.txt urls;".
                "adjust changed weights");
            $start_time = microtime(true);
            $cnt = 0;
            $num_triples = count($to_crawl_sites);
            $added_urls = [];
            $added_pairs = [];
            $contains_host = [];
            /* if we allow web page recrawls then check here to see if delete
               url filter*/
            if ($this->page_recrawl_frequency > 0 &&
                $this->web_queue->getUrlFilterAge() >
                C\ONE_DAY * $this->page_recrawl_frequency) {
                L\crawlLog("Scheduler: Emptying queue page url filter!!!!!!");
                $this->web_queue->emptyUrlFilter();
            }
            $this->allow_disallow_cache_time = microtime(true);
            foreach ($to_crawl_sites as $triple) {
                $url = $triple[0];
                $cnt++;
                L\crawlTimeoutLog("..Scheduler: Processing url %s of %s ",
                    $cnt, $num_triples);
                if (strlen($url) < 7) { // strlen("http://")
                    continue;
                }
                if ($url[0] != 'h' && trim($url) == "localhost") {
                    $url = "http://localhost/";
                }
                $weight = $triple[1];
                $this->web_queue->addSeenUrlFilter($triple[2]); //add for dedup
                unset($triple[2]); // so triple is now a pair
                $host_url = UrlParser::getHost($url);
                if (strlen($host_url) < 7) { // strlen("http://")
                    continue;
                }
                $scheme = UrlParser::getScheme($host_url);
                if ($scheme == "gopher") {
                    $host_with_robots = $host_url . "/0/robots.txt";
                } else {
                    $host_with_robots = $host_url . "/robots.txt";
                }
                $robots_in_queue =
                    $this->web_queue->containsUrlQueue($host_with_robots);
                if ($this->web_queue->containsUrlQueue($url)) {
                    if ($robots_in_queue) {
                        $this->web_queue->adjustQueueWeight(
                            $host_with_robots, $weight, false);
                    }
                    $this->web_queue->adjustQueueWeight($url,
                        $weight, false);
                } else if ($this->allowedToCrawlSite($url) &&
                    !$this->disallowedToCrawlSite($url) &&
                    $this->withinQuota($url, 0)) {
                    if (!$this->web_queue->containsGotRobotTxt($host_url)
                        && !$robots_in_queue
                        && !isset($added_urls[$host_with_robots])
                        ) {
                            $added_pairs[] = [$host_with_robots, $weight];
                            $added_urls[$host_with_robots] = true;
                    }
                    if (!isset($added_urls[$url])) {
                        $added_pairs[] = $triple; // see comment above
                        $added_urls[$url] = true;
                    }
                }
            }
            $this->web_queue->notifyFlush();
            L\crawlLog(" time: " . L\changeInMicrotime($start_time));
            L\crawlLog("C.. Scheduler: Add urls to queue");
            $start_time = microtime(true);
            /*
                 adding urls to queue involves disk, contains and adjust do not
                 so group and do last
             */
            $this->web_queue->addUrlsQueue($added_pairs);
            L\crawlLog("... Scheduler: Added " . count($added_pairs). " urls ".
                "to queue from orginal list of $num_triples urls.");
        }
        L\crawlLog("Scheduler: time: " . L\changeInMicrotime($start_time));
        L\crawlLog("Scheduler: Done queue schedule file: $file");
        unlink($file);
    }
    /**
     * Used to split a large schedule of to crawl sites into small
     * ones (which are written to disk) which can be handled by
     * processDataArchive
     *
     * It is possible that a large schedule file is created if someone
     * pastes more than MAX_FETCH_SIZE many urls into the initial seed sites
     * of a crawl in the  UI.
     *
     * @param array &$sites array containing to crawl data
     */
    public function dumpBigScheduleToSmall(&$sites)
    {
        L\crawlLog("Writing small schedules...");
        $dir = C\CRAWL_DIR . "/schedules/" . self::schedule_data_base_name.
            $this->crawl_time;
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $day = floor($this->crawl_time/C\ONE_DAY) - 1;
            //want before all other schedules, so will be reloaded first
        $dir .= "/$day";
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $count = count($sites);
        $schedule_data = [];
        $schedule_data[self::SCHEDULE_TIME] = $this->crawl_time;
        $schedule_data[self::TO_CRAWL] = [];
        for ($i = 0; $i < $count; $i += C\MAX_FETCH_SIZE) {
            L\crawlTimeoutLog("..have written %s urls of %s urls so far", $i,
                $count);
            $schedule_data[self::TO_CRAWL] = array_slice($sites, $i,
                C\MAX_FETCH_SIZE);
            if (count($schedule_data[self::TO_CRAWL]) > 0) {
                $data_string = L\webencode(
                    gzcompress(serialize($schedule_data)));
                $data_hash = L\crawlHash($data_string);
                file_put_contents($dir . "/At" . $this->crawl_time .
                    "From127-0-0-1WithHash$data_hash.txt", $data_string);
                $data_string = "";
                $schedule_data[self::TO_CRAWL] = [];
            }
        }
        $this->db->setWorldPermissionsRecursive(
            C\CRAWL_DIR . '/cache/' .
            self::queue_base_name . $this->crawl_time);
        $this->db->setWorldPermissionsRecursive($dir);
    }
    /**
     * Writes status information about the current crawl so that the webserver
     * app can use it for its display.
     *
     * @param array $sites contains the most recently crawled sites
     */
    public function writeCrawlStatus($sites)
    {
        $crawl_status = [];
        $stat_file = $this->crawl_status_file_name;
        if (file_exists($stat_file) ) {
            $crawl_status = unserialize(file_get_contents($stat_file));
            if (!isset($crawl_status['CRAWL_TIME']) ||
                $crawl_status['CRAWL_TIME'] != $this->crawl_time) {
                $crawl_status = []; // status of some other crawl
            }
        }
        $crawl_status['MOST_RECENT_FETCHER'] = $this->most_recent_fetcher;
        if (isset($sites[self::RECENT_URLS])) {
            $crawl_status['MOST_RECENT_URLS_SEEN'] = $sites[self::RECENT_URLS];
        }
        $crawl_status['CRAWL_TIME'] = $this->crawl_time;
        $crawl_status['CHANNEL'] = $this->channel;
        $crawl_status['REPEAT_TYPE'] = (!isset($this->repeat_type)) ?
            -1 : $this->repeat_type;
        if (!empty($this->index_archive->repeat_time)) {
            $crawl_status['REPEAT_TIME'] = $this->index_archive->repeat_time;
        }
        $crawl_status['ROBOTS_TXT'] = (!isset($this->robots_txt)) ?
            C\ALWAYS_FOLLOW_ROBOTS : $this->robots_txt;
        $crawl_status['MAX_DEPTH'] = (!isset($this->max_depth)) ?
            -1 : $this->max_depth;
        $index_archive_class = C\NS_LIB . (($crawl_status['REPEAT_TYPE'] > 0 ) ?
            "DoubleIndexBundle" : "IndexArchiveBundle");
        $base_name = ($crawl_status['REPEAT_TYPE'] > 0 ) ?
            self::double_index_base_name : self::index_data_base_name;
        $info_bundle = $index_archive_class::getArchiveInfo(C\CRAWL_DIR .
            '/cache/' . $base_name . $this->crawl_time);
        $index_archive_info = unserialize($info_bundle['DESCRIPTION']);
        $crawl_status['COUNT'] = $info_bundle['COUNT'];
        $now = time();
        $change_in_time = C\ONE_HOUR + 1;
        while (count($this->hourly_crawl_data) > 0 &&
            $change_in_time > C\ONE_HOUR) {
            $least_recent_hourly_pair = array_pop($this->hourly_crawl_data);
            $change_in_time =
                ($now - $least_recent_hourly_pair[0]);
        }
        if ($change_in_time <= C\ONE_HOUR) {
            $this->hourly_crawl_data[] = $least_recent_hourly_pair;
        }
        array_unshift($this->hourly_crawl_data,
            [$now, $info_bundle['VISITED_URLS_COUNT']]);
        $crawl_status['VISITED_COUNT_HISTORY'] = $this->hourly_crawl_data;
        $crawl_status['VISITED_URLS_COUNT'] =
            $info_bundle['VISITED_URLS_COUNT'];
        $crawl_status['DESCRIPTION'] = $index_archive_info['DESCRIPTION'] ?? "";
        $crawl_status['QUEUE_PEAK_MEMORY'] = memory_get_peak_usage();
        file_put_contents($stat_file, serialize($crawl_status), LOCK_EX);
        chmod($stat_file, 0777);
        L\crawlLog("End checking for new URLs data memory usage: " .
            memory_get_usage());
        L\crawlLog("The current crawl description is: ".
                $index_archive_info['DESCRIPTION']) ?? "empty";
        L\crawlLog("Number of unique pages so far: ".
            $info_bundle['VISITED_URLS_COUNT']);
        L\crawlLog("Total urls extracted so far: " . $info_bundle['COUNT']);
        if (isset($sites[self::RECENT_URLS])) {
            L\crawlLog("Of these, the most recent urls are:");
            set_error_handler(null);
            foreach ($sites[self::RECENT_URLS] as $url) {
                L\crawlLog("URL: " .
                @iconv("UTF-8", "ISO-8859-1//IGNORE", $url));
            }
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        }
    }
    /**
     * Used to create encode a string representing with meta info for
     * a fetcher schedule.
     *
     * @param int $schedule_time timestamp of the schedule
     * @return string base64 encoded meta info
     */
    public function calculateScheduleMetaInfo($schedule_time)
    {
        //notice does not contain self::QUEUE_SERVERS
        $sites = [];
        $sites[self::CRAWL_TIME] = $this->crawl_time;
        $sites[self::SCHEDULE_TIME] = $schedule_time;
        $sites[self::CRAWL_ORDER] = $this->crawl_order;
        $sites[self::MAX_DEPTH] = $this->max_depth;
        $sites[self::REPEAT_TYPE] = $this->repeat_type;
        $sites[self::ROBOTS_TXT] = $this->robots_txt;
        $sites[self::CRAWL_TYPE] = $this->crawl_type;
        $sites[self::CRAWL_INDEX] = $this->crawl_index;
        $sites[self::CACHE_PAGES] = $this->cache_pages;
        $sites[self::PAGE_RULES] = $this->page_rules;
        $sites[self::RESTRICT_SITES_BY_URL] = $this->restrict_sites_by_url;
        $sites[self::INDEXED_FILE_TYPES] = $this->indexed_file_types;
        $sites[self::ALLOWED_SITES] = $this->allowed_sites;
        $sites[self::DISALLOWED_SITES] = $this->disallowed_sites;
        $sites[self::INDEXING_PLUGINS] =  $this->indexing_plugins;
        $sites[self::INDEXING_PLUGINS_DATA] =  $this->indexing_plugins_data;
        $sites[self::PAGE_RANGE_REQUEST] = $this->page_range_request;
        $sites[self::MAX_DESCRIPTION_LEN] = $this->max_description_len;
        $sites[self::MAX_LINKS_TO_EXTRACT] = $this->max_links_to_extract;
        $sites[self::POST_MAX_SIZE] = L\metricToInt(ini_get("post_max_size"));
        $sites[self::SITES] = [];
        return base64_encode(serialize($sites)) . "\n";
    }
    /**
     * Produces a schedule.txt file of url data for a fetcher to crawl next.
     *
     * The hard part of scheduling is to make sure that the overall crawl
     * process obeys robots.txt files. This involves checking the url is in
     * an allowed path for that host and it also involves making sure the
     * Crawl-delay directive is respected. The first fetcher that contacts the
     * server requesting data to crawl will get the schedule.txt
     * produced by produceFetchBatch() at which point it will be unlinked
     * (these latter thing are controlled in FetchController).
     *
     * @see FetchController
     */
    public function produceFetchBatch()
    {
        $i = 1; // array implementation of priority queue starts at 1 not 0
        $fetch_size = 0;
        L\crawlLog("FB Scheduler: Start Produce Fetch Batch.");
        L\crawlLog("FB Crawl Time is: ". $this->crawl_time);
        L\crawlLog("FB Memory usage is " . memory_get_usage() );
        $count = $this->web_queue->to_crawl_queue->count;
        $schedule_time = time();
        $first_line = $this->calculateScheduleMetaInfo($schedule_time);
        $sites = [];
        $delete_urls = [];
        $crawl_delay_hosts = [];
        $time_per_request_guess = C\MINIMUM_FETCH_LOOP_TIME ;
            // it would be impressive if we can achieve this speed
        $current_crawl_index = -1;
        L\crawlLog("FB Scheduler: Trying to Produce Fetch Batch; " .
            "Queue Size $count");
        $start_time = microtime(true);
        $fh = $this->web_queue->openUrlArchive();
        /*
            $delete - array of items we will delete from the queue after
                we have selected all of the items for fetch batch
            $sites - array of urls for fetch batch indices in this array we'll
                call slots. Crawled-delayed host urls are spaced by a certain
                number of slots
        */
        $num_waiting_urls = 0;
        $max_links = max(C\MAX_LINKS_TO_EXTRACT, $this->max_links_to_extract);
        $max_queue_size =  C\NUM_URLS_QUEUE_RAM -
            C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER * $max_links;
        $examined_count = 0;
        $num_without_robots = 0;
        while ($i <= $count && $fetch_size < C\MAX_FETCH_SIZE) {
            $examined_count = $i;
            L\crawlTimeoutLog("FB..Scheduler: still producing fetch batch. ".
                "Examining location %s in queue of %s.", $i, $count);
            //look in queue for url and its weight
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            // if queue error remove entry any loop
            if ($tmp === false || strcmp($url, "LOOKUP ERROR") == 0) {
                $delete_urls[$i] = false;
                L\crawlLog("FB Scheduler: Removing lookup error at".
                    " $i during produce fetch");
                $i++;
                continue;
            }
            $no_flags = false;
            $hard_coded = false;
            $host_url = UrlParser::getHost($url);
            if ($flag ==  WebQueueBundle::NO_FLAGS) {
                $hard_coded_pos = strpos($url, "###!");
                if ($hard_coded_pos > 0 ) {
                    $has_robots = true;
                    $hard_coded = true;
                    $is_robot = false;
                } else {
                    $has_robots =
                        $this->web_queue->containsGotRobotTxt($host_url);
                    $scheme = UrlParser::getScheme($host_url);
                    if ($scheme == "gopher") {
                        $is_robot =
                            (strcmp($host_url . "/0/robots.txt", $url) == 0);
                    } else {
                        $is_robot =
                            (strcmp($host_url . "/robots.txt", $url) == 0);
                    }
                }
                $no_flags = true;
            } else {
                $is_robot = ($flag == WebQueueBundle::ROBOT);
                if ($flag >= WebQueueBundle::SCHEDULABLE) {
                    $has_robots = true;
                    if ($flag > WebQueueBundle::SCHEDULABLE) {
                        $delay = $flag - WebQueueBundle::SCHEDULABLE;
                    }
                }
            }
            //if $url is a robots.txt url see if we need to schedule or not
            if ($is_robot) {
                if ($has_robots) {
                    $delete_urls[$i] = $url;
                    $i++;
                } else {
                    $next_slot = $this->getEarliestSlot($current_crawl_index,
                        $sites);
                    if ($next_slot < C\MAX_FETCH_SIZE) {
                        /* note don't add to seen url filter
                           since check robots every 24 hours as needed
                         */
                        $sites[$next_slot] = [$url, $weight, 0];
                        $current_crawl_index++;
                        $delete_urls[$i] = $url;
                        $fetch_size++;
                        $i++;
                    } else { //no more available slots so prepare to bail
                        $i = $count;
                        if ($no_flags) {
                            $this->web_queue->setQueueFlag($url,
                                WebQueueBundle::ROBOT);
                        }
                    }
                }
                continue;
            }
            //Now handle the non-robots.txt url case
            $robots_okay = true;
            if (!$has_robots) {
                $num_without_robots++;
                $i++;
                continue;
            }
            if ($no_flags) {
                if ($this->robots_txt == C\IGNORE_ROBOTS ||
                    ($this->robots_txt == C\ALLOW_LANDING_ROBOTS &&
                    rtrim($url, "/") == rtrim($host_url, "/"))) {
                    $robots_okay = true;
                } else if (!isset($hard_coded) || !$hard_coded) {
                    $robots_okay = $this->web_queue->checkRobotOkay($url);
                } else {
                    $robots_okay = true;
                }
                if (!$this->allowedToCrawlSite($url) ||
                    $this->disallowedToCrawlSite($url)) {
                    /* This is checked when added to queue,
                       we check again here in case allowed and disallowed
                       sites have changed since then
                     */
                    $robots_okay = false;
                }
                if (!$robots_okay) {
                    $delete_urls[$i] = $url;
                    $this->web_queue->addSeenUrlFilter($url);
                    $i++;
                    continue;
                }
                $delay = $this->web_queue->getCrawlDelay($host_url);
                if ($delay <= 0) {
                    /* if company level domain delays, then we're delayed
                        as well
                     */
                    $cld = UrlParser::getCompanyLevelDomain($host_url);
                    $cld_host = UrlParser::getScheme($host_url) .
                        "://" . $cld;
                    $delay = $this->web_queue->getCrawlDelay($cld_host);
                }
            }
            if (!$this->withinQuota($url)) {
                //we're not allowed to schedule $url till next hour
                $delete_urls[$i] = $url;
                //delete from queue (so no clog) but don't mark seen
                $i++;
                continue;
            }
            //each host has two entries in $this->waiting_hosts
            $num_waiting = floor(count($this->waiting_hosts)/2);
            if ($delay > 0) {
                // handle adding a url if there is a crawl delay
                $hash_host = L\crawlHash($host_url);
                $is_waiting_host = isset($this->waiting_hosts[$hash_host]);
                /*
                  To ensure that crawl-delay isn't violated by two separate
                  fetchers crawling the same host, if a host has a crawl
                  delay we only let it appear in one outstanding schedule
                  at a time. When data appears back from the fetcher handling
                  a crawl-delayed host, we'll clear it to appear in another
                  schedule
                 */
                if ((!$is_waiting_host
                    && $num_waiting < C\MAX_WAITING_HOSTS) ||
                    $is_waiting_host && $this->waiting_hosts[$hash_host] ==
                    $schedule_time) {
                    $this->waiting_hosts[$hash_host] =
                       $schedule_time;
                    $this->waiting_hosts[$schedule_time][] =
                        $hash_host;
                    $request_batches_per_delay =
                        ceil($delay/$time_per_request_guess);
                    if (!isset($crawl_delay_hosts[$hash_host])) {
                        $next_earliest_slot = $current_crawl_index;
                        $crawl_delay_hosts[$hash_host] = $next_earliest_slot;
                    } else {
                        $next_earliest_slot = $crawl_delay_hosts[$hash_host]
                            + $request_batches_per_delay
                            * C\NUM_MULTI_CURL_PAGES;
                    }
                    if (($next_slot =
                        $this->getEarliestSlot($next_earliest_slot,
                            $sites)) < C\MAX_FETCH_SIZE) {
                        $crawl_delay_hosts[$hash_host] = $next_slot;
                        $delete_urls[$i] = $url;
                        $sites[$next_slot] = [$url, $weight, $delay];
                        $this->web_queue->addSeenUrlFilter($url);
                        /* we might miss some sites by marking them
                           seen after only scheduling them
                         */
                        $fetch_size++;
                    } else {
                        if ($num_waiting_urls <
                            C\WAITING_URL_FRACTION * $max_queue_size) {
                            $num_waiting_urls++;
                            if ($no_flags) {
                                $this->web_queue->setQueueFlag($url,
                                    $delay + WebQueueBundle::SCHEDULABLE);
                            }
                        } else {
                            // has crawl delay but too many already waiting
                            $delete_urls[$i] = $url;
                        }
                    }
                } else if (!$is_waiting_host) {
                    // has crawl delay but too many already waiting
                    $delete_urls[$i] = $url;
                    //delete from queue (so no clog) but don't mark seen
                }
            } else { // add a url no crawl delay
                $next_slot = $this->getEarliestSlot($current_crawl_index,
                    $sites);
                if ($next_slot < C\MAX_FETCH_SIZE) {
                    $sites[$next_slot] = [$url, $weight, 0];
                    $delete_urls[$i] = $url;
                    $this->web_queue->addSeenUrlFilter($url);
                        /* we might miss some sites by marking them
                           seen after only scheduling them
                         */
                    $current_crawl_index++;
                    $fetch_size++;
                } else { //no more available slots so prepare to bail
                    $i = $count;
                    if ($no_flags) {
                        $this->web_queue->setQueueFlag($url,
                            WebQueueBundle::SCHEDULABLE);
                    }
                }
            } //no crawl-delay else
            $i++;
        } //end while
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime(true);
        L\crawlLog("FB...Scheduler: Done selecting URLS for fetch batch time ".
            "so far:". L\changeInMicrotime($start_time));
        L\crawlLog("FB...Scheduler: Examined urls while making fetch batch:" .
            $examined_count);
        L\crawlLog("FB...Scheduler: Examined urls without robots.txt yet so ".
            "could not schedule:" .
            $num_without_robots);
        L\crawlLog("FB...Scheduler: Number of crawl-delayed waiting urls ".
            "seen in queue:" . $num_waiting_urls);
        $num_deletes = count($delete_urls);
        $k = 0;
        foreach ($delete_urls as $delete_url) {
            $k++;
            L\crawlTimeoutLog("FB..Scheduler: Removing selected url %s of %s ".
                "from queue.", $k, $num_deletes);
            if ($delete_url) {
                $this->web_queue->removeQueue($delete_url);
            } else {
                /*  if there was a hash table look up error still get rid of
                    index from priority queue */
                $this->web_queue->to_crawl_queue->poll($k);
            }
        }
        L\crawlLog("FB...Scheduler: Removed $k URLS for fetch batch from ".
            "queue in time: " . L\changeInMicrotime($new_time));
        $new_time = microtime(true);
        if (isset($sites) && count($sites) > 0 ) {
            $dummy_slot = [self::DUMMY, 0.0, 0];
            /* dummy's are used for crawl delays of sites with longer delays
               when we don't have much else to crawl.
             */
            $cnt = 0;
            for ($j = 0; $j < C\MAX_FETCH_SIZE; $j++) {
                if (isset( $sites[$j])) {
                    $cnt++;
                    if ($cnt == $fetch_size) {
                        break;
                    }
                } else {
                    if ($j % C\NUM_MULTI_CURL_PAGES == 0) {
                        $sites[$j] = $dummy_slot;
                    }
                }
            }
            ksort($sites);
            //write schedule to disk
            $fh = fopen(C\CRAWL_DIR . "/schedules/" .
                self::schedule_name . $this->crawl_time . ".txt", "wb");
            fwrite($fh, $first_line);
            $num_sites = count($sites);
            $k = 0;
            foreach ($sites as $site) {
                L\crawlTimeoutLog(
                    "FB..Scheduler: Still Writing fetch schedule %s of %s.",
                    $k, $num_sites);
                $k++;
                $extracted_etag = null;
                list($url, $weight, $delay) = $site;
                $key = L\crawlHash($url, true);
                if (C\USE_ETAG_EXPIRES) {
                /*check if we have cache validation data for a URL. If both
                  ETag and Expires timestamp are found or only an expires
                  timestamp is found, the timestamp is compared with the current
                  time. If the current time is less than the expires timestamp,
                  the URL is not added to the fetch batch. If only an ETag is
                  found, the ETag is appended to the URL so that it can be
                  processed by the fetcher.
                 */
                    $value = $this->web_queue->etag_btree->findValue($key);
                    if ($value !== null) {
                        $cache_validation_data = $value[1];
                        if ($cache_validation_data['etag'] !== -1 &&
                            $cache_validation_data['expires'] !== -1) {
                            $expires_timestamp =
                                $cache_validation_data['expires'];
                            $current_time = time();
                            if ($current_time < $expires_timestamp) {
                                continue;
                            } else {
                                $etag = $cache_validation_data['etag'];
                                $extracted_etag = "ETag: " . $etag;
                            }
                        } else if ($cache_validation_data['etag'] !== -1) {
                            $etag = $cache_validation_data['etag'];
                            $extracted_etag = "ETag: " . $etag;
                        } else if ($cache_validation_data['expires'] !== -1) {
                            $expires_timestamp =
                                $cache_validation_data['expires'];
                            $current_time = time();
                            if ($current_time < $expires_timestamp) {
                                continue;
                            }
                        }
                    }
                }
                $host_url = UrlParser::getHost($url);
                $dns_lookup = $this->web_queue->dnsLookup($host_url);
                if ($dns_lookup) {
                    $url .= "###" . urlencode($dns_lookup);
                }
                if ($extracted_etag !== null) {
                    $url .= $extracted_etag;
                }
                $out_string = base64_encode(
                    L\packFloat($weight) . L\packInt($delay).$url) . "\n";
                fwrite($fh, $out_string);
            }
            fclose($fh);
            L\crawlLog("FB...Scheduler: Sort URLS and write schedule time: " .
                L\changeInMicrotime($new_time));
            L\crawlLog("FB Scheduler: End Produce Fetch Batch Memory usage: " .
                memory_get_usage() );
            L\crawlLog("FB Scheduler: Created fetch batch of size $num_sites." .
                " $num_deletes urls were deleted.".
                " Queue size is now " . $this->web_queue->to_crawl_queue->count.
                "...Total Time to create batch: ".
                L\changeInMicrotime($start_time));
        } else {
            L\crawlLog("FB Scheduler: No fetch batch created!! " .
                "Time failing to make a fetch batch:" .
                L\changeInMicrotime($start_time)." . Loop properties:$i $count".
                " $num_deletes urls were deleted in failed attempt.");
            $max_links = max(C\MAX_LINKS_TO_EXTRACT,
                $this->max_links_to_extract);
            if ($num_deletes < 5 && $i >= $count &&
                    $count >= C\NUM_URLS_QUEUE_RAM -
                    C\SEEN_URLS_BEFORE_UPDATE_SCHEDULER * $max_links) {
                L\crawlLog("FB Scheduler: Queue Full and Couldn't produce ".
                    "Fetch Batch!! Or Delete any URLS!!!");
                L\crawlLog("FB Scheduler: Rescheduling Queue Contents ".
                    "(not marking seen) to try to unjam!");
                $this->dumpQueueToSchedules(true);
                $this->clearWebQueue();
            }
        }
    }
    /**
     * Gets the first unfilled schedule slot after $index in $arr
     *
     * A schedule of sites for a fetcher to crawl consists of MAX_FETCH_SIZE
     * many slots earch of which could eventually hold url information.
     * This function is used to schedule slots for crawl-delayed host.
     *
     * @param int $index location to begin searching for an empty slot
     * @param array &$arr list of slots to look in
     * @return int index of first available slot
     */
    public function getEarliestSlot($index, &$arr)
    {
        $cnt = count($arr);
        for ( $i = $index + 1; $i < $cnt; $i++) {
            if (!isset($arr[$i] ) ) {
                break;
            }
        }
        return $i;
    }
    /**
     * Used to remove from the queue urls that are no longer crawlable
     * because the allowed and disallowed sites have changed.
     */
    public function cullNoncrawlableSites()
    {
        $count = $this->web_queue->to_crawl_queue->count;
        L\crawlLog("Scheduler: ".
            " Culling noncrawlable urls after change in crawl parameters;".
            " Queue Size $count");
        $start_time = microtime(true);
        $fh = $this->web_queue->openUrlArchive();
        $delete_urls = [];
        $i = 1;
        while($i < $count) {
            L\crawlTimeoutLog("..Scheduler: ".
                "still culling noncrawlable urls. Examining ".
                "location %s in queue of %s.", $i, $count);
            $tmp = $this->web_queue->peekQueue($i, $fh);
            list($url, $weight, $flag, $probe) = $tmp;
            if (!$this->allowedToCrawlSite($url) ||
                $this->disallowedToCrawlSite($url)) {
                $delete_urls[] = $url;
            }
            $i++;
        }
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime(true);
        L\crawlLog("...Scheduler: Done selecting cullable URLS, time so far:".
            L\changeInMicrotime($start_time));
        $this->web_queue->closeUrlArchive($fh);
        $new_time = microtime(true);
        $num_deletes = count($delete_urls);
        $k = 0;
        foreach ($delete_urls as $delete_url) {
            $k++;
            L\crawlTimeoutLog("..Scheduler: Removing selected url %s of %s ".
                "from queue.", $k, $num_deletes);
            if ($delete_url) {
                $this->web_queue->removeQueue($delete_url);
            } else {
                /*  if there was a hash table look up error still get rid of
                    index from priority queue */
                $this->web_queue->to_crawl_queue->poll($k);
            }
        }
        L\crawlLog("...Scheduler: Removed $k cullable URLS  ".
            "from queue in time: ". L\changeInMicrotime($new_time));
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
        // try to eliminate spam sites, but still crawl onion urls
        $tld = "";
        if ($host = parse_url($url, \PHP_URL_HOST)) {
            $host_parts = explode(".", $host);
            $tld = $host_parts[count($host_parts) - 1];
        }
        if (!C\nsdefined("KEEP_SPAM_DOMAINS") && $tld != 'onion') {
            $consonants = "bcdfghjklmnpqrstvwxyz";
            $vowels = "aeiouy";
            $host = @parse_url($url, PHP_URL_HOST);
            $port = UrlParser::getPort($url);
            $digit_consonants = "([$consonants]|\d)";
            $digit_vowels = "([$vowels]|\d)";
            if (preg_match("/($digit_consonants{5}|$digit_vowels{5})|" .
                "(($digit_consonants{4}|$digit_vowels{4})" .
                ".+\.(cn|tv|cc|se|tw|ru|in|xyz))/",
                $host)) {
                return false;
            }
            if (in_array(substr($host, -3), [".cn", ".tw", ".ru", ".in",
                "xyz"]) && !in_array($port, [80, 443])) {
                return false;
            }
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
     * Checks if the $url is from a site which has an hourly quota to download.
     * If so, it bumps the quota count and return true; false otherwise.
     * This method also resets the quota queue every over
     *
     * @param string $url to check if within quota
     * @param int $bump_count how much to bump quota count if url is from a
     *      site with a quota
     * @return bool whether $url exceeds the hourly quota of the site it is from
     */
    public function withinQuota($url, $bump_count = 1)
    {
        if (!($site = UrlParser::urlMemberSiteArray(
            $url, $this->quota_sites_keys,
            "q" . $this->allow_disallow_cache_time, true))) {
            return true;
        }
        list($quota, $current_count) = $this->quota_sites[$site];
        if ($current_count < $quota) {
            $this->quota_sites[$site] = [$quota, $current_count + $bump_count];
            $flag = true;
        } else {
            L\crawlLog("Quota exceeded removing " .
                "url:$url Quota Site:$site Count:$current_count Quota:$quota");
            $flag = false;
        }
        if ($this->quota_clear_time + C\ONE_HOUR < time()) {
            $this->quota_clear_time = time();
            foreach ($this->quota_sites as $site => $info) {
                list($quota,) = $info;
                $this->quota_sites[$site] = [$quota, 0];
            }
        }
        return $flag;
    }
}
if (!C\nsdefined("UNIT_TEST_MODE")) {
    /*
     * Instantiate and runs the QueueSever
     */
    $queue_server =  new QueueServer();
    $queue_server->start();
}
