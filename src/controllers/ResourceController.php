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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\MediaConstants;
use seekquarry\yioop\library\processors\ImageProcessor;

/**
 * Used to serve resources, css, or scripts such as images from APP_DIR
 *
 * @author Chris Pollett
 */
class ResourceController extends Controller implements CrawlConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    public $activities = ["get", "syncList", "syncNotify", "suggest"];
    /**
     * Checks that the request seems to be coming from a legitimate fetcher
     * or mirror server then determines which activity  is being requested
     * and calls the method for that activity.
     *
     */
    public function processRequest()
    {
        if ((isset($_REQUEST['a']) && in_array(
            $_REQUEST['a'], ["get", "suggest"]))
            || $this->checkRequest()) {
            $activity = $_REQUEST['a'];
            if (in_array($activity, $this->activities)) {
                $this->call($activity);
                return;
            }
        }
        $this->requestError();
    }
    /**
     * Gets the resource $_REQUEST['n'] from APP_DIR/$_REQUEST['f'] or
     * CRAWL_DIR/$_REQUEST['f']  after cleaning
     */
    public function get()
    {
        if (!isset($_REQUEST['n']) || !(isset($_REQUEST['f']) ||
            isset($_REQUEST['b']))) {
            $this->requestError();
        }
        if (!empty($_REQUEST['b'])) {
            if (in_array($_REQUEST['b'], ["yioopbar.xml", "favicon.ico",
            "robots.txt"])) {
                $name = $_REQUEST['b'];
                $base_dir = C\BASE_DIR;
            } else if (in_array($_REQUEST['b'], ["css", "scripts",
                "resources"])) {
                list($name, $base_dir) = $this->getNameAndBaseFolder(true);
            } else if (in_array($_REQUEST['b'], ["locale"])) {
                $name = $this->clean($_REQUEST['n'], "file_name");
                $folder = $_REQUEST['b'];
                $base_dir = C\BASE_DIR . "/$folder";
            }
        } else if (!empty($_REQUEST['f'])) {
            if (in_array($_REQUEST['f'], ["css", "scripts", "resources"])) {
                list($name, $base_dir) = $this->getNameAndBaseFolder();
            } else if (in_array($_REQUEST['f'], ["cache"])) {
                /*  perform check since these request should come from a known
                    machine
                */
                if (!$this->checkRequest()) {
                    $this->requestError();
                }
                $name = $this->clean($_REQUEST['n'], "file_name");
                $folder = $_REQUEST['f'];
                $base_dir = C\CRAWL_DIR . "/$folder";
            } else if (in_array($_REQUEST['f'], ["locale"])) {
                $name = $this->clean($_REQUEST['n'], "file_name");
                $sub_path = "";
                if (!empty($_REQUEST['sf'])) {
                    $sub_path = $this->clean($_REQUEST['sf'], "string");
                    $sub_path = str_replace(".", "", $sub_path) . "/";
                    if ($sub_path == "/") {
                        $sub_path = "";
                    }
                }
                $folder = $_REQUEST['f'];
                $base_dir = C\APP_DIR . "/$folder";
                $name = $sub_path . $name;
            } else {
                $this->requestError();
                return;
            }
        } else {
            $this->requestError();
            return;
        }
        $allow_cache = true;
        if ((isset($_REQUEST['o']) && isset($_REQUEST['l'])) ) {
            $offset = $this->clean($_REQUEST['o'], "int");
            $limit = $this->clean($_REQUEST['l'], "int");
            $allow_cache = false;
        }
        $path = "$base_dir/$name";
        if (isset($_REQUEST['t']) && $_REQUEST['t'] == 'feed' &&
            !file_exists($path) && file_exists("$path.txt")) {
            $image_url = $this->web_site->fileGetContents("$path.txt");
            if (!empty($image_url)) {
                $image_page = FetchUrl::getPage($image_url);
                set_error_handler(null);
                $image = @imagecreatefromstring($image_page);
                set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                $thumb = ImageProcessor::createThumb($image);
                if (!empty($thumb)) {
                    $this->web_site->filePutContents($path, $thumb);
                }
            }
        }
        if (file_exists($path)) {
            $path = realpath($path);
            $mime_type = L\mimeType($path);
            $mime_parts = explode("/", $mime_type);
            $size = filesize($path);
            $start = 0;
            $end = $size - 1;
            if ($start = 0) {
                $end = 0;
            }
            $this->web_site->header("Content-type: $mime_type");
            $this->web_site->header('Content-Disposition: filename="' .
                $name . '"');
            $this->web_site->header("Accept-Ranges: bytes");
            if (isset($_SERVER['HTTP_RANGE'])) {
                $this->serveRangeRequest($path, $size, $start, $end);
                return;
            }
            if ($allow_cache) {
                $this->web_site->header("Cache-Control: public");
                if ($this->checkUnmodifiedAndProcess($path, $size)) {
                    return;
                }
            }
            $this->web_site->header("Content-Length: " . $size);
            $this->web_site->header("Content-Range: bytes $start-$end/$size");
            if (isset($offset) && isset($limit)) {
                // do not switch to fileGetContents
                echo file_get_contents($path, false, null, $offset, $limit);
            } else {
                $fh = fopen($path, "r");
                while(!feof($fh)) {
                    echo fread($fh, 4096);
                    flush();
                }
                fclose($fh);
            }
        } else {
            $this->requestError();
        }
    }
    /**
     * Returns the file system folder where resources are stored
     * making use of the n field for the name of the resource, its type,
     * the sf field describing the desired subfolder
     * and whether this is a request for a thumbnail or a object
     * @param bool $is_src_folder should we look in the base directory
     *  (src folder) or work_directory to try to find the resource
     * @return array ordered pair [path beneath base folder to file, basefolder]
     */
    public function getNameAndBaseFolder($is_src_folder = false)
    {
        $name = $this->clean($_REQUEST['n'] ?? "", "file_name");
        $type = UrlParser::getDocumentType($name);
        if (isset($_REQUEST['feed']) ||
            (!empty($_REQUEST['t']) && $_REQUEST['t'] == 'feed')) {
            $_REQUEST['t'] = 'feed';
            $type = "";
            unset($_REQUEST['p']);
        }
        /* notice in this case we didn't check if request come from a
           legitimate source but we do try to restrict it to being
           a file (not a folder) in the above array. If the request
           is for a file in resources, then if it is for a private
           group, we will check in checkAndLogViewGetBaseFolder
           if the request is legit
        */
        $base_dir = $this->checkAndLogViewGetBaseFolder($name, $is_src_folder);
        if (!$base_dir) {
            $this->requestError();
        }
        $name = urlencode($name);
        $name = UrlParser::getDocumentFilename($name);
        $name = urldecode($name);
        $name = ($type != "") ? "$name.$type" : $name;
        if (!$is_src_folder) {
            if (!empty($_REQUEST['t'])) {
                if ($_REQUEST['t'] == 'athumb') {
                    $name .= ".gif";
                } else {
                    $name .= ".jpg";
                }
            }
            $sub_path = "";
            if (!empty($_REQUEST['sf'])) {
                $sub_path = $this->clean($_REQUEST['sf'], "string");
                $sub_path = str_replace(".", "", $sub_path) . "/";
                if ($sub_path == "/") {
                    $sub_path = "";
                }
            }
            $name = $sub_path . $name;
        }
        return [$name, $base_dir];
    }
    /**
     * Handles requests that result in errors to this controller
     */
    public function requestError()
    {
        $error = B\directUrl("error", false, true);
        $error_location = "Location: $error";
        $this->web_site->header($error_location);
        return;
    }
    /**
     * Computes based on the request the folder that should be used to
     * find a file during a resource get request. It also checks if user
     * has access to the requested folder and file. Finally, if this is
     * for a logged in user, it records the view in the user's session.
     *
     * @param string $media_name being requested, only used for logging, not
     *     computing base folder
     * @return mixed either a string with the folder name in it or false if
     *      the user does not have access or that folder does not exist.
     */
    public function checkAndLogViewGetBaseFolder($media_name = "",
        $is_src_folder = false)
    {
        if ($is_src_folder) {
            $folder = empty($_REQUEST['b']) ? "" :
                $this->clean($_REQUEST['b'], 'string');
        } else {
            $folder = empty($_REQUEST['f']) ? "" :
                $this->clean($_REQUEST['f'], 'string');
        }
        $base_dir = ($is_src_folder) ? C\BASE_DIR . "/$folder" :
            C\APP_DIR . "/$folder";
        $add_to_path = false;
        $is_group_item = false;
        $page_id = "";
        if (!$is_src_folder && isset($_REQUEST['s'])&& !isset($_REQUEST['g']) &&
            $folder == "resources") {
            // handle sub-folders of resource (must be numeric)
            $subfolder = $this->clean($_REQUEST['s'], "hash");
            $prefix_folder = substr($subfolder, 0, 3);
            $add_to_path = true;
        } else if (!$is_src_folder && isset($_REQUEST['g'])) {
            $user_id = isset($_SESSION['USER_ID']) ? $_SESSION['USER_ID'] :
                C\PUBLIC_USER_ID;
            if (isset($_REQUEST['p'])) {
                $page_id = $this->clean($_REQUEST['p'], 'string');
            }
            $group_id = $this->clean($_REQUEST['g'], "int");
            $group_model = $this->model('group');
            $token_okay = true;
            $pre_token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user_id);
            if ($group_id == C\PUBLIC_GROUP_ID) {
                $user_id = C\PUBLIC_USER_ID;
            } else {
                $token_okay = $pre_token_okay;
                if (empty($_COOKIE) && stristr($_SERVER['HTTP_USER_AGENT'],
                    "Mobile") !== false && stristr($_SERVER['HTTP_USER_AGENT'],
                    "Safari") !== false) {
                    $this->web_site->header('HTTP/1.0 403 Forbidden');
                    //fixes mobile safari no send cookie bug
                    \seekquarry\yioop\library\webExit();
                }
            }
            $group = $group_model->getGroupById($group_id, $user_id);
            if (!$group || !$token_okay) {
                return false;
            }
            $sub_path = (empty($_REQUEST['sf'])) ? "" :
                $this->clean($_REQUEST['sf'], "string");
            $this->recordViewSession($page_id, $sub_path, $media_name);
            $prefix_word = (isset($_REQUEST['t'])) ? 't' : '';
            $base_subfolder = L\crawlHash(
                'group' . $group_id . $page_id . C\AUTH_KEY);
            $prefix_folder = substr($base_subfolder, 0, 3);
            $subfolder = $prefix_word . $base_subfolder;
            $add_to_path = true;
            $is_group_item = true;
        }
        if ($add_to_path) {
            if ($is_group_item) {
                $redirect_dir = "$base_dir/$prefix_folder/$base_subfolder";
            }
            if ($is_group_item &&
                file_exists($redirect_dir . "/redirect.txt")) {
                $tmp_path = $this->web_site->fileGetContents($redirect_dir .
                    "/redirect.txt");
                if (is_dir($tmp_path)) {
                    if ($subfolder == $base_subfolder) {
                        $base_dir = $tmp_path;
                    } else {
                        $subfolder = L\crawlHash($tmp_path);
                        $prefix_folder = substr($subfolder, 0, 3);
                        $subfolder = $prefix_word . $subfolder;
                        $base_dir .= "/$prefix_folder/$subfolder";
                    }
                }
            } else {
                $base_dir .= "/$prefix_folder/$subfolder";
            }
        }
        return $base_dir;
    }
    /**
     * Code to handle HTTP range requests of resources. This allows
     * HTTP pseudo-streaming of video. This code was inspired by:
     * http://www.tuxxin.com/php-mp4-streaming/
     *
     * @param string $file Name of file to serve range request for
     * @param int $size size of the file in bytes
     * @param int $start starting byte location want to serve
     * @param int $end ending byte location want ot serve
     */
    public function serveRangeRequest($file, $size, $start, $end)
    {
        $current_start = $start;
        $current_end = $end;
        $pre_range_parts = explode('=', $_SERVER['HTTP_RANGE'], 2);
        $range = ",";
        if (count($pre_range_parts) == 2) {
            list(, $range) = $pre_range_parts;
        }
        if (strpos($range, ',') !== false) {
            $this->web_site->header(
                'HTTP/1.1 416 Requested Range Not Satisfiable');
            $this->web_site->header("Content-Range: bytes $start-$end/$size");
            return;
        }
        if ($range == '-') {
            $current_start = $size - 1;
        } else {
            $range = explode('-', $range);
            $current_start = trim($range[0]);
            $current_end = (isset($range[1]) && is_numeric(trim($range[1])))
                ? trim($range[1]) : $size;
            if ($current_start === "") {
                $current_start = max(0, $size - $range[1] - 1);
            }
        }
        $current_end = ($current_end > $end) ? $end : $current_end;
        if ($current_start > $current_end || $current_start > $size - 1 ||
            $current_end >= $size) {
            $this->web_site->header(
                'HTTP/1.1 416 Requested Range Not Satisfiable');
            $this->web_site->header("Content-Range: bytes $start-$end/$size");
            return;
        }
        $start = $current_start;
        $end = $current_end;
        $length = $end - $start + 1;
        $fp = @fopen($file, 'rb');
        fseek($fp, $start);
        $this->web_site->header('HTTP/1.1 206 Partial Content');
        $this->web_site->header("Content-Range: bytes $start-$end/$size");
        $this->web_site->header("Content-Length: ".$length);
        $buffer = 8192;
        $position = ftell($fp);
        while(!feof($fp) && $position <= $end && connection_status() == 0) {
            if ($position + $buffer > $end) {
                $buffer = $end - $position + 1;
            }
            echo fread($fp, $buffer);
            flush();
            $position = ftell($fp);
        }
        fclose($fp);
    }
    /**
     * Used to get a keyword suggest trie. This sends additional
     * header so will be decompressed on the fly
     */
    public function suggest()
    {
        if (!isset($_REQUEST["locale"])){
            return;
        }
        $locale = $_REQUEST["locale"];
        $count = preg_match("/^[a-zA-z]{2}(-[a-zA-z]{2})?$/", $locale);
        if ($count != 1) {
            return;
        }
        $locale = str_replace("-", "_", $locale);
        $path = C\LOCALE_DIR . "/$locale/resources/suggest_trie.txt.gz";
        if (file_exists($path)) {
            $size = filesize($path);
            $this->web_site->header("Cache-Control: public");
            $this->web_site->header("Content-Type: application/json");
            $this->web_site->header("Content-Encoding: gzip");
            $this->web_site->header("Content-Length: " . $size);
            if ($this->checkUnmodifiedAndProcess($path, $size)) {
                return;
            }
            readfile($path);
        }
    }
    /**
     * Checks if a request is for a file that was cached and not size modified.
     * If so, processes and output 304 headers
     *
     * @param string $path file system path to file resource
     * @param int $size size of file
     * @return bool whether resource hasn't changed (true) or has (false)
     */
    public function checkUnmodifiedAndProcess($path, $size)
    {
        $expires = gmdate( "D, d M Y H:i:s", time() +
            intval(C\RESOURCE_CACHE_TIME))." GMT";
        $this->web_site->header("Expires: $expires");
        $last_modified  = filemtime($path);
        $this->web_site->header("Last-Modified: " .
            gmdate( "D, d M Y H:i:s", $last_modified )." GMT" );
        $modified_since = (isset( $_SERVER["HTTP_IF_MODIFIED_SINCE"]))
            ? strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]) : false;
        $etag_header = (isset($_SERVER["HTTP_IF_NONE_MATCH"])) ?
            trim($_SERVER["HTTP_IF_NONE_MATCH"]) : false;
        //very crude etag
        $etag = sprintf( '"%s-%s"', $last_modified, $size);
        $this->web_site->header("Etag: $etag");
        if ($modified_since === $last_modified &&
            $etag === $etag_header) {
            $this->web_site->header("HTTP/1.1 304 Not Modified");
            return true;
        }
        return false;
    }
    /**
     * Used to notify a machine that another machine acting as a mirror
     * is still alive. Data is stored in a txt file self::mirror_table_name
     */
    public function syncNotify()
    {
        if (isset($_REQUEST['last_sync']) && $_REQUEST['last_sync'] > 0 ) {
            $mirror_table_name = C\CRAWL_DIR . "/" . self::mirror_table_name;
            $mirror_table = [];
            $time = time();
            if (file_exists($mirror_table_name) ) {
                $mirror_table = unserialize(
                    $this->web_site->fileGetContents($mirror_table_name));
                if (isset($mirror_table['time']) &&
                    $mirror_table['time'] - $time > C\MIRROR_SYNC_FREQUENCY) {
                    $mirror_table = [];
                    // truncate table periodically to get rid of stale entries
                }
            }
            if (isset($_REQUEST['robot_instance'])) {
                $mirror_table['time'] = $time;
                $mirror_table['machines'][
                    $this->clean($_REQUEST['robot_instance'], "string")] =
                    [L\remoteAddress(), $_REQUEST['machine_uri'],
                    $time,
                    $this->clean($_REQUEST['last_sync'], "int")];
                $this->web_site->filePutContents($mirror_table_name,
                    serialize($mirror_table));
            }
        }
    }
    /**
     * Returns a list of syncable files and the modification times
     */
    public function syncList()
    {
        $this->syncNotify();
        $info = [];
        if (isset($_REQUEST["last_sync"])) {
            $last_sync = $this->clean($_REQUEST["last_sync"], "int");
        } else {
            $last_sync = 0;
        }
        // substrings to exclude from our list
        $excludes = [".DS", "__MACOSX", "queries", "QueueBundle", "tmp",
            "thumb"];
        $sync_files = $this->model("crawl")->getDeltaFileInfo(
            C\CRAWL_DIR . "/cache", $last_sync, $excludes);
        if (count($sync_files) > 0 ) {
            $info[self::STATUS] = self::CONTINUE_STATE;
            $info[self::DATA] = $sync_files;
        } else {
            $info[self::STATUS] = self::NO_DATA_STATE;
        }
        echo base64_encode(gzcompress(serialize($info)));
    }
}
