<?php

namespace WPML\TranslationPolicy\Policy;

class StoredEditorSql implements EditorSqlInterface {

	private $sql;

	public function __construct( PolicySql $sql ) {
		$this->sql = $sql;
	}

	public function usesNativeEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string {
		return $this->editor( $postIdColumn, $postTypeColumn ) . " = '" . PolicyTable::EDITOR_NATIVE . "'";
	}

	public function usesWpmlEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string {
		return $this->editor( $postIdColumn, $postTypeColumn ) . " <> '" . PolicyTable::EDITOR_NATIVE . "'";
	}

	public function nativeEditorFlag( string $todaysExpression, string $postIdColumn, string $postTypeColumn ): string {
		return 'CASE WHEN ' . $this->usesNativeEditor( $todaysExpression, $postIdColumn, $postTypeColumn ) . " THEN 'yes' ELSE 'no' END";
	}

	private function editor( string $postIdColumn, string $postTypeColumn ): string {
		return $this->sql->editorOf( $postIdColumn, $postTypeColumn, PolicyTable::fallbackEditor() );
	}
}
