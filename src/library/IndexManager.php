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
 * For crawlHash
 */
require_once __DIR__ . "/Utility.php";
/**
 * Class used to manage open IndexArchiveBundle's while performing
 * a query. Ensures an easy place to obtain references to these bundles
 * and ensures only one object per bundle is instantiated in a Singleton-esque
 * way.
 *
 * @author Chris Pollett
 */
class IndexManager implements CrawlConstants
{
    /**
     * Open IndexArchiveBundle's managed by this manager
     * @var array
     */
    public static $indexes = [];
    /**
     * List of entries of the form name of bundle => time when cached
     * @var array
     */
    public static $index_times = [];
    /**
     * Max number of IndexArchiveBundles that can be cached
     */
    const INDEX_CACHE_SIZE = 1000;
    /**
     * Returns a reference to the managed copy of an IndexArchiveBundle object
     * with a given timestamp or feed (for handling media feeds)
     *
     * @param string $index_name timestamp of desired IndexArchiveBundle
     * @return object the desired IndexArchiveBundle reference
     */
    public static function getIndex($index_name)
    {
        $index_name = trim($index_name); //trim to fix postgres quirkiness
        if ($index_name == "feed" || $index_name == self::FEED_CRAWL_TIME) {
            $index_archive_name = self::feed_index_data_base_name;
            $index_name = "feed";
        } else {
            $index_archive_name = self::index_data_base_name . $index_name;
        }
        if (empty(self::$indexes[$index_name]) ||
            (!empty(self::$index_times[$index_name]) &&
            ($index_name == 'feed' && php_sapi_name() == 'cli') &&
            (time() - self::$index_times[$index_name])
            > C\MIN_QUERY_CACHE_TIME) ) {
            if (file_exists(C\CRAWL_DIR.'/cache/' . $index_archive_name)) {
                $tmp = new IndexArchiveBundle(C\CRAWL_DIR . '/cache/' .
                    $index_archive_name);
                if (!$tmp) {
                    return false;
                }
            } else {
                $tmp = false;
                $use_name = $index_name;
                $serve_archive = -1;
                if (preg_match("/\-\d$/", $index_name)) {
                    $serve_archive = substr($index_name, -1);
                    $use_name = substr($index_name, 0, -2);
                }
                $index_archive_name = self::double_index_base_name .
                    $use_name;
                $status_file = C\CRAWL_DIR . '/cache/' .
                    $index_archive_name . "/status.txt";
                if ($serve_archive < 0 && file_exists($status_file)) {
                    $status = unserialize(file_get_contents($status_file));
                    $active_archive = (empty($status["swap_count"])) ? 1 :
                        $status["swap_count"] % 2;
                    $serve_archive = 1 - $active_archive;
                }
                $tmp = new IndexArchiveBundle(
                    C\CRAWL_DIR . '/cache/' . $index_archive_name .
                    "/bundle$serve_archive");
                if (!$tmp) {
                    $serve_archive = ($serve_archive == 0) ? 1 : 0;
                    $tmp = new IndexArchiveBundle(
                        C\CRAWL_DIR . '/cache/' . $index_archive_name .
                        "/bundle$serve_archive");
                }
                if (!$tmp) {
                    return false;
                }
            }
            self::$indexes[$index_name] = $tmp;
            self::$indexes[$index_name]->setCurrentShard(0, true);
            self::$index_times[$index_name] = time();
            /*
               If too many cached discard oldest 1/3 of cached indices
             */
            if (count(self::$indexes) > self::INDEX_CACHE_SIZE) {
                $times = array_values(self::$index_times);
                sort($times);
                $oldest_third = $times[floor(count($times)/3)];
                foreach (self::$index_times as $name => $time) {
                    if ($time <= $oldest_third) {
                        unset(self::$index_times[$name], self::$indexes[$name]);
                    }
                }
            }
        }
        return self::$indexes[$index_name];
    }
    /**
     *  Clears the static variables in which caches of read in indexes
     *  and dictionary info is stored.
     */
    public static function clearCache()
    {
        self::$indexes = [];
        self::$index_times = [];
    }
    /**
     * Returns the version of the index, so that Yioop can determine
     * how to do word lookup.The only major change to the format was
     * when word_id's went from 8 to 20 bytes which happened around Unix
     * time 1369754208.
     *
     * @param string $index_name unix timestamp of index
     * @return int 0 - if the orginal format for Yioop indexes; 1 -if 20 byte
     *     word_id format
     */
    public static function getVersion($index_name)
    {
        $index_name = (empty($index_name) || $index_name[0] != '-') ?
            $index_name : substr($index_name, 1);
        if (intval($index_name) < C\VERSION_0_TIMESTAMP) {
            return 0;
        }
        $tmp_index = self::getIndex($index_name);
        if (isset($tmp_index->version)) {
            return $tmp_index->version;
        }
        return 1;
    }
    /**
     * Gets an array of posting list positions for each shard in the
     * bundle $index_name for the word id $hash
     *
     * @param string $index_name bundle to look $hash in
     * @param string $hash hash of phrase or word to look up in bundle
     *     dictionary
     * @param int $threshold after the number of results exceeds this amount
     *     stop looking for more dictionary entries.
     * @param int $start_generation what generation in the index to start
     *      finding occurrence of phrase from
     * @param int $num_distinct_generations from $start_generation how
     *      many generation to search forward to
     * @param bool $with_remaining_total whether to total number of
     *      postings found as well or not
     * @return array either [total, sequence of four tuples]
    *       or sequence of four tuples:
     *      (index_shard generation, posting_list_offset, length, exact id
     *      that match $hash)
     */
    public static function getWordInfo($index_name, $hash, $threshold = -1,
        $start_generation = -1, $num_distinct_generations = -1,
        $with_remaining_total = false)
    {
        $index = self::getIndex($index_name);
        $pre_info = [];
        if (!empty($index->dictionary)) {
            $pre_info =
                $index->dictionary->getWordInfo($hash, true, $threshold,
                $start_generation, $num_distinct_generations, true);
        }
        $last_desired_generation = $start_generation +
            $num_distinct_generations;
        if (isset($index->generation_info['ACTIVE'])) {
            $active_generation = $index->generation_info['ACTIVE'];
            if ((empty($index->generation_info['LAST_DICTIONARY_SHARD']) ||
                $index->generation_info['LAST_DICTIONARY_SHARD'] <
                $active_generation) && ($active_generation <
                $last_desired_generation || $last_desired_generation < 0)) {
                $active_shard_file = $index->dir_name .
                    "/posting_doc_shards/index" . $active_generation;
                if (file_exists($active_shard_file)) {
                    if (!empty($index->non_merged_shard) &&
                        !empty($index->non_merged_generation) &&
                        $index->non_merged_generation == $active_generation) {
                        $active_shard = $index->non_merged_shard;
                    } else {
                        $active_shard = new IndexShard($active_shard_file, 0,
                            C\NUM_DOCS_PER_GENERATION, true);
                        $index->non_merged_shard = $active_shard;
                        $index->non_merged_generation = $active_generation;
                    }
                    $active_info = $active_shard->getWordInfo($hash, true);
                    if (is_array($active_info)) {
                        if (empty($pre_info)) {
                            $pre_info[0] = 0;
                            $pre_info[1] = [];
                        }
                        $pre_info[1][] = [$active_generation,
                            $active_info[0], $active_info[1], $active_info[2],
                            $active_info[3]];
                        $pre_info[0] += $active_info[2];
                    }
                }
            }
        }
        if (!empty($pre_info[1])) {
            list($total, $info) = $pre_info;
        } else {
            $total = 0;
            $info = [];
        }
        return ($with_remaining_total) ? [$total, $info] : $info;
    }
    /**
     * Returns the number of document that a given term or phrase appears in
     * in the given index where we discount later generation -- those with
     * lower document rank more
     *
     * @param string $term what to look up in the indexes dictionary
     *     no  mask is used for this look up
     * @param string $index_name index to look up term or phrase in
     * @return int number of documents
     */
    public static function discountedNumDocsTerm($term, $index_name)
    {
        static $num_docs_cache = [];
        if (isset($num_docs_cache[$index_name][$term])) {
            return $num_docs_cache[$index_name][$term];
        }
        $hash = crawlHashWord($term, true);
        $word_info = self::getWordInfo($index_name, $hash, -1, 0,
            C\NUM_DISTINCT_GENERATIONS);
        if (empty($word_info)) {
            return 0.0;
        }
        $total = 0.0;
        $i = 1;
        foreach ($word_info as $generation_info) {
            list($generation, , , $num_docs) = $generation_info;
            $discount = max($generation + 1, $i++);
            $total += $num_docs / $discount;
        }
        if (count($num_docs_cache) > 1000) {
            $num_docs_cache = [];
        }
        if (!empty($num_docs_cache[$index_name]) &&
            count($num_docs_cache[$index_name]) > 10000) {
            $num_docs_cache[$index_name] = [];
        }
        $num_docs_cache[$index_name][$term] = $total;
        return $total;
    }
}
