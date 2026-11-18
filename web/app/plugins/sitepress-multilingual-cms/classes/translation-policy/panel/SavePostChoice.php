<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\PolicyWriter;
use WPML\TranslationPolicy\Policy\Precedence;
use WPML\TranslationPolicy\Settings\LockedChoice;
use WPML\TranslationPolicy\Settings\ScopeValues;
use WPML\TranslationPolicy\Settings\Words as SettingsWords;

class SavePostChoice {

	const REASON = 'language-panel';

	const EDITORS = PolicyTable::WPML_EDITORS;

	private $sql;

	private $writer;

	private $choices;

	public function __construct( PolicySql $sql, PolicyWriter $writer, PostChoices $choices ) {
		$this->sql     = $sql;
		$this->writer  = $writer;
		$this->choices = $choices;
	}

	public function save( int $postId, string $postType, array $changes, array $reset, string $scope = PolicyTable::SCOPE_POST ) {
		$changes = $this->checkChanges( $changes );
		$reset   = $this->checkReset( $reset, $changes );
		if ( ! $changes && ! $reset ) {
			throw new \InvalidArgumentException( 'A panel change sets the editor or the method, or resets one.' );
		}
		$isForType = PolicyTable::SCOPE_TYPE === $scope;
		if ( ! $isForType && PolicyTable::SCOPE_POST !== $scope ) {
			throw new \InvalidArgumentException( 'A panel change is for this post or for all posts of its type.' );
		}
		if ( $isForType && $reset ) {
			throw new \InvalidArgumentException( 'Only a change of this post gives a value back to the post type.' );
		}

		if ( $isForType ) {
			$this->saveForType( $postType, $changes );

			return;
		}

		$current = $this->choices->forPost( $postId, $postType );
		foreach ( array_merge( array_keys( $changes ), $reset ) as $column ) {
			if ( PostChoices::STATE_LOCK === $current[ $column ]['state'] ) {
				throw new LockedChoice( esc_html( (string) $current[ $column ]['lock'] ) );
			}
		}

		$this->writeChoice( PolicyTable::SCOPE_POST, (string) $postId, $changes, $reset );
		if ( $reset ) {
			$this->resetDefaults( $postId, $current['rows'], $reset );
		}
	}

	private function saveForType( string $postType, array $changes ) {
		$rows = $this->sql->settingsRows( $postType );
		foreach ( array_keys( $changes ) as $column ) {
			$value = ScopeValues::column( $rows, $column, $postType );
			if ( ScopeValues::STATE_LOCK === $value['state'] ) {
				throw new LockedChoice( esc_html( SettingsWords::lockRefused( SettingsWords::lock( $value['source'], $value['reason'] ) ) ) );
			}
		}

		$isEditorChange = array_key_exists( PolicyTable::COLUMN_EDITOR, $changes );
		if ( $isEditorChange && ! in_array( Precedence::resolve( $rows, $postType, PolicyTable::fallbackEditor() )->editor(), self::EDITORS, true ) ) {
			throw new \InvalidArgumentException( 'All posts of a type that uses the WordPress editor keep their editor.' );
		}

		$this->writeChoice( PolicyTable::SCOPE_TYPE, $postType, $changes, [] );
		foreach ( array_keys( $changes ) as $column ) {
			$this->sql->clearPostColumnOfType( $postType, $column );
		}
	}

	private function checkChanges( array $changes ): array {
		$checked = [];
		foreach ( $changes as $column => $value ) {
			$allowed = PolicyTable::COLUMN_EDITOR === $column ? self::EDITORS : ( PolicyTable::COLUMN_METHOD === $column ? PolicyTable::METHODS : null );
			if ( null === $allowed || ! in_array( $value, $allowed, true ) ) {
				throw new \InvalidArgumentException( sprintf( 'Invalid panel change "%s".', esc_html( (string) $column ) ) );
			}
			$checked[ $column ] = $value;
		}

		return $checked;
	}

	private function checkReset( array $reset, array $changes ): array {
		$checked = [];
		foreach ( $reset as $column ) {
			if ( ! in_array( $column, [ PolicyTable::COLUMN_EDITOR, PolicyTable::COLUMN_METHOD ], true ) || array_key_exists( $column, $changes ) ) {
				throw new \InvalidArgumentException( 'A panel reset names the editor or the method, not a column it also sets.' );
			}
			$checked[] = $column;
		}

		return array_values( array_unique( $checked ) );
	}

	private function writeChoice( string $scope, string $scopeId, array $changes, array $reset ) {
		$choice = $this->sql->choiceRow( $scope, $scopeId );

		$values = [];
		foreach ( [ PolicyTable::COLUMN_EDITOR, PolicyTable::COLUMN_METHOD ] as $column ) {
			$value             = $changes[ $column ] ?? ( in_array( $column, $reset, true ) ? null : ( $choice[ $column ] ?? null ) );
			$values[ $column ] = ( null === $value || '' === $value ) ? null : (string) $value;
		}

		if ( null === $values[ PolicyTable::COLUMN_EDITOR ] && null === $values[ PolicyTable::COLUMN_METHOD ] ) {
			if ( $choice ) {
				$this->assertWritten( $this->writer->remove( $scope, $scopeId, PolicyTable::STRENGTH_CHOICE, (string) ( $choice['source'] ?? '' ) ) );
			}

			return;
		}

		$this->assertWritten( $this->writer->set( $scope, $scopeId, $values[ PolicyTable::COLUMN_EDITOR ], $values[ PolicyTable::COLUMN_METHOD ], PolicyTable::STRENGTH_CHOICE, '', self::REASON ) );
	}

	private function resetDefaults( int $postId, array $rows, array $reset ) {
		foreach ( $rows as $row ) {
			$isWpmlPostDefault = PolicyTable::SCOPE_POST === ( $row['scope'] ?? '' )
				&& PolicyTable::STRENGTH_DEFAULT === ( $row['strength'] ?? '' )
				&& '' === (string) ( $row['source'] ?? '' );
			if ( ! $isWpmlPostDefault ) {
				continue;
			}

			$clearsEditor = in_array( PolicyTable::COLUMN_EDITOR, $reset, true ) && null !== ( $row['editor'] ?? null );
			$clearsMethod = in_array( PolicyTable::COLUMN_METHOD, $reset, true ) && null !== ( $row['method'] ?? null );
			if ( ! $clearsEditor && ! $clearsMethod ) {
				continue;
			}

			$editor = $clearsEditor ? null : ( $row['editor'] ?? null );
			$method = $clearsMethod ? null : ( $row['method'] ?? null );

			$this->assertWritten(
				null === $editor && null === $method
					? $this->writer->remove( PolicyTable::SCOPE_POST, (string) $postId, PolicyTable::STRENGTH_DEFAULT, '' )
					: $this->writer->set( PolicyTable::SCOPE_POST, (string) $postId, $editor, $method, PolicyTable::STRENGTH_DEFAULT, '', (string) ( $row['reason'] ?? '' ) )
			);
		}
	}

	private function assertWritten( bool $isWritten ) {
		if ( ! $isWritten ) {
			throw new \RuntimeException( 'The policy writer could not write the panel choice.' );
		}
	}
}
