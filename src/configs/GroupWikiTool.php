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
 * GroupWikiTool is used to manage the integrity of resource folders for
 * wiki pages of Yioop Groups.
 *
 * A description of its usage is given in the $usage global variable
 *
 *
 * @author Chris Pollett
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */

namespace seekquarry\yioop\configs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\VersionManager;
use seekquarry\yioop\models\Model;
use seekquarry\yioop\models\GroupModel;

if (php_sapi_name() != 'cli' ||
    defined("seekquarry\\yioop\\configs\\IS_OWN_WEB_SERVER")) {
    echo "BAD REQUEST"; exit();
}
/** Loads common utility functions*/
require_once __DIR__."/../library/Utility.php";
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/**
 * Used to print out a description of how to use GroupWikiTool.php
 * @var string
 */
$usage = <<<EOD
GroupWikiTool.php
==============

GroupWikiTool is used to manage the integrity of resource folders for
wiki pages of Yioop Groups. The tool has a command to look up what is
the path for a wiki page of a given group for a locale. To maintain
previous version of wiki page resources, Yioop writes a .archive folder
in the wiki page's resource folder and uses it to maintain
previous versions of this folder. Before changes to the .archive
folder are made, a LOCK file is written. In the event of a crash before
completion of an operation, this LOCK file might be present and
prevent further changes to the resources in a wiki. This tool let's one
clear this lock. It also allows one to remove the existing .archive folder
and rebuild it from scratch. Finally, it allows a user to save a new version
snapshot of the current resource folder.

Usage
=====
php GroupWikiTool.php command folder

php GroupWikiTool.php clear-lock folder
  if folder is the name of a Group Wiki page resource folder, then this
  operation will remove any LOCK file on the .archive folder

php GroupWikiTool.php path group_name page_name locale_tag
  returns the resource and thumb folders for the given group, page,
  and locale.

php GroupWikiTool.php reset folder
  if folder is the name of a Group Wiki page
  resource folder, then this will delete the current .archive folder and replace
  it with a freshly computed one

php GroupWikiTool.php version folder
  if folder is the name of a Group Wiki page resource folder, then this
  will save a save a new version snapshot to the .archive subfolder

EOD;
if (empty($argv[2])) {
    $argv[2] = getcwd();
}
$num_args = count($argv);
if ( $num_args < 3 ) {
    echo $usage;
    exit();
}
switch ($argv[1]) {
    case "clear-lock":
        $lock_file = $argv[2] . "/.archive/LOCK";
        if (file_exists($argv[2])) {
            unlink($lock_file);
            echo "Group Wiki Page Resource Lock file removed!";
        }
        break;
    case "path":
        if (empty($argv[4])) {
            $argv[4] = C\DEFAULT_LOCALE;
        }
        if (empty($argv[3])) {
            $argv[3] = "Main";
        }
        if (empty($argv[2])) {
            $argv[2] = "Public";
        }
        $group_model = new GroupModel();
        $group_id = $group_model->getGroupId($argv[2]);
        if (!$group_id) {
            echo "Could not locate that group name!!\n\n";
            echo $usage;
            exit();
        }
        $page_id = $group_model->getPageId($group_id, $argv[3], $argv[4]);
        if (!$page_id) {
            echo "Could not locate that page name in {$argv[2]} wiki!!\n\n";
            echo $usage;
            exit();
        }
        $folders =
            $group_model->getGroupPageResourcesFolders($group_id, $page_id);
        if (empty($folders[1])) {
            echo "$argv[3] page folders not yet created!!\n\n";
            echo $usage;
            exit();
        }
        echo "Resource folder: {$folders[0]}\nThumb folder: {$folders[1]}\n";
        break;
    case "reset":
        if (file_exists($argv[2] . "/.archive")) {
            $model = new Model();
            $db = $model->db;
            $db->unlinkRecursive($argv[2] . "/.archive");
        }
        $vcs = new VersionManager($argv[2]);
        break;
    case "version":
        $vcs = new VersionManager($argv[2]);
        $vcs->createVersion();
        break;
    default:
        echo $usage;
        exit();
}
