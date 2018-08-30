/*
    Client-side input validation. This ensures that invalid data cannot be
    submitted to the server, since the server will discard invalid data
    automatically.

    Each input box that has validation enabled has an attribute named
    `data-validate-as`. This attribute contains the type of data that the input
    box is expected to contain.
*/
function validateInput(e) {
    if (e.is("[data-validate-as]")) {
        var type = e.attr("data-validate-as");
        var value = e.val();
        switch (type) {

            case "json":
                /*
                    To check for JSON validity, attempt to parse it. If it can't
                    be parsed, it throws an exception, and is invalid.
                */
                try {
                    JSON.parse(value);
                } catch (e) {
                    return false;
                }
                return true;

            case "html":
                /*
                    To check for HTML validity, create an empty div element and
                    assign the HTML string to the innerHTML of the element. If
                    the HTML string contains invalid syntax (such as unclosed
                    tags), the string will be adapted to either escape or change
                    the HTML so that it becomes valid (in the case of unclosed
                    tags, it will close the tags), meaning the innerHTML will
                    change from the input passed to it. Simply check if the
                    innerHTML is still the same string as the HTML string we
                    assigned to it, and if it's not, that means it was changed
                    in some way to become valid, hence it wasn't valid in the
                    first place, and we return false.

                    Note that we have to remove replacement tags for webhooks
                    (e.g. <%COORDS%>) from the string first, because those
                    aren't valid HTML. They are replaced server-side so that the
                    output does not contain those tags.
                */
                var div = document.createElement("div");
                /*
                    This regex matches:

                    <%[^%\(]+
                        The start of a tag (the tag opener <% plus the tag
                        itself, which can be any symbol except \(, which denotes
                        the start of an argument list, e.g. <%TIME(c)>, and %,
                        which denotes the end of the tag)

                    (\([^\)]+\))?
                        The argument list, wrapped in parentheses, if one is
                        present.

                    %>
                        The end of the tag.
                */
                var filtered = value.replace(/<%[^%\(]+(\([^\)]+\))?%>/g, "");
                div.innerHTML = filtered;
                return div.innerHTML === filtered;

            case "http-uri":
                /*
                    HTTP URLs should start with "http://" or "https://".
                */
                return value.match(/^https?\:\/\//);

            case "tg-uri":
                /*
                    Telegram URLs should start with "tg://send?to=".
                */
                return value.match(/^tg\:\/\/send\?to=?/);

            case "regex-string":
                /*
                    Strings which should validate against a custom regex. This
                    regex is stored in the `data-validate-regex` attribute of
                    the element.
                */
                return value.match(new RegExp(e.attr("data-validate-regex")));

            case "geofence":
                /*
                    Valid geofences. This can be either an empty string to
                    disable the geofence, or a list of coordinate pairs.

                    This regex matches:

                    (\r\n?|\n\r?)*
                    Reference `[Rf1]`
                        Any number of newlines in any format (CR, CR-LF, LF,
                        LF-CR) at the start of the list. These are stripped away
                        when processed on the server, so it doesn't matter if it
                        is there or not.

                    -?(90|[1-8]?\d(\.\d+)?)
                    Reference `[Rf2]`
                        Matches a latitude. This can be positive or negative,
                        any number from 0 to 90. The group checks for either
                        "90", or any optional digit 1 through 8 followed by any
                        digit from 0 to 9. The latter case also allows checking
                        for decimal values with the (\.\d+)? group.

                    -?(180|(1[0-7]\d|[1-9]?\d)(\.\d+)?)
                    Reference `[Rf3]`
                        Matches a longitude. This can be positive or negative,
                        any number from 0 to 180. The group checks for either
                        "180", or a "1" (to indicate hundreds) followed by any
                        digit 0 through 7, and another digit 0 through 9 (this
                        option covers numbers 100-179), or any optional digit 1
                        through 9 followed by any digit 0 through 9 (this covers
                        numbers 0-99). The latter two (0-99 and 100-179) also
                        allow checking for decimal values with the (\.\d+)?
                        group.

                    ([Rf2],[Rf3][Rf1]){3,}
                        Matches pair consisting of one latitude, one longitude
                        and any number of newlines that may follow it, and
                        requires three or more of those pairs to be considered
                        valid.
                */
                return value === "" || value.match(/^(\r\n?|\n\r?)*(-?(90|[1-8]?\d(\.\d+)?),-?(180|(1[0-7]\d|[1-9]?\d)(\.\d+)?)(\r\n?|\n\r?)*){3,}$/);

            case "text":
                /*
                    Plain text. Always valid.
                */
                return true;
        }
    }
}

/*
    Add an input handler to all elements that validation should be performed
    against. If validation fails, a thin red border should be drawn around the
    input box to ensure that the user can visually identify the value as
    invalid.
*/
$("body").on("input", "[data-validate-as]", function() {
    if (validateInput($(this))) {
        $(this).css("border", "");
    } else {
        $(this).css("border", "1px solid red");
    }
});

/*
    Forms which require validation before submission have the
    `require-validation` class attached to them. These should have a handler
    that catches the `submit` event and prevents the form from being submitted
    if it contains invalid data.

    The handler loops over all inputs which have validation enabled and checks
    their validity. If one fails, it is given a red border to visually
    distinguish it as invalid, and the form is prevented from being submitted.
    The user is also alerted to the fact that the form contains invalid data.

    This function also handles `unsavedChanges`, however, the functionality to
    prevent navigating away from pages when there are unsaved changes must be
    implemented elsewhere, as its required functionality depends on the contents
    of the page this script is included from.
*/
var unsavedChanges = false;
$("form.require-validation").on("submit", function(e) {
    var valid = true;
    $(this).find("[data-validate-as]").each(function() {
        if (validateInput($(this))) {
            $(this).css("border", "");
        } else {
            valid = false;
            $(this).css("border", "1px solid red");
        }
    });
    if (!valid) {
        e.preventDefault();
        alert(validationFailedMessage);
    } else {
        /*
            The form is being submitted now, so to prevent the "unsaved changes"
            dialog from popping up, we declare that we no longer have unsaved
            changes.
        */
        unsavedChanges = false;
    }
});
