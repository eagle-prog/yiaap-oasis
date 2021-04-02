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
 * Element used to control how urls are filtered out of search results
 * (if desired) after a crawl has already been performed.
 *
 * @author Chris Pollett
 */
class ResultsEditorElement extends Element
{
    /**
     * Draws the Screen for the Search Filter activity. This activity is
     * used to filter urls out of the search results
     *
     * @param array $data keys used to store disallowed_sites
     */
    public function render($data)
    {
        $edit_url_mode = !empty($data['MODE']) && $data['MODE'] == 'editurl';
        $edit_kwiki_mode = !empty($data['MODE']) &&
            $data['MODE'] == 'editkwiki';
        $edit_query_map_mode = !empty($data['MODE']) &&
            $data['MODE'] == 'editquerymap';
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        ?>
        <div class="current-activity">
        <ul class='tab-menu-list'>
        <li><script>
            document.write('<a href="javascript:switchTab(' +
                "'editresulttab', ['querymaptab', 'knowledgetab']);" +'" ' +
                "id='editresulttabitem' " +
                'class="<?=$data['edit_result_active'] ?>"><?=
                tl('resultseditor_element_edit_results')?></a>');
            </script>
            <noscript>
            <a href="#editresulttab" id='editresulttabitem'><?=
                tl('resultseditor_element_edit_results')?></a>
            </noscript>
        </li>
        <li><script>
            document.write('<a href="javascript:switchTab(' +
                "'querymaptab', ['editresulttab','knowledgetab']);" +'" ' +
                "id='querymaptabitem' " +
                'class="<?=$data['query_map_active'] ?>"><?=
                tl('resultseditor_element_query_map')?></a>');
            </script>
            <noscript>
            <a href="#querymaptab" id='querymaptabitem'><?=
                tl('resultseditor_element_query_map')?></a>
            </noscript>
        </li>
        <li><script>
            document.write('<a href="javascript:switchTab(' +
                "'knowledgetab', ['querymaptab', 'editresulttab']);" +'" ' +
                "id='knowledgetabitem' " +
                'class="<?=$data['knowledge_wiki_active'] ?>"><?=
                tl('resultseditor_element_knowledge_wiki')?></a>');
            </script>
            <noscript>
            <a href="#knowledgetab" id='knowledgetabitem'><?=
                tl('resultseditor_element_knowledge_wiki')?></a>
            </noscript>
        </li>
        </ul>
        <div class='tab-menu-content'>
        <div id="editresulttab">
        <h2><?= tl('resultseditor_element_edit_result') ?>
        <?=$this->view->helper("helpbutton")->render(
        "Edit Search Results", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="urlUpdateForm" method="post" >
        <div  class="top-margin">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" /><?php
        if (!empty($data['ID'])) { ?>
            <input type="hidden" name="ID" value="<?=
                $data['ID'] ?>" /><?php
        }?>
        <b><label for="urlfield"><?=
            tl('resultseditor_element_page_url')?></label></b>
        <input type="url" id="urlfield"
            name="URL"  class="extra-wide-field <?=
            ($edit_url_mode) ? 'gray' : "" ?>" value='<?=$data["URL"] ?>'
            <?= ($edit_url_mode) ? 'readonly="readonly" ' : "" ?>
            />
        <?php
        if (!$edit_url_mode) { ?>
            <button class="button-box" name='arg' value='loadurl'
                type="submit" ><?= tl('resultseditor_element_load_page')
            ?></button><?php
        } ?>
        </div><?php
        if ($edit_url_mode) { ?>
            <div class="top-margin"><label for="url-actions"><b><?=
                tl('resultseditor_element_url_action') ?></b></label><?php
                $this->view->helper("options")->render("url-actions",
                "URL_ACTION", $data['URL_ACTIONS'],
                $data['URL_ACTION'], "switchUrlAction();"); ?></div>
            <div  class="top-margin" id='title-div'>
            <b><label for="titlefield"><?=
                tl('resultseditor_element_page_title')?></label></b>
            <input type="text" id="titlefield"
                name="TITLE"  class="extra-wide-field" value='<?=$data["TITLE"]
                ?>' />
            </div>
            <div class="top-margin" id='description-div'><label
                for="descriptionfield"><b><?=
                tl('resultseditor_element_description') ?></b></label></div>
            <textarea class="tall-text-area" id="descriptionfield"
                name="DESCRIPTION" ><?=$data['DESCRIPTION'] ?></textarea>
            <div class="center slight-pad"><button class="button-box"
                formaction="<?=$admin_url ?>a=resultsEditor&amp;<?=
                    C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
                    ?>" ><?=
                    tl('resultseditor_element_back')
                ?></button> &nbsp;&nbsp; <button class="button-box"
                type="submit" name='arg' value='saveurl'><?=
                tl('resultseditor_element_save') ?></button></div><?php
        }?>
        </form>
        </div>
        <div id="querymaptab">
        <h2><?= tl('resultseditor_element_query_map')?>
        <?=$this->view->helper("helpbutton")->render(
            "Query Result Mappings", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="knowledgeForm" method="post" >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="editquerymap" />
        <input type="hidden" name="posted" value="posted" />
        <div class="top-margin">
        <b><label for="querymapfield"><?=
            tl('resultseditor_element_query')?></label></b>
        <input type="text" id="querymapfield"
            name="MAP_QUERY"  class="extra-wide-field <?=
            ($edit_query_map_mode) ? 'gray' : "" ?>" value='<?=
            (empty($data["MAP_QUERY"])) ? "" : $data["MAP_QUERY"];?>'
            <?= ($edit_query_map_mode) ? 'readonly="readonly" ' : "" ?>/><?php
        if (!$edit_query_map_mode) { ?>
            <button class="button-box" name='arg' value='loadquerymap'
                type="submit" ><?= tl('resultseditor_element_load_map')
            ?></button><?php
        } ?>
        </div><?php
        if ($edit_query_map_mode) { ?>
            <div class="top-margin"><label for="querymap-urls"><b><?=
                tl('resultseditor_element_sites_querymap_urls')
                ?></b></label></div>
            <textarea class="tall-text-area" id="querymap-urls"
                name="MAP_URLS"  ><?= $data['MAP_URLS']
            ?></textarea>
            <div class="center slight-pad"><button class="button-box"
                formaction="<?=$admin_url ?>a=resultsEditor&amp;<?=
                    C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
                    ?>&amp;MODE=loadquerymap" ><?=
                    tl('resultseditor_element_back')
                ?></button> &nbsp;&nbsp; <button class="button-box"
                name='arg' value='savequerymap'
                type="submit"><?= tl('resultseditor_element_save')
                ?></button></div><?php
        }?>
        </form>
        </div>
        <div id="knowledgetab">
        <h2><?= tl('resultseditor_element_knowledge_wiki')?>
        <?=$this->view->helper("helpbutton")->render(
            "Knowledge Wiki Results", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="knowledgeForm" method="post" >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="editkwiki" />
        <input type="hidden" name="posted" value="posted" />
        <div class="top-margin">
        <b><label for="queryfield"><?=
            tl('resultseditor_element_query')?></label></b>
        <input type="text" id="queryfield"
            name="QUERY"  class="extra-wide-field" value='<?=
            (empty($data["QUERY"])) ? "" : $data["QUERY"];?>' /><?php
        if (!$edit_kwiki_mode) { ?>
            <button class="button-box" name='arg' value='loadkwiki'
                type="submit" ><?= tl('resultseditor_element_load_page')
            ?></button><?php
        } ?>
        </div><?php
        if ($edit_kwiki_mode) { ?>
            <div class="top-margin"><label for="kwiki-page"><b><?=
                tl('resultseditor_element_sites_kwiki_page')
                ?></b></label></div>
            <textarea class="tall-text-area" id="kwiki-page"
                name="KWIKI_PAGE" data-buttons='all,!wikibtn-slide,
                !wikibtn-search' ><?=
                $data['KWIKI_PAGE']
            ?></textarea>
            <div class="center slight-pad"><button class="button-box"
                formaction="<?=$admin_url ?>a=resultsEditor&amp;<?=
                    C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
                    ?>&amp;MODE=loadkwiki" ><?=
                    tl('resultseditor_element_back')
                ?></button> &nbsp;&nbsp; <button class="button-box"
                name='arg' value='savekwiki'
                type="submit"><?= tl('resultseditor_element_save')
                ?></button></div><?php
        }?>
        </form>
        </div>
        </div>
        </div>
        <script>
        function switchTab(newtab, oldtabs)
        {
            setDisplay(newtab, true);
            ntab = elt(newtab + "item");
            if (ntab) {
                ntab.className = 'active';
            }
            for (oldtab of oldtabs) {
                setDisplay(oldtab, false);
                otab = elt(oldtab + "item");
                if (otab) {
                    otab.className = '';
                }
            }
        }
        function switchUrlAction()
        {
            var url_action = elt("url-actions");
            if (!url_action) {
                return;
            }
            url_action = url_action.options[url_action.selectedIndex].value;
            if (url_action == -1 || url_action == <?=
                C\SEARCH_FILTER_GROUP_ITEM ?>) {
                setDisplay('title-div', false);
                setDisplay('description-div', false);
                setDisplay('descriptionfield', false);
            } else {
                setDisplay('title-div', true);
                setDisplay('description-div', true);
                setDisplay('descriptionfield', true);
            }
        }
        </script>
    <?php
    }
}
