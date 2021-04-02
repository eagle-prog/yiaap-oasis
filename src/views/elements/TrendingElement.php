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
use seekquarry\yioop\library\CrawlConstants as CC;
/**
 * Class to draw statistics and charts about trending news feed terms
 *
 * @author Chris Pollett
 */
class TrendingElement extends Element
{
    /**
     * Used to draw either trending news feed term scores or charts
     *
     * @param array $data contains stats to draw
     */
    public function render($data)
    {
        if (empty($data['CONTAINER_LABEL']) ||
            ($data['CONTAINER_LABEL'] == 'center' &&
            empty($data['LANDING_HIGHLIGHTS']))) {
            if (empty($data['CHART_DATA'])) {
                $this->renderTrendingPeriods($data);
            } else {
                $this->renderTermChart($data);
            }
        } else {
            $this->renderLandingHighlights($data);
        }
    }
    /**
     * Used to draw top NUM_TRENDING hourly, daily, weekly term scores
     *
     * @param array $data contains stats to draw
     */
    public function renderTrendingPeriods($data)
    {
        $date_map = [
            C\ONE_HOUR => tl('trending_element_hourly'),
            C\ONE_DAY => tl('trending_element_daily'),
            C\ONE_WEEK => tl('trending_element_weekly'),
            C\ONE_MONTH => tl('trending_element_monthly'),
            C\ONE_YEAR => tl('trending_element_yearly'),
        ];
        $query_dates = [ C\ONE_HOUR => 'h', C\ONE_DAY => 'd',
            C\ONE_WEEK => 'w', C\ONE_MONTH => 'm', C\ONE_YEAR => 'y'
        ];
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token = ($logged_in) ? $data[C\CSRF_TOKEN] : "";
        $token_string_amp = ($logged_in) ?
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]."&amp;" : "";
        if(isset($data['TREND_DATA'])) {
            ?><div class='all-trends'><?php
            if (!empty($data['CATEGORY_NAME'])) {
                $search_url = B\subsearchURL($data['CATEGORY'] , true);
                $added_term = "";
            } else {
                $search_url = C\SHORT_BASE_URL . "?";
                $added_term = " media:" . $data['CATEGORY'];
                $data['CATEGORY_NAME'] = ucfirst($data['CATEGORY']);
            }
            $base_chart_query = "chart:" . $data['CATEGORY'] . ":";?>
            <h2 class="trending"><?=tl('trending_element_trending_terms',
                $data['CATEGORY_NAME']);
            ?></h2>
            <div class="trending-container"><?php
            foreach($data['TREND_DATA'] as $period => $occurrences) {
                if (empty($occurrences)) {
                    continue;
                }
                ?>
                <div class="trending-float">
                    <form method="get" action="<?=C\SHORT_BASE_URL ?>">
                    <input type="hidden" name="q" value="<?=$base_chart_query .
                        $query_dates[$period] ?>" />
                    <p><b><?=$date_map[$period]?></b>
                    <?php
                    if ($period !== C\ONE_HOUR) {?>
                        <button class="float-opposite" type="submit"><?=
                        tl('trending_element_chart')?></button></p><?php
                    }?>
                    <table class="trending-table">
                        <tr class="trending-tr">
                        <th class="trending-th"><?=
                            tl('trending_element_term')?></th>
                        <th class="trend-score trending-th"><?=
                            tl('trending_element_score'); ?></th></tr><?php
                    $background = "";
                    foreach($occurrences as $item) {
                        $background = empty($background) ?
                            " class='back-gray' " : "";
                        ?>
                        <tr <?=$background ?>>
                        <td class="trending-td"><?php
                        if ($period != C\ONE_HOUR) {?>
                            <input type='checkbox'
                                name='term[<?=
                                urlencode($item['TERM'])
                                ?>]'  />
                            <?php
                        }
                        if (empty($data['CATEGORY_TYPE']) ||
                            $data['CATEGORY_TYPE'] != 'trending_value') { ?>
                            <a href="<?=
                            $search_url . $token_string_amp .
                            "q=" . $item['TERM'] . $added_term ?>"><?=
                            $item['TERM']?></a><?php
                        } else {
                            echo $item['TERM'];
                        }?></td><?php
                        if ($period == C\ONE_HOUR) {
                            ?><td class="trend-score trending-td"><?=
                            number_format($item['OCCURRENCES'], 2);?></td>
                            <?php
                        } else {
                            ?><td class="trend-score trending-td"><a href="<?=
                            C\SHORT_BASE_URL . "?" . $token_string_amp .
                            "q=$base_chart_query{$query_dates[$period]}:" .
                            urlencode(urlencode($item['TERM']));
                            ?>"><?=
                            number_format($item['OCCURRENCES'], 2);?></a></td>
                            <?php
                        } ?>
                        </tr>
                        <?php
                    } ?></table>
                    </form>
                </div>
                <?php
            } ?>
            </div>
            </div>
            <div class="trending-footer" ><b><?=
                tl('trending_element_date', date('r')) ?></b></div>
            <?php
        }
    }
    /**
     * Used to draw random trending terms results on landing page
     *
     * @param array $data contains stats to draw
     */
    public function renderLandingHighlights($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token = ($logged_in) ? $data[C\CSRF_TOKEN] : "";
        $token_query = ($logged_in && isset($data[C\CSRF_TOKEN])) ?
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] : "";
        foreach ($data['LANDING_HIGHLIGHTS'] as $highlight) {
            if ($highlight['TYPE'] == 'random_terms' &&
                !empty($highlight['DATA'])) {
                $category = ($highlight['CATEGORY'] == 'news') ? "" :
                    "category=" . $highlight['CATEGORY'];
                $trending_url = C\SHORT_BASE_URL . "?q=trending:" .
                    $highlight['CATEGORY'];
                if (!empty($highlight['ORDER']) &&
                    strtolower($highlight['ORDER']) != 'score_desc') {
                    $trending_url .= ":" . $highlight['ORDER'];
                }
                if (!empty($token_query)) {
                    $trending_url .= "&amp;" . $token_query;
                }
                $terms_per_row = (!empty($_SERVER['MOBILE'])) ? 2 : 3;
                ?><div class="trending-highlight"><h2><a href="<?= $trending_url
                    ?>"><?= $highlight['NAME']; ?>...</a></h2><ul><?php
                $num_rows = intval(count($highlight)/$terms_per_row);
                $i = 0;
                $token_query = (empty($token_query)) ? "" : $token_query .
                    "&amp;";
                $num_random_trends = (!empty($_SERVER['MOBILE'])) ?
                    6 : 9;
                $highlight_data = array_slice($highlight['DATA'], 0,
                    $num_random_trends);
                foreach ($highlight_data as $term) {
                    if ($i % $terms_per_row == 0 && $i > 0) { ?>
                    </ul> <ul>
                    <?php }
                    ?><li><a href="<?=
                        B\subsearchURL($highlight['CATEGORY'] , true) .
                        $token_query . "q=" . $term ?>"><?=$term
                        ?></a></li><?php
                    $i++;
                }
                ?></ul></div><?php
            } else if ($highlight['TYPE'] == 'feed' &&
                !empty($highlight['DATA'])) {
                $subsearch = $highlight['FOLDER_NAME'];
                $feed_url = B\subsearchURL($subsearch, !empty($token_query)) .
                    $token_query;
                $delim = (empty($token_query) && C\REDIRECTS_ON) ? "?" :
                    "&amp;";
                ?>
                <div class="feed-highlight"><h2><a href="<?=$feed_url
                    ?>"><?= $highlight['NAME']; ?>...</a></h2><ul><?php
                    foreach ($highlight['DATA'] as $item) {
                        $encode_source = urlencode(urlencode(
                            $item[CC::SOURCE_NAME]));
                        $source_query = "q=media:$subsearch:" . $encode_source;
                        $title = $item[CC::TITLE];
                        if (!empty($this->view->controller_object)) {
                            $phrase_model =
                                $this->view->controller_object->model("phrase");
                            $title = trim($phrase_model->getSnippets($title,
                                [], 60));
                        }
                        ?><li><a href="<?=$item[CC::URL] ?>"><?=$title
                            ?></a>&#8230;<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <a class="gray-link" rel='nofollow' href="<?=
                            $feed_url . $delim . $source_query ?>" ><?=
                            $item[CC::SOURCE_NAME] ?></a></li><?php
                    }?></ul></div><?php
            }
        }
    }
    /**
     * Used to draw a chart of term scores for a time period
     *
     * @param array $data contains chart info about term
     */
    public function renderTermChart($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $token = ($logged_in) ? $data[C\CSRF_TOKEN] : "";
        $token_string_amp = ($logged_in) ?
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]."&amp;" : "";
        if (!empty($data['CATEGORY_NAME'])) {
            $search_url = B\subsearchURL($data['CATEGORY'] , true);
            $added_term = "";
        } else {
            $search_url = C\SHORT_BASE_URL . "?";
            $added_term = " media:" . $data['CATEGORY'];
        }
        $trending_url = C\SHORT_BASE_URL . "?q=" . "trending:" .
            $data['CATEGORY'];
        if (!empty($token_query))  {
            $trending_url .= $token_query;
        }
        $terms_string = "";
        $dash_state = 0;
        $comma = "";
        $trending_scores = "<a href='$trending_url'>" .
            tl('trending_element_trend_scores') . "</a>";
        foreach ($data['TERMS'] as $term) {
            $terms_string .= $comma;
            $comma = "', '";
            $term_url = $search_url . $token_string_amp .
                "q=" . $term . $added_term;
            if ($dash_state == 0) {
                $rgb = "rgb(0,0,255)";
                $span = "'<a href='$term_url' ".
                    "style='text-decoration:underline;color:$rgb;'>";
            } else if ($dash_state == 1) {
                $rgb = "rgb(0,0,178)";
                $span = "<a href='$term_url' ".
                    "style='text-decoration:underline;".
                    "text-decoration-style:dashed;color:$rgb;'>";
            } else {
                $rgb = "rgb(0,0,124)";
                $span = "<a href='$term' ".
                    "style='text-decoration:underline;".
                    "text-decoration-style:dotted;color:$rgb;'>";
            }
            $terms_string .= "$span$term</a>'";
            $dash_state = ($dash_state + 1) % 3;
        }
        $title_map = [
            C\ONE_DAY => tl('trending_element_hourly_trend', $trending_scores,
                $terms_string),
            C\ONE_WEEK => tl('trending_element_daily_trend', $trending_scores,
                $terms_string),
            C\ONE_MONTH => tl('trending_element_monthly_trend',
                $trending_scores, $terms_string),
            C\ONE_YEAR => tl('trending_element_yearly_trend', $trending_scores,
                $terms_string),
        ];
        ?><h2 class="trending"><?=$title_map[$data['PERIOD']]; ?></h2>
        <div id="chart"></div>
        <div class="trending-footer" ><b><?=
            tl('trending_element_date', date('r')) ?></b></div><?php
    }
}
