<?php

namespace WPML\ST\TranslationFile;

use WPML\Collect\Support\Collection;
use WPML\ST\TranslateWpmlString;

class StringsRetrieve {

	const KEY_JOIN = '::JOIN::';

	const PLURAL_FORM_PATTERN = '/^(.+) \[plural ([0-9]+)\]$/';

	private $string_retrieve;

	public function __construct( \WPML\ST\DB\Mappers\StringsRetrieve $string_retrieve ) {
		$this->string_retrieve = $string_retrieve;
	}


	public function get( $domain, $language, $modified_mo_only ) {
		return $this->loadFromDb( $language, $domain, $modified_mo_only )
					->filter(
						function ( $string ) {
							return (bool) $string['translation'];
						}
					)
					->mapToGroups(
						function ( array $string ) {
							return $this->groupPluralFormsOfSameString( $string );
						}
					)
					->map(
						function ( Collection $strings, $key ) {
							return $this->buildStringEntity( $strings, $key );
						}
					)
					->filter()
					->values()
					->toArray();
	}

	private function loadFromDb( $language, $domain, $modified_mo_only = false ) {
		$rows = $this->string_retrieve->get( $language, $domain, $modified_mo_only );

		if ( $modified_mo_only && $rows ) {
			$rows = $this->addOtherFormsOfChangedPluralStrings( $rows, $language, $domain );
		}

		if ( 'default' === strtolower( (string) $domain ) ) {
			$rows = DefaultDomainResolver::resolve( $rows );
		}

		$result = \wpml_collect( $rows );

		return $result->map(
			function ( $row ) {
				return $this->parseResult( $row );
			}
		);
	}

	private function addOtherFormsOfChangedPluralStrings( array $rows, $language, $domain ) {
		$pluralRows = $this->string_retrieve->getPluralFormRows( $language, $domain );
		if ( ! $pluralRows ) {
			return $rows;
		}

		$pluralRowsByGroup = [];
		foreach ( $pluralRows as $row ) {
			$group = $this->getFormGroup( $row );
			if ( null !== $group && null !== $group['form'] ) {
				$pluralRowsByGroup[ $group['key'] ][] = $row;
			}
		}

		$present = [];
		$changed = [];
		foreach ( $rows as $row ) {
			$present[ self::rowKey( $row ) ] = true;
			$group                           = $this->getFormGroup( $row );
			if ( null !== $group && isset( $pluralRowsByGroup[ $group['key'] ] ) ) {
				$changed[ $group['key'] ] = $group;
			}
		}

		if ( ! $changed ) {
			return $rows;
		}

		$singulars = [];
		foreach ( $changed as $key => $group ) {
			foreach ( $pluralRowsByGroup[ $key ] as $row ) {
				if ( ! isset( $present[ self::rowKey( $row ) ] ) ) {
					$rows[]                          = $row;
					$present[ self::rowKey( $row ) ] = true;
				}
			}
			$singulars[] = $group['singular'];
		}

		foreach ( $this->string_retrieve->getRowsByOriginals( $language, $domain, $singulars ) as $row ) {
			$group = $this->getFormGroup( $row );
			if ( null !== $group && null === $group['form'] && isset( $changed[ $group['key'] ] ) && ! isset( $present[ self::rowKey( $row ) ] ) ) {
				$rows[]                          = $row;
				$present[ self::rowKey( $row ) ] = true;
			}
		}

		return $rows;
	}

	private function getFormGroup( array $row ) {
		if ( TranslateWpmlString::canTranslateWithMO( $row['original'], $row['name'] ) ) {
			return null;
		}

		$singular = (string) $row['original'];
		$form     = null;
		if ( preg_match( self::PLURAL_FORM_PATTERN, $singular, $matches ) ) {
			$singular = $matches[1];
			$form     = (int) $matches[2];
		}

		return [
			'key'      => $singular . self::KEY_JOIN . (string) $row['gettext_context'],
			'singular' => $singular,
			'form'     => $form,
		];
	}

	private static function rowKey( array $row ) {
		return ( isset( $row['source_context'] ) ? $row['source_context'] : '' ) . self::KEY_JOIN . $row['id'];
	}

	private function parseResult( array $row_data ) {
		return [
			'id'          => $row_data['id'],
			'original'    => $row_data['original'],
			'context'     => $row_data['gettext_context'],
			'translation' => self::parseTranslation( $row_data ),
			'name'        => $row_data['name'],
		];
	}

	public static function parseTranslation( array $row_data ) {
		$value = null;

		if ( self::hasTranslatedValue( $row_data ) ) {
			$value = $row_data['translated'];
		} elseif ( ! empty( $row_data['mo_string'] ) ) {
			$value = $row_data['mo_string'];
		}

		return $value;
	}

	public static function hasTranslatedValue( array $row_data ) {
		if ( empty( $row_data['translated'] ) ) {
			return false;
		}

		return in_array( $row_data['status'], [ ICL_TM_COMPLETE, ICL_TM_NEEDS_UPDATE ] );
	}

	private function groupPluralFormsOfSameString( array $string ) {
		$groupKey = $this->getPluralGroupKey( $string );

		if ( preg_match( self::PLURAL_FORM_PATTERN, $string['original'], $matches ) ) {
			$string['original'] = $matches[1];
			$string['index']    = $matches[2];
		} else {
			$string['index'] = null;
		}

		return [
			$string['original'] . self::KEY_JOIN . $string['context'] . self::KEY_JOIN . $groupKey => $string,
		];
	}

	private function getPluralGroupKey( array $string ) {
		$cannotBelongToPluralGroup = TranslateWpmlString::canTranslateWithMO( $string['original'], $string['name'] );

		if ( $cannotBelongToPluralGroup ) {
			return $string['name'];
		}

		return '';
	}

	private function buildStringEntity( Collection $strings, $key ) {
		$forms = [];
		$name  = null;
		foreach ( $strings as $string ) {
			$form = (int) $string['index'];
			if ( ! array_key_exists( $form, $forms ) ) {
				$forms[ $form ] = $string['translation'];
				if ( 0 === $form ) {
					$name = $string['name'];
				}
			}
		}
		ksort( $forms );

		if ( ! array_key_exists( 0, $forms ) ) {
			return null;
		}

		list( $original, $context ) = explode( self::KEY_JOIN, $key );
		$isPlural                   = count( $forms ) > 1 && array_keys( $forms ) === range( 0, count( $forms ) - 1 );
		$translations               = $isPlural ? array_values( $forms ) : [ $forms[0] ];
		$stringEntity               = new StringEntity( $original, $translations, $context, $isPlural ? $original : null );
		$stringEntity->set_name( $name );

		return $stringEntity;
	}
}
