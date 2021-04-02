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
 * @author Pushkar Umaranikar
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;

/**
 * Component of the Yioop control panel used to handle activitys for
 * managing advertisements. i.e., create advertisement, activate/
 * deactivate advertisement, edit advertisement.It is used by AdminController
 *
 * @author Pushkar Umaranikar (enhancements Chris Pollett)
 */
class StoreComponent extends Component
{
    /**
     * Used to manage the purchase and storage of advertising credits
     *
     * @return array $data field variables necessary for display of view
     */
    public function manageCredits()
    {
        $parent = $this->parent;
        $credit_model = $parent->model("credit");
        $signin_model = $parent->model("signin");
        $user_model = $parent->model("user");
        $data = [];
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $data["ELEMENT"] = "managecredits";
        $data["AMOUNTS"] = [0 => tl('store_component_credit_amounts'),
            "10" => tl('store_component_ten_in_credits'),
            "20" => tl('store_component_twenty_in_credits'),
            "50" => tl('store_component_fifty_in_credits'),
            "100" => tl('store_component_hundred_in_credits'),
        ];
        $data['COST_AMOUNTS'] = [
            10 => 1000, 20 => 2000, 50 => 5000, 100 => 10000,
        ];
        $data['MONTHS'] = [ 0 => tl('store_component_month'),
            "01" => "01", "02" => "02", "03" => "03",
            "04" => "04", "05" => "05", "06" => "06", "07" => "07",
            "08" => "08", "09" => "09", "10" => "10", "11" => "11",
            "12" => "12"
        ];
        $search_array = [];
        $user_id = $_SESSION['USER_ID'];
        $username = $signin_model->getUserName($user_id);
        $data["USER"] = $user_model->getUser($username);
        $data["USER_ID"] = $user_id;
        $current_year = date('Y');
        $data['YEARS'] = [ 0 => tl('store_component_year')];
        for ( $year = $current_year; $year < $current_year + 20; $year++ ) {
            $data['YEARS'][$year] = $year;
        }
        $arg = (isset($_REQUEST['arg'])) ? $parent->clean($_REQUEST['arg'],
            "string") : "";
        $num_dollars = (isset($_REQUEST['NUM_DOLLARS']) &&
            isset($data['COST_AMOUNTS'][$_REQUEST['NUM_DOLLARS']])) ?
            $_REQUEST['NUM_DOLLARS'] : 0;
        $data['BALANCE'] = $credit_model->getCreditBalance($user_id);
        if (C\CreditConfig::isActive() && (!($user_id == C\ROOT_ID &&
            C\ALLOW_FREE_ROOT_CREDIT_PURCHASE))) {
            $data["INCLUDE_SCRIPTS"][] = 'credit';
            $ad_script_found = false;
            for ($i = C\DATABASE_VERSION; $i >= C\MIN_AD_VERSION; $i--) {
                $get_credit_token_initialize_script =
                    "FN" . md5(UrlParser::getBaseDomain(C\NAME_SERVER) .
                    $i . "getCreditTokenInitializeScript");
                if (method_exists( C\NS_CONFIGS . "CreditConfig",
                    $get_credit_token_initialize_script)) {
                    $ad_script_found = true;
                    break;
                }
            }
            if ($ad_script_found) {
                $data['SCRIPT'] .=
                    C\CreditConfig::$get_credit_token_initialize_script();
            } else {
                $data['DISPLAY_MESSAGE'] =
                    tl('store_component_script_failure');
            }
        }
        $data['FORM_TYPE'] = 'purchaseCredits';
        switch ($arg)
        {
            case "purchaseCredits":
                $message = "";
                if ($num_dollars <= 0) {
                    return $parent->redirectWithMessage(
                        tl('store_component_invalid_credit_quantity'),
                        []);
                }
                /*  string to translate stored in column of 32 chars
                    so not writing advertisement_component
                 */
                $strings_to_translate_for_model = [
                    tl('advertisement_buy_credits'),
                    tl('advertisement_init_ledger')];
                $token = $parent->clean($_REQUEST['CREDIT_TOKEN'], "string");
                if (!($user_id == C\ROOT_ID &&
                    C\ALLOW_FREE_ROOT_CREDIT_PURCHASE)) {
                    $is_active = C\CreditConfig::isActive();
                    if ($is_active && empty($token)) {
                        return $parent->redirectWithMessage(
                            tl('store_component_credit_token_empty'),
                            []);
                    }
                    if ($is_active && !C\CreditConfig::charge(
                        $num_dollars, $parent->clean(
                        $_REQUEST['CREDIT_TOKEN'], "string"), $message)) {
                        return $parent->redirectWithMessage(
                            tl('store_component_processing_error',
                                $message), []);
                    }
                }
                $credit_model->updateCredits($user_id,
                    $data['COST_AMOUNTS'][$num_dollars],
                    'advertisement_buy_credits');
                return $parent->redirectWithMessage(
                    tl('store_component_credits_purchased'),
                    []);
                break;
            case "search":
                $data["FORM_TYPE"] = "search";
                $search_array =
                    $parent->tableSearchRequestHandler($data,
                        "manageCredits", ['ALL_FIELDS' =>
                        ['amount', 'timestamp', 'type'],
                        'EQUAL_COMPARISON_TYPES' => ['type'],
                        'BETWEEN_COMPARISON_TYPES' => ['amount'],
                        'TIMESTAMP_COMPARISON_TYPES' => ['timestamp']
                    ]);
                if (empty($_SESSION['LAST_SEARCH']['manageCredits']) ||
                    isset($_REQUEST['type'])) {
                    $_SESSION['LAST_SEARCH']['manageCredits'] =
                        $_SESSION['SEARCH']['manageCredits'];
                    unset($_SESSION['SEARCH']['manageCredits']);
                } else {
                    $default_search = true;
                }
                break;
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['manageCredits'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array = $parent->restoreLastSearchFromSession($data,
                        'manageCredits');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array = $_SESSION['LAST_SEARCH']['manageCredits'][
                        'SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['manageCredits']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["timestamp", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $credit_model, "TRANSACTIONS",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            ["USER_ID" => $user_id]);
        if ($data['FORM_TYPE'] == 'purchaseCredits') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Used to handle the Create, Edit and Activation of Advertisements
     *
     * @return array $data field variables necessary for display of view
     */
    public function manageAdvertisements()
    {
        $parent = $this->parent;
        $signin_model = $parent->model("signin");
        $user_model = $parent->model("user");
        $role_model = $parent->model('role');
        $advertisement_model = $parent->model("advertisement");
        $credit_model = $parent->model("credit");
        $data = [];
        $data['DURATIONS'] = [ 0 => tl('store_component_num_days'),
            1 => tl('store_component_one_day'),
            7 => tl('store_component_seven_days'),
            30 => tl('store_component_thirty_days'),
            90 => tl('store_component_ninety_days'),
            180 => tl('store_component_one_eighty_days'),
        ];
        $request_field_types = [
            'context' => 'string',
            "start_row" => 'int', "num_show"  => 'int', "end_row"  => 'int',
            "NAME" => 'string', "DESTINATION" => 'web-url',
            "DESCRIPTION"  => 'string', "KEYWORDS"  => 'string',
            "BUDGET"  => 'int', "DURATION" => array_keys($data['DURATIONS']),
            'id' => 'int', 'status' => 'int'];
        $request_fields = array_keys($request_field_types);
        $data['MONTHS'] = [ 0 => tl('store_component_month'),
            "01" => "01", "02" => "02", "03" => "03",
            "04" => "04", "05" => "05", "06" => "06", "07" => "07",
            "08" => "08", "09" => "09", "10" => "10", "11" => "11",
            "12" => "12"
        ];
        $current_year = date('Y');
        $data['YEARS'] = [ 0 => tl('store_component_year')];
        for ( $year = $current_year; $year < $current_year + 20; $year++ ) {
            $data['YEARS'][$year] = $year;
        }
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $data["ELEMENT"] = "manageadvertisements";
        $data['FORM_TYPE'] = "addadvertisement";
        $data['DURATION'] = 0;
        foreach ($request_field_types as $field => $type) {
            if (isset($_REQUEST[$field])) {
                $data[$field] = $parent->clean($_REQUEST[$field], $type);
            }
        }
        $initial_display_state = 'false';
        if (isset($_REQUEST['EDIT_AD'])) {
            if ($_REQUEST['EDIT_AD'] != "true") {
                unset($_REQUEST['CALCULATE']);
                unset($_REQUEST['arg']);
            }
            $initial_display_state = 'true';
        }
        if (isset($_REQUEST['CALCULATE']) || (isset($_REQUEST['arg']) &&
            $_REQUEST['arg'] == "addadvertisement")) {
            if (empty($_REQUEST['NAME']) ||
                empty($_REQUEST['DESCRIPTION']) ||
                empty($_REQUEST['DESTINATION'])) {
                $_REQUEST['EDIT_AD'] = "true";
                return $parent->redirectWithMessage(
                    tl('store_component_fields_cannot_be_empty'),
                    array_merge([$_REQUEST['arg'], 'EDIT_AD'],
                    $request_fields));
            }
            if (!isset($_REQUEST['DURATION']) || $_REQUEST['DURATION'] == 0) {
                $_REQUEST['EDIT_AD'] = "true";
                return $parent->redirectWithMessage(
                    tl('store_component_duration_cannot_be_empty'),
                    array_merge([$_REQUEST['arg'], 'EDIT_AD'],
                    $request_fields));
            }
            if (empty($_REQUEST['KEYWORDS'])) {
                $_REQUEST['EDIT_AD'] = "true";
                return $parent->redirectWithMessage(
                    tl('store_component_enter_keywords'),
                    array_merge([$_REQUEST['arg'], 'EDIT_AD'],
                    $request_fields));
            }
            $data['START_DATE'] = date(C\AD_DATE_FORMAT);
            $_REQUEST['START_DATE'] = $data['START_DATE'];
            $start_date = strtotime($data['START_DATE']);
            $data['END_DATE'] = date(C\AD_DATE_FORMAT,
                $start_date + (($data['DURATION'] - 1) * C\ONE_DAY));
            $_REQUEST['END_DATE'] = $data['END_DATE'];
            $this->initializeAdKeywords($data, $start_date, $data['DURATION']);
            $initial_display_state = 'true';
        }
        $user_id = $_SESSION['USER_ID'];
        $is_admin = $role_model->checkUserRole($user_id, C\ADMIN_ROLE);
        $data['HAS_ADMIN_ROLE'] = $is_admin;
        $username = $signin_model->getUserName($user_id);
        $data["USER"] = $user_model->getUser($username);
        $data["USER_ID"] = $user_id;
        $data['PAGING'] = "";
        $search_array = [];
        $arg = (isset($_REQUEST['arg'])) ? $parent->clean($_REQUEST['arg'],
            "string") : "";
        $data['BALANCE'] = $credit_model->getCreditBalance($user_id);
        switch ($arg)
        {
            case "addadvertisement":
                if ( isset($_REQUEST['PURCHASE'])) {
                    $advertisement = [];
                    $advertisement['USER_ID'] = $user_id;
                    $fields = ["NAME", "DESCRIPTION",
                        "DESTINATION", "BUDGET", "KEYWORDS",
                        "START_DATE", "END_DATE"];
                    foreach ($fields as $field) {
                        if (isset($_REQUEST[$field])) {
                            $advertisement[$field] = $data[$field];
                        }
                    }
                    if (empty($_REQUEST['KEYWORDS'])) {
                        $_REQUEST['EDIT_AD'] = "true";
                        return $parent->redirectWithMessage(
                            tl('store_component_enter_keywords'),
                            array_merge(['arg', 'EDIT_AD'], $request_fields));
                    }
                    $ad_start_date = $data['START_DATE'];
                    if ($advertisement["BUDGET"] < $data['AD_MIN_BID']) {
                        $_REQUEST['EDIT_AD'] = "true";
                        return $parent->redirectWithMessage(
                            tl('store_component_bid_too_low'),
                            array_merge(['arg', 'EDIT_AD'], $request_fields));
                    }
                    if ($data['BALANCE'] < $advertisement["BUDGET"]) {
                        $_REQUEST['EDIT_AD'] = "true";
                        return $parent->redirectWithMessage(
                            tl('store_component_too_few_credits'),
                            array_merge(['arg', 'EDIT_AD'], $request_fields));
                    }
                    $message = "";
                    $strings_to_translate_for_model =
                        [tl('advertisement_buy_ad')];
                    $advertisement_model->addAdvertisement($advertisement,
                        $data["AD_KEYWORDS"], $data['AD_MIN_BID'], $user_id);
                    $credit_model->updateCredits($user_id,
                        -$data["BUDGET"],
                        'advertisement_buy_ad');
                    $preserve = [];
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $preserve[] = 'arg';
                    }
                    return $parent->redirectWithMessage(
                        tl('store_component_ad_created'),
                        array_merge($preserve,
                        ["start_row", "num_show", "end_row"]));
                }
                break;
            case "changestatus":
                if (isset($_REQUEST['id'])) {
                    $ad = $advertisement_model->getAdvertisementById(
                        $data['id']);
                    if (empty($ad) || ($user_id != $ad['USER_ID'] &&
                        !$is_admin) ) {
                        break;
                    }
                    $user_ad_statuses = [C\ADVERTISEMENT_ACTIVE_STATUS,
                        C\ADVERTISEMENT_DEACTIVATED_STATUS];
                    $admin_ad_statuses = [C\ADVERTISEMENT_ACTIVE_STATUS,
                        C\ADVERTISEMENT_SUSPENDED_STATUS];
                    if ($user_id == $ad['USER_ID'] && !in_array(
                        $data['status'], $user_ad_statuses)) {
                        break;
                    } else if ($user_id != $ad['USER_ID'] &&
                        $is_admin && !in_array(
                        $data['status'], $admin_ad_statuses)) {
                        break;
                    }
                    $result = $advertisement_model->setAdvertisementStatus(
                        $data['id'], $data['status']);
                    if ($result) {
                    $preserve = ["start_row", "end_row", "num_show"];
                        if (!empty($_REQUEST['context'])) {
                            $_REQUEST['arg'] = 'search';
                            $preserve[] = 'arg';
                        }
                        return $parent->redirectWithMessage(tl(
                            tl('store_component_status_changed')),
                            array_merge($preserve, $request_fields));
                    }
                }
                break;
            case "editadvertisement":
                $data["FORM_TYPE"] = "editadvertisement";
                $update = false;
                $data['SCRIPT'] .= "elt('focus-button').focus();";
                if (isset($_REQUEST['save'])) {
                    $update = true;
                }
                if (isset($_REQUEST['id'])) {
                    $ad = $advertisement_model->getAdvertisementById(
                        $data['id']);
                    $ad_fields = ["NAME", "DESTINATION",
                        "DESCRIPTION","BUDGET","KEYWORDS",
                        "START_DATE", 'END_DATE'];
                    if (!empty($ad) && ($user_id == $ad['USER_ID'] ||
                        $is_admin)) {
                        foreach ($ad_fields as $field) {
                            $data[$field] = isset($data[$field])  ?
                                $data[$field] : $ad[$field];
                        }
                        if ($is_admin) {
                            $data['AD_USER_NAME'] = $user_model->getUsername(
                                $ad['USER_ID']);
                        }
                        if ($update) {
                            $updated_advertisement = [];
                            $ad_update_fields = ["NAME",
                                "DESCRIPTION","DESTINATION"];
                            foreach ($ad_update_fields as $field) {
                                if (isset($_REQUEST[$field])) {
                                    $updated_advertisement[$field] =
                                        $data[$field];
                                }
                            }
                            $advertisement_model->updateAdvertisement(
                                $updated_advertisement, $data['id']);
                            foreach ($request_fields as $field) {
                                unset($data[$field]);
                            }
                            unset($data['START_DATE']);
                            unset($data['END_DATE']);
                            return $parent->redirectWithMessage(
                                tl('store_component_ad_updated'),
                                ["arg", "id", "start_row", "num_show",
                                "end_row", "context"]);
                        }
                    }
                }
                break;
            case "search":
                $data["FORM_TYPE"] = "search";
                $search_array =
                    $parent->tableSearchRequestHandler($data,
                        "manageAdvertisements",
                        ['ALL_FIELDS' => ['name', 'description', 'destination',
                        'keywords', 'budget', 'start_date', 'end_date'],
                        'BETWEEN_COMPARISON_TYPES' => ['budget', 'start_date',
                        'end_date']]);
                if (empty($_SESSION['LAST_SEARCH']['manageAdvertisements']) ||
                    isset($_REQUEST['name'])) {
                    $_SESSION['LAST_SEARCH']['manageAdvertisements'] =
                        $_SESSION['SEARCH']['manageAdvertisements'];
                    unset($_SESSION['SEARCH']['manageAdvertisements']);
                } else {
                    $default_search = true;
                }
                break;
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['manageAdvertisements'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'manageAdvertisements');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH']['manageAdvertisements'][
                        'SEARCH_ARRAY'];
                    $data['PAGING'] = $_SESSION['LAST_SEARCH'][
                        'manageAdvertisements']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["id", "", "", "DESC"];
            }
        }
        if (!$_SERVER["MOBILE"]) {
            $data['SCRIPT'] .= "\npreview(elt('ad-name'))\n" .
                "preview(elt('ad-description'))\n".
                "preview(elt('ad-destination'), 'ad-name')\n";
        }
        $parent->pagingLogic($data, $advertisement_model, "ADVERTISEMENTS",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            ["USER_ID" => $user_id, "ADMIN" => $is_admin]);
        if ($data['FORM_TYPE'] == 'addadvertisement') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row',
                $initial_display_state);";
        }
        return $data;
    }
    /**
     * Sets up the $data['AD_KEYWORD'] as an associative array of
     * (keyword, day) => bid_amounts based on min bid for that ad keyword on
     * that day. Set up $data['EXPENSIVE_KEYWORD'] as the most expensive
     * ad keyword for the dates in question and also sets up $data['AD_MIN_BID']
     * as the minimum bid required for the dates in question
     *
     * @param array &$data associative array of data used by the view to
     *      draw itself
     * @param int $start_date state date in seconds since beginning of Unix
     *  epoch
     * @param int $day_count number of days ad campaign will last
     */
    public function initializeAdKeywords(&$data, $start_date, $day_count)
    {
        $parent = $this->parent;
        $keywords = explode("," , strtoupper($data['KEYWORDS']));
        array_walk($keywords, [C\NS_COMPONENTS .
            "StoreComponent", "trim_value"]);
        $min_bid_reqd = 0;
        $expensive_bid = 0;
        foreach ($keywords as $keyword) {
            $date = date(C\AD_DATE_FORMAT, $start_date);
            $keyword_bid_amount = 0;
            for ($k = 0; $k < $day_count; $k++) {
                $bid_amount = $parent->model('advertisement')->getBidAmount(
                    $keyword, $date);
                $half_bid = ceil($bid_amount/2);
                if ($bid_amount > C\AD_KEYWORD_INIT_BID ) {
                    $min_bid_reqd += $half_bid;
                    $data['AD_KEYWORDS'][$keyword][$date] =
                        $half_bid;
                    $keyword_bid_amount += $half_bid;
                } else {
                    $min_bid_reqd += $bid_amount;
                    $data['AD_KEYWORDS'][$keyword][$date] =
                        $half_bid;
                    $keyword_bid_amount += $half_bid;
                }
                $date = date(C\AD_DATE_FORMAT, strtotime($date .' +1 day'));
            }
            if ($keyword_bid_amount >= $expensive_bid) {
                $expensive_bid = $keyword_bid_amount;
                $data['EXPENSIVE_KEYWORD'] = $keyword;
            }
        }
        $data['AD_MIN_BID'] = $min_bid_reqd;
    }
    /**
     * Trim white spaces callback for array_walk
     *
     * @param string &$value string to remove initial and trailing whitespace
     *      from
     */
    public function trim_value(&$value)
    {
        $value = trim($value);
    }
}
