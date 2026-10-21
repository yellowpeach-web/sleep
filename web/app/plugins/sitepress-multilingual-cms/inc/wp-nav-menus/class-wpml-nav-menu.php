<?php

use WPML\API\Sanitize;

class WPML_Nav_Menu {
	private $current_menu;
	private $current_lang;

	private $current_language_menus = array();

	protected $term_translations;

	protected $post_translations;

	protected $sitepress;

	public $wpdb;

	public $nav_menu_actions;

	function __construct( SitePress $sitepress, wpdb $wpdb, WPML_Post_Translation $post_translations, WPML_Term_Translation $term_translations ) {
		$this->sitepress         = $sitepress;
		$this->wpdb              = $wpdb;
		$this->post_translations = $post_translations;
		$this->term_translations = $term_translations;
		$this->nav_menu_actions  = new WPML_Nav_Menu_Actions(
			$sitepress,
			$wpdb,
			$post_translations,
			$term_translations
		);
	}

	public function init_hooks() {
		if ( is_admin() ) {
			add_filter( 'option_nav_menu_options', array( $this, 'option_nav_menu_options' ) );
			add_filter( 'wp_get_nav_menus', array( $this, 'wp_get_nav_menus_filter' ) );
		}

		if ( $this->must_filter_menus() ) {
			add_filter( 'get_terms', array( $this, 'get_terms_filter' ), 1, 3 );
		}

		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'wp_nav_menu_args', array( $this, 'wp_nav_menu_args_filter' ) );
		add_filter( 'wp_nav_menu_items', array( $this, 'wp_nav_menu_items_filter' ) );
		add_filter( 'nav_menu_meta_box_object', array( $this, '_enable_sitepress_query_filters' ), 11 );
	}

	private function must_filter_menus() {
		global $pagenow;

		return 'nav-menus.php' === $pagenow
			   || 'widgets.php' === $pagenow
			   || filter_input( INPUT_POST, 'action' ) === 'save-widget';
	}

	function init() {
		global $sitepress, $sitepress_settings, $pagenow, $wpml_request_handler, $wpml_language_resolution;

		$this->adjust_current_language_if_required();

		$default_language = $sitepress->get_default_language();

		if ( $pagenow === 'nav-menus.php' ) {
			add_action( 'admin_footer', array( $this, 'nav_menu_language_controls' ), 10 );

			wp_enqueue_script( 'wp_nav_menus', ICL_PLUGIN_URL . '/res/js/wp-nav-menus.js', array( 'jquery', 'wpml-purify' ), ICL_SITEPRESS_SCRIPT_VERSION, true );
			wp_enqueue_style( 'wp_nav_menus_css', ICL_PLUGIN_URL . '/res/css/wp-nav-menus.css', array(), ICL_SITEPRESS_SCRIPT_VERSION, 'all' );

			add_action( 'parse_query', array( $this, 'action_parse_query' ) );
		}

		if ( is_admin() ) {
			$this->_set_menus_language();
			$this->get_current_menu();
		}

		if ( isset( $_POST['action'] )
			 && $_POST['action'] === 'menu-get-metabox'
			 && (bool) ( $lang = $wpml_language_resolution->get_referrer_language_code() ) !== false
		) {
			$sitepress->switch_lang( $lang );
		}

		if ( isset( $this->current_menu['language'] )
			 && isset( $this->current_menu['id'] )
			 && $this->current_menu['id']
			 && $this->current_menu['language']
			 && $this->current_menu['language'] != $default_language
			 && isset( $_GET['menu'] )
			 && empty( $_GET['lang'] )
		) {
			wp_redirect(
				admin_url(
					sprintf(
						'nav-menus.php?menu=%d&lang=%s',
						$this->current_menu['id'],
						$this->current_menu['language']
					)
				)
			);
		}

		$this->current_lang = $wpml_request_handler->get_requested_lang();

		if ( isset( $_POST['icl_wp_nav_menu_ajax'] ) ) {
			$this->ajax( $_POST );
		}

		add_action( 'admin_footer', array( $this, '_set_custom_status_in_theme_location_switcher' ) );

		if ( ! $sitepress_settings['auto_adjust_ids'] && ! defined( 'DOING_AJAX' ) ) {
			add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
		}
		$this->setup_menu_item();

		if ( $this->sitepress->get_wp_api()->is_core_page( 'menu-sync/menus-sync.php' ) ) {
			$this->setup_menu_synchronization();
		}

		\WPML\Request\Adapter\Ajax::register( 'icl_msync_confirm', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_wp_menus_sync', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( '_icl_nonce_menu_sync', '_icl_nonce_menu_sync' ) ), array( $this, 'sync_menus_via_ajax' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_get_links_for_menu_strings_translation', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_get_links_for_menu_strings_translation', '_nonce' ) ), array( $this, 'get_links_for_menu_strings_translation_ajax' ) );
	}

	function sync_menus_via_ajax() {
		if ( isset( $_POST['_icl_nonce_menu_sync'] ) && wp_verify_nonce( $_POST['_icl_nonce_menu_sync'], '_icl_nonce_menu_sync' ) ) {

			$sync_payload = \WPML\Request\Payload::listField(
				$_POST,
				'sync',
				/* translators: Shown on the WP Menus Sync screen when the browser sent no changes to apply. */
				__( 'No menu changes were received, so nothing was synchronized. Please reload the page and try again.', 'sitepress' )
			);
			if ( \WPML\Request\Payload::isRefusal( $sync_payload ) ) {
				\WPML\Request\Payload::refuse( $sync_payload );

				return;
			}

			global $icl_menus_sync,$wpdb, $wpml_post_translations, $wpml_term_translations, $sitepress;
			include_once WPML_PLUGIN_PATH . '/inc/wp-nav-menus/menus-sync.php';
			$icl_menus_sync = new ICLMenusSync( $sitepress, $wpdb, $wpml_post_translations, $wpml_term_translations );
			$icl_menus_sync->init( WPML_Menu_Sync_Store::get() );

			$unknown_menus = $icl_menus_sync->unknown_menu_ids( $sync_payload );
			if ( $unknown_menus ) {
				\WPML\Request\Payload::refuse(
					\WPML\Request\Payload::invalid(
						'sync',
						'no such menu on this site: ' . implode( ', ', $unknown_menus ),
						/* translators: Shown on the WP Menus Sync screen when the changes it sent name a menu the site no longer has. */
						__( 'These changes are out of date: they name a menu this site no longer has. Please reload the page and try again.', 'sitepress' )
					)
				);

				return;
			}

			$results = \WPML\Core\Compatibility\OperationContext::within(
				\WPML\Core\Compatibility\OperationContext::SYNC_MENUS,
				static function () use ( $icl_menus_sync, $sync_payload ) {
					return $icl_menus_sync->do_sync( $sync_payload );
				}
			);
			WPML_Menu_Sync_Store::save( $results );
			wp_send_json_success( true );
		} else {
			wp_send_json_error( false );
		}
	}

	public function get_links_for_menu_strings_translation_ajax() {
		global $icl_menus_sync, $wpml_post_translations, $wpml_term_translations;
		$nonce = isset( $_GET['_nonce'] ) ? sanitize_text_field( $_GET['_nonce'] ) : '';

		if ( ! current_user_can( 'manage_options' ) ) {
			/* translators: Error message returned when the user is not allowed to carry out the request. */
			wp_send_json_error( esc_html__( 'Unauthorized', 'sitepress' ), 401 );
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'wpml_get_links_for_menu_strings_translation' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ), 400 );
			return;
		}

		include_once WPML_PLUGIN_PATH . '/inc/wp-nav-menus/menus-sync.php';
		$icl_menus_sync = new ICLMenusSync( $this->sitepress, $this->wpdb, $wpml_post_translations, $wpml_term_translations );
		wp_send_json_success( $icl_menus_sync->get_links_for_menu_strings_translation() );
	}

	function admin_menu_setup( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}

		$menu               = array();
		$menu['order']      = 700;
		$menu['page_title'] = __( 'WP Menus Sync', 'sitepress' );
		$menu['menu_title'] = __( 'WP Menus Sync', 'sitepress' );
		$menu['capability'] = 'wpml_manage_wp_menus_sync';
		$menu['menu_slug']  = WPML_PLUGIN_FOLDER . '/menu/menu-sync/menus-sync.php';

		do_action( 'wpml_admin_menu_register_item', $menu );
	}

	private function _set_menus_language() {
		global $wpdb, $sitepress;

		$default_language   = $sitepress->get_default_language();
		$untranslated_menus = $wpdb->get_col(
			"
									            SELECT term_taxonomy_id
									            FROM {$wpdb->term_taxonomy} tt
									            LEFT JOIN {$wpdb->prefix}icl_translations i
									              ON i.element_type = 'tax_nav_menu'
									                AND i.element_id = tt.term_taxonomy_id
									            WHERE tt.taxonomy='nav_menu'
									              AND i.language_code IS NULL"
		);
		foreach ( (array) $untranslated_menus as $item ) {
			$sitepress->set_element_language_details( $item, 'tax_nav_menu', null, $default_language );
		}
		$untranslated_menu_items = $wpdb->get_col(
			"
										            SELECT DISTINCT p.ID
										            FROM {$wpdb->posts} p
										            JOIN {$wpdb->term_relationships} tr
										              ON tr.object_id = p.ID
										            JOIN {$wpdb->term_taxonomy} itt
										              ON itt.term_taxonomy_id = tr.term_taxonomy_id
										                AND itt.taxonomy = 'nav_menu'
										            LEFT JOIN {$wpdb->prefix}icl_translations i
										              ON i.element_type = 'post_nav_menu_item'
										                AND i.element_id = p.ID
										            WHERE p.post_type = 'nav_menu_item'
										              AND i.language_code IS NULL"
		);
		if ( ! empty( $untranslated_menu_items ) ) {
			foreach ( $untranslated_menu_items as $item ) {
				WPML_Set_Language::run_exempt_core_flow(
					function () use ( $sitepress, $item, $default_language ) {
						$sitepress->set_element_language_details( $item, 'post_nav_menu_item', null, $default_language, null, true, true );
					}
				);
			}
		}
	}

	function ajax( $data ) {
		if ( $data['icl_wp_nav_menu_ajax'] == 'translation_of' ) {
			$trid = isset( $data['trid'] ) ? $data['trid'] : false;
			echo $this->render_translation_of( $data['lang'], $trid );
		}
		exit;
	}

	function _get_menu_language( $menu_id ) {
		global $wpml_term_translations;

		return $menu_id ? $wpml_term_translations->lang_code_by_termid( $menu_id ) : false;
	}

	function _get_first_menu( $lang ) {
		global $wpdb;
		$menu_tt_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(element_id) FROM {$wpdb->prefix}icl_translations WHERE element_type='tax_nav_menu' AND language_code=%s",
				$lang
			)
		);

		return $menu_tt_id
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $menu_tt_id ) )
			: false;
	}

	function get_current_menu() {
		global $sitepress, $wpml_request_handler;
		$nav_menu_recently_edited      = get_user_option( 'nav_menu_recently_edited' );
		$nav_menu_recently_edited_lang = $this->_get_menu_language( $nav_menu_recently_edited );
		$current_language              = $sitepress->get_current_language();
		$admin_language_cookie         = $wpml_request_handler->get_cookie_lang();
		if ( ! isset( $_REQUEST['menu'] ) && $nav_menu_recently_edited_lang != $current_language ) {
			$nav_menu_selected_id = $this->_get_first_menu( $current_language );
			if ( $nav_menu_selected_id ) {
				update_user_option( get_current_user_id(), 'nav_menu_recently_edited', $nav_menu_selected_id );
			} else {
				$_REQUEST['menu'] = 0;
			}
		} elseif ( ! isset( $_REQUEST['menu'] ) && ! isset( $_GET['lang'] )
				&& ( empty( $nav_menu_recently_edited_lang ) || $nav_menu_recently_edited_lang != $admin_language_cookie )
				&& ( empty( $_POST['action'] ) || $_POST['action'] != 'update' ) ) {
			$nav_menu_selected_id = $this->_get_first_menu( $current_language );
			update_user_option( get_current_user_id(), 'nav_menu_recently_edited', $nav_menu_selected_id );
		} elseif ( isset( $_REQUEST['menu'] ) ) {
			$nav_menu_selected_id = $_REQUEST['menu'];
		} else {
			$nav_menu_selected_id = $nav_menu_recently_edited;
		}

		$this->current_menu['id'] = $nav_menu_selected_id;
		if ( $this->current_menu['id'] ) {
			$this->_load_menu( $this->current_menu['id'] );
		} else {
			$this->current_menu['trid'] = isset( $_GET['trid'] ) ? (int) $_GET['trid'] : null;
			if ( isset( $_POST['icl_nav_menu_language'] ) ) {
				$this->current_menu['language'] = $_POST['icl_nav_menu_language'];
			} elseif ( isset( $_GET['lang'] ) ) {
				$this->current_menu['language'] = (int) $_GET['lang'];
			} else {
				$this->current_menu['language'] = $admin_language_cookie;
			}
			$this->current_menu['translations'] = array();
		}
	}

	function _load_menu( $menu_id = false ) {
		$menu_id          = $menu_id ? $menu_id : $this->current_menu['id'];
		$menu_term_object = get_term( $menu_id, 'nav_menu' );
		if ( ! empty( $menu_term_object->term_taxonomy_id ) ) {
			$ttid                         = $menu_term_object->term_taxonomy_id;
			$current_menu                 = array( 'id' => $menu_id );
			$current_menu['trid']         = $this->term_translations->get_element_trid( $ttid );
			$current_menu['translations'] = $current_menu['trid']
				? $this->sitepress->get_element_translations( $current_menu['trid'], 'tax_nav_menu' ) : array();
			$current_menu['language']     = $this->term_translations->lang_code_by_termid( $menu_id );
		}
		$this->current_menu = ! empty( $current_menu['translations'] ) ? $current_menu : null;

		return $this->current_menu;
	}

	private function get_action_icon( $css_class, $label ) {
		return '<span class="' . $css_class . '" title="' . esc_attr( $label ) . '"></span>';
	}

	function nav_menu_language_controls() {
		global $sitepress, $wpdb;
		$this->_load_menu();
		$default_language = $sitepress->get_default_language();
		$current_lang     = isset( $this->current_menu['language'] ) ? $this->current_menu['language'] : $sitepress->get_current_language();
		$langsel          = '<br class="clear" />';

		if ( isset( $this->current_menu['id'] ) && $this->current_menu['id'] ) {
			$langsel .= '<div class="icl_nav_menu_text" style="float:right;">';
			/* translators: Label in front of the flags that show which translations a menu item has, on the menus screen. */
			$langsel .= __( 'Translations:', 'sitepress' );
			foreach ( $sitepress->get_active_languages() as $lang ) {
				if ( ! isset( $this->current_menu['language'] )
					 || $lang['code'] == $this->current_menu['language'] ) {
					continue;
				}
				if ( isset( $this->current_menu['translations'][ $lang['code'] ] ) ) {
					$menu_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $this->current_menu['translations'][ $lang['code'] ]->element_id ) );
					/* translators: Name of the flag icon of a menu item that already has a translation in that language, used as its tooltip. Verb phrase, imperative, written in lower case. */
					$label   = __( 'edit translation', 'sitepress' );
					$tr_link = '<a style="text-decoration:none" title="' . esc_attr( $label ) . '" href="' . admin_url( 'nav-menus.php' ) .
							   '?menu=' . $menu_id
							   . '&lang=' . $lang['code'] . '">'
							   . $this->get_action_icon( WPML_Post_Status_Display::ICON_TRANSLATION_EDIT, $label )
							   . $lang['display_name']
							   . '</a>';
				} else {
					/* translators: Name of the flag icon of a menu item that has no translation yet in that language, used as its tooltip. Verb phrase, imperative, written in lower case. */
					$label   = __( 'add translation', 'sitepress' );
					$tr_link = '<a style="text-decoration:none" title="' . esc_attr( $label ) . '" href="' . admin_url( 'nav-menus.php' ) .
							   '?action=edit&menu=0&trid=' . $this->current_menu['trid']
							   . '&lang=' . $lang['code'] . '">'
							   . $this->get_action_icon( WPML_Post_Status_Display::ICON_TRANSLATION_ADD, $label )
							   . esc_html( $lang['display_name'] )
							   . '</a>';
				}
				$trs[] = $tr_link;
			}
			$langsel .= '&nbsp;';
			if ( isset( $trs ) ) {
				$langsel .= join( ', ', $trs );
			}
			$langsel .= '</div><br />';
			$langsel .= '<div class="icl_nav_menu_text" style="float:right; clear:right">';
			$langsel .= '<div><a href="' . admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/menu-sync/menus-sync.php' ) . '">' . __( 'Synchronize menus between languages.', 'sitepress' ) . '</a></div>';
			$langsel .= '</div>';

		}

		/* translators: Column heading and field label in the WPML admin, for the language of a piece of content. Noun, singular. */
		$langsel .= '<label class="menu-name-label howto"><span>' . __( 'Language', 'sitepress' ) . '</span>';
		$langsel .= '&nbsp;&nbsp;';
		$langsel .= '<select name="icl_nav_menu_language" id="icl_menu_language">';
		foreach ( $sitepress->get_active_languages() as $lang ) {
			if ( isset( $this->current_menu['translations'][ $lang['code'] ] ) && $this->current_menu['language'] != $lang['code'] ) {
				continue;
			}
			if ( isset( $this->current_menu['language'] ) && $this->current_menu['language'] ) {
				$selected = $lang['code'] == $this->current_menu['language'] ? ' selected="selected"' : '';
			} else {
				$selected = $lang['code'] == $sitepress->get_current_language() ? ' selected="selected"' : '';
			}
			$langsel .= '<option value="' . $lang['code'] . '"' . $selected . '>' . $lang['display_name'] . '</option>';
		}
		$langsel .= '</select>';
		$langsel .= '</label>';

		if ( $current_lang !== $default_language ) {
			$langsel     .= '<span id="icl_translation_of_wrap">';
			$trid_current = ! empty( $this->current_menu['trid'] ) ? $this->current_menu['trid'] : ( isset( $_GET['trid'] ) ? $_GET['trid'] : 0 );
			$langsel     .= $this->render_translation_of( $current_lang, (int) $trid_current );

			$langsel .= '</span>';
		}
		$langsel .= '</span>';

		if ( $this->current_menu && $this->current_menu['trid'] ) {
			$langsel .= '<input type="hidden" id="icl_nav_menu_trid" name="icl_nav_menu_trid" value="' . $this->current_menu['trid'] . '" />';
		}

		$langsel .= '';

		echo $this->render_button_language_switcher_settings();
		?>
		<script type="text/javascript">
					jQuery(document).ready(function () {
						<?php WPML_Admin_Scripts_Setup::print_dom_ready_call(); ?>function () {
							var update_menu_form = jQuery('#update-nav-menu');
							update_menu_form.find('.publishing-action:first').before(<?php echo wp_json_encode( $langsel ); ?>);
							jQuery('#side-sortables').before('<?php $this->languages_menu(); ?>');
				<?php if ( $this->current_lang != $default_language ) : ?>
							jQuery('.nav-tabs .nav-tab').each(function () {
								jQuery(this).attr('href', jQuery(this).attr('href') + '&lang=<?php echo esc_js( $this->current_lang ); ?>');
							});
							var original_action = update_menu_form.attr('ACTION') ? update_menu_form.attr('ACTION') : '';
							update_menu_form.attr('ACTION', original_action + '?lang=<?php echo esc_js( $this->current_lang ); ?>');
				<?php endif; ?>
							WPML_core.wp_nav_align_inputs();

						});
					});
		</script>
		<?php
	}

	function get_menus_without_translation( $lang, $trid = 0 ) {
		$wpdb = $this->wpdb;
		$res  = $wpdb->get_results(
			$wpdb->prepare(
				"
	                SELECT ts.element_id, ts.trid, t.name
			        FROM {$wpdb->prefix}icl_translations ts
			        JOIN {$wpdb->term_taxonomy} tx ON ts.element_id = tx.term_taxonomy_id
			        JOIN {$wpdb->terms} t ON tx.term_id = t.term_id
			        LEFT JOIN {$wpdb->prefix}icl_translations mo
		            ON mo.trid = ts.trid
		            	AND mo.language_code = %s
		        WHERE ts.element_type='tax_nav_menu'
	                AND ts.language_code != %s
		            AND ts.source_language_code IS NULL
		            AND tx.taxonomy = 'nav_menu'
			            AND ( mo.element_id IS NULL OR ts.trid = %d )",
				$lang,
				$lang,
				$trid
			)
		);
		$menus              = array();
		foreach ( $res as $row ) {
			$menus[ $row->trid ] = $row;
		}

		return $menus;
	}

	private function render_translation_of( $lang, $trid = false ) {
		global $sitepress;
		$out = '';

		if ( $sitepress->get_default_language() != $lang ) {
			$menus    = $this->get_menus_without_translation( $lang, (int) $trid );
			$disabled = empty( $this->current_menu['id'] ) && isset( $_GET['trid'] ) ? ' disabled="disabled"' : '';
			/* translators: Label in front of the name of the menu this one is a translation of, on the menus screen. The menu name follows it, so it ends without a full stop. */
			$out     .= '<label class="menu-name-label howto"><span>' . __( 'Translation of', 'sitepress' ) . '</span>&nbsp;';
			$out     .= '<select name="icl_translation_of" id="icl_menu_translation_of"' . $disabled . '>';
			/* translators: Shown in place of a value when there is nothing to show. Written in lower case because it stands where a value would. */
			$out     .= '<option value="none">--' . __( 'none', 'sitepress' ) . '--</option>';
			foreach ( $menus as $mtrid => $m ) {
				if ( (int) $trid === (int) $mtrid ) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';
				}
				$out .= '<option value="' . (int) $m->element_id . '"' . $selected . '>' . esc_html( $m->name ) . '</option>';
			}
			$out .= '</select>';
			$out .= '</label>';
			if ( $disabled !== '' ) {
				$out .= '<input type="hidden" name="icl_nav_menu_trid" value="' . (int) $_GET['trid'] . '"/>';
			}
		}

		return $out;
	}

	private function render_button_language_switcher_settings() {
		global $wpml_language_switcher;

		$output            = '';
		$default_lang      = $this->sitepress->get_default_language();
		$default_lang_menu = isset( $this->current_menu['translations'][ $default_lang ] )
			? $this->current_menu['translations'][ $default_lang ] : null;

		if ( $default_lang_menu && isset( $default_lang_menu->element_id ) ) {
			$output  = '<div id="wpml-ls-menu-management" style="display:none;">';
			$output .= $wpml_language_switcher->get_button_to_edit_slot( 'menus', $default_lang_menu->element_id );
			$output .= '</div>';
		}

		return $output;
	}

	function get_menus_by_language() {
		global $wpdb, $sitepress;
		$langs          = array();
		$admin_language = $sitepress->get_admin_language();
		$res            = $wpdb->get_results(
			$wpdb->prepare(
				"
	            SELECT lt.name AS language_name, l.code AS lang, COUNT(ts.translation_id) AS c
            FROM {$wpdb->prefix}icl_languages l
                JOIN {$wpdb->prefix}icl_languages_translations lt ON lt.language_code = l.code
                JOIN {$wpdb->prefix}icl_translations ts ON l.code = ts.language_code
            WHERE lt.display_language_code=%s
                AND l.active = 1
                AND ts.element_type = 'tax_nav_menu'
            GROUP BY ts.language_code
	            ORDER BY major DESC, english_name ASC",
				$admin_language
			)
		);
		foreach ( $res as $row ) {
			$langs[ $row->lang ] = $row;
		}
		return $langs;
	}

	function languages_menu( $echo = true ) {
		global $sitepress;
		$langs = $this->get_menus_by_language();

		foreach ( $sitepress->get_active_languages() as $lang ) {
			if ( ! isset( $langs[ $lang['code'] ] ) ) {
				$langs[ $lang['code'] ]                = new stdClass();
				$langs[ $lang['code'] ]->language_name = $lang['display_name'];
				$langs[ $lang['code'] ]->lang          = $lang['code'];
			}
		}
		$url = admin_url( 'nav-menus.php' );
		$ls  = array();
		foreach ( $langs as $l ) {
			$class        = $l->lang == $this->current_lang ? ' class="current"' : '';
			$url_suffix   = '?lang=' . $l->lang;
			$count_string = isset( $l->c ) && $l->c > 0 ? ' (' . $l->c . ')' : '';
			$ls[]         = '<a href="' . $url . $url_suffix . '"' . $class . '>' . esc_html( $l->language_name ) . $count_string . '</a>';
		}
		$ls_string  = '<div class="icl_lang_menu icl_nav_menu_text">';
		$ls_string .= join( '&nbsp;|&nbsp;', $ls );
		$ls_string .= '</div>';
		if ( $echo ) {
			echo $ls_string;
		}

		return $ls_string;
	}

	function get_terms_filter( $terms, $taxonomies, $args ) {
		global $wpdb, $sitepress, $pagenow;

		if ( ! is_array( $terms ) ) {
			return $terms;
		}

		$taxonomies = array_values( (array) $taxonomies );
		if ( ! $taxonomies ) {
			return $terms;
		}

		$fields = is_array( $args ) && isset( $args['fields'] ) ? $args['fields'] : '';

		if ( ! $sitepress->is_translated_taxonomy( $taxonomies[0] ) && 'nav_menu' !== $taxonomies[0] ) {
			return $terms;
		}

		if ( 'nav-menus.php' === $pagenow
			 && array_key_exists( 'fields', $args )
			 && array_key_exists( 'action', $_POST )
			 && 'nav_menu' === $taxonomies[0]
			 && 'ids' === $args['fields']
			 && 'update' === $_POST['action']
		) {
			return $terms;
		}

		if ( ! empty( $terms ) ) {
			$txs = array();
			foreach ( $taxonomies as $t ) {
				$txs[] = 'tax_' . $t;
			}

			$tt = array();
			foreach ( $terms as $t ) {
				if ( is_object( $t ) ) {
					$tt[] = $t->term_taxonomy_id;
				} else {
					if ( is_numeric( $t ) ) {
						$tt[] = $t;
					}
				}
			}

			$ftt = array();
			if ( ! empty( $tt ) ) {
				$ftt = $wpdb->get_col(
					$wpdb->prepare(
						"
                            SELECT element_id
                            FROM {$wpdb->prefix}icl_translations
							WHERE element_type IN (" . implode( ', ', array_fill( 0, count( $txs ), '%s' ) ) . ")
							  AND element_id IN (" . implode( ', ', array_fill( 0, count( $tt ), '%d' ) ) . ')
	                              AND language_code=%s',
						...array_merge(
							array_values( $txs ),
							array_map( 'intval', array_values( $tt ) ),
							[ $this->current_lang ]
						)
					)
				);
			}

			foreach ( $terms as $k => $v ) {
				if ( isset( $v->term_taxonomy_id ) && ! in_array( $v->term_taxonomy_id, $ftt ) ) {
					unset( $terms[ $k ] );
				}
			}

			if ( 'nav_menu' === $taxonomies[0] ) {
				$terms = $this->scope_menu_strings_to_current_language( $terms, $fields );
			}
		}

		if ( in_array( $fields, array( 'id=>name', 'id=>slug', 'id=>parent' ), true ) ) {
			return $terms;
		}

		return array_values( $terms );
	}

	private function scope_menu_strings_to_current_language( $terms, $fields ) {
		if ( ! $this->current_lang ) {
			return $terms;
		}

		$byKey = array(
			'id=>name' => 'term_id',
			'id=>slug' => 'term_id',
		);
		$byValue = array(
			'names' => 'name',
			'slugs' => 'slug',
		);

		if ( isset( $byKey[ $fields ] ) ) {
			$allowed = $this->get_current_language_menus( 'term_id' );
			foreach ( $terms as $k => $v ) {
				if ( ! in_array( (string) $k, $allowed, true ) ) {
					unset( $terms[ $k ] );
				}
			}

			return $terms;
		}

		if ( ! isset( $byValue[ $fields ] ) ) {
			return $terms;
		}

		$allowed = $this->get_current_language_menus( $byValue[ $fields ] );
		foreach ( $terms as $k => $v ) {
			if ( is_string( $v ) && ! in_array( $v, $allowed, true ) ) {
				unset( $terms[ $k ] );
			}
		}

		return $terms;
	}

	private function get_current_language_menus( $column ) {
		global $wpdb;

		$columns = array( 'term_id', 'name', 'slug' );
		if ( ! in_array( $column, $columns, true ) ) {
			return array();
		}

		if ( ! isset( $this->current_language_menus[ $this->current_lang ] ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT t.term_id, t.name, t.slug
					FROM {$wpdb->terms} t
					INNER JOIN {$wpdb->term_taxonomy} tt
						ON tt.term_id = t.term_id AND tt.taxonomy = 'nav_menu'
					INNER JOIN {$wpdb->prefix}icl_translations icl_t
						ON icl_t.element_id = tt.term_taxonomy_id AND icl_t.element_type = 'tax_nav_menu'
					WHERE icl_t.language_code = %s",
					$this->current_lang
				)
			);

			$menus = array_fill_keys( $columns, array() );
			foreach ( (array) $rows as $row ) {
				foreach ( $columns as $name ) {
					$menus[ $name ][] = isset( $row->{$name} ) ? (string) $row->{$name} : '';
				}
			}

			$this->current_language_menus[ $this->current_lang ] = $menus;
		}

		return $this->current_language_menus[ $this->current_lang ][ $column ];
	}

	public function parse_query( $q ) {
		if ( ! array_key_exists( 'post_type', $q->query_vars ) ) {
			return $q;
		}

		if ( 'nav_menu_item' === $q->query_vars['post_type'] ) {
			return $q;
		}

		if ( $this->sitepress->is_translated_post_type( $q->query_vars['post_type'] ) ) {
			$q->query_vars['suppress_filters'] = 0;
		}

		return $q;
	}
	public function action_parse_query( $q ) {
		$this->parse_query( $q );
	}

	function option_nav_menu_options( $val ) {
		global $wpdb, $sitepress;
		$debug_backtrace = $sitepress->get_backtrace( 5 );

		if ( isset( $debug_backtrace[4] ) && $debug_backtrace[4]['function'] === '_wp_auto_add_pages_to_menu' && ! empty( $val['auto_add'] ) ) {
			$post_lang = Sanitize::stringProp( 'icl_post_language', $_POST );
			$post_lang = ! $post_lang && isset( $_POST['lang'] ) ? Sanitize::string( $_POST['lang'] ) : $post_lang;
			$post_lang = ! $post_lang && $this->is_duplication_mode() ? $sitepress->get_current_language() : $post_lang;

			if ( $post_lang ) {
				$val['auto_add'] = $wpdb->get_col(
					$wpdb->prepare(
						"
					SELECT element_id
					FROM {$wpdb->prefix}icl_translations
					WHERE element_type = 'tax_nav_menu'
						AND element_id IN ( " . implode( ', ', array_fill( 0, count( $val['auto_add'] ), '%d' ) ) . ' )
						AND language_code = %s',
						...array_merge(
							array_map( 'intval', array_values( $val['auto_add'] ) ),
							[ $post_lang ]
						)
					)
				);
			}
		}

		return $val;
	}

	private function is_duplication_mode() {
		return isset( $_POST['langs'] );
	}

	function wp_nav_menu_args_filter( $args ) {

		if ( ! $args['menu'] ) {
			$locations = get_nav_menu_locations();
			if ( isset( $args['theme_location'] ) && isset( $locations[ $args['theme_location'] ] ) ) {
				$args['menu'] = self::convert_nav_menu_id( $locations[ $args['theme_location'] ] );
			}
		};

		if ( ! $args['menu'] ) {
			remove_filter( 'theme_mod_nav_menu_locations', array( $this->nav_menu_actions, 'theme_mod_nav_menu_locations' ) );
			$locations = get_nav_menu_locations();
			if ( isset( $args['theme_location'] ) && isset( $locations[ $args['theme_location'] ] ) ) {
				$args['menu'] = self::convert_nav_menu_id( $locations[ $args['theme_location'] ] );
			}
			add_filter( 'theme_mod_nav_menu_locations', array( $this->nav_menu_actions, 'theme_mod_nav_menu_locations' ) );
		}

		if ( is_object( $args['menu'] ) && ( ! empty( $args['menu']->term_id ) ) ) {
				$args['menu'] = wp_get_nav_menu_object( self::convert_nav_menu_id( $args['menu']->term_id ) );
		}

		if ( ( ! is_object( $args['menu'] ) ) && is_numeric( $args['menu'] ) ) {
				$args['menu'] = wp_get_nav_menu_object( self::convert_nav_menu_id( (int) $args['menu'] ) );
		}

		if ( ( ! is_object( $args['menu'] ) ) && is_string( $args['menu'] ) ) {
			$term = get_term_by( 'slug', $args['menu'], 'nav_menu' );
			if ( false === $term ) {
					$term = get_term_by( 'name', $args['menu'], 'nav_menu' );
			}

			if ( false !== $term ) {
					$args['menu'] = wp_get_nav_menu_object( self::convert_nav_menu_id( $term->term_id ) );
			}
		}

		if ( ! is_object( $args['menu'] ) ) {
				$args['menu'] = false;
		}

		return $args;
	}

	private static function convert_nav_menu_id( $navMenuId ) {
		return wpml_object_id_filter( $navMenuId, 'nav_menu', true );
	}

	function wp_nav_menu_items_filter( $items ) {
		$items = preg_replace(
			'|<li id="([^"]+)" class="menu-item menu-item-type-taxonomy"><a href="([^"]+)">([^@]+) @([^<]+)</a>|',
			'<li id="$1" class="menu-item menu-item-type-taxonomy"><a href="$2">$3</a>',
			$items
		);
		return $items;
	}

	function _set_custom_status_in_theme_location_switcher() {
		global $sitepress_settings, $sitepress, $wpdb;

		if ( ! $sitepress_settings ) {
			return;
		}
		$tl                   = (array) get_theme_mod( 'nav_menu_locations' );
		$menus_not_translated = array();
		foreach ( $tl as $k => $menu ) {
			$menu_tt_id        = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='nav_menu'", $menu ) );
			$menu_trid         = $sitepress->get_element_trid( $menu_tt_id, 'tax_nav_menu' );
			$menu_translations = $sitepress->get_element_translations( $menu_trid, 'tax_nav_menu' );
			if ( ! isset( $menu_translations[ $this->current_lang ] ) || ! $menu_translations[ $this->current_lang ] ) {
				$menus_not_translated[] = $k;
			}
		}
		if ( ! empty( $menus_not_translated ) ) {
			?>
			<script type="text/javascript">
							jQuery(document).ready(function () {
								<?php WPML_Admin_Scripts_Setup::print_dom_ready_call(); ?>function () {
					<?php foreach ( $menus_not_translated as $menu_id ) : ?>
									var menu_id = '<?php echo esc_js( $menu_id ); ?>';
									var location_menu_id = jQuery('#locations-' + menu_id);
									if (location_menu_id.length > 0) {
										location_menu_id.find('option').first().html('<?php echo esc_js( /* translators: First option in the dropdown that picks a menu, shown when the menu has no translation in the language being edited. It starts in lower case as the dropdown shows it. */ __( 'not translated in current language', 'sitepress' ) ); ?>');
										location_menu_id.css('font-style', 'italic');
										location_menu_id.change(function () {
											if (jQuery(this).val() != 0) {
												jQuery(this).css('font-style', 'normal');
											} else {
												jQuery(this).css('font-style', 'italic')
											}
										});
									}
					<?php endforeach; ?>
								});
							});
			</script>
			<?php
		}
	}

	function _enable_sitepress_query_filters( $args ) {
		if ( isset( $args->_default_query ) ) {
			$args->_default_query['suppress_filters'] = false;
		}
		return $args;
	}

	function wp_get_nav_menus_filter( $menus ) {
		global $pagenow;
		if ( is_admin() && isset( $pagenow ) && $pagenow === 'customize.php' ) {
			$menus = $this->unfilter_non_default_language_menus( $menus );
		}

		return $menus;
	}

	private function setup_menu_item() {
		add_action( 'wpml_admin_menu_configure', array( $this, 'admin_menu_setup' ) );
	}

	private function setup_menu_synchronization() {
		global $icl_menus_sync, $wpml_post_translations, $wpml_term_translations;
		include_once WPML_PLUGIN_PATH . '/inc/wp-nav-menus/menus-sync.php';
		$icl_menus_sync = new ICLMenusSync( $this->sitepress, $this->wpdb, $wpml_post_translations, $wpml_term_translations );
	}

	private function unfilter_non_default_language_menus( $menus ) {
		global $sitepress, $wpml_term_translations;
		$default_language = $sitepress->get_default_language();

		foreach ( $menus as $index => $menu ) {
			$menu_ttid = is_object( $menu ) ? $menu->term_taxonomy_id : $menu;
			$menu_language = $wpml_term_translations->get_element_lang_code( $menu_ttid );
			if ( $menu_language != $default_language && $menu_language != null ) {
				unset( $menus[ $index ] );
			}
		}

		return $menus;
	}

	private function adjust_current_language_if_required() {
		global $pagenow;

		if ( $pagenow === 'nav-menus.php' && isset( $_GET['menu'] ) && $_GET['menu'] ) {
			$current_lang = $this->sitepress->get_current_language();
			$menu_lang    = $this->_get_menu_language( (int) $_GET['menu'] );
			if ( $menu_lang && ( $current_lang !== $menu_lang ) ) {
				$this->sitepress->switch_lang( $menu_lang );
				$_GET['lang'] = $menu_lang;
			}
		}

	}
}
