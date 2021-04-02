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

use seekquarry\yioop\configs as C;

/** For tl, getLocaleTag and Yioop constants */
require_once __DIR__.'/../../library/LocaleFunctions.php';

/**
 * Translate the supplied arguments into the current locale.
 *
 * This function is a convenience copy of the same function
 * @see seekquarry\yioop\library\tl() to this subnamespace
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    return call_user_func_array(C\NS_LIB . "tl", func_get_args());
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/**
 * Base Element Class.
 * Elements are classes are used to render portions of
 * a web page which might be common to several views
 * like a view there is supposed to minimal php code
 * in an element
 *
 * @author Chris Pollett
 */
abstract class Element
{
    /**
     * The View on which this Element is drawn
     * @var object
     */
    public $view;
    /**
     * constructor stores a reference to the view this element will reside on
     *
     * @param object $view   object this element will reside on
     */
    public function __construct($view = null)
    {
        $this->view = $view;
    }
    /**
     * This method is responsible for actually drawing the view.
     * It should be implemented in subclasses.
     *
     * @param $data - contains all external data from the controller
     * that should be used in drawing the view
     */
    public abstract function render($data);
    /**
     * Used to draw the hamburger menu symbol and associated link to the
     * settings menu
     *
     * @param bool $logged_in whether or not the user is logged in. If so,
     *  the hamburger menu symbol draws the users name
     */
    public function renderSettingsToggle($logged_in)
    { ?>
        <div class='noscript-hide'>
        <div class="settings" id="settings-toggle" tabindex="0" role="menu"
            onkeydown="javascript:toggleOptions()"
            onclick="javascript:toggleOptions()" ><?php
        if ($logged_in) {
            $user_name = $_SESSION['USER_NAME'];
            if (mb_strlen($user_name) > 6) {
                $user_name = mb_substr($user_name, 0, 4) . "..";
            }
            ?>
            <div class='top'>[<?=$user_name ?>]
            </div><div class='bottom'><span role="img"
                aria-label="<?= tl('element_settings_toggle')
                ?>">&equiv;</span></div>
            <?php
        } else {
            e('<span role="img" aria-label="'.
                tl('element_settings_toggle').'">&equiv;</span>');
        }?></div></div><?php
    }
}
