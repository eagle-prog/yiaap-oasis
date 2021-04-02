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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Library of functions used to manipulate and to extract components from urls
 *
 * @author Chris Pollett
 */
class UrlParser
{
    /**
     * Checks if the url scheme is either http, https, or gopher (old protocol
     * but somewhat geeky-cool to still support).
     *
     * @param string $url  the url to check
     * @return bool returns true if it is either http,https, or gopher and false
     *     otherwise
     */
    public static function isSchemeCrawlable($url)
    {
        $url_parts = @parse_url($url);
         if (isset($url_parts['scheme']) && !in_array($url_parts['scheme'],
             ["http", "https", "gopher"])) {
            return false;
        }
        return true;
    }
    /**
     * Converts a url with a scheme into one without. Also removes trailing
     * slashes from url. Shortens url to desired length by inserting ellipsis
     * for part of it if necessary
     *
     * @param string $url  the url to trim
     * @param int $max_len length to shorten url to, 0 = no shortening
     * @return string the trimmed url
     */
    public static function simplifyUrl($url, $max_len = 0)
    {
        $url_parts = @parse_url($url);
        if (isset($url_parts['scheme'])) {
            $scheme_len = strlen($url_parts['scheme']);
            $url = mb_substr($url, $scheme_len + 3);
        }
        $len = strlen($url);
        if (isset($url[$len - 1]) && $url[$len - 1] == "/") {
            $url = mb_substr($url, 0, $len - 1);
        }
        if ($len > 0 && $len > $max_len) {
            $front_len = ceil($max_len * 0.8);
            $end_len = ceil($max_len * 0.2);
            $url = mb_substr($url, 0, $front_len)."...".
                mb_substr($url, -$end_len);
        }
        $url = urldecode($url);
        $url = mb_ereg_replace(" ", "%20", $url);
        return $url;
    }
    /**
     * Checks if the url has a host part.
     *
     * @param string $url  the url to check
     * @return bool true if it does; false otherwise
     */
    public static function hasHostUrl($url)
    {
       $url_parts = @parse_url($url);
       return isset($url_parts['host']);
    }
    /**
     * Get the port number of a url if present; if not return 80
     *
     * @param string $url the url to extract port number from
     * @return int a port number
     */
    public static function getPort($url)
    {
        $url_parts = @parse_url($url);
        if (isset($url_parts['port'])) {
            return $url_parts['port'];
        }
        if (isset($url_parts['scheme']) &&
            $url_parts['scheme'] == 'https') {
            return 443;
        }
        return 80;
    }
    /**
     * Get the scheme of a url if present; if not return http
     *
     * @param string $url the url to extract scheme from
     * @return int a port number
     */
    public static function getScheme($url)
    {
        $scheme = substr($url, 0, strpos($url, ":"));
        if ($scheme) {
            return $scheme;
        }
        return "http";
    }
    /**
     * Attempts to guess the language tag based on url
     *
     * @param string $url the url to parse
     * @return the top level domain if present; false otherwise
     */
    public static function getLang($url)
    {
        $LANG = [
            'us' => 'en',
            "uk" => 'en',
            "ca" => 'en',
            "au" => 'en',
            "bz" => 'en',
            "ie" => 'en',
            "jm" => 'en',
            "nz" => 'en',
            "za" => 'en',
            "zw" => 'en',
            "tt" => 'en',
            "eg" => 'ar',
            "dz" => 'ar',
            "bh" => 'ar',
            "jo" => 'ar',
            "kw" => 'ar',
            "lb" => 'ar',
            "iq" => 'ar',
            "ma" => 'ar',
            "om" => 'ar',
            "qa" => 'ar',
            "sa" => 'ar',
            "sy" => 'ar',
            "tn" => 'ar',
            "ae" => 'ar',
            "ye" => 'ar',
            "de" => 'de',
            "at" => "de",
            "es" => 'es',
            "ar" => 'es',
            "bo" => 'es',
            "cl" => 'es',
            "co" => 'es',
            "cr" => 'es',
            "dr" => 'es',
            "ec" => 'es',
            "sv" => 'es',
            "gt" => 'es',
            "hn" => 'es',
            "mx" => 'es',
            "ni" => 'es',
            "pa" => 'es',
            "py" => 'es',
            "pe" => 'es',
            "pr" => 'es',
            "uy" => 'es',
            "ve" => 'es',
            "fr" => 'fr-FR',
            "be" => 'fr-FR',
            "lu" => 'fr-FR',
            "hk" => 'zh-CN',
            "id" => 'in-ID',
            "il" => 'he',
            "ir" => 'fa',
            "it" => 'it',
            "jp" => 'ja',
            "kp" => 'ko',
            "kr" => 'ko',
            "pl" => 'pl',
            "br" => 'pt',
            "pt" => 'pt',
            "qc" => 'fr',
            "ru" => 'ru',
            "sg" => 'zh-CN',
            "th" => 'th',
            "tr" => 'tr',
            "tw" => 'zh-CN',
            "vi" => 'vi-VN',
            "vn" => 'vi-VN',
            "cn" => 'zh-CN',
        ];
        $host = @parse_url($url, \PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $host_parts = explode(".", $host);
        $count = count($host_parts);
        if ($count > 0) {
            $tld = $host_parts[$count - 1];
            if ($tld == 'ca' && isset($host_parts[$count - 2]) &&
                $host_parts[$count - 2] == 'qc') {
                $tld = 'qc';
            }
            if (isset($LANG[$tld])) {
                return $LANG[$tld];
            }
        }
        /* this is to guess language from domains like:
            it.wikipedia.org
         */
        if (in_array($tld, ["com", "edu", "gov", "mil", "org", "net"])) {
            if (in_array($host_parts[0], ['az', 'ar', 'ba', 'be', 'bg', 'bn',
                'bo', 'br', 'bs', 'ca', 'co', 'cs', 'cy', 'da', 'de', 'ds',
                'dv', 'el', 'en', 'es', 'eu', 'fa', 'fi', 'fil', 'fo', 'fr',
                'fy', 'ga', 'gd', 'gl', 'gsw', 'gu', 'ha', 'he', 'hi', 'hr',
                'hsb', 'hu', 'hy', 'id', 'ig', 'ii', 'is', 'it', 'iu', 'ja',
                'ka', 'kk', 'kl', 'km', 'kn', 'kok', 'ko', 'ky', 'lb', 'lo',
                'lt', 'lv', 'mi', 'mk', 'ml', 'mn', 'moh', 'mr', 'ms', 'mt',
                'nb', 'ne', 'nl', 'nn', 'nso', 'oc', 'or', 'pa', 'pl', 'prs',
                'ps', 'pt', 'qut', 'quz', 'rm', 'ro', 'ru', 'rw', 'sah', 'sa',
                'se', 'si', 'sk','sl','sma','smj','smn','sms','sq','sr','sv',
                'sw','syr','ta', 'te','tg','th','tk','tn', 'tr', 'tt', 'tzm',
                'ug', 'uk', 'ur', 'uz', 'vi', 'wo', 'xh','yo', 'zh', 'zu'])) {
                return $host_parts[0];
            } else {
                return "en";
            }
        }
        return null;
    }
    /**
     * Get the host name portion of a url if present; if not return false
     *
     * @param string $url the url to parse
     * @param bool $with_login_and_port whether to include user,password,port
     *      if present
     * @return the host portion of the url if present; false otherwise
     */
    public static function getHost($url, $with_login_and_port = true)
    {
        $url_parts = @parse_url($url);
        if (!isset($url_parts['scheme']) ) {
            return false;
        }
        $host_url = $url_parts['scheme'].'://';
        //handles common typo http:/yahoo.com rather than http://yahoo.com
        if (!isset($url_parts['host'])) {
            if (isset($url_parts['path'])) {
                $url_parts = @parse_url($url_parts['scheme'].":/".
                    $url_parts['path']);
                if (!isset($url_parts['host'])) {
                    return false;
                }
            } else {
                return false;
            }
        }
        if ($with_login_and_port &&
            isset($url_parts['user']) && isset($url_parts['pass'])) {
            $host_url .= $url_parts['user'].":".$url_parts['pass']."@";
        }
        if (strlen($url_parts['host']) <= 0) {
            return false;
        }
        $host_url .= $url_parts['host'];
        if ($with_login_and_port && isset($url_parts['port'])) {
            $host_url .= ":".$url_parts['port'];
        }
        return $host_url;
    }
    /**
     * Gets the domain of a url less any leading www
     * @param string $url to get domain of
     * @return string the base domain as defined above
     */
    public static function getBaseDomain($url)
    {
        $host = UrlParser::getHost($url, false);
        $scheme = UrlParser::getScheme($url);
        $domain = substr($host, strlen($scheme) + 3);
        $start_four = substr($domain, 0 , 4);
        if ($start_four == "www.") {
            return substr($domain, 4);
        }
        return $domain;
    }
    /**
     * Get the path portion of a url if present; if not return null
     *
     * @param string $url the url to parse
     * @param bool $with_query_string (whether to also include the query
     *     string at the end of the path)
     * @return the host portion of the url if present; null otherwise
     */
    public static function getPath($url, $with_query_string = false)
    {
        $url_parts = @parse_url($url);
        if (!isset($url_parts['path'])) {
            return null;
        }
        // windows hack
        $url_parts['path'] = str_replace("\/", "/", $url_parts['path']);
        $path = $url_parts['path'];
        $len = strlen($url);
        if ($len < 1) {
            return null;
        }
        if ($with_query_string && isset($url_parts['query'])) {
            $path .= "?".$url_parts['query'];
        } else if ($with_query_string && $url[$len - 1] == "?") {
            $path .= "?"; //handle blank query string case
        }
        return $path;
    }
    /**
     * Returns as a two element array the host and path of a url
     *
     * @param string $url initial url to get host and path of
     * @param bool $with_login_and_port controls whether the host should
     *     should contain login and port info
     * @param bool $with_query_string says whether the path should contain
     *     the query string as well
     * @return array host and the path as a pair
     */
    public static function getHostAndPath($url, $with_login_and_port = true,
        $with_query_string = false)
    {
        $url_parts = @parse_url($url);
        $path = (isset($url_parts['path'])) ? $url_parts['path'] : false;
        if (!isset($url_parts['scheme']) ) {return [false, false];}
        $host_url = $url_parts['scheme'].'://';
        //handles common typo http:/yahoo.com rather than http://yahoo.com
        if (!isset($url_parts['host'])) {
            if ($path) {
                $url_parts = @parse_url($url_parts['scheme'].":/".
                    $path);
                if (!isset($url_parts['host'])) {
                    return [false, false];
                }
                $path = (isset($url_parts['path'])) ? $url_parts['path'] :false;
            } else {
                return [false, false];
            }
        }
        if ($with_login_and_port &&
            isset($url_parts['user']) && isset($url_parts['pass'])) {
            $host_url .= $url_parts['user'].":".$url_parts['pass']."@";
        }
        if (strlen($url_parts['host']) <= 0) { return [false, false]; }
        $host_url .= $url_parts['host'];
        if ($with_login_and_port && isset($url_parts['port'])) {
            $host_url .= ":".$url_parts['port'];
        }
        if (!$path) {
            return [$host_url, false];
        }
        // windows hack
        $path = str_replace("\/", "/", $path);
        $len = strlen($url);
        if ($len < 1) {
            return [$host_url, false];
        }
        if ($with_query_string && isset($url_parts['query'])) {
            $path .= "?".$url_parts['query'];
        } else if ($with_query_string && $url[$len - 1] == "?") {
            $path .= "?"; //handle blank query string case
        }
        return [$host_url, $path];
    }
    /**
     * Gets an array of prefix urls from a given url. Each prefix contains at
     * least the the hostname of the the start url
     *
     * http://host.com/b/c/ would yield http://host.com/ , http://host.com/b,
     * http://host.com/b/, http://host.com/b/c, http://host.com/b/c/
     *
     * @param string $url the url to extract prefixes from
     * @return array the array of url prefixes
     */
    public static function getHostPaths($url)
    {
        $host_paths = [$url];
        list($host, $path) = self::getHostAndPath($url);
        if (!$host) {return $host_paths;}
        $host_paths[] = $host;
        $path_parts = explode("/", $path);
        $url = $host;
        foreach ($path_parts as $part) {
         if ($part != "") {
            $url .="/$part";
            $host_paths[] = $url;
            }
            $host_paths[] = $url."/";
        }
        $host_paths = array_unique($host_paths);
        return $host_paths;
    }
    /**
     * Gets the subdomains of the host portion of a url. So
     *
     * http://a.b.c/d/f/
     * will return a.b.c, .a.b.c, b.c, .b.c, c, .c
     *
     * @param string $url the url to extract prefixes from
     * @return array the array of url prefixes
     */
    public static function getHostSubdomains($url)
    {
        $subdomains = [];
        $url_parts = @parse_url($url);
        if (!isset($url_parts['host']) || strlen($url_parts['host']) <= 0) {
            return $subdomains;
        }
        $host = $url_parts['host'];
        $host_parts = explode(".", $host);
        $num_parts = count($host_parts);
        $domain = "";
        for ($i = $num_parts - 1; $i >= 0 ; $i--) {
            $domain = $host_parts[$i].$domain;
            $subdomains[] = $domain;
            $domain = ".$domain";
            $subdomains[] = $domain;
        }
        return $subdomains;
    }
    /**
     * Checks if $path matches against any of the Robots.txt style regex
     * paths in $paths
     *
     * @param string $path a path component of a url
     * @param array $robot_paths in format of robots.txt regex paths
     * @return bool whether it is a member or not
     */
    public static function isPathMemberRegexPaths($path, $robot_paths)
    {
        $is_member = false;
        $len = strlen($path);
        foreach ($robot_paths as $robot_path) {
            $rlen = strlen($robot_path);
            if ($rlen == 0) {
                continue;
            }
            $end_match = false;
            $end = ($robot_path[$rlen - 1] == "$") ? 1 : 0;
            $path_string = substr($robot_path, 0, $rlen - $end);
            $path_parts = explode("*", $path_string);
            $offset = -1;
            $old_part_len = 0;
            $is_match = true;
            foreach ($path_parts as $part) {
                $offset += 1 + $old_part_len;
                $old_part_len = strlen($part);
                if ($part == "") {
                    continue;
                } else if ($offset >= $len) {
                    $is_match = false;
                    break;
                }
                $new_offset = stripos($path, $part, $offset);
                if ($new_offset === false ||
                    ($offset == 0 && $new_offset > 0)) {
                    $is_match = false;
                    break;
                }
                $offset = $new_offset;
            }
            if ($is_match) {
                if ($end == 0 || strlen($part) + $offset == $len) {
                    $is_member = true;
                }
            }
        }
        return $is_member;
    }
    /**
     * Given a url, extracts the words in the host part of the url
     * provided the url does not have a path part more than / .
     * Ignores a leading www and also ignore tld.
     *
     * For example, "http://www.yahoo.com/" returns " yahoo "
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string space separated words extracted.
     *
     */
    public static function getWordsInHostUrl($url)
    {
        $words = [];
        $url_parts = @parse_url($url);
        if (!isset($url_parts['host']) || strlen($url_parts['host']) <= 0
            || (isset($url_parts['path']) && $url_parts['path'] != "/")||
            isset($url_parts['query'])
            || isset($url_parts['fragment'])) {
            // if no host or has a query string bail
            return "";
        }
        $host = $url_parts['host'];
        $host_parts = preg_split("/\.|\-/", $host);
        if (count($host_parts) <= 1) {
            return "";
        }
        array_pop($host_parts); // get rid of tld
        if (stristr($host_parts[0], "www")) {
            array_shift($host_parts);
        }
        $words = array_merge($words, $host_parts);
        $word_string = " " . implode(" ", $words). " ";
        return $word_string;
    }
    /**
     * Given a url, extracts the words in the last path part of the url
     * For example,
     * http://us3.php.net/manual/en/function.array-filter.php
     * yields " function array filter "
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string space separated words extracted.
     *
     */
    public static function getWordsLastPathPartUrl($url)
    {
        $words = [];
        $url_parts = @parse_url($url);
        $path_info = (empty($url_parts['path'])) ? [] :
            @pathinfo($url_parts['path']);
        $path = "";
        if (isset($path_info['dirname'])) {
            $path .= $path_info['dirname']."/";
        }
        if (isset($path_info['filename'])) {
            $path .= $path_info['filename'];
        }
        $pre_path_parts = explode("/", $path);
        $count = count($pre_path_parts);
        if ($count < 1 ) {
            return "";
        }
        $last_path = $pre_path_parts[$count - 1];
        $path_parts = preg_split("/(_|-|\ |\+|\.)/", urldecode($last_path));
        foreach ($path_parts as $part) {
            if (strlen($part) > 0 ) {
                $words[] = $part;
            }
        }
        $word_string = " ".implode(" ", $words). " ";
        return $word_string;
    }
    /**
     * Given a url, makes a guess at the file type of the file it points to
     *
     * @param string $url a url to figure out the file type for
     * @param string $default default type to be returned in the case that
     *      document type cannot be determined from the url, defaults to
     *      html
     *
     * @return string the guessed file type.
     *
     */
    public static function getDocumentType($url, $default = "html")
    {
        if ($url == "") {
            return $default;
        }
        $url_parts = @parse_url(str_replace("%3F", "?", urlencode($url)));
        if (!isset($url_parts['path'])) {
            return $default;
        } else if ($url[strlen($url)-1] == "/" || $url[strlen($url)-1] == "\\"){
            return $default;
        } else {
            $path_parts = pathinfo($url_parts['path']);
            if (empty($path_parts["extension"]) ) {
             return $default;
            }
            return urldecode($path_parts["extension"]);
        }
    }
    /**
     * Gets the filename portion of a url if present;
     * otherwise returns "Some File"
     *
     * @param string $url a url to parse
     * @return string the filename portion of this url
     */
    public static function getDocumentFilename($url)
    {
        $url_parts = @parse_url($url);
        if (!isset($url_parts['path'])) {
            return "html"; //we default to html
        } else {
            $path_parts = pathinfo($url_parts['path']);
            if (empty($path_parts["filename"]) ) {
                return "Some File";
            }
            return urldecode($path_parts["filename"]);
        }
    }
    /**
     * Get the query string component of a url
     *
     * @param string $url  a url to get the query string out of
     * @return string the query string if present; null otherwise
     */
    public static function getQuery($url)
    {
        $url_parts = @parse_url($url);
        if (isset($url_parts['query'])) {
            $out = $url_parts['query'];
        } else {
            $out = null;
        }
        return $out;
    }
    /**
     * Get the url fragment string component of a url
     *
     * @param string $url  a url to get the url fragment string out of
     * @return string the url fragment string if present; null otherwise
     */
    public static function getFragment($url)
    {
        $url_parts = @parse_url($url);
        if (isset($url_parts['fragment'])) {
            $out = $url_parts['fragment'];
        } else {
            $out = null;
        }
        return $out;
    }
    /**
     * Given a $link that was obtained from a website $site, returns
     * a complete URL for that link.
     * For example, the $link
     * some_dir/test.html
     * on the $site
     * http://www.somewhere.com/bob
     * would yield the complete url
     * http://www.somewhere.com/bob/some_dir/test.html
     *
     * @param string $link  a relative or complete url
     * @param string $site  a base url
     * @param string $no_fragment if false then if the url had a fragment
     *     (#link_within_page) then the fragement will be included
     *
     * @return string a complete url based on these two pieces of information
     *
     */
    public static function canonicalLink($link, $site, $no_fragment = true)
    {
        $link = trim($link);
        if (!self::isSchemeCrawlable($link)) {return null;}
        if (isset($link[0]) &&
            $link[0] == "/" && isset($link[1]) && $link[1] == "/") {
            $http = ($site[4] == 's') ? "https:" : "http:";
            $link = $http . $link;
        }
        if (self::hasHostUrl($link)) {
            list($host, $path) = self::getHostAndPath($link);
            $query = self::getQuery($link);
            $fragment = self::getFragment($link);
        } else {
            $host = self::getHost($site);
            if ($link !=null && $link[0] == "/") {
                $path = $link;
            } else {
                $site_path = self::getPath($site);
                $site_path_parts = pathinfo($site_path);
                if (isset($site_path_parts['dirname'])) {
                    $pre_path = $site_path_parts['dirname'];
                } else {
                    $pre_path = "";
                }
                if (isset($site_path_parts['basename']) &&
                    !isset($site_path_parts['extension'])) {
                    $pre_path .= "/" . $site_path_parts['basename'];
                }
                if (strlen($link) > 0) {
                     $pre_path = ($link[0] != "#") ? $pre_path . "/" . $link :
                        $pre_path . $link;
                }
                $path = $pre_path;
                $path2 = $path;
                do {
                    $path = $path2;
                    $path2 = str_replace("//", "/", $path);
                } while($path != $path2);
                $path = self::getPath($path);
                $so_far_link = $host . $pre_path;
                $query = self::getQuery($so_far_link);
                $fragment = self::getFragment($so_far_link);
            }
        }
        // take a stab at paths containing ..
        $path = preg_replace('/(\/\w+\/\.\.\/)+/', "/", $path);
        // if still has .. give up
        if (stristr($path, "../"))
        {
            return null;
        }
        // handle paths with dot in it
        $path = preg_replace('/(\.\/)+/', "", $path);
        $path = str_replace(" ", "%20", $path);
        $link_path_parts = pathinfo($path);
        $path2 = $path;
        do {
            $path = $path2;
            $path2 = str_replace("//", "/", $path);
        } while($path != $path2);
        $path = str_replace("/./", "/", $path);
        if ($path == "." || substr($path, -2) == "/.") {
            $path = "/";
        }
        if ($path == "" && !(isset($fragment) && $fragment !== "")) {
            $path = "/";
        }
        $url = $host.$path;
        if (isset($query) && $query !== "") {
            $url .= "?".$query;
        }
        if (!empty($fragment) && !$no_fragment) {
            $url .= "#".$fragment;
        }
        return $url;
    }
    /**
     * Checks if a url has a repeated set of subdirectories, and if the number
     * of repeats occurs more than some threshold number of times
     *
     * A pattern like bob/.../bob counts as own reptition.
     * bob/.../alice/.../bob/.../alice would count as two (... should be read
     * as ellipsis, not a directory name).If the threshold is three and there
     * are at least three repeated mathes this function return true; it returns
     * false otherwise.
     *
     * @param string $url the url to check
     * @param int $repeat_threshold the number of repeats of a subdir name to
     *     trigger a true response
     * @return bool whether a repeated subdirectory name with more matches than
     *     the threshold was found
     *
     */
    public static function checkRecursiveUrl($url, $repeat_threshold = 3)
    {
        if (!is_string($url)) {
            return false;
        }
        $url_parts = explode("/", $url);
        $count= count($url_parts);
        $flag = 0;
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($url_parts[$j] == $url_parts[$i]) {
                    $flag++;
                }
            }
        }
        if ($flag > $repeat_threshold) {
            return true;
        }
        return false;
    }
    /**
     * Checks if a $url is on localhost
     *
     * @param string $url the url to check
     * @return bool whether or not it is on localhost
     */
    public static function isLocalhostUrl($url)
    {
        static $localhosts = ["localhost", "127.0.0.1", "::1"];
        static $uncomputed = true;
        $host = UrlParser::getHost($url, false);
        if ($uncomputed) {
            if (isset($_SERVER["SERVER_NAME"])) {
                $localhosts[] = $_SERVER["SERVER_NAME"];
                $localhosts[] = gethostbyname($_SERVER["SERVER_NAME"]);
            }
            if (isset($_SERVER["SERVER_ADDR"])) {
                $localhosts[] = $_SERVER["SERVER_ADDR"];
            }
            $uncomputed = false;
        }
        foreach ($localhosts as $localhost) {
            if (stristr($host, $localhost)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Checks if the url belongs to one of the sites listed in site_array
     * Sites can be either given in the form domain:host or
     * in the form of a url in which case it is check that the site url
     * is a substring of the passed url.
     *
     * @param string $url url to check
     * @param array $site_array sites to check against
     * @param string $name identifier to store $site_array with in this
     *     public function's cache
     * @param bool $return_rule whether when a match is found to return true or
     *     to return the matching site rule
     * @return mixed whether the url belongs to one of the sites
     */
    public static function urlMemberSiteArray($url, $site_array,
        $name, $return_rule = false)
    {
        static $cache = [];
        if (!is_array($site_array)) {
            return false;
        }
        if (!isset($cache[$name])) {
            if (count($cache) > 2 * C\MIN_RESULTS_TO_GROUP) {
                $cache = [];
            }
            $i = 0;
            $cache[$name]["domains"] = [];
            $cache[$name]["regexes"] = [];
            $cache[$name]["hosts"] = [];
            $cache[$name]["paths"] = [];
            $cache[$name]["sites"] = [];
            foreach ($site_array as $site) {
                if (strncmp($site, "domain:", 7) == 0) {
                    $cache[$name]["domains"][] = substr($site, 7);
                    continue;
                }
                if (strncmp($site, "regex:", 6) == 0) {
                    $cache[$name]["regexes"][] = substr($site, 6);
                    continue;
                }
                list($site_host, $site_path) =
                    UrlParser::getHostAndPath($site, true, true);
                $cache[$name]["hosts"][] = $site_host;
                $cache[$name]["paths"][] = $site_path;
                $cache[$name]["sites"][] = $site;
                $i++;
            }
            $cache[$name]["domains"] = array_values(array_unique(
                $cache[$name]["domains"]));
        }
        $flag = false;
        $domains = & $cache[$name]["domains"];
        $regexes = & $cache[$name]["regexes"];
        $hosts = & $cache[$name]["hosts"];
        $paths = & $cache[$name]["paths"];
        $sites = & $cache[$name]["sites"];
        foreach ($regexes as $regex) {
            if (preg_match($regex, $url)) {
                if ($return_rule) {
                    return "regex:$regex";
                }
                return true;
            }
        }
        list($host, $path) = UrlParser::getHostAndPath($url, true, true);
        foreach ($domains as $domain) {
            $pos = strrpos($host, $domain);
            if ($pos !== false &&
                $pos + strlen($domain) == strlen($host) ) {
                if ($return_rule) {
                    return "domain:$domain";
                }
                return true;
            }
        }
        $count = count($sites);
        for ($i = 0; $i < $count; $i++) {
            $flag = UrlParser::isPathMemberRegexPaths($host, [$hosts[$i]]);
            if (!$flag) {
                continue;
            }
            $flag = UrlParser::isPathMemberRegexPaths($path, [$paths[$i]]);
            if ($flag) {
                break;
            }
        }
        if ($return_rule && $flag) {
            $flag = $sites[$i];
        }
        return $flag;
    }
    /**
     * Used to delete links from array of links $links based on whether
     * they are the same as the site they came from (or otherwise judged
     * irrelevant)
     *
     * @param array $links pairs of the form $link =>$link_info
     * @param string $parent_url a site that the links were found on
     * @return array just those links which pass the relevancy test
     */
    public static function cleanRedundantLinks($links, $parent_url)
    {
        $out_links = [];
        foreach ($links as $url => $url_info) {
            //ignore links back to oneself (too easy to spam)
            if (strcmp($parent_url, $url) != 0) {
                $out_links[$url] = $url_info;
            }
        }
        return $out_links;
    }
    /**
     * Prunes a list of url => text pairs down to max_link many pairs
     * by choosing those whose text has the most information. Information
     * crudely measured by the effective number of terms in the text.
     * To compute this, we count the number of terms by splitting on white
     * space. We then multiply this by the ratio of the compressed length
     * of the text divided by its uncompressed length.
     *
     * @param array $links list of pairs $url=>$text
     * @param int $max_links maximum number of links from $links to return
     * @return array $out_links extracted from $links accodring to the
     *     description above.
     */
    public static function pruneLinks($links, $max_links =
        C\MAX_LINKS_TO_EXTRACT)
    {
        $consonants = "bcdfghjklmnpqrstvyz";
        $vowels = "aeiouy";
        $digit_consonants = "([$consonants]|\d)";
        $digit_vowels = "([$vowels]|\d)";
        if ($max_links <= 0 || count($links) <= $max_links) {
            return $links;
        }
        $info_link = [];
        // choose the $max_links many pages with most info (crude)
        foreach ($links as $url => $text) {
            $text = (is_string($text)) ? $text : "";
            $terms = preg_split("/\s+|\-|\_|\~/", $text);
            $num_terms = count($terms);
            $len_text = strlen($text) + 1;
            $compressed_len = strlen(gzcompress($text)) + 1;
            $effective_num_terms = $num_terms *
                (min($compressed_len/$len_text, 1));
            if (!isset($info_link[$url])) {
                $info_link[$url] = $effective_num_terms;
            } else {
                $info_link[$url] += $effective_num_terms;
            }
        }
        arsort($info_link);
        $link_urls = array_keys(array_slice($info_link, 0,
            $max_links));
        $out_links = [];
        foreach ($link_urls as $url) {
            $out_links[$url] = $links[$url];
        }
        return $out_links;
    }
    /**
     * Returns the number of links in the array $links which
     * which share the same company level domain (cld) as $url
     * For www.yahoo.com the cld is yahoo.com, for
     * www.theregister.co.uk it is theregister.co.uk. It is
     * similar for organizations. It also tries to determine
     * if a $url is potentially part of a link farm. To do this
     * it checks (1) if the number of distinct, not sub-locale domains with a
     * shared company domain is high > $threshold/2. This suggest a lot of bogus
     * outgoing links  that are all under one company's control. For example, a site
     * www.foo.com linking to md5_hash.foo.com for many different md5 hashes.
     * If this is detected this method returns -1. This method also returns -1
     * if (2) there seem to be lots of links ($threshold) from the current
     * domain to a single domain that shares the same company domain. This
     * might indicate  a domain md5_hash.foo.com with lots of links to a domain
     * www.foo.com
     *
     * @param string $url the url to compare against $links
     * @param array $links an array of urls
     * @param int $threshold number above which if either situation (1) or (2)
     *  above happens then deem site spam
     * @return int the number of times $url shares the cld with a
     *     link in $links. If thinks part of link farm returns -1
     */
    public static function countCompanyLevelDomainsInCommonDetectFarm($url,
        $links, $threshold = 200)
    {
        $cld = UrlParser::getCompanyLevelDomain($url);
        $cnt = 0;
        $dom_cnt = 0;
        $host = parse_url($url, \PHP_URL_HOST);
        $seen_hosts = [];
        foreach ($links as $link_url) {
            $link_cld = UrlParser::getCompanyLevelDomain($link_url);
            $link_host = parse_url($link_url, \PHP_URL_HOST);
            list($host_start, ) = explode(".", $link_host);
            // count number of shared company domains
            if (strcmp($cld, $link_cld) == 0) {
                $cnt++;
                /* count distinct subdomains which are not likely to be locale
                    sub-domain
                 */
                if ($link_host != $host  &&
                    strlen($host_start) > 3) {
                    if (empty($seen_hosts[$link_host])) {
                        $dom_cnt++;
                        $seen_hosts[$link_host] = 0;
                    }
                    $seen_hosts[$link_host]++;
                }
            }
        }
        $maximal_host = (empty($seen_hosts)) ? 0 : max($seen_hosts);
        if (($dom_cnt > $threshold/2) || ($maximal_host > $threshold)) {
            if (!in_array($cld, ['wikipedia.org', 'wiktionary.org',
                'wikimedia.org', 'wikiquote.org', 'wikiversity.org',
                'wikimediafoundation.org', 'wikinews.org'])) {
                return -1;
            }
        }
        return $cnt;
    }
    /**
     * Calculates the company level domain for the given url
     *
     * For www.yahoo.com the cld is yahoo.com, for
     * www.theregister.co.uk it is theregister.co.uk. It is
     * similar for organizations.
     *
     * @param string $url url to determine cld for
     * @return string the cld of $url
     */
    public static function getCompanyLevelDomain($url)
    {
        $subdomains = UrlParser::getHostSubdomains($url);
        if (!isset($subdomains[0]) || !isset($subdomains[2])) {
            return "";
        }
        /*
            if $url is www.yahoo.com
                $subdomains[0] == com, $subdomains[1] == .com,
                $subdomains[2] == yahoo.com,$subdomains[3] == .yahoo.com
            etc.
         */
        if (strlen($subdomains[0]) == 2 && strlen($subdomains[2]) <= 6
            && isset($subdomains[4])) {
            return $subdomains[4];
        }
        return $subdomains[2];
    }
    /**
     * Checks if a url's host is either a company level domain (a cld) or
     * is of the form www.cld or has as its cld a domain that is in one of
     * the supplied BloomFilterFile objects
     * @param string $url url to check if in above form
     * @param array $filters array of BloomFilterFile objects
     * @return bool whether or not url has above form
     */
    public static function cullByDomainFilter($url, $filters)
    {
        $url = mb_strtolower($url);
        $cld = self::getCompanyLevelDomain($url);
        $host = parse_url($url, \PHP_URL_HOST);
        $host_less_cld = mb_strtolower(
            preg_replace('/' . preg_quote($cld) . '$/u', '', $host));
        if (in_array($host_less_cld, ["", "www."])) {
            return false;
        }
        foreach ($filters as $filter) {
            if ($filter->contains($cld)) {
                return false;
            }
        }
        return true;
    }
    /**
     * Guess mime type based on extension of the file
     *
     * @param string $file_name name of the file
     * @param string $default what mime type to return if mime type
     *      couldn't be determined
     * @return string $mime_type for the given file name
     */
    public static function guessMimeTypeFromFileName($file_name,
        $default = 'text/plain')
    {
        $mime_type_map = [
            "bmp" => 'image/bmp',
            "doc" => 'application/msword',
            "epub" => 'application/epub+zip',
            "gif" => 'image/gif',
            "asp" => 'text/asp',
            "aspx" => 'text/asp',
            'cgi' => 'text/html',
            "cfm" => 'text/html',
            "cfml" => 'text/html',
            "do" => 'text/html',
            "htm" => 'text/html',
            "html" => 'text/html',
            "jsp" => 'text/html',
            "php" => 'text/html',
            "pl" => 'text/html',
            "java" => 'text/java',
            "py" => 'text/py',
            "shtml" => 'text/html',
            "jpg" => 'image/jpeg',
            "jpeg" => 'image/jpeg',
            "pdf" => 'application/pdf',
            "png" => 'image/png',
            "ppt" => 'application/vnd.ms-powerpoint',
            "pptx" => 'application/vnd.openxmlformats-officedocument.'.
                'presentationml.presentation',
            "rss" => 'application/rss+xml',
            "rtf" => 'text/rtf',
            "svg" => 'image/svg+xml',
            "csv" => 'text/csv',
            "tab" => 'text/tab-separated-values',
            "tsv" => 'text/x-java-source',
            "txt" => 'text/plain',
            "xlsx" => 'application/vnd.openxmlformats-officedocument.'.
                'spreadsheetml.sheet',
            "xml" => 'text/xml',
            "js" => 'text/plain',
            "c" => 'text/plain',
            "cc" => 'text/plain',
            "cs" => 'text/plain'
        ];
        $extension = UrlParser::getDocumentType($file_name);
        if (isset($mime_type_map[$extension])) {
            $mime_type = $mime_type_map[$extension];
        } else {
            $mime_type = $default;
        }
        return $mime_type;
    }
    /**
     * Extracts text from a url. Similar to @see getWordsInHostUrl
     * and  @see getWordsLastPathPartUrl except operates on whole
     * url. This function is mainly used on link documents, the
     * previous two are mainly used with standard documents
     *
     * @param string $url to find text that might say what link is about
     * @return string heuristically derived text.
     */
     public static function extractTextFromUrl($url)
     {
        $text = preg_replace("/(\:\d*)|\/|\-|\#/", " ", strip_tags($url));
        $text = preg_replace("/www|https?|".
            "\.(com|gov|org|mil|edu|net|biz|io|ca|au|uk|to|us|ly|".
            "fr|gk|dk|es|pt|vn|cn|tw|in|jp|is|de|id|it|kp|kr|th|tr|sg|".
            "uy|ni|mx|ar|ye|ae|za|zw|be|ir|pr|py|pa|il|sv|bo|at|ma|qc|".
            "dr|ec|gt|pl|vi|s?html|php\d?|cgi|asp|jsp|gif|png|jpg|mp4|".
            "mp3|txt|ftp)/", "", $text);
        $text = preg_replace("/\.|\%[a-f0-9]+/i", " ", $text);
        $text =  preg_replace("/\s+/", " ", $text);
        return $text;
     }
}
