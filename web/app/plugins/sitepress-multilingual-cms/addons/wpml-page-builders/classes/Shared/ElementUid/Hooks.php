<?php

namespace WPML\PB\ElementUid;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const PRIORITY_SHUTDOWN_FLUSH = 25;

	private $capture;

	private $shortcodeCapture;

	private $fieldDecorator;

	private $timestampsFilter;

	private $markupBuilder;

	public function __construct(
		Capture $capture,
		ShortcodeCapture $shortcodeCapture,
		FieldDecorator $fieldDecorator,
		TimestampsFilter $timestampsFilter
	) {
		$this->capture          = $capture;
		$this->shortcodeCapture = $shortcodeCapture;
		$this->fieldDecorator   = $fieldDecorator;
		$this->timestampsFilter = $timestampsFilter;
	}

	public function add_hooks() {
		add_action( 'pre_post_update', [ Registry::class, 'onPrePostUpdate' ], 10, 2 );

		add_action( 'wpml_start_string_package_registration', [ $this->capture, 'onStart' ] );
		add_action( 'wpml_pb_register_strings_for_post', [ $this->capture, 'onWalk' ] );
		add_action( 'wpml_pb_register_string_for_node', [ $this->capture, 'onString' ], 10, 4 );
		add_action( 'wpml_delete_unused_package_strings', [ $this->capture, 'onEnd' ] );

		add_action( 'wpml_pb_register_shortcode_string', [ $this->shortcodeCapture, 'onString' ], 10, 3 );
		add_action( 'wpml_pb_after_register_shortcode_strings', [ $this->shortcodeCapture, 'flush' ] );
		add_action( 'shutdown', [ $this->shortcodeCapture, 'flush' ], self::PRIORITY_SHUTDOWN_FLUSH );

		add_filter( 'wpml_tm_adjust_translation_fields', [ $this->fieldDecorator, 'addUidToFields' ], 10, 2 );
		add_filter( 'wpml_pb_block_uid_timestamps', [ $this->timestampsFilter, 'get' ], 10, 2 );

		add_filter( 'wpml_resolve_custom_field_preferences', [ $this, 'resolveOwnMetaPreferences' ], 10, 3 );
		add_filter( 'wpml_duplicate_custom_fields_exceptions', [ $this, 'excludeOwnMetasFromDuplication' ] );

		add_filter( 'wpml_pb_element_uid_markup', [ $this, 'getElementMarkup' ], 10, 2 );
		add_action( 'save_post', [ $this, 'refreshTranslationRegistry' ], 30 );
		add_action( 'updated_post_meta', [ $this, 'onBuilderMetaSaved' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'onBuilderMetaSaved' ], 10, 4 );
	}

	public function resolveOwnMetaPreferences( $preferences, $metaKeys, $elementType = 'post' ) {
		if ( ! is_array( $preferences ) || 'post' !== $elementType ) {
			return $preferences;
		}

		$ours = [ Registry::META_KEY, NameMap::META_KEY ];

		foreach ( $metaKeys as $metaKey ) {
			if ( in_array( $metaKey, $ours, true ) ) {
				$preferences[ $metaKey ] = 0;
			}
		}

		return $preferences;
	}

	public function excludeOwnMetasFromDuplication( $exceptions ) {
		if ( ! is_array( $exceptions ) ) {
			return $exceptions;
		}

		return array_merge( $exceptions, [ Registry::META_KEY, NameMap::META_KEY ] );
	}

	public function getElementMarkup( $markup, $postId ) {
		return $this->getMarkupBuilder()->getForFilter( $markup, $postId );
	}

	public function refreshTranslationRegistry( $postId ) {
		$this->getMarkupBuilder()->refreshRegistry( (int) $postId );
	}

	public function onBuilderMetaSaved( $metaId, $postId, $metaKey, $metaValue = null ) {
		$builderDataKeys = [
			\WPML_Elementor_Data_Settings::META_KEY_DATA,
			\WPML_Beaver_Builder_Data_Settings::META_FIELD_KEY,
			'panels_data',
			'_cornerstone_data',
		];

		if ( in_array( $metaKey, $builderDataKeys, true ) && ! self::isEmptyLayout( $metaValue ) ) {
			Registry::onBuilderContentChanged( (int) $postId );
			$this->getMarkupBuilder()->refreshRegistry( (int) $postId, time() );
		}
	}

	private static function isEmptyLayout( $metaValue ) {
		if ( is_array( $metaValue ) ) {
			return [] === $metaValue;
		}

		return is_string( $metaValue ) && in_array( trim( $metaValue ), [ '', '[]' ], true );
	}

	protected function getMarkupBuilder() {
		if ( null === $this->markupBuilder ) {
			$providers = apply_filters(
				'wpml_pb_element_uid_markup_providers',
				[
					new Markup\ElementorProvider(),
					new Markup\BeaverBuilderProvider(),
					new Markup\SiteOriginProvider(),
					new Markup\CornerstoneProvider(),
				]
			);

			$this->markupBuilder = new Markup\MarkupBuilder( new Registry(), $providers );
		}

		return $this->markupBuilder;
	}
}
