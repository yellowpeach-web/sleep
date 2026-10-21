jQuery(function () {
    "use strict";
    jQuery('.editinline').on(
        'click', function () {
            var lang, parentDiv, editButton, postLink;

            parentDiv = jQuery(this).closest('div');
            editButton = parentDiv.find('.edit').find('a');
            postLink = editButton.attr('href');
            lang = wpmlLangFromEditLink(postLink);
            if (!lang) {
                return;
            }
            parseJSONTerms(lang);
			}
		);
	}
);

/**
 * Reads the `lang` query argument out of a row's edit link.
 *
 * Read as one query argument rather than "everything after lang=": the edit
 * link is built by whatever filters ran, so `lang` is not guaranteed to be the
 * last argument, and taking the rest of the URL made the language code carry
 * the arguments that follow it.
 *
 * @param href String
 *
 * @return String|null
 */
function wpmlLangFromEditLink(href) {
	"use strict";
	var match = String(href || '').match(/[?&]lang=([^&#]+)/);

	return match ? match[1] : null;
}

/**
 * This is only used for hierarchical Taxonomies
 *
 * @param lang String
 */

function parseJSONTerms(lang) {
	"use strict";
	var JSONString, allTerms, termsInCorrectLang, taxonomy;
	JSONString = jQuery('#icl-terms-by-lang').html();
	if (!JSONString) {
		// The map is only printed with the `icl_translations` column; without it
		// there is nothing to prune against, and parsing null throws.
		return;
	}
	allTerms = JSON.parse(JSONString);
	if (allTerms.hasOwnProperty(lang)) {
		termsInCorrectLang = allTerms[lang];
		for (taxonomy in termsInCorrectLang) {
			if (termsInCorrectLang.hasOwnProperty(taxonomy)) {
				removeWrongLanguageTerms(termsInCorrectLang[taxonomy], taxonomy);
			}
		}
	}

}

/**
 * Escapes a value for use inside an attribute selector.
 *
 * @param value String
 *
 * @return String
 */
function wpmlEscapeSelectorValue(value) {
	"use strict";
	return String(value).replace(/(["\\])/g, '\\$1');
}

/**
 * Reads the term id out of a term-checklist list item id.
 *
 * WordPress builds those ids as `in-{taxonomy}-{term_id}-{n}` (the trailing
 * segment makes the id unique on the page); older cores emitted the shorter
 * `{taxonomy}-{term_id}`. Both shapes are read here, positionally, so the
 * uniqueness suffix does not end up inside the id.
 *
 * @param domElementID String
 * @param taxonomy String
 *
 * @return String|null
 */
function wpmlParseChecklistTermId(domElementID, taxonomy) {
	"use strict";
	var prefixes, i, prefix, rest, digits;

	prefixes = ['in-' + taxonomy + '-', taxonomy + '-'];
	for (i = 0; i < prefixes.length; i += 1) {
		prefix = prefixes[i];
		if (0 === String(domElementID).indexOf(prefix)) {
			rest = String(domElementID).substring(prefix.length);
			digits = rest.match(/^\d+/);
			return digits ? digits[0] : null;
		}
	}

	return null;
}

function removeWrongLanguageTerms(termsList, taxonomy) {
	"use strict";
	var termsUL, termsListElements, escapedTaxonomy;

	termsUL = jQuery('.' + taxonomy + '-checklist');
	if (!termsUL.length) {
		return;
	}

	// Match BOTH li-id shapes, and search descendants rather than direct children
	// so nested <ul class="children"> hierarchies are pruned too.
	escapedTaxonomy = wpmlEscapeSelectorValue(taxonomy);
	termsListElements = termsUL.find(
		'li[id^="' + escapedTaxonomy + '-"], li[id^="in-' + escapedTaxonomy + '-"]'
	);

	jQuery.each(
		termsListElements, function (index, liElement) {
			var termId = wpmlParseChecklistTermId(liElement.id, taxonomy);
			if (null === termId) {
				return;
			}
			if (termsList.indexOf(termId) === -1) {
				jQuery(liElement).hide();
			} else {
				jQuery(liElement).show();
			}
		}
	);
}
