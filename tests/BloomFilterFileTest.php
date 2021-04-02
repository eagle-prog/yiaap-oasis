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
use seekquarry\yioop\library\BloomFilterFile;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test that the BloomFilterFile class provides the basic functionality
 * of a persistent set. I.e., we can insert things into it, and we can do
 * membership testing
 *
 * @author Chris Pollett
 */
class BloomFilterFileTest extends UnitTest
{
    /**
     * Set up a bloom filter that can store up to 10 items and that saves
     * itself every 100 writes
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] = new BloomFilterFile(C\WORK_DIRECTORY.
            "/test.ftr", 10, 100);
    }
    /**
     * Since a BloomFilterFile is a PersistentStructure it periodically saves
     * itself to a file. To clean up we delete the files that might be created
     */
    public function tearDown()
    {
        if (file_exists(C\WORK_DIRECTORY."/test.ftr")) {
            unlink(C\WORK_DIRECTORY."/test.ftr");
        }
    }
    /**
     * Tests that if nothing is in the bloom filter yet, that if we do a lookup
     * we don't find anything
     */
    public function notInTestCase()
    {
        $this->assertFalse(
            $this->test_objects['FILE1']->contains(66), "File 1 contains 66");
    }
    /**
     * Tests if we insert something into the bloom filter, that when we look it
     * up, we find it. On the other hand, if we look something else up that we
     * didn't insert, we shouldn't find it
     *
     */
    public function inTestCase()
    {
        $this->test_objects['FILE1']->add(77);
        $this->test_objects['FILE1']->add("prime minister");
        $this->test_objects['FILE1']->add("prime minister*");
        $this->assertTrue(
            $this->test_objects['FILE1']->contains(77), "File 1 contains 77");
        $this->assertTrue(
            $this->test_objects['FILE1']->contains("prime minister"),
            "File 1 contains prime minister");
        $this->assertTrue(
            $this->test_objects['FILE1']->contains("prime minister*"),
            "File 1 contains prime minister*");
        $this->assertFalse(
            $this->test_objects['FILE1']->contains(66), "File 1 contains 66");
    }
    /**
     * Check that if we force save the bloom filter file and then we reload it
     * back in that it has the same Contents
     *
     */
    public function saveLoadTestCase()
    {
        $this->test_objects['FILE1']->add(77);
        $this->test_objects['FILE1']->save();
        $this->test_objects['FILE1'] = null;
        $this->test_objects['FILE2'] = BloomFilterFile::load(C\WORK_DIRECTORY.
            "/test.ftr");
        $this->assertTrue(
            $this->test_objects['FILE2']->contains(77), "File 2 contains 77");
        $this->assertFalse(
            $this->test_objects['FILE2']->contains(66), "File 2 contains 66");
    }
}
