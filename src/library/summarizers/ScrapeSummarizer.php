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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing.
 *
 * @author Charles Bocage charles.bocage@sjsu.edu
 *  rewritten Chris Pollett chris@pollett.org
 */
class ScrapeSummarizer extends Summarizer
{
    /**
     * Scrapes the web document for important tags to make a summary
     *
     * @param object $dom   a document object to extract a description from.
     * @param string $page original page string to extract description from
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     *
     * @return array a a triple (string summary, array word cloud, array
     *      of position => scores for positions within the summary)
     */
    public static function getSummary($dom, $page, $lang)
    {
        $xpath = new \DOMXPath($dom);
        $metas = $xpath->evaluate("/html//meta");
        $max_summary_len = PageProcessor::$max_description_len;
        $block = "";
        $ellipsis = "";
        //look for a meta tag with a description
        foreach ($metas as $meta) {
            if (stristr($meta->getAttribute('name'), "description") &&
                trim($meta->getAttribute('content')) != "") {
                $block .= $ellipsis  . $meta->getAttribute('content');
                $ellipsis = " .. ";
            }
        }
        $block = trim($block);
        $pos = 0;
        $blocks = [];
        $block_ranks = [];
        if ($block != "") {
            $blocks[] = $block;
            $block_ranks[] = 3. * log(strlen($block) + 1);
        }
        $tag_weights = [ "h1" => 3., "h2" => 3., "pre" => 0.5, "li" => 0.5];
        $max_score = max($tag_weights) * strlen($page);
        $changeable_index = 0;
        $fixed_summary_len = 0;
        while($pos >= 0) {
            $old_pos = $pos;
            list($pos, $block, $tag_name) = self::offsetContentsNextTag(
                "h\d|div|pr?e?|th|td|li|dt|dd|article|section|cite", $page,
                $old_pos, true);
            $block = trim(strip_tags($block));
            $score_pos = pow(1 + $pos, 0.5);
            if (!empty($block)) {
                $sentences = self::getSentences($block);
                $weight = (empty($tag_weights[$tag_name])) ? 1.0 :
                    $tag_weights[$tag_name];
                foreach ($sentences as $sentence) {
                    $blocks[] = $sentence;
                    $block_ranks[] = ($weight * log(strlen($sentence) + 1)) /
                        $score_pos;
                }
            }
            if ($pos > 0 && !empty($block_ranks[$changeable_index]) &&
                $max_score/$score_pos < $block_ranks[$changeable_index]) {
                $fixed_summary_len += strlen($blocks[$changeable_index]);
                $changeable_index++;
                if ($fixed_summary_len > $max_summary_len) {
                    break;
                }
            }
        }
        arsort($block_ranks);
        list($summary, $summary_scores) = self::getSummaryFromSentenceScores(
            $block_ranks, $blocks, $lang);
        return [$summary, self::wordCloudFromSummary($summary,  $lang),
            $summary_scores];
    }
    /**
     * Return the contents of the next tag in $page after $offset which
     * matches $tag_regex. I.e, if an open tag foo matches $tag_regex,
     * then the nearest closing tag /foo is found after the open tag
     * and then in between contents is returned. If $lazy is true,
     * then if foo matches $tag_regex, then the nearest closing tag that
     * matches $tag_regex is found. This may or may not be foo. For example
     * $tag_regex might foo|goo. If a /goo was found before a /foo, then
     * the latest open tag for goo after offset and before /goo is searched for,
     * and the contents between this open an close goo are returned.
     *
     * @param string $tag_regex a regex string that matches for xml tag names
     *    not including < and >. I.e., the pattern p would match for a <p> tag
     *    p|h\d would match <p>,<h1>,<h2>, etc.
     * @param string $page string we are searching for tag contents
     * @param int $offset where to start searching from in $page
     * @param boolean $lazy whether to be greedy or lazy in the contents
     *      we return in the sense descriebd above.
     */
    public static function offsetContentsNextTag($tag_regex, $page,
        $offset = -1, $lazy = false)
    {
        list($pos, $start_tag) = L\preg_search(
            "/\<(" . $tag_regex . ")[^\>]*\>/u", $page, $offset, true);
        if ($pos == -1) {
            return [-1, "", $tag_regex];
        }
        $start_contents_pos = $pos + strlen($start_tag);
        if($lazy) {
            list($end_tag_pos, $end_tag) = L\preg_search(
                "/\<\/(" . $tag_regex . ")[^\>]*\>/", $page,
                $start_contents_pos, true);
            if ($end_tag_pos == -1) {
                return [-1, "", $tag_regex];
            }
            $tag_name_end_pos = L\preg_search("/\s|\>/", $end_tag, 2);
            if ($tag_name_end_pos == -1) {
                return [-1, ""];
            }
            $tag_name = substr($end_tag, 2, $tag_name_end_pos - 2);
            $at_least_once = false;
            $start_tag = "";
            $old_pos = $pos;
            while ($pos < $end_tag_pos && $pos > -1 && $pos != $old_pos) {
                $old_pos = $pos;
                $old_start_tag = $start_tag;
                list($pos, $start_tag) = L\preg_search(
                    "/\<(" . $tag_name . ")[^\>]*\>/", $page, $pos, true);
                $at_least_once = true;
            }
            if ($pos == -1 && !$at_least_once) {
                return [-1, "", $tag_regex];
            } else if (!$at_least_once) {
                $old_pos = $pos;
                $old_start_tag = $start_tag;
            }
            $start_contents_pos = $old_pos + strlen($old_start_tag);
        } else {
            $tag_name_end_pos = L\preg_search("/\s|\>/", $start_tag, 1);
            if ($tag_name_end_pos == -1) {
                return [-1, ""];
            }
            $tag_name = substr($start_tag, 1, $tag_name_end_pos - 1);
            list($end_tag_pos, $end_tag) = L\preg_search("/\<\/(" . $tag_name .
                ")[^\>]*\>/", $page, $start_contents_pos, true);
        }
        $contents = ($end_tag_pos == -1) ? substr($page, $start_contents_pos) :
            substr($page, $start_contents_pos,
                $end_tag_pos - $start_contents_pos);
        $end_pos = ($end_tag_pos == -1) ? -1 : $end_tag_pos + strlen($end_tag);
        return [$end_pos, $contents, $tag_name];
    }
}
