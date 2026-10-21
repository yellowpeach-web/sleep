<?php

namespace WPML\TM\ATE\Review;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\TM\API\Jobs;
use function WPML\Container\make;
use function WPML\FP\pipe;

class ApproveTranslations implements IHandler {

	const REASON_OPEN_FOR_EDITING = 'open_for_editing';

	public function run( Collection $data ) {
		$jobIds           = $data->get( 'jobsIds' );
		$TranslatedPostId = function ( $arg ) {
			if ( PackageJob::isPackageJob( $arg ) || TermJob::isTermJob( $arg ) ) {
				return null;
			}
			return Jobs::getTranslatedPostId()( $arg );
		};

		$getJob = function ( $jobId ) {
			return Jobs::get( $jobId ) ?: TermJob::get( (int) $jobId );
		};

		$approve = pipe(
			Obj::addProp( 'translated_id', $TranslatedPostId ),
			Obj::props( [ 'job_id', 'translated_id', 'element_type_prefix' ] ),
			Lst::zipObj( [ 'jobId', 'postId', 'element_type_prefix' ] ),
			'wpml_collect',
			[ make( AcceptTranslation::class ), 'run' ],
			fn( Either $result ) => [
				'jobId'  => $result->coalesce( Fns::identity(), Fns::identity() )->getOrElse( 0 ),
				'status' => Fns::isRight( $result ),
			]
		);

		$refuseOpenForEditing = fn( $job ) => [
			'jobId'  => (int) Obj::prop( 'job_id', $job ),
			'status' => false,
			'reason' => self::REASON_OPEN_FOR_EDITING,
		];

		return wpml_collect( $jobIds )
			->map( $getJob )
			->filter( ReviewStatus::doesJobNeedReview() )
			->map( Logic::ifElse( Relation::propEq( 'review_status', ReviewStatus::EDITING ), $refuseOpenForEditing, $approve ) );
	}
}
