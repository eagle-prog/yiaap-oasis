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
 * @author Chris Pollett
 * @license https://www.gnu.org/licenses/ GPL3
 * @link https://www.seekquarry.com/
 * @copyright 2017 - 2018
 * @filesource
 */
/**
 * Defines a class for drawing and editing spreadsheets within a tag with
 *
 * Example uses:
 * spreadsheet = new Spreadsheet(some_html_element_id,
 *     [["Tom",5],["Sally", 6]]); //read-only
 * spreadsheet.draw();
 *
 * spreadsheet2 = new Spreadsheet(some_html_element_id2,
 *     [["Tom",5],["Sally", 6]], {"mode":"write"}); //editable
 * spreadsheet2.draw();
 *
 * @param String spreadsheet_id the id of the tag in which to draw the
 *      spreadsheet
 * @param Array supplied_data two dimensional array of the rows and columns
 *      of data for the spreadsheet
 */
function Spreadsheet(spreadsheet_id, supplied_data)
{
    var self = this;
    var p = Spreadsheet.prototype;
    var properties = (typeof arguments[2] !== 'undefined') ?
        arguments[2] : {};
    var container = document.getElementById(spreadsheet_id);
    if (!container) {
        return false;
    }
    p.max_name_length = 32;
    supplied_data = eval(supplied_data);
    if (!Array.isArray(supplied_data)) {
        supplied_data = [];
    }
    var width = 0;
    for (index in supplied_data) {
        if (!Array.isArray(supplied_data[index])) {
            supplied_data[index] = [];
        }
        if ([index].length > width) {
            width = supplied_data[index].length;
        }
    }
    var length = supplied_data.length;
    var data = [];
    var eval_data = [];
    for (var i = 0; i < length; i++) {
        data[i] = [];
        for (var j = 0; j < width; j++) {
            data[i][j] = (typeof supplied_data[i][j] == 'undefined') ? "" :
                supplied_data[i][j];
        }
    }
    var property_defaults = {
        'mode' : 'read', // currently, only supports csv
        'data_id' : spreadsheet_id + "-data",
        'data_name' : 'page',
        'headings' : true, // whether headings drawn for table
        'offset' : [0, 0], /* 0 in first coordinate means top row is A, a
                1 would mean B,etc.
                offset of 0 in second coordinate, means first column 1
                offset of 1 in second coordinate, means first column 2
                */
        'table_style' : 'overflow:auto;',
        'user_name' : 'anonymous',
    };
    for (var property_key in property_defaults) {
        if (typeof properties[property_key] !== 'undefined') {
            this[property_key] = properties[property_key];
        } else {
            this[property_key] = property_defaults[property_key];
        }
    }
    /**
     * Main function used to draw the spreadsheet with the container tag
     */
    p.draw = function()
    {
        //used to draw a csv based on spreadsheet data
        var table = "<div style='" + self.table_style + "'>";
        var length = data.length;
        var width = (typeof data[0] == 'undefined') ? 0 : data[0].length;
        var add_button = "";
        var delete_button = "";
        var pre_delete_button = "";
        var x_offset = self.offset[0];
        var y_offset = self.offset[1];
        if (self.mode == 'write') {
            table += "<input id='" + self.data_id + "' type='hidden' " +
                "name='" + self.data_name + "' value='" + JSON.stringify(
                data)+ "' />";
            add_button = "<button>+</button>";
            pre_delete_button = "<button>-</button>";
        } else {
            add_button = "";
            pre_delete_button = "";
        }
        table += "<table border='1' >";
        if (self.headings || self.mode == 'write') {
            table += "<tr><th></th>";
            for (var i = 0; i < width; i++) {
                table += "<th style='min-width:1in;text-align:right;'>" +
                    delete_button + self.letterRepresentation(
                    i + y_offset) + add_button +
                    "</th>";
                delete_button = pre_delete_button;
            }
            table += "</tr>";
        }
        delete_button = "";
        eval_data = [];
        for (i = 0; i < length; i++) {
            eval_data[i] = [];
            table += "<tr>";
            if (self.headings || self.mode == 'write') {
                table += "<th style='min-width:1.1in;text-align:right;'>" +
                    delete_button + (i + x_offset + 1) + add_button + "</th>";
                delete_button = pre_delete_button;
            }
            for (var j = 0; j < width; j++) {
                eval_data[i][j] = data[i][j];
                var item = "";
                if (typeof eval_data[i][j] == 'string') {
                    item = eval_data[i][j];
                    if (item.charAt(0) == '=') {
                        item = self.evaluateCell(item.substring(1), 0)[1];
                        eval_data[i][j] = item;
                    }
                } else {
                    item = eval_data[i][j];
                }
                if (item == "" && self.mode == 'read') {
                    item = "<div style='min-height:1em;'></div>";
                }
                table += "<td style='min-width:1.1in;text-align:right;'>" +
                    item + "</td>";
            }
            table += "</tr>";
        }
        table += "</table></div>";
        container.innerHTML = table;
    }
    /**
     * Calculates the value of a cell expression in a spreadsheet. This code
     * runs on the client. In Yioop's GroupModel there is almost identical
     * PHP code that runs on the server.
     *
     * @param String cell_expression a string representing a formula to
     * calculate from a spreadsheet file
     * @param Number location character position in cell_expression to start
     *      evaluating from
     * @param Number operator_pos a postion in the array of binary operators
     *      ['*', '/', '+', '-', '%'](used to compute operators with preference)
     * @return Array [new_loc, the value of the cell or the String 'NaN' if
     *      the expression was not evaluatable]
     */
    p.evaluateCell = function(cell_expression, location, operator_pos)
    {
        var out = [location, false];
        var operators = ['%', '-','+', '/', '*'];
        if (operator_pos === undefined) {
            operator_pos = 0;
        }
        if (location >= cell_expression.length) {
            return out;
        }
        if (operator_pos >= operators.length) {
            out = self.evaluateFactor(cell_expression, location);
            return out;
        }
        var operator = operators[operator_pos];
        var left_out = self.evaluateCell(cell_expression, location,
            operator_pos + 1);
        if (left_out[0] >= cell_expression.length) {
            return left_out;
        }
        left_out[0] = self.skipWhitespace(cell_expression, left_out[0]);
        if (cell_expression.charAt(left_out[0]) != operator) {
            return left_out;
        }
        var right_out = self.evaluateCell(cell_expression, left_out[0] + 1,
            operator_pos);
        out[0] = self.skipWhitespace(cell_expression, right_out[0]);
        out[1] = eval("" + Number(left_out[1]) + operator +
            Number(right_out[1]));
        return out;
    }
    /**
     * Used to evaluate the left hand factor of a binary operator
     * appearing in a CSV spreadsheet cell
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of factor protion that this method needs to evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evaluateFactor = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        out = self.evalFunctionInvocation(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalParenthesizedExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalRangeExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalNegatedExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalNumericExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalCellNameExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        out = self.evalStringExpression(cell_expression, location);
        if (out[1] !== false) {
            return out;
        }
        return out;
    }
    /**
     * Used to evaluate a function call
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of function call that this method needs to evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalFunctionInvocation = function(cell_expression, location)
    {
        var out = [location, false];
        var rest = cell_expression.substring(location, location +
            self.max_name_length);
        var pattern =
            new RegExp("^(avg|ceil|cell|col|exp|floor|log|min|max|pow|"+
            "row|sqrt|sum|username)\\(", 'i');
        var call_parts = rest.match(pattern);
        if (call_parts === null) {
            return out;
        }
        location += call_parts[0].length;
        var arg_values = self.evaluateArgListExpression(cell_expression,
            location);
        if (arg_values[1] == "NaN") {
            return arg_values;
        }
        if ((["username"].includes(
            call_parts[1]) && arg_values[1] !== false) ||
            (["ceil", "floor", "exp", "log", "sqrt"].includes(
            call_parts[1]) && arg_values[1].length != 1) ||
            (["cell", "pow"].includes(call_parts[1]) &&
            arg_values[1].length != 2) ||(["min", "max"].includes(call_parts[1])
            && arg_values[1].length == 0) ||
            (["col", "row"].includes(call_parts[1]) &&
            arg_values[1].length != 4) ||
            cell_expression.charAt(arg_values[0]) != ')') {
            out[0] = arg_values[0];
            out[1] = "NaN";
            return out;
        }
        out[0] = arg_values[0] + 1;
        switch (call_parts[1]) {
            case "ceil":
                var arg = arg_values[1][0];
                out[1] = Math.ceil(Number(arg));
                break;
            case "cell":
                var args = arg_values[1];
                var row_col = self.cellNameAsRowColumn("" + args[1] + args[0]);
                if (row_col !== null) {
                    out[1] = eval_data[row_col[0] - 1][row_col[1]];
                } else {
                    out[1] = "NaN";
                }
                break;
            case "col":
                var args = arg_values[1];
                var tmp = self.cellNameAsRowColumn("" + args[2] + "0");
                var start = Math.max(tmp[1] - 1, 0);
                tmp = self.cellNameAsRowColumn("" + args[3] + "0");
                var end = Math.min(tmp[1] - 1, eval_data[0].length - 1);
                out[1] = -1;
                for (var j = start; j <= end; j++) {
                    if (eval_data[Math.max(args[1] - 1,0)][j] == args[0]) {
                        out[1] = self.letterRepresentation(j);
                        break;
                    }
                }
                break;
            case "exp":
                var arg = arg_values[1][0];
                out[1] = Math.exp(Number(arg));
                break;
            case "floor":
                var arg = arg_values[1][0];
                out[1] = Math.floor(Number(arg));
            case "log":
                var arg = arg_values[1][0];
                out[1] = Math.log(Number(arg));
                break;
            case "min":
                var minnands = arg_values[1];
                var min = minnands[0];
                for (var i = 0; i < minnands.length; i++) {
                    if (minnands[i] < min) {
                        min = minnands[i];
                    }
                }
                out[1] = min;
                break;
            case "max":
                var maxands = arg_values[1];
                var max = maxands[0];
                for (var i = 0; i < maxands.length; i++) {
                    if (maxands[i] > max) {
                        max = maxands[i];
                    }
                }
                out[1] = max;
                break;
            case "pow":
                out[1] = Math.pow(Number(arg_values[1][0]),
                    Number(arg_values[1][1]));
                break;
            case "row":
                var args = arg_values[1];
                var tmp = self.cellNameAsRowColumn("" + args[1] + "0");
                var col = tmp[1];
                var start = Math.max(args[2] - 1, 0);
                var end = Math.min(args[3] - 1, eval_data.length - 1);
                out[1] = -1;
                for (var i = start; i <= end; i++) {
                    if (eval_data[i][col] == args[0]) {
                        out[1] = i + 1;
                        break;
                    }
                }
                break;
            case "sqrt":
                var arg = arg_values[1][0];
                out[1] = Math.sqrt(Number(arg));
                break;
            case "sum":
            case "avg":
                var sum = 0;
                var summands = arg_values[1];
                for (var i = 0; i < summands.length; i++) {
                    var summand = Number(summands[i]);
                    sum += summand;
                }
                out[1] = (call_parts[1] == 'sum') ? sum :
                     sum/summands.length;
                break;
            case "username":
                out[1] = self.user_name;
                break;
        }
        return out;
    }
    /**
     * Used to evaluate a spreadsheet expression surrounded by
     * parentheses appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of parentheses expression that this method needs to
     *      evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalParenthesizedExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        if (cell_expression.charAt(location) == "(") {
            out = self.evaluateCell(cell_expression, location + 1);
            if (cell_expression.charAt(out[0]) != ')') {
                out[1] = "NaN";
                return out;
            }
            out[0] = self.skipWhitespace(cell_expression, out[0] + 1);
            return out;
        }
        return out;
    }
    /**
     * Used to evaluate the expressions in a list of arguments to
     * a function call appearing in a CSV spreadsheet
     * cell formula
     *
     * @param string $cell_expression cell formula to evaluate
     * @param Number $location start offset in cell expression
     *      of argument list that this method needs to
     *      evaluate
     * @return Array [new_loc, array of arg-list]
     */
    p.evaluateArgListExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        var more_args = true;
        out = [location, []];
        while (more_args) {
            more_args = false;
            var sub_out = self.evaluateCell(cell_expression, location);
            if (sub_out[1] == 'NaN' || sub_out[1] === false) {
                return sub_out;
            }
            if (typeof sub_out[1] == 'object') {
                for(var i = 0 ; i < sub_out[1].length; i++) {
                    out[1].push(sub_out[1][i]);
                }
            } else {
                out[1].push(sub_out[1]);
            }
            location = self.skipWhitespace(cell_expression, sub_out[0]);
            out[0] = location;
            if (location < cell_expression.length &&
                cell_expression.charAt(location) == ',') {
                more_args = true;
                location++;
            }
        }
        return out;
    }
    /**
     * Used to convert range expressions, cell_name1:cell_name2 into a
     * sequence of cells, cell_name1, ..., cell_name2 so that it may be
     * used as part of an argument list to a function call
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String $cell_expression cell formula to evaluate
     * @param Number $location start offset in cell expression
     *      of range expression that this method needs to
     *      evaluate
     * @return Array [new_loc, array of range cells]
     */
    p.evalRangeExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        var rest = cell_expression.substring(location, location +
            self.max_name_length);
        value = rest.match(/^([A-Z]+)(\d+)\s*\:\s*([A-Z]+)(\d+)/);
        var col_flag = false;
        if (value !== null && ((col_flag = (value[1] == value[3])) ||value[2] ==
            value[4])) {
            out[0] = self.skipWhitespace(cell_expression, location +
                value[0].length);
            out[1] = [];
            if (col_flag) {
                for (var i = Number(value[2]); i <= Number(value[4]); i++) {
                    var row_col = self.cellNameAsRowColumn("" + value[1] + i);
                    if (typeof eval_data[row_col[0] - 1] == 'undefined' ||
                        typeof eval_data[row_col[0] - 1][row_col[1]] ==
                        'undefined' ||
                        eval_data[row_col[0] - 1][row_col[1]] == 'NaN') {
                        out[1] = "NaN";
                        return out;
                    }
                    out[1].push(eval_data[row_col[0] - 1][row_col[1]]);
                }
            } else {
                var row_col1 = self.cellNameAsRowColumn("" + value[1]+value[2]);
                var row_col2 = self.cellNameAsRowColumn("" + value[3]+value[4]);
                var i = row_col1[0];
                for (var j = row_col1[1]; j <= row_col2[1]; j++) {
                    if (typeof eval_data[i - 1] == 'undefined' ||
                        typeof eval_data[i - 1][j] == 'undefined'
                        || eval_data[i - 1][j] == 'NaN'){
                        out[1] = "NaN";
                        return out;
                    }
                    out[1].push(eval_data[i - 1][j]);
                }
            }
        }
        return out;
    }
    /**
     * Used to parse expression of the form: -expr
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of negated expression that this method needs to
     *      evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalNegatedExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        if (cell_expression.charAt(location) == "-") {
            sub_out = self.evaluateCell(cell_expression, location + 1);
            if (sub_out[1] == 'NaN' || sub_out[1] == false) {
                return sub_out;
            }
            out[0] = self.skipWhitespace(cell_expression, sub_out[0]);
            out[1] = - sub_out[1];
            return out;
        }
        return out;
    }
    /**
     * Used to parse a integer or float expression
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of integer or float expression that this method needs to
     *      evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalNumericExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        var rest = cell_expression.substring(location, location +
            self.max_name_length);
        var value = rest.match(/^\-?\d+(\.\d*)?|^\-?\.\d+/);
        if (value !== null) {
            out[0] = self.skipWhitespace(cell_expression,location +
                value[0].length);
            out[1] = (value[0].match(/\./) == '.') ? parseFloat(value[0]) :
                parseInt(value[0]);
            return out;
        }
        return out;
    }
    /**
     * Used to parse an expression of the form letter sequence followed by
     * number sequence corresponding to the name of a spreadsheet cell
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of cell name expression that this method needs to
     *      evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalCellNameExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        var rest = cell_expression.substring(location, location +
            self.max_name_length);
        value = rest.match(/^[A-Z]+\d+/);
        if (value !== null) {
            out[0] = self.skipWhitespace(cell_expression,location +
                value[0].length);
            var row_col = self.cellNameAsRowColumn(value.toString().trim());
            out[1] = eval_data[row_col[0] - 1][row_col[1]];
        }
        return out;
    }
    /**
     * Used to parse a string expression, "some string" or 'some string',
     * appearing in a CSV spreadsheet cell formula
     *
     * @param String cell_expression cell formula to evaluate
     * @param Number location start offset in cell expression
     *      of string expression that this method needs to
     *      evaluate
     * @return Array [new_loc, the value of sub-expression]
     */
    p.evalStringExpression = function(cell_expression, location)
    {
        var out = [location, false];
        if (location >= cell_expression.length) {
            return out;
        }
        location = self.skipWhitespace(cell_expression, location);
        var rest = cell_expression.substring(location, location +
            self.max_name_length);
        var value = rest.match(/^\"([^\"]*)\"/);
        if (value === null) {
            value = rest.match(/^\'([^\']*)\'/);
        }
        if (value !== null) {
            out[0] = self.skipWhitespace(cell_expression,location +
                value[0].length);
            out[1] = value[1];
            return out;
        }
        return out;
    }
    /**
     * Returns the position of the first non-whitespace character after
     * location in the string (returns location if location is non-WS or
     * if no location found).
     *
     * @param String haystack string to search in
     * @param Number location where to start search from
     * @return Number position of non-WS character
     */
    p.skipWhitespace = function(haystack, location)
    {
        var next_loc = haystack.substring(location).search(/\S/);
        if (next_loc > 0) {
            location += next_loc;
        }
        return location;
    }
    /**
     * Converts a decimal number to a base 26 number string using A-Z for 0-25.
     * Used where drawing column headers for spreadsheet
     * @param Number number the value to convert to base 26
     * @return String result of conversion
     */
    p.letterRepresentation = function(number)
    {
        var pre_letter;
        var out = "";
        do {
            pre_letter = number % 26;
            number = Math.floor(number/26);
            out += String.fromCharCode(65 + pre_letter);
        } while (number > 25);
        return out;
    }
    /**
     * Given a cell name string, such as B4, converts it to an ordered pair
     * suitable for lookup in the spreadsheets data array. On B4,
     * [3, 1] would be returned.
     *
     * @param String cell_name name to convert
     * @return Array ordered pair corresponding to name
     */
    p.cellNameAsRowColumn = function(cell_name)
    {
        var cell_parts = cell_name.match(/^([A-Z]+)(\d+)$/);
        if (cell_parts == null) {
            return null;
        }
        var column_string = cell_parts[1];
        var len = column_string.length;
        var column = 0;
        var old_column = 0;
        var shift = 26;
        for (var i = 0; i < len; i++) {
            column += old_column + (column_string.charCodeAt(i) - 65);
            old_column = shift * column;
        }
        return [parseInt(cell_parts[2]), column];
    }
    /**
     * Callback for click events on spreadsheet. Determines if the event
     * occurred on a spreadsheet cell. If so, it opens a prompt for a
     * new value for the cell and updates the cell and the associated form
     * hidden input value.
     * @param Object event click event object
     */
    p.updateCell = function (event) {
        var type = (event.target.innerHTML == "+") ? 'add' :
            (event.target.innerHTML == "-") ? 'delete' :'cell';
        var target = (type == 'cell') ? event.target :
            event.target.parentElement;
        var row = target.parentElement.rowIndex - 1;
        var column = target.cellIndex - 1;
        var length = data.length;
        var width = data[0].length;
        if (row >= 0 && column >= 0) {
            self.makeUpdatableCell(event.target, row, column,
                data[row][column]);
        } else if (type == 'add' && row == -1 && column >= 0) {
            for (var i = 0; i < length; i++) {
                for (var j = width; j > column + 1; j--) {
                    data[i][j] = data[i][j-1];
                }
                data[i][column + 1] = "";
            }
            data_elt = document.getElementById(self.data_id);
            data_elt.value = JSON.stringify(data);
            self.draw();
        } else if (type == 'add' && row >= 0 && column == -1) {
            data[length] = [];
            for (var i = length; i > row + 1; i--) {
                for (var j = 0; j < width; j++) {
                    data[i][j] = data[i - 1][j];
                }
            }
            for (var j = 0; j < width; j++) {
                data[row + 1][j] = "";
            }
            data_elt = document.getElementById(self.data_id);
            data_elt.value = JSON.stringify(data);
            self.draw();
        } else if (type == 'delete' && row == -1 && column >= 0) {
            for (var i = 0; i < length; i++) {
                for (var j = column ; j < width - 1; j++) {
                    data[i][j] = data[i][j + 1];
                }
                data[i].pop();
            }
            data_elt = document.getElementById(self.data_id);
            data_elt.value = JSON.stringify(data);
            self.draw();
        } else if (type == 'delete' && row >= 0 && column == -1) {
            for (var i = row; i < length - 1; i++) {
                    data[i] = data[i + 1];
            }
            data.pop();
            data_elt = document.getElementById(self.data_id);
            data_elt.value = JSON.stringify(data);
            self.draw();
        }
        event.stopPropagation();
        event.preventDefault();
    }
    /**
     * Replaces table cell in spreadsheet with an input tag which can be
     * edited, sets up events for handling when cell update is over.
     *
     * @param Object cell the td tag object for the cell
     * @param Number row the row in the spreadsheet the cell corresponds to
     * @param Number col the column in the spreadsheet the cell corresponds to
     * @param String cell_value the non HTMl contents of the cell
     */
    p.makeUpdatableCell = function (cell, row, column, cell_value) {
        cell.innerHTML = "<input id='update-cell' value=" +
            JSON.stringify(cell_value.replace(/\"/g, '&quot;')) +
            " data-row='" + row + "' data-column='" + column + "' " +
            "style='background:lightgray; width:1in; text-align:inherit'/>";
        document.getElementById('update-cell').focus();
        elt('update-cell').addEventListener("blur", self.finishCellUpdate,
            false);
    }
    /**
     * Called after a spreadsheet cell that was being edited becomes blurred.
     * It is used to update the value of the cell in the data array, and to
     * redraw and re-evaluate the spreadsheet.
     *
     * @param event the Javascript blur event that signaled cell editing was
     *      was complete
     */
    p.finishCellUpdate = function (event) {
        var cell = document.getElementById('update-cell');
        if (cell == null) {
            return;
        }
        cell.removeEventListener("blur", self.finishCellUpdate,
            false);
        var row = cell.getAttribute('data-row');
        var column = cell.getAttribute('data-column');
        var cell_value = cell.value;
        if (cell_value != null) {
            data[row][column] = cell_value;
            data_elt = document.getElementById(self.data_id);
            data_elt.value = JSON.stringify(data);
            self.draw();
            data[row][column] = cell_value.replace(/\'/g, '&#39;')
            data_elt.value = JSON.stringify(data);
        }
    }
    if (this.mode == 'write') {
        container.addEventListener("click", self.updateCell, true);
    }
}
