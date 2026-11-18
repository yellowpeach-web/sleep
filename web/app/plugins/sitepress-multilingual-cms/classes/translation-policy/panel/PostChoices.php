<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TranslationPolicy\Policy\Constraints;
use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\TranslationPolicy\Policy\Precedence;

class PostChoices {

	const STATE_OWN       = 'own';
	const STATE_INHERITED = 'inherited';
	const STATE_LOCK      = 'lock';
	const STATE_PLUGIN    = 'plugin';

	private $sql;

	public function __construct( PolicySql $sql ) {
		$this->sql = $sql;
	}

	public function forPost( int $postId, string $postType ): array {
		$rows   = $this->sql->panelRows( $postId, $postType );
		$answer = Precedence::resolve( $rows, $postType, PolicyTable::fallbackEditor() );

		return [
			'rows'        => $rows,
			'editor'      => self::column( $rows, PolicyTable::COLUMN_EDITOR, $answer->editor(), $postId, $postType ),
			'method'      => self::column( $rows, PolicyTable::COLUMN_METHOD, (string) $answer->method(), $postId, $postType ),
			'wpml_editor' => self::wpmlEditor( $rows, $answer->editor(), $postType ),
			'type_editor' => self::typeEditor( $rows, $postType ),
		];
	}

	private static function typeEditor( array $rows, string $postType ): string {
		return self::editorWithout(
			$rows,
			function ( $row ) {
				return PolicyTable::SCOPE_POST === ( $row['scope'] ?? '' );
			},
			$postType
		);
	}

	private static function editorWithout( array $rows, callable $isLeftOut, string $postType ): string {
		$kept = array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $isLeftOut ) {
					return ! $isLeftOut( $row );
				}
			)
		);

		return Precedence::resolve( $kept, $postType, PolicyTable::fallbackEditor() )->editor();
	}

	public static function column( array $rows, string $column, string $value, int $postId, string $postType ): array {
		$row = Precedence::decidingRow( $rows, $column, $postType );

		if ( $row && PolicyTable::STRENGTH_LOCK === $row['strength'] ) {
			$label = Constraints::label( (string) ( $row['source'] ?? '' ), (string) ( $row['reason'] ?? '' ), $postId );

			return [
				'value' => $value,
				'state' => self::STATE_LOCK,
				'lock'  => '' !== $label ? $label : Words::lockedByPlugin(),
			];
		}

		$state = self::STATE_INHERITED;
		if ( $row && PolicyTable::SCOPE_POST === $row['scope'] ) {
			$isPlugins = PolicyTable::STRENGTH_DEFAULT === $row['strength'] && '' !== (string) ( $row['source'] ?? '' );
			$state     = $isPlugins ? self::STATE_PLUGIN : self::STATE_OWN;
		}

		return [
			'value' => $value,
			'state' => $state,
			'lock'  => null,
		];
	}

	private static function wpmlEditor( array $rows, string $editor, string $postType ): string {
		if ( in_array( $editor, PolicyTable::WPML_EDITORS, true ) ) {
			return $editor;
		}

		return self::editorWithout(
			$rows,
			function ( $row ) {
				return PolicyTable::EDITOR_NATIVE === ( $row['editor'] ?? null );
			},
			$postType
		);
	}
}
