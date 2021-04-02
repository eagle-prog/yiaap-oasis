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
 * @author Snigdha Rao Parvatneni
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\locale\bn\resources;

/**
 * Bengali specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['হিসাবে', 'আমি', 'তার', 'যে', 'তিনি', 'ছিল',
        'জন্য', 'উপর', 'হয়', 'সঙ্গে', 'তারা', 'হতে', 'এ', 'এক', 'আছে', 'এই',
        'থেকে', 'দ্বারা', 'গরম', 'শব্দ', 'কিন্তু', 'কি', 'কিছু', 'হয়', 'এটা', 'আপনি',
        'বা', 'ছিল', 'দী', 'এর', 'থেকে', 'এবং', 'একটি', 'মধ্যে', 'আমরা', 'করতে',
        'পারেন', 'আউট', 'অন্যান্য', 'ছিল', 'যা', 'কি', 'তাদের', 'সময়', 'যদি',
        'অভিলাষ', 'কিভাবে', 'তিনি বলেন,', 'একটি', 'প্রতিটি', 'বলুন', 'না', 'সেট',
        'তিন', 'চান', 'বায়ু', 'ভাল', 'এছাড়াও', 'খেলা', 'ছোট', 'শেষ', 'করা', 'হোম',
        'পড়া', 'হাত', 'পোর্ট', 'বড়', 'বানান', 'যোগ করা', 'এমনকি', 'জমি', 'এখানে',
        'অবশ্যই', 'বড়', 'উচ্চ', 'এমন', 'অনুসরণ করা', 'আইন', 'কেন', 'জিজ্ঞাসা',
        'পুরুষ', 'পরিবর্তন', 'গিয়েছিলাম', 'আলো', 'ধরনের', 'বন্ধ', 'প্রয়োজন', 'ঘর',
        'ছবি', 'চেষ্টা', 'আমাদের', 'আবার', 'পশু', 'বিন্দু', 'মা', 'বিশ্বের', 'কাছাকাছি',
        'নির্মাণ', 'স্ব', 'পৃথিবী', 'বাবা'];
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
