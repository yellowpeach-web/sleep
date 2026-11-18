<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\TranslationPolicy\Policy\Policy;
use WPML\TranslationPolicy\Policy\PolicySql;

class PolicyMigration {

	const POSTS_PER_BATCH = 1000;

	private $stores;

	private $sql;

	public function __construct( LegacyEditorStores $stores, PolicySql $sql ) {
		$this->stores = $stores;
		$this->sql    = $sql;
	}

	public static function create( ?\wpdb $wpdb = null, ?\SitePress $sitepress = null ): self {
		$wpdb      = $wpdb ?: $GLOBALS['wpdb'];
		$sitepress = $sitepress ?: $GLOBALS['sitepress'];

		return new self( new LegacyEditorStores( $wpdb, $sitepress ), new PolicySql( $wpdb ) );
	}

	public function addMissing(): int {
		$now        = current_time( 'mysql', true );
		$derivation = $this->derivation();
		$tm         = $this->stores->tmSettings();

		$inserted = $this->sql->insertMissing( $this->siteAndTypeRows( $derivation, $tm ), $now );

		$afterId = 0;
		do {
			list( $lastId, $posts ) = $this->stores->postKeys( $afterId, self::POSTS_PER_BATCH );
			if ( $posts ) {
				$inserted += $this->sql->insertMissing( $derivation->postRows( $tm, $posts ), $now );
			}
			$afterId = $lastId;
		} while ( $lastId );

		Policy::resetRequestCache();

		return $inserted;
	}

	public function sync(): array {
		$counts = $this->reconcile( true );
		Policy::resetRequestCache();

		return $counts;
	}

	public function syncSettings(): array {
		$counts = $this->reconcile( true, false );
		Policy::resetRequestCache();

		return $counts;
	}

	public function followSiteEditor( string $editor ): int {
		$moved = $this->sql->moveSiteEditor( $editor, current_time( 'mysql', true ) );
		Policy::resetRequestCache();

		return $moved;
	}

	public function plan(): array {
		return $this->reconcile( false );
	}

	private function reconcile( bool $shouldWrite, bool $shouldIncludePosts = true ): array {
		$now        = current_time( 'mysql', true );
		$derivation = $this->derivation();
		$tm         = $this->stores->tmSettings();
		$counts     = [
			'added'   => 0,
			'updated' => 0,
			'removed' => 0,
		];

		$rows        = $this->siteAndTypeRows( $derivation, $tm );
		$pluginRows  = array_filter( $rows, fn( $row ) => '' !== $row['source'] );
		$stampedRows = array_diff_key( $rows, $pluginRows );

		$this->apply( $stampedRows, $this->sql->stampedRows(), $shouldWrite, $now, $counts );

		foreach ( $pluginRows as $row ) {
			if ( ! $this->sql->rowId( $row['scope'], $row['scope_id'], $row['strength'], $row['source'] ) ) {
				$counts['added'] += $shouldWrite ? $this->sql->insertMissing( [ $row ], $now ) : 1;
			}
		}

		if ( ! $shouldIncludePosts ) {
			return $counts;
		}

		$afterId = 0;
		do {
			list( $lastId, $posts ) = $this->stores->postKeys( $afterId, self::POSTS_PER_BATCH );
			$upTo = $lastId ?: PHP_INT_MAX;
			$this->apply( $posts ? $derivation->postRows( $tm, $posts ) : [], $this->sql->stampedPostRows( $afterId, $upTo ), $shouldWrite, $now, $counts );
			$afterId = $lastId;
		} while ( $lastId );

		return $counts;
	}

	private function apply( array $derived, array $stamped, bool $shouldWrite, string $now, array &$counts ) {
		$remove = [];
		foreach ( $stamped as $row ) {
			$key = PolicyDerivation::key( $row );
			if ( ! isset( $derived[ $key ] ) ) {
				$remove[] = (int) $row['id'];
				continue;
			}

			$new = $derived[ $key ];
			unset( $derived[ $key ] );
			if ( self::value( $row['editor'] ) === $new['editor'] && self::value( $row['method'] ) === $new['method'] ) {
				continue;
			}

			$counts['updated']++;
			if ( $shouldWrite ) {
				$this->sql->updateStamped( (int) $row['id'], $new['editor'], $new['method'], $now );
			}
		}

		$counts['removed'] += $shouldWrite ? $this->sql->deleteStamped( $remove ) : count( $remove );
		$counts['added']   += $shouldWrite ? $this->sql->insertMissing( $derived, $now ) : count( $derived );
	}

	private function siteAndTypeRows( PolicyDerivation $derivation, array $tm ): array {
		return $derivation->siteAndTypeRows(
			$tm,
			$this->stores->sinceDates(),
			$this->stores->isTranslateEverythingOn(),
			$this->stores->translatableTypes()
		);
	}

	private function derivation(): PolicyDerivation {
		return new PolicyDerivation( $this->stores->wpmlEditor() );
	}

	private static function value( $value ) {
		return null === $value || '' === $value ? null : (string) $value;
	}
}
