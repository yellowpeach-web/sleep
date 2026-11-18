<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\FP\Either;
use WPML\FP\Right;

class GetTranslationEngine implements IHandler {

	const PTC_SLUGS = [ PtcEngineStatus::PTC_ENGINE_SLUG, 'wpml_ai' ];

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( 'forbidden' );
		}

		global $wpml_dic;

		if ( $wpml_dic && self::truthy( $data->get( 'fresh', false ) ) ) {
			self::flushEngineCache( $wpml_dic );
		}

		$engine = self::resolveActiveEngine();

		if ( $engine === null ) {
			return Right::of( [ 'engine' => null, 'engineName' => '', 'engineSlug' => '' ] );
		}

		$isPtc = self::isPtcSlug( $engine->getCodeName() );

		return Right::of(
			[
				'engine'     => $isPtc ? 'ptc' : 'other',
				'engineName' => $isPtc ? '' : $engine->getFormalName(),
				'engineSlug' => $isPtc ? '' : (string) $engine->getCodeName(),
			]
		);
	}

	public static function resolveActiveEngine() {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return null;
		}

		try {
			return $wpml_dic->make( ActiveEngineQueryInterface::class )->resolve()->getEngine();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	public static function isPtcSlug( $slug ) {
		return in_array( (string) $slug, self::PTC_SLUGS, true );
	}

	private static function truthy( $value ) {
		return is_bool( $value ) ? $value : ( 'false' !== $value && (bool) $value );
	}

	private static function flushEngineCache( $wpml_dic ) {
		try {
			$wpml_dic->make( EnginesServiceInterface::class )->flushCache();
		} catch ( \Throwable $e ) {
			return false;
		}

		return true;
	}
}
