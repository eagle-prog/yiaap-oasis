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
namespace seekquarry\yioop\locale\vi_VN\resources;

/**
 * Vietnamese specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram for Vietnamese neither
 * char gramming or stemming seemed to make sense, so
 * for now this file is blank.
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
    public static $stop_words = ['như', 'tôi', 'mình', 'mà', 'ông', 'là',
        'cho', 'trên', 'là', 'với', 'họ', 'được', 'tại', 'một', 'có',
        'này', 'từ', 'bởi', 'nóng', 'từ', 'nhưng', 'những', 'gì', 'một',
        'số', 'là', 'nó', 'anh', 'hoặc', 'có', 'các', 'của', 'để', 'và',
        'một', 'trong', 'chúng', 'tôi', 'có', 'thể', 'ra', 'khác', 'là',
        'mà', 'làm', 'của', 'họ', 'thời', 'gian', 'nếu', 'sẽ', 'như', 'thế',
        'nào', 'nói', 'một', 'môi', 'nói ', 'không', 'bộ', 'ba', 'muốn',
        'không', 'khí', 'cũng', 'cũng', 'chơi', 'nhỏ', 'cuố', 'đặt', 'nhà',
        'đọc', 'tay', 'cổng', 'lớn', 'chính', 'tả', 'thêm', 'thậm', 'chí',
        'đất', 'ở', 'đây', 'phải', 'lớn', 'cao', 'như', 'vậy', 'theo',
        'hành', 'động', 'lý', 'do ', 'tại ', 'sao', 'xin', 'người', 'đàn',
        'ông', 'thay', 'đổi', 'đi', 'ánh', 'sáng', 'loại', 'tắt', 'cần', 'nhà',
        'hình', 'ảnh', 'thử', 'chúng', 'tôi', 'một ', 'lần', 'nữa', 'động',
        'vật', 'điểm', 'mẹ', 'thế', 'giới', 'gần', 'xây', 'dựng', 'tự', 'đất',
        'cha'];
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
