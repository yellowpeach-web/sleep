<?php

namespace ACFML\Strings;

use WPML\FP\Obj;

class FieldGroupPackageTitles {

	private $fieldGroupsByKey;

	public function titleOf( array $fieldGroup ) {
		if ( null === $this->fieldGroupsByKey ) {
			$this->fieldGroupsByKey = self::readFieldGroupsByKey();
		}

		$this->fieldGroupsByKey[ $fieldGroup['key'] ] = $fieldGroup;

		return self::titlesByKey( $this->fieldGroupsByKey )[ $fieldGroup['key'] ];
	}

	public static function titlesByKey( array $fieldGroups ) {
		$groupTitleCounts = array_count_values( array_map( [ self::class, 'getGroupTitle' ], $fieldGroups ) );

		$titlesByKey = [];
		foreach ( $fieldGroups as $fieldGroup ) {
			$groupTitle = self::getGroupTitle( $fieldGroup );

			$titlesByKey[ $fieldGroup['key'] ] = $groupTitleCounts[ $groupTitle ] > 1
				? sprintf( '%s (%s)', $groupTitle, $fieldGroup['key'] )
				: $groupTitle;
		}

		return $titlesByKey;
	}

	public function syncAll() {
		$this->fieldGroupsByKey = self::readFieldGroupsByKey();
		$titlesByKey            = self::titlesByKey( $this->fieldGroupsByKey );

		$lastRenamedPackage = null;
		foreach ( $this->readStoredPackages() as $storedPackage ) {
			$title = Obj::prop( $storedPackage->name, $titlesByKey );
			if ( $title && $title !== $storedPackage->title ) {
				$lastRenamedPackage = self::renamePackage( (int) $storedPackage->ID, $title );
			}
		}

		if ( $lastRenamedPackage ) {
			$lastRenamedPackage->flush_cache();
		}
	}

	public function syncAllOnShutdown() {
		add_action( 'shutdown', [ $this, 'syncAll' ] );
	}

	private static function getGroupTitle( array $fieldGroup ) {
		return trim( (string) Obj::prop( 'title', $fieldGroup ) ) ?: $fieldGroup['key'];
	}

	private static function readFieldGroupsByKey() {
		$fieldGroupsByKey = [];
		foreach ( array_merge( acf_get_raw_field_groups(), acf_get_local_field_groups() ) as $fieldGroup ) {
			$fieldGroupsByKey[ $fieldGroup['key'] ] = $fieldGroup;
		}

		return $fieldGroupsByKey;
	}

	private function readStoredPackages() {
		$packages = apply_filters( 'wpml_pt_all_packages', [] );

		return array_filter(
			is_array( $packages ) ? $packages : [],
			function ( $package ) {
				return is_object( $package ) && Package::FIELD_GROUP_PACKAGE_KIND_SLUG === ( $package->kind_slug ?? null );
			}
		);
	}

	private static function renamePackage( int $packageId, string $title ): \WPML_Package {
		$package        = Factory::createWpmlPackage( $packageId );
		$package->title = $title;
		$package->update_package_record();

		return $package;
	}
}
