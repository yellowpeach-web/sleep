<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

class PostTranslationLinks implements \IWPML_Frontend_Action {

	const LAYOUT_SECTION = 'layout-section';

	const POST_CONTENT = 'post-content';

	const CONTENT_SECTION_NAME = 'content';

	const CONTENT_SECTION_STACK = [ self::LAYOUT_SECTION ];

	const POST_CONTENT_IN_CONTENT_SECTION_STACK = [ self::LAYOUT_SECTION, self::POST_CONTENT ];

	const POST_CONTENT_OUTPUT_FILTER = 'fusion_component_fusion_tb_content_content';

	private $renderingStack = [];

	private $contentDepth = 0;

	private $isAppendingToContent = false;

	private $hasShownLinksInContentSection = false;

	private $postContentLinks = '';

	private $isPostContentLinksBefore = false;

	private $isPostContentLinksAfter = false;

	public function add_hooks() {
		add_action( 'awb_remove_third_party_the_content_changes', [ $this, 'startLayoutSection' ] );
		add_action( 'awb_readd_third_party_the_content_changes', [ $this, 'endRendering' ] );
		add_action( 'fusion_content_pre_render', [ $this, 'startPostContent' ] );
		add_action( 'fusion_content_post_render', [ $this, 'endRendering' ] );
		add_filter( 'the_content', [ $this, 'enterContent' ], PHP_INT_MIN );
		add_filter( 'the_content', [ $this, 'startAppendingToContent' ], \WPML_LS_Render::THE_CONTENT_FILTER_PRIORITY - 1 );
		add_filter( 'the_content', [ $this, 'endAppendingToContent' ], \WPML_LS_Render::THE_CONTENT_FILTER_PRIORITY + 1 );
		add_filter( 'the_content', [ $this, 'leaveContent' ], PHP_INT_MAX );
		add_filter( 'wpml_ls_post_alternative_languages', [ $this, 'showOnceInContentSection' ], PHP_INT_MAX );
		add_filter( self::POST_CONTENT_OUTPUT_FILTER, [ $this, 'addLinksToPostContentOutput' ] );
	}

	public function startLayoutSection() {
		$this->startRendering( self::LAYOUT_SECTION );
	}

	public function startPostContent() {
		$this->startRendering( self::POST_CONTENT );
		$this->postContentLinks         = '';
		$this->isPostContentLinksBefore = false;
		$this->isPostContentLinksAfter  = false;
	}

	public function endRendering() {
		array_pop( $this->renderingStack );
	}

	public function enterContent( $content ) {
		++$this->contentDepth;

		return $content;
	}

	public function leaveContent( $content ) {
		$this->contentDepth = max( 0, $this->contentDepth - 1 );

		return $content;
	}

	public function startAppendingToContent( $content ) {
		$this->isAppendingToContent = true;

		return $content;
	}

	public function endAppendingToContent( $content ) {
		$this->isAppendingToContent = false;

		if ( '' === $this->postContentLinks || ! is_string( $content ) || ! $this->isOwnContentCallOf( self::POST_CONTENT_IN_CONTENT_SECTION_STACK ) ) {
			return $content;
		}

		return $this->takeOutPostContentLinks( $content );
	}

	public function showOnceInContentSection( $html ) {
		if ( ! $this->isAppendingToContent || ! $this->renderingStack ) {
			return $html;
		}

		if ( $this->hasShownLinksInContentSection ) {
			return '';
		}

		if ( $this->isOwnContentCallOf( self::POST_CONTENT_IN_CONTENT_SECTION_STACK ) ) {
			$this->postContentLinks = (string) $html;

			return $html;
		}

		if ( $this->isOwnContentCallOf( self::CONTENT_SECTION_STACK ) ) {
			$this->hasShownLinksInContentSection = true;

			return $html;
		}

		return '';
	}

	public function addLinksToPostContentOutput( $content ) {
		if ( '' === $this->postContentLinks || $this->hasShownLinksInContentSection || ! is_string( $content ) ) {
			return $content;
		}

		$this->hasShownLinksInContentSection = true;

		$links                  = $this->postContentLinks;
		$this->postContentLinks = '';

		return ( $this->isPostContentLinksBefore ? $links : '' ) . $content . ( $this->isPostContentLinksAfter ? $links : '' );
	}

	private function startRendering( string $kind ) {
		$this->renderingStack[] = [
			'kind'         => $kind,
			'contentDepth' => $this->contentDepth,
		];
	}

	private function takeOutPostContentLinks( string $content ): string {
		$links = $this->postContentLinks;

		if ( 0 === strpos( $content, $links ) ) {
			$this->isPostContentLinksBefore = true;
			$content                        = (string) substr( $content, strlen( $links ) );
		}

		$lastPosition = strrpos( $content, $links );
		if ( false !== $lastPosition ) {
			$this->isPostContentLinksAfter = true;
			$content                       = substr_replace( $content, '', $lastPosition, strlen( $links ) );
		}

		return $content;
	}

	private function isOwnContentCallOf( array $stack ): bool {
		$innermost = end( $this->renderingStack );

		return $innermost
			&& $this->contentDepth === $innermost['contentDepth'] + 1
			&& array_column( $this->renderingStack, 'kind' ) === $stack
			&& self::CONTENT_SECTION_NAME === \Fusion_Template_Builder()->get_current_override_name();
	}
}
