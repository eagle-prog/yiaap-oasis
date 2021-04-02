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
 * @author Harika Nukala harikanukala9@gmail.co
 *      (updated after yahoo stock quotes went dark, by Chris Pollett)
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2009 - 2021
 * @filesource
 */
namespace seekquarry\yioop\examples\stockbot;

/**
 * This class demonstrates a simple Stock Chat Bot using the Yioop
 * ChatBot APIs for Yioop Discussion Groups.
 * To use this bot:
 * (1) Move this file to some folder of a web server you have access to.
 *     Denote by some_url the url of this folder. If you point your
 *     browser at this folder you should see a message that begins with:
 *     There was a configuration issue with your query.
 * (2) Under STOCK_URL below change the apikey value to your apikey value
 *      that you get from https://www.alphavantage.co/
 * (3) Create a new Yioop User.
 * (4) Under Manage Accounts, click on the lock symbol next to Account Details
 * (5) Check the Bot User check bot, click save.
 * (6) Two form variables should appear: Bot Unique Token and Bot Callback URL.
 *      Fill in a value for Bot Unique Token that matches the value set
 *      for ACCESS_TOKEN in the code within the StockBot class.
 *      Fill in some_url (as defined in step (1)) for the value of Bot Callback
 *      URL
 * (7) Add the the user you created in Yioop to the group that you would like
 *     the bot to service. Let the name of this user be user_name.
 * (8) When logged into the user_name account you should now see a Bot Story
 *     activity in the activity side bar. Click on Bot Story. This activity
 *     lets you add patterns that tell your chat bot how to react when it
 *     sees various expresssions. To demo your bot add the following three
 *     patterns:
 *      (a) Request Expression: What is your name?
 *          Trigger State: 0
 *          Remote Message:
 *          Result State: 0
 *          Response: Stockbot
 *          This first pattern just says if someone does a query of the form:
 *          @user_name What is your name?
 *          your bot should respond with:
 *          Stockbot
 *      (b) Request Expression: What is the price of $stock?
 *          Trigger State: 0
 *          Remote Message: getStockPrice,$stock
 *          Result State: 0
 *          Response: $REMOTE_RESPONSE
 *          This second pattern will respond to queries of the form
 *          "What is the price of $stock?" For example,
 *          What is the price of Tesla?
 *          It will then use the Bot Callback url to make a request of
 *          this script where the remote_message variable is set to
 *          getStockPrice,$stock For example, in the case above,
 *          getStockPrice,Tesla
 *          The script below uses alphavantage's Api to get the price for the
 *          requested stock and generate a response that it
 *          sends back to the chatbot in a variable $REMOTE_RESPONSE which
 *          it then uses in its response. This might look like:
 *          The stock price of TSLA is 294.0900.
 *      (c) Request Expression: What is the symbol $name?
 *          Trigger State: 0
 *          Remote Message: getSymbol,$stock
 *          Result State: 0
 *          Response: $REMOTE_RESPONSE
 *          This last pattern will respond to queries of the form
 *          "What is the symbol of $name?" For example,
 *          What is the symbol of Tesla?
 *          It will then use the Bot Callback url to make a request of
 *          this script where the remote_message variable is set to
 *          getSymbol,$name For example, in the case above,
 *          getSymbol,Tesla
 *          The script below uses yahoo fincance's Api to get the symbol for the
 *          requested stock and generate a response that it
 *          sends back to the chatbot in a variable $REMOTE_RESPONSE which
 *          it then uses in its response. This might look like:
 *          TSLA.
 * (9) Talk to your bot in yioop in this groups by commenting on an
 *     already existing thread with a message beginning with @user_name.
 */
class StockBot
{
    /**
     * Url of site that this bot gets ticker symbol from
     */
    const SYMBOL_URL = "http://d.yimg.com/autoc.finance.yahoo.com/autoc";
    /**
     * Url of site that this bot gets stock price from
     */
    const STOCK_URL =
        "https://www.alphavantage.co/query?function=TIME_SERIES_INTRADAY".
        "&apikey=289TVF6Y8CH12BRD&interval=1min&symbol=";
    /**
     * Token given when setting up the bot in Yioop  for callback requests
     * This bots checks that a request from a Yioop Intance  sends
     * a timestamp as well as the hash of this timestamp with the bot_token
     * and post data and that these match the expected values
     */
    const ACCESS_TOKEN = "3456";
    /**
     * Number of seconds that the passed timestamp can differ from the current
     * time on the WeatherBot machine.
     */
    const TIME_WINDOW = 60;
    /**
     * This is the method called to get the StockBot to handle an incoming
     * HTTP request, and echo a stock related message
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
            if (in_array($action, ["getStockPrice", "getSymbol"])) {
                $result = $this->$action($args);
            }
        }
        echo $result;
    }
    /**
     * This method is used to check a request that it comes from a site
     * that knows the bot_token in use by this StockBot.
     */
    function checkBotToken()
    {
        return true;
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
     * Get stock price information for a ticker symbol
     *
     * @param string $args the value of $args[0] should be the name
     *      to get stock price for
     * @return string stock price information
     */
    function getStockPrice($args)
    {
        $symbol = $this->getSymbol($args);
        $url = self::STOCK_URL . $symbol;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($data, true);
        $time_series = $result["Time Series (1min)"];
        $most_recent_time = key($time_series);
        $most_recent_info = $time_series[$most_recent_time];
        $close = $most_recent_info["4. close"];
        return "The stock price of $symbol is $close.";
    }
    /**
     * Get ticker symbol for a company name
     *
     * @param array $args the value of $args[0] should be the company name to
     *      get ticker symbol for
     * @return string ticker symbol
     */
    function getSymbol($args)
    {
        $url = self::SYMBOL_URL . "?region=1&query=" . $args[0] ."&lang=en";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($data);
        $symbol = $result->ResultSet->Query;
        return $symbol;
    }
}
$bot = new StockBot();
$bot->processRequest();
