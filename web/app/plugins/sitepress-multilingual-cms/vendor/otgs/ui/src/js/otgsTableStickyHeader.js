/**
 * Sticky table headers (wpmldev-12550): CSS `position: sticky`, no library, no jQuery.
 *
 * A table with the class `js-otgs-table-sticky-header` keeps its `<thead>` cells
 * in view while the body scrolls under them, below WordPress's admin bar. The
 * styles are injected here so the one existing consumer (the Media Translation
 * table) keeps enqueueing the script alone, as before.
 */

const STYLE_ID = 'otgs-table-sticky-header-style';
const CSS =
	'.js-otgs-table-sticky-header thead th, .js-otgs-table-sticky-header thead td {' +
	' position: -webkit-sticky; position: sticky; top: var(--otgs-sticky-header-top, 0px);' +
	' z-index: 2; background-color: rgba(255,255,255,.8); }';

/**
 * The height of a fixed admin bar, which sticky cells must stop under; 0 when the
 * bar scrolls with the page (narrow screens) or is absent.
 *
 * @return {number}
 */
export function adminBarOffset() {
	const bar = document.getElementById( 'wpadminbar' );
	if ( ! bar ) {
		return 0;
	}
	const position = window.getComputedStyle ? window.getComputedStyle( bar ).position : 'fixed';
	return 'fixed' === position ? bar.offsetHeight : 0;
}

/**
 * @param {Element[]} tables
 */
export function applyOffset( tables ) {
	const offset = adminBarOffset() + 'px';
	tables.forEach( table => table.style.setProperty( '--otgs-sticky-header-top', offset ) );
}

export function initialize() {
	const tables = [ ...document.querySelectorAll( '.js-otgs-table-sticky-header' ) ];
	if ( ! tables.length ) {
		return tables;
	}
	if ( ! document.getElementById( STYLE_ID ) ) {
		const style = document.createElement( 'style' );
		style.id = STYLE_ID;
		style.textContent = CSS;
		document.head.appendChild( style );
	}
	applyOffset( tables );
	window.addEventListener( 'resize', () => applyOffset( tables ) );
	return tables;
}

window.addEventListener( 'DOMContentLoaded', () => {
	initialize();
} );
