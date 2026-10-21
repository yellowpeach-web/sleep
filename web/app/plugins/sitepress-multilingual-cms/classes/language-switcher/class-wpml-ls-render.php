<?php

use WPML\LanguageSwitcher\SubmenuIds;

class WPML_LS_Render extends WPML_SP_User {

	const THE_CONTENT_FILTER_PRIORITY = 100;

	private $current_template;

	private $templates;

	private $settings;

	private $model_build;

	private $inline_styles;

	private $assets;

	private $wpml_ls_exclude_in_menu;

	private $has_hierarchical_menu = false;

	private $is_preview = false;

	public function __construct( $templates, $settings, $model_build, $inline_styles, $sitepress ) {
		$this->templates     = $templates;
		$this->settings      = $settings;
		$this->model_build   = $model_build;
		$this->inline_styles = $inline_styles;
		$this->assets        = new WPML_LS_Assets( $this->templates, $this->settings );
		parent::__construct( $sitepress );
	}

	public function init_hooks() {
		if ( $this->sitepress->get_setting( 'setup_complete' ) ) {

			add_filter( 'wp_get_nav_menu_items', array( $this, 'wp_get_nav_menu_items_filter' ), 10, 2 );
			add_filter( 'wp_setup_nav_menu_item', array( $this, 'maybe_repair_menu_item' ), PHP_INT_MAX );
			add_filter( 'nav_menu_link_attributes', array( $this, 'add_menu_link_accessibility_attributes' ), 10, 4 );
			add_action( 'wp_footer', array( $this, 'add_menu_accessibility_script' ), 999 );

			add_filter( 'the_content', array( $this, 'the_content_filter' ), self::THE_CONTENT_FILTER_PRIORITY );
			if ( ! $this->is_widgets_page() ) {
				add_action( 'wp_footer', array( $this, 'wp_footer_action' ), 19 );
			}

			$this->assets->init_hooks();
		}
	}

	public function render( $slot ) {
		$html  = '';
		$model = array();

		if ( ! $this->is_hidden( $slot ) ) {
			list( $template_slug, $template ) = $this->resolve_template( $slot );

			$result = $this->render_with_template( $slot, $template_slug, $template, true );
			$html   = $result['html'];
			$model  = $result['model'];
		}

		return $this->filter_html( $html, $model, $slot );
	}

	private function render_with_template( $slot, $template_slug, $template, $can_fall_back ) {
		$html  = '';
		$model = array();

		$this->current_template = clone $template;

		$sandbox = false;
		if ( $slot->template_string() ) {
			$this->current_template->set_template_string( $slot->template_string() );
			$sandbox = true;
		}

		$model = $this->model_build->get( $slot, $this->current_template->get_template_data() );

		if ( $model['languages'] && ! $this->hides_when_nothing_to_switch( $model, $slot ) ) {
			$this->assets->maybe_late_enqueue_template( $template_slug );
			$model = $this->add_submenu_id( $model, $slot, $template_slug );
			$this->current_template->set_model( $model );

			try {
				$html = $this->current_template->get_html( $sandbox );
			} catch ( \Throwable $e ) {
				if ( $sandbox || WPML_LS_Template::ENGINE_PHP !== $this->current_template->get_engine() ) {
					throw $e;
				}

				$this->log_template_failure( $template_slug, $e );
				$fallback = $can_fall_back ? $this->get_fallback_template( $slot, $template_slug ) : null;

				if ( $fallback ) {
					return $this->render_with_template( $slot, $fallback[0], $fallback[1], false );
				}

				$html = '';
			}
		}

		return array(
			'html'  => $html,
			'model' => $model,
		);
	}

	private function resolve_template( $slot ) {
		$template_slug = $slot->template();
		$template      = $this->templates->get_template( $template_slug );

		if ( $template->is_missing() && ! $slot->template_string() ) {
			$fallback = $this->get_fallback_template( $slot, $template_slug );

			if ( $fallback ) {
				return $fallback;
			}
		}

		return array( $template_slug, $template );
	}

	private function get_fallback_template( $slot, $failed_slug ) {
		$fallback_slug = $this->get_core_template_slug_for( $slot );

		if ( ! $fallback_slug || $fallback_slug === $failed_slug || ! apply_filters( 'wpml_ls_fallback_to_core_template', true, $slot, $failed_slug ) ) {
			return null;
		}

		$template = $this->templates->get_template( $fallback_slug );

		return $template->is_missing() ? null : array( $fallback_slug, $template );
	}

	private function get_core_template_slug_for( $slot ) {
		$core_templates = $this->settings->get_core_templates();

		if ( $slot->is_menu() ) {
			$type = 'menu-item';
		} elseif ( $slot->is_footer() ) {
			$type = 'list-horizontal';
		} elseif ( $slot->is_post_translations() ) {
			$type = 'post-translations';
		} else {
			$type = 'dropdown';
		}

		return isset( $core_templates[ $type ] ) ? $core_templates[ $type ] : null;
	}

	private function log_template_failure( $template_slug, $e ) {
		( new WPML_WP_API() )->error_log(
			sprintf(
				'WPML: the language switcher template "%s" failed (%s: %s in %s:%d). The core template is shown instead.',
				$template_slug,
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);
	}

	private function add_submenu_id( array $model, $slot, $template_slug = null ) {
		$prefixes = [
			'wpml-legacy-dropdown'       => 'wpml-ls-submenu-',
			'wpml-legacy-dropdown-click' => 'wpml-ls-submenu-click-',
		];
		$template = null === $template_slug ? $slot->template() : $template_slug;

		if ( ! isset( $prefixes[ $template ] ) || $slot->template_string() ) {
			return $model;
		}

		$css_id = isset( $model['backward_compatibility']['css_id'] ) ? (string) $model['backward_compatibility']['css_id'] : '';

		$model['submenu_id'] = SubmenuIds::reserve( $prefixes[ $template ] . ( '' === $css_id ? 'default' : $css_id ) );

		return $model;
	}

	private function hides_when_nothing_to_switch( array $model, $slot ) {
		$nothing_to_switch_to = ! empty( $model['languages'] )
			&& isset( $model['has_alternatives'] )
			&& ! $model['has_alternatives'];

		if ( $this->is_preview || ! $nothing_to_switch_to ) {
			return false;
		}

		return (bool) apply_filters( 'wpml_ls_hide_when_nothing_to_switch', true, $model, $slot );
	}

	private function filter_html( $html, $model, $slot ) {
		return apply_filters( 'wpml_ls_html', $html, $model, $slot );
	}

	public function get_preview( $slot ) {
		$ret = [];

		$this->is_preview = true;
		try {
			if ( $slot->is_menu() ) {
				$ret['html'] = $this->render_menu_preview( $slot );
			} elseif ( $slot->is_post_translations() ) {
				$ret['html'] = $this->post_translations_label( $slot );
			} else {
				$ret['html'] = $this->render( $slot );
			}
		} finally {
			$this->is_preview = false;
		}

		$ret['css']    = $this->current_template->get_styles( true );
		$ret['js']     = $this->current_template->get_scripts( true );
		$ret['styles'] = $this->inline_styles->get_slots_inline_styles( [ $slot ] );

		return $ret;
	}

	public function wp_get_nav_menu_items_filter( $items, $menu ) {
		if ( $this->should_not_alter_menu() ) {
			return $items;
		}

		if ( $this->is_for_menu_panel_in_customizer() ) {
			return $items;
		}

		$slot = $this->settings->get_menu_settings_from_id( $menu->term_id );

		if ( $slot->is_enabled() && ! $this->is_hidden( $slot ) ) {
			$is_before  = 'before' === $slot->get( 'position_in_menu' );
			$lang_items = $this->get_menu_items( $slot );

			if ( $items && $lang_items ) {
				$items = $this->merge_menu_items( $items, $lang_items, $is_before );
			} elseif ( ! $items && $lang_items ) {
				$items = $lang_items;
			}
		}

		return $items;
	}

	private function is_for_menu_panel_in_customizer() {
		return is_customize_preview() && ! did_action( 'template_redirect' );
	}

	private function should_not_alter_menu() {
		if ( null === $this->wpml_ls_exclude_in_menu ) {
			$this->wpml_ls_exclude_in_menu = apply_filters( 'wpml_ls_exclude_in_menu', true );
		}

		return is_admin() && $this->wpml_ls_exclude_in_menu;
	}


	private function merge_menu_items( $items, $lang_items, $is_before ) {
		if ( $is_before ) {
			$items_to_prepend = $lang_items;
			$items_to_append  = $items;
		} else {
			$items_to_prepend = $items;
			$items_to_append  = $lang_items;
		}

		$menu_orders = wp_list_pluck( $items_to_prepend, 'menu_order' );
		$offset      = max( $menu_orders );

		foreach ( $items_to_append as $item ) {
			$item->menu_order = $item->menu_order + $offset;
		}

		return array_merge( $items_to_prepend, $items_to_append );
	}

	private function get_menu_items( $slot ) {
		$lang_items = array();
		$model      = $this->model_build->get( $slot );

		if ( $this->hides_when_nothing_to_switch( $model, $slot ) ) {
			return $lang_items;
		}

		if ( isset( $model['languages'] ) ) {
			list( $template_slug, $template ) = $this->resolve_template( $slot );
			$this->current_template           = $template;
			$can_fall_back                    = true;
			$menu_order                       = 1;

			foreach ( $model['languages'] as $language_model ) {
				if ( ! empty( $language_model['is_parent'] ) ) {
					$this->has_hierarchical_menu = true;
				}

				$this->current_template->set_model( $language_model );

				try {
					$html = $this->current_template->get_html();
				} catch ( \Throwable $e ) {
					if ( WPML_LS_Template::ENGINE_PHP !== $this->current_template->get_engine() ) {
						throw $e;
					}

					$this->log_template_failure( $template_slug, $e );
					$fallback = $can_fall_back ? $this->get_fallback_template( $slot, $template_slug ) : null;
					$html     = '';

					if ( $fallback ) {
						$can_fall_back          = false;
						list( $template_slug, ) = $fallback;
						$this->current_template = $fallback[1];
						$this->current_template->set_model( $language_model );
						$html = $this->current_template->get_html();
					}
				}

				if ( $template_slug !== $slot->template() ) {
					$this->assets->maybe_late_enqueue_template( $template_slug );
				}

				$item_content             = $this->filter_html( $html, $language_model, $slot );
				$ls_menu_item             = new WPML_LS_Menu_Item( $language_model, $item_content );
				$ls_menu_item->menu_order = $menu_order;
				$menu_order++;

				$lang_items[] = $ls_menu_item;
			}
		}

		return $lang_items;
	}

	public function maybe_repair_menu_item( $item ) {
		if ( $this->should_not_alter_menu() ) {
			return $item;
		}

		if ( ! $item instanceof WPML_LS_Menu_Item ) {
			return $item;
		}

		if ( ! $item->db_id ) {
			$item->db_id = $item->ID;
		}

		return $item;
	}

	private function render_menu_preview( $slot ) {
		$items    = $this->get_menu_items( $slot );
		$class    = $slot->get( 'is_hierarchical' ) ? 'wpml-ls-menu-hierarchical-preview' : 'wpml-ls-menu-flat-preview';
		$defaults = array(
			'before'         => '',
			'after'          => '',
			'link_before'    => '',
			'link_after'     => '',
			'theme_location' => '',
		);
		$defaults = (object) $defaults;
		$output   = walk_nav_menu_tree( $items, 2, $defaults );

		/* translators: Stand-in words shown in the preview of the language switcher where the menu items of the site would be. */
		$dummy_item = esc_html__( 'menu items', 'sitepress' );

		if ( $slot->get( 'position_in_menu' ) === 'before' ) {
			$output .= '<li class="dummy-menu-item"><a href="#">' . $dummy_item . '...</a></li>';
		} else {
			$output = '<li class="dummy-menu-item"><a href="#">...' . $dummy_item . '</a></li>' . $output;
		}

		return '<div><ul class="wpml-ls-menu-preview ' . $class . '">' . $output . '</ul></div>';
	}

	private function is_hidden( $slot ) {
		if ( ! function_exists( 'wpml_home_url_ls_hide_check' ) ) {
			require WPML_PLUGIN_PATH . '/inc/post-translation/wpml-root-page-actions.class.php';
		}

		return wpml_home_url_ls_hide_check() && ! $slot->is_shortcode_actions();
	}

	public function is_widgets_page() {
		global $pagenow;

		return is_admin() && 'widgets.php' === $pagenow;
	}

	public function the_content_filter( $content ) {
		$post_translations = '';
		$slot              = $this->settings->get_slot( 'statics', 'post_translations' );

		if ( $slot->is_enabled() && $this->sitepress->get_wp_api()->is_singular() ) {
			$post_translations = $this->post_translations_label( $slot );
		}

		if ( $post_translations ) {
			if ( $slot->get( 'display_before_content' ) ) {
				$content = $post_translations . $content;
			}

			if ( $slot->get( 'display_after_content' ) ) {
				$content = $content . $post_translations;
			}
		}

		return $content;
	}

	public function post_translations_label( $slot ) {
		$css_classes = $this->model_build->get_wrapper_css_classes( $slot );
		$html        = $this->render( $slot );
		if ( $html ) {
			$html = sprintf( $slot->get( 'availability_text' ), $html );
			$html = '<p class="' . $css_classes . '">' . $html . '</p>';

			$html = apply_filters( 'icl_post_alternative_languages', $html );

			$html = apply_filters( 'wpml_ls_post_alternative_languages', $html );
		}

		return $html;
	}

	public function wp_footer_action() {
		$slot = $this->settings->get_slot( 'statics', 'footer' );
		if ( $slot->is_enabled() ) {
			echo $this->render( $slot );
		}
	}

	public function add_menu_link_accessibility_attributes( $atts, $item, $args = null, $depth = 0 ) {
		if ( $item instanceof WPML_LS_Menu_Item ) {
			if ( isset( $item->aria_label ) ) {
				$atts['aria-label'] = $item->aria_label;
			}

			if ( ! $this->menu_walk_stops_at( $args, $depth ) ) {
				if ( isset( $item->link_role ) && ! empty( $item->link_role ) ) {
					$atts['role'] = $item->link_role;
				}

				if ( isset( $item->aria_expanded ) && ! empty( $item->aria_expanded ) ) {
					$atts['aria-expanded'] = $item->aria_expanded;
				}

				if ( isset( $item->aria_controls ) && ! empty( $item->aria_controls ) ) {
					$atts['aria-controls'] = $item->aria_controls;
				}
			}

			if ( isset( $item->classes ) && is_array( $item->classes ) ) {
				foreach ( $item->classes as $class ) {
					if ( strpos( $class, 'wpml-ls-current-language' ) !== false ) {
						$atts['aria-current'] = 'page';
						break;
					}
				}
			}
		}

		return $atts;
	}

	private function menu_walk_stops_at( $args, $depth ) {
		if ( ! is_object( $args ) || ! isset( $args->depth ) ) {
			return false;
		}

		$max_depth = (int) $args->depth;

		return $max_depth > 0 && (int) $depth + 1 >= $max_depth;
	}

	public function add_menu_accessibility_script() {
		if ( ! $this->has_hierarchical_menu_items() ) {
			return;
		}
		?>
		<style id="wpml-ls-menu-a11y-css">.wpml-ls-submenu-dismissed .wpml-ls-dismissible-submenu{display:none !important}</style>
		<script id="wpml-ls-menu-a11y-js">
		(function() {
			'use strict';
			var linkSelector = 'a[aria-controls^="wpml-ls-submenu-"]';
			var dismissed = 'wpml-ls-submenu-dismissed';
			var dismissible = 'wpml-ls-dismissible-submenu';

			function isEscape(e) {
				return e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27;
			}

			function isShown(submenu) {
				var style = window.getComputedStyle(submenu);
				if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
					return false;
				}
				var box = submenu.getBoundingClientRect();
				return box.width >= 2 && box.height >= 2 && box.right > 0 && box.left < document.documentElement.clientWidth;
			}

			function uniqueId(base, submenu) {
				var id = base, n = 2, element = document.getElementById(id);
				while (element && element !== submenu) {
					id = base + '-' + n;
					n++;
					element = document.getElementById(id);
				}
				return id;
			}

			function findSubmenu(li) {
				for (var child = li.firstElementChild; child; child = child.nextElementSibling) {
					if (child.tagName === 'UL') {
						return child;
					}
				}
				return li.querySelector('ul');
			}

			function setUp(li, link) {
				var submenu = findSubmenu(li);
				if (!submenu) {
					link.removeAttribute('aria-expanded');
					link.removeAttribute('aria-controls');
					link.removeAttribute('role');
					return;
				}
				if (!submenu.id) {
					submenu.id = uniqueId(link.getAttribute('aria-controls'), submenu);
				}
				link.setAttribute('aria-controls', submenu.id);
				// The style hides this list (also when it is not a direct child of the item).
				submenu.classList.add(dismissible);

				var shownAtPointerDown = null;
				var sync = function() {
					var expanded = isShown(submenu) ? 'true' : 'false';
					if (link.getAttribute('aria-expanded') !== expanded) {
						link.setAttribute('aria-expanded', expanded);
					}
					// A theme script may (re)name the submenu after this set-up.
					if (submenu.id && link.getAttribute('aria-controls') !== submenu.id) {
						link.setAttribute('aria-controls', submenu.id);
					}
				};
				// One pending measure per item: in the next frame, and 350 ms after the last event.
				var frame = 0, timer = 0;
				var syncSoon = function() {
					if (!frame) {
						frame = window.requestAnimationFrame(function() {
							frame = 0;
							sync();
						});
					}
					window.clearTimeout(timer);
					timer = window.setTimeout(function() {
						timer = 0;
						sync();
					}, 350);
				};
				var toggle = function(wasShown) {
					if (wasShown) {
						li.classList.add(dismissed);
					} else {
						li.classList.remove(dismissed);
						if (document.activeElement !== link) {
							link.focus();
						}
					}
					syncSoon();
				};

				link.addEventListener('pointerdown', function() {
					shownAtPointerDown = isShown(submenu);
				});
				link.addEventListener('click', function(e) {
					var handledByTheme = e.defaultPrevented;
					var wasShown = shownAtPointerDown === null ? isShown(submenu) : shownAtPointerDown;
					shownAtPointerDown = null;
					e.preventDefault();
					if (handledByTheme) {
						// The theme opens and closes this submenu: it decides, not an earlier dismiss.
						li.classList.remove(dismissed);
						syncSoon();
						return;
					}
					toggle(wasShown);
				});
				// Another control in the item (a theme's own arrow button) shows or hides the
				// submenu: an earlier dismiss must not keep it hidden.
				li.addEventListener('click', function(e) {
					if (!link.contains(e.target) && !submenu.contains(e.target)) {
						li.classList.remove(dismissed);
						syncSoon();
					}
				});
				link.addEventListener('keydown', function(e) {
					if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' || e.keyCode === 13 || e.keyCode === 32) {
						e.preventDefault();
						toggle(isShown(submenu));
					}
				});
				li.addEventListener('keydown', function(e) {
					if (isEscape(e) && isShown(submenu)) {
						e.preventDefault();
						li.classList.add(dismissed);
						link.focus();
						syncSoon();
					}
				});
				li.addEventListener('focusin', syncSoon);
				li.addEventListener('focusout', function(e) {
					if ((!e.relatedTarget || !li.contains(e.relatedTarget)) && !li.matches(':hover')) {
						li.classList.remove(dismissed);
					}
					syncSoon();
				});
				li.addEventListener('mouseenter', syncSoon);
				li.addEventListener('mouseleave', function() {
					if (!li.contains(document.activeElement)) {
						li.classList.remove(dismissed);
					}
					syncSoon();
				});
				submenu.addEventListener('transitionend', sync);
				if (window.MutationObserver) {
					new MutationObserver(syncSoon).observe(li, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-expanded', 'id'], subtree: true });
				}
				sync();
				syncSoon();
			}

			function init() {
				var links = document.querySelectorAll('li.wpml-ls-menu-item ' + linkSelector);
				for (var i = 0; i < links.length; i++) {
					var li = links[i].closest('li.wpml-ls-menu-item');
					if (li && !li.wpmlLsMenuA11y) {
						li.wpmlLsMenuA11y = true;
						setUp(li, links[i]);
					}
				}
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
		</script>
		<?php
	}

	private function has_hierarchical_menu_items() {
		return $this->has_hierarchical_menu;
	}
}