<?php

namespace WPML\TM\ATE\Cancel;

use WPML\TM\ATE\ClonedSites\NoticeBrand;
use WPML\TM\ATE\ClonedSites\ReconnectNotice;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\Jobs\JobLog;

class FailedCancelNotice implements \IWPML_Backend_Action {

	const OPTION = 'wpml_tm_ate_cancel_notices';

	const CAP = 20;

	public function add_hooks() {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_filter( 'wpml_tm_dashboard_notices', [ $this, 'addDashboardNotice' ] );
	}

	public function addDashboardNotice( $notices ) {
		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = [ $this, 'render' ];

		return $notices;
	}

	public static function heldCount() {
		$down = PendingCancels::ateDown();

		return $down ? (int) $down['kept'] : 0;
	}

	public static function heldParagraph() {
		$kept = self::heldCount();
		if ( $kept <= 0 ) {
			return '';
		}

		return '<p class="wpml-ate-cancels-held">' . esc_html( self::heldParagraphText( $kept ) ) . '</p>';
	}

	public static function queue( $userId, array $entry ) {
		$userId = (int) $userId;
		if ( $userId <= 0 ) {
			return false;
		}

		$all       = self::readAll();
		$entries   = $all[ $userId ] ?? [];
		$entries[] = [
			'ate_job_id'  => (int) ( $entry['ate_job_id'] ?? 0 ),
			'wpml_job_id' => (int) ( $entry['wpml_job_id'] ?? 0 ),
			'at'          => time(),
		];
		$all[ $userId ] = array_slice( $entries, - self::CAP );

		return (bool) update_option( self::OPTION, $all, false );
	}

	public function render() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		$html = '';

		if ( ReconnectNotice::isAteScreen() && ! ReconnectState::isReconnecting() ) {
			$kept = self::heldCount();
			if ( $kept > 0 ) {
				$html .= self::heldLine( $kept );
			}
		}

		$userId  = (int) get_current_user_id();
		$all     = $userId > 0 ? self::readAll() : [];
		$entries = $all[ $userId ] ?? [];

		if ( $entries ) {
			unset( $all[ $userId ] );
			if ( $all ) {
				update_option( self::OPTION, $all, false );
			} else {
				delete_option( self::OPTION );
			}

			foreach ( $entries as $entry ) {
				$html .= '<div class="notice notice-warning is-dismissible wpml-ate-cancel-failed-notice"><p>'
					. esc_html( self::text( (int) $entry['wpml_job_id'] ) )
					. '</p></div>';
			}
		}

		if ( '' === $html ) {
			return;
		}

		echo wp_kses( $html, ReconnectNotice::allowedTags() );

		if ( ! $entries ) {
			return;
		}

		JobLog::runInGroup(
			JobLog::GROUP_ID_CANCELS,
			'Failed ATE cancel notice shown',
			function () use ( $userId, $entries ) {
				JobLog::add( 'ate_cancel_notice_shown', [
					'user_id'      => $userId,
					'ate_job_ids'  => array_map( 'intval', array_column( $entries, 'ate_job_id' ) ),
					'wpml_job_ids' => array_map( 'intval', array_column( $entries, 'wpml_job_id' ) ),
				] );
			}
		);
	}

	private static function heldLine( $kept ) {
		$kept = max( 1, (int) $kept );

		return '<div class="notice notice-warning wpml-ate-cancels-held">'
			. NoticeBrand::row()
			. '<p>' . esc_html( self::heldLineText( $kept ) ) . '</p></div>';
	}

	public static function heldSentences( $kept ) {
		$kept = max( 1, (int) $kept );

		return [ 'line' => self::heldLineText( $kept ), 'paragraph' => self::heldParagraphText( $kept ) ];
	}

	private static function heldLineText( $kept ) {
		return sprintf(
			/* translators: Admin notice on the WPML translation screens while the automatic translation service does not answer and the jobs the user cancelled are still open there. %d: how many jobs. */
			_n(
				'WPML could not cancel %d translation job in Advanced Translation Editor. Our translation system is not available right now. WPML keeps trying.',
				'WPML could not cancel %d translation jobs in Advanced Translation Editor. Our translation system is not available right now. WPML keeps trying.',
				$kept,
				'sitepress'
			),
			$kept
		);
	}

	private static function heldParagraphText( $kept ) {
		return sprintf(
			/* translators: A sentence added to the notice that WPML is reconnecting to the automatic translation service, when jobs the user cancelled are still open there. %d: how many jobs. */
			_n(
				'WPML could not cancel %d translation job in Advanced Translation Editor yet.',
				'WPML could not cancel %d translation jobs in Advanced Translation Editor yet.',
				$kept,
				'sitepress'
			),
			$kept
		);
	}

	private static function text( $wpmlJobId ) {
		$description = JobDescription::of( $wpmlJobId );
		$title       = '' !== $description['title'] ? $description['title'] : '#' . $wpmlJobId;
		$language    = '' !== $description['language'] ? $description['language'] : __( 'unknown language', 'sitepress' );

		return sprintf(
			/* translators: Admin notice after WPML could not stop a translation job at the automatic translation service; the job stays cancelled in WPML. 1: the title of the content, 2: the name of the language it is translated into. */
			__( 'WPML could not cancel the translation of \'%1$s\' (%2$s) in Advanced Translation Editor. The job is cancelled in WPML. Advanced Translation Editor may still translate it and charge for it.', 'sitepress' ),
			$title,
			$language
		);
	}

	private static function readAll() {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$all = [];
		foreach ( $stored as $userId => $entries ) {
			if ( (int) $userId > 0 && is_array( $entries ) ) {
				$all[ (int) $userId ] = array_values( array_filter( $entries, 'is_array' ) );
			}
		}

		return $all;
	}
}
