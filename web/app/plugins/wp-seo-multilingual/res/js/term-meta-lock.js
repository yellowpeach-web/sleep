/**
 * Visual lock for package-served Yoast term meta fields (wpmlwpseo-395).
 *
 * Yoast's term metabox is a React app we must not touch: no reparenting, no
 * classes or styles on its nodes. The locks are absolutely-positioned overlays
 * living in a layer WE own, appended after the metabox, and kept aligned with
 * the target fields via ResizeObserver / MutationObserver. The overlay is a
 * cue, not the enforcement - the server-side save guard is what keeps a
 * displayed translation from being persisted.
 */
( function () {
	'use strict';

	var data = window.wpseomlTermMetaLock;
	if ( ! data || ! data.lockedFields ) {
		return;
	}

	var overriddenFields = data.overriddenFields || {};

	var TARGETS = {
		wpseo_title:                   '#yoast-google-preview-title-metabox',
		wpseo_desc:                    '#yoast-google-preview-description-metabox',
		wpseo_focuskw:                 '#focus-keyword-input-metabox',
		wpseo_bctitle:                 '#yoast-breadcrumbs-title-metabox',
		'wpseo_opengraph-title':       '#social-title-input-metabox',
		'wpseo_opengraph-description': '#social-description-input-metabox',
		'wpseo_twitter-title':         '#x-title-input-metabox',
		'wpseo_twitter-description':   '#x-description-input-metabox',
	};

	var metabox = document.getElementById( 'wpseo_meta' );
	if ( ! metabox ) {
		return;
	}

	var layer = document.createElement( 'div' );
	layer.className = 'wpseoml-lock-layer';
	layer.setAttribute( 'data-wpseoml', 'lock-layer' );
	metabox.appendChild( layer );

	var overlays = {};
	var unlocked = {};
	var hints    = {};

	function buildOverlay( field ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'wpseoml-lock';
		overlay.setAttribute( 'data-wpseoml', 'lock-' + field );

		var label = document.createElement( 'span' );
		label.className = 'wpseoml-lock__label';
		label.title = data.i18n.lockedHint;
		label.innerHTML = '<span class="dashicons dashicons-lock"></span> ';
		label.appendChild( document.createTextNode( data.i18n.lockedLabel ) );

		var unlock = document.createElement( 'button' );
		unlock.type = 'button';
		unlock.className = 'button-link wpseoml-lock__unlock';
		unlock.setAttribute( 'data-wpseoml', 'unlock-' + field );
		unlock.textContent = data.i18n.editAnyway;
		unlock.addEventListener( 'click', function () {
			unlocked[ field ] = true;
			overlay.remove();
			delete overlays[ field ];
			var target = document.querySelector( TARGETS[ field ] );
			if ( target ) {
				target.focus();
			}
			scheduleSync();
		} );

		overlay.appendChild( label );
		overlay.appendChild( unlock );

		return overlay;
	}

	function buildHint( field ) {
		var hint = document.createElement( 'div' );
		hint.className = 'wpseoml-lock-hint';
		hint.setAttribute( 'data-wpseoml', 'override-hint-' + field );
		hint.textContent = data.i18n.overrideHint;

		return hint;
	}

	function positionHint( hint, target ) {
		var box    = target.getBoundingClientRect();
		var anchor = layer.getBoundingClientRect();

		if ( ! box.width || ! box.height ) {
			hint.style.display = 'none';
			return;
		}

		hint.style.display = '';
		hint.style.top     = ( box.bottom - anchor.top + 2 ) + 'px';
		hint.style.left    = ( box.left - anchor.left ) + 'px';
		hint.style.width   = box.width + 'px';
	}

	function position( overlay, target ) {
		var box    = target.getBoundingClientRect();
		var anchor = layer.getBoundingClientRect();

		if ( ! box.width || ! box.height ) {
			overlay.style.display = 'none';
			return;
		}

		overlay.style.display = '';
		overlay.style.top     = ( box.top - anchor.top ) + 'px';
		overlay.style.left    = ( box.left - anchor.left ) + 'px';
		overlay.style.width   = box.width + 'px';
		overlay.style.height  = box.height + 'px';
	}

	function sync() {
		Object.keys( TARGETS ).forEach( function ( field ) {
			var target = document.querySelector( TARGETS[ field ] );

			syncLock( field, target );
			syncHint( field, target );
		} );
	}

	function syncLock( field, target ) {
		if ( ! data.lockedFields[ field ] || unlocked[ field ] ) {
			return;
		}

		if ( ! target ) {
			if ( overlays[ field ] ) {
				overlays[ field ].style.display = 'none';
			}
			return;
		}

		if ( ! overlays[ field ] ) {
			overlays[ field ] = buildOverlay( field );
			layer.appendChild( overlays[ field ] );
		}

		position( overlays[ field ], target );
	}

	// The hint marks a field whose value competes with a package translation:
	// a stored manual override, or a field the user just unlocked.
	function syncHint( field, target ) {
		if ( ! overriddenFields[ field ] && ! unlocked[ field ] ) {
			return;
		}

		if ( ! target ) {
			if ( hints[ field ] ) {
				hints[ field ].style.display = 'none';
			}
			return;
		}

		if ( ! hints[ field ] ) {
			hints[ field ] = buildHint( field );
			layer.appendChild( hints[ field ] );
		}

		positionHint( hints[ field ], target );
	}

	var scheduled = false;
	function scheduleSync() {
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			sync();
		} );
	}

	new MutationObserver( scheduleSync ).observe( metabox, { childList: true, subtree: true } );
	new ResizeObserver( scheduleSync ).observe( metabox );
	window.addEventListener( 'resize', scheduleSync );
	window.addEventListener( 'scroll', scheduleSync, true );

	sync();
} )();
