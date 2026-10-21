<?php

namespace WPML\TM\API;

use WPML\Collect\Support\Traits\Macroable;
use WPML\Element\API\PostTranslations;
use WPML\Element\API\TranslationsRepository;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\FP\Lst;
use WPML\Settings\PostType\Automatic;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\API\Job\Map;
use WPML\TM\ATE\Review\ReviewStatusLookup;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Records\UpdateTranslationReviewStatus;
use WPML\Translation\TranslateJobErrorServiceFactory;
use function WPML\Container\make;
use function WPML\FP\curryN;
use function WPML\FP\pipe;

class Jobs {
	use Macroable;

	const SENT_MANUALLY      = 1;
	const SENT_AUTOMATICALLY = 3;
	const SENT_FROM_REVIEW   = 4;
	const SENT_RETRY         = 5;
	const SENT_VIA_DASHBOARD = 6;

	public static function init() {

		self::macro( 'getPostJob', curryN( 3, function ( $postId, $postType, $language ) {
			return self::getElementJob( $postId, 'post_' . $postType, $language );
		} ) );


		self::macro( 'getTridJob', curryN( 2, function ( $trid, $language ) {
			$result = TranslationsRepository::getByTridAndLanguage( $trid, $language );
			if ( $result ) {
				return $result;
			}
			$jobId = wpml_load_core_tm()->get_translation_job_id( $trid, $language );

			return $jobId ? wpml_tm_load_job_factory()->get_translation_job_as_stdclass( $jobId ) : null;
		} ) );

		self::macro( 'setNotTranslatedStatus', self::setStatus( Fns::__, ICL_TM_NOT_TRANSLATED ) );

		self::macro( 'setTranslationService', curryN( 2, function ( $jobId, $translationService ) {
			return self::updateTranslationStatusField( $jobId, 'translation_service', $translationService, '%s' );
		} ) );

		self::macro( 'clearReviewStatus', self::setReviewStatus( Fns::__, null ) );

		self::macro( 'incrementRetryCount', curryN( 1, function ( $jobId ) {
			$job = self::get( $jobId );

			return $job && isset( $job->ate_comm_retry_count )
				? self::updateTranslationStatusField(
					$jobId,
					'ate_comm_retry_count',
					$job->ate_comm_retry_count + 1
				 )
				: null;
		} ) );

		self::macro( 'getTranslation', curryN( 1, Fns::converge( Obj::prop(), [
			Obj::prop( 'language_code' ),
			pipe( Obj::prop( 'original_doc_id' ), Fns::memorize( PostTranslations::get() ) )
		] ) ) );

		self::macro( 'getTranslatedPostId', curryN( 1, pipe( self::getTranslation(), Obj::prop( 'element_id' ) ) ) );


		self::macro( 'setTranslated', curryN( 2, function ( $jobId, $status ) {
			return self::updateTranslateJobField( $jobId, 'translated', $status );
		} ) );

		self::macro( 'clearTranslated', self::setTranslated( Fns::__, false ) );

		self::macro( 'clearAutomatic', curryN( 1, function ( $jobId ) {
			return self::updateTranslateJobField( $jobId, 'automatic', 0 );
		} ) );

		self::macro( 'delete', curryN( 1, function ( $jobId ) {
			global $wpdb;

			$rid           = Map::fromJobId( $jobId );

			$wpdb->delete(
				$wpdb->prefix . 'icl_translation_status',
				[ 'rid' => $rid ],
				[ 'rid' => '%d' ]
			);

			\WPML_Translation_Records_Delete::jobs_by_ids( [ $jobId ] );

			$service = TranslateJobErrorServiceFactory::create();
			$service->deleteError( $jobId );
		} ) );

		self::macro( 'isEligibleForAutomaticTranslations', curryN( 1, Fns::memorize( function ( $wpmlJobId ) {
			$getPostType = pipe( Obj::prop( 'original_post_type' ), Str::replace( 'post_', '' ) );
			return Maybe::of( $wpmlJobId )
			            ->map( Jobs::get() )
			            ->map( Logic::both(
				            pipe( $getPostType, [ Automatic::class, 'shouldTranslate' ] ),
							function ( $job ) {
								return LanguageMappings::isCodeEligibleForAutomaticTranslations(
									Obj::prop( 'language_code', $job ),
									Obj::prop( 'source_language_code', $job )
								);
							}
			            ) )
			            ->getOrElse( false );
		} ) ) );
	}

	public static function getCurrentUrl() {
		if ( ! isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			return admin_url();
		}

		$protocol = ( ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) || Obj::prop( 'SERVER_PORT', $_SERVER ) == 443 ) ? "https://" : "http://";

		return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	public static function shouldBeATESynced( $job ) {
		$statuses = [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ];

		return Lst::includes( (int) Obj::prop( 'status', $job ), $statuses ) &&
		       Obj::prop( 'editor', $job ) === \WPML_TM_Editors::ATE;
	}

	public static function isDeliveredAndCurrent( $translation ) {
		if ( ! $translation ) {
			return false;
		}

		return ICL_TM_COMPLETE === (int) Obj::prop( 'status', $translation )
		       && 1 !== (int) Obj::prop( 'needs_update', $translation );
	}

	public static function setAutomaticStatus( $jobId, $isAutomatic ) {
		self::updateTranslateJobField( $jobId, 'automatic', $isAutomatic ? 1 : 0 );

		if ( $isAutomatic ) {
			self::updateTranslateJobField( $jobId, 'translator_id', 0 );
			self::updateTranslationStatusField( $jobId, 'translator_id', 0 );
			self::setStatus( $jobId, ICL_TM_IN_PROGRESS );
		}
	}

	public static function setSentFrom( $jobId, $sentFrom ) {
		if ( null === $sentFrom ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job
					SET sent_from = %d
					WHERE job_id = %d AND sent_from IS NULL",
				(int) $sentFrom,
				(int) $jobId
			)
		);
	}

	public static function computeWordsLifetimeMax( $jobId ) {
		global $wpdb;

		$current = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, wpml_words_to_translate_count_lifetime_max
				 FROM {$wpdb->prefix}icl_translate_job
				 WHERE job_id = %d",
				$jobId
			)
		);

		if ( ! $current ) {
			return 0;
		}

		if ( ! empty( $current->wpml_words_to_translate_count_lifetime_max ) ) {
			return (int) $current->wpml_words_to_translate_count_lifetime_max;
		}

		$max = self::previousJobsLifetimeMax( (int) $current->rid, $jobId );

		if ( $max > 0 ) {
			self::setWordsLifetimeMax( $jobId, $max );
		}

		return $max;
	}

	public static function previousJobsLifetimeMax( $rid, $jobId ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(
					MAX( wpml_words_to_translate_count_lifetime_max ),
					MAX( wpml_words_to_translate_count ),
					0
				)
				FROM {$wpdb->prefix}icl_translate_job
				WHERE rid = %d AND job_id < %d AND sent_from = %d",
				$rid,
				$jobId,
				self::SENT_AUTOMATICALLY
			)
		);
	}

	public static function setWordsLifetimeMax( $jobId, $count ) {
		return self::updateTranslateJobField( $jobId, 'wpml_words_to_translate_count_lifetime_max', (int) $count );
	}

	public static function wasCreatedByTEA( $jobId ) {
		$job = self::get( $jobId );

		return $job
			&& isset( $job->sent_from )
			&& self::SENT_AUTOMATICALLY === (int) $job->sent_from;
	}

	public static function setStatus( $jobId = null, $status = null ) {
		return call_user_func_array(
			curryN(
				2,
				function ( $jobId, $status ) {
					return self::updateTranslationStatusField(
						$jobId,
						'status',
						$status
					);
				}
			),
			func_get_args()
		);
	}


	public static function setNeedsUpdate( $jobId ) {
		return self::updateTranslationStatusField( $jobId, 'needs_update', 1 );
	}


	public static function setReviewStatus( $jobId = null, $status = null ) {
		return call_user_func_array(
			curryN(
				2,
				function ( $jobId, $status ) {
					$result = self::updateTranslationStatusField(
						$jobId,
						'review_status',
						$status,
						'%s'
					);

					ReviewStatusLookup::invalidate();

					return $result;
				}
			),
			func_get_args()
		);
	}


	public static function get( $jobId = null ) {
		return call_user_func_array(
			curryN(
				1,
				function ( $jobId ) {
					return wpml_tm_load_job_factory()->get_translation_job_as_stdclass( $jobId );
				}
			),
			func_get_args()
		);
	}

	public static function getEditUrl( $returnUrl = null, $jobId = null ) {
		return call_user_func_array(
			curryN(
				2,
				function ( $returnUrl, $jobId ) {
					$jobEditUrl = admin_url( TranslationQueuePage::base()
						. '&job_id='
						. $jobId
						. '&return_url=' . urlencode( $returnUrl ) );

					return apply_filters( 'icl_job_edit_url', $jobEditUrl, $jobId );
				}
			),
			func_get_args()
		);
	}

	public static function getElementJob( $postId = null, $elementType = null, $language = null ) {
		return call_user_func_array(
			curryN(
				3,
				function ( $postId, $elementType, $language ) {
					global $sitepress;

					$trid = $sitepress->get_element_trid( $postId, $elementType );

					return self::getTridJob( $trid, $language );
				}
			),
			func_get_args()
		);
	}

	public static function getStatus( $jobId ) {
		global $wpdb;

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.status FROM {$wpdb->prefix}icl_translation_status s
				 INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				 WHERE j.job_id = %d",
				$jobId
			)
		);

		return null === $status ? null : (int) $status;
	}

	public static function isCancelled( int $jobId ): bool {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ts.status, j.translated FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				 WHERE j.job_id = %d
				   AND j.job_id = ( SELECT MAX( newer.job_id ) FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = j.rid )",
				$jobId
			)
		);

		if ( ! is_object( $row ) ) {
			return false;
		}

		$status = (int) $row->status;

		return ICL_TM_ATE_CANCELLED === $status
			|| ( ! (int) $row->translated && in_array( $status, [ ICL_TM_NOT_TRANSLATED, ICL_TM_COMPLETE, ICL_TM_DUPLICATE ], true ) );
	}

	public static function isAutomatic( $jobId ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT j.automatic FROM {$wpdb->prefix}icl_translate_job j
				 WHERE j.job_id = %d",
				$jobId
			)
		);
	}

	private static function updateTranslationStatusField( $jobId, $fieldName, $newValue, $fieldType = '%d' ) {
		global $wpdb;

		$shouldLog = 'status' === $fieldName
			&& class_exists( JobLog::class )
			&& JobLog::canLog();
		$oldStatus = null;
		if ( $shouldLog ) {
			$oldStatus = $wpdb->get_var( $wpdb->prepare(
				"SELECT s.status FROM {$wpdb->prefix}icl_translation_status s
				 INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = s.rid
				 WHERE j.job_id = %d",
				$jobId
			) );
		}

		$newValueSqlString = null === $newValue ? 'NULL' : $fieldType;
		$unpreparedQuery = "
					UPDATE {$wpdb->prefix}icl_translation_status
						SET `{$fieldName}` = {$newValueSqlString}
						WHERE rid = (
						    SELECT rid FROM {$wpdb->prefix}icl_translate_job
						    WHERE job_id = %d
						)
					";

		if ( null === $newValue ) {
			$query = $wpdb->prepare( $unpreparedQuery, $jobId );
		} else {
			$query = $wpdb->prepare( $unpreparedQuery, $newValue, $jobId );
		}

		$affected = $wpdb->query( $query );
		if ( $affected > 0 ) {
			do_action( 'wpml_tm_ate_jobs_updated', [ $jobId ] );
		}

		if ( $shouldLog ) {
			JobLog::add( 'job_status_set', [
				'job_id'     => (int) $jobId,
				'old_status' => null === $oldStatus ? null : (int) $oldStatus,
				'new_status' => null === $newValue ? null : (int) $newValue,
				'affected'   => false === $affected ? false : (int) $affected,
			] );
		}

		return $jobId;
	}

	private static function updateTranslateJobField( $jobId, $fieldName, $newValue ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'icl_translate_job',
			[ $fieldName => $newValue ],
			[ 'job_id' => $jobId ]
		);

		return $jobId;
	}

	public static function getPreviousJob( $jobId ) {
		global $wpdb;

		$sql = "
			SELECT * FROM {$wpdb->prefix}icl_translate_job job
			WHERE job.job_id < %d AND job.rid = (
				SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d
			)
			ORDER BY job.job_id DESC
		";

		return $wpdb->get_row( $wpdb->prepare( $sql, $jobId, $jobId ) );
	}
}

Jobs::init();
