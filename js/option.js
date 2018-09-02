/*
    This script file contains functions to facilitate the functionality of
    options which require scripts.
*/

/*
    Handle item selection changes for `IconPackOption` downdowns. This function
    updates the icon pack preview box.
*/
function viewTheme(selectorID, theme) {
    var box = document.getElementById("iconviewer-" + selectorID);
    box.innerHTML = "";

    if (theme === "") return;

    var variants = ["light", "dark"];
    var varbox = {};

    for (var i = 0; i < variants.length; i++) {
        varbox[variants[i]] = document.createElement("div");
        varbox[variants[i]].style.width = "calc(100% - 20px)";
        varbox[variants[i]].style.padding = "10px";
    }

    varbox["light"].style.backgroundColor = "#ccc";
    varbox["dark"].style.backgroundColor = "#333";

    var tdata = isc_opts.themedata[theme];

    for (var i = 0; i < isc_opts.icons.length; i++) {
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
                var icobox = document.createElement("img");
                icobox.src = uri.split("{%variant%}").join(variants[j]);
                icobox.style.width = "68px";
                icobox.style.height = "68px";
                icobox.style.margin = "5px";
                varbox[variants[j]].appendChild(icobox);
            }
        }
    }

    if (tdata.hasOwnProperty("logo")) {
        var logo = document.createElement("img");
        logo.src = isc_opts.baseuri + "themes/icons/" + theme + "/" + tdata["logo"].split("{%variant%}").join(isc_opts.colortheme);
        logo.style.width = "400px";
        logo.style.maxWidth = "100%";
        logo.marginTop = "20px";
        box.appendChild(logo);
    }

    var name = document.createElement("h2");
    name.innerText = tdata.name;
    name.style.color = "#" + (isc_opts.colortheme == "dark" ? "ccc" : "333");
    name.style.marginBottom = "0";
    box.appendChild(name);

    var author = document.createElement("p");
    author.innerText = "Authored by " + tdata.author;
    box.appendChild(author);

    for (var i = 0; i < variants.length; i++) {
        box.appendChild(varbox[variants[i]]);
    }
}
