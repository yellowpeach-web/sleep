<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\PB\Elementor\Helper\DynamicTag;
use WPML\PB\Elementor\Helper\ElementTree;
use WPML\PB\Elementor\Helper\Template;

class EditorTemplateIds implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const LOOP_WIDGETS = [ 'loop-grid', 'loop-carousel' ];

	const TEMPLATE_WIDGETS = [ 'template' ];

	const TEMPLATE_POST_TYPE = 'elementor_library';

	const POPUP_ELEMENT_TYPES = [ 'widget', 'container' ];

	const POPUP_TAG_NAMES = [ 'popup' ];

	const POPUP_KEY = 'popup';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'elementor/document/load/data', [ $this, 'convertLoopTemplateIds' ], 10, 2 );
	}

	public function convertLoopTemplateIds( $data, $document = null ) {
		if ( ! is_array( $data ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return $data;
		}

		$language = $this->getTranslationLanguage( (int) $document->get_main_id() );

		if ( ! $language ) {
			return $data;
		}

		return ElementTree::map(
			$data,
			function ( array $element ) use ( $language ) {
				return $this->convertElement( $element, $language );
			}
		);
	}

	private function getTranslationLanguage( $postId ) {
		if ( ! $postId ) {
			return null;
		}

		$postType = get_post_type( $postId );

		if ( ! $postType ) {
			return null;
		}

		$originalId = (int) $this->sitepress->get_original_element_id( $postId, 'post_' . $postType );

		if ( ! $originalId || $originalId === $postId ) {
			return null;
		}

		$language         = $this->getPostLanguage( $postId );
		$originalLanguage = $this->getPostLanguage( $originalId );

		return $language && $language !== $originalLanguage ? $language : null;
	}

	private function getPostLanguage( $postId ) {
		$details = apply_filters( 'wpml_post_language_details', null, $postId );

		return is_array( $details ) && ! empty( $details['language_code'] ) ? $details['language_code'] : null;
	}

	private function convertElement( array $element, $language ) {
		return $this->convertPopupId(
			$this->convertTemplateWidget( $this->convertLoopElement( $element, $language ), $language ),
			$language
		);
	}

	private function convertTemplateWidget( array $element, $language ) {
		if (
			! isset( $element['widgetType'], $element['settings']['template_id'] )
			|| ! in_array( $element['widgetType'], self::TEMPLATE_WIDGETS, true )
			|| ! is_numeric( $element['settings']['template_id'] )
		) {
			return $element;
		}

		$element['settings']['template_id'] = $this->convertId( $element['settings']['template_id'], $language );

		return $element;
	}

	private function convertLoopElement( array $element, $language ) {
		if (
			! isset( $element['widgetType'], $element['settings'] )
			|| ! is_array( $element['settings'] )
			|| ! in_array( $element['widgetType'], self::LOOP_WIDGETS, true )
		) {
			return $element;
		}

		$settings = $element['settings'];

		if ( isset( $settings['template_id'] ) ) {
			$settings['template_id'] = $this->convertId( $settings['template_id'], $language );
		}

		if ( isset( $settings['alternate_templates'] ) && is_array( $settings['alternate_templates'] ) ) {
			foreach ( $settings['alternate_templates'] as $key => $row ) {
				if ( is_array( $row ) && isset( $row['template_id'] ) ) {
					$settings['alternate_templates'][ $key ]['template_id'] = $this->convertId( $row['template_id'], $language );
				}
			}
		}

		$element['settings'] = $settings;

		return $element;
	}

	private function convertPopupId( array $element, $language ) {
		if (
			! isset( $element['elType'], $element['settings']['__dynamic__']['link'] )
			|| ! in_array( $element['elType'], self::POPUP_ELEMENT_TYPES, true )
			|| ! is_string( $element['settings']['__dynamic__']['link'] )
		) {
			return $element;
		}

		$element['settings']['__dynamic__']['link'] = DynamicTag::mapSetting(
			$element['settings']['__dynamic__']['link'],
			self::POPUP_TAG_NAMES,
			self::POPUP_KEY,
			function ( $id ) use ( $language ) {
				return $this->convertId( $id, $language, null );
			}
		);

		return $element;
	}

	private function convertId( $id, $language, $postType = self::TEMPLATE_POST_TYPE ) {
		if ( ! is_numeric( $id ) || ! (int) $id ) {
			return $id;
		}

		if ( null === $postType ) {
			$postType = get_post_type( (int) $id ) ?: self::TEMPLATE_POST_TYPE;
		}

		$converted = apply_filters( 'wpml_object_id', (int) $id, $postType, true, $language );

		if ( ! $converted ) {
			return $id;
		}

		$converted = Template::fallBackWhenUnpublished( (int) $id, (int) $converted, $postType );

		return is_string( $id ) ? (string) $converted : (int) $converted;
	}
}
