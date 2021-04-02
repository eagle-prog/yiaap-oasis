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
use seekquarry\yioop\library\AnalyticsManager;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\models\Model;
use seekquarry\yioop\controllers\components\Component;
use seekquarry\yioop\views\View;

/**
 * Load crawlHash  and timing functions
 */
require_once C\BASE_DIR . "/library/Utility.php";
/** For guessing locale and formatting date based on locale guessed*/
require_once C\BASE_DIR . "/library/LocaleFunctions.php";
/**
 * Translate the supplied arguments into the current locale.
 *
 * This function is a convenience copy of the same function
 * @see seekquarry\yioop\library\tl() to this subnamespace
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    return call_user_func_array(C\NS_LIB . "tl", func_get_args());
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/**
 * Base controller class for all controllers on
 * the SeekQuarry site.
 *
 * @author Chris Pollett
 */
abstract class Controller
{
    /**
     *  Stores a reference to the web server when Yioop runs in CLI mode,
     *  it acts as request router in non-CLI  mode.
     *  In CLI, mode it is useful for caching files in RAM as they are read
     * @var seekquarry\yioop\library\WebSite
     */
    public $web_site;
    /**
    * Array of instances of components used by this controller
     * @var array
     */
    public $component_instances;
    /**
     * Array of instances of views used by this controller
     * @var array
     */
    public $view_instances = [];
    /**
     * Array of instances of models used by this controller
     * @var array
     */
    public $model_instances;
    /**
     * Array of instances of indexing_plugins used by this controller
     * @var array
     */
    public $plugin_instances;
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to
     * @var array
     */
    public $activities = [];
    /**
     * Associative array of activity => component activity is on, used
     * by @see Controller::call method to actually invoke a given activity
     * on a given component
     * @var array
     */
    public $activity_component = [];
    /**
     * Associative array of $components activities for this controller
     * Components are collections of activities (a little like traits) which
     * can be reused.
     *
     * @var array
     */
    public static $component_activities = [];
    /**
     * Sets up component activities, instance array, and plugins.
     *
     * @param seekquarry\yioop\library\WebSite $web_site is the web server
     *      when Yioop runs in CLI mode, it acts as request router in non-CLI
     *      mode. In CLI, mode it is useful for caching files in RAM as they
     *      are read
     */
    public function __construct($web_site = null)
    {

        $this->web_site = $web_site; /* for redirects to work this needs to
            be intialized here */
        // this is to force a signout request to signout in all controllers
        if (isset($_REQUEST['a']) && $_REQUEST['a'] == "signout") {
            $_SESSION = [];
            unset($_REQUEST);
            return $this->redirectWithMessage(
                tl('search_controller_logout_successful'));
        }
        /* if no signout was made, get on with the business of building
           this controller
         */
        mb_internal_encoding("UTF-8");
        mb_regex_encoding("UTF-8");
        $class = get_class($this);
        foreach ($class::$component_activities as $component => $activities) {
            foreach ($activities as $activity) {
                $this->activity_component[$activity] = $component;
                $this->activities[] = $activity;
            }
        }
        $this->component_instances = [];
        $this->plugins_instances = [];
        $this->model_instances = [];
        $this->view_instances = [];
    }
    /**
     * This function should be overriden to web handle requests
     */
    public abstract function processRequest();
    /**
     * Dynamic loader for Component objects which might live on the current
     * Component
     *
     * @param string $component name of model to return
     */
    public function component($component)
    {
        if (!isset($this->component_instances[$component])) {
            $component_name = C\NS_COMPONENTS . ucfirst($component) .
                "Component";
            $this->component_instances[$component] =
                new $component_name($this);
        }
        return $this->component_instances[$component];
    }
    /**
     * Dynamic loader for Model objects which might live on the current
     * Controller
     *
     * @param string $model name of model to return
     */
    public function model($model)
    {
        if (!isset($this->model_instances[$model])) {
            $model_name = C\NS_MODELS . ucfirst($model) . "Model";
            $this->model_instances[$model] = new $model_name(C\DB_NAME,
                true, $this->web_site);
        }
        return $this->model_instances[$model];
    }
    /**
     * Dynamic loader for Plugin objects which might live on the current
     * Controller
     *
     * @param string $plugin name of Plugin to return
     */
    public function plugin($plugin)
    {
        if (!isset($this->plugin_instances[$plugin])) {
            $plugin_name = C\NS_PLUGINS .
                ucfirst($plugin)."Plugin";
            $this->plugin_instances[$plugin] = new $plugin_name();
        }
        return $this->plugin_instances[$plugin];
    }
    /**
     * Used to get a list of all available indexing plugins for this Yioop
     * instance.
     *
     * @param array elts of which are names of indexing plugins
     */
    public function getIndexingPluginList()
    {
        $dir_prefixes = [C\BASE_DIR, C\APP_DIR];
        foreach ($dir_prefixes as $dir_prefix) {
            $plugin_dir = $dir_prefix . "/library/indexing_plugins/";
            $plugin_dir_len = strlen($plugin_dir);
            $plugin_ext_len = strlen("Plugin.php");
            foreach (glob("$plugin_dir*Plugin.php") as $filename) {
                $tmp_plug_name = substr($filename, $plugin_dir_len,
                    -$plugin_ext_len);
                if ($tmp_plug_name != "Indexing") {
                    $indexing_plugins[] = $tmp_plug_name;
                }
            }
        }
        return $indexing_plugins;
    }
    /**
     * Dynamic loader for View objects which might live on the current
     * Controller
     *
     * @param string $view name of view to return
     */
    public function view($view)
    {
        if (!isset($this->view_instances[$view])) {
            $view_name = C\NS_VIEWS . ucfirst($view)."View";
            $this->view_instances[$view] = new $view_name($this);
        }
        return $this->view_instances[$view];
    }
    /**
     * Send the provided view to output, drawing it with the given
     * data variable, using the current locale for translation, and
     * writing mode
     *
     * @param string $view   the name of the view to draw
     * @param array $data   an array of values to use in drawing the view
     */
    public function displayView($view, $data)
    {
        $data['LOCALE_TAG'] = L\getLocaleTag();
        $data['LOCALE_DIR'] = L\getLocaleDirection();
        $data['BLOCK_PROGRESSION'] = L\getBlockProgression();
        $data['WRITING_MODE'] = L\getWritingMode();
        if (C\QUERY_STATISTICS) {
            $data['QUERY_STATISTICS'] = [];
            $machine =  isset($_SERVER["HTTP_HOST"]) ?
                htmlentities($_SERVER["HTTP_HOST"]) : "localhost";
            $machine_uri = isset($_SERVER['REQUEST_URI']) ?
                htmlentities($_SERVER['REQUEST_URI']) : "/";
            $protocol = (isset($_SERVER["HTTPS"])) ? "https://" : "http://";
            if ($machine == '::1') { //IPv6 :(
                $machine = "[::1]/";
                //used if the fetching and queue serving on the same machine
            }
            $data['YIOOP_INSTANCE'] = $protocol . $machine . $machine_uri;
            $data['TOTAL_ELAPSED_TIME'] = 0;
            foreach ($this->model_instances as $model_name => $model) {
                $data['QUERY_STATISTICS'] = array_merge(
                    $model->db->query_log,
                    $data['QUERY_STATISTICS']
                    );
                $data['TOTAL_ELAPSED_TIME'] +=
                    $model->db->total_time;
            }
            $locale_info = L\getLocaleQueryStatistics();
            $data['QUERY_STATISTICS'] = array_merge(
                    $locale_info['QUERY_LOG'],
                    $data['QUERY_STATISTICS']
                    );
            $data['TOTAL_ELAPSED_TIME'] +=
                    $locale_info['TOTAL_ELAPSED_TIME'];
            $mail_total_time = AnalyticsManager::get("MAIL_TOTAL_TIME");
            $mail_messages = AnalyticsManager::get("MAIL_MESSAGES");
            if ($mail_total_time && $mail_messages) {
                $data['QUERY_STATISTICS'] = array_merge($mail_messages,
                    $data['QUERY_STATISTICS']
                    );
                $data['TOTAL_ELAPSED_TIME'] += $mail_total_time;
            }
        }
        $data['c'] = isset($_REQUEST['c']) ? $_REQUEST['c'] : null;
        if (isset($_SESSION['DISPLAY_MESSAGE'])) {
            $data['DISPLAY_MESSAGE'] = $_SESSION['DISPLAY_MESSAGE'];
            unset($_SESSION['DISPLAY_MESSAGE']);
        }
        $this->view($view)->render($data);
    }
    /**
     * Does a 301 redirect to the given location, sets a session variable
     * to display a message when get there.
     *
     * @param string $message message to write
     * @param string $copy_fields $_REQUEST fields to copy for redirect
     * @param bool $restart if yioop is being run as its own server rather than
     *      under apache whether to restart this server.
     * @param bool $use_base_url sould the base_url be included in the redirect
     */
    public function redirectWithMessage($message, $copy_fields = false,
        $restart = false, $use_base_url = false)
    {
        $default_fields = ["c", "a", C\CSRF_TOKEN, "just_thread",
            "just_group_id", "just_user_id", "group_id", "user_id", "role_id",
            "limit", "num", "v"];
        if ($copy_fields) {
            $copy_fields = array_merge($default_fields, $copy_fields);
        } else {
            $copy_fields = $default_fields;
        }
        $query_array = [];
        foreach ($copy_fields as $field) {
            if (isset($_REQUEST[$field])) {
                if (is_array($_REQUEST[$field])) {
                    $array_params_cleaned = $_REQUEST[$field];
                    foreach ($array_params_cleaned as $key => $value) {
                        $query_array[$field][$this->clean($key, "string")] =
                            $this->clean($value, "string") ;
                    }
                } else {
                    $query_array[$field] = $this->clean($_REQUEST[$field],
                        "string");
                }
            }
        }
        if (isset($_REQUEST['route'])) {
            foreach ($query_array as $field => $value) {
                if (!empty($_REQUEST['route'][$field])) {
                    unset($query_array[$field]);
                }
            }
        }
        $query_array = array_filter($query_array);
        $url = ($use_base_url) ? C\BASE_URL : "";
        $location = ($query_array == []) ? C\BASE_URL :
            $url . "?" . http_build_query($query_array);
        if ($message) {
            $data['MESSAGE'] = $message;
            $_SESSION['DISPLAY_MESSAGE'] = $message;
        } else {
            unset($_SESSION['DISPLAY_MESSAGE']);
        }
        if (empty($this->web_site) && php_sapi_name() == 'cli') {
            //this case happens for configure_tool.php
            return $data;
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") {
            e("go$location");
        } else {
            if (!empty($this->web_site)) {
                if ($restart && C\nsdefined("IS_OWN_WEB_SERVER")) {
                    $this->web_site->header("Refresh:4; url=$location");
                    $this->web_site->header("Connection:close");
                    e(tl("controller_restart_server"));
                    L\webExit("restart " . C\WORK_DIRECTORY .
                        "/data/previous_session.txt");
                } else {
                    $this->web_site->header("Location:$location");
                }
            }
        }
        $_FILES = []; // this is for when Yioop run as own web server
        L\webExit();
    }
    /**
     *  Method to perform a 301 redirect to $location in both under web server
     *  and CLI setting
     *
     * @param string $location url to redirect to
     */
    public function redirectLocation($location)
    {
        $this->web_site->header("Location:$location");
        $_FILES = []; // this is for when Yioop run as own web server
        L\webExit();
    }
    /**
     * When an activity involves displaying tabular data (such as rows of
     * users, groups, etc), this method might be called to set up $data
     * fields for next, prev, and page links, it also makes the call to the
     * model to get the row data sorted and restricted as desired. For some
     * data sources, rather than directly make a call to the model to get the
     * data it might be passed directly to this method.
     *
     * @param array &$data used to send data to the view will be updated by
     *     this method with row and paging data
     * @param mixed $field_or_model if an object, this is assumed to be a model
     *     and so the getRows method of this model is called to get row data,
     *     sorted and restricted according to $search_array; if a string
     *     then the row data is assumed to be in $data[$field_or_model] and
     *     pagingLogic itself does the sorting and restricting.
     * @param string $output_field output rows for the view will be stored in
     *     $data[$output_field]
     * @param int $default_show if not specified by $_REQUEST, then this will
     *     be used to determine the maximum number of rows that will be
     *     written to $data[$output_field]
     * @param array $search_array used to sort and restrict in
     *     the getRows call or the data from $data[$field_or_model].
     *     Each element of this is a quadruple name of a field, what comparison
     *     to perform, a value to check, and an order (ascending/descending)
     *     to sort by
     * @param string $var_prefix if there are multiple uses of pagingLogic
     *     presented on the same view then $var_prefix can be prepended to
     *     to the $data field variables like num_show, start_row, end_row
     *     to distinguish between them
     * @param array $args additional arguments that are passed to getRows and
     *     in turn to selectCallback, fromCallback, and whereCallback that
     *     might provide user_id, etc to further control which rows are
     *     returned
     */
     public function pagingLogic(&$data, $field_or_model, $output_field,
        $default_show, $search_array = [], $var_prefix = "", $args = null)
     {
        $data_fields = [];
        $r = [];
        $request_fields = ['num_show' => C\DEFAULT_ADMIN_PAGING_NUM,
            'start_row' => 0, 'end_row' => C\DEFAULT_ADMIN_PAGING_NUM];
        foreach ($request_fields as $field => $default) {
            if (isset($_REQUEST[$var_prefix . $field])) {
                $r[$field] = $_REQUEST[$var_prefix . $field];
            } else {
                $r[$field] = $default;
            }
        }
        if ($r['start_row'] + $r['num_show'] != $r['end_row']) {
            $r['end_row'] = $r['start_row'] + $r['num_show'];
        }
        $d = [];
        $data_fields = ['NUM_TOTAL', 'NUM_SHOW', 'START_ROW', 'END_ROW',
            'NEXT_START', 'NEXT_END', 'PREV_START', 'PREV_END'];
        $var_field = strtoupper($var_prefix);
        foreach ($data_fields as $field) {
            $d[$field] = $var_prefix . $field;
        }
        $num_show = (isset($r['num_show']) &&
            isset($this->view("admin")->helper("pagingtable")->show_choices[
                $r['num_show']])) ? $r['num_show'] : $default_show;
        $data[$d['NUM_SHOW']] = $num_show;
        $data[$d['START_ROW']] = isset($r['start_row']) ?
             max(0, $this->clean($r['start_row'],"int")) : 0;
        if (is_object($field_or_model)) {
            $data[$output_field] = $field_or_model->getRows(
                $data[$d['START_ROW']], $num_show, $num_rows, $search_array,
                $args);
        } else {
            $num_rows = count($data[$field_or_model]);
            if ($search_array != []) {
                $out_data = [];
                foreach ($data[$field_or_model] as $name => $field_data) {
                    $checks_passed = true;
                    foreach ($search_array as $search_data) {
                        list($column_name, $comparison, $search_value, $sort) =
                            $search_data;
                        if ($search_value == "") {
                            continue;
                        }
                        if (isset($args[$column_name])) {
                            $column_name = $args[$column_name];
                        }
                        $row_value = is_object($field_data) ?
                            $field_data->$column_name:
                            $field_data[$column_name];
                        $cmp = strcmp($search_value, $row_value);
                        if (($cmp == 0 && $comparison == "=") ||
                            ($cmp != 0 && $comparison == "!=")
                            ) {
                            continue;
                        }
                        $pos = strpos($row_value, $search_value);
                        $len_row = strlen($row_value);
                        $len_search = strlen($search_value);
                        if (($comparison == "CONTAINS" && $pos !== false) ||
                            ($comparison == "BEGINS WITH" && $pos === 0) ||
                            ($comparison == "ENDS WITH" && $pos === $len_row -
                            $len_search)) {
                            continue;
                        }
                        $checks_passed = false;
                        break;
                    }
                    if ($checks_passed) {
                        $out_data[$name] = $field_data;
                    }
                }
                foreach ($search_array as $search_data) {
                    list($column_name, $comparison, $search_value, $sort) =
                        $search_data;
                    if ($sort == "NONE") {
                        continue;
                    }
                    if (isset($args[$column_name])) {
                        $column_name = $args[$column_name];
                    }
                    $values = [];
                    foreach ($out_data as $name => $field_data) {
                        $values[$name] = is_object($field_data) ?
                            $field_data->$column_name:
                            $field_data[$column_name];
                    }
                    $sort = ($sort=="DESC") ? SORT_DESC: SORT_ASC;
                    array_multisort($values, $sort, $out_data);
                }
            } else {
                $out_data = $data[$field_or_model];
            }
            $data[$output_field] = array_slice($out_data,
                $data[$d['START_ROW']], $num_show);
        }
        $data[$d['START_ROW']] = min($data[$d['START_ROW']], $num_rows);
        $data[$d['END_ROW']] = min($data[$d['START_ROW']] + $num_show,
            $num_rows);
        if (isset($r['start_row'])) {
            $data[$d['END_ROW']] = max($data[$d['START_ROW']],
                    min($this->clean($r['end_row'],"int"), $num_rows));
        }
        $data[$d['NEXT_START']] = $data[$d['END_ROW']];
        $data[$d['NEXT_END']] = min($data[$d['NEXT_START']] + $num_show,
            $num_rows);
        $data[$d['PREV_START']] = max(0, $data[$d['START_ROW']] - $num_show);
        $data[$d['PREV_END']] = $data[$d['START_ROW']];
        $data[$d['NUM_TOTAL']] = $num_rows;
     }
    /**
     * Used to invoke an activity method of the current controller or one
     * its components
     *
     * @param $activity method to invoke
     */
     public function call($activity)
     {
        if (isset($this->activity_component[$activity])) {
            return $this->component(
                $this->activity_component[$activity])->$activity();
        }
        return $this->$activity();
     }
    /**
     * Generates a cross site request forgery preventing token based on the
     * provided user name, the current time and the hidden AUTH_KEY
     *
     * @param string $user   username to use to generate token
     * @return string   a csrf token
     */
    public function generateCSRFToken($user)
    {
        $time = time();
        $_SESSION['OLD_CSRF_TIME'] = (isset($_SESSION['CSRF_TIME'])) ?
            $_SESSION['CSRF_TIME'] : 0;
        $_SESSION['CSRF_TIME'] = $time;
        return L\crawlHash($user . $time . C\AUTH_KEY)."*$time";
    }
    /**
     * Checks if the form CSRF (cross-site request forgery preventing) token
     * matches the given user and has not expired (1 hour till expires)
     *
     * @param string $token_name attribute of $_REQUEST containing CSRFToken
     * @param string $user  user id
     * @return bool  whether the CSRF token was valid
     */
    public function checkCSRFToken($token_name, $user)
    {
        $token_okay = false;
        if (isset($_REQUEST[$token_name]) &&
            strlen($_REQUEST[$token_name]) == 22) {
            $token_parts = explode("*", $_REQUEST[$token_name]);
            if (isset($token_parts[1]) &&
                $token_parts[1] + C\ONE_HOUR > time() &&
                L\crawlHash($user . $token_parts[1] . C\AUTH_KEY) ==
                $token_parts[0]) {
                $token_okay = true;
            }
        }
        return $token_okay;
    }
    /**
     * Checks if the timestamp in $_REQUEST[$token_name]
     * matches the timestamp of the last CSRF token accessed by this user
     * for the kind of activity for which there might be a conflict.
     * This is to avoid accidental replays of postings etc if the back button
     * used.
     *
     * @param string $token_name name of a $_REQUEST field used to hold a
     *     CSRF_TOKEN
     * @param string $action name of current action to check for conflicts
     * @return bool whether a conflicting action has occurred.
     */
     public function checkCSRFTime($token_name, $action = "")
     {
        $token_okay = false;
        if (isset($_REQUEST[$token_name])) {
            $token_parts = explode("*", $_REQUEST[$token_name]);
            if (isset($token_parts[1])) {
                $timestamp_to_check = $token_parts[1];
                if ($action == "") {
                    if (isset($_SESSION['OLD_CSRF_TIME']) &&
                        $token_parts[1] == $_SESSION['OLD_CSRF_TIME']) {
                        $token_okay = true;
                    }
                } else {
                    if (!isset($_SESSION['OLD_ACTION_STAMPS'][$action]) ||
                        (isset($_SESSION['OLD_ACTION_STAMPS'][$action]) &&
                        $_SESSION['OLD_ACTION_STAMPS'][$action] <=
                            $timestamp_to_check)) {
                        $_SESSION['OLD_ACTION_STAMPS'][$action] =
                            $timestamp_to_check;
                        $token_okay = true;
                        $cull_time = time() - C\ONE_HOUR;
                        foreach ($_SESSION['OLD_ACTION_STAMPS'] as $act =>
                            $time) {
                            if ($time < $cull_time) {
                                unset($_SESSION['OLD_ACTION_STAMPS'][$act]);
                            }
                        }
                    }
                }
            }
        }
        return $token_okay;
    }
    /**
     * Used to return just the timestamp portion of the CSRF token
     *
     * @param string $token_name name of a $_REQUEST field used to hold a
     *     CSRF_TOKEN
     * @return int the timestamp portion of the CSRF_TOKEN
     */
    public function getCSRFTime($token_name)
    {
        $timestamp = 0;
        if (isset($_REQUEST[$token_name])) {
            $token_parts = explode("*", $_REQUEST[$token_name]);
            if (isset($token_parts[1])) {
                return intval($token_parts[1]);
            }
        }
        return $timestamp;
    }
    /**
     * Used to clean strings that might be tainted as originate from the user
     *
     * @param mixed $value tainted data
     * @param mixed $type type of data in value can be one of the following
     *      strings: bool, color, double, float, int, hash, or string, web-url;
     *      or it can be an array listing allowed values. If the latter, then
     *      if the value is not in the array the cleaned value will be first
     *      element of the array if $default is null
     * @param mixed $default if $value is not set default value is returned,
     *     this isn't used much since if the error_reporting is E_ALL
     *     or -1 you would still get a Notice.
     * @return string the clean input matching the type provided
     */
    public function clean($value, $type, $default = null)
    {
        $clean_value = null;
        if (is_array($type)) {
            if (in_array($value, $type)) {
                return $value;
            } else {
                if ($default != null) {
                    return $default;
                }
                reset($type);
                return current($type);
            }
        }
        switch ($type) {
            case "boolean":
                // no break
            case "bool":
                if (isset($value)) {
                    if (!is_bool($value)) {
                        $clean_value = false;
                        if ($value == "true" || $value != 0) {
                            $clean_value = true;
                        }
                    }
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = false;
                }
                break;
            case "color":
                if (isset($value)) {
                    $colors = ["black", "silver", "gray", "white",
                        "maroon", "red", "purple", "fuchsia", "green", "lime",
                        "olive", "yellow", "navy", "blue", "teal", "aqua",
                        "orange", "aliceblue", "antiquewhite", "aquamarine",
                        "azure", "beige", "bisque", "blanchedalmond",
                        "blueviolet", "brown", "burlywood", "cadetblue",
                        "chartreuse", "chocolate", "coral", "cornflowerblue",
                        "cornsilk", "crimson", "darkblue", "darkcyan",
                        "darkgoldenrod", "darkgray", "darkgreen", "darkgrey",
                        "darkkhaki", "darkmagenta", "darkolivegreen",
                        "darkorange", "darkorchid", "darkred", "darksalmon",
                        "darkseagreen", "darkslateblue", "darkslategray",
                        "darkslategrey", "darkturquoise", "darkviolet",
                        "deeppink", "deepskyblue", "dimgray", "dodgerblue",
                        "firebrick", "floralwhite", "forestgreen", "gainsboro",
                        "ghostwhite", "gold", "goldenrod", "greenyellow",
                        "grey", "honeydew", "hotpink", "indianred", "indigo",
                        "ivory", "khaki", "lavender", "lavenderblush",
                        "lawngreen", "lemonchiffon", "lightblue", "lightcoral",
                        "lightcyan", "lightgoldenrodyellow", "lightgray",
                        "lightgreen", "lightgrey", "lightpink", "lightsalmon",
                        "lightseagreen", "lightskyblue", "lightslategray",
                        "lightslategrey", "lightsteelblue", "lightyellow",
                        "limegreen", "linen", "mediumaquamarine",
                        "mediumblue", "mediumorchid", "mediumpurple",
                        "mediumseagreen", "mediumslateblue",
                        "mediumspringgreen", "mediumturquoise",
                        "mediumvioletred", "midnightblue", "mintcream",
                        "mistyrose", "moccasin", "navajowhite", "oldlace",
                        "olivedrab", "orangered", "orchid", "palegoldenrod",
                        "palegreen", "paleturquoise", "palevioletred",
                        "papayawhip", "peachpuff", "peru", "pink", "plum",
                        "powderblue", "rosybrown", "royalblue", "saddlebrown",
                        "salmon", "sandybrown", "seagreen", "seashell",
                        "sienna",  "skyblue", "slateblue", "slategray",
                        "slategrey", "snow", "springgreen", "steelblue",
                        "tan", "thistle", "tomato", "turquoise", "violet",
                        "wheat", "whitesmoke", "yellowgreen", "rebeccapurple"
                    ];
                    if (in_array($value, $colors)
                        || preg_match('/^#[a-fA-F0-9][a-fA-F0-9][a-fA-F0-9]'.
                        '([a-fA-F0-9][a-fA-F0-9][a-fA-F0-9])?$/',
                            trim($value))) {
                        $clean_value = trim($value);
                    } else {
                        $clean_value = "#FFF";
                    }
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = "#FFF";
                }
                break;
            case "double":
                if (isset($value)) {
                    $clean_value = doubleval($value);
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
                break;
            case "float":
                if (isset($value)) {
                    $clean_value = floatval($value);
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
                break;
            case "file_name":
                if (isset($value)) {
                    $clean_value = strtr($value, ["&amp;" => "&", "\\" => "/",
                        "*" => "-", ":" => "-", ".." => "-"]);
                } else {
                    $clean_value = $default;
                }
                break;
            case "hash";
                if (isset($value)) {
                    if (strlen($value) == strlen(L\crawlHash("A")) &&
                        base64_decode($value)) {
                        $clean_value = $value;
                    }
                } else {
                    $clean_value = $default;
                }
                break;
            case "int":
                if (isset($value)) {
                    $clean_value = intval($value);
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
                break;
            case "int32":
                if (isset($value)) {
                    $clean_value = intval($value);
                    if ($clean_value < -2147483648) {
                        $clean_value = -2147483648;
                    } else if ($clean_value > 2147483647) {
                        $clean_value = 2147483647;
                    }
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = 0;
                }
                break;
            case "string":
                if (isset($value)) {
                    $value2 = str_replace("&amp;", "&", $value);
                    // -CP REMEMBER TO CK THIS!!!!!
                    $value2 = mb_convert_encoding($value2, "UTF-8");
                    $clean_value = $value2 ;
                    $clean_value = @htmlspecialchars($value2, ENT_QUOTES,
                        "UTF-8");
                } else {
                    $clean_value = $default;
                }
                break;
            case "time":
                if (isset($value)) {
                    $value = trim($value);
                    $value_parts = explode(":", $value);
                    if (isset($value_parts[1])) {
                        $hour = intval($value_parts[0]);
                        $min = intval($value_parts[1]);
                        if ($hour >= 0 && $hour < 24 && $min >= 0 &&
                            $min < 60) {
                            $clean_value = $value;
                        } else {
                            $clean_value = "00:00";
                        }
                    } else {
                        $clean_value = "00:00";
                    }
                } else if ($default != null) {
                    $clean_value = $default;
                } else {
                    $clean_value = "00:00";
                }
                break;
            case 'web-url':
                if (isset($value)) {
                    $value = trim($value);
                    $start = substr($value, 0, 4);
                    $is_web = ($start == 'http');
                    if (!$is_web && $start != "goph") {
                        $value = "http://$value";
                        $is_web = true;
                    }
                    if ($is_web) {
                        $value = str_replace("&amp;", "&", $value);
                        $clean_value = @htmlentities($value, ENT_QUOTES,
                            "UTF-8");
                    }
                    $clean_value = UrlParser::canonicalLink($clean_value,
                        $default, false);
                } else {
                    $clean_value = $default;
                }
                break;
        }
        return $clean_value;
    }
    /**
     * Converts an array of lines of strings into a single string with
     * proper newlines, each line having been trimmed and potentially
     * cleaned
     *
     * @param array $arr the array of lines to be process
     * @param string $endline_string what string should be used to indicate
     *     the end of a line
     * @param bool $clean whether to clean each line
     * @return string a concatenated string of cleaned lines
     */
    public function convertArrayLines($arr, $endline_string="\n",
        $clean = false)
    {
        $output = "";
        $eol = "";
        foreach ($arr as $line) {
            $output .= $eol;
            $out_line = trim($line);
            if ($clean) {
                $out_line = $this->clean($out_line, "string");
            }
            $output .= trim($out_line);
            $eol = $endline_string;
        }
        return $output;
    }
    /**
     * Cleans a string consisting of lines, typically of urls into an array of
     * clean lines. This is used in handling data from the crawl options
     * text areas. # is treated as a comment
     *
     * @param string $str contains the url data
     * @param string $line_type does additional cleaning depending on the type
     *     of the lines. For instance, if is "url" then a line not beginning
     *     with a url scheme will have http:// prepended.
     * @return array $lines an array of clean lines
     */
    public function convertStringCleanArray($str, $line_type="url")
    {
        $pre_lines = preg_split('/\n+/', $str);
        $lines = [];
        foreach ($pre_lines as $line) {
            $pre_line = trim($this->clean($line, "string"));
            if (strlen($pre_line) > 0) {
                if ($line_type == "url") {
                    $start_line = substr($pre_line, 0, 6);
                    if (!in_array($start_line,
                        ["file:/", "http:/", "domain", "https:", 'gopher',
                        "regex:"]) &&
                        $start_line[0] != "#") {
                        $pre_line = "https://". $pre_line;
                    }
                }
                $lines[] = $pre_line;
            }
        }
        return $lines;
    }
    /**
     * Checks the request if a request is for a valid activity and if it uses
     * the correct authorization key
     *
     * @return bool whether the request was valid or not
     */
    public function checkRequest()
    {
        if (empty($_REQUEST['time']) ||
            empty($_REQUEST['session']) || (
            !in_array($_REQUEST['a'], $this->activities)
            && $_REQUEST['c'] != 'jobs' )) {
            return false;
        }
        $time = $_REQUEST['time'];
            // request must be within the autologout of this machine's clock
        if (abs(time() - $time) > intval(C\AUTOLOGOUT)) {
            return false;
        }
        $session = $_REQUEST['session'];
        if (md5($time . C\AUTH_KEY) != $session) {
            return false;
        }
        return true;
    }
    /**
     * Used to set up the head variables for and page_data of a wiki or static
     * page associated with a view.
     *
     * @param object $view View on which page data will be rendered
     * @param string $page_name a string name/id to associate with page. For
     *     example, might have 404 for a page about 404 errors
     * @param string $page_data this is the actual content of a wiki or
     *      static page
     */
    public function parsePageHeadVarsView($view, $page_name, $page_data)
    {
        list($view->head_objects[$page_name], $view->page_objects[$page_name])=
            $this->parsePageHeadVars($page_data, true);
    }
    /**
     * Used to parse head meta variables out of a data string provided either
     * from a wiki page or a static page. Meta data is stored in lines
     * before the first occurrence of END_HEAD_VARS. Head variables
     * are name=value pairs. An example of head
     * variable might be:
     * title = This web page's title
     * Anything after a semi-colon on a line in the head section is treated as
     * a comment
     *
     * @param string $page_data this is the actual content of a wiki or
     *      static page
     * @param bool whether to output just an array of head variables or
     *      if output a pair [head vars, page body]
     * @return array the associative array of head variables or pair
     *      [head vars, page body]
     */
    public function parsePageHeadVars($page_data, $with_body = false)
    {
        $page_parts = explode("END_HEAD_VARS", $page_data);
        $head_object = [];
        if (count($page_parts) > 1) {
            $head_lines = preg_split("/\n\n/", array_shift($page_parts));
            $page_data = implode("END_HEAD_VARS", $page_parts);
            foreach ($head_lines as $line) {
                $semi_pos =  (strpos($line, ";")) ? strpos($line, ";"):
                    strlen($line);
                $line = substr($line, 0, $semi_pos);
                $line_parts = explode("=", $line);
                if (count($line_parts) == 2) {
                    $key = trim(urldecode($line_parts[0]));
                    $value = urldecode(trim($line_parts[1]));
                    if ($key == 'page_alias') {
                        $value = str_replace(" ", "_", $value);
                    }
                    $head_object[$key] = $value;
                }
            }
        } else {
            $page_data = $page_parts[0];
        }
        if ($with_body) {
            return [$head_object, $page_data];
        }
        return $head_object;
    }
    /**
     * If external source advertisements are present in the output of this
     * controller this function can be used to initialize the field variables
     * used to write the appropriate Javascripts
     *
     * @param array &$data data to be used in drawing the view
     * @param bool $ads_off whether or not ads are turned off so that this
     *      method should do nothing
     */
     public function initializeAdFields(&$data, $ads_off = false)
     {
        if (C\AD_LOCATION != "none") {
            $data["AD_LOCATION"] = ($ads_off) ? "none" : C\AD_LOCATION;
            $ad_fields = ['TOP_ADSCRIPT', 'SIDE_ADSCRIPT', 'GLOBAL_ADSCRIPT'];
            foreach ($ad_fields as $ad_field) {
                $ad = html_entity_decode(constant(C\NS_CONFIGS . $ad_field),
                    ENT_QUOTES);
                $ad = preg_replace("[&#40;]","(",$ad);
                $data[$ad_field] = preg_replace("[&#41;]",")",$ad);
            }
        }
     }
    /**
     * Adds to an integer, $actual_value, epsilon-noise taken from an
     * L_1 gaussian source to centered at $actual_value to get a epsilon
     * private, integer value.
     *
     * @param int $actual_value number want to make private
     * @return int $fuzzy_value number after noise added
     */
    public function addDifferentialPrivacy($actual_value)
    {
        $sigma = 1 / C\PRIVACY_EPSILON;
        $max_value = (2 * $actual_value) + 1;
        /* Calculation by Integration
         * f: exp(-1 * ((abs($actual_value - $x)) / $sigma))
         * Since function consists of absolute value, break the integration
         * into two to remove absolute from the function.
         * First integral runs from 0 through $actual_value, and second
         * integral runs from $actual_value through $max_value.
         * Then after using substition rule, first and second integral
         * range change, say [a,b] and [b,c]
         */
        $a = -1 * $actual_value;
        $b = 0;
        $c = -1 * ($max_value - $actual_value);
        $integral_value = $sigma * (2 - exp($a / $sigma) - exp($c / $sigma));
        $random = rand(0, $integral_value);
        $p = 0;
        for ($i = 0; $i < $max_value; $i++) {
            $arg = -1 * (($actual_value - $i) / $sigma);
            $p +=  exp($arg);
            if ($p > $random) {
                break;
            }
        }
        $fuzzy_value = $i;
        return $fuzzy_value;
    }
    /**
     * Used to store in a session which media list items have been viewed so
     * we can put an indicator by them when the media list is rendered
     *
     * @param int $page_id the id of page with media list
     * @param string $sub_path the resource folder on that page
     * @param string $media_name item to store indiicator into session for
     */
    public function recordViewSession($page_id, $sub_path, $media_name)
    {
        $hash_id = L\crawlHash($page_id . $media_name . $sub_path);
        if (empty($_SESSION['USER_ID'])||
            !L\isPositiveInteger($_SESSION['USER_ID']) ||
            $_SESSION['USER_ID'] == C\PUBLIC_USER_ID || !empty($_REQUEST['t'])){
            return;
        }
        if (empty($_SESSION['seen_media']) ||
            !is_array($_SESSION['seen_media'])) {
            $_SESSION['seen_media'] = [];
        }
        if (in_array($hash_id, $_SESSION['seen_media'])) {
            if (end($_SESSION['seen_media']) == $hash_id) {
                // this item was already the most recently viewed
                return;
            }
            $_SESSION['seen_media'] = array_diff($_SESSION['seen_media'],
                [$hash_id]);
        }
        array_push($_SESSION['seen_media'], $hash_id);
        $keep_deleting = true;
        while ($keep_deleting) {
            if (strlen(serialize($_SESSION)) >= C\MAX_USER_SESSION_SIZE) {
                array_shift($_SESSION['seen_media']);
            } else {
                $keep_deleting = false;
            }
            if (count($_SESSION['seen_media']) <= 0) {
                $keep_deleting = false;
            }
        }
        $this->model("user")->setUserSession($_SESSION['USER_ID'], $_SESSION);
    }
}
