/*
    This script contains functions to enable table sorting on the administration
    interface. Sorting is enabled for all column headers with the
    `data-sort-function` attribute set. This attribute must contain the index of
    a function in the `sortFunctions` object corresponding to the comparison
    function for sorting that column.
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
});
