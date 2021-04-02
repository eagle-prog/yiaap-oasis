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
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\PageRuleParser;
use seekquarry\yioop\library\Classifiers\Classifier;
use seekquarry\yioop\library\CrawlDaemon;

/**
 * Controller used to handle admin functionalities such as
 * modify login and password, CREATE, UPDATE,DELETE operations
 * for users, roles, locale, and crawls
 *
 * @author Chris Pollett
 */
class AdminController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    public $activities = ["crawlStatus", "machineStatus", "signout"];
    /**
     * An array of activities which are periodically updated within other
     * activities that they live. For example, within manage crawl,
     * the current crawl status is updated every 20 or so seconds.
     * @var array
     */
    public $status_activities = ["crawlStatus", "machineStatus"];
    /**
     * Associative array of $components activities for this controller
     * Components are collections of activities (a little like traits) which
     * can be reused.
     *
     * @var array
     */
    public static $component_activities = [
        "accountaccess" =>
            ["manageAccount", "manageUsers", "manageRoles"],
        "crawl" => ["manageCrawls",  "manageClassifiers", "mixCrawls",
            "pageOptions", "resultsEditor", "scrapers",  "searchSources"],
        "social" => ["groupFeeds", "manageGroups", "wiki"],
        "chatbot" => ["botStory"],
        "store" => ["manageCredits", "manageAdvertisements"],
        "system" => ["manageMachines", "manageLocales", "serverSettings",
            "security", "appearance", "configure"],
    ];
    /**
     * This is the main entry point for handling requests to administer the
     * Yioop/SeekQuarry site
     *
     * ProcessRequest determines the type of request (signin , manageAccount,
     * etc) is being made.  It then calls the appropriate method to handle the
     * given activity. Finally, it draws the relevant admin screen
     */
    public function processRequest()
    {
        $data = [];
        if (!C\PROFILE) {
            return $this->configureRequest();
        }
        $view = "signin";
        if (!empty($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else if (!empty($_SESSION['USER_NAME']) && empty($_REQUEST['u'])) {
            $user = $this->model("signin")->getUserId($_SESSION['USER_NAME'],
                "string");
            $_SESSION['USER_ID'] = $user;
        } else {
            $user = L\remoteAddress();
        }
        $data['SCRIPT'] = "";
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user);
        if ($token_okay || isset($_REQUEST['u'])) {
            if (isset($_SESSION['USER_ID']) && !isset($_REQUEST['u'])) {
                $_SESSION['USER_NAME'] = $this->model("signin")->
                    getUserName($user);
                $data = array_merge($data, $this->processSession());
                if (!isset($data['REFRESH'])) {
                    $view = "admin";
                } else {
                    $view = $data['REFRESH'];
                }
            } else if (!isset($_SESSION['REMOTE_ADDR'])
                && !isset($_REQUEST['u'])) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_need_cookies') . "</h1>');";
                unset($_SESSION['USER_ID']);
            } else if ($this->checkSignin()) {
                $_SESSION['USER_NAME'] = $_REQUEST['u'];
                // successful login.
                $user_id = $this->model("signin")->getUserId(
                    $this->clean($_REQUEST['u'], "string"));
                $session = $this->model("user")->getUserSession($user_id);
                $last_activity = [];
                $_SESSION['USER_NAME'] = $_REQUEST['u'];
                if (isset($_SESSION['LAST_ACTIVITY']) &&
                    is_array($_SESSION['LAST_ACTIVITY'])) {
                    $_REQUEST = array_merge($_REQUEST,
                        $_SESSION['LAST_ACTIVITY']);
                }
                if (is_array($session)) {
                    $_SESSION = $session;
                }
                $allowed_activities =
                    $this->model("user")->getUserActivities($user_id);
                // now don't want to use remote address anymore
                if (empty($allowed_activities)) {
                    unset($_SESSION['USER_ID']);
                    unset($_REQUEST);
                    $_REQUEST['c'] = "admin";
                    return $this->redirectWithMessage(
                        tl('admin_controller_account_not_active'));
                } else {
                    $_SESSION['USER_ID'] = $user_id;
                    $_REQUEST[C\CSRF_TOKEN] = $this->generateCSRFToken(
                        $_SESSION['USER_ID']);
                    $preserve_array = [];
                    if (!empty($_REQUEST['preserve']) &&
                        $_REQUEST['preserve'] == 'true') {
                        $preserve_array = [
                            'a', 'arg', 'filter', 'group_id',
                            'just_thread', 'just_group_id', 'visible_users',
                            'user_filter'
                            ];
                    }
                    return $this->redirectWithMessage(
                        tl('admin_controller_login_successful'),
                        $preserve_array);
                }
            } else {
                unset($_SESSION['USER_ID']);
                $login_attempted = false;
                if (isset($_REQUEST['u'])) {
                    $login_attempted = true;
                }
                unset($_REQUEST);
                $_REQUEST['c'] = "admin";
                if ($login_attempted) {
                    return $this->redirectWithMessage(
                        tl('admin_controller_login_failed'));
                }
            }
        } else if ($this->checkCSRFToken(C\CSRF_TOKEN, "config")) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_login_to_config')."</h1>')";
        } else if (isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->status_activities)) {
            e("<p class='red'>".
                tl('admin_controller_status_updates_stopped')."</p>");
            \seekquarry\yioop\library\webExit();
        }
        if ($token_okay && isset($_SESSION["USER_ID"])) {
            $data["ADMIN"] = true;
        } else {
            $data["ADMIN"] = false;
        }
        if ($view == 'signin') {
            $data[C\CSRF_TOKEN] = $this->generateCSRFToken(
                L\remoteAddress());
            $data['SCRIPT'] .= "var u; if ((u = elt('username')) && u.focus) ".
               "u.focus();";
        }
        $_SESSION['REMOTE_ADDR'] = L\remoteAddress();
        if (!isset($data["USERNAME"]) && isset($_SESSION['USER_ID'])) {
            $signin_model = $this->model("signin");
            $data['USERNAME'] = $signin_model->getUserName(
                $_SESSION['USER_ID']);
        }
        $this->initializeAdFields($data, false);
        $this->displayView($view, $data);
    }
    /**
     * If there is no profile/work directory set up then this method
     * get called to by pass any login and go to the configure screen.
     * The configure screen is only displayed if the user is connected
     * from localhost in this case
     */
    public function configureRequest()
    {
        $data = $this->processSession();
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken("config");
        $this->displayView("admin", $data);
    }
    /**
     * Checks whether the user name and password sent presumably by the signin
     * form match a user in the database
     *
     * @return bool whether they do or not
     */
    public function checkSignin()
    {
        $result = false;
        if (isset($_REQUEST['u']) && isset($_REQUEST['p']) ) {
            $result = $this->model("signin")->checkValidSignin(
                $this->clean($_REQUEST['u'], "string"),
                $this->clean($_REQUEST['p'], "string") );
        }
        return $result;
    }
    /**
     * Determines the user's current allowed activities and current activity,
     * then calls the method for the latter.
     *
     * This is called from {@link processRequest()} once a user is logged in.
     *
     * @return array $data the results of doing the activity for display in the
     *     view
     */
    public function processSession()
    {
        $allowed = false;
        if (!C\PROFILE || (C\nsdefined("FIX_NAME_SERVER") &&
            C\FIX_NAME_SERVER)) {
            $activity = "configure";
        } else if (isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
            if (in_array($activity, ["groupFeeds", "wiki"])) {
                unset($_REQUEST['c']);
                $_REQUEST[C\CSRF_TOKEN] = $this->generateCSRFToken(
                    $_SESSION['USER_ID']);
                $location = B\controllerUrl("group", true) .
                    http_build_query($_REQUEST);
                $this->redirectLocation($location);
            }
        } else {
            $activity = "manageAccount";
        }
        $activity_model = $this->model("activity");
        if (!C\PROFILE) {
            $allowed_activities = [ [
                "ACTIVITY_NAME" =>
                $activity_model->getActivityNameFromMethodName($activity),
                'METHOD_NAME' => $activity,
                'ALLOWED_ARGUMENTS' => 'all'],
            ];
            $allowed = true;
        } else {
            $allowed_activities =
                 $this->model("user")->getUserActivities($_SESSION['USER_ID']);
        }
        if ($allowed_activities == []) {
            $data['INACTIVE'] = true;
            return $data;
        }
        $allowed_argument = false;
        $activity_index = 0;
        foreach ($allowed_activities as $allowed_activity) {
            if ($activity == $allowed_activity['METHOD_NAME']) {
                 $allowed = true;
                 $arguments = preg_split("/\s*\,\s*/",
                    trim($allowed_activity['ALLOWED_ARGUMENTS']));
                 $arg = $_REQUEST['arg'] ?? "";
                 if ((in_array('all', $arguments) &&
                    !in_array("-$arg", $arguments)) ||
                    in_array($arg, $arguments) ) {
                    $allowed_argument = true;
                    break;
                 }
            }
            if ($allowed_activity['METHOD_NAME'] == "manageCrawls" &&
                $activity == "crawlStatus") {
                $allowed = true;
                $allowed_argument = true;
                break;
            }
            if ($allowed_activity['METHOD_NAME'] == "manageMachines" &&
                $activity == "machineStatus") {
                $allowed = true;
                $allowed_argument = true;
                break;
            }
            if ($allowed_activity['METHOD_NAME'] == "groupFeeds" &&
                $activity == "wiki") {
                $allowed = true;
                $allowed_argument = true;
                break;
            }
            $activity_index++;
        }
        // always allow managing account
        if (!$allowed && $activity == "manageAccount") {
            $activity = $allowed_activities[0]['METHOD_NAME'];
            $_REQUEST["a"] = $activity;
            $allowed = true;
        }
        if (!$allowed_argument) {
            unset($_REQUEST['arg']);
        }
        $data['ALLOWED_ARGUMENTS'] = "";
        //for now we allow anyone to get crawlStatus
        if ($allowed) {
            $data = $this->call($activity);
            if (!is_array($data)) {
                $data = [];
            }
            $data['ACTIVITY_METHOD'] = $activity; //for settings controller
            $data['ACTIVITIES'] = $allowed_activities;
            $data['ALLOWED_ARGUMENTS'] = $allowed_activity['ALLOWED_ARGUMENTS'];
        }
        if (!in_array($activity, $this->status_activities)) {
            $name_activity = $activity;
            if ($activity == "wiki") {
                $name_activity = "groupFeeds";
            }
            $data['CURRENT_ACTIVITY'] =
                $activity_model->getActivityNameFromMethodName($name_activity);
        }
        $data['COMPONENT_ACTIVITIES'] = isset($data["ACTIVITIES"]) ?
            self::computeComponentActivities($data["ACTIVITIES"]) : [];
        return $data;
    }
    /**
     * For a given user's access and the list component and activities
     * return a list of translated names of components associated to a
     * list of user accessible activities for that component
     *
     * @param array $user_activities a list of activities that a
     *  user is allowed to access
     * @return array of translated name of component => [list of user accessible
     *  actvitities]
     */
    public static function computeComponentActivities($user_activities)
    {
        $user_component_activities = [];
        $component_translations = [
            "accountaccess" => tl('admin_controller_account_access'),
            "social" => tl('admin_controller_social'),
            "crawl" => tl('admin_controller_crawl_settings'),
            "system" => tl('admin_controller_system_settings'),
            "store" => tl('admin_controller_store'),
            "chatbot" => tl('admin_controller_chatbot')
        ];
        foreach (self::$component_activities as $component => $activities) {
            foreach ($user_activities as $activity) {
                if (in_array($activity['METHOD_NAME'], $activities)) {
                    $user_component_activities[
                        $component_translations[$component]][] =
                        $activity;
                }
            }
        }
        return $user_component_activities;
    }
    /**
     * Used to handle crawlStatus REST activities requesting the status of the
     * current web crawl
     *
     * @return array $data contains crawl status of current crawl as well as
     *     info about prior crawls and which crawl is being used for default
     *     search results
     */
    public function crawlStatus()
    {
        $data = [];
        $data['SCRIPT'] = "";
        $data['REFRESH'] = "crawlstatus";
        $crawl_model = $this->model("crawl");
        $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        if (isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }
        $machine_urls = $this->model("machine")->getQueueServerUrls();
        list($stalled, $status, $data['RECENT_CRAWLS']) =
            $crawl_model->combinedCrawlInfo($machine_urls);
        if ($stalled) {
            foreach ($stalled as $channel => $stall_status) {
                if ($stall_status) {
                    $crawl_model->sendStopCrawlMessage($channel, $machine_urls);
                }
            }
        }
        $data['CRAWL_TOGGLE'] = (!isset($_REQUEST['crawl_toggle'])) ? [] :
            array_filter(
                explode(",", $this->clean($_REQUEST['crawl_toggle'], "string")
            ));
        $data["ACTIVE_CRAWLS"] = $status;
        if (!empty($data["ACTIVE_CRAWLS"])) {
            $num_crawls = count($data['RECENT_CRAWLS']);
            foreach($data["ACTIVE_CRAWLS"] as $channel => $crawl) {
                //erase from previous crawl list any active crawl
                for ($i = 0; $i < $num_crawls; $i++) {
                    if (!empty($crawl['CRAWL_TIME']) &&
                        $data['RECENT_CRAWLS'][$i]['CRAWL_TIME'] ==
                        $crawl['CRAWL_TIME'] && !empty($crawl['DESCRIPTION'])) {
                        $data['RECENT_CRAWLS'][$i] = false;
                    }
                }
            }
            $data['RECENT_CRAWLS']= array_filter($data['RECENT_CRAWLS']);
        }
        if (isset($data['RECENT_CRAWLS'][0])) {
            L\rorderCallback($data['RECENT_CRAWLS'][0],
                $data['RECENT_CRAWLS'][0], 'CRAWL_TIME');
            usort($data['RECENT_CRAWLS'], C\NS_LIB . "rorderCallback");
        }
        $this->pagingLogic($data, 'RECENT_CRAWLS', 'RECENT_CRAWLS',
            C\DEFAULT_ADMIN_PAGING_NUM);
        return $data;
    }
    /**
     * Gets data from the machine model concerning the on/off states
     * of the machines managed by this Yioop instance and then passes
     * this data the the machinestatus view.
     * @return array $data MACHINES field has information about each
     *     machine managed by this Yioop instance as well the on off
     *     status of its queue_servers and fetchers.
     *     The REFRESH field is used to tell the controller that the
     *     view shouldn't have its own sidemenu.
     */
    public function machineStatus()
    {
        $machine_model = $this->model("machine");
        $data = [];
        $data['REFRESH'] = "machinestatus";
        $data['MACHINE_NAMES'] = [];
        $data['CHANNELS'] = range(0, C\MAX_CHANNELS -1);
        $data['CHANNELS'][-1 ] = tl('system_component_is_replica');
        ksort($data['CHANNELS']);
        $data['FETCHER_NUMBERS'] = [
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            16 => 16
        ];
        $machine_names = $machine_model->getQueueServerNames();
        $data['PARENT_MACHINES'] = array_combine($machine_names,
            $machine_names);
        $data['PARENT'] = $machine_names[0] ?? "";
        $data['FETCHER_NUMBER'] = 2;
        $data['CHANNEL'] = 0;
        $this->pagingLogic($data, $machine_model, 'MACHINES',
            C\DEFAULT_ADMIN_PAGING_NUM);
        $profile =  $this->model("profile")->getProfile(C\WORK_DIRECTORY);
        $media_mode = isset($profile['MEDIA_MODE']) ?
            $profile['MEDIA_MODE']: "name_server";
        $data['MEDIA_MODE'] = $media_mode;
        if ($data['MEDIA_MODE'] == "name_server" &&
            $data['MACHINES']['NAME_SERVER']["MEDIA_UPDATER_TURNED_ON"] &&
            $data['MACHINES']['NAME_SERVER']["MediaUpdater"] == 0) {
            // try to restart news server if dead
            CrawlDaemon::start("MediaUpdater", 'none', "", -1);
        }
        return $data;
    }
    /**
     * Used to update the yioop installation profile based on $_REQUEST data
     *
     * @param array &$data field data to be sent to the view
     * @param array &$profile used to contain the current and updated profile
     *     field values
     * @param array $check_box_fields fields whose data comes from a html
     *     checkbox
     */
    public function updateProfileFields(&$data, &$profile,
        $check_box_fields = [])
    {
        $script_array = ['SIDE_ADSCRIPT', 'TOP_ADSCRIPT', 'GLOBAL_ADSCRIPT'];
        foreach ($script_array as $value) {
            if (isset($_REQUEST[$value])) {
                $_REQUEST[$value] = strtr($_REQUEST[$value], ["(" => "&#40;",
                    ")" => "&#41;"]);
            }
        }
        $color_fields = ['BACKGROUND_COLOR', 'FOREGROUND_COLOR',
            'SIDEBAR_COLOR', 'TOPBAR_COLOR'];
        foreach ($this->model("profile")->profile_fields as $field) {
            if (isset($_REQUEST[$field])) {
                if ($field != "ROBOT_DESCRIPTION" &&
                    $field != "PROXY_SERVERS") {
                    if (in_array($field, $color_fields)) {
                        $clean_value =
                            $this->clean($_REQUEST[$field], "color");
                    } else {
                        $clean_value =
                            $this->clean($_REQUEST[$field], "string");
                    }
                } else {
                    $clean_value = $_REQUEST[$field];
                }
                if ($field == "NAME_SERVER" &&
                    $clean_value[strlen($clean_value) -1] != "/") {
                    $clean_value .= "/";
                }
                $data[$field] = $clean_value;
                $profile[$field] = $data[$field];
                if ($field == "PROXY_SERVERS") {
                    $proxy_array = preg_split("/(\s)+/", $clean_value);
                    $profile[$field] =$this->convertArrayLines(
                        $proxy_array, "|Z|", true);
                }
            }
            if (!isset($data[$field])) {
                if (defined($field) && !in_array($field, $check_box_fields)) {
                    $data[$field] = constant($field);
                } else {
                    $data[$field] = "";
                }
                if (in_array($field, $check_box_fields)) {
                    $profile[$field] = false;
                }
            }
        }
    }
    /**
     * Used to set up view data for table search form (might make use of
     * $_REQUEST if form was submitted, results gotten, and we want to preserve
     * form drop down). Table search forms
     * are used by manageUsers, manageRoles, manageGroups, to do advanced
     * search of the entity they are responsible for.
     *
     * @param array &$data modified to contain the field data needed for
     *     the view to draw the search form
     * @param string activity in which this search is being conducted
     * @param array $comparison_fields those fields of the entity
     *     in question ( for example, users) which we can search both with
     *     string comparison operators and equality operators
     * @param string $field_postfix suffix to append onto field names in
     *     case there are multiple forms on the same page
     */
    public function tableSearchRequestHandler(&$data, $activity,
        $comparison_fields = [], $field_postfix = "")
    {
        $data['FORM_TYPE'] = "search";
        $activity_postfix = $activity . $field_postfix;
        $data['EQUAL_COMPARISON_TYPES'] = [
            "=" => tl('admin_controller_equal'),
            "!=" => tl('admin_controller_not_equal'),
        ];
        $data['INEQUALITY_COMPARISON_TYPES'] = array_merge(
            $data['EQUAL_COMPARISON_TYPES'], [
            "<" => tl('admin_controller_less_than'),
            "<=" => tl('admin_controller_less_equal'),
            ">" => tl('admin_controller_greater_than'),
            ">=" => tl('admin_controller_greater_equal'),
        ]);
        $data['BETWEEN_COMPARISON_TYPES'] = [
            'BETWEEN' => tl('admin_controller_between'),
            'NOT BETWEEN' => tl('admin_controller_not_between'),
        ];
        $data['COMPARISON_TYPES'] = array_merge(
            $data['EQUAL_COMPARISON_TYPES'],[
            "=" => tl('admin_controller_equal'),
            "!=" => tl('admin_controller_not_equal'),
            "CONTAINS" => tl('admin_controller_contains'),
            "BEGINS WITH" => tl('admin_controller_begins_with'),
            "ENDS WITH" => tl('admin_controller_ends_with'),
        ]);
        $_SESSION['SEARCH'][$activity_postfix]['COMPARISON_TYPES'] =
            $data['COMPARISON_TYPES'];
        $_SESSION['SEARCH'][$activity_postfix]['EQUAL_COMPARISON_TYPES'] =
            $data['EQUAL_COMPARISON_TYPES'];
        $data['SORT_TYPES'] = [
            "NONE" => tl('admin_controller_no_sort'),
            "ASC" => tl('admin_controller_sort_ascending'),
            "DESC" => tl('admin_controller_sort_descending'),
        ];
        $_SESSION['SEARCH'][$activity_postfix]['SORT_TYPES'] =
            $data['SORT_TYPES'];
        $paging = "";
        $comparisons_all = $comparison_fields['ALL_FIELDS'] ?? [];
        $equal_comparison_fields = $comparison_fields['EQUAL_COMPARISON_TYPES']
            ?? [];
        $between_comparison_fields =
            $comparison_fields['BETWEEN_COMPARISON_TYPES'] ?? [];
        $timestamp_comparison_fields =
            $comparison_fields['TIMESTAMP_COMPARISON_TYPES'] ?? [];
        $inequality_comparison_fields =
            $comparison_fields['INEQUALITY_COMPARISON_TYPES'] ?? [];
        $between_fields = array_merge($between_comparison_fields,
            $timestamp_comparison_fields);
        foreach ($comparisons_all as $comparison_start) {
            $comparison = $comparison_start . $field_postfix . "_comparison";
            $comparison_types = (in_array($comparison_start,
                 $equal_comparison_fields)) ? 'EQUAL_COMPARISON_TYPES' :
                 ((in_array($comparison_start, $inequality_comparison_fields)) ?
                'INEQUALITY_COMPARISON_TYPES' :
                 ((in_array($comparison_start, $between_fields)) ?
                 'BETWEEN_COMPARISON_TYPES' : 'COMPARISON_TYPES'));
            $default_type = ($comparison_types == 'BETWEEN_COMPARISON_TYPES') ?
                "BETWEEN" : ($comparison_types == 'COMPARISON_TYPES' ?
                "CONTAINS" : "=");
            $data[$comparison] = (isset($_REQUEST[$comparison]) &&
                isset($data[$comparison_types][
                $_REQUEST[$comparison]])) ? $_REQUEST[$comparison] :
                $default_type;
            $_SESSION['SEARCH'][$activity_postfix]['COMPARISON_FIELDS'
                ][$comparison] = $data[$comparison];
            $paging .= "&amp;$comparison=".
                urlencode($data[$comparison]);
        }
        foreach ($comparisons_all as $sort_start) {
            $sort = $sort_start . $field_postfix . "_sort";
            $data[$sort] = (isset($_REQUEST[$sort]) &&
                isset($data['SORT_TYPES'][
                $_REQUEST[$sort]])) ? $_REQUEST[$sort] :
                "NONE";
            $_SESSION['SEARCH'][$activity_postfix]['SORT'][$sort] =
                $data[$sort];
            $paging .= "&amp;$sort=" . urlencode($data[$sort]);
        }
        $search_array = [];
        foreach ($comparisons_all as $field) {
            $field_comparison = $field . $field_postfix . "_comparison";
            $field_sort = $field . $field_postfix . "_sort";
            $is_timestamp_type = in_array($field, $timestamp_comparison_fields);
            $is_between_type = in_array($field, $between_comparison_fields);
            if ($is_between_type || $is_timestamp_type) {
                $low = "_low";
                $field_name_low = $field. $low . $field_postfix;
                $data[$field_name_low] = (isset($_REQUEST[$field_name_low]) &&
                    $_REQUEST[$field_name_low] != '-1') ?
                    $this->clean($_REQUEST[$field_name_low], "string") :
                    "";
                $high = "_high";
                $field_name_high = $field. $high . $field_postfix;
                $data[$field_name_high] = (isset($_REQUEST[$field_name_high]) &&
                    $_REQUEST[$field_name_high] != '-1') ?
                    $this->clean($_REQUEST[$field_name_high], "string") :
                    "";
                $_SESSION['SEARCH'][$activity_postfix]['FIELD_NAMES'
                    ][$field_name_low] = $data[$field_name_low];
                $_SESSION['SEARCH'][$activity_postfix]['FIELD_NAMES'
                    ][$field_name_high] = $data[$field_name_high];
                if ($is_timestamp_type) {
                    $search_array[] = [$field, $data[$field_comparison],
                        strtotime($data[$field_name_low]),
                        strtotime($data[$field_name_high]), $data[$field_sort]];
                } else {
                    $search_array[] = [$field,
                        $data[$field_comparison], $data[$field_name_low],
                        $data[$field_name_high], $data[$field_sort]];
                }
                $paging .= "&amp;$field_name_low=" . urlencode(
                    $data[$field_name_low]). "&amp;$field_name_high=" .
                    urlencode($data[$field_name_high]);
            } else {
                $field_name = $field . $field_postfix;
                    $data[$field_name] = (isset($_REQUEST[$field_name]) &&
                        $_REQUEST[$field_name] != '-1') ?
                        $this->clean($_REQUEST[$field_name], "string") :
                        "";
                $_SESSION['SEARCH'][$activity_postfix]['FIELD_NAMES'
                    ][$field_name] = $data[$field_name];
                if ($field_name == 'access' && $data[$field_name] >= 10) {
                    $search_array[] = ["status",
                        $data[$field_comparison], $data[$field_name]/10,
                        $data[$field_sort]];
                } else {
                    $search_array[] = [$field,
                        $data[$field_comparison], $data[$field_name],
                        $data[$field_sort]];
                }
                $paging .= "&amp;$field_name=" . urlencode($data[$field_name]);
            }
        }
        $data['PAGING'] = $paging;
        $_SESSION['SEARCH'][$activity_postfix]['SEARCH_ARRAY'] =
            $search_array;
        $_SESSION['SEARCH'][$activity_postfix]['PAGING'] =
            $data['PAGING'];
        return $search_array;
    }
    /**
     * For activity involving items for which one can do search (user, group,
     * roles) this method is used to marshal the last search that was performed
     * out of the session when one navigates back to search
     *
     * @param array &$data field variables used by view to draw itself
     * @param string $activity current activity marshalling last search for
     * @param string $field_postfix some activities support multiple search
     *   forms. The field postfix is used to select among these.
     */
    function restoreLastSearchFromSession(&$data, $activity,
        $field_postfix = "")
    {
        $activity_postfix = $activity . $field_postfix;
        if (empty($_SESSION['LAST_SEARCH'][$activity_postfix])) {
            return;
        }
        $last_search = $_SESSION['LAST_SEARCH'][$activity_postfix];
        foreach (['COMPARISON_TYPES', 'EQUAL_COMPARISON_TYPES',
            'SORT_TYPES', 'SEARCH_ARRAY', 'PAGING'] as $field) {
            $data[$field] = (empty($last_search[$field])) ? [] :
                $last_search[$field];
        }
        foreach (['COMPARISON_FIELDS', 'SORT', 'FIELD_NAMES'] as $field) {
            foreach ($last_search[$field] as $name => $value) {
                $data[$name] = $value;
            }
        }
        return $data['SEARCH_ARRAY'];
    }
}
