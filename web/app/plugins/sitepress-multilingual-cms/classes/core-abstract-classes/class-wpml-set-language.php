<?php

class WPML_Set_Language extends WPML_Full_Translation_API {

	const TRID_ALLOCATION_LOCK_TIMEOUT_SECONDS = 15;

	const TRID_ALLOCATION_LOCK_TTL_SECONDS = 30;

	private static $exempt_core_flow_depth = 0;

	private $trid_allocation_lock;

	public static function run_exempt_core_flow( callable $flow ) {
		$was = self::$exempt_core_flow_depth;

		++self::$exempt_core_flow_depth;

		try {
			return call_user_func( $flow );
		} finally {
			self::$exempt_core_flow_depth = $was;
		}
	}

	private static function is_exempt_core_flow_running() {
		return self::$exempt_core_flow_depth > 0;
	}

	public function set(
		$el_id,
		$el_type,
		$trid,
		$language_code,
		$src_language_code = null,
		$check_duplicates = true,
		$check_null = false
	) {
		if ( strlen( $el_type ) > 60 ) {
			return false;
		}

		if ( ! $this->may_create_in( $el_id, $el_type, $trid, $language_code, $src_language_code ) ) {
			return false;
		}

		$this->clear_cache();
		if ( $check_duplicates && $el_id && (bool) ( $el_type_db = $this->check_duplicate(
			$el_type,
			$el_id
		) ) === true
		) {
			return false;
		}

		$context = explode( '_', $el_type );
		$context = $context[0];

		$src_language_code = $src_language_code === $language_code ? null : $src_language_code;

		if ( $check_null && is_null( $trid ) ) {
			$existing = $this->get_existing( $el_type, $el_id );

			if ( $existing ) {
				$trid          = $existing->trid;
				$language_code = $existing->language_code;
			}
		}

		if ( $trid ) {
			$existing_translation_id = $el_id ? $this->existing_element( $el_id, $el_type ) : null;
			$this->maybe_delete_orphan( $trid, $language_code, $el_id, (bool) $existing_translation_id );

			$populated_translation_id = null;
			if ( ! $el_id ) {
				$row_translation_id = $this->trid_lang_trans_id( $trid, $language_code );
				if ( $row_translation_id && ! $this->is_placeholder_update( $trid, $language_code ) ) {
					$populated_translation_id = $row_translation_id;
				}
			}

			if ( $el_id && (bool) ( $translation_id = $this->is_language_change( $el_id, $el_type, $trid ) ) === true
				&& (bool) $this->trid_lang_trans_id( $trid, $language_code ) === false
			) {
				$previous_language_code = $this->language_of_row( (int) $translation_id );

				$this->wpdb->update(
					$this->wpdb->prefix . 'icl_translations',
					array( 'language_code' => $language_code ),
					array( 'translation_id' => $translation_id )
				);

				do_action(
					'wpml_translation_update',
					array(
						'type'           => 'update',
						'trid'           => $trid,
						'element_id'     => $el_id,
						'element_type'   => $el_type,
						'translation_id' => $translation_id,
						'context'        => $context,
					)
				);

				if ( $previous_language_code && $previous_language_code !== $language_code ) {
					$this->move_source_language_of_group( $trid, $el_type, $context, $previous_language_code, $language_code );
				}

			} elseif ( $el_id && (bool) ( $translation_id = $existing_translation_id ) === true ) {
				$this->change_translation_of( $trid, $el_id, $el_type, $language_code, $src_language_code );
			} elseif ( $populated_translation_id ) {
				$translation_id = (int) $populated_translation_id;
			} elseif ( (bool) ( $translation_id = $this->is_placeholder_update( $trid, $language_code ) ) === true ) {
				$placeholder = array( 'element_id' => $el_id );
				if ( ! $el_id && $src_language_code ) {
					$placeholder['source_language_code'] = $src_language_code;
				}

				$this->wpdb->update(
					$this->wpdb->prefix . 'icl_translations',
					$placeholder,
					array( 'translation_id' => $translation_id )
				);

				do_action(
					'wpml_translation_update',
					array(
						'type'           => 'update',
						'trid'           => $trid,
						'element_id'     => $el_id,
						'element_type'   => $el_type,
						'translation_id' => $translation_id,
						'context'        => $context,
					)
				);

			} elseif ( (bool) ( $translation_id = $this->trid_lang_trans_id( $trid, $language_code ) ) === false ) {
				$translation_id = $this->insert_new_row( $el_id, $trid, $el_type, $language_code, $src_language_code );
			}
		} else {
			$this->delete_existing_row( $el_type, $el_id );
			$translation_id = $this->insert_new_row( $el_id, false, $el_type, $language_code, $src_language_code );
		}

		$this->clear_cache();
		if ( $translation_id && substr( $el_type, 0, 4 ) === 'tax_' ) {
			$taxonomy = substr( $el_type, 4 );
			do_action( 'created_term_translation', $taxonomy, $el_id, $language_code );
		}
		do_action( 'icl_set_element_language', $translation_id, $el_id, $language_code, $trid );

		wp_cache_delete( ...self::get_cache_ref( $el_id, $el_type ) );

		return $translation_id;
	}

	public static function get_cache_ref( $el_id, $el_type ) {
		return [ $el_id . ':' . $el_type, 'element_language_details' ];
	}

	private function get_existing( $element_type, $element_id ) {
		$wpdb = $this->wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid, language_code
					FROM {$wpdb->prefix}icl_translations
					WHERE element_type = %s
					AND element_id = %d
					LIMIT 1",
				$element_type,
				$element_id
			)
		);
	}

	private function trid_lang_trans_id( $trid, $lang ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id
													FROM {$wpdb->prefix}icl_translations
															WHERE trid = %d
																AND language_code = %s
															LIMIT 1",
				$trid,
				$lang
			)
		);
	}

	private function language_of_row( $translation_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d",
				$translation_id
			)
		);
	}

	private function move_source_language_of_group( $trid, $el_type, $context, $from, $to ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT translation_id, element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND source_language_code = %s",
				$trid,
				$from
			)
		);

		if ( ! $rows ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_translations',
			array( 'source_language_code' => $to ),
			array(
				'trid'                 => $trid,
				'source_language_code' => $from,
			)
		);

		foreach ( $rows as $row ) {
			do_action(
				'wpml_translation_update',
				array(
					'type'           => 'update',
					'trid'           => $trid,
					'element_id'     => null === $row->element_id ? null : (int) $row->element_id,
					'element_type'   => $el_type,
					'translation_id' => (int) $row->translation_id,
					'context'        => $context,
				)
			);
		}
	}

	private function change_translation_of( $trid, $el_id, $el_type, $language_code, $src_language_code ) {
		$src_language_code = empty( $src_language_code )
			? $this->sitepress->get_source_language_by_trid( $trid ) : $src_language_code;
		if ( $src_language_code !== $language_code ) {
			$this->release_status_of_row_leaving_group( $trid, $el_id, $el_type );

			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_translations',
				array(
					'trid'                 => $trid,
					'language_code'        => $language_code,
					'source_language_code' => $src_language_code,
				),
				array(
					'element_type' => $el_type,
					'element_id'   => $el_id,
				)
			);

			$context = explode( '_', $el_type );

			do_action(
				'wpml_translation_update',
				array(
					'type'         => 'update',
					'trid'         => $trid,
					'element_id'   => $el_id,
					'element_type' => $el_type,
					'context'      => $context[0],
				)
			);
		}
	}

	private function release_status_of_row_leaving_group( $trid, $el_id, $el_type ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, trid FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND element_id = %d LIMIT 1",
				$el_type,
				$el_id
			)
		);

		if ( is_object( $row ) && (int) $row->trid !== (int) $trid ) {
			WPML_Translation_Records_Delete::status_by_translation_ids( array( $row->translation_id ) );
		}
	}

	private function delete_existing_row( $el_type, $el_id ) {

		$context     = explode( '_', $el_type );
		$update_args = array(
			'element_id'   => $el_id,
			'element_type' => $el_type,
			'context'      => $context[0],
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

		WPML_Translation_Records_Delete::translations_where(
			'element_type = %s AND element_id = %d',
			array( $el_type, $el_id )
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );
	}

	private function insert_new_row( $el_id, $trid, $el_type, $language_code, $src_language_code ) {
		$wpdb = $this->wpdb;

		if ( ! $el_id && class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
			\WPML\TM\Jobs\JobLog::add( 'insert_new_row_null_element_id', [
				'el_id'                => $el_id,
				'el_type'              => $el_type,
				'trid'                 => $trid,
				'language_code'        => $language_code,
				'source_language_code' => $src_language_code,
			] );
		}

		$new = array(
			'element_type'  => $el_type,
			'language_code' => $language_code,
		);

		$trid_lock_acquired = false;
		if ( false === $trid ) {
			$trid_lock_acquired = $this->acquire_trid_allocation_lock();

			if ( ! $trid_lock_acquired ) {
				$this->log_trid_allocation_lock_failure( $el_id, $el_type, $language_code );

				return $el_id ? (int) $this->existing_element( $el_id, $el_type ) : 0;
			}
		}

		try {
			if ( false === $trid ) {
				$translation_id = $el_id ? $this->existing_element( $el_id, $el_type ) : false;
				if ( $translation_id ) {
					return (int) $translation_id;
				}

				$trid = 1 + (int) $this->wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );
			} else {
				$src_language_code           = empty( $src_language_code )
					? $this->sitepress->get_source_language_by_trid( $trid ) : $src_language_code;
				$new['source_language_code'] = $src_language_code;
			}

			$new['trid'] = $trid;
			if ( $el_id ) {
				$new['element_id'] = $el_id;
			}

			$this->wpdb->insert_id = 0;
			if ( isset( $new['source_language_code'], $new['element_id'] ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}icl_translations
						(element_type, language_code, source_language_code, trid, element_id)
						VALUES (%s, %s, %s, %d, %d)
						ON DUPLICATE KEY UPDATE translation_id = LAST_INSERT_ID(translation_id)",
						$new['element_type'],
						$new['language_code'],
						$new['source_language_code'],
						$new['trid'],
						$new['element_id']
					)
				);
			} elseif ( isset( $new['source_language_code'] ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}icl_translations
						(element_type, language_code, source_language_code, trid, element_id)
						VALUES (%s, %s, %s, %d, NULL)
						ON DUPLICATE KEY UPDATE translation_id = LAST_INSERT_ID(translation_id)",
						$new['element_type'],
						$new['language_code'],
						$new['source_language_code'],
						$new['trid']
					)
				);
			} elseif ( isset( $new['element_id'] ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}icl_translations
						(element_type, language_code, trid, element_id)
						VALUES (%s, %s, %d, %d)
						ON DUPLICATE KEY UPDATE translation_id = LAST_INSERT_ID(translation_id)",
						$new['element_type'],
						$new['language_code'],
						$new['trid'],
						$new['element_id']
					)
				);
			} else {
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}icl_translations
						(element_type, language_code, trid, element_id)
						VALUES (%s, %s, %d, NULL)
						ON DUPLICATE KEY UPDATE translation_id = LAST_INSERT_ID(translation_id)",
						$new['element_type'],
						$new['language_code'],
						$new['trid']
					)
				);
			}

			if ( false === $result ) {
				return 0;
			}

			$translation_id = (int) $this->wpdb->insert_id;
			$inserted       = 1 === (int) $result;
		} finally {
			if ( $trid_lock_acquired ) {
				$this->release_trid_allocation_lock();
			}
		}

		if ( ! $inserted ) {
			return $translation_id;
		}

		$context = explode( '_', $el_type );

		do_action(
			'wpml_translation_update',
			array(
				'type'           => 'insert',
				'trid'           => $trid,
				'element_id'     => $el_id,
				'element_type'   => $el_type,
				'translation_id' => $translation_id,
				'context'        => $context[0],
			)
		);

		return $translation_id;
	}

	private function acquire_trid_allocation_lock() {
		$this->trid_allocation_lock = \WPML\Container\make( \WPML\Utilities\AdvisoryLockFactory::class )->create( 'trid', self::TRID_ALLOCATION_LOCK_TTL_SECONDS );

		return $this->trid_allocation_lock->acquire( self::TRID_ALLOCATION_LOCK_TIMEOUT_SECONDS );
	}

	private function release_trid_allocation_lock() {
		$this->trid_allocation_lock->release();
	}

	private function log_trid_allocation_lock_failure( $el_id, $el_type, $language_code ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'[WPML] New trid allocation aborted: the allocator lock could not be acquired within %d seconds (element_id: %s, element_type: %s, language_code: %s).',
					self::TRID_ALLOCATION_LOCK_TIMEOUT_SECONDS,
					$el_id ? (string) $el_id : 'NULL',
					(string) $el_type,
					(string) $language_code
				)
			);
		}
	}

	private function is_language_change( $el_id, $el_type, $trid ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id
				 FROM {$wpdb->prefix}icl_translations
				 WHERE element_type = %s
				   AND element_id = %d
				   AND trid = %d",
				$el_type,
				$el_id,
				$trid
			)
		);
	}

	private function is_placeholder_update( $trid, $language_code ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"	SELECT translation_id
									FROM {$wpdb->prefix}icl_translations
									WHERE trid=%d
										AND language_code = %s
										AND element_id IS NULL",
				$trid,
				$language_code
			)
		);
	}

	private function existing_element( $el_id, $el_type ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id
					                   FROM {$wpdb->prefix}icl_translations
				                   WHERE element_type= %s
				                    AND element_id= %d
				                   LIMIT 1",
				$el_type,
				$el_id
			)
		);
	}

	private function may_create_in( $el_id, $el_type, $trid, $language_code, $src_language_code = null ) {
		if ( ! $el_id ) {
			return true;
		}

		if ( self::is_exempt_core_flow_running() ) {
			return true;
		}

		$reason = \WPML\Languages\LanguageWriteGuard::reasonFor( $language_code );

		if ( '' === $reason ) {
			return true;
		}

		if ( $this->is_translation_job_apply() ) {
			return true;
		}

		if ( \WPML\Languages\LanguageWriteGuard::REASON_PAUSED === $reason
			&& ! $this->is_translation_into( $trid, $language_code, $src_language_code ) ) {
			return true;
		}

		if ( $this->existing_element( $el_id, $el_type ) ) {
			return true;
		}

		if ( $trid && $this->is_placeholder_update( (int) $trid, $language_code ) ) {
			return true;
		}

		do_action(
			'wpml_content_creation_refused',
			(string) $language_code,
			$reason,
			array(
				'element_id'   => $el_id,
				'element_type' => $el_type,
				'trid'         => $trid,
			)
		);

		return false;
	}

	private function is_translation_job_apply() {
		return WPML_Save_Translation_Data_Action::is_delivery_window_open();
	}

	private function is_translation_into( $trid, $language_code, $src_language_code ) {
		if ( $src_language_code && $src_language_code !== $language_code ) {
			return true;
		}

		if ( ! $trid ) {
			return false;
		}

		$source = $this->sitepress->get_source_language_by_trid( $trid );

		return $source && $source !== $language_code;
	}

	private function maybe_delete_orphan( $trid, $language_code, $correct_element_id, $correct_element_is_registered ) {
		if ( ! $correct_element_id ) {
			return;
		}
		$wpdb = $this->wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, element_type, element_id
				 FROM {$wpdb->prefix}icl_translations
				 WHERE   trid = %d
					AND language_code = %s
					AND (
						element_id <> %d
						OR (element_id IS NULL AND %d = 1)
					)
					AND source_language_code IS NOT NULL
					",
				$trid,
				$language_code,
				$correct_element_id,
				$correct_element_is_registered
			)
		);

		$translation_id = ( null != $result ? $result->translation_id : null );

		if ( $translation_id ) {

			$context     = explode( '_', $result->element_type );
			$update_args = array(
				'trid'           => $trid,
				'element_id'     => $result->element_id,
				'element_type'   => $result->element_type,
				'translation_id' => $translation_id,
				'context'        => $context[0],
			);

			if ( class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
				\WPML\TM\Jobs\JobLog::addError( 'orphan_translation_deleted', [
					'trid'                  => (int) $trid,
					'language_code'         => $language_code,
					'translation_id'        => (int) $translation_id,
					'orphan_element_id'     => null === $result->element_id ? null : (int) $result->element_id,
					'kept_element_id'       => (int) $correct_element_id,
					'element_type'          => $result->element_type,
				] );
			}

			do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

			WPML_Translation_Records_Delete::translations_by_ids( array( $translation_id ) );

			do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );
		}
	}

	private function check_duplicate( $el_type, $el_id ) {
		$res   = false;
		$exp   = explode( '_', $el_type );
		$_type = $exp[0];
		if ( in_array( $_type, array( 'post', 'tax' ) ) ) {
			$res = $this->duplicate_from_db( $el_id, $el_type, $_type );
			if ( $res ) {
				$fix_assignments = new WPML_Fix_Type_Assignments( $this->sitepress );
				$fix_assignments->run();
				$res = $this->duplicate_from_db( $el_id, $el_type, $_type );
			}
		}

		return $res;
	}

	private function duplicate_from_db( $el_id, $el_type, $_type ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_type
	                        FROM {$wpdb->prefix}icl_translations
                        WHERE element_id = %d
                          AND element_type <> %s
                          AND element_type LIKE %s",
				$el_id,
				$el_type,
				$_type . '%'
			)
		);
	}

	private function clear_cache() {
		$this->term_translations->reload();
		$this->post_translations->reload();
		$this->sitepress->get_translations_cache()->clear();
	}
}
