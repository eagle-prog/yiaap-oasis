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
 * @author Charles Bocage charles.bocage@sjsu.edu
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\summarizers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\LinearAlgebra;

/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing. The method @see getSummary
 * is used to obtain such a summary. In GraphBasedSummarizer's implementation
 * of this method sentences are ranks using a page rank style algorithm
 * based on sentence adjacencies calculated using a distortion score between
 * pair of sentence (@see LinearAlgebra::distortion for details on this).
 * The page rank is then biased using a Zipf-like transformation to slightly
 * favor sentences earlier in the document
 *
 * @author Charles Bocage charles.bocage@sjsu.edu
 *      Chris Pollett chris@pollett.org
 */
class GraphBasedSummarizer extends Summarizer
{
    /**
     * This summarizer uses a page rank-like algorithm to find the
     * important sentences in a document, generate a word cloud, and
     * give scores for those sentences.
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
        $unmodified_doc = strip_tags($page,
            '<a><h1><h2><h3><h4><h5><h6><b><em><i><u><dl><ol><ul><title>');
        list($sentences_with_punctuation, $sentences) =
            self::getPunctuatedUnpunctuatedSentences($dom, $unmodified_doc,
            $lang);
        $sentences = PhraseParser::stemTermsK($sentences, $lang, true);
        list($terms, $tf_per_sentence_normalized) =
            self::computeTermFrequenciesPerSentence($sentences, $lang);
        $adjacency = self::computeAdjacency($tf_per_sentence_normalized);
        $sorted_sentence_ranks = self::getSentenceRanks($adjacency);
        list($summary, $summary_scores) = self::getSummaryFromSentenceScores(
            $sorted_sentence_ranks, $sentences_with_punctuation, $lang);
        return [$summary, self::wordCloudFromSummary($summary,  $lang),
            $summary_scores];
    }
    /**
     * Compute the sentence ranks using power method.
     * Take adjacency matrix and apply it 10 times to starting sentence
     * ranks, all of which are 1/n where n is the number of sentences.
     * We assume pr = (A^{10}r) approximates (A^{11}r) and so A*pr = pr,
     * i.e, the pr vector is a eigentvector of A and its components
     * approximate the importance of each sentence. After computing
     * ranks in this way, we multiply the components of the resulting
     * vector to slightly bias the results to favor earlier sentences in
     * the document using a Zipf-like distribution on sentence order.
     *
     * @param array $adjacency the adjacency matrix (normalized to
     *      satisfy conditions for power method to converge) generated for the
     *      sentences
     * @return array the sentence ranks
     */
    public static function getSentenceRanks($adjacency)
    {
        $n = count($adjacency);
        $sentence_ranks = array_fill(0, $n, 1.0 / $n);
        for ($i = 0; $i < 10; $i++ ) {
            $sentence_ranks = LinearAlgebra::multiply($adjacency,
                $sentence_ranks);
        }
        // bias start of doc according to a zipf like weighting
        foreach ($sentence_ranks as $index => $weight) {
            $weight *= 1.0/(1.0 + pow($index, 0.4));
            $sentence_ranks[$index] = $weight;
        }
        arsort($sentence_ranks);
        return $sentence_ranks;
    }
    /**
     * Compute the adjacency matrix based on its distortion measure
     * @param array $tf_per_sentence_normalized the array of term frequencies
     * @return array the array of sentence adjacency
     */
    public static function computeAdjacency($tf_per_sentence_normalized)
    {
        $result = [[]];
        $n = count($tf_per_sentence_normalized);
        for ($i = 0; $i < $n; $i++ ) {
            $result[$i][$i] = 0;
            for ($j = $i + 1; $j < $n; $j++ ) {
                $result[$i][$j] = LinearAlgebra::distortion(
                    $tf_per_sentence_normalized[$i],
                    $tf_per_sentence_normalized[$j]);
                $result[$j][$i] = $result[$i][$j];
            }
        }
        return $result;
    }
}
