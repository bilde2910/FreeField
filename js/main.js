var addingPoi = false;
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

function disableAddPOI(setFlag) {
    map.off('click', getCoordsForPOI);
    $("#add-poi-banner").fadeOut(150);
    addingPoi = !setFlag;
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

function openMarker(popup, id) {
    var poiObj = pois[id];
    $("#poi-name").text(poiObj.name);
    $("#poi-objective-icon").attr("src", resolveIconUrl(poiObj.objective.type));
    $("#poi-reward-icon").attr("src", resolveIconUrl(poiObj.reward.type));
    $("#poi-objective").text(resolveObjective(poiObj.objective));
    $("#poi-reward").text(resolveReward(poiObj.reward));
    $("#poi-directions").on("click", function() {
        switch (settings.get("naviProvider")) {
            case "bing":
                window.open("https://www.bing.com/maps?rtp=~pos." + encodeURI(poiObj.latitude + "_" + poiObj.longitude + "_" + poiObj.name));
                break;
            case "google":
                window.open("https://www.google.com/maps/dir/?api=1&destination=" + encodeURI(poiObj.latitude + "," + poiObj.longitude));
                break;
            case "here":
                window.open("https://share.here.com/r/mylocation/" + encodeURI(poiObj.latitude + "," + poiObj.longitude) + "?m=d&t=normal");
                break;
            case "mapquest":
                window.open("https://www.mapquest.com/directions/to/near-" + encodeURI(poiObj.latitude + "," + poiObj.longitude));
                break;
            case "waze":
                window.open("https://waze.com/ul?ll=" + encodeURI(poiObj.latitude + "," + poiObj.longitude) + "&navigate=yes");
                break;
            case "yandex":
                window.open("https://yandex.ru/maps?rtext=~" + encodeURI(poiObj.latitude + "," + poiObj.longitude));
                break;
        }
    });
    $("#poi-close").on("click", function() {
        popup._onClickClose();
    });
    var displayAddPoi = permissions["report-research"];
    if (displayAddPoi && (poiObj.objective.type != "unknown" || poiObj.reward.type != "unknown")) {
        displayAddPoi = permissions["overwrite-research"];
    }
    if (displayAddPoi) {
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
        $("#poi-add-report").show();
    } else {
        $("#poi-add-report").hide();
    }
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
                    if ($(poiObj.element).hasClass(oldReward)) $(poiObj.element).removeClass(oldReward).addClass(reward);
                    if ($(poiObj.element).hasClass(oldObjective)) $(poiObj.element).removeClass(oldObjective).addClass(objective);
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
                    popup._onClickClose();
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
    var variant = settings.get("theme");
    var url = iconSets[settings.get("iconSet")][icon].split("{%variant%}").join(variant);
    return url;
}

function addMarkers(markers) {
    markers.forEach(function(marker) {
        var e = document.createElement("div");
        e.className = "marker " + marker.reward.type + " " + styleMap[settings.get("mapProvider")][settings.get("mapStyle/"+settings.get("mapProvider"))] + " " + settings.get("iconSet");

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

function hasLocalStorageSupport() {
    try {
        localStorage.setItem("featureTest", "featureTest");
        localStorage.removeItem("featureTest");
        return true;
    } catch (ex) {
        return false;
    }
}

var localStorageSupport = hasLocalStorageSupport();

function saveSettings() {
    if (localStorageSupport) {
        localStorage.setItem("settings", JSON.stringify(settings));
        console.log("Saved!");
    }
}

$(document).ready(function() {
    var screenHeight = $(window).height();
    $('.full-container').css('height', screenHeight + 'px');

    $.getJSON("./xhr/poi.php", function(data) {
        addMarkers(data["pois"]);
    });
});

$("#add-poi-start").on("click", function() {
    if (addingPoi) return;

    addingPoi = true;
    $("#add-poi-banner").fadeIn(150);
    map.on('click', getCoordsForPOI);
});

$("#add-poi-cancel-banner").on("click", function() {
    disableAddPOI(true);
});

$("#add-poi-cancel").on("click", function() {
    disableAddPOI(true);
    $("#add-poi-details").fadeOut();
});

$("#add-poi-submit").on("click", function() {
    $("#add-poi-submit").prop("disabled", true);
    var poiName = $("#add-poi-name").val();
    var poiLat = parseFloat($("#add-poi-lat").val());
    var poiLon = parseFloat($("#add-poi-lon").val());
    $("#add-poi-working").fadeIn(150);
    $.ajax({
        url: "./xhr/poi.php",
        type: "PUT",
        dataType: "json",
        data: JSON.stringify({
            lat: poiLat,
            lon: poiLon,
            name: poiName
        }),
        statusCode: {
            201: function(data) {
                var markers = [data.poi];
                addMarkers(markers);
                addingPoi = false;
                spawnBanner("success", resolveI18N("poi.add.success", poiName));
                $("#add-poi-details").fadeOut(150);
                $("#add-poi-working").fadeOut(150);
            }
        }
    }).fail(function(xhr) {
        var data = xhr.responseJSON;
        var reason = "Unknown reason";
        console.log(data);
        if (data !== undefined && data.hasOwnProperty("reason")) {
            reason = resolveI18N(data["reason"]);
        }
        spawnBanner("failed", resolveI18N("poi.add.failed.message", poiName, reason));
        $("#add-poi-working").fadeOut(150);
        $("#add-poi-submit").prop("disabled", false);
    });
});

$("#menu-open-settings").on("click", function() {
    $(".user-setting").each(function() {
        var key = $(this).attr("data-key");
        var value = settings.get(key, true);
        $(this).val(value);
    });

    $("#map-menu").hide();
    $("#map-container").hide();
    $("#settings-container").show();
    $("#settings-menu").show();
    return false;
});

$("#menu-close-settings").on("click", function() {
    $("#settings-menu").hide();
    $("#settings-container").hide();
    $("#map-container").show();
    $("#map-menu").show();
    return false;
});

$("#user-settings-save").on("click", function() {
    $(".user-setting").each(function() {
        var key = $(this).attr("data-key");
        var value = $(this).val();

        var tree = key.split("/");
        switch (tree.length) {
            case 1:
                settings[tree[0]] = value;
                break;
            case 2:
                settings[tree[0]][tree[1]] = value;
                break;
            case 3:
                settings[tree[0]][tree[1]][tree[2]] = value;
                break;
            case 4:
                settings[tree[0]][tree[1]][tree[2]][tree[3]] = value;
                break;
            case 5:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]] = value;
                break;
            case 6:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]] = value;
                break;
            case 7:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]] = value;
                break;
            case 8:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]] = value;
                break;
            case 9:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]][tree[8]] = value;
                break;
            case 10:
                settings[tree[0]][tree[1]][tree[2]][tree[3]][tree[4]][tree[5]][tree[6]][tree[7]][tree[8]][tree[9]] = value;
                break;
        }
    });
    saveSettings();
    location.reload();
});

$("#menu-reset-settings").on("click", function() {
    if (confirm(resolveI18N("user_settings.reset.confirm"))) {
        if (localStorageSupport) {
            localStorage.removeItem("settings");
            location.reload();
        }
    }
});
