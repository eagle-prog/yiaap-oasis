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
 * @author Chris Pollett chris@pollett.orgs
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\library as L;

/**
 * This is class is used to handle
 * db results needed for a user to login
 *
 * @author Chris Pollett
 */
class SigninModel extends Model
{
    /**
     * Checks that a username password pair is valid. This function
     * is slow because the underlying crypt to slow
     *
     * @param string $username the username to check
     * @param string $password the password to check
     * @return bool  where the password is that of the given user
     *     (or at least hashes to the same thing)
     */
    public function checkValidSignin($username, $password)
    {
        $db = $this->db;
        $row = $this->getUserDetails($username);
        $start_time = microtime(true);
        if ($row) {
            $crypt_password = L\crawlCrypt($password, $row['PASSWORD']);
            $valid_password = ($crypt_password == $row['PASSWORD']);
        } else {
            $crypt_password = L\crawlCrypt($password);
            $valid_password = false;
        }
        // crude avoid timing attacks if possible
        $micro_delta = L\changeInMicrotime($start_time);
        $sleep_time = intval(1000000 * (0.25 - $micro_delta));
        if ($sleep_time < 0) {
            $sleep_time = intval(1000000 * (0.5 - $micro_delta));
        }
        if ($sleep_time < 0) {
            $sleep_time = intval(1000000 * (1 - $micro_delta));
        }
        if ($sleep_time > 0) {
            usleep($sleep_time);
        }
        return $valid_password;
    }
    /**
     * Get user details from database
     *
     * @param string $username username
     * @return array $result array of user data
     */
    public function getUserDetails($username)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME, PASSWORD FROM USERS ".
            "WHERE LOWER(USER_NAME) = LOWER(?) " . $db->limitOffset(1);
        $i = 0;
        do {
            if ($i > 0) {
                sleep(3);
            }
            $result = $db->execute($sql, [$username]);
            $i++;
        } while (!$result && $i < 2);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Checks that a username email pair is valid
     *
     * @param string $username the username to check
     * @param string $email the email to check
     * @return bool  where the email is that of the given user
     *     (or at least hashes to the same thing)
     */
    public function checkValidEmail($username, $email)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME, EMAIL FROM USERS ".
            "WHERE LOWER(USER_NAME) = LOWER(?) " . $db->limitOffset(1);

        $result = $db->execute($sql, [$username]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);

        return email == $row['EMAIL'];
    }
    /**
     * Get the user_name associated with a given userid
     *
     * @param string $user_id the userid to look up
     * @return string the corresponding username
     */
   public function getUserName($user_id)
   {
       $db = $this->db;
       $sql = "SELECT USER_NAME FROM USERS WHERE USER_ID = ? " .
            $db->limitOffset(1);
       $result = $db->execute($sql, [$user_id]);
       $row = $db->fetchArray($result);
       $username = $row['USER_NAME'];
       return mb_strtolower($username);
   }
     /**
     * Get the email associated with a given user_id
     *
     * @param string $user_id the userid to look up
     * @return string the corresponding email
     */
   public function getEmail($user_id)
   {
       $db = $this->db;
       $sql = "SELECT EMAIL FROM USERS WHERE
            USER_ID = ?  " . $db->limitOffset(1);
       $result = $db->execute($sql, [$user_id]);
       $row = $db->fetchArray($result);
       $email = mb_strtolower($row['EMAIL']);
       return $email;
   }
    /**
     * Changes the email of a given user
     *
     * @param string $username username of user to change email of
     * @param string $email new email for user
     * @return bool update successful or not.
     */

    public function changeEmail($username, $email)
    {
        $sql = "UPDATE USERS SET EMAIL= ? WHERE USER_NAME = ? ";
        $result = $this->db->execute($sql, [mb_strtolower($email), $username]);
        return $result != false;
    }
    /**
     * Changes the password of a given user
     *
     * @param string $username username of user to change password of
     * @param string $password new password for user
     * @return bool update successful or not.
     */
    public function changePassword($username, $password)
    {
        $sql = "UPDATE USERS SET PASSWORD=? WHERE USER_NAME = ? ";
        $result = $this->db->execute($sql,
            [L\crawlCrypt($password), $username]);
        return $result != false;
    }
}
