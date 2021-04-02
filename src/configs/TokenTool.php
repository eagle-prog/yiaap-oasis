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
 * END LICENSE
 *
 * TokenTool is used to create suggest word dictionaries and 'n' word gram
 * filter files for the Yioop! search engine.
 *
 * A description of its usage is given in the $usage global variable
 *
 * @author Ravi Dhillon  ravi.dhillon@yahoo.com, Chris Pollett (modified for n
 *     ngrams, added more functionality)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */

namespace seekquarry\yioop\configs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\models as M;
use seekquarry\yioop\controllers\JobsController;
use seekquarry\yioop\models\LocaleModel;
use seekquarry\yioop\models\GroupModel;

if (php_sapi_name() != 'cli' ||
    defined("seekquarry\\yioop\\configs\\IS_OWN_WEB_SERVER")) {
    echo "BAD REQUEST"; exit();
}
/** Load in global configuration settings and crawlHash function */
require_once __DIR__ . "/../library/Utility.php";
ini_set("memory_limit", C\TOKEN_TOOL_MEMORY_LIMIT);
/*
   The phrase "More at Wikipedia..." with a link concludes the knowledge
   wiki entries we generate from wikipedia data.
   Many of these translations were obtained using Bing translate
   https://www.bing.com/translator/ .
   Kannada translation powered by Yandex.ttranslate
   https://translate.yandex.com/ .
 */
$more_localizations = [
    "ar" => 'المزيد في ويكيبيديا...',
    "bn" => 'উইকিপিডিয়ায় আরো...',
    "de" => 'Mehr bei Wikipedia...',
    "en-US" => 'More at Wikipedia...',
    "es" => 'Más en Wikipedia...',
    "fa" => 'بیشتر در ویکی پدیا...',
    "fr-FR" => 'Plus d\'article sur Wikipédia...',
    "he" => 'עוד בוויקיפדיה...',
    "hi" => 'विकिपीडिया में और अधिक ...',
    "id" => 'Selengkapnya di Wikipedia...',
    "it" => 'Più informazioni su Wikipedia...',
    "ja" => 'ウィキペディアでもっと...',
    "kn" => 'ಹೆಚ್ಚು ವಿಕಿಪೀಡಿಯ...',
    "ko" => '위키백과에 대한 자세한 내용은...',
    "nl" => 'Meer op Wikipedia...',
    "pl" => 'Więcej na Wikipedii...',
    "pt" => 'Mais na Wikipédia...',
    "ru" => 'Подробнее в Википедии...',
    "te" => 'వికీపీడియాలో ఎక్కువ...',
    "th" => 'เพิ่มเติมที่วิกิพีเดีย ...',
    "tl" => 'Higit pa sa Wikipedia ...',
    "tr" => 'Vikipedi\'de daha fazlası...',
    "vi-VN" => 'Thêm tại Wikipedia...',
    "zh-CN" => '更多维基百科...',
];
/**
 * Used to print out a description of how to use TokenTool.php
 * @var string
 */
$usage = <<<EOD
TokenTool.php
==============

Usage
=====
TokenTool is used to create suggest word dictionaries,
'n' word gram (for word entities) filter files, knowledge wiki and seed
site entries, segment filters, and named entity tag and part of speech tag
files for the Yioop! search engine for a locale. It can also be used to
localize Yioop text strings for a new language.
To create dictionaries, filter, or tag files, the user
puts a source file in Yioop's WORK_DIRECTORY/prepare folder. TokenTool will
typically output the resulting file in the folder
LOCALE_DIR/locale_tag/resources where locale_tag is the locale the
file is being created for. Suggest word dictionaries are used to supply the
content of the dropdown of search terms that appears as a user is entering a
query in Yioop. To make a suggest dictionary one can use a command like:

php TokenTool.php dictionary filename locale endmarker

Here filename should be in the current folder or PREP_DIR and should consist
of one word per line, locale is the locale this suggest (for example, en-US)
file is being made for and where a file suggest-trie.txt.gz will be written,
and endmarker is the end of word symbol to use in the trie. For example,
$ works pretty well.

TokenTool.php can also be used to make filter files. A filter file is used to
detect when words in a language should be treated as a unit when extracting text
during a crawl and at search time.  For example, Bill Clinton is 2 word gram
which should be treated as unit because it is a particular person. These
filter files can also be used  with a segmenter which
might be used to split Chinese or Japanese text which does not have spaces into
a sequence of Chinese and Japanese words (which may be made out of multiple
characters). For a nonsegmenter filter, TokenTool.php is run from the
command line as:

php TokenTool.php filter wiki_file lang locale n extract_type max_to_extract

where file is a page dump wikipedia xml file, is a bz2 compressed page dump
wikipedia xml file, or is a wiki page count dump file (
it can also be a folder of these kind of files) whose urls can be
used to determine the n-grams, lang is an Wikipedia language tag
(ignored in segmenter case), locale is the IANA language tag of the locale
to store the results for (if different from lang, for example, en-US versus
en for lang), n is the number of words in a row to consider , extract_type is
where from Wikipedia source to extract:

0 = title's,
1 = redirect's,
2 = page count dump wikipedia data,
3 = page count dump wiktionary data.

A knowledge wiki entry is a search wiki page which is displayed on a given
query usually in a callout box. TokenTool.php can be used to create such
entries based on the first paragraph of a Wikipedia page which matches the
query. At the same time TokenTool.php is doing this it can also use
the infoboxes on wiki pages to generate a initial list of potential seed
sites for a web crawl. The syntax to create knowledge wiki seed sites is:

php TokenTool.php kwiki-seeds locale page_count_file wiki_locale_dump \
    num_entries num_seeds

Here locale is the IANA language tag of the locale to create knowledge wiki
entries and seed sites for, page_count_file is a wiki page count dump file (
or folder of such files), wiki_locale_dump is a dump of wikipedia pages and
meta pages, num_entries says to get use the num_entries wiki pages according to
what the page count file says were most frequently accessed and only make
knowledge wiki entries for these, num_seeds says only use the infoboxes on the
top num_seeds many pages as sources of seed site urls. Seed site urls will be
written to the current crawl.ini file used to set the crawl parameters of the
next crawl.

In some Asian languages, such as Chinese, Japanese, there are no spaces
between words. In order to segment terms/words from a sentence, either
a segmenter filter file or a stochastic segmenter file is used by
Yioop. By default, Yioop tries to use stochastic segmentation to segment
strings into phrases if a stochastic segmenter file for the
locale exists. This is a slightly better approach. A segmenter
filter file allows Yioop to split phrases according to reverse maximal match,
which is a less accurate way to do segmentation.
To create such files, TokenTool.php is run from the
command line as:

php TokenTool.php stochastic-segmenter locale files_format max_files dataset_files...

or

php TokenTool.php segment-filter locale dictionary_file

respectively. Here locale is the IANA language tag of the locale to store
the results for, files_format is the format of the files,
currently supported format can be "default" or "CTB" (Chinese Tree Bank).
The default format has all word segmented by space,
CTB information can be found at:
https://www.cs.brandeis.edu/~clp/ctb/
max_files is the maximum number of found dataset files to process during
training. If negative it means train on all files, if positive train at most
that many files (useful if running out of memory). dataset_files... should be a
list of text files or glob pattern to such text files with the format described
above and dictionary_file is a text file or glob pattern to such text file
with one word/line. An example is:

php TokenTool.php stochastic-segmenter zh-CN CTB -1 segmented/*

segment-filter outputs a file LOCALE_DIR/locale_tag/resources/segment.ftr
and stochastic-segmenter outputs a file
LOCALE_DIR/locale_tag/resources/term_weights.txt.gz

TokenTool can be used to create name entity tag files. These can be
used to find named entities in text passages for a language, and is
used as part of the stochastic-segmenter process. The command to create such
a file is:

php TokenTool.php entity-tagger locale tag_separator max_files dataset_files...

the arguments are the same as stochastic-segmenter except tag_separator.
The input training files contain tagged white space separated terms.
If the tag_separator was '-', then non-named entity examples should look like:
term-o, and named entity example might look like term-nr or term-nt
where nr = proper noun, ns = place name, or nt = temporal noun. For Chinese,
one can use a little script-fu to convert the postagged data of the Chinese
treebank into this format. The file output by running the entity-tagger command
is LOCALE_DIR/locale_tag/resources/nect_weights.txt.gz

TokenTool can also be used to part of speech tag files. These can be used for
Yioop's question answering subsystem for a language. The command to create such
a file is:

php TokenTool.php pos-tagger locale tag_separator max_files dataset_files...

The command line arguments are the same as the entity-tagger command. The file
output by this command is: LOCALE_DIR/locale_tag/resources/pos_weights.txt.gz

Localizing Yioop's web app strings to a new language can be done manually
via the Manage Locale activity within Yioop. Alternatively, TokenTool can
be used to generate initial translations for these strings using the
Yandex Translate API. To use this one needs to have in Yioop's
src/configs/LocalConfig.php file a line like:
nsdefine("TRANSLATE_API_KEY", "the value of your Yandex Translate API key");
Then to translate Yioop web app strings to a locale one can do:

php TokenTool.php translate-locale locale

or

php TokenTool.php translate-locale locale with_wiki_pages

Here locale is the IANA language tag of the locale to translate. If locale
is set to "all", then translations will be done for all locales in the Yioop
system (excluding English, which is where the translations are coming from).
with_wiki_pages controls how public and help wiki pages arree translated.
By default and if this is <=0, public and help wiki pages are not translated,
if it is 1, they are translated to the locale if the locale does not already
have a translation. If it is >1 then it is force translated to locale.
After this program finishes, you should do a sanity check on the translations
using Manage Locales. Remember if you hover above a translated item in the
Edit string interface it displays as a tooltip the original English text.

Obtaining Data
==============
Many word lists are obtainable on the web for free with Creative Commons
licenses. A good starting point is:
http://en.wiktionary.org/wiki/Wiktionary:Frequency_lists
A little script-fu can generally take such a list and put it into the
format of one word/term per line which is needed by TokenTool.php

For filter file, page count dumps can be found at
https://dumps.wikimedia.org/other/pagecounts-ez/merged/
These probably give the best n-gram or all gram results, usually
in a matter of minutes; nevertheless, this tool does support trying to extract
similar data from Wikipedia dumps. This can take hours.

For Wikipedia dumps, one can go to https://dumps.wikimedia.org/enwiki/
and obtain a dump of the English Wikipedia (similar for other languages).
This page lists all the dumps according to date they were taken. Choose any
suitable date or the latest. A link with a label such as 20120104/, represents
a  dump taken on  01/04/2012.  Click this link to go in turn to a page which has
many links based on type of content you are looking for. For
this tool you are interested in files under

"Recombine all pages, current versions only".

Beneath this we might find a link with a name like:
enwiki-20120104-pages-meta-current.xml.bz2
which is a file that could be processed by this tool.

A Creative Commons licensed file which can be manipulated into a dictionary
file suitable for Chinese segmentation can be found at:
http://www.mdbg.net/chindict/chindict.php?page=cc-cedict

EOD;
$num_args = count($argv);
if ( $num_args < 3 || $num_args > 8) {
    echo $usage;
    exit();
}
switch ($argv[1]) {
    case "dictionary":
        if (!isset($argv[3])) {
            $argv[3] = "en-US";
        }
        if (!isset($argv[4])) {
            $argv[4] = "$";
        }
        makeSuggestTrie($argv[2], $argv[3], $argv[4]);
        break;
    case "entity-tagger":
        $file_names = getTrainingFileNames($argv);
        $ne_tagger = new L\NamedEntityContextTagger($argv[2]);
        $ne_tagger->train($file_names, $argv[3]);
        echo "Training Complete!";
        break;
    case "filter":
        array_shift($argv);
        array_shift($argv);
        makeNWordGramsFiles($argv);
        break;
    case "kwiki-seeds":
        if (!isset($argv[6])) {
           echo $usage;
        }
        makeKwikiEntriesGetSeedSites($argv[2], $argv[3], $argv[4],
            $argv[5], $argv[6]);
        break;
    case "pos-tagger":
        $file_names = getTrainingFileNames($argv);
        $pos_tagger = new L\PartOfSpeechContextTagger($argv[2]);
        $pos_tagger->train($file_names, $argv[3]);
        echo "Training Complete!";
        break;
    case "segment-filter":
        $file_path = PREP_DIR . "/";
        if (!file_exists($file_path . $argv[3])) {
            echo $argv[3] . " does not exist in " . $file_path;
            exit();
        }
        L\NWordGrams::makeSegmentFilterFile($file_path . $argv[3], $argv[2]);
        break;
    case "stochastic-segmenter":
        $file_names = getTrainingFileNames($argv);
        $segmenter = new L\StochasticTermSegmenter($argv[2]);
        $segmenter->train($file_names, $argv[3]);
        echo "Training Complete!";
        break;
    case "translate-locale":
        if (!isset($argv[2])) {
           echo $usage;
        }
        if (!empty($argv[3]) && intval($argv[3]) > 0) {
            require_once "PublicHelpPages.php";
        } else {
            $argv[3] = 0;
        }
        translateLocale($argv[2], $argv[3]);
        break;
    default:
        echo $usage;
        exit();
}
if (!PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}
/**
 * Returns an array of filenames to be used for training the current
 * task in TokenTool
 * @param array $command_line_args supplied to TokenTool.php. Assume
 *  array of the format:
 *  [ ... max_file_names_to_consider, file_glob1, file_glob2, ...]
 * @param int $start_index index in $command_line_args of
 *  max_file_names_to_consider
 * @return array $file_names of files with training data
 */
function getTrainingFileNames($command_line_args, $start_index = 4)
{
    $file_path = PREP_DIR . "/";
    if (!isset($command_line_args[$start_index + 1])) {
       echo $usage;
       exit();
    }
    $file_names = [];
    for($i = $start_index + 1; $i < count($command_line_args); $i++) {
        $files = glob($file_path . $command_line_args[$i]);
        if (count($files) == 0) {
            echo "error: $file_path{$command_line_args[$i]}: File not found\n";
            exit();
        }
        $file_names = array_merge($file_names, $files);
    }
    if ($command_line_args[$start_index] > 0) {
        $file_names = array_slice($file_names, 0, $command_line_args[4]);
    }
    return $file_names;
}

/**
 * Generates knowledge wiki callouts for search results pages based
 * on the first paragraph of a Wikipedia Page that matches a give qeury.
 * Also generates an initial list of potential seed sites for a crawl
 * based off urls scraped from the wiki pages.
 *
 * @param string $locale_tag the IANA language tag of the locale to
 *   create knowledge wiki entries and seed sites for
 * @param string $page_count_file the file name of a a wiki page count dump
 *  file (or folder of such files). Such a file contains the names of wiki
 *  pages and how many times they were accessed
 * @param string $wiki_dump_file  a dump of wikipedia pages and meta pages
 * @param int $max_entries maximum number of kwiki entries to create.
 *  Will pick the one with the highest counts in $page_count_file
 * @param int $max_seed_sites maximum number of seed sites to add
 *  to Yioop's set of seed sites. Again chooses those with highest
 *  page count score
 */
function makeKwikiEntriesGetSeedSites($locale_tag, $page_count_file,
    $wiki_dump_file, $max_entries, $max_seed_sites)
{
    global $more_localizations;
    $more_at_wikipedia = $more_localizations['en-US'];
    $major_tag = explode("-", $locale_tag)[0];
    if (!empty($more_localizations[$locale_tag])) {
        $more_at_wikipedia = $more_localizations[$locale_tag];
    }
    $block_size = 8192;
    $output_message_threshold = $block_size * $block_size;
    $max_pages = max($max_entries, $max_seed_sites);
    foreach (["page_count_file" => $page_count_file,
        "wiki_dump_file" => $wiki_dump_file] as $variable => $file_name) {
        if (!file_exists($file_name)) {
            if (file_exists(C\PREP_DIR . "/$file_name")) {
                $$variable = C\PREP_DIR . "/$file_name";
            } else {
                echo "File $file_name does not exist!\n";
                exit();
            }
        }
    }
    $title_counts = [];
    if (is_dir($page_count_file)) {
        $count_files = glob($page_count_file . "/*totals*.bz2");
    } else {
        $count_files = [$page_count_file];
    }
    foreach ($count_files as $page_count_file) {
        echo "Now processing page count file: $page_count_file\n";
        $title_counts = getTopPages($page_count_file, $locale_tag, $max_pages,
            $title_counts);
    }
    $rank_titles = [];
    $i = 0;
    foreach ($title_counts as $title => $count) {
        $rank_titles[$title] = $i;
        $i++;
    }
    $verticals_model = new M\SearchverticalsModel();
    $crawl_model = new M\CrawlModel();
    list($fr, $read, $close) = smartOpen($wiki_dump_file);
    $input_buffer = "";
    $time = time();
    $pages_processed = 0;
    $pages_processed_since_output = 0;
    $top_found = 0;
    $web_site_counts = [];
    while (!feof($fr)) {
        $page = getNextPage($fr, $read, $block_size, $input_buffer);
        $len = strlen($page);
        if ($len == 0) {
            break;
        }
        if($pages_processed_since_output > 10000) {
            echo "Have now read " . $pages_processed . " wiki pages." .
                " Peak memory so far: " . memory_get_peak_usage().
                ".\n     Number of top pages found: ". $top_found.
                ". Elapsed time processing wiki dump file: " .
                (time() - $time) . "s\n";
            $pages_processed_since_output = 0;
        }
        $pages_processed++;
        $pages_processed_since_output++;
        $title_offset = getTagOffsetPage($page, "title", 0);
        if ($title_offset === false || empty($title_offset[0])) {
            continue;
        }
        $title = substr($title_offset[0], strpos($title_offset[0], ">") + 1,
            -strlen("</title>"));
        $query = mb_strtolower(trim($title));
        $wiki_title = trim(str_replace(" ", "_", $title));
        $underscore_title = mb_strtolower($wiki_title);
        if (isset($title_counts[$underscore_title])) {
            $text_offset = getTagOffsetPage($page, "text", 0);
            if (!is_array($text_offset) ||
                stripos($text_offset[0], "#REDIRECT") !== false) {
                continue;
            }
            $text = substr($text_offset[0], strpos($text_offset[0], ">") + 1,
                -strlen("</text>"));
            $infobox_offset = getBraceTag($text, "{{", "}}", "Infobox");
            $website = "";
            if ($infobox_offset !== false) {
                preg_match("/website\s+\=\s*.+(https?\:[^\"\n\'\)\]\}\|\s]+)/",
                $infobox_offset[0], $matches);
                if (empty($matches[1])) {
                    preg_match("/url\s+\=\s*.+(https?\:[^\"\n\'\)\]\}\|\s]+)/",
                    $infobox_offset[0], $matches);
                }
                if (!empty($matches[1])) {
                    $website = $matches[1];
                    if (empty($web_site_counts[$website])) {
                        $web_site_counts[$website] = 0;
                    }
                    $web_site_counts[$website] +=
                        $title_counts[$underscore_title];
                }
            }
            if (($entry = $verticals_model->getKnowledgeWiki($query,
                $locale_tag)) !== false || $rank_titles[$underscore_title] >=
                $max_entries) {
                continue;
            }
            $text = str_replace($infobox_offset[0], "\n", $text);
            $text = removeTags($text, "{", "}");
            $text = removeTags($text, "&lt;!--", "--&gt;");
            $text = preg_replace('/\&lt\;ref[^\>]+\/\&gt;/u', " ", $text);
            $text = removeTags($text, "&lt;ref", "/ref&gt;");
            $text = preg_replace('/\[\[[^\[\]]+\|([^\[\|]+)\]\]/u', "$1",
                $text);
            $text = preg_replace('/\[\[(File|Image)\:(.+)\]\]/u', "", $text);
            $text = preg_replace('/\[\[(File|Image)\:(.+)\n/u', "\n", $text);
            $text = preg_replace('/\[\[([^\[\]]+)\]\]/u', "$1", $text);
            $text = preg_replace('/\'\'\'([^\']+)\'\'\'/u', "$1", $text);
            $text = preg_replace('/\'\'([^\']+)\'\'/u', "$1", $text);
            $text = preg_replace('/\'+/u', "'", $text);
            $text = preg_replace('/\n\*.+/u', "\n", $text);
            $text = preg_replace('/\([^\p{L}\p{N}]+\)/u', "", $text);
            $text = preg_replace('/\,[^\p{L}\p{N}]+(\p{P})/u', "$1", $text);
            $text = preg_replace('/\([^\p{L}\p{N}]+(\p{P})/u', "$1", $text);
            $text = preg_replace('/\((\s)+/', "(", $text);
            $text = preg_replace('/(\s+)\,/', ",", $text);
            $text = preg_replace('/\s+(\(?)quot;/', "$1'", $text);
            $text = preg_replace('/\s+(\(?)lt;/', "$1<", $text);
            $text = preg_replace('/\=\=+[^\=]+\=+\=/', " ", $text);
            $text = trim($text);
            $text = ltrim($text, ".");
            $text = trim($text);
            $text = substr($text, 0, strpos($text, "\n\n"));
            $first_paragraph = html_entity_decode(
                preg_replace('/\s+/u', " ", $text));
            $top_found++;
            if (str_word_count($first_paragraph) >= 10) {
                if (($close_paren = strpos($first_paragraph, ")")) !== false) {
                    $open_paren = strpos($first_paragraph, "(");
                    if ($open_paren === false || $open_paren > $close_paren) {
                        continue;
                    }
                }
                if (preg_match("/。|\.|\!|\?/", $first_paragraph)) {
                    if (!empty($website)) {
                        $simplified_website = L\UrlParser::simplifyUrl($website,
                            100);
                        $website = "[[$website|$simplified_website]]";
                    }
                    $wikipedia_url = "https://$major_tag.wikipedia.org/wiki/" .
                        $wiki_title;
                    $abstract = "== $title ==\n$website<br>\n$first_paragraph" .
                        "<br>\n[[$wikipedia_url|$more_at_wikipedia]]";
                    $abstract = wikiHeaderPageToString($head_vars, $abstract);
                    $verticals_model->setPageName(C\ROOT_ID,
                        C\SEARCH_GROUP_ID, mb_strtolower(
                        str_replace("-", " ", $query)), $abstract,
                        $locale_tag, time(), $query, "");
                }
            }
        }
    }
    $close($fr);
    arsort($web_site_counts);
    $web_site_counts = array_slice($web_site_counts, 0,  $max_seed_sites);
    $web_sites = array_keys($web_site_counts);
    $seed_info = $crawl_model->getSeedInfo();
    echo "Updating seed sites with found wiki seed sites\n";
    $seed_info['seed_sites']['url'][] = "#\n#" .
        date('r')."\n#$locale_tag Wikipedia Dump Data\n#";
    foreach($web_sites as $url) {
        $seed_info['seed_sites']['url'][] = $url;
    }
    $crawl_model->setSeedInfo($seed_info);
}
/**
 * Gets the next wiki page from a file handle pointing to the wiki dump file
 * @param resource $fr file handle (might be a  compressed file handle,
 *  for example, corresponding to gzopen of bzopen)
 * @param function $read a function for reading from thhe given file handle
 * @param int $block_size size of blocks to use when reading
 * @param string & $input_buffer used to buffer data from the wiki dump file
 */
function getNextPage($fr, $read, $block_size, &$input_buffer)
{
    while (!feof($fr) && strpos($input_buffer, "</page>") === false) {
        $input_text = $read($fr, $block_size);
        $len = strlen($input_text);
        if ($len == 0) {
            break;
        }
        $input_buffer .= $input_text;
    }
    $start_pos = strpos($input_buffer, "<page>") ?? 0;
    $end_pos = strpos($input_buffer, "</page>", $start_pos) + strlen("</page>");
    $len = $end_pos - $start_pos;
    $page = substr($input_buffer, $start_pos, $len);
    $input_buffer = substr($input_buffer, $end_pos);
    return $page;
}
/**
 * Remove all occurrence of a open close tag pairs from $text
 * @param string $text to remove tag pair from
 * @param string $open string pattern for open tag
 * @param string $close string pattern for close tag
 * @return string text after tag removed
 */
function removeTags($text, $open, $close)
{
    $old_text = "";
    while ($text != $old_text) {
        $old_text = $text;
        $tag_offset = getBraceTag($text, $open, $close, "");
        if ($tag_offset === false) {
            break;
        }
        if ($tag_offset !== false) {
            $text = str_replace($tag_offset[0], " ", $text);
        }
    }
    return $text;
}
/**
 * Get a substring offset pair matching the input open close brace tag pattern
 *
 * @param string $page source text to search for the tag in
 *   For example, lala {{infobox {{blah yoyoy}} }} dada.
 * @param string $brace_open character sequence starting the tag region. For
 *  example {{
 * @param string $brace_close character sequence ending the tag region. For
 *  example }}
 * @param string $tag tag that might be associated with the opening of the
 *  the sequence. For example infobox.
 * @param int $offset offset to start searching from
 * @return array ordered pair [substring containing the brace tag, offset after
 *  the tag]. If had  "lala {{infobox {{blah yoyoy}} }} dada" as input and
 *  searched on {{, }}, infobox, 0 would get ["{{infobox {{blah yoyoy}}", 31]
 */
function getBraceTag($page, $brace_open, $brace_close, $tag, $offset = 0)
{
    $start_pos = strpos($page, $brace_open . $tag, $offset);
    if ($start_pos === false) {
        return false;
    }
    $brace_stack = ['b'];
    $current_pos = $start_pos + strlen($brace_open . $tag);
    $open_len = strlen($brace_open);
    $close_len = strlen($brace_close);
    while (!empty($brace_stack)) {
        $next_open = strpos($page, $brace_open, $current_pos);
        $next_close = strpos($page, $brace_close, $current_pos);
        if ($next_close === false) {
            return false;
        }
        if ($next_open === false) {
            $next_open = $next_close;
        }
        if ($next_open < $next_close) {
            array_push($brace_stack, 'b');
            $tag_len = $open_len;
        } else {
            array_pop($brace_stack);
            $tag_len = $close_len;
        }
        $current_pos = min($next_open, $next_close) + $tag_len;
    }
    $end_pos = $current_pos;
    $len = $end_pos - $start_pos;
    $outer_contents = substr($page, $start_pos, $len);
    return [$outer_contents, $end_pos];
}
/**
 * Get the outer contents of an xml open/close tag pair from
 * a text source together with a new offset location after
 * @param string $page text source to search the tag pair in
 * @param string $tag the xml tag to look for
 * @param int $offset offset to start searching after for the open/close pair
 * @param array ordered pair [outer contents, new offset]
 */
function getTagOffsetPage($page, $tag, $offset = 0)
{
    $start_pos = strpos($page, "<$tag", $offset);
    if ($start_pos === false) {
        return false;
    }
    $end_pos = strpos($page, "</$tag>", $start_pos);
    if ($end_pos === false) {
        return false;
    }
    $end_pos += strlen("</$tag>");
    $len = $end_pos - $start_pos;
    $outer_contents = substr($page, $start_pos, $len);
    return [$outer_contents, $end_pos];
}
/**
 * Returns title and page counts of the top $max_pages many entries
 * in a $page_count_file for a locale $locale_tag
 *
 * @param string $page_count_file page count file to use to search for title
 *  counts with respect to a locale
 * @param string $locale_tag locale to get top pages for
 * @param int $max_pages number of pages
 * @param array $title_counts title counts that migt have come from analyzing
 *  a previous file. These will be in the output and contribute to $max_pages
 * @return array $title_counts wiki page titles => num_views associative array
 */
function getTopPages($page_count_file, $locale_tag, $max_pages,
    $title_counts = [])
{
    $hash_file_name = C\PREP_DIR . "/" . L\crawlHash($page_count_file .
        $locale_tag . $max_pages) . ".txt";
    if (file_exists($hash_file_name)) {
        echo "...Using previously computed cache file of wiki title counts\n";
        return unserialize(file_get_contents($hash_file_name));
    }
    $block_size = 8192;
    $output_message_threshold = $block_size * $block_size;
    list($fr, $read, $close) = smartOpen($page_count_file);
    $locale_tag = explode("-", $locale_tag)[0];
    $pattern = '/^' . $locale_tag . "(\.[a-z])?([\s|_][^\p{P}]+)+\s\d*/u";
    $bytes = 0;
    $bytes_since_last_output = 0;
    $input_buffer = "";
    $time = time();
    while (!feof($fr)) {
        $input_text = $read($fr, $block_size);
        $len = strlen($input_text);
        if ($len == 0) {
            break;
        }
        $bytes += $len;
        $bytes_since_last_output += $len;
        if ($bytes_since_last_output > $output_message_threshold) {
            echo "Have now read " . $bytes . " many bytes." .
                " Peak memory so far: " . memory_get_peak_usage().
                ".\n     Number of titles so far: ".count($title_counts).
                ". Elapsed time so far: ".(time() - $time)."s\n";
            $bytes_since_last_output = 0;
        }
        $input_buffer .= mb_strtolower($input_text);
        $lines = explode("\n", $input_buffer);
        $input_buffer = array_pop($lines);
        foreach ($lines as $line) {
            preg_match($pattern, $line, $matches);
            if (count($matches) > 0) {
                $line_parts = explode(" ", $matches[0]);
                if (isset($line_parts[1]) &&
                    isset($line_parts[2])) {
                    $title = $line_parts[1];
                    if (substr_count($title, "_") > 2) {
                        continue;
                    }
                    $title_counts[$title] = intval($line_parts[2]);
                }
            }
            $buffer_size = max(4 * $max_pages, 40000);
            if (count($title_counts) > $buffer_size) {
                echo  "..pruning results to $max_pages terms.\n";
                arsort($title_counts);
                $title_counts = array_slice($title_counts, 0, $max_pages);
            }
        }
    }
    arsort($title_counts);
    $title_counts = array_slice($title_counts, 0, $max_pages);
    file_put_contents($hash_file_name, serialize($title_counts));
    return $title_counts;
}
/**
 * Gets a read file handle for $file_open appropriate for whether it is
 * uncompressed, bz2 compressed, or gz compressed. It returns also
 * function pointers to the functions needed to do reading and closing for
 * the file handle.
 *
 * @param string $file_name name of file want read file handle for
 * @return array [file_handle, read_function_ptr, close_function_ptr]
 */
function smartOpen($file_name)
{
    if (strpos($file_name, "bz2") !== false) {
        $fr = bzopen($file_name, 'r') or
            die ("Can't open compressed file");
        $read = "bzread";
        $close = "bzclose";
    } else if (strpos($file_name, "gz") !== false) {
        $fr = gzopen($file_name, 'r') or
            die ("Can't open compressed file");
        $read = "gzread";
        $close = "gzclose";
    } else {
        $fr = fopen($file_name, 'r') or die("Can't open file");
        $read = "fread";
        $close = "fclose";
    }
    return [$fr, $read, $close];
}
/**
 * Translates Yioop web app strings to a given locale ($locale_tag) and writes
 * the LOCALE_DIR/$locale_tag/configure.ini file for these translations.
 * Currently, translations are done using the Yandex.translate
 * (https://translate.yandex.com/) API.
 *
 * @param string $locale_tag of locale to translate
 * @param int $with_wiki_pages if this is <=0, public and help wiki pages
 *  are not translated, if it is 1, they are translated to the locale
 *  if the locale does not already have a translation. If it is >1 then
 *  it is force translated to locale.
 */
function translateLocale($locale_tag, $with_wiki_pages = 0)
{
    global $public_pages;
    global $help_pages;
    if (!C\nsdefined('TRANSLATE_API_KEY')) {
        echo "You need to get a Yandex translate API key to use this command";
        return;
    }
    $_SERVER["NO_LOGGING"] = true;
    $controller = new JobsController();
    $translate_base_url =
        "https://translate.yandex.net/api/v1.5/tr.json/translate?key=";
    $locale_model = new M\LocaleModel();
    $group_model = new M\GroupModel();
    $translate_from_data = $locale_model->getStringData("en-US");
    if ($locale_tag == 'all') {
        $locales_data = $locale_model->getLocaleList(false);
        $locale_tags = [];
        foreach($locales_data as $locale_data) {
            $locale_tags[] = $locale_data['LOCALE_TAG'];
        }
    } else {
        $locale_tags = [$locale_tag];
    }
    echo "Translations ".
        "Powered by Yandex.translate (https://translate.yandex.com/)\n";
    foreach ($locale_tags as $locale_tag) {
        if ($locale_tag == 'en-US') {
            echo "English (en-US) is the base language and ".
                "cannot be translated to\n";
            continue;
        }
        $locale_name = $locale_model->getLocaleName($locale_tag);
        if (empty($locale_name)) {
            echo "$locale_tag does not exist skipping translation...\n";
            continue;
        }
        $major_locale = explode("-", $locale_tag)[0];
        $pre_url = $translate_base_url . C\TRANSLATE_API_KEY . "&lang=" .
            "en-$major_locale&text=";
        echo "Translating strings from en-US to $locale_tag \n";
        $translate_to_data = $locale_model->getStringData($locale_tag);
        $unsaved_count = 0;
        $total_count = 0;
        foreach ($translate_to_data as $string_id => $text) {
            $total_count++;
            if (empty($text) && !empty($translate_from_data[$string_id])) {
                echo "$total_count $string_id " .
                    "{$translate_from_data[$string_id]}\n";
                $translate_text = $translate_from_data[$string_id];
                echo "Translating string_id: " .
                    "$string_id text: $translate_text\n";
                $translated_text = translatePhrase($translate_text,
                    $locale_tag);
                if ($translated_text !== false) {
                    echo "...success! translation: $translated_text\n";
                    $translate_to_data[$string_id] = $translated_text;
                    $unsaved_count++;
                    echo "...Current unsaved count: $unsaved_count\n";
                    if ($unsaved_count >= 10) {
                        echo "Saving progress...\n";
                        $locale_model->updateStringData($locale_tag,
                            $translate_to_data);
                        $unsaved_count = 0;
                    }
                }
                sleep(5);
            }
        }
        if ($unsaved_count > 0) {
            echo "Saving last group of strings\n";
            $locale_model->updateStringData($locale_tag, $translate_to_data);
        }
        if ($with_wiki_pages <= 0) {
            continue;
        }
        $wiki_groups_pages = ["PUBLIC" => $public_pages, "HELP" => $help_pages];
        foreach ($wiki_groups_pages as $group => $wiki_groups_pages) {
            $group_id = ($group == "PUBLIC") ?
                C\PUBLIC_GROUP_ID : C\HELP_GROUP_ID;
            echo "Translating the $group wiki group!\n";
            $groups_english_pages = $wiki_groups_pages['en-US'];
            foreach ($groups_english_pages as $page_name => $group_page) {
                if (!empty($wiki_groups_pages[$locale_tag][$page_name]) &&
                    $with_wiki_pages == 1) {
                    echo "Have already in exported pages translated to: ".
                        "$locale_tag $page_name\n";
                    continue;
                }
                if ($with_wiki_pages == 1 && ($info =
                    $group_model->getPageInfoByName($group_id, $page_name,
                    $locale_tag, "edit")) != false) {
                    echo "Have already in database translated to : ".
                        "$locale_tag $page_name\n";
                    continue;
                }
                //\r\n line endings
                $group_page = preg_replace('/\r/u', '', $group_page);
                $parsed_page = $controller->parsePageHeadVars($group_page,
                    true);
                if (empty($parsed_page[0])) {
                    $parsed_page[0] = [];
                }
                if (empty($parsed_page[1]) ||
                    (!empty($parsed_page[0]["page_type"]) &&
                    $parsed_page[0]["page_type"] != "standard")) {
                    echo "Did not translate wiki page: $page_name -- ".
                        "page needs to be non-empty and of standard page ".
                        "type\n";
                    continue;
                }
                $human_page_name = preg_replace("/\_/u", " ", $page_name);
                echo "Translating wiki page named: " . $human_page_name . "\n";
                $alias_header = $parsed_page[0];
                $alias_header["page_type"] = "page_alias";
                $alias_header["title"] = $human_page_name;
                $translated_human_page_name = translatePhrase($human_page_name,
                    $locale_tag);
                if (!$translated_human_page_name) {
                    echo "Translating failed!\n";
                    continue;
                }
                echo "Translated page will be named: " .
                    $translated_human_page_name . "\n";
                $translated_page_name =
                    preg_replace("/\s/u", "_", $translated_human_page_name);
                if ($translated_page_name == $page_name) {
                    $translated_page_name .= "-" . $locale_name;
                    $translated_page_name =
                        preg_replace("/\s/u", "_", $translated_page_name);
                    echo "Translated wiki page name will be: " .
                        $translated_page_name;
                }
                $alias_header["page_alias"] = $translated_page_name;
                $translated_header = $parsed_page[0];
                $translated_header['title'] = $translated_page_name;
                if (!empty($translated_header['description'])) {
                    echo "Translating page description: " .
                        $translated_header['description'] . "\n";
                    $translated_header['description'] = translatePhrase(
                        $translated_header['description'], $locale_tag);
                    echo "Translated page description: " .
                        $translated_header['description'] . "\n";
                }
                $page_parts = explode("\n\n", $parsed_page[1]);
                $translated_page_data = "";
                $connective = "";
                echo "Page has " . count($page_parts) . " paragraphs\n";
                $i = 0;
                foreach ($page_parts as $page_part) {
                    $i++;
                    echo "Translating paragraph $i\n";
                    $translated_page_data .= $connective;
                    if (preg_match("/\<nowiki|\(\(resource/",
                        $page_part)) {
                        $out_data = $page_part;
                    } else {
                        $out_data = translatePhrase($page_part,
                            $locale_tag);
                    }
                    $translated_page_data .= $out_data;
                    $connective = "\n\n";
                    sleep(2);
                }
                echo "Translated page data:\n" .
                    $translated_page_data . "\n";
                $page_name = preg_replace("/ /u", "_", $page_name);
                $translated_page_name =  urlencode(preg_replace("/ /u", "_",
                    $translated_page_name));
                $alias_page = wikiHeaderPageToString($alias_header, "");
                $translated_page = wikiHeaderPageToString($translated_header,
                    $translated_page_data);
                $group_model->setPageName(C\ROOT_ID, $group_id, $page_name,
                    $alias_page, $locale_tag, "create",
                    L\tl('social_component_page_created', $page_name),
                    L\tl('social_component_page_discuss_here'));
                $group_model->setPageName(C\ROOT_ID, $group_id,
                    $translated_page_name, $translated_page, $locale_tag,
                    "create", L\tl('social_component_page_created',
                    $translated_page_name),
                    L\tl('social_component_page_discuss_here'));
                sleep(5);
            }
        }
    }
}
/**
 * Converts an array of wiki header information and a wiki page contents
 * string into a string suitable to be store into the GROUP_PAGE_HISTORY
 * database table.
 *
 * @param array $wiki_header of wiki header information
 * @param string $wiki_page_data mediawiki data
 * @return string suitable to be stored in GROUP_PAGE_HISTORY
 */
function wikiHeaderPageToString($wiki_header, $wiki_page_data)
{
    $page_defaults = [
        'page_type' => 'standard',
        'page_alias' => '',
        'page_border' => 'solid',
        'toc' => true,
        'title' => '',
        'author' => '',
        'robots' => '',
        'description' => '',
        'alternative_path' => '',
        'page_header' => '',
        'page_footer' => '',
        'sort' => 'aname'
    ];
    $head_string = "";
    foreach ($page_defaults as $key => $default) {
        $value = (empty($wiki_header[$key])) ? $default : $wiki_header[$key];
        $head_string .= urlencode($key) . "=" .
            urlencode($value) . "\n\n";
    }
    if (is_array($wiki_page_data)) { //template case
        $wiki_page_data = base64_encode(serialize($wiki_page_data));
    }
    if (!empty($wiki_page_data) || (!empty($wiki_header['page_type']) &&
        $wiki_header['page_type'] != 'standard')) {
        $page = $head_string . "END_HEAD_VARS" . $wiki_page_data;
    }
    return $page;
}
/**
 * Translates a string from English to a given locale using an online
 * translation tool.
 * @param string $translate_text text to be translated
 * @param string $locale_tag locale to translate to
 * @return mixed translated string on success, false otherwise
 */
function translatePhrase($translate_text, $locale_tag)
{
    static $translate_base_url =
        "https://translate.yandex.net/api/v1.5/tr.json/translate?key=";
    static $current_locale_tag = "";
    static $pre_url = "";
    static $controller;
    if ($current_locale_tag != $locale_tag) {
        $major_locale = explode("-", $locale_tag)[0];
        $pre_url = $translate_base_url . C\TRANSLATE_API_KEY . "&lang=" .
            "en-$major_locale&text=";
    }
    if (empty($controller)) {
        $controller = new JobsController();
    }
    $tmp = urlencode($translate_text);
    $tmp = preg_replace("/\%26\%23039\%3B/u", "'", $tmp);
    $tmp = preg_replace_callback("/\%26((:?\w|\%23|\d)+)\%3B/u",
        function($matches) {
            $out = "[{";
            $entity = $matches[1] ?? "";
            for($i = 0; $i < strlen($entity); $i++) {
                $out .= "(" . ord($entity[$i]) . ")";
            }
            $out .= "}]";
            return $out;
        }, $tmp);
    $tmp = preg_replace("/\%25s/u", "[{115}]", $tmp);
    $tmp = preg_replace("/\+/u", " ", $tmp);
    $tmp = preg_replace("/\%0A/u", "\n", $tmp);
    $tmp = preg_replace("/\%([\d|A-F])([\d|A-F])/u", "([$1][$2])", $tmp);
    $tmp = preg_replace("/\[A\]/u", "[10]", $tmp);
    $tmp = preg_replace("/\[B\]/u", "[11]", $tmp);
    $tmp = preg_replace("/\[C\]/u", "[12]", $tmp);
    $tmp = preg_replace("/\[D\]/u", "[13]", $tmp);
    $tmp = preg_replace("/\[E\]/u", "[14]", $tmp);
    $tmp = preg_replace("/\[F\]/u", "[15]", $tmp);
    $url = $pre_url . urlencode($tmp);
    $response =  json_decode(L\FetchUrl::getPage($url), true);
    if (!empty($response["text"][0]) && !empty($response["code"]) &&
        $response["code"] == 200) {
        $tmp = $response["text"][0];
        $tmp = preg_replace("/\[15个?\]/u", "[F]", $tmp);
        $tmp = preg_replace("/\[14个?\]/u", "[E]", $tmp);
        $tmp = preg_replace("/\[13个?\]/u", "[D]", $tmp);
        $tmp = preg_replace("/\[12个?\]/u", "[C]", $tmp);
        $tmp = preg_replace("/\[11个?\]/u", "[B]", $tmp);
        $tmp = preg_replace("/\[10个?\]/u", "[A]", $tmp);
        $tmp = preg_replace("/\(\s*\[\s*([\d|A-F])\s*\]\s*".
            "\[\s*([\d|A-F])\s*\]\s*\)/u", "%$1$2", $tmp);
        $tmp = preg_replace("/\n/u", "%0A", $tmp);
        $tmp = preg_replace("/ /u", "+",  $tmp);
        $tmp = preg_replace("/\[\s*\{\s*115\s*\}\s*\]/u", "%25s", $tmp);
        $tmp = preg_replace_callback("/\[\{((:?\(\d+\))+)\}\]/u",
        function($matches) {
            $out = "";
            $encoded_entity = $matches[1] ?? "";
            if (preg_match_all("/\((\d+)\)/", $encoded_entity, $matches) > 0) {
                $char_codes = $matches[1] ?? [];
                foreach ($char_codes as $char_code) {
                    if (intval($char_code) < 256) {
                        $out .= chr(intval($char_code));
                    }
                }
            }
            if (strlen($out) > 0) {
                $out = "&$out;";
            }
            return $out;
        }, $tmp);
        $tmp = preg_replace('/\'/', "%26%23039%3B", $tmp);
        $translated_text = urldecode($tmp);
        return $translated_text;
    }
    return false;
}
/**
 * Makes an n or all word gram Bloom filter based on the supplied arguments
 * Wikipedia files are assumed to have been place in the PREP_DIR before this
 * is run and writes it into the resources folder of the given locale
 *
 * @param array $args command line arguments with first two elements of $argv
 *     removed. For details on which arguments do what see the $usage variable
 */
function makeNWordGramsFiles($args)
{
    if (!isset($args[1])) {
        $args[1] = "en";
        $args[2] = "en_US";
    }
    if (!isset($args[2])) {
        $args[2] = $args[1];
    }
    if (!isset($args[3])) {
        $args[3] = "all"; // 2 or more (all-grams)
    }
    if (!isset($argv[4])) {
        $args[4] = NWordGrams::PAGE_COUNT_WIKIPEDIA;
    }
    if (!isset($args[5]) && $args[3] == "all" &&
        $args[4] == NWordGrams::PAGE_COUNT_WIKIPEDIA) {
        $args[5] = 75000;
    } else {
        $args[5] = -1;
    }
    $wiki_file_path = PREP_DIR . "/";
    if (!file_exists($wiki_file_path . $args[0])) {
        echo $args[0] . " does not exist in $wiki_file_path";
        exit();
    }
    /*
     *This call creates a ngrams text file from input xml file and
     *returns the count of ngrams in the text file.
     */
    list($num_ngrams, $max_gram_len) =
        NWordGrams::makeNWordGramsTextFile($args[0], $args[1], $args[2],
        $args[3], $args[4], $args[5]);
    /*
     *This call creates a bloom filter file from n word grams text file based
     *on the language specified. The lang passed as parameter is prefixed
     *to the filter file name. The count of n word grams in text file is passed
     *as a parameter to set the limit of n word grams in the filter file.
     */
    NWordGrams::makeNWordGramsFilterFile($args[2], $args[3], $num_ngrams,
        $max_gram_len);
}

/**
 * Makes a trie that can be used to make word suggestions as someone enters
 * terms into the Yioop! search box. Outputs the result into the file
 * suggest_trie.txt.gz in the supplied locale dir
 *
 * @param string $dict_file where the word list is stored, one word per line
 * @param string $locale which locale to write the suggest file to
 * @param string $end_marker used to indicate end of word in the trie
 */
function makeSuggestTrie($dict_file, $locale, $end_marker)
{
    $locale = str_replace("-", "_", $locale);
    $out_file = LOCALE_DIR . "/$locale/resources/suggest_trie.txt.gz";

    // Read and load dictionary and stop word files
    $words = fileWithTrim($dict_file);
    sort($words);
    $trie = new L\Trie($end_marker);

    /** Ignore the words in the following cases. If the word
     *  - contains punctuation
     *  - is less than 3 characters
     *  - is a stop word
     */
    foreach ($words as $word) {
        if (mb_ereg_match("\p{P}", $word) == 0 && mb_strlen($word) > 2) {
            $trie->add($word);
        }
    }
    $output = [];
    $output["trie_array"] = $trie->trie_array;
    $output["end_marker"] = $trie->end_marker;
    file_put_contents($out_file, gzencode(json_encode($output), 9));
}

/**
 * Reads file into an array or outputs file not found. For each entry in
 * array trims it. Any blank lines are deleted
 *
 * @param $file_name file to read into array
 * @return array of trimmed lines
 */
function fileWithTrim($file_name)
{
    if (!file_exists($file_name)) {
        $file_name = PREP_DIR . "/$file_name";
        if (!file_exists($file_name)) {
            echo "$file_name Not Found\n\n";
            return [];
        }
    }
    $file_string = file_get_contents($file_name);
    $pre_lines = mb_split("\n", $file_string);
    $lines = [];
    foreach ($pre_lines as $pre_line) {
        $line = preg_replace( "/(^\s+)|(\s+$)/us", "", $pre_line );
        if ($line != "") {
            array_push($lines, $line);
        }
    }
    return $lines;
}
