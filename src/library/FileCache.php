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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\models\datasources as D;

/** For Yioop global defines */
require_once __DIR__ . "/../configs/Config.php";
/**
 * Library of functions used to implement a simple file cache
 *
 * @author Chris Pollett
 */
class FileCache
{
    /**
     * File used to serve last cache request
     * @var string
     */
    public $cache_file;
    /**
     * Folder name to use for this FileCache
     * @var string
     */
    public $dir_name;
    /**
     * Total number of bins to cycle between
     */
    const NUMBER_OF_BINS = 50;
    /**
     * Maximum number of files in a bin
     */
    const MAX_FILES_IN_A_BIN = 5000;
    /**
     * Creates the directory for the file cache, sets how frequently
     * all items in the cache expire
     *
     * @param string $dir_name folder name of where to put the file cache
     * @param WebSite an optional object that might be used to serve webpages
     *      when Yioop run in CLI mode. This object has fileGetContents and
     *      filePutContents methods which allow RAM caching of files.
     */
    public function __construct($dir_name, $web_site = null)
    {
        $this->dir_name = $dir_name;
        if (!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            chmod($this->dir_name, 0777);
            $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS) . "Manager";
            $db = new $db_class();
            $db->setWorldPermissionsRecursive($this->dir_name, true);
        }
        $this->web_site = $web_site;
    }
    /**
     * Either a wrapper for file_get_contents, or if a WebSite object is being
     * used to serve pages, it reads it in using blocking I/O
     * file_get_contents() and caches it before return its string contents.
     * Note this function assumes that only the web server is performing I/O
     * with this file. filemtime() can be used to see if a file on disk has been
     * changed and then you can use $force_read = true below to force re-
     * reading the file into the cache
     *
     * @param string $filename name of file to get contents of
     * @param bool $force_read whether to force the file to be read from
     *      presistent storage rather than the cache
     * @return string contents of the file given by $filename
     */
    public function fileGetContents($filename, $force_read = false)
    {
        if (!empty($this->web_site)) {
            return $this->web_site->fileGetContents($filename, $force_read);
        }
        return file_get_contents($filename);
    }
    /**
     * Either a wrapper for file_put_contents, or if a WebSite object is being
     * used to serve pages, writes $data to the persistent file with name
     * $filename. Saves a copy in the RAM cache if there is a copy already
     * there.
     *
     * @param string $filename name of file to write to persistent storages
     * @param string $data string of data to store in file
     */
    public function filePutContents($filename, $data)
    {
        if (!empty($this->web_site)) {
            return $this->web_site->filePutContents($filename, $data);
        }
        return file_put_contents($filename, $data);
    }
    /**
     * Retrieve data associated with a key that has been put in the cache
     *
     * @param string $key the key to look up
     * @return mixed the data associated with the key if it exists, false
     *     otherwise
     */
    public function get($key)
    {
        $checksum_block = $this->checksum($key);
        $checksum_dir = $this->dir_name . "/$checksum_block";
        $this->cache_file = $checksum_dir . "/c" . webencode($key);
        if (file_exists($this->cache_file)) {
            $this->updateCache($key);
            return unserialize($this->fileGetContents($this->cache_file));
        }
        return false;
    }
    /**
     * Stores in the file cache a key-value pair
     *
     * @param string $key to associate with value
     * @param mixed $value to store
     */
    public function set($key, $value)
    {
        $checksum_block = $this->checksum($key);
        $checksum_dir = $this->dir_name . "/$checksum_block";
        if (!file_exists($checksum_dir)) {
            mkdir($checksum_dir);
            chmod($checksum_dir, 0777);
        }
        $cache_file = "$checksum_dir/c".webencode($key);
        $this->updateCache($key);
        $this->filePutContents($cache_file, serialize($value));
    }
    /**
     * Makes a 0 - self::NUMBER_OF_BINS value out of the provided key
     *
     * @param string $key to convert to a random value between
     *     0 - self::NUMBER_OF_BINS
     * @return int value between 0 and self::NUMBER_OF_BINS
     */
    public function checksum($key)
    {
        $len = strlen($key);
        $value = 0;
        for ($i = 0; $i < $len; $i++) {
            $value += ord($key[$i]);
        }
        return ($value % self::NUMBER_OF_BINS);
    }
    /**
     * Deletes cache key value files and ram copies of key values stored in the
     * this file cache
     */
    public function clear()
    {
        if (!empty($this->web_site)) {
            $this->web_site->clearFileCache();
        }
        if (is_dir($this->dir_name)) {
            $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
            $db = new $db_class();
            $db->unlinkRecursive($this->dir_name, false);
        }
    }
    /**
     * Used to mark a cache item, and keep track of rounds according to the
     * marker algorith. This function determine if the cache is too full
     * and if so eject an item.
     *
     * @param string $key that was just read from or written to. Might need
     *  to be marked according to Marker algorithm
     */
    protected function updateCache($key)
    {
        $checksum_block = $this->checksum($key);
        $checksum_dir = $this->dir_name . "/$checksum_block";
        $marker_file = "$checksum_dir/cache_markers.txt";
        if (!file_exists($checksum_dir)) {
            mkdir($checksum_dir);
            chmod($checksum_dir, 0777);
        }
        if (file_exists($marker_file)) {
            $data = unserialize($this->fileGetContents($marker_file));
        } else {
            $data = [];
        }
        if (empty($data['MARKED'])) {
            $data['MARKED'] = [];
        }
        if (empty($data['UNMARKED'])) {
            $data['UNMARKED'] = [];
        }
        $now = time();
        if (empty($data['TIME'])) {
            $data['TIME'] = $now;
        }
        if (empty($data['UNMARKED'][$key]) && empty($data['MARKED'][$key])) {
            $data['UNMARKED'][$key] = true;
        }
        if (!empty($data['UNMARKED'][$key])) {
            $data['MARKED'][$key] = true;
            unset($data['UNMARKED'][$key]);
        }
        $num_marked = count($data['MARKED']);
        if ($num_marked > self::MAX_FILES_IN_A_BIN) {
            $data['UNMARKED'] = array_merge($data['UNMARKED'], $data['MARKED']);
            $data['MARKED'] = [];
        }
        $num_unmarked = count($data['UNMARKED']);
        $total_in_cache = $num_marked + $num_unmarked;
        if ($total_in_cache > self::MAX_FILES_IN_A_BIN) {
            $num_delete = $total_in_cache - self::MAX_FILES_IN_A_BIN;
            $num_unmarked_delete = min($num_unmarked, $num_delete);
            for ($i = 0; $i < $num_unmarked_delete; $i++) {
                $keys = array_keys($data['UNMARKED']);
                $eject_key = mt_rand(0, $num_unmarked - 1);
                unset($data['UNMARKED'][$eject_key]);
                $num_unmarked--;
                $delete_file = $checksum_dir . "/" . webencode($eject_key);
                if (file_exists($delete_file)) {
                    unlink($delete_file);
                }
            }
            if ($now - $data["TIME"] > C\MIN_QUERY_CACHE_TIME) {
                $in_cache_files = array_flip(glob($checksum_dir . "/c*"));
                $keys = array_keys($data['UNMARKED']);
                foreach ($keys as $check_key) {
                    $check_file = $checksum_dir . "/" . webencode($check_key);
                    if (isset($in_cache_files[$check_file])) {
                        unset($in_cache_files[$check_file]);
                    }
                }
                foreach ($in_cache_files as $to_delete => $num) {
                    unlink($to_delete);
                }
                $data["TIME"] = $now;
            }
        }
        $this->filePutContents($marker_file, serialize($data));
    }
}
