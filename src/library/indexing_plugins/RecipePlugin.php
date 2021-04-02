<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2011 - 2017 Priya Gangaraju priya.gangaraju@gmail.com,
 *     Chris Pollett, chris@pollett.org
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com, Chris Pollett
 *     chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2011 - 2018
 * @filesource
 */
namespace seekquarry\yioop\library\indexing_plugins;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\controllers\SearchController;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\IndexArchiveBundle;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\processors\HtmlProcessor;
use seekquarry\yioop\library\UrlParser;

/** Don't try to use file cache either*/
if (empty($_SERVER["USE_CACHE"])) {
    $_SERVER["USE_CACHE"] = false;
}
/** Get the crawlHash function */
require_once C\BASE_DIR . "/library/Utility.php";
/** For locale used by recipe query*/
require_once C\BASE_DIR . "/library/LocaleFunctions.php";
/**
 * This class handles recipe processing.
 * It extracts ingredients from the recipe pages while crawling.
 * It clusters the recipes using Kruskal's minimum spanning tree
 * algorithm after crawl is stopped. This plugin was designed by
 * looking at what was needed to screen scrape recipes from the
 * following sites:
 *
 * https://allrecipes.com/
 * http://www.geniuskitchen.com/
 * http://www.betterrecipes.com/
 * https://www.bettycrocker.com/
 *
 *
 * @author Priya Gangaraju, Chris Pollett (re-organized, added documentation,
 *     updated)
 */
class RecipePlugin extends IndexingPlugin implements CrawlConstants
{
    /**
     * Ingredients that are common to many recipes so unlikely to be the main
     * ingredient for a recipe
     * @var array
     */
    public static $basic_ingredients = [
        'onion','oil','cheese','pepper','sauce',
        'salt','milk','butter','flour','cake',
        'garlic','cream','soda','honey','powder',
        'sauce','water','vanilla','pepper','bread',
        'sugar','vanillaextract','celery',
        'seasoning','syrup','skewers','egg',
        'muffin','ginger','basil','oregano',
        'cinammon','cumin','mayonnaise','mayo',
        'chillipowder','lemon','greens','yogurt',
        'margarine','asparagus','halfhalf',
        'pancakemix','coffee','cookies','lime',
        'chillies','cilantro','rosemary',
        'vanillaextract','vinegar','shallots',
        'wine','cornmeal','nonstickspray'];
    /**
     * Ratio of clusters/total number of recipes seen
     */
    const CLUSTER_RATIO = 0.1;
    /**
     * Number of recipes to put into a shard before switching shards while
     * clustering
     */
    const NUM_RECIPES_PER_SHARD = 1000;
    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher. For the recipe plugin a sub-document
     * will be the title of the recipe. The description will consists
     * of the ingredients of the recipe. Ingredients will be separated by
     * ||
     *
     * @param string $page web-page contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array consisting of a sequence of subdoc arrays found
     *     on the given page. Each subdoc array has a self::TITLE and
     *     a self::DESCRIPTION
     */
    public function pageProcessing($page, $url)
    {
        L\crawlLog("...Using recipe plugin to check for recipes!");
        $page = preg_replace('@<script[^>]*?>.*?</script>@si', ' ', $page);
        $page = preg_replace('/>/', '> ', $page);
        $dom = HtmlProcessor::dom($page);
        if ($dom == null) {
            return null;
        }
        $xpath = new \DOMXPath($dom);
        //detect recipes
        $recipes_per_page = $xpath->evaluate(
            /*allr, f.com, brec, fnet*/
            "//header[contains(@class, 'recipe-header')]/h1|" .
            "/html//*[@id='recipe_title']|" .
            "/html//*[@itemtype='http://schema.org/Recipe']");
        $recipe = [];
        $subdocs_description = [];
        if (is_object($recipes_per_page) && $recipes_per_page->length != 0) {
            $recipes_count = $recipes_per_page->length;
            $titles = $xpath->evaluate(
               /* allr, f.com, brec, fnet   */
               "/html//*[@id = 'recipe_title']|" .
               "//header[contains(@class, 'recipe-header')]/h1|" .
               "/html//*[@itemprop = 'name']");
            if ($titles->length == 0) {
                return $subdocs_description;
            }
            for ($i = 0; $i < $recipes_count; $i++) {
                $ingredients = $xpath->evaluate(
                    /*allr*, fcomm, brec, fnet*/
                    "/html//ul[@class = 'ingredient-wrap']/li|" .
                    "/html//li[@data-ingredient]|" .
                    "/html//*[@itemprop ='ingredient']|" .
                    "/html//*[@itemprop='ingredients']");
                $ingredients_result = "";
                if (is_object($ingredients) && $ingredients->length != 0){
                    $lastIngredient = end($ingredients);
                    foreach ($ingredients as $ingredient) {
                        $content = trim($ingredient->textContent);
                        if (!empty($content)) {
                            if ($content  != $lastIngredient)
                                $ingredients_result .= $content."||";
                            else
                                $ingredients_result .= $content;
                        }
                    }
                    $ingredients_result = mb_ereg_replace(
                        "(\s)+", " ", $ingredients_result);
                }
                $recipe[self::TITLE] = $titles->item($i)->textContent;
                $recipe[self::DESCRIPTION] = $ingredients_result;
                $subdocs_description[] = $recipe;
            }
        }
        $num_recipes = count($subdocs_description);
        L\crawlLog("...$num_recipes found.");
        return $subdocs_description;
    }
    /**
     * Implements post processing of recipes. recipes are extracted
     * ingredients are scrubbed and recipes are clustered. The clustered
     * recipes are added back to the index.
     *
     * @param string $index_name  index name of the current crawl.
     */
    public function postProcessing($index_name)
    {
        if (!class_exists("\SplHeap")) {
            L\crawlLog("...Recipe Plugin Requires SPLHeap for clustering!");
            L\crawlLog("...Aborting plugin");
            return;
        }
        $search_controller = new SearchController();
        $query = "recipe:all i:$index_name";
        L\crawlLog("...Running Recipe Plugin!");
        L\crawlLog("...Finding docs tagged as recipes.");
        $more_docs = true;
        $raw_recipes = [];
        $limit = 0;
        $up_to_last_shard_num = 0;
        $num = 100;
        $added_recipes = false;
        set_error_handler(null);
        while($more_docs) {
            $added_recipes_round = false;
            while($more_docs ||
                $limit > $up_to_last_shard_num + self::NUM_RECIPES_PER_SHARD) {
                $results = @$search_controller->queryRequest($query,
                    $num, $limit, 1, $index_name);
                if (isset($results["PAGES"]) &&
                    ($num_results = count($results["PAGES"])) > 0 ) {
                    $raw_recipes = array_merge($raw_recipes, $results["PAGES"]);
                }
                L\crawlLog("Scanning recipes $limit through ".
                    ($limit + $num_results).".");
                $limit += $num_results;
                if (isset($results["SAVE_POINT"]) ){
                    $end = true;
                    foreach ($results["SAVE_POINT"] as $save_point) {
                        if ($save_point != -1) {
                            $end = false;
                        }
                    }
                    if ($end) {
                        $more_docs = false;
                    }
                } else {
                    $more_docs = false;
                }
            }
            set_error_handler(C\NS_CONFIGS . "yioop_error_handler");
            L\crawlLog("...Clustering from $up_to_last_shard_num to ");
            $up_to_last_shard_num += self::NUM_RECIPES_PER_SHARD;
            L\crawlLog("...to $up_to_last_shard_num");
            // only cluster if would make more than one cluster
            if (count($raw_recipes) * self::CLUSTER_RATIO > 1 ) {
                $recipes = [];
                $i = 0;
                foreach ($raw_recipes as $raw_recipe) {
                    $ingredients = explode("||",
                        $raw_recipe[self::DESCRIPTION]);
                    if (is_array($ingredients) && count($ingredients) > 1) {
                        $recipes[$i][0]= $raw_recipe[self::TITLE];
                        $recipes[$i][1] = $ingredients;
                        $recipes[$i][2] = L\crawlHash($raw_recipe[self::URL]);
                        $recipes[$i][3] = $raw_recipe;
                        $i++;
                    }
                }
                $recipes_ingredients = [];
                $count = count($recipes);
                foreach ($recipes as $key => $recipe) {
                    foreach ($recipe[1] as $index => $ingredient) {
                        if (strlen($ingredient) != 0 && (
                                substr($ingredient,
                                    strlen($ingredient) - 1) != ":")) {
                            $main_ingredient =
                                $this->getIngredientName((string)$ingredient);
                            if (strlen($main_ingredient) != 0) {
                                $recipe[1][$index] = $main_ingredient;
                            } else {
                                unset($recipe[1][$index]);
                            }
                        } else {
                            unset($recipe[1][$index]);
                        }
                    }
                        $recipes[$key] = $recipe;
                }
                $num_recipes = count($recipes);
                $k = 0;
                $weights = [];
                $distinct_ingredients = [];
                for ($i = 0; $i < $num_recipes; $i++) {
                    $recipe1_main_ingredient = "";
                    // recipe1 is an array of ingredients
                    $recipe1 = $recipes[$i][1];
                    $recipe_name = $recipes[$i][0];
                    $recipe1_title = strtolower($recipes[$i][0]);
                    $distinct_ingredients[$recipe_name] = $recipes[$i][1];
                    $doc_keys[$recipe_name] = $recipes[$i][2];
                    $recipes_summary[$recipe_name] = $recipes[$i][3];
                    foreach ($recipe1 as $ingredient){
                        if ($ingredient != "" && !in_array($ingredient,
                            RecipePlugin::$basic_ingredients)) {
                                if (strstr($recipe1_title, $ingredient)) {
                                    $recipe1_main_ingredient = $ingredient;
                                    break;
                                }
                        }
                    }
                    for ($j = $i + 1; $j < $num_recipes; $j++) {
                        $recipe2_main_ingredient = "";
                        // recipe2 is an array of ingredients
                        $recipe2 = $recipes[$j][1];
                        $recipe2_title = strtolower($recipes[$j][0]);
                        $weights[$k][0] = $recipes[$i][0];
                        $weights[$k][1] = $recipes[$j][0];
                        $merge_array = array_merge($recipe1, $recipe2);
                        $vector_array = array_unique($merge_array);
                        sort($vector_array);
                        $recipe1_vector = array_fill_keys($vector_array, 0);
                        $recipe2_vector = array_fill_keys($vector_array, 0);
                        foreach ($recipe1 as $ingredient) {
                            $recipe1_vector[$ingredient] = 1;
                        }
                        $main_ingredient_found = false;
                        foreach ($recipe2 as $ingredient) {
                            if (!$main_ingredient_found && $ingredient != "" &&
                                !in_array($ingredient,
                                RecipePlugin::$basic_ingredients)) {
                                    if (strstr($recipe2_title, $ingredient))  {
                                        $recipe2_main_ingredient = $ingredient;
                                        $main_ingredient_found = true;
                                    }
                            }
                            $recipe2_vector[$ingredient] = 1;
                        }
                        $edge_weight = 0;
                        $matches = 1;
                        foreach ($vector_array as $vector) {
                            $diff = $recipe1_vector[$vector] -
                                $recipe2_vector[$vector];
                            $vector_diff[$vector] = (pow($diff, 2));
                            if (abs($diff) == 1)
                                $matches += 1;
                            $edge_weight += $vector_diff[$vector];
                        }
                        $main_ingredient_match = 1;
                        /*  recipes that share main ingredients should have
                            heavier weighted edges between them
                         */
                        if ($recipe1_main_ingredient ==
                            $recipe2_main_ingredient) {
                            $main_ingredient_match = 1000;
                        }
                        $edge_weight = sqrt($edge_weight) *
                            $matches * $main_ingredient_match;
                        $weights[$k][2] = $edge_weight;
                        $k++;
                    }
                }
                /* $weights at this point is an array
                   [vertex_0, vertex_1, edge_weight]
                 */
                $clusters = $this->getClusters($weights, $distinct_ingredients);
                L\crawlLog("...Making new shard with clustered ".
                    "recipes as docs.");
                $index_shard = new IndexShard("cluster_shard");
                $word_lists = [];
                $recipe_sites = [];
                foreach ($clusters as $cluster) {
                    $num_recipes_cluster = count($cluster);
                    for ($i = 0; $i < $num_recipes_cluster - 1; $i++) {
                        $meta_ids = [];
                        $summary = [];
                        $recipe = $cluster[$i];
                        $summary[self::URL] =
                            $recipes_summary[$recipe][self::URL];
                        $summary[self::TITLE] =
                            $recipes_summary[$recipe][self::TITLE];
                        $summary[self::DESCRIPTION] =
                            $recipes_summary[$recipe][self::DESCRIPTION];
                        $summary[self::TIMESTAMP] =
                            $recipes_summary[$recipe][self::TIMESTAMP];
                        $summary[self::ENCODING] =
                            $recipes_summary[$recipe][self::ENCODING];
                        $summary[self::HASH] =
                            $recipes_summary[$recipe][self::HASH];
                        $doc_keys[$recipe] =
                            L\crawlHash($summary[self::URL], true);
                        $hash_rhost =  "r". substr(L\crawlHash(//r is for recipe
                            UrlParser::getHost(
                            $summary[self::URL])."/", true), 1);
                        $doc_keys[$recipe] .= $summary[self::HASH] .$hash_rhost;
                        $summary[self::TYPE] =
                            $recipes_summary[$recipe][self::TYPE];
                        $summary[self::HTTP_CODE] =
                            $recipes_summary[$recipe][self::HTTP_CODE];
                        $recipe_sites[] = $summary;
                        $meta_ids[] = "ingredient:" .
                            trim($cluster["ingredient"]);
                        L\crawlLog("ingredient:" . $cluster["ingredient"]);
                        if (!$index_shard->addDocumentWords($doc_keys[$recipe],
                            self::NEEDS_OFFSET_FLAG,
                            $word_lists, $meta_ids, true, false)) {
                            L\crawlLog("Problem inserting recipe: ".
                                $summary[self::TITLE]);
                        }
                    }
                }
                $shard_string = $index_shard->save(true);
                $index_shard = IndexShard::load("cluster_shard",
                    $shard_string);
                unset($shard_string);
                L\crawlLog("...Adding recipe shard to index archive bundle");
                $dir = C\CRAWL_DIR . "/cache/" . self::index_data_base_name .
                    $index_name;
                if (empty($index_archive)) {
                    $index_archive = new IndexArchiveBundle($dir, false);
                }
                if ($index_shard->word_docs_packed) {
                    $index_shard->unpackWordDocs();
                }
                if (!empty($recipe_sites)) {
                    $generation = $index_archive->initGenerationToAdd(
                        count($recipe_sites));
                    L\crawlLog("... Adding ".count($recipe_sites).
                        " recipe docs.");
                    $index_archive->addPages($generation,
                        self::SUMMARY_OFFSET, $recipe_sites, 0);
                    $k = 0;
                    foreach ($recipe_sites as $site) {
                        $recipe = $site[self::TITLE];
                        $hash = L\crawlHash($site[self::URL], true).
                            $site[self::HASH] .
                            "r". substr(L\crawlHash( // r is for recipe
                            UrlParser::getHost($site[self::URL])."/",true), 1);
                        $summary_offsets[$hash] = $site[self::SUMMARY_OFFSET];
                    }
                    $index_shard->changeDocumentOffsets($summary_offsets);
                    $index_archive->addIndexData($index_shard);
                    $added_recipes_round = true;
                    $added_recipes = true;
                }
            }
            if ($added_recipes) {
                $index_archive->forceSave();
                $index_archive->addAdvanceGeneration();
                $index_archive->dictionary->mergeAllTiers();
                $this->db->setWorldPermissionsRecursive(
                    C\CRAWL_DIR.'/cache/'.
                    self::index_data_base_name . $index_name);
            }
            L\crawlLog("...Recipe plugin finished.");
        }
    }
    /**
     * Extracts the main ingredient from the ingredient.
     *
     * @param string $text ingredient.
     * @return string $name main ingredient
     */
    public function getIngredientName($text)
    {
        $special_chars = ['/\d+/','/\\//'];
        $ingredient = preg_replace($special_chars," ", $text);
        $ingredient = strtolower($ingredient);
        $varieties = ['apple','bread','cheese','chicken','shrimp',
            'tilapia','salmon','butter','chocolate','sugar','pepper','water',
            'mustard','cream','lettuce','sauce','crab','garlic','mushrooms',
            'tortilla','potatoes','steak','rice','vinegar','carrots',
            'marshmellows','onion','oil','ham','parsley','cilantro','broth',
            'stock','flour','seasoning','banana','pasta','noodles','pork',
            'bacon','olives','spinach','yogurt','celery','beans','egg',
            'apricot','whiskey','wine','milk','mango','tomato','lemon',
            'salsa','herbs','sourdough','prosciutto','seasoning','syrup',
            'honey','skewers','muffin','beef','cinammon','thyme','asparagus',
            'turkey','pumpkin'];
        foreach ($varieties as $variety){
            if (strstr($ingredient, $variety)) {
                $ingredient = $variety;
            }
        }
        $words = explode(' ', $ingredient);
        $measurements = ['cup','cups','ounces','teaspoon','teaspoons',
            'tablespoon','tablespoons','pound','pounds','tbsp','tsp','lbs',
            'inch','pinch','oz','lb','tbs','can','bag','C','c','tb'];
        $sizes = ['small','large','thin','less','thick','bunch'];
        $prepositions = ['into', 'for', 'by','to','of'];
        $misc = ['hot','cold','room','temperature','plus','stick','pieces',
            "confectioners",'semisweet','white','all-purpose','bittersweet',
            'cut','whole','or','and','french','wedges','package','pkg','shells',
            'cartilege','clean','hickory','fillets','fillet','plank','planks',
            'cedar','taste','spicy','glaze','crunchy','sharp','chips','juice',
            'optional','fine','regular','dash','overnight','soaked','classic',
            'firm','delicious','prefer','plain'];
        $attributes = ['boneless','skinless','breast','legs','thighs',
            'washington','fresh','flat','leaf','ground','extra','virgin','dry',
            'cloves','lean','ground','roma','all purpose','light','brown',
            'idaho','kosher','frozen','garnish'];
        $nouns = [];
        $i = 0;
        $endings = ['/\,/','/\./','/\+/','/\*/',"/'/","/\(/","/\)/"];
        foreach ($words as $word) {
            if ($word != ''){
                $word = strtolower($word);
                foreach ($varieties as $variety){
                        if (strstr($word,$variety))
                            $word = $variety;
                    }
                $word = preg_replace($endings,"",$word);
                if (!in_array($word,$measurements) && !in_array($word,$sizes)
                    && !in_array($word,$prepositions) && !in_array($word,$misc)
                    && !in_array($word,$attributes)) {
                    $ending = substr($word, -2);
                    $ending2 = substr($word, -3);
                    if ($ending != 'ly' && $ending != 'ed' && $ending2 != 'ing')
                    {
                    $nouns[] = $word;
                    }
                }
            }
        }
        $name = implode(" ", $nouns);
        $name = preg_replace('/[^a-zA-Z]/', "", $name);
        return $name;
    }
    /**
     * Creates tree from the input and apply Kruskal's algorithm to find minimal.
     * spanning tree
     *
     * @param array $edges elements of form (recipe_1_title, recipe_2_title, weight)
     * @return array $min_edges just those edges from the original edgest needed to
     * make a minimal spanning
     */
    private function extractMinimalSpanningTreeEdges($edges)
    {
        $vertices = [];
        $tree_heap = new MinWeightedEdgeHeap();
        $vertice_no = 1;
        for ($i = 0; $i < count($edges) - 1; $i++) {
            $edge1 = new WeightedEdge($edges[$i][0], $edges[$i][1],
                $edges[$i][2]);
            $tree_heap->insert($edge1);
            $vertex1 = $edge1->getStartVertex();
            $vertex2 = $edge1->getEndVertex();
            if (empty($vertices[$vertex1->getLabel()])){
                    $vertices[$vertex1->getLabel()] = $vertice_no;
                    $vertice_no++;
            }
            if (empty($vertices[$vertex2->getLabel()])) {
                    $vertices[$vertex2->getLabel()] = $vertice_no;
                    $vertice_no++;
            }
        }
        $k = 0;
        $tree_heap->top();
        while($k < count($vertices) - 1) {
            $min_edge = $tree_heap->extract();
            $vertex1= $min_edge->getStartVertex()->getLabel();
            $vertex2 = $min_edge->getEndVertex()->getLabel();
            if ($vertices[$vertex1] != $vertices[$vertex2]){
                if ($vertices[$vertex1] < $vertices[$vertex2]){
                        $m = $vertices[$vertex2];
                        $n = $vertices[$vertex1];
                } else {
                    $m = $vertices[$vertex1];
                    $n = $vertices[$vertex2];
                }
                foreach ($vertices as $vertex => $no){
                    if ($no == $m) {
                        $vertices[$vertex] = $n;
                    }
                }
                $min_edges[] = $min_edge;
                $k++;
            }
        }
        return $min_edges;
    }
    /**
     * Clusters the recipes from an array recipe adjacency weights.
     *
     * @param array $recipe_adjacency_weights array of triples
     *  (recipe_1_title, recipe_2_title, weight)
     * @param array $distinct_ingredients list of possible ingredients
     * @return array list of clusters of recipes. This array will have
     *      total_number_of_recipes *  self::CLUSTER_RATIO many clusters.
     *      Each cluster will contain an ingredient field with the most common
     *      non basic ingredient found in the recipes of that cluster.
     */
    private function getClusters($recipe_adjacency_weights,
        $distinct_ingredients)
    {
        $minimal_spanning_tree_edges = $this->extractMinimalSpanningTreeEdges(
            $recipe_adjacency_weights);
        $recipe_clusterer = new RecipeClusterer($minimal_spanning_tree_edges);
        $clusters = $recipe_clusterer->makeClusters();
        $clusters_with_ingredient_label =
            $recipe_clusterer->addMostCommonIngredientClusters($clusters,
                $distinct_ingredients);
        return $clusters_with_ingredient_label;
    }
    /**
     * Which mime type page processors this plugin should do additional
     * processing for
     *
     * @return array an array of page processors
     */
    public static function getProcessors()
    {
        return ["HtmlProcessor"];
    }
    /**
     * Returns an array of additional meta words which have been added by
     * this plugin
     *
     * @return array meta words and maximum description length of results
     *     allowed for that meta word (in this case 2000 as want
     *     to allow sufficient descriptions of whole recipes)
     */
    public static function getAdditionalMetaWords()
    {
        return ["recipe:" => C\MAX_DESCRIPTION_LEN,
            "ingredient:" => C\MAX_DESCRIPTION_LEN];
    }
}
/**
 * Vertex class for used for Recipe Clustering
 */
class Vertex
{
    /**
     * Name of this Vertex (recipe title)
     * @var string
     */
    public $label;
    /**
     * Whether this node has been seen as part of MST construction
     * @var bool
     */
    public $visited;
    /**
     * Construct a vertex suitable for the Recipe Clustering Minimal Spanning
     * Tree
     *
     * @param string $label name of this Vertex (recipe title)
     */
    public function __construct($label)
    {
        $this->label = $label;
        $this->visited = false;
    }
    /**
     * Accessor for label of this Vertex
     * @return string label of Vertex
     */
    public function getLabel()
    {
        return $this->label;
    }
    /**
     * Sets the vertex to visited
     */
    public function visited()
    {
        $this->visited = true;
    }
    /**
     * Accessor for $visited state of this Vertex
     * @return bool $visited state
     */
    public function isVisited()
    {
        return $this->visited;
    }
}
/**
 * Directed Edge class for Recipe Clustering
 */
class WeightedEdge
{
    /**
     * Starting vertex of the directed edge this object represents
     * @var Vertex
     */
    public $start_vertex;
    /**
     * End vertex of the directed edge this object represents
     * @var Vertex
     */
    public $end_vertex;
    /**
     * Weight of this edge
     * @var float
     */
    public $cost;
    /**
     * Construct a directed Edge using a starting and ending vertex and a weight
     *
     * @param Vertex $vertex1 starting Vertex
     * @param Vertex $vertex2 ending Vertex
     * @param float $cost weight of this edge
     */
    public function __construct($vertex1, $vertex2, $cost)
    {
        $this->start_vertex = new Vertex($vertex1);
        $this->end_vertex = new Vertex($vertex2);
        $this->cost = $cost;
    }
    /**
     * Accessor for starting vertex of this edge
     * @return Vertex starting vertex
     */
    public function getStartVertex()
    {
        return $this->start_vertex;
    }
    /**
     * Accessor for ending vertex of this edge
     * @return Vertex ending vertex
     */
    public function getEndVertex()
    {
        return $this->end_vertex;
    }
    /**
     * Accessor for weight of this edge
     * @return float weight of this edge
     */
    public function getCost()
    {
        return $this->cost;
    }
}
/**
 * Class to define Minimum Spanning tree for recipes. constructMST constructs
 * the minimum spanning tree using heap. formCluster forms clusters by
 * deleting the most expensive edge. BreadthFirstSearch is used to
 * traverse the MST.
 */
class RecipeClusterer
{
    /**
     * Maintains a priority queue of edges ordered by max weight
     * @var MaxWeightedEdgeHeap
     */
    public $cluster_heap;
    /**
     * Array of Vertices (Recipes)
     * @var array
     */
    public $vertices;
    /**
     * Adjacency matrix of whether recipes are adjacent to each other
     * @var array
     */
    public $adjacency_matrix;
    /**
     * Constructs a tree suitable for building containing a Minimal Spanning
     * Tree for Kruskal clustering
     * @param array $edges vertices and edge weights of MST
     */
    public function __construct($edges)
    {
        $this->cluster_heap = new MaxWeightedEdgeHeap();
        $this->vertices = [];
        foreach ($edges as $edge) {
            $this->cluster_heap->insert($edge);
            $vertex1 = $edge->getStartVertex();
            $vertex2 = $edge->getEndVertex();
            $this->adjacency_matrix[$vertex1->getLabel()][$vertex2->getLabel()]=
                $vertex2->getLabel();
            $this->adjacency_matrix[$vertex2->getLabel()][$vertex1->getLabel()]=
                $vertex1->getLabel();
            if (empty($this->vertices) || !in_array($vertex1,$this->vertices))
                $this->vertices[$vertex1->getLabel()] = $vertex1;
            if (empty($this->vertices) || !in_array($vertex2,$this->vertices))
                $this->vertices[$vertex2->getLabel()] = $vertex2;
        }
    }
    /**
     * Forms the clusters from $this->cluster_heap by removing maximum weighted
     * edges. $this->cluster_heap is initially a weighted tree as edges are
     * removed it becomes a forest. Once the number of trees in the forest
     * reaches $num_recipes * RecipePlugin::CLUSTER_RATIO, the trees are treated
     * as the clusters to be returned.
     *
     * @return array $clusters each element of $clusters is an array of
     *  recipe names
     */
    public function makeClusters()
    {
        $this->cluster_heap->top();
        $num_initial_edges = $this->cluster_heap->count();
        $num_recipes = count($this->vertices);
        $node_queue = new Queue($num_initial_edges);
        $num_cluster_to_make = $num_recipes * RecipePlugin::CLUSTER_RATIO;
        $clusters = [];
        /*
            Idea remove $num_cluster_to_make many weightiest edges from tree
            to get a forest. As do this add to queue end points of
            removed edges.
         */
        for ($j = 0; $j < $num_cluster_to_make - 1; $j++) {
            $max_edge = $this->cluster_heap->extract();
            $cluster1_start = $max_edge->getStartVertex()->getLabel();
            $cluster2_start = $max_edge->getEndVertex()->getLabel();
            $this->adjacency_matrix[$cluster1_start][$cluster2_start] = -1;
            $this->adjacency_matrix[$cluster2_start][$cluster1_start] = -1;
            $node_queue->enqueue($cluster1_start);
            $node_queue->enqueue($cluster2_start);
        }
        $queue = new Queue($num_initial_edges);
        $i = 0;
        // Now use Queue above to make clusters (trees in resulting forest)
        while(!$node_queue->isEmpty()) {
            $node = $node_queue->dequeue();
            if ($this->vertices[$node]->isVisited() == false) {
                $this->vertices[$node]->visited();
                $clusters[$i][] = $this->vertices[$node]->getLabel();
                $queue->enqueue($this->vertices[$node]->getLabel());
                while(!$queue->isEmpty()){
                    $node = $queue->dequeue();
                    while(($next_node = $this->getNextVertex($node)) != -1){
                        $this->vertices[$next_node]->visited();
                        $clusters[$i][]= $this->vertices[
                            $next_node]->getLabel();
                        $queue->enqueue($this->vertices[
                            $next_node]->getLabel());
                    }
                }
            }
            $i++;
        }
        return $clusters;
    }
   /**
    * Gets the next vertex  from the adjacency matrix for a given vertex
    *
    * @param string $vertex vertex
    * @return adjacent vertex if it has otherwise -1.
    */
    public function getNextVertex($vertex)
    {
        foreach ($this->adjacency_matrix[$vertex] as $vert=>$value) {
            if ($value != -1
                && ($this->vertices[$value]->isVisited() == false)) {
                return $this->adjacency_matrix[$vertex][$vert];
            }
        }
        return -1;
    }
   /**
    * Adds to each element of an array of recipe clusters,
    * a field ingredient containing the string name of the most common,
    * non-basic ingredient found in that cluster.
    *
    * @param array $clusters clusters of recipes.
    * @param array $ingredients array of ingredients of recipes.
    * @return array $new_clusters clusters with common ingredient appended.
    */
    public function addMostCommonIngredientClusters($clusters, $ingredients)
    {
        $k =1;
        $new_clusters = [];
        foreach ($clusters as $cluster) {
            $recipes_count = 0;
            $cluster_recipe_ingredients = [];
            $common_ingredients = [];
            for ($i = 0; $i < count($cluster); $i++){
                $recipe_name = $cluster[$i];
                $main_ingredients =
                    array_diff($ingredients[$recipe_name],
                    RecipePlugin::$basic_ingredients);
                $cluster_recipe_ingredients = array_merge(
                    $cluster_recipe_ingredients,
                    array_unique($main_ingredients));
            }
            $ingredient_occurrences =
                array_count_values($cluster_recipe_ingredients);
            if (empty($ingredient_occurrences)) {
                continue;
            }
            $max = max($ingredient_occurrences);
            foreach ($ingredient_occurrences as $key => $value){
                if ($max == $value && !in_array($key,
                    RecipePlugin::$basic_ingredients)) {
                    $common_ingredients[] = $key;
                }
            }
            $cluster_ingredient = $common_ingredients[0];
            $cluster["ingredient"] = $cluster_ingredient;
            $new_clusters[] = $cluster;
            $k++;
        }
        return $new_clusters;
    }
}
if (class_exists("\SplHeap")) {
    /**
     * Heap used during clustering to select next edge to use to cluster
     */
    class MaxWeightedEdgeHeap extends \SplHeap
    {
        /**
         *  Compares edge costs and returns -1, 0, 1 so that the edge with
         *  maximum cost/weight is at the top of heap
         *
         * @param Edge $edge1 first Edge to compare
         * @param Edge $edge2 second Edge to compare
         * @return int -1,-0,1 as described above
         */
        public function compare($edge1, $edge2)
        {
            $values1 = $edge1->getCost();
            $values2 = $edge2->getCost();
            if ($values1 == $values2) return 0;
            return $values1 < $values2 ? -1 : 1;
        }
    }
    /**
     * Heap used to compute minimal spanning tree
     */
    class MinWeightedEdgeHeap extends \SplHeap
    {
        /**
         *  Compares edge costs and returns -1, 0, 1 so that the edge with
         *  minimum cost/weight is at the top of heap
         *
         * @param Edge $edge1 first Edge to compare
         * @param Edge $edge2 second Edge to compare
         * @return int -1,-0,1 as described above
         */
        public function compare($edge1, $edge2)
        {
            $values1 = $edge1->getCost();
            $values2 = $edge2->getCost();
            if ($values1 == $values2) return 0;
            return $values1 > $values2 ? -1 : 1;
        }
    }
}
/**
 * Queue for the BFS traversal
 */
class Queue
{
    /**
     * Number of elements queue can hold
     * @var int
     */
    public $size;
    /**
     * Circular array used to store queue elements
     * @var array
     */
    public $queue_array;
    /**
     * Index in $queue_array of the front of the queue
     * @var int
     */
    public $front;
    /**
     * Index in $queue_array of the end of the queue
     * @var int
     */
    public $rear;
    /**
     * Builds a queue suitable for doing breadth first search traversal
     * @param int $size number of elements queue can hold
     */
    public function __construct($size)
    {
        $this->queue_array = [];
        $this->front = 0;
        $this->rear = -1;
        $this->size = $size;
    }
    /**
     * Add an element, typically a Vertex label to the queue
     * @param string $i typically a Vertex label
     */
    public function enqueue($i)
    {
        if ($this->rear == $this->size - 1)
            $this->rear = -1;
        $this->queue_array[++$this->rear] = $i;
    }
    /**
     * Removes the front of the queue and returns it
     * @return string front of queue
     */
    public function dequeue()
    {
        $temp = $this->queue_array[$this->front++];
        if ($this->front == $this->size)
            $this->front = 0;
        return $temp;
    }
    /**
     * Whether or not the queue is empty
     * @return bool
     */
    public function isEmpty()
    {
        if (($this->rear + 1)== $this->front ||
            ($this->front + $this->size - 1) == $this->rear)
            return true;
        return false;
    }

}
