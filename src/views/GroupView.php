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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;

/**
 * View used to draw and allow editing of group feeds when not in the admin view
 * (so activities panel on side is not present.) This is also used to draw
 * group feeds for public feeds when not logged.
 *
 * @author Chris Pollett
 */
class GroupView extends ComponentView implements CrawlConstants
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * constructor stores a reference to the view this element will reside on
     *
     * @param object $view   object this element will reside on
     */
    public function __construct($view = null)
    {
        parent::__construct($view);
        $this->addContainer("top", "groupbar");
        $this->addContainer("top", "<div class='nav-container'>");
        $this->addContainer("top", "groupmenu");
        $this->addContainer("top", "adminmenu");
        $this->addContainer("top", "</div>");
        $this->addContainer("sub-top", "header");
        $this->addContainer("center", "group");
        $this->addContainer("center", "footer");
    }
}
