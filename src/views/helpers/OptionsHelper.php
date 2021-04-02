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

use seekquarry\yioop\configs as C;

/**
 * This is a helper class is used to handle
 * draw select options form elements
 *
 * @author Chris Pollett
 */
class OptionsHelper extends Helper
{
    /**
     * Draws an HTML select tag according to the supplied parameters
     *
     * @param string $id   the id attribute the select tag should have
     *      If empty string id attribute not echo'd
     * @param string $name   the name this form element should use
     *      If empty string name attribute not echo'd
     * @param array $options   an array of key value pairs for the options
     *    tags of this select element. The key is used as an option tag's
     *    value and the value being used as its contents. If the value
     *    is empty in a key value pair then the key is taken as the
     *    label of a new optgroup.
     * @param string $selected   which option (note singular -- no support
     *     for selecting more than one) should be set as selected
     *     in the select tag
     * @param mixed $onchange_action if true then submit the parent form if
     *     this drop down is changed, if false, normal dropdown, if
     *     a callback function, then when change call callback
     * @param array $additional_attributes associative array of attributes =>
     *      values to add to the open select tag if present
     */
    public function render($id, $name, $options, $selected,
        $onchange_action = false, $additional_attributes = [])
    {
        $stretch = ($_SERVER["MOBILE"]) ? 4 : 6;
        $word_wrap_len = $stretch * C\NAME_TRUNCATE_LEN;
        $id_info = ($id != "") ? " id='$id' " : " ";
        $name_info = ($name != "") ? " name='$name' " : " ";
        ?>
        <select <?= $id_info ?> <?= $name_info ?> <?php
            if ($onchange_action === true) {
                e(' onchange="this.form.submit()" ');
            } else if (is_string($onchange_action) ) {
                e(" onchange='$onchange_action' ");
            }
            foreach ($additional_attributes as $attribute => $value) {
                e(" $attribute='$value' ");
            }
        ?> >
        <?php
        $open_optgroup = false;
        foreach ($options as $value => $text) {
            if (empty($text) && !empty($value)) {
                if ($open_optgroup) {
                    ?></optgroup><?php
                }
                ?><optgroup label='<?=$value ?>'><?php
                continue;
            }
            ?>
            <option value="<?= $value ?>" <?php
                if (trim($value) == ((is_string($selected)) ? trim($selected) :
                    $selected)) {
                    e('selected="selected"');
                }
                if (mb_strlen($text) > $word_wrap_len + 3) {
                    $text = mb_substr($text, 0, $word_wrap_len)."...";
                }
             ?>><?= $text ?></option>
        <?php
        }
        if ($open_optgroup) {
            ?></optgroup><?php
        }
        ?>
    </select><?php
        if ($onchange_action !== false) { ?>
            <noscript>
            <input type="submit" value="<?=tl('option_helper_go') ?>">
            </noscript>
            <?php
        }
    }
    /**
     * Creates a dropdown where selecting an item redirects to a given url.
     *
     * @param string $id  the id attribute the select tag should have
     *      If empty string id attribute not echo'd
     * @param array $options an array of key value pairs for the options
     *    tags of this select element. The key is used as an option tag's
     *    value and the value being used as its contents. If the value
     *    is empty in a key value pair then the key is taken as the
     *    label of a new optgroup.
     * @param string $selected which url should be selected in dropdown
     * @param string $url_prefix keys in $options should correspond to urls.
     *      if such a key doesn't begin with http, it is assumed to be
     *      a url suffix and the value $url_prefix is put before it to get
     *      a complete url before the window location is changed.
     * @param boolean $as_list whether to output the result as a dropdown
     *      or as an unordered list.
     */
    public function renderLinkDropDown($id, $options, $selected, $url_prefix,
        $as_list = false)
    {
        if ($as_list) {
            $started = false;
            foreach ($options as $url => $option) {
                if (empty($option)) {
                    if ($started) {
                        $started = false;
                        e("</ul>");
                    }
                    e("<h2 class='option-heading'>". urldecode($url) . "</h2>");
                } else {
                    if (!$started) {
                        e("<ul class='square-list'>");
                        $started = true;
                    }
                    $out_url = (strpos($url, C\SHORT_BASE_URL) === false) ?
                        $url_prefix . $url : $url;
                    $out_url = rtrim($out_url, "?");
                    if ($url == $selected) {
                        e("<li><b><a href='$out_url'>" . urldecode($option).
                            "</a></b></li>");
                    } else {
                        e("<li><a href='$out_url'>" . urldecode($option).
                            "</a></li>");
                    }
                }
            }
            if ($started) {
                e("</ul>");
            }
        } else {
            $stretch = ($_SERVER["MOBILE"]) ? 4 : 6;
            $word_wrap_len = $stretch * C\NAME_TRUNCATE_LEN;
            $id_info = ($id != "") ? " id='$id' " : " ";
            $list_id_info = ($id != "") ? " id='$id-list' " : " ";
            $selected_id_info = ($id != "") ? " id='selected-$id' " : " ";
            $selected_id_info2 = ($id != "") ? " id='selected2-$id' " : " ";
            $opt_group = "";
            foreach ($options as $value => $text) {
                if (empty($selected_text) || $value == $selected) {
                    $selected_text = $text;
                }
                if (empty($text) && !empty($value)) {
                    $opt_group = " class='opt-group' ";
                }
            }
            ?>
            <div class="dropdown-container" <?= $id_info ?>>
            <ul class="link-dropdown"  >
                <li tabindex="0"><b><a class="dropdown-selector"
                    <?=$selected_id_info?>
                    href="javascript:this.preventDefault;"><?=$selected_text
                    ?></a></b>
                <ul <?=$list_id_info?>><?php
                foreach ($options as $value => $text) {
                    if (empty($text) && !empty($value)) {?>
                        <li><b><?=urldecode($value) ?></b></li><?php
                    } else {
                        if ($selected_text == $text) {?>
                            <li>&check;<a <?=
                                $selected_id_info2 ?> <?=
                                $opt_group?>  href="<?=$url_prefix .
                                $value ?>" ><?=urldecode($text) ?></a></li><?php
                        } else {?>
                            <li>&nbsp;&nbsp;&nbsp;<a <?=
                                $opt_group?>  href="<?=$url_prefix .
                                $value ?>" ><?=urldecode($text) ?></a></li><?php
                        }
                    }
                }?>
                </ul>
                </li>
            </ul>
            </div><?php
        }
    }
}
