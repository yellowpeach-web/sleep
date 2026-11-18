<?php

namespace WPML\TranslationPolicy\Policy;

class StoredListEditor implements ListEditorInterface {

	private $reader;

	private $answers;

	public function __construct( PolicyReaderInterface $reader ) {
		$this->reader = $reader;
	}

	public function usesNativeEditor( $postId, callable $listPostIds ): bool {
		$postId = (int) $postId;
		$filters = $this->reader->editorFilters();
		if ( null === $this->answers ) {
			$listIds       = array_merge( [ $postId ], (array) $listPostIds() );
			$this->answers = $this->reader->forMany( $listIds );
			$filters->prime( $listIds );
		}
		if ( ! isset( $this->answers[ $postId ] ) ) {
			$this->answers[ $postId ] = $this->reader->forPost( $postId );
		}

		$useTmEditor = ! \WPML_TM_Post_Edit_TM_Editor_Mode::is_translated_independently( $postId )
			&& PolicyTable::EDITOR_NATIVE !== $this->answers[ $postId ]->editor();
		$useTmEditor = $filters->useTmEditor( $useTmEditor, $postId );
		$blocked     = $filters->blockedPosts( [ $postId ] );

		return ! $useTmEditor || isset( $blocked[ $postId ] );
	}
}
