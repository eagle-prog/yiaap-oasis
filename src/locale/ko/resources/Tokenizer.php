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
namespace seekquarry\yioop\locale\ko\resources;

use seekquarry\yioop\models\LocaleModel;

/**
 * Korean specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['로', '나는', '그의', '그', '그', '했다',
        '에 대한', '에', '아르', '와', '그들', '있다', '에', '일', '이', '이',
        '부터', '에 의해', '뜨거운', '단어', '하지만', '무엇', '다소', '이다', '그',
        '당신', '또는', '했다', '에', '의', '에', '과', '이', '에', '우리', '수',
        '아웃', '다른', '했다', '하는', '할', '자신의', '시간', '면', '것', '방법',
        '말했다', '이', '각', '이야기', '하지', '세트', '세', '필요', '공기', '잘',
        '또한', '재생', '작은', '끝', '넣어', '홈', '읽기', '손', '포트', '큰',
        '철자', '추가', '도', '땅', '여기', '해야', '큰', '높은', '이러한', '따라',
        '행위', '이유', '문의', '남자', '변경', '갔다', '빛', '종류', '오프',
        '필요가있다', '집', '사진', '시험', '우리', '다시', '동물', '포인트',
        '어머니', '세계', '가까운', '구축', '자기', '지구', '아버지'];
    /**
     * How many characters in a char gram for this locale
     * @var int
     */
    public static $char_gram_len = 3;
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
            $pattern = '/(' . implode('|', self::$stop_words) . ')/u';
        }
        $data = preg_replace($pattern, '', $data);
        return $data;
    }
}
