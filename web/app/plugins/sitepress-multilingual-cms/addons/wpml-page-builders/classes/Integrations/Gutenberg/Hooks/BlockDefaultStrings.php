<?php

namespace WPML\PB\Gutenberg\Hooks;

use WPML\FP\Str;

class BlockDefaultStrings implements \WPML\PB\Gutenberg\Integration {

	const CORE_NAMESPACE    = 'core';
	const ACF_NAMESPACE     = 'acf';
	const SYSTEM_KEY_PREFIX = '_';

	const BLOCK_TYPES_ROUTE = '/wp/v2/block-types';

	const REGISTERED_OPTION = 'wpml_pb_block_default_strings';

	private $registered = [];

	private $registeredDefaults;

	private $newDefaults = [];

	private $memoAutoloaded = false;

	private $saveQueued = false;

	private $textRules;

	private $pending = [];

	private $translatesAtRender;

	public function add_hooks() {
		add_filter( 'block_type_metadata_settings', [ $this, 'translateDefaultStrings' ] );
		add_filter( 'render_block_data', [ $this, 'translateRenderedBlockDefaults' ], 10, 3 );
		add_filter( 'rest_pre_dispatch', [ $this, 'translateDefaultsForBlockTypesRoute' ], 10, 3 );
	}

	public function translateDefaultStrings( array $settings ): array {
		if (
			empty( $settings['name'] )
			|| ! is_string( $settings['name'] )
			|| empty( $settings['attributes'] )
			|| ! is_array( $settings['attributes'] )
			|| ! $this->isEligibleBlock( $settings['name'] )
		) {
			return $settings;
		}

		$blockName = $settings['name'];
		$atRender  = $this->translatesAtRender();

		foreach ( $settings['attributes'] as $key => &$attribute ) {
			$stringName = (string) $key;

			if ( '' === $stringName || $this->isSystemKey( $key ) || ! $this->isTranslatableDefault( $stringName, $attribute ) ) {
				continue;
			}

			$default = $attribute['default'];

			if ( $atRender ) {
				$this->pending[ $blockName ][ $stringName ] = $default;
				continue;
			}

			$this->registerString( $blockName, $stringName, $default );

			$attribute['default'] = $this->translate( $default, $blockName, $stringName );
		}
		unset( $attribute );

		return $settings;
	}

	public function translateRenderedBlockDefaults( $parsedBlock, $sourceBlock = null, $parentBlock = null ) {
		if ( ! $this->pending || ! is_array( $parsedBlock ) ) {
			return $parsedBlock;
		}

		if ( null === $parentBlock ) {
			$this->translateTypesInTree( $parsedBlock );
		} elseif ( isset( $parsedBlock['blockName'] ) && is_string( $parsedBlock['blockName'] ) ) {
			$this->translateBlockType( $parsedBlock['blockName'] );
		}

		return $parsedBlock;
	}

	public function translateDefaultsForBlockTypesRoute( $result, $server = null, $request = null ) {
		if (
			$this->pending
			&& is_object( $request )
			&& method_exists( $request, 'get_route' )
			&& Str::startsWith( self::BLOCK_TYPES_ROUTE, (string) $request->get_route() )
		) {
			foreach ( array_keys( $this->pending ) as $blockName ) {
				$this->translateBlockType( $blockName );
			}
		}

		return $result;
	}

	private function translateTypesInTree( array $parsedBlock ) {
		if ( isset( $parsedBlock['blockName'] ) && is_string( $parsedBlock['blockName'] ) ) {
			$this->translateBlockType( $parsedBlock['blockName'] );
		}
		if ( empty( $parsedBlock['innerBlocks'] ) || ! is_array( $parsedBlock['innerBlocks'] ) ) {
			return;
		}
		foreach ( $parsedBlock['innerBlocks'] as $innerBlock ) {
			if ( ! $this->pending ) {
				return;
			}
			if ( is_array( $innerBlock ) ) {
				$this->translateTypesInTree( $innerBlock );
			}
		}
	}

	private function translateBlockType( string $blockName ) {
		if ( ! isset( $this->pending[ $blockName ] ) ) {
			return;
		}
		$defaults = $this->pending[ $blockName ];
		unset( $this->pending[ $blockName ] );

		$blockType = $this->getRegisteredBlockType( $blockName );
		if ( ! $blockType || ! isset( $blockType->attributes ) || ! is_array( $blockType->attributes ) ) {
			return;
		}

		foreach ( $defaults as $stringName => $default ) {
			if (
				isset( $blockType->attributes[ $stringName ]['default'] )
				&& $default === $blockType->attributes[ $stringName ]['default']
				&& $this->isTranslatableDefault( (string) $stringName, $blockType->attributes[ $stringName ] )
			) {
				$blockType->attributes[ $stringName ]['default'] = $this->translate( $default, $blockName, $stringName );
			}
		}
	}

	protected function getRegisteredBlockType( string $blockName ) {
		return class_exists( '\WP_Block_Type_Registry' )
			? \WP_Block_Type_Registry::get_instance()->get_registered( $blockName )
			: null;
	}

	private function translatesAtRender(): bool {
		if ( null === $this->translatesAtRender ) {
			$this->translatesAtRender = ! is_admin();
		}

		return $this->translatesAtRender;
	}

	private function translate( string $default, string $blockName, string $stringName ) {
		return apply_filters( 'wpml_translate_single_string', $default, $blockName, $stringName );
	}

	private function isEligibleBlock( string $blockName ): bool {
		return ! Str::startsWith( self::CORE_NAMESPACE . '/', $blockName )
			&& ! Str::startsWith( self::ACF_NAMESPACE . '/', $blockName )
			&& ! ( function_exists( 'acf_has_block_type' ) && acf_has_block_type( $blockName ) );
	}

	private function isSystemKey( $key ): bool {
		return is_string( $key ) && Str::startsWith( self::SYSTEM_KEY_PREFIX, $key );
	}

	private function isTranslatableDefault( string $key, $attribute ): bool {
		return is_array( $attribute )
			&& isset( $attribute['type'], $attribute['default'] )
			&& $this->getTextRules()->isText( $key, $attribute['default'], $attribute );
	}

	private function getTextRules(): TextDefaultRules {
		if ( null === $this->textRules ) {
			$this->textRules = new TextDefaultRules();
		}

		return $this->textRules;
	}

	private function registerString( string $blockName, string $stringName, string $value ): void {
		if ( ! is_admin() ) {
			return;
		}

		$key = $blockName . '/' . $stringName;
		if ( isset( $this->registered[ $key ] ) ) {
			return;
		}
		$this->registered[ $key ] = true;

		$hash       = md5( $blockName . "\0" . $stringName . "\0" . $value );
		$registered = $this->getRegisteredDefaults();
		if ( isset( $registered[ $key ] ) && $hash === $registered[ $key ] ) {
			return;
		}

		do_action( 'wpml_register_single_string', $blockName, $stringName, $value );

		$this->registeredDefaults[ $key ] = $hash;
		$this->newDefaults[ $key ]        = $hash;
		$this->queueSave();
	}

	public function saveRegisteredDefaults() {
		if ( $this->newDefaults ) {
			update_option( self::REGISTERED_OPTION, array_merge( $this->readStoredDefaults(), $this->newDefaults ), false );
			$this->newDefaults = [];
		} elseif ( $this->memoAutoloaded && function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::REGISTERED_OPTION, false );
		}

		$this->memoAutoloaded = false;
	}

	private function getRegisteredDefaults(): array {
		if ( null === $this->registeredDefaults ) {
			$stored                   = get_option( self::REGISTERED_OPTION, [] );
			$this->registeredDefaults = is_array( $stored ) ? $stored : [];
			$this->memoAutoloaded     = array_key_exists( self::REGISTERED_OPTION, (array) wp_load_alloptions() );
			if ( $this->memoAutoloaded ) {
				$this->queueSave();
			}
		}

		return $this->registeredDefaults;
	}

	private function readStoredDefaults(): array {
		wp_cache_delete( self::REGISTERED_OPTION, 'options' );
		$notOptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notOptions ) && isset( $notOptions[ self::REGISTERED_OPTION ] ) ) {
			unset( $notOptions[ self::REGISTERED_OPTION ] );
			wp_cache_set( 'notoptions', $notOptions, 'options' );
		}

		$stored = get_option( self::REGISTERED_OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	private function queueSave() {
		if ( ! $this->saveQueued ) {
			$this->saveQueued = true;
			add_action( 'shutdown', [ $this, 'saveRegisteredDefaults' ] );
		}
	}
}
