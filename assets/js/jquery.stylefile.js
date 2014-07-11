/*global FileReader, Image, jQuery */
/*jslint plusplus: true, white: true */
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 15-06-2014.
 */

(function($) {
    "use strict";
    $.fn.stylefile = function(options)  {

        var settings = $.extend({}, $.fn.stylefile.defaults, options),

        prettySize = function(nBytes) {
            var units = ["B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"],
                m = 0;
            while (nBytes > 1024) {
                nBytes /= 1024;
                m++;
            }
            return (m ? nBytes.toFixed(settings.decimals) : nBytes) + " " + units[m];
        },

        handleFiles = function(files, info) {

            function templateHelper(file)   {
                return settings.infoTemplate.replace("{nbytes}", file.size)
                    .replace("{nice_nbytes}", prettySize(file.size))
                    .replace("{filename}", file.name);
            }

            var file = files[0],
                newImg,
                reader;

            if (file.type.match(/image.*/))  {
                newImg = new Image();

                newImg.onload = function() {
                    info.html(templateHelper(file).replace("{imgsize}", newImg.width + "&times;" + newImg.height));
                    settings.onImage.call(this);
                };

                reader = new FileReader();
                reader.onload = function(e) {
                    newImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
            else    {
                info.html(templateHelper(file).replace("{imgsize}", ""));
            }
        };

        return this.each(function() {
            var info = $("<p>", {
                    class: settings.infoClass
                }).html(settings.noFileText),

                finp = $(this).hide().on("change", function(evt) {
                    handleFiles(evt.target.files, info);
                }),

                btn = $("<a>", {
                    class: settings.btnClass,
                    href: "#"
                })
                    .text(settings.btnText)
                    .click(function(e) {
                        finp.click();
                        e.preventDefault();
                    });

            finp.after(btn);
            btn.after(info);

            return this;

        });
    };

    $.fn.stylefile.defaults = {
        decimals: 3,
        btnClass: "btn btn-default",
        btnText: "Browse",
        infoClass: "text-muted",
        noFileText: '<span class="nofile">No file selected</span>',
        infoTemplate: '{filename}&emsp;<span class="imgsize">{imgsize}</span><br /><span class="filesize">{nice_nbytes} ({nbytes} bytes)</span>',
        onImage: function() {}
    };
} (jQuery));