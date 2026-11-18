/*globals iclSaveForm*/
// The Settings hub's legacy option forms have no action/method of their own:
// they save over admin-ajax only through this iclSaveForm() submit binding.
// TranslationManagement binds the same six forms from scripts-tm.js; this file
// is enqueued by WPML\Settings\UI only when tm.php is not loaded (Blog
// license), so a form is never bound twice. Keep the two lists in sync.
jQuery(function () {
    jQuery('#icl_doc_translation_method').submit(iclSaveForm);
    jQuery('#icl_page_sync_options').submit(iclSaveForm);
    jQuery('form[name="icl_custom_tax_sync_options"]').submit(iclSaveForm);
    jQuery('form[name="icl_custom_posts_sync_options"]').submit(iclSaveForm);
    jQuery('form[name="icl_cf_translation"]').submit(iclSaveForm);
    jQuery('form[name="icl_tcf_translation"]').submit(iclSaveForm);
});
