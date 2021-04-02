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
 *
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library;

/**
 * A class used to ensure can autoload non utility and locale function when
 * using Yioop as a composer library. Also let's one set the debug level
 */
class Library
{
    /**
     * Requires non-class defined functions that belong to the Yioop library
     * which might not otherwise be autoloaded. For use when Yioop is being
     * used as a composer library
     *
     * @param bool $debugging whether to turn on php error reporting
     * @param bool $fake_profile since when used with composer typically
     *      the composer's copy of the work directory and profile is not
     *      set up. This flag is used to force Yioop to pretend the work
     *      directory is set up, so that it doesn't through errors when using
     *      the library.
     */
    public static function init($debugging = false, $fake_profile = true)
    {
        if ($debugging) {
            define("seekquarry\\yioop\\configs\\DEBUG_LEVEL", 7);
        }
        if ($fake_profile) {
            define("seekquarry\\yioop\\configs\\PROFILE", true);
        }
        /**
         * For utility functions
         */
        require_once __DIR__ . "/Utility.php";
        /**
         * So have LocaleFunctions
         */
        require_once __DIR__ . "/LocaleFunctions.php";
    }
}
