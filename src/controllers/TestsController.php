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
use seekquarry\yioop\tests as T;
use seekquarry\yioop\library\UnitTest;
use seekquarry\yioop\library\JavascriptUnitTest;
use seekquarry\yioop\library\BrowserRunner;

/**
 *Error handler so  catch errors as exceptions too
 *
 * @param int $errno number code of error
 * @param string $errstr text of error message
 * @param string $errfile filename of file in which error occurred
 * @param int $errline line number of error
 */
function exceptionErrorHandler($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
/**
 * don't try to use cache
 * @ignore
 */
$_SERVER["USE_CACHE"] = false;
/**
 * Do not send output to log files
 * @ignore
 */
$_SERVER["LOG_TO_FILES"] = false;
/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Chris Pollett
 */
class TestsController extends Controller
{
    /**
     * Handles requests to list all tests, run all test cases, or run a
     * particular test case
     */
    public function processRequest()
    {
        $view = 'tests';
        set_error_handler(C\NS_CONTROLLERS . "exceptionErrorHandler");
        $allowed_activities = ["listTests", "runAllTests", "runTest"];
        try {
            $tmp = new BrowserRunner();
            $allowed_activities[] = "runBrowserTests";
            C\nsconddefine("BROWSER_TESTS", true);
        } catch (\Exception $e) {
            C\nsconddefine("BROWSER_TESTS", false);
        }
        $data = [];
        set_error_handler(null);
        $signin_model = $this->model("signin");
        if (isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
            $data['USERNAME'] = $signin_model->getUserName($user_id);
            $_SESSION['USER_NAME'] = $data['USERNAME'];
        } else {
            $user_id = C\PUBLIC_GROUP_ID;
        }
        $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user_id);
        if (!empty($data['ADMIN']) && !$token_okay) {
            unset($data['ADMIN']);
        }
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user_id);
        $_SERVER['NO_LOGGING'] = true;
        if (isset($_REQUEST['activity']) &&
            in_array($_REQUEST['activity'], $allowed_activities)) {
            $activity = $_REQUEST['activity'];
        } else {
            $activity = "listTests";
        }
        $data = array_merge($data, $this->$activity());
        if (!empty($data['ERROR'])) {
            include(C\BASE_DIR . "/error.php");
            \seekquarry\yioop\library\webExit();
        }
        $this->displayView($view, $data);
    }
    /**
     * This function is responsible for getting a list of test case names
     * from the folder tests so that this list of names can eventually
     * be displayed to the user.
     */
    function listTests()
    {
        $data = ['ACTIVITY' => 'list'];
        $data['TEST_NAMES'] = [];
        $names = $this->getTestNames();
        foreach ($names as $name) {
            $test_name = $this->getClassNameFromFileName($name);
            $data['TEST_NAMES'][] = $test_name;
        }
        return $data;
    }
    /**
     * This function runs the PhantomJS tests by calling the Browser shell to
     * execute the PhantomJs tests written in JavaScript. The script
     * phantomjs_runner.js is invoked to run tests which are written as a
     * sequence of steps that the Phantomjs client performs. Currently,
     * these are two test files that can be tested using phantomjs_runner.js
     * web_ui_test.js and mobile_ui_test.js. One can run these tests from
     * the command line by appropriately modifying:
     *
     * phantomjs phantomjs_runner.js URL_OF_YIOOP_INSTANCE web_or_mobile \
     *   1 admin_login admin_password
     *
     * Here the word web_or_mobile should be replaced with either the word web or
     * the word mobile depending on the test suite desired and admin_login and
     * password should be replace with the yioop admin log and admin password.
     */
    function runBrowserTests()
    {
        $mode = "";
        $resp_code = "";
        $u = $_REQUEST['u'];
        $p = $_REQUEST['p'];
        if (isset($_REQUEST['mode'])) {
            if (!empty($_REQUEST['debug'])) {
                $debug = true;
            } else {
                $debug = "false";
            }
            $mode = htmlentities($_REQUEST['mode'], ENT_QUOTES, "UTF-8");
            $resp = [];
            if (!in_array($mode, ["web", "mobile"])) {
                $resp_code = "HTTP/1.1 400 Bad Request";
            } else {
                try {
                    $browser_runner = new BrowserRunner();
                    $test_results = $browser_runner->execute(C\TEST_DIR .
                        "/phantomjs_runner.js", C\BASE_URL, $mode, $debug, $u,
                        $p);
                    if (!$test_results) {
                        $resp_code = "HTTP/1.1 500";
                    } else {
                        $resp['results'] = $test_results;
                        $resp_code = "HTTP/1.1 200 OK";
                    }
                } catch (Exception $e) {
                    $resp_code = "HTTP/1.1 500";
                    $resp['error'] = $e->getMessage();
                }
            }
        } else {
            $resp_code = "HTTP/1.1 500";
            $resp['error'] = "Bad Request";
        }
        $this->web_site->header($resp_code);
        $this->web_site->header("Content-Type:application/json");
        echo json_encode($resp);
        \seekquarry\yioop\library\webExit();
    }

    /**
     * This is a utility function to get the Full URL of the current page.
     * @param bool $strip_query_params whether to get rid of the query string
     *      or not
     * @return string full url
     */
    function getFullURL($strip_query_params = false)
    {
        $page_url = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on")
            ? "https://" : "http://";
        if (!in_array($_SERVER["SERVER_PORT"], ["80", "443"])) {
            $page_url .= $_SERVER["SERVER_NAME"] . ":" .
                $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
        } else {
            $page_url .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
        }
        //return full URL with query params stripped, if requested.
        return $strip_query_params ? strtok($page_url, '?') : $page_url;
    }
    /**
     * Runs all the unit_tests in the current directory and displays the results
     */
    function runAllTests()
    {
        $data = ['ACTIVITY' => 'render_all_tests'];
        $names = $this->getTestNames();
        $data['ALL_RESULTS'] = [];
        foreach ($names as $name) {
            $class_name = $this->getClassNameFromFileName($name);
            $_REQUEST['test'] = $class_name;
            $test_results = $this->runTest();
            $data['ALL_RESULTS'][$class_name] = $test_results['RESULTS'];
            if (!empty($test_results['ERROR'])) {
                $data['ERROR'] = $test_results['ERROR'];
            }
        }
        return $data;
    }
    /**
     * Run the single unit test whose name is given in $_REQUEST['test'] and
     * display the results. If the unit test file was blah_test.php, then
     * $_REQUEST['test'] should be blah.
     */
    function runTest()
    {
        $data = ['ACTIVITY' => 'render_test', 'RESULTS' => []];
        if (isset($_REQUEST['test'])) {
            //clean name
            $name = preg_replace("/[^A-Za-z_0-9]/", '', $_REQUEST['test']);
            $data['TEST_NAME'] = $name;
            if (!file_exists(C\TEST_DIR . "/$name.php")) {
                $data['ERROR'] = true;
                return $data;
            }
            $class_name = C\NS_TESTS . $name;
            $test = new $class_name();
            if ($class_name == C\NS_TESTS ."PhantomjsUiTest" &&
                !C\BROWSER_TESTS) {
                $data['PHANTOMJS_REQUIRED'] = true;
                return $data;
            } elseif ($test instanceof JavascriptUnitTest) {
                $data['RESULTS'] = [ 'JS' => true, 'DATA' => $test->run()];
            } else {
                $data['RESULTS'] = $test->run();
            }
        } else {
            $data['ERROR'] = true;
        }
        return $data;
    }
    /**
     * Gets the names of all the unit test files in the current directory.
     * Doesn't really check for this explicitly, just checks if the file
     * end with _test.php
     *
     * @return array an array of unit test files
     */
    function getTestNames()
    {
        return glob(C\TEST_DIR .'/*Test.php');
    }
    /**
     * Convert the unit test file names into unit test class names
     *
     * @param string $name  a file name with words separated by underscores, ending
     * in .php
     *
     * @return string  a camel-cased name ending with Test
     */
    function getClassNameFromFileName($name)
    {
        //strip .php
        $class_name = substr($name, 0, - strlen(".php"));
        $class_name = substr($class_name, strlen(C\TEST_DIR) + 1);
        return $class_name;
    }
}
