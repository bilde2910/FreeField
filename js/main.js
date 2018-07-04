var pois = [];

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

function getCoordsForPOI(e) {
    disableAddPOI(false);
    $("#add-poi-lon").val(e.lngLat.lng);
    $("#add-poi-lat").val(e.lngLat.lat);
    $("#add-poi-submit").prop("disabled", false);
    $("#add-poi-details").fadeIn(150);
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

function resolveObjective(objective) {
    var objdef = {
        "categories": null,
        "params": []
    };
    if (objectives.hasOwnProperty(objective.type)) {
        objdef = objectives[objective.type];
    }

    var i18nstring = resolveI18N("objective." + objective.type);
    if (objective.params.hasOwnProperty("quantity")) {
        if (objective.params.quantity == 1) {
            i18nstring = resolveI18N("objective." + objective.type + ".singular");
        } else {
            i18nstring = resolveI18N("objective." + objective.type + ".plural");
        }
    }
    if (objective.params.constructor !== Array) {
        for (var i = 0; i < objdef.params.length; i++) {
            var param = objdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(parameterToString(param, objective.params[param]));
        }
    }
    return i18nstring;
}

function resolveReward(reward) {
    var rewdef = {
        "categories": null,
        "params": []
    };
    if (rewards.hasOwnProperty(reward.type)) {
        rewdef = rewards[reward.type];
    }

    var i18nstring = resolveI18N("reward." + reward.type);
    if (reward.params.hasOwnProperty("quantity")) {
        if (reward.params.quantity == 1) {
            i18nstring = resolveI18N("reward." + reward.type + ".singular");
        } else {
            i18nstring = resolveI18N("reward." + reward.type + ".plural");
        }
    }
    if (reward.params.constructor !== Array) {
        for (var i = 0; i < rewdef.params.length; i++) {
            var param = rewdef.params[i];
            i18nstring = i18nstring.split("{%" + (i + 1) + "}").join(parameterToString(param, reward.params[param]));
        }
    }
    return i18nstring;
}

function openMarker(popup, id) {
    var poiObj = pois[id];
    $("#poi-name").text(poiObj.name);
    $("#poi-objective-icon").attr("src", resolveIconUrl(poiObj.objective.type));
    $("#poi-reward-icon").attr("src", resolveIconUrl(poiObj.reward.type));
    $("#poi-objective").text(resolveObjective(poiObj.objective));
    $("#poi-reward").text(resolveReward(poiObj.reward));
    $("#poi-directions").on("click", function() {
        window.open("https://www.google.com/maps/dir/?api=1&destination=" + encodeURI(poiObj.lat + "," + poiObj.lon));
    });
    $("#poi-close").on("click", function() {
        popup._onClickClose();
    });
    $("#poi-add-report").on("click", function() {
        // Reset the report form
        $("input.parameter").val(null);
        $("select.parameter").each(function() {
            $(this)[0].selectedIndex = 0;
        });

        // Set the current research objective
        $("#update-poi-objective").val(poiObj.objective.type == "unknown" ? null : poiObj.objective.type);
        if (poiObj.objective.type !== "unknown") {
            $("#update-poi-objective").trigger("change");
            var params = objectives[poiObj.objective.type].params;
            for (var i = 0; i < params.length; i++) {
                parseObjectiveParameter(params[i], poiObj.objective.params[params[i]]);
            }
        } else {
            $(".objective-parameter").hide();
        }
        $("#update-poi-reward").val(poiObj.reward.type == "unknown" ? null : poiObj.reward.type);
        if (poiObj.reward.type !== "unknown") {
            $("#update-poi-reward").trigger("change");
            var params = rewards[poiObj.reward.type].params;
            for (var i = 0; i < params.length; i++) {
                parseRewardParameter(params[i], poiObj.reward.params[params[i]]);
            }
        } else {
            $(".reward-parameter").hide();
        }

        $("#poi-details").hide();
        $("#update-poi-details").show();
    });
    $("#update-poi-submit").on("click", function() {
        var objective = $("#update-poi-objective").val();
        if (objective == null) {
            spawnBanner("failed", resolveI18N("poi.update.failed.message", resolveI18N("poi.update.failed.reason.objective_null")));
            return;
        }
        var reward = $("#update-poi-reward").val();
        if (reward == null) {
            spawnBanner("failed", resolveI18N("poi.update.failed.message", resolveI18N("poi.update.failed.reason.reward_null")));
            return;
        }

        var objDefinition = objectives[objective];
        var rewDefinition = rewards[reward];

        var objParams = {};
        for (var i = 0; i < objDefinition.params.length; i++) {
            var paramData = getObjectiveParameter(objDefinition.params[i]);
            if (paramData == null || paramData == "") {
                spawnBanner("failed", resolveI18N("poi.update.failed.message", resolveI18N("xhr.failed.reason.missing_fields")));
                return;
            }
            objParams[objDefinition.params[i]] = paramData;
        }

        var rewParams = {};
        for (var i = 0; i < rewDefinition.params.length; i++) {
            var paramData = getRewardParameter(rewDefinition.params[i]);
            if (paramData == null || paramData == "") {
                spawnBanner("failed", resolveI18N("poi.update.failed.message", resolveI18N("xhr.failed.reason.missing_fields")));
                return;
            }
            rewParams[rewDefinition.params[i]] = paramData;
        }

        $("#update-poi-submit").prop("disabled", true);
        $("#update-poi-working").fadeIn(150);
        $.ajax({
            url: "./xhr/poi.php",
            type: "PATCH",
            dataType: "json",
            data: JSON.stringify({
                id: poiObj.id,
                objective: {
                    type: objective,
                    params: objParams
                },
                reward: {
                    type: reward,
                    params: rewParams
                }
            }),
            statusCode: {
                204: function(data) {
                    var oldObjective = poiObj.objective.type;
                    var oldReward = poiObj.reward.type;
                    if ($(poiObj.element).hasClass(oldObjective)) $(poiObj.element).removeClass(oldObjective).addClass(objective);
                    if ($(poiObj.element).hasClass(oldReward)) $(poiObj.element).removeClass(oldReward).addClass(reward);
                    poiObj.objective = {
                        type: objective,
                        params: objParams
                    };
                    poiObj.reward = {
                        type: reward,
                        params: rewParams
                    };
                    console.log(poiObj.element);
                    spawnBanner("success", resolveI18N("poi.update.success", poiObj.name));
                    $("#update-poi-details").fadeOut(150);
                    $("#update-poi-working").fadeOut(150);
                }
            }
        }).fail(function(xhr) {
            var data = xhr.responseJSON;
            var reason = "Unknown reason";
            console.log(data);
            if (data !== undefined && data.hasOwnProperty("reason")) {
                reason = resolveI18N(data["reason"]);
            }
            spawnBanner("failed", resolveI18N("poi.update.failed.message", reason));
            $("#update-poi-working").fadeOut(150);
            $("#update-poi-submit").prop("disabled", false);
        });
    });

    $("#update-poi-name").val(poiObj.name);
    $("#poi-details").fadeIn(150);
}

function closeMarker(popup) {
    $("#poi-directions").off();
    $("#poi-close").off();
    $("#poi-add-report").off();
    $("#update-poi-submit").off();
    $("#poi-details").fadeOut(150);
    setTimeout(function() {
        $("#poi-objective-icon").attr("src", "about:blank");
        $("#poi-reward-icon").attr("src", "about:blank");
    }, 150);
}

$("#update-poi-cancel").on("click", function() {
    $("#update-poi-details").hide();
    $("#poi-details").show();
});

function resolveIconUrl(icon) {
    var variant = settings.theme;
    var url = iconSets[settings.iconSet][icon].split("{%variant%}").join(variant);
    return url;
}

function addMarkers(markers) {
    markers.forEach(function(marker) {
        var e = document.createElement("div");
        e.className = "marker " + marker.reward.type + " " + styleMap[settings.mapProvider][settings.mapStyle] + " " + settings.iconSet;

        marker["element"] = e;
        pois[marker.id] = marker;

        var popup = new mapboxgl.Popup({
            offset: 25
        });

        popup.on("open", function() {
            openMarker(popup, marker.id);
        });
        popup.on("close", function() {
            closeMarker(popup);
        });

        new mapboxgl.Marker(e)
            .setLngLat([marker.longitude, marker.latitude])
            .setPopup(popup)
            .addTo(map);
    });
}

$(document).ready(function() {
    var screenHeight = $(window).height();
    $('div#map').css('height', screenHeight + 'px');

    $.getJSON("./xhr/poi.php", function(data) {
        addMarkers(data["pois"]);
    });
});

