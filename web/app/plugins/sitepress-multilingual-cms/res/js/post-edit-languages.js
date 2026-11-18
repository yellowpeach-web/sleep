/*globals jQuery, post_edit_languages_data */

function build_language_links(data, $, container) {
	"use strict";

	const getNewQueryString = function(sourceUrl, newQueryArgs) {
		const url = new URL(sourceUrl),
			search = url.searchParams;

		for (let key in newQueryArgs) {
			if (Object.prototype.hasOwnProperty.call(newQueryArgs, key)) {
				search.set(key, newQueryArgs[key]);
			}
		}

		return search.toString();
	};

	var urlData;

	var appendLanguageItem = function (item, index, list) {
		var is_current = item.current || false;
		var language_code = item.code;
		var language_count = item.count;
		var language_name = item.name;
		var statuses = item.statuses;
		var type = item.type;

		var language_item = $('<li></li>');
		language_item.addClass('language_' + language_code);
		if (index > 0) {
			language_item.append('&nbsp;|&nbsp;');
		}

		var language_summary = $('<span></span>');
		language_summary.addClass('count');
		language_summary.addClass(language_code);
		language_summary.text(' (' + ( language_count < 0 ? "0" : language_count ) + ')');

		var current;
		if (is_current) {
			current = $('<strong></strong>');
		} else if (language_count >= 0) {
			current = $('<a></a>');
			urlData = {
				post_type: type,
				lang:      language_code
			};

			if (statuses && statuses.length) {
				urlData.post_status = statuses.join(',');
			}

			current.attr('href', '?' + getNewQueryString(location.href, urlData));
		} else {
			current = $('<span></span>');
		}

		current.append(language_name);
		current.appendTo(language_item);
		current.append(language_summary);

		language_item.appendTo(list);
	};

	if (data.hasOwnProperty('language_links')) {
		var languages_container = $('<ul></ul>');
		languages_container.prependTo(container);

		/** @namespace data.language_links */
		/** @namespace data.statuses */
		for (var i = 0; i < data.language_links.length; i++) {
			appendLanguageItem(data.language_links[i], i, languages_container);
		}

		$(document).trigger('wpml_language_links_added', [languages_container]);
	}

	/**
	 * The "Removed" group - DELETION-FLOWS §3.2 option 2, mechanism (c): the
	 * languages that are off the site and still hold content, so the items the
	 * client chose to KEEP stay reachable from the list itself. The key is absent
	 * on every site that removed no language, so this whole block never runs there.
	 *
	 * @namespace data.removed_languages
	 */
	if (data.hasOwnProperty('removed_languages') && data.removed_languages.length) {
		var removed_container = $('<ul></ul>');
		removed_container.addClass('icl_subsubsub_removed');
		// The list keeps its native semantics - a screen reader still announces how
		// many languages are in it - and gains a name. A role="group" here would
		// name the same element while taking the list role away from it.
		removed_container.attr('aria-label', data.removed_languages_title || data.removed_languages_label);
		removed_container.appendTo(container);

		var removed_label = $('<li></li>');
		removed_label.addClass('wpml-removed-languages-label');
		removed_label.text(data.removed_languages_label + ':');
		removed_label.append('&nbsp;');
		removed_label.appendTo(removed_container);

		for (var j = 0; j < data.removed_languages.length; j++) {
			appendLanguageItem(data.removed_languages[j], j, removed_container);
		}

		$(document).trigger('wpml_removed_language_links_added', [removed_container]);
	}
}

jQuery(function ($) {
    "use strict";

    var data = post_edit_languages_data;
    var subsubsub = $('.subsubsub');
    var container = subsubsub.next('.icl_subsubsub');

    if (container.length === 0) {
        container = $('<div></div>');
        container.addClass('icl_subsubsub');

		subsubsub.after(container);
	}

	build_language_links(data, $, container);
});