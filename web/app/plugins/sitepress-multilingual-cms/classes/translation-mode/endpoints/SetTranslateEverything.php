<?php

namespace WPML\TranslationMode\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\ATE\Application\Service\TranslateEverythingPrerequisites;
use WPML\FP\Left;
use WPML\FP\Right;
use WPML\PostHog\RefreshRecording;
use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything\Preflight;
use WPML\WP\OptionManager;

class SetTranslateEverything implements IHandler {

	const KEY_TRANSLATE_EVERYTHING_CHOSEN = 'translate-everything-chosen';

	public function run( Collection $data ) {
		$useTranslateEverything = (bool) $data->get( 'translateEverything' );
		$fireAction             = $data->get( 'fireAction', true );
		$fireAction             = is_bool( $fireAction ) ? $fireAction : ( 'false' !== $fireAction && (bool) $fireAction );

		if ( $useTranslateEverything ) {
			$refusalKey = $this->getRefusalKey();
			if ( $refusalKey !== null ) {
				return Left::of( [ 'key' => $refusalKey ] );
			}
		}

		$advisories = $useTranslateEverything ? Preflight::collect() : array();

		Option::setTranslateEverything( $useTranslateEverything );
		( new OptionManager() )->set( Option::OPTION_GROUP, self::KEY_TRANSLATE_EVERYTHING_CHOSEN, true );
		if ( $fireAction ) {
			do_action( 'wpml_set_translate_everything', $useTranslateEverything );
		}

		RefreshRecording::forceRefresh(
			[
				'during_setup'     => true,
				'setup_tea_choice' => $useTranslateEverything ? 'tea' : 'manual',
			]
		);

		return Right::of( $advisories ? array( 'advisories' => $advisories ) : true );
	}


	private function getRefusalKey() {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			throw new \RuntimeException(
				'The WPML container is not available, so the Translate Everything prerequisites cannot be checked.'
			);
		}

		$prerequisites = $wpml_dic->make( TranslateEverythingPrerequisites::class );

		return $prerequisites->getRefusalKey();
	}
}
