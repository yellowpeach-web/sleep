<?php

namespace WPML\TranslationPolicy\Policy;

interface EditorSqlInterface {

	public function usesNativeEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string;

	public function usesWpmlEditor( string $todaysCondition, string $postIdColumn, string $postTypeColumn ): string;

	public function nativeEditorFlag( string $todaysExpression, string $postIdColumn, string $postTypeColumn ): string;
}
