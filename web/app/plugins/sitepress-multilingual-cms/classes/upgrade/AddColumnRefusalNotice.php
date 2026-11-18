<?php

namespace WPML\Upgrade;

use WPML\Infrastructure\WordPress\Port\Persistence\Options;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates\Repository;

class AddColumnRefusalNotice implements \IWPML_Backend_Action {

	const NOTICE_GROUP = 'wpml-add-column';
	const NOTICE_ID    = 'wpml-add-column-refused';

	const RETRY_ARG = 'wpml-retry-updates';

	const JOB_TABLE = 'icl_translate_job';

	public function add_hooks() {
		add_action( 'wpml_loaded', [ $this, 'retry_if_asked' ], 0 );
		add_action( 'admin_init', [ $this, 'refresh' ], 20 );
	}

	public function retry_if_asked() {
		if ( ! isset( $_GET[ self::RETRY_ARG ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET[ self::RETRY_ARG ] ) );
		if ( ! wp_verify_nonce( $nonce, self::RETRY_ARG ) ) {
			return;
		}

		AddColumn::retry();
		$this->updates_log()->retryGaveUp();
	}

	public function refresh() {
		if ( wp_doing_ajax() ) {
			return;
		}

		global $wpdb;

		$recorded = AddColumn::refusals();
		$refusals = AddColumn::forgetAddedColumns( $wpdb );
		if ( count( $refusals ) < count( $recorded ) ) {
			$this->updates_log()->retryGaveUp();
		}

		$used_up = array_filter(
			$refusals,
			function ( $record ) {
				return $record['attempts'] >= AddColumn::MAX_ATTEMPTS;
			}
		);
		$notices = wpml_get_admin_notices();

		if ( ! $used_up ) {
			if ( $notices->get_notice( self::NOTICE_ID, self::NOTICE_GROUP ) ) {
				$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
			}

			return;
		}

		$notice = $notices->create_notice( self::NOTICE_ID, self::text( $used_up ), self::NOTICE_GROUP );
		$notice->set_css_class_types( [ 'error' ] );
		$notice->set_dismissible( false );
		$notice->add_capability_check( [ 'manage_options' ] );
		$notice->set_restrict_to_page_prefixes( [ 'sitepress-multilingual-cms', 'wpml', 'tm/menu' ] );
		$notice->add_action( new \WPML_Notice_Action( __( 'Try again', 'sitepress' ), $this->retry_url(), false, false, true ) );
		$notices->add_notice( $notice );
	}

	public static function text( array $refusals ) {
		$items     = '';
		$job_table = false;
		foreach ( $refusals as $record ) {
			$job_table = $job_table || self::JOB_TABLE === substr( $record['table'], -strlen( self::JOB_TABLE ) );
			$error     = '' !== $record['error']
				? $record['error']
				: __( 'The database gave no error message.', 'sitepress' );
			$items    .= '<li><code>' . self::literal( $record['query'] ) . '</code><br>' . self::literal( $error ) . '</li>';
		}

		$count = count( $refusals );

		return '<h3>' . esc_html__( 'WPML could not update its database.', 'sitepress' ) . '</h3>'
			. '<p>' . esc_html(
				$job_table
					? __( 'Translation jobs cannot be opened until the update succeeds.', 'sitepress' )
					: __( 'Some WPML features do not work until the update succeeds.', 'sitepress' )
			) . '</p>'
			. '<p>' . esc_html(
				_n(
					'The database refused this statement:',
					'The database refused these statements:',
					$count,
					'sitepress'
				)
			) . '</p>'
			. '<ul>' . $items . '</ul>'
			. '<p>' . esc_html(
				sprintf(
					/* translators: %d: how many times WPML ran the statement */
					__( 'WPML stopped after %d attempts. Click Try again to run the update now.', 'sitepress' ),
					AddColumn::MAX_ATTEMPTS
				)
			) . '</p>'
			. '<p>' . esc_html__( 'If it fails again, ask your host to run the statement on the database. You can also send this message to WPML support.', 'sitepress' ) . '</p>';
	}

	private static function literal( $text ) {
		return str_replace( '`', '&#96;', esc_html( $text ) );
	}

	private function retry_url() {
		return add_query_arg(
			[ self::RETRY_ARG => wp_create_nonce( self::RETRY_ARG ) ],
			admin_url( 'index.php' )
		);
	}

	private function updates_log() {
		return new Repository( new Options() );
	}
}
