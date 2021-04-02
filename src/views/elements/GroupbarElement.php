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
/**
 * Element used to draw the navigation bar on group feed and wiki pages.
 *
 * @author Chris Pollett
 */
class GroupbarElement extends Element
{
    /**
     * Used to draw the navigation bar on the group feed and wiki page portion
     * of the yioop website
     *
     * @param array $data contains antiCSRF token, as well as data on
     *     used to initialize title for group or wiki page
     */
    public function render($data)
    {
        if (isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] == 'presentation' &&
            $data['MODE'] == 'read' ) {
            return;
        }
        $logo = C\LOGO_MEDIUM;
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }
        $logged_in = !empty($data["ADMIN"]);
        $is_wiki = isset($data['ELEMENT']) && $data['ELEMENT'] == 'wiki';
        $token_string = ($logged_in) ? C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]
            : "";
        $alt_base_query = B\feedsUrl("",
            "", true, $data['CONTROLLER']) . $token_string;
        $base_query = ($is_wiki) ? htmlentities(B\wikiUrl("", true,
            $data['CONTROLLER'], $data["GROUP"]["GROUP_ID"])) : $alt_base_query;
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        ?><div class="none" >[<a href="#center-container"><?=
        tl('groupbar_element_skip_nav')
        ?>]</a></div><div id='nav-bar' class="nav-bar">
            <div class='inner-bar'><?php
            $this->renderSettingsToggle($logged_in); ?>
        <h1><a href="<?=C\SHORT_BASE_URL ?><?php
            if ($logged_in) {
                e("?$token_string");
            }
            ?>"><img src="<?= C\SHORT_BASE_URL . $logo ?>" alt="<?=
            $this->view->logo_alt_text ?>" /></a><span> -
            <?php
        if (!empty($data['ACTIVITY_METHOD']) &&
            $data['ACTIVITY_METHOD'] == 'wiki') {
            $wiki_page_list_url = rtrim(htmlentities(B\wikiUrl(
                "pages", true, $data['CONTROLLER'],
                $data["GROUP"]["GROUP_ID"])) . $token_string, "?"); ?>
            <span>
                <a href="<?= $alt_base_query . '&amp;v=grouped'
                ?>" ><img alt="<?=tl('groupbar_element_groups')?>"
                src="<?=C\SHORT_BASE_URL ?>resources/grouped.png" /></a>
                <a href="<?= $wiki_page_list_url
                ?>" ><img alt="<?=tl('groupbar_element_page_list')?>"
                src="<?=C\SHORT_BASE_URL ?>resources/list.png" /></a>
            </span><?php
            $page_name = str_replace("_", " ", $data['PAGE_NAME']);
            $is_page_list = false;
            if ($data['PAGE_NAME'] != "Main") {
                if ($data['MODE'] == 'pages') {
                    $page_name = tl('groupbar_element_page_list');
                    $is_page_list = true;
                }
                $wiki_main_page_url = rtrim(htmlentities(B\wikiUrl(
                    "Main", true, $data['CONTROLLER'],
                    $data["GROUP"]["GROUP_ID"])) . $token_string, "?");?>
                <a href="<?=$wiki_main_page_url; ?>"><?=
                urldecode($data['GROUP']['GROUP_NAME']) ?></a>:<?php
            } else {?>
                <?=urldecode($data['GROUP']['GROUP_NAME']) ?>:<?php
            }
            if ($data['MODE'] == 'read' || $is_page_list) {
                e(urldecode($page_name));
            } else {
                $wiki_base_url = htmlentities(B\wikiUrl(
                    $data['PAGE_NAME'], true, $data['CONTROLLER'],
                    $data["GROUP"]["GROUP_ID"])) . $token_string;
                ?>
                <a href='<?=$wiki_base_url
                    ?>'  target='preview'><?=urldecode($page_name); ?></a>
                <?php
            }
        } else {?>
            <span>
            <a href="<?= $base_query. '&amp;v=grouped'
            ?>" ><img alt="<?=tl('groupbar_element_groups')?>"
            src="<?=C\SHORT_BASE_URL ?>resources/grouped.png" /></a>
            <a href="<?= $base_query. '&amp;v=ungrouped'
            ?>" ><img alt="<?=tl('groupbar_element_feeds')?>"
            src="<?=C\SHORT_BASE_URL ?>resources/list.png" /></a>
            </span><?php
            if (!empty($data['JUST_THREAD']) &&
                !empty($data['WIKI_PAGE_NAME'])) {
                    $group_name =
                        $data['PAGES'][0][L\CrawlConstants::SOURCE_NAME];
                    e(tl('groupbar_element_page_thread',
                        urldecode($data['WIKI_PAGE_NAME']),
                        urldecode($group_name)));
            } else if (!empty($data['JUST_GROUP_ID'])) {
                    e(urldecode($data['SUBTITLE']));
            } else if (!empty($data['JUST_USER_ID'])) {
                if (empty($data['PAGES'][0]["USER_NAME"])) {
                    e(tl("groupbar_element_no_path_info"));
                } else {
                    e(tl("groupbar_element_userfeed",
                        urldecode($data['PAGES'][0]["USER_NAME"])));
                }
            } else if (!empty($data['JUST_THREAD'])) {
                if (!empty($data['GROUP_NAME'])) {
                    e( "<a href='".htmlentities(
                        B\feedsUrl("group", $data["GROUP_ID"],
                        true, 'group')) . $token_string ."'>" .
                        urldecode($data['GROUP_NAME']) . "</a>:" .
                        urldecode($data['SUBTITLE']));
                } else {
                    e(urldecode($data['SUBTITLE']));
                }
            }
        }?></span>
        </h1>
        </div>
        </div>
        <?php
    }
}
