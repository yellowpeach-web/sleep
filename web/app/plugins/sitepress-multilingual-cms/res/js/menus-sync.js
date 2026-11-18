var WPML_core = WPML_core || {};

(function () {
    "use strict";

    jQuery(function () {

        jQuery("#icl_msync_cancel").click(function () {
            location.href = WPML_core.sanitize(location.href).replace(/#(.)$/, '');
        });

        var icl_msync_confirm = jQuery('#icl_msync_confirm');
        var check_all = icl_msync_confirm.find('thead :checkbox');

        //Remove already assigned events: that's what makes that this slow!
        check_all.off('click');
		check_all.off('change');

		check_all.on('change', function () {
			var on = jQuery(this).prop('checked');
			var checkboxes = icl_msync_confirm.find('tbody :checkbox');

			if (on) {
				checkboxes.each( function( i, el ) {
					jQuery( el ).prop( 'checked', 'checked' );
				});
				jQuery( '#icl_msync_submit' ).prop( 'disabled', false );
			} else {
				checkboxes.each( function( i, el ) {
					jQuery( el ).prop( 'checked', false );
				});
				jQuery( '#icl_msync_submit' ).prop( 'disabled', true );
			}
		});

		icl_msync_confirm.find('tbody :checkbox').on('change', function () {

			if (jQuery(this).attr('readonly') == 'readonly') {
				jQuery(this).prop('checked', !jQuery(this).prop('checked'));
			}

			var checked_items = icl_msync_confirm.find('tbody :checkbox:checked');
			var checked_count = checked_items.length;

			jQuery('#icl_msync_submit').prop('disabled', !checked_count);

			if (checked_count && checked_items.length == icl_msync_confirm.find('tbody :checkbox').length) {
				jQuery('#icl_msync_confirm').find('thead :checkbox').prop('checked', true);
			} else {
				jQuery('#icl_msync_confirm').find('thead :checkbox').prop('checked', false);
			}

			WPML_core.icl_msync_validation();

		});

		jQuery('#icl_msync_submit').on('click', function () {
			jQuery(this).prop('disabled', true);

			var total_menus = jQuery('input[name^=sync]:checked').length;

			var spinner = jQuery('<span class="spinner"></span>');
			jQuery('#icl_msync_message').before(spinner);
			spinner.css({display:       'inline-block',
										float:        'none',
										'visibility': 'visible'
									});

			WPML_core.sync_menus(total_menus);

		});

		var max_vars_warning = jQuery('#icl_msync_max_input_vars');
		if (max_vars_warning.length) {
			var menu_sync_check_box_count = jQuery('input[name^=sync]').length;
			var max_vars_extra = 10; // Allow for a few other items as well. eg. nonce, etc
			if (menu_sync_check_box_count + max_vars_extra > max_vars_warning.data('max_input_vars')) {
				var warning_text = max_vars_warning.html();
				warning_text = warning_text.replace('!NUM!', menu_sync_check_box_count + max_vars_extra);
				max_vars_warning.html(warning_text);
				max_vars_warning.show();
			}
		}
	});

	WPML_core.icl_msync_validation = function () {

		jQuery('#icl_msync_confirm').find('tbody :checkbox').each(function () {
			var mnthis = jQuery(this);

			mnthis.prop('readonly', false);

			if (jQuery(this).attr('name') == 'menu_translation[]') {
				var spl = jQuery(this).val().split('#');
				var menu_id = spl[0];

				jQuery('#icl_msync_confirm').find('tbody :checkbox').each(function () {

					if (jQuery(this).val().search('newfrom-' + menu_id + '-') == 0 && jQuery(this).prop('checked')) {
						mnthis.prop('checked', true);
						mnthis.prop('readonly', true);
					}
				});
			}
		});
	};

	/**
	 * Stop the run and say what the server refused. `WPML\Request\Payload` and
	 * the request gate both answer {code, message, params}; anything else is
	 * shown as it came, and a refusal with nothing in it falls back to the
	 * sentence shipped with the screen.
	 */
	WPML_core.msync_failed = function (refusal) {
		var message = refusal && 'object' === typeof refusal ? refusal.message : refusal;

		jQuery('.spinner').remove();
		jQuery('#icl_msync_submit').prop('disabled', false);
		jQuery('#icl_msync_message')
			.text(message ? message : menus_sync.syncFailedText)
			.fadeIn('slow');
	};

	WPML_core.sync_menus = function (total_menus) {

		var message;
		var data = 'action=icl_msync_confirm';
		data += '&_icl_nonce_menu_sync=' + jQuery('#_icl_nonce_menu_sync').val();

		var number_to_send = 50;

		var menus = jQuery('input[name^=sync]:checked:not(:disabled)');
		var icl_msync_message = jQuery('#icl_msync_message');
		if (menus.length) {

			for (var i = 0; i < Math.min(number_to_send, menus.length); i++) {

				data += '&' + jQuery(menus[i]).serialize();

				jQuery(menus[i]).prop('disabled', true);
			}

			message = jQuery('#icl_msync_submit').data('message');
			message = message.replace('%1', total_menus - menus.length);
			message = message.replace('%2', total_menus);

			icl_msync_message.text(message);

			jQuery.ajax({
										url:     ajaxurl,
										type:    "POST",
										data:    data,
										success: function (response) {
											if (response && response.success) {
												WPML_core.sync_menus(total_menus);
												return;
											}
											// wp_send_json_error() answers
											// {success:false,data:…}; a 2xx carrying one
											// is a refusal, and used to match no branch
											// at all, so the batch simply stopped with
											// the spinner running and nothing said.
											WPML_core.msync_failed(response ? response.data : null);
										},
										// A refused request (a malformed payload is
										// answered 400, a denied caller 403) is routed
										// here by jQuery and never to success:
										// (DEV0905-8).
										error:   function (xhr) {
											WPML_core.msync_failed(xhr && xhr.responseJSON ? xhr.responseJSON.data : null);
										}
									});
		} else {
			icl_msync_message.hide();
			message = jQuery('#icl_msync_submit').data('message-complete');
			icl_msync_message.text(message);
			jQuery('.spinner').remove();
			jQuery('#icl_msync_cancel').fadeOut();
			icl_msync_message.fadeIn('slow');
			jQuery.ajax({
										url:     ajaxurl,
										data:    {
											'action': 'wpml_get_links_for_menu_strings_translation',
											'_nonce': menus_sync.menusSyncNonce,
										},
										success: function (response) {
											if (response.success && response.data.items) {
												// M5 (2026-05-26): match the PHP partial's
												// `display_menu_links_to_string_translation()`
												// — one `<p>` with two sentences joined by
												// `<br>`. Sentence 1 has gettext `%1$s` /
												// `%2$s` placeholders for the link tags
												// around "Translations -> Strings"
												// (wpmldev-7288); split the translated
												// string on the placeholders and build the
												// `<a>` via jQuery so we don't have to use
												// `.html()` (avoids any HTML-injection risk
												// if a translation ever introduces stray
												// markup).
												var parts = menus_sync.text1.split(/%[12]\$s/);
												var element = jQuery('<p></p>');
												element.append(document.createTextNode(parts[0] || ''));
												var link = jQuery('<a></a>')
													.attr('href', menus_sync.stringsUrl)
													.text(parts[1] || '');
												link.appendTo(element);
												element.append(document.createTextNode(parts[2] || ''));
												element.append('<br>');
												element.append(document.createTextNode(menus_sync.text2));

												element.appendTo(jQuery('#icl_msync_confirm_form'));
											}
										},
										error:   function (jqXHR, status, error) {
											var parsed_response = jqXHR.statusText || status || error;
											alert(parsed_response);
										},
									});

		}

	};

}());
