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

use seekquarry\yioop\configs as C;

/**
 *
 * Draws the view on which people can control
 * their search settings such as num links per screen
 * and the language settings
 *
 * @author Chris Pollett
 */
class TestsView extends View
{
    /**
     * This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the web page on which users can control their search settings.
     *
     * @param array $data passed from controller. It fields contain info
     *  about the possibble tests that can be run, which test activity to
     *  carry out, etc.
     */
    public function renderView($data)
    {
        $logged_in = !empty($data["ADMIN"]);
        $token_string = ($logged_in) ? "?" .C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        $logo = C\LOGO_LARGE;
        if ($_SERVER["MOBILE"]) {
            $logo = C\LOGO_SMALL;
        }
        ?>
        <div class="group-heading logo"><div class="test">
        <h1 class="logo"><a href="<?=C\SHORT_BASE_URL .
            $token_string ?>"><img src="<?=C\SHORT_BASE_URL .
        $logo ?>" alt="<?=$this->logo_alt_text ?>" /></a><span> - <?=
            tl('tests_view_tests') ?></span>
        </h1>
        </div>
        </div>
        <div class="small-top" >
        <div class='current-activity'>
        <?php
            switch ($data['ACTIVITY']) {
                case "render_test":
                    $this->renderTest($data);
                break;
                case "render_all_tests":
                    $this->renderAllTests($data);
                break;
                case "list":
                default:
                    $this->renderTestsList($data);
                break;
            }
        ?>
        </div>
        </div>
        <div class='landing-spacer'></div><?php
    }
    /**
     * This function is responsible for listing out HTML links to the available
     * unit tests a user can run
     * @param array $data passed from controller. Its $data['TEST_NAMES']
     *      field should contain an array of all the tests that can be run
     */
    function renderTestsList($data)
    {
        ?>
        <p><a href="?activity=runAllTests"><?=
            tl('tests_view_run_all_tests')?></a>.</p>
        <h2><?=tl('tests_view_available_tests')?></h2>
        <ul class='square-list'>
        <?php
        foreach ($data['TEST_NAMES'] as $test_name) {
            ?><li><a href='?activity=runTest&test=<?=
            $test_name?>'><?=$test_name ?></a></li><?php
        }
        ?>
        </ul>
        <?php
    }
    /**
     * Uses draw the results of all avaliable unit tests in Yioop
     *
     * @param string $data passed from controller. Its $data['ALL_RESULTS']
     *      field should contain an array of all the test results for unit
     *      tests that were carried out
     */
    function renderAllTests($data)
    {
        ?><p><a href='?activity=listTests'>See test case list</a>.</p><?php
        $data['NO_ALL_LINK'] = true;
        foreach ($data['ALL_RESULTS'] as
            $data['TEST_NAME'] => $data['RESULTS']){
            $this->renderTest($data);
        }
    }
    /**
     * Uses draw the results of unit test class, run the tests in it and display
     * the results
     *
     * @param string $data  passed from controller. Its $data['TEST_NAME']
     *      field should contain an array test case results for a particular
     *      unit test. It's $data['NO_ALL_LINK'] should say whether all tests
     *      are currently being run or just one. $data['PHANTOMJS_REQUIRED']
     *      indicates some tests for Javascript that were run require
     *      Phantomjs to work
     */
    function renderTest($data)
    {
        if (empty($data['NO_ALL_LINK'])) { ?>
            <p><a href='?activity=listTests'>See test case list</a>.</p>
            <?php
        } ?>
        <h2><?=$data['TEST_NAME']?></h2><?php
        if (!empty($data['PHANTOMJS_REQUIRED']) ) {
            e(tl('tests_view_phantomjs_required'));
            return;
        } else if (!empty($data['RESULTS']['JS'])) {
            foreach ($data['RESULTS']['DATA'] as
                $test_case_name => $case_data) {
                echo $case_data;
            }
        } else {
            ?>
            <table class="wikitable">
            <?php
            foreach ($data['RESULTS'] as $test_case_name => $case_data) {
                ?><tr><th style='text-align:right'><?=
                $test_case_name?></th><?php
                $passed = 0;
                $count = 0;
                $failed_items = [];
                foreach ($case_data as $item) {
                    if ($item['PASS']) {
                        $passed++;
                    } else {
                        $failed_items[] = $item;
                    }
                    $count++;
                }
                if ($count == $passed) {
                    $color = "back-light-green";
                } else {
                    $color = "back-red";
                }
                ?><td class='<?=$color?>'><?=$passed?>/<?=$count?> <?=
                    tl('tests_view_tests_passed')?><br /><?php
                if (count($failed_items) > 0) {
                    foreach ($failed_items as $item) {
                        echo "  FAILED: ".$item['NAME']."<br />";
                    }
                }
                ?></td></tr><?php
            }
            ?>
            </table>
            <?php
        }
    }
}
