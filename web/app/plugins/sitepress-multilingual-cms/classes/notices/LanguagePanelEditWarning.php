<?php

namespace WPML\Notices;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;

class LanguagePanelEditWarning implements \IWPML_REST_Action {

	const PLACEMENT_EDITOR = 'editor';

	private $notices;

	private $noticesFactory;

	public function __construct( ?callable $noticesFactory = null ) {
		$this->noticesFactory = $noticesFactory ?: function () {
			return ( new \WPML_TM_Post_Edit_Notices_Factory() )->create();
		};
	}

	public function add_hooks() {
		if ( \WPML\Plugins::isTMLoadedForRequest() ) {
			add_filter( 'wpml_post_language_panel_data', [ $this, 'addEditWarning' ], 10, 3 );
		}
	}

	public function addEditWarning( $data, $postId, array $context = [] ) {
		if ( ! is_array( $data ) || empty( $data['translatable'] ) || ! $this->isSiteEditorRead( $data, $context ) ) {
			return $data;
		}

		$warning = $this->notices()->get_edit_warning( (int) $postId, isset( $data['language'] ) && is_string( $data['language'] ) ? $data['language'] : null );
		if ( ! $warning ) {
			return $data;
		}

		$data['notices']   = isset( $data['notices'] ) && is_array( $data['notices'] ) ? $data['notices'] : [];
		$data['notices'][] = [
			'id'        => $warning['id'],
			'type'      => 'warning',
			'text'      => $warning['text'],
			'actions'   => $warning['actions'],
			'placement' => self::PLACEMENT_EDITOR,
		];

		return $data;
	}

	private function isSiteEditorRead( array $data, array $context ) {
		$postType = isset( $data['post_type'] ) && is_string( $data['post_type'] ) ? $data['post_type'] : '';
		$screen   = isset( $context['screen'] ) && is_string( $context['screen'] ) ? $context['screen'] : null;

		return SiteEditorEntityTypes::getsSiteEditorControls( $postType, $screen );
	}

	private function notices() {
		if ( ! $this->notices ) {
			$this->notices = call_user_func( $this->noticesFactory );
		}

		return $this->notices;
	}
}
