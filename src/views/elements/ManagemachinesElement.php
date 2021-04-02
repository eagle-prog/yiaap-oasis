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
 * Used to draw the admin screen on which admin users can add/delete
 * and manage machines which might act as fetchers or queue_servers.
 * The managing protion of this element is actually done via an ajax
 * call of the MachinestatusView
 *
 * @author Chris Pollett
 */
class ManagemachinesElement extends Element
{
    /**
     * Draws the ManageMachines element to the output buffer
     *
     * @param array $data  contains antiCSRF token, as well as data for
     * the select fetcher number element.
     */
    public function render($data)
    {
        $admin_url = B\controllerUrl('admin', true);
        $status_url = $admin_url .
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&a=machineStatus" .
            '&num_show=' . $data['NUM_SHOW'] . "&start_row=".
            $data['START_ROW'] . "&end_row=" . $data['END_ROW'];
        ?>
        <div class="current-activity">
        <div id="machinestatus" >
        <script>
        document.write('<h2 class="red"><?=
            tl('managemachines_element_awaiting_status') ?></h2>');
        </script>
        <noscript>
        <iframe src="<?=str_replace('&', '&amp;', $status_url .
            '&noscript=true') ?>" style="width:100%; height: 800px">
        </iframe>
        </noscript>
        </div>
        <script>
        var updateId;
        var first_update = true;
        function machineStatusUpdate()
        {
            admin_form_object = elt('add-machine-form');
            if (admin_form_object &&
                admin_form_object.style.display == 'block') {
                return;
            }
            var startUrl = "<?= $status_url ?>";
            var machineTag = elt('machinestatus');
            first_update = false;
            getPage(machineTag, startUrl, initAddMachineForm);
        }
        function initAddMachineForm()
        {
            setDisplay('add-machine-form', false);
            toggleReplica();
        }
        function clearUpdate()
        {
             clearInterval(updateId );
             var machineTag = elt('machinestatus');
             machineTag.innerHTML= "<h2 class='red'><?=
                tl('managemachines_element_no_longer_update')?></h2>";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60 * sec;
             machineStatusUpdate();
             updateId = setInterval("machineStatusUpdate()", 30*sec);
             setTimeout("clearUpdate()", 20 * minute + sec);
        }
        function toggleReplica()
        {
            var channel = elt('channel-type')
            if (channel.value == -1) {
                m1_value = "table-row";
                m2_value = "none";
            } else {
                m1_value = "none";
                m2_value = "table-row";
            }
            setDisplay('m1', m1_value);
            setDisplay('m2', m2_value);
            return false;
        }
        </script>
        </div>
    <?php
    }
}
