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
 * @author Chris Pollett
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Used to make using Yioop through this index page rather than as part of
 * a library
 */
define("seekquarry\\yioop\\BASE_INDEX_ENTRY", true);
/**
 * This will in turn load Config.php
 */
require_once __DIR__ . "/src/library/Utility.php";
/**
 * Sends a request from the outer Yioop index.php file on to the inner one
 * Marks that redirects are on
 */
function passthruYioopRequest()
{
    $web_site = $GLOBALS['web_site'];
    $uri = $_SERVER['REQUEST_URI'];
    $new_uri = preg_replace("@/(\.?/)+@", "/", $uri);
    if (!empty($web_site) && $new_uri != $uri) {
        $web_site->header("Location: $new_uri");
        L\webExit();
    }
    require_once __DIR__ . "/src/index.php";
    bootstrap($web_site);
    return true;
}
/**
 * Used to process the command line arguments to yioop when run in CLI
 * mode as its own web server
 */
function processCommandLine()
{
    global $argv;
    define("seekquarry\\yioop\\configs\\IS_OWN_WEB_SERVER", true);
    $address = 8080;
    $sleep = 0;
    $session_file = "";
    if (empty($argv[1])) {
        $argv[1] = 'start';
    } else {
        if ($argv[1] == 'stop') {
            return;
        }
        $possible_address = $argv[1];
        $possible_sleep = 0;
        if (empty($argv[2])) {
            if (in_array($argv[1], ['start', 'terminal', 'child'])) {
                $possible_address = 8080;
            } else {
                $argv[1] = 'start';
            }
        } else {
            $possible_sleep = $argv[2];
            if (in_array($argv[1], ['start', 'terminal', 'child'])) {
                $possible_address = $argv[2];
                if (empty($argv[3])) {
                    $possible_sleep = 0;
                    if ($argv[2] == 'none') {
                        $possible_address = 8080;
                    }
                } else {
                    $possible_sleep = $argv[3];
                    if ($argv[2] == 'none') {
                        $possible_address = $argv[3];
                        if (empty($argv[4])) {
                            $possible_sleep = 0;
                        } else {
                            $possible_sleep = $argv[4];
                        }
                        if (!empty($argv[5])) {
                            $session_file = $argv[5];
                        }
                    } else if (!empty($argv[4])) {
                        $session_file = $argv[4];
                    }
                }
            } else if (!empty($argv[3])) {
                if (intval($possible_sleep) > 0) {
                    $argv[1] = 'restart';
                } else {
                    $argv[1] = 'start';
                }
                $session_file = $argv[3];
            }
        }
        $tmp_address = intval($possible_address);
        if ($tmp_address > 0 && $tmp_address < 65535) {
            $address = $possible_address;
        } else {
            $url_parts = @parse_url($possible_address);
            if(!empty($url_parts)) {
                $address = $possible_address;
            }
        }
        if (intval($possible_sleep) > 0 ) {
            $sleep = $possible_sleep;
        }
    }
    $argv[2] = 'none';
    $argv[3] = $address;
    $argv[4] = $sleep;
    $argv[5] = $session_file;
}
/**
 * Creates a  website object and specify some common routes.
 * If run in CLI mode this will start the server linstening for connect
 * web connections
 */
$web_site = new L\WebSite('.');
$web_site->get('/*', 'seekquarry\\yioop\\passthruYioopRequest');
$web_site->post('/*', 'seekquarry\\yioop\\passthruYioopRequest');
if ($web_site->isCli()) {
    $web_site->setTimer(10, function () use ($web_site) {
        if (!C\PROFILE) {
            return;
        }
        static $start_time = 0;
        $global_stop_file = C\WORK_DIRECTORY . "/data/global_stop.txt";
        if ($start_time == 0) {
            $start_time = time();
        }
        if (file_exists($global_stop_file)) {
            $stop_time = intval(file_get_contents($global_stop_file));
            if ($stop_time > $start_time) {
                echo "Stopping Server...";
                $web_site->stop = true;
                $web_site->restart = false;
            } else {
                unlink($global_stop_file);
            }
        }
        $log_msg = "Current Memory Usage: ".memory_get_usage(). " Peak usage:" .
            memory_get_peak_usage();
        L\crawlLog($log_msg);
    });
    if (file_exists(C\LOG_DIR)) {
        // don't use logging until after log directory built on first run
        $web_site->middleware(function () use ($web_site)
        {
            $lock_file = L\CrawlDaemon::getLockFileName("index", '');
            /*  in first run case, lock file won't be created by outer
                instance as folder not created yet
             */
            if (!file_exists($lock_file)) {
                file_put_contents($lock_file,  time());
            }
            $log_msg = $_SERVER['REMOTE_ADDR'] . " " .$_SERVER['REQUEST_URI'];
            L\crawlLog($log_msg);
        });
    }
    $session_info = null;
    if (!empty($argv[5]) && file_exists($argv[5])) {
        $session_info = unserialize(file_get_contents($argv[5]));
        if ($argv[1] == 'restart') {
            if(!empty($session_info['SESSIONS']['TERMINAL'])) {
                $argv[1] = 'start';
            } else {
                $argv[1] = 'child';
            }
        }
    }
    L\CrawlDaemon::init($argv, "index");
    L\crawlLog("\n\nInitialize logger..", "index", true);
    if ($argv[4] > 0) {
        sleep($argv[4]);
    }
    if (empty($session_info)) {
        $session_info = ['SESSIONS' => [], 'SESSION_QUEUE' => []];
    }
    if ($argv[1] == 'terminal') {
        $session_info['SESSIONS']['TERMINAL'] = true;
    }
    $context = [];
    if (C\nsdefined("SERVER_CONTEXT")) {
        $context['SERVER_CONTEXT'] = C\SERVER_CONTEXT;
    }
    $context['SESSION_INFO'] = $session_info;
    $web_site->listen($argv[3], $context);
} else {
    $web_site->process();
}
