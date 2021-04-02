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
namespace seekquarry\yioop\views;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
/**
 * This view is used to display information about
 * crawls that have been made by this seek_quarry instance
 *
 * @author Chris Pollett
 */
class CrawlstatusView extends View
{
    /**
     * Instantiates a view for drawing the current status of crawls in the
     * Yioop system
     * @param object $controller_object that is using this view
     */
    public function __construct($controller_object = null)
    {
        if (!empty($_REQUEST['noscript'])) {
            $this->layout = "web"; /*
                want whole rather than partial page if no Javascript
                calling context
                */
        }
        parent::__construct($controller_object);
    }
    /**
     * An Ajax call from the Manage Crawl Element in Admin View triggers
     * this view to be instantiated. The renderView method then draws statistics
     * about the currently active crawl.The $data is supplied by the crawlStatus
     * method of the AdminController.
     *
     * @param array $data info about the current crawl status
     */
    public function renderView($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $csrf_string = C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
        $pre_base_url = "{$admin_url}a=manageCrawls&amp;{$csrf_string}";
        $base_url = "$pre_base_url&amp;arg=";
        $filter = (empty($data['FILTER'])) ? "" :
            "&amp;filter=" . $data['FILTER'];
        $query_stats_url = "{$base_url}querystats$filter";
        $statistics_url = "{$base_url}statistics&amp;";
        $target = (empty($_REQUEST['noscript'])) ? "" : " target='_parent' ";
        $none = (empty($_REQUEST['noscript']) &&
            empty($_REQUEST['crawlform'])) ? "none" : "";
        ?>
        <h1  class="slim"><?= tl('crawlstatus_view_crawl_status')?></h1>
        <div class="no-margin">&nbsp;[<a <?=$target?> href='<?=$admin_url .
        $csrf_string ?>&amp;a=manageMachines'><?=
        tl('crawlstatus_view_manage_machines') ?></a>]</div>
        <?php
        $this->renderActiveCrawls($data);
        $data['TABLE_TITLE'] = tl('crawlstatus_view_crawls');
        if (C\SEARCH_ANALYTICS_MODE && C\SEARCH_ANALYTICS_MODE != "0") {
            $data['TABLE_TITLE'] .= " <span class='no-bold medium-large'>" .
            "[<a $target href='$query_stats_url'>" .
            tl('crawlstatus_view_query_stats') . "</a>]</span>";
        }
        $data['ACTIVITY'] = 'manageCrawls';
        $data['VIEW'] = $this;
        $data['NO_FLOAT_TABLE'] = false;
        $data['FORM_TYPE'] = null;
        $data['NO_SEARCH'] = true;
        if (!empty($_REQUEST['crawlform'])) {
            $data['ALTERNATIVE_ADD_TOGGLE_URL'] = $pre_base_url;
        }
        $num_columns = (empty($_SERVER["MOBILE"])) ? 6: 4;?>
        <table class="admin-table">
        <tr><td class="no-border" colspan="<?=
            $num_columns ?>"><div><?php $this->helper(
            "pagingtable")->render($data); ?></div>
            <div id='admin-form-row' class='admin-form-row <?=$none ?>'><?php
            if ($data['FORM_TYPE'] == "search") {
                $this->renderSearchForm($data);
            } else {
                $this->renderCrawlForm($data);
            }?>
            </div>
            </td>
        </tr>
        <tr><th><?= tl('crawlstatus_view_description') ?></th><?php
        if (empty($_SERVER["MOBILE"])) {?>
            <th><?php
            e(tl('crawlstatus_view_timestamp')); ?></th>
            <th><?php e(tl('crawlstatus_view_url_counts'));?></th><?php
        }
        ?>
        <th colspan="3"><?= tl('crawlstatus_view_actions') ?></th></tr><?php
        if (!empty($data['RECENT_CRAWLS'])) {
            foreach ($data['RECENT_CRAWLS'] as $crawl) {
                $description = ($_SERVER["MOBILE"]) ?
                    wordwrap($crawl['DESCRIPTION'],
                    10, "<br />\n", true) :
                    $crawl['DESCRIPTION']; ?>
                <tr><td><b><?php e($description); ?></b><br />
                    [<a <?=$target?> href="<?= $statistics_url .
                        C\CSRF_TOKEN."=" . $data[C\CSRF_TOKEN] ?>&amp;its=<?=
                        $crawl['CRAWL_TIME'] ?>"><?=
                    tl('crawlstatus_view_statistics') ?></a>]</td><?php
                    if (!$_SERVER["MOBILE"]) { ?>
                    <td><?php
                        e("<b>{$crawl['CRAWL_TIME']}</b><br />");
                        e("<span class='smaller-font'>" .
                            date("r", $crawl['CRAWL_TIME']) . "</span>");
                            ?></td><?php
                        $visited_urls_count =
                            (empty($crawl["QUERY_VISITED_URLS_COUNT"])) ?
                            (isset($crawl["VISITED_URLS_COUNT"]) ?
                                $crawl['VISITED_URLS_COUNT'] : 0) :
                            $crawl["QUERY_VISITED_URLS_COUNT"];
                        $query_count =
                            (empty($crawl["QUERY_COUNT"])) ?
                            (isset($crawl["COUNT"]) ?
                                $crawl['COUNT'] : 0) :
                            $crawl["QUERY_COUNT"];
                        ?>
                        <td> <?= $visited_urls_count .  "/".
                            $query_count ?></td><?php
                    }
                    ?>
                    <td><?php if ($crawl['RESUMABLE']) { ?>
                        <a <?=$target?> href="<?= $base_url
                            ?>resume&amp;timestamp=<?=
                            $crawl['CRAWL_TIME'] ?>"><?=
                            tl('crawlstatus_view_resume') ?></a>
                        <?php } else {
                                e(tl('crawlstatus_view_no_resume'));
                              }?></td>
                <td>
                <?php
                if ( $crawl['CRAWL_TIME'] != $data['CURRENT_INDEX']) { ?>
                    <a <?=$target
                        ?> href="<?= $base_url ?>index&amp;timestamp=<?=
                        $crawl['CRAWL_TIME'] ?>"><?=
                        tl('crawlstatus_view_set_index') ?></a>
                <?php
                } else { ?>
                    <?= tl('crawlstatus_view_search_index'); ?>
                <?php
                }
                ?>
                </td>
                <td><a <?=$target?> href="<?= $base_url
                    ?>delete&timestamp=<?= $crawl['CRAWL_TIME']
                    ?>"><?= tl('crawlstatus_view_delete') ?></a></td>
                </tr><?php
            }
        } else { ?>
            <tr><td class='red'><?=
                tl('crawlstatus_view_no_previous_crawl')?></td><?php
        }?>
        </table>
        <?php
    }
    /**
     * This is used to render information about ongoing crawls
     * @param array $data associative array containing info about
     *  which crawls are still running, how many urls they have, etc.
     */
    public function renderActiveCrawls($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = "{$admin_url}a=manageCrawls&amp;".
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]."&amp;arg=";
        $statistics_url = "{$base_url}statistics&amp;";
        $target = (empty($_REQUEST['noscript'])) ? "" : " target='_parent' ";
        if (empty($data["ACTIVE_CRAWLS"])) {
           ?><p class="red"><b><?=
           tl('crawlstatus_view_no_active_crawls') ?></b> <?php
           return;
        }
        $i = 0;
        $num_active = count($data["ACTIVE_CRAWLS"]);
        ?>
        <ol>
        <?php
        foreach ($data["ACTIVE_CRAWLS"] as $channel => $crawl) {
            $draw_button = false;
            $class_none = (in_array("a$i", $data['CRAWL_TOGGLE']) ||
                $num_active <= 1) ?
                "" : " class='none' ";
            if (!isset($crawl['DESCRIPTION'])) {
                continue;
            } ?>
            <li><div><?php if ($num_active > 1) {
                ?>[<a href="javascript:toggleDisplay('active-crawl-<?=$i
                ?>')" ><?php
            } ?><b><?php
            switch ($crawl['DESCRIPTION']) {
                case 'BEGIN_CRAWL':
                    e(tl('crawlstatus_view_starting_crawl'));
                    $draw_button = true;
                    break;
                case 'RESUME_CRAWL':
                    e(tl('crawlstatus_view_resuming_crawl'));
                    $draw_button = true;
                    break;
                case 'SHUTDOWN_QUEUE':
                    e(tl('crawlstatus_view_shutdown_queue'));
                    break;
                case 'SHUTDOWN_DICTIONARY':
                    e(tl('crawlstatus_view_closing_dict'));
                    break;
                case 'SHUTDOWN_RUNPLUGINS':
                    e(tl('crawlstatus_view_run_plugins'));
                    break;
                default:
                    e($crawl['DESCRIPTION']);
                    $draw_button = true;
            } ?></b><?php
            if ($num_active > 1) {
                ?></a>]<?php
            } ?><?php
            if ($draw_button) {
                ?>&nbsp;&nbsp;
                <a <?=$target?> class='anchor-button' href="<?=
                    $base_url ?>stop&channel=<?=$channel ?>" ><?=
                    tl('crawlstatus_view_stop_crawl') ?></a><?php
            }
            ?>
            </div>
            <div id='active-crawl-<?=$i ?>' <?=$class_none ?> >
            <?php
            if ( $crawl['CRAWL_TIME'] != $data['CURRENT_INDEX']) { ?>
               [<a <?=$target?> href="<?=$base_url ?>index&amp;timestamp=<?=
                    $crawl['CRAWL_TIME'] ?>"><?=
                    tl('crawlstatus_view_set_index') ?></a>]
            <?php
            } else { ?>
                [<?= tl('crawlstatus_view_search_index') ?>]
            <?php
            }
            ?>
            [<a <?=$target?> href="<?=$admin_url
            ?>a=manageCrawls&amp;arg=options&amp;<?=
            C\CSRF_TOKEN."=" . $data[C\CSRF_TOKEN] ?>&amp;ts=<?=
            $crawl['CRAWL_TIME'] ?>"><?=
            tl('crawlstatus_view_changeoptions') ?></a>]
            <?php
            if (isset($crawl['CRAWL_TIME'])) { ?>
                <p><b><?= tl('crawlstatus_view_timestamp') ?></b>
                <?= $crawl['CRAWL_TIME']  ?></p>
                <p><b><?= tl('crawlstatus_view_time_started') ?></b>
                <?= date("r",$crawl['CRAWL_TIME']) ?> </p>
            <?php
            } ?>
            <p><b><?= tl('crawlstatus_view_channel') ?></b><?= $channel ?></p>
            <?php if (isset($crawl['SCHEDULER_PEAK_MEMORY']) &&
                isset($crawl['QUEUE_PEAK_MEMORY'])) { ?>
                <p><b><?= tl('crawlstatus_view_indexer_memory') ?></b>
                <?= $crawl['QUEUE_PEAK_MEMORY'] ?></p>
                <p><b><?= tl('crawlstatus_view_scheduler_memory') ?></b>
                <?= $crawl['SCHEDULER_PEAK_MEMORY'] ?></p>
            <?php } else { ?>
                <p><b><?= tl('crawlstatus_view_queue_memory') ?></b>
                <?php
                if (isset($crawl['QUEUE_PEAK_MEMORY'])) {
                    e($crawl['QUEUE_PEAK_MEMORY']);
                } else {
                    e(tl('crawlstatus_view_no_mem_data'));
                } ?>
                </p>
            <?php } ?>
            <p><b><?= tl('crawlstatus_view_fetcher_memory') ?></b>
            <?php
            if (isset($crawl['FETCHER_PEAK_MEMORY'])) {
                e($crawl['FETCHER_PEAK_MEMORY']);
            } else {
                e(tl('crawlstatus_view_no_mem_data'));
            } ?>
            </p>
            <p><b><?= tl('crawlstatus_view_webapp_memory') ?></b>
            <?php
            if (isset($crawl['WEBAPP_PEAK_MEMORY'])) {
                e($crawl['WEBAPP_PEAK_MEMORY']);
            } else {
                e(tl('crawlstatus_view_no_mem_data'));
            } ?>
            </p>
            <p><b><?= tl('crawlstatus_view_urls_per_hour') ?></b> <?php
                if (isset($crawl['VISITED_URLS_COUNT_PER_HOUR'])) {
                    e(number_format($crawl['VISITED_URLS_COUNT_PER_HOUR'],
                        2, ".", ""));
                } else {
                    e("0.00");
                }
                ?></p>
            <p><b><?= tl('crawlstatus_view_visited_urls') ?></b> <?php
                if (isset($crawl['VISITED_URLS_COUNT'])) {
                    e($crawl['VISITED_URLS_COUNT']); } else {e("0");}
                ?></p>
            <p><b><?= tl('crawlstatus_view_total_urls') ?></b> <?php
                if (isset($crawl['COUNT'])) {
                    e($crawl['COUNT']);
                } else {
                    e("0");
                }
                ?></p>
            <?php if (!empty($crawl['QUERY_COUNT'])) { ?>
                <p><b><?= tl('crawlstatus_view_previous_visited') ?></b> <?php
                    if (isset($crawl['QUERY_VISITED_URLS_COUNT'])) {
                        e($crawl['QUERY_VISITED_URLS_COUNT']); } else {e("0");}
                    ?></p>
                <p><b><?= tl('crawlstatus_view_previous_total') ?></b> <?php
                    if (isset($crawl['QUERY_COUNT'])) {
                        e($crawl['QUERY_COUNT']);
                    } else {
                        e("0");
                    }
                    ?></p><?php
            } ?>
            <p><b><?= tl('crawlstatus_view_most_recent_fetcher') ?></b>
            <?php
            if (isset($crawl['MOST_RECENT_FETCHER'])) {
                e($crawl['MOST_RECENT_FETCHER']);
                if (isset($crawl['MOST_RECENT_TIMESTAMP'])) {
                    e(" @ ".date("r", $crawl['MOST_RECENT_TIMESTAMP']));
                }
            } else {
                e(tl('crawlstatus_view_no_fetcher'));
            }
            ?></p>
            <h2><?php e(tl('crawlstatus_view_most_recent_urls')); ?></h2>
            <?php
            if (isset($crawl['MOST_RECENT_URLS_SEEN']) &&
                count($crawl['MOST_RECENT_URLS_SEEN']) > 0) {
                e('<pre>');
                foreach ($crawl['MOST_RECENT_URLS_SEEN'] as $url) {
                    e(htmlentities(wordwrap($url, 60, "\n", true))."\n");
                }
                e('</pre>');
            } else {
                e("<p>".tl('crawlstatus_view_no_recent_urls')."</p>");
            }
            ?>
            </div></li>
            <?php
            $i++;
        }
        ?>
        </ol>
        <?php
    }
    /**
     * Draws the form used to start a new crawl
     * @param array $data containing CSRF_TOKEN field and other field used
     *  to draw this form
     */
    public function renderCrawlForm($data)
    {
        $target = (empty($_REQUEST['noscript'])) ? "" : " target='_parent' ";
        ?>
        <h2><?= tl('managecrawls_element_new_crawl') ?></h2>
        <form id="crawlStartForm" method="get" <?=$target?> >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageCrawls" />
        <input type="hidden" name="arg" value="start" />

        <p><label for="description-name"><?=
            tl('crawlstatus_view_description') ?></label>
            <input type="text" id="description-name" name="description"
                value="<?php
                if (isset($data['DESCRIPTION'])) {
                    e($data['DESCRIPTION']);
                } ?>" maxlength="<?=C\TITLE_LEN ?>" class="wide-field" />
            <button class="button-box" type="submit"><?=
                tl('crawlstatus_view_start') ?></button>
            <a <?=$target ?> href="?c=admin&amp;a=manageCrawls<?php
                ?>&amp;arg=options&amp;<?=
                C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] ?>"><?=
                tl('crawlstatus_view_options') ?></a><?=
                "&nbsp;" .$this->helper("helpbutton")->render(
                "New Crawl", $data[C\CSRF_TOKEN]) ?>
        </p>
        </form><?php
    }
}
