<?php

namespace WPML\LanguageEditor\Save;

use function WPML\Container\make;

class CrossPageNotice implements \IWPML_Backend_Action {

	const LANGUAGES_PAGE    = 'tm/menu/settings';
	const LANGUAGES_SECTION = 'languages';

	const DISMISS_META = 'wpml_lang_save_paused_dismissed';

	const DISMISS_ACTION = 'wpml_lang_save_dismiss_paused';

	private $task;

	public function add_hooks() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_init', [ $this, 'resolve' ] );
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		\WPML\Request\Adapter\Ajax::register( self::DISMISS_ACTION, \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( self::DISMISS_ACTION, 'nonce' ) ), [ $this, 'ajaxDismiss' ] );
		add_filter( 'wpml_language_editor_save_bootstrap', [ $this, 'addPendingTask' ] );
	}

	public function resolve() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->task = make( SaveEngine::class )->status();
	}

	public function render() {
		if ( ! $this->task || $this->task->isTerminal() ) {
			return;
		}

		$onLanguages = $this->isLanguagesPage();
		$status      = $this->task->getStatus();

		if ( $onLanguages ) {
			return;
		}

		if ( SaveTask::STATUS_IN_PROGRESS === $status || SaveTask::STATUS_PENDING === $status ) {
			$this->renderRunning();
			return;
		}

		if ( SaveTask::STATUS_PAUSED === $status && ! $this->isPausedDismissed() ) {
			$this->renderPaused();
		}
	}

	private function renderRunning() {
		echo '<div class="notice notice-warning wpml-lang-save-running">';
		echo '<p>';
		echo esc_html__(
			'A language update is in progress. Don\'t change language settings until it finishes.',
			'sitepress'
		) . ' ';
		echo '<a href="' . esc_url( $this->languagesUrl() ) . '">'
			. esc_html__( 'Go to Settings > Languages', 'sitepress' )
			. '</a>';
		echo '</p>';
		echo '</div>';
	}

	private function languagesUrl() {
		return admin_url(
			'admin.php?page=' . self::LANGUAGES_PAGE . '&section=' . self::LANGUAGES_SECTION
		);
	}

	private function renderPaused() {
		echo '<div class="notice notice-warning is-dismissible wpml-lang-save-paused" '
			. 'data-op="' . esc_attr( $this->opKey() ) . '" '
			. 'data-nonce="' . esc_attr( wp_create_nonce( self::DISMISS_ACTION ) ) . '">';
		echo '<p>';
		echo esc_html__( 'Language changes are still in progress.', 'sitepress' ) . ' ';
		echo '<a href="' . esc_url( $this->languagesUrl() ) . '">'
			. esc_html__( 'Go to Settings > Languages', 'sitepress' )
			. '</a>';
		echo '</p>';
		echo '</div>';
	}

	public function addPendingTask( $saveData ) {
		if ( ! is_array( $saveData ) ) {
			$saveData = [];
		}
		if ( $this->task && ! $this->task->isTerminal() ) {
			$units = $this->task->getCursor( 'units', [] );
			$saveData['pendingTask'] = [
				'taskId'    => (int) $this->task->getTaskId(),
				'status'    => SaveTask::statusName( $this->task->getStatus() ),
				'percent'   => (int) $this->task->getPercent(),
				'stepIndex' => (int) $this->task->getCursor( 'unitIdx', 0 ),
				'stepCount' => is_array( $units ) ? count( $units ) : 0,
			];
		}
		return $saveData;
	}

	public function enqueue() {
		if ( ! current_user_can( 'manage_options' ) || ! $this->task || $this->task->isTerminal() ) {
			return;
		}
		if ( $this->isLanguagesPage()
			|| SaveTask::STATUS_PAUSED !== $this->task->getStatus()
			|| $this->isPausedDismissed() ) {
			return;
		}

		$js = sprintf(
			'document.addEventListener("click",function(e){'
			. 'if(!e.target.classList||!e.target.classList.contains("notice-dismiss"))return;'
			. 'var b=e.target.closest(".wpml-lang-save-paused");if(!b)return;'
			. 'var d=new FormData();d.append("action",%s);'
			. 'd.append("op",b.getAttribute("data-op")||"");'
			. 'd.append("nonce",b.getAttribute("data-nonce")||"");'
			. 'fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:d});});',
			wp_json_encode( self::DISMISS_ACTION )
		);
		wp_add_inline_script( 'common', $js );
	}

	public function ajaxDismiss() {
		if ( ! current_user_can( 'manage_options' )
			|| ! check_ajax_referer( self::DISMISS_ACTION, 'nonce', false ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$op = isset( $_POST['op'] ) ? sanitize_text_field( wp_unslash( $_POST['op'] ) ) : '';
		if ( '' === $op ) {
			wp_send_json_error( 'missing_op' );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META, $op );
		wp_send_json_success();
	}

	private function opKey() {
		if ( ! $this->task ) {
			return '';
		}
		return (string) $this->task->getTaskId() . ':' . (string) $this->task->getStartingDate();
	}

	private function isPausedDismissed() {
		$dismissed = (string) get_user_meta( get_current_user_id(), self::DISMISS_META, true );
		return '' !== $dismissed && $dismissed === $this->opKey();
	}

	private function isLanguagesPage() {
		$page    = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? $_GET['page'] : '';
		$section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';
		return self::LANGUAGES_PAGE === $page && self::LANGUAGES_SECTION === $section;
	}
}
