<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\Precedence;

class ScopeValues {

	const COLUMNS = [ PolicyTable::COLUMN_EDITOR, PolicyTable::COLUMN_METHOD ];

	const SAME_AS_SITE = '';

	const STATE_CHOICE = 'choice';

	const STATE_DEFAULT = 'default';

	const STATE_LOCK = 'lock';

	const STATE_SITE = 'site';

	private $sql;

	private $siteRows;

	public function __construct( PolicySql $sql ) {
		$this->sql = $sql;
	}

	public function site(): array {
		return $this->values( '' );
	}

	public function siteEditor(): array {
		return self::shownSiteEditor( $this->rows( '' ), PolicyTable::fallbackEditor() );
	}

	public static function shownSiteEditor( array $rows, string $fallbackEditor ): array {
		$value = self::column( $rows, PolicyTable::COLUMN_EDITOR, '' );
		if ( PolicyTable::EDITOR_WPML === $value['value'] ) {
			$value['value'] = Precedence::resolve( $rows, '', $fallbackEditor )->editor();
		}

		return $value;
	}

	public function type( string $postType ): array {
		return $this->values( $postType );
	}

	private function values( string $postType ): array {
		$rows   = $this->rows( $postType );
		$values = [];
		foreach ( self::COLUMNS as $column ) {
			$values[ $column ] = self::column( $rows, $column, $postType );
		}

		return $values;
	}

	private function rows( string $postType ): array {
		if ( '' !== $postType ) {
			return $this->sql->settingsRows( $postType );
		}
		if ( null === $this->siteRows ) {
			$this->siteRows = $this->sql->settingsRows( '' );
		}

		return $this->siteRows;
	}

	public static function column( array $rows, string $column, string $postType ): array {
		$row    = Precedence::decidingRow( $rows, $column, $postType );
		$isType = '' !== $postType;

		if ( ! $row ) {
			return $isType
				? self::value( self::SAME_AS_SITE, self::STATE_SITE )
				: self::value( self::fallback( $column ), self::STATE_DEFAULT );
		}

		$isFromSite = PolicyTable::SCOPE_SITE === $row['scope'];
		$isLock   = PolicyTable::STRENGTH_LOCK === $row['strength'];
		if ( $isType && $isFromSite && ! $isLock ) {
			return self::value( self::SAME_AS_SITE, self::STATE_SITE );
		}

		$state = $isLock ? self::STATE_LOCK : ( PolicyTable::STRENGTH_CHOICE === $row['strength'] ? self::STATE_CHOICE : self::STATE_DEFAULT );

		return self::value( (string) $row[ $column ], $state, (string) ( $row['source'] ?? '' ), (string) ( $row['reason'] ?? '' ) );
	}

	public static function fallback( string $column ): string {
		return PolicyTable::COLUMN_EDITOR === $column ? PolicyTable::fallbackEditor() : PolicyTable::FALLBACK_METHOD;
	}

	private static function value( string $value, string $state, string $source = '', string $reason = '' ): array {
		return [
			'value'  => $value,
			'state'  => $state,
			'source' => $source,
			'reason' => $reason,
		];
	}
}
