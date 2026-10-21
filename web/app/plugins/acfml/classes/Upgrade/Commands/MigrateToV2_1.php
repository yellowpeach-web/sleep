<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Options;
use ACFML\Strings\Factory;
use ACFML\Strings\Translator;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class MigrateToV2_1 implements Command {

	const KEY = 'migrate-to-v2_1';

	const STATUS_DONE = 'done';

	const INIT_PRIORITY = 2;

	const REGISTRATIONM_PRIORITY = 9;

	public static function run() {
		Hooks::onAction( 'acf/init', self::INIT_PRIORITY )
			->then( function() {
				if ( null === Options::get( self::KEY ) && self::isStActivated() ) {
					$factory    = new Factory();
					$translator = new Translator( $factory );

					Hooks::onFilter( 'acf/post_type/registration_args', self::REGISTRATIONM_PRIORITY, 2 )
						->then( spreadArgs(
							function( $args, $data ) use ( $translator ) {
								$translator->registerCpt( $data );
								return $args;
							}
						) );
					Hooks::onFilter( 'acf/taxonomy/registration_args', self::REGISTRATIONM_PRIORITY, 2 )
						->then( spreadArgs(
							function( $args, $data ) use ( $translator ) {
								$translator->registerTaxonomy( $data );
								return $args;
							}
						) );
					Hooks::onFilter( 'acf/validate_options_page', self::REGISTRATIONM_PRIORITY )
						->then( spreadArgs(
							function( $data ) use ( $translator ) {
								$translator->registerOptionsPage( $data );
								return $data;
							}
						) );

					Options::set( self::KEY, self::STATUS_DONE );
				}
			} );
	}

	public static function isStActivated() {
		return defined( 'WPML_ST_VERSION' );
	}

}
