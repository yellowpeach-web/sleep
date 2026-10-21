<?php

namespace WPML\TM\Settings;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\FP\Lst;
use WPML\LIB\WP\Hooks;
use WPML\WP\OptionManager;
use function WPML\Container\make;

class CustomFieldChangeDetector implements \IWPML_Backend_Action, \IWPML_DIC_Action {
	const PREVIOUS_SETTING = 'previous-custom-fields-to-translate';
	const DETECTED_SETTING = 'detected-custom-fields-to-translate';
	const RECHECK_SECONDS  = 86400;

	const PENDING_CACHE = 'wpml_tm_pending_rulings';

	const PENDING_CACHE_SECONDS = 3600;


	public function add_hooks() {
		Hooks::onAction( 'wpml_after_tm_loaded', 1 )
		     ->then( [ self::class, 'detectIfDue' ] );
	}

	public static function detectIfDue() {
		$signal = ContainerFreeServices::changeSignal();
		if ( ! $signal->isCheckDue( self::RECHECK_SECONDS ) ) {
			return;
		}

		self::notify( self::getNew() );
		self::updatePrevious();

		$signal->markChecked();
	}

	public static function getNew() {
		if ( is_null( OptionManager::getOr( null, 'TM', self::PREVIOUS_SETTING ) ) ) {
			self::updatePrevious();
		}

		return Lst::diff(
			Repository::getCustomFieldsToTranslate() ?: [],
			OptionManager::getOr( [], 'TM', self::PREVIOUS_SETTING )
		);
	}

	public static function notify( array $newFields ) {
		$newFields = self::withoutConsented( $newFields );
		$newFields = self::carriedByTranslatedPosts( $newFields );

		if ( count( $newFields ) ) {
			OptionManager::update(
				'TM',
				self::DETECTED_SETTING,
				Lst::concat( self::getDetected(), $newFields )
			);
		}
	}

	private static function withoutConsented( array $fields ) {
		if ( ! count( $fields ) ) {
			return $fields;
		}

		$consented = PreferenceReapplyConsent::consume( $fields );

		return $consented ? array_values( array_diff( $fields, $consented ) ) : $fields;
	}

	public static function remove( array $fields ) {
		if ( count( $fields ) ) {
			OptionManager::update(
				'TM',
				self::DETECTED_SETTING,
				Lst::diff( self::getDetected(), $fields )
			);
		}
	}

	public static function updatePrevious() {
		OptionManager::update( 'TM', self::PREVIOUS_SETTING, Repository::getCustomFieldsToTranslate() );
	}

	public static function getDetected() {
		return OptionManager::getOr( [], 'TM', self::DETECTED_SETTING );
	}

	public static function pending() {
		$detected = array_values( array_unique( array_map( 'strval', (array) self::getDetected() ) ) );
		if ( ! $detected ) {
			return [];
		}

		$kept = get_option( self::PENDING_CACHE );
		if (
			is_array( $kept )
			&& isset( $kept['detected'], $kept['carried'], $kept['at'] )
			&& $kept['detected'] === $detected
			&& time() - (int) $kept['at'] < self::PENDING_CACHE_SECONDS
		) {
			return array_values( (array) $kept['carried'] );
		}

		$carried = self::carriedByTranslatedPosts( $detected );
		update_option(
			self::PENDING_CACHE,
			[
				'detected' => $detected,
				'carried'  => $carried,
				'at'       => time(),
			],
			true
		);

		return $carried;
	}

	public static function onPostMetaWritten( $metaIds, $objectId, $metaKey ) {
		$kept = get_option( self::PENDING_CACHE );
		if ( is_array( $kept ) && isset( $kept['detected'] ) && in_array( (string) $metaKey, (array) $kept['detected'], true ) ) {
			delete_option( self::PENDING_CACHE );
		}
	}

	public static function forgetPending() {
		if ( false !== get_option( self::PENDING_CACHE ) ) {
			delete_option( self::PENDING_CACHE );
		}
	}

	private static function carriedByTranslatedPosts( array $fields ) {
		if ( ! count( $fields ) ) {
			return $fields;
		}

		$affected = make( AffectedPostsQuery::class );

		return array_values(
			array_filter(
				$fields,
				function ( $field ) use ( $affected ) {
					return $affected->countAffected( [ (string) $field ] ) > 0;
				}
			)
		);
	}

	public function processNewFields() {
		$consented = PreferenceReapplyConsent::consume( self::getDetected() );
		if ( $consented ) {
			self::remove( $consented );
		}
	}
}
