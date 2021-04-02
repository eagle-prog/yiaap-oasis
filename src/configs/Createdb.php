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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\configs;

use seekquarry\yioop\library as L;
use seekquarry\yioop\models\Model;
use seekquarry\yioop\models\ProfileModel;
use seekquarry\yioop\models\GroupModel;
use seekquarry\yioop\configs as C;

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    echo "BAD REQUEST";
    exit();
}
/** For crawlHash function */
require_once __DIR__ . "/../library/Utility.php";
/** For wiki page translation stuff */
require_once __DIR__ . "/../library/LocaleFunctions.php";
/** To make it easy to insert translations */
require_once __DIR__ . "/../library/UpgradeFunctions.php";
$profile_model = new ProfileModel(DB_NAME, false);
$private_profile_model = new ProfileModel(PRIVATE_DB_NAME, false);
$db_class = NS_DATASOURCES . ucfirst(DBMS)."Manager";
$private_db_class = NS_DATASOURCES . ucfirst(PRIVATE_DBMS)."Manager";
$dbinfo = ["DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME];
$private_dbinfo = ["DBMS" => PRIVATE_DBMS, "DB_HOST" => PRIVATE_DB_HOST,
    "DB_USER" => PRIVATE_DB_USER, "DB_PASSWORD" => PRIVATE_DB_PASSWORD,
    "DB_NAME" => PRIVATE_DB_NAME];
$lower_dbms = strtolower(DBMS);
if (!in_array($lower_dbms, ['sqlite', 'sqlite3'])) {
    $db = new $db_class();
    $private_db = new $private_db_class();
    if ($lower_dbms == 'pdo' && stristr($dbinfo['DB_HOST'], 'PGSQL')) {
        $which_dbms = "pgsql";
    }
    $public_exist = true;
    if (!$db->connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)) {
        $host = preg_replace("/\;dbname\=\w+/", "", DB_HOST);
        L\crawlLog('Public database doesn\'t exist yet, trying to create it');
        $public_exist = false;
        $db->connect($host, DB_USER, DB_PASSWORD, "");
    }
    $private_exist = true;
    if(!$private_db->connect(PRIVATE_DB_HOST, PRIVATE_DB_USER,
        PRIVATE_DB_PASSWORD, PRIVATE_DB_NAME)) {
        $private_host = preg_replace("/\;dbname\=\w+/", "", PRIVATE_DB_HOST);
        L\crawlLog('Private database doesn\'t exist yet, trying to create it');
        $private_exist = false;
        $db->connect($private_host, PRIVATE_DB_USER, PRIVATE_DB_PASSWORD);
    }
    /*  postgres doesn't let you drop a database while connected to it so drop
        tables instead first
     */
    $profile_model->initializeSql($db, $dbinfo);
    $private_profile_model->initializeSqlPrivate($private_db, $private_dbinfo);
    $database_tables = array_keys($profile_model->create_statements);
    $private_database_tables = array_keys(
        $private_profile_model->private_create_statements);
    if ($public_exist) {
        foreach ($database_tables as $table) {
            if ($table == "CURRENT_WEB_INDEX" || substr($table, -5) != "INDEX"){
                $db->execute("DROP TABLE IF EXISTS " . $table);
            }
        }
    } else {
        $db->execute("CREATE DATABASE " . DB_NAME);
        $db->disconnect();
        $db->connect(); // default connection goes to actual DB
    }
    if ($private_exist) {
        foreach ($private_database_tables as $table) {
            if ($table == "CURRENT_WEB_INDEX" || substr($table, -5) != "INDEX"){
                $private_db->execute("DROP TABLE IF EXISTS " . $table);
            }
        }
    } else {
        $private_db->execute("CREATE DATABASE ". PRIVATE_DB_NAME);
        $private_db->disconnect();
        $private_db->connect(PRIVATE_DB_HOST, PRIVATE_DB_USER,
            PRIVATE_DB_PASSWORD, PRIVATE_DB_NAME);
    }
} else {
    $which_dbms = "sqlite";
    @unlink(CRAWL_DIR . "/data/" . DB_NAME . ".db");
    $db = new $db_class();
    $db->connect();
    $db->execute("PRAGMA journal_mode=WAL");
    @unlink(CRAWL_DIR . "/data/" . PRIVATE_DB_NAME . ".db");
    $private_db = new $private_db_class();
    $private_db->connect(PRIVATE_DB_HOST, PRIVATE_DB_USER,
        PRIVATE_DB_PASSWORD, PRIVATE_DB_NAME);
    $private_db->execute("PRAGMA journal_mode=WAL");
}
if (!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}
if (!$private_profile_model->createDatabaseTablesPrivate($private_db,
    $private_dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}
$db->execute("INSERT INTO VERSION VALUES (" . DATABASE_VERSION . ")");
$creation_time = L\microTimestamp();
//numerical value of the blank password
$profile = $profile_model->getProfile(WORK_DIRECTORY);
$new_profile = $profile;
$profile_model->updateProfile(WORK_DIRECTORY, $new_profile, $profile);
//default account is root without a password
$sql ="INSERT INTO USERS VALUES (" . ROOT_ID . ", 'admin', 'admin','" .
        ROOT_USERNAME . "',
        'root@dev.null', '".L\crawlCrypt('')."', '".ACTIVE_STATUS.
        "', '".L\crawlCrypt(ROOT_USERNAME . AUTH_KEY . $creation_time).
        "', '$creation_time', 0, 0)";
$db->execute($sql);
/* public account is an inactive account for used for public permissions
   default account is root without a password
 */
$sql ="INSERT INTO USERS VALUES (".PUBLIC_USER_ID.", 'all', 'all','public',
        'public@dev.null', '".L\crawlCrypt('')."', '".INACTIVE_STATUS.
        "', '".L\crawlCrypt('public' . AUTH_KEY . $creation_time)."',
        '$creation_time', 0, 0)";
$db->execute($sql);
//default public group with group id 1
$creation_time = L\microTimestamp();
$sql = "INSERT INTO GROUPS VALUES(".PUBLIC_GROUP_ID.",'Public','" .
    $creation_time . "','".ROOT_ID."', '"  .PUBLIC_JOIN . "', '" . GROUP_READ .
    "', " . NON_VOTING_GROUP.", " . FOREVER . ", 0)";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO ROLE VALUES (" . ADMIN_ROLE . ", 'Admin' )");
$db->execute("INSERT INTO ROLE VALUES (" . USER_ROLE . ", 'User' )");
$db->execute("INSERT INTO ROLE VALUES (".BOT_ROLE.", 'Bot User' )");
$db->execute("INSERT INTO USER_ROLE VALUES (" . ROOT_ID . ", " . ADMIN_ROLE .
    ")");
$db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", ".
    PUBLIC_GROUP_ID.", " . ACTIVE_STATUS . ", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (".PUBLIC_USER_ID.", ".
    PUBLIC_GROUP_ID.", " . ACTIVE_STATUS . ", $now)");
//Create a Group for Wiki HELP.
$sql = "INSERT INTO GROUPS VALUES (" . HELP_GROUP_ID . ",'Help','" .
    $creation_time . "','" . ROOT_ID . "',
    '" . PUBLIC_BROWSE_REQUEST_JOIN . "', '" . GROUP_READ_WIKI .
    "', " . UP_DOWN_VOTING_GROUP . ", " . FOREVER . ", 0)";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (" . PUBLIC_USER_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
//Create a Group for Search Result Edits
$sql = "INSERT INTO GROUPS VALUES (" . SEARCH_GROUP_ID . ",'Search','" .
    $creation_time . "','" . ROOT_ID . "',
    '" . PUBLIC_BROWSE_REQUEST_JOIN . "', '" . GROUP_READ_WIKI .
    "', " . UP_DOWN_VOTING_GROUP . ", " . FOREVER . ", 0)";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", " .
    SEARCH_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (" . PUBLIC_USER_ID . ", " .
    SEARCH_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$locales = [
    ['ar', 'العربية', 'rl-tb'],
    ['bn', 'বাংলা', 'lr-tb'],
    ['de', 'Deutsch', 'lr-tb'],
    ['en-US', 'English', 'lr-tb'],
    ['es', 'Español', 'lr-tb'],
    ['fa', 'فارسی', 'rl-tb'],
    ['fr-FR', 'Français', 'lr-tb'],
    ['he', 'עברית', 'rl-tb'],
    ['hi', 'हिन्दी', 'lr-tb'],
    ['id', 'Bahasa', 'lr-tb'],
    ['it', 'Italiano', 'lr-tb'],
    ['ja', '日本語', 'lr-tb'],
    ['kn', 'ಕನ್ನಡ', 'lr-tb'],
    ['ko', '한국어', 'lr-tb'],
    ['nl', 'Nederlands', 'lr-tb'],
    ['pl', 'Polski', 'lr-tb'],
    ['pt', 'Português', 'lr-tb'],
    ['ru', 'Русский', 'lr-tb'],
    ['te', 'తెలుగు', 'lr-tb'],
    ['th', 'ไทย', 'lr-tb'],
    ['tl', 'Tagalog', 'lr-tb'],
    ['tr', 'Türkçe', 'lr-tb'],
    ['vi-VN', 'Tiếng Việt', 'lr-tb'],
    ['zh-CN', '中文', 'lr-tb'],
];
$i = 1;
foreach ($locales as $locale) {
    $db->execute("INSERT INTO LOCALE VALUES ($i, '{$locale[0]}',
        '{$locale[1]}', '{$locale[2]}', '1')");
    $locale_index[$locale[0]] = $i;
    $i++;
}
$group_model = new GroupModel(DB_NAME, false);
$group_model->db = $db;
/*
   Set up generic page relationship
 */
$db->execute("INSERT INTO PAGE_RELATIONSHIP VALUES (-1, 'generic_links')");
// Insert Default Public Wiki Pages
if (file_exists(APP_DIR . "/configs/PublicHelpPages.php")) {
    require_once APP_DIR . "/configs/PublicHelpPages.php";
} else {
    require_once BASE_DIR . "/configs/PublicHelpPages.php";
}
$default_locale = L\getLocaleTag();
foreach ($public_pages as $locale_tag => $locale_pages) {
    L\setLocaleObject($locale_tag);
    foreach ($locale_pages as $page_name => $page_content) {
        $page_name = str_replace(" ", "_", $page_name);
        $page_content = str_replace("'", "&#039;", $page_content);
        $group_model->setPageName(ROOT_ID, PUBLIC_GROUP_ID, $page_name,
            $page_content, $locale_tag, "create",
            L\tl('social_component_page_created', $page_name),
            L\tl('social_component_page_discuss_here'));
    }
}
//Insert Default Public Help pages
foreach ($help_pages as $locale_tag => $locale_pages) {
    L\setLocaleObject($locale_tag);
    foreach ($locale_pages as $page_name => $page_content) {
        $page_name = str_replace(" ", "_", $page_name);
        $page_content = str_replace("'", "&#039;", $page_content);
        $group_model->setPageName(ROOT_ID, HELP_GROUP_ID, $page_name,
            $page_content, $locale_tag, "create",
            L\tl('social_component_page_created', $page_name),
            L\tl('social_component_page_discuss_here'));
    }
}
L\setLocaleObject($default_locale);
/* End Help content insertion. */
$activities = [
    "manageAccount" => ['db_activity_manage_account',
        [
            "ar" => 'إدارة الحساب',
            "bn" => 'অ্যাকাউন্ট পরিচালনা করুন',
            "de" => 'Konto Verwalten',
            "en-US" => 'Manage Account',
            "es" => 'Administrar la cuenta',
            "fa" => 'مدیریت حساب',
            "fr-FR" => 'Gérer votre compte',
            "he" => 'נהל חשבון',
            "hi" => 'खाते का प्रबंधन करें',
            "id" => 'Kelola Akun',
            "it" => 'Gestisci account',
            "ja" => 'アカウント管理',
            "kn" => 'ಖಾತೆ ನಿರ್ವಹಣೆ',
            "ko" => '사용자 계정 관리',
            "nl" => 'Account Beheren',
            "pl" => 'Zarządzaj kontem',
            "pt" => 'Gerenciar a conta',
            "ru" => 'Управление счетом',
            "te" => 'ఖాతాను నిర్వహించండి',
            "th" => 'จัดการบัญชี',
            "tl" => 'Pamahalaan Ang Mga Account',
            "tr" => 'Hesabı Yönet',
            "vi-VN" => 'Quản lý tài khoản',
            "zh-CN" => '管理帳號',
        ]],
    "manageUsers" => ['db_activity_manage_users',
        [
            "ar" => 'إدارة المستخدمين',
            "bn" => 'ব্যবহারকারীদের পরিচালনা করুন',
            "de" => 'Benutzer Verwalten',
            "en-US" => 'Manage Users',
            "es" => 'Administrar usuarios',
            "fa" => 'مدیریت کاربران',
            "fr-FR" => 'Gérer les utilisateurs',
            "he" => 'ניהול משתמשים',
            "hi" => 'उपयोगकर्ताओं का प्रबंधन करें',
            "id" => 'Mengelola pengguna',
            "it" => 'Gestire gli utenti',
            "ja" => 'ユーザー管理',
            "kn" => 'ಬಳಕೆದಾರರು ನಿರ್ವಹಿಸಿ',
            "ko" => '사용자 관리',
            "nl" => 'Gebruikers beheren',
            "pl" => 'Zarządzaj użytkownikami',
            "pt" => 'Gerenciar usuários',
            "ru" => 'Управление пользователями',
            "te" => 'వినియోగదారులను నిర్వహించండి',
            "th" => 'จัดการผู้ใช้',
            "tl" => 'Pamahalaan Ang Mga User',
            "tr" => 'Kullanıcıları Yönet',
            "vi-VN" => 'Quản lý tên sử dụng',
            "zh-CN" => '管理使用者',
        ]],
    "manageRoles" => ['db_activity_manage_roles',
        [
            "ar" => 'إدارة أدوار',
            "bn" => 'ভূমিকা পরিচালনা করুন',
            "de" => 'Rollen Verwalten',
            "en-US" => 'Manage Roles',
            "es" => 'Administrar roles',
            "fa" => 'مدیریت نقش‌ها',
            "fr-FR" => 'Gérer les rôles',
            "he" => 'ניהול תפקידים',
            "hi" => 'भूमिकाओं का प्रबंधन करें',
            "id" => 'Mengelola peran',
            "it" => 'Gestire i ruoli',
            "ja" => '役割管理',
            "kn" => 'ಪಾತ್ರಗಳನ್ನು ನಿರ್ವಹಿಸಿ',
            "ko" => '사용자 권한 관리',
            "nl" => 'Rollen beheren',
            "pl" => 'Zarządzanie rolami',
            "pt" => 'Gerenciar funções',
            "ru" => 'Управление ролями',
            "te" => 'పాత్రలను నిర్వహించండి',
            "th" => 'จัดการบทบาท',
            "tl" => 'Pamahalaan Ang Mga Tungkulin',
            "tr" => 'Rolleri Yönetme',
            "vi-VN" => 'Quản lý chức vụ',
            "zh-CN" => '管理角色',
        ]],
    "manageCrawls" => ['db_activity_manage_crawl',
        [
            "ar" => 'إدارة يزحف',
            "bn" => 'দল পরিচালনা করুন',
            "de" => 'Verwalten Kriecht',
            "en-US" => 'Manage Crawls',
            "es" => 'Administrar los rastreos',
            "fa" => 'مدیریت خزش‌ها',
            "fr-FR" => 'Gérer les indexes',
            "he" => 'ניהול סריקות',
            "hi" => 'क्रॉल का प्रबंधन करें',
            "id" => 'Mengelola perayapan',
            "it" => 'Gestire le ricerche',
            "ja" => '検索管理',
            "kn" => 'ನಿರ್ವಹಿಸಿ ಕ್ರಾಲ್',
            "ko" => '크롤 관리',
            "nl" => 'Beheer Crawls',
            "pl" => 'Zarządzanie przeszukuje',
            "pt" => 'Gerenciar rastreamentos',
            "ru" => 'Управление Ползает',
            "te" => 'Crawls నిర్వహించండి',
            "th" => 'จัดการการตระเวน',
            "tl" => 'Pamahalaan Ang Mga Pag-Crawl',
            "tr" => 'Taramaları Yönetme',
            "vi-VN" => 'Quản lý sự bò',
            "zh-CN" => '管理爬网',
        ]],
    "mixCrawls" => ['db_activity_mix_crawls',
        [
            "ar" => 'مزيج يزحف',
            "bn" => 'ছোটখাটো মেলামেশা',
            "de" => 'Mix Kriecht',
            "en-US" => 'Mix Crawls',
            "es" => 'Mezclar De Los Rastreos',
            "fa" => 'ترکیب‌های خزش‌ها',
            "fr-FR" => 'Mélanger les indexes',
            "he" => 'מערבבים סריקות',
            "hi" => 'मिक्स क्रॉल',
            "id" => 'Gabungan merangkak',
            "it" => 'Combinare le ricerche',
            "ja" => 'クロールを混在させるには',
            "kn" => 'ಮಿಶ್ರಣ ಕ್ರಾಲ್',
            "ko" => '크롤링을 혼합하려면',
            "nl" => 'Mix Crawls',
            "pl" => 'Mieszać przeszukuje',
            "pt" => 'Misturar crawls',
            "ru" => 'Смесь Уползает',
            "te" => 'Crawls మిక్స్ చేయడానికి',
            "th" => 'การมิกซ์คลาน',
            "tl" => 'Paghaluin Ang Mga Pag-Crawl',
            "tr" => 'Crawls Karıştırmak için',
            "vi-VN" => 'Trộn Bò',
            "zh-CN" => '混合爬',
        ]],
    "manageClassifiers" => ['db_activity_manage_classifiers',
        [
            "ar" => 'إدارة المصنفات',
            "bn" => 'শ্রেণীফায়ার পরিচালনা করুন',
            "de" => 'Verwalten Von Klassifikatoren',
            "en-US" => 'Manage Classifiers',
            "es" => 'Administrar los clasificadores',
            "fa" => 'مدیریت كلسّفرس',
            "fr-FR" => 'Gérer les Classificateurs',
            "he" => 'ניהול מסווג',
            "hi" => 'क्लासिफायर्स का प्रबंधन करें',
            "id" => 'Mengelola Pengklasifikasi',
            "it" => 'Gestire i classificatori',
            "ja" => '分類子の管理',
            "kn" => 'ನಿರ್ವಹಿಸಿ ಪಟ್ಟಿ',
            "ko" => '분류자 관리',
            "nl" => 'Beheer Classifiers',
            "pl" => 'Zarządzanie klasyfikatorami',
            "pt" => 'Gerenciar classificadores',
            "ru" => 'Управление классификаторами',
            "te" => 'క్లాసిఫైయర్ లను నిర్వహించండి',
            "th" => 'จัดการลักษณนาม',
            "tl" => 'Pamahalaan Ang Mga Classifiers',
            "tr" => 'Sınıflandırıcıları Yönetme',
            "vi-VN" => 'Quản lý phân loại',
            "zh-CN" => '管理分类',
        ]],
    "pageOptions" => ['db_activity_file_options',
        [
            "ar" => 'خيارات الصفحة',
            "bn" => 'পৃষ্ঠার পছন্দসমূহ',
            "de" => 'Seite Optionen',
            "en-US" => 'Page Options',
            "es" => 'Las opciones de la página',
            "fa" => 'تنظیمات صفحه',
            "fr-FR" => 'Options de fichier',
            "he" => 'אפשרויות דף',
            "hi" => 'पेज विकल्प',
            "id" => 'Pilihan halaman',
            "it" => 'Opzioni pagina',
            "ja" => 'ページ オプション',
            "kn" => 'ಪುಟ ಆಯ್ಕೆಗಳು',
            "ko" => '페이지 옵션',
            "nl" => 'Opties voor de pagina',
            "pl" => 'Opcje strony',
            "pt" => 'Opções de página',
            "ru" => 'Параметры страницы',
            "te" => 'పుట ఐచ్ఛికాలు',
            "th" => 'ตัวเลือกเพจ',
            "tl" => 'Pahina Ng Mga Pagpipilian',
            "tr" => 'Sayfa Seçenekleri',
            "vi-VN" => 'Tùy chọn trang',
            "zh-CN" => '页面上选择',
        ]],
    "resultsEditor" => ['db_activity_results_editor',
        [
            "ar" => 'نتائج محرر',
            "bn" => 'ফলাফল সম্পাদক',
            "de" => 'Ergebnisse-Editor',
            "en-US" => 'Results Editor',
            "es" => 'Editor de resultados',
            "fa" => 'ویرایشگر نتایج',
            "fr-FR" => 'Éditeur de résultats',
            "he" => 'תוצאות עורך',
            "hi" => 'परिणाम संपादक',
            "id" => 'Editor hasil',
            "it" => 'Editor risultati',
            "ja" => '結果エディタ',
            "kn" => 'ಫಲಿತಾಂಶಗಳು ಸಂಪಾದಕ',
            "ko" => '결과 편집기',
            "nl" => 'Resultaten Editor',
            "pl" => 'Edytor wyników',
            "pt" => 'Editor de Resultados',
            "ru" => 'Редактор результатов',
            "te" => 'ఫలితాల ఎడిటర్',
            "th" => 'ตัวแก้ไขผลลัพธ์',
            "tl" => 'Mga Resulta Editor',
            "tr" => 'Sonuç Editörü',
            "vi-VN" => 'Trình biên tập kết quả',
            "zh-CN" => '结果编辑',
        ]],
    "searchSources" => ['db_activity_search_services',
        [
            "ar" => 'مصادر البحث',
            "bn" => 'অনুসন্ধান সূত্র',
            "de" => 'Suche Quellen',
            "en-US" => 'Search Sources',
            "es" => 'Fuentes de búsqueda',
            "fa" => 'منابع جستجو',
            "fr-FR" => 'Sources de recherche',
            "he" => 'חיפוש מקורות',
            "hi" => 'खोज स्रोत',
            "id" => 'Cari sumber',
            "it" => 'Origini di ricerca',
            "ja" => '検索ソース',
            "kn" => 'ಹುಡುಕಾಟ ಮೂಲಗಳು',
            "ko" => '소스 검색',
            "nl" => 'Zoek Bronnen',
            "pl" => 'Źródła wyszukiwania',
            "pt" => 'Fontes de pesquisa',
            "ru" => 'Источники поиска',
            "te" => 'మూలాలు శోధించు',
            "th" => 'ค้นหาแหล่งที่มา',
            "tl" => 'Maghanap Ng Mga Pinagkukunan',
            "tr" => 'Arama Kaynakları',
            "vi-VN" => 'Nguồn tìm kiếm',
            "zh-CN" => '搜索来源',
        ]],
    "scrapers" => ['db_activity_scrapers',
        [
            "ar" => 'ويب كاشطات',
            "bn" => 'ওয়েব স্ক্রাবের',
            "de" => 'Web-Schaber',
            "en-US" => 'Web Scrapers',
            "es" => 'Web Raspadores',
            "fa" => 'وب زداینده',
            "fr-FR" => 'Web grattoirs',
            "he" => 'אינטרנט מגרדים',
            "hi" => 'वेब स्क्रैपर्स',
            "id" => 'Web Scraper',
            "it" => 'Raschiatori Web',
            "ja" => 'ウェブスクレーパー',
            "kn" => 'ವೆಬ್ ಸ್ಕ್ರೇಪರ್ಗಳು',
            "ko" => '웹 스크레이퍼',
            "nl" => 'Web scrapers',
            "pl" => 'Skrobaki internetowe',
            "pt" => 'Raspadores da Web',
            "ru" => 'Веб Скребкерс',
            "te" => 'వెబ్ స్క్రాప్స్',
            "th" => 'ขูดเว็บ',
            "tl" => 'Web Scraper',
            "tr" => 'Web Kazıyıcılar',
            "vi-VN" => 'Web Chọc',
            "zh-CN" => '网刮',
        ]],
    "groupFeeds" => ['db_activity_group_feeds',
        [
            "ar" => 'يغذي الويكي',
            "bn" => 'ফিড এবং উইকিস',
            "de" => 'Feeds und Wikis',
            "en-US" => 'Feeds and Wikis',
            "es" => 'Fuentes y wikis',
            "fa" => 'تغذیه و ویکیهای',
            "fr-FR" => 'Les flux et les wikis',
            "he" => 'הזנות במיזמים',
            "hi" => 'फ़ीड और विकी',
            "id" => 'Feed dan wiki',
            "it" => 'Feed e Wiki',
            "ja" => 'フィードと Wiki',
            "kn" => 'ಆಹಾರ ಮತ್ತು ವಿಕಿಗಳು',
            "ko" => '피드 및 위키',
            "nl" => 'Feeds en Wikis',
            "pl" => 'Kanały i wiki',
            "pt" => 'Feeds e Wikis',
            "ru" => 'Каналы и Вики',
            "te" => 'సారములు మరియు వికీలు',
            "th" => 'ฟีดและวิกิ',
            "tl" => 'Feed at Wiki',
            "tr" => 'Yayınlar ve Vikiler',
            "vi-VN" => 'Thức ăn và Wiki',
            "zh-CN" => '饲料和维基',
        ]],
    "manageGroups" => ['db_activity_manage_groups',
        [
            "ar" => 'إدارة المجموعات',
            "bn" => 'দল পরিচালনা করুন',
            "de" => 'Gruppen Verwalten',
            "en-US" => 'Manage Groups',
            "es" => 'Administrar grupos',
            "fa" => 'مدیریت گروه',
            "fr-FR" => 'Gérer les groupes',
            "he" => 'ניהול קבוצות',
            "hi" => 'समूहों का प्रबंधन करें',
            "id" => 'Mengelola grup',
            "it" => 'Gestisci gruppi',
            "ja" => 'グループの管理',
            "kn" => 'ಗುಂಪುಗಳು ನಿರ್ವಹಿಸಿ',
            "ko" => '그룹 관리',
            "nl" => 'Groepen beheren',
            "pl" => 'Zarządzanie grupami',
            "pt" => 'Grupos gerenciados',
            "ru" => 'Управлять группами',
            "te" => 'సమూహాలను నిర్వహించండి',
            "th" => 'จัดการกลุ่ม',
            "tl" => 'Pamahalaan Ang Mga Pangkat',
            "tr" => 'Grupları Yönet',
            "vi-VN" => 'Quản lý nhóm',
            "zh-CN" => '管理组',
        ]],
    "botStory" => ['db_activity_botstory',
        [
            "ar" => 'بوت القصة',
            "bn" => 'বট গল্প',
            "de" => 'Bot-Geschichte',
            "en-US" => 'Bot Story',
            "es" => 'Bot historia',
            "fa" => 'ربات داستان',
            "fr-FR" => 'Bot histoire',
            "he" => 'בוט הסיפור',
            "hi" => 'बीओटी कहानी',
            "id" => 'Bot Cerita',
            "it" => 'Bot Storia',
            "ja" => 'ボットストーリー',
            "kn" => 'ಬೋಟ್ ಕಥೆ',
            "ko" => '봇 스토리',
            "nl" => 'Bot Story',
            "pl" => 'Historia botów',
            "pt" => 'História do bot',
            "ru" => 'История бота',
            "te" => 'బాట్ స్టోరీ',
            "th" => 'เรื่องราวของ Bot',
            "tl" => 'Bot Kuwento',
            "tr" => 'Bot Hikayesi',
            "vi-VN" => 'Bot Câu Chuyện',
            "zh-CN" => '机器人的故事',
        ]],
    "manageCredits" => ['db_activity_manage_credits',
        [
            "ar" => 'إدارة الاعتمادات',
            "bn" => 'ক্রেডিট পরিচালনা করুন',
            "de" => 'Guthaben Verwalten',
            "en-US" => 'Manage Credits',
            "es" => 'Administrar créditos',
            "fa" => 'مدیریت اعتبار',
            "fr-FR" => 'Gérer les crédits',
            "he" => 'ניהול קרדיטים',
            "hi" => 'रेडिट का प्रबंधन करें',
            "id" => 'Mengelola Kredit',
            "it" => 'Gestire i crediti',
            "ja" => 'クレジットの管理',
            "kn" => 'ನಿರ್ವಹಿಸಿ ಕ್ರೆಡಿಟ್ಸ್',
            "ko" => '크레딧 관리',
            "nl" => 'Credits beheren',
            "pl" => 'Zarządzaj kredytami',
            "pt" => 'Gerenciar créditos',
            "ru" => 'Управление кредитами',
            "te" => 'క్రెడిట్ లను నిర్వహించండి',
            "th" => 'จัดการเครดิต',
            "tl" => 'Pamahalaan Ang Mga Kredito',
            "tr" => 'Kredileri Yönet',
            "vi-VN" => 'Quản Lý Các Khoản Tín Dụng',
            "zh-CN" => '管理学分',
        ]],
    "manageAdvertisements" => ['db_activity_manage_advertisements',
        [
            "ar" => 'إدارة إعلانات',
            "bn" => 'বিজ্ঞাপন পরিচালনা করুন',
            "de" => 'Werbung Verwalten',
            "en-US" => 'Manage Advertisements',
            "es" => 'Administrar anuncios',
            "fa" => 'مدیریت تبلیغات',
            "fr-FR" => 'Gérer les publicités',
            "he" => 'ניהול פרסומות',
            "hi" => 'विज्ञापनों का प्रबंधन करें',
            "id" => 'Mengelola Iklan',
            "it" => 'Gestire gli annunci',
            "ja" => '提供情報の管理',
            "kn" => 'ಜಾಹೀರಾತುಗಳು ನಿರ್ವಹಿಸಿ',
            "ko" => '광고 관리',
            "nl" => 'Advertenties beheren',
            "pl" => 'Zarządzaj reklamami',
            "pt" => 'Gerenciar anúncios',
            "ru" => 'Управление рекламой',
            "te" => 'ప్రకటనలను నిర్వహించండి',
            "th" => 'จัดการโฆษณา',
            "tl" => 'Pamahalaan Ang Mga Advertisement',
            "tr" => 'Reklamları Yönet',
            "vi-VN" => 'Quản Lý Quảng Cáo',
            "zh-CN" => '管理的广告',
        ]],
    "manageMachines" => ['db_activity_manage_machines',
        [
            "ar" => 'إدارة الآلات',
            "bn" => 'মেশিন পরিচালনা করুন',
            "de" => 'Verwalten Maschinen',
            "en-US" => 'Manage Machines',
            "es" => 'Gestionar las máquinas',
            "fa" => 'مدیریت دستگاه‌ها',
            "fr-FR" => 'Gérer les machines',
            "he" => 'ניהול מכונות',
            "hi" => 'मशीनों का प्रबंधन करें',
            "id" => 'Mengelola mesin',
            "it" => 'Gestire le macchine',
            "ja" => 'マシンの管理',
            "kn" => 'ನಿರ್ವಹಿಸಿ ಯಂತ್ರಗಳು',
            "ko" => '컴퓨터 관리',
            "nl" => 'Beheer Machines',
            "pl" => 'Zarządzanie maszynami',
            "pt" => 'Gerenciar máquinas',
            "ru" => 'Управление машинами',
            "te" => 'యంత్రాలు నిర్వహించండి',
            "th" => 'จัดการเครื่องจักร',
            "tl" => 'Pamahalaan Ang Machine',
            "tr" => 'Makineleri Yönet',
            "vi-VN" => 'Quản Lý Máy',
            "zh-CN" => '管理机',
        ]],
    "manageLocales" => ['db_activity_manage_locales',
        [
            "ar" => 'إدارة مواقع',
            "bn" => 'লোকেশনসমূহ পরিচালনা করুন',
            "de" => 'Verwalten Locales',
            "en-US" => 'Manage Locales',
            "es" => 'Administrar configuraciones regionales',
            "fa" => 'مدیریت زبان‌ها',
            "fr-FR" => 'Gérer les lieux',
            "he" => 'ניהול אזורים',
            "hi" => 'स्थानों का प्रबंधन करें',
            "id" => 'Mengelola locales',
            "it" => 'Gestire le impostazioni locali',
            "ja" => 'ローケル管理',
            "kn" => 'ನಿರ್ವಹಿಸಿ ಪ್ರದೇಶಗಳಲ್ಲಿ',
            "ko" => '로케일 관리',
            "nl" => 'Beheer varianten',
            "pl" => 'Zarządzanie ustawieniami locales',
            "pt" => 'Gerenciar locais',
            "ru" => 'Управление Локалами',
            "te" => 'లోకేల్స్ నిర్వహించండి',
            "th" => 'จัดการตำแหน่งที่เป็น',
            "tl" => 'Pamahalaan Ang Mga Locale',
            "tr" => 'Yerel Leri Yönet',
            "vi-VN" => 'Quản lý miền địa phương',
            "zh-CN" => '管理选择',
        ]],
    "serverSettings" => ['db_activity_server_settings',
        [
            "ar" => 'إعدادات الخادم',
            "bn" => 'সার্ভার সেটিংস',
            "de" => 'Server-Einstellungen',
            "en-US" => 'Server Settings',
            "es" => 'La configuración del servidor',
            "fa" => 'تنظیمات سرور',
            "fr-FR" => 'Paramètres du serveur',
            "he" => 'הגדרות שרת',
            "hi" => 'सर्वर सेटिंग',
            "id" => 'Pengaturan server',
            "it" => 'Impostazione server',
            "ja" => 'サーバー設定',
            "kn" => 'ಸರ್ವರ್ ಸೆಟ್ಟಿಂಗ್ಗಳನ್ನು',
            "ko" => '서버 설정',
            "nl" => 'Server instellingen',
            "pl" => 'Ustawienia serwera',
            "pt" => 'Configuração do servidor',
            "ru" => 'Настройка сервера',
            "te" => 'సర్వర్ అమర్పు',
            "th" => 'การตั้งค่าเซิร์ฟเวอร์',
            "tl" => 'Mga Setting Ng Server',
            "tr" => 'Sunucu Ayarı',
            "vi-VN" => 'Cài Đặt Máy Chủ',
            "zh-CN" => '设置服务器',
        ]],
    "security" => ['db_activity_security',
        [
            "ar" => 'الأمن',
            "bn" => 'নিরাপত্তা',
            "de" => 'Sicherheit',
            "en-US" => 'Security',
            "es" => 'Seguridad',
            "fa" => 'امنیت',
            "fr-FR" => 'Sécurité',
            "he" => 'אבטחה',
            "hi" => 'सुरक्षा',
            "id" => 'Keamanan',
            "it" => 'Sicurezza',
            "ja" => 'セキュリティ',
            "kn" => 'ಭದ್ರತಾ',
            "ko" => '보안',
            "nl" => 'Veiligheid',
            "pl" => 'Zabezpieczeń',
            "pt" => 'Segurança',
            "ru" => 'Безопасности',
            "te" => 'భద్రతా',
            "th" => 'ความปลอดภัย',
            "tl" => 'Seguridad',
            "tr" => 'Güvenlik',
            "vi-VN" => 'An ninh',
            "zh-CN" => '安全',
        ]],
    "appearance" => ['db_activity_appearance',
        [
            "ar" => 'المظهر',
            "bn" => 'উপস্থিতি',
            "de" => 'Aussehen',
            "en-US" => 'Appearance',
            "es" => 'Apariencia',
            "fa" => 'ظاهر',
            "fr-FR" => 'Aspect',
            "he" => 'מראה',
            "hi" => 'उपस्थिति',
            "id" => 'Penampilan',
            "it" => 'Aspetto',
            "ja" => '外観',
            "kn" => 'ಕಾಣಿಸಿಕೊಂಡ',
            "ko" => '모양을',
            "nl" => 'Verschijning',
            "pl" => 'Wygląd',
            "pt" => 'Aparência',
            "ru" => 'Внешний вид',
            "te" => 'స్వరూపం',
            "th" => 'ลักษณะ',
            "tl" => 'Hitsura',
            "tr" => 'Görünüm',
            "vi-VN" => 'Xuất hiện',
            "zh-CN" => '外观',
        ]],
    "configure" => ['db_activity_configure',
        [
            "ar" => 'تكوين',
            "bn" => 'কনফিগার',
            "de" => 'Konfigurieren',
            "en-US" => 'Configure',
            "es" => 'Configurar',
            "fa" => 'پیکربندی',
            "fr-FR" => 'Configurer',
            "he" => 'הגדיר',
            "hi" => 'कॉन्फ़िगर',
            "id" => 'Mengkonfigurasi',
            "it" => 'Configurare',
            "ja" => '設定',
            "kn" => 'ಸಂರಚಿಸಲು',
            "ko" => '구성',
            "nl" => 'Configureren',
            "pl" => 'Skonfigurować',
            "pt" => 'Configurar',
            "ru" => 'Настроить',
            "te" => 'కాన్ఫిగర్',
            "th" => 'ตั้ง ค่า คอน ฟิก',
            "tl" => 'I-Configure',
            "tr" => 'Yapılandırmak',
            "vi-VN" => 'Cấu hình',
            "zh-CN" => '配置',
        ]],
];
$i = 1;
foreach ($activities as $activity => $translation_info) {
    // set-up activity
    $db->execute("INSERT INTO ACTIVITY VALUES ($i, $i, '$activity')");
    //give admin role the ability to have that activity (except ads)
    if (!in_array($activity, ["manageCredits", "manageAdvertisements"] )) {
        if ($activity == "botStory") {
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (" .
                BOT_ROLE . ", $i, 'all')");
        } else {
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (" .
                ADMIN_ROLE . ", $i, 'all')");
        }
    }
    $db->execute("INSERT INTO TRANSLATION
        VALUES($i, '{$translation_info[0]}')");
    foreach ($translation_info[1] as $locale_tag => $translation) {
        $index = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES ($i, $index,
            '$translation')");
    }
    $i++;
}
$new_user_activities = [
    "manageAccount",
    "manageGroups",
    "groupFeeds"
];
foreach ($new_user_activities as $new_activity) {
    $i = 1;
    foreach ($activities as $key => $value) {
        if ($new_activity == $key){
        //give new user role the ability to have that activity
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".
                USER_ROLE . ", $i, 'all')");
        }
        $i++;
    }
}
$db->execute("INSERT INTO MACHINE VALUES ('NAME_SERVER', 'BASE_URL', 0, 2,'')");
$media_sources = [
    ['100000000', 'Yahoo News', 'rss', 'news', 'https://news.yahoo.com/rss/',
        '//content/@url###/Daily(\s|\-)+Beast/i', 'en-US'],
    ['100000002', 'Yioop News', 'json', 'news',
        'https://www.yioop.com/s/news?f=json',
        '//channel###//item###//title###//description###//link###//image_link',
        'en-US'],
    ['100000003', 'The Hollywood Reporter', 'html', 'news',
        'https://www.hollywoodreporter.com/',
        "//script[contains(@id, 'js-news-data')]" .
        "###//article###//h1###//p###//a###//img/@src",
        'en-US'],
    ['100000004', 'National Weather Service 4', 'regex', 'weather',
        'http://forecast.weather.gov/product.php?'.
        'site=NWS&issuedby=04&product=SCS&format=txt&version=1&glossary=0',
        '/WEA\s+LO\/HI\s*\n+([^<]+)\n+NATIONAL/mi###/\n/###'.
        '/^(.+?)\s\s\s+/###/\s\s\s+(.+?)$/###http://www.weather.gov/###',
        'en-US'],
    ['100000005', 'Ted', 'feed_podcast', '2592000',
        'https://pa.tedcdn.com/feeds/talks.rss',
        '############enclosure###Public@Podcast Examples/Ted/%Y-%m-%d %F',
        'en-US'],
];
$sql = "INSERT INTO MEDIA_SOURCE(TIMESTAMP, NAME, TYPE, CATEGORY,
    SOURCE_URL, AUX_INFO, LANGUAGE) VALUES  (?, ?, ?, ?, ?, ?, ?)";
foreach ($media_sources as $media_source) {
    $db->execute($sql, $media_source);
}
$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 1, 'media:video')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 100, 1, -1,
    'media:news')");
$db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
    'images','m:2', 50, '')");
$db->execute("INSERT INTO TRANSLATION VALUES (1002, 'db_subsearch_images')");
$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
    'videos','m:3', 10, '')");
$db->execute("INSERT INTO TRANSLATION VALUES (1003, 'db_subsearch_videos')");
$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_news',
    'news','m:4', 20, 'lang:default-major  highlight:1')");
$db->execute("INSERT INTO TRANSLATION VALUES (1004, 'db_subsearch_news')");
$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_trends',
    'trends','-1', 10, 'trending:news:score_desc highlight:2')");
$db->execute("INSERT INTO TRANSLATION VALUES (1005, 'db_subsearch_trends')");
$sql = "INSERT INTO SCRAPER(NAME, SIGNATURE, TEXT_PATH, DELETE_PATHS,
    EXTRACT_FIELDS) VALUES (?, ?, ?, ?, ?)";
$scrapers = [
    ["DRUPAL", "/html/head/*[contains(@href, '/sites/all/themes') or " .
        "contains(@href, '/sites/default/files') or " .
        "contains(@content, 'Drupal')]",
        "//div[@id='page']|//main",
        "//*[contains(@id,'comments')]\n" .
        "//*[contains(@id,'respond')]\n" .
        "//*[contains(@class,'bottomcontainerBox')]\n" .
        "//*[contains(@class,'post-by')]\n" .
        "//*[contains(@class,'entry meta-clear')]",
        ""],
    ["MEDIAWIKI", "//meta[contains(@content, 'MediaWiki')]",
        "//*[contains(@id, 'mw-content-text')]",
        "//*[contains(@class, 'nmbox')]\n" .
        "//*[contains(@class, 'hatnote')]\n" .
        "//*[contains(@class, 'infobox')]",
        ""],
    ["VBULLETIN", "/html/head/*[contains(@href,'vbulletin')]",
        "//div[contains(@class, 'body_wrapper')]",
        "//*[contains(@id, 'above')]\n" .
        "//*[contains(@id, 'below')]\n" .
        "//*[contains(@id, 'breadcrumb')]\n" .
        "//*[contains(@id, 'notices')]\n" .
        "//*[contains(@id, 'footer')]\n".
        "//*[contains(@id, 'forum_info_options')]\n" .
        "//*[contains(@class, 'threadlisthead')]\n" .
        "//*[contains(@class, 'threaddetails')]\n".
        "//*[contains(@id, 'pagination')]\n".
        "//*[contains(@class, 'threadstats')]\n".
        "//*[contains(@class, 'threadlastpost')]\n".
        "//span[contains(@class, 'label')]",
        ""],
    ["VIDEO SITE",
        "//meta[@property='og:type' and contains(@content, 'video')]", "", "",
        "IS_VIDEO=//meta[@property='og:type' and" .
        " contains(@content, 'video')]/@content\n" .
        "IS_VR=//meta[(@property='og:video:tag' ".
        " (contains(@content, '360') or" .
        " contains(@content, '180') or contains(@content, 'VR'))]/@content\n" .
        "SITE_NAME=//meta[@property='og:site_name']/@content\n" .
        "DURATION=//meta[@property='video:duration']/@content\n" .
        "THUMB_URL=//meta[@property='og:image']/@content"],
    ["WORDPRESS", "/html/head/*[contains(@href, 'wp-content')".
        " or contains(@href, 'wp-includes')]",
        "//div[starts-with(@id, 'post-') and " .
        "'post-' = translate(@id, '0123456789', '') and " .
        "string-length(@id) >4]|//div[contains(@class, 'homepagewrapper')]" ,
        "//*[contains(@id, 'entry-comments')]\n" .
        "//*[contains(@class, 'sharedaddy')]\n" .
        "//*[contains(@class, 'blog-subscribe')]\n" .
        "//*[contains(@id, 'entry-side')]",
        ""],
    ["YIOOP", "/html/head/*[contains(@href,".
        "'c=resource&amp;a=get&amp;f=css&amp;n=auxiliary.css')]",
        "//div[contains(@class, 'body-container')]",
        "//*[contains(@id, 'message')]\n" .
        "//*[contains(@id, 'help')]\n" .
        "//*[contains(@id, 'MathJax')]\n" .
        "//*[contains(@class, 'component-container')]\n" .
        "//*[contains(@class, 'top-bar')]\n".
        "//*[contains(@class, 'query-statistics')]\n" .
        "//*[contains(@class, 'admin-collapse')]\n" .
        "//option[not(contains(@selected, 'selected'))]\n" .
        "//*[contains(@id, 'suggest')]\n" .
        "//*[contains(@id, 'spell')]",
        ""],
    ];
foreach ($scrapers as $scraper) {
    $db->execute($sql, $scraper);
}
$subsearch_translations = [
    'db_subsearch_images' => [
            "ar" => 'لصور',
            "bn" => 'প্রতিচ্ছবি',
            "de" => 'Bilder',
            "en-US" => 'Images',
            "es" => 'Imágenes',
            "fa" => 'تصاوی',
            "fr-FR" => 'Images',
            "he" => 'תמונות',
            "hi" => 'छवियां',
            "id" => 'Gambar',
            "it" => 'Immagini',
            "ja" => '画像',
            "kn" => 'ಚಿತ್ರಗಳು',
            "ko" => '이미지',
            "nl" => 'Beelden',
            "pl" => 'Obrazów',
            "pt" => 'Imagens',
            "ru" => 'Изображения',
            "te" => 'చిత్రాలు',
            "th" => 'ภาพ',
            "tl" => 'Mga larawan',
            "tr" => 'Görüntü',
            "vi-VN" => 'Hình',
            "zh-CN" => '图象',
    ],
    'db_subsearch_videos' => [
            "ar" => 'فيدي',
            "bn" => 'ভিডিও',
            "de" => 'Videos',
            "en-US" => 'Videos',
            "es" => 'Videos',
            "fa" => 'ویدیوها',
            "fr-FR" => 'Vidéos',
            "he" => 'קטעי וידאו',
            "hi" => 'वीडियो',
            "id" => 'Video',
            "it" => 'Video',
            "ja" => 'ビデオ',
            "kn" => 'ವೀಡಿಯೊಗಳು',
            "ko" => '동영상',
            "nl" => 'Videos',
            "pl" => 'Filmy',
            "pt" => 'Vídeos',
            "ru" => 'Видео',
            "te" => 'వీడియోలు',
            "th" => 'วิดีโอ',
            "tl" => 'Video',
            "tr" => 'Video',
            "vi-VN" => 'Thâu hình',
            "zh-CN" => '录影',
    ],
    'db_subsearch_news' => [
            "ar" => 'أخبار',
            "bn" => 'সংবাদ',
            "de" => 'Nachrichten',
            "en-US" => 'News',
            "es" => 'Noticias',
            "fa" => 'اخبا',
            "fr-FR" => 'Actualités',
            "he" => 'חדשות',
            "hi" => 'समाचार',
            "id" => 'Berita',
            "it" => 'Notizie',
            "ja" => 'ニュース',
            "kn" => 'ಸುದ್ದಿ',
            "ko" => '뉴스',
            "nl" => 'Nieuws',
            "pl" => 'Wiadomości',
            "pt" => 'Notícias',
            "ru" => 'Новости',
            "te" => 'న్యూస్',
            "th" => 'ข่าว',
            "tl" => 'Balita',
            "tr" => 'Haber',
            "vi-VN" => 'Tin tức',
            "zh-CN" => '新闻',
    ],
    'db_subsearch_trends' => [
            "ar" => 'الاتجاهات',
            "bn" => 'প্রবণতা',
            "de" => 'Trends',
            "en-US" => 'Trends',
            "es" => 'Tendencias',
            "fa" => 'روند',
            "fr-FR" => 'Tendances',
            "he" => 'גמות',
            "hi" => 'रुझान',
            "id" => 'Tren',
            "it" => 'Tendenze',
            "ja" => '動向',
            "kn" => 'ಪ್ರವೃತ್ತಿಗಳು',
            "ko" => '동향',
            "nl" => 'Trends',
            "pl" => 'Trendy',
            "pt" => 'Tendências',
            "ru" => 'Тенденции',
            "te" => 'ట్రెండ్',
            "th" => 'แนว โน้ม',
            "tl" => 'Mga uso',
            "tr" => 'Eğilim',
            "vi-VN" => 'Xu hướng',
            "zh-CN" => '趨勢',
    ]
];
foreach ($subsearch_translations as $identifier => $locale_translations) {
    foreach ($locale_translations as $locale_tag => $translation) {
        L\updateTranslationForStringId($db, $identifier, $locale_tag,
            $translation);
    }
}
if ($lower_dbms == 'pdo' && stristr(DB_HOST, 'pgsql')  !== false) {
    /* For postgres count initial values of SERIAL sequences
       will be screwed up unless do
     */
    $auto_tables = ["ACTIVITY" =>"ACTIVITY_ID", "ADVERTISEMENT" => "ID",
        "CHAT_BOT_PATTERN" => "PATTERN_ID", "GROUP_ITEM" =>"ID",
        "GROUP_PAGE" => "ID", "GROUPS" => "GROUP_ID", "LOCALE"=> "LOCALE_ID",
        "PAGE_RELATIONSHIP" => "ID", "QUERY_ITEM" => "ID",
        "ROLE" => "ROLE_ID", "SCRAPER" => "ID",
        "TRANSLATION" => "TRANSLATION_ID", "USERS" => "USER_ID"];
    foreach ($auto_tables as $table => $auto_column) {
        $sql = "SELECT MAX($auto_column) AS NUM FROM $table";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $next = $row['NUM'] ?? 1;
        $sequence = strtolower("{$table}_{$auto_column}_seq");
        $sql = "SELECT setval('$sequence', $next)";
        $db->execute($sql);
        $sql = "SELECT nextval('$sequence')";
        $db->execute($sql);
    }
}
$db->disconnect();
$private_db->disconnect();
if (in_array($lower_dbms, ['sqlite','sqlite3'])){
    chmod(CRAWL_DIR . "/data/" . DB_NAME . ".db", 0666);
    chmod(CRAWL_DIR . "/data/" . PRIVATE_DB_NAME . ".db", 0666);
}
echo "Create DB succeeded\n";
