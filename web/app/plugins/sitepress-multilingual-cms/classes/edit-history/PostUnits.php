<?php

namespace WPML\EditHistory;

class PostUnits {

	private $blockUnits;

	private $package;

	public function __construct( callable $blockUnits, \WPML_Element_Translation_Package $package ) {
		$this->blockUnits = $blockUnits;
		$this->package    = $package;
	}

	public function read( \WP_Post $post, \WP_Post $original ): array {
		$units = [
			[ 'k' => 'title', 'x' => (string) $post->post_title ],
			[ 'k' => 'URL', 'x' => (string) $post->post_name ],
		];

		$units   = array_merge( $units, ( $this->blockUnits )( (string) $post->post_content ) );
		$units[] = [ 'k' => 'excerpt', 'x' => (string) $post->post_excerpt ];

		$fields = $this->package->get_custom_field_texts( $post, $this->package->get_custom_fields_to_translate( $original ) );
		foreach ( $fields as $field => $text ) {
			$units[] = [ 'k' => (string) $field, 'x' => (string) $text ];
		}

		return $units;
	}

	public function pair( int $translationId, int $originalId ) {
		$translation = get_post( $translationId );
		$original    = get_post( $originalId );
		if ( ! $translation instanceof \WP_Post || ! $original instanceof \WP_Post ) {
			return null;
		}

		$modified = strtotime( $original->post_modified_gmt . ' UTC' );

		return [
			'units'    => $this->read( $translation, $original ),
			'original' => $this->read( $original, $original ),
			'modified' => $modified > 0 ? $modified : null,
		];
	}

	public static function forBlockEditor(): self {
		return new self(
			[ new \WPML\PB\Gutenberg\BlockStrings( \WPML_Gutenberg_Integration_Factory::createStringsInBlock( new \WPML_Gutenberg_Config_Option() ) ), 'units' ],
			new \WPML_Element_Translation_Package()
		);
	}
}
