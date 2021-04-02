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
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;

/**
 * Element responsible for displaying Ad credits purchase form and
 * recent transaction table
 */
class ManagecreditsElement extends Element
{
    /**
     * Draws create advertisement form and existing campaigns
     * @param array $data containing field values that have already been
     *  been filled in, data about exsting campaigns and the anti-CSRF attack token
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
        <h2><?=tl('managecredits_element_balance', $data['BALANCE']) ?></h2>
        <?php
            $data['TABLE_TITLE'] = tl('managecredits_element_transactions');
            $data['ACTIVITY'] = 'manageCredits';
            if (empty($data['FORM_TYPE'])) {
                $data['FORM_TYPE'] = "purchaseCredits";
            }
            if (in_array($data['FORM_TYPE'], ['search'])) {
                $data['DISABLE_ADD_TOGGLE'] = true;
            }
            $data['VIEW'] = $this->view;
            $num_columns = 4;
        ?>
        <table class="admin-table">
            <tr><td class="no-border" colspan="<?=
                $num_columns ?>"><?php $this->view->helper(
                "pagingtable")->render($data); ?>
                <div id='admin-form-row' class='admin-form-row'><?php
                if ($data['FORM_TYPE'] == "search") {
                    $this->renderSearchForm($data);
                } else {
                    $this->renderCreditsForm($data);
                }?>
                </div>
                </td>
            </tr>
            <tr>
                <th><?= tl('managecredits_element_type')?>
                </th>
                <th><?= tl('managecredits_element_amount')?>
                </th>
                <th><?= tl('managecredits_element_date')?>
                </th>
                 <th><?= tl('managecredits_element_total')?>
                </th>
            </tr>
            <?php
            foreach ($data['TRANSACTIONS'] as $tr) {
                ?>
                <tr>
                <td><?=tl($tr['TYPE']) ?></td>
                <td><?=$tr['AMOUNT'] ?></td>
                <td><?=date("r", $tr['TIMESTAMP']) ?></td>
                <td><?=$tr['BALANCE'] ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        </div>
        <?php
    }
    /**
     * Draws the form used to create or edit a keyword
     * advertisement
     * @param array $data containing field values that have already been
     *  been filled in and the anti-CSRF attack token
     */
    public function renderCreditsForm($data)
    { ?>
        <h2><?= tl('managecredits_element_purchase_credits') ?>
            <?= $this->view->helper("helpbutton")->render(
                "Manage Credits", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="purchase-credits-form" method="post" >
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="manageCredits"/>
            <input type="hidden" name="arg" value="purchaseCredits" />
            <input type="hidden" id="credit-token"
                name="CREDIT_TOKEN" value="" />
            <table class='name-table'>
            <tr><th class="table-label"><label for="num-credits"><?=
                tl('managecredit_element_num_credits') ?>:
            </label></th>
            <td>
            <?php
            $this->view->helper('options')->render('num-credits', 'NUM_DOLLARS',
                $data["AMOUNTS"], 0);
            ?>
            </td>
            </tr>
            <tr><th class="table-label"><label for="card-number"><?=
                tl('managecredit_element_card_number') ?>:
            </label></th>
            <td>
            <input class="narrow-field" id="card-number"
                type="text" size="20" <?=
                    C\CreditConfig::getAttribute('card-number','name')
                    ?>="<?=
                    C\CreditConfig::getAttribute('card-number','value')
                    ?>" />
            </td></tr>
            <tr><th class="table-label"><label for="cvc"><?=
                tl('managecredit_element_cvc') ?>:
            </label></th>
            <td>
            <input class="narrow-field" id="cvc"
                type="text" size="4" <?=
                    C\CreditConfig::getAttribute('cvc','name')?>="<?=
                    C\CreditConfig::getAttribute('cvc','value') ?>" />
            </td></tr>
            <tr><th class="table-label"><label for="expiration"><?=
                tl('managecredit_element_expiration') ?>:
            </label></th>
            <td>
            <?php
            $this->view->helper('options')->render('expiration', '',
                $data['MONTHS'], 0, false, [
                    C\CreditConfig::getAttribute('exp-month','name') =>
                    C\CreditConfig::getAttribute('exp-month','value')]);
            ?> / <?php
            $this->view->helper('options')->render('', '',
                $data['YEARS'], 0, false, [
                    C\CreditConfig::getAttribute('exp-year','name') =>
                    C\CreditConfig::getAttribute('exp-year','value')]);
            ?>
            </td></tr>
            <tr>
            <td></td>
            <td><div class="narrow-field green tiny-font"><?=
            tl('managecredits_element_charge_warning')
            ?> <a target="_blank" rel="noopener" href="<?=B\wikiUrl(
                'ad_program_terms') ?>"><?=
            tl('managecredits_element_program_terms')
            ?></a>.</div></td>
            </tr>
            <tr>
            <td></td>
            <td class="center">
            <input class="button-box" id="purchase"
                name="PURCHASE" value="<?=
                tl('managecredits_element_purchase')
                ?>" type="submit" />
            <?php
            if (C\CreditConfig::isActive()) {
                $ad_script_found = false;
                for ($i = C\DATABASE_VERSION; $i >= C\MIN_AD_VERSION; $i--) {
                    $get_submit_purchase_script = "FN" . md5(
                        UrlParser::getBaseDomain(C\NAME_SERVER) .
                        $i . "getSubmitPurchaseScript");
                    if (method_exists( C\NS_CONFIGS . "CreditConfig",
                        $get_submit_purchase_script)) {
                        $ad_script_found = true;
                        break;
                    }
                }
                if ($ad_script_found) {
                    $data['SCRIPT'] .=
                        e(C\CreditConfig::$get_submit_purchase_script());
                } else {
                    e("<br /><span class='red'>".
                        tl('managecredits_element_script_failure')."</span>");
                }
            }
            ?>
            </td>
            </tr>
            </table>
        </form><?php
    }
    /**
     * Draws the search for credit transactions forms
     *
     * @param array $data consists of values of locale fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageCredits";
        $view = $this->view;
        $title = tl('managecredits_element_search');
        $fields = [
            tl('managecredits_element_type') => ["type",
                $data['EQUAL_COMPARISON_TYPES']],
            tl('managecredits_element_amount') => ["amount",
                $data['BETWEEN_COMPARISON_TYPES']],
            tl('managecredits_element_date') => ["timestamp",
                $data['BETWEEN_COMPARISON_TYPES']]
        ];
        $dropdowns = [
            'amount' => "range",
            'type' => ['advertisement_init_ledger' =>
                tl('advertisement_init_ledger'),
                'advertisement_buy_credits' => tl('advertisement_buy_credits'),
                'advertisement_buy_ad' => tl('advertisement_buy_ad'),
                'social_component_join_group_fee' =>
                    tl('social_component_join_group_fee')],
            'timestamp' => "date"];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $fields, $dropdowns);
    }
}
