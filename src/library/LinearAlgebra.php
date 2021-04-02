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
use seekquarry\yioop\library as L;

/**
 * Class useful for handling linear algebra operations on associative array
 * with key => value pairs where the value is a number.
 * We call such key => value array, term vectors, or more simply, vectors.
 *
 * @author Chris Pollett chris@pollett.org
 */
class LinearAlgebra
{
    /**
     * Adds two vectors component-wise. Treat empty components in either
     * array as zero entries. If either vector is in fact a constant
     * then add that constant to each entry
     * @param mixed $vector1 first term vector to add. If is a scalar
     *      then add that scalar to all components of other vector
     * @param mixed $vector2 second term vector to add.  If is a scalar
     *      then add that scalar to all components of other vector
     * @return array associative array corresponding to component-wise adding
     *      these two vectors.
     */
    public static function add($vector1, $vector2)
    {
        if (is_array($vector1) && is_array($vector2)) {
            foreach($vector2 as $coord2 => $value2) {
                $vector1[$coord2] = (empty($vector1[$coord2])) ? $value2 :
                    $vector1[$coord2] + $value2;
            }
        } else {
            $scalar = 0;
            if (is_array($vector1) && is_numeric($vector2)) {
                $scalar = $vector2;
            } else if (is_array($vector2) && is_numeric($vector1)) {
                $scalar = $vector1;
                $vector1 = $vector2;
            }
            foreach($vector1 as $coord => $value) {
                $vector1[$coord] = $value + $scalar;
            }
        }
        return $vector1;
    }
    /**
     * Calculates the distortion between two term vectors
     * 1. Check each word in first term vector to see if it exists in second.
     * If the word X of first term vector does not exist in second term vector,
     * square the score of word X and add to the $sum
     * and increase the number of $not_in_common words by one.
     * 2. In case the term X is common between first term vector and
     * second term vector, subtract first and second vectors weight for this
     * term, square the result and add to $sum.
     * 3. Then check each word in second term vector to see if it exists in
     * first, in case the word Y is not in the first term vector,
     * square the weight of word Y and add it to the $sum and increase
     * the number of $not_in_common words by one.
     * 4. At the end, calculate the distortion between sentence1 and
     * sentence2 by dividing $sum by $not_in_common
     * words.
     * @param array $vector1 (term => weight) pairs of the first
     *      sentence
     * @param array $vector2 (term => weight) pairs of the second
     *      sentence
     * @return float the distortion distance between the two sentences
     */
    public static function distortion($vector1, $vector2)
    {
        $sum = 0;
        $not_in_common = 0;
        $distortion = 0;
        foreach($vector1 as $key => $weight) {
            if (empty($vector2[$key])) {
                $sum += $weight * $weight;
                $not_in_common++;
            } else {
                $diff = $weight - $vector2[$key];
                $sum += $diff * $diff;
            }
        }
        foreach($vector2 as $key => $weight) {
            if (empty($vector1[$key])) {
                $sum += $weight * $weight;
                $not_in_common++;
            }
        }
        if ($not_in_common != 0) {
            $distortion = $sum / $not_in_common;
        }
        return $distortion;
    }
    /**
     * Computes the inner product (the dot product) of two term vectors
     *
     * @param array $vector1 first term vector in product
     * @param array $vector2 second term vector in product
     * @param number the sum of the product of the components of the two
     *  vectors
     */
    public static function dot($vector1, $vector2)
    {
        $v1 = (count($vector1) < count($vector2)) ? $vector1 : $vector2;
        $v2 = (count($vector1) < count($vector2)) ? $vector2 : $vector1;
        $sum = 0.;
        foreach($v1 as $coordinate => $value) {
            if (!empty($v2[$coordinate])) {
                $sum += $value * $v2[$coordinate];
            }
        }
        return $sum;
    }
    /**
     * Computes the L_k distance between two vectors. When k=2, this corresponds
     * to Euclidean length
     *
     * @param array $vector1 first term vector to determine distance between
     * @param array $vector2 second term vector to determine distance between
     * @param int $norm_power which norm, L_{$norm_power}, to use.
     *    $norm_power should be >= 1
     * @return number L_{$norm_power} distance between two vectors
     */
    public static function distance($vector1, $vector2, $norm_power = 2)
    {
        $vector = self::subtract($vector1, $vector2);
        return self::length($vector, $norm_power);
    }
    /**
     * Computes the L_k length of a vector. When k=2, this corresponds to
     * Euclidean length
     *
     * @param array $vector to compute the length of
     * @param int $norm_power which norm, L_{$norm_power}, to use.
     *    $norm_power should be >= 1
     * @return number length of vector with respect to desired metric.
     */
    public static function length($vector, $norm_power = 2)
    {
        $norm = 0.;
        foreach($vector as $weight) {
            $norm += pow(abs($weight), $norm_power);
        }
        $norm = pow($norm, 1./$norm_power);
        return $norm;
    }
    /**
     * Perform multiplication of either a scalar, vector, or a matrix and a
     * vector
     * @param array $scalar_vec_mat the scalar, vector or matrix to multiply
     *      against the vector
     * @param array $vector the vector to multiply against
     * @return array the new vector after it has been multiplied
     */
    public static function multiply($scalar_vec_mat, $vector)
    {
        if (is_numeric($scalar_vec_mat)) {
            foreach($vector as $coordinate => $value) {
                $vector[$coordinate] *= $scalar_vec_mat;
            }
            return $vector;
        } else if (is_array($scalar_vec_mat)) {
            if (is_array($scalar_vec_mat[0])) {
                $result = [];
                foreach ($vector as $i => $i_value) {
                    $result[$i] = 0;
                    foreach ($vector as $j => $j_value) {
                        if (!empty($scalar_vec_mat[$i][$j])) {
                            $result[$i] += $scalar_vec_mat[$i][$j] * $j_value;
                        }
                    }
                }
                return $result;
            } else {
                foreach ($scalar_vec_mat as $i => $value) {
                    $vector[$i] = (empty($vector[$i])) ?
                        0 : $vector[$i] * $value;
                }
                foreach ($vector as $i => $value) {
                    if (empty($scalar_vec_mat[$i])) {
                        $vector[$i] = 0;
                    }
                }
                return $vector;
            }
        }
        return false;
    }
    /**
     * Computes a unit length vector in the direction of the supplied vector
     *
     * @param array $vector vector to find unit vector for
     * @return array unit vector in desired direction
     *      (on zero input vector, returns zero output vector)
     */
    public static function normalize($vector)
    {
        $norm = sqrt(self::dot($vector, $vector));
        return ($norm == 0) ? $vector : self::multiply(1.0/$norm, $vector);
    }
    /**
     * Computes the cosine similarity between two vectors:
     *  ($vector1 * $vector2)/(||$vector1||*||$vector2||)
     * @param array $vector1 first term vector to compare
     * @param array $vector2 second term vector to compare
     * @return number a score measuring how similar these two vectors
     *  are with respect to cosine similarity
     */
    public static function similarity($vector1, $vector2)
    {
        $similarity = self::dot($vector1, $vector2) /
            (self::length($vector1) * self::length($vector2));
        return $similarity;
    }
    /**
     * Subtracts two vectors component-wise. Treat empty components in either
     * array as zero entries.  If either vector is in fact a constant
     * then subtract that constant from each entry
     * @param array $vector1 first term vector to subtract.  If is a scalar
     *      then subtract that scalar from all components of other vector
     * @param array $vector2 second term vector to subtract.  If is a scalar
     *      then subtract that scalar from all components of other vector
     * @return array associative array corresponding to component-wise
     *      subtracting these two vectors.
     */
    public static function subtract($vector1, $vector2)
    {
        if (is_array($vector1) && is_array($vector2)) {
            foreach($vector2 as $coord2 => $value2) {
                $vector1[$coord2] = (empty($vector1[$coord2])) ? -$value2 :
                    $vector1[$coord2] - $value2;
            }
        } else {
            $scalar = 0;
            if (is_array($vector1) && is_numeric($vector2)) {
                $scalar = $vector2;
            } else if (is_array($vector2) && is_numeric($vector1)) {
                $scalar = $vector1;
                $vector1 = $vector2;
            }
            foreach($vector1 as $coord => $value) {
                $vector1[$coord] = $value - $scalar;
            }
        }
        return $vector1;
    }
}
