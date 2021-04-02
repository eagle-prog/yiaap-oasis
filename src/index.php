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
 * Main web interface entry point for Yioop!
 * search site. Used to both get and display
 * search results. Also used for inter-machine
 * communication during crawling
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Main entry point to the Yioop web app.
 *
 * Initialization is done in  a function to avoid polluting the global
 * namespace with variables.
 * @param object $web_site
 * @param bool $start_new_session whether to start a session or not
 */
function bootstrap($web_site = null, $start_new_session = true)
{
    //check if mobile css and formatting should be used or not
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        if ((stristr($agent, "mobile") || stristr($agent, "fennec")) &&
            !stristr($agent, "ipad") ) {
            $_SERVER["MOBILE"] = true;
        } else {
            $_SERVER["MOBILE"] = false;
        }
    } else {
        $_SERVER["MOBILE"] = false;
    }
    /**
     * Did we come to this index.php from ../index.php? If so, rewriting
     * must be on
     */
    if (!C\nsdefined("REDIRECTS_ON")) {
        C\nsdefine("REDIRECTS_ON", false);
    }
    /**
     * Check if doing url rewriting, and if so, do initial routing
     */
    configureRewrites($web_site);
    if ((C\DEBUG_LEVEL & C\ERROR_INFO) == C\ERROR_INFO) {
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    }
    /**
     * Load global functions related to localization
     */
    require_once __DIR__ . "/library/LocaleFunctions.php";
    ini_set("memory_limit", C\INDEX_FILE_MEMORY_LIMIT);
    if (!empty($web_site)) {
        if ((empty($_REQUEST['c']) || $_REQUEST['c'] != 'resource')) {
            $web_site->header(
                "Content-Security-Policy: frame-ancestors 'self'");
                //prevent click-jacking
        }
        $web_site->header("X-Content-Type-Options: nosniff"); /*
        Let browsers know that we should be setting the mimetype correctly --
        For non dumb browsers this should help prevent against XSS attacks
        to images containing HTML. Also, might help against PRSSI attacks.
        */
        if ($start_new_session) {
            if (checkCookieConsent()) {
                $options = ['name' => C\SESSION_NAME,
                    'cookie_lifetime' => C\COOKIE_LIFETIME];
                if (C\nsdefined("SECURE_COOKIE") && C\SECURE_COOKIE) {
                    $options["cookie_secure"] = true;
                }
                if (intval(C\COOKIE_LIFETIME) <= 0) {
                    $options['cookie_lifetime'] = C\AUTOLOGOUT;
                }
                if (isset($_REQUEST['cookieconsent'])) {
                    if (empty($_REQUEST['cookieconsent']) ||
                        $_REQUEST['cookieconsent'] == "false") {
                        //remove cookie from browser if consent revoked
                        $options["cookie_lifetime"] = - C\ONE_HOUR;
                    }
                }
                $web_site->sessionStart($options);
                if (!empty($_GET['cookieconsent'])) {
                    $uri = preg_replace("/cookieconsent\=[a-zA-Z]+(\&+)?/", "",
                        $_SERVER['REQUEST_URI']);
                    if (strlen($uri) > 1 && substr($uri, -1) == "?") {
                        $uri = substr($uri, 0, -1);
                    }
                    $web_site->header("HTTP/1.0 301 Moved Permanently");
                    $web_site->header("Location:" . $uri);
                    L\webExit();
                }
            }
        }
    }
    /**
     * Load global functions related to checking Yioop! version
     */
    require_once C\BASE_DIR . "/library/UpgradeFunctions.php";
    if (!function_exists('mb_internal_encoding')) {
        echo "PHP Zend Multibyte Support must be enabled for Yioop! to run.";
        exit();
    }
    /**
     * Make an initial setting of controllers. This can be overridden in
     * configs/LocalConfig.php
     */
    $available_controllers = ["admin", "api", "archive",  "cache",
        "classifier", "crawl", "fetch", "group", "jobs", "machine", "resource",
        "search", "static"];
    if (C\DISPLAY_TESTS) {
        $available_controllers[] = "tests";
    }
    if (function_exists(C\NS_CONFIGS . "localControllers")) {
        $available_controllers = array_merge($available_controllers,
            C\localControllers());
    }
    if (in_array(C\REGISTRATION_TYPE, ['no_activation', 'email_registration',
        'admin_activation'])) {
        $available_controllers[] = "register";
    }
    if (!C\WEB_ACCESS) {
        $available_controllers = ["admin", "archive", "cache", "crawl",
            "fetch", "jobs", "machine"];
    }
    //the request variable c is used to determine the controller
    if (!isset($_REQUEST['c'])) {
        $controller_name = "search";
        if (C\nsdefined('LANDING_PAGE') && C\LANDING_PAGE &&
            !isset($_REQUEST['q'])) {
            $controller_name = "static";
            $_REQUEST['c'] = "static";
            $_REQUEST['p'] = "Main";
        }
    } else {
        $controller_name = $_REQUEST['c'];
    }
    if (!in_array($controller_name, $available_controllers))
    {
        $controller_name = "static";
        $_REQUEST['c'] = "static";
        $_REQUEST['p'] = "404";
    }
    // if no profile exists we force the page to be the configuration page
    if (!C\PROFILE || (C\nsdefined("FIX_NAME_SERVER") && C\FIX_NAME_SERVER)) {
        $controller_name = "admin";
    }
    $locale_tag = L\getLocaleTag();
    if (C\PROFILE && L\upgradeDatabaseWorkDirectoryCheck()) {
        /**
         * Load global functions needed to upgrade between versions
         * (note only do this if need to upgrade)
         */
        require_once C\BASE_DIR . "/library/VersionFunctions.php";
        L\upgradeDatabaseWorkDirectory();
    }
    if (C\PROFILE && L\upgradeLocalesCheck($locale_tag)) {
        L\upgradeLocales();
        /* upgrade manipulations might mess with global local,
            so set it back here
         */
        L\setLocaleObject($locale_tag);
    }
    /**
     * Loads controller responsible for calculating
     * the data needed to render the scene
     *
     */
    $controller_class = C\NS_CONTROLLERS . ucfirst($controller_name) .
        "Controller";
    $controller = new $controller_class($web_site);
    $controller->processRequest();
}
/**
 * Checks if a cookie consent form was obtained. This
 * This function returns true if a session cookie
 * was received from the browser, or a form variable
 * saying cookies are okay was received, or the cookie
 * Yioop profile says the consent mechanism is disabled
 *
 * @return bool cookie consent (true) else false
 */
function checkCookieConsent()
{
    if (C\PROFILE && intval(C\COOKIE_LIFETIME) > 0 &&
        empty($_COOKIE[C\SESSION_NAME])
        && empty($_REQUEST['cookieconsent'])) {
        $_SERVER["COOKIE_CONSENT"] = false;
        return false;
    }
    return true;
}
/**
 * Used to setup and handles url rewriting for the Yioop Web app
 *
 * Developers can add new routes by creating a Routes class in
 * the app_dir with a static method getRoutes which should return
 * an associating array of incoming_path => handler function
 * @param object $web_site used to send error pages if configuration
 *  fails
 */
function configureRewrites($web_site)
{
    $route_map = [
        'wd' => 'routeAppFile',
        'favicon.ico' => 'routeBaseFile',
        'robots.txt' => 'routeBaseFile',
        'yioopbar.xml' => 'routeBaseFile',
        'css' => 'routeBaseFile',
        'locale' => 'routeBaseFile',
        'resources' => 'routeBaseFile',
        'scripts' => 'routeBaseFile',
        'blog' => 'routeBlog',
        'admin' => 'routeController',
        'register' => 'routeController',
        'tests' => 'routeController',
        'advertise' => 'routeDirect',
        'bot' => 'routeDirect',
        'privacy' => 'routeDirect',
        'terms' => 'routeDirect',
        'group' => 'routeFeeds',
        'thread' => 'routeFeeds',
        'user' => 'routeFeeds',
        's' => "routeSubsearch",
        'suggest' => 'routeSuggest',
        'p' => 'routeWiki'
    ];
    if (class_exists(C\NS. "Routes")) {
        $route_map = array_merge($route_map, Routes::getRoutes());
    }
    /**
     * Check for paths of the form index.php/something which yioop doesn't
     * support
     */
    $s_name = $_SERVER['SCRIPT_NAME'] . "/";
    $path_name = substr($_SERVER["REQUEST_URI"], 0, strlen($s_name));
    if (strcmp($path_name, $s_name) == 0) {
        $_SERVER["PATH_TRANSLATED"] = C\BASE_DIR;
        $script_info = pathinfo($s_name);
        $_SERVER["PATH_INFO"] = ($script_info["dirname"] == "/") ? "" :
            $script_info["dirname"] ;
        $error = directUrl("error", false, true);
        $web_site->header("Location: $error");
        L\webExit();
    }
    if (!isset($_SERVER["PATH_INFO"])) {
        $_SERVER["PATH_INFO"] = ".";
    }
    if (!C\REDIRECTS_ON) {
        return;
    }
    /**
     * Now look for and handle routes
     */
    $index_php = "index.php";
    if ((php_sapi_name() == 'cli')) {
        $script_path = "/";
    } else {
        $script_path = substr($_SERVER['PHP_SELF'], 0, -strlen($index_php));
    }
    $request_script = "";
    if (empty($_SERVER['QUERY_STRING'])) {
        $request_script = rtrim(
            substr($_SERVER['REQUEST_URI'], strlen($script_path)), "?");
    } else {
        $q_pos = strpos($_SERVER['REQUEST_URI'], "?");
        if ($q_pos !== false) {
            $request_script = substr($_SERVER['REQUEST_URI'], 0,
                $q_pos);
        }
        $request_script = substr($request_script, strlen($script_path));
    }
    $request_script = ($request_script == "") ? $index_php : $request_script;
    if (in_array($request_script, ['', '/', $index_php])) {
        return;
    }
    $request_parts = explode("/", $request_script);
    $handled = false;
    if (isset($route_map[$request_parts[0]])) {
        if (empty($_REQUEST['c']) || $_REQUEST['c'] == $request_parts[0]) {
            $route = C\NS . $route_map[$request_parts[0]];
            $handled = $route($request_parts);
        } else if (!empty($_REQUEST['c'])) {
            $handled = true;
        }
    }
    if (!$handled) {
        $error = directUrl("error", false, true);
        $error_location = "Location:" . $error;
        if (!C\REDIRECTS_ON) {
            $error_location .= ".php";
        }
        if ($request_parts[0] != "error" && $request_parts[0] != "error.php") {
            $web_site->header($error_location);
            return;
        }
        $web_site->header("HTTP/1.0 404 Not Found");
        $route_args = ["404"];
        routeDirect($route_args);
    }
}
/**
 * Used to handle routes that will eventually just serve
 * files from either the APP_DIR
 * These include files like css, scripts, suggest tries, images, and videos.
 * @param array $route_args of url parts (split on slash)
 * @return bool whether was able to compute a route or not
 */
function routeAppFile($route_args)
{
    if (empty($route_args[2])) {
        return false;
    }
    $num_args = count($route_args);
    $_REQUEST['route']['c'] = true;
    $_REQUEST['c'] = "resource";
    $_REQUEST['route']['a'] = true;
    $_REQUEST['a'] = "get";
    if ($route_args[1] == 'suggest') {
        $_REQUEST['a'] = "suggest";
        $_REQUEST['route']['locale'] = true;
        $_REQUEST['locale'] = $route_args[2];
        return true;
    } else if ($route_args[1] == 'users') {
        $_REQUEST['route']['f'] = true;
        $_REQUEST['f'] = "resources";
        $_REQUEST['route']['s'] = true;
        $_REQUEST['s'] = $route_args[2];
        $_REQUEST['n'] = 'user_icon.jpg';
        $_REQUEST['route']['n'] = true;
        return true;
    } else if(in_array($route_args[1], ['css', 'scripts', 'locale'])) {
        $_REQUEST['route']['f'] = true;
        $_REQUEST['f'] = $route_args[1];
        $_REQUEST['route']['n'] = true;
        $rest_args = implode("/", array_slice($route_args, 2));
        $_REQUEST['n'] = $rest_args;
        return true;
    } else if (in_array($route_args[1], ["athumbs", "resources", "thumbs"])) {
        $_REQUEST['route']['f'] = true;
        $_REQUEST['f'] = "resources";
        if (in_array($route_args[1], ["thumbs", "athumbs"])) {
            if ($num_args < 6) {
                return false;
            }
            $_REQUEST['route']['t'] = true;
            $_REQUEST['t'] = rtrim($route_args[1], "s");
        }
        if ($num_args == 3) {
            $_REQUEST['route']['n'] = true;
            $_REQUEST['n'] = $route_args[2];
            return true;
        } else if ($num_args >= 6) {
            $token_parts = explode("=", $route_args[2]);
            if (count($token_parts) != 2 && $route_args[2] != "-") {
                return false;
            }
            $_REQUEST['route'][$token_parts[0]] = true;
            if (count($token_parts) == 2) {
                $_REQUEST[$token_parts[0]] = $token_parts[1];
            }
            $_REQUEST['route']['g'] = true;
            $_REQUEST['g'] = $route_args[3];
            $_REQUEST['route']['p'] = true;
            $_REQUEST['p'] = $route_args[4];
            if ($num_args == 6) {
                $_REQUEST['route']['n'] = true;
                $_REQUEST['n'] = $route_args[5];
                return true;
            }
            if ($num_args >= 7) {
                $_REQUEST['route']['n'] = true;
                $_REQUEST['n'] = array_pop($route_args);
                $path = implode("/", array_slice($route_args, 5));
                $_REQUEST['route']['sf'] = true;
                $_REQUEST['sf'] = urldecode(urldecode($path));
                return true;
            }
        }
    }
    return false;
}
/**
 * Used to handle routes that will eventually just serve
 * files from either the BASE_DIR
 * These include files like css, scripts, images, and robots.txt.
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeBaseFile($route_args)
{
    $_REQUEST['route']['c'] = true;
    $_REQUEST['c'] = "resource";
    $_REQUEST['route']['a'] = true;
    $_REQUEST['a'] = "get";
    $_REQUEST['route']['b'] = true;
    $_REQUEST['b'] = $route_args[0];
    if (count($route_args) == 1) {
        $_REQUEST['route']['n'] = true;
        $_REQUEST['n'] = '-';
    } else {
        $_REQUEST['route']['n'] = true;
        array_shift($route_args);
        $_REQUEST['n'] = implode("/", $route_args);
    }
    return true;
}
/**
 * Used to route page requests to pages that are fixed Public Group wiki
 * that should always be present. For example, 404 page.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeDirect($route_args)
{
    $_REQUEST['route']['c'] = true;
    $_REQUEST['c'] = "static";
    $_REQUEST['route']['p'] = true;
    $_REQUEST['p'] = $route_args[0];
    return true;
}
/**
 * Given the name of a fixed public group static page creates the url
 * where it can be accessed in this instance of Yioop, making use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $name of static page
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @param bool $with_base_url whether to use SHORT_BASE_URL or BASE_URL (true).
 * @return string url for the page in question
 */
function directUrl($name, $with_delim = false, $with_base_url = false)
{
    $base_url = ($with_base_url) ? C\BASE_URL : C\SHORT_BASE_URL;
    if (C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        return $base_url . $name . $delim;
    } else {
        $delim = ($with_delim) ? "&" : "";
        return "$base_url$name.php$delim";
    }
}
/**
 * Used to route page requests to for the website's public blog
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeBlog($route_args)
{
    $_REQUEST['route']['c'] = true;
    $_REQUEST['c'] = "group";
    $_REQUEST['route']['a'] = true;
    $_REQUEST['a'] = "groupFeeds";
    $_REQUEST['route']['just_group_id'] = true;
    $_REQUEST['just_group_id'] = 2;
    return true;
}
/**
 * Used to route page requests for pages corresponding to a group, user,
 * or thread feed. If redirects on then urls ending with /feed_type/id map
 * to a page for the id'th item of that feed_type
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeFeeds($route_args)
{
    $handled = true;
    if (isset($route_args[1]) && $route_args[1] == intval($route_args[1])) {
        $_REQUEST['c'] = "group";
        if (!empty($route_args[2])) {
            $_REQUEST['a'] = 'wiki';
            if ($route_args[2] == 'pages') {
                $_REQUEST['arg'] = 'pages';
                $_REQUEST['route']['arg'] = true;
            } else {
                if (empty($_REQUEST['page_name'])) {
                    $_REQUEST['page_name'] = $route_args[2];
                    $_REQUEST['route']['page_name'] = true;
                }
                if (empty($_REQUEST['sf']) && !empty($route_args[3]) ) {
                    $rest = array_slice($route_args, 3);
                    $_REQUEST['sf'] = implode("/", $rest);
                    $_REQUEST['route']['sf'] = true;
                }
            }
        }
        $_REQUEST['a'] = (isset($_REQUEST['a']) &&
            $_REQUEST['a'] == 'wiki') ? 'wiki' : "groupFeeds";
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
        $end = ($route_args[0] == 'thread') ? "" : "_id";
        if ($_REQUEST['a'] == 'wiki') {
            $_REQUEST['group_id'] = $route_args[1];
            $_REQUEST['route']['group_id'] = true;
        } else {
            $just_id = "just_" . $route_args[0] . $end;
            $_REQUEST[$just_id] = $route_args[1];
            $_REQUEST['route'][$just_id] = true;
        }
    } else if (!isset($route_args[1])) {
        $_REQUEST['c'] = "group";
        $_REQUEST['a'] = (isset($_REQUEST['a']) &&
            $_REQUEST['a'] == 'wiki') ? $_REQUEST['a'] : "groupFeeds";
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the type of feed, the identifier of the feed instance, and which
 * controller is being used creates the url where that feed item can be
 * accessed from the instance of Yioop. It makes use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $type of feed: group, user, thread
 * @param int $id the identifier for that feed.
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @param string $controller which controller is being used to access the
 *      feed: usually admin or group
 * @param bool $use_short_base_url whether to create the url as a relative
 *   url using C\SHORT_BASE_URL or as a full url using  C\BASE_URL
 *   (the latter is useful for mail notifications)
 * @return string url for the page in question
 */
function feedsUrl($type, $id, $with_delim = false, $controller = "group",
    $use_short_base_url = true)
{
    $base_url = ($use_short_base_url) ? C\SHORT_BASE_URL : C\BASE_URL;
    if (C\REDIRECTS_ON && $controller == 'group') {
        $delim = ($with_delim) ? "?" : "";
        $path = ($type == "") ? "group" : "$type/$id";
        return "$base_url$path$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        $begin = (C\REDIRECTS_ON && $controller == "admin") ?
            "admin?" : "?c=$controller&";
        $query = "{$begin}a=groupFeeds";
        $end = ($type == 'thread') ? "" : "_id";
        if ($type != "") {
            if ($begin == "admin?" && $type == "group") {
                $query = "admin/$id";
                $delim = "?";
            } else {
                $query .= "&just_{$type}$end=$id";
            }
        }
        return "$base_url$query$delim";
    }
}
/**
 * Used to route page requests to end-user controllers such as
 * register, admin. urls ending with /controller_name will
 * be routed to that controller.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeController($route_args)
{
    $_REQUEST['c'] = $route_args[0];
    $_REQUEST['route']['c'] = true;
    if (isset($route_args[1]) && intval($route_args[1]) == $route_args[1]) {
        if (isset($_REQUEST['a']) && $_REQUEST['a'] == 'wiki') {
            $_REQUEST['group_id'] = $route_args[1];
        } else if (!empty($route_args[2])) {
            $_REQUEST['a'] = 'wiki';
            $_REQUEST['group_id'] = $route_args[1];
            if ($route_args[2] == 'pages') {
                $_REQUEST['arg'] = 'pages';
                $_REQUEST['route']['arg'] = true;
            } else {
                $_REQUEST['page_name'] = $route_args[2];
                if (empty($_REQUEST['sf']) && !empty($route_args[3]) ) {
                    $rest = array_slice($route_args, 3);
                    $_REQUEST['sf'] = implode("/", $rest);
                    $_REQUEST['route']['sf'] = true;
                }
                $_REQUEST['route']['page_name'] = true;
            }
            $_REQUEST['route']['page_name'] = true;
            $_REQUEST['route']['a'] = true;
        } else {
            $_REQUEST['a'] = 'groupFeeds';
            $_REQUEST['just_group_id'] = $route_args[1];
        }
        $_REQUEST['route']['group_id'] = true;
    }
    return true;
}
/**
 * Given the name of a controller for which an easy end-user link is useful
 * creates the url where it can be accessed on this instance of Yioop,
 * making use of the defined variable REDIRECTS_ON. Examples of end-user
 * controllers would be the admin, and register controllers.
 *
 * @param string $name of controller
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function controllerUrl($name, $with_delim = false)
{
    $base_url = C\SHORT_BASE_URL;
    if (C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        $_REQUEST['route']['c'] = true;
        return $base_url . $name . $delim;
    } else {
        $delim = ($with_delim) ? "&" : "";
        return $base_url . "?c=$name$delim";
    }
}
/**
 * Used to route page requests for subsearches such as news, video, and images
 * (site owner can define other). Urls of the form /s/subsearch will
 * go the page handling the subsearch.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeSubsearch($route_args)
{
    $handled = true;
    if (isset($route_args[1])) {
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['s'] = true;
        $_REQUEST['c'] = "search";
        $_REQUEST['s'] = $route_args[1];
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the name of a subsearch  creates the url where it can be accessed
 * on this instance of Yioop, making use of the defined variable REDIRECTS_ON.
 * Examples of subsearches include news, video, and images. A site owner
 * can add to these and delete from these.
 *
 * @param string $name of subsearch
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function subsearchUrl($name, $with_delim = false)
{
    $base_url = C\SHORT_BASE_URL;
    if (C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        return $base_url ."s/$name$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        return "$base_url?s=$name$delim";
    }
}
/**
 * Used to route requests for the suggest-a-url link on the tools page.
 * If redirects on, then /suggest routes to this suggest-a-url page.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeSuggest($route_args)
{
    $_REQUEST['c'] = "register";
    $_REQUEST['a'] = "suggestUrl";
    return true;
}
/**
 * Return the url for the suggest-a-url link on the more tools page, making use
 * of the defined variable REDIRECTS_ON.
 *
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function suggestUrl($with_delim = false)
{
    $base_url = C\SHORT_BASE_URL;
    if (C\REDIRECTS_ON) {
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
        $delim = ($with_delim) ? "?" : "";
        return $base_url ."suggest$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        return "$base_url?c=register&a=suggestUrl$delim";
    }
}
/**
 * Used to route page requests for pages corresponding to a wiki page of
 * group. If it is a wiki page for the public group viewed without being
 * logged in, the route might come in as yioop_instance/p/page_name if
 * redirects are on. If it is for a non-public wiki or page accessed with
 * logged in the url will look like either:
 * yioop_instance/group/group_id?a=wiki&page_name=some_name
 * or
 * yioop_instance/admin/group_id?a=wiki&page_name=some_name&csrf_token_string
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeWiki($route_args)
{
    $handled = true;
    if (isset($route_args[1])) {
        if ($route_args[1] == 'pages') {
            $_REQUEST['c'] = "group";
            $_REQUEST['a'] = 'wiki';
            $_REQUEST['arg'] = 'pages';
            $_REQUEST['route']['c'] = true;
            $_REQUEST['route']['a'] = true;
            $_REQUEST['route']['arg'] = true;
        } else {
            $_REQUEST['c'] = "static";
            $_REQUEST['p'] = $route_args[1];
            $_REQUEST['route']['c'] = true;
            $_REQUEST['route']['p'] = true;
            if (empty($_REQUEST['sf']) && !empty($route_args[2]) ) {
                $rest = array_slice($route_args, 2);
                $_REQUEST['sf'] = implode("/", $rest);
                $_REQUEST['route']['sf'] = true;
            }
        }
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the name of a wiki page, the group it belongs to, and which
 * controller is being used creates the url where that feed item can be
 * accessed from the instance of Yioop. It makes use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $name of wiki page
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @param string $controller which controller is being used to access the
 *      feed: usually static (for the public group), admin, or group
 * @param int $id the group the wiki page belongs to
 * @return string url for the page in question
 */
function wikiUrl($name, $with_delim = false, $controller = "static", $id =
    C\PUBLIC_GROUP_ID)
{
    $q = ($with_delim) ? "?" : "";
    $a = ($with_delim) ? "&" : "";
    $is_static = ($controller == "static");
    $base_url = C\SHORT_BASE_URL;
    if (C\REDIRECTS_ON && $controller != 'api') {
        $q = ($with_delim) ? "?" : "";
        if ($is_static) {
            if ($name == "") {
                $name = "Main";
            }
            return $base_url ."p/$name$q";
        } else {
            $page = ($name== "") ? "?a=wiki$a" : "/$name$q";
            return $base_url .
                "$controller/$id$page";
        }
    } else {
        $delim = ($with_delim) ? "&" : "";
        if ($name == 'pages') {
            if ($is_static) {
                $controller = "group";
            }
            return "$base_url?c=$controller&a=wiki&arg=pages&group_id=$id$a";
        } else {
            if ($is_static) {
                if ($name == "") {
                    $name = "main";
                }
                return "$base_url?c=static&p=$name$a";
            } else {
                $page = ($name== "") ? "" : "&page_name=$name";
                return "$base_url?c=$controller&a=wiki&group_id=$id$page$a";
            }
        }
    }
}
if (php_sapi_name() != 'cli' &&
    (empty($web_site) &&
    !defined("seekquarry\\yioop\\configs\\REDIRECTS_ON"))) {
    /**
     * For error function and yioop constants if we are in non-cli
     * non-redirects situation
     */
    require_once __DIR__ . "/library/Utility.php";
    $web_site =  new L\WebSite();
    bootstrap($web_site);
}
