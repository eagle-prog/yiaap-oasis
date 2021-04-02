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
namespace seekquarry\yioop\locale\pl\resources;

/**
 * Polish specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['jak', 'I', 'jego', 'że', 'on', 'było', 'dla',
        'na', 'są', 'zespół', 'oni', 'być', 'w', 'jeden', 'mieć', 'tego', 'z',
        'przez', 'gorący', 'słowo', 'ale', 'co', 'niektóre', 'jest', 'to',
        'ty', 'lub', 'miał', 'kilka', 'stopa', 'do', 'i', 'ciągnąć', 'w',
        'my', 'puszka', 'na zewnątrz', 'inne', 'były', 'który', 'zrobić',
        'ich', 'czas', 'jeśli', 'będzie', 'jak', 'powiedział', 'próba',
        'każda', 'powiedzieć', 'nie', 'zestaw', 'trzy', 'chcą', 'powietrze',
        'dobrze', 'również', 'grać', 'mały', 'koniec', 'wkładać',
        'Strona', 'główna', 'czytaj', 'ręka', 'port', 'duży', 'zaklęcie',
        'dodać', 'nawet', 'ziemia', 'tutaj', 'musi', 'duży', 'wysoki',
        'takie', 'śledzić', 'akt', 'dlaczego', 'zapytaj', 'mężczyźni',
        'zmiana', 'poszedł', 'światła', 'rodzaj', 'z', 'potrzeba', 'dom',
        'obraz', 'spróbuj', 'nas', 'ponownie', 'zwierząt', 'punkt', 'matka',
        'świat', 'blisko', 'budować', 'własny', 'ziemia', 'ojciec'];
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
