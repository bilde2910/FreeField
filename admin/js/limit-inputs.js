/*
    This script contains functions that disable input fields client-side to
    prevent them from being submitted to the server. The script tracks changes
    to input fields on the page and disables all inputs that have not been
    changed. This is done to work around PHP's `max_input_vars` default
    limitation of 1000.
*/

/*
    This function is called with a jQuery <form> element to flag all inputs that
    are children of that element to work with the change detection described
    above.
*/
function disableUnchangedFor(element) {
    /*
        Bind an event handler that flags an input as changed if the user changes
        it. <input>, <select> and <textarea> tags are bound.
    */
    element.on("input", "input, select, textarea", function() {
        $(this).attr("data-changed", "true");
    });

    /*
        Bind an event handler to form submission. This checks if the
        `data-changed` is set for any inputs. If it is not set for a particular
        input, the control is disabled to prevent it from getting submitted to
        the server.
    */
    element.on("submit", function(e) {
        var unchangedInputs = $('input:not([data-changed]):not([name^="_"]), '
                              + 'select:not([data-changed]), '
                              + 'textarea:not([data-changed])');

        unchangedInputs.prop("disabled", true);
    });
}

/*
    Enable change detection with all forms declaring the class `limit-inputs`.
*/
disableUnchangedFor($("form.limit-inputs"));
