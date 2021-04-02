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
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop\configs as C;
/**
 * Element responsible for drawing the sequence of available pages for
 * search  results.
 * @author Chris Pollett
 */
class PaginationElement extends Element
{
    /**
     * Draws the sequence of available pages for
     * search results. (next prev links and, group of pages)
     * @param array $data containing fields with info about thhe  total
     *  number of search results for the query, the subsearch the query is
     *  is for, the desired number of results per page, which page we are on,
     *  etc.
     */
    public function render($data)
    {
        if (empty($data['TOTAL_ROWS'])) {
            return;
        }
        $base_url = (empty($data['PAGING_QUERY'])) ? "" :
            "?" . http_build_query($data['PAGING_QUERY'], '', '&amp;');
        if ($data['RESULTS_PER_PAGE'] < 0) {
            $results_per_page = $data['RESULTS_PER_PAGE'];
            if (!empty($data['SUBSEARCH']) && !empty($data["SUBSEARCHES"])) {
                foreach ($data["SUBSEARCHES"] as $search) {
                    if ($search['FOLDER_NAME'] == $data['SUBSEARCH']) {
                        $results_per_page = -$search['PER_PAGE'];
                        break;
                    }
                }
            }
            $this->view->helper("pagination")->render($base_url, $data['LIMIT'],
                $results_per_page, $data['TOTAL_ROWS']);
        } else {
            $this->view->helper("pagination")->render($base_url, $data['LIMIT'],
                $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        }
    }
}
