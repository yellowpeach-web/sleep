<?php

namespace ACFML\Convertable;

abstract class AbstractUrlConvertable implements \WPML_ACF_Convertable {

	protected const RESOLUTION_STATE_RESOLVED  = 'resolved';
	protected const RESOLUTION_STATE_UNCHANGED = 'unchanged';
	protected const RESOLUTION_STATE_DEFERRED  = 'deferred';

	protected function resolveTargetLang( \WPML_ACF_Field $acf_field ) {
		if ( '' !== $acf_field->target_lang ) {
			return $acf_field->target_lang;
		}
		if ( isset( $_POST['lang'] ) ) {
			return sanitize_key( wp_unslash( $_POST['lang'] ) );
		}
		return null;
	}

	protected function translateUrl( $url, $targetLang ) {
		$resolution = $this->resolveUrl( $url, $targetLang );

		if ( self::RESOLUTION_STATE_DEFERRED === $resolution['resolution_state'] ) {
			return $url;
		}

		return $resolution['url'];
	}

	protected function resolveUrl( $url, $targetLang ) {
		$result = [
			'url'                => $url,
			'source_post_id'     => null,
			'translated_post_id' => null,
			'resolution_state'   => self::RESOLUTION_STATE_UNCHANGED,
		];

		if ( ! is_string( $url ) || '' === $url || empty( $targetLang ) ) {
			return $result;
		}

		$sourceLang = apply_filters( 'wpml_default_language', null );
		$resolution = apply_filters(
			'wpml_resolve_single_url',
			null,
			$url,
			$targetLang,
			$sourceLang
		);

		if ( $this->isDeferredResolution( $resolution ) ) {
			$result['resolution_state'] = self::RESOLUTION_STATE_DEFERRED;
			return $result;
		}

		if ( $this->isValidResolution( $resolution ) ) {
			return [
				'url'                => $resolution['url'],
				'source_post_id'     => $resolution['source_post_id']
					? (int) $resolution['source_post_id']
					: null,
				'translated_post_id' => $resolution['translated_post_id']
					? (int) $resolution['translated_post_id']
					: null,
				'resolution_state'   => isset( $resolution['resolution_state'] )
					? $resolution['resolution_state']
					: $this->inferResolutionState( $url, $resolution['url'] ),
			];
		}

		return $this->resolveUrlLegacy( $url, $targetLang );
	}

	private function isDeferredResolution( $resolution ) {
		return is_array( $resolution )
			&& isset( $resolution['resolution_state'] )
			&& self::RESOLUTION_STATE_DEFERRED === $resolution['resolution_state'];
	}

	private function isValidResolution( $resolution ) {
		return is_array( $resolution )
			&& array_key_exists( 'url', $resolution )
			&& is_string( $resolution['url'] )
			&& array_key_exists( 'source_post_id', $resolution )
			&& $this->isValidOptionalPostId( $resolution['source_post_id'] )
			&& array_key_exists( 'translated_post_id', $resolution )
			&& $this->isValidOptionalPostId( $resolution['translated_post_id'] )
			&& (
				! array_key_exists( 'resolution_state', $resolution )
				|| in_array(
					$resolution['resolution_state'],
					[ self::RESOLUTION_STATE_RESOLVED, self::RESOLUTION_STATE_UNCHANGED ],
					true
				)
			);
	}

	private function isValidOptionalPostId( $postId ) {
		if ( null === $postId ) {
			return true;
		}

		return false !== filter_var(
			$postId,
			FILTER_VALIDATE_INT,
			[ 'options' => [ 'min_range' => 1 ] ]
		);
	}

	protected function resolvePostIdsLegacy( $url, $targetLang ) {
		$sourceId = $this->isStaticFrontPage( $url )
			? (int) get_option( 'page_on_front' )
			: $this->resolvePostId( $url );

		return [
			'source_post_id'     => $sourceId ?: null,
			'translated_post_id' => $sourceId
				? $this->translatePostId( $sourceId, $targetLang )
				: null,
		];
	}

	private function resolveUrlLegacy( $url, $targetLang ) {
		$result = [
			'url'                => $url,
			'source_post_id'     => null,
			'translated_post_id' => null,
			'resolution_state'   => self::RESOLUTION_STATE_UNCHANGED,
		];

		if ( $this->isStaticFrontPage( $url ) ) {
			$sourceId                     = (int) get_option( 'page_on_front' );
			$result['source_post_id']     = $sourceId ?: null;
			$result['translated_post_id'] = $sourceId
				? $this->translatePostId( $sourceId, $targetLang )
				: null;
			$result['url']                = apply_filters( 'wpml_permalink', $url, $targetLang );

			$result['resolution_state'] = $this->inferResolutionState( $url, $result['url'] );
			return $result;
		}

		$postId = $this->resolvePostId( $url );
		if ( $postId ) {
			$translatedId = $this->translatePostId( $postId, $targetLang );

			$result['source_post_id']     = $postId;
			$result['translated_post_id'] = $translatedId;
			if ( $translatedId ) {
				$scope = $this->openLanguageScope( $targetLang );
				try {
					$permalink = get_permalink( $translatedId );
				} finally {
					$this->closeLanguageScope( $scope );
				}

				if ( is_string( $permalink ) && '' !== $permalink ) {
					$result['url'] = $permalink;
				}
			}

			$result['resolution_state'] = $this->inferResolutionState( $url, $result['url'] );
			return $result;
		}

		$result['url']              = apply_filters( 'wpml_permalink', $url, $targetLang, true );
		$result['resolution_state'] = $this->inferResolutionState( $url, $result['url'] );
		return $result;
	}

	private function inferResolutionState( $sourceUrl, $resolvedUrl ) {
		return $sourceUrl === $resolvedUrl
			? self::RESOLUTION_STATE_UNCHANGED
			: self::RESOLUTION_STATE_RESOLVED;
	}

	private function translatePostId( $sourceId, $targetLang ) {
		$translatedId = apply_filters(
			'wpml_object_id',
			$sourceId,
			get_post_type( $sourceId ),
			false,
			$targetLang
		);

		return $translatedId ? (int) $translatedId : null;
	}

	private function resolvePostId( $url ) {
		$scope = $this->openLanguageScope( apply_filters( 'wpml_default_language', null ) );
		try {
			$postId = function_exists( 'wpcom_vip_url_to_postid' )
				? wpcom_vip_url_to_postid( $url )
				: url_to_postid( $url );
		} finally {
			$this->closeLanguageScope( $scope );
		}

		return $postId > 0 ? $postId : 0;
	}

	private function isStaticFrontPage( $url ) {
		$pageOnFront = (int) get_option( 'page_on_front' );
		return $pageOnFront > 0 && get_permalink( $pageOnFront ) === $url;
	}

	private function openLanguageScope( $targetLang ) {
		$currentLang = apply_filters( 'wpml_current_language', null );
		if ( $currentLang === $targetLang ) {
			return false;
		}

		do_action( 'wpml_switch_language', $targetLang );

		return true;
	}

	private function closeLanguageScope( $isOpen ) {
		if ( $isOpen ) {
			do_action( 'wpml_switch_language', null );
		}
	}
}
