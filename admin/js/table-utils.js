/*
    This script contains table utilities, such as functions to enable table
    sorting and pagination.

    Sorting is enabled for all column headers with the `data-sort-function`
    attribute set. This attribute must contain the index of a function in the
    `sortFunctions` object corresponding to the comparison function for sorting
    that column.

    Pagination is enabled for all tables with the `paginate` class. Such tables
    must be followed by a `.paginate-outer` block element containing a
    `.paginate-inner` block element, which will contain navigation controls for
    the table.

    Searching may be enabled for tables that use pagination by placing a text input
    box with the `data-search-for` attribute set to the ID of the table that should
    be searchable on the page. Pagination is a requirement to use this feature.
*/

/*
    Returns the cell of index `col` from jQuery <tr> element `row`.
*/
function getCell(row, col) {
    return $(row).children("td").eq(col);
}

/*
    Returns a comparison function used to sort a given column of a table. The
    function is declared for each column via the `data-sort-function` attribute
    of the <th> element corresponding to each column in the document.
*/
function getSortFunction(func, colIndex) {
    var sortFunctions = {
        /*
            Alphanumerically sorts the table by text content.
        */
        "alphanumeric": function(a, b) {
            var cA = getCell(a, colIndex), cB = getCell(b, colIndex);
            return cA.text().localeCompare(
                cB.text()
            );
        },
        /*
            Parses the text content of the cells to numerical values and
            compares the values.
        */
        "numeric": function(a, b) {
            var cA = getCell(a, colIndex), cB = getCell(b, colIndex);
            return parseFloat(cA.text()) - parseFloat(cB.text());
        },
        /*
            Uses the text value of the first input box in the cell to sort the
            table.
        */
        "input-value": function(a, b) {
            var cA = getCell(a, colIndex), cB = getCell(b, colIndex);
            return cA.find("input").val().localeCompare(
                cB.find("input").val()
            );
        },
        /*
            Uses the value of the first select box in the cell to sort the
            table.
        */
        "select-value": function(a, b) {
            var cA = getCell(a, colIndex), cB = getCell(b, colIndex);
            var valA = cA.find("select").val(), valB = cB.find("select").val();
            return $.isNumeric(valA) && $.isNumeric(valB)
                ? valA - valB
                : valA.localeCompare(valB);
        },
        /*
            Used to sort the "Current research" column of the POI table. Sorts
            by ID of the objective, followed by ID of the reward if the
            objective IDs are equivalent.
        */
        "poi-dual-marker": function(a, b) {
            var attr = "data-marker-id";
            var cA = getCell(a, colIndex), cB = getCell(b, colIndex);
            var m1A = cA.children("img").first(), m1B = cB.children("img").first();
            var m2A = cA.children("img").last(), m2B = cB.children("img").last();
            var marker1Cmp = m1A.attr(attr).localeCompare(m1B.attr(attr));
            var marker2Cmp = m2A.attr(attr).localeCompare(m2B.attr(attr));
            return marker1Cmp != 0 ? marker1Cmp : marker2Cmp;
        }
    };

    return sortFunctions[func];
}

/*
    Bind sorting functionality to all table header cells with a defined
    `data-sort-function`.
*/
$("th[data-sort-function]").click(function() {
    /*
        Get the sorting function for the current column index.
    */
    var func = getSortFunction(
        $(this).attr("data-sort-function"),
        $(this).index()
    );
    /*
        Find and sort the rows of the table accordingly.
    */
    var table = $(this).closest("table");
    var rows = table.find("tr:gt(0)").toArray().sort(func);
    /*
        Check if the rows should be reversed, and flip the "reverse" flag to
        reverse the sort direction for the next click on this column header.
    */
    var reverse = $(this).prop("data-sort-reverse");
    if (reverse) rows = rows.reverse();
    $(this).prop("data-sort-reverse", !reverse);
    /*
        Replace the rows in the table with the sorted row array.
    */
    for (var i = 0; i < rows.length; i++) table.append(rows[i]);
    /*
        If the table uses pagination, reload it as the order of the rows have
        changed.
    */
    if (table.is(".paginate")) reloadTablePagination(table);
});

/*
    Initializes pagination for tables which have requested it with the
    `paginate` class. Each table must have a block element with the
    `paginate-outer` class at some point following the table on the same level
    in the DOM. This element must contain a block element down in the hierarchy
    with the `paginate-inner` class, to hold navigation controls for the
    paginated table.
*/
$(document).ready(function() {
    /*
        Initialize navigation controls for paginated tables.
    */
    $(".paginate-inner").each(function(idx, e) {
        /*
            Paragraph box that contains all of the pagination controls.
        */
        var box = $('<p class="paginate-button-box">');
        /*
            An ellipsis sign, used to separate the first/prev/next/last buttons
            from the numbered buttons indicating page numbers.
        */
        var ellipsis = $('<span class="fas fa-ellipsis-h paginate-ellipsis">');
        /*
            Navigation buttons.
        */
        box.append($('<span class="fas fa-angle-double-left paginate-first">'));
        box.append($('<span class="fas fa-angle-left paginate-prev">'));
        box.append(ellipsis);
        box.append($('<span class="paginate-prev-2">'));
        box.append($('<span class="paginate-prev-1">'));
        box.append($('<span class="paginate-cur">'));
        box.append($('<span class="paginate-next-1">'));
        box.append($('<span class="paginate-next-2">'));
        box.append(ellipsis.clone());
        box.append($('<span class="fas fa-angle-right paginate-next">'));
        box.append($('<span class="fas fa-angle-double-right paginate-last">'));
        /*
            Add event handler for the navigation buttons. Do not add this event
            handler to ellipsis icons or to the current page button (as it would
            go to the same page).
        */
        box.find("span:not(.paginate-ellipsis):not(.paginate-cur)").click(function() {
            var table = $(this).closest(".paginate-outer").prev("table.paginate");
            var to = parseInt($(this).attr("data-paginate-to"));
            stepPage(table, box, to);
        });
        /*
            Add an event handler to the current page button which instead
            prompts the user for a particular page number to navigate to.
        */
        box.find("span.paginate-cur").click(function() {
            var table = $(this).closest(".paginate-outer").prev("table.paginate");
            var to = parseInt(prompt(resolveI18N("ui.paginate.go_to")));
            if (!isNaN(to)) stepPage(table, box, to - 1);
        });
        /*
            Add the paragraph box to the `.paginate-inner` container.
        */
        $(e).append(box);
    });
    /*
        Populate the navigation buttons and perform row calculations on all
        paginated tables.
    */
    $("table.paginate").each(function(idx, e) {
        reloadTablePagination($(e));
    });
});

/*
    This function sets up or reloads pagination for any jQuery <table> element.
*/
function reloadTablePagination(table) {
    /*
        Calculate the 0-indexed page number that each row in the table should
        appear on, and assign this to a data attribute for later use. Make sure not
        to include rows hidden by search.
    */
    var rPage = 0;
    table.find('tbody tr:not([data-search-match="0"])').each(function(idx, e) {
        rPage = Math.floor(idx / 5);
        $(e).attr("data-paginate-page", rPage);
    });
    /*
        Find the current and last pages of the table. The last page can be found
        by finding the page index of the last row in the table. If the current
        page has not been specified, set it to 0 (first page).
    */
    var curPage = 0;
    var lastPage = rPage;
    if (table.is("[data-paginate-current]")) {
        curPage = parseInt(table.attr("data-paginate-current"));
        /*
            If, after a table reload, the current page exceeds the number of
            pages in the table, set the current page to the last existing page.
        */
        if (curPage > lastPage) {
            curPage = lastPage;
        }
    }
    /*
        Set these values in data attributes for later processing in
        `stepPage()`.
    */
    table.attr("data-paginate-current", curPage);
    table.attr("data-paginate-last", lastPage);
    /*
        Step to the `curPage`th page of the table. This effectively initializes/
        updates the navigation buttons for the table.
    */
    stepPage(table, table.next(".paginate-outer").find(".paginate-inner p"), curPage);
}

/*
    This function sets the given table to display the page in `to`. `box` refers
    to the <p> element containing the navigation buttons for this table.
*/
function stepPage(table, box, to) {
    /*
        Find the current and last pages of the table.
    */
    var page = parseInt(table.attr("data-paginate-current"));
    var last = parseInt(table.attr("data-paginate-last"));
    /*
        Check if the requested page is within these bounds. If not, fix them to
        the bounds.
    */
    if (to < 0) to = 0;
    if (to > last) to = last;
    /*
        Hide all rows, then display the ones on the current page.
    */
    $("table.paginate tbody tr").hide();
    $("table.paginate tbody tr[data-paginate-page=" + to + "]").show();
    /*
        Update navigation buttons attribute values and display labels to reflect
        the current page and its neighbors.
    */
    box.find(".paginate-first").attr("data-paginate-to", 0);
    box.find(".paginate-prev").attr("data-paginate-to", to - 1);
    box.find(".paginate-prev-2").attr("data-paginate-to", to - 2).text(to - 2 < 0 ? "\u200C" : to - 1);
    box.find(".paginate-prev-1").attr("data-paginate-to", to - 1).text(to - 1 < 0 ? "\u200C" : to);
    box.find(".paginate-cur").attr("data-paginate-to", to).text(to + 1);
    box.find(".paginate-next-1").attr("data-paginate-to", to + 1).text(to + 1 > last ? "\u200C" : to + 2);
    box.find(".paginate-next-2").attr("data-paginate-to", to + 2).text(to + 2 > last ? "\u200C" : to + 3);
    box.find(".paginate-next").attr("data-paginate-to", to + 1);
    box.find(".paginate-last").attr("data-paginate-to", last);
    /*
        Set the current page of the table to the requested table.
    */
    table.attr("data-paginate-current", to);
}

/*
    Returns a search function used to search for a string in a table cell. The
    function is declared for each column via the `data-search-function` attribute
    of the <th> element corresponding to each column in the table.
*/
function getSearchFunction(func) {
    var searchFunctions = {
        /*
            Searches against the plain text contents of the cell.
        */
        "plain-text": function(cell, query) {
            return cell.text().toLowerCase().includes(query);
        },
        /*
            Searches against the value of text input boxes in the cell.
        */
        "input-value": function(cell, query) {
            var found = false;
            cell.find('input[type="text"]').each(function(idx, e) {
                if ($(e).val().toLowerCase().includes(query)) found = true;
            });
            return found;
        },
        /*
            Searches against objective and reward in the "Current research" column
            of the cell.
        */
        "poi-dual-marker": function(cell, query) {
            var found = false;
            cell.find('img').each(function(idx, e) {
                if ($(e).attr("alt").toLowerCase().includes(query)) found = true;
            });
            return found;
        }
    };

    return searchFunctions[func];
}

/*
    Set up search boxes. Search boxes are input boxes with a `data-search-for`
    attribute containing the ID of the table that the search box should search in.
    Searchable tables must also use pagination.
*/
$('input[type="text"][data-search-for]').on("input", function() {
    var query = $(this).val().toLowerCase();
    var table = $("#" + $(this).attr("data-search-for"));
    /*
        Each column in the table that is searchable has an attribute
        `data-search-function` corresponding to one of the functions in
        `getSearchFunction()` above. `searchFuncs` will contain a list of search
        functions for each column, where the function of any particular column can
        be looked up by the index of each table cell in its row.
    */
    var searchFuncs = [];
    table.find("thead th").each(function(idx, e) {
        if ($(e).is("[data-search-function]")) {
            searchFuncs.push(getSearchFunction($(e).attr("data-search-function")));
        } else {
            searchFuncs.push(null);
        }
    });
    /*
        Remove the search match highlighting from all cells if there is no query in
        the search box.
    */
    var tableBody = table.find("tbody");
    if (query == "") {
        tableBody.find("td").removeClass("search-match");
    }
    /*
        Search the query against every cell in the table.
    */
    tableBody.find("tr").each(function(idx, e) {
        var matches = false;
        $(e).find("td").each(function(cIdx, cE) {
            /*
                Check if the cell matches the search query using the specified
                search function for this column. If there is a match, add the
                `search-match` class for highlighting.
            */
            if (searchFuncs[cIdx] != null && searchFuncs[cIdx]($(cE), query)) {
                matches = true;
                if (query != "") $(cE).addClass("search-match");
            } else {
                if (query != "") $(cE).removeClass("search-match");
            }
        });
        /*
            If there was a match in this row, add the `data-search-match` attribute
            and set it to 1, otherwise, set it to 0. This is used to hide the rows
            via CSS, and to specify that the rows should be ignored when setting up
            table pagination.
        */
        if (matches) {
            $(e).attr("data-search-match", 1);
        } else {
            $(e).attr("data-search-match", 0);
        }
    });
    /*
        Since we probably just removed or added a whole bunch of rows, set up table
        pagination again with proper page counts.
    */
    reloadTablePagination(table);
});
