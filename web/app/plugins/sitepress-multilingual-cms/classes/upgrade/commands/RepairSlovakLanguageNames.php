<?php

namespace WPML\Upgrade\Commands;

class RepairSlovakLanguageNames implements \IWPML_Upgrade_Command {

	const TABLE_NAME           = 'icl_languages_translations';
	const LANGUAGE_CODE        = 'sk';
	const CLEAR_PENDING_OPTION = 'wpml_repair_slovak_names_clear_pending';

	const NAMES = [
		'ar'      => [ 'السلافية', 'السلوفاكية' ],
		'el'      => [ 'Σλαβική', 'Σλοβακικά' ],
		'es'      => [ 'Eslavo', 'Eslovaco' ],
		'fi'      => [ 'Slaavi', 'Slovakki' ],
		'fr'      => [ 'Slave', 'Slovaque' ],
		'he'      => [ 'סלאבית', 'סלובקית' ],
		'it'      => [ 'Slavo', 'Slovacco' ],
		'ja'      => [ 'スラヴ語派', 'スロバキア語' ],
		'ko'      => [ '슬라브어', '슬로바키아어' ],
		'nl'      => [ 'Slavisch', 'Slowaaks' ],
		'pt-br'   => [ 'Eslavo', 'Eslovaco' ],
		'pt-pt'   => [ 'Eslavo', 'Eslovaco' ],
		'ro'      => [ 'Slavă', 'Slovacă' ],
		'ru'      => [ 'Славянский', 'Словацкий' ],
		'sv'      => [ 'Slavisk', 'Slovakiska' ],
		'th'      => [ 'สลาวิก', 'สโลวัก' ],
		'tr'      => [ 'Slav dili', 'Slovakça' ],
		'zh-hans' => [ '斯拉夫语', '斯洛伐克语' ],
		'zh-hant' => [ '斯拉夫語', '斯洛伐克語' ],
	];

	private $schema;

	private $results;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run_admin() {
		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE_NAME;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->results = true;

			return $this->results;
		}

		$repaired = 0;
		$failed   = false;
		foreach ( self::NAMES as $display => list( $old, $right ) ) {
			$stored = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT name FROM {$wpdb->prefix}icl_languages_translations WHERE language_code = %s AND display_language_code = %s",
					self::LANGUAGE_CODE,
					$display
				)
			);
			if ( '' !== (string) $wpdb->last_error ) {
				$failed = true;
				continue;
			}
			if ( $old !== $stored ) {
				continue;
			}

			if ( 0 === $repaired && ! get_option( self::CLEAR_PENDING_OPTION ) ) {
				update_option( self::CLEAR_PENDING_OPTION, 1, false );
			}

			$changed = $wpdb->update(
				$table,
				[ 'name' => $right ],
				[
					'language_code'         => self::LANGUAGE_CODE,
					'display_language_code' => $display,
					'name'                  => $old,
				],
				[ '%s' ],
				[ '%s', '%s', '%s' ]
			);

			if ( false === $changed ) {
				$failed = true;
				continue;
			}

			$repaired += (int) $changed;
		}

		if ( get_option( self::CLEAR_PENDING_OPTION ) ) {
			if ( $this->clearLanguageNameCaches() ) {
				delete_option( self::CLEAR_PENDING_OPTION );
			} else {
				$failed = true;
			}
		}

		$this->results = ! $failed;

		return $this->results;
	}

	private function clearLanguageNameCaches() {
		global $sitepress;

		if ( function_exists( 'wpml_language_cache_rotate_epoch' ) && false === wpml_language_cache_rotate_epoch() ) {
			return false;
		}

		if ( $sitepress ) {
			$sitepress->get_language_name_cache()->clear();
		}

		\WPML\LanguageEditor\LanguageNames::resetCache();

		return true;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}
}
