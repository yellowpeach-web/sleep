var WPMLCore = WPMLCore || {};

WPMLCore.Tooltip = function (element) {
	this.trigger = element;
	this.content = this.trigger.html(this.trigger.html()).text();
	this.edge = 'bottom';
	this.align = 'left';
	this.margin_left = '-54px';

	if (!this.content) {
		this.content = this.decodeEntities(this.trigger.data('content'));
	}

	if (this.trigger.data('edge')) {
		this.edge = this.trigger.data('edge');
	}

	if (this.trigger.data('align')) {
		this.align = this.trigger.data('align');
	}

	if (this.trigger.data('margin_left')) {
		this.margin_left = this.trigger.data('margin_left');
	}

	this.trigger.empty();
	this.trigger.click(jQuery.proxy(this.onTriggerClick, this));
};

WPMLCore.Tooltip.prototype = {
	open:           function () {
		if (this.trigger.length && this.content) {
			this.trigger.addClass('js-wpml-active-tooltip');
			this.trigger.pointer({
														 pointerClass: 'js-wpml-tooltip',
														 content:      this.content,
														 position:     {
															 edge:  this.edge,
															 align: this.align
														 },
														 show:         jQuery.proxy(this.onShow, this),
														 close:        this.onClose,
														 buttons:      this.buttons

													 }).pointer('open');
		}
	},
	onShow:         function (event, t) {
		t.pointer.css('marginLeft', this.margin_left);
	},
	onClose:        function (event, t) {
		t.pointer.css('marginLeft', '0');
	},
	onTriggerClick: function (e) {
		e.preventDefault();
		this.open();
	},
	buttons:        function (event, t) {
		var button = jQuery('<a class="close" href="#">&nbsp;</a>');

		return button.on('click.pointer', function (e) {
			e.preventDefault();
			t.element.pointer('close');
		});
	},
	decodeEntities: function (encodedString) {
		var textArea = document.createElement('textarea');
		textArea.innerHTML = encodedString;
		return textArea.value;
	}
};

WPMLCore.initializeTooltips = function() {
	"use strict";

	var tooltips = jQuery('.js-wpml-tooltip-open'), tooltip = {};

	tooltips.each(function (index, element) {
		tooltip = new WPMLCore.Tooltip(jQuery(element));
	});

  // Media-translation settings help icons: dark popover, no close button,
  // hover-only (skipped on touch). Same shared behavior as the light/close-button
  // help icons on the Languages pages — only the style/options differ.
  WPMLCore.createHoverableTooltip({
    trigger:          '.js-wpml-hoverable-tooltip',
    popover:          '.wpml-hoverable-tooltip',
    activeClass:      'js-wpml-hoverable-tooltip-active',
    pointerClass:     'js-wpml-hoverable-tooltip wpml-hoverable-tooltip',
    wideTriggerClass: 'js-wpml-hoverable-tooltip-wide',
    wideModifier:     'wide-tooltip',
    withClose:        false,
    withKeyboard:     false,
    skipOnTouch:      true,
    withLink:         true,
    linkSeparator:    '<br>',
    marginLeft:       null,
    closeDelay:       500,
    position:         { my: 'center bottom', at: 'center top', edge: 'bottom center' }
  });
};

(function () {
    'use strict';

    jQuery(function () {
        WPMLCore.initializeTooltips();
    });
}());

/**
 * Wire hover/focus help tooltips that open a WordPress pointer popover. Shared by
 * every WPML help "?" icon so the open-on-hover behavior lives in one place:
 *   - the language-settings icons (language switchers, AJAX cookie, root-url and
 *     the URL-format submit button) use the light style with a close button;
 *   - the media-translation settings icons reuse it with the dark style, no close
 *     button, and hover-only (skipped on touch).
 *
 * Behavior: open on hover (and, when `withKeyboard`, on keyboard focus); keep the
 * popover open while it is itself hovered so any link inside stays reachable;
 * close on mouse-leave (after `closeDelay`), blur, or Escape. With `withKeyboard`,
 * click is kept as a fallback for touch devices where hover is not available.
 *
 * @param {Object}  config
 * @param {jQuery}  [config.context]          Element to delegate the trigger events from. Default: document.
 * @param {string}  config.trigger            Selector for the trigger icons.
 * @param {string}  config.popover            Selector for the opened popover(s) (used to keep it open on hover).
 * @param {string}  config.activeClass        Class added to the currently-open trigger.
 * @param {string}  config.pointerClass       pointerClass passed to the WP pointer widget.
 * @param {string}  [config.wideTriggerClass] When the trigger has this class, append `wideModifier` to pointerClass.
 * @param {string}  [config.wideModifier]     Class appended to pointerClass for wide triggers. Default: 'wide-tooltip'.
 * @param {Object}  [config.position]         WP pointer position. Default: { edge: 'bottom', align: 'left' }.
 * @param {?string} [config.marginLeft]       Left margin applied to the popover; pass null to leave it untouched. Default: '-54px'.
 * @param {number}  [config.closeDelay]       Delay before closing on mouse-leave, in ms. Default: 300.
 * @param {boolean} [config.withClose]        Render a close (×) button. Default: true.
 * @param {boolean} [config.withKeyboard]     Also open on focus and click, and close on blur/Escape. Default: true.
 * @param {boolean} [config.skipOnTouch]      Do not wire anything on touch devices. Default: false.
 * @param {boolean} [config.withLink]         Append the trigger's data-link-* as a link inside the popover. Default: false.
 * @param {string}  [config.linkSeparator]    Markup placed before the appended link. Default: '<br><br>'.
 */
WPMLCore.createHoverableTooltip = function (config) {
	var $             = jQuery;
	var context       = config.context || $(document);
	var marginLeft    = config.marginLeft === undefined ? '-54px' : config.marginLeft;
	var closeDelay    = config.closeDelay || 300;
	var withClose     = config.withClose !== false;
	var withKeyboard  = config.withKeyboard !== false;
	var withLink      = config.withLink === true;
	var linkSeparator = config.linkSeparator || '<br><br>';
	var position      = config.position || { edge: 'bottom', align: 'left' };
	var closeTimer    = null;

	var closeActiveTooltip = function () {
		$('.' + config.activeClass).pointer('close');
	};

	var cancelClose = function () {
		clearTimeout(closeTimer);
	};

	// Delay closing so the cursor can travel from the icon to the popover (and
	// back) without the tooltip disappearing.
	var scheduleClose = function () {
		clearTimeout(closeTimer);
		closeTimer = setTimeout(closeActiveTooltip, closeDelay);
	};

	var openTooltip = function (triggerNode) {
		if (triggerNode.hasClass(config.activeClass)) {
			return; // Tooltip is already open for this icon; avoid reopening (flicker).
		}

		var content = triggerNode.data('content');

		if (withLink) {
			var linkText = triggerNode.data('link-text');
			if (linkText && String(linkText).length > 0) {
				var linkUrl    = triggerNode.data('link-url') || '#';
				var linkTarget = triggerNode.data('link-target');
				content += linkSeparator + '<a href="' + linkUrl + '" target="' + linkTarget + '">' + linkText + '</a>';
			}
		}

		if (triggerNode.length && content) {
			// Close any other open tooltip only when we are actually opening a new
			// one. Bailing out earlier (no content) must not close the active
			// tooltip — otherwise hovering a popover that happens to match the
			// trigger selector (e.g. media's pointerClass shares the trigger class)
			// would dismiss it immediately, making links inside unreachable.
			closeActiveTooltip();

			var pointerClass = config.pointerClass;
			if (config.wideTriggerClass && triggerNode.hasClass(config.wideTriggerClass)) {
				pointerClass += ' ' + (config.wideModifier || 'wide-tooltip');
			}

			triggerNode.addClass(config.activeClass);
			triggerNode.pointer({
				pointerClass: pointerClass,
				content:      content,
				position:     position,
				show: function (event, t) {
					if (marginLeft) {
						t.pointer.css('marginLeft', marginLeft);
					}
				},
				close: function (event, t) {
					if (marginLeft) {
						t.pointer.css('marginLeft', '0');
					}
					t.element.removeClass(config.activeClass);
				},
				buttons: withClose ? function (event, t) {
					var button = $('<a class="close" href="#">&nbsp;</a>');

					return button.on('click.pointer', function (e) {
						e.preventDefault();
						t.element.pointer('close');
					});
				} : function () {}
			}).pointer('open');
		}
	};

	var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
	if (config.skipOnTouch && isTouchDevice) {
		return;
	}

	// Show the help tooltip on hover.
	context
		.on('mouseenter.tooltip', config.trigger, function () {
			cancelClose();
			openTooltip($(this));
		})
		.on('mouseleave.tooltip', config.trigger, function () {
			scheduleClose();
		});

	// Keep the popover open while it is hovered so any link inside stays reachable.
	$(document)
		.on('mouseenter.tooltip', config.popover, cancelClose)
		.on('mouseleave.tooltip', config.popover, scheduleClose);

	// Keyboard/touch affordances: open on focus and on click (a fallback for touch
	// devices, where hover is not available), and close on blur or Escape.
	if (withKeyboard) {
		context
			.on('focus.tooltip', config.trigger, function () {
				cancelClose();
				openTooltip($(this));
			})
			.on('blur.tooltip', config.trigger, function () {
				scheduleClose();
			})
			.on('click.tooltip', config.trigger, function (e) {
				e.preventDefault();
				cancelClose();
				openTooltip($(this));
			});

		$(document).on('keydown.tooltip', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				closeActiveTooltip();
			}
		});
	}
};