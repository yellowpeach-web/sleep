<?php

namespace WPML\Setup;

use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\LanguageEditor\Save\CrossPageNotice;

class DisableNotices implements \IWPML_DIC_Action, \IWPML_Backend_Action {

	const SETUP_PAGES = [
		'sitepress-multilingual-cms/menu/setup.php',
		'sitepress-multilingual-cms/menu/languages.php',
	];

	public function add_hooks() {
		add_action( 'in_admin_header', [ $this, 'removeNotices' ], PHP_INT_MAX );
	}

	public function removeNotices() {
		if ( self::isNoticeFreePage( $_GET ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public static function isNoticeFreePage( array $query ) {
		$page = Obj::propOr( '', 'page', $query );

		if ( Lst::includes( $page, self::SETUP_PAGES ) ) {
			return true;
		}

		return CrossPageNotice::LANGUAGES_PAGE === $page
			&& CrossPageNotice::LANGUAGES_SECTION === Obj::propOr( '', 'section', $query );
	}
}
