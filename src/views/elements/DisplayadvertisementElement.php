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
 * @author Pushkar Umaranikar
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop\configs as C;

/**
 * Element responsible for displaying the advertisement
 * on the search results page
 *
 * @author Pushkar Umaranikar
 */
class DisplayadvertisementElement extends Element
{
    /**
     * Draws relevant advertisement on search results page.
     *
     * @param array $data keys are generally the different setting that can
     *     be set in the crawl.ini file
     */
    public function render($data)
    {
        if (!empty($data['RELEVANT_ADVERTISEMENT'])) {
            $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
            $token_string_amp = ($logged_in) ?
                C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN]."&amp;" : "";
            $destination_url =
                (strpos($data['RELEVANT_ADVERTISEMENT']['DESTINATION'],
                "http") !== false) ?
                $data['RELEVANT_ADVERTISEMENT']['DESTINATION'] :
                "http://". $data['RELEVANT_ADVERTISEMENT']['DESTINATION'];
            $url = C\SHORT_BASE_URL . "?" . ($token_string_amp) .
                "c=search&a=recordClick&arg=" .
                $data['RELEVANT_ADVERTISEMENT']['ID'];
            ?>
            <div class="display-ad">
            <p>
                <img src="<?= C\SHORT_BASE_URL . C\AD_LOGO ?>" />
                <a href="<?= $destination_url ?>"
                   onclick="recordClick();" <?php if ($data["OPEN_IN_TABS"]) {
                      ?>target="_blank" rel="noopener"<?php }?>>
                      <?= $data['RELEVANT_ADVERTISEMENT']['NAME'] ?>
                </a>
                <span><?=$data['RELEVANT_ADVERTISEMENT']['DESCRIPTION']?>
                </span>
            </p>
            </div>
            <script>
                function recordClick() {
                    var url = <?php echo(json_encode($url));?>;
                    var http = new XMLHttpRequest();
                    http.open("POST", url, true);
                    http.send();
                }
            </script>
        <?php
        }
    }
}
