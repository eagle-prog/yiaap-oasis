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
 * @author Charles Bocage charles.bocage@sjsu.edu
 *  (changed to web scrapers by Chris Pollett)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\controllers\AdminController;
use seekquarry\yioop\library as L;
use seekquarry\yioop\models as M;

if (isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0){
    echo "BAD REQUEST";
    exit();
}
/**
 * This script inserts 239 Web Scraper into the database so that one can
 * test the crawl speed of Yioop in a scenario that there are a moderate
 * number of Web Scrapers.
 */
/**
 * Calculate base directory of script
 * @ignore
 */
define("seekquarry\\yioop\\configs\\PARENT_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/tests")));
define("seekquarry\\yioop\\configs\\BASE_DIR", C\PARENT_DIR . "/src");
require_once C\BASE_DIR . '/configs/Config.php';
$scraper_model = new M\ScraperModel();
//Add lots of Web Scrapers
$web_scrapers = getScraperEntries();
$num_scrapers = count($web_scrapers);
$controller = new AdminController();
$i = 0;
foreach ($web_scrapers as $web_scraper) {
    $name = $web_scraper[0];
    $signature = $web_scraper[1];
    $scraper_model->add($controller->clean($name, "string"),
        $controller->clean($signature, "string"), 1, "", "", "");
    $i++;
    echo "Adding scraper $i of $num_scrapers.\n";
}
/**
 * This function has an array of Web Scrapers.
 *
 * @param array CMS detector values.
 */
function getScraperEntries()
{
    return [
        ["1C-Bitrix","(?:<link[^>]+components/bitrix|(?:src|href)=\"/bitrix" .
            "/(?:js|templates))"],
        ["3DM","<title>3ware 3DM([\\d\\.]+)?\\;version:\\1"],
        ["apps['ATG Web Commerce']","<[^>]+_DARGS"],
        ["AdRiver","(?:<embed[^>]+(?:src=\"https?://mh\\d?\\.adriver\\.ru/|" .
            "flashvars=\"[^\"]*(?:http:%3A//(?:ad|mh\\d?)\\.adriver\\.ru/|a" .
            "driver_banner))|<(?:(?:iframe|img)[^>]+src|a[^>]+href)=\"https" .
            "?://ad\\.adriver\\.ru/)"],
        ["apps['Adobe CQ5']","<div class=\"[^\"]*parbase"],
        ["apps['Adobe ColdFusion']","<!-- START headerTags\\.cfm"],
        ["apps['Advanced Web Stats']","aws\\.src = [^<]+caphyon-analytics"],
        ["Adzerk","<iframe [^>]*src=\"[^\"]+adzerk\\.net"],
        ["apps['Apache HBase']","<style[^>]+static/hbase"],
        ["apps['Apache Hadoop']","<style[^>]+static/hadoop"],
        ["apps['Apache JSPWiki']","<html[^>]* xmlns:jspwiki="],
        ["AppNexus","<(?:iframe|img)[^>]+adnxs\\.(?:net|com)"],
        ["Arastta","Powered By <a href=\"[^>]+Arastta"],
        ["apps['Arc Forum']","ping\\.src = node\\.href;\\s+[^>]+\\s+}\\s+</" .
            "script>"],
        ["Artifactory","<span class=\"version\">Artifactory(?: Pro)?(?: Pow" .
            "er Pack)?(?: ([\\d.]+))?\\;version:\\1"],
        ["apps['Atlassian Confluence']","Powered by <a href=[^>]+atlassian\\" .
            ".com/software/confluence(?:[^>]+>Atlassian Confluence</a> ([\\d" .
            ".]+))?\\;version:\\1"],
        ["apps['Atlassian FishEye']","<title>(?:Log in to )?FishEye (?:and " .
            "Crucible )?([\\d.]+)?</title>\\;version:\\1"],
        ["apps['Atlassian Jira']","Powered by\\s+<a href=[^>]+atlassian\\.c" .
            "om/(?:software/jira|jira-bug-tracking/)[^>]+>Atlassian\\s+JIRA" .
            "(?:[^v]*v(?:ersion: )?(\\d+\\.\\d+(\\.\\d+)?))?\\;version:\\1"],
        ["Avangate","<link[^>]* href=\"^https?://edge\\.avangate\\.net/"],
        ["BIGACE","(?:Powered by <a href=\"[^>]+BIGACE|<!--\\s+Site is runn" .
            "ing BIGACE)"],
        ["Banshee","Built upon the <a href=\"[^>]+banshee-php\\.org/\">[a-z" .
            "]+</a>(?:v([\\d.]+))?\\;version:\\1"],
        ["BigDump","<!-- <h1>BigDump: Staggered MySQL Dump Importer ver\\. " .
            "([\\d.b]+)\\;version:\\1"],
        ["Bigcommerce","<link href=[^>]+cdn\\d+\\.bigcommerce\\.com/v"],
        ["Bigware","(?:Diese <a href=[^>]+bigware\\.de|<a href=[^>]+/main_b" .
            "igware_\\d+\\.php)"],
        ["tv']","<(?:param|embed|iframe)[^>]+blip\\.tv/play"],
        ["Bonfire","Powered by <a[^>]+href=\"https?://(?:www\\.)?cibonfire\\" .
            ".com[^>]*>Bonfire v([^<]+)\\;version:\\1"],
        ["Bugzilla","href=\"enter_bug\\.cgi\">"],
        ["apps['Burning Board']","<a href=\"[^>]+woltlab\\.com[^<]+<strong>" .
            "Burning Board"],
        ["apps['Business Catalyst']","<!-- BC_OBNW -->"],
        ["BuySellAds","<script[^>]*>[^<]+?bsa.src\\s*=\\s*['\"](?:https?:)?" .
            "\\/{2}\\w\\d\\.buysellads\\.com\\/[\\w\\d\\/]+?bsa\\.js['\"]"],
        ["CO2Stats","src=[^>]+co2stats\\.com/propres\\.php"],
        ["apps['CS Cart']","&nbsp;Powered by (?:<a href=[^>]+cs-cart\\.com|" .
            "CS-Cart)"],
        ["apps['Carbon Ads']","<[a-z]+ [^>]*id=\"carbonads-container\""],
        ["Cargo","<link [^>]+Cargo feed"],
        ["Chamilo","\">Chamilo ([\\d.]+)</a>\\;version:\\1"],
        ["CodeIgniter","<input[^>]+name=\"ci_csrf_token\"\\;version:2+"],
        ["Contao","<!--[^>]+powered by (?:TYPOlight|Contao)[^>]*-->"],
        ["Coppermine","<!--Coppermine Photo Gallery ([\\d.]+)\\;version:\\1"],
        ["CubeCart","(?:Powered by <a href=[^>]+cubecart\\.com|<p[^>]+>Powe" .
            "red by CubeCart)"],
        ["apps['DM Polopoly']","<(?:link [^>]*href|img [^>]*src)=\"/polopol" .
            "y_fs/"],
        ["DNN","<!-- by DotNetNuke Corporation"],
        ["DTG","<a[^>]+Site Powered by DTG"],
        ["Demandware","<[^>]+demandware\\.edgesuite"],
        ["DirectAdmin","<a[^>]+>DirectAdmin</a> Web Control Panel"],
        ["Disqus","<div[^>]+id=\"disqus_thread\""],
        ["Django","(?:powered by <a[^>]+>Django ?([\\d.]+)?|<input[^>]*name" .
            "=[\"']csrfmiddlewaretoken[\"'][^>]*>)\\;version:\\1"],
        ["Dokeos","(?:Portal <a[^>]+>Dokeos|@import \"[^\"]+dokeos_blue)"],
        ["Doxygen","(?:<!-- Generated by Doxygen ([\\d.]+)|<link[^>]+doxyge" .
            "n\\.css)\\;version:\\1"],
        ["DreamWeaver","(?:<!--[^>]*(?:InstanceBeginEditable|Dreamweaver([^" .
            ">]+)target|DWLayoutDefaultTable)|function MM_preloadImages\\(\\" .
            ") \\{)\\;version:\\1"],
        ["Drupal","<(?:link|style)[^>]+sites/(?:default|all)/(?:themes|modu" .
            "les)/"],
        ["apps['Drupal Commerce']","<[^>]+(?:id=\"block[_-]commerce[_-]cart" .
            "[_-]cart|class=\"commerce[_-]product[_-]field)"],
        ["ELOG","<title>ELOG Logbook Selection</title>"],
        ["Epoch","<link[^>]+?href=\"[^\"]+epoch(?:\\.min)?\\.css"],
        ["apps['FAST ESP']","<form[^>]+id=\"fastsearch\""],
        ["apps['FAST Search for SharePoint']","<input[^>]+ name=\"Parametri" .
            "cSearch"],
        ["FWP","<!--\\s+FwP Systems"],
        ["apps['Fact Finder']","<!-- Factfinder"],
        ["FlexCMP","<!--[^>]+FlexCMP[^>v]+v\\. ([\\d.]+)\\;version:\\1"],
        ["FluxBB","Powered by (?:<strong>)?<a href=\"[^>]+fluxbb"],
        ["Flyspray","(?:<a[^>]+>Powered by Flyspray|<map id=\"projectsearch" .
            "form)"],
        ["apps['Font Awesome']","<link[^>]* href=[^>]+font-awesome(?:\\.min" .
            ")?\\.css"],
        ["Fortune3","(?:<link [^>]*href=\"[^\\/]*\\/\\/www\\.fortune3\\.com" .
            "\\/[^\"]*siterate\\/rate\\.css|Powered by <a [^>]*href=\"[^\"]" .
            "+fortune3\\.com)"],
        ["FrontPage","<html[^>]+urn:schemas-microsoft-com:office:office"],
        ["apps['GX WebManager']","<!--\\s+Powered by GX"],
        ["Gallery","<div id=\"gsNavBar\" class=\"gcBorder1\">"],
        ["Gambio","(?:<link[^>]* href=\"templates/gambio/|<a[^>]content\\.p" .
            "hp\\?coID=\\d|<!-- gambio eof -->|<!--[\\s=]+Shopsoftware by G" .
            "ambio GmbH \\(c\\))"],
        ["Glyphicons","(?:<link[^>]* href=[^>]+glyphicons(?:\\.min)?\\.css|" .
            "<img[^>]* src=[^>]+glyphicons)"],
        ["apps['Google Font API']","<link[^>]* href=[^>]+fonts\\.(?:googlea" .
            "pis|google)\\.com"],
        ["apps['Google Tag Manager']","googletagmanager\\.com/ns\\.html[^>]" .
            "+></iframe>"],
        ["Gravatar","<[^>]+gravatar\\.com/avatar/"],
        ["apps['Green Valley CMS']","<img[^>]+/dsresource\\?objectid="],
        ["Handlebars","<[^>]*type=[^>]text\\/x-handlebars-template"],
        ["HeadJS","<[^>]*data-headjs-load"],
        ["Highcharts","<svg[^>]*><desc>Created with Highcharts ([\\d.]*)\\;" .
            "version:\\1"],
        ["Highstock","<svg[^>]*><desc>Created with Highstock ([\\d.]*)\\;ve" .
            "rsion:\\1"],
        ["Hippo"," <[^>]+/binaries/(?:[^/]+/)*content/gallery/"],
        ["HubSpot","<!-- Start of Async HubSpot"],
        ["Hybris","<[^>]+(?:/sys_master/|/hybr/|/_ui/desktop/)"],
        ["IPB","<link[^>]+ipb_[^>]+\\.css"],
        ["InProces","<!-- CSS InProces Portaal default -->"],
        ["Indexhibit","<(?:link|a href) [^>]+ndxz-studio"],
        ["Indico","Powered by\\s+(?:CERN )?<a href=\"http://(?:cdsware\\.ce" .
            "rn\\.ch/indico/|indico-software\\.org|cern\\.ch/indico)\">(?:C" .
            "DS )?Indico( [\\d\\.]+)?\\;version:\\1"],
        ["Invenio","(?:Powered by|System)\\s+(?:CERN )?<a (?:class=\"footer" .
            "\" )?href=\"http://(?:cdsware\\.cern\\.ch(?:/invenio)?|invenio" .
            "-software\\.org|cern\\.ch/invenio)(?:/)?\">(?:CDS )?Invenio</a" .
            ">\\s*v?([\\d\\.]+)?\\;version:\\1"],
        ["Ionicons","<link[^>]* href=[^>]+ionicons(?:\\.min)?\\.css"],
        ["apps['JTL Shop']","(?:<input[^>]+name=\"JTLSHOP|<a href=\"jtl\\.p" .
            "hp)"],
        ["Joomla","(?:<div[^>]+id=\"wrapper_r\"|<[^>]+(?:feed|components)/c" .
            "om_|<table[^>]+class=\"pill)\\;confidence:50"],
        ["K2","<!--(?: JoomlaWorks \"K2\"| Start K2)"],
        ["apps['Kendo UI']","<link[^>]*\\s+href=[^>]*styles/kendo\\.common(" .
            "?:\\.min)?\\.css[^>]*/>"],
        ["apps['Koala Framework']","<!--[^>]+This website is powered by Koa" .
            "la Web Framework CMS"],
        ["Koken","<html lang=\"en\" class=\"k-source-essays k-lens-essays\">"],
        ["Koobi","<!--[^K>-]+Koobi ([a-z\\d.]+)\\;version:\\1"],
        ["Less","<link[^>]+ rel=\"stylesheet/less\""],
        ["apps['LightMon Engine']","<!-- Lightmon Engine Copyright Lightmon"],
        ["Lightbox","<link [^>]*href=\"[^\"]+lightbox(?:\\.min)?\\.css"],
        ["Lithium"," <a [^>]+Powered by Lithium"],
        ["Livefyre","<[^>]+(?:id|class)=\"livefyre"],
        ["Locomotive","<link[^>]*/sites/[a-z\\d]{24}/theme/stylesheets"],
        ["MODx","<a[^>]+>Powered by MODx</a>"],
        ["MantisBT","<img[^>]+ alt=\"Powered by Mantis Bugtracker"],
        ["apps['Materialize CSS']","<link[^>]* href=\"[^\"]*materialize(?:\\" .
            ".min)?\\.css"],
        ["MediaWiki","(?:<a[^>]+>Powered by MediaWiki</a>|<[^>]+id=\"t-spec" .
            "ialpages)"],
        ["Meebo","(?:<iframe id=\"meebo-iframe\"|Meebo\\('domReady'\\))"],
        ["Meteor","<link[^>]+__meteor-css__"],
        ["Methode","<!-- Methode uuid: \"[a-f\\d]+\" ?-->"],
        ["NET']","<input[^>]+name=\"__VIEWSTATE"],
        ["MiniBB","<a href=\"[^\"]+minibb[^<]+</a>[^<]+<!--End of copyrigh" .
            "t link"],
        ["CMS']","(<script|link)[^>]*mg-(core|plugins|templates)"],
        ["Mollom","<img[^>]+\\.mollom\\.com"],
        ["Moodle","<img[^>]+moodlelogo"],
        ["MyBB","(?:<script [^>]+\\s+<!--\\s+lang\\.no_new_posts|<a[^>]* ti" .
            "tle=\"Powered By MyBB)"],
        ["NOIX","(?:<[^>]+(?:src|href)=[^>]*/media/noix|<!-- NOIX)"],
        ["NVD3","<link[^>]* href=[^>]+nv\\.d3(?:\\.min)?\\.css"],
        ["apps['OWL Carousel']","<link [^>]*href=\"[^\"]+owl.carousel(?:\\." .
            "min)?\\.css"],
        ["apps['OXID eShop']","<!--[^-]*OXID eShop"],
        ["Odoo","<link[^>]* href=[^>]+/web/css/(?:web\\.assets_common/|webs" .
            "ite\\.assets_frontend/)\\;confidence:25"],
        ["apps['Open Web Analytics']","<!-- (?:Start|End) Open Web Analytic" .
            "s Tracker -->"],
        ["OpenCart","(?:index\\.php\\?route=[a-z]+/|Powered By <a href=\"[^" .
            ">]+OpenCart)"],
        ["OpenCms","<link href=\"/opencms/"],
        ["apps['OpenText Web Solutions']","<!--[^>]+published by Open Text " .
            "Web Solutions"],
        ["apps['Outlook Web App']","<link\\s[^>]*href=\"[^\"]*?([\\d.]+)/th" .
            "emes/resources/owafont\\.css\\;version:\\1"],
        ["js']","<\\/div>\\s*<!-- outerContainer -->\\s*<div\\s*id=\"printC" .
            "ontainer\"><\\/div>"],
        ["PHP-Fusion","Powered by <a href=\"[^>]+php-fusion"],
        ["PHP-Nuke","<[^>]+Powered by PHP-Nuke"],
        ["PayPal","<input[^>]+_s-xclick"],
        ["Penguin","<link[^>]+?href=\"[^\"]+penguin(?:\\.min)?\\.css"],
        ["Percussion","<[^>]+class=\"perc-region\""],
        ["Pligg","<span[^>]+id=\"xvotes-0"],
        ["Plura","<iframe src=\"[^>]+pluraserver\\.com"],
        ["Polymer","(?:<polymer-[^>]+|<link[^>]+rel=\"import\"[^>]+/polymer" .
            "\\.html\")"],
        ["Posterous","<div class=\"posterous"],
        ["Powergap","<a[^>]+title=\"POWERGAP"],
        ["PrestaShop","Powered by <a\\s+[^>]+>PrestaShop"],
        ["apps['Project Wonderful']","<div[^>]+id=\"pw_adbox_"],
        ["apps['Pure CSS']","<link[^>]+(?:([\\d.])+/)?pure(?:-min)?\\.css\\;" .
            "version:\\1"],
        ["CMS","<a href=\"[^>]+opensolution\\.org/\">CMS by"],
        ["Cart","<a href=\"[^>]+opensolution\\.org/\">(?:Shopping cart by" .
            "|Sklep internetowy)"],
        ["RainLoop","<meta [^>]*(?:content=\"([^\"]+)[^>]+ id=\"rlAppVersio" .
            "n\"|id=\"rlAppVersion\"[^>]+ content=\"([^\"]+))\\;version:\\1" .
            "?\\1:\\2"],
        ["apps['RBS Change']","<html[^>]+xmlns:change="],
        ["RDoc","<link[^>]+href=\"[^\"]*rdoc-style\\.css"],
        ["Reddit","(?:<a[^>]+Powered by Reddit|powered by <a[^>]+>reddit<)"],
        ["Redmine","Powered by <a href=\"[^>]+Redmine"],
        ["RoundCube","<title>RoundCube"],
        ["apps['SDL Tridion']","<img[^>]+_tcm\\d{2,3}-\\d{6}\\."],
        ["apps['SOBI 2']","(?:<!-- start of Sigsiu Online Business Index|<d" .
            "iv[^>]* class=\"sobi2)"],
        ["apps['SQL Buddy']","(?:<title>SQL Buddy</title>|<[^>]+onclick=\"s" .
            "ideMainClick\\(\"home\\.php)"],
        ["Semantic-ui","(?:<div class=\"ui\\s[^>]+\">)\\;confidence:30"],
        ["apps['Sentinel License Monitor']","<title>Sentinel (?:Keys )?Lice" .
            "nse Monitor</title>"],
        ["Seoshop","<a[^>]+title=\"SEOshop"],
        ["ShinyStat","<img[^>]*\\s+src=['\"]?https?://www\\.shinystat\\.com" .
            "/cgi-bin/shinystat\\.cgi\\?[^'\"\\s>]*['\"\\s/>]"],
        ["Shopatron","<body class=\"shopatron"],
        ["Shopify","<link[^>]+=['\"]//cdn\\.shopify\\.com"],
        ["SilverStripe","Powered by <a href=\"[^>]+SilverStripe"],
        ["Sitecore","<img[^>]+src=\"[^>]*/~/media/[^>]+\\.ashx"],
        ["Sizmek","(?:<a [^>]*href=\"[^/]*//[^/]*serving-sys\\.com/|<img [^" .
            ">]*src=\"[^/]*//[^/]*serving-sys\\.com/)"],
        ["Slimbox","<link [^>]*href=\"[^/]*slimbox(?:-rtl)?\\.css"],
        ["apps['Slimbox 2']","<link [^>]*href=\"[^/]*slimbox2(?:-rtl)?\\.css"],
        ["apps['Smart Ad Server']","<img[^>]+smartadserver\\.com\\/call"],
        ["SmartSite","<[^>]+/smartsite\\.(?:dws|shtml)\\?id="],
        ["Solodev","<div class='dynamicDiv' id='dd\\.\\d\\.\\d'>"],
        ["Splunk","<p class=\"footer\">&copy; [-\\d]+ Splunk Inc\\.(?: Splu" .
            "nk ([\\d\\.]+( build [\\d\\.]*\\d)?))?[^<]*</p>\\;version:\\1"],
        ["Spree","(?:<link[^>]*/assets/store/all-[a-z\\d]{32}\\.css[^>]+>|<" .
            "script>\\s*Spree\\.(?:routes|translations|api_key))"],
        ["SquirrelMail","<small>SquirrelMail version ([.\\d]+)[^<]*<br \\;v" .
            "ersion:\\1"],
        ["apps['Squiz Matrix']","<!--\\s+Running (?:MySource|Squiz) Matrix"],
        ["apps['Store Systems']","Shopsystem von <a href=[^>]+store-systems" .
            "\\.de\"|\\.mws_boxTop"],
        ["Stripe","<input[^>]+data-stripe"],
        ["SweetAlert","<link[^>]+?href=\"[^\"]+sweet-alert(?:\\.min)?\\.css"],
        ["Swiftlet","Powered by <a href=\"[^>]+Swiftlet"],
        ["SyntaxHighlighter",
            "(<script|<link)[^>]*sh(Core|Brush|ThemeDefault)"],
        ["TWiki","<img [^>]*(?:title|alt)=\"This site is powered by the TWi" .
            "ki collaboration platform"],
        ["apps['TYPO3 CMS']","<(?:script[^>]+ src|link[^>]+ href)=[^>]+typo" .
            "3temp/"],
        ["apps['TYPO3 Neos']","<html[^>]+xmlns:typo3=\"[^\"]+Flow/Packages/" .
            "Neos/"],
        ["TeamCity","<span class=\"versionTag\"><span class=\"vWord\">Versi" .
            "on</span> ([\\d\\.]+)\\;version:\\1"],
        ["TiddlyWiki","<[^>]*type=[^>]text\\/vnd\\.tiddlywiki"],
        ["Titan","<script[^>]+>var titan"],
        ["Trac","<a id=\"tracpowered"],
        ["Tumblr","<iframe src=\"[^>]+tumblr\\.com"],
        ["apps['Twitter Bootstrap']","<style>/\\*!\\* Bootstrap v(\\d\\.\\d" .
            "\\.\\d)\\;version:\\1"],
        ["UltraCart","<form [^>]*action=\"[^\"]*\\/cgi-bin\\/UCEditor\\?(?:" .
            "[^\"]*&)?merchantId=[^\"]"],
        ["Umbraco","powered by <a href=[^>]+umbraco"],
        ["VP-ASP","<a[^>]+>Powered By VP-ASP Shopping Cart</a>"],
        ["Vanilla","<body id=\"(?:DiscussionsPage|vanilla)"],
        ["Veoxa","<img [^>]*src=\"[^\"]+tracking\\.veoxa\\.com"],
        ["VideoJS","<div[^>]+class=\"video-js+\">"],
        ["Vignette","<[^>]+=\"vgn-?ext"],
        ["Vimeo","(?:<(?:param|embed)[^>]+vimeo\\.com/moogaloop|<iframe[^>]" .
            "player\\.vimeo\\.com)"],
        ["VirtueMart","<div id=\"vmMainPage"],
        ["Volusion","<link [^>]*href=\"[^\"]*/vspfiles/"],
        ["apps['W3 Total Cache']","<!--[^>]+W3 Total Cache"],
        ["apps['WP Rocket']","<!--[^>]+WP Rocket"],
        ["apps['Web Optimizer']","<title [^>]*lang=\"wo\">"],
        ["Webtrends","<img[^>]+id=\"DCSIMG\"[^>]+webtrends"],
        ["Wikispaces","<script[^>]*>[^<]*session_url:\\s*'https://session\\." .
            "wikispaces\\.com/"],
        ["WikkaWiki","Powered by <a href=\"[^>]+WikkaWiki"],
        ["apps['Wolf CMS']","(?:<a href=\"[^>]+wolfcms\\.org[^>]+>Wolf CMS(" .
            "?:</a>)? inside|Thank you for using <a[^>]+>Wolf CMS)"],
        ["WooCommerce","<!-- WooCommerce"],
        ["WordPress","<link rel=[\"']stylesheet[\"'] [^>]+wp-(?:content|inc" .
            "ludes)"],
        ["apps['WordPress Super Cache']","<!--[^>]+WP-Super-Cache"],
        ["apps['Wowza Media Server']","<title>Wowza Media Server \\d+ ((\\w" .
            "+ Edition )?\\d+\\.[\\d\\.]+( build\\d+)?)?\\;version:\\1"],
        ["X-Cart","Powered by X-Cart(?: (\\d+))? <a[^>]+href=\"http://www\\." .
            "x-cart\\.com/\"[^>]*>\\;version:\\1"],
        ["XAMPP","<title>XAMPP( Version ([\\d\\.]+))?</title>\\;version:\\1" .
            "\\;confidence:90"],
        ["XMB","<!-- Powered by XMB"],
        ["XenForo","(?:jQuery\\.extend\\(true, XenForo|Forum software by Xe" .
            "nForo&trade;|<!--XF:branding|<html[^>]+id=\"XenForo\")"],
        ["apps['YUI Doc']","(?:<html[^>]* yuilibrary\\.com/rdf/[\\d.]+/yui\\" .
            ".rdf|<body[^>]+class=\"yui3-skin-sam)"],
        ["YaBB","Powered by <a href=\"[^>]+yabbforum"],
        ["apps['Yahoo Advertising']","<iframe[^>]+adserver\\.yahoo\\.com"],
        ["apps['Yahoo! Ecommerce']","<link[^>]+store\\.yahoo\\.net"],
        ["Direct']","<yatag class=\"ya-partner__ads\">"],
        ["Yii","Powered by <a href=\"http://www.yiiframework.com/\" rel=\"e" .
            "xternal\">Yii Framework</a>"],
        ["apps['Yoast SEO']","<!-- This site is optimized with the Yoast"],
        ["YouTube","<(?:param|embed|iframe)[^>]+youtube(?:-nocookie)?\\.com" .
            "/(?:v|embed)"],
        ["ZK","<!-- ZK [\\.\\d\\s]+-->"],
        ["apps['ZURB Foundation']","<link[^>]+foundation[^>\"]+css"],
        ["Zabbix","<body[^>]+zbxCallPostScripts"],
        ["Zanox","<img [^>]*src=\"[^\"]+ad\\.zanox\\.com"],
        ["cPanel","<!-- cPanel"],
        ["cgit","<[^>]+id='cgit'"],
        ["comScore","<iframe[^>]* (?:id=\"comscore\"|scr=[^>]+comscore)|\\." .
            "scorecardresearch\\.com/beacon\\.js|COMSCORE\\.beacon"],
        ["gitweb","<!-- git web interface version"],
        ["nopCommerce","(?:<!--Powered by nopCommerce|Powered by: <a[^>]+no" .
            "pcommerce)"],
        ["osCSS","<body onload=\"window\\.defaultStatus='oscss templates';\""],
        ["osCommerce","(?:<a[^>]*(?:\\?|&)osCsid|Powered by (?:<[^>]+>)?osC" .
            "ommerce</a>|<[^>]+class=\"[^>]*infoBoxHeading)"],
        ["ownCloud","<a href=\"https://owncloud.com\" target=\"_blank\">own" .
            "Cloud Inc.</a><br/>Your Cloud, Your Data, Your Way!"],
        ["apps['papaya CMS']","<link[^>]*/papaya-themes/"],
        ["phpAlbum","<!--phpalbum ([.\\d\\s]+)-->\\;version:\\1"],
        ["phpBB","(?:Powered by <a[^>]+phpbb|<a[^>]+phpbb[^>]+class=\\.copy" .
            "right|    phpBB style name|<[^>]+styles/(?:sub|pro)silver/theme|<" .
            "img[^>]+i_icon_mini|<table class=\"forumline)"],
        ["phpDocumentor","<!-- Generated by phpDocumentor"],
        ["phpMyAdmin","(?: \\| phpMyAdmin ([\\d.]+)<\\/title>|PMA_sendHeade" .
            "rLocation\\(|<link [^>]*href=\"[^\"]*phpmyadmin\\.css\\.php)\\;" .
            "version:\\1"],
        ["phpPgAdmin","(?:<title>phpPgAdmin</title>|<span class=\"appname\"" .
            ">phpPgAdmin)"],
        ["phpwind","Powered by <a href=\"[^\"]+phpwind\\.net"],
        ["prettyPhoto","(?:<link [^>]*href=\"[^\"]*prettyPhoto(?:\\.min)?\\." .
            "css|<a [^>]*rel=\"prettyPhoto)"],
        ["punBB","Powered by <a href=\"[^>]+punbb"],
        ["reCAPTCHA","(?:<div[^>]+id=\"recaptcha_image|<link[^>]+recaptcha|" .
            "document\\.getElementById\\('recaptcha')"],
        ["viennaCMS","powered by <a href=\"[^>]+viennacms"],
        ["js']","<link[^>]+?href=\"[^\"]+vis(?:\\.min)?\\.css"],
        ["xCharts","<link[^>]* href=\"[^\"]*xcharts(?:\\.min)?\\.css"],
        ["xtCommerce","<div class=\"copyright\">[^<]+<a[^>]+>xt:Commerce"],
    ];
}
