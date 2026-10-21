<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\Element\API\Entity\LanguageMapping;
use WPML\FP\Left;
use WPML\FP\Maybe;
use WPML\LanguageEditor\Adapter\LanguageRepository;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Setup\Option;
use WPML\TM\ATE\API\CachedATEAPI;
use function WPML\Container\make;

class CountryPhase implements PhaseProcessor {

	const ID = 'country';

	private $wpdb;

	private $languages;

	public function __construct( \wpdb $wpdb, ?LanguageRepository $languages = null ) {
		$this->wpdb      = $wpdb;
		$this->languages = $languages ? $languages : new LanguageRepository();
	}

	public function getId() {
		return self::ID;
	}

	public function applies( array $change ) {
		return isset( $change['country'] ) && ! empty( $change['code'] );
	}

	public function getTotal( array $change ) {
		return $this->applies( $change ) ? 1 : 0;
	}

	public function getChunkSize() {
		return 1;
	}

	public function isSkippable() {
		return true;
	}

	public function processChunk( array $change, $offset ) {
		$code = (string) $change['code'];

		$ateCountry = isset( $change['country']['ateCountry'] ) ? strtoupper( trim( (string) $change['country']['ateCountry'] ) ) : '';

		$before = $this->rowBefore( $code );

		$changed = $before['country'] !== $ateCountry;

		$reseed = ! empty( $change['refresh_names'] );

		if ( $changed ) {
			$this->writeIdentity( $code, $before, $ateCountry );
		}

		if ( $reseed ) {
			$this->languages->refreshNames( $code, $this->targetPreset( $change ), (string) $before['country'] );
		}

		if ( $changed || $reseed ) {
			CacheFlush::afterColumnWrite();
		}

		if ( empty( $change['country']['remap'] ) ) {
			return PhaseResult::ok( 1, [], [ 'remap' => 'not_requested' ] );
		}

		$target = $this->targetIso( $change );
		if ( null === $target ) {
			return PhaseResult::ok( 1, [ [ 'item' => $offset, 'reason' => 'catalogue_pair_unknown' ] ] );
		}
		$named = $target;

		try {
			$api = make( \WPML_TM_ATE_API::class );

			$existing = $this->mappingsFor( $api, $code );

			if ( null === $existing ) {
				return PhaseResult::retry();
			}

			if ( $existing && $this->allTargetsMatch( $existing, $target ) ) {
				return PhaseResult::ok(
					1,
					[],
					[
						'remap'  => 'skipped',
						'reason' => 'unchanged',
						'target' => $target,
					]
				);
			}

			$ateTarget = $this->ateTarget( $api, $target );
			if ( null === $ateTarget ) {
				return PhaseResult::retry();
			}
			$presetCode = $this->presetCodeOf( $code );
			if ( [] === $ateTarget && $this->isHomeCountry( $presetCode, $ateCountry ) ) {
				$fallback = $this->ateTarget( $api, $presetCode );
				if ( null === $fallback ) {
					return PhaseResult::retry();
				}
				if ( [] !== $fallback ) {
					$ateTarget = $fallback;
					$named     = $presetCode;
					if ( $existing && $this->allTargetsMatch( $existing, $ateTarget['iso'] ) ) {
						return PhaseResult::ok(
							1,
							[],
							[
								'remap'  => 'skipped',
								'reason' => 'unchanged',
								'target' => $named,
							]
						);
					}
				}
			}
			if ( [] === $ateTarget ) {
				return PhaseResult::ok(
					1,
					[ [ 'item' => $offset, 'reason' => 'ate_target_language_unavailable' ] ],
					[ 'target' => $target, 'kept' => ! empty( $existing ) ]
				);
			}

			$mapping = new LanguageMapping(
				$code,
				(string) ( isset( $change['name'] ) ? $change['name'] : $code ),
				$ateTarget['id'],
				$ateTarget['iso']
			);

			$created = false;
			if ( [] === $this->rowsAt( $existing, $ateTarget['iso'] ) ) {
				$result = $api->create_language_mapping( [ $mapping ] );

				if ( $result instanceof Left ) {
					$refusal = $this->refusalReason( $result );
					if ( null !== $refusal ) {
						return PhaseResult::ok(
							1,
							[ [ 'item' => $offset, 'reason' => 'ate_mapping_refused', 'detail' => $refusal ] ],
							[ 'target' => $target, 'kept' => ! empty( $existing ) ]
						);
					}
					return PhaseResult::retry();
				}
				$created = true;

				Option::removeLanguageMapping( $code );
				Option::addLanguageMapping( $mapping );
				CachedATEAPI::clearAllCaches();
			}

			$after = $created ? $this->mappingsFor( $api, $code ) : $existing;
			if ( null === $after ) {
				return PhaseResult::retry();
			}
			$staleIds = array_keys( array_diff_key( $after, $this->rowsAt( $after, $ateTarget['iso'] ) ) );
			if ( $staleIds ) {
				$removed = $api->remove_language_mapping( $staleIds );
				if ( false === $removed ) {
					return PhaseResult::retry();
				}
				CachedATEAPI::clearAllCaches();
			}

			return PhaseResult::ok(
				1,
				[],
				[
					'remap'  => 'remapped',
					'mode'   => $existing ? 'rekeyed' : 'created',
					'target' => $named,
				]
			);
		} catch ( \Throwable $e ) {
			return PhaseResult::retry();
		}
	}

	private function refusalReason( Left $result ) {
		$error = $result->bichain(
			function ( $value ) {
				return $value;
			},
			function () {
				return null;
			}
		);
		if ( ! $error instanceof \WP_Error || 'wpml_ate_language_mapping_failed' !== $error->get_error_code() ) {
			return null;
		}
		$data    = (array) $error->get_error_data();
		$reasons = [];
		foreach ( (array) ( isset( $data['mappings'] ) ? $data['mappings'] : [] ) as $refused ) {
			$refused = (array) $refused;
			$result  = isset( $refused['result'] ) ? (array) $refused['result'] : [];
			if ( isset( $result['reason'] ) && '' !== trim( (string) $result['reason'] ) ) {
				$reasons[] = trim( (string) $result['reason'] );
			}
		}

		return $reasons ? implode( '; ', array_unique( $reasons ) ) : 'refused';
	}

	private function ateTarget( \WPML_TM_ATE_API $api, $target ) {
		$languages = $api->get_available_languages();
		if ( ! is_array( $languages ) && ! is_object( $languages ) ) {
			return null;
		}
		$languages = (array) $languages;
		if ( [] === $languages ) {
			return null;
		}
		$want = strtolower( trim( (string) $target ) );
		foreach ( $languages as $language ) {
			$language = (array) $language;
			if ( isset( $language['iso'] ) && strtolower( (string) $language['iso'] ) === $want ) {
				if ( ! isset( $language['id'] ) || (int) $language['id'] <= 0 ) {
					return [];
				}

				return [
					'id'  => (int) $language['id'],
					'iso' => (string) $language['iso'],
				];
			}
		}

		return [];
	}

	private function rowBefore( $code ) {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}icl_languages WHERE code = %s LIMIT 1",
				$code
			),
			ARRAY_A
		);

		$read = function ( $key ) use ( $row ) {
			return is_array( $row ) && isset( $row[ $key ] ) && null !== $row[ $key ] ? trim( (string) $row[ $key ] ) : '';
		};

		return [
			'country'      => strtoupper( $read( 'country' ) ),
			'tag'          => $read( 'tag' ),
			'display_code' => $read( 'display_code' ),
			'bcp_47'       => $read( 'bcp_47' ),
		];
	}

	private function writeIdentity( $code, array $before, $newCountry ) {
		$data = [ 'country' => '' !== $newCountry ? $newCountry : null ];

		$tag = $this->derivedTag( $code, $before, $newCountry );
		if ( null !== $tag ) {
			$data['tag'] = $tag;
		}

		$bcp47 = $this->derivedBcp47( $code, $newCountry );
		if ( null !== $bcp47 ) {
			$data['bcp_47'] = $bcp47;
		}

		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_languages',
			$data,
			[ 'code' => $code ]
		);

		LanguageCodeResolution::resetStoredTags();
	}

	private function derivedBcp47( $code, $newCountry ) {
		if ( ! $this->hasBcp47Column() ) {
			return null;
		}

		$identity = LanguageCodeResolution::publishedIdentity( $code );
		if ( '' === $identity['language'] ) {
			return null;
		}

		$bcp47 = Bcp47::compose(
			$identity['language'],
			$identity['script'],
			'' !== $newCountry ? $newCountry : null
		);

		return '' !== $bcp47 ? $bcp47 : null;
	}

	private function hasBcp47Column() {
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare( "SHOW COLUMNS FROM `{$this->wpdb->prefix}icl_languages` LIKE %s", 'bcp_47' )
		);
	}

	private function derivedTag( $code, array $before, $newCountry ) {
		$tag = CountryTagDerivation::rewrite(
			$before['tag'],
			[
				CountryTagDerivation::forCountry( $code, $before['country'] ),
				CountryTagDerivation::legacyLocaleTag( $this->languages, $code, $before['country'] ),
				$code,
				$before['display_code'],
			],
			CountryTagDerivation::forCountry( $code, $newCountry )
		);

		if ( null === $tag ) {
			return null;
		}

		return '' === CustomLanguageFormat::validateHreflang( $tag ) ? $tag : null;
	}



	private function mappingsFor( $api, $code ) {
		$mapping = $api->get_language_mapping();

		if ( $mapping instanceof Maybe ) {
			if ( $mapping->isNothing() ) {
				return null;
			}
			$records = $mapping->getOrElse( null );
		} else {
			$records = $mapping;
		}

		if ( ! is_array( $records ) ) {
			return null;
		}

		$found = [];
		foreach ( $records as $record ) {
			$source = $this->recordField( $record, 'source_code', 'sourceCode' );
			$id     = $this->recordField( $record, 'id', 'id' );
			if ( null !== $id && strtolower( (string) $source ) === strtolower( $code ) ) {
				$found[ (int) $id ] = (string) $this->recordField( $record, 'target_code', 'targetCode' );
			}
		}

		return $found;
	}

	private function allTargetsMatch( array $existing, $target ) {
		return count( $existing ) === count( $this->rowsAt( $existing, $target ) );
	}

	private function rowsAt( array $mappings, $target ) {
		return array_filter(
			$mappings,
			function ( $current ) use ( $target ) {
				return '' !== $current && 0 === strcasecmp( (string) $current, (string) $target );
			}
		);
	}

	private function recordField( $record, $arrayKey, $property ) {
		if ( is_array( $record ) ) {
			return isset( $record[ $arrayKey ] ) ? $record[ $arrayKey ] : null;
		}
		if ( is_object( $record ) ) {
			if ( isset( $record->{$property} ) ) {
				return $record->{$property};
			}
			if ( isset( $record->{$arrayKey} ) ) {
				return $record->{$arrayKey};
			}
		}

		return null;
	}

	private function targetPreset( array $change ) {
		return isset( $change['country']['preset'] )
			? strtolower( trim( (string) $change['country']['preset'] ) )
			: '';
	}

	private function targetIso( array $change ) {
		$presetCode = $this->presetCodeOf( (string) $change['code'] );
		if ( '' === $presetCode ) {
			return null;
		}

		$ateCountry = isset( $change['country']['ateCountry'] )
			? strtoupper( trim( (string) $change['country']['ateCountry'] ) )
			: '';
		$country    = '' === $ateCountry ? null : $ateCountry;

		$pair = $this->languages->presetPair( $presetCode, $country );
		if ( null === $pair ) {
			$pair = $this->languages->presetPair( $presetCode, $country, true );
		}
		if ( null === $pair || ! isset( $pair['code'] ) || '' === trim( (string) $pair['code'] ) ) {
			return null;
		}

		return trim( (string) $pair['code'] );
	}

	private function presetCodeOf( $code ) {
		$resolved = LanguageCodeResolution::resolve( $code );
		if ( null !== $resolved ) {
			return (string) $resolved['preset_code'];
		}
		$preset = LanguageCodeResolution::presetByCode( LanguageCodeResolution::head( $code ) );

		return null !== $preset ? (string) $preset['code'] : '';
	}

	private function isHomeCountry( $presetCode, $country ) {
		if ( '' === $presetCode || '' === $country ) {
			return false;
		}
		$preset = $this->languages->preset( $presetCode, true );
		$home   = $preset && isset( $preset['default_country'] ) ? strtoupper( trim( (string) $preset['default_country'] ) ) : '';

		return '' !== $home && $home === strtoupper( $country );
	}
}
