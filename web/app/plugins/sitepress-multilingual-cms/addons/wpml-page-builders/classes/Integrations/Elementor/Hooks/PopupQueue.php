<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\PB\Elementor\Helper\Template;
use function WPML\FP\spreadArgs;

class PopupQueue implements \IWPML_Frontend_Action {

	const LOCATION = 'popup';

	public function add_hooks() {
		Hooks::onAction( 'elementor/theme/before_do_popup' )
			->then( spreadArgs( [ $this, 'dedupeTranslatedPopups' ] ) );
	}

	public function dedupeTranslatedPopups( $manager ) {
		$queueMethods = [ 'get_documents_for_location', 'remove_doc_from_location', 'add_doc_to_location' ];

		foreach ( $queueMethods as $queueMethod ) {
			if ( ! method_exists( $manager, $queueMethod ) ) {
				return;
			}
		}

		foreach ( $manager->get_documents_for_location( self::LOCATION ) as $popupId ) {
			$postType     = get_post_type( $popupId );
			$translatedId = (int) Template::fallBackWhenUnpublished(
				$popupId,
				apply_filters( 'wpml_object_id', $popupId, $postType, true ),
				$postType
			);

			if ( $translatedId && $translatedId !== (int) $popupId ) {
				$manager->remove_doc_from_location( self::LOCATION, $popupId );
				$manager->add_doc_to_location( self::LOCATION, $translatedId );
			}
		}
	}
}
