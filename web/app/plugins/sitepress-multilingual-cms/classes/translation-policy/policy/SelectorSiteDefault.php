<?php

namespace WPML\TranslationPolicy\Policy;

class SelectorSiteDefault {

	const REASON = 'editor-selector';

	private $sql;

	private $writer;

	public function __construct( PolicySql $sql, PolicyWriter $writer ) {
		$this->sql    = $sql;
		$this->writer = $writer;
	}

	public static function create(): self {
		global $wpdb;

		$sql = new PolicySql( $wpdb );

		return new self( $sql, new PolicyWriter( $wpdb, new PolicyTable( $wpdb ), $sql ) );
	}

	public function keepWordPressEditor(): bool {
		if ( $this->sql->siteHasChoiceOrLock() ) {
			return false;
		}

		$default = null;
		foreach ( $this->sql->rowsForSite() as $row ) {
			if ( PolicyTable::STRENGTH_DEFAULT === $row['strength'] && '' === (string) $row['source'] ) {
				$default = $row;
				break;
			}
		}

		if ( $default && PolicyTable::EDITOR_NATIVE === $default['editor'] ) {
			return false;
		}

		$method = $default && in_array( $default['method'], PolicyTable::METHODS, true ) ? $default['method'] : null;

		return $this->writer->set( PolicyTable::SCOPE_SITE, '', PolicyTable::EDITOR_NATIVE, $method, PolicyTable::STRENGTH_DEFAULT, '', self::REASON );
	}
}
