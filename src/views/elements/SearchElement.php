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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\views\elements\Element;

/**
 * Element used to present search results
 * It is also contains the landing page search box for
 * people to types searches into
 *
 * @author Chris Pollett
 */
class SearchElement extends Element implements CrawlConstants
{
    /**
     * Represent extension of Git urls
     */
    const GIT_EXTENSION = ".git";
    /**
     * Number of decimals for search result scores
     */
    const SCORE_PRECISION = 4;
    /**
     * Draws the main landing pages as well as search result pages
     *
     * @param array $data  PAGES contains all the summaries of web pages
     * returned by the current query, $data also contains information
     * about how the the query took to process and the total number
     * of results, how to fetch the next results, etc.
     *
     */
    public function render($data)
    {
        if (empty($data['IS_LANDING'])) {
            $this->renderSearchResults($data);
        } else {
            $this->renderSearchLanding($data);
        }
        if (C\PROFILE && !empty($data["ADMIN"]) && empty($data['API']) ) { ?>
            <script>
            /*
                Used to warn that user is about to be logged out
             */
            function logoutWarn()
            {
                doMessage(
                    "<h2 class='red'><?php
                        e(tl('admin_view_auto_logout_one_minute'))?></h2>");
            }
            /*
                Javascript to perform autologout
             */
            function autoLogout()
            {
                document.location='<?=C\SHORT_BASE_URL ?>?a=signout';
            }
            //schedule logout warnings
            var sec = 1000;
            var minute = 60 * sec;
            var autologout = <?=C\AUTOLOGOUT ?> * sec;
            setTimeout("logoutWarn()", autologout - minute);
            setTimeout("autoLogout()", autologout);
            </script><?php
        }
    }
    /**
     * Used to draw the results of a query to the Yioop Search Engine
     *
     * @param array $data an associative array containing a PAGES field needed
     *     to render search result
     */
    public function renderSearchResults($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token = ($logged_in) ? $data[C\CSRF_TOKEN] : "";
        $token_string = ($logged_in) ? C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN] .
            "&" : "";
        $token_string_amp = ($logged_in) ?
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]."&amp;" : "";
        $is_search_view = (get_class($this->view) == C\NS_VIEWS .
            "SearchView");
        if ($is_search_view) {
            if (!empty($data['PAGES']) && !$_SERVER["MOBILE"]) {
                ?><h2 class="search-stats"><?php
                if ($data['RESULTS_PER_PAGE'] != -1) {
                    $num_results = min($data['TOTAL_ROWS'],
                        $data['LIMIT'] + $data['RESULTS_PER_PAGE']);
                    $limit = min($data['LIMIT'] + 1, $num_results);
                     ?> <?= tl('search_element_calculated',
                        number_format($data['ELAPSED_TIME'], 5)) ?> <?=
                     tl('search_element_results', $limit, $num_results,
                        $data['TOTAL_ROWS']) ?><?php
                } else {
                    e(tl('search_element_num_results', $data['TOTAL_ROWS']));
                } ?></h2><?php
            } ?>
            <div id='search-body' class="search-body" >
            <?php if (C\WORD_SUGGEST && empty($data['TREND_DATA']) &&
                empty($data['CHART_DATA'])) { ?>
                <div id="spell-check" class="spell"><span
                class="hidden" >&nbsp;</span></div><?php
            } ?>
            <div class="search-results"><?php
        } else { ?>
            <div class="continuous-result-separator"><?=
                $data['LIMIT'] ?></div>
            <hr id='limit-<?=$data['LIMIT'] ?>' data-total='<?=
                $data['TOTAL_ROWS'] ?>' />
            <?php
        }
        if (!empty($data['BEST_ANSWER'])) {
            ?><div id="best-answer" class="echo-link">
                 <?= $data['BEST_ANSWER']; ?>
            </div><?php
        }
        if (empty($data['PAGES']) && empty($data['TREND_DATA']) &&
            empty($data['CHART_DATA']) && $data['LIMIT'] == 0) {
            ?>
            <div class='no-search-results'>
                <?=tl('search_element_no_results') ?>
            </div>
            <?php
        }
        if (empty($data['PAGES'])) {
            if($is_search_view) { ?>
                </div>
                </div>
                <?php
            }
            return;
        }
        foreach ($data['PAGES'] as $page) {
            if (isset($page[self::URL])) {
                if (substr($page[self::URL], 0, 4) == "url|") {
                    $url_parts = explode("|", $page[self::URL]);
                    $url = $url_parts[1];
                    $link_url = $url;
                    $title = (empty($page[self::TITLE]) ||
                        $page[self::TITLE] == $url) ?
                        UrlParser::simplifyUrl($url, 60) :
                        $page[self::TITLE];
                    $subtitle = "title='" . $page[self::URL] . "'";
                } else {
                    $url = $page[self::URL];
                    if (substr($url, 0, 7) == "record:") {
                        $link_url = "?" . $token_string .
                        "a=cache&q=" . $data['QUERY'].
                        "&arg=" . urlencode($url) . "&its=".
                        $page[self::CRAWL_TIME];
                    } else {
                        $link_url = $url;
                    }
                    $title = mb_convert_encoding($page[self::TITLE],
                        "UTF-8", "UTF-8");
                    if (strlen(trim($title)) == 0) {
                        $title = UrlParser::simplifyUrl($url, 60);
                    }
                    $subtitle = "";
                }
            } else {
                $url = "";
                $link_url = $url;
                $title = isset($page[self::TITLE]) ? $page[self::TITLE] : "";
                $subtitle = "";
            }
            $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] :
                "";
            $base_query = "?" . $token_string_amp.
                    "c=search";
            if (isset($page['IMAGES'])) {
                $this->view->helper("images")->render($page['IMAGES'],
                    $base_query . "&amp;q={$data['QUERY']}", $subsearch,
                    $data['IMAGE_SUBSEARCH_ENABLED']);
                continue;
            } else if (isset($page['FEED'])) {
                $this->view->helper("feeds")->render($page['FEED'],
                    $token, $data['QUERY'],  $subsearch,
                    $data['OPEN_IN_TABS']);
                continue;
            } else if (isset($page[self::SOURCE_NAME])) {
                $this->view->helper("feeds")->renderFeedItem($page,
                    $token, $data['QUERY'], $subsearch, $data['OPEN_IN_TABS']);
                continue;
            }
            ?><div class='result'>
            <h2>
            <?php
                if (strpos($link_url, self::GIT_EXTENSION)) { ?>
                <a href="?<?= $token_string_amp
                    ?>a=cache&amp;q=<?= $data['QUERY']
                    ?>&amp;arg=<?= urlencode($url) ?>&amp;its=<?=
                    $page[self::CRAWL_TIME] ?>&amp;repository=git"
                    rel='nofollow'>
            <?php } else { ?>
                <a href="<?= htmlentities($link_url)
                    ?>" rel="nofollow" <?php
                    if ($data["OPEN_IN_TABS"]) { ?>
                        target="_blank" rel="noopener"<?php
                    }?> >
                    <?php
            }
            $is_video = false;
            $is_image = false;
            if (isset($page[self::THUMB]) && $page[self::THUMB] != 'null'
                && $page[self::THUMB] != 'NULL') {
                if (empty($page[self::IS_VIDEO])) {
                    ?><img src="<?= $page[self::THUMB] ?>"
                    loading="lazy" alt="<?=title ?>" /><?php
                    $is_video = false;
                    $is_image = true;
                } else {
                    $is_video = true;
                }
            }
            if (!$is_image) {
                e($title);
                if (isset($page[self::TYPE])) {
                    $this->view->helper("filetype")->render($page[self::TYPE]);
                }
            }
            ?></a>
            </h2>
            <?php if ($is_video) {
                $this->view->helper("videourl")->render($link_url,
                    $page[self::THUMB], $data["OPEN_IN_TABS"]);
            }
            if (!$_SERVER["MOBILE"] && isset($page[self::WORD_CLOUD]) &&
                is_array($page[self::WORD_CLOUD])) { ?>
                <p><span class="echo-link" <?=$subtitle ?>><?=
                    UrlParser::simplifyUrl($url, 40)." "
                ?></span>
                <?php
                if (isset($page['ANSWER'])) {
                    $answer = $page['ANSWER'];?>
                    <span class="echo-link" <?=$subtitle ?>>
                    <?php e("<span class='word-cloud-spacer'>".
                    tl('search_element_possible_answer')."</span>"); ?>
                    <?=$answer." "?>
                    </span><?php
                }
                $cloud = $page[self::WORD_CLOUD];
                ?>
                <?php
                $i = 1;
                if (!empty($cloud)) {
                    e("<span class='word-cloud-spacer'>".
                        tl('search_element_word_cloud')."</span>");
                        $len = 0;
                    foreach ($cloud as $word) {
                        $len += strlen($word);
                        if ($len > 40) {
                            break;
                        }
                        ?><span class="word-cloud">
                        <a class='word-cloud-<?= $i ?>' href="?<?=
                            $token_string_amp?>its=<?= $data['its']
                            ?>&amp;q=<?=$word ?>"><?=
                            $this->view->helper("displayresults")->render($word)
                            ?></a></span><?php
                        $i++;
                    }
                }
            } else { ?>
                <p><span class="echo-link" <?=$subtitle ?>><?=
                    UrlParser::simplifyUrl($url, 100)." "
                ?></span><?php
            } ?></p><?php
            if (!isset($page[self::ROBOT_METAS]) ||
                !in_array("NOSNIPPET", $page[self::ROBOT_METAS])) {
                    $description = isset($page[self::DESCRIPTION]) ?
                        $page[self::DESCRIPTION] : "";
                    $description = ltrim(mb_convert_encoding($description,
                        "UTF-8", "UTF-8"), C\PUNCT);
                    e("<p>".$this->view->helper("displayresults")->
                        render($description)."</p>");
                }?>
            <p class="serp-links-score"><?php
            $aux_link_flag = false;
            if (isset($page[self::TYPE]) && $page[self::TYPE] != "link") {
                if (C\CACHE_LINK && (!isset($page[self::ROBOT_METAS]) ||
                    !(in_array("NOARCHIVE", $page[self::ROBOT_METAS]) ||
                      in_array("NONE", $page[self::ROBOT_METAS])))) {
                    $aux_link_flag = true; ?>
                    <a href="?<?=$token_string_amp ?>a=cache&amp;q=<?=
                        $data['QUERY'] ?>&amp;arg=<?=urlencode($url)
                        ?>&amp;its=<?= $page[self::CRAWL_TIME] ?>"
                        rel='nofollow'>
                        <?php
                        if ($page[self::TYPE] == "text/html" ||
                            stristr($page[self::TYPE], "image")) {
                            e(tl('search_element_cache'));
                        } else {
                            e(tl('search_element_as_text'));
                        }
                        ?></a>.<?php
                }
                if (C\SIMILAR_LINK) {
                    $aux_link_flag = true; ?>
                    <a href="?<?=$token_string_amp
                        ?>a=related&amp;arg=<?=urlencode($url)
                        ?>&amp;its=<?= $page[self::CRAWL_TIME]
                        ?>" rel='nofollow'><?= tl('search_element_similar')
                        ?></a>.
                    <?php
                }
                if (C\IN_LINK) {
                    $aux_link_flag = true; ?>
                    <a href="?<?= $token_string_amp ?>q=<?=
                        urlencode("link:".$url) ?>&amp;its=<?=
                        $page[self::CRAWL_TIME] ?>" rel='nofollow'><?=
                        tl('search_element_inlink') ?></a>.<?php
                }
                if (C\IP_LINK && isset($page[self::IP_ADDRESSES])) {
                    foreach ($page[self::IP_ADDRESSES] as $address) {
                        if ($address == "0.0.0.0") {
                            continue;
                        }
                        ?>
                        <a href="?<?=$token_string_amp
                            ?>q=<?=urlencode('ip:' . $address)
                            ?>&amp;its=<?=$data['its'] ?>" rel='nofollow'>IP:<?=
                            $address ?></a>. <?php
                    }
                }
            }
            if ($_SERVER["MOBILE"] && $aux_link_flag) {e("<br />");}
            if (!C\nsdefined("RESULT_SCORE") || C\RESULT_SCORE) {
                if (!empty($page[self::SCORE])) {
                    ?><span title="<?php
                    e(tl('search_element_rank',
                        number_format($page[self::DOC_RANK], 2)) . "\n");
                    e(tl('search_element_relevancy',
                        number_format($page[self::RELEVANCE], 2) ) . "\n");
                    e(tl('search_element_proximity',
                        number_format($page[self::PROXIMITY], 2) ) . "\n");
                    if (isset($page[self::USER_RANKS])) {
                        foreach ($page[self::USER_RANKS] as $label => $score) {
                            e($label . ":" . number_format($score, 2) . "\n");
                        }
                    }
                    ?>" ><?=tl('search_element_score',
                        number_format($page[self::SCORE],
                        self::SCORE_PRECISION))?></span><?php
                } else if (!empty($page[self::PINNED])) {
                    e(tl('search_element_score_pinned'));
                }
            }
            ?>
            </p>
        </div>
        <?php
        } //end foreach
        if($is_search_view) { ?>
            </div>
            </div>
            <?php
        } ?>
        <?php
    }
    /**
     * Draws the landing page for this instance of Yioop when the default
     * big search bar (rather than the Main public wiki page is used)
     * @param array $data containing fields used to draw landing page
     */
    public function renderSearchLanding($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $query_parts = [];
        if ($logged_in) {
            $query_parts[C\CSRF_TOKEN] = $data[C\CSRF_TOKEN];
        }
        $logo = C\SHORT_BASE_URL . C\LOGO_LARGE;
        if ($_SERVER["MOBILE"]) {
            $logo = C\SHORT_BASE_URL . C\LOGO_MEDIUM;
        }
        ?>
        <div class='top-landing-spacer'></div>
        <h1 class="logo"><a href="<?= C\SHORT_BASE_URL ?><?php
        if ($logged_in) {
            e("?".http_build_query($query_parts));
        } ?>"><img src="<?php e($logo); ?>" alt="<?= tl('search_element_title')
                 ?>" ></a>
        </h1><?php
        $subsearch = "";
        if (!empty($data['SUBSEARCH'])) {
            $key = array_search($data['SUBSEARCH'],
                array_column($data["SUBSEARCHES"], 'FOLDER_NAME'));
            if(!empty($key)) {
                e(" <div class='logo-subsearch'>" .
                    $data["SUBSEARCHES"][$key]['SUBSEARCH_NAME'] .
                    "</div>");
                $subsearch = "subsearch";
            }
        } ?>
        <div class="<?=$subsearch ?> search-box">
        <form id="search-form" method="get" action="<?=C\SHORT_BASE_URL ?>"
            onsubmit="processSubmit()">
        <p><?php
        if (isset($data["SUBSEARCH"]) && $data["SUBSEARCH"] != "") {
            ?><input type="hidden" name="s" value="<?=
            $data['SUBSEARCH'] ?>" /><?php
        }
        if ($logged_in) { ?>
            <input id="csrf-token" type="hidden" name="<?= C\CSRF_TOKEN ?>"
                value="<?= $data[C\CSRF_TOKEN] ?>" /><?php
        } ?>
        <input id="its-value" type="hidden" name="its" value="<?=
            $data['its'] ?>" />
        <input type="search" <?php if (C\WORD_SUGGEST) { ?>
            autocomplete="off"  onkeyup="onTypeTerm(event, this)"
            <?php } ?>
            title="<?= tl('search_element_input_label') ?>"
            id="query-field" name="q" value="<?php
            if (isset($data['QUERY']) && !isset($data['NO_QUERY'])) {
                e(urldecode($data['QUERY']));} ?>"
            placeholder="<?= tl('search_element_input_placeholder') ?>"/>
        <button class="button-box" type="submit"><img
            src='<?=C\SHORT_BASE_URL ?>resources/search-button.png'
            alt='<?= tl('search_element_search') ?>'/></button>
        </p>
        </form>
        </div>
        <div id="suggest-dropdown">
            <ul id="suggest-results" class="suggest-list">
            </ul>
        </div>
        <?php
    }
}
