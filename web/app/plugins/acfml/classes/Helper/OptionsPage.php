<?php

namespace ACFML\Helper;

use WPML\API\Sanitize;
use WPML\FP\Obj;

class OptionsPage implements ContentType {

	const CPT         = 'acf-ui-options-page';
	const SCREEN_SLUG = 'acf-ui-options-page';
	const SYNC_OPTION = null;

	public function getInternalPostType() {
		return self::CPT;
	}

	public function getObjectSlug( $internalPostId ) {
		$internalPostContent = get_post_field( 'post_content', $internalPostId, 'raw' );
		$objectSettings      = maybe_unserialize( $internalPostContent );
		return Obj::propOr( null, 'menu_slug', $objectSettings );
	}

	public function getLabelTranslationsPackageSlug() {
		return \ACFML\Strings\Package::OPTION_PAGE_PACKAGE_KIND_SLUG;
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
		return '';
	}

	public function getLabelsTranslationInfoLabel() {
		/* translators: Row label in the Multilingual Setup panel, saying how the names of a post type, taxonomy or options page are translated. */
		return __( 'Labels Translation', 'acfml' );
	}

	public function getTranslationSettingsUrl() {
		return '';
	}

}
