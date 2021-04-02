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
namespace seekquarry\yioop\locale\te\resources;

use seekquarry\yioop\models\LocaleModel;

/**
 * Telegu specific tokenization code. Typically, tokenizer.php
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
    public static $stop_words = ['గా', 'నేను', 'తన', 'ఆ', 'అతను', 'ఉంది',
        'కోసం', 'న', 'ఉన్నాయి', 'తో', 'వారు', 'ఉంటుంది', 'వద్ద', 'ఒకటి', 'కలిగి',
        'ఈ', 'నుండి', 'ద్వారా', 'వేడి', 'పదం', 'కానీ', 'ఏమి', 'కొన్ని', 'ఉంది',
        'ఇది', 'మీరు', 'లేదా', 'వచ్చింది', 'ది', 'యొక్క', 'కు', 'మరియు', 'ఒక',
        'లో', 'మేము', 'చెయ్యవచ్చు', 'అవుట్', 'ఇతర', 'ఉన్నాయి', 'ఇది', 'చేయండి',
        'వారి', 'సమయం', 'ఉంటే', 'రెడీ', 'ఎలా', 'అన్నాడు', 'ఒక', 'ప్రతి', 'చెప్పండి',
        'చేస్తుంది', 'సెట్', 'మూడు', 'కావలసిన', 'గాలి', 'బాగా', 'కూడా', 'ప్లే',
        'చిన్న', 'ముగింపు', 'చాలు', 'హోమ్', 'చదవడానికి', 'చేతి', 'పోర్ట్', 'పెద్ద',
        'అక్షరక్రమ', 'జోడించండి', 'కూడా', 'భూమి', 'ఇక్కడ', 'తప్పక', 'పెద్ద', 'అధిక',
        'ఇటువంటి', 'అనుసరించండి', 'చట్టం', 'ఎందుకు', 'గోవా', 'పురుషులు', 'మార్పు',
        'వెళ్ళింది', 'కాంతి', 'రకం', 'ఆఫ్', 'అవసరం', 'ఇల్లు', 'చిత్రాన్ని', 'ప్రయత్నించండి',
        'మాకు', 'మళ్ళీ', 'జంతు', 'పాయింట్', 'తల్లి', 'ప్రపంచ', 'సమీపంలో',
        'నిర్మించడానికి', 'స్వీయ', 'భూమి', 'తండ్రి'];
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
