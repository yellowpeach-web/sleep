<?php

namespace WPML\ST\StringsScanning\JS;

use WPML\ST\Vendor\Peast\Peast;
use WPML\ST\Vendor\Peast\Syntax\Node\CallExpression;
use WPML\ST\Vendor\Peast\Syntax\Node\Node;
use WPML\ST\Vendor\Peast\Traverser;

class GettextCalls {

	const FUNCTIONS = [
		'__'  => [ 'text' => 0, 'domain' => 1 ],
		'_x'  => [ 'text' => 0, 'context' => 1, 'domain' => 2 ],
		'_n'  => [ 'text' => 0, 'plural' => 1, 'domain' => 3 ],
		'_nx' => [ 'text' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ],
	];

	public function find( $code ) {
		require_once WPML_ST_PATH . '/lib/StringScanning/autoload.php';

		$calls = [];
		$this->collect( (string) $code, null, false, $calls );

		return $calls;
	}

	private function collect( $code, $line, $withComments, array &$calls ) {
		$ast = Peast::latest(
			$code,
			[
				'sourceType' => Peast::SOURCE_TYPE_MODULE,
				'comments'   => $withComments,
				'jsx'        => true,
			]
		)->parse();

		$traverser = new Traverser();
		$traverser->addFunction(
			function ( $node ) use ( $line, &$calls ) {
				if ( ! $node instanceof Node || 'CallExpression' !== $this->type( $node ) ) {
					return;
				}

				$name = $this->calleeName( $node );

				if ( false === $name ) {
					return;
				}

				$nodeLine = null === $line ? $node->getLocation()->getStart()->getLine() : $line;

				if ( isset( self::FUNCTIONS[ $name ] ) ) {
					$call = $this->read( $node, self::FUNCTIONS[ $name ] );

					if ( $call ) {
						$calls[] = $call + [ 'line' => $nodeLine ];
					}
				}

				if ( 'eval' === $name ) {
					$evalCode = $this->evalCode( $node );

					if ( $evalCode ) {
						$this->collect( $evalCode, $node->getLocation()->getStart()->getLine(), true, $calls );
					}
				}
			}
		);
		$traverser->traverse( $ast );
	}

	private function read( CallExpression $node, array $positions ) {
		$args = [];

		foreach ( $node->getArguments() as $argument ) {
			$type = $this->type( $argument );

			if ( 'Identifier' === $type || 'Expression' === substr( $type, -strlen( 'Expression' ) ) ) {
				$args[] = '';
			} elseif ( 'Literal' === $type ) {
				$args[] = $argument->getValue();
			} elseif ( 'TemplateLiteral' === $type ) {
				if ( count( $argument->getExpressions() ) ) {
					return null;
				}

				$args[] = $argument->getQuasis()[0]->getValue();
			} else {
				return null;
			}
		}

		$get = static function ( $key ) use ( $args, $positions ) {
			return isset( $positions[ $key ], $args[ $positions[ $key ] ] ) ? (string) $args[ $positions[ $key ] ] : null;
		};

		$text = (string) $get( 'text' );

		if ( '' === $text ) {
			return null;
		}

		return [
			'text'    => $text,
			'plural'  => $get( 'plural' ),
			'context' => $get( 'context' ),
			'domain'  => $get( 'domain' ),
		];
	}

	private function evalCode( CallExpression $node ) {
		foreach ( $node->getArguments() as $argument ) {
			if ( 'Literal' === $this->type( $argument ) ) {
				return (string) $argument->getValue();
			}
		}

		return '';
	}

	private function calleeName( CallExpression $node ) {
		$callee = $node->getCallee();
		$type   = $this->type( $callee );

		if ( 'Identifier' === $type ) {
			return $callee->getName();
		}

		if ( 'MemberExpression' === $type ) {
			return $this->identifierName( $callee->getProperty() );
		}

		if ( 'CallExpression' === $type ) {
			$inner     = $callee->getCallee();
			$arguments = $callee->getArguments();

			if (
				'Identifier' !== $this->type( $inner )
				|| 'Object' !== $this->identifierName( $inner )
				|| [] === $arguments
				|| 'MemberExpression' !== $this->type( $arguments[0] )
			) {
				return false;
			}

			$member   = $arguments[0];
			$property = $member->getProperty();

			if ( 'Identifier' === $this->type( $property ) ) {
				return $this->identifierName( $property );
			}

			if ( 'Literal' === $this->type( $property ) ) {
				$comments = $property->getLeadingComments();

				if ( 1 === count( $comments ) && 'multiline' === $comments[0]->getKind() ) {
					return trim( $comments[0]->getText() );
				}

				return (string) $property->getValue();
			}

			return false;
		}

		if ( 'ParenthesizedExpression' === $type ) {
			$sequence = $callee->getExpression();

			if ( 'SequenceExpression' !== $this->type( $sequence ) || [] === $node->getArguments() ) {
				return false;
			}

			$expressions = $sequence->getExpressions();

			if ( 2 !== count( $expressions ) || 'Literal' !== $this->type( $expressions[0] ) ) {
				return false;
			}

			if ( 'Identifier' === $this->type( $expressions[1] ) ) {
				return $this->identifierName( $expressions[1] );
			}

			if ( 'MemberExpression' === $this->type( $expressions[1] ) ) {
				$indirect = $expressions[1];

				return $this->identifierName( $indirect->getProperty() );
			}
		}

		return false;
	}

	private function type( $node ) {
		return $node instanceof Node ? $node->getType() : '';
	}

	private function identifierName( $node ) {
		if ( 'Identifier' !== $this->type( $node ) ) {
			return false;
		}

		return $node->getName();
	}
}
