<?php

namespace WPML\PB\Gutenberg;

use WPML\FP\Obj;

class NativeEditorWriteBack implements Integration {

	const PRIORITY = 90;

	private $pb_integration;

	private $strings_in_block;

	private $sitepress;

	private $job_fields;

	private $string_translations;

	private $content_before = [];

	public function __construct(
		\WPML_PB_Integration $pb_integration,
		StringsInBlock\Collection $strings_in_block,
		\SitePress $sitepress,
		NativeEditorJobFields $job_fields,
		NativeEditorStringTranslations $string_translations
	) {
		$this->pb_integration      = $pb_integration;
		$this->strings_in_block    = $strings_in_block;
		$this->sitepress           = $sitepress;
		$this->job_fields          = $job_fields;
		$this->string_translations = $string_translations;
	}

	public function add_hooks() {
		add_action( 'post_updated', [ $this, 'rememberContentBefore' ], 10, 3 );
		add_action( 'save_post', [ $this, 'writeBackTranslationEdits' ], self::PRIORITY, 2 );
	}

	public function rememberContentBefore( $post_id, $post_after, $post_before ) {
		if ( $post_before instanceof \WP_Post ) {
			$this->content_before[ (int) $post_id ] = (string) $post_before->post_content;
		}
	}

	public function writeBackTranslationEdits( $translated_post_id, $translated_post ) {
		if ( ! $translated_post instanceof \WP_Post ) {
			return;
		}

		if ( ! $this->pb_integration->is_editing_translation_with_native_editor( (int) $translated_post_id ) ) {
			return;
		}

		$original_post = $this->getOriginalPost( $translated_post );
		if ( ! $original_post ) {
			return;
		}

		$language = $this->sitepress->get_language_for_element( (int) $translated_post_id, 'post_' . $translated_post->post_type );
		if ( ! $language ) {
			return;
		}

		$pairs = $this->pairBlocks(
			$original_post,
			$this->collectBlocks( $original_post->post_content ),
			$translated_post,
			$this->collectBlocks( $translated_post->post_content )
		);

		if ( $pairs ) {
			$this->writeTranslations( $translated_post, $original_post, $pairs, $language );
		}
	}

	private function getOriginalPost( \WP_Post $translated_post ) {
		$original_id = (int) apply_filters(
			'wpml_original_element_id',
			null,
			$translated_post->ID,
			'post_' . $translated_post->post_type
		);

		if ( ! $original_id || $original_id === (int) $translated_post->ID ) {
			return null;
		}

		$original_post = get_post( $original_id );

		return $original_post instanceof \WP_Post ? $original_post : null;
	}

	private function collectBlocks( $content ) {
		return $this->collectFromBlocks( \WPML_Gutenberg_Integration::parse_blocks( (string) $content ), '' );
	}

	private function collectFromBlocks( array $blocks, $parent_path ) {
		$collected = [];

		foreach ( $blocks as $position => $block ) {
			if (
				'' === (string) Obj::propOr( '', 'blockName', $block )
				&& ! Obj::propOr( [], 'innerBlocks', $block )
				&& '' === trim( (string) Obj::propOr( '', 'innerHTML', $block ) )
			) {
				continue;
			}

			$block = \WPML_Gutenberg_Integration::sanitize_block( $block );
			$path  = '' === $parent_path ? (string) $position : $parent_path . '.' . $position;
			$name  = (string) Obj::propOr( '', 'blockName', $block );

			$collected[] = [
				'path'    => $path,
				'block'   => $name,
				'strings' => array_values( (array) $this->strings_in_block->find( $block ) ),
			];

			if ( ! empty( $block->innerBlocks ) ) {
				$collected = array_merge( $collected, $this->collectFromBlocks( $block->innerBlocks, $path ) );
			}
		}

		return $collected;
	}

	private function pairBlocks( \WP_Post $original_post, array $original, \WP_Post $translated_post, array $translated ) {
		if ( apply_filters( 'wpml_pb_block_uids_healed', false, (int) $translated_post->ID ) ) {
			return $this->pairByPosition( $original, $translated );
		}

		$original_uids   = $this->getBlockUids( $original_post );
		$translated_uids = $this->getBlockUids( $translated_post );

		if ( $original_uids && $translated_uids ) {
			return $this->pairByUid( $original, $original_uids, $translated, $translated_uids );
		}

		return $this->pairByPosition( $original, $translated );
	}

	private function getBlockUids( \WP_Post $post ) {
		$uids = apply_filters( 'wpml_pb_block_uids', [], (int) $post->ID, (string) $post->post_content );

		return is_array( $uids ) ? $uids : [];
	}

	private function pairByUid( array $original, array $original_uids, array $translated, array $translated_uids ) {
		$original_by_uid   = $this->indexByUid( $original, $original_uids );
		$translated_by_uid = $this->indexByUid( $translated, $translated_uids );

		$original_sequence   = array_values( array_intersect( array_keys( $original_by_uid ), array_keys( $translated_by_uid ) ) );
		$translated_sequence = array_values( array_intersect( array_keys( $translated_by_uid ), array_keys( $original_by_uid ) ) );

		$pairs = [];

		foreach ( $this->inEveryLongestCommonOrder( $original_sequence, $translated_sequence ) as $uid ) {
			if ( $this->sameShape( $original_by_uid[ $uid ], $translated_by_uid[ $uid ] ) ) {
				$pairs[] = [ $original_by_uid[ $uid ], $translated_by_uid[ $uid ] ];
			}
		}

		return $pairs;
	}

	private function indexByUid( array $entries, array $uids ) {
		$indexed = [];
		$shared  = [];

		foreach ( $entries as $entry ) {
			$uid = isset( $uids[ $entry['path'] ] ) ? $uids[ $entry['path'] ] : '';

			if ( ! is_string( $uid ) || '' === $uid ) {
				continue;
			}

			if ( isset( $indexed[ $uid ] ) ) {
				$shared[ $uid ] = true;
			}

			$indexed[ $uid ] = $entry;
		}

		foreach ( array_keys( $shared ) as $uid ) {
			unset( $indexed[ $uid ] );
		}

		return $indexed;
	}

	private function inEveryLongestCommonOrder( array $original, array $translated ) {
		$position = array_flip( array_map( 'strval', $translated ) );
		$at       = [];

		foreach ( $original as $uid ) {
			$at[] = $position[ (string) $uid ];
		}

		$count  = count( $at );
		$ending = array_fill( 0, $count, 1 );
		$from   = array_fill( 0, $count, 1 );

		for ( $k = 0; $k < $count; $k++ ) {
			for ( $i = 0; $i < $k; $i++ ) {
				if ( $at[ $i ] < $at[ $k ] ) {
					$ending[ $k ] = max( $ending[ $k ], $ending[ $i ] + 1 );
				}
			}
		}

		for ( $k = $count - 1; $k >= 0; $k-- ) {
			for ( $i = $count - 1; $i > $k; $i-- ) {
				if ( $at[ $i ] > $at[ $k ] ) {
					$from[ $k ] = max( $from[ $k ], $from[ $i ] + 1 );
				}
			}
		}

		$longest = $count ? max( $ending ) : 0;
		$on_run  = [];

		for ( $k = 0; $k < $count; $k++ ) {
			if ( $ending[ $k ] + $from[ $k ] - 1 === $longest ) {
				$on_run[ $ending[ $k ] ][] = $k;
			}
		}

		$kept = [];

		foreach ( $on_run as $step ) {
			if ( 1 === count( $step ) ) {
				$kept[ $step[0] ] = $original[ $step[0] ];
			}
		}

		ksort( $kept );

		return array_values( $kept );
	}

	private function pairByPosition( array $original, array $translated ) {
		if ( count( $original ) !== count( $translated ) ) {
			return [];
		}

		$pairs = [];

		foreach ( $original as $index => $entry ) {
			if ( ! $this->sameShape( $entry, $translated[ $index ] ) ) {
				return [];
			}

			$pairs[] = [ $entry, $translated[ $index ] ];
		}

		return $pairs;
	}

	private function sameShape( array $original, array $translated ) {
		return $original['block'] === $translated['block']
			&& count( $original['strings'] ) === count( $translated['strings'] );
	}

	private function writeTranslations( \WP_Post $translated_post, \WP_Post $original_post, array $pairs, $language ) {
		$package = $this->getGutenbergPackage( $original_post );
		if ( ! $package ) {
			return;
		}

		$candidates = $this->collectCandidates( $pairs, $package, $this->getRowsByName( $package ) );
		if ( ! $candidates ) {
			return;
		}

		$complete = [];

		foreach ( $this->string_translations->get( array_keys( $candidates ), $language ) as $string_id => $translation ) {
			if ( ICL_TM_COMPLETE === (int) $translation['status'] && null !== $translation['value'] ) {
				$complete[ (int) $string_id ] = (string) $translation['value'];
			}
		}

		$before     = $this->getTextsBeforeThisSave( $translated_post );
		$package_id = (int) Obj::propOr( 0, 'ID', $package );
		$job_values = [];

		foreach ( $candidates as $string_id => $candidate ) {
			$value = $this->pickEditedValue( $string_id, $candidate, $complete, $before );

			if ( null === $value ) {
				continue;
			}

			$this->pb_integration->without_resave(
				function () use ( $string_id, $language, $value ) {
					do_action( 'wpml_add_string_translation', $string_id, $language, $value, ICL_TM_COMPLETE );
				}
			);

			$field_type                = \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package_id, $string_id );
			$job_values[ $field_type ] = $value;
		}

		if ( $job_values ) {
			$this->job_fields->refresh(
				(int) $translated_post->ID,
				'post_' . $translated_post->post_type,
				$language,
				$job_values
			);
		}
	}

	private function getGutenbergPackage( \WP_Post $original_post ) {
		$packages = apply_filters( 'wpml_st_get_post_string_packages', [], $original_post->ID );

		foreach ( (array) $packages as $package ) {
			if (
				$package instanceof \WPML_Package
				&& \WPML_Gutenberg_Integration::PACKAGE_ID === Obj::prop( 'kind', $package )
			) {
				return $package;
			}
		}

		return null;
	}

	private function getRowsByName( \WPML_Package $package ) {
		$rows = [];

		foreach ( (array) $package->get_package_strings() as $row ) {
			$name = (string) Obj::propOr( '', 'name', $row );

			if ( '' !== $name ) {
				$rows[ $name ] = $row;
			}
		}

		return $rows;
	}

	private function collectCandidates( array $pairs, \WPML_Package $package, array $rows ) {
		$candidates = [];

		foreach ( $pairs as $pair ) {
			list( $original, $translated ) = $pair;

			foreach ( $original['strings'] as $position => $original_string ) {
				$name = $package->sanitize_string_name( (string) Obj::propOr( '', 'id', $original_string ) );
				$row  = isset( $rows[ $name ] ) ? $rows[ $name ] : null;

				$string_id = (int) Obj::propOr( 0, 'id', $row );
				$value     = (string) Obj::propOr( '', 'value', $translated['strings'][ $position ] );

				if ( ! $string_id || '' === $value ) {
					continue;
				}

				if ( ! isset( $candidates[ $string_id ] ) ) {
					$candidates[ $string_id ] = [
						'original' => (string) Obj::propOr( '', 'value', $row ),
						'values'   => [],
					];
				}

				$candidates[ $string_id ]['values'][] = $value;
			}
		}

		return $candidates;
	}

	private function getTextsBeforeThisSave( \WP_Post $translated_post ) {
		if ( ! isset( $this->content_before[ (int) $translated_post->ID ] ) ) {
			return null;
		}

		$before = [];

		foreach ( $this->collectBlocks( $this->content_before[ (int) $translated_post->ID ] ) as $entry ) {
			foreach ( $entry['strings'] as $string ) {
				$before[ (string) Obj::propOr( '', 'value', $string ) ] = true;
			}
		}

		return $before;
	}

	private function pickEditedValue( $string_id, array $candidate, array $complete, $before ) {
		$current = isset( $complete[ $string_id ] ) ? $complete[ $string_id ] : null;
		$others  = array_diff_key( $complete, [ $string_id => true ] );
		$edited  = [];

		$values = array_unique( $candidate['values'] );

		$shared = count( $values ) > 1;

		foreach ( $values as $value ) {
			if ( $shared && ( null === $before || isset( $before[ $value ] ) ) ) {
				continue;
			}

			if ( null !== $current ) {
				if ( $value === $current ) {
					continue;
				}
			} elseif ( null === $before || isset( $before[ $value ] ) || $value === $candidate['original'] ) {
				continue;
			}

			if ( in_array( $value, $others, true ) ) {
				continue;
			}

			$edited[] = $value;
		}

		return 1 === count( $edited ) ? $edited[0] : null;
	}
}
