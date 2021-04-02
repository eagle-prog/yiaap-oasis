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
 * Element responsible for displaying the user account features
 * that someone can modify for their own SeekQuarry/Yioop account.
 *
 * @author Chris Pollett
 */
class ManageaccountElement extends Element
{
    /**
     * Draws a view with a summary of a user's account together with
     * a form for updating user info such as password as well as with
     * useful links for groups, etc
     *
     * @param array $data anti-CSRF token
     */
    public function render($data)
    {
        $token = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $feed_url =  htmlentities(B\feedsUrl("", "",
            true, "group")). "$token";
        $group_url = "{$admin_url}a=manageGroups&amp;$token";
        $mix_url = "{$admin_url}a=mixCrawls&amp;$token";
        $crawls_url = "{$admin_url}a=manageCrawls&amp;$token";
        $machines_url = "{$admin_url}a=manageMachines&amp;$token";
        $base_url = "{$admin_url}a=manageAccount&amp;$token";
        $querystats_url = $crawls_url . "&amp;arg=querystats";
        $edit_or_no_url = $base_url . (
            (isset($data['EDIT_USER'])) ? "&amp;edit=false":"&amp;edit=true");
        $edit_or_no_text = tl('manageaccount_element_edit_or_no_text');
        $edit_or_no_img = C\SHORT_BASE_URL . ((isset($data['EDIT_USER'])) ?
            "resources/unlocked.png" : "resources/locked.png");
        $password_or_no_url = $base_url .(
            (isset($data['EDIT_PASSWORD'])) ? "&amp;edit_pass=false":
            "&amp;edit_pass=true");
        $disabled = (isset($data['EDIT_USER'])) ? "" : "disabled='disabled'";
        $span_icon = 9 + ((!empty($data['RECOVERY'])) ?
            count($data['RECOVERY']): 0);
        ?>
        <div class="current-activity">
            <h2><?= tl('manageaccount_element_welcome',
                $data['USERNAME']) ?></h2>
            <p><?= tl('manageaccount_element_what_can_do') ?></p>
            <h2><?=tl('manageaccount_element_account_details')
                ?><span class='smaller-font'><a href="<?=$edit_or_no_url ?>"
                style="position:relative; top:3px;" ><img src="<?=
                $edit_or_no_img?>" title='<?=$edit_or_no_text ?>' alt='<?=
                $edit_or_no_text ?>' /></a>
            </span></h2>
            <form id="changeUserForm" method="post"
                autocomplete="off" enctype="multipart/form-data"><?php
            $row_col_span = 'rowspan="4"';
            if (isset($data['EDIT_USER'])) {
                $row_col_span = 'rowspan="7"';
                if (isset($data['EDIT_PASSWORD'])) {
                    $row_col_span = 'rowspan="12"';
                }
                if (!empty($data['USER']['IS_BOT_USER'])) {
                    $row_col_span = 'rowspan="17"';
                }
                if ($_SERVER["MOBILE"]) {
                    $row_col_span = 'class="center" style="width:300px"';
                }
            }
            ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="manageAccount" />
            <input type="hidden" name="arg" value="updateuser" />

            <table class="name-table">
            <tr>
            <td <?=$row_col_span?> class="user-icon-td" ><img
                class='user-icon' id='current-icon'
                src="<?= $data['USER']['USER_ICON'] ?>" alt="<?=
                    tl('manageaccount_element_icon') ?>" /><?php
                if (isset($data['EDIT_USER'])) {
                    ?>
                    <?php
                    $this->view->helper("fileupload")->render('current-icon',
                        'user_icon', 'user-icon',  C\THUMB_SIZE, 'image',
                        ['image/png', 'image/gif', 'image/jpeg']);
                    if ($_SERVER["MOBILE"] ) { ?>
                        </td></tr></table><table class="name-table">
                        <?php
                    } else {
                        e('</td>');
                    }
                } else {
                    e('</td>');
                }
                ?>
            <th class="table-label"><label for="user-name"><?=
                tl('manageaccount_element_username') ?>:</label></th>
                <td><input type="text" id="user-name"
                    name="user_name"  maxlength="<?= C\NAME_LEN ?>"
                    value="<?= $data['USER']['USER_NAME'] ?>"
                    class="narrow-field" disabled="disabled" /></td>
                    </tr>
            <tr><th class="table-label"><label for="first-name"><?php
                    e(tl('manageaccount_element_firstname')); ?>:</label></th>
                <td><input type="text" id="first-name"
                    name="FIRST_NAME"  maxlength="<?= C\NAME_LEN?>"
                    value="<?php e($data['USER']['FIRST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="last-name"><?php
                    e(tl('manageaccount_element_lastname')); ?>:</label></th>
                <td><input type="text" id="last-name"
                    name="LAST_NAME"  maxlength="<?= C\NAME_LEN ?>"
                    value="<?php e($data['USER']['LAST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="e-mail"><?php
                    e(tl('manageaccount_element_email')); ?>:</label></th>
                <td><input type="email" id="e-mail"
                    name="EMAIL"  maxlength="<?= C\LONG_NAME_LEN ?>"
                    <?php e($disabled);?>
                    value="<?php e($data['USER']['EMAIL']); ?>"
                    class="narrow-field"/></td></tr>
            <?php
            if (isset($data['EDIT_USER'])) {?>
                <tr>
                <th class="table-label"><label for="locale"><b><?=
                    tl('options_element_language_label')
                    ?></b></label></th>
                <td><?php
                    $this->view->element("language")->render($data); ?></td>
                </tr><?php
                if (!empty($data['yioop_bot_configuration'])) {
                    ?>
                    <tr>
                    <th class="table-label"><label for="is_bot"><?php
                            e(tl('manageaccount_element_is_bot'));
                            ?></label></th>
                    <td><input type="checkbox" id="is_bot"
                               name="IS_BOT_USER" value="true"
                            <?php if (!empty($data['USER']['IS_BOT_USER'])
                                && $data['USER']['IS_BOT_USER'] == true) {
                                    e("checked='checked'");
                            } ?>
                        />
                    </td></tr><?php
                    if ($data['USER']['IS_BOT_USER'] == true) { ?>
                        <tr><th class="table-label">
                            <label for="bot-unique-token"><?php
                            e(tl('manageaccount_element_bot_unique_token'))
                            ?></label></th>
                            <td><input type="text" id="bot-unique-token"
                                name="BOT_TOKEN" value="<?php
                                e($data['USER']['BOT_TOKEN']); ?>"
                                class="narrow-field" />
                            </td>
                        </tr>
                        <tr>
                            <th class="table-label">
                            <label for="bot-callback-url"><?php
                            e(tl('manageaccount_element_bot_callback_url'));
                            ?></label></th>
                            <td><input type="text" id="bot-callback-url"
                                name="BOT_CALLBACK_URL" value="<?php
                                e($data['USER']['CALLBACK_URL']); ?>"
                                class="narrow-field"/>
                            </td>
                        </tr>
                        <?php
                    }
                }?>
                <tr><th class="table-label"><label for="password"><a href="<?php
                e($password_or_no_url);?>"><?php
                e(tl('manageaccount_element_password'))?></a></label></th>
                <td><input type="password" id="password"
                    name="password"  maxlength="<?= C\LONG_NAME_LEN
                    ?>" class="narrow-field"/>
                </td></tr>
                <?php if (isset($data['EDIT_PASSWORD'])) { ?>
                <tr><th class="table-label"><label for="new-password"><?php
                    e(tl('manageaccount_element_new_password'))?></label></th>
                    <td><input type="password" id="new-password"
                        name="new_password"  maxlength="<?=
                        C\LONG_NAME_LEN?>" class="narrow-field"/>
                    </td></tr>
                <tr><th class="table-label"><label for="retype-password"><?php
                    e(tl('manageaccount_element_retype_password'));
                    ?></label></th>
                    <td><input type="password" id="retype-password"
                        name="retype_password"  maxlength="<?=
                        C\LONG_NAME_LEN?>" class="narrow-field" />
                    </td></tr>
                    <?php
                    $question_sets = [];
                    if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
                        $question_sets = [
                            tl('manageaccount_element_new_recovery_qa') =>
                            $data['RECOVERY']];
                    }
                    $i = 0;
                    foreach ($question_sets as $name => $set) {
                        $first = true;
                        $num = count($set);
                        foreach ($set as $question) {
                            if ($first) { ?>
                                <tr><th class="table-label"
                                    rowspan='<?= $num
                                    ?>' style="max-width:2in;"><?php
                                    e($name);
                                ?></th><td class="table-input border-top">
                            <?php
                            } else { ?>
                                <tr><td class="table-input">
                            <?php
                            }
                            $this->view->helper("options")->render(
                                "question-$i", "question_$i",
                                $question, $data['RECOVERY_ANSWERS'][$i]);
                            $first = false;
                            e("</td></tr>");
                            $i++;
                        }
                    }
                }
                ?>
                <tr><td></td>
                <td class="center"><button
                    class="button-box" type="submit"><?php
                    e(tl('manageaccount_element_save')); ?></button></td></tr>
                <?php
            } ?>
            </table>
            </form>
            <h2><?=tl('manageaccount_element_groups_and_feeds')?></h2>
            <p><?= tl('manageaccount_element_group_info') ?><?php
            if ($data['NUM_GROUPS'] > 1 || $data['NUM_GROUPS'] == 0) {
                e(tl('manageaccount_element_num_groups',
                    $data['NUM_GROUPS']));
            } else {
                e(tl('manageaccount_element_num_group',
                    $data['NUM_GROUPS']));
            }?></p>
            <?php
            $pre_manage_group_url = htmlentities(B\controllerUrl("admin", true))
                . "$token&amp;a=manageGroups&amp;arg=editgroup&amp;group_id=";
            $is_root = $_SESSION['USER_ID'] == C\ROOT_ID;
            foreach ($data['GROUPS'] as $group) {
                $manage_group = "";
                $is_owner = $group['OWNER_ID'] == $_SESSION['USER_ID'];
                if ($is_owner || $is_root ) {
                    $statistics_url = $group_url . "&amp;arg=statistics&amp;" .
                        'group_id='. $group['GROUP_ID'].'&amp;user_id=' .
                         $_SESSION['USER_ID'];
                    if ($is_owner) {
                        $manage_group = "|<a href='". $pre_manage_group_url .
                            $group['GROUP_ID'] . "'>" .
                            tl('manageaccount_element_manage')."</a>";
                    }
                    $statistics_link = ", <a href='$statistics_url'>" .
                        tl('manageaccount_element_statistics') . "</a>";
                } else {
                    $statistics_link = "";
                }
                ?>
                <div class="access-result">
                    <div><b><a href="<?=htmlentities(B\feedsUrl("group",
                    $group['GROUP_ID'], true, "group")) . $token ?>"
                    rel="nofollow"><?=$group['GROUP_NAME']
                    ?></a> [<a href="<?=htmlentities(B\wikiUrl("Main", true,
                        "group", $group['GROUP_ID'])) .
                        $token ?>"><?=
                        tl('manageaccount_element_group_wiki')?></a><?=
                        $manage_group; ?>] (<?=
                        tl('manageaccount_element_group_stats',
                        $group['NUM_POSTS'], $group['NUM_THREADS']) .
                        $statistics_link ?>)</b>
                    </div>
                    <div class="slight-pad">
                    <b><?=tl('manageaccount_element_last_post') ?></b><?php
                    if ($group['THREAD_ID'] >= 0 || $group['NUM_THREADS'] > 0) {
                        e("<a href=\"" . B\feedsUrl("thread",
                            $group['THREAD_ID'], true, "admin") .
                            $token . "\">" . $group['ITEM_TITLE'] .
                            "</a>");
                    } else {
                        e($group['ITEM_TITLE']);
                    }?>
                    </div>
                </div>
                <?php
            }
            if (!empty($data['THREAD_RECOMMENDATIONS']) ||
                !empty($data['GROUP_RECOMMENDATIONS'])) {
                ?>
                <h2><?=tl('manageaccount_element_recommendations')?></h2>
                <div class="access-result">
                <?php
                if (!empty($data['THREAD_RECOMMENDATIONS'])) {
                    ?><b><?=tl('manageaccount_element_rec_threads')?></b><?php
                    foreach ($data['THREAD_RECOMMENDATIONS'] as
                        $thread => $threadName) { ?>
                        <a href="<?=htmlentities(B\feedsUrl("thread",
                            $thread, true, "group")) .
                                $token ?>" ><?= $threadName ?></a>
                        <?php
                    }
                }
                ?><br /><?php
                if (!empty($data['GROUP_RECOMMENDATIONS'])) {
                    ?><b><?=tl('manageaccount_element_rec_groups') ?></b><?php
                    foreach ($data['GROUP_RECOMMENDATIONS'] as
                        $group_id => $group_name) { ?>
                        <a href="<?= htmlentities(B\feedsUrl("group",
                            $group_id, true, "group")) .
                            $token ?>" ><?= $group_name ?></a>
                        <?php
                    }
                }
                ?></div><?php
            }?>
            <p>[<a href="<?=$feed_url ."&amp;v=ungrouped" ?>"><?=
            tl('manageaccount_element_go_to_combined_feed') ?></a>]
            [<a href="<?=$feed_url ?>"><?=
            tl('manageaccount_element_go_to_groups') ?></a>]
            [<a href="<?= $group_url ?>"><?=
                tl('manageaccount_element_manage_groups')
                ?></a>]
            [<a href="<?= $group_url . "&amp;browse=true" ?>"><?=
                tl('manageaccount_element_join_groups')
                ?></a>]
            </p><?php
            if (isset($data['CRAWL_MANAGER']) && $data['CRAWL_MANAGER']) {
                ?>
                <h2><?php
                e(tl('manageaccount_element_crawl_and_index')); ?></h2>
                <p><?php e(tl('manageaccount_element_crawl_info')); ?></p>
                <p><?php e(tl('manageaccount_element_num_crawls',
                    $data["CRAWLS_RUNNING"], $data["NUM_CLOSED_CRAWLS"]));?></p>
                <p>[<a href="<?php e($crawls_url); ?>"><?php
                    e(tl('manageaccount_element_manage_crawls'));
                    ?></a>] [<a href="<?php e($querystats_url); ?>"><?php
                    e(tl('manageaccount_element_query_statistics'));
                    ?></a>] [<a href="<?php e($machines_url); ?>"><?php
                    e(tl('manageaccount_element_manage_machines'));
                    ?></a>]</p>
                <?php
            }
            if (in_array("mixCrawls",
                array_column($data['ACTIVITIES'],"METHOD_NAME"))) { ?>
                <h2><?=tl('manageaccount_element_crawl_mixes') ?></h2>
                <p><?=tl('manageaccount_element_mixes_info') ?></p>
                <p><?php
                if ($data['NUM_MIXES'] > 1 || $data['NUM_MIXES'] == 0) {
                    e(tl('manageaccount_element_num_mixes',
                        $data['NUM_MIXES']));
                } else {
                    e(tl('manageaccount_element_num_mix',
                        $data['NUM_MIXES']));
                } ?></p>
                <p>[<a href="<?= $mix_url ?>"><?=
                    tl('manageaccount_element_manage_mixes')
                    ?></a>]</p><?php
            } ?>
        </div>
        <?php
    }
}
