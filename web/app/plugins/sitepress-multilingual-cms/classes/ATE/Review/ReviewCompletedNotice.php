<?php

namespace WPML\TM\ATE\Review;

use WPML\FP\Relation;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

class ReviewCompletedNotice implements \IWPML_Backend_Action {

	const ROUTE = 'ate-review-completed-notice';

	public function add_hooks() {
		if ( Relation::propEq( 'reviewCompleted', 'inWPML', $_GET ) && self::policy()->permits() ) {
			$text = esc_html__( "You've completed reviewing your selection. WPML will let you know when there's new content to review.", 'sitepress' );
			wpml_get_admin_notices()->add_notice(
				\WPML_Notice::make( 'reviewCompleted', $text )
				            ->set_css_class_types( 'notice-info' )
				            ->set_flash()
			);
		}
	}

	public static function policy() {
		return Registry::declare(
			Registry::PSEUDO_ROUTE,
			self::ROUTE,
			Policy::capability(
				[ 'translate', 'manage_translations' ],
				Authenticity::none( 'WPML\'s own redirect after the last translation review; fixed notice text, no domain write' )
			)
		);
	}
}
