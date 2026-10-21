<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class ContentTypesSettingsSearchIndex implements \IWPML_Backend_Action {

  const POST_TYPES_SECTION = 'section=post-types';
  const TAXONOMIES_SECTION = 'section=taxonomies';
  const POST_TYPE_TARGET_PREFIX = 'wpml-search-post-type-';
  const TAXONOMY_TARGET_PREFIX = 'wpml-search-taxonomy-';


  public function add_hooks() {
    add_filter( 'wpml_settings_search_index', array( $this, 'addItems' ) );
  }


  public function addItems( $index ) {
    if ( ! is_array( $index ) ) {
      return $index;
    }

    foreach ( $index as $i => $section ) {
      if ( ! is_array( $section ) || ! isset( $section['href'] ) || ! is_string( $section['href'] ) ) {
        continue;
      }

      if ( strpos( $section['href'], self::POST_TYPES_SECTION ) !== false ) {
        $index[ $i ]['subs'] = array_merge(
          isset( $section['subs'] ) && is_array( $section['subs'] ) ? $section['subs'] : array(),
          $this->postTypeItems()
        );
      }

      if ( strpos( $section['href'], self::TAXONOMIES_SECTION ) !== false ) {
        $index[ $i ]['subs'] = array_merge(
          isset( $section['subs'] ) && is_array( $section['subs'] ) ? $section['subs'] : array(),
          $this->taxonomyItems()
        );
      }
    }

    return $index;
  }


  private function postTypeItems(): array {
    if ( ! class_exists( 'WPML_Post_Types' ) || ! isset( $GLOBALS['sitepress'] ) ) {
      return array();
    }

    $postTypes = ( new \WPML_Post_Types( $GLOBALS['sitepress'] ) )->get_translatable_and_readonly();
    $items     = array();

    foreach ( $postTypes as $slug => $postType ) {
      $slug  = (string) $slug;
      $label = isset( $postType->labels->name ) ? trim( wp_strip_all_tags( $postType->labels->name ) ) : '';
      if ( $label === '' ) {
        continue;
      }

      $items[] = array(
        'label'  => sprintf( '%s (%s)', $label, $slug ),
        'anchor' => 'ml-content-setup-sec-7',
        'target' => self::POST_TYPE_TARGET_PREFIX . sanitize_title( $slug ),
      );
    }

    return $items;
  }


  private function taxonomyItems(): array {
    global $wp_taxonomies;

    if ( ! is_array( $wp_taxonomies ) ) {
      return array();
    }

    $excluded = array( 'nav_menu', 'link_category', 'post_format' );
    $items    = array();

    foreach ( array_diff( array_keys( $wp_taxonomies ), $excluded ) as $slug ) {
      $slug    = (string) $slug;
      $taxonomy = $wp_taxonomies[ $slug ];
      $label    = isset( $taxonomy->label ) ? trim( wp_strip_all_tags( $taxonomy->label ) ) : '';
      if ( $label === '' ) {
        continue;
      }

      $items[] = array(
        'label'  => sprintf( '%s (%s)', $label, $slug ),
        'anchor' => 'ml-content-setup-sec-8',
        'target' => self::TAXONOMY_TARGET_PREFIX . sanitize_title( $slug ),
      );
    }

    return $items;
  }


}
