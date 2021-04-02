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
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\controllers\RegisterController;
use seekquarry\yioop\models\LocaleModel;
use seekquarry\yioop\views\RegisterView;

/**
 * Component of the Yioop control panel used to handle activitys for
 * managing accounts, users, roles, and groups. i.e., Settings of users
 * and groups, what roles and groups a user has, what roles and users
 * a group has, and what activities make up a role. It is used by
 * AdminController
 *
 * @author Chris Pollett
 */
class AccountaccessComponent extends Component
{
    /**
     * Used to handle the change current user password admin activity
     *
     * @return array $data SCRIPT field contains success or failure message
     */
    public function manageAccount()
    {
        $parent = $this->parent;
        $signin_model = $parent->model("signin");
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $crawl_model = $parent->model("crawl");
        $role_model = $parent->model("role");
        $profile_model = $parent->model("profile");
        $locale_model = $parent->model("locale");
        $cron_model = $parent->model("cron");
        $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
        $data["ELEMENT"] = "manageaccount";
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $changed_settings_flag = false;
        $data['yioop_bot_configuration'] = $profile['CONFIGURE_BOT'];
        if ($profile['CONFIGURE_BOT'] == 'enable_bot_users') {
            $data['yioop_bot_configuration'] = true;
        } else {
            $data['yioop_bot_configuration'] = false;
        }
        $user_id = $_SESSION['USER_ID'];
        /**
         * Preparing data for recommending users with threads and groups
         */
        $cron_timestamp =
            $cron_model->getCronTime("item_group_recommendations");
        $data['THREAD_RECOMMENDATIONS'] =
            $user_model->getRecommendations($cron_timestamp,
                $user_id, C\THREAD_RECOMMENDATION);
        $data['GROUP_RECOMMENDATIONS'] =
            $user_model->getRecommendations($cron_timestamp,
            $user_id, C\GROUP_RECOMMENDATION);
        $username = $signin_model->getUserName($user_id);
        $data["USER"] = $user_model->getUser($username);
        $data["CRAWL_MANAGER"] = false;
        if ($user_model->isAllowedUserActivity($user_id, "manageCrawls")) {
            $data["CRAWL_MANAGER"] = true;
            $machine_urls = $parent->model("machine")->getQueueServerUrls();
            list($stalled, $status, $recent_crawls) =
                $crawl_model->combinedCrawlInfo($machine_urls, true);
            $data["CRAWLS_RUNNING"] = 0;
            $data["NUM_CLOSED_CRAWLS"] = count($recent_crawls);
            foreach ($status as $channel => $channel_status) {
                if (!empty($channel_status['CRAWL_TIME'])) {
                    $data["CRAWLS_RUNNING"]++;
                    $data["NUM_CLOSED_CRAWLS"]--;
                }
            }
        }
        if (isset($_REQUEST['edit']) && $_REQUEST['edit'] == "true") {
            $data['EDIT_USER'] = true;
        }
        if (isset($_REQUEST['edit_pass'])) {
            if ($_REQUEST['edit_pass'] == "true") {
                $data['EDIT_USER'] = true;
                $data['EDIT_PASSWORD'] = true;
            } else {
                $data['EDIT_USER'] = true;
            }
        }
        if (!empty($data['EDIT_USER'])) {
            $user_session = $user_model->getUserSession($user_id);
            if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
                if (!isset($user_session['RECOVERY']) &&
                    !isset($_SESSION['RECOVERY'])) {
                    $data['RECOVERY'] = $this->makeRecoveryQuestions();
                    $_SESSION['RECOVERY'] = $data['RECOVERY'];
                } else if (isset($_SESSION['RECOVERY'])) {
                    $data['RECOVERY'] = $_SESSION['RECOVERY'];
                } else {
                    $data['RECOVERY'] = $user_session['RECOVERY'];
                }
                if (isset($user_session["RECOVERY_ANSWERS"])) {
                    $data["RECOVERY_ANSWERS"] =
                        $user_session["RECOVERY_ANSWERS"];
                }
                $num_questions = count($data['RECOVERY']);
                $recovery_answer_change = false;
                if (!isset($user_session["RECOVERY_ANSWERS"])) {
                    $data['RECOVERY_ANSWERS'] = [];
                }
                for ($i = 0; $i < $num_questions; $i++) {
                    if (isset($_REQUEST["question_$i"]) &&
                        $_REQUEST["question_$i"] != -1 &&
                        in_array($_REQUEST["question_$i"],
                        $data['RECOVERY'][$i]) ) {
                        $recovery_answer_change = true;
                        $data["RECOVERY_ANSWERS"][$i] =
                            $_REQUEST["question_$i"];
                        $user_session["RECOVERY_ANSWERS"][$i] =
                            $_REQUEST["question_$i"];
                    }
                    if (!isset($data["RECOVERY_ANSWERS"][$i])) {
                        $data["RECOVERY_ANSWERS"][$i] = -1;
                    }
                }
                if ($recovery_answer_change) {
                    $user_session['RECOVERY'] = $data['RECOVERY'];
                    $changed_settings_flag = true;
                }
            }
        }
        $data['USERNAME'] = $username;
        $data['NUM_SHOWN'] = 5;
        $data['NUM_GROUPS'] = $group_model->countUserGroups($user_id);
        $group_ids = $parent->model("impression")->recent($user_id,
            C\GROUP_IMPRESSION, $data['NUM_SHOWN']);
        $num_shown = count($group_ids);
        if ($num_shown < $data['NUM_GROUPS'] && $num_shown <
            $data['NUM_SHOWN']) {
            $groups = $group_model->getUserGroups($user_id, "", 0,
                $data['NUM_SHOWN']);
            foreach ($groups as $group) {
                if (!in_array($group['GROUP_ID'], $group_ids)) {
                    $group_ids[] = $group['GROUP_ID'];
                }
                if (count($group_ids) >= $data['NUM_SHOWN']) {
                    break;
                }
            }
        }
        $num_shown = count($group_ids);
        $data['GROUPS'] = [];
        $i = 0;
        foreach ($group_ids as $group_id) {
            $tmp_group = $group_model->getGroupById($group_id,
                $user_id);
            if (!$tmp_group) {
                continue;
            }
            $data['GROUPS'][$i] = $tmp_group;
            $item = $group_model->getMostRecentGroupPost($group_id);
            $data['GROUPS'][$i]['NUM_POSTS'] =
                $group_model->getGroupPostCount($group_id);
            $data['GROUPS'][$i]['NUM_THREADS'] =
                $group_model->getGroupThreadCount($group_id);
            $data['GROUPS'][$i]['NUM_PAGES'] =
                $group_model->getGroupPageCount($group_id);
            if (isset($item['TITLE'])) {
                $data['GROUPS'][$i]["ITEM_TITLE"] = $item['TITLE'];
                $data['GROUPS'][$i]["THREAD_ID"] = $item['PARENT_ID'];
            } else {
                $data['GROUPS'][$i]["ITEM_TITLE"] =
                    tl('accountaccess_component_no_posts_yet');
                $data['GROUPS'][$i]["THREAD_ID"] = -1;
            }
            $i++;
        }
        $languages = $locale_model->getLocaleList();
        foreach ($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if (isset($_REQUEST['lang']) &&
            in_array($_REQUEST['lang'],
            array_keys($data['LANGUAGES']))) {
            $old_value = isset($_SESSION['l']) ?
                $_SESSION['l'] : L\getLocaleTag();
            $_SESSION['l'] = $_REQUEST['lang'];
            L\setLocaleObject($_SESSION['l']);
            if ($old_value != $_SESSION['l'] || empty($user_session['l'])
                || $user_session['l'] != $_SESSION['l']) {
                $changed_settings_flag = true;
                $user_session['l'] = $_SESSION['l'];
            }
        }
        $data['LANGUAGES_TO_SHOW'] = 1;
        $data['LOCALE_TAG'] = L\getLocaleTag();
        $data['NUM_SHOWN'] = $num_shown;
        $data['NUM_MIXES'] = count($crawl_model->getMixList($user_id));
        $arg = (isset($_REQUEST['arg'])) ? $_REQUEST['arg'] : "";
        switch ($arg) {
            case "updateuser":
                if (isset($_REQUEST['new_password']) ) {
                    $pass_len = strlen($_REQUEST['new_password']);
                    if ($pass_len > C\LONG_NAME_LEN) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_too_long'),
                            ["edit", "edit_pass"]);
                    }
                }
                if (isset($data['EDIT_PASSWORD']) &&
                    (!isset($_REQUEST['retype_password']) ||
                    !isset($_REQUEST['new_password']) ||
                    $_REQUEST['retype_password'] !=
                        $_REQUEST['new_password'])){
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_passwords_dont_match'),
                        ["edit", "edit_pass"]);
                }
                $result = $signin_model->checkValidSignin($username,
                    $parent->clean($_REQUEST['password'], "string") );
                if (!$result) {
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_invalid_password'),
                        ["edit", "edit_pass"]);
                }
                if (isset($data['EDIT_PASSWORD'])) {
                    $signin_model->changePassword($username,
                        $parent->clean($_REQUEST['new_password'],
                        "string"));
                }
                $user = [];
                $user['USER_ID'] = $user_id;
                $fields = ["EMAIL" => C\LONG_NAME_LEN,
                    "FIRST_NAME" => C\NAME_LEN, "LAST_NAME" => C\NAME_LEN];
                foreach ($fields as $field => $len) {
                    if (isset($_REQUEST[$field])) {
                        $user[$field] = substr($parent->clean(
                            $_REQUEST[$field], "string"), 0, $len);
                        $data['USER'][$field] =  $user[$field];
                    }
                }
                $is_bot_updated = false;
                $bot_role_id = $role_model->getRoleId('Bot User');
                if (isset($_REQUEST['IS_BOT_USER']) && $bot_role_id) {
                    $data['USER']['IS_BOT_USER'] = 1;
                    $is_bot_updated = true;
                    $role_model->addUserRole($user_id, $bot_role_id);
                } else if ($bot_role_id) {
                    $data['USER']['IS_BOT_USER'] = 0;
                    $role_model->deleteUserRole($user_id, $bot_role_id);
                }
                if (isset($_REQUEST['BOT_TOKEN'])){
                    $user['BOT_TOKEN'] = $_REQUEST['BOT_TOKEN'];
                    $data['USER']['BOT_TOKEN'] = $user['BOT_TOKEN'];
                    $is_bot_updated = true;
                }
                if (isset($_REQUEST['BOT_CALLBACK_URL'])){
                    $user['CALLBACK_URL'] = $_REQUEST['BOT_CALLBACK_URL'];
                    $data['USER']['CALLBACK_URL'] = $user['CALLBACK_URL'];
                    $is_bot_updated = true;
                }
                if (isset($_FILES['user_icon']['name']) &&
                    $_FILES['user_icon']['name'] != "") {
                    if (!in_array($_FILES['user_icon']['type'],
                        ['image/png', 'image/gif', 'image/jpeg'])) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_unknown_imagetype'),
                            ["edit", "edit_pass"]);
                    }
                    if ($_FILES['user_icon']['size'] > C\THUMB_SIZE) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_icon_too_big'),
                             ["edit", "edit_pass"]);
                    }
                    if (empty($_FILES['user_icon']['data'])) {
                        $user['IMAGE_STRING'] = file_get_contents(
                            $_FILES['user_icon']['tmp_name']);
                    } else {
                        $user['IMAGE_STRING'] = $_FILES['user_icon']['data'];
                    }
                    $folder = $user_model->getUserIconFolder(
                        $user['USER_ID']);
                    if (!$folder) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_no_user_folder'),
                            ["edit", "edit_pass"]);
                    }
                }
                $user_model->updateUser($user);
                if ($changed_settings_flag) {
                    $user_model->setUserSession($user_id, $user_session);
                }
                if ($is_bot_updated) {
                    $user_model->updateBot($user);
                }
                $data['USER']['USER_ICON'] = $user_model->getUserIconUrl(
                    $user['USER_ID']);
                unset($user['IMAGE_STRING']);
                return $parent->redirectWithMessage(
                    tl('accountaccess_component_user_updated'),
                    ["edit", "edit_pass"]);
        }
        return $data;
    }
    /**
     * Creates an array of recovery questions and possible answers for a user
     * to select their answers to. This list is chosen from a larger list
     * of questions translated from strings appearing in @see RegisterView
     *
     * @return array questions and answers
     */
    public function makeRecoveryQuestions()
    {
        $register_view = $this->parent->view("register");
        $locale = LocaleModel::$current_locale;
        $recovery_qa = RegisterController::getRecoveryQuestions($register_view,
            $locale);
        $out_recovery_qa = RegisterController::selectQuestionsAnswers(
            $recovery_qa, RegisterController::NUM_RECOVERY_QUESTIONS);
        return $out_recovery_qa[0];
    }
    /**
     * Used to handle the manage user activity.
     *
     * This activity allows new users to be added, old users to be
     * deleted and allows roles to be added to/deleted from a user
     *
     * @return array $data infomation about users of the system, roles, etc.
     *     as well as status messages on performing a given sub activity
     */
    public function manageUsers()
    {
        $parent = $this->parent;
        $request_fields = ['start_row', 'num_show', 'end_row',
            'visible_roles', 'visible_groups', 'role_filter', 'role_sorts',
            'group_filter', 'group_sorts', 'role_limit', 'group_limit',
            'context'];
        $signin_model = $parent->model("signin");
        $user_model = $parent->model("user");
        $group_model = $parent->model("group");
        $role_model = $parent->model("role");
        $possible_arguments = ["adduser", 'edituser', 'search',
            "deleteuser", "adduserrole", "deleteuserrole",
            "addusergroup", "deleteusergroup", "updatestatus"];
        $data["ELEMENT"] = "manageusers";
        $data['SCRIPT'] = "";
        $data['STATUS_CODES'] = [
            C\ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            C\INACTIVE_STATUS => tl('accountaccess_component_inactive_status'),
            C\SUSPENDED_STATUS =>
                tl('accountaccess_component_suspended_status'),
        ];
        $data['MEMBERSHIP_CODES'] = [
            C\INACTIVE_STATUS => tl('accountaccess_component_request_join'),
            C\INVITED_STATUS => tl('accountaccess_component_invited'),
            C\ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            C\SUSPENDED_STATUS =>
                tl('accountaccess_component_suspended_status')
        ];
        $data['FORM_TYPE'] = "adduser";
        $search_array = [];
        $username = "";
        if (isset($_REQUEST['user_name'])) {
            $username = substr($parent->clean($_REQUEST['user_name'], "string"),
                0, C\NAME_LEN);
        }
        if ($username == "" && isset($_REQUEST['arg']) && $_REQUEST['arg']
            != "search") {
            unset($_REQUEST['arg']);
        }
        $select_group = isset($_REQUEST['selectgroup']) ?
            $parent->clean($_REQUEST['selectgroup'],"string") : "";
        $select_role = isset($_REQUEST['selectrole']) ?
            $parent->clean($_REQUEST['selectrole'],"string") : "";
        if (isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'edituser') {
            if ($select_role != "") {
                $_REQUEST['arg'] = "adduserrole";
            } else if ($select_group != ""){
                $_REQUEST['arg'] = "addusergroup";
            }
        }
        $user_id = -1;
        $data['visible_roles'] = 'false';
        $data['visible_groups'] = 'false';
        if ($username != "") {
            $user_id = $signin_model->getUserId($username);
            if ($user_id) {
                $this->getUserRolesData($data, $user_id);
                $this->getUserGroupsData($data, $user_id);
            }
        }
        $data['CURRENT_USER'] = ["user_name" => "", "first_name" => "",
            "last_name" => "", "email" => "", "status" => "", "password" => "",
            "repassword" => ""];
        $data['PAGING'] = "";
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $arg = $_REQUEST['arg'];
            $pass_len = (isset($_REQUEST['new_password'])) ?
                strlen($_REQUEST['new_password']) : 0;
            switch ($arg) {
                case "adduser":
                    if ($pass_len > C\LONG_NAME_LEN ) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_too_long'),
                            $request_fields);
                    } else if ($_REQUEST['retypepassword'] !=
                        $_REQUEST['password']) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_dont_match'),
                            $request_fields);
                    } else if (trim($username) == "") {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_invalid_username'),
                            $request_fields);
                    } else if ($signin_model->getUserId($username) > 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_exists'),
                            $request_fields);
                    } else if (!isset($data['STATUS_CODES'][
                        $_REQUEST['status']])) {
                        $_REQUEST['status'] = C\INACTIVE_STATUS;
                    } else {
                        $norm_password = "";
                        $norm_password =
                            substr($parent->clean($_REQUEST['password'],
                            "string"), 0, C\LONG_NAME_LEN);
                        $username = trim($username);
                        $user_model->addUser($username, $norm_password,
                            substr(trim($parent->clean($_REQUEST['first_name'],
                                "string")), 0, C\NAME_LEN),
                            substr(trim($parent->clean($_REQUEST['last_name'],
                                "string")), 0, C\NAME_LEN),
                            substr(trim($parent->clean($_REQUEST['email'],
                                "string")), 0, C\LONG_NAME_LEN),
                            $_REQUEST['status']
                        );
                        $data['USER_NAMES'][$username] = $username;
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_added'),
                            $request_fields);
                    }
                    break;
                case "edituser":
                    $data['FORM_TYPE'] = "edituser";
                    if (!empty($_REQUEST['context'])) {
                        $data['context'] = "search";
                    }
                    $user = $user_model->getUser($username);
                    if (!$user) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_doesnt_exist'),
                            $request_fields);
                    }
                    $update = false;
                    $error = false;
                    if ($user["USER_ID"] == C\PUBLIC_USER_ID) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_cant_edit_public_user'),
                            $request_fields);
                    }
                    foreach ($data['CURRENT_USER'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'user_name') {
                            if ($field != "password" || ($_REQUEST["password"]
                                != md5("password") && $_REQUEST["password"] ==
                                $_REQUEST["retypepassword"])) {
                                $tmp = $parent->clean(
                                    $_REQUEST[$field], "string");
                                if ($tmp != $user[$upper_field]) {
                                    $user[$upper_field] = $tmp;
                                    if (!isset($_REQUEST['change_filter'])) {
                                        $update = true;
                                    }
                                }
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            } else if ($_REQUEST["password"] !=
                                $_REQUEST["retypepassword"]) {
                                $error = true;
                                break;
                            }
                        } else if (isset($user[$upper_field])){
                            if ($field != "password" &&
                                $field != "retypepassword") {
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            }
                        }
                    }
                    $data['CURRENT_USER']['password'] = md5("password");
                    $data['CURRENT_USER']['retypepassword'] = md5("password");
                    if ($error) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_dont_match'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if ($update) {
                        $user_model->updateUser($user);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_updated'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if (isset($_REQUEST['change_filter'])) {
                        if ($_REQUEST['change_filter'] == "group") {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_group').
                                "</h1>');";
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_role').
                                "</h1>');";
                        }
                    }
                    $data['CURRENT_USER']['id'] = $user_id;
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    break;
                case "deleteuser":
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $request_fields[] = 'arg';
                    }
                    $user_id =
                        $signin_model->getUserId($username);
                    if ($user_id <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_username_doesnt_exists'
                            ), $request_fields);
                    } else if (in_array(
                        $user_id, [C\ROOT_ID, C\PUBLIC_USER_ID])) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_cant_delete_builtin'),
                            $request_fields);
                    } else {
                        $user_model->deleteUser($username);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_deleted'),
                            $request_fields);
                    }
                    break;
                case "adduserrole":
                    $_REQUEST['arg'] = 'edituser';
                    if ($user_id <= 0 ) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            array_merge(['arg'], $request_fields));
                    } else  if (!($role_id = $role_model->getRoleId(
                        $select_role))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else if ($role_model->checkUserRole($user_id,
                        $role_id)) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_already_added'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else {
                        $role_model->addUserRole($user_id, $role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_added'),
                            array_merge(['arg', 'user_name'],
                            $request_fields));
                    }
                    break;
                case "addusergroup":
                    $_REQUEST['arg'] = 'edituser';
                    if ( $user_id <= 0 ) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            array_merge(['arg'], $request_fields));
                    } else if (!($group_id = $group_model->getGroupId(
                        $select_group))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_doesnt_exists'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else if ($group_model->checkUserGroup($user_id,
                        $group_id)){
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_already_added'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else {
                        $group_model->addUserGroup($user_id,
                            $group_id);
                        $this->getUserGroupsData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_added'),
                            array_merge(['arg', 'user_name'],
                            $request_fields));
                    }
                    break;
                case "deleteuserrole":
                    $_REQUEST['arg'] = 'edituser';
                    if ($user_id <= 0) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            array_merge(['arg'],
                            $request_fields));
                    }
                    $deleted = 0;
                    if (!empty($_REQUEST['role_ids'])) {
                        $role_ids = $_REQUEST['role_ids'];
                        $ids = explode("*", $role_ids);
                        foreach ($ids as $role_id) {
                            $role_id = (!empty($role_id)) ?
                                $parent->clean($role_id, 'int'): 0;
                            if ($role_model->checkUserRole($user_id,
                                $role_id)) {
                                $role_model->deleteUserRole(
                                    $user_id, $role_id);
                                $deleted++;
                            }
                        }
                    }
                    if ($deleted == 1) {
                        $this->getUserRolesData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_deleted'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else if ($deleted > 1) {
                        $this->getUserRolesData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolenames_deleted'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    }
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_rolename_doesnt_exists'
                        ), array_merge(['arg', 'user_name'],
                        $request_fields));
                case "deleteusergroup":
                    $_REQUEST['arg'] = 'edituser';
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['c'] = 'search';
                        $request_fields[] = 'arg';
                    }
                    if ($user_id <= 0) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            $request_fields);
                    }
                    $deleted = 0;
                    if (!empty($_REQUEST['group_ids'])) {
                        $group_ids = $_REQUEST['group_ids'];
                        $ids = explode("*", $group_ids);
                        foreach ($ids as $group_id) {
                            $group_id = (!empty($group_id)) ?
                                $parent->clean($group_id, 'int'): 0;
                            if ($group_model->checkUserGroup($user_id,
                                $group_id)) {
                                $group_model->deleteUserGroup(
                                    $user_id, $group_id);
                                $deleted++;
                            }
                        }
                    }
                    if ($deleted == 1) {
                        $this->getUserGroupsData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_group_deleted'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    } else if ($deleted > 1) {
                        $this->getUserGroupsData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groups_deleted'
                            ), array_merge(['arg', 'user_name'],
                            $request_fields));
                    }
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_groupname_doesnt_exists'
                        ), array_merge(['arg', 'user_name'],
                        $request_fields));
                case "search":
                    $data["FORM_TYPE"] = "search";
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        "manageUsers",
                        ['ALL_FIELDS' =>
                            ['user', 'first', 'last', 'email', 'status'],
                         'EQUAL_COMPARISON_TYPES' => ['status']], "_name");
                    if (empty($_SESSION['LAST_SEARCH']['manageUsers_name']) ||
                        (!empty($_SESSION['LAST_SEARCH']['manageUsers_name']) &&
                        isset($_REQUEST['user_name'])) ) {
                        $_SESSION['LAST_SEARCH']['manageUsers_name'] =
                            $_SESSION['SEARCH']['manageUsers_name'];
                        unset($_SESSION['SEARCH']['manageUsers_name']);
                    } else {
                        $default_search = true;
                    }
                    break;
                case "updatestatus":
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $request_fields[] = 'arg';
                    }
                    $user_id = $signin_model->getUserId($username);
                    if (!isset($data['STATUS_CODES'][$_REQUEST['userstatus']])||
                        $user_id == 1) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_username_doesnt_exists'
                            ), $request_fields);
                    } else {
                        $user_model->updateUserStatus($user_id,
                            $_REQUEST['userstatus']);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_userstatus_updated'),
                            $request_fields);
                    }
                    break;
            }
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['manageUsers_name'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'manageUsers_name');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array = $_SESSION['LAST_SEARCH'][
                        'manageUsers_name']['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['manageUsers_name']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["user", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $user_model, "USERS",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        $num_users = count($data['USERS']);
        for ($i = 0; $i < $num_users; $i++) {
            $data['USERS'][$i]['NUM_GROUPS'] =
                $group_model->countUserGroups($data['USERS'][$i]['USER_ID']);
        }
        if ($data['FORM_TYPE'] == 'adduser') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the roles that a user
     * has subject to $_REQUEST['role_limit'], $_REQUEST['role_filter'],
     * and $_REQUEST['role_sorts']. Information about these roles is added as
     * fields to $data[NUM_USER_ROLES'] and $data['USER_ROLES']
     *
     * @param array &$data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    public function getUserRolesData(&$data, $user_id)
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $data['visible_roles'] = (isset($_REQUEST['visible_roles']) &&
            $_REQUEST['visible_roles']=='true') ? 'true' : 'false';
        if ($data['visible_roles'] == 'false') {
            unset($_REQUEST['role_filter']);
            unset($_REQUEST['role_limit']);
        }
        if (isset($_REQUEST['role_filter'])) {
            $role_filter = substr($parent->clean(
                $_REQUEST['role_filter'], 'string'), 0, C\NAME_LEN);
        } else {
            $role_filter = "";
        }
        $data['ROLE_FILTER'] = $role_filter;
        $data['ROLE_SORTS'] = (empty($_REQUEST['role_sorts'])) ? [] :
            json_decode(urldecode($_REQUEST['role_sorts']), true);
        if ($data['ROLE_SORTS'] === null) {
            $data['ROLE_SORTS'] = json_decode(html_entity_decode(
                urldecode($_REQUEST['role_sorts'])), true);
            $data['ROLE_SORTS'] = ($data['ROLE_SORTS']) ? $data['ROLE_SORTS'] :
                [];
        }
        $data['NUM_USER_ROLES'] =
            $role_model->countUserRoles($user_id, $role_filter);
        if (isset($_REQUEST['role_limit'])) {
            $role_limit = min($parent->clean(
                $_REQUEST['role_limit'], 'int'),
                $data['NUM_USER_ROLES']);
            if ($role_limit == $data['NUM_USER_ROLES']) {
                $role_limit = $data['NUM_USER_ROLES']- C\NUM_RESULTS_PER_PAGE;
            }
            $role_limit = max($role_limit, 0);
        } else {
            $role_limit = 0;
        }
        $data['ROLE_LIMIT'] = $role_limit;
        $data['USER_ROLES'] =
            $role_model->getUserRoles($user_id, $role_filter,
            $data['ROLE_SORTS'], $role_limit);
        $data['SCRIPT'] .= "listenAll('input.role-id', 'click',".
            " function(event){ updateCheckedIds(role_ids, event.target); });";
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the groups that a user
     * belongs to subject to $_REQUEST['group_limit'],
     * $_REQUEST['group_filter'], and $_REQUEST['group_sorts']. Information
     * about these roles is added as
     * fields to $data[NUM_USER_GROUPS'] and $data['USER_GROUPS']
     *
     * @param array &$data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    public function getUserGroupsData(&$data, $user_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_groups'] = (isset($_REQUEST['visible_groups']) &&
            $_REQUEST['visible_groups']=='true') ? 'true' : 'false';
        if ($data['visible_groups'] == 'false') {
            unset($_REQUEST['group_filter']);
            unset($_REQUEST['group_limit']);
        }
        if (isset($_REQUEST['group_filter'])) {
            $group_filter = substr($parent->clean(
                $_REQUEST['group_filter'], 'string'), 0, C\SHORT_TITLE_LEN);
        } else {
            $group_filter = "";
        }
        $data['GROUP_FILTER'] = $group_filter;
        $data['GROUP_SORTS'] = (empty($_REQUEST['group_sorts'])) ? [] :
            json_decode(urldecode($_REQUEST['group_sorts']), true);
        if ($data['GROUP_SORTS'] === null) {
            $data['GROUP_SORTS'] = json_decode(html_entity_decode(
                urldecode($_REQUEST['group_sorts'])), true);
            $data['GROUP_SORTS'] = ($data['GROUP_SORTS']) ?
                $data['GROUP_SORTS'] : [];
        }
        $data['NUM_USER_GROUPS'] =
            $group_model->countUserGroups($user_id, $group_filter);
        if (isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USER_GROUPS']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['USER_GROUPS'] =
            $group_model->getUserGroups($user_id, $group_filter,
            $data['GROUP_SORTS'], $group_limit);
        $data['SCRIPT'] .= "listenAll('input.group-id', 'click',".
            " function(event){ updateCheckedIds(group_ids, event.target); });";
    }
    /**
     * Used to handle the manage role activity.
     *
     * This activity allows new roles to be added, old roles to be
     * deleted and allows activities to be added to/deleted from a role
     *
     * @return array $data information about roles in the system, activities,
     *     etc. as well as status messages on performing a given sub activity
     */
    public function manageRoles()
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $possible_arguments = ["addactivity", "addrole",
                "deleteactivity","deleterole", "editrole", "search"];
        $data["ELEMENT"] = "manageroles";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addrole";
        $search_array = [];
        $data['CURRENT_ROLE'] = ["name" => ""];
        $data['PAGING'] = "";
        if (isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'editrole') {
            if (isset($_REQUEST['selectactivity']) &&
                $_REQUEST['selectactivity'] >= 0) {
                $_REQUEST['arg'] = "addactivity";
            }
        }
        if (isset($_REQUEST['name'])) {
            $name = substr($parent->clean($_REQUEST['name'], "string"),
                0, C\NAME_LEN);
             $data['CURRENT_ROLE']['name'] = $name;
        } else {
            $name = "";
        }
        if ($name != "") {
            $role_id = $role_model->getRoleId($name);
            $data['ROLE_ACTIVITIES'] =
                $role_model->getRoleActivities($role_id);
            $all_activities = $parent->model("activity")->getActivityList();
            $activity_ids = [];
            $activity_names = [];
            foreach ($all_activities as $activity) {
                $activity_ids[] = $activity['ACTIVITY_ID'];
                $activity_names[$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }
            $available_activities = [];
            $role_activity_ids = [];
            foreach ($data['ROLE_ACTIVITIES'] as $activity) {
                $role_activity_ids[] = $activity["ACTIVITY_ID"];
            }
            $tmp = [];
            foreach ($all_activities as $activity) {
                if (!in_array($activity["ACTIVITY_ID"], $role_activity_ids) &&
                    !isset($tmp[$activity["ACTIVITY_ID"]])) {
                    $tmp[$activity["ACTIVITY_ID"]] = true;
                    $available_activities[] = $activity;
                }
            }
            $data['AVAILABLE_ACTIVITIES'][-1] =
                tl('accountaccess_component_add_roleactivity');
            foreach ($available_activities as $activity) {
                $data['AVAILABLE_ACTIVITIES'][$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }
            if (isset($_REQUEST['selectactivity'])) {
                $select_activity =
                    $parent->clean($_REQUEST['selectactivity'], "int" );
            } else {
                $select_activity = "";
            }
            if ($select_activity != "") {
                $data['SELECT_ACTIVITY'] = $select_activity;
            } else {
                $data['SELECT_ACTIVITY'] = -1;
            }
        }
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "addactivity":
                    $_REQUEST['arg'] = "editrole";
                    if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name", "context"]);
                    } else if (!in_array($select_activity, $activity_ids)) {
                        return $parent->redirectWithMessage(
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name", "context"]);
                    } else {
                        $role_model->addActivityRole(
                            $role_id, $select_activity);
                        unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_activity_added'),
                            ["arg", "start_row", "end_row", "num_show", "name",
                            "context"]);
                    }
                    break;
                case "addrole":
                    $name = trim($name);
                    if ($name != "" && $role_model->getRoleId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>');";
                    } else if ($name != "") {
                        $role_model->addRole($name);
                        $data['CURRENT_ROLE']['name'] = "";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>');";
                   } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_blank').
                            "</h1>');";
                   }
                   $data['CURRENT_ROLE']['name'] = "";
                   break;
                case "deleteactivity":
                   $_REQUEST['arg'] = "editrole";
                   if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name", "context"]);
                    } else if (!in_array($select_activity, $activity_ids)) {
                        return $parent->redirectWithMessage(
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name", "context"]);
                    } else {
                        $role_model->deleteActivityRole(
                            $role_id, $select_activity);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        $data['AVAILABLE_ACTIVITIES'][$select_activity] =
                            $activity_names[$select_activity];
                        $data['SELECT_ACTIVITY'] = -1;
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_activity_deleted'),
                            ["arg", "start_row", "end_row", "num_show",
                            "name", "context"]);
                    }
                    break;
                case "deleterole":
                    $preserve = [];
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $preserve[] = 'arg';
                    }
                    if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ),array_merge($preserve, ["start_row", "end_row",
                            "num_show"]));
                    } else {
                        $role_model->deleteRole($role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_deleted'),
                            array_merge($preserve, ["start_row", "end_row",
                            "num_show"]));
                    }
                    break;
                case "editrole":
                    $data['FORM_TYPE'] = "editrole";
                    $role = false;
                    if ($name) {
                        $role = $role_model->getRole($name);
                    }
                    if ($role === false) {
                        $data['FORM_TYPE'] = "addrole";
                        break;
                    }
                    if (!empty($_REQUEST['context'])) {
                        $data['context'] = 'search';
                    }
                    $num_activities = count($data['ROLE_ACTIVITIES']);
                    $update_activities = $_REQUEST['activities'] ?? [];
                    if (!$update_activities) {
                        break;
                    }
                    $update = false;
                    for ($i  = 0; $i < $num_activities; $i++) {
                        $role_activity = $data['ROLE_ACTIVITIES'][$i];
                        $activity_id = $role_activity['ACTIVITY_ID'];
                        if (isset($update_activities[$activity_id]) &&
                            $update_activities[$activity_id] !=
                            $role_activity['ALLOWED_ARGUMENTS']) {
                            $role_model->updateActivityRoleArguments($role_id,
                                $activity_id, $update_activities[$activity_id]);
                            $update = true;
                        }
                    }
                    if ($update) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_role_updated'),
                            ["arg", "name", "start_row", "end_row", "num_show",
                            "context"]);
                    }
                    break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        "manageRoles", ['ALL_FIELDS' => ['name']]);
                    if (empty($_SESSION['LAST_SEARCH']['manageRoles']) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['manageRoles'] =
                            $_SESSION['SEARCH']['manageRoles'];
                        unset($_SESSION['SEARCH']['manageRoles']);
                    } else {
                        $default_search = true;
                    }
                    break;
            }
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['manageRoles'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'manageRoles');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH']['manageRoles']['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['manageRoles']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["name", "", "", "ASC"];
            }
        }
        if ($data['FORM_TYPE'] == 'addrole') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        $parent->pagingLogic($data, $role_model, "ROLES",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        return $data;
    }
}
