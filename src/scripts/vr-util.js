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
function draw360(id_360, image_360)
{
    var vr_png =
        "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAAAmCAYAAAB0xJ2ZA"+
        "AAMI2lDQ1BJQ0MgUHJvZmlsZQAASImVVwdUk8kWnr8kISGhBRCQEnoTpUiXGlroUgUb"+
        "IQkklBADQcWOLCqwFlRUwIauiii6FkAWGxZQWRTsdbGgouiiLjZQ3iQBdN1XzrvnzMx"+
        "37tx757vTzgwAypFskSgDVQEgU5gjjgr0ZUxJSGSQHgEE4EAVKIJxbE62yCcyMhRAGW"+
        "n/Lu9vQGsoV22ksf7Z/19FlcvL5gCAREKczM3mZEJ8BADciSMS5wBA6IV649k5IoiJk"+
        "CVQF0OCEJtIcaocu0hxshyHymxiopgQJwGgQGWzxakAKEl5MXI5qTCOUgnEtkKuQAhx"+
        "E8SeHD6bC/EgxOMyM7MgVraA2CL5uzipf4uZPBqTzU4dxfJcZKLgJ8gWZbDn/p/T8b8"+
        "lM0MyMoYxLFS+OChKmrN03tKzQqSYCnGrMDk8AmI1iK8JuDJ7KX7KlwTFDtt/5GQz4Z"+
        "wBTQBQKpftFwKxLsRGkvRYn2HsyRbLfKE9mpjHj4mXx0eF4qyo4fhonjAjPHQ4Tgmfx"+
        "xrBVbxs/+gRmxRBAAtiuIZogyCHFTMcszVXEBcOsRLE97LTo0OGfV/k8Znho2NJoqSc"+
        "4ZpjIDN7JBfMJEUcECW3x5z4Alb4sD40hx8TJPfFZnDYMg5aEKfxsqeEjvDh8vz85Xy"+
        "wfJ4wdpgnVirK8Y0att8pyogctseaeBmBUr0RxO3ZudEjvn05cLPJc8FBGjs4Uj4uri"+
        "7KiYyRc8MZIBQwgR9gAAksySALpAFBe299LxjpCQBsIAapgAdshjUjHvGyHiGso0Eee"+
        "AURD2SP+vnKenkgF+q/jGrltQ1IkfXmyjzSwVOIM3Ed3BN3x0Nh7Q2LPe6Cu474MZRH"+
        "RiX6E/2IQcQAouVMQb74h7gMwIEZZMAiBiGw5cGspByEI9y/xSE8JXQQHhGuE7oIt0E"+
        "ceALtBP/I8Fs0waguDHTBqAHD2SV/nx1uBlk74r64B+QPueOauA6wwSfCTHxwL5ibI9"+
        "R+m7V/x10ywppsS0bJY8jeZIsf7ZSslBxHfaS5fc9Tzit5NBPmaM+PozG/y40L25AfL"+
        "bHl2GGsBTuNXcCasHrAwE5iDVgbdlyKR/fGE9neGBktSsYnHcYRjNjY1tj22A7+MDZ7"+
        "eHyxbP1BDm9OjvTgMLNEc8WCVH4Owwfe1jwGS8gZP45hb2sHb1Hp3S+/Wvouy+50RFv"+
        "1m25JFgCTKoeGho5904XdB+DIKwAod77pzIvgcc4FoLWcIxHnynW4tCIAClCGJ0Ub6M"+
        "O7ywJmZA+cgDvwBv4gGESAGJAAZsB55oNMyHo2mA+WgEJQDFaD9aAcbAU7wB6wHxwC9"+
        "aAJnAbnwSVwBVwHd+Fe6QYvQR94DwYQBCEhNISOaCMGiClijdgjLogn4o+EIlFIApKE"+
        "pCJCRILMR5YixUgpUo5sR6qRX5FjyGnkAtKB3EYeIj3IW+QziqFUVB3VQ83QCagL6oO"+
        "GoDHodDQVnYXmoQXoSnQjWoXuQ+vQ0+gl9Drahb5E+zGAKWKamCFmg7lgTCwCS8RSMD"+
        "G2ECvCyrAqrBZrhCt9FevCerFPOBGn4wzcBu7XIDwW5+Cz8IV4CV6O78Hr8LP4Vfwh3"+
        "od/JdAIugRrghuBRZhCSCXMJhQSygi7CEcJ5+CZ6ia8JxKJmkRzojM8qwnENOI8Yglx"+
        "M/EA8RSxg/iY2E8ikbRJ1iQPUgSJTcohFZI2kfaRTpI6Sd2kjwqKCgYK9goBCokKQoV"+
        "8hTKFvQonFDoVnikMkFXIpmQ3cgSZS55LXkXeSW4kXyZ3kwcoqhRzigclhpJGWULZSK"+
        "mlnKPco7xTVFQ0UnRVnKwoUFysuFHxoGKr4kPFT1Q1qhWVSZ1GlVBXUndTT1FvU9/Ra"+
        "DQzmjctkZZDW0mrpp2hPaB9VKIrjVdiKXGVFilVKNUpdSq9ViYrmyr7KM9QzlMuUz6s"+
        "fFm5V4WsYqbCVGGrLFSpUDmmclOlX5WuaqcaoZqpWqK6V/WC6nM1kpqZmr8aV61AbYf"+
        "aGbXHdIxuTGfSOfSl9J30c/RudaK6uTpLPU29WH2/ert6n4aaxkSNOI05GhUaxzW6ND"+
        "FNM02WZobmKs1Dmjc0P4/RG+MzhjdmxZjaMZ1jPmiN1fLW4mkVaR3Quq71WZuh7a+dr"+
        "r1Gu177vg6uY6UzWWe2zhadczq9Y9XHuo/ljC0ae2jsHV1U10o3Snee7g7dNt1+PX29"+
        "QD2R3ia9M3q9+pr63vpp+uv0T+j3GNANPA0EBusMThq8YGgwfBgZjI2Ms4w+Q13DIEO"+
        "J4XbDdsMBI3OjWKN8owNG940pxi7GKcbrjJuN+0wMTMJM5pvUmNwxJZu6mPJNN5i2mH"+
        "4wMzeLN1tmVm/23FzLnGWeZ15jfs+CZuFlMcuiyuKaJdHSxTLdcrPlFSvUytGKb1Vhd"+
        "dkatXayFlhvtu4YRxjnOk44rmrcTRuqjY9Nrk2NzcPxmuNDx+ePrx//eoLJhMQJaya0"+
        "TPhq62ibYbvT9q6dml2wXb5do91beyt7jn2F/TUHmkOAwyKHBoc3E60n8iZumXjLke4"+
        "Y5rjMsdnxi5Ozk9ip1qnH2cQ5ybnS+aaLukukS4lLqyvB1dd1kWuT6yc3J7cct0Nuf7"+
        "rbuKe773V/Psl8Em/SzkmPPYw82B7bPbo8GZ5Jnts8u7wMvdheVV6PvI29ud67vJ/5W"+
        "Pqk+ezzee1r6yv2Per7genGXMA85Yf5BfoV+bX7q/nH+pf7PwgwCkgNqAnoC3QMnBd4"+
        "KogQFBK0JugmS4/FYVWz+oKdgxcEnw2hhkSHlIc8CrUKFYc2hqFhwWFrw+6Fm4YLw+s"+
        "jQAQrYm3E/UjzyFmRv00mTo6cXDH5aZRd1Pyolmh69MzovdHvY3xjVsXcjbWIlcQ2xy"+
        "nHTYurjvsQ7xdfGt81ZcKUBVMuJegkCBIaEkmJcYm7Evun+k9dP7V7muO0wmk3pptPn"+
        "zP9wgydGRkzjs9UnsmeeTiJkBSftDdpkB3BrmL3J7OSK5P7OEzOBs5Lrjd3HbeH58Er"+
        "5T1L8UgpTXme6pG6NrWH78Uv4/cKmIJywZu0oLStaR/SI9J3pw9lxGccyFTITMo8JlQ"+
        "TpgvPZulnzcnqEFmLCkVds9xmrZ/VJw4R78pGsqdnN+Sow0d2m8RC8pPkYa5nbkXux9"+
        "lxsw/PUZ0jnNM212ruirnP8gLyfpmHz+PMa55vOH/J/IcLfBZsX4gsTF7YvMh4UcGi7"+
        "sWBi/csoSxJX/J7vm1+af5fS+OXNhboFSwuePxT4E81hUqF4sKby9yXbV2OLxcsb1/h"+
        "sGLTiq9F3KKLxbbFZcWDJZySiz/b/bzx56GVKSvbVzmt2rKauFq4+sYarzV7SlVL80o"+
        "frw1bW7eOsa5o3V/rZ66/UDaxbOsGygbJhq6NoRsbNplsWr1psJxffr3Ct+JApW7lis"+
        "oPm7mbO7d4b6ndqre1eOvnbYJtt7YHbq+rMqsq20Hckbvj6c64nS2/uPxSvUtnV/GuL"+
        "7uFu7v2RO05W+1cXb1Xd++qGrRGUtOzb9q+K/v99jfU2tRuP6B5oPggOCg5+OLXpF9v"+
        "HAo51HzY5XDtEdMjlUfpR4vqkLq5dX31/PquhoSGjmPBx5ob3RuP/jb+t91Nhk0VxzW"+
        "OrzpBOVFwYuhk3sn+U6JTvadTTz9untl898yUM9fOTj7bfi7kXOv5gPNnWnxaTrZ6tD"+
        "ZdcLtw7KLLxfpLTpfq2hzbjv7u+PvRdqf2usvOlxuuuF5p7JjUcaLTq/P0Vb+r56+xr"+
        "l26Hn6940bsjVs3p93susW99fx2xu03d3LvDNxdfI9wr+i+yv2yB7oPqv6w/ONAl1PX"+
        "8Yd+D9seRT+6+5jz+OWT7CeD3QVPaU/Lnhk8q35u/7ypJ6DnyoupL7pfil4O9Ba+Un1"+
        "V+dri9ZE/vf9s65vS1/1G/Gbobck77Xe7/5r4V3N/ZP+D95nvBz4UfdT+uOeTy6eWz/"+
        "Gfnw3MHiQNbvxi+aXxa8jXe0OZQ0MitpgtewpgsKApKQC83Q0ALQEA+hX4fpgq/5vJB"+
        "JH/J2UI/Ccs/7/JxAmAWthIn+HMUwAchMXMG8ZeDEAEbGO8AergMFqGJTvFwV4eS6kG"+
        "AJLh0NBb+L4hwzIYODQ0EDk09KUSkr0GwInn8j+hVKR/0G22UtRpcBj8KP8CUQ9xO6a"+
        "BOOgAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAIEaVRYdFhNTDpjb20uYWRvYmUueG1wAA"+
        "AAAAA8eDp4bXBtZXRhIHhtbG5zOng9ImFkb2JlOm5zOm1ldGEvIiB4OnhtcHRrPSJYT"+
        "VAgQ29yZSA1LjQuMCI+CiAgIDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53"+
        "My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+CiAgICAgIDxyZGY6RGVzY3J"+
        "pcHRpb24gcmRmOmFib3V0PSIiCiAgICAgICAgICAgIHhtbG5zOmV4aWY9Imh0dHA6Ly"+
        "9ucy5hZG9iZS5jb20vZXhpZi8xLjAvIgogICAgICAgICAgICB4bWxuczp0aWZmPSJod"+
        "HRwOi8vbnMuYWRvYmUuY29tL3RpZmYvMS4wLyI+CiAgICAgICAgIDxleGlmOlBpeGVs"+
        "WURpbWVuc2lvbj41MjA8L2V4aWY6UGl4ZWxZRGltZW5zaW9uPgogICAgICAgICA8ZXh"+
        "pZjpQaXhlbFhEaW1lbnNpb24+Njc0PC9leGlmOlBpeGVsWERpbWVuc2lvbj4KICAgIC"+
        "AgICAgPHRpZmY6T3JpZW50YXRpb24+MTwvdGlmZjpPcmllbnRhdGlvbj4KICAgICAgP"+
        "C9yZGY6RGVzY3JpcHRpb24+CiAgIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+Cr4+4A4A"+
        "AAZtSURBVGgF7VjfS9VLEJ/jj1QKwZQiS1FBL6EWPXRB7UVK/R9CDUXCrtiDgqJoD0V"+
        "kQYjYheoh9dqT0JM+iaVSSJKKP8AMwkoNiSI0f/bDs3c/Y/Nls3OOeyTuFW1kz+7O7s"+
        "7sfGZmd/26lCbaxRSwi21n038DsKUI2EFJs7UIcG0Jtm25aGsAbEtTtrapXQ9AkC1ub"+
        "rfbdur/Os/lchGKLbl24jsATxtbEHxGgAhaW1uj0dFR+vjxIwUGBtJ2ezvBWNlTcnIy"+
        "HThwgPs2IFgBsLCwQLf+vkXn8s9RREQEffnyhQIC9PGB61CiTa5G9KVtxqHwZb6MiQy"+
        "pwTfb0pf5Un+XI4aHhoZSa2srff36lbKzs38NAKILERAbE0tpaWkUHBws7G1Xnzp1ig"+
        "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"+
        "AAElFTkSuQmCC";
    var vr_display = null;
    var frame_data = null;
    var mat4_identity = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];
    function mat4Zero(matrix)
    {
        for (var i = 0; i < 16; i++) {
            matrix[i] = 0;
        }
    }
    function mat4Identity(matrix)
    {
        for (var i = 0; i < 16; i++) {
            matrix[i] = mat4_identity[i];
        }
    }
    function mat4Perspective(out, fovy, aspect, near, far)
    {
        let f = 1.0 / Math.tan(fovy / 2), nf;
        mat4Zero(out);
        out[0] = f / aspect;
        out[5] = f;
        out[11] = -1;
        if (far != null && far !== Infinity) {
            nf = 1 / (near - far);
            out[10] = (far + near) * nf;
            out[14] = (2 * far * near) * nf;
        } else {
            out[10] = -1;
            out[14] = -2 * near;
        }
        return out;
    }
    function mat4FromQuat(out, q)
    {
        let x = q[0], y = q[1], z = q[2], w = q[3];
        let x2 = x + x;
        let y2 = y + y;
        let z2 = z + z;
        let xx = x * x2;
        let yx = y * x2;
        let yy = y * y2;
        let zx = z * x2;
        let zy = z * y2;
        let zz = z * z2;
        let wx = w * x2;
        let wy = w * y2;
        let wz = w * z2;
        out[0] = 1 - yy - zz;
        out[1] = yx + wz;
        out[2] = zx - wy;
        out[3] = 0;
        out[4] = yx - wz;
        out[5] = 1 - xx - zz;
        out[6] = zy + wx;
        out[7] = 0;
        out[8] = zx + wy;
        out[9] = zy - wx;
        out[10] = 1 - xx - yy;
        out[11] = 0;
        out[12] = 0;
        out[13] = 0;
        out[14] = 0;
        out[15] = 1;
        return out;
    }
    function mat4Invert(out, a)
    {
        let a00 = a[0], a01 = a[1], a02 = a[2], a03 = a[3];
        let a10 = a[4], a11 = a[5], a12 = a[6], a13 = a[7];
        let a20 = a[8], a21 = a[9], a22 = a[10], a23 = a[11];
        let a30 = a[12], a31 = a[13], a32 = a[14], a33 = a[15];
        let b00 = a00 * a11 - a01 * a10;
        let b01 = a00 * a12 - a02 * a10;
        let b02 = a00 * a13 - a03 * a10;
        let b03 = a01 * a12 - a02 * a11;
        let b04 = a01 * a13 - a03 * a11;
        let b05 = a02 * a13 - a03 * a12;
        let b06 = a20 * a31 - a21 * a30;
        let b07 = a20 * a32 - a22 * a30;
        let b08 = a20 * a33 - a23 * a30;
        let b09 = a21 * a32 - a22 * a31;
        let b10 = a21 * a33 - a23 * a31;
        let b11 = a22 * a33 - a23 * a32;
        // Calculate the determinant
        let det = b00 * b11 - b01 * b10 + b02 * b09 +
            b03 * b08 - b04 * b07 + b05 * b06;
        if (!det) {
        return null;
        }
        det = 1.0 / det;
        out[0] = (a11 * b11 - a12 * b10 + a13 * b09) * det;
        out[1] = (a02 * b10 - a01 * b11 - a03 * b09) * det;
        out[2] = (a31 * b05 - a32 * b04 + a33 * b03) * det;
        out[3] = (a22 * b04 - a21 * b05 - a23 * b03) * det;
        out[4] = (a12 * b08 - a10 * b11 - a13 * b07) * det;
        out[5] = (a00 * b11 - a02 * b08 + a03 * b07) * det;
        out[6] = (a32 * b02 - a30 * b05 - a33 * b01) * det;
        out[7] = (a20 * b05 - a22 * b02 + a23 * b01) * det;
        out[8] = (a10 * b10 - a11 * b08 + a13 * b06) * det;
        out[9] = (a01 * b08 - a00 * b10 - a03 * b06) * det;
        out[10] = (a30 * b04 - a31 * b02 + a33 * b00) * det;
        out[11] = (a21 * b02 - a20 * b04 - a23 * b00) * det;
        out[12] = (a11 * b07 - a10 * b09 - a12 * b06) * det;
        out[13] = (a00 * b09 - a01 * b07 + a02 * b06) * det;
        out[14] = (a31 * b01 - a30 * b03 - a32 * b00) * det;
        out[15] = (a20 * b03 - a21 * b01 + a22 * b00) * det;
        return out;
    }
    function addButtonElement(message, key, icon)
    {
        var button_elt = document.createElement("div");
        var webgl_canvas = document.getElementById(id_360);
        var button_container = document.getElementById("vr-button-container" +
            id_360);
        if (!button_container) {
            button_container = document.createElement("div");
            button_container.id = "vr-button-container" + id_360;
            Object.assign(button_container.style, {
                fontFamily : "sans-serif",
                position : "absolute",
                zIndex : "999",
                left : "0",
                bottom : "0",
                right : "0",
                margin : "0",
                padding : "0"
            });
            button_container.align = "right";
            webgl_canvas.parentNode.appendChild(button_container);
        }
        Object.assign(button_elt.style, {
            color: "#FFF",
            fontWeight: "bold",
            backgroundColor : "#888",
            borderRadius : "5px",
            border : "3px solid #555",
            position : "relative",
            display :  "inline-block",
            margin : "0.5em",
            padding : "0.75em",
            cursor : "pointer",
        });
        button_elt.align = "center";
        if (icon) {
            button_elt.innerHTML = "<img src='" + icon + "'/><br />" +
                message;
        } else {
            button_elt.innerHTML = message;
        }
        if (key) {
         var key_elt = document.createElement("span");
         Object.assign(key_elt.style, {fontSize : "0.75em",
            fontStyle : "italic" });
         key_elt.innerHTML = " (" + key + ")";
         button_elt.appendChild(key_elt);
        }
        button_container.appendChild(button_elt);
        return button_elt;
    }
    function addButton(message, key, icon, callback)
    {
        var key_listener = null;
        if (key) {
            var keyCode = key.charCodeAt(0);
            key_listener = function (event) {
                if (event.keyCode === keyCode) {
                    callback(event);
                }
            };
            document.addEventListener("keydown", key_listener, false);
        }
        var element = addButtonElement(message, key, icon);
        element.addEventListener("click", function (event) {
            callback(event);
            event.preventDefault();
        }, false);
        return {
            element: element,
            keyListener: key_listener
        };
    }
    function removeButton(button)
    {
        if (!button) {
            return;
        }
        if (button.element.parentElement) {
            button.element.parentElement.removeChild(button.element);
        }
        if (button.keyListener) {
            document.removeEventListener("keydown", button.keyListener, false);
        }
    }
    var project_matrix = new Float32Array(mat4_identity);
    var view_matrix = new Float32Array(mat4_identity);
    var vr_present_button = null;
    // WebGL setup.
    var gl = null;
    var panorama = null;
    function onContextLost(event)
    {
        event.preventDefault();
        console.log( 'WebGL Context Lost.' );
        gl = null;
        panorama = null;
    }
    function onContextRestored(event)
    {
        console.log( 'WebGL Context Restored.' );
        init(vr_display ? vr_display.capabilities.hasExternalDisplay : false);
    }
    var webgl_canvas = document.getElementById(id_360);
    webgl_canvas.addEventListener('webglcontextlost', onContextLost, false );
    webgl_canvas.addEventListener('webglcontextrestored', onContextRestored,
        false);
    function init(preserve_drawing_buffer)
    {
        var glAttribs = {
            alpha: false,
            antialias: false,
            preserveDrawingBuffer: preserve_drawing_buffer
        };
        gl = webgl_canvas.getContext("webgl", glAttribs);
        if (!gl) {
            gl = webgl_canvas.getContext("experimental-webgl", glAttribs);
            if (!gl) {
                return;
            }
        }
        gl.enable(gl.DEPTH_TEST);
        gl.enable(gl.CULL_FACE);
        panorama = new VRPanorama(gl);
        panorama.setImage(image_360);
        // Wait until we have a WebGL context to resize and start rendering.
        window.addEventListener("resize", onResize, false);
        onResize();
        window.requestAnimationFrame(onAnimationFrame);
    }
      // ================================
      // WebVR-specific code begins here.
      // ================================
      function onVRRequestPresent() {
        vr_display.requestPresent([{ source: webgl_canvas }]).then(function () {
        }, function (err) {
            var errMsg = "requestPresent failed.";
            if (err && err.message) {
                errMsg += "<br />" + err.message
            }
            console.log(errMsg);
        });
      }
    function onVRExitPresent()
    {
        if (!vr_display.isPresenting) {
            return;
        }
        vr_display.exitPresent().then(function () {}, function () {
            console.log("exitPresent failed.");
        });
    }
    function onVRPresentChange()
    {
        onResize();
        var tl = document.getElementById('tl');
        if (vr_display.isPresenting) {
            if (vr_display.capabilities.hasExternalDisplay) {
                removeButton(vr_present_button);
                vr_present_button = addButton(tl['exit_vr'], "E", vr_png,
                    onVRExitPresent);
            }
        } else {
            if (vr_display.capabilities.hasExternalDisplay) {
                removeButton(vr_present_button);
                vr_present_button = addButton(tl['enter_vr'], "E", vr_png,
                    onVRRequestPresent);
            }
        }
    }
    if (navigator.getVRDisplays) {
        frame_data = new VRFrameData();
        navigator.getVRDisplays().then(function (displays) {
            var tl = document.getElementById('tl');
            if (displays.length > 0) {
                vr_display = displays[displays.length - 1];
                vr_display.depthNear = 0.1;
                vr_display.depthFar = 1024.0;
                init(true);
                if (vr_display.capabilities.canPresent) {
                    vr_present_button = addButton(tl["enter_vr"], "E",
                        vr_png, onVRRequestPresent);
                }
                // For the benefit of automated testing. Safe to ignore.
                if (vr_display.capabilities.canPresent &&
                    WGLUUrl.getBool('canvasClickPresents', false)) {
                    webgl_canvas.addEventListener("click", onVRRequestPresent,
                        false);
                }
                window.addEventListener('vrdisplaypresentchange',
                    onVRPresentChange, false);
                window.addEventListener('vrdisplayactivate', onVRRequestPresent,
                    false);
                window.addEventListener('vrdisplaydeactivate', onVRExitPresent,
                    false);
            } else {
                init(false);
                console.log("No VRDisplays found.");
            }
        },
        function () {
            console.log("No WebVR support.");
        });
    } else if (navigator.getVRDevices) {
        init(false);
        console.log("WebVR version too old.");
    } else {
        init(false);
        console.log("No WebVR support.");
    }
    function onResize() {
        if (vr_display && vr_display.isPresenting) {
            var left_eye = vr_display.getEyeParameters("left");
            var right_eye = vr_display.getEyeParameters("right");
            webgl_canvas.width = Math.max(left_eye.renderWidth,
                right_eye.renderWidth) * 2;
            webgl_canvas.height = Math.max(left_eye.renderHeight,
                right_eye.renderHeight);
        } else {
            webgl_canvas.width =
                webgl_canvas.offsetWidth * window.devicePixelRatio;
            webgl_canvas.height =
                webgl_canvas.offsetHeight * window.devicePixelRatio;
        }
    }
    function getPoseMatrix(out, pose)
    {
        var orientation = pose.orientation;
        if (!orientation) {
            orientation = [0, 0, 0, 1];
        }
        mat4FromQuat(out, orientation);
        mat4Invert(out, out);
    }
    function onAnimationFrame(t)
    {
        // do not attempt to render if there is no available WebGL context
        if (!gl || !panorama) {
          return;
        }
        gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
        if (vr_display) {
            vr_display.requestAnimationFrame(onAnimationFrame);
            vr_display.getFrameData(frame_data);
            getPoseMatrix(view_matrix, frame_data.pose);
            if (vr_display.isPresenting) {
                gl.viewport(0, 0, webgl_canvas.width * 0.5,
                    webgl_canvas.height);
                panorama.render(frame_data.leftProjectionMatrix, view_matrix);
                gl.viewport(webgl_canvas.width * 0.5, 0,
                    webgl_canvas.width * 0.5, webgl_canvas.height);
                panorama.render(frame_data.rightProjectionMatrix, view_matrix);
                vr_display.submitFrame();
            } else {
                gl.viewport(0, 0, webgl_canvas.width, webgl_canvas.height);
                mat4Perspective(project_matrix, Math.PI * 0.4,
                    webgl_canvas.width / webgl_canvas.height, 0.1, 1024.0);
                panorama.render(project_matrix, view_matrix);
            }
        } else {
            window.requestAnimationFrame(onAnimationFrame);
            // No VRDisplay found.
            gl.viewport(0, 0, webgl_canvas.width, webgl_canvas.height);
            mat4Perspective(project_matrix, Math.PI * 0.4,
                webgl_canvas.width / webgl_canvas.height, 0.1, 1024.0);
            mat4Identity(view_matrix);
            panorama.render(project_matrix, view_matrix);
        }
    }
};
