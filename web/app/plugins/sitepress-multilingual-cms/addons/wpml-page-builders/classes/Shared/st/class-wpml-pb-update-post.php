<?php

use WPML\FP\Wrapper;
use WPML\TM\Jobs\JobLog;
use function WPML\FP\invoke;

class WPML_PB_Update_Post {

	const STRING_TRANSLATIONS = 'string_translations';

	const SKIP_NO_TRANSLATION_ROW     = 'no-translation-row';
	const SKIP_NO_TRANSLATED_POST     = 'no-translated-post';
	const SKIP_NO_STRING_TRANSLATIONS = 'no-string-translations';
	const SKIP_NATIVE_EDITOR          = 'last-edited-in-native-editor';

	private $package_data;
	private $strategy;
	private $sitepress;

	public function __construct( $sitepress, $package_data, IWPML_PB_Strategy $strategy ) {
		$this->sitepress    = $sitepress;
		$this->package_data = $package_data;
		$this->strategy     = $strategy;
	}

	public function update() {

		$package          = $this->package_data['package'];
		$original_post_id = $package->post_id;
		$post             = get_post( $original_post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$element_type      = 'post_' . $post->post_type;
		$trid              = $this->sitepress->get_element_trid( $original_post_id, $element_type );
		$post_translations = $this->sitepress->get_element_translations( $trid, $element_type, false, true );

		$languages = $this->package_data['languages'];

		$string_translations = $this->get_string_translations();

		foreach ( $languages as $lang ) {
			if ( ! isset( $post_translations[ $lang ] ) ) {
				$this->log_skip( $post, 0, $lang, self::SKIP_NO_TRANSLATION_ROW );
				continue;
			}

			$translated_post_id = $post_translations[ $lang ]->element_id;

			if ( ! $translated_post_id || ! ( get_post( $translated_post_id ) instanceof WP_Post ) ) {
				$this->log_skip( $post, (int) $translated_post_id, $lang, self::SKIP_NO_TRANSLATED_POST );
				continue;
			}

			if ( ! self::has_translation_for_language( $string_translations, $lang ) ) {
				$this->log_skip( $post, (int) $translated_post_id, $lang, self::SKIP_NO_STRING_TRANSLATIONS );
				continue;
			}

			$translations = apply_filters( 'wpml_pb_update_post_translations', $string_translations, $package, $lang );

			$this->update_post( $translated_post_id, $post, $translations, $lang );
		}
	}

	private static function has_translation_for_language( $string_translations, $lang ) {
		foreach ( (array) $string_translations as $translations ) {
			if ( is_array( $translations ) && isset( $translations[ $lang ] ) ) {
				return true;
			}
		}

		return false;
	}

	public function update_content( $content, $lang ) {
		$package      = $this->package_data['package'];
		$translations = apply_filters( 'wpml_pb_update_post_translations', $this->get_string_translations(), $package, $lang );

		return Wrapper::of( $this->strategy )
						->map( invoke( 'get_content_updater' ) )
						->map( invoke( 'update_content' )->with( $content, $translations, $lang ) )
						->get();
	}

	private function get_string_translations() {
		if ( array_key_exists( self::STRING_TRANSLATIONS, $this->package_data ) ) {
			return $this->package_data[ self::STRING_TRANSLATIONS ];
		}

		return $this->package_data['package']->get_translated_strings( [] );
	}

	private function update_post( $translated_post_id, $original_post, $string_translations, $lang ) {
		if ( WPML_PB_Last_Translation_Edit_Mode::is_native_editor( $translated_post_id ) ) {
			$this->log_skip( $original_post, (int) $translated_post_id, $lang, self::SKIP_NATIVE_EDITOR );
			return;
		}

		$content_updater = $this->strategy->get_content_updater();
		$content_updater->update( $translated_post_id, $original_post, $string_translations, $lang );
	}

	private function log_skip( WP_Post $original_post, $translated_post_id, $lang, $reason ) {
		$package = $this->package_data['package'];
		$data    = [
			'original_post_id'   => (int) $original_post->ID,
			'post_type'          => (string) $original_post->post_type,
			'translated_post_id' => (int) $translated_post_id,
			'language'           => (string) $lang,
			'reason'             => $reason,
			'package_id'         => is_object( $package ) && isset( $package->ID ) ? (int) $package->ID : 0,
		];

		do_action( 'wpml_pb_update_post_skipped', $data );

		if ( class_exists( JobLog::class ) ) {
			JobLog::add( 'pb_rebuild_skipped', $data );
		}
	}
}
