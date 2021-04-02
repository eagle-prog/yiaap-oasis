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
 * Contains the forms for managing Web Page Scrapers.
 *
 * @author Charles Bocage (repurposed from CMS Detector element by
 *  Chris Pollett)
 */
class ScrapersElement extends Element
{
    /**
     * Renders Web Scrapers form and the table used for drawing the
     * current scrapers
     *
     * @param array $data contains the available scrapers
     *      as well as potentially edit info for the current scraper
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $token_string = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
        $pre_base_url = $admin_url . $token_string;
        $base_url = $pre_base_url . "&amp;a=scrapers";
        ?>
        <div class="current-activity"><?php
        $data['TABLE_TITLE'] = tl('scrapers_element_scrapers');
        $data['TABLE_ID'] = "scrapers-table";
        $data['NO_FLOAT_TABLE'] = false;
        $data['ACTIVITY'] = 'scrapers';
        $data['VIEW'] = $this->view;
        $num_columns = 3;
        $paging = "&amp;start_row=".$data['START_ROW'].
            "&amp;end_row=".$data['END_ROW'].
            "&amp;num_show=".$data['NUM_SHOW'];
        if (in_array($data['FORM_TYPE'], ['edit', 'search'])) {
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        if ($data['FORM_TYPE'] == 'edit') {
            $this->view->helper("close")->render($base_url . $arg_context);
            $this->renderScraperForm($data);
            return;
        }
        ?>
        <table class="admin-table scrapers-table" >
        <tr><td class="no-border" colspan="<?=
            $num_columns ?>"><?php $this->view->helper(
            "pagingtable")->render($data);
            if ($data['FORM_TYPE'] != "edit") { ?>
                <div id='admin-form-row' class='admin-form-row'><?php
                if ($data['FORM_TYPE'] == "search") {
                    $this->renderSearchForm($data);
                } else {
                    $this->renderScraperForm($data);
                }?>
                </div><?php
            } ?></td>
        </tr>
        <tr><th><?= tl('scrapers_element_info_heading') ?></th>
            <th colspan="2"><?= tl('scrapers_element_actions') ?></th>
        <?php
        foreach ($data['SCRAPERS'] as $scraper) {
            $encode_source = urlencode(
                urlencode($scraper['NAME']));
            $td_style = ($data['FORM_TYPE'] == 'edit' &&
                $data['CURRENT_SCRAPER']['name'] == $scraper['NAME']) ?
                "class='admin-edit-box'" : "";  ?>
            <tr>
                <td>
                <b><?= $scraper['NAME'] ?></b><br />
                <b><?=tl('scrapers_element_signature') ?></b>
                <pre><?=$scraper['SIGNATURE'] ?></pre>
                <b><?=tl('scrapers_element_priority') ?></b>
                <pre><?=$scraper['PRIORITY'] ?></pre>
                <b><?=tl('scrapers_element_text_path') ?></b>
                <pre><?=$scraper['TEXT_PATH'] ?></pre>
                <b><?=tl('scrapers_element_delete_paths') ?></b>
                <pre><?=$scraper['DELETE_PATHS'] ?></pre>
                <b><?=tl('scrapers_element_extract_fields') ?></b>
                <pre><?=$scraper['EXTRACT_FIELDS'] ?></pre>
                </td>
                <td><a href="<?=$base_url."&amp;arg=edit&amp;id=".
                    $scraper['ID']. $paging ?>"><?=
                tl('scrapers_element_edit') ?></a></td>
                <td <?=$td_style ?>><a onclick='javascript:return confirm("<?=
                    tl('scrapers_element_confirm_delete') ?>");' href="<?=
                    $base_url . "&amp;arg=delete&amp;id=".
                    $scraper['ID'] . $paging  ?>"><?=
                    tl('scrapers_element_delete_scraper')
                ?></a></td></tr><?php
            } ?>
        </table>
        </div><?php
    }
    /**
     * Used to draw the formm for adding a new scraper or editing an existing
     * one
     *
     * @param array $data contains potentially edit info for the current scraper
     */
    public function renderScraperForm($data)
    {
        if ($data["FORM_TYPE"] == "edit") {
            ?>
            <h2><?= tl('scrapers_element_edit_scraper')?></h2>
            <?php
        } else {
            ?>
            <h2><?= tl('scrapers_element_add_scraper')?>
            <?= $this->view->helper("helpbutton")->render(
                "Scrapers", $data[C\CSRF_TOKEN]) ?>
            </h2>
            <?php
        }
        ?>
        <form id="admin-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="scrapers" />
        <input type="hidden" name="arg" value="<?=
            $data['FORM_TYPE']?>" />
        <?php
        if ($data['FORM_TYPE'] == "edit") {
            ?>
            <input type="hidden" name="id" value="<?= $data['id']?>" />
            <?php
        }
        ?>
        <table class="name-table">
        <tr><td><label for="scraper-name"><b><?=
            tl('scrapers_element_scraper_name')?></b></label></td><td>
            <input type="text" id="scraper-name" name="name"
                value="<?=$data['CURRENT_SCRAPER']['name'] ?>"
                maxlength="<?= C\LONG_NAME_LEN ?>"
                class="wide-field" <?php
                if ($data["FORM_TYPE"] == "edit") {
                    e("disabled='disabled'");
                } ?>/></td></tr>
        <tr><td><label for="scrapers-signature"><b><?=
            tl('scrapers_element_signature')?></b></label></td><td>
            <input type="text" id="scrapers-signature" name="signature"
                value="<?=$data['CURRENT_SCRAPER']['signature'] ?>"
                maxlength="<?=C\MAX_URL_LEN ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="scraper-priority"><b><?=
            tl('scrapers_element_priority')?></b></label></td><td>
            <?php $this->view->helper("options")->render("scraper-priority",
            "priority", $data['SCRAPER_PRIORITIES'],
                $data['CURRENT_SCRAPER']['priority']);
                ?></td></tr>
        <tr><td><label for="scraper-text-path"><b><?=
            tl('scrapers_element_text_path')?></b></label></td><td>
            <input type="text" id="scraper-text-path"
                name="text_path"
                value=
                "<?=$data['CURRENT_SCRAPER']['text_path']?>"
                maxlength="<?=C\MAX_URL_LEN ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="scrapers-delete-paths"><b><?=
            tl('scrapers_element_delete_paths')?></b></label></td><td>
            <textarea class="short-text-area" id="scrapers-delete-paths"
                name="delete_paths"><?=
                $data['CURRENT_SCRAPER']['delete_paths']?></textarea></td></tr>
        <tr><td><label for="scrapers-extract-fields"><b><?=
            tl('scrapers_element_extract_fields')?></b></label></td><td>
            <textarea class="short-text-area" id="scrapers-extract-fields"
                name="extract_fields"><?=
                $data['CURRENT_SCRAPER']['extract_fields']
                ?></textarea></td></tr>
        <tr><td></td><td class="center"><button class="button-box" <?php
        if ($data['FORM_TYPE'] == 'edit') {
            e("id='focus-button'");
        }?>
            type="submit"><?=tl('scrapers_element_save')
            ?></button></td></tr>
        </table>
        </form><?php
    }
    /**
     * Draws the search for Web Scrapers
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "scrapers";
        $view = $this->view;
        $title = tl('scrapers_element_search');
        $fields = [
            tl('scrapers_element_name') => "name",
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $fields);
    }
}
