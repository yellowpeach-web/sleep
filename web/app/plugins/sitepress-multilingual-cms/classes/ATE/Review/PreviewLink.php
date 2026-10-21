<?php

namespace WPML\TM\ATE\Review;

use WPML\API\Sanitize;
use WPML\Collect\Support\Traits\Macroable;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\TM\API\Jobs;
use WPML\UrlHandling\PreviewHost;
use function WPML\FP\curryN;

class PreviewLink {
	use Macroable;

	public static function init() {
		self::macro( 'getWithLanguagesParam', curryN( 3, function ( $languages, $translationPostId, $jobId ) {
			$returnUrl = Sanitize::string( Obj::propOr( Obj::prop( 'REQUEST_URI', $_SERVER ), 'returnUrl', $_GET ) );
			$url = self::getWithSpecifiedReturnUrl( (string) $returnUrl, $translationPostId, $jobId );

			if ( $languages ) {
				$url = \add_query_arg(['targetLanguages' => urlencode( join( ',', $languages ) ),], $url);
			}
			return $url;
		} ) );

		self::macro( 'get', curryN( 2, function ( $translationPostId, $jobId ) {
			$returnUrl = Sanitize::string( Obj::propOr( Obj::prop( 'REQUEST_URI', $_SERVER ), 'returnUrl', $_GET ) );

			return self::getWithSpecifiedReturnUrl( (string) $returnUrl, $translationPostId, $jobId );
		} ) );

		self::macro( 'getByJob', curryN( 1, Fns::converge(
			self::get(),
			[
				Jobs::getTranslatedPostId(),
				Obj::prop( 'job_id' ),
			]
		) ) );
	}

	public static function getWithSpecifiedReturnUrl( $returnUrl = null, $translationPostId = null, $jobId = null ) {
		$callback = function ( $returnUrl, $translationPostId, $jobId ) {
			$returnUrl         = (string) $returnUrl;
			$translationPostId = (int) $translationPostId;
			$jobId             = (int) $jobId;

			if ( $translationPostId <= 0 ) {
				if ( $jobId > 0 ) {
					do_action( 'wpml_tm_ghost_translation_detected', 0, $jobId );
				}

				return '';
			}

			$permalink = \get_permalink( $translationPostId );

			if ( false === $permalink ) {
				if ( ! \get_post( $translationPostId ) ) {
					do_action( 'wpml_tm_ghost_translation_detected', $translationPostId );
				}

				return '';
			}

			if ( PreviewHost::isDomainMode() ) {
				$permalink = PreviewHost::toRequestHost( (string) $permalink );
			}

			$isPublicPostType = function ( $postId ) {
				$publicPostTypes = get_post_types( [ 'public' => true ] );
				$postType        = get_post_type( $postId );

				return in_array( $postType, $publicPostTypes, true );
			};

			$args = [
				'preview_id'    => $translationPostId,
				'preview_nonce' => \wp_create_nonce( self::getNonceName( $translationPostId ) ),
				'preview'       => true,
				'jobId'         => $jobId > 0 ? $jobId : '',
				'returnUrl'     => rawurlencode( $returnUrl ),
			];

			if ( !$isPublicPostType( $translationPostId ) ) {
				$args['p'] = $translationPostId;
			}

			return \add_query_arg(
				NonPublicCPTPreview::addArgs( $args ),
				$permalink
			);
		};

		return call_user_func_array( curryN( 3, $callback ), func_get_args() );
	}

	public static function getNonceName( $translationPostId = null ) {
		return Str::concat( 'post_preview_', $translationPostId );
	}
}

PreviewLink::init();
