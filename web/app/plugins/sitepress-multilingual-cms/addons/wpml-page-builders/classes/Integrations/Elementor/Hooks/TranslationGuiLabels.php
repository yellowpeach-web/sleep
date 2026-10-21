<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\Compatibility\BaseTranslationGuiLabels;

class TranslationGuiLabels extends BaseTranslationGuiLabels {

	const POST_TYPE_LANDING_PAGE      = 'e-landing-page';
	const POST_TYPE_FLOATING_ELEMENTS = 'e-floating-buttons';
	const POST_TYPE_TEMPLATE          = 'elementor_library';

	protected function getPostTypes() {
		return [
			self::POST_TYPE_LANDING_PAGE,
			self::POST_TYPE_FLOATING_ELEMENTS,
			self::POST_TYPE_TEMPLATE,
		];
	}

	protected function getFormat() {
		// Translators: %s: Post type label. For example, Elementor Landing Pages.
		return __( 'Elementor %s', 'sitepress' );
	}

	protected function formatLabel( $label, $name, $isPlural ) {
		if ( $name === self::POST_TYPE_TEMPLATE && $isPlural ) {
			/* translators: Name of the Elementor template content type, shown as the kind of content being translated in WPML's translation editor and job list. "Elementor" is a product name and stays in English. */
			return __( 'Elementor Templates', 'sitepress' );
		}
		return sprintf( $this->getFormat(), $label );
	}

}
