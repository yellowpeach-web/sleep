<?php

namespace WPML\PB\Cornerstone\Media\Modules;

class CardNode extends \WPML_Cornerstone_Media_Node_With_URLs {

	const FACES = [ 'card_front', 'card_back' ];

	protected function get_keys() {
		$keys = GraphicNode::getImageKeys( GraphicNode::PREFIXES_BY_TYPE['button'] );

		foreach ( self::FACES as $face ) {
			$keys = array_merge( $keys, GraphicNode::getImageKeys( self::getFaceHeadlinePrefixes( $face ) ) );

			foreach ( BackgroundNode::KEYS as $backgroundKey ) {
				$keys[] = $face . '_' . $backgroundKey;
			}
		}

		return $keys;
	}

	private static function getFaceHeadlinePrefixes( string $face ): array {
		return array_map(
			fn( string $headlinePrefix ): string => $face . '_' . $headlinePrefix,
			GraphicNode::PREFIXES_BY_TYPE['headline']
		);
	}
}
