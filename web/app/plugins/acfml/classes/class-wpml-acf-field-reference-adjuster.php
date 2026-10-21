<?php


namespace ACFML;

use WPML\API\Sanitize;

class FieldReferenceAdjuster implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $sitepress;
	private $originalReference;
	private $displayedPostId;
	const GROUP_POST_TYPE = 'acf-field-group';
	const FIELD_POST_TYPE = 'acf-field';

	const ACF_POST_TYPES = [ self::GROUP_POST_TYPE, self::FIELD_POST_TYPE, 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ];

	private $fieldName;

	private $parentByReference = [];

	private $referencesByGroup = [];

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( $this->hooksShouldBeRegistered() ) {
			add_filter( 'acf/load_reference', [ $this, 'translatedFieldReference' ], 10, 3 );
		}
	}

	public function translatedFieldReference( $originalReference, $fieldName, $displayedPostId ) {
		$this->originalReference = $originalReference;
		$this->displayedPostId   = $displayedPostId;
		$this->fieldName         = $fieldName;

		if ( $this->referenceLanguageDifferentThanPostLanguage() ) {
			return $this->getTranslatedFieldReference();
		}

		return $originalReference;
	}

	private function hooksShouldBeRegistered() {
		return ( $this->isFrontEndRequest() || $this->isPostEditRequest() )
			&& $this->fieldGroupsAreTranslatable();
	}

	private function isFrontEndRequest() {
		return ! \is_admin();
	}

	private function isPostEditRequest() {
		global $pagenow;

		if ( \wp_doing_ajax() ) {
			$action = (string) Sanitize::stringProp( 'action', $_REQUEST );
			$postId = isset( $_REQUEST['post_id'] ) ? (int) $_REQUEST['post_id'] : 0;

			return 0 === strpos( $action, 'acf/' ) && $postId && $this->isNotAcfPostType( \get_post_type( $postId ) );
		}

		if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
			return false;
		}

		if ( isset( $_GET['post'] ) ) {
			$postType = \get_post_type( (int) $_GET['post'] );
		} elseif ( isset( $_POST['post_ID'] ) ) {
			$postType = \get_post_type( (int) $_POST['post_ID'] );
		} else {
			$postType = Sanitize::stringProp( 'post_type', $_GET ) ?: 'post';
		}

		return $this->isNotAcfPostType( $postType );
	}

	private function isNotAcfPostType( $postType ) {
		return $postType && ! in_array( $postType, self::ACF_POST_TYPES, true );
	}

	private function fieldGroupsAreTranslatable() {
		return $this->sitepress->is_translated_post_type( self::GROUP_POST_TYPE );
	}

	private function referenceLanguageDifferentThanPostLanguage() {
		return $this->getFieldLanguage() !== $this->getPostLanguage();
	}

	private function getPostLanguage() {
		return $this->getLanguageCode( $this->displayedPostId );
	}

	private function getFieldLanguage() {
		$fieldsParent = $this->getFieldsParentByReference();
		if ( $fieldsParent ) {
			return $this->getLanguageCode( $fieldsParent, sprintf( 'post_%s', self::GROUP_POST_TYPE ) );
		}

		return '';
	}

	private function getLanguageCode( $postId, $postType = 'post_post' ) {
		$languageDetails = $this->sitepress->get_element_language_details( $postId, $postType );

		return isset( $languageDetails->language_code ) ? (string) $languageDetails->language_code : '';
	}

	private function getTranslatedFieldReference() {
		$fieldsParent = $this->getFieldsParentByReference();
		if ( $fieldsParent ) {
			$elementType           = sprintf( 'post_%s', self::GROUP_POST_TYPE );
			$trid                  = $this->sitepress->get_element_trid( $fieldsParent, $elementType );
			$translatedFieldGroups = $this->sitepress->get_element_translations( $trid, $elementType, false, true );
			$translatedFieldGroup  = isset( $translatedFieldGroups[ $this->sitepress->get_current_language() ]->element_id )
				? (int) $translatedFieldGroups[ $this->sitepress->get_current_language() ]->element_id
				: 0;

			if ( $translatedFieldGroup && 'publish' === get_post_status( $translatedFieldGroup ) ) {
				$translatedReference = $this->getReferenceFromChildrenOfFieldGroup( $translatedFieldGroup );

				return $translatedReference ?: $this->originalReference;
			}
		}

		return $this->originalReference;
	}

	private function getFieldsParentByReference() {
		$reference = (string) $this->originalReference;
		if ( array_key_exists( $reference, $this->parentByReference ) ) {
			return $this->parentByReference[ $reference ];
		}

		$posts = get_posts(
			[
				'name'        => $this->originalReference,
				'post_type'   => self::FIELD_POST_TYPE,
				'numberposts' => 1,
			]
		);

		$this->parentByReference[ $reference ] = ( isset( $posts[0] ) && is_a( $posts[0], \WP_Post::class ) ) ? $posts[0]->post_parent : null;

		return $this->parentByReference[ $reference ];
	}

	private function getReferenceFromChildrenOfFieldGroup( $groupId ) {
		if ( ! isset( $this->referencesByGroup[ $groupId ] ) ) {
			$posts = get_posts(
				[
					'numberposts' => -1,
					'post_type'   => self::FIELD_POST_TYPE,
					'post_parent' => $groupId,
					'post_status' => 'publish',
				]
			);

			$references = [];
			foreach ( (array) $posts as $post ) {
				if ( ! isset( $references[ $post->post_excerpt ] ) ) {
					$references[ $post->post_excerpt ] = $post->post_name;
				}
			}
			$this->referencesByGroup[ $groupId ] = $references;
		}

		return $this->referencesByGroup[ $groupId ][ (string) $this->fieldName ] ?? null;
	}
}
