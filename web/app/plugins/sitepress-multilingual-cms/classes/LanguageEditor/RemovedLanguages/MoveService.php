<?php

namespace WPML\LanguageEditor\RemovedLanguages;

use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\Save\Phase\LanguageStringTranslations;
use WPML\LanguageEditor\TranslationPause;
use WPML\OperationRecord\Repository;
use WPML\Posts\TranslatedContentOfLanguages;
use WPML\Troubleshooting\AttachTranslatedContent;

class MoveService {

	const ERROR_MISSING_CODE = 'missing_code';

	const ERROR_SAME_LANGUAGE = 'same_language';

	const ERROR_SOURCE_NOT_REMOVED = 'source_not_removed';

	const ERROR_SOURCE_HAS_NO_CONTENT = 'source_has_no_content';

	const ERROR_TARGET_NOT_ACTIVE = 'target_not_active';

	const ERROR_CONFIRMATION_REQUIRED = 'confirmation_required';

	const SKIP_TRID_CONFLICT = 'trid-conflict';

	const ON_COLLISION_SKIP = 'skip';

	const ON_COLLISION_MERGE = 'merge';

	const COUNT_KEYS = array( 'content', 'sources', 'stringTranslations', 'strings' );

	const RECORD_LINES = array(
		'content'            => 'content',
		'sources'            => 'sources',
		'stringTranslations' => LanguageStringTranslations::RECORD_TYPE,
		'strings'            => 'strings',
	);

	public static function targetsFor( $sourceCode ) {
		$source = (string) $sourceCode;

		if ( '' === $source || ! self::isRemoved( $source ) || ! self::hasContent( $source ) ) {
			return array();
		}

		$candidates = array();
		foreach ( self::activeCodes() as $code ) {
			if ( $code !== $source ) {
				$candidates[] = $code;
			}
		}

		if ( ! $candidates ) {
			return array();
		}

		$head         = LanguageCodeResolution::head( $source );
		$names        = DisplayNames::forCodes( $candidates );
		$displayCodes = Directory::displayCodes( $candidates );

		$tier1 = array();
		$tier2 = array();

		foreach ( $candidates as $code ) {
			$displayCode = isset( $displayCodes[ $code ] ) && '' !== $displayCodes[ $code ]
				? $displayCodes[ $code ]
				: $code;

			$entry = array(
				'code'                  => $code,
				'displayCode'           => $displayCode,
				'name'                  => isset( $names[ $code ] ) && $names[ $code ] !== $code
					? $names[ $code ]
					: $displayCode,
				'requires_confirmation' => false,
			);

			if ( LanguageCodeResolution::head( $code ) === $head ) {
				$tier1[] = $entry;
				continue;
			}

			$entry['requires_confirmation'] = true;
			$tier2[]                        = $entry;
		}

		return array_merge( $tier1, $tier2 );
	}

	public static function move( $sourceCode, $targetCode, $confirmed, $onCollision = self::ON_COLLISION_SKIP, $dryRun = false ) {
		$source      = (string) $sourceCode;
		$target      = (string) $targetCode;
		$confirmed   = (bool) $confirmed;
		$onCollision = self::ON_COLLISION_MERGE === $onCollision ? self::ON_COLLISION_MERGE : self::ON_COLLISION_SKIP;

		if ( '' === $source || '' === $target ) {
			return self::refusal( $source, $target, $confirmed, 0, self::ERROR_MISSING_CODE );
		}

		if ( $source === $target ) {
			return self::refusal( $source, $target, $confirmed, 0, self::ERROR_SAME_LANGUAGE );
		}

		if ( ! self::isRemoved( $source ) ) {
			return self::refusal( $source, $target, $confirmed, 0, self::ERROR_SOURCE_NOT_REMOVED );
		}

		if ( ! self::hasContent( $source ) ) {
			return self::refusal( $source, $target, $confirmed, 0, self::ERROR_SOURCE_HAS_NO_CONTENT );
		}

		if ( ! in_array( $target, self::activeCodes(), true ) ) {
			return self::refusal( $source, $target, $confirmed, 0, self::ERROR_TARGET_NOT_ACTIVE );
		}

		$tier = LanguageCodeResolution::head( $source ) === LanguageCodeResolution::head( $target ) ? 1 : 2;

		if ( 2 === $tier && ! $confirmed ) {
			return self::refusal( $source, $target, $confirmed, $tier, self::ERROR_CONFIRMATION_REQUIRED );
		}

		$probe      = AttachTranslatedContent::run( $source, $target, true );
		$collisions = MergeService::collisions( $probe['collidingRows'] );

		$otherCollisions = max( 0, count( $probe['collidingRows'] ) - $collisions['total'] );

		if ( $dryRun && $collisions['total'] > 0 ) {
			return self::dryRunAnswer( $source, $target, $confirmed, $tier, $collisions, $otherCollisions );
		}

		$repository = new Repository();

		$recordId = $repository->start( Repository::KIND_MOVE, array( $source, $target ) );

		$panelRow = TranslatedContentOfLanguages::counts( array( $source ) );
		$before   = (int) $panelRow['total'];

		$engine = AttachTranslatedContent::run( $source, $target );

		$merged = array(
			'termsMerged'              => 0,
			'postsDetached'            => 0,
			'defaultCategoryForgotten' => false,
		);

		if ( self::ON_COLLISION_MERGE === $onCollision && $collisions['total'] > 0 ) {
			$merged = MergeService::run( $probe['collidingRows'], $source, $target );

			$engine['skipped'] = (int) TranslatedContentOfLanguages::counts( array( $source ) )['total'];
		}

		$repository->finalize( $recordId, self::recordPatch( $engine, $before ) );

		TranslationPause::resetCache();

		return array(
			'error'           => '',
			'source'          => $source,
			'target'          => $target,
			'confirmed'       => $confirmed,
			'tier'            => $tier,
			'record'          => (int) $recordId,
			'counts'          => self::counts( $engine, $before ),
			'skipped'         => isset( $engine['skipped'] ) ? (int) $engine['skipped'] : 0,
			'onCollision'     => $onCollision,
			'collisions'      => $collisions,
			'merged'          => $merged,
			'dryRun'          => false,
			'otherCollisions' => $otherCollisions,
		);
	}

	private static function recordPatch( array $engine, $before ) {
		$patch = array( 'counts_add' => array() );

		foreach ( self::counts( $engine, $before ) as $key => $count ) {
			$patch['counts_add'][ self::RECORD_LINES[ $key ] ] = array( 'moved' => $count );
		}

		$skipped = isset( $engine['skipped'] ) ? (int) $engine['skipped'] : 0;

		if ( $skipped > 0 ) {
			$patch['counts_add']['content']['skipped'] = $skipped;
			$patch['skipped_add']['content'][]         = array( 'reason' => self::SKIP_TRID_CONFLICT );
		}

		return $patch;
	}

	private static function counts( array $engine, $before ) {
		$counts = array();

		foreach ( self::COUNT_KEYS as $key ) {
			$counts[ $key ] = isset( $engine[ $key ] ) ? (int) $engine[ $key ] : 0;
		}

		$skipped           = isset( $engine['skipped'] ) ? (int) $engine['skipped'] : 0;
		$counts['content'] = max( 0, (int) $before - $skipped );

		return $counts;
	}

	private static function refusal( $source, $target, $confirmed, $tier, $error ) {
		return array(
			'error'           => $error,
			'source'          => $source,
			'target'          => $target,
			'confirmed'       => $confirmed,
			'tier'            => $tier,
			'record'          => null,
			'counts'          => array_fill_keys( self::COUNT_KEYS, 0 ),
			'skipped'         => 0,
			'onCollision'     => self::ON_COLLISION_SKIP,
			'collisions'      => array(
				'terms' => 0,
				'posts' => 0,
				'total' => 0,
			),
			'merged'          => array(
				'termsMerged'              => 0,
				'postsDetached'            => 0,
				'defaultCategoryForgotten' => false,
			),
			'dryRun'          => false,
			'otherCollisions' => 0,
		);
	}

	private static function dryRunAnswer( $source, $target, $confirmed, $tier, array $collisions, $otherCollisions = 0 ) {
		return array(
			'error'           => '',
			'source'          => $source,
			'target'          => $target,
			'confirmed'       => $confirmed,
			'tier'            => $tier,
			'record'          => null,
			'counts'          => array_fill_keys( self::COUNT_KEYS, 0 ),
			'skipped'         => 0,
			'onCollision'     => self::ON_COLLISION_SKIP,
			'collisions'      => $collisions,
			'merged'          => array(
				'termsMerged'              => 0,
				'postsDetached'            => 0,
				'defaultCategoryForgotten' => false,
			),
			'dryRun'          => true,
			'otherCollisions' => (int) $otherCollisions,
		);
	}

	private static function isRemoved( $code ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code = %s AND active <> 1",
				(string) $code
			)
		);

		return null !== $found && '' !== $found;
	}

	private static function hasContent( $code ) {
		return TranslatedContentOfLanguages::hasAny( array( (string) $code ) );
	}

	private static function activeCodes() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return array();
		}

		$codes = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		return array_values(
			array_filter(
				array_map( 'strval', $codes ),
				function ( $code ) {
					return '' !== $code;
				}
			)
		);
	}
}
