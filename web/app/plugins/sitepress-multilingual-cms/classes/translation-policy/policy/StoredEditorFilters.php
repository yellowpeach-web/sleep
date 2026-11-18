<?php

namespace WPML\TranslationPolicy\Policy;

class StoredEditorFilters implements EditorFiltersInterface {

	private $sql;

	private $log;

	private $locks = [];

	public function __construct( PolicySql $sql, LegacyFilterLog $log ) {
		$this->sql = $sql;
		$this->log = $log;
	}

	public function useTmEditor( $useTmEditor, $postId ) {
		$this->log->record( self::USE_TM_EDITOR );

		return apply_filters( self::USE_TM_EDITOR, $useTmEditor, $postId );
	}

	public function blockedPosts( $postIds ) {
		$this->log->record( self::EXCLUDE_POSTS );

		return apply_filters( self::EXCLUDE_POSTS, $this->lockedOnNativeEditor( is_array( $postIds ) ? $postIds : [] ), $postIds );
	}

	public function prime( array $postIds ) {
		$this->lockedOnNativeEditor( $postIds );
	}

	private function lockedOnNativeEditor( array $postIds ): array {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ), fn( $id ) => $id > 0 ) ) );
		$unread  = array_values( array_filter( $postIds, fn( $id ) => ! array_key_exists( $id, $this->locks ) ) );

		$winners = [];
		foreach ( $unread ? $this->sql->editorLocksForPosts( $unread ) : [] as $row ) {
			$postId = (int) $row['id'];
			if ( ! isset( $winners[ $postId ] ) && Precedence::offersEditor( $row['editor'], (string) $row['post_type'] ) ) {
				$winners[ $postId ] = $row;
			}
		}

		foreach ( $unread as $postId ) {
			$row                    = $winners[ $postId ] ?? null;
			$this->locks[ $postId ] = $row && PolicyTable::EDITOR_NATIVE === $row['editor']
				? esc_html( Constraints::label( (string) $row['source'], (string) $row['reason'], $postId ) )
				: null;
		}

		$blocked = [];
		foreach ( $postIds as $postId ) {
			if ( null !== $this->locks[ $postId ] ) {
				$blocked[ $postId ] = $this->locks[ $postId ];
			}
		}

		return $blocked;
	}
}
