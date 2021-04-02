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
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\summarizers\CentroidSummarizer;
use seekquarry\yioop\library\summarizers\GraphBasedSummarizer;
use seekquarry\yioop\library\summarizers\ScrapeSummarizer;
use seekquarry\yioop\library\summarizers\CentroidWeightedSummarizer;

/**
 * To try to guess locale's from string samples
 */
require_once __DIR__."/../LocaleFunctions.php";
/**
 * Parent class common to all processors used to create crawl summary
 * information  that involves basically text data
 *
 * @author Chris Pollett
 */
class TextProcessor extends PageProcessor
{
    /**
     * Set-ups the any indexing plugins associated with this page
     * processor
     *
     * @param array $plugins an array of indexing plugins which might
     *     do further processing on the data handles by this page
     *     processor
     * @param int $max_description_len maximal length of a page summary
     * @param int $max_links_to_extract maximum number of links to extract
     *      from a single document
     * @param string $summarizer_option CRAWL_CONSTANT specifying what kind
     *      of summarizer to use self::BASIC_SUMMARIZER,
     *      self::GRAPH_BASED_SUMMARIZER and self::CENTROID_SUMMARIZER
     *      self::CENTROID_WEIGHTED_SUMMARIZER
     */
    public function __construct($plugins = [], $max_description_len = null,
        $max_links_to_extract = null,
        $summarizer_option = self::BASIC_SUMMARIZER)
    {
        parent::__construct($plugins, $max_description_len,
            $max_links_to_extract, $summarizer_option);
        /** Register File Types We Handle*/
        $add_extensions = ["csv", "tab", "tsv", "txt"];
        self::$indexed_file_types = array_merge(self::$indexed_file_types,
            $add_extensions);
        self::$mime_processor["text/plain"] = "TextProcessor";
        self::$mime_processor["text/csv"] = "TextProcessor";
        self::$mime_processor["text/x-java-source"] = "TextProcessor";
        self::$mime_processor["text/tab-separated-values"] = "TextProcessor";
    }
    /**
     * Computes a summary based on a text string of a document
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by
     *     TextProcessor at this point. Some of its subclasses override
     *     this method and use url to produce complete links for
     *     relative links within a document
     *
     * @return array a summary of (title, description,links, and content) of
     *     the information in $page
     */
    public function process($page, $url)
    {
        $summary = null;
        if (is_string($page)) {
            $remove_styles_page = preg_replace(
                '@<style[^>]*?>.*?</style>@si', ' ', $page);
            $dom = self::dom($remove_styles_page);
            $summary[self::TITLE] = "";
            $summary[self::LANG] = self::calculateLang($remove_styles_page);
            $summary[self::PAGE] = "<html><body><div><pre>" .
                strip_tags($remove_styles_page) . "</pre></div></body></html>";
            list($summary[self::DESCRIPTION], $summary[self::WORD_CLOUD],
                $summary[self::DESCRIPTION_SCORES]) =
                $this->summarizer->getSummary($dom, $summary[self::PAGE],
                    $summary[self::LANG]);
            $summary[self::LINKS] = self::extractHttpHttpsUrls(
                $remove_styles_page);
        }
        return $summary;
    }
    /**
     * Tries to determine the language of the document by looking at the
     * $sample_text and $url provided
     * the language
     * @param string $sample_text sample text to try guess the language from
     * @param string $url url of web-page as a fallback look at the country
     *     to figure out language
     *
     * @return string language tag for guessed language
     */
    public static function calculateLang($sample_text = null, $url = null)
    {
        if ($url != null) {
            $lang = UrlParser::getLang($url);
            if ($lang && !in_array($lang, ["en", "en-US"])) {
                return $lang;
            }
        }
        if ($sample_text != null) {
            $lang = L\guessLocaleFromString($sample_text);
        } else {
            $lang = null;
        }
        return $lang;
    }
    /**
     * Gets the text between two tags in a document starting at the current
     * position.
     *
     * @param string $string document to extract text from
     * @param int $cur_pos current location to look if can extract text
     * @param string $start_tag starting tag that we want to extract after
     * @param string $end_tag ending tag that we want to extract until
     * @return array pair consisting of when in the document we are after
     *     the end tag, together with the data between the two tags
     */
    public static function getBetweenTags($string, $cur_pos, $start_tag,
        $end_tag)
    {
        $len = strlen($string);
        if (($between_start = strpos($string, $start_tag, $cur_pos)) ===
            false ) {
            return [$len, ""];
        }
        $between_start  += strlen($start_tag);
        if (($between_end = strpos($string, $end_tag, $between_start)) ===
            false ) {
            $between_end = $len;
        }
        $cur_pos = $between_end + strlen($end_tag);
        $between_string = substr($string, $between_start,
            $between_end - $between_start);
        return [$cur_pos, $between_string];
    }
    /**
     * Tries to extract http or https links from a string of text.
     * Does this by a very approximate regular expression.
     *
     * @param string $page text string of a document
     * @return array a set of http or https links that were extracted from
     *     the document
     */
    public static function extractHttpHttpsUrls($page)
    {
        $pattern =
            '@((http|https)://([^ \t\r\n\v\f\'\"\;\,<>\{\}])*)@i';
        $sites = [];
        preg_match_all($pattern, $page, $matches);
        $i = 0;
        foreach ($matches[0] as $url) {
            if (!isset($sites[$url]) && strlen($url) < C\MAX_URL_LEN &&
                strlen($url) > 4) {
                $sites[$url] = UrlParser::extractTextFromUrl($url);
                $i++;
                if (self::$max_links_to_extract > 0 &&
                    $i >= self::$max_links_to_extract) {
                    break;
                }
            }
        }
        return $sites;
    }
    /**
     * If an end of file is reached before closed tags are seen, this methods
     * closes these tags in the correct order.
     *
     * @param string &$page a reference to an xml or html document
     */
    public static function closeDanglingTags(&$page)
    {
        $l_pos = strrpos($page, "<");
        $g_pos = strrpos($page, ">");
        if ($g_pos && $l_pos > $g_pos) {
            $page = substr($page, 0, $l_pos);
        }
        // put all opened tags into an array
        preg_match_all("#<([a-z]+)( .*)?(?!/)>#iU", $page, $result);
        $openedtags = $result[1];

        // put all closed tags into an array
        preg_match_all("#</([a-z]+)>#iU", $page, $result);
        $closedtags=$result[1];
        $len_opened = count($openedtags);
        // all tags are closed
        if (count($closedtags) == $len_opened){
            return;
        }
        $openedtags = array_reverse($openedtags);
        // close tags
        for ($i=0;$i < $len_opened;$i++) {
            if (!in_array($openedtags[$i],$closedtags)){
              $page .= '</'.$openedtags[$i].'>';
            } else {
              unset($closedtags[array_search($openedtags[$i],$closedtags)]);
            }
        }
    }
    /**
     * Return a document object based on a string containing the contents of
     * a web page
     *
     * @param string $page   a web page
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        /*
             first do a crude check to see if we have at least an <html> tag
             otherwise try to make a simplified html document from what we got
         */
        if (stristr($page, "<html") === false) {
            $head_tags = "<title><meta><base>";
            $head = strip_tags($page, $head_tags);
            $body_tags = "<frameset><frame><noscript><img><span><b><i><em>".
                "<strong><h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th><dt><dir><dl><dd>";
            $body = "\n\n" . strip_tags($page, $body_tags). "\n\n";
            $body = preg_replace("/\n\n(.+)\n\n/s", '<div>\n$1\n</div>\n',
                $body);
            $page = "<html><head>$head</head><body>$body</body></html>";
        }
        $dom = L\getDomFromString($page);
        return $dom;
    }
}
