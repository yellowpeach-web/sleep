/*jshint browser:true, devel:true */
/*global document */

var WPMLLanguageSwitcherDropdownClick = (function() {
    "use strict";

    var wrapperSelector = '.js-wpml-ls-legacy-dropdown-click';
    var submenuSelector = '.js-wpml-ls-sub-menu';
    var toggleSelector = 'a.js-wpml-ls-item-toggle';

    var isOpen = function(wrapper) {
        var subMenu = wrapper.querySelector(submenuSelector);
        return !!subMenu && subMenu.style.visibility === 'visible';
    };

    var setOpen = function(wrapper, open) {
        var subMenu = wrapper.querySelector(submenuSelector);
        var toggleLink = wrapper.querySelector(toggleSelector);

        if (!subMenu) {
            return;
        }

        subMenu.style.visibility = open ? 'visible' : 'hidden';
        if(toggleLink) {
            toggleLink.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    };

    // Closes every open switcher that does not hold the event's target (all of
    // them without an event). The listener stays on the document only while a
    // switcher is open.
    var close = function(event) {
        var switchers = document.querySelectorAll(wrapperSelector);
        var anyOpen = false;

        for(var i=0;i<switchers.length;i++){
            if (!isOpen(switchers[i])) {
                continue;
            }
            if (event && event.target && switchers[i].contains(event.target)) {
                anyOpen = true;
            } else {
                setOpen(switchers[i], false);
            }
        }

        if (!anyOpen) {
            document.removeEventListener('click', close);
        }
    };

    var toggle = function() {
        if (isOpen(this)) {
            setOpen(this, false);
            close();
        } else {
            // Only one switcher is open at a time.
            close();
            setOpen(this, true);
            document.addEventListener('click', close);
        }

        return false;
    };

    var preventDefault = function(e) {
        var evt = e ? e : window.event;

        if (evt.preventDefault) {
            evt.preventDefault();
        }

        evt.returnValue = false;
    };

    var handleKeydown = function(e) {
        var key = e.key || e.keyCode;

        if (key === 'Enter' || key === ' ' || key === 13 || key === 32) {
            preventDefault(e);
            var wrapper = this.closest(wrapperSelector);
            if (wrapper) {
                toggle.call(wrapper, e);
            }
        }
    };

    // Escape on the toggle or on a language link closes the list and moves
    // focus to the toggle.
    var handleWrapperKeydown = function(e) {
        var key = e.key || e.keyCode;

        if ((key === 'Escape' || key === 'Esc' || key === 27) && isOpen(this)) {
            preventDefault(e);
            setOpen(this, false);
            var toggleLink = this.querySelector(toggleSelector);
            if (toggleLink) {
                toggleLink.focus();
            }
        }
    };

    // The switcher under a pointer press. Safari does not focus a link on a
    // mouse press, so pressing a language link moves focus out of the switcher
    // (focusout with no relatedTarget) before the click reaches the link. The
    // click that ends the press closes the list (toggle(), or close() for a
    // click outside), so focusout leaves it alone during the press.
    var pressedWrapper = null;

    var handlePointerdown = function() {
        pressedWrapper = this;
    };

    // The press ends with its click, or with no click (pointercancel, a key).
    var endPress = function() {
        pressedWrapper = null;
    };

    // Focus leaving the switcher (Tab out of the list, a click elsewhere)
    // closes the list. Focus is not moved.
    var handleFocusout = function(e) {
        if (pressedWrapper === this) {
            return;
        }
        if ((!e.relatedTarget || !this.contains(e.relatedTarget)) && isOpen(this)) {
            setOpen(this, false);
        }
    };

    var documentReady = false;

    var init = function() {
        // init() can run more than once (a theme or plugin may call it again): bind once.
        if (!documentReady) {
            documentReady = true;
            document.addEventListener('click', endPress, true);
            document.addEventListener('pointercancel', endPress, true);
            document.addEventListener('keydown', endPress, true);
        }

        var wrappers = document.querySelectorAll(wrapperSelector);
        for(var i=0; i < wrappers.length; i++ ) {
            if (wrappers[i].wpmlLsDropdownClickReady) {
                continue;
            }
            wrappers[i].wpmlLsDropdownClickReady = true;
            wrappers[i].addEventListener('click', toggle );
            wrappers[i].addEventListener('keydown', handleWrapperKeydown );
            wrappers[i].addEventListener('focusout', handleFocusout );
            wrappers[i].addEventListener('pointerdown', handlePointerdown );
        }

        var links = document.querySelectorAll(wrapperSelector + ' ' + toggleSelector);
        for(var j=0; j < links.length; j++) {
            if (links[j].wpmlLsDropdownClickReady) {
                continue;
            }
            links[j].wpmlLsDropdownClickReady = true;
            links[j].addEventListener('click', preventDefault );
            links[j].addEventListener('keydown', handleKeydown );
        }
    };

    return {
        'init': init
    };

})();

document.addEventListener('DOMContentLoaded', function(){
    "use strict";
    WPMLLanguageSwitcherDropdownClick.init();
});
