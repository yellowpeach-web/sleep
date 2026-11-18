<?php


namespace WPML\ST\Main\Ajax;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\ST\API\Fns as STAPI;
use WPML\ST\API\StringById;
use WPML\TM\ATE\Review\StringEditAcceptsReview;

class SaveTranslation implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'wpml_manage_string_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			return Either::left( 'not allowed' );
		}

		$id          = Obj::prop( 'id', $data );
		$translation = Obj::prop( 'translation', $data );
		$lang        = Obj::prop( 'lang', $data );

		if ( $id && trim( $translation ?? '' ) !== '' && $lang ) {
			$saved = STAPI::saveTranslation( $id, $lang, $translation, ICL_TM_COMPLETE );
			$this->acceptReviewOfPost( (int) $id, (string) $lang );

			return Either::of( $saved );
		} else {
			return Either::left( 'invalid data' );
		}
	}

	private function acceptReviewOfPost( int $stringId, string $lang ) {
		if ( ! class_exists( StringEditAcceptsReview::class ) ) {
			return;
		}

		StringEditAcceptsReview::afterEditOfPostString(
			function () use ( $stringId ) {
				return $this->postOfString( $stringId );
			},
			$lang
		);
	}

	private function postOfString( int $stringId ): int {
		$string    = apply_filters( StringById::FILTER, null, $stringId );
		$packageId = is_array( $string ) ? (int) ( $string['string_package_id'] ?? 0 ) : 0;

		return $packageId ? (int) ( new \WPML_Package( $packageId ) )->post_id : 0;
	}
}
