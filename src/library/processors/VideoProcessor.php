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

/**
 * Base abstract class common to all processors used to create crawl summary
 * information from videos
 *
 * @author Chris Pollett
 */
class VideoProcessor extends PageProcessor
{
    /**
     * Number of images to use for an animated thumbnail
     */
    const NUM_ANIMATED_THUMBS = 10;
    /**
     * Minimum duration movie (in seconds ) before make an animated thumbnail
     */
    const MIN_ANIMATE_LENGTH = 60;
    /**
     * Extract summary data from the image provided in $page together the url
     *     in $url where it was downloaded from
     *
     * VideoProcessor class defers a proper implementation of this method to
     *     subclasses
     *
     * @param string $page  the image represented as a character string
     * @param string $url  the url where the image was downloaded from
     * @return array summary information including a thumbnail and a
     *     description (where the description is just the url)
     */
    public function process($page, $url)
    {
        return null;
    }
    /**
     * Used to save a temporary file with the data downloaded for a url
     * while carrying out image processing
     *
     * @param string $page contains data about an image that one needs to save
     * @param string $url where $page data came from
     * @param string $file_extension to be associated wit the $page data
     */
    public function saveTempFile($page, $url, $file_extension)
    {
        static $call_count = 0;
        $temp_dir = C\CRAWL_DIR . "/temp/";
        if (!file_exists($temp_dir)) {
             mkdir($temp_dir);
        }
        if (!file_exists($temp_dir)) {
            return null;
        }
        $temp_file = $temp_dir . $call_count .
            L\crawlHash($url) . ".$file_extension";
        $call_count++;
        file_put_contents($temp_file, $page);
        return $temp_file;
    }
    /**
     *
     */
    public static function getDuration($video)
    {
        if(!function_exists("exec")) {
            return -1;
        }
        $path = pathinfo(C\FFMPEG);
        $ffprobe = $path['dirname'] . "/ffprobe";
        $duration_exec = "$ffprobe -i \"$video\" -show_entries ".
            "format=duration -v quiet -of csv=\"p=0\"";
        return floatval(exec($duration_exec));
    }

    /**
     * Used to create a thumbnail from an image object
     *
     * @param object $image  image object with image
     * @param int $width = width in pixels of thumb
     * @param int $height = height in pixels of thumb
     *
     */
    public static function createThumbs($folder, $thumb_folder, $file_name,
        $width = C\THUMB_DIM, $height = -1,
        $num_frames = self::NUM_ANIMATED_THUMBS,
        $min_animate_length = self::MIN_ANIMATE_LENGTH)
    {
        if(!function_exists("exec") || !C\nsdefined("FFMPEG")) {
            return;
        }
        @unlink("$thumb_folder/$file_name.jpg");
        $duration = self::getDuration("$folder/$file_name");
        $num_thumbs = ($duration > $min_animate_length) ? $num_frames : 1;
        $thumb_interval = ceil($duration/$num_thumbs);
        $thumb_time = min(ceil($duration/2), 3);
        $png_input = "";
        for ($thumb_num = 0, $thumb_time = min(ceil($duration/2), 3);
            $thumb_time < $duration; $thumb_num++,
            $thumb_time += $thumb_interval) {
            $out_name = sprintf("$thumb_folder/$file_name-"."%'.02d".".jpg",
                $thumb_num);
            $png_input .= " -i \"$out_name\" ";
            $make_static_thumb =
                C\FFMPEG . " -ss $thumb_time -i \"$folder/$file_name\"".
                " -hide_banner -loglevel panic -vframes 1 -map 0:v:0" .
                " -vf \"thumbnail,scale=$width:$height\" -y " .
                "\"$out_name\" ";
            exec($make_static_thumb);
        }
        if ($num_thumbs > 1) {
            $make_animated_thumb = C\FFMPEG . " -hide_banner -loglevel ".
                "panic -framerate 1 -pattern_type glob " .
                "-i '$thumb_folder/$file_name-*.jpg' -y  " .
                "  \"$thumb_folder/$file_name.gif\"";
            exec($make_animated_thumb);
            clearstatcache("$thumb_folder/$file_name.gif");
        }
        $jpegs = glob("$thumb_folder/$file_name-*.jpg");
        foreach ($jpegs as $jpeg) {
            if ($jpeg == "$thumb_folder/$file_name-00.jpg") {
                rename($jpeg, "$thumb_folder/$file_name.jpg");
            } else {
                unlink($jpeg);
            }
        }
        clearstatcache("$thumb_folder/$file_name.jpg");
    }
}
