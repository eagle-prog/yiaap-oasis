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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\WebArchiveBundle;

/**
 * Fetcher machines also act as archives for complete caches of web pages,
 * this controller is used to handle access to these web page caches
 *
 * @author Chris Pollett
 */
class ArchiveController extends Controller implements CrawlConstants
{
    /**
     * The only legal activity this controller will accept is a request
     * for the cache of a web page
     * @var array
     */
    public $activities = ["cache"];

    /**
     * Main method for this controller to handle requests. It first checks
     * the request is valid, and then handles the corresponding activity
     *
     * For this controller the only activity is to handle a cache request
     */
    public function processRequest()
    {
        $data = [];
        /* do a quick test to see if this is a request seems like from a
           legitimate machine
         */
        if (!$this->checkRequest()) {return; }
        $activity = $this->clean($_REQUEST['a'], "string");
        $this->call($activity);
    }
    /**
     * Retrieves the requested page from the WebArchiveBundle and echo it page,
     * base64 encoded
     */
    public function cache()
    {
        $offset = $this->clean($_REQUEST['offset'], "int");
        $partition = $this->clean($_REQUEST['partition'], "int");
        $crawl_time = $this->clean($_REQUEST['crawl_time'], "string");
        $prefix = "";
        if (isset($_REQUEST['instance_num'])) {
            $prefix = $this->clean($_REQUEST['instance_num'], "int")."-";
        }
        if (file_exists(C\CRAWL_DIR.'/cache/'.$prefix . self::archive_base_name.
                $crawl_time)) {
            $web_archive = new WebArchiveBundle(
                C\CRAWL_DIR.'/cache/'.$prefix . self::archive_base_name.
                    $crawl_time);
            $page = $web_archive->getPage($offset, $partition);
            echo base64_encode(serialize($page));
        } else {
            echo base64_encode(serialize(false));
        }
    }
}
