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
use seekquarry\yioop\controllers\components\CrawlComponent as CR;

/**
 * Element responsible for displaying info about a given crawl mix
 *
 * @author Chris Pollett
 */
class EditmixElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $context = "";
        if (!empty($data['context'])) {
            $context = "context=true&amp;arg=search&amp;";
        }
        ?>
        <div class="current-activity">
        <?=$this->view->helper("close")->render($admin_url .
            "a=mixCrawls&amp;$context" . C\CSRF_TOKEN . '='.
            $data[C\CSRF_TOKEN]); ?>
        <h2><?= tl('editmix_element_edit_mix')?></h2>
        <form id="mixForm" method="get">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="mixCrawls" />
        <?php
            if ($context) { ?>
                <input type="hidden" name="context" value="search" />
                <?php
            }
        ?>
        <input type="hidden" name="arg" value="editmix" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="mix[TIMESTAMP]"
            value="<?= $data['MIX']['TIMESTAMP'] ?>" />
        <div class="top-margin"><label for="mix-name"><?=
            tl('editmix_element_mix_name') ?></label>
            <input type="text" id="mix-name" name="mix[NAME]"
                value="<?php if (isset($data['MIX']['NAME'])) {
                    e($data['MIX']['NAME']); } ?>" maxlength="<?=
                    C\NAME_LEN ?>" class="wide-field"/>
        </div>
        <h3><?= tl('editmix_element_mix_components')?>
        <?= $this->view->helper("helpbutton")->render(
            "Editing a Crawl Mix", $data[C\CSRF_TOKEN]) ?>
        </h3>
        <div>
        [<a href='javascript:addFragment(1, <?=CR::MAX_MIX_FRAGMENTS?>, <?='"'.
            tl('editmix_element_too_many').'"'
            ?>)'><?=tl('editmix_element_add_fragment') ?></a>]
        </div>
        <div id="mix-tables" >
        </div>
        <div class="center slight-pad">
            <button class="button-box"
            type="submit"><?= tl('editmix_element_save_button')
            ?></button></div>
        </form>
        </div>
    <?php
    }
}
