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
 * @author Chris Pollett (chris@pollett.org)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\summarizers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\LinearAlgebra;

/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing. This is done by
 * the @see getSummmary method. To generate a summary a normalized
 * term frequency vector is computed for each sentence. An average
 * vector is then computed by summing these and renormalizing the result.
 * The computation of this average vector is biased by weighting earlier
 * sentences vectors more when computing the sum of vectors. This is done
 * using weight coming from a Zipf like distribution. Once an average
 * sentence is obtained, then sentences are score against it using
 * a residual cosine similarity score. I.e., the most important sentence
 * is determined by cosine rank. Then the components of this sentence in
 * the direction of the average sentence is deleted from the average sentence.
 * and the next most important sentence is computed by ranking against this
 * new average sentence vector and so on.
 * @author Charles Bocage (charles.bocage@sjsu.edu)
 *     rewritten Chris Pollett (chris@pollett.org)
 */
class CentroidWeightedSummarizer extends Summarizer
{
    /**
     * Generates a summary, word cloud, and summary scores based on
     *  the closeness of normalized term frequency vectors to an average
     *  term frequency vector for sentences.
     *
     * @param object $dom document object model of page to summarize
     * @param string $page complete raw page to generate the summary from.
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     *
     * @return array a triple (string summary, array word cloud, array
     *      of position => scores for positions within the summary)
     */
    public static function getSummary($dom, $page, $lang)
    {
        list($original_sentences, $sentences) =
            self::getPunctuatedUnpunctuatedSentences($dom, $page, $lang);
        list($terms, $tf_per_sentence_normalized) =
            self::computeTermFrequenciesPerSentence($sentences, $lang);
        $tf_average_sentence =
            self::getAverageSentence($tf_per_sentence_normalized);
        $word_cloud = self::wordCloudFromTermVector($tf_average_sentence);
        $sorted_sentence_scores =
            self::scoreSentencesVersusAverage($tf_per_sentence_normalized,
            $tf_average_sentence);
        list($summary, $summary_scores) = self::getSummaryFromSentenceScores(
            $sorted_sentence_scores, $original_sentences, $lang);
        /* Summary of text summarization */
        return [$summary, $word_cloud, $summary_scores];
    }

    /**
     * Computes an average sentence by adding the normalized term frequency
     * vectors for each sentence weighted by a Zipf like distrbution on
     * sentence index and normalizing the resulting vector
     * @param array $term_frequencies_normalized the array with the terms as
     *      the key and its normalized frequency as the value
     * @return array a normalized vector of term => weights
     *
     */
    public static function getAverageSentence($term_frequencies_normalized)
    {
        $average_sentence = [];
        if (!empty($term_frequencies_normalized)) {
            foreach ($term_frequencies_normalized as
                $sentence_index => $term_frequencies_sentence) {
                /* used to slightly favor earlier sentences according
                   to a Zipf like behavior
                 */
                $sentence_weight = 1.0/(1.0 + pow($sentence_index, 0.80));
                foreach ($term_frequencies_sentence as
                    $term_index => $frequency) {
                    $frequency *= $sentence_weight;
                    $average_sentence[$term_index] =
                        (empty($average_sentence[$term_index])) ?
                        $frequency :
                        $average_sentence[$term_index] + $frequency;
                }
            }
            $average_sentence = LinearAlgebra::normalize($average_sentence);
            arsort($average_sentence);
        }
        return $average_sentence;
    }
    /**
     * Computes scores for each sentence => word vector in
     * an array of sentence => word_vectors based on
     * on how it compares versus an average sentence word vector
     * Here word vectors are normalized vectors and scores are determined
     * by inner product.
     *
     * @param array $sentence_vectors the array with the terms as
     *      the key and its normalized frequency as the value
     * @param array $average_sentence an array of each words average
     *      frequency value
     * @return array array of sentence index => score pairs
     */
    public static function scoreSentencesVersusAverage($sentence_vectors,
        $average_sentence)
    {
        $sentence_scores = [];
        $max_sentence_score = 1;
        while ($max_sentence_score > 0) {
            $max_sentence_score = -1;
            $max_sentence_index = -1;
            /* compute the most importance sentence vector based on
               current average
             */
            foreach ($sentence_vectors as $sentence => $word_vector) {
                $sentence_score = LinearAlgebra::dot($average_sentence,
                    $word_vector);
                if ($max_sentence_score < $sentence_score) {
                    $max_sentence_score = $sentence_score;
                    $max_sentence_index = $sentence;
                }
            }
            if ($max_sentence_score > -1) {
                // add most importants index and score to $sentence_scores
                $sentence_scores[$max_sentence_index] = $max_sentence_score;
                $word_vector = $sentence_vectors[$max_sentence_index];
                /* delete $word_vector (which is supposed to be normalized)
                   from average
                 */
                foreach ($word_vector as $term => $weight) {
                    $average_sentence[$term] -=
                        $average_sentence[$term] * $weight;
                }
                unset($sentence_vectors[$max_sentence_index]);
            }
        }
        return $sentence_scores;
    }
}
