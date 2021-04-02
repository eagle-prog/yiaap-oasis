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
use seekquarry\yioop\library\VersionManager;
use seekquarry\yioop\library\UnitTest;

/**
 * UnitTests for the VersionManager class.
 *
 * @author Chris Pollett
 */
class VersionManagerTest extends UnitTest
{
    /**
     * our dbms manager handle so we can call unlinkRecursive
     * @var object
     */
    public $db;
    /**
     * Folder to use for test repository
     * @var string
     */
     public $version_test_folder = C\TEST_DIR .
        "/test_files/version_manager_test";
    /**
     * Sets up a miminal DBMS manager class so that we will be able to use
     * unlinkRecursive to tear down the files created bby our tests
     */
    public function __construct()
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
        $this->db = new $db_class();
        if (!file_exists($this->version_test_folder)) {
            mkdir($this->version_test_folder);
        }
    }
    /**
     * Does nothing
     */
    public function setUp()
    {
    }
    /**
     * Delete the files created associated with the VersionManager tests
     */
    public function tearDown()
    {
        $this->db->unlinkRecursive($this->version_test_folder, false);
    }
    /**
     * Test the ability to create a new version of a folder within the
     * VervionManager archive.
     */
    public function createVersionFolderTestCase()
    {
        file_put_contents($this->version_test_folder . "/test.txt", "hi there");
        $this->assertTrue(file_exists($this->version_test_folder . "/test.txt"),
            "Write anything test folder");
        $vcs = new VersionManager($this->version_test_folder);
        $this->assertTrue(file_exists($this->version_test_folder . "/.archive"),
            "Archive sub-folder created");
        $archive_info = $vcs->headInfo();
        $this->assertTrue(!empty($archive_info),
            "HEAD info file exists and not empty");
        $this->assertTrue(!empty($archive_info['TIMESTAMP']),
            "HEAD version timestamp exists");
        $this->assertTrue(!empty($archive_info['FILES']['test.txt']),
            "First test file is in stored in HEAD info");
    }
    /**
     * Tests that we can put and get files from the head version of
     * the managed folder's version archive.
     */
    public function getPutContentsTestCase()
    {
        $test_files = [];
        $timestamps = [];
        $vcs = new VersionManager($this->version_test_folder);
        foreach (["test1.txt", "test2.txt", "test3.txt"] as $test_file) {
            $file_name = $this->version_test_folder . "/$test_file";
            $vcs->headPutContents($file_name, $test_file);
            $head_info1 = $vcs->headInfo();
            $data = $vcs->headGetContents($file_name);
            $this->assertEqual($test_file, $data,
                "head put followed by get returns same data");
            $head_info2 = $vcs->headInfo();
            $this->assertEqual($head_info1['TIMESTAMP'],
                $head_info2['TIMESTAMP'],
                "get shouldn't change version timestamp");
            $timestamps[$test_file] = $head_info1['TIMESTAMP'];
        }
        $old_test_file = "";
        foreach (["test1.txt", "test2.txt", "test3.txt"] as $test_file) {
            $file_name = $this->version_test_folder . "/$test_file";
            list($type, $data) = $vcs->versionGetContents($file_name,
                $timestamps[$test_file]);
            $this->assertEqual('f', $type,
                "versionGetContents read back correct type.");
            $this->assertEqual($test_file, $data,
                "versionGetContents can get data written by a given version.");
            if (!empty($old_test_file)) {
                $lookup = $vcs->versionGetContents("$file_name",
                    $timestamps[$old_test_file]);
                $this->assertEqual(VersionManager::HASH_LOOKUP_FAIL, $lookup,
                    "can't find file in too early a version");
            }
            $old_test_file = $test_file;
        }
    }
    /**
     * Tests file manipulations that can be done on files in the head version
     * of the repository. (copy a file, rename a file, delete a file).
     */
    public function copyDeleteRenameTestCase()
    {
        $vcs = new VersionManager($this->version_test_folder);
        $file_name = [];
        $timestamp = [];
        for($i = 1; $i <= 3; $i++) {
            $file_name[$i] = $this->version_test_folder . "/test$i.txt";
        }
        $vcs->headPutContents($file_name[1], "hi");
        $head_info = $vcs->headInfo();
        $timestamp[1] = $head_info["TIMESTAMP"];
        $this->assertTrue(file_exists($file_name[1]),
            "Make test file succeeded");
        $vcs->headRename($file_name[1], $file_name[2]);
        $head_info = $vcs->headInfo();
        $this->assertTrue(!file_exists($file_name[1]),
            "Test file rename succeeded - file not in old location");
        $this->assertTrue(file_exists($file_name[2]),
            "Test file rename succeeded - file is in new location");
        $timestamp[2] = $head_info["TIMESTAMP"];
        $vcs->headCopy($file_name[2], $file_name[3]);
        $this->assertTrue(file_exists($file_name[2]),
            "Test file copy succeeded - file is in old location");
        $this->assertTrue(file_exists($file_name[3]),
            "Test file copy succeeded - file is in new location");
        $head_info = $vcs->headInfo();
        $timestamp[3] = $head_info["TIMESTAMP"];
        $vcs->headDelete($file_name[2]);
        $this->assertTrue(!file_exists($file_name[2]),
            "Test file delete succeeded - file is no longer present in head");
        $this->assertTrue(file_exists($file_name[3]),
            "Test file delete succeeded - the files still exist head");
        for ($i = 1; $i <= 3; $i++) {
            list(, $data) =
                $vcs->versionGetContents($file_name[$i], $timestamp[$i]);
            $this->assertEqual($data, "hi", "Can still read old version $i");
        }
        $sub_folder = $this->version_test_folder . "/sub";
        $sub_file = "$sub_folder/foo.txt";
        $sub2_folder = $this->version_test_folder . "/sub2";
        $sub2_file = "$sub2_folder/foo.txt";
        $vcs->headMakeDirectory($sub_folder);
        $vcs->headPutContents($sub_file, "lala");
        $head_info = $vcs->headInfo();
        $timestamp = $head_info['TIMESTAMP'];
        list(, $data) = $vcs->versionGetContents($sub_file, $timestamp);
        $this->assertEqual($data, "lala", "Can read version from subfolder");
        $vcs->headRename($sub_folder, $sub2_folder);
        $this->assertTrue(!file_exists($sub_file),
            "Test sub folder file doesn't exist after rename");
        $vcs->restoreVersion(1);
        $this->assertTrue(!file_exists($sub2_file),
            "Test sub folder file correctly deleted on version restore");
    }
    /**
     * Tests restoring a folder to a given timestamp, making sure the
     * correct files are present after the restore.
     */
    public function restoreVersionTestCase()
    {
        $timestamps = [];
        $vcs = new VersionManager($this->version_test_folder);
        $head_info = $vcs->headInfo();
        $timestamps["initial"] = $head_info['TIMESTAMP'];
        foreach (["test1.txt", "test2.txt"] as $test_file) {
            $file_name = $this->version_test_folder . "/$test_file";
            $vcs->headPutContents($file_name, $test_file);
            $head_info = $vcs->headInfo();
            $timestamps[$test_file] = $head_info['TIMESTAMP'];
        }
        $dir = $this->version_test_folder . "/test";
        $vcs->headMakeDirectory($dir);
        $this->assertTrue(file_exists($dir),
            "headMakeDirectory succeeded");
        symlink(C\TEST_DIR . "/test_files/test.pdf", $dir . "/test.pdf");
        $vcs->createVersion($dir . "/test.pdf");
        $head_info = $vcs->headInfo();
        $timestamps["symlink"] = $head_info['TIMESTAMP'];
        $vcs->restoreVersion($timestamps["initial"]);
        $this->assertTrue(!file_exists($this->version_test_folder .
            "/test1.txt"),
            "After Restore later file not present 1");
        $vcs->restoreVersion($timestamps["test1.txt"]);
        $this->assertTrue(file_exists($this->version_test_folder .
            "/test1.txt"),
            "After restore file that should be present is");
        $this->assertTrue(!file_exists($this->version_test_folder .
            "/test2.txt"),
            "After Restore later file not present 2");
        $vcs->restoreVersion($timestamps["symlink"]);
        $this->assertTrue(file_exists($dir . "/test.pdf"),
            "After Restore symlink restored");
    }
    /**
     * Tests getting the active version of the repository at a given
     * timestamp and between a range of timestamps
     */
    public function versionGettersTestCase()
    {
        $vcs = new VersionManager($this->version_test_folder);
        $file_name = $this->version_test_folder . "/test.txt";
        $timestamps = [];
        $shift_timestamps = [];
        for ($i = 0; $i < 5; $i++) {
            $vcs->headPutContents($file_name, $i);
            $head_info = $vcs->headInfo();
            $timestamps[$i] = $head_info['TIMESTAMP'];
            usleep(20000);
            $shift_timestamps[$i] = microtime(true);
            $active_timestamp = $vcs->getActiveVersion($shift_timestamps[$i]);
            $this->assertEqual($active_timestamp, $timestamps[$i],
                "Active Timestamp $i correct");
        }
        $range_timestamps = $vcs->getVersionsInRange($shift_timestamps[1],
            $shift_timestamps[3]);
        $this->assertEqual($range_timestamps[0], $timestamps[2],
            "Range Timestamp 0 correct");
        $this->assertEqual($range_timestamps[1], $timestamps[3],
            "Range Timestamp 1 correct");
        $this->assertEqual(count($range_timestamps), 2,
            "Correct Number of Range Timestamps");
    }
}
