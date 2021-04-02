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
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UnitTest;

/**
 * Code used to test the Hindi stemming algorithm. The inputs for the
 * algorithm came from the sample text file for the
 * The stemmed results come from the Java program that the PHP stemmer is
 * based off of at
 * http://members.unine.ch/jacques.savoy/clef/HindiStemmerLight.java.txt
 * which has since been modified to try to improve accuracy
 *
 * @author Chris Pollett
 */
class HiTokenizerTest extends UnitTest
{
    /**
     * Each test we set up a new Hindi Tokenizer object
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] = PhraseParser::getTokenizer("hi");
    }
    /**
     * Nothing done for unit test tear done
     */
    public function tearDown()
    {
    }
    /**
     * Tests whether the stem function for the Hindi stemming algorithm
     * stems words according to the rules of stemming. The function tests stem
     * by calling stem with the words in $test_words and compares the results
     * with the stem words in $stem_words
     *
     * $test_words is an array containing a set of words in French provided in
     * the snowball web page
     * $stem_words is an array containing the stems for words in $test_words
     */
    public function stemmerTestCase()
    {
        $stem_dir = C\PARENT_DIR.'/tests/test_files/hindi_stemmer';
        //Test word set from snowball
        $test_words = file("$stem_dir/input_vocabulary.txt");
        //Stem word set from snowball for comparing results
        $stem_words = file("$stem_dir/stemmed_result.txt");
        /**
         * check if function stem correctly stems the words in $test_words by
         * comparing results with stem words in $stem_words
         */
        $tokenizer = $this->test_objects['FILE1'];
        $no_stem_list = isset($tokenizer::$no_stem_list) ?
            $tokenizer::$no_stem_list : [];
        for ($i = 0; $i < count($test_words); $i++) {
            $word = trim($test_words[$i]);
            if (in_array($word, $no_stem_list) ||
                strlen($word) < 3) {
                continue;
            }
            $stem = trim($stem_words[$i]);
            $word_stem = $tokenizer->stem($word);
            $this->assertEqual($word_stem, $stem, "function stem correctly ".
                "stems $word to $stem, stems to $word_stem instead");
        }
    }
    /**
     * Tests that phrase tagger can correctly assign parts of speech to
     * the Hindi translation of Mahatma Gandhi's birth was on October 2
     */
    public function partsOfSpeechTestCase()
    {
        $tokenizer = $this->test_objects['FILE1'];
        //ideally will get work in new version
        // echo
        // $tokenizer::tagPartsOfSpeechPhrase("महामा गाँधी का जम 2 अक्टूबर को हुआ");
    }
}
