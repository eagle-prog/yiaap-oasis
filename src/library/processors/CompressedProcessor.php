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
use seekquarry\yioop\library\PartialZipArchive;

/**
 * Used to create crawl summary information
 * for a gz compressed file whose uncompressed form has
 * a processor we index.
 *
 * @author Chris Pollett
 */
class CompressedProcessor extends PageProcessor
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
        self::$indexed_file_types[] = "gz";
        self::$indexed_file_types[] = "bz";
        self::$mime_processor["application/x-gzip"] = "CompressedProcessor";
        self::$mime_processor["application/x-bzip"] = "CompressedProcessor";
        self::$mime_processor["application/x-bzip2"] = "CompressedProcessor";
        self::$mime_processor["application/zip"] = "CompressedProcessor";
        self::$mime_processor["application/x-zip-compressed"] =
            "CompressedProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of compressed file of some known indexed_file_type
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
        if (preg_match('/^(.+)\.(gz|bz|zip)$/', $url, $match) === false ||
            empty($match[2])) {
            return null;
        }
        $uncompress_url = $match[1];
        $compress_type = $match[2];
        $compress_open = $compress_type . "open";
        $compress_read = $compress_type . "read";
        $compress_close = $compress_type . "close";
        $mime_type = UrlParser::guessMimeTypeFromFileName($uncompress_url,
            "unknown");
        if ($mime_type == 'unknown' ||
            !isset(self::$mime_processor[$mime_type]) ) {
            return null;
        }
        $uncompress_page = "";
        if ($compress_type == 'zip') {
            $file_name = UrlParser::getDocumentFilename($url);
            $zip = new PartialZipArchive($page);
            if ($zip) {
                $uncompress_page = $zip->getFromName($file_name);
            }
        } else {
            $temp_dir = C\CRAWL_DIR . "/temp/";
            if (!file_exists($temp_dir)) {
                 mkdir($temp_dir);
            }
            if (!file_exists($temp_dir)) {
                return null;
            }
            $temp_file = $temp_dir .  L\crawlHash($url);
            file_put_contents($temp_file, $page);
            if (!file_exists($temp_file)) {
                return null;
            }
            $ch = $compress_open($temp_file, "r");
            $max_size = 10 * C\PAGE_RANGE_REQUEST;
            while ($block = $compress_read($ch, 8192)) {
                $uncompress_page .= $block;
                if (strlen($uncompress_page) > $max_size) {
                    break;
                }
            }
            $compress_close($ch);
            unlink($temp_file);
        }
        if (empty($uncompress_page)) {
            return null;
        }
        $processor_name = C\NS_PROCESSORS .
            PageProcessor::$mime_processor[$mime_type];
        $processor = new $processor_name([], self::$max_description_len,
            $this->summarizer_option);
        return $processor->process($uncompress_page, $url);
    }
    /**
     * Return a document object based on a string containing the contents of
     * an XML page
     *
     * @param string $page   a web page
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        return L\getDomFromString($page);
    }
}
