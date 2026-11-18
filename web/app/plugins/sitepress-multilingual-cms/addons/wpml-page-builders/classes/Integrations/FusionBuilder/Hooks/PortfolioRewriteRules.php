<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\Utilities\RequestMemo;
use WPML\Utils\Unserialize;
use function WPML\FP\spreadArgs;

class PortfolioRewriteRules implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action {

	const PORTFOLIO_POST_TYPE = 'avada_portfolio';

	const SLUG_OPTION_KEY = 'portfolio_slug';

	const AVADA_DEFAULT_SLUG = 'portfolio-items';

	const PRIORITY_BEFORE_AVADA_FILTER = 0;

	const PRIORITY_AFTER_AVADA_FILTER = 2;

	private $memo;

	public function add_hooks() {
		Hooks::onFilter( 'rewrite_rules_array' )
			->then( spreadArgs( [ $this, 'storeRulesWithDefaultLanguageSlug' ] ) );

		add_filter( 'option_rewrite_rules', [ $this, 'readStoredRulesWithDefaultLanguageSlug' ], self::PRIORITY_BEFORE_AVADA_FILTER );

		Hooks::onFilter( 'option_rewrite_rules', self::PRIORITY_AFTER_AVADA_FILTER )
			->then( spreadArgs( [ $this, 'readRulesWithRegisteredSlug' ] ) );
	}

	public function storeRulesWithDefaultLanguageSlug( $rules ) {
		$registeredSlug = $this->getRegisteredSlug();

		if ( ! is_array( $rules ) || ! $registeredSlug || $this->isDefaultLanguageRequest() ) {
			return $rules;
		}

		$defaultSlug = $this->getDefaultLanguageSlug();

		if ( $registeredSlug === $defaultSlug ) {
			return $rules;
		}

		$rulePrefix = $this->getRulePrefix();

		return $this->replaceRuleBase( $rules, $rulePrefix . $registeredSlug . '/', $rulePrefix . $defaultSlug . '/' );
	}

	public function readStoredRulesWithDefaultLanguageSlug( $rules ) {
		$registeredSlug = $this->getRegisteredSlug();

		if ( ! is_array( $rules ) || ! $registeredSlug ) {
			return $rules;
		}

		return $this->getMemo()->rewriteRules(
			$rules,
			function ( $rules ) use ( $registeredSlug ) {
				$defaultSlug = $this->isDefaultLanguageRequest() ? $registeredSlug : $this->getDefaultLanguageOptionSlug();
				$rulePrefix  = $this->getRulePrefix();
				$storedBase  = $defaultSlug ? $this->getStoredBase( $rules, $rulePrefix, $defaultSlug ) : null;

				if ( null === $storedBase ) {
					return $rules;
				}

				return $this->replaceRuleBase( $rules, $rulePrefix . $storedBase . '/', $rulePrefix . $defaultSlug . '/' );
			},
			'stored|' . $registeredSlug,
			true
		);
	}

	public function readRulesWithRegisteredSlug( $rules ) {
		$registeredSlug = $this->getRegisteredSlug();

		if ( ! is_array( $rules ) || ! $registeredSlug ) {
			return $rules;
		}

		return $this->getMemo()->rewriteRules(
			$rules,
			function ( $rules ) use ( $registeredSlug ) {
				$defaultSlug = $this->getDefaultLanguageSlug();

				if ( $registeredSlug === $defaultSlug ) {
					return $rules;
				}

				$rulePrefix = $this->getRulePrefix();

				return $this->replaceRuleBase( $rules, $rulePrefix . $defaultSlug . '/', $rulePrefix . $registeredSlug . '/' );
			},
			$registeredSlug,
			true
		);
	}

	private function getMemo() {
		if ( null === $this->memo ) {
			$optionName = $this->getAvadaOptionName( apply_filters( 'wpml_default_language', null ) );
			$this->memo = new RequestMemo(
				array_merge( RequestMemo::REWRITE_RULES_WRITES, [ 'add_option_' . $optionName, 'update_option_' . $optionName ] )
			);
		}

		return $this->memo;
	}

	private function replaceRuleBase( $rules, $fromBase, $toBase ) {
		$replacedRules = [];

		foreach ( $rules as $match => $query ) {
			if ( 0 === strpos( $match, $fromBase ) ) {
				$replaced = $toBase . substr( $match, strlen( $fromBase ) );
				$match    = array_key_exists( $replaced, $rules ) ? $match : $replaced;
			}

			$replacedRules[ $match ] = $query;
		}

		return $replacedRules;
	}

	private function getStoredBase( $rules, $rulePrefix, $defaultSlug ) {
		$bases = [];

		foreach ( $rules as $match => $query ) {
			if ( ! is_string( $match ) || ! is_string( $query ) ) {
				continue;
			}

			$match = ltrim( $match, '^' );

			if (
				( '' !== $rulePrefix && 0 !== strpos( $match, $rulePrefix ) )
				|| ! preg_match( '/[?&](' . self::PORTFOLIO_POST_TYPE . '=|post_type=' . self::PORTFOLIO_POST_TYPE . '(&|$))/', $query )
			) {
				continue;
			}

			$pattern = substr( $match, strlen( $rulePrefix ) );
			$base    = rtrim( substr( $pattern, 0, strcspn( $pattern, '([?.*+\\' ) ), '/' );

			if ( '' !== $base ) {
				$bases[ $base ] = true;
			}
		}

		$bases = array_keys( $bases );
		$roots = array_filter(
			$bases,
			function ( $base ) use ( $bases ) {
				foreach ( $bases as $other ) {
					if ( 0 === strpos( $base, $other . '/' ) ) {
						return false;
					}
				}

				return true;
			}
		);

		if ( in_array( $defaultSlug, $roots, true ) ) {
			return null;
		}

		if ( 1 === count( $roots ) ) {
			return (string) reset( $roots );
		}

		return in_array( self::AVADA_DEFAULT_SLUG, $roots, true ) ? self::AVADA_DEFAULT_SLUG : null;
	}

	private function getRegisteredSlug() {
		$postType = get_post_type_object( self::PORTFOLIO_POST_TYPE );

		return $postType && ! empty( $postType->rewrite['slug'] ) ? $postType->rewrite['slug'] : null;
	}

	private function isDefaultLanguageRequest() {
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		return $currentLanguage && apply_filters( 'wpml_default_language', null ) === $currentLanguage;
	}

	private function getDefaultLanguageSlug() {
		return $this->getDefaultLanguageOptionSlug() ?: self::AVADA_DEFAULT_SLUG;
	}

	private function getDefaultLanguageOptionSlug() {
		return $this->getMemo()->remember(
			'defaultLanguageSlug',
			function () {
				$optionName = $this->getAvadaOptionName( apply_filters( 'wpml_default_language', null ) );
				$options    = Unserialize::maybe( apply_filters( 'wpml_unfiltered_admin_string', get_option( $optionName ), $optionName ) );

				return is_array( $options ) && ! empty( $options[ self::SLUG_OPTION_KEY ] ) ? (string) $options[ self::SLUG_OPTION_KEY ] : null;
			}
		);
	}

	private function getAvadaOptionName( $language ) {
		$baseOptionName = is_callable( [ \Fusion_Settings::class, 'get_original_option_name' ] )
			? \Fusion_Settings::get_original_option_name()
			: MultilingualOptions::OPTION_NAME;

		return MultilingualOptions::LANGUAGE_OF_UNSUFFIXED_OPTION === $language
			? $baseOptionName
			: $baseOptionName . '_' . $language;
	}

	private function getRulePrefix() {
		global $wp_rewrite;

		$postType = get_post_type_object( self::PORTFOLIO_POST_TYPE );

		return ! empty( $postType->rewrite['with_front'] ) && $wp_rewrite ? substr( $wp_rewrite->front, 1 ) : '';
	}
}
