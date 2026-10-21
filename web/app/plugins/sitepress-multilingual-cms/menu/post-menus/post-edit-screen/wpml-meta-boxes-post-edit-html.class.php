<?php

use WPML\TranslationRoles\LangPairPermissions;

class WPML_Meta_Boxes_Post_Edit_HTML {

	const FLAG_HAS_MEDIA_OPTIONS = 'wpml_has_media_options';
	const WRAPPER_ID = 'icl_div';

	const MINOR_EDIT_TRACE_LIMIT = 8;

	private $sitepress;
	private $post_translation;
	private $translation_of_options;
	private $allowed_languages;
	private $can_translate_post;
	private $is_original;
	private $post;
	private $post_type_label;
	private $selected_language;
	private $source_language;
	private $translations;
	private $trid;
	private $lang_pair_permissions;

	function __construct(
		SitePress $sitepress,
		WPML_Post_Translation $post_translation,
		?LangPairPermissions $lang_pair_permissions = null
	) {
		$this->sitepress             = $sitepress;
		$this->post_translation      = $post_translation;
		$this->lang_pair_permissions = $lang_pair_permissions ?: new LangPairPermissions();
	}

	public function render_languages( $post = null ) {
		if ( ! $post || ! is_post_type_translated( $post->post_type ) ) {
			return;
		}
		wp_enqueue_script( OTGS_Assets_Handles::POPOVER_TOOLTIP );
		wp_enqueue_style( OTGS_Assets_Handles::POPOVER_TOOLTIP );

		$this->set_post( $post );
		$this->init_post_data();
		$this->post_edit_languages_duplicate_of();

		ob_start();
		$this->post_edit_languages_dropdown();
		$this->connect_translations();
		$this->translation_of();
		$this->languages_actions();
		$this->copy_from_original( $post );

		if ( $this->sitepress->is_translated_post_type( 'attachment' ) ) {
			$this->media_options( $post );
		}

		$this->minor_edit();

		do_action( 'icl_post_languages_options_after' );
		$contents = ob_get_clean();

		echo $this->is_a_duplicate() ? '<span style="display:none">' . $contents . '</span>' : $contents;
	}

	private function post_edit_languages_duplicate_of() {
		$duplicate_original_id = $this->is_a_duplicate();
		if ( is_numeric( $duplicate_original_id ) ) {
			?>
			<div class="icl_cyan_box"><?php
				/* translators: Notice at the top of the editing screen of a post that WPML keeps as a copy of another one. %s: a link, already wrapped in its tags, whose text is the title of the original post. */
				printf( esc_html__( 'This document is a duplicate of %s and it is maintained by WPML.', 'sitepress' ), '<a href="' . esc_url( get_edit_post_link( $duplicate_original_id ) ) . '">' . esc_html( get_the_title( $duplicate_original_id ) ) . '</a>' );
				?>
				<?php /* translators: Button label on the editing screen of a post that WPML keeps as a copy: stop keeping it as a copy and translate it on its own. Verb, imperative. */ ?>
				<p><input id="icl_translate_independent" class="button-secondary" type="button" value="<?php esc_html_e( 'Translate independently', 'sitepress' ) ?>"/></p>
				<?php wp_nonce_field( 'reset_duplication_nonce', '_icl_nonce_rd' ) ?>
			</div>
		<?php
		}
	}

	private function post_edit_languages_dropdown() {
		?>
		<div id="icl_document_language_dropdown" class="icl_box_paragraph"
		     data-metabox-refresh-nonce="<?php echo wp_create_nonce( WPML_Meta_Boxes_Post_Edit_Ajax::ACTION_GET_META_BOXES ) ?>"
		     data-admin-ls-refresh-nonce="<?php echo wp_create_nonce( WPML_Meta_Boxes_Post_Edit_Ajax::ACTION_GET_ADMIN_LS ) ?>">

			<?php
			$disabled_language = disabled( false, $this->can_translate_post, false );
			wp_nonce_field( WPML_Post_Edit_Ajax::AJAX_ACTION_SWITCH_POST_LANGUAGE, 'nonce' );
			?>
			<select name="icl_post_language" id="icl_post_language" <?php echo $disabled_language; ?>>
				<?php
				foreach ( $this->get_language_options() as $code => $option ) {
					?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( true, $option['selected'], true ); ?>>
						<?php echo esc_html( $option['label'] ); ?>
					</option>
					<?php
				}
				?>
			</select>
			<input type="hidden" name="icl_trid" value="<?php echo esc_attr( (string) $this->get_trid() ); ?>"/>
		</div>
		<?php
	}

	private function get_language_options() {
		$active_langs = $this->sitepress->get_active_languages();
		$active_langs = apply_filters( 'wpml_active_languages_access', $active_langs, array( 'action' => 'edit' ) );
		$translations = $this->get_translations();
		$options      = array();

		$recorded = $this->post->ID ? $this->post_translation->get_element_lang_code( $this->post->ID ) : null;
		if ( $recorded && $recorded === $this->selected_language && ! isset( $active_langs[ $recorded ] ) ) {
			$details              = $this->sitepress->get_language_details( $recorded );
			$options[ $recorded ] = array(
				/* translators: %s: language name, e.g. "French". */
				'label'    => sprintf( __( '%s (inactive)', 'sitepress' ), isset( $details['display_name'] ) ? $details['display_name'] : $recorded ),
				'selected' => true,
			);
		}

		foreach ( $active_langs as $code => $lang ) {
			if ( ( $code != $this->selected_language && ! in_array( $code, $this->allowed_languages ) )
			     || ( isset( $translations[ $code ] ) && $translations[ $code ] != $this->post->ID )
			) {
				continue;
			}
			$options[ $code ] = array(
				'label'    => $lang['display_name'],
				'selected' => $recorded ? $code === $this->selected_language : $this->is_selected_lang( $code, $this->selected_language ),
			);
		}

		return $options;
	}

	private function connect_translations() {
		if ( 'auto-draft' !== $this->post->post_status ) {
			$trid             = $this->get_trid();
			$current_language = $this->sitepress->get_current_language();
			if ( count( $this->get_translations() ) === 1 && $this->sitepress->has_orphan_translations( $this->post->post_type, $current_language ) ) {
				$args                  = array();
				$args['language_code'] = $this->selected_language;
				$args['display_code']  = $this->sitepress->get_default_language();
				$language_name         = apply_filters( 'wpml_display_single_language_name', null, $args );
				$post_type			   = get_post_type( $this->post );
				$post_type_object	   = $post_type ? get_post_type_object( $post_type ) : false;

				if ( ! $post_type_object ) {
					return;
				}
				?>
                <div id="icl_document_connect_translations_dropdown" class="icl_box_paragraph">
                    <p>
                        <a class="js-set-post-as-source" href="#">
							<?php esc_html_e( 'Connect with translations', 'sitepress' ); ?>
                        </a>
                    </p>
                    <input type="hidden" id="icl_connect_translations_post_id" name="icl_connect_translations_post_id"
                           value="<?php echo esc_attr( (string) $this->post->ID ); ?>"/>
                    <input type="hidden" id="icl_connect_translations_trid" name="icl_connect_translations_trid"
                           value="<?php echo esc_attr( (string) $trid ); ?>"/>
                    <input type="hidden" id="icl_connect_translations_post_type"
                           name="icl_connect_translations_post_type" value="<?php echo esc_attr( (string) $this->post->post_type ); ?>"/>
                    <input type="hidden"
                           id="icl_connect_translations_language"
                           name="icl_connect_translations_language"
                           value="<?php echo esc_attr( (string) $current_language ); ?>"/>
                    <?php wp_nonce_field( 'get_orphan_posts_nonce', '_icl_nonce_get_orphan_posts' ); ?>
                </div>

                <div class="hidden">
                    <div id="connect_translations_dialog"
                         title="<?php esc_attr_e( sprintf( 'Choose a %s to assign', $post_type_object->labels->singular_name ), 'sitepress' ); ?>"
                         data-set_as_source-text="<?php echo esc_attr( sprintf( /* translators: Button label in the dialog that connects a post to its translations. %1$s: the name of a language, %2$s: the name of the content type, for example post or page. */ __( 'Make %1$s the original language for this %2$s', 'sitepress' ), $language_name, $this->post->post_type ) ); ?>"
                         data-alert-text="<?php esc_attr_e( "Please make sure to save your post, if you've made any change, before proceeding with this action!", 'sitepress' ); ?>"
                         data-cancel-label="<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */ esc_attr_e( 'Cancel', 'sitepress' ); ?>"
                         data-ok-label="<?php /* translators: Button label that confirms a dialog, and the answer sent back when a request went through. */ esc_attr_e( 'Ok', 'sitepress' ); ?>">
                        <p class="js-ajax-loader ajax-loader">
							<?php /* translators: Text shown while the dialog on the post editing screen is waiting for data; three dots follow it. */ esc_html_e( 'Loading', 'sitepress' ); ?>&hellip; <span class="spinner"></span>
                        </p>

                        <div class="posts-found js-posts-found">
                            <label id="post-label" for="post_search">
								<?php esc_html_e( 'Type a post title', 'sitepress' ); ?>: </label>
                            <input id="post_search" type="text">
                        </div>
                        <p class="js-no-posts-found no-posts-found"><?php esc_html_e( 'No posts found', 'sitepress' ) ?></p>
                        <input type="hidden" id="assign_to_trid">
                    </div>
                    <div id="connect_translations_dialog_confirm"
                         title="<?php esc_attr_e( 'Connect this post?', 'sitepress' ); ?>"
                         data-cancel-label="<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */ esc_attr_e( 'Cancel', 'sitepress' ); ?>"
                         data-assign-label="<?php /* translators: Button label in the dialog that connects a post to its translations: carry the connection out. Verb, imperative. */ esc_attr_e( 'Assign', 'sitepress' ); ?>"
                         data-connect-failed-text="<?php /* translators: Shown in the "Connect with translations" dialog on the post editing screen when connecting the post failed and the server gave no reason. */ esc_attr_e( 'This post was not connected. Reload the page and try again.', 'sitepress' ); ?>">
                        <p>
                            <span class="ui-icon ui-icon-alert"></span> <?php esc_html_e( 'You are about to connect the current post with these following posts', 'sitepress' ); ?>
                            : </p>
                        <div id="connect_translations_dialog_confirm_list">
                            <p class="js-ajax-loader ajax-loader">
	                            <?php /* translators: Text shown while the dialog on the post editing screen is waiting for data; three dots follow it. */ esc_html_e( 'Loading', 'sitepress' ); ?>&hellip; <span class="spinner"></span>
                            </p>
                        </div>
                        <p class="js-connect-translations-error icl_error_text" role="alert" hidden></p>
                        <?php wp_nonce_field( 'get_posts_from_trid_nonce', '_icl_nonce_get_posts_from_trid' ); ?>
						<?php wp_nonce_field( 'connect_translations_nonce', '_icl_nonce_connect_translations' ); ?>
                    </div>
                </div>
				<?php
			}
		}
	}

	private function translation_of() {
		?>
		<div id="translation_of_wrap">
			<?php
			if ( 'auto-draft' !== $this->post->post_status && ( $this->is_a_translation() || ! $this->has_translations() ) ) {
				$disabled = disabled( false, $this->is_edit_action() && $this->get_trid(), false );
				?>

				<div id="icl_translation_of_panel" class="icl_box_paragraph">
					<label for="icl_translation_of"><?php /* translators: Label in front of the dropdown that picks the original post this one is a translation of, on the post editing screen. The name of the original post follows it, so it ends without a full stop. */ esc_html_e( 'This is a translation of', 'sitepress' ); ?></label>&nbsp;
					<select name="icl_translation_of" id="icl_translation_of" <?php echo $disabled; ?>>
						<?php
						$this->render_translation_of_options();
						?>
					</select>
					<?php  ?>
					<?php
					$trid = $this->get_trid();
					$source_element_id = $trid ? SitePress::get_original_element_id_by_trid( $trid ) : false;
					if ( $disabled && ! empty( $source_element_id ) ) {
						?>
						<input type="hidden" name="icl_translation_of" id="icl_translation_of_hidden" value="<?php echo esc_attr( (string) $source_element_id ); ?>">
					<?php
					}
					?>
				</div>
			<?php
			}
			?>
		</div><!--//translation_of_wrap--><?php  ?>

		<br clear="all"/>
	<?php
	}

	private function minor_edit() {
		if ( $this->get_minor_edit_backtrace()->is_function_in_call_stack( 'the_block_editor_meta_boxes' ) ) {
			do_action( 'wpml_minor_edit_for_gutenberg' );
		}
	}

	private function get_minor_edit_backtrace() {
		return new WPML\Utils\DebugBackTrace( self::MINOR_EDIT_TRACE_LIMIT );
	}

	private function languages_actions() {
		$status_display = new WPML_Post_Status_Display( $this->sitepress->get_active_languages() );

		if ( $this->can_translate() ) {

			do_action( 'icl_post_languages_options_before', $this->post->ID );

			list( $translated_posts, $untranslated_posts ) = $this->count_untranslated_posts();
			?>

			<div id="icl_translate_options">
				<?php
				if ( $untranslated_posts ) {
					$this->languages_table( $status_display );
				}

				if ( $translated_posts > 0 ) {
					$this->translation_summary( $status_display );
				}
				?>

			</div>
		<?php
		}
	}

	private function is_a_translation() {
		return ! $this->is_original && ( $this->selected_language != $this->source_language || ( isset( $_GET[ 'lang' ] ) && $this->source_language !== $_GET[ 'lang' ] ) ) && 'all' !== $this->sitepress->get_current_language();
	}

	private function has_translations() {
		return (bool) $this->get_translations();
	}

	private function render_translation_of_options() {
		$this->init_translation_of_options();
		foreach ( $this->translation_of_options as $option_value => $option_data ) {
			echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $option_value ), selected( true, $option_data[ 'selected' ], false ), esc_html( $option_data[ 'label' ] ) );
		}
	}

	private function can_translate() {
		$trid = $this->get_trid();
		$can_translate_args = array(
			'trid'         => $trid,
			'translations' => $this->get_translations(),
		);

		$can_translate_aaa = $trid && ( $this->is_edit_action() || apply_filters( 'wpml_post_edit_can_translate', false, $can_translate_args ) );

		return $can_translate_aaa;
	}

	private function count_untranslated_posts() {
		$untranslated_found = 0;
		$translations_found = 0;
		$active_langs       = $this->sitepress->get_active_languages();
		$translations       = $this->get_translations();
		foreach ( $active_langs as $lang ) {
			if ( $this->selected_language == $lang['code'] ) {
				continue;
			}
			if ( isset( $translations[ $lang['code'] ] ) ) {
				$translations_found += 1;
			} else {
				$untranslated_found += 1;
			}
		}

		return array( $translations_found, $untranslated_found );
	}

	private function languages_table( $status_display ) {
		?>
		<p style="clear:both;"><b><?php esc_html_e( 'Translate this Document', 'sitepress' ); ?></b></p>

		<?php
		do_action( 'wpml_before_post_edit_translations_table', $this->post );
		?>

		<table width="100%" id="icl_untranslated_table" class="icl_translations_table">
			<tr>
				<th>&nbsp;</th>
				<?php /* translators: Option in the dropdown that says what happens to a field, and the heading of the column of the post editing screen where a translation is started: the text is translated. Verb, imperative. */ ?>
				<th align="right"><?php esc_html_e( 'Translate', 'sitepress' ) ?></th>
				<th align="right" width="10" style="padding-left:8px;"><?php echo /* translators: Column heading and button label on the post editing screen: make a copy of this post in another language. Verb, imperative. */ esc_html_x( 'Duplicate', 'button: make a copy', 'sitepress' ); ?></th>
			</tr>
			<?php
			$active_langs = $this->sitepress->get_active_languages();
			$active_langs = apply_filters( 'wpml_active_languages_access', $active_langs, array( 'action' => 'edit', 'post_type' => $this->post_type_label, 'post_id' => $this->post->ID ) );
			$active_langs = array_intersect_key(
				$active_langs,
				array_flip(
					\WPML\LanguageEditor\TranslationPause::filterTranslatable(
						array_map( 'strval', array_keys( $active_langs ) )
					)
				)
			);
			foreach ( $active_langs as $lang ) {
				$this->translate_option( $lang, $status_display );
			}
			?>
			<tr>
				<td colspan="3" align="right">
					<input
							id="icl_make_duplicates"
							type="button"
							class="button-secondary"
							value="<?php echo /* translators: Column heading and button label on the post editing screen: make a copy of this post in another language. Verb, imperative. */ esc_attr_x( 'Duplicate', 'button: make a copy', 'sitepress' ); ?>"
							disabled="disabled"
							style="display:none;"
							data-action="<?php echo WPML_Meta_Boxes_Post_Edit_Ajax::ACTION_DUPLICATE; ?>"
        					data-nonce="<?php echo wp_create_nonce( WPML_Meta_Boxes_Post_Edit_Ajax::ACTION_DUPLICATE ); ?>"
        					data-post-id="<?php echo isset( $this->post->ID ) ? $this->post->ID : 0; ?>"
					/>
				</td>
			</tr>
		</table>
		<?php
		if (
			'draft' === $this->post->post_status
			&& ! \WPML\Setup\Option::getTranslateEverythingDrafts()
			&& \WPML\Setup\Option::getTranslateEverything()
			&& \WPML\Setup\Option::getHasTranslateEverythingBeenEverUsed()
		) {
			echo '<div class="wpml-info-small wpml-spacing-top">';
			printf(
				/* translators: Link on the post editing screen that opens the setting for translating drafts automatically. %1$s: the opening tag of the link, %2$s: its closing tag. */
				esc_html__( '%1$sEnable automatic translation for drafts%2$s', 'sitepress' ),
				'<a href="' . esc_url( admin_url( \WPML\UIPage::getSettings() . '&section=ai-translation#translate-everything-drafts' ) ) . '">',
				'</a>'
			);
			echo '</div>';
		}
	}

	private function translation_summary( $status_display ) {
		$dupes          = $this->sitepress->get_duplicates( $this->post->ID );
		$not_show_flags = ! apply_filters( 'wpml_setting', true, 'show_translations_flag' );
		?>
        <div class="icl_box_paragraph">
            <?php /* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */ ?>
            <p id="icl_translated_title"><b><?php esc_html_e( 'Translations', 'sitepress' ) ?></b>
          (<a class="icl_toggle_show_translations" href="#" <?php if ( $not_show_flags ) : ?>style="display:none;"<?php endif; ?>><?php /* translators: Link text on the post editing screen that folds the list of translations away. It sits in brackets after the list and starts in lower case. Verb, imperative. */ esc_html_e( 'hide', 'sitepress' ); ?></a><a class="icl_toggle_show_translations" href="#" <?php if ( ! $not_show_flags ) : ?>style="display:none;"<?php endif; ?>><?php /* translators: Link text on the post editing screen that unfolds the list of translations. It sits in brackets after the list and starts in lower case. Verb, imperative. */ esc_html_e( 'show', 'sitepress' ); ?></a>)
            </p>

			<?php
			 do_action( 'wpml_before_post_edit_translations_summary', $this->post );

			 wp_nonce_field( 'toggle_show_translations_nonce', '_icl_nonce_tst' );
			 ?>

            <table width="100%" class="icl_translations_table wpml-margin-bottom-base" id="icl_translations_table"
			       <?php
			       if ( $not_show_flags ) : ?>style="display:none;"<?php endif; ?>>
				<?php
				$odd_even     = 1;
				$active_langs = $this->sitepress->get_active_languages();
				$translations = $this->get_translations();
				?>
				<?php foreach ( $active_langs as $lang ) :
					if ( $this->selected_language === $lang['code'] ) {
						continue;
					} ?>
                    <tr <?php if ( $odd_even < 0 ) : ?>class="icl_odd_row"<?php endif; ?>>
						<?php if ( isset( $translations[ $lang['code'] ] ) ) : ?>
							<?php $odd_even = $odd_even * - 1; ?>
                            <td style="padding-left: 4px;">
								<?php echo esc_html( $lang['display_name'] ); ?>
								<?php if ( isset( $dupes[ $lang['code'] ] ) ) {
									/* translators: Word shown in brackets after a language name in the translations table of the post editing screen, marking a translation that WPML keeps as a copy of the original. Noun. */
									echo ' (' . esc_html__( 'duplicate', 'sitepress' ) . ')';
								} ?>
                            </td>
                            <td align="right">
								<?php echo $status_display->get_status_html( $this->post->ID, $lang['code'] ); ?>
                            </td>

						<?php endif; ?>
                    </tr>
				<?php endforeach; ?>
            </table>
        </div>
		<?php
	}

	private function init_translation_of_options() {
		$this->translation_of_options = array();
		$element_id                   = null;
		if ( $this->trid ) {
			$this->fix_source_language();
			$element_id = $this->post_translation->get_element_id( $this->source_language, $this->trid );
		}
		/* translators: First option in the dropdown that picks the original post this one is a translation of: no post chosen. The dashes keep it apart from the post titles. */
		$this->add_translation_of_option( 'none', __( '--None--', 'sitepress' ), false );
		if ( $element_id && ! isset( $_GET['icl_ajx'] ) ) {
			$element_title = $this->get_element_title( $element_id );
			$this->add_translation_of_option( $element_id, $element_title, true );
		}

		if ( $this->handle_as_original() && apply_filters( 'wpml_language_is_active', null, $this->selected_language )
		) {
			$untranslated = $this->get_untranslated_posts();
			foreach ( $untranslated as $translation_of_id => $translation_of_title ) {
				$this->add_translation_of_option( $translation_of_id, $translation_of_title, false );
			}
		}
	}

	private function translate_option( $lang, $status_display ) {

		static $row = 0;

		if ( $this->selected_language == $lang[ 'code' ] ) {
			return;
		}

		$row_class = 0 === $row % 2 ? 'class="icl_odd_row"' : '';
		?>
		<tr <?php echo $row_class; ?>>
			<?php
			$translations = $this->get_translations();
			if ( ! isset( $translations[ $lang['code'] ] ) ) {
				$row ++;
				?>
				<td style="padding-left: 4px;">
					<?php echo esc_html( $lang['display_name'] ); ?>
				</td>
				<td align="right">
					<?php echo $status_display->get_status_html( $this->post->ID, $lang[ 'code' ] ); ?>
				</td>
				<td align="right">
				    <?php
				    $disabled_duplication       = false;
				    /* translators: Name of the checkbox that makes a copy of the post in another language, used as its tooltip and, with the language name, as its screen reader name. Verb, imperative. */
				    $disabled_duplication_title = esc_attr__( 'Create duplicate', 'sitepress' );
				    $element_key                = array( 'trid' => $this->trid, 'language_code' => $lang['code'] );
				    $translation_status         = apply_filters( 'wpml_tm_translation_status', null, $element_key );
				    echo PHP_EOL . '<!-- $translation_status = ' . $translation_status . ' -->' . PHP_EOL;

				    if ( $translation_status && $translation_status < ICL_TM_COMPLETE ) {
				        $disabled_duplication       = true;
				        if ( ICL_TM_DUPLICATE === (int) $translation_status ) {
				            $disabled_duplication_title = esc_attr__( 'This post is already duplicated.', 'sitepress' );
				        } else {
				            $disabled_duplication_title = esc_attr__( "Can't create a duplicate. A translation is in progress.", 'sitepress' );
				        }
				    }

				    if ( ! $this->lang_pair_permissions->is_allowed( $this->selected_language, $lang['code'], $this->post->ID ) ) {
				        if ( ! $disabled_duplication ) {
				            $disabled_duplication_title = esc_attr__( "You don't have the rights to duplicate to this language.", 'sitepress' );
				        }
				        $disabled_duplication = true;
				    }

				    $duplication_label = sprintf(
				        /* translators: Screen reader label of a duplicate checkbox on the post editing screen, e.g. "Create duplicate for German". %1$s: what the checkbox does, or why it is disabled, %2$s: language name. */
				        __( '%1$s for %2$s', 'sitepress' ),
				        $disabled_duplication_title,
				        $lang['display_name']
				    );

				    ?>
				    <input
						<?php disabled( true, $disabled_duplication ); ?>
						type="checkbox"
						name="icl_dupes[]"
						value="<?php echo esc_attr( $lang['code'] ); ?>"
						title="<?php echo esc_attr( $disabled_duplication_title ); ?>"
						class="wpml-checkbox-native"
						aria-label="<?php echo esc_attr( $duplication_label ); ?>"
					/>
				</td>

			<?php
			}
			?>
		</tr>
	<?php
	}

	private function handle_as_original() {

		return $this->is_original || ! $this->source_language || $this->source_language === $this->selected_language;
	}

	private function add_translation_of_option( $value, $label, $selected ) {
		if ( ! isset( $this->translation_of_options[ $value ] ) ) {
			if ( trim( $label ) == '' ) {
				$label = '{' . __( 'Post without a title', 'sitepress' ) . '}';
			}
			$this->translation_of_options[ $value ] = array( 'label' => $label, 'selected' => $selected );
		}
	}

	private function fix_source_language() {
		if ( ! $this->source_language ) {
			if ( $this->post ) {
				$this->source_language = $this->post_translation->get_source_lang_code( $this->post->ID );
			} else {
				$this->source_language = $this->sitepress->get_default_language();
			}
		}
	}

	private function is_edit_action() {
		return isset( $_GET['action'] ) && 'edit' === $_GET['action'];
	}

	private function is_selected_lang( $lang_code, $selected_language ) {

		return $lang_code === $selected_language
		       || ( ! $this->sitepress->is_active_language( $selected_language )
		            && $lang_code === $this->sitepress->get_default_language() );
	}

	private function copy_from_original( $post ) {
		$trid        = $this->get_trid();
		$source_lang = filter_var( isset( $_GET['source_lang'] ) ? $_GET['source_lang'] : '', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$source_lang = 'all' === $source_lang ? $this->sitepress->get_default_language() : $source_lang;
		$lang        = filter_var( isset( $_GET['lang'] ) ? $_GET['lang'] : '', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! $lang ) {
			$lang = $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );
		}

		$source_lang = ! $source_lang && isset( $_GET['post'] ) && $lang !== $this->sitepress->get_default_language()
				? $this->post_translation->get_source_lang_code( $post->ID ) : $source_lang;

		if ( $trid && $source_lang && $source_lang !== $lang ) {
			$_lang_details    = $this->sitepress->get_language_details( $source_lang );
			$source_lang_name = $_lang_details['display_name'];
			$this->display_copy_from_button( $source_lang, $source_lang_name, $post, $trid );

			if ( ! \WPML\LanguageEditor\TranslationPause::isPaused( $lang ) ) {
				$this->display_set_as_dupl_btn(
					$post,
					$source_lang_name,
					$this->post_translation->get_element_id( $source_lang, $trid ),
					$lang
				);
			}
		}
	}

	private function media_options( $post ) {
		if ( \WPML\Media\Option::shouldHandleMediaAuto() ) {
			return;
		}

		$original_post_id = (int) $this->post_translation->get_original_post_ID( $this->trid );

		$duplicate_media    = \WPML\Media\Option::shouldDuplicateMedia( $post->ID, false );
		$duplicate_featured = \WPML\Media\Option::shouldDuplicateFeatured( $post->ID, false );

		if( ! $original_post_id ){
			$duplicate_media = $duplicate_media ?? \WPML\FP\Obj::prop( 'duplicate_media', \WPML\Media\Option::getNewContentSettings() );
			$duplicate_featured  = $duplicate_featured ?? \WPML\FP\Obj::prop( 'duplicate_featured', \WPML\Media\Option::getNewContentSettings() );
		} else{
			$duplicate_media = $duplicate_media ?? \WPML\Media\Option::shouldDuplicateMedia( $original_post_id );
			$duplicate_featured  = $duplicate_featured ?? \WPML\Media\Option::shouldDuplicateFeatured( $original_post_id );
		}

		if( ! $original_post_id || (int) $post->ID === $original_post_id ){
			/* translators: Heading of the media options in the WPML panel of the post editing screen, on an original or a new post. Under it are two checkboxes: "Featured image" and "Media attachments". */
			$heading = __( 'Use the same media in translations', 'sitepress' );
			/* translators: Tooltip of the media options in the WPML panel of the post editing screen, on an original or a new post. The items are the "Featured image" and "Media attachments" checkboxes. %s: a link to the documentation, already wrapped in its tags, whose text is "Learn more about translating media with WPML". */
			$tooltip = esc_html__( 'Translations reuse the checked items from this content. Uncheck an item to use different media in translations. %s', 'sitepress' );
		} else {
			/* translators: Heading of the media options in the WPML panel of the post editing screen, on a translation. Under it are two checkboxes: "Featured image" and "Media attachments". */
			$heading = __( 'Use the same media as the original', 'sitepress' );
			/* translators: Tooltip of the media options in the WPML panel of the post editing screen, on a translation. The items are the "Featured image" and "Media attachments" checkboxes. %s: a link to the documentation, already wrapped in its tags, whose text is "Learn more about translating media with WPML". */
			$tooltip = esc_html__( 'This translation reuses the checked items from the original. Uncheck an item to use different media in this translation. %s', 'sitepress' );
		}

		$doc_url  = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/translating-your-contents/media/',
			[
				'medium'   => 'post-editor',
				'campaign' => 'media-translation',
			]
		);
		$doc_link = '<a href="' . esc_url( $doc_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn more about translating media with WPML', 'sitepress' ) . '</a>';

		echo '<br /><br /><strong class="wpml-media-options-heading">' . esc_html( $heading ) . '</strong> ';
		echo '<i class="otgs-ico-help js-otgs-popover-tooltip" data-tippy-zindex="999999" data-tippy-boundary="viewport" tabindex="0" role="img"' .
		     ' aria-label="' . esc_attr( trim( sprintf( $tooltip, '' ) ) ) . '"' .
		     ' title="' . esc_attr( sprintf( $tooltip, $doc_link ) ) . '"></i>';

		/* translators: Checkbox under the media options heading in the WPML panel of the post editing screen. Checked, the translations use the same featured image. */
		$duplicate_featured_label = __( 'Featured image', 'sitepress' );
		/* translators: Checkbox under the media options heading in the WPML panel of the post editing screen. Checked, the translations use the same images and files attached to the content. */
		$duplicate_media_label = __( 'Media attachments', 'sitepress' );

		echo '<br /><input name="' . self::FLAG_HAS_MEDIA_OPTIONS . '" type="hidden" value="1"/>';
		echo '<br /><label><input name="wpml_duplicate_featured" type="checkbox" value="1" ' .
		     checked( $duplicate_featured, true, false ) . '/>&nbsp;' . esc_html( $duplicate_featured_label ) . '</label>';
		echo '<br /><label><input name="wpml_duplicate_media" type="checkbox" value="1" ' .
		     checked( $duplicate_media, true, false ) . '/>&nbsp;' . esc_html( $duplicate_media_label ) . '</label>';

	}

	private function display_copy_from_button( $source_lang, $source_lang_name, $post, $trid ) {
		$disabled = trim( $post->post_content ) ? ' disabled="disabled"' : '';
		wp_nonce_field( 'copy_from_original_nonce', '_icl_nonce_cfo_' . $trid );
		echo '<input id="icl_cfo" class="button-secondary" type="button" value="' . sprintf(
		        /* translators: Button label on the post editing screen: bring the text of the original language into this translation. %s: the name of the original language. */
		        esc_html__( 'Copy content from %s', 'sitepress' ),
                esc_attr( $source_lang_name )
            ) . '"
				onclick="icl_copy_from_original(\'' . esc_js( $source_lang ) . '\', \'' . esc_js( (string) $trid ) . '\')"'
		     . $disabled . ' />'; ?>
		<i class="otgs-ico-help js-otgs-popover-tooltip"
		   data-tippy-zindex="999999"
		   <?php /* translators: Tooltip of the button that copies the text of the original language into this translation, on the post editing screen. "It" is that button. */ ?>
		   title="<?php echo  esc_html__("This operation copies the content from the original language onto this translation. It's meant for when you want to start with the original content, but keep translating in this language. This button is only enabled when there's no content in the editor.",'sitepress');?>"></i>
	<?php }

	private function display_set_as_dupl_btn( $post, $source_lang_name, $original_post_id, $post_lang ) {
		wp_nonce_field( 'set_duplication_nonce', '_icl_nonce_sd' );
		/* translators: Button label on the post editing screen: replace the text of this translation with the text of the original language. %s: the name of the original language. */
		$overwrite_label = sprintf( esc_html__( 'Overwrite with %s content.', 'sitepress' ), $source_lang_name );
		?>
		<input id="icl_set_duplicate" type="button" class="button-secondary"
		       value="<?php echo esc_attr( $overwrite_label ); ?>"
		       data-wpml_original_post_id="<?php echo absint( $original_post_id ); ?>"
		       data-post_lang="<?php echo esc_attr( $post_lang ); ?>"/>
		<span style="display: none;"><?php echo esc_js(
					sprintf(
							/* translators: Question asked before the text of a translation is replaced with the text of the original language. %1$s: the name of the content type, for example post or page, %2$s: the name of the original language. */
							__(
									'The current content of this %1$s will be permanently lost. WPML will copy the %2$s content and replace the current content.',
									'sitepress'
							),
							$post->post_type,
							esc_html( $source_lang_name )
					)
			); ?></span>
		<i class="otgs-ico-help js-otgs-popover-tooltip"
		   data-tippy-zindex="999999"
		   <?php /* translators: Tooltip of the button that keeps this translation the same as the original, on the post editing screen. "It" is that button. */ ?>
		   title="	  <?php echo esc_html__("This operation will synchronize this translation with the original language. When you edit the original, this translation will update immediately. It's meant when you want the content in this language to always be the same as the content in the original language.",'sitepress') ?>"></i>
		<?php
	}

	private function get_untranslated_posts() {
		$untranslated = array();
		if ( $this->selected_language != $this->sitepress->get_default_language() ) {
			$args['element_type']    = 'post_' . $this->post->post_type;
			$args['target_language'] = $this->selected_language;
			$args['source_language'] = $this->sitepress->get_default_language();
			$untranslated_ids        = apply_filters( 'wpml_elements_without_translations', null, $args );
			foreach ( $untranslated_ids as $id ) {
				$untranslated[ $id ] = get_the_title( $id );
			}
		}

		return $untranslated;
	}


	private function get_translations() {

		return $this->post_translation->get_element_translations( false, $this->get_trid() );
	}

	private function get_trid() {
		$post_id     = isset( $this->post->ID ) ? $this->post->ID : 0;
		$post_status = isset( $this->post->post_status ) ? $this->post->post_status : '';

		return $this->post_translation->get_save_post_trid( $post_id, $post_status );
	}

	private function get_element_title( $source_element_id ) {
		$element_title = '';
		if ( $source_element_id && $source_element_id != $this->post->ID ) {
			$element_title = get_the_title( $source_element_id );
			if ( trim( $element_title ) === '' ) {
				$element_title = '{' . esc_html__( 'Post without a title', 'sitepress' ) . '}';
			}
		}

		return $element_title;
	}

	private function init_post_data() {
		global $wp_post_types;

		$this->init_trid_and_selected_language();
		$this->init_source_element_data();
		$this->remember_pending_translation_group();

		global $icl_meta_box_globals;
		$icl_meta_box_globals = array(
				'active_languages'  => $this->sitepress->get_active_languages(),
				'translations'      => $this->get_translations(),
				'selected_language' => $this->selected_language
		);

		$this->post_type_label = wpml_mb_strtolower( $wp_post_types[ $this->post->post_type ]->labels->singular_name
		                                             != "" ? $wp_post_types[ $this->post->post_type ]->labels->singular_name : $wp_post_types[ $this->post->post_type ]->labels->name );
	}

	private function is_a_duplicate() {

		return get_post_meta( $this->post->ID, '_icl_lang_duplicate_of', true );
	}

	private function set_post( &$post ) {
		$this->allowed_languages  = $this->get_allowed_target_langs( $post );
		$this->can_translate_post = ! empty( $this->allowed_languages );
		$this->selected_language  = null;
		$this->source_language    = null;
		$this->post_type_label    = null;
		$this->trid               = null;
		$this->translations       = array();
		$this->is_original        = false;
		$this->post               = $post;
	}

	private function get_allowed_target_langs( $post ) {
		$active_languages = $this->sitepress->get_active_languages();
		$can_translate    = array_keys( $active_languages );
		$can_translate    = array_diff(
			$can_translate,
			array( $this->post_translation->get_element_lang_code( $post->ID ) )
		);

		return apply_filters( 'wpml_allowed_target_langs', $can_translate, $post->ID, 'post' );
	}

	private function remember_pending_translation_group() {
		if (
			empty( $this->post->ID )
			|| 'auto-draft' !== $this->post->post_status
			|| ! $this->trid
			|| ! $this->source_language
			|| (int) $this->post->post_author !== (int) get_current_user_id()
		) {
			return;
		}

		( new \WPML\Infrastructure\WordPress\Component\Translation\Application\Repository\PendingTranslationGroupRepository() )->remember(
			(int) $this->post->ID,
			new \WPML\Core\Component\Translation\Domain\PendingTranslationGroup( (int) $this->trid, (string) $this->source_language )
		);
	}

	private function init_trid_and_selected_language() {
		$current_lang            = $this->sitepress->get_current_language();
		$this->selected_language = $current_lang;
		if ( $this->post->ID && $this->post->post_status !== 'auto-draft' ) {
			$this->trid = $this->post_translation->get_element_trid( $this->post->ID );
			if ( $this->trid ) {
				$this->selected_language = $this->post_translation->get_element_lang_code( $this->post->ID );
			} else {
				$this->sitepress->set_element_language_details( $this->post->ID,
				                                                'post_' . $this->post->post_type,
				                                                null,
				                                                $current_lang );
				$this->trid              = $this->post_translation->get_element_trid( $this->post->ID );
				$this->selected_language = $current_lang;
			}
		} else {
			$this->trid              = isset( $_GET['trid'] ) ? intval( $_GET['trid'] ) : false;
			$this->selected_language = isset( $_GET['lang'] ) ? strip_tags( $_GET['lang'] ) : $current_lang;
			$this->selected_language = $this->resolve_requested_language( $this->selected_language, $current_lang );
		}
		$before_url = $this->selected_language;
		if ( isset( $_GET['lang'] ) ) {
			$this->selected_language = strip_tags( $_GET['lang'] );
			$this->selected_language = $this->resolve_requested_language( $this->selected_language, $before_url );
		}
	}

	private function resolve_requested_language( $requested, $fallback ) {
		$resolved = \WPML\Language\ActiveLanguagesReadModel::canonical( (string) $requested );

		return array_key_exists( $resolved, \WPML\Language\ActiveLanguagesReadModel::rows() ) ? $resolved : (string) $fallback;
	}

	private function init_source_element_data() {
		$this->source_language = isset( $_GET['source_lang'] ) ? filter_var( $_GET['source_lang'],
		                                                                     FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : false;
		$this->is_original     = false;
		if ( ! $this->source_language ) {
			$translations = $this->get_translations();
			if ( isset( $translations[ $this->selected_language ] ) ) {
				$selected_content_translation = $translations[ $this->selected_language ];
				$this->is_original            = (bool) $this->post_translation->get_source_lang_code( $selected_content_translation ) === false;
				if ( ! $this->is_original ) {
					$selected_content_language_details = $this->sitepress->get_element_translations( $selected_content_translation,
					                                                                                 'post_' . $this->post->post_type );
					if (isset( $selected_content_language_details->source_language_code ) ) {
						$this->source_language = $selected_content_language_details->source_language_code;
					}
				}
			}
		}
	}
}
