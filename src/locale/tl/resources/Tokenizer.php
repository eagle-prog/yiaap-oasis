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
namespace seekquarry\yioop\locale\tl\resources;

use seekquarry\yioop\models\LocaleModel;

/**
 * Tagalog (spoken in Philipines) specific tokenization code.
 * Typically, tokenizer.php either contains a stemmer for the language in
 * question or it specifies how many characters in a char gram
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
    public static $stop_words = ['akin', 'aking', 'ako', 'alin', 'am', 'amin',
        'aming', 'ang', 'ano', 'anumang', 'apat', 'at', 'atin', 'ating', 'ay',
        'bababa', 'bago', 'bakit', 'bawat', 'bilang', 'dahil', 'dalawa',
        'dapat', 'din', 'dito', 'doon', 'gagawin', 'gayunman', 'ginagawa',
        'ginawa', 'ginawang', 'gumawa', 'gusto', 'habang', 'hanggang',
        'hindi', 'huwag', 'iba', 'ibaba', 'ibabaw', 'ibig', 'ikaw', 'ilagay',
        'ilalim', 'ilan', 'inyong', 'isa', 'isang', 'itaas', 'ito', 'iyo',
        'iyon', 'iyong', 'ka', 'kahit', 'kailangan', 'kailanman', 'kami',
        'kanila', 'kanilang', 'kanino', 'kanya', 'kanyang', 'kapag', 'kapwa',
        'karamihan', 'katiyakan', 'katulad', 'kaya', 'kaysa', 'ko', 'kong',
        'kulang', 'kumuha', 'kung', 'laban', 'lahat', 'lamang', 'likod',
        'lima', 'maaari', 'maaaring', 'maging', 'mahusay', 'makita', 'marami',
        'marapat', 'masyado', 'may', 'mayroon', 'mga', 'minsan', 'mismo',
        'mula', 'muli', 'na', 'nabanggit', 'naging', 'nagkaroon', 'nais',
        'nakita', 'namin', 'napaka', 'narito', 'nasaan', 'ng', 'ngayon',
        'ni', 'nila', 'nilang', 'nito', 'niya', 'niyang', 'noon', 'o', 'pa',
        'paano', 'pababa', 'paggawa', 'pagitan', 'pagkakaroon', 'pagkatapos',
        'palabas', 'pamamagitan', 'panahon', 'pangalawa', 'para', 'paraan',
        'pareho', 'pataas', 'pero', 'pumunta', 'pumupunta', 'sa', 'saan',
        'sabi', 'sabihin', 'sarili', 'sila', 'sino', 'siya', 'tatlo', 'tayo',
        'tulad', 'tungkol', 'una', 'walang'];
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
