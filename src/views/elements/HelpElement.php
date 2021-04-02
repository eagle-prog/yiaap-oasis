<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2021  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @license https://www.gnu.org/licenses/ GPL3
 *  @link https://www.seekquarry.com/
 *  @copyright 2009 - 2021
 *  @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop\configs as C;

/**
 * This element is used to display the list of available activities
 * in the AdminView
 *
 * @author Eswara Rajesh Pinapala
 */
class HelpElement extends Element
{
    /**
     * Displays a list of admin activities
     *
     * @param array $data available activities and CSRF token
     */
    public function render($data)
    {
        $mobile = !empty($_SERVER['MOBILE']);
        $help_class_add = ($mobile) ? "" : "small-margin-help-pane";
        $help_id = ($mobile) ? "mobile-help" : "small-margin-help";
        $str_mobile = ($mobile) ? "true" : "false";
        ?>
        <div id="<?= $help_id ?>">
            <div id="help-frame" class="frame help-pane <?=$help_class_add ?>">
                <div id="help-close" class="float-opposite">
                    [<a class="close" tabindex="0"
                        onclick="toggleHelp('help-frame',
                        <?=$str_mobile; ?>,'<?= $_REQUEST['c']
                        ?>');return false; ">X </a>]
                </div>
                <div id="help-frame-head">
                    <h2 id="page_name" class="help-title">&nbsp;</h2>
                </div>
                <div id="help-frame-body" class="wordwrap">
                </div>
                <div id="help-frame-editor" class="wordwrap">
                </div>
            </div>
        </div><?php
    }
}
