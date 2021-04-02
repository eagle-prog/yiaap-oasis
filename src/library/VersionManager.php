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
namespace seekquarry\yioop\library;

/**
 * VersionManager can be used to create and manage versions of files in a folder
 * so that a user can revert the files to any version desired back to the
 * time the folder under manager was first managed. It is used by Yioop's
 * Wiki system to handle versions of image and other media resources for a
 * Wiki page.
 *
 * @author Chris Pollett
 */
class VersionManager
{
    /**
     *  Return code constants for public VersionManager methods
     */
    const RENAME_FAILED = -12;
    const PUT_CONTENTS_FAILED = -11;
    const MAKE_DIR_FAILED = -10;
    const DELETE_FAILED = -9;
    const COPY_FAILED = -8;
    const UNMANAGED_FILE_LOOK_UP = -7;
    const TARGET_LOCATION_ERROR = -6;
    const INVALID_DIR_ENTRY = -5;
    const HASH_FILE_NOT_FOUND = -4;
    const VERSION_LOOK_UP_FAIL = -3;
    const HASH_LOOKUP_FAIL = -2;
    const LOCK_FAIL = -1;
    const SUCCESS = 1;
    /**
     * Name of subfolder in which to store files when a version of the managed
     * folder is created.
     * @var string
     */
    public $archive_name;
    /**
     * Filesystem path to the archive folder
     * @var string
     */
    public $archive_path;
    /**
     * Filesystem path to file that is used for locking whether a new
     * VersionManager public method is allowed to manipulate files in the
     * archive.
     * @var string
     */
    public $lock_file;
    /**
     * Path to folder in archive in which a list of all versions is maintained
     * @var string
     */
    public $versions_path;
    /**
     * Hash algorithm to be applied to folder's files inorder to come up with
     * a name to store in a version in the archive.
     * @var string
     */
    public $hash_algorithm;
    /**
     * Folder that is being managed (prior versions of files in it being
     * maintained) by this VersionManager instance
     * @var string
     */
    public $managed_folder;
    /**
     * File system permissions to use when storing version files into the
     * version archive. If <=0 then use default file permissions
     * @var string
     */
    public $permissions;
    /**
     * Creates an object which can be used to manage multiple versions of
     * files in a managed folder, storing prior version in an archive folder
     * using a hash_algorithm to determine how to name these archived files
     * and saving these archived files according to some file system
     * permissions.
     *
     * @param string $managed_folder what folder should be managed with
     *      this versioning system
     * @param string $archive_name file_name in the folder to use for the
     *      subfolder containing the archived versions of files
     * @param string $hash_algorithm what hash algorithm should be used to
     *      generate archive filenames. Defaults to sha256
     * @param int $permissions what to set the file permissions to for the
     *      archive file. To keep things simple this defaults to world
     *      read write. In practice you probably want to tailor this to
     *      the situation for security. If you set the value to <= 0
     *      the permissions will be whatever your OS would use by default
     */
    public function __construct($managed_folder = '.',
        $archive_name = '.archive', $hash_algorithm = 'sha256',
        $permissions = 0777)
    {
        $this->managed_folder = realpath($managed_folder);
        if (empty($this->managed_folder)) {
             return;
        }
        $this->archive_name = $archive_name;
        $this->archive_path = $this->managed_folder . "/$archive_name";
        $this->versions_path = $this->archive_path . "/versions";
        $this->lock_file = $this->archive_path . "/LOCK";
        $this->hash_algorithm = $hash_algorithm;
        $this->permissions = $permissions;
        if (!file_exists($this->archive_path)) {
            foreach ([$this->archive_path, $this->versions_path]
                as $path) {
                if (!file_exists($path)) {
                    mkdir($path);
                    if ($this->permissions > 0) {
                        chmod($path, $this->permissions);
                    }
                }
            }
            $this->createVersion("", "", 1);
        }
    }
    /**
     * If $file_changed is not a subpath of $folder then only the $folder file
     * is involved in new version. I.e.,  a single repository dir file for
     * folder will be made. If $file_changed is a nonexistent file in $folder
     * then the dir's in path to $file_changed will be updated.
     *
     * @param string $file_changed
     * @param string $folder
     * @param int $now
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @param array $force_update_list
     * @return mixed either an error code or the name of the hash_file in
     *      the repository for the version just created
     */
    public function createVersion($file_changed = "", $folder = "", $now = 0,
        $lock = true, $force_update_list = [])
    {
        if (empty($this->managed_folder)) {
             return self::LOCK_FAIL;
        }
        if ($now == 0) {
            $now = microtime(true);
        }
        if (empty($file_changed)) {
            $file_changed = $this->managed_folder;
        }
        if (empty($folder)) {
            $folder = $this->managed_folder;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        if ($lock) {
            file_put_contents($lock_file, $now);
        }
        $file_changed = realpath($file_changed);
        $folder_files = glob("$folder/*");
        $version = [];
        $file_list = [];
        foreach ($folder_files as $file) {
            // not allowed to archive the archive
            if (strpos($file, $this->archive_path) === 0) {
                continue;
            }
            $file_name = basename($file);
            $extension = (is_dir($file)) ? "d" : (is_link($file) ? "l" : "f");
            list($hash_time_file_name, $hash_time_path) =
                $this->getArchiveFileAndPath($file, 0, true);
            $handled = false;
            $hash_time_file = "$hash_time_path/$hash_time_file_name.t";
            if (file_exists($hash_time_file) &&
                strpos($file_changed, $file) === false &&
                !in_array($file, $force_update_list)) {
                $last_change = file_get_contents($hash_time_file);
                list($hash_dir_name, $hash_dir_path) =
                    $this->getArchiveFileAndPath($file, trim($last_change),
                        true);
                if (file_exists("$hash_dir_path/$hash_dir_name.$extension")) {
                    $file_list[$file_name] = "$hash_dir_name.$extension";
                    $handled = true;
                }
            }
            if (!$handled) {
                file_put_contents($hash_time_file, $now);
                if ($extension == 'd') {
                    $hash_dir_name =
                        $this->createVersion($file_changed, $file, $now,
                        false, $force_update_list);
                    $file_list[$file_name] = "$hash_dir_name.d";
                } else if ($extension == 'l') {
                    $link_path = readlink($file);
                    $file_list[$file_name] = "$link_path.l";
                } else {
                    list($hash_file, $hash_path) = $this->getArchiveFileAndPath(
                        $file, $now, true);
                    link($file, "$hash_path/$hash_file.f");
                    $file_list[$file_name] = "$hash_file.f";
                }
            }
        }
        $version['TIMESTAMP'] = $now;
        $version['FILES'] = $file_list;
        $serial_version = serialize($version);
        list($hash_folder_name, $hash_folder_path) =
            $this->getArchiveFileAndPath($folder, $now, true);
        $hash_folder = "$hash_folder_path/$hash_folder_name.d";
        file_put_contents($hash_folder, $serial_version);
        list($hash_time_file_name, $hash_time_path) =
            $this->getArchiveFileAndPath($folder, 0, true);
        file_put_contents("$hash_time_path/$hash_time_file_name.t", $now);
        if ($folder == $this->managed_folder) {
            link($hash_folder, $this->getVersionPath($now, true) .
                "/$now");
            $most_recent_file = "{$this->versions_path}/HEAD";
            if (file_exists($most_recent_file)) {
                unlink($most_recent_file);
            }
            link($hash_folder, $most_recent_file);
        }
        if ($lock) {
            unlink($lock_file);
        }
        return $hash_folder_name;
    }
    /**
     * Read from the head version of the repository the contents of $file.
     *
     * @param string $file name of file to get contents of
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headGetContents($file, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::UNMANAGED_FILE_LOOK_UP;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        if (strpos($file, $this->managed_folder) !== 0) {
            return self::UNMANAGED_FILE_LOOK_UP;
        }
        return file_get_contents($file);
    }
    /**
     * Write $data into the file $file in the head version of the repository
     *
     * @param string $file name of file to store data for
     * @param string $data what to store in the file $file
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headPutContents($file, $data, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::PUT_CONTENTS_FAILED;
        }
        if (strpos($file, $this->managed_folder) !== 0) {
            return self::UNMANAGED_FILE_LOOK_UP;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        if (file_exists($file)) {
            unlink($file);
        }
        if (file_put_contents($file, $data) === false) {
            if ($lock) {
                unlink($lock_file);
            }
            return self::PUT_CONTENTS_FAILED;
        }
        $this->createVersion($file, "", 0, false);
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Copies the data in a file $from_name in the head version of the
     * repository to a different file named $to_name
     *
     * @param string $from_name name of file or dir to copy
     * @param string $to_name name of file or dir to save copy to
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headCopy($from_name, $to_name, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::COPY_FAILED;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        if (file_exists($from_name)) {
            $from_name = realpath($from_name);
            copy($from_name, $to_name);
            $to_name = realpath($to_name);
            if (!file_exists($to_name)) {
                if ($lock) {
                    unlink($lock_file);
                }
                return self::COPY_FAILED;
            }
        }
        $this->createVersion($to_name, "", 0, false);
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Deletes a file $file from the version of the repository
     *
     * @param string $file name of file to delete
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headDelete($file, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::DELETE_FAILED;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        if (file_exists($file)) {
            $file = realpath($file);
            if (is_file($file)) {
                unlink($file);
            } else {
                rmdir($file);
            }
            if (file_exists($file)) {
                if ($lock) {
                    unlink($lock_file);
                }
                return self::DELETE_FAILED;
            }
        }
        $this->createVersion($file, "", 0, false);
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Makes a directory named $dir in the current folder in the head
     * version of the repository being managed
     *
     * @param string $dir name of directory folder to make
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headMakeDirectory($dir, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::MAKE_DIR_FAILED;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        if(!mkdir($dir)) {
            if ($lock) {
                unlink($lock_file);
            }
            return self::MAKE_DIR_FAILED;
        }
        $this->createVersion($dir, "", 0, false);
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Renames the file $old_name in the head version of the
     * repository to a different file named $new_name
     *
     * @param string $old_name original name of file
     * @param string $new_name what to change it to
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return int success code
     */
    public function headRename($old_name, $new_name, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::RENAME_FAILED;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        if (file_exists($old_name)) {
            $old_name = realpath($old_name);
            rename($old_name, $new_name);
            $new_name = realpath($new_name);
            if (file_exists($new_name)) {
                $this->createVersion($old_name, "", 0, false);
                $this->createVersion($new_name, "", 0, false);
            } else {
                if ($lock) {
                    unlink($lock_file);
                }
                return self::RENAME_FAILED;
            }
        }
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Returns the files in the root directory in the most recent version of the
     * repository together with a TIMESTAMP of the date when the most recent
     * version was made.
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return mixed either any array [TIMESTAMP => time of last version,
     *      FILES => files in last version's folder] or LOCK_FAIL error code
     */
    public function headInfo($lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::LOCK_FAIL;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        $version_path = $this->archive_path . "/versions";
        return unserialize(file_get_contents("$version_path/HEAD"));
    }
    /**
     * Retrieves the contents of a file from a particular version of the
     * repository
     * @param string $file name of file to get data about
     * @param int $timestamp which version want to get file out of
     * @param bool $get_nearest_version if true then if $timestamp doesn't
     *      exist as a version get the nearest version after $timestamp
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @return mixed either a string with the file's data or an error code
     */
    public function versionGetContents($file, $timestamp,
        $get_nearest_version = false, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::HASH_FILE_NOT_FOUND;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        $path_info =
            $this->getHashNamePath($file, $timestamp, $get_nearest_version);
        if(!is_array($path_info)) {
            return self::HASH_LOOKUP_FAIL;
        }
        list($type, $hash_name, $archive_path) = $path_info;
        $hash_file = "$archive_path/$hash_name.$type";
        if ($type == 'l') {
            $hash_file = $hash_name;
        }
        if (file_exists($hash_file)) {
            $data = file_get_contents($hash_file);
            if ($type == 'd') {
                $data = unserialize($data);
            }
            return [$type, $data];
        } else {
            return self::HASH_FILE_NOT_FOUND;
        }
    }
    /**
     * Restores the version of the repository that existed at a timestamp to
     * the managed folder. Files currently in the managed folder before the
     * restored but which exist in the HEAD version of the repository are
     * removed from the managed folder (kept in repository).
     *
     * @param int $timestamp of version what to restore to
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     * @param bool $force_lock whether or not any existing lock should be
     *      ignored
     * @return int success code
     */
    public function restoreVersion($timestamp, $lock = true,
        $force_lock = false)
    {
        if (empty($this->managed_folder)) {
            return self::VERSION_LOOK_UP_FAIL;
        }
        $lock_file = $this->lock_file;
        if (!$force_lock && $lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        file_put_contents($lock_file, microtime(true));
        $version_timestamp = $this->getActiveVersion($timestamp, false);
        if ($version_timestamp == self::VERSION_LOOK_UP_FAIL) {
            if ($lock || ($force_lock && file_exists($lock_file))) {
                unlink($lock_file);
            }
            return self::VERSION_LOOK_UP_FAIL;
        }
        $this->unlinkHead(false);
        $restored_files = $this->copyFromVersionOrHashName(
            $this->managed_folder, dirname($this->managed_folder),
            $version_timestamp, "", false);
        if (!is_array($restored_files)) {
            $restored_files = [];
        }
        $version_path = $this->getVersionPath($version_timestamp);
        $most_recent_file = "{$this->versions_path}/HEAD";
        if (file_exists($most_recent_file)) {
            if ($lock || ($force_lock && file_exists($lock_file))) {
                unlink($lock_file);
            }
            unlink($most_recent_file);
        }
        link("$version_path/$version_timestamp", $most_recent_file);
        $this->createVersion("", "", 0, false, $restored_files);
        if (file_exists($lock_file) && ($lock || $force_lock)) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Gets the most recent version timestamp of a version in the repository
     * that is less than or equal to the searched for timestamp.
     * @param int $search_timestamp want to find the version in the repository
     *      closest to, but not exceeding this value.
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     */
    public function getActiveVersion($search_timestamp, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::VERSION_LOOK_UP_FAIL;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        $search_year = intval(date('Y', $search_timestamp));
        $search_day = intval(date('z', $search_timestamp));
        $version_paths = glob("$this->versions_path/*");
        $max_year_path = "";
        $max_year = self::VERSION_LOOK_UP_FAIL;
        $next_max_year = self::VERSION_LOOK_UP_FAIL;
        $next_year_path = "";
        foreach ($version_paths as $version_path) {
            $year = basename($version_path);
            if (!is_numeric($year)) {
                continue;
            }
            $year = intval($year);
            if ($year > $max_year && $year <= $search_year) {
                $next_year_path = $max_year_path;
                $max_year_path = $version_path;
                $next_max_year = $max_year;
                $max_year = $year;
            }
        }
        if ($max_year == self::VERSION_LOOK_UP_FAIL) {
            if ($lock && file_exists($lock_file)) {
                return self::LOCK_FAIL;
            }
            return self::VERSION_LOOK_UP_FAIL;
        }
        $check_years = [ $next_max_year =>  $next_year_path,
            $max_year => $max_year_path];
        if ( $next_max_year == self::VERSION_LOOK_UP_FAIL) {
            $check_years = [$max_year => $max_year_path];
        }
        $next_day_path = "";
        $max_day_path = "";
        $next_max_day = self::VERSION_LOOK_UP_FAIL;
        $max_day = self::VERSION_LOOK_UP_FAIL;
        $max_year = self::VERSION_LOOK_UP_FAIL;
        foreach ($check_years as $year => $year_path) {
            $day_paths = glob("$year_path/*");
            foreach ($day_paths as $day_path) {
                $day = intval(basename($day_path));
                if($search_year == $year && $day > $search_day) {
                    continue;
                }
                if ($day > $max_day || $year > $max_year) {
                    $max_year = $year;
                    $next_day_path = $max_day_path;
                    $max_day_path = $day_path;
                    $next_max_day = $max_day;
                    $max_day = $day;
                }
            }
        }
        if ($max_day == self::VERSION_LOOK_UP_FAIL) {
            if ($lock && file_exists($lock_file)) {
                return self::LOCK_FAIL;
            }
            return self::VERSION_LOOK_UP_FAIL;
        }
        $check_days = [ $next_max_day =>  $next_day_path,
            $max_day => $max_day_path];
        if ($next_max_day == self::VERSION_LOOK_UP_FAIL) {
            $check_days = [$max_day => $max_day_path];
        }
        $max_timestamp = self::VERSION_LOOK_UP_FAIL;
        foreach ($check_days as $day => $day_path) {
            $timestamp_paths = glob("$day_path/*");
            foreach ($timestamp_paths as $timestamp_path) {
                $timestamp = floatval(basename($timestamp_path));
                if ($timestamp > $search_timestamp) {
                    continue;
                }
                if ($timestamp > $max_timestamp) {
                    $max_timestamp = $timestamp;
                }
            }
        }
        return $max_timestamp;
    }
    /**
     * Gets all the versions times that exist in the repository and which are
     * between in time two values.
     *
     * @param int $start_time look for timestamps in repository above or equal
     *      this value
     * @param int $end_time look for timestamps in repository below or equal
     *      this value
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     */
    public function getVersionsInRange($start_time, $end_time, $lock = true)
    {
        if (empty($this->managed_folder)) {
            return self::LOCK_FAIL;
        }
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        $filtered_versions = [];
        $version_paths = glob("$this->versions_path/*");
        $start_year = intval(date('Y', $start_time));
        $start_day = intval(date('z', $start_time));
        $end_year = intval(date('Y', $end_time));
        $end_day = intval(date('z', $end_time));
        foreach ($version_paths as $version_path) {
            $year = basename($version_path);
            if (!is_numeric($year)) {
                continue;
            }
            $year = intval($year);
            if ($year >= $start_year &&
                $year <= $end_year) {
                $days_path = glob("$version_path/*");
                foreach ($days_path as $day_path) {
                    $day = intval(basename($day_path));
                    if (($year == $start_year && $day < $start_day) ||
                        ($year == $end_year && $day > $end_day)) {
                        continue;
                    }
                    $timestamp_paths = glob("$day_path/*");
                    foreach ($timestamp_paths as $timestamp_path) {
                        $timestamp = floatval(basename($timestamp_path));
                        if ($timestamp >= $start_time &&
                            $timestamp <= $end_time) {
                            $filtered_versions[] = $timestamp;
                        }
                    }
                }
            }
        }
        return $filtered_versions;
    }
    /**
     * Get the path in the repository archive that corresponds to the given
     * hash name of an object that might be in the repository.
     * Currently, the archive consists of two nested folders based on prefixes
     * of objects stored in the repository, so this method calculates those
     * prefixes and tacks them on to the archive path.
     *
     * @param string $hash_name to make a archive path for
     * @return string path to $hash_name object in repository archive
     */
    protected function getArchivePathHashName($hash_name)
    {
        $first_prefix = substr($hash_name, 0, 2);
        $second_prefix = substr($hash_name, 2, 2);
        $archive_path = $this->archive_path . "/$first_prefix/$second_prefix";
        return $archive_path;
    }
    /**
     * Gets the hash file name and path within the archive repository for
     * a file name from the managed folder that existed at timestamp
     *
     * @param string $file name of file want to get the archive name and
     *      archive path for
     * @param int $timestamp of version want to compute archive filename and
     *      path for
     * @param bool $make_path whether to make folders (if they don't exists
     *      already) in the archive repository for the path calculated
     * @return array [hash_name, archive_path] to use for file in the
     *      repository for object given that version timestamp
     */
    protected function getArchiveFileAndPath($file, $timestamp,
        $make_path = false)
    {
        $hash_name = hash($this->hash_algorithm, $file . $timestamp);
        $first_prefix = substr($hash_name, 0, 2);
        $second_prefix = substr($hash_name, 2, 2);
        $archive_path = $this->archive_path;
        foreach ([$first_prefix, $second_prefix] as $prefix) {
            $archive_path .= "/$prefix";
            if ($make_path && !file_exists($archive_path)) {
                mkdir($archive_path);
                if ($this->permissions > 0) {
                    chmod($archive_path, $this->permissions);
                }
            }
        }
        return [$hash_name, $archive_path];
    }
    /**
     * Versions are stored in the version subfolder of the archive repository
     * within a year folder within a day folder. Given a timestamp this
     * function returns the path of the version folder it would correspond to
     * @param int $timestamp to find version folder for
     *
     * @param bool $make_path whether to make folders (if they don't exists
     *      already) in the archive repository for the path calculated
     * @return string path to version folder
     */
    protected function getVersionPath($timestamp, $make_path = false)
    {
        $version_path = $this->archive_path . "/versions";
        $year = date('Y', $timestamp);
        $day = date('z', $timestamp);
        foreach ([$year, $day] as $prefix) {
            $version_path .= "/$prefix";
            if ($make_path && !file_exists($version_path)) {
                mkdir($version_path);
                if ($this->permissions > 0) {
                    chmod($version_path, $this->permissions);
                }
            }
        }
        return $version_path;
    }
    /**
     * Given a file or directory and a timestamp finds the path to that
     * file in the repository by tranversing the repository and looking
     * the hash names of folders subfolders in the repository.
     * The timestamp that is lookedup might not be the timestamp of the file
     * because when created a version that file might not have changed so
     * its old info is copied into the new version. This is why a traversal
     * might be needed.
     *
     * @param string $file name of file to get path for
     * @param int $timestamp which version in repository want to get file
     *      for
     * @param bool $get_nearest_version if true then if $timestamp doesn't
     *      exist as a version get the nearest version after $timestamp
     * @param string $path_so_far path in managed folder that this recursive
     *      procedure has already traversed
     * @param string $hash_path_so_far corresponding path to path_so_far but
     *      in the archive repository
     * @return string path to file in the archive repository
     */
    protected function getHashNamePath($file, $timestamp,
        $get_nearest_version = false, $path_so_far = "", $hash_path_so_far = "")
    {
        if (file_exists($file)) {
            $file = realpath($file);
        }
        if ($get_nearest_version) {
            $timestamp = $this->getActiveVersion($timestamp);
        }
        if (empty($path_so_far)) {
            $path_so_far = $this->managed_folder;
        }
        if (strpos($file, $path_so_far) !== 0) {
            return self::UNMANAGED_FILE_LOOK_UP;
        }
        if ($hash_path_so_far == "") {
            list($file_name, $archive_path) =
                $this->getArchiveFileAndPath($path_so_far, $timestamp);
        } else {
            $file_name = $hash_path_so_far;
            $archive_path = $this->getArchivePathHashName($hash_path_so_far);
        }
        if ($file == $path_so_far) {
            foreach (['d', 'f'] as $type) {
                if (file_exists("$archive_path/$file_name.$type")) {
                    return [$type, $file_name, $archive_path];
                }
            }
            return self::INVALID_DIR_ENTRY;
        }
        $hash_path_so_far = "$archive_path/$file_name.d";
        if (!file_exists($hash_path_so_far)) {
            return self::HASH_LOOKUP_FAIL;
        }
        $path_so_far_contents = unserialize(
            file_get_contents($hash_path_so_far));
        $rest_path = substr($file, strlen($path_so_far) + 1);
        list($next_folder_name,) = explode("/", $rest_path, 2);
        if (empty($path_so_far_contents['FILES'][$next_folder_name])) {
            return self::INVALID_DIR_ENTRY;
        }
        $next_hash_info = $path_so_far_contents['FILES'][$next_folder_name];
        $next_hash_name = substr($next_hash_info, 0, -2);
        $next_type = substr($next_hash_info, -1);
        if ($next_type == 'l' && "$path_so_far/$next_folder_name" == $file) {
            return [$next_type, $next_hash_name, ""];
        }
        $path_info = $this->getHashNamePath($file, $timestamp, false,
            "$path_so_far/$next_folder_name", $next_hash_name);
        return $path_info;
    }
    /**
     * Delete all the files from the managed folder which exist in the HEAD
     * version in the archive repository
     *
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     */
    protected function unlinkHead($lock = true)
    {
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        if ($lock) {
            file_put_contents($lock_file, microtime(true));
        }
        $this->traverseUnlinkHead($this->managed_folder);
        if ($lock) {
            unlink($lock_file);
        }
        return self::SUCCESS;
    }
    /**
     * Copies to the target folder in the managed folder a file or
     * directory that existed at a given timestamp in the archive repository
     * @param string $file file name of file or direcotry to copy to managed
     *      folder
     * @param string $target folder to copy to
     * @param int $timestamp which archive version of the file to copy
     * @param string $hash_name_type either f, l, or d depending on whether
     *      the object in the repository is known to be a file, link, or folder.
     *      If left empty then this is looked up in the repository
     * @param bool $lock whether or not a lock should be obtained before
     *      carrying out the operation
     */
    protected function copyFromVersionOrHashName($file, $target, $timestamp,
        $hash_name_type = "", $lock = true)
    {
        $lock_file = $this->lock_file;
        if ($lock && file_exists($lock_file)) {
            return self::LOCK_FAIL;
        }
        if ($lock) {
            file_put_contents($lock_file, microtime(true));
        }
        if (file_exists($file)) {
            $file = realpath($file);
        }
        if (strpos($file, $this->managed_folder) !== 0) {
            if ($lock) {
                unlink($lock_file);
            }
            return self::UNMANAGED_FILE_LOOK_UP;
        }
        if (!file_exists($target)) {
            if ($lock) {
                unlink($lock_file);
            }
            return self::TARGET_LOCATION_ERROR;
        }
        if (empty($hash_name_type)) {
            $path_info =
                $this->getHashNamePath($file, $timestamp);
            if(!is_array($path_info)) {
                if ($lock) {
                    unlink($lock_file);
                }
                return self::HASH_LOOKUP_FAIL; // return error we got
            }
            list($type, $hash_name, $archive_path) = $path_info;
        } else {
            $type = substr($hash_name_type, -1);
            $hash_name = substr($hash_name_type, 0, -2);
            $archive_path = $this->getArchivePathHashName($hash_name);
        }
        $target_name = basename($file);
        $target_file = "$target/$target_name";
        if ($type == 'f') {
            link("$archive_path/$hash_name.$type", $target_file);
        }
        if ($type == 'l') {
            symlink($hash_name, $target_file);
        }
        $contents = ['FILES' => []];
        if (file_exists("$archive_path/$hash_name.$type")) {
            $contents = unserialize(
                file_get_contents("$archive_path/$hash_name.$type"));
            if (!isset($contents['FILES'])) {
                if ($lock) {
                    unlink($lock_file);
                }
                return self::INVALID_DIR_ENTRY;
            }
        }
        if (!file_exists($target_file) && !mkdir($target_file)) {
            if ($lock) {
                unlink($lock_file);
            }
            return self::TARGET_LOCATION_ERROR;
        }
        $result = [$target_file];
        foreach ($contents['FILES'] as $file_name => $hash_name_type) {
            $archive_path = $this->getArchivePathHashName($hash_name_type);
            $type = substr($hash_name_type, -1);
            $hash_name = substr($hash_name_type, 0, -2);
            if ($type == 'f') {
                $result[] = "$target_file/$file_name";
                link("$archive_path/$hash_name_type",
                    "$target_file/$file_name");
            } else if ($type == 'l') {
                $result[] = "$target_file/$file_name";
                symlink($hash_name, $target_file);
            } else if ($type == 'd') {
                $sub_result = $this->copyFromVersionOrHashName(
                    "$file/$file_name", "$target_file", $timestamp,
                    $hash_name_type, false);
                if (!is_array($sub_result)) {
                    if ($lock) {
                        unlink($lock_file);
                    }
                    return $sub_result;
                }
                $result = array_merge($result, $sub_result);
            }
        }
        if ($lock) {
            unlink($lock_file);
        }
        return $result;
    }
    /**
     * Recursively traverse a directory structure and call a callback function
     *
     * @param string $dir name of folder to delete
     * @param int $timestamp only deletes if the file existed in the version
     *      given by the timestamp in the repository (by default this is
     *      the timestamp asscoaited with the HEAD version)
     */
    protected function traverseUnlinkHead($dir, $timestamp = 0)
    {
        if ($timestamp == 0) {
            $info = $this->headInfo(false);
            if (empty($info['TIMESTAMP'])) {
                return  self::VERSION_LOOK_UP_FAIL;
            }
            $timestamp = $info['TIMESTAMP'];
        }
        // single not directory case
        if (!is_dir($dir)) {
            $this->deleteVersionFileOrFolder($dir, $timestamp);
            return self::SUCCESS;
        }
        if (!$dh = @opendir($dir)) {
            return self::VERSION_LOOK_UP_FAIL;
        }
        while (($obj = readdir($dh)) !== false) {
            if (in_array($obj, ['.', '..', $this->archive_name,
                $this->lock_file])) {
                continue;
            }
            if (is_dir($dir . '/' . $obj)) {
                $this->traverseUnlinkHead($dir.'/'.$obj, $timestamp);
            }
            $obj_results = @$this->deleteVersionFileOrFolder($dir . '/' . $obj,
                $timestamp);
        }
        closedir($dh);
        return self::SUCCESS;
    }
    /**
     * This function is used in the process of recursively deleting a
     * directory
     *
     * @param string $file_or_dir the filename or directory name to be deleted
     * @param int $timestamp only deletes if the file existed in the version
     *      given by the timestamp in the repository
     */
    protected function deleteVersionFileOrFolder($file_or_dir, $timestamp = 0)
    {
        if ($timestamp == 0) {
            $info = $this->headInfo();
            if (empty($info['TIMESTAMP'])) {
                return  self::VERSION_LOOK_UP_FAIL;
            }
            $timestamp = $info['TIMESTAMP'];
        }
        if (file_exists($file_or_dir)) {
            $path_info = $this->getHashNamePath($file_or_dir, $timestamp,
                false);
            if (!is_array($path_info)) {
                return $path_info;
            }
            if (is_file($file_or_dir) || is_link($file_or_dir)) {
                unlink($file_or_dir);
            } else {
                if (count(scandir($file_or_dir)) == 2) {
                    rmdir($file_or_dir);
                }
            }
        }
        return self::SUCCESS;
    }
}
