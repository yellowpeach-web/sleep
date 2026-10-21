<?php

namespace ACFML\Strings;

use ACFML\Strings\Helper\ContentTypeLabels;
use WPML\FP\Obj;

class CptHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const AFTER_LOAD_LOCAL_CPTS_PRIORITY = 30;

	private $factory;

	private $translator;

	public function __construct( Factory $factory, Translator $translator ) {
		$this->factory    = $factory;
		$this->translator = $translator;
	}

	public function add_hooks() {
		add_action( 'acf/update_post_type', [ $this, 'register' ] );
		add_filter( 'acf/post_type/registration_args', [ $this, 'translate' ], 10, 2 );
		add_filter( 'acf/load_post_types', [ $this, 'enterCptTitleHere' ], self::AFTER_LOAD_LOCAL_CPTS_PRIORITY );
		add_action( 'acf/delete_post_type', [ $this, 'delete' ] );
	}

	public function register( $postData ) {
		$this->translator->registerCpt( $postData );
	}

	public function translate( $postTypeArgs, $postData ) {
		return ContentTypeLabels::translateLabels(
			$postTypeArgs,
			$this->translator->translateCpt( $postData, $postTypeArgs ),
			[ 'description', 'enter_title_here' ]
		);
	}

	public function delete( $postData ) {
		$this->factory->createPackage( $postData['post_type'], Package::CPT_PACKAGE_KIND_SLUG )->delete();
	}

	public function enterCptTitleHere( $postsData ) {
		array_walk( $postsData, function( &$value ) {
			if ( ! $this->isDoingEnterTitleHereFilter( $value['post_type'] ) ) {
				return;
			}

			if ( ! Obj::prop( 'enter_title_here', $value ) ) {
				return;
			}

			$translatedLabels = $this->translator->translateCpt(
				[
					'post_type'        => $value['post_type'],
					'enter_title_here' => $value['enter_title_here'],
				]
			);

			$value['enter_title_here'] = Obj::propOr( $value['enter_title_here'], 'enter_title_here', $translatedLabels );
		} );

		return $postsData;
	}

	private static function getCurrentScreenPostType() {
		$currentScreen = get_current_screen();
		if ( ! $currentScreen ) {
			return null;
		}
		return $currentScreen->post_type;
	}

	private function isDoingEnterTitleHereFilter( $postType ) {
		global $pagenow;

		if ( ! doing_filter( 'enter_title_here' ) ) {
			return false;
		}

		$isPostEditScreen = in_array( $pagenow, [ 'post.php', 'post-new.php' ], true );
		if ( ! $isPostEditScreen ) {
			return false;
		}

		if ( self::getCurrentScreenPostType() !== $postType ) {
			return false;
		}

		return true;
	}

}
