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
 * Element responsible for drawing the side menu with sign in/create account,
 * search source options, search settings, and tool info for search pages
 *
 * @author Chris Pollett
 */
class SearchmenuElement extends Element
{
    /**
     * Method responsible for drawing the side menu with more
     * search option, account, and tool info
     *
     * @param array $data contains fields needed to draw links on page
     */
    public function render($data)
    {
        $logged_in = !empty($data['ADMIN']);
        $token_string = ($logged_in && isset($data[C\CSRF_TOKEN])) ?
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] : "";
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $register_url = htmlentities(B\controllerUrl('register', true));
        $logo = C\LOGO_MEDIUM;
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }
        ?>
        <div id='menu-options-background'
            tabindex="0" onclick="toggleOptions()">
        </div>
        <nav id="menu-options" class="menu-options">
        <script>
            document.write('<div class="float-opposite medium-font ' +
            'slight-pad">[<a href="javascript:toggleOptions()" >' +
            '<span role="img" aria-label="<?=tl('searchmenu_element_close')
            ?>">X</span></a>]</div>');
        </script>
        <h2><a href="<?=C\SHORT_BASE_URL ?><?php
            if ($logged_in) {
                e("?$token_string");
            }
            ?>"><img src="<?= C\SHORT_BASE_URL . $logo ?>" alt="<?=
            $this->view->logo_alt_text ?>" /></a></h2><?php
            if ($logged_in) { ?>
                <div class="medium-font slight-pad reduce-top"><a
                    class="gray-link"
                    onclick="javascript:setDisplay('admin-menu-options', true);
                    setDisplay('menu-options', false);"
                    href="#admin-menu-options" >&Lt;<?=
                    tl('searchmenu_element_admin_menu'); ?></a></div><?php
            } ?>
        <h2 class="option-heading"><?php
            if (!$logged_in) {
                e(tl('searchmenu_element_welcome'));
            }
            ?></h2>
            <ul class='square-list'>
            <?php
            if (!$logged_in) {
                ?><li><a href="<?= B\controllerUrl('admin') ?>"><?=
                tl('searchmenu_element_signin') ?></a></li><?php
                if (in_array(C\REGISTRATION_TYPE, ['no_activation',
                    'email_registration', 'admin_activation'])) { ?>
                    <li><a href="<?=rtrim($register_url . "a=createAccount&amp;" .
                        $token_string, "?&amp;") ?>"><?=
                        tl('searchmenu_element_create_account')
                        ?></a></li>
                    <?php
                }
            }?>
            </ul>
        <?php if (C\SUBSEARCH_LINK) { ?>
        <h2 class="option-heading" ><?=
            tl('searchmenu_element_categories')?></h2>
            <ul class='square-list'><?php
            $selected_category = (empty($data["SUBSEARCH"])) ? "" :
                $data["SUBSEARCH"];
            foreach ($data["SUBSEARCHES"] as $search) {
                $source = B\subsearchUrl($search["FOLDER_NAME"]);
                $delim = (C\REDIRECTS_ON) ? "?" : "&amp;";
                if ($search["FOLDER_NAME"] == "") {
                    $source = C\SHORT_BASE_URL;
                    $delim = "?";
                }
                $query = "";
                if (isset($data[C\CSRF_TOKEN]) && $logged_in) {
                    $query .= $delim . C\CSRF_TOKEN .
                        "=" . $data[C\CSRF_TOKEN];
                    $delim = "&";
                }
                if (isset($data['QUERY']) &&
                    !isset($data['NO_QUERY'])) {
                    $query .= "{$delim}q={$data['QUERY']}";
                }
                $option_url = "$source$query";
                $bold_open = "";
                $bold_close = "";
                if ($selected_category == $search["FOLDER_NAME"]) {
                    $selected_category = $option_url;
                    $bold_open = "<b>";
                    $bold_close = "</b>";
                }
                ?>
                <li><?=$bold_open ?><a href="<?=$option_url ?>"><?=
                    $search['SUBSEARCH_NAME'] ?></a><?=$bold_close
                    ?></li><?php
            } ?>
        </ul><?php
        }?>
        <h2 class="option-heading" ><?=
            tl('searchmenu_element_searchsettings')?></h2>
            <form id='settings-form' method="post"><?php
            if (!empty($data['QUERY'])) { ?>
                <input type="hidden" name='q' value="<?=$data['QUERY'] ?>"/>
                <?php
            }
            if (!empty($data['SUBSEARCH'])) { ?>
                <input type="hidden" name='s' value="<?=$data['SUBSEARCH'] ?>"/>
                <?php
            }
            ?>
            <ul class='square-list'>
            <li><label for="time-period"><b><?=
                tl('searchmenu_element_time_label') ?></b></label> <?php
                $this->view->helper("options")->render(
                    "time-period", "timeperiod", $data['TIME_PERIODS'],
                    $data['TIME_PERIOD_SELECTED']); ?></li><?php
            if (C\SUBSEARCH_LINK && !empty($data["SUBSEARCH"])) {
                if ($data["SUBSEARCH"] == 'images') { ?>
                    <li><label for="image-size"><b><?=
                        tl('searchmenu_element_size_label')
                        ?></b></label><?php
                        $this->view->helper("options")->render(
                            "image-size", "imagesize", $data['IMAGE_SIZES'],
                            $data['IMAGE_SIZE_SELECTED']); ?></li><?php
                }
                if ($data["SUBSEARCH"] == 'videos') { ?>
                    <li><label for='min-video-duration'><b><?=
                        tl('searchmenu_element_minduration')
                        ?></b></label><?php
                        $min_durations = $data['VIDEO_DURATIONS'];
                        unset($min_durations[1000000]);
                        $max_durations = $data['VIDEO_DURATIONS'];
                        unset($max_durations[0]);
                        $this->view->helper("options")->render(
                            "min-video-duration", "minvideoduration",
                            $min_durations, $data['VIDEO_MIN_DURATION']);?></li>
                    <li><label for='max-video-duration'><b><?=
                        tl('searchmenu_element_maxduration')
                        ?></b></label><?php
                        $this->view->helper("options")->render(
                            "max-video-duration", "maxvideoduration",
                            $max_durations, $data['VIDEO_MAX_DURATION']);
                            ?></li><?php
                }
            }
            ?>
            <li><label for="locale"><b><?=
                tl('searchmenu_element_language_label') ?></b></label><?php
                $this->view->element("language")->render($data); ?></li>
            <li><label for="per-page"><b><?=
                tl('searchmenu_element_results_per_page') ?></b></label><?php
                $this->view->helper("options")->render(
                "per-page", "perpage", $data['PER_PAGE'],
                $data['PER_PAGE_SELECTED']); ?></li>
            <li><label for="open-in-tabs"><b><?=
                tl('searchmenu_element_open_in_tabs') ?></b></label>
                    <input type="checkbox" id="open-in-tabs"
                    name="open_in_tabs" value="true"
                    <?php  if (!empty($data['OPEN_IN_TABS'])) {
                        ?>checked='checked'<?php
                    } ?> /></li>
            <li><label for="safe-search"><b><?=
                tl('searchmenu_element_safe_search') ?></b></label>
                <input type="checkbox" id="safe-search" name="safe_search"
                    value="true" <?php  if (isset($data['SAFE_SEARCH']) &&
                        $data['SAFE_SEARCH'] == 'true') {
                        ?>checked='checked'<?php
                    } ?> /></li>
            <li class="center no-bullet"><button class="small-font"
                type="submit"><?=tl('searchmenu_element_save') ?></button></li>
            </ul>
            </form>
        <?php
        $tools = [];
        if (empty($token_string))  {
            $suggest_url = B\suggestUrl();
            $pages_url = B\wikiUrl('pages');
        } else {
            $suggest_url = B\suggestUrl(true) . $token_string;
            $pages_url = B\wikiUrl('pages', true) . $token_string;
        }
        $tools[$pages_url] = tl('searchmenu_element_wiki_pages');
        if (in_array(C\REGISTRATION_TYPE, ['no_activation',
            'email_registration', 'admin_activation'])) {
            $tools[$suggest_url] = tl('searchmenu_element_suggest');
        }
        if ($tools != []) { ?>
            <h2 id="tools" class="option-heading"><?php
                e(tl('searchmenu_element_tools'))?></h2>
            <ul class="square-list">
            <?php
            foreach ($tools as $tool_url => $tool_name) {
                ?><li><a href='<?=$tool_url;?>'><?=$tool_name; ?>
                </a></li><?php
            }
        }
        ?>
    </nav>
        <?php
    }
}
