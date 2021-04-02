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
 * Used for crawlLog and crawlHash
 */
require_once __DIR__ . '/Utility.php';
/**
 * A DoubleIndexBundle encapsulates and provided methods for two
 * IndexArchiveBundle used to store a repeating crawl. One one thse bundles
 * is used to handle current search queries, while the other is used to store
 * an ongoing crawl, once the crawl time has been reach the roles of the two
 * bundles are swapped
 *
 * @author Chris Pollett
 */
class DoubleIndexBundle implements CrawlConstants
{
    /**
     * How frequency the live and ongoing archive should be swapped
     * in seconds
     * @var int
     */
    public $repeat_frequency;
    /**
     * Last time live and ongoing archives were switched
     * @var int
     */
    public $repeat_time;
    /**
     * The number of times live and ongoing archives have swapped
     * @var int
     */
    public $swap_count;
    /**
     * The internal IndexArchiveBundle which is active
     * @var IndexArchiveBundle
     */
    public $active_archive;
    /**
     * Holds for a non-read-only archive whether we
     * build the IndexArchive's in an incremental fashion adding new
     * documents periodically, or instead do we rebuild the whole index
     * archive each time we forceSave.
     * @var bool
     */
    public $incremental;
    /**
     * The number of the internal IndexArchiveBundle which is active
     * @var IndexArchiveBundle
     */
    public $active_archive_num;
    /**
     * A short text name for this DoubleIndexBundle
     * @var string
     */
    public $description;
    /**
     * Number of docs before a new generation is started for an
     * IndexArchiveBundle in this DoubleIndexBundle
     * @var int
     */
    public $num_docs_per_generation;
    /**
     * Makes or initializes an DoubleIndexBundle with the provided parameters
     *
     * @param string $dir_name folder name to store this bundle
     * @param bool $read_only_archive whether to open archive only for reading
     *      or reading and writing
     * @param string $description a text name/serialized info about this
     *      IndexArchiveBundle
     * @param int $num_docs_per_generation the number of pages to be stored
     *      in a single shard
     * @param bool $incremental for a non-read-only archive whether we
     *      build the IndexArchive in an incremental fashion adding new
     *      documents periodically, or instead do we rebuild the whole index
     *      archive each time we forceSave.
     * @param int $repeat_frequency how often the crawl should be redone in
     *      seconds (has no effect if $read_only_archive is true)
     */
    public function __construct($dir_name, $read_only_archive = true,
        $description = null, $num_docs_per_generation =
        C\NUM_DOCS_PER_GENERATION, $incremental, $repeat_frequency = 3600)
    {
        $this->dir_name = $dir_name;
        $this->incremental = $incremental;
        $this->num_docs_per_generation = $num_docs_per_generation;
        $index_archive_exists = false;
        $is_dir = is_dir($this->dir_name);
        if (!$is_dir && !$read_only_archive) {
            mkdir($this->dir_name);
            $this->active_archive = new IndexArchiveBundle($dir_name .
                "/bundle0", false, null, $num_docs_per_generation,
                $incremental);
            $bundle = new IndexArchiveBundle($dir_name . "/bundle1",
                false, null, $num_docs_per_generation,
                $incremental);
        } else if (!$is_dir) {
            return false;
        }
        $read_status = false;
        if (file_exists($this->dir_name . "/status.txt")) {
            $status = unserialize(
                file_get_contents($this->dir_name . "/status.txt"));
            $read_status = true;
        }
        $this->repeat_frequency =  (empty($status["repeat_frequency"])) ?
            $repeat_frequency : $status["repeat_frequency"];
        $this->repeat_time = (empty($status["repeat_time"])) ?
            time() : $status["repeat_time"];
        $this->swap_count = (empty($status["swap_count"])) ?
            0 : $status["swap_count"];
        $this->active_archive_num = $this->swap_count % 2;
        $this->description = (empty($status["DESCRIPTION"])) ?
            $description : $status["DESCRIPTION"];
        if (!$read_status && !$read_only_archive) {
            $status = ["repeat_frequency" => $this->repeat_frequency,
                "repeat_time" => $this->repeat_time,
                "swap_count" => $this->swap_count,
                "DESCRIPTION" => $this->description
            ];
            file_put_contents($this->dir_name . "/status.txt",
                serialize($status));
        }
        if (empty($this->active_archive)) {
            $this->active_archive = new IndexArchiveBundle($dir_name .
                "/bundle" . $this->active_archive_num, $read_only_archive, null,
                $num_docs_per_generation, $incremental);
        }
    }
    /**
     * Switches which of the two bundles is the the one new index data will
     * be written. Before switching closes old bundle properly.
     */
    public function swapActiveBundle()
    {
        $this->forceSave();
        $this->addAdvanceGeneration();
        $this->active_archive->dictionary->mergeAllTiers();
        $this->swap_count++;
        $this->active_archive_num = $this->swap_count % 2;
        $bundle_name = $this->dir_name . "/bundle" . $this->active_archive_num;
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS) . "Manager";
        $db = new $db_class();
        $db->unlinkRecursive($bundle_name, true);
        $this->active_archive = new IndexArchiveBundle($bundle_name,
            false, null, $this->num_docs_per_generation, $this->incremental);
        $this->repeat_time = time();
        $status = ["repeat_frequency" => $this->repeat_frequency,
            "repeat_time" => $this->repeat_time,
            "swap_count" => $this->swap_count,
            "DESCRIPTION" => $this->description
        ];
        file_put_contents($this->dir_name . "/status.txt",
            serialize($status));
    }
    /**
     * Used when a crawl stops to perform final dictionary operations
     * to produce a working stand-alone index.
     */
    public function stopIndexingBundle()
    {
        $this->forceSave();
        $this->addAdvanceGeneration();
        $this->active_archive->dictionary->mergeAllTiers();
        $bundle_name = $this->dir_name . "/bundle" . $this->active_archive_num;
        /* we haven't swapped yet, so want to serve results from bundle
           that exists. i.e., the not active one
         */
        $status = ["repeat_frequency" => $this->repeat_frequency,
            "repeat_time" => $this->repeat_time,
            "swap_count" => $this->swap_count,
            "DESCRIPTION" => $this->description
        ];
        file_put_contents($this->dir_name . "/status.txt",
            serialize($status));
    }
    /**
     * Checks if the amount of time since the two IndexArchiveBundles in
     * this DoubleIndexBundle roles have been swapped has exceeded the
     * swap time for this buundle.
     *
     * @return bool true if the swap time has been exceeded
     */
    public function swapTimeReached()
    {
        return ($this->repeat_time + $this->repeat_frequency < time());
    }
    /**
     * Add the array of $pages to the summaries WebArchiveBundle pages
     * of the active IndexArchiveBundle,
     * storing in the partition $generation and the field used
     * to store the resulting offsets given by $offset_field.
     *
     * @param int $generation field used to select partition
     * @param string $offset_field field used to record offsets after storing
     * @param array &$pages data to store
     * @param int $visited_urls_count number to add to the count of visited urls
     *     (visited urls is a smaller number than the total count of objects
     *     stored in the index).
     */
    public function addPages($generation, $offset_field, &$pages,
        $visited_urls_count)
    {
        $this->active_archive->addPages($generation, $offset_field, $pages,
            $visited_urls_count);
    }
    /**
     * Adds the provided mini inverted index data to the active
     * IndexArchiveBundle
     * Expects initGenerationToAdd to be called before, so generation is correct
     *
     * @param object $index_shard a mini inverted index of word_key=>doc data
     *     to add to this IndexArchiveBundle
     */
    public function addIndexData($index_shard)
    {
        $this->active_archive->addIndexData($index_shard);
    }
    /**
     * Determines based on its size, if index_shard should be added to
     * the active generation or in a new generation should be started.
     * If so, a new generation is started, the old generation is saved, and
     * the dictionary of the old shard is copied to the bundles dictionary
     * and a log-merge performed if needed
     *
     * @param int $add_num_docs number of docs in the shard about to be added
     * @param object $callback object with join function to be
     *     called if process is taking too long
     * @param bool $blocking whether there is an ongoing merge tiers operation
     *      occurring, if so don't do anything and return -1
     * @return int the active generation after the check and possible change has
     *     been performed
     */
    public function initGenerationToAdd($add_num_docs, $callback = null,
        $blocking = false)
    {
        return $this->active_archive->initGenerationToAdd($add_num_docs,
            $callback, $blocking);
    }
    /**
     * Starts a new generation,  the dictionary of the old shard is copied to
     * the bundles dictionary and a log-merge performed if needed. This
     * function may be called by initGenerationToAdd as well as when resuming
     * a crawl rather than loading the periodic index of save of a too large
     * shard.
     *
     * @param object $callback object with join function to be
     *     called if process is taking too long
     */
    public function addAdvanceGeneration($callback = null)
    {
        $this->active_archive->addAdvanceGeneration($callback);
    }
    /**
     * Adds the words from this shard to the dictionary
     * @param object $callback object with join function to be
     *     called if process is taking too  long
     */
    public function addCurrentShardDictionary($callback = null)
    {
        $this->active_archive->addCurrentShardDictionary($callback);
    }
    /**
     * Returns the shard which is currently being used to read word-document
     * data from the bundle. If one wants to write data to the bundle use
     * getActiveShard() instead. The point of this method is to allow
     * for lazy reading of the file associated with the shard.
     *
     * @param bool $force_read whether to force no advance generation and
     *      merge dictionary side effects
     * @return object the currently being index shard
     */
     public function getCurrentShard($force_read = false)
     {
        return $this->active_archive->getCurrentShard($force_read);
     }
    /**
     * Sets the current shard to be the $i th shard in the index bundle.
     *
     * @param $i which shard to set the current shard to be
     * @param $disk_based whether to read the whole shard in before using or
     *     leave it on disk except for pages need
     */
     public function setCurrentShard($i, $disk_based = false)
     {
        return $this->active_archive->setCurrentShard($i, $disk_based);
     }
    /**
     * Gets the page out of the summaries WebArchiveBundle with the given
     * offset and generation
     *
     * @param int $offset byte offset in partition of desired page
     * @param int $generation which generation WebArchive to look up in
     *     defaults to the same number as the current shard
     * @return array desired page
     */
    public function getPage($offset, $generation = -1)
    {
        return $this->active_archive->getPage($offset, $generation);
    }
    /**
     * Forces the current shard to be saved
     */
    public function forceSave()
    {
        $this->active_archive->forceSave();
    }
    /**
     * Computes the number of occurrences of each of the supplied list of
     * word_keys
     *
     * @param array $word_keys keys to compute counts for
     * @return array associative array of key => count values.
     */
    public function countWordKeys($word_keys)
    {
        return $this->active_archive->countWordKeys($word_keys);
    }
    /**
     * The start schedule is the first schedule a queue server makes
     * when a crawl is just started. To facilitate switching between
     * IndexArchiveBundles when doing a crawl with a DoubleIndexBundle
     * this start schedule is stored in the DoubleIndexBundle, when the
     * IndexArchiveBundles' roles (query and crawl) are swapped,
     * the DoubleIndexBundle copy is used to start the crawl from the beginning
     * again. This method copies the start schedule from the schedule folder
     * to the DoubleIndexBundle at the start of a crawl for later use to do
     * this swapping
     *
     * @param string $dir_name folder in the bundle where the schedule
     *      should be stored
     * @param int $channel channel that is being used to do the current
     *      double index crawl. Typical yioop instance might have several
     *      ongoing crawls each with a different channel
     */
    public static function setStartSchedule($dir_name, $channel)
    {
        $start_schedule = C\CRAWL_DIR . "/schedules/$channel-" .
            self::schedule_start_name;
        if (file_exists($dir_name) && is_dir($dir_name) &&
            file_exists($start_schedule)) {
            copy($start_schedule, $dir_name . "/" . self::schedule_start_name);
        }
    }
    /**
     * The start schedule is the first schedule a queue server makes
     * when a crawl is just started. To facilitate switching between
     * IndexArchiveBundles when doing a crawl with a DoubleIndexBundle
     * this start schedule is stored in the DoubleIndexBundle, when the
     * IndexArchiveBundles' roles (query and crawl) are swapped,
     * this method copies the start schedule from the DoubleIndexBundle
     * to the schedule folder to restart the crawl
     *
     * @param string $dir_name folder in the bundle where the schedule
     *      is stored
     * @param int $channel channel that is being used to do the current
     *      double index crawl. Typical yioop instance might have several
     *      ongoing crawls each with a different channel
     */
    public static function getStartSchedule($dir_name, $channel)
    {
        $start_schedule = C\CRAWL_DIR . "/schedules/$channel-" .
            self::schedule_start_name;
        if (file_exists($dir_name) && is_dir($dir_name)) {
            copy($dir_name . "/" . self::schedule_start_name, $start_schedule);
        }
    }
    /**
     * Gets information about a DoubleIndexBundle out of its status.txt
     * file
     *
     * @param string $dir_name folder name of the DoubleIndexBundle to get
     *     info for
     * @return array containing the name (description) of the
     *     DouleIndexBundle, the number of items stored in it, and the
     *     number of WebArchive file partitions it uses.
     */
    public static function getArchiveInfo($dir_name)
    {
        $info = unserialize(file_get_contents($dir_name . "/status.txt"));
        $swap_count = intval($info['swap_count']);
        $active = $swap_count % 2;
        $inactive = 1 - $active;
        $bundle_name = $dir_name . "/bundle$active";
        $count_info = IndexArchiveBundle::getArchiveInfo($bundle_name);
        $info['COUNT'] = $count_info['COUNT'];
        $info['VISITED_URLS_COUNT'] = $count_info['VISITED_URLS_COUNT'];
        $bundle_name = $dir_name . "/bundle$inactive";
        $count_info = IndexArchiveBundle::getArchiveInfo($bundle_name);
        $info['QUERY_COUNT'] = $count_info['COUNT'];
        $info['QUERY_VISITED_URLS_COUNT'] = $count_info['VISITED_URLS_COUNT'];
        return $info;
    }
    /**
     * Sets the archive info struct for the index archive and web archive
     * bundles associated with this double index bundle. This struct has fields
     * like: DESCRIPTION   (serialied store of global parameters of the crawl
     * like seed sites,  timestamp, etc),  COUNT (num urls seen +
     * pages seen stored for the index archive in use for crawling),
     * VISITED_URLS_COUNT (number of pages seen for the index archive in use for
     * crawling), QUERY_COUNT (num urls seen +
     * pages seen stored for the index archive in use for querying, not
     * crawling), QUERY_VISITED_URLS_COUNT number of pages seen for the
     * index archive in use for querying not crawling),
     * NUM_DOCS_PER_PARTITION (how many doc/web archive in bundle).
     *
     * @param string $dir_name folder with archive bundle
     * @param array $info struct with above fields
     */
    public static function setArchiveInfo($dir_name, $info)
    {
        file_put_contents($dir_name . "/status.txt",
            serialize($info));
    }
    /**
     * Returns the last time the archive info of the bundle was modified.
     *
     * @param string $dir_name folder with archive bundle
     */
    public static function getParamModifiedTime($dir_name)
    {
        $info = unserialize(file_get_contents($dir_name . "/status.txt"));
        $swap_count = intval($info['swap_count']);
        $active = $swap_count % 2;
        $bundle_name = $dir_name . "/bundle" . $active;
        $count_time =
            WebArchiveBundle::getParamModifiedTime($bundle_name .
            "/summaries");
        clearstatcache();
        return max(filemtime($dir_name . "/status.txt"), $count_time);
    }
}
