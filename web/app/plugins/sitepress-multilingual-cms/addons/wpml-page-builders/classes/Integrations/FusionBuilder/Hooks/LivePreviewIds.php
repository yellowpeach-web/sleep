<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\FP\Obj;
use WPML\PB\Request\Ajax;
use WPML\PB\Shortcode\AdjustIdsHooks;

class LivePreviewIds implements \IWPML_AJAX_Action, \IWPML_DIC_Action {

	private const LIVE_RENDER_ACTION = 'get_shortcode_render';

	private const LIVE_QUERY_ACTION_PREFIX = 'get_';

	private const PRIORITY_BEFORE_AVADA_QUERY = 0;

	private $adjustIdsHooks;

	public function __construct( AdjustIdsHooks $adjustIdsHooks ) {
		$this->adjustIdsHooks = $adjustIdsHooks;
	}

	public function add_hooks() {
		$action = Obj::prop( 'action', $_POST );

		if ( ! is_string( $action ) ) {
			return;
		}

		if ( self::LIVE_RENDER_ACTION === $action ) {
			$this->adjustIdsHooks->add_hooks();
		} elseif ( $this->isLiveQueryRequest( $action ) ) {
			$tag = substr( $action, strlen( self::LIVE_QUERY_ACTION_PREFIX ) );

			if ( $this->adjustIdsHooks->hasIdAttributes( $tag ) ) {
				Ajax::listen(
					$action,
					fn() => $this->convertParams( $tag ),
					'Avada Live query of an element: converts the ID params of the element to the current language; Avada checks its fusion_load_nonce',
					self::PRIORITY_BEFORE_AVADA_QUERY,
					0
				);
			}
		}
	}

	private function isLiveQueryRequest( $action ) {
		return 0 === strpos( $action, self::LIVE_QUERY_ACTION_PREFIX ) && isset( $_POST['fusion_load_nonce'] );
	}

	private function convertParams( $tag ) {
		if ( ! isset( $_POST['model']['params'] ) || ! is_array( $_POST['model']['params'] ) ) {
			return;
		}

		foreach ( $_POST['model']['params'] as $paramName => $paramValue ) {
			if ( $this->canHoldIds( $paramName, $paramValue ) ) {
				$_POST['model']['params'][ $paramName ] = $this->convertParam( $paramName, $paramValue, $tag );
			}
		}
	}

	private function canHoldIds( $paramName, $paramValue ) {
		return is_string( $paramName )
			&& is_string( $paramValue )
			&& '' !== $paramValue
			&& false === strpbrk( $paramValue, '"\'' );
	}

	private function convertParam( string $paramName, string $paramValue, string $tag ): string {
		$attribute = $this->adjustIdsHooks->convertIdAttributes( $paramName . '="' . $paramValue . '"', $tag );

		return substr( $attribute, strlen( $paramName ) + 2, -1 );
	}
}
