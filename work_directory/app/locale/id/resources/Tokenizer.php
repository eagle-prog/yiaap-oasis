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
namespace seekquarry\yioop\locale\id\resources;

use seekquarry\yioop\models\LocaleModel;

/**
 * Indonesian specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['sebagai', 'saya', 'nya', 'bahwa', 'dia',
        'adalah', 'untuk', 'pada', 'adalah', 'dengan', 'mereka', 'menjadi',
        'di', 'satu', 'memiliki', 'ini', 'dari', 'oleh', 'hot', 'kata',
        'tapi', 'apa', 'beberapa', 'adalah', 'itu', 'anda', 'atau',
        'memiliki', 'itu', 'dari', 'untuk', 'dan', 'sebuah', 'di', 'kami',
        'bisa', 'out', 'lainnya', 'yang', 'yang', 'melakukan', 'mereka',
        'waktu', 'jika', 'akan', 'bagaimana', 'kata', 'an', 'masing-masing',
        'memberitahu', 'tidak', 'Kumpulan', 'tiga', 'ingin', 'udara', 'baik',
        'juga', 'bermain', 'kecil', 'end', 'menempatkan', 'rumah', 'baca',
        'tangan', 'pelabuhan', 'besar', 'mantra', 'tambahkan', 'bahkan',
        'tanah', 'di sini', 'harus', 'besar', 'tinggi', 'seperti', 'ikuti',
        'tindakan', 'mengapa', 'bertanya', 'laki-laki', 'perubahan', 'pergi',
        'cahaya', 'jenis', 'off', 'perlu', 'rumah', 'gambar', 'coba', 'kami',
        'lagi', 'hewan', 'titik', 'ibu', 'dunia', 'dekat', 'membangun',
        'diri', 'bumi', 'ayah'];
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
