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
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\UrlParser;

/**  For crawlHash function and Yioop Project constants */
require_once __DIR__."/../library/Utility.php";
/**
 *
 * This is a base class for all models
 * in the SeekQuarry search engine. It provides
 * support functions for formatting search results
 *
 * @author Chris Pollett
 */
class Model implements CrawlConstants
{
    const SNIPPET_TITLE_LENGTH = 20;
    const MAX_SNIPPET_TITLE_LENGTH = 20;
    const SNIPPET_LENGTH_LEFT = 20;
    const SNIPPET_LENGTH_RIGHT = 40;
    const MIN_SNIPPET_LENGTH = 100;
    /**
     * Default maximum character length of a search summary
     */
    const DEFAULT_DESCRIPTION_LENGTH = 150;
    /** Reference to a DatasourceManager
     * @var object
     */
    public $db;
    /** Name of the search engine database
     * @var string
     */
    public $db_name;
    /** Reference to a private DatasourceManager
     * @var object
     */
    public $private_db;
    /** Name of the private search engine database
     * @var string
     */
    public $private_db_name;
    /**
     * Associative array of page summaries which might be used to
     * override default page summaries if set.
     * @var array
     */
    public $edited_page_summaries = null;
    /**
     * These fields if present in $search_array (used by @see getRows() ),
     * but with value "-1", will be skipped as part of the where clause
     * but will be used for order by clause
     * @var array
     */
    public $any_fields = [];
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * @var array
     */
    public $search_table_column_map = [];
    /** Reference to a WebSite object in use to serve pages (if any)
     * @var object
     */
    public $web_site;
    /**
     * Cache object to be used if we are doing caching
     * @var object
     */
    public static $cache;
    /**
     * Sets up the database manager that will be used and name of the search
     * engine database
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     * @param WebSite an optional object that might be used to serve webpages
     *      when Yioop run in CLI mode. This object has fileGetContents and
     *      filePutContents methods which allow RAM caching of files.
     */
    public function __construct($db_name = C\DB_NAME, $connect = true,
        $web_site = null)
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $private_db_class = C\NS_DATASOURCES . ucfirst(C\PRIVATE_DBMS).
            "Manager";
        $this->db = new $db_class();
        $this->private_db = new $private_db_class();
        if ($connect) {
            $this->db->connect();
            $this->private_db->connect(C\PRIVATE_DB_HOST, C\PRIVATE_DB_USER,
                C\PRIVATE_DB_PASSWORD, C\PRIVATE_DB_NAME);
        }
        $this->db_name = $db_name;
        $this->private_db_name = C\PRIVATE_DB_NAME;
        $this->web_site = $web_site;
    }
    /**
     * Either a wrapper for file_get_contents, or if a WebSite object is being
     * used to serve pages, it reads it in using blocking I/O
     * file_get_contents() and caches it before return its string contents.
     * Note this function assumes that only the web server is performing I/O
     * with this file. filemtime() can be used to see if a file on disk has been
     * changed and then you can use $force_read = true below to force re-
     * reading the file into the cache
     *
     * @param string $filename name of file to get contents of
     * @param bool $force_read whether to force the file to be read from
     *      presistent storage rather than the cache
     * @return string contents of the file given by $filename
     */
    public function fileGetContents($filename, $force_read = false)
    {
        if (!empty($this->web_site)) {
            return $this->web_site->fileGetContents($filename, $force_read);
        }
        return file_get_contents($filename);
    }
    /**
     * Either a wrapper for file_put_contents, or if a WebSite object is being
     * used to serve pages, writes $data to the persistent file with name
     * $filename. Saves a copy in the RAM cache if there is a copy already
     * there.
     *
     * @param string $filename name of file to write to persistent storages
     * @param string $data string of data to store in file
     */
    public function filePutContents($filename, $data)
    {
        if (!empty($this->web_site)) {
            return $this->web_site->filePutContents($filename, $data);
        }
        return file_put_contents($filename, $data);
    }
    /**
     * Creates a  directory and sets it to world permission if it doesn't
     * aleady exist
     *
     * @param string $directory name of directory to create
     * @return int -1 on failure, 0 if already existed, 1 if created
     */
    public function createIfNecessaryDirectory($directory)
    {
        if (file_exists($directory)) {
            return 0;
        } else {
            set_error_handler(null);
            @mkdir($directory);
            @chmod($directory, 0777);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        }
        if (file_exists($directory)) {
            return 1;
        }
        return -1;
    }
    /**
     * Given a page summary, extracts snippets which
     * are related to a set of search words. For each snippet, bold faces the
     * search terms, and then creates a new summary array.
     *
     * @param array $page a single search result summary
     * @param array $words keywords (typically what was searched on)
     * @param int $description_length length of the description
     * @return array $page which has been snippified and bold faced
     */
    public function formatSinglePageResult($page, $words = null,
        $description_length = self::DEFAULT_DESCRIPTION_LENGTH)
    {
        if (empty($page[self::TITLE])) {
            $page[self::TITLE] = "";
        }
        $page[self::TITLE] = strip_tags($page[self::TITLE]);
        $page[self::DESCRIPTION] = strip_tags(
            preg_replace("/\<\s+([a-zA-Z])/", '<$1',
            $page[self::DESCRIPTION]));
        if (strlen($page[self::TITLE]) == 0) {
            $offset = min(mb_strlen($page[self::DESCRIPTION]),
                self::SNIPPET_TITLE_LENGTH);
            $end_title = mb_strpos($page[self::DESCRIPTION], " ", $offset);
            $ellipsis = "";
            if ($end_title > self::SNIPPET_TITLE_LENGTH) {
                $ellipsis = "...";
                if ($end_title > self::MAX_SNIPPET_TITLE_LENGTH) {
                    $end_title = self::MAX_SNIPPET_TITLE_LENGTH;
                }
            }
            $page[self::TITLE] = mb_substr($page[self::DESCRIPTION], 0,
                $end_title) . $ellipsis;
            //still no text revert to url
            if (strlen($page[self::TITLE]) == 0 &&
                isset($page[self::URL])) {
                $page[self::TITLE] = $page[self::URL];
            }
        }
        // do a little cleaning on text
        if ($words != null) {
            $page[self::TITLE] =
                $this->boldKeywords($page[self::TITLE], $words);
            if (!isset($page[self::IS_FEED])) {
                $page[self::DESCRIPTION] =
                    $this->getSnippets($page[self::DESCRIPTION],
                    $words, $description_length);
            }
            $page[self::DESCRIPTION] =
                $this->boldKeywords($page[self::DESCRIPTION], $words);
        } else {
            $page[self::DESCRIPTION] = mb_substr($page[self::DESCRIPTION],
                0, $description_length);
            $test_snippet = preg_replace('/[^\s]+$/u', "",
                $page[self::DESCRIPTION]);
            if(!empty($test_snippet)) {
                $page[self::DESCRIPTION] = $test_snippet;
            }
        }
        $page[self::TITLE] = trim($page[self::TITLE], " .");
        $pre_description = preg_replace("/\p{C}+|^[^\p{L}]+/u", "",
            $page[self::DESCRIPTION]);
        $page[self::DESCRIPTION] = (substr($pre_description, 0, 2) == "b>") ?
            "<" . $pre_description : $pre_description;
        return $page;
    }
    /**
     * Given a string, extracts a snippets of text related to a given set of
     * key words. For a given word a snippet is a window of characters to its
     * left and right that is less than a maximum total number of characters.
     * There is also a rule that a snippet should avoid ending in the middle of
     * a word
     *
     * @param string $text haystack to extract snippet from
     * @param array $words keywords used to make look in haystack
     * @param string $description_length length of the description desired
     * @return string a concatenation of the extracted snippets of each word
     */
    public function getSnippets($text, $words, $description_length)
    {
        static $search_words = [];
        static $last_words = "";
        static $word_regex = "";
        if (mb_strlen($text) < $description_length) {
            return $text;
        }
        if (empty($words)) {
            $snippet_string = mb_substr($text, 0, $description_length);
            $rpos = strrpos($snippet_string, " ");
            if ($rpos) {
                $snippet_string = mb_substr($snippet_string, 0, $rpos);
            }
            return $snippet_string;
        }
        $word_string = implode(" ",  $words);
        $words_change = false;
        if ($word_string != $last_words) {
            $words_change = true;
            $last_words = $word_string;
        }
        $start_regex = "/";
        $left = self::SNIPPET_LENGTH_LEFT;
        $left3 = $left - 3;
        $right = self::SNIPPET_LENGTH_RIGHT;
        //note we don't want ' to count as the cause of a word boundary
        $start_regex2 = "/\b(\w{3}.{0,$left3})?(?:(?:";
        $end_regex = "/ui";
        $end_regex2 = ").{0,$right}\b)+/ui";
        $ellipsis = "";
        if ($words_change || empty($search_words)) {
            // orginal list of words might have had space separated phrases;
            $search_words = array_filter(array_unique(
                explode(" ", $word_string)));
            $word_regex = "";
            $delim = "";
            foreach ($search_words as $word) {
                //term must start on word boundary and end within 3 chars of one
                $word_regex .= $delim . "\b" . preg_quote($word, '/') .
                    ".{0,3}?\b";
                $delim = "|";
            }
        }
        $snippet_string = "";
        $text_sources = array_filter(array_unique(explode(".. ", $text)));
        foreach ($text_sources as $text_source) {
            $len = mb_strlen($text_source);
            $offset = 0;
            if ($len < self::MIN_SNIPPET_LENGTH) {
                if (preg_match($start_regex . $word_regex .
                    $end_regex, $text_source, $match)) {
                    if (stristr($snippet_string, $text_source) === false) {
                        $snippet_string .= $ellipsis . $text_source;
                        $ellipsis = " ... ";
                    }
                }
            } else {
                preg_match_all($start_regex2 . $word_regex . $end_regex2,
                    $text_source, $matches);
                if (isset($matches[0])) {
                    $seen_match = [];
                    foreach ($matches[0] as $match) {
                        $match = trim($match, ".");
                        if (stristr($snippet_string, $match) === false) {
                            $snippet_string .= $ellipsis. $match;
                            $ellipsis = " ... ";
                            if (mb_strlen($snippet_string) >=
                                $description_length) {
                                break;
                            }
                        }
                    }
                }
            }
            if (mb_strlen($snippet_string) >= $description_length) {
                $snippet_string = mb_substr($snippet_string, 0,
                    $description_length);
                $test_snippet = preg_replace('/[^\s]+$/', "",
                    $snippet_string);
                if(!empty($test_snippet)) {
                    $snippet_string = $test_snippet;
                }
                break;
            }
        }
        return $snippet_string;
    }
    /**
     * Given a string, wraps in bold html tags a set of key words it contains.
     *
     * @param string $text haystack string to look for the key words
     * @param array $words an array of words to bold face
     *
     * @return string  the resulting string after boldfacing has been applied
     */
    public function boldKeywords($text, $words)
    {
        $words = array_unique($words);
        foreach ($words as $word) {
            if ($word != "" && !stristr($word, "/")) {
                $pattern = '/(\b)(' . preg_quote($word, '/').'.{0,3}?)(\b)/i';
                $new_text = preg_replace($pattern, '$1<b>$2</b>$3', $text);
                $text = $new_text;
            }
        }
        return $text;
    }
    /**
     * Gets a list of all DBMS that work with the search engine
     *
     * @return array Names of available data sources
     */
    public function getDbmsList()
    {
        $list = [];
        $data_managers = glob(C\BASE_DIR.'/models/datasources/*Manager.php');

        foreach ($data_managers as $data_manager) {
            $dbms =
                substr($data_manager,
                    strlen(C\BASE_DIR.'/models/datasources/'), -
                    strlen("Manager.php"));
            if ($dbms != 'Datasource') {
                $list[] = $dbms;
            }
        }
        return $list;
    }
    /**
     * Returns whether the provided dbms needs a login and password or not
     * (sqlite or sqlite3)
     *
     * @param string $dbms the name of a database management system
     * @return bool true if needs a login and password; false otherwise
     */
    public function loginDbms($dbms)
    {
        return !in_array($dbms, ["Sqlite3"]);
    }
    /**
     * Used to determine if an action involves just one yioop instance on
     * the current local machine or not
     *
     * @param array $machine_urls urls of yioop instances to which the action
     *     applies
     * @param string $index_timestamp if timestamp exists checks if the index
     *     has declared itself to be a no network index.
     * @return bool whether it involves a single local yioop instance (true)
     *     or not (false)
     */
    public function isSingleLocalhost($machine_urls, $index_timestamp = -1)
    {
        if ($index_timestamp >= 0) {
            $index_archive_name = self::index_data_base_name . $index_timestamp;
            if (file_exists(C\CRAWL_DIR .
                "/cache/$index_archive_name/no_network.txt")) {
                return true;
            }
        }
        $name_path = UrlParser::getPath(C\NAME_SERVER);
        foreach ($machine_urls as $url) {
            if (!UrlParser::isLocalhostUrl($url) ||
                $name_path != UrlParser::getPath($url)) {
                return false;
            }
        }
        if (is_array($machine_urls) && count($machine_urls) == 1 &&
            C\NAME_SERVER == $machine_urls[0]) {
            $mirror_table_name = C\CRAWL_DIR . "/" . self::mirror_table_name;
            if (file_exists($mirror_table_name) &&
                time() - filemtime($mirror_table_name) <
                2 * C\MIRROR_NOTIFY_FREQUENCY) {
                return false;
            }
        }
        return true;
    }
    /**
     * Used to get the translation of a string_id stored in the database to
     * the given locale.
     *
     * @param string $string_id id to translate
     * @param string $locale_tag to translate to
     * @return mixed translation if found, $string_id, otherwise
     */
    public function translateDb($string_id, $locale_tag)
    {
        static $lookup = [];
        $db = $this->db;
        if (isset($lookup[$string_id])) {
            return $lookup[$string_id];
        }
        $sql = "
            SELECT TL.TRANSLATION AS TRANSLATION
            FROM TRANSLATION T, LOCALE L, TRANSLATION_LOCALE TL
            WHERE T.IDENTIFIER_STRING = :string_id AND
                L.LOCALE_TAG = :locale_tag AND
                L.LOCALE_ID = TL.LOCALE_ID AND
                T.TRANSLATION_ID = TL.TRANSLATION_ID " . $db->limitOffset(1);
        $result = $db->execute($sql,
            [":string_id" => $string_id, ":locale_tag" => $locale_tag]);
        $row = $db->fetchArray($result);
        if (isset($row['TRANSLATION'])) {
            return $row['TRANSLATION'];
        }
        return $string_id;
    }
    /**
     * Get the user_id associated with a given username
     * (In base class as used as an internal method in both signin and
     *  user models)
     *
     * @param string $username the username to look up
     * @return string the corresponding userid
     */
    public function getUserId($username)
    {
        $db = $this->db;
        $sql = "SELECT USER_ID FROM USERS WHERE
            LOWER(USER_NAME) = LOWER(?) ". $db->limitOffset(1);
        $result = $db->execute($sql, [$username]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        $user_id = $row['USER_ID'] ?? false;
        return $user_id;
    }
    /**
     * Creates the WHERE and ORDER BY clauses for a query of a Yioop
     * table such as USERS, ROLE, GROUP, which have associated search web
     * forms. Searches are case insensitive
     *
     * @param array $search_array each element of this is a quadruple
     *     name of a field, what comparison to perform, a value to check,
     *     and an order (ascending/descending) to sort by
     * @param array $any_fields these fields if present in search array
     *     but with value "-1" will be skipped as part of the where clause
     *     but will be used for order by clause
     * @return array string for where clause, string for order by clause
     */
    public function searchArrayToWhereOrderClauses($search_array,
        $any_fields = ['status'])
    {
        $db = $this->db;
        $where = "";
        $order_by = "";
        $order_by_comma = "";
        $where_and = "";
        $sort_types = ["ASC", "DESC"];
        foreach ($search_array as $row) {
            if (isset($this->search_table_column_map[$row[0]])) {
                $field_name = $this->search_table_column_map[$row[0]];
            } else {
                $field_name = $row[0];
            }
            if (in_array($row[1], ['BETWEEN', 'NOT_BETWEEN'])) {
                list(, $comparison, $value_low, $value_high, $sort_dir,) = $row;
                if ($value_low != "" || $value_high != "") {
                    if ($where == "") {
                        $where = " WHERE ";
                    }
                    $where .= $where_and;
                    if ($value_low != "" && $value_high == "") {
                        $where .= "$field_name >= '" .
                            $db->escapeString($value_low) . "'";
                    } else if ($value_low == "" && $value_high != "") {
                        $where .= "$field_name <= '" .
                            $db->escapeString($value_high) . "'";
                    } else if ($value_low != "" && $value_high != "") {
                        $where .= $field_name . " ". $comparison . " '" .
                            $db->escapeString($value_low) . "' AND '".
                            $db->escapeString($value_high) . "'";
                    }
                    $where_and = " AND ";
                }
            } else {
                list(, $comparison, $value, $sort_dir,) = $row;
                if (!empty($value) && (!in_array($row[0], $any_fields)
                    || $value != "-1")) {
                    if ($where == "") {
                        $where = " WHERE ";
                    }
                    $where .= $where_and;
                    switch ($comparison) {
                        case "=":
                        case "!=":
                        case "<":
                        case ">":
                        case "<=":
                        case ">=":
                             $where .= "$field_name$comparison'" .
                                $db->escapeString($value) . "'";
                            break;
                        case "CONTAINS":
                             $where .= "LOWER($field_name) LIKE LOWER('%".
                                $db->escapeString($value)."%')";
                            break;
                        case "BEGINS WITH":
                             $where .= "LOWER($field_name) LIKE LOWER('".
                                $db->escapeString($value)."%')";
                            break;
                        case "ENDS WITH":
                             $where .= "LOWER($field_name) LIKE LOWER('%".
                                $db->escapeString($value)."')";
                            break;
                    }
                    $where_and = " AND ";
                }
            }
            if (in_array($sort_dir, $sort_types)) {
                if ($order_by == "") {
                    $order_by = " ORDER BY ";
                }
                $order_by .= $order_by_comma . $field_name . " " . $sort_dir;
                $order_by_comma = ", ";
            }
        }
        return [$where, $order_by];
    }
    /**
     * Gets a range of rows which match the provided search criteria from
     * $th provided table
     *
     * @param int $limit starting row from the potential results to return
     * @param int $num number of rows after start row to return
     * @param int &$total gets set with the total number of rows that
     *     can be returned by the given database query
     * @param array $search_array each element of this is a
     *     quadruple name of a field, what comparison to perform, a value to
     *     check, and an order (ascending/descending) to sort by
     * @param array $args additional values which may be used to get rows
     *      (what these are will typically depend on the subclass
     *      implementation)
     * @return array
     */
    public function getRows($limit, $num, &$total,
        $search_array = [], $args = null)
    {
        $db = $this->db;
        $tables = $this->fromCallback($args);
        $limit = $db->limitOffset($limit, $num);
        list($where, $order_by) =
            $this->searchArrayToWhereOrderClauses($search_array,
            $this->any_fields);
        $more_conditions = $this->whereCallback($args);
        if ($more_conditions) {
            $add_where = " WHERE ";
            if ($where != "") {
                $add_where = " AND ";
            }
            $where .= $add_where. $more_conditions;
        }
        $count_column = "*";
        if (isset($this->search_table_column_map['key'])) {
            $count_column = "DISTINCT " . $this->search_table_column_map['key'];
        }
        $sql = "SELECT COUNT($count_column) AS NUM FROM $tables $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $total = $row['NUM'];
        $select_columns = $this->selectCallback($args);
        $sql = "SELECT $select_columns FROM ".
            "$tables $where $order_by $limit";
        $result = $db->execute($sql);
        $i = 0;
        $rows = [];
        $row_callback = false;
        if ($result) {
            while ($rows[$i] = $db->fetchArray($result)) {
                $rows[$i] = $this->rowCallback($rows[$i], $args);
                $i++;
            }
            unset($rows[$i]); //last one will be null
        }
        $rows = $this->postQueryCallback($rows);
        return $rows;
    }
    /**
     * Controls which columns and the names of those columns from the tables
     * underlying the given model should be return from a getRows call.
     * This defaults to *, but in general will be overriden in subclasses of
     * Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine the columns
     * @return string a comma separated list of columns suitable for a SQL
     *     query
     */
    public function selectCallback($args  = null)
    {
        return "*";
    }
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * This defaults to the single table whose name is whatever is before
     * Model in the name of the model. For example, by default on FooModel
     * this method would return "FOO". If a different behavior, this can be
     * overriden in subclasses of Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args  = null)
    {
        $name = strtoupper(get_class($this));
        $name = substr($name, strlen(C\NS_MODELS), -strlen("Model"));
        return $name;
    }
    /**
     * Controls the WHERE clause of the SQL query that
     * underlies the given model and should be used in a getRows call.
     * This defaults to an empty WHERE clause.
     *
     * @param mixed $args additional arguments that might be used to construct
     *     the WHERE clause.
     * @return string a SQL WHERE clause
     */
    public function whereCallback($args = null)
    {
        return "";
    }
    /**
     * Called after as row is retrieved by getRows from the database to
     * perform some manipulation that would be useful for this model.
     * For example, in CrawlModel, after a row representing a crawl mix
     * has been gotten, this is used to perform an additional query to marshal
     * its components. By default this method just returns this row unchanged.
     *
     * @param array $row row as retrieved from database query
     * @param mixed $args additional arguments that might be used by this
     *     callback
     * @return array $row after callback manipulation
     */
    public function rowCallback($row, $args)
    {
        return $row;
    }
    /**
     * Called after getRows has retrieved all the rows that it would retrieve
     * but before they are returned to give one last place where they could
     * be further manipulated. For example, in MachineModel this callback
     * is used to make parallel network calls to get the status of each machine
     * returned by getRows. The default for this method is to leave the
     * rows that would be returned unchanged
     *
     * @param array $rows that have been calculated so far by getRows
     * @return array $rows after this final manipulation
     *
     */
    public function postQueryCallback($rows)
    {
        return $rows;
    }
}
