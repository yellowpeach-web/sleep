<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;


class TabStripRenderer {


  public function render( array $tabs, string $activeTabId ): void {
    $visibleCount = 0;
    foreach ( $tabs as $tab ) {
      if ( $tab->isVisible() ) {
        $visibleCount++;
      }
    }
    if ( $visibleCount <= 1 ) {
      return;
    }

    echo '<div class="wpml-translations-tab-strip wpml:flex wpml:gap-0 wpml:border-b wpml:border-gray-300 wpml:mb-6 wpml:flex-wrap">';

    foreach ( $tabs as $tab ) {
      if ( ! $tab->isVisible() ) {
        continue;
      }

      $href     = self::tabHref( $tab->id() );
      $isActive = $tab->id() === $activeTabId;
      $cls = $isActive
        ? 'wpml-translations-tab wpml-translations-tab-active wpml:relative wpml:px-4 wpml:py-2.5 wpml:whitespace-nowrap'
        : 'wpml-translations-tab wpml:relative wpml:px-4 wpml:py-2.5 wpml:whitespace-nowrap wpml:text-gray-500 wpml:hover:text-gray-700';

      echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $href ) . '"' . ( $isActive ? ' aria-current="page"' : '' ) . '>';
      echo esc_html( $tab->label() );
      $badgeSlotId = $tab->badgeSlotId();
      if ( $badgeSlotId !== null && ! $isActive ) {
        echo '<span class="wpml-tab-badge wpml-tab-badge--attention" id="' . esc_attr( $badgeSlotId ) . '" hidden></span>';
      }
      echo '<span class="tab-tip" aria-hidden="true">';
      echo esc_html( $tab->tooltip() );
      echo '<span class="tab-tip-arrow"></span>';
      echo '</span>';
      echo '</a>';
    }

    echo '</div>';
  }


  private static function tabHref( string $tabId ): string {
    if ( $tabId === 'dashboard' ) {
      return admin_url( 'admin.php?page=tm/menu/main.php' );
    }
    return admin_url( 'admin.php?page=tm/menu/main.php&tab=' . rawurlencode( $tabId ) );
  }


}
