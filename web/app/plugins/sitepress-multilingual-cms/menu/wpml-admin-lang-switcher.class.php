<?php

use WPML\FP\Obj;
use WPML\UIPage;

class WPML_Admin_Language_Switcher {

	private $flag_kses_tags = array(
		'img' => array(
			'src'    => array(),
			'class'  => array(),
			'height' => array(),
			'width'  => array(),
		),
		'i'   => array(
			'class'       => array(),
			'aria-hidden' => array(),
		),
	);

	private $current_language;

	private function has_flag_file_url( $flag_url ) {
		if ( ! $flag_url ) {
			return false;
		}

		$path = wp_parse_url( $flag_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === trim( $path, '/' ) ) {
			return false;
		}

		return 'flags' !== basename( untrailingslashit( $path ) );
	}

	private function get_flag_markup( $sitepress, $language_code ) {
		$flag_url = $sitepress->get_flag_url( $language_code );

		if ( ! $this->has_flag_file_url( $flag_url ) ) {
			return '<i class="otgs-ico-flag"></i>';
		}

		return $sitepress->get_flag_image( $language_code, [], '', [ 'icl_als_iclflag' ] );
	}

	function render() {
		wp_enqueue_script( OTGS_Assets_Handles::POPOVER_TOOLTIP );
		wp_enqueue_style( OTGS_Assets_Handles::POPOVER_TOOLTIP );

		$languages_links = $this->get_languages_links();
		$this->render_admin_bar_menu( $languages_links, $this->current_language );
	}

	public static function link_base_page( $script_basename ) {
		return 'admin-ajax.php' === $script_basename ? 'post.php' : $script_basename;
	}

	public static function follows_post_language( $post_language, $current_language, $language_resolution ) {
		return $post_language
			&& $post_language != $current_language
			&& $language_resolution->is_language_active( $post_language, false );
	}

	public function get_languages_links() {
		global $wpdb, $pagenow, $mode, $sitepress, $wpml_language_resolution;

		$all_languages_enabled = true;
		$script_name           = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : '';
		$current_page          = self::link_base_page( basename( $script_name ) );
		$post_type             = false;
		$trid                  = false;
		$translations          = false;
		$languages_links       = array();

		$is_post = false;
		$is_tax  = false;
		$is_menu = false;

		$this->current_language = $sitepress->get_current_language();
		$this->current_language = $this->current_language ? $this->current_language : $sitepress->get_default_language();

		switch ( $pagenow ) {
			case 'post.php':
			case 'admin-ajax.php':
				$is_post = true;
				$post_id = @intval( Obj::propOr( $_GET['post'], 'post_id', $_POST ) );
				$post    = get_post( $post_id );

				$post_language = $sitepress->get_language_for_element( $post_id, 'post_' . get_post_type( $post_id ) );
				if ( self::follows_post_language( $post_language, $this->current_language, $wpml_language_resolution ) ) {
					$sitepress->switch_lang( $post_language );
					$this->current_language = $sitepress->get_current_language();
				}
				$trid         = $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );
				$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type, true );

				break;
			case 'post-new.php':
				$all_languages_enabled = false;
				if ( isset( $_GET['trid'] ) ) {
					$trid         = intval( $_GET['trid'] );
					$post_type    = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post';
					$translations = $sitepress->get_element_translations( $trid, 'post_' . $post_type, true );
					$is_post      = true;
				}
				break;
			case 'edit-tags.php':
			case 'term.php':
				$is_tax = true;
				if ( $sitepress->get_wp_api()->is_term_edit_page() ) {
					$all_languages_enabled = false;
				}

				$taxonomy    = \WPML\SuperGlobals\Request::param( 'taxonomy' );
				$term_tax_id = 0;

				if ( isset( $_GET['tag_ID'] ) ) {
					$term_id     = @intval( $_GET['tag_ID'] );
					$term_tax_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND term_id=%d", $taxonomy, $term_id ) );
				}
				if ( $term_tax_id ) {
					$trid = $sitepress->get_element_trid( $term_tax_id, 'tax_' . $taxonomy );
				}
				if ( $trid ) {
					$translations = $sitepress->get_element_translations( $trid, 'tax_' . $taxonomy, true );
				}

				break;
			case 'nav-menus.php':
				$is_menu = true;
				if ( isset( $_GET['menu'] ) && $_GET['menu'] ) {
					$menu_id    = $_GET['menu'];
					$element_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d", $menu_id ) );
					$trid         = $trid = $sitepress->get_element_trid( $element_id, 'tax_nav_menu' );
					$translations = $sitepress->get_element_translations( $trid, 'tax_nav_menu', true );
				}
				$all_languages_enabled = false;
				break;
		}

		if( UIPage::isTMDashboard( $_GET ) ) {
			$all_languages_enabled = false;
		}

		$active_languages = $sitepress->get_active_languages();

		if ( 'all' !== $this->current_language && ! isset( $active_languages[ $this->current_language ] ) ) {
			$this->current_language = $sitepress->get_default_language();
		}

		$current_active_language = null;
		if ( 'all' !== $this->current_language ) {
			$current_active_language = isset( $active_languages[ $this->current_language ] ) ? $active_languages[ $this->current_language ] : null;
		}
		$active_languages = apply_filters( 'wpml_admin_language_switcher_active_languages', $active_languages );
		if ( 'all' !== $this->current_language && ! isset( $active_languages[ $this->current_language ] ) && null !== $current_active_language ) {
			array_unshift( $active_languages, $current_active_language );
		}

		$request_query_vars = array();
		$request_query      = isset( $_SERVER['QUERY_STRING'] ) ? filter_var( wp_unslash( $_SERVER['QUERY_STRING'] ), FILTER_SANITIZE_URL ) : '';
		if ( $request_query ) {
			parse_str( $request_query, $request_query_vars );
			unset( $request_query_vars['lang'], $request_query_vars['admin_bar'] );
		}

		foreach ( $active_languages as $lang ) {
			$current_page_lang = $current_page;

			$query_vars = $request_query_vars;
			if ( $is_post ) {
				if ( isset( $translations[ $lang['code'] ] ) && isset( $translations[ $lang['code'] ]->element_id ) ) {
					$query_vars['post'] = $translations[ $lang['code'] ]->element_id;
					unset( $query_vars['source_lang'] );
					$current_page_lang    = 'post.php';
					$query_vars['action'] = 'edit';
				} else {
					$current_page_lang = 'post-new.php';
					if ( isset( $post ) ) {
						$query_vars['post_type']   = $post->post_type;
						$query_vars['source_lang'] = $this->current_language;
					} else {
						$query_vars['post_type'] = $post_type;
					}
					$query_vars['trid'] = $trid;
					unset( $query_vars['post'], $query_vars['action'] );
				}
			} elseif ( $is_tax ) {
				if ( isset( $translations[ $lang['code'] ] ) && isset( $translations[ $lang['code'] ]->term_id ) ) {
					$query_vars['tag_ID'] = $translations[ $lang['code'] ]->term_id;
				} else {
					$query_vars['trid']        = $trid;
					$query_vars['source_lang'] = $this->current_language;
					unset( $query_vars['tag_ID'], $query_vars['action'] );
				}
			} elseif ( $is_menu ) {
				if ( ! empty( $menu_id ) ) {
					if ( isset( $translations[ $lang['code'] ]->term_id ) ) {
						$query_vars['menu'] = $translations[ $lang['code'] ]->term_id;
					} else {
						$query_vars['menu']   = 0;
						$query_vars['trid']   = $trid;
						$query_vars['action'] = 'edit';
					}
				}
			}

			$query_string = http_build_query( $query_vars );

			$query = '?';
			if ( ! empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query .= 'lang=' . $lang['code'];

			$link_url = admin_url( $current_page_lang . $query );

			$languages_links[ $lang['code'] ] = array(
				'url'     => $link_url . '&admin_bar=1',
				'current' => $lang['code'] == $this->current_language,
				'anchor'  => $lang['display_name'],
				'flag'    => $this->get_flag_markup( $sitepress, $lang['code'] ),
			);

		}

		if ( $all_languages_enabled ) {
			$all_query_vars = $request_query_vars;
			if ( $is_post && ! empty( $post_id ) && 'post.php' === $current_page ) {
				$all_query_vars['post']   = $post_id;
				$all_query_vars['action'] = 'edit';
			}
			$query_string = http_build_query( $all_query_vars );

			$query = '?';
			if ( ! empty( $query_string ) ) {
				$query .= $query_string . '&';
			}
			$query   .= 'lang=all';
			$link_url = admin_url( $current_page . $query );

			$languages_links['all'] = array(
				'url'     => $link_url,
				'current' => 'all' == $this->current_language,
				/* translators: First option in the language dropdown of the WPML admin: show the content of every language at once. */
				'anchor'  => __( 'All languages', 'sitepress' ),
				'flag'    => '<i class="otgs-ico-wpml" aria-hidden="true"></i>',
			);
		} else {
			if ( 'all' == $this->current_language ) {
				$this->current_language                                = $sitepress->get_default_language();
				$languages_links[ $this->current_language ]['current'] = true;
			}
		}

		$current_language_item = isset( $languages_links[ $this->current_language ] ) ? $languages_links[ $this->current_language ] : null;
		$languages_links       = apply_filters( 'wpml_admin_language_switcher_items', $languages_links );
		if ( ! isset( $languages_links[ $this->current_language ] ) ) {
			$languages_links = array_merge( array( $this->current_language => $current_language_item ), $languages_links );
		}

		return $languages_links;
	}


	private function render_admin_bar_menu( $languages_links, $current_language ) {

		global $wp_admin_bar;

		$parent = 'WPML_ALS';
		$lang   = isset( $languages_links[ $this->current_language ] ) ? $languages_links[ $this->current_language ] : null;

		if ( null === $lang ) {
			return;
		}

		$help_tip_text = __( 'This language selector determines which content to display. You can choose items in a specific language or in all languages. To change the language of the WordPress Admin interface, go to your profile.', 'sitepress' );

		$wp_admin_bar->add_menu(
			array(
				'parent' => false,
				'id'     => $parent,
				/* translators: Tooltip of the language dropdown of the WPML admin; the name of the language follows it, as in "Showing content in: German". */
				'title'  => '<span title="' . __( 'Showing content in:', 'sitepress' ) . ' ' . $lang['anchor'] . '">'
							. wp_kses( $lang['flag'], $this->flag_kses_tags ) . '&nbsp;' . esc_html( $lang['anchor'] )
							. '</span>'
							. '&nbsp;<i  class="otgs-ico-help js-otgs-popover-tooltip" data-tippy-zIndex="999999" title="' . $help_tip_text . '" ></i>',
				'href'   => false,
			)
		);

		if ( $languages_links ) {
			foreach ( $languages_links as $code => $lang ) {
				if ( $code == $this->current_language ) {
					continue;
				}
				$wp_admin_bar->add_menu(
					array(
						'parent' => $parent,
						'id'     => $parent . '_' . $code,
						'title'  => wp_kses( $lang['flag'], $this->flag_kses_tags ) . '&nbsp;' . esc_html( $lang['anchor'] ),
						'href'   => $lang['url'],
						'meta'   => array(
							/* translators: Tooltip of a language in the dropdown of the WPML admin; the name of that language follows it, as in "Show content in: German". */
							'title' => __( 'Show content in:', 'sitepress' ) . ' ' . $lang['anchor'],
						),
					)
				);
			}
		}
	}
}
