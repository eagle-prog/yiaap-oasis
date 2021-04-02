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
 * Web page used to present search results
 * It is also contains the search box for
 * people to types searches into
 *
 * @author Chris Pollett
 */
class SearchView extends ComponentView
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * The constructor adds to its container all the objects needed to
     * draw a search landing or result page
     * @param object $controller_object that is using this view
     */
    public function __construct($controller_object = null)
    {
        parent::__construct($controller_object);
        $this->addContainer("top", "searchbar");
        $this->addContainer("top", "<div class='nav-container'>");
        $this->addContainer("top", "searchmenu");
        $this->addContainer("top", "adminmenu");
        $this->addContainer("top", "</div>");
        $this->addContainer("sub-top", "header");
        if (!$_SERVER["MOBILE"]) {
            $this->addContainer("opposite", "searchcallout");
        }
        $this->addContainer("center", "topadvertisement");
        $this->addContainer("center", "sideadvertisement");
        $this->addContainer("center", "displayadvertisement");
        if ($_SERVER["MOBILE"]) {
            $this->addContainer("center", "searchcallout");
        }
        $this->addContainer("center", "search");
        $this->addContainer("center", "pagination");
        $this->addContainer("center", "trending");
        $this->addContainer("center", "footer");
    }
}
