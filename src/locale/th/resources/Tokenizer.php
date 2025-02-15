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
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\locale\th\resources;

/**
 * Thai specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram
 *
 * @author Chris Pollett
 */
class Tokenizer
{
    /**
     * A list of frequently occurring terms for this locale which should
     * be excluded from certain kinds of queries. This is also used
     * for language detection
     * @array
     */
    public static $stop_words = ['เป็น', 'ฉัน', 'ของเขา', 'ว่า', 'เขา', 'เป็น',
        'สำหรับ', 'บน', 'มี', 'ด้วย', 'พวกเขา', 'จะเป็น', 'ที่', 'หนึ่ง', 'มี',
        'นี้', 'จาก', 'โดย', 'ร้อน', 'คำ', 'แต่', 'สิ่งที่', 'บาง', 'เป็น', 'มัน',
        'คุณ', 'หรือ', 'มี', 'ได้', 'ของ', 'ที่จะ', 'และ', 'กรอก', 'ใน', 'เรา',
        'สามารถ', 'ออก', 'อื่น ๆ', 'เป็น', 'ซึ่ง', 'ทำ', 'ของพวกเขา', 'เวลา',
        'ถ้า', 'จะ', 'วิธี', 'กล่าวว่า', 'บิน', 'แต่ละ', 'บอก', 'ไม่', 'ชุด', 'สาม',
        'ต้องการ', 'อากาศ', 'ดี', 'ด้วย', 'เล่น', 'ขนาดเล็ก', 'ปลาย', 'ใส่', 'บ้าน',
        'อ่าน', 'มือ', 'พอร์ต', 'ที่มีขนาดใหญ่', 'สะกด', 'เพิ่ม', 'แม้กระทั่ง', 'ที่ดิน',
        'ที่นี่', 'ต้อง', 'ใหญ่', 'สูง', 'เช่น', 'ทำตาม', 'การกระทำ', 'เหตุผลที่',
        'ขอให้', 'ผู้ชาย', 'การเปลี่ยนแปลง', 'ไป', 'แสง', 'ชนิด', 'ออก', 'ต้อง',
        'บ้าน', 'ภาพ', 'พยายาม', 'เรา', 'อีกครั้ง', 'สัตว์', 'จุด', 'แม่', 'โลก',
        'อยู่ใกล้', 'สร้าง', 'ตนเอง', 'โลก', 'พ่อของ'];
    /**
     * How many characters in a char gram for this locale
     * @var int
     */
    public static $char_gram_len = 5;
    /**
     * Removes the stop words from the page (used for Word Cloud generation
     * and language detection)
     *
     * @param mixed $data either a string or an array of string to remove
     *      stop words from
     * @return mixed $data with no stop words
     */
    public static function stopwordsRemover($data)
    {
        static $pattern = "";
        if (empty($pattern)) {
            $pattern = '/\b(' . implode('|', self::$stop_words) . ')\b/u';
        }
        $data = preg_replace($pattern, '', $data);
        return $data;
    }
}
