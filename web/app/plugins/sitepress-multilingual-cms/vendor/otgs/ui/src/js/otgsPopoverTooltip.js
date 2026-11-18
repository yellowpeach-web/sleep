import '../scss/otgsPopoverTooltip.scss';

/**
 * OTGS popover tooltip (wpmldev-12550): our own, no library.
 *
 * Replaces tippy.js 4 + Popper 1 behind the same contract the products use:
 *
 * - hook classes `js-otgs-popover-tooltip` / `js-wpml-popover-tooltip`;
 * - content from `data-tippy-content` (HTML), else the `title` attribute, which is
 *   moved out of the attribute so the browser does not show its own tooltip;
 * - options from `data-tippy-*` attributes: placement, zIndex, distance, maxWidth,
 *   boundary, flip, interactive, sticky, arrow;
 * - the rendered DOM tippy 4 produced, which the products' stylesheets style:
 *   `.tippy-popper[x-placement]` > `.tippy-tooltip.otgs-theme` > `.tippy-arrow` + `.tippy-content`;
 * - keyboard reach: a reference that cannot take focus on its own gets
 *   `tabindex="0"`, as tippy 4's `a11y` default gave it, so Tab opens the tooltip;
 * - `element._tippy` and `popper._tippy`, the instance, with the part of tippy 4's
 *   instance API the products call: `show`, `hide`, `setContent`, `destroy`,
 *   `set( options )` (alias `setProps`) with the `onShow`/`onShown`/`onHide`/
 *   `onHidden`/`onMount` callbacks, `props`, `reference`, `popper`, a numeric `id`
 *   (the popper's id is `tippy-<id>`), `state`, `enable`/`disable` and a
 *   `popperInstance` whose `update` re-positions. WPML core reads `_tippy` to know
 *   whether an element is bound; Toolset Forms binds `onShow` after `initSingle`
 *   and decides there, from `tip.reference` and `tip.props.content`, whether to
 *   suppress the tooltip by setting `tip.popper.style.display` (the batch run of
 *   2026-11-06 caught its form wizard stalling without `set`);
 * - `OTGSUI.otgsPopoverTooltip.initSingle( element )` and `initialize()`.
 */

const HOOK = '.js-otgs-popover-tooltip, .js-wpml-popover-tooltip';
const PLACEMENTS = [ 'top', 'bottom', 'left', 'right' ];
const EDGE = 4; // px kept between the tooltip and the boundary
const HIDE_DELAY = 120; // ms to cross from the reference into an interactive tooltip

const CALLBACKS = [ 'onShow', 'onShown', 'onHide', 'onHidden', 'onMount' ];

const DEFAULTS = {
	placement: 'top',
	zIndex: 9999,
	distance: 10,
	maxWidth: 350,
	boundary: 'viewport',
	flip: true,
	interactive: true,
	sticky: true,
	arrow: true,
	theme: 'otgs',
};

/**
 * @param {Element} element
 * @param {string}  name   option name as written in `data-tippy-<name>` (any case)
 * @return {string|null}
 */
const readOption = ( element, name ) => {
	const value = element.getAttribute( 'data-tippy-' + name );
	return null === value ? null : value;
};

const toBool = ( value, fallback ) => ( null === value ? fallback : 'false' !== value && '0' !== value );
const toNumber = ( value, fallback ) => {
	if ( null === value ) {
		return fallback;
	}
	const n = parseFloat( value );
	return isNaN( n ) ? fallback : n;
};

/**
 * @param {Element} element
 * @return {object}
 */
const optionsOf = ( element ) => {
	const placement = ( readOption( element, 'placement' ) || DEFAULTS.placement ).toLowerCase();
	const maxWidth = readOption( element, 'maxWidth' );
	return {
		placement: PLACEMENTS.some( p => placement.indexOf( p ) === 0 ) ? placement : DEFAULTS.placement,
		zIndex: toNumber( readOption( element, 'zIndex' ), DEFAULTS.zIndex ),
		distance: toNumber( readOption( element, 'distance' ), DEFAULTS.distance ),
		maxWidth: null === maxWidth ? DEFAULTS.maxWidth + 'px' : ( /^\d+(\.\d+)?$/.test( maxWidth ) ? maxWidth + 'px' : maxWidth ),
		boundary: readOption( element, 'boundary' ) || DEFAULTS.boundary,
		flip: toBool( readOption( element, 'flip' ), DEFAULTS.flip ),
		interactive: toBool( readOption( element, 'interactive' ), DEFAULTS.interactive ),
		sticky: toBool( readOption( element, 'sticky' ), DEFAULTS.sticky ),
		arrow: toBool( readOption( element, 'arrow' ), DEFAULTS.arrow ),
		theme: readOption( element, 'theme' ) || DEFAULTS.theme,
	};
};

/**
 * The content, as tippy read it: `data-tippy-content` first, else the `title`,
 * which is then removed so the native tooltip stays away (and restored on destroy).
 *
 * @param {Element} element
 * @return {string}
 */
const contentOf = ( element ) => {
	const data = element.getAttribute( 'data-tippy-content' );
	if ( null !== data && '' !== data ) {
		return data;
	}
	const title = element.getAttribute( 'title' );
	if ( null !== title ) {
		element.setAttribute( 'data-original-title', title );
		element.removeAttribute( 'title' );
		return title;
	}
	return element.getAttribute( 'data-original-title' ) || '';
};

/**
 * @param {string} placement e.g. 'top', 'bottom-start', 'right-end'
 * @return {{base: string, align: string}}
 */
const splitPlacement = ( placement ) => {
	const [ base, align ] = placement.split( '-' );
	return { base, align: align || '' };
};

const OPPOSITE = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };

/**
 * Whether the element can take keyboard focus on its own (so the tooltip opens on
 * Tab without help). tippy 4's `a11y` default gave every other reference a
 * `tabindex="0"`; the products' `<i class="otgs-ico-help">` icons rely on that.
 *
 * @param {Element} element
 * @return {boolean}
 */
const isFocusable = ( element ) => {
	if ( element.hasAttribute( 'tabindex' ) || element.isContentEditable ) {
		return true;
	}
	const tag = element.tagName.toLowerCase();
	if ( 'a' === tag || 'area' === tag ) {
		return element.hasAttribute( 'href' );
	}
	return [ 'button', 'input', 'select', 'textarea', 'summary' ].includes( tag ) && ! element.disabled;
};

/**
 * The visible area the tooltip must stay in, in page coordinates.
 *
 * @return {{top: number, left: number, right: number, bottom: number}}
 */
const viewport = () => {
	const x = window.pageXOffset || document.documentElement.scrollLeft || 0;
	const y = window.pageYOffset || document.documentElement.scrollTop || 0;
	const w = document.documentElement.clientWidth || window.innerWidth;
	const h = document.documentElement.clientHeight || window.innerHeight;
	return { top: y, left: x, right: x + w, bottom: y + h };
};

/**
 * The reference's box in page coordinates.
 *
 * @param {Element} element
 */
const pageRect = ( element ) => {
	const r = element.getBoundingClientRect();
	const x = window.pageXOffset || document.documentElement.scrollLeft || 0;
	const y = window.pageYOffset || document.documentElement.scrollTop || 0;
	return { top: r.top + y, left: r.left + x, right: r.right + x, bottom: r.bottom + y, width: r.width, height: r.height };
};

class PopoverTooltip {
	/**
	 * @param {Element} reference
	 */
	constructor( reference ) {
		this.reference = reference;
		this.options = optionsOf( reference );
		this.props = this.options; // tippy 4's name for the same object
		this.state = { isVisible: false, isDestroyed: false, isEnabled: true };
		this.hideTimer = null;
		this.frame = null;
		PopoverTooltip.counter = ( PopoverTooltip.counter || 0 ) + 1;
		this.id = PopoverTooltip.counter;

		this.popper = document.createElement( 'div' );
		this.popper.className = 'tippy-popper';
		this.popper.id = 'tippy-' + this.id;
		this.popper.setAttribute( 'role', 'tooltip' );
		this.popper.setAttribute( 'data-state', 'hidden' );
		this.popper.style.position = 'absolute';
		this.popper.style.zIndex = String( this.options.zIndex );
		this.popper.style.maxWidth = this.options.maxWidth;

		this.tooltip = document.createElement( 'div' );
		this.tooltip.className = 'tippy-tooltip ' + this.options.theme + '-theme';
		this.tooltip.setAttribute( 'data-animation', 'fade' );
		if ( this.options.interactive ) {
			this.tooltip.setAttribute( 'data-interactive', '' );
		}
		this.popper.appendChild( this.tooltip );

		if ( this.options.arrow ) {
			this.arrow = document.createElement( 'div' );
			this.arrow.className = 'tippy-arrow';
			this.tooltip.appendChild( this.arrow );
		}

		this.content = document.createElement( 'div' );
		this.content.className = 'tippy-content';
		this.tooltip.appendChild( this.content );
		this.setContent( contentOf( reference ) );

		this.onEnter = () => this.show();
		this.onLeave = () => this.scheduleHide();
		this.onTooltipEnter = () => this.cancelHide();
		this.onTooltipLeave = () => this.scheduleHide();
		this.onKey = ( e ) => { if ( 'Escape' === e.key ) { this.hide(); } };

		this.addedTabindex = false;
		if ( ! isFocusable( reference ) ) {
			reference.setAttribute( 'tabindex', '0' );
			this.addedTabindex = true;
		}

		reference.addEventListener( 'mouseenter', this.onEnter );
		reference.addEventListener( 'focus', this.onEnter );
		reference.addEventListener( 'mouseleave', this.onLeave );
		reference.addEventListener( 'blur', this.onLeave );
		reference.addEventListener( 'keydown', this.onKey );
		if ( this.options.interactive ) {
			this.popper.addEventListener( 'mouseenter', this.onTooltipEnter );
			this.popper.addEventListener( 'mouseleave', this.onTooltipLeave );
		}

		this.popperInstance = {
			update: () => this.position(),
			scheduleUpdate: () => this.position(),
			forceUpdate: () => this.position(),
		};

		reference._tippy = this;
		this.popper._tippy = this;
	}

	/**
	 * @param {string} html the content, HTML like tippy 4 took it
	 */
	setContent( html ) {
		this.props.content = html;
		this.content.innerHTML = html;
	}

	/**
	 * tippy 4's `set( options )`: merges options into the instance. The callbacks
	 * (`onShow( instance )` and friends) are called as tippy 4 called them; an
	 * `onShow`/`onHide` returning `false` cancels. `content`, `zIndex`, `maxWidth`,
	 * `theme` and `interactive` are applied at once; placement, distance, flip and
	 * the rest are read when the tooltip is positioned.
	 *
	 * @param {Object} options
	 */
	set( options ) {
		Object.keys( options || {} ).forEach( ( key ) => {
			const value = options[ key ];
			if ( 'content' === key ) {
				this.setContent( value );
			} else if ( 'zIndex' === key ) {
				this.props.zIndex = value;
				this.popper.style.zIndex = String( value );
			} else if ( 'maxWidth' === key ) {
				this.props.maxWidth = 'number' === typeof value ? value + 'px' : value;
				this.popper.style.maxWidth = this.props.maxWidth;
			} else if ( 'theme' === key ) {
				this.props.theme = value;
				this.tooltip.className = 'tippy-tooltip ' + value + '-theme';
			} else if ( 'interactive' === key ) {
				this.props.interactive = !! value;
				this.popper.removeEventListener( 'mouseenter', this.onTooltipEnter );
				this.popper.removeEventListener( 'mouseleave', this.onTooltipLeave );
				if ( value ) {
					this.tooltip.setAttribute( 'data-interactive', '' );
					this.popper.addEventListener( 'mouseenter', this.onTooltipEnter );
					this.popper.addEventListener( 'mouseleave', this.onTooltipLeave );
				} else {
					this.tooltip.removeAttribute( 'data-interactive' );
				}
			} else {
				this.props[ key ] = value;
			}
		} );
	}

	/**
	 * tippy 5+'s name for `set`.
	 *
	 * @param {Object} options
	 */
	setProps( options ) {
		this.set( options );
	}

	enable() {
		this.state.isEnabled = true;
	}

	disable() {
		this.hide();
		this.state.isEnabled = false;
	}

	/**
	 * @param {string} name one of CALLBACKS
	 * @return {*} what the callback returned (`false` cancels show/hide)
	 */
	call( name ) {
		const fn = this.props[ name ];
		return 'function' === typeof fn ? fn( this ) : undefined;
	}

	show() {
		if ( this.state.isDestroyed || this.state.isVisible || ! this.state.isEnabled ) {
			this.cancelHide();
			return;
		}
		this.cancelHide();
		if ( false === this.call( 'onShow' ) ) {
			return;
		}
		this.state.isVisible = true;
		document.body.appendChild( this.popper );
		this.call( 'onMount' );
		this.position();
		this.popper.setAttribute( 'data-state', 'visible' );
		this.reference.setAttribute( 'aria-describedby', this.popper.id );
		this.call( 'onShown' );
		if ( this.options.sticky ) {
			const tick = () => {
				if ( ! this.state.isVisible ) {
					return;
				}
				this.position();
				this.frame = window.requestAnimationFrame ? window.requestAnimationFrame( tick ) : null;
			};
			this.frame = window.requestAnimationFrame ? window.requestAnimationFrame( tick ) : null;
		}
	}

	hide() {
		this.cancelHide();
		if ( ! this.state.isVisible ) {
			return;
		}
		if ( false === this.call( 'onHide' ) ) {
			return;
		}
		this.state.isVisible = false;
		if ( this.frame && window.cancelAnimationFrame ) {
			window.cancelAnimationFrame( this.frame );
		}
		this.frame = null;
		this.popper.setAttribute( 'data-state', 'hidden' );
		this.reference.removeAttribute( 'aria-describedby' );
		if ( this.popper.parentNode ) {
			this.popper.parentNode.removeChild( this.popper );
		}
		this.call( 'onHidden' );
	}

	scheduleHide() {
		this.cancelHide();
		this.hideTimer = setTimeout( () => this.hide(), this.options.interactive ? HIDE_DELAY : 0 );
	}

	cancelHide() {
		if ( this.hideTimer ) {
			clearTimeout( this.hideTimer );
			this.hideTimer = null;
		}
	}

	destroy() {
		this.hide();
		this.reference.removeEventListener( 'mouseenter', this.onEnter );
		this.reference.removeEventListener( 'focus', this.onEnter );
		this.reference.removeEventListener( 'mouseleave', this.onLeave );
		this.reference.removeEventListener( 'blur', this.onLeave );
		this.reference.removeEventListener( 'keydown', this.onKey );
		this.popper.removeEventListener( 'mouseenter', this.onTooltipEnter );
		this.popper.removeEventListener( 'mouseleave', this.onTooltipLeave );
		const title = this.reference.getAttribute( 'data-original-title' );
		if ( null !== title && ! this.reference.hasAttribute( 'title' ) ) {
			this.reference.setAttribute( 'title', title );
		}
		this.reference.removeAttribute( 'data-original-title' );
		if ( this.addedTabindex ) {
			this.reference.removeAttribute( 'tabindex' );
		}
		delete this.reference._tippy;
		this.state.isDestroyed = true;
	}

	/**
	 * Places the popper next to the reference, flipping to the opposite side when
	 * the preferred one has no room inside the boundary, and keeps it inside the
	 * boundary along the other axis. The arrow follows the reference's centre.
	 */
	position() {
		const ref = pageRect( this.reference );
		const box = viewport();
		const popW = this.popper.offsetWidth;
		const popH = this.popper.offsetHeight;
		const { align } = splitPlacement( this.options.placement );
		let { base } = splitPlacement( this.options.placement );
		const d = this.options.distance;

		const room = {
			top: ref.top - box.top,
			bottom: box.bottom - ref.bottom,
			left: ref.left - box.left,
			right: box.right - ref.right,
		};
		const need = ( 'top' === base || 'bottom' === base ) ? popH + d : popW + d;
		if ( this.options.flip && room[ base ] < need && room[ OPPOSITE[ base ] ] > room[ base ] ) {
			base = OPPOSITE[ base ];
		}

		let top;
		let left;
		if ( 'top' === base || 'bottom' === base ) {
			top = 'top' === base ? ref.top - popH - d : ref.bottom + d;
			if ( 'start' === align ) {
				left = ref.left;
			} else if ( 'end' === align ) {
				left = ref.right - popW;
			} else {
				left = ref.left + ref.width / 2 - popW / 2;
			}
			left = Math.max( box.left + EDGE, Math.min( left, box.right - popW - EDGE ) );
		} else {
			left = 'left' === base ? ref.left - popW - d : ref.right + d;
			if ( 'start' === align ) {
				top = ref.top;
			} else if ( 'end' === align ) {
				top = ref.bottom - popH;
			} else {
				top = ref.top + ref.height / 2 - popH / 2;
			}
			top = Math.max( box.top + EDGE, Math.min( top, box.bottom - popH - EDGE ) );
		}

		this.popper.style.top = Math.round( top ) + 'px';
		this.popper.style.left = Math.round( left ) + 'px';
		this.popper.setAttribute( 'x-placement', base + ( align ? '-' + align : '' ) );

		if ( this.arrow ) {
			if ( 'top' === base || 'bottom' === base ) {
				const centre = ref.left + ref.width / 2 - left;
				this.arrow.style.left = Math.round( Math.max( 8, Math.min( centre, popW - 8 ) ) ) + 'px';
				this.arrow.style.top = '';
			} else {
				const centre = ref.top + ref.height / 2 - top;
				this.arrow.style.top = Math.round( Math.max( 8, Math.min( centre, popH - 8 ) ) ) + 'px';
				this.arrow.style.left = '';
			}
		}
	}
}

/**
 * Binds one element. An element already bound keeps its instance.
 *
 * @param {Element} element
 * @return {PopoverTooltip}
 */
export function initSingle( element ) {
	if ( element._tippy ) {
		return element._tippy;
	}
	return new PopoverTooltip( element );
}

/**
 * Binds every hook element on the page.
 *
 * @return {PopoverTooltip[]}
 */
export function initialize() {
	// TODO change all .wpml-popover to otgs-popover
	return [ ...document.querySelectorAll( HOOK ) ].map( initSingle );
}

window.addEventListener( 'DOMContentLoaded', () => {
	initialize();
} );
