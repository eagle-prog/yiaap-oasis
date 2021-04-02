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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\FileCache;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;

/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Chris Pollett
 */
class SearchController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to
     * @var array
     */
    public $activities = ["query", "cache", "chart", "related", "signout",
        "recordClick", "trending"];
    /**
     * Name of the sub-search currently in use
     * @var string
     */
    public $subsearch_name = "";
    /**
     * The localization identifier for the current subsearch
     * @var string
     */
    public $subsearch_identifier = "";
    /**
     * Default number of results to display for the current subsearch
     * @var int
     */
    public $subsearch_per_page = 10;
    /**
     * Default query to use if user doesn't provide one for the current
     * subsearch
     * @var int
     */
    public $subsearch_default_query = "";
    /**
     * In addition to calling the base class' constructor, set up
     * FileCache objects if we're configured to do query
     * caching
     *
     * @param seekquarry\yioop\library\WebSite $web_site is the web server
     *      when Yioop runs in CLI mode, it acts as request router in non-CLI
     *      mode. In CLI, mode it is useful for caching files in RAM as they
     *      are read
     */
    public function __construct($web_site = null)
    {
        parent::__construct($web_site);
        if (empty($_SERVER["USE_CACHE"])) {
            if (C\USE_FILECACHE) {
                $phrase_model = $this->model("phrase");
                $phrase_model::$cache =
                    new FileCache(C\WORK_DIRECTORY . "/cache/queries",
                    $this->web_site);
                $_SERVER["USE_CACHE"] = true;
            } else {
                $_SERVER["USE_CACHE"] = false;
            }
        }
    }
    /**
     * This is the main entry point for handling a search request.
     *
     * ProcessRequest determines the type of search request (normal request ,
     * cache request, or related request), or if its a
     * user is returning from the admin panel via signout. It then calls the
     * appropriate method to handle the given activity.Finally, it draw the
     * search screen.
     */
    public function processRequest()
    {
        $data = [];
        $data['INCLUDE_SCRIPTS'] = [];
        $start_time = microtime(true);
        $this->initializeAdFields($data);
        list($subsearches, $no_query) = $this->initializeSubsearches();
        list($query, $activity, $arg) =
            $this->initializeUserAndDefaultActivity($data);
        if ($activity == "query" && $this->mirrorHandle()) {
            return;
        }
        $format_info = $this->initializeResponseFormat();
        if (!$format_info) {
            return;
        }
        list($view, $web_flag, $raw, $results_per_page, $limit) = $format_info;
        list($index_timestamp, $index_info, $save_timestamp) =
            $this->initializeIndexInfo($web_flag, $raw, $data);
        unset($_SESSION['LAST_ACTIVITY']);
        if (!empty($_REQUEST['q']) || $activity != "query") {
            if (!in_array($activity, ["cache", "trending"])) {
                $this->processQuery($data, $query, $activity, $arg,
                    $results_per_page, $limit, $index_timestamp, $raw,
                    $save_timestamp);
                    // calculate the results of a search if there is one
            } else if ($activity == "cache") {
                if (isset($_REQUEST['repository'])) {
                    $ui_array = [];
                } else {
                    $ui_array = ["highlight", "yioop_nav", "history",
                        "summaries", "version"];
                    if (isset($_REQUEST['from_cache'])) {
                        $ui_array[] = "cache_link_referrer";
                    }
                    if (isset($_REQUEST['hist_open'])) {
                        $ui_array[] = "hist_ui_open";
                    }
                }
                $this->cacheRequestAndOutput($arg, $ui_array, $query,
                    $index_timestamp);
                $this->model("impression")->addQueryImpression(
                    "cache:$arg");
                return;
            }
        }
        $data['ELAPSED_TIME'] = number_format(
            L\changeInMicrotime($start_time), 6);
        if ($view == 'api') {
            $data['ELEMENT'] = 'search';
        }
        if ($view == "serial" || $view == "json") {
            $data['BASE_URL'] = C\BASE_URL;
            if (isset($data["PAGES"])) {
                $count = count($data["PAGES"]);
                for ($i = 0; $i < $count; $i++) {
                    unset($data["PAGES"][$i]["OUT_SCORE"]);
                    $data["PAGES"][$i][self::SCORE]= "".
                        round($data["PAGES"][$i][self::SCORE], 3);
                    $data["PAGES"][$i][self::DOC_RANK]= "".
                        round($data["PAGES"][$i][self::DOC_RANK], 3);
                    $data["PAGES"][$i][self::RELEVANCE]= "".
                        round($data["PAGES"][$i][self::RELEVANCE], 3);
                }
            }
            $data['TOTAL_TIME'] = number_format(L\changeInMicrotime(
                $_SERVER["REQUEST_TIME_FLOAT"]), 6);
            if ($view == "serial") {
                if (isset($_REQUEST['mirror']) &&
                    $_REQUEST['mirror'] == "true") {
                    // mark if we are a mirror -- not making use of yet
                    $data['MIRROR'] = true;
                }
                $data = serialize($data);
                if (empty(ini_get('zlib.output_compression')) &&
                    !$this->web_site->isCli()) {
                    ob_start("ob_gzhandler");
                    $this->web_site->header("Content-Type: text/plain");
                    e($data);
                    ob_end_flush();
                } else {
                    $this->web_site->header("Content-Type: text/plain");
                    $this->web_site->header("Content-Length: " . strlen($data));
                    e($data);
                    flush();
                }
                \seekquarry\yioop\library\webExit();
            } else {
                $out_data = [];
                $out_data["language"] = L\getLocaleTag();
                $out_data["link"] = C\NAME_SERVER .
                    "?f=json&amp;q={$data['QUERY']}";
                $out_data["totalResults"] = $data['TOTAL_ROWS'];
                $out_data["startIndex"] = $data['LIMIT'];
                $out_data["itemsPerPage"] = $data['RESULTS_PER_PAGE'];
                foreach ($data['PAGES'] as $page) {
                    $item = [];
                    $item["title"] = $page[self::TITLE];
                    if (!isset($page[self::TYPE]) ||
                    (isset($page[self::TYPE])
                    && $page[self::TYPE] != "link")) {
                        $item["link"] = $page[self::URL];
                    } else {
                        $item["link"] = strip_tags($page[self::TITLE]);
                    }
                    $item["description"] = strip_tags($page[self::DESCRIPTION]);
                    if (isset($page[self::THUMB])
                        && $page[self::THUMB] != 'null') {
                        $item["thumb"] = $page[self::THUMB];
                    }
                    if (isset($page[self::TYPE])) {
                        $item["type"] = $page[self::TYPE];
                    }
                    if (isset($page[self::IMAGE_LINK])) {
                        $item["image_link"] = $page[self::IMAGE_LINK];
                    }
                    if (!empty($page[self::SUMMARY_OFFSET][0][4])) {
                        $item["pub_date"] = $page[self::SUMMARY_OFFSET][0][4];
                    }
                    $out_data['channel'][] = $item;
                }
                $out = json_encode($out_data);
                // gzip if possible
                $gz_handler_in_use = false;
                if (empty(ini_get('zlib.output_compression')) &&
                    !$this->web_site->isCli()) {
                    ob_start("ob_gzhandler");
                    $gz_handler_in_use = true;
                }
                //jsonp format
                if (isset($_REQUEST['callback'])) {
                    $callback = $this->clean($_REQUEST['callback'], "string");
                    $out = "// API callback\n$callback($out);";
                    $this->web_site->header(
                        "Content-Type: text/javascript; charset=UTF-8");
                } else {
                    $this->web_site->header("Content-Type: application/json");
                }
                if ($gz_handler_in_use) {
                    e($out);
                    ob_end_flush();
                } else {
                    $this->web_site->header("Content-Length: ".strlen($out));
                    e($out);
                    flush();
                }
                \seekquarry\yioop\library\webExit();
            }
            \seekquarry\yioop\library\webExit();
        }
        if ($web_flag) {
            $this->addSearchViewData($index_info, $no_query, $raw, $view,
                $subsearches, $data);
        }
        $this->displayView($view, $data);
    }
    /**
     * Determines how this query is being run and return variables for the view
     *
     * A query might be run as a web-based where HTML is expected as the
     * output, an RSS query, an API query, or as a serial query from a
     * name_server or mirror instance back to one of the other queue servers
     * in a Yioop installation. A query might also request different numbers
     * of pages back beginning at different starting points in the result.
     *
     * @return array consisting of (view to be used to render results,
     *     flag for whether html results should be used, int code for what
     *     kind of group of similar urls should be done on the results,
     *     number of search results to return, start from which result)
     */
    public function initializeResponseFormat()
    {
        $alternative_outputs = ['rss', 'json', 'serial'];
        $view = "search";
        $web_flag = true;
        if (isset($_REQUEST['f']) && $_REQUEST['f'] == 'api') {
            $view = "api";
        } else if (isset($_REQUEST['f']) && in_array($_REQUEST['f'],
            $alternative_outputs) && C\RSS_ACCESS) {
            $view = $_REQUEST['f'];
            $web_flag = false;
        } else if (!C\WEB_ACCESS) {
            return false;
        }
        if (isset($_REQUEST['num'])) {
            $results_per_page = $this->clean($_REQUEST['num'], "int");
            if ($results_per_page <= 0) {
                $results_per_page = C\NUM_RESULTS_PER_PAGE;
            }
        } else {
            $results_per_page = C\NUM_RESULTS_PER_PAGE;
        }
        if (isset($_SESSION['MAX_PAGES_TO_SHOW']) &&
            !in_array($view, $alternative_outputs)) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        }
        if (isset($_REQUEST['raw'])){
            $raw = max($this->clean($_REQUEST['raw'], "int"), 0);
        } else {
            $raw = 0;
        }
        if (isset($_REQUEST['limit'])) {
            $limit = $this->clean($_REQUEST['limit'], "int");
        } else {
            $limit = 0;
        }
        return [$view, $web_flag, $raw, $results_per_page, $limit];
    }
    /**
     * Determines if query results are using a subsearch, and if so
     * initializes them, also it sets up list of subsearches to draw
     * at top of screen.
     *
     * @return array (subsearches, no_query) where subsearches is itself
     *     an array of data about each subsearch to draw, and no_query
     *     is a bool flag used in the case of a news subsearch when no query
     *     was entered by the user but still want to display news
     */
    public function initializeSubsearches()
    {
        $subsearches = $this->model("source")->getSubsearches();
        array_unshift($subsearches, ["FOLDER_NAME" => "",
            "SUBSEARCH_NAME" => tl('search_controller_web')]);
        $no_query = false;
        $this->subsearch_per_page = 0;
        $this->image_subsearch_enabled = false;
        foreach ($subsearches as $search) {
            if ($search["FOLDER_NAME"] === 'images') {
                $this->image_subsearch_enabled = true;
            }
        }
        if (!empty($_REQUEST["s"])) {
            $search_found = false;
            foreach ($subsearches as $search) {
                if ($search["FOLDER_NAME"] === $_REQUEST["s"]) {
                    if (empty($search["INDEX_IDENTIFIER"]) ||
                        empty($search["PER_PAGE"])) {
                        continue;
                    }
                    $search_found = true;
                    $this->subsearch_name = $search["FOLDER_NAME"];
                    $this->subsearch_identifier = $search["INDEX_IDENTIFIER"];
                    $this->subsearch_per_page =  $search["PER_PAGE"];
                    $this->subsearch_default_query =
                        (empty($search["DEFAULT_QUERY"])) ? "" :
                        $search["DEFAULT_QUERY"];
                    if (!isset($_REQUEST['num'])) {
                        $_REQUEST['num'] = $search["PER_PAGE"];
                    }
                    break;
                }
            }
            if (!$search_found) {
                include(C\BASE_DIR . "/error.php");
                \seekquarry\yioop\library\webExit();
            }
            if (!empty($this->subsearch_default_query) &&
                (!isset($_REQUEST['q']) || $_REQUEST['q'] == "")) {
                $_REQUEST['q'] = $this->subsearch_default_query;
                $no_query = true;
            }
        }
        return [$subsearches, $no_query];
    }
    /**
     * Determines the kind of user session that this search request is for
     *
     * This function is called by @see processRequest(). The user session
     * might be one without a login, one with a login so need to validate
     * against to prevent CSRF attacks, just after someone logged out, or
     * a bot session (googlebot, etc) so remove the query request
     *
     * @param array &$data that will eventually be sent to the view. We might
     *     update with error messages
     * @return array consisting of (query based on user info, whether
     *     if a cache request highlighting should be userd, what activity
     *     user wants, any arguments to this activity)
     *
     */
    public function initializeUserAndDefaultActivity(&$data)
    {
        $arg = false;
        $changed_settings_flag = false;
        if (!isset($_REQUEST['a']) || !in_array($_REQUEST['a'],
            $this->activities)) {
            $activity = "query";
        } else {
            $activity = $_REQUEST['a'];
            if (isset($_REQUEST['arg'])) {
                $arg = $this->clean($_REQUEST['arg'], "string");
            }
        }
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
            $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user);
            if ($token_okay === false) {
                $_SESSION = [];
                $user = L\remoteAddress();
                $token_okay = true;
            }
        } else {
            $user = L\remoteAddress();
            $token_okay = true;
        }
        if ($token_okay && isset($_SESSION["USER_ID"]) &&
            $user == $_SESSION['USER_ID']) {
            $data["ADMIN"] = true;
            if (!isset($data["USERNAME"])) {
                $signin_model = $this->model("signin");
                $data['USERNAME'] = $signin_model->getUserName(
                    $_SESSION['USER_ID']);
            }
        } else {
            $data["ADMIN"] = false;
        }
        $languages = $this->model("locale")->getLocaleList();
        foreach ($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if ($token_okay && isset($_REQUEST['lang']) &&
            in_array($_REQUEST['lang'], array_keys($data['LANGUAGES']))) {
            $old_value = isset($_SESSION['l']) ?
                $_SESSION['l'] : L\getLocaleTag();
            $_SESSION['l'] = $_REQUEST['lang'];
            L\setLocaleObject($_SESSION['l']);
            if ($old_value != $_SESSION['l']) {
                $changed_settings_flag = true;
            }
        }
        $data['LOCALE_TAG'] = L\getLocaleTag();
        $data['LANGUAGES_TO_SHOW'] = 1;
        $n = C\NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] = [ -1 => tl('search_controller_continuous'),
            $n => $n, 2 * $n => 2 * $n, 5 * $n => 5 * $n, 10 * $n => 10 * $n];
        if ($token_okay && isset($_REQUEST['perpage']) &&
            in_array($_REQUEST['perpage'], array_keys($data['PER_PAGE']))) {
            $old_value = (isset($_SESSION['MAX_PAGES_TO_SHOW'])) ?
                $_SESSION['MAX_PAGES_TO_SHOW'] : -1;
            $_SESSION['MAX_PAGES_TO_SHOW'] = $_REQUEST['perpage'];
            if ($old_value != $_SESSION['MAX_PAGES_TO_SHOW']) {
                $changed_settings_flag = true;
            }
        }
        if (isset($_SESSION['MAX_PAGES_TO_SHOW'])) {
            $data['PER_PAGE_SELECTED'] = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $data['PER_PAGE_SELECTED'] = -1;
            $_SESSION['MAX_PAGES_TO_SHOW'] = -1;
        }
        if ($token_okay && isset($_REQUEST['perpage'])) {
            $old_value = (empty($_SESSION['OPEN_IN_TABS'])) ? false:
                $_SESSION['OPEN_IN_TABS'];
            $_SESSION['OPEN_IN_TABS'] = (isset($_REQUEST['open_in_tabs'])) ?
                true : false;
            if ($old_value != $_SESSION['OPEN_IN_TABS']) {
                $changed_settings_flag = true;
            }
        }
        if (isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        $data['IMAGE_SIZES'] = [ 'image' => tl('search_controller_allsizes'),
            'image-tracking' => tl('search_controller_tiny'),
            'image-small' => tl('search_controller_small'),
            'image-medium' => tl('search_controller_medium'),
            'image-large' => tl('search_controller_large'),
        ];
        if ($token_okay && isset($_REQUEST['imagesize']) &&
            in_array($_REQUEST['imagesize'],
            array_keys($data['IMAGE_SIZES'])) ) {
            $old_value = (isset($_SESSION['IMAGE_SIZE_SELECTED'])) ?
                $_SESSION['IMAGE_SIZE_SELECTED'] : 'all';
            $_SESSION['IMAGE_SIZE_SELECTED'] =
                (!empty($_REQUEST['imagesize'])) ? $_REQUEST['imagesize']:
                $_SESSION['IMAGE_SIZE_SELECTED'];
            if ($old_value != $_SESSION['IMAGE_SIZE_SELECTED']) {
                $changed_settings_flag = true;
            }
        }
        if (isset($_SESSION['IMAGE_SIZE_SELECTED'])) {
            $data['IMAGE_SIZE_SELECTED'] = $_SESSION['IMAGE_SIZE_SELECTED'];
        } else {
            $data['IMAGE_SIZE_SELECTED'] = 'all';
        }
        $data['VIDEO_DURATIONS'] = [ 0 => tl('search_controller_zeromin'),
            300 => tl('search_controller_fivemin'),
            600 => tl('search_controller_tenmin'),
            900 => tl('search_controller_fifteenmin'),
            1800 => tl('search_controller_halfhour'),
            3600 => tl('search_controller_onehour'),
            7200 => tl('search_controller_twohour'),
            1000000 => tl('search_controller_alllengths'),
        ];
        if ($token_okay && isset($_REQUEST['minvideoduration']) &&
            isset($_REQUEST['maxvideoduration']) &&
            in_array($_REQUEST['minvideoduration'],
            array_keys($data['VIDEO_DURATIONS'])) &&
            in_array($_REQUEST['maxvideoduration'],
            array_keys($data['VIDEO_DURATIONS']))) {
            $old_min_value = (isset($_SESSION['VIDEO_MIN_DURATION'])) ?
                $_SESSION['VIDEO_MIN_DURATION'] : 0;
            $old_max_value = (isset($_SESSION['VIDEO_MAX_DURATION'])) ?
                $_SESSION['VIDEO_MAX_DURATION'] : 1000000;
            $_REQUEST['minvideoduration'] = ($_REQUEST['minvideoduration'] <
                $_REQUEST['maxvideoduration']) ? $_REQUEST['minvideoduration'] :
                0;
            $_SESSION['VIDEO_MIN_DURATION'] = $_REQUEST['minvideoduration'];
            $_SESSION['VIDEO_MAX_DURATION'] = $_REQUEST['maxvideoduration'];
            if ($old_min_value != $_SESSION['VIDEO_MIN_DURATION'] ||
                $old_max_value != $_SESSION['VIDEO_MAX_DURATION']) {
                $changed_settings_flag = true;
            }
        }
        $data['VIDEO_MIN_DURATION'] = (isset($_SESSION['VIDEO_MIN_DURATION'])) ?
            $_SESSION['VIDEO_MIN_DURATION'] : 0;
        $data['VIDEO_MAX_DURATION'] = (isset($_SESSION['VIDEO_MAX_DURATION'])) ?
            $_SESSION['VIDEO_MAX_DURATION'] : 1000000;
        if ($token_okay && isset($_REQUEST['perpage'])) {
            $old_value = (isset($_SESSION['SAFE_SEARCH'])) ?
                $_SESSION['SAFE_SEARCH'] : "true";
            $_SESSION['SAFE_SEARCH'] = (!empty($_REQUEST['safe_search'])) ?
                "true" : "false";
            if ($old_value != $_SESSION['SAFE_SEARCH']) {
                $changed_settings_flag = true;
            }
        }
        if (isset($_SESSION['SAFE_SEARCH'])) {
            $data['SAFE_SEARCH'] = $_SESSION['SAFE_SEARCH'];
        } else {
            $data['SAFE_SEARCH'] = "true";
        }
        $time = time();
        $data['TIME_PERIODS'] = [ 'all' => tl('search_controller_all'),
            'day' => tl('search_controller_today'),
            'week' => tl('search_controller_this_week'),
            'month' => tl('search_controller_this_month'),
            'year' => tl('search_controller_this_year'),
        ];
        if ($token_okay && isset($_REQUEST['timeperiod']) &&
            in_array(trim($_REQUEST['timeperiod']), array_keys(
            $data['TIME_PERIODS']))) {
            $old_value = (isset($_SESSION['TIME_PERIOD_SELECTED'])) ?
                $_SESSION['TIME_PERIOD_SELECTED'] : 'all';
            $_SESSION['TIME_PERIOD_SELECTED'] = trim($_REQUEST['timeperiod']);
            if ($old_value != $_SESSION['TIME_PERIOD_SELECTED']) {
                $changed_settings_flag = true;
            }
        }
        if (isset($_SESSION['TIME_PERIOD_SELECTED'])) {
            $data['TIME_PERIOD_SELECTED'] = $_SESSION['TIME_PERIOD_SELECTED'];
        } else {
            $data['TIME_PERIOD_SELECTED'] = 'all';
        }
        if ($changed_settings_flag) {
            if (L\isPositiveInteger($user)) {
                $this->model("user")->setUserSession($user, $_SESSION);
            }
            return $this->redirectWithMessage(
                tl('settings_controller_settings_saved'),
                ['return', 'oldc', 'q', 's']);
        } else if (isset($_REQUEST['perpage'])) {
            return $this->redirectWithMessage("",
                ['return', 'oldc', 'q']);
        }
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user);
        if (isset($_REQUEST['q'])) {
            $_REQUEST['q'] = mb_substr($_REQUEST['q'], 0, C\MAX_QUERY_LEN);
            $_REQUEST['q'] = $this->restrictQueryByUserAgent($_REQUEST['q']);
        }
        if ($activity == "query") {
            list($query, $activity, $arg) = $this->extractActivityQuery();
        } else {
            $query = $_REQUEST['q'] ?? "";
        }
        $query = $this->clean($query, "string");
        if (isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        if ($activity == "trending") {
            $trending_model = $this->model('trending');
            $source_model = $this->model("source");
            $data['TRENDING'] = true;
            $data['ACTIVITY_METHOD'] = 'trending';
            $data['ACTIVITY_CONTROLLER'] = 'search';
            $data['MORE_PAGE'] = 0;
            $data['CATEGORY'] = 'news';
            $data['CATEGORY_TYPE'] = 'feed';
            $media_categories = $source_model->getMediaCategories(
                ['weather']);
            if (!$media_categories) {
                $media_categories = [];
            }
            if (isset($_REQUEST['category']) && ($key =
                array_search($_REQUEST['category'],
                array_column($media_categories, 'NAME'))) !== false) {
                $data['CATEGORY'] = $_REQUEST['category'];
                $data['CATEGORY_TYPE'] = $media_categories[$key]['TYPE'];
            } else {
                $fail_category = $this->clean($_REQUEST['category'], 'string');
                $data['SCRIPT'] ??= "";
                $data['SCRIPT'] .=
                        "doMessage('<h1 class=\"red\" >".
                        tl('search_controller_no_trend_category',
                        $fail_category).
                        "</h1>');";
            }
            $subsearch = $source_model->getSubsearch($data['CATEGORY']);
            $data['CATEGORY_NAME'] = ($subsearch) ?
                $source_model->getSubsearchName($data['CATEGORY'],
                $data['LOCALE_TAG']) : false;
            $data['QUERY'] = "trending:" . $data['CATEGORY'];
            $order = [];
            if (!empty($_REQUEST['order'])) {
                $pre_order = $this->clean($_REQUEST['order'], "string");
                $data['QUERY'] .= ":$pre_order";
                $pre_order_parts = explode(",", $pre_order);
                foreach ($pre_order_parts as $pre_order_part) {
                    $field_direction = explode("_", $pre_order_part);
                    list($field, $direction) = (empty($field_direction[1])) ?
                        [$pre_order_part, "ASC"] : $field_direction;
                    $field = mb_strtoupper($field);
                    $direction = mb_strtoupper($direction);
                    if (in_array($field, ["TERM", "SCORE", "OCCURRENCES"]) &&
                        in_array($direction, ["ASC", "DESC"])) {
                        $order[$field] = $direction;
                    }
                }
            }
            $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
            $token_query = ($logged_in && isset($data[C\CSRF_TOKEN])) ?
                C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&": "?";
            $trending_url = B\controllerUrl('trending', $logged_in) .
                $token_query;
            if ($arg == 'chart' && !empty($_REQUEST['term']) &&
                !empty($_REQUEST['period'])) {
                $terms = (is_array($_REQUEST['term'])) ?
                    array_keys($_REQUEST['term']) : [$_REQUEST['term']];
                $periods = [C\ONE_DAY, C\ONE_WEEK, C\ONE_MONTH,
                    C\ONE_YEAR];
                $period = in_array($_REQUEST['period'], $periods) ?
                    $_REQUEST['period'] : C\ONE_DAY;
                $data['PERIOD'] = $period;
                $data['TERMS'] = [];
                $data['CHART_DATA'] = [];
                $graphs = [];
                $dates = [ C\ONE_HOUR => 'i', C\ONE_DAY => 'H',
                    C\ONE_WEEK => 'D', C\ONE_MONTH => 'W', C\ONE_YEAR => 'm'
                ];
                $query_dates = [ C\ONE_HOUR => 'h', C\ONE_DAY => 'd',
                    C\ONE_WEEK => 'w', C\ONE_MONTH => 'm', C\ONE_YEAR => 'y'
                ];
                $date_format = (empty($dates[$period])) ? 'H' : $dates[$period];
                $query_date = (empty($query_dates[$period])) ?
                    'D' : $query_dates[$period];
                $graph_keys = [];
                $data['QUERY'] = "chart:" . $data['CATEGORY'] . ":".
                    $query_date . ":";
                $colon = "";
                foreach ($terms as $term) {
                    $term = $this->clean(urldecode($term), "string");
                    $data['TERMS'][] = $term;
                    $graph =
                        $trending_model->termScoresForPeriod($term, $period,
                        L\getLocaleTag(), true);
                    $graph_keys = array_merge($graph_keys, array_keys($graph));
                    $graphs[] = $graph;
                    $data['QUERY'] .= $colon . preg_replace("/\s+/", "_",
                        $term);
                    $colon = ":";
                }
                $graph_keys = array_unique($graph_keys);
                sort($graph_keys);
                $x_values = [];
                foreach($graph_keys as $timestamp) {
                    $x_values[$timestamp] = date($date_format, $timestamp);
                }
                $data['CHART_DATA']['graphs'] = $graphs;
                $data['CHART_DATA']['x_values'] = $x_values;
                $data['CHART_DATA']['num_graphs'] =
                    count($data['CHART_DATA']['graphs']);
                if ($_SERVER["MOBILE"]) {
                    $properties = ["title" => "",
                        "width" => 340, "height" => 300,
                        "tick_font_size" => 8];
                } else {
                    $properties = ["title" => "",
                        "width" => 700, "height" => 500];
                }
                $properties = json_encode($properties);
                $data['SCRIPT'] = (empty($data['SCRIPT'])) ? "" :
                    $data['SCRIPT'];
                $data['SCRIPT'] .= 'chart = new Chart('.
                    '"chart", '. json_encode($data['CHART_DATA']) .
                    ', ' . $properties . '); chart.draw();';
            }
            if (empty($data['CHART_DATA'])) {
                $data['TREND_DATA'] = $trending_model->topTermsForUpdatePeriods(
                    L\getLocaleTag(), [], $data['CATEGORY'], $order);
            } else {
                $data['INCLUDE_SCRIPTS'][] = "chart";
            }
            $_SESSION['REMOTE_ADDR'] = L\remoteAddress();
        } else if (!empty($_REQUEST['s'])) {
            $data['ACTIVITY_METHOD'] = 's/' . $this->subsearch_name;
            $data['ACTIVITY_CONTROLLER'] = 'search';
        }
        return [$query, $activity, $arg];
    }
    /**
     * Determines which crawl or mix timestamp should be in use for this
     * query. It also determines info and returns associated with this
     * timestamp.
     *
     * @param bool $web_flag whether this is a web based query or one from
     *     the search API
     * @param int $raw should validate against list of known crawls or an
     *     internal (say network) query that doesn't require validation
     *     (faster without).
     * @param array &$data that will eventually be sent to the view. We set
     *     the 'its' (index_time_stamp) field here
     * @return array consisting of index timestamp of crawl or mix in use,
     *     $index_info an array of info about that index, and $save_timestamp
     *     timestamp of last savepoint, used if this query is being is the
     *     query for a crawl mix archive crawl.
     */
    public function initializeIndexInfo($web_flag, $raw, &$data)
    {
        if (isset($_REQUEST['machine'])) {
            $current_machine = $this->clean($_REQUEST['machine'], 'int');
        } else {
            $current_machine = 0;
        }
        $crawl_model = $this->model("crawl");
        $this->model("phrase")->current_machine = $current_machine;
        $crawl_model->current_machine = $current_machine;
        if (C\BASE_URL == C\NAME_SERVER) {
            $machine_urls = $this->model("machine")->getQueueServerUrls();
        } else {
            $machine_urls = null;
        }
        $current_its = $crawl_model->getCurrentIndexDatabaseName();
        $index_timestamp = $this->getIndexTimestamp();
        $is_mix = false;
        if ($index_timestamp != $current_its) {
            if ($raw != 1) {
                if ($index_timestamp != 0 ) {
                    //validate timestamp against list
                    //(some crawlers replay deleted crawls)
                    $crawls = $crawl_model->getCrawlList(false, true,
                        $machine_urls, true);
                    if ($crawl_model->isCrawlMix($index_timestamp)) {
                        $is_mix = true;
                    }
                    $found_crawl = false;
                    foreach ($crawls as $crawl) {
                        if ($index_timestamp == $crawl['CRAWL_TIME']) {
                            $found_crawl = true;
                            break;
                        }
                    }
                    if (!$is_mix && ( !$found_crawl &&
                        !isset($_SESSION['its']) &&
                        (isset($_REQUEST['q']) || isset($_REQUEST['arg'])))) {
                        unset($_SESSION['its']);
                        include(C\BASE_DIR . "/error.php");
                        \seekquarry\yioop\library\webExit();
                    } else if (!$found_crawl && !$is_mix) {
                        unset($_SESSION['its']);
                        $index_timestamp = $current_its;
                    }
                }
            }
        }
        $index_info = null;
        if ($web_flag && $index_timestamp != 0) {
            $index_info =  $crawl_model->getInfoTimestamp(
                $index_timestamp, $machine_urls);
            if ($index_info == [] || ((!isset($index_info["COUNT"]) ||
                $index_info["COUNT"] == 0) && !$is_mix)) {
                if ($index_timestamp != $current_its) {
                    $index_timestamp = $current_its;
                    $index_info =  $crawl_model->getInfoTimestamp(
                        $index_timestamp, $machine_urls);
                    if ($index_info == []) {
                        $index_info = null;
                    }
                }
            }
        }
        if (isset($_REQUEST['save_timestamp'])) {
            $save_timestamp = substr($this->clean(
                $_REQUEST['save_timestamp'], 'int'), 0, C\TIMESTAMP_LEN);
        } else {
            $save_timestamp = 0;
        }
        $data['its'] = (isset($index_timestamp)) ? $index_timestamp : 0;
        return [$index_timestamp, $index_info, $save_timestamp];
    }
    /**
     * Finds the timestamp of the main crawl or mix to return results from
     * Does not do checking to make sure timestamp exists.
     *
     * @return string current timestamp
     */
    public function getIndexTimestamp()
    {
        if ((isset($_REQUEST['its']) || isset($_SESSION['its']))) {
            $its = (isset($_REQUEST['its'])) ? $_REQUEST['its'] :
                $_SESSION['its'];
            $index_timestamp = substr($this->clean($its, "int"), 0,
                C\TIMESTAMP_LEN);
        } else {
            $index_timestamp =
                $this->model("crawl")->getCurrentIndexDatabaseName();
        }
        return $index_timestamp;
    }
    /**
     * Sometimes robots disobey the statistics page nofollow meta tag.
     * and need to be stopped before they query the whole index
     *
     * @param string $query  the search request string
     * @return string the search request string if not a bot; "" otherwise
     */
    public function restrictQueryByUserAgent($query)
    {
        $bots = ["googlebot", "baidu", "naver", "sogou"];
        $query_okay = true;
        foreach ($bots as $bot) {
            if (isset($_SERVER["HTTP_USER_AGENT"]) &&
                stristr($_SERVER["HTTP_USER_AGENT"], $bot)) {
                $query_okay = false;
            }
        }
        return ($query_okay) ? $query : "";
    }
    /**
     * Prepares the array $data so the SearchView can draw search results
     *
     * @param array $index_info an array of info about that index in use
     * @param bool $no_query true in the case of a news subsearch when no query
     *     was entered by the user but still want to display news
     * @param int $raw $raw what kind of grouping of identical results should
     *     be done (0 is default, 1 and higher used for internal queries)
     * @param string $view name of view class search results are for
     * @param array $subsearches an array of data about each subsearch to draw
     *     to the view
     * @param array &$data that will eventually be sent to the view for
     *     rendering. This method adds fields to the array
     */
    public function addSearchViewData($index_info, $no_query, $raw, $view,
        $subsearches, &$data)
    {
        if ($index_info !== null) {
            if (isset($index_info['IS_MIX'])) {
                if (tl('search_controller_mix_info',
                    $index_info['DESCRIPTION']) ==
                    "search_controller_mix_info") {
                    // this will cause index info not to be printed
                    $data['INDEX_INFO'] = "";
                } else {
                    $data['INDEX_INFO'] = tl('search_controller_mix_info',
                        $index_info['DESCRIPTION']);
                }
            } else {
                if (isset($index_info['DESCRIPTION']) &&
                    isset($index_info['VISITED_URLS_COUNT']) &&
                    isset($index_info['COUNT']) ) {
                    if (tl('search_controller_crawl_info',
                        $index_info['DESCRIPTION'],
                        $index_info['VISITED_URLS_COUNT'],
                        $index_info['COUNT']) ==
                        "search_controller_crawl_info") {
                        // this will cause index info not to be printed
                        $data['INDEX_INFO'] = "";
                    } else {
                        $data['INDEX_INFO'] = tl('search_controller_crawl_info',
                            $index_info['DESCRIPTION'],
                            $index_info['VISITED_URLS_COUNT'],
                            $index_info['COUNT']);
                    }
                } else {
                    $data['INDEX_INFO'] = "";
                }
            }
        } else {
            $data['INDEX_INFO'] = "";
        }
        $stats_file = C\CRAWL_DIR . "/cache/" . self::statistics_base_name.
                $data['its'] . ".txt";
        $data["SUBSEARCHES"] = $subsearches;
        if ($this->subsearch_name != "" && $this->subsearch_identifier != "") {
            $data["SUBSEARCH"] = $this->subsearch_name;
            if ($no_query && !empty($data["SUBSEARCH"]) &&
                !empty($this->subsearch_default_query)) {
                $data['RSS_FEED_URL'] = B\subsearchUrl($data["SUBSEARCH"],true).
                    "f=rss";
            }
        }
        $data['IMAGE_SUBSEARCH_ENABLED'] =
            (empty($this->image_subsearch_enabled)) ? false : true;
        $data["HAS_STATISTICS"] = file_exists($stats_file);
        if (!isset($data["RAW"])) {
            $data["RAW"] = $raw;
        }
        if (($view == "search" || $view == 'api')
            && $data["RAW"] == 0 && isset($data['PAGES'])) {
            $data['PAGES'] = $this->makeMediaGroups($data['PAGES']);
        }
        $data['IS_LANDING'] = !isset($data['PAGES']) &&
            !isset($data['TRENDING']);
        $data['CSS_CLASSES'] = ($data['IS_LANDING']) ? "landing"  : "";
        /*  Only set up spell correction if single conjunctive query without
            without meta words
         */
        if (!isset($data['QUERY']) ||
            !preg_match('/(\%7C|\%3A|%26quot%3B)/u', $data['QUERY'])) {
            $data['INCLUDE_SCRIPTS'][] = "suggest";
        }
        if (!isset($data['SCRIPT'])) {
            $data['SCRIPT'] = "";
        }
        if ($data['IS_LANDING'] && (empty($data["SUBSEARCH"]) ||
            $data["SUBSEARCH"] == 'web')) {
            $this->addLandingHighlights($data, $subsearches);
        }
        $data['SCRIPT'] .= "\nlocal_strings = {'spell':'".
            tl('search_controller_search')."'};";
        $data['SCRIPT'] .= "\ncsrf_name ='" . C\CSRF_TOKEN . "';";
        $limit = (empty($data['LIMIT'])) ? 0 : $data['LIMIT'];
        $total_rows = (empty($data['TOTAL_ROWS'])) ? 0 :
            $data['TOTAL_ROWS'];
        $end_results = tl("search_controller_end_results");
        $b_url = (empty($data['PAGING_QUERY'])) ? "" : "?" .
            http_build_query($data['PAGING_QUERY'], '', '&');
        $data['SCRIPT'] .= <<< EOD
            if (typeof yioop_post_scripts === 'undefined') {
                yioop_post_scripts = [];
            }
            yioop_post_scripts.push( function() {
                var settings_form = elt('settings-form');
                saveFormState(settings_form);
                settings_form.submitOnChange = function() {
                    if (!equalFormSaveState(settings_form)) {
                        settings_form.submit();
                    } else {
                        toggleOptions();
                    }
                };
            });
EOD;
        if (empty($data['TRENDING']) && (!empty($data['RESULTS_PER_PAGE']) &&
            $data['RESULTS_PER_PAGE'] == -1) ) {
            $to_show = (!empty($this->subsearch_per_page) &&
                $this->subsearch_per_page > 0) ?
                $this->subsearch_per_page: C\NUM_RESULTS_PER_PAGE;
            $data['SCRIPT'] .= " var nextPage = initNextResultsPage($limit," .
                " $total_rows, $to_show, '$b_url', '$end_results');\n";
        }
        $data['INCLUDE_LOCALE_SCRIPT'] = "locale";
        if ($no_query || isset($_REQUEST['no_query'])) {
            $data['NO_QUERY'] = true;
            if (!isset($data['PAGING_QUERY'])) {
                $data['PAGING_QUERY'] = [];
            }
            $data['PAGING_QUERY']['no_query'] = "true";
        }
        $data['MENU'] = 'searchmenu';
        $data['MENU_NAME'] = tl('search_controller_searchmenu');
        if (!empty($_SESSION['USER_ID'])) {
            $allowed_activities =
                 $this->model("user")->getUserActivities($_SESSION['USER_ID']);
            $data['COMPONENT_ACTIVITIES'] =
                AdminController::computeComponentActivities(
                $allowed_activities);
        }
    }
    /**
     * @param array $data
     * @param array $subsearches
     */
    public function addLandingHighlights(&$data, $subsearches)
    {
        $locale_tag = L\getLocaleTag();
        $trend_dir = C\WORK_DIRECTORY . "/cache/trends";
        $highlights_file = "$trend_dir/highlights_{$locale_tag}.txt";
        if (file_exists($highlights_file) &&
            filemtime($highlights_file) + C\ONE_HOUR > time()) {
            $data['LANDING_HIGHLIGHTS'] =
                unserialize($this->web_site->fileGetContents($highlights_file));
            $data['NUM_HIGHLIGHTS'] = count($data['LANDING_HIGHLIGHTS']);
            return;
        }
    }
    /**
     * Only used for serial network queries
     * Used to check if there are any mirrors of the current server.
     * If so, it tries to distribute the query requests randomly amongst
     * the mirrors and itself. To determine if there are mirrors of the
     * current server it looks in a mirror_table.txt file for machines that
     * have notified this machine they are mirroring it.
     *
     * @return bool whether or not a mirror of the current site handled it
     */
    public function mirrorHandle()
    {
        if (empty($_REQUEST['f']) || $_REQUEST['f'] != 'serial') {
            return false;
        }
        $mirror_table_name = C\CRAWL_DIR . "/" . self::mirror_table_name;
        $handled = false;
        if (file_exists($mirror_table_name)) {
            $mirror_table = unserialize($this->web_site->fileGetContents(
                $mirror_table_name));
            $mirrors = [];
            $time = time();
            foreach ($mirror_table['machines'] as $entry) {
                if ($time - $entry[2] < 2 * C\MIRROR_NOTIFY_FREQUENCY) {
                    if ($entry[0] == "::1") {
                        $entry[0] = "[::1]";
                    }
                    /* assume mirror uses same scheme as machine mirroring
                     * i.e., http or https
                     */
                    $request = UrlParser::getScheme(C\BASE_URL) . '://'.
                        $entry[0] . $entry[1];
                    $mirrors[] = $request;
                }
            }
            $count = count($mirrors);
            if ($count > 0 ) {
                mt_srand();
                $rand = mt_rand(0, $count);
                // if ==$count, we'll let the current machine handle it
                if ($rand < $count) {
                    $request = $mirrors[$rand] . "?" .
                        $_SERVER["QUERY_STRING"] . "&mirror=true";
                    if (strpos($_SERVER["QUERY_STRING"], "network=") === false){
                        $request .= "&network=false";
                    }
                    if (empty(ini_get('zlib.output_compression')) &&
                        !$this->web_site->isCli()) {
                        ob_start("ob_gzhandler");
                        $this->web_site->header("Content-Type: text/plain");
                        e(FetchUrl::getPage($request));
                        ob_end_flush();
                    } else {
                        $this->web_site->header("Content-Type: text/plain");
                        $this->web_site->header("Content-Length: " .
                            strlen($data));
                        e(FetchUrl::getPage($request));
                        flush();
                    }
                    $handled = true;
                }
            }
        }
        return $handled;
    }
    /**
     * Searches the database for the most relevant pages for the supplied search
     * terms. Renders the results to the HTML page.
     *
     * @param array &$data an array of view data that will be updated to include
     *     at most results_per_page many search results
     * @param string $query a string containing the words to search on
     * @param string $activity besides a straight search for words query,
     *     one might have other searches, such as a search for related pages.
     *     this argument says what kind of search to do.
     * @param string $arg for a search other than a straight word query this
     *     argument provides auxiliary information on how to conduct the
     *     search. For instance on a related web page search, it might provide
     *     the url of the site with which to perform the related search.
     * @param int $results_per_page the maixmum number of search results
     *     that can occur on a page
     * @param int $limit the first page of all the pages with the query terms
     *     to return. For instance, if 10 then the tenth highest ranking page
     *     for those query terms will be return, then the eleventh, etc.
     * @param int $index_name the timestamp of an index to use, if 0 then
     *     default used
     * @param int $raw ($raw == 0) normal grouping, $raw > 0
     *     no grouping done on data. If $raw == 1 no summary returned (used
     *     with f=serial, end user probably does not want)
     *     In this case, will get offset, generation, etc so could later lookup
     * @param mixed $save_timestamp if this timestamp is nonzero, then save
     *     iterate position, so can resume on future queries that make
     *     use of the timestamp. $save_time_stamp may also be in the format
     *     of string timestamp-query_part to handle networked queries involving
     *     presentations
     */
    public function processQuery(&$data, $query, $activity, $arg,
        $results_per_page, $limit = 0, $index_name = 0, $raw = 0,
        $save_timestamp = 0)
    {
        $no_index_given = false;
        $crawl_model = $this->model("crawl");
        $phrase_model = $this->model("phrase");
        $verticals_model = $this->model("searchverticals");
        if ($index_name == 0) {
            $index_name = $crawl_model->getCurrentIndexDatabaseName();
            if (!$index_name) {
                $pattern = "/(\s)((i:|index:)(\S)+)/";
                $indexes = preg_grep($pattern, [$query]);
                if (isset($indexes[0])) {
                    $index_name = $indexes[0];
                } else {
                    $no_index_given = true;
                }
            }
        }
        $is_mix = $crawl_model->isCrawlMix($index_name);
        if ($no_index_given) {
            $data["ERROR"] = tl('search_controller_no_index_set');
            $data['SCRIPT'] =
                    "doMessage('<h1 class=\"red\" >".
                    tl('search_controller_no_index_set').
                    "</h1>');";
            $data['RAW'] = $raw;
            return $data;
        }
        $phrase_model->index_name = $index_name;
        $phrase_model->additional_meta_words = [];
        foreach ($this->getIndexingPluginList() as $plugin) {
            $tmp_meta_words = $this->plugin($plugin)->getAdditionalMetaWords();
            $phrase_model->additional_meta_words =
                array_merge($phrase_model->additional_meta_words,
                    $tmp_meta_words);
        }
        $crawl_model->index_name = $index_name;
        $original_query = $query;
        list($query, $raw, $use_network, $use_cache_if_possible,
            $guess_semantics) =
                $this->calculateControlWords($query, $raw, $is_mix,
                $index_name);
        $index_archive_name = self::index_data_base_name . $index_name;
        if (file_exists(C\CRAWL_DIR.
            "/cache/$index_archive_name/no_network.txt")) {
            $_REQUEST['network'] = false;
            //if default index says no network queries then no network queries
        }
        $add_query_impression = false;
        if ($use_network &&
            (!isset($_REQUEST['network']) || $_REQUEST['network'] == "true")) {
            $queue_servers = $this->model("machine")->getQueueServerUrls();
            $add_query_impression = true;
        } else {
            $queue_servers = [];
        }
        if (isset($_REQUEST['guess']) &&  $_REQUEST['guess'] == "false") {
            $guess_semantics = false;
        }
        switch ($activity) {
            case "related":
                $data['QUERY'] = "related:$arg";
                $url = $arg;
                $crawl_item = $crawl_model->getCrawlItem($url,
                    $queue_servers);
                $top_phrases  =
                    $this->getTopPhrases($crawl_item, 3, $index_name);
                $top_query = implode(" ", $top_phrases);
                $phrase_results = $phrase_model->getPhrasePageResults(
                    $top_query, $limit, $results_per_page, false,
                    $verticals_model, $use_cache_if_possible, $raw,
                    $queue_servers, $guess_semantics, $save_timestamp);
                $data['PAGING_QUERY']['a'] = 'related';
                $data['PAGING_QUERY']['arg'] = $url;
                if (!empty($this->subsearch_name)) {
                    $data['PAGING_QUERY']['s'] = $this->subsearch_name;
                }
                $data['QUERY'] = urlencode($data['QUERY']);
                break;
            case "query":
                // no break
            default:
                if (trim($query) != "") {
                    $to_show = ($results_per_page > 0) ? $results_per_page :
                        ((!empty($this->subsearch_per_page) &&
                        $this->subsearch_per_page > 0) ?
                        $this->subsearch_per_page: C\NUM_RESULTS_PER_PAGE);
                    $phrase_results =
                        $phrase_model->getPhrasePageResults(
                            $query, $limit, $to_show, true, $verticals_model,
                            $use_cache_if_possible, $raw, $queue_servers,
                            $guess_semantics, $save_timestamp);
                    $query = $original_query;
                    if ($limit == 0) {
                        $callout_info = $verticals_model->getKnowledgeWiki(
                            $original_query, L\getLocaleTag());
                    }
                    $data['SEARCH_CALLOUT'] = "";
                    if (!empty($callout_info)) {
                        list( , $callout) =
                            $this->parsePageHeadVars(
                                $callout_info['PAGE'], true);
                        $data['SEARCH_CALLOUT'] = $callout;
                    }
                }
                $data['PAGING_QUERY']['q'] = $query;
                if (!empty($this->subsearch_name)) {
                    $data['PAGING_QUERY']['s'] =  $this->subsearch_name;
                }
                $data['QUERY'] = $query;
                if ((php_sapi_name() != 'cli' ||
                    C\nsdefined("IS_OWN_WEB_SERVER")) &&
                    C\nsdefined("MONETIZATION_TYPE") &&
                    in_array(C\MONETIZATION_TYPE, ['keyword_advertisements',
                    'fees_and_keywords'])) {
                    $data['ELEMENT'] = "displayadvertisement";
                    $advertisement_model = $this->model("advertisement");
                    if (isset($_REQUEST['a']) &&
                        $_REQUEST['a'] == 'recordClick') {
                        $advertisement_model->addClick($arg);
                    } else {
                        $data['RELEVANT_ADVERTISEMENT'] =
                            $advertisement_model->getRelevantAdvertisement(
                            $query);
                        if (empty($data['RELEVANT_ADVERTISEMENT']['ID'])) {
                            $ad_interpolant = $query;
                            if (!empty($this->subsearch_name)) {
                                $ad_interpolant = $this->model(
                                    "source")->getSubsearchName(
                                    $this->subsearch_name,
                                    L\getLocaleTag());
                            }
                            $data['RELEVANT_ADVERTISEMENT']['ID'] = -1;
                            $data['RELEVANT_ADVERTISEMENT']['NAME'] =
                                tl('search_controller_get_keyword_ads',
                                $ad_interpolant );
                            $data['RELEVANT_ADVERTISEMENT']['DESCRIPTION'] =
                                tl('search_controller_ad_keyword_description');
                            $data['RELEVANT_ADVERTISEMENT']['DESTINATION'] =
                                B\directUrl("advertise", false, true);
                        } else {
                            $advertisement_model->addImpression(
                                $data['RELEVANT_ADVERTISEMENT']['ID']);
                        }
                    }
                }
                break;
        }
        if (!empty($data["ADMIN"])) {
            $data['PAGING_QUERY'][C\CSRF_TOKEN] = $data[C\CSRF_TOKEN];
        }
        $data['PAGING_QUERY']['its'] = empty($data['its']) ? 0 : $data['its'];
        if (C\nsdefined("REDIRECTS_ON") && C\REDIRECTS_ON &&
            !empty($data['PAGING_QUERY']['s']) &&
            strstr($_SERVER['REQUEST_URI'], 's/'.$data['PAGING_QUERY']['s'])) {
            unset($data['PAGING_QUERY']['s']);
        }
        $data['RAW'] = $raw;
        $data['PAGES'] = (isset($phrase_results['PAGES'])) ?
             $phrase_results['PAGES']: [];
        $data['BEST_ANSWER'] = (isset($phrase_results['BEST_ANSWER'])) ?
            $phrase_results['BEST_ANSWER'] : null;
        $data['SAVE_POINT'] = (isset($phrase_results["SAVE_POINT"])) ?
             $phrase_results["SAVE_POINT"]: [ 0 => 1];
        if (isset($phrase_results["HARD_QUERY"])) {
            $data['HARD_QUERY'] = $phrase_results["HARD_QUERY"];
        }
        $data['TOTAL_ROWS'] = (isset($phrase_results['TOTAL_ROWS'])) ?
            $phrase_results['TOTAL_ROWS'] : 0;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        if ($add_query_impression && !empty($data['QUERY'])) {
            $this->model("impression")->addQueryImpression($data['QUERY']);
        }
    }
    /**
     * Extracts from the query string any control words:
     * mix:, m:, raw:, no: and returns an array consisting
     * of the query with these words removed, and then variables
     * for their values.
     *
     * @param string $query original query string
     * @param bool $raw the $_REQUEST['raw'] value
     * @param bool $is_mix if the current index name is that of a crawl mix
     * @param string $index_name timestamp of current mix or index
     *
     * @return array ($query, $raw, $use_network,
     *     $use_cache_if_possible, $guess_semantics)
     */
    public function calculateControlWords($query, $raw, $is_mix, $index_name)
    {
        $original_query = $query;
        $crawl_model = $this->model("crawl");
        if (trim($query) != "") {
            if ($this->subsearch_identifier != "") {
                $replace = " {$this->subsearch_identifier}";
                $query = preg_replace('/\|/', "$replace |", $query);
                $query .= " $replace";
            }
        }
        $query = " $query";
        $mix_metas = ["m:", "mix:"];
        foreach ($mix_metas as $mix_meta) {
            $pattern = "/(\s)($mix_meta(\S)+)/";
            preg_match_all($pattern, $query, $matches);
            if (isset($matches[2][0]) && !isset($mix_name)) {
                $mix_name = substr($matches[2][0],
                    strlen($mix_meta));
                $mix_name = str_replace("+", " ", $mix_name);
                break; // only one mix and can't be nested
            }
        }
        $query = preg_replace($pattern, "", $query);
        if (isset($mix_name)) {
            if (is_numeric($mix_name)) {
                $is_mix = true;
                $index_name = $mix_name;
            } else {
                $tmp = $crawl_model->getCrawlMixTimestamp(
                    $mix_name);
                if ($tmp != false) {
                    $index_name = $tmp;
                    $is_mix = true;
                }
            }
        }
        if ($is_mix) {
            $mix = $crawl_model->getCrawlMix($index_name);
            $query =
                $this->model("phrase")->rewriteMixQuery($query, $mix);
        }
        $pattern = "/(\s)(raw:(\S)+)/";
        preg_match_all($pattern, $query, $matches);
        if (isset($matches[2][0])) {
            $raw = substr($matches[2][0], 4);
            $raw = ($raw > 0) ? 2 : 0;
        }
        $query = preg_replace($pattern, "", $query);
        $original_query = $query;
        $query = preg_replace('/no:cache/', "", $query);
        $use_cache_if_possible = ($original_query == $query) ? true : false;
        $network_work_query = $query;
        $query = preg_replace('/no:network/', "", $query);
        /* if $use_network is true, then if there are multiple machine
           we can try to use them. It doesn't say that a network query
           must happen. If it is false, however, a network query should not
           happen.
         */
        $use_network = ($network_work_query == $query) ? true : false;
        $guess_query = $query;
        $query = preg_replace('/no:guess/', "", $query);
        $guess_semantics = ($guess_query == $query) ? true : false;
        $locale = L\getLocaleTag();
        $locale_major = (explode("-", $locale))[0];
        $query = preg_replace('/lang:default-major/ui', "lang:" .
            $locale_major, $query);
        $query = preg_replace('/lang:default/u', "lang:" . $locale, $query);
        $query = preg_replace('/highlight:\w+/ui', "", $query);
        return [$query, $raw, $use_network,
            $use_cache_if_possible, $guess_semantics];
    }
    /**
     * Groups search result pages together which have thumbnails
     * from an array of search pages. Grouped thumbnail pages stored at array
     * index of first thumbnail found, non thumbnail pages stored where were
     * before
     *
     * @param $pages an array of search result pages to group those pages
     *     with thumbs within
     * @return array $pages after the grouping has been done
     */
    public function makeMediaGroups($pages)
    {
        $first_image = -1;
        $first_feed_item = -1;
        $out_pages = [];
        foreach ($pages as $page) {
            if (isset($page[self::THUMB]) && $page[self::THUMB] != 'null'
                && empty($page[self::THUMB_URL])) {
                if ($first_image == -1) {
                    $first_image = count($out_pages);
                    $out_pages[$first_image]['IMAGES'] = [];
                }
                $out_pages[$first_image]['IMAGES'][] = $page;
            } else if (!empty($page['CATEGORY']) &&
                $page['CATEGORY'] == 'news') {
                if ($first_feed_item == -1) {
                    $first_feed_item = count($out_pages);
                    $out_pages[$first_feed_item]['FEED'] = [];
                }
                $out_pages[$first_feed_item]['FEED'][] = $page;
            } else {
                $out_pages[] = $page;
            }
        }
        return $out_pages;
    }
    /**
     * Given a page summary extract the words from it and try to find documents
     * which match the most relevant words. The algorithm for "relevant" is
     * pretty weak. For now we pick the $num many words whose ratio
     * of number of occurrences in crawl item/ number of occurrences in all
     * documents is the largest
     *
     * @param string $crawl_item a page summary
     * @param int $num number of key phrase to return
     * @param int $crawl_time the timestamp of an index to use, if 0 then
     *     default used
     * @return array  an array of most selective key phrases
     */
    public function getTopPhrases($crawl_item, $num, $crawl_time = 0)
    {
        $crawl_model = $this->model("crawl");
        $queue_servers = $this->model("machine")->getQueueServerUrls();
        if ($crawl_time == 0) {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
        $this->model("phrase")->index_name = $crawl_time;
        $crawl_model->index_name = $crawl_time;
        $phrase_string =
            PhraseParser::extractWordStringPageSummary($crawl_item);
        $crawl_item[self::LANG] = (isset($crawl_item[self::LANG])) ?
            $crawl_item[self::LANG] : C\DEFAULT_LOCALE;
        $stop_obj = PhraseParser::getTokenizer($crawl_item[self::LANG]);
        if ($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
            $phrase_string = $stop_obj->stopwordsRemover($phrase_string);
        }
        $page_word_counts =
            PhraseParser::extractPhrasesAndCount($phrase_string,
                $crawl_item[self::LANG]);
        arsort($page_word_counts);
        $top_phrases = array_keys(array_slice($page_word_counts, 0, $num));
        return $top_phrases;
    }
    /**
     * This method is responsible for parsing out the kind of query
     * from the raw query string
     *
     * This method parses the raw query string for query activities.
     * It parses the name of each activity and its argument
     *
     * @return array list of search activities parsed out of the search string
     */
    public function extractActivityQuery() {
        $query = (isset($_REQUEST['q'])) ? $_REQUEST['q'] : "";
        $query = mb_ereg_replace("(\s)+", " ", $query);
        $query = mb_ereg_replace("\s:", ":", $query);
        $query = mb_ereg_replace(":\s", ":", $query);
        $query_parts = mb_split(" ", $query);
        $count = count($query_parts);
        $out_query = "";
        $activity = "query";
        $arg = "";
        $space = "";
        for ($i = 0; $i < $count; $i++) {
            foreach ($this->activities as $a_activity) {
                $in_pos = mb_strpos($query_parts[$i], "$a_activity:");
                if ($in_pos !== false &&  $in_pos == 0) {
                    $out_query = "";
                    $activity = $a_activity;
                    $arg = mb_substr($query_parts[$i], strlen("$a_activity:"));
                    if ($activity == "trending") {
                        $arg_parts = explode(":", $arg);
                        $_REQUEST['category'] = $arg_parts[0];
                        $_REQUEST['order'] = (empty($arg_parts[1])) ?
                            "" : $arg_parts[1];
                    }
                    if ($activity == "chart") {
                        $activity = "trending";
                        $query_dates = [ 'h' => C\ONE_HOUR, 'd' => C\ONE_DAY,
                            'w' => C\ONE_WEEK, 'm' => C\ONE_MONTH,
                            'y' => C\ONE_YEAR];
                        $chart_parts = explode(":", $arg);
                        $arg = "chart";
                        $_REQUEST['category'] = $chart_parts[0];
                        $_REQUEST["period"] = (isset($query_dates[
                            $chart_parts[1]])) ? $query_dates[$chart_parts[1]] :
                            C\ONE_DAY;
                        $terms = [];
                        if (!empty($chart_parts[2])) {
                            $pre_terms = array_slice($chart_parts, 2);
                            foreach ($pre_terms as $pre_term) {
                                $terms[preg_replace("/\_/u", " ",
                                    $pre_term)] = true;
                            }
                        }
                        $_REQUEST['term'] = empty($_REQUEST['term']) ? $terms :
                            array_merge($_REQUEST['term'], $terms);
                    }
                    continue;
                }
            }
            $out_query .= $space . $query_parts[$i];
            $space = " ";
        }
        return [$out_query, $activity, $arg];
    }
    /**
     * Used in rendering a cached web page to highlight the search terms.
     *
     * @param object $node DOM object to mark html elements of
     * @param array $words an array of words to be highlighted
     * @param object $dom a DOM object for the whole document
     * @return object the node modified to now have highlighting
     */
    public function markChildren($node, $words, $dom)
    {
        if (!isset($node->childNodes->length) ||
            get_class($node) != 'DOMElement') {
            return $node;
        }
        for ($k = 0; $node->childNodes->length; $k++)  {
            if (!$node->childNodes->item($k)) {
                break;
            }
            $clone = $node->childNodes->item($k)->cloneNode(true);
            if ($clone->nodeType == XML_TEXT_NODE) {
                $text = $clone->textContent;
                foreach ($words as $word) {
                    //only mark string of length at least 2
                    if (mb_strlen($word) > 1) {
                        $mark_prefix = L\crawlHash($word);
                        if (stristr($mark_prefix, $word) !== false) {
                            $mark_prefix = preg_replace(
                            "/\b$word.{0,3}?\b/i", '', $mark_prefix);
                        }
                        $text = preg_replace(
                            "/\b$word.{0,3}?\b/i", $mark_prefix.'$0', $text);
                    }
                }
                $text_node =  $dom->createTextNode($text);
                $node->replaceChild($text_node, $node->childNodes->item($k));
            } else {
                $clone = $this->markChildren($clone, $words, $dom);
                $node->replaceChild($clone, $node->childNodes->item($k));
            }
        }
        return $node;
    }
    /**
     * Make relative links canonical with respect to provided $url
     * for links appear within the Dom node.
     *
     * @param object $node dom node to fix links for
     * @param string $url url to use to canonicalize links
     * @return object updated dom node
     */
    public function canonicalizeLinks($node, $url)
    {
        if (!isset($node->childNodes->length) ||
            get_class($node) != 'DOMElement') {
            return $node;
        }
        for ($k = 0; $k < $node->childNodes->length; $k++) {
            if (!$node->childNodes->item($k)) { break; }
            $clone = $node->childNodes->item($k)->cloneNode(true);
            $tag_name = (isset($clone->tagName) ) ? $clone->tagName : "-1";
            if (in_array($tag_name, ["a", "link"])) {
                if ($clone->hasAttribute("href")) {
                    $href = $clone->getAttribute("href");
                    if ($href !="" && $href[0] != "#") {
                        $href = UrlParser::canonicalLink($href, $url, false);
                    }
                    /*
                        Modify non-link tag urls so that they are looked up in
                        the cache before going to the live site
                     */
                    if ($tag_name != "link" &&
                        ($href == "" || $href[0] != "#")) {
                        $href = urlencode($href);
                        $href = $href."&from_cache=true";
                        $crawl_time = $this->getIndexTimestamp();
                        $href = $this->baseLink()."&a=cache&q&arg".
                            "=$href&its=$crawl_time";
                    }
                    $clone->setAttribute("href", $href);
                    //an anchor might have an img tag within it so recurses
                    $clone = $this->canonicalizeLinks($clone, $url);
                    $node->replaceChild($clone, $node->childNodes->item($k));
                }
            } else if (in_array($tag_name, ["img", "object", "script"])) {
                if ($clone->hasAttribute("src")) {
                    $src = $clone->getAttribute("src");
                    $src = UrlParser::canonicalLink($src, $url, false);
                    $clone->setAttribute("src", $src);
                    $node->replaceChild($clone, $node->childNodes->item($k));
                }
            } else {
                if ($tag_name != -1) {
                    $clone = $this->canonicalizeLinks($clone, $url);
                    if (is_object($clone)) {
                        $node->replaceChild($clone,
                            $node->childNodes->item($k));
                    }
                }
            }
        }
        return $node;
    }
    //*********BEGIN SEARCH API *********
    /**
     * Part of Yioop Search API. Performs a normal search query and returns
     * associative array of query results
     *
     * @api
     * @param string $query this can be any query string that could be
     *     entered into the search bar on Yioop (other than related: and
     *     cache: queries)
     * @param int $results_per_page number of results to return
     * @param int $limit first result to return from the ordered query results
     * @param int $grouping ($grouping == 0) normal grouping of links
     *     with associated document, ($grouping > 0)
     *     no grouping done on data
     * @param int $save_timestamp if this timestamp is nonzero, then save
     *     iterate position, so can resume on future queries that make
     *     use of the timestamp
     *
     * @return array associative array of results for the query performed
     */
    public function queryRequest($query, $results_per_page, $limit = 0,
        $grouping = 0, $save_timestamp = 0)
    {
        if (!C\API_ACCESS) {
            return null;
        }
        $grouping = ($grouping > 0 ) ? 2 : 0;
        $data = [];
        $this->processQuery($data, $query, "query", "", $results_per_page,
                $limit, 0, $grouping, $save_timestamp);
        return $data;
    }
    /**
     * Query timestamps can be used to save an iteration position in a
     * a set of query results. This method allows one to delete
     * the supplied save point.
     *
     * @param int $save_timestamp deletes a previously query saved timestamp
     */
    public function clearQuerySavepoint($save_timestamp)
    {
        $this->model("phrase")->clearQuerySavePoint($save_timestamp);
    }
    /**
     * Part of Yioop Search API. Performs a related to a given url
     * search query and returns associative array of query results
     *
     * @api
     * @param string $url to find related documents for
     * @param int $results_per_page number of results to return
     * @param int $limit first result to return from the ordered query results
     * @param string $crawl_time timestamp of crawl to look for related request
     * @param int $grouping ($grouping == 0) normal grouping of links
     *     with associated document, ($grouping > 0)
     *     no grouping done on data
     * @param int $save_timestamp if this timestamp is nonzero, then save
     *     iterate position, so can resume on future queries that make
     *     use of the timestamp
     *
     * @return array associative array of results for the query performed
     */
    public function relatedRequest($url, $results_per_page, $limit = 0,
        $crawl_time = 0, $grouping = 0, $save_timestamp = 0)
    {
        if (!C\API_ACCESS) {return null; }
        $grouping = ($grouping > 0 ) ? 2 : 0;
        $data = [];
        $this->processQuery($data, "", "related", $url, $results_per_page,
            $limit, $crawl_time, $grouping, $save_timestamp);
        return $data;
    }
    /**
     * Part of Yioop Search API. Performs a related to a given url
     * search query and returns associative array of query results
     *
     * @api
     * @param string $url to get cached page for
     * @param array $ui_flags array of  ui features which
     *     should be added to the cache page. For example, "highlight"
     *     would way search terms should be highlighted, "history"
     *     says add history navigation for all copies of this cache page in
     *     yioop system.
     * @param string $terms space separated list of search terms
     * @param string $crawl_time timestamp of crawl to look for cached page in
     * @return string with contents of cached page
     */
    public function cacheRequest($url, $ui_flags = [], $terms ="",
        $crawl_time = 0)
    {
        if (!C\API_ACCESS) return false;
        ob_start();
        $this->cacheRequestAndOutput($url, $ui_flags, $terms,
            $crawl_time);
        $cached_page = ob_get_contents();
        ob_end_clean();
        return $cached_page;
    }
    //*********END SEARCH API *********
    /**
     * Used to get and render a cached web page
     *
     * @param string $url the url of the page to find the cached version of
     * @param array $ui_flags array of  ui features which
     *     should be added to the cache page. For example, "highlight"
     *     would say search terms should be highlighted, "history"
     *     says add history navigation for all copies of this cache page in
     *     yioop system. "summaries" says add a toggle headers and extracted
     *     summaries link. "cache_link_referrer" says a link on a cache page
     *     referred us to the current cache request
     * @param string $terms from orginal query responsible for cache request
     * @param int $crawl_time the timestamp of the crawl to look up the cached
     *     page in
     */
   function cacheRequestAndOutput($url, $ui_flags = [], $terms ="",
        $crawl_time = 0)
    {
        $crawl_model = $this->model("crawl");
        //we always lower case urls even if might cause slight ambiguity
        $url = mb_strtolower($url);
        $cache = $crawl_model::$cache;
        $flag = 0;
        $crawl_item = null;
        $all_future_times = [];
        $all_crawl_times = [];
        $all_past_times = [];
        //Check if the URL is from a cached page
        $cached_link = (in_array("cache_link_referrer", $ui_flags)) ?
            true : false;
        $hash_key = L\crawlHash(
            $terms . $url . serialize($ui_flags) . serialize($crawl_time));
        if (!empty($_SERVER["USE_CACHE"])) {
            if ($new_doc = $cache->get($hash_key)) {
                echo $new_doc;
                return;
            }
        }
        $queue_servers = $this->model("machine")->getQueueServerUrls();
        if ($crawl_time == 0) {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
        //Get all crawl times
        $crawl_times = [];
        $all_crawl_details = $crawl_model->getCrawlList(false, true,
            $queue_servers);
        foreach ($all_crawl_details as $crawl_details) {
            if ($crawl_details['CRAWL_TIME'] != 0) {
                array_push($crawl_times, $crawl_details['CRAWL_TIME']);
            }
        }
        for ($i = 0; $i < count($crawl_times); $i++) {
            $crawl_times[$i] = intval($crawl_times[$i]);
        }
        asort($crawl_times);
        //Get int value of current crawl time for comparison
        $crawl_time_int = intval($crawl_time);
        /* Search for all crawl times containing the cached
          version of the page for $url for multiple and single queue servers */
        list($network_crawl_times, $network_crawl_items) = $this->
            getCrawlItems($url, $crawl_times, $queue_servers);
        $nonnet_crawl_times = array_diff($crawl_times,
            $network_crawl_times);
        if (count($nonnet_crawl_times) > 0) {
            list($nonnet_crawl_times, $nonnet_crawl_items) = $this->
                getCrawlItems($url, $nonnet_crawl_times, null);
        } else {
            $nonnet_crawl_items = [];
        }
        $nonnet_crawl_times = array_diff($nonnet_crawl_times,
            $network_crawl_times);
        $all_crawl_times = array_values(array_merge($nonnet_crawl_times,
            $network_crawl_times));
        sort($all_crawl_times, SORT_STRING);
        //Get past and future crawl times
        foreach ($all_crawl_times as $time) {
            if ($time >= $crawl_time_int) {
                array_push($all_future_times, $time);
            } else {
                array_push($all_past_times, $time);
            }
        }
        /*Get the nearest timestamp (future or past)
         *Check in future first and if not found, check in past
         */
        if (!empty($all_future_times)) {
            $crawl_time = array_shift($all_future_times);
            array_push($all_future_times, $crawl_time);
            sort($all_future_times, SORT_STRING);
            if (in_array($crawl_time, $network_crawl_times)){
                $queue_servers = $network_crawl_items['queue_servers'];
            } else {
                $queue_servers = $nonnet_crawl_items['queue_servers'];
            }
        } else if (!empty($all_past_times)) {
            $crawl_time = array_pop($all_past_times);
            array_push($all_past_times, $crawl_time);
            sort($all_past_times, SORT_STRING);
            if (in_array($crawl_time, $network_crawl_times)){
                $queue_servers = $network_crawl_items['queue_servers'];
            } else {
                $queue_servers = $nonnet_crawl_items['queue_servers'];
            }
        }
        $this->model("phrase")->index_name = $crawl_time;
        $crawl_model->index_name = $crawl_time;
        $crawl_item = $crawl_model->getCrawlItem($url, $queue_servers);
        // A crawl item is able to override the default UI_FLAGS
        if (isset($crawl_item[self::UI_FLAGS]) &&
            is_string($crawl_item[self::UI_FLAGS])) {
            $ui_flags = explode(",", $crawl_item[self::UI_FLAGS]);
        }
        $data = [];
        if ($crawl_item == null) {
            if ($cached_link == true) {
                $this->web_site->header("Location: $url");
            } else {
                $data["URL"] = $url;
                $this->displayView("nocache", $data);
                return;
            }
        }
        $check_fields = [self::TITLE, self::DESCRIPTION, self::LINKS];
        foreach ($check_fields as $field) {
            $crawl_item[$field] = (isset($crawl_item[$field])) ?
                $crawl_item[$field] : "";
        }
        $summary_string =
            $this->crawlItemSummary($crawl_item);
        $robot_instance = $crawl_item[self::ROBOT_INSTANCE];
        $robot_table_name = C\CRAWL_DIR . "/" . self::robot_table_name;
        $robot_table = [];
        if (file_exists($robot_table_name)) {
            $robot_table = unserialize($this->web_site->fileGetContents(
                $robot_table_name));
        }
        if (isset($robot_table[$robot_instance])) {
            $machine = $robot_table[$robot_instance][0];
            $machine_uri = $robot_table[$robot_instance][1];
        } else {
            //guess we are in a single machine setting
            $machine = UrlParser::getHost(C\NAME_SERVER);
            if ($machine[4] == 's') { // start with https://
                $machine = substr($machine, 8);
            } else { // start with http://
                $machine = substr($machine, 7);
            }
            $machine_uri = C\WEB_URI;
        }
        $instance_parts = explode("-", $robot_instance);
        $instance_num = false;
        if (count($instance_parts) > 1) {
            $instance_num = intval($instance_parts[0]);
        }
        if (!empty($crawl_item[self::PAGE])) {
            // Version 2 or newer index doesn't store cache pages separately
            $cache_item = $crawl_item;
        } else if (!empty($crawl_item[self::OFFSET])) {
            $cache_partition = $crawl_item[self::CACHE_PAGE_PARTITION];
            $cache_item = $crawl_model->getCacheFile($machine,
                $machine_uri, $cache_partition, $crawl_item[self::OFFSET],
                $crawl_time, $instance_num);
        }
        if (!isset($cache_item[self::PAGE])) {
            $data["URL"] = $url;
            $data["SUMMARY_STRING"] =
                "\n\n". tl('search_controller_download_fetcher',
                $robot_instance) ."\n\n". $summary_string;
            $this->displayView("nocache", $data);
            return;
        }
        if (isset($crawl_item[self::ROBOT_METAS]) &&
                (in_array("NOARCHIVE", $crawl_item[self::ROBOT_METAS]) ||
                in_array("NONE", $crawl_item[self::ROBOT_METAS])) ) {
            $cache_file = "<div>'.
                tl('search_controller_no_archive_page').'</div>";
        } else {
            $cache_file = $cache_item[self::PAGE];
        }
        if (!empty($crawl_item[self::THUMB]) &&
            empty($crawl_item[self::THUMB_URL])) {
            $cache_file = $this->imageCachePage($url, $cache_item, $cache_file,
                $queue_servers);
            unset($ui_flags["highlight"]);
        }
        if (isset($crawl_item[self::KEYWORD_LINKS])) {
            $cache_item[self::KEYWORD_LINKS] = $crawl_item[self::KEYWORD_LINKS];
        }
        if (!isset($cache_item[self::ROBOT_INSTANCE])) {
            $cache_item[self::ROBOT_INSTANCE] = $robot_instance;
        }
        if (in_array('yioop_nav', $ui_flags) && !((isset($_SERVER['_']) &&
            stristr($_SERVER['_'], 'hhvm')) ||
            (isset($_SERVER['SERVER_SOFTWARE']) &&
            $_SERVER['SERVER_SOFTWARE'] == "HPHP"))) {
            $new_doc = $this->formatCachePage($cache_item, $cache_file, $url,
                $summary_string, $crawl_time, $all_crawl_times, $terms,
                $ui_flags);
        } else {
            $new_doc = $cache_file;
        }
        if (!empty($_SERVER["USE_CACHE"])) {
            $cache->set($hash_key, $new_doc);
        }
        echo $new_doc;
    }
    /**
     * Makes an HTML web page for an image cache item
     *
     * @param string $url original url of the image
     * @param array $cache_item details about the image item
     * @param string $cache_file string with image
     * @param $queue_servers machines used by yioop for the current index
     *     cache item is from. Used to find out urls on which image occurred
     * @return string an HTML page with the image embedded as a data url
     */
    public function imageCachePage($url, $cache_item, $cache_file,
        $queue_servers)
    {
        $inlinks = $this->model("phrase")->getPhrasePageResults(
            "link:$url", 0, 1, true, null, false, 0, $queue_servers);
        $in_url = isset($inlinks["PAGES"][0][self::URL]) ?
            $inlinks["PAGES"][0][self::URL] : "";
        $type = ($cache_item[self::TYPE] == 'image/svg+xml') ?
            "text/html" :  $cache_item[self::TYPE];
        $loc_url = ($in_url == "") ? $url : $in_url;
        $cache_file = "<!DOCTYPE html><html><head><title>" .
            tl('search_controller_site_cache') . "</title></head>".
            "<body><object onclick=\"document.location='$loc_url'\"".
            " data='data:$type;base64,".
            base64_encode($cache_file)."' type='$type' />";
        if ($loc_url != $url) {
            $cache_file .= "<p>".tl('search_controller_original_page').
                "<br /><a href='$loc_url'>$loc_url</a></p>";
        }
        $cache_file .= "</body></html>";
        return $cache_file;
    }
    /**
     * Generates a string representation of a crawl item suitable for
     * for output in a cache page
     *
     * @param array $crawl_item summary information of a web page (title,
     *     description, etc)
     * @return string suitable string formatting of item
     */
    public function crawlItemSummary($crawl_item)
    {
        $summary_string =
            tl('search_controller_extracted_title')."\n\n".
            L\utf8WordWrap($crawl_item[self::TITLE], 80, "\n")."\n\n" .
            tl('search_controller_extracted_description')."\n\n".
            L\utf8WordWrap($crawl_item[self::DESCRIPTION], 80, "\n")."\n\n".
            tl('search_controller_extracted_links')."\n\n".
            L\utf8WordWrap(print_r($crawl_item[self::LINKS], true), 80, "\n");
        if (isset($crawl_item[self::ROBOT_PATHS])) {
            if (isset($crawl_item[self::ROBOT_PATHS][self::ALLOWED_SITES])) {
                $summary_string =
                    tl('search_controller_extracted_allow_paths')."\n\n".
                    L\utf8WordWrap(print_r($crawl_item[self::ROBOT_PATHS][
                        self::ALLOWED_SITES], true),  80, "\n");
            }
            if (isset($crawl_item[self::ROBOT_PATHS][self::DISALLOWED_SITES])) {
                $summary_string =
                    tl('search_controller_extracted_disallow_paths')."\n\n".
                    L\utf8WordWrap(print_r($crawl_item[self::ROBOT_PATHS][
                        self::DISALLOWED_SITES], true),  80, "\n");
            }
            if (isset($crawl_item[self::CRAWL_DELAY])) {
                $summary_string =
                    tl('search_controller_crawl_delay')."\n\n".
                    L\utf8WordWrap(print_r($crawl_item[self::CRAWL_DELAY],
                        true), 80, "\n") ."\n\n". $summary_string;
            }
        }
        $meta_ids = PhraseParser::calculateMetas($crawl_item);
        if (empty($crawl_item[self::JUST_METAS])) {
            $host_words = UrlParser::getWordsInHostUrl($crawl_item[self::URL]);
            $path_words = UrlParser::getWordsLastPathPartUrl(
                $crawl_item[self::URL]);
            $phrase_string = $host_words . " .. " . $crawl_item[self::TITLE] .
                " ..  ". $path_words . " .. ". $crawl_item[self::DESCRIPTION];
            if (empty($crawl_item[self::LANG])) {
                $crawl_item[self::LANG] =
                    L\guessLocaleFromString($phrase_string);
            }
            $word_lists = PhraseParser::extractPhrasesInLists(
                $phrase_string, $crawl_item[self::LANG]);
            $len = strlen($phrase_string);
            if (PhraseParser::computeSafeSearchScore($word_lists['WORD_LIST'],
                $len, $crawl_item[self::URL]) < 0.012) {
                $meta_ids[] = "safe:true";
                $safe = true;
            } else {
                $meta_ids[] = "safe:false";
                $safe = false;
            }
        }
        $summary_string .= "\n\n" .
            tl('search_controller_extracted_meta_words') . "\n\n".
            implode("\n", $meta_ids);
        if (!empty($crawl_item[self::QUESTION_ANSWERS])) {
            $summary_string .= "\n\n" .
                tl('search_controller_extracted_q_a_s') . "\n\n".
                L\utf8WordWrap($this->clean(print_r(
                $crawl_item[self::QUESTION_ANSWERS], true), "string"), 75);
        }
        return $summary_string;
    }
    /**
     * Formats a cache of a web page (adds history ui and highlight keywords)
     *
     * @param array $cache_item details meta information about the cache page
     * @param string $cache_file contains current web page before formatting
     * @param string $url that cache web page was originally from
     * @param string $summary_string summary data that was extracted from the
     *     web page to be put in the actually inverted index
     * @param int $crawl_time timestamp of crawl cache page was from
     * @param array $all_crawl_times timestamps of all crawl times currently
     *     in Yioop system
     * @param string $terms from orginal query responsible for cache request
     * @param array $ui_flags array of  ui features which
     *     should be added to the cache page. For example, "highlight"
     *     would way search terms should be highlighted, "history"
     *     says add history navigation for all copies of this cache page in
     *     yioop system.
     * return string of formatted cached page
     */
    public function formatCachePage($cache_item, $cache_file, $url,
        $summary_string, $crawl_time, $all_crawl_times, $terms, $ui_flags)
    {
        set_error_handler(null);
        //Check if it the URL is from the UI
        $hist_ui_open = in_array("hist_ui_open", $ui_flags) ? true : false;
        $date = date ("F d Y H:i:s", $cache_item[self::TIMESTAMP]);
        $meta_words = PhraseParser::$meta_words_list;
        foreach ($meta_words as $meta_word) {
            $pattern = "/(\b)($meta_word(\S)+)/";
            $terms = preg_replace($pattern, "", $terms);
        }
        $terms = $this->clean(strtr($terms, ["'" => " ", '"' => " ",
            '\\' => " ", '|' => " "]), "string");
        $phrase_string = mb_ereg_replace("[[:punct:]]", " ", $terms);
        $words = mb_split(" ", $phrase_string);
        if (!in_array("highlight", $ui_flags)) {
            $words = [];
        }
        $dom = L\getDomFromString($cache_file);
        $head = $dom->getElementsByTagName('head')->item(0);
        $body = $dom->getElementsByTagName('body')->item(0);
        $html_node = $dom->getElementsByTagName('html')->item(0);
        if (is_object($html_node) && is_object($body)&& !is_object($head)) {
            //make a head if it doesn't exis, but rest of page like html
            $html_first_child = $html_node->firstChild;
            $head = $dom->createElement('head');
            $title = $dom->createElement('title');
            $text_node = $dom->createTextNode(
                tl('search_controller_site_cache'));
            $title->appendChild($text_node);
            $head->appendChild($title);
            $html_node->insertBefore($head, $html_first_child);
        }
        if (is_object($head)) {
            // add a noindex nofollow robot directive to page
            $head_first_child = $head->firstChild;
            $robot_node = $dom->createElement('meta');
            $robot_node = $head->insertBefore($robot_node, $head_first_child);
            $robot_node->setAttribute("name", "ROBOTS");
            $robot_node->setAttribute("content", "NOINDEX,NOFOLLOW");
            $comment = $dom->createComment(
                tl('search_controller_cache_comment'));
            $comment = $head->insertBefore($comment, $robot_node);
            // make link and script links absolute
            $head = $this->canonicalizeLinks($head, $url);
        } else {
            $body_tags = "<frameset><frame><noscript><img><span><b><i><em>".
                "<strong><h1><h2><h3><h4><h5><h6><p><div>".
                "<a><table><tr><td><th><dt><dir><dl><dd><pre>";
            $cache_file = strip_tags($cache_file, $body_tags);
            $cache_file = L\utf8WordWrap($cache_file, 80);
            $cache_file = "<html><head><title>".
                tl('search_controller_site_cache') . "</title></head>".
                "<body>" . $cache_file . "</body></html>";
            $dom = L\getDomFromString($cache_file);
        }
        $body =  $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $cache_file;
        }
        //make tags in body absolute
        $body = $this->canonicalizeLinks($body, $url);
        $first_child = $body->firstChild;
        $text_align = (L\getLocaleDirection() == 'ltr') ? "left" : "right";
        // add information about what was extracted from page
        if (in_array("summaries", $ui_flags)) {
            $summary_toggle_node = $this->createSummaryAndToggleNodes($dom,
                $text_align, $body, $summary_string, $cache_item);
        } else {
            $summary_toggle_node = $first_child;
        }
        if (isset($cache_item[self::KEYWORD_LINKS]) &&
            count($cache_item[self::KEYWORD_LINKS]) > 0) {
            $keyword_node = $this->createDomBoxNode($dom, $text_align,
                "position:relative;width:99%;z-index: 2000000000;");
            $text_node = $dom->createTextNode("Z@key_links@Z");
            $keyword_node->appendChild($text_node);
            $keyword_node = $body->insertBefore($keyword_node,
                $summary_toggle_node);
            $set_key_links = true;
        } else {
            $keyword_node = $summary_toggle_node;
            $set_key_links = false;
        }
        if (in_array("version", $ui_flags)) {
            $version_node =
                $this->createDomBoxNode($dom, $text_align,
                "position:relative;width:99%;z-index: 2000000000;");
            $text_node = $dom->createTextNode(
                    tl('search_controller_cached_version', "Z@url@Z", $date));
            $version_node->appendChild($text_node);
            $brNode = $dom->createElement('br');
            $version_node->appendChild($brNode);
            $this->addCacheJavascriptTags($dom, $version_node);
            $version_node = $body->insertBefore($version_node, $keyword_node);
        } else {
            $version_node = $keyword_node;
        }
        //UI for showing history
        if (in_array("history", $ui_flags)) {
            $history_node = $this->historyUI($crawl_time, $all_crawl_times,
                $version_node, $dom, $terms, $hist_ui_open, $url);
        } else {
            $history_node = $dom->createElement('div');
        }
        if ($history_node) {
            $version_node->appendChild($history_node);
        }
        $body = $this->markChildren($body, $words, $dom);
        $new_doc = @$dom->saveHTML();
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        if (substr($url, 0, 7) != "record:") {
            $url = "<a href='$url'>$url</a>";
        }
        $new_doc = str_replace("Z@url@Z", $url, $new_doc);
        $colors = ["yellow", "orange", "gray", "cyan"];
        $color_count = count($colors);

        $i = 0;
        foreach ($words as $word) {
            //only mark string of length at least 2
            if (mb_strlen($word) > 1) {
                $mark_prefix = L\crawlHash($word);
                if (stristr($mark_prefix, $word) !== false) {
                    $mark_prefix = preg_replace(
                    "/$word/i", '', $mark_prefix);
                }
                $match = $mark_prefix.$word;
                $new_doc = preg_replace("/$match/i",
                    '<span style="background-color:'.
                    $colors[$i].'">$0</span>', $new_doc);
                $i = ($i + 1) % $color_count;
                $new_doc = preg_replace("/".$mark_prefix."/", "", $new_doc);
            }
        }
        if ($set_key_links) {
            $new_doc = $this->addKeywordLinks($new_doc, $cache_item);
        }
        return $new_doc;
    }
    /**
     * Function used to add links for keyword searches in keyword_links
     * array of $cache_item to the text of the $web_page we are going to
     * display the cache of as part of a pache page request
     *
     * @param string $web_page to add links to
     * @param array $cache_item original cache item web page generated from
     * @return string modified web page
     */
    public function addKeywordLinks($web_page, &$cache_item)
    {
        $base = $this->baseLink()."&its=".$this->getIndexTimestamp();
        $link_list = "<ul>";
        foreach ($cache_item[self::KEYWORD_LINKS] as $keywords => $text) {
            $keywords = urlencode($keywords);
            $link_list .= "<li><a href='$base&q=$keywords' rel='nofollow'>".
                "$text</a></li>";
        }
        $link_list .= "</ul>";
        $web_page = str_replace("Z@key_links@Z", $link_list, $web_page);
        return $web_page;
    }
    /**
     * Creates the toggle link and hidden div for extracted header and
     * summary element on cache pages
     *
     * @param DOMDocument $dom used to create new nodes to add to body object
     *     for page
     * @param string $text_align whether rtl or ltr language
     * @param DOMElement $body represent body of cached page
     * @param string $summary_string header and summary that were extraced
     * @param array $cache_item contains infor about the cached item
     * @return DOMElement a div node with toggle link and hidden div
     */
    public function createSummaryAndToggleNodes($dom, $text_align, $body,
        $summary_string, $cache_item)
    {
        $first_child = $body->firstChild;
        $summary_node = $this->createDomBoxNode($dom, $text_align,
            "display:none;position:relative;z-index: 2000000000;", 'pre');
        $summary_node->setAttributeNS("","id", "summary-page-id");
        $summary_node = $body->insertBefore($summary_node, $first_child);
        $summary_string_prefix = "";
        if (isset($cache_item[self::ROBOT_INSTANCE])) {
            $summary_string_prefix =
                "\n\n". tl('search_controller_download_fetcher',
                $cache_item[self::ROBOT_INSTANCE]) ."\n\n";
        }
        if (isset($cache_item[self::HEADER])) {
            //without mb_convert_encoding get conv error when do saveHTML
            $summary_string = $summary_string_prefix .
                $cache_item[self::HEADER] . "\n".
                mb_convert_encoding($summary_string, "UTF-8", "UTF-8");
        }
        $text_node = $dom->createTextNode($summary_string);
        $summary_node->appendChild($text_node);
        $script_node = $dom->createElement('script');
        $script_node = $body->insertBefore($script_node, $summary_node);
        $text_node = $dom->createTextNode("var summary_show = 'none';");
        $script_node->appendChild($text_node);
        $a_div_node = $this->createDomBoxNode($dom, $text_align,
            "position:relative;z-index: 2000000000;");
        $a_node = $dom->createElement("a");
        $a_text_node =
            $dom->createTextNode(tl('search_controller_header_summaries'));
        $toggle_code = "javascript:".
            "summary_show = (summary_show != 'block') ? 'block' : 'none';".
            "summary_pid = elt('summary-page-id');".
            "summary_pid.style.display = summary_show;";
        $a_node->setAttributeNS("", "onclick", $toggle_code);
        $a_node->setAttributeNS("", "style",
            "text-decoration: underline; cursor: pointer");
        $a_node->appendChild($a_text_node);
        $a_div_node->appendChild($a_node);
        $body->insertBefore($a_div_node, $summary_node);
        return $a_div_node;
    }
    /**
     * Creates a bordered tag (usually div) in which to put meta content on a
     * page when it is displayed
     *
     * @param DOMDocument $dom representing cache page
     * @param string $text_align whether doc is ltr or rtl
     * @param string $more_styles any additional styles for box
     * @param string $tag base tag of box (default div)
     * @return DOMElement of styled box
     */
    public function createDomBoxNode($dom, $text_align, $more_styles="",
        $tag="div")
    {
        $div_node = $dom->createElement($tag);
        $div_node->setAttributeNS("","style", "border-color: black; ".
            "border-style:solid; border-width:3px; margin-bottom:10px;".
            "padding: 5px; background-color: white; ".
            "text-align:$text_align; $more_styles");
        return $div_node;
    }
    /**
     * Get crawl items based on queue server setting.
     *
     * @param string $url is the URL of the cached page
     * @param array $crawl_times is an array storing crawl times for all
     *     indexes
     * @param array $queue_servers is an array containing URLs for queue
     *     servers
     * @return array [$all_crawl_times, $all_crawl_items] is an array containing
     *     an array of crawl times and an array of their respective crawl items
     */
    public function getCrawlItems($url, $crawl_times, $queue_servers)
    {
        $all_crawl_times = [];
        $all_crawl_items = [];
        $crawl_model = $this->model("crawl");
        $all_crawl_items['queue_servers'] = $queue_servers;
        foreach ($crawl_times as $time) {
            $crawl_time = (string)$time;
            $crawl_model->index_name = $crawl_time;
            $crawl_item =
                $crawl_model->getCrawlItem($url, $queue_servers);
            if ($crawl_item != null) {
                array_push($all_crawl_times, $crawl_time);
                $all_crawl_items[$crawl_time] = $crawl_item;
            }
        }
        return [$all_crawl_times, $all_crawl_items];
    }
    /**
     * User Interface for history feature
     *
     * @param long $crawl_time is the crawl time
     * @param array $all_crawl_times is an array storing all crawl time
     * @param DOMElement $div_node is the section that contains the History UI
     * @param DOMDocument $dom is the DOM of the cached page
     * @param string $terms is a string containing query terms
     * @param boolean $hist_ui_open is a flag to check if History UI should be
     *     open by default
     * @param string $url is the URL of the page
     *
     * @return DOMElement the section containing the options for
     *     selecting year and month
     */
    public function historyUI($crawl_time, $all_crawl_times, $div_node, $dom,
        $terms, $hist_ui_open, $url)
    {
        //Guess locale for date localization
        $locale_type = L\getLocaleTag();
        //Create data structure that stores years months and associated links
        list($time_ds, $years, $months) = $this->
            createHistoryDataStructure($all_crawl_times, $locale_type, $url);
        //Access to history feature
        $this->toggleHistory($months, $div_node, $dom);
        //Display current year, current month and their respective links
        $current_date = L\formatDateByLocale($crawl_time, $locale_type);
        $current_components = explode(" ", $current_date);
        $current_year = $current_components[2];
        $current_month = $current_components[0];
        //UI for viewing links by selecting year and month
        $d1 = $this->viewLinksByYearMonth($years, $months, $current_year,
        $current_month, $time_ds, $dom);
        /*create divs for all year.month pairs and populate with links
         */
        $d1 = $this->createLinkDivs($time_ds, $current_year, $current_month,
            $d1, $dom, $url, $years, $hist_ui_open, $terms, $crawl_time);
        return $d1;
    }
    /**
     * The history toggle displays the year and month associated with
     * the timestamp at which the page was cached.
     * @param array $months used to store month names for which we have a cache
     * @param DOMElement $div_node is the section that contains the History UI
     * @param DOMDocument $dom is the DOM of the cached page
     */
    public function toggleHistory($months, $div_node, $dom)
    {
        $month_json = json_encode($months);
        $historyLink = $dom->createElement('a');
        $historyLink->setAttributeNS("", "id", "#history");
        $historyLink->setAttributeNS("", "months", $month_json);
        $historyLabel = $dom->createTextNode(tl('search_controller_history'));
        $historyLink->appendChild($historyLabel);
        $div_node->appendChild($historyLink);
        $historyLink->setAttributeNS('', 'style',
            "text-decoration: underline; cursor: pointer");
    }
    /**
     * Creates a data structure for storing years, months and associated
     * timestamp components
     * @param array $all_crawl_times is an array storing all crawl time
     * @param string $locale_type is the locale tag
     * @param string $url is the URL for the cached page
     * @return array $results is an array storing years array, months array
     * and the combined data structure for the History UI
     */
    public function createHistoryDataStructure($all_crawl_times, $locale_type,
        $url)
    {
        $years = [];
        $months = [];
        $time_components = [];
        $time_ds = [];
        //Initialize data structure
        if (!empty($all_crawl_times)){
            foreach ($all_crawl_times as $cache_time) {
                $date_time_string =
                    L\formatDateByLocale($cache_time, $locale_type);
                $time_components = explode(" ", $date_time_string);
                $time_ds[$time_components[2]][$time_components[0]] = null;
            }
        }
        //Populate data structure
        if (!empty($all_crawl_times)){
            foreach ($all_crawl_times as $cache_time){
                $date_time_string =
                    L\formatDateByLocale($cache_time, $locale_type);
                $time_components = explode(" ", $date_time_string);
                if (!in_array($time_components[2], $years)) {
                    array_push($years, $time_components[2]);
                }
                if (!in_array($time_components[0], $months)) {
                    array_push($months, $time_components[0]);
                }
                $temp = "$time_components[0] $time_components[1] ".
                        "$time_components[3] $url $cache_time";
                if ($time_ds[$time_components[2]][$time_components[0]]
                    === null) {
                    $time_ds[$time_components[2]][$time_components[0]] =
                        [$temp];
                } else {
                    array_push($time_ds[$time_components[2]]
                        [$time_components[0]], $temp);
                }
            }
        }
        $results = [];
        array_push($results, $time_ds);
        array_push($results, $years);
        array_push($results, $months);
        return $results;
    }
    /**
     * Create divs for links based on all (year, month) combinations
     * @param array $time_ds is the data structure for History UI
     * @param string $current_year is the year associated with the timestamp
     * of the cached page
     * @param string $current_month is the month associated with the timestamp
     * of the cached page
     * @param DOMElement $d1 is the section that contains options for years and
     * months
     * @param DOMDocument $dom is the DOM for the cached page
     * @param string $url is the URL for the cached page
     * @param array $years is an array storing years associated with all indexes
     * @param boolean $hist_ui_open checks if the History UI state should be
     *     open
     * @param string $terms is a string containing the query terms
     * @param long $crawl_time is the crawl time for the cached page
     * @return DOMElement $d1 is the section containing the options for
     * selecting year and month
     */
    public function createLinkDivs($time_ds, $current_year, $current_month, $d1,
        $dom, $url, $years, $hist_ui_open, $terms, $crawl_time)
    {
        $yrs = array_keys($time_ds);
        foreach ($years as $yr) {
            $mths = array_keys($time_ds[$yr]);
            foreach ($mths as $mth) {
                $yeardiv = $dom->createElement("div");
                $yeardiv->setAttributeNS("", "id", "#$yr$mth");
                $yeardiv->setAttributeNS("", "style", "display:none");
                if ($hist_ui_open === true){
                    if (!strcmp($yr, $current_year) &&
                        !strcmp($mth, $current_month)) {
                        $yeardiv->setAttributeNS("", "style", "display:block");
                        $d1->setAttributeNS("", "style",
                            "display:block");
                    }
                }
                $yeardiv = $d1->appendChild($yeardiv);
                $list_dom = $dom->createElement("ul");
                $yeardiv->appendChild($list_dom);
                foreach ($time_ds[$yr][$mth] as $entries) {
                    $list_item = $dom->createElement('li');
                    $arr = explode(" ", $entries);
                    $url_encoded = urlencode($arr[3]);
                    $link_text = $dom->createTextNode("$arr[0] $arr[1] ".
                            "$arr[2]");
                    $link = $this->baseLink()."&a=cache&".
                        "q=$terms&arg=$url_encoded&its=$arr[4]&hist_open=true";
                    $link_dom = $dom->createElement("a");
                        $link_dom->setAttributeNS("", "href", $link);
                    if ($arr[4] == $crawl_time) {
                        $bold = $dom->createElement('b');
                        $bold->appendChild($link_text);
                        $link_dom->appendChild($bold);
                    } else {
                        $link_dom->appendChild($link_text);
                    }
                    $list_item->appendChild($link_dom);
                    $list_dom->appendChild($list_item);
                }
            }
        }
        return $d1;
    }
    /**
     * Used to create the base link for links to be displayed on caches
     * of web pages this link points to yioop because links on cache pages
     * are to other cache pages
     *
     * @return string desired base link
     */
    public function baseLink()
    {
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $user = L\remoteAddress();
        } else {
            $user = "127.0.0.1";
        }
        $csrf_token = $this->generateCSRFToken($user);
        $link = "?".C\CSRF_TOKEN."=$csrf_token&c=search";
        return $link;
    }
    /**
     * Display links based on selected year and month in History UI
     * @param array $years is an array storing years associated with all indexes
     * @param array $months is an array storing months
     * @param string $current_year is the year associated with the timestamp
     * of the cached page
     * @param string $current_month is the month associated with the timestamp
     * of the cached page
     * @param array $time_ds is the data structure for History UI
     * @param DOMDocument $dom is the DOM for the cached page
     * @return DOMElement $d1 is the section containing the options for
     * selecting year and month
     */
    public function viewLinksByYearMonth($years, $months, $current_year,
        $current_month, $time_ds, $dom)
    {
        $year_json = json_encode($years);
        $month_json = json_encode($months);
        $d1 = $dom->createElement('div');
        $d1->setAttributeNS("", "id", "#d1");
        $d1->setAttributeNS("", "years", $year_json);
        $d1->setAttributeNS("", "months", $month_json);
        $title = $dom->createElement('span');
        $title->setAttributeNS("", "style", "color:green;");
        $title_text = $dom->createTextNode(
            tl('search_controller_all_cached'));
        $br = $dom->createElement('br');
        $title->appendChild($title_text);
        $d1->appendChild($title);
        $d1->appendChild($br);
        $s1 = $dom->createElement('span');
        $y = $dom->createElement('select');
        $y->setAttributeNS("", "id", "#year");
        $m = $dom->createElement('select');
        $m->setAttributeNS("", "id", "#month");
        foreach ($years as $year) {
            $o = $dom->createElement('option');
            $o->setAttributeNS("", "id", "#$year");
            if (strcmp($year, $current_year) == 0){
                $o->setAttributeNS("", "selected", "selected");
                $months = array_keys($time_ds[$year]);
                foreach ($months as $month) {
                    $p = $dom->createElement('option');
                    $p->setAttributeNS("", "id", "#$month");
                    if (strcmp($month, $current_month) == 0){
                        $p->setAttributeNS("", "selected", "selected");
                    }
                    $mt = $dom->createTextNode($month);
                    $p->appendChild($mt);
                    $m->appendChild($p);
                }
            }
            $yt = $dom->createTextNode($year);
            $o->appendChild($yt);
            $y->appendChild($o);
        }
        $yl = $dom->createTextNode(tl('search_controller_year'));
        $ml = $dom->createTextNode(tl('search_controller_month'));
        $s1->appendChild($yl);
        $s1->appendChild($y);
        $s1->appendChild($ml);
        $s1->appendChild($m);
        $d1->appendChild($s1);
        $d1->setAttributeNS("", "style", "display:none");
        $this->addCacheJavascriptTags($dom, $d1);
        return $d1;
    }
    /**
     * Add to supplied node subnodes containing script tags for javascript
     * libraries used to display cache pages
     *
     * @param DOMDocument $dom used to create new nodes
     * @param DomElement &$node what to add script node to
     */
    public function addCacheJavascriptTags($dom, &$node)
    {
        $script = $dom->createElement("script");
        $script->setAttributeNS("","src", C\NAME_SERVER."/scripts/basic.js");
        $node->appendChild($script);
        $script = $dom->createElement("script");
        $script->setAttributeNS("","src", C\NAME_SERVER."/scripts/history.js");
        $node->appendChild($script);
    }
}
