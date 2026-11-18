<?php

namespace WPML\EditHistory;

use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class PanelFactory implements \IWPML_Backend_Action_Loader {

	public function create() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		global $wpdb, $sitepress;

		$store   = new WpdbStateStore( $wpdb );
		$package = new \WPML_Element_Translation_Package();
		$copy    = new PostCopy( fn( \WP_Post $original ) => $package->get_custom_fields_to_translate( $original ) );
		$items   = self::items( $store );
		$report  = new RevertReport( fn( array $report ) => make( \WPML_TM_ATE_API::class )->report_edit_decisions( [ $report ] ), [ JobLog::class, 'add' ] );

		return new PanelHooks(
			$items,
			new Revert(
				$store,
				$items,
				[ $copy, 'read' ],
				[ PostCopy::class, 'write' ],
				[ self::class, 'lockedBy' ],
				[ Revert::class, 'rootsOf' ],
				[ $report, 'send' ],
				[ JobLog::class, 'add' ]
			),
			new PanelPage( fn( string $code ) => (string) $sitepress->get_display_language_name( $code, $sitepress->get_admin_language() ) )
		);
	}

	public static function items( StateStore $store ): PanelItems {
		global $wpdb, $sitepress;

		$units = PostUnits::forBlockEditor();

		return new PanelItems(
			$store,
			new WpdbEntryLog( $wpdb ),
			HousekeepingFactory::owner( $sitepress ),
			static function ( int $translationId, int $originalId, array $saved ) use ( $units ) {
				$post     = get_post( $translationId );
				$original = get_post( $originalId );

				return $post instanceof \WP_Post && $original instanceof \WP_Post ? PostCopy::units( $units, $post, $original, $saved ) : null;
			},
			fn( int $postId ) => current_user_can( 'edit_post', $postId )
		);
	}

	public static function lockedBy( int $postId ) {
		if ( ! function_exists( 'wp_check_post_lock' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		return wp_check_post_lock( $postId );
	}
}
