<?php

namespace WPML\Translation;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;

final class ElementVisibility {

	public static function currentUserCanRead( $post, ?ExecutionContext $context = null ) {
		$context = $context ? $context : ExecutionContextHolder::current();
		if ( $context->isTrusted() ) {
			return true;
		}

		$post = self::resolvePost( $post );
		if ( ! $post ) {
			return false;
		}

		if ( self::isPubliclyViewable( $post ) ) {
			return true;
		}

		return (bool) current_user_can( 'read_post', $post->ID );
	}

	public static function currentUserCanReadGroupSource( $trid, $sourceLanguage, $elementType, ?ExecutionContext $context = null ) {
		$context = $context ? $context : ExecutionContextHolder::current();
		if ( $context->isTrusted() ) {
			return true;
		}

		$postId = self::groupSourceId( (int) $trid, $sourceLanguage, (string) $elementType );

		return $postId ? self::currentUserCanRead( $postId, $context ) : false;
	}

	public static function filterReadablePosts( array $translations, ?ExecutionContext $context = null ) {
		$context = $context ? $context : ExecutionContextHolder::current();
		if ( $context->isTrusted() ) {
			return $translations;
		}

		return array_filter(
			$translations,
			function ( $row ) use ( $context ) {
				if ( ! is_object( $row ) ) {
					return true;
				}
				$elementId = isset( $row->element_id ) ? (int) $row->element_id : 0;
				if ( ! $elementId || ! isset( $row->post_status ) ) {
					return true;
				}

				return self::currentUserCanRead( $elementId, $context );
			}
		);
	}

	private static function resolvePost( $post ) {
		if ( $post instanceof \WP_Post ) {
			return $post;
		}

		$postId = (int) $post;
		if ( $postId <= 0 ) {
			return null;
		}

		$resolved = get_post( $postId );

		return $resolved instanceof \WP_Post ? $resolved : null;
	}

	private static function isPubliclyViewable( \WP_Post $post ) {
		if ( function_exists( 'is_post_publicly_viewable' ) ) {
			return (bool) is_post_publicly_viewable( $post );
		}

		return function_exists( 'is_post_status_viewable' ) && function_exists( 'is_post_type_viewable' )
			&& is_post_type_viewable( $post->post_type )
			&& is_post_status_viewable( $post->post_status );
	}

	private static function groupSourceId( $trid, $sourceLanguage, $elementType ) {
		if ( $trid <= 0 || '' === $elementType ) {
			return 0;
		}

		global $wpdb;

		$languageCondition = null === $sourceLanguage || '' === $sourceLanguage
			? ' AND source_language_code IS NULL'
			: $wpdb->prepare( ' AND language_code = %s', (string) $sourceLanguage );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s",
				$trid,
				$elementType
			) . $languageCondition . ' LIMIT 1'
		);
	}
}
