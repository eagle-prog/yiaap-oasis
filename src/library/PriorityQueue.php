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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/**
 * Loaded for crawlLog function
 */
require_once __DIR__ . "/Utility.php";
/**
 *
 * Code used to manage a memory efficient priority queue.
 * Weights for the queue must be flaots. The queue itself is
 * implemented using heaps
 *
 * @author Chris Pollett
 */
class PriorityQueue extends StringArray implements CrawlConstants
{
    /**
     * Number of values that can be stored in the priority queue
     * @var int
     */
    public $num_values;
    /**
     * Number of bytes needed to store a value associated with a weight
     * @var int
     */
    public $value_size;
    /**
     * Number of bytes needed to store a weight in the queue
     * @var int
     */
    public $weight_size = 4; //size of a float
    /**
     * Number of items that are currently stored in the queue
     * @var int
     */
    public $count;
    /**
     * When the polling the queue returns the least or most weighted value
     * @var string
     */
    public $min_or_max;
    /**
     * An object that implements the Notifier interface (for instance,
     * WebQueueArchive)
     * @var object
     */
    public $notifier; // who to call if move an item in queue
    /**
     * Name of a function to be called if an element in the priority has
     * moved because its weight was adjusted this allows other data structures
     * the ability to know where the element has moved to in the priority queue
     * @var string
     */
    public $adjust_weight_callback;
    /**
     * Makes a priority queue (implemented as an array heap) with the given
     * operating parameters
     *
     * @param string $fname filename to store the data associated with the queue
     * @param int $num_values number of values the queue can hold
     * @param int $value_size the size in a bytes of a value
     * @param string $min_or_max whether this priority queue return least or
     *  most weight values when polled
     * @param object $notifier object to call when a value changes in the queue
     * @param int $save_frequency how often the data in the queue should be
     *  save to disk. (It's default location is RAM)
     * @param string $adjust_weight_callback name of a function to be called
     *  if an element in the priority has moved because its weight was adjusted
     *  this allows other data structures the ability to know where the element
     *  has moved to in the priority queue
     */
    public function __construct($fname, $num_values, $value_size,
        $min_or_max, $notifier = null,
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY,
        $adjust_weight_callback = null)
    {
        $this->num_values = $num_values;
        $this->value_size = $value_size;
        $this->min_or_max = $min_or_max;
        $this->count = 0;
        $this->notifier = $notifier;
        $this->adjust_weight_callback =  $adjust_weight_callback;
        parent::__construct($fname, $num_values,
            $value_size + $this->weight_size, $save_frequency);
    }
    /**
     * Gets the data stored at the ith location in the priority queue
     *
     * @param int $i location to return data from
     * @return mixed array data if the value of $i is between 1 and count, false
     *     otherwise
     */
    public function peek($i = 1)
    {
        if ($i < 1 || $i > $this->count) {
            crawlLog("Peek Index $i not in Range [1, {$this->count}]");
            return false;
        }
        return $this->getRow($i);
    }
    /**
     * Removes and returns the ith element out of the Priority queue.
     * Since this is a priority queue the first element in the queue
     * will either be the min or max (depending on queue type) element
     * stored. If $i is not in range an error message is written to the log.
     * This operation also performs a check to see if the queue should be
     * saved to disk
     *
     * @param int $i element to get out of the queue
     * @return mixed array data if the value of $i is between 1 and count, false
     *     otherwise
     */
    public function poll($i = 1)
    {
        if ($i < 1 || $i > $this->count) {
            crawlLog("Index $i not in Range [1, {$this->count}]");
            return false;
        }
        $extreme = $this->peek($i);
        $last_entry = $this->getRow($this->count);
        $this->putRow($i, $last_entry);
        $this->count--;
        $this->percolateDown($i);
        $this->checkSave();
        return $extreme;
    }
    /**
     * Inserts a new item into the priority queue.
     *
     * @param string $data what to insert into the queue
     * @param int $weight how much the new data should be weighted
     * @return mixed index location in queue where item was stored if
     *     successful, otherwise false.
     */
    public function insert($data, $weight)
    {
        if ($this->count == $this->num_values) {
            return false;
        }
        $this->count++;
        $cur = $this->count;
        $this->putRow($cur, [$data, $weight]);
        $loc = $this->percolateUp($cur);
        return $loc;
    }
    /**
     * Add $delta to the $ith element in the priority queue and then adjusts
     * the queue to store the heap property
     *
     * @param int $i element whose weight should be adjusted
     * @param int $delta how much to change the weight by
     */
    public function adjustWeight($i, $delta)
    {
        if ( ($tmp = $this->peek($i)) === false) {
            crawlLog("Index $i not in queue adjust weight failed");
            return false;
        }
        list($data, $old_weight) = $tmp;
        if ($this->adjust_weight_callback) {
            $adjust_weight = $this->adjust_weight_callback;
            $new_weight = $adjust_weight($old_weight, $delta);
        } else {
            $new_weight = $old_weight + $delta;
        }
        $this->putRow($i, [$data, $new_weight]);
        if ($new_weight > $old_weight) {
            if ($this->min_or_max == self::MIN) {
                $this->percolateDown($i);
            } else {
                $this->percolateUp($i);
            }
        } else {
            if ($this->min_or_max == self::MAX) {
                $this->percolateDown($i);
            } else {
                $this->percolateUp($i);
            }
        }
    }
    /**
     * Pretty prints the contents of the queue viewed as an array.
     *
     */
    public function printContents()
    {
        for ($i = 1; $i <= $this->count; $i++) {
            $row = $this->peek($i);
            print "Entry: $i Value: ".$row[0]." Weight: ".$row[1]."\n";
        }
    }
    /**
     * Return the contents of the priority queue as an array of
     * value weight pairs.
     *
     * @return array contents of the queue
     */
    public function getContents()
    {
        $rows = [];
        for ($i = 1; $i <= $this->count; $i++) {
            $rows[] = $this->peek($i);
        }
        return $rows;
    }
    /**
     * Scales the weights of elements in the queue so that the sum fo the new
     * weights is $new_total
     *
     * This function is used periodically to prevent the queue from being
     * gummed up because all of the weights stored in it are too small.
     *
     * @param int $new_total what the new sum of weights of elements in the
     *     queue will be after normalization
     */
    public function normalize($new_total = C\NUM_URLS_QUEUE_RAM)
    {
        $count = $this->count;
        $total_weight = $this->totalWeight();
        if ($total_weight <= 0) {
            crawlLog(
                "Total queue weight was zero!! Doing uniform renormalization!");
        }
        for ($i = 1; $i <= $count; $i++) {
            $row = $this->getRow($i);
            if ($total_weight > 0) {
                $row[1] = floor(($new_total * $row[1])/$total_weight);
            } else {
                $row[1] = floor($new_total/$count);
            }
            $this->putRow($i, $row);
        }
    }
    /**
     * If the $ith element in the PriorityQueue violates the heap
     * property with its parent node (children should be of lower
     * priority than the parent), this function
     * tries modify the heap to restore the heap property.
     *
     * @param int $i node to consider in restoring the heap property
     * @return int final position $ith node ends up at
     */
    public function percolateUp($i)
    {
        if ($i <= 1) return $i;
        $start_row = $this->getRow($i);
        $parent = $i;
        while ($parent > 1) {
            $child = $parent;
            $parent = floor($parent/2);
            $row = $this->getRow($parent);
            if ($this->compare($row[1], $start_row[1]) < 0) {
                $this->putRow($child, $row);
            } else {
                $this->putRow($child, $start_row);
                return $child;
            }
        }
        $this->putRow(1, $start_row);
        return 1;
    }
    /**
     * If the ith element in the PriorityQueue violates the heap
     * property with some child node (children should be of lower
     * priority than the parent), this function
     * tries modify the heap to restore the heap property.
     *
     * @param int $i node to consider in restoring the heap property
     */
    public function percolateDown($i)
    {
        $start_row = $this->getRow($i);
        $count = $this->count;
        $parent = $i;
        $child = 2*$parent;
        while ($child <= $count) {
            $left_child_row = $this->getRow($child);
            if ($child < $count) { // this 'if' checks if there is a right child
                $right_child_row = $this->getRow($child + 1);
                if ($this->compare(
                    $left_child_row[1], $right_child_row[1]) < 0) {
                    $child++;
                }
            }
            $child_row = $this->getRow($child);
            if ($this->compare($start_row[1], $child_row[1]) < 0) {
                $this->putRow($parent, $child_row);
            } else {
                $this->putRow($parent, $start_row);
                return;
            }
            $parent = $child;
            $child = 2 * $parent;
        }
        $this->putRow($parent, $start_row);
    }
    /**
     * Computes the difference of the two values $value1 and $value2
     *
     * Which is subtracted from which is determined by whether this is
     * a min_or_max priority queue
     *
     * @param int $value1 a value to take the difference between
     * @param int $value2 the other value
     * @return int the differences
     */
    public function compare($value1, $value2)
    {
      if ($this->min_or_max == self::MIN) {
         return $value2 - $value1;
      } else {
         return $value1 - $value2;
      }
    }
    /**
     * Gets the ith element of the PriorityQueue viewed as an array
     *
     * @param int $i element to get
     * @return array value stored in queue together with its weight as a two
     *     element array
     */
    public function getRow($i)
    {
        $value_size = $this->value_size;
        $weight_size = $this->weight_size;
        $row = $this->get($i);
        $value = substr($row, 0, $value_size);
        $pre_weight = substr($row, $value_size, $weight_size);
        $weight = unpackInt($pre_weight);
        return [$value, $weight];
    }
    /**
     * Add data to the $i row of the priority queue viewed as an array
     * Calls the notifier associated with this queue about the change
     * in data's location
     *
     * @param int $i location to add data
     * @param array $row data to add (a two element array in the form
     *     key, int value).
     */
    public function putRow($i, $row)
    {
        $raw_data = $row[0] . packInt($row[1]);
        $this->put($i, $raw_data);
        if ($this->notifier != null) {
            $this->notifier->notify($i, $row);
        }
    }
    /**
     * Computes and returns the weight of all items in prority queue
     *
     * @return int weight of all items stored in the priority queue
     */
    public function totalWeight()
    {
        $count = $this->count;
        $total_weight = 0;
        for ($i = 1; $i <= $count; $i++) {
            $row = $this->getRow($i);
            $total_weight += $row[1];
        }
        return $total_weight;
    }


}
