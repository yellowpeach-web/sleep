/* global wp, window */

/**
 * The block editor prints `admin_notices` output inside its hidden no-JS
 * fallback wrapper (`.wrap.hide-if-js.block-editor-no-js`), so a notice printed
 * there is in the DOM at 0x0 and nobody sees it. This hands the same messages to
 * the editor's own notice store, which is where the block editor renders
 * notices.
 *
 * Every WPML producer with a message for a block-editor screen appends one entry
 * to `window.wpmlBlockEditorNotices` through WPML\Notices\BlockEditorNotice, so
 * several producers share this one script without overwriting each other.
 *
 * An entry is `{ id, status, text, actions, isDismissible }`, where `status` is
 * one of the editor's 'error' | 'warning' | 'info' | 'success'.
 */
(function () {

	var entries = window.wpmlBlockEditorNotices;

	if (!entries || !entries.length || !window.wp || !wp.domReady || !wp.data) {
		return;
	}

	wp.domReady(function () {

		var notices = wp.data.dispatch('core/notices');

		if (!notices || !notices.createNotice) {
			return;
		}

		entries.forEach(function (entry) {
			if (!entry || !entry.text) {
				return;
			}

			notices.createNotice(entry.status || 'info', entry.text, {
				id: entry.id,
				isDismissible: entry.isDismissible !== false,
				actions: entry.actions || []
			});
		});
	});

})();
