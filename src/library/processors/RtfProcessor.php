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

/**
 * Used to create crawl summary information
 * for RTF files
 *
 * @author Chris Pollett
 */
class RtfProcessor extends TextProcessor
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
        self::$indexed_file_types[] = "rtf";
        self::$mime_processor["text/rtf"] = "RtfProcessor";
    }
    /**
     * Computes a summary based on a rtf string of a document
     *
     * @param string $page rtf string of a document
     * @param string $url location the document came from, not used by
     *     RTFProcessor at this point.
     *
     * @return array a summary of (title, description,links, and content) of
     *     the information in $page
     */
    public function process($page, $url)
    {
        $text = "";
        if (is_string($page)) {
            $text =  self::extractText($page);
        }
        if ($text == "") {
            $text = $url;
        }
        $summary = parent::process($text, $url);
        return $summary;
    }
    /**
     * Gets plain text out of an rtf string
     *
     * Plain text is mainly extracted by getText(), this function does
     * some pre and post processing of escape braces and stuff
     *
     * @param string $rtf_string what to extract plain text out of
     * @return string plain texts
     */
    public static function extractText($rtf_string)
    {
        $rtf_string = preg_replace('/\\\{/',"!ZZBL!", $rtf_string);
        $rtf_string = preg_replace('/\\\}/',"!ZZBR!", $rtf_string);
        $rtf_string = preg_replace('/\\\\\'d\d/',"'", $rtf_string);
        $rtf_string = preg_replace('/\\\\\'b\d/',"'", $rtf_string);
        $out = self::getText($rtf_string);
        $out = preg_replace("!ZZBL!",'/\\\{/', $out);
        $out = preg_replace("!ZZBR!", '/\\\}/', $out);
        return $out;
    }
    /**
     * Gets plain text out of an rtf string
     *
     * @param string $rtf_string what to extract plain text out of
     * @return string plain texts
     */
    public static function getText($rtf_string)
    {
        $len = strlen($rtf_string);
        $cur_pos = 0;
        $out = "";
        $i = 0;
        while($cur_pos < $len) {
            list($cur_pos, $object_string) =
                self::getNextObject($rtf_string, $cur_pos);
            if (strpos($object_string, "{")) {
                $out .= self::getText($object_string);
            } else {
                if (preg_match('/\\\/',$object_string) == 0) {
                    $out .=  $object_string;
                } else if (preg_match('/\\\(par)/', $object_string) > 0) {
                    $text = preg_replace('/\\\(\w)+/', "", $object_string);
                    $out .= $text."\n";
                } else if (preg_match(
                    '/(\\\(title)|\\\(author)|\\\(operator)|\\\(company))/',
                    $object_string) > 0) {
                    $text = preg_replace('/\\\(\w)+/', "", $object_string);
                    $out .= $text."\n\n";
                }
            }
        }
        return $out;
    }
    /**
     * Gets the contents of the rtf group at the current position in the string
     *
     * @param string $rtf_string data to get rtf group from
     * @param int $cur_pos position in $rtf_string at which to get  group
     * @return string contents of rtf groups
     */
    public static function getNextObject($rtf_string, $cur_pos)
    {
        return self::getBetweenTags($rtf_string, $cur_pos, '{', '}');
    }
}
