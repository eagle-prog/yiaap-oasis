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

/**
 * BotModel is used to handle database statements related to Bot User stories
 * A Bot User Story consists of a sequence of patterns for what a bot should
 * do when another user posts a request to the bot (a message beginning
 * @bot_name) in a discussion group.
 *
 * @author Chris Pollett rewritten from file creater Harika Nukala
 */
class BotModel extends Model
{
    /**
     * Add a new pattern to the chat bot $user_id
     *
     * @param int $user_id of the chat bot user adding pattern for
     * @param string $request the from a different user to try to match against
     * @param string $trigger_state only match if the internal state of the
     *      bot for the rquesting user is this value
     * @param string $remote_call rest method on the chat bot external site to
     *      call (if any) when $request and $trigger_state conditions met
     * @param string $result_state internal state to switch to for that user
     * @param string $response to echo back to request user in a follow up
     *      post
     */
    public function addPattern($user_id, $request, $trigger_state,
        $remote_call, $result_state, $response)
    {
        $sql = "INSERT INTO CHAT_BOT_PATTERN (USER_ID, REQUEST,
            TRIGGER_STATE, REMOTE_MESSAGE, RESULT_STATE, RESPONSE)
            VALUES (?, ?, ?, ?, ?, ?)";
        $params = [intval($user_id), $request, $trigger_state,
            $remote_call, $result_state, $response];
        $this->db->execute($sql, $params);
    }
    /**
     * Returns the number of patterns associated with a bot user_id
     *
     * @param int $user_id id of bot to count the number of patterns for
     * @return int $number of patterns
     */
    public function countPatterns($user_id)
    {
        $db = $this->db;
        $sql = "SELECT COUNT(*) AS NUM FROM CHAT_BOT_PATTERN WHERE USER_ID = ?";
        $result = $db->execute($sql, [$user_id]);
        if (!$result) {
            return 0;
        }
        $row = $db->fetchArray($result);
        return $row['NUM'];
    }
    /**
     * Deletes a pattern using its id.
     *
     * @param int $pattern_id of pattern to delete
     */
    public function deletePattern($pattern_id)
    {
        $sql = "DELETE FROM CHAT_BOT_PATTERN WHERE PATTERN_ID = ?";
        $this->db->execute($sql, [$pattern_id]);
    }
    /**
     * Get pattern the its id
     *
     * @param int $pattern_id to use to look up an expression
     * @return array expression corresponding to the expressionid.
     */
    public function getPatternById($pattern_id)
    {
        $db = $this->db;
        $sql = "SELECT * FROM CHAT_BOT_PATTERN WHERE PATTERN_ID = (?)" .
            $db->limitOffset(1);
        $result = $db->execute($sql, [$pattern_id]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Update an existing pattern for a chat bot
     *
     * @param array $pattern object to update
     */
    public function updatePattern($pattern)
    {
        $sql = "UPDATE CHAT_BOT_PATTERN SET ";
        $comma = "";
        if (empty($pattern['PATTERN_ID'])) {
            return;
        }
        $params = [];
        foreach (['REQUEST', 'TRIGGER_STATE', 'REMOTE_MESSAGE',
            'RESULT_STATE', 'RESPONSE'] as $field) {
            if (isset($pattern[$field])) {
                $sql .= "$comma $field = ?";
                $comma = ",";
                $params[] = $pattern[$field];
            }
        }
        $sql .= " WHERE PATTERN_ID=?";
        $params[] = $pattern['PATTERN_ID'];
        $this->db->execute($sql, $params);
    }
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * This defaults to the single table whose name is whatever is before
     * Model in the name of the model. For example, by default on FooModel
     * this method would return "FOO". If a different behavior, this can be
     * overriden in subclasses of Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args = null)
    {
        return "CHAT_BOT_PATTERN";
    }
    /**
     * Used to restrict getRows in which rows it returns. Rows in this
     * case corresponding to chat bot patterns for a particular chat bot user.
     *
     * @param array $args first element is the $user_id of the chat bot user
     * @return string a SQL WHERE clause suitable to perform the above
     *     restrictions
     */
    public function whereCallback($args = null)
    {
        if (empty($args)) {
            return "";
        }
        list($user_id, ) = $args;
        return "USER_ID = $user_id";
    }
}
