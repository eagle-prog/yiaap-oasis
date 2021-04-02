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
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;

/**
 * Provides the AdminController activity that allows users to
 * create Chat Bot Stories. A Chat Bot story is a collection of patterns
 * (expression, trigger state, remote call, result state, responses) that
 * govern how a chat bot will behave under various circumstances
 *
 * @author Harika Nukala (simplified and rewritten Chris Pollett)
 */
class ChatbotComponent extends Component
{
    /**
     * Handles addpattern, editpattern, deletepattern requests for the
     * Chat Bot story of the current user.
     *
     * @return array $data field variables (FORM_TYPE,
     *      CURRENT_PATTERN, PATTERNS) used by BotstoryElement to
     *      render the view in which people can see the current pattern to add
     *      or edit as well as an array of patterns that have current been added
     *      for this bot
     */
    public function botStory()
    {
        $parent = $this->parent;
        $bot_model = $parent->model("bot");
        $data["ELEMENT"] = "botstory";
        $data['SCRIPT'] = "";
        $data["CURRENT_PATTERN"] = ['request' => "", 'trigger_state' => 0,
            'remote_message' => "", 'result_state' => 0, 'response' => ""];
        $must_have = ['REQUEST'];
        $to_clean = ['REQUEST' => 'string', 'TRIGGER_STATE' => 'string',
            'REMOTE_MESSAGE' => 'string', 'RESULT_STATE' => 'string',
            'RESPONSE' => 'string'];
        $user_id = $_SESSION['USER_ID'];
        $remote_call_pattern =
            '/^[a-zA-Z]\w+\s*\(\s*(\$\w+(\,\s*\$\w+)*)?\)\;?$/';
        $data["FORM_TYPE"] = (empty($_REQUEST['form_type']) ||
            !in_array($_REQUEST['form_type'],
            ["addpattern", "editpattern"]) ) ?  "addpattern" :
            $_REQUEST['form_type'];
        $pattern = [];
        if (!empty($_REQUEST['pattern_id'])) {
            $pattern_id = $parent->clean($_REQUEST['pattern_id'], "int");
            $pattern = $bot_model->getPatternById($pattern_id);
            if (empty($pattern)) {
                return $parent->redirectWithMessage(
                    tl('chatbot_component_bot_lookup_error'));
            }
            foreach ($data["CURRENT_PATTERN"]  as $field => $value) {
                $data["CURRENT_PATTERN"][$field] = $pattern[strtoupper($field)];
            }
        }
        if (isset($_REQUEST['arg'])) {
            switch ($_REQUEST['arg']) {
                case "addpattern":
                    $data['FORM_TYPE'] = "addpattern";
                    $num_patterns = $bot_model->countPatterns($user_id);
                    if ($num_patterns > C\MAX_BOT_PATTERNS) {
                        return $parent->redirectWithMessage(
                            tl('chatbot_component_max_patterns_exceeded'));
                    }
                    $p = [];
                    foreach ($to_clean as $clean_me => $type) {
                        $lower_clean = strtolower($clean_me);
                        if (in_array($clean_me, $must_have) &&
                            empty($_REQUEST[$lower_clean])) {
                            return $parent->redirectWithMessage(
                                tl('chatbot_component_missing_required'));
                        }
                        $p[$clean_me] = (empty($_REQUEST[$lower_clean])) ?
                            $data["CURRENT_PATTERN"][$lower_clean] :
                            trim($parent->clean($_REQUEST[$lower_clean],$type));
                        $data["CURRENT_PATTERN"][$lower_clean] =
                            $p[$clean_me];
                    }
                    $bot_model->addPattern($user_id, $p['REQUEST'],
                        $p['TRIGGER_STATE'], $p['REMOTE_MESSAGE'],
                        $p['RESULT_STATE'], $p['RESPONSE']);
                    return $parent->redirectWithMessage(
                        tl('chatbot_component_pattern_added'),
                        ['FORM_TYPE']);
                    break;
                case "editpattern":
                    $data['FORM_TYPE'] = "editpattern";
                    $pattern = ["PATTERN_ID" => $pattern_id];
                    foreach ($data['CURRENT_PATTERN'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field])) {
                            $pattern[$upper_field] = $parent->clean(
                                $_REQUEST[$field], $to_clean[$upper_field]);
                            if (in_array($upper_field, $must_have) &&
                                empty($pattern[$upper_field])) {
                                return $parent->redirectWithMessage(
                                    tl('chatbot_component_missing_required'));
                            }
                            $data['CURRENT_PATTERN'][$field] =
                                $pattern[$upper_field];
                        } else {
                            $pattern[$upper_field] = $value;
                        }
                    }
                    $bot_model->updatePattern($pattern);
                    return $parent->redirectWithMessage(
                        tl('chatbot_component_pattern_updated'),
                        ['form_type', 'pattern_id']);
                    break;
                case "deletepattern":
                    if (empty($_REQUEST['pattern_id'])) {
                        return $parent->redirectWithMessage(
                            tl('chatbot_component_no_delete_pattern'),
                            ['FORM_TYPE']);
                    }
                    $pattern_id = $parent->clean($_REQUEST['pattern_id'],
                        "int");
                    $bot_model->deletePattern($pattern_id);
                    return $parent->redirectWithMessage(
                        tl('chatbot_component_pattern_deleted'));
                    break;
            }
        }
        $search_array[]=["PATTERN_ID", "", "", "ASC"];
        $parent->pagingLogic($data, $bot_model, "PATTERNS",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "", [$user_id]);
        if ($data['FORM_TYPE'] == 'addpattern') {
            $data['SCRIPT'] .= "setDisplay('admin-form-row', false);";
        }
        if ($data['FORM_TYPE'] == 'editpattern') {
            $data['SCRIPT'] .= "elt('focus-button').focus();";
        }
        return $data;
    }
}
