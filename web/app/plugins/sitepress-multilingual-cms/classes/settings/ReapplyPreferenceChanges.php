<?php

namespace WPML\TM\Settings;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\FP\Obj;
use function WPML\Container\make;

class ReapplyPreferenceChanges extends AbstractTaskEndpoint {

	const LOCK_TIME         = 5;
	const MAX_RETRIES       = 10;
	const POSTS_PER_REQUEST = 10;
	const TERMS_PER_REQUEST = 10;
	const TRANSLATIONS_PER_REQUEST = 20;

	const KIND_CUSTOM_FIELDS = 'custom-fields';
	const KIND_PAGE_URL      = 'page-url';

	const UNIT_POSTS        = 'posts';
	const UNIT_TRANSLATIONS = 'translations';

	private $slugRewrite;

	private $affectedPosts;

	private $processNewTranslatableFields;

	private $elementFactory;

	private $reapplyToTerms;

	public function __construct(
		AffectedPostsQuery $affectedPosts,
		ProcessNewTranslatableFields $processNewTranslatableFields,
		\WPML_Translation_Element_Factory $elementFactory,
		UpdateBackgroundTask $updateBackgroundTask,
		BackgroundTaskService $backgroundTaskService,
		?PageUrlSlugRewrite $slugRewrite = null,
		?ReapplyToTerms $reapplyToTerms = null
	) {
		$this->affectedPosts                = $affectedPosts;
		$this->processNewTranslatableFields = $processNewTranslatableFields;
		$this->elementFactory               = $elementFactory;
		$this->slugRewrite                  = $slugRewrite ?: new PageUrlSlugRewrite();
		$this->reapplyToTerms               = $reapplyToTerms ?: make( ReapplyToTerms::class );

		parent::__construct( $updateBackgroundTask, $backgroundTaskService );
	}

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload   = (array) $task->getPayload();
		$kind      = self::kindOf( $payload );
		$changeSet = PreferenceChangeSet::fromArray( (array) Obj::propOr( [], 'changes', $payload ) );
		$processed = (array) Obj::propOr( [], 'processed', $payload );
		$lastPostId = (int) Obj::propOr( 0, 'lastPostId', $payload );
		$planned   = self::KIND_PAGE_URL === $kind && self::isPlanned( $payload );
		$remaining = [];
		if ( $planned ) {
			$remaining = self::plannedTranslationIds( $payload );
			$postIds   = array_slice( $remaining, 0, self::TRANSLATIONS_PER_REQUEST );
		} elseif ( self::KIND_PAGE_URL === $kind ) {
			$postIds = $this->affectedPosts->pageTranslated( $lastPostId, self::POSTS_PER_REQUEST );
		} else {
			$postIds = $this->nextPage( $changeSet, $this->extraPostIds( $payload ), $lastPostId );
		}

		if ( ! $postIds && self::KIND_CUSTOM_FIELDS === $kind && $this->runTermBatch( $task, $payload, $changeSet ) ) {
			return $task;
		}

		if ( $postIds ) {
			$units = count( $postIds );
			if ( $planned ) {
				$payload['processed']      = $this->rewriteTranslations( $postIds, self::pageUrlTransitionOf( $payload ), $processed );
				$payload['translationIds'] = array_slice( $remaining, count( $postIds ) );
			} elseif ( self::KIND_PAGE_URL === $kind ) {
				$payload['processed'] = $this->rewriteSlugs( $postIds, self::pageUrlTransitionOf( $payload ), $processed );
			} else {
				$carriers   = CarrierPosts::of( $postIds );
				$seenBefore = array_map( 'intval', (array) Obj::propOr( [], 'carriers', $payload ) );
				$seenBefore           = array_values( array_unique( array_merge( $seenBefore, $this->carriersWithTheirOwnTurn( $changeSet, $carriers ) ) ) );
				$seen                 = $seenBefore;
				$units                = CarrierPosts::countUnits( $carriers, $seen );
				$payload['processed'] = $this->applyToPosts( $postIds, $changeSet, $processed, $carriers, $seenBefore );
				$payload['carriers']  = $seen;
			}
			if ( ! $planned ) {
				$payload['lastPostId'] = max( $postIds );
			}
			$task->setPayload( $payload );
			$task->addCompletedCount( $units );
			$task->setRetryCount( 0 );
		} else {
			$payload['processed'] = $processed;
			$task->setPayload( $payload );
			$task->setTotalCount( $task->getCompletedCount() );
			$task->finish();
			if ( self::KIND_CUSTOM_FIELDS === $kind ) {
				$this->announceTermBatch( [], $changeSet, $task );
			}
		}

		if ( self::KIND_PAGE_URL === $kind ) {
			return $task;
		}

		do_action(
			'wpml_preference_reapply_batch_completed',
			$postIds,
			$changeSet->toArray(),
			self::summaryOf( $task )
		);

		return $task;
	}

	public function getDescription( Collection $data ) {
		if ( self::KIND_PAGE_URL === self::kindOf( $data->toArray() ) ) {
			/* translators: Title of the progress notification shown while WPML rewrites the URLs of existing translations after the Page URL setting was changed. */
			return __( 'Updating the URLs of existing translations for the changed Page URL setting.', 'sitepress' );
		}

		return sprintf(
			/* translators: %s is a comma separated list of custom field names. */
			__( 'Updating existing content for the changed field preferences: %s.', 'sitepress' ),
			implode( ', ', array_keys( (array) $data->get( 'changes', [] ) ) )
		);
	}

	public function getTotalRecords( Collection $data ) {
		if ( self::KIND_PAGE_URL === self::kindOf( $data->toArray() ) ) {
			return self::isPlanned( $data->toArray() )
				? count( self::plannedTranslationIds( $data->toArray() ) )
				: $this->affectedPosts->countTranslated();
		}

		$changeSet = PreferenceChangeSet::fromArray( (array) $data->get( 'changes', [] ) );
		$acting    = $changeSet->actingFieldNames();

		return ( $acting ? $this->affectedPosts->countCarriers( $acting ) : 0 )
			+ count( $this->extraPostIds( [ 'extraPostIds' => $data->get( 'extraPostIds', [] ) ] ) )
			+ $this->reapplyToTerms->countAffected( $changeSet )
			+ count( self::idsFromPayload( 'extraTermIds', [ 'extraTermIds' => $data->get( 'extraTermIds', [] ) ] ) );
	}

	public static function kindOf( array $payload ) {
		return self::KIND_PAGE_URL === Obj::propOr( self::KIND_CUSTOM_FIELDS, 'kind', $payload )
			? self::KIND_PAGE_URL
			: self::KIND_CUSTOM_FIELDS;
	}

	public static function summaryOf( BackgroundTask $task ) {
		$payload        = (array) $task->getPayload();
		$processed      = (array) Obj::propOr( [], 'processed', $payload );
		$processedTerms = (array) Obj::propOr( [], 'processedTerms', $payload );

		return [
			'taskId'      => $task->getTaskId(),
			'isCompleted' => (bool) $task->isStatusCompleted(),
			'total'       => (int) $task->getTotalCount(),
			'completed'   => (int) $task->getCompletedCount(),
			'unit'        => self::unitOf( $payload ),
			'processed'   => [
				PreferenceChangeSet::TRANSLATE => (int) Obj::propOr( 0, PreferenceChangeSet::TRANSLATE, $processed ),
				PreferenceChangeSet::COPY      => (int) Obj::propOr( 0, PreferenceChangeSet::COPY, $processed ),
				PreferenceChangeSet::COPY_ONCE => (int) Obj::propOr( 0, PreferenceChangeSet::COPY_ONCE, $processed ),
				PreferenceChangeSet::IGNORE    => (int) Obj::propOr( 0, PreferenceChangeSet::IGNORE, $processed ),
				'slug'                         => (int) Obj::propOr( 0, 'slug', $processed ),
				'slugUnchanged'                => (int) Obj::propOr( 0, 'slugUnchanged', $processed ),
				'sent'                         => (int) Obj::propOr( 0, 'sent', $processed ),
				'heldRange'                    => (int) Obj::propOr( 0, 'heldRange', $processed ),
				'heldLanguage'                 => (int) Obj::propOr( 0, 'heldLanguage', $processed ),
				'types'                        => (array) Obj::propOr( [], 'types', $processed ),
			],
			'processedTerms' => [
				PreferenceChangeSet::COPY      => (int) Obj::propOr( 0, PreferenceChangeSet::COPY, $processedTerms ),
				PreferenceChangeSet::COPY_ONCE => (int) Obj::propOr( 0, PreferenceChangeSet::COPY_ONCE, $processedTerms ),
			],
		];
	}

	private function typeEntry( $postType, $count ) {
		$object   = function_exists( 'get_post_type_object' ) ? get_post_type_object( $postType ) : null;
		$singular = $object && ! empty( $object->labels->singular_name ) ? (string) $object->labels->singular_name : (string) $postType;
		$plural   = $object && ! empty( $object->labels->name ) ? (string) $object->labels->name : (string) $postType;
		if ( 0 === strpos( (string) get_locale(), 'en' ) ) {
			$singular = function_exists( 'mb_strtolower' ) ? mb_strtolower( $singular ) : strtolower( $singular );
			$plural   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $plural ) : strtolower( $plural );
		}

		return [ 'count' => (int) $count, 'singular' => $singular, 'plural' => $plural ];
	}

	private function extraPostIds( array $payload ) {
		return self::idsFromPayload( 'extraPostIds', $payload );
	}

	private static function idsFromPayload( string $key, array $payload ): array {
		$ids = array_map( 'intval', (array) Obj::propOr( [], $key, $payload ) );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function carriersWithTheirOwnTurn( PreferenceChangeSet $changeSet, array $carriers ) {
		$shared = CarrierPosts::shared( $carriers );
		$acting = $changeSet->actingFieldNames();

		return $shared && $acting ? $this->affectedPosts->affectedAmong( $acting, $shared ) : [];
	}

	private function runTermBatch( BackgroundTask $task, array $payload, PreferenceChangeSet $changeSet ): bool {
		$termIds = $this->nextTermPage(
			$changeSet,
			self::idsFromPayload( 'extraTermIds', $payload ),
			(int) Obj::propOr( 0, 'lastTermId', $payload )
		);

		if ( ! $termIds ) {
			return false;
		}

		$payload['processedTerms'] = $this->reapplyToTerms->apply( $termIds, $changeSet, (array) Obj::propOr( [], 'processedTerms', $payload ) );
		$payload['lastTermId']     = max( $termIds );
		$task->setPayload( $payload );
		$task->addCompletedCount( count( $termIds ) );
		$task->setRetryCount( 0 );
		$this->announceTermBatch( $termIds, $changeSet, $task );

		return true;
	}

	private function nextTermPage( PreferenceChangeSet $changeSet, array $extraTermIds, int $lastTermId ): array {
		return self::mergeInKeysetWindow(
			$this->reapplyToTerms->page( $changeSet, $lastTermId, self::TERMS_PER_REQUEST ),
			$extraTermIds,
			$lastTermId,
			self::TERMS_PER_REQUEST
		);
	}

	private function announceTermBatch( array $termIds, PreferenceChangeSet $changeSet, BackgroundTask $task ) {
		do_action(
			'wpml_preference_reapply_terms_batch_completed',
			$termIds,
			ReapplyToTerms::termChanges( $changeSet )->toArray(),
			self::summaryOf( $task )
		);
	}

	private function nextPage( PreferenceChangeSet $changeSet, array $extraPostIds, $lastPostId ) {
		$acting = $changeSet->actingFieldNames();

		return self::mergeInKeysetWindow(
			$acting ? $this->affectedPosts->page( $acting, $lastPostId, self::POSTS_PER_REQUEST ) : [],
			$extraPostIds,
			$lastPostId,
			self::POSTS_PER_REQUEST
		);
	}

	private static function mergeInKeysetWindow( array $pageIds, array $extraIds, int $lastId, int $limit ): array {
		$remaining = array_filter(
			$extraIds,
			function ( $id ) use ( $lastId ) {
				return $id > $lastId;
			}
		);

		if ( $remaining ) {
			$pageIds = array_values( array_unique( array_merge( $pageIds, $remaining ) ) );
			sort( $pageIds );
			$pageIds = array_slice( $pageIds, 0, $limit );
		}

		return $pageIds;
	}

	private function applyToPosts( array $postIds, PreferenceChangeSet $changeSet, array $processed, array $carriers = [], array $seen = [] ) {
		$namesByTransition = $changeSet->namesByTransition();
		$needsUpdate       = [];
		$types             = isset( $processed['types'] ) && is_array( $processed['types'] ) ? $processed['types'] : [];
		$counted           = [];

		foreach ( $postIds as $postId ) {
			$postId     = (int) $postId;
			$carrierId  = isset( $carriers[ $postId ] ) ? (int) $carriers[ $postId ] : $postId;
			$sourceMeta = get_post_meta( $postId );
			$sourceMeta = is_array( $sourceMeta ) ? $sourceMeta : [];

			$isNewUnit = $carrierId === $postId || ( ! in_array( $carrierId, $counted, true ) && ! in_array( $carrierId, $seen, true ) );
			if ( $isNewUnit ) {
				$postType           = (string) get_post_type( $carrierId );
				$postType           = '' !== $postType ? $postType : 'post';
				$types[ $postType ] = $this->typeEntry( $postType, ( isset( $types[ $postType ]['count'] ) ? (int) $types[ $postType ]['count'] : 0 ) + 1 );
				if ( $carrierId !== $postId ) {
					$counted[] = $carrierId;
				}
			}

			foreach ( $namesByTransition as $transition => $names ) {
				if ( PreferenceChangeSet::IGNORE === $transition ) {
					continue;
				}

				$present = array_values( array_intersect( $names, array_keys( $sourceMeta ) ) );
				if ( ! $present ) {
					continue;
				}

				if ( PreferenceChangeSet::TRANSLATE === $transition ) {
					if ( $carrierId !== $postId && ( in_array( $carrierId, $needsUpdate, true ) || in_array( $carrierId, $seen, true ) ) ) {
						continue;
					}
					$needsUpdate[] = $carrierId;
				}

				$processed[ $transition ] = ( isset( $processed[ $transition ] ) ? (int) $processed[ $transition ] : 0 ) + 1;

				if ( PreferenceChangeSet::COPY === $transition ) {
					$this->applyCopy( $postId, $present );
				} elseif ( PreferenceChangeSet::COPY_ONCE === $transition ) {
					$this->applyCopyOnce( $postId, $present, $sourceMeta );
				}
			}
		}

		$processed['types'] = $types;

		if ( $needsUpdate ) {
			$counts = (array) $this->processNewTranslatableFields->updateNeedsUpdate( array_values( array_unique( $needsUpdate ) ) );
			foreach ( [ 'sent', 'heldRange', 'heldLanguage' ] as $key ) {
				$processed[ $key ] = ( isset( $processed[ $key ] ) ? (int) $processed[ $key ] : 0 ) + ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 );
			}
		}

		return $processed;
	}

	public static function pageUrlTransitionOf( array $payload ) {
		$transition = (string) Obj::propOr( PageUrlSlugRewrite::FORCE_GENERATE, 'transition', $payload );

		return PageUrlSlugRewrite::isRewrite( $transition ) ? $transition : PageUrlSlugRewrite::FORCE_GENERATE;
	}

	public static function isPlanned( array $payload ) {
		return array_key_exists( 'translationIds', $payload ) && is_array( $payload['translationIds'] );
	}

	public static function plannedTranslationIds( array $payload ) {
		return self::isPlanned( $payload )
			? array_values( array_filter( array_map( 'intval', $payload['translationIds'] ) ) )
			: [];
	}

	public static function unitOf( array $payload ) {
		return self::KIND_PAGE_URL === self::kindOf( $payload ) && self::isPlanned( $payload )
			? self::UNIT_TRANSLATIONS
			: self::UNIT_POSTS;
	}

	private function rewriteTranslations( array $translationIds, $transition, array $processed ) {
		$moved     = 0;
		$unchanged = 0;
		foreach ( $translationIds as $translationId ) {
			$change = $this->slugRewrite->planTranslation( (int) $translationId, $transition );
			if ( $change ) {
				$this->slugRewrite->applyChange( $change );
				$moved++;
			} else {
				$unchanged++;
			}
		}

		$processed['slug']          = ( isset( $processed['slug'] ) ? (int) $processed['slug'] : 0 ) + $moved;
		$processed['slugUnchanged'] = ( isset( $processed['slugUnchanged'] ) ? (int) $processed['slugUnchanged'] : 0 ) + $unchanged;

		return $processed;
	}

	private function rewriteSlugs( array $postIds, $transition, array $processed ) {
		$moved = 0;
		foreach ( array_values( array_unique( array_map( 'intval', $postIds ) ) ) as $postId ) {
			$moved += $this->slugRewrite->apply( $postId, $transition );
		}

		$processed['slug'] = ( isset( $processed['slug'] ) ? (int) $processed['slug'] : 0 ) + $moved;

		return $processed;
	}

	private function applyCopy( $postId, array $fieldNames ) {
		$this->syncFor( $fieldNames )->sync_all_custom_fields( $postId );
	}

	private function applyCopyOnce( $postId, array $fieldNames, array $sourceMeta ) {
		$translationIds = $this->translationIds( $postId );
		if ( ! $translationIds ) {
			return;
		}

		$sync = $this->syncFor( $fieldNames );

		foreach ( $translationIds as $translationId ) {
			$targetMeta = get_post_meta( $translationId );
			$targetMeta = is_array( $targetMeta ) ? $targetMeta : [];

			foreach ( $fieldNames as $metaKey ) {
				if ( ! isset( $sourceMeta[ $metaKey ] ) ) {
					continue;
				}

				$values = isset( $targetMeta[ $metaKey ] ) && ! empty( $targetMeta[ $metaKey ] )
					? [ $targetMeta[ $metaKey ] ]
					: [];

				$values = apply_filters(
					'wpml_custom_field_values',
					$values,
					[
						'post_id'                   => $translationId,
						'meta_key'                  => $metaKey,
						'custom_fields_translation' => WPML_COPY_ONCE_CUSTOM_FIELD,
					]
				);

				if ( empty( $values ) ) {
					$sync->sync_custom_field( $postId, $translationId, $metaKey );
				}
			}
		}
	}

	private function translationIds( $postId ) {
		$element        = $this->elementFactory->create( $postId, 'post' );
		$translationIds = [];

		foreach ( (array) $element->get_translations() as $translation ) {
			$translationId = (int) $translation->get_element_id();
			if ( $translationId && $translationId !== (int) $postId ) {
				$translationIds[] = $translationId;
			}
		}

		return $translationIds;
	}

	protected function syncFor( array $fieldNames ) {
		return new \WPML_Sync_Custom_Fields( $this->elementFactory, array_values( $fieldNames ) );
	}
}
