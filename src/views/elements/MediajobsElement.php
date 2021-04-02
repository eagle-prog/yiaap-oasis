<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2021 Chris Pollett chris@pollett.org
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
 * Element used to draw toggles indicating which jobs the Media Updater
 * will run and letting the user turn these jobs on/off.
 *
 * @author Chris Pollett
 */
class MediajobsElement extends Element
{
    /**
     * Draws interface to allow users to say which jobs will run in the
     * MediaUpdater. Also used to draw the nameserver/distribbuted mode toggle
     *
     * @param array $data with field containing the nonstatic values needed
     *  to draw this element
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $csrf_token = C\CSRF_TOKEN."=". $data[C\CSRF_TOKEN];
        $base_url = "{$admin_url}a=manageMachines&amp;$csrf_token&amp;arg=";
        $name_server_update = empty($data['MEDIA_MODE']) ||
            $data['MEDIA_MODE'] =='name_server';
        $update_mode_url = $base_url . "updatemode"; ?>
        <div class="current-activity">
        <div class="<?=$data['leftorright'] ?>">
        [<a href="<?=$base_url ?>"
        >X</a>]
        </div>
        <h2><?=tl('mediajobs_element_configure_media_jobs') . "&nbsp;" .
            $this->view->helper("helpbutton")->render(
            "Configure Media Jobs", $data[C\CSRF_TOKEN]) ?></h2>
        <div><b><?php
            e(tl('mediajobs_element_mode'));
        ?></b> [<?php
        if ($name_server_update) {
            ?><?= tl('mediajobs_element_nameserver');
            ?>|<a href="<?=$update_mode_url ?>"><?=
            tl('mediajobs_element_distributed')?></a><?php
        } else {
            ?><a href="<?=$update_mode_url ?>"><?=
            tl('mediajobs_element_nameserver');
            ?></a>|<?=
            tl('mediajobs_element_distributed');?><?php
        }
        ?>]</div>
        <h3><?=tl('mediajobs_element_jobs_list') ?></h3>
        <table class="admin-table">
            <tr><th><?=tl('mediajobs_element_job_name')?></th>
                <th><?=tl('mediajobs_element_run_status') ?></th></tr><?php
            foreach ($data["JOBS_LIST"] as $job_name => $enabled) {
                $selected = ($enabled) ? "enablejob&amp;job_name=$job_name" :
                    "disablejob&amp;job_name=$job_name";
                $options = [
                    "enablejob&amp;job_name=$job_name" =>
                        tl('mediajobs_element_on'),
                    "disablejob&amp;job_name=$job_name" =>
                        tl('mediajobs_element_off'),
                ]; ?>
                <tr><td ><?=$job_name ?></td><td><?=
                $this->view->helper('options')->renderLinkDropDown(
                    "job-toggle-" . $job_name, $options, $selected, $base_url
                );
                ?></td></tr><?php
            } ?>
        </table>
        </div><?php
    }
}
