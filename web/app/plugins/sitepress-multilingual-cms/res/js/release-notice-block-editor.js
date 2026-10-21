/* global wp, window */

/**
 * The Round 5 "your update replaced a translation in progress" notice, in the
 * block editor.
 *
 * The editor saves over REST and never reloads, so an `edit_form_top` or
 * `admin_notices` notice has no screen to appear on. It asks WPML for the
 * notice instead, over a route that hands each queued entry out exactly once.
 *
 * WHY IT ASKS RATHER THAN BEING TOLD (measured on the lab, round 5 W4.1):
 *
 * - the entry does not exist when the save's response arrives. WPML cancels the
 *   superseded translation, reads what came back and writes the notice on
 *   `shutdown`, which runs after the response has been sent - so no field on
 *   that response can carry it. That is what the poll below is for;
 * - and nothing may be consumed while a page is BUILT, because after every save
 *   Gutenberg fetches a whole editor page in the background to refresh the
 *   metaboxes, and that page's scripts never run. A page that runs no scripts
 *   asks for nothing, so it takes nothing.
 *
 * `show()` dispatches each notice id exactly once, so an answer that arrives
 * twice - a load and a save that raced - is one notice on the screen.
 */
( function () {

	var data = window.wpmlReleaseNoticeData;

	if ( ! data || ! data.path || ! window.wp || ! wp.domReady || ! wp.data || ! wp.apiFetch ) {
		return;
	}

	var shown = {};

	/** Cancels an older chain when a newer save starts one. */
	var chain = 0;

	/**
	 * @param {{id: string, text: string, ledger_url: string}|null} notice
	 *
	 * @return {boolean} Whether something was dispatched.
	 */
	function show( notice ) {
		if ( ! notice || ! notice.id || ! notice.text ) {
			return false;
		}

		if ( shown[ notice.id ] ) {
			return true;
		}

		var notices = wp.data.dispatch( 'core/notices' );

		if ( ! notices || ! notices.createNotice ) {
			return false;
		}

		shown[ notice.id ] = true;

		notices.createNotice( 'success', notice.text, {
			id: notice.id,
			isDismissible: true,
			actions: notice.ledger_url
				? [ { url: notice.ledger_url, label: data.linkLabel } ]
				: []
		} );

		return true;
	}

	/**
	 * Asks once, and keeps asking on the given schedule until something comes
	 * back or the schedule runs out.
	 *
	 * @param {number[]} delays Milliseconds before each further attempt.
	 */
	function ask( delays ) {
		chain ++;

		var mine = chain;

		function attempt( remaining ) {
			if ( mine !== chain ) {
				return;
			}

			wp.apiFetch( { path: data.path } ).then( function ( notice ) {
				if ( mine !== chain ) {
					return;
				}

				if ( show( notice ) || ! remaining.length ) {
					return;
				}

				retry( remaining );
			} ).catch( function () {
				if ( mine === chain && remaining.length ) {
					retry( remaining );
				}
			} );
		}

		function retry( remaining ) {
			window.setTimeout( function () {
				attempt( remaining.slice( 1 ) );
			}, remaining[ 0 ] );
		}

		attempt( ( delays || [] ).slice() );
	}

	wp.domReady( function () {
		// Whatever an earlier save left queued: one question, no schedule. The
		// entry is either already there or it is not.
		ask( [] );

		var editor = wp.data.select( 'core/editor' );

		if ( ! editor || ! editor.isSavingPost ) {
			return;
		}

		var wasSaving = editor.isSavingPost();

		wp.data.subscribe( function () {
			var isSaving = wp.data.select( 'core/editor' ).isSavingPost();

			// Only on the edge from saving to saved. The entry this save
			// produces is written when the request ends, so the schedule starts
			// here rather than the answer arriving with the response.
			if ( wasSaving && ! isSaving ) {
				ask( data.pollDelays );
			}

			wasSaving = isSaving;
		} );
	} );

} )();
