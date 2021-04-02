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
 * This View is responsible for drawing the login
 * screen for the admin panel of the Seek Quarry app
 *
 * @author Chris Pollett
 */
class SigninView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the login web page.
     *
     * @param array $data  contains the anti CSRF token
     * the view
     */
    public function renderView($data)
    {
        $logged_in = isset($data['ADMIN']) && $data['ADMIN'];
        $logo = C\LOGO_LARGE;
        $user_value = isset($_SESSION["USER_NAME"]) &&
            isset($_SESSION['USER_ID']) ? " value='{$_SESSION["USER_NAME"]}' " :
            "";
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }?>
        <div class="landing non-search">
        <h1 class="logo"><a href="<?=C\SHORT_BASE_URL ?><?php if ($logged_in) {
                e('?' . C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]);
            }?>"><img src="<?=C\SHORT_BASE_URL .
            $logo ?>" alt="<?= $this->logo_alt_text
            ?>" /></a><span> - <?=tl('signin_view_signin') ?></span></h1>
        <form method="post">
        <div class="login">
            <table>
            <tr>
            <td class="table-label" ><b><label for="username"><?=
                tl('signin_view_username') ?></label>:</b></td><td
                    class="table-input"><input id="username" type="text"
                    class="narrow-field" maxlength="<?= C\NAME_LEN
                    ?>" name="u" <?=$user_value ?> />
            </td><td></td></tr>
            <tr>
            <td class="table-label" ><b><label for="password"><?=
                tl('signin_view_password') ?></label>:</b></td><td
                class="table-input"><input id="password" type="password"
                class="narrow-field" maxlength="<?= C\LONG_NAME_LEN
                ?>" name="p" /></td>
            <td><input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                    id="CSRF-TOKEN" value="<?= $data[C\CSRF_TOKEN] ?>" />
                <input type="hidden" name="c" value="admin" />
                <input type="hidden" name="cookieconsent" value="true" />
            </td>
            </tr><?php
            $border_top = "";
            if (isset($_SERVER["COOKIE_CONSENT"]) &&
                !$_SERVER["COOKIE_CONSENT"]) {
                $border_top = " border-top "; ?>
                <tr>
                    <td>&nbsp;</td>
                    <td class="table-input <?=$border_top ?> narrow-field" ><?=
                        tl('signin_view_privacy_i_agree') ?>
                    <a href="<?php e(C\SHORT_BASE_URL);
                        ?>terms.php"><?= tl('signin_view_terms')
                        ?></a>
                    <?php e(tl('signin_view_and')); ?>
                    <a href="<?php e(C\SHORT_BASE_URL);
                        ?>privacy.php"><?= tl('signin_view_privacy')
                        ?></a><?= tl('signin_view_period') ?>
                    </td>
                </tr><?php
            } ?>
            <tr><td>&nbsp;</td><td class="<?= $border_top; ?>">
            <button  type="submit" ><?=tl('signin_view_login') ?></button>
            </td><td>&nbsp;</td></tr>
            </table>
        </div>
        </form>
        <div class="signin-exit">
            <ul>
                <?php
                if (in_array(C\REGISTRATION_TYPE, ['no_activation',
                    'email_registration', 'admin_activation'])) {
                    if (C\RECOVERY_MODE != C\NO_RECOVERY) {
                    ?>
                    <li><a href="<?=B\controllerUrl('register', true)
                        ?>a=recoverPassword<?php
                        if ($logged_in) {
                            e('&amp;'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
                        } ?>" ><?=tl('signin_view_recover_password') ?></a></li>
                    <?php
                    }
                    ?>
                    <li><a href="<?=B\controllerUrl('register', true)
                        ?>a=createAccount<?php
                        if ($logged_in) {
                            e('&amp;'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
                        }?>"><?=tl('signin_view_create_account') ?></a></li>
                    <li><a href="<?=B\controllerUrl('register', true)
                        ?>a=resendRegistration<?php
                        if ($logged_in) {
                            e('&amp;'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
                        }?>"><?=tl('signin_view_resend_activation')?></a></li>
                <?php
                }
            ?>
                <li><a href="."><?=tl('signin_view_return') ?></a></li>
            </ul>
        </div>
        </div>
        <div class='landing-spacer'></div>
        <?php
    }
}
