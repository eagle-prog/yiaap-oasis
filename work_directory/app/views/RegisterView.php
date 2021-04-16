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
 * @author Chris Pollett
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\views;

use seekquarry\yioop\configs as C;

/**
 * Draws the page that allows a user to register for an account
 *
 * @author Mallika Perepa (creator), Chris Pollett, Akash Patel
 */
class RegisterView extends ComponentView
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is the most..? followed by one of the form Which is the least ..?
     * followed by a string which is a comma separated list of possibilities
     * arranged from least to most. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale. You can also
     * change them in the Security element
     * @var array
     */
    public $captchas_qa;
    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is your favorite..? followed by one of the form
     * Which is your like the least..? followed by a string which is a comma
     * separated choices. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale.
     * @var array
     */
    public $recovery_qa;
    /**
     * Besides setting calling the constructor for the base class this
     * constructor also sets up the captchas_qa and recovery_qa arrays
     * so they can be localized. The reason for putting these arrays in a
     * view is so that multiple controllers/components can see and manipulate
     * them
     * @param object $controller_object that is using this view
     */
    public function __construct($controller_object = null)
    {
        $this->recovery_qa = [
            [ tl('register_view_recovery1_more'),
                tl('register_view_recovery1_less'),
                tl('register_view_recovery1_choices')],
            [ tl('register_view_recovery2_more'),
                tl('register_view_recovery2_less'),
                tl('register_view_recovery2_choices')],
            [ tl('register_view_recovery3_more'),
                tl('register_view_recovery3_less'),
                tl('register_view_recovery3_choices')],
            [ tl('register_view_recovery4_more'),
                tl('register_view_recovery4_less'),
                tl('register_view_recovery4_choices')],
            [ tl('register_view_recovery5_more'),
                tl('register_view_recovery5_less'),
                tl('register_view_recovery5_choices')],
            [ tl('register_view_recovery6_more'),
                tl('register_view_recovery6_less'),
                tl('register_view_recovery6_choices')],
            ];
        parent::__construct($controller_object);
        $this->addContainer("top", "searchbar");
        $this->addContainer("center", "register");
    }
}
?>
