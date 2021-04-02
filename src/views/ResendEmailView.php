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
 * @author Chris Pollett
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\views;

use seekquarry\yioop\configs as C;

/**
 * This View is responsible for drawing the
 * screen for resending the confirm account link
 *
 * @author Chris Pollett
 */
class ResendEmailView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the recover password web page and the page one get after
     * following the recover password email
     *
     * @param array $data  contains the anti CSRF token
     *     the view, data for captcha and recover dropdowns
     */
    public function renderView($data)
    {
        $logo = C\LOGO_LARGE;
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }
        $missing = [];
        if (isset($data['MISSING'])) {
            $missing = $data['MISSING'];
        }
        ?>
        <div class="landing non-search">
        <div class="small-top">
            <h1 class="logo"><a href="<?=C\SHORT_BASE_URL ?>?<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"><img
                src="<?= C\SHORT_BASE_URL . $logo ?>" alt="<?=
                $this->logo_alt_text ?>"/></a><span> - <?=
                tl('resendemail_view_resend_activation') ?></span></h1>
            <form method="post">
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="resendComplete" />
            <input type="hidden" name="cookieconsent" value="true" />
            <?php if (isset($_SESSION["random_string"])) { ?>
            <input type='hidden' name='nonce_for_string'
                id='nonce_for_string' />
            <input type='hidden' name='random_string' id='random_string'
                value='<?= $_SESSION["random_string"] ?>' />
            <input type='hidden' name='time1' id='time1'
                value='<?= $_SESSION["request_time"] ?>' />
            <input type='hidden' name='level' id='level'
                value='<?= $_SESSION["level"] ?>' />
            <input type='hidden' name='time' id='time' value='<?= time() ?>'/>
            <?php
            }
            ?>
            <div class="register">
                <table>
                    <tr>
                    <th class="table-label"><label for="username">
                        <?php
                        e(tl('resendemail_view_username')); ?></label>
                    </th>
                    <td class="table-input">
                        <input id="username" type="text"
                            class="narrow-field" maxlength="<?=
                            C\NAME_LEN ?>"
                            name="user" autocomplete="off"
                            value = "<?= $data['USER'] ?>"/>
                        <?= in_array("user", $missing)
                            ?'<span class="red">*</span>':''?></td>
                    </tr>
                    <?php
                    if (isset($data['CAPTCHA_IMAGE'])) {
                        ?>
                        <tr><th class="table-label" rowspan='2'><label
                            for="user-captcha-text"><?=
                            tl('resendemail_view_human_check')
                            ?></label></th><td><img class="captcha"
                            src="<?= $data['CAPTCHA_IMAGE'] ?>" alt="CAPTCHA">
                            </td></tr><tr><td>
                            <input type="text" maxlength="<?=C\CAPTCHA_LEN ?>"
                            id="user-captcha-text" class="narrow-field"
                            name="user_captcha_text"/></td></tr>
                        <?php
                    }
                    $border_top = "";
                    if (isset($_SERVER["COOKIE_CONSENT"]) &&
                        !$_SERVER["COOKIE_CONSENT"]) {
                        $border_top = " border-top "; ?>
                        <tr>
                            <td>&nbsp;</td>
                            <td class="table-input <?=$border_top
                                ?> narrow-field" ><?=
                                tl('resendemail_view_privacy_i_agree') ?>
                            <a href="<?php e(C\SHORT_BASE_URL);
                                ?>terms.php"><?= tl('resendemail_view_terms')
                                ?></a>
                            <?php e(tl('resendemail_view_and')); ?>
                            <a href="<?php e(C\SHORT_BASE_URL);
                                ?>privacy.php"><?=
                                tl('resendemail_view_privacy')
                                ?></a><?= tl('resendemail_view_period') ?>
                            </td>
                        </tr><?php
                    } ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td class="table-input border-top">
                            <input type="hidden"
                                name="<?= C\CSRF_TOKEN ?>"
                                value="<?= $data[C\CSRF_TOKEN] ?>"/>
                            <button  type="submit"><?=
                                tl('resendemail_view_resend_email')
                            ?></button>
                        </td>
                    </tr>
                </table>
            </div>
            </form>
            <div class="signin-exit">
                <ul>
                <li><a href="."><?= tl('resendemail_view_return') ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <div class='tall-landing-spacer'></div>
        <?php  if (isset($_SESSION["random_string"])) {?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
            var body = tag(body);
            body.onload = findNonce('nonce_for_string', 'random_string'
                , 'time', 'level');
            }, false);
        </script>
        <?php
        }
    }
}
