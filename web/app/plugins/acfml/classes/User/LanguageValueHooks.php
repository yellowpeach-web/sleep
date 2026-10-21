<?php

namespace ACFML\User;

use ACFML\Field\RelationalIds;
use ACFML\FilteredAcfFieldReferenceTrait;

class LanguageValueHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	use FilteredAcfFieldReferenceTrait;

	const PRIORITY_AFTER_FIELD_TYPES = 20;

	const USER_POST_ID_PREFIX = 'user_';

	private $fallbackReferences;

	private $isSharedByFieldName = [];

	public function __construct( FallbackReferences $fallbackReferences ) {
		$this->fallbackReferences = $fallbackReferences;
	}

	public function add_hooks() {
		add_filter( 'acf/pre_load_value', [ $this, 'rememberFieldBeforeLoad' ], 10, 3 );
		add_filter( 'acf/pre_update_value', [ $this, 'rememberFieldBeforeUpdate' ], 10, 4 );
		add_action( 'acf/delete_value', [ $this, 'rememberFieldBeforeDelete' ], 10, 3 );
		add_filter( 'acf/pre_load_metadata', [ $this, 'loadLanguageValue' ], 10, 4 );
		add_filter( 'acf/pre_update_metadata', [ $this, 'updateLanguageValue' ], 10, 5 );
		add_filter( 'acf/pre_delete_metadata', [ $this, 'deleteLanguageValue' ], 10, 4 );
		add_filter( 'acf/update_value', [ $this, 'repointFallbacksBeforeSave' ], self::PRIORITY_AFTER_FIELD_TYPES, 3 );
		add_action( 'wpml_language_has_switched', [ $this, 'forgetLoadedValues' ] );
	}

	public static function getLanguageKey( $fieldName, $languageCode ) {
		return $fieldName . '_' . $languageCode;
	}

	public function rememberFieldBeforeLoad( $value, $postId, $field ) {
		$this->rememberField( $postId, $field );

		return $value;
	}

	public function rememberFieldBeforeUpdate( $result, $value, $postId, $field ) {
		$this->rememberField( $postId, $field );

		return $result;
	}

	public function rememberFieldBeforeDelete( $postId, $fieldName, $field ) {
		$this->rememberField( $postId, $field );
	}

	public function loadLanguageValue( $value, $postId, $fieldName, $isReference ) {
		$userId       = self::getUserId( $postId );
		$languageCode = $this->getLanguageOfOwnValue( $userId, $fieldName, $value, $isReference );
		if ( ! $languageCode ) {
			return $value;
		}

		$languageValues = get_metadata( 'user', $userId, self::getLanguageKey( $fieldName, $languageCode ), false );
		if ( $languageValues ) {
			return reset( $languageValues );
		}

		return $this->getDefaultLanguageFallback( $userId, $fieldName, $languageCode );
	}

	public function updateLanguageValue( $result, $postId, $fieldName, $value, $isReference ) {
		$userId       = self::getUserId( $postId );
		$languageCode = $this->getLanguageOfOwnValue( $userId, $fieldName, $result, $isReference );
		if ( ! $languageCode ) {
			return $result;
		}

		return update_metadata( 'user', $userId, self::getLanguageKey( $fieldName, $languageCode ), $value );
	}

	public function deleteLanguageValue( $result, $postId, $fieldName, $isReference ) {
		$userId       = self::getUserId( $postId );
		$languageCode = $this->getLanguageOfOwnValue( $userId, $fieldName, $result, $isReference );
		if ( ! $languageCode ) {
			return $result;
		}

		return delete_metadata( 'user', $userId, self::getLanguageKey( $fieldName, $languageCode ) );
	}

	public function repointFallbacksBeforeSave( $value, $postId, $field ) {
		$userId       = self::getUserId( $postId );
		$languageCode = $userId && RelationalIds::isRelational( $field ) && ! self::isShared( $field ) ? self::getSecondaryLanguage() : null;
		if ( ! $languageCode ) {
			return $value;
		}

		return $this->fallbackReferences->repointBeforeSave( $value, $userId, $field, $languageCode );
	}

	public function forgetLoadedValues() {
		$loadedValues = acf_get_store( 'values' );

		foreach ( array_keys( (array) $loadedValues->get_data() ) as $cacheKey ) {
			if ( 0 === strpos( (string) $cacheKey, self::USER_POST_ID_PREFIX ) ) {
				$loadedValues->remove( $cacheKey );
			}
		}
	}

	private function getDefaultLanguageFallback( int $userId, string $fieldName, string $languageCode ) {
		$field = $this->getRelationalField( $userId, $fieldName );
		if ( ! $field ) {
			return null;
		}

		$defaultValues = get_metadata( 'user', $userId, $fieldName, false );

		return $defaultValues ? RelationalIds::translateAll( reset( $defaultValues ), $field, $languageCode ) : null;
	}

	private function getRelationalField( int $userId, string $fieldName ) {
		$reference = $this->normalizeAcfFieldReference( get_metadata( 'user', $userId, '_' . $fieldName, true ) );
		$field     = is_string( $reference ) && '' !== $reference ? acf_get_field( $reference ) : null;

		return is_array( $field ) && RelationalIds::isRelational( $field ) ? $field : null;
	}

	private function rememberField( $postId, $field ) {
		if ( self::getUserId( $postId ) && isset( $field['name'] ) && is_string( $field['name'] ) ) {
			$this->isSharedByFieldName[ $field['name'] ] = self::isShared( $field );
		}
	}

	private function getLanguageOfOwnValue( int $userId, string $fieldName, $preemptedResult, bool $isReference ): ?string {
		if ( ! $userId || null !== $preemptedResult || $isReference || ! empty( $this->isSharedByFieldName[ $fieldName ] ) ) {
			return null;
		}

		return self::getSecondaryLanguage();
	}

	private static function isShared( array $field ): bool {
		if ( ! self::isCopyField( $field ) ) {
			return false;
		}

		$parentField = empty( $field['parent'] ) ? null : acf_get_field( $field['parent'] );

		return ! is_array( $parentField ) || self::isShared( $parentField );
	}

	private static function isCopyField( array $field ): bool {
		return isset( $field['wpml_cf_preferences'] ) && WPML_COPY_CUSTOM_FIELD === (int) $field['wpml_cf_preferences'];
	}

	private static function getUserId( $postId ): int {
		return is_string( $postId ) && preg_match( '/^user_(\d+)$/', $postId, $matches ) ? (int) $matches[1] : 0;
	}

	private static function getSecondaryLanguage(): ?string {
		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$defaultLanguage = apply_filters( 'wpml_default_language', null );

		return $currentLanguage && 'all' !== $currentLanguage && $currentLanguage !== $defaultLanguage ? $currentLanguage : null;
	}
}
