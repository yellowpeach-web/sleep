<?php

namespace WPML\EditHistory;

use WPML\TM\Jobs\JobLog;

class DeliveryFactory {

	private static $delivery;

	public static function delivery() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		return self::$delivery ?? ( self::$delivery = self::build() );
	}

	public static function reset() {
		self::$delivery = null;
	}

	private static function build(): Delivery {
		global $wpdb;

		$units = PostUnits::forBlockEditor();
		$copy  = new PostCopy( fn( \WP_Post $original ) => ( new \WPML_Element_Translation_Package() )->get_custom_fields_to_translate( $original ) );

		return new Delivery(
			new WpdbStateStore( $wpdb ),
			new WpdbEntryLog( $wpdb ),
			new JobRows( $wpdb ),
			Normaliser::create(),
			[ $units, 'pair' ],
			[ $copy, 'read' ],
			fn( int $jobId, int $deliveryId ) => do_action( ReSend::DROPPED_ACTION, $jobId, $deliveryId ),
			[ JobLog::class, 'add' ]
		);
	}
}
