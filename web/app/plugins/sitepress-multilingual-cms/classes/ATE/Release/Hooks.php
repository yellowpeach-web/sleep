<?php

namespace WPML\TM\ATE\Release;

use WPML\TM\Jobs\JobLog;

class Hooks implements \IWPML_Action {

	private $handledPosts = [];

	public function add_hooks() {
		add_action( 'wp_trash_post', [ $this, 'cancelJobsOfPost' ] );
		add_action( 'before_delete_post', [ $this, 'cancelJobsOfPost' ] );
		add_action( 'pre_delete_term', [ $this, 'cancelJobsOfTerm' ], 10, 2 );

		add_action( 'edit_form_top', [ $this, 'renderUpdateNotice' ] );
		add_action( 'admin_notices', [ $this, 'renderTrashNotice' ] );

		add_filter( 'wpml_release_ledger_url', [ ReleaseNotices::class, 'fillLedgerUrl' ] );
	}


	public function cancelJobsOfPost( $postId ) {
		$postId = (int) $postId;

		if ( $postId <= 0 || isset( $this->handledPosts[ $postId ] ) ) {
			return;
		}

		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return;
		}

		$this->handledPosts[ $postId ] = true;

		$jobs = InFlightChargedJobs::jobsForPost( $postId );

		if ( ! $jobs ) {
			return;
		}

		$counts = InFlightChargedJobs::forJobIds(
			array_map(
				function ( $job ) {
					return $job['job_id'];
				},
				$jobs
			)
		);

		$this->cancelJobs( $jobs, 'post_deleted', [ 'post_id' => $postId ] );

		if ( InFlightChargedJobs::moneyMoved( $counts ) ) {
			ReleaseNotices::queueTrashNotice( $postId, InFlightChargedJobs::countForCopy( $counts ) );
		}
	}


	public function cancelJobsOfTerm( $term, $taxonomy ) {
		if ( ! is_string( $taxonomy ) || '' === $taxonomy ) {
			return;
		}

		$termTaxonomy = get_term( (int) $term, $taxonomy );

		if ( ! $termTaxonomy instanceof \WP_Term ) {
			return;
		}

		$jobs = InFlightChargedJobs::jobsForTerm( $termTaxonomy->term_taxonomy_id, $taxonomy );

		if ( ! $jobs ) {
			return;
		}

		$this->cancelJobs(
			$jobs,
			'term_deleted',
			[
				'element_id'   => (int) $termTaxonomy->term_taxonomy_id,
				'element_type' => 'tax_' . $taxonomy,
			]
		);
	}


	private function cancelJobs( array $jobs, $event, array $context ) {
		JobLog::maybeInitRequest();

		$ownsGroup = ! JobLog::isGroupOpen();

		if ( $ownsGroup ) {
			JobLog::createNewGroup(
				JobLog::GROUP_ID_JOB_LIFECYCLE,
				'Cancel the translations of deleted content',
				array_merge(
					$context,
					[
						'reason'    => \WPML_TM_ATE_API::CANCEL_REASON_DELETED,
						'job_count' => count( $jobs ),
					]
				)
			);
		}

		foreach ( $context as $key => $value ) {
			JobLog::addExtraLogData( $key, $value );
		}

		try {
			JobLog::add(
				$event,
				array_merge(
					$context,
					[
						'reason' => \WPML_TM_ATE_API::CANCEL_REASON_DELETED,
						'jobs'   => array_map(
							function ( $job ) {
								return [ 'job_id' => $job['job_id'] ];
							},
							$jobs
						),
					]
				)
			);

			foreach ( $jobs as $job ) {
				$entity = new \WPML_TM_Post_Job_Entity(
					(int) $job['rid'],
					\WPML_TM_Job_Entity::POST_TYPE,
					0,
					new \WPML_TM_Jobs_Batch( 0, '' ),
					ICL_TM_IN_PROGRESS,
					[]
				);
				$entity->set_translate_job_id( (int) $job['job_id'] );
				$entity->set_translation_service( 'local' );
				$entity->set_editor( \WPML_TM_Editors::ATE );
				$entity->set_editor_job_id( (int) $job['editor_job_id'] );

				$this->announce( $entity, \WPML_TM_ATE_API::CANCEL_REASON_DELETED );
			}

			$this->revertLocalRows(
				array_map(
					function ( $job ) {
						return $job['rid'];
					},
					$jobs
				)
			);
		} finally {
			foreach ( array_keys( $context ) as $key ) {
				JobLog::removeExtraLogData( $key );
			}

			if ( $ownsGroup ) {
				JobLog::finishCurrentGroup();
			}
		}
	}


	protected function announce( \WPML_TM_Post_Job_Entity $job, $reason ) {
		do_action( 'wpml_tm_job_cancelled', $job, $reason );
	}

	private function revertLocalRows( array $rids ) {
		\WPML\Translation\PreviousStateServiceFactory::create()->revertInFlight(
			array_values( array_unique( array_filter( array_map( 'intval', $rids ) ) ) ),
			[ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ],
			ICL_TM_ATE_CANCELLED
		);
	}


	public function renderUpdateNotice( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ReleaseNotices::consume( ReleaseNotices::KIND_UPDATE, $post->ID ) ) {
			$this->renderNotice( ReleaseNotices::updateNoticeText() );
		}

		if ( ReleaseNotices::consume( ReleaseNotices::KIND_MANUAL_SAVE, $post->ID ) ) {
			$this->renderNotice( ReleaseNotices::manualSaveNoticeText() );
		}
	}


	public function renderTrashNotice() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		$entries = ReleaseNotices::consume( ReleaseNotices::KIND_TRASH );

		if ( ! $entries ) {
			return;
		}

		$jobs = 0;
		foreach ( $entries as $entry ) {
			$jobs += (int) $entry['jobs'];
		}

		if ( $jobs <= 0 ) {
			return;
		}

		$this->renderNotice(
			sprintf(
				/* translators: %d: number of translations that were cancelled. */
				_n(
					'%d translation in progress for this page was cancelled. You are not charged for it.',
					'%d translations in progress for this page were cancelled. You are not charged for them.',
					$jobs,
					'sitepress'
				),
				$jobs
			)
		);
	}


	private function renderNotice( $text ) {
		$html = '<div class="notice notice-info is-dismissible wpml-release-notice"><p>'
			. esc_html( $text )
			. ' '
			. sprintf(
				/* translators: Line added under a message about automatic translation. %s: the reason the service gave. */
				esc_html__( 'Details: %s.', 'sitepress' ),
				ReleaseNotices::ledgerLink()
			)
			. '</p></div>';

		echo wp_kses(
			$html,
			[
				'div' => [ 'class' => [] ],
				'p'   => [],
				'a'   => [ 'href' => [] ],
			]
		);
	}

}
