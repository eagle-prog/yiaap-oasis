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
namespace seekquarry\yioop\views\layouts;

use seekquarry\yioop\configs as C;
use seekquarry\yioop as B;

/**
 * Layout used for the seek_quarry Website
 * including pages such as search landing page
 * and settings page
 *
 * @author Chris Pollett
 */
class WebLayout extends Layout
{
    /**
     * Responsible for drawing the header of the document containing
     * Yioop! title and including basic.js. It calls the renderView method of
     * the View that lives on the layout. If the QUERY_STATISTIC config setting
     * is set, it output statistics about each query run on the database.
     * Finally, it draws the footer of the document.
     *
     * @param array $data  an array of data set up by the controller to be
     * be used in drawing the WebLayout and its View.
     */
    public function render($data)
    {
        ?><!DOCTYPE html>
        <html lang="<?= $data['LOCALE_TAG']; ?>" dir="<?=
            $data['LOCALE_DIR']?>" data-redirect="<?=
            (C\REDIRECTS_ON) ? 'true' : 'false'?>" data-base="<?=
            C\SHORT_BASE_URL; ?>">
        <head>
        <title><?php
        if (isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['title'])) {
            e($this->view->head_objects[$data['page']]['title']);
        } else {
            e(tl('web_layout_title'));
        } ?></title><?php
        if (isset($this->view->head_objects['robots'])) {
            ?><meta name="ROBOTS" content="<?=
            $this->view->head_objects['robots'] ?>" /><?php
        }?>
        <meta name="description" content="<?php
        if (isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['description'])) {
            e($this->view->head_objects[$data['page']]['description']);
        } else {
            e(tl('web_layout_description'));
        } ?>" />
        <meta name="Author" content="<?=tl('web_layout_site_author') ?>" />
        <meta name="referrer" content="strict-origin-when-cross-origin" />
        <meta charset="utf-8" /><?php
        if ($_SERVER["MOBILE"]) {?>
            <meta name="viewport"
                content="width=device-width, initial-scale=1.0"><?php
        }
        $path_url = C\SHORT_BASE_URL;
        $aux_css = false;
        if (file_exists(C\APP_DIR . '/css/auxiliary.css')) {
            if (C\REDIRECTS_ON) {
                $aux_css = "{$path_url}wd/css/auxiliary.css";
            } else {
                $aux_css = "$path_url?c=resource&amp;a=get&amp;".
                    "f=css&amp;n=auxiliary.css";
            }
        }
        /* Remember to give complete paths to all link tag hrefs to
           avoid PRSSI attacks
           http://www.theregister.co.uk/2015/02/20/prssi_web_vuln/
         */ ?>
        <link rel="icon" href="<?= $path_url . C\FAVICON ?>" /><?php
        $search_css = $path_url . 'css/search.css';
        if (file_exists(C\APP_DIR . '/css/search.css')) {
            if (C\REDIRECTS_ON) {
                $search_css = "{$path_url}wd/css/search.css";
            } else {
                $search_css = "$path_url?c=resource&amp;a=get&amp;".
                    "f=css&amp;n=search.css";
            }
        } ?>
        <link rel="stylesheet" type="text/css" href="<?=$search_css ?>" /><?php
        if ($aux_css) { ?>
            <link rel="stylesheet" type="text/css"
                href="<?=$aux_css ?>" /><?php
        }
        if (C\nsdefined("SEARCHBAR_PATH") && C\SEARCHBAR_PATH != "") { ?>
            <link rel="search" type="application/opensearchdescription+xml"
                href="<?=C\SEARCHBAR_PATH ?>"
                title="Content search" /><?php
        }
        if (!empty($data['RSS_FEED_URL'])) {?>
            <link rel="alternate" type="application/rss+xml"
                href="<?=$data['RSS_FEED_URL'] ?>" /><?php
        }
        if (isset($data['INCLUDE_STYLES'])) {
            foreach ($data['INCLUDE_STYLES'] as $style_name) {
                e('<link rel="stylesheet" type="text/css"
                    href="'. $path_url . 'css/'.
                    $style_name.'.css" />'."\n");
            }
        } ?>
        <style>
        <?php
        $background_color = "#FFFFFF";
        if (C\nsdefined('BACKGROUND_COLOR')) {
            $background_color = !empty($data['BACKGROUND_COLOR']) ?
                $data['BACKGROUND_COLOR'] : C\BACKGROUND_COLOR; ?>
            body
            {
                background-color: <?=$background_color ?>;
            }
            <?php
        }
        if (C\nsdefined('BACKGROUND_IMAGE') && C\BACKGROUND_IMAGE) {
            $background_image = !empty($data['BACKGROUND_IMAGE']) ?
                $data['BACKGROUND_IMAGE'] : C\BACKGROUND_IMAGE; ?>
            body
            {
                background-image: url(<?=html_entity_decode(
                    $background_image) ?>);
                background-repeat: no-repeat;
                background-size: 12in;
            }
            body.mobile
            {
                background-size: 100%;
            }
            <?php
        }
        $foreground_color = "#FFFFFF";
        if (C\nsdefined('FOREGROUND_COLOR')) {
            $foreground_color = !empty($data['FOREGROUND_COLOR']) ?
                $data['FOREGROUND_COLOR'] : C\FOREGROUND_COLOR; ?>
            .frame,
            .icon-upload,
            .current-activity,
            .light-content,
            .small-margin-current-activity,
            .suggest-list li span.unselected
            {
                background-color: <?=$foreground_color ?>;
            }
            .foreground-color,
            .icon-upload
            {
                color: <?=$foreground_color ?>;
            }
            <?php
        }
        if (C\nsdefined('SIDEBAR_COLOR')) { ?>
            .cookie-consent,
            .menu-options
            {
                background-color: <?=!empty($data['SIDEBAR_COLOR']) ?
                    $data['SIDEBAR_COLOR'] : C\SIDEBAR_COLOR ?>;
            }
            .light-content,
            .mobile .light-content
            {
                border: 16px solid <?=!empty($data['SIDEBAR_COLOR']) ?
                    $data['SIDEBAR_COLOR'] : C\SIDEBAR_COLOR ?>;
            }
            .sidebar-color
            {
                color: <?=!empty($data['SIDEBAR_COLOR']) ?
                    $data['SIDEBAR_COLOR'] : C\SIDEBAR_COLOR ?>;
            }
            <?php
        }
        if (C\nsdefined('TOPBAR_COLOR')) {
            $top_color = (!empty($data['TOPBAR_COLOR'])) ?
                $data['TOPBAR_COLOR'] : C\TOPBAR_COLOR; ?>
            .display-ad p,
            p.start-ad {
                background-color: #DFD;
            }
            td.admin-edit-row-field,
            .top-color,
            .suggest-list,
            .suggest-list li,
            .suggest-list li span.selected,
            .search-box {
                background-color: <?=$top_color ?>;
            }
            .top-container,
            .top-container .inner-bar
            {
                background: <?=$top_color ?>;
            } <?php
        }
        if (!empty($data['NUM_HIGHLIGHTS'])) {
            $landing_height = ($data['NUM_HIGHLIGHTS'] >= 2) ? 0 : 2;
            $mobile_height = ($data['NUM_HIGHLIGHTS'] >= 2) ? 0 : 70;
            ?>
            .top-landing-spacer
            {
                height:<?=$landing_height; ?>in;
            }
            .mobile .top-landing-spacer
            {
                height:<?=$mobile_height; ?>px;
            }
            <?php
        } ?>
        </style><?php
        if (empty($_REQUEST['noscript'])) { ?>
            <noscript>
            <style>
            .noscript-hide
            {
                display: none;
            }
            .top-container
            {
                display: contents;
            }
            .center-container
            {
                margin-top: -65px;
            }
            .html-ltr .nav-container
            {
                height: 1000px;
                left: 0px;
                overflow-y: scroll;
                position:fixed;
                top: 0px;
            }
            .html-ltr .menu-options
            {
                left: 0px;
                overflow-y:unset;
                position:static;
            }
            .html-rtl .menu-options
            {
                position:static;
                right: 0px;
            }
            .html-ltr .logo-subsearch
            {
                left: 370px;
            }
            .html-ltr .body-container
            {
                margin-left: 300px;
            }
            .html-rtl .nav-container
            {
                overflow-y:scroll;
                position:fixed;
                right: 0px;
                top: 0px;
            }
            .html-rtl .body-container
            {
                margin-right: 300px;
            }
            .html-rtl .logo-subsearch
            {
                right: 370px;
            }
            #admin-menu-options
            {
                display: block;
            }
            </style>
            </noscript><?php
        } ?>
        </head><?php
        $data['MOBILE'] = ($_SERVER["MOBILE"]) ? 'mobile': '';
        flush();
        ?>
        <body class="html-<?=$data['BLOCK_PROGRESSION']?> html-<?=
            $data['LOCALE_DIR'] ?> html-<?= $data['WRITING_MODE'].' '.
            $data['MOBILE'] ?>" >
        <div id="body-container" class="body-container">
        <div id="message" ></div><?php
        $this->view->renderView($data);
        if (C\QUERY_STATISTICS && (!isset($this->presentation) ||
            !$this->presentation)) { ?>
            <div class="query-statistics"><?php
            e("<h1>" . tl('web_layout_query_statistics')."</h1>");
            e("<div><b>".
                $data['YIOOP_INSTANCE']
                ."</b><br /><br />");
            e("<b>".tl('web_layout_total_elapsed_time',
                 $data['TOTAL_ELAPSED_TIME'])."</b></div>");
            foreach ($data['QUERY_STATISTICS'] as $query_info) {
                e("<div class='query'><div>".$query_info['QUERY'].
                    "</div><div><b>".
                    tl('web_layout_query_time',
                        $query_info['ELAPSED_TIME']).
                        "</b></div></div>");
            } ?>
            </div><?php
        }
        if (isset($_SERVER["COOKIE_CONSENT"]) && !$_SERVER["COOKIE_CONSENT"]
            && !(!empty($_REQUEST['c']) && in_array($_REQUEST['c'],
            ['admin', 'register']))) {
            $consent_url = htmlentities($_SERVER["REQUEST_URI"]);
            $separator = (strpos($consent_url, "?") !== false) ? "&amp;" :
                "?";
            if (strpos($consent_url, "cookieconsent=true") === false) {
                $consent_url .= $separator . "cookieconsent=true";
            }
            ?>
            <div class="cookie-consent">
                <?=tl('web_layout_cookie_uses') ?>
                <a href="<?=B\directUrl('privacy') ?>"><?=
                tl('web_layout_privacy_policy') ?></a>.
                <a href="<?=$consent_url; ?>" class='anchor-button-consent'><?=
                    tl('web_layout_allow_cookies') ?></a>
            </div><?php
        }
        $script_path = C\APP_DIR . "/scripts/basic.js";
        $basic_js = $path_url . "scripts/basic.js";
        if (file_exists($script_path)) {
            $basic_js = "$path_url?c=resource&amp;a=get" .
                "&amp;f=scripts&amp;n=basic.js";
        }
        ?>
        <script src="<?=$basic_js ?>" ></script><?php
        if ($this->view->helper('helpbutton')->is_help_initialized) {
            if (!isset($data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"] = [];
            }
            $data["INCLUDE_SCRIPTS"][] = "help";
        }
        if ($this->view->helper('helpbutton')->script) {
            if (!isset($data['SCRIPT'])) {
                $data['SCRIPT'] = "";
            }
            $data['SCRIPT'] = $this->view->helper('helpbutton')->script .
                   $data['SCRIPT'];
        }
        if (isset($data['INCLUDE_SCRIPTS'])) {
            foreach ($data['INCLUDE_SCRIPTS'] as $script_name) {
                if ($script_name == "math") {
                    $math_jax_path = C\APP_DIR . "/scripts/MathJax/MathJax.js";
                    if (file_exists($math_jax_path)  && C\REDIRECTS_ON) {
                        e('<script src="' . $path_url .
                            'wd/scripts/MathJax/MathJax.js'.
                            '?config=TeX-MML-AM_HTMLorMML" ></script>');
                    } else {
                        e('<script src="https://cdnjs.cloudflare.com/ajax/' .
                            'libs/mathjax/2.7.3/MathJax.js'.
                            '?config=TeX-MML-AM_HTMLorMML" ></script>');
                    }
                    // don't process math if html tag has class 'none'
                    e('<script>'.
                        'MathJax.Hub.Config({ asciimath2jax: { '.
                        'ignoreClass: "none" '.
                        '} });'.
                        '</script>');
                } else if ($script_name == "credit" &&
                    C\CreditConfig::isActive()) {
                    e('<script src="' . C\CreditConfig::getCreditTokenUrl() .
                        '" ></script>');
                } else {
                    $script_path = C\APP_DIR . "/scripts/" . $script_name .
                        ".js";
                    if (file_exists($script_path)) {
                        if (C\REDIRECTS_ON) {
                            $script_url = $path_url . 'wd/scripts/'.
                                $script_name.'.js';
                        } else {
                            $script_url = "$path_url?c=resource&amp;a=get" .
                                "&amp;f=scripts&amp;n=$script_name.js";
                        }
                    } else {
                        $script_url = $path_url . 'scripts/'.
                            $script_name . '.js';
                    }
                    e ("<script src='$script_url' ></script>");
                }
            }
        }
        if (isset($data['INCLUDE_LOCALE_SCRIPT'])) {
            $locale_tag = str_replace("-", "_", $data["LOCALE_TAG"]);
            $locale_path = C\APP_DIR .
                "/locale/$locale_tag/resources/locale.js";
            $default_locale_path = C\BASE_DIR .
                "/locale/$locale_tag/resources/locale.js";
            if (file_exists($locale_path)) { ?>
                <script src='<?=$path_url . "?c=resource&amp;a=get" .
                    "&amp;f=locale&amp;sf=$locale_tag/resources".
                    "&amp;n=$script_name.js" ?>' ></script><?php
            } else if (file_exists($default_locale_path)) { ?>
                <script src='<?=$path_url . "locale/$locale_tag" .
                "/resources/locale.js" ?>' ></script><?php
            }
        } ?>
        <div id='tl'></div>
        <script><?php
        if (isset($data['SCRIPT'])) {
            e($data['SCRIPT']);
        }
        if (isset($data['DISPLAY_MESSAGE'])) {
            e("\ndoMessage('<h1 class=\"display-message\" >" .
                $data['DISPLAY_MESSAGE'] .
                "</h1>');");
        } ?>;/*keep semi-colon just in case inserted JS didn't have */
        if (typeof yioop_post_scripts === 'object' ) {
            for (var callback_index in yioop_post_scripts) {
                yioop_post_scripts[callback_index]();
            }
        }
        </script>
        </div>
        </body>
        </html><?php
    }
}
