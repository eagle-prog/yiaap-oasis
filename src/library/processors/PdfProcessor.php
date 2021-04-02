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
use seekquarry\yioop\Library as L;
use seekquarry\yioop\library\ComputerVision;
use seekquarry\yioop\library\UrlParser;

/**
 * Used to create crawl summary information
 * for PDF files
 *
 * @author Chris Pollett
 */
class PdfProcessor extends TextProcessor
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
            $max_links_to_extract,$summarizer_option);
        /** Register File Types We Handle*/
        self::$indexed_file_types[] = "pdf";
        self::$mime_processor["application/pdf"] = "PdfProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of PDF data.
     *
     * @param $page  a string consisting of web-page contents
     * @param $url  the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $text = "";
        if (is_string($page)) {
            list($encoding, $title) = self::getEncodingTitle($page);
            $text =  self::getText($page, $url, $encoding);
        }
        if ($text == "") {
            $text = $url;
        }
        $summary = parent::process($text, $url);
        if ($title) {
            $summary[self::TITLE] = $title;
        }
        return $summary;
    }
    /**
     * Returns the first encoding format information found in the PDF document
     *
     * @param string $pdf_string a string representing the PDF document
     * @return array [encoding, title] which of the default (if any) PDF
     * encoding formats is being used: MacRomanEncoding, WinAnsiEncoding,
     * PDFDocEncoding, etc as well as a title for the document if found
     *
     */
    public static function getEncodingTitle($pdf_string)
    {
        $len = strlen($pdf_string);
        $cur_pos = 0;
        $out = "";
        $i = 0;
        set_error_handler(null);
        $encoding = "";
        $title = "";
        while($cur_pos < $len && (!$encoding || !$title)) {
            list($cur_pos, $object_string) =
                self::getNextObject($pdf_string, $cur_pos);
            $object_dictionary = self::getObjectDictionary($object_string);
            if (preg_match("/\/(\w+Encoding)\b/", $object_dictionary,
                $match) != false) {
                $encoding = $match[1];
            }
            if (preg_match("/\/Title\(([^\)]+)\)/", $object_dictionary,
                $match) != false) {
                $title = $match[1];
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        return [$encoding, $title];
    }
    /**
     * Gets the text out of a PDF document
     *
     * @param string $pdf_string a string representing the PDF document
     * @param $url  the url where the page contents came from,
     *    used to canonicalize relative links
     * @param string $encoding which of the default (if any) PDF encoding
     *    formats is being used: MacRomanEncoding, WinAnsiEncoding,
     *    PDFDocEncoding, etc.
     * @return string text extracted from the document
     */
    public static function getText($pdf_string, $url, $encoding = "")
    {
        $len = strlen($pdf_string);
        $cur_pos = 0;
        $out = "";
        $i = 0;
        set_error_handler(null);
        $state = "text";
        $temp_dir = C\CRAWL_DIR . "/temp/";
        if (!file_exists($temp_dir)) {
             mkdir($temp_dir);
        }
        if (!file_exists($temp_dir)) {
            return null;
        }
        $lang = UrlParser::getLang($url);
        while($cur_pos < $len) {
            list($cur_pos, $object_string) =
                self::getNextObject($pdf_string, $cur_pos);
            $object_dictionary = self::getObjectDictionary($object_string);
            if (ComputerVision::ocrEnabled() &&
                self::objectDictionaryHas($object_dictionary, ["Image"]) &&
                self::objectDictionaryHas($object_dictionary, ["XObject"]) &&
                self::objectDictionaryHas($object_dictionary, ["Width"]) &&
                self::objectDictionaryHas($object_dictionary, ["Height"]) &&
                !self::objectDictionaryHas($object_dictionary, ["ImageMask"])) {
                $stream_data = ltrim(self::getObjectStream($object_string));
                preg_match("/\/Width\s+(\d+)\b/", $object_dictionary, $matches);
                $width = $matches[1] ?? 0;
                preg_match("/\/Height\s+(\d+)\b/", $object_dictionary,
                    $matches);
                $height = $matches[1] ?? 0;
                preg_match("/\/BitsPerComponent\s+(\d+)\b/", $object_dictionary,
                    $matches);
                $bits_per_component = $matches[1] ?? 8;
                preg_match("/\/ColorSpace\s+(Device)?(Gray|RGB|CMYK)\b/",
                    $object_dictionary, $matches);
                $color_space = $matches[2] ?? "RGB";
                $is_jpeg = preg_match("/\/Filter\s+\/DCTDecode\b/",
                    $object_dictionary);
                if (!$width || !$height || $color_space == "CMYK") {
                    continue;
                }
                $is_rgb = ($color_space == "RGB");
                if (self::objectDictionaryHas($object_dictionary,
                    ["FlateDecode"])) {
                    $stream_data = @gzuncompress($stream_data);
                }
                if ($is_jpeg) {
                    // if pdf corrupted this can also through an error
                    @$image  = imagecreatefromstring($stream_data);
                } else {
                    $image  = imagecreatetruecolor($width, $height);
                    $pix_loc = 0;
                    for($y = 0; $y < $height; $y++) {
                        for($x = 0; $x < $width; $x++) {
                            if ($is_rgb) {
                                $r = empty($stream_data[$pix_loc]) ? 255 :
                                    ord($stream_data[$pix_loc]);
                                $g = empty($stream_data[$pix_loc + 1]) ? 255 :
                                    ord($stream_data[$pix_loc + 1]);
                                $b = empty($stream_data[$pix_loc + 2]) ? 255 :
                                    ord($stream_data[$pix_loc + 2]);
                                $pix_loc += 3;
                            } else {
                                $r = empty($stream_data[$pix_loc]) ? 255 :
                                    ord($stream_data[$pix_loc]);
                                $g = $r;
                                $b = $r;
                                $pix_loc++;
                            }
                            $color = imagecolorallocate($image, $r, $g, $b);
                            imagesetpixel($image, $x, $y, $color);
                        }
                    }
                }
                $temp_file = $temp_dir . L\crawlHash($stream_data) . ".png";
                if ($image) {
                    @imagepng($image, $temp_file);
                    $ocr_data = ComputerVision::recognizeText($temp_file,
                        [$lang]);
                    if (!empty($ocr_data)) {
                        $out  .= $ocr_data;
                    }
                    @unlink($temp_file);
                }
            } else if (self::objectDictionaryHas(
                $object_dictionary, ["Type", "Font", "FontDescriptor"])) {
                $state = "font";
                continue;
            }
            if (!self::objectDictionaryHas(
                $object_dictionary, ["Image", "Catalog"])) {
                $stream_data =
                    rtrim(ltrim(self::getObjectStream($object_string)));
                if ($state == 'font') {
                    $state == 'text';
                    continue;
                }
                if (self::objectDictionaryHas(
                    $object_dictionary, ["FlateDecode"])) {
                    $stream_data = @gzuncompress($stream_data);
                    if (strpos($stream_data, "PS-AdobeFont")) {
                        $out .= $stream_data . "\n\n";
                    }
                    $text = self::parseText($stream_data, $encoding);
                    $out .= $text. "\n\n";
                } else {
                    $text = self::parseText($stream_data, $encoding);
                    if (strpos($stream_data, "PS-AdobeFont")){
                        $out .= $stream_data . "\n\n";
                    }
                    $out .= $text . "\n\n";
                }
            }
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        $font_pos = strpos($out, "PS-AdobeFont");
        if (!$font_pos) {
            $font_pos = strlen($out);
        }
        $out = substr($out, 0, $font_pos);
        return $out;
    }
    /**
     * Gets between an obj and endobj tag at the current position in a PDF
     * document
     *
     * @param string $pdf_string astring of a PDF document
     * @param int $cur_pos a integer postion in that string
     * @return string the contents of the PDF object located at $cur_pos
     */
    public static function getNextObject($pdf_string, $cur_pos)
    {
        return self::getBetweenTags($pdf_string, $cur_pos, "obj", "endobj");
    }
    /**
     * Checks if the PDF object's object dictionary is in a list of types
     *
     * @param string $object_dictionary the object dictionary to check
     * @param array $type_array the list of types to check against
     * @return whether it is in or not
     */
    public static function objectDictionaryHas($object_dictionary, $type_array)
    {
        foreach ($type_array as $type) {
            if (strstr($object_dictionary, $type)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Gets the object dictionary portion of the current PDF object
     * @param string $object_string represents the contents of a PDF object
     * @return string the object dictionary for the object
     */
    public static function getObjectDictionary($object_string)
    {
        list( , $object_dictionary) =
            self::getBetweenTags($object_string, 0, '<<', '>>');
        return $object_dictionary;
    }
    /**
     * Gets the object stream portion of the current PDF object
     *
     * @param string $object_string represents the contents of a PDF object
     * @return string the object stream for the object
     */
    public static function getObjectStream($object_string)
    {
        list( , $stream_data) =
            self::getBetweenTags($object_string, 0, 'stream', 'endstream');
        return $stream_data;
    }
    /**
     * Extracts text from PDF data, getting rid of non printable data,
     * square brackets and parenthesis and converting char codes to their
     * values.
     *
     * @param string $data source to extract character data from
     * @param string $encoding which of the default (if any) PDF encoding
     *    formats is being used: MacRomanEncoding, WinAnsiEncoding,
     *    PDFDocEncoding, etc.
     * @return string extracted text
     */
    public static function parseText($data, $encoding = "")
    {
        $cur_pos = 0;
        //replace ASCII codes in decimal with their value
        $data = preg_replace_callback('/\\\(\d{3})/',
            function($matches) {
                return chr(intval($matches[1]));
            },
            $data);
        //replace ASCII codes in hex with their value
        $data = preg_replace_callback('/\<([0-9A-F]{2})\>/',
            function($matches) {
                return chr(hexdec($matches[1]));
            },
            $data);
        $len = strlen($data);
        $out = "";
        $escape_flag = false;
        while($cur_pos < $len) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '[' && !$escape_flag) {
                list($cur_pos, $text) = self::parseBrackets($data, $cur_pos,
                    $encoding);
                $cur_pos--;
                $out .= " ". $text;
            }
            if ($cur_char == '\\') {
                $escape_flag = true;
            } else {
                $escape_flag = false;
            }
            $cur_pos++;
        }
        return $out;
    }
    /**
     * Extracts text till the next close brackets
     *
     * @param string $data source to extract character data from
     * @param int $cur_pos position to start in $data
     * @param string $encoding which of the default (if any) PDF encoding
     *    formats is being used: MacRomanEncoding, WinAnsiEncoding,
     *    PDFDocEncoding, etc.
     * @return array pair consisting of the final position in $data as well
     *     as extracted text
     */
    public static function parseBrackets($data, $cur_pos, $encoding = "")
    {
        $cur_pos++;
        $len = strlen($data);
        $out = "";
        $escape_flag =false;
        $cur_char = "";
        while($cur_pos < $len && ($cur_char != "]")) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '(') {
                list($cur_pos, $text) = self::parseParentheses($data, $cur_pos,
                    $encoding);
                $cur_pos --;
                $out .= $text;
            }
            $cur_pos++;
        }
        if (isset($data[$cur_pos]) && isset($data[$cur_pos + 1]) &&
            ord($data[$cur_pos]) == ord('T') &&
                ord($data[$cur_pos + 1]) == ord('J') ) {
            if (isset($data[$cur_pos + 3]) &&
                ord($data[$cur_pos + 3]) != ord('F')) {
                $out .= " ";
            } else {
                $out .= "\n";
            }
        }
        return [$cur_pos, $out];
    }
    /**
     * Extracts ASCII text till the next close parenthesis
     *
     * @param string $data source to extract character data from
     * @param int $cur_pos position to start in $data
     * @param string $encoding which of the default (if any) PDF encoding
     *    formats is being used: MacRomanEncoding, WinAnsiEncoding,
     *    PDFDocEncoding, etc.
     * @return array pair consisting of the final position in $data as well
     *     as extracted text
     */
    public static function parseParentheses($data, $cur_pos, $encoding)
    {
        $cur_pos++;
        $len = strlen($data);
        $out = "";
        $escape_flag =false;
        $cur_char = "";
        while($cur_pos < $len && ($cur_char != ")" || $escape_flag)) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '\\' && !$escape_flag) {
                $escape_flag = true;
            } else {
                if ($escape_flag || $cur_char !=")") {
                    $out .= self::convertChar($cur_char, $encoding);
                }
                $escape_flag = false;
            }
            $cur_pos++;
        }
        $check_positioning = substr($data, $cur_pos, 4);
        if (preg_match("/\-\d{3}/", $check_positioning) > 0 ) {
            $out .= " ";
        }
        return [$cur_pos, $out];
    }
    /**
     * Used to convert characters from one of the built in PDF
     * encodings to UTF-8
     * @param char $cur_char character to conver
     * @param string $encoding which of the default (if any) PDF encoding
     *    formats is being used: MacRomanEncoding, WinAnsiEncoding,
     *    PDFDocEncoding, etc.
     * @return string resultign converted string for character
     */
    public static function convertChar($cur_char, $encoding)
    {
        $ascii = ord($cur_char);
        if ((9 <= $ascii && $ascii <= 13) ||
            (32 <= $ascii && $ascii <= 126)) {
            return $cur_char;
        }
        if ($encoding == "MacRomanEncoding") {
            switch($ascii)
            {
                case 190:
                    return 'ae';
                case 198:
                    return 'AE';
                case 206:
                    return 'OE';
                case 207:
                    return 'oe';
                case 222:
                    return 'fi';
                case 223:
                    return 'fl';
            }
        } else if ($encoding == "WinAnsiEncoding") {
            switch($ascii)
            {
                case 140:
                    return 'OE';
                case 156:
                    return 'oe';
                case 198:
                    return 'AE';
                case 230:
                    return 'ae';
            }
        }
        return "";
    }
}
