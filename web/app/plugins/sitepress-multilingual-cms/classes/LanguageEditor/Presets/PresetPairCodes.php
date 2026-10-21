<?php

namespace WPML\LanguageEditor\Presets;

use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;
use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\LanguageEditor\Flags\FlagManifest;

class PresetPairCodes {

	private static $bundledIndex = null;

	public static function compute( array $presets, array $countriesByPreset, array $localeMap, array $pairCodes ) {
		$byLanguage = [];
		foreach ( $presets as $code => $p ) {
			$language = isset( $p['language'] ) && '' !== (string) $p['language']
				? (string) $p['language']
				: LanguagePairKey::languageSubtag( (string) $code, isset( $p['script'] ) ? $p['script'] : null );
			$byLanguage[ $language ][ $code ] = $p;
		}

		$localeOverride = [];
		foreach ( $countriesByPreset as $pc => $pcRows ) {
			foreach ( (array) $pcRows as $pcRow ) {
				if ( isset( $pcRow['wp_locale'] ) && '' !== (string) $pcRow['wp_locale'] ) {
					$localeOverride[ $pc ][ strtoupper( (string) $pcRow['country_code'] ) ] = (string) $pcRow['wp_locale'];
				}
			}
		}

		$out = [];
		foreach ( $byLanguage as $lang => $langPresets ) {
			$lang = (string) $lang;
			$plans = [];
			foreach ( $langPresets as $code => $p ) {
				$script   = $p['script'] !== null && $p['script'] !== '' ? (string) $p['script'] : null;
				$isRegion = $p['type'] === 'regional';
				$hasFlag  = $p['language_flag'] !== null && $p['language_flag'] !== '';

				$rows    = $countriesByPreset[ $code ] ?? [];
				$default = null;
				foreach ( $rows as $r ) {
					if ( (int) $r['is_default'] === 1 ) {
						$default = strtoupper( (string) $r['country_code'] );
					}
				}

				$entries = [];
				if ( $p['country_mode'] === 'required' ) {
					foreach ( $rows as $r ) {
						$cc = strtoupper( (string) $r['country_code'] );
						if ( $cc !== '' ) {
							$entries[] = [ $cc, $cc, $cc === (string) $default ];
						}
					}
				} else {
					$hasAnchor = isset( $pairCodes[ $code ][''] ) && '' !== (string) $pairCodes[ $code ][''];
					if ( $hasAnchor ) {
						$entries[] = [ '', $isRegion ? $default : null, true ];
					}
					foreach ( $rows as $r ) {
						$cc = strtoupper( (string) $r['country_code'] );
						if ( $cc !== '' && ! ( $hasAnchor && $isRegion && $cc === $default ) ) {
							$entries[] = [ $cc, $cc, ! $hasAnchor && $cc === (string) $default ];
						}
					}
				}

				$plans[ $code ] = [
					'script'   => $script,
					'isRegion' => $isRegion,
					'langFlag' => $hasFlag ? (string) $p['language_flag'] : null,
					'entries'  => $entries,
				];
			}

			$localeId    = [];
			$scriptForms = [];
			$write       = function ( string $code, $pickerCountry, $identityCountry, $resolved, $plan, $isDefault ) use ( &$out, &$localeId, &$scriptForms, $lang, $localeMap, $localeOverride ) {
				$override = isset( $localeOverride[ $code ][ strtoupper( (string) $identityCountry ) ] )
					? $localeOverride[ $code ][ strtoupper( (string) $identityCountry ) ]
					: null;

				$script = $plan['script'];
				if ( null === $override && null !== $script && ! LanguageDerivation::isPrimaryLocaleScript( $lang, $script ) ) {
					$bare = self::locale( $code, $lang, null, $identityCountry, $localeMap, $override );
					if ( $isDefault ) {
						$scriptForms[ $code ] = isset( $localeId[ $bare ] );
					}
					$useScript = isset( $scriptForms[ $code ] ) ? $scriptForms[ $code ] : isset( $localeId[ $bare ] );
					$locale    = $useScript
						? self::locale( $code, $lang, $script, $identityCountry, $localeMap, $override )
						: $bare;
				} else {
					$locale = self::locale( $code, $lang, $script, $identityCountry, $localeMap, $override );
				}

				$localeId[ $locale ]            = isset( $localeId[ $locale ] ) ? $localeId[ $locale ] : $resolved;
				$out[ $code ][ $pickerCountry ] = [
					'code'           => $resolved,
					'default_locale' => $locale,
					'flag'           => self::defaultFlag( $lang, $plan['script'], $identityCountry, $plan['langFlag'], $plan['isRegion'] ),
				];
			};

			foreach ( [ true, false ] as $primaries ) {
				foreach ( $plans as $code => $plan ) {
					$code = (string) $code;
					foreach ( $plan['entries'] as $e ) {
						if ( $e[2] !== $primaries ) {
							continue;
						}
						$write( $code, $e[0], $e[1], self::authoredCode( $pairCodes, $code, $e[0] ), $plan, $e[2] );
					}
				}
			}
		}

		return $out;
	}

	private static function authoredCode( array $pairCodes, $preset, $country ) {
		$country = strtoupper( (string) $country );
		if ( isset( $pairCodes[ $preset ][ $country ] ) && '' !== (string) $pairCodes[ $preset ][ $country ] ) {
			return (string) $pairCodes[ $preset ][ $country ];
		}
		throw new \InvalidArgumentException(
			sprintf(
				'Preset pair %s/%s has no authored code (LanguagePresetsData pair_codes).',
				esc_html( (string) $preset ),
				esc_html( '' === $country ? '-' : $country )
			)
		);
	}

	private static function locale( $code, $language, $script, $country, array $localeMap, $override = null ) {
		if ( null !== $override && '' !== (string) $override ) {
			return (string) $override;
		}

		$base = isset( $localeMap[ $code ] ) && '' !== (string) $localeMap[ $code ]
			? (string) $localeMap[ $code ]
			: null;

		if ( null === $base ) {
			$bare = isset( $localeMap[ $language ] ) ? (string) $localeMap[ $language ] : (string) $language;
			$base = ( null === $script || LanguageDerivation::isPrimaryLocaleScript( $language, $script ) )
				? $bare
				: strtolower( (string) $language ) . '_' . ucfirst( strtolower( (string) $script ) );
		}

		return self::withRegion( $base, $country );
	}

	private static function withRegion( $locale, $country ) {
		if ( ! $country ) {
			return $locale;
		}
		$parts  = explode( '_', (string) $locale );
		$result = $parts[0];
		if ( isset( $parts[1] ) && preg_match( '/^[A-Z][a-z]{3}$/', $parts[1] ) ) {
			$result .= '_' . $parts[1];
		}

		return $result . '_' . strtoupper( (string) $country );
	}

	public static function defaultFlag( $language, $script, $country, $langFlag, $isRegion ) {
		$countryFlag = ( null !== $country && '' !== (string) $country )
			? FlagManifest::instance()->countryFile( (string) $country )
			: null;

		return LanguageDerivation::defaultFlagFile( $language, $script, $country, $countryFlag, $langFlag, $isRegion );
	}

	public static function defaultCode( $presetCode, $country ) {
		$presetCode = strtolower( trim( (string) $presetCode ) );
		if ( '' === $presetCode ) {
			return null;
		}
		$country = strtoupper( trim( (string) $country ) );

		$bundled = self::bundledPreset( $presetCode );
		if ( null !== $bundled
			&& isset( $bundled['pair_codes'][ $country ] )
			&& '' !== (string) $bundled['pair_codes'][ $country ] ) {
			return (string) $bundled['pair_codes'][ $country ];
		}

		$composed = '' === $country ? $presetCode : $presetCode . '-' . strtolower( $country );

		return CatalogueSyncRunner::isLanguageCode( $composed ) ? $composed : $presetCode;
	}

	public static function defaultLocale( $presetCode, $country ) {
		$bundled = self::bundledPreset( strtolower( trim( (string) $presetCode ) ) );
		if ( null === $bundled ) {
			return null;
		}

		$country = strtoupper( trim( (string) $country ) );
		if ( '' !== $country
			&& isset( $bundled['wp_locale_by_country'][ $country ] )
			&& '' !== (string) $bundled['wp_locale_by_country'][ $country ] ) {
			return (string) $bundled['wp_locale_by_country'][ $country ];
		}

		return isset( $bundled['wp_code'] ) && '' !== (string) $bundled['wp_code']
			? (string) $bundled['wp_code']
			: null;
	}

	private static function bundledPreset( $presetCode ) {
		if ( null === self::$bundledIndex ) {
			$index = [];
			foreach ( LanguagePresetsData::data() as $row ) {
				$code = strtolower( trim( (string) ( isset( $row['code'] ) ? $row['code'] : '' ) ) );
				if ( '' === $code ) {
					continue;
				}

				$pairCodes = [];
				if ( isset( $row['pair_codes'] ) && is_array( $row['pair_codes'] ) ) {
					foreach ( $row['pair_codes'] as $cc => $pairCode ) {
						if ( '' !== (string) $pairCode ) {
							$pairCodes[ strtoupper( trim( (string) $cc ) ) ] = strtolower( trim( (string) $pairCode ) );
						}
					}
				}

				$locales = [];
				if ( isset( $row['wp_locale_by_country'] ) && is_array( $row['wp_locale_by_country'] ) ) {
					foreach ( $row['wp_locale_by_country'] as $cc => $locale ) {
						$cc = strtoupper( trim( (string) $cc ) );
						if ( '' !== $cc && '' !== (string) $locale ) {
							$locales[ $cc ] = (string) $locale;
						}
					}
				}

				$index[ $code ] = [
					'pair_codes'           => $pairCodes,
					'wp_locale_by_country' => $locales,
					'wp_code'              => isset( $row['wp_code'] ) && '' !== (string) $row['wp_code'] ? (string) $row['wp_code'] : null,
				];
			}
			self::$bundledIndex = $index;
		}

		return isset( self::$bundledIndex[ $presetCode ] ) ? self::$bundledIndex[ $presetCode ] : null;
	}

	private static function isDefaultScript( $language, $script ) {
		if ( $script === null ) {
			return true;
		}
		$defaults = LanguageDerivation::DEFAULT_SCRIPT;

		return isset( $defaults[ $language ] ) && $defaults[ $language ] === strtolower( (string) $script );
	}
}
