<?php

class WPML_Term_Actions extends WPML_Full_Translation_API {

	private $delete_recursion_flag = false;

	public static $delete_sync_suspended = false;

	private $items_detached_from_menu = array();

	private $detached_items_cleanup_hooked = false;

	private $deletion_settings;

	private $dialog_answer;

	private function dialog_answer() {
		if ( ! $this->dialog_answer ) {
			$this->dialog_answer = new \WPML\ContentDeletion\DialogAnswer();
		}

		return $this->dialog_answer;
	}

	private function delete_cascade_action( $taxonomy ) {
		$answered = $this->dialog_answer()->originalAction();

		return null !== $answered ? $answered : $this->deletion_settings()->termOriginalAction( $taxonomy );
	}

	private function deletion_settings() {
		if ( ! $this->deletion_settings ) {
			$sitepress               = $this->sitepress;
			$this->deletion_settings = new \WPML\ContentDeletion\Settings(
				function ( $key, $default = false ) use ( $sitepress ) {
					return $sitepress->get_setting( $key, $default );
				}
			);
		}

		return $this->deletion_settings;
	}

	public function generate_unique_term_slug_ajax_handler() {
		if ( $this->sitepress->get_wp_api()->is_ajax() && wp_verify_nonce( $_POST['nonce'], 'wpml_generate_unique_slug_nonce' ) ) {
			$term          = array_key_exists( 'term', $_POST ) ? sanitize_text_field( $_POST['term'] ) : '';
			$taxonomy      = array_key_exists( 'taxonomy', $_POST ) ? sanitize_text_field( $_POST['taxonomy'] ) : '';
			$language_code = array_key_exists( 'language_code', $_POST ) ? sanitize_text_field( $_POST['language_code'] ) : '';

			wp_send_json_success(
				array(
					'slug' => urldecode( $this->term_translations->generate_unique_term_slug( $term, '', $taxonomy, $language_code ) ),
				)
			);
		} else {
			wp_send_json_error();
		}
	}

	function save_term_actions( $tt_id, $taxonomy ) {
		if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		};
		$post_action  = filter_input( INPUT_POST, 'action' );
		$term_lang    = $this->get_term_lang( $tt_id, $post_action, $taxonomy );
		$trid         = $this->get_saved_term_trid( $tt_id, $post_action );
		$src_language = $this->term_translations->get_source_lang_code( $tt_id );
		$this->sitepress->set_element_language_details(
			$tt_id,
			'tax_' . $taxonomy,
			$trid,
			$term_lang,
			$src_language
		);

		add_action( "saved_{$taxonomy}", array( $this, 'sync_term_meta' ), PHP_INT_MAX, 2 );
	}

	public function sync_term_meta( $term_id, $tt_id ) {
		$is_new_term      = 'created_term' === current_filter();
		$sync_meta_action = new WPML_Sync_Term_Meta_Action( $this->sitepress, $tt_id, $is_new_term );
		$sync_meta_action->run();
	}

	function delete_term_actions( $term_taxonomy_id, $taxonomy_name ) {
		$element_type = 'tax_' . $taxonomy_name;

		$lang_details = $this->sitepress->get_element_language_details( $term_taxonomy_id, $element_type );
		if ( ! $lang_details ) {
			return;
		}

		$trid = $lang_details->trid;

		if ( empty( $lang_details->source_language_code ) ) {
			if ( ! $this->delete_recursion_flag && ! self::$delete_sync_suspended
				&& \WPML\ContentDeletion\Settings::ALL === $this->delete_cascade_action( $taxonomy_name ) ) {
				$this->delete_recursion_flag = true;
				$translations                = $this->sitepress->get_element_translations( $trid, $element_type );
				$this->delete_translations( $term_taxonomy_id, $taxonomy_name, $translations );
				$this->delete_recursion_flag = false;
			} else {
				$this->restore_items_detached_from_translated_menus( $trid, $element_type, $term_taxonomy_id );
				$promote_to = $this->dialog_answer()->promoteTo();
				$this->set_new_original_term(
					$trid,
					$lang_details->language_code,
					$promote_to === $lang_details->language_code ? null : $promote_to
				);
			}
		}

		$update_args = array(
			'element_id'   => $term_taxonomy_id,
			'element_type' => $element_type,
			'context'      => 'tax',
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );
		WPML_Translation_Records_Delete::translations_by_columns(
			array(
				'element_type' => $element_type,
				'element_id'   => $term_taxonomy_id,
			)
		);
		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );
	}

	public function set_new_original_term( $trid, $deleted_language_code, $preferred = null ) {
		$wpdb = $this->wpdb;
		if ( $trid && $deleted_language_code ) {
			$order_languages = $this->sitepress->get_setting( 'languages_order' );
			$this->term_translations->reload();
			$translations = $this->term_translations->get_element_translations( false, $trid );
			$translations = is_array( $translations ) ? $translations : array();
			unset( $translations[ $deleted_language_code ] );

			$new_source_lang_code = \WPML\ContentDeletion\PromotePick::pick(
				is_array( $order_languages ) ? $order_languages : array(),
				$translations,
				0,
				$preferred
			);
			if ( $new_source_lang_code ) {
				$rows_updated = $this->wpdb->update(
					$this->wpdb->prefix . 'icl_translations',
					array( 'source_language_code' => $new_source_lang_code ),
					array(
						'trid'                 => $trid,
						'source_language_code' => $deleted_language_code,
					)
				);

				if ( 0 < $rows_updated ) {
					do_action( 'wpml_translation_update', array( 'trid' => $trid ) );

					do_action(
						\WPML\ContentDeletion\ItemDeleteRecorder::HOOK_PROMOTED,
						$trid,
						$new_source_lang_code
					);
				}

				$this->wpdb->query(
					"UPDATE {$wpdb->prefix}icl_translations
									 SET source_language_code = NULL
									 WHERE language_code = source_language_code"
				);
			}
		}
	}

	public function isTranslatedTermValidForRelationDeletion( $term, $postTranslations ) {
		$valid = isset( $postTranslations[ $term->language_code ] );

		if ( $term->original && $valid ) {
			$valid = ! $postTranslations[ $term->language_code ]->original;
		}

		return $valid;
	}

	public function deleted_term_relationships( $post_id, $delete_terms, $taxonomy ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$postTrid = $this->sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );

		if ( ! $postTrid ) {
			return;
		}

		$isOriginalPost = function ( $translation ) use ( $post_id ) {
			return $translation->original == 1 && $translation->element_id == $post_id;
		};

		$postTranslations = $this->sitepress->get_element_translations( $postTrid, 'post_' . $post->post_type );
		$originalPost     = wpml_collect( $postTranslations )->filter( $isOriginalPost )->first();

		if ( ! $originalPost ) {
			return;
		}

		$getTermsTrids = function ( $termId ) use ( $taxonomy ) {
			return $this->sitepress->get_element_trid( $termId, 'tax_' . $taxonomy );
		};

		$onlyValidTermsTrids = function ( $trid ) {
			return \WPML\FP\Logic::isTruthy( $trid );
		};

		$getDeletedTermsTranslations = function ( $trid ) use ( $taxonomy ) {
			return $this->sitepress->get_element_translations( $trid, 'tax_' . $taxonomy );
		};

		$removedByLanguage = array();

		$deleteTermTranslationsRelations = function ( $deletedTermTranslation ) use ( $postTranslations, $taxonomy, &$removedByLanguage ) {
			if ( $this->isTranslatedTermValidForRelationDeletion( $deletedTermTranslation, $postTranslations ) ) {
				$translated_post = $postTranslations[ $deletedTermTranslation->language_code ];
				$deleted         = $this->wpdb->delete(
					$this->wpdb->term_relationships,
					array(
						'object_id'        => $translated_post->element_id,
						'term_taxonomy_id' => $deletedTermTranslation->element_id,
					)
				);
				if ( $deleted ) {
					$removedByLanguage[ $deletedTermTranslation->language_code ][] = (int) $deletedTermTranslation->element_id;
				}
				if ( 'nav_menu' === $taxonomy ) {
					$this->items_detached_from_menu[ (int) $deletedTermTranslation->element_id ][] = (int) $translated_post->element_id;
					$this->hook_detached_items_cleanup();
				}
			}
		};

		$deletedTermsTranslations = wpml_collect( $delete_terms )
			->map( $getTermsTrids )
			->filter( $onlyValidTermsTrids )
			->map( $getDeletedTermsTranslations )
			->flatten()
			->toArray();

		\WPML\FP\Fns::map( \WPML\FP\Fns::tap( $deleteTermTranslationsRelations ), $deletedTermsTranslations );

		$this->update_term_counts_in_languages( $removedByLanguage, $taxonomy );
	}

	private function update_term_counts_in_languages( array $tt_ids_by_language, $taxonomy ) {
		if ( ! $tt_ids_by_language ) {
			return;
		}

		$sitepress             = $this->sitepress;
		$argsFilterRemoved     = remove_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ) );
		$getTermsFilterRemoved = remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1 );
		$clausesFilterRemoved  = remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );

		try {
			foreach ( $tt_ids_by_language as $language => $tt_ids ) {
				$sitepress->switch_lang( $language );
				try {
					wp_update_term_count( array_values( array_unique( $tt_ids ) ), $taxonomy );
				} finally {
					$sitepress->switch_lang();
				}
			}
		} finally {
			if ( $argsFilterRemoved ) {
				add_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ), 10, 2 );
			}
			if ( $getTermsFilterRemoved ) {
				add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
			}
			if ( $clausesFilterRemoved ) {
				add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10, 3 );
			}
		}
	}

	public function added_term_relationships( $object_id ) {
		$wpdb = $this->wpdb;

		$corrections = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
									ctt.taxonomy,
									ctt.term_id,
									p.ID
								FROM {$wpdb->posts} p
								JOIN {$wpdb->prefix}icl_translations i
								  ON p.ID = i.element_id
								     AND i.element_type = CONCAT('post_', p.post_type)
							    JOIN {$wpdb->prefix}icl_translations ip
									ON ip.trid = i.trid
										AND i.source_language_code = ip.language_code
								JOIN {$wpdb->prefix}icl_translations it
								JOIN {$wpdb->term_taxonomy} tt
								    ON tt.term_taxonomy_id = it.element_id
								       AND CONCAT('tax_', tt.taxonomy) = it.element_type
								JOIN {$wpdb->term_relationships} objrel
								  ON objrel.object_id = ip.element_id
								    AND objrel.term_taxonomy_id = tt.term_taxonomy_id
								JOIN {$wpdb->prefix}icl_translations itt
								  ON itt.trid = it.trid
								    AND itt.language_code = i.language_code
								JOIN {$wpdb->term_taxonomy} ctt
								  ON ctt.term_taxonomy_id = itt.element_id
								LEFT JOIN {$wpdb->term_relationships} trans_rel
									ON trans_rel.object_id = p.ID
										AND trans_rel.term_taxonomy_id = ctt.term_taxonomy_id
								WHERE  ip.element_id = %d
									AND trans_rel.object_id IS NULL",
				$object_id
			)
		);

		is_array( $corrections ) && $this->apply_added_term_changes( $corrections );
	}

	private function apply_added_term_changes( $corrections ) {
		$changes = array();

		foreach ( $corrections as $correction ) {
			if ( ! $this->sitepress->is_translated_taxonomy( $correction->taxonomy ) ) {
				continue;
			}
			if ( ! isset( $changes[ $correction->taxonomy ] ) ) {
				$changes[ $correction->ID ][ $correction->taxonomy ] = array();
			}
			$changes[ $correction->ID ][ $correction->taxonomy ][] = (int) $correction->term_id;
		}
		foreach ( $changes as $post_id => $tax_changes ) {
			foreach ( $tax_changes as $taxonomy => $term_ids ) {
				remove_action(
					'set_object_terms',
					array( 'WPML_Terms_Translations', 'set_object_terms_action' ),
					10
				);
				add_filter( 'wpml_disable_term_adjust_id', '__return_true' );
				$this->sitepress->get_wp_api()->wp_set_object_terms( $post_id, $term_ids, $taxonomy, true );
				remove_filter( 'wpml_disable_term_adjust_id', '__return_true' );
				add_action(
					'set_object_terms',
					array( 'WPML_Terms_Translations', 'set_object_terms_action' ),
					10,
					6
				);
			}
		}
	}

	private function get_term_lang( $tt_id, $post_action, $taxonomy ) {
		$term_lang = filter_input(
			INPUT_POST,
			'icl_tax_' . $taxonomy . '_language',
			FILTER_SANITIZE_FULL_SPECIAL_CHARS
		);
		$term_lang = $term_lang ? $term_lang : $this->get_term_lang_ajax( $taxonomy, $post_action );
		$term_lang = $term_lang ? $term_lang : $this->get_lang_from_post( $post_action, $tt_id );

		$term_lang = $term_lang ? $term_lang : $this->sitepress->get_current_language();
		$term_lang = apply_filters( 'wpml_create_term_lang', $term_lang );
		$term_lang = \WPML\Language\ActiveLanguagesReadModel::canonical( (string) $term_lang );
		$term_lang = $this->sitepress->is_active_language( $term_lang ) ? $term_lang
			: $this->sitepress->get_default_language();

		return $term_lang;
	}

	private function get_lang_from_post( $post_action, $tt_id ) {
		$icl_post_lang = filter_input( INPUT_POST, 'icl_post_language' );
		$term_lang     = $post_action === 'editpost' && $icl_post_lang ? $icl_post_lang : null;
		$term_lang     = $post_action === 'post-quickpress-publish' ? $this->sitepress->get_default_language()
			: $term_lang;
		$term_lang     = ! $term_lang && $post_action === 'inline-save-tax' || $post_action === 'editedtag'
			? $this->term_translations->get_element_lang_code( $tt_id ) : $term_lang;
		$term_lang     = ! $term_lang && $post_action === 'inline-save'
			? $this->post_translations->get_element_lang_code(
				filter_input( INPUT_POST, 'post_ID', FILTER_SANITIZE_NUMBER_INT )
			) : $term_lang;

		return $term_lang;
	}

	public function get_term_lang_ajax( $taxonomy, $post_action ) {
		if ( isset( $_POST['_ajax_nonce'] ) && filter_var( $_POST['_ajax_nonce'] ) !== false
			 && $post_action === 'add-' . $taxonomy
		) {
			$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
			parse_str( (string) wpml_parse_url( $referrer, PHP_URL_QUERY ), $qvars );
			$term_lang = ! empty( $qvars['post'] ) && $this->sitepress->is_translated_post_type(
				get_post_type( (int) $qvars['post'] )
			)
				? $this->post_translations->get_element_lang_code( $qvars['post'] )
				: ( isset( $qvars['lang'] ) ? $qvars['lang'] : null );
		}

		return isset( $term_lang ) ? $term_lang : null;
	}

	private function get_saved_term_trid( $tt_id, $post_action ) {
		if ( $post_action === 'editpost' ) {
			$trid = $this->term_translations->get_element_trid( $tt_id );
		} elseif ( $post_action === 'editedtag' ) {
			$translation_of = filter_input( INPUT_POST, 'icl_translation_of', FILTER_VALIDATE_INT );
			$translation_of = $translation_of ? $translation_of : filter_input( INPUT_POST, 'icl_translation_of' );

			$trid           = $translation_of === 'none' ? false
				: ( $translation_of
					? $this->term_translations->get_element_trid( $translation_of )
					: $trid = filter_input( INPUT_POST, 'icl_trid', FILTER_SANITIZE_NUMBER_INT )
				);
		} else {
			$trid = filter_input( INPUT_POST, 'icl_trid', FILTER_SANITIZE_NUMBER_INT );
			$trid = $trid
				? $trid
				: $this->term_translations->get_element_trid(
					filter_input( INPUT_POST, 'icl_translation_of', FILTER_VALIDATE_INT )
				);
			$trid = $trid ? $trid : $this->term_translations->get_element_trid( $tt_id );
		}

		return $trid;
	}

	private function delete_translations( $term_taxonomy_id, $taxonomy, array $translations ) {
		$has_filter = remove_filter( 'get_term', array( $this->sitepress, 'get_term_adjust_id' ), 1 );
		$default_categories = array_map( 'intval', (array) $this->sitepress->get_setting( 'default_categories', array() ) );
		foreach ( $translations as $translation ) {
			if ( (int) $translation->element_id !== (int) $term_taxonomy_id ) {
				if ( in_array( (int) $translation->element_id, $default_categories, true ) ) {
					continue;
				}
				if ( 'nav_menu' === $taxonomy ) {
					$this->delete_items_detached_from_menu( (int) $translation->element_id );
					wp_delete_nav_menu( $translation->term_id );
				} else {
					wp_delete_term( $translation->term_id, $taxonomy );
				}
			}
		}
		if ( $has_filter ) {
			add_filter( 'get_term', array( $this->sitepress, 'get_term_adjust_id' ), 1, 1 );
		}
	}

	private function delete_items_detached_from_menu( $menu_tt_id ) {
		if ( empty( $this->items_detached_from_menu[ $menu_tt_id ] ) ) {
			return;
		}

		$item_ids = array_unique( $this->items_detached_from_menu[ $menu_tt_id ] );
		unset( $this->items_detached_from_menu[ $menu_tt_id ] );

		foreach ( $item_ids as $item_id ) {
			if ( 'nav_menu_item' === get_post_type( $item_id ) ) {
				wp_delete_post( $item_id, true );
			}
		}
	}

	private function hook_detached_items_cleanup() {
		if ( ! $this->detached_items_cleanup_hooked ) {
			$this->detached_items_cleanup_hooked = true;
			add_action( 'shutdown', array( $this, 'delete_items_left_outside_every_menu' ), 0 );
		}
	}

	public function delete_items_left_outside_every_menu() {
		$notes                          = $this->items_detached_from_menu;
		$this->items_detached_from_menu = array();

		foreach ( $notes as $item_ids ) {
			foreach ( array_unique( $item_ids ) as $item_id ) {
				if ( 'nav_menu_item' === get_post_type( $item_id ) && ! $this->is_in_a_menu( $item_id ) ) {
					$this->move_children_up( $item_id );
					wp_delete_post( $item_id, true );
				}
			}
		}
	}

	private function move_children_up( $item_id ) {
		$parent   = (string) (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true );
		$children = get_posts(
			array(
				'post_type'        => 'nav_menu_item',
				'post_status'      => 'any',
				'meta_key'         => '_menu_item_menu_item_parent',
				'meta_value'       => (string) $item_id,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);

		foreach ( $children as $child_id ) {
			update_post_meta( (int) $child_id, '_menu_item_menu_item_parent', $parent );
		}
	}

	private function is_in_a_menu( $item_id ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tr.object_id FROM {$wpdb->term_relationships} tr
				 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'nav_menu'
				 WHERE tr.object_id = %d LIMIT 1",
				$item_id
			)
		);
	}

	private function restore_items_detached_from_translated_menus( $trid, $element_type, $term_taxonomy_id ) {
		if ( ! $this->items_detached_from_menu || 'tax_nav_menu' !== $element_type ) {
			return;
		}

		$restoredByLanguage = array();
		$translations       = $this->sitepress->get_element_translations( $trid, $element_type );
		foreach ( (array) $translations as $translation ) {
			if ( ! isset( $translation->element_id ) || (int) $translation->element_id === (int) $term_taxonomy_id ) {
				continue;
			}

			if ( $this->restore_items_detached_from_menu( (int) $translation->element_id ) && isset( $translation->language_code ) ) {
				$restoredByLanguage[ $translation->language_code ][] = (int) $translation->element_id;
			}
		}

		$this->update_term_counts_in_languages( $restoredByLanguage, 'nav_menu' );
	}

	private function restore_items_detached_from_menu( $menu_tt_id ) {
		if ( empty( $this->items_detached_from_menu[ $menu_tt_id ] ) ) {
			return false;
		}

		$item_ids = array_unique( $this->items_detached_from_menu[ $menu_tt_id ] );
		unset( $this->items_detached_from_menu[ $menu_tt_id ] );

		foreach ( $item_ids as $item_id ) {
			$this->wpdb->insert(
				$this->wpdb->term_relationships,
				array(
					'object_id'        => (int) $item_id,
					'term_taxonomy_id' => (int) $menu_tt_id,
					'term_order'       => 0,
				)
			);
		}

		return true;
	}
}
