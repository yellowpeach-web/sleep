<?php

namespace WPML\Posts;

use WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest\SaveFlags;

/**
 * The block editor's meta-box loader leg, and whether it is the leg of a REST
 * "minor edit" (wpmldev-8629, S5/S18; P1-1 of the M2 review, decoupled in the
 * re-review).
 *
 * The block editor saves a post in two requests whenever a meta box without
 * `__back_compat_meta_box` is registered: the REST save
 * (POST /wp/v2/<type>/<id>), then - about a second later - a form POST to
 * `post.php?action=edit&meta-box-loader=1` whose hidden action is `editpost`
 * (core builds the URL in edit-form-blocks.php). That second request runs
 * `edit_post()` -> `wp_update_post()` -> `save_post` a second time, so every
 * save-time reader sees the same client save twice. WPML's own Language box
 * causes the leg until M5+M6; any third-party box causes it after that.
 *
 * Three readers ask about the leg, for two reasons:
 * - the Translate Everything dispatch (`Actions::sendToTranslation()`)
 *   decides on the leg which of the two requests sends the post, once
 *   (wpmldev-11379): the leg sends when its REST save left a pending marker
 *   for it ({@see \WPML\TM\AutomaticTranslation\Actions\PendingMetaBoxSave}),
 *   because the leg is where a third-party box (ACF, Yoast) writes its
 *   fields and the job must carry them as saved; a leg without a marker is
 *   skipped unless it changed the language assignment, because its REST save
 *   already sent before its response (or carried no flag - an editor tab
 *   still running older JavaScript) and a second run would cancel the
 *   in-flight job and create a second ATE generation;
 * - the TM reader, the Blog-licence listener and TEA ask whether the leg
 *   FOLLOWS A REST MINOR EDIT: that save carried the tick as its
 *   `wpml_minor_edit` REST arg, the leg carries no `icl_minor_edit` of its
 *   own, and a minor edit means no marking and no dispatch on either request.
 *   The REST save left a per-post marker ({@see SaveFlags::consumeMinorEditForLeg()});
 *   the leg consumes it here.
 *
 * This class is the ONE place for the leg predicate, so the TM and Blog-licence
 * readers do not depend on the Translate-Everything `Actions` class for a
 * TM-only concern; `Actions::isBlockEditorMetaBoxSave()` delegates here.
 *
 * NAMESPACED SINCE the wpmldev-8629 loader-bridge red (pattern of wpmldev-8638,
 * {@see \WPML\Language\CacheKeyClassifier}). Global (`WPML_Meta_Box_Loader_Leg`)
 * until then: a global class new since 4.9.x has no PSR-4 rule for the
 * pre-upgrade Composer loader to resolve it with, so the 4.9.x loader bridge
 * (`build/legacy-loader-bridge.php --check`) refuses it unless it is a reviewed
 * entry of `build/legacy-loader-bridge-known-globals.json`; under `WPML\` the
 * bridge carries it itself. `WPML\Posts` because the class is the one leg
 * predicate of a POST save, read by the TM, Blog-licence and TEA readers of
 * that save, and the namespace this directory's other classes already use. The
 * alias below keeps the old name working for any caller not updated.
 */
class MetaBoxLoaderLeg {

	public static function is_request() {
		if ( ! isset( $_GET['meta-box-loader'], $_GET['meta-box-loader-nonce'], $_POST['action'] ) ) {
			return false;
		}

		$nonce = sanitize_key( wp_unslash( $_GET['meta-box-loader-nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'meta-box-loader' ) ) {
			return false;
		}

		return 'editpost' === sanitize_key( wp_unslash( $_POST['action'] ) );
	}

	/**
	 * Whether this request is the meta-box loader leg of a REST "minor edit" of
	 * the post - and consumes one count of the marker that save left, once per
	 * request, so the TM, Blog-licence and TEA readers of the same leg agree.
	 * A WP-CLI, cron, quick-edit or classic save of the same post is never the
	 * leg and never consumes anything.
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public static function is_minor_edit_leg( $post_id ) {
		return self::is_request() && SaveFlags::consumeMinorEditForLeg( (int) $post_id );
	}
}

class_alias( MetaBoxLoaderLeg::class, 'WPML_Meta_Box_Loader_Leg' );
