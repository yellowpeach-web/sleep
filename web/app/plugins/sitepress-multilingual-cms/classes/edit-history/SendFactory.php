<?php

namespace WPML\EditHistory;

class SendFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	private static $send;

	private static $resend;

	public function create() {
		return self::send() ? new SendHooks() : null;
	}

	public static function send() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		return self::$send ?? ( self::$send = self::build() );
	}

	public static function resend() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		return self::$resend ?? ( self::$resend = new ReSend( new JobRows( $GLOBALS['wpdb'] ), self::sendAutomatically(), [ \WPML\TM\Jobs\JobLog::class, 'add' ] ) );
	}

	public static function reset() {
		self::$send   = null;
		self::$resend = null;
	}

	private static function build(): HistorySend {
		global $wpdb;

		$log        = new WpdbEntryLog( $wpdb );
		$store      = new WpdbStateStore( $wpdb );
		$normaliser = Normaliser::create();
		$jobRows    = new JobRows( $wpdb );
		$resend     = self::resend();
		$units      = PostUnits::forBlockEditor();

		return new HistorySend(
			new HistoryLoader( $log, $store ),
			HistoryKey::create(),
			new SafetyNet( $log, $store, $normaliser ),
			new FromScratch( $log, $store ),
			$store,
			$normaliser,
			[ $units, 'pair' ],
			[ $jobRows, 'lastApplied' ],
			[ $jobRows, 'translationOf' ],
			[ \WPML\TM\Jobs\JobLog::class, 'add' ],
			null,
			null,
			$resend ? [ $resend, 'takeDropped' ] : null
		);
	}

	private static function sendAutomatically(): callable {
		return static function ( array $element ) {
			$batch = new \WPML_TM_Translation_Batch(
				[
					new \WPML_TM_Translation_Batch_Element(
						$element['original'],
						$element['type'],
						$element['source'],
						[ $element['language'] => \TranslationManagement::TRANSLATE_ELEMENT_ACTION ]
					),
				],
				\TranslationProxy_Batch::get_generic_batch_name( true ),
				[ $element['language'] => (int) get_current_user_id() ]
			);
			$batch->setTranslationMode( 'auto' );

			wpml_load_core_tm()->send_jobs( $batch, $element['type'], \WPML\TM\API\Jobs::SENT_AUTOMATICALLY );
		};
	}
}
