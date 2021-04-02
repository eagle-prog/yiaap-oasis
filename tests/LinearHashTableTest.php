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
 * This file contains unit tests of the LinearHashTable class
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
use seekquarry\yioop\models\Model;
use seekquarry\yioop\library\UnitTest;

/**
 *
 */
 class LinearHashTableTest extends UnitTest
{
    /**
     * Prefix of folders for linear hashing test
     */
    const TEST_DIR = '/test_files/linear_test';
    /**
     * Sets up an array to keep track of what linear hash tables we've made
     * so that we can delete them when done a test.
     */
    public function setUp()
    {
        $this->table_dirs = [];
    }
    /**
     * Used to create a single hash table in the folder
     * TEST_DIR . $max_items_per_file which allows at most
     * $max_items_per_file to be stored in a bucket
     *
     * @param int $max_items_per_file number of items allowed to be stored in a
     *  bucket
     */
    public function createTable($max_items_per_file)
    {
        $table_dir = __DIR__ . self::TEST_DIR . $max_items_per_file;
        $this->table_dirs[] = $table_dir;
        return new L\LinearHashTable($table_dir,
            $max_items_per_file);
    }
    /**
     * Deletes all the Linear Hash tables in $this->table_dirs
     */
    public function tearDown()
    {
        $model = new Model();
        foreach ($this->table_dirs as $table_dir) {
            $model->db->unlinkRecursive($table_dir);
        }
        $this->table_dirs = [];
    }
    /**
     */
    public function insertHashKeyLookupTestCase()
    {
        $max_file_size = 2 << 10;
        for ($i = 1; $i < $max_file_size; $i *= 2) {
            $hash_file = $this->createTable($i);
            for ($j = 0; $j < 258; $j++) {
                $hash_file->put(pack("J2", $j, 0), "test$i-$j", true);
                $value = $hash_file->get(pack("J2", $j, 0), true);
                $this->assertEqual("test$i-$j", $value,
                    "immediate epoch $i put $j get $j test");
            }
            for ($j = 0; $j < 258; $j++) {
                $value = $hash_file->get(pack("J2", $j, 0), true);
                $this->assertEqual("test$i-$j", $value,
                    "epoch $i put $j get $j test");
            }
        }
    }
    /**
     */
    public function insertKeyLookupTestCase()
    {
        $hash_file = $this->createTable(4);
        for ($j = 0; $j < 258; $j++) {
            $hash_file->put($j,"test-$j");
            $value = $hash_file->get($j);
            $this->assertEqual("test-$j", $value,
                "immediate put $j get $j test");
        }
        for ($j = 0; $j < 258; $j++) {
            $value = $hash_file->get($j);
            $this->assertEqual("test-$j", $value, "put $j get $j test");
        }
    }
    /**
     * Tests whether key value pairs can be deleted from the linear hash table
     * without destroying non-deleted data. This test is for pre-hashed keys
     */
    public function deleteHashKeyTestCase()
    {
        $max_file_size = 2 << 10;
        for ($i = 1; $i < $max_file_size; $i *= 2) {
            $hash_file = $this->createTable($i);
            for ($j = 0; $j < 260; $j++) {
                $hash_file->put(pack("J2", $j, 0), "test$i-$j", true);
            }
            for ($j = 65; $j < 195; $j++) {
                $hash_file->delete(pack("J2", $j, 0), true);
            }
            for ($j = 0; $j < 260; $j++) {
                $value = $hash_file->get(pack("J2", $j, 0), true);
                if ($j < 65 || $j >= 195) {
                    $this->assertEqual("test$i-$j", $value,
                        "epoch $i put $j get $j test");
                } else {
                    $this->assertEqual(false, $value,
                        "epoch $i delete $j get false test");
                }
            }
        }
    }
    /**
     * Tests whether key value pairs can be deleted from the linear hash table
     * without destroying non-deleted data. This test is for non-pre-hashed keys
     */
    public function deleteKeyTestCase()
    {
        $hash_file = $this->createTable(4);
        for ($j = 0; $j < 260; $j++) {
            $hash_file->put($j, "test-$j");
        }
        for ($j = 65; $j < 195; $j++) {
            $hash_file->delete($j);
        }
        for ($j = 0; $j < 260; $j++) {
            $value = $hash_file->get($j);
            if ($j < 65 || $j >= 195) {
                $this->assertEqual("test-$j", $value, "put $j get $j test");
            } else {
                $this->assertEqual(false, $value, "delete $j get false test");
            }
        }
    }
}
