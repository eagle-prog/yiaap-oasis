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
namespace seekquarry\yioop\locale\zh_CN\resources;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\PhraseParser;

/**
 * Chinese specific tokenization code. Typically, tokenizer.php
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
     * @var array
     */
    public static $stop_words = ['一', '人', '里', '会', '没', '她', '吗', '去',
        '也', '有', '这', '那', '不', '什', '个', '来', '要', '就', '我', '你',
        '的', '是', '了', '他', '么', '们', '在', '说', '为', '好', '吧', '知道',
        '我的', '和', '你的', '想', '只', '很', '都', '对', '把', '啊', '怎', '得',
        '还', '过', '不是', '到', '样', '飞', '远', '身', '任何', '生活', '够',
        '号', '兰', '瑞', '达', '或', '愿', '蒂', '別', '军', '正', '是不是',
        '证', '不用', '三', '乐', '吉', '男人', '告訴', '路', '搞', '可是',
        '与', '次', '狗', '决', '金', '史', '姆', '部', '正在', '活', '刚',
        '回家', '贝', '如何', '须', '战', '不會', '夫', '喂', '父', '亚', '肯定',
        '女孩', '世界'];
    /**
     * regular expression to determine if the None of the char in this
     * term is in current language.
     * @var string
     */
    public static $non_char_preg = "/^[^\p{Han}]+$/u";
    /**
     * The dictionary of characters can be used as Chinese Numbers
     * @var string
     */
    public static $num_dict =
       "1234567890○〇零一二两三四五六七八九十百千万亿".
       "０１２３４５６７８９壹贰叁肆伍陆柒捌玖拾廿卅卌佰仟萬億";
    /**
     * Dots used in Chinese Numbers
     * @var string
     */
    public static $dot = "\.．点";
    /**
     * A list of characters can be used at the end of numbers
     * @var string
     */
    public static $num_end = "％%";
    /**
     * Exception words of the regex found by functions:
     * isCardinalNumber, isOrdinalNumber, isDate
     * ex. "十分" in most of time means "very", but it will
     * be determined to be "10 minutes" by the function so we
     * need to remove it
     * @var array of string
     */
    public static $exception_list= ["十分","一","一点","千万",
    "万一", "一一", "拾", "一时", "千千", "万万", "陆"];
    /**
     * A list of characters can be used as Chinese punctuations
     * @var string
     */
    public static $punctuation_preg =
    "/^([\x{2000}-\x{206F}\x{3000}-\x{303F}\x{FF00}-\x{FF0F}" .
    "\x{FF1A}-\x{FF20}\x{FF3B}-\x{FF40}\x{FF5B}-\x{FF65}" .
    "\x{FFE0}-\x{FFEE}\x{21}-\x{2F}\x{21}-\x{2F}" .
    "\x{3A}-\x{40}\x{5B}-\x{60}\x{25cf}])\\1*$/u";
    /**
     * Any unique identifier corresponding to the component of a triplet which
     * can be answered using a question answer list
     * @var string
     */
    public static $question_token = "qqq";
    /**
     * Words array that determine if a sentence passed in is a question
     * @var array
     */
    public static $question_words = [
        "any" => ["谁" => "who",
                "哪儿|哪里" => "where",
                "哪个" => "which",
                "哪些" => "list",
                "哪" => ["after" => [   "1|一"=>"which",
                                    "[2-9]|[1-9][0-9]+"=>"list"
                                ],
                       "other"=>"where"
                        ],
                "什么|啥|咋" => [ "after" => [    "地方"=>"where",
                                            "地点"=>"where",
                                            "时\w*"=>"when"
                                       ],
                                "other" => "what"],
                "怎么|怎样|怎么样|如何" => "how",
                "为什么" => "why",
                "多少" => "how many",
                "几\w*" => ["any" => ["吗|\?|？" => "how many"],
                    "other" => false],
                "多久" => "how long",
                "多大" => "how big"
                ],
        "other" => [  "any" => [    "吗"=>"yesno",
                                "呢" => "what about"
                           ],
                    "other" => [  "other" => false,
                                "any" => ["\?|？" => "yesno"]
                             ]
                 ]
        ];
    /**
     * List of adjective-like parts of speech that might appear in lexicon file
     * Predicative adjective: VA
     * other noun-modifier: JJ
     * @var array
     */
    public static $adjective_type = ["VA", "JJ"];
    /**
     * List of adverb-like parts of speech that might appear in lexicon file
     * @var array
     */
    public static $adverb_type = ["AD"];
    /**
     * List of conjunction-like parts of speech that might appear in lexicon
     * file
     * Coordinating conjunction: CC
     * Subordinating conjunction: CS
     * @var array
     */
    public static $conjunction_type = ["CC", "CS"];
    /**
     * List of determiner-like parts of speech that might appear in lexicon
     * file
     * Determiner: DT
     * Cardinal Number: CD
     * Ordinal Number: OD
     * Measure word: M
     * @array
     */
    public static $determiner_type = ["DT", "CD", "OD", "M"];
    /**
     * List of noun-like parts of speech that might appear in lexicon file
     * Proper Noun: NR
     * Temporal Noun: NT
     * Other Noun: NN
     * Pronoun: PN
     * @var array
     */
    public static $noun_type = ["NR", "NT", "NN", "PN"];
    /**
     * List of verb-like parts of speech that might appear in lexicon file
     * Copula: VC
     * you3 as the main verb: VE
     * Other verb: VV
     * Short passive voice: SB
     * Long passive voice: LB
     * @var array
     */
    public static $verb_type = ["VC", "VE", "VV", "SB", "LB"];
    /**
     * List of particle-like parts of speech that might appear in lexicon file
     * No meaning words that can appear anywhere
     * @var array
     */
    public static $particle_type = [
        "AS", "ETC", "DEC", "DEG", "DEV", "MSP",
        "DER", "SP", "IJ", "FW"];
    /**
     * StochasticTermSegmenter instance used for segmenting chines
     * @var object
     */
    private static $stochastic_term_segmenter;
    /**
     * Named Entity tagger instance used to recognizer noun entities in
     * Chinese text
     * @var object
     */
    private static $named_entity_tagger;
    /**
     * PartOfSpeechContextTagger instance used in adding part of speech
     * annotations to Chinese text
     * @var object
     */
    private static $pos_tagger;
    /**
     * Holds a associative array with keys which are traditional characters
     * and values their simplified character correspondents.
     * @var array
     */
    private static $traditional_simplified_map;
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
        $data = self::normalize($data);
        $data = preg_replace($pattern, '', $data);
        return $data;
    }
    /**
     * A word segmenter.
     * Such a segmenter on input thisisabunchofwords would output
     * this is a bunch of words
     *
     * @param string $pre_segment  before segmentation
     * @param string $method  indicates which method to use
     * @return string with words separated by space
     */
    public static function segment($pre_segment, $method = "STS")
    {
        switch($method)
        {
            case "RMM":
                return PhraseParser::reverseMaximalMatch($pre_segment, "zh-CN",
                ['/^\d+$/', '/^[a-zA-Z]+$/']);
            case "STS":
                return self::getStochasticTermSegmenter()
                    ->segmentText($pre_segment);
        }
    }
    /**
     * Check if the term passed in is a Cardinal Number
     * @param string $term to check if a cardinal number or not
     * @return bool whether it is a cardinal or not
     */
    public static function isCardinalNumber($term)
    {
        return !in_array($term,self::$exception_list)
            && preg_match("/^([" . self::$num_dict .
            "]+([" . self::$dot . "][" .self::$num_dict .
            "]+)?[" . self::$num_end .
            "]?[余餘多]?[百千万亿佰仟萬億]*)".
            "$|^([".self::$num_dict."]+分之[" . self::$num_dict .
            "]+([" . self::$dot . "][" .self::$num_dict .
            "]+)?)$/u", $term);
    }
    /*
     * Check if the term passed in is a Ordinal Number
     * @param string $term to check if a ordinal number or not
     * @return bool whether it is a ordinal or not
     */
    public static function isOrdinalNumber($term)
    {
        return !in_array($term,self::$exception_list)
            && preg_match("/^第[" . self::$num_dict .
            "]*$/u", $term);
    }
    /*
     * Check if the term passed in is a date
     * @param string $term to check if a date or not
     * @return bool whether it is a date or not
     */
    public static function isDate($term)
    {
        return !in_array($term,self::$exception_list)
            && preg_match("/^[" . self::$num_dict .
            "]+(年|年代|月|日|时|小时|時|小時|" .
            "点|点钟|點|點鐘|分|分鐘|秒|秒鐘)$/u",$term);
    }
    /**
     * Check if the term is a punctuation
     */
    public static function isPunctuation($term)
    {
        return preg_match(self::$punctuation_preg, $term);
    }
    /**
     * Check if all the chars in the term is NOT current language
     * @param string $term is a string that to be checked
     * @return bool true if all the chars in $term is NOT current language
     *         false otherwise
     */
    public static function isNotCurrentLang($term)
    {
        return preg_match(self::$non_char_preg, $term);
    }
    /**
     * Get the segmenter instance, instantiating it if necessary
     * @return StochasticTermSegmenter
     */
    public static function getStochasticTermSegmenter()
    {
        if (!self::$stochastic_term_segmenter) {
            self::$stochastic_term_segmenter
                = new L\StochasticTermSegmenter("zh-CN");
        }
        return self::$stochastic_term_segmenter;
    }
    /**
     * Determines the part of speech tag of a term using simple rules if
     * possible
     * @param string $term to see if can get a part of speech for via a rule
     * @return string part of speech tag or $term if can't be determine
     */
    public static function getPosKey($term)
    {
        if (self::isPunctuation($term)) {
            return 'PU';
        } else if (self::isCardinalNumber($term)) {
            return 'CD';
        } else if (self::isOrdinalNumber($term)) {
            return 'OD';
        } else if (self::isDate($term)) {
            return 'NT';
        } else if (self::isNotCurrentLang($term)) {
            return 'FW';
        }
        return $term;
    }
    /**
     * Possible tags a term can have that can be determined by a simple rule
     * @return array
     */
    public static function getPosKeyList()
    {
        return ['PU','CD','OD','NT','FW'];
    }
    /**
     * Return list of possible tags that an unknown term can have
     * @return array
     */
    public static function getPosUnknownTagsList()
    {
        return ["NN","NR","VV","VA"];
    }
    /**
     * Get the named entity tagger instance
     * @return NamedEntityContextTagger for Chinese
     */
    public static function getNamedEntityTagger()
    {
        if (!self::$named_entity_tagger) {
            self::$named_entity_tagger
                = new L\NamedEntityContextTagger("zh-CN");
        }
        return self::$named_entity_tagger;
    }
    /**
     * Get Part of Speec instance
     * @return PartOfSpeechContextTagger for Chinese
     */
    public static function getPosTagger()
    {
        if (!self::$pos_tagger) {
            self::$pos_tagger
                = new L\PartOfSpeechContextTagger("zh-CN");
        }
        return self::$pos_tagger;
    }
    /**
     * Converts traditional Chinese characters to simplified characters
     * @param  string $text is a string of Chinese Char
     * @return string normalized form of the text
     */
    public static function normalize($text)
    {
        if (empty(self::$traditional_simplified_map)) {
            $path = C\LOCALE_DIR .
                "/zh_CN/resources/traditional_simplified.txt.gz";
            if (!file_exists($path)) {
                return $text;
            }
            self::$traditional_simplified_map =
                unserialize(gzdecode(file_get_contents($path)));
        }
        if (is_string($text)) {
            $chars = preg_split('//u', $text, null, PREG_SPLIT_NO_EMPTY);
        } else {
            return $text;
        }
        $num_chars = count($chars);
        for($i = 0; $i < $num_chars; $i++) {
            if (isset(self::$traditional_simplified_map[$chars[$i]])) {
                $chars[$i] = self::$traditional_simplified_map[$chars[$i]];
            }
        }
        return implode($chars);
    }
    /**
     * Scans a word list for phrases. For phrases found generate
     * a list of question and answer pairs at two levels of granularity:
     * CONCISE (using all terms in orginal phrase) and RAW (removing
     * (adjectives, etc).
     *
     * @param array $word_and_phrase_list of statements
     * @return array with two fields: QUESTION_LIST consisting of triplets
     *      (SUBJECT, PREDICATES, OBJECT) where one of the components has been
     *      replaced with a question marker.
     */
    public static function extractTripletsPhrases($word_and_phrase_list)
    {
        $triplets_list = [];
        $question_list = [];
        $question_answer_list = [];
        $triplet_types = ['CONCISE', 'RAW'];
        foreach ($word_and_phrase_list as $word_and_phrase => $position_list) {
            // strip parentheticals
            $word_and_phrase = preg_replace(
                "/[\{\[\(【（][^\}\]）】\)]+[\}\]\)）】]/u",
                "", $word_and_phrase);
            $tagged_phrase = self::tagTokenizePartOfSpeech($word_and_phrase);
            $parse_tree = ['cur_node' => 0];
            $extracted_triplets_set = [];
            $extracted_triplets=[];
            $pre_sub = [];
            while ($parse_tree['cur_node'] < count($tagged_phrase)) {
                $parse_tree = self::parseWholePhrase($tagged_phrase,
                ['cur_node'=>$parse_tree['cur_node']],$pre_sub);
                $triplets = self::extractTripletsParseTree($parse_tree);
                if (isset($parse_tree['NP'])) {
                    $pre_sub = $parse_tree['NP'];
                }
                $extracted_triplets_set[] = self::rearrangeTripletsByType(
                    $triplets);
                // next partial sentence
                while($parse_tree['cur_node'] < count($tagged_phrase)
                    && $tagged_phrase[$parse_tree['cur_node']]["tag"] != "PU") {
                    $parse_tree['cur_node']++;
                }
                $parse_tree['cur_node']++;
            }
            foreach($extracted_triplets_set as $extracted_triplets) {
                foreach ($triplet_types as $type) {
                    if (!empty($extracted_triplets[$type])) {
                        $triplets = $extracted_triplets[$type];
                        $questions = $triplets['QUESTION_LIST'];
                        foreach ($questions as $question) {
                            $question_list[$question] = $position_list;
                        }
                        $question_answer_list = array_merge(
                            $question_answer_list,
                            $triplets['QUESTION_ANSWER_LIST']);
                    }
                }
            }
        }
        $out_triplets['QUESTION_LIST'] = $question_list;
        $out_triplets['QUESTION_ANSWER_LIST'] = $question_answer_list;
        return $out_triplets;
    }
    /**
     * Split input text into terms and output an array with one element
     * per term, that element consisting of array with the term token
     * and the part of speech tag.
     *
     * @param string $text string to tag and tokenize
     * @return array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term) for one each token in $text
     */
    public static function tagTokenizePartOfSpeech($text)
    {
        $segmented = self::getStochasticTermSegmenter()->segmentSentence($text);
        $tags = self::getPosTagger()->predict($segmented);
        $result=[];
        for($i = 0; $i < count($segmented); $i++) {
            $result[$i] = [];
            $result[$i]["token"] = $segmented[$i];
            $result[$i]["tag"] = $tags[$i];
        }
        return $result;
    }
    /**
     * Starting at the $cur_node in a $tagged_phrase parse tree for an English
     * sentence, create a phrase string for each of the next nodes
     * which belong to part of speech group $type.
     *
     * @param array &$cur_node node within parse tree
     * @param array $tagged_phrase parse tree for phrase
     * @param string $type self::$noun_type, self::$verb_type, etc
     * @return string phrase string involving only terms of that $type
     */
    public static function parseTypeList(&$cur_node, $tagged_phrase, $type)
    {
        $string = "";
        $previous_string = "";
        $previous_tag = "";
        $start_node = $cur_node;
        $next_tag = (empty($tagged_phrase[$cur_node]['tag'])) ? "" :
            trim($tagged_phrase[$cur_node]['tag']);
        $allowed_conjuncts = [];
        while ($next_tag && (in_array($next_tag, $type) ||
            in_array($next_tag, $allowed_conjuncts))) {
            $previous_string = $string;
            $string .= $tagged_phrase[$cur_node]['token'];
            $cur_node++;
            $allowed_conjuncts = self::$conjunction_type;
            $previous_tag = $next_tag;
            $next_tag = (empty($tagged_phrase[$cur_node]['tag'])) ? "" :
                trim($tagged_phrase[$cur_node]['tag']);
        }
        if (in_array($previous_tag, $allowed_conjuncts) && $start_node <
            $cur_node) {
            $cur_node--;
            $string = $previous_string;
        }
        return $string;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for an adjective if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["cur_node" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "JJ" a subarray with a token node for the adjective that was
     *      parsed
     */
    public static function parseAdjective($tagged_phrase, $tree)
    {
        $adjective_string = self::parseTypeList($tree['cur_node'],
            $tagged_phrase, self::$adjective_type);
        if (!empty($adjective_string)) {
            $tree["JJ"] = $adjective_string;
            $partical_string = self::parseTypeList($tree['cur_node'],
                $tagged_phrase,self::$particle_type);
            if ($partical_string) {
                $tree["PARTICAL"] = $partical_string;
            }
        }
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a determiner if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "DT" a subarray with a token node for the determiner that was
     *      parsed
     */
    public static function parseDeterminer($tagged_phrase, $tree)
    {
        $determiner_string = "";
        /* In: All the cows low, "All the" is considered a determiner.
           That is, we will mush together the predeterminer with the determiner
         */
        $determiner_string = self::parseTypeList($tree['cur_node'],
            $tagged_phrase, self::$determiner_type);
        if (!empty($determiner_string)) {
            $tree["DT"] = $determiner_string;
            $partical_string = self::parseTypeList($tree['cur_node'],
                $tagged_phrase,self::$particle_type);
            if ($partical_string) {
                $tree["PARTICAL"] = $partical_string;
            }
        }
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a noun if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "NN" a subarray with a token node for the noun string that was
     *      parsed
     */
    public static function parseNoun($tagged_phrase, $tree)
    {
        //Combining multiple noun into one
        $noun_string = self::parseTypeList($tree['cur_node'], $tagged_phrase,
            self::$noun_type);
        if (!empty($noun_string)) {
            $tree["NN"] = $noun_string;
            $partical_string = self::parseTypeList($tree['cur_node'],
                $tagged_phrase,self::$particle_type);
            if ($partical_string) {
                $tree["PARTICAL"] = $partical_string;
            }
        }
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a verb if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "VB" a subarray with a token node for the verb string that was
     *      parsed
     */
    public static function parseVerb($tagged_phrase, $tree)
    {
        $verb_string = self::parseTypeList($tree['cur_node'], $tagged_phrase,
            self::$verb_type);
        if (!empty($verb_string)) {
            $tree["VB"] = $verb_string;
            $partical_string = self::parseTypeList($tree['cur_node'],
                $tagged_phrase,self::$particle_type);
            if ($partical_string) {
                $tree["PARTICAL"] = $partical_string;
            }
        }
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a sequence of
     * prepositional phrases if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["cur_node" =>
     *      current parse position in $tagged_phrase]
     * @param int $index which term in $tagged_phrase to start to try to parse
     *      a preposition from
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      parsed followed by additional possible fields (here i
     *      represents the ith clause found):
     *      "IN_i" with value a preposition subtree
     *      "DT_i" with value a determiner subtree
     *      "JJ_i" with value an adjective subtree
     *      "NN_i"  with value an additional noun subtree
     */
    public static function parsePrepositionalPhrases($tagged_phrase, $tree,
        $index = 1)
    {
        $cur_node = $tree['cur_node'];
        /* There are two forms of preposition.
           The first one has lc only
           之前(lc) 他在看书 */
        if (isset($tagged_phrase[$cur_node]['tag']) &&
            trim($tagged_phrase[$cur_node]['tag']) == "LC") {
            $tree["LC"] = $tagged_phrase[$cur_node]['token'];
            $tree['cur_node']+=1;
            return $tree;
        }
        /* Second form:
          format: prep [anything] [locolizer|punctuation]
           在(p)今天早上，(pu) 他 在(p) 车 里(lc) 睡觉。
           In the morning today, he was sleeping in the car.
         */
        if (isset($tagged_phrase[$cur_node]['tag']) &&
            trim($tagged_phrase[$cur_node]['tag']) == "P") {
            /* can have multiple prep's in a row, for example,
               it is known in over 20 countries
              */
            $preposition_string = self::parseTypeList($cur_node, $tagged_phrase,
                ["P"]);
            if (!empty($preposition_string)) {
                $tree["P"] = $preposition_string;
            }
            while(isset($tagged_phrase[$cur_node]) &&
                isset($tagged_phrase[$cur_node]['tag']) &&
                !in_array($tagged_phrase[$cur_node]['tag'],["PU", "LC"])) {
                $tree["P"] .= $tagged_phrase[$cur_node]['token'];
                $cur_node++;
            }
            $lc_string = self::parseTypeList($cur_node, $tagged_phrase,
                ["LC"]);
            if ($lc_string) {
                $tree["P"] .= $lc_string;
            }
            $partical_string = self::parseTypeList($cur_node,
                $tagged_phrase,self::$particle_type);
            if ($partical_string) {
                $tree["PARTICAL"] = $partical_string;
            }
        }
        $tree['cur_node'] = $cur_node;
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a noun phrase if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "NP" a subarray with possible fields
     *      "DT" with value a determiner subtree
     *      "JJ" with value an adjective subtree
     *      "NN" with value a noun tree
     */
    public static function parseNounPhrase($tagged_phrase, $tree)
    {
        $cur_node = $tree['cur_node'];
        $tree_dt = self::parseDeterminer($tagged_phrase,
            ['cur_node' => $cur_node]);
        $tree_jj = self::parseAdjective($tagged_phrase,
            ['cur_node' => $tree_dt['cur_node']]);
        $tree_pp = self::parsePrepositionalPhrases($tagged_phrase,
            ['cur_node' => $tree_jj['cur_node']]);
        $tree_nn = self::parseNoun($tagged_phrase,
            ['cur_node' => $tree_pp['cur_node']]);
        if ($tree_nn['cur_node'] == $tree_pp['cur_node']) {
            $tree['NP'] = "";
            return $tree;
        }
        $tree_pp2 = self::parsePrepositionalPhrases($tagged_phrase,
            ['cur_node' => $tree_nn['cur_node']]);
        $cur_node = $tree_pp2['cur_node'];
        $cc = "";
        if (!empty($tagged_phrase[$cur_node]['tag']) &&
            in_array($tagged_phrase[$cur_node]['tag'],
            self::$conjunction_type)) {
            $cc = $tagged_phrase[$cur_node]['token'];
            $cur_node++;
        }
        $tree_np = self::parseNounPhrase($tagged_phrase,
            ['cur_node' => $cur_node]);
        if ($tree_np['cur_node'] == $cur_node && $cc) {
            $cur_node--;
            $tree_np = [];
            $cc = "";
        } else {
            $cur_node = $tree_np['cur_node'];
        }
        unset($tree_dt['cur_node'], $tree_jj['cur_node'],
            $tree_nn['cur_node'], $tree_pp['cur_node'],
            $tree_np['cur_node'], $tree_pp2['cur_node']);
        $sub_tree = ['DT' => $tree_dt, 'JJ' => $tree_jj, 'PRP' => $tree_pp,
        'NN' => $tree_nn, 'PRP2'=>$tree_pp2,'CC' => $cc,
            'ADD_NP' => $tree_np];
        return ['cur_node' => $cur_node, 'NP' => $sub_tree];
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a verb phrase if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "VP" a subarray with possible fields
     *      "VB" with value a verb subtree
     *      "NP" with value an noun phrase subtree
     */
    public static function parseVerbPhrase($tagged_phrase, $tree)
    {
        $cur_node = $tree['cur_node'];
        $adverb_string="";
        do {
            $start_node=$cur_node;
            $adverb_string .= self::parseTypeList($cur_node, $tagged_phrase,
                self::$adverb_type);
            $adverb_string .= self::parseTypeList($cur_node, $tagged_phrase,
                self::$particle_type);
        } while($start_node!=$cur_node);
        if (!empty($adverb_string)) {
            $tree_vb["RB"] = $adverb_string;
        } else {
            $tree_vb=[];
        }
        $tree_vb = array_merge($tree_vb,
            self::parseVerb($tagged_phrase, ['cur_node' => $cur_node]));
        if ($cur_node == $tree_vb['cur_node']) {
            // if no verb return what started with
            return $tree;
        }
        $cur_node = $tree_vb['cur_node'];
        $tree_np = self::parseNounPhrase($tagged_phrase,
            ['cur_node' => $tree_vb['cur_node']]);
        $cur_node = $tree_np['cur_node'];
        if (!empty($tree_np['NP'])) {
            unset($tree_vb['cur_node'], $tree_np['cur_node']);
            return ['VP' => ['VB' => $tree_vb, 'NP' => $tree_np['NP']],
                'cur_node' => $cur_node];
        }
        unset($tree_vb['cur_node']);
        return ['VP' => ['VB' => $tree_vb], 'cur_node' => $cur_node];
    }
    /**
     * Given a part-of-speeech tagged phrase array generates a parse tree
     * for the phrase using a recursive descent parser.
     *
     * @param array $tagged_phrase an array of pairs of the form
     *  ("token" => token_for_term, "tag"=> part_of_speech_tag_for_term)
     * @param $tree that consists of ["curnode" =>
     *  current parse position in $tagged_phrase]
     * @param $tree_np_pre subject found from previous sub-sentence
     * @return array used to represent a tree. The array has up to three fields
     *  $tree["cur_node"] index of how far we parsed our$tagged_phrase
     *  $tree["NP"] contains a subtree for a noun phrase
     *  $tree["VP"] contains a subtree for a verb phrase
     */
    public static function parseWholePhrase($tagged_phrase, $tree,
        $tree_np_pre = [])
    {
        //remove heading adverbs
        $cur_node = $tree['cur_node'];
        do {
            $start_node = $cur_node;
            self::parseTypeList($cur_node, $tagged_phrase,
                self::$adverb_type);
            self::parseTypeList($cur_node, $tagged_phrase,
                self::$particle_type);
        } while ($start_node != $cur_node);
        $tree_np = self::parseNounPhrase($tagged_phrase,
            ["cur_node" => $cur_node]);
        if ($tree_np['cur_node'] == $cur_node) {
            if (!empty($tree_np_pre)) {
                $tree_np['NP'] = $tree_np_pre;
                $tree_np["cur_node"] = $cur_node;
            } else {
                return $tree;
            }
        }
        $tree_vp = self::parseVerbPhrase($tagged_phrase,
            ["cur_node" => $tree_np['cur_node']]);
        if ($tree_np['cur_node'] == $tree_vp['cur_node']) {
            return $tree;
        }
        $cur_node = $tree_vp['cur_node'];
        unset($tree_np['cur_node'], $tree_vp['cur_node']);
        if (!empty($tree_start) && !empty($tree_np['NP'])) {
            $tree_np['NP']['PRP-1'] = $tree_start;
        }
        return ['cur_node' => $cur_node, 'NP' => $tree_np['NP'],
            'VP' => $tree_vp['VP']];
    }
    /**
     * Takes a parse tree of a phrase and computes subject, predicate, and
     * object arrays. Each of these array consists of two components CONCISE and
     * RAW, CONCISE corresponding to something more similar to the words in the
     * original phrase and RAW to the case where extraneous words have been
     * removed
     *
     * @param are $tree a parse tree for a sentence
     * @return array triplet array
     */
    public static function extractTripletsParseTree($tree)
    {
        $triplets = [];
        $triplets['subject'] = self::extractSubjectParseTree($tree);
        $triplets['predicate'] = self::extractPredicateParseTree($tree);
        $triplets['object'] = self::extractObjectParseTree($tree);
        return $triplets;
    }
    /**
     * Takes phrase tree $tree and a part-of-speech $pos returns
     * the deepest $pos only path in tree.
     *
     * @param array $tree phrase to extract type from
     * @param string $pos the part of speech to extract
     * @return string the label of deepest $pos only path in $tree
     */
    public static function extractDeepestSpeechPartPhrase($tree, $pos)
    {
        $extract = "";
        if (!empty($tree[$pos])) {
            $extract = self::extractDeepestSpeechPartPhrase($tree[$pos], $pos);
        }
        if (!$extract && !empty($tree[$pos]) && !empty($tree[$pos][$pos])) {
            $extract = $tree[$pos][$pos];
        }
        return $extract;
    }
    /**
     * Takes a parse tree of a phrase or statement and returns an array
     * with two fields CONCISE and RAW the former having the object of
     * the original phrase (as a string) the latter having the importart
     * parts of the object
     *
     * @param array representation of a parse tree of a phrase
     * @return array with two fields CONCISE and RAW as described above
     */
    public static function extractObjectParseTree($tree)
    {
        $object = [];
        if (!empty($tree['VP'])) {
            $tree_vp = $tree['VP'];
            if (!empty($tree_vp['NP'])) {
                $nb = $tree_vp['NP'];
                $object['CONCISE'] = $tree_vp['NP'];
                while (isset($object['CONCISE']["ADD_NP"]["NP"]) &&
                    is_array($object['CONCISE']["ADD_NP"]["NP"])) {
                    $object['CONCISE'] = $object['CONCISE']["ADD_NP"]["NP"];
                }
                $object['CONCISE'] = $object['CONCISE']["NN"]["NN"] ??
                    "";
                $raw_object = "";
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($nb));
                foreach ($it as $v) {
                    $raw_object .= $v;
                }
                $object['RAW'] = $raw_object;
            } else {
                $object['CONCISE'] = "";
                $object['RAW'] = "";
            }
        } else {
            $object['CONCISE'] = "";
            $object['RAW'] = "";
        }
        return $object;
    }
    /**
     * Takes a parse tree of a phrase or statement and returns an array
     * with two fields CONCISE and RAW the former having the predicate of
     * the original phrase (as a string) the latter having the importart
     * parts of the predicate
     *
     * @param array representation of a parse tree of a phrase
     * @return array with two fields CONCISE and RAW as described above
     */
    public static function extractPredicateParseTree($tree)
    {
        $predicate = [];
        if (!empty($tree['VP'])) {
            $tree_vp = $tree['VP'];
            $predicate['CONCISE'] = self::extractDeepestSpeechPartPhrase(
                $tree_vp, "VB");
            $raw_predicate = "";
            if (!empty($tree_vp['VB'])) {
                $tree_vb = $tree_vp['VB'];
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($tree_vb));
                foreach ($it as $v) {
                    $raw_predicate .= $v;
                }
                $predicate['RAW'] = $raw_predicate;
            }
        } else {
            $predicate['CONCISE'] = "";
            $predicate['RAW'] = "";
        }
        return $predicate;
    }
    /**
     * Takes a parse tree of a phrase or statement and returns an array
     * with two fields CONCISE and RAW the former having the subject of
     * the original phrase (as a string) the latter having the importart
     * parts of the subject
     *
     * @param array representation of a parse tree of a phrase
     * @return array with two fields CONCISE and RAW as described above
     */
    public static function extractSubjectParseTree($tree)
    {
        $subject = [];
        if (!empty($tree['NP'])) {
            $subject['CONCISE'] = $tree['NP'];
            while (isset($subject['CONCISE']["ADD_NP"]["NP"]) &&
                is_array($subject['CONCISE']["ADD_NP"]["NP"])) {
                $subject['CONCISE'] = $subject['CONCISE']["ADD_NP"]["NP"];
            }
            $subject['CONCISE'] = $subject['CONCISE']["NN"]["NN"] ?? "";
            $raw_subject = "";
            $it = new \RecursiveIteratorIterator(
                new \RecursiveArrayIterator($tree['NP']));
            foreach ($it as $v) {
                $raw_subject .= $v ;
            }
            $subject['RAW'] = $raw_subject;
        } else {
            $subject['CONCISE'] = "";
            $subject['RAW'] = "";
        }
        return $subject;
    }
    /**
     * Takes a triplets array with subject, predicate, object fields with
     * CONCISE and RAW subfields and rearranges it to have two fields CONCISE
     * and RAW with subject, predicate, object, and QUESTION_ANSWER_LIST
     * subfields
     *
     * @param array $sub_pred_obj_triplets in format described above
     * @return array $processed_triplets in format described above
     */
    public static function rearrangeTripletsByType($sub_pred_obj_triplets)
    {
        $processed_triplet = [];
        $processed_triplets['CONCISE'] =
            self::extractTripletByType($sub_pred_obj_triplets, "CONCISE");
        $processed_triplets['RAW'] =
            self::extractTripletByType($sub_pred_obj_triplets, "RAW");
        return $processed_triplets;
    }
    /**
     * Takes a triplets array with subject, predicate, object fields with
     * CONCISE, RAW subfields and produces a triplits with $type subfield (where
     * $type is one of CONCISE and RAW) and with subject, predicate, object,
     * and QUESTION_ANSWER_LIST subfields
     *
     * @param array $sub_pred_obj_triplets  in format described above
     * @param string $type either CONCISE or RAW
     * @return array $triplets in format described above
     */
    public static function extractTripletByType($sub_pred_obj_triplets, $type)
    {
        $triplets = [];
        if (!empty($sub_pred_obj_triplets['subject'][$type])
            && !empty($sub_pred_obj_triplets['predicate'][$type])
            && !empty($sub_pred_obj_triplets['object'][$type])) {
            $question_answer_triplets = [];
            $sentence = [ trim($sub_pred_obj_triplets['subject'][$type]),
                trim($sub_pred_obj_triplets['predicate'][$type]),
                trim($sub_pred_obj_triplets['object'][$type])];
            $question_triplets = [];
            for ($j = 0; $j < 2; $j++) {
                if ($j == 1 && in_array($sentence[1], ['是'])) {
                    $tmp = $sentence[2];
                    $sentence[2] = $sentence[0];
                    $sentence[0] = $tmp;
                } else if ($j == 1) {
                    break;
                }
                for ($i = 0; $i < 3; $i++) {
                    $q_sentence = $sentence;
                    $q_sentence[$i] = self::$question_token;
                    $q_sentence_string = implode(" ", $q_sentence);
                    $question_triplets[] = $q_sentence_string;
                    $question_answer_triplets[$q_sentence_string] =
                        preg_replace('/\s+/u', ' ',$sentence[$i]);
                }
            }
            $triplets['QUESTION_LIST'] = $question_triplets;
            $triplets['QUESTION_ANSWER_LIST'] = $question_answer_triplets;
        }
        return $triplets;
    }
    /**
     * Takes any question started with WH question and returns the
     * triplet from the question
     *
     * @param string $question question to parse
     * @return array question triplet
     */
    public static function questionParser($question)
    {
        $tagged_question = self::tagTokenizePartOfSpeech($question);
        $generated_questions = [];
        $keywords = self::isQuestion($question);
        if ($keywords) {
            $generated_questions=self::parseQuestion(
                $tagged_question, 1, $keywords);
        }
        return $generated_questions;
    }
    /**
     * Takes a phrase query entered by user and return true if it is question
     * and false if not
     *
     * @param $phrase any statement
     * @return bool returns question word if statement is question
     */
    public static function isQuestion($phrase)
    {
        $terms=self::getStochasticTermSegmenter()->segmentSentence($phrase);
        $qt=self::questionType($terms, self::$question_words);
        if (in_array($qt["types"], ["who","what","which","where","when",
            "whose", "how", "how many", "how long", "how big"])) {
            return $qt["ques_words"];
        }
        return false;
    }
    /**
     * Takes tagged question string starts with Who
     * and returns question triplet from the question string
     *
     * @param string $tagged_question part-of-speech tagged question
     * @param int $index current index in statement
     * @param string $question_word is the question word need to be replaced
     * @return array parsed triplet
     */
    public static function parseQuestion($tagged_question, $index,
        $question_word)
    {
        $generated_questions = [];
        $tree = ["cur_node" => 0];
        $parse_tree = self::parseWholePhrase($tagged_question, $tree);
        $triplets = self::extractTripletsParseTree($parse_tree);
        $triplet_types = ['CONCISE', 'RAW'];
        foreach ($triplet_types as $type) {
            if (!empty($triplets['subject'][$type])
                && !empty($triplets['predicate'][$type])
                && !empty($triplets['object'][$type])) {
                $sub = trim($triplets['subject'][$type]);
                $sub = preg_replace("/^.*".$question_word.".*$/u",
                    self::$question_token, $sub);
                $pre = trim($triplets['predicate'][$type]);
                $pre = preg_replace("/^.*".$question_word.".*$/u",
                    self::$question_token, $pre);
                $obj = trim($triplets['object'][$type]);
                $obj = preg_replace("/^.*".$question_word.".*$/u",
                    self::$question_token, $obj);
                $generated_questions[$type][] = $obj . " " . $pre . " " . $sub;
                $generated_questions[$type][] = $sub . " " . $pre . " " . $obj;
            }
        }
        return $generated_questions;
    }
    /**
     * Helper function for isQuestion
     * @param $term_array segmented Chinese terms
     * @param $type_list currect trace of self::$question_words
     * return ["ques_words"=>ques_words,"types"=>types]
     */
    public static function questionType($term_array, $type_list)
    {
        if (!isset($type_list["any"])) {
            return ["ques_words"=>"","types"=>""];
        }
        $types = "";
        $ques_words = "";
        for($i = 0; $i < count($term_array); $i++ ) {
            foreach($type_list["any"] as $key => $value) {
                if (preg_match('/^('.$key.')$/u',$term_array[$i])) {
                    if (is_array($value)) {
                        if(isset($value["after"])) {
                            $found_after = false;
                            if (array_key_exists($i+1,$term_array)) {
                                foreach($value["after"] as $key2 => $value2) {
                                    if (preg_match('/^(' . $key2 . ')$/u',
                                        $term_array[$i + 1])) {
                                        $ques_words = $term_array[$i].
                                            " " . $term_array[$i + 1];
                                        $types = $value2;
                                        $found_after = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found_after && isset($type_list["other"]) &&
                                $value["other"]) {
                                $ques_words = $term_array[$i];
                                $types = $value["other"];
                            }
                        } elseif (isset($value["any"])) {
                            $t = self::questionType($term_array,$value);
                            $ques_words[] = $term_array[$i];
                            $types = $t["types"];
                        }
                    } elseif ($value) {
                        $ques_words = $term_array[$i];
                        $types = $value;
                    }
                }
            }
        }
        if ($types == "" && isset($type_list["other"])) {
            if (is_array($type_list["other"])) {
                $t = self::questionType($term_array, $type_list["other"]);
                $ques_words = $t["ques_words"];
                $types = $t["types"];
            } elseif ($type_list["other"]) {
                $types = $type_list["other"];
            }
        }
        return ["ques_words" => $ques_words, "types" => $types];
    }
}
