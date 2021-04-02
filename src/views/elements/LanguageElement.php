<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2021 Chris Pollett chris@pollett.org
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

/**
 * Element used to display available languages in the settings view
 *
 * @author Chris Pollett
 */
class LanguageElement extends Element
{
    /**
     * Draws a selects tag with a list of available languages
     *
     * @param array $data this variables LANGUAGES elt contains pairs of
     *     IANA tag and language names; its LOCALE_TAG is the current
     *     IANA locale tag
     */
    public function render($data)
    {
        if (empty($data['LANGUAGES'])) {
            return;
        }
        $num_languages = count($data['LANGUAGES']);
        $size = min(4, $num_languages);
        if (!empty($data['LANGUAGES_TO_SHOW'])) {
            $size = $data['LANGUAGES_TO_SHOW'];
        }
        ?>
        <select id="locale" name="lang" dir="ltr" size="<?= $size ?>">
        <?php
        foreach ($data['LANGUAGES'] as $locale_tag => $locale_name) {
            if ($data['LOCALE_TAG'] == $locale_tag) {
                e('<option value="'.$locale_tag.'"  selected="selected">'.
                    $locale_name.'</option>');
            } else {
                e('<option value="'.$locale_tag.'">'.$locale_name.'</option>');
            }
        }
        ?>
        </select>
        <?php
    }

}
