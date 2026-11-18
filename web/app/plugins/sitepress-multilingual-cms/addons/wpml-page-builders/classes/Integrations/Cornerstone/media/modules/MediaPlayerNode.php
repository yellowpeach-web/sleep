<?php

namespace WPML\PB\Cornerstone\Media\Modules;

class MediaPlayerNode extends \WPML_Cornerstone_Media_Node_With_URLs {

	const POSTER_KEY       = 'mejs_poster';
	const SOURCE_FILES_KEY = 'mejs_source_files';

	protected function get_keys() {
		return [ self::POSTER_KEY ];
	}

	public function translate( $node_data, $target_lang, $source_lang ) {
		$node_data = parent::translate( $node_data, $target_lang, $source_lang );

		if ( ! empty( $node_data[ self::SOURCE_FILES_KEY ] ) && is_string( $node_data[ self::SOURCE_FILES_KEY ] ) ) {
			$node_data[ self::SOURCE_FILES_KEY ] = $this->translateSourceFiles( $node_data[ self::SOURCE_FILES_KEY ], $target_lang, $source_lang );
		}

		return $node_data;
	}

	private function translateSourceFiles( string $sourceFiles, string $targetLanguage, string $sourceLanguage ): string {
		$translatedFiles = [];

		foreach ( explode( "\n", $sourceFiles ) as $sourceFile ) {
			$translatedFiles[] = $this->translateSourceFile( $sourceFile, $targetLanguage, $sourceLanguage );
		}

		return implode( "\n", $translatedFiles );
	}

	private function translateSourceFile( string $sourceFile, string $targetLanguage, string $sourceLanguage ): string {
		$file = trim( $sourceFile );

		if ( '' === $file ) {
			return $sourceFile;
		}

		return $this->translateValue( $file, $targetLanguage, $sourceLanguage );
	}
}
