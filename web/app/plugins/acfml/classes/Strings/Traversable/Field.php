<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Config;
use ACFML\Strings\Transformer\Transformer;

class Field extends Entity {

	protected function getConfig() {
		return Config::getForField();
	}

	protected function transform( Transformer $transformer, $value, $config ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $label ) {
				if ( is_string( $label ) ) {
					$value[ $key ] = $transformer->transform( $label, $config );
				}
			}
		} else {
			$value = $transformer->transform( $value, $config );
		}

		return $value;
	}
}
