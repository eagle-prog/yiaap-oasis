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

/**
 * Loads crawlLog functions if needed
 */
require_once __DIR__ . "/Utility.php";
/**
 *
 *
 * @author Chris Pollett
 */
class LinearHashTable
{
    /**
     *
     */
    public $folder;
    /**
     *
     */
    public $parameters;
    /**
     *
     */
    const DEFAULT_COMPRESSOR = C\NS_COMPRESSORS . "NonCompressor";
    /**
     *
     */
    const FILE_OFFSET_LEN = 8;
    /**
     *
     */
    const FILE_LEN_LEN = 8;
    /**
     *
     */
    const HASH_LEN = 16;
    /**
     *
     */
    const INDEX_CACHE_SIZE = 100;
    /**
     *
     */
    const INDEX_RECORD_LEN = self::HASH_LEN + self::FILE_OFFSET_LEN +
        self::FILE_LEN_LEN;
    /**
     *
     */
    const MAX_ITEMS_PER_FILE = 16384;
    /**
     *
     */
    const DEFAULT_PARAMETERS = ["MAX_ITEMS_PER_FILE" =>
        self::MAX_ITEMS_PER_FILE, "COMPRESSOR" => self::DEFAULT_COMPRESSOR,
        "COUNT" => 0];
    /**
     *
     */
    const PARAMETERS_FILE = "parameter.txt";
    /**
     *
     */
    const DELETE_EXTENSION = ".del";
    /**
     *
     */
    const HASH_INDEX_EXTENSION = ".hix";

    /**
     *
     */
    const TEMP_EXTENSION = ".tmp";
    /**
     *
     */
    public function __construct($folder,
        $max_items_per_file = self::MAX_ITEMS_PER_FILE,
        $compressor = self::DEFAULT_COMPRESSOR)
    {
        $initial_parameters = self::DEFAULT_PARAMETERS;
        $initial_parameters["MAX_ITEMS_PER_FILE"] = $max_items_per_file;
        $initial_parameters["COMPRESSOR"] = $compressor;
        $this->folder = $folder;
        $folder_paths = [$folder];
        foreach ($folder_paths as $folder_path) {
            if (!file_exists($folder_path)) {
                if(!mkdir($folder_path)) {
                    return null;
                }
            }
        }
        $parameter_path = $folder . "/" . self::PARAMETERS_FILE;
        $this->parameters = [];
        if(file_exists($parameter_path)) {
            $this->parameters = json_decode(file_get_contents($parameter_path),
                true);
        }
        foreach (self::DEFAULT_PARAMETERS as $field => $value) {
            $this->parameters[$field] = $this->parameters[$field] ??
                $initial_parameters[$field];
        }
        $this->saveParameters();
    }
    /**
     *
     */
    public function get($key, $is_hash_key = false)
    {
        $hash_key = ($is_hash_key) ? $key : $this->hashKey($key);
        $index_info = $this->getIndexInfo($hash_key);
        if (empty($index_info) || empty($index_info[1])) {
            return false;
        }
        list($index_path, $index) = $index_info;
        $index_data = $this->getIndex($index, $hash_key);
        if ($index_data === false) {
            return false;
        }
        list($offset, $len) = $index_data;
        return $this->getArchive($index_path, $offset, $len);
    }
    /**
     *
     */
    public function getIndex($index, $hash_key)
    {
        list($position, $found) = $this->binarySearch($hash_key, $index);
        if ($found) {
            $index_position =
                self::INDEX_RECORD_LEN * $position + self::HASH_LEN;
            $len = self::FILE_OFFSET_LEN + self::FILE_LEN_LEN;
            return array_values(
                unpack("J2", substr($index, $index_position, $len)));
        }
        return false;
    }
    /**
     *
     */
    public function binarySearch($hash_key, $index)
    {
        $low = 0;
        $index_record_len = self::INDEX_RECORD_LEN;
        $hash_len = self::HASH_LEN;
        $high = floor(strlen($index) / $index_record_len) - 1;
        $current = (($low + $high + 1) >> 1);
        do {
            $current_key = substr($index, $current * $index_record_len,
                $hash_len);
            $cmp = strcmp($hash_key, $current_key);
            if ($cmp < 0) {
                $high = $current;
                if ($current <= $low) {
                    return [-1, false];
                } else {
                    if ($current - 1 == $low) {
                        $current--;
                        $high = $current;
                    }
                }
            } else if ($cmp > 0) {
                if ($high == $current) {
                    return [$current, false];
                }
                $low = $current;
            } else  {
                return [$current, true];
            }
            //round up for reverse binary search
            $current = (($low + $high + 1) >> 1);
        } while($current >= $low);
    }
    /**
     *
     */
    public function getArchive($hash_path, $offset, $len)
    {
        $compressor = new ($this->parameters["COMPRESSOR"])();
        $fh = fopen($hash_path . $compressor->fileExtension() , "r");
        $value = false;
        if (fseek($fh, $offset) == 0) {
            $compressed_file = fread($fh, $len);
            $value = $compressor->uncompress($compressed_file);
        }
        fclose($fh);
        return json_decode($value);
    }
    /**
     *
     */
    public function exists($key, $compute_hash = true)
    {
        if ($compute_hash) {
            $hash_key = $this->hashKey($key);
        } else {
            $hash_key = $key;
        }
        list($hash_path, $index) = $this->getIndexInfo($hash_key);
        if ($index) {
            list($position, $found) = $this->binarySearch($hash_key, $index);
            if ($found) {
                return true;
            }
        }
        return false;
    }
    /**
     *
     */
    public function put($key, $value, $is_hash_key = false,
        $change_count = 0)
    {
        $hash_key = ($is_hash_key) ? $key : $this->hashKey($key);
        $split = false;
        if ($change_count == 0) {
            if ($this->exists($hash_key, false)) {
                return false;
            }
            $count = $this->parameters['COUNT'];
            if ($count > 1 &&
                $this->checkSplitMerge($count + 1) > 0) {
                if($this->splitMigrate()) {
                    $split = true;
                }
            }
            $this->parameters['COUNT']++;
            $count = $this->parameters['COUNT'];
        } else {
            $count = $change_count;
        }
        $hash_path = $this->getHashPath($hash_key, $count,
            -1, true);
        if (($add_info = $this->addArchive($hash_path, $value)) !== false) {
            list($offset, $len) = $add_info;
            if($this->addIndex($hash_key, $offset, $len, $count)) {
                $this->saveParameters();
                return true;
            }
        }
        return false;
    }
    /**
     *
     */
    public function delete($key, $is_hash_key = false)
    {
        $hash_key = ($is_hash_key) ? $key : $this->hashKey($key);
        $index_info = $this->getIndexInfo($hash_key);
        list($index_path, $index) = $index_info;
        $found = false;
        if ($index) {
            $search_info = $this->binarySearch($hash_key, $index);
            list($position, $found) = $search_info;
        }
        if (!$found) {
            return;
        }
        $delete_count_file = $index_path . self::DELETE_EXTENSION;
        if (file_exists($delete_count_file)) {
            $delete_count = intval(file_get_contents($delete_count_file));
        } else {
            $delete_count = 0;
        }
        $index = substr($index, 0, self::INDEX_RECORD_LEN * $position) .
            substr($index, self::INDEX_RECORD_LEN * ($position + 1));
        if (empty($index)) {
            $this->unlinkHashPath($index_path);
        } else {
            file_put_contents($index_path . self::HASH_INDEX_EXTENSION, $index);
        }
        $num_records = ceil(strlen($index) / self::INDEX_RECORD_LEN);
        $delete_count++;
        $max_items_per_file = $this->parameters["MAX_ITEMS_PER_FILE"];
        if ($this->checkSplitMerge($this->parameters['COUNT'] - 1) < 0) {
            $this->mergeMigrate();
        } else if (!empty($index) && $delete_count > 1 &&
            $delete_count > 0.1 * $max_items_per_file) {
            $tmp_path = $index_path . self::TEMP_EXTENSION;
            rename($index_path . self::HASH_INDEX_EXTENSION,
                $tmp_path . self::HASH_INDEX_EXTENSION);
            $compressor = new ($this->parameters["COMPRESSOR"])();
            $file_extension = $compressor->fileExtension();
            rename($index_path . $file_extension,
                $tmp_path . $file_extension);
            rename($index_path . self::DELETE_EXTENSION,
                $tmp_path .  self::DELETE_EXTENSION);
            $this->insertRecordsFromIndex($tmp_path,
                $this->parameters['COUNT']);
            $this->unlinkHashPath($tmp_path);
        } else {
            file_put_contents($delete_count_file, $delete_count);
        }
        $this->parameters['COUNT']--;
        $this->saveParameters();
    }
    /**
     *
     */
    public function mergeMigrate()
    {
        list(,$migrate_to_path_low, $migrate_to_path_high) =
            $this->computeMigratePaths();
        $new_count = $this->parameters['COUNT'] - 1;
        $this->insertRecordsFromIndex($migrate_to_path_low, $new_count);
        $this->insertRecordsFromIndex($migrate_to_path_high, $new_count);
        $this->unlinkHashPath($migrate_to_path_low);
        $this->unlinkHashPath($migrate_to_path_high);
    }
    /**
     *
     */
    public function splitMigrate()
    {
        list($migrate_from_path,) = $this->computeMigratePaths();
        $this->insertRecordsFromIndex($migrate_from_path);
        $this->unlinkHashPath($migrate_from_path);
    }
    /**
     *
     */
    public function unlinkHashPath($hash_path)
    {
        $index_file_name = $hash_path . self::HASH_INDEX_EXTENSION;
        if (file_exists($index_file_name)) {
            unlink($index_file_name);
        }
        $delete_file_name = $hash_path . self::DELETE_EXTENSION;
        if (file_exists($delete_file_name)) {
            unlink($delete_file_name);
        }
        $compressor = new ($this->parameters["COMPRESSOR"])();
        $archive_file_name = $hash_path . $compressor->fileExtension();
        if (file_exists($archive_file_name)) {
            unlink($archive_file_name);
        }
    }
    /**
     *
     */
    public function computeMigratePaths($count = -1, $max_items_per_file = -1)
    {
        $count = ($count == -1) ? $this->parameters['COUNT'] : $count;
        $max_items_per_file = max(1, ($max_items_per_file == -1) ?
            $this->parameters['MAX_ITEMS_PER_FILE'] : $max_items_per_file);
        list($num_files, $max_num_bits, $pow_max, $threshold) =
            $this->bitStatistics($count, $max_items_per_file);
        $migrate_from_path = ($threshold >= 0) ?
            $this->getHashPath(pack("J", $threshold),
            $count, $max_items_per_file) : false;
        $migrate_to_path_high = $this->getHashPath(pack("J", $pow_max +
            $threshold - 1), $count + 1, $max_items_per_file);
        if ($threshold == 0) {
            $migrate_to_path_low = $this->getHashPath(
                pack("J", ($pow_max -1) >>1), $count + 1, $max_items_per_file);
        } else {
            $migrate_to_path_low = $this->getHashPath(pack("J", $threshold - 1),
                $count + 1, $max_items_per_file);
        }
        return [$migrate_from_path, $migrate_to_path_low,
            $migrate_to_path_high];
    }
    /**
     *
     */
    public function insertRecordsFromIndex($hash_path, $new_count = -1)
    {
        $new_count = ($new_count == -1) ? $this->parameters['COUNT'] + 1 :
            $new_count;
        $index_path = $hash_path . self::HASH_INDEX_EXTENSION;
        if (!file_exists($index_path)) {
            return;
        }
        $index = file_get_contents($index_path);
        $index_items = str_split($index, self::INDEX_RECORD_LEN);
        foreach ($index_items as $index_item) {
            $hash_key = substr($index_item, 0, self::HASH_LEN);
            list(,$offset, $len) =
                unpack("J2", substr($index_item, self::HASH_LEN));
            $value = $this->getArchive($hash_path, $offset, $len);
            $this->put($hash_key, $value, true, $new_count);
        }
    }
    /**
     *
     */
    public function addArchive($hash_path, $value)
    {
        $compressor = new ($this->parameters["COMPRESSOR"])();
        $fh =  fopen($hash_path . $compressor->fileExtension() , "c+");
        fseek($fh, 0, SEEK_END);
        $offset = ftell($fh);
        $serial_value = json_encode($value);
        $compressed_serial_value = $compressor->compress($serial_value);
        $len = strlen($compressed_serial_value);
        fwrite($fh, $compressed_serial_value, $len);
        fclose($fh);
        return [$offset, $len];
    }
    /**
     *
     */
    public function addIndex($hash_key, $offset, $len, $count = -1)
    {
        $index_info = $this->getIndexInfo($hash_key, $count);
        list($index_path, $index) = $index_info;
        if ($index) {
            $search_info = $this->binarySearch($hash_key, $index);
            list($position, $found) = $search_info;
        } else {
            $index = "";
            $position = 0;
            $found = false;
        }
        if (!$found) {
            $index = substr($index, 0, self::INDEX_RECORD_LEN * ($position+1)) .
                $hash_key . pack("J", $offset) . pack("J", $len) .
                substr($index, self::INDEX_RECORD_LEN * ($position + 1));
            file_put_contents($index_path . self::HASH_INDEX_EXTENSION, $index);
            return true;
        }
        return false;
    }
    /**
     *
     */
    public function hashKey($key)
    {
        return md5($key, true);
    }
    /**
     *
     */
    public function getIndexInfo($hash_key, $count = -1)
    {
        $count = ($count == -1) ? $this->parameters['COUNT'] : $count;
        $hash_path = $this->getHashPath($hash_key, $count);
        $index_path = $hash_path . self::HASH_INDEX_EXTENSION;
        if (!file_exists($index_path)) {
            return [$hash_path, false];
        }
        return [$hash_path, file_get_contents($index_path)];
    }
    /**
     *
     */
    public function checkSplitMerge($new_count)
    {
        $count = $this->parameters['COUNT'];
        $max_items_per_file = $this->parameters['MAX_ITEMS_PER_FILE'];
        $current_expected_num_files = ceil($count/$max_items_per_file);
        $next_expected_num_files = ceil($new_count/$max_items_per_file);
        return $next_expected_num_files - $current_expected_num_files;
    }
    /**
     *
     */
    public function getHashPath($hash_key, $count = -1,
        $max_items_per_file = -1, $mkdir_if_not_exists = false)
    {
        $count = ($count == -1) ? $this->parameters['COUNT'] : $count;
        $max_items_per_file = max(1, ($max_items_per_file == -1) ?
            $this->parameters['MAX_ITEMS_PER_FILE'] : $max_items_per_file);
        $pack_hash = str_pad(substr($hash_key, 0, 8), 8, "\0", STR_PAD_LEFT);
        $hash_int = unpack("J", $pack_hash)[1];
        list($num_files, $max_num_bits, $pow_max, $threshold) =
            $this->bitStatistics($count, $max_items_per_file);
        $init_prefix = "";
        if ($num_files == 1 && $max_items_per_file != 1) {
            $init_prefix = "z";
        }
        $masked_int = ($hash_int & ($pow_max - 1));
        $num_bits = (($masked_int < $threshold)) ? $max_num_bits :
            $max_num_bits - 1;
        $mask = ($num_bits == 0) ? 0 : (1 << ($num_bits)) - 1;
        $prefix = $this->folder ?? ".";
        for ($i = 56, $j = 0; $i > 0; $i -= 8, $j++) {
            if ($num_bits > $i) {
                $prefix .= "/" . ord($pack_hash[$j]);
                if ($mkdir_if_not_exists && !file_exists($prefix) ) {
                    mkdir($prefix);
                }
            }
        }
        return $prefix . "/" . $init_prefix .
            sprintf("%'.0{$num_bits}b",($hash_int & $mask));
    }
    /**
     *
     */
    public function bitStatistics($count, $max_items_per_file)
    {
        $num_files = max(1, ceil($count/$max_items_per_file));
        $max_num_bits = ceil(log($num_files + 1, 2));
        $pow_max = 1 << ($max_num_bits - 1);
        $threshold = $num_files - $pow_max;
        return [$num_files, $max_num_bits, $pow_max, $threshold];
    }
    /**
     *
     */
    public function saveParameters()
    {
        $parameter_path = $this->folder . "/" . self::PARAMETERS_FILE;
        file_put_contents($parameter_path, json_encode($this->parameters));
    }
}
