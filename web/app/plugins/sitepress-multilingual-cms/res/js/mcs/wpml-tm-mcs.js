jQuery(function () {

    var ajax_success_action = function (response, response_text) {
        if (response.success) {
            response_text.text(icl_ajx_saved);
        } else {
            response_text.text(icl_ajx_error);
            response_text.show();
        }
        setTimeout(function () {
            response_text.fadeOut('slow');
		}, 2500);
	};

    jQuery( '#js-translated_document-options-btn' ).click(function(){

		var document_status = jQuery( 'input[name*="icl_translated_document_status"]:checked' ).val(),
      document_status_sync = jQuery( 'input[name*="icl_translated_document_status_sync"]:checked' ).val(),
			page_url = jQuery( 'input[name*="icl_translated_document_page_url"]:checked' ).val(),
      hide_translated_terms = jQuery('input[name="tm_block_retranslating_terms"]:checked').val(),
			response_text = jQuery( '#icl_ajx_response_tdo' ),
			spinner = '<span id="js-document-options-spinner" style="float: inherit; margin: 0" class="spinner is-active"></span>';

		response_text.html( spinner );
		response_text.show();

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpml_translated_document_options',
				nonce: jQuery( '#wpml-translated-document-options-nonce' ).val(),
				document_status: document_status,
        document_status_sync: document_status_sync,
        tm_block_retranslating_terms: hide_translated_terms,
				page_url: page_url
			},
			success: function ( response ) {
				ajax_success_action( response, response_text );
			}
		});
	});

	// Lives on the M4 URLs and SEO sub-page (wpmldev-7006). The Page URL
	// radios are lifted out of the Translated Documents Options form by
	// UrlsAndSeoController and wrapped in their own form with this button.
	// POSTs only `page_url` — sending the other fields (which aren't in
	// this form's DOM) would resolve to empty and overwrite the saved
	// status / status_sync / taxonomies settings, since the AJAX handler
	// gates updates on array_key_exists.
	jQuery( '#js-page-url-options-btn' ).click( function () {
		var page_url      = jQuery( 'input[name*="icl_translated_document_page_url"]:checked' ).val(),
			response_text = jQuery( '#icl_ajx_response_page_url' ),
			spinner       = '<span style="float: inherit; margin: 0" class="spinner is-active"></span>';

		response_text.html( spinner );
		response_text.show();

		jQuery.ajax( {
			url: ajaxurl,
			type: 'POST',
			data: {
				action:   'wpml_translated_document_options',
				nonce:    jQuery( '#wpml-translated-document-options-nonce' ).val(),
				page_url: page_url
			},
			success: function ( response ) {
				ajax_success_action( response, response_text );
				// The same event iclSaveForm() fires for the hub's other option
				// forms, so the Page URL confirmation (wpmldev-7041) can wait
				// for the save before it drives its task.
				if ( response.success ) {
					if ( typeof wpmlCustomEvent === 'function' ) {
						wpmlCustomEvent( 'icl-save-form-wpml_page_url_options' );
					} else if ( typeof CustomEvent === 'function' ) {
						document.dispatchEvent( new CustomEvent( 'icl-save-form-wpml_page_url_options' ) );
					}
				}
			}
		} );
	} );

	jQuery( '#translation-pickup-mode' ).click(function(){
		var pickup_mode = jQuery( 'input[name*="icl_translation_pickup_method"]:checked' ).val(),
			response_text = jQuery( '#icl_ajx_response_tpm' ),
			spinner = '<span id="js-document-options-spinner" style="float: inherit; margin: 0" class="spinner is-active"></span>';

		response_text.html( spinner );
		response_text.show();

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wpml_save_translation_pickup_mode',
				nonce: jQuery( '#wpml_save_translation_pickup_mode' ).val(),
				pickup_mode: pickup_mode
			},
			success: function ( response ) {
				ajax_success_action( response, response_text );
			}
		});
	});

  var radioButtons = document.querySelectorAll('input[name="t_method"]');

  function updateTableClass() {
    var table = document.querySelector('.t_method__table');
    var useForOldTranslationsCheckbox = document.querySelector('.old-translations .wpml-checkbox');
    // wpmldev-7165: this styles the Translation-Editor method table, which only
    // exists on the Translation Editor settings section. The script is also
    // enqueued on other TM-settings sections for the Page-URL handler, where the
    // table is absent - bail out instead of dereferencing null.
    if (!table || !useForOldTranslationsCheckbox) {
      return;
    }
    useForOldTranslationsCheckbox.setAttribute('style', 'display: none;');

    radioButtons.forEach(function(radio) {
      var radioLabel = radio.nextElementSibling;
      if (radio.checked) {
        radioLabel.textContent = icl_enabled_text;
        if (radio.value === "ATE") {
          table.classList.add('t_method__ate-editor');
          table.classList.remove('t_method__classic-editor');
          useForOldTranslationsCheckbox.setAttribute('style', 'display: inline-block;');
        } else if (radio.value === "1") {
          table.classList.add('t_method__classic-editor');
          table.classList.remove('t_method__ate-editor');
        }
      } else {
        radioLabel.textContent = icl_choose_text;
      }
    });
  }

  updateTableClass();

  radioButtons.forEach(function(radio) {
    radio.addEventListener('change', updateTableClass);
  });

  // Handle ATE for old translations' checkbox.
  const useAteForOldTranslationModal = jQuery('.wpml-js-warning-modal-ate-for-old-translations');
  const useAteForOldTranslationCheckbox = jQuery('input[name="wpml-old-jobs-editor"]');
  const translationMethodFormSubmit = jQuery('#icl_doc_translation_method input[type="submit"]');

  useAteForOldTranslationCheckbox.prop('disabled', false);
  useAteForOldTranslationCheckbox.change(function () {
    if (jQuery(this).prop('checked')) {
      useAteForOldTranslationModal.data('submittedData', false);
      useAteForOldTranslationModal.dialog('open');
    } else {
      translationMethodFormSubmit.click();
    }
  });

  useAteForOldTranslationModal.dialog({
    autoOpen: false,
    modal: true,
    dialogClass: 'wpml-dialog otgs-ui-dialog otgs-ui-dialog-no-border wpml-use-ate-confirmation-modal',
    resizable: false,
    close: function (e) {
      if (!useAteForOldTranslationModal.data('submittedData')) {
        useAteForOldTranslationCheckbox.prop('checked', false);
      }
    },
    buttons:
      [
        {
          text: useAteForOldTranslationModal.data('close-btn-txt'),
          class: 'wpml-button base-btn button-secondary wpml-button--outlined',
          click: function () {
            jQuery(this).dialog("close");
          }
        },
        {
          text: useAteForOldTranslationModal.data('ok-btn-txt'),
          class: 'button-primary wpml-button base-btn',
          click: function () {
            translationMethodFormSubmit.click();
            useAteForOldTranslationModal.data('submittedData', true);
            jQuery(this).dialog("close");
          }
        },
      ],
    focus: function () {
      jQuery('.wpml-use-ate-confirmation-modal .ui-dialog-buttonpane .button-secondary').blur();
    }
  })
});
