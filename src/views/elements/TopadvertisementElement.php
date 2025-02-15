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

/**
 * This element is used to draw the keyword advertisement above search
 * results (if present)
 *
 * @author Chris Pollett
 */
class TopadvertisementElement extends Element
{
    /**
     * Draws a keyword advertisement (if there is one) at the top
     * of a search results page
     * @param array $data with a field TOP_ADSCRIPT that should contain
     *  the advertisement text
     */
    public function render($data)
    {
        $is_landing = (!isset($data['PAGES']) && !isset($data['MORE']));
        if ((!$is_landing) && in_array(C\AD_LOCATION, ['top', 'both'] ) &&
            !empty($data['TOP_ADSCRIPT'])) {
            ?>
            <div class="top-adscript"><?= $data['TOP_ADSCRIPT'] ?></div>
        <?php
        }
    }
}
