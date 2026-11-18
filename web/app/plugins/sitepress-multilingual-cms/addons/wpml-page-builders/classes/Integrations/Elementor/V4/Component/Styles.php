<?php

namespace WPML\PB\Elementor\V4\Component;

use Elementor\Plugin;
use WPML\PB\Elementor\Helper\Template;

class Styles implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const RENDER_ACTION = 'elementor/post/render';

	const ENQUEUE_ACTION = 'elementor/frontend/after_enqueue_post_styles';

	private $instanceIds;

	private $translatedIds = [];

	public function __construct( InstanceIds $instanceIds ) {
		$this->instanceIds = $instanceIds;
	}

	public function add_hooks() {
		add_action( self::RENDER_ACTION, [ $this, 'renderTranslatedComponent' ] );
		add_action( self::ENQUEUE_ACTION, [ $this, 'dequeueOriginalComponents' ], 11 );
	}

	public function renderTranslatedComponent( $postId ) {
		$postId = (int) $postId;

		if (
			isset( $this->translatedIds[ $postId ] )
			|| ! Cache::hasOwnStyles( $postId )
			|| $this->isPreview()
		) {
			return;
		}

		$translatedId = $this->instanceIds->translateId( $postId );

		if ( $translatedId ) {
			$translatedId = (int) Template::fallBackWhenUnpublished( $postId, $translatedId, get_post_type( $postId ) );
		}

		if ( ! $translatedId || $translatedId === $postId ) {
			return;
		}

		$this->translatedIds[ $postId ] = $translatedId;

		do_action( self::RENDER_ACTION, $translatedId );
	}

	public function dequeueOriginalComponents() {
		foreach ( array_keys( $this->translatedIds ) as $originalId ) {
			$prefix = Cache::LOCAL_STYLES_KEY . '-' . $originalId . '-';

			foreach ( wp_styles()->queue as $handle ) {
				if ( 0 === strpos( $handle, $prefix ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	private function isPreview() {
		$elementor = Plugin::instance();

		return isset( $elementor->preview ) && $elementor->preview->is_preview_mode();
	}
}
