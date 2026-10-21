<?php

use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;

class WPML_Installation extends WPML_WPDB_And_SP_User {

	const WPML_START_VERSION_KEY = 'wpml_start_version';

	public static function getStartVersion() {
		return get_option( self::WPML_START_VERSION_KEY, '0.0.0' );
	}

	function go_to_setup1() {
		$wpdb = $this->wpdb;

		$this->truncate_translation_records();

		$settings = $this->sitepress->get_settings();

		unset(
			$settings['default_categories'],
			$settings['default_language'],
			$settings['setup_wizard_step']
		);

		$settings['existing_content_language_verified'] = 0;
		$settings['active_languages'] = array();
		$GLOBALS['sitepress_settings']['existing_content_language_verified'] = $settings['existing_content_language_verified'];
		update_option( 'icl_sitepress_settings', $settings );

		$this->wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}icl_locale_map" );

		$this->wpdb->update( $this->wpdb->prefix . 'icl_languages', array( 'active' => 0 ), array( 'active' => 1 ) );
	}

	private function maybe_set_locale( $initial_language_code ) {
		$wpdb = $this->wpdb;

		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code=%s", $initial_language_code ) ) ) {
			$default_locale = $wpdb->get_var(
				$wpdb->prepare( "SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code=%s", $initial_language_code )
			);
			if ( $default_locale ) {
				$wpdb->insert(
					$wpdb->prefix . 'icl_locale_map',
					array( 'code' => $initial_language_code, 'locale' => $default_locale )
				);

			}
		}
	}

	public function finish_step2( $active_languages ) {
		return $this->set_active_languages( $active_languages );
	}

	public function set_active_languages( $arr ) {
		$wpdb = $this->wpdb;

		$tmp = $this->sanitize_language_input( $arr );
		if ( (bool) $tmp === false ) {
			return false;
		}

		foreach ( $tmp as $code ) {
			$default_locale = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT default_locale FROM {$wpdb->prefix}icl_languages WHERE code= %s LIMIT 1",
					$code
				)
			);
			if ( $default_locale ) {
				$code_exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT code FROM {$wpdb->prefix}icl_locale_map WHERE code = %s LIMIT 1",
						$code
					)
				);
				if ( $code_exists ) {
					$this->wpdb->update(
						$this->wpdb->prefix . 'icl_locale_map',
						array( 'locale' => $default_locale ),
						array( 'code' => $code )
					);
				} else {
					$this->wpdb->insert(
						$this->wpdb->prefix . 'icl_locale_map',
						array( 'code' => $code, 'locale' => $default_locale )
					);
				}
			}
			SitePress_Setup::insert_default_category( $code );
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_languages SET active = 1 WHERE code IN (" . implode( ', ', array_fill( 0, count( $tmp ), '%s' ) ) . ')',
				...$tmp
			)
		);

		if ( $this->has_language_column( 'display_code' ) ) {
			$this->wpdb->query(
				"UPDATE {$this->wpdb->prefix}icl_languages SET display_code = code WHERE display_code IS NULL AND active = 1 AND code IN (" . wpml_prepare_in( $tmp ) . " ) "
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_languages SET active = 0 WHERE code NOT IN (" . implode( ', ', array_fill( 0, count( $tmp ), '%s' ) ) . ')',
				...$tmp
			)
		);

		if ( class_exists( \WPML\LanguageEditor\LanguageNames::class ) ) {
			foreach ( $tmp as $code ) {
				\WPML\LanguageEditor\LanguageNames::seedNewLanguage( $code );
			}
		}

		$this->updated_active_languages();

		return true;
	}

	private function sanitize_language_input( $lang_codes ) {
		$languages       = array_fill_keys( $this->sitepress->get_supported_language_codes( true ), true );
		$sanitized_codes = array();
		$lang_codes      = array_filter( array_unique( $lang_codes ) );
		foreach ( $lang_codes as $code ) {
			$code = esc_sql( trim( $code ) );
			if ( isset( $languages[ $code ] ) ) {
				$sanitized_codes[] = $code;
			}
		}

		return $sanitized_codes;
	}

	public function finish_installation( ) {
		icl_set_setting( 'store_frontend_cookie', 1 );

		icl_set_setting( 'setup_complete', 1, true );

		update_option( self::WPML_START_VERSION_KEY, ICL_SITEPRESS_VERSION );

		do_action( 'wpml_setup_completed' );
	}

	public function store_site_key( $site_key = false ) {
		if ( $site_key ) {
			icl_set_setting( 'site_key', $site_key, true );
		}
	}

	public function finish_step3() {
		$this->maybe_move_setup( 4 );
	}

	private function maybe_move_setup( $step ) {
		$setup_complete = icl_get_setting( 'setup_complete' );
		if ( empty( $setup_complete ) ) {
			icl_set_setting( 'setup_wizard_step', $step, true );
		}
	}

	private function updated_active_languages() {
		wp_cache_init();
		wpml_reload_active_languages_setting( true );

		icl_cache_clear();
		WPML_Query_Utils::flush_archive_language_sets();
		$this->refresh_active_lang_cache( wpml_get_setting_filter( false, 'default_language' ), true );
		$this->update_languages_order();
		$active_langs = $this->sitepress->get_active_languages( true );
		$this->maybe_move_setup( 3 );
		if ( count( $active_langs ) > 1 ) {
			icl_set_setting( 'dont_show_help_admin_notice', true );
		}
	}

	public function finish_step1( $initial_language_code ) {
		$this->set_initial_default_category( $initial_language_code );
		$this->prepopulate_translations( $initial_language_code );
		$this->update_active_language( $initial_language_code );
		$admin_language = $this->get_admin_language( $initial_language_code );
		$this->maybe_set_locale( $admin_language );
		icl_set_setting( 'existing_content_language_verified', 1 );
		icl_set_setting( 'default_language', $initial_language_code );
		icl_set_setting( 'setup_wizard_step', 2 );
		icl_save_settings();
		wp_cache_flush();
		$this->refresh_active_lang_cache( $initial_language_code );
		add_filter( 'locale', array( $this->sitepress, 'locale_filter' ), 10, 1 );

		if ( ! array_key_exists( 'wp_styles', $GLOBALS ) || ! $GLOBALS['wp_styles'] ) {
			wp_styles();
		}

		if ( $this->sitepress->is_rtl( $admin_language ) ) {
			$GLOBALS['text_direction'] = 'rtl';
			$GLOBALS['wp_styles']->text_direction = 'rtl';
		} else {
			$GLOBALS['text_direction'] = 'ltr';
			$GLOBALS['wp_styles']->text_direction = 'ltr';
		}

		$GLOBALS['wp_locale'] = new WP_Locale();
		$admin_locale = $this->sitepress->get_locale( $admin_language );
		if ( false !== $admin_locale ) {
			$GLOBALS['locale'] = $admin_locale;
		}

		do_action( 'icl_initial_language_set' );
	}

	private function get_admin_language( $initial_language_code ) {
		$user_locale = get_user_meta( get_current_user_id(), 'locale', true );

		if ( $user_locale ) {
			$lang = $this->sitepress->get_language_code_from_locale( $user_locale, false );

			if ( $lang ) {
				return $lang;
			}
		}

		return $initial_language_code;

	}

	private function set_initial_default_category( $initial_lang ) {
		$wpdb = $this->wpdb;

		$blog_default_cat        = get_option( 'default_category' );
		$blog_default_cat_tax_id = $wpdb->get_var(
			$wpdb->prepare(
				"	SELECT term_taxonomy_id
	                FROM {$wpdb->term_taxonomy}
	                WHERE term_id=%d
	                  AND taxonomy='category'",
				$blog_default_cat
			)
		);

		if ( ! LanguageCode::isEnglish( $initial_lang ) ) {
			$this->rename_default_category_of_initial_language( $initial_lang, $blog_default_cat );
		}


		icl_set_setting( 'default_categories', array( $initial_lang => $blog_default_cat_tax_id ), true );
	}

	private function rename_default_category_of_initial_language( $initial_lang, $category_id ) {
		global $sitepress;
		$sitepress->switch_locale( $initial_lang );
		/* translators: The name WordPress gives its default category. WPML uses it when it makes that category in a new language, so use the same wording WordPress itself uses in your language. */
		$tr_cat = __( 'Uncategorized', 'sitepress' );
		$tr_cat = $tr_cat === 'Uncategorized' ? 'Uncategorized @' . $initial_lang : $tr_cat;
		$sitepress->switch_locale();

		wp_update_term( $category_id, 'category', array(
			'name' => $tr_cat,
			'slug' => sanitize_title( $tr_cat ),
		) );
	}

	public function refresh_active_lang_cache( $display_language, $active_only = false, $major_first = false,  $order_by = 'english_name' ) {
		if ( ! \WPML\Language\WpmlPresence::onCurrentBlog() ) {
			return array();
		}

		$active_snippet     = $active_only ? 'WHERE l.active = 1' : '';
		$country_column      = $this->has_language_column( 'country' ) ? 'l.country,' : 'NULL AS country,';
		$display_code_column = $this->has_language_column( 'display_code' ) ? 'l.display_code,' : '';
		$res_query
							= "
            SELECT
              l.code,
              l.id,
              english_name,
              nt.name AS native_name,
              major,
              active,
              default_locale,
              encode_url,
              tag,
              {$country_column}
              {$display_code_column}
              lt.name AS display_name
			FROM {$this->wpdb->prefix}icl_languages l
			LEFT OUTER JOIN {$this->wpdb->prefix}icl_languages_translations nt
			  ON ( nt.language_code = l.code AND nt.display_language_code = l.code )
            LEFT OUTER JOIN {$this->wpdb->prefix}icl_languages_translations lt
			  ON ( l.code = lt.language_code
			  AND ( lt.display_language_code = %s
			  OR (lt.display_language_code = 'en'
			    AND NOT EXISTS ( SELECT *
			          FROM {$this->wpdb->prefix}icl_languages_translations ls
			          WHERE ls.language_code = l.code
			            AND ls.display_language_code = %s ) ) ) )
			{$active_snippet}
            GROUP BY l.code";


		$allowed_order_by = array(
			'active',
			'code',
			'country',
			'default_locale',
			'display_code',
			'display_name',
			'encode_url',
			'english_name',
			'id',
			'major',
			'native_name',
			'tag',
		);
		$order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'english_name';

		$order_by_fields = array();
		if ( $major_first ) {
			$order_by_fields[] = 'major DESC';
		}
		$order_by_fields[] = $order_by . ' ASC';

		$res_query .= PHP_EOL . 'ORDER BY ' . implode( ', ', $order_by_fields );

		$res_query_prepared = $this->wpdb->prepare( $res_query, $display_language, $display_language );
		$res                = $this->wpdb->get_results( $res_query_prepared, ARRAY_A );
		$languages          = array();

		$icl_cache = \WPML\Language\ActiveLanguagesReadModel::cache();
		foreach ( (array) $res as $r ) {
			$r['display_name'] = $this->resolve_language_name( $r, $display_language, $r['display_name'] );
			$r['native_name']  = $this->resolve_language_name( $r, $r['code'], $r['native_name'] );

			$languages[ $r[ 'code' ] ] = $r;
			$icl_cache->set( 'language_details_' . $r['code'] . $display_language, $r );
		}

		if ( $active_only ) {
			$icl_cache->set( 'in_language_' . $display_language . '_' . $major_first . '_' . $order_by, $languages );
		} else {
			$icl_cache->set( 'all_language_' . $display_language . '_' . $major_first . '_' . $order_by, $languages );
		}

		$icl_cache->save_cache_if_required();

		return $languages;
	}

	private function resolve_language_name( array $row, $display_code, $stored ) {
		if ( null !== $stored && '' !== $stored ) {
			return (string) $stored;
		}

		$code = isset( $row['code'] ) ? (string) $row['code'] : '';

		$native = isset( $row['native_name'] ) ? (string) $row['native_name'] : '';
		if ( '' !== $native ) {
			return $native;
		}

		if ( class_exists( '\WPML\LanguageEditor\LanguageNames' ) ) {
			$catalogue = \WPML\LanguageEditor\LanguageNames::nameFor( $code, (string) $display_code );
			if ( null !== $catalogue && '' !== $catalogue ) {
				return (string) $catalogue;
			}
		}

		$english = isset( $row['english_name'] ) ? (string) $row['english_name'] : '';

		return '' !== $english ? $english : $code;
	}

	private function has_language_column( $column ) {
		$wpdb = $this->wpdb;

		static $columns = array();

		$key = $wpdb->prefix . '|' . $column;

		if ( ! array_key_exists( $key, $columns ) ) {
			$columns[ $key ] = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s",
					$column
				)
			);
		}

		return $columns[ $key ];
	}

	private function update_languages_order() {
		\WPML\LanguageEditor\LanguagesOrder::sync( $this->sitepress );
	}

	private function truncate_translation_records() {
		$this->wpdb->query( "TRUNCATE TABLE {$this->wpdb->prefix}icl_translations" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->wpdb->prefix}icl_translation_status" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->wpdb->prefix}icl_translate_job" );
		$this->wpdb->query( "TRUNCATE TABLE {$this->wpdb->prefix}icl_translate" );
	}

	private function prepopulate_translations( $lang ) {
		$wpdb = $this->wpdb;

		$existing_lang_verified = icl_get_setting( 'existing_content_language_verified' );
		if ( ! empty( $existing_lang_verified ) ) {
			return;
		}

		icl_cache_clear();

		$one_translation = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations
				 WHERE language_code<>%s AND source_language_code IS NOT NULL",
				$lang
			)
		);
		if ( $one_translation ) {
			return;
		}

		$this->truncate_translation_records();
		$wpdb->query(
			$wpdb->prepare(
				"
			INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
			SELECT CONCAT('post_',post_type), ID, ID, %s, NULL FROM {$wpdb->posts} WHERE post_status IN ('draft', 'publish','schedule','future','private', 'pending', 'trash')
			",
				$lang
			)
		);

		$maxtrid = 1 + (int) $this->wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );

		global $wp_taxonomies;
		$taxonomies = array_keys( (array) $wp_taxonomies );
		foreach ( $taxonomies as $tax ) {
			$element_type = 'tax_' . $tax;
			$wpdb->query(
				$wpdb->prepare(
					"
					INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
					SELECT %s, term_taxonomy_id, %d+term_taxonomy_id, %s, NULL FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s
					",
					$element_type,
					$maxtrid,
					$lang,
					$tax
				)
			);
			$maxtrid = 1 + (int) $this->wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );
		}

		$wpdb->query(
			$wpdb->prepare(
				"
			INSERT INTO {$wpdb->prefix}icl_translations(element_type, element_id, trid, language_code, source_language_code)
			SELECT 'comment', comment_ID, %d+comment_ID, %s, NULL FROM {$wpdb->comments}
			",
				$maxtrid,
				$lang
			)
		);

		delete_option( \WPML\Comments\Counts\Store::OPTION );
	}

	public function update_active_language( $lang ) {
		$this->wpdb->update( $this->wpdb->prefix . 'icl_languages', array( 'active' => '1' ), array( 'code' => $lang ) );
	}

}
