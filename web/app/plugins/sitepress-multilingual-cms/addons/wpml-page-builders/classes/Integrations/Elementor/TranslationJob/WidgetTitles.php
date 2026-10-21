<?php

namespace WPML\PB\Elementor\TranslationJob;

use WPML\PB\TranslationJob\KnownLabels;

class WidgetTitles {

	public static function get( $widget ) {
		$type = isset( KnownLabels::ELEMENTOR_WIDGET_TYPES[ $widget ] ) ? KnownLabels::ELEMENTOR_WIDGET_TYPES[ $widget ] : null;

		if ( ! $type || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}

		$widgetType = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $type );
		if ( ! is_object( $widgetType ) || ! method_exists( $widgetType, 'get_title' ) ) {
			return null;
		}

		$title = $widgetType->get_title();

		return is_string( $title ) && '' !== trim( $title ) ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : null;
	}
}
