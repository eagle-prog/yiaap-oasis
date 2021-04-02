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
namespace seekquarry\yioop\views\helpers;

use seekquarry\yioop\configs as C;
/**
 * Helper used to draw thumbnails for video sites
 *
 * @author Chris Pollett
 */
class VideourlHelper extends Helper
{
    /**
     * Used to a thumbnail for a video link.
     * @param string $url to  video site
     * @param string $data_url for thumbnail
     * @param boolean $open_in_tabs whether new links should be opened in
     *    tabs
     */
    public function render($url, $data_url, $open_in_tabs = false)
    {
        $url = htmlentities($url);
        ?><a class="video-link" href="<?= $url ?>" <?php
        if ($open_in_tabs) { ?>
            target="_blank" rel="noopener"<?php
        }?>><img
        class="thumb" src="<?= $data_url  ?>"
        alt="<?php e(tl('videourl_helper_videothumb')); ?>" />
        <img class="video-play" src="<?=C\SHORT_BASE_URL
            ?>resources/play.png" alt="" />
        </a>
        <?php
    }
}
