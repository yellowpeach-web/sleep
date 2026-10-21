/* globals wpml_media_popup */

jQuery(function ($) {
    var dialogBox = $('#wpml-media-dialog');
    var dialogForm = $('#wpml-media-dialog-form');
    var mediaFileUploadForm = $('#wpml-media-file-upload-form');

    dialogBox.dialog({
                         resizable: false,
                         draggable: false,
                         height   : 'auto',
                         width    : 800,
                         autoOpen : false,
		modal:         true,
		dialogClass:   'otgs-ui-dialog wpml-media-dialog wpml-dialog-translate',
		title:         wpml_media_popup.title,
		create:        function () {
			$('#jquery-ui-style-css').prop('disabled', true);
			dialogBox.removeAttr('hidden');
		},
		open:          function (event, ui) {
			var thisDialog = $(this);
			var dialogRoot = thisDialog.closest('.ui-dialog');
			// jQuery UI marks the dialog role=dialog; state the modality too, so
			// the Escape dismissal below is the pattern the element declares.
			dialogRoot.attr('aria-modal', 'true');
			var hasUsagesInPostsByCopy = thisDialog.find('input[name=has_usages_in_posts_by_copy]').val().toString() === '1';
			var hasUsagesInPostsByReference = thisDialog.find('input[name=has_usages_in_posts_by_reference]').val().toString() === '1';
			var copiedMediaLabel = thisDialog.find('input[name=copied_media_label]').val();
			var referencedMediaLabel = thisDialog.find('input[name=referenced_media_label]').val();
			var cannotEditAsCopyNotice = jQuery('#wpml-media-is-copy-notice #wpml-media-is-copy-cannot-edit-notice');

			cannotEditAsCopyNotice.css('display', 'none');
			dialogRoot.find('.wpml-form-row-translation-field button').removeClass('disabled');
			dialogRoot.find('input[type=text], textarea').removeAttr('disabled');
			dialogRoot.find('.wpml-form-row-translation-field button').attr('title', dialogRoot.find('.wpml-form-row-translation-field button').attr('data-defaulttitle'));

			dialogRoot.find('.wpml-media-type-label').remove();
			dialogRoot.find('#wpml-media-is-copy-notice').css('display', 'none');
			dialogRoot.find('#wpml-media-is-reference-notice').css('display', 'none');
			dialogRoot.find('#wpml-media-is-copyandreference-notice').css('display', 'none');
			var label;

			if (hasUsagesInPostsByCopy && !hasUsagesInPostsByReference) {
				label = jQuery('<div class="wpml-media-type-label">' + copiedMediaLabel + '</div>');
				dialogRoot.find('.ui-dialog-title').append(label);
				dialogRoot.find('#wpml-media-is-copy-notice').css('display', 'block');
			}
			if (hasUsagesInPostsByReference && !hasUsagesInPostsByCopy) {
				label = jQuery('<div class="wpml-media-type-label wpml-media-type-label--light">' + referencedMediaLabel + '</div>');
				dialogRoot.find('.ui-dialog-title').append(label);
				dialogRoot.find('#wpml-media-is-reference-notice').css('display', 'block');
			}
			if (hasUsagesInPostsByCopy && hasUsagesInPostsByReference) {
				label = jQuery('<div class="wpml-media-type-label">' + copiedMediaLabel + '</div>');
				dialogRoot.find('.ui-dialog-title').append(label);
				label = jQuery('<div class="wpml-media-type-label wpml-media-type-label--light">' + referencedMediaLabel + '</div>');
				dialogRoot.find('.ui-dialog-title').append(label);
				dialogRoot.find('#wpml-media-is-copyandreference-notice').css('display', 'block');
			}

			repositionDialog();
			if (WPML_Media_Batch_Url_Translation.hasDialog) {
				WPML_Media_Batch_Url_Translation.reset();
			}
			var translationForm = thisDialog.find('form');
			var nonceValue = translationForm.find('input[name="wpnonce"]').val();
			var translatedLanguage = translationForm.find('input[name="translated-language"]').val();

			$.ajax({
				url:      ajaxurl,
				type:     'GET',
				dataType: 'json',
				data: {
					attachmentId: thisDialog.find('input[name="original-attachment-id"]').val(),
					translatedLanguage: translatedLanguage,
					wpnonce: nonceValue,
					action: 'wpml_media_get_attachment_translation_data',
				},
				success:  function (response) {
					if (response.data.wasAttachmentTranslatedAutomaticallyInAnyPostAsCopy && !hasUsagesInPostsByReference) {
						cannotEditAsCopyNotice.css('display', 'block');
						dialogRoot.find('.wpml-form-row-translation-field button').addClass('disabled');
						dialogRoot.find('input[type=text], textarea').attr('disabled', 'disabled');
						dialogRoot.find('.wpml-form-row-translation-field button').attr('title', dialogRoot.find('.wpml-form-row-translation-field button').attr('data-disabledtitle'));
					}
				}
			});
		},
		close: function () {
			$('#jquery-ui-style-css').prop('disabled', false);
			discardStagedMediaFile();
			if (WPML_Media_Batch_Url_Translation.hasDialog) {
				WPML_Media_Batch_Url_Translation.showDialog();
			}
		},
		buttons:       [
			{
				text:  wpml_media_popup.cancel,
				class: 'alignleft',
				click: function () {
					$(this).find('.spinner').remove();
					$(this).dialog('close');
				}
			},
			{
				text:     wpml_media_popup.save,
				class:    'button-primary alignright',
				disabled: true,
				click:    function () {
					var thisDialog = $(this);
					disableFormSave();
					var ajaxLoader      = $('<span class="spinner"></span>');
					var translationForm = thisDialog.find('form');
					ajaxLoader.insertBefore('.wpml-media-dialog .button-primary').css({
						visibility: 'visible',
						float:      'none'
					});
					$.ajax({
						url:      ajaxurl,
						type:     'POST',
						dataType: 'json',
						data:     translationForm.serialize(),
						success:  function (response) {

							if (response.success) {
								var originalAttachmentId      = translationForm.find('input[name="original-attachment-id"]').val();
								var translatedLanguage        = translationForm.find('input[name="translated-language"]').val();
								var mediaTranslationWrap      = $('#media-attachment-' + originalAttachmentId + '-' + translatedLanguage);
								var batchMediaTranslationWrap = $('#batch-media-translation-wrap');

								// The hidden inputs hold the STRINGS "0"/"1"; a string "0" is
								// truthy, so the flags must be compared, not tested (wpmldev-8480).
								// The hidden inputs hold the STRINGS "0"/"1"; a string "0" is
								// truthy, so the flags must be compared, not tested (wpmldev-8480).
								var isMediaUpload  = false;
								var isRestoreMedia = false;
								if (response.data.thumb) {
									mediaTranslationWrap.find('img').attr('src', response.data.thumb).fadeIn();
									mediaTranslationWrap.data('thumb', response.data.thumb);
									mediaTranslationWrap.data('media-is-translated', 1);
									mediaTranslationWrap.find('.otgs-ico-edit').hide();

									isMediaUpload = translationForm.find('input[name=update-media-file]').val() === '1';
								} else {
									mediaTranslationWrap.find('img').attr('src', '').hide();
									mediaTranslationWrap.data('thumb', '');
									mediaTranslationWrap.find('.otgs-ico-edit').show();
									mediaTranslationWrap.find('img')
										.closest('.js-open-media-translation-dialog')
										.removeClass('wpml-media-translation-image');
									mediaTranslationWrap.data('media-is-translated', 0);

									isRestoreMedia = translationForm.find('input[name=restore-media]').val() === '1';
								}

								if (isMediaUpload || isRestoreMedia) {
									WPML_Media_Batch_Url_Translation.createDialog(originalAttachmentId, response.data.usage);
									batchMediaTranslationWrap.find('#batch-media-translation-form').show();
									batchMediaTranslationWrap.removeClass('notice-success');
									batchMediaTranslationWrap.addClass('notice-info');
								}

								mediaTranslationWrap.attr('title', mediaTranslationWrap.data('language-name') + ': ' +
									wpml_media_popup.status_labels[response.data.status]);

								mediaTranslationWrap.data('title', $('#media-title-translation').val());
								mediaTranslationWrap.data('caption', $('#media-caption-translation').val());
								mediaTranslationWrap.data('alt_text', $('#media-alt-text-translation').val());
								mediaTranslationWrap.data('description', $('#media-description-translation').val());

								var translatedCustomFields = translationForm.find('*[name^="translation[custom-field]"]');
								var newCustomFieldsObject = {};

								translatedCustomFields.each(function() {
									var $el  = $(this);
									var fieldID = $el.data('field_id');
									if (!Array.isArray(newCustomFieldsObject[fieldID])) {
										newCustomFieldsObject[fieldID] = [];
									}
									newCustomFieldsObject[fieldID].push($el.val());
								});

								mediaTranslationWrap.data('custom_fields', newCustomFieldsObject);

								if (response.data.attachment_id) {
									mediaTranslationWrap.data('attachment-id', response.data.attachment_id);
								}

								if (mediaTranslationWrap.find('.otgs-ico-add:visible').length) {
									var addIcon = mediaTranslationWrap.find('.otgs-ico-add');
									addIcon.removeClass('otgs-ico-add').addClass('otgs-ico-edit');
									mediaTranslationWrap.find('.js-open-media-translation-dialog')
										.attr('aria-label', mediaTranslationWrap.data('label-edit'));
									if (response.data.thumb) {
										addIcon.hide();
									}
									if (response.data.thumb) {
										mediaTranslationWrap.find('img')
											.closest('.js-open-media-translation-dialog')
											.addClass('wpml-media-translation-image');
									}
								}

								// Reset before closing: the close handler discards a staged
								// file that is still flagged, and this one is now saved.
								translationForm.find('input[name=restore-media]').val(0);
								translationForm.find('input[name=update-media-file]').val(0);

								thisDialog.dialog('close');
								ajaxLoader.remove();
							}

						}
					});
				}
			}
		]
	});

	function disableFormSave() {
		$('.wpml-media-dialog .ui-dialog-buttonset .button-primary').prop('disabled', true);
	}

	function enableFormSave(e) {
		if (typeof e !== 'undefined') {
			var charCode = (e.which) ? e.which : e.keyCode;
		}
		if (typeof e === 'undefined' || charCode >= 32 || charCode === 8) {
			$('.wpml-media-dialog .ui-dialog-buttonset .button-primary').prop('disabled', false);
		}
	}

	dialogForm.on('keyup', 'input, textarea', enableFormSave);

	$(window).resize(repositionDialog);

	function repositionDialog() {
		var winH = $(window).height() - 180;
		$('.wpml-media-dialog').css({
			'max-width': '95%'
		});

		$('.wpml-media-dialog .ui-dialog-content').css({
			'max-height': winH
		});

		dialogBox.dialog('option', 'position', {
			my: 'center',
			at: 'center',
			of: window
		});
	}

	$('.js-open-media-translation-dialog').click(function () {

		var attachmentRow   = $(this).closest('.wpml-media-attachment-row');
		var translatedMedia = $(this).closest('.wpml-media-wrapper');

		resetProgressAnimation();

		updateDialogImages(attachmentRow, translatedMedia);

		if (translatedMedia.data('media-is-translated')) {
			enableUsingTranslatedMediaFile();
		} else {
			enableUsingOriginalMediaFile();
		}

		updateDialogFormFields(attachmentRow, translatedMedia);

		updateDialogHiddenFormFields(attachmentRow, translatedMedia);

		dialogBox.dialog('open');

		dialogBox.find('.js-button-copy').click(function (event) {
			event.preventDefault();
			var formRow          = $(this).closest('.wpml-form-row');
			var originalInput    = formRow.find('input[id$="original"],textarea[id$="original"]');
			var translationInput = formRow.find('input[id$="translation"],textarea[id$="translation"]');
			if (translationInput.val() !== originalInput.val()) {
				translationInput.val(originalInput.val());
				enableFormSave();
			}
			return false;
		});

	});

	function updateDialogImages(attachmentRow, translatedMedia) {

		dialogBox.find('.wpml-header-original .wpml-title-flag img').attr('src', attachmentRow.data('flag'));
		dialogBox.find('.wpml-header-translation .wpml-title-flag img').attr('src', translatedMedia.data('flag'));

		$('.wpml-media-original-image .wpml-media-original-title')
			.html(attachmentRow.data('is-image') ? '' : attachmentRow.data('file-name'));
		$('.wpml-media-upload-handle .wpml-media-translated-title')
			.html(attachmentRow.data('is-image') || !translatedMedia.data('media-is-translated') ? '' : translatedMedia.data('file-name'));

		dialogBox.find('.wpml-header-original strong').html(attachmentRow.data('language-name'));
		dialogBox.find('.wpml-header-translation strong').html(translatedMedia.data('language-name'));

		var originalImg   = dialogBox.find('.wpml-form-row .wpml-media-original-image img');
		var translatedImg = dialogBox.find('.wpml-form-row .wpml-media-upload-handle img');

		originalImg.attr('src', attachmentRow.data('thumb')).attr('alt', attachmentRow.data('language-code'));

		translatedImg.attr('src', translatedMedia.data('thumb') ? translatedMedia.data('thumb') : attachmentRow.data('thumb')).attr('alt', translatedMedia.data('language-code'));
		if (!attachmentRow.data('is-image')) {
			originalImg.addClass('is-non-image');
			translatedImg.addClass('is-non-image');
		} else {
			originalImg.removeClass('is-non-image');
			translatedImg.removeClass('is-non-image');
		}

		mediaFileUploadForm.find('input:file').attr('accept', attachmentRow.data('mime-type'));
	}

	function updateDialogFormFields(attachmentRow, translatedMedia) {
		// Every text row is offered whether or not the original filled it: a
		// caption or description can exist only in the target language
		// (wpmldev-8479, ruling 2026-09-10).
		$('#media-title-original').val(attachmentRow.data('title') || '');
		$('#media-title-translation').val(translatedMedia.data('title') || '');
		$('#media-caption-original').val(attachmentRow.data('caption') || '');
		$('#media-caption-translation').val(translatedMedia.data('caption') || '');
		$('#media-alt-text-original').val(attachmentRow.data('alt_text') || '');
		$('#media-alt-text-translation').val(translatedMedia.data('alt_text') || '');
		$('#media-description-original').val(attachmentRow.data('description') || '');
		$('#media-description-translation').val(translatedMedia.data('description') || '');
		$('.wpml-form-row-title, .wpml-form-row-caption, .wpml-form-row-alt-text, .wpml-form-row-description').show();

		var customFieldsHTML = '';
		if (attachmentRow.data('custom_fields')) {
			var customFields = attachmentRow.data('custom_fields');
			var copyText     = attachmentRow.data('copy_from_original');
			// A language with no translation yet carries no translated values (its cell says
			// null); every read below must see an empty set, not null (wpmldev-10115).
			var translatedCustomFields = translatedMedia.data('custom_fields') || {};

			function escapeHTML(value) {
				return String(value)
					.replace(/&/g, '&amp;')
					.replace(/"/g, '&quot;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			}

			function renderRow(fieldID, fieldName, displayVal, translatedVal) {
				const hasNewLines = displayVal.includes('\n');
				fieldID       = escapeHTML(fieldID);
				fieldName     = escapeHTML(fieldName);
				displayVal    = escapeHTML(displayVal);
				translatedVal = escapeHTML(translatedVal);
				return `
					  <div class="wpml-form-row wpml-form-row-custom-field ${fieldID}">
						<label for="media-custom-field-${fieldID}-original">${fieldID}</label>
				
						${hasNewLines
							? `<textarea readonly id="media-custom-field-${fieldName}-original" cols="22" rows="4">${displayVal}</textarea>`
							: `<input readonly id="media-custom-field-${fieldName}-original" type="text" value="${displayVal}">`
						}
						<button type="button" class="button-copy button-secondary js-button-copy otgs-ico-copy"
								title="${escapeHTML(copyText)}" aria-label="${escapeHTML(copyText)}"></button>
				
						${hasNewLines
							? `<textarea data-field_id="${fieldID}" name="translation[custom-field][${fieldName}]" id="media-custom-field-${fieldName}-translation" cols="22" rows="4">${translatedVal}</textarea>`
							: `<input data-field_id="${fieldID}" name="translation[custom-field][${fieldName}]" id="media-custom-field-${fieldName}-translation" type="text" value="${translatedVal}">`
						}
					  </div>
					`;
			}

			Object.entries(customFields).forEach(([fieldID, val]) => {
				if (!val) return;

				var translatedValOrBlank = k => (translatedCustomFields[k] || '');
				var fieldName;

				if ( val.length > 1 ) {
					val.forEach((item, index) => {
						fieldName = `${fieldID}__cf${index}`;
						var displayVal = item;
						var translatedVal = Array.isArray(translatedCustomFields[fieldID])
							? (translatedCustomFields[fieldID][index] || '')
							: '';
						customFieldsHTML += renderRow( fieldID, fieldName, displayVal, translatedVal );
					});
				} else {
					fieldName = fieldID;
					// MED-3c: renderRow tests `displayVal.includes('\n')`. Passing the raw
					// meta ARRAY turned that into element equality, so a single-valued
					// multiline field never reached the textarea branch. Pass the string,
					// exactly as the multi-entry branch already does per item.
					customFieldsHTML += renderRow( fieldID, fieldName, Array.isArray(val) ? (val[0] || '') : val, translatedValOrBlank(fieldID) );
				}
			});
		}

		$('#wpml-custom-fields-container').html(customFieldsHTML);
	}

	/**
	 * The dialog closed with an upload staged and not saved: ask the server to
	 * delete the staged file, so it is neither left on disk nor put live by a
	 * later save (MED-4a).
	 */
	function discardStagedMediaFile() {
		var updateMediaFile = dialogForm.find('input[name=update-media-file]');
		if (updateMediaFile.val().toString() !== '1') {
			return;
		}
		updateMediaFile.val(0);

		var data = {};
		$.each(mediaFileUploadForm.serializeArray(), function (i, field) {
			data[field.name] = field.value;
		});
		data.discard = 1;

		$.ajax({
			url:  ajaxurl,
			type: 'POST',
			data: data
		});
	}

	function updateDialogHiddenFormFields(attachmentRow, translatedMedia) {
		var hasUsagesInPostsByCopy = attachmentRow.closest('.wpml-media-attachment-row').attr('data-has-usages-in-posts-by-copy');
		var hasUsagesInPostsByReference = attachmentRow.closest('.wpml-media-attachment-row').attr('data-has-usages-in-posts-by-reference');
		var copiedMediaLabel = attachmentRow.closest('.wpml-media-attachment-row').attr('data-copied-media-label');
		var referencedMediaLabel = attachmentRow.closest('.wpml-media-attachment-row').attr('data-referenced-media-label');
		dialogForm.find('input[name=has_usages_in_posts_by_copy]').val(hasUsagesInPostsByCopy);
		dialogForm.find('input[name=has_usages_in_posts_by_reference]').val(hasUsagesInPostsByReference);
		dialogForm.find('input[name=copied_media_label]').val(copiedMediaLabel);
		dialogForm.find('input[name=referenced_media_label]').val(referencedMediaLabel);

		dialogForm.find('input[name=original-attachment-id]').val(attachmentRow.data('attachment-id'));
		dialogForm.find('input[name=translated-attachment-id]').val(translatedMedia.data('attachment-id'));
		dialogForm.find('input[name=translated-language]').val(translatedMedia.data('language-code'));

		dialogForm.find('input[name=restore-media]').val(0);
		// A file staged in an earlier opening of the dialog was cancelled. Left
		// at 1, a save that only changed the texts put that file live (MED-4a).
		dialogForm.find('input[name=update-media-file]').val(0);

		mediaFileUploadForm.find('input[name=attachment-id]').val(translatedMedia.data('attachment-id'));
		mediaFileUploadForm.find('input[name=original-attachment-id]').val(attachmentRow.data('attachment-id'));
		mediaFileUploadForm.find('input[name=language]').val(translatedMedia.data('language-code'));
	}

	function enableUsingTranslatedMediaFile() {
		dialogForm.find('.wpml-media-upload-text').hide();
		dialogForm.find('.js-wpml-media-revert').show();
	}

	function enableUsingOriginalMediaFile() {
		dialogForm.find('.wpml-media-upload-text').show();
		dialogForm.find('.js-wpml-media-revert').hide();
	}

	function resetProgressAnimation() {
		$('.wpml-media-dialog').find('.spinner').remove();
	}

	function triggerMediaUpload(event) {
		event.preventDefault();
		mediaFileUploadForm.find('input[type=file]').trigger('click');
		return false;
	}

	function restoreMediaFile(event) {
		event.preventDefault();

		var imagesRow       = $(this).closest('.wpml-form-row');
		var originalImage   = imagesRow.find('.wpml-media-original-image img');
		var translatedImage = imagesRow.find('.wpml-media-translation-image img');

		dialogForm.find('input[name=update-media-file]').val(0);

		translatedImage.attr('src', originalImage.attr('src'));

		dialogForm.find('input[name=restore-media]').val(1);

		enableUsingOriginalMediaFile();
		enableFormSave();

		return false;
	}

	$('.js-wpml-media-revert').on('click', 'a', restoreMediaFile);

	dialogBox.find('.wpml-form-row').on('click', '.wpml-media-translation-image', triggerMediaUpload);

	// The dialog opener, "Use a different file" and "Revert to original" are
	// anchors without href, exposed as role=button: Enter and Space must act
	// like a click so the screen is operable by keyboard (wpmldev-8429).
	$(document).on('keydown', '.js-open-media-translation-dialog, .wpml-media-upload-handle a, .js-wpml-media-revert a', function (event) {
		if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
			event.preventDefault();
			$(this).trigger('click');
		}
	});

	mediaFileUploadForm.find('input[type=file]').change(
		function () {
			var file   = $(this)[0].files[0];
			var upload = new Upload(file);
			upload.doUpload();
		}
	);

	// Async file upload
	var Upload = function (file) {
		this.file        = file;
		this.progressBar = $('#wpml-media-upload-progress-animation');
	};

	Upload.prototype.getType  = function () {
		return this.file.type;
	};
	Upload.prototype.getSize  = function () {
		return this.file.size;
	};
	Upload.prototype.getName  = function () {
		return this.file.name;
	};
	Upload.prototype.doUpload = function () {
		var that     = this;
		var formData = new FormData();

		this.resetError();

		var attachmentId = 0;

		formData.append('file', this.file, this.getName());
		var fields = mediaFileUploadForm.serializeArray();
		$.each(fields, function (i, field) {
			formData.append(field.name, field.value);

			if (field.name === 'attachment-id') {
				attachmentId = field.value;
			}
		});

		that.progressBar.show();

		$.ajax({
			type:        'POST',
			url:         ajaxurl,
			xhr:         function () {
				var myXhr = $.ajaxSettings.xhr();
				if (myXhr.upload) {
					myXhr.upload.addEventListener('progress', that.progressHandling, false);
				}
				return myXhr;
			},
			success:     function (response) {
				that.progressBar.hide();
				if (response.success) {
					var translatedImgTag = dialogBox.find('.wpml-form-row .wpml-media-upload-handle img');
					translatedImgTag.attr('src', response.data.thumb);
					dialogForm.find('input[name=translated-attachment-id]').val(response.data.attachment_id);
					if (translatedImgTag.hasClass('is-non-image')) {
						dialogForm.find('.wpml-media-translated-title').html(response.data.name);
					}

					enableFormSave();
					dialogForm.find('input[name=update-media-file]').val(1);

					enableUsingTranslatedMediaFile();

					// Reset 'file' field
					mediaFileUploadForm.find('input[type=file]').val('');
				} else {
					that.setError(response.data);
				}
			},
			async:       true,
			data:        formData,
			cache:       false,
			contentType: false,
			processData: false,
			timeout:     60000
		});
	};

	Upload.prototype.progressHandling = function (event) {
		var percent         = 0;
		var position        = event.loaded || event.position;
		var total           = event.total;
		var progress_bar_id = '#wpml-media-upload-progress-animation';
		if (event.lengthComputable) {
			percent = Math.ceil(position / total * 100);
		}
		$(progress_bar_id + ' .upload-progress-bar').css('width', +percent + '%');
		$(progress_bar_id + ' .status').text(percent + '%');
	};

	Upload.prototype.setError = function (text) {
		$('#wpml-media-upload-error').html(text);
	};

	Upload.prototype.resetError = function () {
		this.setError('');
	};


});
