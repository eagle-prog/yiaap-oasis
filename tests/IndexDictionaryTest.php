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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\IndexDictionary;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test that the IndexDictionary class can properly add shards
 * and retrieve correct posting slice ranges in the shards.
 *
 * @author Chris Pollett
 */
class IndexDictionaryTest extends UnitTest
{
    /**
     * Construct some index shard we can add documents to
     */
    public function setUp()
    {
        $this->test_objects['shard'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard.txt", 0);
        $this->test_objects['shard2'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard2.txt", 1);
        $this->test_objects['shard3'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard3.txt", 2);
        $this->test_objects['dictionary'] = new IndexDictionary(
            C\WORK_DIRECTORY . "/dictionary", null);
    }
    /**
     * Deletes any index shard files we may have created
     */
    public function tearDown()
    {
        set_error_handler(null);
        @unlink(C\WORK_DIRECTORY . "/shard.txt");
        @unlink(C\WORK_DIRECTORY . "/shard2.txt");
        @unlink(C\WORK_DIRECTORY . "/shard3.txt");
        $dbms_manager = C\NS_DATASOURCES . ucfirst(C\DBMS) . "Manager";
        $db = new $dbms_manager();
        $db->unlinkRecursive(C\WORK_DIRECTORY . "/dictionary", true);
        set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
    }
    /**
     * Check that appending two index shards works correctly
     */
    public function addShardDictionaryTestCase()
    {
        $docid = "AAAAAAAABBBBBBBBCCCCCCCC"; //set up doc
        $offset = 5;
        $word_counts = [
            'MMMMMMMM' => [1, 3, 5],
            'NNNNNNNN' => [2, 4, 6],
            'OOOOOOOO' => [7, 8, 9],
        ];
        $meta_ids = ["PPPPPPPP", "QQQQQQQQ"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids);
        $this->assertEqual($this->test_objects['shard']->len_all_link_docs, 9,
            "Len All Docs Correctly Counts Length of First Doc");
        $this->test_objects['shard']->save();
        $shard = new IndexShard(C\WORK_DIRECTORY .
            "/shard.txt", 0, C\NUM_DOCS_PER_GENERATION, true);
        $word_id = L\crawlHashWord('MMMMMMMM');
        $shard_info = $shard->getWordInfo($word_id);
        $this->test_objects['dictionary']->addShardDictionary($shard);
        $dict_info = $this->test_objects['dictionary']->getWordInfo($word_id);
        array_shift($dict_info[0]);
        $first_entry = array_shift($dict_info);
        $this->assertEqual($shard_info, $first_entry,
            "Shard word entry agrees with dictionary word entry");
        $docid = "AAAAAAAABBBBBBBBEEEEEEEE";
        $offset = 10;
        $word_counts = [
            'BBBBBBBB' => [1],
            'CCCCCCCC' => [2],
            'MMMMMMMM' => [6],
        ];
        $meta_ids = ["EEEEEEEE", "FFFFFFFF"];
        $this->test_objects['shard2']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids);
        $this->test_objects['shard2']->save();
        $shard = new IndexShard(C\WORK_DIRECTORY .
            "/shard2.txt", 1, C\NUM_DOCS_PER_GENERATION, true);
        $word_id = L\crawlHashWord('MMMMMMMM');
        $shard_info2 = $shard->getWordInfo($word_id);
        $this->test_objects['dictionary']->addShardDictionary($shard);
        $dict_info = $this->test_objects['dictionary']->getWordInfo($word_id);
        $this->assertEqual(count($dict_info), 2,
            "After second shard insert have two entries for all M word");
        array_shift($dict_info[1]);
        $second_entry = $dict_info[1];
        $this->assertEqual($shard_info2, $second_entry,
            "Second entry in two shard case for M word matches expected");
    }
}
