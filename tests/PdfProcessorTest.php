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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\ComputerVision;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\processors\PdfProcessor;
use seekquarry\yioop\library\UnitTest;

/**
 * UnitTest for the PdfProcessor class. A PdfProcessor is used to process
 * a .pdf file and extract summary from it. This
 * class tests the processing of an .pdf file.
 *
 * @author Chris Pollett
 */
class PdfProcessorTest extends UnitTest implements CrawlConstants
{
    /**
     * Creates a new PdfProcessor object using the test.pdf
     * file made from the seekquarry landing page
     */
    public function setUp()
    {
    }
    /**
     * Delete any files associated with our test on PdfProcessor (in this case
     * none)
     */
    public function tearDown()
    {
    }
    /**
     * Test case to check whether words known to be in the PDF were extracted
     * is retrieved correctly.
     */
    public function wordExtractionTestCase()
    {
        $pdf_object = new PdfProcessor();
        $url = "http://www.yioop.com/test.pdf";
        $filename = C\PARENT_DIR . "/tests/test_files/test.pdf";
        $page = file_get_contents($filename);
        $summary = $pdf_object->process($page, $url);
        $words = explode(" ", $summary[self::DESCRIPTION]);
        $this->assertTrue(in_array("Documentation", $words),
            "Word Extraction 1");
        $this->assertTrue(in_array("Yioop", $words),
            "Word Extraction 2");
        $this->assertTrue(in_array("Open", $words),
            "Word Extraction 3");
    }
    /**
     * Tests Tessaract text extraction from Images
     */
    public function textFromImageTestCase()
    {
        if (ComputerVision::ocrEnabled()) {
            $pdf_object = new PdfProcessor();
            $url = "http://www.yioop.com/test2.pdf";
            $filename = C\PARENT_DIR . "/tests/test_files/test2.pdf";
            $page = file_get_contents($filename);
            $summary = $pdf_object->process($page, $url);
            $words = explode(" ", $summary[self::DESCRIPTION]);
            $this->assertTrue(in_array("Maureen", $words),
                "Word From Image Extraction 1");
            $this->assertTrue(in_array("Phantom", $words),
                "Word From Image Extraction 2");
            $this->assertTrue(in_array("playing", $words),
                "Word From Image Extraction 3");
        }
    }
}
