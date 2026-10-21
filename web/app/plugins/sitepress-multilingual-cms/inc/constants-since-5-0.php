<?php
/**
 * Constants first defined in 5.0, inside files that already existed in
 * 4.9.x (inc/cache.php, inc/constants.php) - split out here so an in-place
 * 4.9.x -> 5.0 upgrade through the Installer can rely on them even in the
 * one request that performs the upgrade (wpmldev-8638).
 *
 * THE MECHANISM. That request already booted 4.9.x when it started: its
 * objects, its hooks and its Composer loader stay in memory while WordPress
 * deletes the plugin folder and extracts the 5.0 tree in place, then - for
 * an active plugin - re-activates it in the SAME PHP process. `require_once`
 * is deduplicated by file path, not by content, so a file the 4.9.x process
 * already loaded before the swap - inc/cache.php, inc/constants.php, both
 * required unconditionally near plugin boot - keeps running the OLD body it
 * already parsed for the rest of that one request, even though the bytes on
 * disk are now 5.0's. Any constant the 5.0 version of one of those files
 * defines, that the 4.9.x version did not, is therefore simply undefined for
 * the rest of that request: PHP 7.x logs "Use of undefined constant" and
 * treats the bare token as its own name (a wrong option key, here); PHP 8.x
 * throws an Error. Measured on this exact pairing (4.9.8 + the 5.0 package):
 * `WPML_LANGUAGE_DETAILS_CACHE_OPTION` and `WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX`,
 * referenced from classes/language/ActiveLanguagesReadModel.php - itself a
 * file new in 5.0, so it loads fresh with the 5.0 bytes, but the constants
 * its own `new \icl_cache(...)` call depends on come from inc/cache.php,
 * which is still running the OLD 4.9.x body in that same request.
 *
 * THE FIX. Every constant in this position is defined HERE ONCE, guarded
 * against a redundant define(), and inc/cache.php / inc/constants.php each
 * `require_once` this file at the exact point they used to define these
 * constants directly - so an ordinary (non-straddling) 5.0 boot sees the
 * same constants, the same values, in the same effective order as before;
 * nothing about it changes. Every 5.0 file that references one of these
 * constants ALSO `require_once`s this file directly, near the top, before
 * first use. That second require_once is never a no-op during the straddle:
 * this file did not exist before 5.0, so there is no old copy of it already
 * loaded to deduplicate against - PHP loads it, and the constant becomes
 * defined, regardless of what state the rest of the process is in. That is
 * what makes the straddle safe: the constant no longer depends on which
 * copy of cache.php/constants.php happened to already be in memory.
 *
 * `build/legacy-loader-bridge.php --check` guards this family the same way
 * it guards the class-resolution and constructor-drift families next to it:
 * a constant that belongs here but is referenced unguarded from a file that
 * does not require_once this one fails packaging.
 *
 * Deliberately NOT here: `CDT_QA_API_URL` (inc/constants.php) is also new in
 * 5.0, but every reference to it inside this repository already goes through
 * `defined( 'CDT_QA_API_URL' )` - its only unguarded reader is the Installer
 * package (a separate repository) - so there is nothing in this tree for the
 * bridge to protect. `WPML_ORG_ORIGIN` is not defined by WPML at all in
 * either version (it is an opt-in wp-config override, always consulted
 * through `defined( 'WPML_ORG_ORIGIN' )` in WpmlOrgOrigin::configured()), so
 * it cannot raise "Undefined constant" by construction. `WPML_TM_FOLDER` is
 * also new to inc/constants.php in 5.0, but that is a deliberate, unrelated,
 * already-guarded fix (wpmldev-8159/8160) for a Blog-license boot order
 * problem that exists on 4.9.x alone, independent of any straddle - moving
 * it here is out of scope for this ticket.
 */

if ( ! defined( 'WPML_LANGUAGE_DETAILS_CACHE_OPTION' ) ) {
	define( 'WPML_LANGUAGE_DETAILS_CACHE_OPTION', '_icl_cache_language_details' );
}

if ( ! defined( 'WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION' ) ) {
	define( 'WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION', '_icl_cache_language_details_epoch' );
}

if ( ! defined( 'WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX' ) ) {
	define( 'WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX', '_icl_cache_language_names_' );
}

if ( ! defined( 'ICL_TM_ATE_UNSOLVABLE' ) ) {
	define( 'ICL_TM_ATE_UNSOLVABLE', 41 );
}

if ( ! defined( 'WPML_POST_META_SOURCE_SETTING_INDEX' ) ) {
	define( 'WPML_POST_META_SOURCE_SETTING_INDEX', 'custom_fields_readonly_config_source' );
}

if ( ! defined( 'WPML_TERM_META_SOURCE_SETTING_INDEX' ) ) {
	define( 'WPML_TERM_META_SOURCE_SETTING_INDEX', 'custom_term_fields_readonly_config_source' );
}
