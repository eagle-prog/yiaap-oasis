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
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;

/**
 * This class manages the editing of search verticals. This includes
 * allowing one to specify a search result should be filtered from
 * the results of a query, it also includes alterning the title and description
 * of a result from how it is stored in a particular index and it finally
 * includes creating, updating, deleting knowledge wiki results
 * To handle these activities this class leverages the existing group
 * wiki system of Yioop. Edited and filtered search results correspond
 * to group feed entries in a Search Group. Edited knowledge wiki entries
 * correspond to wiki entires in the Search Group.
 *
 * @author Chris Pollett
 */
class SearchverticalsModel extends GroupModel
{
    /**
     * Used to hold an in_memory cache of what search results are to be
     * filtered
     *
     * @var array
     */
    public $memory_filter = [];
    /**
     * Used to hold an in memory copy of the timestamp of the last time
     * a search result was altered
     *
     * @var int
     */
    public $last_change = null;
    /**
     * Check if a URL is supposed to be filtered from search results.
     *
     * @param string $url to see if filtered
     * @param bool $compute_hash_key flag to control hashing before computing
     *     GROUP_ITEM lookup. Sometimes $url might be already the hash of
     *      a URL to check if filtered
     * @return bool whether the url should be filtered from search results or
     *      not
     */
    public function isFiltered($url, $compute_hash_key = false)
    {
        if (!empty($this->query_filter)) {
            if (in_array($url, $this->query_filter)) {
                return false;
            }
        }
        if ($compute_hash_key) {
            $host = UrlParser::getHost($url);
        } else {
            $host = $url;
        }
        if (isset($this->memory_filter[$host])) {
            return $this->memory_filter[$host];
        } else {
            if (count($this->memory_filter) > 2 * C\MIN_RESULTS_TO_GROUP) {
                $this->memory_filter = [];
            }
            list($parent_id, $user_id) = $this->hashIntPair($host,
                $compute_hash_key);
            $db = $this->db;
            $sql = "SELECT * FROM GROUP_ITEM WHERE PARENT_ID=? AND " .
                "USER_ID = ? AND GROUP_ID = " . C\SEARCH_GROUP_ID .
                " AND TYPE = ". C\SEARCH_FILTER_GROUP_ITEM . " " .
                $db->limitOffset(1);
            $result = $db->execute($sql, [$parent_id, $user_id]);
            $this->memory_filter[$host] = true;
            if (!$result) {
                $this->memory_filter[$host] = false;
            } else {
                $row = $db->fetchArray($result);
                if (!$row) {
                    $this->memory_filter[$host] = false;
                }
            }
            return $this->memory_filter[$host];
        }
    }
    /**
     * Returns the timestamp of the last time any of the search results
     * were edited.
     *
     * @return int timestamp of last edited search results
     */
    public function lastChange()
    {
        if ($this->last_change != null) {
            return $this->last_change;
        }
        $db = $this->db;
        $sql = "SELECT MAX(PUBDATE) AS LAST_CHANGE FROM GROUP_ITEM";
        $result = $db->execute($sql);
        if (!$result) {
            return 0;
        } else {
            $row = $db->fetchArray($result);
            if (!$row) {
                return 0;
            }
        }
        $this->last_change = $row['LAST_CHANGE'];
        return $this->last_change;
    }
    /**
     * Computes a hash value as an ordered pair of ints. Used to store
     * filtered urls into the GROUP_ITEM table. In this situation,
     * the ordered pair is used later for the PARENT_ID and USER_ID in the
     * (both of which have indexes) Search group look-up.
     *
     * @param string $input to be hash to a pair of integers
     * @param bool $compute_hash flag to chheck if a crawlHash is done
     *  before converting the result to an ordered pair. In some situations
     *  the url or host of the url has already been hashed so don't want
     *  to hash it again.
     * @return array [int, int] that corresponds to the hash of the input
     *  to keep postgres happy (no unsigned ints) we make the value
     *  of this function signed
     */
    public function hashIntPair($input, $compute_hash = true)
    {
        if ($compute_hash) {
            $hash = substr(L\crawlHash($input . "/", true), 1);
        } else {
            $hash = $input;
        }
        $front = abs(L\unpackInt(substr($hash, 0, 4))) - 2147483648;
        $back = abs(L\unpackInt(substr($hash, 4, 4))) - 2147483648;
        return [$front, $back];
    }
    /**
     * Given a $query and a $locale_tag returns a ordered sets
     * of urls to put at the top of the search results for that query
     * if such a map has been defined.
     *
     * @param string $query user supplied query
     * @param string $locale_tag language that the lookup of urls should
     *  be done for
     * @return array of urls that correspond to the query
     */
    public function getQueryMap($query, $locale_tag)
    {
        $db = $this->db;
        list($parent_id, $user_id) =  $this->hashIntPair("$query $locale_tag");
        $sql = "SELECT URL FROM GROUP_ITEM " .
            "WHERE GROUP_ID = " . C\SEARCH_GROUP_ID . " AND PARENT_ID=? AND " .
            "USER_ID = ? AND TYPE= '" . C\QUERY_MAP_GROUP_ITEM . "' ".
            "ORDER BY TITLE";
        $result = $db->execute($sql, [$parent_id, $user_id]);
        if (!$result) {
            return [];
        }
        $map_urls = [];
        if ($result) {
            while ($row = $db->fetchArray($result)) {
                $map_urls[] = trim($row['URL']);
            }
        }
        return $map_urls;
    }
    /**
     * Stores a query map into the public database.
     * A query map associate a $query in a $locale_tag language to a set of
     * urls desired to be at the top of the search results.
     * @param string $query that triggers the mapping
     * @param array $map_urls urls that should appear at the top of the search
     *      results
     * @param string $locale_tag for the language the map should apply to
     */
    public function setQueryMap($query, $map_urls, $locale_tag)
    {
        $db = $this->db;
        list($parent_id, $user_id) =  $this->hashIntPair("$query $locale_tag");
        $delete_sql = "DELETE FROM GROUP_ITEM WHERE GROUP_ID = " .
            C\SEARCH_GROUP_ID . " AND PARENT_ID=? AND " .
            "USER_ID = ? AND TYPE= '" . C\QUERY_MAP_GROUP_ITEM . "'";
        $time = time();
        $db->execute($delete_sql, [$parent_id, $user_id]);
        $i = 0;
        foreach($map_urls as $url) {
            $out_num = sprintf("%'.04d", $i);
            $id = $this->addGroupItem($parent_id, C\SEARCH_GROUP_ID, $user_id,
                "$out_num", "", C\QUERY_MAP_GROUP_ITEM, $time, trim($url));
            $i++;
        }
    }
    /**
     * Get the knowledge wiki page corresponding to a search query.
     * This is used in wiki read mode for search result verticals or edit mode
     * (the wiki info is not pre-parsed) for editing the knowledge wiki page.
     *
     * @param string $query to get knowledge wiki results for
     * @param string $locale_tag the locale tag language that one want the
     *  results for
     * @param bool $edit_mode whether the wiki page should be pre-parsed
     *  suitable for display in query results) or left unparsed (suitable
     *   for editing).
     * @return array knowledge wiki page info
     */
    public function getKnowledgeWiki($query, $locale_tag, $edit_mode = false)
    {
        $db = $this->db;
        $query = mb_strtolower(str_replace("-", " ", $query));
        if ($edit_mode) {
            $row = $this->getPageInfoByName(C\SEARCH_GROUP_ID, $query,
                $locale_tag, "edit");
        } else {
            $sql = "SELECT ID, PAGE, DISCUSS_THREAD FROM GROUP_PAGE " .
                "WHERE GROUP_ID = " . C\SEARCH_GROUP_ID . " AND " .
                "TITLE = ? AND LOCALE_TAG = ? ". $db->limitOffset(0, 1);
            $result = $db->execute($sql, ["$query", $locale_tag]);
            if (!$result) {
                return false;
            }
            $row = $db->fetchArray($result);
            if (!$row) {
                return false;
            }
        }
        return $row;
    }
    /**
     * Updates the title and description text that will be presented
     * when a given url appears in search results
     * @param int $id if the url has been edited previous then the id of the
     *      group item with the edit. If this is 0/empty then a new group item
     *      for the edit is created. If -1 then deletes the entry
     * @param int $type either SEARCH_FILTER_GROUP_ITEM or
     *  SEARCH_EDIT_GROUP_ITEM
     * @param string $url to change search result for
     * @param string $title new title for search result
     * @param string $description new snippet text for search result
     * @return mixed integer id of edited/created result or if used
     *      to delete then false
     */
    function updateUrlResult($id, $type, $url, $title, $description)
    {
        $db = $this->db;
        $this->last_change = time();
        if ($type == C\SEARCH_FILTER_GROUP_ITEM) {
            $url =  UrlParser::getHost($url);
        }
        list($parent_id, $user_id) = $this->hashIntPair($url);
        if (empty($id)) {
            $id = $this->addGroupItem($parent_id, C\SEARCH_GROUP_ID, $user_id,
                $title, $description, $type, $this->last_change, $url);
        } else {
            $item = $this->getEditedPageResult($url);
            $sql = "DELETE FROM GROUP_ITEM  WHERE ID = ?";
            $db->execute($sql, [$id]);
            unset($id);
            if (!empty($item['TYPE']) &&
                $item['TYPE'] == C\SEARCH_FILTER_GROUP_ITEM) {
                $sql = "DELETE FROM GROUP_ITEM  WHERE URL = ?";
                $db->execute($sql, [$url . "/"]);
            }
            $id = $this->addGroupItem($parent_id, C\SEARCH_GROUP_ID, $user_id,
                $title, $description, $type, $this->last_change, $url);
        }
        return $id ?? false;
    }
    /**
     * Returns any edited search result associated with a url
     *
     * @param string $url url to get edit search results for
     * @return mixed either false if no edited search results or an
     *  associative array containing edited results if they exist
     */
    function getEditedPageResult($url)
    {
        $db = $this->db;
        $host = UrlParser::getHost($url);
        list($parent_id, $user_id) = $this->hashIntPair($host);
        $sql = "SELECT ID, TITLE, URL, DESCRIPTION, PUBDATE, EDIT_DATE, TYPE " .
            "AS URL_ACTION FROM GROUP_ITEM " .
            "WHERE GROUP_ID = " . C\SEARCH_GROUP_ID . " AND PARENT_ID=? AND " .
            "USER_ID = ? ". $db->limitOffset(0, 1);
        $result = $db->execute($sql, [$parent_id, $user_id]);
        if ($result) {
            $row = $db->fetchArray($result);
            if ($row && $row['URL_ACTION'] == C\SEARCH_FILTER_GROUP_ITEM) {
                return $row;
            }
        }
        list($parent_id, $user_id) = $this->hashIntPair($url);
        $result = $db->execute($sql, [$parent_id, $user_id]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        if (!$row) {
            return false;
        }
        return $row;
    }
    /**
     * Given an array page summaries, for each summary check if the
     * url corresponds to a search result that was human edited, if so,
     * replace and format it.
     *
     * @param array $results web pages summaries (these in turn are
     *     arrays!)
     * @param array $words keywords (typically what was searched on)
     * @param int $description_length length of the description
     * @return array summaries which have been snippified and bold faced
     */
    public function incorporateEditedPageResults($results, $words = null,
        $description_length = self::DEFAULT_DESCRIPTION_LENGTH)
    {
        if (isset($results['PAGES'])) {
            $pages = $results['PAGES'];
            $num_pages = count($pages);
        } else {
            $output['TOTAL_ROWS'] = 0;
            $output['PAGES'] = null;
            return;
        }
        $deleted_a_page = false;
        for ($i = 0; $i < $num_pages; $i++) {
            $page = $pages[$i];
            if (!isset($page[self::URL])) {
                unset($pages[$i]);
                $deleted_a_page = true;
                continue;
            }
            $url_parts = explode("|", $page[self::URL]);
            if (count($url_parts) > 1) {
                $url = trim($url_parts[1]);
            } else {
                $url = $page[self::URL];
            }
            if ($summary = $this->getEditedPageResult($url)) {
                $page[self::URL] = $url;
                foreach ([self::TITLE => "TITLE",
                    self::DESCRIPTION => "DESCRIPTION"] as
                    $result_field => $edit_field) {
                    if (isset($summary[$edit_field])) {
                        $page[$result_field] = $summary[$edit_field];
                    }
                }
                $page = $this->formatSinglePageResult($page, $words,
                    self::DEFAULT_DESCRIPTION_LENGTH);
                $pages[$i] = $page;
            } else if (!empty($page[self::PINNED])) {
                unset($pages[$i]);
                $deleted_a_page = true;
            }
        }
        $output['TOTAL_ROWS'] = $results['TOTAL_ROWS'];
        $output['PAGES'] = ($deleted_a_page) ? $pages : array_values($pages);
        return $output;
    }
}
