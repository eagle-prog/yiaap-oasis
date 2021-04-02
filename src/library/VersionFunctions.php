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
 * This file contains global functions connected to upgrading the database
 * between different versions of Yioop
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\models as M;
use seekquarry\yioop\models\datasources as D;


/**
 * Upgrades a Version 0 version of the Yioop database to a Version 1 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion1(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE VERSION (ID $integer PRIMARY KEY)");
    $db->execute("INSERT INTO VERSION VALUES (1)");
    $db->execute("CREATE TABLE USER_SESSION( USER_ID $integer PRIMARY KEY, ".
        "SESSION VARCHAR(".C\MAX_GROUP_POST_LEN."))");
}
/**
 * Upgrades a Version 1 version of the Yioop database to a Version 2 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion2(&$db)
{
    $db->execute("ALTER TABLE USERS ADD UNIQUE ( USER_NAME )" );
    $db->execute("INSERT INTO LOCALE VALUES (
        17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
    $db->execute("INSERT INTO LOCALE VALUES (
        18, 'hi', 'हिन्दी', 'lr-tb')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5,
        'Modifier les rôles')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5,
        'Modifier les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5,
        'Mélanger les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5,
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Configurer')");
}
/**
 * Upgrades a Version 2 version of the Yioop database to a Version 3 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion3(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("INSERT INTO LOCALE VALUES (19, 'tr', 'Türkçe', 'lr-tb')");
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 10)");
    $db->execute("CREATE TABLE MACHINE (
        NAME VARCHAR(16) PRIMARY KEY, URL VARCHAR(".C\MAX_URL_LEN.") UNIQUE,
        HAS_QUEUE_SERVER BOOLEAN, NUM_FETCHERS $integer)");
    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID>5 AND " .
        "ACTIVITY_ID<11");
    $db->execute(
        "DELETE FROM TRANSLATION WHERE TRANSLATION_ID>5 AND " .
        "TRANSLATION_ID<11");
    $db->execute("DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID>5 AND TRANSLATION_ID<11");
    $db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'pageOptions')");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'searchFilters')");
    $db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'manageMachines')");
    $db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageLocales')");
    $db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'configure')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (6, 'db_activity_file_options')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_search_filters')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES(8,'db_activity_manage_machines')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (9,'db_activity_manage_locales')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (10, 'db_activity_configure')");

    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Page Options')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Search Filters')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (8, 1, 'Manage Machines')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (9, 1, 'Manage Locales')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (10, 1, 'Configure')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5,
        'Options de fichier')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Modifier les ordinateurs')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5,
        'Configurer')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
        9, 9, 'ローケル管理')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 9, '設定')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
        9, 10, '로케일 관리')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 10, '구성')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 15,
        'Quản lý miền địa phương')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15,
        'Sắp xếp hoạt động dựa theo hoạch định')");
}
/**
 * Upgrades a Version 3 version of the Yioop database to a Version 4 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion4(&$db)
{
    $db->execute("ALTER TABLE MACHINE ADD COLUMN PARENT VARCHAR(".
        C\NAME_LEN.")");
}
/**
 * Upgrades a Version 4 version of the Yioop database to a Version 5 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion5(&$db)
{
    $static_page_path = C\LOCALE_DIR."/". C\DEFAULT_LOCALE."/pages";
    if (!file_exists($static_page_path)) {
        mkdir($static_page_path);
    }
    $default_bot_txt_path = "$static_page_path/bot.thtml";
    $old_bot_txt_path = C\WORK_DIRECTORY."/bot.txt";
    if (file_exists($old_bot_txt_path) && !file_exists($default_bot_txt_path)){
        rename($old_bot_txt_path, $default_bot_txt_path);
    }
    $db->setWorldPermissionsRecursive($static_page_path);
}
/**
 * Upgrades a Version 5 version of the Yioop database to a Version 6 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion6(&$db)
{
    if (!file_exists(C\PREP_DIR)) {
        mkdir(C\PREP_DIR);
    }
    $db->setWorldPermissionsRecursive(C\PREP_DIR);
}
/**
 * Upgrades a Version 6 version of the Yioop database to a Version 7 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion7(&$db)
{
    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID=7");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'resultsEditor')");
    $db->execute("DELETE FROM TRANSLATION WHERE TRANSLATION_ID=7");
    $db->execute("DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID=7");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_results_editor')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Results Editor')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Éditeur de résultats')");
}
/**
 * Upgrades a Version 7 version of the Yioop database to a Version 8 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion8(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("INSERT INTO LOCALE VALUES (20, 'fa', 'فارسی', 'rl-tb')");
    $db->execute("CREATE TABLE ACTIVE_FETCHER (NAME VARCHAR(".C\NAME_LEN.")," .
        " FETCHER_ID $integer)");
    $db->execute("CREATE TABLE CRON_TIME " .
        "(TIMESTAMP INT(" . C\TIMESTAMP_LEN . "))");
    $db->execute("INSERT INTO CRON_TIME VALUES ('" . time() . "')");
    upgradeLocales();
}
/**
 * Upgrades a Version 8 version of the Yioop database to a Version 9 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion9(&$db)
{
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 11)");
    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID >= 8");
    $db->execute("DELETE FROM TRANSLATION WHERE TRANSLATION_ID >= 8");
    $db->execute("DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID >= 8");
    $db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'searchSources')");
    $db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageMachines')");
    $db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'manageLocales')");
    $db->execute("INSERT INTO ACTIVITY VALUES (11, 11, 'configure')");
    $db->execute("INSERT INTO TRANSLATION VALUES(8,
        'db_activity_search_services')");
    $db->execute("INSERT INTO TRANSLATION VALUES(9,
        'db_activity_manage_machines')");
    $db->execute("INSERT INTO TRANSLATION VALUES (10,
        'db_activity_manage_locales')");
    $db->execute("INSERT INTO TRANSLATION VALUES (11,
        'db_activity_configure')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 1,
        'Search Sources')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 1,
        'Manage Machines')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 1,
        'Manage Locales')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 1,
        'Configure')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Sources de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5,
        'Modifier les ordinateurs')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 5,
        'Configurer')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 9,
        'ローケル管理')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 9,
        '設定')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10,
        10, '로케일 관리')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11,
        10, '구성')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15,
        'Quản lý miền địa phương')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 15,
        'Sắp xếp hoạt động dựa theo hoạch định')");
}
/**
 * Upgrades a Version 9 version of the Yioop database to a Version 10 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion10(&$db)
{
    $db->execute("CREATE TABLE MEDIA_SOURCE (TIMESTAMP INT(11) PRIMARY KEY,
        NAME VARCHAR(16) UNIQUE, TYPE VARCHAR(16),
        SOURCE_URL VARCHAR(256), THUMB_URL VARCHAR(256)
        )");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
        'YouTube', 'video', 'http://www.youtube.com/watch?v={}&',
        'http://img.youtube.com/vi/{}/2.jpg')");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
        'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}/',
        'http://www.metacafe.com/thumb/{}.jpg')");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
        'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
        'http://www.dailymotion.com/thumbnail/video/{}')");
}
/**
 * Upgrades a Version 10 version of the Yioop database to a Version 11 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion11(&$db)
{
    $db->execute("DROP TABLE CRON_TIME");
    $db->execute("ALTER TABLE ROLE_ACTIVITY ADD CONSTRAINT
        PK_RA PRIMARY KEY(ROLE_ID, ACTIVITY_ID)");
    $db->execute("CREATE TABLE SUBSEARCH (LOCALE_STRING VARCHAR(16) " .
        "PRIMARY KEY, FOLDER_NAME VARCHAR(16), INDEX_IDENTIFIER CHAR(13))");
}
/**
 * Upgrades a Version 11 version of the Yioop database to a Version 12 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion12(&$db)
{
    $db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images')");
    $db->execute("INSERT INTO MIX_GROUPS VALUES(2, 0, 1)");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(2, 0, 1, 1,
        'media:image')");
    $db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos')");
    $db->execute("INSERT INTO MIX_GROUPS VALUES(3, 0, 1)");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(3, 0, 1, 1,
        'media:video')");
    $db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
        'images','m:2',50)");
    $db->execute("INSERT INTO TRANSLATION VALUES " .
        "(1002,'db_subsearch_images')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
            (1002, 1, 'Images' )");

    $db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
        'videos','m:3',10)");
    $db->execute("INSERT INTO TRANSLATION VALUES " .
        "(1003,'db_subsearch_videos')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
            (1003, 1, 'Videos' )");
}
/**
 * Upgrades a Version 12 version of the Yioop database to a Version 13 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion13(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE FEED_ITEM (GUID VARCHAR(11) PRIMARY KEY,
        TITLE VARCHAR(512), LINK VARCHAR(256), DESCRIPTION VARCHAR(4096),
        PUBDATE $integer, SOURCE_NAME VARCHAR(16))");
    if (!file_exists(C\WORK_DIRECTORY . "/feeds")) {
        mkdir(C\WORK_DIRECTORY . "/feeds");
    }
    upgradeLocales(); //force locale upgrade
}
/**
 * Upgrades a Version 13 version of the Yioop database to a Version 14 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion14(&$db)
{
    $db->execute("ALTER TABLE MEDIA_SOURCE ADD LANGUAGE VARCHAR(7)");
}
/**
 * Upgrades a Version 14 version of the Yioop database to a Version 15 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion15(&$db)
{
    $db->execute("DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP=2
        AND GROUP_ID=0");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(
        2, 0, 1, 1, 'media:image site:doc')");
    $db->execute("DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP=3
        AND GROUP_ID=0");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(
        3, 0, 1, 1, 'media:video site:doc')");
    $db->execute("INSERT INTO LOCALE VALUES (21, 'te',
        'తెలుగు', 'lr-tb')");
    upgradeLocales();
}
/**
 * Upgrades a Version 15 version of the Yioop database to a Version 16 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion16(&$db)
{
    addActivityAtId($db, 'db_activity_manage_classifiers',
        "manageClassifiers", 4);
    updateTranslationForStringId($db, 'db_activity_manage_classifiers',
        'en-US', 'Manage Classifiers');
    updateTranslationForStringId($db, 'db_activity_manage_classifiers',
        'fr-FR', 'Manage Classifiers');
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'fr-FR',
        'Classificateurs');

    $old_archives_path = C\WORK_DIRECTORY . "/cache/archives";
    $new_archives_path = C\WORK_DIRECTORY . "/archives";
    if (file_exists($old_archives_path)) {
        rename($old_archives_path, $new_archives_path);
    } else if (!file_exists($new_archives_path)) {
        mkdir($new_archives_path);
    }
    $db->setWorldPermissionsRecursive($new_archives_path);

    $new_classifiers_path = C\WORK_DIRECTORY."/classifiers";
    if (!file_exists($new_classifiers_path)) {
        mkdir($new_classifiers_path);
    }
    $db->setWorldPermissionsRecursive($new_classifiers_path);
    upgradeLocales();
}
/**
 * Upgrades a Version 16 version of the Yioop database to a Version 17 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion17(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("CREATE TABLE GROUPS (GROUP_ID $integer PRIMARY KEY
        $auto_increment ,GROUP_NAME VARCHAR(128), CREATED_TIME INT(11),
           CREATOR_ID INT(11))");
    $db->execute("CREATE TABLE USER_GROUP (USER_ID $integer,
        GROUP_ID $integer, PRIMARY KEY (GROUP_ID, USER_ID) )");
    addActivityAtId($db, 'db_activity_manage_groups', "manageGroups", 4);
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'en-US',
        'Manage Groups');
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'fr-FR',
        'Modifier les groupes');
    upgradeLocales();
}
/**
 * Upgrades a Version 17 version of the Yioop database to a Version 18 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion18(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("CREATE TABLE ACCESS (NAME VARCHAR(16), ID $integer,
                TYPE VARCHAR(16))");
    $db->execute("CREATE TABLE BLOG_DESCRIPTION (TIMESTAMP INT(11) UNIQUE,
                DESCRIPTION VARCHAR(4096))");
    addActivityAtId($db, 'db_activity_blogs_pages', "blogPages", 6);
    updateTranslationForStringId($db, 'db_activity_blogs_pages', 'en-US',
        'Blogs and Pages');
    updateTranslationForStringId($db, 'db_activity_blogs_pages', 'fr-FR',
        'les blogs et les pages');
    upgradeLocales();
}
/**
 * Upgrades a Version 18 version of the Yioop database to a Version 19 version
 * This update has been superseded by the Version20 update and so its contents
 * have been eliminated.
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion19(&$db)
{
}
/**
 * Upgrades a Version 19 version of the Yioop database to a Version 20 version
 * This is a major upgrade as the user table have changed. This also acts
 * as a cumulative since version 0.98. It involves a web form that has only
 * been localized to English
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion20(&$db)
{
    if (!isset($_REQUEST['v20step'])) {
        $_REQUEST['v20step'] = 1;
    }
    $upgrade_check_file = C\WORK_DIRECTORY . "/v20check.txt";
    if (!file_exists($upgrade_check_file)) {
        $upgrade_password = substr(sha1(microtime(true).C\AUTH_KEY), 0, 8);
        file_put_contents($upgrade_check_file, $upgrade_password);
    } else {
        $v20check = trim(file_get_contents($upgrade_check_file));
        if (isset($_REQUEST['v20step']) && $_REQUEST['v20step'] == 2 &&
            (!isset($_REQUEST['upgrade_code'])||
            $v20check != trim($_REQUEST['upgrade_code']))) {
            $_REQUEST['v20step'] = 1;
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                "v20check.txt not typed in correctly!</h1>')";
        }
    }
    switch ($_REQUEST['v20step']) {
        case "2":
            $profile_model = new M\ProfileModel(C\DB_NAME, false);
            $profile_model->db = $db;
            $save_tables = ["ACTIVE_FETCHER", "CURRENT_WEB_INDEX",
                "FEED_ITEM", "MACHINE", "MEDIA_SOURCE", "SUBSEARCH",
                "VERSION"];
            $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
                "DB_USER" => C\DB_USER, "DB_PASSWORD" => C\DB_PASSWORD,
                "DB_NAME" => C\DB_NAME];
            $creation_time = microTimestamp();
            $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
            $new_profile = $profile;
            $new_profile['MAIL_SERVER']= "";
            $new_profile['MAIL_PORT']= "";
            $new_profile['MAIL_USERNAME']= "";
            $new_profile['MAIL_PASSWORD']= "";
            $new_profile['MAIL_SECURITY']= "";
            $new_profile['REGISTRATION_TYPE'] = 'disable_registration';
            $new_profile['USE_MAIL_PHP'] = true;
            $new_profile['WORD_SUGGEST'] = true;
            $profile_model->updateProfile(C\WORK_DIRECTORY, $new_profile,
                $profile);
            //get current users
            //(assume can fit in memory and doesn't take long)
            $users = [];
            $user_tables_sql = ["SELECT USER_NAME FROM USER",
                "SELECT USER_NAME, FIRST_NAME, LAST_NAME, EMAIL FROM USERS"];
            $i = 0;
            foreach ($user_tables_sql as $sql) {
                $result = $db->execute($sql);
                if ($result) {
                    while($users[$i] = $db->fetchArray($result)) {
                        $setup_user_fields = [];
                        if ($users[$i]["USER_NAME"] == "root" ||
                            $users[$i]["USER_NAME"] == "public") {
                                continue;
                        }
                        $users[$i]["FIRST_NAME"] =
                            (isset($users[$i]["FIRST_NAME"])) ?
                            $users[$i]["FIRST_NAME"] : "FIRST_$i";
                        $users[$i]["LAST_NAME"] =
                            (isset($users[$i]["LAST_NAME"])) ?
                            $users[$i]["LAST_NAME"] : "LAST_$i";
                        $users[$i]["EMAIL"] =
                            (isset($users[$i]["EMAIL"])) ?
                            $users[$i]["EMAIL"] : "user$i@dev.null";
                        $users[$i]["PASSWORD"] = $v20check;
                        $users[$i]["STATUS"] = C\INACTIVE_STATUS;
                        $users[$i]["CREATION_TIME"] = $creation_time;
                        $users[$i]["UPS"] = 0;
                        $users[$i]["DOWNS"] = 0;
                        $i++;
                    }
                    unset($users[$i]);
                    $result = null;
                }
            }
            $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
                "DB_USER" => C\DB_USER, "DB_PASSWORD" => C\DB_PASSWORD,
                "DB_NAME" => C\DB_NAME];
            $profile_model->initializeSql($db, $dbinfo);
            $database_tables = array_diff(
                array_keys($profile_model->create_statements),
                $save_tables);
            $database_tables = array_merge($database_tables,
                ["BLOG_DESCRIPTION", "USER_OLD", "ACCESS"]);
            foreach ($database_tables as $table) {
                if (!in_array($table, $save_tables)){
                    $db->execute("DROP TABLE ".$table);
                }
            }
            if ($profile_model->migrateDatabaseIfNecessary(
                $dbinfo, $save_tables)) {
                $user_model = new M\UserModel(C\DB_NAME, false);
                $user_model->db = $db;
                foreach ($users as $user) {
                    $user_model->addUser($user["USER_NAME"], $user["PASSWORD"],
                        $user["FIRST_NAME"], $user["LAST_NAME"],
                        $user["EMAIL"], $user["STATUS"]);
                }
                $user = [];
                $user['USER_ID'] = C\ROOT_ID;
                $user['PASSWORD'] = $v20check;
                $user_model->updateUser($user);
                $db->execute("DELETE FROM VERSION WHERE ID < 19");
                $db->execute("UPDATE VERSION SET ID=20 WHERE ID=19");
                return;
            }
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                "Couldn't migrate database tables from defaults!</h1>')";
            // no break
        case "1":
            // no break
        default:
            ?>
            <!DOCTYPE html>
            <html lang='en-US'>
            <head>
            <title>Yioop Upgrade Detected</title>
            <meta name="ROBOTS" content="NOINDEX,NOFOLLOW" />
            <meta name="Author" content="Christopher Pollett" />
            <meta charset="utf-8" />
            <?php if ($_SERVER["MOBILE"]) {?>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <?php } ?>
            <link rel="stylesheet" type="text/css"
                 href="<?= C\SHORT_BASE_URL ?>/css/search.css" />
            </head>
            <body class="html-ltr <?php if ($_SERVER["MOBILE"]) {e('mobile'); }
            ?>" >
            <div id="message" ></div>
            <div class='small-margin-current-activity'>
            <h1 class='center green'>Yioop Upgrade Detected!</h1>
            <p>Upgrading to Version 1 of Yioop from an earlier version
            is a major upgrade. The way passwords are stored and the
            organization of the Yioop database has changed. Here is
            what is preserved by this upgrade:</p>
            <ol>
            <li>Existing crawls and archive data.</li>
            <li>Machines known if this instance is a name server.</li>
            <li>Media sources and subsearches.</li>
            <li>Feed items.</li>
            </ol>
            <p>Here is what happens during the upgrade which might
            result in data loss:</p>
            <ol>
            <li>Root and user account passwords are changed to the contents of
            v20check.txt.</li>
            <li>User accounts other than root are marked as inactived,
            so will have tobe activated under Manage Users before that person
            can sign in.</li>
            <li>All roles except Admin and User are deleted. Root
            will be given Admin role, all other users will receive
            User role.</li>
            <li>All existing groups are deleted.</li>
            <li>Existing crawl mixes will be deleted.</li>
            <li>Any customized translations that begin with the prefix db_.
            Other still in use translations will be preserved.</li>
            </ol>
            <p>If given the above you don't want to upgrade, merely replace
            this folder with the contents of your old Yioop instance and
            you should be able to continue to use Yioop as before.</p>
            <p>If you decide to proceed with the upgrade, please back up
            both your existing database and work directory.</p>
            <form method="post" action="?">
            <p><label for="upgrade-code">
            <b>In the field below enter the string found in the file:<br />
            <span class="green"><?= C\WORK_DIRECTORY."/v20check.txt"?></span>
            </b></label></p>
            <input id='upgrade-code' class="extra-wide-field"
                name="upgrade_code" type="text" />
            <input type="hidden" name="v20step" value="2" />
            <button class="button-box" type="submit">Upgrade</button>
            </form>
            <?php
        break;
    }
    ?>
    </div>
    <script src="<?= C\SHORT_BASE_URL ?>/scripts/basic.js" ></script>
    <script>
    <?php
    if (isset($data['SCRIPT'])) {
        e($data['SCRIPT']);
    }
    ?></script>
    </body>
    </html>
   <?php
   exit();
}
/**
 * Upgrades a Version 20 version of the Yioop database to a Version 21 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion21(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE GROUP_THREAD_VIEWS(
        THREAD_ID $integer PRIMARY KEY, NUM_VIEWS $integer)");
    $db->execute("ALTER TABLE MEDIA_SOURCE RENAME TO MEDIA_SOURCE_OLD");
    $db->execute("CREATE TABLE MEDIA_SOURCE (TIMESTAMP NUMERIC(11) PRIMARY KEY,
        NAME VARCHAR(64) UNIQUE, TYPE VARCHAR(16), SOURCE_URL VARCHAR(256),
        AUX_INFO VARCHAR(512), LANGUAGE VARCHAR(7))");
    D\DatasourceManager::copyTable("MEDIA_SOURCE_OLD", $db, "MEDIA_SOURCE",
        $db);
    $db->execute("DROP TABLE MEDIA_SOURCE_OLD");
}
/**
 * Upgrades a Version 21 version of the Yioop database to a Version 22 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion22(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("INSERT INTO GROUP_THREAD_VIEWS
        SELECT DISTINCT PARENT_ID, 1 FROM GROUP_ITEM WHERE
        NOT EXISTS (SELECT THREAD_ID
        FROM GROUP_THREAD_VIEWS WHERE THREAD_ID=PARENT_ID)");
    $db->execute("ALTER TABLE LOCALE ADD ACTIVE $integer DEFAULT 1");
}
/**
 * Upgrades a Version 22 version of the Yioop database to a Version 23 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion23(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("ALTER TABLE GROUPS ADD POST_LIFETIME $integer DEFAULT ".
        FOREVER);
}
/**
 * Upgrades a Version 23 version of the Yioop database to a Version 24 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion24(&$db)
{
    $profile_model = new M\ProfileModel(C\DB_NAME, false);
    $profile_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_USER" => C\DB_USER, "DB_PASSWORD" => C\DB_PASSWORD,
        "DB_NAME" => C\DB_NAME];
    $profile_model->initializeSql($db, $dbinfo);
    foreach ($profile_model->create_statements as $object_name => $statement) {
        if (stristr($object_name, "_INDEX")) {
            if (!$db->execute($statement)) {
                echo $statement." ERROR!";
                exit();
            }
        } else {
            if (!$db->execute("ALTER TABLE $object_name RENAME TO " .
                $object_name . "_OLD")) {
                echo "RENAME $object_name ERROR!";
                exit();
            }
            if (!$db->execute($statement)) {
                echo $statement." ERROR!";
                exit();
            }
            D\DatasourceManager::copyTable($object_name."_OLD", $db,
                $object_name, $db);
            $db->execute("DROP TABLE ".$object_name."_OLD");
        }
    }
}
/**
 * Upgrades a Version 24 version of the Yioop database to a Version 25 version
 * This version upgrade includes creation of Help group that holds help pages.
 * Help Group is created with GROUP_ID=HELP_GROUP_ID. If a Group with
 * Group_ID=HELP_GROUP_ID already exists,
 * then that GROUP is moved to the end of the GROUPS table(Max group id is
 * used).
 *
 * @param object $db data source to use to upgrade
 */
function upgradeDatabaseVersion25(&$db)
{
    /** For reading HELP_GROUP_ID**/
    $sql = "SELECT COUNT(*) AS NUM FROM GROUPS WHERE GROUP_ID=" .
        C\HELP_GROUP_ID;
    $result = $db->execute($sql);
    $row = ($db->fetchArray($result));
    $is_taken = intval($row['NUM']);
    if ($is_taken > 0) {
        //Get the max group Id , increment it to push the old group
        $sql = "SELECT MAX(GROUP_ID) AS MAX_GROUP_ID FROM GROUPS";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $max_group_id = $row['MAX_GROUP_ID'] + 1;
        $tables_to_update_group_id = ["GROUPS", "GROUP_ITEM",
            "GROUP_PAGE", "GROUP_PAGE_HISTORY", "USER_GROUP"];
        foreach ($tables_to_update_group_id as $table) {
            $sql = "UPDATE $table "
                . "Set GROUP_ID=$max_group_id "
                . "WHERE "
                . "GROUP_ID=" . C\HELP_GROUP_ID;
            $db->execute($sql);
        }
    }
    //Insert the Help Group
    $creation_time = microTimestamp();
    $sql = "INSERT INTO GROUPS VALUES(" . C\HELP_GROUP_ID . ",'Help','"
        . $creation_time . "','" . C\ROOT_ID . "',
        '" . C\PUBLIC_BROWSE_REQUEST_JOIN . "', '" . C\GROUP_READ_WIKI . "',
        " . C\UP_DOWN_VOTING_GROUP . ", " . C\FOREVER . ")";
    $db->execute($sql);
    $now = time();
    $db->execute("INSERT INTO USER_GROUP VALUES (" . C\ROOT_ID . ", " .
        C\HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
    $db->execute("INSERT INTO USER_GROUP VALUES (" . C\PUBLIC_USER_ID . ", " .
        C\HELP_GROUP_ID . ", " . C\ACTIVE_STATUS . ", $now)");
    //Insert into Groups
    $help_pages = getWikiHelpPages();
    foreach ($help_pages as $page_name => $page_content) {
        $page_content = str_replace("&amp;", "&", $page_content);
        $page_content = @htmlentities($page_content, ENT_QUOTES, "UTF-8");
        $group_model = new M\GroupModel(C\DB_NAME, false);
        $group_model->db = $db;
        $group_model->setPageName(C\ROOT_ID, C\HELP_GROUP_ID, $page_name,
            $page_content, "en-US", "Creating Default Pages", "$page_name "
            . "Help Page Created!", "Discuss the page in this thread!");
    }
}
/**
 * Upgrades a Version 25 version of the Yioop database to a Version 26 version
 * This version upgrade includes updation fo the Help pages in the database to
 * work with the changes to the way Hyperlinks are specified in wiki markup.
 * The changes were implemented to point all articles with page names
 * containing %20 to be able to work with '_' and vice versa.
 * @param object $db data source to use to upgrade
 */
function upgradeDatabaseVersion26(&$db)
{
    //Delete all existing pages in Help group
    $params = [C\HELP_GROUP_ID];
    $sql = "DELETE FROM GROUP_PAGE WHERE GROUP_ID=?";
    $db->execute($sql, $params);
    $sql = "DELETE FROM GROUP_PAGE_HISTORY WHERE GROUP_ID=?";
    $db->execute($sql, $params);
    //Insert the Help Group pages with corrected titles
    $creation_time = microTimestamp();
    $sql = "INSERT INTO GROUPS VALUES(" . C\HELP_GROUP_ID . ",'Help','"
        . $creation_time . "','" . C\ROOT_ID . "',
        '" . C\PUBLIC_BROWSE_REQUEST_JOIN . "', '" . C\GROUP_READ_WIKI . "',
        " . C\UP_DOWN_VOTING_GROUP . ", " . C\FOREVER . ")";
    $db->execute($sql);
    $now = time();
    $db->execute("INSERT INTO USER_GROUP VALUES (" . C\ROOT_ID . ", " .
        C\HELP_GROUP_ID . ", " . C\ACTIVE_STATUS . ", $now)");
    $db->execute("INSERT INTO USER_GROUP VALUES (" . C\PUBLIC_USER_ID . ", " .
        C\HELP_GROUP_ID . ", " . C\ACTIVE_STATUS . ", $now)");
    //Insert into Groups
    $help_pages = getWikiHelpPages();
    foreach ($help_pages as $page_name => $page_content) {
        $page_content = str_replace("&amp;", "&", $page_content);
        $page_content = @htmlentities($page_content, ENT_QUOTES, "UTF-8");
        $group_model = new M\GroupModel(C\DB_NAME, false);
        $group_model->db = $db;
        $group_model->setPageName(C\ROOT_ID, C\HELP_GROUP_ID, $page_name,
            $page_content, "en-US", "Creating Default Pages", "$page_name "
            . "Help Page Created!", "Discuss the page in this thread!");
    }
}
/**
 * Upgrades a Version 26 version of the Yioop database to a Version 27 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion27(&$db)
{
    $db->execute("ALTER TABLE GROUP_ITEM ADD COLUMN EDIT_DATE
        NUMERIC(".C\TIMESTAMP_LEN.")");
}
/**
 * Upgrades a Version 27 version of the Yioop database to a Version 28 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion28(&$db)
{
    $db->execute("ALTER TABLE FEED_ITEM ADD COLUMN IMAGE_LINK
        VARCHAR(".C\MAX_URL_LEN.")");
}
/**
 * Upgrades a Version 28 version of the Yioop database to a Version 29 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion29(&$db)
{
    $sql = "INSERT INTO LOCALE (LOCALE_NAME, LOCALE_TAG,
        WRITING_MODE, ACTIVE) VALUES (?, ?, ?, ?)";
    $db->execute($sql, ["Nederlands", "nl", "lr-tb", 1]);
    $nl_translations = [
        'db_activity_manage_account' => 'Account Beheren',
        'db_activity_manage_users' => 'Gebruikers beheren',
        'db_activity_manage_roles' => 'Rollen beheren',
        'db_activity_manage_groups' => 'Groepen beheren',
        'db_activity_manage_crawl' => 'Beheer Crawl',
        'db_activity_mix_crawls' => 'Mix Crawls',
        'db_activity_group_feeds' => 'Feeds en Wikis',
        'db_activity_manage_classifiers' => 'Beheer Classifiers',
        'db_activity_file_options' => 'Opties voor de pagina',
        'db_activity_results_editor' => 'Resultaten Editor',
        'db_activity_search_services' => 'Zoek Bronnen',
        'db_activity_manage_machines' => 'Beheer Machines',
        'db_activity_manage_locales' => 'Beheer varianten',
        'db_activity_server_settings' => 'Server Settings',
        'db_activity_security' => 'Veiligheid',
        'db_activity_configure' => 'Configureren',
        'db_subsearch_images' => 'Beelden',
        'db_subsearch_videos' => 'Videos',
        'db_subsearch_news' => 'Nieuws',
    ];
    foreach ($nl_translations as $identifier => $translation) {
        updateTranslationForStringId($db, $identifier, 'nl', $translation);
    }
}
/**
 * Upgrades a Version 29 version of the Yioop database to a Version 30 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion30(&$db)
{
    $db->execute("CREATE TABLE MEDIA_UPDATER_PROPERTIES(
        NAME VARCHAR(". C\NAME_LEN .") NOT NULL, VALUE VARCHAR(".
        C\NAME_LEN.") NOT NULL)");
    $db->execute("INSERT INTO MEDIA_UPDATER_PROPERTIES
        VALUES ('PRESENT_ON_NAME_SERVER','true')");
}
/**
 * Upgrades a Version 30 version of the Yioop database to a Version 31 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion31(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE MEDIA_UPDATER_PROPERTIES");
    $db->execute("DROP TABLE ACTIVE_FETCHER");
    $db->execute("CREATE TABLE ACTIVE_PROCESS (NAME VARCHAR(". C\NAME_LEN.
        "), ID $integer, TYPE VARCHAR(" . C\NAME_LEN . "))");
    $profile_model = new M\ProfileModel(C\DB_NAME, false);
    $profile['MEDIA_MODE'] = "name_server";
    $profile_model->updateProfile(C\WORK_DIRECTORY, [], $profile);
}
/**
 * Upgrades a Version 31 version of the Yioop database to a Version 32 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion32(&$db)
{
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("ALTER TABLE USERS ADD COLUMN IS_ADVERTISER $integer");
    $db->execute("CREATE TABLE ADVERTISEMENTS(ADV_ID $integer
        PRIMARY KEY $auto_increment, USER_ID $integer,
        ADV_NAME VARCHAR(". C\ADVERTISEMENT_NAME_LEN ."),
        ADV_DESCRIPTION VARCHAR(". C\ADVERTISEMENT_TEXT_LEN ."),
        ADV_DESTINATION VARCHAR(". C\ADVERTISEMENT_DESTINATION_LEN ."),
        ADV_KEYWORDS VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
        STATUS $integer, ADV_BUDGET $integer,
        ADV_DATE VARCHAR(". C\ADVERTISEMENT_DATE_LEN ."))");
    $db->execute("CREATE TABLE ADVERTISEMENT_KEYWORD
        (ID $integer PRIMARY KEY $auto_increment,
        KEYWORD VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
        BID_AMOUNT $integer,
        BID_DATE VARCHAR(". C\ADVERTISEMENT_DATE_LEN ."))");
    $db->execute("CREATE TABLE ADVERTISEMENT_METADATA
        (ADV_ID $integer, CLICKS $integer, IMPRESSIONS $integer)");
    addActivityAtId($db, 'db_activity_manage_advertisements',
        "manageAdvertisements", 17);
    updateTranslationForStringId($db, 'db_activity_manage_advertisements',
        'en-US', 'Manage Advertisements');
    $role_model->addRole('Business User');
    $role_id = $role_model->getRoleId('Business User');
    $role_model->addActivityRole($role_id, 17);
}
/**
 * Upgrades a Version 32 version of the Yioop database to a Version 33 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion33(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("DROP TABLE ADVERTISEMENTS");
    $db->execute("DROP TABLE ADVERTISEMENT_KEYWORD");
    $db->execute("DROP TABLE ADVERTISEMENT_METADATA");
    $db->execute("CREATE TABLE ADVERTISEMENT(AD_ID $integer
        PRIMARY KEY $auto_increment, USER_ID $integer,
        AD_NAME VARCHAR(". C\ADVERTISEMENT_NAME_LEN ."),
        AD_DESCRIPTION VARCHAR(". C\ADVERTISEMENT_TEXT_LEN ."),
        AD_DESTINATION VARCHAR(". C\ADVERTISEMENT_DESTINATION_LEN ."),
        AD_KEYWORDS VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
        STATUS $integer, AD_BUDGET $integer,
        AD_DATE VARCHAR(". C\ADVERTISEMENT_DATE_LEN ."))");
    $db->execute("CREATE TABLE ADVERTISEMENT_KEYWORD
        (ID $integer PRIMARY KEY $auto_increment,
        KEYWORD VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
        BID_AMOUNT $integer,
        BID_DATE VARCHAR(". C\ADVERTISEMENT_DATE_LEN ."))");
    $db->execute("CREATE TABLE ADVERTISEMENT_METADATA
        (AD_ID $integer, CLICKS $integer, IMPRESSIONS $integer)");
}
/**
 * Upgrades a Version 33 version of the Yioop database to a Version 34 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion34(&$db)
{
    addActivityAtId($db, 'db_activity_appearance',
        "appearance", 16);
    updateTranslationForStringId($db, 'db_activity_appearance',
        'en-US', 'Appearance');
    updateTranslationForStringId($db, 'db_activity_appearance',
        'fr-FR', 'Aspect');
    updateTranslationForStringId($db, 'db_activity_appearance',
        'nl', 'Verschijning');
}
/**
 * Upgrades a Version 34 version of the Yioop database to a Version 35 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion35(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE CREDIT_LEDGER
        (USER_ID $integer, AMOUNT $integer, TYPE VARCHAR(" .
        C\NAME_LEN . "), BALANCE $integer, TIMESTAMP NUMERIC(" .
        C\TIMESTAMP_LEN . "))");
    addActivityAtId($db, 'db_activity_manage_credits',
        "manageCredits", 18);
    updateTranslationForStringId($db, 'db_activity_manage_credits',
        'en-US', 'Manage Credits');
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $activity_model = new M\ActivityModel(C\DB_NAME, false);
    $activity_model->db = $db;
    $role_id = $role_model->getRoleId('Business User');
    $activity_id = $activity_model->getActivityIdFromMethodName(
        "manageCredits");
    $role_model->addActivityRole($role_id, $activity_id);
    $activity_id = $activity_model->getActivityIdFromMethodName(
        "manageAdvertisements");
    $role_model->addActivityRole($role_id, $activity_id);
}
/**
 * Upgrades a Version 35 version of the Yioop database to a Version 36 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion36(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("DROP TABLE ADVERTISEMENT");
    $db->execute("DROP TABLE ADVERTISEMENT_KEYWORD");
    $db->execute("DROP TABLE ADVERTISEMENT_METADATA");
    $db->execute("DELETE FROM CREDIT_LEDGER");
    $db->execute("CREATE TABLE ADVERTISEMENT (ID $integer
        PRIMARY KEY $auto_increment, USER_ID $integer,
        NAME VARCHAR(". C\ADVERTISEMENT_NAME_LEN ."),
        DESCRIPTION VARCHAR(". C\ADVERTISEMENT_TEXT_LEN ."),
        DESTINATION VARCHAR(". C\ADVERTISEMENT_DESTINATION_LEN ."),
        KEYWORDS VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN ."),
        STATUS $integer, BUDGET $integer, CLICKS $integer,
        IMPRESSIONS $integer, START_DATE VARCHAR(".
        C\ADVERTISEMENT_DATE_LEN ."), END_DATE VARCHAR(".
        C\ADVERTISEMENT_DATE_LEN ."))");
    $db->execute("CREATE TABLE ACCEPTED_AD_BIDS
        (AD_ID $integer, KEYWORD VARCHAR(". C\ADVERTISEMENT_KEYWORD_LEN
        ."), BID_AMOUNT $integer, BID_DATE VARCHAR(".
        C\ADVERTISEMENT_DATE_LEN ."))");
}
/**
 * Upgrades a Version 36 version of the Yioop database to a Version 37 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion37(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE ITEM_IMPRESSION(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        VIEW_DATE NUMERIC(" . C\TIMESTAMP_LEN . "))");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        NUM_ALL_TIME $integer, NUM_YEAR $integer, NUM_MONTH $integer,
        NUM_DAY $integer, PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE))");
    $db->execute("CREATE TABLE GROUP_PAGE_LINK(
        LINK_TYPE_ID $integer, FROM_ID $integer,
        TO_ID $integer)");
    $db->execute("INSERT INTO ITEM_IMPRESSION_SUMMARY
        SELECT ".C\PUBLIC_USER_ID.", THREAD_ID, ".C\THREAD_IMPRESSION.",
        NUM_VIEWS, 0, 0, 0 FROM GROUP_THREAD_VIEWS");
}

/**
 * Upgrades a Version 37 version of the Yioop database to a Version 38 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion38(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE GROUP_THREAD_VIEWS");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY_OLD(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        NUM_ALL_TIME $integer, NUM_YEAR $integer, NUM_MONTH $integer,
        NUM_DAY $integer, PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
        UPDATE_TIMESTAMP))");
    D\DatasourceManager::copyTable("ITEM_IMPRESSION_SUMMARY", $db,
        "ITEM_IMPRESSION_SUMMARY_OLD", $db);
    $db->execute("DROP TABLE ITEM_IMPRESSION_SUMMARY");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        UPDATE_PERIOD $integer,
        UPDATE_TIMESTAMP  NUMERIC(" . C\TIMESTAMP_LEN . "),
        NUM_VIEWS $integer, PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
        UPDATE_PERIOD))");
    $db->execute("INSERT INTO ITEM_IMPRESSION_SUMMARY
        SELECT USER_ID, ITEM_ID, ITEM_TYPE, ".C\FOREVER.", 0, NUM_ALL_TIME AS
        NUM_VIEWS FROM ITEM_IMPRESSION_SUMMARY_OLD");
}
/**
 * Upgrades a Version 38 version of the Yioop database to a Version 39 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion39(&$db)
{
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $db->execute("CREATE TABLE CMS_DETECTORS (TIMESTAMP NUMERIC(" .
        C\TIMESTAMP_LEN . ") PRIMARY KEY NOT NULL, NAME VARCHAR(" .
        C\TITLE_LEN . "), HEADER VARCHAR(" . C\TITLE_LEN . ")," .
        "IMPORTANT_CONTENT VARCHAR(" . C\TITLE_LEN . "))");
    addActivityAtId($db, 'db_activity_cms_detectors',
        "cmsDetectors", 20);
    updateTranslationForStringId($db, 'db_activity_cms_detectors',
        'en-US', 'CMS Detectors');
    $role_id = $role_model->getRoleId('Admin');
    $role_model->addActivityRole($role_id, 20);
}
/**
 * Upgrades a Version 39 version of the Yioop database to a Version 40 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion40(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE GROUP_PAGE_PRE_LINK(
        LINK_TYPE_ID $integer, FROM_ID $integer,
        TO_GROUP_ID $integer, TO_PAGE_NAME VARCHAR(" . C\TITLE_LEN .
        "))");
    $db->execute("CREATE INDEX GP_PRE_INDEX ON GROUP_PAGE_PRE_LINK
        (TO_GROUP_ID, TO_PAGE_NAME)");
}
/**
 * Upgrades a Version 40 version of the Yioop database to a Version 41 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion41(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $db->execute("CREATE TABLE QUERY_ITEM (ID $serial PRIMARY KEY
        $auto_increment, QUERY_HASH CHAR(".
        C\TIMESTAMP_LEN . ") UNIQUE, QUERY VARCHAR(" . C\MAX_QUERY_LEN
        ."), CREATION NUMERIC(" . C\TIMESTAMP_LEN . "))");
    $db->execute("CREATE INDEX QI_QUERY_HASH_INDEX ON
        QUERY_ITEM (QUERY_HASH)");
}
/**
 * Upgrades a Version 41 version of the Yioop database to a Version 42 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion42(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE ITEM_IMPRESSION_SUMMARY");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        UPDATE_PERIOD $integer,
        UPDATE_TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
        NUM_VIEWS $integer, PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
        UPDATE_PERIOD, UPDATE_TIMESTAMP))");
}
/**
 * Upgrades a Version 42 version of the Yioop database to a Version 43 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion43(&$db)
{
    $db->execute("INSERT INTO ITEM_IMPRESSION_SUMMARY
        SELECT DISTINCT I.USER_ID AS USER_ID,
            I.ITEM_ID AS ITEM_ID, I.ITEM_TYPE AS ITEM_TYPE,
            " . C\FOREVER . " AS UPDATE_PERIOD,
            0 AS UPDATE_TIMESTAMP,
            0 AS NUM_VIEWS
        FROM ITEM_IMPRESSION I WHERE I.ITEM_ID > 0 AND NOT EXISTS
           (SELECT * FROM ITEM_IMPRESSION_SUMMARY IIS
            WHERE IIS.USER_ID = I.USER_ID AND
            IIS.ITEM_TYPE= I.ITEM_TYPE AND IIS.ITEM_ID= I.ITEM_ID
            AND IIS.UPDATE_PERIOD = ".C\FOREVER."
        )");
}
/**
 * Upgrades a Version 43 version of the Yioop database to a Version 44 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion44(&$db)
{
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $sql = "SELECT ACTIVITY_ID FROM ACTIVITY ".
        "WHERE METHOD_NAME = 'cmsDetectors'";
    $result = $db->execute($sql, []);
    if ($result) {
        $row = $db->fetchArray($result);
        if (!empty($row['ACTIVITY_ID'])) {
            $sql = "DELETE FROM ROLE_ACTIVITY WHERE ACTIVITY_ID=?";
            $db->execute($sql, [$row['ACTIVITY_ID']]);
            $sql = "DELETE FROM ACTIVITY WHERE ACTIVITY_ID=?";
            $db->execute($sql, [$row['ACTIVITY_ID']]);
        }
    }
    $sql = "SELECT TRANSLATION_ID FROM TRANSLATION ".
        "WHERE IDENTIFIER_STRING = 'db_activity_cms_detectors' " .
        $db->limitOffset(1);
    $result = $db->execute($sql, []);
    if ($result) {
        $row = $db->fetchArray($result);
        $translate_id = $row['TRANSLATION_ID'];
        $sql = "DELETE FROM TRANSLATION WHERE TRANSLATION_ID=?";
        $db->execute($sql, [$translate_id]);
        $sql = "DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID=?";
        $db->execute($sql, [$translate_id]);
    }
    $sql = "SELECT ACTIVITY_ID FROM ACTIVITY ".
        "WHERE METHOD_NAME = 'scrapers'";
    $result = $db->execute($sql, []);
    if ($result) {
        $row = $db->fetchArray($result);
        if  ($row) { //activity already exists
            return;
        }
    }
    $db->execute("CREATE TABLE SCRAPER (ID $serial PRIMARY KEY
        $auto_increment, NAME VARCHAR(" .
        C\TITLE_LEN . ") UNIQUE, SIGNATURE VARCHAR(" . C\TITLE_LEN . ")," .
        "SCRAPE_RULES VARCHAR(" . C\TITLE_LEN . "))");
    addActivityAtId($db, 'db_activity_scrapers',
        "scrapers", 20);
    updateTranslationForStringId($db, 'db_activity_scrapers',
        'en-US', 'Web Scrapers');
    $role_id = $role_model->getRoleId('Admin');
    $role_model->addActivityRole($role_id, 20);
}
/**
 * Upgrades a Version 44 version of the Yioop database to a Version 45 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion45(&$db)
{
    $sql = "DELETE FROM SCRAPER";
    $db->execute($sql);
    $sql = "INSERT INTO SCRAPER(NAME, SIGNATURE, SCRAPE_RULES) VALUES (?,?,?)";
    $scrapers = [
        ["YIOOP", "/html/head/*[contains(@href,".
            "'c=resource&amp;a=get&amp;f=css&amp;n=auxiliary.css')]",
            "//div[contains(@class, 'body-container')]###" .
            "//*[contains(@id, 'message')]###//*[contains(@id, 'help')]###" .
            "//*[contains(@id, 'MathJax')]###" .
            "//*[contains(@class, 'component-container')]###" .
            "//*[contains(@class, 'top-bar')]###".
            "//*[contains(@class, 'query-statistics')]###" .
            "//*[contains(@class, 'admin-collapse')]###" .
            "//option[not(contains(@selected, 'selected'))]###" .
            "//*[contains(@id, 'suggest')]###//*[contains(@id, 'spell')]"],
        ["DRUPAL", "/html/head/*[contains(@href, '/sites/all/themes') or " .
            "contains(@href, '/sites/default/files') or ".
            "contains(@content, 'Drupal')]",
            "//div[@id='page']|//main" .
            "###//*[contains(@id,'comments')]" .
            "###//*[contains(@id,'respond')]" .
            "###//*[contains(@class,'bottomcontainerBox')]" .
            "###//*[contains(@class,'post-by')]" .
            "###//*[contains(@class,'entry meta-clear')]"],
        ["MEDIAWIKI", "//meta[contains(@content, 'MediaWiki')]",
            "//*[contains(@id, 'mw-content-text')]###".
            "//*[contains(@class, 'nmbox')]###" .
            "//*[contains(@class, 'hatnote')]###" .
            "//*[contains(@class, 'infobox')]"],
        ["VBULLETIN", "/html/head/*[contains(@href,'vbulletin')]",
            "//div[contains(@class, 'body_wrapper')]###" .
            "//*[contains(@id, 'above')]###//*[contains(@id, 'below')]###" .
            "//*[contains(@id, 'breadcrumb')]###" .
            "//*[contains(@id, 'notices')]###" .
            "//*[contains(@id, 'footer')]###".
            "//*[contains(@id, 'forum_info_options')]###" ."
            //*[contains(@class, 'threadlisthead')]###" ."
            //*[contains(@class, 'threaddetails')]###".
            "//*[contains(@id, 'pagination')]###".
            "//*[contains(@class, 'threadstats')]###".
            "//*[contains(@class, 'threadlastpost')]###".
            "//span[contains(@class, 'label')]"],
        ["WORDPRESS", "/html/head/*[contains(@href, 'wp-content')".
            " or contains(@href, 'wp-includes')]",
            "//div[starts-with(@id, 'post-') and " .
            "'post-' = translate(@id, '0123456789', '') and " .
            "string-length(@id) >4]|//div[contains(@class, 'homepagewrapper')]".
            "###//*[contains(@id, 'entry-comments')]###" .
            "//*[contains(@class, 'sharedaddy')]###" .
            "//*[contains(@class, 'blog-subscribe')]###".
            "//*[contains(@id, 'entry-side')]"]
        ];
    foreach ($scrapers as $scraper) {
        $db->execute($sql, $scraper);
    }
}
/**
 * Upgrades a Version 45 version of the Yioop database to a Version 46 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion46(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $serial = $db->serialType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("CREATE TABLE PAGE_RELATIONSHIP (
        ID $serial PRIMARY KEY $auto_increment, NAME VARCHAR(" .
        C\NAME_LEN . ") UNIQUE)");
    $db->execute("INSERT INTO PAGE_RELATIONSHIP VALUES (-1,
        'generic_links')");
}
/**
 * Upgrades a Version 46 version of the Yioop database to a Version 47 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion47(&$db)
{
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE CHAT_BOT (
        USER_ID $integer PRIMARY KEY, BOT_TOKEN CHAR(". C\TIMESTAMP_LEN .
        ") UNIQUE, CALLBACK_URL VARCHAR(" . C\MAX_URL_LEN . "))");
    $role_model->addRole('Bot User');
}
/**
 * Upgrades a Version 47 version of the Yioop database to a Version 48 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion48(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY_OLD(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        UPDATE_PERIOD $integer,
        UPDATE_TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
        NUM_VIEWS $integer, PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
        UPDATE_PERIOD, UPDATE_TIMESTAMP))");
    D\DatasourceManager::copyTable("ITEM_IMPRESSION_SUMMARY", $db,
        "ITEM_IMPRESSION_SUMMARY_OLD", $db);
    $db->execute("DROP TABLE ITEM_IMPRESSION_SUMMARY");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_SUMMARY(
        USER_ID $integer, ITEM_ID $integer, ITEM_TYPE $integer,
        UPDATE_PERIOD $integer, UPDATE_TIMESTAMP NUMERIC(" .
        C\TIMESTAMP_LEN . "), NUM_VIEWS $integer,
        FUZZY_NUM_VIEWS $integer DEFAULT -1, TMP_NUM_VIEWS $integer DEFAULT -1,
        PRIMARY KEY(USER_ID, ITEM_ID, ITEM_TYPE,
        UPDATE_PERIOD, UPDATE_TIMESTAMP))");
    $db->execute("INSERT INTO ITEM_IMPRESSION_SUMMARY (USER_ID,
        ITEM_ID, ITEM_TYPE, UPDATE_PERIOD, UPDATE_TIMESTAMP, NUM_VIEWS)
        SELECT USER_ID, ITEM_ID, ITEM_TYPE, UPDATE_PERIOD, UPDATE_TIMESTAMP,
        NUM_VIEWS FROM ITEM_IMPRESSION_SUMMARY_OLD");
}
/**
 * Upgrades a Version 48 version of the Yioop database to a Version 49 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion49(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE GROUP_PAGE_LINK");
    $db->execute("DROP TABLE GROUP_PAGE_PRE_LINK");
    $db->execute("CREATE TABLE GROUP_PAGE_LINK( LINK_TYPE_ID $integer,
        FROM_ID $integer, TO_ID $integer,
        PRIMARY KEY(LINK_TYPE_ID, FROM_ID, TO_ID))");
    $db->execute("CREATE TABLE GROUP_PAGE_PRE_LINK(LINK_TYPE_ID $integer,
        FROM_ID $integer, TO_GROUP_ID $integer, TO_PAGE_NAME VARCHAR(" .
        C\TITLE_LEN ."), PRIMARY KEY(LINK_TYPE_ID, FROM_ID, TO_GROUP_ID,
        TO_PAGE_NAME))");
}
/**
 * Upgrades a Version 49 version of the Yioop database to a Version 50 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion50(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $private_db_class = C\NS_DATASOURCES . ucfirst(C\PRIVATE_DBMS)."Manager";
    $private_dbinfo = ["DBMS" => C\PRIVATE_DBMS, "DB_HOST" => C\PRIVATE_DB_HOST,
        "DB_NAME" => C\PRIVATE_DB_NAME, "DB_PASSWORD" => C\PRIVATE_DB_PASSWORD];
    $private_db = new $private_db_class();
    $private_db->connect(C\PRIVATE_DB_HOST, C\PRIVATE_DB_USER,
        C\PRIVATE_DB_PASSWORD, C\PRIVATE_DB_NAME);
    $private_auto_increment = $private_db->autoIncrement($private_dbinfo);
    $private_serial = $private_db->serialType($private_dbinfo);
    $private_db->execute("CREATE TABLE TYPE_KEYS (KEY_ID
        $private_serial PRIMARY KEY $private_auto_increment,
        TYPE_ID $integer, KEY_NAME VARCHAR(30) )");
    $db->execute("CREATE TABLE GROUPS_OLD (GROUP_ID $serial PRIMARY KEY
        $auto_increment, GROUP_NAME VARCHAR(" . C\SHORT_TITLE_LEN."),
        CREATED_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN . "),
        OWNER_ID $integer, REGISTER_TYPE $integer,
        MEMBER_ACCESS $integer, VOTE_ACCESS $integer DEFAULT ".
        C\NON_VOTING_GROUP . ", POST_LIFETIME $integer DEFAULT ".
        C\FOREVER . ")");
    D\DatasourceManager::copyTable("GROUPS", $db,
        "GROUPS_OLD", $db);
    $db->execute("DROP TABLE GROUPS");
    $db->execute("CREATE TABLE GROUPS (GROUP_ID $serial PRIMARY KEY
        $auto_increment, GROUP_NAME VARCHAR(" . C\SHORT_TITLE_LEN."),
        CREATED_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN . "),
        OWNER_ID $integer, REGISTER_TYPE $integer,
        MEMBER_ACCESS $integer, VOTE_ACCESS $integer DEFAULT ".
        C\NON_VOTING_GROUP . ", POST_LIFETIME $integer DEFAULT ".
        C\FOREVER . ", ENCRYPTION $integer DEFAULT 0)");
    $db->execute("INSERT INTO GROUPS (GROUP_ID,
        GROUP_NAME, CREATED_TIME, OWNER_ID, REGISTER_TYPE, MEMBER_ACCESS,
        VOTE_ACCESS, POST_LIFETIME)
        SELECT GROUP_ID, GROUP_NAME, CREATED_TIME, OWNER_ID, REGISTER_TYPE,
        MEMBER_ACCESS, VOTE_ACCESS, POST_LIFETIME FROM GROUPS_OLD");
}
/**
 * Upgrades a Version 50 version of the Yioop database to a Version 51 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion51(&$db)
{
    $role_model = new M\RoleModel(C\DB_NAME, false);
    $role_model->db = $db;
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $sql = "SELECT ACTIVITY_ID FROM ACTIVITY ".
        "WHERE METHOD_NAME = 'botStory'";
    $result = $db->execute($sql, []);
    if ($result) {
        $row = $db->fetchArray($result);
        if  ($row) { //activity already exists
            return;
        }
    }
    addActivityAtId($db, 'db_activity_botstory',
        "botStory", 21);
    updateTranslationForStringId($db, 'db_activity_botstory',
        'en-US', 'Bot Story');
    $role_id = $role_model->getRoleId('Bot User');
    $role_model->addActivityRole($role_id, 21);
    $sql="CREATE TABLE INTENT(INTENT_ID $integer PRIMARY KEY $auto_increment,
        INTENT VARCHAR(" . C\MAX_DESCRIPTION_LEN . "),USER_ID $integer)";
    $db->execute($sql);
    $db->execute("CREATE TABLE EXPRESSION(EXPRESSION_ID $integer
          PRIMARY KEY $auto_increment,
          EXPRESSION VARCHAR(" . C\MAX_DESCRIPTION_LEN ."),
          INTENT_ID $integer)");
    $db->execute("CREATE TABLE ENTITY(ENTITY_ID $integer
        PRIMARY KEY $auto_increment,
        ENTITY_NAME VARCHAR(" . C\MAX_DESCRIPTION_LEN ."),
        USER_ID $integer)");
    $db->execute("CREATE TABLE INTENT_EXPRESSION_ENTITY(ID $integer
        PRIMARY KEY $auto_increment,
        INTENT_ID $integer, EXPRESSION_ID $integer, ENTITY_ID $integer,
        ENTITY_VALUE VARCHAR(" . C\MAX_DESCRIPTION_LEN ."),
        PRIMARY KEY(INTENT_ID,EXPRESSION_ID,ENTITY_ID))");
    $db->execute("CREATE TABLE BOT_TERM_FREQUENCY(TERM VARCHAR
        (" . C\MAX_DESCRIPTION_LEN . "), FREQUENCY $integer,
         INTENT_ID $integer, PRIMARY KEY(TERM, INTENT_ID))");
}
/**
 * Upgrades a Version 51 version of the Yioop database to a Version 52 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion52(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE ITEM_IMPRESSION_STAT(ITEM_ID $integer,
        ITEM_TYPE $integer, UPDATE_PERIOD $integer, SUM $integer DEFAULT -1,
        FUZZY_NUM_VIEWS $integer DEFAULT -1)");
}
/**
 * Upgrades a Version 52 version of the Yioop database to a Version 53 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion53(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("CREATE TABLE AVERAGE_RATING (AVG_RATING $integer)");
    $db->execute("INSERT INTO AVERAGE_RATING VALUES (0)");
    $db->execute("CREATE TABLE IDF (WORD_ID $integer, ITEM_IMP FLOAT,
        USER_IMP FLOAT)");
    $db->execute("CREATE TABLE IDF_TEMP (WORD_ID $integer, ITEM_IMP
        FLOAT, USER_IMP FLOAT)");
    $db->execute("CREATE TABLE ITEM_BIAS(ITEM_ID $integer,
        ITEM_BIAS FLOAT)");
    $db->execute("CREATE TABLE ITEM_WORD_FREQUENCY (ITEM_ID $integer,
        WORD_ID $integer, FREQUENCY $integer, LOGFREQUENCY FLOAT)");
    $db->execute("CREATE TABLE ITEM_WORD_WEIGHTS(WORD_ID $integer,
        ITEM_ID $integer, WEIGHT FLOAT)");
    $db->execute("CREATE TABLE LIVE_TABLE (TRENDING $integer,
        THREADS $integer, GROUPS $integer)");
    $db->execute("INSERT INTO LIVE_TABLE VALUES (1,1,1)");
    $db->execute("CREATE TABLE PREDICTED_TABLE(USER_ID $integer,
        ITEM_ID $integer, USER_BIAS FLOAT, ITEM_BIAS FLOAT, RATING FLOAT)");
    $db->execute("CREATE TABLE RECOMMENDATION_LIST_GROUPS_1
        (USER_ID $integer, GROUP_1 $integer, GROUP_2
        $integer, GROUP_3 v, RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE RECOMMENDATION_LIST_GROUPS_2
        (USER_ID $integer, GROUP_1 $integer, GROUP_2
        $integer, GROUP_3 $integer, RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE RECOMMENDATION_LIST_THREADS_1
        (USER_ID $integer, GROUP_1 $integer, GROUP_2 $integer, GROUP_3 $integer,
        RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE RECOMMENDATION_LIST_THREADS_2
        (USER_ID $integer, GROUP_1 $integer, GROUP_2 $integer, GROUP_3 $integer,
        RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE RECOMMENDATION_TRENDING_THREADS_1
        (USER_ID $integer, GROUP_1 $integer, GROUP_2 $integer, GROUP_3 $integer,
        RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE RECOMMENDATION_TRENDING_THREADS_2
        (USER_ID $integer, GROUP_1 $integer, GROUP_2 $integer, GROUP_3 $integer,
        RECOMMEND_TYPE VARCHAR)");
    $db->execute("CREATE TABLE SIMILARITY
        (USER_ID $integer, ITEM_ID $integer, COSINE_SIMILARITY FLOAT)");
    $db->execute("CREATE TABLE USER_BIAS
        (USER_ID $integer, USER_BIAS FLOAT)");
    $db->execute("CREATE TABLE USER_WORD_FREQUENCY
        (FREQUENCY $integer, USER_ID $integer, WORD_ID $integer,
        LOGFREQUENCY FLOAT)");
    $db->execute("CREATE TABLE USER_WORD_FREQUENCY_TEMP
        (FREQUENCY $integer, USER_ID $integer, WORD_ID $integer,
        LOGFREQUENCY FLOAT)");
    $db->execute("CREATE TABLE USER_WORD_WEIGHTS
        (WORD_ID $integer, USER_ID $integer, WEIGHT FLOAT)");
    $db->execute("CREATE TABLE USER_WORD_WEIGHTS_TEMP
        (WORD_ID $integer, USER_ID $integer, WEIGHT FLOAT)");
    $db->execute("CREATE TABLE WORDS
        (WORD_ID $integer, WORD VARCHAR)");
    $db->execute("CREATE TABLE WORDS_TEMP (WORD_ID $integer)");
    $db->execute("CREATE TABLE SIMILARITY_TEMP
        (USER_ID $integer, ITEM_ID $integer, COSINE_SIMILARITY FLOAT)");
    $db->execute("CREATE TABLE USER_THREAD_GROUP
        (USER_ID $integer, ITEM_ID $integer, GROUP_ID $integer)");
}
/**
 * Upgrades a Version 53 version of the Yioop database to a Version 54 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion54(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $former_tables_which_should_be_deleted = [
        "AVERAGE_RATING", "IDF", "IDF_TEMP", "ITEM_BIAS", "ITEM_RECOMMENDATION",
        "ITEM_TERM_FREQUENCY", "ITEM_WORD_FREQUENCY", "ITEM_TERM_WEIGHTS",
        "ITEM_USER_IDF", "ITEM_WORD_WEIGHTS", "LIVE_TABLE",
        "PREDICTED_TABLE", "RECOMMENDATION_LIST_GROUPS_1",
        "RECOMMENDATION_LIST_GROUPS_2", "RECOMMENDATION_LIST_GROUPS_3",
        "RECOMMENDATION_LIST_THREADS_1", "RECOMMENDATION_LIST_THREADS_2",
        "RECOMMENDATION_LIST_THREADS_3", "RECOMMENDATION_TRENDING_THREADS_1",
        "RECOMMENDATION_TRENDING_THREADS_2",
        "RECOMMENDATION_TRENDING_THREADS_3", "SIMILARITY", "USER_BIAS",
        "USER_ITEM_SIMILARITY", "USER_TERM_FREQUENCY", "USER_TERM_WEIGHTS",
        "USER_WORD_FREQUENCY", "USER_WORD_FREQUENCY_TEMP",
        "USER_WORD_WEIGHTS", "USER_WORD_WEIGHTS_TEMP", "WORDS",
        "WORD_TEMP", "SIMILARITY_TEMP", "USER_THREAD_GROUP"
    ];
    foreach ($former_tables_which_should_be_deleted as $table) {
        $db->execute("DROP TABLE IF EXISTS $table");
    }
    $new_tables_and_indexes_sql = [
        "CREATE TABLE ITEM_RECOMMENDATION (ITEM_ID $integer,
            USER_ID $integer, ITEM_TYPE $integer,
            SCORE FLOAT, TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "))",
        "CREATE INDEX IR_USER_ID_INDEX ON ITEM_RECOMMENDATION(USER_ID)",
        "CREATE TABLE ITEM_TERM_FREQUENCY (ITEM_ID $integer, TERM_ID $integer,
            FREQUENCY $integer, LOG_FREQUENCY FLOAT,
            PRIMARY KEY(TERM_ID, TERM_ID))",
        "CREATE INDEX ITF_TERM_ID_INDEX ON ITEM_TERM_FREQUENCY(TERM_ID)",
        "CREATE TABLE ITEM_TERM_WEIGHTS (TERM_ID $integer, ITEM_ID $integer,
            WEIGHT FLOAT, PRIMARY KEY(TERM_ID, ITEM_ID))",
        "CREATE TABLE USER_ITEM_SIMILARITY (USER_ID $integer,
            THREAD_ID $integer, SIMILARITY FLOAT, GROUP_MEMBER $integer,
            PRIMARY KEY(USER_ID, THREAD_ID))",
        "CREATE TABLE USER_TERM_FREQUENCY (USER_ID $integer, TERM_ID $integer,
            FREQUENCY $integer, LOG_FREQUENCY FLOAT,
            PRIMARY KEY(USER_ID, TERM_ID))",
        "CREATE INDEX UTF_TERM_ID_INDEX ON USER_TERM_FREQUENCY(TERM_ID)",
        "CREATE TABLE USER_TERM_WEIGHTS (TERM_ID $integer, USER_ID $integer,
            WEIGHT FLOAT, PRIMARY KEY(TERM_ID, USER_ID))" ];
        foreach ($new_tables_and_indexes_sql as $sql) {
            $db->execute($sql);
        }
}
/**
 * Upgrades a Version 54 version of the Yioop database to a Version 55 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion55(&$db)
{
    $db->execute("CREATE TABLE LEXICON(
        TERM VARCHAR(". C\LONG_NAME_LEN ."),
        LOCALE VARCHAR(" . C\NAME_LEN . "),
        PART_OF_SPEECH VARCHAR(16), PRIMARY KEY(TERM, LOCALE))");
    // Retrieve the locales added to the Locale table
    $sql = "SELECT LOCALE_TAG from LOCALE";
    $result = $db->execute($sql);
    if ($result) {
        $locales = $db->fetchArray($result);
    }
    /*
     * Go through the locales, check of there is a lexicon,
     * if present then add it to the Lexicon database.
     * as (term, part_of_speech, locale)
     */
    foreach ($locales as $locale) {
        $folder_name = $locale;
        if (strstr($locale, "-")) {
            $locale_name = explode("-", $locale);
            $folder_name = $locale_name . "_" . $locale_name;
        }
        $lexicon_file = C\LOCALE_DIR . "/" . $folder_name .
            "/resources/lexicon.txt.gz";
        if (file_exists($lexicon_file)) {
            $lines = gzfile($lexicon_file);
            $insert_values = "";
            $count = 0;
            foreach ($lines as $line) {
                $line = trim($line, " ");
                $line = explode(" ", $line);
                $insert_values .= '(' . trim($line[0]) . ',' . $locale[0] .
                    ',' . trim($line[1]) . '),';
                $count++;
                if ($count >= C\NUM_LEX_BULK_INSERTS) {
                    $insert_values = rtrim($insert_values, ',');
                    $query = "INSERT INTO LEXICON (TERM, LOCALE, PART_OF_SPEECH)
                     VALUES {$insert_values}";
                    $db->exec($query);
                    $insert_values = "";
                    $count = 0;
                }
            }
            if ($count > 0) {
                $insert_values = rtrim($insert_values, ',');
                $query = "INSERT INTO LEXICON (TERM, LOCALE, PART_OF_SPEECH)
                    VALUES $insert_values";
                $db->exec($query);
            }
        }
    }
}
/**
 * Upgrades a Version 56 version of the Yioop database to a Version 5 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion57(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    if (ucfirst(C\DBMS) == "Sqlite3" || substr(C\DB_HOST, 0, 6) == 'sqlite') {
        $db->execute("ALTER TABLE USERS RENAME TO USERS_OLD");
        $db->execute("CREATE TABLE USERS(USER_ID $serial PRIMARY KEY
            $auto_increment, FIRST_NAME VARCHAR(" . C\NAME_LEN."),
            LAST_NAME VARCHAR(" . C\NAME_LEN . "), USER_NAME VARCHAR(" .
            C\NAME_LEN .") UNIQUE, EMAIL VARCHAR(" . C\LONG_NAME_LEN . "),
            PASSWORD VARCHAR(" . C\LONG_NAME_LEN . "), STATUS $integer,
            HASH VARCHAR(" . C\LONG_NAME_LEN . "),
            CREATION_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN .
            "), UPS $integer DEFAULT 0,
            DOWNS $integer DEFAULT 0)");
        $db->execute("INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME,
            USER_NAME, EMAIL, PASSWORD, STATUS, HASH, CREATION_TIME, UPS,
            DOWNS)
            SELECT USER_ID, FIRST_NAME, LAST_NAME, USER_NAME, EMAIL, PASSWORD,
            STATUS, HASH, CREATION_TIME, UPS, DOWNS
            FROM USERS_OLD");
    } else {
        // only one of these should do anything
        $db->execute("ALTER TABLE USERS DROP COLUMN IS_ADVERTISER");
        $db->execute("ALTER TABLE USERS DROP COLUMN USES_STORE");
    }
}
/**
 * Upgrades a Version 57 version of the Yioop database to a Version 58 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion58(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("ALTER TABLE MACHINE RENAME TO MACHINE_OLD");
    $db->execute("CREATE TABLE MACHINE (NAME VARCHAR(" . C\NAME_LEN
        .") PRIMARY KEY, URL VARCHAR(" . C\MAX_URL_LEN .
        "), CHANNEL $integer, NUM_FETCHERS $integer, PARENT VARCHAR(" .
        C\NAME_LEN . ") )");
    D\DatasourceManager::copyTable("MACHINE_OLD", $db, "MACHINE",
        $db);
    $db->execute("DROP TABLE MACHINE_OLD");
}
/**
 * Upgrades a Version 58 version of the Yioop database to a Version 59 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion59(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE USER_SESSION");
    $db->execute("CREATE TABLE USER_SESSION (
        USER_ID $integer PRIMARY KEY, SESSION VARCHAR(" .
        C\MAX_USER_SESSION_SIZE . ") )" );
}
/**
 * Upgrades a Version 59 version of the Yioop database to a Version 60 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion60(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $integer = $db->integerType($dbinfo);
    $db->execute("DELETE FROM MEDIA_SOURCE WHERE TYPE='video'");
    $db->execute("ALTER TABLE SCRAPER RENAME TO SCRAPER_OLD");
    $db->execute("CREATE TABLE SCRAPER (ID $serial PRIMARY KEY " .
        "$auto_increment, NAME VARCHAR(" . C\TITLE_LEN . "), " .
        "PRIORITY $integer DEFAULT 0, " .
        "SIGNATURE VARCHAR(" . C\MAX_URL_LEN . ")," .
        "TEXT_PATH VARCHAR(" . C\MAX_URL_LEN . ") DEFAULT ''," .
        "DELETE_PATHS VARCHAR(" . (10 *C\MAX_URL_LEN)  . ") DEFAULT '', " .
        "EXTRACT_FIELDS VARCHAR(" . (10 * C\MAX_URL_LEN) . ")  DEFAULT '')");
    $sql = "SELECT * FROM SCRAPER_OLD";
    if (($result = $db->execute($sql)) !== false) {
        while ($row = $db->fetchArray($result)) {
            if (strpos($row['SCRAPE_RULES'], "###") === false) {
                $row["TEXT_PATH"] = $row['SCRAPE_RULES'];
                $row["DELETE_PATHS"] = "";
            } else {
                list($row["TEXT_PATH"], $paths) =
                    explode("###", $row['SCRAPE_RULES'], 2);
                $row['DELETE_PATHS'] = trim(implode("\n", explode("###",
                    $paths)));
            }
            $row['PRIORITY'] = 0;
            $row['EXTRACT_FIELDS'] = "";
            unset($row['SCRAPE_RULES']);
            $statement = "INSERT INTO SCRAPER (";
            $comma = "";
            foreach ($row as $col => $value) {
                $statement .= $comma . $col;
                $comma = ",";
            }
            $statement .= ") VALUES (";
            $comma = "";
            foreach ($row as $col => $value) {
                $statement .= $comma . " '" . $db->escapeString($value) .
                    "'";
                $comma = ",";
            }
            $statement .= ")";
            if (($db->execute($statement)) === false) {
                return false;
            }
        }
    }
    $sql = "INSERT INTO SCRAPER(NAME, SIGNATURE, TEXT_PATH, DELETE_PATHS,
        EXTRACT_FIELDS) VALUES (?, ?, ?, ?, ?)";
    $scrapers = [
        ["VIDEO SITE",
        "//meta[@property='og:type' and contains(@content, 'video')]",
        "", "", "IS_VIDEO=//meta[@property='og:type' and" .
        " contains(@content, 'video')]/@content\n" .
        "SITE_NAME=//meta[@property='og:site_name']/@content\n" .
        "DURATION=//meta[@property='video:duration']/@content\n" .
        "THUMB_URL=//meta[@property='og:image']/@content"],
    ];
    foreach ($scrapers as $scraper) {
        $db->execute($sql, $scraper);
    }
    $db->execute("DROP TABLE SCRAPER_OLD");
}
/**
 * Upgrades a Version 60 version of the Yioop database to a Version 61 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion61(&$db)
{
    $db->execute("ALTER TABLE MEDIA_SOURCE RENAME TO MEDIA_SOURCE_OLD");
    $db->execute("CREATE TABLE MEDIA_SOURCE (
        TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . ") PRIMARY KEY,
        NAME VARCHAR(" . C\LONG_NAME_LEN . "),
        TYPE VARCHAR(" . C\NAME_LEN . "),
        CATEGORY VARCHAR(" . C\NAME_LEN . ") DEFAULT 'news',
        SOURCE_URL VARCHAR(" . C\MAX_URL_LEN . "), AUX_INFO VARCHAR(".
        C\MAX_URL_LEN . "), LANGUAGE VARCHAR(" . C\NAME_LEN . "))");
    $db->execute("INSERT INTO MEDIA_SOURCE (TIMESTAMP, NAME, TYPE,
        SOURCE_URL, AUX_INFO, LANGUAGE)
        SELECT TIMESTAMP, NAME, TYPE, SOURCE_URL, AUX_INFO, LANGUAGE
        FROM MEDIA_SOURCE_OLD");
    $db->execute("DROP TABLE MEDIA_SOURCE_OLD");
}
/**
 * Upgrades a Version 61 version of the Yioop database to a Version 62 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion62(&$db)
{
    $db->execute("CREATE TABLE TRENDING_TERM (
        TERM VARCHAR(" . C\TITLE_LEN . "),
        OCCURRENCES FLOAT DEFAULT 0,
        UPDATE_PERIOD NUMERIC,
        TIMESTAMP NUMERIC(" . C\TIMESTAMP_LEN . "),
        LANGUAGE VARCHAR(" . C\NAME_LEN . ")
        )");
}
/**
 * Upgrades a Version 63 version of the Yioop database to a Version 64 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion64(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("DROP TABLE ITEM_IMPRESSION_STAT");
    $db->execute("CREATE TABLE ITEM_IMPRESSION_STAT(ITEM_ID $integer,
        ITEM_TYPE $integer, UPDATE_PERIOD $integer,
        NUM_VIEWS $integer DEFAULT -1,
        FUZZY_NUM_VIEWS $integer DEFAULT -1)");
}
/**
 * Upgrades a Version 64 version of the Yioop database to a Version 65 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion65(&$db)
{
    $db->execute("ALTER TABLE TRENDING_TERM ADD COLUMN CATEGORY VARCHAR(" .
        C\TITLE_LEN . ") DEFAULT 'news'");
    //Create a Group for Search Result Edits
    $group_model = new M\GroupModel(C\DB_NAME, false);
    $group_model->db = $db;
    $group_id = $group_model->getGroupId('Search');
    if ($group_id <= 0) {
        $group_model->addGroup('Search', C\ROOT_ID,
            C\PUBLIC_BROWSE_REQUEST_JOIN, C\GROUP_READ_WIKI,
            C\UP_DOWN_VOTING_GROUP, C\FOREVER, 0);
        $group_id = $group_model->getGroupId('Search');
        if ($group_id <= 0) {
            return; // upgrade fail
        }
    }
    $group_model->addUserGroup(C\PUBLIC_USER_ID, $group_id);
    $local_config_file = C\BASE_DIR . "/configs/LocalConfig.php";
    if (file_exists($local_config_file)) {
        $local_config = file_get_contents($local_config_file);
    } else {
        $local_config = "<";
        $local_config .= <<< EOD
?php
/**
 * Local configuration overrides
 * @package configs
 */
namespace seekquarry\yioop\configs;
EOD;
    }
    if (strstr($local_config, 'SEARCH_GROUP_ID') === false) {
        $local_config .= "\nnsdefine('SEARCH_GROUP_ID', $group_id);\n";
        file_put_contents($local_config_file, $local_config);
    }
}
/**
 * Upgrades a Version 65 version of the Yioop database to a Version 66 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion66(&$db)
{
    $db->execute("ALTER TABLE GROUP_ITEM ADD COLUMN URL VARCHAR(" .
        C\TITLE_LEN . ") DEFAULT ''");
}
/**
 * Upgrades a Version 66 version of the Yioop database to a Version 67 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion67(&$db)
{
    $db->execute("ALTER TABLE ROLE_ACTIVITY ADD COLUMN " .
        "ALLOWED_ARGUMENTS VARCHAR(" . C\MAX_URL_LEN . ") DEFAULT 'all'");
    $db->execute("ALTER TABLE SUBSEARCH ADD COLUMN " .
        "DEFAULT_QUERY VARCHAR(" . C\TITLE_LEN . ") DEFAULT ''");
}
/**
 * Upgrades a Version 67 version of the Yioop database to a Version 68 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion68(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $integer = $db->integerType($dbinfo);
    $db->execute("ALTER TABLE MIX_COMPONENTS ADD COLUMN " .
        "DIRECTION $integer DEFAULT 1");
    $db->execute("DELETE FROM MIX_COMPONENTS WHERE TIMESTAMP = 4 AND
        FRAGMENT_ID = 0");
    $db->execute("INSERT INTO MIX_COMPONENTS(TIMESTAMP,
        FRAGMENT_ID, CRAWL_TIMESTAMP, WEIGHT, DIRECTION, KEYWORDS)
        VALUES (4, 0, 100, 1, -1, 'media:news')");
    $db->execute("DROP TABLE FEED_ITEM");
    if (file_exists(C\WORK_DIRECTORY . "/feeds")) {
        $db->unlinkRecursive(C\WORK_DIRECTORY . "/feeds");
    }
}

/**
 * Upgrades a Version 68 version of the Yioop database to a Version 69 version
 * @param object $db datasource to use to upgrade.
 */
function upgradeDatabaseVersion69(&$db)
{
    $dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
        "DB_NAME" => C\DB_NAME, "DB_PASSWORD" => C\DB_PASSWORD];
    $auto_increment = $db->autoIncrement($dbinfo);
    $serial = $db->serialType($dbinfo);
    $integer = $db->integerType($dbinfo);
    $db->execute("ALTER TABLE USERS RENAME TO USERS_OLD");
    $db->execute("CREATE TABLE USERS(USER_ID $serial PRIMARY KEY
        $auto_increment, FIRST_NAME VARCHAR(" . C\NAME_LEN."),
        LAST_NAME VARCHAR(" . C\NAME_LEN . "), USER_NAME VARCHAR(" .
        C\NAME_LEN .") UNIQUE, EMAIL VARCHAR(" . C\LONG_NAME_LEN . "),
        PASSWORD VARCHAR(" . C\LONG_NAME_LEN . "), STATUS $integer,
        HASH VARCHAR(" . C\LONG_NAME_LEN . "),
        CREATION_TIME VARCHAR(" . C\MICROSECOND_TIMESTAMP_LEN .
        "), UPS $integer DEFAULT 0, DOWNS $integer DEFAULT 0)");
    $db->execute("INSERT INTO USERS (USER_ID, FIRST_NAME, LAST_NAME,
        USER_NAME, EMAIL, PASSWORD, STATUS, HASH, CREATION_TIME, UPS, DOWNS)
        SELECT USER_ID, FIRST_NAME, LAST_NAME, USER_NAME, EMAIL, PASSWORD,
            STATUS, HASH, CREATION_TIME, UPS, DOWNS
        FROM USERS_OLD");
}
