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
 * @author Mangesh Dahale mangeshadahale@gmail.com
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
 * the @see getSummmary method. getSummary does this splitting
 * the document into sentences and computing inverse sentence frequency
 * (should be ISL, but we call IDF) scores for each term. It then computes
 * an average document vector (we call centroid) with components
 * (total number of occurrences  of term) * (IDF score of term).
 * It also generates a word cloud for a document. Notice if we divided
 * this by number of documents, we would have components
 * average term frequency * IDF. As ranking by either won't affect out
 * results, we don't divide. We then compute the cosine similarity of
 * each sentence vector with this average and choose the top sentences
 * to make our summary. Here a sentence vector has components
 * term frequency in sentence * IDF score of term.
 *
 * @author Mangesh Dahale mangeshadahale@gmail.com
 */
class CentroidSummarizer extends Summarizer
{
    /**
     * Generates a summary, word cloud, and sentence scoring for a provides
     * web page. To do this the page is split into sentences and inverse
     * sentence frequency (should be ISL, but we call IDF) scores for each term
     * term are computed. Then an average document vector (we call centroid)
     * with components
     * (total number of occurrences  of term) * (IDF score of term)
     * is found. We then compute the cosine similarity of
     * each sentence vector with this average and choose the top sentences
     * to make our summary. Here a sentence vector has components
     * term frequency in sentence * IDF score of term.
     *
     * @param object $dom document object model of page to summarize
     * @param string $page complete raw page to generate the summary from.
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     * @return array a triple (string summary, array word cloud, array
     *      of position => scores for positions within the summary)
     */
    public static function getSummary($dom, $page, $lang)
    {
        list($original_sentences, $sentences) =
            self::getPunctuatedUnpunctuatedSentences($dom, $page, $lang);
        $terms = self::getTermsFromSentences($sentences, $lang);
        $num_sentences = count($original_sentences);
        $formatted_doc = self::formatDoc($page);
        list($centroid, $idf) = self::computeCentroidIdfFromSentences($terms,
            $sentences, $formatted_doc, $lang);
        $word_cloud = self::wordCloudFromTermVector($centroid, $terms);
        $sorted_sentence_scores = self::scoreSentencesVersusPageTerms(
            $sentences, $centroid, $idf, $terms);
        list($summary, $summary_scores) = self::getSummaryFromSentenceScores(
            $sorted_sentence_scores, $original_sentences, $lang);
        return [$summary, $word_cloud, $summary_scores];
    }
    /**
     * Computes a number of occurrences of term * inverse sentence frequency
     * vector over  all terms in the document as well as inverse sentence
     * frequencies for each term in a document.
     * @param array $terms distinct terms in a document
     * @param array $sentences sentences of a document
     * @param string $formatted_doc original document with some punctuation
     *      removed
     * @param string $lang locale tag for document
     * @return array [truncated to maximal self::CENTROID_COMPONENTS
     *      number of occurrences of term * inverse sentence frequency
     *      vector, array of inverse sentence frequencies for each term
     *      in document]
     */
    public static function computeCentroidIdfFromSentences($terms,
        $sentences, $formatted_doc, $lang)
    {
        $num_sentences = count($sentences);
        $num_terms = count($terms);
        if ($num_terms == 0) {
            return [[], [], 0];
        }
        /* Initialize Nk [Number of sentences the term occurs] */
        $nk = [];
        $nk = array_fill(0, $num_terms, 0);
        for ($j = 0; $j < $num_terms; $j++) {
            for ($i = 0; $i < $num_sentences; $i++) {
                if (is_string($terms[$j]) &&
                    strpos($sentences[$i], $terms[$j]) !== false) {
                    $nk[$j]++;
                }
            }
        }
        /* Calculate IDF (inverse document frequency) score for each term
         */
        $idf = [];
        for ($k = 0; $k < $num_terms; $k++) {
            $idf[$k] = ($nk[$k] == 0) ? 0 : log($num_sentences / $nk[$k]);
        }
        /* Count TF for finding centroid */
        $b = "\b"; //term break character
        if (in_array($lang, ["zh-CN", "ja", "ko"])) {
            $b = ""; // some asian languages don't use
        }
        set_error_handler(null);
        // Calculate term frequency whole doc (nt) * IDF (sentence) scores
        $ntidf = [];
        for ($j = 0; $j < $num_terms; $j++) {
            $quoted = preg_quote($terms[$j], "/");
            $nt = @preg_match_all("/$b(" . $quoted . ")$b/ui", $formatted_doc,
                $matches); //$matches included for backwards compatibility
            $ntidf[$j] = $nt * $idf[$j];
            if (is_nan($ntidf[$j]) || is_infinite($ntidf[$j])) {
                $ntidf[$j] = 0;
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        /* Calculate centroid */
        arsort($ntidf);
        /* pick top self::CENTROID_COMPONENTS components of the ntidf vector
           as centroid preserving term_index => value association
         */
        $centroid = array_slice($ntidf, 0, self::CENTROID_COMPONENTS, true);
        return [$centroid, $idf];
    }
    /**
     * Calculates scores for an array of sentences using normalized
     * tf-idf score vector of sentence  dot centroid vector.
     *
     * @param array $sentences unpunctated sentences from a source in the
     *  order they originally appeared in the source
     * @param array $centroid an array of term_index => nt *idf scores for that
     *  term. Here nt number of times term appear in whole document
     *  idf is inverse document frequency for that term amongst the
     *  sentences
     * @param array $idf array of pairs of form term_index =>
     *  inverse document frequencies of term amongst sentences
     * @param array $terms an array of terms from the sentences that
     *  term_indexes mentioned above index into
     * @return array scores for each sentence
     */
    public static function scoreSentencesVersusPageTerms($sentences,
        $centroid, $idf, $terms)
    {
        $centroid_norm = LinearAlgebra::length($centroid);
        /* Calculate similarity measure between centroid and each sentence */
        $num_terms = count($terms);
        $sentence_scores = [];
        foreach ($sentences as $sentence) {
            $sentence_tfidf_dot_centroid = 0;
            $sentence_tfidf_norm_square = 0;
            foreach($centroid as $k => $ntidf_k) {
                $idf_k = $idf[$k];
                //term frequency of term k in current sentence
                $tf_k = substr_count($sentence, $terms[$k]);
                // TFIDF score of term k in current centence
                $tfidf_k = ($tf_k > 0) ?
                    (1 + log($tf_k)) * $idf_k : 0;
                $sentence_tfidf_dot_centroid += ($tfidf_k * $ntidf_k);
                $sentence_tfidf_norm_square += ($tfidf_k * $tfidf_k);
            }
            $normalization = sqrt($sentence_tfidf_norm_square) * $centroid_norm;
            $sentence_scores[] = ($normalization == 0) ? 0 :
                $sentence_tfidf_dot_centroid / $normalization;
        }
        return $sentence_scores;
    }
}
