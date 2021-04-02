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
 * @author Sarika Padmashali (tweaks cpollett)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
/*
 * defining translation variable
 */
var tl = document.tl;
/*
 * To validate email address
 *
 * Checks if the email address entered is in the correct format
 * @return boolean true if the email address entered is valid else return false
 */
function emailValid()
{
    var mail_format = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
    var email_field = elt("email");
    if (email_field.value.match(mail_format)) {
        var email_error = elt("email-error");
        email_error.innerHTML = "<span class=\"green\">" +
            tl['register_validator_js_valid_email'] + "</span>";
        return true;
    } else {
        if (email_field.value != "") {
            var email_error = elt("email-error");
            email_error.innerHTML = "<span class=\"red\">" +
                tl['register_validator_js_invalid_email'] + "</span>";
        }
        return false;
    }
}
/*
 * To validate password
 *
 * Checks if the password entered is strong enough
 * Warns the user of the password is weak or blank
 * It also checks if the retyped password matches
 */
function passwordValid()
{
    var password_error = elt("password-error");
    var strong_regex = new RegExp("^(?=.{7,})(((?=.*[A-Z])(?=.*[a-z]))|" +
        "((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$", "g");
    var enough_regex = new RegExp("(?=.{6,}).*", "g");
    var password = elt("pass-word");
    if (enough_regex.test(password.value) == false) {
        password_error.innerHTML = "<span class=\"red\">" +
         tl['register_validator_js_more_characters'] + "</span>";
        return false;
    } else if (strong_regex.test(password.value)) {
        password_error.innerHTML = "<span class=\"green\">" +
            tl['register_validator_js_strong_password'] + "</span>";
        return true;
    } else {
        password_error.innerHTML = "<span class=\"red\">" +
            tl['register_validator_js_weak_password'] + "</span>";
        return false;
    }
}
/*
 * To check if retyped password matches
 *
 * Checks if the retyped password matches
 * If not warns the user about it
 */
function passwordMatch()
{
    var retyped_pass = elt("retype-password");
    var password= elt("pass-word");
    var password_match = elt("pass-match");
    if (password.value == "") {
        return true;
    }
    if (retyped_pass.value == password.value) {
        password_match.innerHTML = "<span class=\"green\">" +
            tl['register_validator_js_retype_password_matched'] + "</span>";
        return true;
    } else {
        password_match.innerHTML = "<span class=\"red\">" +
            tl['register_validator_js_retype_password_not_matched'] + "</span>";
        return false;
    }
}
/*
 * To check if user name is not blank
 *
 * Checks if the user name has been entered
 * If not warns the user to enter the username
 */
function checkElement(name, error_name, message)
{
    var user =  elt(name).value;
    var user_error = elt(error_name);
    if (user.match(/^\s*$/)) {
        user_error.innerHTML = "<span class=\"red\">" + message + "</span>";
    } else {
        user_error.innerHTML = "<span class=\"red\"></span>";
    }
}
/*
 * Disables the submit button
 *
 * Enables the submit button only if all input fields are valid
 * If not keeps the submit button disabled until the user corrects all the
 * fields
 */
function setSubmitStatus()
{
    var user =  elt("username").value;
    var first = elt("firstname").value;
    var last = elt("lastname").value;
    if (emailValid() && passwordValid() &&
        passwordMatch() && first != "" &&last != "" && user!= "") {
        elt("submit").disabled = false;
    } else {
        elt("submit").disabled = true;
    }
}
function checkAll()
{
    checkElement("firstname", "first-error",
        tl['register_validator_js_enter_firstname']);
    checkElement("lastname", "last-error",
        tl['register_validator_js_enter_lastname']);
    checkElement("username", "user-error",
        tl['register_validator_js_enter_username']);
    setSubmitStatus();
}
/*
 * event handler to check first name
 */
elt("firstname").addEventListener("keyup", function() {
    checkElement("firstname", "first-error",
        tl['register_validator_js_enter_firstname']);
    setSubmitStatus();
});
/*
 * event handler to check last name
 *
 */
elt("lastname").addEventListener("keyup", function() {
    checkElement("lastname", "last-error",
        tl['register_validator_js_enter_lastname']);
    setSubmitStatus();
});
/*
 * event handler to check user name
 *
 */
elt("username").addEventListener("keyup", function() {
    checkElement("username", "user-error",
        tl['register_validator_js_enter_username']);
    setSubmitStatus();
});
/*
 * event handler to validate email address
 *
 */
elt("email").addEventListener("keyup", function() {
    emailValid();
    setSubmitStatus();
});
/*
 * event handler to validate password
 *
 */
elt("pass-word").addEventListener("keyup", function() {
    passwordValid();
    setSubmitStatus();
});
/*
 * event handler to validate retyped password
 *
 */
elt("retype-password").addEventListener("keyup",  function() {
    passwordMatch();
    setSubmitStatus();
});