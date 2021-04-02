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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;

/**
 * This class handles requests from a computer that is managing several
 * fetchers and queue_servers. This controller might be used to start, stop
 * fetchers/queue_server as well as get status on the active fetchers
 *
 * @author Chris Pollett
 */
class MachineController extends Controller implements CrawlConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    public $activities = ["statuses", "update", "log"];
    /**
     * Number of characters from end of most recent log file to return
     * on a log request
     */
    const LOG_LISTING_LEN = 200000;
    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     *
     */
    public function processRequest()
    {
        $data = [];
        /* do a quick test to see if this is a request seems like
           from a legitimate machine
         */
        if (!$this->checkRequest()) {
            return;
        }
        $activity = $_REQUEST['a'];
        if (in_array($activity, $this->activities)) {
            $this->call($activity);
        }
    }
    /**
     * Checks the running/non-running status of the
     * fetchers and queue_servers of the current Yioop instance
     */
    public function statuses()
    {
        if (isset($_REQUEST["arg"])) {
            /* This method is typically invoked based on a name server
               request containing info about what machine this is
               (@see  MachineModel::getMachineStatuses($machines = [])).
               Here we store the info about what machine we are for local
               use.
             */
            $hash_url = $this->clean($_REQUEST["arg"], "string");
            // the next file tells the MediaUpdater what machine it is
            $this->web_site->filePutContents(C\WORK_DIRECTORY .
                "/schedules/current_machine_info.txt",
                $hash_url);
        }
        $this->web_site->header("Content-Type: application/json");
        echo json_encode(CrawlDaemon::statuses());
    }
    /**
     * Used to start/stop a queue_server/fetcher of the current Yioop instance
     * based on the queue_server and fetcher fields of the current $_REQUEST
     */
    public function update()
    {
        if (!isset($_REQUEST['type']) || !isset($_REQUEST['id']) ||
            !isset($_REQUEST['action']) ) {
                return;
        }
        $statuses = CrawlDaemon::statuses();
        $channel = empty($_REQUEST['channel']) ? 0 :
            $this->clean($_REQUEST['channel'], "int");
        switch ($_REQUEST['type']) {
            case 'QueueServer':
                if ($_REQUEST['action'] == "start" &&
                    !isset($statuses["$channel-QueueServer"][-1])) {
                    CrawlDaemon::start("QueueServer", "$channel",
                        self::INDEXER, 0);
                    CrawlDaemon::start("QueueServer", "$channel",
                        self::SCHEDULER, 2);
                } else if ($_REQUEST['action'] == "stop" &&
                    isset($statuses["QueueServer"][$channel]) ) {
                    CrawlDaemon::stop("QueueServer", "$channel");
                }
                break;
            case 'Mirror':
                if ($_REQUEST['action'] == "start" &&
                    !isset($statuses["Mirror"][-1])) {
                    $parent = (isset($_REQUEST['parent'])) ?
                        $this->clean($_REQUEST['parent'], 'string') : "";
                    if ($parent) {
                        $this->web_site->filePutContents(C\CRAWL_DIR .
                            "/schedules/mirror_parent.txt",
                            L\webdecode($parent));
                    }
                    CrawlDaemon::start("Mirror");
                } else if ($_REQUEST['action'] == "stop" &&
                    isset($statuses["Mirror"][-1]) ) {
                    CrawlDaemon::stop("Mirror");
                }
                break;
            case 'MediaUpdater':
                if ($_REQUEST['action'] == "start" &&
                    !isset($statuses["MediaUpdater"][-1])) {
                    CrawlDaemon::start("MediaUpdater");
                } else if ($_REQUEST["action"] == "stop" &&
                    isset($statuses["MediaUpdater"][-1]) ) {
                    CrawlDaemon::stop("MediaUpdater");
                }
                break;
            case 'Fetcher':
                $id = $_REQUEST['id'];
                if ($_REQUEST['action'] == "start" &&
                    !isset($statuses["$channel-Fetcher"][$id ]) ) {
                    CrawlDaemon::start("Fetcher", "$id-$channel", "$channel");
                } else if ($_REQUEST['action'] == "stop" &&
                    isset($statuses["$channel-Fetcher"][$id]) ) {
                    CrawlDaemon::stop("Fetcher", "$id-$channel");
                }
                break;
            case 'RestartFetcher':
                $error_log = C\CRASH_LOG_NAME;
                $id = $_REQUEST['id'];
                $msg = "Restarting $channel-Fetcher $id";
                $time_string = date("r", time());
                $out_msg = "[$time_string] $msg\n";
                $lines = L\tail(C\LOG_DIR . "/$id-$channel-Fetcher.log", 10);
                foreach ($lines as $line) {
                    $out_msg .= "!!!!$line\n";
                }
                if (!file_exists($error_log) || filesize($error_log) >
                    C\MAX_LOG_FILE_SIZE) {
                    /* use file_put_contents as filePutContetns doesn't
                       support FILE_APPEND
                     */
                    file_put_contents($error_log, $out_msg);
                } else {
                    file_put_contents($error_log, $out_msg, FILE_APPEND);
                }
                CrawlDaemon::start("Fetcher", "$id-$channel", "$channel");
                break;
        }
    }
    /**
     * Used to retrieve a fetcher/queue_server logfile for the the current
     * Yioop instance
     */
    public function log()
    {
        $log_data = "";
        if (!isset($_REQUEST["type"])) {
            echo json_encode(urlencode($log_data));
            return;
        }
        $channel = (empty($_REQUEST["channel"])) ? 0 :
            $this->clean($_REQUEST["channel"], "int");
        switch ($_REQUEST["type"]) {
            case "Fetcher":
                $fetcher_num = $this->clean($_REQUEST["id"], "int");
                $log_file_name = C\LOG_DIR .
                    "/{$fetcher_num}-{$channel}-Fetcher.log";
                break;
            case "MediaUpdater":
            case "Mirror":
                $log_file_name = C\LOG_DIR . "/" . $_REQUEST["type"].".log";
                break;
            case "QueueServer":
                $log_file_name = C\LOG_DIR . "/$channel-".$_REQUEST["type"].
                    ".log";
                break;
        }
        $filter = "";
        if (isset($_REQUEST["f"])) {
            $filter = $this->clean($_REQUEST["f"], "string");
        }
        if (file_exists($log_file_name)) {
            $size = filesize($log_file_name);
            $len = min(self::LOG_LISTING_LEN, $size);
            $fh = fopen($log_file_name, "r");
            if ($fh) {
                fseek($fh, $size - $len);
                $log_data = fread($fh, $len);
                fclose($fh);
            }
            if (!empty($filter) && strlen($log_data) > 0) {
                $quote_filter = preg_quote($filter);
                if(preg_match_all('/.*' . $quote_filter . '.*\n/ui', $log_data,
                    $matches)) {
                    $log_data = implode("", ($matches[0] ?? []) );
                } else {
                    $log_data = "";
                }
            }
            if (empty($log_data)) {
                $log_data = tl('machine_controller_nolines') . "\n";
            }
        }
        $this->web_site->header("Content-Type: application/json");
        echo json_encode(L\webencode($log_data));
    }
}
