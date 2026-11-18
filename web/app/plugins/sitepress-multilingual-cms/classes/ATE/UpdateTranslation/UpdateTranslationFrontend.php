<?php

namespace WPML\TM\ATE\UpdateTranslation;

use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\Core\WP\App\Resources;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Hooks\ReturnCommand;
use WPML\TM\ATE\Review\PreviewLink;
use WPML\TM\Editor\ManualJobCreationErrorNotice;
use WPML\TM\Jobs\Authorization\AuthorizedJobResolver;
use WPML\UIPage;

class UpdateTranslationFrontend implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const RETURN_JOB_PARAM = 'ate-return-job';

	private $canAccessJob;

	public function __construct( ?callable $canAccessJob = null ) {
		$this->canAccessJob = $canAccessJob ?: function ( $jobId ) {
			return (bool) AuthorizedJobResolver::make()->byLocalId( ExecutionContextHolder::current(), $jobId );
		};
	}

	public function add_hooks() {
		if ( ! self::hasValidNonce() ) {
			Hooks::onAction( 'template_redirect' )
				->then( [ $this, 'handleUpdateTranslationFrontend' ] );
		}
	}

	public function handleUpdateTranslationFrontend() {
		$jobId = $this->getReturnedJobId();
		if ( ! $jobId || ! $this->shouldHandleRequest() ) {
			return;
		}

		$post = $this->getPostCurrentPostObject();
		if ( ! $post ) {
			return;
		}

		$notApplied = $this->getNotAppliedCopy();
		if ( $notApplied ) {
			$this->loadApp( $this->getAppData( $jobId, $post, false, $notApplied ) );

			return;
		}

		if ( $this->getJobIdFromCurrentPost( $post ) !== $jobId || ! $this->isDeliveryPending( $jobId ) ) {
			return;
		}

		$this->loadApp( $this->getTranslationFrontendData( $jobId, $post ) );
	}

	private function loadApp( array $data ) {
		Hooks::onAction( 'wp_footer' )
			->then( [ $this, 'printUpdateTranslationAnchor' ] );

		$enqueue = Resources::enqueueApp( 'updateTranslationFrontend' );
		$enqueue( $data );
	}

	private static function hasValidNonce() {
		$get = Obj::prop( Fns::__, $_GET );

		return (bool) \wp_verify_nonce(
			$get( 'preview_nonce' ),
			PreviewLink::getNonceName( (int) $get( 'preview_id' ) )
		);
	}

	private function getReturnedJobId() {
		$jobId = Obj::prop( self::RETURN_JOB_PARAM, $_GET );

		return is_string( $jobId ) && ctype_digit( $jobId ) ? (int) $jobId : 0;
	}

	private function getNotAppliedCopy() {
		$code    = Obj::prop( 'ateJobCreationError', $_GET );
		$outcome = Obj::prop( 'ateDeliveryOutcome', $_GET );

		if ( ! is_string( $code ) || ! ctype_digit( $code ) ) {
			return null;
		}

		$copy = NotAppliedCopy::get( (int) $code, is_string( $outcome ) ? $outcome : '', User::canManageTranslations() );

		return $copy && ManualJobCreationErrorNotice::policy()->permits() ? $copy : null;
	}

	private function shouldHandleRequest() {
		return is_user_logged_in()
			&& \WPML_TM_ATE_Status::is_enabled_and_activated()
			&& is_main_query()
			&& have_posts()
			&& is_singular();
	}

	private function isDeliveryPending( $jobId ) {
		if ( ! get_transient( ReturnCommand::completedMarkerKey( get_current_user_id(), $jobId ) ) ) {
			return false;
		}

		if ( ! call_user_func( $this->canAccessJob, $jobId ) ) {
			return false;
		}

		$job = Jobs::get( $jobId );

		return $job && Jobs::shouldBeATESynced( $job );
	}

	private function getJobIdFromCurrentPost( $post ) {
		global $sitepress;

		$elementType = 'post_' . $post->post_type;
		$trid        = $sitepress->get_element_trid( $post->ID, $elementType );
		$lang        = $sitepress->get_language_for_element( $post->ID, $elementType );

		return $trid && $lang ? (int) wpml_load_core_tm()->get_translation_job_id( $trid, $lang ) : 0;
	}

	private function getPostCurrentPostObject() {
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		return $post;
	}

	public function printUpdateTranslationAnchor() {
		echo '
            <script type="text/javascript" >
               var ajaxurl = "' . \admin_url( 'admin-ajax.php', 'relative' ) . '"
            </script>
            <div id="wpml_update_translation_frontend"></div>
        ';
	}

	public function getTranslationFrontendData( $jobId, $post ) {
		$job = Jobs::get( $jobId );
		if ( ! $job ) {
			return [];
		}

		return $this->getAppData( $jobId, $post, Jobs::shouldBeATESynced( $job ), null );
	}

	private function getAppData( $jobId, $post, $needsUpdate, $notApplied ) {
		return [
			'name' => 'updateTranslation',
			'data' => [
				'jobId'              => (int) $jobId,
				'postId'             => $post->ID,
				'completedInATE'     => 'COMPLETED',
				'isReturningFromATE' => true,
				'clickedBackInATE'   => false,
				'needsUpdate'        => $needsUpdate,
				'notApplied'         => $notApplied,
				'errorFooter'        => $this->getErrorFooter(),
				'endpoints'          => [
					'update' => UpdateTranslation::class,
				],
			],
		];
	}

	private function getErrorFooter() {
		$canManage = User::canManageTranslations();

		return [
			'linkUrl'   => admin_url( $canManage ? UIPage::getTMDashboard() : UIPage::getTranslationQueue() ),
			'linkText'  => $canManage
				/* translators: Link under a message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. It opens WPML's Translation Management screen. */
				? __( 'Go to Translation Management', 'sitepress' )
				/* translators: Link under a message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. It opens the translator's list of translation jobs. */
				: __( 'Go to Translations', 'sitepress' ),
			/* translators: Label of the button or control that closes something: a message or a bar on a page of the site, an open section, or a dialog. Verb, imperative, not the adjective meaning near. */
			'closeText' => __( 'Close', 'sitepress' ),
		];
	}
}
