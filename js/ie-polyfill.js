/*
    This script file is a polyfill for Internet Explorer, which does not support
    ECMAScript 6.
*/
if (!String.prototype.startsWith) {
	String.prototype.startsWith = function(search, pos) {
		return this.substr(!pos || pos < 0 ? 0 : +pos, search.length) === search;
	};
}
