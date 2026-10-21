<?php

class WPML_ST_Blog_Name_And_Description_Hooks implements \IWPML_Action {

	const STRING_DOMAIN               = 'WP';
	const STRING_NAME_BLOGNAME        = 'Blog Title';
	const STRING_NAME_BLOGDESCRIPTION = 'Tagline';

	const STORED_TRANSLATIONS_OPTION = 'wpml_st_blog_name_and_description_translations';

	const STRING_IDS_OPTION = 'wpml_st_blog_name_and_description_string_ids';

	private static $forgot_in_this_request = false;

	private $cache = [];

	private $is_active_on_current_blog = true;

	public function add_hooks() {
		add_action( 'wpml_st_add_string_translation', [ self::class, 'on_string_translation_saved' ], 10, 4 );
		add_action( 'wpml_st_language_of_strings_changed', [ self::class, 'on_strings_language_changed' ], 10, 1 );
		add_action( 'wpml_st_before_remove_strings', [ self::class, 'on_strings_removed' ], 10, 1 );
		add_action( 'wpml_st_string_unregistered', [ self::class, 'on_strings_removed' ], 10, 0 );

		if ( ! $this->is_customize_page() ) {
			add_filter( 'option_blogname', [ $this, 'option_blogname_filter' ] );
			add_filter( 'option_blogdescription', [ $this, 'option_blogdescription_filter' ] );
			add_action( 'wpml_language_has_switched', [ $this, 'clear_cache' ] );
			add_action( 'switch_blog', [ $this, 'switch_blog_action' ] );
		}
	}

	private function is_customize_page() {
		global $pagenow;

		return 'customize.php' === $pagenow;
	}

	public function option_blogname_filter( $blogname ) {
		return $this->translate_option( self::STRING_NAME_BLOGNAME, $blogname );
	}

	public function option_blogdescription_filter( $blogdescription ) {
		return $this->translate_option( self::STRING_NAME_BLOGDESCRIPTION, $blogdescription );
	}

	private function translate_option( $name, $value ) {
		if ( ! $this->is_active_on_current_blog || ! wpml_st_is_requested_blog() ) {
			return $value;
		}

		if ( ! isset( $this->cache[ $name ] ) ) {
			$this->cache[ $name ] = $this->get_translation( $name, $value );
		}

		return $this->cache[ $name ];
	}

	private function get_translation( $name, $value ) {
		if ( ! self::is_listening() ) {
			return wpml_get_string_current_translation( $value, self::STRING_DOMAIN, $name );
		}

		global $WPML_String_Translation;
		$language = $WPML_String_Translation->get_current_string_language( $name );
		$stored   = self::valid_or_new( get_option( self::STORED_TRANSLATIONS_OPTION, [] ) );

		if (
			isset( $stored['translations'][ $language ][ $name ] )
			&& $stored['translations'][ $language ][ $name ][0] === $value
		) {
			return $stored['translations'][ $language ][ $name ][1];
		}

		$translation = wpml_get_string_current_translation( $value, self::STRING_DOMAIN, $name );

		if ( ! is_customize_preview() ) {
			$this->store( $language, $name, $value, $translation );
		}

		return $translation;
	}

	private function store( $language, $name, $value, $translation ) {
		global $wpdb;

		if ( self::$forgot_in_this_request ) {
			return;
		}

		$stored = self::valid_or_new(
			maybe_unserialize(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
						self::STORED_TRANSLATIONS_OPTION
					)
				)
			)
		);

		$stored['translations'][ $language ][ $name ] = [ $value, $translation ];
		update_option( self::STORED_TRANSLATIONS_OPTION, $stored, true );

		$ids = get_option( self::STRING_IDS_OPTION, false );
		$ids = is_array( $ids ) ? $ids : [];
		$id  = (int) icl_get_string_id( $value, self::STRING_DOMAIN, $name );
		$new = $ids;
		if ( $id || '' === (string) $value ) {
			$new[ $name ] = $id;
		} else {
			unset( $new[ $name ] );
		}
		if ( $new !== $ids || false === get_option( self::STRING_IDS_OPTION, false ) ) {
			update_option( self::STRING_IDS_OPTION, $new, true );
		}
	}

	private static function valid_or_new( $stored ) {
		if (
			! is_array( $stored )
			|| ! isset( $stored['expires'], $stored['translations'] )
			|| ! is_array( $stored['translations'] )
			|| $stored['expires'] < time()
		) {
			return [
				'expires'      => time() + HOUR_IN_SECONDS,
				'translations' => [],
			];
		}

		return $stored;
	}

	public static function on_string_translation_saved( $string_translation_id, $translation_data, $language, $string_id ) {
		if ( self::concerns_our_strings( [ $string_id ] ) ) {
			self::forget();
		}
	}

	public static function on_strings_language_changed( $string_ids ) {
		if ( self::concerns_our_strings( (array) $string_ids ) ) {
			self::forget();
		}
	}

	public static function on_strings_removed( $string_ids = null ) {
		if ( null === $string_ids || self::concerns_our_strings( (array) $string_ids ) ) {
			self::forget();
			delete_option( self::STRING_IDS_OPTION );
		}
	}

	private static function is_listening() {
		return false !== has_action( 'wpml_st_add_string_translation', [ self::class, 'on_string_translation_saved' ] );
	}

	private static function forget() {
		delete_option( self::STORED_TRANSLATIONS_OPTION );
		self::$forgot_in_this_request = true;
	}

	private static function concerns_our_strings( array $string_ids ) {
		$ids = get_option( self::STRING_IDS_OPTION, false );

		if ( ! is_array( $ids ) || ! isset( $ids[ self::STRING_NAME_BLOGNAME ], $ids[ self::STRING_NAME_BLOGDESCRIPTION ] ) ) {
			return true;
		}

		return (bool) array_intersect( array_map( 'intval', $string_ids ), array_filter( array_map( 'intval', $ids ) ) );
	}

	public function clear_cache() {
		$this->cache = [];
	}

	public function switch_blog_action() {
		$this->is_active_on_current_blog = is_plugin_active( basename( WPML_ST_PATH ) . '/plugin.php' );
	}

	public static function is_string( $string_name ) {
		return in_array(
			$string_name,
			[
				self::STRING_NAME_BLOGDESCRIPTION,
				self::STRING_NAME_BLOGNAME,
			],
			true
		);
	}
}
