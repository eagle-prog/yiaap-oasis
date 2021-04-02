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
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
/**
 * Element used to draw the navigation bar on admin pages.
 *
 * @author Chris Pollett
 */
class AdminbarElement extends Element
{
    /**
     * Used to draw the navigation bar on the admin portion
     * of the yioop website
     *
     * @param array $data contains anti-CSRF token, as well as data on
     *     used to render what the current admin activity is
     */
    public function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $query_parts = [];
        $token_string = "";
        if ($logged_in) {
            $query_parts[C\CSRF_TOKEN] = $data[C\CSRF_TOKEN];
            $token_string = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
        }
        $logo = C\LOGO_MEDIUM;
        $current_activity = (empty($data['browse']) &&
            $data['CURRENT_ACTIVITY'] != 'manageGroups') ?
            $data['CURRENT_ACTIVITY'] : tl('adminbar_element_join_groups');
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        } ?><div class="none" >[<a href="#center-container"><?=
        tl('adminbar_element_skip_nav')
        ?>]</a></div><div id='nav-bar' class="nav-bar">
            <div class='inner-bar'>
            <?php $this->renderSettingsToggle($logged_in); ?>
            <h1>
            <a href="<?= C\SHORT_BASE_URL . "?" .
                C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] ?>"><img
            src="<?= $logo ?>" alt="<?= $this->view->logo_alt_text
                ?>" /></a><span> - <?php
            if($data['ACTIVITY_METHOD'] != 'manageAccount') {
                ?><a href='<?=
                B\controllerUrl("admin", true) . $token_string .
                "&amp;a=manageAccounts"?>'><?=tl('adminbar_element_admin')
                ?></a><?php
            } else {
                e(tl('adminbar_element_admin'));
            }
            e(" [$current_activity]");
            ?></span></h1>
        </div>
        </div>
        <?php
    }
}
