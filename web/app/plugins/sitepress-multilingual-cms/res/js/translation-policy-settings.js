/**
 * The "Translation editor and method" section of WPML → Settings after the site's cutover
 * (wpmldev-11185): the editor and who translates, for the site and for each post type.
 *
 * Each change saves at once: a radio of step 1 or step 2, or one dropdown of a post type.
 * The saves run one after the other. After a save, the screen shows "Saved" by the choice,
 * and gets step 3 again from the answer, because a save can change its summary, the order
 * of its types, and the site's values that its dropdowns show.
 */
( function () {
	'use strict';

	var data = window.wpmlTranslationPolicySettings;

	var CLASS_STATUS = 'wpml-translation-policy-settings__status';
	var CLASS_NOTE_ROW = 'wpml-translation-policy-settings__note-row';
	var CLASS_STATUS_ROW = 'wpml-translation-policy-settings__status-row';
	var SELECTOR_OTHERS = '.wpml-translation-policy-settings__others';
	var SELECTOR_SHOW_ALL = '.wpml-translation-policy-settings__show-all';

	/**
	 * The radios of one group.
	 *
	 * @param {string} name
	 *
	 * @return {NodeList}
	 */
	function radiosOf( name ) {
		return document.querySelectorAll( 'input[type="radio"][name="' + name + '"]' );
	}

	/**
	 * The value a control saves: a radio's `data-value` (the radios of step 1 keep today's
	 * values), or its value.
	 *
	 * @param {Element} control
	 *
	 * @return {string}
	 */
	function valueOf( control ) {
		return control.getAttribute( 'data-value' ) || control.value;
	}

	/**
	 * wpmldev-12717 (W-24): "Translate with PTC?" before the site or a post type changes to PTC.
	 * It reads the words left when it opens. Without a usable account it says so and offers
	 * "Turn on automatic translation".
	 *
	 * @return {Promise<boolean>} True on "Use PTC"; false on "Cancel" or Escape.
	 */
	function confirmPtc() {
		var confirm = data.ptcConfirm;
		if ( ! confirm ) {
			return window.Promise.resolve( true );
		}
		// A browser without <dialog> still asks.
		if ( ! window.HTMLDialogElement || ! window.HTMLDialogElement.prototype.showModal ) {
			return window.Promise.resolve( window.confirm( [ confirm.words.title, confirm.words.send, confirm.words.update, confirm.words.words ].join( '\n\n' ) ) );
		}

		var words = confirm.words;
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'wpml-translation-policy-settings__ptc-confirm';
		dialog.setAttribute( 'aria-labelledby', 'wpml-translation-policy-ptc-confirm-title' );

		function paragraph( text, className ) {
			var element = document.createElement( 'p' );
			element.textContent = text;
			if ( className ) {
				element.className = className;
			}
			dialog.appendChild( element );
			return element;
		}

		function button( text, className ) {
			var element = document.createElement( 'button' );
			element.type = 'button';
			element.className = className;
			element.textContent = text;
			return element;
		}

		var title = document.createElement( 'h2' );
		title.id = 'wpml-translation-policy-ptc-confirm-title';
		title.textContent = words.title;
		dialog.appendChild( title );
		paragraph( words.send );
		paragraph( words.update );
		var account = paragraph( words.loading, 'description' );
		paragraph( words.translateEverything );

		var buttons = document.createElement( 'p' );
		buttons.className = 'wpml-translation-policy-settings__ptc-confirm-buttons';
		var cancel = button( words.cancel, 'button' );
		var use = button( words.use, 'button button-primary' );
		buttons.appendChild( cancel );
		buttons.appendChild( use );
		dialog.appendChild( buttons );
		document.body.appendChild( dialog );

		window.fetch( confirm.url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': confirm.restNonce } } )
			.then( function ( result ) {
				return result.json();
			} )
			.then( function ( state ) {
				account.className = '';
				if ( state && false === state.account_usable ) {
					account.textContent = words.noAccount + ' ';
					account.className = 'wpml-translation-policy-settings__ptc-confirm-warning';
					if ( state.remedy_url ) {
						var link = document.createElement( 'a' );
						link.href = state.remedy_url;
						link.textContent = words.turnOn;
						account.appendChild( link );
					}
					return;
				}
				account.textContent = [ words.words, state && state.words_left ? state.words_left : '' ].join( ' ' ).trim();
			} )
			.catch( function () {
				account.className = '';
				account.textContent = words.words;
			} );

		return new window.Promise( function ( resolve ) {
			function close( answer ) {
				dialog.close();
				dialog.parentNode.removeChild( dialog );
				resolve( answer );
			}

			cancel.addEventListener( 'click', function () {
				close( false );
			} );
			use.addEventListener( 'click', function () {
				close( true );
			} );
			dialog.addEventListener( 'cancel', function ( event ) {
				event.preventDefault();
				close( false );
			} );
			dialog.showModal();
		} );
	}

	function init( section ) {
		var toggle = section.querySelector( '.wpml-translation-policy-settings__toggle' );
		var panel = section.querySelector( '.wpml-translation-policy-settings__panel' );
		var summary = section.querySelector( '.wpml-translation-policy-settings__summary' );
		var methodTable = section.querySelector( '.wpml-translation-policy-settings__method-table' );
		var queue = window.Promise.resolve();
		var waiting = 0;

		/**
		 * A dropdown is grey while its value follows the site: "Same as the site", or the
		 * value the page loaded with when that value follows the site.
		 *
		 * @param {Element} select
		 */
		function markFollowsSite( select ) {
			var follows = '' === select.value ||
				( '1' === select.getAttribute( 'data-follows-site' ) && select.value === select.getAttribute( 'data-initial' ) );
			select.classList.toggle( 'is-following-site', follows );
		}

		/**
		 * Step 2: the chosen column of the table, and the words of its radios, as step 1 does
		 * for its own table (res/js/mcs/wpml-tm-mcs.js).
		 */
		function markMethodTable() {
			if ( ! methodTable ) {
				return;
			}
			Array.prototype.forEach.call( radiosOf( 'wpml-translation-policy-site-method' ), function ( radio ) {
				var label = radio.nextElementSibling;
				if ( label && window.icl_enabled_text && window.icl_choose_text ) {
					label.textContent = radio.checked ? window.icl_enabled_text : window.icl_choose_text;
				}
				if ( radio.checked ) {
					var isPtc = data.methodPtc === radio.value;
					methodTable.classList.toggle( 't_method__classic-editor', isPtc );
					methodTable.classList.toggle( 't_method__ate-editor', ! isPtc );
				}
			} );
		}

		/**
		 * Where the answer of a save shows: the line under step 1 or step 2, or a line by
		 * the post type's row.
		 *
		 * @param {Element} control
		 *
		 * @return {Element|null}
		 */
		function statusOf( control ) {
			var row = control.closest( 'tr[data-id]' );
			if ( row && panel.contains( row ) ) {
				return statusOfRow( row.getAttribute( 'data-id' ) );
			}

			var note = section.querySelector( '[data-note-for="' + control.getAttribute( 'data-column' ) + '"]' );
			if ( ! note ) {
				return null;
			}
			note.hidden = false;

			return note.querySelector( '.' + CLASS_STATUS );
		}

		/**
		 * The status line of a post type: a row of its own under the type's row, across the
		 * table, as the lock notes are.
		 *
		 * @param {string} postType
		 *
		 * @return {Element|null}
		 */
		function statusOfRow( postType ) {
			var row = panel.querySelector( 'tr[data-scope="post_type"][data-id="' + postType + '"]' );
			if ( ! row ) {
				return null;
			}

			// The status row goes after the type's own notes.
			var last = row;
			while ( last.nextElementSibling && last.nextElementSibling.classList.contains( CLASS_NOTE_ROW ) ) {
				last = last.nextElementSibling;
				if ( last.classList.contains( CLASS_STATUS_ROW ) ) {
					return last.querySelector( '.' + CLASS_STATUS );
				}
			}

			var statusRow = document.createElement( 'tr' );
			statusRow.className = CLASS_NOTE_ROW + ' ' + CLASS_STATUS_ROW;
			statusRow.innerHTML = '<td colspan="3"><p class="description"><span class="' + CLASS_STATUS + '" role="status" aria-live="polite"></span></p></td>';
			row.classList.add( 'has-note' );
			last.classList.add( 'has-note' );
			row.parentNode.insertBefore( statusRow, last.nextElementSibling );

			return statusRow.querySelector( '.' + CLASS_STATUS );
		}

		/**
		 * @param {Element|null} status
		 * @param {string}       message
		 * @param {boolean}      isError
		 */
		function showMessage( status, message, isError ) {
			if ( ! status ) {
				return;
			}
			status.textContent = message;
			status.setAttribute( 'role', isError ? 'alert' : 'status' );
			status.classList.toggle( 'is-error', isError );
		}

		/**
		 * Puts the control back on the value the page has saved.
		 *
		 * @param {Element} control
		 */
		function restore( control ) {
			if ( 'radio' !== control.type ) {
				control.value = control.getAttribute( 'data-initial' );
				markFollowsSite( control );
				return;
			}

			Array.prototype.forEach.call( radiosOf( control.name ), function ( radio ) {
				radio.checked = valueOf( radio ) === radio.getAttribute( 'data-initial' );
				radio.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
			} );
		}

		/**
		 * The control, or every radio of its group: they share one value.
		 *
		 * @param {Element} control
		 *
		 * @return {Element[]|NodeList}
		 */
		function groupOf( control ) {
			return 'radio' === control.type ? radiosOf( control.name ) : [ control ];
		}

		/**
		 * Sets one attribute on the control, or on every radio of its group. null removes it.
		 *
		 * @param {Element}     control
		 * @param {string}      name
		 * @param {string|null} value
		 */
		function setOnGroup( control, name, value ) {
			Array.prototype.forEach.call( groupOf( control ), function ( member ) {
				if ( null === value ) {
					member.removeAttribute( name );
				} else {
					member.setAttribute( name, value );
				}
			} );
		}

		/**
		 * The value the client chose last: the one a save waits for (`data-pending`), or
		 * the saved one (`data-initial`).
		 *
		 * @param {Element} control
		 *
		 * @return {string}
		 */
		function latestOf( control ) {
			return control.hasAttribute( 'data-pending' ) ? control.getAttribute( 'data-pending' ) : control.getAttribute( 'data-initial' );
		}

		/**
		 * The line where the answer of a save shows. Step 3 can be new after a save: the
		 * type's row is found again.
		 *
		 * @param {Element} control
		 * @param {string}  postType The post type, or '' for the site.
		 *
		 * @return {Element|null}
		 */
		function statusAfterSave( control, postType ) {
			return postType ? statusOfRow( postType ) : statusOf( control );
		}

		/**
		 * Step 3 again, from the answer of a save: its summary, its table and "Show all". The
		 * panel stays open, "Show all" stays as the client left it, and the focus stays on the
		 * dropdown the client changed.
		 *
		 * @param {Object} state Section::stateAfterSave().
		 */
		function update( state ) {
			if ( summary && 'string' === typeof state.summary ) {
				summary.textContent = state.summary;
			}

			Object.keys( state.notes || {} ).forEach( function ( column ) {
				var note = section.querySelector( '[data-note-for="' + column + '"] .wpml-translation-policy-settings__note-text' );
				if ( note ) {
					note.textContent = state.notes[ column ];
				}
			} );

			// A save that waits holds a dropdown of the panel: its own answer builds the panel.
			if ( 'string' !== typeof state.panel || waiting > 0 ) {
				return;
			}

			var others = panel.querySelector( SELECTOR_OTHERS );
			var isShowingAll = !! others && ! others.hidden;
			var focusedId = panel.contains( document.activeElement ) ? document.activeElement.id : '';

			panel.innerHTML = state.panel;

			if ( isShowingAll ) {
				showAll();
			}
			if ( focusedId && document.getElementById( focusedId ) ) {
				document.getElementById( focusedId ).focus();
			}
		}

		function showAll() {
			var others = panel.querySelector( SELECTOR_OTHERS );
			var button = panel.querySelector( SELECTOR_SHOW_ALL );
			if ( others ) {
				others.hidden = false;
			}
			if ( button ) {
				button.parentNode.removeChild( button );
			}
		}

		/**
		 * Saves one value of one scope: the site, or one post type.
		 *
		 * @param {Element} control
		 * @param {string}  value The value the client chose, when the save was queued.
		 *
		 * @return {Promise}
		 */
		function save( control, value ) {
			var scope = control.closest( '[data-scope]' );
			var row = control.closest( 'tr[data-id]' );
			var postType = row ? row.getAttribute( 'data-id' ) : '';
			var item = { scope: scope.getAttribute( 'data-scope' ), id: scope.getAttribute( 'data-id' ) };
			item[ control.getAttribute( 'data-column' ) ] = value;

			var body = new window.FormData();
			body.append( 'action', data.action );
			body.append( data.fields.nonce, data.nonce );
			body.append( data.fields.items, JSON.stringify( [ item ] ) );

			showMessage( statusOf( control ), data.words.saving, false );

			/**
			 * The end of this save. A newer choice of the same control is still waiting when
			 * `data-pending` holds another value: then this save does not touch the control.
			 *
			 * @param {Object|null} state   The answer of a save that passed, or null.
			 * @param {string}      message
			 */
			function done( state, message ) {
				var isLatest = latestOf( control ) === value;
				waiting--;
				if ( isLatest ) {
					setOnGroup( control, 'data-pending', null );
				}

				if ( state ) {
					setOnGroup( control, 'data-initial', value );
					update( state );
				} else if ( isLatest ) {
					restore( control );
				}
				showMessage( statusAfterSave( control, postType ), message, ! state );
			}

			return window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( result ) {
					return result.json();
				} )
				.then( function ( result ) {
					if ( result && result.success ) {
						done( result.data, result.data.message );
						return;
					}

					done( null, result && result.data && result.data.message ? result.data.message : data.words.notSaved );
				} )
				.catch( function () {
					done( null, data.words.notSaved );
				} );
		}

		section.addEventListener( 'change', function ( event ) {
			var target = event.target;
			if ( ! target.hasAttribute( 'data-column' ) || target.disabled ) {
				return;
			}
			if ( 'SELECT' === target.tagName ) {
				markFollowsSite( target );
			}
			if ( 'radio' === target.type ) {
				markMethodTable();
				// restore() sends a change for each radio of the group: only a checked radio is
				// a choice of the client.
				if ( ! target.checked ) {
					return;
				}
			}

			var value = valueOf( target );
			if ( value === latestOf( target ) ) {
				return;
			}

			// wpmldev-12717: a change to PTC asks first, every time. "Cancel" keeps the saved value.
			if ( becomesPtc( target, value ) ) {
				confirmPtc().then( function ( confirmed ) {
					if ( confirmed ) {
						enqueue( target, value );
					} else {
						restore( target );
					}
				} );
				return;
			}

			enqueue( target, value );
		} );

		/**
		 * Does this change make the site or a post type use PTC? A post type's "Same as the
		 * site" ('') does when the site uses PTC.
		 *
		 * @param {Element} control
		 * @param {string}  value
		 *
		 * @return {boolean}
		 */
		function becomesPtc( control, value ) {
			if ( 'method' !== control.getAttribute( 'data-column' ) ) {
				return false;
			}
			if ( data.methodPtc === value ) {
				return true;
			}
			if ( '' !== value || 'SELECT' !== control.tagName ) {
				return false;
			}

			var site = radiosOf( 'wpml-translation-policy-site-method' )[ 0 ];

			return !! site && data.methodPtc === latestOf( site );
		}

		/**
		 * @param {Element} control
		 * @param {string}  value
		 */
		function enqueue( control, value ) {
			setOnGroup( control, 'data-pending', value );
			waiting++;
			queue = queue.then( function () {
				return save( control, value );
			} );
		}

		if ( toggle && panel ) {
			toggle.addEventListener( 'click', function () {
				var isOpen = 'true' === toggle.getAttribute( 'aria-expanded' );
				toggle.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
				panel.hidden = isOpen;
			} );
		}

		// The panel's content is new after each save: listen on the panel, not on the button.
		if ( panel ) {
			panel.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( SELECTOR_SHOW_ALL ) ) {
					showAll();
				}
			} );
		}

		markMethodTable();
	}

	function start() {
		var section = document.querySelector( '.wpml-translation-policy-settings' );
		if ( section && data ) {
			init( section );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
