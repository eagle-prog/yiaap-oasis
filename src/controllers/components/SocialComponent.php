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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\MailServer;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\WikiParser;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\PhraseParser;
/**
 * Provides activities to AdminController related to creating, updating
 * blogs (and blog entries), static web pages, and crawl mixes.
 *
 * @author Chris Pollett
 */
class SocialComponent extends Component implements CrawlConstants
{
    /**
     *  Constant for when attempt to handle file uploads and no files were
     *  uploaded
     */
    const UPLOAD_NO_FILES = -1;
    /**
     *  Constant for when attempt to handle file uploads and not all of the
     *  file upload information was present
     */
    const UPLOAD_FAILED = 0;
    /**
     *  Constant for when attempt to handle file uploads and file were
     *  successfully uploaded
     */
    const UPLOAD_SUCCESS = 1;
    /**
     * Used to handle the manage group activity.
     *
     * This activity allows new groups to be created out of a set of users.
     * It allows admin rights for the group to be transferred and it allows
     * roles to be added to a group. One can also delete groups and roles from
     * groups.
     *
     * @return array $data information about groups in the system
     */
    public function manageGroups()
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $possible_arguments = ["activateuser",
            "addgroup", "banuser", "changeowner", "import",
            "creategroup", "deletegroup", "deleteselected", "deleteuser",
            "editgroup", "graphstats", "inviteusers", "joingroup",
            "memberaccess", "postlifetime", "registertype", "reinstateuser",
            "search", "statistics", "unsubscribe", "voteaccess"];
        $data["ELEMENT"] = "managegroups";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addgroup";
        $data['MEMBERSHIP_CODES'] = [
            C\INACTIVE_STATUS => tl('social_component_request_join'),
            C\INVITED_STATUS => tl('social_component_invited'),
            C\ACTIVE_STATUS => tl('social_component_active_status'),
            C\SUSPENDED_STATUS => tl('social_component_suspended_status')
        ];
        $data['REGISTER_CODES'] = [
            C\NO_JOIN => tl('social_component_no_join'),
            C\REQUEST_JOIN => tl('social_component_by_request'),
            C\PUBLIC_BROWSE_REQUEST_JOIN =>
                tl('social_component_public_request'),
            C\PUBLIC_JOIN => tl('social_component_public_join'),
        ];
        if (in_array(C\MONETIZATION_TYPE,
            ['group_fees', 'fees_and_keywords'])) {
            $data['can_monetise_group'] = true;
            $monetise_codes = [100 => tl('social_component_hundred_credits'),
                200 => tl('social_component_two_hundred_credits'),
                500 => tl('social_component_five_hundred_credits'),
                1000 => tl('social_component_thousand_credits'),
                2000 => tl('social_component_two_thousand_credits')];
            $data['REGISTER_CODES'] += $monetise_codes;
        } else {
            $data['can_monetise_group'] = false;
        }
        $data['ACCESS_CODES'] = [
            C\GROUP_PRIVATE => tl('social_component_private'),
            C\GROUP_READ => tl('social_component_read'),
            C\GROUP_READ_COMMENT => tl('social_component_read_comment'),
            C\GROUP_READ_WRITE => tl('social_component_read_write'),
            C\GROUP_READ_WIKI => tl('social_component_read_wiki'),
        ];
        $data['VOTING_CODES'] = [
            C\NON_VOTING_GROUP => tl('social_component_no_voting'),
            C\UP_VOTING_GROUP => tl('social_component_up_voting'),
            C\UP_DOWN_VOTING_GROUP => tl('social_component_up_down_voting')
        ];
        $data['POST_LIFETIMES'] = [
            C\FOREVER => tl('social_component_forever'),
            C\ONE_HOUR => tl('social_component_one_hour'),
            C\ONE_DAY => tl('social_component_one_day'),
            C\ONE_MONTH => tl('social_component_one_month'),
        ];
        $data['ENCRYPTION_CODES'] = [
            1 => tl('social_component_encryption_enable'),
            0 => tl('social_component_encryption_disable'),
        ];
        if (in_array(C\MONETIZATION_TYPE, ['group_fees','fees_and_keywords'])) {
            $data['can_monetise_group'] = true;
        } else {
            $data['can_monetise_group'] = false;
        }
        $search_array = [];
        $default_group = ["name" => "","id" => "", "owner" =>"",
            "register" => -1, "member_access" => -1, 'vote_access' => -1,
            "post_lifetime" => -1, "encryption" => 0];
        $data['CURRENT_GROUP'] = $default_group;
        $data['PAGING'] = "";
        $name = "";
        $data['visible_users'] = "";
        $is_owner = false;
        if (!isset($_REQUEST['arg'])) {
            $_REQUEST['arg'] = "";
        }
        /* start owner verify code / get current group
           $group_id is only set in this block (except creategroup) and it
           is only not null if $group['OWNER_ID'] == $_SESSION['USER_ID'] where
           this is also the only place group loaded using $group_id
        */
        if (!empty($_REQUEST['group_id'])) {
            $group_id = $parent->clean($_REQUEST['group_id'], "int" );
            $group = $group_model->getGroupById($group_id,
                $_SESSION['USER_ID']);
            if (isset($group['OWNER_ID'] ) &&
                ($group['OWNER_ID'] == $_SESSION['USER_ID'] ||
                ($_SESSION['USER_ID'] == C\ROOT_ID && in_array($_REQUEST['arg'],
                ["changeowner", 'statistics', 'graphstats'])))) {
                $name = $group['GROUP_NAME'];
                $data['CURRENT_GROUP']['name'] = $name;
                $data['CURRENT_GROUP']['id'] = $group['GROUP_ID'];
                $data['CURRENT_GROUP']['owner'] = $group['OWNER'];
                $data['CURRENT_GROUP']['register'] =
                    $group['REGISTER_TYPE'];
                $data['CURRENT_GROUP']['member_access'] =
                    $group['MEMBER_ACCESS'];
                $data['CURRENT_GROUP']['vote_access'] =
                    $group['VOTE_ACCESS'];
                $data['CURRENT_GROUP']['post_lifetime'] =
                    $group['POST_LIFETIME'];
                $data['CURRENT_GROUP']['encryption'] =
                    $group['ENCRYPTION'];
                $is_owner = true;
            } else if (!in_array($_REQUEST['arg'],
                ["deletegroup", "joingroup", "unsubscribe"]) &&
                $_SESSION['USER_ID'] != C\ROOT_ID) {
                $group_id = null;
                $group = null;
            }
        } else if (isset($_REQUEST['name'])) {
            $name = substr(trim($parent->clean($_REQUEST['name'], "string")), 0,
                C\SHORT_TITLE_LEN);
            $data['CURRENT_GROUP']['name'] = $name;
            $group_id = null;
            $group = null;
        } else {
            $group_id = null;
            $group = null;
        }
        /* end ownership verify */
        $browse = false;
        $search_name = "manageGroups";
        if (isset($_REQUEST['browse']) && $_REQUEST['browse'] == 'true') {
            $browse = true;
            $data['browse'] = 'true';
            $search_name = "browseGroups";
        }
        $data['USER_FILTER'] = "";
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "activateuser":
                    $_REQUEST['arg'] = "editgroup";
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context'] == 'search') {
                        $data['context'] = 'search';
                    }
                    $num_activated = 0;
                    if ($is_owner && !empty($_REQUEST['user_ids'])) {
                        $user_ids = $_REQUEST['user_ids'];
                        $ids = explode("*", $user_ids);
                        foreach ($ids as $user_id) {
                            $user_id = (!empty($user_id)) ?
                                $parent->clean($user_id, 'int'): 0;
                            if ($group_model->checkUserGroup($user_id,
                                $group_id)) {
                                $group_model->updateStatusUserGroup($user_id,
                                    $group_id, C\ACTIVE_STATUS);
                                $num_activated++;
                            }
                        }
                    }
                    $this->getGroupUsersData($data, $group_id);
                    if ($num_activated == 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_user_activated'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row', 'user_filter',
                            'user_sorts', "visible_users"]);
                    } else if ($num_activated > 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_users_activated'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row', 'user_filter',
                            'user_sorts', "visible_users"]);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_user_activated'),
                        ["arg", 'context', 'end_row', 'group_limit',
                        'num_show', 'start_row', 'user_filter',
                        'user_sorts', "visible_users"]);
                case "addgroup":

                    if (($add_id = $group_model->getGroupId($name)) > 0) {
                        $register =
                            $group_model->getRegisterType($add_id);
                        if ($register >= C\LOW_JOIN_FEE &&
                            !in_array(C\MONETIZATION_TYPE,
                            ['group_fees','fees_and_keywords'])) {
                            $register = C\NO_JOIN;
                        }
                        if (($add_id > 0 && !empty($register) && $register !=
                            C\NO_JOIN) || $_SESSION['USER_ID'] == C\ROOT_ID) {
                            $search =
                                (($_REQUEST['context'] ?? "") == 'search');
                            if ($register >= C\LOW_JOIN_FEE &&
                                (empty($_REQUEST['browse']) ||
                                $_REQUEST['browse'] != 'true')) {
                                $url = htmlentities(B\controllerUrl(
                                    "admin", true)). C\CSRF_TOKEN . "=" .
                                    $parent->generateCSRFToken(
                                        $_SESSION["USER_ID"]) .
                                    "&a=manageGroups" .
                                    "&browse=true&name=" .
                                    $name;
                                if ($search) {
                                    $url .= '&arg=search';
                                }
                                return $parent->redirectLocation($url);
                            }
                            $this->addGroup($data, $add_id, $register);
                            if (isset($_REQUEST['browse']) &&
                                $_REQUEST['browse'] == 'true' && $search) {
                                $_REQUEST['arg'] = 'search';
                            } else {
                                $_REQUEST['arg'] = 'none';
                            }
                            return $parent->redirectWithMessage(
                                tl('social_component_joined'),
                                ['browse', 'arg', 'start_row',
                                'end_row', 'num_show']);
                        } else {
                            return $parent->redirectWithMessage(
                                tl('social_component_groupname_unavailable'));
                        }
                    } else if (!empty($name)) {
                        $_REQUEST['arg'] = "creategroup";
                        $_REQUEST['add_refer'] = 1;
                        return $parent->redirectWithMessage(
                            tl('social_component_name_available'),
                            ['add_refer', 'arg', 'context', 'end_row',
                            'group_limit', 'name', 'num_show', 'start_row',
                            'user_filter', 'user_sorts',"visible_users"]);
                    }
                    break;
                case "banuser":
                    $_REQUEST['arg'] = "editgroup";
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    };
                    $banned = 0;
                    if ($is_owner && !empty($_REQUEST['user_ids'])) {
                        $user_ids = $_REQUEST['user_ids'];
                        $ids = explode("*", $user_ids);
                        foreach ($ids as $user_id) {
                            $user_id = (!empty($user_id)) ?
                                $parent->clean($user_id, 'int'): 0;
                            if ($group_model->checkUserGroup($user_id,
                                $group_id)) {
                                $group_model->updateStatusUserGroup($user_id,
                                    $group_id, C\SUSPENDED_STATUS);
                                $banned++;
                            }
                        }
                    }
                    $this->getGroupUsersData($data, $group_id);
                    if ($banned == 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_user_banned'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row','user_filter',
                            'user_sorts',"visible_users"]);
                    } else  if ($banned > 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_users_banned'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row', 'user_filter',
                            'user_sorts',"visible_users"]);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_user_banned'),
                        ["arg", 'context', 'end_row', 'group_limit',
                        'num_show', 'start_row', 'user_filter',
                        'user_sorts',"visible_users"]);
                case "changeowner":
                    $data['FORM_TYPE'] = "changeowner";
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    if (isset($_REQUEST['new_owner']) && $is_owner) {
                        $new_owner_name = substr(
                            $parent->clean($_REQUEST['new_owner'],
                            'string'), 0, C\NAME_LEN);
                        $new_owner = $parent->model("user")->getUser(
                            $new_owner_name);
                        if (!empty($_REQUEST['context'])) {
                            $_REQUEST['arg'] = 'search';
                        }
                        if (isset($new_owner['USER_ID']) ) {
                            if ($group_model->checkUserGroup(
                                $new_owner['USER_ID'], $group_id)) {
                                $group_model->changeOwnerGroup(
                                    $new_owner['USER_ID'], $group_id);
                                if (empty($_REQUEST['context'])) {
                                    $_REQUEST['arg'] = "none";
                                }
                                unset($_REQUEST['group_id']);
                                return $parent->redirectWithMessage(
                                    tl('social_component_owner_changed'),
                                    ['arg', 'start_row', 'end_row',
                                    'num_show', 'browse']);
                            } else {
                                return $parent->redirectWithMessage(
                                    tl('social_component_not_in_group'),
                                    ['arg', 'start_row', 'end_row',
                                    'num_show', 'browse']);
                            }
                        } else {
                            return $parent->redirectWithMessage(
                                tl('social_component_not_a_user'),
                                ["arg", 'start_row', 'end_row',
                                'num_show']);
                        }
                    }
                    break;
                case "creategroup":
                    if ($_SESSION['USER_ID'] == C\PUBLIC_USER_ID) {
                        return $parent->redirectWithMessage(
                            tl('social_component_public_cant_create'));
                    } else if ($group_model->getGroupId($name) > 0) {
                        return $parent->redirectWithMessage(
                            tl('social_component_groupname_exists'));
                    } else if (!empty($name)) {
                        $group_fields = [
                            "member_access" => ["ACCESS_CODES", C\GROUP_READ],
                            "register" => ["REGISTER_CODES", C\REQUEST_JOIN],
                            "vote_access" => ["VOTING_CODES",
                                C\NON_VOTING_GROUP],
                            "post_lifetime" => ["POST_LIFETIMES", C\FOREVER],
                            "encryption" => ["ENCRYPTION_CODES", 0]
                        ];
                        foreach ($group_fields as $field => $info) {
                            if (!isset($_REQUEST[$field]) ||
                                !in_array($_REQUEST[$field],
                                array_keys($data[$info[0]]))) {
                                $_REQUEST[$field] = $info[1];
                            }
                        }
                        $group_model->addGroup($name,
                            $_SESSION['USER_ID'], $_REQUEST['register'],
                            $_REQUEST['member_access'],
                            $_REQUEST['vote_access'],
                            $_REQUEST['post_lifetime'],
                            $_REQUEST['encryption']);
                        //one exception to setting $group_id
                        $group_id = $group_model->getGroupId($name);
                        return $parent->redirectWithMessage(
                            tl('social_component_groupname_created'),
                            ["arg", 'start_row', 'end_row', 'num_show']);
                    }
                    break;
                case "deletegroup":
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    $data['CURRENT_GROUP'] = $default_group;
                    if ( $group_id <= 0) {
                        return $parent->redirectWithMessage(
                          tl('social_component_groupname_doesnt_exists'),
                          ["arg"]);
                    } else if (($group &&
                        $group['OWNER_ID'] == $_SESSION['USER_ID']) ||
                        $_SESSION['USER_ID'] == C\ROOT_ID) {
                        $group_model->deleteGroup($group_id);
                        return $parent->redirectWithMessage(
                            tl('social_component_group_deleted'), ["arg"]);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_delete_group'),
                        ["arg", 'start_row', 'end_row', 'num_show']);
                case "deleteuser":
                    $_REQUEST['arg'] = "editgroup";
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    }
                    $deleted = 0;
                    if ($is_owner && !empty($_REQUEST['user_ids'])) {
                        $user_ids = $_REQUEST['user_ids'];
                        $ids = explode("*", $user_ids);
                        foreach ($ids as $user_id) {
                            $user_id = (!empty($user_id)) ?
                                $parent->clean($user_id, 'int'): 0;
                            if ($group_model->deletableUser($user_id,
                                $group_id)) {
                                $group_model->deleteUserGroup(
                                    $user_id, $group_id);
                                $deleted++;
                            }
                        }
                    }
                    if ($deleted == 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_user_deleted'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row', 'user_filter',
                            'user_sorts',"visible_users"]);
                    } else if ($deleted > 1) {
                        return $parent->redirectWithMessage(
                            tl('social_component_users_deleted'),
                            ["arg", 'context', 'end_row', 'group_limit',
                            'num_show', 'start_row', 'user_filter',
                            'user_sorts',"visible_users"]);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_delete_user_group'),
                        ["arg", 'context', 'end_row', 'group_limit',
                        'num_show', 'start_row', 'user_filter',
                        'user_sorts',"visible_users"]);
                case "editgroup":
                    if (!$group_id || !$is_owner) {
                        break;
                    }
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    }
                    $data['FORM_TYPE'] = "editgroup";
                    $update_fields = [
                        ['register', 'REGISTER_TYPE','REGISTER_CODES'],
                        ['member_access', 'MEMBER_ACCESS', 'ACCESS_CODES'],
                        ['vote_access', 'VOTE_ACCESS', 'VOTING_CODES'],
                        ['post_lifetime', 'POST_LIFETIME', 'POST_LIFETIMES'],
                        ['encryption', 'ENCRYPTION', 'ENCRYPTION_CODES']
                        ];
                    $message = $this->updateGroup($data, $group,
                        $update_fields);
                    if (!empty($message)) {
                        return $parent->redirectWithMessage($message,
                            ['arg', 'browse', 'context', 'start_row',
                            'end_row',
                            'group_limit', 'num_show', 'user_filter',
                            'user_sorts', 'visible_users']);
                    }
                    $data['CURRENT_GROUP']['register'] =
                        $group['REGISTER_TYPE'];
                    $data['CURRENT_GROUP']['member_access'] =
                        $group['MEMBER_ACCESS'];
                    $data['CURRENT_GROUP']['vote_access'] =
                        $group['VOTE_ACCESS'];
                    $data['CURRENT_GROUP']['post_lifetime'] =
                        $group['POST_LIFETIME'];
                    $data['CURRENT_GROUP']['encryption'] =
                        $group['ENCRYPTION'];
                    $data['SCRIPT'] .= "listenAll('input.user-id', 'click',".
                        " updateCheckedUserIds);";
                    $this->getGroupUsersData($data, $group_id);
                    if (!empty($_FILES['DISCUSSION_DATA']['tmp_name'])) {
                        if (empty($_FILES['DISCUSSION_DATA']['data'])) {
                            $feed_data = $parent->web_site->fileGetContents(
                                $_FILES['DISCUSSION_DATA']['tmp_name']);
                        } else {
                            $feed_data = $_FILES['DISCUSSION_DATA']['data'];
                        }
                        $this->importDiscussions($group_id,
                            $group['OWNER_ID'], $feed_data);
                    }
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    break;
                case "inviteusers":
                    $data['FORM_TYPE'] = "inviteusers";
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    }
                    if (isset($_REQUEST['users_names']) && $is_owner) {
                        $users_string = $parent->clean($_REQUEST['users_names'],
                            "string");
                        $pre_user_names = preg_split("/\s+|\,/", $users_string);
                        $users_invited = false;
                        foreach ($pre_user_names as $user_name) {
                            $user_name = trim($user_name);
                            $user = $parent->model("user")->getUser($user_name);
                            if ($user) {
                                if (!$group_model->checkUserGroup(
                                    $user['USER_ID'], $group_id)) {
                                    $group_model->addUserGroup(
                                        $user['USER_ID'], $group_id,
                                        C\INVITED_STATUS);
                                    $users_invited = true;
                                }
                            }
                        }
                        $_REQUEST['arg'] = "editgroup";
                        if ($users_invited) {
                            return $parent->redirectWithMessage(
                                tl('social_component_users_invited'),
                                ["arg", 'context', 'end_row', 'group_limit',
                                'num_show', 'start_row', 'user_filter',
                                'user_sorts',"visible_users"]);
                        } else {
                            return $parent->redirectWithMessage(
                                tl('social_component_no_users_invited'),
                                ["arg", 'context', 'end_row', 'group_limit',
                                'num_show', 'start_row', 'user_filter',
                                'user_sorts',"visible_users"]);
                        }
                    }
                    break;
                case "joingroup":
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context'] == 'search') {
                        $_REQUEST['arg'] = 'search';
                    } else {
                        $_REQUEST['arg'] = 'none';
                    }
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if ($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                            $group_id, C\INVITED_STATUS)) {
                        $group_model->updateStatusUserGroup($user_id,
                            $group_id, C\ACTIVE_STATUS);
                        return $parent->redirectWithMessage(
                            tl('social_component_joined'), ['arg']);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_join'),
                        ['arg', 'browse', 'start_row', 'end_row',
                        'num_show']);
                case "graphstats":
                    if (!$group_id || (!$is_owner &&
                        $_SESSION['USER_ID'] != C\ROOT_ID)) {
                        break;
                    }
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    };
                    $data['FORM_TYPE'] = "graphstats";
                    $data['INCLUDE_SCRIPTS'][] = "chart";
                    $impression_model = $parent->model("impression");
                    $period = $_REQUEST['time'];
                    $data["STATISTICS"][$period] =
                    $impression_model->getPeriodHistogramData(
                        $_REQUEST['impression'], $period, $_REQUEST['item']);
                    $graph_data = [];
                    if ($period == C\ONE_DAY) {
                        $column_name = tl('social_component_hour');
                        $dt_format = "H";
                        $now = date("H");
                        for ($i = 0 ; $i < 24; $i++) {
                            $graph_data[" ".(($now + $i) % 24 + 1)] = 0;
                        }
                    } else if ($period == C\ONE_MONTH) {
                        $column_name = tl('social_component_day');
                        $dt_format = "d";
                        $now = date("d");
                        for ($i = 0 ; $i < 31; $i++) {
                            $graph_data[" ".(($now + $i) % 31 +1)] = 0;
                        }
                    } else if ($period == C\ONE_YEAR) {
                        $column_name = tl('social_component_month');
                        $dt_format = "M";
                        $now = date("n");
                        $months = [tl('social_component_jan'),
                            tl('social_component_feb'),
                            tl('social_component_mar'),
                            tl('social_component_apr'),
                            tl('social_component_may'),
                            tl('social_component_jun'),
                            tl('social_component_jul'),
                            tl('social_component_aug'),
                            tl('social_component_sep'),
                            tl('social_component_oct'),
                            tl('social_component_nov'),
                            tl('social_component_dec')];
                        for ($i = 0 ; $i < 12; $i++) {
                            $graph_data[$months[(($now + $i) % 12 )]] = 0;
                        }
                    }
                    $graph_title = tl('social_component_visits', $column_name);
                    foreach ($data['STATISTICS'][$period] as $key =>
                        $statistics_value) {
                        $timestamp = $statistics_value['UPDATE_TIMESTAMP'];
                        $dt = date($dt_format, $timestamp);
                        if ($period != C\ONE_YEAR) {
                            $graph_data[" ".intval($dt)] =
                                $statistics_value['VIEWS'];
                        } else {
                            $graph_data[$dt] =
                                $statistics_value['VIEWS'];
                        }
                    }
                    $graph_data = json_encode($graph_data);
                    if ($_SERVER["MOBILE"]) {
                        $properties = ["title" => $graph_title,
                            "width" => 340, "height" => 300,
                            "tick_font_size" => 8];
                    } else {
                        $properties = ["title" => $graph_title,
                            "width" => 700, "height" => 500];
                    }
                    $properties = json_encode($properties);
                    $data['SCRIPT'] .= 'chart = new Chart('.
                        '"chart", '. $graph_data .
                        ', '.$properties.'); chart.draw();';
                    $this->getGroupUsersData($data, $group_id);
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    break;
                case "memberaccess":
                    $update_fields = [
                        ['memberaccess', 'MEMBER_ACCESS', 'ACCESS_CODES']];
                    $message =
                        $this->updateGroup($data, $group, $update_fields);
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    unset($_REQUEST['group_id']);
                    return $parent->redirectWithMessage($message,
                        ['arg', 'browse', 'start_row', 'end_row',
                        'num_show', 'visible_users', 'user_filter']);
                case "postlifetime":
                    $update_fields = [
                        ['postlifetime', 'POST_LIFETIME', 'POST_LIFETIMES']];
                    $message =
                        $this->updateGroup($data, $group, $update_fields);
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    unset($_REQUEST['group_id']);
                    return $parent->redirectWithMessage($message,
                        ['arg', 'browse', 'start_row', 'end_row',
                        'num_show', 'visible_users', 'user_filter']);
                case "voteaccess":
                    $update_fields = [
                        ['voteaccess', 'VOTE_ACCESS', 'VOTING_CODES']];
                    $message =
                        $this->updateGroup($data, $group, $update_fields);
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    unset($_REQUEST['group_id']);
                    return $parent->redirectWithMessage($message,
                        ['arg', 'browse', 'start_row', 'end_row',
                        'num_show', 'visible_users', 'user_filter']);
                case "registertype":
                    $update_fields = [
                        ['registertype', 'REGISTER_TYPE',
                            'REGISTER_CODES']];
                    $message =
                        $this->updateGroup($data, $group, $update_fields);
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    unset($_REQUEST['group_id']);
                    return $parent->redirectWithMessage($message,
                        ['arg', 'browse', 'start_row', 'end_row',
                        'num_show', 'visible_users', 'user_filter']);
                case "search":
                    $data['ACCESS_CODES'][C\INACTIVE_STATUS * 10] =
                        tl('social_component_request_join');
                    $data['ACCESS_CODES'][C\INVITED_STATUS * 10] =
                        tl('social_component_invited');
                    $data['ACCESS_CODES'][C\SUSPENDED_STATUS * 10] =
                        tl('social_component_banned_status');
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                            $search_name, ['ALL_FIELDS' => ['name', 'owner',
                            'register', 'access','voting', 'lifetime'],
                            'EQUAL_COMPARISON_TYPES' =>
                            ['register', 'access', 'voting', 'lifetime']]);
                    if (empty($_SESSION['LAST_SEARCH'][$search_name]) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH'][$search_name] =
                            $_SESSION['SEARCH'][$search_name];
                        unset($_SESSION['SEARCH'][$search_name]);
                    } else {
                        $default_search = true;
                    }
                    break;
                case "statistics":
                    if (!$group_id || (!$is_owner &&
                        $_SESSION['USER_ID'] != C\ROOT_ID)) {
                            break;
                    }
                    if (!empty($_REQUEST['context']) &&
                        $_REQUEST['context']=='search') {
                        $data['context'] = 'search';
                    };
                    $data['FORM_TYPE'] = "statistics";
                    $impression_model = $parent->model("impression");
                    $periods = [C\ONE_HOUR, C\ONE_DAY, C\ONE_MONTH, C\ONE_YEAR,
                        C\FOREVER];
                    $stat_types = [C\GROUP_IMPRESSION, C\THREAD_IMPRESSION,
                        C\WIKI_IMPRESSION];
                    $filter = (empty($_REQUEST['filter'])) ? "" :
                        $parent->clean($_REQUEST['filter'], 'string');
                    $data['FILTER'] = $filter;
                    foreach ($periods as $period) {
                        $data["STATISTICS"][C\GROUP_IMPRESSION][$period] =
                            $impression_model->getStatistics(C\GROUP_IMPRESSION,
                            $period, $filter, $group_id);
                        $data["STATISTICS"][C\THREAD_IMPRESSION][$period] =
                            $impression_model->getStatistics(
                            C\THREAD_IMPRESSION, $period,  $filter, $group_id);
                        $data["STATISTICS"][C\WIKI_IMPRESSION][$period] =
                            $impression_model->getStatistics(C\WIKI_IMPRESSION,
                            $period, $filter, $group_id);
                    }
                    if (C\DIFFERENTIAL_PRIVACY) {
                        $this->socialPrivacy($data);
                    }
                    $this->getGroupUsersData($data, $group_id);
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    break;
                case "unsubscribe":
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                    } else {
                        $_REQUEST['arg'] = 'none';
                    }
                    $user_id = (isset($_REQUEST['user_id'])) ?
                        $parent->clean($_REQUEST['user_id'], 'int'): 0;
                    if ($user_id && $group_id &&
                        $group_model->checkUserGroup($user_id,
                        $group_id)) {
                        $group_model->deleteUserGroup($user_id,
                            $group_id);
                        return $parent->redirectWithMessage(
                            tl('social_component_unsubscribe'),
                            ['arg','start_row', 'end_row', 'num_show']);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_no_unsubscribe'),
                        ['arg', 'start_row', 'end_row', 'num_show']);
            }
        }
        $current_id = $_SESSION["USER_ID"];
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH'][$search_name])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        $search_name);
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH'][$search_name]['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH'][$search_name]['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["name", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $group_model,
            "GROUPS", C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            [$current_id, $browse]);
        $num_groups = count($data['GROUPS']);
        for ($i = 0; $i < $num_groups; $i++) {
            $data['GROUPS'][$i]['NUM_MEMBERS'] =
                $group_model->countGroupUsers($data['GROUPS'][$i]['GROUP_ID']);
        }
        if ($data['FORM_TYPE'] == 'addgroup') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Used to add Differential Privacy for each group
     *
     * @param object &$data contains fields which will be sent to the view
     */
    public function socialPrivacy(&$data)
    {
        $parent = $this->parent;
        $impression_model = $parent->model("impression");
        foreach ($stat_types as $field) {
            $i = 0;
            foreach ($periods as  $period) {
                if ($field == C\GROUP_IMPRESSION) {
                    if (!empty($data["STATISTICS"][$field][$period])) {
                        $view_stat = $impression_model->getImpressionStat(
                            $data["STATISTICS"][$field][$period][0]['ID'],
                            $field, $period);
                        $fuzzy_views = $view_stat[1];
                        if (empty($view_stat[0]) ||
                            $view_stat[0] != $data["STATISTICS"][$field][
                            $period][0]['NUM_VIEWS'] ||
                            $tmp_data[$i - 1] > $fuzzy_views) {
                            $fuzzy_views = $parent->addDifferentialPrivacy(
                                $data["STATISTICS"][$field][
                                $period][0]['NUM_VIEWS']);
                            /* Make sure each time period's
                            fuzzified view is at least as large as
                            previous time period's value */
                            if ($i > 0) {
                                if ($tmp_data[$i - 1] > $fuzzy_views) {
                                    $fuzzy_views = $tmp_data[$i - 1];
                                }
                            }
                            $impression_model->updateImpressionStat(
                                $data["STATISTICS"][$field][$period][0]['ID'],
                                $field, $period, $data["STATISTICS"][$field][
                                    $period][0]['NUM_VIEWS'],
                                $fuzzy_views);
                        }
                        $data["STATISTICS"][$field][$period][0]
                            ['NUM_VIEWS'] = ($fuzzy_views == 0)?
                            tl('managegroups_element_no_activity'):
                            $fuzzy_views;
                        $tmp_data[$i] = $fuzzy_views;
                        $i++;
                    }
                } else {
                    if (!empty($data['STATISTICS'][$field][$period])) {
                        foreach ($data['STATISTICS'][$field]
                            [$period] as $item_name =>
                            $item_data) {
                            $view_stat = $impression_model->getImpressionStat(
                                $item_data[0]['ID'], $field, $period);
                            $fuzzy_views = $view_stat[1];
                            if ($view_stat[0] != $item_data[0]['NUM_VIEWS'] ||
                                $tmp_data[$item_name][$i - 1] > $fuzzy_views) {
                                $fuzzy_views = $parent->addDifferentialPrivacy(
                                    $item_data[0]['NUM_VIEWS']);
                                if ($i > 0) {
                                    if ($tmp_data[$item_name][$i-1] >
                                        $fuzzy_views) {
                                        $fuzzy_views =
                                            $tmp_data[$item_name][$i - 1];
                                    }
                                }
                                $impression_model->updateImpressionStat(
                                    $item_data[0]['ID'],
                                    $field, $period, $item_data[0]['NUM_VIEWS'],
                                    $fuzzy_views);
                            }
                            $data["STATISTICS"][$field][$period][
                                $item_name][0]['NUM_VIEWS']=
                                ($fuzzy_views == 0) ?
                                tl('managegroups_element_no_activity'):
                                $fuzzy_views;
                            $tmp_data[$item_name][$i] =
                                $fuzzy_views;
                            /* Decrypt group items if encrypted before
                                displaying */
                            if ($field == C\THREAD_IMPRESSION &&
                                $group_model->isGroupEncrypted($group_id)) {
                                // Decrypt thread's title
                                $key = $group_model->getGroupKey($group_id);
                                $decrypted_item_name = $group_model->decrypt(
                                    $item_name, $key);
                                $data['STATISTICS'][$field][$period][
                                    $decrypted_item_name] = $item_data;
                                unset($data['STATISTICS'][$field][$period][
                                    $item_name]);
                            }
                        }
                        $i++;
                    }
                }
            }
        }
    }
    /**
     * Used to import group discussion thread from another grouping or bulletin
     * site that has the ability to show the group as rss or atom. Examples
     * of such site are: phpBB, google groups, phorum
     *
     * @param int $group_id id of group that thread post data will be imported
     *  into
     * @param int $user_id id of person doing the importing (should be
     *  owner of group)
     * @param string $feed_data an rss or atom feed containing forum/group posts
     */
    public function importDiscussions($group_id, $user_id, $feed_data)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $page = preg_replace('@<(/?)(\w+\s*)\:@u', '<$1', $feed_data);
        $page = preg_replace("@<link@", "<slink", $page);
        $page = preg_replace("@</link@", "</slink", $page);
        $page = preg_replace("@pubDate@i", "pubdate", $page);
        $page = preg_replace("@&lt;@", "<", $page);
        $page = preg_replace("@&gt;@", ">", $page);
        $page = preg_replace("@<br(\s[^>]*)*\/?>@i", "[br]", $page);
        $page = preg_replace("@<hr(\s[^>]*)*\/?>@i", "[hr]", $page);
        $page = preg_replace("@<\/?i(\s[^>]*)*>@", "''", $page);
        $page = preg_replace("@<\/?b(\s[^>]*)*>@",  "'''", $page);
        $page = preg_replace("@<\/?u(\s[^>]*)*>@",  "'''", $page);
        $page = preg_replace("@<\/?tt(\s[^>]*)*>@",  "'''", $page);
        $page = preg_replace("@<p(\s[^>]*)*>@", "\n\n", $page);
        $page = preg_replace("@<\/p(\s[^>]*)*>@", "\n", $page);
        $page = preg_replace('@<\/?(o|u)l(\s[^>]*)*>@', "\n\n", $page);
        $page = preg_replace("@<li(\s[^>]*)*>@", "*", $page);
        $page = preg_replace("@<\/li(\s[^>]*)*>@", "\n", $page);
        $page = preg_replace("@<!\[CDATA\[(.+?)\]\]>@s", '$1', $page);
        $dom = L\getDomFromString($page);
        $rss_elements = ["title" => "title",
            "description" => "description", "link" =>"slink",
            "author" => ["author", "creator"],
            "guid" => "guid", "pubdate" => "pubdate"];
        $nodes = $dom->getElementsByTagName('item');
        if ($nodes->length == 0) {
            // maybe we're dealing with atom rather than rss
            $nodes = $dom->getElementsByTagName('entry');
            $rss_elements = [
                "title" => "title", "description" => ["summary", "content"],
                "link" => "slink", "guid" => "id",
                "author" => ["author", "creator"],
                "pubdate" => "updated"];
        }
        $num_added = 0;
        $num_seen = 0;
        $items = [];
        $feed_types = ['phpbb', 'googlegroup', 'phorum'];
        $feed_type = 'unknown';
        $i = 0;
        foreach ($nodes as $node) {
            $item = [];
            foreach ($rss_elements as $db_element => $feed_element) {
                if (!is_array($feed_element)) {
                    $feed_element = [$feed_element];
                }
                foreach ($feed_element as $tag_name) {
                    $tag_node = $node->getElementsByTagName(
                            $tag_name)->item(0);
                    $element_text = (is_object($tag_node)) ?
                        $tag_node->nodeValue: "";
                    if ($element_text) {
                        break;
                    }
                }
                if ($db_element == "link" && $tag_node && $element_text == "") {
                    $element_text = $tag_node->getAttribute("href");
                }
                $element_text = htmlentities(strip_tags($element_text));
                $element_text = preg_replace('/\[br\]/', "<br>", $element_text);
                $element_text = preg_replace('/\[hr\]/', "<hr>", $element_text);
                $item[$db_element] = $element_text;
            }
            if ($feed_type == 'unknown') {
                if (stripos($item['link'], 'viewtopic.php') !== false) {
                    $feed_type = 'phpbb';
                } else if (stripos($item['link'],'groups.google.com')
                    !==false) {
                    $feed_type = 'googlegroup';
                } else if (stripos($item['link'], 'read.php') !== false) {
                    $feed_type = 'phorum';
                }
            }
            switch ($feed_type) {
                case 'phpbb':
                    if (@preg_match('/t\=(\d+)/', $item['link'], $match)
                        !== false) {
                        $item['thread'] = $match[1];
                    }
                    if (($pos = strrpos($item['description'], 'Statistics:'))
                        !== false) {
                        $item['description'] = substr($item['description'], 0,
                            $pos);
                    }
                    if (($pos = strrpos($item['title'], '&bull;'))
                        !== false) {
                        $item['title'] = trim(substr($item['title'], $pos + 6));
                    }
                    break;
                case 'googlegroup':
                    if (@preg_match('@/d/msg/.*/(.*)/@', $item['link'], $match)
                        !== false) {
                        $item['thread'] = $match[1];
                    }
                    break;
                case 'phorum':
                    if (@preg_match('@read\.php\?.*\,(.*)\,@', $item['link'],
                        $match) !== false) {
                        $item['thread'] = $match[1];
                    }
                    break;
                default:
                    $item['thread'] = $i;
            }
            $i++;
            $pos = (strtotime($item['pubdate'], 0)) ?
                strtotime($item['pubdate'], 0) : $i;
            $items[$pos] = $item;
        }
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        ksort($items);
        $threads = [];
        foreach ($items as $item) {
            $parent_id = 0;
            if (!empty($item['thread']) &&
                !empty($threads[$item['thread']])) {
                $parent_id = $threads[$item['thread']];
                $item['title'] = preg_replace("/^Re\:/", "--",
                    trim($item['title']), 1);
            }
            $post_prefix = "";
            $post_user_id = $group_model->getUserId($item['author']);
            if (!$post_user_id) {
                $post_user_id = $user_id;
                $post_prefix .= "'''" .
                    tl('social_component_originally_posted', $item['author']) .
                    "'''\n\n";
            }
            if (!$timestamp = strtotime($item['pubdate'], 0)) {
                $timestamp = time();
                $post_prefix .= "'''" .
                    tl('social_component_originally_dated', $item['pubdate']) .
                    "'''\n\n";
            }
            $thread_id = $group_model->addGroupItem(
                $parent_id, $group_id, $post_user_id, $item['title'],
                $parent->clean($post_prefix . $item['description'], "string"),
                C\STANDARD_GROUP_ITEM, $timestamp);
            if ($parent_id == 0) {
                $threads[$item['thread']] = $thread_id;
            }
        }
    }
    /**
     * Used to add a group to a user's list of group or to request
     * membership in a group if the group is By Request or Public
     * Request
     *
     * @param array &$data field variables to be drawn to view,
     *      we modify the SCRIPT component of this with a message
     *      regarding success of not of add attempt.
     * @param int $add_id group id to be added
     * @param int $register the registration type of the group
     */
    public function addGroup(&$data, $add_id, $register)
    {
        $parent = $this->parent;
        $group_model = $parent->model('group');
        $credit_model = $parent->model('credit');
        $user_id = $_SESSION['USER_ID'];
        $join_type = (($register == C\REQUEST_JOIN ||
            $register == C\PUBLIC_BROWSE_REQUEST_JOIN) &&
            $_SESSION['USER_ID'] != C\ROOT_ID) ?
            C\INACTIVE_STATUS : C\ACTIVE_STATUS;
        if ($register >= C\LOW_JOIN_FEE && in_array(C\MONETIZATION_TYPE,
            ['group_fees','fees_and_keywords'])) {
            $balance = $credit_model->getCreditBalance($user_id);
            if ($balance - $register < 0) {
                return $parent->redirectWithMessage(
                    tl('social_component_buy_more_credits_join'));
            }
            $group_name = $group_model->getGroupName($add_id);
            $strings_to_translate_for_model =
                [tl('social_component_join_group_fee')];
            $credit_model->updateCredits($user_id, -$register,
                'social_component_join_group_fee');
        }
        $msg = ($join_type == C\ACTIVE_STATUS) ?
            "doMessage('<h1 class=\"red\" >".
            tl('social_component_group_joined'). "</h1>');" :
            "doMessage('<h1 class=\"red\" >".
            tl('social_component_group_request_join'). "</h1>');";
        $group_model->addUserGroup(
            $user_id, $add_id, $join_type);
        $data['SCRIPT'] .= $msg;
        if (!in_array($register, [C\REQUEST_JOIN,
            C\PUBLIC_BROWSE_REQUEST_JOIN] ) ) {
            return;
        }
        // if account needs to be activated email owner
        $group_info = $group_model->getGroupById($add_id,
            C\ROOT_ID);
        $user_model = $parent->model("user");
        $owner_info = $user_model->getUser(
            $group_info['OWNER']);
        $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
            C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
            C\MAIL_SECURITY);
        $subject = tl('social_component_activate_group',
            $group_info['GROUP_NAME']);
        $current_username = $user_model->getUserName(
            $_SESSION['USER_ID']);
        $edit_user_url = C\NAME_SERVER . "?c=admin&a=manageGroups".
            "&arg=editgroup&group_id=$add_id&visible_users=true".
            "&user_filter=$current_username&preserve=true";
        $body = tl('social_component_activate_body',
            $current_username,
            $group_info['GROUP_NAME'])."\n".
            $edit_user_url . "\n\n".
            tl('social_component_notify_closing')."\n".
            tl('social_component_notify_signature');
        $message = tl(
            'social_component_notify_salutation',
            $owner_info['USER_NAME'])."\n\n";
        $message .= $body;
        $server->send($subject, C\MAIL_SENDER,
            $owner_info['EMAIL'], $message);
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the users that a group
     * has to subject to $_REQUEST['user_limit'] and
     * $_REQUEST['user_filter']. Information about these roles is added as
     * fields to $data[NUM_USERS_GROUP'] and $data['GROUP_USERS']
     *
     * @param array &$data data for the manageGroups view.
     * @param int $group_id group to look up users for
     */
    public function getGroupUsersData(&$data, $group_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_users'] = $_REQUEST['visible_users'] ?? 'false';
        $data['USER_SORTS'] = (empty($_REQUEST['user_sorts'])) ? [] :
            json_decode(urldecode($_REQUEST['user_sorts']), true);
        if ($data['USER_SORTS'] === null) {
            $data['USER_SORTS'] = json_decode(html_entity_decode(
                urldecode($_REQUEST['user_sorts'])), true);
            $data['USER_SORTS'] = ($data['USER_SORTS']) ? $data['USER_SORTS'] :
                [];
        }
        if ($data['visible_users'] == 'false') {
            unset($_REQUEST['user_filter']);
            unset($_REQUEST['user_limit']);
        }
        if (isset($_REQUEST['user_filter'])) {
            $user_filter = substr($parent->clean(
                $_REQUEST['user_filter'], 'string'), 0, C\NAME_LEN);
        } else {
            $user_filter = "";
        }
        $data['USER_FILTER'] = $user_filter;
        $data['NUM_USERS_GROUP'] =
            $group_model->countGroupUsers($group_id, $user_filter);
        if (isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USERS_GROUP']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['GROUP_USERS'] =
            $group_model->getGroupUsers($group_id, $user_filter,
            $data['USER_SORTS'], $group_limit);
    }
    /**
     * Used by $this->manageGroups to check and clean $_REQUEST variables
     * related to groups, to check that a user has the correct permissions
     * if the current group is to be modfied, and if so, to call model to
     * handle the update
     *
     * @param array &$data used to add any information messages for the view
     *     about changes or non-changes to the model
     * @param array &$group current group which might be altered
     * @param array $update_fields which fields in the current group might be
     *     changed. Elements of this array are triples, the name of the
     *     group field, name of the request field to use for data, and an
     *     array of allowed values for the field
     */
    public function updateGroup(&$data, &$group, $update_fields)
    {
        $parent = $this->parent;
        $changed = false;
        if (!isset($group["OWNER_ID"]) ||
            $group["OWNER_ID"] != $_SESSION['USER_ID']) {
            return tl('social_component_no_permission');
        }
        $return_value = "";
        foreach ($update_fields as $row) {
            list($request_field, $group_field, $check_field) = $row;
            if (isset($_REQUEST[$request_field]) &&
                in_array($_REQUEST[$request_field],
                    array_keys($data[$check_field]))) {
                if ($group[$group_field] != $_REQUEST[$request_field]) {
                    $group[$group_field] =
                        $_REQUEST[$request_field];
                    $changed = true;
                    $return_value =
                            tl('social_component_group_updated');
                }
            } else if (!empty($_REQUEST[$request_field]) &&
                is_int($_REQUEST[$request_field])) {
                $return_value =
                    tl('social_component_unknown_access');
            }
        }
        if ($changed) {
            $parent->model("group")->updateGroup($group);
        }
        return $return_value;
    }
    /**
     * Used to support requests related to posting, editing, modifying,
     * and deleting group feed items.
     *
     * @return array $data fields to be used by GroupfeedElement
     */
    public function groupFeeds()
    {
        $parent = $this->parent;
        $controller_name = (get_class($parent) == C\NS_CONTROLLERS .
            "AdminController") ? "admin" : "group";
        $data["CONTROLLER"] = $controller_name;
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $cron_model = $parent->model("cron");
        $cron_time = $cron_model->getCronTime("cull_old_items");
        $delta = time() - $cron_time;
        if ($delta > C\ONE_HOUR) {
            $cron_model->updateCronTime("cull_old_items");
            $group_model->cullExpiredGroupItems();
        } else if ($delta == 0) {
            $cron_model->updateCronTime("cull_old_items");
        }
        $data["ELEMENT"] = "groupfeed";
        $data['SCRIPT'] = "";
        $data["INCLUDE_STYLES"] = ["editor"];
        if (isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
        } else {
            $user_id = C\PUBLIC_GROUP_ID;
        }
        $username = $user_model->getUsername($user_id);
        if (isset($_REQUEST['num'])) {
            $results_per_page = $parent->clean($_REQUEST['num'], "int");
        } else if (isset($_SESSION['MAX_PAGES_TO_SHOW']) &&
            $_SESSION['MAX_PAGES_TO_SHOW'] > 0) {
            $results_per_page = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $results_per_page = C\NUM_RESULTS_PER_PAGE;
        }
        if (isset($_REQUEST['limit'])) {
            $limit = $parent->clean($_REQUEST['limit'], "int");
        } else {
            $limit = 0;
        }
        if (isset($_SESSION['OPEN_IN_TABS'])) {
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        $clean_array = [ "title" => "string", "description" => "string",
            "just_group_id" => "int", "just_thread" => "int",
            "just_user_id" => "int"];
        $strings_array = [ "title" => C\TITLE_LEN, "description" =>
            C\MAX_GROUP_POST_LEN];
        if ($user_id == C\PUBLIC_GROUP_ID) {
            $_SESSION['LAST_ACTIVITY']['a'] = 'groupFeeds';
            $_SESSION['LAST_ACTIVITY']['c'] = $controller_name;
        } else {
            unset($_SESSION['LAST_ACTIVITY']);
        }
        foreach ($clean_array as $field => $type) {
            $$field = ($type == "string") ? "" : 0;
            if (isset($_REQUEST[$field])) {
                $tmp = $parent->clean($_REQUEST[$field], $type);
                if (isset($strings_array[$field])) {
                    $tmp = substr($tmp, 0, $strings_array[$field]);
                }
                if ($user_id == C\PUBLIC_GROUP_ID) {
                    $_SESSION['LAST_ACTIVITY'][$field] = $tmp;
                }
                $$field = $tmp;
            }
        }
        $possible_arguments = ["addcomment", "addgroup", "deletepost",
            "downvote", "newthread", "status", "updatepost",  "upvote"];
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "addcomment":
                    if (!empty($_REQUEST['page_type']) &&
                         $_REQUEST['page_type'] == "page_and_feedback") {
                         $_REQUEST['a'] = "wiki";
                         unset($_REQUEST['just_thread']);
                         unset($_REQUEST['limit']);
                         unset($_REQUEST['num']);
                    }
                    if (!isset($_REQUEST['parent_id'])
                        || !$_REQUEST['parent_id']
                        || !isset($_REQUEST['group_id'])
                        || !$_REQUEST['group_id']) {
                        return $parent->redirectWithMessage(
                            tl('social_component_comment_error'),
                            ['page_name']);
                    }
                    if (!$description) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_comment'),
                            ['page_name']);
                    }
                    $parent_id = $parent->clean($_REQUEST['parent_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id,
                        $user_id, true);
                    $read_comment = [C\GROUP_READ_COMMENT, C\GROUP_READ_WRITE,
                        C\GROUP_READ_WIKI];
                    if (!$group || $user_id == C\PUBLIC_USER_ID ||
                        ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $read_comment) &&
                        $user_id != C\ROOT_ID)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_post_access'),
                            ['page_name']);
                    }
                    if ($parent_id >= 0) {
                        $parent_item = $group_model->getGroupItem($parent_id);
                        if (!$parent_item) {
                            return $parent->redirectWithMessage(
                                tl('social_component_no_post_access'),
                                ['page_name']);
                        }
                    } else {
                        $parent_item = [
                            'TITLE' => tl('social_component_join_group',
                                $username, $group['GROUP_NAME']),
                            'DESCRIPTION' =>
                                tl('social_component_join_group_detail',
                                    date("r", $group['JOIN_DATE']),
                                    $group['GROUP_NAME']),
                            'ID' => -$group_id,
                            'PARENT_ID' => -$group_id,
                            'GROUP_ID' => $group_id
                        ];
                    }
                    $title = "-- " . $parent_item['TITLE'];
                    $id = $group_model->addGroupItem($parent_item["ID"],
                        $group_id, $user_id, $title, $description);
                    list($bots_called, $post_parts) =
                        $this->getRequestedBots($group_id, $description);
                    $result = $this->handleResourceUploads(
                            $group_id, "post" . $id);
                    if ($result == self::UPLOAD_FAILED) {
                        return $parent->redirectWithMessage(
                            tl('social_component_upload_error'));
                    }
                    $followers = $group_model->getThreadFollowers(
                        $parent_item["ID"], $group['OWNER_ID'], $user_id);
                    $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
                        C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
                        C\MAIL_SECURITY);
                    $post_url = "";
                    if (in_array($group['REGISTER_TYPE'],
                        [C\PUBLIC_BROWSE_REQUEST_JOIN, C\PUBLIC_JOIN])) {
                        $post_url = B\feedsUrl("thread", $parent_item["ID"],
                            true, "group", false) . "preserve=true\n";
                    }
                    $subject = tl('social_component_thread_notification',
                        $parent_item['TITLE']);
                    $body = tl('social_component_notify_body') . "\n" .
                        $parent_item['TITLE']."\n".
                        $post_url .
                        tl('social_component_notify_closing')."\n".
                        tl('social_component_notify_signature');
                    foreach ($followers as $follower) {
                        if (empty($follower['USER_ID'])) {
                            continue;
                        }
                        if (!$user_model->isBotUser($follower['USER_ID'])) {
                            $message = tl('social_component_notify_salutation',
                                $follower['USER_NAME']) . "\n\n";
                            $message .= $body;
                            $server->send($subject, C\MAIL_SENDER,
                                $follower['EMAIL'], $message);
                        }
                    }
                    $this->addAnyBotResponses($parent_item["ID"], $group_id,
                        $bots_called, $title, $post_parts);
                    return $parent->redirectWithMessage(
                        tl('social_component_comment_added'), ['page_name']);
                    break;
                case "addgroup":
                    if ($_SESSION['USER_ID'] == C\PUBLIC_USER_ID) {
                        return $parent->redirectWithMessage(
                            tl('social_component_public_cant_add'));
                    }
                    $register =
                        $group_model->getRegisterType($just_group_id);
                    if ($just_group_id > 0 && !empty($register)
                        && $register != C\NO_JOIN) {
                        if ($register >= C\LOW_JOIN_FEE &&
                            !in_array(C\MONETIZATION_TYPE,
                            ['group_fees','fees_and_keywords'])) {
                            $register = C\NO_JOIN;
                            return $parent->redirectWithMessage(
                                tl('social_component_groupname_cant_add'));
                        } else if ($register >= C\LOW_JOIN_FEE &&
                            in_array(C\MONETIZATION_TYPE, ['group_fees',
                            'fees_and_groups'])) {
                            $url = htmlentities(B\controllerUrl(
                                "admin", true)). C\CSRF_TOKEN . "=" .
                                $parent->generateCSRFToken(
                                    $_SESSION["USER_ID"]) .
                                "&a=manageGroups" .
                                "&arg=search&browse=true&name=" .
                                $name;
                            return $parent->redirectLocation($url);
                        }
                        $this->addGroup($data, $just_group_id, $register);
                        unset($data['SUBSCRIBE_LINK']);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('social_component_groupname_cant_add'));
                    }
                    break;
                case "deletepost":
                    if (!empty($_REQUEST['page_type']) &&
                        $_REQUEST['page_type'] == "page_and_feedback") {
                        $_REQUEST['a'] = "wiki";
                    }
                    if (!isset($_REQUEST['post_id'])) {
                        return $parent->redirectWithMessage(
                            tl('social_component_delete_error'),
                            ['page_name']);
                        break;
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $group_item = $group_model->getGroupItem($post_id);
                    $success = false;
                    if ($group_item) {
                        // this method checks if user can delete post
                        $success =
                            $group_model->deleteGroupItem($post_id, $user_id);
                    }
                    $search_array = [["parent_id", "=", $just_thread, ""]];
                    $item_count = $group_model->getGroupItemCount($search_array,
                        $user_id, -1);
                    if (!empty($_REQUEST['page_type']) &&
                         $_REQUEST['page_type'] == "page_and_feedback") {
                         unset($_REQUEST['just_thread']);
                         $_REQUEST['group_id'] = $group_item['GROUP_ID'];
                    }
                    if ($success) {
                        $group_model->deleteResources($group_item["GROUP_ID"],
                            "post" . $post_id);
                        if ($item_count == 0) {
                            unset($_REQUEST['just_thread']);
                        }
                        return $parent->redirectWithMessage(
                            tl('social_component_item_deleted'),
                            ['page_name']);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_item_deleted'),
                            ['page_name']);
                    }
                    break;
                case "downvote":
                    if (!isset($_REQUEST['group_id']) || !$_REQUEST['group_id']
                        ||!isset($_REQUEST['post_id']) ||
                        !$_REQUEST['post_id']) {
                        return $parent->redirectWithMessage(
                            tl('social_component_vote_error'));
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id,
                        $user_id, true);
                    if (!$group || $user_id == C\PUBLIC_USER_ID
                        || (!in_array($group["VOTE_ACCESS"],
                        [C\UP_DOWN_VOTING_GROUP] ) ) ) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_vote_access'));
                    }
                    $post_item = $group_model->getGroupItem($post_id);
                    if (!$post_item || $post_item['GROUP_ID'] != $group_id) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    if ($group_model->alreadyVoted($user_id, $post_id)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_already_voted'));
                    }
                    $group_model->voteDown($user_id, $post_id);
                    return $parent->redirectWithMessage(
                        tl('social_component_vote_recorded'));
                    break;
                case "newthread":
                    if (!isset($_REQUEST['group_id']) ||
                        !$_REQUEST['group_id']) {
                        return $parent->redirectWithMessage(
                            tl('social_component_comment_error'));
                    }
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    if (!$description || !$title) {
                        return $parent->redirectWithMessage(
                            tl('social_component_need_title_description'));
                    }
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id,
                        $user_id, true);
                    $new_thread = [C\GROUP_READ_WRITE, C\GROUP_READ_WIKI];
                    if (!$group || $user_id == C\PUBLIC_USER_ID ||
                        ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $new_thread) &&
                        $user_id != C\ROOT_ID)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    $thread_id = $group_model->addGroupItem(0,
                        $group_id, $user_id, $title, $description);
                    list($bots_called, $post_parts) =
                        $this->getRequestedBots($group_id, $description);
                    $result = $this->handleResourceUploads(
                            $group_id, "post" . $thread_id);
                    if ($result == self::UPLOAD_FAILED) {
                        return $parent->redirectWithMessage(
                            tl('social_component_upload_error'));
                    }
                    if ($user_id == $group['OWNER_ID']) {
                        $followers = $group_model->getGroupUsers($group_id);
                    } else {
                        $owner_name = $user_model->getUsername(
                            $group['OWNER_ID']);
                        $follower = $user_model->getUser($owner_name);
                        $followers = [$follower];
                    }
                    $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
                        C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
                        C\MAIL_SECURITY);
                    $subject = tl('social_component_new_thread_mail',
                        $group['GROUP_NAME']);
                    $post_url = B\feedsUrl("thread", $thread_id, true,
                        "group", false)."preserve=true\n";
                    $body = tl('social_component_new_thread_body',
                        $group['GROUP_NAME'])."\n".
                        "\"".$title."\"\n".
                        $post_url .
                        tl('social_component_notify_closing')."\n".
                        tl('social_component_notify_signature');
                    foreach ($followers as $follower) {
                        if ($follower['USER_ID'] != $user_id &&
                            ($user_id == $group['OWNER_ID'] ||
                             $follower['USER_ID'] == $group['OWNER_ID'])
                        && !$user_model->isBotUser($follower['USER_ID'])) {
                            $message = tl('social_component_notify_salutation',
                                $follower['USER_NAME'])."\n\n";
                            $message .= $body;
                            $server->send($subject, C\MAIL_SENDER,
                                $follower['EMAIL'], $message);
                        }
                    }
                    $this->addAnyBotResponses($thread_id, $group_id,
                        $bots_called, "--" . $title, $post_parts);
                    $thread_url = B\feedsUrl('thread', $thread_id) .
                        C\CSRF_TOKEN . "=" .
                        $parent->generateCSRFToken($_SESSION["USER_ID"]) ;
                    $_SESSION['DISPLAY_MESSAGE'] =
                        tl('social_component_thread_created');
                    //return $parent->redirectLocation($thread_url);
                    return $parent->redirectWithMessage(
                        tl('social_component_thread_created'));
                    break;
                case "status":
                    $data['REFRESH'] = "feedstatus";
                    if (!empty($_REQUEST['feed_time']))
                    $data['REFRESH_TIMESTAMP'] = $parent->clean(
                        $_REQUEST['feed_time'], "int");
                    break;
                case "updatepost":
                    if (!empty($_REQUEST['page_type']) &&
                         $_REQUEST['page_type'] == "page_and_feedback") {
                         $_REQUEST['a'] = "wiki";
                         unset($_REQUEST['limit']);
                         unset($_REQUEST['num']);
                    }
                    if (!isset($_REQUEST['post_id'])) {
                        return $parent->redirectWithMessage(
                            tl('social_component_comment_error'),
                            ['page_name']);
                    }
                    if (!$description || !$title) {
                        return $parent->redirectWithMessage(
                            tl('social_component_need_title_description'),
                            ['page_name']);
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $action = "updatepost" . $post_id;
                    if (!$parent->checkCSRFTime(C\CSRF_TOKEN, $action)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_post_edited_elsewhere'),
                            ['page_name']);
                    }
                    $items = $group_model->getGroupItems(0, 1,
                        [["post_id", "=", $post_id, ""]], $user_id);
                    if (isset($items[0])) {
                        $item = $items[0];
                    } else {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_update_access'),
                            ['page_name']);
                    }
                    $group_id = $item['GROUP_ID'];
                    $_REQUEST['group_id'] = $group_id;
                    $group = $group_model->getGroupById($group_id, $user_id,
                        true);
                    $update_thread = [C\GROUP_READ_WRITE, C\GROUP_READ_WIKI];
                    if ($post_id != $item['PARENT_ID'] && $post_id > 0) {
                        $update_thread[] = C\GROUP_READ_COMMENT;
                        $parent_items = $group_model->getGroupItems(0, 1,
                            [["post_id", "=", $item['PARENT_ID'], ""]],
                            $user_id);
                        if (!empty($parent_items[0])) {
                            $parent_item = $parent_items[0];
                            $title = "-- " . $parent_item['TITLE'];
                        }
                    }
                    if (!$group || $user_id == C\PUBLIC_USER_ID ||
                        ($group["OWNER_ID"] != $user_id &&
                        !in_array($group["MEMBER_ACCESS"], $update_thread) &&
                        $user_id != ROOT_ID)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_update_access'),
                            ['page_name']);
                        break;
                    }
                    $group_model->updateGroupItem($post_id, $title,
                        $description);
                    $result = $this->handleResourceUploads(
                        $group_id, "post" . $post_id);
                    if ($result == self::UPLOAD_FAILED) {
                        return $parent->redirectWithMessage(
                            tl('social_component_upload_error'),
                            ['page_name']);
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_post_updated'),
                        ['page_name']);
                    break;
                case "upvote":
                    if (!isset($_REQUEST['group_id']) || !$_REQUEST['group_id']
                        ||!isset($_REQUEST['post_id']) ||
                        !$_REQUEST['post_id']) {
                        return $parent->redirectWithMessage(
                            tl('social_component_vote_error'));
                    }
                    $post_id = $parent->clean($_REQUEST['post_id'], "int");
                    $group_id = $parent->clean($_REQUEST['group_id'], "int");
                    $group = $group_model->getGroupById($group_id, $user_id,
                        true);
                    if (!$group || $user_id == C\PUBLIC_USER_ID ||
                        (!in_array($group["VOTE_ACCESS"],
                        [C\UP_VOTING_GROUP, C\UP_DOWN_VOTING_GROUP] ) ) ) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_vote_access'));
                    }
                    $post_item = $group_model->getGroupItem($post_id);
                    if (!$post_item || $post_item['GROUP_ID'] != $group_id) {
                        return $parent->redirectWithMessage(
                            tl('social_component_no_post_access'));
                    }
                    if ($group_model->alreadyVoted($user_id, $post_id)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_already_voted'));
                    }
                    $group_model->voteUp($user_id, $post_id);
                    return $parent->redirectWithMessage(
                        tl('social_component_vote_recorded'));
                    break;
            }
        }
        $view_mode = (isset($_REQUEST['v'])) ?
            $parent->clean($_REQUEST['v'], "string") : "grouped";
        $data['VIEW_MODE'] = $view_mode;
        $view_mode = (!$just_group_id && !$just_user_id
            && !$just_thread) ? $view_mode : "ungrouped";
        if ($view_mode == "grouped") {
            $this->calculateRecentFeedsAndThread($data, $user_id);
            return $this->calculateGroupedFeeds($user_id, $limit,
                $results_per_page, $controller_name, $data);
        }
        $groups_count = 0;
        $pages = [];
        $page = [];
        if (!$just_user_id && (!$just_thread || $just_thread < 0)) {
            $search_array = [
                ["group_id", "=", max(-$just_thread, $just_group_id), ""],
                ["access", "!=", C\GROUP_PRIVATE, ""],
                ["status", "=", C\ACTIVE_STATUS, ""],
                ["join_date", "=", "", "DESC"]
            ];
            $groups = $group_model->getRows(
                0, $limit + $results_per_page, $groups_count,
                $search_array, [$user_id, false]);
            // create feed items for first join dates to given groups
            foreach ($groups as $group) {
                $page = [];
                $page['USER_ICON'] = C\SHORT_BASE_URL .
                    "resources/anonymous.png";
                $page[self::TITLE] = tl('social_component_join_group',
                    $username, $group['GROUP_NAME']);
                $page[self::DESCRIPTION] =
                    tl('social_component_join_group_detail',
                        date("r", $group['JOIN_DATE']), $group['GROUP_NAME']);
                $page['ID'] = -$group['GROUP_ID'];
                $page['PARENT_ID'] = -$group['GROUP_ID'];
                $page['USER_NAME'] = "";
                $page['USER_ID'] = "";
                $page['GROUP_ID'] = $group['GROUP_ID'];
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
                $page['MEMBER_ACCESS'] = $group['MEMBER_ACCESS'];
                $page['STATUS'] = $group['STATUS'];
                if ($group['OWNER_ID'] == $user_id || $user_id == C\ROOT_ID) {
                    $page['MEMBER_ACCESS'] = C\GROUP_READ_WIKI;
                }
                $page['PUBDATE'] = $group['JOIN_DATE'];
                $pages[$group['JOIN_DATE']] = $page;
            }
        }
        $pub_clause = ['pub_date', "=", "", "DESC"];
        $sort = "krsort";
        if ($just_thread) {
            $thread_parent = $group_model->getGroupItem($just_thread);
            $group_id = $thread_parent['GROUP_ID'] ?? false;
            if (isset($thread_parent["TYPE"]) &&
                $thread_parent["TYPE"] == C\WIKI_GROUP_ITEM) {
                $page_info = $group_model->getPageInfoByThread($just_thread);
                if (isset($page_info["PAGE_NAME"])) {
                    $group_id = $page_info['GROUP_ID'];
                    $data["WIKI_PAGE_NAME"] = $page_info["PAGE_NAME"];
                    $group = $group_model->getGroupById($group_id, $user_id);
                    if ($group["OWNER_ID"] == $user_id ||
                        ($group["STATUS"] == C\ACTIVE_STATUS &&
                        $group["MEMBER_ACCESS"] == C\GROUP_READ_WIKI)) {
                        $data["CAN_EDIT"] = true;
                        $edit_or_source = "edit";
                    } else {
                        $data["CAN_EDIT"] = false;
                        $edit_or_source = "source";
                    }
                }
            }
            if ((!isset($_REQUEST['f']) ||
                !in_array($_REQUEST['f'], ["rss", "json", "serial"]))) {
                $pub_clause = ['pub_date', "=", "", "ASC"];
                $sort = "ksort";
                $parent->model("impression")->add($user_id, $just_thread,
                    C\THREAD_IMPRESSION);
                $parent->model("impression")->add($user_id, $group_id,
                    C\GROUP_IMPRESSION);
            }
        }
        $search_array = [
            ["parent_id", "=", $just_thread, ""],
            ["group_id", "=", $just_group_id, ""],
            ["user_id", "=", $just_user_id, ""],
            $pub_clause];
        $for_group = ($just_group_id) ? $just_group_id : (($just_thread) ?
            -2 : -1);
        if (!empty($just_thread) ) {
            $data['JUST_THREAD'] = $just_thread;
        }
        list($item_count, $pages) = $this->initializeFeedItems($data, $pages,
            $user_id, $search_array, $for_group, $sort, $limit,
            $results_per_page);
        $data['SUBTITLE'] = "";
        $type = "";
        $type_id = "";
        if (!empty($just_thread) ) {
            $thread_start_item = $group_model->getGroupItem($just_thread);
            if (empty($thread_start_item) ||
                empty($thread_start_item["TITLE"])) {
                $data['NO_POSTS_IN_THREAD'] = true;
                if ($just_thread < 0) {
                    $data['SUBTITLE'] = empty($pages[0][self::TITLE]) ?
                        "" : $pages[0][self::TITLE];
                    $data["GROUP_ID"] = -$just_thread;
                    $group = $group_model->getGroupById(
                        $data["GROUP_ID"], $user_id);
                    if (!empty($group)) {
                        $data["GROUP_NAME"] = $group["GROUP_NAME"];
                        $data['GROUP_STATUS'] = $group['STATUS'];
                    }
                }
            } else {
                $title = $thread_start_item["TITLE"];
                $data['SUBTITLE'] = trim($title, "\- \t\n\r\0\x0B");
                $type = "thread";
                $type_id = $just_thread;
                $group = $group_model->getGroupById(
                    $thread_start_item['GROUP_ID'], $user_id);
                $data["GROUP_ID"] = $group["GROUP_ID"];
                $data["GROUP_NAME"] = $group["GROUP_NAME"];
                $data['GROUP_STATUS'] = $group['STATUS'];
            }
        }
        if (!$just_group_id && !$just_thread) {
           $data['GROUP_STATUS'] = C\ACTIVE_STATUS;
        }
        if ($just_group_id) {
            $group = $group_model->getGroupById($just_group_id, $user_id);
            if (!$group) {
                if ($user_id == C\PUBLIC_USER_ID) {
                    $_REQUEST = ['c' => "admin", 'a' => '', C\CSRF_TOKEN => ''];
                    return $parent->redirectWithMessage(
                        tl("social_component_login_first"));
                }
                unset($_REQUEST['route']);
                $_REQUEST['just_group_id'] = C\PUBLIC_GROUP_ID;
                return $parent->redirectWithMessage(
                    tl("social_component_no_group_access"), false, false,
                    true);
            }
            $data['GROUP_STATUS'] = $group['STATUS'];
            if (!isset($page[self::SOURCE_NAME]) ) {
                $page[self::SOURCE_NAME] = $group['GROUP_NAME'];
            }
            if (empty($pages) ) {
                $data['NO_POSTS_YET'] = true;
                if ($user_id == $group['OWNER_ID'] || $user_id == C\ROOT_ID) {
                        // this case happens when a group is no read
                        $data['NO_POSTS_START_THREAD'] = true;
                }
            }
            if ($user_id != C\PUBLIC_USER_ID &&
                !$group_model->checkUserGroup($user_id, $just_group_id)) {
                $data['SUBSCRIBE_LINK'] = $group_model->getRegisterType(
                    $just_group_id);
            }
            $data['SUBTITLE'] = $page[self::SOURCE_NAME];
            $type= "group";
            $type_id = $just_group_id;
            $data['JUST_GROUP_ID'] = $just_group_id;
            $parent->model("impression")->add($user_id, $just_group_id,
                C\GROUP_IMPRESSION);
        }
        if ($just_user_id) {
            $page["USER_NAME"] = $user_model->getUsername($just_user_id);
            $data['SUBTITLE'] = $page["USER_NAME"];
            $type = "user";
            $type_id = $just_user_id;
            $data['JUST_USER_ID'] = $just_user_id;
        }
        if ($user_id != C\PUBLIC_USER_ID) {
            $thread_ids = $parent->model("impression")->recent($user_id,
                C\THREAD_IMPRESSION, 3);
        }
        $this->calculateRecentFeedsAndThread($data, $user_id);
        $data['TOTAL_ROWS'] = $item_count + $groups_count;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $token_string = ($user_id !=  C\PUBLIC_USER_ID )? C\CSRF_TOKEN . "=".
            $this->parent->generateCSRFToken($user_id) : "";
        $data['PAGING_QUERY'] = htmlentities(B\feedsUrl($type, $type_id,
            true, $controller_name));
        $paging_query = $data['PAGING_QUERY'];
        if (!empty($type)) {
            $data['RSS_FEED_URL'] = $paging_query . "f=rss";
        }
        if ($view_mode == 'ungrouped') {
            $connector = (substr($paging_query, -1) == "?") ? "" :
                "&amp;";
            $paging_query .= "{$connector}v=ungrouped";
            $data['RSS_FEED_URL'] = $paging_query . "&amp;f=rss";
        }
        $paging_query = html_entity_decode($paging_query);
        $data['SCRIPT'] .= " let nextPage = initNextResultsPage($limit," .
            " {$data['TOTAL_ROWS']}, $results_per_page, ".
            "'$paging_query&$token_string', '', 'results-container', ".
            "'result-batch');\n";
        if ($limit > 0) {
            $data['SCRIPT'] .= " let previousPage = initPreviousResultsPage(".
                "$limit, {$data['TOTAL_ROWS']}, $results_per_page, ".
                "'$paging_query&$token_string', 'results-container', ".
                "'result-batch');\n";
        }
        if (!empty($data['REFRESH_TIMESTAMP']) && !empty($pages)) {
            $max_pubdate = 0;
            $num_pages = count($pages);
            for ($i = 0; $i < $num_pages; $i++) {
                $page = $pages[$i];
                if (empty($page['PUBDATE']) ||
                    ($page['PUBDATE'] <= $data['REFRESH_TIMESTAMP'] &&
                    !empty($page['EDIT_DATE']) &&
                    $page['EDIT_DATE'] > $max_pubdate)) {
                    unset($pages[$i]);
                    $limit++;
                    continue;
                }
                if ($page['PUBDATE'] > $max_pubdate) {
                    $max_pubdate = $page['PUBDATE'];
                }
                if (!empty($page['EDIT_DATE']) &&
                    $page['EDIT_DATE'] > $max_pubdate) {
                    $max_pubdate = $page['EDIT_DATE'];
                }
            }
            if ($max_pubdate <= $data['REFRESH_TIMESTAMP']) {
                \seekquarry\yioop\library\webExit();
            }
        }
        $data['PAGES'] = array_values($pages);
        $data['LIMIT'] = $limit;
        $this->initializeWikiEditor($data, -1);
        return $data;
    }
    /**
     * Determines a list of posts that might need to reply to a post in
     * a group
     *
     * @param int $group_id get chat bots following this group
     * @param string $description post message to see if called any bots
     *      by using a phrase like: @bot_name some request
     * @return array [array of bots referred to in post, array of post
     *      portions for each robot]
     */
    private function getRequestedBots($group_id, $description)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $bot_followers = $group_model->getGroupBots($group_id);
        $bots = [];
        $bots_called = [];
        $post_parts = [];
        foreach ($bot_followers as $bot_follower) {
            $bots[] = $bot_follower['USER_NAME'];
        }
        if (preg_match_all('/(?<!\w)@(\w+)\s([^@]*)/si', $description,
            $matches)) {
            foreach ($matches[1] as $match) {
                $match = mb_strtolower($match);
                $index = array_search($match, $bots);
                if ($index !== false) {
                    $bots_called[] = $bot_followers[$index];
                } else {
                    $bots_called[] = null;
                }
            }
            $post_parts = $matches[2];
        }
        return [$bots_called, $post_parts];
    }
    /**
     * This follows up to the thread post $thread_id to $group_id any
     * response that $bots following this group might have
     *
     * @param int $thread_id id of the thread post to follow up
     * @param int $group_id of group thread post was posted to
     * @param array $bots list of chat bot users following group
     * @param string $title title of thread post to follow up
     * @param array $posts for each bot the contents of message applicable
     *      to that bot
     */
    private function addAnyBotResponses($thread_id, $group_id, $bots, $title,
        $posts)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $num_bots = count($bots);
        $sites = [];
        $post_data = [];
        $time = time();
        $user_id = (empty($_SESSION['USER_ID'])) ? C\PUBLIC_USER_ID:
            $_SESSION['USER_ID'];
        $user_name = (empty($_SESSION['USER_NAME'])) ? "PUBLIC" :
            $_SESSION['USER_NAME'];
        if (empty($_SESSION["CHAT_BOT_STATES"])) {
            $_SESSION["CHAT_BOT_STATES"] = [];
        }
        for ($i = 0; $i < $num_bots; $i++) {
            if (empty($bots[$i]['USER_ID'])) {
                continue;
            }
            $bot_id = $bots[$i]['USER_ID'];
            if (empty($_SESSION["CHAT_BOT_STATES"][$bot_id])) {
                $_SESSION["CHAT_BOT_STATES"][$bot_id] = "0";
            }
            $bots[$i]['PATTERN'] =
                $this->computeBotPattern($bot_id, $posts[$i]);
            if (!empty($bots[$i]['PATTERN'])) {
                $bots[$i]['PATTERN']['VARS']['REMOTE_MESSAGE'] =
                    $this->interpolateBotVariables(
                    $bots[$i]['PATTERN']['REMOTE_MESSAGE'],
                    $bots[$i]['PATTERN']['VARS']);
            }
            if (empty($bots[$i]['PATTERN']['VARS']['REMOTE_MESSAGE'])) {
                $sites[$i] = [];
            } else {
                $sites[$i][CrawlConstants::URL] = $bots[$i]['CALLBACK_URL'];
                $post_data[$i] = "remote_message=".
                    urlencode($bots[$i]['PATTERN']['VARS']['REMOTE_MESSAGE']) .
                    "&post=" . urlencode($posts[$i]) . "&bot_token=" .
                    hash("sha256", $bots[$i]['BOT_TOKEN'] .
                        $time . $posts[$i]) . "*" . $time .
                    "&bot_name=" . $bots[$i]['USER_NAME'];
            }
        }
        $outputs = [];
        if (count($sites) > 0) {
            $outputs = FetchUrl::getPages($sites, false, 0, null,
                self::URL, self::PAGE, true, $post_data);
        }
        for ($i = 0;  $i < $num_bots; $i++) {
            if (!empty($bots[$i]['PATTERN']) &&
                isset($outputs[$i][self::PAGE]) ) {
                $bots[$i]['PATTERN']['VARS']['REMOTE_RESPONSE'] =
                    $outputs[$i][self::PAGE];
            }
        }
        foreach ($bots as $bot) {
            if (empty($bot['PATTERN'])) {
                continue;
            }
            $bot_id = $bot['USER_ID'];
            $result_state = $this->interpolateBotVariables(
                $bot['PATTERN']['RESULT_STATE'],
                $bot['PATTERN']['VARS']);
            $_SESSION["CHAT_BOT_STATES"][$bot_id] = (empty($result_state)) ?
                "0" : $result_state;
            $bot['PATTERN']['VARS']['RESULT_STATE'] = $result_state;
            $response = $this->interpolateBotVariables(
                $bot['PATTERN']['RESPONSE'],
                $bot['PATTERN']['VARS']);
            if (!empty($response)) {
                $group_model->addGroupItem($thread_id,
                    $group_id, $bot_id, $title, $response);
            }
        }
    }
    /**
     * Determines which, if any, chat bot patterns of chat bot $bot_id are
     * applicable to the post $post given the current state of the chat bot
     * for the user who made $post.
     *
     * @param int $bot_id of chat bot to look for applicable pattern
     * @param string $post messages to compare against pattern request
     *      expressions
     * @return array $pattern first pattern that matches. Its ['VARS'] field
     *      will contain any binding values that were made to make the match
     */
    private function computeBotPattern($bot_id, $post)
    {
        $parent = $this->parent;
        $bot_model = $parent->model("bot");
        $total = 0;
        $patterns = $bot_model->getRows(0, C\MAX_BOT_PATTERNS,
            $total, [], [$bot_id]);
        if (empty($patterns)) {
            return [];
        }
        $post = preg_replace("/" . C\PUNCT . "/", " ", $post);
        $post = trim(preg_replace("/\s+/mu", " ", $post));
        foreach ($patterns as $pattern) {
            $request = $pattern['REQUEST'];
            $num_vars = preg_match_all('/\$(\w+)/', $request, $var_matches);
            $request = preg_replace('/\$\w+/', "dzqqzd", $request);
            $request = preg_replace("/" . C\PUNCT . "/", " ", $request);
            $request = trim(preg_replace('/\s+/mu', " ", $request));
            $request = preg_quote($request, "/");
            $request = preg_replace('/dzqqzd/', "(.+)", $request);
            $num_matches = preg_match("/$request/iu", $post, $matches);
            if ($num_matches > 0) {
                array_shift($matches);
                $bot_variables = array_combine($var_matches[1], $matches);
                if (!empty($_SESSION['USER_NAME'])) {
                    $bot_variables['USER_NAME'] = $_SESSION['USER_NAME'];
                }
                $state = $this->interpolateBotVariables(
                    $pattern['TRIGGER_STATE'], $bot_variables);
                if ($_SESSION["CHAT_BOT_STATES"][$bot_id] == $state) {
                    $pattern['VARS'] = $bot_variables;
                    return $pattern;
                }
            }
        }
        return [];
    }
    /**
     * Given a string $to_interpolate with variables in it (strings of word
     * characters beginning with a $) and given an array of variable =>
     * value, replaces the variables in $to_inpolate with their corresponding
     * value, returning the resulting string
     *
     * @param string $to_interpolate string to replace variables in
     * @param array $bot_variables sequence of variable => value pairs to
     *      replace in string.
     * @return string $to_interpolate after substitutions have been made
     */
    private function interpolateBotVariables($to_interpolate, $bot_variables)
    {
        foreach ($bot_variables as $var => $value) {
            $pattern = '/\$' . preg_quote($var, "/") . '/u';
            $to_interpolate = preg_replace($pattern, $value, $to_interpolate);
        }
        return $to_interpolate;
    }
    /**
     * Used to compute set up a list of feed items to be displayed by the
     * groupFeeds activity
     *
     * @param array &$data associative array of values to be echoed by the view
     *      this method might add to INCLUDE_SCRIPT formatting scripts such as
     *      for math which might be used to help draw feed items
     * @param array $pages contains feed items corresponding to first join
     *      dates to various groups. Other feed items will be added to this
     *      array
     * @param int $user_id id of user requesting thread info
     * @param array $search_array associative array used to determine where
     *      clause of what threads, groups, or user posts to get feed items for
     * @param int $for_group
     * @param string $sort either ksort or krsort to specify final sort
     *      direction of feed items
     * @param int $limit index of first feed item out of all applicable items
     *      to display
     * @param int $results_per_page number of feed items to display feed data
     *      for
     */
    private function initializeFeedItems(&$data, $pages, $user_id,
        $search_array, $for_group, $sort, &$limit, $results_per_page)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $impression_model = $parent->model("impression");
        $user_model = $parent->model("user");
        $item_count = $group_model->getGroupItemCount($search_array, $user_id,
            $for_group);
        $updatable = false;
        if (!empty($data["JUST_THREAD"]) && $data["JUST_THREAD"] >= 0) {
            $display_message = $_SESSION['DISPLAY_MESSAGE'] ?? "";
            $is_wiki = !empty($data["HEAD"]['page_type']) &&
                $data["HEAD"]['page_type'] == 'page_and_feedback';
            if (!$is_wiki &&
                $display_message == tl('social_component_comment_added')) {
                $limit = floor($item_count / $results_per_page) *
                    $results_per_page;
            }
            if ($limit > $item_count - $results_per_page) {
                $updatable = true;
            }
        }
        $group_items = $group_model->getGroupItems(0,
            $limit + $results_per_page, $search_array, $user_id, $for_group);
        $recent_found = false;
        $time = time();
        $j = 0;
        $parser = new WikiParser("", [], true);
        $locale_tag = L\getLocaleTag();
        $page = false;
        $math = false;
        $csrf_token = C\CSRF_TOKEN . "=" . $this->parent->generateCSRFToken(
            $user_id);
        foreach ($group_items as $item) {
            $page = $item;
            if (C\DIFFERENTIAL_PRIVACY && !empty($page['NUM_VIEWS'])) {
                /* Recalculate fuzzy view only if NUM_VIEWS
                   has been updated since last calculation
                 */
                if (empty($page['TMP_NUM_VIEWS']) ||
                    ($page['NUM_VIEWS'] != $page['TMP_NUM_VIEWS'])) {
                    // fuzzify the number of views to add privacy
                    $fuzzy_views =
                        $parent->addDifferentialPrivacy($page['NUM_VIEWS']);
                    $impression_model->updatePrivacyViews($page['ID'],
                        $page['NUM_VIEWS'], $fuzzy_views);
                    $page['NUM_VIEWS'] = $fuzzy_views;
                } else {
                    $page['NUM_VIEWS'] = $page['FUZZY_NUM_VIEWS'];
                }
            }
            $page['USER_ICON'] = $user_model->getUserIconUrl($page['USER_ID']);
            $page[self::TITLE] = $page['TITLE'];
            unset($page['TITLE']);
            $description = $page['DESCRIPTION'];
            //start code for sharing crawl mixes
            preg_match_all("/\[\[([^\:\n]+)\:mix(\d+)\]\]/", $description,
                $matches);
            $num_matches = count($matches[0]);
            for ($i = 0; $i < $num_matches; $i++) {
                $match = preg_quote($matches[0][$i], "@");
                $match = str_replace("@","\@", $match);
                $replace = "<a href='?c=admin&amp;a=mixCrawls" .
                    "&amp;arg=importmix&amp;".C\CSRF_TOKEN."=".
                    $parent->generateCSRFToken($user_id).
                    "&amp;timestamp={$matches[2][$i]}'>".
                    $matches[1][$i]."</a>";
                $description = preg_replace("@".$match."@u", $replace,
                    $description);
                $page["NO_EDIT"] = true;
            }
            //end code for sharing crawl mixes
            $page[self::DESCRIPTION] = $parser->parse($description);
            $page[self::DESCRIPTION] =
                $group_model->insertResourcesParsePage($item['GROUP_ID'],
                 "post" . $item['ID'],
                $locale_tag, $page[self::DESCRIPTION]);
            $page[self::DESCRIPTION] = preg_replace('/\[{token}\]/',
                $csrf_token, $page[self::DESCRIPTION]);
            if (!$math && strpos($page[self::DESCRIPTION], "`") !== false) {
                $math = true;
                if (!isset($data["INCLUDE_SCRIPTS"])) {
                    $data["INCLUDE_SCRIPTS"] = [];
                }
                $data["INCLUDE_SCRIPTS"][] = "math";
            }
            unset($page['DESCRIPTION']);
            $page['OLD_DESCRIPTION'] = $description;
            $page[self::SOURCE_NAME] = $page['GROUP_NAME'];
            unset($page['GROUP_NAME']);
            if ($item['OWNER_ID'] == $user_id || $user_id == C\ROOT_ID) {
                $page['MEMBER_ACCESS'] = C\GROUP_READ_WIKI;
            }
            if ($updatable &&
                !$recent_found && !$math && $time - $item["PUBDATE"] <
                5 * C\ONE_MINUTE) {
                $recent_found = true;
                $data['SCRIPT'] .= 'doUpdate();';
            }
            $pages[$item["PUBDATE"] . sprintf("%04d", $j)] = $page;
            $j++;
        }
        if ($pages) {
            $sort($pages);
            $pages = array_slice($pages, $limit, $results_per_page);
        }
        return [$item_count, $pages];
    }
    /**
     * Used to add to $data information about the most recently view threads
     * and groups of the current user. This will be used to populate the
     * navigation dropdown in WikiView or WikiElement
     *
     * @param array &$data associative array of values to be echoed by the view
     * @param int $user_id id of user requesting thread info
     */
    public function calculateRecentFeedsAndThread(&$data, $user_id)
    {
        if ($user_id == C\PUBLIC_USER_ID) {
            return;
        }
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $thread_ids = $parent->model("impression")->recent($user_id,
            C\THREAD_IMPRESSION, 5);
        $group_ids = $parent->model("impression")->recent($user_id,
            C\GROUP_IMPRESSION, 5);
        if (!empty($thread_ids)) {
            $data['RECENT_THREADS'] = [];
            foreach ($thread_ids as $recent_thread_id) {
                $thread_start_item = $group_model->getGroupItem(
                    $recent_thread_id);
                $thread_name = $thread_start_item['TITLE'] ?? "";
                if (!empty($thread_name) &&
                    (empty($data['JUST_THREAD']) ||
                    $recent_thread_id != $data['JUST_THREAD'])) {
                    $data['RECENT_THREADS'][$thread_name] =
                        htmlentities(B\feedsUrl("thread", $recent_thread_id,
                        true,  $data['CONTROLLER']));
                }
            }
        }
        if (!empty($group_ids)) {
            $data['RECENT_GROUPS'] = [];
            $thread_group_id = (empty($data["JUST_GROUP_ID"])) ?
                (empty($data["GROUP_ID"]) ? -1 : $data["GROUP_ID"] ) :
                $data["JUST_GROUP_ID"];
            foreach ($group_ids as $recent_group_id) {
                $group_name = $group_model->getGroupName(
                    $recent_group_id);
                if (!empty($group_name) && !empty($thread_group_id) &&
                    ($recent_group_id != $thread_group_id)) {
                    $data['RECENT_GROUPS'][$group_name] =
                        htmlentities(B\feedsUrl("group",  $recent_group_id,
                        false,  $data['CONTROLLER']));
                }
            }
        }
    }
    /**
     * Used to handle file uploads either to message posts or wiki pages
     *
     * @param string $group_id the group the message or wiki page is associated
     *      with
     * @param string $store_id the id of the message post or wiki page
     * @param string $sub_path used to specify sub-folder of default resource
     *      folder to copy to
     */
    public function handleResourceUploads($group_id, $store_id, $sub_path = "")
    {
        if (!isset($_FILES) || !is_array($_FILES)) {
            return self::UPLOAD_NO_FILES;
        }
        $keys = array_keys($_FILES);
        if (!isset($keys[0])) {
            return self::UPLOAD_NO_FILES;
        }
        $upload_field = $keys[0];
        $parent = $this->parent;
        $group_model = $parent->model("group");
        if (!isset($_FILES[$upload_field]['name'])) {
            return self::UPLOAD_NO_FILES;
        }
        $upload_parts = ['name', 'type', 'tmp_name', 'data'];
        $is_file_array = false;
        $num_files = 1;
        if (is_array($_FILES[$upload_field]['name'])) {
            $num_files =
                count($_FILES[$upload_field]['name']);
            $is_file_array = true;
        }
        $files = [];
        $upload_okay = true;
        for ($i = 0; $i < $num_files; $i ++) {
            foreach ($upload_parts as $part) {
                $file_part = ($is_file_array && isset(
                    $_FILES[$upload_field][$part][$i])) ?
                    $_FILES[$upload_field][$part][$i] :
                    ((!$is_file_array && isset(
                    $_FILES[$upload_field][$part])) ?
                    $_FILES[$upload_field][$part] :
                    false );
                if ($part == 'data') {
                    $files[$i][$part] = (empty($file_part) ) ? "" :
                        $file_part;
                    continue;
                }
                if ($file_part) {
                    $files[$i][$part] = $parent->clean(
                        $file_part, 'string');
                } else {
                    $upload_okay = false;
                    break 2;
                }
            }
        }
        if ($upload_okay) {
            foreach ($files as $file) {
                $group_model->copyFileToGroupPageResource(
                    $file['tmp_name'], $file['name'], $file['type'],
                    $group_id, $store_id, $sub_path, $file['data']);
            }
        }
        if (!$upload_okay) {
            return self::UPLOAD_FAILED;
        }
        return self::UPLOAD_SUCCESS;
    }
    /**
     * Used to set up GroupfeedView to draw a users group feeds grouped
     * by group names as opposed to as a linear list of thread and post
     * titles
     *
     * @param int $user_id id of current user
     * @param int $limit lower bound on the groups to display feed data for
     * @param int $results_per_page number of groups to display feed data
     *      for
     * @param string $controller_name name of controller on which this
     *      this component lives (either admin or group). Used by
     *      view to draw expand or collapse link
     * @param array $data field data for view to draw itself
     */
    public function calculateGroupedFeeds($user_id, $limit, $results_per_page,
        $controller_name, $data)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['MODE'] = 'grouped';
        $data['group_sorts'] = [ "name_asc" =>
            html_entity_decode(tl('social_component_name_asc')),
            "name_desc" => html_entity_decode(tl('social_component_name_desc')),
            "join_asc" => html_entity_decode(tl('social_component_join_asc')),
            "join_desc" => html_entity_decode(tl('social_component_join_desc')),
        ];
        $data['GROUP_SORT'] = (!empty($_REQUEST['group_sort']) &&
            isset($data['group_sorts'][$_REQUEST['group_sort']])) ?
            $_REQUEST['group_sort'] : "join_desc";
        $data["GROUP_FILTER"] = (empty($_REQUEST['group_filter'])) ?
            "" : $parent->clean($_REQUEST['group_filter'], "string");
        $search_array = [];
        if ($data["GROUP_FILTER"]) {
            $name_clause = ["name", "CONTAINS", $data["GROUP_FILTER"]];
        } else {
            $name_clause = ["name", "", ""];
        }
        $name_clause[3] = ($data['GROUP_SORT'] == "name_asc") ?
            "ASC" : ($data['GROUP_SORT'] == "name_desc" ? "DESC" : "");
        if ($name_clause != ["name", "", "", ""]) {
            $search_array[] = $name_clause;
        }
        $join_clause = ["join_date", "", ""];
        $join_clause[3] = ($data['GROUP_SORT'] == "join_asc") ?
            "ASC" : ($data['GROUP_SORT'] == "join_desc" ? "DESC" : "");
        if ($join_clause != ["join_date", "", "", ""]) {
            $search_array[] = $join_clause;
        }
        $data['GROUPS'] = $group_model->getRows($limit, $results_per_page,
            $data['NUM_GROUPS'], $search_array, [$user_id, false]);
        $num_shown = count($data['GROUPS']);
        for ($i = 0; $i < $num_shown; $i++) {
            $group_id = $data['GROUPS'][$i]['GROUP_ID'];
            $item = $group_model->getMostRecentGroupPost($group_id);
            $data['GROUPS'][$i]['NUM_POSTS'] = $group_model->getGroupPostCount(
                $group_id);
            $data['GROUPS'][$i]['NUM_THREADS']=
                $group_model->getGroupThreadCount($group_id);
            $data['GROUPS'][$i]['NUM_PAGES'] = $group_model->getGroupPageCount(
                $data['GROUPS'][$i]['GROUP_ID']);
            if (isset($item['TITLE'])) {
                $data['GROUPS'][$i]["ITEM_TITLE"] = $item['TITLE'];
                $data['GROUPS'][$i]["THREAD_ID"] = $item['PARENT_ID'];
            } else {
                $data['GROUPS'][$i]["ITEM_TITLE"] =
                    tl('social_component_no_posts_yet');
                $data['GROUPS'][$i]["THREAD_ID"] = -1;
            }
        }
        $data['NUM_SHOWN'] = $num_shown;
        $data['LIMIT'] = $limit;
        $data['RESULTS_PER_PAGE'] = $results_per_page;
        $data['PAGING_QUERY'] = B\feedsUrl("", "",
            true, $controller_name);
        return $data;
    }
    /**
     * Handles requests to reading, editing, viewing history, reverting, etc
     * wiki pages
     * @return array $data an associative array of form variables used to draw
     *     the appropriate wiki page
     */
    public function wiki()
    {
        $parent = $this->parent;
        $controller_name =
            (get_class($parent) == C\NS_CONTROLLERS . "AdminController") ?
                "admin" : "group";
        $base_url = C\SHORT_BASE_URL;
        list($data, $sub_path, $additional_substitutions, $clean_array,
            $strings_array, $page_defaults) = $this->initCommonWikiArrays(
                $controller_name, $base_url);
        $group_model = $parent->model("group");
        if (isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = C\PUBLIC_USER_ID;
        }
        $last_care_missing = 2;
        $missing_fields = false;
        $i = 0;
        if ($user_id == C\PUBLIC_USER_ID) {
            $_SESSION['LAST_ACTIVITY']['a'] = 'wiki';
            $_SESSION['LAST_ACTIVITY']['c'] = $controller_name;
        } else {
            unset($_SESSION['LAST_ACTIVITY']);
        }
        $missings = [];
        foreach ($clean_array as $field => $type) {
            if (isset($_REQUEST[$field])) {
                if ($field == 'page' && is_array($_REQUEST[$field])) {
                    $tmp = [];
                    foreach ($_REQUEST[$field]  as $key => $value) {
                        $key = $parent->clean($key, "string");
                        $value =  $parent->clean($value, "string");
                        $tmp[substr($key, 0, C\TITLE_LEN)] =
                            substr($value, 0, C\MAX_GROUP_PAGE_LEN);
                    }
                } else {
                    $tmp = $parent->clean($_REQUEST[$field], $type);
                }
                if (isset($strings_array[$field]) &&
                    !is_array($tmp)) {
                    $tmp = substr($tmp, 0, $strings_array[$field]);
                }
                if ($field == "page_name") {
                    $tmp = str_replace(" ", "_", $tmp);
                }
                if ($field == "group_name") {
                    $pre_id = $group_model->getGroupId($tmp);
                    if ($pre_id > 0) {
                        $group_id = $pre_id;
                        unset($missings[$field]);
                        if (empty($missings)) {
                            $missing_fields = false;
                        }
                    }
                }
                $$field = $tmp;
                if ($user_id == C\PUBLIC_USER_ID) {
                    $_SESSION['LAST_ACTIVITY'][$field] = $tmp;
                }
            } else if ($i < $last_care_missing) {
                $$field = false;
                $missing_fields = true;
                $missings[$field] = true;
            }
            $i++;
        }
        $data['RESOURCE_FILTER']  = (isset($resource_filter)) ?
            $resource_filter : "";
        $data['OPEN_IN_TABS'] = empty($_SESSION['OPEN_IN_TABS']) ? false :
            true;
        if (!empty($group_id)) {
        } else if (!empty($page_id)) {
            $page_info = $group_model->getPageInfoByPageId($page_id);
            if (isset($page_info["GROUP_ID"])) {
                $group_id = $page_info["GROUP_ID"];
                unset($page_info);
            } else {
                $group_id = C\PUBLIC_GROUP_ID;
            }
        } else {
            $group_id = C\PUBLIC_GROUP_ID;
        }
        $group = $group_model->getGroupById($group_id, $user_id);
        if (!$group || !isset($group["OWNER_ID"])) {
            if ($data['MODE'] !== 'api') {
                if ($user_id == C\PUBLIC_USER_ID) {
                    $_REQUEST = ['c' => "admin", 'a' => '', C\CSRF_TOKEN => ''];
                    return $parent->redirectWithMessage(
                        tl("social_component_login_first"));
                }
                unset($_REQUEST["route"]);
                $_REQUEST['group_id'] = C\PUBLIC_GROUP_ID;
                return $parent->redirectWithMessage(
                    tl("social_component_no_group_access"), false, false,
                    true);
            } else {
                $data['errors'] =  [];
                $data['errors'][] = tl("social_component_no_group_access");
            }
            $group_id = C\PUBLIC_GROUP_ID;
            $group = $group_model->getGroupById($group_id, $user_id);
        } else {
            if ($group["OWNER_ID"] == $user_id ||
                ($group["STATUS"] == C\ACTIVE_STATUS &&
                $group["MEMBER_ACCESS"] == C\GROUP_READ_WIKI)) {
                $data["CAN_EDIT"] = true;
            }
        }
        if ($group_id == C\PUBLIC_GROUP_ID) {
            $read_address = "[{controller_and_page}]";
        } else {
            $read_address = htmlentities(B\wikiUrl("", true, '[{controller}]',
                $group_id)) . "[{token}]&amp;page_name=";
        }
        if (isset($_REQUEST["arg"])) {
            switch ($_REQUEST["arg"]) {
                case "edit":
                    $page_id = isset($page_id) ? $page_id : null;
                    $page_name = isset($page_name) ? $page_name : null;
                    $page = isset($page) ? $page : null;
                    $edit_reason = isset($edit_reason) ? $edit_reason: null;
                    $this->editWiki($data, $user_id, $group_id, $group,
                        $page_id, $page_name, $page, $page_defaults, $sub_path,
                        $edit_reason, $missing_fields, $read_address,
                        $additional_substitutions);
                    break;
                case "history":
                    if (!isset($page_id) || !$page_id) {
                        break;
                    }
                    $data["MODE"] = "history";
                    $data["PAGE_NAME"] = "history";
                    $limit = isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"]) &&
                        $_SESSION["MAX_PAGES_TO_SHOW"] > 0) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       C\DEFAULT_ADMIN_PAGING_NUM;
                    $default_history = true;
                    if (isset($show)) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $show);
                        if ($page_info) {
                            $data["MODE"] = "show";
                            $default_history = false;
                            $data["PAGE_NAME"] = $page_info["PAGE_NAME"];
                            $parser = new WikiParser($read_address,
                                $additional_substitutions);
                            $parsed_page = $parser->parse($page_info["PAGE"]);
                            $data["PAGE_ID"] = $page_id;
                            $data[C\CSRF_TOKEN] =
                                $parent->generateCSRFToken($user_id);
                            $history_link = "?c={$data['CONTROLLER']}&amp;".
                                "a=wiki&amp;". C\CSRF_TOKEN.'='.
                                $data[C\CSRF_TOKEN].
                                '&amp;arg=history&amp;page_id='.
                                $data['PAGE_ID'];
                            $data["PAGE"] =
                                "<div>&nbsp;</div>".
                                "<div class='black-box back-dark-gray'>".
                                "<div class='float-opposite'>".
                                "<a href='$history_link'>".
                                tl("social_component_back") . "</a></div>".
                                tl("social_component_history_page",
                                $data["PAGE_NAME"], date("c", $show)) .
                                "</div>" . $parsed_page;
                            $data["DISCUSS_THREAD"] =
                                $page_info["DISCUSS_THREAD"];
                        }
                    } else if (!empty($diff) &&
                        isset($diff1) && isset($diff2)) {
                        $page_info1 = $group_model->getHistoryPage(
                            $page_id, $diff1);
                        $page_info2 = $group_model->getHistoryPage(
                            $page_id, $diff2);
                        $data["MODE"] = "diff";
                        $default_history = false;
                        $data["PAGE_NAME"] = $page_info2["PAGE_NAME"];
                        $data["PAGE_ID"] = $page_id;
                        $data[C\CSRF_TOKEN] =
                            $parent->generateCSRFToken($user_id);
                        $history_link = htmlentities(B\controllerUrl(
                            $data['CONTROLLER'],true)) .
                            "a=wiki&amp;".C\CSRF_TOKEN.'='.
                            $data[C\CSRF_TOKEN].
                            '&amp;arg=history&amp;page_id='.
                            $data['PAGE_ID'];
                        $out_diff = "<div>+++ {$data["PAGE_NAME"]}\t".
                            "''$diff1''\n";
                        $out_diff .= "<div>--- {$data["PAGE_NAME"]}\t".
                            "''$diff2''\n";
                        $out_diff .= L\diff($page_info2["PAGE"],
                            $page_info1["PAGE"], true);
                        $data["PAGE"] =
                            "<div>&nbsp;</div>".
                            "<div class='black-box back-dark-gray'>".
                            "<div class='float-opposite'>".
                            "<a href='$history_link'>".
                            tl("social_component_back") . "</a></div>".
                            tl("social_component_diff_page",
                            $data["PAGE_NAME"], date("c", $diff1),
                            date("c", $diff2)) .
                            "</div>" . "$out_diff";
                    } else if (isset($revert) && $data["CAN_EDIT"]) {
                        $page_info = $group_model->getHistoryPage(
                            $page_id, $revert);
                        if ($page_info) {
                            $action = "wikiupdate_".
                                "group=".$group_id."&page=" .
                                $page_info["PAGE_NAME"];
                            if (!$parent->checkCSRFTime(C\CSRF_TOKEN,
                                $action)) {
                                $data['SCRIPT'] .=
                                    "doMessage('<h1 class=\"red\" >".
                                    tl('social_component_wiki_edited_elsewhere')
                                    . "</h1>');";
                                break;
                            }
                            $group_model->revertResources($page_id, $group_id,
                                $revert);
                            $group_model->setPageName($user_id,
                                $group_id, $page_info["PAGE_NAME"],
                                $page_info["PAGE"],
                                $data['CURRENT_LOCALE_TAG'],
                                tl('social_component_page_revert_to',
                                date('c', $revert)), "", "", $read_address,
                                $additional_substitutions);
                            return $parent->redirectWithMessage(
                                tl("social_component_page_reverted"),
                                ['arg', 'page_name', 'page_id']);
                        } else {
                            return $parent->redirectWithMessage(
                                tl("social_component_revert_error"),
                                ['arg', 'page_name', 'page_id']);
                        }
                    }
                    if (empty($data["DISCUSS_THREAD"])) {
                        $page_info = $group_model->getPageInfoByPageId(
                            $page_id);
                        $data["DISCUSS_THREAD"] =
                            empty($page_info["DISCUSS_THREAD"]) ? -1 :
                            $page_info["DISCUSS_THREAD"];
                    }
                    if ($default_history) {
                        $data["LIMIT"] = $limit;
                        $data["RESULTS_PER_PAGE"] = $num;
                        list($data["TOTAL_ROWS"], $data["PAGE_NAME"],
                            $data["HISTORY"]) =
                            $group_model->getPageHistoryList($page_id, $limit,
                            $num);
                        if ((!isset($diff1) || !isset($diff2))) {
                            $data['diff1'] = $data["HISTORY"][0]["PUBDATE"]
                                ?? 0;
                            $data['diff2'] = $data["HISTORY"][0]["PUBDATE"]
                                ?? 0;
                            if (count($data["HISTORY"]) > 1) {
                                $data['diff2'] = $data["HISTORY"][1]["PUBDATE"];
                            }
                        }
                    }
                    $data['page_id'] = $page_id;
                    break;
                case "media":
                    $this->mediaWiki($data, $group_id, $page_id, $sub_path);
                    break;
                case "pages":
                    $data["MODE"] = "pages";
                    $limit = isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"]) &&
                        $_SESSION["MAX_PAGES_TO_SHOW"] > 0) ?
                       $_SESSION["MAX_PAGES_TO_SHOW"] :
                       C\DEFAULT_ADMIN_PAGING_NUM;
                    $filter = (empty($filter)) ? "" : $filter;
                    if (isset($page_name)) {
                        $data['PAGE_NAME'] = $page_name;
                    }
                    $data["LIMIT"] = $limit;
                    $data["RESULTS_PER_PAGE"] = $num;
                    $data["FILTER"] = preg_replace("/\s+/u", "_", $filter);
                    $search_page_info = false;
                    if ($filter != "") {
                        $search_page_info = $group_model->getPageInfoByName(
                            $group_id, $filter, $data['CURRENT_LOCALE_TAG'],
                            "read");
                    }
                    if (!$search_page_info) {
                        list($data["TOTAL_ROWS"], $data["PAGES"]) =
                            $group_model->getPageList(
                            $group_id, $data['CURRENT_LOCALE_TAG'], $filter,
                            $limit, $num);
                        if ($data["TOTAL_ROWS"] == 0 && $filter != "") {
                            $data["MODE"] = "read";
                            $page_name = $data["FILTER"];
                        }
                    } else {
                        $data["MODE"] = "read";
                        $page_name = $data["FILTER"];
                    }
                    break;
                case 'relationships':
                    $data["MODE"] = "relationships";
                    $data["PAGE_NAME"] = "related";
                    if (empty($page_id)) {
                        break;
                    }
                    $page_info = $group_model->getPageInfoByPageId(
                        $page_id);
                    if (!isset($page_name)) {
                        $page_name = empty($page_info['PAGE_NAME']) ? "links" :
                            $page_info['PAGE_NAME'];
                    }
                    $limit = isset($limit) ? $limit : 0;
                    $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"]) &&
                        $_SESSION["MAX_PAGES_TO_SHOW"] > 0) ?
                        $_SESSION["MAX_PAGES_TO_SHOW"] :
                        C\DEFAULT_ADMIN_PAGING_NUM;
                    $data["PAGE_ID"] = $page_id;
                    $data["PAGE_NAME"] = $page_name;
                    $data["DISCUSS_THREAD"] = empty($page_info["DISCUSS_THREAD"]
                        ) ? -1 : $page_info['DISCUSS_THREAD'];
                    $data["GROUP_ID"] = $page_info["GROUP_ID"];
                    $data["LIMIT"] = $limit;
                    $data["RESULTS_PER_PAGE"] = $num;
                    list($data["TOTAL_ROWS"], $data["RELATIONSHIPS"]) =
                        $group_model->getRelationshipsToFromPage($page_id,
                        $limit, $num);
                    //only one relationship so select
                    if (count($data["RELATIONSHIPS"]) == 1) {
                        $current = current($data["RELATIONSHIPS"]);
                        $_REQUEST["reltype"] =
                            $current["RELATIONSHIP_TYPE"];
                    }
                    if (isset($_REQUEST["reltype"])) {
                        $rel_type = $parent->clean($_REQUEST["reltype"],
                            "string");
                        $data["REL-TYPE"] = $rel_type;
                        $data["GROUP_ID"] = $group_id;
                        //clean up
                        if (!empty($page_id)) {
                            $page_info = $group_model->getPageInfoByPageId(
                                $page_id);
                            if (!isset($page_name)) {
                                $page_name = empty($page_info['PAGE_NAME'])
                                    ? "rel-types" : $page_info['PAGE_NAME'];
                            }
                            $limit = isset($limit) ? $limit : 0;
                            $num = (isset($_SESSION["MAX_PAGES_TO_SHOW"]) &&
                                $_SESSION["MAX_PAGES_TO_SHOW"] > 0) ?
                                $_SESSION["MAX_PAGES_TO_SHOW"] :
                                C\DEFAULT_ADMIN_PAGING_NUM;
                            $data["PAGE_ID"] = $page_id;
                            $data["PAGE_NAME"] = $page_name;
                            $data["DISCUSS_THREAD"] =
                                empty($page_info["DISCUSS_THREAD"] ) ? -1 :
                                $page_info['DISCUSS_THREAD'];
                            $data["GROUP_ID"] = $page_info["GROUP_ID"];
                            $data["LIMIT"] = $limit;
                            $data["RESULTS_PER_PAGE"] = $num;
                            list($data["TOTAL_TO_PAGES"],
                                $data["PAGES_THAT_LINK_TO"],
                                $data["TOTAL_FROM_PAGES"],
                                $data["PAGES_THAT_LINK_FROM"]) =
                                $group_model->pagesLinkedWithRelationship(
                                    $page_id, $data["GROUP_ID"],
                                    $data["PAGE_NAME"], $rel_type, $limit,$num);
                        }
                    }
                    break;
                case 'source':
                    if (isset($_REQUEST['caret']) &&
                       isset($_REQUEST['scroll_top'])
                            && !isset($page)) {
                        $caret = $parent->clean($_REQUEST['caret'],
                            'int');
                        $scroll_top = $parent->clean($_REQUEST['scroll_top'],
                            'int');
                        $data['SCRIPT'] .= "wiki = elt('wiki-page');".
                            "if (wiki.setSelectionRange) { " .
                            "   wiki.focus();" .
                            "   wiki.setSelectionRange($caret, $caret);".
                            "} ".
                            "wiki.scrollTop = $scroll_top;";
                    }
                    $data["MODE"] = "source";
                    $page_info = $group_model->getPageInfoByName($group_id,
                        $page_name, $data['CURRENT_LOCALE_TAG'], 'resources');
                    /* if page not yet created than $page_info will be null
                       so in the below $page_info['ID'] won't be set.
                     */
                    if (isset($page_info['ID'])) {
                        $data['RESOURCES_INFO'] =
                            $group_model->getGroupPageResourceUrls($group_id,
                            $page_info['ID'], $sub_path);
                    } else {
                        $data['RESOURCES_INFO'] = [];
                    }
                    break;
            }
        }
        if (!$page_name) {
            $page_name = tl('social_component_main');
        }
        $data["GROUP"] = $group;
        if (in_array($data["MODE"], ["api", "read", "edit", "media",
            "source"])) {
            // history action might set page, otherwise...
            if (empty($data["PAGE"]) && empty($data['RESOURCE_NAME'])) {
                $data["PAGE_NAME"] = $page_name;
                if (!empty($search_page_info)) {
                    $page_info = $search_page_info;
                } else {
                    $page_info = $group_model->getPageInfoByName($group_id,
                        $page_name, $data['CURRENT_LOCALE_TAG'], $data["MODE"]);
                }
                $data["PAGE"] = $page_info["PAGE"] ?? "";
                $data["PAGE_ID"] = $page_info["ID"] ?? "";
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"] ?? "";
            }
            if (empty($data["PAGE"]) &&
                $data['CURRENT_LOCALE_TAG'] != C\DEFAULT_LOCALE) {
                //fallback to default locale for translation
                $page_info = $group_model->getPageInfoByName(
                    $group_id, $page_name, C\DEFAULT_LOCALE, $data["MODE"]);
                $data["PAGE"] = $page_info["PAGE"] ?? "";
                $data["PAGE_ID"] = $page_info["ID"] ?? "" ;
                $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"] ?? "";
            }
            $view = $parent->view($data['VIEW']);
            $parent->parsePageHeadVarsView($view, $data["PAGE_ID"],
                $data["PAGE"]);
            if ($data['MODE'] == "read" || empty($_REQUEST['n'])) {
                $data["PAGE"] = $view->page_objects[$data["PAGE_ID"]];
            }
            $data["HEAD"] = $view->head_objects[$data["PAGE_ID"]];
            if (isset($data["HEAD"]['page_type']) &&
                $data["HEAD"]['page_type'] == 'page_alias' &&
                $data["HEAD"]['page_alias'] != '' &&
                in_array($data['MODE'], ["read", 'api']) &&
                !isset($_REQUEST['noredirect']) ) {
                if ($data['MODE'] == 'api') {
                    $controller_name = "api";
                }
                return $parent->redirectLocation(B\wikiUrl(
                    $data["HEAD"]['page_alias'],
                    true, $controller_name, $group_id) . C\CSRF_TOKEN . '=' .
                    $parent->generateCSRFToken($user_id));
            }
            if ($data['MODE'] == "read") {
                $data['GROUP_STATUS'] = $group['STATUS'];
                $data['JUST_THREAD'] = true;
                $this->initializeReadMode($data, $user_id, $group_id,
                    $sub_path);
            } else if (in_array($data['MODE'], ['edit', 'source'])) {
                foreach ($page_defaults as $key => $default) {
                    $data[$key] = $default;
                    if (isset($data["HEAD"][$key])) {
                        $data[$key] = $data["HEAD"][$key];
                    }
                }
                $this->sortWikiResources($data);
                $data['settings'] = "false";
                if (!empty($data['RESOURCE_NAME'])) {
                $name_parts = pathinfo($data['RESOURCE_NAME']);
                if (!empty($name_parts['extension'])) {
                        switch ($name_parts['extension']) {
                            case 'csv':
                                $user_config = "";
                                if (!empty($_SESSION['USER_NAME'])) {
                                    $user_config .= ',user_name:'.
                                        json_encode($_SESSION['USER_NAME']);
                                }
                                $data['INCLUDE_SCRIPTS'][] = 'spreadsheet';
                                $data['SCRIPT'] .=
                                    'spreadsheet = new Spreadsheet(' .
                                    '"spreadsheet",' .
                                    $data["PAGE"] . ', {mode:"write"'.
                                    "$user_config});".
                                    'spreadsheet.draw();';
                                $data['SPREADSHEET'] = true;
                                break;
                        }
                    }
                }
                if (isset($_REQUEST['settings']) &&
                     $_REQUEST['settings']=='true') {
                    $data['settings'] = "true";
                }
                $data['current_page_type'] = $data["page_type"];
                $templates = $group_model->getTemplateMap($group_id,
                    $data['CURRENT_LOCALE_TAG']);
                /*
                    if the page id is not that of a template, then
                    we add the list of templates to the available
                    page_type page can be set to and we check
                    if page uses a template
                 */
                if (empty($templates["t" . $data["PAGE_ID"]])) {
                    $data['page_types'] = array_merge($data['page_types'],
                        $templates);
                    if (empty($_REQUEST['n']) &&
                        !empty($templates[$data['current_page_type']])) {
                        $template_name = $templates[$data['current_page_type']];
                        $template_info = $group_model->
                            getPageInfoByName($group_id, $template_name,
                            $data['CURRENT_LOCALE_TAG'], "read");
                        list( ,$tmp_page) = $parent->parsePageHeadVars(
                            $template_info['PAGE'], true);
                        $tmp_page = preg_replace("/{{text\|(.+?)\|(.+?)}}/",
                            "<input type='text' class='narrow-field'" .
                            " name='page[$1]' placeholder='$2'" .
                            " value='{{field|$1}}' />", $tmp_page);
                        $tmp_page = preg_replace("/{{area\|(.+?)\|(.+?)}}/",
                            "<textarea class='short-text-area'" .
                            " name='page[$1]' placeholder='$2'>" .
                            "{{field|$1}}</textarea>", $tmp_page);
                        if (empty($data['PAGE'])) {
                            $data['PAGE'] = preg_replace(
                                "/{{field\|(.+?)}}/", "", $tmp_page);
                        } else {
                            set_error_handler(null);
                            $page_data = @unserialize(base64_decode(
                                $data['PAGE']));
                            set_error_handler(C\NS_CONFIGS .
                                "yioop_error_handler");
                            if (is_array($page_data)) {
                                foreach ($page_data as
                                    $page_key => $page_value) {
                                    $tmp_page = preg_replace(
                                        "/{{field\|" .
                                        preg_quote($page_key, "/") .
                                        "}}/", $page_value, $tmp_page);
                                }
                            }
                            $data['PAGE'] = preg_replace(
                                "/{{field\|(.+?)}}/", "", $tmp_page);
                        }
                    }
                }
                $this->initializeWikiPageToggle($data);
                if (empty($data['RESOURCE_NAME'])) {
                    $this->initializeWikiEditor($data);
                }
            }
        }
        if (!empty($data['PAGE_ID'])) {
            $data['PAGE_HAS_RELATIONSHIPS'] =
                $group_model->countPageRelationships($data['PAGE_ID']);
        }
        $this->updateGetWikiImpressionInfo($data, $user_id, $group_id);
        return $data;
    }
    /**
     * Sets up view variables for wiki pages when in read mode. If
     * a user send a command to indicate a media resource on a media list
     * is not viewed, then also update session accordingly
     *
     * @param array &$data associative array of values to be echoed by the view
     * @param int $user_id id of user requesting a wiki page
     * @param int $group_id group in which wiki page belongs
     * @param string $sub_path any path within wiki page folder for resources
     */
    private function initializeReadMode(&$data, $user_id, $group_id, $sub_path)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        if (!empty($_REQUEST['clear']) && !empty($_SESSION['seen_media'])
            && is_array($_SESSION['seen_media'])) {
            $media_name = $parent->clean($_REQUEST['clear'], 'file_name');
            $type = UrlParser::getDocumentType($media_name);
            if ($type != "") {
                $media_name = UrlParser::getDocumentFilename($media_name);
                $media_name = urlencode($media_name);
                $media_name = "$media_name.$type";
            }
            $hash_id = L\crawlHash($data["PAGE_ID"] . $media_name . $sub_path);
            if (in_array($hash_id, $_SESSION['seen_media'])) {
                $_SESSION['seen_media'] = array_diff($_SESSION['seen_media'],
                    [$hash_id]);
                $parent->model("user")->setUserSession($user_id,
                    $_SESSION);
            }
        }
        if (isset($data["HEAD"]['page_header']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            $page_header = $group_model->getPageInfoByName($group_id,
                $data["HEAD"]['page_header'],
                $data['CURRENT_LOCALE_TAG'], $data["MODE"]);
            if (isset($page_header['PAGE'])) {
                $header_parts =
                    explode("END_HEAD_VARS", $page_header['PAGE']);
            }
            $data["PAGE_HEADER"] = (isset($header_parts[1])) ?
                $header_parts[1] : ($page_header['PAGE'] ?? "");
        }
        if (isset($data["HEAD"]['page_footer']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            $page_footer = $group_model->getPageInfoByName($group_id,
                $data["HEAD"]['page_footer'], $data['CURRENT_LOCALE_TAG'],
                $data["MODE"]);
            if (isset($page_footer['PAGE'])) {
                $footer_parts =
                    explode("END_HEAD_VARS", $page_footer['PAGE']);
            }
            $data['PAGE_FOOTER'] = (isset($footer_parts[1])) ?
                $footer_parts[1] : ($page_footer['PAGE'] ?? "");
        }
        if (!isset($data["INCLUDE_SCRIPTS"])) {
            $data["INCLUDE_SCRIPTS"] = [];
        }
        if (strpos($data["PAGE"], "`") !== false) {
            $data["INCLUDE_SCRIPTS"][] = "math";
        }
        if (strpos($data["PAGE"], "canvas-360") !== false) {
            $data["INCLUDE_SCRIPTS"] = array_merge($data["INCLUDE_SCRIPTS"],
                ["wglu-program", "vr-panorama", "vr-util"]);
            $data["SCRIPT"] .= ";var tl_elt = elt('tl'); tl_elt.enter_vr ='".
                tl('enter_vr') . "'; tl_elt.exit_vr = '".tl('exit_vr')."';";
        }
        if (preg_match("/\(\(resource(\-?[a-z]+)?\:(.+?)csv(.+?)\|(.+?)\)\)/ui",
            $data["PAGE"])) {
            $data["PAGE"] = $group_model->insertResourcesParsePage($group_id,
                 $data["PAGE_ID"], $data['CURRENT_LOCALE_TAG'], $data["PAGE"],
                 "", "admin", true);
        }
        if (stripos($data["PAGE"], "chart_data") !== false) {
            if (!in_array("chart", $data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"][] = "chart";
            }
            if (strpos($data["SCRIPT"], "new Chart") === false) {
                if ($_SERVER["MOBILE"]) {
                    $properties = ["width" => 340, "height" => 300,
                        "tick_font_size" => 8];
                } else {
                    $properties = ["width" => 700, "height" => 500];
                }
                $data['SCRIPT'] .= <<< 'EOD'
                for (var chart_elt in chart_data) {
                    var chart = new Chart(
                        'chart_' + chart_elt,
                        chart_data[chart_elt],
                        chart_config[chart_elt]);
                    chart.draw();
                }
EOD;
            }
        }
        if (strpos($data["PAGE"], "spreadsheet_data") !== false) {
            if (!in_array("spreadsheet", $data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"][] = "spreadsheet";
            }
            if (strpos($data["SCRIPT"], "new Spreadsheet") === false) {
                $data['SCRIPT'] .= <<< 'EOD'
                for (var spreadsheet_elt in spreadsheet_data) {
                    var spreadsheet = new Spreadsheet(
                        'spreadsheet_' + spreadsheet_elt,
                        spreadsheet_data[spreadsheet_elt],
                        spreadsheet_config[spreadsheet_elt]);
                    spreadsheet.draw();
                }
EOD;
            }
            $data['SPREADSHEET'] = true;
        }
        if (empty($data["HEAD"]['page_type'])) {
            return;
        }
        //handles template page types for read case
        if ($data["HEAD"]['page_type'][0] == 't' &&
            is_numeric(substr($data["HEAD"]['page_type'], 1))) {
            $templates = $group_model->getTemplateMap($group_id,
                $data['CURRENT_LOCALE_TAG']);
            if (empty($_REQUEST['n']) &&
                !empty($templates[$data["HEAD"]['page_type']])) {
                $template_name = $templates[$data["HEAD"]['page_type']];
                $template_info = $group_model->
                    getPageInfoByName($group_id, $template_name,
                    $data['CURRENT_LOCALE_TAG'], "read");
                list( ,$tmp_page) = $parent->parsePageHeadVars(
                    $template_info['PAGE'], true);
                $tmp_page = preg_replace("/{{(area|text)\|(.+?)\|(.+?)}}/",
                    "{{field|$2}}", $tmp_page);
                if (empty($data['PAGE'])) {
                    $data['PAGE'] = preg_replace(
                        "/{{field\|(.+?)}}/", "", $tmp_page);
                } else {
                    set_error_handler(null);
                    $page_data = @unserialize(base64_decode(substr(
                        $data['PAGE'], strlen('<div>'),
                        -strlen('</div>'))));
                    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
                    if (is_array($page_data)) {
                        foreach ($page_data as
                            $page_key => $page_value) {
                            $tmp_page = preg_replace(
                                "/{{field\|" . preg_quote($page_key, "/") .
                                "}}/", $page_value, $tmp_page);
                        }
                    }
                    $data['PAGE'] = preg_replace(
                        "/{{field\|(.+?)}}/", "", $tmp_page);
                }
            }
        } else if ($data["HEAD"]['page_type'] == 'page_and_feedback') {
            $just_thread = $data['DISCUSS_THREAD'];
            $thread_parent =
                $group_model->getGroupItem($just_thread);
            $edit_or_source = ($data["CAN_EDIT"]) ? "edit" : "source";
            $search_array = [
                ["parent_id", "=", $just_thread, ""],
                ["pub_date", "", "", "DESC"]];
            $limit = (!empty($_REQUEST['limit'])) ?
                $parent->clean($_REQUEST['limit'], 'int') : 0;
            $results_per_page =  (!empty($_REQUEST['num'])) ?
                $parent->clean($_REQUEST['num'], 'int') :
                C\NUM_RESULTS_PER_PAGE;
            list($item_count, $pages) = $this->initializeFeedItems($data, [],
                $user_id, $search_array, -2, "krsort",
                $limit, $results_per_page);
            if ($limit + count($pages) == $item_count) {
                $begin_page = array_pop($pages);
                $data["WIKI_MEMBER_ACCESS"] = $begin_page["MEMBER_ACCESS"];
                $data['WIKI_PARENT_ID'] = $data['DISCUSS_THREAD'];
                $data['WIKI_GROUP_ID'] = $group_id;
            }
            $item_count--;
            $data['TOTAL_ROWS'] = $item_count;
            if ($data['TOTAL_ROWS'] == 0) {
                $data['NO_POSTS_YET'] = true;
            }
            $data['INCLUDE_SCRIPTS'][] =  "wiki";
            $data['LIMIT'] = $limit;
            $data['RESULTS_PER_PAGE'] = $results_per_page;
            $data['PAGES'] = $pages;
            $data[C\CSRF_TOKEN] = $parent->generateCSRFToken($user_id);
            $data['PAGING_QUERY'] = htmlentities(B\wikiUrl($data['PAGE_NAME'],
                true, $data['CONTROLLER'], $group_id)) .
                C\CSRF_TOKEN . '='. $data[C\CSRF_TOKEN] .
                "&amp;page_type=page_and_feedback";
            $data['WIKI_FEED_BASE'] = C\BASE_URL . "?c=". $data['CONTROLLER'] .
                "&amp;a=groupFeeds&amp;just_thread=".$data['DISCUSS_THREAD'] .
                "&amp;". C\CSRF_TOKEN . '='. $data[C\CSRF_TOKEN] .
                "&amp;page_type=page_and_feedback&amp;page_name=" .
                $data['PAGE_NAME'];
            if ($data['VIEW'] != 'api') {
                $data['SCRIPT'] .= " let nextPage = initNextResultsPage(" .
                    "$limit, {$data['TOTAL_ROWS']}, $results_per_page, ".
                    "'{$data['PAGING_QUERY']}', '', " .
                    "'results-container', 'result-batch');\n";
            }
        } else if ($data["HEAD"]['page_type'] == 'media_list') {
            $data['RESOURCES_INFO'] =
                $group_model->getGroupPageResourceUrls($group_id,
                    $data['PAGE_ID'], $sub_path);
            $this->sortWikiResources($data);
        } else if ($data["HEAD"]['page_type'] == 'presentation' &&
            $data['CONTROLLER'] == 'group') {
            $data['page_type'] = 'presentation';
            $data['INCLUDE_SCRIPTS'][] =  "slidy";
            $data['INCLUDE_STYLES'][] =  "slidy";
        }
    }
    /**
     * Used to populate recent page and group activity dropdowns for a wiki
     * page and to update the recent page impressions so that this can be
     * calculated
     *
     * @param array &$data $data data to be sent to the view, will be modified
     *  according to impression info.
     * @param int $user_id id of the user requesting to change the given wiki
     *  page
     * @param int $group_id id of the group the wiki page belongs to
     */
    private function updateGetWikiImpressionInfo(&$data, $user_id, $group_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        if (!empty($data['PAGE_ID']) && $data['MODE'] != 'api') {
            $parent->model("impression")->add($user_id, $data['PAGE_ID'],
                C\WIKI_IMPRESSION);
            $parent->model("impression")->add($user_id, $group_id,
                C\GROUP_IMPRESSION);
        }
        if ($user_id != C\PUBLIC_USER_ID) {
            $page_ids = $parent->model("impression")->recent($user_id,
                C\WIKI_IMPRESSION, 5);
            if (!empty($page_ids)) {
                $data['RECENT_PAGES'] = [];
                foreach ($page_ids as $recent_page_id) {
                    $page_info = $group_model->getPageInfoByPageId(
                        $recent_page_id);
                    $group_name = empty($page_info['GROUP_ID']) ? "" :
                        $group_model->getGroupName($page_info['GROUP_ID']);
                    if (!empty($page_info) &&
                        ($page_info['PAGE_NAME'] != $data['PAGE_NAME'])) {
                        $data['RECENT_PAGES'][
                            $page_info['PAGE_NAME']. "@". $group_name] =
                            htmlentities(B\wikiUrl($page_info['PAGE_NAME'],
                            true, $data['CONTROLLER'],
                            $page_info['GROUP_ID']));
                        if ($data['MODE'] == 'edit') {
                            $data['RECENT_PAGES'][$page_info['PAGE_NAME']
                                 . "@" . $group_name] .=
                                "&amp;arg=edit&amp;";
                        }
                    }
                }
            }
            $group_ids = $parent->model("impression")->recent($user_id,
                C\GROUP_IMPRESSION, 5);
            if (!empty($group_ids)) {
                $data['RECENT_GROUPS'] = [];
                foreach ($group_ids as $recent_group_id) {
                    $group_name = $group_model->getGroupName(
                        $recent_group_id);
                    if (!empty($group_name) &&
                        ($recent_group_id != $group_id ||
                        $data['PAGE_NAME'] != 'Main')) {
                        $data['RECENT_GROUPS'][$group_name] =
                            htmlentities(B\wikiUrl("Main" , true,
                            $data['CONTROLLER'], $recent_group_id));
                        if ($data['MODE'] == 'edit') {
                            $data['RECENT_GROUPS'][$group_name] .=
                                "&amp;arg=edit&amp;";
                        }
                    }
                }
            }
        }
    }
    /**
     * Used to sort the resources on a wiki pages either for display in case
     * of reading a media list or to help find resources in the case of a
     * user using edit mode
     *
     * @param array &$data data to be sent to the view. The
     *  $data["RESOURCES_INFO"]['resources'] array of resources will be
     *  sorted according to the wiki page's settings as given in
     *  $data["HEAD"]['sort']
     */
    public function sortWikiResources(&$data)
    {
        if (empty($data["HEAD"]['sort']) ||
            empty($data["RESOURCES_INFO"]['resources'])) {
            return;
        }
        set_error_handler(null);
        $sort_map = @unserialize(L\webdecode($data["HEAD"]['sort']));
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        $sort_key = (empty($data['SUB_PATH'])) ? "." : $data['SUB_PATH'];
        $sort_key = rtrim($sort_key, '/');
        if (empty($sort_map[$sort_key])) {
            return;
        }
        $sort_field = substr($sort_map[$sort_key], 1);
        $callback = ($sort_map[$sort_key][0] == 'r') ?
            "rorderCallback" : "orderCallback";
        if ($sort_field == 'name') {
            $callback = ($sort_map[$sort_key][0] == 'r') ?
                "stringROrderCallback" : "stringOrderCallback";
        }
        $callback_name = C\NS_LIB . $callback;
        $callback_name(null, null, $sort_field);
        usort($data["RESOURCES_INFO"]["resources"], C\NS_LIB . $callback);
    }
    /**
     * Used to handle edit settings and resources actions for the wiki()
     * activity
     *
     * This method was pulled out of the giant switch case in wiki() and the
     * refactoring still needs some work. Hence, the awkward parameter list
     * below.
     *
     * @param array &$data $data data to be sent to the view, will be modified
     *  according to the edit action.
     * @param int $user_id id of the user requesting to change the given wiki
     *  page
     * @param int $group_id id of the group the wiki page belongs to
     * @param array $group associative array of info about the group wiki
     *  page belongs to
     * @param int $page_id if of wiki page being edited
     * @param string $page_name string name of wiki page being edited
     * @param string $page cleaned wiki page that came from $_REQUEST, if any
     * @param array $page_defaults associative aray system-wide defaults
     *  for page settings of any wiki page
     * @param string $sub_path sub resource folder being edited of wiki page, if
     *  any
     * @param string $edit_reason reason for performing update on wiki page
     * @param array $missing_fields fields missing from the request that might
     *  be needed to perform edit
     * @param string $read_address url base addressed to use
     *  in performing some wiki substitutions to generate a html page from a
     *  wiki page.
     * @param array $additional_substitutions additional preg_replace
     *  substitutions to make in going from wiki page to html
     */
    private function editWiki(&$data, $user_id, $group_id, $group, $page_id,
        $page_name, $page, $page_defaults, $sub_path, $edit_reason,
        $missing_fields, $read_address, $additional_substitutions)
    {
        if (!$data["CAN_EDIT"]) {
            return;
        }
        $parent = $this->parent;
        $group_model = $parent->model("group");
        if (isset($_REQUEST['caret']) &&
           isset($_REQUEST['scroll_top'])
                && !isset($page)) {
            $caret = $parent->clean($_REQUEST['caret'],
                'int');
            $scroll_top = $parent->clean($_REQUEST['scroll_top'],
                'int');
            $data['SCRIPT'] .= "wiki = elt('wiki-page');" .
                "if (wiki != null) { " .
                "   if (wiki.setSelectionRange) { " .
                "       wiki.focus();" .
                "       wiki.setSelectionRange($caret, $caret);".
                "   } ".
                "   wiki.scrollTop = $scroll_top;" .
                "}";
        }
        $data["MODE"] = "edit";
        $page_info = $group_model->getPageInfoByName($group_id,
            $page_name, $data['CURRENT_LOCALE_TAG'], 'resources');
        /* if page not yet created than $page_info will be null
           so in the below $page_info['ID'] won't be set.
         */
        $upload_allowed = true;
        if ($missing_fields) {
            return $parent->redirectWithMessage(
                tl("social_component_missing_fields"));
        } else if (isset($page_info['ID']) && !empty($_REQUEST['n'])) {
            $file_name = $parent->clean(urldecode($_REQUEST['n']),
                "file_name");
            $data['PAGE'] = $group_model->getPageResource(
                 $file_name, $group_id, $page_info['ID'], $sub_path);
            $name_parts = pathinfo($file_name);
            if (empty($name_parts['extension']) ||
                $name_parts['extension'] != 'csv') {
                $data['PAGE'] = htmlentities($data['PAGE']);
            }
            if ($page !== null && $data['PAGE'] !== false) {
                $action = "wikiupdate_".
                    "group=".$group_id."&page=".$page_name . "&resource_name=" .
                    $file_name;
                if (!$parent->checkCSRFTime(C\CSRF_TOKEN, $action)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_wiki_edited_elsewhere').
                        "</h1>');";
                    return;
                }
                $success = $group_model->setPageResource($file_name,
                    $_REQUEST['page'], $group_id, $page_info['ID'],
                    $sub_path);
                if ($success) {
                    return $parent->redirectWithMessage(
                        tl("social_component_resource_saved"),
                        ['arg', 'page_name', 'settings',
                        'caret', 'scroll_top','back_params', 'sf', "n"]);
                } else {
                    return $parent->redirectWithMessage(
                        tl('social_component_resource_not_saved'),
                        ['arg', 'page_name', 'settings',
                        'caret', 'scroll_top', 'sf', "n"]);
                }
            }
            $data['PAGE_ID'] = $page_info['ID'];
            $data['PAGE_NAME'] = $page_name;
            $data['RESOURCE_NAME'] = $file_name;
        } else {
            $head_object = $parent->parsePageHeadVars(
                $page_info['PAGE'] ?? "");
            $is_currently_template = (!empty($head_object["page_type"]) &&
                $head_object["page_type"][0] == 't');
            if (isset($page) || ($is_currently_template &&
                !empty($_REQUEST['page_type']))
                || isset($_REQUEST['sort'])) {
                $action = "wikiupdate_".
                    "group=" . $group_id . "&page=" . $page_name;
                if (!$parent->checkCSRFTime(C\CSRF_TOKEN, $action)) {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('social_component_wiki_edited_elsewhere').
                        "</h1>');";
                    return;
                }
                $write_head = false;
                $head_vars = [];
                $page_types = array_keys($data['page_types']);
                $page_borders = array_keys($data['page_borders']);
                $set_path = false;
                foreach ($page_defaults as $key => $default) {
                    $head_vars[$key] = (isset($head_object[$key])) ?
                        $head_object[$key] : $default;
                    if (isset($_REQUEST[$key])) {
                        $head_vars[$key] =  trim(
                            $parent->clean($_REQUEST[$key], "string"));
                        switch ($key) {
                            case 'page_type':
                                if (!in_array($head_vars[$key], $page_types) &&
                                    ($head_vars[$key][0] != 't' ||
                                    !is_numeric(substr($head_vars[$key],1))) ) {
                                    $head_vars[$key] = $default;
                                }
                                break;
                            case 'page_borders':
                                if (!in_array($head_vars[$key],
                                    $page_borders)) {
                                    $head_vars[$key] = $default;
                                }
                                break;
                            case 'alternative_path':
                                if (!is_dir($head_vars[$key]) &&
                                    !empty($head_vars[$key])) {
                                    $head_vars[$key] = $default;
                                } else if (!empty($_SESSION['USER_ID'])
                                    && $_SESSION['USER_ID'] == C\ROOT_ID) {
                                    $set_path = true;
                                }
                                break;
                            case 'sort':
                                if (empty($page) &&
                                    !isset($page_info['PAGE'])) {
                                    break;
                                }
                                if (in_array($head_vars[$key],
                                    ['name', 'size', 'modified'])) {
                                    if (isset($page_info['PAGE'])) {
                                        if (!isset($page)) {
                                            $page_parts =
                                                explode("END_HEAD_VARS",
                                                $page_info['PAGE']);
                                            $page = isset($page_parts[1]) ?
                                                $page_parts[1] : $page_parts[0];
                                        }
                                    }
                                    $new_key = 'a' . $head_vars[$key];
                                    if (isset($head_object['sort'])) {
                                        set_error_handler(null);
                                        $head_object['sort'] = @unserialize(
                                            L\webdecode($head_object['sort']));
                                        set_error_handler(C\NS_LIB .
                                            "yioop_error_handler");
                                    } else {
                                        $head_object['sort'] = [];
                                    }
                                    $sort_path = empty($sub_path) ? "." :
                                        $sub_path;
                                    $sort_path = rtrim($sort_path, '/');
                                    if (empty($head_object['sort'][$sort_path])
                                        || $head_object['sort'][$sort_path] ==
                                        $new_key) {
                                        $new_key = 'r' . $head_vars[$key];
                                    }
                                    $head_object['sort'][$sort_path] = $new_key;
                                    $head_vars[$key] = L\webencode(serialize(
                                        $head_object['sort']));
                                    $edit_reason = "Change resource sort";
                                    $write_head = true;
                                } else {
                                    $head_vars[$key] = $default;
                                }
                                break;
                            default:
                                $head_vars[$key] =
                                    trim(preg_replace("/\n+/", "\n",
                                    $head_vars[$key]));
                        }
                        if ($head_vars[$key] != $default) {
                            $write_head = true;
                        }
                    } else if ($key == 'toc') {
                        if (isset($_REQUEST['title'])) {
                            $head_vars[$key] = false;
                        } else {
                            $head_vars[$key] == true;
                        }
                    }
                }
                $head_string = "";
                foreach ($page_defaults as $key => $default) {
                    $head_string .= urlencode($key) . "=" .
                        urlencode($head_vars[$key]) . "\n\n";
                }
                if (is_array($page)) { //template case
                    $page = base64_encode(serialize($page));
                }
                if (!empty($page) || (!empty($head_vars['page_type']) &&
                    $head_vars['page_type'] != 'standard')) {
                    $page = $head_string . "END_HEAD_VARS" . $page;
                }
                $page_info['ID'] = $group_model->setPageName($user_id,
                    $group_id, $page_name, $page,
                    $data['CURRENT_LOCALE_TAG'], $edit_reason,
                    tl('social_component_page_created', $page_name),
                    tl('social_component_page_discuss_here'),
                    $read_address, $additional_substitutions);
                if ($set_path && !empty($page_info['ID'])) {
                    $tmp = $group_model->getGroupPageResourcesFolders(
                        $group_id, $page_info['ID'], "", true, false);
                    if (isset($tmp[1])) {
                        list($resource_path, $thumb_path,) = $tmp;
                        if (!empty($head_vars['alternative_path'])) {
                            $parent->web_site->filePutContents(
                                "$resource_path/redirect.txt",
                                $head_vars['alternative_path']);
                        } else if (file_exists(
                            "$resource_path/redirect.txt") ) {
                            unlink("$resource_path/redirect.txt");
                        }
                    }
                }
                if (!isset($_FILES['page_resource']['name']) ||
                    $_FILES['page_resource']['name'] == "") {
                    return $parent->redirectWithMessage(
                        tl("social_component_page_saved"),
                        ['arg', 'page_name', 'settings',
                        'caret', 'scroll_top','back_params', 'sf']);
                }
            }
        }
        // Delete a marked diamond icon from video list
        if (!empty($_REQUEST['clear']) && !empty($_SESSION['seen_media'])
            && is_array($_SESSION['seen_media'])) {
            $media_name = $parent->clean($_REQUEST['clear'], 'file_name');
            $hash_id = L\crawlHash($page_info['ID']. $media_name . $sub_path);
            if (in_array($hash_id, $_SESSION['seen_media'])) {
                $_SESSION['seen_media'] = array_diff($_SESSION['seen_media'],
                    [$hash_id]);
                $parent->model("user")->setUserSession($user_id,
                    $_SESSION);
            }
        }
        if (isset($_REQUEST['delete'])) {
            $resource_name = $parent->clean($_REQUEST['delete'],
                "file_name");
            $upload_allowed = false;
            if (isset($page_info['ID']) &&
                $group_model->deleteResource($resource_name,
                $group_id, $page_info['ID'], $sub_path)) {
                $group_model->versionGroupPage($user_id, $page_info['ID'],
                    tl('social_component_resource_deleted'));
                return $parent->redirectWithMessage(
                    tl('social_component_resource_deleted'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            } else {
                return $parent->redirectWithMessage(
                    tl('social_component_resource_not_deleted'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
        }  if (isset($_REQUEST['extract'])) {
            $resource_name = $parent->clean($_REQUEST['extract'],
                "string");
            $upload_allowed = false;
            if (isset($page_info['ID']) &&
                $group_model->extractResource($resource_name,
                $group_id, $page_info['ID'], $sub_path)) {
                $group_model->versionGroupPage($user_id, $page_info['ID'],
                    tl('social_component_resource_extracted'));
                return $parent->redirectWithMessage(
                    tl('social_component_resource_extracted'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            } else {
                return $parent->redirectWithMessage(
                    tl('social_component_resource_not_extracted'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
        } else if (isset($_REQUEST['clip_folder'])) {
            $_SESSION['CLIP_FOLDER'] = ['GROUP_NAME' => $group['GROUP_NAME'],
                'GROUP_ID' => $group_id, 'PAGE_ID' => $page_info['ID'],
                'PAGE_NAME' => $page_name, 'SUB_PATH' => empty($sub_path) ?
                 "" : $sub_path];
            $parent->model("user")->setUserSession($user_id, $_SESSION);
            return $parent->redirectWithMessage(
                tl('social_component_clip_folder_set'),
                ['arg', 'page_name', 'settings',
                'caret', 'scroll_top', 'sf']);
        } else if (isset($_REQUEST['clip_copy'])) {
            $resource_name = $parent->clean($_REQUEST['clip_copy'],
                "string");
            if (empty($_SESSION['CLIP_FOLDER']) ||
                !$group_model->copyResourceToClipFolder(
                $_SESSION['CLIP_FOLDER'], $resource_name, $group_id,
                $page_info['ID'], $sub_path)) {
                return $parent->redirectWithMessage(
                    tl('social_component_copy_fail'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
            $group_model->versionGroupPage($user_id, $page_info['ID'],
                tl('social_component_copy_success'));
            return $parent->redirectWithMessage(
                tl('social_component_copy_success'),
                ['arg', 'page_name', 'settings',
                'caret', 'scroll_top', 'sf']);
        } else if (!empty($_REQUEST['new_resource_name']) &&
            !empty($_REQUEST['old_resource_name'])) {
            $upload_allowed = false;
            $old_resource_name = $parent->clean(
                $_REQUEST['old_resource_name'], "file_name");
            $new_resource_name = $parent->clean(
                $_REQUEST['new_resource_name'], "file_name");
            if (isset($page_info['ID']) &&
                $group_model->renameResource($old_resource_name,
                    $new_resource_name, $group_id,
                    $page_info['ID'], $sub_path)) {
                $group_model->versionGroupPage($user_id, $page_info['ID'],
                    tl('social_component_resource_renamed'));
                return $parent->redirectWithMessage(
                    tl('social_component_resource_renamed'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            } else {
                return $parent->redirectWithMessage(
                    tl('social_component_resource_not_renamed'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
        } else if (isset($_REQUEST['resource_actions']) &&
            in_array($_REQUEST['resource_actions'],
            ['new-folder', 'new-text-file', 'new-csv-file']) &&
            !empty($page_info['ID'])) {
            if ($group_model->newResource($_REQUEST['resource_actions'],
                $group_id, $page_info['ID'], $sub_path)) {
                $group_model->versionGroupPage($user_id, $page_info['ID'],
                    tl('social_component_resource_created'));
                return $parent->redirectWithMessage(
                    tl('social_component_resource_created'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            } else {
                return $parent->redirectWithMessage(
                    tl('social_component_resource_not_created'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
        }
        if ($upload_allowed && !empty($_FILES['page_resource']['name'])) {
            if (!isset($page_info['ID'])) {
                $_FILES = [];
                return $parent->redirectWithMessage(
                    tl('social_component_resource_save_first'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
            $result = $this->handleResourceUploads(
                $group_id, $page_info['ID'], $sub_path);
            if ($result == self::UPLOAD_SUCCESS) {
                //we re-parse page so resources parsed
                if (isset($page) && isset($edit_reason)) {
                    $group_model->setPageName($user_id,
                        $group_id, $page_name, $page,
                        $data['CURRENT_LOCALE_TAG'], $edit_reason,
                        tl('social_component_page_created',
                        $page_name),
                        tl('social_component_page_discuss_here'),
                        $read_address, $additional_substitutions);
                }
                return $parent->redirectWithMessage(
                    tl('social_component_resource_uploaded'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            } else {
                return $parent->redirectWithMessage(
                    tl('social_component_upload_error'),
                    ['arg', 'page_name', 'settings',
                    'caret', 'scroll_top', 'sf']);
            }
        }
        if (isset($page_info['ID'])) {
            $create = ($user_id == C\PUBLIC_USER_ID) ? false : true;
            $data['RESOURCES_INFO'] =
                $group_model->getGroupPageResourceUrls($group_id,
                $page_info['ID'], $sub_path, $create);
        } else {
            $data['RESOURCES_INFO'] = [];
        }
        $data['CLIP_IS_CURRENT_DIR'] = false;
        if (!empty($_SESSION['CLIP_FOLDER'])) {
            $data['CLIP_FOLDER'] = $_SESSION['CLIP_FOLDER']['GROUP_NAME'] .
                "@" . $_SESSION['CLIP_FOLDER']['PAGE_NAME'] . "/" .
                $_SESSION['CLIP_FOLDER']['SUB_PATH'];
            if ($group['GROUP_NAME'] ==
                $_SESSION['CLIP_FOLDER']['GROUP_NAME'] &&
                $page_name == $_SESSION['CLIP_FOLDER']['PAGE_NAME'] &&
                ($_SESSION['CLIP_FOLDER']['SUB_PATH'] == $sub_path )) {
                 $data['CLIP_IS_CURRENT_DIR'] = true;
            }
        }
    }
    /**
     * Used to set up the partially processed wiki page, before media inserted,
     * needed to display a single media item on a media list. The name of
     * the media item to be display is expected to come from $_REQUEST['n'].
     *
     * @param array &$data array of field variables for view will be modified
     *  by this function
     * @param int $group_id id of group wiki page belongs to
     * @param int $page_id id of wiki page
     * @param string $sub_path sub-resource folder that is being used, if any,
     *  to get resources from
     */
    public function mediaWiki(&$data, $group_id, $page_id, $sub_path="")
    {
        if (!isset($page_id) || !isset($_REQUEST['n'])) {
            return;
        }
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $media_name = $parent->clean($_REQUEST['n'], "file_name");
        $page_info = $group_model->getPageInfoByPageId($page_id);
        $data["DISCUSS_THREAD"] = $page_info["DISCUSS_THREAD"] ?? "";
        $data['SCRIPT'] = $data['SCRIPT'] ?? "";
        $data['PAGE_NAME'] = htmlentities($page_info['PAGE_NAME'] ?? "");
        $page_info = $group_model->getPageInfoByName($group_id,
            $page_info['PAGE_NAME'] ?? "", $data['CURRENT_LOCALE_TAG'], 'edit');
        $data['RESOURCES_INFO'] = $group_model->getGroupPageResourceUrls(
            $group_id, $page_id, $sub_path);
        $data['HEAD'] = $parent->parsePageHeadVars($page_info['PAGE'] ?? "");
        $this->sortWikiResources($data);
        $resources = $data['RESOURCES_INFO']['resources'] ?? "";
        $num_resources = (is_array($resources)) ? count($resources) : 0;
        for ($i = 0; $i < $num_resources; $i++) {
            if ($resources[$i]['name'] == $media_name) {
                break;
            }
        }
        if ($i == $num_resources) {
            $parent->web_site->header("HTTP/1.0 404 Not Found");
            $data["MEDIA_NAME"] = $media_name;
            $parent->displayView("nocache", $data);
            \seekquarry\yioop\library\webExit(); //bail
        }
        $is_static = ($data['CONTROLLER'] == 'static') ? true : false;
        $base_url = htmlentities(B\wikiUrl($data['PAGE_NAME'] , true,
            $data['CONTROLLER'], $group_id));
        if (isset($_SESSION['USER_ID']) && intval($_SESSION['USER_ID']) > 0) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = C\PUBLIC_USER_ID;
        }
        $csrf_token = $this->parent->generateCSRFToken(
            $user_id);
        if (!empty($data['ADMIN'])) {
            $base_url .= C\CSRF_TOKEN . "=". $csrf_token;
        }
        $folder_prefix = ($is_static) ? $base_url : $base_url . "&amp;";
        $folder_prefix .= "page_id=". $page_id;
        $data['ROOT_LINK'] = $folder_prefix;
        if (!empty($data['SUB_PATH'])) {
             $folder_prefix .= "&amp;sf=" . urlencode($data['SUB_PATH']);
        }
        $url_prefix = $folder_prefix . "&amp;arg=media";
        $mime_type = L\mimeType($media_name, true);
        $prev_name = ($i < $num_resources &&
            isset($resources[$i - 1]['name'])) ?
            $resources[$i - 1]['name'] : false;
        $next_name = (isset($resources[$i + 1]['name'])) ?
            $resources[$i + 1]['name'] : false;
        $name_parts = pathinfo($media_name);
        $file_name = $name_parts['filename'];
        $data['MEDIA_NAME'] = $media_name;
        $page_string = "";
        $data['URL_PREFIX'] = $url_prefix;
        if (!empty($prev_name)) {
            $data['PREV_LINK'] = "$url_prefix&amp;n=" . urlencode($prev_name);
            $prev_link = $data['PREV_LINK'];
            if (!in_array($mime_type, ["application/epub+zip",
                "application/pdf", 'video/mp4', 'video/m4v'])) {
                $data['SCRIPT'] .= 'leftSwipe(document, function(evt) {'.
                    'window.location="'.$prev_link.'";})'."\n";
            }
        }
        if (!empty($next_name)) {
            $data['NEXT_LINK'] = "$url_prefix&amp;n=" . urlencode($next_name);
            $data['NEXT_INDEX'] = $i+1;
            $next_link = $data['NEXT_LINK'];
            if (!in_array($mime_type,["application/epub+zip",
                "application/pdf", 'video/mp4', 'video/m4v'])) {
                $data['SCRIPT'] .= 'rightSwipe(document, function(evt) {'.
                    'window.location="'.$next_link.'";})'."\n";
            }
        }
        $page_string .= "<div class='media-container'>";
        if (!empty($sub_path)) {
            $page_string .= "((resource:$media_name|$sub_path".
                "|$file_name ))";
        } else {
            $page_string .= "((resource:$media_name|$file_name ))";
        }
        $page_string .= "</div>";
        $include_charts_and_spreadsheets = ($mime_type == 'text/csv') ?
            true : false;
        $data["PAGE"] = $group_model->insertResourcesParsePage(
            $group_id, $page_id, $data['CURRENT_LOCALE_TAG'],
            $page_string, $csrf_token, $data['CONTROLLER'],
            $include_charts_and_spreadsheets);
        if (substr($mime_type, 0, 4) == 'text') {
            $this->parent->recordViewSession($page_id, $sub_path, $media_name);
        }
        if ($mime_type == "application/epub+zip") {
            $this->addEpubMediaScripts($file_name, $data);
        } else if ($mime_type == "application/pdf") {
            $this->addPdfMediaScripts($file_name, $data);
        } else if ($mime_type == "text/csv") {
            $data['INCLUDE_SCRIPTS'][] = 'spreadsheet';
            $data['SPREADSHEET'] = true;
        }
        $data["PAGE_ID"] = $page_id;
    }
    /**
     * Adds Javascript used to display epub files to $data view variables fo
     * display when in media gallery mode
     *
     * @param string $file_name name of epub file
     * @param array &$data associative array of fields to be rendered in the
     * view
     */
    private function addEpubMediaScripts($file_name, &$data)
    {
        $data['INCLUDE_SCRIPTS'][] = 'epub.js/libs/jszip/jszip.min';
        $data['INCLUDE_SCRIPTS'][] = 'epub.js/build/epub.min';
        $book_name = "b" . str_replace('-', "_", L\crawlHash($file_name));
        $width = ($_SERVER["MOBILE"]) ? 300 : 760;
        $height = ($_SERVER["MOBILE"]) ? 460 : 600;
        $offset = ($_SERVER["MOBILE"]) ? 0 : 2;
        $doc_sections = tl('social_component_doc_sections');
        $data['SCRIPT'] .= <<< EOD
            let $book_name = ePub({$book_name}_url, {
                width: $width,
                height: $height,
                spreads: false}
            );
            $book_name.reflect_offset = $offset;
            $book_name.reflect_name = '$book_name';
            $book_name.reflect_type = 'epub';
            $book_name.getToc().then(function(toc) {
                let media_path_obj = elt('media-path-list');
                docfrag = document.createDocumentFragment();
                let doc_sections = document.createElement("li");
                let i = 0;
                doc_sections.innerHTML = '<b>$doc_sections</b>';
                docfrag.appendChild(doc_sections);
                toc.forEach( function(chapter) {
                    var toc_item = document.createElement("li");
                    toc_item.innerHTML="<a onclick='$book_name.goto(\""+
                        chapter.href + "\").then(function () {" +
                            "updateMediaLocationInfo($book_name);" +
                        "})" +
                        "' href='javascript:void(0);' >" +
                        chapter.label + "</a>";
                    toc_item.ref = chapter.href;
                    toc_item.epub_ref = $book_name;
                    docfrag.appendChild(toc_item);
                    i++;
                    }
                );
                media_path_obj.num_doc_sections = i;
                media_path_obj.insertBefore(docfrag, media_path_obj.firstChild);
            } );
            $book_name.renderTo('area-$book_name');
            if (localStorage) {
                let {$book_name}_page_string = localStorage.getItem(
                    '$book_name-pagination');
                let {$book_name}_location = localStorage.getItem(
                    '$book_name-location');
            }
            if ({$book_name}_page_string) {
                $book_name.pagination = new EPUBJS.Pagination();
                let {$book_name}_page_list =
                    JSON.parse({$book_name}_page_string);
                $book_name.pagination.pageList = {$book_name}_page_list;
                $book_name.pagination.process({$book_name}_page_list);
                if ({$book_name}_location) {
                    $book_name.gotoCfi({$book_name}_location);
                    updateMediaLocationInfo($book_name, {$book_name}_location);
                } else {
                    updateMediaLocationInfo($book_name, {$book_name}_location);
                }
            } else {
                $book_name.generatePagination().then(
                    function() {
                        updateMediaLocationInfo($book_name);
                        {$book_name}_page_string =
                            JSON.stringify($book_name.pagination.pageList);
                        if (localStorage) {
                            localStorage.setItem('$book_name-pagination',
                                {$book_name}_page_string);
                        }
                    }
                );
            }
            document.onkeydown = function (evt) {
                let key_code_pressed;
                if (evt) { // IE8 and earlier
                    key_code_pressed = evt.keyCode;
                } else if (evt.which) { // IE9/Firefox/Chrome/Opera/Safari
                    key_code_pressed = evt.which;
                }
                if (key_code_pressed == 37) {
                    previousMediaPage($book_name);
                }
                if (key_code_pressed == 39) {
                    nextMediaPage($book_name);
                }
            }
EOD;
    }
    /**
     * Adds Javascript used to display PDF files to $data view variables fo
     * display when in media gallery mode
     *
     * @param string $file_name name of epub file
     * @param array &$data associative array of fields to be rendered in the
     * view
     */
    private function addPdfMediaScripts($file_name, &$data)
    {
        $data['INCLUDE_SCRIPTS'][] = 'pdf.js/build/pdf';
        $book_name = "b" . str_replace('-', "_", L\crawlHash(trim($file_name)));
        $doc_sections = tl('social_component_doc_sections');
        $data['SCRIPT'] .= <<< EOD
            var $book_name = {};
            PDFJS.getDocument({$book_name}_url).then(
            function (pdf) {
                $book_name = pdf;
                $book_name.reflect_name = '$book_name';
                $book_name.reflect_type = 'pdf';
                $book_name.reflect_orientation = '0';
                $book_name.reflect_page_num = '1';
                if (localStorage) {
                    $book_name.reflect_page_num = localStorage.getItem(
                        '$book_name-reflect-page-num');
                    if (!$book_name.reflect_page_num) {
                        $book_name.reflect_page_num = "1";
                    }
                    $book_name.reflect_orientation = localStorage.getItem(
                        '$book_name-reflect-orientation');
                    if (!$book_name.reflect_orientation) {
                        $book_name.reflect_orientation = "0";
                    }
                }
                renderPdfPage($book_name,
                    parseInt($book_name.reflect_page_num));
                $book_name.getOutline().then(function(toc) {
                    var media_path_obj = elt('media-path-list'),
                    docfrag = document.createDocumentFragment();
                    let doc_sections = document.createElement("li");
                    let i = 0;
                    doc_sections.innerHTML = '<b>$doc_sections</b>';
                    docfrag.appendChild(doc_sections);
                    toc.forEach( function(chapter) {
                        let toc_item = document.createElement("li");
                        toc_item.id = "toc$book_name" + i;
                        toc_item.ref = chapter.dest;
                        toc_item.pdf_ref = $book_name;
                        toc_item.getPage = function () {
                            toc_item.pdf_ref.getPageIndex(toc_item.ref[0]).then(
                                function (page_index) {
                                    renderPdfPage(toc_item.pdf_ref,
                                        page_index + 1);
                                }
                            );
                        };
                        toc_item.innerHTML =
                            "<a onclick='elt(\"toc$book_name" + i+
                            "\").getPage()'  href='javascript:void(0);' >" +
                            chapter.title + "</a>";

                        docfrag.appendChild(toc_item);
                        i++;
                        }
                    );
                    media_path_obj.num_doc_sections = i;
                    media_path_obj.insertBefore(docfrag,
                        media_path_obj.firstChild);
                });
            });
            leftSwipe(document, function(evt) {
                nextMediaPage($book_name);
                }
            );
            rightSwipe(document, function(evt) {
                previousMediaPage($book_name);
                }
            );
            document.onkeydown = function(evt) {
                var key_code_pressed;
                if (evt) { // IE8 and earlier
                    key_code_pressed = evt.keyCode;
                } else if (evt.which) { // IE9/Firefox/Chrome/Opera/Safari
                    key_code_pressed = evt.which;
                }
                if (key_code_pressed == 37) {
                    previousMediaPage($book_name);
                }
                if (key_code_pressed == 39) {
                    nextMediaPage($book_name);
                }
            }
EOD;
    }
    /**
     * Used to initialize arrays for dropdowns in WikiElement as well
     * as various arrays for cleaning request variables
     *
     * @param string $controller_name used to set up variables for view elements
     *  should be either admin, api, or group depending on which controller
     *  is being used to handle wiki interaction
     * @param string $base_url to use in creating link targets
     */
    private function initCommonWikiArrays($controller_name, $base_url)
    {
        $parent = $this->parent;
        $data = [];
        $data["CONTROLLER"] = $controller_name;
        $data["ELEMENT"] = "wiki";
        $data["VIEW"] = "group";
        $data["SCRIPT"] = "";
        $data["INCLUDE_STYLES"] = ["editor"];
        $locale_tag = L\getLocaleTag();
        $data['CURRENT_LOCALE_TAG'] = $locale_tag;
        $sub_path = "";
        if (!empty($_REQUEST['page_name'])) {
            $name_parts = explode("/", $_REQUEST['page_name']);
            if (count($name_parts) > 1) {
                $_REQUEST['page_name'] = array_shift($name_parts);
                $sub_path = $parent->clean(implode("/", $name_parts),
                    'string');
                $sub_path = str_replace(".", "", $sub_path);
                $data['SUB_PATH'] = htmlentities($sub_path);
            }
        }
        if (!empty($_REQUEST['sf'])) {
            $sub_path = $parent->clean($_REQUEST['sf'], 'string');
            $sub_path = str_replace(".", "", $sub_path);
            $data['SUB_PATH'] = htmlentities($sub_path);
        }
        $data['ORIGINAL_SUB_PATH'] = $sub_path;
        $data["CAN_EDIT"] = false;
        if ((isset($_REQUEST['c'])) && $_REQUEST['c'] == "api") {
            //wiki help request
            $data['MODE'] = 'api';
            $data['VIEW'] = 'api';
        } else {
            $data["MODE"] = "read";
            // additional feed data on page_and_feedback page
            if (!empty($_REQUEST['f']) && $_REQUEST['f'] == "api") {
                $data['VIEW'] = 'api';
            }
        }
        $data['page_types'] = [
            "standard" => tl('social_component_standard_page'),
            "page_and_feedback" => tl('social_component_page_and_feedback'),
            "page_alias" => tl('social_component_page_alias'),
            "media_list" => tl('social_component_media_list'),
            "presentation" => tl('social_component_presentation')
        ];
        $data['page_borders'] = [
            "solid-border" => tl('social_component_solid'),
            "dashed-border" => tl('social_component_dashed'),
            "none" => tl('social_component_none')
        ];
        $data['resource_actions'] = [
            "actions" => tl('social_component_actions'),
            "new-folder" => tl('social_component_new_folder'),
            "new-text-file" => tl('social_component_new_text_file'),
            "new-csv-file" => tl('social_component_new_csv_file'),
        ];
        $search_translation = tl('social_component_search');
        $search_form = <<<EOD
<form method="get" action='$base_url' class="search-box $2-search-box" >
<input type='hidden' name="its" value='$1' />
<input type='text'  name='q'  value="" placeholder='$3'
    title='$3' class='search-input' />
<button type="submit" class='search-button'><img
    src='{$base_url}resources/search-button.png'
    alt='$search_translation'/></button>
</form>
EOD;
        $additional_substitutions[] = ['/{{\s*search\s*:\s*(.+?)\s*\|'.
            '\s*size\s*:\s*(.+?)\s*\|\s*placeholder\s*:\s*(.+?)}}/',
            $search_form];
        $clean_array = [
            "group_id" => "int",
            "page_name" => "string",
            "page" => "string",
            "edit_reason" => "string",
            "filter" => 'string',
            "limit" => 'int',
            "num" => 'int',
            "page_id" => 'int',
            "show" => 'int',
            "sort" => 'string',
            "diff" => 'int',
            "diff1" => 'int',
            "diff2" => 'int',
            'resource_filter' => 'file_name',
            "revert" => 'int',
            "group_name" => 'string',
        ];
        $strings_array = [
            "page_name" => C\TITLE_LEN,
            "page" => C\MAX_GROUP_PAGE_LEN,
            "edit_reason" => C\SHORT_TITLE_LEN,
            "filter" => C\SHORT_TITLE_LEN,
            "resource_filter" => C\SHORT_TITLE_LEN];
        $page_defaults = [
            'page_type' => 'standard',
            'page_alias' => '',
            'page_border' => 'solid',
            'toc' => true,
            'title' => '',
            'author' => '',
            'robots' => '',
            'description' => '',
            'alternative_path' => '',
            'page_header' => '',
            'page_footer' => '',
            'sort' => 'aname'
        ];
       /* Check if back params need to be set. Set them if required.
          the back params are usually sent when the wiki action is initiated
          from within an open help article.
        */
        $data["OTHER_BACK_URL"] = "";
        if (isset($_REQUEST['back_params']) &&
            ((isset($_REQUEST['arg']) && in_array(
                $parent->clean($_REQUEST['arg'],"string"), ['edit',
                'read'])) || (isset($_REQUEST['page_name'])))
                ) {
            $back_params_cleaned = $_REQUEST['back_params'];
            array_walk($back_params_cleaned, [$parent, 'clean']);
            foreach ($back_params_cleaned as
                    $back_param_key => $back_param_value) {
                $data['BACK_PARAMS']["back_params[$back_param_key]"]
                    = $back_param_value;
                $data["OTHER_BACK_URL"] .=
                    "&amp;back_params[$back_param_key]" . "=" .
                    $back_param_value;
            }
            $data['BACK_URL'] = http_build_query($back_params_cleaned);
        }
        return [$data, $sub_path, $additional_substitutions, $clean_array,
            $strings_array, $page_defaults];
    }
    /**
     * Used to create Javascript used to toggle a wiki page's settings control
     *
     * @param array &$data will contain in SCRIPT field neccessary Javascript
     *  to pass to view.
     */
    private function initializeWikiPageToggle(&$data)
    {
        $init_toggle_settings = (empty($data['RESOURCE_NAME'])) ?
            'setDisplay("toggle-settings", !is_page_alias, "inline");': '';
        $toggle_settings_on = (empty($data['RESOURCE_NAME'])) ?
            'setDisplay("toggle-settings", true);': '';
        $toggle_settings_false = (empty($data['RESOURCE_NAME'])) ?
            'setDisplay("toggle-settings", false);': '';
        $toggle_settings_inline = (empty($data['RESOURCE_NAME'])) ?
            'setDisplay("toggle-settings", true, "inline");': '';
        $data['SCRIPT'] .= <<< EOD
            setDisplay('page-settings', {$data['settings']});
            mode = '{$data['MODE']}';
            function toggleSettings()
            {
                var settings = elt('p-settings');
                settings.value = (settings.value == 'true')
                    ? 'false' : 'true';
                var value = (settings.value == 'true') ? true : false;
                var r_settings =elt('r-settings');
                if (r_settings && mode == 'edit') {
                    elt('r-settings').value = settings.value;
                }
                setDisplay('page-settings', value);
                var page_type = elt("page-type");
                var cur_type = page_type.options[
                    page_type.selectedIndex].value;
                if (cur_type == "media_list" && mode == 'edit') {
                    setDisplay('save-container', value);
                }
            }
            ptype = document.getElementById("page-type");
            is_media_list = ('media_list'=='{$data['current_page_type']}');
            is_settings = {$data['settings']};
            is_page_alias = ('page_alias'=='{$data['current_page_type']}');
            setDisplay('page-settings', is_settings || is_page_alias);
            setDisplay("media-list-page", is_media_list && !is_page_alias);
            setDisplay("page-container", !is_media_list && !is_page_alias);
            setDisplay("non-alias-type", !is_page_alias);
            setDisplay("alias-type", is_page_alias);
            if (mode == 'edit') {
                setDisplay('save-container', !is_media_list || is_settings);
                setDisplay('resource-upload-form', is_media_list);
            }
            $init_toggle_settings
            setDisplay("page-resources", !is_page_alias);
            ptype.onchange = function() {
                var cur_type = ptype.options[ptype.selectedIndex].value;
                if (cur_type == "media_list") {
                    setDisplay("media-list-page", true, "inline");
                    setDisplay("page-container", false);
                    $toggle_settings_on
                    setDisplay("non-alias-type", true);
                    setDisplay("alias-type", false);
                    setDisplay("page-resources", true);
                    if (mode == 'edit') {
                        setDisplay("resource-upload-form", true);
                    }
                } else if (cur_type == "page_alias") {
                    $toggle_settings_false
                    setDisplay("media-list-page", false);
                    setDisplay("page-container", false);
                    setDisplay("non-alias-type", false);
                    setDisplay("alias-type", true);
                    setDisplay("page-resources", false);
                } else {
                    setDisplay("page-container", true);
                    setDisplay("media-list-page", false);
                    $toggle_settings_inline
                    setDisplay("non-alias-type", true);
                    setDisplay("alias-type", false);
                    setDisplay("page-resources", true);
                    if (mode == 'edit') {
                        setDisplay("resource-upload-form", false);
                    }
                }
            }
EOD;
    }
}
