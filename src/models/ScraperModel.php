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
 * @author Chris Pollett chris@pollett.orgs
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
/**
 * Used to manage data related to the SCRAPER database table.
 * This table is used to store web scrapers, a tool for scraping
 * important content from pages which might have been generated
 * by a content management system.
 *
 * @author Charles Bocage (changed CMS_DETECTORS to SCRAPER and
 *  simplified Chris Pollett)
 */
class ScraperModel extends Model
{
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     *
     * @param string $args it does not matter.
     * @return string which table to use
     */
    public function fromCallback($args = null)
    {
        return "SCRAPER";
    }
    /**
     * Return the contents of the SCRAPER table
     * @return array associative of rows with ID,  NAME, SIGNATURE,
     * PRIORITY, TEXT_PATH, DELETE_PATHS, EXTRACT_FIELDS, one for each scraper
     */
    public function getAllScrapers()
    {
        $db = $this->db;
        $sources = [];
        $sql = "SELECT * FROM SCRAPER";
        $result = $db->execute($sql);
        if (!$result) {
            return false;
        }
        while ($sources[] = $db->fetchArray($result)) {
        }
        return $sources;
    }
    /**
     * Used to add a new scraper to Yioop
     *
     * @param string $name of scraper to add
     * @param string $signature the xpath to query the html of a web
     *     document to see if a scrape rule should be applied
     * @param int $priority to choose this scrape rule as opposed to other
     *      scrape rules
     * @param string $text_path the xpath string used to find
     *     the main dom container for the important text in the html document
     * @param string $delete_paths xpath strings of dom elements to be removed
     *      from the dom after the dom was restricted to just the $text_path
     *      content. These are used to remove extranenous info from the main
     *      text contents. Each xpath should be separated from each other by
     *      a new line.
     * @param string $extract_fields a string of lines each line consists
     *      of a summary field name followed by = followed by
     *      an xpath. The intended meaning of such a line is to evaluate
     *      the xpath and create a new field in a document summary with
     *      either the concatenated, trimmed text value of the nodes of the
     *      results of the xpath
     */
    public function add($name, $signature, $priority, $text_path,
        $delete_paths, $extract_fields)
    {
        $db = $this->db;
        $sql = "INSERT INTO SCRAPER(NAME, SIGNATURE, PRIORITY, TEXT_PATH,
            DELETE_PATHS, EXTRACT_FIELDS) VALUES (?, ?, ?, ?, ?, ?)";
        $db->execute($sql, [$name, $signature, $priority, $text_path,
            $delete_paths, $extract_fields]);
    }
    /**
     * Deletes the scraper with the provided id
     *
     * @param int $id of scraper to be deleted
     */
    public function delete($id)
    {
        $sql = "DELETE FROM SCRAPER WHERE ID=?";
        $this->db->execute($sql, [$id]);
    }
    /**
     * Returns the scraper with the given id
     * @param int $id of scraper to look up
     * @return array associative array with ID, NAME, SIGNATURE,
     *      PRIORITY, TEXT_PATH, DELETE_PATHS, EXTRACT_FIELDS of a scraper
     */
    public function get($id)
    {
        $db = $this->db;
        $sql = "SELECT * FROM SCRAPER WHERE ID = ?";
        $result = $db->execute($sql, [$id]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }
    /**
     * Used to update the fields stored in a SCRAPER row according to
     * an array holding new values
     * @param array $scraper_info updated values for scraper
     */
    public function update($scraper_info)
    {
        $id = $scraper_info['ID'];
        unset($scraper_info['ID']);
        $sql = "UPDATE SCRAPER SET ";
        $comma = "";
        $params = [];
        foreach ($scraper_info as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE ID=?";
        $params[] = $id;
        $this->db->execute($sql, $params);
    }
}
