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

$(document).ready(function() {
    var screenHeight = $(window).height();
    $('div#map').css('height', screenHeight + 'px');
    });
});

