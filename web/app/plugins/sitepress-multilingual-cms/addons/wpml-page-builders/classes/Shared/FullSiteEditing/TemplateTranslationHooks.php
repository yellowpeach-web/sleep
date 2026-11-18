<?php

namespace WPML\PB\FullSiteEditing;

use WPML\BlockEditor\SiteEditor\TemplateSaveLanguage;
use WPML\Element\API\Post;
use WPML\Element\API\PostTranslations;
use WPML\FP\Obj;
use WPML\PB\Gutenberg\Integration;

class TemplateTranslationHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, Integration {


	const PRIORITY_AFTER_WP_TEMPLATE_SLUG = 11;

	public function add_hooks() {
		add_filter( 'wpml_tm_translation_job_data', [ $this, 'doNotTranslateTitle' ], 20, 2 );
		add_filter( 'wpml_pre_save_pro_translation', [ $this, 'copyOriginalTitleToTranslation' ], 20, 2 );
		add_action( 'rest_after_insert_wp_template', [ $this, 'syncPostName' ], 20, 1 );
		add_action( 'rest_after_insert_wp_template_part', [ $this, 'syncPostName' ], 20, 1 );
		add_filter( 'pre_wp_unique_post_slug', [ $this, 'keepSlugUniqueInLanguage' ], self::PRIORITY_AFTER_WP_TEMPLATE_SLUG, 5 );
		add_action( 'wpml_pro_translation_completed', [ $this, 'syncPostNameAfterDelivery' ] );
	}

	public function keepSlugUniqueInLanguage( $override_slug, $slug, $post_id, $post_status, $post_type ) {
		if ( ! TemplateLocalizer::isTemplate( $post_type ) || '' === (string) $slug ) {
			return $override_slug;
		}

		$language = $this->languageOf( (int) $post_id );
		if ( ! $language ) {
			return $override_slug;
		}

		$theme     = $this->themeOf( (int) $post_id );
		$candidate = (string) $slug;
		$suffix    = 2;

		while ( $this->slugTakenInLanguage( $candidate, (string) $post_type, (int) $post_id, $language, $theme ) ) {
			$max_length = 200 - ( strlen( (string) $suffix ) + 1 );
			$candidate = ( function_exists( 'wp_truncate_slug' ) ? wp_truncate_slug( (string) $slug, $max_length ) : _truncate_post_slug( (string) $slug, $max_length ) ) . '-' . $suffix;
			$suffix++;
		}

		return $candidate;
	}

	public function syncPostNameAfterDelivery( $new_post_id ) {
		if ( ! TemplateLocalizer::isTemplate( (string) get_post_type( (int) $new_post_id ) ) ) {
			return;
		}

		$original_id = (int) PostTranslations::getOriginalId( (int) $new_post_id );
		if ( $original_id > 0 ) {
			$this->syncPostName( get_post( $original_id ) );
		}
	}

	private function languageOf( int $post_id ): ?string {
		$language = $post_id > 0 ? Post::getLang( $post_id ) : null;
		$language = $language ?: TemplateSaveLanguage::languageForRowBeingCreated();
		$language = $language ?: apply_filters( 'wpml_current_language', null );

		return is_string( $language ) && '' !== $language ? $language : null;
	}

	private function themeOf( int $post_id ): string {
		$terms = $post_id > 0 ? get_the_terms( $post_id, 'wp_theme' ) : false;
		if ( is_array( $terms ) && $terms && isset( $terms[0]->name ) ) {
			return (string) $terms[0]->name;
		}

		return (string) get_stylesheet();
	}

	private function slugTakenInLanguage( string $slug, string $post_type, int $post_id, string $language, string $theme ): bool {
		global $wpdb;

		$sql = "SELECT p.ID
				FROM {$wpdb->posts} p
				JOIN {$wpdb->prefix}icl_translations t
					ON t.element_id = p.ID AND t.element_type = %s
				JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'wp_theme'
				JOIN {$wpdb->terms} tm ON tm.term_id = tt.term_id
				WHERE p.post_name = %s
					AND p.post_type = %s
					AND p.ID <> %d
					AND t.language_code = %s
					AND tm.name = %s
				LIMIT 1";

		return (bool) $wpdb->get_var( $wpdb->prepare( $sql, 'post_' . $post_type, $slug, $post_type, $post_id, $language, $theme ) );
	}

	public function doNotTranslateTitle( array $package, $post ) {
		return TemplateLocalizer::isTemplate( Obj::prop( 'post_type', $post ) )
			? Obj::assocPath( [ 'contents', 'title', 'translate' ], 0, $package )
			: $package;
	}

	public function copyOriginalTitleToTranslation( $postData, $job ) {
		if ( ! TemplateLocalizer::isJobType( Obj::prop( 'original_post_type', $job ) ) ) {
			return $postData;
		}

		$post = \get_post( $job->original_doc_id );
		if ( ! $post ) {
			return $postData;
		}

		$postData['post_title'] = $post->post_title;
		$postData['post_name']  = $post->post_name;
		$targetLang             = property_exists( $job, 'language_code' ) ? $job->language_code : null;
		if ( $targetLang ) {
			$postData['post_name'] = TemplateLocalizer::getLocalizedTemplateSlug( $post->post_name, $targetLang );
		}

		return $postData;
	}

	public function syncPostName( $post ) {
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}

		$translations = wpml_collect( PostTranslations::get( $post->ID ) );
		$saved        = $translations->first(
			function ( $translation ) use ( $post ) {
				return (int) Obj::prop( 'element_id', $translation ) === (int) $post->ID;
			}
		);

		if ( ! $saved || ! Obj::prop( 'original', $saved ) ) {
			return;
		}

		global $wpdb;
		$translations
			->reject( Obj::prop( 'original' ) )
			->each(
				function ( $translation ) use (
					$post,
					$wpdb
				) {
					$id = (int) Obj::prop( 'element_id', $translation );
					if ( ! $id ) {
						return;
					}

					$expected = TemplateLocalizer::getLocalizedTemplateSlug( $post->post_name, $translation->language_code );
					if ( Obj::prop( 'post_name', get_post( $id ) ) === $expected ) {
						return;
					}

					if ( $this->slugTakenInLanguage( $expected, (string) get_post_type( $id ), $id, (string) $translation->language_code, $this->themeOf( $id ) ) ) {
						return;
					}

					$wpdb->update(
						$wpdb->prefix . 'posts',
						[ 'post_name' => $expected ],
						[ 'ID' => $id ]
					);
					clean_post_cache( $id );
				}
			);
	}
}
