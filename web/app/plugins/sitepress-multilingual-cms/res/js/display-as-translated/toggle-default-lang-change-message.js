jQuery(function () {
    var before_message = jQuery('.wpml-default-lang-before-message');
    var gotIt = jQuery('.wpml-default-lang-before-message input');
    var languageEditorHost = jQuery('.wpml-language-editor-host');

    function setSaveDisabled(disabled) {
        jQuery('#icl_save_default_button, .wpml-le-save').prop('disabled', disabled);
    }

    function warnBeforeChangingDefaultLanguage() {
        before_message.show();
        setSaveDisabled(true);
    }

    // The warning is rendered by the legacy Site Languages partial. On the
    // redesigned Languages page that partial is kept hidden behind the shared
    // language editor, so move the warning next to the active editor surface.
    if (languageEditorHost.length && before_message.length) {
        before_message.detach().insertAfter(languageEditorHost);
    }

    jQuery('#icl_change_default_button').click(function () {
        warnBeforeChangingDefaultLanguage();
    });

    // The new editor renders its rows asynchronously. Delegate the event so the
    // same warning also gates its "Set as default" action.
    jQuery(document).on('click', '.wpml-le-setdefault', function () {
        warnBeforeChangingDefaultLanguage();
    });

    jQuery('#icl_cancel_default_button').click(function () {
		before_message.hide();
	});

	jQuery(gotIt).on('change', function(){
		setSaveDisabled(!gotIt.is(':checked'));
	})
});
