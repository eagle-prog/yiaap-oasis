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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;

/**
 * Element responsible for draw the feeds a user is subscribed to
 *
 * @author Chris Pollett
 */
class GroupfeedElement extends Element implements CrawlConstants
{
    /**
     * Draws the Feeds for the Various Groups a User is a associated with.
     *
     * @param array $data feed items should be prepared by the controller
     *     and stored in the $data['PAGES'] variable.
     *     makes use of the CSRF token for anti CSRF attacks
     */
    public function render($data)
    {
        $logged_in = !empty($data["ADMIN"]);
        $is_status = isset($data['STATUS']);
        $is_api = !empty($data['API']);
        $token_string = ($logged_in) ? C\CSRF_TOKEN . "=".
            $data[C\CSRF_TOKEN] : "";
        $base_query = B\feedsUrl("", "", true, $data['CONTROLLER']) .
            $token_string;
        $paging_query = $data['PAGING_QUERY'] . $token_string;
        if (!$is_status && !$is_api) {?>
            <div class="small-margin-current-activity no-min-height" ><?php
            if ($logged_in) {
                if (isset($data['SUBSCRIBE_LINK'])) {
                    ?><div class="float-same group-request-add"><?php
                    if ($data['SUBSCRIBE_LINK'] == C\PUBLIC_JOIN) {
                        e('[<a href="' . $paging_query . '&amp;arg=addgroup">'.
                        tl('groupfeed_element_add_group').
                        '</a>]');
                    } else if ($data['SUBSCRIBE_LINK'] != C\NO_JOIN) {
                        e('[<a href="' . $paging_query . '&amp;arg=addgroup">'.
                        tl('groupfeed_element_request_add').
                        '</a>]');
                    }
                    ?></div>
                    <?php
                }
            }
            if (!$_SERVER["MOBILE"] &&
                isset($data["AD_LOCATION"]) &&
                in_array($data["AD_LOCATION"], ['side', 'both'] ) ) { ?>
                <div class="side-adscript"><?=$data['SIDE_ADSCRIPT'] ?></div>
                <?php
            }
        }
        $data['TOTAL_ROWS'] = empty($data['TOTAL_ROWS']) ? 0 :
            $data['TOTAL_ROWS'];
        if (isset($data['MODE']) && $data['MODE'] == 'grouped') {
            $this->renderGroupedView($paging_query, $data);
        } else {
            $data['MODE'] = "ungrouped";
            $this->renderUngroupedView($logged_in, $base_query,
                $paging_query, $data);
        }
        if (!$is_api && !$is_status && $logged_in) {
            $thread_type = intval($data['JUST_THREAD'] ?? 0); ?>
            </div><?php
            $this->renderScripts($data, true);
        }
    }
    /**
     * Used to draw group feeds items when we are grouping feeds items by group
     *
     * @param string $paging_query stem for all links
     *      drawn in view
     * @param array &$data fields used to draw the queue
     */
    public function renderGroupedView($paging_query, &$data)
    {
        $token_string = (!empty($data['ADMIN'])) ? C\CSRF_TOKEN . "=".
            $data[C\CSRF_TOKEN] : "";
        if (!empty($data["GROUP_FILTER"])) {
            $paging_query .= "&amp;group_filter=" . $data["GROUP_FILTER"];
        }
        if (!empty($data["GROUP_SORT"])) {
            $paging_query .= "&amp;group_sort=" . $data["GROUP_SORT"];
        }
        ?>
        <div class="float-opposite">
        <form>
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
            value="<?= $data[C\CSRF_TOKEN] ?>" />
        <b><label for='group-filter'><?=tl('groupfeed_element_filter')
        ?></label></b>
        <input class="narrow-field" type="search" id="group-filter"
            name='group_filter' value='<?=
            $data["GROUP_FILTER"] ?>'/>
        <b><label for='group-sort'><?=tl('groupfeed_element_sort')?></label></b>
        <?= $this->view->helper('options')->render("group-sort", "group_sort",
            $data['group_sorts'], $data["GROUP_SORT"]) ?>
        <button type="submit" class="button-box" ><?=
            tl('groupfeed_element_go')?></button>
        </form>
        </div>
        <h2><?=
            tl('groupfeed_element_my_groups') ?></h2><?php
        foreach ($data['GROUPS'] as $group) {
            e("<div class=\"access-result\">" .
                "<div><b>" .
                "<a href=\"". htmlentities(
                B\feedsUrl("group", $group['GROUP_ID'], true,
                $data['CONTROLLER'])) . $token_string . "&amp;v=grouped" .
                "\" rel=\"nofollow\">" .
                $group['GROUP_NAME'] . "</a> " .
                "[<a href=\"".htmlentities(
                B\wikiUrl("", true, $data['CONTROLLER'],
                    $group['GROUP_ID'])) . $token_string .
                "\">" . tl('groupfeed_element_group_wiki') . "</a>] " .
                "(" . tl('groupfeed_element_group_stats',
                        $group['NUM_POSTS'],
                        $group['NUM_THREADS']) . ")</b>" .
                "</div>" .
                "<div class=\"slight-pad\">" .
                "<b>" . tl('groupfeed_element_last_post')
                . "</b> ");
                if ($group['THREAD_ID'] >= 0 || $group['NUM_THREADS'] > 0) {
                    e("<a href=\"" . B\feedsUrl("thread", $group['THREAD_ID'],
                        true, $data['CONTROLLER']) . $token_string . "\">" .
                        $group['ITEM_TITLE'] . "</a>");
                } else {
                    e($group['ITEM_TITLE']);
                }
                e("</div></div>");
            $data['TOTAL_ROWS'] = $data['NUM_GROUPS'];
        }
        if ($data['NUM_GROUPS'] > $data['RESULTS_PER_PAGE']) {
            $this->view->helper("pagination")->render(
                $paging_query, $data['LIMIT'], $data['RESULTS_PER_PAGE'],
                $data['TOTAL_ROWS']);
        }
    }
    /**
     * Used to draw feed items as a combined thread of all groups
     *
     * @param bool $logged_in where or not the session is of a logged in user
     * @param string $base_query url that serves as the stem for all links
     *      drawn in view
     * @param string $paging_query base_query concatenated with limit and num
     * @param array &$data fields used to draw the queue
     * @return array $page last feed item processed
     */
    public function renderUngroupedView($logged_in, $base_query, $paging_query,
        &$data)
    {
        $is_api = !empty($data['API']);
        $is_status = !empty($data['STATUS']);
        $open_in_tabs = $data['OPEN_IN_TABS'];
        $time = time();
        $can_comment = [C\GROUP_READ_COMMENT, C\GROUP_READ_WRITE,
            C\GROUP_READ_WIKI];
        $start_thread = [C\GROUP_READ_WRITE, C\GROUP_READ_WIKI];
        if (!isset($data['GROUP_STATUS']) ||
            $data['GROUP_STATUS'] != C\ACTIVE_STATUS) {
            $can_comment = [];
            $start_thread = [];
        }
        $token_string = ($logged_in) ? C\CSRF_TOKEN . "=".
            $data[C\CSRF_TOKEN] : "";
        $no_follow = ($token_string) ? " rel='nofollow' " : "";
        $page = [];
        $member_access = (!empty($data['WIKI_MEMBER_ACCESS'])) ?
            $data['WIKI_MEMBER_ACCESS'] : (
            (empty($data['PAGES'][0]["MEMBER_ACCESS"])) ?
            C\NOT_MEMBER_STATUS : $data['PAGES'][0]["MEMBER_ACCESS"]);
        $parent_id = (!empty($data['WIKI_PARENT_ID'])) ?
            $data['WIKI_PARENT_ID'] : (
            (empty($data['PAGES'][0]["PARENT_ID"])) ?
            -1 : $data['PAGES'][0]["PARENT_ID"]);
        $group_id = (!empty($data['WIKI_GROUP_ID'])) ?
            $data['WIKI_GROUP_ID'] : (
            (empty($data['PAGES'][0]["GROUP_ID"])) ?
            -1 : $data['PAGES'][0]["GROUP_ID"]);
        $is_thread = !empty($data['JUST_THREAD']);
        $is_group = !empty($data['JUST_GROUP_ID']);
        $is_user = !empty($data['JUST_USER_ID']);
        $is_page_with_comments = ($data['ELEMENT'] == 'wiki');
        $is_all_feed = !$is_thread && !$is_group && !$is_page_with_comments &&
            !$is_user;
        if ($is_all_feed && !$is_api && !$is_status) {?>
            <h2 class='feed-heading'><?=
            tl('groupfeed_element_combined_discussions') ?></h2><?php
        }
        if (!$is_api && !$is_status &&
            in_array($member_access, $can_comment)) {
            if ($is_group && in_array($member_access, $start_thread)) {
                $this->drawStartThreadForm($data['JUST_GROUP_ID'], $data);
            } else if ($is_page_with_comments) {
                $data['page_type'] = 'page_and_feedback';
                $this->drawCommentForm($parent_id, $group_id, $data);
            }
        }
        if (!$is_api && !$is_status && !empty($data['NO_POSTS_YET'])) {
            if (isset($data['NO_POSTS_START_THREAD'])) {
                //no read case where no posts yet
                $this->drawStartThreadForm($data['JUST_GROUP_ID'], $data);
            }
            if ($is_thread && !$is_page_with_comments) {
                $this->drawCommentForm($parent_id, $group_id, $data);
            }
        }
        if (!$is_api && !$is_status) {?>
            <div id='results-container' data-time="<?=time() ?>"><?php
            if ($data['LIMIT'] > 0 && !$is_page_with_comments) {
                $this->view->helper("pagination")->singleButtonPagination(
                    $paging_query, $data['LIMIT'], $data['RESULTS_PER_PAGE'],
                    $data['TOTAL_ROWS'], false, $logged_in, true);
            }
        }
        if (!$is_api) {?>
            <div class="result-batch" data-time='<?=time() ?>' ><?php
        }
        if (isset($data['NO_POSTS_IN_THREAD']) && $data['JUST_THREAD'] >= 0) {
            ?>
            <div class="button-group-result red medium-font" ><?=
            tl('groupfeed_element_thread_no_exist') ?></div>
            <?php
        }
        $first_page = !$is_page_with_comments && $data['LIMIT'] == 0;
        $old_pubdate = -1;
        $now = time();
        foreach ($data['PAGES'] as $page) {
            $pub_date = $page['PUBDATE'];
            $pub_date_diff = abs($page['PUBDATE'] - $old_pubdate);
            $pub_date_age = $now - $page['PUBDATE'];
            $pub_data_change = ($pub_date_age < C\ONE_HOUR &&
                $pub_date_diff > 5 * C\ONE_MINUTE) || ($pub_date_age < C\ONE_DAY
                && $pub_date_diff > C\ONE_HOUR) || ($pub_date_diff > C\ONE_DAY);
            $old_pubdate = ($pub_data_change) ? $pub_date : $old_pubdate;
            $pub_date = $this->view->helper("feeds")->getPubdateString(
                $time, $pub_date, !$is_thread);
            $edit_date = false;
            if (isset($page['EDIT_DATE']) && $page['EDIT_DATE'] &&
                $page['EDIT_DATE'] != $page['PUBDATE']) {
                $edit_date = $this->view->helper("feeds")->getPubdateString(
                    $time, $page['EDIT_DATE'], !$is_thread);
            }
            $encode_source = urlencode(urlencode($page[self::SOURCE_NAME]));
            $group_result = "small-group-result";
            $has_voting = $logged_in && isset($page["VOTE_ACCESS"]) &&
                in_array($page["VOTE_ACCESS"], [C\UP_DOWN_VOTING_GROUP,
                C\UP_VOTING_GROUP]);
            $has_up_voting = $has_voting &&
                ($page["VOTE_ACCESS"] == C\UP_VOTING_GROUP);
            $has_up_down_voting = $has_voting &&
                ($page["VOTE_ACCESS"] == C\UP_DOWN_VOTING_GROUP);
            if ($pub_data_change) { ?>
                <div class='gray align-opposite' ><?=$pub_date?></div>
                <?php
            }
            ?>
            <div class='<?="$group_result"?>'>
            <span class="none"><hr /></span>
            <?php
            $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] : "";
            $edit_list = ($page['ID'] == $page['PARENT_ID']) ?
                $start_thread : $can_comment;
            if (in_array($page["MEMBER_ACCESS"], $edit_list) &&
                !$is_group && isset($_SESSION['USER_ID']) &&
                (($page['USER_ID'] != "" &&
                $page['USER_ID'] == $_SESSION['USER_ID']) ||
                $_SESSION['USER_ID'] == C\ROOT_ID || (!empty($page['OWNER_ID'])
                && $_SESSION['USER_ID'] == $page['OWNER_ID'])) &&
                isset($page['TYPE']) && $page['TYPE'] != C\WIKI_GROUP_ITEM) {
                ?>
                <div class="float-opposite button-container"><?php
                if ($has_voting) {
                    $up_vote = $paging_query . "&amp;post_id=".$page['ID'] .
                        "&amp;arg=upvote&amp;group_id=".$page['GROUP_ID'];
                    $down_vote = $paging_query ."&amp;post_id=".$page['ID'].
                        "&amp;arg=downvote&amp;group_id=".$page['GROUP_ID'];
                    if ($has_up_down_voting) {
                        e(" <span class='gray bigger-font'>(".
                            "<a class='vote-button'".
                            "href='$up_vote'><span role='img' aria-label='".
                            tl('groupfeed_element_up_vote') .
                            "'>+</span><span class='none'>" .
                            tl('groupfeed_element_up_vote') .
                            "</span></a>{$page['UPS']}/<a ".
                            "class='vote-button' href='$down_vote'><span ".
                            "role='img' aria-label='" .
                            tl('groupfeed_element_down_vote') ."'>-</span>".
                            "<span class='none'>" .
                            tl('groupfeed_element_down_vote') . "</span></a>".
                            "{$page['DOWNS']})</span>");
                    } else if ($has_up_voting) {
                        e(" <span class='gray bigger-font'>(".
                            "<a class='vote-button' ".
                            "href='$up_vote'><span role='img' aria-label='".
                            tl('groupfeed_element_up_vote') .
                            "'>+</span><span class='none'>" .
                            tl('groupfeed_element_up_vote') .
                            "</span></a>{$page['UPS']})</span>");
                    }
                }
                if ($is_all_feed && in_array($page["MEMBER_ACCESS"],
                    $can_comment)) { ?>
                    <script>
                    document.write("<a class='action-anchor-button' "+
                        "href='javascript:commentForm(" + '<?=
                        "{$page['ID']}, {$page['PARENT_ID']}, " .
                        "{$page['GROUP_ID']}" ?>)' + "'" +
                        '><span role="img" aria-label="<?=
                        tl('groupfeed_element_comment') ?>">💬' +
                        '</span></a>');
                    </script><?php
                }
                if (!isset($page['NO_EDIT'])) {
                    if ($is_api || $is_status) { ?>
                        <a class="action-button-link"
                            href="javascript:updatePostForm(<?=
                            $page['ID']?>)"><span role="img" aria-label="<?=
                            tl('groupfeed_element_edit')
                            ?>"> ✏️</a><?php
                    } else {?>
                        <script>
                        document.write('<a class="action-anchor-button" ' +
                            'href="javascript:updatePostForm(<?=
                            $page['ID']?>)"><span role="img" aria-label="<?=
                            tl('groupfeed_element_edit')
                            ?>"> ✏️</a>');
                        </script><?php
                    }
                }
                $delete_url = (empty($data['WIKI_FEED_BASE'])) ?
                    $paging_query : $data['WIKI_FEED_BASE'];
                ?>
                <a class="action-anchor-button"
                    onclick="return confirm('<?=
                    tl('groupfeed_element_confirm_delete') ?>');"
                    href="<?= $delete_url .'&amp;arg=deletepost&amp;'.
                    "post_id=" . $page['ID']
                    ?>"><span role="img" aria-label="<?=
                    tl('groupfeed_element_delete') ?>">🗑<span class="none"><?=
                    tl('groupfeed_element_delete') ?></span></span></a>
                </div><?php
            }
            $title_class = "";
            if (!empty($data['DISCUSS_THREAD'])) {
                $title_class = ' class="none" ';
            }
            $feed_user_icon = "small-feed-user-icon";
            $feed_item_body = "small-feed-item-body";
            $feed_item_body_first = (($is_thread && $first_page) ||
                !$is_thread) ? " style='padding-top:0' " :"";
            ?>
            <div id='result-<?= $page['ID'] ?>' >
            <div class="float-same center" >
            <img class="<?= $feed_user_icon?>" src="<?=$page['USER_ICON'] ?>"
                alt="<?=tl('groupfeed_element_usericon') ?>"/><br />
            <a class="feed-user-link echo-link" <?= $no_follow ?>
                href="<?= $this->formatHref(B\feedsUrl("user", $page['USER_ID'],
                    true, $data['CONTROLLER']) . $token_string);
                ?>" ><?=$page['USER_NAME'] ?></a>
            </div>
            <div class="<?=$feed_item_body ?>" <?=$feed_item_body_first
             ?> ><?php
            if (!$is_thread || $first_page) {?>
                <h2><a href="<?= $this->formatHref(B\feedsUrl('thread',
                    $page['PARENT_ID'], true, $data['CONTROLLER']) .
                    $token_string) ?>" <?= $no_follow ?> <?=$title_class ?>
                    id='title<?=$page['ID']?>' <?php
                    if ($open_in_tabs) {
                        ?> target="_blank" rel="noopener"<?php
                    }
                    ?>><?= $page[self::TITLE] ?></a><?php
                    if (!$is_page_with_comments) {
                        if (isset($page['NUM_POSTS'])) {
                            e(" (");
                            e(tl('groupfeed_element_num_posts',
                                $page['NUM_POSTS']));
                            if (!$_SERVER["MOBILE"] &&
                                $data['RESULTS_PER_PAGE'] <
                                $page['NUM_POSTS']) {
                                $thread_query = htmlentities(
                                    B\feedsUrl("thread",
                                    $page['PARENT_ID'], true,
                                    $data['CONTROLLER']));
                                $this->view->helper("pagination")->render(
                                    $thread_query . $token_string, 0,
                                    $data['RESULTS_PER_PAGE'],
                                    $page['NUM_POSTS'], true, $logged_in);
                            }
                            e(", " . tl('groupfeed_element_num_views',
                                $page['NUM_VIEWS']));
                            e(") ");
                        }
                        e(".");
                        if (!$is_group && !$is_thread) { ?>
                            <b><a class="gray-link" <?= $no_follow ?> href="<?=
                                $this->formatHref(
                                B\feedsUrl('group', $page['GROUP_ID'], true,
                                $data['CONTROLLER']) . $token_string) ?>" ><?=
                                $page[self::SOURCE_NAME] ?></a></b><?php
                        }
                    } ?>
                </h2><?php
                $first_page = false;
            } else { ?>
                <div id="hidden-title<?=$page['ID']?>" class="none"><?=
                    $page[self::TITLE] ?></div><?php
            }
            if (!$is_group) {
                $description = $page[self::DESCRIPTION] ?? "";?>
                <div id='description<?= $page['ID']?>'><?php
                    e($description);
                    if ($edit_date) {
                        e("(<b>". tl('groupfeed_element_edited', $edit_date).
                        "</b>)");
                    }?>
                </div>
                <?php
                if (!isset($page['NO_EDIT']) &&
                    isset($page['OLD_DESCRIPTION'])){
                    ?>
                    <div id='old-description<?= $page['ID'] ?>'
                        class='none'><?=$page['OLD_DESCRIPTION'] ?></div>
                    <?php
                }
            }
            ?>
            </div>
            </div>
            <div id='<?= $page["ID"] ?>'></div>
            </div>
            <?php
        } //end foreach
        if (!$is_api) {?>
            </div><?php
        }
        if (!$is_api && !$is_status) {
            if ($is_all_feed) {
                $paging_query .= "&amp;v=ungrouped";
            }
            $this->view->helper("pagination")->singleButtonPagination(
                $paging_query, $data['LIMIT'], $data['RESULTS_PER_PAGE'],
                $data['TOTAL_ROWS'], false, $logged_in);?>
            </div><?php
        }
        if (!$is_api && !$is_status) {
            if ($is_thread && $logged_in && !$is_status &&
                !$is_page_with_comments && isset($data['GROUP_STATUS']) &&
                $data['GROUP_STATUS'] == C\ACTIVE_STATUS) {
                $this->drawCommentForm($data['JUST_THREAD'], $data["GROUP_ID"],
                    $data);
            }
        }
    }
    /**
     * Used to draw the form to start a new thread in a group
     * @param int $group_id of group to draw form forr
     * @param array $data containing other field needed to draw the form
     */
    private function drawStartThreadForm($group_id, $data)
    {
        $just_fields = ["LIMIT" => "limit", "RESULTS_PER_PAGE" => "num",
            "JUST_GROUP_ID" => "just_group_id", 'MODE' => 'v'];
        $hidden_form = "\n";
        foreach ($just_fields as $field => $form_field) {
            if (isset($data[$field])) {
                $hidden_form .= "<input type=\"hidden\" " .
                    "name=\"$form_field\" value=\"{$data[$field]}\" ".
                    "/>\n";
            }
        }
        ?>
        <div class='button-group-result'>
        <script>
        document.write('<button class="button-box" onclick=' + "'" +
            "toggleDisplay(\"start-thread\")'><?=
            tl('groupfeed_element_start_thread') ?></button>");
        </script>
        <noscript>
        <h2><b><?= tl('groupfeed_element_start_thread') ?></b></h2>
        </noscript>
        <div id='start-thread' class="light-gray-box top-bottom-margin">
            <br />
            <form method="post" action="<?=C\SHORT_BASE_URL?>" ><?=
                $hidden_form ?>
            <input type="hidden" name="c" value="<?=
                $data['CONTROLLER'] ?>" />
            <input type="hidden" name="a" value="groupFeeds" />
            <input type="hidden" name="arg" value="newthread" />
            <input type="hidden" name="group_id" value="<?=
                $group_id ?>" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                value="<?= $data[C\CSRF_TOKEN] ?>" />
            <p><b><label for="title-start-thread" ><?=
                tl("groupfeed_element_subject")
            ?></label></b></p>
            <p><input type="text" id="title-start-thread"
                name="title" value=""  maxlength="<? C\TITLE_LEN ?>"
                class="wide-field"/></p>
            <p><b><label for="description-start-thread" ><?=
                tl("groupfeed_element_post")
            ?></label></b></p>
            <textarea class="short-text-area"
                id="description-start-thread" name="description"
                data-buttons="all,!wikibtn-search,!wikibtn-heading,<?=
                ""?>!wikibtn-slide" ></textarea>
            <div class="upload-gray-box center black">
            <input type="file" id="file-start-thread"
                name="file_start-thread"
                class="none" multiple="multiple" /><?=
                tl('groupfeed_element_drag_textarea'); ?>
            <a href="javascript:elt('file-start-thread').click()">
            <?= tl('groupfeed_element_click_textarea'); ?></a>
            </div>
            <div>
            <button class="button-box float-opposite" type="submit"><?=
                tl("groupfeed_element_save")
            ?></button>
            <br><br>
            </div>
            </form>
        <script>
        if (typeof yioop_post_scripts !== 'object' ) {
            yioop_post_scripts = {};
        }
        yioop_post_scripts['start-thread'] = function()
        {
            initializeFileHandler('description-start-thread',
                "file-start-thread",
                <?= min(L\metricToInt(ini_get('upload_max_filesize')),
                L\metricToInt(ini_get('post_max_size')))
                ?>, "textarea", null, true);
            editorize('description-start-thread');
            setDisplay('start-thread', false);
        }
        </script>
        </div>
        </div>
        <?php
    }
    /**
     * Used to draw the add comment form to add a comment to an
     * existing thread
     *
     * @param int $thread_id of thread to draw form for
     * @param int $group_id of group to draw form for
     * @param array $data containing other field needed to draw the form
     */
    private function drawCommentForm($thread_id, $group_id, $data)
    {
        $just_fields = ["LIMIT" => "limit", "RESULTS_PER_PAGE" => "num",
            "JUST_THREAD" => 'just_thread', 'page_type' => 'page_type',
            "PAGE_NAME" => 'page_name', 'MODE' => 'v'
        ];
        $hidden_form = "\n";
        foreach ($just_fields as $field => $form_field) {
            if (isset($data[$field])) {
                $hidden_form .= "<input type=\"hidden\" ".
                    "name=\"$form_field\" value=\"{$data[$field]}\" />\n";
            }
        } ?>
        <div class="button-group-result">
        <script>
        document.write('<button class="button-box" onclick=' + "'" +
            "toggleDisplay(\"add-comment\")'><label " +
            "for=\"comment-add-comment\" ><?=
            tl('groupfeed_element_comment') ?></label></button>");
        </script>
        <noscript>
        <h2><label for="comment-add-comment" ><b><?=
            tl('groupfeed_element_comment') ?></b></label></h2>
        </noscript>
        <div id="add-comment">
        <form method="post" action="<?=C\SHORT_BASE_URL?>"><?= $hidden_form ?>
        <input type="hidden" name="c" value="<?=
            $data['CONTROLLER'] ?>" />
        <input type="hidden" name="a" value="groupFeeds" />
        <input type="hidden" name="arg" value="addcomment" />
        <input type="hidden" name="parent_id" value="<?=$thread_id; ?>" />
        <input type="hidden" name="group_id" value="<?= $group_id?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
            value="<?= $data[C\CSRF_TOKEN] ?>" />
        <textarea class="short-text-area"
            id="comment-add-comment" name="description"
            data-buttons="all,!wikibtn-search,!wikibtn-heading,
            !wikibtn-slide" ></textarea>
        <script>
        document.write('<div class="upload-gray-box center black">' +
            '<input type="file" id="file-add-comment" ' +
            'name="file_add-comment" class="none" multiple="multiple" />' +
            '<?= tl('groupfeed_element_drag_textarea') ?>' +
            '<a href="javascript:elt(\'file-add-comment\').click()"><?=
            tl('groupfeed_element_click_textarea') ?></a></div>');
        </script>
        <button class="button-box float-opposite"
            type="submit"><?= tl("groupfeed_element_save") ?>
        </button><div>&nbsp;<br /><br /></div>
        </form>
        </div>
        <script>
        if (typeof yioop_post_scripts !== 'object' ) {
            yioop_post_scripts = {};
        }
        yioop_post_scripts['comment-add-comment'] = function()
        {
            var comment_id = 'comment-add-comment';
            initializeFileHandler(comment_id, "file-add-comment",
                <?= min(L\metricToInt(ini_get('upload_max_filesize')),
                L\metricToInt(ini_get('post_max_size')))
                ?>, "textarea", null, true);
            editorize(comment_id);
            setDisplay('add-comment', false);
        }
        </script>
        </div>
        <?php
    }
    /**
     * Used to slightly clean up hypertext links before drawing them
     * (get rid of empty queries, avoid double encoding)
     *
     * @param string $url to clean up
     * @return string cleaned url
     */
    private function formatHref($url)
    {
        return rtrim(html_entity_decode($url), '?');
    }
    /**
     * Used to render the dropdown for paths within the top group feed
     * drop down
     *
     * @param array $data set up in controller and SocialComponent with
     *      data fields view and this element are supposed to render
     * @param array $feed_array (url => path) options
     * @param string $aux_url url of current group wiki in the case of a group
     *      feed. Url of all groups in the case of user feed.
     * @param string $groups_url link to the all feeds feed for a given user
     * @param string $group_name name of current groupfeed
     * @param string $render_type if "user" then prints feed info appropriate
     *      for a single use, if "just_group_and_thread" doesn't print group
     *      or user specific info, otherwise defaults to current
     *      group specific info
     * @param bool $as_list if should render as an unordered-list rather than
     *      a dropdown
     */
    public function renderPath($data, $feed_array, $aux_url,
        $groups_url, $group_name, $render_type = "", $as_list = false)
    {
        $options = [];
        $selected_url = "";
        if ($render_type == "just_group_and_thread") {
            $options = [tl('groupfeed_element_feedplaces') => ""];
            $options[$groups_url . "&amp;v=ungrouped"] =
                tl('groupfeed_element_combined_discussions');
            $options[$groups_url] = tl('groupfeed_element_mygroups');
            $selected_url = ($data['VIEW_MODE'] == 'ungrouped') ?
                $groups_url . "&amp;v=ungrouped" : $groups_url;
        } else  if ($render_type == "user") {
            $options = [tl('groupfeed_element_userplaces') => ""];
            foreach ($feed_array as $url => $name) {
                $selected_url = $url;
                break;
            }
            $options = array_merge($options, $feed_array);
            $options[$aux_url . "&amp;v=ungrouped"] =
                tl('groupfeed_element_combined_discussions');
            $options[$aux_url] = tl('groupfeed_element_mygroups');
        } else {
            $options = [tl('groupfeed_element_groupplaces', $group_name) => ""];
            foreach ($feed_array as $url => $name) {
                $selected_url = $url;
                break;
            }
            $options = array_merge($options, $feed_array);
            if ($aux_url) {
                $options[$aux_url] = tl('groupfeed_element_wiki_name',
                    $group_name);
            }
            $options[$groups_url . "&amp;v=ungrouped"] =
                tl('groupfeed_element_combined_discussions');
            $options[$groups_url] = tl('groupfeed_element_mygroups');
        }
        if (!empty($data['RECENT_THREADS'])) {
            $token_string = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
            $options[tl('groupfeed_element_recent_threads')] = "";
            foreach ($data['RECENT_THREADS'] as $thread_name => $url) {
                $options[$url . $token_string] = $thread_name;
            }
        }
        if (!empty($data['RECENT_GROUPS'])) {
            $token_string = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
            $add_options[tl('groupfeed_element_recent_groups')] = "";
            $found_new = false;
            foreach ($data['RECENT_GROUPS'] as $group_name => $url) {
                $out_token = (strstr($url, C\CSRF_TOKEN) === false) ?
                    $token_string : "";
                if (!empty($out_token) && (strstr($url, "?") === false)) {
                    $url .= "?";
                }
                if (empty($options[$url . $out_token])) {
                    $add_options[$url . $out_token] =
                        $group_name ;
                    $found_new = true;
                }
            }
            if ($found_new) {
                $options = array_merge($options, $add_options);
            }
        }
        $this->view->helper('options')->renderLinkDropDown('feed-path',
            $options, $selected_url, $selected_url, $as_list);
    }
    /**
     * Used to render the Javascript that appears at the non-status updating
     * portion of the footer of this element.
     *
     * @param array $data contains arguments needs to draw urls correctly.
     */
    public function renderScripts($data, $with_status_update = false)
    {
        $data['TOTAL_ROWS'] = empty($data['TOTAL_ROWS']) ? 0 :
            $data['TOTAL_ROWS'];
        if ($data['LIMIT'] + $data['RESULTS_PER_PAGE'] == $data['TOTAL_ROWS']) {
            $data['LIMIT'] += $data['RESULTS_PER_PAGE'] - 1;
        }
        $paging_query = $data['PAGING_QUERY'];
        $token_string = (!empty($data['ADMIN'])) ? C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        $limit_hidden = "";
        $delim = "";
        if (isset($data['LIMIT'])) {
            $paging_query .= "limit=".$data['LIMIT'];
            $delim = "&";
        }
        $num_hidden = "";
        if (isset($data['RESULTS_PER_PAGE'])) {
            $paging_query .= "{$delim}num=".$data['RESULTS_PER_PAGE'];
            $delim = "&";
        }
        $just_fields = ["LIMIT" => "limit", "RESULTS_PER_PAGE" => "num",
            "JUST_THREAD" => 'just_thread', "JUST_USER_ID" => "just_user_id",
            "JUST_GROUP_ID" => "just_group_id", 'page_type' => 'page_type',
            "PAGE_NAME" => 'page_name', 'MODE' => 'v'];
        $hidden_form = "\n";
        foreach ($just_fields as $field => $form_field) {
            if (isset($data[$field])) {
                $hidden_form .= "'<input type=\"hidden\" ".
                    "name=\"$form_field\" value=\"{$data[$field]}\" />' +\n";
            }
        }
        $this->view->helper("fileupload")->setupFileUploadParams();
        $hide_title = "";
        if (!empty($data['DISCUSS_THREAD'])) {
            $hide_title = ' class="none" ';
        }
        ?>
        <script><?php
            $clear = ($_SERVER["MOBILE"]) ? " clear" : "";
            $drag_above_text = tl('groupfeed_element_drag_textarea');
            $click_link_text = tl('groupfeed_element_click_textarea');
        ?>
        if (typeof feed_update_id === 'undefined') {
            let feed_update_id = null;
        }
        function commentForm(id, parent_id, group_id)
        {
            tmp = '<div class="post<?= $clear ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length);
            if (start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form method="post" action="<?=C\SHORT_BASE_URL?>">' +
                    <?= $hidden_form ?>
                    '<input type="hidden" name="c" value="<?=
                        $data['CONTROLLER'] ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="addcomment" />' +
                    '<input type="hidden" name="parent_id" value="' +
                        parent_id + '" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?= C\CSRF_TOKEN ?>" '+
                    'value="<?= $data[C\CSRF_TOKEN] ?>" />' +
                    '<h2><b><label for="comment-'+ id +'" ><?=
                        tl("groupfeed_element_comment")
                    ?></label></b></h2>'+
                    '<textarea class="short-text-area" '+
                    'id="comment-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,'+
                    '!wikibtn-slide" '+
                    '></textarea>' +
                    '<div class="upload-gray-box center black">' +
                    '<input type="file" id="file-' + id + '" name="file_' + id +
                    '"  class="none" multiple="multiple" />' +
                    '<?= $drag_above_text ?>' +
                    '<a href="javascript:elt(\'file-' + id + '\').click()">'+
                    '<?= $click_link_text ?></a></div>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?= tl("groupfeed_element_save") ?>'+
                    '</button><div>&nbsp;<br /><br /></div>' +
                    '</form>';
                let comment_id = 'comment-' + id;
                initializeFileHandler(comment_id , "file-" + id,
                    <?= min(L\metricToInt(ini_get('upload_max_filesize')),
                    L\metricToInt(ini_get('post_max_size')))
                    ?>, "textarea", null, true);
                editorize(comment_id);
                elt(comment_id).focus();
            } else {
                elt(id).innerHTML = "";
            }
        }
        function updatePostForm(id)
        {
            if (typeof feed_update_id !== 'undefined' && feed_update_id) {
                clearInterval(feed_update_id);
            }
            let title_elt = elt('title' + id);
            let title = null;
            let title_disabled = "";

            if (title_elt) {
                title = elt('title' + id).innerHTML;
                if (title.substr(0, 2) == "--") {
                    title_disabled = "disabled='disabled'";
                }
            }
            let description = elt('old-description'+id).innerHTML;
            let tmp = '<div class="post<?= $clear ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if (start_elt != tmp) {
                setDisplay('result-' + id, false);
                tmp +=
                    '<form method="post"  action="<?=C\SHORT_BASE_URL?>">' +
                    <?= $hidden_form ?>
                    '<input type="hidden" name="c" value="<?=
                        $data['CONTROLLER'] ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="updatepost" />' +
                    '<input type="hidden" name="post_id" value="' +
                        id + '" />' +
                    '<input type="hidden" name="<?= C\CSRF_TOKEN ?>" '+
                    'value="<?= $data[C\CSRF_TOKEN] ?>" />';
                    if (title) {
                        tmp += '<h2><b><?=
                            tl("groupfeed_element_edit_post")
                        ?></b></h2><p <?=$hide_title
                        ?>><b><label for="title-'+ id +'" ><?=
                            tl("groupfeed_element_subject")
                        ?></label></b></p>' +
                        '<p <?= $hide_title
                        ?>><input type="text" name="title" id="title-'+
                        id + '" value="'+title+'" '+
                        ' ' + title_disabled + ' maxlength="<?= C\TITLE_LEN
                        ?>" class="wide-field"/></p>';
                    } else {
                        let hidden_title = elt('hidden-title' + id);
                        if (hidden_title) {
                            tmp += '<input type="hidden" name="title" '+
                                'value="' + hidden_title.innerHtml + '" />';
                        }
                    }
                    tmp += '<p><b><label for="description-'+ id +'" ><?=
                        tl("groupfeed_element_post")
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,' +
                    '!wikibtn-slide" >' + description + '</textarea>'+
                    '<div class="upload-gray-box center black">' +
                    '<input type="file" id="file-' + id + '" name="file_' + id +
                    '"  class="none" multiple="multiple" />' +
                    '<?= $drag_above_text?>' +
                    '<a href="javascript:elt(\'file-' + id + '\').click()">'+
                    '<?=$click_link_text ?></a></div>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?= tl("groupfeed_element_save")
                    ?></button>' +
                    '<div><br /><br /></div>'+
                    '</form>';
                elt(id).innerHTML = tmp;
                let description_id = 'description-' + id;
                initializeFileHandler(description_id , "file-" + id,
                    <?= min(L\metricToInt(ini_get('upload_max_filesize')),
                    L\metricToInt(ini_get('post_max_size')))
                    ?>, "textarea", null, true);
                editorize(description_id);
            } else {
                elt(id).innerHTML = "";
                setDisplay('result-' + id, true);
                doUpdate();
            }
        }<?php
        if ($with_status_update) { ?>
            function feedStatusUpdate()
            {
                let start_url = "<?=html_entity_decode($paging_query) .
                    $delim . $token_string . '&arg=status&feed_time=' ?>";
                let results_container_obj = elt('results-container');
                let feed_time =
                    parseInt(results_container_obj.getAttribute('data-time'));
                if (results_container_obj.lastElementChild) {
                    let tmp_time =
                        results_container_obj.lastElementChild.getAttribute(
                        'data-time');
                    if (tmp_time) {
                        feed_time = parseInt(tmp_time);
                    }
                }
                getPage(null, start_url + feed_time,
                    function(text) {
                    elt('results-container').style.backgroundColor = "#EEE";
                    let tmp_container = document.createElement("div");
                    tmp_container.innerHTML = text;
                    elt('results-container').appendChild(
                        tmp_container.lastElementChild);
                    setTimeout("resetBackground()", 0.5 * sec);
                });
            }
            function clearUpdate()
            {
                 clearInterval(feed_update_id);
                 elt('results-container').innerHTML= "<h2 class='red'><?=
                    tl('groupfeed_element_no_longer_update')?></h2>";
            }
            function resetBackground()
            {
                 elt('results-container').style.backgroundColor = "#FFF";
            }
            function doUpdate()
            {
                var sec = 1000;
                var minute = 60 * sec;
                feed_update_time = 15;
                feed_update_id = setInterval("feedStatusUpdate()",
                    feed_update_time * sec);
                setTimeout("clearUpdate()", 20 * minute + sec);
            }<?php
        } else {?>
            function doUpdate()
            {
            }<?php
        }?>
        </script>
        <?php
    }
}
