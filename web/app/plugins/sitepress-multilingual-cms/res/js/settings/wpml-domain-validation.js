/**
 * Used on the language settings page, validates the text content of one row
 * of the language domains settings form and potentially sanitizes the contents
 * of the row's text field.
 * Un-checks the text fields validation checkbox in case the field contains an
 * empty string after sanitization.
 *
 * @param domainInput Object
 * @param domainCheckBox Object
 * @returns {{run: run}}
 * @constructor
 */
var WpmlDomainValidation = function (domainInput, domainCheckBox) {

    return {
        run: function () {
            // The host is everything after an optional scheme and up to the first
            // path separator. It is expressed as "not a delimiter" rather than as
            // a list of allowed characters, because \w is ASCII-only without the
            // /u flag, so an internationalised domain was truncated at its first
            // non-ASCII character before it ever reached the server (wpmldev-1432).
            // Punycode labels (xn--...) were always fine and stay fine.
            var textInput = domainInput.val().match(/^(?:.+\/\/)?([^\s\/\\?#&<>"']*)/)[1];
            if (!textInput) {
                domainCheckBox.prop('checked', false)
            }
            domainInput.val(textInput ? textInput : '');
        }
    }
};