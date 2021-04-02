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
 * @copyright 2016-2020
 * @filesource
 */
/**
 * Defines a class useful for making point and line graph charts.
 *
 * @param String chart_id id of tag that the chart will be drawn into
 * @param Object data a sequence {x_1:y_1, ... x_1,y_n} points to plot
 *    x_i's can be arbitrary labels, y_i's are assumes to be floats
 * @param Object (optional) properties override values for any of the
 *      properties listed in the property_defaults variable below
 */
function Chart(chart_id, data)
{
    var self = this;
    var p = Chart.prototype;
    var properties = (typeof arguments[2] !== 'undefined') ?
        arguments[2] : {};
    var container = document.getElementById(chart_id);
    if (!container) {
        return false;
    }
    var property_defaults = {
        'axes_color' : [128,128,128], // color of the x and y axes lines
        'caption' : '', // caption text appears at bottom
        'caption_style' : 'font-size: 14pt; text-align: center;',
            // CSS styles to apply to caption text
        'data_color' : [0,0,255], //color used to draw graph
        'height' : 500, //height of area to draw into in pixels
        'line_width' : 1, // width of line in line graph
        'x_padding' : 30, //x-distance left side of canvas tag to y-axis
        'y_padding' : 30, //y-distance bottom of canvas tag to x-axis
        'point_radius' : 3, //radius of points that are plot in point graph
        'tick_length' : 10, // length of tick marks along axes
        'ticks_y' : 5, // number of tick marks to use for the y axis
        'tick_font_size' : 10, //size of font to use when labeling ticks
        'title' : '', // title text appears at top
        'title_style' : 'font-size:24pt; text-align: center;',
            // CSS styles to apply to title text
        'type' : 'LineGraph', // currently, can be either a LineGraph,
            //PointGraph or BarGraph
        'width' : 500 //width of area to draw into in pixels
    };
    for (var property_key in property_defaults) {
        if (typeof properties[property_key] !== 'undefined') {
            this[property_key] = properties[property_key];
        } else {
            this[property_key] = property_defaults[property_key];
        }
    }
    title_tag = (this.title) ? '<div style="' + this.title_style
         + 'width:' + this.width + '" >' + this.title + '</div>' : '';
    caption_tag = (this.caption) ? '<figcaption style="' + this.caption_style
         + 'width:' + this.width + '" >' + this.caption + '</figcaption>' : '';
    container.innerHTML = '<figure>'+ title_tag + '<canvas id="' + chart_id +
        '-content" ></canvas>' + caption_tag + '</figure>';
    canvas = document.getElementById(chart_id + '-content');
    if (!canvas || typeof canvas.getContext === 'undefined') {
        return
    }
    var context = canvas.getContext("2d");
    canvas.width = this.width;
    canvas.height = this.height;
    this.data = data;
    /**
     * Main function used to draw the graph type selected
     */
    p.draw = function()
    {
        self['draw' + self.type]();
    }
    /**
     * Used to store in fields the min and max y values as well as the start
     * and end x keys, and the range = max_y - min_y
     */
    p.initMinMaxRange = function()
    {
        self.min_value = null;
        self.max_value = null;
        var key;
        var val;
        var num_graphs = 1;
        var graph_data = {};
        if (!data.num_graphs) {
            graph_data[0] = data;
        } else {
            graph_data = data.graphs;
            num_graphs = data.num_graphs;
        }
        for (var i = 0; i < num_graphs; i++) {
            for (key in graph_data[i]) {
                val = parseFloat(graph_data[i][key]);
                if (self.min_value === null) {
                    self.min_value = val;
                    self.max_value = val;
                }
                if (val < self.min_value) {
                    self.min_value = val;
                }
                if (val > self.max_value) {
                    self.max_value = val;
                }
            }
        }
        self.range = self.max_value - self.min_value;
        self.range = (self.range == 0) ? 1 : self.range;
        self.max_value += self.range * 0.1;
        self.min_value -= self.range * 0.1;
        self.range = self.max_value - self.min_value;
    }
    /**
     * Used to draw a point at location x,y in the canvas
     */
    p.plotPoint = function(x,y)
    {
        var c = context;
        c.beginPath();
        c.arc(x, y, self.point_radius, 0, 2 * Math.PI, true);
        c.fill();
    }
    /**
     * Used to draw a bar from the x-axis, to the point x,y
     */
    p.plotBar = function(x,y)
    {
        var height = self.height - self.y_padding - self.tick_length;
        var y_bottom = self.tick_length + height *
            ((1 + self.min_value)/self.range);
        var c = context;
        c.beginPath();
        c.rect(x - self.point_radius/2, height + self.tick_length,
            self.point_radius, y - self.tick_length - height +
            self.point_radius);
        c.fill();
    }
    /**
     * Draws the x and y axes for the chart as well as ticks marks and values
     */
    p.renderAxes = function()
    {
        var c = context;
        var height = self.height - self.y_padding;
        c.strokeStyle = "rgb(" + self.axes_color[0] + "," +
            self.axes_color[1] + "," + self.axes_color[2] + ")";
        c.lineWidth = self.line_width;
        c.beginPath();
        c.moveTo(self.x_padding - self.tick_length,
            self.height - self.y_padding);
        c.lineTo(self.width - self.x_padding,  height);  // x axis
        c.stroke();
        c.beginPath();
        c.moveTo(self.x_padding, self.tick_length);
        c.lineTo(self.x_padding, self.height - self.y_padding +
            self.tick_length);  // y axis
        c.stroke();
        var spacing_y = self.range/self.ticks_y;
        height -= self.tick_length;
        var min_y = parseFloat(self.min_value);
        var max_y = parseFloat(self.max_value);
        var num_format = new Intl.NumberFormat("en-US",
            {"maximumFractionDigits":2});
        // Draw y ticks and values

        for (var val = min_y; val < max_y + spacing_y; val += spacing_y) {
            y = self.tick_length + height *
                (1 - (val - self.min_value)/self.range);
            c.font = self.tick_font_size + "px serif";
            c.fillText(num_format.format(val), 0, y + self.tick_font_size/2,
                self.x_padding - self.tick_length);
            c.beginPath();
            c.moveTo(self.x_padding - self.tick_length, y);
            c.lineTo(self.x_padding, y);
            c.stroke();
        }
        var num_graphs = 1;
        var graph_data = {};
        if (!data.num_graphs) {
            graph_data[0] = data;
        } else {
            graph_data = data.graphs;
            num_graphs = data.num_graphs;
        }
        var x_values;
        if (!data.x_values) {
            x_values = graph_data[0];
        } else {
            x_values = data.x_values;
        }
        // Draw x ticks and values
        var dx = (self.width - 2 * self.x_padding) /
            (Object.keys(x_values).length - 1);
        var x = self.x_padding;
        for (x_key in x_values) {
            c.font = self.tick_font_size + "px serif";
            var x_value = x_values[x_key];
            c.fillText(x_value, x -
                self.tick_font_size/2 * (x_value.length - 0.5),
                self.height - self.y_padding +  self.tick_length +
                self.tick_font_size, self.tick_font_size *
                (x_value.length - 0.5));
            c.beginPath();
            c.moveTo(x, self.height - self.y_padding + self.tick_length);
            c.lineTo(x, self.height - self.y_padding);
            c.stroke();
            x += dx;
        }
    }
    /**
     * Draws a chart consisting of just x-y plots of points in data.
     */
    p.drawPointGraph = function()
    {
        self.initMinMaxRange();
        self.renderAxes();
        num_graphs = 1;
        var graph_data = {};
        if (!data.num_graphs) {
            graph_data[0] = data;
        } else {
            graph_data = data.graphs;
            num_graphs = data.num_graphs;
        }
        var x_values;
        for (var i = 0; i < num_graphs; i++) {
            if (!data.x_values) {
                x_values = graph_data[i];
            } else {
                x_values = data.x_values;
            }
            var dx = (self.width - 2 * self.x_padding) /
                ( Object.keys(x_values).length - 1);
            var c = context;
            c.lineWidth = self.line_width;
            var dim = 1 - (0.7 * (i % 3));
            c.strokeStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            c.fillStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            var height = self.height - self.y_padding - self.tick_length;
            var x = self.x_padding;
            for (var x_key in x_values) {
                if (graph_data[i][x_key]) {
                    y = self.tick_length + height *
                        (1 - (graph_data[i][x_key] -
                        self.min_value)/self.range);
                    self.plotPoint(x, y);
                }
                x += dx;
            }
        }
    }
    /**
     * Draws a chart consisting of x-y plots of points in data, each adjacent
     * point pairs connected by a line segment
     */
    p.drawLineGraph = function()
    {
        self.drawPointGraph();
        num_graphs = 1;
        var graph_data = {};
        if (!data.num_graphs) {
            graph_data[0] = data;
        } else {
            graph_data = data.graphs;
            num_graphs = data.num_graphs;
        }
        var x_values;
        var dash_state = 0;
        for (var i = 0; i < num_graphs; i++) {
            if (!data.x_values) {
                x_values = graph_data[i];
            } else {
                x_values = data.x_values;
            }
            var c = context;
            c.beginPath();
            if (dash_state == 0) {
                c.setLineDash([]);
            } else if (dash_state == 1) {
                c.setLineDash([5, 5]);
            } else {
                c.setLineDash([2, 2]);
            }
            var dim = 1 - (0.7 * (i % 3));
            c.strokeStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            c.fillStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            dash_state = (dash_state + 1) % 3;
            var x = self.x_padding;
            var dx =  (self.width - 2 * self.x_padding) /
                (Object.keys(x_values).length - 1);
            var height = self.height - self.y_padding  - self.tick_length;
            var first_time = true;
            for (var x_key in x_values) {
                if (graph_data[i][x_key]) {
                    y = self.tick_length + height *
                        (1 - (graph_data[i][x_key] - self.min_value)
                        / self.range);
                    if (first_time) {
                        first_time = false;
                        c.moveTo(x, y);
                    } else {
                        c.lineTo(x, y);
                    }
                }
                x += dx;
            }
            c.stroke();
        }
    }
    /**
     * Draws a chart consisting of x-y plots of points in data, where each
     * data point is a bar from the x-axis to the point in question
     */
    p.drawBarGraph = function()
    {
        self.initMinMaxRange();
        self.renderAxes();
        num_graphs = 1;
        graph_data = {};
        if (!data.num_graphs) {
            graph_data[0] = data;
        } else {
            graph_data = data.graphs;
            num_graphs = data.num_graphs;
        }
        var x_values;
        for (var i = 0; i < num_graphs; i++) {
            if (!data.x_values) {
                x_values = graph_data[i];
            } else {
                x_values = data.x_values;
            }
            var dx = (self.width - 2 * self.x_padding) /
                (Object.keys(x_values).length - 1);
            var c = context;
            c.lineWidth = self.line_width;
            var dim = 0.7 * (i % 3);
            c.strokeStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            c.fillStyle = "rgb(" + dim * self.data_color[0] + "," +
                dim * self.data_color[1] + "," + dim * self.data_color[2] + ")";
            var height = self.height - self.y_padding - self.tick_length;
            var x = self.x_padding + i * (dx/num_graphs)
            for (x_key in x_values) {
                if (graph_data[i][x_key]) {
                    y = self.tick_length + height *
                        (1 - (graph_data[i][x_key] - self.min_value)
                        / self.range);
                        self.plotBar(x, y);
                }
                x += dx;
            }
        }
    }
}
