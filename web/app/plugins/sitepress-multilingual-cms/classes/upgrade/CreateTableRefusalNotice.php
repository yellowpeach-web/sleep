<?php

namespace WPML\Upgrade;

class CreateTableRefusalNotice implements \IWPML_Backend_Action {

	const NOTICE_GROUP = 'wpml-create-table';
	const NOTICE_ID    = 'wpml-create-table-refused';

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'refresh' ] );
	}

	public function refresh() {
		if ( wp_doing_ajax() ) {
			return;
		}

		global $wpdb;

		$refusals = CreateTable::forgetCreatedTables( $wpdb );
		$notices  = wpml_get_admin_notices();

		if ( ! $refusals ) {
			if ( $notices->get_notice( self::NOTICE_ID, self::NOTICE_GROUP ) ) {
				$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
			}

			return;
		}

		$notice = $notices->create_notice( self::NOTICE_ID, self::text( $refusals ), self::NOTICE_GROUP );
		$notice->set_css_class_types( [ 'error' ] );
		$notice->set_dismissible( false );
		$notice->set_restrict_to_page_prefixes( [ 'sitepress-multilingual-cms', 'wpml' ] );
		$notices->add_notice( $notice );
	}

	public static function text( array $refusals ) {
		$items = '';
		foreach ( $refusals as $table => $record ) {
			$error  = '' !== $record['error']
				? $record['error']
				: __( 'The database gave no error message.', 'sitepress' );
			$items .= '<li><code>' . esc_html( (string) $table ) . '</code>: ' . esc_html( $error ) . '</li>';
		}

		$count = count( $refusals );

		return '<p><strong>' . esc_html(
			_n(
				'WPML could not create a database table it needs.',
				'WPML could not create database tables it needs.',
				$count,
				'sitepress'
			)
		) . '</strong></p>'
			. '<p>' . esc_html(
				_n(
					'The database refused the statement with this error. The features that use this table do not work until it exists.',
					'The database refused the statements with these errors. The features that use these tables do not work until they exist.',
					$count,
					'sitepress'
				)
			) . '</p>'
			. '<ul>' . $items . '</ul>'
			. '<p>' . esc_html__( 'Ask your host to check that the database user may create tables. If the error is about something else, send it to WPML support.', 'sitepress' ) . '</p>'
			. '<p>' . esc_html__( 'WPML tries again on every page load. This message goes away when the tables exist.', 'sitepress' ) . '</p>';
	}
}
