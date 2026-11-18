<?php

namespace WPML\PB\Elementor\Hooks;

class SiteSettings implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const KIND = 'Elementor Site Settings';

	const KIND_SLUG = 'elementor-site-settings';

	const TITLE = 'Site Settings';

	const STRING_SITE_NAME = 'site_name';

	const STRING_SITE_DESCRIPTION = 'site_description';

	const MARKER_META = '_wpml_elementor_kit';

	const ACTIVE_KIT_OPTION = 'elementor_active_kit';

	const KIT_OPTIONS = [ self::ACTIVE_KIT_OPTION, 'elementor_previous_kit' ];

	const TEMPLATES_POST_TYPE = 'elementor_library';

	const TEMPLATE_TYPE_META = '_elementor_template_type';

	const PAGE_SETTINGS_META = '_elementor_page_settings';

	const CARRIED_OPTIONS = [
		self::STRING_SITE_NAME        => 'blogname',
		self::STRING_SITE_DESCRIPTION => 'blogdescription',
	];

	const CLAIMS_DECLARED_OPTION = 'wpml_elementor_site_settings_claims_declared';

	private const CLAIMS_RETRY_OPTION = 'wpml_elementor_site_settings_claims_retry_at';

	private const CLAIMS_RETRY_INTERVAL = 86400;

	const STRING_TITLES = [
		self::STRING_SITE_NAME        => 'Site name',
		self::STRING_SITE_DESCRIPTION => 'Site description',
	];

	private $packages = [];

	private $registered = [];

	private $kitsMarked = false;

	public function add_hooks() {
		add_action( 'elementor/document/after_save', [ $this, 'onKitSaved' ], 10, 2 );

		add_action( 'update_option_blogname', [ $this, 'onSiteNameOptionUpdated' ], 20, 2 );
		add_action( 'update_option_blogdescription', [ $this, 'onSiteDescriptionOptionUpdated' ], 20, 2 );
		add_action( 'update_option_' . self::ACTIVE_KIT_OPTION, [ $this, 'onActiveKitChanged' ], 10, 2 );
		add_filter( 'wpml_active_string_package_kinds', [ $this, 'addPackageKind' ] );

		( new SiteSettingsOwnerTranslation( $this ) )->add_hooks();

		add_filter( 'wpml_tm_dashboard_exclude_posts_with_meta_keys', [ $this, 'excludeFromDashboard' ], 10, 2 );
		add_filter( 'wpml_exclude_post_from_auto_translate', [ $this, 'excludeFromAutomaticTranslation' ], 10, 2 );

		if ( is_admin() ) {
			add_action( 'init', [ $this, 'markExistingKits' ] );
			add_action( 'init', [ $this, 'declareExistingClaims' ] );
		}
	}

	public function onKitSaved( $document, $data ) {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! $this->isPublishSave( $data ) ) {
			return;
		}

		$kitId = (int) $document->get_main_id();

		if ( ! $kitId || ! $this->isKit( $kitId ) || $kitId !== $this->getActiveKitId() ) {
			return;
		}

		$stored = get_post_meta( $kitId, self::PAGE_SETTINGS_META, true );
		$values = [];

		foreach ( array_keys( self::STRING_TITLES ) as $name ) {
			$values[ $name ] = $this->getSavedValue( $data, $stored, $name );
		}

		$this->registerPackage( $kitId, $values );
	}

	public function onSiteNameOptionUpdated( $oldValue, $value ) {
		$this->onOptionUpdated( self::STRING_SITE_NAME, $oldValue, $value );
	}

	public function onSiteDescriptionOptionUpdated( $oldValue, $value ) {
		$this->onOptionUpdated( self::STRING_SITE_DESCRIPTION, $oldValue, $value );
	}

	private function onOptionUpdated( $name, $oldValue, $value ) {
		if ( $oldValue === $value ) {
			return;
		}

		if ( ! is_string( $value ) ) {
			return;
		}

		$kitId = $this->getActiveKitId();

		if ( ! $kitId || ! $this->isKit( $kitId ) ) {
			return;
		}

		$stored = get_post_meta( $kitId, self::PAGE_SETTINGS_META, true );
		$values = [];

		foreach ( array_keys( self::STRING_TITLES ) as $stringName ) {
			$values[ $stringName ] = $name === $stringName ? $value : $this->getSavedValue( null, $stored, $stringName );
		}

		if ( isset( $this->registered[ $kitId ] ) && $this->registered[ $kitId ] === $values ) {
			return;
		}

		$this->registerPackage( $kitId, $values );
	}

	public function onActiveKitChanged( $oldKitId, $kitId ) {
		$oldKitId = (int) $oldKitId;
		$kitId    = (int) $kitId;

		if ( $oldKitId === $kitId ) {
			return;
		}

		$this->clearClaimsRetry();

		if ( $oldKitId ) {
			$this->releaseClaims( $oldKitId );
		}

		if ( ! $kitId || ! $this->isKit( $kitId ) ) {
			return;
		}

		$values = $this->getStoredValues( $kitId );

		if ( array_filter( $values, 'strlen' ) ) {
			$this->registerPackage( $kitId, $values );
		}
	}

	public function registerStoredValues( $kitId ) {
		$kitId = (int) $kitId;

		$this->registerPackage( $kitId, $this->getStoredValues( $kitId ) );
	}

	private function registerPackage( $kitId, array $values ) {
		$package = $this->getPackage( $kitId );

		do_action( 'wpml_start_string_package_registration', $package );

		foreach ( self::STRING_TITLES as $name => $title ) {
			$value = isset( $values[ $name ] ) ? $values[ $name ] : '';

			if ( '' !== $value ) {
				do_action( 'wpml_register_string', $value, $name, $package, $title, 'LINE' );
			}
		}

		do_action( 'wpml_delete_unused_package_strings', $package );

		$this->releasePreviousKitClaims( $kitId );
		$this->declareClaims( $package, $values );
		$this->clearClaimsRetry();

		update_post_meta( $kitId, self::MARKER_META, 1 );

		$this->registered[ $kitId ] = $values;
	}

	private function clearClaimsRetry() {
		delete_option( self::CLAIMS_RETRY_OPTION );
	}

	private function releasePreviousKitClaims( $kitId ) {
		$previousKitId = (int) get_option( self::KIT_OPTIONS[1] );

		if ( $previousKitId && $previousKitId !== (int) $kitId ) {
			$this->releaseClaims( $previousKitId );
		}
	}

	private function releaseClaims( $kitId ) {
		do_action( 'wpml_st_package_string_carries_release', $this->getPackage( $kitId ) );
	}

	private function declareClaims( array $package, array $values ) {
		$declared = [];

		foreach ( self::CARRIED_OPTIONS as $name => $option ) {
			if ( isset( $values[ $name ] ) && '' !== $values[ $name ] ) {
				do_action( 'wpml_st_package_string_carries', $package, $name, [ 'option' => $option ] );
				$declared[] = $name;
			}
		}

		return $declared;
	}

	public function declareExistingClaims() {
		if ( get_option( self::CLAIMS_DECLARED_OPTION ) ) {
			return;
		}

		if ( (int) get_option( self::CLAIMS_RETRY_OPTION ) > time() ) {
			return;
		}

		$kitId    = $this->getActiveKitId();
		$declared = [];
		$package  = null;

		if ( $kitId && $this->isKit( $kitId ) ) {
			$values = $this->getStoredValues( $kitId );

			if ( array_filter( $values, 'strlen' ) ) {
				$package = $this->getPackage( $kitId );

				$this->registerStoredValues( $kitId );
				$declared = array_keys( array_filter( array_intersect_key( $values, self::CARRIED_OPTIONS ), 'strlen' ) );
			}
		}

		foreach ( $declared as $name ) {
			if ( ! apply_filters( 'wpml_st_package_string_carries_accepted', false, $package, $name ) ) {
				update_option( self::CLAIMS_RETRY_OPTION, time() + self::CLAIMS_RETRY_INTERVAL, false );

				return;
			}
		}

		update_option( self::CLAIMS_DECLARED_OPTION, 1, true );
	}

	private function getStoredValues( $kitId ) {
		$stored = get_post_meta( $kitId, self::PAGE_SETTINGS_META, true );
		$values = [];

		foreach ( array_keys( self::STRING_TITLES ) as $name ) {
			$values[ $name ] = $this->getSavedValue( null, $stored, $name );
		}

		return $values;
	}

	public function addPackageKind( $kinds ) {
		$kind = [
			self::KIND_SLUG => [
				'title'  => self::TITLE,
				'plural' => self::TITLE,
				'slug'   => self::KIND_SLUG,
			],
		];

		return is_array( $kinds ) ? array_merge( $kinds, $kind ) : $kind;
	}

	public function excludeFromDashboard( $metaKeys, $postType ) {
		if ( is_array( $metaKeys ) && self::TEMPLATES_POST_TYPE === $postType ) {
			$metaKeys[] = self::MARKER_META;
		}

		return $metaKeys;
	}

	public function excludeFromAutomaticTranslation( $excluded, $postId ) {
		if ( $excluded || ! is_numeric( $postId ) ) {
			return $excluded;
		}

		return $this->isKit( (int) $postId ) ? true : $excluded;
	}

	public function markExistingKits() {
		if ( $this->kitsMarked ) {
			return;
		}

		$this->kitsMarked = true;

		foreach ( self::KIT_OPTIONS as $option ) {
			$kitId = (int) get_option( $option );

			if ( $kitId && $this->isKit( $kitId ) && ! get_post_meta( $kitId, self::MARKER_META, true ) ) {
				update_post_meta( $kitId, self::MARKER_META, 1 );
			}
		}
	}

	public function getPackage( $kitId ) {
		$kitId = (int) $kitId;

		if ( isset( $this->packages[ $kitId ] ) ) {
			return $this->packages[ $kitId ];
		}

		$package = [
			'kind'      => self::KIND,
			'kind_slug' => self::KIND_SLUG,
			'name'      => (string) $kitId,
			'title'     => self::TITLE,
			'edit_link' => admin_url( 'post.php?post=' . $kitId . '&action=elementor' ),
		];

		$this->packages[ $kitId ] = $package;

		return $package;
	}

	private function isPublishSave( $data ) {
		return is_array( $data )
			&& isset( $data['settings'] )
			&& is_array( $data['settings'] )
			&& isset( $data['settings']['post_status'] )
			&& 'publish' === $data['settings']['post_status'];
	}

	private function getSavedValue( $data, $stored, $name ) {
		if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) && isset( $data['settings'][ $name ] ) ) {
			$value = $data['settings'][ $name ];
		} elseif ( is_array( $stored ) && isset( $stored[ $name ] ) ) {
			$value = $stored[ $name ];
		} else {
			return '';
		}

		return is_string( $value ) ? $value : '';
	}

	private function isKit( $postId ) {
		return 'kit' === get_post_meta( $postId, self::TEMPLATE_TYPE_META, true );
	}

	public function getActiveKit() {
		$kitId = $this->getActiveKitId();

		return $kitId && $this->isKit( $kitId ) ? $kitId : 0;
	}

	private function getActiveKitId() {
		return (int) get_option( self::ACTIVE_KIT_OPTION );
	}
}
