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
 * Used to draw the admin screen on which admin users can create roles, delete
 * roles and add and delete activitiess from roles
 *
 * @author Chris Pollett
 */
class ManagerolesElement extends Element
{
    /**
     * renders the screen in which roles can be created, deleted, and activities
     * can be added to and deleted from a selected roles
     *
     * @param array $data  contains antiCSRF token, as well as data on
     *     available roles or which activity has what role
     */
    public function render($data)
    {?>
        <div class="current-activity">
        <?= $this->renderRoleTable($data); ?>
        </div>
        <?php
    }
    /**
     * Draws the table to display thhe currently available roles
     * and their properties in this Yioop system
     * @param array $data info about current users and current roles, CSRF token
     */
    public function renderRoleTable($data)
    {
        $data['TABLE_TITLE'] = tl('manageroles_element_roles');
        $data['ACTIVITY'] = 'manageRoles';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = false;
        if (in_array($data['FORM_TYPE'], ['editrole', 'search'])) {
            $data['DISABLE_ADD_TOGGLE'] = true;
        }
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=manageRoles";
        $context = "";
        $arg_context = "";
        if ($data['FORM_TYPE'] == 'search' ||
            !empty($data['context']) && $data['context'] == 'search') {
            $context = 'context=search&amp;';
            $arg_context = "&amp;arg=search";
        }
        if ($data['FORM_TYPE'] == 'editrole') {
            $this->view->helper("close")->render($base_url . $arg_context);
            $this->renderRoleForm($data);
            return;
        }?>
        <div id='role-info' >
        <table class="admin-table">
            <tr><td class="no-border" colspan="3"><?=$this->view->helper(
                "pagingtable")->render($data); ?><?php
                if ($data['FORM_TYPE'] != "editrole") { ?>
                    <div id='admin-form-row' class='admin-form-row'><?php
                    if ($data['FORM_TYPE'] == "search") {
                        $this->renderSearchForm($data);
                    } else {
                        $this->renderRoleForm($data);
                    }?>
                    </div><?php
                }?></td>
            </tr>
            <tr>
                <th><?= tl('manageroles_element_rolename')?></th>
                <th colspan='2'><?= tl('manageroles_element_actions') ?></th>
            </tr><?php
            if (isset($data['START_ROW'])) {
                $base_url .= "&amp;start_row=".$data['START_ROW'].
                    "&amp;end_row=".$data['END_ROW'].
                    "&amp;num_show=".$data['NUM_SHOW'];
            }
            $delete_url = $base_url . "&amp;arg=deleterole&amp;$context";
            $edit_url = $base_url . "&amp;arg=editrole&amp;$context";
            $stretch = ($_SERVER["MOBILE"]) ? 1 :2;
            foreach ($data['ROLES'] as $role) {?>
                <tr><?php
                foreach ($role as $colname => $role_column) {
                    if (strlen($role_column) > $stretch * C\NAME_TRUNCATE_LEN) {
                        $role_column = wordwrap($role_column,
                             $stretch * C\NAME_TRUNCATE_LEN, "\n", true);
                    }
                    e("<td>$role_column</td>");
                }?>
                <td><a href="<?php e($edit_url . 'name='.
                    urlencode($role['NAME'])); ?>"><?=
                tl('manageroles_element_edit') ?></a></td>
                <td><?php
                    if (in_array($role['NAME'], ['Admin', 'User',
                        'Bot User', 'Business User'])) {
                        e('<span class="gray">'.
                            tl('manageroles_element_delete').'</span>');
                    } else {
                    ?>
                        <a onclick='javascript:return confirm("<?php
                        e(tl('manageroles_element_confirm_delete'));
                        ?>");' href="<?php e($delete_url . 'name='.
                        $role['NAME']); ?>"><?php
                        e(tl('manageroles_element_delete')."</a>");
                    }?></td>
                </tr><?php
            }?>
        </table>
        <?php if ($_SERVER["MOBILE"]) { ?>
            <div class="clear">&nbsp;</div>
        <?php } ?>
        </div><?php
    }
    /**
     * Draws the add role and edit role forms
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderRoleForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=manageRoles";
        $paging = "";
        if (isset($data['START_ROW'])) {
            $paging = "&amp;start_row=".$data['START_ROW'].
                "&amp;end_row=".$data['END_ROW'].
                "&amp;num_show=".$data['NUM_SHOW'];
            $base_url .= $paging;
        }
        $editrole = ($data['FORM_TYPE'] == "editrole") ? true: false;?>
        <form id="admin-form" method="post"><?php
        if ($editrole) {
            e("<h2>" . tl('manageroles_element_role_info') . "</h2>");
        } else {
            e("<h2>" . tl('manageroles_element_add_role') . "</h2>");
        }
        ?>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageRoles" />
        <input type="hidden" name="arg" value="<?= $data['FORM_TYPE'] ?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="role-name"><?=
            tl('manageroles_element_rolename') ?></label>:</th>
            <th><input type="text" id="role-name"
                name="name"  maxlength="<?= C\NAME_LEN  ?>"
                value="<?= $data['CURRENT_ROLE']['name'] ?>"
                class="narrow-field" <?php
                if ($editrole) {
                    e(' disabled="disabled" ');
                }
                ?> /></th></tr>
        <?php
        if ($editrole) {
            $context = "";
            if (!empty($data['context']) && $data['context'] == 'search') {
                $context = 'context=search&amp;';
            }
            ?>
            <tr><th class="table-label" style="vertical-align:top"><?=
                tl('manageroles_element_role_activities') ?>:</th>
                <td><div class='light-gray-box'>
                <table class='role-activity-table'>
                <tr><th><?=tl('manageroles_element_activity_name')?></th>
                    <th><?=tl('manageroles_element_allowed_arguments')?></th>
                    <th><?=tl('manageroles_element_activity_actions')?></th>
                    <?php
                foreach ($data['ROLE_ACTIVITIES'] as $activity_array) {?>
                    <tr><td><b><?=$activity_array['ACTIVITY_NAME']
                    ?></b></td><?php
                    if ($data['CURRENT_ROLE']['name'] == 'Admin' &&
                        in_array($activity_array['ACTIVITY_NAME'],
                        ["Manage Account", "Manage Users",
                        "Manage Roles", "Manage Groups",
                        "Server Settings", "Security", "Configure"])) {?>
                        <td><input type="text" disabled="disabled"
                            name="activities[<?=$activity_array['ACTIVITY_ID']
                            ?>]" maxlength="<?= C\NAME_LEN  ?>"
                            value="<?=$activity_array['ALLOWED_ARGUMENTS'] ?>"
                            class="narrow-field" /></td>
                        <td><span class='gray'><?=
                            tl('manageroles_element_delete')?></span></td><?php
                    } else { ?>
                        <td><input type="text"
                            name="activities[<?=$activity_array['ACTIVITY_ID']
                            ?>]" maxlength="<?= C\NAME_LEN  ?>"
                            value="<?=$activity_array['ALLOWED_ARGUMENTS'] ?>"
                            class="narrow-field" /></td>
                        <td><a href='<?=$admin_url. "a=manageRoles".
                            "&amp;arg=deleteactivity&amp;$context".
                            "selectactivity=". $activity_array['ACTIVITY_ID'] .
                            "&amp;name=".$data['CURRENT_ROLE']['name'].
                            "&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                            $paging?>'><?=tl('manageroles_element_delete')
                            ?></a></td><?php
                    }?>
                </tr><?php
                } ?>
                </table><?php
                if (count($data['AVAILABLE_ACTIVITIES']) > 1) {
                    $this->view->helper("options")->render(
                        "add-roleactivity",
                        "selectactivity", $data['AVAILABLE_ACTIVITIES'],
                        $data['SELECT_ACTIVITY'], true);?><?php
                } ?>
                </div>
                </td></tr><?php
        } ?>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?= tl('manageroles_element_save') ?></button></td>
        </tr>
        </table>
        </form><?php
    }
    /**
     * Draws the search for roles forms
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageRoles";
        $view = $this->view;
        $title = tl('manageroles_element_search_role');
        $fields = [
            tl('manageroles_element_rolename') => "name",
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $fields);
    }
}
