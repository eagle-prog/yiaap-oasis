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
use seekquarry\yioop\library\LinearAlgebra;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * Base class for all summarizers. Summarizers chief method is
 * getSummary which is supposed to take a text or XML
 * document and produces a summary of that document up to
 * PageProcessor::$max_description_len many characters. Summarizers
 * also contain various methods to generate word cloud from such a summary
 * @see Summarizer::wordCloudFromSummary and/or document centroids
 * wordCloudFromTermVector.
 *
 * @author Charles Bocage charles.bocage@sjsu.edu
 *   Chris Pollett chris@pollett.org
 *
 */
class Summarizer
{
    /**
     * Number of distinct terms to use in generating summary
     */
    const MAX_DISTINCT_TERMS = 1000;
    /**
     * Number of nonzero centroid components
     */
    const CENTROID_COMPONENTS = 1000;
    /**
     * Number of words in word cloud
     */
    const WORD_CLOUD_LEN = 5;
    /**
     * Compute a summary, word cloud, and scores for text ranges within
     *  the summary of a document in a given language
     *
     * @param object $dom document object model used to locate items for
     *      summary
     * @param string $page raw document sentences should be extracted from
     * @param string $lang locale tag for language the summary is in
     * @return array [$summary, $word_cloud, $summary_scores]
     */
    public static function getSummary($dom, $page, $lang) {
        throw \Exception("Not defined");
    }
    /**
     * Breaks any content into sentences with and without punctuation
     * @param object $dom a document object to extract a description from.
     * @param string $content complete page.
     * @param string $lang local tag of the language for data being
     *  processed
     * @return array array [sentences_with_punctuation,
     *  sentences_with_punctuation_stripped]
     */
    public static function getPunctuatedUnpunctuatedSentences($dom, $content,
        $lang)
    {
        $xpath = new \DOMXPath($dom);
        $metas = $xpath->evaluate("/html//meta");
        $description = "";
        $output_file_contents = "";
        //look for a meta tag with a description
        foreach ($metas as $meta) {
            if (stristr($meta->getAttribute('name'), "description")) {
                $description .= " .. " . $meta->getAttribute('content');
            }
        }
        $content = $description . " ". self::pageProcessing($content);
        $content = preg_replace("/\[\d+\]/u", " ", $content);
        $sentences_with_punctuation = self::getSentences($content);
        $stop_obj = PhraseParser::getTokenizer($lang);
        $sentences = self::removeStopWords($sentences_with_punctuation,
            $stop_obj);
        $sentences = self::removePunctuation($sentences);
        return [$sentences_with_punctuation, $sentences];
    }
    /**
     * Breaks any content into sentences by splitting it on spaces or carriage
     *  returns
     * @param string $content complete page.
     * @return array array of sentences from that content.
     */
    public static function getSentences($content)
    {
        $content = preg_replace([ "/\n+(\.| |\t)+/u",
            "/((\p{L}|\p{N}|\)|\}|\]){5,}\s?(\.|\|।|\!|\?|！|？|。))\s+/u",
            "/।/u", "/(\n|\r)(\n|\r)+/", "/।./u"], ["\n", "$1.\n", "।\n\n",
            "..\n", "।"], $content);
        $lines = preg_split('/\.\n/u', $content, 0, PREG_SPLIT_NO_EMPTY);
        $lines = preg_replace("/\s+/u", " ", $lines);
        return $lines;
    }
    /**
     * Formats the sentences to remove all characters except words,
     *   digits and spaces
     * @param string $sentence complete page.
     * @return string formatted sentences.
     */
    public static function formatSentence($sentence)
    {
        $sentence = trim(preg_replace('/[^\p{L}\p{N}\s]+/u',
            ' ', mb_strtolower($sentence)));
        return $sentence;
    }
    /**
     * Formats the document to remove carriage returns, hyphens and digits
     * as we will not be using digits in word cloud.
     * The formatted document generated by this function is only used to
     * compute centroid.
     * @param string $content formatted page.
     * @return string formatted document.
     */
    public static function formatDoc($content)
    {
        $substitute = ['/[\n\r\-]+/', '/[^\p{L}\s\.]+/u', '/\.+/'];
        $content = preg_replace($substitute, ' ', $content);
        return $content;
    }
    /**
     * This function does an additional processing on the page
     * such as removing all the tags from the page
     * @param string $page complete page.
     * @return string processed page.
     */
    public static function pageProcessing($page)
    {
        $substitutions = ['@<script[^>]*?>.*?</script>@si',
            '/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
            '@<style[^>]*?>.*?</style>@si', '/\t\n/', '/\s{2,}/'
        ];
        $page = preg_replace($substitutions, ' ', $page);
        $new_page = preg_replace("/\<br\s*(\/)?\s*\>/u", "\n", $page);
        $changed = false;
        if ($new_page != $page) {
            $changed = true;
            $page = $new_page;
        }
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/iu", "\n\n", $page);
        $page = preg_replace("/\<a/iu", " <a", $page);
        $page = html_entity_decode($page);
        $page = preg_replace("/\</u", " <", $page);
        $page = strip_tags($page);
        if ($changed) {
            $page = preg_replace("/(\r?\n[\t| ]*){2}/u", "\n", $page);
        }
        $page = preg_replace("/(\r?\n[\t| ]*)/u", "\n", $page);
        $page = preg_replace("/\n\n\n+/u", "\n\n", $page);
        return $page;
    }
    /**
     * Returns a new array of sentences without the stop words
     * @param array $sentences the array of sentences to process
     * @param object $stop_obj the class that has the stopworedRemover method
     * @return array a new array of sentences without the stop words
     */
    public static function removeStopWords($sentences, $stop_obj)
    {
        if ($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
            $results = $stop_obj->stopwordsRemover($sentences);
        } else {
            $results = $sentences;
        }
        return $results;
    }
    /**
     * Remove punctuation from an array of sentences
     * @param array $sentences the sentences in the doc
     * @return array the array of sentences with the punctuation removed
     */
     public static function removePunctuation($sentences)
     {
        if (is_array($sentences)) {
            foreach ($sentences as $key => $sentence) {
                $sentences[$key] = trim(preg_replace('/[' . C\PUNCT . ']+/iu',
                    ' ', $sentence));
            }
        }
        return $sentences;
     }
    /**
     * Get up to the top self::MAX_DISTINCT_TERMS terms from an array of
     * sentences  in order of term frequency.
     * @param array $sentences the sentences in the doc
     * @param string $lang locale tag for stemming
     * @return array an array of terms in the array of sentences
     */
    public static function getTermsFromSentences($sentences, $lang)
    {
        $terms = [];
        foreach ($sentences as $sentence) {
            $terms = array_merge($terms,
                PhraseParser::segmentSegment($sentence, $lang));
        }
        $terms = array_filter($terms);
        $terms_counts = array_count_values($terms);
        arsort($terms_counts);
        $terms_counts = array_slice($terms_counts, 0, self::MAX_DISTINCT_TERMS);
        //top self::MAX_DISTINCT_TERMS terms in descending order
        $terms = array_unique(array_keys($terms_counts));
        return $terms;
    }
    /**
     * Splits sentences into terms and returns [array of terms,
     *  array normalized term frequencies]
     * @param array $sentences the array of sentences to process
     * @param string $lang the current locale
     * @return array an array with [array of terms,
     *  array normalized term frequencies] pairs
     */
    public static function computeTermFrequenciesPerSentence($sentences,
        $lang)
    {
        $tf_per_sentence_normalized = [];
        $terms = [];
        foreach ($sentences as $sentence) {
            $sentence_terms = PhraseParser::segmentSegment($sentence, $lang);
            $tf_current_sentence =
                self::getTermFrequencies($sentence_terms, $sentence);
            $tf_per_sentence_normalized[] =
                LinearAlgebra::normalize($tf_current_sentence);
        }
        return [$terms, $tf_per_sentence_normalized];
    }
    /**
     * Calculates an array with key terms and values their frequencies
     * based on a supplied sentence or sentences
     *
     * @param array $terms the list of all terms in the doc
     * @param mixed $sentence_or_sentences either a single string sentence
     *  or an array of sentences
     * @return array sequence of term => frequency pairs
     */
    public static function getTermFrequencies($terms, $sentence_or_sentences)
    {
        $t = count($terms);
        $nk = array_fill(0, $t, 0);
        $sentences =  (is_array($sentence_or_sentences)) ?
            $sentence_or_sentences : [$sentence_or_sentences];
        foreach ($sentences as $sentence) {
            for ($j = 0; $j < $t; $j++) {
                $nk[$j] += preg_match_all("/\b" . preg_quote($terms[$j], '/') .
                    "\b/iu", $sentence);
            }
        }
        return array_combine($terms, $nk);
    }
    /**
     * Generates an array of most important words from a string $summary.
     * Currently, the algorithm is a based on terms frequencies after
     * stopwords removed
     *
     * @param string $summary text to derive most important words of
     * @param string $lang locale tag for language of $summary
     * @param array $term_frequencies a supplied list of terms and frequencies
     *  for words in summary. If null then these will be computed.
     * @return array the top self::WORD_CLOUD_LEN most important terms in
     *  $summary
     */
    public static function wordCloudFromSummary($summary, $lang,
        $term_frequencies = null)
    {
        if ($term_frequencies == null) {
            $stop_obj = PhraseParser::getTokenizer($lang);
            if ($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
                $summary = $stop_obj->stopwordsRemover($summary);
            }
            $summary = mb_strtolower($summary);
            $terms = PhraseParser::segmentSegment($summary, $lang);
            $term_frequencies = self::getTermFrequencies($terms, $summary);
        }
        arsort($term_frequencies);
        $top = array_slice($term_frequencies, 0 , self::WORD_CLOUD_LEN);
        return array_keys($top);
    }
    /**
     * Given a sorted term vector for a document computes a word cloud of the
     * most important self::WORD_CLOUD_LEN many terms
     *
     * @param array $term_vector if $terms is false then centroid is expected
     *  a sequence of pairs term => weight, otherwise,
     *  if $terms is an array of terms, then $term_vector should be
     *  a sequence of term_index=>weight pairs.
     * @param mixed $terms if not false, then should be an array of terms,
     *  at a minimum having all the indices of $term_vector
     * @return array the top self::WORD_CLOUD_LEN most important terms in
     *  $summary
     */
    public static function wordCloudFromTermVector($term_vector, $terms = false)
    {
        arsort($term_vector);
        $i = 0;
        $word_cloud = [];
        foreach ($term_vector as $term_index => $value) {
            if ($i >= self::WORD_CLOUD_LEN) {
                break;
            }
            $word_cloud[$i] = (empty($terms)) ? $term_index :
                $terms[$term_index];
            $i++;
        }
        return $word_cloud;
    }
    /**
     * Given a score-sorted array of sentence index => score pairs and
     * and a set of sentences, outputs a summary of up to a
     * PageProcessor::$max_description_len based on the highest scored sentences
     * concatenated in the order they appeared in the original document.
     *
     * @param array $sentence_scores an array sorted by score of
     *      sentence_index => score pairs.
     * @param array $sentences the array of sentences corresponding to sentence
     *      $sentence_scores indices
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     * @return string a string that represents the summary
     */
    public static function getSummaryFromSentenceScores(
            $sentence_scores, $sentences, $lang)
    {
        $summary = "";
        $summary_length = 0;
        $top = self::numSentencesForSummary($sentence_scores, $sentences);
        if ($top < 1) {
            if (!empty($sentences[0])) {
                $summary = substr($sentences[0], 0,
                    PageProcessor::$max_description_len);
                return [ltrim($summary), [1]];
            }
        }
        $summary_indices = array_keys(array_slice($sentence_scores, 0,
            $top, true));
        sort($summary_indices);
        $eos = ($lang == 'hi') ? "।" : "."; //default end of sentence symbol
        $summary_scores = [];
        $score_pos = 1; /* Starting offset in docs always 1 not 0 so works with
            modified9 encoding/decoding
         */
        foreach ($summary_indices as $index) {
            $sentence = PhraseParser::compressSentence($sentences[$index],
                $lang);
            if ($summary_length + strlen($sentence) >
                PageProcessor::$max_description_len) {
                break;
            } else {
                $summary_length += strlen($sentence);
                $summary .= " " . rtrim($sentence, $eos) . "$eos ";
                $summary_scores[$score_pos] = $sentence_scores[$index];
                $score_pos += str_word_count($sentence);
            }
        }
        $summary_scores = LinearAlgebra::normalize($summary_scores);
        // Want to make sure all entries nonzero so smooth
        $summary_scores = LinearAlgebra::multiply(1 - 0.001, $summary_scores);
        $summary_scores = LinearAlgebra::add(0.001, $summary_scores);
        /* Final score pos used to determine number of words in summary
           when later decode.
         */
        $summary_scores[$score_pos] = 0;
        return [ltrim($summary), $summary_scores];
    }
    /**
     * Calculates how many sentences to put in the summary to match the
     * MAX_DESCRIPTION_LEN.
     *
     * @param array $sentence_scores associative array of
     *  sentence-number-in-doc => similarity score to centroid
     *  (sorted from highest to lowest score).
     * @param array $sentences sentences in doc in their original order
     * @return int number of sentences
     */
    public static function numSentencesForSummary($sentence_scores, $sentences)
    {
        $top = 0;
        $length = 0;
        foreach ($sentence_scores as $sentence_index => $score)
        {
            if ($length < PageProcessor::$max_description_len) {
                $length += strlen($sentences[$sentence_index]);
                $top++;
            }
        }
        return $top;
    }
}
