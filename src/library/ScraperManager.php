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
 * @author Charles Bocage (charles.bocage@sjsu.edu)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Class used by html processors to detect if a page matches a particular
 * signature such as that of a content management system, and
 * also to provide scraping mechanisms for the content of such a page
 *
 * @author Charles Bocage (charles.bocage@sjsu.edu) updated to support
 *      scraper priorities and extract fields Chris Pollett
 */
class ScraperManager
{
    /**
     * Method used to check a page against a supplied list of scrapers
     * for a matching signature. If a match is found that scraper is returned.
     *
     * @param string $page the html page to check
     * @param array $scrapers an array of scrapers to check against
     * @return array an associative array of scraper properties if a matching
     *      scraper signature found; otherwise, the empty array
     */
    public static function getScraper($page, $scrapers)
    {
        $out_scraper = [];
        $out_priority = -1;
        foreach ($scrapers as $scraper) {
            if (empty($scraper)) {
                continue;
            }
            $signature = html_entity_decode(
                $scraper['SIGNATURE'], ENT_QUOTES);
            if (self::checkSignature($page, $signature) &&
                $scraper['PRIORITY'] > $out_priority) {
                $out_scraper = $scraper;
                $out_priority = $scraper['PRIORITY'];
            }
        }
        foreach ($out_scraper as $key => $value) {
            $out_scraper[$key] = html_entity_decode($value, ENT_QUOTES);
        }
        return $out_scraper;
    }
    /**
     * Applies scrape rules to a given page. A scrape rule consists of
     * TEXT_PATH xpath for the main content of a web page, a sequence of
     * \n separated DELETE_PATHS for what should be removed from the
     * main content as irrelevant, and finally a list EXTRACT_FIELDS
     * of additional summary fields which should be extracted from the
     * page content
     *
     * @param string $page the web page to operate on
     * @param array a scraper object to apply the rules of
     * @return string the result of extracting first xpath content and
     *  deleting from it according to the remaining xpath rules
     */
    public static function applyScraperRules($page, $scraper)
    {
        $delete_paths = explode("\n", $scraper["DELETE_PATHS"]);
        $dom = self::getContentByXquery($page, $scraper["TEXT_PATH"]);
        $summary = [];
        if (!empty($dom)) {
            foreach ($delete_paths as $tag_to_remove) {
                self::removeContentByXquery($dom, $tag_to_remove);
                if (empty($dom)) {
                    break;
                }
            }
            if (!empty($dom)) {
                $out_page = utf8SafeSaveHtml($dom);
            }
        }
        set_error_handler(null);
        $extract_fields = explode("\n", $scraper["EXTRACT_FIELDS"]);
        $results = false;
        $dom = new \DOMDocument();
        if (@$dom->loadHTML($page)) {
            foreach ($extract_fields as $extract_field) {
                if (preg_match('/^([^\=\:]+)(\:?\=)(.+)$/', $extract_field,
                    $parts)) {
                    list(, $summary_field, $assign_type, $value_or_query) =
                        $parts;
                    $summary_field = trim($summary_field);
                    if (defined(C\NS_LIB . "CrawlConstants::" .$summary_field)){
                        $summary_field = constant(
                            C\NS_LIB . "CrawlConstants::" .$summary_field);
                    }
                    $out_text = "";
                    if ($assign_type == ":=") {
                        $out_text = $value_or_query;
                    } else {
                        if ($xpath = new \DOMXpath($dom)) {
                            $results = @$xpath->query($value_or_query);
                            if (!empty($results) && $results->length > 0) {
                                $len = $results->length;
                                for ($i = 0; $i < $len; $i++) {
                                    $out_text .=
                                        $results->item($i)->textContent;
                                }
                            }
                        }
                    }
                    $summary[$summary_field] = trim($out_text);
                }
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        $out_page = empty($out_page) ? $page : $out_page;
        return [$summary, $out_page];
    }
    /**
     * If $signature begins with '/', checks to see if applying
     * the xpath in $signature to $page results
     * in a non-empty dom node list. Otherwise, does a match of the
     * regex (without matching start and end delimiters (say, /)
     * against $page and returns whether found
     *
     * @param string $page a web document to check
     * @param string $signature an xpath to check against
     * @return boolean true if the given xpath return a non empty dom node list
     */
    public static function checkSignature($page, $signature)
    {
        if ($signature[0] == '/') {
            $dom = new \DOMDocument();
            $results = false;
            set_error_handler(null);
            if (!empty($page) && @$dom->loadHTML($page)) {
                if ($xpath = new \DOMXpath($dom)) {
                    $results = $xpath->query($signature);
                }
            }
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            return !empty($results->length) && $results->length > 0;
        } else {
            return (mb_ereg($signature, $page) !== false);
        }
    }
    /**
     * Get the contents of a document via an xpath
     * @param string $page a document to apply the xpath query against
     * @param string $query the xpath query to run
     *
     * @return \DOMDocument dom of a simplified web page containing nodes
     *      matching xpath query within an html body tag.
     */
    public static function getContentByXquery($page, $query)
    {
        $out_dom = null;
        $dom = L\getDomFromString($page);
        if (!empty($dom->documentElement) && !empty($query)) {
            $xpath = new \DOMXPath($dom);
            $xpath_result = $xpath->query($query);
            if (!empty($xpath_result) && $xpath_result->length > 0) {
                $out_dom = new \DOMDocument();
                $out_dom->loadHTML("<html><body></body></html>");
                $node = $out_dom->importNode($xpath_result->item(0), true);
                $out_dom->documentElement->childNodes->item(0)->appendChild(
                    $node);
            }
        }
        return $out_dom;
    }
    /**
     * Removes from the contents of a DOMDocument the results of
     * an xpath query
     * @param \DOMDocument $dom a document to apply the xpath query against
     * @param string $query the xpath query to run
     */
    public static function removeContentByXquery($dom, $query)
    {
        if (empty($dom) || empty($query)) {
            return;
        }
        $xpath = new \DOMXPath($dom);
        $xpath_result = $xpath->query($query);
        if ($xpath_result->length > 0) {
            $len = $xpath_result->length;
            for ($i = 0; $i < $len; $i++) {
                $node = $xpath_result->item($i);
                $parent = $node->parentNode;
                if ($parent) {
                    $parent->removeChild($node);
                }
            }
        }
    }
}
