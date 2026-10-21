<?php

class WPML_TM_Post_Actions extends WPML_Translation_Job_Helper {

	private $action_helper;

	private $blog_translators;

	private $tm_records;

	public function __construct(
		WPML_TM_Action_Helper $helper,
		WPML_TM_Blog_Translators $blog_translators,
		WPML_TM_Records $tm_records )
	{
		$this->action_helper    = $helper;
		$this->blog_translators = $blog_translators;
		$this->tm_records       = $tm_records;
	}

	public function save_post_actions( $post_id, $post, $force_set_status = false ) {
		global $wpdb, $sitepress, $current_user;

		$trid = isset( $_POST['icl_trid'] ) && is_numeric( $_POST['icl_trid'] )
			? $_POST['icl_trid'] : $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );

		$trid = apply_filters( 'wpml_tm_save_post_trid_value', isset( $trid ) ? $trid : '', $post_id );
		$lang = apply_filters( 'wpml_tm_save_post_lang_value', '', $post_id );

		$trid = $this->maybe_retrive_trid_again( $trid, $post );
		$needs_second_update = array_key_exists( 'needs_second_update', $_POST ) ? (bool) $_POST['needs_second_update'] : false;

		$is_original = empty( $trid )
			? false
			: ! (bool) $this->tm_records
				->icl_translations_by_element_id_and_type_prefix( $post_id, 'post_' . $post->post_type )
				->source_language_code();

		if ( ! empty( $trid ) && ! $is_original && ! $this->is_quick_edit() ) {
			$lang = $lang ? $lang : $this->get_save_post_lang( $lang, $post_id );
			$res  = $wpdb->get_row( $wpdb->prepare( "
			 SELECT element_id, language_code FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL
		 ",
				$trid ) );
			if ( $res ) {
				$original_post_id = $res->element_id;
				$from_lang        = $res->language_code;
				$original_post    = get_post( $original_post_id );
				if ( ! $original_post ) {
					return;
				}
				$md5              = $this->action_helper->post_md5( $original_post );
				$translation_id   = $this->tm_records
					->icl_translations_by_trid_and_lang( $trid, $lang )
					->translation_id();
				$status_record = $translation_id
					? $this->tm_records->icl_translation_status_by_translation_id( $translation_id )
					: null;

				$records_current_user = $this->save_records_the_current_user_as_translator( $post_id, $status_record );
				$user_id              = $records_current_user
					? $current_user->ID
					: $this->recorded_translator( $status_record );

				if ( $records_current_user ) {
					$this->maybe_add_as_translator( $user_id, $lang, $from_lang );
				}
				if ( $translation_id ) {
					$needs_update_before_save = $status_record->needs_update();
					$superseded_ate_job = $this->in_flight_ate_job( $status_record );
					$translation_package = $this->action_helper->create_translation_package( $original_post_id );
					list( $rid, $update ) = $this->action_helper->get_tm_instance()->update_translation_status( array(
						                                                                                            'translation_id'      => $translation_id,
						                                                                                            'status'              => isset( $force_set_status ) && $force_set_status > 0 ? $force_set_status : ICL_TM_COMPLETE,
						                                                                                            'translator_id'       => $user_id,
						                                                                                            'needs_update'        => $needs_second_update,
						                                                                                            'md5'                 => $md5,
						                                                                                            'translation_service' => 'local',
					                                                                                            ) );
					if ( ! $update ) {
						$job_id = $this->action_helper->add_translation_job( $rid, $user_id, $translation_package );
					} else {
						$job_id          = \WPML\TM\API\Job\Map::fromRid( $rid );
						if ( ! $job_id || $needs_update_before_save ) {
							$job_id = $this->action_helper->add_translation_job(
								$rid,
								$user_id,
								$translation_package
							);
						} elseif ( \WPML\TM\Jobs\TranslationRecord::isCompletedJobId( $job_id ) ) {
							$job_id = $this->translated_text_changed( $job_id, $post )
								? $this->action_helper->add_translation_job( $rid, $user_id, $translation_package )
								: 0;
						}
					}

					if ( $job_id ) {
						wpml_tm_load_old_jobs_editor()->set( $job_id, WPML_TM_Editors::WP );

						do_action( 'wpml_save_job_fields_from_post', $job_id );

						$this->complete_saved_job( $job_id );
					}

					if ( $superseded_ate_job ) {
						$this->cancel_superseded_ate_job( $superseded_ate_job, $post_id );
					}
				}
			}
		}

		if ( ! empty( $trid ) && empty( $_POST['icl_minor_edit'] ) ) {
			$is_original  = false;
			$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type, false, true );
			foreach ( $translations as $translation ) {
				if ( $translation->original == 1 && $translation->element_id == $post_id ) {
					$is_original = true;
					break;
				}
			}

			if ( $is_original ) {
				$statusesUpdater = $this->get_translation_statuses_updater( $post_id, $translations );

				if ( ! apply_filters( 'wpml_tm_delegate_translation_statuses_update', false, $post_id, $statusesUpdater ) ) {
					call_user_func( $statusesUpdater );
				}
			}
		}
	}

	protected function translated_text_changed( $job_id, $post ) {
		$job = new WPML_Post_Translation_Job( $job_id );

		return $job->fields_differ_from_post( $post );
	}

	private function complete_saved_job( $job_id ) {
		$job = $this->tm_records->icl_translate_job_by_job_id( $job_id );

		if ( (int) $job->translated() ) {
			$job->complete();
		}
	}

	private function is_quick_edit() {
		if( ! array_key_exists( 'action', $_POST ) )
			return false;

		return $_POST['action'] === 'inline-save';
	}

	private function in_flight_ate_job( $status_record ) {
		if ( ! $status_record ) {
			return null;
		}

		$status = (int) $status_record->status();
		if ( ! in_array( $status, \WPML\TM\ATE\Release\InFlightChargedJobs::IN_FLIGHT_STATUSES, true ) ) {
			return null;
		}

		$rid    = (int) $status_record->rid();
		$job_id = $rid > 0 ? (int) \WPML\TM\API\Job\Map::fromRid( $rid ) : 0;
		$job    = $job_id > 0 ? \WPML\TM\API\Jobs::get( $job_id ) : null;

		if ( ! is_object( $job )
			|| WPML_TM_Editors::ATE !== ( isset( $job->editor ) ? $job->editor : null )
			|| (int) ( isset( $job->editor_job_id ) ? $job->editor_job_id : 0 ) <= 0
			|| (int) ( isset( $job->translated ) ? $job->translated : 0 )
		) {
			return null;
		}

		return array(
			'job_id'        => $job_id,
			'rid'           => $rid,
			'editor_job_id' => (int) $job->editor_job_id,
			'status'        => $status,
		);
	}

	private function cancel_superseded_ate_job( array $ate_job, $post_id ) {
		$entity = new WPML_TM_Post_Job_Entity(
			$ate_job['rid'],
			WPML_TM_Job_Entity::POST_TYPE,
			0,
			new WPML_TM_Jobs_Batch( 0, '' ),
			$ate_job['status'],
			array()
		);
		$entity->set_translate_job_id( $ate_job['job_id'] );
		$entity->set_translation_service( 'local' );
		$entity->set_editor( WPML_TM_Editors::ATE );
		$entity->set_editor_job_id( $ate_job['editor_job_id'] );

		\WPML\TM\Jobs\JobLog::add(
			'translation_job_superseded_by_manual_save',
			array(
				'job_id'     => $ate_job['job_id'],
				'rid'        => $ate_job['rid'],
				'ate_job_id' => $ate_job['editor_job_id'],
				'post_id'    => (int) $post_id,
			)
		);

		$this->announce_cancel( $entity, WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED );

		\WPML\TM\ATE\Release\ReleaseNotices::queueManualSaveNotice( (int) $post_id );
	}

	protected function announce_cancel( WPML_TM_Post_Job_Entity $job, $reason ) {
		do_action( 'wpml_tm_job_cancelled', $job, $reason );
	}

	private function save_records_the_current_user_as_translator( $post_id, $status_record ) {
		return $this->is_edit_of_this_post( $post_id ) || ! $this->translation_has_a_job( $status_record );
	}

	private function is_edit_of_this_post( $post_id ) {
		if ( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] ) {
			return (int) $_POST['post_ID'] === (int) $post_id;
		}

		$edited = self::post_id_open_in_the_editor();

		return $edited && $edited === (int) $post_id;
	}

	private static function post_id_open_in_the_editor() {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) || ! WPML_URL_HTTP_Referer::is_post_edit_page() ) {
			return 0;
		}

		$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$query   = (string) wpml_parse_url( $referer, PHP_URL_QUERY );
		$parts   = array();
		parse_str( $query, $parts );

		return isset( $parts['post'] ) ? (int) $parts['post'] : 0;
	}

	private function translation_has_a_job( $status_record ) {
		if ( ! $status_record ) {
			return false;
		}

		$rid = $status_record->rid();

		return $rid && \WPML\TM\API\Job\Map::fromRid( $rid );
	}

	private function recorded_translator( $status_record ) {
		return $status_record ? (int) $status_record->translator_id() : 0;
	}

	public function get_translation_statuses_updater( $post_id, $translations ) {
		return function () use ( $post_id, $translations ) {
			$needsUpdate = false;
			$md5 = null;
			foreach ( $translations as $translation ) {
				if ( ! $translation->original ) {
					if ( null === $md5 ) {
						$md5 = $this->action_helper->post_md5( $post_id );
					}
					$statusRecord = $this->tm_records->icl_translation_status_by_translation_id( $translation->translation_id );
					$storedMd5    = $statusRecord->md5();
					if ( $md5 !== $storedMd5 ) {
						if ( $this->action_helper->post_md5_matches( $post_id, $storedMd5, $md5 ) ) {
							$statusRecord->update( [ 'md5' => $md5 ] );
							continue;
						}

						$needsUpdate = true;
						$status      = $statusRecord->status();

						if ( ! $statusRecord->exists() && $this->is_published_post_translation( $translation ) ) {
							$status = ICL_TM_COMPLETE;
						}

						$data                = [
							'translation_id'      => $translation->translation_id,
							'needs_update'        => 1,
							'md5'                 => $md5,
							'status'              => $status === ICL_TM_ATE_CANCELLED ? ICL_TM_NOT_TRANSLATED : $status,
						];
						$this->action_helper->get_tm_instance()->update_translation_status( $data );
					}
				}
			}
			return $needsUpdate;
		};
	}

	private function is_published_post_translation( $translation ) {
		$element_id = isset( $translation->element_id ) ? (int) $translation->element_id : 0;
		if ( ! $element_id ) {
			return false;
		}

		$element_type = isset( $translation->element_type ) ? (string) $translation->element_type : '';
		if ( '' !== $element_type && 0 !== strpos( $element_type, 'post_' ) ) {
			return false;
		}

		$translated_post = get_post( $element_id );

		return $translated_post && 'publish' === $translated_post->post_status;
	}

	private function maybe_add_as_translator( $user_id, $target_lang, $source_lang ) {

		$user = new WP_User( $user_id );
		if ( $user->has_cap( 'manage_options' ) && $target_lang && ! $this->blog_translators->is_translator( $user_id,
		                                                        array(
			                                                        'lang_from'      => $source_lang,
			                                                        'lang_to'        => $target_lang,
			                                                        'admin_override' => false
		                                                        ) )
		) {
			global $wpdb;

			$user->add_cap( \WPML\LIB\WP\User::CAP_TRANSLATE );

			$language_pair_records = new WPML_Language_Pair_Records( $wpdb, new WPML_Language_Records( $wpdb ) );
			$language_pair_records->store( $user_id, array( $source_lang => array( $target_lang ) ) );
		}
	}

	private function get_save_post_lang( $lang, $post_id ) {
		if ( ( ! isset( $lang ) || ! $lang ) && ! empty( $_POST['icl_post_language'] ) ) {
			$lang = $_POST['icl_post_language'];
		} else {
			global $wpml_post_translations;

			$lang = $wpml_post_translations->get_element_lang_code( $post_id );
		}

		return $lang;
	}

	private function maybe_retrive_trid_again( $trid, $post ) {
		global $wpdb, $sitepress;
		$element_type_from_trid = $wpdb->get_var( $wpdb->prepare( "SELECT element_type FROM {$wpdb->prefix}icl_translations WHERE trid=%d", $trid ) );
		if ( $element_type_from_trid && $post->post_type !== $element_type_from_trid ) {
			$trid = $sitepress->get_element_trid( $post->ID, 'post_' . $post->post_type );
		}

		return $trid;
	}
}
