<?php

class WPML_ST_Translations_File_MO implements IWPML_ST_Translations_File {

	private $filepath;

	private $plural_forms;

	public function __construct( $filepath ) {
		$this->filepath = $filepath;
	}

	public function get_translations() {
		$mo          = new MO();
		$pomo_reader = new POMO_CachedFileReader( $this->filepath );

		$mo->import_from_reader( $pomo_reader );

		$this->plural_forms = isset( $mo->headers['Plural-Forms'] ) ? (string) $mo->headers['Plural-Forms'] : null;

		return self::get_translations_from_entries( $mo->entries );
	}

	public function get_plural_forms() {
		return $this->plural_forms;
	}

	public static function get_translations_from_entries( $entries ) {
		$translations = array();

		foreach ( $entries as $entry ) {
			$original       = str_replace( "\n", '\n', $entry->singular );
			$translations[] = new WPML_ST_Translations_File_Translation( $original, $entry->translations[0], $entry->context );

			if ( $entry->is_plural ) {
				$count = count( $entry->translations );
				for ( $form = 1; $form < $count; $form++ ) {
					if ( '' === (string) $entry->translations[ $form ] ) {
						continue;
					}
					$translations[] = new WPML_ST_Translations_File_Translation(
						$original . sprintf( WPML_ST_Translations_File_JED::PLURAL_SUFFIX_PATTERN, $form ),
						$entry->translations[ $form ],
						$entry->context
					);
				}
			}
		}

		return $translations;
	}
}
