<?php

namespace WPML\Upgrade\Commands;

class BackfillLanguageTypeColumn extends MigrateLanguagesToCountryModel implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	public function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		if ( ! $this->has_country_model_columns() ) {
			return false;
		}

		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . 'icl_languages';

		$presets = self::index_presets();

		$rows = $wpdb->get_results( "SELECT id, code, default_locale FROM `{$table}` WHERE type IS NULL ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$assignment = self::resolve( (string) $row->code, (string) $row->default_locale, $presets );

			$wpdb->update(
				$table,
				[ 'type' => $assignment['type'] ],
				[ 'id' => (int) $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		self::forgetRowIdentityMemo();

		$this->record_undetermined_flag();

		$this->result = true;

		return true;
	}
}
