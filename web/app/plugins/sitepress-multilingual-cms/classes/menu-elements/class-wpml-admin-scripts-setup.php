<?php
class WPML_Admin_Scripts_Setup extends WPML_Full_Translation_API {

	const PRIORITY_ENQUEUE_SCRIPTS = 10;

	private $page;

	public function __construct( &$wpdb, &$sitepress, &$post_translation, &$term_translation, $page ) {
		parent::__construct( $sitepress, $wpdb, $post_translation, $term_translation );
		$this->page = $page;
	}

	public static function print_dom_ready_call() {
		?>
(function (run) { if ('loading' === document.readyState) { document.addEventListener('DOMContentLoaded', run); } else { run(); } })(
		<?php
	}

	public function add_admin_hooks() {
		add_action( 'admin_print_scripts', array( $this, 'wpml_js_scripts_setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wpml_css_setup' ), self::PRIORITY_ENQUEUE_SCRIPTS );
	}

	public function register_styles() {
		wp_register_style( 'otgs-dialogs', ICL_PLUGIN_URL . '/res/css/otgs-dialogs.css', array( 'wp-jquery-ui-dialog' ), ICL_SITEPRESS_SCRIPT_VERSION );
		wp_register_style( 'wpml-dialog', ICL_PLUGIN_URL . '/res/css/dialog.css', array( 'otgs-dialogs' ), ICL_SITEPRESS_SCRIPT_VERSION );
		wp_register_style( 'wpml-wizard', ICL_PLUGIN_URL . '/res/css/wpml-wizard.css', [], ICL_SITEPRESS_SCRIPT_VERSION );
	}

	private function print_js_globals() {
		?>
        <script type="text/javascript">
            // <![CDATA[
            var
                <?php /* translators: Notice shown after a WPML setting is saved. */ ?>
                icl_ajx_saved = '<?php echo icl_js_escape( __( 'Data saved', 'sitepress' ) ); ?>',
                icl_ajx_error = '<?php echo icl_js_escape( __( 'Error: data not saved', 'sitepress' ) ); ?>',
                <?php /* translators: Notice on the language URL-format form when "A different domain per language" was not saved because some domains failed the validation. %s: the domains that failed, as a comma-separated list of "language: domain" pairs. */ ?>
                icl_ajx_domains_not_validated = <?php echo wp_json_encode( __( 'Not saved. These domains did not validate: %s', 'sitepress' ) ); ?>,
                <?php /* translators: Notice on the language URL-format form when "A different domain per language" was not saved because two languages were given the same domain. %s: that domain. */ ?>
                icl_ajx_domains_duplicate = <?php echo wp_json_encode( __( 'Not saved. Two languages point at the same domain: %s', 'sitepress' ) ); ?>,
                <?php /* translators: Notice on the language URL-format form when "A different domain per language" was not saved because a firewall or a CDN (content delivery network) blocked WPML's check of the domains. The domains themselves load. %s: the domains, as a comma-separated list. */ ?>
                icl_ajx_domains_blocked = <?php echo wp_json_encode( __( "Not saved. A firewall or CDN blocked WPML's check for: %s.", 'sitepress' ) ); ?>,
                <?php /* translators: Sentence after the notice "Not saved. A firewall or CDN blocked WPML's check for: ...". "Validate on save" is the label of the checkbox next to each domain on this form; use the same translation as that label. */ ?>
                icl_ajx_domains_blocked_hint = <?php echo wp_json_encode( __( 'If these domains show your site, untick "Validate on save" for them and save again.', 'sitepress' ) ); ?>,
                <?php /* translators: Word in brackets after the name of a language, saying that it is the language the site was built in. Adjective or noun, written in lower case, never a verb. */ ?>
                icl_default_mark = '<?php echo icl_js_escape( __( 'default', 'sitepress' ) ); ?>',
                icl_this_lang = '<?php echo esc_js( $this->sitepress->get_current_language() ); ?>',
                icl_ajxloaderimg_src = '<?php echo esc_url( ICL_PLUGIN_URL ); ?>/res/img/ajax-loader.gif',
                <?php /* translators: Line under the field where a category is added on the post editing screen. %s: the address of the categories screen, together with the href it sits in; it fills the opening tag of the link that is already in the text. */ ?>
                icl_cat_adder_msg = '<?php echo icl_js_escape( sprintf( __( 'To add categories that already exist in other languages go to the <a%s>category management page</a>', 'sitepress' ), ' href="' . admin_url( 'edit-tags.php?taxonomy=category' ) . '"' ) ); ?>',
				<?php /* translators: Button label in the WPML admin: pick a value. Verb, imperative. */ ?>
				icl_choose_text = '<?php echo icl_js_escape( __('Choose', 'sitepress') ); ?>',
				<?php /* translators: Value shown next to a setting in the WPML admin: it is turned on. Adjective, past participle. */ ?>
				icl_enabled_text = '<?php echo icl_js_escape( __('Enabled', 'sitepress') ); ?>';
            // ]]>

			<?php
			if ( ! $this->sitepress->get_setting( 'ajx_health_checked' ) && ! (bool) get_option( '_wpml_inactive' ) ) {
                /* translators: Error notice shown when WPML cannot work on this site. %1$s: the opening tag of the link that unfolds the details, %2$s: its closing tag. The words between the two are the text of that link. */
                $error = __( "WPML can't run normally. There is an installation or server configuration problem. %1\$sShow details%2\$s", 'sitepress' );
                $error_message = sprintf( $error, '<a href="#" onclick="jQuery(this).parent().next().slideToggle()">', '</a>' );
                ?>
                <?php self::print_dom_ready_call(); ?>function () {
                    jQuery.ajax({
                                    type : "POST",
                                    url  : ajaxurl,
                                    data : "action=wpml_health_check&_health_nonce=<?php echo esc_js( wp_create_nonce( 'wpml_health_check' ) ); ?>",
                                    error: function (msg) {
                                        // The request that failed: jQuery calls this handler with `this` set to the call's own settings.
                                        var failed_request_url = jQuery('<div/>').text(this.url + (this.url.indexOf('?') === -1 ? '?' : '&') + 'action=wpml_health_check').html();
                                        var icl_initial_language = jQuery('#icl_initial_language');
                                        if (icl_initial_language.length) {
                                            icl_initial_language.find('input').attr('disabled', 'disabled');
                                        }
                                        <?php /* translators: Label in front of the details of a failed request in the WPML admin. */ ?>
                                        jQuery('.wrap').prepend('<div class="error"><p>' + <?php echo wp_json_encode( $error_message ); ?> + '</p><p style="display:none">' + <?php echo wp_json_encode( __( 'AJAX Error:', 'sitepress' ) ); ?> + ' ' + msg.statusText + ' [' + msg.status + ']<br />URL:' + failed_request_url + '</p></div>');
                                    }
                                });
                });
                <?php
			}
			?>
        </script>
		<?php

	}

	public function wpml_js_scripts_setup() {
		global $pagenow, $sitepress;
		$default_language = $this->sitepress->get_default_language();
		$current_language = $this->sitepress->get_current_language();
		$page_basename    = $this->page;

		$this->print_js_globals();

		$wpml_script_setup_args['default_language'] = $default_language;
		$wpml_script_setup_args['current_language'] = $current_language;
		do_action( 'wpml_scripts_setup', $wpml_script_setup_args );

		if ( 'options-reading.php' === $pagenow ) {
			$this->print_reading_options_js();
		} elseif ( in_array(
			$pagenow,
			array(
				'categories.php',
				'edit-tags.php',
				'edit.php',
				'term.php',
			),
			true
		)
				   && $current_language !== $default_language
		) {
			$this->correct_status_links_js( $current_language );
		}

		if ( 'edit-tags.php' === $pagenow || 'term.php' === $pagenow ) {
			$post_type = isset( $_GET['post_type'] ) ? '&post_type=' . esc_html( $_GET['post_type'] ) : '';
			$admin_url = admin_url( 'edit-tags.php' );
			$admin_url = add_query_arg( 'taxonomy', esc_js( \WPML\SuperGlobals\Request::param( 'taxonomy' ) ), $admin_url );
			$admin_url = add_query_arg( 'lang', $current_language, $admin_url );
			$admin_url = add_query_arg( 'message', 3, $admin_url );
			if ( $post_type ) {
				$admin_url = add_query_arg( 'post_type', $post_type, $admin_url );
			}
			?>
			<script type="text/javascript">
				<?php self::print_dom_ready_call(); ?>function () {
					var edit_tag = jQuery('#edittag');
					if (edit_tag.find('[name="_wp_original_http_referer"]').length && edit_tag.find('[name="_wp_http_referer"]').length) {
						edit_tag.find('[name="_wp_original_http_referer"]').val('<?php echo esc_js( $admin_url ); ?>');
					}
				});
			</script>
			<?php
		}
		$trid        = filter_input( INPUT_GET, 'trid', FILTER_SANITIZE_NUMBER_INT );
		$source_lang = null !== $trid ? filter_input(
			INPUT_GET,
			'source_lang',
			FILTER_SANITIZE_FULL_SPECIAL_CHARS
		) : null;
		if ( $trid ) {
			$new_post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : 'post';
			if ( ! \WPML\Translation\ElementVisibility::currentUserCanReadGroupSource( $trid, $source_lang ? $source_lang : null, 'post_' . $new_post_type ) ) {
				$trid        = null;
				$source_lang = null;
			}
		}
		if ( 'post-new.php' === $pagenow ) {
			if ( $trid ) {
				$translations = $this->post_translations->get_element_translations( false, $trid );

				$sticky_posts = wpml_sticky_post_sync()->get_unfiltered_sticky_posts_option();

				$is_sticky = false;
				foreach ( $translations as $t ) {
					if ( in_array( $t, $sticky_posts ) ) {
						$is_sticky = true;
						break;
					}
				}
				if ( $this->sitepress->get_setting( 'sync_ping_status' ) || $this->sitepress->get_setting( 'sync_comment_status' ) ) {
					$this->print_ping_and_comment_sync_js( $trid, $source_lang );
				}
				if ( $this->sitepress->get_setting( 'sync_private_flag' ) && 'private' === $this->post_translations->get_original_post_status( $trid, $source_lang ) ) {
					?>
					<script type="text/javascript"><?php self::print_dom_ready_call(); ?>function () {
							jQuery('#visibility-radio-private').prop('checked', true);
							<?php /* translators: The WordPress post status shown on the post editing screen: only the site's own users can see the post. Adjective. */ ?>
							jQuery('#post-visibility-display').html('<?php echo icl_js_escape( __( 'Private', 'sitepress' ) ); ?>');
						});
					</script>
					<?php
				}
				if ( $this->sitepress->get_setting( 'sync_post_taxonomies' ) ) {
					$this->print_tax_sync_js();
				}
				$custom_field_note = new WPML_Sync_Custom_Field_Note( $this->sitepress );
				$custom_field_note->print_sync_copy_custom_field_note( $source_lang, $translations );
			}
			?>
			<?php if ( ! empty( $is_sticky ) && $this->sitepress->get_setting( 'sync_sticky_flag' ) ) : ?>
				<script type="text/javascript">
					<?php self::print_dom_ready_call(); ?>
						function () {
							var block_editor = wpml_get_block_editor();
							if(block_editor){
								block_editor.then(function () {
									setTimeout(function () {
										wp.data.dispatch('core/editor').editPost({sticky: true});
									}, 100);
								});
							} else {
								jQuery('#sticky').prop('checked', true);
								var post_visibility_display = jQuery('#post-visibility-display');
								<?php /* translators: The WordPress post state shown on the post editing screen: the post is kept at the top of the list. Adjective. */ ?>
								post_visibility_display.html(post_visibility_display.html() + ', <?php echo icl_js_escape( __( 'Sticky', 'sitepress' ) ); ?>');
							}
						}
					);
				</script>
			<?php endif; ?>
			<?php
		}
		if ( ( 'page-new.php' === $pagenow || ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) ) )
			 && ( $trid
				  && ( $this->sitepress->get_setting( 'sync_page_template' )
					   || $this->sitepress->get_setting( 'sync_page_ordering' ) ) )
		) {
			$this->print_mo_sync_js( $trid, $source_lang );
		}

		if ( $this->sitepress->is_post_edit_screen() && $this->sitepress->get_setting( 'sync_post_date' ) ) {
			$this->print_sync_date_js();
		}
		if ( 'post-new.php' === $pagenow && $trid && $sitepress->get_setting( 'sync_post_format' ) && function_exists(
			'get_post_format'
		)
		) {
			$format = $this->post_translations->get_original_post_format( $trid, $source_lang );
			?>
			<script type="text/javascript">
				<?php self::print_dom_ready_call(); ?>function () {
					jQuery('#post-format-' + '<?php echo $format; ?>').prop('checked', true);
				});
			</script>
			<?php
		}

		wp_enqueue_script( 'theme-preview' );

		if ( 'languages' === $page_basename || 'string-translation' === $page_basename ) {
			wp_enqueue_script( 'wp-color-picker' );
			wp_register_style(
				'wpml-color-picker',
				ICL_PLUGIN_URL . '/res/css/colorpicker.css',
				array( 'wp-color-picker' ),
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			wp_enqueue_style( 'wpml-color-picker' );
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}

	private function correct_status_links_js( $current_language ) {
		?>
		<script type="text/javascript">
			<?php self::print_dom_ready_call(); ?>
				function () {
					jQuery(document).ready(
						function () {
							jQuery('.subsubsub>li a').each(
								function () {
									var h = jQuery(this).attr('href');
									var urlg = -1 === h.indexOf('?') ? '?' : '&';
									jQuery(this).attr('href', h + urlg + 'lang=<?php echo esc_js( $current_language ); ?>');
								}
							);
							jQuery('.column-categories a, .column-tags a, .column-posts a').each(
								function () {
									jQuery(this).attr('href', jQuery(this).attr('href') + '&lang=<?php echo esc_js( $current_language ); ?>');
								}
							);
						}
					);
				}
			);
		</script>
		<?php
	}

	private function print_mo_sync_js( $trid, $source_lang ) {
		$menu_order    = $this->sitepress->get_setting( 'sync_page_ordering' )
			? $this->post_translations->get_original_menu_order( $trid, $source_lang )
			: null;
		$page_template = $this->sitepress->get_setting( 'sync_page_template' )
			? get_post_meta(
				$this->post_translations->get_element_id( $source_lang, $trid ),
				'_wp_page_template',
				true
			)
			: null;
		if ( $menu_order || $page_template ) {
			?>
			<script type="text/javascript"><?php self::print_dom_ready_call(); ?>function () {
			<?php
			if ( $menu_order ) {
				?>
					jQuery('#menu_order').val(<?php echo esc_js( $menu_order ); ?>);
					<?php
			}
			if ( $page_template && 'default' !== $page_template ) {
				?>
					jQuery('#page_template').val('<?php echo esc_js( $page_template ); ?>');
					<?php
			}
			?>
				});</script>
				<?php
		}
	}

	private function print_ping_and_comment_sync_js( $trid, $source_lang ) {
		?>
		<script type="text/javascript"><?php self::print_dom_ready_call(); ?>function () {
				var comment_status = jQuery('#comment_status');
				var ping_status = jQuery('#ping_status');
				<?php if ( $this->sitepress->get_setting( 'sync_comment_status' ) ) : ?>
					<?php if ( $this->post_translations->get_original_comment_status( $trid, $source_lang ) === 'open' ) : ?>
				comment_status.prop('checked', true);
				<?php else : ?>
				comment_status.prop('checked', false);
				<?php endif; ?>
				<?php endif; ?>
				<?php if ( $this->sitepress->get_setting( 'sync_ping_status' ) ) : ?>
					<?php if ( $this->post_translations->get_original_ping_status( $trid, $source_lang ) === 'open' ) : ?>
				ping_status.prop('checked', true);
				<?php else : ?>
				ping_status.prop('checked', false);
				<?php endif; ?>
				<?php endif; ?>
			});</script>
			<?php
	}

	private function print_sync_date_js() {
		$post_id = $this->get_current_req_post_id();
		if ( $post_id !== null ) {
			$original_id = $this->post_translations->get_original_element( $post_id );
			if ( $original_id && (int) $original_id !== (int) $post_id ) {
				$original_date        = get_post_field( 'post_date', $original_id );
				$exp                  = explode( ' ', $original_date );
				list( $aa, $mm, $jj ) = explode( '-', $exp[0] );
				list( $hh, $mn, $ss ) = explode( ':', $exp[1] );
				?>
				<script type="text/javascript">
					<?php self::print_dom_ready_call(); ?>
						function () {
							jQuery('#aa').val('<?php echo esc_js( $aa ); ?>').attr('readonly', 'readonly');
							jQuery('#mm').val('<?php echo esc_js( $mm ); ?>').attr('disabled', 'disabled').attr('id', 'mm-disabled').attr('name', 'mm-disabled');
							// create a hidden element for month because we wont get anything returned from the disabled month dropdown.
							jQuery('<input type="hidden" id="mm" name="mm" value="<?php echo $mm; ?>" />').insertAfter('#mm-disabled');
							jQuery('#jj').val('<?php echo esc_js( $jj ); ?>').attr('readonly', 'readonly');
							jQuery('#hh').val('<?php echo esc_js( $hh ); ?>').attr('readonly', 'readonly');
							jQuery('#mn').val('<?php echo esc_js( $mn ); ?>').attr('readonly', 'readonly');
							jQuery('#ss').val('<?php echo esc_js( $ss ); ?>').attr('readonly', 'readonly');
							var timestamp = jQuery('#timestamp');
							timestamp.find('b').append(( '<span> <?php esc_html_e( 'Copied From the Original', 'sitepress' ); ?></span>'));
							timestamp.next().html('<span style="margin-left:1em;"><?php /* translators: Link text that opens something for changing: a date, a language or a translation. Verb, imperative. */ esc_html_e( 'Edit', 'sitepress' ); ?></span>');
						});
				</script>
				<?php
			}
		}
	}

	private function print_tax_sync_js() {
		$post_type   = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';
		$source_lang = isset( $_GET['source_lang'] )
			? \WPML\Language\RequestedLanguage::forPrivileged( sanitize_text_field( wp_unslash( $_GET['source_lang'] ) ) )
			: $this->sitepress->get_default_language();
		if ( null === $source_lang ) {
			return;
		}

		$trid         = filter_var( $_GET['trid'], FILTER_SANITIZE_NUMBER_INT );
		$translations = $this->sitepress->get_element_translations( $trid, 'post_' . $post_type );
		if ( ! isset( $translations[ $source_lang ] ) ) {
			return;
		}

		$current_lang      = $this->sitepress->get_current_language();
		$translatable_taxs = $this->sitepress->get_translatable_taxonomies( true, $post_type );
		$all_taxs          = array_map( 'strval', get_object_taxonomies( $post_type ) );

		$js = array();

		$this->sitepress->switch_lang( $source_lang );

		try {
			foreach ( $all_taxs as $tax ) {
				$tax_detail = get_taxonomy( $tax );
				$terms      = get_the_terms( $translations[ $source_lang ]->element_id, $tax );
				$term_names = array();
				if ( $terms ) {
					foreach ( $terms as $term ) {
						if ( $tax_detail->hierarchical ) {
							$term_id = in_array( $tax, $translatable_taxs )
								? $this->term_translations->term_id_in(
									$term->term_id,
									$current_lang,
									false
								) : $term->term_id;
							$js[]    = 'jQuery(\'input[id^="in-' . $tax . '-' . $term_id . '"]\').filter(function() {
											return /^in-' . $tax . '-' . $term_id . '(-\\d+)?$/.test(this.id);
										}).prop(\'checked\', true);';
						} else {
							if ( in_array( $tax, $translatable_taxs ) ) {
								$term_id = $this->term_translations->term_id_in( $term->term_id, $current_lang, false );
								if ( $term_id ) {
									$term         = get_term( $term_id, $tax );
									$term_names[] = esc_js( $term->name );
								}
							} else {
								$term_names[] = esc_js( $term->name );
							}
						}
					}
				}

				if ( $term_names ) {
					$js[] = "jQuery('#" . esc_js( $tax ) . ".taghint').css('visibility','hidden');";
					$js[] = "jQuery('#new-tag-" . esc_js( $tax ) . "').val('" . esc_js( join( ', ', $term_names ) ) . "');";
				}
			}
		} finally {
			$this->sitepress->switch_lang();
		}

		if ( $js ) {
			?>
			<script type="text/javascript">
			// <![CDATA[
			<?php self::print_dom_ready_call(); ?>function(){
				<?php echo join( PHP_EOL, $js ); ?>
				jQuery().ready(function() {
					jQuery(".tagadd").click();
					jQuery('html, body').prop({scrollTop:0});
					jQuery('#title').focus();
				});
			});
			// ]]>
			</script>
			<?php
		}
	}

	private function get_current_req_post_id() {

		return isset( $_GET['post'] ) ? filter_var( $_GET['post'], FILTER_SANITIZE_NUMBER_INT ) : null;
	}

	private function print_reading_options_js() {
		list( $warn_home, $warn_posts ) = $this->verify_home_and_blog_pages_translations();
		if ( $warn_home || $warn_posts ) {
			?>
			<script type="text/javascript">
				<?php self::print_dom_ready_call(); ?>function () {
					jQuery('input[name="show_on_front"]').parent().parent().parent().parent().append('<?php echo str_replace( "'", "\\'", $warn_home . $warn_posts ); ?>');
				});
			</script>
			<?php
		}
	}

	function wpml_css_setup() {
		$requested_page = \WPML\SuperGlobals\Request::page();
		if ( '' !== $requested_page ) {
			$page          = basename( $requested_page );
			$page_basename = str_replace( '.php', '', $page );
			$page_basename = preg_replace( '/[^\w-]/', '', $page_basename );
		}
		wp_enqueue_style( 'sitepress-style', ICL_PLUGIN_URL . '/res/css/style.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
		if ( isset( $page_basename ) && file_exists( WPML_PLUGIN_PATH . '/res/css/' . $page_basename . '.css' ) ) {
			wp_enqueue_style( 'sitepress-' . $page_basename, ICL_PLUGIN_URL . '/res/css/' . $page_basename . '.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
		}

		wp_enqueue_style( 'wpml-dialog' );
		wp_enqueue_style( 'otgs-icons' );
		wp_enqueue_style( 'wpml-wizard' );
		wp_enqueue_style( 'thickbox' );
	}

	private function verify_home_and_blog_pages_translations() {
		$warn_home     = $warn_posts = '';
		$page_on_front = get_option( 'page_on_front' );
		if ( 'page' === get_option( 'show_on_front' ) && $page_on_front ) {
			$warn_home = $this->missing_page_warning(
				$page_on_front,
				/* translators: Warning in the WPML admin when the front page of the site is missing in one or more languages. %s: the names of those languages, separated by commas. */
				__( 'Your home page does not exist or its translation is not published in %s.', 'sitepress' )
			);
		}
		$page_for_posts = get_option( 'page_for_posts' );
		if ( $page_for_posts ) {
			$warn_posts = $this->missing_page_warning(
				$page_for_posts,
				/* translators: Warning in the WPML admin when the page that lists the posts is missing in one or more languages. %s: the names of those languages, separated by commas. */
				__( 'Your blog page does not exist or its translation is not published in %s.', 'sitepress' ),
				'margin-top:4px;'
			);
		}

		return array( $warn_home, $warn_posts );
	}

	private function missing_page_warning( $original_page_id, $label, $additional_css = '' ) {
		$warn_posts = '';
		if ( $original_page_id ) {
			$page_posts_translations = $this->post_translations->get_element_translations( $original_page_id );
			$missing_posts           = array();
			$active_languages        = $this->sitepress->get_active_languages();
			foreach ( $active_languages as $lang ) {
				if ( ! isset( $page_posts_translations[ $lang['code'] ] )
					 || get_post_status( $page_posts_translations[ $lang['code'] ] ) !== 'publish'
				) {
					$missing_posts[] = $lang['display_name'];
				}
			}
			if ( ! empty( $missing_posts ) ) {
				$warn_posts  = '<div class="icl_form_errors" style="font-weight:bold;' . $additional_css . '">';
				$warn_posts .= sprintf( $label, join( ', ', $missing_posts ) );
				$warn_posts .= '<br />';
				$warn_posts .= '<a href="' . get_edit_post_link( $original_page_id ) . '">' . __(
					'Edit this page to add translations',
					'sitepress'
				) . '</a>';
				$warn_posts .= '</div>';
			}
		}

		return $warn_posts;
	}
}
