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
namespace seekquarry\yioop\locale\hi\resources;

use seekquarry\yioop\configs as C;
/**
 * Hindi specific tokenization code. In particular, it has a stemmer,
 * The stemmer is my stab at porting Ljiljana Dolamic (University of Neuchatel,
 * www.unine.ch/info/clef/) Java stemming algorithm:
 * http://members.unine.ch/jacques.savoy/clef/HindiStemmerLight.java.txt
 * Here given a word, its stem is that part of the word that
 * is common to all its inflected variants. For example,
 * tall is common to tall, taller, tallest. A stemmer takes
 * a word and tries to produce its stem.
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
    public static $stop_words = ['जैसा', 'मैं', 'उसके', 'कि', 'वह', 'था', 'के',
        'लिए', 'पर', 'हैं', 'साथ', 'वे', 'हो', 'पर', 'एक', 'है', 'इस', 'से', 'द्वारा',
        'गरम', 'शब्द', 'लेकिन', 'क्या', 'कुछ', 'है', 'यह', 'आप', 'या', 'था', 'की',
        'तक', 'और', 'एक', 'में', 'हम', 'कर', 'सकते', 'हैं', 'बाहर', 'अन्य', 'थे', 'जो',
        'कर', 'उनके', 'समय', 'अगर', 'होगा', 'कैसे', 'कहा', 'एक', 'प्रत्येक', 'बता',
        'करता', 'है', 'सेट', 'तीन', 'चाहते हैं', 'हवा', 'अच्छी तरह से', 'भी', 'खेलने',
        'छोटे', 'अंत', 'डाल', 'घर', 'पढ़ा', 'हाथ', 'बंदरगाह', 'बड़ा', 'जादू', 'जोड़',
        'और', 'भी', 'भूमि', 'यहाँ', 'चाहिए', 'बड़ा', 'उच्च', 'ऐसा', 'का', 'पालन', 'करें',
        'अधिनियम', 'क्यों', 'पूछना', 'पुरुषों', 'परिवर्तन', 'चला', 'गया', 'प्रकाश', 'तरह',
        'बंद', 'आवश्यकता', 'घर', 'तस्वीर', 'कोशिश', 'हमें', 'फिर', 'पशु', 'बिंदु', 'मां',
        'दुनिया', 'निकट', 'बनाना', 'आत्म', 'पृथ्वी', 'पिता'];
    /**
     * List of verb-like parts of speech that might appear in lexicon
     * @var array
     */
    public static $verb_type = ["VB", "VBD", "VBG", "VBN", "VBP", "VBZ",
        "RB"];
    /**
     * List of noun-like parts of speech that might appear in lexicon
     * @var array
     */
    public static $noun_type = ["NN", "NNS", "NNP", "NNPS", "DT"];
    /**
     * List of adjective-like parts of speech that might appear in lexicon
     * @var array
     */
    public static $adjective_type = ["JJ", "JJR", "JJS"];
    /**
     * List of postpositional-like parts of speech that might appear in lexicon
     * @var array
     */
    public static $postpositional_type = ["IN", "inj", "PREP", "proNN",
        "CONJ", "INT", "particle", "case", "PSP", "direct_DT", "PRP"];
    /**
     * List of questions in Hindi
     * @var array
     */
    public static $question_pattern =
        "/\b[क्या|कब|कहा|क्यों|कौन|जिसे|जिसका|कहाँ|कहां]\b/ui";
    /**
     * Any unique identifier corresponding to the component of a triplet which
     * can be answered using a question answer list
     * @var string
     */
    public static $question_token = "qqq";
    /**
     * Words we don't want to be stemmed
     * @var array
     */
    public static $no_stem_list = [];
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
    /**
     * Stub function which could be used for a word segmenter.
     * Such a segmenter on input thisisabunchofwords would output
     * this is a bunch of words
     *
     * @param string $pre_segment  before segmentation
     * @return string should return string with words separated by space
     *     in this case does nothing
     */
    public static function segment($pre_segment)
    {
        return $pre_segment;
    }
    /**
     * Computes the stem of an Hindi word
     *
     * @param string $word the string to stem
     * @return string the stem of $word
     */
    public static function stem($word)
    {
        return $word;
    }
    /**
     * Removes common Hindi suffixes
     *
     * @param string $word to remove suffixes from
     * @return string result of suffix removal
     */
    private static function removeSuffix($word)
    {
        return $word;
    }
    /**
     * The method takes as input a phrase and returns a string with each
     * term tagged with a part of speech.
     *
     * @param string $phrase text to add parts speech tags to
     * @param bool $with_tokens whether to include the terms and the tags
     *      in the output string or just the part of speech tags
     * @return string $tagged_phrase which is a string of format term~pos
     */
    public static function tagPartsOfSpeechPhrase($phrase, $with_tokens = true)
    {
        $tagged_tokens = self::tagTokenizePartOfSpeech($phrase);
        $tagged_phrase  = self::taggedPartOfSpeechTokensToString(
            $tagged_tokens, $with_tokens);
        return $tagged_phrase;
    }
    /**
     * Uses the lexicon to assign a tag to each token and then uses a rule
     * based approach to assign the most likely of tags to each token
     *
     * @param string $text input phrase which is to be tagged
     * @return string $result which is an array of token => tag
     */
    public static function tagTokenizePartOfSpeech($text)
    {
        static $dictionary = [];
        $lexicon_file = C\LOCALE_DIR . "/hi/resources/lexicon.txt.gz";
        if (empty($dictionary)) {
            if (file_exists($lexicon_file)) {
                $lex_data = gzdecode(file_get_contents($lexicon_file));
                preg_match_all("/([^\s\,]+)[\s|\,]+([^\n]+)/u",
                    $lex_data, $lex_parts);
                $dictionary = array_combine($lex_parts[1], $lex_parts[2]);
            }
        }
        $tokens = preg_split("/\s+/u", $text);
        $result = [];
        $tag_list = [];
        $i = 0;
        foreach ($tokens as $token)
        {
            //Tag the tokens as found in the Lexicon
            $token = trim($token);
            $current = ["token" => $token, "tag" => "UNKNOWN"];
            $term = $current["token"];
            if (!empty($dictionary[$token])) {
                $tag_list = explode(" ", $dictionary[$token]);
                $current['tag'] = $tag_list[0];
            }
            if (is_numeric($token)) {
                $current["tag"] = "NN";
            } else if (in_array($token, ["है", "हैं"])) {
                $current["tag"] = "VB";
            }
            if (empty($current["tag"])) {
                $current["tag"] = "UNKNOWN";
            }
            $result[$i] = $current;
            $i++;
        }
        $result = self::tagUnknownWords($result);
        return $result;
    }
    /**
     * This method tags the remaining words in a partially tagged text array.
     *
     * @param array $partially_tagged_text term array representing a text
     *      passage. Each element in array is in turnan associative array
     *      [token => token_value, tag => tag_value (may be empty)]
     * @return array text passage array where all empty tags now have values
     */
    public static function tagUnknownWords($partially_tagged_text)
    {
        $result = $partially_tagged_text;
        $verbs = ["VBZ", "VBD", "VBN"];
        $length = count($result);
        $previous = $result[0];
        for ($i = 1; $i < $length; $i++)
        {
            $current = $result[$i];
            $current["token"] = trim($current["token"]);
            $current["tag"] = trim($current["tag"]);
            if ($current["tag"] == "UNKNOWN" || $previous["tag"] == "UNKNOWN") {
                /**
                 * RULE 1: If the previous word tagged is a Adjective Pronoun
                 * Postposition then the current word is likely to be a noun
                 */
                if ($previous["tag"] == "JJ"     ||
                    $previous["tag"] == "PRO_NN" ||
                    $previous["tag"] == "POST_POS") {
                    $current["tag"] = "NN";
                    $result[$i] = $current;
                }
                /**
                 * RULE 2: If the current word is a verb then the previous
                 * word is likely to be a noun
                 */
                if (in_array($current["tag"], $verbs)) {
                    $previous["tag"] = "NN";
                    $result[$i-1] = $previous;
                }
                /**
                 * PRONOUN IDENTIFICATION
                 * RULE 3: If the previous word is unknown and cuurent word
                 * is a noun then the previous word is most likely to be a
                 * pronoun
                 */
                if ($previous["tag"] == "UNKNOWN" &&
                    $current["tag"] == "NN") {
                    $previous["tag"] = "PRP";
                    $result[$i-1] = $previous;
                }
                /**
                 * VERB IDENTIFICATION
                 * RULE 4: If the current word is tagged as Auxilary verb and
                 * previous word is tagged as Unknown then most likely that
                 * the previous word is a verb
                 */
                if ($current["tag"] == "VAUX" &&
                    $previous["tag"] == "UNKNOWN") {
                    $previous["tag"] = "VB";
                    $result[$i-1] = $previous;
                }
                /**
                 * ADJECTIVE IDENTIFIATION
                 * RULE 5: if the currennt word ends with "तम" or "इक" or "िक"
                 * or "तर" then the word is an adjective
                 */
                if(mb_substr($current["token"], -2, 2) == "इक" ||
                    mb_substr($current["token"], -2, 2) == "िक" ||
                    mb_substr($current["token"], -2, 2) == "तर"  ||
                    mb_substr($current["token"], -2, 2) == "तम") {
                    $current["tag"] = "JJ";
                    $result[$i] = $current;
                }
                if ($current["tag"] == "UNKNOWN") {
                    $current["tag"] = "NN";
                    $result[$i] = $current;
                }
                if ($previous["tag"] == "UNKNOWN"){
                    $previous["tag"] = "NN";
                    $result[$i-1] = $previous;
                }
            }
            $previous = $current;
        }
        return $result;
     }
    /**
     * This method is used to simplify the different tags of speech to a
     * common form
     *
     * @param array $tagged_tokens which is an array of tokens assigned tags.
     * @param bool $with_tokens whether to include the terms and the tags
     *      in the output string or just the part of speech tags
     * @return string $tagged_phrase which is a string fo form token~pos
     */
    public static function taggedPartOfSpeechTokensToString($tagged_tokens,
        $with_tokens = true)
    {
        $tagged_phrase = "";
        $with_tokens = $with_tokens;
        $simplified_parts_of_speech = [
          "NNS" => "NN", "NNP" => "NN", "NNPS" => "NN","WP" => "NN",
          "VB" => "VB", "VBD" => "VB", "VBN" => "VB", "VBP" => "VB",
          "VBZ" => "VB",
          "JJ" => "AJ", "JJR" => "AJ", "JJS" => "AJ",
          "RB" => "AV", "RBR" => "AV", "RBS" => "AV", "WRB" => "AV",
          "inj" => "IN", "case" => "IN", "proNN" => "IN", "particle" => "IN",
          "PREP" => "IN", "IN" => "IN", "PSP" => "IN",
          "direct_DT" => "DT",
       ];
        foreach ($tagged_tokens as $t) {
            $tag = trim($t["tag"]);
            $tag = (isset($simplified_parts_of_speech[$tag])) ?
                   $simplified_parts_of_speech[$tag] : $tag;
            $token = ($with_tokens) ? $t["token"] . "~" : "";
            $tagged_phrase .= $token . $tag .  " ";
        }
        return $tagged_phrase;
    }
    /**
     * Starting at the $cur_node in a $tagged_phrase parse tree for a Hindi
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
        $tag_phrase_string = "";
        $start_node = $cur_node;
        $next_tag = (empty($tagged_phrase[$cur_node]['tag'])) ? "" :
            trim($tagged_phrase[$cur_node]['tag']);
        $allowed_conjuncts = [];
        while ($next_tag && (in_array($next_tag, $type))) {
            $tag_phrase_string .= " ". $tagged_phrase[$cur_node]['token'];
            $cur_node++;
            $next_tag = (empty($tagged_phrase[$cur_node]['tag'])) ? "" :
                trim($tagged_phrase[$cur_node]['tag']);
        }
        return $tag_phrase_string;
    }
    /**
     * Takes a part-of-speech tagged phrase and pre-tree with a
     * parse-from position and builds a parse tree for a noun if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag" => part_of_speech_tag_for_term)
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
        }
        return $tree;
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
        }
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and parse-tree with a
     * parse-from position and builds a parse tree for a sequence of
     * postpositional phrases if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag" => part_of_speech_tag_for_term)
     * @param array $tree that consists of ["cur_node" =>
     *      current parse position in $tagged_phrase]
     * @param int $index position in array to start from
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     */
    public static function parsePostpositionPhrase($tagged_phrase, $tree,
        $index = 1)
    {
        $cur_node = $tree["cur_node"];
        $tree_pp["cur_node"] = $tree["cur_node"];
        if (isset ($tagged_phrase[$cur_node]["tag"]) &&
            in_array($tagged_phrase[$cur_node]["tag"],
            self::$postpositional_type)) {
            $pp_string = self::parseTypeList($cur_node, $tagged_phrase,
                self::$postpositional_type);
            if (!empty($pp_string)) {
                $tree_pp["IN_$index"] = $pp_string;
            }
            $adjective_string = self::parseTypeList($cur_node, $tagged_phrase,
                self::$adjective_type);
            if (!empty($adjective_string)) {
                $tree_pp["JJ_$index"] = $adjective_string;
            }
            $nn_string = self::parseTypeList($cur_node, $tagged_phrase,
                self::$noun_type);
            if (!empty($nn_string)) {
                $tree_pp["NN_$index"] = $nn_string;
            }
            $tree_pp["cur_node"] = $cur_node;
            $tree_next = self::parsePostpositionPhrase($tagged_phrase,
                $tree_pp, $index + 1);
            $tree_pp = array_merge($tree_pp, $tree_next);
        }
        $tree["cur_node"] = $tree_pp["cur_node"];
        unset($tree_pp["cur_node"]);
        $tree["POST"] = $tree_pp;
        return $tree;
    }
    /**
     * Takes a part-of-speech tagged phrase and parse-tree with a
     * parse-from position and builds a parse tree for a noun phrase if possible
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param array $tree that consists of ["curnode" =>
     *      current parse position in $tagged_phrase]
     * @return array has fields
     *      "cur_node" index of how far we parsed $tagged_phrase
     *      "JJ" with value an Adjective subtree
     *      "NN" with value of a Noun Subtree
     */
    public static function parseNounPhrase($tagged_phrase, $tree)
    {
        $cur_node = $tree["cur_node"];
        $tree_jj = self::parseAdjective($tagged_phrase,
            ["cur_node" => $tree["cur_node"]]);
        $tree_nn = self::parseNoun($tagged_phrase,
            ["cur_node" => $tree_jj["cur_node"]]);
        if ($tree_nn["cur_node"] == $cur_node) {
            $tree["NP"] = "";
            return $tree;
        }
        $cur_node = $tree_nn["cur_node"];
        unset($tree_jj["cur_node"], $tree_nn["cur_node"]);
        return ["cur_node" => $cur_node, "NP" =>
            ["JJ" => $tree_jj, "NN" => $tree_nn] ];
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
     */
    public static function parseVerbPhrase($tagged_phrase, $tree)
    {
        $cur_node = $tree["cur_node"];
        $tree_vb = self::parseVerb($tagged_phrase, ["cur_node" => $cur_node]);
        if ($tree_vb["cur_node"] == $cur_node) {
            $tree["VP"] = [];
            return $tree;
        }
        $cur_node = $tree_vb["cur_node"];
        $postposition_string = self::parseTypeList($cur_node,
            $tagged_phrase, self::$postpositional_type);
        if (!empty($postposition_string)) {
            $tree_vb["IN"] = $postposition_string;
        }
        $tree_np = self::parseNounPhrase($tagged_phrase,
            ["cur_node" => $cur_node]);;
        if ($tree_np["cur_node"] !=  $cur_node) {
            $cur_node = $tree_np["cur_node"];
            unset($tree_vb["cur_node"], $tree_np["cur_node"]);
            return ['cur_node' => $cur_node, 'VP' =>['VB' => $tree_vb,
                'NP' => $tree_np["NP"]]];
        }
        unset($tree_vb["cur_node"]);
        return ['cur_node' => $cur_node, 'VP' => ['VB' => $tree_vb]];
    }
    /**
     * Given a part-of-speeech tagged phrase array generates a parse tree
     * for the phrase using a recursive descent parser.
     *
     * @param array $tagged_phrase
     *      an array of pairs of the form ("token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term)
     * @param $tree this parameter is ignored but kept so as to match
     *      other methods such as @see parseNounPhrase in the recursive
     *      descent parser
     * @return array used to represent a tree. The array has up to three fields
     *      $tree["cur_node"] index of how far we parsed our$tagged_phrase
     *      $tree["NP"] contains a subtree for a subject phrase
     *      $tree["POST"] contains a subtree for a object phrase
     *      $tree["VP"] contains a subtree for a predicate phrase
     */
    public static function parseWholePhrase($tagged_phrase, $tree = [])
    {
        $tree_np = self::parseNounPhrase($tagged_phrase, ["cur_node" => 0]);
        $tree_pp = self::parsePostpositionPhrase($tagged_phrase,
            ["cur_node" => $tree_np["cur_node"]] );
        $tree_vp = self::parseVerbPhrase($tagged_phrase,
            ["cur_node" => $tree_pp["cur_node"]] );
        $cur_node = $tree_vp["cur_node"];
        unset($tree_np["cur_node"], $tree_pp["cur_node"], $tree_vp["cur_node"]);
        return ["cur_node" => $cur_node, "NP" => $tree_np["NP"],
            "POST" => $tree_pp["POST"], "VP" => $tree_vp["VP"]];
    }
    /**
     * Scans a word list for phrases. For phrases found generate
     * a list of question and answer pairs at two levels of granularity:
     * CONCISE (using all terms in orginal phrase) and RAW (removing
     * (adjectives, etc).
     *
     * @param array $word_and_phrase_list of statements
     * @return array with two fields: QUESTION_LIST consisting of
     *      (SUBJECT, COMPLEMENT) where one of the components has been
     *      replaced with a question marker.
     */
    public static function extractTripletsPhrases($word_and_phrase_list)
    {
        $triplets_list = [];
        $question_list = [];
        $question_answer_list = [];
        $triplet_types = ["CONCISE", "RAW"];
        foreach ($word_and_phrase_list as $word_and_phrase => $position_list) {
            $sentence = $word_and_phrase;
            $sentence = preg_replace("/\s+/u", " ", $word_and_phrase);
            $sentence = trim($sentence);
            $tagged_phrase = self::tagTokenizePartOfSpeech($sentence);
            $parse_tree = self::parseWholePhrase($tagged_phrase,
                ["cur_node" => 0]);
            $triplets = self::extractTripletsParseTree($parse_tree);
            $extracted_triplets = self::rearrangeTripletsByType($triplets);
            foreach ($triplet_types as $type) {
                if (!empty($extracted_triplets[$type])) {
                    $triplets = $extracted_triplets[$type];
                    $questions = $triplets["QUESTION_LIST"];
                    foreach ($questions as $question) {
                        $question_list[$question] = $position_list;
                    }
                    $question_answer_list = array_merge($question_answer_list,
                        $triplets["QUESTION_ANSWER_LIST"]);
                }
            }
        }
        $out_triplets["QUESTION_LIST"] = $question_list;
        $out_triplets["QUESTION_ANSWER_LIST"] = $question_answer_list;
        return $out_triplets;
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
        if (!empty($tree["NP"])) {
            $subject["CONCISE"] = self::extractDeepestSpeechPartPhrase(
                $tree["NP"], "NN");
            $raw_subject = "";
            $it = new \RecursiveIteratorIterator(
                new \RecursiveArrayIterator($tree["NP"]));
            foreach ($it as $v) {
                $raw_subject .= $v . " ";
            }
            $subject["RAW"]= $raw_subject;
        } else {
            $subject["CONCISE"] = "";
            $subject["RAW"] = "";
        }
        return $subject;
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
        if (!empty($tree["VP"])) {
            $tree_vp = $tree["VP"];
            $predicate["CONCISE"] = self::extractDeepestSpeechPartPhrase(
                $tree_vp, "VB");
            $raw_predicate = "";
            if (!empty($tree_vp["VB"])) {
                $tree_vb = $tree_vp["VB"];
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($tree_vb));
                foreach ($it as $v) {
                    $raw_predicate .= $v . " ";
                }
                $predicate["RAW"] = $raw_predicate;
            }
        } else {
            $predicate["CONCISE"] = "";
            $predicate["RAW"] = "";
        }
        return $predicate;
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
        if (!empty($tree["POST"])) {
            $tree_pp = $tree["POST"];
            if (!empty($tree_pp["NP"])) {
                $np = $tree_pp["NP"];
                $object["CONCISE"] = self::extractDeepestSpeechPartPhrase($np,
                    "NN");
            } else {
                $object["CONCISE"] = "";
            }
            $raw_object = "";
            $it = new \RecursiveIteratorIterator(
                new \RecursiveArrayIterator($tree_pp));
            foreach ($it as $v) {
                $raw_object .= $v . " ";
            }
            $object["RAW"] = $raw_object;
        } else {
            $object["CONCISE"] = "";
            $object["RAW"] = "";
        }
        return $object;
    }
    /**
     * Takes a parse tree of a phrase and computes subject, predicate, and
     * object arrays. Each of these array consists of two components CONCISE and
     * RAW, CONCISE corresponding to something more similar to the words in the
     * original phrase and RAW to the case where extraneous words have been
     * removed
     *
     * @param  array $parse_tree a parse tree for a sentence
     * @return array triplet array
     */
    public static function extractTripletsParseTree($parse_tree)
    {
        $triplets = [];
        $triplets["subject"] = self::extractSubjectParseTree($parse_tree);
        $triplets["object"] = self::extractObjectParseTree($parse_tree);
        $triplets["predicate"] = self::extractPredicateParseTree($parse_tree);
        return $triplets;
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
        $processed_triplets["CONCISE"] =
            self::extractTripletByType($sub_pred_obj_triplets, "CONCISE");
        $processed_triplets["RAW"] =
            self::extractTripletByType($sub_pred_obj_triplets, "RAW");
        return $processed_triplets;
    }
    /**
     * Takes a triplets array with subject, predicate, object fields with
     * CONCISE, RAW subfields and produces triplets with $type subfield
     * where $type is one of CONCISE and RAW and with subject, predicate,
     * object and QUESTION_ANSWER_LIST subfields
     *
     * @param array $sub_pred_obj_triplets  in format described above
     * @param string $type either CONCISE or RAW
     * @return array $triplets in format described above
     */
    public static function extractTripletByType($sub_pred_obj_triplets, $type)
    {
        $triplets = [];
        if (!empty($sub_pred_obj_triplets["subject"][$type])
            && !empty($sub_pred_obj_triplets["predicate"][$type])
            && !empty($sub_pred_obj_triplets["object"][$type])) {
            $question_answer_triplets = [];
            $sentence = [$sub_pred_obj_triplets["subject"][$type],
                    $sub_pred_obj_triplets["object"][$type],
                    $sub_pred_obj_triplets["predicate"][$type]];
            $question_triplets = [];
            for ($j = 0; $j < 2; $j++) {
                for ($i = 0; $i < 3; $i++) {
                    $question = $sentence;
                    $question[$i] = self::$question_token;
                    $question_string = implode(" ", $question);
                    $question_string = trim($question_string);
                    $question_string = preg_replace("/\s+/u", " ",
                        $question_string);
                    $question_triplets[] = $question_string;
                    $question_answer_triplets[$question_string] =
                        preg_replace("/\s+/u", " ", $sentence[$i]);
                }
            }
            $triplets["QUESTION_LIST"] = $question_triplets;
            $triplets["QUESTION_ANSWER_LIST"] = $question_answer_triplets;
        }
        return $triplets;
    }
    /**
     * Takes tagged question string starts with Who
     * and returns question triplet from the question string
     *
     * @param string $tagged_question part-of-speech tagged question
     * @param int $index current index in statement
     * @return array parsed triplet
     */
    public static function parseQuestion($tagged_question, $index)
    {
        $generated_questions = [];
        $triplets = [];
        $tree_np = self::parseNounPhrase($tagged_question,
            ["cur_node" => 0]);
        $triplets["subject"] = self::extractSubjectParseTree($tree_np);
        $tree_vp = self::parseVerbPhrase($tagged_question,
            ["cur_node" => $index + 1]);
        $triplets["predicate"] = self::extractPredicateParseTree($tree_vp);
        $triplet_types = ["CONCISE", "RAW"];
        foreach ($triplet_types as $type) {
            if (!empty($triplets["subject"][$type])
                && !empty($triplets["predicate"][$type])) {
                $question = trim (trim($triplets["subject"][$type]) .
                    " " . self::$question_token .
                    " " . trim($triplets["predicate"][$type]));
                $question = preg_replace("/\s+/u", " ", $question);
                $generated_questions[$type][] = $question;
            }
        }
        return $generated_questions;
    }
    /**
     * Takes a phrase query entered by user and return true if it is question
     * and false if not
     *
     * @param $phrase any statement
     * @return bool returns true if statement is question
     */
    public function isQuestion($phrase)
    {
        return preg_match(self::$question_pattern, $phrase);
    }
    /**
     * Takes questions and returns the triplet from the question
     *
     * @param string $question question to parse
     * @return array question triplet
     */
    public static function questionParser($question)
    {
        $question = trim($question);
        $question = preg_replace("/\s+/u", " ", $question);
        $tagged_question = self::tagTokenizePartOfSpeech($question);
        $index = -1;
        foreach ($tagged_question as $i => $term_pos) {
            if (preg_match(self::$question_pattern, $term_pos["token"])) {
                $index = $i;
                $term_pos["tag"] = "p_wh";
                $tagged_question[$i] = $term_pos;
                break;
            }
        }
        return self::parseQuestion($tagged_question, $index);
    }
}
