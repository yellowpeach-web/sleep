<?php

namespace WPML\Troubleshooting;

class TranslationsTableKeysNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const OPTION       = 'wpml_translations_table_keys_check';
	const CHECK_EVERY  = 86400;
	const NOTICE_GROUP = 'wpml-troubleshooting';
	const NOTICE_ID    = 'translations-table-keys';
	const TOOL_SLUG    = 'translations-table-keys';

	const WPML_PAGE_PREFIXES = [ 'sitepress-multilingual-cms', 'tm/menu', 'wpml-' ];

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'check' ] );
	}

	public function check( $now = null ) {
		if ( wp_doing_ajax() ) {
			return;
		}

		$now    = is_int( $now ) && $now > 0 ? $now : time();
		$stored = get_option( self::OPTION );
		if ( is_array( $stored ) && isset( $stored['at'], $stored['broken'] ) && $now - (int) $stored['at'] < self::CHECK_EVERY ) {
			return;
		}

		$broken = ( new TranslationsTableKeys( $this->wpdb ) )->lacksPrimaryKeyOrAutoIncrement();
		self::record( $broken, $now );
	}

	public static function recheckNow() {
		delete_option( self::OPTION );
	}

	public static function isKnownBroken() {
		$stored = get_option( self::OPTION );

		return is_array( $stored ) && ! empty( $stored['broken'] );
	}

	public static function record( $broken, $now ) {
		update_option(
			self::OPTION,
			[
				'at'     => (int) $now,
				'broken' => (bool) $broken,
			],
			false
		);

		$notices = wpml_get_admin_notices();
		if ( ! $broken ) {
			$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );

			return;
		}

		$text = sprintf(
			/* translators: Notice on WPML's admin screens. "The translations table" is the database table where WPML records which content is a translation of which. %s: the address of the repair on WPML → Support, which fills the link. */
			__( 'WPML found a problem in the translations table on this site: it lost its keys. Until it is repaired, some posts show as not translated. <a href="%s">Repair the translations table</a>', 'sitepress' ),
			esc_url( self::toolUrl() )
		);

		$notice = $notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'error' );
		$notice->set_dismissible( false );
		$notice->add_display_callback( [ self::class, 'isWpmlScreen' ] );
		$notices->add_notice( $notice );
	}

	public static function isWpmlScreen() {
		$page = (string) \WPML\SuperGlobals\Request::page();
		foreach ( self::WPML_PAGE_PREFIXES as $prefix ) {
			if ( 0 === strpos( $page, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	public static function toolUrl() {
		return admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php&tool=' . self::TOOL_SLUG );
	}
}
