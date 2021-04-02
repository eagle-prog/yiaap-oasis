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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;

/**
 * This Element is responsible for drawing screens in the Admin View related
 * to localization. Namely, the ability to create, delete, and text writing mode
 * for locales as well as the ability to modify translations within a locale.
 *
 * @author Chris Pollett
 */
class ManagelocalesElement extends Element
{
    /**
     * Responsible for drawing the ceate, delete set writing mode screen for
     * locales as well ass the screen for adding modifying translations
     *
     * @param array $data  contains info about the available locales and what
     *     has been translated
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $context = "";
        $arg_context = "";
        if ($data['FORM_TYPE'] == 'search' ||
            !empty($data['context']) && $data['context'] == 'search') {
            $context = 'context=search&amp;';
            $arg_context= "&amp;arg=search";
        }
        ?>
        <div class="current-activity">
        <?php
        $data['TABLE_TITLE'] = tl('managelocales_element_locale_list');
        $data['NO_FLOAT_TABLE'] = false;
        $data['ACTIVITY'] = 'manageLocales';
        $data['VIEW'] = $this->view;
        $num_columns = $_SERVER["MOBILE"] ? 4 : 7;
        if (in_array($data['FORM_TYPE'], ['search'])) {
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        $base_url = $admin_url . 'a=manageLocales&amp;'. $context .
            C\CSRF_TOKEN . "=".  $data[C\CSRF_TOKEN];
        if ($data['FORM_TYPE'] == 'editlocale') { ?>
            <div class="float-opposite">[<a href="<?=$base_url .
                $arg_context?>">X</a>]</div>
            <?php
            $this->renderLocaleForm($data);
            return;
        }
        ?>
        <table class="admin-table">
            <tr><td class="no-border" colspan="<?=
                $num_columns ?>"><?php $this->view->helper(
                "pagingtable")->render($data);
                if ($data['FORM_TYPE'] != "editlocale") { ?>
                    <div id='admin-form-row' class='admin-form-row'><?php
                    if ($data['FORM_TYPE'] == "search") {
                        $this->renderSearchForm($data);
                    } else {
                        $this->renderLocaleForm($data);
                    }?>
                    </div><?php
                } ?></td>
            </tr>
            <tr>
            <th><?= tl('managelocales_element_localename') ?></th>
            <?php
            if (!$_SERVER["MOBILE"]) { ?>
                <th><?= tl('managelocales_element_localetag') ?></th>
                <th><?= tl('managelocales_element_writingmode') ?></th>
                <th><?= tl('managelocales_element_enabled') ?></th>
            <?php
            }
            ?>
            <th><?= tl('managelocales_element_percenttranslated') ?></th>
            <th colspan="2"><?= tl('managelocales_element_actions') ?></th>
            </tr>
        <?php
        if (isset($data['START_ROW'])) {
            $base_url .= "&amp;start_row=".$data['START_ROW'].
                "&amp;end_row=".$data['END_ROW'].
                "&amp;num_show=".$data['NUM_SHOW'];
        }
        foreach ($data['LOCALES'] as $locale) {
            $align_style =  " class='align-right' "
            ?>
            <tr><td><a href='<?=$base_url .
                "&amp;arg=editstrings&amp;selectlocale=".$locale['LOCALE_TAG']
                ?>' ><?= $locale['LOCALE_NAME']?></a></td><?php
            if (!$_SERVER["MOBILE"]) { ?>
                <td><?=$locale['LOCALE_TAG']?></td>
                <td><?=$locale['WRITING_MODE']?></td><?php
                $gr_class = ($locale['ACTIVE']) ? " class='green' " :
                    " class='red' ";
                ?>
                <td <?=$gr_class?> ><?= ($locale['ACTIVE'] ?
                    tl('managelocales_element_true') :
                    tl('managelocales_element_false'))?></td><?php
            } ?>
            <td <?=$align_style ?>><?=
                $locale['PERCENT_WITH_STRINGS']?></td>
            <td><a href='<?=$base_url .
                "&amp;arg=editlocale&amp;selectlocale=".
                $locale['LOCALE_TAG']?>' ><?=
                tl('managelocales_element_edit')?></a></td>
            <td><a href='<?=$base_url
                ."&amp;arg=deletelocale&amp;selectlocale=".
                $locale['LOCALE_TAG']?>'
                onclick='javascript:return confirm("<?=
                tl('managelocales_confirm_delete')?>");' ><?=
                tl('managelocales_element_delete')?></a></td>
            </tr><?php
        } ?>
        </table>
        </div>
    <?php
    }
    /**
     * Draws the add locale and edit locale forms
     *
     * @param array $data consists of values of locale fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderLocaleForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=" . $data[C\CSRF_TOKEN].
            "&amp;a=manageLocales";
        $editlocale = ($data['FORM_TYPE'] == "editlocale") ? true: false; ?>
        <form id="admin-form" method="post"><?php
        if ($editlocale) { ?>
            <h2><?=tl('managelocales_element_locale_info') ?><?php
        } else { ?>
            <h2><?= tl('managelocales_element_add_locale') ?><?php
        }
        e("&nbsp;" . $this->view->helper("helpbutton")->render(
            "Add Locale", $data[C\CSRF_TOKEN]));?>
        </h2>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="<?= $data['FORM_TYPE'] ?>" />
        <?php
        if ($editlocale) {
        ?>
            <input type="hidden" name="selectlocale" value="<?=
            $data['CURRENT_LOCALE']['localetag'] ?>" /><?php
        }
        ?>
        <table class="name-table">
            <tr><th><label for="locale-name"><?php
                e(tl('managelocales_element_localenamelabel'))?></label></th>
                <td><input type="text" id="locale-name"
                    name="localename" maxlength="<?=C\LONG_NAME_LEN
                    ?>" class="narrow-field"
                    value="<?= $data['CURRENT_LOCALE']['localename'] ?>"
                    <?php
                    if ($editlocale) {
                        e(' disabled="disabled" ');
                    }
                    ?> />
                </td>
            </tr>
            <tr><th><label for="locale-tag"><?=
                tl('managelocales_element_localetaglabel')?></label></th>
                <td><input type="text" id="locale-tag"
                name="localetag"  maxlength="<?= C\NAME_LEN ?>"
                value="<?= $data['CURRENT_LOCALE']['localetag'] ?>"
                <?php
                if ($editlocale) {
                    e(' disabled="disabled" ');
                }
                ?>
                class="narrow-field"/></td>
            </tr>
            <tr><th><?=tl('managelocales_element_writingmodelabel')?></th>
            <td><?php $this->view->helper("options")->render(
                        "writing-mode", "writingmode",
                        $data['WRITING_MODES'],
                        $data['CURRENT_LOCALE']['writingmode']); ?>
            <?= $this->view->helper("helpbutton")->render(
                "Locale Writing Mode", $data[C\CSRF_TOKEN]) ?>
            </td>
            </tr>
            <tr><th><label for="locale-active"><?=
                tl('managelocales_element_localeenabled')?></label></th>
            <td><input type="checkbox" id="locale-active"
                    name="active" value="1" <?php
                    if ($data['CURRENT_LOCALE']['active'] > 0) {
                        e("checked='checked'");
                    }
                    ?> />
            </td>
            </tr>
            <tr><td></td><td class="center"><button class="button-box" <?php
                if ($data['FORM_TYPE'] == 'editlocale') {
                    e("id='focus-button'");
                }?>
                type="submit" name="update" value="true"><?=
                tl('managelocales_element_save')
                ?></button></td>
            </tr>
        </table>
        </form>
        <?php
    }
    /**
     * Draws the search for locales forms
     *
     * @param array $data consists of values of locale fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageLocales";
        $view = $this->view;
        $title = tl('managelocales_element_search_locales');
        $fields = [
            tl('managelocales_element_localename') => "name",
            tl('managelocales_element_localetag') => "tag",
            tl('managelocales_element_writingmode') => "mode",
            tl('managelocales_element_enabled') =>
                ["active", $data['EQUAL_COMPARISON_TYPES']]
        ];
        $dropdowns = [
            "mode" => ["lr-tb" => "lr-rb", "rl-tb" => "rl-tb",
                "tb-rl" => "tb-rl", "tb-lr" => "tb-lr"],
            "active" => ["1" => tl('managelocales_element_true'),
                "0" => tl('managelocales_element_false')]
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $fields, $dropdowns);
    }
}
