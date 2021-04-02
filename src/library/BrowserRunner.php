<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 * Copyright (C) 2009 - 2021  Chris Pollett chris@pollett.org
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * END LICENSE
 *
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Used to execute browser-based Javascript and browser page rendering from PHP.
 *
 * @author Eswara Rajesh Pinapala
 */
class BrowserRunner
{
    /**
     * Tests if there is a headless browser (typically Phantom JS) available
     * before constructing this kind of object. If not, it throws an exceptio
     */
    public function __construct()
    {
        $version = $this->execute("-v");
        if (!$version) {
            throw new \Exception("BrowserRunner currently requires PhantomJS ".
                "package to run");
        }
    }
    /**
     * Runs a Javascript in the current headless browser instance and
     * return the results as either a JSON or PHP object.
     * @param string $script Javascript to run in browser
     */
    public function execute()
    {
        $command = C\PHANTOM_JS." " . implode(' ', func_get_args());
        $shell_result = shell_exec(escapeshellcmd($command));
        if ($shell_result === null) {
            return false;
        }
        if (substr($shell_result, 0, 1) !== '{') {
            //return if the result is not a JSON.
            return $shell_result;
        } else {
            //If the result is a JSON, decode JSON into a PHP array.
            $json = json_decode($shell_result, true);
            if ($json === null) {
                return false;
            }
            return $json;
        }
    }
}
