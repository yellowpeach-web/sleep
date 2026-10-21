/*global jQuery*/
/*localization global: wpml_tm_strings*/

var WPML_TM = WPML_TM || {};

(function () {
    "use strict";

    jQuery(function () {

        // Translator notes - translation dashboard - start
        jQuery('.icl_tn_link').click(function () {
            jQuery('.icl_post_note:visible').slideUp();
            var anchor = jQuery(this);
            var spl = anchor.attr('id').split('_');
            var doc_id = spl[3];
            var icl_post_note_doc_id = jQuery('#icl_post_note_' + doc_id);
            if (icl_post_note_doc_id.css('display') !== 'none') {
                icl_post_note_doc_id.slideUp();
            } else {
                icl_post_note_doc_id.slideDown();
                var text_area = icl_post_note_doc_id.find('textarea');
                text_area.focus();
                text_area.data('original_value', text_area.val());
            }
            return false;
        });

        jQuery('.icl_tn_cancel').click(function () {
            var note_div = jQuery(this).closest('.icl_post_note'),
                text_area = note_div.find('textarea');

            text_area.val( text_area.data('original_value' ) );
            note_div.slideUp();
        });

        // wpmldev-7978: the `save_translator_note` ajax emitter was removed —
        // no server-side handler for that legacy `icl_ajx_action` branch has
        // existed in the product for a long time and no screen renders the
        // `.icl_tn_save` control any more. Translator notes are saved with the
        // post itself (`icl_tn_note`, see wpml-admin-post-actions.class.php).
        // Translator notes - translation dashboard - end

        // MC Setup — mirrored by res/js/translation-options.js for licenses
        // without TM (wpmldev-8391); keep both lists in sync.
        jQuery('#icl_doc_translation_method').submit(iclSaveForm);
        jQuery('#icl_page_sync_options').submit(iclSaveForm);
        jQuery('form[name="icl_custom_tax_sync_options"]').submit(iclSaveForm);
        jQuery('form[name="icl_custom_posts_sync_options"]').submit(iclSaveForm);
        jQuery('form[name="icl_cf_translation"]').submit(iclSaveForm);
        jQuery('form[name="icl_tcf_translation"]').submit(iclSaveForm);

        jQuery('#icl_tm_jobs_dup_submit').click(function () {
            return confirm(jQuery(this).next().html());
        });

        // --- Start: XLIFF form handler ---
        var icl_xliff_options_form = jQuery('#icl_xliff_options_form');
        if (icl_xliff_options_form !== undefined) {
            jQuery("#icl_xliff_options_form").off();
            jQuery(document).on('submit', '#icl_xliff_options_form', icl_xliff_set_newlines);
        }

        // --- End: XLIFF form handler ---

        // --- Start: Notifications form handler ---
        if (jQuery('#translation-notifications-form').length) {
            jQuery(document).off('submit', '#translation-notifications-form');
            jQuery(document).on('submit', '#translation-notifications-form', icl_save_notification_settings);
        }
        // --- End: Notifications form handler ---
    });

    function icl_xliff_set_newlines(e) {
        e.preventDefault();

        var form = jQuery(this);
        var submitButton = form.find(':submit');

        submitButton.prop('disabled', true);
        var ajaxLoader = jQuery(icl_ajxloaderimg).insertBefore(submitButton);
        var icl_xliff_newlines = jQuery("input[name=icl_xliff_newlines]:checked").val();
        var icl_xliff_version = jQuery("select[name=icl_xliff_version]").val();

        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            dataType: 'json',
            data:  {
                action: 'set_xliff_options',
                security: wpml_xliff_ajax_nonce,
                icl_xliff_newlines: icl_xliff_newlines,
                icl_xliff_version: icl_xliff_version
            },
            success: function (msg) {
                if (!msg.error) {
                    fadeInAjxResp('#icl_ajx_response', icl_ajx_saved);
                }
                else {
                    alert(msg.error);
                }
            },
            error: function (msg) {
                fadeInAjxResp('#icl_ajx_response', icl_ajx_error);
            },
            complete: function () {
                ajaxLoader.remove();
                submitButton.prop('disabled', false);
            }
        });

        return false;
    }

    function icl_save_notification_settings(e) {
        e.preventDefault();

        var form = jQuery(this);
        var submitButton = form.find(':submit');

        submitButton.prop('disabled', true);
        var ajaxLoader = jQuery(icl_ajxloaderimg).insertBefore(submitButton);

        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            dataType: 'json',
            data: form.serialize() + '&action=save_notification_settings',
            success: function (msg) {
                if (msg && msg.success) {
                    fadeInAjxResp('#icl_ajx_response_notifications', icl_ajx_saved);
                } else {
                    fadeInAjxResp('#icl_ajx_response_notifications', icl_ajx_error, true);
                }
            },
            error: function () {
                fadeInAjxResp('#icl_ajx_response_notifications', icl_ajx_error, true);
            },
            complete: function () {
                ajaxLoader.remove();
                submitButton.prop('disabled', false);
            }
        });

        return false;
    }

    if (typeof String.prototype.startsWith !== 'function') {
        // see below for better implementation!
        String.prototype.startsWith = function (str){
            return this.slice(0, str.length) === str;
        };
    }
    if (typeof String.prototype.endsWith !== 'function') {
        String.prototype.endsWith = function (str){
            return this.slice(-str.length) === str;
        };
    }
}());

(function($) {
    $(function () {
        $('#translation-notifications').on('change', 'input', function (e) {
            var input = $(e.target);
						var children = [];
						if ( input.data('child') ) {
							children = input.data('child').split('||');
						}
						for (var i = 0; i < children.length; i++) {
							var child = $('[name="' + children[i] + '"]');
							if ( child.length ) {
									child.prop('disabled', !input.is(":checked"));
							}
						}

        });
    });
})(jQuery);
