<?php

use WPML\ST\SlugTranslation\PostArchive\Records;

class WPML_ST_Slug_Translation_Post_Custom_Types_Repository implements WPML_ST_Slug_Translation_Custom_Types_Repository {
	private $sitepress;

	private $custom_type_factory;

	private $archive_type_factory;

	private $post_slug_translation_settings;

	public function __construct(
		SitePress $sitepress,
		WPML_ST_Slug_Custom_Type_Factory $custom_type_factory,
		?WPML_ST_Slug_Custom_Type_Factory $archive_type_factory = null
	) {
		$this->sitepress            = $sitepress;
		$this->custom_type_factory  = $custom_type_factory;
		$this->archive_type_factory = $archive_type_factory;
	}


	public function get() {
		$types = array_values( array_filter(
			get_post_types( array( 'publicly_queryable' => true ) ),
			array( $this, 'filter' )
		) );

		$custom_types = array_map( array( $this, 'build_object' ), $types );

		if ( $this->archive_type_factory ) {
			foreach ( $types as $type ) {
				$archive_type = $this->build_archive_object( $type );
				if ( $archive_type ) {
					$custom_types[] = $archive_type;
				}
			}
		}

		return $custom_types;
	}

	private function build_archive_object( string $type ) {
		$registered_slug = Records::get_registered_archive_slug( get_post_type_object( $type ) );
		if ( '' === $registered_slug ) {
			return null;
		}

		return $this->archive_type_factory->create( $type, $this->is_display_as_translated( $type ), $registered_slug );
	}

	private function filter( $type ) {
		$post_slug_translation_settings = $this->get_post_slug_translation_settings();

		return isset( $post_slug_translation_settings['types'][ $type ] )
		       && $post_slug_translation_settings['types'][ $type ]
		       && $this->sitepress->is_translated_post_type( $type );
	}

	private function build_object( $type ) {
		return $this->custom_type_factory->create( $type, $this->is_display_as_translated( $type ) );
	}

	private function get_post_slug_translation_settings() {
		if ( null === $this->post_slug_translation_settings ) {
			$this->post_slug_translation_settings = $this->sitepress->get_setting( 'posts_slug_translation', array() );
		}

		return $this->post_slug_translation_settings;
	}


	private function is_display_as_translated( $type ) {
		return $this->sitepress->is_display_as_translated_post_type( $type );
	}
}