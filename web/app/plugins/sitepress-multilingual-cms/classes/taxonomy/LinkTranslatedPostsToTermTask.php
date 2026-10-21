<?php

namespace WPML\TM\Taxonomy;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\FP\Obj;

class LinkTranslatedPostsToTermTask extends AbstractTaskEndpoint {

	const LOCK_TIME   = 60;
	const MAX_RETRIES = 10;

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload = (array) $task->getPayload();
		$utils   = wpml_get_term_translation_util();
		$linked  = $utils->link_translated_posts_to_term(
			(int) Obj::propOr( 0, 'term_taxonomy_id', $payload ),
			(string) Obj::propOr( '', 'lang', $payload ),
			(string) Obj::propOr( '', 'taxonomy', $payload ),
			\WPML_Term_Translation_Utils::LINK_PAGE_SIZE
		);

		$task->setRetryCount( 0 );
		if ( $linked ) {
			$task->addCompletedCount( count( $linked ) );
		}
		if (
			count( $linked ) < \WPML_Term_Translation_Utils::LINK_PAGE_SIZE
			|| $task->getCompletedCount() >= $task->getTotalCount()
		) {
			$task->finish();
		}

		return $task;
	}

	public function getDescription( Collection $data ) {
		/* translators: Shown in WPML's list of background tasks while a newly translated category or tag is added to the translated posts that use it. */
		return __( 'Adding a translated category or tag to the translated posts that use it.', 'sitepress' );
	}

	public function getTotalRecords( Collection $data ) {
		return wpml_get_term_translation_util()->count_translated_posts_missing_term(
			(int) $data->get( 'term_taxonomy_id', 0 ),
			(string) $data->get( 'lang', '' ),
			(string) $data->get( 'taxonomy', '' )
		);
	}
}
