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
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PriorityQueue;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test the PriorityQueue class that is used to figure out which URL
 * to crawl next
 *
 * @author Chris Pollett
 */
class PriorityQueueTest extends UnitTest
{
    /**
     * We setup two queue one that always returns the max element, one that
     * always returns the min element
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] =
            new PriorityQueue(C\WORK_DIRECTORY . "/queue1.txt",
                100, 4, CrawlConstants::MAX);
        $this->test_objects['FILE2'] =
            new PriorityQueue(C\WORK_DIRECTORY . "/queue2.txt",
                100, 4, CrawlConstants::MIN);
    }
    /**
     * Since our queues are persistent structures, we delete files that might be
     * associated with them when we tear down
     */
    public function tearDown()
    {
        set_error_handler(null);
        @unlink(C\WORK_DIRECTORY."/queue1.txt");
        @unlink(C\WORK_DIRECTORY."/queue2.txt");
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    }
    /**
     * Insert five items into a priority queue. Checks that the resulting heap
     * array matches the expected array calculated by hand. Weights of some
     * elements of the queue are adjusted and the resulting heap array checked
     * again. The results of polling the queue and normalizing the queue are
     * tested
     */
    public function maxQueueTestCase()
    {
        $this->test_objects['FILE1']->insert("aaaa", 6);
        $this->test_objects['FILE1']->insert("baaa", 7);
        $this->test_objects['FILE1']->insert("caaa", 4);
        $this->test_objects['FILE1']->insert("daaa", 5);
        $this->test_objects['FILE1']->insert("eaaa", 8);
        $expected_array = [ ["eaaa", 8], ["baaa", 7], ["caaa", 4], ["daaa", 5],
             ["aaaa", 6]];
        $this->assertEqual(
            $this->test_objects['FILE1']->getContents(), $expected_array,
            "Insert into queue yields expected contents");
        $this->test_objects['FILE1']->adjustWeight(3, 5);
        $expected_array = [["caaa", 9], ["baaa", 7], ["eaaa", 8], ["daaa", 5],
             ["aaaa", 6]];
        $this->assertEqual(
            $this->test_objects['FILE1']->getContents(), $expected_array,
            "Adjust elt weight yields expected contents");
        $this->test_objects['FILE1']->normalize(29);
        $total_weight = $this->test_objects['FILE1']->totalWeight();
        $num_items = $this->test_objects['FILE1']->count;
        $this->assertEqual($total_weight <= 29,
            "Total weight is less than the to normalize to total");
        $this->assertEqual($total_weight >= 29 - $num_items,
            "Total weight is greater than the to normalize to total ".
            "less num items");
        $queue_data = $this->test_objects['FILE1']->getContents();
        $elt = $this->test_objects['FILE1']->poll();
        $this->assertEqual($elt[0], "caaa", "Remove caaa from queue okay");
        $elt = $this->test_objects['FILE1']->poll();
        $this->assertEqual($elt[0], "eaaa", "Remove eaaa from queue okay");
        $elt = $this->test_objects['FILE1']->poll();
        $this->assertEqual($elt[0], "baaa", "Remove baaa from queue okay");
        $elt = $this->test_objects['FILE1']->poll();
        $this->test_objects['FILE1']->normalize();
        $expected_array = [["daaa", C\NUM_URLS_QUEUE_RAM]];
        $this->assertEqual(
            $this->test_objects['FILE1']->getContents(), $expected_array,
            "Queue after deletes has expected content");
    }
    /**
     * Inserts five elements inserted into a minimum priority queue. The
     * resulting heap array is compared to expected. Then repeated polling is
     * done to make sure the objects come out in the correct order.
     */
    public function minQueueTestCase()
    {
        $this->test_objects['FILE2']->insert("aaaa", 6);
        $this->test_objects['FILE2']->insert("baaa", 7);
        $this->test_objects['FILE2']->insert("caaa", 4);
        $this->test_objects['FILE2']->insert("daaa", 5);
        $this->test_objects['FILE2']->insert("eaaa", 8);
        $expected_array = [["caaa", 4], ["daaa", 5], ["aaaa", 6],
            ["baaa", 7], ["eaaa", 8]];
        $this->assertEqual(
            $this->test_objects['FILE2']->getContents(), $expected_array,
            "Queue has expected order after initial inserts");
        $elt = $this->test_objects['FILE2']->poll();
        $this->assertEqual($elt[0], "caaa", "Remove caaa from queue okay");
        $elt = $this->test_objects['FILE2']->poll();
        $this->assertEqual($elt[0], "daaa", "Remove daaa from queue okay");
        $elt = $this->test_objects['FILE2']->poll();
        $this->assertEqual($elt[0], "aaaa", "Remove aaaa from queue okay");
        $elt = $this->test_objects['FILE2']->poll();
        $this->assertEqual($elt[0], "baaa", "Remove baaa from queue okay");
        $elt = $this->test_objects['FILE2']->poll();
        $this->assertEqual($elt[0], "eaaa", "Remove eaaa from queue okay");
        $this->assertEqual(
            $this->test_objects['FILE2']->getContents(),
            [], "Queue should be empty after deletes");
    }
}
