var WPML_Media_Batch_Url_Translation = WPML_Media_Batch_Url_Translation || {

    hasDialog: false,
    dialog: jQuery('#batch-media-translation-wrap'),
    form: jQuery('#batch-media-translation-form'),
    globalScope: 0,
    attachmentId: 0,

    createDialog: function (attachmentId, postsList) {
        this.hasDialog = true;
        this.attachmentId = attachmentId;

        this.dialog.find('.js-was-replaced').text(this.dialog.data('was-replaced'));
        this.dialog.find('.js-other-posts').text(this.dialog.data('other-posts'));
        this.dialog.find('.js-without-usage').text(this.dialog.data('without-usage'));

        if (postsList.length > 0) {
            this.dialog.find('.usage').show();
            var ul = this.dialog.find('.usage ul');
            var escapeHTML = function (value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            };
            for (var i in postsList) {
                var li = postsList[i].url ?
                    '<a href="' + escapeHTML(postsList[i].url) + '">' + escapeHTML(postsList[i].title) + '</a>' :
                    escapeHTML(postsList[i].title);
                ul.append('<li>' + li + '</li>');
            }
        } else {
            this.dialog.find('.no-usage').show();
        }
    },

    // The offer is rendered once, at the top of the page above the list. The
    // row the user acted on can be a full screen or more below it, so bring
    // the offer into view when it appears; otherwise the question sits outside
    // the viewport and is missed (wpmldev-8413).
    showDialog: function () {
        this.dialog.show();
        this.dialog.scrollTop(0);
        this.dialog.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
    },

    closeDialog: function (event) {
        var self = WPML_Media_Batch_Url_Translation;
        if (typeof event !== 'undefined') {
            event.preventDefault();
        }
        self.dialog.hide();
        self.reset();
    },

    setInProgress: function (on) {
        this.form.find('input.button-primary:submit').prop('disabled', on);
        this.form.find('input[name=global-scan-scope]').prop('disabled', on);
    },

    runScan: function () {
        var self = WPML_Media_Batch_Url_Translation;
        var form = jQuery(this);

        self.globalScope = form.find('input[name=global-scan-scope]:checked').val();

        var nextAction = [];
        nextAction['wpml_media_translate_media_url_in_posts'] = 'wpml_media_translate_media_url_in_custom_fields';

        if ( wpml_media_batch_translation.is_st_enabled ) {
			nextAction['wpml_media_translate_media_url_in_custom_fields'] = 'wpml_media_translate_media_url_in_strings';
		}

        self.setInProgress(true);
        jQuery.ajax({
            url: ajaxurl,
            type: 'post',
            dataType: 'json',
            data: form.serialize(),
            error: self.onRequestFailed,
            success: function (response) {
                // A prepare that did not run leaves the wpml_media_processed
                // markers in place, so the runners would skip the very posts the
                // scan is for. Stop and say so rather than scan on dirty state
                // (MED-10a).
                if (response && response.success === false) {
                    self.setInProgress(false);
                    self.setStatus(
                        typeof response.data === 'string'
                            ? response.data
                            : wpml_media_batch_translation.prepare_failed
                    );
                    return;
                }

                self.setStatus(response.message);
                self.scan(null, 'wpml_media_translate_media_url_in_posts', nextAction);
            }
        })
        return false;
    },

    reset: function () {
        this.dialog.find('.usage').hide();
        this.dialog.find('.no-usage').hide();
        this.dialog.find('.usage ul').html('');
        this.dialog.find('.js-was-replaced, .js-other-posts, .js-without-usage').text('');
        this.attachmentId = 0;
        this.setStatus('');
        this.dialog.hide();
        this.hasDialog = false;
    },

    setStatus: function (text) {
        this.dialog.find('.status').html(text);
    },

    // A scan that ends leaves its result and its control on the screen: the
    // block used to hide its form and close itself three seconds later, so the
    // owner could neither read the outcome nor run it again (wpmldev-8431).
    setComplete: function (text) {
        this.setStatus(text);
        this.setInProgress(false);
        this.dialog.removeClass('notice-info').addClass('notice-success');
    },

    // A round that fails (a 403 from the request gate, a 500, a dropped
    // connection) used to reach no handler at all: the status froze and the
    // button stayed disabled for good (wpmldev-8431). Say so and hand the
    // control back.
    onRequestFailed: function () {
        var self = WPML_Media_Batch_Url_Translation;
        self.setInProgress(false);
        self.dialog.removeClass('notice-success').addClass('notice-info');
        self.setStatus(wpml_media_batch_translation.request_failed);
    },

    scan: function (offset, action, nextAction) {
        var self = WPML_Media_Batch_Url_Translation;
        if (typeof offset === 'undefined') {
            offset = 0;
        }
        jQuery.ajax(
            {
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: wpml_media_batch_translation.nonce,
                    global: self.globalScope,
                    attachment_id: self.attachmentId,
                    offset: offset
                },
                dataType: 'json',
                error: self.onRequestFailed,
                success: function (response) {
                    if (!response || response.success === false || !response.data) {
                        self.onRequestFailed();
                        return;
                    }
                    self.dialog.removeClass('notice-success').addClass('notice-info');
                    self.setStatus(response.data.message);
                    if (response.data.continue > 0) {
                        self.scan(response.data.offset, action, nextAction);
                    } else {
                        if (nextAction[action]) {
                            self.scan(null, nextAction[action], nextAction);
                        } else {
                            self.setComplete(wpml_media_batch_translation.complete);
                        }
                    }
                }
            }
        );
    }

};


jQuery(function ($) {
    "use strict";

    WPML_Media_Batch_Url_Translation.form.on('submit', WPML_Media_Batch_Url_Translation.runScan);
    WPML_Media_Batch_Url_Translation.dialog.on('click', '.js-close', WPML_Media_Batch_Url_Translation.closeDialog);

});