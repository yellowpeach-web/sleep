<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\PolicyWriter;
use WPML\TranslationPolicy\Policy\Precedence;

class SaveSettings {

	const REASON = 'tm-settings';

	const REASON_SETUP = 'setup-wizard';

	private $sql;

	private $writer;

	private $postTypes;

	public function __construct( PolicySql $sql, PolicyWriter $writer, array $postTypes ) {
		$this->sql       = $sql;
		$this->writer    = $writer;
		$this->postTypes = $postTypes;
	}

	public function save( array $items, string $reason = self::REASON ) {
		$checked = [];
		foreach ( $items as $item ) {
			$checked[] = $this->check( $item );
		}

		foreach ( $checked as $item ) {
			$this->write( $item, $reason );
		}
	}

	private function check( $item ): array {
		if ( ! is_array( $item ) ) {
			throw new \InvalidArgumentException( 'A settings item is an array.' );
		}

		$scope         = $item['scope'] ?? '';
		$isSite        = PolicyTable::SCOPE_SITE === $scope;
		$id            = $isSite ? '' : (string) ( $item['id'] ?? '' );
		$isOfferedType = PolicyTable::SCOPE_TYPE === $scope && in_array( $id, $this->postTypes, true );
		if ( ! $isSite && ! $isOfferedType ) {
			throw new \InvalidArgumentException( 'The settings save only the site and the post types the screen offers.' );
		}

		$changes = [];
		foreach ( ScopeValues::COLUMNS as $column ) {
			if ( array_key_exists( $column, $item ) ) {
				$changes[ $column ] = $this->checkValue( $column, $item[ $column ], $id );
			}
		}
		if ( ! $changes ) {
			throw new \InvalidArgumentException( 'A settings item changes the editor, the method, or both.' );
		}

		$rows = $this->sql->settingsRows( $id );
		foreach ( array_keys( $changes ) as $column ) {
			$value = ScopeValues::column( $rows, $column, $id );
			if ( ScopeValues::STATE_LOCK === $value['state'] ) {
				throw new LockedChoice( esc_html( Words::lockRefused( Words::lock( $value['source'], $value['reason'] ) ) ) );
			}
		}

		return [
			'scope'   => $scope,
			'id'      => $id,
			'changes' => $changes,
			'rows'    => $rows,
		];
	}

	private function checkValue( string $column, $value, string $postType ): string {
		$value = is_string( $value ) ? $value : '';

		if ( '' !== $postType && ScopeValues::SAME_AS_SITE === $value ) {
			return $value;
		}

		if ( ! Precedence::offersValue( $column, $value, $postType ) || PolicyTable::EDITOR_WPML === $value ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid %s "%s".', esc_html( $column ), esc_html( $value ) ) );
		}

		return $value;
	}

	private function write( array $item, string $reason ) {
		$choice = null;
		foreach ( $item['rows'] as $row ) {
			if ( $item['scope'] === $row['scope'] && PolicyTable::STRENGTH_CHOICE === $row['strength'] ) {
				$choice = $row;
				break;
			}
		}

		$values = [];
		foreach ( ScopeValues::COLUMNS as $column ) {
			$value             = array_key_exists( $column, $item['changes'] ) ? $item['changes'][ $column ] : ( $choice[ $column ] ?? null );
			$values[ $column ] = ( null === $value || '' === $value ) ? null : (string) $value;
		}

		if ( null === $values[ PolicyTable::COLUMN_EDITOR ] && null === $values[ PolicyTable::COLUMN_METHOD ] ) {
			if ( $choice ) {
				$this->assertWritten( $this->writer->remove( $item['scope'], $item['id'], PolicyTable::STRENGTH_CHOICE, (string) ( $choice['source'] ?? '' ) ) );
			}

			return;
		}

		$this->assertWritten( $this->writer->set( $item['scope'], $item['id'], $values[ PolicyTable::COLUMN_EDITOR ], $values[ PolicyTable::COLUMN_METHOD ], PolicyTable::STRENGTH_CHOICE, '', $reason ) );
	}

	private function assertWritten( bool $isWritten ) {
		if ( ! $isWritten ) {
			throw new \RuntimeException( 'The policy writer could not write the settings.' );
		}
	}
}
