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
 * Element used to draw the navigation bar on search pages.
 *
 * @author Chris Pollett
 */
class SearchbarElement extends Element
{
    /**
     * Used to draw the navigation bar on the search page portion
     * of the yioop website
     *
     * @param array $data contains antiCSRF token, as well as data on
     *     used to initialize the search form
     */
    public function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $query_parts = [];
        if ($logged_in) {
            $query_parts[C\CSRF_TOKEN] = $data[C\CSRF_TOKEN];
        }
        $token_string = ($logged_in && isset($data[C\CSRF_TOKEN])) ?
            C\CSRF_TOKEN . "=".$data[C\CSRF_TOKEN] : "";
        $logo = C\SHORT_BASE_URL . C\LOGO_MEDIUM;
        if ($_SERVER["MOBILE"]) {
            $logo = C\SHORT_BASE_URL . C\LOGO_SMALL;
        }
        if (!empty($data['IS_LANDING'])) {
            ?><div class="none" >[<a href="#search-form"><?=
            tl('searchbar_element_skip_nav')
            ?>]</a></div><div id='nav-bar' class="nav-bar">
            <?php $this->renderNavbar($logged_in); ?>
            </div><?php
            return;
        }
        ?><div class="none" >[<a href="#center-container"><?=
        tl('searchbar_element_skip_nav')
        ?>]</a></div><div id='nav-bar' class="nav-bar">
            <div class='inner-bar'>
            <?php $this->renderNavbar($logged_in); ?>
        </div>
        </div>
        <?php
    }
}
