<?php

namespace WPML\LanguageSwitcher;

class CustomTemplates {

	private $settings;

	private $templates;

	public function __construct( \WPML_LS_Settings $settings, \WPML_LS_Templates $templates ) {
		$this->settings  = $settings;
		$this->templates = $templates;
	}

	public function get( $engine = null ) {
		return $this->collect( $this->templates->get_templates(), $engine );
	}

	public function getUploads() {
		return $this->collect( $this->templates->get_uploads_templates(), null );
	}

	private function collect( $registered, $engine ) {
		$found = [];

		foreach ( is_array( $registered ) ? $registered : [] as $slug => $template ) {
			if ( $template->is_missing() || $template->is_core() || ( null !== $engine && $engine !== $template->get_engine() ) ) {
				continue;
			}

			$data           = $template->get_template_data();
			$found[ $slug ] = [
				'name'       => isset( $data['name'] ) ? (string) $data['name'] : (string) $slug,
				'slug'       => (string) $slug,
				'engine'     => $template->get_engine(),
				'folder'     => self::folder( $data ),
				'in_uploads' => isset( $data['in_uploads'] ) ? (bool) $data['in_uploads'] : ( isset( $data['php_allowed'] ) && ! $data['php_allowed'] ),
				'used_by'    => [],
			];
		}

		if ( ! $found ) {
			return [];
		}

		foreach ( $this->settings->get_active_slots() as $slot ) {
			if ( ! $slot->template_string() && isset( $found[ $slot->template() ] ) ) {
				$found[ $slot->template() ]['used_by'][] = $this->slot_label( $slot );
			}
		}

		ksort( $found );

		return array_values( $found );
	}

	private function slot_label( $slot ) {
		if ( $slot->is_footer() ) {
			/* translators: Name of a language switcher in a list: the one shown in the footer of the site. */
			return __( 'Footer language switcher', 'sitepress' );
		}

		if ( $slot->is_post_translations() ) {
			/* translators: Name of a language switcher in a list: the links to the other languages of a post, shown above or below its content. */
			return __( 'Post translations links', 'sitepress' );
		}

		if ( $slot->is_shortcode_actions() ) {
			/* translators: Name of a language switcher in a list: the one printed by the language switcher shortcode and the PHP action. */
			return __( 'Shortcode and PHP action language switcher', 'sitepress' );
		}

		if ( $slot->is_sidebar() ) {
			$sidebars = $this->settings->get_registered_sidebars();
			$name     = isset( $sidebars[ $slot->slug() ]['name'] ) ? $sidebars[ $slot->slug() ]['name'] : $slot->slug();

			/* translators: Name of a language switcher in a list: the one in a widget area. %s: the name of the widget area. */
			return sprintf( __( 'Widget area language switcher: %s', 'sitepress' ), $name );
		}

		$menus = $this->settings->get_available_menus();
		$name  = isset( $menus[ $slot->slug() ]->name ) ? $menus[ $slot->slug() ]->name : '#' . $slot->slug();

		/* translators: Name of a language switcher in a list: the one in a menu. %s: the name of the menu. */
		return sprintf( __( 'Menu language switcher: %s', 'sitepress' ), $name );
	}

	private static function folder( array $template_data ) {
		$paths  = isset( $template_data['path'] ) ? (array) $template_data['path'] : [];
		$folder = (string) end( $paths );
		$root   = defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/\\' ) . '/' : '';

		return '' !== $root && 0 === strpos( $folder, $root ) ? substr( $folder, strlen( $root ) ) : $folder;
	}
}
