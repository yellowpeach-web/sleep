<?php

namespace ACFML\FieldGroup\Endpoints;

use ACFML\FieldGroup\AttachedPosts;
use ACFML\FieldGroup\AttachedTerms;
use ACFML\FieldGroup\HandTranslatedPosts;
use ACFML\FieldGroup\Mode;
use ACFML\FieldGroup\PreferenceReapplyService;
use ACFML\FieldPreferences\SubfieldRules;
use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class CountAffectedPosts implements IHandler {

	const SCOPE_POST = 'post';
	const SCOPE_TERM = 'term';
	const SCOPE_BOTH = 'both';

	public function authorize( Collection $data ) {
		return self::canRead( (int) $data->get( 'fieldGroupId' ) );
	}

	public function run( Collection $data ) {
		$fieldGroupId = (int) $data->get( 'fieldGroupId' );

		if ( ! self::canRead( $fieldGroupId ) ) {
			return Either::left( 'invalid-field-group' );
		}

		$changes = self::readChanges( $data->get( 'changes' ) );

		return Either::right( self::count( $fieldGroupId, $changes, self::readMode( $data->get( 'mode' ) ) ) );
	}

	private static function count( $fieldGroupId, array $changes, ?string $pendingMode = null ) {
		$service = $changes ? PreferenceReapplyService::get() : null;
		$scope   = self::getScope( $fieldGroupId );

		if ( ! $service || ! method_exists( $service, 'countAffectedForChanges' ) ) {
			$fallback = AttachedPosts::getCount( $fieldGroupId );

			return [
				'count'               => $fallback,
				'postCount'           => $fallback,
				'termCount'           => 0,
				'handTranslatedCount' => 0,
				'isTranslatedCount'   => false,
				'scope'               => $scope,
			];
		}

		$postCount           = 0;
		$handTranslatedCount = 0;

		if ( self::SCOPE_TERM !== $scope ) {
			$handTranslatedCount = HandTranslatedPosts::countAffected(
				self::fieldNames( $changes ),
				$pendingMode ? [ $fieldGroupId => $pendingMode ] : []
			);
			$postCount           = max( 0, (int) $service->countAffectedForChanges( $changes ) - $handTranslatedCount );
		}

		$termCount = self::SCOPE_POST === $scope ? 0 : self::countAffectedTerms( $service, $changes );

		return [
			'count'               => $postCount + $termCount,
			'postCount'           => $postCount,
			'termCount'           => $termCount,
			'handTranslatedCount' => $handTranslatedCount,
			'isTranslatedCount'   => true,
			'scope'               => $scope,
		];
	}

	private static function getScope( $fieldGroupId ) {
		if ( ! function_exists( 'acf_get_field_group' ) ) {
			return self::SCOPE_POST;
		}

		$fieldGroup = acf_get_field_group( $fieldGroupId );

		if ( ! is_array( $fieldGroup ) ) {
			return self::SCOPE_POST;
		}

		$types = SubfieldRules::elementTypesForGroup( $fieldGroup );

		if ( [ 'term' ] === $types ) {
			return self::SCOPE_TERM;
		}

		return in_array( 'term', $types, true ) ? self::SCOPE_BOTH : self::SCOPE_POST;
	}

	private static function countAffectedTerms( $service, array $changes ) {
		if ( method_exists( $service, 'countAffectedTermsForChanges' ) ) {
			return (int) $service->countAffectedTermsForChanges( $changes );
		}

		$copyChanges = self::copyChanges( $changes );

		return $copyChanges ? AttachedTerms::countAffected( self::fieldNames( $copyChanges ) ) : 0;
	}

	private static function copyChanges( array $changes ) {
		return array_filter(
			$changes,
			function ( $change ) {
				return in_array( (int) $change['newPref'], [ WPML_COPY_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ], true );
			}
		);
	}

	private static function fieldNames( array $changes ) {
		$names = [];

		foreach ( $changes as $change ) {
			$names[] = (string) $change['field'];
		}

		return $names;
	}

	private static function readMode( $mode ): ?string {
		return in_array( $mode, Mode::ALL, true ) ? $mode : null;
	}

	private static function readChanges( $changes ) {
		if ( ! is_array( $changes ) ) {
			return [];
		}

		$read = [];

		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) || empty( $change['name'] ) || ! isset( $change['to'] ) ) {
				continue;
			}

			$read[] = [
				'field'   => (string) $change['name'],
				'oldPref' => isset( $change['from'] ) && '' !== $change['from'] ? (int) $change['from'] : null,
				'newPref' => (int) $change['to'],
			];
		}

		return $read;
	}

	public static function canRead( $fieldGroupId ) {
		return $fieldGroupId > 0
			&& 'acf-field-group' === get_post_type( $fieldGroupId )
			&& current_user_can( 'edit_post', $fieldGroupId );
	}
}
