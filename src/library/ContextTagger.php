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
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */

namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
/**
 * Abstract, base context tagger class.
 * A context tagger is used to apply a sequence of labels to a sequence terms
 * or characters of text based on a surrounding context. Context Taggers
 * typically make use of n-gram context of a term such as the n/2 - terms
 * before and after the term and maybe the earlier tags from a same phrase or
 * sentence to make prediction
 *
 * @author Chris Pollett
 */
abstract class ContextTagger
{
    /**
     * Locale tag of language this recognizer is for
     * @var string
     */
    public $lang;
    /**
     * The name of the file where the tagging model should be stored and read
     * from
     * @var string
     */
    public $tagger_file = "tagger.txt.gz";
    /**
     * Complete file system path to the file where the tagging model should be
     * stored and read from
     * @var string
     */
    public $tagger_path = "";
    /**
     * 2D weights for features involving the prior two words to the
     * current word and the next two words after the current word
     * For a given word position, one has vector, that gives te
     * value for each term in the complete training term set, unknown term set,
     * and rule based tag term set, what its weight is
     * Determined during training
     * @var array
     */
    public $word_feature;
    /**
     * The bias vector for features we are training
     *
     * Determined during training
     * @var array
     */
    public $bias;
    /**
     * The weights for features involving the prior two tags to the
     * current word whose tag we are trying to determine
     * Determined during training
     * @var array
     */
    public $tag_feature;
    /**
     * Array of strings for each possible tag for a term
     * associated as [tag => tag index]
     * @var array
     */
    public $tag_set;
    /**
     * Minimum allowed value for a weight component
     * @var float
     */
    public $min_w;
    /**
     * Maximum allowed value for a weight component
     * @var float
     */
    public $max_w;
    /**
     * Tokenizer for the language this tagger tags for
     * @var Tokenizer
     */
    public $tokenizer;
    /**
     * Constructor for the ContextTagger.
     * Sets the language this tagger tags for and sets up the path for
     * where it should be stored
     * @param string $lang locale tag of the language this tagger tags is for
     */
    public function __construct($lang)
    {
        $lang = str_replace("-", "_", $lang);
        $this->lang = $lang;
        $this->tagger_path = C\LOCALE_DIR . "/$lang/resources/" .
            $this->tagger_file;
        $this->tokenizer = PhraseParser::getTokenizer($lang);
    }
    /**
     * Converts training data from the format tagged sentence with terms of the
     * form term_tag into a pair of arrays [[terms_in_sentence],
     *  [tags_in_sentence]]
     * @param mixed $text_files can be a file or an array of file names
     * @param string $term_tag_separator separator used to separate term and tag
     *  for terms in input sentence
     * @param function $term_callback callback function applied to a term
     *  before adding term to sentence term array
     * @param function $tag_callback callback function applied to a part of
     *  speech tag  before adding tag to sentence tag array
     * @param bool $tag_on_array_chars for some kinds of text processing
     *  it better to assume the tags are applied to each char within a term
     *  rather than at the term level. For example, we might want to use
     *  char within a term for name entity tagging. THis flag if true
     *  says to do this; otherwise don't
     * @return array of separated sentences, each sentence having the format of
     *  [[terms...], [tags...]]
     *  Currently, the training data needs to fit Chinese Treebank format:
     *  term followed by a underscore and followed by the tag
     *  e.g. "新_VA 的_DEC 南斯拉夫_NR 会国_NN"
     *  To adapt to other language, some modifications are needed
     */
    public static function processTexts($text_files, $term_tag_separator = "_",
        $term_callback = null, $tag_callback = null,
        $tag_on_array_chars = false)
    {
        $out = [];
        foreach($text_files as $text_file) {
            if (file_exists($text_file)) {
                $fh = fopen($text_file, "r");
                while (!feof($fh))  {
                    $line = fgets($fh);
                    if(strpos($line, '<') !== false) {
                        continue;
                    }
                    $term_tag_pairs = preg_split("/[\s　]+/u", $line);
                    if (!count($term_tag_pairs)) {
                        continue;
                    }
                    $out[] = [];
                    $last_out = count($out) - 1;
                    $out[$last_out][0] = [];
                    $out[$last_out][1] = [];
                    foreach ($term_tag_pairs as $term_tag_pair) {
                        $t = explode($term_tag_separator, $term_tag_pair);
                        if (count($t) == 2) {
                            $tag = $tag_callback ? $tag_callback($t[1]) : $t[1];
                            if ($tag_on_array_chars) {
                                $to_tags = preg_split('//u', $t[0], null,
                                    PREG_SPLIT_NO_EMPTY);
                            } else {
                                $to_tags = [$t[0]];
                            }
                            foreach($to_tags as $to_tag) {
                                $out[$last_out][0][] = $term_callback ?
                                    $term_callback($to_tag) : $to_tag;
                                $out[$last_out][1][] = $tag;
                            }
                        }
                    }
                }
                fclose($fh);
            }
        }
        return $out;
    }
    /**
     * Maps a term to a corresponding key if the term matches some simple
     * pattern such as being a number
     * @param string $term is the term to be checked
     * @return mixed either the int key for those matrices of just the term
     *  itself if the tokenizer does not ave the method getPosKey for the
     *  current language
     */
    public function getKey($term)
    {
        if (!empty($this->tokenizer) && method_exists($this->tokenizer,
            "getPosKey")) {
            return $this->tokenizer::getPosKey($term);
        }
        return $term;
    }
    /**
     * Given a sentence (array $terms), find the key for the term at position
     * $index
     * @param int $index position of term to get key for
     * @param array $terms an array of terms typically from and in the order of
     *  a sentence
     * @return mixed key position in word_feature weights and bias arrays
     *  could be either an int, or the term itself, or the simple rule
     *  based part of speec it belongs to
     */
    public function getIndex($index, $terms)
    {
        if ($index < 0) {
            $k = $index - 2;
        } else if ($index >= count($terms)) {
            $k = $index - count($terms) - 2;
        } else {
            $k = $this->getKey($terms[$index]);
        }
        return $k;
    }
    /**
     * Save the trained weight to disk
     */
    public function saveWeights()
    {
        $out = [];
        $out["min_w"] = $this->min_w;
        $out["max_w"] = $this->max_w;
        $out["w"] = [];
        foreach(array_keys($this->word_feature) as $key) {
            $out["w"][$key] = $this->packW($key);
        }
        foreach(array_keys($this->tag_feature) as $key) {
            $out["t"][$key] = $this->packT($key);
        }
        $out["b"] = $this->packB();
        $out["tag_set"] = $this->tag_set;
        echo "Saving...";
        file_put_contents($this->tagger_path,
            gzencode(serialize($out), 9));
        echo " ok\n";
    }
    /**
     * Load the trained data from disk
     * @param bool $for_training whether we are continuing to train (true) or
     *  whether we are using the loaded data for prediction
     */
    public function loadWeights($for_training = false)
    {
        if (!file_exists($this->tagger_path)) {
            echo "$this->tagger_path does not exist!";
            exit();
        }
        $f = unserialize(gzdecode(file_get_contents($this->tagger_path)),
            ['allowed_classes' => false]);
        $this->word_feature = $f["w"];
        $this->tag_feature = $f["t"] ?? [];
        $this->bias = $f["b"];
        $this->min_w = $f["min_w"];
        $this->max_w = $f["max_w"];
        $this->tag_set = $f["tag_set"];
        if ($for_training) {
            foreach(array_keys($this->word_feature) as $key) {
                $this->word_feature[$key] = $this->unpackW($key);
            }
            foreach(array_keys($this->tag_feature) as $key) {
                $this->tag_feature[$key] = $this->unpackT($key);
            }
            $this->bias = $this->unpackB();
        }
    }
    /**
     * Pack the bias vector represented as an array into a string
     * @return string the bias vector packed as a string
     */
    public function packB()
    {
        return pack("f*", ...$this->bias);
    }
    /**
     * Unpack the bias represented as a string into an array
     * @return array the bias vector unpacked from a string
     */
    public function unpackB()
    {
        return array_merge(unpack("f" . strval(count($this->tag_set)),
            $this->bias));
    }
    /**
     * Pack the tag_feature represented as an array into a string
     * @param int $key in tag_feature set corresponding to a part of speech
     * @return string packed tag_feature vector
     */
    public function packT($key)
    {
        return pack("f*", ...$this->tag_feature[$key]);
    }
    /**
     * Unpack the tag_feature represented as a string into an array
     * @param int $key in tag_feature set corresponding to a part of speech
     * @return array unpacked tag_feature vector
     */
    public function unpackT($key)
    {
        return array_merge(unpack("f" . strval(count($this->tag_set)),
            $this->tag_feature[$key]));
    }
    /**
     * Pack the weights matrix to a string for a particular part of speech key
     * @param int $key index corresponding to a part of speech according to
     *  $this->tag_set
     * @return string the packed weights matrix
     */
    public function packW($key)
    {
        $bin_str = "";
        foreach ($this->word_feature[$key] as $i => $t) {
            foreach ($t as $u) {
                $v = 65535 * ($u - $this->min_w) /
                    ($this->max_w - $this->min_w);
                $bin_str .= pack("S", intval($v));
            }
        }
        return $bin_str;
    }
    /**
     * Unpack the weight matrix for a given part of speech key. This
     * is a 5 x term_set_size matrix the 5 rows corresponds to
     * -2, -1, 0, 1, 2, locations in a 5-gram.
     * An (i, j) entry roughly gives the probability of the j term in location i
     * having the part of speech given by $key
     * @param int $key in word_feature set corresponding to a part of speech
     * @return array of weights corresponding to that key
     */
    public function unpackW($key)
    {
        $weights = [];
        $size = count($this->tag_set);
        for ($i = 0; $i < 5; $i++) {
            $weights[$i - 2] = array_merge(unpack("S" . strval($size),
                $this->word_feature[$key], 2 * $i * count($this->tag_set)));
            for($j = 0; $j < $size; $j++) {
                $weights[$i - 2][$j] = ($weights[$i - 2][$j] / 65535) *
                    ($this->max_w - $this->min_w) + $this->min_w;
            }
        }
        return $weights;
    }
    /**
     * Get the bias value for a tag
     * @param int $tag_index the index of tag's value within the bias string
     * @return float bias value for tag
     */
    public function getB($tag_index)
    {
        return unpack("f", $this->bias, $tag_index * 4)[1];
    }
    /**
     * Set the bias value for tag
     * @param int $tag_index the index of tag's value within the bias string
     * @param float $value bias value to associate to tag
     */
    public function setB($tag_index, $value)
    {
        $this->bias = substr_replace($this->bias, pack("f", $value),
            $tag_index * 4, 4);
    }
    /**
     * Get the tag feature value for tag
     * @param int $key in tag_feature set corresponding to a part of speech
     * @param int $tag_index the index of tag's value within the tag feature
     *  string
     * @return float tag feature value for tag
     */
    public function getT($key, $tag_index)
    {
        return unpack("f", $this->tag_feature[$key], $tag_index * 4)[1];
    }
    /**
     * Get the weight value for term at position for tag
     * @param string $term to get weight of
     * @param int $position of term within the current 5-gram
     * @param int $tag_index index of the particular tag we are trying to see
     *  the term's weight for
     * @return float
     */
    public function getW($term, $position, $tag_index)
    {
        $t = unpack("S", $this->word_feature[$term], 2 * ($position + 2) *
            count($this->tag_set) + $tag_index * 2)[1] / 65535 *
            ($this->max_w - $this->min_w) + $this->min_w;;
        return $t;
    }
    /**
     * Tags a sequence of strings according to this tagger's predict method
     * returning the tagged result as a string.
     * This function is mainly used to facilitate unit testing of taggers.
     * @param string $text to be tagged
     * @param string $tag_separator terms in the output string will
     *  be the terms from the input texts followed by $tag_separator
     *  followed by their tag. So if $tag_separator == "_", then a term
     *  中国 in the input texts might be 中国_NR in the output string
     * @return string single string where terms in the intput texts
     *  have been tagged. For example output might look like:
     *  中国_NR 人民_NN 将_AD 满怀信心_VV
     *  地_DEV 开创_VV 新_VA 的_DEC 业绩_NN 。_PU
     */
    public function tag($text, $tag_separator = "_")
    {
        $tagged_text = "";
        $lines = preg_split('/\r\n|\r|\n/u', $text);
        foreach($lines as $line) {
            $line_vector = explode(" ", trim($line));
            $tag_vector = $this->predict($line_vector);
            $tagged_term_vector = [];
            for($i = 0; $i < count($tag_vector); $i++) {
                if (is_array($tag_vector[$i])) {
                    list($term_element, $tag_element) = $tag_vector[$i];
                } else {
                    $term_element = $line_vector[$i];
                    $tag_element = $tag_vector[$i];
                }
                $tagged_term_vector[$i] = $term_element . $tag_separator .
                    $tag_element;
            }
            $tagged_text .= join(" ", $tagged_term_vector);
        }
        return $tagged_text;
    }
    /**
     * Uses text files to train a tagger for terms or chars in a  document
     * @param mixed $text_files with training data. These can be a file or
     *  an array of file names.
     * @param string $term_tag_separator separator used to separate term and tag
     *  for terms in input sentence
     * @param float $learning_rate learning rate when cycling over data trying
     *  to minimize the cross-entropy loss in the prediction of the tag of the
     *  middle term.
     * @param int $num_epoch number of times to cycle through the
     *  complete data set. Default value of 1200 seems to avoid overfitting
     * @param function $term_callback callback function applied to a term
     *  before adding term to sentence term array as part of processing and
     *  training with a sentence.
     * @param function $tag_callback callback function applied to a part of
     *  speech tag  before adding tag to sentence tag array as part of
     *  processing and training with a sentence.
     */
    public abstract function train($text_files, $term_tag_separator = "-",
        $learning_rate = 0.1, $num_epoch = 1200, $term_callback = null,
        $tag_callback = null, $resume = false);
    /**
     * Predicts a tagging for all elements of $sentence
     *
     * @param mixed $sentence is an array of segmented terms/chars
     *  or a string that will be split on white space
     * @return array predicted tags. The ith entry in the returned results
     *  is the tag of ith element of $sentence
     */
    public abstract function predict($sentence);
}
