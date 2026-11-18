<?php

namespace WPML\PB\BeaverBuilder\BeaverThemer;

use WPML\Convert\Ids;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class LocationHooks implements \IWPML_Backend_Action {

	const LAYOUT_CPT = 'fl-theme-layout';

	const LOCATIONS_RULES_KEY = '_fl_theme_builder_locations';

	const EXCLUSIONS_RULES_KEY = '_fl_theme_builder_exclusions';

	const BUILDER_DATA_KEY = \WPML_Beaver_Builder_Data_Settings::META_FIELD_KEY;

	const BUILDER_DATA_KEYS = [
		self::BUILDER_DATA_KEY,
		\WPML_Beaver_Builder_Data_Settings::META_FIELD_DRAFT_KEY,
	];

	const TERM_RULE_TYPES = [ 'wordpress/archive-term', 'wordpress/post-term' ];

	const POST_RULE_TYPES = [ 'wordpress/post', 'wordpress/post-parent' ];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_copy_meta_field', 10, 4 )
			->then( spreadArgs( [ $this, 'translateLocationRulesMeta' ] ) );

		Hooks::onAction( 'wpml_pro_translation_completed', 10, 3 )
			->then( spreadArgs( [ $this, 'translateConditionalLogic' ] ) );
	}

	public function translateLocationRulesMeta( $copiedValue, $translatedPostId, $originalPostId, $metaKey ) {
		if ( in_array( $metaKey, [ self::LOCATIONS_RULES_KEY, self::EXCLUSIONS_RULES_KEY ], true ) ) {
			$targetLang = self::getLayoutLanguage( $translatedPostId );

			foreach ( $copiedValue as &$rule ) {
				$rule = $this->translateRule( $rule, $targetLang );
			}
		}

		return $copiedValue;
	}

	public function translateConditionalLogic( $newPostId, $fields, $job ) {
		foreach ( self::BUILDER_DATA_KEYS as $builderDataKey ) {
			$this->convertSavedConditionalLogic( $newPostId, $builderDataKey, Obj::prop( 'language_code', $job ) );
		}
	}

	private function convertSavedConditionalLogic( $translatedPostId, $builderDataKey, $targetLang ) {
		$builderData = get_post_meta( $translatedPostId, $builderDataKey, true );
		if ( ! $builderData || ! is_array( $builderData ) ) {
			return;
		}

		$originalBuilderData = wp_json_encode( $builderData );
		$builderData         = $this->convertModulesConditionalLogic( $builderData, $targetLang );

		if ( wp_json_encode( $builderData ) !== $originalBuilderData ) {
			update_post_meta( $translatedPostId, $builderDataKey, $builderData );
		}
	}

	public function translateRebuiltConditionalLogic( array $builderData, $translatedPostId ) {
		return $this->convertModulesConditionalLogic( $builderData, self::getPostLanguage( $translatedPostId ) );
	}

	private function convertModulesConditionalLogic( array $builderData, $targetLang ) {
		foreach ( $builderData as $module ) {
			if ( is_object( $module ) && isset( $module->settings->visibility_logic ) ) {
				$module->settings->visibility_logic = $this->translateVisibilityLogic( $module->settings->visibility_logic, $targetLang );
			}
		}

		return $builderData;
	}

	private function translateVisibilityLogic( $visibilityLogic, $targetLang ) {
		foreach ( (array) $visibilityLogic as &$logicGroup ) {
			foreach ( (array) $logicGroup as &$rule ) {
				if ( is_object( $rule ) ) {
					$rule = $this->translateConditionalRule( $rule, $targetLang );
				}
			}
		}

		return $visibilityLogic;
	}

	private function translateConditionalRule( $rule, $targetLang ) {
		$ruleType = Obj::prop( 'type', $rule );

		if ( in_array( $ruleType, self::TERM_RULE_TYPES, true ) && isset( $rule->term, $rule->taxonomy ) ) {
			$rule->term = self::translateElement( $rule->term, $rule->taxonomy, $targetLang );
		} elseif ( in_array( $ruleType, self::POST_RULE_TYPES, true ) && isset( $rule->post ) ) {
			$postType = get_post_type( $rule->post );
			if ( $postType ) {
				$rule->post = self::translateElement( $rule->post, $postType, $targetLang );
			}
		}

		return $rule;
	}

	private function translateRule( $rule, $targetLangCode ) {
		$parts = explode( ':', $rule );

		if ( 3 === count( $parts ) ) {
			$rule = implode( ':', [ $parts[0], $parts[1], self::translateElement( $parts[2], $parts[1], $targetLangCode ) ] );
		} elseif ( 5 === count( $parts ) ) {
			$rule = implode( ':', [ $parts[0], $parts[1], $parts[2], $parts[3], self::translateElement( $parts[4], $parts[3], $targetLangCode ) ] );
		}

		return $rule;
	}

	private static function getLayoutLanguage( $translatedPostId ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $translatedPostId,
				'element_type' => self::LAYOUT_CPT,
			]
		);
	}

	private static function getPostLanguage( $postId ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => get_post_type( $postId ),
			]
		);
	}

	private static function translateElement( $elementId, $elementType, $targetLangCode ) {
		return Ids::convert( $elementId, $elementType, true, $targetLangCode );
	}
}
