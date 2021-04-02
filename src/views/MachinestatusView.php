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
 * the on/off state of the queue_servers and fetchers managed by
 * this instance of Yioop.
 *
 * @author Chris Pollett
 */
class MachinestatusView extends View
{
    /**
     * Instantiates a view for drawing the current status of media updater,
     * queue server, and fetchers in the  Yioop system
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
     * Draws the ManagestatusView to the output buffer
     *
     * @param array $data  contains on/off status info for each of the machines
     *     managed by this Yioop instance.
     */
    public function renderView($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $csrf_token = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
        $base_url = "{$admin_url}a=manageMachines&amp;$csrf_token&amp;arg=";
        $log_url = $base_url ."log&amp;name=NAME_SERVER&type=MediaUpdater".
            "&id=0";
        $on_media_updater = $base_url . "update&amp;action=start&amp;".
            "name=NAME_SERVER&amp;type=MediaUpdater&amp;id=0";
        $off_media_updater = $base_url ."update&amp;action=stop&amp;".
            "name=NAME_SERVER&amp;type=MediaUpdater&amp;id=0";
        $name_server_update = $data['MEDIA_MODE']=='name_server';
        $update_mode_url = $base_url . "updatemode";
        $caution = !isset($data['MACHINES']['NAME_SERVER']["MediaUpdater"])
            || $data['MACHINES']['NAME_SERVER']["MediaUpdater"] == 0;
        $target = (empty($_REQUEST['noscript'])) ? "" : " target='_parent' ";
        ?>
        <h1 class="slim"><?=tl('machinestatus_view_machine_statuses')?></h1>
        <div class="no-margin">&nbsp;[<a <?=$target?> href='<?= $admin_url .
            $csrf_token?>&amp;a=manageCrawls'><?=
            tl('machinestatus_view_manage_crawls') ?></a>]</div>
        <h2><?=tl('machinestatus_view_media_updater'). "&nbsp;" .
            $this->helper("helpbutton")->render(
            "Media Updater", $data[C\CSRF_TOKEN]) ?></h2>
        <div class="no-margin">&nbsp;[<a <?=$target
            ?> href="<?=$base_url . 'mediajobs'
            ?>"><?= tl('machinestatus_view_configure_media_jobs'); ?>]</a></div>
        <div class="box">
        <h3 class="no-margin"><?=tl('machinestatus_view_nameserver') ?></h3>
        <form id="media-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="mediamode" />
        <table class="machine-table"><tr>
        <th><?= tl('machinestatus_view_media_updater') ?></th>
        <td>[<a <?=$target?> href="<?= $log_url ?>"><?=
            tl('machinestatus_view_log') ?></a>]</td>
        <td><?php
            $this->helper("toggle")->render(
            ($data['MACHINES']['NAME_SERVER']["MEDIA_UPDATER_TURNED_ON"] == 1),
            $on_media_updater,
            $off_media_updater, $caution);?>
        </td>
        </tr></table>
        </form>
        </div>
        <?php
        if (count($data['MACHINES']) >= 1) {
        $data['TABLE_TITLE'] = tl('machinestatus_view_machines') .
             $this->helper("helpbutton")->render(
                 "Machine Information", $data[C\CSRF_TOKEN]);
            $data['ACTIVITY'] = 'manageMachines';
            $data['VIEW'] = $this;
            $data['TOGGLE_ID'] = 'add-machine-form';
            $data['FORM_TYPE'] = null;
            $data['NO_SEARCH'] = true;
            $data['NO_FLOAT_TABLE'] = false;
            $this->helper("pagingtable")->render($data);
        } ?>
        <div id='add-machine-form' class='box'>
        <h2><?= tl('machinestatus_view_add_machine') . "&nbsp;" .
            $this->helper("helpbutton")->render(
                "Manage Machines", $data[C\CSRF_TOKEN]) ?></h2>
        <form <?=$target ?> id="name-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="addmachine" />
        <table class="name-table">
        <tr><th><label for="machine-name"><?=
            tl('machinestatus_view_machine_name') ?></label></th>
            <td><input type="text" id="machine-name" name="name"
                maxlength="<?= C\NAME_LEN ?>" class="wide-field" /></td>
        </tr>
        <tr><th><label for="machine-url"><?=
            tl('machinestatus_view_machineurl')?></label></th>
            <td><input type="url" id="machine-url" name="url"
                maxlength="<?=C\MAX_URL_LEN ?>" class="wide-field" /></td></tr>
        <tr><th><label for="channel-type"><?=
            tl('machinestatus_view_machine_channel_type')?></label></th>
            <td><?= $this->helper("options")->render("channel-type",
            "channel", $data['CHANNELS'], $data['CHANNEL'],
            "toggleReplica()");
            ?></td></tr>
         <tr id="m1"><th><label for="parent-machine-name"><?=
            tl('machinestatus_view_parent_name')?></label></th>
            <td><?= $this->helper("options")->render(
                "parent-machine-name", "parent",
                $data['PARENT_MACHINES'], $data['PARENT']); ?></td></tr>
        <tr id="m2"><th><label for="fetcher-number"><?=
            tl('machinestatus_view_num_fetchers')?></label></th><td>
            <?php $this->helper("options")->render("fetcher-number",
            "num_fetchers", $data['FETCHER_NUMBERS'],$data['FETCHER_NUMBER']);
            ?></td></tr>
        <tr><th></th><td><button class="button-box" type="submit"><?=
            tl('machinestatus_view_save') ?></button></td>
        </tr>
        </table>
        </form>
        </div>
        <?php
        foreach ($data['MACHINES'] as $k => $m) {
            if (!is_numeric($k)) {
                continue;
            }
            ?>
            <div class="box">
            <div class="float-opposite" >[<a <?=$target?> href='<?=
                $base_url . "deletemachine&amp;name={$m['NAME']}"
                ?>' onclick='javascript:return confirm("<?=
                tl('machinestatus_view_confirm') ?>");' ><?=
                tl('machinestatus_view_delete') ?></a>]</div>
            <h3 class="no-margin"><?php e($m['NAME']); ?><span
                class='smaller-font'
                style="position:relative;top:-3px;font-weight: normal;"><?php
                if (empty($m['CHANNEL'])) { ?>
                    [<?=
                    tl('machinestatus_view_channel', $m['CHANNEL'])
                    ?>]<?php
                } else {?>
                    [<?=
                    tl('machinestatus_view_parent', $m['PARENT'])
                    ?>]<?php
                }?>[<?= $m['URL']?>]</span>
            </h3>
            <table class="machine-table">
            <?php
            $on_queue_server = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;channel={$m['CHANNEL']}&amp;type=QueueServer".
                "&amp;action=start";
            $off_queue_server = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;channel={$m['CHANNEL']}&amp;type=QueueServer".
                "&amp;action=stop";
            $on_mirror = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=Mirror&amp;action=start";
            $off_mirror = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=Mirror&amp;action=stop";
            $on_media_updater = $base_url . "update&amp;action=start&amp;".
                "name={$m['NAME']}&amp;type=MediaUpdater&amp;id=0";
            $off_media_updater = $base_url ."update&amp;action=stop&amp;".
                "name={$m['NAME']}&amp;type=MediaUpdater&amp;id=0";
            if (!empty($m['STATUSES']) &&
                $m['STATUSES'] == 'NOT_CONFIGURED_ERROR') {
                ?>
                </table>
                <span class='red'><?=
                    tl('machinestatus_view_not_configured') ?></span>
                </div>
                <?php
                continue;
            }
            if (!empty($m['PARENT'])) {
                    $log_url = $base_url . "log&name={$m['NAME']}".
                        "&type=Mirror&id=0";
                ?>
                <tr>
                <th><?= tl('machinestatus_view_mirror', $m['PARENT']) ?>
                    </th>
                <td><table><tr><td>#00[<a <?=$target
                    ?> href="<?php e($log_url);?>"><?=
                    tl('machinestatus_view_log') ?>]</td></tr><tr><td><?php
                    $caution = isset($m['STATUSES']['Mirror']) && (
                        !isset($m['STATUSES']['Mirror'][-1]) ||
                        !$m['STATUSES']['Mirror'][-1]);
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']['Mirror']),
                        $on_mirror, $off_mirror, $caution);
                ?></td></tr></table></td></tr>
                </table>
                </div><br /><?php
                continue;
            }
            if (isset($m['CHANNEL']) && intval($m['CHANNEL']) >= 0) {
                $log_url = $base_url . "log&name={$m['NAME']}".
                    "&channel={$m['CHANNEL']}";
                ?>
                <tr><th><?= tl('machinestatus_view_queue_server') ?>
                </th><td><table><tr><td>#00[<a <?=$target?> href="<?= $log_url .
                    "&type=QueueServer&id=0" ?>"><?=
                    tl('machinestatus_view_log') ?>]</a>
                    </td></tr><tr><td><?php
                    $caution =
                        isset($m['STATUSES']["QueueServer"][$m['CHANNEL']]) &&
                        !$m['STATUSES']["QueueServer"][$m['CHANNEL']];
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']["QueueServer"][$m['CHANNEL']]),
                        $on_queue_server, $off_queue_server, $caution);
                ?></td></tr></table></td>
                <?php
            } else {
                ?>
                <tr><th><?= tl('machinestatus_view_queue_server')
                ?></th><td style="width:100px;"><?php
                e(tl('machinestatus_view_no_queue_server'));
                ?></td>
                <?php
            }
            if (!$name_server_update) {
                $colspan = " colspan='2' ";
                if ($_SERVER["MOBILE"]) {
                    e('</tr><tr>');
                    $colspan = "";
                }
                ?>
                <th <?=$colspan ?>><?=tl('machinestatus_view_media_updater') ?>
                </th><td><table><tr><td>#00[<a <?=$target?> href="<?= $log_url .
                    "&type=MediaUpdater&id=0" ?>"><?=
                    tl('machinestatus_view_log')?>]</a>
                    </td></tr><tr><td><?php
                    $caution = isset($m['STATUSES']["MediaUpdater"]) && (
                        !isset($m['STATUSES']["MediaUpdater"][-1]) ||
                        !$m['STATUSES']["MediaUpdater"][-1]);
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']["MediaUpdater"]),
                        $on_media_updater, $off_media_updater, $caution);
                ?></td></tr></table></td>
                <?php
            }
            ?>
            </tr>
            <?php
            if (!$_SERVER["MOBILE"]) {
                ?>
                <tr class="machine-table-hr"><td class="machine-table-hr"
                    colspan="10"><hr/></td></tr>
                <?php
            }
            if (empty($m['NUM_FETCHERS'])) {
                e("<tr class='border-top'><td colspan='10'><h3>".
                    tl('machinestatus_view_no_fetchers')."</h3></td></tr>");
            } else {
                $machine_wrap_number = ($_SERVER["MOBILE"]) ? 2 : 4;
                for ($i = 0; $i < $m['NUM_FETCHERS']; $i++) {
                    $on_fetcher = $base_url . "update&amp;name={$m['NAME']}" .
                        "&amp;action=start&amp;type=Fetcher&amp;id=$i" .
                        "&amp;channel={$m['CHANNEL']}";
                    $off_fetcher = $base_url . "update&amp;name={$m['NAME']}".
                        "&amp;action=stop&amp;type=Fetcher&amp;id=$i" .
                        "&amp;channel={$m['CHANNEL']}";
                    if ($i  == 0) { ?>
                        <tr><th rowspan="<?=
                            ceil($m['NUM_FETCHERS'] / $machine_wrap_number);
                            ?>"><?=
                            tl('machinestatus_view_fetchers') ?></th><?php
                    }
                    ?><td><table><tr><td>#<?php
                    $log_url = $base_url .
                        "log&amp;name={$m['NAME']}&amp;type=Fetcher&id=$i" .
                        "&amp;channel={$m['CHANNEL']}";
                    if ($i < 10){e("0");} e($i);
                    ?>[<a <?=$target?> href="<?= $log_url ?>"><?=
                        tl('machinestatus_view_log') ?></a>]</td>
                    </tr><tr><td><?php
                    $toggle = false;
                    $caution = false;
                    if (isset($m['STATUSES']["Fetcher"][$i])) {
                        $toggle = true;
                        $caution = ($m['STATUSES']["Fetcher"][$i] == 0);
                    }
                    $this->helper("toggle")->render(
                        $toggle, $on_fetcher, $off_fetcher, $caution);?></td>
                    </tr>
                    </table></td><?php
                    if ($i % $machine_wrap_number ==
                        ($machine_wrap_number - 1) % $machine_wrap_number){
                        ?>
                        </tr><tr>
                        <?php
                    }
                }
                ?></tr><?php
            }
        ?></table></div><br /><?php
        }
    }
}
