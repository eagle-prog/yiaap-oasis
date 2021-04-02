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
 * Element used to draw forms to set up the various external servers
 * that might be connected with a Yioop installation
 *
 * @author Chris Pollett
 */
class ServersettingsElement extends Element
{
    /**
     * Method that draw forms to set up the various external servers
     * that might be connected with a Yioop installation
     *
     * @param array $data holds data on the profile elements which have been
     *     filled in as well as data about which form fields to display
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $token_string = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
        $pre_base_url = $admin_url . $token_string;
        $base_url = $pre_base_url . "&amp;a=serverSettings";
        ?>
        <div class="current-activity">
        <form id="serverSettingsForm" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="serverSettings" />
        <input type="hidden" name="arg" value="update" />
        <h2><?= tl('serversettings_element_server_settings')?></h2>
        <div class="bold">
            <div class="top-margin">
            <fieldset><legend><?=tl('serversettings_element_name_server') .
                "&nbsp;" . $this->view->helper("helpbutton")->render(
                "Name Server Setup", $data[C\CSRF_TOKEN]) ?></legend><?php
                if (!empty($this->view->controller_object->web_site) &&
                    $this->view->controller_object->web_site->isCli()) {
                    ?>
                    [<a href="<?=$base_url . '&amp;arg=restart' ?>"><?=
                    tl('serversettings_element_restart');?></a>]
                    <?php
                }
                ?><div ><b><label for="queue-fetcher-salt"><?=
                    tl('serversettings_element_name_server_key')
                    ?></label></b>
                    <input type="text" id="queue-fetcher-salt" name="AUTH_KEY"
                        value="<?= $data['AUTH_KEY'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="name-server-url"><?=
                    tl('serversettings_element_name_server_url')
                    ?></label></b>
                    <input type="url" id="name-server-url" name="NAME_SERVER"
                        value="<?= $data['NAME_SERVER'] ?>"
                        class="extra-wide-field" />
                </div>
                <div id="filecache">
                <div class="top-margin"><label for="use-filecache"><b><?=
                    tl('serversettings_element_use_filecache')?></b></label>
                    <input type="checkbox" id="use-filecache"
                        name="USE_FILECACHE" value="true" <?=
                        $data['USE_FILECACHE'] ? "checked='checked'" :
                            "" ?> />
                    [<a href="<?= $base_url . '&amp;arg=clearCache'
                    ?>"><?=tl('serversettings_element_clear_cache')
                    ?></a>]
                </div>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset><legend><?=tl('serversettings_element_database_setup').
                "&nbsp;" .$this->view->helper("helpbutton")->render(
                "Database Setup", $data[C\CSRF_TOKEN]) ?>
                </legend>
                <div ><label for="database-system"><b><?=
                    tl('serversettings_element_database_system')
                    ?></b></label>
                    <?php $this->view->helper("options")->render(
                        "database-system", "DBMS",
                        $data['DBMSS'], $data['DBMS']);
                ?>
                </div>
                <div class="top-margin"><b><label for="database-name"><?=
                    tl('serversettings_element_databasename') ?></label></b>
                    <input type="text" id="database-name" name="DB_NAME"
                        value="<?= $data['DB_NAME'] ?>"
                        class="wide-field" />
                </div>
                <div id="login-dbms">
                    <div class="top-margin"><b><label for="database-host"><?=
                        tl('serversettings_element_databasehost')
                        ?></label></b>
                        <input type="text" id="database-host" name="DB_HOST"
                            value="<?= $data['DB_HOST'] ?>"
                            class="wide-field" />
                    </div>
                    <div class="top-margin"><b><label for="database-user"><?=
                        tl('serversettings_element_databaseuser')
                        ?></label></b>
                        <input type="text" id="database-user" name="DB_USER"
                            value="<?= $data['DB_USER'] ?>"
                            class="wide-field" />
                    </div>
                    <div class="top-margin"><b><label
                        for="database-password"><?=
                        tl('serversettings_element_databasepassword')
                        ?></label></b>
                        <input type="password" id="database-password"
                            name="DB_PASSWORD" value="<?=
                            $data['DB_PASSWORD'] ?>" class="wide-field" />
                    </div>
                </div>
            </fieldset>
            </div>
            <div class = "top-margin">
            <fieldset >
                <legend><label for="account-registration"><?=
                tl('serversettings_element_account_registration') ?>
                </label>
                <?= $this->view->helper("helpbutton")->render(
                    "Account Registration", $data[c\CSRF_TOKEN])
                    ?></legend>
                <?php $this->view->helper("options")->render(
                    "account-registration", "REGISTRATION_TYPE",
                    $data['REGISTRATION_TYPES'],
                    $data['REGISTRATION_TYPE']);
                ?>
                <div id="registration-info">
                <div class="top-margin"><b><label for="mail-sender"><?=
                    tl('serversettings_element_mail_sender')?></label></b>
                    <input type="email" id="mail-sender" name="MAIL_SENDER"
                        value="<?= $data['MAIL_SENDER'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="send-media-updater"><?=
                    tl('serversettings_element_send_media_updater')
                    ?></label></b>
                    <input type="checkbox" id="send-media-updater"
                        name="SEND_MAIL_MEDIA_UPDATER"
                        value="true" <?php if (
                            $data['SEND_MAIL_MEDIA_UPDATER'] == true) {
                            e("checked='checked'");
                            }?> />
                </div>
                <div class="top-margin"><b><label for="use-php-mail"><?=
                    tl('serversettings_element_use_php_mail')?></label></b>
                    <input type="checkbox" id="use-php-mail" name="USE_MAIL_PHP"
                        value="true" <?php if ( $data['USE_MAIL_PHP'] == true) {
                        e("checked='checked'");}?> />
                </div>
                <div id="smtp-info">
                <div class="top-margin"><b><label for="mail-server"><?=
                    tl('serversettings_element_mail_server')?></label></b>
                    <input type="text" id="mail-server" name="MAIL_SERVER"
                        value="<?=$data['MAIL_SERVER'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-port"><?=
                    tl('serversettings_element_mail_serverport')
                    ?></label></b>
                    <input type="text" id="mail-port" name="MAIL_SERVERPORT"
                        value="<?=$data['MAIL_SERVERPORT'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-username"><?=
                    tl('serversettings_element_mail_username')?></label></b>
                    <input type="text" id="mail-username" name="MAIL_USERNAME"
                        value="<?= $data['MAIL_USERNAME'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-password"><?=
                    tl('serversettings_element_mail_password')?></label></b>
                    <input type="password" id="mail-password"
                        name="MAIL_PASSWORD"
                        value="<?= $data['MAIL_PASSWORD'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><b><label for="mail-security"><?=
                    tl('serversettings_element_mail_security')?></label></b>
                    <input type="text" id="mail-security" name="MAIL_SECURITY"
                        value="<?= $data['MAIL_SECURITY'] ?>"
                        class="wide-field" />
                </div>
                </div>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset><legend><?=tl('serversettings_element_proxy_title') .
                "&nbsp;" .$this->view->helper("helpbutton")->render(
                        "Proxy Server", $data[C\CSRF_TOKEN]) ?></legend>
                <div ><b><label for="tor-proxies"><?=
                    tl('serversettings_element_tor_proxy')?></label></b>
                    <input type="text" id="tor-proxies" name="TOR_PROXY"
                        value="<?=$data['TOR_PROXY'] ?>"
                        class="wide-field" />
                </div>
                <div class="top-margin"><label for="use-proxy"><b><?=
                    tl('serversettings_element_use_proxy_servers')
                        ?></b></label>
                        <input type="checkbox" id="use-proxy"
                            name="USE_PROXY" value="true" <?=
                            $data['USE_PROXY'] ? "checked='checked'" :
                            ""  ?> />
                </div>
                <div id="proxy">
                    <div class="top-margin"><label for="proxy-servers" ><b><?=
                    tl('serversettings_element_proxy_servers') ?></b></label>
                    </div>
                    <textarea class="short-text-area" id="proxy-servers"
                    name="PROXY_SERVERS"><?=$data['PROXY_SERVERS'] ?></textarea>
                </div>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset><legend>
            <?= tl('serversettings_element_monetization_config') .
            "&nbsp;" . $this->view->helper("helpbutton")->render(
                "Monetization", $data[C\CSRF_TOKEN]) ?></legend>
            <div>
                <label for="monetization-type"><?=
                tl('serversettings_element_monetization_type')
                    ?></label>
                <?php $this->view->helper("options")->render(
                    "monetization-type", "MONETIZATION_TYPE",
                    $data['MONETIZATION_TYPES'],
                    $data['MONETIZATION_TYPE']);
                ?>
            </div>
            <div id="payment-processing">
            <?php
            if (!C\CreditConfig::isActive()) { ?>
                <br /><b class="red"><?=
                tl('serversettings_element_no_payment_processing') ?></b><br />
                [<a href="https://www.seekquarry.com/adscript"><?=
                tl('serversettings_element_purchase_processing')
                ?></a>]
                <?php
            }
            ?>
            </div>
            <div id="ad-location-info">
            <br /><b><?=tl('serversettings_element_ad_location') ?></b><br />
            <input id='ad-location-top' type='radio' name='AD_LOCATION'
                value="top" onchange="showHideScriptdiv();" <?=
                ($data['AD_LOCATION'] == 'top') ?'checked' : ''
                ?> /><label for="ad-location-top"><?=
                tl('serversettings_element_top') ?></label>
            <input id='ad-location-side' type='radio' name='AD_LOCATION'
                value="side" onclick="showHideScriptdiv();" <?=
                ($data['AD_LOCATION'] == 'side') ?
                    'checked' : '' ?> /><label for="ad-location-side"><?=
                tl('serversettings_element_side') ?></label>
            <input type='radio' id='ad-location-both'
                name='AD_LOCATION' value="both"
                onclick="showHideScriptdiv();" <?=
                ($data['AD_LOCATION'] == 'both') ? 'checked'
                    :'' ?> /><label for="ad-location-both"><?=
                tl('serversettings_element_both') ?></label>
            <input type='radio' id='ad-location-none' name='AD_LOCATION'
                value="none" onclick="showHideScriptdiv();" <?=
                ($data['AD_LOCATION'] == 'none') ?
                    'checked' : '' ?> /><label for="ad-location-none"><?=
                tl('serversettings_element_none') ?></label>
            <div id="global-adscript-config">
            <label for="global-adscript"><b><?=
            tl('serversettings_element_global_adscript')
            ?></b></label>
            <textarea class="short-text-area" id="global-adscript"
                name="GLOBAL_ADSCRIPT"><?=
                html_entity_decode($data['GLOBAL_ADSCRIPT'], ENT_QUOTES)
                ?></textarea></div>
            <div id="top-adscript-config"><label for="top-adscript"><b><?=
                tl('serversettings_element_top_adscript')
            ?></b></label>
            <textarea class="short-text-area" id="top-adscript"
                name="TOP_ADSCRIPT"><?=
                html_entity_decode($data['TOP_ADSCRIPT'], ENT_QUOTES)
                ?></textarea></div>
            <div id="side-adscript-config"><label for="side-adscript"><b><?=
                tl('serversettings_element_side_adscript')
            ?></b></label>
            <textarea class="short-text-area" id="side-adscript"
                name="SIDE_ADSCRIPT"><?=
                html_entity_decode($data['SIDE_ADSCRIPT'], ENT_QUOTES)
            ?></textarea></div></div>
            </fieldset>
            </div>
            <div class="top-margin">
                <fieldset>
                    <legend>
            <?= tl('serversettings_element_bot_configuration') .
            "&nbsp;" . $this->view->helper("helpbutton")->render(
                "Bot Configuration", $data[C\CSRF_TOKEN]) ?></legend>
                <div>
                <label for="bot-configuration"><?=
                tl('serversettings_element_bot_settings')
                    ?></label>
                <?php $this->view->helper("options")->render(
                    "bot-configuration","CONFIGURE_BOT",
                    $data['CONFIGURE_BOTS'],
                    $data['CONFIGURE_BOT']);
                ?>
            </div>
                </fieldset>
            </div>
            <div class="top-margin center">
            <button class="button-box" type="submit"><?=
                tl('serversettings_element_save') ?></button>
            </div>
            </div>
        </form>
        </div>
        <script>
        window.onload = function()
        {
            showHideScriptdiv();
            selectMonetizationType();
        }
        /**
         * Method to show/block div including text area depending upon location
         * selected for the advertisement to display on search results page.
         */
        function showHideScriptdiv()
        {
            /*
             * Get the radio button list represnting location for the
             * advertisement.
             */
            var ad_server_config = document.getElementsByName('AD_LOCATION');
            /*
             * Show/ block div with text area depending upon the radio
             * button value.
             */
            var ad_align = [
                ['block','block','none'], //top[top,global,side]
                ['none','block','block'], //side
                ['block','block','block'], //both
                ['none','none','none'], //none
            ];
            for (var i = 0; i < ad_server_config.length; i++){
                if (ad_server_config[i].checked) {
                    elt('top-adscript-config').style.display = ad_align[i][0];
                    elt('global-adscript-config').style.display =
                        ad_align[i][1];
                    elt('side-adscript-config').style.display = ad_align[i][2];
                    break;
                }
            }
        }
        function selectMonetizationType() {
            var monetization_type = elt('monetization-type').value;
            setDisplay('ad-location-info',
                (monetization_type == 'external_advertisements'));
            setDisplay('payment-processing',
                (monetization_type == 'fees_and_keywords' ||
                monetization_type == 'group_fees' ||
                monetization_type == 'keywords_advertisements'));
        }
        </script>
    <?php
    }
}
