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
use seekquarry\yioop\library\classifiers\Classifier;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\PageRuleParser;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\media_jobs as M;
use seekquarry\yioop\library\processors as P;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * This component is used to provide activities for the admin controller
 * related to configuring and performing a web or archive crawl
 *
 * @author Chris Pollett
 */
class CrawlComponent extends Component implements CrawlConstants
{
    /**
     * Maximum number of search result fragments in a crawl mix
     */
    const MAX_MIX_FRAGMENTS = 10;
    /**
     * Used to handle the manage crawl activity.
     *
     * This activity allows new crawls to be started, statistics about old
     * crawls to be seen. It allows a user to stop the current crawl or
     * restart an old crawl. It also allows a user to configure the options
     * by which a crawl is conducted
     *
     * @return array $data information and statistics about crawls in the
     *     system as well as status messages on performing a given sub activity
     */
    public function manageCrawls()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $possible_arguments =
            ["delete", "index", "options", "querystats", "resume", "start",
            "statistics", "stop"];
        $need_machines_arguments = ["delete", "options", "resume", "statistics",
            "start", "stop"];
        $data["ELEMENT"] = "managecrawls";
        $data['SCRIPT'] = "doUpdate();";
        $request_fields = ['start_row', 'num_show', 'end_row'];
        $flag = 0;
        foreach ($request_fields as $field) {
            $data[strtoupper($field)] = isset($_REQUEST[$field]) ? max(0,
                $parent->clean($_REQUEST[$field], 'int')) :
                (isset($data['NUM_SHOW']) ? $data['NUM_SHOW'] :
                $flag * C\DEFAULT_ADMIN_PAGING_NUM);
            $flag = 1;
        }
        $arg = (empty($_REQUEST['arg'])) ? "-1" : $_REQUEST['arg'];
        if (in_array($arg, $possible_arguments)) {
            if (in_array($arg, $need_machines_arguments)) {
                $timestamp = 0;
                if (isset($_REQUEST['timestamp'])) {
                     $timestamp = substr($parent->clean(
                        $_REQUEST['timestamp'], "int"), 0,
                        C\TIMESTAMP_LEN);
                }
                $machine_urls = $parent->model("machine")->getQueueServerUrls(
                    $timestamp);
                $num_machines = count($machine_urls);
                if ($num_machines <  1 || ($num_machines ==  1 &&
                    UrlParser::isLocalhostUrl($machine_urls[0]))) {
                    $machine_urls = null;
                }
            }
            switch ($arg) {
                case "delete":
                    if ($timestamp != 0) {
                         $crawl_model->deleteCrawl($timestamp,
                            $machine_urls);
                        return $parent->redirectWithMessage(
                            tl('crawl_component_delete_crawl_success'),
                            $request_fields);
                     } else {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_delete_crawl_fail'),
                            $request_fields);
                     }
                    break;
                case "index":
                    $timestamp = substr($parent->clean($_REQUEST['timestamp'],
                        "int"), 0,  C\TIMESTAMP_LEN);
                    $crawl_model->setCurrentIndexDatabaseName($timestamp);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_set_index'),
                        $request_fields);
                case "options":
                    $this->editCrawlOption($data, $machine_urls);
                    break;
                case "querystats":
                    $data["ELEMENT"] = "querystats";
                    $data["leftorright"] = (L\getLocaleDirection() == 'ltr') ?
                        "right": "left";
                    $impression_model = $parent->model("impression");
                    $periods = [C\ONE_HOUR, C\ONE_DAY, C\ONE_MONTH, C\ONE_YEAR,
                        C\FOREVER];
                    $filter = (empty($_REQUEST['filter'])) ? "" :
                        $parent->clean($_REQUEST['filter'], 'string');
                    $data['FILTER'] = $filter;
                    foreach ($periods as $period) {
                        $data["STATISTICS"][$period] =
                            $impression_model->getStatistics(C\QUERY_IMPRESSION,
                                $period, $filter);
                    }
                    /* Add Differential Privacy for query
                       statistics if enabled */
                    if (C\DIFFERENTIAL_PRIVACY) {
                        $i = 0;
                        foreach ($periods as $period) {
                            if (!empty($data["STATISTICS"][$period])) {
                                foreach ($data['STATISTICS'][$period] as
                                    $item_name => $item_data) {
                                    $view_stat =
                                        $impression_model->getImpressionStat(
                                        $item_data[0]['ID'], C\QUERY_IMPRESSION,
                                        $period);
                                    $fuzzy_views = $view_stat[1];
                                    if (empty($view_stat[0]) || $view_stat[0]!=
                                        $item_data[0]['NUM_VIEWS'] ||
                                        $tmp_data[$item_name][$i - 1] >
                                        $fuzzy_views) {
                                        $fuzzy_views =
                                            $parent->addDifferentialPrivacy(
                                            $item_data[0]['NUM_VIEWS']);
                                        /* Make sure each time period's
                                        fuzzified view is at least as large as
                                        previous time period's value */
                                        if ($i > 0) {
                                            if ($tmp_data[$item_name][$i-1] >
                                                $fuzzy_views) {
                                                $fuzzy_views =
                                                    $tmp_data[$item_name][$i-1];
                                            }
                                        }
                                        $impression_model->updateImpressionStat(
                                            $item_data[0]['ID'],
                                            C\QUERY_IMPRESSION, $period,
                                            $item_data[0]['NUM_VIEWS'],
                                            $fuzzy_views);
                                    }
                                    $data["STATISTICS"][
                                        $period][$item_name][0]['NUM_VIEWS'] =
                                        ($fuzzy_views == 0) ?
                                        tl('managegroups_element_no_activity') :
                                        $fuzzy_views;
                                    $tmp_data[$item_name][$i] = $fuzzy_views;
                                }
                                $i++;
                            }
                        }
                    }
                    break;
                case "resume":
                    $crawl_params = [];
                    $crawl_params[self::STATUS] = "RESUME_CRAWL";
                    $crawl_params[self::CRAWL_TIME] =
                        substr($parent->clean($_REQUEST['timestamp'], "int"), 0,
                        C\TIMESTAMP_LEN);
                    $seed_info = $crawl_model->getCrawlSeedInfo(
                        $crawl_params[self::CRAWL_TIME], $machine_urls);
                    $this->getCrawlParametersFromSeedInfo($crawl_params,
                        $seed_info);
                    $crawl_params[self::TOR_PROXY] = C\TOR_PROXY;
                    if (C\USE_PROXY) {
                        $crawl_params[self::PROXY_SERVERS] =
                            explode("|Z|", C\PROXY_SERVERS);
                    }
                   /*
                       Write the new crawl parameters to the name server, so
                       that it can pass them along in the case of a new archive
                       crawl.
                    */
                    $crawl_params[self::CHANNEL] =
                        empty($crawl_params[self::CHANNEL]) ?
                        0 : $crawl_params[self::CHANNEL];
                    $filename = C\CRAWL_DIR . "/schedules/".
                        $crawl_params[self::CHANNEL] .
                        "-NameServerMessages.txt";
                    $parent->web_site->filePutContents($filename,
                        serialize($crawl_params));
                    chmod($filename, 0777);
                    if($crawl_model->sendStartCrawlMessage($crawl_params,
                        null, $machine_urls)) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_resume_crawl'),
                            $request_fields);
                    }
                    return $parent->redirectWithMessage(
                        tl('crawl_component_resume_fail'), $request_fields);
                case "start":
                    $this->startCrawl($data, $request_fields);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_starting_new_crawl'),
                        $request_fields);
                case "statistics":
                    $data["ELEMENT"] = "statistics";
                    $data["leftorright"] = (L\getLocaleDirection() == 'ltr') ?
                        "right": "left";
                    $index = (empty($_REQUEST['its'])) ? "" :
                        substr($parent->clean($_REQUEST['its'], "string"), 0,
                        C\TIMESTAMP_LEN);
                    /*
                       validate timestamp against list
                      (some crawlers replay deleted crawls)
                     */
                    if ($index) {
                        $crawls = $crawl_model->getCrawlList(false, true,
                            $machine_urls, true);
                        $found_crawl = false;
                        foreach ($crawls as $crawl) {
                            if ($index == $crawl['CRAWL_TIME']) {
                                $found_crawl = true;
                                break;
                            }
                        }
                        $index = ($found_crawl) ? $index : false;
                    }
                    if (!$index) {
                        include(C\BASE_DIR . "/error.php");
                        \seekquarry\yioop\library\webExit(); //bail
                    }
                    $data['its'] = $index;
                    $this->crawlStatistics($data, $machine_urls);
                    if (!empty($_REQUEST['recompute'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_recomputing_stats',
                            $data['its']));
                    }
                    break;
                case "stop":
                    $channel = (!isset($_REQUEST['channel'])) ? 0 :
                        $parent->clean($_REQUEST['channel'], "int");
                    $crawl_param_file = C\CRAWL_DIR .
                        "/schedules/$channel-crawl_params.txt";
                    if (file_exists($crawl_param_file)) {
                        unlink($crawl_param_file);
                    }
                    $info = [];
                    $info[self::STATUS] = "STOP_CRAWL";
                    $filename = C\CRAWL_DIR .
                        "/schedules/$channel-NameServerMessages.txt";
                    $parent->web_site->filePutContents($filename,
                        serialize($info));
                    $crawl_model->sendStopCrawlMessage($channel, $machine_urls);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_stop_crawl'), $request_fields);
            }
        }
        $data['INCLUDE_SCRIPTS'][] = 'help';
        return $data;
    }
    /**
     * Handles admin request related to the editing a crawl mix activity
     *
     * @param array $data info about the fragments and their contents for a
     *     particular crawl mix (changed by this method)
     */
    public function editMix(&$data)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $data["leftorright"] =
            (L\getLocaleDirection() == 'ltr') ? "right": "left";
        $data["ELEMENT"] = "editmix";
        $user_id = $_SESSION['USER_ID'];
        $mix = [];
        $timestamp = 0;
        if (isset($_REQUEST['timestamp'])) {
            $timestamp = substr($parent->clean($_REQUEST['timestamp'], "int"),
                0, C\TIMESTAMP_LEN);
        } else if (isset($_REQUEST['mix']['TIMESTAMP'])) {
            $timestamp = substr(
                $parent->clean($_REQUEST['mix']['TIMESTAMP'], "int"),
                0, C\TIMESTAMP_LEN);
        }
        if (!$crawl_model->isCrawlMix($timestamp)) {
            $_REQUEST['a'] = "mixCrawls";
            return $parent->redirectWithMessage(
                tl('social_component_mix_invalid_timestamp'));
        }
        if (!$crawl_model->isMixOwner($timestamp, $user_id)) {
            $_REQUEST['a'] = "mixCrawls";
            return $parent->redirectWithMessage(
                tl('social_component_mix_not_owner'));
        }
        $mix = $crawl_model->getCrawlMix($timestamp);
        $owner_id = $mix['OWNER_ID'];
        $parent_id = $mix['PARENT'];
        $data['MIX'] = $mix;
        $data['INCLUDE_SCRIPTS'] = ["mix"];
        //set up an array of translation for javascript-land
        $data['SCRIPT'] .= "tl = {".
            'social_component_add_crawls:"'.
                tl('social_component_add_crawls') .
            '",' . 'social_component_num_results:"'.
                tl('social_component_num_results').'",'.
            'social_component_del_frag:"'.
                tl('social_component_del_frag').'",'.
            'social_component_weight:"'.
                tl('social_component_weight').'",'.
            'social_component_name:"'.tl('social_component_name').'",'.
            'social_component_order:"'.
                tl('social_component_order').'",'.
            'social_component_ascending:"'.
                tl('social_component_ascending').'",'.
            'social_component_descending:"'.
                tl('social_component_descending').'",'.
            'social_component_add_keywords:"'.
                tl('social_component_add_keywords').'",'.
            'social_component_actions:"'.
                tl('social_component_actions').'",'.
            'social_component_add_query:"'.
                tl('social_component_add_query').'",'.
            'social_component_delete:"'.tl('social_component_delete').'"'.
            '};';
        //clean and save the crawl mix sent from the browser
        if (isset($_REQUEST['update']) && $_REQUEST['update'] ==
            "update") {
            $mix = $_REQUEST['mix'];
            $mix['TIMESTAMP'] = $timestamp;
            $mix['OWNER_ID']= $owner_id;
            $mix['PARENT'] = $parent_id;
            $mix['NAME'] = $parent->clean($mix['NAME'], "string");
            $comp = [];
            $save_mix = false;
            if (isset($mix['FRAGMENTS'])) {
                if ($mix['FRAGMENTS'] != null && count($mix['FRAGMENTS']) <
                    self::MAX_MIX_FRAGMENTS) {
                    foreach ($mix['FRAGMENTS'] as
                        $fragment_id => $fragment_data) {
                        if (isset($fragment_data['RESULT_BOUND'])) {
                            $mix['FRAGMENTS'][$fragment_id]['RESULT_BOUND'] =
                                $parent->clean($fragment_data['RESULT_BOUND'],
                                    "int");
                        } else {
                            $mix['FRAGMENTS']['RESULT_BOUND'] = 0;
                        }
                        if (isset($fragment_data['COMPONENTS'])) {
                            $comp = [];
                            foreach ($fragment_data['COMPONENTS'] as
                                $component) {
                                $row = [];
                                $row['CRAWL_TIMESTAMP'] =
                                    $parent->clean(
                                        $component['CRAWL_TIMESTAMP'], "int");
                                $row['WEIGHT'] = $parent->clean(
                                    $component['WEIGHT'], "float");
                                $row['DIRECTION'] = ($parent->clean(
                                    $component['DIRECTION'], "int") > 0) ? 1 :
                                    -1;
                                $row['KEYWORDS'] = $parent->clean(
                                    $component['KEYWORDS'],
                                    "string");
                                $comp[] =$row;
                            }
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS']=$comp;
                        } else {
                            $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'] =
                                [];
                        }
                    }
                    $save_mix = true;
                } else if (count($mix['FRAGMENTS']) >=
                    self::MAX_MIX_FRAGMENTS) {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                    return $parent->redirectWithMessage(
                        tl('social_component_too_many_fragments'));
                } else {
                    $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
                }
            } else {
                $mix['FRAGMENTS'] = $data['MIX']['FRAGMENTS'];
            }
            if ($save_mix) {
                $data['MIX'] = $mix;
                $crawl_model->setCrawlMix($mix);
                $preserve = [];
                if (!empty($_REQUEST['context']) &&
                    $_REQUEST['context'] == 'search') {
                    $_REQUEST['arg'] = 'search';
                    $preserve = ['arg'];
                }
                return $parent->redirectWithMessage(
                    tl('social_component_mix_saved'), $preserve);
            }
        }
        $data['SCRIPT'] .= 'fragments = [';
        $not_first = "";
        foreach ($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $data['SCRIPT'] .= $not_first . '{';
            $not_first= ",";
            if (isset($fragment_data['RESULT_BOUND'])) {
                $data['SCRIPT'] .= "num_results:".
                    $fragment_data['RESULT_BOUND'];
            } else {
                $data['SCRIPT'] .= "num_results:1 ";
            }
            $data['SCRIPT'] .= ", components:[";
            if (isset($fragment_data['COMPONENTS'])) {
                $comma = "";
                foreach ($fragment_data['COMPONENTS'] as $component) {
                    $crawl_ts = $component['CRAWL_TIMESTAMP'];
                    $crawl_name = $data['available_crawls'][$crawl_ts];
                    $data['SCRIPT'] .= $comma." [$crawl_ts, '$crawl_name', ".
                        $component['WEIGHT'].", ".$component['DIRECTION'].", ";
                    $comma = ",";
                    $keywords = (isset($component['KEYWORDS'])) ?
                        $component['KEYWORDS'] : "";
                    $data['SCRIPT'] .= "'$keywords'] ";
                }
            }
            $data['SCRIPT'] .= "] }";
        }
        $data['SCRIPT'] .= ']; drawFragments();';
    }
    /**
     * Handles admin request related to the crawl mix activity
     *
     * The crawl mix activity allows a user to create/edit crawl mixes:
     * weighted combinations of search indexes
     *
     * @return array $data info about available crawl mixes and changes to them
     *     as well as any messages about the success or failure of a
     *     sub activity.
     */
    public function mixCrawls()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $user_model = $parent->model("user");
        $possible_arguments = [ "createmix", "deletemix", "editmix", "index",
            "search"];
        $data["ELEMENT"] = "mixcrawls";
        $user_id = $_SESSION['USER_ID'];
        $data['mix_default'] = 0;
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if ($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = null;
        }
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['available_crawls'][0] = tl('social_component_select_crawl');
        $data['available_crawls'][1] = tl('social_component_default_crawl');
        $data['SCRIPT'] = "c = [];c[0]='".
            tl('social_component_select_crawl') . "';";
        $data['SCRIPT'] .= "c[1]='".
            tl('social_component_default_crawl') . "';";
        foreach ($crawls as $crawl) {
            $data['available_crawls'][$crawl['CRAWL_TIME']] =
                $crawl['DESCRIPTION'];
            $data['SCRIPT'] .= 'c['.$crawl['CRAWL_TIME'].']="' .
                $crawl['DESCRIPTION'] . '";';
        }
        $search_array = [];
        $can_manage_crawls = $user_model->isAllowedUserActivity(
                $_SESSION['USER_ID'], "manageCrawls");
        $data['PAGING'] = "";
        $data['FORM_TYPE'] = "addmix";
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "createmix":
                    $mix['TIMESTAMP'] = time();
                    if (isset($_REQUEST['NAME'])) {
                        $mix['NAME'] = substr(trim($parent->clean(
                            $_REQUEST['NAME'], 'string')), 0, C\NAME_LEN);
                    } else {
                        $mix['NAME'] = "";
                    }
                    if ($mix['NAME'] &&
                        !$crawl_model->getCrawlMixTimestamp($mix['NAME'])) {
                        $mix['FRAGMENTS'] = [];
                        $mix['OWNER_ID'] = $user_id;
                        $mix['PARENT'] = -1;
                        $crawl_model->setCrawlMix($mix);
                        return $parent->redirectWithMessage(
                            tl('social_component_mix_created'));
                    } else {
                        return $parent->redirectWithMessage(
                            tl('social_component_invalid_name'));
                    }
                    break;
                case "deletemix":
                    if (!isset($_REQUEST['timestamp'])||
                        !$crawl_model->isMixOwner($_REQUEST['timestamp'],
                            $user_id)) {
                        return $parent->redirectWithMessage(
                            tl('social_component_mix_invalid_timestamp'));
                    }
                    $crawl_model->deleteCrawlMix($_REQUEST['timestamp']);
                    $preserve = [];
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $preserve[] = 'arg';
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_mix_deleted'), $preserve);
                case "editmix":
                    //$data passed by reference
                    $this->editMix($data);
                    if (!empty($_REQUEST['context'])) {
                        $data['context'] = 'search';
                    }
                    break;
                case "index":
                    $timestamp = substr(
                        $parent->clean($_REQUEST['timestamp'], "int"), 0,
                        C\TIMESTAMP_LEN);
                    if ($can_manage_crawls) {
                        $crawl_model->setCurrentIndexDatabaseName(
                            $timestamp);
                    } else {
                        $_SESSION['its'] = $timestamp;
                        $user_model->setUserSession($user_id, $_SESSION);
                    }
                    $preserve = [];
                    if (!empty($_REQUEST['context'])) {
                        $_REQUEST['arg'] = 'search';
                        $preserve[] = 'arg';
                    }
                    return $parent->redirectWithMessage(
                        tl('social_component_set_index'), $preserve);
                case "search":
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                            "mixCrawls", ['ALL_FIELDS' => ['name']]);
                    if (empty($_SESSION['LAST_SEARCH']['mixCrawls']) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['mixCrawls'] =
                            $_SESSION['SEARCH']['mixCrawls'];
                        unset($_SESSION['SEARCH']['mixCrawls']);
                    } else {
                        $default_search = true;
                    }
                    break;
            }
        }
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['mixCrawls'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'mixCrawls');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH']['mixCrawls']['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['mixCrawls']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["name", "", "", "ASC"];
            }
        }
        if ($data['FORM_TYPE'] == 'addmix') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        /*Currently,allow all users who can use crawl mixes access
          to all mixes, to restrict this add:
          $search_array[] = ["owner_id", "=", $user_id, ""];
         */
        $parent->pagingLogic($data, $crawl_model, "available_mixes",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "", true);
        if (!$can_manage_crawls && isset($_SESSION['its'])) {
            $crawl_time = $_SESSION['its'];
        } else {
            $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        }
        if (isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }
        return $data;
    }
    /**
     * Called from @see manageCrawls to start a new crawl on the machines
     * $machine_urls. Updates $data array with crawl start message
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $request_fields if start crawl fails this is a list of
     *      request fields to preserve in the redirect message
     */
    public function startCrawl(&$data, $request_fields)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $crawl_params = [];
        $crawl_params[self::STATUS] = "NEW_CRAWL";
        $crawl_params[self::CRAWL_TIME] = time();
        $seed_info = $crawl_model->getSeedInfo();
        $this->getCrawlParametersFromSeedInfo($crawl_params, $seed_info);
        $machine_urls = $parent->model("machine")->getQueueServerUrls(0,
            $crawl_params[self::CHANNEL]);
        if ($crawl_params[self::CHANNEL] > 0 && empty($machine_urls)) {
            $parent->redirectWithMessage(
                tl('crawl_component_fail_no_machines_channel'),
                $request_fields);
        }
        if (isset($_REQUEST['description']) &&
            !empty(trim($_REQUEST['description']))) {
            $description = substr(
                $parent->clean(trim($_REQUEST['description']), "string"), 0,
                C\TITLE_LEN);
        } else {
            $parent->redirectWithMessage(
                tl('crawl_component_fail_no_description'),
                $request_fields);
        }
        $crawl_params['DESCRIPTION'] = $description;
        $crawl_params[self::TOR_PROXY] = C\TOR_PROXY;
        if (C\USE_PROXY) {
            $crawl_params[self::PROXY_SERVERS] = explode("|Z|",
                C\PROXY_SERVERS);
        }
        if (isset($crawl_params[self::INDEXING_PLUGINS]) &&
            is_array($crawl_params[self::INDEXING_PLUGINS])) {
            foreach ($crawl_params[self::INDEXING_PLUGINS] as $plugin) {
                if ($plugin == "") {
                    continue;
                }
                $plugin_class = C\NS_PLUGINS . $plugin . "Plugin";
                $plugin_obj = $parent->plugin(lcfirst($plugin));
                if (method_exists($plugin_class, "loadConfiguration")) {
                    $crawl_params[self::INDEXING_PLUGINS_DATA][$plugin] =
                        $plugin_obj->loadConfiguration();
                }
            }
        }
        /*
           Write the new crawl parameters to the name server, so
           that it can pass them along in the case of a new archive
           crawl.
        */
        $crawl_params[self::CHANNEL] = empty($crawl_params[self::CHANNEL]) ?
            0 : $crawl_params[self::CHANNEL];
        $filename = C\CRAWL_DIR .
            "/schedules/{$crawl_params[self::CHANNEL]}-NameServerMessages.txt";
        $parent->web_site->filePutContents($filename, serialize($crawl_params));
        chmod($filename, 0777);
        if(!$crawl_model->sendStartCrawlMessage($crawl_params,
            $seed_info, $machine_urls)) {
            $parent->redirectWithMessage(
                tl('crawl_component_start_fail'),
                $request_fields);
        }
    }
    /**
     * Reads the parameters for a crawl from an array gotten from a crawl.ini
     * file
     *
     * @param array &$crawl_params parameters to write to queue_server
     * @param array $seed_info data from crawl.ini file
     */
    public function getCrawlParametersFromSeedInfo(&$crawl_params, $seed_info)
    {
        $parent = $this->parent;
        $crawl_params[self::CRAWL_TYPE] = $seed_info['general']['crawl_type'];
        $crawl_params[self::CRAWL_INDEX] =
            $seed_info['general']['crawl_index'] ?? '';
        $crawl_params[self::CHANNEL] =
            $seed_info['general']['channel'] ?? '';
        $crawl_params[self::ARC_DIR]=
            $seed_info['general']['arc_dir'] ?? '';
        $crawl_params[self::ARC_TYPE] =
            $seed_info['general']['arc_type'] ?? '';
        $crawl_params[self::CACHE_PAGES] =
            intval($seed_info['general']['cache_pages']) ?? true;
        $crawl_params[self::PAGE_RANGE_REQUEST] =
            intval($seed_info['general']['page_range_request']) ??
            C\PAGE_RANGE_REQUEST;
        $crawl_params[self::MAX_DESCRIPTION_LEN] =
            intval($seed_info['general']['max_description_len']) ??
            C\MAX_DESCRIPTION_LEN;
        $crawl_params[self::MAX_LINKS_TO_EXTRACT] =
            intval($seed_info['general']['max_links_to_extract']) ??
            C\MAX_LINKS_TO_EXTRACT;
        $crawl_params[self::PAGE_RECRAWL_FREQUENCY] =
            intval($seed_info['general']['page_recrawl_frequency']) ??
            C\PAGE_RECRAWL_FREQUENCY;
        $crawl_params[self::TO_CRAWL] = $seed_info['seed_sites']['url'];
        $crawl_params[self::CRAWL_ORDER] = $seed_info['general']['crawl_order'];
        $crawl_params[self::MAX_DEPTH] = $seed_info['general']['max_depth'];
        $crawl_params[self::REPEAT_TYPE] = $seed_info['general']['repeat_type'];
        $crawl_params[self::SLEEP_START] = $seed_info['general']['sleep_start'];
        $crawl_params[self::SLEEP_DURATION] =
            $seed_info['general']['sleep_duration'];
        $crawl_params[self::ROBOTS_TXT] = $seed_info['general']['robots_txt'];
        $crawl_params[self::RESTRICT_SITES_BY_URL] =
            $seed_info['general']['restrict_sites_by_url'];
        $crawl_params[self::ALLOWED_SITES] =
            $seed_info['allowed_sites']['url'] ?? [];
        $crawl_params[self::DISALLOWED_SITES] =
            $seed_info['disallowed_sites']['url'] ?? [];
        if (isset($seed_info['indexed_file_types']['extensions'])) {
            $crawl_params[self::INDEXED_FILE_TYPES] =
                $seed_info['indexed_file_types']['extensions'];
        }
        if (isset($seed_info['general']['summarizer_option'])) {
            $crawl_params[self::SUMMARIZER_OPTION] =
                $seed_info['general']['summarizer_option'];
        }
        if (isset($seed_info['active_classifiers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_CLASSIFIERS] =
                $seed_info['active_classifiers']['label'];
        }
        if (isset($seed_info['active_rankers']['label'])) {
            // Note that 'label' is actually an array of active class labels.
            $crawl_params[self::ACTIVE_RANKERS] =
                $seed_info['active_rankers']['label'];
        }
        if (isset($seed_info['indexing_plugins']['plugins'])) {
            $crawl_params[self::INDEXING_PLUGINS] =
                $seed_info['indexing_plugins']['plugins'];
        }
        $crawl_params[self::PAGE_RULES] =
            $seed_info['page_rules']['rule'] ?? [];
    }
    /**
     * Called from @see manageCrawls to edit the parameters for the next
     * crawl (or current crawl) to be carried out by the machines
     * $machine_urls. Updates $data array to be supplied to AdminView
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls string urls of machines managed by this
     * Yioop name server on which to perform the crawl
     */
    public function editCrawlOption(&$data, $machine_urls)
    {
        $parent = $this->parent;
        $crawl_model= $parent->model("crawl");
        $machine_model = $parent->model("machine");
        $data["leftorright"] = (L\getLocaleDirection() == 'ltr') ?
            "right": "left";
        $data["ELEMENT"] = "crawloptions";
        $crawls = $crawl_model->getCrawlList(false, false,
            $machine_urls);
        $indexes = $crawl_model->getCrawlList(true, true, $machine_urls);
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = L\remoteAddress();
        }
        $mixes = $crawl_model->getMixList($user, false);
        foreach ($mixes as $mix) {
            $tmp = [];
            $tmp["DESCRIPTION"] = "MIX::".$mix["NAME"];
            $tmp["CRAWL_TIME"] = $mix["TIMESTAMP"];
            $tmp["ARC_DIR"] = "MIX";
            $tmp["ARC_TYPE"] = "MixArchiveBundle";
            $indexes[] = $tmp;
        }
        $add_message = "";
        $indexes_by_crawl_time = [];
        $update_flag = false;
        $data['available_options'] = [
            tl('crawl_component_use_below'),
            tl('crawl_component_use_defaults')];
        $data['available_crawl_indexes'] = [];
        $data['INJECT_SITES'] = "";
        $data['options_default'] = tl('crawl_component_use_below');
        foreach ($crawls as $crawl) {
            if (strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    tl('crawl_component_previous_crawl')." ".
                    $crawl['DESCRIPTION'];
            }
        }
        foreach ($indexes as $i => $crawl) {
            $data['available_crawl_indexes'][$crawl['CRAWL_TIME']]
                = $crawl['DESCRIPTION'];
            $indexes_by_crawl_time[$crawl['CRAWL_TIME']] =& $indexes[$i];
        }
        $no_further_changes = false;
        $seed_current = $crawl_model->getSeedInfo();
        if (isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] == 1) {
            $seed_info = $crawl_model->getSeedInfo(true);
            if (isset(
                $seed_current['general']['page_range_request'])) {
                $seed_info['general']['page_range_request'] =
                    $seed_current['general']['page_range_request'];
            }
            if (isset(
                $seed_current['general']['page_recrawl_frequency'])
                ) {
                $seed_info['general']['page_recrawl_frequency'] =
                $seed_current['general']['page_recrawl_frequency'];
            }
            if (isset(
                $seed_current['general']['max_description_len'])) {
                $seed_info['general']['max_description_len'] =
                    $seed_current['general']['max_description_len'];
            }
            $update_flag = true;
            $no_further_changes = true;
        } else if (isset($_REQUEST['load_option']) &&
            $_REQUEST['load_option'] > 1 ) {
            $timestamp =
                $parent->clean($_REQUEST['load_option'], "int");
            $seed_info = $crawl_model->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            if (isset(
                $seed_current['general']['page_range_request'])) {
                $seed_info['general']['page_range_request'] =
                    $seed_current['general']['page_range_request'];
            }
            if (isset(
                $seed_current['general']['page_recrawl_frequency'])
                ) {
                $seed_info['general']['page_recrawl_frequency'] =
                $seed_current['general']['page_recrawl_frequency'];
            }
            if (isset(
                $seed_current['general']['max_description_len'])) {
                $seed_info['general']['max_description_len'] =
                    $seed_current['general']['max_description_len'];
            }
            $update_flag = true;
            $no_further_changes = true;
        } else if (isset($_REQUEST['ts'])) {
            $timestamp = substr($parent->clean($_REQUEST['ts'], "int"), 0,
                C\TIMESTAMP_LEN);
            $seed_info = $crawl_model->getCrawlSeedInfo(
                $timestamp, $machine_urls);
            $data['ts'] = $timestamp;
        } else {
            $seed_info = $crawl_model->getSeedInfo();
        }
        if (!C\DIRECT_ADD_SUGGEST &&
            isset($_REQUEST['suggest']) && $_REQUEST['suggest'] == 'add') {
            $suggest_urls = $crawl_model->getSuggestSites();
            if (isset($_REQUEST['ts'])) {
                $new_urls = [];
            } else {
                $seed_info['seed_sites']['url'][] = "#\n#".
                    tl('crawl_component_added_urls', date('r'))."\n#";
                $crawl_model->clearSuggestSites();
            }
            foreach ($suggest_urls as $suggest_url) {
                $suggest_url = trim($suggest_url);
                if (!in_array($suggest_url, $seed_info['seed_sites']['url'])
                    && strlen($suggest_url) > 0) {
                    if (isset($_REQUEST['ts'])) {
                        $new_urls[] = $suggest_url;
                    } else {
                        $seed_info['seed_sites']['url'][] = $suggest_url;
                    }
                }
            }
            $add_message= tl('crawl_component_add_suggest');
            if (isset($_REQUEST['ts'])) {
                $data["INJECT_SITES"] = $parent->convertArrayLines($new_urls);
                if ($data["INJECT_SITES"] == "") {
                    $add_message= tl('crawl_component_no_new_suggests');
                }
            }
            $update_flag = true;
            $no_further_changes = true;
        }
        $page_options_properties = ['indexed_file_types',
            'active_classifiers', 'page_rules', 'indexing_plugins'];
        //these properties should be changed under page_options not here
        foreach ($page_options_properties as $property) {
            if (isset($seed_current[$property])) {
                $seed_info[$property] = $seed_current[$property];
            }
        }
        if (!$no_further_changes && isset($_REQUEST['crawl_indexes'])
            && in_array($_REQUEST['crawl_indexes'],
            array_keys($data['available_crawl_indexes']))) {
            $seed_info['general']['crawl_index'] = $_REQUEST['crawl_indexes'];
            $index_data = $indexes_by_crawl_time[$_REQUEST['crawl_indexes']];
            if (isset($index_data['ARC_DIR'])) {
                $seed_info['general']['arc_dir'] = $index_data['ARC_DIR'];
                $seed_info['general']['arc_type'] = $index_data['ARC_TYPE'];
            } else {
                $seed_info['general']['arc_dir'] = '';
                $seed_info['general']['arc_type'] = '';
            }
            $update_flag = true;
        }
        $data['crawl_index'] =  (isset($seed_info['general']['crawl_index'])) ?
            $seed_info['general']['crawl_index'] : '';
        $data['available_crawl_types'] =
            [self::WEB_CRAWL, self::ARCHIVE_CRAWL];
        if (!$no_further_changes && isset($_REQUEST['crawl_type']) &&
            in_array($_REQUEST['crawl_type'],
            $data['available_crawl_types'])) {
            $seed_info['general']['crawl_type'] = $_REQUEST['crawl_type'];
            $update_flag = true;
        }
        $data['crawl_type'] = $seed_info['general']['crawl_type'];
        if ($data['crawl_type'] == self::WEB_CRAWL) {
            $data['web_crawl_active'] = "active";
            $data['archive_crawl_active'] = "";
        } else {
            $data['archive_crawl_active'] = "active";
            $data['web_crawl_active'] = "";
            if (isset($_REQUEST['aserver_channel'])) {
                $_REQUEST['server_channel'] = $_REQUEST['aserver_channel'];
            }
            if (isset($_REQUEST['asleep_start'])) {
                $_REQUEST['server_channel'] = $_REQUEST['asleep_start'];
            }
            if (isset($_REQUEST['asleep_duration'])) {
                $_REQUEST['server_channel'] = $_REQUEST['asleep_duration'];
            }
        }
        $data['available_server_channels'] = $machine_model->getChannels();
        if (!$no_further_changes && isset($_REQUEST['server_channel']) &&
            in_array($_REQUEST['server_channel'],
            $data['available_server_channels'])) {
            $seed_info['general']['channel'] = $_REQUEST['server_channel'];
            $update_flag = true;
        }
        $data['server_channel'] = empty($seed_info['general']['channel']) ?
            max(0, $data['available_server_channels']) :
            $seed_info['general']['channel'];
        $data['available_crawl_orders'] = [
            self::BREADTH_FIRST =>
                tl('crawl_component_breadth_first'),
            self::PAGE_IMPORTANCE =>
                tl('crawl_component_page_importance')];
        if (!$no_further_changes && isset($_REQUEST['crawl_order']) &&
            in_array($_REQUEST['crawl_order'],
            array_keys($data['available_crawl_orders']))) {
            $seed_info['general']['crawl_order'] = $_REQUEST['crawl_order'];
            $update_flag = true;
        }
        $data['crawl_order'] = $seed_info['general']['crawl_order'];
        $data['available_max_depths'] = [
            -1 => tl('crawl_component_no_limit'), 0 => 0, 1 => 1, 2 => 2,
            3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9,
            10 => 10, 11 => 11, 12 => 12, 13 => 13, 14 => 14, 15 => 15,
            16 => 16, 32 =>32, 64 =>64, 128 => 128, 255 => 255];
        if (!$no_further_changes && isset($_REQUEST['max_depth']) &&
            in_array($_REQUEST['max_depth'],
            array_keys($data['available_max_depths']))) {
            $seed_info['general']['max_depth'] = $_REQUEST['max_depth'];
            $update_flag = true;
        }
        $data['max_depth'] = $seed_info['general']['max_depth'];
        $data['available_repeat_types'] = [
            -1 => tl('crawl_component_no_repeat'),
            C\ONE_MINUTE * 2 => tl('crawl_component_two_minutes'),
            C\ONE_HOUR => tl('crawl_component_hourly'),
            C\ONE_DAY => tl('crawl_component_daily'),
            C\ONE_WEEK => tl('crawl_component_weekly'),
            (2 * C\ONE_WEEK) => tl('crawl_component_fortnightly'),
            C\ONE_MONTH => tl('crawl_component_monthly'),
            (2 * C\ONE_MONTH) => tl('crawl_component_bimonthly'),
            (6 * C\ONE_MONTH) => tl('crawl_component_semiannually'),
            (C\ONE_YEAR) => tl('crawl_component_annually'),
        ];
        if (!$no_further_changes && isset($_REQUEST['repeat_type']) &&
            in_array($_REQUEST['repeat_type'],
            array_keys($data['available_repeat_types']))) {
            $seed_info['general']['repeat_type'] = $_REQUEST['repeat_type'];
            $update_flag = true;
        }
        $data['repeat_type'] = empty($seed_info['general']['repeat_type']) ?
            -1 : $seed_info['general']['repeat_type'];
        if (!$no_further_changes && isset($_REQUEST['sleep_start'])) {
            $data['sleep_start'] =
                $parent->clean($_REQUEST['sleep_start'], "time");
            $seed_info['general']['sleep_start'] = $data['sleep_start'];
            $update_flag = true;
        }
        $data['sleep_start'] = empty($seed_info['general']['sleep_start']) ?
            "00:00" : $seed_info['general']['sleep_start'];
        $data['available_sleep_durations'] = [
            -1 => tl('crawl_component_no_sleep'),
            C\ONE_MINUTE => tl('crawl_component_one_minute'),
            2 * C\ONE_HOUR => tl('crawl_component_two_hours'),
            3 * C\ONE_HOUR => tl('crawl_component_three_hours'),
            4 * C\ONE_HOUR => tl('crawl_component_four_hours'),
            6 * C\ONE_HOUR => tl('crawl_component_six_hours'),
            8 * C\ONE_HOUR => tl('crawl_component_eight_hours'),
            10 * C\ONE_HOUR => tl('crawl_component_ten_hours'),
            12 * C\ONE_HOUR => tl('crawl_component_twelve_hours'),
            14 * C\ONE_HOUR => tl('crawl_component_fourteen_hours'),
            16 * C\ONE_HOUR => tl('crawl_component_sixteen_hours'),
            18 * C\ONE_HOUR => tl('crawl_component_eighteen_hours'),
            20 * C\ONE_HOUR => tl('crawl_component_twenty_hours'),
            22 * C\ONE_HOUR => tl('crawl_component_twenty_two_hours'),
        ];
        if (!$no_further_changes && isset($_REQUEST['sleep_duration']) &&
            in_array($_REQUEST['sleep_duration'],
            array_keys($data['available_sleep_durations']))) {
            $seed_info['general']['sleep_duration'] =
                $_REQUEST['sleep_duration'];
            $update_flag = true;
        }
        $data['sleep_duration'] =
            empty($seed_info['general']['sleep_duration']) ?
            -1 : $seed_info['general']['sleep_duration'];
        if (!$no_further_changes && isset($_REQUEST['posted'])) {
            $seed_info['general']['restrict_sites_by_url'] =
                (isset($_REQUEST['restrict_sites_by_url'])) ?
                true : false;
            $update_flag = true;
        }
        $data['robots_txt_behaviors'] = [
            C\ALWAYS_FOLLOW_ROBOTS => tl('crawl_component_always_follow'),
            C\ALLOW_LANDING_ROBOTS => tl('crawl_component_allow_landing'),
            C\IGNORE_ROBOTS => tl('crawl_component_ignore'),
        ];
        $data['robots_txt'] = empty($seed_info['general']['robots_txt']) ?
            C\ALWAYS_FOLLOW_ROBOTS : $seed_info['general']['robots_txt'];
        if (!$no_further_changes && isset($_REQUEST['robots_txt']) &&
            in_array($_REQUEST['robots_txt'],
            array_keys($data['robots_txt_behaviors']))) {
            $seed_info['general']['robots_txt'] = $_REQUEST['robots_txt'];
            $update_flag = true;
        }
        $data['restrict_sites_by_url'] =
            $seed_info['general']['restrict_sites_by_url'];
        $site_types = ['allowed_sites' => 'url', 'disallowed_sites' => 'url',
            'seed_sites' => 'url'];
        foreach ($site_types as $type => $field) {
            if (!$no_further_changes && isset($_REQUEST[$type])) {
                $seed_info[$type][$field] =
                    $parent->convertStringCleanArray(
                    $_REQUEST[$type], $field);
                    $update_flag = true;
            }
            if (isset($seed_info[$type][$field])) {
                $data[$type] = $parent->convertArrayLines(
                    $seed_info[$type][$field]);
            } else {
                $data[$type] = "";
            }
        }
        $data['TOGGLE_STATE'] =
            ($data['restrict_sites_by_url']) ?
            "checked='checked'" : "";

        $data['SCRIPT'] = "setDisplay('toggle', ".
            "'{$data['restrict_sites_by_url']}');";
        if (!isset($_REQUEST['ts'])) {
            $data['SCRIPT'] .=
            " elt('load-options').onchange = ".
            "function() { if (elt('load-options').selectedIndex !=".
            " 0) { elt('crawloptionsForm').submit();  }};";
        }
        if ($data['crawl_type'] == CrawlConstants::WEB_CRAWL) {
            $data['SCRIPT'] .=
                "switchTab('webcrawltab', 'archivetab');";
        } else {
            $data['SCRIPT'] .=
                "switchTab('archivetab', 'webcrawltab');";
        }
        $inject_urls = [];
        if (isset($_REQUEST['ts']) &&
            isset($_REQUEST['inject_sites']) && $_REQUEST['inject_sites']) {
                $timestamp = substr($parent->clean($_REQUEST['ts'],
                    "string"), 0, C\TIMESTAMP_LEN);
                $inject_urls =
                    $parent->convertStringCleanArray(
                    $_REQUEST['inject_sites']);
        }
        if ($update_flag) {
            $preserve_fields = ["arg"];
            if (isset($_REQUEST['ts'])) {
                $preserve_fields = ["arg", "ts"];
                if ($inject_urls != []) {
                    $seed_info['seed_sites']['url'][] = "#\n#".
                        tl('crawl_component_added_urls', date('r'))."\n#";
                    $seed_info['seed_sites']['url'] = array_merge(
                        $seed_info['seed_sites']['url'], $inject_urls);
                }
                $crawl_model->setCrawlSeedInfo($timestamp,
                    $seed_info, $machine_urls);
                if ($inject_urls != [] &&
                    $crawl_model->injectUrlsCurrentCrawl(
                        $timestamp, $inject_urls, $machine_urls)) {
                    $add_message = "<br />".
                        tl('crawl_component_urls_injected');
                    if (isset($_REQUEST['use_suggest']) &&
                        $_REQUEST['use_suggest']) {
                        $crawl_model->clearSuggestSites();
                    }
                }
            } else {
                $crawl_model->setSeedInfo($seed_info);
            }
            return $parent->redirectWithMessage(
                tl('crawl_component_update_seed_info'). " $add_message",
                $preserve_fields);
        }
        return $data;
    }
    /**
     * Called from @see manageCrawls to read in the file with statistics
     * information about a crawl. This file is computed by @see AnalyticsJob
     *
     * @param array &$data an array of info to supply to AdminView
     * @param array $machine_urls machines that are being used in crawl
     * Yioop name server on which to perform the crawl
     */
    public function crawlStatistics(&$data, $machine_urls)
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $pre_stats_file = C\CRAWL_DIR."/cache/pre_".self::statistics_base_name.
            $data['its'].".txt";
        $stats_file = str_replace("pre_", "", $pre_stats_file);
        $data["HAS_STATISTICS"] = true;
        $data["STATISTICS_SCHEDULED"] = false;
        if (!empty($_REQUEST['recompute'])) {
            set_error_handler(null);
            @unlink($stats_file);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        }
        if (!file_exists($stats_file)) {
            $info = $crawl_model->getInfoTimestamp($data['its'],
                $machine_urls);
            if (!$info) {
                include(C\BASE_DIR."/error.php");
                \seekquarry\yioop\library\webExit(); //bail
            }
            $info["TIMESTAMP"] = $data['its'];
            $data = array_merge($data, $info);
            $data["HAS_STATISTICS"] = false;
        } else {
            $data = array_merge($data, unserialize(
                $parent->web_site->fileGetContents($stats_file)));
        }
        $data['GENERAL_STATS'] = [
            tl("crawl_component_description") => $data["DESCRIPTION"],
            tl("crawl_component_timestamp") => $data["TIMESTAMP"],
            tl("crawl_component_crawl_date") => date("r",$data["TIMESTAMP"]),
            tl("crawl_component_pages") => $data["VISITED_URLS_COUNT"],
            tl("crawl_component_url") => $data["COUNT"]
        ];
        if (!$data["HAS_STATISTICS"]) {
            if (!empty($info)) {
                if (file_exists($pre_stats_file)) {
                    $data["STATISTICS_SCHEDULED"] = true;
                } else {
                    $parent->web_site->filePutContents($pre_stats_file,
                        serialize($info));
                    chmod($pre_stats_file, 0777);
                }
            }
            return;
        }
        if (isset($data["HOST"]["DATA"]["all"])) {
            $data['GENERAL_STATS'][tl("crawl_component_number_hosts")] =
                $data["HOST"]["DATA"]["all"];
        }
        $data["STAT_HEADINGS"] = [
            tl("crawl_component_error_codes") => "CODE",
            tl("crawl_component_sizes") => "SIZE",
            tl("crawl_component_links_per_page") => "NUMLINKS",
            tl("crawl_component_page_date") => "MODIFIED",
            tl("crawl_component_dns_time") => "DNS",
            tl("crawl_component_download_time") => "TIME",
            tl("crawl_component_top_level_domain") => "SITE",
            tl("crawl_component_file_extension") => "FILETYPE",
            tl("crawl_component_media_type") => "MEDIA",
            tl("crawl_component_language") => "LANG",
            tl("crawl_component_server") => "SERVER",
            tl("crawl_component_os") => "OS",
        ];
    }
    /**
     * Handles admin requests for creating, editing, and deleting classifiers.
     *
     * This activity implements the logic for the page that lists existing
     * classifiers, including the actions that can be performed on them.
     */
    public function manageClassifiers()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $possible_arguments = ['addclassifier', 'editclassifier',
            'finalizeclassifier', 'deleteclassifier', 'search'];
        $data['ELEMENT'] = 'manageclassifiers';
        $data['SCRIPT'] = '';
        $data['FORM_TYPE'] = 'addclassifier';
        $search_array = [];
        $request_fields = ['start_row', 'num_show', 'end_row'];
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if ($num_machines < 1 || ($num_machines == 1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = null;
        }
        $data['leftorright'] =
            (L\getLocaleDirection() == 'ltr') ? 'right': 'left';

        $classifiers = Classifier::getClassifierList();
        $start_finalizing = false;
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            if (isset($_REQUEST['name'])) {
                $name = substr($parent->clean($_REQUEST['name'], 'string'), 0,
                    C\NAME_LEN);
                $name = Classifier::cleanLabel($name);
            } else if (isset($_REQUEST['class_label'])) {
                $name = substr($parent->clean(
                    $_REQUEST['class_label'], 'string'), 0,
                    C\NAME_LEN);
                $name = Classifier::cleanLabel($name);
            } else {
                $name = "";
            }
            switch ($_REQUEST['arg'])
            {
                case 'addclassifier':
                    if (!isset($classifiers[$name])) {
                        $classifier = new Classifier($name);
                        Classifier::setClassifier($classifier);
                        $classifiers[$name] = $classifier;
                        return $parent->redirectWithMessage(
                            tl('crawl_component_new_classifier'),
                            $request_fields);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_classifier_exists'),
                            $request_fields);
                    }
                break;
                case 'deleteclassifier':
                    $_REQUEST['arg'] = empty($_REQUEST['context']) ?
                        'none': 'search';
                    $request_fields[] = 'arg';
                    /*
                       In addition to deleting the classifier, we also want to
                       delete the associated crawl mix (if one exists) used to
                       iterate over existing indexes in search of new training
                       examples.
                     */
                    if (isset($classifiers[$name])) {
                        unset($classifiers[$name]);
                        Classifier::deleteClassifier($name);
                        $mix_name = Classifier::getCrawlMixName($name);
                        $mix_time = $crawl_model->getCrawlMixTimestamp(
                            $mix_name);
                        if ($mix_time) {
                            $crawl_model->deleteCrawlMixIteratorState(
                                $mix_time);
                            $crawl_model->deleteCrawlMix($mix_time);
                        }
                        return $parent->redirectWithMessage(
                            tl('crawl_component_classifier_deleted'),
                            $request_fields);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_classifier'),
                            $request_fields);
                    }
                break;
                case 'editclassifier':
                    if (isset($classifiers[$name])) {
                        $data['class_label'] = $name;
                        $this->editClassifier($data, $classifiers,
                            $machine_urls);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_classifier'),
                            $request_fields);
                    }
                break;
                case 'finalizeclassifier':
                    /*
                       Finalizing is too expensive to be done directly in the
                       controller that responds to the web request. Instead, a
                       daemon is launched to finalize the classifier
                       asynchronously and save it back to disk when it's done.
                       In the meantime, a flag is set to indicate the current
                       finalizing state.
                     */
                    CrawlDaemon::start("ClassifierTrainer", $name, '', -1);
                    $classifier = $classifiers[$name];
                    $classifier->finalized = Classifier::FINALIZING;
                    $start_finalizing = true;
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                        tl('crawl_component_finalizing_classifier').
                        '</h1>\');';
                break;
                case 'search':
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                            "manageClassifiers", ['ALL_FIELDS' => ['name']]);
                    if (empty($_SESSION['LAST_SEARCH']['manageClassifiers']) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['manageClassifiers'] =
                            $_SESSION['SEARCH']['manageClassifiers'];
                        unset($_SESSION['SEARCH']['manageClassifiers']);
                    } else {
                        $default_search = true;
                    }
                break;
            }
        }
        $data['classifiers'] = $classifiers;
        if ($search_array == [] || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['manageClassifiers'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'manageClassifiers');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH'][
                        'manageClassifiers']['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['manageClassifiers']['PAGING'];
                }
            }
            if ($search_array == []) {
                $search_array[] = ["name", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, 'classifiers', 'classifiers',
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "",
            ['name' => 'class_label']);
        $data['reload'] = false;
        foreach ($classifiers as $label => $classifier) {
            if ($classifier->finalized == Classifier::FINALIZING) {
                $data['reload'] = true;
                break;
            }
        }
        if ($data['reload'] && !$start_finalizing) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\">".
                tl('crawl_component_finalizing_classifier'). '</h1>\');';
        }
        if ($data['FORM_TYPE'] == 'addclassifier') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Handles the particulars of editing a classifier, which includes changing
     * its label and adding training examples.
     *
     * This activity directly handles changing the class label, but not adding
     * training examples. The latter activity is done interactively without
     * reloading the page via XmlHttpRequests, coordinated by the classifier
     * controller dedicated to that task.
     *
     * @param array $data data to be passed on to the view
     * @param array $classifiers map from class labels to their associated
     *    classifiers
     * @param array $machine_urls string urls of machines managed by this
     *    Yioop name server
     */
    public function editClassifier(&$data, $classifiers, $machine_urls)
    {
        $parent = $this->parent;
        $data['ELEMENT'] = 'editclassifier';
        $data['INCLUDE_SCRIPTS'] = ['classifiers'];
        if (!empty($_REQUEST['context']) &&
            $_REQUEST['context']=='search') {
            $data['context'] = 'search';
        }
        // We want recrawls, but not archive crawls.
        $crawls = $parent->model("crawl")->getCrawlList(false, true,
            $machine_urls);
        $data['CRAWLS'] = $crawls;

        $classifier = $classifiers[$data['class_label']];

        if (isset($_REQUEST['update']) && $_REQUEST['update'] == 'update') {
            if (isset($_REQUEST['rename_label'])) {
                $new_label = substr($parent->clean($_REQUEST['rename_label'],
                    'string'), 0, C\NAME_LEN);
                $new_label = preg_replace('/[^a-zA-Z0-9_]/', '', $new_label);
                if (!isset($classifiers[$new_label])) {
                    $old_label = $classifier->class_label;
                    $classifier->class_label = $new_label;
                    Classifier::setClassifier($classifier);
                    Classifier::deleteClassifier($old_label);
                    $data['class_label'] = $new_label;
                } else {
                    $_REQUEST['name'] = $_REQUEST['class_label'];
                    return $parent->redirectWithMessage(
                        tl('crawl_component_classifier_exists'),
                        ['arg', 'name', 'context']);
                }
            }
        }
        $data['classifier'] = $classifier;
        // Translations for the classification javascript.
        $data['SCRIPT'] .= "window.tl = {".
            'crawl_component_load_failed:"'.
                tl('crawl_component_load_failed').'",'.
            'crawl_component_loading:"'.
                tl('crawl_component_loading').'",'.
            'crawl_component_added_examples:"'.
                tl('crawl_component_added_examples').'",'.
            'crawl_component_label_update_failed:"'.
                tl('crawl_component_label_update_failed').'",'.
            'crawl_component_updating:"'.
                tl('crawl_component_updating').'",'.
            'crawl_component_acc_update_failed:"'.
                tl('crawl_component_acc_update_failed').'",'.
            'crawl_component_na:"'.
                tl('crawl_component_na').'",'.
            'crawl_component_no_docs:"'.
                tl('crawl_component_no_docs').'",'.
            'crawl_component_num_docs:"'.
                tl('crawl_component_num_docs').'",'.
            'crawl_component_in_class:"'.
                tl('crawl_component_in_class').'",'.
            'crawl_component_not_in_class:"'.
                tl('crawl_component_not_in_class').'",'.
            'crawl_component_skip:"'.
                tl('crawl_component_skip').'",'.
            'crawl_component_prediction:"'.
                tl('crawl_component_prediction').'",'.
            'crawl_component_scores:"'.
                tl('crawl_component_scores').'"'.
            '};';
        /*
           We pass along authentication information to the client, so that it
           can authenticate any XmlHttpRequests that it makes in order to label
           documents.
         */
        $time = strval(time());
        $session = md5($time . C\AUTH_KEY);
        $data['SCRIPT'] .=
            "Classifier.initialize(".
                "'{$data['class_label']}',".
                "'{$session}',".
                "'{$time}');";
    }
    /**
     * Handles admin request related to controlling file options to be used
     * in a crawl
     *
     * This activity allows a user to specify the page range size to be
     * be used during a crawl as well as which file types can be downloaded
     */
    public function pageOptions()
    {
        PageProcessor::initializeIndexedFileTypes();
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $profile_model = $parent->model("profile");
        $data["ELEMENT"] = "pageoptions";
        $data['SCRIPT'] = "";
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $num_machines = count($machine_urls);
        if ($num_machines <  1 || ($num_machines ==  1 &&
            UrlParser::isLocalhostUrl($machine_urls[0]))) {
            $machine_urls = null;
        }
        $data['available_options'] = [
            tl('crawl_component_use_below'),
            tl('crawl_component_use_defaults')];
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls);
        $data['options_default'] = tl('crawl_component_use_below');
        foreach ($crawls as $crawl) {
            if (strlen($crawl['DESCRIPTION']) > 0 ) {
                $data['available_options'][$crawl['CRAWL_TIME']] =
                    $crawl['DESCRIPTION'];
            }
        }
        $seed_info = $crawl_model->getSeedInfo();
        $data['RECRAWL_FREQS'] = [-1 => tl('crawl_component_recrawl_never'),
            1 => tl('crawl_component_recrawl_1day'),
            2 => tl('crawl_component_recrawl_2day'),
            3 => tl('crawl_component_recrawl_3day'),
            7 => tl('crawl_component_recrawl_7day'),
            14 => tl('crawl_component_recrawl_14day')];
        $data['SIZE_VALUES'] = [10000 => 10000, 50000 => 50000,
            100000 => 100000, 500000 => 500000, 1000000 => 1000000,
            5000000 => 5000000, 10000000 => 10000000];
        $data['LEN_VALUES'] = [2000 => 2000, 5000 => 5000, 10000 => 10000,
            50000 => 50000, 100000 => 100000, 500000 => 500000,
            1000000 => 1000000, 5000000 => 5000000, 10000000 => 10000000];
        $data['MAX_LINKS_VALUES'] = [10 => 10, 20 => 20, 50 => 50,
            100 => 100, 200 => 200, 500 => 500,
            -1 => tl('crawl_component_unlimited')];
        $data['available_summarizers'] = [
            self::BASIC_SUMMARIZER => tl('crawl_component_basic'),
            self::CENTROID_SUMMARIZER =>  tl('crawl_component_centroid'),
            self::CENTROID_WEIGHTED_SUMMARIZER =>
                tl('crawl_component_centroid_weighted'),
            self::GRAPH_BASED_SUMMARIZER => tl('crawl_component_graph_based')];
        if (!isset($seed_info["indexed_file_types"]["extensions"])) {
            $seed_info["indexed_file_types"]["extensions"] =
                PageProcessor::$indexed_file_types;
        }
        $loaded = false;
        if (isset($_REQUEST['load_option']) && $_REQUEST['load_option'] > 0) {
            if ($_REQUEST['load_option'] == 1) {
                $seed_loaded = $crawl_model->getSeedInfo(true);
            } else {
                $timestamp = substr($parent->clean(
                    $_REQUEST['load_option'], "int"), 0, C\TIMESTAMP_LEN);
                $seed_loaded = $crawl_model->getCrawlSeedInfo(
                    $timestamp, $machine_urls);
            }
            $copy_options = ["general" => ["page_recrawl_frequency",
                "page_range_request", "max_description_len",
                "max_links_to_extract", "cache_pages", 'summarizer_option'],
                "indexed_file_types" => ["extensions"],
                "indexing_plugins" => ["plugins", "plugins_data"]];
            foreach ($copy_options as $main_option => $sub_options) {
                foreach ($sub_options as $sub_option) {
                    if (isset($seed_loaded[$main_option][$sub_option])) {
                        $seed_info[$main_option][$sub_option] =
                            $seed_loaded[$main_option][$sub_option];
                    }
                }
            }
            if (isset($seed_loaded['page_rules'])) {
                $seed_info['page_rules'] =
                    $seed_loaded['page_rules'];
            }
            if (isset($seed_loaded['active_classifiers'])) {
                $seed_info['active_classifiers'] =
                    $seed_loaded['active_classifiers'];
            } else {
                $seed_info['active_classifiers'] = [];
                $seed_info['active_classifiers']['label'] = [];
            }
            $loaded = true;
        } else {
            $seed_info = $crawl_model->getSeedInfo();
            if (isset($_REQUEST["page_recrawl_frequency"]) &&
                in_array($_REQUEST["page_recrawl_frequency"],
                    array_keys($data['RECRAWL_FREQS']))) {
                $seed_info["general"]["page_recrawl_frequency"] =
                    $_REQUEST["page_recrawl_frequency"];
            }
            if (isset($_REQUEST["page_range_request"]) &&
                in_array($_REQUEST["page_range_request"],
                $data['SIZE_VALUES'])) {
                $seed_info["general"]["page_range_request"] =
                    $_REQUEST["page_range_request"];
            }
            if (isset($_REQUEST['summarizer_option'])
                && in_array($_REQUEST['summarizer_option'],
                array_keys($data['available_summarizers']))) {
                $seed_info['general']['summarizer_option'] =
                    $_REQUEST['summarizer_option'];
            }
            if (isset($_REQUEST["max_description_len"]) &&
                in_array($_REQUEST["max_description_len"],
                $data['LEN_VALUES'])) {
                $seed_info["general"]["max_description_len"] =
                    $_REQUEST["max_description_len"];
            }
            if (isset($_REQUEST["max_links_to_extract"]) &&
                in_array($_REQUEST["max_links_to_extract"],
                array_keys($data['MAX_LINKS_VALUES']))) {
                $seed_info["general"]["max_links_to_extract"] =
                    $_REQUEST["max_links_to_extract"];
            }
            if (isset($_REQUEST["cache_pages"]) ) {
                $seed_info["general"]["cache_pages"] = true;
            } else if (isset($_REQUEST['posted'])) {
                //form sent but check box unchecked
                $seed_info["general"]["cache_pages"] = false;
            }
            if (isset($_REQUEST['page_rules'])) {
                $seed_info['page_rules']['rule'] =
                    $parent->convertStringCleanArray(
                    $_REQUEST['page_rules'], 'rule');
            }
        }
        if (!isset($seed_info["general"]["page_recrawl_frequency"])) {
            $seed_info["general"]["page_recrawl_frequency"] =
                C\PAGE_RECRAWL_FREQUENCY;
        }
        $data['summarizer_option'] = isset(
            $seed_info['general']['summarizer_option']) ?
            $seed_info['general']['summarizer_option'] :
            self::BASIC_SUMMARIZER;
        $data['PAGE_RECRAWL_FREQUENCY'] =
            $seed_info["general"]["page_recrawl_frequency"];
        if (!isset($seed_info["general"]["cache_pages"])) {
            $seed_info["general"]["cache_pages"] = false;
        }
        $data["CACHE_PAGES"] = $seed_info["general"]["cache_pages"];
        if (!isset($seed_info["general"]["page_range_request"])) {
            $seed_info["general"]["page_range_request"] = C\PAGE_RANGE_REQUEST;
        }
        $data['PAGE_SIZE'] = $seed_info["general"]["page_range_request"];
        if (!isset($seed_info["general"]["max_description_len"])) {
            $seed_info["general"]["max_description_len"] =
            C\MAX_DESCRIPTION_LEN;
        }
        $data['MAX_LEN'] = $seed_info["general"]["max_description_len"];
        $data['MAX_LINKS_TO_EXTRACT'] =
            $seed_info["general"]["max_links_to_extract"] ??
            C\MAX_LINKS_TO_EXTRACT;
        $data['INDEXING_PLUGINS'] = [];
        $included_plugins = [];
        if (isset($_REQUEST["posted"]) && !$loaded) {
            $seed_info['indexing_plugins']['plugins'] =
                (isset($_REQUEST["INDEXING_PLUGINS"])) ?
                $_REQUEST["INDEXING_PLUGINS"] : [];
        }
        $included_plugins =
            (isset($seed_info['indexing_plugins']['plugins'])) ?
                $seed_info['indexing_plugins']['plugins']
                : [];
        foreach ($parent->getIndexingPluginList() as $plugin) {
            if ($plugin == "") {
                continue;
            }
            $plugin_name = ucfirst($plugin);
            $data['INDEXING_PLUGINS'][$plugin_name]['checked'] =
                (in_array($plugin_name, $included_plugins)) ?
                "checked='checked'" : "";
            /* to use method_exists we want that the require_once for the
               plugin class has occurred so we instantiate the object via the
               plugin method call which will also do the require if needed.
             */
            $plugin_object = $parent->plugin(lcfirst($plugin_name));
            $class_name = C\NS_PLUGINS . $plugin_name."Plugin";
            if ($loaded && method_exists($class_name, 'setConfiguration') &&
                method_exists($class_name, 'loadDefaultConfiguration')) {
                if (isset($seed_info['indexing_plugins']['plugins_data'][
                    $plugin_name])) {
                    $plugin_object->setConfiguration($seed_info[
                        'indexing_plugins']['plugins_data'][$plugin_name]);
                } else {
                    $plugin_object->loadDefaultConfiguration();
                }
                $plugin_object->saveConfiguration();
            }
            if (method_exists($class_name, 'configureHandler') &&
                method_exists($class_name, 'configureView')) {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = true;
                $plugin_object->configureHandler($data);
            } else {
                $data['INDEXING_PLUGINS'][$plugin_name]['configure'] = false;
            }
        }
        $profile =  $profile_model->getProfile(C\WORK_DIRECTORY);
        if (!isset($_REQUEST['load_option'])) {
            $data = array_merge($data, $profile);
        } else {
            $parent->updateProfileFields($data, $profile,
                ['IP_LINK','CACHE_LINK', 'SIMILAR_LINK', 'IN_LINK',
                'RESULT_SCORE', 'SIGNIN_LINK', 'SUBSEARCH_LINK',
                'WORD_SUGGEST']);
        }
        $weights = ['TITLE_WEIGHT' => 4,
            'DESCRIPTION_WEIGHT' => 1, 'LINK_WEIGHT' => 2,
            'MIN_RESULTS_TO_GROUP' => 200];
        $change = false;
        foreach ($weights as $weight => $value) {
            if (isset($_REQUEST[$weight])) {
                $data[$weight] = $parent->clean($_REQUEST[$weight], 'float', 1
                    );
                $profile[$weight] = $data[$weight];
                $change = true;
            } else if (isset($profile[$weight]) && $profile[$weight] != ""){
                $data[$weight] = $profile[$weight];
            } else {
                $data[$weight] = $value;
                $profile[$weight] = $data[$weight];
                $change = true;
            }
        }
        if ($change == true) {
            $profile_model->updateProfile(C\WORK_DIRECTORY, [], $profile);
        }
        $data['INDEXED_FILE_TYPES'] = [];
        $filetypes = [];
        foreach (PageProcessor::$indexed_file_types as $filetype) {
            $ison =false;
            if (isset($_REQUEST["filetype"]) && !$loaded) {
                if (isset($_REQUEST["filetype"][$filetype])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                    $change = true;
                }
            } else {
                if (isset($seed_info["indexed_file_types"]["extensions"]) &&
                    in_array($filetype,
                    $seed_info["indexed_file_types"]["extensions"])) {
                    $filetypes[] = $filetype;
                    $ison = true;
                }
            }
            $data['INDEXED_FILE_TYPES'][$filetype] = ($ison) ?
                "checked='checked'" :'';
        }
        $seed_info["indexed_file_types"]["extensions"] = $filetypes;
        $data['CLASSIFIERS'] = [];
        $data['RANKERS'] = [];
        $active_classifiers = [];
        $active_rankers = [];
        foreach (Classifier::getClassifierList() as $classifier) {
            $label = $classifier->class_label;
            $ison = false;
            if (isset($_REQUEST['classifier']) && !$loaded) {
                if (isset($_REQUEST['classifier'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_classifiers']['label'])) {
                if (in_array($label,
                    $seed_info['active_classifiers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['CLASSIFIERS'][$label] = 'checked="checked"';
                $active_classifiers[] = $label;
            } else {
                $data['CLASSIFIERS'][$label] = '';
            }
            $ison = false;
            if (isset($_REQUEST['ranker']) && !$loaded) {
                if (isset($_REQUEST['ranker'][$label])) {
                    $ison = true;
                }
            } else if ($loaded || !isset($_REQUEST['posted']) &&
                isset($seed_info['active_rankers']['label'])) {
                if (isset($seed_info['active_rankers']['label']) &&
                    in_array($label, $seed_info['active_rankers']['label'])) {
                    $ison = true;
                }
            }
            if ($ison) {
                $data['RANKERS'][$label] = 'checked="checked"';
                $active_rankers[] = $label;
            } else {
                $data['RANKERS'][$label] = '';
            }
        }
        $parent->pagingLogic($data, 'CLASSIFIERS', 'CLASSIFIERS',
            C\DEFAULT_ADMIN_PAGING_NUM/5, [], "",
            ['name' => 'class_label']);
        $seed_info['active_classifiers']['label'] = $active_classifiers;
        $seed_info['active_rankers']['label'] = $active_rankers;
        if (isset($seed_info['page_rules']['rule'])) {
            if (isset($seed_info['page_rules']['rule']['rule'])) {
                $data['page_rules'] = $parent->convertArrayLines(
                    $seed_info['page_rules']['rule']['rule']);
            } else {
                $data['page_rules'] = $parent->convertArrayLines(
                    $seed_info['page_rules']['rule']);
            }
        } else {
            $data['page_rules'] = "";
        }
        $allowed_options = ['crawl_time', 'search_time', 'test_options'];
        if (isset($_REQUEST['option_type']) &&
            in_array($_REQUEST['option_type'], $allowed_options)) {
            $data['option_type'] = $_REQUEST['option_type'];
        } else {
            $data['option_type'] = 'crawl_time';
        }
        if ($data['option_type'] == 'crawl_time') {
            $data['crawl_time_active'] = "active";
            $data['search_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('crawltimetab',".
                "'searchtimetab', 'testoptionstab')\n";
        } else if ($data['option_type'] == 'search_time') {
            $data['search_time_active'] = "active";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "";
            $data['SCRIPT'] .= "\nswitchTab('searchtimetab',".
                "'crawltimetab', 'testoptionstab')\n";
        } else {
            $data['search_time_active'] = "";
            $data['crawl_time_active'] = "";
            $data['test_options_active'] = "active";
            $data['SCRIPT'] .= "\nswitchTab('testoptionstab',".
                "'crawltimetab', 'searchtimetab');\n";
        }
        $crawl_model->setSeedInfo($seed_info);
        if ($change == true && $data['option_type'] != 'test_options') {
            return $parent->redirectWithMessage(
                tl('crawl_component_page_options_updated'),
                ["option_type"], true);
        }
        $test_processors = [
            "text/html" => "html",
            "text/asp" => "html",
            "text/xml" => "xml",
            "text/robot" => "robot",
            "application/xml" => "xml",
            "application/xhtml+xml" => "html",
            "application/rss+xml" => "rss",
            "application/atom+xml" => "rss",
            "text/csv" => "text",
            "text/gopher" => "gopher",
            "text/plain" => "text",
            "text/rtf" => "rtf",
            "text/tab-separated-values" => "text",
        ];
        $data['test_methods'] = [
            -1 => tl('crawl_component_page_submission'),
            "uri" => tl('crawl_component_test_uri'),
            "file-upload" => tl('crawl_component_test_upload'),
            "direct-input" => tl('crawl_component_test_input'),
        ];
        $data['test_method'] = (!empty($_REQUEST['test_method']) &&
            in_array($_REQUEST['test_method'],
            array_keys($data['test_methods']))) ? $_REQUEST['test_method'] : -1;
        $data['MIME_TYPES'] = array_keys($test_processors);
        $data['page_type'] = "text/html";
        if (isset($_REQUEST['page_type']) && in_array($_REQUEST['page_type'],
            $data['MIME_TYPES'])) {
            $data['page_type'] = $_REQUEST['page_type'];
        }
        $data['TESTPAGE'] = (isset($_REQUEST['TESTPAGE'])) ?
            $_REQUEST['TESTPAGE'] : ""; //clean just before displaying below
        $data["PAGE_RANGE_REQUEST"] = $seed_info["general"][
            "page_range_request"];
        $test_uri = "";
        $test_filename = "";
        if (!empty($_FILES['test_file_upload']['tmp_name'])) {
            $test_filename = $_FILES['test_file_upload']['name'];
            $data['TESTPAGE'] = file_get_contents(
                $_FILES['test_file_upload']['tmp_name'], false, null, 0,
                $data["PAGE_RANGE_REQUEST"]);
            unlink($_FILES['test_file_upload']['tmp_name']);
        } else if (!empty($_REQUEST['test_uri'])) {
            $test_uri = $parent->clean($_REQUEST['test_uri'], 'web-url');
            $sites = [ [self::URL => $test_uri] ];
            $site_pages = FetchUrl::getPages($sites, true,
                $data["PAGE_RANGE_REQUEST"], C\CRAWL_DIR . "/temp");
            $site = $site_pages[0];
            if (!empty($site[self::HTTP_CODE]) && empty($site[self::TYPE]) &&
                $site[self::HTTP_CODE]>= 300 && $site[self::HTTP_CODE] <= 400) {
                $site[self::TYPE] = "text/plain";
            }
            $data['TESTPAGE'] = (empty($site[self::PAGE])) ? "" :
                $site[self::PAGE];
        }
        $data['test_uri'] = $test_uri;
        if ($data['option_type'] == 'test_options' && $data['TESTPAGE'] != "") {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('crawl_component_page_options_running_tests')."</h1>');";
            PageProcessor::initializeIndexedFileTypes();
            $data['PROCESS_TIMES'] = [];
            $start_time = microtime(true);
            if (empty($site)) {
                $site = [];
                $site[self::ENCODING] = "UTF-8";
                $site[self::URL] = "https://test-site.yioop.com/$test_filename";
                $site[self::IP_ADDRESSES] = ["4.4.4.4"];
                $site[self::HTTP_CODE] = 200;
                $site[self::MODIFIED] = date("U", time());
                $site[self::TIMESTAMP] = time();
                $site[self::TYPE] = "text/html";
                $site[self::HEADER] = "page options test extractor";
                $site[self::SERVER] = "unknown";
                $site[self::SERVER_VERSION] = "unknown";
                $site[self::OPERATING_SYSTEM] = "unknown";
                $site[self::LANG] = 'en-US';
                $site[self::JUST_METAS] = false;
                $site[self::PAGE] = $data['TESTPAGE'];
                if (isset($_REQUEST['page_type']) &&
                    in_array($_REQUEST['page_type'], $data['MIME_TYPES'])) {
                    $site[self::TYPE] = $_REQUEST['page_type'];
                }
            }
            if (!empty($_FILES['test_file_upload']['type'])) {
                $site[self::TYPE] = $_FILES['test_file_upload']['type'];
            }
            if ($site[self::TYPE] == 'text/html' &&
                empty($site[self::ENCODING])) {
                $site[self::ENCODING] =
                    L\guessEncodingHtmlXml($data['TESTPAGE']);
            }
            L\convertUtf8IfNeeded($site, self::PAGE, self::ENCODING);
            $data['TESTPAGE'] = $site[self::PAGE];
            if (empty(PageProcessor::$mime_processor[$site[self::TYPE]])) {
                return $parent->redirectWithMessage(
                    tl('crawl_component_page_options_no_processor'),
                    ["option_type"], true);
            }
            $processor_name = PageProcessor::$mime_processor[$site[self::TYPE]];
            $plugin_processors = [];
            if (isset($seed_info['indexing_plugins']['plugins'])) {
                foreach ($seed_info['indexing_plugins']['plugins'] as $plugin) {
                    if ($plugin == "") {
                        continue;
                    }
                    $plugin_name = C\NS_PLUGINS . $plugin . "Plugin";
                    $tmp_object = new $plugin_name();
                    $supported_processors = $tmp_object->getProcessors();
                    foreach ($supported_processors as $supported_processor) {
                        $parent_processor = C\NS_PROCESSORS . $processor_name;
                        do {
                            if (C\NS_PROCESSORS .$supported_processor ==
                                $parent_processor) {
                                $plugin_object =
                                    $parent->plugin(lcfirst($plugin));
                                if (method_exists($plugin_name,
                                    "loadConfiguration")) {
                                    $plugin_object->loadConfiguration();
                                }
                                $plugin_processors[] = $plugin_object;
                                break;
                            }
                        } while(($parent_processor =
                            get_parent_class($parent_processor)) &&
                            $parent_processor != "PageProcessor");
                    }
                }
            }
            $processor_name = C\NS_PROCESSORS . $processor_name;
            $page_processor = new $processor_name($plugin_processors,
                $seed_info["general"]["max_description_len"],
                -1,
                $seed_info["general"]["summarizer_option"]);
            set_error_handler(null);
            if (L\generalIsA($processor_name, C\NS_PROCESSORS.
                "HtmlProcessor")) {
                P\HtmlProcessor::$page_options_testing = true;
                $page_processor->scrapers = $parent->model("scraper"
                    )->getAllScrapers();
            }
            $is_text = false;
            if (L\generalIsA($processor_name, C\NS_PROCESSORS.
                "TextProcessor")) {
                $is_text = true;
            }
            $doc_info = $page_processor->handle(
                substr($data['TESTPAGE'], 0, $data["PAGE_RANGE_REQUEST"]),
                $site[self::URL]);
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            if (!$doc_info) {
                $data["AFTER_PAGE_PROCESS"] = "";
                $data["AFTER_RULE_PROCESS"] = "";
                $data["EXTRACTED_WORDS"] = "";
                $data["EXTRACTED_META_WORDS"] ="";
                return $data;
            }
            $original_links = $doc_info[self::LINKS];
            if ($processor_name != C\NS_PROCESSORS . "RobotProcessor" &&
                !isset($doc_info[self::JUST_METAS])) {
                $doc_info[self::LINKS] = UrlParser::pruneLinks(
                    $doc_info[self::LINKS],
                    $seed_info["general"]["max_links_to_extract"]);
            }
            foreach ($doc_info as $key => $value) {
                $site[$key] = $value;
            }
            if (isset($site[self::PAGE])) {
                unset($site[self::PAGE]);
            }
            if (isset($site[self::ROBOT_PATHS])) {
                $site[self::JUST_METAS] = true;
            }
            $content = ($is_text) ? ($site[self::DESCRIPTION] ?? "") :
                ($site[self::PAGE] ?? "");
            $page_hash = FetchUrl::computePageHash($content);
            $site[self::HASH] = L\toHexString($page_hash);
            $after_process = $this->mapSiteConstants($site);
            $data["AFTER_PAGE_PROCESS"] = L\utf8WordWrap($parent->clean(
                print_r($after_process, true), "string"), 70, "\n", true);
            $data['PROCESS_TIMES']['PAGE_PROCESS'] = L\changeInMicrotime(
                $start_time);
            $rule_time = microtime(true);
            $rule_string = implode("\n", $seed_info['page_rules']['rule']);
            $rule_string = html_entity_decode($rule_string, ENT_QUOTES);
            $page_rule_parser =
                new PageRuleParser($rule_string);
            $page_rule_parser->executeRuleTrees($site);
            $after_process = $this->mapSiteConstants($site);
            $data["AFTER_RULE_PROCESS"] = L\utf8WordWrap($parent->clean(
                print_r($after_process, true), "string"), 70, "\n", true);
            $lang = null;
            $data['PROCESS_TIMES']['RULE_PROCESS'] = L\changeInMicrotime(
                $rule_time);
            $rule_time = microtime(true);
            if (isset($site[self::LANG])) {
                $lang = $site[self::LANG];
            }
            $meta_ids = PhraseParser::calculateMetas($site);
            if (empty($site[self::JUST_METAS])) {
                $host_words = UrlParser::getWordsInHostUrl($site[self::URL]);
                $path_words = UrlParser::getWordsLastPathPartUrl(
                    $site[self::URL]);
                $phrase_string = $host_words . " .. ".$site[self::TITLE] .
                    " ..  ". $path_words . " .. ". $site[self::DESCRIPTION];
                if (empty($site[self::LANG])) {
                    $lang = L\guessLocaleFromString($phrase_string,
                        $lang);
                        $site[self::LANG] = $lang;
                }
                $word_lists = PhraseParser::extractPhrasesInLists(
                    $phrase_string, $lang);
                $len = strlen($phrase_string);
                if (PhraseParser::computeSafeSearchScore(
                    $word_lists['WORD_LIST'], $len) < 0.012) {
                    $meta_ids[] = "safe:true";
                    $safe = true;
                } else {
                    $meta_ids[] = "safe:false";
                    $safe = false;
                }
            }
            if (!isset($word_lists['WORD_LIST'])) {
                $word_lists['WORD_LIST'] = [];
            }
            if (!isset($word_lists['QUESTION_ANSWER_LIST'])) {
                $word_lists['QUESTION_ANSWER_LIST'] = [];
            }
            $cld_cnt = UrlParser::countCompanyLevelDomainsInCommonDetectFarm(
                $site[self::URL], array_keys($original_links));
            $meta_ids[]= "cld:$cld_cnt";
            $data['TESTPAGE'] = $parent->clean($data['TESTPAGE'], "string");
            $data["EXTRACTED_WORDS"] = L\utf8WordWrap($parent->clean(
                print_r($word_lists['WORD_LIST'], true), "string"),
                70, "\n", true);
            $data["EXTRACTED_META_WORDS"] = L\utf8WordWrap($parent->clean(
                print_r($meta_ids, true), "string"), 70, "\n", true);
            $data["QUESTIONS_TRIPLET"] = L\utf8WordWrap($parent->clean(
                print_r($word_lists['QUESTION_ANSWER_LIST'], true),
                "string"), 70, "\n", true);
            $data['PROCESS_TIMES']['TOTAL'] = L\changeInMicrotime($start_time);
            if (empty($word_lists['TIMES'])) {
                $word_lists['TIMES'] = [];
            }
            $data['PROCESS_TIMES'] = array_merge($data['PROCESS_TIMES'],
                $word_lists['TIMES']);
        }
        return $data;
    }
    /**
     * Given an array with key fields coming from CrawlConstants returns
     * an associative array sorted by key with the key fields the string names
     * of the CrawlConstants in the orginal array. So if an array has
     * a field [CrawlConstants::PAGE] => some page, the new array has a field
     * PAGE => some page.
     * @param array $site the input array with CrawlConstant fields
     * @return array converted array with string names of CrawlConstants
     */
    private function mapSiteConstants($site)
    {
        static $inverse_constants = [];
        if (empty($inverse_constants)) {
            $reflect = new \ReflectionClass(C\NS_LIB . "CrawlConstants");
            $crawl_constants = $reflect->getConstants();
            $crawl_keys = array_keys($crawl_constants);
            $crawl_values = array_values($crawl_constants);
            $inverse_constants = array_combine($crawl_values, $crawl_keys);
        }
        $after_process = [];
        foreach ($site as $key => $value) {
            $out_key = (isset($inverse_constants[$key])) ?
                $inverse_constants[$key] : $key;
            $after_process[$out_key] = $value;
        }
        ksort($after_process);
        return $after_process;
    }
    /**
     * Handles admin request related to the Scrapers activity
     *
     * This activity allows a user to specify the configuration for the
     * ways we detect Scrapers
     *
     * @return array $data info about the Scraper settings
     */
    public function scrapers()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $scraper_model = $parent->model("scraper");
        $possible_arguments = ["add", "delete", "edit", "search"];
        $request_fields = ['start_row', 'num_show', 'end_row'];
        $data = [];
        $data["ELEMENT"] = "scrapers";
        $data['SCRIPT'] = "";
        $n = C\NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] = [$n => $n, 2*$n => 2*$n, 5*$n=> 5*$n,
            10*$n=>10*$n];
        if (isset($_REQUEST['per_page']) &&
            in_array($_REQUEST['per_page'], array_keys($data['PER_PAGE']))) {
            $data['PER_PAGE_SELECTED'] = $_REQUEST['per_page'];
        } else {
            $data['PER_PAGE_SELECTED'] = C\NUM_RESULTS_PER_PAGE;
        }
        $data['SCRAPER_PRIORITIES'] = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $data["CURRENT_SCRAPER"] = [
            "name" => "", "signature" => "",
            "priority" => 0, "text_path" => "",
            "delete_paths" => "", "extract_fields" => ""];
        $types = [
            "name" => "string", "signature" => "string",
            "priority" => "int", "text_path" => "string",
            "delete_paths" => "string", "extract_fields" => "string"];
        $data['FORM_TYPE'] = "add";
        $r = [];
        foreach ($data["CURRENT_SCRAPER"]  as $key => $value) {
            $r[$key] = (empty($_REQUEST[$key])) ? $value :
                $parent->clean($_REQUEST[$key], $types[$key]);
        }
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg'])
            {
                case "add":
                    if (empty($_REQUEST['name']) ||
                        empty($_REQUEST['signature'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_scraper_missing fields'),
                            $request_fields);
                    }
                    $scraper_model->add($r["name"], $r["signature"],
                        $r["priority"], $r["text_path"], $r["delete_paths"],
                        $r["extract_fields"]);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_scraper_added'),
                        $request_fields);
                break;
                case "delete":
                    if (empty($_REQUEST['id'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_delete_scraper'),
                            $request_fields);
                    }
                    $scraper_id = $parent->clean($_REQUEST['id'], "string");
                    $scraper_model->delete($scraper_id);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_scraper_deleted'),
                        $request_fields);
                break;
                case "edit":
                    $data['FORM_TYPE'] = "edit";
                    $scraper = false;
                    $scraper_id = (isset($_REQUEST['id'])) ?
                        $parent->clean($_REQUEST['id'], "string") : "";
                    if ($scraper_id) {
                        $scraper = $scraper_model->get($scraper_id);
                    }
                    if (!$scraper) {
                        $data['FORM_TYPE'] = "add";
                        break;
                    }
                    $data['id'] = $scraper_id;
                    $update = false;
                    foreach ($data['CURRENT_SCRAPER'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'name') {
                            $scraper[$upper_field] = $r[$field];
                            $data['CURRENT_SCRAPER'][$field] =
                                $scraper[$upper_field];
                            $update = true;
                        } else if (!empty($scraper[$upper_field])){
                            $data['CURRENT_SCRAPER'][$field] =
                                $scraper[$upper_field];
                        }
                    }
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    if ($update) {
                        $scraper_model->update($scraper);
                        $fields = array_merge(["arg", "id"],
                            $request_fields);
                        return $parent->redirectWithMessage(
                            tl('crawl_component_scraper_updated'),
                            $fields);
                    }
                break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        "scrapers", ['ALL_FIELDS' => ['name']]);
                    if (empty($_SESSION['LAST_SEARCH']['scrapers']) ||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['scrapers'] =
                            $_SESSION['SEARCH']['scrapers'];
                        unset($_SESSION['SEARCH']['scrapers']);
                    } else {
                        $default_search = true;
                    }
                    break;
            }
        }
        if (empty($search_array) || !empty($default_search)) {
            if (!empty($_SESSION['LAST_SEARCH']['scrapers'])) {
                if (!empty($_REQUEST['arg']) && $_REQUEST['arg'] == 'search') {
                    $search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'manageRoles');
                } else if (!empty($_REQUEST['context'])) {
                    $search_array =
                        $_SESSION['LAST_SEARCH']['scrapers']['SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['scrapers']['PAGING'];
                }
            }
            if (empty($search_array)) {
                $search_array[] = ["name", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $scraper_model, "SCRAPERS",
            C\DEFAULT_ADMIN_PAGING_NUM/5, $search_array, "");
        if ($data['FORM_TYPE'] == 'add') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        return $data;
    }
    /**
     * Handles admin request related to the search filter activity
     *
     * This activity allows a user to specify hosts whose web pages are to be
     * filtered out the search results
     *
     * @return array $data info about the groups and their contents for a
     *     particular crawl mix
     */
    public function resultsEditor()
    {
        $parent = $this->parent;
        $verticals_model = $parent->model("searchverticals");
        $data["ELEMENT"] = "resultseditor";
        $data['SCRIPT'] = "";
        $data["URL_ACTIONS"] = [
            -1 => tl('crawl_component_results_no_action'),
            C\SEARCH_FILTER_GROUP_ITEM =>
                tl('crawl_component_results_filter_host'),
            C\SEARCH_EDIT_GROUP_ITEM =>
                tl('crawl_component_results_edit_result')
        ];
        foreach (["ID", "URL_ACTION", "URL", "TITLE",
            "DESCRIPTION", "MAP_URLS"] as $field) {
            $data[$field] = (isset($_REQUEST[$field])) ?
                $parent->clean($_REQUEST[$field], "string") :
                 ((isset($data[$field]) ) ? $data[$field] : "");
        }
        if (empty($data["URL_ACTION"])) {
            $data["URL_ACTION"] = -1;
        }
        if ($data["URL"] != "") {
            $data["URL"] = UrlParser::canonicalLink($data["URL"], "");
            if ($data["URL_ACTION"] == C\SEARCH_FILTER_GROUP_ITEM) {
                $data["URL"] = UrlParser::getHost($data["URL"]);
            }
        }
        $data['SCRIPT'] .= "switchUrlAction(" . $data["URL_ACTION"] . ");";
        $query = (empty($_REQUEST['QUERY'])) ? "" :
            $parent->clean($_REQUEST['QUERY'], "string");
        $map_query = (empty($_REQUEST['MAP_QUERY'])) ? "" :
            $parent->clean($_REQUEST['MAP_QUERY'], "string");
        $kwiki_page = (empty($_REQUEST['KWIKI_PAGE'])) ? "" :
            $parent->clean($_REQUEST['KWIKI_PAGE'], "string");
        if (isset($_REQUEST['arg']) ) {
            switch ($_REQUEST['arg'])
            {
                case "loadkwiki":
                    if (empty($query)) {
                        $_REQUEST['MODE'] = "loadkwiki";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_no_query'),
                            ["MODE"]);
                    }
                    $kwiki = $verticals_model->getKnowledgeWiki($query,
                        L\getLocaleTag(), true);
                    $_REQUEST["MODE"] = 'editkwiki';
                    if (empty($kwiki)) {;
                        $_REQUEST["QUERY"] = $query;
                        $_REQUEST["KWIKI_PAGE"] = "";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_kwiki_new'),
                            ["QUERY", "KWIKI_PAGE", "MODE"]);
                    } else {
                        $_REQUEST["ID"] = $kwiki["ID"];
                        $_REQUEST["QUERY"] = $query;
                        list( , $kwiki['PAGE']) =
                            $parent->parsePageHeadVars(
                                $kwiki['PAGE'], true);
                        $_REQUEST["KWIKI_PAGE"] = $kwiki['PAGE'];
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_page_loaded'),
                            ["ID", "QUERY", "KWIKI_PAGE", "MODE"]);
                    }
                    break;
                case "loadquerymap":
                    if (empty($map_query)) {
                        $_REQUEST['MODE'] = "loadquerymap";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_no_query_map'),
                            ["MODE"]);
                    }
                    $map_urls = $verticals_model->getQueryMap($map_query,
                        L\getLocaleTag());
                    $_REQUEST["MODE"] = 'editquerymap';
                    $_REQUEST['MAP_QUERY'] = $map_query;
                    if (empty($map_urls)) {
                        $_REQUEST['MAP_URLS'] = [];
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_map_created'),
                            ["MAP_QUERY", "MAP_URLS", "MODE"]);
                    } else {
                        $_REQUEST['MAP_URLS'] = implode("\n", $map_urls);
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_map_loaded'),
                            ["MAP_QUERY", "MAP_URLS", "MODE"]);
                    }
                    break;
                case "loadurl":
                    if (empty($_REQUEST['URL'])) {
                        $_REQUEST['MODE'] = "loadurl";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_no_url'),
                            ["MODE"]);
                    }
                    $url = $parent->clean($_REQUEST['URL'], "web-url");
                    $summary = $verticals_model->getEditedPageResult($url);
                    $_REQUEST["MODE"] = 'editurl';
                    if (empty($summary)) {
                        $_REQUEST["URL"] = $url;
                        $_REQUEST["TITLE"] = "";
                        $_REQUEST["DESCRIPTION"] = "";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_url_new'),
                            ["URL", "TITLE", "DESCRIPTION", "MODE"]);
                    } else {
                        $_REQUEST["ID"] = $summary["ID"];
                        $_REQUEST["URL"] = $summary["URL"];
                        $_REQUEST["URL_ACTION"] = $summary["URL_ACTION"];
                        $_REQUEST["TITLE"] = $summary["TITLE"];
                        $_REQUEST["DESCRIPTION"] = $summary["DESCRIPTION"];
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_page_loaded'),
                            ["ID", "URL", "URL_ACTION", "TITLE",
                            "DESCRIPTION", "MODE"]);
                    }
                    break;
                case "savekwiki":
                    if (empty($query)) {
                        $_REQUEST["MODE"] = 'loadkwiki';
                        $_REQUEST["QUERY"] = "";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_no_query'),
                            ["ID", "QUERY", "KWIKI_PAGE", "MODE"]);
                    }
                    $query = mb_strtolower(str_replace("-", " ", $query));
                    $_REQUEST["QUERY"] = $query;
                    $_REQUEST["MODE"] = 'editkwiki';
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
                    $head_string = "";
                    foreach ($page_defaults as $key => $default) {
                        $value = (empty($head_vars[$key])) ? $default :
                            $head_vars[$key];
                        $head_string .= urlencode($key) . "=" .
                            urlencode($value) . "\n\n";
                    }
                    $_REQUEST["KWIKI_PAGE"] = $kwiki_page;
                    if (!empty($kwiki_page)) {
                        $kwiki_page = $head_string . "END_HEAD_VARS" .
                            $kwiki_page;
                    }
                    $_REQUEST["ID"] = $verticals_model->setPageName(C\ROOT_ID,
                        C\SEARCH_GROUP_ID, $query, $kwiki_page,
                        L\getLocaleTag(), time(),
                        tl('crawl_component_results_editor_kwiki_edit',
                        $query), "");
                    return $parent->redirectWithMessage(
                        tl('crawl_component_results_editor_page_saved'),
                        ["ID", "QUERY", "KWIKI_PAGE", "MODE"]);
                    break;
                case "savequerymap":
                    if (empty($map_query)) {
                        $_REQUEST['MODE'] = "loadquerymap";
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_no_query_map'),
                            ["MODE"]);
                    }
                    $map_urls = explode("\n", $data['MAP_URLS']);
                    $verticals_model->setQueryMap($map_query,
                        $map_urls, L\getLocaleTag());
                    $_REQUEST['MAP_URLS'] = $data['MAP_URLS'];
                    return $parent->redirectWithMessage(
                        tl('crawl_component_results_editor_query_map_saved'),
                        ["MAP_QUERY", "MAP_URLS", "MODE"]);
                    break;
                case "saveurl":
                    $missing_page_field = ($data["URL"] == "") ? true: false;
                    if ($missing_page_field) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_results_editor_need_url'),
                            ["URL", "URL_ACTION", "TITLE", "DESCRIPTION",
                            "MODE"]);
                    }
                    $_REQUEST['ID'] = $verticals_model->updateUrlResult(
                        $data["ID"], $data["URL_ACTION"],
                        $data["URL"], $data["TITLE"],
                        $data["DESCRIPTION"]);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_results_editor_page_updated'),
                        ["ID", "URL_ACTION", "URL", "TITLE",
                        "DESCRIPTION", "MODE"]);
                    break;
            }
        }
        if (isset($_REQUEST['MODE']) &&
            in_array($_REQUEST['MODE'], ['loadkwiki', 'editkwiki'])) {
            $data["INCLUDE_STYLES"] = ["editor"];
            $data['MODE'] = $_REQUEST['MODE'];
            if ($data['MODE'] == 'editkwiki') {
                $this->initializeWikiEditor($data, "kwiki-page");
            }
            $data['QUERY'] = $query;
            $data['KWIKI_PAGE'] = html_entity_decode($kwiki_page);
            $data['knowledge_wiki_active'] = "active";
            $data['query_map_active'] = "";
            $data['edit_result_active'] = "";
            $data['SCRIPT'] .= "switchTab('knowledgetab', ['editresulttab',".
                "'querymaptab']);";
        } else if (isset($_REQUEST['MODE']) &&
            in_array($_REQUEST['MODE'], ['loadquerymap', 'editquerymap'])) {
            $data['knowledge_wiki_active'] = "";
            $data['query_map_active'] = "active";
            $data['MODE'] = $_REQUEST['MODE'];
            $data['MAP_QUERY'] = $map_query;
            $data['edit_result_active'] = "";
            $data['SCRIPT'] .= "switchTab('querymaptab', ['editresulttab',".
                "'knowledgetab']);";
        } else {
            $data['MODE'] = (!empty($_REQUEST['MODE']) &&
                $_REQUEST['MODE'] == 'editurl') ? 'editurl' : 'loadurl';
            $data['knowledge_wiki_active'] = "";
            $data['edit_result_active'] = "active";
            $data['query_map_active'] = "";
            $data['SCRIPT'] .= "switchTab('editresulttab', ['knowledgetab',".
                "'querymaptab']);";
        }
        return $data;
    }
    /**
     * Handles admin request related to the search sources activity
     *
     * The search sources activity allows a user to add/delete search sources
     * for news and podcasts, it also allows a user to control which subsearches
     * appear on the SearchView page
     *
     * @return array $data info about current search sources, and current
     *     sub-searches
     */
    public function searchSources()
    {
        $parent = $this->parent;
        $crawl_model = $parent->model("crawl");
        $source_model = $parent->model("source");
        $source_arguments = ["addsource", "cleardata", "deletesource",
            "editsource", "sourcesearch", "testsource"];
        $subsearch_arguments = ["addsubsearch", "deletesubsearch",
            "editsubsearch", "showsubsearch", "subsearchsearch"];
        $possible_arguments = array_merge($source_arguments,
            $subsearch_arguments);
        $request_fields = ['start_row', 'num_show', 'end_row',
            'SUBstart_row','SUBnum_show', 'SUBend_row'];
        $data = [];
        $data["ELEMENT"] = "searchsources";
        $data['SCRIPT'] = "";
        $data['SOURCE_TYPES'] = [-1 => tl('crawl_component_media_kind'),
            "rss" => tl('crawl_component_rss_feed'),
            "json" => tl('crawl_component_json_feed'),
            "html" => tl('crawl_component_html_feed'),
            "regex" => tl('crawl_component_regex_feed'),
            "feed_podcast" => tl('crawl_component_feed_podcast'),
            "scrape_podcast" => tl('crawl_component_scrape_podcast'),
            "trending_value" => tl('crawl_component_trending_value'),
            ];
        $data['PODCAST_EXPIRES'] = [
            -1 => tl('crawl_component_never'),
            C\ONE_DAY => tl('crawl_component_one_day'),
            C\ONE_WEEK => tl('crawl_component_one_week'),
            C\ONE_MONTH => tl('crawl_component_one_month'),
            C\ONE_YEAR => tl('crawl_component_one_year')
            ];
        $source_type_flag = false;
        if (isset($_REQUEST['type']) &&
            in_array($_REQUEST['type'],
            array_keys($data['SOURCE_TYPES']))) {
            $data['SOURCE_TYPE'] = $_REQUEST['type'];
            $source_type_flag = true;
        } else {
            $data['SOURCE_TYPE'] = -1;
        }
        $machine_urls = $parent->model("machine")->getQueueServerUrls();
        $search_lists = $crawl_model->getCrawlList(false, true,
            $machine_urls);
        $data["SEARCH_LISTS"] = [-1 =>
            tl('crawl_component_sources_indexes'),
            "trending:" => tl('crawl_component_trend_category'),
        ];
        foreach ($search_lists as $item) {
            $data["SEARCH_LISTS"]["i:" . $item["CRAWL_TIME"]] =
                tl('crawl_component_index') . ":" .$item["DESCRIPTION"];
        }
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = L\remoteAddress();
        }
        $search_lists = $crawl_model->getMixList($user);
        foreach ($search_lists as $item) {
            $data["SEARCH_LISTS"]["m:".$item["TIMESTAMP"]] =
                tl('crawl_component_mix') . ":" .$item["NAME"];
        }
        $trend_lists = $source_model->getMediaCategories(['feed_podcast']);
        foreach ($trend_lists as $item) {
            $data["TREND_CATEGORIES"][$item["NAME"]] = $item["NAME"];
        }
        if (isset($_REQUEST['trend_category']) &&
            in_array($_REQUEST['trend_category'], $data["TREND_CATEGORIES"])) {
            $data['TREND_CATEGORY'] = $_REQUEST['trend_category'];
        } else {
            $data['TREND_CATEGORY'] = "news";
        }
        $data['TREND_SORTS'] = ["term_asc" => tl('crawl_component_term_asc'),
            "term_desc" => tl('crawl_component_term_desc'),
            "score_asc" => tl('crawl_component_score_asc'),
            "score_desc" => tl('crawl_component_score_desc'),
        ];
        if (isset($_REQUEST['trend_sort']) &&
            in_array($_REQUEST['trend_sort'],
            array_keys($data['TREND_SORTS']))) {
            $data['TREND_SORT'] = $_REQUEST['trend_sort'];
        } else {
            $data['TREND_SORT'] = "score_desc";
        }
        $n = C\NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] = [$n => $n, 2*$n => 2*$n, 5*$n=> 5*$n,
            10*$n => 10*$n];
        if (isset($_REQUEST['per_page']) &&
            in_array($_REQUEST['per_page'], array_keys($data['PER_PAGE']))) {
            $data['PER_PAGE_SELECTED'] = $_REQUEST['per_page'];
        } else {
            $data['PER_PAGE_SELECTED'] = C\NUM_RESULTS_PER_PAGE;
        }
        $locales = $parent->model("locale")->getLocaleList();
        $data["LANGUAGES"] = [];
        foreach ($locales as $locale) {
            $data["LANGUAGES"][$locale['LOCALE_TAG']] = $locale['LOCALE_NAME'];
        }
        if (isset($_REQUEST['language']) &&
            in_array($_REQUEST['language'],
                array_keys($data["LANGUAGES"]))) {
            $data['SOURCE_LOCALE_TAG'] =
                $_REQUEST['language'];
        } else {
            $data['SOURCE_LOCALE_TAG'] = C\DEFAULT_LOCALE;
        }
        $data['LANDING_PRIORITIES'] = [
            "highlight:false" => tl('crawl_component_no_highlight')];
        for ($i = 1; $i < 20; $i++) {
            $data['LANDING_PRIORITIES']["highlight:$i"] = "$i";
        }
        if (!empty($_REQUEST['landing_highlight']) &&
            preg_match('/highlight:\d+/', $_REQUEST['landing_highlight'])) {
            $data['LANDING_HIGHLIGHT'] = $_REQUEST['landing_highlight'];
        } else {
            $data['LANDING_HIGHLIGHT'] = "highlight:false";
        }
        $data["CURRENT_SOURCE"] = [
            "name" => "", "type"=> $data['SOURCE_TYPE'], "source_url" => "",
            "aux_info" => "", 'category' => "news", 'channel_path' => "",
            "image_xpath" => "", "trending_stop_regex"=> "", 'item_path' => "",
            'title_path' => "", 'description_path' => "", 'link_path' => "",
            "language" => $data['SOURCE_LOCALE_TAG']];
        $data["CURRENT_SUBSEARCH"] = [
            "default_query" => "", "folder_name" =>"", "index_identifier" => "",
            "landing_highlight" => "highlight:false",
            "locale_string" => "", "per_page" => $data['PER_PAGE_SELECTED'],
            "trend_category" => $data['TREND_CATEGORY'],
            "trend_sort" => $data['TREND_SORT']];
        if (!isset($_REQUEST['arg']) || in_array($_REQUEST['arg'],
            $source_arguments)) {
            $data['media_source_active'] = 'active';
            $data['subsearches_active'] = '';
            $data['SCRIPT'] .= "switchTab('mediasourcetab', 'subsearchestab');";
        } else {
            $data['media_source_active'] = '';
            $data['subsearches_active'] = 'active';
            $data['SCRIPT'] .= "switchTab('subsearchestab', 'mediasourcetab');";
        }
        $data['SOURCE_FORM_TYPE'] = "addsource";
        $data["SEARCH_FORM_TYPE"] = "addsubsearch";
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg'])
            {
                case "addsource":
                    if (!$source_type_flag) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_source_type'),
                            $request_fields);
                    }
                    $must_have = ["name", "type", 'source_url'];
                    if (isset($_REQUEST['type']) &&
                        !in_array($_REQUEST['type'], ['feed_podcast',
                        'scrape_podcast'])) {
                        $_REQUEST['trending_stop_regex'] ??= "";
                    }
                    $is_parse_feed = false;
                    if (isset($_REQUEST['type']) &&
                        in_array($_REQUEST['type'], ['html', 'json',
                        'regex'] )) {
                        $is_parse_feed = true;
                        $must_have = array_merge($must_have, [
                            'channel_path', 'item_path', 'title_path',
                            'description_path', 'link_path']);
                    } else if (isset($_REQUEST['type']) &&
                        in_array($_REQUEST['type'], ['feed_podcast',
                        'scrape_podcast'])) {
                        if (isset($_REQUEST['expires'])) {
                            $_REQUEST['category'] = $_REQUEST['expires'];
                        }
                        $is_parse_feed = true;
                    } else if (isset($_REQUEST['type']) &&
                        $_REQUEST['type'] == 'trending_value') {
                        $must_have[] = 'image_xpath';
                        $is_parse_feed = true;
                    }
                    if (isset($_REQUEST['type']) && $_REQUEST['type'] == -1) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_missing_type'),
                            array_merge($request_fields, $must_have));
                    }
                    $to_clean = array_merge($must_have,
                        ['aux_info', 'category','language', 'image_xpath',
                        'channel_path', 'item_path', 'title_path',
                        'trending_stop_regex', 'description_path',
                        'link_path']);
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            trim($parent->clean($_REQUEST[$clean_me],
                            "string"))  : "";
                        if ($clean_me == "source_url") {
                            $r[$clean_me] = UrlParser::canonicalLink(
                                $r[$clean_me], "");
                            if ($r[$clean_me] == "/") {
                                $r[$clean_me] = "";
                            }
                        }
                        if (in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "" ) {
                            echo $clean_me;
                            if (empty($_REQUEST['modify_add'])) {
                                $_REQUEST['modify_add'] = [];
                                $request_fields[] = "modify_add";
                            }
                            $_REQUEST['modify_add'][$clean_me] = $clean_me;
                        } else {
                            unset($_REQUEST['modify_add'][$clean_me]);
                        }
                    }
                    if (!empty($_REQUEST['modify_add'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_missing_fields'),
                            array_merge($request_fields, $to_clean));
                    }
                    if ($is_parse_feed) {
                        $r['aux_info'] = $r['channel_path'] . "###" .
                            $r['item_path'] . "###" . $r['title_path'].
                            "###".$r['description_path']."###".$r['link_path'].
                            "###" . $r['image_xpath'] . "###" .
                            $r['trending_stop_regex'];
                    } else if (isset($_REQUEST['type']) &&
                        $_REQUEST['type'] == 'rss') {
                        $r['aux_info'] = $r['image_xpath'] . "###" .
                            $r['trending_stop_regex'];
                    }
                    $source_model->addMediaSource(
                        $r['name'], $r['type'], $r['category'],
                        $r['source_url'], $r['aux_info'], $r['language']);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_media_source_added'),
                        $request_fields);
                    break;
                case "addsubsearch":
                    $to_clean = ["folder_name", 'index_identifier'];
                    $must_have = $to_clean;
                    $to_clean[] = "default_query";
                    $_REQUEST['arg'] = "showsubsearch";
                    $request_fields[] = 'arg';
                    foreach ($to_clean as $clean_me) {
                        $r[$clean_me] = (isset($_REQUEST[$clean_me])) ?
                            trim($parent->clean($_REQUEST[$clean_me],
                            "string")) : "";
                        if (in_array($clean_me, $must_have) &&
                            $r[$clean_me] == "") {
                            return $parent->redirectWithMessage(
                                tl('crawl_component_missing_fields'),
                                array_merge($request_fields, $to_clean));
                        }
                    }
                    if ($r['index_identifier'] == 'trending:') {
                        $r['default_query'] = $r['index_identifier'] .
                            $data['TREND_CATEGORY'] . ":" .$data['TREND_SORT'];
                        $r['index_identifier'] = -1;
                    }
                    $r['default_query'] .= (empty($data['LANDING_HIGHLIGHT'])
                        || $data['LANDING_HIGHLIGHT'] == 'highlight:false') ?
                        "" : " " . $data['LANDING_HIGHLIGHT'];
                    $source_model->addSubsearch(
                        $r['folder_name'], $r['index_identifier'],
                        $data['PER_PAGE_SELECTED'], $r['default_query']);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_subsearch_added'),
                        $request_fields);
                        break;
                case "cleardata":
                    $profile = $parent->model("profile")->getProfile(
                        C\WORK_DIRECTORY);
                    $machines = null;
                    if (!empty($profile['MEDIA_MODE']) &&
                        $profile['MEDIA_MODE'] == "distributed") {
                        $machines = $parent->model("machine")->getMachineList();
                    }
                    $source_model->clearFeedData($machines);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_clearing_data'),
                        $request_fields);
                    break;
                case "deletesource":
                    if (!isset($_REQUEST['ts'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_delete_source'),
                            $request_fields);
                    }
                    $timestamp = $parent->clean($_REQUEST['ts'], "string");
                    $source_model->deleteMediaSource($timestamp);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_media_source_deleted'),
                        $request_fields);
                    break;
                case "deletesubsearch":
                    $_REQUEST['arg'] = "showsubsearch";
                    $request_fields[] = 'arg';
                    if (!isset($_REQUEST['fn'])) {
                        return $parent->redirectWithMessage(
                            tl('crawl_component_no_delete_source'),
                            $request_fields);
                        break;
                    }
                    $folder_name = $parent->clean($_REQUEST['fn'], "string");
                    $source_model->deleteSubsearch($folder_name);
                    return $parent->redirectWithMessage(
                        tl('crawl_component_subsearch_deleted'),
                        $request_fields);
                    break;
                case "editsubsearch":
                    $data['SEARCH_FORM_TYPE'] = "editsubsearch";
                    $subsearch = false;
                    $folder_name = (isset($_REQUEST['fn'])) ?
                        $parent->clean($_REQUEST['fn'], "string") : "";
                    if ($folder_name) {
                        $subsearch = $source_model->getSubsearch($folder_name);
                    }
                    if (!$subsearch) {
                        $data['SOURCE_FORM_TYPE'] = "addsubsearch";
                        break;
                    }
                    if (preg_match("/highlight:\d+/ui",
                        $subsearch['DEFAULT_QUERY'], $highlight_priority)) {
                        $subsearch['LANDING_HIGHLIGHT'] =
                            $highlight_priority[0];
                        $subsearch['DEFAULT_QUERY'] =
                            trim(preg_replace('/highlight:\d+/ui', "",
                            $subsearch['DEFAULT_QUERY']));
                    }
                    if ($subsearch['INDEX_IDENTIFIER'] == -1 &&
                        substr($subsearch['DEFAULT_QUERY'], 0, 9) ==
                        "trending:") {
                        $subsearch['INDEX_IDENTIFIER'] = "trending:";
                        $subsearch_parts = explode(":",
                            $subsearch['DEFAULT_QUERY']);
                        if (!empty($subsearch_parts[1]) &&
                            in_array($subsearch_parts[1],
                            $data["TREND_CATEGORIES"])) {
                            $subsearch['TREND_CATEGORY'] = $subsearch_parts[1];
                        } else {
                            $subsearch['TREND_CATEGORY'] = 'news';
                        }
                        if (!empty($subsearch_parts[2]) &&
                            in_array($subsearch_parts[2],
                            array_keys($data["TREND_SORTS"]))) {
                            $subsearch['TREND_SORT'] = $subsearch_parts[2];
                        } else {
                            $subsearch['TREND_SORT'] = 'score_desc';
                        }
                    }
                    $data['fn'] = $folder_name;
                    $update = false;
                    foreach ($data['CURRENT_SUBSEARCH'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'name') {
                            $subsearch[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_SUBSEARCH'][$field] =
                                $subsearch[$upper_field];
                            $update = true;
                        } else if (isset($subsearch[$upper_field])){
                            $data['CURRENT_SUBSEARCH'][$field] =
                                $subsearch[$upper_field];
                        }
                    }
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    if ($update) {
                        $fields = array_merge(array("arg", "fn"),
                            $request_fields);
                        $s = $subsearch;
                        if ($s['INDEX_IDENTIFIER'] == 'trending:') {
                            $s['DEFAULT_QUERY'] =
                                trim(preg_replace('/trending:\w+(\b)/ui', '$1',
                                $s['DEFAULT_QUERY']));
                            $s['DEFAULT_QUERY'] = $s['INDEX_IDENTIFIER'] .
                                $data['TREND_CATEGORY'] . ":" .
                                $data['TREND_SORT'] . " ";
                            $s['INDEX_IDENTIFIER'] = -1;
                        }
                        $s['DEFAULT_QUERY'] =
                            trim(preg_replace('/highlight:\w+(\b)/ui', '$1',
                            $s['DEFAULT_QUERY']));
                        unset($s['TREND_CATEGORY'], $s['TREND_SORT'],
                            $s['LANDING_HIGHLIGHT']);
                        $s['DEFAULT_QUERY'] .=
                            (empty($data['LANDING_HIGHLIGHT'])
                            || $data['LANDING_HIGHLIGHT'] == 'highlight:false')?
                            "" : " " . $data['LANDING_HIGHLIGHT'];
                        $subsearch = $s;
                        $source_model->updateSubsearch($subsearch);
                        return $parent->redirectWithMessage(
                            tl('crawl_component_subsearch_updated'),
                            $fields);
                    }
                    break;
                case "editsource":
                    $data['SOURCE_FORM_TYPE'] = "editsource";
                    $source = false;
                    $timestamp = (isset($_REQUEST['ts'])) ?
                        $parent->clean($_REQUEST['ts'], "string") : "";
                    if ($timestamp) {
                        $source = $source_model->getMediaSource($timestamp);
                    }
                    if (!$source) {
                        $data['SOURCE_FORM_TYPE'] = "addsource";
                        break;
                    }
                    $data['ts'] = $timestamp;
                    $update = false;
                    $is_parse_feed = false;
                    $is_rss_feed = false;
                    if (isset($_REQUEST['type']) &&
                        in_array($_REQUEST['type'], ['feed_podcast',
                        'scrape_podcast'])) {
                        if (isset($_REQUEST['expires'])) {
                            $_REQUEST['category'] = $_REQUEST['expires'];
                        }
                    }
                    $aux_parts = explode("###", $source['AUX_INFO']);
                    if (in_array($source['TYPE'], ['html', 'json',
                        'regex', 'feed_podcast', 'scrape_podcast',
                        'trending_value'])) {
                        $is_parse_feed = true;
                        $aux_parts = array_pad($aux_parts, 7, "");
                        list($source['CHANNEL_PATH'],
                            $source['ITEM_PATH'], $source['TITLE_PATH'],
                            $source['DESCRIPTION_PATH'],
                            $source['LINK_PATH']) = $aux_parts;
                        $source['IMAGE_XPATH'] = $aux_parts[5] ?? "";
                        $source['TRENDING_STOP_REGEX'] = $aux_parts[6] ?? "";
                    } else if ($source['TYPE'] == 'rss') {
                        $is_rss_feed = true;
                        $source['IMAGE_XPATH'] = $aux_parts[0] ?? "";
                        $source['TRENDING_STOP_REGEX'] = $aux_parts[1] ?? "";
                    }
                    foreach ($data['CURRENT_SOURCE'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'name') {
                            $source[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_SOURCE'][$field] =
                                $source[$upper_field];
                            $update = true;
                        } else if (isset($source[$upper_field])){
                            $data['CURRENT_SOURCE'][$field] =
                                $source[$upper_field];
                        }
                    }
                    $data['SCRIPT'] .= "elt('focus-button').focus();";
                    if ($update) {
                        if ($is_parse_feed) {
                            $source['AUX_INFO'] =
                                $source['CHANNEL_PATH']."###" .
                                $source['ITEM_PATH']."###".
                                $source['TITLE_PATH'] . "###" .
                                $source['DESCRIPTION_PATH'] . "###".
                                $source['LINK_PATH']. "###".
                                $source['IMAGE_XPATH'] . "###" .
                                $source['TRENDING_STOP_REGEX'];
                        } else if ($is_rss_feed) {
                            $source['AUX_INFO'] =  $source['IMAGE_XPATH'] .
                                "###" . $source['TRENDING_STOP_REGEX'];
                        }
                        unset($source['CHANNEL_PATH'], $source['ITEM_PATH'],
                            $source['TITLE_PATH'], $source['DESCRIPTION_PATH'],
                            $source['LINK_PATH'], $source['IMAGE_XPATH'],
                            $source['TRENDING_STOP_REGEX']);

                        $source_model->updateMediaSource($source);
                        $fields = array_merge(array("arg", "ts"),
                            $request_fields);
                        return $parent->redirectWithMessage(
                            tl('crawl_component_media_source_updated'),
                            $fields);
                    }
                    break;
                case "sourcesearch":
                    $data['SOURCE_FORM_TYPE'] = "search";
                    $media_search_array =
                        $parent->tableSearchRequestHandler($data,
                            "searchSources", ['ALL_FIELDS' => [
                                'name', 'source_url', 'language', 'category',
                                'type']], '_media');
                    if (empty($_SESSION['LAST_SEARCH']['searchSources_media'])||
                        isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['searchSources_media'] =
                            $_SESSION['SEARCH']['searchSources_media'];
                        unset($_SESSION['SEARCH']['searchSources_media']);
                    } else {
                        $default_search = true;
                    }
                    break;
                case "subsearchsearch":
                    $data['SEARCH_FORM_TYPE'] = "search";
                    $subsearch_search_array =
                        $parent->tableSearchRequestHandler($data,
                            "searchSources", ['ALL_FIELDS' => [
                                'folder_name', 'index_identifier', 'per_page',
                                'default_query'], 'EQUAL_COMPARISON_TYPES' =>
                                ['index_identifier'],
                                'INEQUALITY_COMPARISON_TYPES' =>
                                ['per_page']], '_subsearch');
                    if (empty($_SESSION['LAST_SEARCH'][
                        'searchSources_subsearch'])||isset($_REQUEST['name'])) {
                        $_SESSION['LAST_SEARCH']['searchSources_subsearch'] =
                            $_SESSION['SEARCH']['searchSources_subsearch'];
                        unset($_SESSION['SEARCH']['searchSources_subsearch']);
                    } else {
                        $default_search = true;
                    }
                    break;
                case "testsource":
                    $data['SOURCE_FORM_TYPE'] = "testsource";
                    $source = false;
                    $timestamp = (isset($_REQUEST['ts'])) ?
                        $parent->clean($_REQUEST['ts'], "string") : "";
                    if ($timestamp) {
                        $source = $source_model->getMediaSource($timestamp);
                    }
                    if (!$source) {
                        $data['SOURCE_FORM_TYPE'] = "addsource";
                        break;
                    }
                    $data['ts'] = $timestamp;
                    if (in_array($source['TYPE'], ['html', 'json',
                        'regex', 'rss'])) {
                        $feeds_update_job = new M\FeedsUpdateJob();
                        $feeds_update_job->parseFeedAuxInfo($source);
                        $data['FEED_TEST_RESULTS'] =
                            $feeds_update_job->updateFoundItemsOneGo([$source],
                            C\ONE_WEEK, true);
                    } else if (in_array($source['TYPE'], ['feed_podcast',
                        'scrape_podcast'])) {
                        $wiki_update_job = new M\WikiMediaJob();
                        $wiki_update_job->parsePodcastAuxInfo($source, true);
                        $data['FEED_TEST_RESULTS'] = "<h2>" .
                            tl('crawl_component_parsed_feed'). "</h2>\n" .
                            "<pre  class='source-test'>\n" .
                            print_r($source, true) . "</pre>\n";
                        $data['FEED_TEST_RESULTS'] .=
                            $wiki_update_job->updatePodcastsOneGo([$source],
                            C\ONE_WEEK, true);
                    }
                    break;
            }
        }
        $data['CAN_LOCALIZE'] = $parent->model("user")->isAllowedUserActivity(
            $_SESSION['USER_ID'], "manageLocales");
        if (empty($media_search_array)) {
            if (!empty($_SESSION['LAST_SEARCH']['searchSources_media'])) {
                if (!empty($_REQUEST['arg']) &&
                    $_REQUEST['arg'] == 'sourcesearch') {
                    $media_search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'searchSources', '_media');
                } else if (!empty($_REQUEST['context'])) {
                    $media_search_array =
                        $_SESSION['LAST_SEARCH']['searchSources_media'][
                            'SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['searchSources_media'][
                            'PAGING'];
                }
            }
            if (empty($media_search_array)) {
                $media_search_array[] = ["name", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $source_model, "MEDIA_SOURCES",
            C\DEFAULT_ADMIN_PAGING_NUM/5, $media_search_array);
        if (empty($subsearch_search_array)) {
            if (!empty($_SESSION['LAST_SEARCH']['searchSources_subsearch'])) {
                if (!empty($_REQUEST['arg']) &&
                    $_REQUEST['arg'] == 'subsearchsearch') {
                    $subsearch_search_array =
                        $parent->restoreLastSearchFromSession($data,
                        'searchSources', '_subsearch');
                } else if (!empty($_REQUEST['context'])) {
                    $subsearch_search_array =
                        $_SESSION['LAST_SEARCH']['searchSources_subsearch'][
                            'SEARCH_ARRAY'];
                    $data['PAGING'] =
                        $_SESSION['LAST_SEARCH']['searchSources_subsearch'][
                            'PAGING'];
                }
            }
            if (empty($subsearch_search_array)) {
                $subsearch_search_array[] = ["FOLDER_NAME", "", "", "ASC"];
            }
        }
        $parent->pagingLogic($data, $source_model,
            "SUBSEARCHES", C\DEFAULT_ADMIN_PAGING_NUM/5,
            $subsearch_search_array, "SUB", "SUBSEARCH");
        foreach ($data["SUBSEARCHES"] as $search) {
            if (!isset($data["SEARCH_LISTS"]
                [trim($search['INDEX_IDENTIFIER'])])) {
                $source_model->deleteSubsearch($search["FOLDER_NAME"]);
            }
        }
        if ($data['SOURCE_FORM_TYPE'] != 'search') {
            $data['SCRIPT'] .= "source_type = elt('source-type');" .
                "source_type.onchange = switchSourceType; " .
                "switchSourceType();";
        }
        if ($data['SEARCH_FORM_TYPE'] != 'search') {
            $data['SCRIPT'] .= "index_source = elt('index-source');" .
                "index_source.onchange = switchIndexSource; " .
                "switchIndexSource();";
        }
        if ($data['SEARCH_FORM_TYPE'] == 'addsubsearch') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        if ($data['SOURCE_FORM_TYPE'] == 'addsource') {
            $modify_add = (empty($_REQUEST['modify_add'])) ? "false" : "true";
            $data['MODIFY_ADD'] = $_REQUEST['modify_add'] ?? [];
            $data['SCRIPT'] .= "setDisplay('media-form-row', $modify_add);";
        }
        return $data;
    }
}
