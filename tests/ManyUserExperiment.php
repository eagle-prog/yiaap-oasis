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
 * Test to see for big strings which how long various string concatenation
 * operations take.
 *
 * @author Chris Pollett chris@pollett.orgs
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\models as M;

if (isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}
/**
 * This script inserts 500 users into the database so that one can
 * test UI of Yioop in a scenario that has a moderate number of users.
 * It then insert groups for these users
 */
/**
 * Calculate base directory of script
 * @ignore
 */
define("seekquarry\\yioop\\configs\\PARENT_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/tests")));
define("seekquarry\\yioop\\configs\\BASE_DIR", C\PARENT_DIR . "/src");
require_once C\BASE_DIR.'/configs/Config.php';
$user_model = new M\UserModel();
//Add lots of users
$user_ids = [];
for ($i = 0; $i < 500; $i++) {
    echo "Adding User $i\n";
    $id = $user_model->addUser("User$i", "test", "First$i", "Last$i",
        "user$i@email.net", C\ACTIVE_STATUS);
    if ($id === false) {
        echo "Problem inserting user into DB, aborting...\n";
        exit(1);
    }
    $user_ids[$i] = $id;
}
// add lots of groups
$group_model = new M\GroupModel();
$group_ids = [];
for ($i = 0; $i < 100; $i++) {
    echo "Creating Group $i\n";
    $group_ids[$i] = $group_model->addGroup("Group$i", $user_ids[$i],
        C\PUBLIC_JOIN, C\GROUP_READ_WRITE);
}
// add lots of users to groups (everyone belongs to at least group 1)
for ($i = 0; $i < 100; $i++) {
    $user_id = $user_ids[$i];
    for ($j = 1; $j < ($i/10)+1; $j++) {
        $group_model->addUserGroup($user_id, $group_ids[$j], C\ACTIVE_STATUS);
    }
}
$impression_model = new M\ImpressionModel();
// add lots of threads and comments (hence, also impressions) to group 1
for ($i = 0; $i < 100; $i++) {
    $user_id = $user_ids[$i];
    $title = "My first thread: User $user_id";
    echo "Adding User $user_id  Group {$group_ids[1]} Thread\n";
    $parent_id = $group_model->addGroupItem(0, $group_ids[1], $user_id,
        $title, "I really like this thread, User:$user_id");
    $title = "--" . $title;
    for ($j = 0; $j < ($i / 10) + 1; $j++) {
        $comment_user_id = $user_ids[$j];
        $impression_model->add($comment_user_id, $parent_id,
            C\THREAD_IMPRESSION);
        $impression_model->add($$comment_user_id, $group_ids[1],
            C\GROUP_IMPRESSION);
        $group_model->addGroupItem($parent_id, $group_ids[1], $comment_user_id,
            $title, "I totally agree with you, User:$comment_user_id");
    }
}
// add lots of roles
$role_model = new M\RoleModel();
$user_id = $user_ids[2];
for ($i = 0; $i < 100; $i++) {
    echo "Creating Role $i\n";
    $role_model->addRole("Role$i");
    $role_id =  $role_model->getRoleId("Role$i");
    $role_model->addUserRole($user_id, $role_id);
}
$crawl_model = new M\CrawlModel();
$mix = [];
$mix['TIMESTAMP'] = time();
$mix['FRAGMENTS'] = [];
$mix['OWNER_ID'] = C\ROOT_ID;
$mix['PARENT'] = -1;
for ($i = 0; $i < 100; $i++) {
    echo "Creating Crawl Mix $i\n";
    $mix['TIMESTAMP']++;
    $mix['NAME'] = "Mix$i";
    $crawl_model->setCrawlMix($mix);
}
