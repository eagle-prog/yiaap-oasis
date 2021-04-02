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
 * Used to set the configuration settings of the Yioop/SeekQuarry project.
 *
 * Some settings can be set in the Page Options and Server Settings
 * and Appearance activities. Other settings can be overriden by making
 * a LocalConfig.php file in the same folder as this file and using the
 * same namespace.  If a setting in this file is created using nsdefine
 * it is unlikely that it is safe to override. If it is created using
 * nsconddefine it should be fair game for tweaking in the LocalConfig.php
 * file
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\configs;

/**
 * So can autoload classes. We try to use the autoloader that
 * Composer would define but if that fails we use a default autoloader
 */
if (file_exists(__DIR__ . "/../../vendor/autoload.php")) {
    require_once __DIR__ . "/../../vendor/autoload.php";
} else {
    spl_autoload_register(function ($class) {
        // project-specific namespace prefix
        $prefix = 'seekquarry\\yioop\\tests';
        // does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            $prefix = 'seekquarry\\yioop';
            $len = strlen($prefix);
            // no, move to the next registered autoloader
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            } else {
                $check_dirs = [WORK_DIRECTORY . "/app", BASE_DIR];
            }
        } else {
            $check_dirs = [PARENT_DIR . "/tests"];
        }
        // get the relative class name
        $relative_class = substr($class, $len);
        // use forward-slashes, add ./php
        $unixify_class_name = "/".str_replace('\\', '/', $relative_class) .
            '.php';
        foreach ($check_dirs as $dir) {
            $file = $dir . $unixify_class_name;
            if (file_exists($file)) {
                require $file;
                break;
            }
        }
    });
}
/**
 * User defined function to perform error handling for yioop if
 * the error box was checked in the configure menu.
 *
 * @param int $errno the level of the error raised, as an integer
 * @param string $errstr the error message
 * @param string $errfile the filename the error occurred in
 * @param int $errline the line number of the error
 */
function yioop_error_handler($errno, $errstr, $errfile, $errline)
{
    $num_lines_of_backtrace = 5;
    $error_types = [
        E_NOTICE => 'NOTICE:', E_WARNING => 'WARNING:'];
    $type = (isset($error_types[$errno])) ? $error_types[$errno]:
        "PHP OTHER ERROR";
    echo "<pre>\n";
    echo "$type $errstr at line $errline in $errfile\n";
    $backtrace = debug_backtrace();
    array_shift($backtrace);
    $i = 0;
    $in_or_called = "in";
    foreach ($backtrace as $call) {
        $function = "";
        if (isset($call['class'])) {
            $function .= $call['class']."->";
        }
        if (isset($call['function'])) {
            $function .= $call['function'];
        }
        $line = (isset($call['line'])) ? $call['line'] : "";
        $file = (isset($call['file'])) ? $call['file'] : "";
        echo "  $in_or_called $function, line $line".
            " in $file \n";
        $in_or_called = "called from";
        $i++;
        if ($i >= $num_lines_of_backtrace) {
            break;
        }
    }
    if (count($backtrace) > $num_lines_of_backtrace) {
        echo "...\n";
    }
    echo "</pre>";
}
/**
 * Define a constant in the Yioop configs namespace (seekquarry\yioop)
 * @param string $constant the name of the constant to define
 * @param $value the value to give it
 */
function nsdefine($constant, $value)
{
    define("seekquarry\\yioop\\configs\\" . $constant, $value);
}
/**
 * Check if a constant has been defined in the yioop configuration
 * namespace.
 * @param string $constant the constant to check if defined
 * @return bool whether or not it was
 */
function nsdefined($constant)
{
    return defined("seekquarry\\yioop\\configs\\" . $constant);
}
/**
 * Define a constant in the Yioop configs namespace (seekquarry\yioop)
 * if it hasn't been defined yet, otherwise do nothing.
 * @param string $constant the name of the constant to define
 * @param $value the value to give it
 */
function nsconddefine($constant, $value)
{
    if (!defined("seekquarry\\yioop\\configs\\" . $constant)) {
        define("seekquarry\\yioop\\configs\\" . $constant, $value);
    }
}
/**
 * Version number for upgrade database function
 * @var int
 */
nsdefine('DATABASE_VERSION', 69);
/**
 * Minimum Version fo Yioop for which keyword ad script
 * still works with this version
 * @var int
 */
nsdefine('MIN_AD_VERSION', 36);
/**
 * Version number for upgrading locale resource folders and for upgrading
 * public and help wikis
 */
nsdefine('RESOURCES_WIKI_VERSION', 10);
/**
 * nsdefine's the BASE_URL constant for this script
 * if run from the command line as part of index.php HTTP server scrip
 * set the current working directory as well
 */
function initializeBaseUrlAndCurrentWorkingDirectory()
{
    global $argv;
    $path_info = pathinfo($_SERVER['SCRIPT_NAME']);
    if (php_sapi_name() == 'cli') {
        $server_port = 80;
        if (defined("seekquarry\\yioop\\BASE_INDEX_ENTRY")) {
            $server_port = 8080;
            \seekquarry\yioop\processCommandLine();
            if (!empty($path_info['dirname']) && $path_info['dirname'] != '.') {
                chdir($path_info['dirname']);
                $path_info['dirname'] = '.';
                $_SERVER['DOCUMENT_ROOT'] = '';
                $_SERVER['PHP_SELF'] = 'index.php';
                $_SERVER['SCRIPT_NAME'] = 'index.php';
                $_SERVER['SCRIPT_FILENAME'] = 'index.php';
                $_SERVER['PATH_TRANSLATED'] = 'index.php';
                $_SERVER['PWD'] = $path_info['dirname'];
            }
            if (!empty($argv[3])) {
                if (intval($argv[3]) > 0) {
                    $server_port = intval($argv[3]);
                } else {
                    $url_parts = @parse_url($argv[3]);
                    if (empty($url_parts['port']) &&
                        !empty($url_parts['scheme'])) {
                        $server_port = ($url_parts['scheme'] == "https") ?
                            443 : 80;
                    } else if (!empty($url_parts['port'])) {
                        $server_port = $url_parts['port'];
                    }
                }
            }
        } else {
            /* This situation happens for some command line tools like
               QueryTool which can run fine without BASE_URL being defined
             */
            return;
        }
    } else {
        $server_port = isset($_SERVER['HTTP_X_FORWARDED_PORT']) ?
            $_SERVER['HTTP_X_FORWARDED_PORT'] :
            (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80);
    }
    $http = (!empty($_SERVER['HTTPS']) || $server_port == 443) ?
        "https://" : "http://";
    $port = ( ($http == "http://" && ($server_port != 80) ||
        ($http == "https://" && $server_port != 443))) ?
        ":" . $server_port : "";
    if (nsdefined('SERVER_CONTEXT')) {
        $context = SERVER_CONTEXT;
        if (!empty($context['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = $context['SERVER_NAME'];
        }
    }
    $server_name = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] :
        "localhost";
    if (strpos($server_name, ":") !== false && $server_name[0] != '[') {
        $server_name = "[$server_name]"; //guessing ipv6 address
    }
    $dir_name = $path_info["dirname"];
    if ($dir_name == ".") {
        $dir_name = "";
    }
    $extra_slash = ($dir_name == '/') ? "" : '/';
    if ((!defined("seekquarry\\yioop\\configs\\REDIRECTS_ON") || !REDIRECTS_ON)
        && strpos($dir_name, "src") === false) {
        $extra_slash .= "src/";
    }
    //used in register controller to create links back to server
    nsdefine("BASE_URL", $http . $server_name . $port . $dir_name .
        $extra_slash);
    nsdefine("SHORT_BASE_URL", $dir_name . $extra_slash);
}
/*
    pcre is an external library to php which can cause Yioop
    to seg fault if given instances of reg expressions with
    large recursion depth on a string.
    https://bugs.php.net/bug.php?id=47376
    The goal here is to cut off these problems before they happen.
    We do this in config.php because it is included in most Yioop
    files.
 */
ini_set('pcre.recursion_limit', 3000);
ini_set('pcre.backtrack_limit', 1000000);
    /** Calculate base directory of script
     * @ignore
     */
nsconddefine("BASE_DIR", str_replace("\\", "/", realpath(__DIR__ ."/../")));
nsconddefine("PARENT_DIR",  substr(BASE_DIR, 0, -strlen("/src")));
nsconddefine("TEST_DIR",  PARENT_DIR . '/tests');
if (file_exists(BASE_DIR . "/configs/LocalConfig.php")) {
    /** Include any locally specified defines (could use as an alternative
        way to set work directory) */
    require_once(BASE_DIR . "/configs/LocalConfig.php");
}
if (!defined("seekquarry\\yioop\\configs\\REDIRECTS_ON")) {
    if (!empty($_SERVER['YIOOP_REDIRECTS_ON']) || php_sapi_name() == 'cli' ||
        (nsdefined("IS_OWN_WEB_SERVER") && IS_OWN_WEB_SERVER)) {
        define("seekquarry\\yioop\\configs\\REDIRECTS_ON", true);
    } else {
        define("seekquarry\\yioop\\configs\\REDIRECTS_ON", false);
    }
}
initializeBaseUrlAndCurrentWorkingDirectory();
/** Yioop Namespace*/
nsdefine('NS', "seekquarry\\yioop\\");
/** configs sub-namespace */
nsdefine('NS_CONFIGS', NS . "configs\\");
/** controllers sub-namespace */
nsdefine('NS_CONTROLLERS', NS . "controllers\\");
/** components sub-namespace */
nsdefine('NS_COMPONENTS', NS_CONTROLLERS . "components\\");
/** executables sub-namespace */
nsdefine('NS_EXEC', NS . "executables\\");
/** library sub-namespace */
nsdefine('NS_LIB', NS . "library\\");
/** jobs sub-namespace */
nsdefine('NS_JOBS', NS_LIB . "media_jobs\\");
/** Models sub-namespace */
nsdefine('NS_MODELS', NS . "models\\");
/** datasources sub-namespace */
nsdefine('NS_DATASOURCES', NS_MODELS . "datasources\\");
/** archive_bundle_iterators sub-namespace */
nsdefine('NS_ARCHIVE', NS_LIB . "archive_bundle_iterators\\");
/** indexing_plugins sub-namespace */
nsdefine('NS_PLUGINS', NS_LIB . "indexing_plugins\\");
/** indexing_plugins sub-namespace */
nsdefine('NS_PROCESSORS', NS_LIB . "processors\\");
/** indexing_plugins sub-namespace */
nsdefine('NS_COMPRESSORS', NS_LIB . "compressors\\");
/** text sumamrizer sub-namespace */
nsdefine('NS_SUMMARIZERS', NS_LIB . "summarizers\\");
/** locale sub-namespace */
nsdefine('NS_LOCALE', NS . "locale\\");
/** views sub-namespace */
nsdefine('NS_VIEWS', NS . "views\\");
/** elements sub-namespace */
nsdefine('NS_ELEMENTS', NS_VIEWS . "elements\\");
/** helpers sub-namespace */
nsdefine('NS_HELPERS', NS_VIEWS . "helpers\\");
/** layouts sub-namespace */
nsdefine('NS_LAYOUTS', NS_VIEWS . "layouts\\");
/** tests sub-namespace */
nsdefine('NS_TESTS', NS . "tests\\");
/** Don't display any query info*/
nsdefine('NO_DEBUG_INFO', 0);
/** bit of DEBUG_LEVEL used to indicate test cases should be displayable*/
nsdefine('TEST_INFO', 1);
/** bit of DEBUG_LEVEL used to indicate query statistics should be displayed*/
nsdefine('QUERY_INFO', 2);
/** bit of DEBUG_LEVEL used to indicate php messages should be displayed*/
nsdefine('ERROR_INFO', 4);
/** Maintenance mode restricts access to local machine*/
nsdefine("MAINTENANCE_MODE", false);
/** Constant used to indicate lasting an arbitrary number of seconds */
nsdefine('FOREVER', -2);
/** Number of seconds in a day*/
nsdefine('ONE_DAY', 86400);
/** Number of seconds in a week*/
nsdefine('ONE_WEEK', 604800);
/** Number of seconds in a 30 day month */
nsdefine('ONE_MONTH', 2592000);
/** Number of seconds in a 365 day year */
nsdefine('ONE_YEAR',  31536000);
/** Number of seconds in an hour */
nsdefine('ONE_HOUR', 3600);
/** Number of seconds in a minute */
nsdefine('ONE_MINUTE', 60);
/** Number of seconds in a second */
nsdefine('ONE_SECOND', 1);
/** setting Profile.php to something else in LocalConfig.php allows one to have
 *  two different yioop instances share the same work_directory but maybe have
 *  different configuration settings. This might be useful if one was
 *  production and one was more dev.
 */
nsconddefine('PROFILE_FILE_NAME', "/Profile.php");
nsconddefine('MAINTENANCE_MESSAGE', <<<EOD
This Yioop! installation is undergoing maintenance, please come back later!
EOD
);
if (MAINTENANCE_MODE && !empty($_SERVER["SERVER_ADDR"]) &&
    !empty($_SERVER["REMOTE_ADDR"]) &&
    $_SERVER["SERVER_ADDR"] != $_SERVER["REMOTE_ADDR"]) {
    echo MAINTENANCE_MESSAGE;
    exit();
}

/** */
nsdefine('DEFAULT_WORK_DIRECTORY', PARENT_DIR . "/work_directory");

if (!nsdefined('WORK_DIRECTORY')) {
/*+++ The next block of code is machine edited, change at
your own risk, please use configure web page instead +++*/
nsdefine('WORK_DIRECTORY', DEFAULT_WORK_DIRECTORY);
/*++++++*/
// end machine edited code
}
/** Directory for local versions of web app classes*/
nsdefine('APP_DIR', WORK_DIRECTORY . "/app");
/**
 * Directory to place files such as dictionaries that will be
 * converted to Bloom filter using token_tool.php. Similarly,
 * can be used to hold files which will be used to prepare
 * a file to assist in crawling or serving search results
 */
nsdefine('PREP_DIR', WORK_DIRECTORY . "/prepare");
/** Locale dir to use in case LOCALE_DIR does not exist yet or is
 * missing some file
 */
nsdefine('FALLBACK_LOCALE_DIR', BASE_DIR . "/locale");
/** Captcha mode indicating to use a hash cash computation for a captcha*/
nsdefine('HASH_CAPTCHA', 2);
/** Captcha mode indicating to use a classic image based captcha*/
nsdefine('IMAGE_CAPTCHA', 3);
/** */
nsdefine('NO_RECOVERY', 0);
/** */
nsdefine('EMAIL_RECOVERY', 1);
/** */
nsdefine('EMAIL_AND_QUESTIONS_RECOVERY', 2);
if (file_exists(WORK_DIRECTORY . PROFILE_FILE_NAME)) {
    if ((file_exists(WORK_DIRECTORY . "/locale/en-US") &&
        !file_exists(WORK_DIRECTORY . "/locale/en_US"))
        || (file_exists(WORK_DIRECTORY . "/app/locale/en-US") &&
        !file_exists(WORK_DIRECTORY . "/app/locale/en_US"))) {
        $old_profile = file_get_contents(WORK_DIRECTORY . PROFILE_FILE_NAME);
        $new_profile = preg_replace('/\<\?php/', "<?php\n".
            "namespace seekquarry\\yioop\\configs;\n",
            $old_profile);
        $new_profile = preg_replace("/(define(?:d?))\(/", 'ns$1(',
            $new_profile);
        file_put_contents(WORK_DIRECTORY . PROFILE_FILE_NAME, $new_profile);
    }
    require_once WORK_DIRECTORY . PROFILE_FILE_NAME;
    nsdefine('PROFILE', true);
    nsdefine('CRAWL_DIR', WORK_DIRECTORY);
    if (is_dir(APP_DIR . "/locale")) {
        nsdefine('LOCALE_DIR', WORK_DIRECTORY . "/app/locale");
    } else if (is_dir(WORK_DIRECTORY . "/locale")) {
        //old work directory location
        nsdefine('LOCALE_DIR', WORK_DIRECTORY . "/locale");
    } else {
        /** @ignore */
        nsdefine('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    }
    nsdefine('LOG_DIR', WORK_DIRECTORY . "/log");
    if (nsdefined('DB_URL') && !nsdefined('DB_HOST')) {
        nsdefine('DB_HOST', DB_URL); //for backward compatibility
    }
    if (nsdefined('QUEUE_SERVER') && !nsdefined('NAME_SERVER')) {
        nsdefine('NAME_SERVER', QUEUE_SERVER); //for backward compatibility
    }
    if (NAME_SERVER == 'http://' || NAME_SERVER == 'https://') {
        nsdefine("FIX_NAME_SERVER", true);
    }
} else {
    if ((!isset( $_SERVER['SERVER_NAME']) ||
        $_SERVER['SERVER_NAME']!=='localhost')
        && !nsdefined("NO_LOCAL_CHECK") && !nsdefined("WORK_DIRECTORY")
        && php_sapi_name() != 'cli' &&
        !nsdefined("IS_OWN_WEB_SERVER")) {
        echo "SERVICE AVAILABLE ONLY VIA LOCALHOST UNTIL CONFIGURED";
        exit();
    }
    /** @ignore */
    nsconddefine('PROFILE', false);
    nsdefine('RECOVERY_MODE', EMAIL_RECOVERY);
    nsconddefine('DEBUG_LEVEL', NO_DEBUG_INFO);
    nsdefine('USE_FILECACHE', false);
    nsdefine('WEB_ACCESS', true);
    nsdefine('RSS_ACCESS', true);
    nsdefine('API_ACCESS', true);
    nsdefine('REGISTRATION_TYPE', 'disable_registration');
    nsdefine('USE_MAIL_PHP', true);
    nsdefine('MAIL_SENDER', '');
    nsdefine('MAIL_SERVER', '');
    nsdefine('MAIL_PORT', '');
    nsdefine('MAIL_USERNAME', '');
    nsdefine('MAIL_PASSWORD', '');
    nsdefine('MAIL_SECURITY', '');
    nsdefine('MEDIA_MODE', 'name_server');
    nsdefine('DBMS', 'Sqlite3');
    nsdefine('DB_NAME', "public_default");
    nsdefine('DB_USER', '');
    nsdefine('DB_PASSWORD', '');
    nsdefine('DB_HOST', '');
    nsdefine('PRIVATE_DBMS', 'Sqlite3');
    nsdefine('PRIVATE_DB_USER', '');
    nsdefine('PRIVATE_DB_PASSWORD', '');
    nsdefine('PRIVATE_DB_HOST', '');
    nsdefine('PRIVATE_DB_NAME', "private_default");
    /** @ignore */
    nsdefine('CRAWL_DIR', BASE_DIR);
    /** @ignore */
    nsdefine('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    nsdefine('INDEX_FILE_MEMORY_LIMIT', "250M");
    /** @ignore */
    nsdefine('LOG_DIR', BASE_DIR . "/log");
    nsdefine('NAME_SERVER', "http://localhost/");
    nsdefine('USER_AGENT_SHORT', "NeedsNameBot");
    nsdefine('DEFAULT_LOCALE', "en-US");
    nsdefine('AUTH_KEY', 0);
    nsdefine('USE_PROXY', false);
    nsdefine('TOR_PROXY', '127.0.0.1:9150');
    nsdefine('PROXY_SERVERS', null);
    nsdefine('WORD_SUGGEST', true);
    nsdefine('CACHE_LINK', true);
    nsdefine('SIMILAR_LINK', true);
    nsdefine('IN_LINK', true);
    nsdefine('IP_LINK', true);
    nsdefine('RESULT_SCORE', true);
    nsdefine('SIGNIN_LINK', true);
    nsdefine('SUBSEARCH_LINK', true);
    /** BM25F weight for title text */
    nsdefine('TITLE_WEIGHT', 4);
    /** BM25F weight for other text within doc*/
    nsdefine('DESCRIPTION_WEIGHT', 1);
    /** BM25F weight for other text within links to a doc*/
    nsdefine('LINK_WEIGHT', 2);
    /**
     * If that many exist, the minimum number of results to get
     * and group before trying to compute the top x (say 10) results
     */
    nsdefine('MIN_RESULTS_TO_GROUP', 200);
    nsdefine('BACKGROUND_COLOR', "#FFFFFF");
    nsdefine('FOREGROUND_COLOR', "#FFFFFF");
    nsdefine('SIDEBAR_COLOR', "#F8F8F8");
    nsdefine('TOPBAR_COLOR', "#F5F5FF");
    nsdefine('MONETIZATION_TYPE', "no_monetization");
    nsdefine('AD_LOCATION','none');
}

/** ignore */
nsconddefine('PRIVATE_DBMS', 'Sqlite3');
nsconddefine('PRIVATE_DB_USER', '');
nsconddefine('PRIVATE_DB_PASSWORD', '');
nsconddefine('PRIVATE_DB_HOST', '');
nsconddefine('PRIVATE_DB_NAME', "private_default");
/** URL that all url paths will be constructed from.
 *  The definition below is only used for some command line tools.
 */
nsconddefine('BASE_URL', NAME_SERVER);
/** Would be the path only version of BASE_URL usually. However,
 * for command line tools we define it the same as BASE_URL
 */
nsconddefine('SHORT_BASE_URL', BASE_URL);
/** Relative url to website logos of different sizes */
nsconddefine('LOGO_SMALL', "resources/yioop-small.png");
nsconddefine('LOGO_MEDIUM', "resources/yioop-medium.png");
nsconddefine('LOGO_LARGE', "resources/yioop-large.png");
/** Url for website favicon */
nsconddefine('FAVICON', "favicon.ico");
/** Timezone for website */
nsconddefine('TIMEZONE', 'America/Los_Angeles');
/* name of the cookie used to manage the session
   (store language and perpage settings), define CSRF token
 */
nsconddefine('SESSION_NAME', "yioopbiscuit");
nsconddefine('CSRF_TOKEN', "YIOOP_TOKEN");
nsconddefine('RESOURCE_CACHE_TIME', "" . ONE_HOUR);
nsconddefine('AUTOLOGOUT', "" . ONE_HOUR);
nsconddefine('COOKIE_LIFETIME', "" . ONE_YEAR);
/* secure cookies are cookies that can only be sentt over https */
nsconddefine('SECURE_COOKIE', ((BASE_URL == NAME_SERVER) &&
    !empty($_SERVER['HTTPS'])) ? true : false);
/** locations that ads can be placed in search result pages */
nsconddefine('AD_LOCATION', "none");
date_default_timezone_set(TIMEZONE);
if ((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}
/** if true tests are diplayable*/
nsdefine('DISPLAY_TESTS', ((DEBUG_LEVEL & TEST_INFO) == TEST_INFO));
/** if true query statistics are diplayed */
nsconddefine('QUERY_STATISTICS', ((DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO));
/*
 * Various groups and user ids. These must be nsdefined before the
 * profile check and return below
 */
/** ID of the root user */
nsdefine('ROOT_ID', 1);
/**User name of the root user. If you want to change this, change
 the value in LocalConfig.php, then run the Createdb.php script. You
 should do this before you have much data in your system. */
nsconddefine('ROOT_USERNAME', "root");
/** Role of the root user */
nsdefine('ADMIN_ROLE', 1);
/** Default role of an active user */
nsdefine('USER_ROLE', 2);
/** Default role of an advertiser */
nsdefine('BUSINESS_ROLE', 3);
/** Default role of a bot user */
nsdefine('BOT_ROLE', 4);
/** ID of the group to which all Yioop users belong */
nsdefine('PUBLIC_GROUP_ID', 2);
/** ID of the group to which all Yioop users belong */
nsdefine('PUBLIC_USER_ID', 2);
/** ID of the group to which all Yioop Help Wiki articles belong */
nsdefine('HELP_GROUP_ID', 3);
/** ID of the group to search sidebar wiki pages and edited search results
 belong.
 */
nsconddefine('SEARCH_GROUP_ID', 4);
/** Length of advertisement name string */
nsdefine('ADVERTISEMENT_NAME_LEN', 25);
/** Length of advertisement text description */
nsdefine('ADVERTISEMENT_TEXT_LEN', 35);
/** Length of advertisement keywords */
nsdefine('ADVERTISEMENT_KEYWORD_LEN', 60);
/** Length of advertisement date */
nsdefine('ADVERTISEMENT_DATE_LEN', 20);
/** Length of advertisement destination */
nsdefine('ADVERTISEMENT_DESTINATION_LEN', 60);
/** value used to create advertisement*/
nsdefine('ADVERTISEMENT_ACTIVE_STATUS', 1);
/** value used to stop advertisement campaign */
nsdefine('ADVERTISEMENT_DEACTIVATED_STATUS',2);
/** value used to admin suspend advertisement campaign */
nsdefine('ADVERTISEMENT_SUSPENDED_STATUS',3);
/** value used to indicate campaign completed successfully */
nsdefine('ADVERTISEMENT_COMPLETED_STATUS',4);
if (!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the below as desired +++*/
/** this is the User-Agent names the crawler provides
 * a web-server it is crawling
 */
if (defined("seekquarry\\yioop\\config\REDIRECTS_ON") &&
    REDIRECTS_ON) {
    nsconddefine('USER_AGENT',
        'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'; +'.NAME_SERVER.'bot)');
    $name_server_url = NAME_SERVER;
} else {
    $name_server_url =  (NAME_SERVER . "src/" == BASE_URL ||
        NAME_SERVER . "/src/" == BASE_URL ||
        substr(NAME_SERVER, -4) == "src/" ) ? BASE_URL : NAME_SERVER;
    nsconddefine('USER_AGENT',
        'Mozilla/5.0 (compatible; ' .
        USER_AGENT_SHORT.'; +' . NAME_SERVER . 'bot.php)');
}
/**
 * To change the Open Search Tool bar name overrride the following variable
 * in your LocalConfig.php file
 */
nsconddefine('SEARCHBAR_PATH', $name_server_url . "yioopbar.xml");
/**
 * Phantom JS is used by some optional Javascript tests of the Yioop interface.
 * The constant PHANTOM_JS should point to the path to phantomjs
 */
nsconddefine("PHANTOM_JS", "phantomjs");
/** maximum size of a log file before it is rotated */
nsconddefine("MAX_LOG_FILE_SIZE", 5000000);
/** number of log files to rotate amongst */
nsconddefine("NUMBER_OF_LOG_FILES", 5);
/**
 * how long in seconds to keep a cache of a robot.txt
 * file before re-requesting it
 */
nsconddefine('CACHE_ROBOT_TXT_TIME', ONE_DAY);
/**
 * QueueServer cache's in ram up to this many robots.txt files
 * to speed up checking if a url is okay to crawl. All robots.txt
 * files are kept on disk, but might be slower to access if not in cache.
 */
nsconddefine('SIZE_ROBOT_TXT_CACHE', 1000);
/**
 *
 */
nsdefine('ALWAYS_FOLLOW_ROBOTS', 1);
/**
 *
 */
nsdefine('ALLOW_LANDING_ROBOTS', 2);
/**
 *
 */
nsdefine('IGNORE_ROBOTS', 3);

/**
 * Whether the scheduler should track ETag and Expires headers.
 * If you want to turn this off set the variable to false in
 * LocalConfig.php
 */
nsconddefine('USE_ETAG_EXPIRES', true);
/**
 * if the robots.txt has a Crawl-delay larger than this
 * value don't crawl the site.
 * maximum value for this is 255
 */
nsconddefine('MAXIMUM_CRAWL_DELAY', 64);
/** maximum number of active crawl-delayed hosts */
nsconddefine('MAX_WAITING_HOSTS', 250);
/** maximum fraction of URLS in the Queue that are crawl-delayed and waiting
 * before delete from queue new crawl-delayed urls
 */
nsconddefine('WAITING_URL_FRACTION', 0.1);
/** Minimum weight in priority queue before rebuild */
nsconddefine('MIN_QUEUE_WEIGHT', 83);
/**  largest sized object allowed in a web archive (used to sanity check
 *  reading data out of a web archive)
 */
nsconddefine('MAX_ARCHIVE_OBJECT_SIZE', 100000000);
/** Treat earlier timestamps as being indexes of format version 0 */
nsconddefine('VERSION_0_TIMESTAMP', 1369754208);
/** Treat earlier timestamps as being indexes of format version 1 */
nsconddefine('VERSION_1_TIMESTAMP', 1528045371);
/** What version format to use for default indexing **/
nsconddefine('DEFAULT_CRAWL_FORMAT', 2);
/** 1 Gigibyte (GiB)*/
nsdefine('ONE_GIB', 1073741824);
/**
 * Code to determine how much memory current machine has
 */
function defineMemoryProfile()
{
    //assume have at least 4GiB
    $memory = 4 * ONE_GIB;
    if (strstr(PHP_OS, "WIN")) {
        if (function_exists("exec")) {
            exec('wmic memorychip get capacity', $memory_array);
            if ($memory_array) {
                $memory = array_sum($memory_array);
            }
        }
    } else if (stristr(PHP_OS, "LINUX")) {
        set_error_handler(null);
        $mem_data = @file_get_contents("/proc/meminfo");
        set_error_handler(NS_CONFIGS . "yioop_error_handler");
        if (!empty($mem_data)) {
            $data = preg_split("/\s+/", $mem_data);
            $memory = 1024 * intval($data[1]);
        }
    } else if (stristr(PHP_OS, "DARWIN")) {
        exec('/usr/sbin/sysctl hw.memsize', $memory_array);
        if (!empty($memory_array)) {
            preg_match("/\d+/", $memory_array[0], $mem_matches);
            $memory = $mem_matches[0];
        }
    }
    $memory_factor = ceil($memory / (2 * ONE_GIB));
    nsdefine('MEMORY_PROFILE', min(4, $memory_factor));
    nsdefine('SYSTEM_RAM', $memory);
}
//Check system memory then set up limits for prcoesses based on this
defineMemoryProfile();
/** Max memory index.php can use */
nsconddefine('INDEX_FILE_MEMORY_LIMIT', ceil(MEMORY_PROFILE/4) . "000M");
/** Max memory a QueueServer can use */
nsconddefine('QUEUE_SERVER_MEMORY_LIMIT', MEMORY_PROFILE . "000M");
/** Max memory a Fetcher can use */
nsconddefine('FETCHER_MEMORY_LIMIT', ceil(MEMORY_PROFILE/2) . "000M");
/** Max memory a MediaUpdater can use */
nsconddefine('MEDIA_UPDATER_MEMORY_LIMIT', ceil(MEMORY_PROFILE/2) . "000M");
/** Max memory a Mirror can use */
nsconddefine('MIRROR_MEMORY_LIMIT', ceil(MEMORY_PROFILE/4) ."000M");
/** Max memory a ClassifierTrainer can use */
nsconddefine('CLASSIFIER_TRAINER_LIMIT', ceil(MEMORY_PROFILE/4) ."000M");
/** Max memory a QueueServer can use */
nsconddefine('ARC_TOOL_MEMORY_LIMIT', (2 * MEMORY_PROFILE) . "000M");
/** Max memory a TokenTool can use */
nsconddefine('TOKEN_TOOL_MEMORY_LIMIT', ceil(MEMORY_PROFILE/2) . "000M");
/** Used to control fraction of memory filled of current process
 *  (usually Fetcher or QueueServer) before action (such as switch shard)
 *  on current class (usually IndexArchiveBundle) is taken.
 */
nsconddefine('MEMORY_FILL_FACTOR', 0.6);
/**
 * bloom filters are used to keep track of which urls are visited,
 * this parameter determines up to how many
 * urls will be stored in a single filter. Additional filters are
 * read to and from disk.
 */
nsconddefine('URL_FILTER_SIZE', MEMORY_PROFILE * 5000000);
/**
 * maximum number of urls that will be held in ram
 * (as opposed to in files) in the priority queue
 */
nsconddefine('NUM_URLS_QUEUE_RAM', MEMORY_PROFILE * 80000);
/** number of documents before next gen */
nsconddefine('NUM_DOCS_PER_GENERATION', MEMORY_PROFILE * 10000);
/** precision to round floating points document scores */
nsconddefine('PRECISION', 10);
/** maximum number of links to extract from a page on an initial pass*/
nsconddefine('MAX_LINKS_TO_EXTRACT', MEMORY_PROFILE * 80);
/** Estimate of the average number of links per page a document has*/
nsconddefine('AVG_LINKS_PER_PAGE', 24);
/**  minimum char length of link text before gets its own document */
nsconddefine('MIN_LINKS_TEXT_CHARS', 3);
/**  maximum number of chars for link text to use for any given url on
 *   page. As an example suppose the the url https://www.yahoo.com/
 *   appears twice of a page, first with with link text "Yahoo!" and
 *   the second time with text "Web Portal", the total useful link text
 *   for https://www.yahoo.com/ is "Yahoo! Web Portal" or 18 characters.
 *   MAX_LINKS_TEXT_CHARS serves as an upper bound on this useful link text
 *   after which further text on the same page to the same url will be trimmed.
 */
nsconddefine('MAX_LINKS_TEXT_CHARS', 100);
/**  maximum length of urls to try to queue, this is important for
 *  memory when creating schedule, since the amount of memory is
 *  going to be greater than the product MAX_URL_LEN*MAX_FETCH_SIZE
 *  text_processors need to promise to implement this check or rely
 *  on the base class which does implement it in extractHttpHttpsUrls
 */
nsconddefine('MAX_URL_LEN', 2048);
/** request this many bytes out of a page -- this is the default value to
 * use if the user doesn't set this value in the page options GUI
 */
nsdefine('PAGE_RANGE_REQUEST', 50000);
/**
 * When getting information from an index dictionary in word iterator
 * how many distinct generations to read in in one go
 */
nsconddefine('NUM_DISTINCT_GENERATIONS', 20);
/**
 * Used in computing the DOC_RANK when a going through index in descending
 * fashion.  It represents an upper bound on the maximum number of
 * generations an IndexArchiveBundle should have
 */
nsconddefine('MAX_GENERATIONS', 10000);
/**
 * Max number of chars to extract for description from a page to index.
 * Only words in the description are indexed. -- this is the default value
 * can be set in Page Options
 */
nsdefine('MAX_DESCRIPTION_LEN', 2000);
/**
 * Allow pages to be recrawled after this many days -- this is the
 * default value to use if the user doesn't set this value in the page options
 * GUI. What this controls is how often the page url filter is deleted.
 * A nonpositive value means the filter will never be deleted.
 */
nsdefine('PAGE_RECRAWL_FREQUENCY', -1);
/** number of multi curl page requests in one go */
nsconddefine('NUM_MULTI_CURL_PAGES', 100);
/** number of pages to extract from an archive in one go */
nsconddefine('ARCHIVE_BATCH_SIZE', 100);
/** time in seconds before we give up on multi page requests*/
nsconddefine('PAGE_TIMEOUT', 10);
/** time in seconds before we give up on a single page request*/
nsconddefine('SINGLE_PAGE_TIMEOUT', (ONE_MINUTE/2));
/** max time in seconds in a process before write a log message if
 *  crawlTimeoutLog is called repeatedly from a loop
 */
nsconddefine('LOG_TIMEOUT', 30);
/** Number of lines of QueueServer log file to check to make sure both
 *  Indexer and Scheduler are running. 6000 should be roughly 20-30 minutes
 */
nsconddefine('LOG_LINES_TO_RESTART', 6000);
/** File name of file used to record last log lines when a Yioop process has
 * crashed.
 */
nsconddefine('CRASH_LOG_NAME', LOG_DIR . "/YioopCrashes.log");
/**
 * Maximum time a crawl daemon process can go before calling.
 * This is also the amount of time in log files of a Indexer or Scheduler
 * without a message before the process is deemed dead, and might be restarted
 * by other Queue Server process if possible.
 * @see CrawlDaemon::processHandler
 */
nsconddefine('PROCESS_TIMEOUT', 15 * ONE_MINUTE);
/** Number of seconds of no fetcher contact before crawl is deemed dead
 *  The files C\CRAWL_DIR . "/schedules/{$this->channel}-crawl_status.txt"
    is used to determine if CRAWL_TIMEOUT reached.
 *  This is modified by QueueServer::writeAdminMessages only when
 *  the crawl state (waiting/start crawl/ shutdown, etc) changes.
 *  It is also updated when a fetcher sends an update command to
 *  FetchController when sends (schedule, robot, etag, or index info). Hence,
 *  if no data is sent by a fetcher to a queue server for a long time the
 *  crawl is likely stalled
 */
nsconddefine("CRAWL_TIMEOUT", 2 * PROCESS_TIMEOUT);
/**
 * Delay in microseconds between processing pages to try to avoid
 * CPU overheating. On some systems, you can set this to 0.
 */
nsconddefine('FETCHER_PROCESS_DELAY', 10000);
/**
 * Number of error page 400 or greater seen from a host before crawl-delay
 * host and dump remainder from current schedule
 */
nsconddefine('DOWNLOAD_ERROR_THRESHOLD', 50);
/** Crawl-delay to set in the event that DOWNLOAD_ERROR_THRESHOLD exceeded*/
nsconddefine('ERROR_CRAWL_DELAY', 20);
/**
 * if FFMPEG defined, the maximum size of a uploaded video file which will
 * be automatically transcode by Yioop to mp4 and webm
 */
nsconddefine("MAX_VIDEO_CONVERT_SIZE", 2000000000);
/**
 * The maximum time limit in seconds where if a file is not converted by the
 * time it will be picked up again by the client media updater
 * This value largely depends on the no of client media updaters that we have
 * and also the maximum video size that would be uploaded to yioop.
 * This value should be kept more than the sleeping time of media updater
 * loop to avoid conversion of same file multiple times.
 */
nsconddefine('MAX_FILE_TIMESTAMP_LIMIT', 600);
/**
 * Mail scheduled for delivery by yioop is aggregated in a text file until
 * MAIL_AGGREGATION_TIME seconds has passed. At this point all the mail
 * in thhe aggregation file is sent and a new aggregation file is started.
 */
nsconddefine('MAIL_AGGREGATION_TIME', 30);
/**
 * Default edge size of square image thumbnails in pixels
 */
nsconddefine('THUMB_DIM', 128);
/**
 * Maximum size of a user thumb file that can be uploaded
 */
nsconddefine('THUMB_SIZE', 1000000);
/** Characters we view as not part of words, not same as POSIX [:punct:]*/
nsconddefine('PUNCT', "\.|\,|\:|\;|\"|\'|\[|\/|\%|\?|-|" .
    "\]|\{|\}|\(|\)|\!|\||।|\&|\`|" .
    "\’|\‘|©|®|™|℠|…|\/|\>|，|\=|。|）|：|、|" .
    "”|“|《|》|（|「|」|★|【|】|·|\+|\*|；".
        "|！|—|―|？|！|،|؛|؞|؟|٪|٬|٭|\‚|\‘");
/** Number of total description deemed title */
nsconddefine('AD_HOC_TITLE_LENGTH', 50);
/** Maximum numbber of simultaneous crawls (each concurrent crawl gets one
   channel
*/
nsconddefine('MAX_CHANNELS', 10);
/** Used to say number of bytes in histogram bar (stats page) for file
    download sizes
 */
nsconddefine('DOWNLOAD_SIZE_INTERVAL', 5000);
/** Used to say number of secs in histogram bar for file download times*/
nsconddefine('DOWNLOAD_TIME_INTERVAL', 0.5);
/**
 * How many non robot urls the fetcher successfully downloads before
 * between times data sent back to queue server
 */
nsconddefine('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', MEMORY_PROFILE * 95);
/** maximum number of urls to schedule to a given fetcher in one go */
nsconddefine('MAX_FETCH_SIZE', MEMORY_PROFILE * 1000);
/** fetcher must wait at least this long between multi-curl requests */
nsconddefine('MINIMUM_FETCH_LOOP_TIME', 5);
/** an idling fetcher sleeps this long between queue_server pings*/
nsconddefine('FETCH_SLEEP_TIME', 10);
/** an a queue_server minimum loop idle time*/
nsconddefine('QUEUE_SLEEP_TIME', 5);
/** How often mirror script tries to synchronize with machine it is mirroring*/
nsconddefine('MIRROR_SYNC_FREQUENCY', ONE_HOUR);
/** How often mirror script tries to notify machine it is mirroring that it
is still alive*/
nsconddefine('MIRROR_NOTIFY_FREQUENCY', ONE_MINUTE);
/** Max time before currrent index shard is rebuilt (queue_server) */
nsconddefine('FORCE_SAVE_TIME', 10 * ONE_MINUTE);
/** maximum lenght of a  search query */
nsconddefine('MAX_QUERY_TERMS', 10);
/** maximum number of terms allowed in a conjunctive search query */
nsconddefine('MAX_QUERY_LEN', 4096);
/** whether to use question answering system */
nsconddefine('ENABLE_QUESTION_ANSWERING', true);
/** If true, when processing query see if subsets of terms in query form a
 *  known phrase and if so do lookup with that rather than do a conjunctive
 *  query over those terms
 */
/** Number of words until a string of words might be parsed as a sentence
 * for question answering
 */
nsconddefine('PHRASE_THRESHOLD', 3);
/** default number of search results to display per page */
nsconddefine('NUM_RESULTS_PER_PAGE', 10);
/** Number of recently crawled urls to display on admin screen */
nsconddefine('NUM_RECENT_URLS_TO_DISPLAY', 10);
/** Maximum time a set of results can stay in query cache before it is
 * invalidated. If negative, then never use time to kick something out of
 * cache.
 */
nsconddefine('MAX_QUERY_CACHE_TIME', 2 * ONE_DAY); //two days
/** Minimum time a set of results can stay in query cache before it is
 * invalidated (used for active crawl or feed results)
 */
nsconddefine('MIN_QUERY_CACHE_TIME', ONE_HOUR); //one hour
/**
 * Default number of items to page through for users,roles, mixes, etc
 * on the admin screens
 */
nsconddefine('DEFAULT_ADMIN_PAGING_NUM', 50);
/** Maximum number of bytes that the file that the suggest-a-url form
 * send data to can be.
 */
nsconddefine('MAX_SUGGEST_URL_FILE_SIZE', 100000);
/** Maximum number of a user can suggest to the suggest-a-url form in one day
 */
nsconddefine('MAX_SUGGEST_URLS_ONE_DAY', 10);
/** Directly add suggested urls to crawl options and inject them into any
 *  active crawl. If false, these are stored in a file and the user has to
 *  click a button to add them.
 */
nsconddefine('DIRECT_ADD_SUGGEST', false);
/**
 * Length after which to truncate names for users/groups/roles when
 * they are displayed (not in DB)
 */
nsconddefine('NAME_TRUNCATE_LEN', 7);
/** USER STATUS value used for someone who is not in a group but can browse*/
nsdefine('NOT_MEMBER_STATUS', -1);
/** USER STATUS value used for a user who can log in and perform activities */
nsdefine('ACTIVE_STATUS', 1);
/**
 * USER STATUS value used for a user whose account is created, but which
 * still needs to undergo admin or email verification/activation
 */
nsdefine('INACTIVE_STATUS', 2);
/**
 * USER STATUS used to indicate an account which can no longer perform
 * activities but which might be retained to preserve old blog posts.
 */
nsdefine('SUSPENDED_STATUS', 3);
/** Group status used to indicate a user that has been invited to join
 * a group but who has not yet accepted
 */
nsdefine('INVITED_STATUS', 4);
/**
 * Group registration type that only allows people to join a group by
 * invitation
 */
nsdefine('NO_JOIN', 1);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner
 */
nsdefine('REQUEST_JOIN', 2);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner, but allows people to browse the groups
 * content without join
 */
nsdefine('PUBLIC_BROWSE_REQUEST_JOIN', 3);
/**
 * Group registration type that allows anyone to obtain membership
 * in the group
 */
nsdefine('PUBLIC_JOIN', 4);
/**
 * If a group has a fee to join, the fee will e at least this much.
 */
nsdefine('LOW_JOIN_FEE', 20);
/**
 *  Group access code signifying only the group owner can
 *  read items posted to the group or post new items
 */
nsdefine('GROUP_PRIVATE', 1);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
nsdefine('GROUP_READ', 2);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
nsdefine('GROUP_READ_COMMENT', 3);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 */
nsdefine('GROUP_READ_WRITE', 4);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 *  and can edit the group's wiki
 */
nsdefine('GROUP_READ_WIKI', 5);
/**
 * Indicates a group where people can't up and down vote threads
 */
nsdefine("NON_VOTING_GROUP", 0);
/**
 * Indicates a group where people can vote up threads (but not down)
 */
nsdefine("UP_VOTING_GROUP", 1);
/**
 * Indicates a group where people can vote up and down threads
 */
nsdefine("UP_DOWN_VOTING_GROUP", 2);
/**
 *  Typical posts to a group feed are on user created threads and
 *  so are of this type
 */
nsdefine('STANDARD_GROUP_ITEM', 0);
/**
 *  Indicates the thread was created to go alongside the creation of a wiki
 *  page so that people can discuss the pages contents
 */
nsdefine('WIKI_GROUP_ITEM', 1);
/**
 *  Indicates the thread was created to filter items from search results
 */
nsdefine('SEARCH_FILTER_GROUP_ITEM', 2);
/**
 *  Indicates the thread was created to edit items in search results
 */
nsdefine('SEARCH_EDIT_GROUP_ITEM', 3);
/**
 *  Indicates the thread was created to edit a query => url_list item
 */
nsdefine('QUERY_MAP_GROUP_ITEM', 4);
/**
 *  Used to record that a page belongs to the standard category
 */
nsdefine('WIKI_STANDARD_LINK', -1);
/**
 *  Used to record that a page belongs to the template category
 */
nsdefine('WIKI_TEMPLATE_LINK', -2);
/**
 *  Controls whether the BulkEmailJob is used to send mails from the
 *  Yioop instance or if mails are sent from the web app.
 */
nsconddefine('SEND_MAIL_MEDIA_UPDATER', false);
/**
 * Impression type used to record one view of a thread
 */
nsdefine('THREAD_IMPRESSION', 1);
/**
 * Impression type used to record one view of a wiki page
 */
nsdefine('WIKI_IMPRESSION', 2);
/**
 * Impression type used to record one thread or wiki page view in a group
 */
nsdefine('GROUP_IMPRESSION', 3);
/**
 * Impression type used to record one search query view
 */
nsdefine('QUERY_IMPRESSION', 4);
/**
 * Number of ITEM_RECOMMENDATIONs to suggest to a user
 */
nsdefine('MAX_RECOMMENDATIONS', 3);
/**
 * Type used to indicate ITEM_RECOMMENDATION score is about a trending thread
 */
nsdefine('TRENDING_RECOMMENDATION', 1);
/**
 * Type used to indicate ITEM_RECOMMENDATION score is about a thread
 */
nsdefine('THREAD_RECOMMENDATION', 2);
/**
 * Type used to indicate ITEM_RECOMMENDATION score is about a group
 */
nsdefine('GROUP_RECOMMENDATION', 3);
/**
 * Used to control update frequency of impression analytic data when
 * media updater in use
 */
nsconddefine("ANALYTICS_UPDATE_INTERVAL", ONE_HOUR / 6);
/** Value of epsilon in differential privacy formula */
nsconddefine('PRIVACY_EPSILON', 0.01);
/** Flag to turn on/off search impression recording */
nsconddefine('SEARCH_ANALYTICS_MODE', true);
/** Flag to turn on/off group impression recording */
nsconddefine('GROUP_ANALYTICS_MODE', true);
/** Flag to turn on/off differential privacy */
nsconddefine('DIFFERENTIAL_PRIVACY', false);
/** Number of trending feed item results to compute */
nsconddefine('NUM_TRENDING', 50);
/*
 * Database Field Sizes
 */
/* Length for names of things like first name, last name, etc */
nsdefine('NAME_LEN', 32);
/** Used for lengths of media sources, passwords, and emails */
nsdefine('LONG_NAME_LEN', 64);
/** Length for names of things like group names, etc */
nsdefine('SHORT_TITLE_LEN', 128);
/** Length for names of things like titles of blog entries, etc */
nsdefine('TITLE_LEN', 512);
/** Length of a feed item or post, etc */
nsdefine('MAX_GROUP_POST_LEN', 8192);
/** Length for for the contents of a wiki_page */
nsdefine('MAX_GROUP_PAGE_LEN', 524288);
/** Length for base 64 encode timestamps */
nsdefine('TIMESTAMP_LEN', 11);
/** Length for timestamps down to microseconds */
nsdefine('MICROSECOND_TIMESTAMP_LEN', 20);
/** Length for a CAPTCHA */
nsdefine('CAPTCHA_LEN', 6);
/** Length for a number field */
nsdefine('MAX_IP_ADDRESS_AS_STRING_LEN', 39);
/** Length for a number field */
nsdefine('NUM_FIELD_LEN', 4);
/** Length for writing mode in locales */
nsdefine('WRITING_MODE_LEN', 5);
/** Max user session size */
nsdefine('MAX_USER_SESSION_SIZE', 16384);
/*
 * Adjustable CHAT BOT RELATED defines
 */
/** Maximum number of patterns that can be used in a chat bot */
nsconddefine('MAX_BOT_PATTERNS', 50);
/** Maximum number chat bots that will be listened to in a group.
 * Beyond this number additional bots will be ignored. I.e., they
 * won't get a chance to answer posts
 */
nsconddefine('GROUP_BOT_FOLLOWERS', 10);
/*
 * Adjustable AD RELATED defines
 *
 /** Truncate length for ad description and keywords*/
nsdefine('ADVERTISEMENT_TRUNCATE_LEN', 8);
/** Initial bid amount for advertisement keyword */
nsconddefine('AD_KEYWORD_INIT_BID', 1);
/** Allows the root account to purchase free ad credits. Might
 *  mess up the value of credits if allow. This only makes a difference
 *  in the presence of an ad processing script
 */
nsconddefine('ALLOW_FREE_ROOT_CREDIT_PURCHASE', false);
/** advertisement date format for start date and end date*/
nsconddefine('AD_DATE_FORMAT','Y-m-d');
/** advertisement logo*/
nsconddefine('AD_LOGO','resources/adv-logo.png');
/** sentence compression enabled or not*/
nsconddefine('SENTENCE_COMPRESSION_ENABLED', false);
/** The number of rows to be used in bulk insert from Lexicon */
nsconddefine('NUM_LEX_BULK_INSERTS',100000);
