<?php

namespace WPML\TM\XLIFF;

use WPML\TM\ATE\TranslateEverything\TermBundle;

class TermBundleXliffReader {

	public static function isTermBatchXliff( $xliff ) {
		return is_string( $xliff )
			&& (bool) preg_match( '/<file\b[^>]*\boriginal="' . preg_quote( TermBundle::ELEMENT_TYPE, '/' ) . '-\d+"/', $xliff );
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
		if ( ! preg_match( '/^' . preg_quote( TermBundle::ELEMENT_TYPE, '/' ) . '-(\d+)$/', $original, $m ) ) {
			return $this->error( 'unexpected file/@original, not a term bundle: ' . $original );
		}

		$terms = [];
		if ( isset( $file->body ) ) {
			foreach ( $file->body->children() as $group ) {
				if ( ! preg_match( '/^term-(\d+)$/', (string) $group->attributes()->id, $g ) ) {
					continue;
				}
				$ttid      = (int) $g[1];
				$fields    = [];
				$sentUnits = [];
				foreach ( $group->children() as $unit ) {
					$id = (string) $unit->attributes()->id;
					if ( '' === $id ) {
						continue;
					}
					$singleId            = TermBundle::singleTermUnitId( $id, $ttid );
					$fields[ $singleId ] = TaxonomyTermXliffReader::targetOf( $unit );
					$source              = isset( $unit->source->mrk ) ? (string) $unit->source->mrk : (string) $unit->source;
					if ( '' !== trim( $source ) ) {
						$sentUnits[] = $singleId;
					}
				}
				$terms[ $ttid ] = [
					'fields'    => $fields,
					'sentUnits' => array_values( array_unique( $sentUnits ) ),
				];
			}
		}

		return [
			'bundleJobId' => (int) $m[1],
			'sourceLang'  => (string) $file->attributes()->{'source-language'},
			'targetLang'  => (string) $file->attributes()->{'target-language'},
			'terms'       => $terms,
		];
	}

	private function error( $reason ) {
		return new \WP_Error(
			'term_bundle_xliff_invalid',
			'The term bundle XLIFF could not be parsed: ' . $reason
		);
	}
}
