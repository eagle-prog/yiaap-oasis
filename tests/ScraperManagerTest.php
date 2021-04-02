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
 * @author Charles Bocage charles.bocage@sjsu.edu
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\ScraperManager;
use seekquarry\yioop\library\UnitTest;
use seekquarry\yioop\models\ScraperModel;

/**
 * Code used to test Web Scrapers.
 *
 * @author Charles Bocage (modified to be used for Scraper class from
 *  previous code for CMS Detectors by Chris Pollett)
 */
class ScraperManagerTest extends UnitTest
{
    /**
     * Nothing done for unit test setup
     */
    public function setUp()
    {
    }
    /**
     * Nothing done for unit test tear done
     */
    public function tearDown()
    {
    }
    /**
     * This function uses its local variable $file_names to read in a
     * file with a list of files to scrape. Each file is then checked
     * against the current active list of scrapers, and the scraper
     * label of the scraper whose signature matched against the file
     * is determined. This label is then compared against the $correct_names
     * array to see if the answer is as expected.
     */
    public function checkTestCase()
    {
        $scrapers_dir = C\PARENT_DIR . '/tests/test_files/scrapers';
        $file_names = file("$scrapers_dir/scraper_input.txt");
        $correct_names =
            file("$scrapers_dir/scraper_results.txt");
        for ($i = 0; $i < count($file_names); $i++) {
            $file_name = trim($file_names[$i]);
            $file_path = $scrapers_dir . "/" . $file_name;
            $correct_name = trim($correct_names[$i]);
            $contents = file_get_contents($file_path);
            $scraper_model = new ScraperModel();
            $scrapers = $scraper_model->getAllScrapers();
            $scraper =
                ScraperManager::getScraper($contents, $scrapers);
            $name = empty($scraper['NAME']) ? "" : $scraper['NAME'];
            $this->assertEqual($name, $correct_name,
                "incorrectly detects the contents in \"$file_name\" ".
                "as a $name site rather than a $correct_name site.");
        }
    }
}
