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

use seekquarry\yioop\controllers\SearchController;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UnitTest;

/**
 * Tests the functionaility of WikiParser used when processing Wikipedia dumps
 * and used for Yioop's internal wiki infrastructure
 *
 * @author Chris Pollett
 */
class WikiParserTest extends UnitTest
{
    /**
     * No set up being done for the time being
     */
    public function setUp()
    {
    }
    /**
     * No tear down being done for the time being
     */
    public function tearDown()
    {
    }
    /**
     * Checks that the basic WikiParser substitutions are done correctly
     */
    public function checkBasicSubstitutionsTestCase()
    {
        $controller = new SearchController();
        $parser = new L\WikiParser();
        for ($i = 1; $i < 6; $i++) {
            $heading = str_repeat("=", $i);
            $parsed = $parser->parse("{$heading}Title{$heading}");
            $expected = "\n<div>\n\n<h$i id='Title'>Title</h$i>\n</div>\n";
            $this->assertEqual($parsed, $expected,
                "Level $i heading parses as expected!");
        }
        $this->assertEqual($parsed, $expected,
            "Level $i Heading Parses as Expected!");
        $parsed = $parser->parse($controller->clean("'''Bold'''", "string"));
        $expected = "\n<div>\n<b>Bold</b>\t\n</div>\n";
        $this->assertEqual($parsed, $expected, "Bold text parses as expected!");
        $parsed = $parser->parse($controller->clean("''Italics''", "string"));
        $expected = "\n<div>\n<i>Italics</i>\t\n</div>\n";
        $this->assertEqual($parsed, $expected,
            "Italics text parses as expected!");
        $parsed = $parser->parse($controller->clean("#item1\n#item2",
            "string"));
        $expected = "\n<div>\n\n<ol>\n<li>item1</li>\n<li>item2</li>\n".
            "</ol>\n\n</div>\n";
        $this->assertEqual($parsed, $expected,
            "Ordered list parses as expected!");
        $parsed = $parser->parse($controller->clean("*item1\n*item2",
            "string"));
        $expected = "\n<div>\n\n<ul>\n<li>item1</li>\n<li>item2</li>\n".
            "</ul>\n\n</div>\n";
        $this->assertEqual($parsed, $expected,
            "Unordered list parses as expected!");
    }
}
