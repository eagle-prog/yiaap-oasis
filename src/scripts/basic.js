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
/*
 * Display a two second message in the message div at the top of the web page
 *
 * @param String msg  string to display
 */
function doMessage(msg)
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = msg;
    msg_timer = setInterval("undoMessage()", 2000);
}
/*
 * Undisplays the message display in the message div and clears associated
 * message display timer
 */
function undoMessage()
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = "";
    clearInterval(msg_timer);
}
/*
 * Function to set up a request object even in  older IE's
 *
 * @return Object the request object
 */
function makeRequest()
{
    try {
        request = new XMLHttpRequest();
    } catch (e) {
        try {
            request = new ActiveXObject('MSXML2.XMLHTTP');
        } catch (e) {
            try {
            request = new ActiveXObject('Microsoft.XMLHTTP');
            } catch (e) {
            return false;
            }
        }
    }
    return request;
}
/*
 * Make an AJAX request for a url and put the results as inner HTML of a tag
 * If the response is the empty string then the tag is not replaced
 *
 * @param Object tag  a DOM element to put the results of the AJAX request
 * @param String url  web page to fetch using AJAX
 * @param Function success_callback function to call on success
 * @param Function fail_callback function to call on failure
 */
function getPage(tag, url)
{
    var request = makeRequest();
    if (request) {
        let self = this;
        let success_callback = (typeof arguments[2] == 'undefined') ?
            null : arguments[2];
        let fail_callback = (typeof arguments[3] == 'undefined') ?
            null : arguments[3];
        request.onreadystatechange = function()
        {
            if (self.request.readyState == 4) {
                if( self.request.responseText != "") {
                    if (tag != null) {
                        tag.innerHTML = self.request.responseText;
                    }
                    if (success_callback) {
                        success_callback(self.request.responseText);
                    }
                } else {
                    if (fail_callback) {
                        fail_callback(self.request.responseText);
                    }
                }
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}
/*
 * Returns the position of the caret within a node
 *
 * @param String input type element
 */
function caret(node)
{
    if (node.selectionStart) {
        return node.selectionStart;
    } else if (!document.selection) {
        return false;
    }
    // old ie hack
    var insert_char = "\001",
    sel = document.selection.createRange(),
    dul = sel.duplicate(),
    len = 0;

    dul.moveToElementText(node);
    sel.text = insert_char;
    len = dul.text.indexOf(insert_char);
    sel.moveStart('character',-1);
    sel.text = "";
    return len;
}
/*
 * Shorthand for document.createElement()
 *
 * @param String name tag name of element desired
 * @return Element the create element
 */
function ce(name)
{
    return document.createElement(name);
}
/*
 * Shorthand for document.getElementById()
 *
 * @param String id  the id of the DOM element one wants
 */
function elt(id)
{
    return document.getElementById(id);
}
/*
 * Shorthand for document.getElementsByTagName()
 *
 * @param String name the name of the DOM element one wants
 */
function tag(name)
{
    return document.getElementsByTagName(name);
}
/*
 * Shorthand for document.querySelectorAll()
 *
 * @param String css selector of the DOM element one wants
 */
function sel(css_selector)
{
    return document.querySelectorAll(css_selector);
}
/*
 * Used to add an event listener to an object
 * @param object object to add to lister to
 * @param String event_type the kind of event to listen for
 * @param Function handler callback function to handle event
 */
function listen(object, event_type, handler)
{
    object.addEventListener(event_type, handler, false);
}
/*
 * Used to add an event listener to all objects in DOM matching a css selector
 * @param String css selector to query DOM with
 * @param String event_type the kind of event to listen for
 * @param Function handler callback function to handle event
 */
function listenAll(css_selector, event_type, handler)
{
    var selected_objects = sel(css_selector);
    for (var i = 0; i < selected_objects.length; i++) {
        selected_objects[i].addEventListener(event_type, handler, false);
    }
}
/*
 * Used to toggle the options menu controlled  by the settings-toggle
 * Hamburger menu icon
 */
function toggleOptions()
{
    toggleDisplay('menu-options-background');
    let body_tag_obj = tag('body')[0];
    let body_obj = elt('body-container');
    let top_obj = elt('top-container');
    let logo_subsearch_obj = elt('logo-subsearch');
    let center_obj = elt('center-container');
    let menu_obj = elt('menu-options');
    let admin_menu_obj = elt('admin-menu-options');
    if (menu_obj.style.left == '0px' || menu_obj.style.right == '0px') {
        if (body_tag_obj.classList.contains('html-ltr')) {
            body_obj.style.marginLeft = '0px';
            menu_obj.style.left = '-300px';
            if (typeof admin_menu_obj != 'undefined' &&
                admin_menu_obj) {
                admin_menu_obj.style.left = '-300px';
            }
            if (typeof logo_subsearch_obj != 'undefined' &&
                logo_subsearch_obj) {
                logo_subsearch_obj.style.left =
                    logo_subsearch_obj.style.oldLeft;
            }
        } else {
            body_obj.style.marginRight = '0px';
            menu_obj.style.right = '-300px';
            if (typeof admin_menu_obj != 'undefined' &&
                admin_menu_obj) {
                admin_menu_obj.style.right = '-300px';
            }
            if (typeof logo_subsearch_obj != 'undefined') {
                logo_subsearch_obj.style.right =
                    logo_subsearch_obj.style.oldRight;
            }
        }
        setTimeout(function () {
            if (typeof top_obj.style.oldValue != 'undefined' &&
                top_obj) {
                top_obj.style.display = top_obj.style.oldValue;
            } else {
                top_obj.style.display = 'block';
            }
            center_obj.style.marginTop = '65px';
        }, 250);
    } else {
        top_obj.style.oldValue = top_obj.style.display;
        top_obj.style.display = 'contents';
        center_obj.style.marginTop = '0';
        if (body_tag_obj.classList.contains('html-ltr')) {
            body_obj.style.marginLeft = '300px';
            menu_obj.style.left = '0px';
            if (typeof admin_menu_obj != 'undefined' &&
                admin_menu_obj) {
                admin_menu_obj.style.left = '0px';
            }
            if (typeof logo_subsearch_obj != 'undefined' &&
                logo_subsearch_obj) {
                logo_subsearch_obj.style.oldLeft =
                    logo_subsearch_obj.style.left;
                logo_subsearch_obj.style.left ='370px';
            }
        } else {
            body_obj.style.marginRight = '300px';
            menu_obj.style.right = '0px';
            if (typeof admin_menu_obj != 'undefined' &&
                admin_menu_obj) {
                admin_menu_obj.style.right = '0px';
            }
            if (typeof logo_subsearch_obj != 'undefined' &&
                logo_subsearch_obj) {
                logo_subsearch_obj.style.oldRight =
                    logo_subsearch_obj.style.right;
                logo_subsearch_obj.style.right ='370px';
            }
        }
    }
}
/*
 * Used to store a copy of the states of elements on a form so that they can
 * be compared with the state at a later time. Typically, this would be used
 * to figure out if the form needs to be submitted.
 *
 * @param Object form a web form to make copies of the states to of
 */
function saveFormState(form)
{
    for (var i = 0;  i < form.length; i++) {
        var elt = form[i];
        elt.dataset.stateValue = elt.value;
        elt.dataset.stateChecked = elt.checked;
    }
}
/*
 * Used to compare the states of elements on a form with a saved state for
 * those elements. Returns true or false depending on whether these two states
 * are the same. Typically, this would be used
 * to figure out if the form needs to be submitted.
 * @param Object form a web form to compare if form elements equal to saved
 *    state
 * @return Boolean true or false depending on if equal
 */
function equalFormSaveState(form)
{
    for (var i = 0; i < form.length; i++) {
        var elt = form[i];
        if (('stateValue' in elt.dataset &&
            elt.dataset.stateValue !== elt.value) || (
            'stateChecked' in elt.dataset &&
            "" + elt.dataset.stateChecked != "" + elt.checked)) {
            return false;
        }
    }
    return true;
}
/*
 * Create a callback function used to get the next group of search/feed results
 * when continuous scrolling based reulst displaying is used.
 *
 * @param int limit what is ranked position of first result to get
 * @param int total_results total number of search results for query
 * @param int results_per_page number of results which should be returned
 * @param String base_url url to process query at
 * @param String end_result_string human language string to write if no
 *      more results
 * @param String container_id of the div tag used for all results
 * @param String next_results_id of tag to use forr the next block of
 *  results within this container
 */
function initNextResultsPage(limit, total_results, results_per_page,
    base_url, end_result_string, container_id, results_id)
{
    let can_add_state = 1;
    let scroll_obj = null;
    if (container_id === undefined) {
        container_id = "search-body";
        scroll_obj = window;
        scroll_body = document.body;
    } else {
         scroll_obj = document.getElementById(container_id);
         scroll_body = scroll_obj;
    }
    scroll_obj.scrollTo(0, 0);
    if (results_id === undefined) {
        results_id = "search-results";
    }
    let nextPage = function () {
        if (limit < total_results && can_add_state > 0) {
            can_add_state = 0;
            let tmp_hr = elt("limit-" + limit);
            if (tmp_hr != null) {
                var tmp_total = tmp_hr.getAttribute('data-total');
                if (tmp_total > 0) {
                    total_results = parseInt(tmp_total);
                }
            }
            limit += results_per_page;
            getPage(null, base_url + "&limit=" + limit +
                "&f=api", function(text) {
                let container_body = document.getElementById(
                    container_id);
                let next_results = document.createElement("div");
                let button_parent = null;
                next_results.setAttribute("class", results_id);
                next_results.innerHTML = text;
                let next_button = elt('next-button');
                if (next_button && next_button.parentNode &&
                    next_button.parentNode.parentNode &&
                    next_button.parentNode.parentNode == container_body) {
                    button_parent = container_body.removeChild(
                        next_button.parentNode);
                }
                container_body.appendChild(next_results);
                if (button_parent != null) {
                    container_body.appendChild(button_parent);
                }
                if (results_id == 'search-results' &&
                    next_results.children.length < results_per_page + 2 &&
                    (next_results.children.length != 3 ||
                    next_results.children[2].children.length <
                    results_per_page)) {
                    total_results = limit - (results_per_page + 2 -
                        next_results.children.length);
                }
                can_add_state = 1;
            }, function() {
                limit = total_results;
                can_add_state = 0;
                return;
            });
        }
        if (limit >= total_results && can_add_state >= 0) {
            can_add_state = -1;
            setDisplay('next-button', false);
            if (end_result_string != "") {
                var end_results = document.createElement("h3");
                end_results.setAttribute("class", "center");
                end_results.innerHTML = end_result_string;
                end_results.style.background = '#F8F8F8';
                elt(container_id).appendChild(end_results);
            }
        }
    }
    scroll_obj.addEventListener("scroll", function() {
        if ((scroll_obj.scrollTop !== undefined && scroll_obj.scrollTop >=
            scroll_body.scrollHeight - scroll_obj.clientHeight) ||
            (scroll_obj.scrollY  !== undefined && scroll_obj.scrollY >=
                scroll_body.scrollHeight - scroll_obj.innerHeight)) {
            nextPage();
        }
    });
    return nextPage;
}
/*
 * Create a callback function used to get the previous group of
 * search/feed results when continuous scrolling based reulst displaying is
 * used.
 *
 * @param int limit what is ranked position of first result to get
 * @param int total_results total number of search results for query
 * @param int results_per_page number of results which should be returned
 * @param String base_url url to process query at
 * @param String end_result_string human language string to write if no
 *      more results
 * @param String container_id of the div tag used for all results
 * @param String next_results_id of tag to use forr the next block of
 *  results within this container
 */
function initPreviousResultsPage(limit, total_results, results_per_page,
    base_url, container_id, results_id)
{
    let can_add_state = true;
    let scroll_obj = null;
    if (container_id === undefined) {
        container_id = "search-body";
        scroll_obj = window;
        scroll_body = document.body;
    } else {
         scroll_obj = document.getElementById(container_id);
         scroll_body = scroll_obj;
    }
    if (results_id === undefined) {
        results_id = "search-results";
    }
    let previousPage = function () {
        if (limit > 0 && can_add_state) {
            can_add_state = false;
            let tmp_hr = elt("limit-" + limit);
            if (tmp_hr != null) {
                var tmp_total = tmp_hr.getAttribute('data-total');
                if (tmp_total > 0) {
                    total_results = parseInt(tmp_total);
                }
            }
            let top = (scroll_obj.scrollTop !== undefined) ?
                scroll_obj.scrollTop : scroll_obj.scrollY;
            limit -= results_per_page;
            limit = (limit > 0) ? limit : 0;
            getPage(null, base_url + "&limit=" + limit +
                "&f=api", function(text) {
                let container_body = document.getElementById(
                    container_id);
                let previous_results = document.createElement("div");
                let button_parent = null;
                previous_results.setAttribute("class", results_id);
                previous_results.innerHTML = text;
                let previous_button = elt('previous-button');
                if (previous_button && previous_button.parentNode &&
                    previous_button.parentNode.parentNode &&
                    previous_button.parentNode.parentNode == container_body) {
                    container_body.insertBefore(previous_results,
                        previous_button.parentNode);
                    button_parent = container_body.removeChild(
                        previous_button.parentNode);
                }
                if (button_parent != null) {
                    container_body.insertBefore(button_parent,
                        previous_results);
                }
                scroll_obj.scrollTo(0, top + previous_results.clientHeight);
                if (previous_results.children.length <
                    results_per_page + 2 && (previous_results.children.length
                    != 3 || previous_results.children[2].children.length <
                    results_per_page)) {
                    total_results = limit - (results_per_page + 2 -
                        previous_results.children.length);
                }
                can_add_state = true;
            });
        }
        if (limit <= 0 && can_add_state) {
            can_add_state = false;
            setDisplay('previous-button', false);
        }
    }
    if (total_results - limit < results_per_page) {
        previousPage();
    }
    scroll_obj.addEventListener("scroll", function() {
        if ((scroll_obj.scrollTop !== undefined && scroll_obj.scrollTop <= 10)||
            (scroll_obj.scrollY  !== undefined && scroll_obj.scrollY <= 10)) {
            previousPage();
        }
    });
    return previousPage;
}
/*
 * Used to set up a listener for a right-to-left swipe event
 *
 * @param Object obj element on which to listen for a swipe event
 * @param Function handler callback to handle swipe event
 */
function leftSwipe(obj, handler)
{
    listen(obj, 'touchstart', startLeft);
    listen(obj, 'touchmove', leftMoveChecker);
    listen(obj, 'mousedown', startLeft);
    listen(obj, 'mousemove', leftMoveChecker);
    var x_begin = null;
    var y_begin = null;
    function startLeft(evt)
    {
        if (evt.type == 'touchstart') {
            evt = evt.touches[0];
        }
        x_begin = evt.clientX;
        y_begin = evt.clientY;
    }
    function leftMoveChecker(evt)
    {
        if ( !x_begin || !y_begin ) {
            return;
        }
        if (evt.type == 'touchmove') {
            evt = evt.touches[0];
        }
        var x_end = evt.clientX;
        var y_end = evt.clientY;
        var delta_x = x_end - x_begin;
        var delta_y = y_end - y_begin;
        // check whether moved more in x or y direction
        if ( Math.abs( delta_x ) > Math.abs( delta_y ) ) {
            if (delta_x < -5) {
                handler(evt);
            }
        }
        /* reset values */
        x_begin = null;
        y_begin = null;
    }
}
/*
 * Used to set up a listener for a left-to-right swipe event
 *
 * @param Object obj element on which to listen for a swipe event
 * @param Function handler callback to handle swipe event
 */
function rightSwipe(obj, handler)
{
    listen(obj, 'touchstart', startRight);
    listen(obj, 'touchmove', rightMoveChecker);
    listen(obj, 'mousedown', startRight);
    listen(obj, 'mousemove', rightMoveChecker);
    var x_begin = null;
    var y_begin = null;
    function startRight(evt)
    {
        if (evt.type == 'touchstart') {
            evt = evt.touches[0];
        }
        x_begin = evt.clientX;
        y_begin = evt.clientY;
    }
    function rightMoveChecker(evt)
    {
        if ( !x_begin || !y_begin ) {
            return;
        }
        if (evt.type == 'touchmove') {
            evt = evt.touches[0];
        }
        var x_end = evt.clientX;
        var y_end = evt.clientY;
        var delta_x = x_end - x_begin;
        var delta_y = y_end - y_begin;
        // check whether moved more in x or y direction
        if ( Math.abs( delta_x ) > Math.abs( delta_y ) ) {
            if (delta_x > 5) {
                handler(evt);
            }
        }
        /* reset values */
        x_begin = null;
        y_begin = null;
    }
}
/*
 * Used to countdown the number of remaining characters that can be entered in
 * a text field
 *
 * @param String text_field_id id of input text field to count down
 * @param String display_box_id id of element in which to display countdown
 */
function updateCharCountdown(text_field_id, display_box_id)
{
    text_field = elt(text_field_id);
    display_box = elt(display_box_id);
    if (typeof text_field.maxLength != 'undefined' && display_box) {
        display_box.innerHTML = text_field.maxLength - text_field.value.length;
    }
}
/*
 * Global used by initializeFileHandler fileUploadSubmit to determine if
 * a submit event has already occurred
 * @var Boolean
 */
was_submitted = false;
/*
 * Global used by initializeFileHandler to keep track of all files
 * associated with a form to be uploaded. Assume only one form on a page.
 * @var Boolean
 */
file_list = new Array();
/*
 * Used to handle drag and drop file attachment and uploads on wiki and
 * group feed pages
 *
 * @param String drop_id id of element to listen for drop events
 * @param String file_id id of form file input that dropped objects
 *      will be associated with
 * @param String drop_kind what kind of element drop_id is. One of text (for
 *      textfield), textarea will add text to textarea, image
 *      (will replace image with what's dropped), or all.
 * @param Array types what file types can be upload
 * @param Boolean multiple whether multiple items dan be dropped in one go
 *      or selected from the file input picker.
 */
function initializeFileHandler(drop_id, file_id, max_size, drop_kind, types,
    multiple)
{
    var drop_elt = document.getElementById(drop_id);
    var file_elt = document.getElementById(file_id);
    var parent_form = file_elt.form;
    var last_call = "clear";
    var tl = document.tl;
    listen(parent_form, "submit", fileUploadSubmit);
    listen(drop_elt, "dragenter", stopNoPropagate);
    listen(drop_elt, "dragexit", stopNoPropagate);
    listen(drop_elt, "dragover", stopNoPropagate);
    listen(drop_elt, "drop", drop);
    listen(file_elt, "change",
        function(event)
        {
            if (last_call != "drop") {
                checkAndSetFile(file_elt.files);
            }
            last_call = "clear";
        }
    );
    function fileUploadSubmit(event)
    {
        stopNoPropagate(event);
        if (was_submitted) {
            return;
        }
        was_submitted = true;
        var form_data = new FormData();
        var form_elements = parent_form.elements;
        var k = 0;
        for (var i = 0; i <  form_elements.length; i++) {
            var element = form_elements[i];
            if (element.type == "file") {
                var name = element.name;
                if (file_list[name] === undefined) {
                    continue;
                }
                if (file_list[name].length == 1 &&
                    file_list[name][0].length == 1) {
                    form_data.append(name,
                        file_list[name][0][0]);
                        k++;
                } else {
                    for (var j = 0; j < file_list[name].length; j++) {
                        for (var m = 0; m < file_list[name][j].length; m++) {
                            form_data.append(name + "[" + k + "]",
                                file_list[name][j][m]);
                            k++;
                        }
                    }
                }
            } else if (element.type == "checkbox") {
                if (element.checked) {
                    form_data.append(element.name, element.value);
                }
            } else {
                form_data.append(element.name, element.value);
            }
        }
        var request = new XMLHttpRequest();
        if (k > 0) {
            listen(request.upload, "progress", uploadProgress, false);
        }
        listen(request, "load", uploadComplete, false);
        listen(request, "error", uploadFailed, false);
        listen(request, "abort", uploadCanceled, false);
        //keep ie happy
        var submit_to = (parent_form.action) ? parent_form.action :
            document.location;
        request.open("post", submit_to);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.send(form_data);
    }
    function drop(event)
    {
        stopNoPropagate(event);
        var files = event.dataTransfer.files;
        var count = files.length;
        if (count > 0) {
            last_call = "drop";
            checkAndSetFile(files);
        }
    }
    function checkAndSetFile(files)
    {
        if (!multiple && files.length > 1) {
            doMessage('<h1 class=\"red\" >' +
                tl["basic_js_too_many_files"] + '</h1>');
            return;
        }
        for (var i = 0; i < files.length; i++) {
            if (!checkAllowedType(files[i])) {
                doMessage('<h1 class=\"red\" >' +
                    tl["basic_js_invalid_filetype"] + '</h1>');
                return;
            }
            if (max_size > 0 && files[i].size > max_size) {
                doMessage('<h1 class=\"red\" >' +
                    tl["basic_js_file_too_big"] + '</h1>');
                return;
            }
        }
        if (file_list[file_elt.name] === undefined) {
            file_list[file_elt.name] = new Array();
        }
        if (multiple) {
            file_list[file_elt.name][file_list[file_elt.name].length] = files;
        } else {
            file_list[file_elt.name][0] = files;
        }
        if (drop_kind == "image") {
            var img_url = URL.createObjectURL(files[0]);
            drop_elt.src = img_url;
        } else if (drop_kind == "textarea") {
            for (var j = 0; j < files.length; j++) {
                addToPage(files[j].name, drop_id);
            }
        } else if (drop_kind == "all" || drop_kind == "text") {
            if (drop_elt.innerHTML == "&nbsp;") {
                drop_elt.innerHTML = "";
            }
            for (var i = 0; i < files.length; i++) {
                var br = (drop_elt.innerHTML == "") ? "" : "<br />";
                drop_elt.innerHTML += br + files[i].name;
            }
        }
    }
    function stopNoPropagate(event)
    {
        event.stopPropagation();
        event.preventDefault();
    }
    function checkAllowedType(to_check)
    {
        if (types == null || types.length == 0) {
            return true;
        }
        for (type in types) {
            if (to_check.type == types[type]) {
                return true;
            }
        }
        return false;
    }
    function uploadProgress(event)
    {
        var progress = elt('message');
        if (event.lengthComputable) {
            var percent_complete =
                Math.round(event.loaded * 100 / event.total);
            progress.innerHTML = '<h1 class=\"red\" >' +
                tl["basic_js_upload_progress"] +
                percent_complete.toString() + '%</h1>';
        } else {
            progress.innerHTML = '<h1 class=\"red\" >' +
                tl["basic_js_progress_meter_disabled"] +'</h1>';
        }
    }
    function uploadComplete(event)
    {
        /* This event is raised when the server sends back a response */
        if (event.target.responseText.substring(0,2) == "go") {
            window.location = event.target.responseText.substring(2);
        } else {
            document.open();
            document.write(event.target.responseText);
            document.close();
        }
    }
    function uploadFailed(event)
    {
        doMessage('<h1 class=\"red\" >' +
            tl["basic_js_upload_error"] +'</h1>');
    }
    function uploadCanceled(event)
    {
        doMessage('<h1 class=\"red\" >' +
            tl["basic_js_upload_cancelled"] +'</h1>');
    }
}
/*
 * Sets whether an elt is styled as display:none or block
 *
 * @param String id  the id of the DOM element one wants
 * @param mixed value  true means display display_type false display none;
 *     anything else will display that value
 * @param mixed display_type type to set CSS display property to in the event
 *      value is true (might be block or inline, etc).
 */
function setDisplay(id, value, display_type)
{
    if (display_type === undefined){
        display_type = "block";
    }
    obj = elt(id);
    if(!obj) {
        return;
    }
    if (value == true)  {
        value = display_type;
    }
    if (value == false) {
        value = "none";
    }
    obj.style.display = value;
    if (value == "none") {
        obj.setAttribute('aria-hidden', true);
    } else {
        obj.setAttribute('aria-hidden', false);
    }
}
/*
 * Toggles an element between display:none and display block
 * @param String id  the id of the DOM element one wants
 */
function toggleDisplay(id, display_type)
{
    if (display_type === undefined) {
        display_type = "block";
    }
    obj = elt(id);
    if (obj.style.display == display_type)  {
        value = "none";
    } else {
        value = display_type;
    }
    obj.style.display = value;
    if (value == "none") {
        obj.setAttribute('aria-hidden', true);
    } else {
        obj.setAttribute('aria-hidden', false);
    }
}
/*
 * Make an AJAX request for a url
 *
 * @param String url  web page to fetch using AJAX
 */
function getPageWithMessage(url)
{
    var request = makeRequest();
    if (request) {
        var self = this;
        request.onreadystatechange = function()
        {
            if (self.request.readyState == 4) {
                 doMessage(self.request.responseText);
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}
/*
 *
 * @param Object media_obj
 */
function previousMediaPage(media_obj)
{
    var type = media_obj.reflect_type;
    var media_name = media_obj.reflect_name;
    if (type == 'epub') {
        media_obj.prevPage().then(
            function () {
                updateMediaLocationInfo(media_obj);
            }
        );
    } else if (type == 'pdf') {
        if (media_obj.reflect_page_num > 1) {
            renderPdfPage(media_obj, media_obj.reflect_page_num - 1);
        }
    }
}
/*
 *
 * @param Object media_obj
 */
function nextMediaPage(media_obj)
{
    var type = media_obj.reflect_type;
    var media_name = media_obj.reflect_name;
    if (type == 'epub') {
        media_obj.nextPage().then(
            function () {
                updateMediaLocationInfo(media_obj);
            }
        );
    } else if (type == 'pdf') {
        if (media_obj.reflect_page_num < media_obj.numPages) {
            renderPdfPage(media_obj, media_obj.reflect_page_num + 1);
        }
    }
    return false;
}
/*
 *
 * @param Object media_obj
 */
function setMediaLocationFromRange(media_obj)
{
    var type = media_obj.reflect_type;
    var media_name = media_obj.reflect_name;
    var percent = elt('range-'+media_name).value;
    if (type == 'epub') {
        if (percent > 0) {
            media_obj.goto(percent + "%").then(
                function() {
                    updateMediaLocationInfo(media_obj);
                }
            )
        } else {
            media_obj.gotoPage(1).then(
                function() {
                    updateMediaLocationInfo(media_obj);
                }
            )
        }
    } else if (type == 'pdf') {
        if (percent > 0) {
            var total_pages = media_obj.numPages;
            var current_page = media_obj.reflect_page_num;
            var page_num =  parseInt((percent * total_pages)/100);
            renderPdfPage(media_obj, page_num);
        } else {
            renderPdfPage(media_obj, 1)
        }
    }
    return false;
}

/*
 *
 * @param Object media_obj
 */
function rotatePdf(media_obj)
{
    var orientation = (parseInt(media_obj.reflect_orientation) + 90) % 360;
    media_obj.reflect_orientation = "" + orientation;
    renderPdfPage(media_obj, media_obj.reflect_page_num);
}
/*
 *
 * @param Object media_obj
 */
function renderPdfPage(media_obj, num)
{
    if (media_obj.reflect_rendering) {
        return;
    }
    var media_name = media_obj.reflect_name;
    media_obj.reflect_page_num = num;
    media_obj.reflect_rendering = true;
    media_obj.getPage(num).then(function(page) {
        var viewport = page.getViewport(1.5,
            parseInt(media_obj.reflect_orientation));
        var canvas = elt('area-' + media_name);
        var context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        var renderContext = {
            canvasContext: context,
            viewport: viewport
        };
        var renderTask = page.render(renderContext);
        renderTask.promise.then(function () {
            media_obj.reflect_rendering = false;
            updateMediaLocationInfo(media_obj);
        });
    });
}
/*
 *
 * @param Object media_obj
 */
 function updateMediaLocationInfo(media_obj)
 {
    var type = media_obj.reflect_type;
    var offset = media_obj.reflect_offset;
    var media_name = media_obj.reflect_name;
    if (type == 'epub') {
        var iframe = tag('iframe');
        if (iframe.length > 0) {
            iframe_doc = iframe[0].contentDocument ||
                iframe[0].contentWindow.document;
            leftSwipe(iframe_doc, function(evt) {
                nextMediaPage(media_obj);
                }
            );
            rightSwipe(iframe_doc, function(evt) {
                previousMediaPage(media_obj);
                }
            );
        }
        var loc = (typeof(arguments[1]) !== 'undefined') ?
            arguments[1] :
            media_obj.getCurrentLocationCfi();
        if (localStorage) {
            localStorage[media_name + '-location'] = loc;
        }
        var ready = media_obj.pagination.totalPages;
        var total_pages = media_obj.pagination.totalPages;
        var current_page = media_obj.pagination.pageFromCfi(loc) - offset;
        var percent = (ready) ?
            parseInt(media_obj.pagination.percentageFromCfi(loc) * 100) : 0;
    } else if (type == 'pdf') {
        var ready = media_obj.numPages;
        var total_pages = media_obj.numPages;
        var current_page = media_obj.reflect_page_num;
        if (localStorage) {
            localStorage[media_name + '-reflect-page-num'] = current_page;
            localStorage[media_name + '-reflect-orientation'] =
                media_obj.reflect_orientation;
        }
        var percent = (ready) ?
            parseInt((current_page * 100)/ total_pages) : 0;
    }
    elt('pos-'+media_name).innerHTML = (ready) ? current_page + "/" +
        total_pages : "-/-";
    elt('range-'+media_name).value = percent;
 }
