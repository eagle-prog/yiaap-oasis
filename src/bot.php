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
 * Web page used to display information about the web robot used in
 * the SeekQuarry/Yioop Search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop;

$_REQUEST['c'] = "static";
$_REQUEST['p'] = "bot";
require_once __DIR__ . "/library/WebSite.php";
$web_site = empty($GLOBALS['web_site']) ?
    new \seekquarry\yioop\library\WebSite() : $GLOBALS['web_site'];
require_once __DIR__ . "/library/Utility.php";
require_once __DIR__ . "/index.php";
bootstrap($web_site, false);
\seekquarry\yioop\library\webExit();
