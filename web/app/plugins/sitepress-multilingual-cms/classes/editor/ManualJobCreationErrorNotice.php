<?php

namespace WPML\TM\Editor;

use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\TM\API\Jobs;
use WPML\UIPage;
use function WPML\Container\make;
use function WPML\FP\pipe;

class ManualJobCreationErrorNotice implements \IWPML_Backend_Action {

	const RETRY_LIMIT = 3;

	const ROUTE = 'ate-job-creation-error-notice';

	public function add_hooks() {
		if ( \WPML_TM_ATE_Status::is_enabled() ) {

			Hooks::onAction( 'wp_loaded' )
			     ->then( function () {
				     $notices = make( \WPML_Notices::class );

				     if ( isset( $_GET['ateJobCreationError'] ) ) {
					     if ( ! self::policy()->permits() ) {
						     return;
					     }
					     $request = $_GET;
					     $notice  = $notices->create_notice( __CLASS__, $this->getContent( $request ) );

					     $notice->set_css_class_types( self::isAutomaticTranslationRunning( $request ) ? 'info' : 'error' );
					     $notice->set_dismissible( false );

					     $notices->add_notice( $notice );
				     } else {
					     $notices->remove_notice( 'default', __CLASS__ );
				     }
			     } );
		}
	}

	public static function policy() {
		return \WPML\Request\Policy\Registry::declare(
			\WPML\Request\Policy\Registry::PSEUDO_ROUTE,
			self::ROUTE,
			\WPML\Request\Policy\Policy::capability(
				[ 'translate', 'manage_translations' ],
				\WPML\Request\Policy\Authenticity::none( 'WPML\'s own redirect after a failed ATE job creation or a refused return delivery; the notice text comes from fixed templates, the job id only selects the retry link and the outcome only the fixed text' )
			)
		);
	}

	private function getContent( array $params ) {
		$isATENotActiveError   = pipe( Obj::prop( 'ateJobCreationError' ), Cast::toInt(), Relation::equals( Editor::ATE_IS_NOT_ACTIVE ) );
		$isCancelledInWpml     = pipe( Obj::prop( 'ateJobCreationError' ), Cast::toInt(), Relation::equals( Editor::ATE_JOB_CANCELLED_IN_WPML ) );
		$isSiteReconnecting    = pipe( Obj::prop( 'ateJobCreationError' ), Cast::toInt(), Relation::equals( Editor::ATE_SITE_RECONNECTING ) );
		$isDeliveryNotApplied  = pipe( Obj::prop( 'ateJobCreationError' ), Cast::toInt(), Relation::equals( Editor::ATE_DELIVERY_NOT_APPLIED ) );
		$isRetryLimitExceeded  = pipe( Obj::prop( 'jobId' ), [ ATERetry::class, 'getCount' ], Relation::gt( self::RETRY_LIMIT ) );

		return Logic::cond( [
			[ $isATENotActiveError, [ self::class, 'ateNotActiveMessage' ] ],
			[ $isCancelledInWpml, [ self::class, 'cancelledInWpmlMessage' ] ],
			[ $isSiteReconnecting, [ self::class, 'siteReconnectingMessage' ] ],
			[ $isDeliveryNotApplied, [ self::class, 'deliveryNotAppliedMessage' ] ],
			[ [ self::class, 'isAutomaticTranslationRunning' ], [ self::class, 'automaticTranslationRunningMessage' ] ],
			[ $isRetryLimitExceeded, [ self::class, 'retryMessage' ] ],
			[ Fns::always( true ), [ self::class, 'retryFailedMessage' ] ]
		], $params );
	}

	public static function isAutomaticTranslationRunning( array $params ) {
		return Editor::ATE_AUTOMATIC_TRANSLATION_RUNNING === (int) Obj::prop( 'ateJobCreationError', $params );
	}

	public static function automaticTranslationRunningMessage( array $params ) {
		global $sitepress;

		$job      = is_numeric( Obj::prop( 'jobId', $params ) ) ? Jobs::get( (int) Obj::prop( 'jobId', $params ) ) : null;
		$code     = $job ? (string) Obj::prop( 'language_code', $job ) : '';
		$language = $code && $sitepress ? $sitepress->get_language_details( $code ) : null;
		$name     = is_array( $language ) && isset( $language['display_name'] ) ? $language['display_name'] : $code;

		return '<div>'
			/* translators: Status of a translation in the list of content: the machine has not translated it yet. %s: the name of the language. */
			. esc_html( sprintf( __( '%s: Waiting for automatic translation', 'sitepress' ), $name ) )
			. '</div>';
	}

	public static function deliveryNotAppliedMessage( array $params ) {
		$outcome = Obj::prop( 'ateDeliveryOutcome', $params );

		return '<div>' . self::deliveryNotAppliedText( is_string( $outcome ) ? $outcome : '' ) . '</div>';
	}

	private static function deliveryNotAppliedText( $outcome ) {
		switch ( $outcome ) {
			case \WPML\TM\ATE\Receive\SingleJobDelivery::CLIENT_EDITS:
				/* translators: Error notice after coming back from the Advanced Translation Editor when the finished translation was not saved because the translation is being edited. */
				return esc_html__( 'The translation from the Advanced Translation Editor was not saved because the translation is being edited. To translate it, send the original to translation again.', 'sitepress' );

			case \WPML\TM\ATE\Receive\SingleJobDelivery::APPLY_FAILED:
				/* translators: Error notice after coming back from the Advanced Translation Editor when saving the finished translation failed. */
				return esc_html__( 'The translation from the Advanced Translation Editor could not be saved. WPML will try to deliver it again on its own; if it keeps failing, send the original to translation again.', 'sitepress' );

			case \WPML\TM\ATE\Receive\SingleJobDelivery::SUPERSEDED:
				/* translators: Error notice after coming back from the Advanced Translation Editor when a newer translation job replaced the one that was just finished. */
				return esc_html__( 'The translation from the Advanced Translation Editor was not applied because a newer translation job has replaced it. WPML will deliver the newer job.', 'sitepress' );

			case \WPML\TM\ATE\Receive\SingleJobDelivery::CLOSED:
				/* translators: Error notice after coming back from the Advanced Translation Editor when the translation request was closed while the editor was open. */
				return esc_html__( 'The translation from the Advanced Translation Editor was not applied because the translation request was closed meanwhile.', 'sitepress' );

			default:
				/* translators: Error notice after coming back from the Advanced Translation Editor when the finished translation was not applied, without a named reason. */
				return esc_html__( 'The translation from the Advanced Translation Editor was not applied. To translate this content, send the original to translation again.', 'sitepress' );
		}
	}

	public static function cancelledInWpmlMessage() {
		return '<div>'
			/* translators: Error notice after coming back from the translation editor when the job finished there had already been cancelled in WPML. */
			. esc_html__( 'This translation job was cancelled in WPML before it was completed. The translation you saved in the editor was not applied. To translate this page, send it to translation again.', 'sitepress' )
			. '</div>';
	}

	public static function siteReconnectingMessage() {
		return '<div>'
			/* translators: Error notice after a translation could not open in the Advanced Translation Editor because the translation service no longer recognizes the site's address. */
			. esc_html__( 'This translation can\'t open in the Advanced Translation Editor yet. The translation service doesn\'t recognize this site\'s new address, and WPML is reconnecting the site on its own. Open the translation again in a few minutes.', 'sitepress' )
			. ' <a href="' . esc_url( UIPage::getTMDashboard() ) . '">'
			/* translators: Link in an error notice; it opens the screen that shows whether WPML is connected to the translation service. */
			. esc_html__( 'See the connection status', 'sitepress' )
			. '</a></div>';
	}

	public static function retryMessage( array $params ) {
		$returnUrl  = \remove_query_arg( [ 'ateJobCreationError', 'jobId' ], Jobs::getCurrentUrl() );
		$jobId      = Obj::prop( 'jobId', $params );

		if ( ! is_numeric( $jobId ) ) {
			return sprintf(
				'<div class="wpml-display-flex wpml-display-flex-center">%1$s</div>',
				__( "WPML didn't manage to translate this page.", 'sitepress' )
			);
		}

		$jobEditUrl = Jobs::getEditUrl( $returnUrl, (int) $jobId );

		$fallbackErrorMessage = sprintf(
			'<div class="wpml-display-flex wpml-display-flex-center">%1$s <a class="button wpml-margin-left-sm" href="%2$s">%3$s</a></div>',
			__( "WPML didn't manage to translate this page.", 'sitepress' ),
			$jobEditUrl,
			/* translators: Button label in an error notice: do the same thing once more. Verb phrase, imperative. */
			__( 'Try again', 'sitepress' )
		);

		$tryAgainTextLink = sprintf( '<a href="%1$s">%2$s</a>',
			$jobEditUrl,
			/* translators: Button label in an error notice: do the same thing once more. Verb phrase, imperative. */
			__( 'Try again', 'sitepress' ) );

		$ateApiErrorMessage = ATEDetailedErrorMessage::readDetailedError( $tryAgainTextLink );

		return $ateApiErrorMessage ?: $fallbackErrorMessage;
	}

	public static function retryFailedMessage() {
		$support_url          = \WPML\OutboundLinks\OutboundLinks::to(
			\WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL,
			array(
				'medium'   => 'notice',
				'campaign' => 'support',
			)
		);
		$fallbackErrorMessage = '<div>' .
		                        sprintf(
			                        /* translators: %s: link to WPML support. */
			                        __( 'WPML tried to translate this page three times and failed. To get it fixed, contact %s', 'sitepress' ),
			                        /* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. It is the name of the support team, a noun. */
			                        '<a target=\'_blank\' href="' . esc_url( $support_url ) . '">' . __( 'WPML support', 'sitepress' ) . '</a>'
		                        ) . '</div>';

		$ateApiErrorMessage = ATEDetailedErrorMessage::readDetailedError();

		return $ateApiErrorMessage ?: $fallbackErrorMessage;

	}

	public static function ateNotActiveMessage() {
		return '<div>' .
		       sprintf(
			       /* translators: %s: link to the Translation Management Dashboard. */
			       __( 'WPML’s <b>Advanced Translation Editor</b> is enabled but not activated. Go to %s to resolve the issue.', 'sitepress' ),
			       '<a href="' . UIPage::getTMDashboard() . '">' . __( 'WPML Translation Management Dashboard', 'sitepress' ) . '</a>'
		       )
		       . '</div>';
	}
}
