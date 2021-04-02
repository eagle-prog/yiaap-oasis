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
 * @author Xianghong Sun sxh19911230@gmail.com
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/**
 * Class for segmenting terms using Stochastic Finite State Word Segmentation
 *
 * @author Xianghong Sun and Chris Pollett (tweaks to adding new language)
 */
class StochasticTermSegmenter
{
    /**
     * Percentage for cache entries. Value should be between 0 and 1.0
     * Set to small number when running on memory limited machines
     * Here is a general comparison when setting it to 0 and 1:
     * In the test of Chinese Segmentation on pku dataset,
     * the peak usage of memory is 26.288MB vs. 151.46MB
     * The trade off is some efficiency,
     * In the test of Chinese Segmentation on pku dataset,
     * the speed is 43.803s vs. 1.540s
     * Default value = 0.06
     * The time and Peak Memory are 5.094s and 98.97MB
     * @var number from 0 - 1.0
     */
    private $cache_pct;
    /**
     * Cache of sub trie of dictionary trie used to speed up look up
     * @var array
     */
    private $cache = [];
    /**
     * The language currently being used  e.g. zh-CN, ja
     * @var string
     */
    public $lang;
    /**
     * Regular expression to determine if the non of the char in this
     * term is in current language
     * Recommanded expression for:
     * Chinese:  \p{Han}
     * Japanese: \x{4E00}-\x{9FBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}
     * Korean:   \x{3130}-\x{318F}\x{AC00}-\x{D7AF}
     * @var string
     */
    public $non_char_preg;
    /**
     * Default score for any unknown term
     * @var float
     */
    public $unknown_term_score;
    /**
     * A dictionary that contains statistical information on terms for a
     * language. A non-empty dictionary should have two fields:
     * N, the number of terms in the dictionary; dic,
     * a trie implemented using nested php arrays that implements the
     * dictionary. The leaves of the trie have frequency counts for terms
     * stored in the trie.
     * @var array
     */
    public $dictionary;
    /**
     * Path on disk to where segmentor dictionary should be stored
     * @var string
     */
    public $dictionary_path;
    /**
     * Maximum character length of a term
     */
    const MAX_TERM_LENGTH = 7;
    /**
     * Constructs an instance of this class used for segmenting string with
     * respect to words in a locale using a probabilistic approach to evaluate
     * segmentation possibilities.
     * @param string $lang locale this instance will do segmentation for
     * @param float $cache_pct percentage of whole trie that can be
     *  cached for faster look-up
     */
    function __construct($lang, $cache_pct = 0.06)
    {
        $lang = str_replace("-", "_", $lang);
        $this->lang = $lang;
        $this->dictionary_path = C\LOCALE_DIR .
         "/$lang/resources/term_weights.txt.gz";
        $this->cache_pct = $cache_pct;
        $this->tokenizer = PhraseParser::getTokenizer($lang);
        if (!is_object($this->tokenizer)) {
            return;
        }
        /*
         * To use a StocasticTermSegmenter, a locale's Tokenizer should
         * implement isCardinalNumber, isOrdinalNumber, isDate,
         * isPunctuation, isNotCurrentLang and optionally getNamedEntityTagger
         */
        if (method_exists($this->tokenizer, "getNamedEntityTagger")) {
            /*
             * Named entity recognizer;
             */
            $this->named_entity_tagger =
                $this->tokenizer::getNamedEntityTagger();
        }
    }
    /**
     * Check if the term passed in is an exception term
     * Not all valid terms should be indexed.
     * e.g. there are infinite combinations of numbers in the world.
     * isExceptionImpl should be defined in constructor if needed
     * @param string $term is a string that to be checked
     * @return true if $term is an exception term, false otherwise
     */
    public function isException($term)
    {
        if (!empty($this->tokenizer) &&
            method_exists($this->tokenizer, "isCardinalNumber") &&
            method_exists($this->tokenizer, "isOrdinalNumber") &&
            method_exists($this->tokenizer, "isDate")) {
            return $this->tokenizer::isCardinalNumber($term)
                || $this->tokenizer::isOrdinalNumber($term)
                || $this->tokenizer::isDate($term);
        }
        return false;
    }
    /**
     * Check if the term passed in is a punctuation character
     * isPunctuationImpl should be defined in constructor if needed
     * @param string $term is a string that to be checked
     * @return true if $term is some kind of punctuation, false otherwise
     */
    public function isPunctuation($term)
    {
        if (!empty($this->tokenizer) &&
            method_exists($this->tokenizer, "isPunctuation")) {
            return $this->tokenizer::isPunctuation($term);
        }
        return false;
    }
    /**
     * Check if all the chars in the term are NOT from the current language
     * @param string $term is a string that to be checked
     * @return bool true if all the chars in $term are NOT from the current
     *  language false otherwise
     */
    public function notCurrentLang($term)
    {
        if (!empty($this->tokenizer) &&
            method_exists($this->tokenizer, "isNotCurrentLang")) {
            return $this->tokenizer::isNotCurrentLang($term);
        }
        return false;
    }
    /**
     * Generate a term dictionary file for later segmentation
     * @param mixed $text_files is a string name or an array of files
     *  that to be trained; words in the files need to be segmented by space
     * @param string $format currently only support default and CTB
     */
    public function train($text_files, $format = "default")
    {
        $ctb_fmt = false;
        switch ($format) {
            case("default"):
                break;
            case("CTB"):
                $ctb_fmt = true;
                break;
            default:
                echo "Unrecognized format";
                exit();
        }
        echo "Saving file to: {$this->dictionary_path}\n";
        $dictionary = [];
        $N = 0;
        if (is_string($text_files)) {
            $text_files = [$text_files];
        }
        foreach($text_files as $text_file) {
            if (file_exists($text_file) && !is_dir($text_file)) {
                $fh = fopen($text_file, "r");
                while(!feof($fh))  {
                    $line = fgets($fh);
                    if ($ctb_fmt and preg_match('/^<.*>$/', trim($line))) {
                        continue;
                    }
                    $words = preg_split("/[\s　]+/u", $line);
                    foreach ($words as $word) {
                        if (!empty($word) && !$this->isException($word) &&
                            !$this->notCurrentLang($word)) {
                            if (!empty($this->tokenizer) &&
                              method_exists($this->tokenizer, "normalize")) {
                                  $word=$this->tokenizer::normalize($word);
                            }
                            if (!empty($dictionary[$word])) {
                                $dictionary[$word]++;
                            } else if (mb_strlen($word) <
                                self::MAX_TERM_LENGTH) {
                                $dictionary[$word] = 1;
                            }
                        }
                    }
                }
                fclose($fh);
            }
        }
        $this->dictionary = [];
        $this->dictionary["N"] = 0;
        $this->dictionary["dic"] = [];
        ksort($dictionary);
        $start_char = null;
        $tmp_array = [];
        foreach ($dictionary as $key => $value) {
            if (mb_substr($key, 0, 1) != $start_char) {
                $this->dictionary["dic"][$start_char] =
                    json_encode($tmp_array[$start_char] ?? []);
                $tmp_array = [];
                $start_char = mb_substr($key, 0, 1);
            }
            $this->add($key, $value, $tmp_array);
            $this->dictionary["N"]++;
        }
        $this->unknown_term_score = $this->getScore(1);
        file_put_contents($this->dictionary_path,
            gzencode(json_encode($this->dictionary), 9));
    }
    /**
     * Segments the text in a list of files
     * @param mixed $text_files can be a file name or a list of file names
     *        to be segmented
     * @param bool $return_string return segmented string if true,
     *        print to stdout otherwise
     *        user can use > filename to output it to a file
     * @return string segmented words with space or true/false;
     */
    public function segmentFiles($text_files, $return_string = false)
    {
        if ($return_string) {
            $result = "";
        }
        if (is_string($text_files)) {
            $text_files = [$text_files];
        }
        foreach($text_files as $text_file) {
            if (file_exists($text_file)) {
                $fh = fopen($text_file, "r");
                while(! feof($fh))  {
                    $line = fgets($fh);
                    if (mb_strlen($line)) {
                        $t = $this->segmentSentence($line);
                        if ($return_string) {
                            $result .= join( " ", $t) ."\n" ;
                        } else {
                            echo join(" ", $t) . "\n";
                        }
                    }
                }
                fclose($fh);
            } else {
                echo "cannot open $text_file\n";
            }
        }
        if ($return_string) {
            return $result;
        }
        return true;
    }
    /**
     * Segments text into terms separated by space
     * @param string $text to be segmented
     * @param string $normalize return the normalized form
     *                乾隆->干隆
     * @return string segmented terms with space
     */
    public function segmentText($text, $normalize=false)
    {
        $segmented_text = "";
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            if (mb_strlen($line) > 0) {
                $segmented_line = $this->segmentSentence($line);
                if (!empty($segmented_line)) {
                    $segmented_text .= join(" ", $segmented_line) . "\n";
                }
            }
        }
        return mb_substr($segmented_text, 0, -1);
    }
    /**
     * Segments a single sentence into an array of words.
     * Must NOT contain any new line characters.
     * @param string $sentence is a string without newline to be segmented
     * @return array of segmented words
     */
    public function segmentSentence($sentence)
    {
        $t = preg_split("/[\s　]+/u", trim($sentence));
        if(count($t) > 1) {
            $ret = [];
            foreach($t as $s) {
                $segments = $this->segmentSentence($s);
                if (is_array($segments)) {
                    $ret = array_merge($ret, $segments);
                }
            }
            return $ret;
        }
        if (!$this->dictionary) {
            if (!file_exists($this->dictionary_path)) {
                crawlLog("{$this->dictionary_path} does not exist!");
                return null;
            }
            $this->dictionary =
                json_decode(gzdecode(file_get_contents(
                    $this->dictionary_path)), true);
            gc_collect_cycles();
            $this->unknown_term_score = $this->getScore(1);
        }
        $cache_size =
            floor(count($this->dictionary['dic']) * $this->cache_pct);
        if ($cache_size == 0) {
            $cache_size = 1;
        }
        $unnormalized = trim($sentence);
        $normalized = (!empty($this->tokenizer) &&
           method_exists($this->tokenizer, "normalize")) ?
           $this->tokenizer::normalize($unnormalized) : $unnormalized;
        $characters = preg_split('//u', $normalized, null,
            PREG_SPLIT_NO_EMPTY);
        if (!count($characters)) {
            return [];
        }
        $net_dict = [];
        if (isset($this->named_entity_tagger)) {
            $named_entities = $this->named_entity_tagger->predict(
                $characters);
            foreach($named_entities as $e) {
                $this->add($e[0], 1, $net_dict);
            }
        }
        $score = [];
        $path = [];
        //init base
        $score[-1] = 0;
        for($index = 0; $index < count($characters); $index++) {
            //If not current language
            if ($this->notCurrentLang($characters[$index]) &&
                !$this->isPunctuation($characters[$index])) {
                $current_char = $characters[$index];
                for($j = $index + 1; $j < count($characters); $j++) {
                    if ($this->notCurrentLang($current_char . $characters[$j])&&
                        !$this->isPunctuation($characters[$j])) {
                        $current_char .= $characters[$j];
                    } else {
                        break;
                    }
                }
                if (!isset($score[$j - 1]) ||  $score[$j - 1] >
                    $score[$index - 1] + $this->unknown_term_score) {
                    $score[$j - 1] = $score[$index - 1] +
                        $this->unknown_term_score;
                    $path[$j - 1] = $index - 1;
                }
            }
            //If date or number
            if ($this->isException($characters[$index])) {
                $current_char = $characters[$index];
                for($j = $index + 1; $j < count($characters); $j++) {
                    if (!$this->isException(
                        $current_char . $characters[$j])) {
                        break;
                    }
                    $current_char .= $characters[$j];
                }
                if (!isset($score[$j - 1]) ||
                    $score[$j - 1] > $score[$index - 1] +
                        $this->unknown_term_score) {
                    $score[$j - 1] = $score[$index - 1] +
                        $this->unknown_term_score;
                    $path[$j - 1] = $index - 1;
                }
            }
            //If is punctuation, give slightly better score than unknown words
            if ($this->isPunctuation($characters[$index])) {
                $current_char = $characters[$index];
                for($j = $index + 1; $j < count($characters); $j++) {
                    if (!$this->isPunctuation(
                        $current_char . $characters[$j])) {
                        break;
                    }
                    $current_char .= $characters[$j];
                }
                if (!isset($score[$j - 1]) ||
                    $score[$j - 1] > $score[$index - 1] +
                        $this->unknown_term_score / 1.1) {
                    $score[$j - 1] = $score[$index - 1] +
                        $this->unknown_term_score  / 1.1;
                    $path[$j - 1] = $index - 1;
                }
            }
            /* All case (Even not in current lang because dictionary may
                contains those terms
                check the first char, give score even nothing matches
             */
            if (!isset($score[$index]) ||
                $score[$index-1] + $this->unknown_term_score < $score[$index]) {
                $score[$index] = $score[$index-1] +
                    $this->unknown_term_score;
                $path[$index] = $index - 1;
            }
            //if entry exists, look for the term
            if (isset($this->dictionary["dic"][$characters[$index]])) {
                if (!isset($this->cache[$characters[$index]])) {
                    $this->cache = [$characters[$index] =>
                        json_decode(
                        $this->dictionary["dic"][$characters[$index]],
                        true)] + $this->cache;
                    while (count($this->cache) > $cache_size) {
                        array_pop($this->cache);
                    }
                }
                $subdic = $this->cache;
                for ($j = $index; $j < count($characters); $j++) {
                    if (!isset($subdic[$characters[$j]])) {
                        break;
                    }
                    $subdic = $subdic[$characters[$j]];
                    if (isset($subdic['$']) && (!isset($score[$j]) ||
                        (isset($score[$index - 1]) && is_numeric($subdic['$'])
                        && is_numeric($subdic['$']) &&
                        $score[$index - 1] + $subdic['$'] < $score[$j]))) {
                        $score[$j] = $score[$index - 1] +
                            $this->getScore($subdic['$']);
                        $path[$j] = $index - 1;
                    }
                }
            }
            //Check Named Entity Tagger dictionary
            if (isset($net_dict[$characters[$index]])) {
                $subdic = $net_dict;
                for ($j = $index; $j < count($characters); $j++) {
                    if (!isset($subdic[$characters[$j]])) {
                        break;
                    }
                    $subdic = $subdic[$characters[$j]];
                    if (isset($subdic['$']) && (!isset($score[$j]) ||
                        (isset($score[$index - 1]) &&
                        $score[$index - 1] + $subdic['$'] < $score[$j]))) {
                        $score[$j] = $score[$index - 1] +
                            $this->getScore($subdic['$']);
                        $path[$j] = $index - 1;
                    }
                }
            }
        }
        //trace path
        $t = max(array_keys($path));
        $tmp = [];
        while($t != -1) {
            $tmp[] = $t;
            $t = $path[$t];
        }
        $result = [];
        $t = 0;
        foreach(array_reverse($tmp) as $next_node) {
            $result_word = "";
            while($t <= $next_node) {
              $result_word .= $characters[$t];
              $t++;
            }
            $result[] = $result_word;
        }
        return $result;
    }
    /**
     * Calculates a score for a term based on its frequency versus that
     * of the whole trie.
     * @param int $frequency is an integer tells the frequency of a word
     * @return float the score of the term.
     */
    public function getScore($frequency)
    {
        if (!empty($this->dictionary["N"]) &&
            is_numeric($this->dictionary["N"])) {
            return -log($frequency / $this->dictionary["N"]);
        } else {
            return 0;
        }
    }
    /**
     * Adds a (term, frequency) pair to an array based trie
     *
     * @param string $term the term to be inserted
     * @param string $frequency the frequency to be inserted
     * @param array &$trie array based trie we want to insert the key value
     *      pair into
     */
    public function add($term, $frequency, &$trie)
    {
        $sub_trie = & $trie;
        for ($i = 0; $i < mb_strlen($term, "utf-8"); $i++) {
            $character = mb_substr($term, $i, 1, "utf-8");
            $enc_char = $character;
            // If letter doesnt exist then create one by
            // assigning new array
            if (!isset($sub_trie[$enc_char])) {
                $sub_trie[$enc_char] = [];
            }
            $sub_trie = &$sub_trie[$enc_char];
        }
        // Set end of term marker
        $sub_trie['$'] = $frequency;
    }
}
