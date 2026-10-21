/*jshint browser:true, devel:true */
/*global document */

/*
 * The list opens on hover and on focus through the stylesheet. This script
 * keeps the toggle's aria-expanded equal to what is on screen, opens the list
 * on a click, Enter or Space, and closes it on Escape and on Enter or Space
 * while it is open (the "dismissed" state, wpmldev-2027). A screen reader's
 * click (no pointer press) works like Enter.
 */
var WPMLLanguageSwitcherDropdown = (function() {
	"use strict";

	var toggleSelector = '.js-wpml-ls-legacy-dropdown a.js-wpml-ls-item-toggle';
	var parentSelector = '.wpml-ls-item-legacy-dropdown';
	var openClass = 'wpml-ls-keyboard-open';
	var dismissedClass = 'wpml-ls-dismissed';

	var isEnterOrSpace = function(e) {
		return e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' || e.keyCode === 13 || e.keyCode === 32;
	};

	var isEscape = function(e) {
		return e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27;
	};

	var setUp = function(toggle) {
		// init() can run more than once (a theme or plugin may call it again): bind once.
		if (toggle.wpmlLsDropdownReady) {
			return;
		}
		var parent = toggle.closest(parentSelector);
		var submenu = parent ? parent.querySelector('.wpml-ls-sub-menu') : null;
		if (!submenu) {
			return;
		}
		toggle.wpmlLsDropdownReady = true;

		var shownAtPointerDown = null;
		// A pointer press inside the switcher. Safari does not focus a link on a
		// mouse press, so pressing a language link moves focus out of the switcher
		// (focusout with no relatedTarget) before the click reaches the link.
		// Closing then would hide the list under the pointer and lose the click,
		// so focus that leaves during a press is handled when the press ends.
		var pressed = false;
		var leftDuringPress = false;

		var isShown = function() {
			return window.getComputedStyle(submenu).visibility === 'visible';
		};

		var sync = function() {
			var expanded = isShown() ? 'true' : 'false';
			if (toggle.getAttribute('aria-expanded') !== expanded) {
				toggle.setAttribute('aria-expanded', expanded);
			}
		};

		var syncSoon = function() {
			window.requestAnimationFrame(sync);
		};

		var open = function() {
			parent.classList.remove(dismissedClass);
			parent.classList.add(openClass);
			if (document.activeElement !== toggle) {
				toggle.focus();
			}
			syncSoon();
		};

		var dismiss = function() {
			parent.classList.add(dismissedClass);
			parent.classList.remove(openClass);
			syncSoon();
		};

		// Enter or Space: close the list when it is shown, open it otherwise.
		var toggleList = function() {
			if (isShown()) {
				dismiss();
			} else {
				open();
			}
		};

		// Focus has left the switcher.
		var leave = function() {
			parent.classList.remove(openClass);
			if (!parent.matches(':hover')) {
				parent.classList.remove(dismissedClass);
			}
			syncSoon();
		};

		parent.addEventListener('pointerdown', function() {
			pressed = true;
			leftDuringPress = false;
		});

		// The press ends with its click, or with no click (pointercancel, a key).
		// A click on a link of the switcher (the toggle, or a language that opens
		// its page) keeps the list; anything else closes it if focus left.
		var endPress = function(e) {
			if (!pressed) {
				return;
			}
			pressed = false;
			var link = e.type === 'click' && e.target && e.target.closest ? e.target.closest('a') : null;
			var onLink = !!link && parent.contains(link);
			if (link !== toggle) {
				shownAtPointerDown = null;
			}
			if (leftDuringPress && !onLink && !parent.contains(document.activeElement)) {
				leave();
			}
			leftDuringPress = false;
		};
		document.addEventListener('click', endPress, true);
		document.addEventListener('pointercancel', endPress, true);
		document.addEventListener('keydown', endPress, true);

		toggle.addEventListener('pointerdown', function() {
			shownAtPointerDown = isShown();
		});

		toggle.addEventListener('click', function(e) {
			var byPointer = shownAtPointerDown !== null;
			var wasShown = byPointer ? shownAtPointerDown : isShown();
			shownAtPointerDown = null;
			e.preventDefault();
			if (!byPointer) {
				// A click with no pointer press is what a screen reader sends for
				// Enter (NVDA, JAWS browse mode) or VO+Space: same as Enter, on
				// what was shown before this click. (Focusing the toggle here
				// shows the list through the stylesheet.)
				if (wasShown) {
					dismiss();
					if (document.activeElement !== toggle) {
						toggle.focus();
					}
				} else {
					open();
				}
				return;
			}
			// A pointer click never closes the list: on touch, the tap that shows
			// the list by hover would close it at once.
			if (!wasShown || parent.classList.contains(dismissedClass)) {
				open();
			} else {
				if (document.activeElement !== toggle) {
					toggle.focus();
				}
				syncSoon();
			}
		});

		toggle.addEventListener('keydown', function(e) {
			if (isEnterOrSpace(e)) {
				e.preventDefault();
				toggleList();
			}
		});

		parent.addEventListener('keydown', function(e) {
			if (isEscape(e) && isShown()) {
				e.preventDefault();
				dismiss();
				toggle.focus();
			}
		});

		parent.addEventListener('focusin', syncSoon);
		parent.addEventListener('mouseenter', syncSoon);

		parent.addEventListener('focusout', function(e) {
			if (!e.relatedTarget || !parent.contains(e.relatedTarget)) {
				if (pressed) {
					leftDuringPress = true;
				} else {
					leave();
					return;
				}
			}
			syncSoon();
		});

		parent.addEventListener('mouseleave', function() {
			if (!parent.contains(document.activeElement)) {
				parent.classList.remove(dismissedClass);
			}
			syncSoon();
		});
	};

	var init = function() {
		var toggles = document.querySelectorAll(toggleSelector);
		for (var i = 0; i < toggles.length; i++) {
			setUp(toggles[i]);
		}
	};

	return {
		'init': init
	};

})();

document.addEventListener('DOMContentLoaded', function(){
	"use strict";
	WPMLLanguageSwitcherDropdown.init();
});
