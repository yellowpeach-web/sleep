<?php

namespace WPML\PB\Elementor\Hooks;

class SiteSettingsOwnerTranslation {

	const STRING_IDS_OPTION = 'wpml_st_blog_name_and_description_string_ids';

	const STRING_DOMAIN = 'WP';

	const OWNER_STRINGS = [
		'Blog Title' => SiteSettings::STRING_SITE_NAME,
		'Tagline'    => SiteSettings::STRING_SITE_DESCRIPTION,
	];

	private $siteSettings;

	private $lookedUpIds = [];

	private static $isWriting = false;

	public function __construct( SiteSettings $siteSettings ) {
		$this->siteSettings = $siteSettings;
	}

	public function add_hooks() {
		add_action( 'wpml_st_add_string_translation', [ $this, 'mirrorOwnerTranslation' ], 10, 4 );
	}

	public function mirrorOwnerTranslation( $stringTranslationId, $translationData, $language, $stringId ) {
		if ( self::$isWriting || ! is_numeric( $stringId ) || ! is_string( $language ) || '' === $language ) {
			return;
		}

		$ownerStringName = $this->getOwnerStringName( (int) $stringId );

		if ( null === $ownerStringName ) {
			return;
		}

		$value = $this->getCompleteValue( $translationData );

		if ( null === $value ) {
			return;
		}

		$kitId = $this->siteSettings->getActiveKit();

		if ( ! $kitId ) {
			return;
		}

		$packageStringId = $this->getPackageStringId( $kitId, self::OWNER_STRINGS[ $ownerStringName ] );

		if ( ! $packageStringId ) {
			return;
		}

		self::$isWriting = true;

		try {
			do_action( 'wpml_add_string_translation', $packageStringId, $language, $value, ICL_TM_COMPLETE );
			$this->completeCounterpart( $kitId, $ownerStringName, $language );
		} finally {
			self::$isWriting = false;
		}
	}

	private function completeCounterpart( $kitId, $ownerStringName, $language ) {
		$counterpartName = 'Blog Title' === $ownerStringName ? 'Tagline' : 'Blog Title';
		$stringId        = $this->getOwnerStringId( $counterpartName );
		$value           = $stringId ? $this->getCompleteTranslation( $stringId, $language ) : null;

		if ( null === $value ) {
			return;
		}

		$packageStringId = $this->getPackageStringId( $kitId, self::OWNER_STRINGS[ $counterpartName ], false );

		if ( ! $packageStringId || null !== $this->getCompleteTranslation( $packageStringId, $language ) ) {
			return;
		}

		do_action( 'wpml_add_string_translation', $packageStringId, $language, $value, ICL_TM_COMPLETE );
	}

	private function getCompleteTranslation( $stringId, $language ) {
		$translations = icl_get_string_translations_by_id( $stringId );

		return is_array( $translations ) && isset( $translations[ $language ] )
			? $this->getCompleteValue( $translations[ $language ] )
			: null;
	}

	private function getOwnerStringName( $stringId ) {
		foreach ( array_keys( self::OWNER_STRINGS ) as $name ) {
			$id = $this->getOwnerStringId( $name );

			if ( $id && $id === $stringId ) {
				return $name;
			}
		}

		return null;
	}

	private function getOwnerStringId( $name ) {
		$ids = get_option( self::STRING_IDS_OPTION, false );

		return is_array( $ids ) && ! empty( $ids[ $name ] ) ? (int) $ids[ $name ] : $this->lookUpStringId( $name );
	}

	private function lookUpStringId( $name ) {
		if ( ! array_key_exists( $name, $this->lookedUpIds ) ) {
			$this->lookedUpIds[ $name ] = (int) apply_filters(
				'wpml_string_id',
				null,
				[
					'context' => self::STRING_DOMAIN,
					'name'    => $name,
				]
			);
		}

		return $this->lookedUpIds[ $name ];
	}

	private function getCompleteValue( $translationData ) {
		if (
			! is_array( $translationData )
			|| ! isset( $translationData['status'], $translationData['value'] )
			|| ICL_TM_COMPLETE !== (int) $translationData['status']
			|| ! is_string( $translationData['value'] )
			|| '' === $translationData['value']
		) {
			return null;
		}

		return $translationData['value'];
	}

	private function getPackageStringId( $kitId, $name, $registerMissing = true ) {
		$package = $this->siteSettings->getPackage( $kitId );
		$query   = [
			'context' => $package['kind_slug'] . '-' . $package['name'],
			'name'    => $name,
		];

		$id = (int) apply_filters( 'wpml_string_id', null, $query );

		if ( ! $id && $registerMissing ) {
			$this->siteSettings->registerStoredValues( $kitId );

			$id = (int) apply_filters( 'wpml_string_id', null, $query );
		}

		return $id;
	}
}
