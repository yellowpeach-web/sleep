<?php

namespace WPML\TM\PostEditScreen;

use IWPML_Backend_Action;
use WP_Post;
use WPML_Super_Globals_Validation;
use WPML\Core\Component\Translation\Domain\PendingTranslationGroup;
use WPML\Infrastructure\WordPress\Component\Translation\Application\Repository\PendingTranslationGroupRepository;
use WPML\Core\Component\Translation\Application\Repository\PendingTranslationGroupRepositoryInterface;

class PendingTranslationGroupOnLoad implements IWPML_Backend_Action {

	private $request;

	private $repository;

	public function __construct(
		?WPML_Super_Globals_Validation $request = null,
		?PendingTranslationGroupRepositoryInterface $repository = null
	) {
		$this->request    = $request ?: new WPML_Super_Globals_Validation();
		$this->repository = $repository ?: new PendingTranslationGroupRepository();
	}

	public function add_hooks() {
		add_action( 'load-post-new.php', [ $this, 'arm' ] );
	}

	public function arm() {
		add_action( 'add_meta_boxes', [ $this, 'remember' ], 1, 2 );
	}

	public function remember( $post_type, $post = null ) {
		$group = $this->requested_group();

		if (
			! $group
			|| ! $post instanceof WP_Post
			|| empty( $post->ID )
			|| 'auto-draft' !== $post->post_status
			|| (int) $post->post_author !== (int) get_current_user_id()
		) {
			return;
		}

		$this->repository->remember( (int) $post->ID, $group );
	}

	private function requested_group() {
		$trid        = (int) $this->request->get( 'trid', FILTER_SANITIZE_NUMBER_INT );
		$source_lang = (string) $this->request->get( 'source_lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $trid <= 0 || '' === $source_lang ) {
			return null;
		}

		return new PendingTranslationGroup( $trid, $source_lang );
	}
}

class_alias( PendingTranslationGroupOnLoad::class, 'WPML_Pending_Translation_Group_On_Load' );
