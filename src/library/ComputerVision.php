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
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/**
 * For crawlHash
 */
require_once __DIR__ . "/Utility.php";
/**
 * To convert to Iso639-2
 */
require_once __DIR__ . "/LocaleFunctions.php";
/**
 * Class used to encapsulate verious methods related to computer
 * vision that might be useful for indexing documents. These
 * include recognizing text in images
 */
class ComputerVision
{
    /**
     * Returns whether or not this Yioop system can recognize text in images
     * Currently, this is down using the tesseract external program, so this
     * method checks if a path to that program has been defined.
     * @return bool whether a path to tesseract has been defined.
     */
    public static function ocrEnabled()
    {
        return C\nsdefined("TESSERACT");
    }
    /**
     * Given a file path to a image file and set of target languages, returns
     * the text in those languages that the image contained
     *
     * @param string $image_path a filepath to an image
     * @param array $langs locale_tags of languages we want to extract text for
     * @return string text extracted from image
     */
    public static function recognizeText($image_path,
        $langs = [C\DEFAULT_LOCALE])
    {
        static $call_count = 0;
        if (!C\nsdefined("TESSERACT")) {
            return "";
        }
        $temp_dir = C\CRAWL_DIR . "/temp/";
        if (!file_exists($temp_dir)) {
             mkdir($temp_dir);
        }
        if (!file_exists($temp_dir)) {
            return "";
        }
        $image_file_name = pathinfo($image_path, PATHINFO_BASENAME);
        $iso_string = "";
        $add = "";
        foreach ($langs as $lang) {
            $iso_lang = localeTagToIso639_2Tag($lang);
            $iso_string .= $add . $iso_lang;
            $add = "+";
        }
        $ocr_file = $temp_dir . $call_count . $image_file_name . "-out";
        $ocr_exec = C\TESSERACT . " $image_path $ocr_file -l $iso_string";
        exec($ocr_exec);
        $ocr_file .= ".txt";
        $ocr_string =  "";
        if (file_exists($ocr_file)) {
            $ocr_string = file_get_contents($ocr_file);
            @unlink($ocr_file);
        }
        $call_count ++;
        return trim($ocr_string, " \t\n\r\0\x0B\x0C");
    }
}
