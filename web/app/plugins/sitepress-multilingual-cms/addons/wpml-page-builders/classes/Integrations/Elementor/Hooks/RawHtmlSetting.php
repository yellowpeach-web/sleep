<?php

namespace WPML\PB\Elementor\Hooks;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlNodesSetting {

	const NODE = 'html';

	const CUSTOM_HTML_WIDGET = 'wp-widget-custom_html';

	const CUSTOM_HTML_CONTENT_FIELD = 'wp>content';

	protected function getNodesToTranslateFilter(): string {
		return 'wpml_elementor_widgets_to_translate';
	}

	protected function getRawHtmlNode(): string {
		return self::NODE;
	}

	protected function withoutRawHtmlNodes( array $nodes ): array {
		return $this->withoutCustomHtmlContent( parent::withoutRawHtmlNodes( $nodes ) );
	}

	private function withoutCustomHtmlContent( array $nodes ): array {
		$fields = $nodes[ self::CUSTOM_HTML_WIDGET ]['fields'] ?? null;

		if ( is_array( $fields ) ) {
			$nodes[ self::CUSTOM_HTML_WIDGET ]['fields'] = array_filter(
				$fields,
				function ( $field ) {
					return self::CUSTOM_HTML_CONTENT_FIELD !== ( $field['field'] ?? null );
				}
			);
		}

		return $nodes;
	}
}
