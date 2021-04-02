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
 * Machine learning based named entity recognizer.
 * NamedEntityContextTagger is used by @see StochasticTermSegmenter
 * to help in segmenting sentences in which no term separators such as spaces
 * are used.
 *
 * @author Xianghong Sun (Principal),
 *  Chris Pollett (mainly simplifications, and documentation)
 */
class NamedEntityContextTagger extends ContextTagger
{
    /**
     * Maximum character length of a named entity
     */
    const MAX_ENTITY_LENGTH = 10;
    /**
     * Minimum entropy needs to go down between epochs or we stop training
     */
    const MIN_ENTROPY_CHANGE = 0.000001;
    /**
     * Constructor for the NamedEntityContextTagger.
     * Sets the language this tagger tags for and sets up the path for
     * where it should be stored
     * @param string $lang locale tag of the language this tagger tags is for
     */
    public function __construct($lang)
    {
        $this->tagger_file = "nect_weights.txt.gz";
        parent::__construct($lang);
    }
    /**
     * Uses text files containing sentences to create a matrix
     * so that from a two chars before a term, two chars after a char context,
     * together with a two tags before a term context and a term,
     * the odds that a named entity as been found can be calculated
     * Format of training file should be a tagged white space separated terms
     * If the separator was '-', then non-named entity examples should look like
     * term-o, and named entity example might look like term-nr or term-nt
     * where nr = proper noun, ns = place name, nt = temporal noun. The
     * use of a $tag_callback might help in mapping more general datasets into
     * this format
     *
     * @param mixed $text_files with training data. These can be a file or
     *  an array of file names.
     * @param string $term_tag_separator separator used to separate term and tag
     *  for terms in input sentence
     * @param float $learning_rate learning rate when cycling over data trying
     *  to minimize the cross-entropy loss in the prediction of the tag of the
     *  middle term.
     * @param int $num_epochs number of times to cycle through the
     *  complete data set. Default value of 1200 seems to avoid overfitting
     * @param function $term_callback callback function applied to a term
     *  before adding term to sentence term array as part of processing and
     *  training with a sentence.
     * @param function $tag_callback callback function applied to a part of
     *  speech tag  before adding tag to sentence tag array as part of
     *  processing and training with a sentence.
     */
    public function train($text_files, $term_tag_separator = "-",
        $learning_rate = 0.1, $num_epochs = 1200, $term_callback = null,
        $tag_callback = null, $resume = false)
    {
        if (is_string($text_files)) {
            $text_files = [$text_files];
        }
        echo "Reading files... \n";
        if(!$term_callback && !empty($this->tokenizer) &&
            method_exists($this->tokenizer, "normalize")) {
            $term_callback = [$this->tokenizer, "normalize"];
        }
        // term_tag_sentences[sentence#] = [[words...], [tags...]]
        $term_tag_sentences = self::processTexts($text_files,
            $term_tag_separator, $term_callback, $tag_callback, true);
        $this->word_feature = [];
        $this->tag_set = [];
        $tag_index = 0;
        for ($i = -4; $i <= -1; $i++) {
            $this->word_feature[$i] = [];
        }
        foreach ($term_tag_sentences as $term_tag_pairs) {
            $terms = $term_tag_pairs[0];
            $tags = $term_tag_pairs[1];
            $this->tag_feature["start"] = [];
            $this->tag_feature["start-start"] = [];
            for ($i = 0; $i < count($terms); $i++) {
                if (!isset($this->tag_set[$tags[$i]])) {
                    $this->tag_set[$tags[$i]] = $tag_index++;
                }
                if ($i == 0) {
                } else if ($i == 1) {
                    if (!isset($this->tag_feature["start-" . $tags[$i-1]])) {
                        $this->tag_feature["start-" . $tags[$i - 1]] = [];
                    }
                    if (!isset($this->tag_feature[$tags[$i - 1]])) {
                        $this->tag_feature[$tags[$i - 1]] = [];
                    }
                } else {
                    if (!isset($this->tag_feature[$tags[$i - 2] . "-" .
                        $tags[$i - 1]])) {
                        $this->tag_feature[$tags[$i - 2] . "-" .
                            $tags[$i - 1]] = [];
                    }
                    if (!isset($this->tag_feature[$tags[$i - 1]])) {
                        $this->tag_feature[$tags[$i - 1]] = [];
                    }
                }
                if (!isset($this->word_feature[$terms[$i]])) {
                    $this->word_feature[$terms[$i]] = [];
                }
            }
        }
        foreach (array_keys($this->word_feature) as $key) {
            for ($i = -2; $i <= 2; $i++) {
                if (!isset($this->word_feature[$key][$i])) {
                    $this->word_feature[$key][$i] = [];
                }
                foreach($this->tag_set as $possible_tag => $tag_index) {
                    if (!isset($this->word_feature[$key][$i][$tag_index])) {
                        $this->word_feature[$key][$i][$tag_index] = 0;
                    }
                }
            }
        }
        foreach (array_keys($this->tag_feature) as $key) {
            foreach($this->tag_set as $possible_tag => $tag_index) {
                if (!isset($this->tag_feature[$key][$tag_index])) {
                    $this->tag_feature[$key][$tag_index] = 0;
                }
            }
        }
        foreach($this->tag_set as $possible_tag => $tag_index) {
            if (!isset($this->bias[$tag_index])) {
                $this->bias[$tag_index] = 0;
            }
        }
        echo "Training...\n";
        //train the weight
        $cross_entropy_loss = 1;
        $pre_cross_entropy_loss = 2;
        for ($epoch = 0; $epoch < $num_epochs &&
            $pre_cross_entropy_loss - $cross_entropy_loss >
            self::MIN_ENTROPY_CHANGE; $epoch++) {
            $this->min_w = 0;
            $this->max_w = 0;
            $time = time();
            $dy_dw = [];
            $dy_dw_n = [];
            $pre_cross_entropy_loss = $cross_entropy_loss;
            $cross_entropy_loss = 0;
            $cross_entropy_loss_n = 0;
            $dy_db = [];
            $dy_db_n = [];
            $dy_dt = [];
            $dy_dt_n = [];
            for($i = 0; $i < count($this->tag_set); $i++) {
                $dy_db[$i] = 0;
                $dy_db_n[$i] = 0;
            }
            //for each sentence
            foreach ($term_tag_sentences as $term_tag_pairs) {
                $terms = $term_tag_pairs[0];
                $tags = $term_tag_pairs[1];
                for ($i = 0; $i < count($terms); $i++) {
                    $k = [];
                    for ($j = -2; $j <= 2; $j++) {
                        $k[$j] = $this->getIndex($i + $j, $terms);
                    }
                    foreach ($this->tag_set as $possible_tag => $tag_index) {
                        $equality = ($possible_tag == $tags[$i]) ? 1 : 0;
                        $sum = 0;
                        //5 terms including term itself
                        for ($j = -2; $j <= 2; $j++) {
                            $sum += $this->word_feature[$k[$j]][$j][$tag_index]
                                ?? 0;
                        }
                        //previous 2 tags
                        if ($i == 0) {
                            $tf1 = "start";
                            $tf2 = "start-start";
                        } else if ($i == 1) {
                            $tf1 = $tags[$i - 1];
                            $tf2 = "start-" . $tags[$i-1];
                        } else {
                            $tf1 = $tags[$i - 1];
                            $tf2 = $tags[$i - 2] . "-" . $tags[$i - 1];
                        }
                        $sum += $this->tag_feature[$tf1][$tag_index];
                        $sum += $this->tag_feature[$tf2][$tag_index];
                        //bias
                        $sum += $this->bias[$tag_index];
                        $sigmoid = 1 / (1 + exp(-1 * $sum));
                        for ($j = -2; $j <= 2; $j++) {
                            if (!isset($dy_dw[$k[$j]])) {
                                $dy_dw[$k[$j]] = [];
                                $dy_dw_n[$k[$j]] = [];
                            }
                            if (!isset($dy_dw[$k[$j]][$j])) {
                                $dy_dw[$k[$j]][$j] = [];
                                $dy_dw_n[$k[$j]][$j] = [];
                            }
                            if (!isset($dy_dw[$k[$j]][$j][$tag_index])) {
                                $dy_dw[$k[$j]][$j][$tag_index] = 0;
                                $dy_dw_n[$k[$j]][$j][$tag_index] = 0;
                            }
                            $dy_dw[$k[$j]][$j][$tag_index] +=
                                ($sigmoid - $equality);
                            $dy_dw_n[$k[$j]][$j][$tag_index] += 1;
                        }
                        //dy_dt
                        if (!isset($dy_dt[$tf1])) {
                            $dy_dt[$tf1] = [];
                            $dy_dt_n[$tf1] = [];
                        }
                        if (!isset($dy_dt[$tf1][$tag_index])) {
                            $dy_dt[$tf1][$tag_index] = 0;
                            $dy_dt_n[$tf1][$tag_index] = 0;
                        }
                        if (!isset($dy_dt[$tf2])) {
                            $dy_dt[$tf2] = [];
                            $dy_dt_n[$tf2] = [];
                        }
                        if (!isset($dy_dt[$tf2][$tag_index])) {
                            $dy_dt[$tf2][$tag_index] = 0;
                            $dy_dt_n[$tf2][$tag_index] = 0;
                        }
                        $dy_dt[$tf1][$tag_index] += ($sigmoid - $equality);
                        $dy_dt_n[$tf1][$tag_index] += 1;
                        $dy_dt[$tf2][$tag_index] += ($sigmoid - $equality);
                        $dy_dt_n[$tf2][$tag_index] += 1;
                        //dy_db
                        $dy_db[$tag_index] += ($sigmoid - $equality);
                        $dy_db_n[$tag_index] += 1;
                        $cross_entropy_loss -= ($equality * log($sigmoid)
                            + (1 - $equality) * log(1 - $sigmoid));
                        $cross_entropy_loss_n++;
                    }
                }
            }
            $cross_entropy_loss /= $cross_entropy_loss_n;
            $duration = time() - $time;
            echo "Epoch {$epoch} of {$num_epochs} took {$duration} seconds." .
                " Current cross_entropy is {$cross_entropy_loss}\n";
            foreach ($dy_dw as $i => $v1) {
                foreach ($v1 as $j => $v2) {
                    foreach ($v2 as $k => $v3) {
                        $this->word_feature[$i][$j][$k] ??= 0;
                        $this->word_feature[$i][$j][$k] -= $dy_dw[$i][$j][$k] /
                            $dy_dw_n[$i][$j][$k] * $learning_rate;
                        if ($this->word_feature[$i][$j][$k] < $this->min_w) {
                            $this->min_w = $this->word_feature[$i][$j][$k];
                        }
                        if ($this->word_feature[$i][$j][$k] > $this->max_w) {
                            $this->max_w = $this->word_feature[$i][$j][$k];
                        }
                    }
                }
            }
            foreach ($dy_dt as $i => $v1) {
                foreach ($v1 as $j => $v2) {
                    $this->tag_feature[$i][$j] -= $dy_dt[$i][$j] /
                        $dy_dt_n[$i][$j] * $learning_rate;
                }
            }
            foreach ($dy_db as $k => $v) {
                $this->bias[$k] -= $dy_db[$k] / $dy_db_n[$k] * $learning_rate;
            }
            if ($epoch % 10 == 9) {
                $this->saveWeights();
            }
        }
        $this->saveWeights();
    }
    /**
     * Predicts named entities that exists in a sentence.
     * @param mixed $sentence is an array of segmented words/terms
     *  or a string that will be split on white space
     * @return array all predicted named entities together with a tag
     *  indicating kind of named entity
     *  ex. [["郑振铎","nr"],["国民党","nt"]]
     */
    public function predict($sentence)
    {
        if (empty($sentence)) {
            return [];
        }
        if (is_array($sentence)) {
            $sentence_vector = $sentence;
        } else {
            $sentence_vector = preg_split("/[\s]+/u", $sentence);
        }
        if (!$this->word_feature) {
            $this->loadWeights();
        }
        $found_entities = [];
        foreach ($sentence_vector as $unnormalized) {
            if (!empty($this->tokenizer) &&
                method_exists($this->tokenizer, "normalize")) {
                /* Mainly used to map Chinese traditional to
                   simplified character
                 */
                $term = $this->tokenizer::normalize($unnormalized);
            } else {
                $term = $unnormalized;
            }
            $characters = preg_split('//u', $term, null,
                PREG_SPLIT_NO_EMPTY);
            if (empty($characters)) {
                continue;
            }
            $tags = [];
            for($i = 0; $i < count($characters); $i++) {
                $character = $characters[$i];
                $score = [];
                foreach($this->tag_set as $possible_tag => $tag_index) {
                    $score[$possible_tag] = 0;
                    for ($j = -2; $j <= 2; $j++) {
                        $k = $this->getIndex($i + $j, $characters);
                        if (isset($this->word_feature[$k])) {
                            $score[$possible_tag] +=
                                $this->getW($k, $j, $tag_index);
                        }
                    }
                    if ($i == 0) {
                        $tf1 = "start";
                        $tf2 = "start-start";
                    } else if ($i == 1) {
                        $tf1 = $tags[$i - 1];
                        $tf2 = "start-" . $tags[$i - 1];
                    } else {
                        $tf1 = $tags[$i - 1];
                        $tf2 = $tags[$i - 2] . "-" . $tags[$i - 1];
                    }
                    $score[$possible_tag] += $this->getT($tf1, $tag_index);
                    $score[$possible_tag] += $this->getT($tf2, $tag_index);
                    $score[$possible_tag] += $this->getB($tag_index);
                }
                $tags[] = array_keys($score, max($score))[0];
            }
            $pre_tag = 'o';
            $current_entity = "";
            $entities = [];
            for ($i = 0; $i < count($characters); $i++) {
                if ($pre_tag != $tags[$i] && $pre_tag != "o") {
                    if (mb_strlen($current_entity) < self::MAX_ENTITY_LENGTH) {
                        $entities[] = [$current_entity, $pre_tag];
                    }
                    $current_entity = "";
                }
                if ($tags[$i] != "o") {
                    $current_entity .= $characters[$i] ?? "";
                }
                $pre_tag = $tags[$i];
            }
            if ($pre_tag != "o") {
                if (mb_strlen($current_entity) < self::MAX_ENTITY_LENGTH) {
                    $entities[] = [$current_entity, $pre_tag];
                }
            }
            $found_entities = array_merge($found_entities, $entities);
        }
        return $found_entities;
    }
}
