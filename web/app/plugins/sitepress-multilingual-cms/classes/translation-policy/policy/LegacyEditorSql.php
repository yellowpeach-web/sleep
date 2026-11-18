<?php

namespace WPML\TranslationPolicy\Policy;

class LegacyEditorSql implements EditorSqlInterface {

	public function usesNativeEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string {
		return $todaysCondition;
	}

	public function usesWpmlEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string {
		return $todaysCondition;
	}

	public function nativeEditorFlag( string $todaysExpression, string $postIdColumn, string $postTypeColumn ): string {
		return $todaysExpression;
	}
}
