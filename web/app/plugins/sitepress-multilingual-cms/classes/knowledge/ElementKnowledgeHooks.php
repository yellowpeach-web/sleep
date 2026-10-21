<?php

namespace WPML\Knowledge;

class ElementKnowledgeHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	use \WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;

	private $knowledge;

	public function __construct( ?ElementKnowledge $knowledge = null, ?callable $filesMatchLoadedVersion = null ) {
		$this->knowledge               = $knowledge;
		$this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
	}

	public function add_hooks() {
		add_action( 'delete_post', [ $this, 'onPostDeleted' ] );
		add_action( 'delete_term', [ $this, 'onTermDeleted' ] );
		add_action( 'deleted_user', [ $this, 'onUserDeleted' ] );
	}

	public function onPostDeleted( $post_id ) {
		if ( ! $this->filesMatchLoadedVersion() ) {
			return;
		}

		$this->repository()->deleteEntity( ElementKnowledge::KIND_POST, (int) $post_id );
	}

	public function onTermDeleted( $term_id ) {
		if ( ! $this->filesMatchLoadedVersion() ) {
			return;
		}

		$this->repository()->deleteEntity( ElementKnowledge::KIND_TERM, (int) $term_id );
	}

	public function onUserDeleted( $user_id ) {
		if ( ! $this->filesMatchLoadedVersion() ) {
			return;
		}

		$this->repository()->deleteEntity( ElementKnowledge::KIND_USER, (int) $user_id );
	}

	private function repository() {
		if ( null === $this->knowledge ) {
			$this->knowledge = new ElementKnowledge();
		}

		return $this->knowledge;
	}
}
