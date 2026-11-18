<?php

class WPML_ST_Package_String_Carriers {

	const OPTION = 'wpml_st_string_carriers';

	const REFUSED_OPTION = 'wpml_st_string_carriers_refused';

	const HANDED_OVER_OPTION = 'wpml_st_string_carriers_handed_over';

	const ADMIN_TEXTS_PREFIX = 'admin_texts_';

	const SITE_IDENTITY_DOMAIN = 'WP';

	const OPTION_TARGETS = [
		'blogname'        => 'Blog Title',
		'blogdescription' => 'Tagline',
	];

	private static $instance;

	private $wpdb;

	private $md5ById = [];

	private $carrierExists = [];

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function get() {
		global $wpdb;

		if ( ! self::$instance ) {
			self::$instance = new self( $wpdb );
		}

		return self::$instance;
	}

	public static function setInstance( $instance ) {
		self::$instance = $instance;
	}

	public function all() {
		if ( ! function_exists( 'get_option' ) ) {
			return [];
		}

		$claims = get_option( self::OPTION, [] );

		if ( ! is_array( $claims ) || ! $claims ) {
			return [];
		}

		return array_filter(
			$claims,
			function ( $claim, $md5 ) {
				return is_string( $md5 )
					&& preg_match( '/^[0-9a-f]{32}$/', $md5 )
					&& is_array( $claim )
					&& isset( $claim['carrier'], $claim['package'] );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	public function live() {
		$claims = $this->all();
		$live   = [];

		foreach ( $claims as $md5 => $claim ) {
			if ( $this->isLive( $claim ) ) {
				$live[ $md5 ] = $claim;
			}
		}

		if ( count( $live ) !== count( $claims ) ) {
			$known = $this->carrierExists;
			$this->writeClaims( $live );
			$this->carrierExists = $known;
		}

		return $live;
	}

	public function hasAny() {
		return (bool) $this->all();
	}

	public function md5s() {
		return array_values( array_map( 'strval', array_keys( $this->live() ) ) );
	}

	public function notCarriedSql( $column ) {
		$condition = $this->notCarriedCondition( $column );

		return '' === $condition ? '' : ' AND ' . $condition . ' ';
	}

	public function notCarriedCondition( $column ) {
		$md5s = $this->md5s();

		if ( ! $md5s ) {
			return '';
		}

		return $column . " NOT IN ( '" . implode( "', '", $md5s ) . "' )";
	}

	public function carrierStringIds() {
		return array_values( array_unique( array_map( 'intval', array_column( $this->live(), 'carrier' ) ) ) );
	}

	public function carriedStringIds() {
		$wpdb = $this->wpdb;

		$md5s = $this->md5s();

		if ( ! $md5s ) {
			return [];
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 IN ( " . implode( ', ', array_fill( 0, count( $md5s ), '%s' ) ) . ' )',
				$md5s
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	public static function md5( $domain, $name, $context = '' ) {
		return md5( $domain . $name . $context );
	}

	public function forTarget( $domain, $name, $context = '' ) {
		$claims = $this->live();
		$md5    = self::md5( $domain, $name, $context );

		return isset( $claims[ $md5 ] ) ? $claims[ $md5 ] : null;
	}

	public function forStringId( $stringId ) {
		$wpdb = $this->wpdb;

		$claims = $this->live();

		if ( ! $claims ) {
			return null;
		}

		$stringId = (int) $stringId;

		if ( ! array_key_exists( $stringId, $this->md5ById ) ) {
			$md5 = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT domain_name_context_md5 FROM {$wpdb->prefix}icl_strings WHERE id = %d",
					$stringId
				)
			);

			$this->md5ById[ $stringId ] = is_string( $md5 ) ? $md5 : null;
		}

		$md5 = $this->md5ById[ $stringId ];

		return null !== $md5 && isset( $claims[ $md5 ] ) ? $claims[ $md5 ] : null;
	}

	private function isLive( array $claim ) {
		$wpdb = $this->wpdb;

		$carrierId = (int) $claim['carrier'];

		if ( ! array_key_exists( $carrierId, $this->carrierExists ) ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}icl_strings WHERE id = %d",
					$carrierId
				)
			);

			if ( null === $found && isset( $wpdb->last_error ) && '' !== $wpdb->last_error ) {
				return true;
			}

			$this->carrierExists[ $carrierId ] = (bool) $found;
		}

		return $this->carrierExists[ $carrierId ];
	}

	public function isCarrier( $carrierStringId ) {
		return (int) $carrierStringId > 0 && in_array( (int) $carrierStringId, $this->carrierStringIds(), true );
	}

	public function translation( $domain, $name, $language, $context = '' ) {
		$wpdb = $this->wpdb;

		$claim = $this->forTarget( $domain, $name, $context );

		if ( ! $claim ) {
			return null;
		}

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$wpdb->prefix}icl_string_translations
				WHERE string_id = %d AND language = %s AND status IN ( %d, %d ) AND value IS NOT NULL AND value <> ''",
				(int) $claim['carrier'],
				$language,
				ICL_TM_COMPLETE,
				ICL_TM_NEEDS_UPDATE
			)
		);

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	public function package( array $claim ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT kind, kind_slug, title FROM {$wpdb->prefix}icl_string_packages WHERE ID = %d",
				(int) $claim['package']
			)
		);

		if ( ! is_object( $row ) ) {
			return null;
		}

		$title = (string) ( $row->kind ?: $row->title );
		$page  = ( defined( 'WPML_TM_FOLDER' ) ? WPML_TM_FOLDER : 'tm' ) . '/menu/main.php';

		return [
			'title' => $title,
			'link'  => admin_url( 'admin.php?page=' . $page . '&sections=' . rawurlencode( 'stringPackage/' . $row->kind_slug ) ),
		];
	}

	public static function resolveTarget( $target ) {
		if ( ! is_array( $target ) ) {
			return null;
		}

		if ( isset( $target['option'] ) && is_string( $target['option'] ) && '' !== $target['option'] ) {
			$option = $target['option'];

			if ( isset( self::OPTION_TARGETS[ $option ] ) ) {
				return [ self::SITE_IDENTITY_DOMAIN, self::OPTION_TARGETS[ $option ], '' ];
			}

			$key = isset( $target['key'] ) && is_string( $target['key'] ) && '' !== $target['key'] ? $target['key'] : $option;

			return [ self::ADMIN_TEXTS_PREFIX . $option, $key, '' ];
		}

		if ( isset( $target['domain'], $target['name'] ) && is_string( $target['domain'] ) && is_string( $target['name'] ) ) {
			$domain = $target['domain'];
			$name   = $target['name'];

			$isSiteIdentity = self::SITE_IDENTITY_DOMAIN === $domain && in_array( $name, self::OPTION_TARGETS, true );
			$isAdminText    = 0 === strpos( $domain, self::ADMIN_TEXTS_PREFIX ) && strlen( $domain ) > strlen( self::ADMIN_TEXTS_PREFIX );

			if ( ( $isSiteIdentity || $isAdminText ) && '' !== $name ) {
				return [ $domain, $name, '' ];
			}
		}

		return null;
	}

	public function claim( $carrierStringId, $packageId, $target ) {
		$carrierStringId = (int) $carrierStringId;
		$packageId       = (int) $packageId;
		$identity        = self::resolveTarget( $target );

		if ( ! $identity ) {
			$this->refused( 'not an option-backed string', $target, $packageId );

			return false;
		}

		if ( ! $carrierStringId || ! $packageId ) {
			$this->refused( 'the package string is not registered', $target, $packageId );

			return false;
		}

		list( $domain, $name, $context ) = $identity;

		$md5    = self::md5( $domain, $name, $context );
		$claims = $this->live();

		if ( isset( $claims[ $md5 ] ) ) {
			$claim = $claims[ $md5 ];

			if ( (int) $claim['package'] !== $packageId ) {
				$this->refusedByAnotherPackage( $md5, $packageId );
				$this->refused( 'another package carries this text', $target, $packageId );

				return false;
			}

			if ( (int) $claim['carrier'] === $carrierStringId ) {
				return true;
			}
		}

		$claims[ $md5 ] = [
			'carrier' => $carrierStringId,
			'package' => $packageId,
			'domain'  => $domain,
			'name'    => $name,
			'context' => $context,
		];

		$this->writeClaims( $claims );

		$this->carryOver( $md5, $carrierStringId, $this->takeHandedOverLanguages( $md5 ) );

		return true;
	}

	public function release( $carrierStringIds ) {
		$claims = $this->all();

		if ( ! $claims || ! is_array( $carrierStringIds ) ) {
			return;
		}

		$ids      = array_map( 'intval', $carrierStringIds );
		$released = [];

		foreach ( $claims as $md5 => $claim ) {
			if ( in_array( (int) $claim['carrier'], $ids, true ) ) {
				$released[ $md5 ] = $claim;
				unset( $claims[ $md5 ] );
			}
		}

		if ( ! $released ) {
			return;
		}

		$this->writeClaims( $claims );

		$handedOver = [];

		foreach ( $released as $md5 => $claim ) {
			$plainId = $this->stringIdByMd5( $md5 );

			if ( $plainId ) {
				$handedOver[ $md5 ] = $this->copyTranslations( (int) $claim['carrier'], $plainId, true );
			}
		}

		$this->recordHandedOver( array_filter( $handedOver ) );
	}

	private function recordHandedOver( array $languages ) {
		if ( ! $languages ) {
			return;
		}

		$handedOver = get_option( self::HANDED_OVER_OPTION, [] );
		$handedOver = is_array( $handedOver ) ? $handedOver : [];

		update_option( self::HANDED_OVER_OPTION, array_merge( $handedOver, $languages ), false );
	}

	private function takeHandedOverLanguages( $md5 ) {
		$handedOver = get_option( self::HANDED_OVER_OPTION, [] );

		if ( ! is_array( $handedOver ) || ! isset( $handedOver[ $md5 ] ) ) {
			return [];
		}

		$languages = array_values( array_filter( (array) $handedOver[ $md5 ], 'is_string' ) );

		unset( $handedOver[ $md5 ] );
		update_option( self::HANDED_OVER_OPTION, $handedOver, false );

		return $languages;
	}

	public function releasePackage( $packageId ) {
		$ids = [];

		foreach ( $this->all() as $claim ) {
			if ( (int) $claim['package'] === (int) $packageId ) {
				$ids[] = (int) $claim['carrier'];
			}
		}

		if ( $ids ) {
			$this->release( $ids );
		}
	}

	private function writeClaims( array $claims ) {
		update_option( self::OPTION, $claims, true );

		$this->carrierExists = [];

		do_action( 'wpml_st_string_carriers_changed' );
	}

	private function carryOver( $md5, $carrierStringId, array $handedOverLanguages ) {
		$plainId = $this->stringIdByMd5( $md5 );

		if ( $plainId ) {
			$this->copyTranslations( $plainId, $carrierStringId, $handedOverLanguages );
		}
	}

	private function copyTranslations( $fromId, $toId, $overwrite ) {
		$from = $this->translationsWithValue( $fromId );

		if ( ! $from ) {
			return [];
		}

		$to = $this->translationsWithValue( $toId );

		foreach ( $from as $language => $row ) {
			$overwrites = true === $overwrite || ( is_array( $overwrite ) && in_array( $language, $overwrite, true ) );

			if ( isset( $to[ $language ] ) && ( ! $overwrites || $to[ $language ] === $row ) ) {
				continue;
			}

			$this->stringObject( $toId )->set_translation( $language, $row['value'], (int) $row['status'] );
		}

		return array_map( 'strval', array_keys( $from ) );
	}

	private function translationsWithValue( $stringId ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language, status, value FROM {$wpdb->prefix}icl_string_translations
				WHERE string_id = %d AND value IS NOT NULL AND value <> ''",
				(int) $stringId
			),
			ARRAY_A
		);

		$out = [];

		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['language'] ] = [
				'value'  => (string) $row['value'],
				'status' => (int) $row['status'],
			];
		}

		return $out;
	}

	private function stringIdByMd5( $md5 ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 = %s",
				$md5
			)
		);
	}

	protected function stringObject( $stringId ) {
		return new WPML_ST_String( (int) $stringId, $this->wpdb );
	}

	private function refusedByAnotherPackage( $md5, $packageId ) {
		$refused = get_option( self::REFUSED_OPTION, [] );
		$refused = is_array( $refused ) ? $refused : [];

		if ( isset( $refused[ $md5 ] ) && in_array( $packageId, (array) $refused[ $md5 ], true ) ) {
			return;
		}

		$refused[ $md5 ][] = $packageId;

		update_option( self::REFUSED_OPTION, $refused, false );
	}

	private function refused( $reason, $target, $packageId ) {
		do_action( 'wpml_st_package_string_carries_refused', $reason, $target, $packageId );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPML String Translation: claim refused (' . $reason . '), package ' . $packageId . ', target ' . wp_json_encode( $target ) );
		}
	}
}
