<?php

namespace WPML\PB\Gutenberg;

use WPML\EditHistory\Removals;
use WPML\FP\Obj;

class DeliveryRemovalHooks implements Integration {

	const MARK = 'wpmlremovedunit';

	private $stringsInBlock;

	private $stringName;

	public function __construct( StringsInBlock\Collection $stringsInBlock, callable $stringName ) {
		$this->stringsInBlock = $stringsInBlock;
		$this->stringName     = $stringName;
	}

	public function add_hooks() {
		add_filter( 'wpml_pb_gutenberg_blocks_to_translate', [ $this, 'remove' ], 10, 4 );
	}

	public function remove( $blocks, $translatedId, $lang, $original ) {
		if ( ! is_array( $blocks ) || ! $original instanceof \WP_Post ) {
			return $blocks;
		}

		$units = Removals::unitsFor( (int) $original->ID, (string) $lang );
		if ( null === $units ) {
			return $blocks;
		}

		$dropped = [];
		$names   = $this->names( array_keys( $units ) );
		if ( $names ) {
			$uids   = (array) apply_filters( 'wpml_pb_block_uids', [], $original->ID, $original->post_content );
			$blocks = array_values( $this->walk( $blocks, '', $names, $uids, (string) $lang, $dropped ) );
		}
		Removals::rebuilt( (int) $original->ID, (string) $lang, $dropped );

		return $blocks;
	}

	private function names( array $units ): array {
		$names = [];
		foreach ( $units as $unit ) {
			if ( preg_match( '/^package-string-\d+-(\d+)$/', (string) $unit, $match ) ) {
				$name = ( $this->stringName )( (int) $match[1] );
				if ( $name ) {
					$names[ (string) $name ] = true;
				}
			}
		}

		return $names;
	}

	private function walk( array $blocks, string $parent, array $names, array $uids, string $lang, array &$dropped ): array {
		$kept     = [];
		$skipNext = false;
		foreach ( array_values( $blocks ) as $position => $block ) {
			if ( self::isBlank( $block ) ) {
				if ( ! $skipNext ) {
					$kept[ $position ] = $block;
				}
				$skipNext = false;
				continue;
			}
			$skipNext = false;

			$path    = '' === $parent ? (string) $position : $parent . '.' . $position;
			$block   = \WPML_Gutenberg_Integration::sanitize_block( $block );
			$own     = array_values( array_map( fn( $string ) => (string) Obj::prop( 'id', $string ), $this->stringsInBlock->find( $block ) ) );
			$removed = array_values( array_filter( $own, fn( string $name ) => isset( $names[ $name ] ) ) );
			$inner   = (array) $block->innerBlocks;
			$left    = $inner ? $this->walk( $inner, $path, $names, $uids, $lang, $dropped ) : [];
			$emptied = $inner && ! $left;

			if ( ( $own && count( $removed ) === count( $own ) && ( ! $inner || $emptied ) ) || ( ! $own && $emptied ) ) {
				$dropped[] = self::element( $block, $path, $removed, $uids );
				$skipNext  = '' === $parent;
				continue;
			}

			if ( $inner ) {
				$block = self::withInnerBlocks( $block, $left );
			}
			if ( $removed ) {
				$block     = $this->withoutStrings( $block, $removed, $lang );
				$dropped[] = self::element( $block, $path, $removed, $uids ) + [ 'block_stays' => true ];
			}
			$kept[ $position ] = $block;
		}

		return $kept;
	}

	private function withoutStrings( \WP_Block_Parser_Block $block, array $removed, string $lang ): \WP_Block_Parser_Block {
		$marks = [];
		foreach ( $removed as $name ) {
			$marks[ $name ] = [
				$lang => [
					'value'  => self::MARK,
					'status' => ICL_TM_COMPLETE,
				],
			];
		}

		$block               = $this->stringsInBlock->update( $block, $marks, $lang );
		$block->innerHTML    = self::unmark( (string) $block->innerHTML );
		$block->innerContent = array_map( fn( $part ) => is_string( $part ) ? self::unmark( $part ) : $part, (array) $block->innerContent );
		$block->attrs        = self::unmarkAll( (array) $block->attrs );

		return $block;
	}

	private static function unmark( string $html ): string {
		$html = (string) preg_replace( '#<([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*>\s*' . self::MARK . '\s*</\1>#', '', $html );

		return str_replace( self::MARK, '', $html );
	}

	private static function unmarkAll( array $attrs ): array {
		foreach ( $attrs as $key => $value ) {
			if ( is_array( $value ) ) {
				$attrs[ $key ] = self::unmarkAll( $value );
			} elseif ( is_string( $value ) && false !== strpos( $value, self::MARK ) ) {
				$attrs[ $key ] = self::unmark( $value );
			}
		}

		return $attrs;
	}

	private static function withInnerBlocks( \WP_Block_Parser_Block $block, array $left ): \WP_Block_Parser_Block {
		$content = [];
		$child   = 0;
		foreach ( (array) $block->innerContent as $part ) {
			if ( null === $part && ! isset( $left[ $child++ ] ) ) {
				continue;
			}
			$content[] = $part;
		}

		$block->innerBlocks  = array_values( $left );
		$block->innerContent = $content;

		return $block;
	}

	private static function element( \WP_Block_Parser_Block $block, string $path, array $removed, array $uids ): array {
		$element = [
			'block'   => (string) $block->blockName,
			'path'    => $path,
			'strings' => $removed,
		];
		$uid     = (string) ( $uids[ $path ] ?? '' );

		return '' === $uid ? $element : $element + [ 'uid' => $uid ];
	}

	private static function isBlank( $block ): bool {
		return '' === (string) Obj::propOr( '', 'blockName', $block )
			&& ! Obj::propOr( [], 'innerBlocks', $block )
			&& '' === trim( (string) Obj::propOr( '', 'innerHTML', $block ) );
	}
}
