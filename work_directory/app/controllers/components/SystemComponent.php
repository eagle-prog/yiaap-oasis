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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\models\datasources as D;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\UrlParser;

/**
 * This component is used to handle activities related to the configuration
 * of a Yioop installation, translations of text ging in the installation,
 * as well as control of specifying what machines make up the installation
 * and which processes they run.
 *
 * @author Chris Pollett
 */
class SystemComponent extends Component
{
    /**
     * Handles admin request related to the managing the machines which perform
     * crawls
     *
     * With this activity an admin can add/delete machines to manage. For each
     * managed machine, the admin can stop and start fetchers/queue_servers
     * as well as look at their log files
     *
     * @return array $data MACHINES, their MACHINE_NAMES, data for
     *     FETCHER_NUMBERS drop-down
     */
    public function manageMachines()
    {
        $parent = $this->parent;
        $machine_model = $parent->model("machine");
        $profile_model = $parent->model("profile");
        $data = [];
        $data["ELEMENT"] = "managemachines";
        $possible_arguments = ["addmachine",  "deletemachine", "disablejob",
            "enablejob", "log", "mediajobs", "update", "updatemode"];
        $data['SCRIPT'] = (empty($_REQUEST['arg'])|| !in_array($_REQUEST['arg'],
            ["disablejob", "enablejob", "mediajobs", "updatemode"])) ?
            "doUpdate();" : "";
        $data["leftorright"]=(L\getLocaleDirection() == 'ltr') ? "right":
            "left";
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
        $tmp = tl('system_component_select_machine');
        if (isset($_REQUEST['channel']) && $_REQUEST['channel'] == -1) {
            $_REQUEST['num_fetchers'] = 0;
        } else {
            $_REQUEST['parent'] = "";
        }
        $request_fields = [
            "name" => "string",
            "url" => "web-url",
            "channel" => array_keys($data['CHANNELS']),
            "num_fetchers" => array_keys($data['FETCHER_NUMBERS']),
            "parent" => "string"
        ];
        $r = [];
        $allset = true;
        foreach ($request_fields as $field => $type) {
            if (isset($_REQUEST[$field])) {
                $r[$field] = $parent->clean($_REQUEST[$field], $type);
                if ($type == "string") {
                    $r[$field] = trim($r[$field]);
                    if ($r[$field] == "" && $field != "parent") {
                        $allset = false;
                    }
                }
                if ($field == "url") {
                    if (isset($r[$field][strlen($r[$field]) - 1]) &&
                        $r[$field][strlen($r[$field])-1] != "/") {
                        $r[$field] .= "/";
                    }
                    $r[$field] = UrlParser::canonicalLink($r[$field],
                        C\NAME_SERVER);
                    if (!$r[$field]) {
                        $allset = false;
                    }
                }
            } else {
                $allset = false;
            }
        }
        if (isset($r["num_fetchers"])) {
            $data['FETCHER_NUMBER'] = $r["num_fetchers"];
        } else {
            $data['FETCHER_NUMBER'] = 0;
        }
        if (isset($r["channel"])) {
            $data['CHANNEL'] = $r["channel"];
        } else {
            $data['CHANNEL'] = 0;
        }
        $machine_exists = (isset($r["name"]) &&
            $machine_model->checkMachineExists("NAME", $r["name"]) ) ||
            (isset($r["url"])  &&  isset($r["channel"]) &&
            $machine_model->checkMachineExists(["URL", "CHANNEL"],
            [$r["url"], $r["channel"]]));
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "addmachine":
                    if ($allset == true && !$machine_exists) {
                        $machine_model->addMachine(
                            $r["name"], $r["url"], $r["channel"],
                            $r["num_fetchers"], $r["parent"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_added'),
                            ["start_row", "end_row", "num_show"]);
                    } else if ($allset && $machine_exists ) {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_exists'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_incomplete'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
                case "deletemachine":
                    if (!$machine_exists) {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_doesnt_exists'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        $machines = $machine_model->getRows(0, 1,
                            $total_rows, [
                                ["name", "=", $r["name"], ""]]);
                        $service_in_use = false;
                        foreach ($machines as $machine) {
                            if ($machine['NAME'] == $r["name"]) {
                                if (isset($machine['STATUSES']) &&
                                    is_array($machine['STATUSES'])) {
                                    unset($machine['STATUSES']['index']);
                                    if ($machine['STATUSES'] != []) {
                                        $service_in_use = true;
                                    }
                                    break;
                                } else {
                                    break;
                                }
                            }
                        }
                        if ($service_in_use) {
                            return $parent->redirectWithMessage(
                                tl('system_component_stop_service_first'),
                                ["start_row", "end_row", "num_show"]);
                            break;
                        }
                        $machine_model->deleteMachine($r["name"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_deleted'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
                case "disablejob":
                    $data["ELEMENT"] = "mediajobs";
                    $_REQUEST['arg'] = 'mediajobs';
                    $jobs_list = $machine_model->getJobsList();
                    $job_name = $_REQUEST["job_name"] ?? "";
                    if (!isset($jobs_list[$job_name])) {
                        return $parent->redirectWithMessage(
                            tl('system_component_job_doesnt_exist'),
                            ["arg", "start_row", "end_row", "num_show"]);
                    }
                    $machine_model->setJobStatus($job_name, false);
                    return $parent->redirectWithMessage(
                        tl('system_component_job_disabled'),
                        ["arg", "start_row", "end_row", "num_show"]);
                    break;
                case "enablejob":
                    $data["ELEMENT"] = "mediajobs";
                    $_REQUEST['arg'] = 'mediajobs';
                    $jobs_list = $machine_model->getJobsList();
                    $job_name = $_REQUEST["job_name"] ?? "";
                    if (!isset($jobs_list[$job_name])) {
                        return $parent->redirectWithMessage(
                            tl('system_component_job_doesnt_exist'),
                            ["arg", "start_row", "end_row", "num_show"]);
                    }
                    $machine_model->setJobStatus($job_name, true);
                    return $parent->redirectWithMessage(
                        tl('system_component_job_enabled'),
                        ["arg", "start_row", "end_row", "num_show"]);
                    break;
                case "log":
                    $log_fields = ["id" => "int", "name"=>"string",
                        "channel" => "int", "f" => "string",
                        "type"=>"string"
                    ];
                    foreach ($log_fields as $field => $type) {
                        if (isset($_REQUEST[$field])) {
                            $r[$field] =
                                $parent->clean($_REQUEST[$field], $type);
                        }
                    }
                    $filter = (isset($r['f'])) ? $r['f'] : "";
                    $r['channel'] = (isset($r['channel'])) ? $r['channel'] : 0;
                    if (isset($_REQUEST["time"])) {
                        $data["time"] =
                            $parent->clean($_REQUEST["time"], "int") + 30;
                    } else {
                        $data["time"] = 30;
                    }
                    if (isset($_REQUEST["NO_REFRESH"])) {
                        $data["NO_REFRESH"] = $parent->clean(
                            $_REQUEST["NO_REFRESH"], "bool");
                    } else {
                        $data["NO_REFRESH"] = false;
                    }
                    $data["ELEMENT"] = "machinelog";
                    $data['filter'] = $filter;
                    $data["REFRESH_LOG"] = "&time=". $data["time"];
                    $data["LOG_TYPE"] = "";
                    if (isset($r['id']) && isset($r['name']) &&
                        isset($r['type'])) {
                        $data["LOG_FILE_DATA"] = $machine_model->getLog(
                            $r["name"], $r["id"], $r["type"], $filter);
                        $data["LOG_TYPE"] = $r['name'] . " ". $r["type"];
                        if ($r["type"] == "fetcher") {
                            $data["LOG_TYPE"] .= " ". $r['id'];
                        }
                        $data["REFRESH_LOG"] .= "&arg=log&name=".$r['name'].
                            "&id=" . $r['id'] . "&type=" . $r["type"] .
                            "&channel=" . $r['channel'];
                    }
                    if ($data["time"] >= C\ONE_HOUR/3) {
                        $data["REFRESH_LOG"] = "";
                    }
                    if (empty($data["LOG_FILE_DATA"])){
                        $data["LOG_FILE_DATA"] =
                            tl('system_component_no_machine_log');
                    }
                    $lines = array_reverse(
                        explode("\n", $data["LOG_FILE_DATA"]));
                    $data["LOG_FILE_DATA"] = implode("\n", $lines);
                    break;
                case "mediajobs":
                    $data["ELEMENT"] = "mediajobs";
                    $profile =  $profile_model->getProfile(C\WORK_DIRECTORY);
                    $data['MEDIA_MODE'] = $profile['MEDIA_MODE'] ??
                        "name_server";
                    $data['JOBS_LIST'] = $machine_model->getJobsList();
                    break;
                case "update":
                    if (isset($_REQUEST["id"])) {
                        $r["id"] =
                            $parent->clean($_REQUEST["id"], "int");
                    } else {
                        $r["id"] = 0;
                    }
                    $available_actions = ["start", "stop"];
                    $available_types = ["QueueServer", "MediaUpdater",
                        "Mirror", "Fetcher"];
                    if (isset($r["name"]) && isset($_REQUEST["action"]) &&
                        in_array($_REQUEST["action"], $available_actions)
                        && isset($_REQUEST["type"]) && in_array(
                        $_REQUEST["type"], $available_types)) {
                        $action = $_REQUEST["action"];
                        $machine_model->update($r["name"],
                            $_REQUEST["action"], $r["id"], $_REQUEST["type"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_servers_updated'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_no_action'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
                case "updatemode":
                    $data["ELEMENT"] = "mediajobs";
                    $profile =  $profile_model->getProfile(C\WORK_DIRECTORY);
                    if (isset($profile['MEDIA_MODE']) &&
                        $profile['MEDIA_MODE'] == "name_server") {
                        $profile['MEDIA_MODE'] = "distributed";
                    } else {
                        $profile['MEDIA_MODE'] = "name_server";
                    }
                    $profile_model->updateProfile(C\WORK_DIRECTORY, [],
                        $profile);
                    $_REQUEST['arg'] = 'mediajobs';
                    return $parent->redirectWithMessage(
                        tl('system_component_updatemode_toggled'),
                        ["arg", "start_row", "end_row", "num_show"]);
                    break;
            }
        }
        $data['INCLUDE_SCRIPTS'][] = 'help';
        $parent->pagingLogic($data, $machine_model, "MACHINE",
            C\DEFAULT_ADMIN_PAGING_NUM);
        return $data;
    }
    /**
     * Handles admin request related to the manage locale activity
     *
     * The manage locale activity allows a user to add/delete locales, view
     * statistics about a locale as well as edit the string for that locale
     *
     * @return array $data info about current locales, statistics for each
     *     locale as well as potentially the currently set string of a
     *     locale and any messages about the success or failure of a
     *     sub activity.
     */
    public function manageLocales()
    {
        $parent = $this->parent;
        $locale_model = $parent->model("locale");
        $possible_arguments = ["addlocale", "deletelocale", "editlocale",
            "editstrings", "search"];
        $search_array = [];
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "managelocales";
        $data['CURRENT_LOCALE'] = ["localename" => "",
            'localetag' => "", 'writingmode' => '-1', 'active' => 1];
        $data['WRITING_MODES'] = [
            -1 => tl('system_component_select_mode'),
            "lr-tb" => "lr-tb",
            "rl-tb" => "rl-tb",
            "tb-rl" => "tb-rl",
            "tb-lr" => "tb-lr"
        ];
        $data['FORM_TYPE'] = "addlocale";
        $paging = true;
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $clean_fields = ['localename', 'localetag', 'writingmode',
                'selectlocale', 'active'];
            $edit_preserve_fields = ["selectlocale", "arg",
                "start_row", "end_row", "num_show", "previous_activity",
                "filter", "show", "context"];
            $preserve_fields = ["start_row", "end_row", "num_show",
                 "context"];
            $incomplete = false;
            $required = ['localename', 'localetag'];
            foreach ($clean_fields as $field) {
                $$field = "";
                if ($field == 'active') {
                    $active = 0;
                }
                if (isset($_REQUEST[$field])) {
                    $tmp = trim($parent->clean($_REQUEST[$field], "string"));
                    if ($field == "writingmode" && ($tmp == -1 ||
                        !isset($data['WRITING_MODES'][$tmp]))) {
                        $tmp = "lr-tb";
                    }
                    if ($tmp == "" && in_array($field, $required)) {
                        $incomplete = true;
                    }
                    $$field = $tmp;
                } else if (in_array($field, $required)) {
                    $incomplete = true;
                }
            }
            switch ($_REQUEST['arg']) {
                case "addlocale":
                    if ($incomplete && isset($_REQUEST['update'])) {
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_missing_info'),
                            $preserve_fields);
                    } else if (isset($_REQUEST['update'])) {
                        $locale_model->addLocale(
                            $localename, $localetag, $writingmode, $active);
                        $locale_model->extractMergeLocales();
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_added'),
                            $preserve_fields);
                    }
                    break;
                case "deletelocale":
                    if (!$locale_model->checkLocaleExists($selectlocale)) {
                        return $parent->redirectWithMessage(
                            tl('system_component_localename_doesnt_exists'),
                            $preserve_fields);
                    }
                    $locale_model->deleteLocale($selectlocale);
                    return $parent->redirectWithMessage(
                        tl('system_component_localename_deleted'),
                        $preserve_fields);
                    break;
                case "editlocale":
                    if (!$locale_model->checkLocaleExists($selectlocale)) {
                        return $parent->redirectWithMessage(
                            tl('system_component_localename_doesnt_exists'),
                            $preserve_fields);
                    }
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    }
                    $data['FORM_TYPE'] = "editlocale";
                    $info = $locale_model->getLocaleInfo($selectlocale);
                    $change = false;
                    if (isset($localetag) && $localetag != "") {
                        $info["LOCALE_TAG"] = $localetag;
                        $change = true;
                    }
                    if (isset($writingmode) && $writingmode != "") {
                        $info["WRITING_MODE"] = $writingmode;
                        $change = true;
                    }
                    if (isset($_REQUEST['update']) &&
                        $active != $info['ACTIVE']) {
                        $info['ACTIVE'] =  $active;
                        $change = true;
                    }
                    $data['CURRENT_LOCALE']['active'] = $info['ACTIVE'];
                    $data['CURRENT_LOCALE']['localename'] =
                        $info["LOCALE_NAME"];
                    $data['CURRENT_LOCALE']['localetag'] =
                        $selectlocale;
                    $data['CURRENT_LOCALE']['writingmode'] =
                        $info["WRITING_MODE"];
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    if ($change) {
                        $locale_model->updateLocaleInfo($info);
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_updated'),
                            $edit_preserve_fields);
                    }
                    break;
                case "editstrings":
                    if (!isset($selectlocale)) {
                        break;
                    }
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    }
                    $paging = false;
                    $data["leftorright"] =
                        (L\getLocaleDirection() == 'ltr') ? "right": "left";
                    $data['PREVIOUS_ACTIVITY'] = "manageLocales";
                    if (isset($_REQUEST['previous_activity']) &&
                        in_array($_REQUEST['previous_activity'], [
                        "security", "searchSources"])) {
                            $data['PREVIOUS_ACTIVITY'] =
                                $_REQUEST['previous_activity'];
                            if ($_REQUEST['previous_activity'] ==
                                'searchSources') {
                                $data['PREVIOUS_ACTIVITY'] .=
                                    "&amp;arg=showsubsearch";
                            }
                    }
                    $data["ELEMENT"] = "editlocales";
                    $data['CURRENT_LOCALE_NAME'] =
                        $locale_model->getLocaleName($selectlocale);
                    $data['CURRENT_LOCALE_TAG'] = $selectlocale;
                    if (isset($_REQUEST['STRINGS'])) {
                        $safe_strings = [];
                        foreach ($_REQUEST['STRINGS'] as $key => $value) {
                            $clean_key = $parent->clean($key, "string" );
                            $clean_value = $parent->clean($value, "string");
                            $safe_strings[$clean_key] = $clean_value;
                        }
                        $locale_model->updateStringData(
                            $selectlocale, $safe_strings);
                        return $parent->redirectWithMessage(
                            tl('system_component_localestrings_updated'),
                            $edit_preserve_fields);
                    } else {
                        $locale_model->extractMergeLocales();
                    }
                    $data['STRINGS'] =
                        $locale_model->getStringData($selectlocale);
                    $data['DEFAULT_STRINGS'] =
                        $locale_model->getStringData(C\DEFAULT_LOCALE);
                    $data['show'] = "all";
                    $data["show_strings"] =
                        [   "all" => tl('system_component_all_strings'),
                            "missing" => tl('system_component_missing_strings')
                        ];
                    if (isset($_REQUEST['show']) &&
                        $_REQUEST['show'] == "missing") {
                        $data["show"]= "missing";
                        foreach ($data['STRINGS'] as
                            $string_id => $translation) {
                            if ($translation != "") {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                    $data["filter"] = "";
                    if (isset($_REQUEST['filter']) && $_REQUEST['filter']) {
                        $filter = $parent->clean($_REQUEST['filter'], "string");
                        $data["filter"] = $filter;
                        foreach ($data['STRINGS'] as
                            $string_id => $translation) {
                            if (mb_stripos($string_id, $filter) === false &&
                                mb_stripos($translation, $filter) === false) {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                    $data['NUM_STRINGS_SHOW'] = 100;
                    $data['TOTAL_STRINGS'] = count($data['STRINGS']);
                    $data['LIMIT'] = (isset($_REQUEST['limit'])) ?
                        min($parent->clean($_REQUEST['limit'], 'int'),
                        $data['TOTAL_STRINGS']) : 0;
                    $data['STRINGS'] = array_slice($data['STRINGS'],
                        $data['LIMIT'], $data['NUM_STRINGS_SHOW']);
                    break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        "manageLocales", ['ALL_FIELDS' =>
                        ['name', 'tag', 'mode', 'active'],
                        'EQUAL_COMPARISON_TYPES' => ['active']]);
                    if (empty($_SESSION['LAST_SEARCH']["manageLocales"]) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']["manageLocales"] =
                            $_SESSION['SEARCH']["manageLocales"];
                        unset($_SESSION['SEARCH']["manageLocales"]);
                    } else {
                        $default_search = true;
                    }
                    break;
            }
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']["manageLocales"])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        "manageLocales");
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH']["manageLocales"][
                        'SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']["manageLocales"]['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array = [["tag", "", "", "ASC"]];;
            }
        }
        if ($paging) {
            $parent->pagingLogic($data, $locale_model,
                "LOCALES", C\DEFAULT_ADMIN_PAGING_NUM, $search_array);
        }
        if ($data['FORM_TYPE'] == 'addlocale') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Handles admin panel requests for mail, database, tor, proxy server
     * settings
     *
     * @return array $data data for the view concerning the current settings
     *     so they can be displayed
     */
    public function serverSettings()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $role_model = $parent->model("role");
        $activity_model = $parent->model("activity");
        $user_id = $_SESSION['USER_ID'];
        $data = [];
        $profile = [];
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "serversettings";
        switch ($arg) {
            case "clearCache":
                L\IndexManager::clearCache();
                $phrase_model = $parent->model("phrase");
                if (!empty($phrase_model::$cache)) {
                    $phrase_model::$cache->clear();
                }
                $crawl_model = $parent->model("crawl");
                $crawl_model->clearCrawlCaches();
                return $parent->redirectWithMessage(
                    tl('system_component_cache_cleared'));
            break;
            case "restart":
                return $parent->redirectWithMessage(
                    tl('system_component_server_restarted'), [], true);
            break;
            case "update":
                $parent->updateProfileFields($data, $profile,
                    ['AD_LOCATION', 'SEND_MAIL_MEDIA_UPDATER',
                    'USE_FILECACHE', 'USE_MAIL_PHP', 'USE_PROXY']);
                $old_profile =
                    $profile_model->getProfile(C\WORK_DIRECTORY);
                if (strcmp($old_profile["MONETIZATION_TYPE"],
                        $data["MONETIZATION_TYPE"]) !== 0) {
                    $user_role_id = $role_model->getRoleId('User');
                    $admin_role_id = $role_model->getRoleId('Admin');
                    $ad_id = $activity_model->getActivityIdFromMethodName(
                        'manageAdvertisements');
                    $credit_id = $activity_model->getActivityIdFromMethodName(
                        'manageCredits');
                    if (isset($data['MONETIZATION_TYPE']) &&
                        in_array($data['MONETIZATION_TYPE'],
                        ['no_monetization', 'external_advertisements'])) {
                        if ($user_role_id) {
                            $role_model->deleteActivityRole($user_role_id,
                                $ad_id);
                            $role_model->deleteActivityRole($user_role_id,
                                $credit_id);
                        }
                        $role_model->deleteActivityRole($admin_role_id,
                            $ad_id);
                        $role_model->deleteActivityRole($admin_role_id,
                            $credit_id);
                    } else if (isset($data['MONETIZATION_TYPE']) &&
                        in_array($data['MONETIZATION_TYPE'],['group_fees'])) {
                        if ($user_role_id) {
                            $role_model->deleteActivityRole($user_role_id,
                                $ad_id);
                            $role_model->addActivityRole($user_role_id,
                                $credit_id);
                        }
                        $role_model->deleteActivityRole($admin_role_id,
                            $ad_id);
                        $role_model->addActivityRole($admin_role_id,
                            $credit_id);
                    } else {
                        if ($user_role_id) {
                            $role_model->addActivityRole($user_role_id, $ad_id);
                            $role_model->addActivityRole($user_role_id,
                                $credit_id);
                        }
                        $role_model->addActivityRole($admin_role_id, $ad_id);
                        $role_model->addActivityRole($admin_role_id,
                            $credit_id);
                    }
                }
                $db_problem = false;
                if ((isset($profile['DBMS']) &&
                    $profile['DBMS'] != $old_profile['DBMS']) ||
                    (isset($profile['DB_NAME']) &&
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_HOST']) &&
                    $profile['DB_HOST'] != $old_profile['DB_HOST'])) {
                    if (!$profile_model->migrateDatabaseIfNecessary(
                        $profile)) {
                        $db_problem = true;
                    }
                } else if ((isset($profile['DB_USER']) &&
                    $profile['DB_USER'] != $old_profile['DB_USER']) ||
                    (isset($profile['DB_PASSWORD']) &&
                    $profile['DB_PASSWORD'] != $old_profile['DB_PASSWORD'])) {
                    if ($profile_model->testDatabaseManager(
                        $profile) !== true) {
                        $db_problem = true;
                    }
                }
                if ($db_problem) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_db'));
                }
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile)) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'),
                        false, true);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'));
                }
                break;
        }
        $data = array_merge($data,
            $profile_model->getProfile(C\WORK_DIRECTORY));
        if ($data['DBMS'] == lcfirst($data['DBMS'])) {
            //using old name, migrate to new
            $_REQUEST['DBMS'] = ucfirst($data['DBMS']);
            $parent->updateProfileFields($data, $profile,
                ['AD_LOCATION', 'SEND_MAIL_MEDIA_UPDATER',
                'USE_FILECACHE', 'USE_MAIL_PHP', 'USE_PROXY']);
            $old_profile = $profile_model->getProfile(C\WORK_DIRECTORY);
            $profile_model->updateProfile(C\WORK_DIRECTORY, $profile,
                $old_profile);
        }
        $data['PROXY_SERVERS'] = str_replace(
            "|Z|","\n", $data['PROXY_SERVERS']);
        $data['DBMSS'] = [];
        $data['SCRIPT'] .= "logindbms = [];\n";
        foreach ($profile_model->getDbmsList() as $dbms) {
            $data['DBMSS'][$dbms] = $dbms;
            if ($profile_model->loginDbms($dbms)) {
                $data['SCRIPT'] .= "logindbms['$dbms'] = true;\n";
            } else {
                $data['SCRIPT'] .= "logindbms['$dbms'] = false;\n";
            }
        }
        $data['REGISTRATION_TYPES'] = [
                'disable_registration' =>
                    tl('system_component_configure_disable_registration'),
                'no_activation' =>
                    tl('system_component_configure_no_activation'),
                'email_registration' =>
                    tl('system_component_configure_email_activation'),
                'admin_activation' =>
                    tl('system_component_configure_admin_activation'),
            ];
        $data['MONETIZATION_TYPES'] = [
            'no_monetization' =>
                tl('system_component_configure_none'),
            'external_advertisements' =>
                tl('system_component_configure_external_advertisements'),
            'group_fees' =>
                tl('system_component_configure_group_fees'),
            'keyword_advertisements' =>
                tl('system_component_configure_keyword_advertisements'),
            'fees_and_keywords' =>
                tl('system_component_configure_fees_and_keywords'),
             ];
        $data['CONFIGURE_BOTS'] = [
            'enable_bot_users' =>
                tl('system_component_enable_bot_users'),
            'disable_bot_users' =>
                tl('system_component_disable_bot_users')
             ];
        $data['show_mail_info'] = "false";
        if (isset($data['REGISTRATION_TYPE']) &&
            in_array($data['REGISTRATION_TYPE'],
            ['email_registration', 'admin_activation'])) {
            $data['show_mail_info'] = "true";
        }
        $data['no_mail_php'] =  ($data["USE_MAIL_PHP"]) ? "false" :"true";
        $data['SCRIPT'] .= <<< EOD
    elt('account-registration').onchange = function () {
        var show_mail_info = false;
        no_mail_registration = ['disable_registration', 'no_activation'];
        if (no_mail_registration.indexOf(elt('account-registration').value)
            < 0) {
            show_mail_info = true;
        }
        setDisplay('registration-info', show_mail_info);
    };
    setDisplay('registration-info', {$data['show_mail_info']});
    elt('use-php-mail').onchange = function () {
        setDisplay('smtp-info', (elt('use-php-mail').checked == false));
    };
    setDisplay('smtp-info', {$data['no_mail_php']});
    elt('database-system').onchange = function () {
        setDisplay('login-dbms', self.logindbms[elt('database-system').value]);
    };
    setDisplay('login-dbms', logindbms[elt('database-system').value]);
    elt('use-proxy').onchange = function () {
        setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
    };
    setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
    elt('monetization-type').onchange = function () {
        var monetization_type = elt('monetization-type').value;
        setDisplay('ad-location-info',
            (monetization_type == 'external_advertisements'));
        setDisplay('payment-processing',
            (monetization_type == 'fees_and_keywords' ||
            monetization_type == 'group_fees' ||
            monetization_type == 'keywords_advertisements'));
    };
EOD;
        return $data;
    }
    /**
     * Responsible for the Captcha Settings and managing Captcha/Recovery
     * questions.
     */
    public function security()
    {
        $parent = $this->parent;
        $captcha_model = $parent->model("captcha");
        $possible_arguments = ["updatequestions", "updatetypes"];
        $not_null_fields = [
            'TIMEZONE' => 'America/Los_Angeles',
            'SESSION_NAME' => "yioopbiscuit",
            'CSRF_TOKEN' => "YIOOP_TOKEN"
        ];
        $data = [];
        $data['TIMEZONES'] = [
            'America/Adak' => 'America/Adak',
            'America/Atka' => 'America/Atka',
            'America/Anchorage' => 'America/Anchorage',
            'America/Juneau' => 'America/Juneau',
            'America/Nome' => 'America/Nome',
            'America/Yakutat' => 'America/Yakutat',
            'America/Dawson' => 'America/Dawson',
            'America/Ensenada' => 'America/Ensenada',
            'America/Los_Angeles' => 'America/Los_Angeles',
            'America/Tijuana' => 'America/Tijuana',
            'America/Vancouver' => 'America/Vancouver',
            'America/Whitehorse' => 'America/Whitehorse',
            'Canada/Pacific' => 'Canada/Pacific',
            'Canada/Yukon' => 'Canada/Yukon',
            'Mexico/BajaNorte' => 'Mexico/BajaNorte',
            'America/Boise' => 'America/Boise',
            'America/Cambridge_Bay' => 'America/Cambridge_Bay',
            'America/Chihuahua' => 'America/Chihuahua',
            'America/Dawson_Creek' => 'America/Dawson_Creek',
            'America/Denver' => 'America/Denver',
            'America/Edmonton' => 'America/Edmonton',
            'America/Hermosillo' => 'America/Hermosillo',
            'America/Inuvik' => 'America/Inuvik',
            'America/Mazatlan' => 'America/Mazatlan',
            'America/Phoenix' => 'America/Phoenix',
            'America/Shiprock' => 'America/Shiprock',
            'America/Yellowknife' => 'America/Yellowknife',
            'Canada/Mountain' => 'Canada/Mountain',
            'Mexico/BajaSur' => 'Mexico/BajaSur',
            'America/Belize' => 'America/Belize',
            'America/Cancun' => 'America/Cancun',
            'America/Chicago' => 'America/Chicago',
            'America/Costa_Rica' => 'America/Costa_Rica',
            'America/El_Salvador' => 'America/El_Salvador',
            'America/Guatemala' => 'America/Guatemala',
            'America/Knox_IN' => 'America/Knox_IN',
            'America/Managua' => 'America/Managua',
            'America/Menominee' => 'America/Menominee',
            'America/Merida' => 'America/Merida',
            'America/Mexico_City' => 'America/Mexico_City',
            'America/Monterrey' => 'America/Monterrey',
            'America/Rainy_River' => 'America/Rainy_River',
            'America/Rankin_Inlet' => 'America/Rankin_Inlet',
            'America/Regina' => 'America/Regina',
            'America/Swift_Current' => 'America/Swift_Current',
            'America/Tegucigalpa' => 'America/Tegucigalpa',
            'America/Winnipeg' => 'America/Winnipeg',
            'Canada/Central' => 'Canada/Central',
            'Canada/East-Saskatchewan' => 'Canada/East-Saskatchewan',
            'Canada/Saskatchewan' => 'Canada/Saskatchewan',
            'Chile/EasterIsland' => 'Chile/EasterIsland',
            'Mexico/General' => 'Mexico/General',
            'America/Atikokan' => 'America/Atikokan',
            'America/Bogota' => 'America/Bogota',
            'America/Cayman' => 'America/Cayman',
            'America/Coral_Harbour' => 'America/Coral_Harbour',
            'America/Detroit' => 'America/Detroit',
            'America/Fort_Wayne' => 'America/Fort_Wayne',
            'America/Grand_Turk' => 'America/Grand_Turk',
            'America/Guayaquil' => 'America/Guayaquil',
            'America/Havana' => 'America/Havana',
            'America/Indianapolis' => 'America/Indianapolis',
            'America/Iqaluit' => 'America/Iqaluit',
            'America/Jamaica' => 'America/Jamaica',
            'America/Lima' => 'America/Lima',
            'America/Louisville' => 'America/Louisville',
            'America/Montreal' => 'America/Montreal',
            'America/Nassau' => 'America/Nassau',
            'America/New_York' => 'America/New_York',
            'America/Nipigon' => 'America/Nipigon',
            'America/Panama' => 'America/Panama',
            'America/Pangnirtung' => 'America/Pangnirtung',
            'America/Port-au-Prince' => 'America/Port-au-Prince',
            'America/Resolute' => 'America/Resolute',
            'America/Thunder_Bay' => 'America/Thunder_Bay',
            'America/Toronto' => 'America/Toronto',
            'Canada/Eastern' => 'Canada/Eastern',
            'America/Caracas' => 'America/Caracas',
            'America/Anguilla' => 'America/Anguilla',
            'America/Antigua' => 'America/Antigua',
            'America/Aruba' => 'America/Aruba',
            'America/Asuncion' => 'America/Asuncion',
            'America/Barbados' => 'America/Barbados',
            'America/Blanc-Sablon' => 'America/Blanc-Sablon',
            'America/Boa_Vista' => 'America/Boa_Vista',
            'America/Campo_Grande' => 'America/Campo_Grande',
            'America/Cuiaba' => 'America/Cuiaba',
            'America/Curacao' => 'America/Curacao',
            'America/Dominica' => 'America/Dominica',
            'America/Eirunepe' => 'America/Eirunepe',
            'America/Glace_Bay' => 'America/Glace_Bay',
            'America/Goose_Bay' => 'America/Goose_Bay',
            'America/Grenada' => 'America/Grenada',
            'America/Guadeloupe' => 'America/Guadeloupe',
            'America/Guyana' => 'America/Guyana',
            'America/Halifax' => 'America/Halifax',
            'America/La_Paz' => 'America/La_Paz',
            'America/Manaus' => 'America/Manaus',
            'America/Marigot' => 'America/Marigot',
            'America/Martinique' => 'America/Martinique',
            'America/Moncton' => 'America/Moncton',
            'America/Montserrat' => 'America/Montserrat',
            'America/Port_of_Spain' => 'America/Port_of_Spain',
            'America/Porto_Acre' => 'America/Porto_Acre',
            'America/Porto_Velho' => 'America/Porto_Velho',
            'America/Puerto_Rico' => 'America/Puerto_Rico',
            'America/Rio_Branco' => 'America/Rio_Branco',
            'America/Santiago' => 'America/Santiago',
            'America/Santo_Domingo' => 'America/Santo_Domingo',
            'America/St_Barthelemy' => 'America/St_Barthelemy',
            'America/St_Kitts' => 'America/St_Kitts',
            'America/St_Lucia' => 'America/St_Lucia',
            'America/St_Thomas' => 'America/St_Thomas',
            'America/St_Vincent' => 'America/St_Vincent',
            'America/Thule' => 'America/Thule',
            'America/Tortola' => 'America/Tortola',
            'America/Virgin' => 'America/Virgin',
            'Antarctica/Palmer' => 'Antarctica/Palmer',
            'Atlantic/Bermuda' => 'Atlantic/Bermuda',
            'Atlantic/Stanley' => 'Atlantic/Stanley',
            'Brazil/Acre' => 'Brazil/Acre',
            'Brazil/West' => 'Brazil/West',
            'Canada/Atlantic' => 'Canada/Atlantic',
            'Chile/Continental' => 'Chile/Continental',
            'America/St_Johns' => 'America/St_Johns',
            'Canada/Newfoundland' => 'Canada/Newfoundland',
            'America/Araguaina' => 'America/Araguaina',
            'America/Bahia' => 'America/Bahia',
            'America/Belem' => 'America/Belem',
            'America/Buenos_Aires' => 'America/Buenos_Aires',
            'America/Catamarca' => 'America/Catamarca',
            'America/Cayenne' => 'America/Cayenne',
            'America/Cordoba' => 'America/Cordoba',
            'America/Fortaleza' => 'America/Fortaleza',
            'America/Godthab' => 'America/Godthab',
            'America/Jujuy' => 'America/Jujuy',
            'America/Maceio' => 'America/Maceio',
            'America/Mendoza' => 'America/Mendoza',
            'America/Miquelon' => 'America/Miquelon',
            'America/Montevideo' => 'America/Montevideo',
            'America/Paramaribo' => 'America/Paramaribo',
            'America/Recife' => 'America/Recife',
            'America/Rosario' => 'America/Rosario',
            'America/Santarem' => 'America/Santarem',
            'America/Sao_Paulo' => 'America/Sao_Paulo',
            'Antarctica/Rothera' => 'Antarctica/Rothera',
            'Brazil/East' => 'Brazil/East',
            'America/Noronha' => 'America/Noronha',
            'Atlantic/South_Georgia' => 'Atlantic/South_Georgia',
            'Brazil/DeNoronha' => 'Brazil/DeNoronha',
            'America/Scoresbysund' => 'America/Scoresbysund',
            'Atlantic/Azores' => 'Atlantic/Azores',
            'Atlantic/Cape_Verde' => 'Atlantic/Cape_Verde',
            'Africa/Abidjan' => 'Africa/Abidjan',
            'Africa/Accra' => 'Africa/Accra',
            'Africa/Bamako' => 'Africa/Bamako',
            'Africa/Banjul' => 'Africa/Banjul',
            'Africa/Bissau' => 'Africa/Bissau',
            'Africa/Casablanca' => 'Africa/Casablanca',
            'Africa/Conakry' => 'Africa/Conakry',
            'Africa/Dakar' => 'Africa/Dakar',
            'Africa/El_Aaiun' => 'Africa/El_Aaiun',
            'Africa/Freetown' => 'Africa/Freetown',
            'Africa/Lome' => 'Africa/Lome',
            'Africa/Monrovia' => 'Africa/Monrovia',
            'Africa/Nouakchott' => 'Africa/Nouakchott',
            'Africa/Ouagadougou' => 'Africa/Ouagadougou',
            'Africa/Sao_Tome' => 'Africa/Sao_Tome',
            'Africa/Timbuktu' => 'Africa/Timbuktu',
            'America/Danmarkshavn' => 'America/Danmarkshavn',
            'Atlantic/Canary' => 'Atlantic/Canary',
            'Atlantic/Faeroe' => 'Atlantic/Faeroe',
            'Atlantic/Faroe' => 'Atlantic/Faroe',
            'Atlantic/Madeira' => 'Atlantic/Madeira',
            'Atlantic/Reykjavik' => 'Atlantic/Reykjavik',
            'Atlantic/St_Helena' => 'Atlantic/St_Helena',
            'Europe/Belfast' => 'Europe/Belfast',
            'Europe/Dublin' => 'Europe/Dublin',
            'Europe/Guernsey' => 'Europe/Guernsey',
            'Europe/Isle_of_Man' => 'Europe/Isle_of_Man',
            'Europe/Jersey' => 'Europe/Jersey',
            'Europe/Lisbon' => 'Europe/Lisbon',
            'Europe/London' => 'Europe/London',
            'Africa/Algiers' => 'Africa/Algiers',
            'Africa/Bangui' => 'Africa/Bangui',
            'Africa/Brazzaville' => 'Africa/Brazzaville',
            'Africa/Ceuta' => 'Africa/Ceuta',
            'Africa/Douala' => 'Africa/Douala',
            'Africa/Kinshasa' => 'Africa/Kinshasa',
            'Africa/Lagos' => 'Africa/Lagos',
            'Africa/Libreville' => 'Africa/Libreville',
            'Africa/Luanda' => 'Africa/Luanda',
            'Africa/Malabo' => 'Africa/Malabo',
            'Africa/Ndjamena' => 'Africa/Ndjamena',
            'Africa/Niamey' => 'Africa/Niamey',
            'Africa/Porto-Novo' => 'Africa/Porto-Novo',
            'Africa/Tunis' => 'Africa/Tunis',
            'Africa/Windhoek' => 'Africa/Windhoek',
            'Arctic/Longyearbyen' => 'Arctic/Longyearbyen',
            'Atlantic/Jan_Mayen' => 'Atlantic/Jan_Mayen',
            'Europe/Amsterdam' => 'Europe/Amsterdam',
            'Europe/Andorra' => 'Europe/Andorra',
            'Europe/Belgrade' => 'Europe/Belgrade',
            'Europe/Berlin' => 'Europe/Berlin',
            'Europe/Bratislava' => 'Europe/Bratislava',
            'Europe/Brussels' => 'Europe/Brussels',
            'Europe/Budapest' => 'Europe/Budapest',
            'Europe/Copenhagen' => 'Europe/Copenhagen',
            'Europe/Gibraltar' => 'Europe/Gibraltar',
            'Europe/Ljubljana' => 'Europe/Ljubljana',
            'Europe/Luxembourg' => 'Europe/Luxembourg',
            'Europe/Madrid' => 'Europe/Madrid',
            'Europe/Malta' => 'Europe/Malta',
            'Europe/Monaco' => 'Europe/Monaco',
            'Europe/Oslo' => 'Europe/Oslo',
            'Europe/Paris' => 'Europe/Paris',
            'Europe/Podgorica' => 'Europe/Podgorica',
            'Europe/Prague' => 'Europe/Prague',
            'Europe/Rome' => 'Europe/Rome',
            'Europe/San_Marino' => 'Europe/San_Marino',
            'Europe/Sarajevo' => 'Europe/Sarajevo',
            'Europe/Skopje' => 'Europe/Skopje',
            'Europe/Stockholm' => 'Europe/Stockholm',
            'Europe/Tirane' => 'Europe/Tirane',
            'Europe/Vaduz' => 'Europe/Vaduz',
            'Europe/Vatican' => 'Europe/Vatican',
            'Europe/Vienna' => 'Europe/Vienna',
            'Europe/Warsaw' => 'Europe/Warsaw',
            'Europe/Zagreb' => 'Europe/Zagreb',
            'Europe/Zurich' => 'Europe/Zurich',
            'Africa/Blantyre' => 'Africa/Blantyre',
            'Africa/Bujumbura' => 'Africa/Bujumbura',
            'Africa/Cairo' => 'Africa/Cairo',
            'Africa/Gaborone' => 'Africa/Gaborone',
            'Africa/Harare' => 'Africa/Harare',
            'Africa/Johannesburg' => 'Africa/Johannesburg',
            'Africa/Kigali' => 'Africa/Kigali',
            'Africa/Lubumbashi' => 'Africa/Lubumbashi',
            'Africa/Lusaka' => 'Africa/Lusaka',
            'Africa/Maputo' => 'Africa/Maputo',
            'Africa/Maseru' => 'Africa/Maseru',
            'Africa/Mbabane' => 'Africa/Mbabane',
            'Africa/Tripoli' => 'Africa/Tripoli',
            'Asia/Amman' => 'Asia/Amman',
            'Asia/Beirut' => 'Asia/Beirut',
            'Asia/Damascus' => 'Asia/Damascus',
            'Asia/Gaza' => 'Asia/Gaza',
            'Asia/Istanbul' => 'Asia/Istanbul',
            'Asia/Jerusalem' => 'Asia/Jerusalem',
            'Asia/Nicosia' => 'Asia/Nicosia',
            'Asia/Tel_Aviv' => 'Asia/Tel_Aviv',
            'Europe/Athens' => 'Europe/Athens',
            'Europe/Bucharest' => 'Europe/Bucharest',
            'Europe/Chisinau' => 'Europe/Chisinau',
            'Europe/Helsinki' => 'Europe/Helsinki',
            'Europe/Istanbul' => 'Europe/Istanbul',
            'Europe/Kaliningrad' => 'Europe/Kaliningrad',
            'Europe/Kiev' => 'Europe/Kiev',
            'Europe/Mariehamn' => 'Europe/Mariehamn',
            'Europe/Minsk' => 'Europe/Minsk',
            'Europe/Nicosia' => 'Europe/Nicosia',
            'Europe/Riga' => 'Europe/Riga',
            'Europe/Simferopol' => 'Europe/Simferopol',
            'Europe/Sofia' => 'Europe/Sofia',
            'Europe/Tallinn' => 'Europe/Tallinn',
            'Europe/Tiraspol' => 'Europe/Tiraspol',
            'Europe/Uzhgorod' => 'Europe/Uzhgorod',
            'Europe/Vilnius' => 'Europe/Vilnius',
            'Europe/Zaporozhye' => 'Europe/Zaporozhye',
            'Africa/Addis_Ababa' => 'Africa/Addis_Ababa',
            'Africa/Asmara' => 'Africa/Asmara',
            'Africa/Asmera' => 'Africa/Asmera',
            'Africa/Dar_es_Salaam' => 'Africa/Dar_es_Salaam',
            'Africa/Djibouti' => 'Africa/Djibouti',
            'Africa/Kampala' => 'Africa/Kampala',
            'Africa/Khartoum' => 'Africa/Khartoum',
            'Africa/Mogadishu' => 'Africa/Mogadishu',
            'Africa/Nairobi' => 'Africa/Nairobi',
            'Antarctica/Syowa' => 'Antarctica/Syowa',
            'Asia/Aden' => 'Asia/Aden',
            'Asia/Baghdad' => 'Asia/Baghdad',
            'Asia/Bahrain' => 'Asia/Bahrain',
            'Asia/Kuwait' => 'Asia/Kuwait',
            'Asia/Qatar' => 'Asia/Qatar',
            'Europe/Moscow' => 'Europe/Moscow',
            'Europe/Volgograd' => 'Europe/Volgograd',
            'Indian/Antananarivo' => 'Indian/Antananarivo',
            'Indian/Comoro' => 'Indian/Comoro',
            'Indian/Mayotte' => 'Indian/Mayotte',
            'Asia/Tehran' => 'Asia/Tehran',
            'Asia/Baku' => 'Asia/Baku',
            'Asia/Dubai' => 'Asia/Dubai',
            'Asia/Muscat' => 'Asia/Muscat',
            'Asia/Tbilisi' => 'Asia/Tbilisi',
            'Asia/Yerevan' => 'Asia/Yerevan',
            'Europe/Samara' => 'Europe/Samara',
            'Indian/Mahe' => 'Indian/Mahe',
            'Indian/Mauritius' => 'Indian/Mauritius',
            'Indian/Reunion' => 'Indian/Reunion',
            'Asia/Kabul' => 'Asia/Kabul',
            'Asia/Aqtau' => 'Asia/Aqtau',
            'Asia/Aqtobe' => 'Asia/Aqtobe',
            'Asia/Ashgabat' => 'Asia/Ashgabat',
            'Asia/Ashkhabad' => 'Asia/Ashkhabad',
            'Asia/Dushanbe' => 'Asia/Dushanbe',
            'Asia/Karachi' => 'Asia/Karachi',
            'Asia/Oral' => 'Asia/Oral',
            'Asia/Samarkand' => 'Asia/Samarkand',
            'Asia/Tashkent' => 'Asia/Tashkent',
            'Asia/Yekaterinburg' => 'Asia/Yekaterinburg',
            'Indian/Kerguelen' => 'Indian/Kerguelen',
            'Indian/Maldives' => 'Indian/Maldives',
            'Asia/Calcutta' => 'Asia/Calcutta',
            'Asia/Colombo' => 'Asia/Colombo',
            'Asia/Kolkata' => 'Asia/Kolkata',
            'Asia/Katmandu' => 'Asia/Katmandu',
            'Antarctica/Mawson' => 'Antarctica/Mawson',
            'Antarctica/Vostok' => 'Antarctica/Vostok',
            'Asia/Almaty' => 'Asia/Almaty',
            'Asia/Bishkek' => 'Asia/Bishkek',
            'Asia/Dacca' => 'Asia/Dacca',
            'Asia/Dhaka' => 'Asia/Dhaka',
            'Asia/Novosibirsk' => 'Asia/Novosibirsk',
            'Asia/Omsk' => 'Asia/Omsk',
            'Asia/Qyzylorda' => 'Asia/Qyzylorda',
            'Asia/Thimbu' => 'Asia/Thimbu',
            'Asia/Thimphu' => 'Asia/Thimphu',
            'Indian/Chagos' => 'Indian/Chagos',
            'Asia/Rangoon' => 'Asia/Rangoon',
            'Indian/Cocos' => 'Indian/Cocos',
            'Antarctica/Davis' => 'Antarctica/Davis',
            'Asia/Bangkok' => 'Asia/Bangkok',
            'Asia/Ho_Chi_Minh' => 'Asia/Ho_Chi_Minh',
            'Asia/Hovd' => 'Asia/Hovd',
            'Asia/Jakarta' => 'Asia/Jakarta',
            'Asia/Krasnoyarsk' => 'Asia/Krasnoyarsk',
            'Asia/Phnom_Penh' => 'Asia/Phnom_Penh',
            'Asia/Pontianak' => 'Asia/Pontianak',
            'Asia/Saigon' => 'Asia/Saigon',
            'Asia/Vientiane' => 'Asia/Vientiane',
            'Indian/Christmas' => 'Indian/Christmas',
            'Antarctica/Casey' => 'Antarctica/Casey',
            'Asia/Brunei' => 'Asia/Brunei',
            'Asia/Choibalsan' => 'Asia/Choibalsan',
            'Asia/Chongqing' => 'Asia/Chongqing',
            'Asia/Chungking' => 'Asia/Chungking',
            'Asia/Harbin' => 'Asia/Harbin',
            'Asia/Hong_Kong' => 'Asia/Hong_Kong',
            'Asia/Irkutsk' => 'Asia/Irkutsk',
            'Asia/Kashgar' => 'Asia/Kashgar',
            'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
            'Asia/Kuching' => 'Asia/Kuching',
            'Asia/Macao' => 'Asia/Macao',
            'Asia/Macau' => 'Asia/Macau',
            'Asia/Makassar' => 'Asia/Makassar',
            'Asia/Manila' => 'Asia/Manila',
            'Asia/Shanghai' => 'Asia/Shanghai',
            'Asia/Singapore' => 'Asia/Singapore',
            'Asia/Taipei' => 'Asia/Taipei',
            'Asia/Ujung_Pandang' => 'Asia/Ujung_Pandang',
            'Asia/Ulaanbaatar' => 'Asia/Ulaanbaatar',
            'Asia/Ulan_Bator' => 'Asia/Ulan_Bator',
            'Asia/Urumqi' => 'Asia/Urumqi',
            'Australia/Perth' => 'Australia/Perth',
            'Australia/West' => 'Australia/West',
            'Australia/Eucla' => 'Australia/Eucla',
            'Asia/Dili' => 'Asia/Dili',
            'Asia/Jayapura' => 'Asia/Jayapura',
            'Asia/Pyongyang' => 'Asia/Pyongyang',
            'Asia/Seoul' => 'Asia/Seoul',
            'Asia/Tokyo' => 'Asia/Tokyo',
            'Asia/Yakutsk' => 'Asia/Yakutsk',
            'Australia/Adelaide' => 'Australia/Adelaide',
            'Australia/Broken_Hill' => 'Australia/Broken_Hill',
            'Australia/Darwin' => 'Australia/Darwin',
            'Australia/North' => 'Australia/North',
            'Australia/South' => 'Australia/South',
            'Australia/Yancowinna' => 'Australia/Yancowinna',
            'Antarctica/DumontDUrville' => 'Antarctica/DumontDUrville',
            'Asia/Sakhalin' => 'Asia/Sakhalin',
            'Asia/Vladivostok' => 'Asia/Vladivostok',
            'Australia/ACT' => 'Australia/ACT',
            'Australia/Brisbane' => 'Australia/Brisbane',
            'Australia/Canberra' => 'Australia/Canberra',
            'Australia/Currie' => 'Australia/Currie',
            'Australia/Hobart' => 'Australia/Hobart',
            'Australia/Lindeman' => 'Australia/Lindeman',
            'Australia/Melbourne' => 'Australia/Melbourne',
            'Australia/NSW' => 'Australia/NSW',
            'Australia/Queensland' => 'Australia/Queensland',
            'Australia/Sydney' => 'Australia/Sydney',
            'Australia/Tasmania' => 'Australia/Tasmania',
            'Australia/Victoria' => 'Australia/Victoria',
            'Australia/LHI' => 'Australia/LHI',
            'Australia/Lord_Howe' => 'Australia/Lord_Howe',
            'Asia/Magadan' => 'Asia/Magadan',
            'Antarctica/McMurdo' => 'Antarctica/McMurdo',
            'Antarctica/South_Pole' => 'Antarctica/South_Pole',
            'Asia/Anadyr' => 'Asia/Anadyr',
            'Asia/Kamchatka' => 'Asia/Kamchatka'
        ];
        $data['AUTOLOGOUT_TIMES'] = [
            2 * C\ONE_MINUTE => tl('system_component_two_minutes'),
            15 * C\ONE_MINUTE => tl('system_component_fifteen_minutes'),
            30 * C\ONE_MINUTE => tl('system_component_half_hour'),
            C\ONE_HOUR => tl('system_component_one_hour'),
            2 * C\ONE_HOUR => tl('system_component_two_hours'),
            C\ONE_DAY => tl('system_component_one_day'),
        ];
        $data['COOKIE_LIFETIMES'] = [
            -1 => tl('system_component_consent_disabled'),
            C\ONE_MONTH => tl('system_component_one_month'),
            6 * C\ONE_MONTH => tl('system_component_six_month'),
            C\ONE_YEAR => tl('system_component_one_year'),
            2 * C\ONE_YEAR => tl('system_component_two_years'),
        ];
        $data['CAN_LOCALIZE'] = $parent->model("user")->isAllowedUserActivity(
            $_SESSION['USER_ID'], "manageLocales");
        $profile_model = $parent->model("profile");
        $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "security";
        $data["CURRENT_LOCALE"] = L\getLocaleTag();
        $data['CAPTCHA_MODES'] = [
           C\HASH_CAPTCHA =>
               tl('system_component_hash_captcha'),
           C\IMAGE_CAPTCHA =>
               tl('system_component_image_captcha'),
            ];
        $data['RECOVERY_MODES'] = [
           C\NO_RECOVERY =>
               tl('system_component_no_recovery'),
           C\EMAIL_RECOVERY =>
               tl('system_component_email_recovery'),
           C\EMAIL_AND_QUESTIONS_RECOVERY =>
               tl('system_component_email_questions'),
            ];
        $data['PRIVACY_MODES'] = [
           true =>
               tl('system_component_enable'),
           false =>
               tl('system_component_disable'),
            ];
        $data['GROUP_ANALYTICS_MODES'] = $data['PRIVACY_MODES'];
        $data['SEARCH_ANALYTICS_MODES'] = $data['PRIVACY_MODES'];
        if (empty($profile['AUTOLOGOUT'])) {
            $profile['AUTOLOGOUT'] = C\ONE_HOUR;
            $change = true;
        }
        if (empty($profile['COOKIE_LIFETIME'])) {
            $profile['COOKIE_LIFETIME'] = C\ONE_YEAR;
            $change = true;
        }
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "updatetypes":
                    $change = false;
                    $mode_fields = ['AUTOLOGOUT', 'CAPTCHA_MODE',
                        'COOKIE_LIFETIME', 'DIFFERENTIAL_PRIVACY',
                        'GROUP_ANALYTICS_MODE', 'RECOVERY_MODE',
                        'SEARCH_ANALYTICS_MODE'];
                    foreach ($mode_fields as $mode) {
                        $modes = ($mode == 'DIFFERENTIAL_PRIVACY') ?
                            'PRIVACY_MODES': (($mode == 'AUTOLOGOUT')
                            ? "AUTOLOGOUT_TIMES" : $mode . "S");
                        if (in_array($_REQUEST[$mode],
                            array_keys($data[$modes]))) {
                            $profile[$mode] = $_REQUEST[$mode];
                            $change = true;
                        }
                    }
                    foreach ($not_null_fields as $field => $value) {
                        if (!empty($_REQUEST[$field])) {
                            $clean_value = $parent->clean($_REQUEST[$field],
                                "string");
                            $profile[$field] = $clean_value;
                            $change = true;
                        }
                        if (empty($profile[$field])) {
                            $profile[$field] = $value;
                            $change = true;
                        }
                    }
                    if ($change) {
                        $profile_model->updateProfile(C\WORK_DIRECTORY,
                            [], $profile);
                        return $parent->redirectWithMessage(
                            tl('system_component_settings_updated'),
                            false, true);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_no_update_settings'));
                    }
                    break;
            }
        }
        $data = array_merge($data,
            $profile_model->getProfile(C\WORK_DIRECTORY));
        $data['AUTOLOGOUT'] = $profile['AUTOLOGOUT'];
        $data["CAPTCHA_MODE"] = $profile["CAPTCHA_MODE"];
        $data["RECOVERY_MODE"] = $profile["RECOVERY_MODE"];
        return $data;
    }
    /**
     * Responsible for handling admin request related to the appearance activity
     *
     * The activity is used to control the look and feel of the Yioop instance
     * such as foreground, background color, icons, etc.
     *
     * @return array $data fields for current appearance settings
     */
    public function appearance()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $group_model = $parent->model("group");
        $data = [];
        $profile = [];
        $data["ELEMENT"] = "appearance";
        $data['SCRIPT'] = "";
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch ($arg) {
            case "profile":
                $parent->updateProfileFields($data, $profile,
                    ['LANDING_PAGE']);
                $old_profile =
                    $profile_model->getProfile(C\WORK_DIRECTORY);
                $folder = C\APP_DIR . "/resources";
                if ((!file_exists(C\APP_DIR) && !mkdir(C\APP_DIR)) ||
                    (!file_exists($folder) && !mkdir($folder))) {
                    return $parent->redirectWithMessage(
                        tl('system_component_no_resource_folder'),
                        ['advanced', 'lang']);
                }
                foreach (array('BACKGROUND_IMAGE', 'LOGO_SMALL',
                    'LOGO_MEDIUM', 'LOGO_LARGE', 'FAVICON',
                    'SEARCHBAR_PATH') as $field) {
                    if (isset($_FILES[$field]['name']) &&
                        $_FILES[$field]['name'] !="") {
                        if ((!in_array($_FILES[$field]['type'],
                            ['image/png', 'image/gif', 'image/jpeg',
                                'image/x-icon']) &&
                            $field != 'SEARCHBAR_PATH') || (
                            $_FILES[$field]['type'] != 'text/xml' &&
                            $field == 'SEARCHBAR_PATH')) {
                            return $parent->redirectWithMessage(
                                tl('system_component_invalid_filetype'),
                                ['advanced', 'lang']);
                        }
                        if ($_FILES[$field]['size'] > C\THUMB_SIZE) {
                            return $parent->redirectWithMessage(
                                tl('system_component_file_too_big'),
                                ['advanced', 'lang']);
                        }
                        $profile[$field] = [];
                        $profile[$field]['name'] = $_FILES[$field]['name'];
                        $profile[$field]['tmp_name'] =
                            $_FILES[$field]['tmp_name'];
                        if (!empty($_FILES[$field]['data'])) {
                            $profile[$field]['data'] =
                                $_FILES[$field]['data'];
                        }
                        if (C\REDIRECTS_ON) {
                            $data[$field] =
                                "wd/resources/" . $profile[$field]['name'];
                        } else {
                            $data[$field] =
                                "?c=resource&amp;a=get&amp;" .
                                "f=resources&amp;n=" . $profile[$field]['name'];
                        }
                    }
                }
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile)) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'),
                        ['advanced', 'lang'], true);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'),
                        ['advanced', 'lang']);
                }
                break;
            case "reset":
                $base_url = (C\nsdefined("BASE_URL")) ? C\BASE_URL :
                    C\NAME_SERVER;
                $name_server_url =  (C\NAME_SERVER . "src/" == C\BASE_URL ||
                    C\NAME_SERVER . "/src/" == C\BASE_URL) ? C\BASE_URL :
                    C\NAME_SERVER;
                $profile = [
                    'LANDING_PAGE' => false,
                    'BACKGROUND_COLOR' => "#FFFFFF",
                    'BACKGROUND_IMAGE' => "",
                    'FOREGROUND_COLOR' => "#FFFFFF",
                    'SIDEBAR_COLOR' => "#F0F0F0",
                    'TOPBAR_COLOR' => "#EEEEFF",
                    'LOGO_SMALL' => "resources/yioop-small.png",
                    'LOGO_MEDIUM' => "resources/yioop-medium.png",
                    'LOGO_LARGE' => "resources/yioop-large.png",
                    'FAVICON' => "favicon.ico",
                    'TIMEZONE' => 'America/Los_Angeles',
                    'SESSION_NAME' => "yioopbiscuit",
                    'CSRF_TOKEN' => "YIOOP_TOKEN",
                    'AUXILIARY_CSS' => "",
                    'SEARCHBAR_PATH' => $name_server_url . "yioopbar.xml"
                ];
                $old_profile = $profile_model->getProfile(C\WORK_DIRECTORY);
                foreach ($old_profile as $key => $value) {
                    $data[$key] = $value;
                }
                $tmp_image = $old_profile['BACKGROUND_IMAGE'];
                $old_profile['BACKGROUND_IMAGE'] = "";
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile,
                    true)) {
                    $old_profile['BACKGROUND_IMAGE'] = $tmp_image;
                    foreach ($profile as $key => $value) {
                        $data[$key] = $value;
                        if (in_array($key, ['BACKGROUND_IMAGE',
                            'LOGO_SMALL', 'LOGO_MEDIUM', 'LOGO_LARGE',
                            'FAVICON', 'SEARCHBAR_PATH'] )
                            && $old_profile[$key] != "") {
                            $resource_name = C\APP_DIR ."/resources/".
                                $old_profile[$key];
                            if (file_exists($resource_name)) {
                                unlink($resource_name);
                            }
                        }
                    }
                    $_REQUEST['advanced'] = "true";
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_reset_completed'),
                        false,true);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'));
                }
                break;
            default:
                $data = array_merge($data,
                    $profile_model->getProfile(C\WORK_DIRECTORY));
        }
        $locale_tag = L\getLocaleTag();
        $not_null_fields = [
            'LOGO_SMALL' => "resources/yioop-small.png",
            'LOGO_MEDIUM' => "resources/yioop-medium.png",
            'LOGO_LARGE' => "resources/yioop-large.png",
            'FAVICON' => "favicon.ico",
        ];
        foreach ($not_null_fields as $field => $default) {
            if (!$data[$field]) {
                $data[$field] = $default;
            }
        }
        return $data;
    }
    /**
     * Responsible for handling admin request related to the configure activity
     *
     * The configure activity allows a user to set the work directory for
     * storing data local to this SeekQuarry/Yioop instance. It also allows one
     * to set the default language of the installation, debug info, robot info,
     * test info, etc.
     *
     * @return array $data fields for available language, debug level,
     *      etc as well as results of processing sub activity if any
     */
    public function configure()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $group_model = $parent->model("group");
        $data = [];
        $profile = [];
        $data['SYSTEM_CHECK'] = $this->systemCheck();
        $languages = $parent->model("locale")->getLocaleList();
        foreach ($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if (isset($_REQUEST['lang']) && $_REQUEST['lang']) {
            $data['lang'] = $parent->clean($_REQUEST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            L\setLocaleObject($data['lang']);
        }
        $data["ELEMENT"] = "configure";
        $data['SCRIPT'] = "";
        $data['PROFILE'] = false;
        if (isset($_REQUEST['WORK_DIRECTORY']) ||
            (C\nsdefined('WORK_DIRECTORY') &&
            C\nsdefined('FIX_NAME_SERVER') && C\FIX_NAME_SERVER) ) {
            if (C\nsdefined('WORK_DIRECTORY') && C\nsdefined('FIX_NAME_SERVER')
                && C\FIX_NAME_SERVER && !isset($_REQUEST['WORK_DIRECTORY'])) {
                $_REQUEST['WORK_DIRECTORY'] = C\WORK_DIRECTORY;
                $_REQUEST['arg'] = "directory";
                @unlink($_REQUEST['WORK_DIRECTORY']."/".C\PROFILE_FILE_NAME);
            }
            $dir =
                $parent->clean($_REQUEST['WORK_DIRECTORY'], "string");
            $data['PROFILE'] = true;
            if (strstr(PHP_OS, "WIN")) {
                //convert to forward slashes so consistent with rest of code
                $dir = str_replace("\\", "/", $dir);
                if ($dir[0] != "/" && $dir[1] != ":") {
                    $data['PROFILE'] = false;
                }
            } else if ($dir[0] != "/") {
                    $data['PROFILE'] = false;
            }
            if ($data['PROFILE'] == false) {
                return $parent->redirectWithMessage(
                    tl('system_component_configure_use_absolute_path'),
                    ['lang']);
            }
            if (strstr($dir."/", C\BASE_DIR)) {
                return $parent->redirectWithMessage(
                    tl('system_component_configure_configure_diff_base_dir'),
                    ['lang']);
            }
            $data['WORK_DIRECTORY'] = $dir;
        } else if (C\nsdefined("WORK_DIRECTORY") &&
            strlen(C\WORK_DIRECTORY) > 0 &&
            strcmp(realpath(C\WORK_DIRECTORY), realpath(C\BASE_DIR)) != 0 &&
            (is_dir(C\WORK_DIRECTORY) || is_dir(C\WORK_DIRECTORY."../"))) {
            $data['WORK_DIRECTORY'] = C\WORK_DIRECTORY;
            $data['PROFILE'] = true;
            if (C\WORK_DIRECTORY == C\DEFAULT_WORK_DIRECTORY &&
                is_writable(C\WORK_DIRECTORY) &&
                !file_exists(C\WORK_DIRECTORY. C\PROFILE_FILE_NAME) ) {
                $_REQUEST['arg'] = 'directory';
            }
        }
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch ($arg) {
            case "directory":
                if (!isset($data['WORK_DIRECTORY'])) {break;}
                if ($data['PROFILE'] &&
                    file_exists($data['WORK_DIRECTORY']."/".
                        C\PROFILE_FILE_NAME)) {
                    $data = array_merge($data, $profile_model->getProfile(
                            $data['WORK_DIRECTORY']));
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_work_dir_set'),
                        ['lang'], true);
                } else if ($data['PROFILE'] &&
                    strlen($data['WORK_DIRECTORY']) > 0) {
                    if ($profile_model->makeWorkDirectory(
                        $data['WORK_DIRECTORY'])) {
                        $profile['DBMS'] = 'sqlite3';
                        $data['DBMS'] = 'sqlite3';
                        $profile['DB_NAME'] = 'public_default';
                        $data['DB_NAME'] = 'public_default';
                        $profile['PRIVATE_DBMS'] = 'sqlite3';
                        $data['PRIVATE_DBMS'] = 'sqlite3';
                        $profile['PRIVATE_DB_NAME'] = 'private_default';
                        $data['PRIVATE_DB_NAME'] = 'private_default';
                        $profile['USER_AGENT_SHORT'] =
                            tl('system_component_name_your_bot');
                        $data['USER_AGENT_SHORT'] =
                            $profile['USER_AGENT_SHORT'];
                        $profile['NAME_SERVER'] = C\BASE_URL;
                        $data['NAME_SERVER'] = $profile['NAME_SERVER'];
                        $profile['AUTH_KEY'] = L\crawlHash(
                            $data['WORK_DIRECTORY'].time());
                        $data['AUTH_KEY'] = $profile['AUTH_KEY'];
                        $robot_instance = str_replace(".", "_",
                            $_SERVER['SERVER_NAME'])."-".time();
                        $profile['ROBOT_INSTANCE'] = $robot_instance;
                        $data['ROBOT_INSTANCE'] = $profile['ROBOT_INSTANCE'];
                        if ($profile_model->updateProfile(
                            $data['WORK_DIRECTORY'], [], $profile)) {
                            if ((defined('WORK_DIRECTORY') &&
                                $data['WORK_DIRECTORY'] == C\WORK_DIRECTORY) ||
                                $profile_model->setWorkDirectoryConfigFile(
                                    $data['WORK_DIRECTORY'])) {
                                /*
                                    Create an initial machine to be used
                                    for crawling
                                */
                                $machine_model = $parent->model('machine');
                                $machine_model->db =
                                    new D\Sqlite3Manager();
                                $machine_model->db->connect("", "", "",
                                    $data['WORK_DIRECTORY'] .
                                    "/data/public_default.db");
                                return $parent->redirectWithMessage(
                            tl('system_component_configure_work_profile_made'),
                                    ['lang'], true);
                            } else {
                                return $parent->redirectWithMessage(
                                tl('system_component_configure_no_set_config'),
                                    ['lang']);
                            }
                        } else {
                            $profile_model->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY']);
                            return $parent->redirectWithMessage(
                            tl('system_component_configure_no_create_profile'),
                                ['lang']);
                        }
                    } else {
                        $profile_model->setWorkDirectoryConfigFile(
                            $data['WORK_DIRECTORY']);
                        return $parent->redirectWithMessage(
                            tl('system_component_configure_work_dir_invalid'),
                            ['lang']);
                    }
                } else {
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_work_dir_invalid'),
                        ['lang']);
                }
                break;
            case "profile":
                $parent->updateProfileFields($data, $profile,
                    ['WEB_ACCESS', 'RSS_ACCESS', 'API_ACCESS']);
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["ERROR_INFO"])) ? C\ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["QUERY_INFO"])) ? C\QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["TEST_INFO"])) ? C\TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];
                $old_profile =
                    $profile_model->getProfile($data['WORK_DIRECTORY']);
                if ($profile_model->updateProfile(
                    $data['WORK_DIRECTORY'], $profile, $old_profile)) {
                    if (isset($_REQUEST['ROBOT_DESCRIPTION'])) {
                        $locale_tag = L\getLocaleTag();
                        $robot_description = substr(
                            $parent->clean($_REQUEST['ROBOT_DESCRIPTION'],
                            "string"), 0, C\MAX_GROUP_PAGE_LEN);
                        $group_model->setPageName(C\ROOT_ID, C\PUBLIC_GROUP_ID,
                            "bot", $robot_description, $locale_tag,
                            "", "", "", "");
                    }
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'),
                        ['lang'], true);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'),
                        ['lang']);
                }
                break;
            default:
                if (isset($data['WORK_DIRECTORY']) &&
                    file_exists($data['WORK_DIRECTORY'] ."/" .
                    C\PROFILE_FILE_NAME)){
                    $data = array_merge($data,
                        $profile_model->getProfile($data['WORK_DIRECTORY']));
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }
        if ($data['PROFILE']) {
            $locale_tag = L\getLocaleTag();
            $robot_info = $group_model->getPageInfoByName(
                 C\PUBLIC_GROUP_ID, "bot", $locale_tag, "edit");
            $data['ROBOT_DESCRIPTION'] = isset($robot_info["PAGE"]) ?
                $robot_info["PAGE"] : tl('system_component_describe_robot');
        }
        $data['SCRIPT'] .=
            "\nelt('locale').onchange = ".
            "function () { elt('configureProfileForm').submit();};\n";

        return $data;
    }
    /**
     * Checks to see if the current machine has php configured in a way
     * Yioop! can run.
     *
     * @return string a message indicatign which required and optional
     *     components are missing; or "Passed" if nothing missing.
     */
     public function systemCheck()
     {
        $parent = $this->parent;
        $required_items = [
            [   "name" => "Multi-Curl",
                "check"=>"curl_multi_init", "type"=>"function"],
            [   "name" => "GD Graphics Library",
                "check"=>"imagecreate", "type"=>"function"],
            [   "name" => "Multibyte Character Library",
                "check"=>"mb_internal_encoding", "type"=>"function"],
            [   "name" => "PDO SQLite3 Library",
                "check"=>"\PDO", "type"=>"class"],
            [   "name" =>
                    "Process Creation Functions (popen, pclose, and exec".
                    " needed for crawling)",
                "check"=>"popen", "type"=>"function"],
        ];
        $optional_items = [
         /* as an example of what this array could contain...
            ["name" => "Supercache", "check" => "\Supercache","type"=> "class"],
          */
        ];
        $missing_required = "";
        $comma = "";
        foreach ($required_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach ($check_parts as $check) {
                if ($check_function($check)) {
                    $check_flag = false;
                }
            }
            if ($check_flag) {
                $missing_required .= $comma.$item["name"];
                $comma = ",<br />";
            }
        }
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 70400) {
            $missing_required .= $comma . tl("system_component_php_version");
            $comma = ", ";
        }

        $out = "";
        $br = "";

        if (!is_writable(C\BASE_DIR."/configs/Config.php")) {
            $out .= tl('system_component_no_write_config_php');
            $br = "<br />";
        }

        if (defined(C\WORK_DIRECTORY) && !is_writable(C\WORK_DIRECTORY)) {
            $out .= $br. tl('system_component_no_write_work_dir');
            $br = "<br />";
        }

        if (intval(ini_get("post_max_size")) < 2) {
            $out .= $br. tl('system_component_post_size_small');
            $br = "<br />";
        }

        if ($missing_required != "") {
            $out .= $br.
                tl('system_component_missing_required'). "<br />".
                $missing_required;
            $br = "<br />";
        }

        $missing_optional = "";
        $comma = "";
        foreach ($optional_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach ($check_parts as $check) {
                if ($check_function($check)) {
                    $check_flag = false;
                }
            }
            if ($check_flag) {
                $missing_optional .= $comma.$item["name"];
                $comma = ", ";
            }
        }
        if ($missing_optional != "") {
            $out .= $br.
                tl('system_component_missing_optional') . "<br />".
                $missing_optional;
            $br = "<br />";
        }
        if ($out == "") {
            $out = tl('system_component_check_passed');
        } else {
            $out = "<span class='red'>$out</span>";
        }
        if (file_exists(C\BASE_DIR."/configs/LocalConfig.php")) {
            $out .= "<br />".tl('system_component_using_local_config');
        }
        return $out;
     }
}
