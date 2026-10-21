<?php

namespace WPML\ST\SlugTranslation\Hooks;

class Hooks {
	const REGISTRATIONS = [
		'registered_post_type' => 'onRegisteredPostType',
		'registered_taxonomy'  => 'onRegisteredTaxonomy',
	];

	private $factory;

	private $slug_translation_settings;

	private $settings_factory;

	private $post_settings;

	private $tax_settings;

	private $memo;

	public function __construct(
		\WPML_Rewrite_Rule_Filter_Factory $factory,
		\WPML_ST_Slug_Translation_Settings $slug_translation_settings,
		?\WPML_ST_Slug_Translation_Settings_Factory $settings_factory = null
	) {
		$this->factory                   = $factory;
		$this->slug_translation_settings = $slug_translation_settings;
		$this->settings_factory          = $settings_factory ?: new \WPML_ST_Slug_Translation_Settings_Factory();
	}


	public function add_hooks() {
		add_action( 'init', [ $this, 'init' ], \WPML_Slug_Translation_Factory::INIT_PRIORITY );
	}

	public function init() {
		if ( $this->slug_translation_settings->is_enabled() ) {
			add_filter( 'option_rewrite_rules', [ $this, 'filter' ], 1, 1 );
			add_filter( 'rewrite_rules_array', [ $this, 'normalizeGeneratedRules' ], PHP_INT_MAX, 1 );
			add_filter( 'flush_rewrite_rules_hard', [ $this, 'flushRewriteRulesHard' ] );
			foreach ( self::REGISTRATIONS as $action => $method ) {
				add_action( $action, [ $this, $method ] );
			}
		}
	}

	public function filter( $value ) {
		if ( empty( $value ) || apply_filters( 'wpml_st_disable_rewrite_rules', false ) ) {
			return $value;
		}

		if ( null === $this->memo && class_exists( '\WPML\Utilities\RequestMemo' ) ) {
			$this->memo = \WPML\Utilities\RequestMemo::forRewriteRules( array_keys( self::REGISTRATIONS ) );
		}

		if ( $this->memo ) {
			return $this->memo->rewriteRules(
				$value,
				function ( $value ) {
					return $this->factory->create()->rewrite_rules_filter( $value );
				},
				'',
				true
			);
		}

		return $this->factory->create()->rewrite_rules_filter( $value );
	}

	public function normalizeGeneratedRules( $rules ) {
		if ( empty( $rules ) || apply_filters( 'wpml_st_disable_rewrite_rules', false ) ) {
			return $rules;
		}

		return $this->factory->create()->normalize_rules( $rules );
	}

	public function clearCache() {
		if ( $this->memo ) {
			$this->memo->forget();
		}
	}

	public function onRegisteredPostType( $post_type ) {
		if ( ! $this->memo ) {
			return;
		}

		if ( ! $this->post_settings ) {
			$this->post_settings = $this->settings_factory->create( \WPML_Slug_Translation_Factory::POST );
		}

		if ( $this->post_settings->is_translated( $post_type ) ) {
			$this->memo->forget();
		}
	}

	public function onRegisteredTaxonomy( $taxonomy ) {
		if ( ! $this->memo ) {
			return;
		}

		if ( ! $this->tax_settings ) {
			$this->tax_settings = $this->settings_factory->createTaxSettings();
		}

		if ( $this->tax_settings->is_translated( $taxonomy ) ) {
			$this->memo->forget();
		}
	}

	public function flushRewriteRulesHard( $hard ) {
		$this->clearCache();

		return $hard;
	}
}
