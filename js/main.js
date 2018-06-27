var styleMap = {
    mapbox: {
        basic: "light",
        streets: "light",
        bright: "light",
        light: "light",
        dark: "dark",
        satellite: "dark"
    }
}

function encodeHTML(data) {
    return $("<div />").text(data).html();
}

function getNewID() {
    return Math.random().toString(36).substr(2, 8);
}

function dismiss(id) {
    $(id).fadeOut(150);
}

function spawnBanner(type, message) {
    var id = getNewID();
    var node = $.parseHTML('<div class="banner ' + type + '" id="dyn-' + id + '"><div class="banner-inner">' + encodeHTML(message) + '</div></div>');
    $("#dynamic-banner-container").append(node);
    $("#dyn-" + id).on("click", function() {
        dismiss("#dyn-" + id);
    });
    $("#dyn-" + id).fadeIn(150);
    setTimeout(function() {
        $("#dyn-" + id).fadeOut(150);
    }, 5000);
}

$(document).ready(function() {
    var screenHeight = $(window).height();
    $('div#map').css('height', screenHeight + 'px');
    });
});

