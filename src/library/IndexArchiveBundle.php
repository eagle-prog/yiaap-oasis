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
 * Encapsulates a set of web page summaries and an inverted word-index of terms
 * from these summaries which allow one to search for summaries containing a
 * particular word.
 *
 * The basic file structures for an IndexArchiveBundle are:
 * <ol>
 * <li>A WebArchiveBundle for web page summaries.</li>
 * <li>A IndexDictionary containing all the words stored in the bundle.
 * Each word entry in the dictionary contains starting and ending
 * offsets for documents containing that word for some particular IndexShard
 * generation.</li>
 * <li>A set of index shard generations. These generations
 * have names index0, index1,... A shard has word entries, word doc entries
 * and document entries. For more information see the index shard
 * documentation.
 * </li>
 * <li>
 * The file generations.txt keeps track of what is the current generation.
 * A given generation can hold NUM_WORDS_PER_GENERATION words amongst all
 * its partitions. After which the next generation begins.
 * </li>
 * </ol>
 *
 *
 * @author Chris Pollett
 */
class IndexArchiveBundle implements CrawlConstants
{
    /**
     * Folder name to use for this IndexArchiveBundle
     * @var string
     */
    public $dir_name;
    /**
     * A short text name for this IndexArchiveBundle
     * @var string
     */
    public $description;
    /**
     * Number of partitions in the summaries WebArchiveBundle
     * @var int
     */
    public $num_partitions_summaries;
    /**
     * Holds for a non-read-only archive whether we
     * build the IndexArchive's in an incremental fashion adding new
     * documents periodically, or instead do we rebuild the whole index
     * archive each time we forceSave.
     * @var bool
     */
    public $incremental;
    /**
     * structure contains info about the current generation:
     * its index (ACTIVE), and the number of words it contains
     * (NUM_WORDS).
     * @var array
     */
    public $generation_info;
    /**
     * Number of docs before a new generation is started
     * @var int
     */
    public $num_docs_per_generation;
    /**
     * WebArchiveBundle for web page summaries
     * @var object
     */
    public $summaries;
    /**
     * IndexDictionary for all shards in the IndexArchiveBundle
     * This contains entries of the form (word, num_shards with word,
     * posting list info 0th shard containing the word,
     * posting list info 1st shard containing the word, ...)
     * @var object
     */
    public $dictionary;
    /**
     * Index Shard for current generation inverted word index
     * @var object
     */
    public $current_shard;
    /**
     * What version of index archive bundle this is
     * @var int
     */
    public $version;
    /**
     * Threshold hold beyond which we don't load old index shard when
     * restarting and instead just advance to a new shard
     */
    const NO_LOAD_SIZE = 50000000;
    /**
     * Threshold index shard beyond which we force the generation to advance
     */
    const FORCE_ADVANCE_SIZE = 120000000;
    /**
     * Makes or initializes an IndexArchiveBundle with the provided parameters
     *
     * @param string $dir_name folder name to store this bundle
     * @param bool $read_only_archive whether to open archive only for reading
     *  or reading and writing
     * @param string $description a text name/serialized info about this
     *      IndexArchiveBundle
     * @param int $num_docs_per_generation the number of pages to be stored
     *      in a single shard
     * @param bool $incremental for a non-read-only archive whether we
     *      build the IndexArchive in an incremental fashion adding new
     *      documents periodically, or instead do we rebuild the whole index
     *      archive each time we forceSave.
     */
    public function __construct($dir_name, $read_only_archive = true,
        $description = null, $num_docs_per_generation =
        C\NUM_DOCS_PER_GENERATION, $incremental = true)
    {
        $this->dir_name = $dir_name;
        $is_dir = is_dir($this->dir_name);
        if (!$is_dir && !$read_only_archive) {
            mkdir($this->dir_name);
            mkdir($this->dir_name . "/posting_doc_shards");
        } else if (!$is_dir) {
            return false;
        }
        $this->incremental = $incremental;
        if (file_exists($this->dir_name . "/generation.txt")) {
            $this->generation_info = unserialize(
                file_get_contents($this->dir_name . "/generation.txt"));
        } else if (!$read_only_archive) {
            $this->generation_info['ACTIVE'] = 0;
            $this->generation_info['LAST_DICTIONARY_SHARD'] = -1;
            file_put_contents($this->dir_name . "/generation.txt",
                serialize($this->generation_info));
        }
        $this->summaries = new WebArchiveBundle($dir_name . "/summaries",
            $read_only_archive, -1, $description);
        if (!$read_only_archive) {
            $this->summaries->initCountIfNotExists("VISITED_URLS_COUNT");
        }
        $this->description = $this->summaries->description;
        if (isset($this->summaries->version)) {
            $this->version = $this->summaries->version;
        }
        $this->num_docs_per_generation = $num_docs_per_generation;
        $this->dictionary = new IndexDictionary($this->dir_name . "/dictionary",
            $this);
    }
    /**
     * Add the array of $pages to the summaries WebArchiveBundle pages being
     * stored in the partition $generation and the field used
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
        $this->summaries->setWritePartition($generation);
        $this->summaries->addPages($offset_field, $pages);
        $this->summaries->addCount($visited_urls_count, "VISITED_URLS_COUNT");
    }
    /**
     * Adds the provided mini inverted index data to the IndexArchiveBundle
     * Expects initGenerationToAdd to be called before, so generation is correct
     *
     * @param object $index_shard a mini inverted index of word_key=>doc data
     *     to add to this IndexArchiveBundle
     */
    public function addIndexData($index_shard)
    {
        crawlLog("**ADD INDEX DIAGNOSTIC INFO...");
        $start_time = microtime(true);
        $this->getActiveShard()->appendIndexShard($index_shard);
        crawlLog("Append Index Shard: Memory usage:" . memory_get_usage() .
          " Time: ".(changeInMicrotime($start_time)));
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
        $generation = $this->generation_info['ACTIVE'];
        $active_file_name = $this->dir_name .
            "/posting_doc_shards/index" . $generation;
        if ($this->incremental) {
            $active_shard = $this->getActiveShard();
            $current_num_docs = (empty($active_shard->num_docs)) ? 0 :
                $active_shard->num_docs;
        } else {
            $partition = $this->summaries->getPartition($generation, false);
            $current_num_docs = $partition->count;
        }
        crawlLog("Current generation has " . $current_num_docs .
            " documents.");
        $memory_limit = metricToInt(ini_get("memory_limit"));
        $before_usage = memory_get_usage();
        crawlLog("Indexer Memory  limit is " . $memory_limit . ". Usage is ".
            $before_usage);
        $too_many_docs = $current_num_docs + $add_num_docs >
            $this->num_docs_per_generation;
        $shard_size_too_big = (file_exists($active_file_name) &&
            filesize($active_file_name) > self::FORCE_ADVANCE_SIZE);
        $too_close_to_memory_limit = $before_usage >
            C\MEMORY_FILL_FACTOR * $memory_limit;
        if ($too_many_docs || $shard_size_too_big ||
            $too_close_to_memory_limit) {
            if ($blocking == true) {
                return -1;
            }
            crawlLog("Switching Index Shard...");
            if ($too_many_docs) {
                crawlLog("...because too many docs in shard ".
                    "($current_num_docs).");
            }
            if ($shard_size_too_big) {
                crawlLog("...because shard size uses too much memory (".
                    filesize($active_file_name) . ").");
            }
            if ($too_close_to_memory_limit) {
                crawlLog("...because too close to overall memory limit (".
                    $before_usage . ").");
            }
            $switch_time = microtime(true);
            // Save current shard dictionary to main dictionary
            $this->forceSave();
            $this->addAdvanceGeneration($callback);
            $num_freed = garbageCollect();
            crawlLog("Indexer force running garbage collector after generation".
                 " advance. This freed " . $num_freed . " bytes.");
            $after_usage = memory_get_usage();
            crawlLog("Indexer after switch memory usage: $after_usage");
            crawlLog("Switch Index Shard time:".
                changeInMicrotime($switch_time));
        }
        return $this->generation_info['ACTIVE'];
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
        $this->addCurrentShardDictionary($callback);
        //this saves space, but makes it harder to recover from indexing errors
        if (C\nsdefined("RESAVE_SHARDS_WITHOUT_DICTIONARY") &&
            C\RESAVE_SHARDS_WITHOUT_DICTIONARY) {
            crawlLog("Resaving active shard without prefix and dictionary.");
            $this->current_shard->saveWithoutDictionary(true);
        }
        crawlLog("..Done resaving active shard.");
        //Set up new shard
        $this->generation_info['ACTIVE']++;
        $this->generation_info['CURRENT'] =
            $this->generation_info['ACTIVE'];
        $current_index_shard_file = $this->dir_name.
            "/posting_doc_shards/index" . $this->generation_info['ACTIVE'];
        $this->current_shard = new IndexShard(
            $current_index_shard_file, $this->generation_info['ACTIVE'],
                $this->num_docs_per_generation);
        file_put_contents($this->dir_name . "/generation.txt",
            serialize($this->generation_info));
    }
    /**
     * Adds the words from this shard to the dictionary
     * @param object $callback object with join function to be
     *     called if process is taking too  long
     */
    public function addCurrentShardDictionary($callback = null)
    {
        $current_index_shard_file = $this->dir_name.
            "/posting_doc_shards/index" . $this->generation_info['ACTIVE'];
        /* want to do the copying of dictionary as files to conserve memory
           in case merge tiers after adding to dictionary
        */
        $this->current_shard = new IndexShard(
            $current_index_shard_file, $this->generation_info['ACTIVE'],
                $this->num_docs_per_generation, true);
        $this->dictionary->addShardDictionary($this->current_shard, $callback);
        $this->generation_info['LAST_DICTIONARY_SHARD'] =
            $this->generation_info['ACTIVE'];
    }
    /**
     * Sets the current shard to be the active shard (the active shard is
     * what we call the last (highest indexed) shard in the bundle. Then
     * returns a reference to this shard
     * @return object last shard in the bundle
     */
     public function getActiveShard()
     {
        if ($this->setCurrentShard($this->generation_info['ACTIVE'])) {
            return $this->getCurrentShard();
        } else if (!isset($this->current_shard) ) {
            $current_index_shard_file = $this->dir_name.
                "/posting_doc_shards/index". $this->generation_info['CURRENT'];
            $this->current_shard = new IndexShard($current_index_shard_file,
                $this->generation_info['CURRENT'],
                $this->num_docs_per_generation);
        }
        return $this->current_shard;
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
        if (!isset($this->current_shard)) {
            if (!isset($this->generation_info['CURRENT'])) {
                $this->generation_info['CURRENT'] =
                    $this->generation_info['ACTIVE'];
            }
            $current_index_shard_file = $this->dir_name .
                "/posting_doc_shards/index". $this->generation_info['CURRENT'];
            if (file_exists($current_index_shard_file)) {
                if (!empty($this->generation_info['DISK_BASED'])) {
                    $this->current_shard = new IndexShard(
                        $current_index_shard_file,
                        $this->generation_info['CURRENT'],
                        $this->num_docs_per_generation, true);
                    $this->current_shard->getShardHeader($force_read);
                    $this->current_shard->read_only_from_disk = true;
                } else {
                    if (!$force_read && filesize($current_index_shard_file) >
                        self::NO_LOAD_SIZE) {
                        $this->addAdvanceGeneration();
                    } else {
                        $this->current_shard =
                            IndexShard::load($current_index_shard_file);
                    }
                }
            } else {
                $this->current_shard = new IndexShard($current_index_shard_file,
                    $this->generation_info['CURRENT'],
                    $this->num_docs_per_generation);
            }
        }
        return $this->current_shard;
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
        $this->generation_info['DISK_BASED'] = $disk_based;
        if (isset($this->generation_info['CURRENT']) &&
            isset($this->generation_info['ACTIVE']) &&
            ($i == $this->generation_info['CURRENT'] ||
            $i > $this->generation_info['ACTIVE'])) {
            return false;
        } else {
            $this->generation_info['CURRENT'] = $i;
            unset($this->current_shard);
            return true;
        }
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
        if ($generation == -1 ) {
            $generation = $this->generation_info['CURRENT'];
        }
        return $this->summaries->getPage($offset, $generation);
    }
    /**
     * Builds an inverted index shard for the current generations index shard.
     */
    public function buildInvertedIndexShard()
    {
        $start_time = microtime(true);
        $keypad = "\x00\x00\x00\x00";
        crawlLog("  Start building inverted index ...  Current Memory:".
            memory_get_usage());
        $generation = $this->generation_info['ACTIVE'];
        $partition = $this->summaries->getPartition($generation, false);
        crawlLog("Building index shard for partition $generation");
        crawlLog("Number of objects in partition:" . $partition->count);
        $shard_name = $this->dir_name .
            "/posting_doc_shards/index$generation";
        if (file_exists($shard_name)) {
            unlink($shard_name);
        }
        $shard = new IndexShard($shard_name, $generation,
            $this->num_docs_per_generation, true);
        $seen_partition = 0;
        $num_partition = $partition->count;
        while($seen_partition < $num_partition) {
            $num_to_get = min($num_partition - $seen_partition,
                8000);
            $offset = $partition->iterator_pos;
            $objects = $partition->nextObjects($num_to_get);
            $cnt = 0;
            foreach ($objects as $object) {
                $cnt++;
                $site = $object[1];
                $interim_time = microtime(true);
                if (!isset($site[self::HASH]) ||
                    (isset($site[self::ROBOT_METAS]) &&
                    in_array("JUSTFOLLOW", $site[self::ROBOT_METAS]))) {
                    continue;
                }
                $doc_rank = false;
                //fetcher might set doc_rank for archive iterators
                if (!empty($site[self::DOC_RANK])) {
                    $doc_rank = $site[self::DOC_RANK];
                }
                //this case  might occur on a recrawl
                if (isset($site[self::TYPE]) && $site[self::TYPE] == "link") {
                    $is_link = true;
                    /*
                      see below in this function for what $site[self::HTTP_CODE]
                      would be for a link
                     */
                    $doc_keys = $site[self::HTTP_CODE];
                    $site_url = $site[self::TITLE];
                    $host =  UrlParser::getHost($site_url);
                    $link_parts = explode('|', $site[self::HASH]);
                    if (isset($link_parts[5])) {
                        $link_origin = $link_parts[5];
                    } else {
                        $link_origin = $site_url;
                    }
                    $url_info = [];
                    if (!empty($site[self::LANG])) {
                        $url_info[self::LANG] = $site[self::LANG];
                    }
                    $meta_ids = PhraseParser::calculateLinkMetas($site_url,
                        $host, $site[self::DESCRIPTION], $link_origin,
                        $url_info);
                    $link_to = "LINK TO:";
                } else {
                    $is_link = false;
                    $site_url = str_replace('|', "%7C", $site[self::URL]);
                    $host = UrlParser::getHost($site_url);
                    $doc_keys = crawlHash($site_url, true) .
                        $site[self::HASH] . "d". substr(crawlHash(
                        $host . "/", true), 1);
                    $meta_ids =  PhraseParser::calculateMetas($site, false);
                    $link_to = "";
                }
                $word_lists = [];
                $triplet_lists = [];
                /*
                    self::JUST_METAS check to avoid getting sitemaps in results for
                    popular words
                 */
                $lang = null;
                $is_safe = false;
                if (!isset($site[self::JUST_METAS])) {
                    $host_words = UrlParser::getWordsInHostUrl($site_url);
                    $path_words = UrlParser::getWordsLastPathPartUrl(
                        $site_url);
                    if ($is_link) {
                        $phrase_string = $site[self::DESCRIPTION];
                    } else {
                        if (isset($site[self::LANG])) {
                            if (isset($this->programming_language_extension[
                                $site[self::LANG]])) {
                                $phrase_string = $site[self::DESCRIPTION];
                            } else {
                                $phrase_string = $host_words . " .. ".
                                    $site[self::TITLE] . " ..  ". $path_words .
                                    " .. ". $site[self::DESCRIPTION];
                            }
                        } else {
                            $phrase_string = $host_words . " " .
                                $site[self::TITLE] . " ". $path_words . " ".
                                $site[self::DESCRIPTION];
                        }
                    }
                    if (empty($site[self::LANG])) {
                        $lang = guessLocaleFromString(
                            $site[self::DESCRIPTION]);
                    } else {
                        $lang = $site[self::LANG];
                    }
                    $word_and_qa_lists = PhraseParser::extractPhrasesInLists(
                        $phrase_string, $lang);
                    $word_lists = $word_and_qa_lists['WORD_LIST'];
                    $len = strlen($phrase_string);
                    if (isset($this->programming_language_extension[$lang]) ||
                        PhraseParser::computeSafeSearchScore($word_lists, $len,
                            $site_url) < 0.012) {
                        $meta_ids[] = "safe:all";
                        $meta_ids[] = "safe:true";
                        $is_safe = true;
                    } else {
                        $meta_ids[] = "safe:all";
                        $meta_ids[] = "safe:false";
                        $is_safe = false;
                    }
                }
                if (!$is_link) {
                    //store inlinks so they can be searched by
                    $num_links = count($site[self::LINKS]);
                    if ($num_links > 0) {
                        $link_rank = false;
                        if ($doc_rank !== false) {
                            $link_rank = max($doc_rank - 1, 1);
                        }
                    } else {
                        $link_rank = false;
                    }
                }
                $description_scores =
                    (empty($site[self::DESCRIPTION_SCORES])) ? [] :
                    $site[self::DESCRIPTION_SCORES];
                $user_ranks =
                    (empty($site[self::USER_RANKS])) ? [] :
                    $site[self::USER_RANKS];
                $shard->addDocumentWords($doc_keys, $offset,
                    $word_lists, $meta_ids, true, false,
                    $description_scores, $user_ranks);
                $offset = $object[0];
                $interim_elapse = changeInMicrotime($interim_time);
                if ($interim_elapse > 5) {
                    crawlLog("..Inverting " . $link_to . $site_url .
                    "...took > 5s.");
                }
                crawlTimeoutLog("..Still building inverted index. Have ".
                    "processed %s of %s documents.\nLast url processed was %s.",
                    $cnt, $num_partition, $link_to . $site_url);
            }
            $seen_partition += $num_to_get;
        }
        $shard->save(false, true);
        crawlLog("  Build inverted index time ".
            changeInMicrotime($start_time));
    }
    /**
     * Forces the current shard to be saved
     */
    public function forceSave()
    {
        if ($this->incremental) {
            $this->getActiveShard()->save(false, true);
        } else {
            $this->buildInvertedIndexShard();
        }
    }
    /**
     * Used when a crawl stops to perform final dictionary operations
     * to produce a working stand-alone index.
     */
    public function stopIndexingBundle()
    {
        $this->forceSave();
        $this->addAdvanceGeneration();
        $this->dictionary->mergeAllTiers();
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
        $words_array = [];
        if (!is_array($word_keys) || count($word_keys) < 1) {
            return null;
        }
        foreach ($word_keys as $word_key) {
            $tmp = $this->dictionary->getWordInfo($word_key);
            if ($tmp === false) {
                $words_array[$word_key] = 0;
            } else {
                $count = 0;
                foreach ($tmp as $entry) {
                    $count += $entry[3];
                }
                $words_array[$word_key] = $count;
            }
        }
        return $words_array;
    }
    /**
     * Gets the description, count of summaries, and number of partitions of the
     * summaries store in the supplied directory. If the file
     * arc_description.txt exists, this is viewed as a dummy index archive for
     * the sole purpose of allowing conversions of downloaded data such as arc
     * files into Yioop! format.
     *
     * @param string $dir_name path to a directory containing a summaries
     *      WebArchiveBundle
     * @return array summary of the given archive
     */
    public static function getArchiveInfo($dir_name)
    {
        if (file_exists($dir_name . "/arc_description.txt")) {
            $crawl = [];
            $info = [];
            $crawl['DESCRIPTION'] = substr(
                file_get_contents($dir_name . "/arc_description.txt"), 0, 256);
            $crawl['ARCFILE'] = true;
            $info['VISITED_URLS_COUNT'] = 0;
            $info['COUNT'] = 0;
            $info['NUM_DOCS_PER_PARTITION'] = 0;
            $info['WRITE_PARTITION'] = 0;
            $info['DESCRIPTION'] = serialize($crawl);
            return $info;
        }
        if (file_exists($dir_name . "/description.txt")) {
            $info = WebArchiveBundle::getArchiveInfo($dir_name);
            if (isset($info['DESCRIPTION'])) {
                return $info;
            }
        }
        return WebArchiveBundle::getArchiveInfo($dir_name . "/summaries");
    }
    /**
     * Sets the archive info struct for the web archive bundle associated with
     * this bundle. This struct has fields like: DESCRIPTION
     * (serialied store of global parameters of the crawl like seed sites,
     * timestamp, etc),  COUNT (num urls seen + pages seen stored),
     * VISITED_URLS_COUNT (number of pages seen while crawling),
     * NUM_DOCS_PER_PARTITION (how many doc/web archive in bundle).
     *
     * @param string $dir_name folder with archive bundle
     * @param array $info struct with above fields
     */
    public static function setArchiveInfo($dir_name, $info)
    {
        WebArchiveBundle::setArchiveInfo($dir_name . "/summaries", $info);
    }
    /**
     * Returns the last time the archive info of the bundle was modified.
     *
     * @param string $dir_name folder with archive bundle
     */
    public static function getParamModifiedTime($dir_name)
    {
        return WebArchiveBundle::getParamModifiedTime($dir_name . "/summaries");
    }
}
