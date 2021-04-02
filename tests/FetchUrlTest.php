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
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test auxiliary functions related to downloading pages with the
 * FetchUrl class.
 *
 * @author Chris Pollett
 */
class FetchUrlTest extends UnitTest
{
    /**
     * For now nothing needed to set up FetchUrl Unit tests
     */
    public function setUp()
    {
    }
    /**
     * For now nothing needed to tear up FetchUrl Unit tests
     */
    public function tearDown()
    {
    }
    /**
     * Check if can put objects into string array and retrieve them
     */
    public function getCurlIpFromHeaderTestCase()
    {
        $ipv4s = FetchUrl::getCurlIp("*   Trying 130.65.255.57:80...");
        $this->assertTrue(!empty($ipv4s[0]),
            "Was able to extract at least one IPv4 address from curl header");
        $ip = $ipv4s[0] ?? "";
        $this->assertEqual($ip, "130.65.255.57",
            'Correct IPv4 address extracted');
        $ipv6s = FetchUrl::getCurlIp("*   Trying 2001:4998:44:41d::3:443...");
        $this->assertTrue(!empty($ipv6s[0]),
            "Was able to extract at least one IPv6 address from curl header");
        $ip = $ipv6s[0] ?? "";
        $this->assertEqual($ip, "2001:4998:44:41d::3",
            'Correct IPv6 address extracted');
    }
}
