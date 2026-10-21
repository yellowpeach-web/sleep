<?php

namespace WPML\TM\AutomaticTranslation\Actions;

use WPML\FP\Lst;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Option;
use WPML\UIPage;
use function WPML\FP\spreadArgs;

class AutomaticTranslationJobCreationFailureNotice implements \IWPML_Action {
	const OPTION_KEY = 'auto-translation-job-creation-error';
	const NOTICE_ID  = 'automatic-job-creation-failed';

	const ELEMENT_TYPE_POST    = 'post';
	const ELEMENT_TYPE_PACKAGE = 'package';

	private $jobFailedElements;

	private $wpmlNotices;

	private $wpmlTranslationElementFactory;

	public function __construct( \WPML_Translation_Element_Factory $translationElementFactory, \WPML_Notices $wpmlNotices ) {
		$this->jobFailedElements             = $this->readStoredElements();
		$this->wpmlNotices                   = $wpmlNotices;
		$this->wpmlTranslationElementFactory = $translationElementFactory;
	}

	private function readStoredElements() {
		$optionVal = Option::get( self::OPTION_KEY );
		$decoded   = $optionVal ? json_decode( $optionVal, true ) : [];

		return is_array( $decoded ) ? $decoded : [];
	}

	public function add_hooks() {
		Hooks::onAction( 'admin_init' )->then( spreadArgs( [ $this, 'updateNotice' ] ) );

		Hooks::onAction( 'wpml_update_failed_jobs_notice' )
			->then(
				spreadArgs(
					[ $this, 'updateNotice' ]
				)
			);
	}

	public function updateNotice( $postElement = null ) {
		$previousJobFailedElements = $this->readStoredElements();
		$this->jobFailedElements   = $previousJobFailedElements;

		$this->deleteElementsThatHaveJobsCreated();

		if ( $postElement ) {
			$this->addFailedJobPostElement( $postElement );
		}

		if ( Lst::length( $previousJobFailedElements ) !== Lst::length( $this->jobFailedElements ) ) {
			$this->updateOrDismissNotice();
		}
	}

	public function deleteElementsThatHaveJobsCreated() {
		$this->jobFailedElements = $this->readStoredElements();

		$idsToRemove = [];
		foreach ( $this->jobFailedElements as $contentId => $contentInfo ) {
			$postElement = $this->createElement( $contentId, $contentInfo );
			if ( ! $postElement ) {
				continue;
			}
			if ( Lst::length( $postElement->get_translations() ) > 1 ) {
				$idsToRemove[] = $contentId;
			}
		}

		if ( $idsToRemove ) {
			$this->jobFailedElements = $this->readStoredElements();
			foreach ( $idsToRemove as $contentId ) {
				unset( $this->jobFailedElements[ $contentId ] );
			}

			if ( Lst::length( $this->jobFailedElements ) ) {
				Option::update( self::OPTION_KEY, $this->encodedContent( $this->jobFailedElements ) );
			}
		}

		if ( ! Lst::length( $this->jobFailedElements ) ) {
			$this->deleteOption();
		}
	}

	public function addFailedJobPostElement( $postElement ) {
		$this->jobFailedElements = $this->readStoredElements();

		$this->jobFailedElements[ $this->keyFor( $postElement ) ] = [
			'title'        => $this->titleOf( $postElement ),
			'lang'         => $postElement->get_language_code(),
			'element_type' => $postElement->get_element_type(),
			'kind_slug'    => self::ELEMENT_TYPE_PACKAGE === $postElement->get_element_type()
				? $postElement->get_type()
				: null,
		];

		$encodedContent = $this->encodedContent( $this->jobFailedElements );
		Option::update( self::OPTION_KEY, $encodedContent );
	}

	private function keyFor( $element ) {
		return self::ELEMENT_TYPE_PACKAGE === $element->get_element_type()
			? self::ELEMENT_TYPE_PACKAGE . ':' . $element->get_id()
			: $element->get_id();
	}

	private function titleOf( $element ) {
		$wpObject = $element->get_wp_object();
		if ( is_object( $wpObject ) && isset( $wpObject->post_title ) ) {
			return $wpObject->post_title;
		}

		$package = apply_filters( 'wpml_st_get_string_package', null, $element->get_id() );
		$title   = is_object( $package ) && ! empty( $package->title ) ? $package->title : '';

		if ( $title ) {
			return $title;
		}

		$kind = $element->get_type();

		return $kind
			/* translators: Names one item in a list of content that could not be sent for translation. %1$s: the kind of item, for example Block, %2$d: the number that stands for it. */
			? sprintf( __( '%1$s (ID %2$d)', 'sitepress' ), $kind, $element->get_id() )
			: (string) $element->get_id();
	}

	private function createElement( $contentId, $contentInfo ) {
		$elementType = isset( $contentInfo['element_type'] ) ? $contentInfo['element_type'] : self::ELEMENT_TYPE_POST;

		try {
			if ( self::ELEMENT_TYPE_PACKAGE === $elementType ) {
				$id = (int) substr( (string) $contentId, strlen( self::ELEMENT_TYPE_PACKAGE ) + 1 );

				return $this->wpmlTranslationElementFactory->create_package(
					$id,
					isset( $contentInfo['kind_slug'] ) ? $contentInfo['kind_slug'] : ''
				);
			}

			return $this->wpmlTranslationElementFactory->create_post( $contentId );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	public function updateOrDismissNotice() {
		if ( Lst::length( $this->jobFailedElements ) ) {
			$this->displayNotice();
		} else {
			$notice = $this->wpmlNotices->get_notice( self::NOTICE_ID );
			$notice && $this->wpmlNotices->dismiss_notice( $notice );
		}
	}

	private function displayNotice() {
		$message = $this->constructMessage();
		$notice  = $this->createNotice( $message );
		$this->wpmlNotices->add_notice( $notice );
	}

	private function constructMessage() {
		$message  = '<h2 id="job_creation_fail_notice">' . __( 'WPML experienced an issue while trying to automatically translating some of your content:', 'sitepress' ) . '</h2>';
		$message .= '<ul>';
		foreach ( $this->jobFailedElements as $contentInfo ) {
			$message .= '<li class="job_creation_fail_element">' . esc_html( (string) $contentInfo['title'] ) . '</li>';
		}

		$message .= '</ul>';
		/* translators: Line under a notice about content that could not be translated automatically. %s: the address of the Translation Management screen; it fills the link tag that is already in the text, whose text is the name of that screen. */
		$message .= '<p id="job_creation_fail_tm_link">' . sprintf( __( 'To translate these items, please go to <a rel="noreferrer" href="%s">Translation Management</a> and send them for translation.', 'sitepress' ), UIPage::getTMDashboard() ) . '</p>';
		/* translators: %s is the URL of the WPML support entry point. */
		$message .= '<p id="job_creation_fail_support_link">' . sprintf( __( 'If the problem continues, contact <a target="_blank" rel="noreferrer" href="%s">WPML support</a> for assistance.', 'sitepress' ), \WPML\OutboundLinks\OutboundLinks::to( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL, array( 'medium' => 'notice', 'campaign' => 'support' ) ) ) . '</p>';

		return $message;
	}

	private function createNotice( $message ) {
		$notice = $this->wpmlNotices->create_notice( self::NOTICE_ID, $message );
		$notice->set_dismissible( true );
		$notice->reset_dismiss();
		$notice->set_css_class_types( [ 'error' ] );

		return $notice;
	}

	private function deleteOption() {
		if ( ! Option::get( self::OPTION_KEY ) ) {
			return;
		}

		Option::delete( self::OPTION_KEY );
	}

	private function encodedContent( $content ) {
		return json_encode( $content ) ?: '';
	}
}
