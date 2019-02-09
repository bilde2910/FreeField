/*
    This script file is invoked from the administration pages and is responsible
    for client-side rendering and functionality of webhooks.
*/

/*
    Button for adding a new webhook.
    Displays: on page body
*/
$("#hooks-add").on("click", function() {
    /*
        Trigger a change event for the selection box where users specify the
        kind of webhook to add. This is because each type of webhook has a
        specific set of options that allow customization for the instance of
        webhook that is added.
        E.g. JSON webhooks have an option for initializing the webhook with a
        preset that pre-fills the JSON body field with the selected preset's
        body. This field should only be displayed when the JSON webhook type is
        selected in the dialog box. This hide/show toggle functionality is bound
        to the change event of the webhook type selection box, so that whenever
        the type of webhook changes, the correct options for that type is
        immediately displayed. Manually triggering the change event here ensures
        that the correct options appear for the type of webhook that is already
        selected when the "add new webhook" dialog opens.
    */
    $("#add-hook-type").trigger("change");

    $("#hooks-add-overlay").fadeIn(150);
});

/*
    Button that cancels adding a new webhook.
    Displays: on the "add new webhook" dialog box
*/
$("#add-hook-cancel").on("click", function() {
    $("#hooks-add-overlay").fadeOut(150);
});

/*
    Button that cancels selecting a Telegram group.
    Displays: on the "select Telegram group" dialog box
*/
$("#select-tg-group-cancel").on("click", function() {
    $("#hooks-tg-groups-overlay").fadeOut(150);
});

/*
    Button that adds a new webhook to the list of active webhooks client-side.
    Displays: on the "add new webhook" dialog box
*/
$("#add-hook-submit").on("click", function() {
    /*
        Different webhook types have different options that can be set by the
        user. Hence, we need to find the type of webhook the user selected to
        add, and then add the webhook, taking into account the relevant options.
    */
    var type = $("#add-hook-type").val();
    switch (type) {
        case "json":
            /*
                JSON hooks have a "preset" option that allows users to auto-fill
                the JSON body field of the webhook with a pre-written JSON data
                array. The preset bodies are stored in the `presets.json` array.
                Here, we check if the user selected such a preset, and if so,
                we fetch the preset and put it into the JSON body field.
            */
            var preset = $("#add-hook-json-preset").val();
            var body = "";
            if (preset != "none") {
                body = presets["json"][preset];
            }

            /*
                Each webhook has a unique ID. Generate such an ID for the new
                webhook. Please see the `getNewID()` function to see how the IDs
                are generated.
            */
            var id = getNewID();

            /*
                Ensure that the ID is unique, to avoid conflicts where this
                webhook would overwrite another one.
            */
            while ($(".hook-instance[data-hook-id=" + id + "]").length > 0) {
                id = getNewID();
            }

            /*
                Create a new HTML webhook node instance and start populating it
                with data. Since the webhook is enabled by default, we'll remove
                the action for enabling the webhook from the list of webhook
                actions. There is no purpose enabling a webhook which is already
                active.
            */
            var node = $(createHookNode("json", id));
            node.find(".hook-payload").val(body);
            node.find("select.hook-actions > option[value=enable]").remove();

            /*
                Update the summary displayed in the header bar of the webhook,
                to ensure that it displays the correct summary for the newly-
                generated webhook. The summary is the string that lists the
                objective and rewards filters to quickly identify when a
                specific webhook is triggered.
            */
            updateSummary(node);

            /*
                In order to display the correct target domain (for new webhooks,
                this is simply '?') in the header bar, we trigger the input
                event for the webhook target URL box. This event is responsible
                for putting the correct target domain in the webhook's header
                bar, or a question mark if no valid domain is found. The domain
                listed updates every time the user types in the webhook URL box.

                This event can only be fired after the webhook node has been
                added to the DOM, so we'll add it to the DOM first and then
                trigger the event.
            */
            $("#active-hooks-list").append(node);
            node.find(".hook-target").trigger("input");

            break;
        case "telegram":
            /*
                Like JSON, Telegram hooks also have a "preset" option for
                auto-filling the webhook body field. Telegram webhook presets
                can be either in plain-text, Markdown or HTML format, and
                presets must indicate which of these the hook should use by the
                file extension of the hook (txt, md and html respectively).
            */
            var preset = $("#add-hook-telegram-preset").val();
            var body = "";
            if (preset != "none") {
                body = presets["telegram"][preset];
            }

            /*
                Each webhook has a unique ID. Generate such an ID for the new
                webhook. Please see the `getNewID()` function to see how the IDs
                are generated.
            */
            var id = getNewID();

            /*
                Ensure that the ID is unique, to avoid conflicts where this
                webhook would overwrite another one.
            */
            while ($(".hook-instance[data-hook-id=" + id + "]").length > 0) {
                id = getNewID();
            }

            /*
                Create a new HTML webhook node instance and start populating it
                with data. Since the webhook is enabled by default, we'll remove
                the action for enabling the webhook from the list of webhook
                actions. There is no purpose enabling a webhook which is already
                active.
            */
            var node = $(createHookNode("telegram", id));
            node.find(".hook-payload").val(body);
            node.find("select.hook-actions > option[value=enable]").remove();
            if (preset != "none") {
                /*
                    For Telegram webhooks specifically, we must also take care
                    that the correct body format is selected based on the chosen
                    preset's format. This is determined by the file extension of
                    the preset. E.g. if the chosen preset is "telegram.md" then
                    the format is Markdown. The parse mode selection box uses
                    file extensions for values, so we can just grab the file
                    extension and put it directly into the selection box as a
                    value, and the correct format will be selected.
                */
                var ext = preset.substr(preset.lastIndexOf(".") + 1);
                node.find(".hook-tg-parse-mode").val(ext);
            }

            /*
                Update the summary displayed in the header bar of the webhook,
                to ensure that it displays the correct summary for the newly-
                generated webhook. The summary is the string that lists the
                objective and rewards filters to quickly identify when a
                specific webhook is triggered.
            */
            updateSummary(node);

            /*
                Similarly to JSON webhooks, we also have to update the target
                "domain" in the header bar. For Telegram webhooks, this is the
                ID of the group that messages should be sent to. And, like JSON,
                we do this by triggering the "input" event.

                We also have to trigger the "input" event of the parse mode
                selection box, so that the header above the webhook body
                correctly updates to reflect the type of body the hook should
                use. E.g. if the parse mode selection box is set to Markdown
                ("md"), the header above the body text box should read "Markdown
                body" rather than "Text body".

                Those events can only be fired after the webhook node has been
                added to the DOM, so we'll add it to the DOM first and then
                trigger the events.
            */
            $("#active-hooks-list").append(node);
            node.find(".hook-target").trigger("input");
            node.find(".hook-tg-parse-mode").trigger("input");

            break;
    }
    /*
        The webhook has now been properly initialized and added to the list of
        active webhooks, so we can close this dialog box.
    */
    $("#hooks-add-overlay").fadeOut(150);
});

/*
    Selection box that lets users choose the type of webhook to add.
    Displays: on the "add new webhook" dialog box
*/
$("#add-hook-type").on("change", function() {
    /*
        Different webhook types have different options that can be set by the
        user. This event will ensure that only the relevant options for the
        selected webhook type are displayed. All of these options have the class
        `hook-add-type-conditional` for this purpose, so we'll hide all of those
        and then display only the ones that are specific to the selected webhook
        type using the `hook-add-type-<type>` class selector.
    */
    $(".hook-add-type-conditional").hide();
    $(".hook-add-type-conditional.hook-add-type-" + $("#add-hook-type").val()).show();
});

/*
    Generates a new ID used to uniquely identify each webhook, as well as
    uniquely identifying each particular objective/reward filter within any
    particular webhook.
*/
function getNewID() {
    /*
        This function serves two main purposes - it should generate an ID for:
        - Webhook instances
        - Objective and reward filters

        These use cases are different in nature, but both can be solved using
        the same ID generation algorithm.
    */
    /*
        WEBHOOK INSTANCE IDs

        Each webhook registered in each installation of FreeField has an ID that
        is unique within that particular installation. The ID is used both
        client-side and server-side. Each input field in the webhook has the ID
        listed in the field name so that the server knows which webhook changes
        should be applied to when changes are saved and pushed form the client.

        If a client adds a new webhook, an ID then has to be assigned to that
        webhook so that the server has a reference to the hook for later use.
        The way this is solved, is that an ID is generated from this script,
        then passed to the server along with all the other, existing hooks. The
        server checks if each hook exists, and if it does not, it creates a hook
        with the given ID and saves it with the data it receives.

        This creates a concurrency problem. It may happen that two users are
        separately setting up webhooks at the same time, and a conflict may
        arise between those two users. If the IDs were numerically incremented
        from each other, then the two users would create the same ID and
        overwrite each others' webhooks. This is solved by randomly generating
        an ID on each client. An 8-character alphanumeric string is more than
        sufficient for this use case as there are no sane situations where the
        number of webhooks will give rise to a potential collision between two
        hook IDs.

        The function that adds webhooks and which calls this function to get an
        ID for the webhook also checks for collisions with existing hook IDs to
        ensure that an ID is never used twice, further minimizing the risk of a
        collision.
    */
    /*
        OBJECTIVE/REWARD FILTERS

        When a filter is added to a webhook to only trigger it if a specific
        objective and/or reward is reported, that filter is assigned a random ID
        so that the server can differentiate between the different filters.

        An example webhook that has two objective filters (evolve and catch) may
        have the following structure:

            hook_ID[objective][FID1][type] = evolve
            hook_ID[objective][FID1][params] = {"quantity":2}
            hook_ID[objective][FID2][type] = catch
            hook_ID[objective][FID2][params] = {"quantity":5}

        ... where ID is the ID of the webhook and FID1, FID2 are the filter IDs.
        This ID structure lets the server know that "evolve" and {"quantity":2}
        belong to the same filter, identified by FID1.

        The filter IDs are never stored anywhere, only temporarily generated by
        the client and processed by the server. A filter's ID will be different
        every time it is generated, and collision avoidance is implemented
        client-side to avoid two filters from overwriting each other.

        The filters themselves are stored on the server as a standard array
        without any associated IDs.
    */
    return Math.random().toString(36).substr(2, 8);
}

/*
    Creates a new HTML node representing a particular objective filter. When a
    user clicks on the button that adds a new objective filter, the user is
    prompted for which type of objective should be added as a filter, and upon
    confirmation, this function is called to create a node to display it on the
    document.

    hook
        The string ID of the hook this objective filter is added for.
*/
function getObjectiveFilterNode(hook) {
    /*
        Each filter should have a unique ID client-side. Generate such an ID for
        the new filter. Please see the `getNewID()` function to see how the IDs
        are generated.
    */
    var no = getNewID();

    /*
        Ensure that the ID is unique, to avoid conflicts where this
        filter would overwrite another one.
    */
    while ($(".hook-filter[data-filter-id=" + no + "]").length > 0) {
        no = getNewID();
    }

    /*
        Create the node. The node contains:
          - A <span> containing human-readable text identifying the properties
            of the selected objective filter
          - A hidden <input> containing the type of objective, as defined in
            /includes/data/objectives.yaml
          - A hidden <input> containing parameters for the objective (such as
            e.g. quantity, typing), in JSON format, associated with the
            objective as defined in /includes/data/objectives.yaml
          - A button that allows users to edit the objective filter
          - A button that allows users to remove the objective filter from the
            webhook
    */
    var node = $.parseHTML(
        '<div class="hook-filter" data-filter-id="' + no + '">' +
            '<span class="hook-objective-text"></span>' +
            '<input type="hidden" class="hook-objective-type" name="hook_' + hook + '[objective][' + no + '][type]" value="unknown">' +
            '<input type="hidden" class="hook-objective-params" name="hook_' + hook + '[objective][' + no + '][params]" value="[]">' +
            '<div class="hook-filter-actions">' +
                '<i class="fas fa-edit hook-edit hook-objective-edit"></i> ' +
                '<i class="far fa-times-circle hook-delete hook-objective-delete"></i>' +
            '</div>' +
        '</div>'
    );
    return node;
}

/*
    Opens a dialog that allows users to create or modify objective filters.

    newObjective
        A boolean that is true if the dialog should result in the creation of a
        new objective filter, or false if the dialog should edit an existing
        filter instead.

    caller
        A jQuery node that represents the button that called this function. This
        is used to identify the webhook that the given filter should be applied
        to, as well as identifying the particular filter that is being edited,
        if `newObjective` is false.
*/
function editObjective(newObjective, caller) {
    /*
        Create an object that holds the filter's current objective.
    */
    var objective;

    if (newObjective) {
        /*
            If we're adding a new objective, we should change the title of the
            dialog box to reflect that. A new filter never has a defined
            objective, so we'll set it to the default "unknown" objective type.
        */
        $("#hooks-update-objective-overlay-title").text(
            resolveI18N("admin.clientside.hooks.popup.add_objective")
        );
        objective = {
            type: "unknown",
            params: []
        }
    } else {
        /*
            If we're changing an existing objective instead, change the title to
            "Edit objective", and fetch the current objective type and
            parameters from the hidden input fields in the objective filter
            node. The objective type is stored in the `hook-objective-type`
            input box as a string, and can be set directly. The parameters are
            stored in `hook-objective-params` as a JSON serialized object, and
            should be parsed first to turn it back into an object.
        */
        $("#hooks-update-objective-overlay-title").text(
            resolveI18N("admin.clientside.hooks.popup.edit_objective")
        );
        objective = {
            type: caller.closest(".hook-filter").find("input[type=hidden].hook-objective-type").val(),
            params: JSON.parse(
                caller.closest(".hook-filter").find("input[type=hidden].hook-objective-params").val()
            )
        };
    }

    /*
        The objective dialog box has input fields for each of the parameters
        that an objective may request values for. These should be reset so they
        are in a default state when the dialog box opens, unless a value for
        them already exists in `objective.params`.

        Input boxes are set to `null` to return to an empty state.
        Select boxes should default to their first child option.
    */
    $("input.parameter").val(null);
    $("select.parameter").each(function() {
        $(this)[0].selectedIndex = 0;
    });

    /*
        Overwrite the default values with specific ones if they are already
        defined in the `objective` variable.
    */
    $("#update-hook-objective").val(objective.type == "unknown" ? null : objective.type);
    if (objective.type !== "unknown") {
        /*
            If the objective parameters is an array, it is most likely empty.
            Convert it to an empty object instead.
        */
        if (objective.params.constructor === Array) {
            objective.params = {};
        }

        /*
            If the objective type is defined, the change event for the objective
            type selection box should be triggered. This will ensure that the
            correct parameter input boxes are displayed when the dialog box is
            made visible. The event hides all parameter inputs and shows the
            ones that are relevant to the selected objective type, as defined in
            /includes/data/objectives.yaml.
        */
        $("#update-hook-objective").trigger("change");

        /*
            The `objectives` object is defined in hooks.php. It contains a list
            of all available objectives, and their parameters. We use this
            object to identify which parameters this objective type requires,
            then loop over them to ensure the required parameter inputs are
            filled in in the dialog box.
        */
        var params = objectives[objective.type].params;
        for (var i = 0; i < params.length; i++) {
            if (objective.params.hasOwnProperty(params[i])) {
                /*
                    The objective has the parameter specified. This indicates
                    that the parameter is enabled. Check the checkbox next to
                    the parameter name to reflect this.
                */
                var enableNode = $("#update-hook-objective-param-" + params[i] + "-enable");
                enableNode.prop("checked", true);
                enableNode.trigger("input");
                /*
                    This function is defined in hooks.php as it contains server-
                    generated code. It parses the data from `objective.params`
                    and fills in the resulting data to the input boxes in the
                    dialog box so that it can be edited by the user.
                */
                parseObjectiveParameter(params[i], objective.params[params[i]]);
            } else {
                /*
                    The objective does not have the parameter specified. This
                    indicates that the parameter is disabled. Uncheck the
                    checkbox next to the parameter name to reflect this.
                */
                var enableNode = $("#update-hook-objective-param-" + params[i] + "-enable");
                enableNode.prop("checked", false);
                enableNode.trigger("input");
            }

        }
    } else {
        /*
            If the objective type is undefined, hide all parameter input boxes,
            since the `unknown` type does not take parameters.
        */
        $(".objective-parameter").hide();
    }

    /*
        Define a handler for the button that saves the updated objective filter
        data. This button exists on the dialog, and the handler contains code
        that is specific to the particular filter instance that is being edited.
    */
    $("#update-hook-objective-submit").on("click", function() {
        /*
            Fetch the objective type the user chose as a string value from the
            objective selection menu.
        */
        var objective = $("#update-hook-objective").val();

        /*
            If that objective is null (i.e. they try to add a new filter without
            selecting an objective), throw an error.
        */
        if (objective == null) {
            alert(resolveI18N(
                "admin.clientside.hooks.update.objective.failed.message",
                resolveI18N("poi.update.failed.reason.objective_null")
            ));
            return;
        }

        /*
            Fetch the definition for the selected objective as defined in
            /includes/data/objectives.yaml. This contains parameter and category
            data for the given objective.
        */
        var objDefinition = objectives[objective];

        /*
            Loop over all the parameters, as required by the objective
            definition. For each parameter, its value is fetched from the dialog
            and parsed by `getObjectiveParameter()`, which is defined in
            hooks.php. This function converts the dialog box data into a JSON
            object or value that represents the parameter data, so that it can
            be serialized to JSON. If any of the data is undefined/not set,
            throw an error. The resultant data should be stored in the
            `objParams` object in the format:

                objParams.parameterName = parameterValue
        */
        var objParams = {};
        for (var i = 0; i < objDefinition.params.length; i++) {
            var paramData = getObjectiveParameter(objDefinition.params[i]);
            var enableParam = $(
                "#update-hook-objective-param-" + objDefinition.params[i] + "-enable"
            ).is(":checked");
            if (enableParam && (paramData == null || paramData == "")) {
                alert(resolveI18N(
                    "admin.clientside.hooks.update.objective.failed.message",
                    resolveI18N("xhr.failed.reason.missing_fields")
                ));
                return;
            }
            if (enableParam) objParams[objDefinition.params[i]] = paramData;
        }

        /*
            Get the document node that represents this objective filter, or
            create a new one, to fill in the required data for the filter.
        */
        var node;
        if (newObjective) {
            node = $(getObjectiveFilterNode(caller.closest(".hook-instance").attr("data-hook-id")));
        } else {
            node = caller.closest(".hook-filter");
        }

        /*
            Update the hidden fields (these contain the data that is sent to the
            server) and the human-readable string that displays the objective as
            text to the user. The latter is handled by `resolveObjective()`,
            which is defined in /js/clientside-i18n.php.
        */
        node.find("input[type=hidden].hook-objective-type").val(objective);
        node.find("input[type=hidden].hook-objective-params").val(JSON.stringify(objParams));
        node.find("span.hook-objective-text").text(resolveObjective({
            type: objective,
            params: objParams
        }));

        /*
            If the objective is new, the node must obviously be appended to the
            document. However, the selection box that lets user switch between
            filtering modes (whitelist/blacklist) must also be enabled, if
            currently disabled. It makes no sense to select a filtering mode
            for a webhook if there are no filters (whitelist would never trigger
            the webhook under those circumstances), but as soon as a filter is
            added, this becomes meaningful again.
        */
        if (newObjective) {
            caller.closest(".hook-filter-objectives").append(node);
            node.parent().find(".hook-mode-objective").prop("disabled", false);
        }

        /*
            Since the filters on the webhook have now changed, the human
            readable summary in the webhook header should be updated as well, so
            the user can see at a glance what kind of filters this hook uses.
        */
        updateSummary(node);

        /*
            Because this click event is specific to this filter, ensure that it
            is disabled before the dialog is closed, so that it's not called
            several times when changing other filters later.
        */
        $("#update-hook-objective-submit").off();
        $("#hooks-update-objective-overlay").fadeOut(150);
    });

    $("#hooks-update-objective-overlay").fadeIn(150);
}

/*
    Creates a new HTML node representing a particular reward filter. When a user
    clicks on the button that adds a new reward filter, the user is prompted for
    which type of reward should be added as a filter, and upon confirmation,
    this function is called to create a node to display it on the document.

    hook
        The string ID of the hook this reward filter is added for.
*/
function getRewardFilterNode(hook) {
    /*
        Each filter should have a unique ID client-side. Generate such an ID for
        the new filter. Please see the `getNewID()` function to see how the IDs
        are generated.
    */
    var no = getNewID();

    /*
        Ensure that the ID is unique, to avoid conflicts where this
        webhook overwrite another one.
    */
    while ($(".hook-filter[data-filter-id=" + no + "]").length > 0) {
        no = getNewID();
    }

    /*
        Create the node. The node contains:
          - A <span> containing human-readable text identifying the properties
            of the selected reward filter
          - A hidden <input> containing the type of reward, as defined in
            /includes/data/rewards.yaml
          - A hidden <input> containing parameters for the reward (such as e.g.
            quantity of items awarded), in JSON format, associated with the
            reward as defined in /includes/data/rewards.yaml
          - A button that allows users to edit the reward filter
          - A button that allows users to remove the reward filter from the
            webhook
    */
    var node = $.parseHTML(
        '<div class="hook-filter" data-filter-id="' + no + '">' +
            '<span class="hook-reward-text"></span>' +
            '<input type="hidden" class="hook-reward-type" name="hook_' + hook + '[reward][' + no + '][type]" value="unknown">' +
            '<input type="hidden" class="hook-reward-params" name="hook_' + hook + '[reward][' + no + '][params]" value="[]">' +
            '<div class="hook-filter-actions">' +
                '<i class="fas fa-edit hook-edit hook-reward-edit"></i> ' +
                '<i class="far fa-times-circle hook-delete hook-reward-delete"></i>' +
            '</div>' +
        '</div>'
    );
    return node;
}

/*
    Opens a dialog that allows users to create or modify reward filters.

    newReward
        A boolean that is true if the dialog should result in the creation of a
        new reward filter, or false if the dialog should edit an existing filter
        instead.

    caller
        A jQuery node that represents the button that called this function. This
        is used to identify the webhook that the given filter should be applied
        to, as well as identifying the particular filter that is being edited,
        if `newReward` is false.
*/
function editReward(newReward, caller) {
    /*
        Create an object that holds the filter's current reward.
    */
    var reward;

    if (newReward) {
        /*
            If we're adding a new reward, we should change the title of the
            dialog box to reflect that. A new filter never has a defined reward,
            so we'll set it to the default "unknown" reward type.
        */
        $("#hooks-update-reward-overlay-title").text(
            resolveI18N("admin.clientside.hooks.popup.add_reward")
        );
        reward = {
            type: "unknown",
            params: []
        }
    } else {
        /*
            If we're changing an existing reward instead, change the title to
            "Edit reward", and fetch the current reward type and parameters from
            the hidden input fields in the reward filter node. The reward type
            is stored in the `hook-reward-type` input box as a string, and can
            be set directly. The parameters are stored in `hook-reward-params`
            as a JSON serialized object, and should be parsed first to turn it
            back into an object.
        */
        $("#hooks-update-reward-overlay-title").text(
            resolveI18N("admin.clientside.hooks.popup.edit_reward")
        );
        reward = {
            type: caller.closest(".hook-filter").find("input[type=hidden].hook-reward-type").val(),
            params: JSON.parse(
                caller.closest(".hook-filter").find("input[type=hidden].hook-reward-params").val()
            )
        };
    }

    /*
        The reward dialog box has input fields for each of the parameters that a
        reward may request values for. These should be reset so they are in a
        default state when the dialog box opens, unless a value for them already
        exists in `reward.params`.

        Input boxes are set to `null` to return to an empty state.
        Select boxes should default to their first child option.
    */
    $("input.parameter").val(null);
    $("select.parameter").each(function() {
        $(this)[0].selectedIndex = 0;
    });

    /*
        Overwrite the default values with specific ones if they are already
        defined in the `reward` variable.
    */
    $("#update-hook-reward").val(reward.type == "unknown" ? null : reward.type);
    if (reward.type !== "unknown") {
        /*
            If the reward parameters is an array, it is most likely empty.
            Convert it to an empty object instead.
        */
        if (reward.params.constructor === Array) {
            reward.params = {};
        }

        /*
            If the reward type is defined, the change event for the reward type
            selection box should be triggered. This will ensure that the correct
            parameter input boxes are displayed when the dialog box is made
            visible. The event hides all parameter inputs and shows the ones
            that are relevant to the selected reward type, as defined in
            /includes/data/rewards.yaml.
        */
        $("#update-hook-reward").trigger("change");

        /*
            The `rewards` object is defined in hooks.php. It contains a list
            of all available rewards, and their parameters. We use this object
            to identify which parameters this reward type requires, then loop
            over them to ensure the required parameter inputs are filled in in
            the dialog box.
        */
        var params = rewards[reward.type].params;
        for (var i = 0; i < params.length; i++) {
            if (reward.params.hasOwnProperty(params[i])) {
                /*
                    The reward has the parameter specified. This indicates that
                    the parameter is enabled. Check the checkbox next to the
                    parameter name to reflect this.
                */
                var enableNode = $("#update-hook-reward-param-" + params[i] + "-enable");
                enableNode.prop("checked", true);
                enableNode.trigger("input");
                /*
                    This function is defined in hooks.php as it contains server-
                    generated code. It parses the data from `reward.params` and
                    fills in the resulting data to the input boxes in the dialog
                    box so that it can be edited by the user.
                */
                parseRewardParameter(params[i], reward.params[params[i]]);
            } else {
                /*
                    The reward does not have the parameter specified. This
                    indicates that the parameter is disabled. Uncheck the
                    checkbox next to the parameter name to reflect this.
                */
                var enableNode = $("#update-hook-reward-param-" + params[i] + "-enable");
                enableNode.prop("checked", false);
                enableNode.trigger("input");
            }
        }
    } else {
        /*
            If the reward type is undefined, hide all parameter input boxes,
            since the `unknown` type does not take parameters.
        */
        $(".reward-parameter").hide();
    }

    /*
        Define a handler for the button that saves the updated reward filter
        data. This button exists on the dialog, and the handler contains code
        that is specific to the particular filter instance that is being edited.
    */
    $("#update-hook-reward-submit").on("click", function() {
        /*
            Fetch the reward type the user chose as a string value from the
            reward selection menu.
        */
        var reward = $("#update-hook-reward").val();

        /*
            If that reward is null (i.e. they try to add a new filter without
            selecting a reward), throw an error.
        */
        if (reward == null) {
            alert(resolveI18N(
                "admin.clientside.hooks.update.reward.failed.message",
                resolveI18N("poi.update.failed.reason.reward_null")
            ));
            return;
        }

        /*
            Fetch the definition for the selected reward as defined in
            /includes/data/rewards.yaml. This contains parameter and category
            data for the given reward.
        */
        var rewDefinition = rewards[reward];

        /*
            Loop over all the parameters, as required by the reward definition.
            For each parameter, its value is fetched from the dialog and parsed
            by `getRewardParameter()`, which is defined in hooks.php. This
            function converts the dialog box data into a JSON object or value
            that represents the parameter data, so that it can be serialized to
            JSON. If any of the data is undefined/not set, throw an error. The
            resultant data should be stored in the `rewParams` object in the
            format:

                rewParams.parameterName = parameterValue
        */
        var rewParams = {};
        for (var i = 0; i < rewDefinition.params.length; i++) {
            var paramData = getRewardParameter(rewDefinition.params[i]);
            var enableParam = $(
                "#update-hook-reward-param-" + rewDefinition.params[i] + "-enable"
            ).is(":checked");
            if (enableParam && (paramData == null || paramData == "")) {
                alert(resolveI18N(
                    "admin.clientside.hooks.update.reward.failed.message",
                    resolveI18N("xhr.failed.reason.missing_fields")
                ));
                return;
            }
            if (enableParam) rewParams[rewDefinition.params[i]] = paramData;
        }

        /*
            Get the document node that represents this reward filter, or create
            a new one, to fill in the required data for the filter.
        */
        var node;
        if (newReward) {
            node = $(getRewardFilterNode(caller.closest(".hook-instance").attr("data-hook-id")));
        } else {
            node = caller.closest(".hook-filter");
        }

        /*
            Update the hidden fields (these contain the data that is sent to the
            server) and the human-readable string that displays the reward as
            text to the user. The latter is handled by `resolveReward()`, which
            is defined in /js/clientside-i18n.php.
        */
        node.find("input[type=hidden].hook-reward-type").val(reward);
        node.find("input[type=hidden].hook-reward-params").val(JSON.stringify(rewParams));
        node.find("span.hook-reward-text").text(resolveReward({
            type: reward,
            params: rewParams
        }));

        /*
            If the reward is new, the node must obviously be appended to the
            document. However, the selection box that lets user switch between
            filtering modes (whitelist/blacklist) must also be enabled, if
            currently disabled. It makes no sense to select a filtering mode
            for a webhook if there are no filters (whitelist would never trigger
            the webhook under those circumstances), but as soon as a filter is
            added, this becomes meaningful again.
        */
        if (newReward) {
            caller.closest(".hook-filter-rewards").append(node);
            node.parent().find(".hook-mode-reward").prop("disabled", false);
        }

        /*
            Since the filters on the webhook have now changed, the human
            readable summary in the webhook header should be updated as well, so
            the user can see at a glance what kind of filters this hook uses.
        */
        updateSummary(node);

        /*
            Because this click event is specific to this filter, ensure that it
            is disabled before the dialog is closed, so that it's not called
            several times when changing other filters later.
        */
        $("#update-hook-reward-submit").off();
        $("#hooks-update-reward-overlay").fadeOut(150);
    });

    $("#hooks-update-reward-overlay").fadeIn(150);
}

/*
    The checkboxes next to the parameter names in the objective and reward
    filter editing dialogs should disable the input box for the parameter if the
    parameter is disabled. This event handler implements that action.
*/
$(".update-hook-param-checkbox").on("input", function() {
    /*
        Determine whether or not the checkbox is checked.
    */
    var paramEnabled = $(this).is(":checked");
    /*
        Find input boxes for the parameter.
    */
    var paramInputs = $(this).closest(".research-parameter").find(".parameter");
    /*
        Disable the input boxes.
    */
    paramInputs.prop("disabled", !paramEnabled);
});

/*
    Escapes HTML in a string.

    data
        The string to escape
*/
function encodeHTML(data) {
    /*
        This works by creating a new empty element, setting its innerText, and
        the extracting the innerHTML from the same node. If innerText contains
        HTML syntax, it will have to be escaped in the underlying HTML in order
        to render as text, hence we can fetch that escaped HTML by calling the
        contents of innerHTML.
    */
    return $("<div />").text(data).html();
}

/*
    Creates a human-readable text summary of the objective and reward filters of
    a webhook. This is displayed in the header bar for each webhook so users can
    quickly identify which filters apply to each hook.
*/
function updateSummary(node) {
    var objText = null;
    node.closest(".hook-instance").find(".hook-filter-objectives .hook-filter").each(function() {
        var filterMode = $(this).parent().find(".hook-mode-objective").val();

        /*
            For each of the objective filters, find the objective type and
            parameters from the hidden fields in each filter node, then
            translate those into a human-readable representation using
            `resolveObjective()` from /js/clientside-i18n.php.
        */
        var objective = resolveObjective({
            type: $(this).find("input[type=hidden].hook-objective-type").val(),
            params: JSON.parse($(this).find("input[type=hidden].hook-objective-params").val())
        });

        /*
            Create a HTML node with this string. This is done so that the span
            element containing this string can be properly styled with CSS.
        */
        var objHTML = '<span class="hook-head-objective-text">' + encodeHTML(objective) + '</span>';

        if (objText === null) {
            /*
                If this is the first filter that is being processed, we need to
                define the beginning of the string. Here, we need to take into
                account whether the filter mode is set to whitelisting or
                blacklisting behavior. If it's set to blacklist, the webhook
                should trigger on any given objective except those that are
                listed in the objective filters. Thus, we need to prepend "any
                objective except" to the string. If we're on whitelisting mode,
                we can just put the objective with without anything preceding
                it, as the implied meaning will be "any of the following" unless
                otherwise specified.
            */
            if (filterMode == "blacklist") {
                objText = resolveI18N("admin.clientside.hooks.any_objective_except", objHTML);
            } else {
                objText = objHTML;
            }
        } else {
            /*
                If `objText` is already defined, that means that the filter
                we're currently processing is not the first in the list - hence
                we need to append to a string we already have. We combine the
                different objectives using "X or Y" e.g. "Battle in 2 raids or
                Win 3 Gym battles". If the filter mode is set to blacklisting
                behavior, we'll use "X or Y" instead, as it flows better with
                language.
            */
            if (filterMode == "blacklist") {
                objText = resolveI18N("admin.clientside.hooks.multi_and", objText, objHTML);
            } else {
                objText = resolveI18N("admin.clientside.hooks.multi_or", objText, objHTML);
            }
        }
    });

    /*
        If there are no defined objective filters, the webhook defaults to being
        triggered by all research objectives, hence the objective text should
        read "any objective". We know that if `objText` is `null` at this point,
        there are no objective filters in place, since the loop above that loops
        over objective filters will set `objText` to some value if it can loop
        over any filter instances.
    */
    if (objText === null) {
        objText = '<span class="hook-head-objective-text">'
                + encodeHTML(resolveI18N("admin.clientside.hooks.any_objective"))
                + '</span>';
    }

    /*
        Now, do the same with reward filters!
    */
    var rewText = null;
    node.closest(".hook-instance").find(".hook-filter-rewards .hook-filter").each(function() {
        var filterMode = $(this).parent().find(".hook-mode-reward").val();

        /*
            For each of the reward filters, find the reward type and parameters
            from the hidden fields in each filter node, then translate those
            into a human-readable representation using `resolveReward()` from
            /js/clientside-i18n.php.
        */
        var reward = resolveReward({
            type: $(this).find("input[type=hidden].hook-reward-type").val(),
            params: JSON.parse($(this).find("input[type=hidden].hook-reward-params").val())
        });

        /*
            Create a HTML node with this string. This is done so that the span
            element containing this string can be properly styled with CSS.
        */
        var rewHTML = '<span class="hook-head-reward-text">' + encodeHTML(reward) + '</span>';

        if (rewText === null) {
            /*
                If this is the first filter that is being processed, we need to
                define the beginning of the string. Here, we need to take into
                account whether the filter mode is set to whitelisting or
                blacklisting behavior. If it's set to blacklist, the webhook
                should trigger on any given reward except those that are listed
                in the reward filters. Thus, we need to prepend "any reward
                except" to the string. If we're on whitelisting mode, we can
                just put the reward with without anything preceding it, as the
                implied meaning will be "any of the following" unless otherwise
                specified.
            */
            if (filterMode == "blacklist") {
                rewText = resolveI18N("admin.clientside.hooks.any_reward_except", rewHTML);
            } else {
                rewText = rewHTML;
            }
        } else {
            /*
                If `rewText` is already defined, that means that the filter
                we're currently processing is not the first in the list - hence
                we need to append to a string we already have. We combine the
                different rewards using "X or Y" e.g. "3 Super Potions or 1 Max
                Revive". If the filter mode is set to blacklisting behavior,
                we'll use "X or Y" instead, as it flows better with language.
            */
            if (filterMode == "blacklist") {
                rewText = resolveI18N("admin.clientside.hooks.multi_and", rewText, rewHTML);
            } else {
                rewText = resolveI18N("admin.clientside.hooks.multi_or", rewText, rewHTML);
            }
        }
    });

    /*
        If there are no defined reward filters, the webhook defaults to being
        triggered by all research rewards, hence the objective text should read
        "any reward". We know that if `rewText` is `null` at this point, there
        are no reward filters in place, since the loop above that loops over
        rewards filters will set `rewText` to some value if it can loop over any
        filter instances.
    */
    if (rewText === null) {
        rewText = '<span class="hook-head-reward-text">'
                + encodeHTML(resolveI18N("admin.clientside.hooks.any_reward"))
                + '</span>';
    }

    /*
        Finally, the objective string and reward string will be combined. The
        resulting string will be of the format "<Objectives> for <rewards>".
        There may be some languages where the order should be reversed for
        whatever reason, so we need to do an I18N lookup that puts these two in
        the correct order. This is handled by the order of {%1} and {%2} in the
        `poi.objective_text` I18N string of the language that is used.

        The resultant string is put in the header bar of the webhook (the target
        element being the element wih the `hook-summary-text` class).
    */
    var text = resolveI18N("poi.objective_text", objText, rewText);
    node.closest(".hook-instance").find(".hook-summary-text").html(text);
}

/*
    The following event handlers use event delegation to bind event handlers to
    all current elements, as well as all elements added in the future, that
    match the given selectors. This is so that newly added webhooks
    automatically have the necessary handlers to have client-side functionality
    without having to manually bind events every time a new webhook is added.
*/

/*
    Clicking on the header bar of a webhook should toggle the visibility of the
    webhook's body, which contains the list of settings for each particular
    webhook.

    Displays: on webhook headers
*/
$(".hook-list").on("click", ".hook-head", function() {
    $(this).parent().find(".hook-body").toggle();
});

/*
    Changing the webhook's target URL should result in the domain of the entered
    URL to be displayed in the webhook's header bar.

    Displays: on webhook headers
*/
$(".hook-list").on("input", ".hook-target", function() {
    /*
        Determine the URI scheme first, since the domain should be extracted
        differently depending on schema.
    */
    var type = $(this).attr("data-uri-scheme");
    var url = "?";

    switch (type) {
        case "http":
            /*
                HTTP URLs should use the authority part of the URI as defined by
                RFC 3986 section 3.2.
            */
            url = $(this).val().split("/");
            if (url.length >= 3) {
                // Authority was found; use it for the domain
                url = url[2];
            } else {
                // Authority was not found; use fallback instead
                url = resolveI18N("admin.clientside.domain.unknown");
            }
            break;

        case "tg":
            /*
                Telegram URLs should use the group ID instead. This is defined
                in the `to` query variable of the URI.
            */
            url = $(this).val().split("?to=");
            if (url.length >= 2) {
                // Group ID was found; use it for the domain
                url = url[1];
            } else {
                // Group ID was not found; use fallback instead
                url = resolveI18N("admin.clientside.domain.unknown");
            }
            break;
    }

    $(this).closest(".hook-instance").find(".hook-domain").text(url);
    return true;
});

/*
    For Telegram webhooks, the webhook URL target input box is a selection box
    which does not allow users manually typing in a target URL. Instead, it has
    an option that, when selected, initiates a query to Telegram's API to list
    the groups the webhook bot is a member of. The results are then displayed in
    a dialog box where the user can pick one of those groups as its target.

    This functionality is because there is no way to find the ID of a Telegram
    group without using the Telegram API (hence it can't be done by users
    manually in the client). In order to make picking groups easier, the dialog
    box displays the name of each group rather than the group ID in the
    selection box containing the list of groups.

    Blame Telegram for this client design choice. See:
    https://stackoverflow.com/q/32423837
*/
$(".hook-list").on("change", 'select[data-uri-scheme="tg"].hook-target', function() {
    /*
        The selection box item that should trigger the dialog prompt has the
        value "_select" (it is displayed as "Select group").
    */
    if ($(this).val() == "_select") {
        /*
            Reset the selection box, so that it appears correctly with the
            previous URL in the background while the dialog box is open for the
            user to select a new one. Also trigger the input event so the target
            domain indicator in the header bar is also updated with the group ID
            of the previous group (selecting the "_select" item will reset it to
            the default "?").

            These changes are applied immediately, so to the user, there is no
            visible period when the selection box shows "Select group" or the
            webhook target is empty, when the dialog box opens.
        */
        $(this)[0].selectedIndex = 0;
        $(this).trigger("input");

        /*
            A bot token is required to list the groups said bot is a member of.
            (How else would Telegram know which bot made the request? :D)
        */
        var token = $(this).closest(".hook-instance").find(".hook-tg-bot-token").val();

        if (token == "") {
            alert(resolveI18N(
                "admin.clientside.hooks.tg.xhr.groups.failed.empty_token"
            ));
            return;
        }

        var xhrURI = "../xhr/tg-groups.php?token=" + encodeURI(token);

        /*
            When the list of webhooks is loaded on the administration pages, the
            server ensures that all Telegram bot tokens in all webhooks are
            substituted with a password mask/placeholder value rather than
            having the bot tokens being sent in plaintext. For example, a
            webhook may have a Telegram bot token stored internally in the
            configuration file, but when that hook is presented on the webhooks
            page in the administration interface, the bot token is replaced in
            the HTML code with a random string, so that the bot token itself is
            never sent back to the client.

            The reason for this is that Telegram for some reason decided that
            bot tokens are valid for sending messages (as is the point of a
            webhook), but are also in scope to perform user authentication. The
            bot token is used to verify that the authentication parameters
            passed from Telegram actually originate from Telegram servers.
            Telegram uses an HMAC hash to perform this verification, where the
            secret key of the HMAC hash is created from the bot token. See
            https://core.telegram.org/widgets/login#checking-authorization.

            The reason Telegram uses the bot token for this purpose is that they
            assume the bot token will be kept secret. After all, only the bot
            developer and Telegram themselves would know this token. Hence,
            anyone with the bot token will be able to craft a valid, signed HMAC
            hash that can be used to authenticate an arbitrary user.

            FreeField allows using Telegram both for webhooks and for
            authentication. It is likely that many installations will re-use the
            same bot token for both purposes. If a user on such an installation
            has access to the webhooks administration interface, they would be
            able to fetch the bot token from registered Telegram webhooks, if
            the bot token was sent in plaintext. They could then use that bot
            token to sign authentication data as if it was signed by Telegram
            itself. The server would have no reason to suspect anything unusual
            was going on, and as such would approve the authentication request.

            Users with access to the webhook administration page would be able
            to exploit this vulnerability to forge a valid authentication of a
            higher privileged user. This could even result in the user being
            able to assign themselves to a higher permission group using the
            compromised account as a tool. By never sending the bot token back
            to the web browser under any circumstances, and instead sending a
            random string mask, this privilege escalation attack vector is
            eliminated.

            Not sending the bot token to the client causes another issue to
            arise. When a user requests to enumerate the Telegram groups a bot
            is in in order to select the correct target group for their
            webhook's messages, the bot token is required in order to identify
            and authorize the bot against Telegram's API. Since this token can't
            be supplied by the client, it must be supplied by the server
            instead. If the user has input a bot token manually on the webhooks
            user interface, we can of course use that token to request the bot's
            group memberships. If the user didn't specify a bot token for the
            current session, we can instead pass the ID of the webhook and have
            the enumeration script look up the bot token from the configuration
            file given the ID of the webhook the bot token is registered in.

            Telegram bot tokens match a very specific format (shown in the
            `token.match()` below), hence if the token doesn't match, we can
            assume that the value of the token field is the server-supplied
            randomly generated replacement mask instead. In that case, fetch the
            ID of the webhook and use that to look up the token server-side.
        */
        if (!token.match(/^\d+:[A-Za-z\d_-]+$/)) {
            var webhookId = $(this).closest(".hook-instance").attr("data-hook-id");
            xhrURI = "../xhr/tg-groups.php?forId=" + encodeURI(webhookId);
        }

        /*
            The event handler that handles the "submit" button for the Telegram
            group selection dialog is specific to each webhook. Hence, remove
            all existing handlers before adding one that is specific to the
            current hook.
        */
        $("#select-tg-group-submit").off();

        /*
            Listing group memberships may take some time, so we'll display a
            loading indicator while waiting for group enumeration.
        */
        $("#hooks-tg-groups-working").fadeIn(150);

        /*
            Calling the Telegram API directly from the browser is a bad idea for
            several reasons, so we'll use the proxy script /xhr/tg-groups.php to
            do it for us. Please see that file to learn how the listing works
            internally.
        */
        var hook = $(this).closest(".hook-instance");
        $.getJSON(xhrURI, function(data) {
            /*
                When we get a response, clear the list of groups so we don't
                have any duplicates, then append each option to the group
                selection box.
            */
            $("#select-tg-group-options").empty();
            var isEmpty = true;
            for (var id in data.groups) {
                if (data.groups.hasOwnProperty(id)) {
                    isEmpty = false;
                    $("#select-tg-group-options").append(
                        '<option value="tg://send?to=' + id + '">' +
                        data.groups[id] +
                        '</option>'
                    );
                }
            }

            /*
                If there are no groups, a dialog should open alerting the user
                to this. There may be situations in which this happens
                erroneously. Please see the documentation if you know the bot is
                in one or more Telegram groups, but this error is thrown
                regardless.
            */
            if (isEmpty) {
                $("#hooks-tg-groups-working").fadeOut(150);
                alert(resolveI18N(
                    "admin.clientside.hooks.tg.xhr.groups.failed.no_groups"
                ));
                return;
            }

            /*
                Create a click handler for the "submit" button in the group
                selection dialog that is specific to the webhook currently being
                edited. This handler should clear the current target URL
                <optgroup> in the webhook target URL selection box, and insert
                the user's selection instead, then select it. The "input" event
                of that selection box is then triggered to force an update to
                the target domain display in the webhook's header.
            */
            $("#select-tg-group-submit").on("click", function() {
                var target = $("#select-tg-group-options").val();
                hook.find(".hook-target-current-group").empty();
                hook.find(".hook-target-current-group").append(
                    '<option value="' + target + '">' + target + '</option>'
                );
                hook.find(".hook-target").val(target);
                hook.find(".hook-target").trigger("input");
                $("#hooks-tg-groups-overlay").fadeOut(150);
            });

            /*
                Hide the "working" loading screen and show the selection dialog
                instead.
            */
            $("#hooks-tg-groups-overlay").fadeIn(150);
            $("#hooks-tg-groups-working").fadeOut(150);

        }).fail(function(xhr) {
            $("#hooks-tg-groups-working").fadeOut(150);
            var data = xhr.responseJSON;
            alert(resolveI18N(data.reason));
        });
    }
});

/*
    Specific to Telegram webhooks: Telegram allows messages to be sent in
    several formats - plain text, Markdown and HTML. If the user selects a
    different parse mode, then the webhook body header needs to update to
    reflect that. E.g. if the parse mode was previously set to plain text, and
    the user changes it to Markdown, the header should change from "Text body"
    to "Markdown body".

    Displays: on webhook body
*/
$(".hook-list").on("change", '.hook-tg-parse-mode', function() {
    $(this).closest(".hook-instance").find(".hook-body-header").text(
        resolveI18N("admin.section.hooks.body." + $(this).val() + ".name")
    );
    var payload = $(this).closest(".hook-instance").find(".hook-payload");

    /*
        Syntax validation should also be performed against the given body
        format, if possible. Ensure that the `data-validate-as` tag is correctly
        set for each format.

        Note: Markdown is not currently validated, so it falls back to plain
        text validation, i.e. default (no validation performed).
    */
    switch ($(this).val()) {
        case "html":
            payload.attr("data-validate-as", "html");
            break;
        default:
            payload.attr("data-validate-as", "text");
            break;
    }
    /*
        Trigger the input event of the payload body so that a validation check
        is performed immediately, rather than waiting for the user to make a
        change to the body text.

        For example, if the payload contains invalid HTML, but the parsing mode
        is set to plain text, the payload is valid. However, changing the
        parsing mode to HTML will result in the payload failing validation. If
        validation is not manually triggered here, the payload would appear as
        valid until the user made a change to it.
    */
    payload.trigger("input");
});

/*
    The webhook body/payload accepts various variables (like `<%COORDS%>`) that
    are substituted with their respective values whenever a webhook is called.
    There is a help section that lists these variables in a section above the
    webhook body input box that is hidden by default. This link, reading "Show
    help", toggles this help section between visible and hidden, and updates its
    own label to "Show help" or "Hide help" accordintly.

    Displays: on webhook body
*/
$(".hook-list").on("click", ".hook-show-help", function(e) {
    e.preventDefault();
    var help = $(this).closest(".hook-body").find(".hook-syntax-help");
    help.toggle();
    if (help.is(":visible")) {
        $(this).text(resolveI18N("admin.clientside.hooks.syntax.hide"));
    } else {
        $(this).text(resolveI18N("admin.clientside.hooks.syntax.show"));
    }
});

/*
    Button that adds a new objective filter to the given webhook. Displayed as a
    plus symbol next to the "Objectives" header on webhooks.

    Displays: on webhook body
*/
$(".hook-list").on("click", ".hook-objective-add", function(e) {
    e.preventDefault();
    /*
        The first parameter to `editObjective()` indicates whether or not a new
        filter should be created (true) or if an existing one should be edited
        (false). Setting this to true will result in the creation of a new
        objective filter, which is the wanted behavior.
    */
    editObjective(true, $(this));
});

/*
    Button that edits an existing webhook objective filter.
    Displays: on each objective filter in a webhook
*/
$(".hook-list").on("click", ".hook-objective-edit", function() {
    /*
        This time we're editing an existing filter instead, so we'll pass
        `false` as the first argument to indicate that we're editing an existing
        filter. The filter in question is determined by looking at the DOM
        parents of the element that resulted in a call to this event handler,
        since said element is within the node of the hook filter that is being
        edited.
    */
    editObjective(false, $(this));
});

/*
    Button that deletes an existing webhook objective filter.
    Displays: on each objective filter in a webhook
*/
$(".hook-list").on("click", ".hook-objective-delete", function() {
    /*
        Since the hook updates are saved server-side, and the data is sent as an
        HTML form, simply deleting the hook node (which in turn contains the
        hidden hook data input fields) is enough to prevent the data from being
        sent to the server and thus processed. The server-side list of
        objectives is cleared and built anew from the list of objectives sent in
        the form, so removing it from the form will remove it from the server-
        side list and will prevent it from being added back.
    */
    var hook = $(this).closest(".hook-instance");
    $(this).closest(".hook-filter").remove();
    if (hook.find(".hook-filter-objectives .hook-filter").length == 0) {
        hook.find(".hook-mode-objective").prop("disabled", true);
    }

    /*
        All filter changes should result in an update to the human-readable
        summary of filters displayed in the webhook header.
    */
    updateSummary(hook);
});

/*
    Selection box that switches between whitelisting and blacklisting mode for
    objective filters. This box should update the human-readable summary in the
    webhook header to reflect this change.

    Displays: on webhook body
*/
$(".hook-list").on("click", ".hook-mode-objective", function() {
    updateSummary($(this));
});

/*
    Button that cancels the dialog that lets the user change the parameters of
    an objective filter.

    Displays: on objective filter paramteter dialog
*/
$("#update-hook-objective-cancel").on("click", function() {
    /*
        The submission button is unique to each particular filter, and is bound
        whenever the dialog appears. Disable any event handlers for that button
        before the dialog is closed.
    */
    $("#update-hook-objective-submit").off();
    $("#hooks-update-objective-overlay").fadeOut(150);
});

/*
    Button that adds a new reward filter to the given webhook. Displayed as a
    plus symbol next to the "Rewards" header on webhooks.

    Displays: on webhook body
*/
$(".hook-list").on("click", ".hook-reward-add", function(e) {
    e.preventDefault();
    /*
        The first parameter to `editReward()` indicates whether or not a new
        filter should be created (true) or if an existing one should be edited
        (false). Setting this to true will result in the creation of a new
        reward filter, which is the wanted behavior.
    */
    editReward(true, $(this));
});

/*
    Button that edits an existing webhook reward filter.
    Displays: on each reward filter in a webhook
*/
$(".hook-list").on("click", ".hook-reward-edit", function() {
    /*
        This time we're editing an existing filter instead, so we'll pass
        `false` as the first argument to indicate that we're editing an existing
        filter. The filter in question is determined by looking at the DOM
        parents of the element that resulted in a call to this event handler,
        since said element is within the node of the hook filter that is being
        edited.
    */
    editReward(false, $(this));
});

/*
    Button that deletes an existing webhook reward filter.
    Displays: on each reward filter in a webhook
*/
$(".hook-list").on("click", ".hook-reward-delete", function() {
    /*
        Since the hook updates are saved server-side, and the data is sent as an
        HTML form, simply deleting the hook node (which in turn contains the
        hidden hook data input fields) is enough to prevent the data from being
        sent to the server and thus processed. The server-side list of
        rewards is cleared and built anew from the list of rewards sent in the
        form, so removing it from the form will remove it from the server-side
        list and will prevent it from being added back.
    */
    var hook = $(this).closest(".hook-instance");
    $(this).closest(".hook-filter").remove();
    if (hook.find(".hook-filter-rewards .hook-filter").length == 0) {
        hook.find(".hook-mode-reward").prop("disabled", true);
    }

    /*
        All filter changes should result in an update to the human-readable
        summary of filters displayed in the webhook header.
    */
    updateSummary(hook);
});

/*
    Selection box that switches between whitelisting and blacklisting mode for
    reward filters. This box should update the human-readable summary in the
    webhook header to reflect this change.

    Displays: on webhook body
*/
$(".hook-list").on("click", ".hook-mode-reward", function() {
    updateSummary($(this));
});

/*
    Button that cancels the dialog that lets the user change the parameters of
    a reward filter.

    Displays: on reward filter paramteter dialog
*/
$("#update-hook-reward-cancel").on("click", function() {
    $("#update-hook-reward-submit").off();
    $("#hooks-update-reward-overlay").fadeOut(150);
});

/*
    Now that all event handlers have been registered, it's time to initialize
    the list of existing webhooks and spawn editable webhook nodes for each
    instance of a webhook on the server.
*/
for (var i = 0; i < hooks.length; i++) {
    /*
        Hooks is a list of webhook objects, defined in hooks.php. It follows the
        structure established in /admin/apply-hooks.php. Fetch the correct hook
        for this loop iteration and create a node that we can modify before we
        append it to the webhook list.
    */
    var hook = hooks[i];
    var node = $(createHookNode(hook.type, hook.id));

    // Target URL
    node.find(".hook-target").val(hook.target);
    // Webhook language
    node.find(".hook-lang").val(hook.language);
    // Icon set to use for icon URLs passed to the webhook
    node.find(".hook-icon-set").val(hook.icons);
    // The body of the request itself
    node.find(".hook-payload").val(hook.body);
    // Whitelist/blacklist mode for objectives
    node.find(".hook-mode-objective").val(hook["filter-mode"].objectives);
    // Whitelist/blacklist mode for rewards
    node.find(".hook-mode-reward").val(hook["filter-mode"].rewards);
    // Geofence, if present
    if (hook.hasOwnProperty("geofence") && hook.geofence !== null) {
        node.find(".hook-geofence").val(hook.geofence);
    }
    // Species set to use for the icon URLs passed to the webhook, if applicable
    if (hook.hasOwnProperty("species")) {
        node.find(".hook-species-set").val(hook.species);
    }
    // Whether or not to use said species icon set
    if (hook.hasOwnProperty("show-species")) {
        node.find(".hook-show-species").prop("checked", hook["show-species"]);
    }

    /*
        There is no reason to enable an already active webhook, or disable one
        that is inactive, so we'll remove the appropriate actions from the
        webhook action selector depending on the state of the webhook.
    */
    if (hook.active) {
        node.find("select.hook-actions > option[value=enable]").remove();
    } else {
        node.find("select.hook-actions > option[value=disable]").remove();
    }

    /*
        Telegram specific settings
    */
    if (hook.type === "telegram") {
        // Telegram bot token
        node.find(".hook-tg-bot-token").val(hook.options["bot-token"]);

        /*
            The webhook target group. Clear the <optgroup> that contains the
            standard empty target <option> and insert and select an option for
            the currently selected webhook target instead.
        */
        node.find(".hook-target-current-group").empty();
        node.find(".hook-target-current-group").append(
            '<option value="' + hook.target + '">' + hook.target + '</option>'
        );
        node.find(".hook-target").val(hook.target);

        // Message/body text parsing mode (txt/md/html)
        node.find(".hook-tg-parse-mode")
            .val(hook.options["parse-mode"]);
        // Whether or not web previews should be disabled for posted messages
        node.find(".hook-tg-disable-web-page-preview")
            .prop("checked", hook.options["disable-web-page-preview"]);
        // Whether or not posted messages should trigger notifications
        node.find(".hook-tg-disable-notification")
            .prop("checked", hook.options["disable-notification"]);
    }

    for (var j = 0; j < hook.objectives.length; j++) {
        /*
            For each of the objective filters on the webhook, a hook node needs
            to be created that represents that filter. That node has two hidden
            input boxes containing the type of objective, and the parameters of
            that objective as a JSON object, respectively. Fill in those input
            boxes with the objective data and also convert it into a human-
            readable string that is displayed on the node, so the user can
            idenfity the purpose of the filter.
        */
        var filter = $(getObjectiveFilterNode(hook.id));
        filter.find("input[type=hidden].hook-objective-type").val(
            hook.objectives[j].type
        );
        filter.find("input[type=hidden].hook-objective-params").val(
            JSON.stringify(hook.objectives[j].params)
        );
        filter.find("span.hook-objective-text").text(resolveObjective({
            type: hook.objectives[j].type,
            params: hook.objectives[j].params
        }));
        node.find(".hook-filter-objectives").append(filter);

        /*
            Enable the selection box that lets users switch between filter
            blacklisting and whitelisting modes. This is disabled by default as
            having this selection box enabled with no filters present doesn't
            make much sense (whitelisting mode on a hook with no filters would
            disable the hook).
        */
        filter.parent().find(".hook-mode-objective").prop("disabled", false);
    }

    for (var j = 0; j < hook.rewards.length; j++) {
        /*
            For each of the reward filters on the webhook, a hook node needs to
            be created that represents that filter. That node has two hidden
            input boxes containing the type of reward, and the parameters of
            that reward as a JSON object, respectively. Fill in those input
            boxes with the reward data and also convert it into a human-readable
            string that is displayed on the node, so the user can idenfity the
            purpose of the filter.
        */
        var filter = $(getRewardFilterNode(hook.id));
        filter.find("input[type=hidden].hook-reward-type").val(
            hook.rewards[j].type
        );
        filter.find("input[type=hidden].hook-reward-params").val(
            JSON.stringify(hook.rewards[j].params)
        );
        filter.find("span.hook-reward-text").text(resolveReward({
            type: hook.rewards[j].type,
            params: hook.rewards[j].params
        }));
        node.find(".hook-filter-rewards").append(filter);

        /*
            Enable the selection box that lets users switch between filter
            blacklisting and whitelisting modes. This is disabled by default as
            having this selection box enabled with no filters present doesn't
            make much sense (whitelisting mode on a hook with no filters would
            disable the hook).
        */
        filter.parent().find(".hook-mode-reward").prop("disabled", false);
    }

    /*
        All of the fields in this webhook have been filled in, so we can update
        the summary displayed in the webhook header bar (which reads data from
        the webhook body) and then display the webhook.
    */
    updateSummary(node);
    $(hook.active ? "#active-hooks-list" : "#inactive-hooks-list").append(node);

    /*
        After the node has been added to the document, we can start triggering
        events. Trigger the `input` event of the box with the webhook target so
        the target domain in the webhook header bar is updated as well.
    */
    node.find(".hook-target").trigger("input");

    /*
        For Telegram webhooks, we also have to trigger the `change` event of the
        box where users change the parsing mode of the message body between
        plain text, Markdown and HTML. This is so that the header above the body
        input box updates to correctly reflect the content format the user is
        expected to use, and to set up the correct input validation method to
        ensure the text body is syntactically valid before the form is
        submitted.
    */
    if (hook.type === "telegram") {
        node.find(".hook-tg-parse-mode").trigger("change");
    }
}
