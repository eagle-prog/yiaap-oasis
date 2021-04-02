<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2021  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Harika Nukala harika.nukala@sjsu.edu
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\examples\weatherbot;

/**
 * This class demonstrates a simple Weather Chat Bot using the Yioop
 * ChatBot APIs for Yioop Discussion Groups.
 * To use this bot:
 * (0) Move this file to some folder of a web server you have access to.
 *     Denote by some_url the url of this folder. If you point your
 *     browser at this folder you should see a message that begins with:
 *     There was a configuration issue with your query.
 * (1) Get an api key from openweathermap.org and fill it in below.
 * (2) Create a new Yioop User.
 * (3) Under Manage Accounts, click on the lock symbol next to Account Details
 * (4) Check the Bot User check box, click save.
 * (5) Two form variables should appear: Bot Unique Token and Bot Callback URL.
 *      Fill in a value for Bot Unique Token that matches the value set
 *      for ACCESS_TOKEN in the code within the WeatherBot class.
 *      Fill in some_url (as defined in step (1)) for the value of Bot Callback
 *      URL
 * (6) Add the the user you created in Yioop to the group that you would like
 *     the bot to service. Let the name of this user be user_name.
 * (7) When logged into the user_name account you should now see a Bot Story
 *     activity in the activity side bar. Click on Bot Story. This activity
 *     lets you add patterns that tell your chat bot how to react when it
 *     sees various expresssions. To demo your bot add the following three
 *     patterns:
 *      (a) Request Expression: What is your name?
 *          Trigger State: 0
 *          Remote Message:
 *          Result State: 0
 *          Response: Weatherbot
 *          This first pattern just says if someone does a query of the form:
 *          @user_name What is your name?
 *          your bot should respond with:
 *          Weatherbot
 *      (b) Request Expression: What is the weather in $location?
 *          Trigger State: 0
 *          Remote Message: getWeather,$location
 *          Result State: 0
 *          Response: $REMOTE_RESPONSE
 *          This second pattern will respond to queries of the form
 *          "What is the weather in $location?" For example,
 *          What is the weather in San Jose?
 *          It will then use the Bot Callback url to make a request of
 *          this script where the remote_message variable is set to
 *          getWeather,$location For example, in the case above,
 *          getWeather,San Jose
 *          The script below uses Yahoo's Api to get the weather for the
 *          request location and generate a response that it
 *          sends back to the chatbot in a variable $REMOTE_RESPONSE which
 *          it then uses in its response. This might look like:
 *          The weather is 55 and partly cloudy in San Jose.
 *      (c) Request Expression: Which is warmer $location1 or $location2?
 *          Trigger State: 0
 *          Remote Message: getWarmer,$location1,$location2
 *          Result State: 0
 *          Response: $REMOTE_RESPONSE
 *          This pattern is similar to (b) except it demonstrate that you
 *          are allowed to have multiple variables in the Request Expression.
 *          This particular rules matches queries like:
 *          Which is warmer San Jose or Toronto?
 *          Sends a request to this script with a remote_message:
 *          getWarmer,San Jose,Toronto
 *          This script then generates a response that the bot receives as
 *          $REMOTE_RESPONSE and the bot echos it:
 *          San Jose at 56 is warmer than Toronto at 52
 * (8) Talk to your bot in yioop in this groups by logging in as a different
 *     user and posting a thread or commenting on a thread in a group you added
 *     the bot to with a message beginning with @user_name.
 */
class WeatherBot
{
    /**
     * Url of site that this bot gets weather information from
     */
    const WEATHER_URL = "https://api.openweathermap.org/data/2.5/weather";
    /**
     * API KEY of site that this bot gets weather information from
     */
    const API_KEY = "";
    /**
     * metric, default, or imperial units for temperature
     */
    const UNITS = "imperial";
    /**
     * Token given when setting up the bot in Yioop  for callback requests
     * This bots checks that a request from a Yioop Intance  sends
     * a timestamp as well as the hash of this timestamp with the bot_token
     * and post data and that these match the expected values
     */
    const ACCESS_TOKEN = "1234";
    /**
     * Number of seconds that the passed timestamp can differ from the current
     * time on the WeatherBot machine.
     */
    const TIME_WINDOW = 60;
    /**
     * This is the method called to get the WeatherBot to handle an incoming
     * HTTP request, and echo a weather related message
     */
    function processRequest()
    {
        $result = "There was a configuration issue with your query.";
        if ($this->checkBotToken() && !empty($_REQUEST['post']) &&
            !empty($_REQUEST['bot_name']) &&
            !empty($_REQUEST['remote_message'])) {
            $message = filter_var($_REQUEST['remote_message'],
                FILTER_SANITIZE_STRING);
            $args = explode(",", $message);
            $action = array_shift($args);
            if (in_array($action, ["getWeather", "getWarmer"])) {
                $result = $this->$action($args);
            }
        }
        echo $result;
    }
    /**
     * This method is used to check a request that it comes from a site
     * that knows the bot_token in use by this WeatherBot.
     */
    function checkBotToken()
    {
        if (!empty($_REQUEST['bot_token'])) {
            $token_parts = explode("*", $_REQUEST['bot_token']);
            $post = empty($_REQUEST["post"]) ? "" : $_REQUEST["post"];
            $hash = hash("sha256", self::ACCESS_TOKEN . $token_parts[1].
                $post);
            if (isset($token_parts[1]) &&
                abs(time() - $token_parts[1]) < self::TIME_WINDOW) {
                // second check avoids timing attacks, works for > php 5.6
                if ((!function_exists('hash_equals') &&
                    $hash == $token_parts[0]) ||
                    hash_equals($hash, $token_parts[0])) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Get weather information about a location
     *
     * @param array $args the value of $args[0] should have
     *      the location to get weather update for
     * @return string weather information
     */
    function getWeather($args)
    {
        $result = $this->getRawWeatherData($args[0]);
        $temp = $result->main->temp ?? "";
        $text = empty($result->weather[0]->description) ?
            "" : mb_strtolower($result->weather[0]->description);
        if (empty($temp) || empty($text)) {
            return "";
        }
        return "The weather is $temp with $text in {$args[0]}.";
    }
    /**
     * Return which location is warmer, the one stored in $args[0] or the
     * one stored in $args[1]
     *
     * @param array $args the value of $args[0] should have the
     *      location to get weather update for
     * @return string weather information
     */
    function getWarmer($args)
    {
        if (empty($args[0]) || empty($args[1])) {
            return "";
        }
        $result = $this->getRawWeatherData($args[0]);
        $tmp0 = $result->main->temp ?? "";
        if (empty($tmp0)) {
            return "";
        }
        $result = $this->getRawWeatherData($args[1]);
        $tmp1 = $result->main->temp ?? "";
        if (empty($tmp1)) {
            return "";
        }
        $out = "";
        if (intval($tmp0) < intval($tmp1)) {
            $out = "{$args[1]} at $tmp1 is warmer than {$args[0]} at $tmp0";
        } else if (intval($tmp0) == intval($tmp1)) {
            $out = "{$args[0]} and {$args[1]} are both $tmp0";
        } else {
            $out = "{$args[0]} at $tmp0 is warmer than {$args[1]} at $tmp1";
        }
        return $out;
    }
    /**
     * Get json data about a location from weather service
     *
     * @param array $location to get weather update for
     * @return object weather information
     */
    function getRawWeatherData($location)
    {
        $url = self::WEATHER_URL . "?APPID=". self::API_KEY . "&units=".
            self::UNITS . "&q=" . urlencode($location);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        return @json_decode($data);
    }
}
$bot = new WeatherBot();
$bot->processRequest();
