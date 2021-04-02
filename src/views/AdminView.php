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
namespace seekquarry\yioop\views;

/**
 * View used to draw activity list and current activty for a logged in user
 *
 * @author Chris Pollett
 */
class AdminView extends ComponentView
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Add to the component view the elemnets necessary to draw the
     * list of admin activities and the current activity forr a logged in user
     * @param object $controller_object that is using this view
     */
    public function __construct($view = null)
    {
        parent::__construct($view);
        $this->addContainer("top", "adminbar");
        $this->addContainer("top", "<div class='nav-container'>");
        $this->addContainer("top", "adminmenu");
        $this->addContainer("top", "</div>");
        $this->addContainer("sub-top", "header");
        if ($_SERVER["MOBILE"]) {
            $this->addContainer("center", "help");
        }
        $this->addContainer("center", "admin");
        $this->addContainer("center", "footer");
        if (!$_SERVER["MOBILE"]) {
            $this->addContainer("opposite", "help");
        }
    }
}
