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
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;

/**
 * This is class is used to handle
 * db results related to Machine Administration
 *
 * @author Chris Pollett
 */
class MachineModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * @var array
     */
    public $search_table_column_map =  ["name" => "NAME"];
    /**
     * Called after getRows has retrieved all the rows that it would retrieve
     * but before they are returned to give one last place where they could
     * be further manipulated. This callback
     * is used to make parallel network calls to get the status of each machine
     * returned by getRows. The default for this method is to leave the
     * rows that would be returned unchanged
     *
     * @param array $rows that have been calculated so far by getRows
     * @return array $rows after this final manipulation
     *
     */
    public function postQueryCallback($rows)
    {
        return $this->getMachineStatuses($rows);
    }
    /**
     * Returns a list of the queue_server (not mirrors) names
     *
     * @return array of machine names
     */
    public function getQueueServerNames()
    {
        $db = $this->db;
        $sql = "SELECT NAME FROM MACHINE WHERE PARENT = '' ORDER BY NAME DESC";
        $result = $db->execute($sql);
        $names = [];
        while ($row = $db->fetchArray($result)) {
            $names[] = $row['NAME'];
        }
        return $names;
    }
    /**
     * Returns urls for all the queue_servers (not mirrors) stored in the DB
     *
     * @param string $crawl_time of a crawl to see the machines used in
     *     that crawl
     * @param int $channel only return QueueServers on this channel
     * @return array machine urls
     */
    public function getQueueServerUrls($crawl_time = 0, $channel = -1)
    {
        static $machines = [];
        $db = $this->db;
        if ($crawl_time == 0 && $channel == -1) {
            $crawl_time = -1;
        }
        if (isset($machines[$crawl_time])) {
            return $machines[$crawl_time];
        }
        $network_crawl_file = C\CRAWL_DIR . "/cache/" .
            self::network_base_name . $crawl_time . ".txt";
        $pre_machines = [];
        if ($crawl_time > 0 && file_exists($network_crawl_file)) {
            $info = unserialize(file_get_contents($network_crawl_file));
            if (isset($info["MACHINE_URLS"])) {
                $pre_machines = $info["MACHINE_URLS"];
            }
        }
        if ($channel >= 0) {
            $sql = "SELECT URL, PARENT FROM MACHINE WHERE CHANNEL = ? ".
                "ORDER BY NAME DESC";
            $result = $db->execute($sql, [$channel]);
        } else {
            $sql = "SELECT URL, PARENT FROM MACHINE ORDER BY NAME DESC";
            $result = $db->execute($sql);
        }
        $i = 0;
        $machines[$crawl_time] =[];
        while ($row = $db->fetchArray($result)) {
            if (!empty($row["URL"]) && $row["URL"] == "BASE_URL") {
                $row["URL"] = C\BASE_URL;
            }
            if (empty($row["PARENT"]) &&
                (empty($pre_machines) || in_array($row["URL"], $pre_machines))){
                $machines[$crawl_time][$i] = $row["URL"];
                $i++;
            }
        }
        unset($machines[$crawl_time][$i]); //last one will be null
        return $machines[$crawl_time];
    }
    /**
     * Check if there is a machine with $column equal to value
     *
     * @param mixed $fields field (string) or fields (array of strings) to
     *      use to look up machines  (either name, url, channel)
     * @param mixed $values value (string) or values (array of strings)
     *      for that field
     * @return bool whether or not has machine
     */
    public function checkMachineExists($fields, $values)
    {
        $db = $this->db;
        if (!is_array($fields)) {
            if (is_array($values)) {
                return false;
            }
            $fields = [$fields];
            $values = [$values];
        }
        $sql = "SELECT COUNT(*) AS NUM FROM MACHINE WHERE ";
        $and = "";
        foreach ($fields as $field) {
            $sql .= "$and $field=? ";
            $and = " AND ";
        }
        $result = $db->execute($sql, $values);
        if (!$row = $db->fetchArray($result)) {
            return false;
        }
        if ($row['NUM'] <= 0) {
            return false;
        }
        return true;
    }
    /**
     * Returns an array of channels used by at least one machine
     *
     * @return array of integer server labels
     */
    public function getChannels()
    {
        $db = $this->db;
        $sql = "SELECT DISTINCT CHANNEL FROM MACHINE
        WHERE CHANNEL >= 0 ORDER BY CHANNEL ASC";
        $result = $db->execute($sql);
        $labels = [];
        while ($row = $db->fetchArray($result)) {
            $labels[$row['CHANNEL']] = $row['CHANNEL'];
        }
        return $labels;
    }
    /**
     * Add a machine to the database using provided string
     *
     * @param string $name  the name of the machine to be added
     * @param string $url the url of this machine
     * @param int $channel - whether this machine is not running a
     *     queue_server or mirror (-1) and if latter
     *      what its channel is (value >=0)
     * @param int $num_fetchers - how many managed fetchers are on this
     *     machine.
     * @param string $parent - if this machine replicates some other machine
     *     then the name of the parent
     */
    public function addMachine($name, $url, $channel, $num_fetchers,
        $parent = "")
    {
        $db = $this->db;
        $sql = "INSERT INTO MACHINE VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [$name, $url, "$channel", $num_fetchers,
            $parent]);
    }
    /**
     * Delete a machine by its name
     *
     * @param string $machine_name the name of the machine to delete
     */
    public function deleteMachine($machine_name)
    {
        $sql = "DELETE FROM MACHINE WHERE NAME=?";
        $this->db->execute($sql, [$machine_name]);
    }
    /**
     *  Returns all the machine names stored in the DB
     *
     *  @return array machine names
     */
    public function getMachineList()
    {
        $machines = [];
        $sql = "SELECT * FROM MACHINE ORDER BY NAME DESC";
        $result = $this->db->execute($sql);
        $i = 0;
        while ($machines[$i] = $this->db->fetchArray($result)) {
            if ($machines[$i]['URL'] == "BASE_URL") {
                $machines[$i]['URL'] = C\BASE_URL;
            }
            $i++;
        }
        unset($machines[$i]); //last one will be null
        return $machines;
    }
    /**
     * Returns the statuses of machines in the machine table of their
     * fetchers and queue_server as well as the name and url's of these machines
     *
     * @param array $machines an array of machines to check the status for
     * @return array  a list of machines, together with all their properties
     * and the statuses of their fetchers and queue_servers
     */
    public function getMachineStatuses($machines = [])
    {
        $num_machines = count($machines);
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        for ($i = 0; $i < $num_machines; $i++) {
            if ($machines[$i]["URL"] == "BASE_URL") {
                $machines[$i]["URL"] = C\BASE_URL;
            }
            $hash_url = L\crawlHash($machines[$i]["URL"]);
            $machines[$i][CrawlConstants::URL] =
                $machines[$i]["URL"] . "?c=machine&a=statuses&time=$time".
                "&session=$session&arg=$hash_url";
        }
        $statuses = FetchUrl::getPages($machines);
        for ($i = 0; $i < $num_machines; $i++) {
            foreach ($statuses as $status) {
                if ($machines[$i][CrawlConstants::URL] ==
                    $status[CrawlConstants::URL]) {
                    $pre_status =
                        json_decode($status[CrawlConstants::PAGE], true);
                    if (!is_array($pre_status)) {
                        continue;
                    }
                    $out_status = [];
                    foreach ($pre_status as $pre_server => $value) {
                        $pre_server_parts = explode("-", $pre_server);
                        if (count($pre_server_parts) == 1) {
                            $out_status[$pre_server] = $value;
                        } else {
                            list($channel, $server) = $pre_server_parts;
                            if ($machines[$i]["CHANNEL"] == $channel) {
                                $out_status[$server] = $value;
                            }
                        }
                    }
                    if (is_array($pre_status)) {
                        $machines[$i]["STATUSES"] = $out_status;
                    } else {
                        $machines[$i]["STATUSES"] = "NOT_CONFIGURED_ERROR";
                    }
                }
            }
        }
        $sql = "SELECT * FROM ACTIVE_PROCESS";
        $result = $this->db->execute($sql);
        if (!$result) {
            return $machines;
        }
        $active_fetchers = [];
        $name_server_updater_on = false;
        while ($row = $this->db->fetchArray($result)) {
            for ($i = 0; $i < $num_machines; $i++) {
                if ($machines[$i]['NAME'] == $row['NAME']) {
                    if (isset($row['ID']) &&
                        isset($machines[$i]["STATUSES"][$row['TYPE']]) &&
                        !isset($machines[$i]["STATUSES"][$row['TYPE']][
                        $row['ID']])) {
                        $machines[$i]["STATUSES"][$row['TYPE']][
                            $row['ID']] = 0;
                    }
                    if ($machines[$i]['URL'] == C\NAME_SERVER && $row['TYPE'] ==
                        "MediaUpdater") {
                        $name_server_updater_on = true;
                    }
                }
                if ($row['NAME'] == "NAME_SERVER" && $row['TYPE'] ==
                    "MediaUpdater" && $row["ID"] == 0) {
                    $name_server_updater_on = true;
                }
            }
        }
        L\stringROrderCallback("", "", "NAME");
        if ($machines != []) {
            usort($machines, C\NS_LIB . "stringROrderCallback");
        }
        $name_server_statuses = CrawlDaemon::statuses();
        $machines['NAME_SERVER']['MEDIA_UPDATER_TURNED_ON'] =
            $name_server_updater_on;
        $machines['NAME_SERVER']['MediaUpdater'] = 0;
        if (isset($name_server_statuses['MediaUpdater'])) {
            $machines['NAME_SERVER']['MediaUpdater'] = 1;
            if (isset($name_server_statuses['MediaUpdater'][-1]) &&
                $name_server_statuses['MediaUpdater'][-1]) {
                $machines['NAME_SERVER']['MEDIA_UPDATER_TURNED_ON'] = 1;
            }
        }
        return $machines;
    }
    /**
     * Get either a fetcher or queue_server log for a machine
     *
     * @param string $machine_name the name of the machine to get the log file
     *      for
     * @param int $id  if a fetcher, which instance on the machine
     * @param string $type one of queue_server, fetcher, mirror,
     *      or MediaUpdater
     * @param string $filter only lines out of log containing this string
     *      returned
     * @return string containing the last MachineController::LOG_LISTING_LEN
     *     bytes of the log record
     */
    public function getLog($machine_name, $id, $type, $filter = "")
    {
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $name_server = ($machine_name == "NAME_SERVER");
        if ($name_server) {
            $row = ["URL" => C\NAME_SERVER, 'CHANNEL' => 0];
        } else {
            $sql =
                "SELECT URL, CHANNEL FROM MACHINE WHERE NAME= ?";
            $result = $this->db->execute($sql, [$machine_name]);
            $row = $this->db->fetchArray($result);
            if (!empty($row["URL"]) && $row["URL"] == "BASE_URL") {
                $row["URL"] = C\BASE_URL;
            }
        }
        if ($row) {
            $url = $row["URL"]. "?c=machine&a=log&time=$time".
                "&session=$session&f=" . urlencode($filter) .
                "&type=$type&id=$id&channel=" . $row['CHANNEL'];
            $log_page = FetchUrl::getPage($url);
            if (defined("ENT_SUBSTITUTE")) {
                $log_data = htmlentities(L\webdecode(json_decode($log_page)),
                    ENT_SUBSTITUTE);
            } else {
                $log_data = htmlentities(L\webdecode(json_decode($log_page)));
            }
        } else {
            $log_data = "";
        }
        return $log_data;
    }
    /**
     * Used to start or stop a queue_server, fetcher, mirror instance on
     * a machine managed by the current one
     *
     * @param string $machine_name name of machine
     * @param string $action "start" or "stop"
     * @param int $id id of process type to update (usually the number of a
     *  fetcher on a particular machine)
     * @param string $type type of process to change the status of
     *      QueueServer, Fetcher, MediaUpdater
     */
    public function update($machine_name, $action, $id, $type)
    {
        $db = $this->db;
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        if ($machine_name == "NAME_SERVER") {
            $row = ["URL" => C\NAME_SERVER, "PARENT" => ""];
        } else {
            $sql = "SELECT URL, CHANNEL, PARENT FROM MACHINE WHERE NAME=?";
            $result = $db->execute($sql, [$machine_name]);
            $row = $db->fetchArray($result);
            if (!empty($row["URL"]) && $row["URL"] == "BASE_URL") {
                $row["URL"] = C\BASE_URL;
            }
        }
        if (!empty($row)) {
            $row["CHANNEL"] = (!isset($row["CHANNEL"])) ? 0 : $row["CHANNEL"];
            $url = $row["URL"]. "?c=machine&a=update&time=$time".
                "&session=$session&action=$action&id=$id".
                "&type=$type&channel=" . $row["CHANNEL"];
            $sql = "DELETE FROM ACTIVE_PROCESS WHERE NAME=? AND
                ID=? AND TYPE=?";
            $db_type = $type;
            if ($type == "RestartFetcher") {
                $db_type = "Fetcher";
            }
            $db->execute($sql, [$machine_name, $id, $db_type]);
            if ($action == "start") {
                $sql = "INSERT INTO ACTIVE_PROCESS VALUES (?, ?, ?)";
                $db->execute($sql, [$machine_name, $id, $db_type]);
            }
            if ($type == "Mirror") {
                if ($row["PARENT"]) {
                    $sql = "SELECT URL FROM MACHINE WHERE NAME='".
                        $row["PARENT"] ."'";
                    $result = $this->db->execute($sql);
                    if ($result &&
                        $parent_row = $this->db->fetchArray($result)) {
                        if (!empty($parent_row["URL"]) &&
                            $parent_row["URL"] == "BASE_URL") {
                            $parent_row["URL"] = C\BASE_URL;
                        }
                        $url .= "&parent=" . L\webencode($parent_row["URL"]);
                    }
                }
            }
            FetchUrl::getPage($url);
        }
    }
    /**
     * Used to restart any fetchers which the user turned on, but which
     * happened to have crashed. (Crashes are usually caused by CURL or
     * memory issues)
     */
    public function restartCrashedFetchers()
    {
        $machine_list = $this->getMachineList();
        $machines = $this->getMachineStatuses($machine_list);
        foreach ($machines as $machine) {
            if (isset($machine["STATUSES"]["Fetcher"])) {
                $fetchers = $machine["STATUSES"]["Fetcher"];
                foreach ($fetchers as $id => $status) {
                    if ($status === 0) {
                        $this->update($machine["NAME"], "start", $id,
                            "RestartFetcher");
                    }
                }
            }
        }
    }
    /**
     * Returns a list of the media jobs present on this server and
     * whether they are running
     *
     * @return array [job_name => status, ...]
     */
    public function getJobsList()
    {
        $job_path = C\BASE_DIR . "/library/media_jobs/";
        $app_job_path = C\APP_DIR ."/library/media_jobs/";
        $job_file_folders = [ $job_path => glob("$job_path*Job.php") ,
            $app_job_path => glob("$app_job_path*Job.php")];
        $jobs_list = [];
        foreach ($job_file_folders as $folder => $job_files) {
            foreach ($job_files as $job_path) {
                $job = $this->getJobNameFromPath($job_path);
                if ($job == 'Media') {
                    continue;
                }
                if (!isset($jobs_list[$job])) {
                    $jobs_list[$job] = $this->getJobStatus($job);
                }
            }
        }
        ksort($jobs_list);
        return $jobs_list;
    }
    /**
     * Returns whether or not a media job is currently scheduled to
     * be periodically runn
     *
     * @param string $job the job to see if running or not
     * @return bool whether scheduled ot be periodically run or not
     */
    public function getJobStatus($job)
    {
        $job_dir = C\WORK_DIRECTORY . "/schedules/jobs";
        $job_file = $job_dir . "/$job.txt";
        if (!file_exists($job_file)) {
            $this->createIfNecessaryDirectory($job_dir);
            file_put_contents($job_file, serialize(true));
            chmod($job_file, 0777);
        }
        return unserialize(file_get_contents($job_file));
    }
    /**
     * Sets whether a media job should be periodically run or nott
     *
     * @param string $job the job to see if running or not
     * @param bool $status (true or non-empty) means periodically run the
     *      job, false means don't run hte job.
     */
    public function setJobStatus($job, $status)
    {
        $status = empty($status) ? false : true;
        $job_dir = C\WORK_DIRECTORY  . "/schedules/jobs";
        $job_file = $job_dir . "/$job.txt";
        $this->createIfNecessaryDirectory($job_dir);
        file_put_contents($job_file, serialize($status));
        chmod($job_file, 0777);
    }
    /**
     *  Returns the name of a job from its class file path
     *
     * @param string $job_path class file path of job
     * @return string name of a job
     */
    private function getJobNameFromPath($job_path)
    {
        $job = pathinfo($job_path, \PATHINFO_FILENAME);
        if (empty($job) || substr($job, -3) != 'Job') {
            return false;
        }
        return substr($job, 0, -3);
    }
}
