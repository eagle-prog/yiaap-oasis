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
 * A library of string, error reporting, log, hash, time, and conversion
 * functions
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__ . "/../configs/Config.php";
/**
 * Adds delimiters to a regex that may or may not have them
 *
 * @param string $expression a regex
 * @return string rgex with delimiters if not there
 */
function addRegexDelimiters($expression)
{
    $first = $expression;
    $len = strlen($expression);
    $last = $expression[$len - 1];
    if (($first != $last && $len > 1) || $len == 1) {
        $expression = ($first != '/' ) ?
            "/" . $expression . "/"
            : "@" . $expression . "@";
    }
    return $expression;
}
/**
 * search for a pcre pattern in a subject from a given offset,
 * return position of first match if found -1 otherwise.
 *
 * @param string $pattern a Perl compatible regular expression
 * @param string $subject to search for pattern in
 * @param int $offset character offset into $subject to begin searching from
 * @param boolean $return_match whether to return as well what the
 *      match was for the pattern
 * @return mixed if $return_match is false then the integer position of first
 *      match, otherwise, it returns the ordered pair [$pos, $match].
 */
function preg_search($pattern, $subject, $offset = 0, $return_match = false)
{
    $pos = -1;
    if (preg_match($pattern, $subject, $matches, PREG_OFFSET_CAPTURE,
        $offset)) {
        $pos = $matches[0][1];
    }
    if ($return_match) {
        $match = (empty($matches[0][0])) ? [] : $matches[0][0];
        return [$pos, $match];
    }
    return $pos;
}
/**
 * Replaces a pcre pattern with a replacement in $subject starting from
 * some offset.
 *
 * @param string $pattern a Perl compatible regular expression
 * @param string $replacement what to replace the pattern with
 * @param string $subject to search for pattern in
 * @param int $offset character offset into $subject to begin searching from
 * @return string result of the replacements
 */
function preg_offset_replace($pattern, $replacement, $subject, $offset = 0)
{
    $start =  substr($subject, 0 , $offset);
    $end = substr($subject, $offset);
    return $start . preg_replace($pattern, $replacement, $end);
}
/**
 * Yioop replacement for parse_ini_file($name, true) in case
 * parse_ini_file is on the disable_functions list. Name has underscores
 * to match original function. This function
 * checks if parse_ini_file is disabled on not. If not, it just
 * calls parse_ini_file; otherwise, it simulates it enough so
 * that configure.ini files used for string translations can be read.
 *
 * @param string $file filename of ini data to parse into an array
 * @return array data parse from file
 */
function parse_ini_with_fallback($file)
{
    static $disabled;
    if (!isset($disabled)) {
        $disabled_string = ini_get('disable_functions');
        $disabled = strpos($disabled_string, "parse_ini_file") !== false;
    }
    if (!$disabled) {
        return parse_ini_file($file, true);
    }
    $lines = file($file);
    $name_space = null;
    $ini = [];
    $assigned = '"((?:[^"\\\]|\\\.)*)"|\w+|'."'".
        '((?:[^'."'".'\\\]|\\\.)*)'."'";
    foreach ($lines as $line) {
        if (preg_match('/\[(\w+)\]/', $line, $matches)) {
            $name_space = $matches[1];
            $ini[$name_space] = [];
        } else if (preg_match("/(\w+|(\w+)(\[\]))\s*\=\s*($assigned)/", $line,
            $matches)){
            if ($name_space) {
                if ($matches[3] == '[]') {
                    $ini[$name_space][$matches[2]][] =
                        getIniAssignMatch($matches);
                } else {
                    $ini[$name_space][$matches[1]] =
                        getIniAssignMatch($matches);
                }
            } else {
                if ($matches[3] == '[]') {
                    $ini[$name_space][$matches[2]][] =
                        getIniAssignMatch($matches);
                } else {
                    $ini[$matches[1]] =
                        getIniAssignMatch($matches);
                }
            }
        }
    }
    return $ini;
}
/**
 * Auxiliary function called from parse_ini_with_fallback to extract from
 * the $matches array produced by the former function's preg_match
 * what kind of assignment occurred in the ini file being parsed.
 *
 * @param string $matches produced by a preg_match in
 *     parse_ini_with_fallback
 * @return mixed value of ini file assignment
 */
function getIniAssignMatch($matches)
{
    if (isset($matches[6])) {
        return $matches[6];
    } else if (isset($matches[5])) {
        return $matches[5];
    } else if (isset($matches[4])) {
        $tmp = $matches[4];
        if ($tmp == "true") {
            return true;
        } else if ($tmp == "false") {
            return false;
        }
        return $tmp;
    }
    return false;
}
/**
 * Copies from $source string beginning at position $start, $length many
 * bytes to destination string
 *
 * @param string $source  string to copy from
 * @param string &$destination string to copy to
 * @param int $start starting offset
 * @param int $length number of bytes to copy
 * @param string $timeout_msg for long copys message to print if taking more
 *     than 30 seconds
 */
function charCopy($source, &$destination, $start, $length, $timeout_msg = "")
{
    $endk = intval($length - 1);
    $end = intval($start + $endk);
    $start = intval($start);
    if (strlen($destination) <= $endk) {
        return;
    }
    if ($timeout_msg == "") {
        for ($j = $end, $k = $endk; $j >= $start; $j--, $k--) {
            $destination[$j] = $source[$k];
        }
    } else {
        $time_out_check_frequency = 5000;
        for ($j = $end, $k = $endk, $t = 0; $j >= $start; $j--, $k--, $t++) {
            $destination[$j] = $source[$k];
            if ($t > $time_out_check_frequency) {
                crawlTimeoutLog($timeout_msg);
                $t = 0;
            }
        }
    }
}
/**
 * Encodes an integer using variable byte coding.
 *
 * @param int $pos_int integer to encode
 * @return string a string of 1-5 chars depending on how bit $pos_int was
 */
function vByteEncode($pos_int)
{
    $result = chr($pos_int & 127);
    $pos_int >>= 7;
    while($pos_int > 0){
        $result .= chr(128 | ($pos_int & 127));
        $pos_int >>= 7;
    }
    return $result;
}
/**
 * Decodes from a string using variable byte coding an integer.
 *
 * @param string &$str string to use for decoding
 * @param int $offset byte offset into string when var int stored
 * @return int the decoded integer
 */
function vByteDecode(&$str, &$offset)
{
    $pos_int = ord($str[$offset] & 127) ;
    $shift = 7;
    while (ord($str[$offset++]) & 128 > 0) {
        $pos_int += (ord($str[$offset] & 127) << $shift);
        $shift += 7;
    }
    return $pos_int;
}
/**
 * Makes an packed integer string from a docindex and the number of
 * occurrences of a word in the document with that docindex.
 *
 * @param int $doc_index index (i.e., a count of which document it
 *     is rather than a byte offset) of a document in the document string
 * @param array $position_list integer positions word occurred in that doc
 * @param bool $delta if true then stores the position_list as a sequence of
 *     differences (a delta list)
 * @return string a modified9 (our compression scheme) packed
 *     string containing this info.
 */
function packPosting($doc_index, $position_list, $delta = true)
{
    if ($delta) {
        $delta_list = deltaList($position_list);
    } else {
        $delta_list = $position_list;
    }
    if ( $doc_index >= (2 << 14) && isset($delta_list[0])
        && $delta_list[0] < (2 << 9)  && $doc_index < (2 << 17)) {
        $delta_list[0] += (((2 << 17) + $doc_index) << 9);
    } else {
        // we add 1 to doc_index to make sure not 0 (modified9 needs > 0)
        array_unshift($delta_list, ($doc_index + 1));
    }
    $encoded_list = encodeModified9($delta_list);
    return $encoded_list;
}
/**
 * Given a packed integer string, uses the top three bytes to calculate
 * a doc_index of a document in the shard, and uses the low order byte
 * to computer a number of occurrences of a word in that document.
 *
 * @param string $posting a string containing
 *     a doc index position list pair coded encoded using modified9
 * @param int &$offset a offset into the string where the modified9 posting
 *     is encoded
 * @param bool $dedelta if true then assumes the list is a sequence of
 *     differences (a delta list) and undoes the difference to get
 *     the original sequence
 * @return array consisting of integer doc_index and a subarray consisting
 *     of integer positions of word in doc.
 */
function unpackPosting($posting, &$offset, $dedelta = true)
{
    $delta_list = (array) decodeModified9($posting, $offset);
    $doc_index = array_shift($delta_list);
    if (($doc_index & (2 << 26)) > 0) {
        $delta0 = $doc_index;
        $doc_index >>= 9;
        $doc_index -= (2 << 17);
        $delta0 -= (((2 << 17) + $doc_index) << 9);
        array_unshift($delta_list, $delta0);
    } else {
        $doc_index--;
    }
    if ($dedelta) {
        deDeltaList($delta_list);
    }
    return [$doc_index, $delta_list];
}
/**
 * This method is used while appending one index shard to another.
 * Given a string of postings adds $add_offset add to each offset to the
 * document map in each posting.
 *
 * @param string &$postings a string of index shard postings
 * @param int $add_offset an fixed amount to add to each postings doc map offset
 *
 * @return string $new_postings where each doc offset has had $add_offset added
 *     to it
 */
function addDocIndexPostings(&$postings, $add_offset)
{
    $offset = 0;
    $new_postings = "";
    $postings_len = strlen($postings);
    while($offset < $postings_len) {
        $post_string = nextPostString($postings, $offset);
        if ($post_string == "" || !($tmp = unpack("N*", $post_string))) {
            continue;
        }
        $posting_list = call_user_func_array("array_merge",
            array_map(C\NS_LIB . "unpackListModified9", $tmp));
        if (!is_array($posting_list)) {
            continue;
        }
        $doc_index = array_shift($posting_list);
        if (($doc_index & (2 << 26)) > 0) {
            $post0 = ($doc_index & ((2 << 9) - 1));
            array_unshift($posting_list, $post0);
            $doc_index -= $post0;
            $doc_index -= (2 << 26);
            $doc_index >>= 9;
        } else {
            $doc_index--;
        }
        $doc_index += $add_offset;
        if ($doc_index >= (2 << 14) && isset($posting_list[0])
            && $posting_list[0] < (2 << 9)  && $doc_index < (2 << 17)) {
            $posting_list[0] += (((2 << 17) + $doc_index) << 9);
        } else {
            // we add 1 to doc_index to make sure not 0 (modified9 needs > 0)
            array_unshift($posting_list, ($doc_index + 1));
        }
        $new_postings .= encodeModified9($posting_list);
    }
    return $new_postings;
}
/**
 * Computes the difference of a list of integers.
 * i.e., (a1, a2, a3, a4) becomes (a1, a2-a1, a3-a2, a4-a3)
 *
 * @param array $list a nondecreasing list of integers
 * @return array the corresponding list of differences of adjacent
 *     integers
 */
function deltaList($list)
{
    $last = 0;
    $delta_list = [];
    foreach ($list as $elt) {
        $delta_list[] = $elt - $last;
        $last = $elt;
    }
    return $delta_list;
}
/**
 * Given an array of differences of integers reconstructs the
 * original list. This computes the inverse of the deltaList function
 *
 * @see deltaList
 * @param array $delta_list a list of nonegative integers
 * @return array a nondecreasing list of integers
 */
function deDeltaList(&$delta_list)
{
    $last = 0;
    $num = count($delta_list);
    for ($i = 1; $i < $num; $i++) {
        $delta_list[$i] += $delta_list[$i - 1];
    }
}

/**
 * Mini-class (so not own file) used to hold encode decode info related to
 * Mod9 encoding (as variant of Simplified-9 specify to Yioop).
 * Mod9 is used to incode a sequence of positive (greater than 0) integers
 * as a string. WARNING: do not expect is to work/decode correctly if
 * sequence has a 0 as the decoding process assumes 0 indicates end of sequence.
 * @see encodeModified9 for a complete description
 */
class Mod9Constants
{
    /**
     * Used in Modified 9 encoding. The ith array entry represents the number of
     * i bit elements that can be stored in a word using modified 9 (0th index
     * location is a dummy value 0 as can't store 0 bit numbers)
     * @array
     */
    public static $MOD9_PACK_POSSIBILITIES = [
        0, 24, 12, 7, 6, 5, 4, 3, 3, 3, 2, 2, 2, 2,
        2,  1,  1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
        1, 1, 1, 1];
    /**
     * Used in Modified 9 encoding. Key values are the number of elements we
     * would like to store in the current word. Values are the bit prefix to use
     * on first byte of word. Notices bits 7 and 6 (128 and 64) are not parts of
     * prefixes as used for continuation bits.
     * @array
     */
    public static $MOD9_NUM_ELTS_CODES = [
        24 => 63, 12 => 62, 7 => 60, 6 => 56, 5 => 52, 4 => 48, 3 => 32,
        2 => 16, 1 => 0];
    /**
     * Keys of this array are prefix codes from the high order byte of a word
     * encoded using Modified 9, values are the number of bits used to encode
     * an element if that prefix code was used.
     * @array
     */
    public static $MOD9_NUM_BITS_CODES = [63 => 1, 62 => 2, 60 => 3, 56 => 4,
        52 => 5, 48 => 6, 32 => 9, 16 => 14, 0 => 28];
    /**
     * Keys of this array are prefix codes from the high order byte of a word
     * encoded using Modified 9, values are the number of elts stored in the
     * remaining bits of the word
     * @array
     */
    public static $MOD9_NUM_ELTS_DECODES = [
        63 => 24, 62 => 12, 60=> 7, 56 => 6, 52 => 5, 48 => 4, 32 => 3,
        16 => 2, 0 => 1];
}
/**
 * Encodes a sequence of integers x, such that 1 <= x <= 2<<28-1
 * as a string. NOTICE x>=1.
 *
 * The encoded string is a sequence of 4 byte words (packed int's).
 * The high order 2 bits of a given word indicate whether or not
 * to look at the next word. The codes are as follows:
 * 11 start of encoded string, 10 continue four more bytes, 01 end of
 * encoded, and 00 indicates whole sequence encoded in one word.
 *
 * After the high order 2 bits, the next most significant bits indicate
 * the format of the current word. There are nine possibilities:
 * 00 - 1 28 bit number, 01 - 2 14 bit numbers, 10 - 3 9 bit numbers,
 * 1100 - 4 6 bit numbers, 1101 - 5 5 bit numbers, 1110 6 4 bit numbers,
 * 11110 - 7 3 bit numbers, 111110 - 12 2 bit numbers, 111111 - 24 1 bit
 * numbers.
 *
 * @param array $list a list of positive integers satsfying above
 * @return string encoded string
 */
function encodeModified9($list)
{
    $MOD9_PACK_POSSIBILITIES = Mod9Constants::$MOD9_PACK_POSSIBILITIES;
    $cnt = 0;
    $cur_size = 1;
    $cur_len = 1;
    $pack_list = [];
    $list_string = "";
    $continue_bits = 3;
    foreach ($list as $elt) {
        $old_len = $cur_len;
        while($elt > $cur_size)
        {
            $cur_len++;
            $cur_size = (1 << $cur_len) - 1;
        }
        if ($cnt < $MOD9_PACK_POSSIBILITIES[$cur_len]) {
            $pack_list[] = $elt;
            $cnt++;
        } else {
            $list_string .= packListModified9($continue_bits,
                $MOD9_PACK_POSSIBILITIES[$old_len], $pack_list);
            $continue_bits = 2;
            $pack_list = [$elt];
            $cur_size = 1;
            $cur_len = 1;
            $cnt = 1;
            while($elt > $cur_size)
            {
                $cur_size = (1 << $cur_len) - 1;
                $cur_len++;
            }
        }
    }
    $continue_bits = ($continue_bits == 3) ? 0 : 1;
    $list_string .= packListModified9($continue_bits,
        $MOD9_PACK_POSSIBILITIES[$cur_len], $pack_list);
    return $list_string;
}
/**
 * Packs the contents of a single word of a sequence being encoded
 * using Modified9.
 *
 * @param int $continue_bits the high order 2 bits of the word
 * @param int $cnt the number of element that will be packed in this word
 * @param array $pack_list a list of positive integers to pack into word
 * @return string encoded 4 byte string
 * @see encodeModified9
 */
function packListModified9($continue_bits, $cnt, $pack_list)
{
    $MOD9_NUM_ELTS_CODES = Mod9Constants::$MOD9_NUM_ELTS_CODES;
    $MOD9_NUM_BITS_CODES = Mod9Constants::$MOD9_NUM_BITS_CODES;

    $out_int = 0;
    $code = $MOD9_NUM_ELTS_CODES[$cnt];
    $num_bits = $MOD9_NUM_BITS_CODES[$code];
    foreach ($pack_list as $elt) {
        $out_int <<= $num_bits;
        $out_int += $elt;
    }
    $out_string = packInt($out_int);

    $out_string[0] = chr(($continue_bits << 6) + $code + ord($out_string[0]));
    return $out_string;
}
/**
 * Returns the next complete posting string from $input_string being at offset.
 * Does not do any decoding.
 *
 * @param string &$input_string a string of postings
 * @param int &$offset an offset to this string which will be updated after call
 * @return string undecoded posting
 */
function nextPostString(&$input_string, &$offset)
{
    if (!isset($input_string[$offset + 3])) {
        $offset +=4; //make sure offset always increases to be safe
        return "";
    }
    $flag_mask = 192;
    $continue_threshold = 128;
    $len = strlen($input_string);
    $end = $offset;
    $error = false;
    $flag_bits = (ord($input_string[$end]) & $flag_mask) ;
    if ($flag_bits && $flag_bits != $flag_mask) {
        crawlLog("!! Decode Error Flags: $flag_bits $flag_mask");
        crawlLog("!! Dropping posting at $offset cycle to next posting.");
        crawlLog("!! Dropped posting length $len.");
        $offset += 4;
        return "";
    }
    $end += 4;
    while($end < $len && $flag_bits >= $continue_threshold) {
        $flag_bits = (ord($input_string[$end]) & $flag_mask);
        $end += 4;
    }
    $post_string = substr($input_string, $offset, $end - $offset);
    $offset = $end;
    return $post_string;
}
/**
 * Decoded a sequence of positive integers from a string that has been
 * encoded using Modified 9
 *
 * @param string $input_string string to decode from
 * @param int &$offset where to string in the string, after decode
 *     points to where one was after decoding.
 * @return array sequence of positive integers that were decoded
 * @see encodeModified9
 */
function decodeModified9($input_string, &$offset)
{
    $post_string = nextPostString($input_string, $offset);
    return call_user_func_array( "array_merge",
        array_map(C\NS_LIB . "unpackListModified9",
            unpack("N*", $post_string)));
}

if (!extension_loaded("yioop") ) {
/**
 * Decode a single word with high two bits off according to modified 9
 *
 * @param string $encoded_list four byte string to decode
 * @return array sequence of integers that results from the decoding.
 */
function unpackListModified9($encoded_list)
{
    switch ($encoded_list & 0x30000000) {
        case 0:
            return  [$encoded_list & 0x0FFFFFFF]; //lop off high nibble
            break;
        case 0x10000000:
            $encoded_list &= 0xEFFFFFFF;
            $num_bits = 14;
            $num_elts = 2;
            $mask = 0x3FFF;
            $shift = 14;
            break;
        case 0x20000000:
            $encoded_list &= 0xDFFFFFFF;
            $num_bits = 9;
            $num_elts = 3;
            $mask = 0x1FF;
            $shift = 18;
            break;
        default:
            $MOD9_NUM_BITS_CODES = Mod9Constants::$MOD9_NUM_BITS_CODES;
            $MOD9_NUM_ELTS_DECODES = Mod9Constants::$MOD9_NUM_ELTS_DECODES;
            $int_string = packInt($encoded_list);
            $first_char = ord($int_string[0]);
            foreach ($MOD9_NUM_BITS_CODES as $code => $num_bits) {
                if (($first_char & $code) == $code) {
                    break;
                }
            }
            $num_elts = $MOD9_NUM_ELTS_DECODES[$code];
            $mask = (1 << $num_bits) - 1;
            $int_string[0] = chr($first_char - $code);
            $encoded_list = unpackInt($int_string);
    }
    $decoded_list = [];
    for ($i = 0; $i < $num_elts; $i++) {
        if (($pre_elt = $encoded_list & $mask) == 0) {
            break;
        }
        array_unshift($decoded_list, $pre_elt);
        $encoded_list >>= $num_bits;
    }
    return $decoded_list;
}
/**
 * Given an int encoding encoding a doc_index followed by a position
 * list using Modified 9, extracts just the doc_index.
 *
 * @param int $encoded_list in the just described format
 * @return int a doc index into an index shard document map.
 */
function docIndexModified9($encoded_list)
{
    $t26 = 2 << 26;
    switch ($encoded_list & 0x30000000) {
        case 0:
            $encoded_list &= 0x0FFFFFFF; //lop off high nibble
            return (($encoded_list & $t26) > 0) ?
                ($encoded_list - $t26 + ($encoded_list & 0x1FF)) >> 9 :
                $encoded_list - 1;
        break;
        case 0x10000000:
            $encoded_list &= 0xEFFFFFFF;
            $num_bits = 14;
            $mask = 0x3FFF;
            $shift = 14;
        break;
        case 0x20000000:
            $encoded_list &= 0xDFFFFFFF;
            $num_bits = 9;
            $mask = 0x1FF;
            $shift = 18;
        break;
        default:
            $MOD9_NUM_BITS_CODES = Mod9Constants::$MOD9_NUM_BITS_CODES;
            $MOD9_NUM_ELTS_DECODES = Mod9Constants::$MOD9_NUM_ELTS_DECODES;
            $first_char = $encoded_list >> 24;
            foreach ($MOD9_NUM_BITS_CODES as $code => $num_bits) {
                if (($first_char & $code) == $code) break;
            }
            $num_elts = $MOD9_NUM_ELTS_DECODES[$code];
            $mask = (1 << $num_bits) - 1;
            $shift = $num_bits * ($num_elts - 1);
            $int_string = packInt($encoded_list);
            $int_string[0] = chr($first_char - $code);
            $encoded_list = (int)hexdec(bin2hex($int_string));
    }
    do {
        if ($doc_index = (($encoded_list >> $shift) & $mask)) {
            $doc_index -= (($doc_index & $t26) > 0) ?
                $t26 + ($doc_index & 0x1FF) : 1;
            return $doc_index;
        }
        $shift -= $num_bits;
    } while($shift >= 0);
    return $doc_index; //shouldn't get here
}
/**
 * Used to decode priority queue page weight and crawl depth from an int
 * used to code this information
 * @param int $weight_info coding weight and depth
 * @param string $crawl_order CrawlConstants code for page crawl order
 *  if not CrawlConstants::PAGE_IMPORTANCE then only depth info would be
 *  stored in priority queue
 * @return array order pair [$weight, $depth]
 */
function decodeQueueWeightInfo($weight_info, $crawl_order)
{
    if ($crawl_order == CrawlConstants::PAGE_IMPORTANCE) {
        $depth = 255 - ($weight_info & 255);
        $weight = $weight_info >> 8;
    } else {
        $depth = $weight_info;
        $weight = 0;
    }
    return [$weight, $depth];
}
/**
 * Packs an ordered pair of weight and depth info for a crawl priority
 * url item into a single int.
 *
 * @param int $weight to be encoded
 * @param int $depth to be encoded
 * @param string $crawl_order CrawlConstants code for page crawl order
 *  if not CrawlConstants::PAGE_IMPORTANCE then only depth info would be
 *  stored in priority queue
 * @return int single int storing both peiece of information, weight in
 *  high order 24 bits, depth in low order 8 bits
 */
function encodeQueueWeightInfo($weight, $depth, $crawl_order)
{
    if ($crawl_order == CrawlConstants::PAGE_IMPORTANCE) {
        $depth = 255 - min(max(0, $depth), 255);
        $weight_info = (min($weight, (1 << 24) - 1) << 8) + $depth;
    } else {
        $weight_info = $depth;
    }
    return $weight_info;
}
/**
 * Given two ints encoding ($weight1, $depth1), ($weight2, $depth2) pairs
 * computes an int encoding ($weight1 + $weight2, min($depth1, $depth2))
 *
 * @param int $weight_info coding weight and depth
 * @param int $adjustment coding an adjustment to weight and depth
 * @return int $weight_info  code for result pair
 */
function adjustWeightCallback($weight_info, $adjustment)
{
    $crawl_order = CrawlConstants::PAGE_IMPORTANCE;
    list($weight, $depth) = decodeQueueWeightInfo($weight_info, $crawl_order);
    list($adjust_weight, $adjust_depth) =
        decodeQueueWeightInfo($adjustment, $crawl_order);
    $weight_info = encodeQueueWeightInfo((min($weight + $adjust_weight,
        (1 << 24) - 1)), min($depth, $adjust_depth), $crawl_order);
    return $weight_info;
}
/**
 * Unpacks an int from a 4 char string
 *
 * @param string $str where to extract int from
 * @return int extracted integer
 */
function unpackInt($str)
{
    if (is_string($str)) {
        return (int)hexdec(bin2hex($str));
    }
    return false;
}
}// end extension_loaded check
/**
 * Packs an int into a 4 char string
 *
 * @param int $my_int the integer to pack
 * @return string the packed string
 */
function packInt($my_int)
{
    return pack("N", $my_int);
}
/**
 * Unpacks a float from a 4 char string
 *
 * @param string $str where to extract int from
 * @return float extracted float
 */
function unpackFloat($str)
{
    if (!is_string($str)) return false;
    $tmp = unpack("f", $str);
    return $tmp[1];
}
/**
 * Packs an float into a four char string
 *
 * @param float $my_float the float to pack
 * @return string the packed string
 */
function packFloat($my_float)
{
    return pack("f", $my_float);
}
/**
 * Used to change the namespace of a serialized php object (assumes doesn't
 * have nested subobjects)
 *
 * @param string $class_name new fully qualified name with namespace
 * @param string $object_string serialized object
 *
 * @return string serialized object with new name
 */
function renameSerializedObject($class_name, $object_string)
{
    /*  number of digits in the length of name of the object needs to be
        less than 12 digits (probably more like 4) for this to work.
    */
    $name_length = intval(substr($object_string, 2, 14));
    $name_space_info_length = strlen("O:".$name_length.":") +
        $name_length + 2; // 2 for quotes;
    $object_string = 'O:' .
        strlen($class_name) . ':"'. $class_name.'"' .
        substr($object_string, $name_space_info_length);
    return $object_string;
}
/**
 * Parses a provided string to make a DOM object. First tries to parse
 * using XML and if this fails uses the more robust HTML Dom parser
 * and manipulates the resulting DOM tree to make correspond to original
 * tags for XML that isn't HTML
 *
 * @param string $to_parse the string to parse a DOMDocument from
 * @return DOMDocument pased on the provides string
 */
function getDomFromString($to_parse)
{
    set_error_handler(null);
    $dom = new \DOMDocument("1.0");
    $dom->formatOutput = true;
    if (stristr($to_parse, "<html") === false && !empty($to_parse)) {
        @$dom->loadXML($to_parse);
    }
    if ($dom->documentElement == null) {
        $dom = new \DOMDocument("1.0");
        $dom->formatOutput = true;
        //this hack modified from php.net
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $to_parse);
        foreach ($dom->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $dom->removeChild($item); // remove hack
            }
        }
        $dom->encoding = 'UTF-8';
        if (!empty($dom->documentElement->firstChild) &&
            !empty($dom->documentElement->firstChild->tagName) &&
            $dom->documentElement->firstChild->tagName == 'body' &&
            !empty($dom->documentElement->firstChild->firstChild)) {
            $node = $dom->documentElement->firstChild->firstChild;
            $to_parse = $dom->saveXML($node);
            if (!empty($to_parse)) {
                $dom = new \DOMDocument("1.0");
                $dom->formatOutput = true;
                @$dom->loadXML($to_parse);
                if ($dom->documentElement == null) {
                    $dom = new \DOMDocument("1.0");
                    $dom->formatOutput = true;
                    @$dom->loadHTML($to_parse);
                }
            }
        }
    }
    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    return $dom;
}
/**
 * Converts a string to string where each char has been replaced by its
 * hexadecimal equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the hexified string
 */
function toHexString($str)
{
    $out = "";
    for ($i = 0; $i < strlen($str); $i++) {
        $out .= sprintf("%02X",ord($str[$i]))." ";
    }
    return $out;
}
/**
 * Converts a string to string where each char has been replaced by a Integer
 * equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the hexified string
 */
function toIntString($str)
{
    $out = "";
    for ($i = 0; $i < strlen($str); $i++) {
        $out .= sprintf("%03u",ord($str[$i]))." ";
    }
    return $out;
}
/**
 * Converts a string to string where each char has been replaced by its
 * binary equivalent
 *
 * @param string $str what we want rewritten in hex
 * @return string the binary string
 */
function toBinString($str)
{
    $out = "";
    for ($i = 0; $i < strlen($str); $i++) {
        $out .= substr(decbin(256+ord($str[$i])), 1)." ";
    }
    return $out;
}
/**
 * Converts a string of the form some int followed by K, M, or G.
 * into its integer equivalent. For example 4K would become 4000,
 * 16M would become 16000000, and 1G would become 1000000000
 * Note not using base 2 for K, M, G
 *
 * @param string $metric_num metric number to convert
 * @return int number the metric string corresponded to
 */
function metricToInt($metric_num)
{
    $num = intval($metric_num);
    if (is_string($metric_num)) {
        $last_char = $metric_num[strlen($metric_num) - 1];
        switch (strtoupper($last_char)) {
            case "K":
                $num *= 1000;
                break;
            case "M":
                $num *= pow(1000, 2);
                break;
            case "G":
                $num *= pow(1000, 3);
                break;
            case "T":
                $num *= pow(1000, 4);
                break;
        }
    }
    return $num;
}
/**
 * Converts a number to a string followed by nothing, K, M, G, T
 * depending on whether number is < 1000, < 10^6, < 10^9, or < 10^(12)
 *
 * @param int $num number to convert
 * @return string number the metric string corresponded to
 */
function intToMetric($num)
{
    if (is_int($num)) {
        $metric_letters = ["", "K", "M", "G", "T", "P", "E"];
        $power = max(intval(log($num, 1000)), 0);
        $power = (isset($metric_letters[$power])) ? $power : 6;
        $num = round($num / pow(1000, $power)). $metric_letters[$power];
    }
    return $num;
}
/**
 * Logs a message to a logfile or the screen
 *
 * @param string $msg message to log
 * @param string $lname name of log file in the LOG_DIR directory, rotated logs
 *     will also use this as their basename followed by a number followed by
 *     gzipped (since they are gzipped (older versions of Yioop used bzip
 *     Some distros don't have bzip but do have gzip. Also gzip was
 *     being used elsewhere in Yioop, so to remove the dependency bzip was
 *     replaced )).
 * @param bool $check_process_handler whether or not to call the processHandler
 *      to check how long the code has run since the last time processHandler
 *      called.
 */
function crawlLog($msg, $lname = null, $check_process_handler = false)
{
    static $logname;
    static $last_check_time = 0;
    static $check_handler = false;
    static $count = 0;
    if (!empty($_SERVER["NO_LOGGING"])) {
        return;
    }
    if ($lname != null)
    {
        $logname = $lname;
    } else if (!isset($logname)) {
        $logname = "message";
    }
    if ($check_process_handler != null) {
        $check_handler = $check_process_handler;
    }
    $time_string = date("r", time());
    $out_msg = "[$count $time_string] $msg";
    $count = ($count + 1) % 1000000000;
    if (!empty($_SERVER["LOG_TO_FILES"])) {
        $pre_logfile = C\LOG_DIR . "/$logname";
        $logfile = "$pre_logfile.log";
        clearstatcache(); //hopefully, this doesn't slow things too much
        if (empty($_SERVER["NO_ROTATE_LOGS"]) &&
            file_exists($logfile) && filesize($logfile) > C\MAX_LOG_FILE_SIZE) {
            $last_logfile = "$pre_logfile." . C\NUMBER_OF_LOG_FILES . ".log.gz";
            if (file_exists($last_logfile)) {
                unlink($last_logfile);
            }
            for ($i = C\NUMBER_OF_LOG_FILES; $i > 0; $i--) {
                $previous_logfile  = "$pre_logfile.".($i-1).".log.gz";
                if (file_exists($previous_logfile)) {
                    rename($previous_logfile, "$pre_logfile.$i.log.gz");
                }
            }
            file_put_contents("$pre_logfile.0.log.gz",
                gzencode(file_get_contents($logfile)));
            unlink($logfile);
        }
        //don't use error_log options in this case to happify hiphop4php
        file_put_contents($logfile, $out_msg . "\n", FILE_APPEND);
        if (changeInMicrotime($last_check_time) > 5 &&
            $check_handler && !$check_process_handler) {
            $continue = ($logname == 'index') ? true : false;
            CrawlDaemon::processHandler($continue);
            $last_check_time = microtime(true);
        }
    } else if (php_sapi_name() != 'cli' && !C\nsdefined("IS_OWN_WEB_SERVER")) {
        error_log($out_msg."\n");
    } else {
        file_put_contents("php://stdout", $out_msg . "\n", FILE_APPEND);
    }
}
/**
 * Writes a log message $msg if more than LOG_TIMEOUT time has passed since
 * the last time crawlTimeoutLog was callled. Useful in loops to write a message
 * as progress is made through the loop (but not on every iteration, but
 * say every 30 seconds).
 *
 * @param mixed $msg usually a string with what to be printed out after the
 *     timeout period. If $msg === true then clears the timout cache
 */
function crawlTimeoutLog($msg)
{
    static $cache_time = 0;
    if (changeInMicrotime($cache_time) < C\LOG_TIMEOUT) {
        return;
    }
    if (func_num_args() > 1) {
        $out_msg = call_user_func_array('sprintf', func_get_args());
    } else {
        $out_msg = & $msg;
    }
    crawlLog($out_msg." Current memory usage:".memory_get_usage());
    $cache_time = microtime(true);
}
/**
 * Computes an 8 byte hash of a string for use in storing documents.
 *
 * An eight byte hash was chosen so that the odds of collision even for
 * a few billion documents via the birthday problem are still reasonable.
 * If the raw flag is set to false then an 11 byte base64 encoding of the
 * 8 byte hash is returned. The hash is calculated as the xor of the
 * two halves of the 16 byte md5 of the string. (8 bytes takes less storage
 * which is useful for keeping more doc info in memory)
 *
 * @param string $string the string to hash
 * @param bool $raw whether to leave raw or base 64 encode
 * @return string the hash of $string
 */
function crawlHash($string, $raw = false)
{
    $pre_hash = md5($string, true);

    $left = substr($pre_hash,0, 8) ;
    $right = substr($pre_hash,8, 8) ;

    $combine = $right ^ $left;

    if (!$raw) {
        $hash = base64Hash($combine);
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $combine;
    }

    return $hash;
}
/**
 * Used to create a 20 byte hash of a string (typically a word or phrase
 * with a wikipedia page). Format is 8 byte crawlHash of term (md5 of term
 * two halves XOR'd), followed by a \x00, followed by the first 11 characters
 * from the term. If there are not enough char's to make 20 bytes, then the
 * string is padded with \x00s to 20bytes.
 *
 * @param string $string word to hash
 * @param bool $raw whether to base64Hash the result
 * @return string first 8 bytes of md5 of $string concatenated with \x00
 *     to indicate the hash is of a word not a phrase concatenated  with the
 *     padded to 11 byte $meta_string.
 */
function crawlHashWord($string, $raw = false)
{
    $pre_hash = substr(md5($string, true), 0, 8) .
        "\x00" . substr($string, 0, 11);
    $pre_hash = str_pad($pre_hash, 20, "\x00");
    /* low order bytes all 0 -- distinguishes it from a crawlHashPath */
    if (!$raw) {
        $hash = base64Hash($pre_hash);
            // common variant of base64 safe for urls and paths
    } else {
        $hash = $pre_hash;
    }
    return $hash;
}
/**
 * Used to compute all hashes for a phrase based on each possible cond_max
 * point. Here cond_max is the location of a substring of a phase which is
 * maximal.
 *
 * @param string $string what to find hashes for
 * @param bool $raw whether to base64 the result
 * @return array of hashes with appropriates shifts if needed
 */
function allCrawlHashPaths($string, $raw = false)
{
    $pos = -1;
    $hashes = [];
    $last_entry = null;
    $new_entry = null;
    $zero = "*";
    $shift = 0;
    $num_spaces = substr_count($string, " ");
    $num = C\MAX_QUERY_TERMS - $num_spaces;
    $j = 0;
    do {
        $old_pos = $pos;
        $path_string = $string;
        for ($i = 0; $i < $num; $i++) {
            $hash = crawlHashPath($path_string, $pos + 1, $raw);
            if ($i > 0 && $j > 0) {
                $path_len = $num_spaces - $j + 1 + $i;
                if ($path_len < 4) {
                    $shift = 32 * $i;
                    if ($path_len == 3 && $i == 3) {
                        $shift -= 3;
                    }
                } else if ($path_len < 6) {
                    if ($i < 4) {
                        $shift = 16 * $i;
                    } else {
                        $shift = 64 + 29 * ($i - 4);
                    }
                } else if ($path_len < 8) {
                    if ($i < 4) {
                        $shift = 8 * $i;
                        if ($path_len == 6) {
                            $shift += 8;
                        }
                    } else if ($i < 6) {
                        $shift = 32 + 16 * ($i - 4);
                    } else {
                        $shift = 64 + 29 * ($i - 6);
                    }
                } else if ($path_len < 10) {
                    if ($i < 4) {
                        $shift = 4 * $i;
                        if ($path_len == 8) {
                            $shift += 4;
                        }
                    } else if ($i < 6) {
                        $shift = 16 + 8 * ($i - 4);
                        if ($path_len == 8) {
                            $shift += 12;
                        }
                    } else if ($i < 8) {
                        $shift = 32 + 16 * ($i - 6);
                    } else {
                        $shift = 64 + 29 * ($i - 8);
                    }
                } else if ($path_len < 12) {
                    if ($i < 4) {
                        $shift = 2 * $i;
                        if ($path_len == 10) {
                            $shift += 2;
                        }
                    } else if ($i < 6) {
                        $shift = 8 + 4 * ($i - 4);
                    } else if ($i < 8) {
                        $shift = 16 + 8 * ($i - 6);
                    } else if ($i < 10) {
                        $shift = 32 + 16 * ($i - 8);
                    } else {
                        $shift = 64 + 29 * ($i - 10);
                    }
                } else if ($path_len < 14) {
                    if ($i < 4) {
                        $shift = $i;
                        if ($path_len == 12) {
                            $shift += 1;
                        }
                    } else if ($i < 6) {
                        $shift = 4 + 2 * ($i - 4);
                    } else if ($i < 8) {
                        $shift = 8 + 4 * ($i - 6);
                    } else if ($i < 10) {
                        $shift = 16 + 8 * ($i - 8);
                    } else if ($i < 12 ){
                        $shift = 32 + 16 * ($i - 10);
                    } else {
                        $shift = 64 + 29 * ($i - 12);
                    }
                }
                $new_entry = [$hash, $shift];
            } else {
                $new_entry = [$hash, 0];
            }
            if ($new_entry != $last_entry) {
                $hashes[] = $new_entry;
            }
            if ($j == 0) {
                break;
            }
            $path_string .= " " . $zero;
        }
        $pos = mb_strpos($string, " ", $pos + 1);
        $j++;
    } while($pos > 0 && $old_pos != $pos);
    return $hashes;
}
/**
 * Given a string makes an 20 byte hash path - where first 8 bytes is
 * a hash of the string before path start, last 12 bytes is the path
 * given by splitting on space and separately hashing each element
 * according to the number of elements and the 3bit selector below:
 *
 * general format: (64 bit lead word hash, 3bit selector, hashes of rest of
 * words)  according to:
 * Selector Bits for each remaining word
 *  001     29 32 32
 *  010     29 16 16 16 16
 *  011     29 16 16 8 8 8 8
 *  100     29 16 16 8 8 4 4 4 4
 *  101     29 16 16 8 8 4 4 2 2 2 2
 *  110     29 16 16 8 8 4 4 2 2 1 1 1 1
 *
 * If $path_start is 0 behaves like crawlHashWord(). The above encoding is
 * typically used to make word_ids for whole phrases, to make word id's
 * for single words, the format is
 * (64 bits for word, 1 byte null, then ignored 11 bytes ).
 *
 * @param string $string what to hash
 * @param int $path_start what to use as the split between 5 byte front
 *     hash and the rest
 * @param bool $raw whether to modified base64 the result
 * @return string 8 bytes that results from this hash process
 */
function crawlHashPath($string, $path_start = 0, $raw = false)
{
    if ($path_start > 0 ) {
        $string_parts = explode(" ", substr($string, $path_start));
        $num_parts = count($string_parts);
    }
    if ($path_start == 0 || $num_parts == 0) {
        $hash = crawlHashWord($string, true);
        if (!$raw) {
            $hash = base64Hash($hash);
        }
        return $hash;
    }
    $front = substr($string, 0, $path_start);
    //Top 8 bytes what a normal crawlHashWord would be
    $front_hash = substr(crawlHashWord($front, true), 0, 8);
    //Low 8 bytes encode paths
    $path_ints = [];
    $modes = [3, 3, 3, 3, 5, 5, 7, 7, 9, 9, 11, 11, 13, 13];
    $mode_nums = [1, 1, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6];
    foreach ($string_parts as $part) {
        if ($part == "*") {
            $path_ints[] = 0;
        } else {
            $path_ints[] = unpackInt(substr(md5($part, true), 0, 4));
        }
    }
    $num_parts = count($path_ints);
    if ($num_parts > 13) {
        $num_parts = 13;
    }
    $mode = $modes[$num_parts];
    $mode_num = $mode_nums[$num_parts];
    switch ($mode) {
        case 3:
            for ($i = 0; $i < 3; $i++) {
                $path_ints[$i] = isset($path_ints[$i]) ? $path_ints[$i] : 0;
            }
            $second_int = $path_ints[1];
            $third_int = $path_ints[2];
        break;
        case 5:
            $path_ints[4] = isset($path_ints[4]) ? $path_ints[4] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $third_int = (($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask);
        break;
        case 7:
            $path_ints[6] = isset($path_ints[6]) ? $path_ints[6] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift)
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask);
        break;
        case 9:
            $path_ints[8] = isset($path_ints[8]) ? $path_ints[8] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift)
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask);
        break;
        case 11:
            $path_ints[10] = isset($path_ints[10]) ? $path_ints[10] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift);
            $shift = 2;
            $mask = 3;
            $third_int = (((((($third_int
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask)) << $shift)
                + ($path_ints[9] & $mask)) << $shift)
                + ($path_ints[10] & $mask);
        break;
        case 13:
        default:
            $path_ints[10] = isset($path_ints[10]) ? $path_ints[10] : 0;
            $path_ints[11] = isset($path_ints[11]) ? $path_ints[11] : 0;
            $path_ints[12] = isset($path_ints[12]) ? $path_ints[12] : 0;
            $shift = 16;
            $mask = (1 << $shift) - 1;
            $second_int = (($path_ints[1] & $mask) << $shift)
                + ($path_ints[2] & $mask);
            $shift = 8;
            $mask = 127;
            $third_int = (((($path_ints[3] & $mask) << $shift)
                + ($path_ints[4] & $mask)) << $shift);
            $shift = 4;
            $mask = 15;
            $third_int = (((($third_int
                + ($path_ints[5] & $mask)) << $shift)
                + ($path_ints[6] & $mask)) << $shift);
            $shift = 2;
            $mask = 3;
            $third_int = (((($third_int
                + ($path_ints[7] & $mask)) << $shift)
                + ($path_ints[8] & $mask)) << $shift);
            $shift = 1;
            $mask = 1;
            $third_int = (((((($third_int
                + ($path_ints[9] & $mask)) << $shift)
                + ($path_ints[10] & $mask)) << $shift)
                + ($path_ints[11] & $mask)) << $shift)
                + ($path_ints[12] & $mask);
        break;
    }
    $hash = $front_hash. pack("NNN", $path_ints[0], $second_int,
        $third_int);
    $hash[8] = chr((ord($hash[8]) & 31) + ($mode_num << 5));
    if (!$raw) {
        $hash = base64Hash($hash);
            // common variant of base64 safe for urls and paths
    }
    return $hash;
}
/**
 * Used to compare to ids for index dictionary lookup. ids
 * are a 8 byte crawlHash together with 12 byte non-hash suffix.
 *
 * @param string $id1 20 byte word id to compare
 * @param string $id2 20 byte word id to compare
 * @return int negative if $id1 smaller, positive if bigger, and 0 if
 *     same
 */
function compareWordHashes($id1, $id2)
{
    return strcmp($id1, $id2);
}
/**
 * Converts a crawl hash number to something closer to base64 coded but
 * so doesn't get confused in urls or DBs
 *
 * @param string $string a hash to base64 encode
 * @return string the encoded hash
 */
function base64Hash($string)
{
    return strtr(rtrim(base64_encode($string), "="), ["/" => "_", "+" => "-"]);
}
/**
 * Decodes a crawl hash number from base64 to raw ASCII
 *
 * @param string $base64 a hash to decode
 * @return string the decoded hash
 */
function unbase64Hash($base64)
{
    //get rid of out modified base64 encoding
    return base64_decode(strtr($base64, ["_" => "/", "-" => "+"]) . "=");
}
/**
 * Encodes a string in a format suitable for post data
 * (mainly, base64, but str_replace data that might mess up post in result)
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webencode($str)
{
    return strtr(base64_encode($str), ["/" => "_", "=" => "~", "+" => "."]);
}
/**
 * Decodes a string encoded by webencode
 *
 * @param string $str string to encode
 * @return string encoded string
 */
function webdecode($str)
{
    return base64_decode(strtr($str, ["." => "+", "~" => "=", "_" => "/"]));
}
/**
 * The crawlHash function is used to encrypt passwords stored in the database.
 * It tries to use the best version the Blowfish variant of php's crypt
 * function available on the current system.
 *
 * @param string $string the string to encrypt
 * @param int $salt salt value to be used (needed to verify if a password is
 *     valid)
 * @return string the crypted string where crypting is done using crawlHash
 */
function crawlCrypt($string, $salt = null)
{
    if ($salt !== null) {
        return crypt($string, $salt);
    }
    /* The length of the salt and its starting prefix say which hash
       public function crypt uses. Blowfish's begins with $2a$, $2x$, or $2y$
       followed by the base 2 logarithm of the iteration count for blowfish.
       We us the more secure 2y.
     */
    $salt = '$2y$12$';
    if (function_exists('random_bytes')) {
        $salt .= strtr(base64_encode(random_bytes(16)), '+', '.');
    } else if (function_exists('mcrypt_create_iv')) {
        $salt .= strtr(base64_encode(mcrypt_create_iv(16,
            MCRYPT_DEV_URANDOM)), '+', '.');
    } else {
        $salt .= substr(str_replace('+', '.', base64_encode(
            pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()))), 0, 22);
    }
    return crypt($string, $salt);
}
/**
 * Used by a controller to take a table and return those rows in the
 * table that a given queue_server would be responsible for handling
 *
 * @param array $table an array of rows of associative arrays which
 *     a queue_server might need to process
 * @param string $field column of $table whose values should be used
 *  for partitioning
 * @param int $num_partition number of queue_servers to choose between
 * @param int $instance the id of the particular server we are interested
 * in
 * @param object $callback function or static method that might be
 *     applied to input before deciding the responsible queue_server.
 *     For example, if input was a url we might want to get the host
 *     before deciding on the queue_server
 * @return array the reduced table that the $instance queue_server is
 *     responsible for
 */
function partitionByHash($table, $field, $num_partition, $instance,
    $callback = null)
{
    $out_table = [];
    if (is_array($table)) {
        foreach ($table as $row) {
            $cell = ($field === null) ? $row : $row[$field];
            $hash_int = calculatePartition($cell, $num_partition, $callback);
            if ($hash_int  == $instance) {
                $out_table[] = $row;
            }
        }
    }
    return $out_table;
}
/**
 * Used by a controller to say which queue_server should receive
 * a given input
 * @param string $input can view as a key that might be processes by a
 *     queue_server. For example, in some cases input might be
 *     a url and we want to determine which queue_server should be
 *     responsible for queuing that url
 * @param int $num_partition number of queue_servers to choose between
 * @param object $callback function or static method that might be
 *     applied to input before deciding the responsible queue_server.
 *     For example, if the input was a url we might want to get the host
 *     before deciding on the queue_server
 * @return int id of server responsible for input
 */
function calculatePartition($input, $num_partition, $callback = null)
{
    if ($callback !== null) {
        $callback_parts = explode("::", $callback);
        if (count($callback_parts) == 1) {
            $input = $callback($input);
        } else {
            $class_name = $callback_parts[0];
            $method_name = $callback_parts[1];
            $tmp_class = new $class_name;
            $input = $tmp_class->$method_name($input);
        }
    }
    $hash_int =  abs(unpackInt(substr(crawlHash($input, true), 0, 4))) %
        $num_partition;
    return $hash_int;
}
/**
 * Measures the change in time in seconds between two timestamps to microsecond
 * precision
 *
 * @param string $start starting time with microseconds
 * @param string $end ending time with microseconds, if null use current time
 * @return float time difference in seconds
 */
function changeInMicrotime($start, $end = null)
{
    if ( !$end ) {
        $end = microtime(true);
    }
    return $end - $start;
}
/**
 * Timestamp of current epoch with microsecond precision useful for situations
 *     where time() might cause too many collisions (account creation, etc)
 * @return string timestamp to microsecond of time in second since start of
 *     current epoch
 */
function microTimestamp()
{
    return vsprintf('%d.%06d', gettimeofday());
}
/**
 * Checks that a timestamp is within the time interval given by a
 * start time (HH:mm) and a duration
 *
 * @param string $start_time string of the form (HH:mm)
 * @param string $duration string containting an int in seconds
 * @param int $time a Unix timestamp.
 * @return int -1 if the time of day of $time is not within the given interval.
 *      Otherwise, the Unix timestamp at which the interval will be over for
 *      the same day as $time.
 */
function checkTimeInterval($start_time, $duration, $time = -1)
{
    $duration = intval($duration);
    if ($duration <= 0 ) {
        return -1;
    }
    if (intval($time) < 0 ) {
        $time = time();
    }
    $today = date('Y-m-d', $time);
    $timezone_offset = date('P', $time);
    $start_time = trim($start_time);
    if (!preg_match("/\d\d\:\d\d/", $start_time)) {
        $start_time = "00:00";
    }
    $start_timestamp = strtotime($today . "T" .$start_time . ":00".
        $timezone_offset);
    $end_timestamp = $start_timestamp + $duration;
    if ($time >= $start_timestamp && $time <= $end_timestamp) {
        return $end_timestamp;
    }
    return -1;
}
/**
 * Converts a CSS unit string into its equivalent in pixels. This is
 * used by @see SvgProcessor.
 *
 * @param string $value  a number followed by a legal CSS unit
 * @return int a number in pixels
 */
function convertPixels($value)
{
    $len = strlen($value);
    if (is_int($value) || $len < 2) {
        return intval($value);
    }
    if ($value[$len - 1] == "%") {
        $num = floatval(substr($value, 0, $len - 1));
        return ($num > 0) ? floor(8 * min(100, $num)) : 0;
    }
    $num = floatval(substr($value, 0, $len - 2));
    $unit = substr($value, $len - 2);
    switch ($unit) {
        case "cm":
        case "pt":
            return intval(28 * $num);
        break;
        case "em":
        case "pc":
            return intval(6 * $num);
        break;
        case "ex":
            return intval(12 * $num);
        break;
        case "in":
            //assume screen 72 dpi as on mac
            return intval(72 * $num);
        break;
        case "mm":
            return intval(2.8 * $num);
        break;
        case "px":
            return intval($num);
        break;
        default:
            $num = $value;
    }
    return intval($num);
}
/**
 * Creates folders along a filesystem path if they don't exist
 *
 * @param string $path a file system path
 * @return bool success or failure
 */
function makePath($path)
{
    $path_parts = explode("/", $path);
    $num_parts = count($path_parts);
    $path_so_far = "";
    for ($i = 0; $i < $num_parts; $i++) {
        $path_so_far .= $path_parts[$i] . "/";
        if (!is_dir($path_so_far)) {
            if (file_exists($path_so_far) || !mkdir($path_so_far) ) {
                return false;
            }
        }
    }
    return true;
}
/**
 * This is a callback function used in the process of recursively deleting a
 * directory
 *
 * @param string $file_or_dir the filename or directory name to be deleted
 * @see DatasourceManager::unlinkRecursive()
 */
function deleteFileOrDir($file_or_dir)
{
    if (file_exists($file_or_dir)) {
        if (is_file($file_or_dir)) {
            unlink($file_or_dir);
        } else {
            rmdir($file_or_dir);
        }
    }
}
/**
 * This is a callback function used in the process of recursively chmoding to
 * 777 all files in a folder
 *
 * @param string $file the filename or directory name to be chmod
 * @see DatasourceManager::setWorldPermissionsRecursive()
 */
function setWorldPermissions($file)
{
    if (php_sapi_name() == 'cli') {
        chmod($file, 0777);
        return;
    }
    set_error_handler(null);
    chmod($file, 0777);
    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
}
/**
 * This is a callback function used in the process of recursively calculating
 * an array of file modification times and files sizes for a directorys
 *
 * @param string $file a name of a file in the file system
 * @return an array whose single element contain an associative array
 *     with the size and modification time of the file
 */
function fileInfo($file)
{
    $info["name"] = $file;
    $info["size"] = filesize($file);
    $info["is_dir"] = is_dir($file);
    $info["modified"] = filemtime($file);
    return [$info];
}
//ordering functions used in sorting
/**
 * Callback function used to sort documents by a field
 *
 * Should be initialized before using in usort with a call
 * like: orderCallback($tmp, $tmp, "field_want");
 *
 * @param string $word_doc_a doc id of first document to compare
 * @param string $word_doc_b doc id of second document to compare
 * @param string $order_field which field of these associative arrays to sort by
 * @return int -1 if first doc bigger 1 otherwise
 */
function orderCallback($word_doc_a, $word_doc_b, $order_field = null)
{
    static $field = "a";
    if ($order_field !== null) {
        $field = $order_field;
        return -1;
    }
    return ((float)$word_doc_a[$field] >
        (float)$word_doc_b[$field]) ? -1 : 1;
}
/**
 * Callback function used to sort documents by a field where field is assume to
 * be a string
 *
 * Should be initialized before using in usort with a call
 * like: stringOrderCallback($tmp, $tmp, "field_want");
 *
 * @param string $word_doc_a doc id of first document to compare
 * @param string $word_doc_b doc id of second document to compare
 * @param string $order_field which field of these associative arrays to sort by
 * @return int -1 if first doc smaller 1 otherwise
 */
 function stringOrderCallback($word_doc_a, $word_doc_b, $order_field = null)
{
    static $field = "a";
    if ($order_field !== null) {
        $field = $order_field;
        return -1;
    }
    return ((string)$word_doc_a[$field] >
        (string)$word_doc_b[$field]) ? -1 : 1;
}
/**
 * Callback function used to sort documents by a field where field is assume to
 * be a string
 *
 * Should be initialized before using in usort with a call
 * like: stringROrderCallback($tmp, $tmp, "field_want");
 *
 * @param string $word_doc_a doc id of first document to compare
 * @param string $word_doc_b doc id of second document to compare
 * @param string $order_field which field of these associative arrays to sort by
 * @return int -1 if first doc bigger 1 otherwise
 */
 function stringROrderCallback($word_doc_a, $word_doc_b, $order_field = null)
{
    static $field = "a";
    if ($order_field !== null) {
        $field = $order_field;
        return -1;
    }
    return ((string)$word_doc_a[$field] <
        (string)$word_doc_b[$field]) ? -1 : 1;
}
/**
 * Callback function used to sort documents by a field in reverse order
 *
 * Should be initialized before using in usort with a call
 * like: rorderCallback($tmp, $tmp, "field_want");
 *
 * @param string $word_doc_a doc id of first document to compare
 * @param string $word_doc_b doc id of second document to compare
 * @param string $order_field which field of these associative arrays to sort by
 * @return int 1 if first doc bigger -1 otherwise
 */
function rorderCallback($word_doc_a, $word_doc_b, $order_field = null)
{
    static $field = "a";
    if ($order_field !== null) {
        $field = $order_field;
        return -1;
    }
    return ((float)$word_doc_a[$field] >
        (float)$word_doc_b[$field]) ? 1 : -1;
}
/**
 * Callback to check if $a is less than $b
 *
 * Used to help sort document results returned in PhraseModel called
 * in IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is less than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getPhrasePageResults()
 */
function lessThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}
/**
 * Callback to check if $a is greater than $b
 *
 * Used to help sort document results returned in PhraseModel called in
 * IndexArchiveBundle
 *
 * @param float $a first value to compare
 * @param float $b second value to compare
 * @return int -1 if $a is greater than $b; 1 otherwise
 * @see IndexArchiveBundle::getSelectiveWords()
 * @see PhraseModel::getTopPhrases()
 */
function greaterThan($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a > $b) ? -1 : 1;
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/**
 * Compute the real remote address of the incoming connection
 * including forwarding
 */
function remoteAddress()
{
    return (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ?
        $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
}
/**
 * Used to read a line of input from the command-line
 * @return string from the command-line
 */
function readInput()
{
    $stdin = fopen('php://stdin', 'r');
    $line = fgets($stdin);
    $line = trim($line);
    fclose($stdin);
    return $line;
}
/**
 * Used to read a line of input from the command-line
 * (on unix machines without echoing it)
 * @return string from the command-line
 */
function readPassword()
{
    system('stty -echo');
    $line = readInput();
    if (!strstr(PHP_OS, "WIN")) {
        e(str_repeat("*", strlen($line))."\n");
    }
    system('stty echo');
    return $line;
}
/**
 * Used to read a several lines from the terminal up until
 * a last line consisting of just a "."
 * @return string from the command-line
 */
function readMessage()
{
    $message = "";
    $line = "";
    do {
        $message .= $line;
        $line = readInput()."\n";
    } while(rtrim($line) != ".");

    return rtrim($message);
}
/**
 * Returns the mime type of the provided file name if it can be determined.
 *
 * @param string $file_name (name of file including path to figure out
 *      mime type for)
 * @param bool $use_extension whether to just try to guess from the file
 *      extension rather than looking at the file
 * @return string mime type or unknown if can't be determined
 */
function mimeType($file_name, $use_extension = false)
{
    return Website::mimeType($file_name, $use_extension);
}
/**
 * Checks if class_1 is the same as class_2 or has class_2 as a parent
 * Behaves like 3 param version (last param true) of PHP is_a function
 * that came into being with Version 5.3.9.
 *
 * @param mixed $class_1 object or string class name to see if in class2
 * @param mixed $class_2 object or string class name to see if contains class1
 * @return bool equal or contains class
 */
function generalIsA($class_1, $class_2)
{
    if ($class_1 == $class_2) {
        return true;
    }
    return (is_a($class_1, $class_2) || is_subclass_of($class_1, $class_2));
}
/**
 * Given the contents of a start XML/HMTL tag strips out all the attributes
 * non listed in $safe_attribute_list
 *
 * @param string $start_tag_contents the contents of an HTML/XML tag. I.e.,
 *     if the tag was &lt;tag stuff&gt; then $start_tag_contents could be stuff
 * @param array $safe_attribute_list a list of attributes which should be kept
 * @return string containing only safe attributes and their values
 */
 function stripAttributes($start_tag_contents, $safe_attribute_list = [])
 {
    $out = "";
    if ($safe_attribute_list != []) {
        $safe_regex = '/(?:(?:\A|\s+)(?:';
        $first = "";
        foreach ($safe_attribute_list as $attribute) {
            $safe_regex .= $first . $attribute;
            $first = "|";
        }
        $safe_regex .= ')\s*=\s*(?:"[^"]+"|'."'[^']+'))/";
        preg_match_all($safe_regex, $start_tag_contents, $matches);
        if (isset($matches[0])) {
            foreach ($matches[0] as $attribute) {
                $out .= " ".$attribute;
            }
            if ($out) {
                $out = trim($out);
            }
        }
    }
    return $out;
}
/**
 * Used to parse into a two dimensional array a string that contains CSV data.
 * @param string $csv_string string with csv data
 * @return array two dimensional array of elements from csv
 */
function parseCsv($csv_string)
{
    $lines = str_getcsv($csv_string, "\n");
    $out_lines = [];
    foreach ($lines as $line) {
        $out_lines[] = str_getcsv($line);
    }
    return $out_lines;
}
/**
 * Converts an array of values to a comma separated value formatted string.
 *
 * @param array $arr values to convert
 * @return string CSV string after conversion
 */
function arraytoCsv($arr)
{
    $fp = fopen('php://temp', 'r+');
    fputcsv($fp, $arr);
    rewind($fp);
    $data = fgets($fp);
    fclose($fp);
    return rtrim($data, "\n");
}
/**
 * Computes a Unix-style diff of two strings. That is it only
 * outputs lines which disagree between the two strings. It outputs +line
 * if a line occurs in the second but not first string and -line if a
 * line occurs in the first string but not the second.
 *
 * @param string $data1 first string to compare
 * @param string $data2 second string to compare
 * @param bool $html whether to output html highlighting
 * @return string respresenting info about where $data1 and $data2 don't match
 */
function diff($data1, $data2, $html = false)
{
    if ($html) {
        $start = "<div>";
        $start_same = "<div class='light-gray'>";
        $start1 = "<div class='red'>";
        $start2 = "<div class='green'>";
        $end = "</div>";
    } else {
        $start = "";
        $start_same = "";
        $start1 = "";
        $start2 = "";
        $end = "";
    }
    $lines1 = explode("\n", $data1);
    $lines2 = explode("\n", $data2);
    $num_lines1 = count($lines1);
    $num_lines2 = count($lines2);
    $shorter_len = min($num_lines1, $num_lines2);
    $longer_len = max($num_lines1, $num_lines2);
    $first_diff = 0;
    // trim off the starts and end lines of the strings that are same
    $head_lcs = [];
    while($first_diff < $shorter_len &&
        strcmp($lines1[$first_diff], $lines2[$first_diff]) == 0) {
        $head_lcs[] = [$first_diff, $first_diff, $lines1[$first_diff]];
        $first_diff++;
    }
    if ($first_diff == $shorter_len) {
        if ($num_lines1 == $num_lines2) {
            return "";
        } else {
            $tmp = $lines1;
            $prefix = "$start1-";
            if ($num_lines1 == $shorter_len) {
                $tmp = $lines2;
                $prefix = "$start2+";
            }
            $out = "$start@@ -$shorter_len,0 +$shorter_len,".
                "$longer_len @@$end\n";
            for ($i = $shorter_len; $i < $longer_len; $i++) {
                $out .= $prefix . $tmp[$i]."$end\n";
            }
            return $out;
        }
    }
    $last_diff = 0;
    $tail_lcs = [];
    $index1 = $num_lines1 - 1 - $last_diff;
    $index2 = $num_lines2 - 1 - $last_diff;
    while($shorter_len - $last_diff > $first_diff &&
        $lines1[$index1] == $lines2[$index2]) {
        array_unshift($tail_lcs, [$index1, $index2, $lines1[$index1]]);
        $last_diff++;
        $index1--;
        $index2--;
    }
    $trim_lines1 = array_slice($lines1, $first_diff, -$last_diff);
    $trim_lines2 = array_slice($lines2, $first_diff, -$last_diff);
    /*  To compute a diff, we first compute the
        LCS = Longest common subsequence of the two string.
     */
    $lcs = computeLCS($trim_lines1, $trim_lines2, $first_diff);
    $lcs = array_merge($head_lcs, $lcs, $tail_lcs);
    $previous_first = -1;
    $previous_second = -1;
    $current_first = 0;
    $current_second = 0;
    $old_line = "";
    $out_string = "";
    if ($lcs == []) {
        $out_string .= "$start@@ -0,$num_lines1 ".
            " +0,$num_lines2 @@$end\n";
        for ($i = 0; $i < $num_lines1; $i++) {
            $out_string .= "$start1-" . $lines1[$i] . "$end\n";
        }
        for ($i = 0; $i < $num_lines2; $i++) {
            $out_string .= "$start2+" . $lines2[$i] . "$end\n";
        }
    } else {
        foreach ($lcs as $lcs_item) {
            list($current_first, $current_second, $line) = $lcs_item;
            $gap1 = $current_first - $previous_first;
            $gap2 = $current_second - $previous_second;
            if ($gap1 > 1 || $gap2 > 1) {
                $gap1++;
                $gap2++;
                $out_string .= "$start@@ -$previous_first,$gap1 ".
                    " +$previous_second,$gap2 @@$end\n";
                $out_string .= "$start_same ".$old_line."$end\n";
                for ($i = $previous_first + 1; $i < $current_first; $i++) {
                    $out_string .= "$start1-" . $lines1[$i] . "$end\n";
                }
                for ($i = $previous_second + 1; $i < $current_second; $i++) {
                    $out_string .= "$start2+" . $lines2[$i] . "$end\n";
                }
                $out_string .= "$start_same ".$line."$end\n";
            }
            $previous_first = $current_first;
            $previous_second = $current_second;
            $old_line = $line;
        }
        if ($current_first < $num_lines1 - 1 ||
            $current_second < $num_lines2 - 1) {
            $gap1 = $num_lines1 - $current_first;
            $gap2 = $num_lines2 - $current_second;
            $out_string .= "$start_same ".$line."$end\n";
            for ($i = $current_first + 1; $i < $num_lines1; $i++) {
                $out_string .= "$start1-" . $lines1[$i] . "$end\n";
            }
            for ($i = $current_second + 1; $i < $num_lines2; $i++) {
                $out_string .= "$start2+" . $lines2[$i] . "$end\n";
            }
        }
    }
    return $out_string;
}
/**
 * Computes the longest common subsequence of two arrays
 *
 * @param array $lines1 an array of lines to compute LCS of
 * @param array $lines2 an array of lines to compute LCS of
 * @param int $offset an offset to shift over array addresses in output by
 */
function computeLCS($lines1, $lines2, $offset = 0)
{
    /*
        add a dummy line so don't have to worry about
        shifting indices in CLRS pseudo-code implementation
    */
    $num_lines1 = count($lines1);
    $num_lines2 = count($lines2);
    array_unshift($lines1, 0);
    array_unshift($lines2, 0);
    /*
        LCS = Longest common subsequence of the two string.
        The code below is based off the pseudo-code in CLRS
     */
    $lcs_moves = [];
    $lcs_values = [];
    for ($i = 1; $i <= $num_lines1; $i++) { //initialize first column
        $lcs_values[$i][0] = 0;
    }
    for ($j = 0; $j <= $num_lines2; $j++) { //initialize first column
        $lcs_values[0][$j] = 0;
    }
    $lcs_moves = [];
    for ($i = 1; $i <= $num_lines1; $i++) {
        for ($j = 1; $j <= $num_lines2; $j++) {
            if ($lines1[$i] == $lines2[$j]) {
                  $lcs_values[$i][$j] = $lcs_values[$i - 1][$j - 1] + 1;
                  $lcs_moves[$i][$j] = "d"; //diagonal
            } elseif ($lcs_values[$i - 1][$j] >= $lcs_values[$i][$j - 1]) {
                $lcs_values[$i][$j] = $lcs_values[$i - 1][$j];
                $lcs_moves[$i][$j] = "u"; // up
            } else {
                $lcs_values[$i][$j] = $lcs_values[$i][$j - 1];
                $lcs_moves[$i][$j] = "l"; // left
            }
        }
    }
    $lcs = [];
    extractLCSFromTable($lcs_moves, $lines1, $num_lines1, $num_lines2,
        $offset, $lcs);
    return $lcs;
}
/**
 * Extracts from a table of longest common sequence moves (probably calculated
 * by @see computeLCS) and a starting coordinate $i, $j in that table,
 * a longest common subsequence
 *
 * @param array $lcs_moves a table of move computed by computeLCS
 * @param array $lines from first of the two arrays computing LCS of
 * @param int $i a line number in string 1
 * @param int $j a line number in string 2
 * @param int $offset a number to add to each line number output into $lcs.
 *     This is useful if we have trimmed off the initially common lines from
 *     our two strings we are trying to compute the LCS of
 * @param array &$lcs an array of triples
 *     (index_string1, index_string2, line)
 *     the indexes indicate the line number in each string, line is the line
 *     in common the two strings
 */
function extractLCSFromTable($lcs_moves, $lines, $i, $j, $offset, &$lcs)
{
    if ($i == 0 || $j == 0) {
        return [];
    }
    if ($lcs_moves[$i][$j] == "d") { //diagonal moves means common to both
        //sub-case first so forward order
        extractLCSFromTable($lcs_moves, $lines, $i - 1, $j - 1, $offset, $lcs);
        $lcs[] = [$i + $offset- 1, $j + $offset - 1, $lines[$i]];
    } elseif ($lcs_moves[$i][$j] == "u") { // up move in matrix
        extractLCSFromTable($lcs_moves, $lines, $i - 1, $j, $offset, $lcs);
    } else { // left move in matrix
        extractLCSFromTable($lcs_moves, $lines, $i, $j - 1, $offset, $lcs);
    }
}
/**
 * Returns an array of the last $num_lines many lines our of a file
 *
 * @param string $file_name name of file to return lines from
 * @param string $num_lines number of lines to retrieve
 * @return array retrieved lines
 */
function tail($file_name, $num_lines)
{
    $size = filesize($file_name);
    $max_line_len = 160;
    $offset = max(0, $size - $max_line_len * $num_lines);
    $file_string = file_get_contents($file_name, $offset);
    $lines = explode("\n", $file_string);
    $lines = array_slice($lines, -$num_lines);
    return $lines;
}
/**
 * Given an array of lines returns a subarray of those lines containing the
 * filter string or filter array
 *
 * @param string $lines to search
 * @param mixed $filters either string to filter lines with or an array of
 *      strings (any of which can be present to pass the filter)
 * @return array lines containing the string
 */
function lineFilter($lines, $filters)
{
    $out_lines = [];
    if (is_string($filters)) {
        $filters = [$filters];
    }
    foreach ($lines as $line) {
        foreach ($filters as $filter) {
            if (stripos($line, $filter) !== false) {
                $out_lines[] = $line;
                break;
            }
        }
    }
    return $out_lines;
}
/**
 * Tries to extract a timestamp from a line which is presumed to come from
 * a Yioop log file
 *
 * @param string $line to search
 * @return int timestamp of that log entry
 */
function logLineTimestamp($line)
{
    preg_match("/^\s*\[\d+\s+(.*)\]/", $line, $matches);
    if (isset($matches[1])) {
        return @strtotime($matches[1]);
    }
    return 0;
}
/**
 * Returns whether an input can be parsed to a positive integer
 *
 * @param mixed $input
 * @return bool whether $input can be parsed to a positive integer.
 */
function isPositiveInteger($input)
{
    return (is_int($input) && $input > 0) ||
        (is_string($input) &&
        preg_match("/^\d+$/", trim($input)) && intval($input) > 0);
}
/**
 * Runs various system garbage collection functions and returns
 * number of bytes freed.
 *
 * @return int number of bytes freed
 */
function garbageCollect()
{
    $bytes_collected = 0;
    if (function_exists("gc_mem_caches")) {
        $bytes_collected += gc_mem_caches();
    }
    $bytes_collected += gc_collect_cycles();
    return $bytes_collected;
}
/**
 * The dom method saveHTML has a tendency to replace UTF-8, non-ascii characters
 * with html entities. This is supposed to save avoiding the replacement.
 * What it does is to first save the dom, then it replaces htmlentities of the
 * form &single_char;  or  &#some_number; with the UTF-8 they correspond to.
 * It leaves all other entities as they are
 *
 * @param DOMDocument $dom
 * @return string output of saving html
 */
function utf8SafeSaveHtml(\DOMDocument $dom)
{
    $out = $dom->saveHTML();
    $out = preg_replace("/\&([a-zA-z][a-zA-z]+)\;/u", '-a-m-p-${1};', $out);
    $out = html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $out = preg_replace("/\-a\-m\-p\-([a-zA-z][a-zA-z]+)\;/u", '&${1};', $out);
    return $out;
}
/**
 * A UTF-8 safe version of PHP's wordwrap function that wraps a string to a
 * given number of characters
 *
 * @param string $string the input string
 * @param int $width the number of characters at which the string will be
 *  wrapped
 * @param string $break string used to break a line into two
 * @param bool $cut whether to always force wrap at $width characters
 *     even if word hasn't ended
 * @return string the given string wrapped at the specified length
 */
function utf8WordWrap($string, $width = 75, $break = "\n", $cut = false)
{
    if($cut) {
        /* Match anything 1 to $width chars long followed by whitespace or EOS,
          otherwise match anything $width chars long
         */
        $search = '/(.{1,'. $width .'})(?:\s|$)|(.{'.$width.'})/uS';
        $replace = '$1$2' . $break;
    } else {
        /* Anchor the beginning of the pattern with a lookahead
          to avoid crazy backtracking when words are longer than $width
         */
        $search = '/(?=\s)(.{1,' . $width . '})(?:\s|$)/uS';
        $replace = '$1'. $break;
    }
    return preg_replace($search, $replace, $string);
}
