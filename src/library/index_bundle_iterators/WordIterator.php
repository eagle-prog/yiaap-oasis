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
namespace seekquarry\yioop\library\index_bundle_iterators;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\IndexManager;

/**
 * Used to iterate through the documents associated with a word in
 * an IndexArchiveBundle. It also makes it easy to get the summaries
 * of these documents.
 *
 * A description of how words and the documents containing them are stored
 * is given in the documentation of IndexArchiveBundle.
 *
 * @author Chris Pollett
 * @see IndexArchiveBundle
 */
class WordIterator extends IndexBundleIterator
{
    /**
     * hash of word or phrase that the iterator iterates over
     * @var string
     */
    public $word_key;
    /**
     * Word key above in our modified base 64 encoding
     * @var string
     */
    public $base64_word_key;
    /**
     * Whether word key corresponds to a meta word
     * @var string
     */
    public $is_meta;
    /**
     * The timestamp of the index is associated with this iterator
     * @var string
     */
    public $index_name;
    /**
     * First shard generation that word info was obtained for
     * @var int
     */
    public $start_generation;
    /**
     * Used to keep track of whether getWordInfo might still get more
     * data on the search terms as advance generations
     * @var bool
     */
    public $no_more_generations;
    /**
     * The next byte offset in the IndexShard
     * @var int
     */
    public $next_offset;
    /**
     * An array of shard generation and posting list offsets, lengths, and
     * numbers of documents
     * @var array
     */
    public $dictionary_info;
    /**
     * The total number of shards that have data for this word
     * @var int
     */
    public $num_generations;
    /**
     * Index into dictionary_info corresponding to the current shard
     * @var int
     */
    public $generation_pointer;
    /**
     * Numeric number of current shard
     * @var int
     */
    public $current_generation;
    /**
     * The current byte offset in the IndexShard
     * @var int
     */
    public $current_offset;
    /**
     * Starting Offset of word occurrence in the IndexShard
     * @var int
     */
    public $start_offset;
    /**
     * Last Offset of word occurrence in the IndexShard
     * @var int
     */
    public $last_offset;
    /**
     * Keeps track of whether the word_iterator list is empty because the
     * word does not appear in the index shard
     * @var int
     */
    public $empty;
    /**
     * Model responsible for keeping track of edited and deleted search results
     * @var SearchfiltersModel
     */
    public $filter;
    /**
     * The current value of the doc_offset of current posting if known
     * @var int
     */
    public $current_doc_offset;
    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;
    /** Length of a doc key*/
    const KEY_LEN = 8;
    /**
     * Creates a word iterator with the given parameters.
     *
     * @param string $word_key hash of word or phrase to iterate docs of
     * @param string $index_name time_stamp of the to use
     * @param bool $raw whether the $word_key is our variant of base64 encoded
     * @param SearchfiltersModel $filter Model responsible for keeping track
     *      of edited and deleted search results
     * @param int $results_per_block the maximum number of results that can
     *      be returned by a findDocsWithWord call
     * @param int $direction when results are access from $index_name in
     *      which order they should be presented. self::ASCENDING is from first
     *      added to last added, self::DESCENDING is from last added to first
     *      added. Note: this value is not saved permanently. So you
     *      could in theory open two read only versions of the same bundle but
     *      reading the results in different directions
     */
    public function __construct($word_key, $index_name, $raw = false,
        $filter = null, $results_per_block =
        IndexBundleIterator::RESULTS_PER_BLOCK, $direction=self::ASCENDING)
    {
        if ($raw == false) {
            //get rid of out modified base64 encoding
            $word_key = L\unbase64Hash($word_key);
        }
        $this->is_meta = (strpos(substr($word_key, 9), ":") !== false);
        $this->direction = $direction;
        $this->filter = $filter;
        $this->word_key = $word_key;
        $this->base64_word_key = L\base64Hash($word_key);
        $this->index_name = $index_name;
        list($this->num_docs, $this->dictionary_info) =
            IndexManager::getWordInfo($index_name, $word_key,
            -1, -1, C\NUM_DISTINCT_GENERATIONS, true);
        if ($this->dictionary_info === false) {
            $this->empty = true;
        } else {
            ksort($this->dictionary_info);
            $this->dictionary_info = array_values($this->dictionary_info);
            $this->num_generations = count($this->dictionary_info);
            if ($this->num_generations == 0) {
                $this->empty = true;
            } else {
                $this->empty = false;
            }
        }
        $this->no_more_generations =
            ($this->num_generations < C\NUM_DISTINCT_GENERATIONS);
        $this->current_doc_offset = null;
        $this->results_per_block = $results_per_block;
        $this->current_block_fresh = false;
        if ($direction == self::ASCENDING)
        $this->start_generation = ($direction == self::ASCENDING) ? 0 :
            $this->num_generations - 1;
        if ($this->dictionary_info !== false) {
            $this->reset();
        }
    }
    /**
     * Returns CrawlConstants::ASCENDING or CrawlConstants::DESCENDING
     * depending on the direction in which this iterator ttraverse the
     * underlying index archive bundle.
     *
     * @return int direction traversing underlying archive bundle
     */
    public function getDirection()
    {
        return $this->direction;
    }
    /**
     * Resets the iterator to the first document block that it could iterate
     * over
     */
    public function reset()
    {
        if (!$this->empty) {//we shouldn't be called when empty - but to be safe
            if ($this->start_generation > 0) {
                list($this->num_docs, $this->dictionary_info) =
                    IndexManager::getWordInfo($this->index_name,
                    $this->word_key, 0, -1, 0, C\NUM_DISTINCT_GENERATIONS,
                    true);
                ksort($this->dictionary_info);
                $this->dictionary_info = array_values($this->dictionary_info);
                $this->num_generations = count($this->dictionary_info);
                $this->no_more_generations =
                    ($this->num_generations < C\NUM_DISTINCT_GENERATIONS);
            }
            $info = ($this->direction == self::ASCENDING) ?
                $this->dictionary_info[0] : $this->dictionary_info[
                $this->num_generations - 1];
            list($this->current_generation, $this->start_offset,
                $this->last_offset, ) = $info;
        } else {
            $this->start_offset = 0;
            $this->last_offset = -1;
            $this->num_generations = -1;
        }
        if ($this->direction == self::ASCENDING) {
            $this->current_offset = $this->start_offset;
            $this->generation_pointer = 0;
        } else {
            $this->current_offset = $this->last_offset;
            /*  reset pointer to the number of gens, which in reverse is the
               first one we want
             */
            $this->generation_pointer = $this->num_generations - 1;
        }
        $this->count_block = 0;
        $this->seen_docs = 0;
        $this->current_doc_offset = null;
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    public function findDocsWithWord()
    {
        if ($this->empty) {
            return -1;
        }
        $ascending = ($this->direction == self::ASCENDING);
        if ($ascending) {
            if (($this->generation_pointer >= $this->num_generations) ||
                $this->generation_pointer == $this->num_generations - 1 &&
                $this->current_offset > $this->last_offset) {
                return -1;
            }
        } else {
            if (($this->generation_pointer < 0)
                || ($this->generation_pointer == 0 &&
                $this->current_offset < $this->start_offset)) {
                return -1;
            }
        }
        $pre_results = [];
        if (!$this->empty) {
            $this->next_offset = $this->current_offset;
            $index = IndexManager::getIndex($this->index_name);
            $index->setCurrentShard($this->current_generation, true);
            //the next call also updates next offset
            $shard = $index->getCurrentShard(true);
            $pre_results = $shard->getPostingsSlice($this->start_offset,
                $this->next_offset, $this->last_offset,
                $this->results_per_block, $this->direction);
        }
        $results = [];
        $doc_key_len = IndexShard::DOC_KEY_LEN;
        $max_num_discount = IndexManager::discountedNumDocsTerm(
            "link:" . $this->base64_word_key, $this->index_name);
        //check for missing docs;
        $hash_urls_with_docs = [];
        foreach ($pre_results as $keys => $data) {
            $host_key = substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
            if (!empty($this->filter) && $this->filter->isFiltered($host_key)) {
                continue;
            }
            // inlinks is the domain of the inlink
            $key_parts = str_split($keys, $doc_key_len);
            $hash_url = $key_parts[0];
            if (!in_array($hash_url, $hash_urls_with_docs)) {
                if (!empty($data[self::IS_DOC])) {
                    $hash_urls_with_docs[] = $hash_url;
                } else {
                    $hash_doc_term = L\base64Hash($hash_url);
                    $num_discounted_docs = IndexManager::discountedNumDocsTerm(
                        "info:" . $hash_doc_term, $this->index_name);
                    if ($num_discounted_docs > 0) {
                        $hash_urls_with_docs[] = $hash_url;
                    } else {
                        //filter those urls for which we don't have the doc
                        continue;
                    }
                }
            }
            $data[self::KEY] = $keys;
            if (isset($key_parts[2])) {
                list(, $data[self::HASH], $data[self::INLINKS]) =
                    $key_parts;
            } else {
                continue;
            }
            if (!$this->is_meta && $max_num_discount > 0
                && !empty($data[self::IS_DOC])) {
                $hash_doc_term = L\base64Hash($hash_url);
                $num_discounted_docs = IndexManager::discountedNumDocsTerm(
                    "link:" . $this->base64_word_key . ":" .
                    $hash_doc_term, $this->index_name);
                if ($num_discounted_docs > 0) {
                    $discount_score = 10.0 * $num_discounted_docs /
                        $max_num_discount;
                    $data[self::DOC_RANK] += $discount_score;
                    $data[self::SCORE] += $discount_score;
                }
            }
            $data[self::CRAWL_TIME] = $this->index_name;
            $results[$keys] = $data;
        }
        $this->count_block = count($results);
        if ($this->generation_pointer == $this->num_generations - 1 &&
            empty($pre_results)) {
            $results = -1;
        }
        $this->pages = $results;
        return $results;
    }
    /**
     * Updates the seen_docs count during an advance() call
     */
    public function advanceSeenDocs()
    {
        if ($this->current_block_fresh != true) {
            if ($this->direction == self::ASCENDING) {
                $num_docs = min($this->results_per_block,
                    IndexShard::numDocsOrLinks($this->next_offset,
                        $this->last_offset));
                $delta_sign = 1;
            } else {
                $total_guess = IndexShard::numDocsOrLinks($this->start_offset,
                    $this->next_offset);
                $num_docs = $total_guess % $this->results_per_block;
                if ($num_docs == 0) {
                    $num_docs = $this->results_per_block;
                } else {
                    $num_docs = IndexShard::numDocsOrLinks($this->start_offset,
                        $this->last_offset) % $this->results_per_block;
                    if ($num_docs == 0) {
                        $num_docs = $this->results_per_block;
                    }
                }
                $delta_sign = -1;
            }
            $this->next_offset = $this->current_offset;
            $this->next_offset += $delta_sign *
                IndexShard::POSTING_LEN * $num_docs;
            if ($num_docs <= 0) {
                return;
            }
        } else {
            $num_docs = $this->count_block;
        }
        $this->current_block_fresh = false;
        $this->seen_docs += $num_docs;
    }
    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *     the must be of greater than or equal generation, and if equal the
     *     next block must all have $doc_offsets larger than or equal to
     *     this value
     */
    public function advance($gen_doc_offset = null)
    {
        if ($gen_doc_offset == null) {
            $this->plainAdvance();
            return;
        }
        $is_ascending = ($this->direction == self::ASCENDING);
        $cur_gen_doc_offset = $this->currentGenDocOffsetWithWord();
        if ($cur_gen_doc_offset == -1 ||
            $this->genDocOffsetCmp($cur_gen_doc_offset,
            $gen_doc_offset, $this->direction) >= 0) {
            return;
        }
        $this->plainAdvance();
        $advance_check = ($is_ascending) ?
            ($this->current_generation < $gen_doc_offset[0]) :
            ($this->current_generation > $gen_doc_offset[0]);
        if ($advance_check) {
            $this->advanceGeneration($gen_doc_offset[0]);
            $this->next_offset = $this->current_offset;
        }
        $index = IndexManager::getIndex($this->index_name);
        $index->setCurrentShard($this->current_generation, true);
        $shard = $index->getCurrentShard();
        if ($is_ascending) {
            $end_point = $this->last_offset;
        } else {
            $end_point = $this->start_offset;
        }
        if ($this->current_generation == $gen_doc_offset[0]) {
            $offset_pair = $shard->nextPostingOffsetDocOffset(
                $this->next_offset, $end_point, $gen_doc_offset[1],
                $this->direction);
            if ($offset_pair === false) {
                $this->advanceGeneration();
                $this->next_offset = $this->current_offset;
            } else {
                list($this->current_offset, $this->current_doc_offset) =
                    $offset_pair;
            }
        }
        if ($is_ascending) {
            $this->seen_docs = ($this->current_offset - $this->start_offset) /
                IndexShard::POSTING_LEN;
        } else {
            $this->seen_docs = ($this->last_offset - $this->current_offset) /
                IndexShard::POSTING_LEN;
        }
    }
    /**
     * Forwards the iterator one group of docs. This is what's called
     * by @see advance($gen_doc_offset) if $gen_doc_offset is null
     */
    public function plainAdvance()
    {
        $is_ascending = ($this->direction == self::ASCENDING);
        $this->advanceSeenDocs();
        $this->current_doc_offset = null;
        $update_check = ($is_ascending) ?
            ($this->current_offset < $this->next_offset) :
            ($this->current_offset > $this->next_offset);
        if ($update_check) {
            $this->current_offset = $this->next_offset;
        } else {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        $update_check = ($is_ascending) ?
            ($this->current_offset > $this->last_offset) :
            ($this->current_offset < $this->start_offset);
        if ($update_check) {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
    }
    /**
     * Switches which index shard is being used to return occurrences of
     * the word to the next shard containing the word
     *
     * @param int $generation generation to advance beyond
     */
    public function advanceGeneration($generation = null)
    {
        if ($generation === null) {
            $generation = $this->current_generation;
        }
        $is_ascending = ($this->direction == self::ASCENDING);
        do {
            $gen_check = ($is_ascending) ?
                ($this->generation_pointer < $this->num_generations) :
                ($this->generation_pointer >= 0);
            if ($gen_check) {
                if ($is_ascending) {
                    $this->generation_pointer++;
                } else {
                    $this->generation_pointer--;
                }
            }
            $gen_check = ($is_ascending) ?
                $this->generation_pointer < $this->num_generations :
                $this->generation_pointer >= 0;
            if ($gen_check) {
                list($this->current_generation, $this->start_offset,
                    $this->last_offset, )
                    = $this->dictionary_info[$this->generation_pointer];
                $this->current_offset = ($is_ascending) ? $this->start_offset:
                    $this->last_offset;
            }
            if (!$this->no_more_generations) {
                $gen_check = ($is_ascending) ?
                    ($this->current_generation < $generation &&
                    $this->generation_pointer >= $this->num_generations) :
                    ($this->current_generation > $generation &&
                    $this->generation_pointer <= 0);
                if ($gen_check) {
                    list($estimated_remaining_total, $info) =
                        IndexManager::getWordInfo($this->index_name,
                        $this->word_key, 0, $this->num_generations,
                        C\NUM_DISTINCT_GENERATIONS, true);
                    if (count($info) > 0) {
                        $this->num_docs = $this->seen_docs +
                            $estimated_remaining_total;
                        ksort($info);
                        $this->dictionary_info = array_merge(
                            $this->dictionary_info, array_values($info));
                        $this->num_generations = count($this->dictionary_info);
                        $this->no_more_generations =
                            count($info) < C\NUM_DISTINCT_GENERATIONS;
                        //will increment back to where were next loop
                        if ($is_ascending) {
                            $this->generation_pointer--;
                        } else {
                            $this->generation_pointer++;
                        }
                    }
                }
            }
            $gen_check = ($is_ascending) ?
                ($this->current_generation < $generation &&
                $this->generation_pointer < $this->num_generations) :
                ($this->current_generation > $generation &&
                $this->generation_pointer >= 0);
        } while($gen_check);
    }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    public function currentGenDocOffsetWithWord()
    {
        if ($this->current_doc_offset !== null) {
            return [$this->current_generation, $this->current_doc_offset];
        }
        $is_ascending = ($this->direction == self::ASCENDING);
        $offset_check = ($is_ascending) ?
            ($this->current_offset > $this->last_offset ||
            $this->generation_pointer >= $this->num_generations) :
            ($this->current_offset < $this->start_offset||
            $this->generation_pointer < -1);
        if ($offset_check) {
            return -1;
        }
        $index = IndexManager::getIndex($this->index_name);
        $index->setCurrentShard($this->current_generation, true);
        $this->current_doc_offset = $index->getCurrentShard(
            )->docOffsetFromPostingOffset($this->current_offset);
        return [$this->current_generation, $this->current_doc_offset];
    }
}
