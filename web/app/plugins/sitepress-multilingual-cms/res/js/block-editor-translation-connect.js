/**
 * wpmldev-5955: keep block-editor translations connected when no HTTP_REFERER is sent.
 *
 * Re-attaches the post-edit context (trid / target language / source language)
 * to the editor's REST save requests via an apiFetch middleware, so the server
 * can recover the translation group even when a path-stripping Referrer-Policy
 * hides the edit-screen URL. The values are provided by PHP on
 * window.wpmlBlockEditorConnect. See class-wpml-block-editor-translation-connect.php.
 */
( function ( wp ) {
	if ( ! wp || ! wp.apiFetch || ! wp.url ) {
		return;
	}

	var args = window.wpmlBlockEditorConnect || {};

	// Post/page REST save endpoint, e.g. /wp/v2/posts/123 or /wp/v2/pages/123.
	var SAVE_ENDPOINT = /\/wp\/v2\/[^/]+\/[0-9]+/;

	var isSaveRequest = function ( options ) {
		var path = options.path || options.url || '';
		return /^(POST|PUT)$/i.test( options.method || '' ) && SAVE_ENDPOINT.test( path );
	};

	wp.apiFetch.use( function ( options, next ) {
		if ( isSaveRequest( options ) ) {
			if ( options.path ) {
				options.path = wp.url.addQueryArgs( options.path, args );
			}
			if ( options.url ) {
				options.url = wp.url.addQueryArgs( options.url, args );
			}
		}

		return next( options );
	} );
} )( window.wp );
