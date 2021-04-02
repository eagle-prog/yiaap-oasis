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
 * Translate the supplied arguments into the current locale.
 *
 * This function is a convenience copy of the same function
 * @see seekquarry\yioop\library\tl() to this subnamespace
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    return call_user_func_array(C\NS_LIB . "tl", func_get_args());
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/**
 * Base component class for all components on
 * the SeekQuarry site. A component consists of a collection of
 * activities and their auxiliary methods that can be used by a controller
 *
 * @author Chris Pollett
 */
class Component
{
    /**
     * Reference to the controller this component lives on
     *
     * @var object
     */
    public $parent = null;

    /**
     * Sets up this component by storing in its parent field a reference to
     *  controller this component lives on
     *
     * @param object $parent_controller reference to the controller this
     *      component lives on
     */
    public function __construct($parent_controller)
    {
        $this->parent = $parent_controller;
    }
    /**
     * Called to include the Javascript Wiki Editor (wiki.js) on a page
     * and to send any localizations needed from PHP to Javascript-land
     * It is used by both Crawl and SocialComponent
     *
     * @param array &$data an asscoiative array of data to be used by the
     *     view and layout that the wiki editor will be drawn on
     *     This method tacks on to INCLUDE_SCRIPTS to make the layout load
     *     wiki.js.
     * @param $id if "" then all textareas on page will get editor buttons,
     *     if -1 then sets up translations, but does not add any button,
     *     otherwise, add buttons to textarea $id will. (Can call this method
     *     multiple times, if want more than one but not all)
     */
    public function initializeWikiEditor(&$data, $id = "")
    {
        if (!isset($data["WIKI_INITIALIZED"]) || !$data["WIKI_INITIALIZED"]) {
            if (!isset($data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"] = [];
            }

            $data["INCLUDE_SCRIPTS"][] = "wiki";
            $data["WIKI_INITIALIZED"] = true;

            //set up an array of translation for javascript-land
            if (!isset($data['SCRIPT'])) {
                $data['SCRIPT'] = "";
            }
            $data['SCRIPT'] .= "\ntl = {".
                'wiki_js_small :"'. tl('wiki_js_small') .'",' .
                'wiki_js_medium :"'. tl('wiki_js_medium').'",'.
                'wiki_js_large :"'. tl('wiki_js_large').'",'.
                'wiki_js_search_size :"'. tl('wiki_js_search_size').'",'.
                'wiki_js_prompt_heading :"'. tl('wiki_js_prompt_heading').'",'.
                'wiki_js_example :"'. tl('wiki_js_example').'",'.
                'wiki_js_table_title :"'. tl('wiki_js_table_title').'",'.
                'wiki_js_submit :"'. tl('wiki_js_submit').'",'.
                'wiki_js_cancel :"'. tl('wiki_js_cancel').'",'.
                'wiki_js_bold :"'. tl('wiki_js_bold') . '",' .
                'wiki_js_italic :"'. tl('wiki_js_italic').'",'.
                'wiki_js_underline :"'. tl('wiki_js_underline').'",'.
                'wiki_js_strike :"'. tl('wiki_js_strike').'",'.
                'wiki_js_hr :"'. tl('wiki_js_hr').'",'.
                'wiki_js_heading :"'. tl('wiki_js_heading').'",'.
                'wiki_js_heading1 :"'. tl('wiki_js_heading1').'",'.
                'wiki_js_heading2 :"'. tl('wiki_js_heading2').'",'.
                'wiki_js_heading3 :"'. tl('wiki_js_heading3').'",'.
                'wiki_js_heading4 :"'. tl('wiki_js_heading4').'",'.
                'wiki_js_bullets :"'. tl('wiki_js_bullet').'",'.
                'wiki_js_bullet :"'. tl('wiki_js_bullet').'",'.
                'wiki_js_numbers :"'. tl('wiki_js_enum') .'",'.
                'wiki_js_enum :"'. tl('wiki_js_enum') .'",'.
                'wiki_js_nowiki :"'. tl('wiki_js_nowiki') .'",'.
                'wiki_js_add_search :"'.tl('wiki_js_add_search') .'",'.
                'wiki_js_search_size :"'. tl('wiki_js_search_size') .'",'.
                'wiki_js_add_wiki_table :"'. tl('wiki_js_add_wiki_table').'",'.
                'wiki_js_for_table_cols :"'. tl('wiki_js_for_table_cols').'",'.
                'wiki_js_for_table_rows :"'. tl('wiki_js_for_table_rows').'",'.
                'wiki_js_hyperlink :"'. tl('wiki_js_add_hyperlink').'",'.
                'wiki_js_add_hyperlink :"'. tl('wiki_js_add_hyperlink').'",'.
                'wiki_js_link_text :"'. tl('wiki_js_link_text').'",'.
                'wiki_js_link_url :"'. tl('wiki_js_link_url').'",'.
                'wiki_js_placeholder :"'. tl('wiki_js_placeholder').'",'.
                'wiki_js_centeraligned :"'. tl('wiki_js_centeraligned').'",'.
                'wiki_js_rightaligned :"'. tl('wiki_js_rightaligned').'",'.
                'wiki_js_leftaligned :"'. tl('wiki_js_leftaligned').'",'.
                'wiki_js_definitionlist :"'.
                tl('wiki_js_definitionlist').'",'.
                'wiki_js_definitionlist_item :"'.
                tl('wiki_js_definitionlist_item').'",'.
                'wiki_js_definitionlist_definition :"'.
                tl('wiki_js_definitionlist_definition').'",'.
                'wiki_js_slide_sample_title :"'.
                    tl('wiki_js_slide_sample_title').'",'.
                'wiki_js_slide_sample_bullet :"'.
                    tl('wiki_js_slide_sample_bullet').'",'.
                'wiki_js_resource_description : "'.
                tl('wiki_js_slide_resource_description').'"'.
                '};';
        }
        if ($id != -1) {
            if ($id == "") {
                $data['SCRIPT'] .= "editorizeAll();\n";
            } else {
                $data['SCRIPT'] .= "editorize('$id');\n";
            }
        }
    }
}
