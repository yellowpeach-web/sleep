<?php

namespace ACFML\Helper;

use WPML\API\Sanitize;
use WPML\FP\Obj;

class Taxonomy implements ContentType {

	const CPT         = 'acf-taxonomy';
	const SCREEN_SLUG = 'acf-taxonomy';
	const SYNC_OPTION = 'taxonomies_sync_option';

	public function getInternalPostType() {
		return self::CPT;
	}

	public function getObjectSlug( $internalPostId ) {
		$internalPostContent = get_post_field( 'post_content', $internalPostId, 'raw' );
		$objectSettings      = maybe_unserialize( $internalPostContent );
		return Obj::propOr( null, 'taxonomy', $objectSettings );
	}

	public function getLabelTranslationsPackageSlug() {
		return \ACFML\Strings\Package::TAXONOMY_PACKAGE_KIND_SLUG;
	}

	public function getWpmlSyncOptionKey() {
		return self::SYNC_OPTION;
	}

	public function getEditorScreenSlug() {
		return self::SCREEN_SLUG;
	}

	public function isEditorScreen() {
		return function_exists( 'acf_is_screen' ) && acf_is_screen( self::SCREEN_SLUG );
	}

	public function isListingScreen() {
		global $pagenow;

		return 'edit.php' === $pagenow
			&& self::CPT === Sanitize::stringProp( 'post_type', $_GET );
	}

	public function getTranslationInfoLabel() {
		/* translators: Name of the WPML screen that decides which taxonomies are translated, used inside the panel that links to it. */
		return __( 'Taxonomy Translation', 'acfml' );
	}

	public function getLabelsTranslationInfoLabel() {
		/* translators: Row label in the Multilingual Setup panel, saying how the names of a post type, taxonomy or options page are translated. */
		return __( 'Labels Translation', 'acfml' );
	}

	public function getTranslationSettingsUrl() {
		return admin_url( 'admin.php?page=tm%2Fmenu%2Fsettings&section=taxonomies' );
	}

}
