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
 * Load charCopy
 */
require_once __DIR__ . "/Utility.php";
/**
 * Data structure used to store one generation worth of the word document
 * index (inverted index). This data structure consists of three main
 * components a word entries, word_doc entries, and document entries.
 *
 * Word entries are described in the documentation for the words field.
 * Word-doc entries are described in the documentation for the word_docs field
 * Document entries are described in the documentation for the doc_infos field
 *
 * IndexShards also have two access modes a $read_only_from_disk mode and
 * a loaded in memory mode. Loaded in memory mode is mainly for writing new
 * data to the shard. When in memory, data in the shard can also be in one of
 * two states packed or unpacked. Roughly, when it is in a packed state it is
 * ready to be serialized to disk; when it is an unpacked state it methods
 * for adding data can be used.
 *
 * Serialized on disk, a shard has a header with document statistics followed
 * by the a prefix index into the words component, followed by the word
 * component itself, then the word-docs component, and finally the document
 * component.
 *
 * @author Chris Pollett
 */
class IndexShard extends PersistentStructure implements CrawlConstants
{
    /**
     * Stores document id's and links to documents id's together with
     * summary offset information, and number of words in the doc/link
     * The format for a record is 4 byte offset, followed by
     * 3 bytes for the document length, followed by 1 byte containing
     * the number of 8 byte doc key strings that make up the doc id (2 for
     * a doc, 3 for a link), followed by the doc key strings themselves.
     * In the case of a document the first doc key string has a hash of the
     * url, the second a hash a tag stripped version of the document.
     * In the case of a link, the keys are a unique identifier for the link
     * context, followed by  8 bytes for
     * the hash of the url being pointed to by the link, followed by 8
     * bytes for the hash of "info:url_pointed_to_by_link".
     * @var string
     */
    public $doc_infos;
    /**
     * Length of $doc_infos as a string
     * @var int
     */
    public $docids_len;
    /**
     * This string is non-empty when shard is loaded and in its packed state.
     * It consists of a sequence of posting records. Each posting
     * consists of a offset into the document entries structure
     * for a document containing the word this is the posting for,
     * as well as the number of occurrences of that word in that document.
     * @var string
     */
    public $word_docs;
    /**
     * Length of $word_docs as a string
     * @var int
     */
    public $word_docs_len;
    /**
     * Stores the array of word entries for this shard
     * In the packed state, word entries consist of the word id,
     * a generation number, an offset into the word_docs structure
     * where the posting list for that word begins,
     * and a length of this posting list. In the unpacked state
     * each entry is a string of all the posting items for that word
     * Periodically data in this words array is flattened to the word_postings
     * string which is a more memory efficient was of storing data in PHP
     * @var array
     */
    public $words;
    /**
     * Stores length of the words array in the shard on disk. Only set if
     * we're in $read_only_from_disk mode
     *
     * @var int
     */
     public $words_len;
    /**
     * An array representing offsets into the words dictionary of the index of
     * the first occurrence of a two byte prefix of a word_id.
     *
     * @var array
     */
    public $prefixes;
    /**
     * Length of the prefix index into the dictionary of the shard
     *
     * @var int
     */
    public $prefixes_len;
    /**
     * This is supposed to hold the number of earlier shards, prior to the
     * current shard.
     * @var int
     */
    public $generation;
    /**
     * This is supposed to hold the number of documents that a given shard can
     * hold.
     * @var int
     */
    public $num_docs_per_generation;
    /**
     * Number of documents (not links) stored in this shard
     * @var int
     */
    public $num_docs;
    /**
     * Keeps track of the number of documents a word is in
     * @var array
     */
    public $num_docs_word;
    /**
     * Number of links (not documents) stored in this shard
     * @var int
     */
    public $num_link_docs;
    /**
     * Number of words stored in total in all documents in this shard
     * @var int
     */
    public $len_all_docs;
    /**
     * Number of words stored in total in all links in this shard
     * @var int
     */
    public $len_all_link_docs;
    /**
     * File handle for a shard if we are going to use it in read mode
     * and not completely load it.
     *
     * @var resource
     */
    public $fh;
    /**
     * An cached array of disk blocks for an index shard that has not
     * been completely loaded into memory.
     * @var array
     */
    public $blocks;
    /**
     * Flag used to determined if this shard is going to be largely kept on
     * disk and to be in read only mode. Otherwise, shard will assume to
     * be completely held in memory and be read/writable.
     * @var bool
     */
    public $read_only_from_disk;
    /**
     * Keeps track of the packed/unpacked state of the word_docs list
     *
     * @var bool
     */
    public $word_docs_packed;
    /**
     * Keeps track of the length of the shard as a file
     *
     * @var int
     */
    public $file_len;
    /**
     * Number of document inserts since the last time word data was flattened
     * to the word_postings string.
     */
     public $last_flattened_words_count;
    /**
     * Used to hold word_id, posting_len, posting triples as a memory efficient
     * string
     * @var string
     */
    public $word_postings;
    /**
     * Fraction of NUM_DOCS_PER_GENERATION document inserts before data
     * from the words array is flattened to word_postings. (It will
     * also be flattened during periodic index saves)
     */
    const FLATTEN_FREQUENCY = 10000;
    /**
     * Bytes of tmp string allowed during flattenings
     */
     const WORD_POSTING_COPY_LEN = 32000;
    /**
     * Used to keep track of whether a record in document infos is for a
     * document or for a link
     */
    const LINK_FLAG =  0x800000;
    /**
     * Shard block size is 1<< this power
     */
    const SHARD_BLOCK_POWER = 12;
    /**
     * Size in bytes of one block in IndexShard
     */
    const SHARD_BLOCK_SIZE = 4096;
    /**
     * Header Length of an IndexShard (sum of its non-variable length fields)
     */
    const HEADER_LENGTH = 40;
    /**
     * Length of the data portion of a word entry in bytes in the shard
     */
    const WORD_DATA_LEN = 12;
    /**
     * Length of a word entry's key in bytes
     */
    const WORD_KEY_LEN = 20;
    /**
     * Length of a key in a DOC ID.
     */
    const DOC_KEY_LEN = 8;
    /**
     * Length of  DOC ID.
     */
    const DOC_ID_LEN = 24;
    /**
     * Maximum number of auxiliary document keys;
     */
    const MAX_AUX_DOC_KEYS = 200;
    /**
     * Length of one posting ( a doc offset occurrence pair) in a posting list
     */
    const POSTING_LEN = 4;
    /**
     * Represents an empty prefix item
     */
    const BLANK = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";
    /**
     * Flag used to indicate that a word item should not be packed or unpacked
     */
    const HALF_BLANK = "\xFF\xFF\xFF\xFF";
    /**
     * Represents an empty prefix item
     */
    const STORE_FLAG = "\x80";
    /**
     * Makes an index shard with the given file name and generation offset
     *
     * @param string $fname filename to store the index shard with
     * @param int $generation when returning documents from the shard
     *      pretend there are this many earlier documents
     * @param int $num_docs_per_generation the number of documents that a
     *      given shard can hold.
     * @param bool $read_only_from_disk used to determined if this shard is
     *      going to be largely kept on disk and to be in read only mode.
     *      Otherwise, shard will assume to be completely held in memory and be
     *      read/writable.
     */
    public function __construct($fname, $generation = 0,
        $num_docs_per_generation = C\NUM_DOCS_PER_GENERATION,
        $read_only_from_disk = false)
    {
        parent::__construct($fname, -1);
        $this->hash_name = crawlHash($fname);
        $this->generation = $generation;
        $this->num_docs_per_generation = $num_docs_per_generation;
        $this->word_docs = "";
        $this->word_postings = "";
        $this->words_len = 0;
        $this->word_docs_len = 0;
        $this->last_flattened_words_count = 0;
        $this->words = [];
        $this->docids_len = 0;
        $this->doc_infos = "";
        $this->num_docs = 0;
        $this->num_link_docs = 0;
        $this->len_all_docs = 0;
        $this->len_all_link_docs = 0;
        $this->blocks = [];
        $this->fh = null;
        $this->read_only_from_disk = $read_only_from_disk;
        $this->word_docs_packed = false;
        $this->blocks_words= [];
    }
    /**
     * Used to pack a list of description scores and user ranks as a
     * string of auxiliary keys for a document map entry in the shard.
     * A document map entry consists of a four byte offset into a WebArchive,
     * three more bytes for the document length as, one byte for the
     * number of 8 byte aux keys, followed by a 24 byte key derived usually
     * from the url, host, etc, followed by the description scores,
     * user rank auxiliary keys.
     *
     * @param array $description_scores pairs position in document =>
     *      weight score that position got during summarization process.
     * @param array $user_ranks float scores gotten by a user classifier/ranker
     *      defined using Manage Classfiers.
     * @return string a string padded to length a multiple of 16 where
     *      @see packValues has been used to map each of the above array into a
     *      string
     */
    public function packAuxiliaryDocumentKeys($description_scores = [],
        $user_ranks = [])
    {
        $max_short = 2<<16 - 1;
        $aux_keys = "";
        $num_description_scores = count($description_scores);
        $num_ranks = count($user_ranks);
        if ($num_description_scores + $num_ranks > self::MAX_AUX_DOC_KEYS) {
            return $aux_keys;
        }
        $description_positions = ($num_description_scores > 0) ?
            deltaList(array_keys($description_scores)) : [];
        if ($num_description_scores > 0 && max($description_positions) <
            $max_short) {
            $aux_keys = "\xFF\xFF" . $this->packValues($description_positions) .
                $this->packValues($description_scores, "f");
        }
        if ($num_ranks > 0) {
            $aux_keys .= $this->packValues($user_ranks, "f");
        }
        $pad_len = 8 - (strlen($aux_keys) % 8);
        $padding = str_pad("", $pad_len, "\x00");
        return $aux_keys . $padding;
    }
    /**
     * Used to unpack a list of description scores and user ranks from a
     * document map entry in the shard. We assume these score were packed
     * using @see packAuxiliaryDocumentKeys.
     *
     * @param string $packed_data containing packed description scores and user
     *      ranks
     * @param int $offset where in the string to begin unpacking from
     * @return array [$description_scores, $user_ranks]
     */
    public function unpackAuxiliaryDocumentKeys($packed_data, $offset = 0)
    {
        $description_scores = [];
        if (substr($packed_data, $offset, 2) == "\xFF\xFF") {
            list($description_positions, $offset) =
                $this->unpackValues($packed_data, $offset + 2);
            deDeltaList($description_positions);
            list($description_weights, $offset) =
                $this->unpackValues($packed_data, $offset, "f");
            $description_scores = array_combine($description_positions,
                $description_weights);
        }
        list($user_ranks, $offset) = $this->unpackValues($packed_data,
            $offset, "f");
        return [$description_scores, $user_ranks];
    }
    /**
     * Used to pack either an array of  nonnegative ints each less than
     * 65535 or array of floats. Pack is done into a string of 2 bytes/
     * entry shorts.
     * @param array $values nonnegative integers or floats to pack
     * @param string $type if is "i" then assuming integers we are packing
     *      otherwise floats
     * @return string with packed values
     */
    public function packValues($values, $type = "i")
    {
        $num_values = count($values);
        if ($type == "i") {
            array_unshift($values, $num_values);
            array_unshift($values, "S*");
            return call_user_func_array("pack", $values);
        }
        $max_short = 2<<16 - 1;
        $max_minus_one = $max_short - 1;
        $packed_values = pack("S", $num_values);
        foreach ($values as $key => $value) {
            $short_value = floor($value * $max_short);
            if ($short_value > $max_minus_one) {
                $short_value = $max_minus_one;
            }
            $packed_values .= pack("S", $short_value);
        }
        return $packed_values;
    }
    /**
     * Used to unpack from a string an an array of short nonnegative ints or
     * 2 byte floats. @see packValues
     * @param string $type if is "i" then assuming integers we are unpacking
     *      otherwise floats
     * @return array [unpacked values array, offset to where processed to in
     *      string]
     */
    public function unpackValues($packed_data, $offset = 0, $type = 'i')
    {
        $max_short = 2<<16 - 1;
        $len_packed = strlen($packed_data);
        if ($len_packed < $offset + 2 ) {
            return [[], $offset];
        }
        $num_values = (unpack("S", substr($packed_data, $offset, 2)))[1];
        $new_offset = $offset + 2 * ($num_values + 1);
        if ($len_packed < $new_offset) {
            return [[], $offset];
        }
        $values = array_values(unpack("S*", substr($packed_data,
            $offset + 2, 2 * $num_values)));
        if ($type == 'i') {
            return [$values, $new_offset];
        }
        foreach ($values as $k => $value) {
            $values[$k] = $value/$max_short;
        }
        return [$values, $new_offset];
    }
    /**
     * Add a new document to the index shard with the given summary offset.
     * Associate with this document the supplied list of words and word counts.
     * Finally, associate the given meta words with this document.
     *
     * @param string $doc_keys a string of concatenated keys for a document
     *     to insert. Each key is assumed to be a string of DOC_KEY_LEN many
     *     bytes. This whole set of keys is viewed as fixing one document.
     * @param int $summary_offset its offset into the word archive the
     *     document's data is stored in
     * @param array $word_lists (word => array of word positions in doc)
     * @param array $meta_ids meta words to be associated with the document
     *     an example meta word would be filetype:pdf for a PDF document.
     * @param bool $is_doc flag used to indicate if what is being sored is
     *     a document or a link to a document
     * @param mixed $rank either false if not used, or a 4 bit estimate of the
     *     rank of this document item
     * @param array $description_scores
     * @param array $user_ranks
     * @return bool success or failure of performing the add
     */
    public function addDocumentWords($doc_keys, $summary_offset, $word_lists,
        $meta_ids = [], $is_doc = false, $rank = false,
        $description_scores = [], $user_ranks = [])
    {
        if ($this->word_docs_packed == true) {
            $this->words = [];
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }
        $doc_len = 0;
        $link_doc_len = 0;
        $doc_keys .= $this->packAuxiliaryDocumentKeys($description_scores,
            $user_ranks);
        $len_key = strlen($doc_keys);
        $num_keys = floor($len_key/self::DOC_KEY_LEN);
        if ($num_keys * self::DOC_KEY_LEN != $len_key) {
            return false;
        }
        if ($num_keys % 2 == 0 ) {
            $doc_keys .= self::BLANK; //want to keep docids_len divisible by 16
        }
        $summary_offset_string = packInt($summary_offset);
        $added_len = strlen($summary_offset_string);
        $this->doc_infos .= $summary_offset_string;
        if ($is_doc) {
            $this->num_docs++;
        } else { //link item
            $this->num_link_docs++;
        }
        foreach ($meta_ids as $meta_id) {
            $word_lists[$meta_id] = [];
        }
        //using $this->docids_len divisible by 16
        $doc_offset = $this->docids_len >> 4;
        foreach ($word_lists as $word => $position_list) {
            $word_id = crawlHashWord($word, true);
            $occurrences = count($position_list);
            $store = packPosting($doc_offset, $position_list);
            if (!isset($this->words[$word_id])) {
                $this->words[$word_id] = $store;
            } else {
                $this->words[$word_id] .= $store;
            }
            if (!isset($this->num_docs_word[$word_id])) {
                $this->num_docs_word[$word_id] = 1;
            } else {
                $this->num_docs_word[$word_id]++;
            }
            if ($occurrences > 0) {
                if ($is_doc == true) {
                    $doc_len += $occurrences;
                } else {
                    $link_doc_len += $occurrences;
                }
            }
            $this->word_docs_len += strlen($store);
        }
        $this->len_all_docs += $doc_len;
        $this->len_all_link_docs += $link_doc_len;
        $flags = ($is_doc) ? 0 : self::LINK_FLAG;
        if ($rank !== false) {
            $rank &= 0x0f;
            $rank <<= 19;
            $flags += $rank;
        }
        $item_len = ($is_doc) ? $doc_len: $link_doc_len;
        $len_num_keys = $this->packDoclenNum(($flags + $item_len), $num_keys);
        $this->doc_infos .=  $len_num_keys;
        $added_len += strlen($len_num_keys);
        $this->doc_infos .= $doc_keys;
        $added_len += strlen($doc_keys);
        $this->docids_len += $added_len;
        return true;
    }
    /**
     * Returns the first offset, last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word.
     *
     * @param string $word_id id of the word one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or not
     * @return array first offset, last offset, count, exact matching id
     */
    public function getWordInfo($word_id, $raw = false)
    {
        if ($raw == false) {
            //get rid of out modified base64 encoding
            $word_id = unbase64Hash($word_id);
        }
        $is_disk = $this->read_only_from_disk;
        $word_item_len = self::WORD_KEY_LEN + self::WORD_DATA_LEN;
        $word_key_len = self::WORD_KEY_LEN;
        if ($is_disk) {
            $this->getShardHeader();
            if (!isset($word_id[1])) {
                return false;
            }
            $prefix = (ord($word_id[0]) << 8) + ord($word_id[1]);
            $prefix_info = $this->getShardSubstring(
                self::HEADER_LENGTH + 8 * $prefix, 8);
            if ($prefix_info == self::BLANK || !isset($prefix_info[2])) {
                return false;
            }
            list(,$offset, $high) = unpack("N*", $prefix_info);
            $high--;
            $start = self::HEADER_LENGTH + $this->prefixes_len  + $offset;
        } else {
            if ($this->word_docs_packed == false) {
                $this->mergeWordPostingsToString();
                $this->packWords(null);
                $this->outputPostingLists();
            }
            $start = 0;
            $high = (strlen($this->words) - $word_item_len)/$word_item_len;
        }
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        do {
            $old_check_loc = $check_loc;
            $word_string = $this->getWordString($is_disk, $start, $check_loc,
                $word_item_len);
            if ($word_string == false) {
                return false;
            }
            $id = substr($word_string, 0, $word_key_len);
            $cmp = compareWordHashes($word_id, $id);
            if ($cmp === 0) {
                $tmp_info = $this->getWordInfoFromString(
                    substr($word_string, $word_key_len));
                $tmp_info[] = $id;
                return $tmp_info;
            } else if ($cmp < 0) {
                $high = $check_loc;
                $check_loc = (($low + $check_loc) >> 1);
            } else {
                if ($check_loc + 1 == $high) {
                    $check_loc++;
                }
                $low = $check_loc;
                $check_loc = (($high + $check_loc) >> 1);
            }
        } while($old_check_loc != $check_loc);
        return false;
    }
    /**
     *  Return word record (word key + posting lookup data )from the shard
     *  from the shard posting list
     *
     * @param bool $is_disk whether the shard is on disk or in memory
     * @param int $start offset to start of the dictionary
     * @param int $location index of record to extract from dictionary
     * @param int $word_item_len length of a word + data record
     */
    function getWordString($is_disk, $start, $location, $word_item_len)
    {
        if ($is_disk) {
            $word_string = $this->getShardSubstring($start +
                $location * $word_item_len, $word_item_len);
        } else {
            $word_string = substr($this->words, $start +
                $location * $word_item_len, $word_item_len);
        }
        return $word_string;
    }
    /**
     * Returns documents using the word_docs string (either as stored
     * on disk or completely read in) of records starting
     * at the given offset and using its link-list of records. Traversal of
     * the list stops if an offset larger than $last_offset is seen or
     * $len many doc's have been returned. Since $next_offset is passed by
     * reference the value of $next_offset will point to the next record in
     * the list (if it exists) after the function is called.
     *
     * @param int $start_offset of the current posting list for query term
     *     used in calculating BM25F.
     * @param int &$next_offset where to start in word docs
     * @param int $last_offset offset at which to stop by
     * @param int $len number of documents desired
     * @param int $direction which direction to iterate through elements
     *      of the posting slice (self::ASCENDING or self::DESCENDING) as
     *      compared to the order of when they were stored
     * @return array desired list of doc's and their info
     */
    public function getPostingsSlice($start_offset, &$next_offset, $last_offset,
        $len, $direction = self::ASCENDING)
    {
        if (!$this->read_only_from_disk && !$this->word_docs_packed) {
            $this->mergeWordPostingsToString();
            $this->packWords(null);
            $this->outputPostingLists();
        } else if ($this->read_only_from_disk && empty($this->num_docs)) {
            $this->getShardHeader();
        }
        // Normal ASCENDING iterator (same order as stored)
        if ($direction == self::ASCENDING) {
            return $this->postingsSliceAscending($start_offset, $next_offset,
                $last_offset, $len);
        } else {
            // Reverse direction used most commonly for feeds
            return $this->postingsSliceDescending($start_offset, $next_offset,
                $last_offset, $len);
        }
    }
    /**
     *
     * @param int $start_offset
     * @param int &$next_offset
     * @param int $last_offset
     * @param int $len
     * @return array
     */
    public function postingsSliceAscending($start_offset, &$next_offset,
        $last_offset, $len)
    {
        $num_docs_so_far = 0;
        $results = [];
        /* wd_len is a kludgy fix because word_docs_len can get out of sync
           when things are file-based and am still tracking down why
        */
        $wd_len = (isset($this->file_len)) ?
            $this->file_len - $this->docids_len : $this->word_docs_len;
        $end = min($wd_len, $last_offset) >> 2;
        $last = $last_offset >> 2;
        $next = $next_offset >> 2;
        $posting_end = $next;
        $total_posting_len = 0;
        $num_postings_so_far = 0;
        do {
            if ($next > $end) {
                break;
            }
            $posting_start = $next;
            $posting = $this->getPostingAtOffset(
                $next, $posting_start, $posting_end);
            $total_posting_len += strlen($posting);
            $num_postings_so_far++;
            $next = $posting_end + 1;
            $num_docs_or_links =
                self::numDocsOrLinks($start_offset, $last_offset,
                    $total_posting_len / $num_postings_so_far);
            list($doc_id, , $item) =
                $this->makeItem($posting, $num_docs_or_links, self::ASCENDING);
            $results[$doc_id] = $item;
            $num_docs_so_far += $next - $posting_start;
        } while ($next <= $last && $num_docs_so_far < $len);
        $next_offset = $next << 2;
        return $results;
    }
    /**
     * @param int $start_offset
     * @param int &$next_offset
     * @param int $last_offset
     * @param int $len
     * @return array
     */
    public function postingsSliceDescending($start_offset, &$next_offset,
        $last_offset, $len)
    {
        $num_docs_so_far = 0;
        $results = [];
        /* wd_len is a kludgy fix because word_docs_len can get out of sync
           when things are file-based and am still tracking down why
        */
        $wd_len = (isset($this->file_len)) ?
            $this->file_len - $this->docids_len : $this->word_docs_len;
        /*  For a reverse shard, the arguments for start offset and
            last offset are the same. It actually gets reversed here,
            where end:=start and last:=start.
        */
        $end = $start_offset >> 2;
        $last = $start_offset >> 2;
        $next = $next_offset >> 2;
        $posting_start = $next;
        $total_posting_len = 0;
        $num_postings_so_far = 0;
        $stop = 0;
        do {
            if ($next < $end) {
                break;
            }
            $posting_end = $next;
            /* getPostingAtOffset will modify both start and end to the value of
               next using addresses
             */
            $posting = $this->getPostingAtOffset(
                $next, $posting_start, $posting_end);
            $total_posting_len += strlen($posting);
            $num_postings_so_far++;
            $next = $posting_start - 1;
            // getting the number of docs is the same ascending as descending
            $num_docs_or_links =
                self::numDocsOrLinks($start_offset, $last_offset,
                    $total_posting_len / $num_postings_so_far);
            list($doc_id, , $item) =
                $this->makeItem($posting, $num_docs_or_links, self::DESCENDING);
            $results[$doc_id] = $item;
            $num_docs_so_far += $posting_end - $next;
        } while ($next >= $last && $num_docs_so_far < $len);
        $next_offset = $next << 2;
        return $results;
    }
    /**
     * An upper bound on the number of docs or links represented by
     * the start and ending integer offsets into a posting list.
     *
     * @param int $start_offset starting location in posting list
     * @param int $last_offset ending location in posting list
     * @param float $avg_posting_len number of bytes in an average posting
     *
     * @return int number of docs or links
     */
    public static function numDocsOrLinks($start_offset, $last_offset,
        $avg_posting_len = 4)
    {
        return ceil(($last_offset - $start_offset) /$avg_posting_len);
    }
    /**
     * Return (docid, item) where item has document statistics (summary offset,
     * relevance, doc rank, and score) for the document give by the
     * supplied posting, based on the the posting lists
     * num docs with word, and the number of occurrences of the word in the doc.
     *
     * @param string $posting a posting entry from some words posting list
     * @param int $num_doc_or_links number of documents or links doc appears in
     * @param int $direction whether to compute DOC_RAN based on the assumption
     *      the iterator is traversing the index in an ascending or descending
     *      fashion
     * @return array ($doc_id, posting_stats_array) for posting
     */
    public function makeItem($posting, $num_doc_or_links, $direction =
        self::ASCENDING)
    {
        $doc_key_len = self::DOC_KEY_LEN;
        $offset = 0;
        list($doc_index, $position_list) = unpackPosting($posting, $offset);
        $item = [];
        $item[self::POSITION_LIST] = $position_list;
        if ($direction == self::ASCENDING) {
            $doc_depth = log(($doc_index + 1) + (C\AVG_LINKS_PER_PAGE + 1) *
                $this->num_docs_per_generation * $this->generation, 10);
        } else {
            $doc_depth = log(($this->num_docs_per_generation - $doc_index + 1) +
                (C\MAX_GENERATIONS - (C\AVG_LINKS_PER_PAGE + 1) *
                $this->num_docs_per_generation * $this->generation), 10);
        }
        $item[self::DOC_RANK] = number_format(10 - $doc_depth, C\PRECISION);
        $doc_loc = $doc_index << 4;
        $tmp =
            unpack("N*", $this->getDocInfoSubstring($doc_loc, $doc_key_len));
        if (isset($tmp[2])) {
            list(, $item[self::SUMMARY_OFFSET], $doc_int) = $tmp;
        } else {
            $item[self::SUMMARY_OFFSET] = false;
            $doc_int  = false;
        }
        $num_keys = $doc_int & 255;
        if ($num_keys > 120) { /* shouldn't have more than this many keys
                                fighting index corruption
                                */
            $num_keys = 3;
        }
        $doc_len = ($doc_int >> 8);
        $item[self::GENERATION] = $this->generation;
        $is_doc = (($doc_len & self::LINK_FLAG) == 0) ? true : false;
        $title_length = ($is_doc) ? C\AD_HOC_TITLE_LENGTH : 0;
        if (!$is_doc) {
            $doc_len &= (self::LINK_FLAG - 1);
        }
        $item[self::DOC_LEN] = $doc_len;
        $item[self::IS_DOC] = $is_doc;
        $item[self::PROXIMITY] =
            $this->computeProximity($position_list, $is_doc);
        $item[self::DESCRIPTION_SCORES] = [];
        $doc_id_len = ($num_keys > 3) ? self::DOC_ID_LEN : $num_keys  *
            $doc_key_len; /* original format allowed shorter doc ids,
                keys might be used for other things than ranking */
        $id_pos = $doc_loc + $doc_key_len;
        $doc_id = $this->getDocInfoSubstring($id_pos, self::DOC_ID_LEN);
        if ($num_keys > 3 && $is_doc) {
            $aux_key_string = $this->getDocInfoSubstring(
                $id_pos + self::DOC_ID_LEN, ($num_keys - 3) * $doc_key_len);
            list($item[self::DESCRIPTION_SCORES], $item[self::USER_RANKS]) =
                $this->unpackAuxiliaryDocumentKeys($aux_key_string);
        } else if ($num_keys == 3) {
            $test_id = rtrim($doc_id, "\x00");
            if (strlen($test_id) + self::DOC_KEY_LEN == strlen($doc_id) ) {
                $doc_id = $test_id;
            }
        } else if ($num_keys == 2) {
            $test_id = rtrim($doc_id, "\xFF");
            if (strlen($test_id) + self::DOC_KEY_LEN == strlen($doc_id)) {
                $doc_id = $test_id;
                $test_id = rtrim($doc_id, "\x00");
                if (strlen($test_id) + self::DOC_KEY_LEN == strlen($doc_id)) {
                    $doc_id = $test_id;
                }
            }
        }
        $occurrences = $this->weightedCount($position_list, $is_doc,
            $title_length, $item[self::DESCRIPTION_SCORES]);
        /*
           for archive crawls we store rank as the 4 bits after the high order
           bit
        */
        $rank_mask = (0x0f) << 19;
        $pre_rank = ($doc_len & $rank_mask);
        if ($pre_rank > 0) {
            $item[self::DOC_RANK] = $pre_rank >> 19;
            $doc_len &= (2 << 19 - 1);
        }
        $skip_stats = false;
        if ($item[self::SUMMARY_OFFSET] == self::NEEDS_OFFSET_FLAG) {
            $skip_stats = true;
            $item[self::RELEVANCE] = 1;
            $item[self::SCORE] = $item[self::DOC_RANK];
        } else if ($is_doc) {
            $average_doc_len = $this->len_all_docs/$this->num_docs;
            $num_docs = $this->num_docs;
            $type_weight = 1;
        } else {
            $average_doc_len = ($this->num_link_docs != 0) ?
                $this->len_all_link_docs/$this->num_link_docs : 0;
            $num_docs = $this->num_link_docs;
            $type_weight = floatval(C\LINK_WEIGHT);
        }
        if (!$skip_stats) {
            $item[self::RELEVANCE] = 0;
            if ($occurrences[self::TITLE] > 0) {
                self::docStats($item, $occurrences[self::TITLE],
                    $title_length,
                    $num_doc_or_links, $title_length, $num_docs,
                    $this->num_docs + $this->num_link_docs,
                    floatval(C\TITLE_WEIGHT));
            }
            if ($occurrences[self::DESCRIPTION] > 0) {
                $average_doc_len =
                    max($average_doc_len - $title_length, 1);
                $doc_len = max($doc_len - $title_length, 1);
                self::docStats($item, $occurrences[self::DESCRIPTION],
                    $doc_len, $num_doc_or_links, $average_doc_len , $num_docs,
                    $this->num_docs + $this->num_link_docs,
                    floatval(C\DESCRIPTION_WEIGHT));
            }
            if ($occurrences[self::LINKS] > 0) {
                self::docStats($item, $occurrences[self::LINKS],
                    $doc_len, $num_doc_or_links, $average_doc_len , $num_docs,
                    $this->num_docs + $this->num_link_docs,
                    floatval(C\LINK_WEIGHT));
            }
            $aux_scores = 1;
            if (isset($item[self::USER_RANKS])) {
                foreach ($item[self::USER_RANKS] as $score) {
                    $aux_scores *= $score;
                }
            }
            $item[self::DOC_RANK] = (is_numeric($item[self::DOC_RANK])) ?
                $item[self::DOC_RANK] : 0.001;
            $item[self::SCORE] = $item[self::DOC_RANK]
                * $item[self::RELEVANCE] * $aux_scores;
                     /*crude score not used for final
                        results */
        }
        return [$doc_id, $num_keys, $item];
    }
    /**
     * Used to sum over the occurences in a position list counting with
     * weight based on term location in the document
     *
     * @param array $position_list positions of term in item
     * @param bool $is_doc whether the item is a document or a link
     * @param int $title_length position in position list at which point
     *  no longer in title of original doc
     * @param array $position_scores pairs position => weight
     *  saying how much a word at a given position range is worth
     * @return array asscoiative array of document_part => weight count
     * of occurrences of term in
     *
     */
    public function weightedCount($position_list, $is_doc,
        $title_length = 0, $position_scores = []) {
        $count = [
            self::TITLE => 0,
            self::DESCRIPTION => 0,
            self::LINKS => 0];
        if (!is_array($position_list)) {
            return $count;
        }
        $num_scores = count($position_scores);
        $num_minus_one = $num_scores - 1;
        $score_keys = array_keys($position_scores);
        $last_score = ($num_scores > 0) ? C\TITLE_WEIGHT *
            $position_scores[$score_keys[$num_minus_one]] : 1;
        $last_index = 0;
        $next_position = $title_length;
        foreach ($position_list as $position) {
            if ($is_doc) {
                if ($position < $title_length) {
                    $count[self::TITLE] ++;
                } else {
                    $weight = 1;
                    if ($num_scores > 0 && $last_index >= $num_minus_one) {
                        $weight = $last_score;
                    } else if ($num_scores > 0) {
                        for ($next_index = $last_index;
                            $next_index < $num_minus_one; $next_index++) {
                            $last_position = $next_position;
                            $next_position = $title_length +
                                $score_keys[$next_index + 1];
                            if ($next_position > $position) {
                                break;
                            }
                        }
                        $last_index = $next_index;
                        if ($last_index >= $num_minus_one) {
                            $weight = $last_score;
                        } else {
                            $weight = C\TITLE_WEIGHT *
                                $position_scores[$score_keys[$last_index]];
                        }
                    }
                    $count[self::DESCRIPTION] += $weight;
                }
            } else {
                $count[self::LINKS]++;
            }
        }
        return $count;
    }
    /**
     * Returns a proximity score for a single term based on its location in
     * doc.
     *
     * @param array $position_list locations of term within item
     * @param bool $is_doc whether the item is a document or not
     * @return int a score for proximity
     */
    public function computeProximity($position_list, $is_doc) {
        return (!$is_doc) ? floatval(C\LINK_WEIGHT):
            ((isset($position_list[0]) &&
            $position_list[0] < C\AD_HOC_TITLE_LENGTH) ?
            floatval(C\TITLE_WEIGHT) : floatval(C\DESCRIPTION_WEIGHT));
    }
    /**
     * Computes BM25F relevance and a score for the supplied item based
     * on the supplied parameters.
     *
     * @param array &$item doc summary to compute a relevance and score for.
     *     Pass-by-ref so self::RELEVANCE and self::SCORE fields can be changed
     * @param int $occurrences - number of occurences of the term in the item
     * @param int $doc_len number of words in doc item represents
     * @param int $num_doc_or_links number of links or docs containing the term
     * @param float $average_doc_len average length of items in corpus
     * @param int $num_docs either number of links or number of docs depending
     *     if item represents a link or a doc.
     * @param int $total_docs_or_links number of docs or links in corpus
     * @param float $type_weight BM25F weight for this component
     *      (doc or link) of score
     */
    public static function docStats(&$item, $occurrences, $doc_len,
        $num_doc_or_links, $average_doc_len, $num_docs, $total_docs_or_links,
        $type_weight)
    {
        $half = 0.5;
        $doc_ratio = ($average_doc_len > 0) ? $doc_len/$average_doc_len : 0;
        $pre_relevance = number_format(
            3 * $occurrences/($occurrences + $half + 1.5*$doc_ratio),
            C\PRECISION);
        if ($num_doc_or_links > $total_docs_or_links) {
            $num_doc_or_links = 0.5 * $total_docs_or_links;
            //this case shouldn't happen but do a sanity check
        }
        $num_term_occurrences = $num_doc_or_links *
            $num_docs/($total_docs_or_links);
        $IDF = log(($num_docs - $num_term_occurrences + $half) /
            ($num_term_occurrences + $half));
        $item[self::RELEVANCE] += $half * $IDF * $pre_relevance * $type_weight;
    }
    /**
     * Gets the posting closest to index $current in the word_docs string
     * modifies the passed-by-ref variables $posting_start and
     * $posting_end so they are the index of the the start and end of the
     * posting
     *
     * @param int $current an index into the word_docs strings
     *     corresponds to a start search loc of $current * self::POSTING_LEN
     * @param int &$posting_start after function call will be
     *     index of start of nearest posting to current
     * @param int &$posting_end after function call will be
     *     index of end of nearest posting to current
     * @return string the substring of word_docs corresponding to the posting
     */
    public function getPostingAtOffset($current, &$posting_start, &$posting_end)
    {
        $posting_start = $current;
        $posting_end = $current;
        $pword = $this->getWordDocsWord($current << 2);

        $chr = ($pword >> 24) & 192;
        if (!$chr) {
            return packInt($pword);
        }
        $continue = $chr != 192;
        while ($continue) {
            $posting_start--;
            $cpword = $this->getWordDocsWord($posting_start << 2);
            $continue = ((($cpword >> 24) & 192) == 128);
        }
        while((($pword >> 24) & 192) > 64) {
            $posting_end++;
            $pword = $this->getWordDocsWord($posting_end << 2);
        }
        return $this->getWordDocsSubstring($posting_start << 2,
            ($posting_end - $posting_start + 1) << 2);
    }
    /**
     * Returns the document index of the posting at offset $current in
     * word_docs
     * @param int $current an offset into the posting lists (word_docs)
     * @return int the doc index of the pointed to posting
     */
    public function getDocIndexOfPostingAtOffset($current)
    {
        $current_offset = $current << 2;
        $pword = $this->getWordDocsWord($current_offset);
        $chr = (($pword >> 24) & 192);
        if (!$chr) {
            return docIndexModified9($pword);
        }
        $continue = $chr != 192;
        while ($continue) {
            $current_offset -= 4;
            $pword = $this->getWordDocsWord($current_offset);
            $continue = ((($pword >> 24) & 192) == 128);
        }
        return docIndexModified9($pword);
    }
    /**
     * Finds the first posting offset between $start_offset and $end_offset
     * of a posting that has a doc_offset bigger than or equal to $doc_offset
     * This is implemented using a galloping search (double offset till
     * get larger than binary search).
     *
     * @param int $start_offset first posting to consider
     * @param int $end_offset last posting before give up
     * @param int $doc_offset document offset we want to be greater than or
     *     equal to (when ASCENDING) or less equal to (DESCENDING)
     * @param int $direction which direction to iterate through elements
     *      of the posting slice (self::ASCENDING or self::DESCENDING) as
     *      compared to the order of when they were stored
     * @return array (int offset to next posting, doc_offset for this post)
     */
     public function nextPostingOffsetDocOffset($start_offset, $end_offset,
        $doc_offset, $direction = self::ASCENDING)
    {
        $is_ascending = ($direction == self::ASCENDING);
        $doc_index = $doc_offset >> 4;
        $start = $start_offset >> 2;
        $end = $end_offset >> 2;
        $post_doc_index = $this->getDocIndexOfPostingAtOffset($end);
        if (($is_ascending && $doc_index > $post_doc_index) ||
            (!$is_ascending && $doc_index < $post_doc_index)) { //fail fast
            return false;
        } else if ($doc_index == $post_doc_index) {
            return [$end << 2, $post_doc_index << 4];
        }
        $current = $start;
        $post_doc_index = $this->gallopPostingOffsetDocOffset($current,
            $doc_index, $end, $direction);
        if ($doc_index == $post_doc_index) {
            return [$current << 2, $post_doc_index << 4];
        }
        if (!$is_ascending) {
            $tmp = $end;
            $end = $start;
            $start = $tmp;
        }
        return $this->binarySearchPostingOffsetDocOffset($start, $end,
            $current, $doc_index, $direction);
     }
    /**
     *
     */
    public function binarySearchPostingOffsetDocOffset($start, $end,
        $current, $doc_index, $direction)
    {
        $low = $start;
        $high = $end;
        if ($direction == self::ASCENDING) {
            do {
                $post_doc_index = $this->getDocIndexOfPostingAtOffset($current);
                if ($doc_index > $post_doc_index) {
                    $low = $current;
                    if ($current >= $end) {
                        return false;
                    } else {
                        if ($current + 1 == $high) {
                            $current++;
                            $low = $current;
                        }
                    }
                } else if ($doc_index < $post_doc_index) {
                    if ($low == $current) {
                        return [$current << 2, $post_doc_index << 4];
                    }
                    $high = $current;
                } else  {
                    return [$current << 2, $post_doc_index << 4];
                }
                $current = (($low + $high) >> 1);
            } while($current <= $end);
        } else {
            do {
                $post_doc_index = $this->getDocIndexOfPostingAtOffset($current);
                if ($doc_index < $post_doc_index) {
                    $high = $current;
                    if ($current <= $start) {
                        return false;
                    } else {
                        if ($current - 1 == $low) {
                            $current--;
                            $high = $current;
                        }
                    }
                } else if ($doc_index > $post_doc_index) {
                    if ($high == $current) {
                        return [$current << 2, $post_doc_index << 4];
                    }
                    $low = $current;
                } else  {
                    return [$current << 2, $post_doc_index << 4];
                }
                //round up for reverse binary search
                $current = (($low + $high + 1) >> 1);
            } while($current >= $start);
        }
        return false;
    }
    /**
     * Performs a galloping search (double forward jump distance each failure
     * step)  forward in a posting list from
     * position $current forward until either $end is reached or a
     * posting with document index bigger than $doc_index is found
     *
     * @param int &$current current posting offset into posting list
     * @param int $doc_index document index want bigger than or equal to
     * @param int $end last index of posting list
     * @return int document index bigger than or equal to $doc_index. Since
     *     $current points at the posting this occurs for if found, no success
     *     by whether $current > $end
     * @param int $direction which direction to iterate through elements
     *      of the posting slice (self::ASCENDING or self::DESCENDING) as
     *      compared to the order of when they were stored
     */
    public function gallopPostingOffsetDocOffset(&$current, $doc_index, $end,
        $direction)
    {
        $stride = 32;
        if ($direction == self::ASCENDING) {
            do {
                $post_doc_index = $this->getDocIndexOfPostingAtOffset($current);
                if ($doc_index <= $post_doc_index) {
                    return $post_doc_index;
                }
                $current += $stride;
                $stride <<= 1;
            } while($current <= $end);
        } else {
            do {
                $post_doc_index = $this->getDocIndexOfPostingAtOffset($current);
                if ($doc_index >= $post_doc_index) {
                    return $post_doc_index;
                }
                $current -= $stride;
                $stride <<= 1;
            } while($current >= $end);
        }
        $current = $end;
        return $post_doc_index;
    }
    /**
     * Given an offset of a posting into the word_docs string, looks up
     * the posting there and computes the doc_offset stored in it.
     *
     * @param int $offset byte/char offset into the word_docs string
     * @return int a document byte/char offset into the doc_infos string
     */
    public function docOffsetFromPostingOffset($offset)
    {
        $doc_index = $this->getDocIndexOfPostingAtOffset($offset >> 2);
        return ($doc_index << 4);
    }
    /**
     * Returns $len many documents which contained the word corresponding to
     * $word_id (only works for loaded shards)
     *
     * @param string $word_id key to look up documents for
     * @param int number of documents desired back (from start of word linked
     *     list).
     * @param int $len number of documents
     * @return array desired list of doc's and their info
     */
    public function getPostingsSliceById($word_id, $len,
        $direction = self::ASCENDING)
    {
        $results = [];
        $info = $this->getWordInfo($word_id, true);
        if ($info !== false) {
            list($first_offset, $last_offset,
                $num_docs_or_links) = $info;
            if ($direction == self::ASCENDING) {
                $results = $this->getPostingsSlice($first_offset,
                    $first_offset, $last_offset, $len);
            } else {
                $results = $this->getPostingsSlice($first_offset,
                    $last_offset, $last_offset, $len, $direction);
            }
        }
        return $results;
    }
    /**
     * Adds the contents of the supplied $index_shard to the current index
     * shard
     *
     * @param object $index_shard the shard to append to the current shard
     */
    public function appendIndexShard($index_shard)
    {
        crawlLog("Appending index shard to current..");
        if ($this->word_docs_packed == true) {
            $this->words = [];
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }
        if ($index_shard->word_docs_packed == true) {
            crawlLog("Unpacking index shard word docs..");
            $index_shard->unpackWordDocs();
            crawlLog("..done.");
        }
        crawlLog("Concatenate index document info maps..");
        $this->doc_infos .= $index_shard->doc_infos;
        crawlLog("..done.");
        crawlLog("Start processing the appended index shard's posting lists..");
        $two_doc_len = 2 * self::DOC_KEY_LEN;
        $num_words = count($index_shard->words);
        $word_cnt = 0;
        foreach ($index_shard->words as $word_id => $postings) {
            // update doc offsets for newly added docs
            $add_len_flag = false;
            $postings_len = strlen($postings);
            if ($postings_len > 0 && ($postings_len != $two_doc_len ||
                strncmp($postings, self::HALF_BLANK, self::POSTING_LEN) != 0)) {
                $new_postings = addDocIndexPostings($postings,
                    ($this->docids_len >> 4));
                $add_len_flag = true;
            } else {
                $new_postings = $postings;
            }
            $new_postings_len = strlen($new_postings);
            if (!isset($this->words[$word_id])) {
                $this->words[$word_id] = $new_postings;
            } else {
                $this->words[$word_id] .= $new_postings;
            }
            if (!isset($this->num_docs_word[$word_id])) {
                $this->num_docs_word[$word_id] =
                    $index_shard->num_docs_word[$word_id];
            } else {
                $this->num_docs_word[$word_id] +=
                    $index_shard->num_docs_word[$word_id];
            }
            if ($add_len_flag) {
                $this->word_docs_len += $new_postings_len;
            }
            crawlTimeoutLog(".. still appending index shard words. At word: %s".
                    " of %s.", $word_cnt, $num_words);
            $word_cnt++;
        }
        crawlLog("..done appending index shard words.");
        $this->docids_len += $index_shard->docids_len;
        $this->num_docs += $index_shard->num_docs;
        $this->num_link_docs += $index_shard->num_link_docs;
        $this->len_all_docs += $index_shard->len_all_docs;
        $this->len_all_link_docs += $index_shard->len_all_link_docs;
        crawlLog("Finishing index append...Mem:" . memory_get_usage());
        if ($this->num_docs - $this->last_flattened_words_count >
            self::FLATTEN_FREQUENCY) {
            crawlLog("...Post Append Flattening Index Word Postings. Mem".
                memory_get_usage());
            $this->mergeWordPostingsToString();
            crawlLog("...Flattened Index Word Postings. Mem:".
                memory_get_usage());
        }
    }
    /**
     * Used to flatten the words associative array to a more memory
     * efficient word_postings string.
     *
     * $this->words is an associative array with associations
     *     wordid => postinglistforid
     * this format is relatively wasteful of memory
     *
     * $this->word_postings is a string in the format
     *     wordid1len1postings1wordid2len2postings2 ...
     * wordids are lex ordered. This is more memory efficient as the
     * former relies on the more wasteful php implementation of associative
     * arrays.
     *
     * mergeWordPostingsToString converts the former format to the latter
     * for each of the current wordids. $this->words is then set to [];
     * Note before this operation is done $this->word_postings might have
     * data from earlier times mergeWordPostingsToString was called, in which
     * case the behavior is controlled by $replace.
     *
     * @param bool $replace whether to overwrite existing word_id postings
     *    (true) or to append (false)
     */
    public function mergeWordPostingsToString($replace = false)
    {
        if ($this->word_docs_packed) {
            return;
        }
        crawlLog("Merge index shard postings to string to save memory.");
        ksort($this->words, SORT_STRING);
        $tmp_string = "";
        $offset = 0;
        $write_offset = 0;
        $len = strlen($this->word_postings);
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $item_len = $key_len + $posting_len;
        $num_words = count($this->words);
        $i = 0;
        foreach ($this->words as $word_id => $postings) {
            $cmp = -1;
            while($cmp < 0 && $offset + $item_len <= $len) {
                crawlTimeoutLog("..merging index word postings to string ..".
                    " processing %s of %s at offset %s less than %s", $i,
                    $num_words, $offset, $len);
                $key = substr($this->word_postings, $offset, $key_len);
                $pack_key_posts_len = substr(
                    $this->word_postings, $offset + $key_len, $posting_len);
                $key_posts_len = unpackInt($pack_key_posts_len);
                $key_postings = substr($this->word_postings,
                    $offset + $item_len, $key_posts_len);
                $word_id_posts_len = strlen($postings);
                $cmp = strcmp($key, $word_id);
                if ($cmp == 0) {
                    if ($replace) {
                        $tmp_string .= $key .
                            packInt($word_id_posts_len) . $postings;
                    } else {
                        $tmp_string .= $key .
                            packInt($key_posts_len + $word_id_posts_len) .
                            $key_postings . $postings;
                        $offset += $item_len + $key_posts_len;
                    }
                } else if ($cmp < 0) {
                    $tmp_string .= $key .$pack_key_posts_len. $key_postings;
                    $offset += $item_len + $key_posts_len;
                } else {
                    $tmp_string .= $word_id .
                        packInt($word_id_posts_len). $postings;
                }
                $tmp_len = strlen($tmp_string);
                $copy_data_len = min(self::WORD_POSTING_COPY_LEN, $tmp_len);
                $copy_to_len = min($offset - $write_offset,
                    $len - $write_offset);
                if ($copy_to_len > $copy_data_len  &&
                    $tmp_len > $copy_data_len) {
                    charCopy($tmp_string, $this->word_postings, $write_offset,
                        $copy_data_len, "merge index charCopy 1");
                    $write_offset += $copy_data_len;
                    $tmp_string = substr($tmp_string, $copy_data_len);
                }
           }
           crawlTimeoutLog("..outer loop merge index postings to string ..".
                " processing %s of %s.", $i, $num_words);
           if ($offset + $item_len > $len) {
                $word_id_posts_len = strlen($postings);
                if ($write_offset < $len) {
                    $tmp_len = strlen($tmp_string);
                    $copy_data_len = $len - $write_offset;
                    if ($tmp_len < $copy_data_len) {//this case shouldn't occur
                        $this->word_postings =
                            substr($this->word_postings, 0, $write_offset);
                        $this->word_postings .= $tmp_string;
                    } else {
                        charCopy($tmp_string, $this->word_postings,
                            $write_offset, $copy_data_len,
                            "merge index charCopy 2");
                        $tmp_string = substr($tmp_string, $copy_data_len);
                        $this->word_postings .= $tmp_string;
                    }
                    $tmp_string = "";
                    $write_offset = $len;
                }
                $this->word_postings .=
                    $word_id . packInt($word_id_posts_len). $postings;
            }
            $i++;
        }
        crawlLog("..Merge Index Posting Final Copy");
        $this->words = [];
        // garbage collection may take a while, call with true so don't time out
        CrawlDaemon::processHandler(true);
        if ($tmp_string != "") {
            crawlLog("..Merge Index Posting Final Copy 1 ".
                "Current Memory: ". memory_get_usage());
            $tmp_string .= substr($this->word_postings, $offset);
            crawlLog("..Merge Index Posting Final Copy 2 ".
                "Current Memory: ". memory_get_usage());
            $this->word_postings = substr($this->word_postings, 0,
                $write_offset);
            crawlLog("..Merge Index Posting Final Copy 3 ".
                "Current Memory: ". memory_get_usage());
            $this->word_postings .= $tmp_string;
        }
        $this->last_flattened_words_count = $this->num_docs;
        crawlLog("..Done Merge Index Posting Final Copy");
    }
    /**
     * Changes the summary offsets associated with a set of doc_ids to new
     * values. This is needed because the fetcher puts documents in a
     * shard before sending them to a queue_server. It is on the queue_server
     * however where documents are stored in the IndexArchiveBundle and
     * summary offsets are obtained. Thus, the shard needs to be updated at
     * that point. This function should be called when shard unpacked
     * (we check and unpack to be on the safe side).
     *
     * @param array $docid_offsets a set of doc_id  associated with a
     *     new_doc_offset.
     */
    public function changeDocumentOffsets($docid_offsets)
    {
        if ($this->word_docs_packed == true) {
            $this->words = [];
            $this->word_docs = "";
            $this->word_docs_packed = false;
        }
        $docids_len = $this->docids_len;
        $doc_key_len = self::DOC_KEY_LEN;
        $row_len = $doc_key_len;
        $posting_len = self::POSTING_LEN;
        $num_items = floor($docids_len / $row_len);
        $item_cnt = 0;
        crawlTimeoutLog(true);
        $missing_count = 0;
        for ($i = 0 ; $i < $docids_len; $i += $row_len) {
            crawlTimeoutLog("..still changing document offsets. At" .
                " document %s of %s.", $item_cnt, $num_items);
            $item_cnt++;
            $doc_info_string = $this->getDocInfoSubstring($i,
                $doc_key_len);
            $tmp = array_values(unpack("N*", $doc_info_string));
            if (count($tmp) < 2) {
                crawlLog("Error reading doc info string at $i");
                break;
            }
            list($offset, $doc_len_info) = $tmp;
            list($doc_len, $num_keys) =
                $this->unpackDoclenNum($doc_len_info);
            $doc_id_len = ($num_keys > 3) ? self::DOC_ID_LEN: $num_keys *
                $doc_key_len;
            $key_count = ($num_keys % 2 == 0) ? $num_keys + 2: $num_keys + 1;
            $row_len = $doc_key_len * ($key_count);
            $id = substr($this->doc_infos, $i + $doc_key_len,
                $doc_id_len); /* id is only three keys of the list of keys,
                remaining keys used for ranker */
            $test_id = rtrim($id, "\x00");
            if (strlen($test_id) + self::DOC_KEY_LEN == strlen($id)) {
                $id = $test_id;
            }
            if (isset($docid_offsets[$id])) {
                charCopy(packInt($docid_offsets[$id]), $this->doc_infos,
                    $i, $posting_len);
            } else if ($offset == self::NEEDS_OFFSET_FLAG &&
                $missing_count < 100) {
                crawlLog("Index Shard Document:" . toHexString($id) .
                   " still needs offset");
                $missing_count++;
            } else if ($offset == self::NEEDS_OFFSET_FLAG &&
                $missing_count == 100) {
                crawlLog("Index Shard: too many docs still need offset, " .
                    "not logging rest");
                $missing_count++;
            } else {
                crawlLog("Still wrong");
            }
        }
    }
    /**
     * Save the IndexShard to its filename
     *
     * @param bool $to_string whether output should be written to a string
     *     rather than the default file location
     * @param bool $with_logging whether log messages should be written
     *     as the shard save progresses
     * @return string serialized shard if output was to string else empty
     *     string
     */
    public function save($to_string = false, $with_logging = false)
    {
        $out = "";
        $this->mergeWordPostingsToString();
        if ($with_logging) {
            crawlLog("Saving index shard .. done merge postings to string");
        }
        $this->prepareWordsAndPrefixes($with_logging);
        if ($with_logging) {
            crawlLog("Saving index shard .. make prefixes");
        }
        $header =  pack("N*", $this->prefixes_len,
            $this->words_len,
            $this->word_docs_len,
            $this->docids_len,
            $this->generation,
            $this->num_docs_per_generation,
            $this->num_docs,
            $this->num_link_docs,
            $this->len_all_docs,
            $this->len_all_link_docs);
        if ($with_logging) {
            crawlLog("Saving index shard .. packed header");
        }
        if ($to_string) {
            $out = $header;
            $this->packWords(null);
            $out .= $this->words;
            $this->outputPostingLists(null, $with_logging);
            $out .= $this->word_docs;
            $out .= $this->doc_infos;
        } else {
            $fh = fopen($this->filename, "wb");
            fwrite($fh, $header);
            fwrite($fh, $this->prefixes);
            $this->packWords($fh, $with_logging);
            if ($with_logging) {
                crawlLog("Saving index shard .. wrote dictionary");
            }
            $this->outputPostingLists($fh, $with_logging);
            if ($with_logging) {
                crawlLog("Saving index shard .. wrote postings lists");
            }
            fwrite($fh, $this->doc_infos);
            fclose($fh);
        }
        if ($with_logging) {
            crawlLog("Saving index shard .. wrote doc map. Done save");
        }
        // clean up by returning to state where could add more docs
        $this->words = [];
        $this->word_docs = "";
        $this->prefixes = "";
        $this->word_docs_packed = false;
        return $out;
    }
    /**
     * This method re-saves a saved shard without the prefixes and dictionary.
     * It would typically be called after this information has been stored
     * in an IndexDictionary obbject so that the data is not redundantly stored
     * @param bool $with_logging whether log messages should be written
     *     as the shard save progresses
     */
    public function saveWithoutDictionary($with_logging = false)
    {
        $this->getShardHeader(true);
        if ($with_logging) {
            crawlLog("Opening without dictionary version of shard to write...");
        }
        $fh = fopen($this->filename . "-tmp", "wb");
        $header =  pack("N*", 0, 0,
            $this->word_docs_len,
            $this->docids_len,
            $this->generation,
            $this->num_docs_per_generation,
            $this->num_docs,
            $this->num_link_docs,
            $this->len_all_docs,
            $this->len_all_link_docs);
        fwrite($fh, $header);
        if ($with_logging) {
            crawlLog("..without dictionary version of shard header written");
        }
        if (!$this->read_only_from_disk) {
            $this->packWords(null, $with_logging);
        }
        $remaining = $this->word_docs_len;
        $offset = 0;
        $buffer_size = 16 * self::SHARD_BLOCK_SIZE;
        while ($remaining > 0) {
            $len = min($remaining, $buffer_size);
            $data = $this->getWordDocsSubstring($offset, $len, false);
            fwrite($fh, $data);
            $offset += $len;
            $remaining -= $len;
        }
        if ($with_logging) {
            crawlLog("..without dictionary version of shard word docs written");
        }
        $remaining = $this->docids_len;
        $offset = 0;
        while ($remaining > 0) {
            $len = min($remaining, $buffer_size);
            $data = $this->getDocInfoSubstring($offset, $len, false);
            fwrite($fh, $data);
            $offset += $len;
            $remaining -= $len;
        }
        if ($with_logging) {
            crawlLog("..without dictionary version of shard doc infos written");
        }
        fclose($fh);
        if (file_exists($this->filename . "-tmp")) {
            if (!empty($this->fh)) {
                fclose($this->fh);
            }
            unlink($this->filename);
            rename($this->filename . "-tmp", $this->filename);
        }
        if ($with_logging) {
            crawlLog("done replacing version of shard.");
        }
    }
    /**
     * Computes the prefix string index for the current words array.
     * This index gives offsets of the first occurrences of the lead two char's
     * of a word_id in the words array. This method assumes that the word
     * data is already in >word_postings
     * @param bool $with_logging whether log messages should be written
     *     as progresses
     */
    public function prepareWordsAndPrefixes($with_logging = false)
    {
        $word_item_len = IndexShard::WORD_KEY_LEN + IndexShard::WORD_DATA_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->words_len = 0;
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $tmp = [];
        $offset = 0;
        $num_words = 0;
        $old_prefix = false;
        while($pos < $word_postings_len) {
            if ($with_logging) {
                crawlTimeoutLog("..Outputting to position %s of" .
                    " %s of prefixes.", $pos, $word_postings_len);
            }
            $this->words_len += $word_item_len;
            $first = substr($this->word_postings, $pos, $key_len);
            $post_len = unpackInt(substr($this->word_postings,
                $pos + $key_len, $posting_len));
            $pos += $key_len + $posting_len + $post_len;
            $prefix = (ord($first[0]) << 8) + ord($first[1]);
            if ($old_prefix === $prefix) {
                $num_words++;
            } else {
                if ($old_prefix !== false) {
                    $tmp[$old_prefix] = pack("N*", $offset, $num_words);
                    $offset += $num_words * $word_item_len;
                }
                $old_prefix = $prefix;
                $num_words = 1;
            }
        }
        $tmp[$old_prefix] = pack("N*", $offset, $num_words);
        $num_prefixes = 2 << 16;
        $this->prefixes = "";
        for ($i = 0; $i < $num_prefixes; $i++) {
            if (isset($tmp[$i])) {
                $this->prefixes .= $tmp[$i];
            } else {
                $this->prefixes .= self::BLANK;
            }
        }
        $this->prefixes_len = strlen($this->prefixes);
    }
    /**
     * Posting lists are initially stored associated with a word as a key
     * value pair. The merge operation then merges them these to a string
     * by word_postings. packWords separates words from postings.
     * After being applied words is a string consisting of
     * triples (as concatenated strings) word_id, start_offset, end_offset.
     * The offsets refer to integers offsets into a string $this->word_docs
     * Finally, if a file handle is given, it writes the word dictionary out
     * to the file as a long string. This function assumes
     * mergeWordPostingsToString has just been called.
     *
     * @param resource $fh a file handle to write the dictionary to, if desired
     * @param bool $with_logging whether to write progress log messages every
     *     30 seconds
     */
    public function packWords($fh = null, $with_logging = false)
    {
        if ($this->word_docs_packed) {
            return;
        }
        $word_item_len = IndexShard::WORD_KEY_LEN + IndexShard::WORD_DATA_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->word_docs_len = 0;
        $this->words = "";
        $total_out = "";
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $two_doc_len = 2 * self::DOC_KEY_LEN;
        while($pos < $word_postings_len) {
            if ($with_logging) {
                crawlTimeoutLog("..packing index shard words at %s of %s.",
                    $pos, $word_postings_len);
            }
            $word_id = substr($this->word_postings, $pos, $key_len);
            $len = unpackInt(substr($this->word_postings,
                $pos + $key_len, $posting_len));
            if (!isset($this->num_docs_word[$word_id])) {
                crawlLog("No number count in index for " .
                    toHexString($word_id));
                $this->num_docs_word[$word_id] = ($len >> 2);
            }
            $num_docs_word = $this->num_docs_word[$word_id];
            $postings = substr($this->word_postings,
                $pos + $key_len + $posting_len, $len);
            $pos += $key_len + $posting_len + $len;
            /*
                we pack generation info to make it easier to build the global
                dictionary
            */
            if ($len != $two_doc_len ||
                strncmp($postings, self::HALF_BLANK, self::POSTING_LEN) != 0) {
                /* if len is small code count in high order half word.
                   In my experimentation all but a 100 or so word counts out of
                   all the words in 40000 or so docs can be coded in this way
                 */
                $orig_len = $len;
                if ($len < 32767 && $num_docs_word <= $len) {
                    $len += ($num_docs_word << 16) + (1 << 31);
                }
                $out = pack("N*", $this->generation, $this->word_docs_len,
                    $len);
                $this->word_docs_len += $orig_len;
                $this->words .= $word_id . $out;
            } else {
                /* single occurrence case - high word blank except high bit,
                   low word has posting (so don't go to posting list,
                   operate only in dictionary)
                 */
                $out = substr($postings,
                    self::POSTING_LEN, $word_item_len);
                $out[0] = chr((0x80 | ord($out[0])));
                $this->words .= $word_id . $out;
            }
        }
        if ($fh != null) {
            fwrite($fh, $this->words);
        }
        $this->words_len = strlen($this->words);
        $this->word_docs_packed = true;
    }
    /**
     * Used to convert the word_postings string into a word_docs string
     * or if a file handle is provided write out the word_docs sequence
     * of postings to the provided file handle.
     *
     * @param resource $fh a filehandle to write to
     * @param bool $with_logging whether to log progress
     */
    public function outputPostingLists($fh = null, $with_logging = false)
    {
        $word_item_len = IndexShard::WORD_KEY_LEN + IndexShard::WORD_DATA_LEN;
        $key_len = self::WORD_KEY_LEN;
        $posting_len = self::POSTING_LEN;
        $this->word_docs = "";
        $total_out = "";
        $word_postings_len = strlen($this->word_postings);
        $pos = 0;
        $tmp_string = "";
        $tmp_len = 0;
        $two_doc_len = 2 * self::DOC_KEY_LEN;
        while($pos < $word_postings_len) {
            if ($with_logging) {
                crawlTimeoutLog("..Outputting to position %s of" .
                    " %s in posting lists.", $pos,
                    $word_postings_len);
            }
            $word_id = substr($this->word_postings, $pos, $key_len);
            $len = unpackInt(substr($this->word_postings,
                $pos + $key_len, $posting_len));
            $postings = substr($this->word_postings,
                $pos + $key_len + $posting_len, $len);
            $pos += $key_len + $posting_len + $len;
            if ($len != $two_doc_len ||
                strncmp($postings,  self::HALF_BLANK, self::POSTING_LEN) != 0) {
                if ($fh != null) {
                    if ($tmp_len < self::SHARD_BLOCK_SIZE) {
                        $tmp_string .= $postings;
                        $tmp_len += $len;
                    } else {
                        fwrite($fh, $tmp_string);
                        $tmp_string = $postings;
                        $tmp_len = $len;
                    }
                } else {
                    $this->word_docs .= $postings;
                }
           }
        }
        if ($tmp_len > 0) {
            if ($fh == null ) {
                $this->word_docs .= $tmp_string;
            } else {
                fwrite($fh, $tmp_string);
            }
        }
    }
    /**
     * Takes the word docs string and splits it into posting lists which are
     * assigned to particular words in the words dictionary array.
     * This method is memory expensive as it briefly has essentially
     * two copies of what's in word_docs.
     */
    public function unpackWordDocs()
    {
        if (!$this->word_docs_packed) {
            return;
        }
        $num_lists = count($this->words);
        $cnt = 0;
        foreach ($this->words as $word_id => $postings_info) {
            /* we are ignoring the first four bytes which contains
               generation info
             */
            crawlTimeoutLog("..still unpacking index posting lists. At" .
                " list %s of %s.", $cnt, $num_lists);
            if ((ord($postings_info[0]) & 0x80) > 0 ) {
                $postings_info[0] = chr(ord($postings_info[0]) - 0x80);
                $postings_info = self::HALF_BLANK . $postings_info;
                $this->words[$word_id] = $postings_info;
                $this->num_docs_word[$word_id] = 1;
            } else {
                $tmp = unpack("N*", substr($postings_info, 4,
                    8));
                if (!isset($tmp[2])) {
                    continue;
                }
                list(, $offset, $len) = $tmp;
                if (($len & (1 << 31))) {
                    $this->num_docs_word[$word_id] = (($len >> 16) & 32767);
                    $len = ($len & 65535);
                } else {
                    //only approximate assuming each posting 4 bytes
                    $this->num_docs_word[$word_id] = $len >> 2;
                }
                $postings = substr($this->word_docs, $offset, $len);
                $this->words[$word_id] = $postings;
            }
            $cnt++;
        }
        unset($this->word_docs);
        $this->word_docs_packed = false;
    }
    /**
     * From disk gets $len many bytes starting from $offset in the word_docs
     * strings
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *     word_docs
     * @param $len number of bytes to get
     * @param bool $cache whether to cache disk blocks read from disk
     * @return desired string
     */
    public function getWordDocsSubstring($offset = 0, $len = 0, $cache = true)
    {
        if ($len <= 0) {
            $len = $this->word_docs_len;
        }
        if ($this->read_only_from_disk) {
            return $this->getShardSubstring($this->word_doc_offset + $offset,
                $len, $cache);
        }
        return substr($this->word_docs, $offset, $len);
    }
    /**
     * Reads 32 bit word as an unsigned int from the offset given in the
     * word_docs string in the shard
     * @param int $offset a byte offset into the word_docs string
     */
    public function getWordDocsWord($offset)
    {
        if ($this->read_only_from_disk) {
            return $this->getShardWord($this->word_doc_offset + $offset);
        }
        return unpackInt(substr($this->word_docs, $offset, 4));
    }
    /**
     * From disk gets $len many bytes starting from $offset in the doc_infos
     * strings
     *
     * @param $offset byte offset to begin getting data out of disk-based
     *     doc_infos
     * @param $len number of bytes to get
     * @param bool $cache whether to cache disk blocks read from disk
     * @return string desired
     */
    public function getDocInfoSubstring($offset = 0, $len = 0, $cache = false)
    {
        if ($len <= 0) {
            $len = $this->docids_len;
        }
        if ($this->read_only_from_disk) {
            return $this->getShardSubstring(
                $this->doc_info_offset + $offset, $len, $cache);
        }
        return substr($this->doc_infos, $offset, $len);
    }
    /**
     * Gets from Disk Data $len many bytes beginning at $offset from the
     * current IndexShard
     *
     * @param int $offset byte offset to start reading from
     * @param int $len number of bytes to read
     * @param bool $cache whether to cache disk blocks read from disk
     * @return string data from that location  in the shard
     */
    public function getShardSubstring($offset, $len, $cache = true)
    {
        $block_offset =  ($offset >> self::SHARD_BLOCK_POWER)
            << self::SHARD_BLOCK_POWER;
        $start_loc = $offset - $block_offset;
        //if all in one block do it quickly
        if ($start_loc + $len < self::SHARD_BLOCK_SIZE) {
            return substr($this->readBlockShardAtOffset($block_offset, $cache),
                $start_loc, $len);
        }
        // otherwise, this loop is slower, but handles general case
        $data = $this->readBlockShardAtOffset($block_offset, $cache);
        if ($data === false) {
            return "";
        }
        $substring = substr($data, $start_loc);
        $block_size = self::SHARD_BLOCK_SIZE;
        $block_offset += $block_size;
        while (strlen($substring) < $len) {
            $data = $this->readBlockShardAtOffset($block_offset, $cache);
            if ($data === false) {
                return $substring;
            }
            $block_offset += $block_size;
            $substring .= $data;
        }
        return substr($substring, 0, $len);
    }
    /**
     * Reads 32 bit word as an unsigned int from the offset given in the shard
     *
     * @param int $offset a byte offset into the shard
     * @return int desired word or false
     */
    public function getShardWord($offset)
    {
        if (isset($this->blocks_words[$offset])) {
            return $this->blocks_words[$offset];
        }
        if ($this->readBlockShardAtOffset(
            ($offset >> self::SHARD_BLOCK_POWER) << self::SHARD_BLOCK_POWER)) {
            return $this->blocks_words[$offset];
        } else {
            return false;
        }
    }
    /**
     * Reads SHARD_BLOCK_SIZE from the current IndexShard's file beginning
     * at byte offset $bytes
     *
     * @param int $bytes byte offset to start reading from
     * @param bool $cache whether to cache disk blocks that have been read to
     *     RAM
     * @return mixed data fromIndexShard file if found, false otherwise
     */
    public function readBlockShardAtOffset($bytes, $cache = true)
    {
        if (isset($this->blocks[$bytes]) && $cache) {
            return $this->blocks[$bytes];
        }
        if ($this->fh === null) {
            if (!file_exists($this->filename)) {
                return false;
            }
            $this->fh = fopen($this->filename, "rb");
            if ($this->fh === false) {
                return false;
            }
            $this->file_len = filesize($this->filename);
        }
        if ($bytes >= $this->file_len) {
            return false;
        }
        $seek = fseek($this->fh, $bytes, SEEK_SET);
        if ($seek < 0) {
            return false;
        }
        if (!$cache) {
            return fread($this->fh, self::SHARD_BLOCK_SIZE);
        }
        if (count($this->blocks) > self::SHARD_BLOCK_SIZE) {
            $this->blocks = [];
            $this->blocks_words = [];
        }
        $this->blocks[$bytes] = fread($this->fh, self::SHARD_BLOCK_SIZE);
        $tmp = & $this->blocks[$bytes];
        $this->blocks_words += array_combine(
            range($bytes, $bytes + strlen($tmp) - 1, 4),
            unpack("N*", $tmp));
        return $tmp;
    }
    /**
     * If not already loaded, reads in from disk the fixed-length'd field
     * variables of this IndexShard ($this->words_len, etc)
     * @return bool whether was able to read in or not
     */
    public function getShardHeader($force = false)
    {
        if (!empty($this->num_docs) && $this->num_docs > 0 && !$force) {
            return true; // if $this->num_docs > 0 assume have read in
        }
        $header = substr($this->readBlockShardAtOffset(0, false),
            0, self::HEADER_LENGTH);
        if (!$header) {
            return false;
        }
        self::headerToShardFields($header, $this);
        $this->doc_info_offset = $this->file_len - $this->docids_len;
        return true;
    }
    /**
     * Used to store the length of a document as well as the number of
     * key components in its doc_id as a packed int (4 byte string)
     *
     * @param int $doc_len number of words in the document
     * @param int $num_keys number of keys that are used to make up its doc_id
     * @return string packed int string representing these two values
     */
    public static function packDoclenNum($doc_len, $num_keys)
    {
        return packInt(($doc_len << 8) + $num_keys);
    }
    /**
     * Used to extract from a 32 bit unsigned int,
     * a pair which represents the length of a document together with the
     * number of keys in its doc_id
     *
     * @param int $doc_info integer to unpack
     * @return array pair (number of words in the document,
     *     number of keys that are used to make up its doc_id)
     */
    public static function unpackDoclenNum($doc_info)
    {
        $num_keys = $doc_info & 255;
        $doc_len = ($doc_info >> 8);
        return [$doc_len, $num_keys];
    }
    /**
     * Converts $str into 3 ints for a first offset into word_docs,
     * a last offset into word_docs, and a count of number of docs
     * with that word.
     *
     * @param string $str
     * @param bool $include_generation
     * @return array of these three or four int's
     */
    public static function getWordInfoFromString($str,
        $include_generation = false)
    {
        list(, $generation, $first_offset, $len) = unpack("N*", $str);
        $orig = $len;
        if (($len & (1 << 31))) {
            $count = (($len >> 16) & 32767);
            $len = ($len & 65535);
        } else {
            $count = $len >> 2;
        }
        $last_offset = $first_offset + $len - self::POSTING_LEN;
        if ($include_generation) {
            return [$generation, $first_offset, $last_offset, $count];
        }
        return [$first_offset, $last_offset, $count];
    }
    /**
     * Load an IndexShard from a file or string
     *
     * @param string $fname the name of the file to the IndexShard from/to
     * @param string &$data stringified shard data to load shard from. If null
     *     then the data is loaded from the $fname if possible
     * @return IndexShard the IndexShard loaded
     */
    public static function load($fname, &$data = null)
    {
        crawlLog("Loading index shard $fname");
        $shard = new IndexShard($fname);
        if ($data === null) {
            $fh = fopen($fname, "rb");
            $shard->file_len = filesize($fname);
            $header = fread($fh, self::HEADER_LENGTH);
        } else {
            $shard->file_len = strlen($data);
            $header = substr($data, 0, self::HEADER_LENGTH);
            $pos = self::HEADER_LENGTH;
        }
        self::headerToShardFields($header, $shard);
        crawlLog("..done reading index shard header");
        if ($data === null) {
            if (!($shard->prefixes_len > 0 ) || !($shard->words_len > 0 ) ||
                !($shard->word_docs_len > 0 )|| !($shard->docids_len > 0 ) ) {
                fclose($fh);
                return null;
            }
            fread($fh, $shard->prefixes_len);
            $words = fread($fh, $shard->words_len);
            $shard->word_docs = fread($fh, $shard->word_docs_len);
            $shard->doc_infos = fread($fh, $shard->docids_len);
            fclose($fh);
        } else {
            $words = substr($data, $pos, $shard->words_len);
            $pos += $shard->words_len;
            $shard->word_docs = substr($data, $pos, $shard->word_docs_len);
            $pos += $shard->word_docs_len;
            $shard->doc_infos = substr($data, $pos, $shard->docids_len);
        }
        $pre_words_array = str_split($words, self::WORD_KEY_LEN +
            self::WORD_DATA_LEN);
        unset($words);
        array_walk($pre_words_array, C\NS_LIB . 'IndexShard::makeWords',
            $shard);
        crawlLog("..done reading making index shard word structure");
        $shard->word_docs_packed = true;
        $shard->unpackWordDocs();
        crawlLog("..done unpacking index shard posting lists");
        return $shard;
    }
    /**
     * Split a header string into a shards field variable
     *
     * @param string $header a string with packed shard header data
     * @param object $shard IndexShard to put data into
     */
    public static function headerToShardFields($header, $shard)
    {
        list(,
            $shard->prefixes_len,
            $shard->words_len,
            $shard->word_docs_len,
            $shard->docids_len,
            $shard->generation,
            $shard->num_docs_per_generation,
            $shard->num_docs,
            $shard->num_link_docs,
            $shard->len_all_docs,
            $shard->len_all_link_docs
            ) = unpack("N*", $header);
        $shard->word_doc_offset = self::HEADER_LENGTH +
            $shard->prefixes_len + $shard->words_len;
    }
    /**
     * Callback function for load method. splits a word_key . word_info string
     * into an entry in the passed shard $shard->words[word_key] = $word_info.
     *
     * @param string &$value  the word_key . word_info string
     * @param int $key index in array - we don't use
     * @param object $shard IndexShard to add the entry to word table for
     */
    public static function makeWords(&$value, $key, $shard)
    {
        $shard->words[substr($value, 0, self::WORD_KEY_LEN)] =
            substr($value, self::WORD_KEY_LEN,
                self::WORD_DATA_LEN);
    }
}
