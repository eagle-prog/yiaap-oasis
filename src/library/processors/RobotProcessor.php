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
use seekquarry\yioop\library\UrlParser;

/**
 * Processor class used to extract information from robots.txt files
 *
 * @author Chris Pollett
 */
class RobotProcessor extends PageProcessor
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
        self::$indexed_file_types[] = "pdf";
        self::$mime_processor["text/robot"] = "RobotProcessor";
    }
    /**
     * Parses the contents of a robots.txt page extracting allowed,
     * disallowed paths, crawl-delay, and sitemaps. We also extract a
     * list of all user agent strings seen.
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by
     *     TextProcessor at this point. Some of its subclasses override
     *     this method and use url to produce complete links for
     *     relative links within a document
     *
     * @return array a summary of (title, description, links, and content) of
     *     the information in $page
     */
    public function process($page, $url)
    {
        $summary = null;
        $summary[self::TITLE] = "";
        $summary[self::DESCRIPTION] = "";
        $summary[self::LANG] = null;
        $summary[self::ROBOT_PATHS] = [self::ALLOWED_SITES => [],
            self::DISALLOWED_SITES => []];
        $summary[self::AGENT_LIST] = [];
        $summary[self::LINKS] = [];
        $host_url = UrlParser::getHost($url);
        $lines = explode("\n", $page);
        $add_rule_state = false;
        $rule_added_flag = false;
        $delay_flag = false;
        $delay = 0;
        foreach ($lines as $pre_line) {
            $pre_line_parts = explode("#", $pre_line);
            $line = $pre_line_parts[0];
            $line_parts = explode(":", $line);
            if (!isset($line_parts[1])) {
                continue;
            }
            $field = array_shift($line_parts);
            $value = implode(":", $line_parts);
            //notice we lower case field, so switch below is case insensitive
            $field = strtolower(trim($field));
            $value = trim($value);
            $specificness = 0;
            if (strlen($value) == 0) {
                continue;
            }
            switch ($field) {
                case "user-agent":
                    //we allow * in user agent string
                    $summary[self::AGENT_LIST][] = $value;
                    $current_specificness =
                        (strcmp($value, C\USER_AGENT_SHORT) == 0) ? 1 : 0;
                    if ($current_specificness < $specificness) {
                        break;
                    }
                    if ($specificness < $current_specificness) {
                        //Give precedence to exact match on agent string
                        $specificness = $current_specificness;
                        $add_rule_state = true;
                        $summary[self::ROBOT_PATHS] =
                            [self::ALLOWED_SITES => [],
                            self::DISALLOWED_SITES => []];
                        break;
                    }
                    $agent_parts = explode("*", $value);
                    $offset = 0;
                    $add_rule_state = true;
                    foreach ($agent_parts as $part) {
                        if ($part == "") {
                            continue;
                        }
                        $new_offset = stripos(C\USER_AGENT_SHORT, $part,
                            $offset);
                        if ($new_offset === false) {
                            $add_rule_state = false;
                            break;
                        }
                        $offset = $new_offset;
                    }
                break;
                case "sitemap":
                    $tmp_url = UrlParser::canonicalLink($value, $host_url);
                    if (!UrlParser::checkRecursiveUrl($tmp_url)
                        && strlen($tmp_url) < C\MAX_URL_LEN) {
                        $summary[self::LINKS][] = $tmp_url;
                    }
                break;
                case "allow":
                    if ($add_rule_state) {
                        $rule_added_flag = true;
                        $summary[self::ROBOT_PATHS][self::ALLOWED_SITES][] =
                            $this->makeCanonicalRobotPath($value);
                    }
                break;
                case "disallow":
                    if ($add_rule_state) {
                        $rule_added_flag = true;
                        $summary[self::ROBOT_PATHS][self::DISALLOWED_SITES][] =
                            $this->makeCanonicalRobotPath($value);
                    }
                break;
                case "crawl-delay":
                    if ($add_rule_state) {
                        $delay_flag = true;
                        $delay = max($delay, intval($value));
                    }
                break;
            }
        }
        if ($delay_flag) {
            if ($delay > C\MAXIMUM_CRAWL_DELAY)  {
               $summary[self::ROBOT_PATHS][self::DISALLOWED_SITES][] = "/";
            } else {
                $summary[self::CRAWL_DELAY] = $delay;
            }
        }
        $summary[self::PAGE] = "<html><body><pre>".
                strip_tags($page)."</pre></body></html>";
        return $summary;
    }
    /**
     *  Converts a path in a robots.txt file into a standard form usable by
     *  Yioop
     *  For robot paths
     *    foo
     *  is treated the same as
     *    /foo
     *  Path might contain urlencoded characters. These are all decoded
     *  except for %2F which corresponds to a / (this is as per
     *  http://www.robotstxt.org/norobots-rfc.txt)
     *
     * @param string $path to convert
     * @return string Yioop canonical path
     */
    public function makeCanonicalRobotPath($path)
    {
        if ($path[0] != "/") {
            $path = "/$path";
        }
        return urldecode(preg_replace("/\%2F/i", "%252F", $path));
    }
}
