<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

use Elementor\Widget_Base;

class AtomicWidgetProcessor implements WidgetProcessorInterface {

	const TYPE_LINK           = 'link';
	const TYPE_INLINE_EDITING = 'inline-editing';

	const EDITOR_TYPE_MAP = [
		'text'                    => 'LINE',
		'textarea'                => 'AREA',
		self::TYPE_LINK           => 'LINK',
		self::TYPE_INLINE_EDITING => 'LINE',
	];

	public function canProcess( $widget ) {
		return method_exists( $widget, 'get_atomic_controls' );
	}

	public function process( $widget ) {
		if ( ! method_exists( $widget, 'get_title' ) || ! method_exists( $widget, 'get_atomic_controls' ) ) {
			return [];
		}

		$title    = $widget->get_title();
		$controls = $widget->get_atomic_controls();
		$config   = [];

		foreach ( $controls as $control ) {
			$config = $this->processControl( $config, $control, $title );
		}

		return $config;
	}

	private function processControl( $config, $section, $title ) {
		$items = $section->get_items();

		foreach ( $items as $control ) {
			if ( ! is_object( $control ) || ! method_exists( $control, 'get_type' ) || ! method_exists( $control, 'get_bind' ) ) {
				continue;
			}

			$type = $control->get_type();
			$name = $control->get_bind();

			if ( ! $this->isRegistrable( $name, $type ) ) {
				continue;
			}

			$editor = $this->getEditorType( $type );

			if ( ! $editor ) {
				continue;
			}

			if ( ! isset( $config['fields'] ) ) {
				$config['fields'] = [];
			}

			foreach ( $this->getFields( $name, $type ) as $field ) {
				$config['fields'][] = $field + [
					'type'        => $title . ': ' . $name,
					'editor_type' => $editor,
				];
			}
		}

		return $config;
	}

	private function getFields( $name, $type ) {
		if ( self::TYPE_LINK === $type ) {
			return [ [ 'field' => $name . '>value>destination>value' ] ];
		}

		if ( self::TYPE_INLINE_EDITING === $type ) {
			$htmlV3Path = $name . '>value>content>value';

			return [
				[ 'field' => $htmlV3Path ],
				[
					'field'    => $name . '>value',
					'field_id' => $htmlV3Path,
				],
			];
		}

		return [ [ 'field' => $name . '>value' ] ];
	}

	private function isRegistrable( $name, $type ) {
		if ( $this->isPrivate( $name ) ) {
			return false;
		}

		if ( ControlNameFilter::shouldExclude( $name ) ) {
			return false;
		}

		return $this->isTranslatable( $type );
	}

	private function isPrivate( $name ) {
		return 0 === strpos( $name, '_' );
	}

	private function isTranslatable( $type ) {
		return null !== $this->getEditorType( $type );
	}

	private function getEditorType( $type ) {
		return self::EDITOR_TYPE_MAP[ $type ] ?? null;
	}
}
