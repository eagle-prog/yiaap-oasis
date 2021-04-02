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
 * Element responsible for displaying info about starting, stopping, deleting,
 * and using a crawl. It makes use of the CrawlStatusView
 *
 * @author Chris Pollett
 */
class ManagecrawlsElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  information about a crawl such as its description
     */
    public function render($data)
    {
        $admin_url = B\controllerUrl('admin', true);
        $status_url = $admin_url .
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&a=crawlStatus" .
            '&num_show=' . $data['NUM_SHOW'] . "&start_row=".
            $data['START_ROW'] . "&end_row=" . $data['END_ROW'];
        if (!empty($_REQUEST['crawlform'])) {
            $status_url .= "&crawlform=true";
        }
        ?>
        <div class="current-activity">
        <div id="crawlstatus" >
        <script>
        document.write('<h2 class="red"><?=
            tl('managecrawls_element_awaiting_status') ?></h2>');
        </script>
        <noscript>
        <iframe src="<?=str_replace("&", "&amp;", $status_url .
            "&noscript=true")?>" style="width:100%; height: 800px;">
        </iframe>
        </noscript>
        </div>
        <script>
        var updateId;
        function crawlStatusUpdate()
        {
            admin_form_object = elt('admin-form-row');
            if (admin_form_object &&
                admin_form_object.style.display == 'block') {
                return;
            }
            let start_url = '<?=$status_url ?>';
            start_url += "&crawl_toggle=";
            let crawl_tag = elt('crawlstatus');
            let i = 0;
            let active_crawl;
            let comma = "";
            while (active_crawl = elt('active-crawl-' + i)) {
                if (active_crawl.style.display != 'none') {
                    start_url += comma + "a" + i;
                    comma = ",";
                }
                i++;
            }
            getPage(crawl_tag, start_url);
        }
        function clearUpdate()
        {
             clearInterval(updateId );
             let crawlTag = elt('crawlstatus');
             crawlTag.innerHTML= "<h2 class='red'><?=
                tl('managecrawls_element_up_longer_update')?></h2>";
        }
        function doUpdate()
        {
             let sec = 1000;
             let minute = 60 * sec;
             crawlStatusUpdate();
             updateId = setInterval("crawlStatusUpdate()", 30*sec);
             setTimeout("clearUpdate()", 20*minute + sec);
        }
        </script>
        </div>
    <?php
    }
}
