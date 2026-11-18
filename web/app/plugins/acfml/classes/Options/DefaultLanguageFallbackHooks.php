<?php

namespace ACFML\Options;

use ACFML\FilteredAcfFieldReferenceTrait;
use ACFML\Strings\Package;

class DefaultLanguageFallbackHooks implements \IWPML_Frontend_Action {

	use FilteredAcfFieldReferenceTrait;

	const TEXT_FIELD_TYPES = [ 'text', 'textarea', 'wysiwyg' ];

	private $primedNamespaces = [];

	private $isRegisteredNamespace = [];

	public function add_hooks() {
		add_filter( 'acf/pre_load_metadata', [ $this, 'loadDefaultLanguageValue' ], 10, 4 );
	}

	public function loadDefaultLanguageValue( $null, $postId, $name, $hidden ) {
		$namespace = null === $null ? $this->getNamespaceOfSecondaryLanguage( $postId ) : null;
		if ( ! $namespace || '' === $name ) {
			return $null;
		}

		$languageReference = '_' . $postId . '_' . $name;
		$defaultReference  = '_' . $namespace . '_' . $name;
		if ( isset( $this->primedNamespaces[ $namespace ] ) ) {
			$this->primeOptions( [ $languageReference, $defaultReference ] );
		}

		if ( null !== get_option( $languageReference, null ) ) {
			return null;
		}

		$this->primeNamespace( $namespace, (string) $postId, $defaultReference );

		$reference = get_option( $defaultReference, null );
		if ( ! $this->isUntranslatedTextField( $reference, $name ) ) {
			return null;
		}

		return $hidden ? $reference : get_option( $namespace . '_' . $name, null );
	}

	private function primeNamespace( $namespace, $postId, $defaultReference ) {
		if ( isset( $this->primedNamespaces[ $namespace ] ) ) {
			return;
		}
		$this->primedNamespaces[ $namespace ] = true;

		$names = [ $defaultReference ];
		foreach ( $this->getOptionsPageFields( $namespace ) as $field ) {
			$fieldName = (string) ( $field['name'] ?? '' );
			if ( '' === $fieldName ) {
				continue;
			}
			$names[] = '_' . $postId . '_' . $fieldName;
			$names[] = '_' . $namespace . '_' . $fieldName;
			if ( in_array( $field['type'] ?? null, self::TEXT_FIELD_TYPES, true ) ) {
				$names[] = $namespace . '_' . $fieldName;
			}
		}

		$this->primeOptions( array_values( array_unique( $names ) ) );
	}

	private function getOptionsPageFields( $namespace ) {
		if ( ! function_exists( 'acf_get_options_pages' ) || ! function_exists( 'acf_get_field_groups' ) ) {
			return [];
		}

		$slugs = [];
		foreach ( (array) acf_get_options_pages() as $page ) {
			if ( $namespace === ( $page['post_id'] ?? null ) && isset( $page['menu_slug'] ) ) {
				$slugs[] = $page['menu_slug'];
			}
		}
		if ( ! $slugs ) {
			return [];
		}

		$fields = [];
		foreach ( (array) acf_get_field_groups() as $group ) {
			if ( empty( $group['active'] ) || ! $this->isLocatedOnOptionsPages( $group, $slugs ) ) {
				continue;
			}
			$groupKey = (string) ( $group['key'] ?? '' );
			if ( '' !== $groupKey && acf_have_local_fields( $groupKey ) ) {
				$fields = array_merge( $fields, (array) acf_get_local_fields( $groupKey ) );
			} elseif ( ! empty( $group['ID'] ) ) {
				$fields = array_merge( $fields, (array) acf_get_raw_fields( $group['ID'] ) );
			}
		}

		return $fields;
	}

	private function isLocatedOnOptionsPages( array $group, array $slugs ) {
		foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( 'options_page' === ( $rule['param'] ?? null ) && '==' === ( $rule['operator'] ?? null ) && in_array( $rule['value'] ?? null, $slugs, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function primeOptions( array $names ) {
		if ( function_exists( 'wp_prime_option_caches' ) ) {
			wp_prime_option_caches( $names );
		}
	}

	private function getNamespaceOfSecondaryLanguage( $postId ) {
		if ( ! is_string( $postId ) ) {
			return null;
		}

		$currentLanguage = acf_get_setting( CurrentLanguageHooks::ACF_CURRENT_LANGUAGE_SETTING );
		if ( ! is_string( $currentLanguage ) || '' === $currentLanguage || acf_get_setting( 'default_language' ) === $currentLanguage ) {
			return null;
		}

		$suffix = '_' . $currentLanguage;
		if ( strlen( $postId ) <= strlen( $suffix ) || substr( $postId, -strlen( $suffix ) ) !== $suffix ) {
			return null;
		}

		$namespace = substr( $postId, 0, -strlen( $suffix ) );

		return $this->isRegisteredNamespace( $namespace ) ? $namespace : null;
	}

	private function isRegisteredNamespace( $namespace ) {
		if ( ! isset( $this->isRegisteredNamespace[ $namespace ] ) ) {
			$pages     = function_exists( 'acf_get_options_pages' ) ? (array) acf_get_options_pages() : [];
			$postIds   = array_column( $pages, 'post_id' );
			$postIds[] = Package::OPTION_PACKAGE_NAMESPACE;

			if ( ! in_array( $namespace, $postIds, true ) ) {
				return false;
			}

			$this->isRegisteredNamespace[ $namespace ] = true;
		}

		return true;
	}

	private function isUntranslatedTextField( $reference, $name ) {
		$fieldKey = $this->normalizeAcfFieldReference( $reference );
		$field    = is_string( $fieldKey ) && '' !== $fieldKey ? acf_get_field( $fieldKey ) : null;

		return is_array( $field )
			&& $name === ( $field['name'] ?? null )
			&& in_array( $field['type'] ?? null, self::TEXT_FIELD_TYPES, true )
			&& isset( $field['wpml_cf_preferences'] )
			&& WPML_TRANSLATE_CUSTOM_FIELD === (int) $field['wpml_cf_preferences'];
	}
}
