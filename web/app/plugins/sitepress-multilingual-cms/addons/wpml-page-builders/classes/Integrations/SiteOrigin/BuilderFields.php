<?php

namespace WPML\PB\SiteOrigin;

class BuilderFields {

	const WIDGETS_FIELD = 'widgets';
	const GRIDS_FIELD   = 'grids';

	public static function getWidgetLists( array $widget ): array {
		$widgetLists = [];

		self::mapWidgetLists(
			$widget,
			function ( array $widgets ) use ( &$widgetLists ) {
				$widgetLists[] = $widgets;

				return $widgets;
			}
		);

		return $widgetLists;
	}

	public static function mapWidgetLists( array $widget, callable $mapWidgets ): array {
		foreach ( $widget as $key => $value ) {
			if ( TranslatableNodes::SETTINGS_FIELD === $key || ! is_array( $value ) ) {
				continue;
			}

			if ( self::isLayout( $value ) ) {
				$value[ self::WIDGETS_FIELD ] = $mapWidgets( $value[ self::WIDGETS_FIELD ] );
			} else {
				$value = self::mapWidgetLists( $value, $mapWidgets );
			}

			$widget[ $key ] = $value;
		}

		return $widget;
	}

	private static function isLayout( array $value ): bool {
		return isset( $value[ self::WIDGETS_FIELD ], $value[ self::GRIDS_FIELD ] ) && is_array( $value[ self::WIDGETS_FIELD ] );
	}
}
