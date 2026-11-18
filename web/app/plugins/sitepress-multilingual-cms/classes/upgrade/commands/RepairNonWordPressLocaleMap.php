<?php

namespace WPML\Upgrade\Commands;

class RepairNonWordPressLocaleMap implements \IWPML_Upgrade_Command {

	const ATTEMPTS_OPTION = 'wpml_locale_map_repair_attempts';

	const MAX_ATTEMPTS = 3;

	const LEGACY_LOCALES = [
		'ca' => 'ca_ES',
		'el' => 'el_GR',
		'et' => 'et_EE',
		'fi' => 'fi_FI',
		'th' => 'th_TH',
		'hr' => 'hr_HR',
		'uk' => 'uk_UA',
		'ku' => 'ku',
		'cy' => 'cy_GB',
		'eo' => 'eo_UY',
		'eu' => 'eu_ES',
		'ga' => 'ga_IE',
		'hy' => 'hy_AM',
		'lv' => 'lv_LV',
		'mn' => 'mn_MN',
		'sq' => 'sq_AL',
		'vi' => 'vi_VN',
		'mt' => 'mt_MT',
		'ne' => 'ne',
		'fo' => 'fo',
		'lt' => 'lt',
		'tg' => 'tg',
		'tr' => 'tr',
		'ug' => 'ug',
		'ur' => 'uz_UZ',
	];

	private $wpdb;

	private $result;

	public function __construct( array $args = [] ) {
		$this->wpdb = isset( $args[0] ) ? $args[0] : $GLOBALS['wpdb'];
	}

	private function run() {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			"SELECT l.code, l.default_locale, m.locale, p.default_locale AS pair_locale,
				EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_language_preset_countries AS c WHERE c.default_locale = m.locale ) AS map_in_catalogue
			FROM {$wpdb->prefix}icl_languages AS l
			INNER JOIN {$wpdb->prefix}icl_locale_map AS m ON m.code = l.code
			LEFT JOIN {$wpdb->prefix}icl_language_preset_countries AS p ON p.preset_code = l.code AND p.country_code = l.country
			WHERE m.locale <> l.default_locale"
		);

		$this->result = null !== $rows && '' === (string) $wpdb->last_error;
		$error        = (string) $wpdb->last_error;

		if ( $rows && $this->result ) {
			$shipped = icl_get_languages_locales();
			$old     = [];

			foreach ( $rows as $row ) {
				$code      = strtolower( (string) $row->code );
				$locale    = (string) $row->locale;
				$default   = (string) $row->default_locale;
				$isShipped = isset( $shipped[ $code ] ) && 0 === strcasecmp( $shipped[ $code ], $default );

				if ( $isShipped && isset( self::LEGACY_LOCALES[ $code ] ) && 0 === strcasecmp( self::LEGACY_LOCALES[ $code ], $locale ) ) {
					$write = [ 'icl_locale_map', [ 'locale' => $default ], $locale ];
				} elseif ( ! $isShipped && '' !== $default && 0 === strcasecmp( (string) $row->pair_locale, $default ) ) {
					$isLegacy = isset( self::LEGACY_LOCALES[ $code ] ) && 0 === strcasecmp( self::LEGACY_LOCALES[ $code ], $locale );
					$write    = (int) $row->map_in_catalogue || $isLegacy
						? [ 'icl_locale_map', [ 'locale' => $default ], $locale ]
						: [ 'icl_languages', [ 'default_locale' => $locale ], $default ];
				} else {
					continue;
				}

				if ( false === $wpdb->update( $wpdb->prefix . $write[0], $write[1], [ 'code' => (string) $row->code ] ) ) {
					$this->result = false;
					$error        = (string) $wpdb->last_error;
					continue;
				}
				$old[ (string) $row->code ] = $write[2];
			}

			if ( $old ) {
				update_option( 'wpml_locale_map_repaired', (array) get_option( 'wpml_locale_map_repaired', [] ) + $old, false );

				$stLocales = get_option( 'wpml_st_language_locales' );
				if ( is_array( $stLocales ) ) {
					update_option( 'wpml_st_language_locales', array_merge( $stLocales, $old ) );
				}
			}
		}

		if ( $this->result ) {
			delete_option( self::ATTEMPTS_OPTION );

			return true;
		}

		$attempts = (int) get_option( self::ATTEMPTS_OPTION, 0 ) + 1;
		if ( $attempts < self::MAX_ATTEMPTS ) {
			update_option( self::ATTEMPTS_OPTION, $attempts, true );

			return false;
		}

		\WPML\PHP\Logger\error(
			'WPML could not repair the locale map after ' . $attempts . ' attempts'
			. ( '' !== $error ? ' (' . $error . ')' : '' )
			. '; it is not tried again. Catalan and the other languages WPML 3.x shipped a different locale for may keep their old map row.'
		);
		delete_option( self::ATTEMPTS_OPTION );
		$this->result = true;

		return true;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
