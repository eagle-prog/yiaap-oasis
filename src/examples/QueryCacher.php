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
namespace seekquarry\yioop\examples;

/**
 * Script to cache run a sequence of queries against a yioop instance
 * so that they can be cached
 */
define("YIOOP_URL", "http://localhost/");
define("TIME_BETWEEN_REQUEST_IN_SECONDS", 5);
define("QUERY_AGENT_NAME", "QUERY_CACHER");
if (empty($argv[1])) {
    echo <<< EOD
QUERY_CACHER
============
This program runs a sequence of queries against a Yioop Installation.
If file caching is turned on for that Yioop Installation, then those query
will be saved to its cache. To run this program, type a command like:
php QueryCacher.php file_name.txt
Here file_name.txt is the name of a text file with one query/line.
EOD;
exit();
} else {
    echo <<< EOD
QUERY_CACHER
============
Now running a sequence of queries against the yioop installation at:

EOD;
    echo YIOOP_URL ."\n\n";
}
$queries = file($argv[1]);
$agent = curl_init();
curl_setopt($agent, CURLOPT_USERAGENT, QUERY_AGENT_NAME);
curl_setopt($agent, CURLOPT_AUTOREFERER, true);
curl_setopt($agent, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($agent, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($agent, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($agent, CURLOPT_NOSIGNAL, true);
curl_setopt($agent, CURLOPT_RETURNTRANSFER, true);
curl_setopt($agent, CURLOPT_FAILONERROR, true);
curl_setopt($agent, CURLOPT_TIMEOUT, TIME_BETWEEN_REQUEST_IN_SECONDS);
curl_setopt($agent, CURLOPT_CONNECTTIMEOUT,
    TIME_BETWEEN_REQUEST_IN_SECONDS);
curl_setopt($agent, CURLOPT_HTTPHEADER, ['Expect:']);
$i = 1;
foreach ($queries as $query) {
    echo $i . " ". $query;
    curl_setopt($agent, CURLOPT_URL, YIOOP_URL . "?q=". urlencode($query));
    $response = curl_exec($agent);
    $i++;
    sleep(TIME_BETWEEN_REQUEST_IN_SECONDS);
}
curl_close($agent);
