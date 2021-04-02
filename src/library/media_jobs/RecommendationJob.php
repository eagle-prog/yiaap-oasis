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
 * @author Sarika Padmashali padmashalisarika@gmail.com
 * (Reworked so could scale for yioop.com by Chris Pollett)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\library\media_jobs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\models\CronModel;

/**
 * Recommendation Job recommends the trending threads as well
 * as threads and groups which are relevant based on the
 * users viewing history
 */
class RecommendationJob extends MediaJob
{
    /**
     * Time in current epoch when analytics last updated
     * @var int
     */
    public $update_time;
    /**
     * Used to track what is the active recommendation timestamp
     * @var int
     */
    public $active_time;
    /**
     * Associative array of the number of items a term appears in
     * @var array
     */
    public $item_idf;
    /**
     * Associative array of the number of user views a term appears in
     * @var array
     */
    public $user_idf;
    /**
     * Number of inserts to try to group into a single insert statement
     * before execution
     */
    const BATCH_SQL_INSERT_NUM = 500;
    /**
     * Maximum number of group items used in making recommendations
     */
    const MAX_GROUP_ITEMS = 50000;
    /**
     * Maximum number of terms used in making recommendations
     */
    const MAX_TERMS = 20000;
    /**
     * Sets up the database connection so can access tables related
     * to recommendations. Initialize timing info related to job.
     */
    public function init()
    {
        $this->update_time = 0;
        $this->active_time = 0;
        $this->name_server_does_client_tasks = true;
        $this->name_server_does_client_tasks_only = true;
        $this->cron_model = new CronModel();
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
    }
    /**
     * Only update if its been more than an hour since the last update
     *
     * @return bool whether its been an hour since the last update
     */
    public function checkPrerequisites()
    {
        $time = time();
        $delta = $time - $this->update_time;
        if ($delta > C\ONE_DAY) {
            $this->update_time = $time;
            L\crawlLog("Prerequisites for Recommendation Media Job met");
            return true;
        }
        L\crawlLog("Time since last update not exceeded, skipping".
            " Recommendation MediaJob $delta");
        return false;
    }
    /**
     * For now analytics update is only done on name server as Yioop
     * currently only supports one DBMS at a time.
     */
    public function nondistributedTasks()
    {
        L\crawlLog("Performing the Recommendation Media Job");
        $this->active_time = $this->cron_model->getCronTime(
            "item_group_recommendations");
        L\crawlLog("Current Active Recommendation Timestamp: ".
            $this->active_time);
        L\crawlLog("...Clearing last run's intermediate results together ".
            "with any old data");
        $this->clearIntermediateRecommendationData();
        L\crawlLog("...Start computing similarity-based group and item ".
            "recommendations...");
        $this->computeThreadGroupRecommendations();
        L\crawlLog("...Finished computing similarity-based group and item ".
            "recommendations.");
        $this->initializeNewUserRecommendations();
        $this->cron_model->updateCronTime(
            "item_group_recommendations", $this->update_time);
    }
    /**
     * Computes recommendations for users who have yet to receive any
     * recommendation of the given type based on what is the most
     * most popular recommendation
     */
    public function initializeNewUserRecommendations()
    {
        $db = $this->db;
        $popular_recommendations = [
            C\THREAD_RECOMMENDATION  => [], C\GROUP_RECOMMENDATION  => []];
        $sql = "SELECT ITEM_ID, SUM(SCORE) AS TOTAL_SCORE FROM " .
            "ITEM_RECOMMENDATION WHERE ITEM_TYPE = ? AND TIMESTAMP = " .
            $this->active_time." GROUP BY ITEM_ID ORDER BY TOTAL_SCORE DESC " .
            $db->limitOffset(C\MAX_RECOMMENDATIONS);
        foreach ($popular_recommendations as $type => $recommendation) {
            $results = $db->execute($sql, [$type]);
            while ($row = $db->fetchArray($results)) {
                $popular_recommendations[$type][] = $row;
            }
        }
        $new_user_sql = "SELECT USER_ID AS USER_ID ".
            "FROM USERS WHERE USER_ID NOT IN ".
            "(SELECT USER_ID FROM ITEM_RECOMMENDATION)";
        $new_user_results = $db->execute($new_user_sql);
        $base_recommend_sql = "INSERT INTO ITEM_RECOMMENDATION VALUES ";
        $insert_recommend_sql = $base_recommend_sql;
        $comma = "";
        $insert_count = 0;
        $i = 0;
        while($row = $db->fetchArray($new_user_results)) {
            $user_id = $row['USER_ID'];
            foreach ($popular_recommendations as $type => $recommendations) {
                foreach ($recommendations as $recommendation) {
                    $insert_recommend_sql .=
                        "$comma ({$recommendation['ITEM_ID']}, $user_id, ".
                        "$type, {$recommendation['TOTAL_SCORE']}," .
                        $this->update_time . ")";
                    $comma = ",";
                    $insert_count++;
                    L\crawlTimeoutLog("..initialized new %s users so far",
                        $i++);
                }
                if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                    $db->execute($insert_recommend_sql);
                    $insert_recommend_sql = $base_recommend_sql;
                    $insert_count = 0;
                    $comma = "";
                }
            }
        }
        if ($insert_count > 0) {
            $db->execute($insert_recommend_sql);
        }
    }
    /**
     * Manages the whole process of computing thread and group recommendations
     * for users. Makes a series of calls to handle parts of this computation
     * before synthesizing the result
     */
    public function computeThreadGroupRecommendations()
    {
        $this->computeItemTermFrequencies();
        $this->computeUserTermFrequencies();
        $number_items = $this->numberItems();
        $number_users = $this->numberUsers();
        $this->computeUserItemIdf($number_items, $number_users);
        $this->tfIdfUsers();
        $this->tfIdfItems();
        $this->computeUserItemSimilarity();
        $not_belongs_subselect =  "NOT EXISTS (SELECT * FROM ".
            "GROUP_ITEM B WHERE S.USER_ID=B.USER_ID ".
            "AND S.THREAD_ID=B.PARENT_ID )";
        $this->calculateSimilarityRecommendations(C\THREAD_RECOMMENDATION,
            "SELECT S.USER_ID, S.THREAD_ID, S.SIMILARITY FROM ".
            "USER_ITEM_SIMILARITY S WHERE $not_belongs_subselect AND ".
            "S.GROUP_MEMBER=1 ORDER BY S.USER_ID ASC, ".
            "S.SIMILARITY DESC", C\MAX_RECOMMENDATIONS);
        $this->calculateSimilarityRecommendations(C\GROUP_RECOMMENDATION,
            "SELECT S.USER_ID AS USER_ID, GI.GROUP_ID AS GROUP_ID," .
            "SUM(S.SIMILARITY) AS RATING FROM ".
            "GROUP_ITEM GI, USER_ITEM_SIMILARITY S ".
            "WHERE GI.ID = S.THREAD_ID AND S.GROUP_MEMBER=0 ".
            "GROUP BY GI.GROUP_ID, S.USER_ID ORDER BY S.USER_ID, RATING DESC",
            C\MAX_RECOMMENDATIONS);
    }
    /**
     * Delete all rows from intermediate tables used in the calculation
     * of group and thread recommendations. Also clears any non-active item
     * recommendations
     */
    public function clearIntermediateRecommendationData()
    {
        $this->db->execute("DELETE FROM ITEM_RECOMMENDATION
            WHERE TIMESTAMP <> '" . $this->active_time . "'");
    }
    /**
     * Computes the number of group items
     * @return int number of items
     */
    public function numberItems()
    {
        $results = $this->db->execute("SELECT COUNT(*) AS NUM_ITEMS FROM ".
            "GROUP_ITEM WHERE LOWER(TITLE) NOT LIKE '%page%'");
        $num_items = 0;
        if ($row = $this->db->fetchArray($results)) {
            $num_items = $row['NUM_ITEMS'];
        }
        return $num_items;
    }
    /**
     * Computes the number of users
     * @return int number of users
     */
    public function numberUsers()
    {
        $results =
            $this->db->execute("SELECT COUNT(*) AS NUM_USERS FROM USERS");
        $num_users = 0;
        if ($row = $this->db->fetchArray($results)) {
            $num_users = $row['NUM_USERS'];
        }
        return $num_users;
    }
    /**
     * Computes the term frequencies for individual items (posts) in groups
     * feeds. That is, for each item in each group for each term in that
     * item compute the number of times it appears in that item.
     */
    public function computeItemTermFrequencies()
    {
        $db = $this->db;
        $group_item_sql = "SELECT ID AS ITEM_ID, TITLE, DESCRIPTION ".
            "FROM GROUP_ITEM ".
            "WHERE LOWER(TITLE) NOT LIKE '%page%' " .
            "ORDER BY PUBDATE DESC " . $db->limitOffset(self::MAX_GROUP_ITEMS);
        $results = $db->execute($group_item_sql);
        $base_sql = "INSERT INTO ITEM_TERM_FREQUENCY VALUES";
        $insert_sql = $base_sql;
        $comma = "";
        $insert_count = 0;
        L\crawlLog("...Computing Item Term Frequencies");
        $i = 0;
        while ($item = $db->fetchArray($results)) {
            $term_frequencies = $this->termCount(
                $item['TITLE'] . " " . $item['DESCRIPTION']);
            foreach ($term_frequencies as $term => $frequency) {
                $log_freq = log($frequency, 10) + 1;
                $insert_sql .= "$comma ({$item['ITEM_ID']}, '" .
                     floor(bindec(str_replace(" ", "", L\toBinString(
                        hash("crc32b", $term, true))))/2) .
                        "', $frequency, $log_freq)";
                $comma = ",";
                $insert_count++;
                L\crawlTimeoutLog("...%s item term frequencies so far",
                    $i++);
                if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                    $db->insertIgnore($insert_sql);
                    $insert_sql = $base_sql;
                    $insert_count = 0;
                    $comma = "";
                }
            }
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
    /**
     * Calculates term => frequency pairs for all terms in a supplied string
     * @param string $record string of terms
     * @return array $term_frequencies associative array term => count
     */
    public static function termCount($record)
    {
        $terms = explode(" ", $record);
        $term_frequencies = array_count_values($terms);
        return $term_frequencies;
    }
    /**
     * Calculates the term frequencies for users. For each post of the user,
     * how often the user has seen a post with that term
     */
    public function computeUserTermFrequencies()
    {
        $db = $this->db;
        $sql = "SELECT II.USER_ID AS UID," .
            "COUNT(*) AS FREQUENCY, IWF.TERM_ID AS TID ".
            "FROM ITEM_TERM_FREQUENCY IWF, ITEM_IMPRESSION II ".
            "WHERE IWF.ITEM_ID = II.ITEM_ID ".
            "GROUP BY USER_ID, TERM_ID";
        $results = $db->execute($sql);
        $base_insert_sql = "INSERT INTO USER_TERM_FREQUENCY VALUES ";
        $insert_sql = $base_insert_sql;
        $insert_count = 0;
        L\crawlLog("...Computing User Term Frequencies");
        $i = 0;
        $comma = "";
        while($row = $db->fetchArray($results)) {
            $uid = $row['UID'];
            $wid = $row['TID'];
            $log_freq = log($row['FREQUENCY'], 10) + 1.0;
            $insert_sql .= "$comma ({$row['UID']}, {$row['TID']},".
                "{$row['FREQUENCY']}, $log_freq)";
            $comma = ",";
            $insert_count++;
            L\crawlTimeoutLog("...%s user term frequencies so far",
                $i++);
            if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                $db->insertIgnore($insert_sql);
                $insert_sql = $base_insert_sql;
                $insert_count = 0;
                $comma = "";
            }
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
    /**
     * Computes inverse document frequencies for each term for each user and
     * for each item. That is, for a particular term, it will compute
     * the number of times a user used that term in a post/the number of
     * posts by that user and take the log of the result. For items, the
     * idea is similar, for each thread, one calculates the number of posts
     * that the term appeared in/the total number of posts in the thread and
     * take the log of the result.
     *
     * @param int $number_items number of items
     * @param int $number_users number of  users
     */
    public function computeUserItemIdf($number_items, $number_users)
    {
        $db = $this->db;
        $terms_sql = "SELECT DISTINCT TERM_ID, SUM(FREQUENCY) AS FREQ ".
            "FROM ITEM_TERM_FREQUENCY GROUP BY TERM_ID ".
            "ORDER BY FREQ DESC " . $db->limitOffset(self::MAX_TERMS);
        $results = $db->execute($terms_sql);
        $num_items_term_sql = "SELECT COUNT(DISTINCT ITEM_ID)".
            " AS NUM_ITEMS_TERM FROM ITEM_TERM_FREQUENCY ".
            "WHERE TERM_ID = ? ";
        $num_users_term_sql ="SELECT COUNT(DISTINCT USER_ID) ".
            "AS NUM_USERS_TERM FROM USER_TERM_FREQUENCY ".
            "WHERE TERM_ID = ? ";
        $i = 0;
        $item_idf =[];
        $user_idf = [];
        L\crawlLog("...Computing User Item IDF values.");
        while($row = $db->fetchArray($results)) {
            $term_id = $row['TERM_ID'];
            /*
                Number of groups having the required term
            */
            $num_items_results = $db->execute($num_items_term_sql, [$term_id]);
            $row = $db->fetchArray($num_items_results);
            $item_idf[$term_id] =
                log($number_items/($row['NUM_ITEMS_TERM']+1), 10);
            /*
                Number of users having the required term
            */
            $num_users_results = $db->execute($num_users_term_sql, [$term_id]);
            $row = $db->fetchArray($num_users_results);
            $user_idf[$term_id] =
                log($number_users/($row['NUM_USERS_TERM'] + 1), 10);
            L\crawlTimeoutLog("...%s user item IDFs so far",
                $i++);
        }
        $this->item_idf = $item_idf;
        $this->user_idf = $user_idf;
    }
    /**
     * Calculates the product  TF * IDF for users based on the
     * results of @see computeUserItemIdf and @see computeUserTermFrequencies
     */
    public function tfIdfUsers()
    {
        L\crawlLog("...Computing TF*IDF scores for users.");
        $db = $this->db;
        $user_idf = $this->user_idf;
        $user_terms_sql = "SELECT TERM_ID, USER_ID, LOG_FREQUENCY ".
            "FROM USER_TERM_FREQUENCY";
        $base_insert_sql = "INSERT INTO USER_TERM_WEIGHTS VALUES ";
        $insert_sql = $base_insert_sql;
        $results = $db->execute($user_terms_sql);
        $insert_count = 0;
        $i = 0;
        $comma = "";
        while($row = $db->fetchArray($results)) {
            L\crawlTimeoutLog("...%s user tf-idfs so far",
                $i++);
            if (!empty($user_idf[$row['TERM_ID']])) {
                $insert_sql .= "$comma ({$row['TERM_ID']}, {$row['USER_ID']}, ".
                    ($row["LOG_FREQUENCY"] * $user_idf[$row['TERM_ID']]) . ")";
                $insert_count++;
                $comma = ",";
            }
            if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                $db->insertIgnore($insert_sql);
                $insert_sql = $base_insert_sql;
                $insert_count = 0;
                $comma = "";
            }
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
    /**
    * Calculates the product  TF * IDF for users based on the
    * results of @see computeUserItemIdf and @see computeItemTermFrequencies
     */
    public function tfIdfItems()
    {
        L\crawlLog("...Computing TF*IDF scores for items.");
        $db = $this->db;
        $item_idf = $this->item_idf;
        $item_terms_sql = "SELECT TERM_ID, ITEM_ID, LOG_FREQUENCY ".
            "FROM ITEM_TERM_FREQUENCY";
        $base_insert_sql = "INSERT INTO ITEM_TERM_WEIGHTS VALUES ";
        $insert_sql = $base_insert_sql;
        $results = $db->execute($item_terms_sql);
        $insert_count = 0;
        $i = 0;
        $comma = "";
        while($row = $db->fetchArray($results)) {
            L\crawlTimeoutLog("...%s term tf-idfs so far",
                $i++);
            if (!empty($item_idf[$row['TERM_ID']])) {
                $insert_sql .= "$comma ({$row['TERM_ID']}, {$row['ITEM_ID']}, ".
                    ($row["LOG_FREQUENCY"] * $item_idf[$row['TERM_ID']]) . ")";
                $insert_count++;
                $comma = ",";
            }
            if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                $db->insertIgnore($insert_sql);
                $insert_sql = $base_insert_sql;
                $insert_count = 0;
                $comma = "";
            }
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
    /**
     * Computes the cosine similarity between users and particular threads
     * based on TF*IDF scores and inserts the result into USER_ITEM_SIMILARITY
     */
    public function computeUserItemSimilarity()
    {
        L\crawlLog("...Computing User Item Similarity Scores.");
        $db = $this->db;
        $similarity_parts_sql =
            "SELECT SUM(UTW.WEIGHT * ITW.WEIGHT) AS THREAD_DOT_USER, ".
            "SUM(UTW.WEIGHT * UTW.WEIGHT) AS USER_MAG," .
            "SUM(ITW.WEIGHT * ITW.WEIGHT) AS ITEM_MAG," .
            "GI.PARENT_ID AS THREAD_ID, UTW.USER_ID AS USER_ID ".
            "FROM ITEM_TERM_WEIGHTS ITW, USER_TERM_WEIGHTS UTW, GROUP_ITEM GI ".
            "WHERE GI.ID = ITW.ITEM_ID AND UTW.TERM_ID=ITW.TERM_ID " .
            "GROUP BY UTW.USER_ID, GI.PARENT_ID";
        $similarity_parts_result = $db->execute($similarity_parts_sql);
        //used to check if belong to group
        $member_info_sql = "SELECT GI.GROUP_ID FROM ".
            "USER_GROUP UG, GROUP_ITEM GI WHERE ".
            "UG.GROUP_ID = GI.GROUP_ID AND LOWER(GI.TITLE) ".
            "NOT LIKE '%page%' AND UG.USER_ID = ? AND  GI.ID = ?";
        //used to check if can join group easily
        $register_info_sql = "SELECT G.GROUP_ID, G.REGISTER_TYPE AS REGISTER ".
            "FROM GROUPS G, GROUP_ITEM GI WHERE ".
            "G.GROUP_ID = GI.GROUP_ID AND GI.ID = ? ";
        $insert_count = 0;
        $base_sql = "INSERT INTO USER_ITEM_SIMILARITY VALUES ";
        $insert_sql = $base_sql;
        $comma = "";
        $i = 0;
        while($row = $db->fetchArray($similarity_parts_result)) {
            list($item_dot_user, $user_magnitude,
                $item_magnitude, $thread_id, $user_id,) = array_values($row);
            $user_magnitude = sqrt($user_magnitude);
            $item_magnitude = sqrt($item_magnitude);
            $add_record = false;
            if ($result = $db->execute($member_info_sql, [$user_id,
                $thread_id])){
                $info_row = $db->fetchArray($result);
                if (!empty($info_row) && $item_dot_user > 0) {
                    $add_record = true;
                    $group_member = 1;
                } else {
                    $access_results =
                        $db->execute($register_info_sql, [$thread_id]);
                    if ($access_results &&
                        $access_row = $db->fetchArray($access_results)) {
                        if (in_array($access_row['REGISTER'],
                            [C\PUBLIC_BROWSE_REQUEST_JOIN, C\PUBLIC_JOIN])) {
                            $add_record = true;
                            $group_member = 0;
                        }
                    }
                }
            }
            L\crawlTimeoutLog("...%s similarity scores so far", $i++);
            if ($add_record) {
                $cos_sim = floatval($item_dot_user)
                    /floatval($user_magnitude * $item_magnitude);
                $insert_count++;
                $insert_sql .= "$comma ($user_id, $thread_id, $cos_sim,
                    $group_member)";
                $comma = ",";
                if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                    $db->insertIgnore($insert_sql);
                    $insert_sql = $base_sql;
                    $insert_count = 0;
                    $comma = "";
                }
            }
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
    /**
     * Computes up to $max_recommendations item recommendations of the given
     * type (thread or group) based on query which computes similarity score
     * between a user and a given type.
     * @param int $recommendation_type a config.php constant indicating the type
     *      of recommendation to compute
     * @param $similarity_sql query used to determine user similarity scores
     *      should output triples: (user_id item_id rating)
     * @param int $max_recommendations maximum number of recommendations to
     *      compute per user
     */
    public function calculateSimilarityRecommendations($recommendation_type,
        $similarity_sql, $max_recommendations)
    {
        $db = $this->db;
        $base_sql = "INSERT INTO ITEM_RECOMMENDATION VALUES";
        $insert_sql = $base_sql;
        $similarity_results = $db->execute($similarity_sql);
        if (!$similarity_results) {
            return;
        }
        $old_user_id = -1; // assume no one has this id
        $comma = "";
        $insert_count = 0;
        $i = 0;
        L\crawlLog("...Computing type: $recommendation_type ".
            "recommendations");
        while($row = $db->fetchArray($similarity_results)) {
            list($user_id, $item_id, $similarity, ) = array_values($row);
            if ($user_id != $old_user_id) {
                $old_user_id = $user_id;
                $num_recommended = 1;
            }
            if ($num_recommended <= $max_recommendations
                && $old_user_id == $user_id) {
                $insert_sql .= "$comma ($item_id, $user_id, " .
                    $recommendation_type .
                    ", $similarity, {$this->update_time})";
                $comma = ",";
                $insert_count++;
                if ($insert_count > self::BATCH_SQL_INSERT_NUM) {
                    $db->insertIgnore($insert_sql);
                    $insert_sql = $base_sql;
                    $insert_count = 0;
                    $comma = "";
                }
                $num_recommended++;
                $old_user_id = $user_id;
            }
            L\crawlTimeoutLog("...%s recommendations so far", $i++);
        }
        if ($insert_count > 0) {
            $db->insertIgnore($insert_sql);
        }
    }
}
