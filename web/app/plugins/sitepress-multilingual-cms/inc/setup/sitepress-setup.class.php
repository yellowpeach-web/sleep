<?php
class SitePress_Setup {

	const NAME_PAIR_INSERT_CHUNK = 200;
	static function setup_complete() {
		global $sitepress;

		return $sitepress->get_setting( 'setup_complete' );
	}

	private static function get_languages_codes() {
		static $languages_codes = array();
		if ( ! $languages_codes ) {
			$languages_codes = icl_get_languages_codes();
		}

		return $languages_codes;
	}

	private static function get_languages_names() {
		static $languages_names = array();
		if ( ! $languages_names ) {
			$languages_names = icl_get_languages_names();
		}

		return $languages_names;
	}

	private static function get_shipped_languages_codes() {
		return array_values( array_intersect_key( self::get_languages_codes(), self::get_languages_names() ) );
	}

	static function get_charset_collate() {
		static $charset_collate = null;

		if ( $charset_collate == null ) {
			$charset_collate = '';
			global $wpdb;
			if ( method_exists( $wpdb, 'has_cap' ) && $wpdb->has_cap( 'collation' ) ) {
				$pair    = wpml_get_upgrade_schema()->get_default_charset_and_collate();
				$charset = $pair['charset'];
				$collate = $pair['collate'];

				if ( $charset ) {
					$charset_collate = "DEFAULT CHARACTER SET $charset";
				}

				if ( $collate ) {
					$charset_collate .= " COLLATE $collate";
				}
			}
		}

		return $charset_collate;
	}

	private static function table_exists( $table_name ) {
		global $wpdb;

		$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return 0 === strcasecmp( $found, $table_name );
	}

	private static function create_languages() {
		global $wpdb;

		if ( self::table_exists( $wpdb->prefix . 'icl_languages' ) ) {
			return true;
		}

		return \WPML\Upgrade\CreateTable::create(
			$wpdb,
			$wpdb->prefix . 'icl_languages',
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_languages` ( `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					  `code` VARCHAR( 7 ) NOT NULL ,
					  `english_name` VARCHAR( 128 ) NOT NULL ,
						  `major` TINYINT NOT NULL DEFAULT 0,
					  `active` TINYINT NOT NULL ,
					  `default_locale` VARCHAR( 35 ),
					  `tag` VARCHAR( 35 ),
					  `encode_url` TINYINT( 1 ) NOT NULL DEFAULT 0,
					  `country` VARCHAR(10) NULL DEFAULT NULL,
					  `display_code` VARCHAR(40) NULL DEFAULT NULL,
					  `is_custom` TINYINT NOT NULL DEFAULT 0,
					  `type` VARCHAR(20) NULL DEFAULT NULL,
					  `translation_paused` TINYINT(1) NOT NULL DEFAULT 0,
					  `bcp_47` VARCHAR( 35 ) NULL DEFAULT NULL,
					  `is_rtl` TINYINT( 1 ) NULL DEFAULT NULL,
					  UNIQUE KEY `code` (`code`),
					  UNIQUE KEY `english_name` (`english_name`),
					  KEY `bcp_47` (`bcp_47`)
				  )"
		);
	}

	static function languages_table_is_complete() {
		if ( ! self::languages_table_has_every_shipped_code() ) {
			return false;
		}

		$languages_codes = self::get_languages_codes();
		$language_pairs  = self::pairs_on_file();

		foreach ( self::get_languages_names() as $lang => $val ) {
			foreach ( $val['tr'] as $k => $display ) {
				$k = self::fix_language_name( $k );

				if ( ! isset( $language_pairs[ strtolower( (string) $languages_codes[ $lang ] ) . '|' . strtolower( (string) $languages_codes[ $k ] ) ] ) ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function languages_table_has_every_shipped_code() {
		return array() === array_diff( self::fold( self::get_shipped_languages_codes() ), self::fold( self::get_language_codes_in_table() ) );
	}

	private static function fold( array $codes ) {
		$folded = array();

		foreach ( $codes as $code ) {
			$folded[] = strtolower( (string) $code );
		}

		return $folded;
	}

	private static function pairs_on_file() {
		$pairs = array();

		foreach ( self::get_language_translations() as $language_code => $display_codes ) {
			foreach ( (array) $display_codes as $display_code ) {
				$pairs[ strtolower( (string) $language_code ) . '|' . strtolower( (string) $display_code ) ] = true;
			}
		}

		return $pairs;
	}

	private static function country_model_is_migrated() {
		return ( new \WPML\Upgrade\CommandsStatus() )
			->hasBeenExecuted( \WPML\Upgrade\Commands\MigrateLanguagesToCountryModel::class );
	}

	private static function get_language_codes_in_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'icl_languages';

		return (array) $wpdb->get_col( "SELECT code FROM {$table_name}" );
	}

	protected static function fix_language_name( $language_name ) {
		if ( strpos( $language_name, 'Norwegian Bokm' ) === 0 ) {
			$language_name = 'Norwegian Bokmål';
		}

		return $language_name;
	}

	private static function get_language_translations() {
		$result = array();

		global $wpdb;
		$rowset = $wpdb->get_results( "SELECT language_code, display_language_code FROM {$wpdb->prefix}icl_languages_translations" );

		if ( is_array( $rowset ) ) {
			foreach ( $rowset as $row ) {
				$result[ $row->language_code ][] = $row->display_language_code;
			}
		}

		return $result;
	}

	private static function existing_language_codes() {
		$result = array();

		foreach ( self::fold( self::get_language_codes_in_table() ) as $code ) {
			$result[ $code ] = true;
		}

		return $result;
	}

	static function fill_languages() {
		global $wpdb, $sitepress;

		$languages_codes = icl_get_languages_codes();
		$lang_locales    = icl_get_languages_locales();

		$table_name = $wpdb->prefix . 'icl_languages';
		if ( ! self::create_languages() ) {
			return false;
		}

		if ( ! self::languages_table_is_complete() ) {
			$active_languages = ( $sitepress !== null
								  && $sitepress->is_setup_complete() ) ? $sitepress->get_active_languages() : array();

			$existing_codes   = self::existing_language_codes();
			$country_migrated = self::country_model_is_migrated();

			$has_bcp_47 = (bool) $wpdb->get_var(
				$wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'bcp_47' )
			);

			$inserted = 0;

			$previous_errors = $wpdb->hide_errors();

			foreach ( self::get_languages_names() as $key => $val ) {
				if ( ! isset( $languages_codes[ $key ] ) ) {
					continue;
				}

				$language_code = $languages_codes[ $key ];

				if ( isset( $existing_codes[ strtolower( $language_code ) ] ) ) {
					continue;
				}

				$default_locale = isset( $lang_locales[ $language_code ] ) ? $lang_locales[ $language_code ] : '';

				$language_tag = str_replace( '_', '-', $language_code );

				$args = array(
					'english_name'   => $key,
					'code'           => $language_code,
					'major'          => $val['major'],
					'active'         => isset( $active_languages[ $language_code ] ) ? 1 : 0,
					'default_locale' => $default_locale,
					'tag'            => $language_tag,
				);

				if ( $has_bcp_47 ) {
					$bcp_47         = \WPML\Core\Component\LanguageEditor\Domain\Bcp47::fromLegacyCode( $language_code );
					$args['bcp_47'] = '' !== $bcp_47 ? $bcp_47 : null;
				}

				if ( $country_migrated ) {
					$assignment = \WPML\Upgrade\Commands\MigrateLanguagesToCountryModel::assignmentForCode(
						$language_code,
						$default_locale
					);

					if ( null !== $assignment['country'] ) {
						$args['country'] = $assignment['country'];
					}

					$args['type'] = $assignment['type'];
				}

				if ( false !== $wpdb->insert( $table_name, $args ) ) {
					$inserted++;
				}
			}

			if ( $previous_errors ) {
				$wpdb->show_errors();
			}

			if ( $inserted > 0 ) {
				\WPML\LanguageEditor\LanguageCodeResolution::resetStoredTags();
			}
		}

		return true;
	}

	private static function create_languages_translations() {
		global $wpdb;

		if ( self::table_exists( $wpdb->prefix . 'icl_languages_translations' ) ) {
			return true;
		}

		return \WPML\Upgrade\CreateTable::create(
			$wpdb,
			$wpdb->prefix . 'icl_languages_translations',
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_languages_translations` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `language_code`  VARCHAR( 7 ) NOT NULL ,
                `display_language_code` VARCHAR( 7 ) NOT NULL ,
                `name` VARCHAR( 255 ) NOT NULL,
                UNIQUE(`language_code`, `display_language_code`)
	            )"
		);
	}

	static function fill_languages_translations() {
		global $wpdb;

		$languages_codes = icl_get_languages_codes();

		$table_name = $wpdb->prefix . 'icl_languages_translations';

		if ( ! self::create_languages_translations() ) {
			return false;
		}

		if ( ! self::languages_table_is_complete() ) {
			$pairs_on_file = self::pairs_on_file();

			$tuples = array();

			foreach ( (array) self::get_languages_names() as $lang => $val ) {
				if ( ! isset( $languages_codes[ $lang ] ) ) {
					continue;
				}

				foreach ( $val['tr'] as $k => $display ) {
					$k = self::fix_language_name( $k );
					if ( ! isset( $languages_codes[ $k ] ) ) {
						continue;
					}

					$language_code         = $languages_codes[ $lang ];
					$display_language_code = $languages_codes[ $k ];

					if ( isset( $pairs_on_file[ strtolower( $language_code ) . '|' . strtolower( $display_language_code ) ] ) ) {
						continue;
					}

					if ( ! trim( $display ) ) {
						$display = $lang;
					}

					$tuples[] = $wpdb->prepare(
						'(%s, %s, %s)',
						array( $language_code, $display_language_code, $display )
					);
				}
			}

			if ( $tuples ) {
				$previous_errors = $wpdb->hide_errors();

				$failed_chunks = 0;

				foreach ( array_chunk( $tuples, self::NAME_PAIR_INSERT_CHUNK ) as $chunk ) {
					$insert_sql = "INSERT INTO {$table_name} (language_code, display_language_code, name) VALUES "
								. implode( ",\n", $chunk );

					if ( $wpdb->query( $insert_sql ) === false ) {
						$failed_chunks++;
					}
				}

				if ( $previous_errors ) {
					$wpdb->show_errors();
				}

				if ( $failed_chunks > 0 ) {
					return false;
				}
			}
		}

		return true;
	}


	private static function create_flags() {
		global $wpdb;

		if ( self::table_exists( $wpdb->prefix . 'icl_flags' ) ) {
			return true;
		}

		return \WPML\Upgrade\CreateTable::create(
			$wpdb,
			$wpdb->prefix . 'icl_flags',
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_flags` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `lang_code` VARCHAR( 10 ) NOT NULL ,
                `flag` VARCHAR( 255 ) NOT NULL ,
				`from_template` TINYINT NOT NULL DEFAULT 0,
                UNIQUE (`lang_code`)
                )"
		);
	}

	public static function fill_flags() {
		global $wpdb;

		if ( self::create_flags() === false ) {
			return;
		}

		$has_country = (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s", 'country' )
		);
		$country     = $has_country ? 'country' : 'NULL AS country';

		$rows = $wpdb->get_results( "SELECT code, {$country} FROM {$wpdb->prefix}icl_languages" );

		$written = 0;

		foreach ( (array) $rows as $row ) {
			$code = (string) $row->code;
			if ( ! $code ) {
				continue;
			}

			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT flag, from_template
                         FROM {$wpdb->prefix}icl_flags
                         WHERE lang_code = %s ",
					$code
				)
			);
			if ( null !== $existing && self::flag_row_is_usable( $existing ) ) {
				continue;
			}

			if ( file_exists( WPML_PLUGIN_PATH . '/res/flags/nil.svg' ) ) {
				$file = 'nil.svg';
			} else {
				$file = 'nil.png';
			}

			$resolved  = \WPML\LanguageEditor\LanguageCodeResolution::resolve( $code );
			$pair_flag = $resolved !== null && ! empty( $resolved['pair_flag'] ) ? (string) $resolved['pair_flag'] : '';
			$pair_flag = '' === $pair_flag ? '' : \WPML\LanguageEditor\Flags\FlagFile::resolve( $pair_flag );

			$glyph = (string) \WPML\LanguageEditor\Flags\FlagManifest::instance()->legacyLanguageFile( $code );
			$flat  = strtolower( $code ) . '.svg' === $glyph ? $glyph : '';

			if ( '' !== $flat && file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $flat ) ) {
				$file = $flat;
			} elseif ( '' !== $flat && file_exists( WPML_PLUGIN_PATH . '/res/flags/' . substr( $flat, 0, -4 ) . '.png' ) ) {
				$file = substr( $flat, 0, -4 ) . '.png';
			} elseif ( '' !== $pair_flag && \WPML\LanguageEditor\Flags\FlagFile::NEUTRAL_GLOBE !== $pair_flag ) {
				$file = $pair_flag;
			} else {
				$country_flag = (string) \WPML\LanguageEditor\Flags\FlagManifest::instance()->countryFile( (string) $row->country );
				if ( '' === $country_flag ) {
					$country_flag = self::country_flag_file( $row->country );
				}
				if ( '' !== $country_flag ) {
					$file = $country_flag;
				}
			}

			if ( null !== $existing ) {
				$wpdb->update(
					$wpdb->prefix . 'icl_flags',
					array(
						'flag'          => $file,
						'from_template' => 0,
					),
					array( 'lang_code' => $code )
				);
				++$written;
				continue;
			}

			$wpdb->insert(
				$wpdb->prefix . 'icl_flags',
				array(
					'lang_code'     => $code,
					'flag'          => $file,
					'from_template' => 0,
				)
			);
			++$written;
		}

		if ( $written ) {
			WPML_Flags::invalidate();
		}
	}

	private static function flag_row_is_usable( $row ) {
		$flag = isset( $row->flag ) ? trim( (string) $row->flag ) : '';

		if ( '@code' === $flag ) {
			return true;
		}
		if ( ! empty( $row->from_template ) ) {
			return true;
		}

		return self::flag_file_present( $flag );
	}

	private static function flag_file_present( $flag ) {
		if ( '' === $flag ) {
			return false;
		}
		if ( \WPML\LanguageEditor\Flags\FlagFile::exists( $flag ) ) {
			return true;
		}

		$png = strtolower( $flag );

		return 1 === preg_match( '/^[a-z0-9][a-z0-9._-]*\.png$/', $png )
			&& file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $png );
	}

	private static function country_flag_file( $country ) {
		global $wpdb;

		$country = strtoupper( trim( (string) $country ) );
		if ( '' === $country ) {
			return '';
		}

		$flag = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT flag FROM {$wpdb->prefix}icl_countries WHERE code = %s",
				$country
			)
		);

		return '' !== $flag && file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $flag ) ? $flag : '';
	}

	public static function insert_default_category( $lang_code ) {
		global $sitepress;

		$default_language = $sitepress->get_default_language();
		if ( '' === (string) $default_language ) {
			$default_language = (string) $sitepress->get_setting( 'default_language' );
		}
		if ( '' === (string) $default_language || $lang_code === $default_language ) {
			return;
		}

		$default_categories = (array) $sitepress->get_setting( 'default_categories', array() );
		if ( isset( $default_categories[ $lang_code ] )
			&& self::default_category_ttid_exists( (int) $default_categories[ $lang_code ] ) ) {
			return;
		}

		$default_category_ttid = self::default_language_category_ttid( $default_categories, $default_language );

		if ( 0 === $default_category_ttid ) {
			return;
		}

		$default_categories[ $default_language ] = $default_category_ttid;

		$default_category_trid = $sitepress->get_element_trid(
			$default_category_ttid,
			'tax_category'
		);

		$existing_ttid = self::find_default_category_of_language( $lang_code, $default_category_trid, $default_category_ttid );
		if ( $existing_ttid ) {
			$tmp = array( 'term_taxonomy_id' => $existing_ttid );
		} else {
			$sitepress->switch_locale( $lang_code );
			/* translators: The name WordPress gives its default category. WPML uses it when it makes that category in a new language, so use the same wording WordPress itself uses in your language. */
			$tr_cat = __( 'Uncategorized', 'sitepress' );
			$sitepress->switch_locale();

			$tmp = self::insert_own_default_category_term( $tr_cat, $lang_code );
		}

		if ( is_wp_error( $tmp ) || empty( $tmp['term_taxonomy_id'] ) ) {
			return;
		}

		$default_categories[ $lang_code ] = $tmp['term_taxonomy_id'];

		$sitepress->set_default_categories( $default_categories );

		if ( $default_category_ttid && ! $default_category_trid ) {
			$sitepress->set_element_language_details(
				$default_category_ttid,
				'tax_category',
				false,
				$default_language
			);
			$default_category_trid = $sitepress->get_element_trid(
				$default_category_ttid,
				'tax_category'
			);
		}
		$sitepress->set_element_language_details(
			$tmp['term_taxonomy_id'],
			'tax_category',
			$default_category_trid,
			$lang_code,
			$default_language
		);
	}

	private static function language_owning_term( $term_taxonomy_id ) {
		global $wpdb;

		$language = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT language_code
				     FROM {$wpdb->prefix}icl_translations
				     WHERE element_type = 'tax_category'
				     AND element_id = %d",
				$term_taxonomy_id
			)
		);

		return $language ? (string) $language : null;
	}

	private static function default_category_slug( $lang_code ) {
		return 'uncategorized-' . strtolower( (string) $lang_code );
	}

	private static function insert_own_default_category_term( $name, $lang_code ) {
		$slug = self::default_category_slug( $lang_code );
		$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );

		for ( $n = 2; $n <= 9 && is_wp_error( $term ) && 'term_exists' === $term->get_error_code(); $n++ ) {
			$term = wp_insert_term( $name, 'category', array( 'slug' => $slug . '-' . $n ) );
		}

		return $term;
	}

	private static function legacy_default_category_name( $lang_code ) {
		return 'Uncategorized @' . $lang_code;
	}

	private static function find_default_category_of_language( $lang_code, $default_category_trid, $default_category_ttid ) {
		global $sitepress;

		if ( $default_category_trid ) {
			$translations = $sitepress->get_element_translations( $default_category_trid, 'tax_category' );
			if ( is_array( $translations ) && isset( $translations[ $lang_code ]->element_id ) ) {
				$linked_ttid = (int) $translations[ $lang_code ]->element_id;
				if ( self::default_category_ttid_exists( $linked_ttid ) ) {
					return $linked_ttid;
				}
			}
		}

		$links = array( self::default_category_slug( $lang_code ), self::legacy_default_category_name( $lang_code ) );
		foreach ( $links as $link ) {
			$candidate = term_exists( $link, 'category' );
			if ( ! is_array( $candidate ) || empty( $candidate['term_taxonomy_id'] ) ) {
				continue;
			}
			$candidate_ttid = (int) $candidate['term_taxonomy_id'];
			if ( $candidate_ttid === (int) $default_category_ttid ) {
				continue;
			}
			$owner_language = self::language_owning_term( $candidate_ttid );
			if ( null === $owner_language || $owner_language === $lang_code ) {
				return $candidate_ttid;
			}
		}

		return 0;
	}

	private static function default_category_ttid_exists( $term_taxonomy_id ) {
		global $wpdb;

		if ( $term_taxonomy_id <= 0 ) {
			return false;
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				     FROM {$wpdb->term_taxonomy}
				     WHERE term_taxonomy_id = %d
				     AND taxonomy = 'category'",
				$term_taxonomy_id
			)
		);
	}

	public static function resolve_default_language_category( array $default_categories, $default_language ) {
		return self::default_language_category_ttid( $default_categories, $default_language );
	}

	private static function default_language_category_ttid( array $default_categories, $default_language ) {
		if ( isset( $default_categories[ $default_language ] )
			&& self::default_category_ttid_exists( (int) $default_categories[ $default_language ] ) ) {
			return self::default_language_member_of( (int) $default_categories[ $default_language ], $default_language );
		}

		$term_taxonomy_id = self::category_ttid_of_term_id( self::stored_default_category_term_id() );

		return $term_taxonomy_id > 0
			? self::default_language_member_of( $term_taxonomy_id, $default_language )
			: 0;
	}

	private static function category_ttid_of_term_id( $term_id ) {
		global $wpdb;

		if ( $term_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				     FROM {$wpdb->term_taxonomy}
				     WHERE term_id = %d
				     AND taxonomy = 'category'",
				$term_id
			)
		);
	}

	private static function stored_default_category_term_id() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				'default_category'
			)
		);
	}

	private static function default_language_member_of( $term_taxonomy_id, $default_language ) {
		global $sitepress;

		$details  = $sitepress->get_element_language_details( $term_taxonomy_id, 'tax_category' );
		$language = isset( $details->language_code ) ? (string) $details->language_code : '';
		if ( '' === $language || $language === $default_language ) {
			return $term_taxonomy_id;
		}

		$trid = $sitepress->get_element_trid( $term_taxonomy_id, 'tax_category' );
		$translations = $trid ? $sitepress->get_element_translations( $trid, 'tax_category' ) : array();

		return isset( $translations[ $default_language ]->element_id )
			? (int) $translations[ $default_language ]->element_id
			: 0;
	}
}
