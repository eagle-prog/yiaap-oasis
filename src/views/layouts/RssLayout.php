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
 * @author Chris Pollett chris@pollett.org
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\views\layouts;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Layout used for the seek_quarry Website
 * including pages such as search landing page
 * and settings page
 *
 * @author Chris Pollett
 */
class RssLayout extends Layout
{
    /**
     * Responsible for drawing the header of the document containing
     * Yioop! title and including basic.js. It calls the renderView method of
     * the View that lives on the layout. If the QUERY_STATISTIC config setting
     * is set, it output statistics about each query run on the database.
     * Finally, it draws the footer of the document.
     *
     * @param array $data  an array of data set up by the controller to be
     * be used in drawing the WebLayout and its View.
     */
    public function render($data)
    {
        $web_site = $this->view->controller_object->web_site;
        $web_site->header("Content-type: application/rss+xml");
        $query = $data['QUERY'] ?? "";
        e('<?xml version="1.0" encoding="UTF-8" ?>'."\n");?>
<rss version="2.0" <?php
if (!empty($data['QUERY'])) {?>
    xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/"
    xmlns:atom="http://www.w3.org/2005/Atom" <?php
}?>
>
    <channel>
        <title><?=tl('rss_layout_title',
             mb_convert_encoding(html_entity_decode(
             urldecode($query)), "UTF-8")) ?></title>
        <language><?= L\getLocaleTag() ?></language><?php
        if (!empty($data['QUERY'])) {?>
            <link><?=C\NAME_SERVER ?>?f=rss&amp;q=<?= $data['QUERY']
            ?>&amp;<?php
            ?>its=<?= $data['its'] ?></link><?php
        } else {?>
            <link><?=C\NAME_SERVER ?></link><?php
        }?>
        <description><?=tl('rss_layout_description',
        mb_convert_encoding(html_entity_decode(urldecode($query)),
        "UTF-8")) ?></description><?php
        if (!empty($data['QUERY'])) {?>
            <opensearch:totalResults><?=$data['TOTAL_ROWS'] ?? 0
            ?></opensearch:totalResults>
            <opensearch:startIndex><?=$data['LIMIT'] ?? 0
            ?></opensearch:startIndex>
            <opensearch:itemsPerPage><?=$data['RESULTS_PER_PAGE'] ??
                C\NUM_RESULTS_PER_PAGE
            ?></opensearch:itemsPerPage>
            <atom:link rel="search" type="application/opensearchdescription+xml"
                href="<?= C\SEARCHBAR_PATH ?>"/>
            <opensearch:Query role="request" searchTerms="<?=$data['QUERY']
                ?>"/><?php
        }
        $this->view->renderView($data); ?>
    </channel>
</rss>
<?php
    }
}
