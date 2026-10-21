<?php

use WPML\Legacy\Translation\Save\SyncParentPost\SyncParentPost;
use WPML\Translation\TranslationElements\FieldCompression;
use WPML\Hooks\WpmlSavePostHooks;
use WPML\TM\Jobs\JobLog;

class WPML_Save_Translation_Data_Action extends WPML_Translation_Job_Helper_With_API {

	const SKIP_SYNC_POST_DATE_FOR_TYPES = [
		'wp_navigation',
		'wp_template_part'
	];

	const SKIP_SITEPRESS_ACTIONS = 'skip_sitepress_actions';

	const JUST_CREATED_META = '_wpml_translation_just_created';

	const JOB_REQUEST_KEYS = [ 'post_ID', 'trid', 'lang', 'needs_second_update' ];

	const POST_KEY_BY_FIELD_TYPE = [
		'title'   => 'post_title',
		'body'    => 'post_content',
		'excerpt' => 'post_excerpt',
		'URL'     => 'post_name',
	];

	private $sitepress;

	private $tm_records;

	private $data;

	private $redirect_target = false;
	private $translate_link_targets_in_posts;
	private $translate_link_targets_in_strings;

	private $sync_parent_post;

	private $job_set_post_id = false;

	public static function is_delivery_window_open() {
		return ! empty( $_POST[ self::SKIP_SITEPRESS_ACTIONS ] );
	}

	public function __construct( $data, $tm_records ) {
		global $wpdb, $ICL_Pro_Translation, $sitepress, $wpml_post_translations;
		parent::__construct();
		$this->sitepress                         = $sitepress;
		$this->data                              = $data;
		$this->tm_records                        = $tm_records;
		$translate_link_targets_global_state     = new WPML_Translate_Link_Target_Global_State( $sitepress );
		$this->translate_link_targets_in_posts   = new WPML_Translate_Link_Targets_In_Posts( $translate_link_targets_global_state, $wpdb, $ICL_Pro_Translation );
		$this->translate_link_targets_in_strings = new WPML_Translate_Link_Targets_In_Strings( $translate_link_targets_global_state, $wpdb, new WPML_WP_API(), $ICL_Pro_Translation );
		$this->sync_parent_post                  = new SyncParentPost( $wpdb, $sitepress, $wpml_post_translations );
	}

	function save_translation() {
		$this->job_set_post_id = false;

		try {
			return $this->apply_translation();
		} catch ( Throwable $error ) {
			$this->forget_job_request_values( $this->job_set_post_id );

			throw $error;
		}
	}

	private function apply_translation() {
		global $wpdb, $sitepress, $iclTranslationManagement, $wpml_post_translations;

		$new_post_id   = false;
		$is_incomplete = false;
		$data          = $this->data;
		$job                 = ! empty( $data['job_id'] ) ? $this->get_translation_job( $data['job_id'], true ) : null;
		$needs_second_update = $job && $job->needs_update ? 1 : 0;
		$original_post       = null;
		$element_type_prefix = null;
		if ( is_object( $job ) ) {
			$element_type_prefix = $iclTranslationManagement->get_element_type_prefix_from_job( $job );
			$original_post       = $iclTranslationManagement->get_post( $job->original_doc_id, $element_type_prefix );
		}

		JobLog::add( 'save_translation_start', [
			'job_id'              => $data['job_id'] ?? null,
			'original_doc_id'     => is_object( $job ) ? ( $job->original_doc_id ?? null ) : null,
			'language_code'       => is_object( $job ) ? ( $job->language_code ?? null ) : null,
			'source_language_code'=> is_object( $job ) ? ( $job->source_language_code ?? null ) : null,
			'element_type_prefix' => $element_type_prefix,
			'has_complete_flag'   => ! empty( $data['complete'] ),
		] );

		$is_external      = apply_filters( 'wpml_is_external', false, $element_type_prefix );
		$data_to_validate = array(
			'original_post' => $original_post,
			'type_prefix'   => $element_type_prefix,
			'data'          => $data,
			'is_external'   => $is_external,
		);

		$validation_results = $this->get_validation_results( $job, $data_to_validate );

		if ( ! $validation_results['is_valid'] ) {
			JobLog::addError( 'save_translation_validation_failed', [
				'job_id'   => $data['job_id'] ?? null,
				'messages' => $validation_results['messages'] ?? [],
			] );
			$this->handle_failed_validation( $validation_results, $data_to_validate );
			$res = false;
		} else {
			foreach ( $data['fields'] as $fieldname => $field ) {
				if ( substr( $fieldname, 0, 6 ) === 'field-' ) {
					$field = apply_filters( 'wpml_tm_save_translation_cf', $field, $fieldname, $data );
				}
				$this->save_translation_field( $field['tid'], $field, $data['job_id'] );
				if ( ! isset( $field['finished'] ) || ! $field['finished'] ) {
					$is_incomplete = true;
				}
			}

			$icl_translate_job  = $this->tm_records->icl_translate_job_by_job_id( $data['job_id'] );
			$rid                = $icl_translate_job->rid();
			$translation_status = $this->tm_records->icl_translation_status_by_rid( $rid );
			$translation_id     = $translation_status->translation_id();

			JobLog::add( 'save_translation_resolved', [
				'job_id'         => $data['job_id'] ?? null,
				'rid'            => $rid,
				'translation_id' => $translation_id,
				'element_id'     => $translation_status->element_id(),
				'target_lang'    => is_object( $job ) ? ( $job->language_code ?? null ) : null,
			] );
			if ( ( $is_incomplete === true || empty( $data['complete'] ) ) && empty( $data['resign'] ) ) {
				$iclTranslationManagement->update_translation_status(
					array(
						'translation_id' => $translation_id,
						'status'         => ICL_TM_IN_PROGRESS,
					)
				);
				$icl_translate_job->update( array( 'translated' => 0 ) );

				self::notify_job_in_progress( $element_type_prefix, $job );
			}

			$element_id       = $translation_status->element_id();
			$translation_trid = null;

			if ( ! $is_external && 'post' === $element_type_prefix ) {
				$foreign_relation = $this->foreign_relation( $translation_id, $job, $original_post );

				if ( $foreign_relation ) {
					$foreign_relation['job']['job_id'] = $data['job_id'] ?? null;
					$foreign_relation['job']['rid']    = $rid;

					JobLog::add( 'save_translation_foreign_relation', $foreign_relation );

					$own_relation = $this->own_relation( $foreign_relation['job'], $translation_status );

					if ( ! $own_relation ) {
						JobLog::addError( 'save_translation_foreign_relation_unresolved', $foreign_relation );

						$this->add_message( [
							'type' => 'error',
							'text' => __( 'The translation could not be applied: this language of the content is already taken by another item or another translation job. Disconnect that item from the content\'s translations, or cancel the other job, and send the content to translation again.', 'sitepress' ),
						] );

						return false;
					}

					$translation_id   = $own_relation['translation_id'];
					$element_id       = $own_relation['element_id'];
					$translation_trid = $foreign_relation['job']['trid'];
				}
			}

			delete_post_meta( $element_id, '_icl_lang_duplicate_of' );

			$existing_translated_post = $element_id ? get_post( $element_id ) : null;
			$cached_copy_only         = $existing_translated_post && ! $this->post_row_exists( $element_id );

			if ( $cached_copy_only ) {
				clean_post_cache( (int) $element_id );
				$existing_translated_post = null;
			}

			if ( $element_id && ! $existing_translated_post ) {
				JobLog::add( 'save_translation_stale_relation', [
					'job_id'           => $data['job_id'] ?? null,
					'rid'              => $rid,
					'translation_id'   => $translation_id,
					'element_id'       => $element_id,
					'cached_copy_only' => $cached_copy_only,
				] );

				$element_id = null;
			}

			if ( ! empty( $data['complete'] ) && ! $is_incomplete ) {
				$job = $this->get_translation_job( $data['job_id'], true );

				$job_set_post_id = false;

				if ( $is_external ) {
					$icl_translate_job->complete();
					self::save_external( $element_type_prefix, $job, [ $this, 'decode_field_data' ] );
				} else {
					$translation_trid = $translation_trid ? $translation_trid : $translation_status->trid();

					if ( $element_id ) {
						$postarr['ID'] = $_POST['post_ID'] = $element_id;
						$job_set_post_id = true;
						$this->job_set_post_id = true;
					} else {
						$postarr['post_status'] = ! $sitepress->get_setting( 'translated_document_status' ) ? 'draft' : $original_post->post_status;
					}

					$undecodable_fields = [];

					foreach ( $job->elements as $field ) {
						$post_key = self::POST_KEY_BY_FIELD_TYPE[ $field->field_type ] ?? null;

						if ( null === $post_key ) {
							continue;
						}

						$decoded = $this->decode_translated_field( $field );

						if ( false === $decoded ) {
							$undecodable_fields[] = $field->field_type;
							continue;
						}

						$postarr[ $post_key ] = $decoded;
					}

					if ( $undecodable_fields ) {
						JobLog::addError( 'save_translation_undecodable_fields', [
							'job_id'      => $data['job_id'] ?? null,
							'rid'         => $rid,
							'field_types' => $undecodable_fields,
						] );

						$this->add_message( [
							'type' => 'error',
							'text' => sprintf(
								/* translators: %s is a comma-separated list of translation field names. */
								__( 'The translation could not be applied: its %s could not be decoded. The job has been left unfinished so it can be sent again.', 'sitepress' ),
								implode( ', ', $undecodable_fields )
							),
						] );

						$this->forget_job_request_values( $job_set_post_id );

						return false;
					}

					$this->sync_emptied_post_excerpt( $postarr, $original_post );

					$this->keep_untranslated_original_body( $postarr, $job, $original_post, ! $element_id );

					if ( isset( $postarr['post_content'] ) && is_string( $postarr['post_content'] ) ) {
						$postarr['post_content'] = \WPML\TM\Jobs\BlockDelimiterAttributes::restore(
							$postarr['post_content'],
							isset( $original_post->post_content ) ? (string) $original_post->post_content : ''
						);
					}

					$postarr['post_author'] = $original_post->post_author;
					$postarr['post_type']   = $original_post->post_type;

					if ( $sitepress->get_setting( 'sync_comment_status' ) ) {
						$postarr['comment_status'] = $original_post->comment_status;
					}
					if ( $sitepress->get_setting( 'sync_ping_status' ) ) {
						$postarr['ping_status'] = $original_post->ping_status;
					}
					if ( $sitepress->get_setting( 'sync_page_ordering' ) ) {
						$postarr['menu_order'] = $original_post->menu_order;
					}
					if ( $sitepress->get_setting( 'sync_private_flag' ) && $original_post->post_status == 'private' ) {
						$postarr['post_status'] = 'private';
					}
					if ( $sitepress->get_setting( 'sync_password' ) && $original_post->post_password ) {
						$postarr['post_password'] = $original_post->post_password;
					}

					$shouldSkipPostDateSync = in_array( $original_post->post_type, self::SKIP_SYNC_POST_DATE_FOR_TYPES );
					if ( ! $shouldSkipPostDateSync && $sitepress->get_setting( 'sync_post_date' ) ) {
						$postarr['post_date'] = $original_post->post_date;
					}

					$postarr = $this->sync_parent_post->linkParentTranslatedPostOrFlagOriginal( $original_post->post_parent, $job->language_code, $postarr );

					$_POST['trid']                         = $translation_trid;
					$_POST['lang']                         = $job->language_code;
					$_POST[ self::SKIP_SITEPRESS_ACTIONS ] = true;
					$_POST['needs_second_update']          = $needs_second_update;

					$postarr = apply_filters( 'icl_pre_save_pro_translation', $postarr );

					$postarr = apply_filters( 'wpml_pre_save_pro_translation', $postarr, $job );

					if ( $element_id ) {
						$translated_document_page_url = $sitepress->get_setting( 'translated_document_page_url' );
						switch ( $translated_document_page_url ) {
							case 'force-generate':
								$postarr['post_name'] = '';
								break;
						}

						$postarr['post_date']     = $existing_translated_post->post_date;
						$postarr['post_date_gmt'] = $existing_translated_post->post_date_gmt;
					}

					$is_new_translation = ! $element_id;

					if ( $is_new_translation ) {
						add_filter( 'wp_insert_post_data', [ __CLASS__, 'stamp_post_modified_now' ], PHP_INT_MAX );
					}

					$new_post_id = wpml_get_create_post_helper()->insert_post( $postarr, $job->language_code, true );

					if ( $is_new_translation ) {
						remove_filter( 'wp_insert_post_data', [ __CLASS__, 'stamp_post_modified_now' ], PHP_INT_MAX );
					}

					if (
						! $is_new_translation
						&& is_wp_error( $new_post_id )
						&& 'empty_content' === $new_post_id->get_error_code()
					) {
						JobLog::add( 'save_translation_post_not_updated', [
							'job_id'     => $data['job_id'] ?? null,
							'rid'        => $rid,
							'element_id' => $element_id,
							'reason'     => $new_post_id->get_error_message(),
						] );

						$new_post_id = (int) $element_id;
					}

					$post_write_failure = self::post_write_failure( $new_post_id, $is_new_translation );

					if ( null !== $post_write_failure ) {
						JobLog::addError(
							$is_new_translation ? 'save_translation_post_insert_failed' : 'save_translation_post_update_failed',
							[
								'job_id'      => $data['job_id'] ?? null,
								'rid'         => $rid,
								'new_post_id' => is_wp_error( $new_post_id ) ? null : $new_post_id,
								'wp_error'    => is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : null,
								'reason'      => $post_write_failure,
								'element_id'  => $element_id,
							]
						);

						$this->add_message( [
							'type' => 'error',
							'text' => $is_new_translation
								? __( 'The translation could not be applied: the translated document could not be created. The job has been left unfinished so it can be applied again.', 'sitepress' )
								: __( 'The translation could not be applied: the translated document could not be updated. The job has been left unfinished so it can be applied again.', 'sitepress' ),
						] );

						$this->forget_job_request_values( $job_set_post_id );

						return false;
					}

					if ( $is_new_translation ) {
						update_post_meta( $new_post_id, self::JUST_CREATED_META, 1 );
					}

					$this->sync_parent_post->linkUnlinkedChildPosts( $original_post->ID, $job->language_code, $new_post_id );

					JobLog::add( 'save_translation_post_inserted', [
						'job_id'          => $data['job_id'] ?? null,
						'rid'             => $rid,
						'new_post_id'     => $new_post_id,
						'pre_existing_element_id' => $element_id,
						'operation'       => $is_new_translation ? 'created' : 'updated',
					] );

					$link = get_edit_post_link( $new_post_id );
					if ( '' === $link ) {
						$link = get_permalink( $new_post_id );
					}

					if ( ! $element_id ) {
						$deletedRows = WPML_Translation_Records_Delete::translations_by_columns(
							array(
								'element_id'   => $new_post_id,
								'element_type' => 'post_' . $postarr['post_type'],
							)
						);

						$linkAffected = $wpdb->update(
							$wpdb->prefix . 'icl_translations',
							array( 'element_id' => $new_post_id ),
							array(
								'translation_id' => $translation_id,
								'trid'           => $translation_trid,
								'language_code'  => $job->language_code,
							)
						);

						$linkedElementId = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d AND trid = %d AND language_code = %s LIMIT 1",
								$translation_id,
								$translation_trid,
								$job->language_code
							)
						);

						$linkLanded = null !== $linkedElementId && (int) $linkedElementId === (int) $new_post_id;

						$linkData = [
							'job_id'              => $data['job_id'] ?? null,
							'rid'                 => $rid,
							'translation_id'      => $translation_id,
							'trid'                => $translation_trid,
							'language_code'       => $job->language_code,
							'new_post_id'         => $new_post_id,
							'linked_element_id'   => $linkedElementId,
							'icl_translations_update_affected_rows' => is_int( $linkAffected ) ? $linkAffected : null,
							'icl_translations_delete_affected_rows' => is_int( $deletedRows ) ? $deletedRows : null,
						];

						if ( ! $linkLanded ) {
							JobLog::addError( 'save_translation_element_id_link_failed', $linkData );

							$this->add_message( [
								'type' => 'error',
								'text' => __( 'The translation could not be applied: the translated document was created but could not be linked to the original. The job has been left unfinished so it can be applied again.', 'sitepress' ),
							] );

							$this->forget_job_request_values( $job_set_post_id );

							return false;
						}

						JobLog::add( 'save_translation_element_id_linked', $linkData );

						do_action(
							'wpml_translation_update',
							array(
								'type'           => 'update',
								'trid'           => (int) $translation_trid,
								'element_id'     => (int) $new_post_id,
								'element_type'   => 'post_' . $postarr['post_type'],
								'translation_id' => (int) $translation_id,
								'context'        => 'post',
							)
						);

						/* translators: First half of a message; the title of the translated content follows it as a link, as in "Translation added: My page." Keep the space after the colon. */
						$user_message = __( 'Translation added: ', 'sitepress' ) . '<a href="' . $link . '">' . $postarr['post_title'] . '</a>.';
					} else {
						/* translators: First half of a message; the title of the translated content follows it as a link, as in "Translation updated: My page." Keep the space after the colon. */
						$user_message = __( 'Translation updated: ', 'sitepress' ) . '<a href="' . $link . '">' . $postarr['post_title'] . '</a>.';
					}

					$icl_translate_job->complete();

					icl_cache_clear( $postarr['post_type'] . 's_per_language' );
					do_action( 'wpml_pro_translation_after_post_save', $new_post_id );

					if ( ! current_user_can( 'manage-categories' ) && ! empty( $postarr['tax_input'] ) ) {
						foreach ( $postarr['tax_input'] as $taxonomy => $terms ) {
							wp_set_post_terms( $new_post_id, $terms, $taxonomy, false );
						}
					}

					$data['fields'] = apply_filters( 'wpml_tm_job_fields', $data['fields'], $job );

					do_action( 'icl_pro_translation_saved', $new_post_id, $data['fields'], $job );
					do_action( 'wpml_translation_job_saved', $new_post_id, $data['fields'], $job );

					$new_post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $new_post_id ) );
					foreach ( $job->elements as $job_element ) {
						if ( $job_element->field_type === 'body' ) {
							$fields_data_translated = apply_filters( 'wpml_tm_job_data_post_content', $new_post_content );
							$fields_data_translated = FieldCompression::compressAndTrack( $fields_data_translated, false, $data['job_id'] );
							$wpdb->update(
								$wpdb->prefix . 'icl_translate',
								array( 'field_data_translated' => $fields_data_translated ),
								array(
									'job_id'     => $data['job_id'],
									'field_type' => 'body',
								)
							);

							break;
						}
					}

					$sitepress->copy_custom_fields( $original_post->ID, $new_post_id );

					$copied_custom_fields = array( '_top_nav_excluded', '_cms_nav_minihome' );
					foreach ( $copied_custom_fields as $ccf ) {
						$val = get_post_meta( $original_post->ID, $ccf, true );
						update_post_meta( $new_post_id, $ccf, $val );
					}

					if ( $sitepress->get_setting( 'sync_page_template' ) ) {
						$_wp_page_template = get_post_meta( $original_post->ID, '_wp_page_template', true );
						if ( ! empty( $_wp_page_template ) ) {
							update_post_meta( $new_post_id, '_wp_page_template', $_wp_page_template );
						}
					}

					$original_meta = get_post_custom( $original_post->ID );
					$custom_fields = \WPML\TM\Settings\Repository::getCustomFields()
						+ wpml_resolve_custom_field_preferences(
							is_array( $original_meta ) ? array_keys( $original_meta ) : []
						);

					$this->package_helper->save_job_custom_fields(
						$job,
						$new_post_id,
						$custom_fields
					);

					$sticky_posts       = get_option( 'sticky_posts' );
					$sticky_posts       = is_array( $sticky_posts ) ? $sticky_posts : [];
					$is_original_sticky = $original_post->post_type == 'post' && in_array( $original_post->ID, $sticky_posts );

					if ( $is_original_sticky && $sitepress->get_setting( 'sync_sticky_flag' ) ) {
						stick_post( $new_post_id );
					} else {
						if ( $original_post->post_type == 'post' && ! is_null( $element_id ) ) {
							unstick_post( $new_post_id );
						}
					}

					$this->add_message(
						array(
							'type' => 'updated',
							'text' => $user_message,
						)
					);
					$this->clear_page_name_cache( $new_post_id );
					WPML_Pre_Option_Page::maybe_clear_privacy_policy_cache( $original_post->ID, $postarr['post_type'] );
				}

				$iclTranslationManagement->set_page_url( $new_post_id );

				if ( isset( $job ) && isset( $job->language_code ) && isset( $job->source_language_code ) ) {
					$this->save_terms_for_job( $data['job_id'] );
				}

				if ( $sitepress->get_setting( 'sync_post_format' ) ) {
					$_wp_post_format = get_post_format( $original_post->ID );
					$_wp_post_format && set_post_format( $new_post_id, $_wp_post_format );
				}

				do_action( 'icl_pro_translation_completed', $new_post_id, $data['fields'], $job );
				do_action( 'wpml_pro_translation_completed', $new_post_id, $data['fields'], $job );

				if ( $new_post_id ) {
					delete_post_meta( $new_post_id, self::JUST_CREATED_META );
				}

				$this->sitepress->executeSavePostHookOnPostTranslationSave( $new_post_id );

				$expectedStatus = apply_filters( 'wpml_tm_applied_job_status', ICL_TM_COMPLETE, $job, $new_post_id );
				$translation_status->update( [
					'status'       => $expectedStatus,
					'needs_update' => $needs_second_update,
				] );

				if ( JobLog::canLog() ) {
					$actualStatus = $wpdb->get_var( $wpdb->prepare(
						"SELECT status FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d LIMIT 1",
						$rid
					) );

					if ( $actualStatus === null ) {
						JobLog::addError( 'save_translation_status_rid_missing_after_update', [
							'job_id'          => $data['job_id'] ?? null,
							'rid'             => $rid,
							'translation_id'  => $translation_id,
							'expected_status' => (int) $expectedStatus,
							'new_post_id'     => $new_post_id,
						] );
					} elseif ( (int) $actualStatus !== (int) $expectedStatus ) {
						JobLog::addError( 'save_translation_status_unexpected_after_update', [
							'job_id'          => $data['job_id'] ?? null,
							'rid'             => $rid,
							'expected_status' => (int) $expectedStatus,
							'actual_status'   => (int) $actualStatus,
							'new_post_id'     => $new_post_id,
						] );
					} else {
						JobLog::add( 'save_translation_status_finalised', [
							'job_id'       => $data['job_id'] ?? null,
							'rid'          => $rid,
							'status'       => (int) $actualStatus,
							'new_post_id'  => $new_post_id,
							'needs_update' => $needs_second_update,
						] );
					}
				}

				$this->forget_job_request_values( $job_set_post_id );

				if ( ! defined( 'REST_REQUEST' ) && ! defined( 'XMLRPC_REQUEST' ) && ! defined( 'DOING_AJAX' ) && ! isset( $_POST['xliff_upload'] ) ) {
					$action_type           = is_null( $element_id ) ? 'added' : 'updated';
					$element_id            = is_null( $element_id ) ? $new_post_id : $element_id;
					$this->redirect_target = admin_url( sprintf( '%s&%s=%d&element_type=%s', \WPML\TM\Menu\TranslationQueue\TranslationQueuePage::base(), $action_type, $element_id, $element_type_prefix ) );
				}
			} else {
				$this->add_message(
					array(
						'type' => 'updated',
						'text' => __( 'Translation (incomplete) saved.', 'sitepress' ),
					)
				);
			}

			$res = true;
		}

		return $res;
	}

	private function forget_job_request_values( $job_set_post_id ) {
		foreach ( self::JOB_REQUEST_KEYS as $key ) {
			if ( 'post_ID' !== $key || $job_set_post_id ) {
				unset( $_POST[ $key ] );
			}
		}
	}

	private static function post_write_failure( $result, $is_new_translation ) {
		if ( is_wp_error( $result ) ) {
			$message = $result->get_error_message();

			return '' !== (string) $message ? (string) $message : (string) $result->get_error_code();
		}

		if ( (int) $result <= 0 ) {
			return 'insert_post returned ' . ( false === $result ? 'false' : (string) (int) $result );
		}

		if ( ! get_post( (int) $result ) ) {
			return 'the ' . ( $is_new_translation ? 'created' : 'updated' ) . ' post ' . (int) $result . ' cannot be read back';
		}

		return null;
	}

	private function post_row_exists( $post_id ) {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

		return null !== $found && (int) $found === (int) $post_id;
	}

	private function foreign_relation( $translation_id, $job, $original_post ) {
		global $wpdb;

		if ( ! $translation_id || ! is_object( $job ) || ! is_object( $original_post ) ) {
			return null;
		}

		$element_type = 'post_' . $original_post->post_type;

		$original = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid, language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s LIMIT 1",
				$original_post->ID,
				$element_type
			)
		);

		if ( ! is_object( $original ) || ! (int) $original->trid ) {
			return null;
		}

		$relation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, element_id, trid, language_code, source_language_code, element_type FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d",
				$translation_id
			)
		);

		if (
			is_object( $relation )
			&& (int) $relation->trid === (int) $original->trid
			&& (string) $relation->language_code === (string) $job->language_code
			&& (string) $relation->element_type === $element_type
		) {
			return null;
		}

		return [
			'job'      => [
				'original_doc_id'      => (int) $original_post->ID,
				'trid'                 => (int) $original->trid,
				'language_code'        => (string) $job->language_code,
				'source_language_code' => (string) $original->language_code,
				'element_type'         => $element_type,
				'translation_id'       => (int) $translation_id,
			],
			'relation' => is_object( $relation ) ? (array) $relation : null,
		];
	}

	private function own_relation( array $job_side, $translation_status ) {
		global $wpdb, $sitepress;

		$own = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, element_id, element_type FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s LIMIT 1",
				$job_side['trid'],
				$job_side['language_code']
			)
		);

		if ( is_object( $own ) ) {
			if ( (string) $own->element_type !== $job_side['element_type'] ) {
				return null;
			}

			$tracked_by = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
					$own->translation_id
				)
			);

			if ( $tracked_by && $tracked_by !== (int) $translation_status->rid() ) {
				return null;
			}

			$translation_id = (int) $own->translation_id;
			$element_id     = $own->element_id ? (int) $own->element_id : null;
		} else {
			$translation_id = (int) $sitepress->set_element_language_details(
				null,
				$job_side['element_type'],
				$job_side['trid'],
				$job_side['language_code'],
				$job_side['source_language_code']
			);
			$element_id     = null;

			if ( ! $translation_id ) {
				return null;
			}
		}

		$translation_status->update( [ 'translation_id' => $translation_id ] );

		return [
			'translation_id' => $translation_id,
			'element_id'     => $element_id,
		];
	}

	public static function stamp_post_modified_now( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data['post_modified']     = current_time( 'mysql' );
		$data['post_modified_gmt'] = current_time( 'mysql', 1 );

		return $data;
	}

	private function decode_translated_field( $field ) {
		$stored = isset( $field->field_data_translated ) ? $field->field_data_translated : '';
		$format = isset( $field->field_format ) ? $field->field_format : '';

		$decoded = $this->decode_field_data( $stored, $format );

		if ( self::is_usable_decoded_value( $decoded ) || '' === (string) $stored ) {
			return $decoded;
		}

		$repaired = FieldCompression::fixDoubleCompression( $stored );

		if ( ! empty( $repaired['was_double_compressed'] ) ) {
			$recovered = $this->decode_field_data( $repaired['data'], $format );

			if ( self::is_usable_decoded_value( $recovered ) ) {
				return $recovered;
			}
		}

		$decompressed = FieldCompression::decompress( $stored, true );

		if ( $decompressed !== $stored ) {
			$recovered = $this->decode_field_data( $decompressed, $format );

			if ( self::is_usable_decoded_value( $recovered ) ) {
				return $recovered;
			}
		}

		if ( self::is_empty_decoded_value( $decoded ) ) {
			return false;
		}

		return $decoded;
	}

	private static function is_usable_decoded_value( $value ) {
		if ( self::is_empty_decoded_value( $value ) ) {
			return false;
		}

		foreach ( (array) $value as $item ) {
			if ( ! self::is_valid_utf8( (string) $item ) ) {
				return false;
			}
		}

		return true;
	}

	private static function is_valid_utf8( $value ) {
		if ( function_exists( 'mb_check_encoding' ) ) {
			return mb_check_encoding( $value, 'UTF-8' );
		}

		return (bool) preg_match( '//u', $value );
	}

	private function keep_untranslated_original_body( array &$postarr, $job, $original_post, $is_new_translation ) {
		if ( ! is_object( $original_post ) || ! isset( $original_post->post_content ) ) {
			return;
		}

		$body = null;

		foreach ( isset( $job->elements ) && is_array( $job->elements ) ? $job->elements : [] as $field ) {
			$field_type = isset( $field->field_type ) ? (string) $field->field_type : '';

			if ( 0 === strpos( $field_type, 'package-string-' ) ) {
				return;
			}

			if ( 'body' === $field_type ) {
				$body = $field;
			}
		}

		if ( $body && 0 !== (int) $body->field_translate ) {
			return;
		}

		if ( ! $body && ! $is_new_translation ) {
			return;
		}

		$postarr['post_content'] = $original_post->post_content;
	}

	private static function is_empty_decoded_value( $value ) {
		if ( is_array( $value ) ) {
			return ! array_filter( $value, function ( $item ) {
				return '' !== (string) $item;
			} );
		}

		return '' === (string) $value;
	}

	private function sync_emptied_post_excerpt( array &$postarr, $original_post ) {
		if ( ! is_object( $original_post ) || ! isset( $original_post->post_excerpt ) ) {
			return;
		}
		$original_excerpt = (string) $original_post->post_excerpt;
		if ( '' !== trim( $original_excerpt ) ) {
			return;
		}
		$postarr['post_excerpt'] = $original_excerpt;
	}

	private function clear_page_name_cache( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}

		$base_key = 'get_single_slug_adjusted_IDs' . $post->post_type . $post->post_name;
		wp_cache_delete( $base_key . $post->post_parent, 'WPML_Page_Name_Query_Filter' );
		wp_cache_delete( $base_key, 'WPML_Page_Name_Query_Filter' );
	}

	function get_redirect_target() {

		return $this->redirect_target;
	}

	private function save_translation_field( $tid, $field, $job_id = null ) {
		global $wpdb;

		$update = [];
		if ( isset( $field['data'] ) ) {
			$update['field_data_translated'] = FieldCompression::compressAndTrack( $field['data'], false, $job_id );
		}
		$update['field_finished'] = isset( $field['finished'] ) && $field['finished'] ? 1 : 0;

		$wpdb->update( $wpdb->prefix . 'icl_translate', $update, array( 'tid' => $tid ) );
	}

	private function handle_failed_validation( $validation_results, $data_to_validate ) {
		if ( isset( $validation_results['messages'] ) ) {
			$messages = (array) $validation_results['messages'];
			if ( $messages ) {
				foreach ( $messages as $message ) {
					$this->add_message(
						array(
							'type' => 'error',
							'text' => $message,
						)
					);
				}
			} else {
				$this->add_message(
					array(
						'type' => 'error',
						'text' => __( 'Submitted data is not valid.', 'sitepress' ),
					)
				);
			}
		}
		do_action( 'wpml_translation_validation_failed', $validation_results, $data_to_validate );
	}

	private function get_validation_results( $job, $data_to_validate ) {
		$is_valid                   = true;
		$original_post              = $data_to_validate['original_post'];
		$element_type_prefix        = $data_to_validate['type_prefix'];
		$validation_default_results = array(
			'is_valid' => $is_valid,
			'messages' => array(),
		);
		if ( ! $job || ! $original_post || ! $element_type_prefix ) {
			$is_valid = false;
			if ( ! $job ) {
				/* translators: Error message shown when a request arrives without the number that says which translation job it is about. */
				$validation_default_results['messages'][] = __( 'Job ID is missing', 'sitepress' );
			}
			if ( ! $original_post ) {
				$validation_default_results['messages'][] = __( 'The original post cannot be retrieved', 'sitepress' );
			}
			if ( ! $element_type_prefix ) {
				$validation_default_results['messages'][] = __( 'The type of the post cannot be retrieved', 'sitepress' );
			}
		} elseif ( ! $this->tm_records->icl_translate_job_by_job_id( $job->job_id )->is_open() ) {
			$is_valid                                 = false;
			$validation_default_results['messages'][] = __( 'This job cannot be edited anymore because a newer job for this element exists.', 'sitepress' );
		}
		$validation_default_results['is_valid'] = $is_valid;
		$validation_results                     = apply_filters( 'wpml_translation_validation_data', $validation_default_results, $data_to_validate );
		$validation_results                     = array_merge( $validation_default_results, $validation_results );

		if ( ! $is_valid && $validation_results['is_valid'] ) {
			$validation_results['is_valid'] = $is_valid;
		}

		return $validation_results;
	}

	private function save_terms_for_job( $job_id ) {
		require_once WPML_TM_PATH . '/inc/translation-jobs/wpml-translation-jobs-collection.class.php';

		$job = new WPML_Post_Translation_Job( $job_id );
		$job->save_terms_to_post();
	}

	private function add_message( $message ) {
		global $iclTranslationManagement;

		$iclTranslationManagement->add_message( $message );
	}

	private static function save_external( $element_type_prefix, $job, $decoder ) {
		do_action( 'wpml_save_external', $element_type_prefix, $job, $decoder );
	}

	private static function notify_job_in_progress( $element_type_prefix, $job ) {
		do_action( 'wpml_tm_job_in_progress', $element_type_prefix, $job );
	}
}
