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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\views\elements\Element;

/**
 * Element used to render the admin interface for a logged in user of Yioop
 *
 * @author Chris Pollett
 */
class AdminElement extends Element implements CrawlConstants
{
    /**
     * Renders the list of admin activities and draws the current activity
     * Renders the Javascript to autologout after an hour
     *
     * @param array $data  what is contained in this array depend on the current
     * admin activity. The $data['ELEMENT'] says which activity to render
     */
    public function render($data)
    {
        ?><div class="content-container" ><?php
        if (isset($data['ELEMENT'])) {
            $element = $data['ELEMENT'];
            $this->view->element($element)->render($data);
        }
        if (C\PROFILE) { ?>
            <script>
            /*
                Used to warn that user is about to be logged out
             */
            function logoutWarn()
            {
                doMessage("<h2 class='red'><?php
                    e(tl('admin_element_auto_logout_one_minute'))?></h2>");
            }
            /*
                Javascript to perform autologout
             */
            function autoLogout()
            {
                document.location='<?=C\SHORT_BASE_URL ?>?a=signout';
            }
            //schedule logout warnings
            var sec = 1000;
            var minute = 60 * sec;
            var autologout = <?=C\AUTOLOGOUT ?> * sec;
            setTimeout("logoutWarn()", autologout - minute);
            setTimeout("autoLogout()", autologout);
            </script><?php
        } ?>
        </div><?php
    }
}
