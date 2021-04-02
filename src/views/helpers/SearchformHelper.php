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
namespace seekquarry\yioop\views\helpers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;

/**
 * Used to draw the form to do advanced search for items in a user, group,
 * locale, etc folder
 *
 * @author Chris Pollett
 */
class SearchformHelper extends Helper
{
    /**
     * Draw the form for advanced search for any HTML table drawn based on
     * using a model's getRow function
     *
     * @param array  $data from the controller with info of what fields might
     *     already be filled.
     * @param object $controller what controller is being used to handle logic
     * @param string $activity what activity the controller was executing
     *     (for return link)
     * @param object $view which view is responsible for calling this helper
     * @param string $title what to display as the header of this form
     * @param array $fields a list of searchable fields
     * @param array $dropdowns which fields should be rendered as dropdowns
     *      this array has the format field_name => dropdown_array
     *      If dropdown_array is a string among time, date, datetime-locale
     *      then a date picker variant will be used for the item. Otherwise,
     *      dropdown_array is assume to be in the form value =>
     *          value translation into locale string
     * @param string $postfix string to tack on to form variables (might use
     *     to make var names unique on page)
     */
    public function render($data, $controller, $activity, $view, $title,
        $fields, $dropdowns = [], $postfix = "")
    {
        $base_url = htmlentities(B\controllerUrl($controller, true)) .
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&amp;a=$activity";
        $old_base_url = $base_url;
        $search_arg = (empty($data['SEARCH_ARG'])) ? 'search' :
            $data['SEARCH_ARG'];
        $browse = false;
        if (isset($data['browse'])) {
            $base_url .= "&amp;browse=" . $data['browse'];
            $browse = true;
        }
        ?>
        <h2><?= $title . "&nbsp;" ?> </h2><?php
        $item_sep = ($_SERVER["MOBILE"]) ? "<br />" : "</td><td>";
        $item_sep_center = ($_SERVER["MOBILE"]) ? "<br />" :
            "</td><td class='center'>";
        ?>
        <form id="search-form" method="get" autocomplete="off">
        <input type="hidden" name="c" value="<?= $controller ?>" />
        <input type="hidden" name="<?=C\CSRF_TOKEN  ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="<?= $activity ?>" />
        <input type="hidden" name="arg" value="<?=$search_arg?>" />
        <?php
        if ($browse) { ?>
            <input type="hidden" name="browse" value="true" />
            <?php
        }
        ?>
        <table class="name-table">
        <?php
        foreach ($fields as $label => $name) {
            if (is_array($name)) {
                $comparison_types = $name[1];
                $name = $name[0];
            } else {
                $comparison_types = $data['COMPARISON_TYPES'];
            }
            e("<tr><td class='table-label'><label for='{$name}-id'>".
                "$label</label>");
            e($item_sep);
            $out_name = $name;
            $out_id_name = $name;
            if ($postfix != "") {
                $out_name = $name . "_$postfix";
                $out_id_name = $name . "-$postfix";
            }
            $view->helper("options")->render(
                "{$out_id_name}-comparison", "${out_name}_comparison",
                $comparison_types, $data["{$out_name}_comparison"], false,
                ['class' => 'full-width']);
            e($item_sep_center);
            if (isset($dropdowns[$name]) && in_array($dropdowns[$name],
                ['date', 'time'])) {
                e("<div class='range-field' ><input type='".
                    $dropdowns[$name]. "' " .
                    "id='{$out_id_name}-id-low' name='".$out_name . "_low' ".
                    "value='" . $data[$out_name . "_low"] .
                    "' class='narrow-field'  /> <hr /> ");
                e("<input type='". $dropdowns[$name]. "' " .
                    "id='{$out_id_name}-id-high' name='" . $out_name .
                    "_high' value='" . $data[$out_name . "_high"] .
                    "' class='narrow-field'  /></div>");
            } else if (isset($dropdowns[$name]) && in_array($dropdowns[$name],
                ['range'])) {
                e("<div class='range-field' ><input type='number' " .
                    "id='{$out_id_name}-id-low' name='".$out_name."_low' ".
                    "value='" . $data[$out_name . "_low"].
                    "' class='narrow-field'  /> <hr /> ");
                e("<input type='number' " .
                    "id='{$out_id_name}-id-high' name='" . $out_name."_high' ".
                    "value='" . $data[$out_name . "_high"] .
                    "' class='narrow-field'  /></div>");
            } else if (isset($dropdowns[$name])) {
                $dropdowns[$name] =
                    ['-1' => tl('searchform_helper_any')] +
                    $dropdowns[$name];
                if ($data["{$out_name}"] == "") {
                    $data["{$out_name}"] = '-1';
                }
                $view->helper("options")->render("{$out_id_name}-id",
                    "{$out_name}", $dropdowns[$name], $data["{$out_name}"],
                    false, ['class' => 'full-width']);
                ?><?php
            } else {
                e("<input type='text' id='{$out_id_name}-id' name='$out_name' ".
                    "maxlength='". C\LONG_NAME_LEN. "' ".
                    "value='{$data[$out_name]}' ".
                    "class='narrow-field'  />");
            }
            e($item_sep);
            $view->helper("options")->render("{$out_id_name}-sort",
                "{$out_name}_sort", $data['SORT_TYPES'],
                $data["{$out_name}_sort"]);
            e("</td></tr>");
        }
        ?>
        <tr><?php if (!$_SERVER["MOBILE"]) {?><td></td><td></td> <?php } ?>
            <td <?php if (!$_SERVER["MOBILE"]) {
                    ?>class="center" <?php
                }
                ?>><button class="button-box"
                type="submit"><?= tl('searchform_helper_search')
                ?></button></td><td></td>
        </tr>
        </table>
        </form>
        <?php
    }

}
