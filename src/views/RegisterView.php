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
 * Draws the page that allows a user to register for an account
 *
 * @author Mallika Perepa (creator), Chris Pollett, Akash Patel
 */
class RegisterView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is the most..? followed by one of the form Which is the least ..?
     * followed by a string which is a comma separated list of possibilities
     * arranged from least to most. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale. You can also
     * change them in the Security element
     * @var array
     */
    public $captchas_qa;
    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is your favorite..? followed by one of the form
     * Which is your like the least..? followed by a string which is a comma
     * separated choices. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale.
     * @var array
     */
    public $recovery_qa;
    /**
     * Besides setting calling the constructor for the base class this
     * constructor also sets up the captchas_qa and recovery_qa arrays
     * so they can be localized. The reason for putting these arrays in a
     * view is so that multiple controllers/components can see and manipulate
     * them
     * @param object $controller_object that is using this view
     */
    public function __construct($controller_object = null)
    {
        $this->recovery_qa = [
            [ tl('register_view_recovery1_more'),
                tl('register_view_recovery1_less'),
                tl('register_view_recovery1_choices')],
            [ tl('register_view_recovery2_more'),
                tl('register_view_recovery2_less'),
                tl('register_view_recovery2_choices')],
            [ tl('register_view_recovery3_more'),
                tl('register_view_recovery3_less'),
                tl('register_view_recovery3_choices')],
            [ tl('register_view_recovery4_more'),
                tl('register_view_recovery4_less'),
                tl('register_view_recovery4_choices')],
            [ tl('register_view_recovery5_more'),
                tl('register_view_recovery5_less'),
                tl('register_view_recovery5_choices')],
            [ tl('register_view_recovery6_more'),
                tl('register_view_recovery6_less'),
                tl('register_view_recovery6_choices')],
            ];
        parent::__construct($controller_object);
    }
    /**
     * Draws the create account web page.
     *
     * @param array $data  contains the anti CSRF token
     *     the view, data for captcha and recover dropdowns
     */
    public function renderView($data)
    {
        $logged_in = (isset($data['ADMIN']) && $data['ADMIN']);
        $append_url = ($logged_in && isset($data[C\CSRF_TOKEN]))
                ? C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] : "";
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
                $append_url ?>"><img src="<?= C\SHORT_BASE_URL .
                $logo ?>" alt="<?= $this->logo_alt_text ?>" /></a><span> - <?=
                tl('register_view_create_account') ?></span></h1>
            <form method="post">
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="processAccountData" />
            <input type="hidden" name="cookieconsent" value="true" />
            <?php
            if (isset($_SESSION["random_string"])) {
            ?>
                <input type='hidden' name='nonce_for_string'
                    id='nonce_for_string' />
                <input type='hidden' name='random_string' id='random_string'
                    value='<?= $_SESSION["random_string"] ?>' />
                <input type='hidden' name='time' id='time'
                    value='<?= $_SESSION["request_time"] ?>' />
                <input type='hidden' name='level' id='level'
                    value='<?= $_SESSION["level"] ?>' />
                <?php
            } ?>
            <div class="register">
                <table>
                    <tr>
                        <th class="table-label">
                            <label for="firstname"><?=
                                tl('register_view_firstname')
                            ?></label>
                        </th>
                        <td class="table-input">
                            <input id="firstname" type="text"
                                class="narrow-field" maxlength="<?=
                                C\NAME_LEN ?>"
                                name="first" autocomplete="off"
                                value = "<?= $data['FIRST'] ?>"/>
                            <span id="first-error"></span></td>
                    </tr>
                    <tr>
                        <th class="table-label">
                            <label for="lastname"><?=
                                tl('register_view_lastname')
                            ?></label>
                        </th>
                        <td class="table-input">
                            <input id="lastname" type="text"
                                class="narrow-field" maxlength="<?=
                                C\NAME_LEN ?>"
                                name="last" autocomplete="off"
                                value = "<?= $data['LAST']  ?>"/>
                            <span id="last-error"></span></td>
                    </tr>
                    <tr>
                        <th class="table-label"><label for="username">
                            <?= tl('register_view_username') ?></label>
                        </th>
                        <td class="table-input">
                            <input id="username" type="text"
                                class="narrow-field" maxlength="<?=
                                C\NAME_LEN ?>"
                                name="user" autocomplete="off"
                                value = "<?= $data['USER'] ?>"/>
                            <span id="user-error"></span></td>
                    </tr>
                    <tr>
                        <th class="table-label"><label for="email"><?=
                            tl('register_view_email') ?></label>
                        </th>
                        <td class="table-input">
                            <input id="email" type="text"
                                class="narrow-field" maxlength="<?=
                                C\LONG_NAME_LEN ?>"
                                name="email" autocomplete="off"
                                value = "<?= $data['EMAIL'] ?>"/>
                            <span id="email-error"></span></td>
                    </tr>
                    <tr>
                        <th class="table-label">
                            <label for="pass-word"><?=
                            tl('register_view_password')
                            ?></label>
                        </th>
                        <td class="table-input">
                            <input id="pass-word" type="password"
                                class="narrow-field" maxlength="<?=
                                C\LONG_NAME_LEN?>"
                                name="password" value="<?=
                                $data['PASSWORD'] ?>" />
                            <span id="password-error"></span></td>
                    </tr>
                    <tr>
                        <th class="table-label">
                            <label for="retype-password"><?=
                                 tl('register_view_retypepassword')
                            ?></label>
                        </th>
                        <td class="table-input">
                            <input id="retype-password" type="password"
                                class="narrow-field" maxlength="<?=
                                C\LONG_NAME_LEN ?>"
                                name="repassword" value="<?=
                                $data['REPASSWORD'] ?>" />
                            <span id="pass-match"></span></td>
                    </tr>
                    <?php
                    // hash captcha or image captcha case
                    if (isset($_SESSION["random_string"]) ||
                        isset($_SESSION["captcha_text"])) {
                        $question_sets = [];
                        if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
                            $question_sets = [
                                tl('register_view_account_recovery') =>
                                $data['RECOVERY']];
                        }
                    } else {
                        $question_sets = [];
                        if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
                            $question_sets = [
                                tl('register_view_account_recovery') =>
                                $data['RECOVERY']];
                        }
                    }
                    $i = 0;
                    foreach ($question_sets as $name => $set) {
                        $first = true;
                        $num = count($set);
                        foreach ($set as $question) {
                            if ($first) { ?>
                                <tr><th class="table-label"
                                    rowspan='<?= $num ?>'><?php
                                    e($name);
                                ?></th><td class="table-input border-top"><?php
                            } else { ?>
                                <tr><td class="table-input"><?php
                            }
                            $data["question_$i"] = $data["question_$i"] ?? "";
                            $this->helper("options")->render(
                                "question-$i", "question_$i",
                                $question, $data["question_$i"]);
                            $first = false;
                            e(in_array("question_$i", $missing)
                                ?'<span class="red">*</span>':'');
                            e("</td></tr>");
                            $i++;
                        }
                    }
                    if (isset($data['CAPTCHA_IMAGE'])) {
                        ?>
                        <tr><th class="table-label" rowspan='2'><label
                            for="user-captcha-text"><?=
                            tl('register_view_human_check')
                            ?></label></th><td><img class="captcha"
                            src="<?= $data['CAPTCHA_IMAGE'] ?>" alt="CAPTCHA">
                            </td></tr><tr><td>
                            <input type="text" maxlength="<?=
                                C\CAPTCHA_LEN ?>"
                            id="user-captcha-text" class="narrow-field"
                            name="user_captcha_text"/></td></tr>
                        <?php
                    }
                    ?>
                    <tr>
                        <td></td>
                        <td class="table-input border-top narrow-field" ><?=
                            tl('register_view_privacy_i_agree') ?>
                        <a href="<?php e(C\SHORT_BASE_URL);
                            ?>terms.php"><?= tl('register_view_terms')
                            ?></a>
                        <?php e(tl('register_view_and')); ?>
                        <a href="<?php e(C\SHORT_BASE_URL);
                            ?>privacy.php"><?= tl('register_view_privacy')
                            ?></a><?= tl('register_view_period') ?>
                        </td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td class="table-input border-top">
                        <input type="hidden"
                            name="<?php e(C\CSRF_TOKEN);?>"
                            value="<?php e($data[C\CSRF_TOKEN]); ?>"/>
                            <button id="submit" type="submit"><?=
                            tl('register_view_create_account')
                            ?></button>
                        </td>
                    </tr>
                </table>
            </div>
            </form>
            <div class="signin-exit">
                <ul>
                <li><a href="."><?= tl('register_view_return') ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <div class='tall-landing-spacer'></div>
        <?php
        if (isset($_SESSION["random_string"])) { ?>
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
    ?>
