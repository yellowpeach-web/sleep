<?php

namespace WPML\WPSEO\RankMathSEO\Slugs;

use WPML\LIB\WP\Hooks as WpHooks;
use WPML\WPSEO\RankMathSEO\Utils;

class Hooks implements \IWPML_Action {

	const FEATURES = [
		[ 'strip_category_base', 'taxonomy', 'category' ],
		[ Utils::REMOVE_PRODUCT_BASE_KEY, 'post', 'product' ],
		[ 'wc_remove_category_base', 'taxonomy', 'product_cat' ],
	];

	private $slugTranslationSettingsFactory;

	public function __construct( \WPML_ST_Slug_Translation_Settings_Factory $slugTranslationSettingsFactory ) {
		$this->slugTranslationSettingsFactory = $slugTranslationSettingsFactory;
	}

	public function add_hooks() {
		WpHooks::onAction( 'admin_init' )
			->then( [ $this, 'ensureStrippedBasesAreNotTranslated' ] );
	}

	public function ensureStrippedBasesAreNotTranslated() {
		foreach ( self::FEATURES as $featureData ) {
			list( $featureKey, $type, $slug ) = $featureData;

			if ( Utils::isGeneralSettingOn( $featureKey ) ) {

				$slugSettings = $this->slugTranslationSettingsFactory->create( $type );

				if ( $slugSettings->is_translated( $slug ) ) {
					$slugSettings->set_type( $slug, false );
					$slugSettings->save();
				}
			}
		}
	}
}
