<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\FP\Either;
use WPML\Collect\Support\Collection;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\ReturnUrl;
use WPML\TM\ATE\Review\PreviewLink;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\Review\StatusIcons;
use function WPML\FP\pipe;

class GetJobsInfo implements \WPML\Ajax\IHandler {

	public function run( Collection $data ) {
		$resolver = \WPML\TM\Jobs\Authorization\AuthorizedJobResolver::make();
		$context  = \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::current();
		$jobIds   = \wpml_collect( (array) $data->get( 'jobIds', [] ) )
			->filter( function ( $jobId ) use ( $resolver, $context ) {
				return (bool) $resolver->byLocalId( $context, $jobId );
			} )
			->values()
			->toArray();

		$returnUrl = ReturnUrl::sanitize( $data->get( 'returnUrl', '' ) );

		$getNativeEditLink = function ( $job ) {
			$translatedPostId = (int) Obj::propOr( 0, 'translatedPostId', $job );

			return $translatedPostId
				? (string) \get_edit_post_link( $translatedPostId, 'url' )
				: '';
		};

		$getLink = Logic::ifElse(
			ReviewStatus::doesJobNeedReview(),
			Fns::converge( PreviewLink::getWithSpecifiedReturnUrl( $returnUrl ), [
				Obj::prop( 'translatedPostId' ),
				Obj::prop( 'jobId' )
			] ),
			pipe( Obj::prop( 'jobId' ), Jobs::getEditUrl( $returnUrl ) )
		);

		$getLabel = Logic::ifElse(
			ReviewStatus::doesJobNeedReview(),
			StatusIcons::getReviewTitle( 'language_code' ),
			StatusIcons::getEditTitle( 'language_code' )
		);

		return Either::of( \wpml_collect( $jobIds )
			->map( Jobs::get() )
			->map( Obj::addProp( 'translatedPostId', Jobs::getTranslatedPostId() ) )
			->map( Obj::renameProp( 'job_id', 'jobId' ) )
			->map( Obj::renameProp( 'editor_job_id', 'ateJobId' ) )
			->map( Obj::addProp( 'viewLink', $getLink ) )
			->map( Obj::addProp( 'nativeEditLink', $getNativeEditLink ) )
			->map( Obj::addProp( 'label', $getLabel ) )
			->map( Obj::pick( [
				'jobId',
				'viewLink',
				'nativeEditLink',
				'automatic',
				'status',
				'label',
				'review_status',
				'ateJobId'
			] ) )
			->map( Obj::evolve( [
				'jobId'     => Cast::toInt(),
				'automatic' => Cast::toInt(),
				'status'    => Cast::toInt(),
				'ateJobId'  => Cast::toInt(),
			] ) ) );

	}

}