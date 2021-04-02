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
use seekquarry\yioop\locale\zh_CN\resources\Tokenizer;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test Named Entity Tagging and Part of Speech Tagging for the
 * Chinese Language. Word segmentation is already tested in
 * @see seekquarry\yioop\tests\PhraseParserTest
 */
class ZhTokenizerTest extends UnitTest
{
    /**
     * Each test we set up a new Italian Tokenizer object
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
     * Tests whether Yioop correctly identity Chinese Named Entities
     */
    public function namedEntityTestCase()
    {
        $source = "孙向宏喜欢去洛杉矶旅游";
        $expected_tagging = "孙向宏_nr 洛杉矶_ns";
        $ne_tagger = new L\NamedEntityContextTagger('zh-CN');
        $output_tagging = $ne_tagger->tag($source);
        $this->assertEqual($output_tagging, $expected_tagging,
            "Named Entities Correctly Found in Chinese Source String");
    }
    /**
     * Tests whether Yioop can correctly tag a Chinese sentence
     */
    public function partOfSpeechTestCase()
    {
        $source = "印度 总统 是 印度 国家元首 和 " .
            "武装部队 总司令 有 该国 第一 公民 之 称";
        $expected_tagging = "印度_NR 总统_NN 是_VC 印度_NR 国家元首_NN ".
            "和_CC 武装部队_NN 总司令_NN 有_VE 该国_NN 第一_VV 公民_NN 之_DEG 称_NN";
        $pos_tagger = new L\PartOfSpeechContextTagger('zh-CN');
        $output_tagging = $pos_tagger->tag($source);
        $this->assertEqual($output_tagging, $expected_tagging,
            "Parts of Speech Correctly Tagged in Chinese Source String");
    }
    /**
     * Traditional to Simplified mapping test
     */
    public function traditionalSimplifiedTestCase()
    {
        $traditional = "那是一個黑暗而暴風雨的夜晚。";
        $simplified = "那是一个黑暗而暴风雨的夜晚。";
        $this->assertEqual(Tokenizer::normalize($traditional), $simplified,
            "Traditional characters correctly mapped to simplied ones");
    }
}
