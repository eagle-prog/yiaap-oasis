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
 * Element responsible for drawing the activity screen for User manipulation
 * in the AdminView.
 *
 * @author Chris Pollett
 */
class ManageusersElement extends Element
{
    /**
     * Draws a screen in which an admin can add users, delete users,
     * and manipulate user roles.
     *
     * @param array $data info about current users and current roles, CSRF token
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
        <?= $this->renderUserTable($data); ?>
        <script>
        function submitViewUserRole()
        {
            elt('viewUserRoleForm').submit();
        }
        </script>
        </div>
        <?php
    }
    /**
     * Draws the table that displays the users and their properties for
     * the Yioop system
     * @param array $data info about current users and current roles, CSRF token
     */
    public function renderUserTable($data)
    {
        $data['TABLE_TITLE'] = tl('manageusers_element_users');
        if ($data['FORM_TYPE'] == "edituser") {
            $data['NO_TITLE'] = true;
        }
        $data['ACTIVITY'] = 'manageUsers';
        $data['VIEW'] = $this->view; ?>
        <div id='admin-info' ><?php
        $default_accounts = [C\ROOT_ID, C\PUBLIC_USER_ID];
        $num_columns = $_SERVER["MOBILE"] ? 4 : 8;
        if (in_array($data['FORM_TYPE'], ['edituser', 'search'])) {
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        $base_url = htmlentities(B\controllerUrl('admin', true)) .
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&amp;a=manageUsers";
        if (isset($data['START_ROW'])) {
            $base_url .= "&amp;start_row=".$data['START_ROW'].
                "&amp;end_row=".$data['END_ROW'].
                "&amp;num_show=".$data['NUM_SHOW'];
        }
        $context = "";
        $arg_context= "";
        if ($data['FORM_TYPE'] == 'search' ||
            !empty($data['context']) && $data['context'] == 'search') {
            $context = 'context=search';
            $arg_context= "&amp;arg=search";
        }
        if ($data['FORM_TYPE'] == 'edituser') {
            $this->view->helper("close")->render($base_url . $arg_context);
            $this->renderUserForm($data);
            return;
        }
        ?>
        <table class="admin-table">
            <tr><td class="no-border" colspan="<?=
                $num_columns ?>"><?php $this->view->helper(
                "pagingtable")->render($data);
                if ($data['FORM_TYPE'] != "edituser") { ?>
                    <div id='admin-form-row' class='admin-form-row'><?php
                    if ($data['FORM_TYPE'] == "search") {
                        $this->renderSearchForm($data);
                    } else {
                        $this->renderUserForm($data);
                    }?>
                    </div><?php
                } ?></td>
            </tr>
            <tr>
                <th><?= tl('manageusers_element_username') ?></th><?php
                if (!$_SERVER["MOBILE"]) { ?>
                    <th><?= tl('manageusers_element_firstname')?></th>
                    <th><?= tl('manageusers_element_lastname') ?></th>
                    <th><?= tl('manageusers_element_email') ?></th>
                    <th><?= tl('manageusers_element_groups') ?></th><?php
                } ?>
                <th><?= tl('manageusers_element_status') ?></th>
                <th colspan='2'><?= tl('manageusers_element_actions') ?></th>
            </tr><?php
            $delete_url = $base_url . "&amp;arg=deleteuser&amp;$context";
            $edit_url = $base_url . "&amp;arg=edituser&amp;$context";
            $mobile_columns = ['USER_NAME', 'STATUS'];
            $stretch = ($_SERVER["MOBILE"]) ? 1 : 2;
            $out_columns = ['USER_NAME', 'FIRST_NAME', 'LAST_NAME',
                'EMAIL', 'NUM_GROUPS', 'STATUS'];
            foreach ($data['USERS'] as $user) { ?>
                <tr><?php
                foreach ($out_columns as $colname) {
                    $user_column = $user[$colname];
                    if ($colname == "USER_ID" || ($_SERVER["MOBILE"] &&
                        !in_array($colname, $mobile_columns))) {
                        continue;
                    }
                    if (strcmp($colname, "STATUS") == 0) {
                        ?><td><?=$data['STATUS_CODES'][$user['STATUS']] ?>
                        </td><?php
                    } else {?>
                        <td><?=$user_column ?></td><?php
                    }
                }
                if ($user['USER_ID'] == C\PUBLIC_USER_ID) {
                    e("<td><span class='gray'>".
                        tl('manageusers_element_edit').'</span></td>');
                } else {
                    ?>
                    <td><a href="<?php e($edit_url .
                        '&amp;user_name=' . $user['USER_NAME']); ?>"><?=
                    tl('manageusers_element_edit') ?></a></td><?php
                }?>
                <td><?php
                    if (in_array($user['USER_ID'], $default_accounts)) {
                        e('<span class="gray">'.
                            tl('manageusers_element_delete').'</span>');
                    } else {
                    ?>
                        <a onclick='javascript:return confirm("<?=
                        tl('manageusers_element_confirm_delete') ?>");'
                        href="<?= $delete_url . '&amp;user_name='.
                        $user['USER_NAME'] ?>"><?php
                        e(tl('manageusers_element_delete')); ?>
                        </a><?php
                    }?></td>
                </tr><?php
            } ?>
        </table>
        </div><?php
    }
    /**
     * Draws the add user and edit user forms
     *
     * @param array $data consists of values of user fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderUserForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url .
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]. "&amp;a=manageUsers&amp;";
        $visibles = "visible_roles=".$data['visible_roles'].
            "&amp;visible_groups=".$data['visible_groups'];
        $limits = isset($data['ROLE_LIMIT']) ?
            "role_limit=".$data['ROLE_LIMIT']: "role_limit=0";
        $limits .= isset($data['GROUP_LIMIT']) ?
            "&amp;group_limit=".$data['GROUP_LIMIT']: "&amp;group_limit=0";
        $filters = isset($data['ROLE_FILTER']) ?
            "role_filter=".$data['ROLE_FILTER'] : "";
        $f_amp = isset($data['ROLE_FILTER']) ? "&amp;" : "";
        $filters .= isset($data['GROUP_FILTER']) ?
            "$f_amp;group_filter=".$data['GROUP_FILTER'] : "";
        $base_url .= $visibles;
        $edituser = ($data['FORM_TYPE'] == "edituser") ? true: false;
        ?>
        <form id="admin-form" method="post" autocomplete="off" ><?php
        if ($edituser) {
            e("<h2>".tl('manageusers_element_user_info'). "</h2>");
        } else {
            e("<h2>".tl('manageusers_element_add_user'). "</h2>");
        }
        ?>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="<?=
            $data['FORM_TYPE'] ?>" />
        <input type="hidden" id="visible-roles" name="visible_roles"
            value="<?= $data['visible_roles'] ?>" />
        <input type="hidden" id="visible-groups" name="visible_groups"
            value="<?= $data['visible_groups'] ?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="user-name"><?=
            tl('manageusers_element_username')?>:</label></th>
            <td><input type="text" id="user-name"
                name="user_name"  maxlength="<?= C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['user_name'] ?>"
                class="narrow-field" <?php
                if ($edituser) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <tr><th class="table-label"><label for="first-name"><?=
            tl('manageusers_element_firstname') ?>:</label></th>
            <td><input type="text" id="first-name"
                name="first_name"  maxlength="<?= C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['first_name'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="last-name"><?=
            tl('manageusers_element_lastname') ?>:</label></th>
            <td><input type="text" id="last-name"
                name="last_name"  maxlength="<?=C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['last_name'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="e-mail"><?=
            tl('manageusers_element_email') ?>:</label></th>
            <td><input type="email" id="e-mail"
                name="email"  maxlength="<?= C\LONG_NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['email'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label
                for="update-userstatus-currentuser"><?=
                tl('manageusers_element_status') ?>:</label></th>
            <td><?php
                if ($data['CURRENT_USER']['user_name'] == 'root') {
                    e("<div class='light-gray-box'><span class='gray'>".
                        $data['STATUS_CODES'][$data['CURRENT_USER']['status']].
                        "</span></div><input type='hidden' name='status' ".
                        "value='".$data['CURRENT_USER']['status']."' />");
                } else {
                    $this->view->helper("options")->render(
                        "update-userstatus-currentuser",
                        "status", $data['STATUS_CODES'],
                        $data['CURRENT_USER']['status']);
                } ?></td></tr>
        <?php
        if ($edituser) {
            $context = "";
            if (!empty($data['context']) && $data['context'] == 'search') {
                $context = 'context=search';
            }
            ?>
            <tr><th class="table-label" style="vertical-align:top"><?=
            tl('manageusers_element_roles') ?>:</th>
                <td><div class='light-gray-box'>
                <div class="center">
                    [<button class="button-anchor"
                        form="admin-form" type='submit' name='visible_roles'
                        value='<?= ($data['visible_roles'] == 'true') ?
                            "false" : "true" ?>'><?=
                            tl('manageusers_element_num_roles',
                            $data['NUM_USER_ROLES'])?></button>]<?php
                        if ($data['visible_roles'] == 'true') {
                            $context = "";
                            if (!empty($data['context'])) {
                                $context = 'context=search';
                            }
                            $sort_string = urlencode(json_encode(
                                $data['ROLE_SORTS']));
                            $action_url = "$base_url&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;role_filter=" . $data['ROLE_FILTER'] .
                                "&amp;role_sorts=" . $sort_string;
                            $with_selected_actions = [
                                -1 => tl('manageusers_element_with_selected'),
                                $action_url . "&amp;arg=deleteuserrole&amp;" =>
                                    tl('manageusers_element_delete'),
                            ];
                            $this->view->helper("options")->render(
                                "with-selected-user-roles",
                                "with_selected_roles",
                                $with_selected_actions, -1, '
                                    var ids_string = arrayIntoFormVariable(
                                        role_ids, "role_ids");
                                    window.location =
                                        this[this.selectedIndex].value +
                                        ids_string;
                                ', ["aria-label" => $with_selected_actions[-1]]);
                        }?>
                </div>
                <?php
                if ($data['visible_roles'] == 'true') {
                    $action_url = $base_url. "&amp;arg=edituser&amp;$context".
                        "&amp;user_name=" . $data['CURRENT_USER']['user_name'].
                        "&amp;role_filter=".$data['ROLE_FILTER'].
                        "&amp;group_filter=".$data['GROUP_FILTER'];
                    $sort_urls = ['ROLE_NAME' => ''];
                    foreach ($sort_urls as $field => $url) {
                        $role_sorts = $data['ROLE_SORTS'];
                        $sort_dir = (empty($role_sorts[$field]) ||
                            $role_sorts[$field]=='ASC') ? 'DESC' : 'ASC';
                        unset($role_sorts[$field]);
                        $role_sorts = array_merge([$field => $sort_dir],
                            $role_sorts);
                        $role_sorts = urlencode(json_encode($role_sorts));
                        $sort_urls[$field] =
                            "$action_url&amp;role_sorts=$role_sorts";
                    }
                    ?>
                    <div id="user-roles">
                    <table><tr><td></td>
                    <th><a href='<?=$sort_urls['ROLE_NAME'] ?>'><?=
                        tl('manageusers_element_name') ?></a></th>
                    <th class='center'><?=
                        tl('manageusers_element_action') ?></th></tr><?php
                    foreach ($data['USER_ROLES'] as $role_array) {
                        e("<tr><td><input type='checkbox' class='role-id' " .
                            "id='role-" . $role_array['ROLE_ID'] . "' " .
                            "name='" . $role_array['ROLE_ID']. "' " .
                            " value='" . $role_array['ROLE_ID'] . "' />");
                        e("</td><td><b><label for='role-".
                            $role_array['ROLE_ID'] ."'>".
                            $role_array['ROLE_NAME'].
                            "</label></b></td>");
                        if ($data['CURRENT_USER']['user_name'] == 'root' &&
                            $role_array['ROLE_NAME'] == 'Admin') {
                            e("<td><span class='gray'>".
                                tl('manageusers_element_delete').
                                "</span></td>");
                        } else {
                            e("<td><a href='{$admin_url}a=manageUsers".
                                "&amp;arg=deleteuserrole&amp;$context".
                                "&amp;role_ids=" . $role_array['ROLE_ID']);
                            e("&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                                "&amp;$visibles&amp;$limits'>".
                                tl('manageusers_element_delete').
                                "</a></td>");
                        }
                        e("</tr>");
                    }
                    ?>
                    </table>
                    <?php
                    if ($data['ROLE_FILTER'] != "" ||
                        (isset($data['NUM_USER_ROLES']) &&
                        $data['NUM_USER_ROLES'] > C\NUM_RESULTS_PER_PAGE)) {
                        $limit = isset($data['ROLE_LIMIT']) ?
                            $data['ROLE_LIMIT']: 0;
                    ?>
                        <div class="center">
                        <?php
                            $action_url = $base_url. "&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;role_filter=".$data['ROLE_FILTER'].
                                "&amp;group_filter=".$data['GROUP_FILTER'];
                            if ($limit >= C\NUM_RESULTS_PER_PAGE ) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;$context" .
                                "&amp;role_limit=".
                                ($limit - C\NUM_RESULTS_PER_PAGE) ?>'
                                >&lt;&lt;</a><?php
                            }
                            ?>
                        <input class="very-narrow-field center"
                            name="role_filter" type="text" maxlength="<?=
                            C\NAME_LEN ?>" value='<?=$data['ROLE_FILTER'] ?>' />
                            <?php
                            if ($data['NUM_USER_ROLES'] > $limit +
                                C\NUM_RESULTS_PER_PAGE) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;$context" .
                                "&amp;role_limit=".
                                ($limit + C\NUM_RESULTS_PER_PAGE) ?>'
                                >&gt;&gt;</a>
                            <?php
                            }
                        ?><br />
                        <button type="submit" name="change_filter"
                            value="role"><?=tl('manageusers_element_filter')
                            ?></button>
                        <br />&nbsp;
                    </div>
                    <?php
                    }
                    ?>
                    <div class="center" >
                    <input type="text" name="selectrole" id='select-role'
                        class="very-narrow-field" />
                    <button type="submit"
                        class="button-box">
                        <label for='select-role'><?=
                        tl('manageusers_element_add_role') ?></label>
                    </button>
                    </div>
                    </div>
                <?php
                }
                ?>
                </div>
                </td></tr>
            <tr><th class="table-label" style="vertical-align:top"><?=
                tl('manageusers_element_groups') ?>:</th>
                <td><div class='light-gray-box'>
                <div class="center">
                    [<button class="button-anchor"
                        form="admin-form" type='submit'
                        name='visible_groups'
                        value='<?= ($data['visible_groups'] == 'true') ?
                            "false" : "true" ?>'><?=
                            tl('manageusers_element_num_groups',
                            $data['NUM_USER_GROUPS'])?></button>]<?php
                    if ($data['visible_groups'] == 'true') {
                        $context = "";
                        if (!empty($data['context'])) {
                            $context = 'context=search';
                        }
                        $sort_string = urlencode(json_encode(
                            $data['GROUP_SORTS']));
                        $action_url = "$base_url&amp;user_name=".
                            $data['CURRENT_USER']['user_name'].
                            "&amp;group_filter=" . $data['GROUP_FILTER'] .
                            "&amp;group_sorts=" . $sort_string;
                        $with_selected_actions = [
                            -1 => tl('manageusers_element_with_selected'),
                            $action_url . "&amp;arg=deleteusergroup&amp;" =>
                                tl('manageusers_element_delete'),
                        ];
                        $this->view->helper("options")->render(
                            "with-selected-user-groups", "with_selected_group",
                            $with_selected_actions, -1, '
                                var ids_string = arrayIntoFormVariable(
                                    group_ids, "group_ids");
                                window.location =
                                    this[this.selectedIndex].value +
                                    ids_string;
                            ', ["aria-label" => $with_selected_actions[-1]]);
                    }?>
                </div>
                <?php
                if ($data['visible_groups'] == 'true') {
                    $action_url = $base_url. "&amp;arg=edituser&amp;$context".
                        "&amp;user_name=" . $data['CURRENT_USER']['user_name'].
                        "&amp;role_filter=".$data['ROLE_FILTER'].
                        "&amp;group_filter=".$data['GROUP_FILTER'];
                    $sort_urls = ['GROUP_NAME' => '', 'STATUS' => ''];
                    foreach ($sort_urls as $field => $url) {
                        $group_sorts = $data['GROUP_SORTS'];
                        $sort_dir = (empty($group_sorts[$field]) ||
                            $group_sorts[$field] == 'ASC') ? 'DESC' : 'ASC';
                        unset($group_sorts[$field]);
                        $group_sorts = array_merge([$field => $sort_dir],
                            $group_sorts);
                        $group_sorts = urlencode(json_encode($group_sorts));
                        $sort_urls[$field] =
                            "$action_url&amp;group_sorts=$group_sorts";
                    }
                    ?>
                    <div id="user-groups">
                    <table><tr><td></td>
                    <th><a href='<?=$sort_urls['GROUP_NAME'] ?>'><?=
                        tl('manageusers_element_name') ?></a></th>
                    <th><a href='<?=$sort_urls['STATUS'] ?>'><?=
                        tl('manageusers_element_status') ?></a></th>
                    <th class='center'><?=
                        tl('manageusers_element_action') ?></th></tr><?php
                    foreach ($data['USER_GROUPS'] as $group_array) {
                        e("<tr><td><input type='checkbox' class='group-id' " .
                            "id='group-" . $group_array['GROUP_ID'] . "' " .
                            "name='" . $group_array['GROUP_ID'] . "' " .
                            " value='" . $group_array['GROUP_ID'] . "' />");
                        e("<td><b><label for='group-" .
                            $group_array['GROUP_ID'] . "'>".
                            $group_array['GROUP_NAME'].
                            "</label></b></td>");
                        e("<td class='gray'>".
                            $data["MEMBERSHIP_CODES"][$group_array['STATUS']].
                            "</td>");
                        e("<td><a href='{$admin_url}a=manageUsers".
                            "&amp;arg=deleteusergroup&amp;$context".
                            "&amp;group_ids=".
                            $group_array['GROUP_ID']);
                        e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                            "&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                            "&amp;$visibles&amp;$limits'>".
                            tl('manageusers_element_delete')."</a></td>");
                    }
                    ?>
                    </table>
                    <?php
                    if ($data['GROUP_FILTER'] != "" ||
                        (isset($data['NUM_USER_GROUPS']) &&
                        $data['NUM_USER_GROUPS'] > C\NUM_RESULTS_PER_PAGE)) {
                        $limit = isset($data['GROUP_LIMIT']) ?
                            $data['GROUP_LIMIT']: 0;
                        ?>
                        <div class="center">
                        <?php
                        $action_url = $base_url. "&amp;user_name=".
                            $data['CURRENT_USER']['user_name'].
                            "&amp;role_filter=".$data['ROLE_FILTER'].
                            "&amp;group_filter=".$data['GROUP_FILTER'];
                        if ($limit >= C\NUM_RESULTS_PER_PAGE ) {
                            ?><a href='<?=
                            "$action_url&amp;arg=edituser&amp;$context" .
                            "&amp;group_limit=".
                            ($limit - C\NUM_RESULTS_PER_PAGE) ?>'
                            >&lt;&lt;</a><?php
                        } ?>
                        <input class="very-narrow-field center"
                            name="group_filter" type="text" maxlength="<?=
                            C\SHORT_TITLE_LEN ?>" value='<?=
                            $data['GROUP_FILTER'] ?>' />
                        <?php
                        if ($data['NUM_USER_GROUPS'] > $limit +
                            C\NUM_RESULTS_PER_PAGE) {
                            ?><a href='<?=
                            "$action_url&amp;arg=edituser&amp;$context" .
                            "&amp;group_limit=".
                            ($limit + C\NUM_RESULTS_PER_PAGE)
                            ?>'>&gt;&gt;</a>
                            <?php
                        }
                        ?><br />
                        <button type="submit" name="change_filter"
                            value="group"><?= tl('manageusers_element_filter')
                        ?></button><br />&nbsp;
                    </div>
                    <?php
                    }
                    ?>
                    <div class="center" >
                    <input type="text" name="selectgroup" id='select-group'
                        class="very-narrow-field" />
                    <button type="submit"
                        class="button-box">
                        <label for='select-group'><?=
                        tl('manageusers_element_add_group')
                        ?></label></button>
                    </div>
                    </div>
                <?php
                }
                ?>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><th class="table-label"><label for="pass-word"><?=
            tl('manageusers_element_password') ?>:</label></th>
            <td><input type="password" id="pass-word"
                onclick='this.select();'
                name="password" maxlength="<?= C\LONG_NAME_LEN?>"
                value="<?= $data['CURRENT_USER']['password'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="retype-password"><?=
            tl('manageusers_element_retype_password') ?>:</label></th>
            <td><input type="password" id="retype-password"
                onclick='this.select();'
                name="retypepassword" maxlength="<?= C\LONG_NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['password'] ?>"
                class="narrow-field"/></td></tr>
        <tr><td></td><td class="center"><button
            class="button-box" <?php
            if ($data['FORM_TYPE'] == 'edituser') {
                e("id='focus-button'");
            }?>
            type="submit"><?= tl('manageusers_element_save')
            ?></button></td>
        </tr>
        </table>
        </form>
        <script>
        var role_ids = [];
        var group_ids = [];
        function arrayIntoFormVariable(arr, form_var)
        {
            arr_string = arr.join("*");
            return form_var + '=' + arr_string;
        }
        function updateCheckedIds(ids, elt)
        {
            var form = this.form;
            if (elt.checked) {
              ids[ids.length] = elt.value;
            } else {
                var index = ids.indexOf(elt.value);
                if (index > -1) {
                     ids.splice(index, 1);
                }
            }
        }
        </script>
        <?php
    }
    /**
     * Draws the search for users forms
     *
     * @param array $data consists of values of user fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageUsers";
        $view = $this->view;
        $title = tl('manageusers_element_search_user');
        $fields = [
            tl('manageusers_element_username') => "user",
            tl('manageusers_element_firstname') => "first",
            tl('manageusers_element_lastname') => "last",
            tl('manageusers_element_email') => "email",
            tl('manageusers_element_status') =>
                ["status", $data['EQUAL_COMPARISON_TYPES']],
        ];
        $postfix = "name";
        $dropdowns = [
            "status" => $data['STATUS_CODES']
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $fields, $dropdowns,
            $postfix);
    }
}
