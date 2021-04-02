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
use seekquarry\yioop\library as L;

/**
 * Element responsible for drawing the side menu portion of an admin
 * page. This allows the user to signout or select from among allowed admin
 * activities
 *
 * @author Chris Pollett
 */
class AdminmenuElement extends Element
{
    /**
     * Method responsible for drawing the side menu portion of an admin
     * page. This allows the user to signout or select from among allowed admin
     * activities
     *
     * @param array $data has info draw acitivity links on page
     */
    public function render($data)
    {
        $logged_in = !empty($data["ADMIN"]);
        $is_submenu = false;
        $admin_prefix = "";
        if (!empty($data['MENU']) && $data["MENU"] != 'adminmenu' &&
            !$logged_in) {
            return;
        } else if (!empty($data['MENU']) && $data["MENU"] != 'adminmenu' &&
            $logged_in) {
            $is_submenu = true;
            $admin_prefix = "admin-";
        }
        $token_string = ($logged_in) ? C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]
            : "";
        $logo = C\LOGO_MEDIUM;
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }
        if (!$is_submenu) {?>
            <div id='menu-options-background'
                tabindex="0" onclick="toggleOptions()">
            </div><?php
        }
        ?>
        <nav id="<?=$admin_prefix ?>menu-options" class="menu-options">
            <script>
            document.write('<div class="float-opposite medium-font ' +
            'slight-pad">[<a href="javascript:toggleOptions()" >' +
            '<span role="img" aria-label="<?=tl('adminmenu_element_close')
            ?>">X</span></a>]</div>');
            </script>
            <h2><a href="<?=C\SHORT_BASE_URL ?><?php
                if ($logged_in) {
                    e("?$token_string");
                }
                ?>"><img src="<?= C\SHORT_BASE_URL . $logo ?>" alt="<?=
                $this->view->logo_alt_text ?>" /></a></h2><?php
                if ($is_submenu) {?>
                <div class="align-opposite medium-font slight-pad reduce-top"><a
                    class="gray-link"
                    onclick="javascript:setDisplay('admin-menu-options', false);
                    setDisplay('menu-options', true);"
                    href="#menu-options"><?=
                    $data['MENU_NAME']?>&Gt;</a></div><?php
                }
            $first = true;
            foreach ($data['COMPONENT_ACTIVITIES'] as
                $component_name => $activities) {
                $count = count($activities);
                if (!empty($activities[0]['METHOD_NAME']) &&
                    $activities[0]['METHOD_NAME'] == 'manageAccount') {
                    $component_name = tl('adminmenu_element_welcome_user',
                        $_SESSION['USER_NAME']);
                }
                ?>
                <h2 class="option-heading"><?=$component_name ?><?php
                ?></h2>
                <ul class='square-list'><?php
                for ($i = 0 ; $i < $count; $i++) {
                    $method_name = $activities[$i]['METHOD_NAME'];
                    if ($method_name == 'groupFeeds') {
                        $feeds_url = B\controllerUrl("group", true) .
                            $token_string;
                        e("<li><a href='" . $feeds_url . "&amp;v=ungrouped' >"
                            . tl('adminmenu_element_combined_discussions').
                            "</a></li>");
                        e("<li><a href='" . $feeds_url . "&amp;v=grouped' >"
                            . tl('adminmenu_element_my_groups') . "</a></li>");
                    } else if ($method_name == 'manageGroups') {
                        $groups_url = B\controllerUrl("admin", true) .
                            $token_string . "&amp;a=" .
                            $activities[$i]['METHOD_NAME'];
                        e("<li><a href='$groups_url'>"
                            . $activities[$i]['ACTIVITY_NAME'] .
                            "</a></li>");
                        e("<li><a href='$groups_url&amp;browse=true'>"
                            . tl('adminmenu_element_join_groups') .
                            "</a></li>");
                    } else {
                        e("<li><a href='"
                            . B\controllerUrl("admin", true)
                            . C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]
                            . "&amp;a="
                            . $activities[$i]['METHOD_NAME']."'>"
                            . $activities[$i]['ACTIVITY_NAME'] .
                            "</a></li>");
                    }
                }
                if ($first) {
                    $first = false; ?>
                    <li><b><a href="<?=C\SHORT_BASE_URL ?>?a=signout"><?=
                        tl('adminmenu_element_signout') ?></a></b></li><?php
                }?>
                </ul>
                <?php
            }
            ?>
        </nav>
        <?php
    }
}
