<?php

namespace ACFML\Repeater\Shuffle;

use ACFML\Helper\AcfPostId;
use WPML\FP\Obj;

class Resolver {

	public static function getStrategy() {
		global $pagenow;

		$is_repeater_update_on_term_edit  = isset( $_REQUEST['action'] ) && 'editedtag' === $_REQUEST['action'] && isset( $_REQUEST['acf'] );
		$is_repeater_display_on_term_edit = isset( $pagenow ) && 'term.php' === $pagenow;
		$is_repeater_update_on_post_edit  = isset( $_REQUEST['action'] ) && 'editpost' === $_REQUEST['action'] && isset( $_REQUEST['acf'] );
		$is_repeater_display_on_post_edit = isset( $pagenow ) && 'post.php' === $pagenow;
		$is_option_page_request           = function() {
			return is_admin() && function_exists( 'acf_get_options_page' ) && acf_get_options_page( Obj::prop( 'page', $_REQUEST ) );
		};

		if ( $is_repeater_update_on_term_edit || $is_repeater_display_on_term_edit ) {
			if ( isset( $_REQUEST['taxonomy'] ) ) {
				return new Term( sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ) ) );
			}
		} elseif ( $is_repeater_update_on_post_edit || $is_repeater_display_on_post_edit ) {
			return new Post();
		} elseif ( $is_option_page_request() ) {
			return new OptionsPage();
		}

		return null;
	}

	public static function forAcfId( $acfId ) {
		switch ( AcfPostId::getType( $acfId ) ) {
			case AcfPostId::TYPE_POST:
				return new Post();
			case AcfPostId::TYPE_TERM:
				return self::forTerm( $acfId );
			case AcfPostId::TYPE_OPTION:
				return self::forOptionsPage( $acfId );
			default:
				return null;
		}
	}

	private static function forTerm( $acfId ) {
		$taxonomy = AcfPostId::getTermTaxonomy( $acfId );

		return $taxonomy ? new Term( $taxonomy ) : null;
	}

	private static function forOptionsPage( $acfId ) {
		if ( ! function_exists( 'acf_get_options_pages' ) || ! acf_get_options_pages() ) {
			return null;
		}

		$optionsPage = new OptionsPage();

		return $optionsPage->isValidId( $acfId ) ? $optionsPage : null;
	}
}
