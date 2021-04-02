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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\models\Model;

/** For tl, getLocaleTag and Yioop constants */
require_once __DIR__.'/../../library/Utility.php';
/**
 * Element responsible for drawing wiki pages in group view
 * It is also responsible for rendering wiki history pages, and listings of
 * wiki pages available for a group
 *
 * @author Chris Pollett
 */
class WikiElement extends Element implements CrawlConstants
{
    /**
     * Draw a wiki page for group, or, depending on $data['MODE'] a listing
     * of all pages for a group, or the history of revisions of a given page
     * or the edit page form
     *
     * @param array $data fields contain data about the page being
     *      displayed or edited, or the list of pages being displayed.
     */
    public function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) && $data["CAN_EDIT"];
        $other_controller = "group";
        $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
            $data["GROUP"]["GROUP_ID"]));
        $csrf_token = "";
        if ($logged_in) {
            $csrf_token = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
            $base_query .= $csrf_token;
        }
        if (isset($data['page_type']) && $data['page_type']
            == 'presentation') {
            e('<div class="presentation-activity">');
            if (isset($data['QUERY_STATISTICS'])) {
                $this->view->layout_object->presentation = true;
            }
        } else {
            $page_border = "";
            if (isset($data["HEAD"]['page_border']) &&
                $data["HEAD"]['page_border'] &&
                $data['HEAD']['page_border'] != 'none') {
                $page_border = $data['HEAD']['page_border'];
            }
            e('<div class="small-margin-current-activity '.$page_border.'">');
        }
        if (isset($data['BACK_URL'])) {
            $this->view->helper("close")->render(C\SHORT_BASE_URL . "?" .
                $data['BACK_URL'] . "&amp;" . C\CSRF_TOKEN . "=" .
                $data[C\CSRF_TOKEN]);
        }
        $folder_prefix = $base_query . '&amp;arg=read&amp;page_name='.
            $data['PAGE_NAME'];
        if (!empty($data['MEDIA_NAME'])) {
            if (!empty($data["PAGE"]) && stristr($data["PAGE"], "iframe") !==
                false) { ?>
                <div class="float-opposite"><a href="javascript:<?= "";
                    ?>window.location=tag('iframe')[0].src;">&#8648;</a><?php
                ?></div><?php
            }
            ?><div class="top-margin"><?php
                e("<b>".tl('wiki_element_places')."</b>");
                if (!empty($data['PREV_LINK'])) {
                    e("<a id='prev-link' href='".
                        "{$data['PREV_LINK']}'>&lt;&lt;</a>&nbsp;");
                } else {
                    e("<a id='prev-link' class='none'" .
                        " href=''>&lt;&lt;</a>&nbsp;");
                }
                $name_parts = pathinfo($data['MEDIA_NAME']);
                $this->renderPath('media-path', $data, [$folder_prefix =>
                    $data['PAGE_NAME']], "", $name_parts['filename']);
                if (!empty($data['NEXT_LINK'])) {
                    e("&nbsp;<a id='next-link' href='" .
                        "{$data['NEXT_LINK']}'>&gt;&gt;</a>");
                } else {
                    e("&nbsp;<a id='next-link' class='none'" .
                        " href=''>&gt;&gt;</a>");
                }
                ?>
            </div>
            <?php
        }
        switch ($data["MODE"]) {
            case "edit":
                $this->renderEditPageForm($data);
                break;
            case "pages":
                $this->renderPages($data, $can_edit, $logged_in);
                break;
            case "history":
                $this->renderHistory($data);
                break;
            case "source":
                $this->renderSourcePage($data);
                break;
            case "resources":
                $this->renderResources($data);
                break;
            case "relationships":
                $this->renderRelationships($data);
                break;
            case "read":
                // no break
            case "show":
            default:
                $is_page_and_feedback = (!empty($data["HEAD"]['page_type']) &&
                    $data["HEAD"]['page_type'] == 'page_and_feedback');
                $is_api = ($data['VIEW'] == 'api');
                if (!$is_page_and_feedback || !$is_api) {
                    $this->renderReadPage($data, $can_edit, $logged_in);
                }
                if ($is_page_and_feedback) {
                    if ($is_api) {
                        $data['API'] = true;
                    } else {
                        ?><hr /><?php
                    }
                    $base_query = htmlentities(B\wikiUrl("", true,
                        $data['CONTROLLER'], $data["GROUP"]["GROUP_ID"]));
                    if ($logged_in) {
                        $csrf_token = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
                        $base_query .= $csrf_token;
                    }
                    $data['FRAGMENT'] = '#result-' . $data['DISCUSS_THREAD'];
                    $this->view->element('groupfeed')->renderUngroupedView(
                        $can_edit, $base_query, $data['PAGING_QUERY'], $data);
                    if (!$is_api) {
                        $this->view->element('groupfeed')->renderScripts($data);
                    }
                }
                break;
        }
        e('</div>');
    }
    /**
     * Used to draw a Wiki Page for reading. If the page does not exist
     * various create/login-to-create etc messages are displayed depending
     * of it the user is logged in. and has write permissions on the group
     *
     * @param array $data fields PAGE used for page contents
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whether current user is logged in or not
     */
    public function renderReadPage($data, $can_edit, $logged_in)
    {
        $group_id = (empty($data["GROUP"]["GROUP_ID"])) ? C\PUBLIC_GROUP_ID:
            $data["GROUP"]["GROUP_ID"];
        $stretch = ($_SERVER["MOBILE"]) ? 3.8 : 6;
        $word_wrap_len = $stretch * C\NAME_TRUNCATE_LEN;
        $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
            $data["GROUP"]["GROUP_ID"]));
        if ($logged_in) {
            $csrf_token = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
            $base_query .= $csrf_token;
        }
        if (isset($data["HEAD"]['page_type']) && $data["HEAD"]['page_type'] ==
            'media_list') {
            $this->renderResources($data, true, $logged_in);
        } elseif (!empty($data["PAGE"])) {
            $sub_path = (empty($data['SUB_PATH'])) ? "": $data['SUB_PATH'];
            $media_id = "";
            if (!empty($data['MEDIA_NAME'])) {
                $media_id = L\crawlHash($data['PAGE_ID'] . $data['MEDIA_NAME'] .
                    $sub_path);
            }
            ?><div><?= $this->dynamicSubstitutions($group_id, $data,
                $data["PAGE"]); ?></div><?php
            if (!empty($data['NEXT_LINK'])) {
                $num_resources = count($data['RESOURCES_INFO']['resources']);
                $url_prefix = html_entity_decode($data["URL_PREFIX"]);
                if ($logged_in) {
                    $csrf_token = C\CSRF_TOKEN . "=" .$data[C\CSRF_TOKEN];
                    if (C\REDIRECTS_ON) {
                        $url_prefix =  preg_replace("/\/-\//u",
                            "/$csrf_token/", $url_prefix);
                    } else {
                        $csrf_token = "&" . $csrf_token;
                        $url_prefix .= $csrf_token;
                    }
                } else {
                    $csrf_token = "";
                }
                ?>
                <script>
                media_elt = document.getElementById('<?=$media_id ?>');
                media_prefix = '<?=$url_prefix ?>';
                url_prefix = '<?=html_entity_decode($data["URL_PREFIX"]) ?>';
                next_resources = [<?php
                    $comma = "";
                    $prev_type = false;
                    for ($i = $data['NEXT_INDEX'] - 1;
                        $i < $num_resources; $i++) {
                        $resource = $data['RESOURCES_INFO']['resources'][$i];
                        if (!$resource['media_type'] || ($prev_type &&
                            $prev_type != $resource['media_type'])) {
                            break;
                        } else {
                            echo $comma . json_encode($resource['name']);
                        }
                        $prev_type = $resource['media_type'];
                        $comma = ",";
                    }
                    ?>];
                end_next_resource = false;
                <?php
                $url_connective =  "&n=";
                if ($i < $num_resources) { ?>
                    end_next_resource = url_prefix + '<?=$url_connective .
                        urlencode($resource["name"]) ?>';<?php
                }
                ?>
                current_resource_index = 1;
                if (media_elt) {
                    if (media_elt.tagName == 'AUDIO' ||
                        media_elt.tagName == 'VIDEO' ) {
                        if (localStorage) {
                            if (media_current_time = localStorage.getItem(
                                "current_time" + media_elt.id)) {
                                media_elt.currentTime = media_current_time;
                            }
                            setInterval(function () {
                                media_elt = document.getElementById('<?=
                                    $media_id ?>');
                                localStorage.setItem("current_time" +
                                    media_elt.id, media_elt.currentTime);
                                epsilon_time = 150;
                                if (media_elt.duration &&
                                    media_elt.duration < 600) {
                                    epsilon_time = Math.abs(0.05 *
                                        media_elt.duration);
                                }
                                if (media_elt.duration &&
                                     media_elt.duration -
                                     media_elt.currentTime < epsilon_time) {
                                     localStorage.removeItem(
                                         "current_time" + media_elt.id);
                                }

                            }, 5000);
                        }
                        media_elt.onended = function(evt) {
                            sources = media_elt.getElementsByTagName('source');
                            source = sources[0];
                            elt('prev-link').href= url_prefix +
                                '<?=$url_connective ?>' +
                                next_resources[current_resource_index - 1];
                            elt('prev-link').style.display = 'inline';
                            if (current_resource_index + 1 <
                                next_resources.length) {
                                elt('next-link').href= url_prefix +
                                    '<?=$url_connective ?>' +
                                    next_resources[current_resource_index + 1]
                            } else if (current_resource_index + 1 ==
                                next_resources.length && end_next_resource) {
                                elt('next-link').href = end_next_resource;
                            } else if (end_next_resource) {
                                window.location = end_next_resource;
                                return;
                            } else {
                                elt('next-link').style.display = 'none';
                            }
                            let old_src = source.getAttribute('src');
                            old_src = old_src.split("+").join("%20");
                            let old_resource =
                                next_resources[current_resource_index - 1];
                            source.setAttribute('src', old_src.replace(
                                encodeURIComponent(old_resource),
                                encodeURIComponent(
                                    next_resources[current_resource_index])));
                            if (next_resources[current_resource_index]) {
                                let next_resource_name =
                                    next_resources[current_resource_index
                                    ].replace(/\.\S{3,5}$/, "");
                                page_path_elt = elt('selected-media-path');
                                page_path_elt.innerHTML = next_resource_name;
                                page_path_elt = elt('selected2-media-path');
                                page_path_elt.innerHTML = next_resource_name;
                                current_resource_index++;
                                media_elt.load();
                                media_elt.play();
                            }
                        }
                    }
                }
                </script>
                <?php
            }
        } elseif (!empty($data["HEAD"]['page_alias'])) {
            $alias = $data["HEAD"]['page_alias'];
            $data['PAGE']["DESCRIPTION"] = tl('wiki_element_redirect_to').
                " <a href='$base_query&amp;".
                "page_name=$alias'>$alias</a>";
            ?>
            <?=$data['PAGE']["DESCRIPTION"] ?>
            <?php
        } elseif ($can_edit) {
            ?>
            <h2><?= tl("wiki_element_page_no_exist",
                wordwrap(urldecode($data["PAGE_NAME"]), $word_wrap_len ,
                "\n", true)) ?></h2>
            <p><?= tl("wiki_element_create_edit") ?></p>
            <p><?= tl("wiki_element_use_form") ?></p>
            <form id="editpageForm" method="get" action="<?=C\SHORT_BASE_URL
            ?>" >
            <input type="hidden" name="c" value="<?= $data['CONTROLLER']?>" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="text" name="page_name" class="narrow-field"
                value="<?=urldecode($data["PAGE_NAME"]) ?>" />
            <button class="button-box" type="submit"><?=
                tl('wiki_element_go') ?></button>
            </form>
            <?php
            e("<p><a href=\"" . htmlentities(B\wikiUrl('Syntax', true,
                $data['CONTROLLER'], C\PUBLIC_GROUP_ID)) .
                C\CSRF_TOKEN .'='.$data[C\CSRF_TOKEN] . '&amp;arg=read'.
                "\">". tl('wiki_element_syntax_summary') .
                "</a>.</p>");
        } elseif (!$logged_in) {
            e("<h2>".tl("wiki_element_page_no_exist",
                wordwrap(urldecode($data["PAGE_NAME"]), $word_wrap_len ,
                "\n", true)) . "</h2>");
            e("<p>".tl("wiki_element_signin_edit")."</p>");
        } else {
            e("<h2>".tl("wiki_element_page_no_exist",
                wordwrap(urldecode($data["PAGE_NAME"]), $word_wrap_len ,
                "\n", true)) .
                "</h2>");
        }
    }
    /**
     * Used to drawn the form that let's someone edit a wiki page
     *
     * @param array $data fields contain data about the page being
     *      edited. In particular, PAGE contains the raw page data
     */
    public function renderEditPageForm($data)
    {
        $simple_base_url = B\wikiUrl("", true,
            $data['CONTROLLER'], $data['GROUP']['GROUP_ID']) .
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN];
        $base_url = htmlentities($simple_base_url);
        $simple_current_url = B\wikiUrl($data['PAGE_NAME'], true,
           $data['CONTROLLER'], $data['GROUP']['GROUP_ID']) .
           C\CSRF_TOKEN . '=' . $data[C\CSRF_TOKEN] . "&arg=edit";
        if (!empty($data['SUB_PATH'])) {
            $simple_current_url .= "&sf=".urlencode($data['SUB_PATH']);
        }
        $current_url = htmlentities($simple_current_url);
        if (isset($data['OTHER_BACK_URL'])) {
            $append = $data['OTHER_BACK_URL'];
        }?>
        <form id="editpageForm" method="post"
            enctype="multipart/form-data"
            onsubmit="
                var caret_pos = elt('caret-pos');
                var scroll_top = elt('scroll-top');
                var wiki_page = elt('wiki-page');
                if (caret_pos && scroll_top && wiki_page) {
                    caret_pos.value =
                    (wiki_page.selectionStart) ?
                    wiki_page.selectionStart : 0;
                    scroll_top.value= (wiki_page.scrollTop) ?
                    wiki_page.scrollTop : 0;
                }" >
            <input type="hidden" name="c" value="<?=$data['CONTROLLER']
            ?>" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <?php
            if (isset($data['RESOURCE_NAME'])) { ?>
                <input type="hidden" name="n" value="<?=
                    urlencode($data['RESOURCE_NAME']) ?>" />
                <?php
            }
            if (isset($data['BACK_PARAMS'])) {
                foreach ($data["BACK_PARAMS"] as
                         $back_param_key => $back_param_value) {
                    e('<input type="hidden" '
                        . 'name="' . $back_param_key .
                        '" value="' .
                        $back_param_value
                        . '" />');
                }
            }
            ?>
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="hidden" name="page_name" value="<?=
                $data['PAGE_NAME'] ?>" />
            <input type="hidden" name="caret" id="caret-pos"/>
            <input type="hidden" name="scroll_top" id="scroll-top"/>
            <input type="hidden" id="p-settings" name="settings" value="<?=
                $data['settings'] ?>"/>
            <div class="top-margin">
                <b><?=tl('wiki_element_locale_name',
                    $data['CURRENT_LOCALE_TAG']) ?></b><br />
                <b><label for="wiki-page"><?php
                $human_page_name = preg_replace("/\_/", " ",
                    urldecode($data['PAGE_NAME']));
                e(tl('wiki_element_page', $human_page_name));
                if (isset($data['RESOURCE_NAME'])) {
                    e("&nbsp;&nbsp;&nbsp;&nbsp;" .
                        tl('wiki_element_resource_name',
                        $data['RESOURCE_NAME']));
                    ?></label></b><?php
                } else {
                    ?></label></b>
                    <span id="toggle-settings"
                    ><script>
                    document.write('[<a href="javascript:toggleSettings()"><?=
                    tl('wiki_element_toggle_page_settings') ?></a>]');
                    </script> [<a href="<?= htmlentities(
                    B\wikiUrl('Syntax', true, $data['CONTROLLER'],
                    C\PUBLIC_GROUP_ID)) . C\CSRF_TOKEN .'='.
                    $data[C\CSRF_TOKEN] . '&amp;arg=read' ?>"><?=
                    tl('wiki_element_syntax_summary')?></a>]</span><?php
                }
                ?>
            </div>
            <div id='page-settings'>
            <div class="top-margin">
            <label for="page-type"><b><?=tl('wiki_element_page_type')
            ?></b></label><?php
            $this->view->helper("options")->render("page-type","page_type",
                $data['page_types'], $data['current_page_type'], true);
            ?>
            </div>
            <div id='alias-type'>
            <div class="top-margin">
            <label for="page-alias"><b><?=tl('wiki_element_page_alias')
            ?></b></label><input type="text" id='page-alias'
                name="page_alias" value="<?= $data['page_alias']?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            <div id='non-alias-type'>
            <div class="top-margin">
            <label for="page-border"><b><?=tl('wiki_element_page_border')
            ?></b></label><?php
            $this->view->helper("options")->render("page-border","page_border",
                $data['page_borders'], $data['page_border']);
            ?>
            </div>
            <div class="top-margin">
            <label for="page-toc"><b><?=tl('wiki_element_table_of_contents')
            ?></b></label><input type="checkbox" name="toc" value="true"
                <?php
                    $checked = (isset($data['toc']) && $data['toc']) ?
                    'checked="checked"' : '';
                    e( $checked );
                ?> id='page-toc' />
            </div>
            <div class="top-margin">
            <label for="page-title"><b><?=tl('wiki_element_title')
            ?></b></label><input type="text" id='page-title'
                name="title" value="<?=$data['title'] ?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-author"><b><?=tl('wiki_element_meta_author')
            ?></b></label><input type="text" id='meta-author'
                name="author" value="<?= $data['author']?>"
                maxlength="<?= C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-robots"><b><?=tl('wiki_element_meta_robots')
            ?></b></label><input type="text" id='meta-robots'
                name="robots" value="<?= $data['robots'] ?>"
                maxlength="<?=C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-description"><b><?=
                tl('wiki_element_meta_description')
            ?></b></label>
            </div>
            <textarea id="meta-description" class="short-text-area"
                name="description" data-buttons='none'><?=$data['description']
            ?></textarea><?php
            if (!empty($_SESSION['USER_ID']) &&
                $_SESSION['USER_ID'] == C\ROOT_ID) { ?>
                <div class="top-margin">
                <label for="alt-path"><b><?=tl('wiki_element_alternative_path')
                ?></b></label><input type="text" id='alt-path'
                    placeholder="<?=tl('wiki_element_empty_use_default')
                    ?>" name="alternative_path" value="<?=
                    $data['alternative_path'] ?>"
                    maxlength="<?=C\LONG_NAME_LEN ?>" class="wide-field"/>
                </div>
                <?php
            }
            ?>
            <div class="top-margin">
            <label for="page-header"><b><?=tl('wiki_element_page_header')
            ?></b></label><input type="text" id='page-header'
                name="page_header" value="<?=$data['page_header']?>"
                maxlength="<?=C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="page-footer"><b><?=tl('wiki_element_page_footer')
            ?></b></label><input type="text" id='page-footer'
                name="page_footer" value="<?=$data['page_footer'] ?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            </div>
            <div id='page-container'>
            <?php
            $show_resources = true;
            if (substr($data['current_page_type'], 0, 1)
                != 't' || !is_numeric(substr($data['current_page_type'], 1))
                ) { ?>
                <?php
                if (empty($data['SPREADSHEET'])) {?>
                    <textarea id="wiki-page"
                        class="tall-text-area" name="page"
                        <?php
                        if ((!isset($data['page_type']) ||
                                $data['page_type'] != 'presentation')) {
                            $data_buttons = 'all,!wikibtn-slide';
                        } else {
                            $data_buttons = 'all';
                        }?>
                        data-buttons='<?=$data_buttons ?>' ><?=$data['PAGE']
                    ?></textarea><?php
                } else {
                    e("<div id='spreadsheet'>&nbsp;</div>");
                }
            } else {
                e($data['PAGE']);
                $show_resources = false;
            }
            if ($show_resources && !isset($data['RESOURCE_NAME'])) {
                ?>
                <div class="green center"><?php
                $this->view->helper("fileupload")->render(
                    'wiki-page', 'page_resource', 'wiki-page-resource',
                    min(L\metricToInt(ini_get('upload_max_filesize')),
                    L\metricToInt(ini_get('post_max_size'))), 'textarea',
                    null, true);
                e(tl('wiki_element_archive_info'));
                ?></div>
                <div class="top-margin">
                <label for="edit-reason"><b><?= tl('wiki_element_edit_reason')
                ?></b></label><input type="text" id='edit-reason'
                    name="edit_reason" value="" maxlength="<?=
                    C\SHORT_TITLE_LEN ?>"
                    class="wide-field"/>
                </div><?php
            }
            ?>
            </div>
            <div id="save-container" class="top-margin center"><?php
            if ($show_resources && isset($data['RESOURCE_NAME'])) { ?>
                <button class="button-box"
                    onclick="window.location='<?=$current_url;
                    ?>'; return false;" ><?=
                    tl('wiki_element_closebutton') ?></button><?php
            }
            ?>
            <button class="button-box" type="submit"><?=
                tl('wiki_element_savebutton') ?></button>
            </div>
        </form>
        <div class="top-margin" id="media-list-page">
        <h2><?= tl('wiki_element_media_list')?></h2>
        <p><?= tl('wiki_element_ml_description')?></p>
        </div>
        <?php
        if ($show_resources) {
        ?>
        <div id="page-resources">
        <h3><?= tl('wiki_element_page_resources')?></h3>
        <p><?= tl('wiki_element_resources_info') ?></p>
        <form id="resource-upload-form" method="post"
            enctype="multipart/form-data">
        <input type="hidden" name="c" value="<?= $data['CONTROLLER']
            ?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="edit" />
        <?php
            if (isset($data['BACK_PARAMS'])) {
                foreach ($data["BACK_PARAMS"] as
                         $back_param_key => $back_param_value) {
                    e('<input type="hidden" '
                        . 'name="' . $back_param_key .
                        '" value="' .
                        $back_param_value
                        . '" />');
                }
            }
        ?>
        <input type="hidden" name="group_id" value="<?=
            $data['GROUP']['GROUP_ID'] ?>" />
        <input type="hidden" name="page_name" value="<?=
            $data['PAGE_NAME'] ?>" />
        <input type="hidden" id="r-settings" name="settings" value="<?=
            $data['settings'] ?>" />
        <div class="center">
        <div id="current-media-page-resource" class="media-upload-box"
                >&nbsp;</div>
        <?php
        $this->view->helper("fileupload")->render(
            'current-media-page-resource', 'page_resource',
            'media-page-resource',
            min(L\metricToInt(ini_get('upload_max_filesize')),
            L\metricToInt(ini_get('post_max_size'))),
            'text', null, true);
        ?>
        </div>
        </form>
        </div>
        <?php
            $this->renderResources($data, false);
        ?>
        <script>
        function renameResource(old_name, id)
        {
            var name_elt = elt("resource-"+id);
            var new_name = "";
            if (name_elt) {
                new_name = name_elt.value;
            }
            if (!name_elt || !new_name) {
                doMessage('<h1 class=\"red\" ><?=
                    tl("wiki_element_rename_failed") ?></h1>');
                return;
            }
            var location = "<?=$simple_current_url?>" +
                "&new_resource_name=" + encodeURIComponent(new_name) +
                "&old_resource_name=" +
                    old_name.replace('&quot;','"');
            window.location = location;
        }
        function deleteConfirm()
        {
            return confirm("<?= tl('wiki_element_delete_operation'); ?>");
        }
        function clipCopy(resource_name)
        {
            var location = "<?=$simple_current_url?>" +
                "&clip_copy=" + resource_name.replace('&quot;','"');
            window.location = location;
        }
        </script>
        <?php
        }
    }
    /**
     * Draws a list of media resources associated with a wiki page
     *
     * @param array $data fields RESOURCES_INFO contains info on resources
     * @param bool $read_mode whether the readering should be for a media
     *      list in read mode or for use on the edit task of any wiki page
     * @param bool $logged_in whether the user is currently logged in or not
     */
    public function renderResources($data, $read_mode, $logged_in = true)
    {
        if (isset($data['RESOURCES_INFO']) && $data['RESOURCES_INFO']) {
            $is_static = ($data['CONTROLLER'] == 'static') ? true : false;
            $token_string = (empty($data[C\CSRF_TOKEN])) ? "" :
                C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN];
            $base_url = htmlentities(B\wikiUrl($data['PAGE_NAME'] , true,
                $data['CONTROLLER'], $data['GROUP']['GROUP_ID']));
            $url_prefix = $data['RESOURCES_INFO']['url_prefix'];
            if ($logged_in) {
                $base_url .= $token_string;
                if (C\REDIRECTS_ON) {
                    $url_prefix =  preg_replace("/\/-\//u",
                        "/$token_string/", $url_prefix);
                } else {
                    $url_prefix .= "&amp;". $token_string;
                }
            }
            $folder_prefix = ($is_static) ? $base_url : $base_url . "&amp;";
            $folder_prefix .= "page_id=". $data["PAGE_ID"];
            $url_is_folder_prefix = false;
            if ($read_mode) {
                $folder_prefix .= "&amp;arg=media";
                $url_prefix = $folder_prefix;
                $url_is_folder_prefix = true;
            } else {
                $folder_prefix = $base_url . "&amp;arg=edit";
                $base_url .= "&amp;settings=".$data['settings'];
            }
            if (C\REDIRECTS_ON) {
                $thumb_prefix =  preg_replace("/\/-\//u", "/$token_string/",
                    $data['RESOURCES_INFO']['thumb_prefix']);
                $athumb_prefix =  preg_replace("/\/-\//u", "/$token_string/",
                    $data['RESOURCES_INFO']['athumb_prefix']);
            } else {
                $thumb_prefix = $data['RESOURCES_INFO']['thumb_prefix'] .
                    "&amp;$token_string";
                $athumb_prefix = $data['RESOURCES_INFO']['athumb_prefix'] .
                    "&amp;$token_string";
            }
            $default_thumb = C\SHORT_BASE_URL .
                $data['RESOURCES_INFO']['default_thumb'];
            $default_editable_thumb = C\SHORT_BASE_URL .
                $data['RESOURCES_INFO']['default_editable_thumb'];
            $default_folder_thumb = C\SHORT_BASE_URL .
                $data['RESOURCES_INFO']['default_folder_thumb'];
            ?>
            <form>
            <div><b><?=tl('wiki_element_places')?></b><?php
            $sub_path = $this->renderPath('resource-path',
                $data, [$folder_prefix => ""]);
            $arg = ($data['CONTROLLER'] == 'static') ? 'read' : $data['MODE'];
            $a = ($data['CONTROLLER'] == 'static') ? 'showPage' : 'wiki';
            $page_name = ($data['CONTROLLER'] == 'static') ? 'p' : 'page_name';
            ?>
            <b><label for='resource-filter'><?=
            tl('wiki_element_resource_filter')?></label></b>
            <input type="hidden" name='<?=C\CSRF_TOKEN?>'
                value='<?=$data[C\CSRF_TOKEN]?>' />
            <input type="hidden" name='arg' value='<?=$arg ?>' />
            <input type="hidden" name='a' value='<?=$a ?>' />
            <input type="hidden" name='c' value='<?=$data['CONTROLLER'] ?>' />
            <input type="hidden" name='<?= $page_name?>'
                value='<?=$data['PAGE_NAME']?>' />
            <?php
            if (!empty($data['SUB_PATH'])) { ?>
                <input type="hidden" name='sf'
                    value='<?=$data['SUB_PATH']; ?>' />
                <?php
            }
            ?>
            <input type="text" class="narrow-field" name='resource_filter'
                id='resource-filter' value="<?=$data['RESOURCE_FILTER']?>" />
            <button type='submit' name='filter_resources'
                class="button-box"><?=tl('wiki_element_go') ?></button>
            </div></form>
            <?php
            if (!empty($data['SUB_PATH'])) {
                $folder_prefix = "$folder_prefix&amp;sf=".urlencode($sub_path);
            }
            if (!empty($data['RESOURCES_INFO']['default_folder_writable'])
                && isset($data['MODE']) && $data['MODE'] == 'edit') {
                if (isset($data['CLIP_IS_CURRENT_DIR']) &&
                    !$data['CLIP_IS_CURRENT_DIR']) {
                    $clip_folders = [];
                    if (!empty($data['CLIP_FOLDER'])) {
                        $clip_folders[] = $data['CLIP_FOLDER'];
                    }
                    $clip_folders[] = tl('wiki_element_current_folder');
                    ?>
                    <div><form><b><label for='clip-folder'><?=
                    tl('wiki_element_clip_folder')?></label></b>
                    <input type="hidden" name='<?=C\CSRF_TOKEN?>'
                        value='<?=$data[C\CSRF_TOKEN]?>' />
                    <input type="hidden" name='arg' value='edit' />
                    <input type="hidden" name='a' value='wiki' />
                    <input type="hidden" name='page_name'
                        value='<?=$data['PAGE_NAME']?>' />
                    <?php
                    if (!empty($data['SUB_PATH'])) { ?>
                        <input type="hidden" name='sf'
                            value='<?=$data['SUB_PATH']; ?>' />
                        <?php
                    }
                    if (count($clip_folders) > 1) {
                        $this->view->helper("options")->render("clip-folder",
                            "clip_folder", $clip_folders, 0, true);
                    } else {
                        ?>
                        <button type='submit' name='clip_folder' value='1'><?=
                        tl('wiki_element_current_folder') ?></button>
                        <?php
                    }
                } else {
                    ?>
                    <div><b><?= tl('wiki_element_clip_folder')?></b>
                    <?= $data['CLIP_FOLDER'] ?>
                    </div>
                    <?php
                }
                ?></form>
               <?php
            }
            if (count($data['RESOURCES_INFO']['resources']) > 0 || !$read_mode){
                ?><div class="wiki-resources"><table><thead><?php
                if (!$read_mode && $data['MODE'] != 'source') {
                    ?><tr><th colspan="2"></th><th><a href='<?=
                        $folder_prefix?>&amp;sort=name'><?=
                        tl('wiki_element_name')
                    ?></a></th><th class="resource-actions"
                    aria-label='<?=$data['resource_actions']['actions']?>'
                    ><form>
                    <input type="hidden" name='<?=C\CSRF_TOKEN?>'
                        value='<?=$data[C\CSRF_TOKEN]?>' />
                    <input type="hidden" name='arg' value='edit' />
                    <input type="hidden" name='a' value='wiki' />
                    <input type="hidden" name='page_name'
                        value='<?=$data['PAGE_NAME']?>' />
                    <?php
                    if (!empty($data['SUB_PATH'])) { ?>
                        <input type="hidden" name='sf'
                            value='<?=$data['SUB_PATH']; ?>' />
                        <?php
                    }
                    $this->view->helper("options")->render("resource-actions",
                        "resource_actions", $data['resource_actions'],
                        'actions', true, ["aria-label" =>
                        $data['resource_actions']['actions']]);
                    ?></form><?php
                    if (!$_SERVER["MOBILE"]) {
                        e("</th><th><a href='$folder_prefix&amp;sort=size'>".
                            tl('wiki_element_size').'</a>');
                        e("</th><th><a href='".
                            "$folder_prefix&amp;sort=modified'>".
                            tl('wiki_element_modified').'</a></th>');
                    } else {
                        e("</th>");
                    } ?>
                </tr></thead><tbody><?php
                }
                $seen_resources = []; /* these are video and audio which
                   appear in different version such as mp4 and mov
                   This array keeps track if have seen an alternative version
                   earlier while processing resource list.
                   */
                $i = 0;
                foreach ($data['RESOURCES_INFO']['resources'] as $resource) {
                    $name = $resource['name'];
                    $hash_id = L\crawlHash($data['PAGE_ID'] . urlencode($name) .
                        ($data['ORIGINAL_SUB_PATH'] ?? ""));
                    $hash2_id = L\crawlHash($data['PAGE_ID'] . $name .
                        ($data['ORIGINAL_SUB_PATH'] ?? ""));
                    if (!empty($data['RESOURCE_FILTER'])&&
                        mb_stripos($name, $data['RESOURCE_FILTER']) === false) {
                        continue;
                    }
                    $name_parts = pathinfo($name);
                    $written_name = $name;
                    $use_editable_thumb = false;
                    if (!empty($name_parts['extension'])) {
                        if (in_array($name_parts['extension'], ['txt',
                            'csv', 'tex', 'php', 'sql', 'html', 'java', 'py',
                            'pl', 'P', 'srt'])) {
                            $use_editable_thumb = true;
                        } elseif (in_array($name_parts['extension'], ['mov',
                            'mp4', 'm4v', 'webm', 'mkv', 'm2ts'])) {
                            $written_name = $name_parts['filename'] .
                                "[".tl('wiki_element_video')."]";
                        } elseif (in_array($name_parts['extension'], ['wav',
                            'mp3','aac', 'aif', 'aiff', 'oga', 'ogg', "m4a"])) {
                            $written_name = $name_parts['filename'] .
                                "[".tl('wiki_element_audio')."]";
                        } else if ($name_parts['extension'] == "vtt") {
                            if( $read_mode) {
                                continue;
                            }
                            $use_editable_thumb = true;
                        }
                    }
                    if ($read_mode && isset($seen_resources[$written_name])) {
                        continue;
                    }
                    $seen_resources[$written_name] = true;
                    $disabled = " disabled='disabled' ";
                    if ($data['CAN_EDIT'] && $resource['is_writable']) {
                        $disabled = "";
                    }
                    if (!$read_mode) {
                        $written_name = $name;
                    }
                    $thumb_connect = (C\REDIRECTS_ON)
                        ? "/" : "&amp;n=";
                    $name_connect = (C\REDIRECTS_ON &&
                        !$url_is_folder_prefix)
                        ? "/" : "&amp;n=";
                    $encode_name = urlencode($name);
                    $current_url = "$url_prefix$name_connect$encode_name";
                    $clear_url = "$folder_prefix&amp;clear=" .
                        $encode_name;
                    $current_thumb = "$thumb_prefix$thumb_connect" .
                        $encode_name;
                    $current_animated_thumb = "$athumb_prefix$thumb_connect" .
                        $encode_name;
                    if (!empty($data['SUB_PATH'])) {
                        if (strpos($current_url, "sf=") === false &&
                            $url_is_folder_prefix) {
                            $encode_sub_path = urlencode($data['SUB_PATH']);
                            $add_sub_path = "&amp;sf=$encode_sub_path";
                            $current_url .= $add_sub_path;
                            $clear_url .= $add_sub_path;
                        }
                        if (!$resource['has_thumb']) {
                            $current_thumb = $default_thumb;
                            if (!$read_mode &&!$disabled &&
                                $use_editable_thumb) {
                                $current_thumb = $default_editable_thumb;
                                $current_url = "$folder_prefix&amp;sf=".
                                    urlencode($data['SUB_PATH']) . "&amp;n=".
                                    $encode_name;
                            }
                        }
                        if (!empty($resource['is_dir'])) {
                            $current_url = "$folder_prefix&amp;sf=".
                                urlencode($data['SUB_PATH'])."/$name";
                            $current_thumb = $default_folder_thumb;
                        }
                    } else {
                        if (!$resource['has_thumb']) {
                            $current_thumb = $default_thumb;
                            if (!$read_mode &&!$disabled &&
                                $use_editable_thumb) {
                                $current_thumb = $default_editable_thumb;
                                $current_url = "$folder_prefix&amp;n=".
                                    urlencode($name);
                            }
                        }
                        if (!empty($resource['is_dir'])) {
                            $current_url = "$folder_prefix&amp;sf=".
                                urlencode($name);
                            $current_thumb = $default_folder_thumb;
                        }
                    }
                    e("<tr class='resource-list' >");
                    if (!empty($_SESSION['seen_media']) &&
                        in_array($hash_id, $_SESSION['seen_media'])) {
                        e("<td class='sidebar-color huge-font'><span " .
                            "id='$hash2_id' class='marked' onclick='" .
                            "clearResource(this.id, \"". $clear_url . "\")' ".
                            ">&diams;</span></td>");
                    } else {
                        e("<td></td>");
                    }
                    $animated_thumb_info = "";
                    if ($resource['has_animated_thumb']) {
                        $animated_thumb_info = "class='animated' " .
                            "data-src='$current_animated_thumb' ".
                            "onmouseover= 'let src = this.src; ".
                            "this.src = this.dataset.src; ".
                            "this.dataset.src =src' " .
                            "onmouseout= 'let src = this.src; ".
                            "this.src = this.dataset.src; ".
                            "this.dataset.src =src' ";
                    }
                    e("<td><a href='$current_url' >");
                    e("<img src='" . $current_thumb .
                        "'  alt='$written_name'  $animated_thumb_info />");
                    e("</a></td>");
                    if ($read_mode) {
                        e("<td><a href='$current_url'>".
                            "$written_name</a></td>");
                    } else {
                        e("<td><input type='text' id='resource-$i' ".
                            "aria-label='".tl('wiki_element_name')."' ".
                            "value='".str_replace("'", "&#39;", $name).
                            "' $disabled /></td>");
                        ?><td><?php if (!$disabled) {
                            ?><button onclick='javascript:renameResource("<?=
                            urlencode($name);?>",
                            <?= $i ?>)' ><?=
                            tl('wiki_element_rename') ?></button><?php
                        }
                        if (!$disabled && empty($resource['is_dir'])
                            && (!isset($data['page_type']) ||
                            $data['page_type'] != 'media_list')) { ?>
                            <script>
                            document.write(
                            "<button onclick='javascript:addToPage(" + '"<?=
                            urlencode($name);
                            ?>", "wiki-page"<?= (empty($data['SUB_PATH'])) ?
                            "" : ", \"{$data['SUB_PATH']}\""
                            ?>)' + "'><?=tl('wiki_element_add_to_page')
                            ?></button>");
                            </script><?php
                        }
                        $append = "";
                        if (isset($data['OTHER_BACK_URL'])) {
                            $append .= $data['OTHER_BACK_URL'];
                        }
                        if (!$disabled && !empty($resource['is_compressed'])) {
                            $extract_url = $base_url .
                                "&amp;arg=edit&amp;extract=". urlencode($name) .
                                    $append;
                            ?><button onclick='window.location="<?=
                            $extract_url ?>";'><?=tl('wiki_element_extract');
                            ?></button> <?php
                        }
                        if (isset($data['CLIP_IS_CURRENT_DIR']) &&
                            !$data['CLIP_IS_CURRENT_DIR'] &&
                            empty($resource['is_dir'])) {
                            ?>
                            <button onclick='javascript:clipCopy("<?=
                            urlencode($name);
                            ?>"<?= (empty($data['SUB_PATH'])) ?
                            "" : ", \"{$data['SUB_PATH']}\""
                            ?>)'><?=tl('wiki_element_clip_copy')
                            ?></button> <?php
                        }
                        if (!$disabled) {
                            $delete_url = $base_url .
                                "&amp;arg=edit&amp;delete=". urlencode($name) .
                                    $append;
                            if (!empty($data['SUB_PATH'])) {
                                $delete_url .= "&amp;sf=".
                                    urlencode($data['SUB_PATH']);
                            }?>
                            <a class="action-anchor-button"
                            onclick='javascript:return deleteConfirm();'
                            href='<?=$delete_url
                            ?>'><span role="img" aria-label="<?=
                            tl('wiki_element_delete');
                            ?>">🗑<span class="none"><?=
                            tl('wiki_element_delete');
                            ?></span></span></a></td><?php
                        } else {
                            e("</td>");
                        }
                        if (!$_SERVER["MOBILE"]) {
                            e("<td>" . L\intToMetric($resource['size']) .
                                "B</td>");
                            e("<td>" . date("r", $resource['modified']) .
                                "</td>");
                        }
                    }
                    e("</tr>");
                    $i++;
                }
                ?></tbody></table></div><?php
                if ($read_mode) {
                    $scroll_id_path = $data['PAGE_ID'];
                    $scroll_id_path .= (empty($data['SUB_PATH'])) ?
                        "" : base64_encode($data['SUB_PATH']);
                    ?>
                    <script>
                    function clearResource(id, url)
                    {
                        if (localStorage && localStorage.getItem(
                            "current_time" + id)) {
                            localStorage.removeItem("current_time" + id);
                        }
                        window.location = url;
                    }
                    function recordScrollTop()
                    {
                        var root_elt = document.documentElement;
                        localStorage.setItem("scroll_top<?=
                            $scroll_id_path ?>", root_elt.scrollTop);
                    }
                    function initializeResourceList()
                    {
                        var marked_elts = document.getElementsByClassName(
                            'marked');
                        for (var i = 0; i < marked_elts.length; i++) {
                            var cur_id = marked_elts.item(i).id;
                            if (localStorage && localStorage.getItem(
                                "current_time" + cur_id)) {
                                marked_elts.item(i).innerHTML = "&loz;";
                            }
                        }
                        var scroll_top;
                        if (localStorage) {
                            document.body.addEventListener("click",
                                recordScrollTop, true);
                            if (scroll_top = localStorage.getItem(
                                "scroll_top<?= $scroll_id_path ?>")) {
                                document.documentElement.scrollTop = scroll_top;
                            }
                        }
                    }
                    initializeResourceList();
                    </script>
                    <?php
                }
            }
        }
        if (empty($data['RESOURCES_INFO']['resources']) ||
            count($data['RESOURCES_INFO']['resources']) == 0) {
            ?>
            <div class='red'><?=tl('wiki_element_no_resources')?></div><?php
        }
    }
    /**
     * Used to render the dropdown that lists paths within media lists folders,
     * recent wiki pages, and groups a user has been to
     *
     * @param string $dropdown_id element id of select tag to be used for
     *      dropdown
     * @param array $data set up in controller and SocialComponent with
     *      data fields view and this element are supposed to render
     * @param array $options if nonempty, then this should be items, key-values
     *      in the form (url => label), to list first in dropdown
     * @param string $selected_url url which is selected by default in dropdown.
     * @param string $top_name name of root media list folder (defaults
     *      to something like "Root Folder" in the language of current locale)
     * @param string $render_type can be: "paths" if just listing folder path
     *      in wiki page resource folder, "just_groups_and_pages" if want a list
     *      of recent groups and wiki pages viewed, or "all" if want both
     * @param boolean $as_list whether to output the result as a dropdown
     *      or as an unordered list.
     */
    public function renderPath($dropdown_id, $data, $options,
        $selected_url = "", $top_name = "", $render_type = "paths",
        $as_list = false)
    {
        $folder_prefix = "";
        if (empty($options)) {
            $options = [];
        } else if ($render_type != "just_groups_and_pages") {
            $folder_prefix = key($options);
            $root_name = $options[$folder_prefix];
            if (empty($root_name)) {
                $root_name = tl('wiki_element_root_folder');
            }
            $options[$folder_prefix] = $root_name;
        }
        $path_parts = (empty($data['SUB_PATH'])) ?
            [] : array_filter(explode("/", $data['SUB_PATH']));
        $sub_path = "";
        $selected_set = ($selected_url) ? true : false;
        if ($render_type == "just_groups_and_pages" && empty($options)) {
            $options = array_merge([-1 => tl('wiki_element_recent_places')],
                $options);
        } else if (in_array($render_type, ['all', 'paths'])) {
            $num_parts = count($path_parts);
            $i = 1;
            foreach ($path_parts as $part) {
                $sub_path .= $part;
                if ($i == $num_parts) {
                    if ($top_name == "") {
                        $options = array_merge([-1 => $part], $options);
                    } else {
                        $options = array_merge([-1 => $top_name,
                           "&amp;sf=$sub_path" => $part], $options);
                    }
                    break;
                } else {
                    $options = array_merge(
                        ["&amp;sf=$sub_path" => $part], $options);
                }
                $i++;
                $sub_path .= '/';
            }
            if ($num_parts == 0) {
                if ($top_name == "" && !empty($part)) {
                    $options = array_merge([-1 => $part], $options);
                } else if (!empty($top_name)){
                    $options = array_merge([-1 => $top_name], $options);
                }
                $selected_set = true;
            }
            $options = array_merge([tl('wiki_element_paths') => ""],
                $options);
        }
        if (in_array($render_type, ['all', 'just_groups_and_pages'])) {
            if (!empty($data['RECENT_PAGES'])) {
                $token_string = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
                $add_options = [tl('wiki_element_recent_pages') => ""];
                $found_new = false;
                foreach ($data['RECENT_PAGES'] as $page_name => $url) {
                    $out_token = (strstr($url, C\CSRF_TOKEN) === false) ?
                        $token_string : "";
                    if (empty($options[$url . $out_token])) {
                        $add_options[$url . $out_token] = $page_name;
                        $found_new = true;
                    }
                }
                if ($found_new) {
                    $options = array_merge($options, $add_options);
                }
            }
            if (!empty($data['RECENT_GROUPS'])) {
                $token_string = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
                $add_options = [tl('wiki_element_recent_groups') => ""];
                $found_new = false;
                foreach ($data['RECENT_GROUPS'] as $group_name => $url) {
                    $out_token = (strstr($url, C\CSRF_TOKEN) === false) ?
                        $token_string : "";
                    if (!empty($out_token) && (strstr($url, "?") === false)) {
                        $url .= "?";
                    }
                    if (empty($options[$url . $out_token])) {
                        $add_options[$url . $out_token] =
                            $group_name ;
                        $found_new = true;
                    }
                }
                if ($found_new) {
                    $options = array_merge($options, $add_options);
                }
            }
        }
        if (count($options) <= 1) {
            $options[tl('wiki_element_login_for_recent')] = "";
        }
        $this->view->helper('options')->renderLinkDropDown($dropdown_id,
            $options, $selected_url, $folder_prefix, $as_list);
        return $sub_path;
    }
    /**
     * Used to draw a list of Wiki Pages for the current group. It also
     * draws a search form and can be used to create pages
     *
     * @param array $data fields for the current controller, CSRF_TOKEN
     *     etc needed to render the search for and paging queries
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whethe current user is logged in or not
     */
    public function renderPages($data, $can_edit, $logged_in)
    {
        $token_string = ($logged_in) ? C\CSRF_TOKEN ."=". $data[C\CSRF_TOKEN] :
            "";
        $group_id = $data["GROUP"]["GROUP_ID"];
        $controller = $data['CONTROLLER'];
        $create_query = htmlentities(B\wikiUrl($data["FILTER"], true,
            $controller, $group_id)) . $token_string . "&amp;arg=edit";
        $paging_query = htmlentities(B\wikiUrl("pages", true, $controller,
            $group_id)) . $token_string;
        ?><h2 class="page-list-header-footer"><?=
            tl("wiki_element_wiki_page_list", $data["GROUP"]["GROUP_NAME"])
        ?></h2><?php
        ?>
        <form id="editpageForm" method="get" class="page-list-header-footer">
        <input type="hidden" name="c" value="<?=$data['CONTROLLER'] ?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="pages" />
        <input type="hidden" name="group_id" value="<?=
            $data['GROUP']['GROUP_ID'] ?>" />
        <input type="search" name="filter" class="extra-wide-field"
            maxlength="<?= C\SHORT_TITLE_LEN ?>"
            placeholder="<?= tl("wiki_element_filter_or_create")
            ?>" value="<?= $data['FILTER'] ?>" />
        <button class="button-box" type="submit"><?=tl('wiki_element_go')
            ?></button>
        </form>
        <?php
        if ($data["FILTER"] != "") {
            ?><a href='<?= $create_query ?>'><?=tl("wiki_element_create_page",
                $data['FILTER']) ?></a><?php
        }
        ?>
        <div>&nbsp;</div>
        <?php
        if ($data['PAGES'] != []) {
            foreach ($data['PAGES'] as $page) {
                if ($page['TYPE'] == 'page_alias' && isset($page['ALIAS'])) {
                    $page["DESCRIPTION"] = tl('wiki_element_redirect_to').
                        " <a href='".htmlentities(B\wikiUrl($page['ALIAS'],
                        true, $controller, $group_id)) . $token_string .
                        "'>".  urldecode($page['ALIAS']) . "</a>";
                } else {
                    $page["DESCRIPTION"] = strip_tags($page["DESCRIPTION"]);
                }
                ?>
                <div class='group-result'>
                <a href="<?= htmlentities(B\wikiUrl($page['TITLE'],
                    true, $controller, $group_id)) . $token_string
                    ?>&amp;noredirect=true" <?php
                    if ($data["OPEN_IN_TABS"]) { ?>
                        target="_blank" rel="noopener"<?php
                    }?>><?=urldecode($page["SHOW_TITLE"]) ?></a><?php
                if ($can_edit) { ?>
                    [<a href="<?= htmlentities(B\wikiUrl($page['TITLE'],
                        true, $controller, $group_id)) . $token_string
                        ?>&amp;noredirect=true&amp;arg=edit" <?php
                        if ($data["OPEN_IN_TABS"]) { ?>
                            target="_blank" rel="noopener"<?php
                        }?>><?=tl('wiki_element_edit')?></a>]<?php
                }
                ?></br />
                <?= $page["DESCRIPTION"] ?>
                </div>
                <div>&nbsp;</div>
                <?php
            }?>
            <div class="page-list-header-footer"><?php
            $this->view->helper("pagination")->render(
                $paging_query, $data['LIMIT'], $data['RESULTS_PER_PAGE'],
                $data['TOTAL_ROWS']);?>
            </div><?php
        }
        if (empty($data['PAGES'])) {
            ?><div><?=tl('wiki_element_no_pages', "<b>".L\getLocaleTag().
            "</b>")?></div><?php
        }
    }
    /**
     * Used to draw the page which displays all wiki pages
     *    that link a wiki page with a particular relationship type
     *
     * @param array $data fields contain info about all such wiki pages
     */
    public function renderRelationships($data)
    {
        $logged_in = !empty($data["ADMIN"]);
        if ($logged_in) {
            $csrf_token = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
        }
        $page_name = $data['PAGE_NAME'];
        $page_id = $data['PAGE_ID'];
        $url_main = htmlentities(B\wikiUrl($page_name, true,
            $data['CONTROLLER'], $data['GROUP_ID'])) . $csrf_token;
        //display pages that link to the particular page
        ?><table><tr><td><?php
        if (!empty($data["PAGES_THAT_LINK_TO"])) {
            foreach ($data["PAGES_THAT_LINK_TO"] as $key => $value) {
                $var = $data["PAGES_THAT_LINK_TO"][$key]["PAGES_LINKING_TO"];
                $url = htmlentities(B\wikiUrl($var, true, $data['CONTROLLER'] ,
                    $data['GROUP_ID'])).$csrf_token."&amp;arg=relationships" .
                    "&amp;page_id=" .
                    $data["PAGES_THAT_LINK_TO"][$key]["PAGE_ID"] .
                    "&amp;reltype=" . $data["REL-TYPE"];?>
                <div class="center"><a href='<?=$url?>' ><?=$var?>
                    </a></div><?php
            }
        }
        ?></td><tr><?php
        $rel_top = (!empty($data["TOTAL_TO_PAGES"])) ? "rel-type-top" : "";
        ?>
        <tr><td class="<?=$rel_top ?> center">|</td></tr><?php
        //displaying the middle table - Current Page Name
        ?><tr><td><div class="rel-type-current"><b><a
            href='<?=$url_main ?>' ><?=$page_name?></a></b><br /><?php
            if (empty($data["RELATIONSHIPS"])) {
                e("<b class='small-font'>".tl('wiki_element_no_cur_rel').
                    "</b>");
            } else {
                foreach ($data["RELATIONSHIPS"] as $key => $value) {
                    $url = $url_main . "&amp;arg=relationships" .
                        "&amp;page_id=" . $page_id;
                    $relationship_type =
                        $data["RELATIONSHIPS"][$key]["RELATIONSHIP_TYPE"];
                    $url .= "&amp;reltype=" . $relationship_type;
                    if (!empty($data["REL-TYPE"]) &&
                        $relationship_type == $data["REL-TYPE"]) {
                        $relationship_type = "<b>$relationship_type</b>";
                    }
                    e("<a class='small-font' href='$url'>".
                        "$relationship_type</a><br />");
                }
            }
           ?></div></td></tr><?php
        $rel_bottom = (!empty($data["TOTAL_FROM_PAGES"])) ?
            "rel-type-bottom" : "";
        ?>
        <tr><td class="<?=$rel_bottom ?> center">|</td></tr><?php
        //display pages that link from the particular page
        ?><tr><td><?php
        if (!empty($data["PAGES_THAT_LINK_FROM"])) {
            foreach ($data["PAGES_THAT_LINK_FROM"] as $key => $value) {
                $var =
                    $data["PAGES_THAT_LINK_FROM"][$key]["PAGES_LINKING_FROM"];
                $url = htmlentities(B\wikiUrl($var, true,
                    $data['CONTROLLER'], $data['GROUP_ID'])).$csrf_token.
                    "&amp;arg=relationships"."&amp;page_id=".
                    $data["PAGES_THAT_LINK_FROM"][$key]["PAGE_ID"].
                    "&amp;reltype=".$data["REL-TYPE"];
                ?><div class="center"><a href='<?=$url?>'><?=$var
                ?></a></div><?php
            }
        }
        ?></td></tr></table><?php
    }
    /**
     * Used to draw the revision history page for a wiki document
     * Has a form that can be used to draw the diff of two revisions
     *
     * @param array $data fields contain info about revisions of a Wiki page
     */
    public function renderHistory($data)
    {
        $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
            $data["GROUP"]["GROUP_ID"]) .C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN]);
        $append = "";
        if (isset($data['OTHER_BACK_URL']) && $data['OTHER_BACK_URL'] != '') {
            $append = $data['OTHER_BACK_URL'];
        }
        $edit_or_source = ($data['CAN_EDIT']) ? 'edit' : 'source';
        if (count($data['HISTORY']) > 1) { ?>
            <div>
            <form id="differenceForm" method="get">
            <input type="hidden" name="c" value="<?=$data['CONTROLLER']
             ?>" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="history" />
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="hidden" name="page_id" value="<?=
                $data["page_id"] ?>" />
            <input type="hidden" name="diff" value="1" />
            <b><?=tl('wiki_element_difference') ?></b>
            <input type="text" id="diff-1" name="diff1"
                value="<?=$data['diff1'] ?>" /> -
            <input type="text" id="diff-2" name="diff2"
                value="<?= $data['diff2'] ?>" />
            <button class="button-box" type="submit"><?=
                tl('wiki_element_go') ?></button>
            </form>
            </div>
            <?php
        }
        ?>
        <div>&nbsp;</div>
        <?php
        $time = time();
        $feed_helper = $this->view->helper("feeds");
        $base_query .= "&amp;arg=history&amp;page_id=".$data["page_id"];
        $first = true;
        foreach ($data['HISTORY'] as $item) {
            ?>
            <div class='group-result'>
            <?php
            if (count($data['HISTORY']) > 1) { ?>
                (<a href="javascript:updateFirst('<?=$item['PUBDATE']
                    ?>');" ><?= tl("wiki_element_diff_first")
                    ?></a> | <a href="javascript:updateSecond('<?=
                    $item['PUBDATE']?>');" ><?= tl("wiki_element_diff_second")
                    ?></a>)
                <?php
            } else { ?>
                (<b><?= tl("wiki_element_diff_first")
                    ?></b> | <b><?= tl("wiki_element_diff_second")
                    ?></b>)
                <?php
            }
            e("<a href='$base_query&show={$item['PUBDATE']}'>" .
                date("c",$item["PUBDATE"])."</a>. <b>{$item['PUBDATE']}</b>. ");
            e(tl("wiki_element_edited_by", $item["USER_NAME"]));
            if (strlen($item["EDIT_REASON"]) > 0) {
                e("<i>{$item["EDIT_REASON"]}</i>. ");
            }
            e(tl("wiki_element_page_len", $item["PAGE_LEN"])." ");
            if ($data['CAN_EDIT']) {
                if ($first && $data['LIMIT'] == 0) {
                    e("[<b>".tl("wiki_element_revert")."</b>].");
                } else {
                    e("[<a href='$base_query&amp;revert=".$item['PUBDATE'].
                    "'>".tl("wiki_element_revert")."</a>].");
                }
            }
            $first = false;
            $next = $item['PUBDATE'];
            ?>
            </div>
            <div>&nbsp;</div>
            <?php
        }?>
        <div class="page-list-header-footer"><?php
        $this->view->helper("pagination")->render(
            $base_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?></div>
        <script>
        function updateFirst(val)
        {
            elt('diff-1').value=val;
        }
        function updateSecond(val)
        {
            elt('diff-2').value=val;
        }
        </script>
        <?php
    }
    /**
     * Used to drawn the form that let's someone see the source of a wiki page
     *
     * @param array $data fields contain data about the page being
     *      edited. In particular, PAGE contains the raw page data
     */
    public function renderSourcePage($data)
    {
        $simple_base_url = B\wikiUrl("", true,
            $data['CONTROLLER'], $data['GROUP']['GROUP_ID']) .
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN];
        $base_url = htmlentities($simple_base_url);
        $append = "";
        if (isset($data['OTHER_BACK_URL'])) {
            $append = $data['OTHER_BACK_URL'];
        }
        ?>
        <div class="float-opposite wiki-history-discuss" >
        [<a href="<?= $base_url . $append ?>&amp;<?=
            '&amp;arg=history&amp;page_id='.$data['PAGE_ID'] ?>"
        ><?= tl('wiki_element_history')?></a>]
        [<a href="<?=htmlentities(B\feedsUrl("thread",
            $data['DISCUSS_THREAD'], true, $data['CONTROLLER'])) .
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN] ?>" ><?=
            tl('wiki_element_discuss')?></a>]
        </div>
        <form id="editpageForm" onsubmit="
            var caret_pos = elt('caret-pos');
            var scroll_top = elt('scroll-top');
            var wiki_page = elt('wiki-page');
            if (caret_pos && scroll_top && wiki_page) {
                caret_pos.value =
                (wiki_page.selectionStart) ?
                wiki_page.selectionStart : 0;
                scroll_top.value= (wiki_page.scrollTop) ?
                wiki_page.scrollTop : 0;
            }" >
            <input type="hidden" name="c" value="<?=$data['CONTROLLER']
            ?>" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="source" />
            <?php
            if (isset($data['BACK_PARAMS'])) {
                foreach ($data["BACK_PARAMS"] as
                         $back_param_key => $back_param_value) {
                    e('<input type="hidden" '
                        . 'name="' . $back_param_key .
                        '" value="' .
                        $back_param_value
                        . '" />');
                }
            }
            ?>
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="hidden" name="page_name" value="<?=
                $data['PAGE_NAME'] ?>" />
            <input type="hidden" name="caret" id="caret-pos"/>
            <input type="hidden" name="scroll_top" id="scroll-top"/>
            <input type="hidden" id="p-settings" name="settings" value="<?=
                $data['settings'] ?>"/>
            <div class="top-margin">
                <b><?=tl('wiki_element_locale_name',
                    $data['CURRENT_LOCALE_TAG']) ?></b><br />
                <b><label for="wiki-page"><?php
                $human_page_name = preg_replace("/\_/", " ",
                    urldecode($data['PAGE_NAME']));
                e(tl('wiki_element_page', $human_page_name));
                ?></label></b> <span id="toggle-settings"
                ><script>
                document.write('[<a href="javascript:toggleSettings()"><?=
                tl('wiki_element_toggle_page_settings') ?></a>]');
                </script></span>
            </div>
            <div id='page-settings'>
            <div class="top-margin">
            <label for="page-type"><b><?=tl('wiki_element_page_type')
            ?></b></label><?php
            $this->view->helper("options")->render("page-type", "page_type",
                $data['page_types'], $data['current_page_type'], true,
                ['disabled' => 'disabled']);
            ?>
            </div>
            <div id='alias-type'>
            <div class="top-margin">
            <label for="page-alias"><b><?=tl('wiki_element_page_alias')
            ?></b></label><input type="text" id='page-alias' disabled="disabled"
                name="page_alias" value="<?= $data['page_alias']?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            <div id='non-alias-type'>
            <div class="top-margin">
            <label for="page-border"><b><?=tl('wiki_element_page_border')
            ?></b></label><?php
            $this->view->helper("options")->render("page-border","page_border",
                $data['page_borders'], $data['page_border'], false,
                ['disabled' => 'disabled']);
            ?>
            </div>
            <div class="top-margin">
            <label for="page-toc"><b><?=tl('wiki_element_table_of_contents')
            ?></b></label><input type="checkbox" name="toc" value="true"
                <?php
                    $checked = (isset($data['toc']) && $data['toc']) ?
                    'checked="checked"' : '';
                    e( $checked );
                ?> id='page-toc' disabled="disabled" />
            </div>
            <div class="top-margin">
            <label for="page-title"><b><?=tl('wiki_element_title')
            ?></b></label><input type="text" id='page-title'
                disabled="disabled"
                name="title" value="<?=$data['title'] ?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-author"><b><?=tl('wiki_element_meta_author')
            ?></b></label><input type="text" id='meta-author'
                name="author" value="<?= $data['author']?>"
                disabled="disabled"
                maxlength="<?= C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-robots"><b><?=tl('wiki_element_meta_robots')
            ?></b></label><input type="text" id='meta-robots'
                disabled="disabled"
                name="robots" value="<?= $data['robots'] ?>"
                maxlength="<?=C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-description"><b><?=
                tl('wiki_element_meta_description')
            ?></b></label>
            </div>
            <textarea id="meta-description" class="short-text-area"
                disabled="disabled"
                name="description" data-buttons='none'><?=$data['description']
            ?></textarea><?php
            if (!empty($_SESSION['USER_ID']) &&
                $_SESSION['USER_ID'] == C\ROOT_ID) { ?>
                <div class="top-margin">
                <label for="alt-path"><b><?=tl('wiki_element_alternative_path')
                ?></b></label><input type="text" id='alt-path'
                    placeholder="<?=tl('wiki_element_empty_use_default')
                    ?>" name="alternative_path" value="<?=
                    $data['alternative_path'] ?>"
                    disabled="disabled"
                    maxlength="<?=C\LONG_NAME_LEN ?>" class="wide-field"/>
                </div>
                <?php
            }
            ?>
            <div class="top-margin">
            <label for="page-header"><b><?=tl('wiki_element_page_header')
            ?></b></label><input type="text" id='page-header'
                name="page_header" value="<?=$data['page_header']?>"
                disabled="disabled"
                maxlength="<?=C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="page-footer"><b><?=tl('wiki_element_page_footer')
            ?></b></label><input type="text" id='page-footer'
                name="page_footer" value="<?=$data['page_footer'] ?>"
                disabled="disabled"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            </div>
            <div id='page-container'><textarea id="wiki-page"
                class="tall-text-area" name="page" data-buttons='none'
                disabled="disabled"><?= $data['PAGE']
                ?></textarea>
            </div>
        </form>
        <div class="top-margin" id="media-list-page">
        <h2><?= tl('wiki_element_media_list')?></h2>
        <p><?= tl('wiki_element_ml_description')?></p>
        </div>
        <div id="page-resources">
        <h3><?= tl('wiki_element_page_resources')?></h3>
        <p><?= tl('wiki_element_resources_info') ?></p>
        </div>
        <?php
            $this->renderResources($data, false);
        ?>
        <?php
    }
    /**
     * The controller used to display a wiki page might vary (could be
     * group or static). Links within a wiki page need to be updated
     * to reflect which controller is being used. This method does the
     * update.
     *
     * @param int $group_id id of wiki page the passed page belongs to
     * @param array $data fields etc which will be sent to the view
     * @param string $pre_page a wiki page where links,etc have not yet
     *      had dynamic substitutions applied
     * @return string page after subustitutions
     */
    public function dynamicSubstitutions($group_id, $data, $pre_page)
    {
        $csrf_token = "";
        $no_amp_csrf_token = "";
        $no_right_amp_csrf_token = "";
        if (!empty($data['ADMIN'])) {
            $no_amp_csrf_token = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
            $no_right_amp_csrf_token =
                "&amp;". $no_amp_csrf_token;
            $csrf_token = $no_right_amp_csrf_token . "&amp;";
        }
        if ($data['CONTROLLER'] == 'static') {
            $address = C\SHORT_BASE_URL . "p/";
            $pre_address = B\controllerUrl("group", true) .
                "{$csrf_token}a=wiki&amp;arg=read&amp;";
        } else {
            $pre_address = B\controllerUrl($data['CONTROLLER'], true) .
                "{$csrf_token}a=wiki&amp;arg=read&amp;";
            $address = $pre_address .
                "group_id=$group_id&amp;page_name=";
        }
        $pre_page = preg_replace('/@@(.*)@(.*)@@/',
            $pre_address . "group_name=$1&amp;page_name=$2", $pre_page);
        $pre_page = preg_replace('/\[{controller_and_page}\]/', $address,
            $pre_page);
        $pre_page = preg_replace('/\[{controller}\]/', $data['CONTROLLER'],
            $pre_page);
        $pre_page = preg_replace('/\/\[{token}\]\//',
            (empty($no_amp_csrf_token)) ? "/-/" : "/$no_amp_csrf_token/" ,
            $pre_page);
        $pre_page = preg_replace('/\[{token}\]/', $csrf_token,
            $pre_page);
        if (stripos($pre_page, "[{recent_places}]") !== false) {
            ob_start();
            $this->renderPath("", $data, [], "", "",
                "just_groups_and_pages");
            $recent_dropdown = ob_get_clean();
            $pre_page = preg_replace('/\[{recent_places}\]/', $recent_dropdown,
                $pre_page);
        }
        return $pre_page;
    }
}
