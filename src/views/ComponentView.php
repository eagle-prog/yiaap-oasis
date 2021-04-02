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
namespace seekquarry\yioop\views;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\views\elements\Element;

/**
 * Base class for views created by adding elements to top, sub-top, same,
 *  opposite, center columns, or bottom possitions
 *
 * @author Chris Pollett
 */
class ComponentView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Containers for Elements that this ComponentView has
     * @var Array
     */
     private $containers = [];
    /**
     * Method used to draw the components  of this ComponentView
     * @param array containing fields to render the elements on this view
     */
    public function renderView($data)
    {
        $container_labels = ["top", "sub-top", "same", "opposite", "center",
            "bottom"];
        $css_classes = empty($data['CSS_CLASSES']) ? "" : $data['CSS_CLASSES'];
        foreach ($container_labels as $container_label) {
            if (!$this->emptyContainer($container_label)) {
                ?><div id="<?=$container_label;
                ?>-container" class="<?=$container_label;
                ?>-container <?=$css_classes?>"><?php
                $this->renderContainer($container_label, $data);
                ?></div><?php
            }
        }
    }
    /**
     * Adds an Element object to the ComponentView container so it can be
     * drawn in that container later.
     *
     * @param string $label name of the container on this component view to
     *      add the element to
     * @param string $element_name name of the Element (for example, for
     *      a GroupoptionsElement this name would be groupoptions) to
     *      instantiate and add to the container
     */
    public function addContainer($label, $element_name)
    {
        if (!isset($this->containers[$label])) {
            $this->containers[$label] = [];
        }
        if (!empty($element_name[0]) && $element_name[0] == "<") {
            $this->containers[$label][] = $element_name;
        }  else {
            $element = $this->element($element_name);
            if ($element instanceof Element) {
                $this->containers[$label][] = $element;
            } else {
                throw new InvalidArgumentException("not valid element name");
            }
        }
    }
    /**
     * Checks if one the top, sub-top,smae, opposite, center or bottom contains
     * has anything in it or not.
     *
     * @param string $label one of labels top, sub-top, etc
     * @return bool whether that container is empty or not
     */
    public function emptyContainer($label)
    {
        return empty($this->containers[$label]);
    }
    /**
     * Draws one of the container labels onto the view using data provided by
     * the controller
     *
     * @param string $label container to draw
     * @param array $data field data from the controller for use during drawing
     */
    public function renderContainer($label, $data)
    {
        if (!$this->emptyContainer($label)) {
            foreach($this->containers[$label] as $element) {
                $data['CONTAINER_LABEL'] = $label;
                if (is_string($element)) {
                    echo $element;
                } else {
                    $element->render($data);
                }
            }
        }
    }
}
