<?php

namespace WPML\PB\TranslationJob;

use WPML\PB\Elementor\TranslationJob\WidgetTitles as ElementorWidgetTitles;
use WPML\PB\Gutenberg\ConfigLabels;

class LabelResolver {

	private $translate;

	private $widgetTitles;

	private $configLabels;

	public function __construct( $translate = null, $widgetTitles = null, $configLabels = null ) {
		$this->translate    = $translate ?: [ self::class, 'translateLabel' ];
		$this->widgetTitles = null === $widgetTitles
			? [ 'elementor' => [ ElementorWidgetTitles::class, 'get' ] ]
			: $widgetTitles;
		$this->configLabels = $configLabels;
	}

	public function resolve( $storedTitle, $getPackageKind = null ) {
		list( $groups, $title ) = Groups::parseGroupLabel( $storedTitle );

		$groupNames = [];
		foreach ( $groups as $group ) {
			$groupNames[ $group ] = $group;
		}

		$source = $this->getSourceLabel( $groups, $title );
		if ( $source ) {
			list( $widget, $suffix ) = $source;
			list( $widgetName, $title ) = $this->translate( $widget, $title );

			$builderWidgetName = $this->getBuilderWidgetName( $widget, $getPackageKind );
			if ( null !== $builderWidgetName ) {
				$widgetName = $builderWidgetName;
			}

			$groupNames[ $groups[0] ] = $widgetName . $suffix;
		}

		$groupLabels = [];
		foreach ( $groupNames as $group => $name ) {
			$groupLabels[ $group ] = $name === $group ? $this->toHumanGroup( $group ) : Labels::convertToHuman( $name );
		}

		return [
			'title'  => $this->toHumanTitle( $title, $groups ),
			'groups' => $groupLabels,
		];
	}

	public function toHumanTitle( $title, array $groups ) {
		if ( $this->configLabels && $this->configLabels->isConfigLabel( $title, $groups ) ) {
			return $title;
		}

		return Labels::convertToHuman( $title );
	}

	public function toHumanGroup( $group ) {
		$blockLabel = $this->configLabels ? $this->configLabels->getBlockLabel( $group ) : null;

		return null !== $blockLabel ? $blockLabel : Labels::convertToHuman( $group );
	}

	private function getSourceLabel( array $groups, $title ) {
		if ( 1 !== count( $groups ) || ! preg_match( '/^(.+?)((?:-\d+)*)$/', $groups[0], $matches ) ) {
			return null;
		}

		if ( ! isset( KnownLabels::LABELS[ $matches[1] . Groups::LABEL_SEPARATOR . $title ] ) ) {
			return null;
		}

		return [ $matches[1], $matches[2] ];
	}

	private function translate( $widget, $field ) {
		$translated = (string) call_user_func( $this->translate, $widget . Groups::LABEL_SEPARATOR . $field );
		$parts      = explode( Groups::LABEL_SEPARATOR, $translated, 2 );

		if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) || '' === trim( $parts[1] ) ) {
			return [ $widget, $field ];
		}

		return $parts;
	}

	private function getBuilderWidgetName( $widget, $getPackageKind ) {
		if ( ! $getPackageKind || ! $this->widgetTitles ) {
			return null;
		}

		$kind = call_user_func( $getPackageKind );
		if ( ! is_string( $kind ) || ! isset( $this->widgetTitles[ $kind ] ) ) {
			return null;
		}

		$name = call_user_func( $this->widgetTitles[ $kind ], $widget );

		return is_string( $name ) && '' !== trim( $name ) ? $name : null;
	}

	public static function translateLabel( $label ) {
		return translate( $label, 'sitepress' );
	}
}
