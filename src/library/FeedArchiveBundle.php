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
 * Used for crawlLog, crawlHash, and garbageCollect
 */
require_once __DIR__ . '/Utility.php';
/**
 * Subclass of IndexArchiveBundle with bloom filters to make it easy to check
 * if a news feed item has been added to the bundle already before adding it
 *
 * @author Chris Pollett
 */
class FeedArchiveBundle extends IndexArchiveBundle
{
    /**
     * Used to store unique identifiers of feed items that have been stored
     * in this FeedArchiveBundle. This filter_a is used for checking if items
     * are already in the archive, when it has URL_FILTER_SIZE/2 items
     * filter_b is added to as well as filter_a. When filter_a is of size
     * URL_FILTER_SIZE filter_a is deleted, filter_b is renamed to filter_a
     * and the process is repeated.
     * @var BloomFilterFile
     */
    public $filter_a;
    /**
     * Auxiliary BloomFilterFile used in checking if feed items are in this
     * archive or not. @see $filter_a
     * @var BloomFilterFile
     */
    public $filter_b;

    /**
     * Makes or initializes an FeedArchiveBundle with the provided parameters
     *
     * @param string $dir_name folder name to store this bundle
     * @param bool $read_only_archive whether to open archive only for reading
     *  or reading and writing
     * @param string $description a text name/serialized info about this
     *      IndexArchiveBundle
     * @param int $num_docs_per_generation the number of pages to be stored
     *      in a single shard
     */
    public function __construct($dir_name, $read_only_archive = true,
        $description = null, $num_docs_per_generation =
        C\NUM_DOCS_PER_GENERATION)
    {
        parent::__construct($dir_name, $read_only_archive, $description,
            $num_docs_per_generation);
        if (file_exists($dir_name . "/filter_a.ftr")) {
            $this->filter_a = BloomFilterFile::load($dir_name .
                "/filter_a.ftr");
        } else {
            $this->filter_a = new BloomFilterFile($dir_name . "/filter_a.ftr",
                C\URL_FILTER_SIZE);
            set_error_handler(null);
            @chmod($dir_name . "/filter_a.ftr", 0755);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        }
        if (file_exists($dir_name . "/filter_b.ftr")) {
            $this->filter_a = BloomFilterFile::load($dir_name .
                "/filter_b.ftr");
        } else {
            $this->filter_b = null;
        }
    }
    /**
     * Add the array of $pages to the summaries WebArchiveBundle pages being
     * stored in the partition $generation and the field used
     * to store the resulting offsets given by $offset_field.
     *
     * @param int $generation field used to select partition
     * @param string $offset_field field used to record offsets after storing
     * @param string $key_field field used to store unique identifier for a
     *      each page item.
     * @param array &$pages data to store
     * @param int $visited_urls_count number to add to the count of visited urls
     *     (visited urls is a smaller number than the total count of objects
     *     stored in the index).
     */
    public function addPagesAndSeenKeys($generation, $offset_field, $key_field,
        &$pages, $visited_urls_count)
    {
        foreach ($pages as $page) {
            $key = $page[$key_field];
            $this->addFilters($key);
        }
        parent::addPages($generation, $offset_field, $pages,
            $visited_urls_count);
    }
    /**
     * Adds the key (often GUID) of a feed item to the bloom filter pair
     * associated with this archive. This always adds to filter a, if
     * filter a is more than half full it adds to filter b. If filter a is full
     * it is deletedand filter b is renamed filter a and te process continues
     * where a new filter b is created when this becomee half full.
     * @param string $key unique identifier of a feed item
     */
    public function addFilters($key)
    {
        if ($this->filter_a->count > C\URL_FILTER_SIZE/2 &&
            !$this->filter_b) {
            if (file_exists($this->dir_name . "/filter_b.ftr")) {
                $this->filter_b = BloomFilterFile::load($dir_name .
                    "/filter_b.ftr");
            } else {
                $this->filter_b = new BloomFilterFile(
                    $this->dir_name . "/filter_b.ftr", C\URL_FILTER_SIZE);
                chmod($dir_name . "/filter_a.ftr", 0755);
            }
        }
        if ($this->filter_a->count > C\URL_FILTER_SIZE) {
            unlink($this->dir_name . "/filter_a.ftr");
            rename($this->dir_name . "/filter_b.ftr",
                $this->dir_name . "/filter_a.ftr");
        }
        $this->filter_a->add($key);
        if ($this->filter_b) {
            $this->filter_b->add($key);
        }
    }
    /**
     * Whether the active filter for this feed contain thee feed item
     * of thee supplied key
     * @param string $key the feed item id to check if in arcive
     * @return bool true if it is in the archive, false otherwise
     */
    public function contains($key)
    {
        return $this->filter_a->contains($key);
    }
    /**
     * Forces the current shard to be saved
     */
    public function forceSave()
    {
        $this->getActiveShard()->save(false, true);
        $this->filter_a->save();
        chmod($this->dir_name . "/filter_a.ftr", 0755);
        if ($this->filter_b) {
            $this->filter_b->save();
            chmod($this->dir_name . "/filter_b.ftr", 0755);
        }
    }
}
