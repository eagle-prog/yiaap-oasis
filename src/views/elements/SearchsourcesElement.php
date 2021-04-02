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
 * This element renders the forms for managing search sources for news, etc.
 * Also, contains form for managing subsearches which appear in SearchView
 *
 * @author Chris Pollett
 */
class SearchsourcesElement extends Element
{
    /**
     * Renders search source, subsearch forms or renders the results of
     * testing a search source
     *
     * @param array $data available Search sources and subsearches or
     *      feed test results
     */
    public function render($data)
    {
        if ($data['SOURCE_FORM_TYPE'] == "testsource") {
            $this->renderFeedTestResults($data);
        } else {
            $this->renderFormsAndTables($data);
        }
    }
    /**
     * Renders the results of testing a search source
     *
     * @param array $data available Search sources and subsearches or
     *      feed test results
     */
    public function renderFeedTestResults($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $token_string = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
        $pre_base_url = $admin_url . $token_string;
        $base_url = $pre_base_url . "&amp;a=searchSources&amp;" .
            "arg=editsource&amp;ts={$data['ts']}";
        ?>
        <div class="current-activity">
            <div class='float-opposite'><a href='<?= $base_url ?>'><?=
                tl('searchsources_element_editsource_form') ?></a></div>
        <?= $data['FEED_TEST_RESULTS'] ?? "";?></div>
        <?php
    }
    /**
     * Renders search source and subsearch forms
     *
     * @param array $data available Search sources  and subsearches
     */
    public function renderFormsAndTables($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $token_string = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
        $pre_base_url = $admin_url . $token_string;
        $base_url = $pre_base_url . "&amp;a=searchSources";
        $localize_url = $pre_base_url . "&amp;a=manageLocales".
            "&amp;arg=editstrings&amp;selectlocale=".$data['LOCALE_TAG'].
            "&amp;previous_activity=searchSources";
        $scrape_feeds = [tl('searchsources_element_channelpath') => 0,
            tl('searchsources_element_item_regex') => 1,
            tl('searchsources_element_titlepath') => 2,
            tl('searchsources_element_descpath') => 3,
            tl('searchsources_element_linkpath') => 4,
            tl('searchsources_element_image_xpath') => 5,
            tl('searchsources_element_trending_stop_regex') => 6,
        ];
        $type_fields = [
            "rss" => [tl('searchsources_element_image_xpath') => 0,
                tl('searchsources_element_trending_stop_regex') => 1],
            "json" => $scrape_feeds,
            "html" => $scrape_feeds,
            "regex" => $scrape_feeds,
            "feed_podcast" => [tl('searchsource_element_alt_link_text') => 4,
                tl('searchsources_element_wiki_destination') => 5
            ],
            "scrape_podcast" => [
                tl('searchsources_element_aux_url_xpath') => 0,
                tl('searchsources_element_link_xpath_text') => 4,
                tl('searchsources_element_wiki_destination') => 5],
            "trending_value" => [
                tl('searchsources_element_trend_category_group') => 5,
                tl('searchsources_element_trending_regex') => 6,
            ],
        ];
        $num_sub_aux_fields = 6;
        $sub_aux_len = floor(C\MAX_URL_LEN/$num_sub_aux_fields);
        $paging_items = ['SUBstart_row', 'SUBend_row', 'SUBnum_show'];
        $paging1 = "";
        foreach ($paging_items as $item) {
            if (isset($data[strtoupper($item)])) {
                $paging1 .= "&amp;" . $item . "=" .
                    $data[strtoupper($item)];
            }
        }
        $paging2 = "";
        $paging_items = ['start_row', 'end_row', 'num_show'];
        foreach ($paging_items as $item) {
            if (isset($data[strtoupper($item)])) {
                $paging2 .= "&amp;" . $item . "=" .
                    $data[strtoupper($item)];
            }
        }
        $data['FORM_TYPE'] = "";
        ?>
        <div class="current-activity">
        <ul class='tab-menu-list'>
        <li><script>
            document.write('<a href="javascript:switchTab(' +
                "'mediasourcetab', 'subsearchestab'" + ');" ' +
                "id='mediasourcetabitem' " +
                'class="<?=$data['media_source_active'] ?>"><?=
                tl('searchsources_element_media_sources')?></a>');
            </script>
            <noscript>
            <a href="#mediasourcetab" id='mediasourcetabitem'><?=
                tl('searchsources_element_media_sources')?></a>
            </noscript>
        </li>
        <li><script>
            document.write('<a href="javascript:switchTab(' +
                "'subsearchestab', 'mediasourcetab'" + ');" ' +
                "id='subsearchestabitem' " +
                'class="<?=$data['subsearches_active'] ?>"><?=
                tl('searchsources_element_subsearches')?></a>');
            </script>
            <noscript>
            <a href="#subsearchestab" id='subsearchestabitem'><?=
                tl('searchsources_element_subsearches')?></a>
            </noscript>
        </li>
        </ul>
        <div class='tab-menu-content'>
        <div id="mediasourcetab"><?php
        if ($data['SOURCE_FORM_TYPE'] == 'editsource') {
            $this->view->helper("close")->render($base_url);
            $this->renderMediaSourceForm($data);
        } else {?>
            <div><b>[<a href="<?= $base_url . '&arg=cleardata'?>"
                onclick='javascript:return confirm("<?=
                tl('searchsources_element_confirm_delete') ?>");' ><?=
                tl('searchsources_element_clear_news_trending')
            ?></a>]</b></div><?php
            $data['SEARCH_ARG'] = 'sourcesearch';
            $data['TABLE_TITLE'] = tl('searchsources_element_media_sources');
            $data['NO_FLOAT_TABLE'] = false;
            $data['ACTIVITY'] = 'searchSources';
            $data['VIEW'] = $this->view;
            $data['NO_SEARCH'] = false;
            if (in_array($data['SOURCE_FORM_TYPE'], ['editsource', 'search'])) {
                if ($data['SOURCE_FORM_TYPE'] == 'search') {
                    $data['FORM_TYPE'] = 'search';
                }
                $data['DISABLE_ADD_TOGGLE'] = true;
            }
            $data['PAGING'] = $paging1;
            $num_columns = 4;
            $data['TOGGLE_ID'] = 'media-form-row';
            ?>
            <table class="admin-table search-sources-table">
            <tr><td class="no-border" colspan="<?=
                $num_columns ?>"><?php $this->view->helper(
                "pagingtable")->render($data);
                if ($data['SOURCE_FORM_TYPE'] != "editsource") { ?>
                    <div id='<?=$data['TOGGLE_ID']
                    ?>' class='admin-form-row'><?php
                    if ($data['SOURCE_FORM_TYPE'] == "search") {
                        $this->renderMediaSearchForm($data);
                    } else {
                        $this->renderMediaSourceForm($data);
                    }?>
                    </div><?php
                } ?></td>
            </tr>
            <tr><th><?= tl('searchsources_element_medianame') ?></th>
                <th colspan="3"><?= tl('searchsources_element_action')
                    ?></th></tr><?php
            foreach ($data['MEDIA_SOURCES'] as $source) {
                $encode_source = urlencode(urlencode($source['NAME']));
                $current_aux_fields = empty($type_fields[$source['TYPE']]) ?
                    $type_fields['rss']: $type_fields[$source['TYPE']];
                $aux_info_parts = explode("###", $source['AUX_INFO']);
                ?>
                <tr><?php
                if ($data['SOURCE_FORM_TYPE'] == 'editsource' &&
                    $data['CURRENT_SOURCE']['name'] == $source['NAME']) {
                    ?><td class='admin-edit-box'><?php
                    $this->renderMediaSourceForm($data); ?>
                    </td><?php
                } else { ?>
                    <td><?php
                    $is_feed = false;
                    $is_trending_value = ($source['TYPE'] == 'trending_value');
                    if (in_array($source['TYPE'], ["rss", "html", 'json',
                        'regex'])) {
                        $is_feed = true;
                        ?><a href="<?=
                            B\subsearchUrl('news', true) . $token_string
                            ?>&amp;q=media:<?=$source['CATEGORY'] ?>:<?=
                            $encode_source ?>"><?=$source['NAME'] ?></a><?php
                    } else { ?>
                        <b><?= $source['NAME'] ?></b>
                        <?php
                    }
                    ?><br />
                        <b><?=tl('searchsources_element_sourcetype'); ?></b>
                        <?= $data['SOURCE_TYPES'][$source['TYPE']] ?><br />
                        <b><?=tl('searchsources_element_locale_tag'); ?></b>
                        <?= $source['LANGUAGE'] ?><br />
                        <b><?=($is_feed || $is_trending_value) ?
                            tl('searchsources_element_category')
                            : tl('searchsources_element_expires'); ?></b>
                        <?php
                            if (in_array($source['TYPE'], ["feed_podcast",
                                "scrape_podcast"])) {
                                e($data['PODCAST_EXPIRES'][
                                    $source['CATEGORY']]);
                            } else {
                                e($source['CATEGORY']);
                            }
                        ?><br />
                        <b><?= tl('searchsources_element_url') ?></b>
                        <pre><?= htmlentities($source['SOURCE_URL'])
                        ?></pre><?php
                        foreach ($current_aux_fields as
                            $aux_name => $aux_index) {
                            ?><b><?=$aux_name ?></b><br />
                            <pre><?=
                            htmlentities($aux_info_parts[$aux_index] ?? "")
                            ?></pre><?php
                        } ?>
                    </td><?php
                } ?>
                <td><a href="<?=$base_url . "&amp;arg=testsource&amp;ts=".
                    $source['TIMESTAMP'] . $paging1 . $paging2 ?>"><?=
                    tl('searchsources_element_testmedia')
                ?></a></td>
                <td><a href="<?=$base_url."&amp;arg=editsource&amp;ts=".
                    $source['TIMESTAMP'] . $paging1 . $paging2 ?>"><?=
                    tl('searchsources_element_editmedia')
                ?></a></td>
                <td><a onclick='javascript:return confirm("<?=
                    tl('searchsources_element_delete_operation') ?>");' href="<?=
                    $base_url."&amp;arg=deletesource&amp;ts=".
                    $source['TIMESTAMP'] . $paging1 . $paging2 ?>"><?=
                    tl('searchsources_element_deletemedia')
                ?></a></td></tr>
                <?php
            } ?>
            </table><?php
        }?>
        </div>
        <div id="subsearchestab">
        <?php
        $data['TOGGLE_ID'] = "";
        $data['SUBFORM_TYPE'] = "";
        $data['SEARCH_ARG'] = 'subsearchsearch';
        $data['TABLE_TITLE'] = tl('searchsources_element_subsearches');
        $data['NO_FLOAT_TABLE'] = false;
        $data['ACTIVITY'] = 'searchSources';
        $data['VIEW'] = $this->view;
        $data['VAR_PREFIX'] = "SUB";
        $data['PAGING'] = $paging2;
        $data['DEFAULT_ARG'] = "showSubsearch";
        $num_columns = $_SERVER["MOBILE"] ? 5 : 8;
        $data['DISABLE_ADD_TOGGLE'] = false;
        if (in_array($data['SEARCH_FORM_TYPE'], ['editsubsearch', 'search'])) {
            if ($data['SEARCH_FORM_TYPE'] == 'search') {
                $data['SUBFORM_TYPE'] = 'search';
            }
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        if ($data['SEARCH_FORM_TYPE'] == 'editsubsearch') {
            $this->view->helper("close")->render($base_url .
                "&amp;arg=". $data['DEFAULT_ARG']);
            $this->renderSubsearchForm($data);
        } else { ?>
            <table class="admin-table">
            <tr><td class="no-border" colspan="<?=
                $num_columns ?>"><?php
                $this->view->helper("pagingtable")->render($data);
                if ($data['SEARCH_FORM_TYPE'] != "editsubsearch") { ?>
                    <div id='admin-form-row' class='admin-form-row'><?php
                    if ($data['SEARCH_FORM_TYPE'] == "search") {
                        $this->renderSubsearchSearchForm($data);
                    } else {
                        $this->renderSubsearchForm($data);
                    }?>
                    </div><?php
                } ?></td>
            </tr>
            <tr><th><?= tl('searchsources_element_dirname') ?></th>
                <th><?= tl('searchsources_element_source') ?></th>
                <?php
                if (!$_SERVER["MOBILE"]) { ?>
                    <th><?=tl('searchsources_element_localestring') ?></th>
                    <th><?= tl('searchsources_element_perpage') ?></th>
                    <th><?= tl('searchsources_element_query_and_format') ?></th>
                    <?php
                }
                ?>
                <th colspan="3"><?= tl('searchsources_element_actions')?></th>
            </tr>
            <?php
            foreach ($data['SUBSEARCHES'] as $search) {
                if(empty($data["SEARCH_LISTS"][
                    trim($search['INDEX_IDENTIFIER'])])) {
                    continue;
                }
                if (preg_match('/highlight:\w+/ui', $search['DEFAULT_QUERY'],
                    $priority_match)) {
                    $landing_priority =
                        $data['LANDING_PRIORITIES'][$priority_match[0]] ??
                        $data['LANDING_PRIORITIES']['highlight:false'];
                } else {
                    $landing_priority =
                        $data['LANDING_PRIORITIES']['highlight:false'];
                }
                $landing_priority =  "<b>" .
                    tl('searchsources_element_landing') . "</b> " .
                    $landing_priority;
                if (preg_match('/trending:(\w+):(\w+)\b/ui',
                    $search['DEFAULT_QUERY'], $trending_parts)) {
                    $search_identifier = $trending_parts[1] ?? "";
                    $search_name =
                        tl('searchsources_element_trending_category');
                    $search_per_page =
                        tl('searchsources_element_not_applicable');
                    $search_sort = str_replace("_", " ",
                        $trending_parts[2] ?? "");
                    $query_format = "<b>".
                        tl('searchsources_element_trend_sort') .
                        "</b> " . $search_sort. "<br />" . $landing_priority;
                } else {
                    $search_identifier = $search['INDEX_IDENTIFIER'];
                    $search_name =
                        $data["SEARCH_LISTS"][trim($search['INDEX_IDENTIFIER'])];
                    $search_per_page = $search['PER_PAGE'];
                    $query_format = "<b>".tl('searchsources_element_query') .
                        "</b> " . trim(preg_replace('/highlight:\w+(\b)/ui',
                        '$1', $search['DEFAULT_QUERY'])) . "<br />" .
                        $landing_priority;
                }
                ?>
                <tr><td><b><?=$search['FOLDER_NAME'] ?></b></td>
                <td><?= "<b>$search_name</b><br />".
                    $search_identifier ?></td><?php
                if (!$_SERVER["MOBILE"]) {
                    ?>
                    <td><?= $search['LOCALE_STRING'] ?></td>
                    <td><?= $search_per_page ?></td>
                    <td><?= $query_format ?></td><?php
                }?>
                <td><a href="<?=$base_url."&amp;arg=editsubsearch&amp;fn=".
                    $search['FOLDER_NAME'].$paging1.$paging2 ?>"><?=
                    tl('searchsources_element_editsource')
                ?></a></td>
                <td><?php
                if ($data['CAN_LOCALIZE']) { ?>
                    <a href='<?=$localize_url."&amp;filter=".
                        $search['LOCALE_STRING']
                        ?>' ><?=tl('searchsources_element_localize')?></a><?php
                } else { ?>
                    <span class="gray"><?= tl('searchsources_element_localize')
                    ?></span><?php
                }
                ?>
                </td>
                <td><a onclick='javascript:return confirm("<?=
                    tl('searchsources_element_delete_operation') ?>");'
                    href="<?=$base_url.'&amp;arg=deletesubsearch&amp;fn='.
                    $search['FOLDER_NAME'] . $paging1 . $paging2 ?>"><?=
                    tl('searchsources_element_deletesubsearch')
                ?></a></td>
            </tr><?php
            } ?>
            </table><?php
        }?>
        </div>
        </div>
        <script>
        <?php
        $channel_string = json_encode(
            html_entity_decode($data['CURRENT_SOURCE']['channel_path']));
        ?>
        function switchSourceType()
        {
            let stype = elt("source-type");
            channel_string = <?= $channel_string ?>;
            channel_inner = '<input type="text"' +
                'id="channel-path" name="channel_path" '+
                'value="' + channel_string + '" ' +
                'maxlength="<?= $sub_aux_len ?>" ' +
                'class="wide-field" />';
            aux_inner = '<textarea class="short-text-area" ' +
                'id="channel-path" name="channel_path">' +
                channel_string +'</textarea>';
            stype = stype.options[stype.selectedIndex].value;
            let source_form_ids = ["alt-link-text", "aux-url-xpath",
                "category-text", "channel-path", "channel-text",
                "description-path", "description-text", "expires-text",
                "image-xpath", "instruct", "instruct-regex",
                "item-text-regex", "item-path", "item-text", "item-text-label",
                "link-path", "link-text", "link-text-label",
                "link-xpath-text", "locale-text",
                "path-label", "source-category", "source-expires",
                "source-locale-tag", "source-thumbnail", "title-path",
                "title-text", "trend-text", "trend-text-label",
                "trend-stop-string", "trend-category-group", "trending-xpath",
                "wiki-page-text", "xpath-text"
            ];
            let on_ids;
            if (stype == "html" || stype == 'json' || stype == 'regex') {
                on_ids =  ["category-text", "channel-path", "channel-text",
                    "description-path", "description-text", "image-xpath",
                    "link-path", "link-text", "link-text-label", "locale-text",
                    "item-path", "path-label", "source-category",
                    "source-locale-tag", "title-text", "title-path",
                    "trend-stop-string", "trend-text", "trend-text-label",
                    "xpath-text"
                ];
                if (stype == 'regex') {
                    on_ids.push("instruct-regex");
                    on_ids.push("item-text-regex");
                    on_ids.push("item-text-label");
                } else {
                    on_ids.push("item-text");
                    on_ids.push("item-text-label");
                    on_ids.push("instruct");
                }
                elt('channel-aux').innerHTML = channel_inner;
                if (elt('source-category').value == "") {
                    elt('source-category').value = "news";
                }
            } else if (stype == "feed_podcast") {
                on_ids =  ["alt-link-text", "expires-text", "image-xpath",
                    "link-path", "locale-text", "link-text-label",
                    "source-expires", "source-locale-tag", "wiki-page-text"
                ];
                elt('source-category').value = "";
            }  else if (stype == "scrape_podcast") {
                on_ids =  ["aux-url-xpath", "channel-path", "expires-text",
                    "image-xpath", "link-path", "link-xpath-text",
                    "link-text-label", "locale-text", "path-label",
                    "source-expires", "source-locale-tag", "wiki-page-text"
                ];
                elt('channel-aux').innerHTML = aux_inner;
                elt('source-category').value = "";
            } else if (stype == "trending_value") {
                on_ids =  [ "category-text", "image-xpath",
                    "locale-text", "source-category",
                    "source-locale-tag", "trend-category-group",
                    "trending-xpath"
                ];
                if (elt('source-category').value == "news") {
                    elt('source-category').value = "";
                }
            } else {
                on_ids =  [ "category-text", "image-xpath", "instruct",
                    "locale-text", "source-category", "source-locale-tag",
                    "trend-stop-string", "trend-text", "trend-text-label",
                    "xpath-text"
                ];
                if (elt('source-category').value == "") {
                    elt('source-category').value = "news";
                    set_news = "news";
                }
            }
            for (const source_form_id of source_form_ids) {
                if (on_ids.includes(source_form_id)) {
                    setDisplay(source_form_id, true);
                } else {
                    setDisplay(source_form_id, false);
                }
            }
        }
        function switchIndexSource()
        {
            let isource = elt("index-source");
            isource = isource.options[isource.selectedIndex].value;
            if (isource == 'trending:') {
                setDisplay("trend-category-row", true, "table-row");
                setDisplay("trend-sort-row", true, "table-row");
                setDisplay("per-page-row", false);
                setDisplay("default-query-row", false);
            } else {
                setDisplay("trend-category-row", false);
                setDisplay("trend-sort-row", false);
                setDisplay("per-page-row", true, "table-row");
                setDisplay("default-query-row", true, "table-row");
            }
        }
        function switchTab(newtab, oldtab)
        {
            setDisplay(newtab, true);
            setDisplay(oldtab, false);
            ntab = elt(newtab + "item");
            if (ntab) {
                ntab.className = 'active';
            }
            otab = elt(oldtab + "item");
            if (otab) {
                otab.className = '';
            }
        }
        </script>
        </div><?php
    }
    /**
     * Draws the Media Source form used to add and edit media sources that
     * that are automatically downloaded by Yioop from news and podcast feeds
     *
     * @param array $data consists of values of media source fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderMediaSourceForm($data)
    {
        ?>
        <form id="add-source-form" method="post"><?php
        if ($data["SOURCE_FORM_TYPE"] == "editsource") { ?>
            <h2><?= tl('searchsources_element_edit_media_source')?></h2>
            <?php
        } else {
            ?>
            <h2><?= tl('searchsources_element_add_media_source')?>
            <?= $this->view->helper("helpbutton")->render(
                "Media Sources", $data[C\CSRF_TOKEN]) ?>
            </h2><?php
        }?>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="<?=
            $data['SOURCE_FORM_TYPE'] ?>" />
        <?php
        if ($data['SOURCE_FORM_TYPE'] == "editsource") {
            ?>
            <input type="hidden" name="ts" value="<?= $data['ts'] ?>" />
            <?php
        }
        ?>
        <table class="name-table">
        <tr><td><label for="source-type"><b><?=
            tl('searchsources_element_sourcetype')?></b></label></td><td>
            <?php $this->view->helper("options")->render("source-type",
            "type", $data['SOURCE_TYPES'],
                $data['CURRENT_SOURCE']['type']); ?></td></tr>
        <tr><td><label for="source-name"><b><?=
            tl('searchsources_element_sourcename')?></b></label></td><td>
            <input type="text" id="source-name" name="name"
                value="<?=$data['CURRENT_SOURCE']['name'] ?>"
                maxlength="<?= C\LONG_NAME_LEN ?>"
                class="wide-field" <?php
                if ($data["SOURCE_FORM_TYPE"] == "editsource") {
                    e("disabled='disabled'");
                } ?>/><?php
            if (!empty($data['MODIFY_ADD']['name'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label for="source-url"><b><?=
            tl('searchsources_element_url')?></b></label></td><td>
            <input type="url" id="source-url" name="source_url"
                value="<?=$data['CURRENT_SOURCE']['source_url'] ?>"
                maxlength="<?=C\MAX_URL_LEN ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['source_url'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label id="locale-text" for="source-locale-tag"><b><?=
            tl('searchsources_element_locale_tag')?></b></label></td><td>
            <?php $this->view->helper("options")->render("source-locale-tag",
                "language", $data['LANGUAGES'],
                 $data['CURRENT_SOURCE']['language']); ?><?php
             if (!empty($data['MODIFY_ADD']['language'])) {
                 ?><span class='red'>*</span><?php
             }
             ?></td></tr>
        <tr><td><label id="category-text" for="source-category"><b><?php
            e(tl('searchsources_element_category'));
            $aux_info_len = C\MAX_URL_LEN;
            $num_sub_aux_fields = 6;
            $sub_aux_len = floor(C\MAX_URL_LEN/$num_sub_aux_fields);
            ?></b></label></td><td>
            <input type="text" id="source-category" name="category"
                value="<?= (empty($data['CURRENT_SOURCE']['category'])) ?
                    "news" : $data['CURRENT_SOURCE']['category'] ?>"
                maxlength="<?= $aux_info_len ?>" class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['category'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td ><label id="expires-text" for="source-expires"><b><?php
            e(tl('searchsources_element_expires'));
            ?></b></label></td><td><?php
            $this->view->helper("options")->render("source-expires",
            "expires", $data['PODCAST_EXPIRES'],
             $data['CURRENT_SOURCE']['category']); ?></td></tr>
        <tr><td colspan="2" class="instruct"><span id='instruct'><?=
            tl('searchsources_element_feed_instruct')
            ?></span><span id='instruct-regex'><?=
            tl('searchsources_element_regex_instruct')
            ?></span></td></tr>
        <tr><td><label id="path-label" for="channel-path">
            <b><span id="aux-url-xpath"><?=
            tl('searchsources_element_aux_url_xpath');
            ?></span><span id="channel-text"><?=
            tl('searchsources_element_channelpath') ?></span></b></label>
            </td><td id='channel-aux'><input type="text"
                id="channel-path" name="channel_path"
                value="<?= $data['CURRENT_SOURCE']['channel_path'] ?>"
                maxlength="<?= $sub_aux_len ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['channel_path'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label id="item-text-label" for="item-path">
            <b id="item-text"><?=
            tl('searchsources_element_item_text') ?></b><b
            id="item-text-regex"><?= tl('searchsources_element_item_regex')
            ?></b></label></td><td>
            <input type="text" id="item-path" name="item_path"
                value="<?=$data['CURRENT_SOURCE']['item_path'] ?>"
                maxlength="<?= $sub_aux_len ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['item_path'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label  id="title-text" for="title-path"><b><?=
            tl('searchsources_element_titlepath')?></b></label></td><td>
            <input type="text" id="title-path" name="title_path"
                value="<?= $data['CURRENT_SOURCE']['title_path'] ?>"
                maxlength="<?= $sub_aux_len ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['title_path'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label id="description-text" for="description-path"><b><?=
            tl('searchsources_element_descpath')?></b></label></td><td>
            <input type="text" id="description-path" name="description_path"
                value="<?= $data['CURRENT_SOURCE']['description_path'] ?>"
                maxlength="<?= $sub_aux_len ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['description_path'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label id="link-text-label" for="link-path">
            <b><span id="link-text"><?=
            tl('searchsources_element_linkpath')?></span><span
            id='link-xpath-text'><?= tl('searchsources_element_link_xpath_text')
                ?></span><span id='alt-link-text'><?=
                tl('searchsource_element_alt_link_text')
                ?></span></b></label></td><td>
            <input type="text" id="link-path" name="link_path"
                value="<?= $data['CURRENT_SOURCE']['link_path'] ?>"
                maxlength="<?= $sub_aux_len ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['link_path'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label for="image-xpath"><b><span id="xpath-text"><?=
            tl('searchsources_element_image_xpath')?></span><span
            id="wiki-page-text"><?=tl('searchsources_element_wiki_destination');
            ?></span><span id="trend-category-group"><?=
            tl('searchsources_element_trend_category_group');
            ?></span></b></label></td><td>
            <input type="text" id="image-xpath" name="image_xpath"
                value="<?= $data['CURRENT_SOURCE']['image_xpath'] ?>"
                maxlength="<?=C\MAX_URL_LEN ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['image2wbmp_xpath'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td><label id="trend-text-label" for="trend-stop-string">
            <b><span id="trend-text"><?=
            tl('searchsources_element_trending_stop_regex')
            ?></span><span id="trending-xpath"><?=
                tl('searchsources_element_trending_regex')
                ?></span></b></label></td><td>
            <input type="text" id="trend-stop-string"
                name="trending_stop_regex"
                value="<?= $data['CURRENT_SOURCE']['trending_stop_regex'] ?>"
                maxlength="<?=C\MAX_URL_LEN ?>"
                class="wide-field" /><?php
            if (!empty($data['MODIFY_ADD']['trending_stop_regex'])) {
                ?><span class='red'>*</span><?php
            }
            ?></td></tr>
        <tr><td></td><td class="center"><button class="button-box" <?php
            if ($data['SOURCE_FORM_TYPE'] == 'editsource') {
                e("id='focus-button'");
            }?>
            type="submit"><?=tl('searchsources_element_save')
            ?></button></td></tr>
        </table>
        </form><?php
    }
    /**
     * Draws the Subsearch element used to configure subsearches that
     * are choosable on landing page and elsewhere
     *
     * @param array $data consists of values of search source fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSubsearchForm($data)
    {
        if ($data["SEARCH_FORM_TYPE"] == "editsubsearch") { ?>
            <h2 id="subsearch-head"><?=
            tl('searchsources_element_edit_subsearch') ?></h2>
            <?php
        } else {
            ?>
            <h2 id="subsearch-head"><?=
                tl('searchsources_element_add_subsearch')?>
            <?= $this->view->helper("helpbutton")->render(
                "Subsearches", $data[C\CSRF_TOKEN]) ?></h2>
            <?php
        }
        ?>
        <form id="admin-form" method="post" >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="<?= $data['SEARCH_FORM_TYPE']
            ?>" />
        <table class="name-table" >
        <tr><td><label for="subsearch-folder-name"><b><?=
            tl('searchsources_element_foldername') ?></b></label></td><td>
            <input type="text" id="subsearch-folder-name" name="folder_name"
                value="<?= $data['CURRENT_SUBSEARCH']['folder_name'] ?>"
                <?php
                if ($data['SEARCH_FORM_TYPE'] == 'editsubsearch') {
                    e("disabled='disabled'");
                }
                ?>
                maxlength="80" class="wide-field" /></td></tr>
        <tr><td><label for="index-source"><b><?=
            tl('searchsources_element_source')?></b></label>
            </td><td>
            <?php $this->view->helper("options")->render("index-source",
            "index_identifier", $data['SEARCH_LISTS'],
                $data['CURRENT_SUBSEARCH']['index_identifier']); ?></td></tr>
        <tr id="per-page-row"><td>
            <label for="per-page"><b><?=
            tl('searchsources_element_per_page') ?></b></label>
            </td><td><?php
            $this->view->helper("options")->render("per-page", "per_page",
                $data['PER_PAGE'], $data['CURRENT_SUBSEARCH']['per_page']); ?>
            </td></tr>
        <tr id='landing-highlight-row'>
            <td><label for='landing-highlight' ><b><?=
            tl('searchsources_element_landing_highlight')?></b></label>
            </td><td><?php
                $this->view->helper("options")->render("landing-highlight",
                "landing_highlight", $data['LANDING_PRIORITIES'],
                $data['CURRENT_SUBSEARCH']['landing_highlight']); ?>
            </td></tr>
        <tr id='default-query-row'>
            <td><label for="subsearch-default-query"><b><?=
            tl('searchsources_element_defaultquery') ?></b></label></td>
            <td>
            <input type="text" id="subsearch-default-query" name="default_query"
                value="<?= $data['CURRENT_SUBSEARCH']['default_query'] ?>"
                maxlength="80" class="wide-field" />
            </td></tr>

        <tr id='trend-category-row'>
            <td><label for='trend-category' ><b><?=
            tl('searchsources_element_trend_category')?></b></label>
            </td><td><?php
            $this->view->helper("options")->render("trend-category",
                "trend_category", $data['TREND_CATEGORIES'],
                $data['CURRENT_SUBSEARCH']['trend_category']); ?>
            </td></tr>
        <tr id='trend-sort-row'>
            <td><label for='trend-sort' ><b><?=
            tl('searchsources_element_trend_sort')?></b></label>
            </td><td><?php
            $this->view->helper("options")->render("trend-sort",
                "trend_sort", $data['TREND_SORTS'],
                $data['CURRENT_SUBSEARCH']['trend_sort']); ?>
            </td></tr>
        <tr><td></td><td class="center"><button class="button-box" <?php
            if ($data['SEARCH_FORM_TYPE'] == 'editsubsearch') {
                e("id='focus-button'");
            }?>
            type="submit"><?= tl('searchsources_element_save')
            ?></button></td></tr>
        </table>
        </form><?php
    }
    /**
     * Draws the search for media source forms
     *
     * @param array $data consists of values of search source fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderMediaSearchForm($data)
    {
        $controller = "admin";
        $activity = "searchSources";
        $view = $this->view;
        $title = tl('searchsources_element_search');
        $fields = [
            tl('searchsources_element_sourcename') => "name",
            tl('searchsources_element_sourcetype') =>
                ["type", $data['EQUAL_COMPARISON_TYPES']],
            tl('searchsources_element_locale_tag') => "language",
            tl('searchsources_element_category') => "category",
            tl('searchsources_element_url') => "source_url",
        ];
        $postfix = "media";
        $dropdowns = [
            "language" => $data['LANGUAGES'],
            "type" => $data['SOURCE_TYPES']
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $fields, $dropdowns,
            $postfix);
    }
    /**
     * Draws the search for media source forms
     *
     * @param array $data consists of values of search source fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSubsearchSearchForm($data)
    {
        $controller = "admin";
        $activity = "searchSources";
        $view = $this->view;
        $title = tl('searchsources_element_search');
        $fields = [
            tl('searchsources_element_foldername') => "folder_name",
            tl('searchsources_element_source') =>
                ["index_identifier", $data['EQUAL_COMPARISON_TYPES']],
            tl('searchsources_element_per_page') => ["per_page",
                $data['INEQUALITY_COMPARISON_TYPES']],
            tl('searchsources_element_defaultquery') => "default_query",
        ];
        $postfix = "subsearch";
        $dropdowns = [
            "per_page" => $data['PER_PAGE']
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $fields, $dropdowns,
            $postfix);
    }
}
