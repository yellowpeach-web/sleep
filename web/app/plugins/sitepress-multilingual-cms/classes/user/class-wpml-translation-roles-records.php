<?php

use WPML\User\CapabilityIndex;

abstract class WPML_Translation_Roles_Records {

	const USERS_WITH_CAPABILITY    = 'LIKE';
	const USERS_WITHOUT_CAPABILITY = 'NOT LIKE';
	const MIN_SEARCH_LENGTH        = 3;
	const HIGH_USER_COUNT_THRESHOLD = 3000;
	const CACHE_GROUP              = __CLASS__;
	const CACHE_PREFIX             = 'wpml-cache-translators-';

	protected $wpdb;

	private $user_query_factory;

	protected $wp_roles;

	protected $administratorRoleManager;

	public function __construct(
		wpdb $wpdb,
		WPML_WP_User_Query_Factory $user_query_factory,
		WP_Roles $wp_roles,
		\WPML\TranslationRoles\Service\AdministratorRoleManager $administratorRoleManager
	) {
		$this->wpdb               = $wpdb;
		$this->user_query_factory = $user_query_factory;
		$this->wp_roles           = $wp_roles;
		$this->administratorRoleManager = $administratorRoleManager;

		$this->prepare_hooks();
	}

	public function has_users_with_capability() {
		if ( CapabilityIndex::isReady() ) {
			$wpdb = $this->wpdb;
			if (
				(bool) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT EXISTS( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s )",
						$this->get_index_meta_key()
					)
				)
			) {
				return true;
			}

			return (bool) $this->reconcile_empty_index();
		}

		return (bool) $this->get_record_ids( self::USERS_WITH_CAPABILITY, '', -1, false, false );
	}

	protected function get_index_meta_key() {
		return CapabilityIndex::metaKey( $this->get_capability(), $this->wpdb->prefix );
	}

	private function get_records_source( $compare, $indexed ) {
		if ( $indexed && self::USERS_WITH_CAPABILITY === $compare ) {
			return $this->wpdb->prepare(
				"SELECT u.id FROM {$this->wpdb->usermeta} c INNER JOIN {$this->wpdb->users} u ON u.ID = c.user_id AND c.meta_key = %s",
				$this->get_index_meta_key()
			);
		}

		$source = $this->wpdb->prepare(
			"SELECT u.id FROM {$this->wpdb->users} u INNER JOIN {$this->wpdb->usermeta} c ON c.user_id=u.ID AND c.meta_key=%s",
			CapabilityIndex::capabilitiesMetaKey( $this->wpdb->prefix )
		);

		if ( $indexed ) {
			return $source . $this->wpdb->prepare(
				" AND NOT EXISTS ( SELECT 1 FROM {$this->wpdb->usermeta} i WHERE i.user_id = u.ID AND i.meta_key = %s )",
				$this->get_index_meta_key()
			);
		}

		return $source . $this->wpdb->prepare(
			" AND c.meta_value {$compare} %s",
			'%' . $this->get_capability() . '%'
		);
	}

	public function get_users_with_capability() {
		return $this->get_records( self::USERS_WITH_CAPABILITY );
	}

	public function get_number_of_users_with_capability() {
		return count( $this->get_users_with_capability() );
	}

	public function search_for_users_without_capability( $search = '', $limit = -1, $use_exact_match = false ) {
		return $this->get_records( self::USERS_WITHOUT_CAPABILITY, $search, $limit, $use_exact_match );
	}

	public function does_user_have_capability( $user_id ) {
		return ( new WPML_WP_Cache( self::CACHE_GROUP . '_does_user_have_capability' ) )->execute_and_cache(
			serialize( [ $user_id ] ),
			function () use ( $user_id ) {
				return $this->fetch_user_capability( $user_id );
			},
			3600
		);
	}

	public function delete_all() {
		$users = $this->get_users_with_capability();
		foreach ( $users as $user ) {
			$this->delete( $user->ID );
		}
	}

	public function delete( $user_id ) {
		$user = new WP_User( $user_id );
		$user->remove_cap( $this->get_capability() );
	}

	private function get_records( $compare, $search = '', $limit = -1, $use_exact_match = false ) {
		$indexed = CapabilityIndex::isReady();
		$users   = $this->get_record_ids( $compare, $search, $limit, $use_exact_match, $indexed );

		if ( $indexed && self::USERS_WITH_CAPABILITY === $compare && ! $users ) {
			$users = $this->reconcile_empty_index( $search, $limit, $use_exact_match );
		}

		$results = array();
		foreach ( $users as $user_id ) {
			$user_data = get_userdata( $user_id );
			if ( $user_data ) {
				$language_pair_records = new WPML_Language_Pair_Records( $this->wpdb, new WPML_Language_Records( $this->wpdb ) );
				$language_pairs        = $language_pair_records->get( $user_id );

				$result    = (object) array(
					'ID'             => $user_data->ID,
					'full_name'      => trim( $user_data->first_name . ' ' . $user_data->last_name ),
					'user_login'     => $user_data->user_login,
					'user_email'     => $user_data->user_email,
					'display_name'   => $user_data->display_name,
					'user_nicename'  => $user_data->user_nicename,
					'language_pairs' => $language_pairs,
					'roles'          => $user_data->roles,
				);
				$results[] = $result;
			}
		}

		return $results;
	}

	private function reconcile_empty_index( $search = '', $limit = -1, $use_exact_match = false ) {
		if ( '' !== trim( (string) $search ) || $limit > 0 ) {
			return array();
		}

		$capability = $this->get_capability();

		if ( CapabilityIndex::isVerifiedEmpty( $capability ) ) {
			return array();
		}

		$users = $this->get_record_ids( self::USERS_WITH_CAPABILITY, '', -1, $use_exact_match, false );

		if ( $users ) {
			CapabilityIndex::requestRebuild();
		} else {
			CapabilityIndex::markVerifiedEmpty( $capability );
		}

		return $users;
	}

	private function get_record_ids( $compare, $search, $limit, $use_exact_match, $indexed ) {
		$search = trim( $search );

		$preparedUserQuery = $this->get_records_source( $compare, $indexed );

		if ( self::USERS_WITHOUT_CAPABILITY === $compare ) {
			$required_wp_roles = $this->get_required_wp_roles();
			$x = 0;
			foreach( $required_wp_roles as $required_wp_role ) {
				if ( $x === 0 ) {
					$preparedUserQuery .= " AND (";
				} else {
					$preparedUserQuery .= " OR ";
				}
				$preparedUserQuery .= $this->wpdb->prepare( "c.meta_value LIKE %s", "%{$required_wp_role}%" );
				$x++;
			}
			if ( $x > 0 ) {
				$preparedUserQuery .= " ) ";
			}
		}

		if ( $search ) {
			$preparedUserQuery .= $use_exact_match
				? $this->wpdb->prepare( " AND (u.user_login = %s OR u.user_nicename = %s OR u.user_email = %s)", "{$search}", "{$search}", "{$search}" )
				: $this->wpdb->prepare( " AND (u.user_login LIKE %s OR u.user_nicename LIKE %s OR u.user_email LIKE %s)", "%{$search}%", "%{$search}%", "%{$search}%" );
		}

		$preparedUserQuery .= ' ORDER BY user_login ASC';

		if ( $limit > 0 ) {
			$preparedUserQuery .= $this->wpdb->prepare(" LIMIT 0,%d", $limit );
		}

		$users = $this->wpdb->get_col( $preparedUserQuery );

		if ( ! $use_exact_match && $search && strlen( $search ) > self::MIN_SEARCH_LENGTH && ( $limit <= 0 || count( $users ) < $limit ) ) {
			$users_from_metas = $this->get_records_from_users_metas( $compare, $search, $limit );
			$users_with_dupes = array_merge( $users, $users_from_metas );
			$users            = wpml_array_unique( $users_with_dupes, SORT_REGULAR );
		}

		if ( ! $indexed && self::USERS_WITH_CAPABILITY === $compare ) {
			$users = $this->only_users_the_capabilities_map_grants( $users );
		}

		return $users;
	}

	private function only_users_the_capabilities_map_grants( array $user_ids ) {
		if ( ! $user_ids ) {
			return $user_ids;
		}

		$ids   = array_map( 'intval', $user_ids );
		$slots = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT user_id, meta_value FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id IN ( {$slots} )",
				array_merge( array( CapabilityIndex::capabilitiesMetaKey( $this->wpdb->prefix ) ), $ids )
			)
		);

		$granting = array();
		foreach ( (array) $rows as $row ) {
			$capabilities = maybe_unserialize( $row->meta_value );
			if ( is_array( $capabilities ) && CapabilityIndex::grants( $capabilities, $this->get_capability() ) ) {
				$granting[ (int) $row->user_id ] = true;
			}
		}

		$kept = array();
		foreach ( $ids as $id ) {
			if ( isset( $granting[ $id ] ) ) {
				$kept[] = $id;
			}
		}

		return $kept;
	}

	public static function delete_cache() {
		delete_option( self::CACHE_PREFIX . \WPML\LIB\WP\User::CAP_TRANSLATE );
		delete_option( self::CACHE_PREFIX . \WPML\LIB\WP\User::CAP_MANAGE_TRANSLATIONS );

		CapabilityIndex::requestRebuild();
	}

	private function get_records_from_users_metas( $compare, $search, $limit = -1 ) {
		$search = trim( $search );
		if ( ! $search ) {
			return array();
		}

		$sanitized_search = preg_replace( '!\s+!', ' ', $search );
		$words            = explode( ' ', $sanitized_search );

		if ( ! $words ) {
			return array();
		}

		$search_by_names = array( 'relation' => 'OR' );

		foreach ( $words as $word ) {
			$search_by_names[] = array(
				'key'     => 'first_name',
				'value'   => $word,
				'compare' => 'LIKE',
			);
			$search_by_names[] = array(
				'key'     => 'last_name',
				'value'   => $word,
				'compare' => 'LIKE',
			);
		}

		$query_args = array(
			'fields'     => 'ID',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => "{$this->wpdb->prefix}capabilities",
					'value'   => $this->get_capability(),
					'compare' => $compare,
				),
				$search_by_names,
			),
			'number'     => $limit,
		);

		if ( 'NOT LIKE' === $compare ) {
			$required_wp_roles = $this->get_required_wp_roles();
			if ( $required_wp_roles ) {
				$query_args['role__in'] = $required_wp_roles;
			}
		}

		$user_query = $this->user_query_factory->create( $query_args );

		return $user_query->get_results();

	}

	private function fetch_user_capability( $user_id ) {
		$wpdb = $this->wpdb;

		if ( CapabilityIndex::isReady() ) {
			return (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT user_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
					$user_id,
					$this->get_index_meta_key()
				)
			);
		}

		$capabilities = maybe_unserialize(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
					$user_id,
					CapabilityIndex::capabilitiesMetaKey( $wpdb->prefix )
				)
			)
		);

		return is_array( $capabilities ) && CapabilityIndex::grants( $capabilities, $this->get_capability() );
	}

	abstract protected function prepare_hooks();

	abstract protected function get_capability();

	abstract protected function get_required_wp_roles();
}
