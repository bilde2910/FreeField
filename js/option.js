/*
    This script file contains functions to facilitate the functionality of
    options which require scripts.
*/

/*
    Handle item selection changes for `IconSetOption` downdowns. This function
    updates the icon set preview box.
*/
$(document).ready(function() {
    $("select.icon-set-option-input").on("input", function() {
        var theme = $(this).val();

        /*
            Get the box used to preview icon sets.
        */
        var parent = $(this).closest(".option-block-follows")
                            .next(".option-following-block");
        parent.html("");

        /*
            Create a child element in which to put all elements of the icon
            selector preview. This box has the `opt-icon-selector` class to
            handle styling of added elements through /css/main.css.
        */
        var box = $("<div />");
        box.addClass("opt-icon-selector");
        parent.append(box);

        /*
            Do not display a preview if no theme is selected.
        */
        if (theme === "") return;

        /*
            Declare variants of the icon set (dark and light icons). Each
            variant has a preview box that displays the icons in that particular
            theme. The <div> elements holding these icons are stored in
            `varbox`.
        */
        var variants = ["light", "dark"];
        var varbox = {};

        /*
            Create two icon containers; one for each variant (light and dark) of
            the theme. Even if the icon set only has explicit support for one
            theme, this shows how the icons would look on both themes.
        */
        for (var i = 0; i < variants.length; i++) {
            varbox[variants[i]] = $("<div />");
            varbox[variants[i]].addClass("icon-box " + variants[i]);
        }

        /*
            Get the icon set metadata for the given theme.
        */
        var tdata = isc_opts.themedata[theme];

        for (var i = 0; i < isc_opts.icons.length; i++) {
            /*
                For each available icon in FreeField, check if the icon set has
                declared assets for the icon, either in vector or raster format,
                and display the icon if so. If there are no assets for the given
                icon, don't add it to the preview.
            */
            var uri = isc_opts.baseuri + "themes/icons/" + theme + "/";
            if (tdata.hasOwnProperty("vector") && tdata["vector"].hasOwnProperty(isc_opts.icons[i])) {
                uri += tdata["vector"][isc_opts.icons[i]];
            } else if (tdata.hasOwnProperty("raster") && tdata["raster"].hasOwnProperty(isc_opts.icons[i])) {
                uri += tdata["raster"][isc_opts.icons[i]];
            } else {
                uri = null;
            }

            if (uri != null) {
                for (var j = 0; j < variants.length; j++) {
                    /*
                        Create <img> nodes referencing the marker and add one to
                        each variant's icon container (a dark variant in the
                        dark icon container and a light variant in the light
                        icon container).
                    */
                    var icobox = $("<img />");
                    icobox.attr("src", uri.split("{%variant%}").join(variants[j]));
                    varbox[variants[j]].append(icobox);
                }
            }
        }

        /*
            If the icon set has a logo, display the logo at the top of the icon
            set preview.
        */
        if (tdata.hasOwnProperty("logo")) {
            var logo = $("<img />");
            logo.attr("src",
                isc_opts.baseuri
                + "themes/icons/"
                + theme + "/"
                + tdata["logo"]
                    .split("{%variant%}")
                    .join(isc_opts.colortheme)
            );
            logo.addClass("logo");
            box.append(logo);
        }

        /*
            Display the name of the icon set.
        */
        var name = $("<h2 />");
        name.text(tdata.name);
        name.addClass("name " + isc_opts.colortheme);
        box.append(name);

        /*
            Display the author of the icon set.
        */
        var author = $("<p />");
        author.text(resolveI18N("admin.option.icon_set.authored_by", tdata.author));
        box.append(author);

        /*
            Append the icon preview boxes underneath the name and author text.
        */
        for (var i = 0; i < variants.length; i++) {
            box.append(varbox[variants[i]]);
        }
    });
    $("select.icon-set-option-input").trigger("input");
});

/*
    Handle value changes for `ColorOption`. This function changes the listed RGB
    values next to the color input.
*/
$(document).ready(function() {
    $(".color-option-input").on("input", function() {
        var value = $(this).val();
        if (value.match(/^#[0-9A-Fa-f]{6}$/)) {
            var r = parseInt(value.substring(1, 3), 16);
            var g = parseInt(value.substring(3, 5), 16);
            var b = parseInt(value.substring(5, 7), 16);
            $(this).next().text("r="+r+", g="+g+", b="+b+"");
        }
    });
});

/*
    Handle text changes for `ParagraphOption` downdowns. This function updates
    the preview box for Markdown text.
*/
if (showdown) {
    var showdown = new showdown.Converter();
    $(document).ready(function() {
        $("textarea[data-has-preview-for='md']").on("input", function() {
            /*
                Get the value of the input box with all user HTML tags escaped.
            */
            var value = $(this).val().split("<").join("&lt;").split(">").join("&gt;");
            var previewBox = $(this).closest(".option-block-follows")
                                    .next(".option-following-block")
                                    .find(".para-content");
            var html = showdown.makeHtml(value);

            /*
                Search for XSS by creating an element consisting of the parsed
                Markdown.
            */
            var xssFound = false;
            var xssTestDiv = document.createElement("div");
            xssTestDiv.innerHTML = html;

            /*
                Search for anchors linking to JavaScript.
            */
            var anchors = xssTestDiv.getElementsByTagName("a");
            for (var i = 0; i < anchors.length; i++) {
                var target = anchors[i].getAttribute("href");
                if (target.toLowerCase().startsWith("javascript:")) xssFound = true;
            }

            /*
                If JavaScript is found, warn the user and disable previews. Server-
                side, Parsedown is used to render Markdown, which is more robust
                when it comes to XSS prevention than Showdown, the client-side
                script.
            */
            if (xssFound) {
                previewBox.css("background", "darkorange");
                previewBox.html('<span class="para-xss-warning"><i class="fas fa-code"></i> '
                                + resolveI18N("admin.option.paragraph.xss_warning")
                                + '</span>');
            } else {
                previewBox.css("background", "none");
                previewBox.html(html);
            }
        });
        $("textarea[data-has-preview-for='md']").trigger("input");
    });
}
