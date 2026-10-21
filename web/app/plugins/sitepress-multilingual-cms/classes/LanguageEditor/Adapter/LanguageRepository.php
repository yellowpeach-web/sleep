<?php

namespace WPML\LanguageEditor\Adapter;

use WPML\Core\Component\LanguageEditor\Domain\Repository\LanguageRepositoryInterface;
use WPML\Element\API\Languages;
use WPML\LanguageEditor\ActiveLanguages;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\RtlLanguages;

class LanguageRepository implements LanguageRepositoryInterface {

	private static $optionalColumns = [ 'display_code', 'type', 'translation_paused', 'bcp_47' ];

	public function activeCodes(): array {
		return ActiveLanguages::codes();
	}

	public function activePairs(): array {
		return ActiveLanguages::pairs();
	}

	public function inactivePairs(): array {
		return ActiveLanguages::inactivePairs();
	}

	public function hasRetainedContent( string $code ): bool {
		return \WPML\Posts\TranslatedContentOfLanguages::hasAny( [ $code ] );
	}

	public function activeDisplayCodes(): array {
		return ActiveLanguages::displayCodes();
	}

	public function removedWithContentDisplayCodes(): array {
		$codes = array_keys( \WPML\Languages\RemovedLanguages::withContent() );

		if ( ! $codes ) {
			return [];
		}

		$tokens = [];
		foreach ( \WPML\LanguageEditor\RemovedLanguages\Directory::displayCodes( $codes ) as $code => $displayCode ) {
			$code        = strtolower( (string) $code );
			$displayCode = strtolower( (string) $displayCode );
			$tokens[ $code ] = '' !== $displayCode ? $displayCode : $code;
		}

		return $tokens;
	}

	public function displayCodeOf( string $code ): ?string {
		$codes       = \WPML\LanguageEditor\RemovedLanguages\Directory::displayCodes( [ $code ] );
		$displayCode = isset( $codes[ $code ] ) ? (string) $codes[ $code ] : '';

		return '' !== $displayCode ? $displayCode : null;
	}

	public function activeLocales(): array {
		return ActiveLanguages::locales();
	}

	public function activeBcp47Tags(): array {
		return ActiveLanguages::bcp47Tags();
	}

	public function isActive( string $code ): bool {
		return ActiveLanguages::isActive( $code );
	}

	public function rowId( string $code ): ?int {
		return ActiveLanguages::rowId( $code );
	}

	public function rowIsCustom( string $code ): ?bool {
		global $wpdb;

		if ( ! self::hasColumn( 'is_custom' ) ) {
			return null;
		}

		$isCustom = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_custom FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);

		return null === $isCustom ? null : (bool) (int) $isCustom;
	}

	public function isCustomIdentity( string $code ): bool {
		return LanguageCodeResolution::isCustomIdentity( $code );
	}

	public function preset( string $presetCode, bool $includeUnvouched = false ): ?array {
		$preset = ActiveLanguages::preset( $presetCode );
		if ( ! $preset ) {
			return null;
		}
		if ( ! $includeUnvouched && self::isUnvouched( $preset ) ) {
			return null;
		}

		return (array) $preset;
	}

	public function presetPair( string $presetCode, ?string $country, bool $includeUnvouched = false ): ?array {
		global $wpdb;

		$offerable = ! $includeUnvouched && self::pairsHaveUnvouchedColumn()
			? ' AND unvouched_at IS NULL'
			: '';

		$sql = "SELECT code, default_locale, flag
			 FROM {$wpdb->prefix}icl_language_preset_countries
			 WHERE preset_code = %s AND country_code = %s" . $offerable;

		$row = $wpdb->get_row(
			$wpdb->prepare( $sql, $presetCode, $country === null ? '' : strtoupper( $country ) )
		);
		if ( ! $row || null === $row->code ) {
			return null;
		}

		return [
			'code'           => (string) $row->code,
			'default_locale' => (string) $row->default_locale,
			'flag'           => (string) $row->flag,
			'country'        => ( $country === null || $country === '' ) ? null : strtoupper( $country ),
		];
	}

	public function presetOwningCode( string $code ): ?array {
		global $wpdb;

		$code = strtolower( trim( $code ) );
		if ( '' === $code ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT code, english_name FROM {$wpdb->prefix}icl_language_presets WHERE LOWER(code) = %s LIMIT 1",
				$code
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.code, p.english_name FROM {$wpdb->prefix}icl_language_presets p INNER JOIN {$wpdb->prefix}icl_language_preset_countries c ON c.preset_code = p.code WHERE LOWER(c.code) = %s LIMIT 1",
					$code
				),
				ARRAY_A
			);
		}

		if ( ! $row ) {
			return null;
		}

		return [
			'code'         => (string) $row['code'],
			'english_name' => (string) $row['english_name'],
		];
	}

	public function withdrawnCatalogueCode( string $code ): ?array {
		global $wpdb;

		$code = strtolower( trim( $code ) );
		if ( '' === $code ) {
			return null;
		}

		$presetRow = function ( $presetCode ) use ( $wpdb ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}icl_language_presets WHERE LOWER(code) = %s LIMIT 1",
					strtolower( (string) $presetCode )
				)
			);
		};

		$preset = $presetRow( $code );
		if ( $preset && ! self::isUnvouched( $preset ) ) {
			return null;
		}

		$pairs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}icl_language_preset_countries WHERE LOWER(code) = %s",
				$code
			)
		);
		$pairs = is_array( $pairs ) ? $pairs : [];

		$withdrawnPair = null;
		foreach ( $pairs as $pair ) {
			$owner = $presetRow( $pair->preset_code );
			if ( ! self::isUnvouched( $pair ) && $owner && ! self::isUnvouched( $owner ) ) {
				return null;
			}
			if ( null === $withdrawnPair ) {
				$withdrawnPair = $pair;
			}
		}

		if ( ! $preset && null === $withdrawnPair ) {
			return null;
		}

		if ( null === $withdrawnPair ) {
			return [
				'preset_code'    => (string) $preset->code,
				'country'        => null,
				'default_locale' => null,
				'flag'           => null,
			];
		}

		$country = isset( $withdrawnPair->country_code ) ? strtoupper( trim( (string) $withdrawnPair->country_code ) ) : '';

		return [
			'preset_code'    => (string) $withdrawnPair->preset_code,
			'country'        => '' === $country ? null : $country,
			'default_locale' => isset( $withdrawnPair->default_locale ) && '' !== (string) $withdrawnPair->default_locale ? (string) $withdrawnPair->default_locale : null,
			'flag'           => isset( $withdrawnPair->flag ) && '' !== (string) $withdrawnPair->flag ? (string) $withdrawnPair->flag : null,
		];
	}

	public function englishNameTaken( string $name, string $code ): bool {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_languages WHERE english_name = %s AND code <> %s",
				$name,
				$code
			)
		) > 0;
	}

	public function activeNameRows(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, code, english_name, country FROM {$wpdb->prefix}icl_languages WHERE active = %d",
				1
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				return [
					'id'           => (int) $row['id'],
					'code'         => (string) $row['code'],
					'english_name' => (string) $row['english_name'],
					'country'      => ( null === $row['country'] || '' === $row['country'] ) ? null : (string) $row['country'],
				];
			},
			is_array( $rows ) ? $rows : []
		);
	}

	public function englishNameOf( string $code ): ?string {
		global $wpdb;

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);

		return null === $name || '' === (string) $name ? null : (string) $name;
	}

	public function englishNameOwner( string $name, string $excludeCode ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, code, active, country FROM {$wpdb->prefix}icl_languages WHERE english_name = %s AND code <> %s",
				$name,
				$excludeCode
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return [
			'id'      => (int) $row['id'],
			'code'    => (string) $row['code'],
			'active'  => (bool) (int) $row['active'],
			'country' => ( null === $row['country'] || '' === $row['country'] ) ? null : (string) $row['country'],
		];
	}

	public function renameEnglishName( int $id, string $name ): void {
		global $wpdb;

		$old = $this->currentIdentity( $id );

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'english_name' => $name ],
			[ 'id' => $id ]
		);

		if ( false === $updated ) {
			\WPML\PHP\Logger\error(
				sprintf( 'Failed to rename language row %d: %s', $id, $wpdb->last_error )
			);

			throw new SaveFailedException(
				esc_html( sprintf( 'Failed to rename language row %d.', $id ) )
			);
		}

		$this->sweepMatrixNames( $old, $name );

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}
	}

	private function currentIdentity( int $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT code, english_name FROM {$wpdb->prefix}icl_languages WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? (array) $row : null;
	}

	private function sweepMatrixNames( $old, string $name ) {
		global $wpdb;

		if ( $old && '' !== (string) $old['english_name'] && (string) $old['english_name'] !== $name ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_languages_translations SET name = %s
					 WHERE language_code = %s AND name = %s",
					$name,
					(string) $old['code'],
					(string) $old['english_name']
				)
			);
		}

		\WPML\LanguageEditor\LanguageNames::resetCache();
		\WPML\Languages\RemovedLanguages::resetCache();
	}

	public function reactivate( int $id, array $fields ): void {
		global $wpdb;

		$old = $this->currentIdentity( $id );

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_languages',
			self::withoutMissingColumns(
				[
					'active'         => 1,
					'english_name'   => $fields['english_name'],
					'default_locale' => $fields['default_locale'],
					'tag'            => $fields['tag'],
					'country'        => $fields['country'],
					'type'           => $fields['type'],
					'display_code'   => $fields['display_code'],
					'bcp_47'         => isset( $fields['bcp_47'] ) ? $fields['bcp_47'] : null,
					'is_rtl'         => isset( $fields['is_rtl'] ) ? $fields['is_rtl'] : null,
					'is_custom'      => 0,
				]
			),
			[ 'id' => $id ]
		);

		if ( false === $updated ) {
			\WPML\PHP\Logger\error(
				sprintf( 'Failed to reactivate language row %d: %s', $id, $wpdb->last_error )
			);

			throw new SaveFailedException(
				esc_html( sprintf( 'Failed to reactivate language row %d.', $id ) )
			);
		}

		$this->sweepMatrixNames( $old, (string) $fields['english_name'] );

		LanguageCodeResolution::resetStoredTags();
		RtlLanguages::resetStoredOverrides();

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}
	}

	public function insert( array $fields ): ?int {
		global $wpdb;

		$languageId = Languages::add(
			$fields['code'],
			$fields['english_name'],
			$fields['default_locale'],
			0,
			1,
			0,
			$fields['tag'],
			$fields['country']
		);

		if ( ! $languageId ) {
			return null;
		}

		$this->stampAfterInsert(
			(int) $languageId,
			[
				'display_code' => $fields['display_code'],
				'type'         => $fields['type'],
				'bcp_47'       => isset( $fields['bcp_47'] ) ? $fields['bcp_47'] : null,
				'is_rtl'       => isset( $fields['is_rtl'] ) ? $fields['is_rtl'] : null,
			]
		);

		LanguageCodeResolution::resetStoredTags();
		RtlLanguages::resetStoredOverrides();

		do_action( 'wpml_update_active_languages' );

		return (int) $languageId;
	}

	public function insertCustom( array $fields ): ?int {
		global $wpdb;

		$existingId = $this->rowId( $fields['code'] );
		if ( $existingId !== null ) {
			if ( false === $this->rowIsCustom( (string) $fields['code'] ) ) {
				return null;
			}

			$old = $this->currentIdentity( $existingId );

			$updated = $wpdb->update(
				$wpdb->prefix . 'icl_languages',
				self::withoutMissingColumns(
					[
						'active'         => 1,
						'english_name'   => $fields['english_name'],
						'default_locale' => $fields['default_locale'],
						'tag'            => $fields['tag'],
						'country'        => $fields['country'],
						'type'           => $fields['type'],
						'display_code'   => $fields['display_code'],
						'is_custom'      => 1,
						'bcp_47'         => null,
						'is_rtl'         => isset( $fields['is_rtl'] ) ? (int) $fields['is_rtl'] : null,
						'encode_url'     => ! empty( $fields['encode_url'] ) ? 1 : 0,
					]
				),
				[ 'id' => $existingId ]
			);

			if ( false === $updated ) {
				return null;
			}

			$this->sweepMatrixNames( $old, (string) $fields['english_name'] );

			LanguageCodeResolution::resetStoredTags();
		RtlLanguages::resetStoredOverrides();

			if ( $updated ) {
				do_action( 'wpml_update_active_languages' );
			}

			return (int) $existingId;
		}

		$languageId = Languages::add(
			$fields['code'],
			$fields['english_name'],
			$fields['default_locale'],
			0,
			1,
			0,
			$fields['tag'],
			$fields['country']
		);

		if ( ! $languageId ) {
			return null;
		}

		$this->stampAfterInsert(
			(int) $languageId,
			[
				'display_code' => $fields['display_code'],
				'type'         => $fields['type'],
				'is_custom'    => 1,
				'encode_url'   => ! empty( $fields['encode_url'] ) ? 1 : 0,
				'is_rtl'       => isset( $fields['is_rtl'] ) ? (int) $fields['is_rtl'] : null,
			]
		);

		LanguageCodeResolution::resetStoredTags();
		RtlLanguages::resetStoredOverrides();

		do_action( 'wpml_update_active_languages' );

		return (int) $languageId;
	}

	public function deactivate( string $code ): void {
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'active' => 0 ],
			[ 'code' => $code ]
		);

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}
	}

	public function setFlag( string $code, string $flagFile ): void {
		if ( self::isCustomFlagUrl( $flagFile ) ) {
			$this->storeFlag( $code, basename( (string) wp_parse_url( $flagFile, PHP_URL_PATH ) ), 1 );

			return;
		}

		if ( '' !== $flagFile && '.svg' !== substr( $flagFile, -4 ) ) {
			$flagFile .= '.svg';
		}
		$this->storeFlag( $code, $flagFile, 0 );
	}

	private function storeFlag( string $code, string $flagFile, int $fromTemplate ): void {
		$current = $this->storedFlagRow( $code );
		$changed = ! $current
			|| (string) ( $current->flag ?? '' ) !== $flagFile
			|| (int) ( $current->from_template ?? 0 ) !== $fromTemplate;

		Languages::setFlag( $code, $flagFile, $fromTemplate );

		if ( $changed ) {
			\WPML_Flags::invalidate();
		}
	}

	private function storedFlagRow( string $code ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT flag, from_template FROM {$wpdb->prefix}icl_flags WHERE lang_code = %s",
				$code
			)
		);
	}

	public function offeredFlagsFor( string $code ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT code, country FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);
		if ( null === $row ) {
			return null;
		}
		$country = isset( $row->country ) && '' !== (string) $row->country ? strtoupper( (string) $row->country ) : null;

		$pairsTable = $wpdb->prefix . 'icl_language_preset_countries';
		$columns   = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$pairsTable}`" );
		$withOffer = in_array( 'offerable_flags', $columns, true );
		$pair      = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT preset_code, flag' . ( $withOffer ? ', offerable_flags' : '' ) . " FROM `{$pairsTable}` WHERE code = %s",
				$code
			)
		);
		if ( null === $pair ) {
			return null;
		}

		$allowed = array_merge(
			[ 'nil.svg', '@code' ],
			\WPML\LanguageEditor\Flags\FlagManifest::instance()->files()
		);
		$push    = function ( $file ) use ( &$allowed ) {
			$file = trim( (string) $file );
			if ( '' === $file ) {
				return;
			}

			$name = \WPML\LanguageEditor\Flags\FlagFile::normalize( $file );
			if ( '' === $name ) {
				$name = strtolower( basename( (string) wp_parse_url( $file, PHP_URL_PATH ) ) );
			}
			if ( '' !== $name ) {
				$allowed[] = $name;
			}
		};

		$current = $this->storedFlagRow( $code );
		if ( $current && empty( $current->from_template ) && isset( $current->flag ) ) {
			$push( $current->flag );
		}

		$push( $pair->flag ?? '' );

		$countries = \WPML\LanguageEditor\PageData::countries();
		if ( null !== $country && isset( $countries[ $country ]['flag'] ) ) {
			$push( $countries[ $country ]['flag'] );
		}
		$presetCode = (string) ( $pair->preset_code ?? '' );
		if ( '' !== $presetCode ) {
			$preset = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT language_flag FROM {$wpdb->prefix}icl_language_presets WHERE code = %s",
					$presetCode
				)
			);
			if ( $preset && ! empty( $preset->language_flag ) ) {
				$push( $preset->language_flag );
			}

			$offeredPairs = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT country_code, flag FROM `{$pairsTable}` WHERE preset_code = %s",
					$presetCode
				)
			);
			foreach ( $offeredPairs as $offeredPair ) {
				$push( $offeredPair->flag ?? '' );

				$offeredCountry = strtoupper( trim( (string) ( $offeredPair->country_code ?? '' ) ) );
				if ( '' !== $offeredCountry && isset( $countries[ $offeredCountry ]['flag'] ) ) {
					$push( $countries[ $offeredCountry ]['flag'] );
				}
			}
		}

		if ( $withOffer && ! empty( $pair->offerable_flags ) ) {
			foreach ( explode( ',', (string) $pair->offerable_flags ) as $granted ) {
				$push( $granted );
			}
		}

		return array_values( array_unique( $allowed ) );
	}

	private static function isCustomFlagUrl( string $flagFile ): bool {
		return (bool) preg_match( '#^(https?:)?//#', $flagFile );
	}

	public function hasFlag( string $code ): bool {
		global $wpdb;

		$flag = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT flag FROM {$wpdb->prefix}icl_flags WHERE lang_code = %s",
				$code
			)
		);

		return is_string( $flag ) && '' !== $flag;
	}

	public function countryFlag( string $code ): ?string {
		global $wpdb;

		$code = strtoupper( trim( $code ) );

		if ( '' === $code ) {
			return null;
		}

		$flag = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT flag FROM {$wpdb->prefix}icl_countries WHERE code = %s",
				$code
			)
		);

		return ( null === $flag || '' === $flag ) ? null : (string) $flag;
	}

	public function countryName( string $code ): ?string {
		global $wpdb;

		$code = strtoupper( trim( $code ) );

		if ( '' === $code ) {
			return null;
		}

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT english_name FROM {$wpdb->prefix}icl_countries WHERE code = %s",
				$code
			)
		);

		return ( null === $name || '' === $name ) ? null : (string) $name;
	}

	public function setTag( string $code, ?string $tag ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'tag' => ( null === $tag ? null : (string) $tag ) ],
			[ 'code' => $code ]
		);
	}

	public function refreshNames( string $code, string $presetCode = '', string $previousCountry = '' ): void {
		\WPML\LanguageEditor\LanguageNames::reseedForIdentityChange( $code, $presetCode, $previousCountry );
	}

	public function setEncodeUrl( string $code, bool $encode ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'encode_url' => $encode ? 1 : 0 ],
			[ 'code' => $code ]
		);
	}

	public function setRtl( string $code, ?bool $rtl ): void {
		global $wpdb;

		$row = self::withoutMissingColumns( [ 'is_rtl' => null === $rtl ? null : ( $rtl ? 1 : 0 ) ] );
		if ( ! $row ) {
			return;
		}

		$wpdb->update( $wpdb->prefix . 'icl_languages', $row, [ 'code' => $code ] );

		RtlLanguages::resetStoredOverrides();
	}


	private function stampAfterInsert( int $languageId, array $stamp ) {
		global $wpdb;

		$stamp = self::withoutMissingColumns( $stamp );

		if ( ! $stamp ) {
			return;
		}

		$updated = $wpdb->update( $wpdb->prefix . 'icl_languages', $stamp, [ 'id' => $languageId ] );

		if ( false === $updated ) {
			\WPML\PHP\Logger\error(
				sprintf( 'Failed to stamp language row %d: %s', $languageId, $wpdb->last_error )
			);

			throw new SaveFailedException(
				esc_html( sprintf( 'Failed to stamp language row %d.', $languageId ) )
			);
		}
	}

	private static function withoutMissingColumns( array $fields ) {
		foreach ( self::$optionalColumns as $column ) {
			if ( array_key_exists( $column, $fields ) && ! self::hasColumn( $column ) ) {
				unset( $fields[ $column ] );
			}
		}

		return $fields;
	}

	private static function hasColumn( $column ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s",
				$column
			)
		);
	}

	private static function isUnvouched( $row ) {
		return isset( $row->unvouched_at ) && '' !== (string) $row->unvouched_at;
	}

	private static function pairsHaveUnvouchedColumn() {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_preset_countries` LIKE %s",
				'unvouched_at'
			)
		);
	}

	public function setDisplayCode( string $code, string $displayCode ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_languages',
			[ 'display_code' => $displayCode ],
			[ 'code' => $code ]
		);

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}

		return false !== $updated;
	}

	public function storedDisplayCode( string $code ): ?string {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT display_code FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);

		return null === $value ? null : (string) $value;
	}

}
