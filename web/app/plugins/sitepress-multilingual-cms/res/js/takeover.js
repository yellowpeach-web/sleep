/**
 * Front-end take-over modal.
 *
 * When a translator clicks the admin-bar "Edit Translation" link for a job that
 * is waiting / in progress for another translator, this intercepts the click and
 * shows a Medium-friction confirmation: an "I understand" checkbox arms the
 * "Take over" button and a red paragraph states the risk. On confirm it calls the
 * Reassign AJAX endpoint and, on success, navigates to the editor URL it returns.
 *
 * Data (job id, assignee, endpoint, nonce, copy) comes from the localized
 * `wpmlTakeOver` object. The script is only enqueued for the hard take-over case.
 */
( function () {
	'use strict';

	var data = window.wpmlTakeOver;
	if ( ! data || ! data.jobId ) {
		return;
	}

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		// Admin editor page: the page IS the "about to open the editor" moment, so
		// show the modal immediately rather than intercepting a link click.
		if ( data.immediate ) {
			openModal( null );
			return;
		}

		var node = document.getElementById( data.nodeId );
		var link = node ? node.querySelector( 'a' ) : null;
		if ( ! link ) {
			return;
		}

		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openModal( link.getAttribute( 'href' ) );
		} );
	} );

	var lastFocused = null;

	function openModal( editHref ) {
		var s = data.strings;
		lastFocused = document.activeElement;

		var overlay = el( 'div', 'wpml-takeover__overlay' );
		var dialog = el( 'div', 'wpml-takeover__dialog' );
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', 'wpml-takeover-title' );

		var title = el( 'h2', 'wpml-takeover__title', s.title );
		title.id = 'wpml-takeover-title';

		var body = el( 'p', 'wpml-takeover__body', s.body );
		var risk = el( 'p', 'wpml-takeover__risk', s.risk );

		var ackLabel = el( 'label', 'wpml-takeover__ack' );
		var ackBox = el( 'input' );
		ackBox.type = 'checkbox';
		ackLabel.appendChild( ackBox );
		ackLabel.appendChild( document.createTextNode( ' ' + s.ack ) );

		var actions = el( 'div', 'wpml-takeover__actions' );
		var cancelBtn = el( 'button', 'wpml-takeover__btn wpml-takeover__btn--secondary', s.cancel );
		cancelBtn.type = 'button';
		var confirmBtn = el( 'button', 'wpml-takeover__btn wpml-takeover__btn--primary', s.takeOver );
		confirmBtn.type = 'button';
		confirmBtn.disabled = true;

		ackBox.addEventListener( 'change', function () {
			confirmBtn.disabled = ! ackBox.checked;
		} );

		actions.appendChild( cancelBtn );
		actions.appendChild( confirmBtn );
		[ title, body, risk, ackLabel, actions ].forEach( function ( n ) {
			dialog.appendChild( n );
		} );
		overlay.appendChild( dialog );
		document.body.appendChild( overlay );

		function close() {
			document.removeEventListener( 'keydown', onKeyDown );
			// On the admin editor page there is nothing useful behind the modal, so
			// cancelling returns to the queue instead of leaving a blank page.
			if ( data.immediate && data.cancelUrl ) {
				window.location.href = data.cancelUrl;
				return;
			}
			if ( overlay.parentNode ) {
				overlay.parentNode.removeChild( overlay );
			}
			if ( lastFocused && lastFocused.focus ) {
				lastFocused.focus();
			}
		}

		function onKeyDown( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				close();
			} else if ( e.key === 'Tab' ) {
				trapTab( e, dialog );
			}
		}

		overlay.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === overlay ) {
				close();
			}
		} );
		cancelBtn.addEventListener( 'click', close );
		confirmBtn.addEventListener( 'click', function () {
			confirmBtn.disabled = true;
			cancelBtn.disabled = true;
			takeOver( editHref, confirmBtn, close );
		} );
		document.addEventListener( 'keydown', onKeyDown );

		ackBox.focus();
	}

	function takeOver( editHref, confirmBtn, close ) {
		var body = new window.URLSearchParams();
		body.append( 'action', 'wpml_action' );
		body.append( 'endpoint', data.endpoint );
		body.append( 'nonce', data.nonce );
		body.append( 'data', JSON.stringify( { jobId: data.jobId, expectedTranslatorId: data.assignee } ) );

		window.fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( res ) {
			return res.json();
		} ).then( function ( json ) {
			if ( json && json.success && json.data && json.data.editUrl ) {
				window.location.href = json.data.editUrl;
				return;
			}
			// status_changed / not_allowed / job_not_found / reassign_failed:
			// the page no longer reflects reality — tell the user and reload.
			var code = json && json.data ? json.data : '';
			window.alert( code === 'status_changed' ? data.strings.statusChanged : data.strings.genericError );
			window.location.reload();
		} ).catch( function () {
			close();
			window.alert( data.strings.genericError );
		} );
	}

	function trapTab( e, dialog ) {
		var focusable = dialog.querySelectorAll( 'button:not([disabled]), input, a[href]' );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}
} )();
