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
 * Super class of all the test classes testing Javascript functions.
 *
 * @author Akash Patel
 */
class JavascriptUnitTest extends UnitTest
{
    /**
     * {@inheritDocs}
     */
    public function setUp()
    {
    }
    /**
     * {@inheritDocs}
     */
    public function run()
    {
        $test_results = [];
        $methods = get_class_methods(get_class($this));
        foreach ($methods as $method) {
            $this->test_objects = null;
            $this->setUp();
            $len = strlen($method);
            if (substr_compare(
                $method, self::case_name, $len - strlen(self::case_name)) == 0){
                $test_results[$method] = $this->$method();
            }
            $this->tearDown();
        }
        return $test_results;
    }
    /**
     * {@inheritDocs}
     */
    public function tearDown()
    {
    }
}
