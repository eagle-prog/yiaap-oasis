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
use seekquarry\yioop\library\CrawlConstants;

/**
 * Used to create crawl summary information
 * for RSS or Atom files
 *
 * @author Chris Pollett
 */
class RssProcessor extends TextProcessor
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
     *      self::CENTROID_SUMMARIZER
     */
    public function __construct($plugins = [], $max_description_len = null,
        $max_links_to_extract = null,
        $summarizer_option = self::BASIC_SUMMARIZER)
    {
        parent::__construct($plugins, $max_description_len,
            $max_links_to_extract, $summarizer_option);
        /** Register File Types We Handle*/
        self::$indexed_file_types[] = "rss";
        self::$mime_processor["application/rss+xml"] = "RssProcessor";
        self::$mime_processor["application/atom+xml"] = "RssProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of rss or atom news feed data.
     *
     * @param string $page   web-page contents
     * @param string $url   the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $summary = null;
        if (is_string($page)) {
            $old_page = $page;
            $page = preg_replace('@<(/?)(\w+\s*)\:@u', '<$1',
                $old_page);
            if (empty($page)) {
                $page = $old_page;
            }
            $page = preg_replace("@<link@", "<slink", $page);
            $page = preg_replace("@</link@", "</slink", $page);
            $page = preg_replace("@pubDate@i", "pubdate", $page);
            $page = preg_replace("@&lt;@", "<", $page);
            $page = preg_replace("@&gt;@", ">", $page);
            $page = preg_replace("@<!\[CDATA\[(.+?)\]\]>@s", '$1', $page);
            $dom = self::dom($page);
            $atom = false;
            $feed_nodes = $dom->getElementsByTagName('feed');
            if ($feed_nodes->length > 0) {
                $atom = true;
            }
            if ($dom !==false) {
                $summary[self::TITLE] = self::title($dom, $atom);
                $summary[self::DESCRIPTION] = self::description($dom, $atom);
                $summary[self::LANG] = self::lang($dom,
                    $summary[self::DESCRIPTION]);
                $summary[self::LINKS] = self::links($dom, $url, $atom);
                if (strlen($summary[self::DESCRIPTION] . $summary[self::TITLE])
                    == 0 && count($summary[self::LINKS]) == 0) {
                    //maybe not rss or atom? treat as text still try to get urls
                    $summary = parent::process($page, $url);
                } else {
                    $summary[self::IS_FEED] = true;
                }
            }
        }
        return $summary;
    }
    /**
     * Determines the language of the rss document by looking at the channel
     * language tag
     *
     * @param object $dom - a document object to check the language of
     * @param string $sample_text sample text to try guess the language from
     * @param string $url guess lang from url as fallback
     * @param bool $atom where this is an atom feed or just rss
     *
     * @return string language tag for guessed language
     */
    public static function lang($dom, $sample_text = null, $url = null,
        $atom = false)
    {
        $xpath = new \DOMXPath($dom);
        $languages = $xpath->evaluate("/rss/channel/language");
        if ($languages && is_object($languages) &&
            is_object($languages->item(0))) {
            return $languages->item(0)->textContent;
        } else {
            $lang = self::calculateLang($sample_text, $url);
        }
        return $lang;
    }
    /**
     * Return a document object based on a string containing the contents of
     * an RSS page
     *
     * @param string $page   a web page
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        return L\getDomFromString($page);
    }
    /**
     * Returns html head title of a webpage based on its document object
     *
     * @param object $dom   a document object to extract a title from.
     * @param bool $atom if the feed is atom or rss
     * @return string  a title of the page
     *
     */
    public static function title($dom, $atom = false)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        if ($atom){
            $xpath->registerNamespace('atom', "http://www.w3.org/2005/Atom");
        }
        $title_query = ($atom) ? "/feed/title|/atom:feed/atom:title" :
            "/rss/channel/title";
        $titles = $xpath->evaluate($title_query);
        $title = "";
        foreach ($titles as $pre_title) {
            $title .= " ". $pre_title->textContent;
        }
        return $title;
    }
    /**
     * Returns descriptive text concerning a webpage based on its document
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @param bool $atom if the feed is atom or rss
     * @return string a description of the page
     */
    public static function description($dom, $atom = false)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        $description = "";
        /*
          concatenate the contents of these dom elements up to
          the limit of description length
        */
        $page_parts = ["/rss/channel/description",
            "/rss/channel/category",
            "/rss/channel/lastBuildDate",
            "/rss/channel/copyright"];
        if ($atom) {
            $xpath->registerNamespace('atom', "http://www.w3.org/2005/Atom");
            $page_parts = ["/feed/subtitle", "/feed/author",
                "/feed/updated", "/feed/rights", "/feed/generator",
                "/atom:feed/atom:subtitle", "/atom:feed/atom:author",
                "/atom:feed/atom:updated", "/atom:feed/atom:rights",
                "/atom:feed/atom:generator"];
        }
        foreach ($page_parts as $part) {
            $doc_nodes = $xpath->evaluate($part);
            foreach ($doc_nodes as $node) {;
                $description .= " " . $node->textContent;
                if (strlen($description) > self::$max_description_len) {
                    break 2;
                }
            }
        }
        $description = mb_ereg_replace("(\s)+", " ",  $description);
        return $description;
    }
    /**
     * Returns up to MAX_LINK_PER_PAGE many links from the supplied
     * dom object where links have been canonicalized according to
     * the supplied $site information.
     *
     * @param object $dom   a document object with links on it
     * @param string $site   a string containing a url
     * @param bool $atom if the feed is atom or rss
     *
     * @return array   links from the $dom object
     */
    public static function links($dom, $site, $atom = false)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        $link_nodes = [
            "/rss/channel" => [ "url" =>"slink",
                CrawlConstants::TITLE => "title"],
            "/rss/channel/image" => [ "url" =>"url",
                CrawlConstants::TITLE => "title"],
            "/rss/channel/item" => [ "url" =>"slink",
                CrawlConstants::TITLE => "title",
                CrawlConstants::DESCRIPTION => "description", "guid" => "guid",
                "pubdate" => "pubdate"
                ],
        ];
        if ($atom) {
            $xpath->registerNamespace('atom', "http://www.w3.org/2005/Atom");
            $link_nodes = [
                "/feed/entry" => ["url" => "slink",
                    CrawlConstants::TITLE => "title",
                    CrawlConstants::DESCRIPTION => ["summary", "content"],
                    "guid" => "id", "pubdate" => "updated"],
                "/atom:feed/atom:entry" => ["url" => "slink",
                    CrawlConstants::TITLE => "title",
                    CrawlConstants::DESCRIPTION => ["summary", "content"],
                    "guid" => "id", "pubdate" => "updated"],
            ];
        }
        $i = 0;
        foreach ($link_nodes as $path => $retrieve_fields) {
            $nodes = $xpath->evaluate($path);
            foreach ($nodes as $node) {
                $result = self::linkAndTexts($node, $retrieve_fields, $site,
                    $atom);
                if ($result != false) {
                    list($url, $info) = $result;
                    $info["media"] = "feed";
                    $sites[$url] = $info;
                    $i++;
                }
                if (self::$max_links_to_extract > 0 &&
                    $i >= self::$max_links_to_extract) {
                    break 2;
                }
            }
        }
        return $sites;
    }
    /**
     * Returns a url info pair where the url comes from the link of
     * the given item node and info is array of additional feed data for that
     *  node. urls are canonicalized according to site.
     *
     * @param object $item_node the DOMNode to get a link and text from
     * @param array $retrieve_fields key value pairs to retrieve, at a minimum
     *      need to have one field of the form url => tag_name
     * @param string $site   a string containing a url
     * @param bool $atom if the feed is atom or rss
     *
     * @return array a url, info pair
     */
    public static function linkAndTexts($item_node, $retrieve_fields,
        $site, $atom = false)
    {
        $info = [];
        foreach ($retrieve_fields as $field_name => $tag_name) {
            foreach ($item_node->childNodes as $node) {
                if ($node->nodeName != $tag_name && (!is_array($tag_name)
                    || !in_array($node->nodeName, $tag_name) )) {
                    continue;
                }
                if ($field_name == 'url') {
                    if (!$atom) {
                        $url = UrlParser::canonicalLink(
                            $node->textContent, $site);
                    } else {
                        $url = UrlParser::canonicalLink(
                            $node->getAttribute("href"), $site);
                    }
                    if ($url === null || $url === "" ||
                        UrlParser::checkRecursiveUrl($url) ||
                        strlen($url) >= C\MAX_URL_LEN) {
                        return false;
                    }
                    continue;
                }
                if (empty($info[$field_name])) {
                    $info[$field_name] = "";
                }
                $info[$field_name] .= " ". $node->textContent;
            }
        }
        if (empty($url) || empty($info)) {
            return false;
        }
        if (count($info) == 1) {
            $info = array_values($info);
            $info[0] = mb_ereg_replace("(\s)+", " ",  $info[0]);
        }
        return [$url, $info];
    }
}
