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
namespace seekquarry\yioop\views\helpers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;

/**
 * Helper used to draw links and snippets for RSS feeds
 *
 * @author Chris Pollett
 */
class FeedsHelper extends Helper implements CrawlConstants
{
    /**
     * Takes page summaries for RSS pages and the current query
     * and draws list of news links and a link to the news link subsearch
     * page if applicable.
     *
     * @param array $feed_pages page data from news feeds
     * @param string  $csrf_token token to prevent cross site request forgeries
     * @param string $query the current search query
     * @param string $subsearch the name of the subsearch of this feed
     *  For example, one could have sports feed, a news feed, etc
     * @param boolean $open_in_tabs whether new links should be opened in
     *    tabs
     */
    public function render($feed_pages, $csrf_token, $query, $subsearch,
        $open_in_tabs = false)
    {
        $feed_url = B\subsearchUrl($subsearch);
        $query_array = (empty($csrf_token)) ? [] :
            [C\CSRF_TOKEN => $csrf_token];
        $delim = (C\REDIRECTS_ON) ? "?" : "&amp;";
        if ($subsearch != 'news') {
            $not_news = true;
            $query_string = http_build_query(array_merge($query_array,
                [ "q" => urldecode($query)]));
            ?>
            <h2><a href="<?= $feed_url . $delim . $query_string ?>"
                ><?= tl('feeds_helper_view_feed_results',
                urldecode($query)) ?></a></h2>
        <?php
        } else {
            $not_news = false;
        }?>
            <div class="feed-list">
        <?php
        $time = time();
        foreach ($feed_pages as $page) {
            if ($not_news) {
                $pub_date = $page[self::PUBDATE];
                $encode_source = urlencode(
                    urlencode($page[self::SOURCE_NAME]));
                $pub_date = $this->getPubdateString($time, $pub_date);
                $media_url = $feed_url . $delim .
                    http_build_query(array_merge($query_array,
                    [ "q" => "media:$subsearch:".urldecode($encode_source)]));
                ?>
                <div class="blockquote">
                <a href="<?= $page[self::URL]
                    ?>" rel="nofollow" <?php
                if ($open_in_tabs) {
                    ?> target="_blank" rel="noopener" <?php
                }
                ?>><?= $page[self::TITLE] ?></a>
                <a class="gray-link" rel='nofollow' href="<?=
                    $media_url ?>" ><?= $page[self::SOURCE_NAME]?></a>
                    <span class='gray'> - <?=$pub_date ?></span>
                </div>
                <?php
            } else {
                $this->renderFeedItem($page, $csrf_token, $query, $subsearch,
                    $open_in_tabs);
            }
        }
        ?>
        </div>
        <?php
    }
    /**
     * Renders one search source feed item into SERP.
     * @param array $page page data of a feed item to render
     * @param string  $csrf_token token to prevent cross site request forgeries
     * @param string $query the current search query
     * @param string $subsearch name of subsearch page this image group on
     * @param boolean $open_in_tabs whether new links should be opened in
     *    tabs
     */
    public function renderFeedItem($page, $csrf_token, $query,
        $subsearch, $open_in_tabs)
    {
        $feed_url = B\subsearchUrl($subsearch);
        $query_array = (empty($csrf_token)) ? [] :
            [C\CSRF_TOKEN => $csrf_token];
        $delim = (C\REDIRECTS_ON) ? "?" : "&amp;";
        $pub_date = $page[self::PUBDATE];
        $encode_source = urlencode(
            urlencode($page[self::SOURCE_NAME]));
        $time = time();
        $pub_date = $this->getPubdateString($time, $pub_date);
        $media_url = $feed_url . $delim .
            http_build_query(array_merge($query_array,
            [ "q" => "media:$subsearch:".urldecode($encode_source)]));
        if (isset($page[self::URL])) {
            if (strncmp($page[self::URL], "url|", 4) == 0) {
                $url_parts = explode("|", $page[self::URL]);
                $url = $url_parts[1];
                $title = UrlParser::simplifyUrl($url, 60);
                $subtitle = "title='".$page[self::URL]."'";
            } else {
                $url = $page[self::URL];
                $title = $page[self::TITLE];
                if (strlen(trim($title)) == 0) {
                    $title = UrlParser::simplifyUrl($url, 60);
                }
                $subtitle = "";
            }
        } else {
            $url = "";
            $title = isset($page[self::TITLE]) ? $page[self::TITLE] :"";
            $subtitle = "";
        }
        $image_string = "";
        $sf = "";
        $image_hash = "";
        if (!empty($page[self::IMAGE_LINK])) {
            $link_scheme = substr($page[self::IMAGE_LINK], 0, 7);
            if ($link_scheme == "data:im") {
                $image_string = "<img class='float-same' ".
                    "src='{$page[self::IMAGE_LINK]}' alt='' />";
            } else if ($link_scheme == "feed://") {
                $image_link_parts = explode("/", $page[self::IMAGE_LINK]);
                if (!empty($image_link_parts[3])) {
                    list(, , $sf, $image_hash) = $image_link_parts;
                }
            } else { //old style image link
                if (preg_match("/sf\=([^\&]+)/", $page[self::IMAGE_LINK],
                    $sf_match) && preg_match("/n\=([^\&]+)/",
                        $page[self::IMAGE_LINK], $n_match)) {
                        $sf = $sf_match[1];
                        $image_hash = $n_match[1];
                }
            }
            if (!empty($sf) && !empty($image_hash)) {
                if (C\nsdefined("REDIRECTS_ON") && C\REDIRECTS_ON) {
                    $image_url = C\NAME_SERVER . "wd/resources/feed/".
                        C\PUBLIC_GROUP_ID . "/1/$sf/$image_hash";
                } else {
                    $image_url = C\NAME_SERVER .
                        "?c=resource&amp;a=get&amp;f=resources".
                        "&amp;g=" . C\PUBLIC_GROUP_ID .
                        "&amp;t=feed&amp;sf=$sf" . "&amp;n=$image_hash";
                }
                $image_string = "<img class='float-same' ".
                    "src='$image_url' alt='' />";
            }
        }
        ?>
        <div class="news-result">
        <?php e($image_string); ?>
        <h2><a href="<?= $page[self::URL] ?>" <?php
        if ($open_in_tabs) {
            ?> target="_blank" rel="noopener nofollow" <?php
        } else {
            ?> rel="nofollow" <?php
        }
        ?>><?= $page[self::TITLE] ?></a>.
        <a class="gray-link" rel='nofollow' href="<?= $media_url
            ?>" ><?= $page[self::SOURCE_NAME] ?></a>
            <span class='gray'> - <?= $pub_date ?></span>
        </h2>
        <p class="echo-link" <?=$subtitle?> ><?=
            UrlParser::simplifyUrl($url, 100) . " " ?></p>
        <?php
        $description = isset($page[self::DESCRIPTION]) ?
            $page[self::DESCRIPTION] : "";
        e("<p>$description</p>");
        ?>
        </div>
        <?php
    }
    /**
     * Write as an string in the current locale the difference between the
     * publication date of a post and the current time
     *
     * @param int $time timestamp for current time
     * @param int $pub_date timestamp for feed_item publication
     * @param bool $relative_times if true use relative times like
     * y seconds ago
     * @return string in the current locale the time difference
     */
    public function getPubdateString($time, $pub_date, $relative_times = true)
    {
        $delta = $time - $pub_date;
        if ($delta < C\ONE_DAY) {
            $num_hours = ceil($delta/C\ONE_HOUR);
            if ($relative_times) {
                if ($num_hours <= 2) {
                    if ($num_hours > 1) {
                        $pub_date = tl('feeds_helper_view_onehour');
                    } else {
                        $num_minutes = floor($delta/C\ONE_MINUTE);
                        $remainder_seconds = $delta % C\ONE_MINUTE;
                        $pub_date =
                            tl('feeds_helper_view_minsecs', $num_minutes,
                                $remainder_seconds);
                    }
                } else {
                    $pub_date =
                        tl('feeds_helper_view_hourdate', $num_hours);
                }
            } else {
                $pub_date = date("g:i a", $pub_date); 
            }
        } else {
            $pub_date = date("d/m/Y", $pub_date);
        }
        return $pub_date;
    }
}
