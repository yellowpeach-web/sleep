<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml;

class WcmlSettingsSearchIndex implements \IWPML_Backend_Action {


  const SETTINGS_PAGE = 'admin.php?page=tm/menu/settings';


  public function add_hooks() {
    add_filter( 'wpml_settings_search_index', array( $this, 'append' ) );
  }


  public function append( $index ) {
    if ( ! is_array( $index ) || ! self::isWcmlActive() ) {
      return $index;
    }

      $index[] = array(
          /* translators: Name of the WooCommerce Multilingual section of WPML → Settings: its entry in the settings search list and its heading. WCML is the product's short name and stays as it is. */
          'section'     => __( 'WCML', 'wpml' ),
          'href'        => self::SETTINGS_PAGE . '&section=wcml',
          'icon'        => 'wcml',
          'capability'  => 'manage_woocommerce',
          'description' => __( 'Product sync, media, download files, reviews and cart behavior.', 'wpml' ),
          'group'       => __( 'WCML — WooCommerce Multilingual & Multicurrency', 'wpml' ),
          'subs'        => array(
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce Multilingual section. Product name: keep it as it is. */
              array( 'label' => __( 'WooCommerce Multilingual', 'wpml' ), 'anchor' => '' ),
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce setting that picks which editor translates products. */
              array( 'label' => __( 'Translation interface', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Variation translation sync on product save', 'wpml' ), 'anchor' => '' ),
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce block about keeping product details the same across languages. */
              array( 'label' => __( 'Products Synchronization', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Products Media Synchronization', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Products Download Files', 'wpml' ), 'anchor' => '' ),
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce setting about product reviews. */
              array( 'label' => __( 'Product reviews', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Cart behavior across languages', 'wpml' ), 'anchor' => '' ),
          ),
      );

      $index[] = array(
          /* translators: Name of the Multicurrency section of WPML → Settings: its entry in the settings search list and its heading. Selling in more than one currency. */
          'section'     => __( 'Multicurrency', 'wpml' ),
          'href'        => self::SETTINGS_PAGE . '&section=multi-currency',
          'icon'        => 'multi-currency',
          'capability'  => 'manage_woocommerce',
          'description' => __( 'Enable multiple currencies, exchange rates, currency switcher behavior.', 'wpml' ),
          'group'       => __( 'WCML — WooCommerce Multilingual & Multicurrency', 'wpml' ),
          'subs'        => array(
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce setting that switches on selling in more than one currency. Verb phrase, imperative. */
              array( 'label' => __( 'Enable multicurrency', 'wpml' ), 'anchor' => '' ),
              /* translators: Entry in the settings search list of WPML → Settings, pointing at the WooCommerce list of currencies. */
              array( 'label' => __( 'Currencies', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Automatic Exchange Rates', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Currency switcher options', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Widget Currency Switcher', 'wpml' ), 'anchor' => '' ),
              array( 'label' => __( 'Product page Currency Switcher', 'wpml' ), 'anchor' => '' ),
          ),
      );

      return $index;
  }


  private static function isWcmlActive(): bool {
    return WcmlBonded::rendersEmbeddedBodies();
  }


}
