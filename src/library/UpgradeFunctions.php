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
 * This file contains global functions to check whether
 * upgrading the database or locales is needed as wells as auxiliary functions
 * to be used by the VersionFunctions.php code to actually carry out
 * upgrades between versions
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

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Checks to see if the locale data of Yioop! of a locale in the work dir is
 * older than the currently running Yioop!
 *
 * @param string $locale_tag locale to check directory of
 */
function upgradeLocalesCheck($locale_tag)
{
    if (!C\PROFILE) {
        return;
    }
    $dir_locale_tag = str_replace("-", "_", $locale_tag);
    $config_name = C\LOCALE_DIR . "/$dir_locale_tag/configure.ini";
    $fallback_config_name =
        C\FALLBACK_LOCALE_DIR . "/$dir_locale_tag/configure.ini";
    if (tl('view_locale_version') != C\RESOURCES_WIKI_VERSION ||
        filemtime($fallback_config_name) > filemtime($config_name)) {
        return "locale";
    }
    return false;
}
/**
 * If the locale data of Yioop! in the work directory is older than the
 * currently running Yioop! then this function is called to at least
 * try to copy the new strings into the old profile.
 */
function upgradeLocales()
{
    if (!C\PROFILE) {
        return;
    }
    $locale = new M\LocaleModel();
    $locale->initialize(C\DEFAULT_LOCALE);
    $force_folders = [];
    /*
        if we're upgrading version2 to 3 we want to make sure stemmer becomes
        tokenizer, version3 to 4 pushes out stopwordsRemover used for
        summarization. version 6 to 7 adds stemmers for french, english,
        german.
        version 7 to 8 adds stemmers for russian and spanish
    */
    if (empty($locale->configure['strings']["view_locale_version"]) ||
        $locale->configure['strings']["view_locale_version"]
        != C\RESOURCES_WIKI_VERSION) {
        $force_folders = ["resources"];
        upgradePublicHelpWiki($locale->db);
    }
    $locale->extractMergeLocales($force_folders);
}
/**
 * Used to force push the default Public and Wiki pages into the current
 * database
 * @param resource &$db datasource to use to upgrade
 */
function upgradePublicHelpWiki(&$db)
{
    /** For new wiki pages */
    require_once C\BASE_DIR . "/configs/PublicHelpPages.php";
    $group_model = new M\GroupModel(C\DB_NAME, false);
    $group_model->db = $db;
    $default_locale = getLocaleTag();
    foreach ($public_pages as $locale_tag => $locale_pages) {
        setLocaleObject($locale_tag);
        foreach ($locale_pages as $page_name => $page_content) {
            $page_info = $group_model->getPageInfoByName(C\PUBLIC_GROUP_ID,
                $page_name, $locale_tag, "edit");
            if (empty($page_info) ||
                ($page_content != $page_info['PAGE'] &&
                (in_array($page_info['EDIT_COMMENT'], ['upgrade', 'create'])))||
                (!empty($page_info['PUBDATE']) &&
                $page_info['PUBDATE'] < 1555802108) ) {
                $group_model->setPageName(C\ROOT_ID, C\PUBLIC_GROUP_ID,
                    $page_name, $page_content, $locale_tag, "upgrade",
                    tl('social_component_page_created', $page_name),
                    tl('social_component_page_discuss_here'));
            }
        }
    }
    //Insert Default Public Help pages
    foreach ($help_pages as $locale_tag => $locale_pages) {
        setLocaleObject($locale_tag);
        foreach ($locale_pages as $page_name => $page_content) {
            $page_info = $group_model->getPageInfoByName(C\HELP_GROUP_ID,
                $page_name, $locale_tag, "edit");
            if (empty($page_info) || ($page_content != $page_info['PAGE'] &&
                (in_array($page_info['EDIT_COMMENT'], ['upgrade', 'create']) ||
                empty($page_info['PUB_DATE']) ||
                $page_info['PUB_DATE'] < 1593297907)) ) {
                $group_model->setPageName(C\ROOT_ID, C\HELP_GROUP_ID,
                    $page_name, $page_content, $locale_tag, "upgrade",
                    tl('social_component_page_created', $page_name),
                    tl('social_component_page_discuss_here'));
            }
        }
    }
    setLocaleObject($default_locale);
}
/**
 * Checks to see if the database data or work_dir folder of Yioop! is from an
 * older version of Yioop! than the currently running Yioop!
 */
function upgradeDatabaseWorkDirectoryCheck()
{
    $model = new M\Model();
    $sql = "SELECT ID FROM VERSION";
    set_error_handler(null);
    for ($i = 0; $i < 3; $i++) {
        $result = @$model->db->execute($sql);
        if ($result !== false) {
            $row = $model->db->fetchArray($result);
            if ((isset($row['ID']) && $row['ID'] >= C\DATABASE_VERSION) ||
                (isset($row['id']) && $row['id'] >= C\DATABASE_VERSION)) {
                return false;
            } else {
                return true;
            }
        }
        sleep(3);
    }
    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    webExit();
}
/**
 * If the database data of Yioop is older than the version of the
 * currently running Yioop then this function is called to try
 * upgrade the database to the new version
 */
function upgradeDatabaseWorkDirectory()
{
    $model = new M\Model();
    $sql = "SELECT ID FROM VERSION";
    set_error_handler(null);
    $result = @$model->db->execute($sql);
    if ($result !== false) {
        $row = $model->db->fetchArray($result);
        if (!empty($row['ID'])) {
            $current_version = min($row['ID'], C\DATABASE_VERSION);
        } else if (!empty($row['id'])) {
            $current_version = min($row['id'], C\DATABASE_VERSION);
        } else {
            $current_version = 1;
        }
    } else {
        webExit(); // maybe someone else has locked DB, so bail
    }
    set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    $result = null; //don't lock db if sqlite
    $versions = range(1, C\DATABASE_VERSION);
    $key = array_search($current_version, $versions);
    $versions = array_slice($versions, $key + 1);
    foreach ($versions as $version) {
        $upgrade_db = C\NS_LIB . "upgradeDatabaseVersion$version";
        if (function_exists($upgrade_db)) {
            $upgrade_db($model->db);
        }
    }
    updateVersionNumber($model->db, C\DATABASE_VERSION);
}

/**
 * Update the database version number to a new number
 * @param object $db datasource for Yioop database
 * @param int $number the new database number
 */
function updateVersionNumber(&$db, $number)
{
    $db->execute("DELETE FROM VERSION");
    $db->execute("INSERT INTO VERSION VALUES ($number)");
}
/**
 * Reads the Help articles from default db and returns the array of pages.
 */
function getWikiHelpPages()
{
    $help_pages = [];
    $default_dbm = new D\Sqlite3Manager();
    $default_dbm->connect("", "", "", C\BASE_DIR . "/data/public_default.db");
    if (!$default_dbm) {
        return false;
    }
    $group_model = new M\GroupModel(C\DB_NAME, true);
    $group_model->db = $default_dbm;
    $page_list = $group_model->getPageList(
        C\HELP_GROUP_ID, "en-US", '', 0, 200);
    foreach ($page_list[1] as $page) {
        if (isset($page['TITLE'])) {
            $page_info = $group_model->getPageInfoByName(
                C\HELP_GROUP_ID, $page['TITLE'], "en-US", "api");
            $page_content = str_replace("&amp;", "&", $page_info['PAGE']);
            $page_content = html_entity_decode($page_content, ENT_QUOTES,
                "UTF-8");
            $help_pages[$page['TITLE']] = $page_content;
        }
    }
    return $help_pages;
}
/**
 * Used to insert a new activity into the database at a given activity_id
 *
 * Inserting at an ID rather than at the end is useful since activities are
 * displayed in admin panel in order of increasing id.
 *
 * @param resource &$db database handle where Yioop database stored
 * @param string $string_id message identifier to give translations for
 *     for activity
 * @param string  $method_name admin_controller method to be called to perform
 *      this activity
 * @param int $activity_id the id location at which to create this activity
 *     activity at and below this location will be shifted down by 1.
 */
function addActivityAtId(&$db, $string_id, $method_name, $activity_id)
{
    $db->execute("UPDATE ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 WHERE ".
        "ACTIVITY_ID >= ?", [$activity_id]);
    $sql = "SELECT * FROM ACTIVITY WHERE ACTIVITY_ID >= ?
        ORDER BY ACTIVITY_ID DESC";
    $result = $db->execute($sql, [$activity_id]);
    while ($row = $db->fetchArray($result)) {
        $db->execute("INSERT INTO ACTIVITY VALUES (?, ?, ?)",
            [($row['ACTIVITY_ID'] + 1), $row['TRANSLATION_ID'],
            $row['METHOD_NAME']]);
        $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID = ?",
            [$row['ACTIVITY_ID']]);
    }
    if (!in_array($method_name, ["manageAdvertisements", "manageCredits"])) {
        $db->execute("UPDATE ROLE_ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 ".
            "WHERE ACTIVITY_ID >= ?", [$activity_id]);
        //give root account permissions on the activity.
        $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, ?)",
            [$activity_id]);
    }
    $sql = "SELECT COUNT(*) AS NUM FROM TRANSLATION";
    $result = $db->execute($sql);
    if (!$result || !($row = $db->fetchArray($result))) {
        echo "Upgrade activity error";
        exit();
    }
    //some search id start at 1000, so +1001 ensures we steer clear of them
    $translation_id = $row['NUM'] + 1001;
    $db->execute("INSERT INTO ACTIVITY VALUES (?, ?, ?)",
        [$activity_id, $translation_id, $method_name]);
    $db->execute("INSERT INTO TRANSLATION VALUES (?, ?)",
        [$translation_id, $string_id]);
}
/**
 * Adds or replaces a translation for a database message string for a given
 * IANA locale tag.
 *
 * @param resource &$db database handle where Yioop database stored
 * @param string $string_id message identifier to give translation for
 * @param string $locale_tag  the IANA language tag to update the strings of
 * @param string $translation the translation for $string_id in the language
 *     $locale_tag
 */
function updateTranslationForStringId(&$db, $string_id, $locale_tag,
    $translation)
{
    $sql = "SELECT LOCALE_ID FROM LOCALE ".
        "WHERE LOCALE_TAG = ? " . $db->limitOffset(1);
    $result = $db->execute($sql, [$locale_tag]);
    $row = $db->fetchArray($result);
    $locale_id = $row['LOCALE_ID'];
    $sql = "SELECT TRANSLATION_ID FROM TRANSLATION ".
        "WHERE IDENTIFIER_STRING = ? " . $db->limitOffset(1);
    $result = $db->execute($sql, [$string_id]);
    $row = $db->fetchArray($result);
    $translate_id = $row['TRANSLATION_ID'];
    $sql = "DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID =? AND ".
        "LOCALE_ID = ?";
    $result = $db->execute($sql, [$translate_id, $locale_id]);
    $sql = "INSERT INTO TRANSLATION_LOCALE VALUES (?, ?, ?)";
    $result = $db->execute($sql, [$translate_id, $locale_id, $translation]);
}
