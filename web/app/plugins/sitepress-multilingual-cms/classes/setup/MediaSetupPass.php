<?php

namespace WPML\Setup;

class MediaSetupPass {

	const MAX_POSTS_PER_REQUEST = 2000;

	const SCANNER_BATCH_SIZE = 100;

	public static function run( $maxPosts = self::MAX_POSTS_PER_REQUEST, $scanner = null ) {
		if ( ! class_exists( 'WPML_Media' )
			|| ( $scanner === null && ! class_exists( 'WPML_Media_Set_Posts_Media_Flag_Factory' ) )
		) {
			return true;
		}

		if ( \WPML_Media::has_setup_run() ) {
			return true;
		}

		$sitepress = self::sitepress();
		if ( $sitepress && ! $sitepress->has_uploaded_media() ) {
			\WPML_Media::set_setup_run();

			return true;
		}

		if ( $scanner === null ) {
			$factory = new \WPML_Media_Set_Posts_Media_Flag_Factory();
			$scanner = $factory->create();
		}

		if ( ! method_exists( $scanner, 'process_batch' ) ) {
			return true;
		}

		if ( method_exists( $scanner, 'clear_flags' ) ) {
			$scanner->clear_flags();
		}

		$walked = 0;
		$cursor = 0;

		while ( $walked < $maxPosts ) {
			$result = $scanner->process_batch( $cursor );

			if ( ! is_array( $result ) || count( $result ) < 3 ) {
				return \WPML_Media::has_setup_run();
			}

			$cursor   = (int) $result[1];
			$continue = (bool) $result[2];

			if ( ! $continue ) {
				return true;
			}

			$walked += self::SCANNER_BATCH_SIZE;
		}

		return false;
	}

	private static function sitepress() {
		global $sitepress;

		return is_object( $sitepress ) && method_exists( $sitepress, 'has_uploaded_media' )
			? $sitepress
			: null;
	}
}
