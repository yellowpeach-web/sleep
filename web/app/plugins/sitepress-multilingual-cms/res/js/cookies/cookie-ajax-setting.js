jQuery(function () {

    var cookie_setting = wpml_cookie_setting,
        ajax_success_action = function (response, response_text) {

            if (response.success) {
                response_text.text(icl_ajx_saved);
            } else {
                response_text.text(icl_ajx_error);
            }

		response_text.show();

		setTimeout(function () {
			response_text.fadeOut('slow');
		}, 2500);
	};

	jQuery( '#' + cookie_setting.button_id ).click(function(){

		var store_frontend_cookie = jQuery( 'input[name*="' + cookie_setting.field_name + '"]:checked' ).val(),
			response_text         = jQuery( '#' + cookie_setting.ajax_response_id ),
			spinner               = jQuery( '#js-store-frontend-cookie-spinner' );

		spinner.addClass( 'is-active' );

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: cookie_setting.ajax_action,
				nonce: jQuery( '#' + cookie_setting.nonce ).val(),
				store_frontend_cookie: store_frontend_cookie
			},
			success: function ( response ) {
				spinner.removeClass( 'is-active' );
				ajax_success_action( response, response_text );
			}
		});
	});

});