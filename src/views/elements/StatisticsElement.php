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
 * Draws an element displaying statistical information about a
 * web crawl such as number of hosts visited, distribution of
 * file sizes, distribution of file type, distribution of languages, etc
 *
 * @author Chris Pollett
 */
class StatisticsElement extends Element
{
    /**
     * Draws the activity used to display statistics about a crawl
     *
     * @param array $data   contains anti CSRF token as well
     *     statistics info about a web crawl
     */
    public function render($data)
    {
        $base_url = C\SHORT_BASE_URL;
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $delim = "?";
        ?>
        <div class="current-activity">
        <div class="<?= $data['leftorright'] ?>">
        [<a href="<?=$admin_url ?>a=manageCrawls&amp;<?=
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"  >X</a>]
        </div>
        <h2><?= tl("statistics_element_crawl_stats") ?><?php
        if (!empty($data['HAS_STATISTICS'])) {
        ?>
        <span class='no-bold medium-large'>[<a href="<?=$admin_url
            ?>a=manageCrawls&amp;arg=statistics&amp;recompute=true&amp;its=<?=
            $data['its']."&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"  ><?=
            tl('statistics_element_recompute_stats') ?></a>]</span>
        <?php
        }
        ?>
        </h2><?php
        foreach ($data['GENERAL_STATS'] as $stat_name => $stat_value) {
            ?>
            <p><b><?=$stat_name?></b> <?=$stat_value ?></p><?php
        }
        if (empty($data['HAS_STATISTICS'])) {
            if (empty($data["STATISTICS_SCHEDULED"])) {
                e("<p class='green'>".
                    tl("statistics_element_stats_scheduled") . "</p>");
            } else {
                e("<p class='green'>".
                    tl("statistics_element_already_scheduled") . "</p>");
            }
            e("</div>");
            return;
        }
        foreach ($data["STAT_HEADINGS"] as $heading => $group_name) {
            if (isset($data[$group_name]["TOTAL"])) { ?>
                <h2><?= $heading ?></h2>
                <table summary= "<?=$heading ?> TABLE" class="box">
                <?php
                $total = $data[$group_name]["TOTAL"];
                $lower_name = strtolower($group_name);
                $data[$group_name]["DATA"] = array_filter(
                    $data[$group_name]["DATA"]);
                foreach ($data[$group_name]["DATA"] as $name => $value) {
                    $width = round(500 * $value / (max($total, 1)));
                    e("<tr><th><a href='".$base_url . $delim .
                        "q=$lower_name:$name i:{$data['its']}' " .
                        "rel='nofollow'>$name</a></th>".
                        "<td><div style='background-color:green;".
                            "width:{$width}px;' >$value</div>".
                        " </td></tr>");
                }
                ?>
                </table>
            <?php
            }
        } ?>
        </div>
        <?php
    }
}
