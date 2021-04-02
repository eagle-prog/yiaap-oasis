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
namespace seekquarry\yioop\locale\ja\resources;

/**
 * Japanese specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['ように', '私は', '彼の', 'その', '彼',
    'た', 'ために', '上の', 'アール', 'とともに', '彼ら', 'ある', 'アット',
    '一つ', '持っている', 'この', 'から', 'バイ', 'ホット', '言葉', 'しかし', '何',
    'いくつかの', 'です', 'それ', 'あなた', 'または', '持っていた', 'インクルード', 'の',
    'へ', 'そして', 'は', 'で', '我々', '缶', 'アウト', 'その他', 'だった',
    'これ', 'やる', 'それらの', '時間', 'もし', '意志', '方法', '前記', 'の',
    'それぞれ', '言う', 'し', 'セット', '個', '欲しい', '空気', 'よく',
    'また', '遊ぶ', '小さい', '終わり', '置く', 'ホーム', '読む', '手',
    'ポート', '大きい', 'スペル', '加える', 'さらに', '土地', 'ここに',
    'しなければならない', '大きい', '高い', 'そのような', '続く', '行為',
    'なぜ', '頼む', '人々', '変更', '行ってきました', '光', '種類', 'オフ',
    '必要', '家', '絵', '試す', '私たち', '再び', '動物', 'ポイント', '母',
    '世界', '近く', 'ビルド', '自己', '地球', '父'];
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
