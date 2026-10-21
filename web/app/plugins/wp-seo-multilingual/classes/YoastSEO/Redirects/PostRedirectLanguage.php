<?php

namespace WPML\WPSEO\YoastSEO\Redirects;

use WPML\Settings\LanguageNegotiation;
use WPML\WPSEO\Shared\Options;

class PostRedirectLanguage implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const KEY_RECORDED_REDIRECTS = 'post-redirect-languages';

	const POST_REDIRECT_META_KEY = '_yoast_post_redirect_info';

	const PLAIN_REDIRECTS_OPTION = 'wpseo-premium-redirects-export-plain';

	public function add_hooks() {
		if ( LanguageNegotiation::isDir() ) {
			return;
		}

		add_action( 'added_post_meta', [ $this, 'recordLanguage' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'recordLanguage' ], 10, 4 );
	}

	public function scopeFrontEndRedirectsToCurrentLanguage() {
		add_filter( 'option_' . self::PLAIN_REDIRECTS_OPTION, [ $this, 'removeOtherLanguageRedirects' ] );
		add_action( 'plugins_loaded', [ $this, 'stopFilteringAfterYoastReadsRedirects' ], PHP_INT_MAX );
	}

	public function recordLanguage( $metaId, $postId, $metaKey, $redirect ) {
		if ( self::POST_REDIRECT_META_KEY !== $metaKey || ! isset( $redirect['origin'], $redirect['target'] ) ) {
			return;
		}

		$language = $this->getPostLanguage( (int) $postId );
		if ( ! $language ) {
			return;
		}

		$recordedRedirects                        = $this->getRecordedRedirects();
		$recordedRedirects[ $redirect['origin'] ] = [
			'language' => $language,
			'target'   => $redirect['target'],
		];

		Options::set( self::KEY_RECORDED_REDIRECTS, $recordedRedirects );
	}

	public function stopFilteringAfterYoastReadsRedirects() {
		remove_filter( 'option_' . self::PLAIN_REDIRECTS_OPTION, [ $this, 'removeOtherLanguageRedirects' ] );
	}

	public function removeOtherLanguageRedirects( $redirects ) {
		$recordedRedirects = $this->getRecordedRedirects();
		if ( ! is_array( $redirects ) || ! $recordedRedirects ) {
			return $redirects;
		}

		$currentLanguage = (string) apply_filters( 'wpml_current_language', null );

		foreach ( $redirects as $origin => $redirect ) {
			if ( $this->belongsToOtherLanguage( $recordedRedirects, (string) $origin, $redirect, $currentLanguage ) ) {
				unset( $redirects[ $origin ] );
			}
		}

		return $redirects;
	}

	private function belongsToOtherLanguage( array $recordedRedirects, string $origin, $redirect, string $currentLanguage ): bool {
		if ( ! isset( $recordedRedirects[ $origin ], $redirect['url'] ) ) {
			return false;
		}

		return $recordedRedirects[ $origin ]['target'] === $redirect['url']
			&& $recordedRedirects[ $origin ]['language'] !== $currentLanguage;
	}

	private function getPostLanguage( int $postId ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => get_post_type( $postId ),
			]
		);
	}

	private function getRecordedRedirects(): array {
		$recordedRedirects = Options::get( self::KEY_RECORDED_REDIRECTS, [] );

		return is_array( $recordedRedirects ) ? $recordedRedirects : [];
	}
}
