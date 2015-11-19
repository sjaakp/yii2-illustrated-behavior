/*global File, FileList, FileReader, Image, Math, jQuery, console */
/*jslint nomen: true, unparam: true, white: true */
/**
 * MIT licence
 * Version 1.0.1
 * Sjaak Priester, Amsterdam 13-06-2014 ... 12-11-2015.
 *
 * @link https://github.com/sjaakp/cropper
 * @link http://www.sjaakpriester.nl/software/cropper
 */
(function ($) {
    "use strict";
    $.widget("sjaakp.cropper", {

        options: {
            aspectRatio: 1,
            margin: 40,
            diagonal: 300,
            minSize: 240,
            sliderPosition: "bottom",
            sliderOptions: {}
        },

        scale: 1,
        zoom: 1,
        margin: 40,
        crop: true,
        loaded: false,

        loadImage: function (file) {
            var that = this,
                thisFile,
                reader;

            // restore defaults
            this.scale = this.zoom =  1;
            this.margin = Number(this.options.margin);    // explicit cast to number to avoid problems with + operator
            this.crop = true;
            this.loaded = false;

            // restore ui
            this._setMargin();
            this.overlay.show();
            this.preview.removeClass("sjaakp-state-loaded sjaakp-state-nocrop");
            this.img.removeAttr("style");   // remove leftovers previous image
            this.slider.slider("option", {
                value: 1,
                max: 1
            });

            // empty img
            this.img.removeAttr("src");

            this._reportNull();

            if (!file) {
                return; // empty
            }

            if (file instanceof FileList) { thisFile = file[0]; }
            else if (file instanceof File) { thisFile = file; }
            else {
                this._loadSrc(file);
                return;
            }

            if (thisFile.type.match(/image.*/))  {  // Lint generates message, ignore
                reader = new FileReader();
                reader.onload = function (evt) {
                    that._loadSrc(evt.target.result);
                };
                reader.readAsDataURL(thisFile);
            }
        },

        _create: function () {
            this.img = $('<img>').addClass("sjaakp-img");
            this.overlay = $("<div>").addClass("sjaakp-overlay");

            this.preview = $("<div>").addClass("sjaakp-preview")
                .append(this.img, this.overlay);

            this.slider = $("<div>").addClass("sjaakp-slider")
                .slider($.extend({}, this.options.sliderOptions, {  // don't let user override our options
                    min: 1,
                    max: 1
                }));
            this._calcPreviewSize();
            this.margin = this.options.margin;
            this._setMargin();

            this.element.addClass("sjaakp-cropper").append(this.preview);
            this._positionSlider();
            this._reportNull();

            this._on(this.slider, {
                slide: function (evt, ui) {
                    this._changeZoom(ui.value);
                },
                dblclick: function(evt) {
                    this.slider.slider("option", "value", 1);
                    this._changeZoom(1);
                }.bind(this)
            });

            var dragging = false;
            this._on(this.overlay, {
                mousedown: function (evt) {
                    if (evt.which === 1) {
                        var d = {
                            left: evt.pageX,
                            top: evt.pageY
                            },
                            startPos = this.imgPos;     // this = widget
                        dragging = true;

                        this._on(this.document, {
                            mousemove: function (evt) {
                                if (dragging) {
                                    this._moveImg({
                                        left: startPos.left + evt.pageX - d.left,
                                        top: startPos.top + evt.pageY - d.top
                                    });
                                    this._report();
                                    evt.preventDefault();
                                }
                            }.bind(this),
                            mouseup: function (evt) {
                                if (dragging)   {
                                    this._off(this.document, "mousemove mouseup");  // unbind events
                                    dragging = false;
                                    evt.preventDefault();
                                }
                            }.bind(this)
                        });

                        evt.preventDefault();
                    }
                }.bind(this)
            });
        },

        _setOption: function (key, value)    {
            if (key === "sliderPosition")  {
                this.element.removeClass("sjaakp-spos-" + this.options.sliderPosition);     // remove old class
            }
            this._super(key, value);
            switch(key) {
                case "aspectRatio":
                    this._calcPreviewSize();
                    this._calcScale();
                    this._centerImg();
                    this._report();     // report new aspect ratio
                    break;

                case "margin":
                    this.margin = Number(value);    // set current margin to option value; explicit cast to number
                    this._calcPreviewSize();
                    this._calcScale();    // scale doesn't change; current margin might!
                    this._setMargin();
                    this._moveImg(this.imgPos);     // ensure img is contained
                    break;

                case "diagonal":
                    this._calcPreviewSize();
                    this._calcScale();
                    break;

                case "sliderPosition":
                    this._positionSlider();
                    break;

                case "minSize":
                    this._calcScale();
                    break;

                default:
                    break;
            }
        },

        _loadSrc: function (src)  {
            var that = this,
                preload = new Image();

            preload.src = src;

            preload.onload = function () {
                that.nativeSize = {
                    width: this.width,
                    height: this.height
                };

                that.img.attr("src", this.src);
                that.loaded = true;
                that.preview.addClass("sjaakp-state-loaded");
                that._calcScale();
                if (that.crop) {
                    that._centerImg();     // center image
                }
                that._report();
            };
        },

        // Calculate and set preview size based on aspect ratio, margin and diagonal.
        _calcPreviewSize: function ()    {
            var asp = this.options.aspectRatio,
                d = Math.sqrt(1 + asp * asp),       // Pythagoras
                f = this.options.diagonal / d,
                margins = 2 * this.options.margin,
                w = f * asp + margins,              // compensate for crop margins
                h = f + margins;

            this.previewSize = {
                width: w,
                height: h
            };
            this.preview.css(this.previewSize);     // set preview size
            this._sizeSlider();
        },

        // Calculate base scale for the image: the smallest scale where the image fits the crop area.
        _calcScale: function ()  {
            if (this.loaded)    {
                var cropWidth = this.previewSize.width - 2 * this.margin,
                    cropHeight = this.previewSize.height - 2 * this.margin,
                    scaleHor = cropWidth / this.nativeSize.width,
                    scaleVert = cropHeight / this.nativeSize.height,
                    scale =  Math.max(scaleHor, scaleVert),
                    scaleMax,
                    maxZoom;

                scaleMax = Math.max(cropWidth, cropHeight) / this.options.minSize;

                if (scaleMax < scale)  {
                    this.overlay.hide();
                    this.preview.addClass("sjaakp-state-nocrop");
                    this.crop = false;
                    this._reportNull();
                }
                else    {
                    maxZoom = scaleMax / scale;
                    if (maxZoom < this.zoom)    {
                        this._changeZoom(maxZoom);
                    }
                    this.slider.slider("option", {
                        max: maxZoom,
                        step: maxZoom / 100
                    });
                }

                if (scale >= 1)  {
                    scale = 1;
                }

                this.scale = scale;
                this._setImgSize();
            }
            else { this.scale = 1; }
        },

        // Set img size
        _setImgSize: function ()    {
            var f = this.scale * this.zoom;
            this.imgSize = {
                width: f * this.nativeSize.width,
                height: f * this.nativeSize.height
            };
            if (this.crop) {
                this.img.css(this.imgSize);
            }
        },

        _positionSlider: function() {
            var pos = this.options.sliderPosition;
            this.slider.slider("option", "orientation", pos === "top" || pos === "bottom" ? "horizontal" : "vertical");
            this.element.addClass("sjaakp-spos-" + this.options.sliderPosition);
            if (pos === "top" || pos === "left")    {
                this.element.prepend(this.slider);
            }
            else    {
                this.element.append(this.slider);
            }
            this._sizeSlider();
        },

        _sizeSlider: function() {
            var pos = this.options.sliderPosition;
            if (pos === "top" || pos === "bottom")  {
                this.slider.width(this.preview.width()).height("");
            }
            else    {
                this.slider.height(this.preview.height()).width("")
                    .find("a").css( {left: "" });   // hack to solve position problem in slider handle
            }
        },

        // Set margin. Implemented as border of overlay.
        _setMargin: function ()  {
            this.overlay.css({ borderWidth: this.margin });
        },

        _centerImg: function()    {
            this._moveImg({left: 0, top: 0});
        },

        _moveImg: function(pos)    {   // pos is center point, with respect to preview's center point
            var offset = {
                    left: (this.imgSize.width - this.previewSize.width) / 2,
                    top: (this.imgSize.height - this.previewSize.height) / 2
                },
                contain = {
                    halfWidth: this.margin + offset.left,
                    halfHeight: this.margin + offset.top
                };

            pos.left = Math.max(Math.min(pos.left, contain.halfWidth), -contain.halfWidth);
            pos.top = Math.max(Math.min(pos.top, contain.halfHeight), -contain.halfHeight);

            this.imgPos = pos;

            this.img.css({
                left: pos.left - offset.left,
                top: pos.top - offset.top
            });
        },

        // Change zoom. Center point of crop area remains the same.
        _changeZoom: function (zoom) {
            var pos = this.imgPos,
                f = zoom / this.zoom;   // new zoom / old zoom

            pos.left *= f;      // scale
            pos.top *= f;
            this.zoom = zoom;   // set new value
            this._setImgSize();
            this._moveImg(pos);
            this._report();
        },

        _report: function () {
            if (this.loaded && this.crop)  {
                var posLeft = this.imgPos.left,
                    posTop = this.imgPos.top,
                    m = this.margin,
                    f = 1 / (this.scale * this.zoom);

                posLeft = m + (this.imgSize.width - this.previewSize.width) / 2 - posLeft;
                posTop = m + (this.imgSize.height - this.previewSize.height) / 2 - posTop;

                this._trigger("change", null, {     // trigger change event
                    aspect: this.options.aspectRatio,
                    x: f * posLeft,          // coordinates in native pixels
                    y: f * posTop,
                    w: f * (this.previewSize.width - 2 * m),
                    h: f * (this.previewSize.height - 2 * m)
                });
            }
        },

        _reportNull: function () {
            this._trigger("change", null, {     // trigger change event
                aspect: this.options.aspectRatio,
                x: 0,          // all coordinates zero, indicating no crop
                y: 0,
                w: 0,
                h: 0
            });
        }
    });
} (jQuery));
