$("#hooks-add").on("click", function() {
    $("#add-hook-type").trigger("change");
    $("#hooks-add-overlay").fadeIn(150);
});

$("#add-hook-cancel").on("click", function() {
    $("#hooks-add-overlay").fadeOut(150);
});

$("#select-tg-group-cancel").on("click", function() {
    $("#hooks-tg-groups-overlay").fadeOut(150);
});

$("#add-hook-submit").on("click", function() {
    var type = $("#add-hook-type").val();
    switch (type) {
        case "json":
            var preset = $("#add-hook-json-preset").val();
            var body = "";

            if (preset != "none") {
                body = presets["json"][preset];
            }

            var id = getNewID();
            while ($(".hook-instance[data-hook-id=" + id + "]").length > 0) {
                id = getNewID();
            }

            var node = $(createHookNode("json", id));
            node.find(".hook-payload").val(body);
            node.find("select.hook-actions > option[value=enable]").remove();

            updateSummary(node);
            $("#active-hooks-list").append(node);
            node.find(".hook-target").trigger("input");

            viewTheme(id + "-icon-selector", $("#" + id + "-icon-selector").val());

            break;
        case "telegram":
            var preset = $("#add-hook-telegram-preset").val();
            var body = "";

            if (preset != "none") {
                body = presets["telegram"][preset];
            }

            var id = getNewID();
            while ($(".hook-instance[data-hook-id=" + id + "]").length > 0) {
                id = getNewID();
            }

            var node = $(createHookNode("telegram", id));
            node.find(".hook-payload").val(body);
            if (preset != "none") {
                var ext = preset.substr(preset.lastIndexOf(".") + 1);
                node.find(".hook-tg-parse-mode").val(ext);
            }
            node.find("select.hook-actions > option[value=enable]").remove();

            updateSummary(node);
            $("#active-hooks-list").append(node);
            node.find(".hook-target").trigger("input");
            node.find(".hook-tg-parse-mode").trigger("input");

            viewTheme(id + "-icon-selector", $("#" + id + "-icon-selector").val());

            break;
    }
    $("#hooks-add-overlay").fadeOut(150);
});

$("#add-hook-type").on("change", function() {
    $(".hook-add-type-conditional").hide();
    $(".hook-add-type-conditional.hook-add-type-" + $("#add-hook-type").val()).show();
});

function getNewID() {
    return Math.random().toString(36).substr(2, 8);
}

function getObjectiveFilterNode(hook) {
    var no = getNewID();
    while ($(".hook-filter[data-filter-id=" + no + "]").length > 0) {
        no = getNewID();
    }
    var node = $.parseHTML('<div class="hook-filter" data-filter-id="' + no + '"><span class="hook-objective-text"></span><input type="hidden" class="hook-objective-type" name="hook_' + hook + '[objective][' + no + '][type]" value="unknown"><input type="hidden" class="hook-objective-params" name="hook_' + hook + '[objective][' + no + '][params]" value="[]"><div class="hook-filter-actions"><i class="fas fa-edit hook-edit hook-objective-edit"></i> <i class="far fa-times-circle hook-delete hook-objective-delete"></i></div></div>');
    return node;
}

function editObjective(newObjective, caller) {
    var objective;

    if (newObjective) {
        $("#hooks-update-objective-overlay-title").text(resolveI18N("admin.clientside.hooks.popup.add_objective"));
        objective = {
            type: "unknown",
            params: []
        }
    } else {
        $("#hooks-update-objective-overlay-title").text(resolveI18N("admin.clientside.hooks.popup.edit_objective"));
        objective = {
            type: caller.parent().parent().find("input[type=hidden].hook-objective-type").val(),
            params: JSON.parse(caller.parent().parent().find("input[type=hidden].hook-objective-params").val())
        };
    }

    // Reset the report form
    $("input.parameter").val(null);
    $("select.parameter").each(function() {
        $(this)[0].selectedIndex = 0;
    });

    // Set the current research objective
    $("#update-hook-objective").val(objective.type == "unknown" ? null : objective.type);
    if (objective.type !== "unknown") {
        $("#update-hook-objective").trigger("change");
        var params = objectives[objective.type].params;
        for (var i = 0; i < params.length; i++) {
            parseObjectiveParameter(params[i], objective.params[params[i]]);
        }
    } else {
        $(".objective-parameter").hide();
    }

    $("#update-hook-objective-submit").on("click", function() {
        var objective = $("#update-hook-objective").val();
        if (objective == null) {
            alert(resolveI18N("admin.clientside.hooks.update.objective.failed.message", resolveI18N("poi.update.failed.reason.objective_null")));
            return;
        }

        var objDefinition = objectives[objective];

        var objParams = {};
        for (var i = 0; i < objDefinition.params.length; i++) {
            var paramData = getObjectiveParameter(objDefinition.params[i]);
            if (paramData == null || paramData == "") {
                alert(resolveI18N("admin.clientside.hooks.update.objective.failed.message", resolveI18N("xhr.failed.reason.missing_fields")));
                return;
            }
            objParams[objDefinition.params[i]] = paramData;
        }

        var node;

        if (newObjective) {
            node = $(getObjectiveFilterNode(caller.closest(".hook-instance").attr("data-hook-id")));
        } else {
            node = caller.parent().parent();
        }

        node.find("input[type=hidden].hook-objective-type").val(objective);
        node.find("input[type=hidden].hook-objective-params").val(JSON.stringify(objParams));
        node.find("span.hook-objective-text").text(resolveObjective({
            type: objective,
            params: objParams
        }));

        if (newObjective) {
            caller.parent().parent().append(node);
            node.parent().find(".hook-mode-objective").prop("disabled", false);
        }

        updateSummary(node);

        $("#update-hook-objective-submit").off();
        $("#hooks-update-objective-overlay").fadeOut(150);
    });

    $("#hooks-update-objective-overlay").fadeIn(150);
}

function getRewardFilterNode(hook) {
    var no = getNewID();
    while ($(".hook-filter[data-filter-id=" + no + "]").length > 0) {
        no = getNewID();
    }
    var node = $.parseHTML('<div class="hook-filter" data-filter-id="' + no + '"><span class="hook-reward-text"></span><input type="hidden" class="hook-reward-type" name="hook_' + hook + '[reward][' + no + '][type]" value="unknown"><input type="hidden" class="hook-reward-params" name="hook_' + hook + '[reward][' + no + '][params]" value="[]"><div class="hook-filter-actions"><i class="fas fa-edit hook-edit hook-reward-edit"></i> <i class="far fa-times-circle hook-delete hook-reward-delete"></i></div></div>');
    return node;
}

function editReward(newReward, caller) {
    var reward;

    if (newReward) {
        $("#hooks-update-reward-overlay-title").text(resolveI18N("admin.clientside.hooks.popup.add_reward"));
        reward = {
            type: "unknown",
            params: []
        }
    } else {
        $("#hooks-update-reward-overlay-title").text(resolveI18N("admin.clientside.hooks.popup.edit_reward"));
        reward = {
            type: caller.parent().parent().find("input[type=hidden].hook-reward-type").val(),
            params: JSON.parse(caller.parent().parent().find("input[type=hidden].hook-reward-params").val())
        };
    }

    // Reset the report form
    $("input.parameter").val(null);
    $("select.parameter").each(function() {
        $(this)[0].selectedIndex = 0;
    });

    // Set the current research reward
    $("#update-hook-reward").val(reward.type == "unknown" ? null : reward.type);
    if (reward.type !== "unknown") {
        $("#update-hook-reward").trigger("change");
        var params = rewards[reward.type].params;
        for (var i = 0; i < params.length; i++) {
            parseRewardParameter(params[i], reward.params[params[i]]);
        }
    } else {
        $(".reward-parameter").hide();
    }

    $("#update-hook-reward-submit").on("click", function() {
        var reward = $("#update-hook-reward").val();
        if (reward == null) {
            alert(resolveI18N("admin.clientside.hooks.update.reward.failed.message", resolveI18N("poi.update.failed.reason.reward_null")));
            return;
        }

        var rewDefinition = rewards[reward];

        var rewParams = {};
        for (var i = 0; i < rewDefinition.params.length; i++) {
            var paramData = getRewardParameter(rewDefinition.params[i]);
            if (paramData == null || paramData == "") {
                alert(resolveI18N("admin.clientside.hooks.update.reward.failed.message", resolveI18N("xhr.failed.reason.missing_fields")));
                return;
            }
            rewParams[rewDefinition.params[i]] = paramData;
        }

        var node;

        if (newReward) {
            node = $(getRewardFilterNode(caller.closest(".hook-instance").attr("data-hook-id")));
        } else {
            node = caller.parent().parent();
        }

        node.find("input[type=hidden].hook-reward-type").val(reward);
        node.find("input[type=hidden].hook-reward-params").val(JSON.stringify(rewParams));
        node.find("span.hook-reward-text").text(resolveReward({
            type: reward,
            params: rewParams
        }));

        if (newReward) {
            caller.parent().parent().append(node);
            node.parent().find(".hook-mode-reward").prop("disabled", false);
        }

        updateSummary(node);

        $("#update-hook-reward-submit").off();
        $("#hooks-update-reward-overlay").fadeOut(150);
    });

    $("#hooks-update-reward-overlay").fadeIn(150);
}

function encodeHTML(data) {
    return $("<div />").text(data).html();
}

function updateSummary(node) {
    var objText = null;
    node.closest(".hook-instance").find(".hook-filter-objectives .hook-filter").each(function() {
        var filterMode = $(this).parent().find(".hook-mode-objective").val();
        var objective = resolveObjective({
            type: $(this).find("input[type=hidden].hook-objective-type").val(),
            params: JSON.parse($(this).find("input[type=hidden].hook-objective-params").val())
        });
        var objHTML = '<span class="hook-head-objective-text">' + encodeHTML(objective) + '</span>';
        if (objText === null) {
            if (filterMode == "blacklist") {
                objText = resolveI18N("admin.clientside.hooks.any_objective_except", objHTML);
            } else {
                objText = objHTML;
            }
        } else {
            if (filterMode == "blacklist") {
                objText = resolveI18N("admin.clientside.hooks.multi_and", objText, objHTML);
            } else {
                objText = resolveI18N("admin.clientside.hooks.multi_or", objText, objHTML);
            }
        }
    });
    if (objText === null) {
        objText = '<span class="hook-head-objective-text">' + encodeHTML(resolveI18N("admin.clientside.hooks.any_objective")) + '</span>';
    }
    var rewText = null;
    node.closest(".hook-instance").find(".hook-filter-rewards .hook-filter").each(function() {
        var filterMode = $(this).parent().find(".hook-mode-reward").val();
        var reward = resolveReward({
            type: $(this).find("input[type=hidden].hook-reward-type").val(),
            params: JSON.parse($(this).find("input[type=hidden].hook-reward-params").val())
        });
        var rewHTML = '<span class="hook-head-reward-text">' + encodeHTML(reward) + '</span>';
        if (rewText === null) {
            if (filterMode == "blacklist") {
                rewText = resolveI18N("admin.clientside.hooks.any_reward_except", rewHTML);
            } else {
                rewText = rewHTML;
            }
        } else {
            if (filterMode == "blacklist") {
                rewText = resolveI18N("admin.clientside.hooks.multi_and", rewText, rewHTML);
            } else {
                rewText = resolveI18N("admin.clientside.hooks.multi_or", rewText, rewHTML);
            }
        }
    });
    if (rewText === null) {
        rewText = '<span class="hook-head-reward-text">' + encodeHTML(resolveI18N("admin.clientside.hooks.any_reward")) + '</span>';
    }
    var text = resolveI18N("poi.objective_text", objText, rewText);
    node.closest(".hook-instance").find(".hook-summary-text").html(text);
}

$(".hook-list").on("click", ".hook-head", function() {
    $(this).parent().find(".hook-body").toggle();
});

$(".hook-list").on("input", ".hook-target", function() {
    var type = $(this).attr("data-uri-scheme");
    var url = "?";

    switch (type) {
        case "http":
            url = $(this).val().split("/");
            if (url.length >= 3) {
                url = url[2];
            } else {
                url = "?";
            }
            break;
        case "tg":
            url = $(this).val().split("?to=");
            if (url.length >= 2) {
                url = url[1];
            } else {
                url = "?";
            }
            break;
    }

    $(this).closest(".hook-instance").find(".hook-domain").text(url);
    return true;
});

$(".hook-list").on("change", 'select[data-uri-scheme="tg"].hook-target', function() {
    if ($(this).val() == "_select") {
        $(this)[0].selectedIndex = 0;
        $(this).trigger("input");
        var token = $(this).closest(".hook-instance").find(".hook-tg-bot-token").val();
        if (token == "") {
            alert(resolveI18N("admin.clientside.hooks.tg.xhr.groups.failed.empty_token"));
            return;
        }
        $("#select-tg-group-submit").off();
        $("#hooks-tg-groups-working").fadeIn(150);
        var hook = $(this).closest(".hook-instance");
        $.getJSON("../xhr/tg-groups.php?token=" + encodeURI(token), function(data) {
            $("#select-tg-group-options").empty();
            var isEmpty = true;
            for (var id in data.groups) {
                if (data.groups.hasOwnProperty(id)) {
                    isEmpty = false;
                    $("#select-tg-group-options").append('<option value="tg://send?to=' + id + '">' + data.groups[id] + '</option>');
                }
            }
            if (isEmpty) {
                $("#hooks-tg-groups-working").fadeOut(150);
                alert(resolveI18N("admin.clientside.hooks.tg.xhr.groups.failed.no_groups"));
                return;
            }
            $("#select-tg-group-submit").on("click", function() {
                var target = $("#select-tg-group-options").val();
                hook.find(".hook-target-current-group").empty();
                hook.find(".hook-target-current-group").append('<option value="' + target + '">' + target + '</option>');
                hook.find(".hook-target").val(target);
                hook.find(".hook-target").trigger("input");
                $("#hooks-tg-groups-overlay").fadeOut(150);
            });
            $("#hooks-tg-groups-overlay").fadeIn(150);
            $("#hooks-tg-groups-working").fadeOut(150);
        }).fail(function(xhr) {
            $("#hooks-tg-groups-working").fadeOut(150);
            var data = xhr.responseJSON;
            alert(resolveI18N(data.reason));
        });
    }
});

$(".hook-list").on("change", '.hook-tg-parse-mode', function() {
    $(this).closest(".hook-instance").find(".hook-body-header").text(resolveI18N("admin.section.hooks.body." + $(this).val() + ".name"));
    var payload = $(this).closest(".hook-instance").find(".hook-payload");
    switch ($(this).val()) {
        case "html":
            payload.attr("data-validate-as", "html");
            break;
        default:
            payload.attr("data-validate-as", "text");
            break;
    }
    payload.trigger("input");
});

$(".hook-list").on("change", '.hook-icon-set', function() {
    viewTheme($(this).attr("id"), $(this).val());
});

$(".hook-list").on("click", ".hook-show-help", function() {
    var help = $(this).closest(".hook-body").find(".hook-syntax-help");
    help.toggle();
    if (help.is(":visible")) {
        $(this).text(resolveI18N("admin.clientside.hooks.syntax.hide"));
    } else {
        $(this).text(resolveI18N("admin.clientside.hooks.syntax.show"));
    }
    return false;
});

$(".hook-list").on("click", ".hook-objective-add", function() {
    editObjective(true, $(this));
    return false;
});

$(".hook-list").on("click", ".hook-objective-edit", function() {
    editObjective(false, $(this));
});

$(".hook-list").on("click", ".hook-objective-delete", function() {
    var hook = $(this).closest(".hook-instance");
    $(this).closest(".hook-filter").remove();
    if (hook.find(".hook-filter-objectives .hook-filter").length == 0) {
        hook.find(".hook-mode-objective").prop("disabled", true);
    }
    updateSummary(hook);
});

$(".hook-list").on("click", ".hook-mode-objective", function() {
    updateSummary($(this));
});

$("#update-hook-objective-cancel").on("click", function() {
    $("#update-hook-objective-submit").off();
    $("#hooks-update-objective-overlay").fadeOut(150);
});

$(".hook-list").on("click", ".hook-reward-add", function() {
    editReward(true, $(this));
    return false;
});

$(".hook-list").on("click", ".hook-reward-edit", function() {
    editReward(false, $(this));
});

$(".hook-list").on("click", ".hook-reward-delete", function() {
    var hook = $(this).closest(".hook-instance");
    $(this).closest(".hook-filter").remove();
    if (hook.find(".hook-filter-rewards .hook-filter").length == 0) {
        hook.find(".hook-mode-reward").prop("disabled", true);
    }
    updateSummary(hook);
});

$(".hook-list").on("click", ".hook-mode-reward", function() {
    updateSummary($(this));
});

$("#update-hook-reward-cancel").on("click", function() {
    $("#update-hook-reward-submit").off();
    $("#hooks-update-reward-overlay").fadeOut(150);
});

for (var i = 0; i < hooks.length; i++) {
    var hook = hooks[i];
    var node = $(createHookNode(hook.type, hook.id));

    node.find(".hook-target").val(hook.target);
    node.find(".hook-lang").val(hook.language);
    node.find(".hook-icon-set").val(hook.icons);
    node.find(".hook-payload").val(hook.body);
    node.find(".hook-mode-objective").val(hook["filter-mode"].objectives);
    node.find(".hook-mode-reward").val(hook["filter-mode"].rewards);

    if (hook.hasOwnProperty("geofence") && hook.geofence !== null) {
        var fenceStr = "";
        for (var i = 0; i < hook.geofence.length; i++) {
            fenceStr += hook.geofence[i][0] + "," + hook.geofence[i][1] + "\n";
        }
        if (fenceStr.length > 0) {
            fenceStr = fenceStr.substring(0, fenceStr.length - 1);
        }
        node.find(".hook-geofence").val(fenceStr);
    }

    if (hook.active) {
        node.find("select.hook-actions > option[value=enable]").remove();
    } else {
        node.find("select.hook-actions > option[value=disable]").remove();
    }

    if (hook.type === "telegram") {
        node.find(".hook-tg-bot-token").val(hook.options["bot-token"]);

        node.find(".hook-target-current-group").empty();
        node.find(".hook-target-current-group").append('<option value="' + hook.target + '">' + hook.target + '</option>');
        node.find(".hook-target").val(hook.target);

        node.find(".hook-tg-parse-mode").val(hook.options["parse-mode"]);
        node.find(".hook-tg-disable-web-page-preview").prop("checked", hook.options["disable-web-page-preview"]);
        node.find(".hook-tg-disable-notification").prop("checked", hook.options["disable-notification"]);
    }

    for (var j = 0; j < hook.objectives.length; j++) {
        var filter = $(getObjectiveFilterNode(hook.id));
        filter.find("input[type=hidden].hook-objective-type").val(hook.objectives[j].type);
        filter.find("input[type=hidden].hook-objective-params").val(JSON.stringify(hook.objectives[j].params));
        filter.find("span.hook-objective-text").text(resolveObjective({
            type: hook.objectives[j].type,
            params: hook.objectives[j].params
        }));
        node.find(".hook-filter-objectives").append(filter);
        filter.parent().find(".hook-mode-objective").prop("disabled", false);
    }

    for (var j = 0; j < hook.rewards.length; j++) {
        var filter = $(getRewardFilterNode(hook.id));
        filter.find("input[type=hidden].hook-reward-type").val(hook.rewards[j].type);
        filter.find("input[type=hidden].hook-reward-params").val(JSON.stringify(hook.rewards[j].params));
        filter.find("span.hook-reward-text").text(resolveReward({
            type: hook.rewards[j].type,
            params: hook.rewards[j].params
        }));
        node.find(".hook-filter-rewards").append(filter);
        filter.parent().find(".hook-mode-reward").prop("disabled", false);
    }

    updateSummary(node);
    $(hook.active ? "#active-hooks-list" : "#inactive-hooks-list").append(node);
    node.find(".hook-target").trigger("input");

    if (hook.type === "telegram") {
        node.find(".hook-tg-parse-mode").trigger("change");
    }

    viewTheme(hook.id + "-icon-selector", $("#" + hook.id + "-icon-selector").val());
}
