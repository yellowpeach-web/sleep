<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\Component\Settings\Application\SearchIndex;

class SettingsMenuCapability implements \IWPML_Backend_Action, \IWPML_DIC_Action {

  private $searchIndex;

  private $index;


  public function __construct( SearchIndex $searchIndex ) {
    $this->searchIndex = $searchIndex;
  }


  public function add_hooks() {
    add_filter( 'wpml_menu_item_before_build', array( $this, 'openToSectionUsers' ) );
    add_action( 'admin_init', array( $this, 'refuseClosedSections' ), 0 );
  }


  public function openToSectionUsers( $item ) {
    if (
      ! $item instanceof \WPML_Admin_Menu_Item
      || SettingsSectionAccess::PAGE_SLUG !== $item->get_menu_slug()
      || current_user_can( $item->get_capability() )
    ) {
      return $item;
    }

    $capability = SettingsSectionAccess::standaloneCapability( $this->index() );
    if ( $capability !== '' ) {
      $item->set_capability( $capability );
    }

    return $item;
  }


  public function refuseClosedSections() {
    $page    = $this->queryArg( 'page' );
    $section = $this->queryArg( 'section' );

    if (
      SettingsSectionAccess::PAGE_SLUG !== $page
      || $section === ''
      || SettingsSectionAccess::canOpenPage()
      || in_array( $section, SettingsSectionAccess::sectionSlugs( SettingsSectionAccess::filterIndex( $this->index(), false ) ), true )
    ) {
      return;
    }

    wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'sitepress' ), 403 );
  }


  protected function queryArg( string $key ): string {
    $value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );

    return is_string( $value ) ? sanitize_text_field( $value ) : '';
  }


  private function index(): array {
    if ( $this->index === null ) {
      $this->index = SettingsSectionAccess::index( $this->searchIndex->getIndex() );
    }

    return $this->index;
  }


}
