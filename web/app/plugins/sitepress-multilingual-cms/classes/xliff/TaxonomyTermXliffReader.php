<?php

namespace WPML\TM\XLIFF;

class TaxonomyTermXliffReader {

	public static function isTermXliff( $xliff ) {
		return is_string( $xliff )
			&& (bool) preg_match( '/<file\b[^>]*\boriginal="term-\d+"/', $xliff );
	}

	public static function sentUnitIds( $xliff ) {
		$file = self::loadFile( $xliff );

		return $file ? self::unitsWithSource( $file ) : [];
	}

	public function read( $xliff ) {
		if ( ! is_string( $xliff ) || '' === trim( $xliff ) ) {
			return $this->error( 'empty XLIFF content' );
		}

		$previous = libxml_use_internal_errors( true );
		$sx       = simplexml_load_string( $xliff );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( false === $sx || ! isset( $sx->file ) ) {
			return $this->error( 'not a valid XLIFF document' );
		}

		$file     = $sx->file;
		$original = (string) $file->attributes()->original;
		if ( ! preg_match( '/^term-(\d+)$/', $original, $m ) ) {
			return $this->error( 'unexpected file/@original, not a term job: ' . $original );
		}
		$termId = (int) $m[1];

		$fields = [];
		if ( isset( $file->body ) ) {
			foreach ( $file->body->children() as $group ) {
				foreach ( $group->children() as $unit ) {
					$id = (string) $unit->attributes()->id;
					if ( '' === $id ) {
						continue;
					}
					$fields[ $id ] = $this->extractTarget( $unit );
				}
			}
		}

		if ( ! isset( $fields['term-name'] ) || '' === $fields['term-name'] ) {
			return $this->error( 'missing or empty term-name target' );
		}

		return [
			'termId'     => $termId,
			'sourceLang' => (string) $file->attributes()->{'source-language'},
			'targetLang' => (string) $file->attributes()->{'target-language'},
			'fields'     => $fields,
			'sentUnits'  => self::unitsWithSource( $file ),
		];
	}

	private static function loadFile( $xliff ) {
		if ( ! is_string( $xliff ) || '' === trim( $xliff ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );
		$sx       = simplexml_load_string( $xliff );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return false !== $sx && isset( $sx->file ) ? $sx->file : null;
	}

	private static function unitsWithSource( \SimpleXMLElement $file ) {
		$ids = [];
		if ( isset( $file->body ) ) {
			foreach ( $file->body->children() as $group ) {
				foreach ( $group->children() as $unit ) {
					$id     = (string) $unit->attributes()->id;
					$source = isset( $unit->source->mrk ) ? (string) $unit->source->mrk : (string) $unit->source;
					if ( '' !== $id && '' !== trim( $source ) ) {
						$ids[] = $id;
					}
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private function extractTarget( \SimpleXMLElement $unit ) {
		if ( isset( $unit->target->mrk ) ) {
			return (string) $unit->target->mrk;
		}
		if ( isset( $unit->target ) ) {
			return (string) $unit->target;
		}

		return '';
	}

	private function error( $reason ) {
		return new \WP_Error(
			'taxonomy_term_xliff_invalid',
			'The taxonomy term XLIFF could not be parsed: ' . $reason
		);
	}
}
