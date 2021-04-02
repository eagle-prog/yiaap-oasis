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
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\models\datasources\DatasourceManager;
use seekquarry\yioop\models\datasources\Sqlite3Manager;

/**
 * This is class is used to handle
 * getting and saving the Profile.php of the current search engine instance
 *
 * @author Chris Pollett
 */
class ProfileModel extends Model
{
    /**
     * These are fields whose values might be set in a Yioop instance
     * Profile.php file
     * @var array
     */
    public $profile_fields = ['AD_LOCATION', 'API_ACCESS', 'AUTH_KEY',
        'AUTOLOGOUT', 'AUXILIARY_CSS', 'BACKGROUND_COLOR', 'BACKGROUND_IMAGE',
        'CACHE_LINK', 'CAPTCHA_MODE', 'CONFIGURE_BOT', 'COOKIE_LIFETIME',
        'CSRF_TOKEN', 'DEBUG_LEVEL', 'DESCRIPTION_WEIGHT',
        'DB_HOST', 'DBMS', 'DB_NAME', 'DB_PASSWORD', 'DB_USER',
        'DEFAULT_LOCALE', 'DIFFERENTIAL_PRIVACY',
        'FAVICON', 'FOREGROUND_COLOR', 'GLOBAL_ADSCRIPT', 'GROUP_ITEM',
        'GROUP_ANALYTICS_MODE', 'IN_LINK', 'IP_LINK', 'LANDING_PAGE',
        'LINK_WEIGHT', 'LOGO_SMALL', 'LOGO_MEDIUM', 'LOGO_LARGE',
        'MAIL_PASSWORD',  'MAIL_SECURITY',
        'MAIL_SENDER', 'MAIL_SERVER', 'MAIL_SERVERPORT', 'MAIL_USERNAME',
        'MIN_RESULTS_TO_GROUP',  'MONETIZATION_TYPE',
        'MEDIA_MODE', 'NAME_SERVER', 'PRIVATE_DB_NAME', 'PRIVATE_DB_HOST',
        'PRIVATE_DBMS', 'PRIVATE_DB_PASSWORD', 'PRIVATE_DB_USER',
        'PROXY_SERVERS', 'RECOVERY_MODE', 'REGISTRATION_TYPE', 'RESULT_SCORE',
        'ROBOT_INSTANCE','RSS_ACCESS', 'SEARCH_ANALYTICS_MODE',
        'SEARCHBAR_PATH', 'SEND_MAIL_MEDIA_UPDATER',
        'SESSION_NAME', 'SIDE_ADSCRIPT', 'SIDEBAR_COLOR', 'SIGNIN_LINK',
        'SIMILAR_LINK', 'SUBSEARCH_LINK', 'TIMEZONE', 'TITLE_WEIGHT',
        'TOPBAR_COLOR', 'TOP_ADSCRIPT','TOR_PROXY', 'USE_FILECACHE',
        'USE_MAIL_PHP', 'USE_PROXY', 'USER_AGENT_SHORT', 'WEB_URI',
        'WEB_ACCESS', 'WORD_SUGGEST'
        ];
    /**
     * Profile fields which are stored in wiki or in a flat file
     * @var array
     */
    public $file_fields = ["AUXILIARY_CSS", "ROBOT_DESCRIPTION"];
    /**
     * Associative array (table_name => SQL statement to create that table
     * for the pubblic database)
     * List is alphabetical and contains all Yioop tables. List is only
     * initialized after an @see initializeSql call.
     * @var array
     */
    public $create_statements;
    /**
     * Associative array (table_name => SQL statement to create that table
     * for the private database)
     * List is alphabetical and contains all Yioop tables. List is only
     * initialized after an @see initializeSql call.
     * @var array
     */
    public $private_create_statements;
    /**
     * {@inheritDoc}
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    public function __construct($db_name = C\DB_NAME, $connect = true)
    {
        parent::__construct($db_name, $connect);
        $this->create_statements = [];
        $this->private_create_statements = [];
    }
    /**
     * Used to construct $this->create_statements, the list of all SQL
     * CREATE statements needed to build a Yioop database
     *
     * @param object $dbm a datasource_manager object used to get strings
     *     for autoincrement and serial types for a given db
     * @param array $dbinfo connect info for the database, also used in
     *     getting autoincrement and serial types
     */
    public function initializeSql($dbm, $dbinfo)
    {
        $auto_increment = $dbm->autoIncrement($dbinfo);
        $serial = $dbm->serialType($dbinfo);
        $integer = $dbm->integerType($dbinfo);
        $page_type = $dbm->pageType($dbinfo);
        $scraper_factor = (stristr($dbinfo['DBMS'], "mysql") !== false) ? 4: 10;
        /**
         * SQL statements used to create the Yioop database. Some of the
         * these statements could use UNIQUE on some the columns that are
         * later used in CREATE INDEX statements. However, because of
         * restrictions on the number of bytes (not chars) in MYSQL for keys
         * this has not been done.
         */
        $this->create_statements = [
            "ACTIVE_PROCESS" =>
                "CREATE TABLE ACTIVE_PROCESS (NAME VARCHAR(".C\NAME_LEN.
                "), ID $integer, TYPE VARCHAR(" . C\NAME_LEN . "))",
            "ADVERTISEMENT" => "CREATE TABLE ADVERTISEMENT (ID
                $serial PRIMARY KEY $auto_increment, USER_ID $integer,
                NAME VARCHAR(". C\ADVERTISEMENT_NAME_LEN ."),
                DESCRIPTION VARCHAR(". C\ADVERTISEMENT_TEXT_LEN ."),
                DESTINATION VARCHAR(". C\ADVERTISEMENT_DESTINATION_LEN ."),
                KEYWORDS VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
                STATUS $integer, BUDGET $integer, CLICKS $integer,
                IMPRESSIONS $integer, START_DATE VARCHAR(".
                C\ADVERTISEMENT_DATE_LEN ."), END_DATE VARCHAR(".
                C\ADVERTISEMENT_DATE_LEN ."))",
            "ACCEPTED_AD_BIDS" => "CREATE TABLE ACCEPTED_AD_BIDS
                (AD_ID $integer, KEYWORD VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN
                ."), BID_AMOUNT $integer, BID_DATE VARCHAR(".
                C\ADVERTISEMENT_DATE_LEN ."))",
            "AAD_KEYWORD_DATE_INDEX" => "CREATE INDEX
                AAD_KEYWORD_DATE_INDEX ON ACCEPTED_AD_BIDS(KEYWORD, BID_DATE)",
            "ACTIVITY" => "CREATE TABLE ACTIVITY (ACTIVITY_ID $serial
                PRIMARY KEY $auto_increment, TRANSLATION_ID $integer,
                METHOD_NAME VARCHAR(" . C\LONG_NAME_LEN . "))",
            "ACTIVITY_TRANSLATION_ID_INDEX" => "CREATE INDEX
                ACTIVITY_TRANSLATION_ID_INDEX ON ACTIVITY (TRANSLATION_ID)",
            "CHAT_BOT" => "CREATE TABLE CHAT_BOT(
                USER_ID $integer PRIMARY KEY, BOT_TOKEN CHAR(". C\TIMESTAMP_LEN .
                ") UNIQUE, CALLBACK_URL VARCHAR(" . C\MAX_URL_LEN . "))",
            "CHAT_BOT_PATTERN" => "CREATE TABLE CHAT_BOT_PATTERN(
                PATTERN_ID $serial PRIMARY KEY $auto_increment,
                USER_ID  $integer,
                REQUEST VARCHAR(" . C\MAX_DESCRIPTION_LEN ."),
                TRIGGER_STATE VARCHAR(" . C\NAME_LEN . "),
                REMOTE_MESSAGE VARCHAR(" . C\MAX_DESCRIPTION_LEN . "),
                RESULT_STATE VARCHAR(" . C\NAME_LEN . "),
                RESPONSE VARCHAR(" . C\MAX_DESCRIPTION_LEN ."))",
            "CRAWL_MIXES" => "CREATE TABLE CRAWL_MIXES (TIMESTAMP NUMERIC(".
                C\TIMESTAMP_LEN.")
                PRIMARY KEY, NAME VARCHAR(" . C\NAME_LEN .
                "), OWNER_ID $integer,
                PARENT NUMERIC(" . C\TIMESTAMP_LEN . "))",
            "CM_OWNER_ID_INDEX" => "CREATE INDEX CM_OWNER_ID_INDEX ON
                CRAWL_MIXES (OWNER_ID)",
            "CM_PARENT_INDEX" => "CREATE INDEX CM_PARENT_INDEX ON
                CRAWL_MIXES (PARENT)",
            "CREDIT_LEDGER" => "CREATE TABLE CREDIT_LEDGER
                (USER_ID $integer, AMOUNT $integer, TYPE VARCHAR(" .
                C\NAME_LEN . "), BALANCE $integer, TIMESTAMP NUMERIC(" .
                C\TIMESTAMP_LEN . "))",
            "CL_USER_INDEX" => "CREATE INDEX CL_USER_INDEX ON
                CREDIT_LEDGER (USER_ID)",
            "CURRENT_WEB_INDEX" => "CREATE TABLE CURRENT_WEB_INDEX
                (CRAWL_TIME NUMERIC(" . C\TIMESTAMP_LEN . ") PRIMARY KEY)",
            "GROUP_ITEM" => "CREATE TABLE GROUP_ITEM (ID $serial PRIMARY KEY
                $auto_increment, PARENT_ID $integer, GROUP_ID $integer,
                USER_ID $integer, URL VARCHAR(" . C\TITLE_LEN
                .") DEFAULT '', TITLE VARCHAR(" . C\TITLE_LEN
                ."), DESCRIPTION VARCHAR(".
                C\MAX_GROUP_POST_LEN . "), PUBDATE NUMERIC(" .
                C\TIMESTAMP_LEN . "),
                EDIT_DATE NUMERIC(" . C\TIMESTAMP_LEN . "),
                UPS $integer DEFAULT 0, DOWNS $integer DEFAULT 0,
                TYPE $integer DEFAULT " . C\STANDARD_GROUP_ITEM . ")",
            "GI_GROUP_ID_INDEX" => "CREATE INDEX GI_GROUP_ID_INDEX ON
                GROUP_ITEM (GROUP_ID)",
            "GI_USER_ID_INDEX" => "CREATE INDEX GI_USER_ID_INDEX ON
                GROUP_ITEM (USER_ID)",
            "GI_PARENT_ID_INDEX" => "CREATE INDEX GI_PARENT_ID_INDEX ON
                GROUP_ITEM (PARENT_ID)",
            "GROUP_ITEM_VOTE" => "CREATE TABLE GROUP_ITEM_VOTE(
                USER_ID $integer, ITEM_ID $integer)",
            "GROUP_PAGE" => "CREATE TABLE GROUP_PAGE (
                ID $serial PRIMARY KEY $auto_increment, GROUP_ID $integer,
                DISCUSS_THREAD $integer, TITLE VARCHAR(" . C\TITLE_LEN . "),
                PAGE $page_type, LOCALE_TAG VARCHAR(" . C\NAME_LEN . "))",
            "GP_ID_INDEX" => "CREATE INDEX GP_ID_INDEX ON GROUP_PAGE
                 (GROUP_ID, TITLE, LOCALE_TAG)",
            "GROUP_PAGE_HISTORY" => "CREATE TABLE GROUP_PAGE_HISTORY(
                PAGE_ID $integer, GROUP_ID $integer, EDITOR_ID $integer,
                TITLE VARCHAR(" . C\TITLE_LEN . "), PAGE  $page_type,
                EDIT_COMMENT VARCHAR(" . C\SHORT_TITLE_LEN .
                "), LOCALE_TAG VARCHAR(" . C\NAME_LEN . "),
                PUBDATE NUMERIC(" . C\TIMESTAMP_LEN . "),
                PRIMARY KEY(PAGE_ID, PUBDATE))",
            "GROUP_PAGE_LINK" => "CREATE TABLE GROUP_PAGE_LINK(
                LINK_TYPE_ID $integer, FROM_ID $integer,
                TO_ID $integer,
                PRIMARY KEY(LINK_TYPE_ID, FROM_ID, TO_ID))",
            "GROUP_PAGE_PRE_LINK" => "CREATE TABLE GROUP_PAGE_PRE_LINK(
                LINK_TYPE_ID $integer, FROM_ID $integer,
                TO_GROUP_ID $integer, TO_PAGE_NAME VARCHAR(" . C\TITLE_LEN .
                "), PRIMARY KEY(LINK_TYPE_ID, FROM_ID, TO_GROUP_ID,
                TO_PAGE_NAME))",
            "GPP_ID_INDEX" => "CREATE INDEX GP_PRE_INDEX ON GROUP_PAGE_PRE_LINK
                 (TO_GROUP_ID, TO_PAGE_NAME)",
            "GROUPS" => "CREATE TABLE GROUPS (
                GROUP_ID $serial PRIMARY KEY $auto_increment,
                GROUP_NAME VARCHAR(" . C\SHORT_TITLE_LEN
                ."), CREATED_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN . "),
                OWNER_ID $integer, REGISTER_TYPE $integer,
                MEMBER_ACCESS $integer, VOTE_ACCESS $integer DEFAULT ".
                C\NON_VOTING_GROUP . ", POST_LIFETIME $integer DEFAULT ".
                C\FOREVER . ", ENCRYPTION $integer DEFAULT 0)",
            /* NOTE: We are not using singular name GROUP for GROUPS as
               GROUP is a reserved SQL keyword
             */
            "GRP_OWNER_ID_INDEX" => "CREATE INDEX GRP_OWNER_ID_INDEX ON
                GROUPS (OWNER_ID)",
            "GRP_MEMBER_ACCESS_INDEX" => "CREATE INDEX GRP_MEMBER_ACCESS_INDEX
                ON GROUPS(MEMBER_ACCESS)",
            "ITEM_IMPRESSION" => "CREATE TABLE ITEM_IMPRESSION(
                USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
                VIEW_DATE NUMERIC(" . C\TIMESTAMP_LEN . "))",
            "ITEM_IMPRESSION_STAT" => "CREATE TABLE ITEM_IMPRESSION_STAT(
                ITEM_ID $integer, ITEM_TYPE $integer, UPDATE_PERIOD $integer,
                NUM_VIEWS $integer DEFAULT -1,
                FUZZY_NUM_VIEWS $integer DEFAULT -1)",
            "ITEM_IMPRESSION_SUMMARY" => "CREATE TABLE ITEM_IMPRESSION_SUMMARY(
                USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
                UPDATE_PERIOD $integer,
                UPDATE_TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
                NUM_VIEWS $integer, FUZZY_NUM_VIEWS $integer DEFAULT -1,
                TMP_NUM_VIEWS $integer DEFAULT -1,
                PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
                UPDATE_PERIOD, UPDATE_TIMESTAMP))",
            "ITEM_RECOMMENDATION" => "CREATE TABLE ITEM_RECOMMENDATION (
                ITEM_ID $integer, USER_ID $integer, ITEM_TYPE $integer,
                SCORE FLOAT, TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "))",
            "IR_USER_ID_INDEX" => "CREATE INDEX IR_USER_ID_INDEX ON
                ITEM_RECOMMENDATION(USER_ID)",
            "ITEM_TERM_FREQUENCY"=> "CREATE TABLE ITEM_TERM_FREQUENCY
                (ITEM_ID $integer, TERM_ID $integer, FREQUENCY $integer,
                LOG_FREQUENCY FLOAT, PRIMARY KEY(ITEM_ID, TERM_ID))",
            "ITF_TERM_ID_INDEX" => "CREATE INDEX ITF_TERM_ID_INDEX ON
                ITEM_TERM_FREQUENCY(TERM_ID)",
            "ITEM_TERM_WEIGHTS"=> "CREATE TABLE ITEM_TERM_WEIGHTS (
                TERM_ID $integer, ITEM_ID $integer, WEIGHT FLOAT,
                PRIMARY KEY(TERM_ID, ITEM_ID))",
            "LOCALE" => "CREATE TABLE LOCALE(LOCALE_ID $serial PRIMARY KEY
                $auto_increment, LOCALE_TAG VARCHAR(" . C\NAME_LEN . "),
                LOCALE_NAME VARCHAR(" . C\LONG_NAME_LEN .
                "), WRITING_MODE CHAR(" .
                C\WRITING_MODE_LEN."), ACTIVE $integer DEFAULT 1)",
            "LCL_LOCALE_TAG_INDEX" => "CREATE INDEX LCL_LOCALE_TAG_INDEX ON
                LOCALE(LOCALE_TAG)",
            "MACHINE" => "CREATE TABLE MACHINE (NAME VARCHAR(" . C\NAME_LEN
                .") PRIMARY KEY,
                URL VARCHAR(" . C\MAX_URL_LEN .
                "), CHANNEL $integer,
                NUM_FETCHERS $integer, PARENT VARCHAR(" . C\NAME_LEN . ") )",
            "MEDIA_SOURCE" => "CREATE TABLE MEDIA_SOURCE (
                TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . ") PRIMARY KEY,
                NAME VARCHAR(" . C\LONG_NAME_LEN . "),
                TYPE VARCHAR(" . C\NAME_LEN . "),
                CATEGORY VARCHAR(" . C\NAME_LEN . ") DEFAULT 'NEWS',
                SOURCE_URL VARCHAR(" . C\MAX_URL_LEN . "), AUX_INFO VARCHAR(".
                C\MAX_URL_LEN . "), LANGUAGE VARCHAR(" . C\NAME_LEN . "))",
            "MS_TYPE_INDEX" => "CREATE INDEX MS_TYPE_INDEX ON
                MEDIA_SOURCE(TYPE)",
            "MIX_COMPONENTS" => "CREATE TABLE MIX_COMPONENTS (
                TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
                FRAGMENT_ID $integer,
                CRAWL_TIMESTAMP NUMERIC(".C\TIMESTAMP_LEN."), WEIGHT FLOAT,
                DIRECTION $integer, KEYWORDS VARCHAR(" . C\TITLE_LEN . "),
                PRIMARY KEY(TIMESTAMP, FRAGMENT_ID, CRAWL_TIMESTAMP) )",
            "MIX_FRAGMENTS" => "CREATE TABLE MIX_FRAGMENTS (
                TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),FRAGMENT_ID $integer,
                RESULT_BOUND $integer, PRIMARY KEY(TIMESTAMP, FRAGMENT_ID))",
            "PAGE_RELATIONSHIP" => "CREATE TABLE PAGE_RELATIONSHIP (
                ID $serial PRIMARY KEY $auto_increment, NAME VARCHAR(" .
                C\NAME_LEN . ") UNIQUE)",
            "QUERY_ITEM" => "CREATE TABLE QUERY_ITEM (ID $serial PRIMARY KEY
                $auto_increment, QUERY_HASH CHAR(". C\TIMESTAMP_LEN .
                ") UNIQUE, QUERY VARCHAR(" . C\MAX_QUERY_LEN."),
                CREATION NUMERIC(" . C\TIMESTAMP_LEN . "))",
            "QI_GROUP_ID_INDEX" => "CREATE INDEX QI_QUERY_HASH_INDEX ON
                QUERY_ITEM (QUERY_HASH)",
            "ROLE" => "CREATE TABLE ROLE (
                ROLE_ID $serial PRIMARY KEY $auto_increment, NAME VARCHAR(".
                C\NAME_LEN."))",
            "ROLE_ACTIVITY" => "CREATE TABLE ROLE_ACTIVITY (ROLE_ID $integer,
                ACTIVITY_ID $integer,
                ALLOWED_ARGUMENTS VARCHAR(" . C\MAX_URL_LEN . ") DEFAULT 'all',
                PRIMARY KEY(ROLE_ID, ACTIVITY_ID))",
            "SCRAPER" =>
                "CREATE TABLE SCRAPER (ID $serial PRIMARY KEY
                $auto_increment, NAME VARCHAR(" . C\TITLE_LEN . "),
                PRIORITY $integer DEFAULT 0,
                SIGNATURE VARCHAR(" . C\MAX_URL_LEN . "),
                TEXT_PATH VARCHAR(" . C\MAX_URL_LEN . ") DEFAULT '',
                DELETE_PATHS VARCHAR(" . ($scraper_factor * C\MAX_URL_LEN) .
                ") DEFAULT '', EXTRACT_FIELDS VARCHAR(" .
                ($scraper_factor * C\MAX_URL_LEN) . ") DEFAULT '')",
            "SUBSEARCH" => "CREATE TABLE SUBSEARCH (
                LOCALE_STRING VARCHAR(" . C\LONG_NAME_LEN . ") PRIMARY KEY,
                FOLDER_NAME VARCHAR(" . C\NAME_LEN."),
                INDEX_IDENTIFIER CHAR(" . (strlen('m:') + C\TIMESTAMP_LEN) . "),
                PER_PAGE $integer,
                DEFAULT_QUERY VARCHAR(" . C\TITLE_LEN . ") DEFAULT ''
                )",
            "TRENDING_TERM" => "CREATE TABLE TRENDING_TERM (
                TERM VARCHAR(" . C\TITLE_LEN . "),
                OCCURRENCES FLOAT DEFAULT 0,
                CATEGORY VARCHAR(" . C\TITLE_LEN . ") DEFAULT 'news',
                UPDATE_PERIOD NUMERIC,
                TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
                LANGUAGE VARCHAR(" . C\NAME_LEN . ")
                )",
            "TRANSLATION" => "CREATE TABLE TRANSLATION (
                TRANSLATION_ID $serial PRIMARY KEY
                $auto_increment, IDENTIFIER_STRING VARCHAR(" . C\TITLE_LEN
                ."))",
            "TRANS_IDENTIFIER_STRING_INDEX" => "CREATE INDEX
                TRANS_IDENTIFIER_STRING_INDEX ON
                TRANSLATION(IDENTIFIER_STRING)",
            "TRANSLATION_LOCALE" => "CREATE TABLE TRANSLATION_LOCALE
                (TRANSLATION_ID $integer, LOCALE_ID $integer,
                TRANSLATION VARCHAR(" . C\MAX_GROUP_POST_LEN."),
                PRIMARY KEY(TRANSLATION_ID, LOCALE_ID))",
            "USERS" => "CREATE TABLE USERS(USER_ID $serial PRIMARY KEY
                $auto_increment, FIRST_NAME VARCHAR(" . C\NAME_LEN."),
                LAST_NAME VARCHAR(" . C\NAME_LEN . "), USER_NAME VARCHAR(" .
                C\NAME_LEN .") UNIQUE, EMAIL VARCHAR(" . C\LONG_NAME_LEN . "),
                PASSWORD VARCHAR(" . C\LONG_NAME_LEN . "), STATUS $integer,
                HASH VARCHAR(" . C\LONG_NAME_LEN . "),
                CREATION_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN .
                "), UPS $integer DEFAULT 0, DOWNS $integer DEFAULT 0)",
            "USRS_USER_NAME_INDEX" => "CREATE INDEX USRS_USER_NAME_INDEX ON
                USERS(USER_NAME)",
            "USER_GROUP" => "CREATE TABLE USER_GROUP (USER_ID $integer,
                GROUP_ID $integer, STATUS $integer,
                JOIN_DATE NUMERIC(" . C\TIMESTAMP_LEN . "),
                PRIMARY KEY (GROUP_ID, USER_ID) )",
            "USER_ROLE" => "CREATE TABLE USER_ROLE (USER_ID $integer,
                ROLE_ID $integer, PRIMARY KEY (ROLE_ID, USER_ID))",
            "USER_SESSION" => "CREATE TABLE USER_SESSION (
                USER_ID $integer PRIMARY KEY, SESSION VARCHAR(".
                C\MAX_USER_SESSION_SIZE . "))",
            "USER_ITEM_SIMILARITY" => "CREATE TABLE USER_ITEM_SIMILARITY
                (USER_ID $integer, THREAD_ID $integer, SIMILARITY FLOAT,
                 GROUP_MEMBER $integer,
                PRIMARY KEY(USER_ID, THREAD_ID))",
            "USER_TERM_FREQUENCY"=>"CREATE TABLE USER_TERM_FREQUENCY
                (USER_ID $integer, TERM_ID $integer, FREQUENCY $integer,
                 LOG_FREQUENCY FLOAT, PRIMARY KEY(USER_ID, TERM_ID))",
            "UTF_TERM_ID_INDEX" => "CREATE INDEX UTF_TERM_ID_INDEX ON
                 USER_TERM_FREQUENCY(TERM_ID)",
            "USER_TERM_WEIGHTS"=>"CREATE TABLE USER_TERM_WEIGHTS
                (TERM_ID $integer, USER_ID $integer, WEIGHT FLOAT,
                PRIMARY KEY(TERM_ID, USER_ID))",
            "VISITOR" => "CREATE TABLE VISITOR(ADDRESS VARCHAR(".
                C\MAX_IP_ADDRESS_AS_STRING_LEN . "),
                PAGE_NAME VARCHAR(" . C\NAME_LEN . "),
                END_TIME $integer, DELAY $integer, FORGET_AGE $integer,
                ACCESS_COUNT $integer,
                PRIMARY KEY(ADDRESS, PAGE_NAME))",
            "VERSION" => "CREATE TABLE VERSION(ID $integer PRIMARY KEY)",
            ];
    }
    /**
     * Used to construct $this->private_create_statements, the list of all SQL
     * CREATE statements needed to build Yioop private database
     *
     * @param object $dbm a datasource_manager object used to get strings
     *     for autoincrement and serial types for a given db
     * @param array $dbinfo connect info for the database, also used in
     *     getting autoincrement and serial types
     */
    public function initializeSqlPrivate($dbm, $dbinfo)
    {
        $auto_increment = $dbm->autoIncrement($dbinfo);
        $serial = $dbm->serialType($dbinfo);
        $integer = $dbm->integerType($dbinfo);
        /**
         * SQL statements used to create the Yioop database. Some of the
         * these statements could use UNIQUE on some the columns that are
         * later used in CREATE INDEX statements. However, because of
         * restrictions on the number of bytes (not chars) in MYSQL for keys
         * this has not been done. AES 256 = 8*32bits long. So need
         * only 32 bytes to store a key. We give up to 64 (LONG_NAME_LEN)
         * as base 64 encoding keys to keep postgres happy when using varchhar
         */
        $this->private_create_statements = ["TYPE_KEYS" =>
            "CREATE TABLE TYPE_KEYS (KEY_ID $serial PRIMARY KEY " .
            "$auto_increment, TYPE_ID $integer, KEY_NAME VARCHAR(" .
            C\LONG_NAME_LEN . "))"];
    }
    /**
     * Creates a folder to be used to maintain local information about this
     * instance of the Yioop/SeekQuarry engine
     *
     * Creates the directory provides as well as subdirectories for crawls,
     * locales, logging, and sqlite DBs.
     *
     * @param string $directory parth and name of directory to create
     */
    public function makeWorkDirectory($directory)
    {
        $to_make_dirs = [$directory, "$directory/app",
            "$directory/archives", "$directory/cache",
            "$directory/classifiers", "$directory/data",
            "$directory/data/domain_filters",
            "$directory/app/locale", "$directory/log", "$directory/prepare",
            "$directory/schedules", "$directory/temp"];
        $dir_status = [];
        foreach ($to_make_dirs as $dir) {
            $dir_status[$dir] = $this->createIfNecessaryDirectory($dir);
            if ($dir_status[$dir] < 0) {
                return false;
            }
        }
        if ($dir_status["$directory/app/locale"] == 1) {
            $this->db->copyRecursive(C\BASE_DIR."/locale",
                "$directory/app/locale");
        }
        if ($dir_status["$directory/data"] == 1) {
            $this->db->copyRecursive(C\BASE_DIR . "/data", "$directory/data");
        }
        return true;
    }
    /**
     * Outputs a Profile.php  file in the given directory containing profile
     * data based on new and old data sources
     *
     * This function creates a Profile.php file if it doesn't exist. A given
     * field is output in the profile
     * according to the precedence that a new value is preferred to an old
     * value is prefered to the value that comes from a currently defined
     * constant. It might be the case that a new value for a given field
     * doesn't exist, etc.
     *
     * @param string $directory the work directory to output the Profile.php
     *     file
     * @param array $new_profile_data fields and values containing at least
     *     some profile information (only $this->profile_fields
     * fields of $new_profile_data will be considered).
     * @param array $old_profile_data fields and values that come from
     *     presumably a previously existing profile
     * @param bool $reset whether the new profile data is coming from a reset
     *     to factory settings or not
     */
    public function updateProfile($directory, $new_profile_data,
        $old_profile_data, $reset = false)
    {
        $n = [];
        $n[] = <<<EOT
<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009-2012  Chris Pollett chris@pollett.org
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
 * @copyright 2009-2012
 * @filesource
 */
namespace seekquarry\yioop\configs;

/**
 * Computer generated file giving the key defines of directory locations
 * as well as database settings used to run the SeekQuarry/Yioop search engine
 */
EOT;
        $base_url = C\NAME_SERVER;
        if (C\nsdefined("BASE_URL")) {
            $base_url = C\BASE_URL;
        }
        //make sure certain fields are not null
        $not_null_fields = [
            'AD_LOCATION' => 'none',
            'BACKGROUND_COLOR' => "#FFF",
            'COOKIE_LIFETIME' => C\ONE_YEAR,
            'CSRF_TOKEN' => "YIOOP_TOKEN",
            'FAVICON' => "favicon.ico",
            'FOREGROUND_COLOR' => "#FFF",
            'LOGO_SMALL' => "resources/yioop-small.png",
            'LOGO_MEDIUM' => "resources/yioop-medium.png",
            'LOGO_LARGE' => "resources/yioop-large.png",
            'MEDIA_MODE' => "name_server",
            'SESSION_NAME' => "yioopbiscuit",
            'SIDEBAR_COLOR' => "#F8F8F8",
            'TIMEZONE' => 'America/Los_Angeles',
            'TOPBAR_COLOR' => "#F5F5FF",
        ];
        $not_null_keys = array_keys($not_null_fields);
        $file_fields = $this->file_fields;
        //now integrate the different profiles
        foreach ($this->profile_fields as $field) {
            if (isset($new_profile_data[$field])) {
                if (!$reset && in_array($field,
                    ['LOGO_SMALL', 'LOGO_MEDIUM', 'LOGO_LARGE',
                    'FAVICON', 'SEARCHBAR_PATH',
                    'BACKGROUND_IMAGE'])) {
                    if (isset($new_profile_data[$field]['name']) &&
                        isset($new_profile_data[$field]['tmp_name'])) {
                        if (empty($new_profile_data[$field]['data'])) {
                            move_uploaded_file(
                            $new_profile_data[$field]['tmp_name'], C\APP_DIR .
                            "/resources/". $new_profile_data[$field]['name']);
                        } else {
                            file_put_contents(C\APP_DIR . "/resources/".
                                $new_profile_data[$field]['name'],
                                $new_profile_data[$field]['data']);
                        }
                        if (C\REDIRECTS_ON) {
                            $profile[$field] = "wd/resources/" .
                                $new_profile_data[$field]['name'];
                        } else {
                            $profile[$field] =
                                "?c=resource&amp;a=get&amp;" .
                                "f=resources&amp;n=" .
                                $new_profile_data[$field]['name'];
                        }
                    } elseif (isset($old_profile_data[$field])) {
                        $profile[$field] = $old_profile_data[$field];
                    } elseif (nsdefined($field)) {
                        $profile[$field] =  constant(C\NS_CONFIGS .$field);
                    } else {
                        $profile[$field] = "";
                    }
                } else {
                    $profile[$field] = $new_profile_data[$field];
                }
            } elseif (isset($old_profile_data[$field])) {
                $profile[$field] = $old_profile_data[$field];
            } elseif (C\nsdefined($field)) {
                $profile[$field] = constant(C\NS_CONFIGS . $field);
            } else {
                $profile[$field] = "";
            }
            if (!$profile[$field] && isset($not_null_fields[$field])) {
                $profile[$field] = $not_null_fields[$field];
            }
            //don't let the database systems be unspecified
            if (empty($profile["DBMS"])) {
                $profile["DBMS"] = 'Sqlite3';
                $profile['DB_NAME'] = "public_default";
            }
            if (empty($profile["PRIVATE_DBMS"])) {
                $profile["PRIVATE_DBMS"] = 'Sqlite3';
                $profile['PRIVATE_DB_NAME'] = "private_default";
            }
            if ($field == "WEB_URI") {
                $profile[$field] = UrlParser::getPath(C\BASE_URL);
            }
            if (in_array($field, $file_fields)) {
                continue;
            }
            if ($field != "DEBUG_LEVEL") {
                $profile[$field] = "\"{$profile[$field]}\"";
            }
            $n[] = "nsdefine('$field', {$profile[$field]});";
        }
        $out = implode("\n", $n);
        if (file_put_contents($directory . C\PROFILE_FILE_NAME, $out)
            !== false) {
            set_error_handler(null);
            @chmod($directory.C\PROFILE_FILE_NAME, 0777);
            if (isset($new_profile_data['AUXILIARY_CSS'])) {
                if (!file_exists(C\APP_DIR . "/css")) {
                    @mkdir(C\APP_DIR . "/css");
                    @chmod(C\APP_DIR . "/css", 0777);
                }
                $css_file = C\APP_DIR . "/css/auxiliary.css";
                file_put_contents($css_file,
                    $new_profile_data['AUXILIARY_CSS']);
                @chmod($css_file, 0777);
            }
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            return true;
        }
        return false;
    }
    /**
     * Check if $dbinfo provided the connection details for a Yioop/SeekQuarry
     * database. If it does provide a valid db connection but no data then try
     * to recreate the database from the default copy stored in /data dir.
     *
     * @param array $dbinfo has fields for DBMS, DB_USER, DB_PASSWORD, DB_HOST
     *     and DB_NAME
     * @param array $skip_list an array of table or index names not to bother
     *     creating or copying
     * @return bool returns true if can connect to/create a valid database;
     *     returns false otherwise
     */
    public function migrateDatabaseIfNecessary($dbinfo, $skip_list = [])
    {
        $test_dbm = $this->testDatabaseManager($dbinfo);
        if ($test_dbm === false || $test_dbm === true) {
            return $test_dbm;
        }
        $this->initializeSql($test_dbm, $dbinfo);
        $copy_tables = array_diff(array_keys($this->create_statements),
            $skip_list);
        if (!($create_ok = $this->createDatabaseTables($test_dbm, $dbinfo))) {
            return false;
        }
        $default_dbm = new Sqlite3Manager();
        $default_dbm->connect("", "", "", C\BASE_DIR."/data/public_default.db");
        if (!$default_dbm) {
            return false;
        }
        foreach ($copy_tables as $table_or_index) {
            if ($table_or_index != "CURRENT_WEB_INDEX" &&
                stristr($table_or_index, "_INDEX")) {
                continue;
            }
            if (!DatasourceManager::copyTable($table_or_index, $default_dbm,
                $table_or_index, $test_dbm)) {
                return false;
            }
        }
        if (stristr($dbinfo["DB_HOST"], "pgsql") !== false) {
            /* For postgres count initial values of SERIAL sequences
               will be screwed up unless do
             */
            $auto_tables = ["ACTIVITY" =>"ACTIVITY_ID",
                "GROUP_ITEM" =>"ID",
                "GROUP_PAGE" => "ID",
                "GROUPS" => "GROUP_ID", "LOCALE"=> "LOCALE_ID",
                "ROLE" => "ROLE_ID", "TRANSLATION" => "TRANSLATION_ID",
                "USERS" => "USER_ID"];
            foreach ($auto_tables as $table => $auto_column) {
                $sql = "SELECT MAX($auto_column) AS NUM FROM $table";
                $result = $test_dbm->execute($sql);
                $row = $test_dbm->fetchArray($result);
                $next = $row['NUM'] ?? 1;
                $sequence = strtolower("{$table}_{$auto_column}_seq");
                $sql = "SELECT setval('$sequence', $next)";
                $test_dbm->execute($sql);
            }
        }
        return true;
    }
    /**
     * On a blank database this method create all the tables necessary for
     * Yioop less those on a skip list
     *
     * @param object $dbm a DatabaseManager open to some DBMS and with a
     *     blank database selected
     * @param array $dbinfo name of database, host, user, and password
     * @param array $skip_list an array of table or index names not to bother
     *     creating
     * @return bool whether all of the creates were successful or not
     */
    public function createDatabaseTables($dbm, $dbinfo, $skip_list = [])
    {
        $this->initializeSql($dbm, $dbinfo);
        $create_statements = $this->create_statements;
        foreach ($create_statements as $table_or_index => $statement) {
            if (in_array($table_or_index, $skip_list) || empty($statement)) {
                continue;
            }
            if (!$result = $dbm->execute($statement)) {
                echo $statement." ERROR!";
                return false;
            }
        }
        return true;
    }
    /**
     * On a blank database this method create all the tables necessary for
     * private Yioop less those on a skip list
     *
     * @param object $dbm a DatabaseManager open to some DBMS and with a
     *     blank database selected
     * @param array $dbinfo name of database, host, user, and password
     * @param array $skip_list an array of table or index names not to bother
     *     creating
     * @return bool whether all of the creates were successful or not
     */
    public function createDatabaseTablesPrivate($dbm, $dbinfo, $skip_list = [])
    {
        $this->initializeSqlPrivate($dbm, $dbinfo);
        $private_create_statements = $this->private_create_statements;
        foreach ($private_create_statements as $table_or_index => $statement) {
            if (in_array($table_or_index, $skip_list)) {
                continue;
            }
            if (!$result = $dbm->execute($statement)) {
                echo $statement . " ERROR!";
                return false;
            }
        }
        return true;
    }
    /**
     * Checks if $dbinfo provides info to connect to an working instance of
     * app db.
     *
     * @param array $dbinfo has field for DBMS, DB_USER, DB_PASSWORD, DB_HOST
     *     and DB_NAME
     * @return mixed returns true if can connect to DBMS with username and
     *     password, can select the given database name and that database
     *     seems to be of Yioop/SeekQuarry type. If the connection works
     *     but database isn't there it attempts to create it. If the
     *     database is there but no data, then it returns a resource for
     *     the database. Otherwise, it returns false.
     */
    public function testDatabaseManager($dbinfo)
    {
        if (!isset($dbinfo['DBMS'])) {
            return false;
        }
        $dbms_manager = C\NS_DATASOURCES . ucfirst($dbinfo['DBMS'])."Manager";
        $test_dbm = new $dbms_manager();
        $fields = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
        foreach ($fields as $field) {
            if (!isset($dbinfo[$field])) {
                $dbinfo[$field] = constant($field);
            }
        }
        $host = $dbinfo['DB_HOST'];
            // for postgres database needs to already exists
        $host = str_ireplace("database=".$dbinfo['DB_NAME'], "",
            $host); // informix, ibm (use connection string DSN)
        $host = str_replace(";;", ";", $host);
        $host = trim($host);
        $conn = $test_dbm->connect($host, $dbinfo['DB_USER'],
            $dbinfo['DB_PASSWORD'], "");
        if ($conn === false) {
            return false;
        }
        //check if can select db or if not create it
        $q = "";
        if (isset($test_dbm->special_quote)) {
            $q = $test_dbm->special_quote;
        }
        set_error_handler(null);
        @$test_dbm->execute("CREATE DATABASE $q".$dbinfo['DB_NAME']."$q");
        $test_dbm->disconnect();
        if (!$test_dbm->connect(
            $dbinfo['DB_HOST'], $dbinfo['DB_USER'],
            $dbinfo['DB_PASSWORD'], $dbinfo['DB_NAME'])) {
            return false;
        }
        /*  check if need to create db contents.
            We check if any locale exists as proxy for contents being okay.
            Temporarily disable more aggressive yioop error handler while do
            this
         */
        $sql = "SELECT LOCALE_ID FROM LOCALE";
        $result = $test_dbm->execute($sql);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
        if ($result !== false && $test_dbm->fetchArray($result) !== false) {
            return true;
        }
        return $test_dbm;
    }
    /**
     * Modifies the config.php file so the WORK_DIRECTORY define points at
     * $directory
     *
     * @param string $directory folder that WORK_DIRECTORY should be defined to
     */
    public function setWorkDirectoryConfigFile($directory)
    {
        $dir_value = "'$directory'";
        if ($directory == C\DEFAULT_WORK_DIRECTORY) {
            $dir_value = "DEFAULT_WORK_DIRECTORY";
        }
        $config = file_get_contents(C\BASE_DIR."/configs/Config.php");
        $start_machine_section = strpos($config,
            '/*+++ The next block of code');
        if ($start_machine_section === false) {
            return false;
        }
        $end_machine_section = strpos($config, '/*++++++*/');
        if ($end_machine_section === false) {
            return false;
        }
        $out = substr($config,  0, $start_machine_section);
        $out .= "/*+++ The next block of code is machine edited, change at\n".
            "your own risk, please use configure web page instead +++*/\n";
        $out .= "nsdefine('WORK_DIRECTORY', $dir_value);\n";
        $out .= substr($config, $end_machine_section);
        if (file_put_contents(C\BASE_DIR."/configs/Config.php", $out)) {
            return true;
        }
        return false;
    }
    /**
     * Reads a profile from a Profile.php file in the provided directory
     *
     * @param string $work_directory directory to look for profile in
     * @return array associate array of the profile fields and their values
     */
    public function getProfile($work_directory)
    {
        $profile = [];
        $profile_string = @file_get_contents($work_directory .
            C\PROFILE_FILE_NAME);
        $file_fields = $this->file_fields;
        ;
        foreach ($this->profile_fields as $field) {
            if (!in_array($field, $file_fields)) {
                $profile[$field] = $this->matchDefine($field, $profile_string);
                if ($field == "AD_LOCATION" && $profile[$field] == "") {
                    $profile[$field] = "none";
                }
            } elseif ($field == "AUXILIARY_CSS") {
                $css_file = C\APP_DIR . "/css/auxiliary.css";
                if (file_exists($css_file)) {
                    $profile[$field] = file_get_contents($css_file);
                } else {
                    $profile[$field] = "";
                }
            }
        }
        return $profile;
    }
    /**
     * Finds the first occurrence of define('$defined', something) in $string
     * and returns something
     *
     * @param string $defined the constant being defined
     * @param string $string the haystack string to search in
     * @return string matched value of define if exists; empty string otherwise
     */
    public function matchDefine($defined, $string)
    {
        preg_match("/nsdefine\((?:\"$defined\"|\'$defined\')\,([^\)]*)\)/",
            $string, $match);
        $match = (isset($match[1])) ? trim($match[1]) : "";
        $len = strlen($match);
        if ($len >=2 && ($match[0] == '"' || $match[0] == "'")) {
            $match = substr($match, 1, strlen($match) - 2);
        }
        return $match;
    }
}
