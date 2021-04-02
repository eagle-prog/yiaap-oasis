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
 * Element responsible for displaying info to allow a user to create
 * a crawl mix or edit an existing one
 *
 * @author Chris Pollett
 */
class MixcrawlsElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
        <?= $this->renderMixesTable($data); ?>
        </div>
        <?php
    }
    /**
     * Draw the table that displays the currently defined crawl mixes
     * @param array $data info about current users and current mixes, CSRF token
     */
    public function renderMixesTable($data)
    {
        $mixes_exist = isset($data['available_mixes']) &&
            count($data['available_mixes']) > 0;
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = "{$admin_url}a=mixCrawls&amp;".C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN]."&amp;arg=";
        $data['TABLE_TITLE'] = tl('mixcrawls_element_available_mixes');
        $data['ACTIVITY'] = 'mixCrawls';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = false;?>
        <div id='mix-info' ><?php
        $context = '';
        if ($data['FORM_TYPE'] == 'search' ||
            !empty($data['context']) && $data['context'] == 'search') {
            $context = 'context=search&';
        }
        $num_columns = $_SERVER["MOBILE"] ? 4 : 5;
        if (in_array($data['FORM_TYPE'], ['search'])) {
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        ?>
        <table class="admin-table">
        <tr><td class="no-border" colspan="<?=
            $num_columns ?>"><?php $this->view->helper(
            "pagingtable")->render($data); ?>
            <div id='admin-form-row' class='admin-form-row'><?php
            if ($data['FORM_TYPE'] == "search") {
                $this->renderSearchForm($data);
            } else {
                $this->renderMixForm($data);
            }?>
            </div>
            </td>
        </tr>
        <tr><th><?= tl('mixcrawls_view_name') ?></th><?php
        if (!$_SERVER["MOBILE"]) { ?>
            <th><?= tl('mixcrawls_view_definition') ?></th><?php
        }
        ?>
        <th colspan="3"><?= tl('mixcrawls_view_actions') ?></th></tr>
        <?php
        foreach ($data['available_mixes'] as $mix) { ?>
            <tr><td><b><?= $mix['NAME'] ?></b><br />
                <?= $mix['TIMESTAMP'] ?><br /><span class='smaller-font'><?=
                    date("d M Y H:i:s", $mix['TIMESTAMP'])
                ?></span></td>
            <?php
            if (!$_SERVER["MOBILE"]) { ?>
                <td><?php
                if (isset($mix['FRAGMENTS'])
                    && count($mix['FRAGMENTS'])  > 0) {
                    foreach ($mix['FRAGMENTS'] as
                        $fragment_id=>$fragment_data) {
                        if (!isset($fragment_data['RESULT_BOUND']) ||
                           !isset($fragment_data['COMPONENTS']) ||
                           count($fragment_data['COMPONENTS']) == 0) {
                           continue;
                        }
                        e(" #".$fragment_data['RESULT_BOUND']."{");
                        $plus = "";
                        foreach ($fragment_data['COMPONENTS'] as
                            $component) {
                            $crawl_timestamp = $component['CRAWL_TIMESTAMP'];
                            $order = ($component['DIRECTION'] > 0) ?
                                tl('mixcrawls_view_ascending') :
                                tl('mixcrawls_view_descending');
                            e($plus . $component['WEIGHT']." * (".
                                $data['available_crawls'][
                                $crawl_timestamp]."[$order] + K:".
                                $component['KEYWORDS'].")");
                            $plus = "<br /> + ";
                        }
                        e("}<br />");
                    }
                } else {
                    e(tl('mixcrawls_view_no_components'));
                }?>
                </td><?php
            }
            ?>
            <td><a href="<?= $base_url ?>editmix&<?=$context ?>timestamp=<?=
                $mix['TIMESTAMP'] ?>"><?=
                tl('mixcrawls_view_edit')?></a></td>
            <td><?php
            if ( $mix['TIMESTAMP'] != $data['CURRENT_INDEX']) { ?>
                <a href="<?= $base_url ?>index&<?=$context ?>timestamp=<?=
                $mix['TIMESTAMP'] ?>"><?= tl('mixcrawls_set_index') ?></a>
                <?php
            } else { ?>
                <?= tl('mixcrawl_search_index') ?> <?php
            } ?>
            </td>
            <td><a onclick='javascript:return confirm("<?=
                tl('mixcrawls_element_confirm_delete') ?>");'
                href="<?= $base_url
                ?>deletemix&<?=$context ?>timestamp=<?=$mix['TIMESTAMP']
                ?>"><?= tl('mixcrawls_view_delete') ?></a></td>
            </tr><?php
        }
        ?>
        </table>
        </div><?php
    }
    /**
     * Draws the create mix form
     *
     * @param array $data used for CSRF_TOKEN
     */
    public function renderMixForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = "{$admin_url}a=mixCrawls&amp;" . C\CSRF_TOKEN."=" .
            $data[C\CSRF_TOKEN]."&amp;arg=";
        ?>
        <form id="admin-form" method="get">
        <h2><?= tl('mixcrawls_element_make_mix') ?>
        <?= $this->view->helper("helpbutton")->render(
            "Crawl Mixes", $data[C\CSRF_TOKEN]) ?></h2>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="createmix" />
        <div class="top-margin">
            <table class="name-table">
            <tr><th>
            <label for="mix-name"><?=
            tl('mixcrawls_element_mix_name') ?></label></th><td>
            <input type="text" id="mix-name" name="NAME"
                value="" maxlength="<?= C\NAME_LEN ?>"
                    class="wide-field"/></td></tr>
            <tr><td></td><td>
            <button class="button-box"  type="submit"><?=
            tl('mixcrawls_element_create_button') ?></button>
        </td></tr>
        </table>
        </div>
        </form>
        <?php
    }
    /**
     * Draws the search for mixes forms
     *
     * @param array $data consists of values of mix fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "mixCrawls";
        $view = $this->view;
        $title = tl('mixcrawls_element_search_mix');
        $fields = [
            tl('mixcrawls_element_mixname') => "name",
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $fields);
    }
}
