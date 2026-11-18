<?php

namespace WPML\EditHistory;

class HousekeepingFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		global $wpdb, $sitepress;

		$log   = new WpdbEntryLog( $wpdb );
		$store = new WpdbStateStore( $wpdb );

		return new HousekeepingHooks( $log, $store, new Sweep( $log, $store, self::owner( $sitepress ) ), new PrivacyTools( $log ) );
	}

	public static function owner( $sitepress ): callable {
		return static function ( int $postId ) use ( $sitepress ) {
			$post = get_post( $postId );
			if ( ! $post instanceof \WP_Post ) {
				return null;
			}

			$type    = 'post_' . $post->post_type;
			$details = $sitepress->get_element_language_details( $postId, $type );

			return [
				is_object( $details ) ? (string) ( $details->language_code ?? '' ) : '',
				(int) apply_filters( 'wpml_original_element_id', null, $postId, $type ),
			];
		};
	}
}
