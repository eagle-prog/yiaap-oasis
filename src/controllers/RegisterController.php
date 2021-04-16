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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\MailServer;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\models\LocaleModel;

/**
 * Controller used to handle account registration and retrieval for
 * the Yioop website. Also handles data for suggest a url
 *
 * @author Mallika Perepa (Creator), Chris Pollett (extensive rewrite)
 */
class RegisterController extends Controller implements CrawlConstants
{
    /**
     * Holds a list of the allowed activities. These encompass various
     * stages of the account creation and account recovery processes
     * @var array
     */
    public $activities = ["createAccount", "emailVerification",
        "processAccountData", "processRecoverData", "recoverPassword",
        "recoverComplete", "resendComplete", "resendRegistration",
        "suggestUrl"];
    /**
     * Non-recovery question fields needed to register a Yioop account.
     * @var array
     */
    public $register_fields = ["first", "last", "user", "email", "password",
        "repassword"];
    /**
     * Forbidden username
     * @var array
     */
    public $forbidden_usernames = "admin moderator root";
    /**
     * An array of triples, each triple consisting of a question of the form
     * Which is your favorite..? followed by one of the form
     * Which is your like the least..? followed by a string which is a comma
     * separated choices. The values for these triples are determined
     * via the translate function tl. So can be set under Manage Locales
     * by editing their values for the desired locale.
     * @var array
     */
    public $recovery_qa;
    /**
     * Number of recovery questions from the complete set of questions to
     * present someone when register for an account
     * @var int
     */
    const NUM_RECOVERY_QUESTIONS = 3;
    /**
     * Define the number of seconds till hash code is valid
     * @var int
     */
    const HASH_TIMESTAMP_TIMEOUT = 300;
    /**
     * Use to match the leading zero in the sha1 of the string
     * @var int
     */
    const HASH_CAPTCHA_LEVEL = 2;
    /**
     * Besides invoking the base controller, sets up in field variables
     * the captcha and recovery question and possible answers.
     *
     * @param seekquarry\yioop\library\WebSite $web_site is the web server
     *      when Yioop runs in CLI mode, it acts as request router in non-CLI
     *      mode. In CLI, mode it is useful for caching files in RAM as they
     *      are read
     */
    public function __construct($web_site = null)
    {
        $locale = LocaleModel::$current_locale;
        $register_view = $this->view("register");
        $this->recovery_qa = self::getRecoveryQuestions($register_view,
            $locale);
        if (C\RECOVERY_MODE == C\NO_RECOVERY) {
            $this->activities = ["createAccount", "emailVerification",
                "processAccountData", "suggestUrl"];
        }
        parent::__construct($web_site);
    }
    /**
     * Main entry method for this controller. Determine which account
     * creation/recovery activity needs to be performed. Calls the
     * appropriate method, then sends the return $data to a view
     * determined by that activity. $this->displayView then renders that
     * view
     */
    public function processRequest()
    {
        $visitor_model = $this->model("visitor");
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = L\remoteAddress();
        }
        $visitor_check_names = ['captcha_time_out','suggest_day_exceeded'];
        foreach ($visitor_check_names as $name) {
            $visitor = $visitor_model->getVisitor(L\remoteAddress(), $name);
            if (isset($visitor['END_TIME']) && $visitor['END_TIME'] > time()) {
                $_SESSION['value'] = date('Y-m-d H:i:s', $visitor['END_TIME']);
                $url = B\wikiUrl($visitor['PAGE_NAME']);
                $this->web_site->header("Location:" . $url);
                \seekquarry\yioop\library\webExit();
            }
        }
        $data = [];
        $data['REFRESH'] = "register";
        $data['SCRIPT'] = "";
        $activity = isset($_REQUEST['a']) ?
            $this->clean($_REQUEST['a'], 'string') : 'createAccount';
        $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user);
        if (!in_array($activity, $this->activities) || (!$token_okay
            && in_array($activity, ["processAccountData",
            "processRecoverData"]) )) {
            $activity = 'createAccount';
        }
        $data["check_user"] = true;
        $data["check_fields"] = $this->register_fields;
        if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
            $num_questions = self::NUM_RECOVERY_QUESTIONS;
        } else {
            $num_questions = 0;
        }
        for ($i = 0; $i < $num_questions; $i++) {
            $data["check_fields"][] = "question_$i";
        }
        $this->preactivityPrerequisiteCheck($activity,
            'processAccountData', 'createAccount', $data);
        unset($data["check_user"]);
        $data["check_fields"] = ["user"];
        if (C\CAPTCHA_MODE != C\IMAGE_CAPTCHA &&
            isset($_SESSION["captcha_text"])) {
            unset($_SESSION["captcha_text"]);
        }
        $this->preactivityPrerequisiteCheck($activity,
            'processRecoverData', 'recoverPassword', $data);
        $this->preactivityPrerequisiteCheck($activity,
            'resendComplete', 'resendRegistration', $data);
        unset($data["check_fields"]);
        $new_data = $this->call($activity);
        $data = array_merge($new_data, $data);
        if (isset($new_data['REFRESH'])) {
            $data['REFRESH'] = $new_data['REFRESH'];
        }
        if (isset($new_data['SCRIPT']) && $new_data['SCRIPT'] != "") {
            $data['SCRIPT'] .= $new_data['SCRIPT'];
        }
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user);
        $view = $data['REFRESH'];
        if (!isset($_SESSION['REMOTE_ADDR'])) {
            if (empty($_REQUEST['a']) || ($_REQUEST['a'] != 'createAccount' &&
                !($_REQUEST['a'] == 'suggestUrl' && !isset($_REQUEST['arg'])))){
                $view = "signin";
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_need_cookies')."</h1>');";
            }
            $visitor_model->updateVisitor(
                L\remoteAddress(), "captcha_time_out");
        }
        if (C\CAPTCHA_MODE == C\HASH_CAPTCHA) {
            if (!isset($data['INCLUDE_SCRIPTS'])) {
                $data['INCLUDE_SCRIPTS']= [];
            }
            $data['INCLUDE_SCRIPTS'] = array_unique(
                array_merge($data['INCLUDE_SCRIPTS'],
                ["sha1", "hash_captcha"]));
        }
        //used to ensure that we have sessions active
        $_SESSION['REMOTE_ADDR'] = L\remoteAddress();
        /*used to manage locales
        *
        * localization of the warning messages
        */
        $tl = ["register_validator_js_valid_email" =>
            tl('register_validator_js_valid_email'),
            "register_validator_js_invalid_email" =>
            tl('register_validator_js_invalid_email'),
            "register_validator_js_more_characters" =>
            tl('register_validator_js_more_characters'),
            "register_validator_js_strong_password" =>
            tl('register_validator_js_strong_password'),
            "register_validator_js_weak_password" =>
            tl('register_validator_js_weak_password'),
             "register_validator_js_enter_username" =>
            tl('register_validator_js_enter_username'),
            "register_validator_js_enter_firstname" =>
            tl('register_validator_js_enter_firstname'),
            "register_validator_js_enter_lastname" =>
            tl('register_validator_js_enter_lastname'),
            "register_validator_js_retype_password_matched" =>
            tl('register_validator_js_retype_password_matched'),
            "register_validator_js_retype_password_not_matched" =>
            tl('register_validator_js_retype_password_not_matched'),
            ];
        if (!empty($data['INCLUDE_SCRIPTS']) &&
            in_array('register_validator', $data['INCLUDE_SCRIPTS'])) {
            $data['SCRIPT'] .= 'tl = '.json_encode($tl).';setSubmitStatus';
        }
        $this->displayView($view, $data);
    }
    /**
     * Sets up the form variables need to present the initial account creation
     * form. If this form is submitted with missing fields, this method
     * would also be called to set up an appropriate MISSING field
     *
     * @return array $data field correspond to values needed for account
     *     creation form
     */
    public function createAccount()
    {
        $data = $this->setupQuestionViewData();
        return $data;
    }
    /**
     * Used to process account data from completely filled in create account
     * forms. Depending on the registration type: no_activation,
     * email registration, or admin activation, either the account is
     * immediately activated or it is created in an active state and an email
     * to the person who could activate it is sent.
     *
     * @return array $data will contain a SCRIPT field with the
     *     Javascript doMessage call saying whether this step was successful
     *     or not
     */
    public function processAccountData()
    {
        $data = [];
        $this->getCleanFields($data);
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        switch (C\REGISTRATION_TYPE) {
            case 'no_activation':
                $data['REFRESH'] = "signin";
                $user_model->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL']);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_created')."</h1>')";
                break;
            case 'email_registration':
                $data['REFRESH'] = "signin";
                $user_model->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'],
                    C\INACTIVE_STATUS);
                $user = $user_model->getUser($data['USER']);
                $this->sendActivationMail($user, $data);
                if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
                    $num_questions = self::NUM_RECOVERY_QUESTIONS;
                } else {
                    $num_questions = 0;
                }
                $start = 0;
                for ($i = $start; $i < $num_questions; $i++) {
                    $j = $i - $start;
                    $_SESSION["RECOVERY_ANSWERS"][$j] =
                        $this->clean($_REQUEST["question_$i"],"string");
                }
                break;
            case 'admin_activation':
                $data['REFRESH'] = "signin";
                $user_model->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'],
                    C\INACTIVE_STATUS);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_request_made')."</h1>');";
                $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
                    C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
                    C\MAIL_SECURITY);
                $subject = tl('register_controller_admin_activation_request');
                $message = tl('register_controller_admin_activation_message',
                    $first_name, $last_name, $user_name);
                $server->send($subject, C\MAIL_SENDER, C\MAIL_SENDER, $message);
                break;
        }
        $user = $user_model->getUser($data['USER']);
        if (!empty($user['USER_ID'])) {
            $user_model->setUserSession($user['USER_ID'], $_SESSION);
        }
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        return $data;
    }
    /**
     * Use to send the account activation email
     *
     * @param array $user associative array of user
     * @param array $data field to be sent to view so can set sent mail
     *  interface message
     */
    function sendActivationMail($user, &$data)
    {
        $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
            C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
            C\MAIL_SECURITY);
        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_registration_email_sent').
            "</h1>');";
        $subject = tl('register_controller_admin_activation_request');
        $message = tl('register_controller_admin_email_salutation',
            $user['FIRST_NAME'], $user['LAST_NAME']). "\n";
        $message .= tl('register_controller_email_body')."\n";
        $creation_time = vsprintf('%d.%06d', gettimeofday());
        $message .= C\BASE_URL .
            "?c=register&a=emailVerification&email=".
            $user['EMAIL'].
            "&hash=" . urlencode(L\crawlCrypt($user['HASH'])).
            "&time=" . $user['CREATION_TIME'];
        $server->send($subject, C\MAIL_SENDER, $user['EMAIL'], $message);
    }
    /**
     * Used to verify the email sent to a user try to set up an account.
     * If the email is legit the account is activated
     *
     * @return array $data will contain a SCRIPT field with the
     *     Javascript doMessage call saying whether verification was
     *     successful or not
     */
    public function emailVerification()
    {
        $data = [];
        $data['REFRESH'] = "signin";
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        $clean_fields = ["email", "time", "hash"];
        $verify = [];
        $error = false;
        foreach ($clean_fields as $field) {
            if (isset($_REQUEST[$field])) {
                $verify[$field] = $this->clean($_REQUEST[$field], "string");
            } else {
                $error = true;
                break;
            }
        }
        if ($error) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_email_verification_error')."</h1>');";
        } else {
            $user = $user_model->getUserByEmailTime($verify["email"],
                $verify["time"]);
            if (isset($user['STATUS']) && $user['STATUS'] == C\ACTIVE_STATUS) {
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_already_activated')."</h1>');";
            } else {
                $hash = L\crawlCrypt($user["HASH"], $verify["hash"]);
                if (isset($user["HASH"]) && $hash == $verify["hash"]) {
                    $user_model->updateUserStatus($user["USER_ID"],
                        C\ACTIVE_STATUS);
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_account_activated')."</h1>');";
                } else {
                    $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_email_verification_error').
                        "</h1>');";
                    $this->model("visitor")->updateVisitor(
                        L\remoteAddress(), "captcha_time_out");
                }
            }
        }
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        $_SESSION['REMOTE_ADDR'] = L\remoteAddress();
        return $data;
    }
    /**
     * Sets up the form variables need to present the initial recover account
     * form. If this form is submitted with missing fields, this method
     * would also be called to set up an appropriate MISSING field
     *
     * @return array $data field correspond to values needed for account
     *     recovery form
     */
    public function recoverPassword()
    {
        $data = $this->setupQuestionViewData();
        $data['REFRESH'] = "recover";
        return $data;
    }
    /**
     * Called with the data from the initial recover form was completely
     * provided and captcha was correct. This method
     * sends the recover email provided the account had
     * recover questions set otherwise sets up an error message.
     *
     * @return array $data will contain a SCRIPT field with the
     *     Javascript doMessage call saying whether email sent or if there
     *     was a problem
     */
    public function processRecoverData()
    {
        $data = [];
        $this->getCleanFields($data);
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        $data["REFRESH"] = "signin";
        $user = $user_model->getUser($data['USER']);
        if (!$user) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            $this->model("visitor")->updateVisitor(
                L\remoteAddress(), "captcha_time_out");
            return $data;
        }
        $session = $user_model->getUserSession($user["USER_ID"]);
        if (!isset($session['RECOVERY']) ||
            (C\RECOVERY_MODE == C\NO_RECOVERY) ||
            (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY &&
            !isset($session['RECOVERY_ANSWERS']))) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_account_recover_fail')."</h1>');";
            return $data;
        }
        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_account_recover_email')."</h1>');";
        $server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
            C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
            C\MAIL_SECURITY);
        $subject = tl('register_controller_recover_request');
        $message = tl('register_controller_admin_email_salutation',
            $user['FIRST_NAME'], $user['LAST_NAME'])."\n";
        $message .= tl('register_controller_recover_body')."\n";
        $time = time();
        $message .= C\BASE_URL .
            "?c=register&a=recoverComplete&user=" .
            $user['USER_NAME'] .
            "&hash=".urlencode(L\crawlCrypt(
                $user['HASH'] . $time . $user['USER_NAME'].C\AUTH_KEY)) .
            "&time=" . $time ;
        $server->send($subject, C\MAIL_SENDER, $user['EMAIL'], $message);
        unset($_SESSION['RECOVERY_ANSWERS']);
        unset($_SESSION['RECOVERY']);
        return $data;
    }
    /**
     * This activity either verifies the recover email and sets up the
     * appropriate  data for a change password form or it verifies the
     * change password form data and changes the password. If verifications
     * fail, error messages are set up
     *
     * @return array form data to be used by recover or signin views
     */
    public function recoverComplete()
    {
        $data = [];
        $data['REFRESH'] = "signin";
        $user_model = $this->model("user");
        $visitor_model = $this->model("visitor");
        $fields = ["user", "hash", "time"];
        if (isset($_REQUEST['finish_hash'])) {
            $fields = ["user", "finish_hash", "time", "password",
                "repassword"];
        }
        $recover_fail = "doMessage('<h1 class=\"red\" >".
            tl('register_controller_account_recover_fail')."</h1>');";
        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $data[$field] = $this->clean($_REQUEST[$field], "string");
            } else {
                $data['SCRIPT'] = $recover_fail;
                return $data;
            }
        }
        $user = $user_model->getUser($data["user"]);
        if (empty($user["USER_ID"])) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_recover_fail')."</h1>');";
            return $data;
        }
        $user_session = $user_model->getUserSession($user["USER_ID"]);
        if (isset($data['finish_hash'])) {
            $finish_hash = urlencode(L\crawlCrypt($user['HASH'].$data["time"].
                $user['CREATION_TIME'] . C\AUTH_KEY,
                urldecode($data['finish_hash'])));
            if ($finish_hash != $data['finish_hash'] ||
                (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY &&
                !$this->checkRecoveryQuestions($user))) {
                $visitor_model->updateVisitor(
                    L\remoteAddress(), "captcha_time_out");
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_recover_fail')."</h1>');";
                return $data;
            }
            if ($data["password"] == $data["repassword"]) {
                if (isset($user_session['LAST_RECOVERY_TIME']) &&
                    $user_session['LAST_RECOVERY_TIME'] > $data["time"]) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_recovered_already')."</h1>');";
                    return $data;
                } else if (time() - $data["time"] > C\ONE_DAY) {
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_recovery_expired')."</h1>');";
                    return $data;
                } else {
                    $user["PASSWORD"] = $data["password"];
                    $user_model->updateUser($user);
                    $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_password_changed')."</h1>');";
                    $user_session['LAST_RECOVERY_TIME'] = time();
                    $user_model->setUserSession($user["USER_ID"],
                        $user_session);
                    return $data;
                }
            } else {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_passwords_dont_match')."</h1>');";
            }
        } else {
            $hash = L\crawlCrypt(
                $user['HASH'].$data["time"].$user['USER_NAME'].C\AUTH_KEY,
                $data['hash']);
            if ($hash != $data['hash']) {
                $visitor_model->updateVisitor(
                    L\remoteAddress(), "captcha_time_out");
                $data['SCRIPT'] = $recover_fail;
                return $data;
            } else if (isset($user_session['LAST_RECOVERY_TIME']) &&
                    $user_session['LAST_RECOVERY_TIME'] > $data["time"]) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_recovered_already')."</h1>');";
                return $data;
            } else if (time() - $data["time"] > C\ONE_DAY) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_recovery_expired')."</h1>');";
                return $data;
            }
        }
        if (!isset($user_session['RECOVERY']) ||
            (C\RECOVERY_MODE == C\NO_RECOVERY) ||
            (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY &&
            !isset($user_session['RECOVERY_ANSWERS']))) {
            $data['SCRIPT'] = $recover_fail;
            return $data;
        }
        $data['PASSWORD'] = "";
        $data['REPASSWORD'] = "";
        for ($i = 0; $i < self::NUM_RECOVERY_QUESTIONS; $i++) {
            $data["question_$i"] = "";
        }
        $data["RECOVERY"] = $user_session['RECOVERY'];
        $data["REFRESH"] = "recover";
        $data["RECOVER_COMPLETE"] = true;
        $data['finish_hash'] = urlencode(L\crawlCrypt($user['HASH'] .
            $data["time"]. $user['CREATION_TIME'] . C\AUTH_KEY));
        return $data;
    }
    /**
     * Sets up the form variables need to present the resend registration
     * form. If this form is submitted with missing fields, this method
     * would also be called to set up an appropriate MISSING field
     *
     * @return array $data field correspond to values needed for account
     *     creation form
     */
    public function resendRegistration()
    {
        $data = $this->setupQuestionViewData();
        $data['REFRESH'] = "resendEmail";
        return $data;
    }
    /**
     * This activity either verifies the recover email and sets up the
     * appropriate  data for a change password form or it verifies the
     * change password form data and changes the password. If verifications
     * error messages are set up
     *
     * @return array form data to be used by recover or signin views
     */
    public function resendComplete()
    {
        $data = [];
        $data['REFRESH'] = "signin";
        $data['SCRIPT'] = "";
        $user_model = $this->model("user");
        $visitor_model = $this->model("visitor");
        $fields = ["user"];
        $resend_fail = "doMessage('<h1 class=\"red\" >".
            tl('register_controller_email_resend_fail')."</h1>');";
        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $data[$field] = $this->clean($_REQUEST[$field], "string");
            } else {
                $data['SCRIPT'] = $resend_fail;
                return $data;
            }
        }
        $user = $user_model->getUser($data["user"]);
        if (empty($user) || empty($user['STATUS']) || empty($user['USER_ID'])
            || empty($user['EMAIL']) || empty($user['FIRST_NAME']) ||
             empty($user['LAST_NAME'])) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_email_resend_fail')."</h1>');";
            return $data;
        } else if ($user['STATUS'] == C\ACTIVE_STATUS) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_user_active_fail')."</h1>');";
            return $data;
        }
        $this->sendActivationMail($user, $data);
        if (!empty($user['USER_ID'])) {
            $user_model->setUserSession($user['USER_ID'], $_SESSION);
        }
        return $data;
    }
    /**
     * Used to handle data from the suggest-a-url to crawl form
     * (suggest_view.php). Basically, it saves any data submitted to
     * a file which can then be imported in manageCrawls
     *
     * @return array $data contains fields with the current value for
     *     the url (if set but not submitted) as well as for a captcha
     */
    public function suggestUrl()
    {
        $data["REFRESH"] = "suggest";
        $visitor_model = $this->model("visitor");
        $crawl_model = $this->model("crawl");
        $clear = false;
        if (C\CAPTCHA_MODE != C\IMAGE_CAPTCHA && isset($_SESSION)) {
            unset($_SESSION["captcha_text"]);
        }
        if (C\CAPTCHA_MODE != C\HASH_CAPTCHA && isset($_SESSION)) {
            unset($_SESSION["request_time"], $_SESSION["level"],
                $_SESSION["random_string"] );
        } else {
            $data['INCLUDE_SCRIPTS'] = ["sha1", "hash_captcha"];
        }
        if (!isset($_SESSION['BUILD_TIME']) || !isset($_REQUEST['build_time'])||
            $_SESSION['BUILD_TIME'] != $_REQUEST['build_time'] ||
            $this->clean($_REQUEST['build_time'], "int") <= 0) {
            if (C\CAPTCHA_MODE == C\HASH_CAPTCHA) {
                $time = time();
                $_SESSION["request_time"] = $time;
                $_SESSION["level"] = self::HASH_CAPTCHA_LEVEL;
                $_SESSION["random_string"] = md5( $time . C\AUTH_KEY);
            }
            $clear = true;
            if (isset($_REQUEST['url'])) {
                unset($_REQUEST['url']);
            }
            if (isset($_REQUEST['arg'])) {
                unset($_REQUEST['arg']);
            }
            $data['build_time'] = time();
            $_SESSION['BUILD_TIME'] = $data['build_time'];
        } else {
            $data['build_time'] = $_SESSION['BUILD_TIME'];
        }
        $data['url'] = "";
        if (isset($_REQUEST['url'])) {
            $data['url'] = $this->clean($_REQUEST['url'], "string");
        }
        $missing = [];
        $save = isset($_REQUEST['arg']) && $_REQUEST['arg'];
        $data['MISSING'] = $missing;
        $fail = false;
        if (C\CAPTCHA_MODE == C\IMAGE_CAPTCHA && !$save) {
            $this->setupGraphicalCaptchaViewData($data);
        }
        if ($save && isset($_REQUEST['url'])) {
            $url = $this->clean($_REQUEST['url'], "string");
            $url_parts = @parse_url($url);
            if (!isset($url_parts['scheme'])) {
                $url = "http://".$url;
            }
            $suggest_host = UrlParser::getHost($url);
            $scheme = UrlParser::getScheme($url);
            if (strlen($suggest_host) < 12 || !$suggest_host ||
                !in_array($scheme, ["http", "https"])) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_invalid_url')."</h1>');";
                $fail = true;
            } else  if ($missing != []) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_error_fields')."</h1>');".
                    "checkAll();";
                $fail = true;
            }
            if (C\CAPTCHA_MODE == C\IMAGE_CAPTCHA && $fail) {
                $this->setupGraphicalCaptchaViewData($data);
            }
            if ($fail) {
                return $data;
            }
            switch (C\CAPTCHA_MODE) {
                case C\HASH_CAPTCHA:
                    if (!$this->validateHashCode()) {
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_hashcode')."</h1>');";
                        $visitor_model->updateVisitor(
                            L\remoteAddress(), "captcha_time_out");
                        return $data;
                    }
                    break;
                case C\IMAGE_CAPTCHA:
                    $user_captcha_text = isset($_REQUEST['user_captcha_text']) ?
                        $this->clean($_REQUEST['user_captcha_text'],"string") :
                        "";
                    if (isset($_SESSION['captcha_text']) &&
                        $_SESSION['captcha_text'] != trim($user_captcha_text)) {
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('register_controller_failed_graphical_human').
                            "</h1>');";
                        unset($_SESSION['captcha_text']);
                        $this->setupGraphicalCaptchaViewData($data);
                        $visitor_model->updateVisitor(
                            L\remoteAddress(), "captcha_time_out");
                        return $data;
                    }
                    $this->setupGraphicalCaptchaViewData($data);
                    break;
            }
            // Handle cases where captcha was okay
            if (C\DIRECT_ADD_SUGGEST) {
                $machine_urls = $this->model("machine")->getQueueServerUrls();
                $status = $crawl_model->crawlStatus($machine_urls);
                if (empty($status['CRAWL_TIME'])) {
                    $seed_info = $crawl_model->getSeedInfo();
                    $seed_info['seed_sites']['url'][] = "#\n#" .
                        date('r')."\n#";
                    $seed_info['seed_sites']['url'][] = $url;
                    $crawl_model->setSeedInfo($seed_info);
                } else {
                    $timestamp = $status['CRAWL_TIME'];
                    $seed_info = $crawl_model->getCrawlSeedInfo(
                        $timestamp, $machine_urls);
                    $seed_info['seed_sites']['url'][] = "#\n#" .
                        date('r')."\n#";
                    $seed_info['seed_sites']['url'][] = $url;
                    $crawl_model->setCrawlSeedInfo($timestamp,
                        $seed_info, $machine_urls);
                    $crawl_model->injectUrlsCurrentCrawl(
                        $timestamp, [$url], $machine_urls);
                }
            } else if (!$crawl_model->appendSuggestSites($url)) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_suggest_full')."</h1>');";
                return $data;
            }
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_url_submitted')."</h1>');";
            $visitor_model->updateVisitor(
                L\remoteAddress(), "suggest_day_exceeded",
                C\ONE_DAY, C\ONE_DAY, C\MAX_SUGGEST_URLS_ONE_DAY);
            $data['build_time'] = time();
            $_SESSION['BUILD_TIME'] = $data['build_time'];
            $data['url'] ="";
        }
        return $data;
    }
    /**
     * Sets up the captcha question and or recovery questions in a $data
     * associative array so that they can be drawn by the register or recover
     * views.
     *
     * @return array $data associate array with field to help the register and
     *     recover view draw themselves
     */
    public function setupQuestionViewData()
    {
        $data = [];
        $data['INCLUDE_SCRIPTS'] = ["register_validator"];
        $fields = $this->register_fields;
        foreach ($fields as $field) {
            $data[strtoupper($field)] = "";
        }
        if (C\CAPTCHA_MODE == C\HASH_CAPTCHA) {
            $data['INCLUDE_SCRIPTS'] = array_unique(
                array_merge($data['INCLUDE_SCRIPTS'],
                ["sha1", "hash_captcha"]));
            $time = time();
            $_SESSION["request_time"] = $time;
            $_SESSION["level"] = self::HASH_CAPTCHA_LEVEL;
            $_SESSION["random_string"] = md5( $time . C\AUTH_KEY );
        }
        if (C\CAPTCHA_MODE == C\IMAGE_CAPTCHA) {
            $this->setupGraphicalCaptchaViewData($data);
        }
        if (!isset($_SESSION['RECOVERY'])) {
            list($data['RECOVERY'], ) = $this->selectQuestionsAnswers(
                $this->recovery_qa, self::NUM_RECOVERY_QUESTIONS);
            $_SESSION['RECOVERY'] = $data['RECOVERY'];
        } else {
            $data['RECOVERY'] = $_SESSION['RECOVERY'];
        }
        return $data;
    }
    /**
     * Sets up the graphical captcha view
     * Draws the string for graphical captcha
     *
     * @param array &$data used by view to draw any dynamic content
     *     in this case we append a field "CAPTCHA_IMAGE" with a data
     *     url of the captcha to draw.
     */
    public function setupGraphicalCaptchaViewData(&$data)
    {
        if (empty($_SESSION)) {
            $_SESSION = [];
        }
        unset($_SESSION["captcha_text"]);
        // defines captcha text
        $characters_for_captcha = '123456789abcdefghijklmnpqrstuvwxyz'.
            'ABCDEFGHIJKLMNPQRSTUVWXYZ';
        $len = strlen($characters_for_captcha);
        // selecting letters for captcha
        $captcha_letter = $characters_for_captcha[rand(0, $len - 1)];
        $word = "";
        for ($i = 0; $i < C\CAPTCHA_LEN; $i++) {
            // selecting letters for captcha
            $captcha_letter = $characters_for_captcha[rand(0, $len - 1)];
            $word = $word . $captcha_letter;
         }
         // stores the captcha in a session variable 'captcha_text'
        $_SESSION['captcha_text'] = $word;
        $data["CAPTCHA_IMAGE"] =
            $this->model("captcha")->makeGraphicalCaptcha($word);
    }
    /**
     * Gets a list of translated recovery questions from the register view.
     * If insufficiently many questions have been translated for the current
     * locale backfill with default locale
     *
     * @param string $register_view view with register for an account form
     *  since auto checked for string to translate, have but a list of
     *  translated recovery string there.
     * @param string $locale which locale to get the recovery questions for
     * @return array of translated recovery questions
     */
    public static function getRecoveryQuestions($register_view, $locale)
    {
        $num_recovery_qa = count($register_view->recovery_qa);
        $out_recovery_qa = [];
        for ($i = 0; $i < $num_recovery_qa; $i++) {
            if ($locale->isTranslated("register_view_recovery{$i}_more") &&
                $locale->isTranslated("register_view_recovery{$i}_less") &&
                $locale->isTranslated("register_view_recovery$i}_choices")) {
                $out_recovery_qa[] = $register_view->recovery_qa[$i];
            }
         }
        $num_so_far = count($out_recovery_qa);
        //If current locale didn't have enough questions fill in from default
        if ($num_so_far < RegisterController::NUM_RECOVERY_QUESTIONS) {
            $recovery_qa = $register_view->recovery_qa;
            foreach ($recovery_qa as $recovery_qa) {
                if (strpos($recovery_qa[0], "register_view_recovery")===false &&
                    strpos($recovery_qa[1], "register_view_recovery")===false &&
                    strpos($recovery_qa[2], "register_view_recovery")===false){
                    $out_recovery_qa[] = $recovery_qa;
                    $num_so_far++;
                    if ($num_so_far >=
                        RegisterController::NUM_RECOVERY_QUESTIONS) { break; }
                }
            }
        }
        return $out_recovery_qa;
    }
    /**
     * Picks $num_select most/least questions from an array of triplets of
     * the form a string question: Which is the most ..?, a string
     * question: Which is the least ..?, followed by a comma separated list
     * of choices ranked from least to most. For each question pick,
     * $num_choices many items from the last element of the triplet are
     * chosen.
     *
     * @param array $question_answers an array t_1, t_2, t_3, t_4, where
     *     each t_i is an associative array containing the most
     *     and least arrays as described above
     * @param int $num_select number of triples from the list to pick
     *     for each triple pick either the most question or the least
     *     question
     * @param int $num_choices from the list component of a triplet we
     *     we pick this many elements
     * @return array a pair consisting of an array of questions and possible
     *     choice for least/most, and another array of the correct answers
     *     to the least/most problem.
     */
    public static function selectQuestionsAnswers($question_answers,$num_select,
        $num_choices = -1)
    {
        $questions = [];
        $answers = [];
        $size_qa = count($question_answers);
        for ($i = 0; $i < $num_select; $i++) {
            do {
                $question_choice = mt_rand(0, $size_qa - 1);
            } while (isset($questions[$question_choice]));
            $more_less = rand(0, 1);
            $answer_possibilities =
                explode(",", $question_answers[$question_choice][2]);
            $selected_possibilities = [];
            $size_possibilities = count($answer_possibilities);
            if ($num_choices < 0) {
                $num = $size_possibilities;
            } else {
                $num = $num_choices;
            }
            for ($j = 0; $j < $num; $j++) {
                do {
                    $selected_possibility = mt_rand(0, $size_possibilities - 1);
                } while(isset($selected_possibilities[$selected_possibility]));
                $selected_possibilities[$selected_possibility] =
                    $answer_possibilities[$selected_possibility];
            }
            $questions[$question_choice] = ["-1" =>
                    $question_answers[$question_choice][$more_less]];
            $tmp = array_values($selected_possibilities);
            $questions[$question_choice] +=  array_combine($tmp, $tmp);
            if ($more_less) {
                ksort($selected_possibilities);
                $selected_possibilities = array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
            } else {
                krsort($selected_possibilities);
                $selected_possibilities = array_values($selected_possibilities);
                $answers[$i] = $selected_possibilities[0];
            }
        }
        $questions = array_values($questions);
        return [$questions, $answers];
    }
    /**
     * Used to select which activity a controller will do. If the $activity
     * is $activity_success, then this method checks the prereqs for
     * $activity_success. If they are not met then the view $data array is
     * updated with an error message and $activity_fail is set to be the
     * next activity. If the prereq is met then the $activity is left as
     * $activity_success. If $activity was not initially equal to
     * $activity_success then this method does nothing.
     *
     * @param string &$activity current tentative activity
     * @param string $activity_success activity to test for and to test prereqs
     *     for.
     * @param string $activity_fail if prereqs not met which acitivity to switch
     *     to
     * @param array &$data data to help render the view this controller draws
     */
    public function preactivityPrerequisiteCheck(&$activity,
        $activity_success, $activity_fail, &$data)
    {
        $profile_model = $this->model("profile");
        $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
        if ($activity == $activity_success) {
            $this->dataIntegrityCheck($data);
            if (!$data["SUCCESS"]) {
                $activity = $activity_fail;
                return;
            }
            switch (C\CAPTCHA_MODE) {
                case C\IMAGE_CAPTCHA:
                    if (empty($_SESSION['captcha_text']) ||
                        $_SESSION['captcha_text'] !=
                        $_REQUEST['user_captcha_text']) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                        tl('register_controller_failed_graphical_human').
                            "</h1>');";
                        if (!empty($_SESSION['captcha_text'])) {
                            unset($_SESSION['captcha_text']);
                        }
                        $this->model("visitor")->updateVisitor(
                            L\remoteAddress(), "captcha_time_out");
                        $activity = $activity_fail;
                    }
                    break;
                case C\HASH_CAPTCHA:
                    if (!$this->validateHashCode()) {
                        $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                            tl('register_controller_failed_hashcode').
                            "</h1>');";
                        $this->model("visitor")->updateVisitor(
                            L\remoteAddress(), "captcha_time_out");
                        $activity = $activity_fail;
                    }
                    break;
            }
        }
    }
    /**
     * Add SCRIPT tags for errors to the view $data array if there were any
     * missing fields on a create account or recover account form.
     * also adds error info if try to create an existing using.
     *
     * @param array &$data contains info for the view on which the above
     *     forms are to be drawn.
     */
    public function dataIntegrityCheck(&$data)
    {
        if (!isset($data['SCRIPT'])) {
            $data['SCRIPT'] = "";
        }
        $data['SUCCESS'] = true;
        $this->getCleanFields($data);
        if ($data['MISSING'] != []) {
            $data['SUCCESS'] = false;
            $message = "doMessage('<h1 class=\"red\" >".
                tl('register_controller_error_fields')."</h1>');";
            if ($data['MISSING'] == ["email"]) {
                $message = "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_check_email')."</h1>');";
            }
            $data['SCRIPT'] .= $message;
        } else if (isset($data["check_user"]) &&
            $this->model("user")->getUserId($data['USER'])) {
            $data['SUCCESS'] = false;
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_user_already_exists')."</h1>');";
        }
    }
    /**
     * Checks whether the answers to the account recovery questions match
     * those provided earlier by an account user
     *
     * @param array $user who to check recovery answers for
     * @return bool true if only if all were correct
     */
    public function checkRecoveryQuestions($user)
    {
        $user_session = $this->model("user")->getUserSession($user["USER_ID"]);
        if (!isset($user_session['RECOVERY_ANSWERS'])) {
            return false;
        }
        $recovery_passed = true;
        for ($i = 0; $i < self::NUM_RECOVERY_QUESTIONS; $i++) {
            $field = "question_".$i;
            if ($_REQUEST[$field] != $user_session['RECOVERY_ANSWERS'][$i]) {
                $recovery_passed = false;
                $this->model("visitor")->updateVisitor(
                    L\remoteAddress(), "captcha_time_out");
                break;
            }
        }
        return $recovery_passed;
    }
    /**
     * Used to clean the inputs for form variables
     * for creating/recovering an account. It also puts
     * in blank values for missing fields into a "MISSING"
     * array
     *
     * @param array &$data an array of data to be sent to the view
     *     After this method is done it will have cleaned versions
     *     of the $_REQUEST variables from create or recover account
     *     forms as well as a "MISSING" field which is an array of
     *     those items which did not have values on the create/recover
     *     account form
     */
    public function getCleanFields(&$data)
    {
        $fields = $this->register_fields;
        if (isset($data["check_fields"])) {
            $fields = $data["check_fields"];
        }
        if (!isset($data["SCRIPT"])) {
            $data["SCRIPT"] = "";
        }
        $missing = [];
        $regex_email=
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+'.
            '(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        foreach ($fields as $field) {
            if (!isset($_REQUEST[$field]) || !trim($_REQUEST[$field])) {
                $error = true;
                $missing[] = $field;
                $data[strtoupper($field)] = "";
            } else if ($field == "email" &&
                !preg_match($regex_email,
                $this->clean($_REQUEST['email'], "string" ))) {
                $error = true;
                $missing[] = "email";
                $data[strtoupper($field)] = "";
            } else if ($field == "user" && stripos($this->forbidden_usernames,
                $_REQUEST[$field]) !== false && $_REQUEST['a'] ==
                'createAccount') {
                $error = true;
                $missing[] = "user";
                $data[strtoupper($field)] = "";
            } else {
                $data[strtoupper($field)] = $this->clean($_REQUEST[$field],
                    "string");
                if (!in_array($field, ['password','repassword'])) {
                    $data[strtoupper($field)] = trim($data[strtoupper($field)]);
                }
            }
        }
        if (isset($_REQUEST['password'])
            && isset($_REQUEST['repassword']) &&
            (strlen($_REQUEST['password']) > C\LONG_NAME_LEN
            || $this->clean($_REQUEST['password'], "string" ) !=
            $this->clean($_REQUEST['repassword'], "string" ))) {
            $error = true;
            $missing[] = "password";
            $missing[] = "repassword";
            $data["PASSWORD"] = "";
            $data["REPASSWORD"] = "";
        }
        if (C\RECOVERY_MODE == C\EMAIL_AND_QUESTIONS_RECOVERY) {
            $num_questions = self::NUM_RECOVERY_QUESTIONS;
        } else {
            $num_questions = 0;
        }
        for ($i = 0; $i < $num_questions; $i++) {
            $field = "question_$i";
            if (!in_array($field, $fields)) {
                continue;
            }
            $recovery = isset($_SESSION['RECOVERY'][$i]) ?
                $_SESSION['RECOVERY'][$i] : [];
            $current_dropdown = $recovery;
            if (!isset($_REQUEST[$field]) || $_REQUEST[$field] == "-1" ||
                !in_array($_REQUEST[$field], $current_dropdown)) {
                $missing[] = $field;
            } else {
                $data[$field] = $_REQUEST[$field];
            }
        }
        $data['MISSING'] = $missing;
    }
     /**
     * Calculates the sha1 of a string consist of a randomString,request_time
     * send by a server and the nonce send by a client.It checks
     * whether the sha1 produces expected number of a leading zeroes
     *
     * @return bool true if the sha1 produces expected number
     * of a leading zeroes.
     */
    public function validateHashCode()
    {
        $hex_key = $_SESSION["random_string"].':'.$_SESSION["request_time"].
            ':'.$_REQUEST['nonce_for_string'];
        $pattern = '/^0{'.$_SESSION['level'].'}/';
        $time = time();
        $_SESSION["request_time"] = $time;
        $_SESSION["random_string"] =  md5( $time . C\AUTH_KEY );
        if ((time() - $_SESSION["request_time"] < self::HASH_TIMESTAMP_TIMEOUT)
           && (preg_match($pattern, sha1($hex_key) ))){
            return true;
        }
        return false;
    }
}
