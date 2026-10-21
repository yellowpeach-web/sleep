<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\UserInterface\Web\Core\Component\Dashboard\Application\DashboardRequirements;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

/**
 * M3/M4 — Taxonomies Translation sub-page.
 *
 * Renders the `ml-content-setup-sec-8` section extracted from
 * `menu/_custom_types_translation.php`. Per changes-details line 219
 * + dev-spec §13 M6, the "Show translated taxonomies in Translation
 * Editor" checkbox is duplicated here (it also lives on Translated
 * Documents Options) so users can flip it from either context — both
 * surfaces share the same option key (`tm_block_retranslating_terms`)
 * and the same AJAX endpoint, so flipping it on one page reflects on
 * the other.
 *
 * Markup is emitted by `TranslatedTaxonomiesToggle::renderCard()` so
 * both pages share one source of truth for the checkbox UI and the
 * hidden-mirror sync trick that maps the new "Show…" label to the
 * legacy `tm_block_retranslating_terms` option (which still stores
 * `1=hide`, `''=show`).
 *
 * The card is a Translation Editor setting, and a Blog license has no
 * Translation Editor (Translation Management is not loaded), so the card is
 * rendered only when TM is allowed — the same canonical check the dispatcher
 * uses to drop the TM-only sections from the index (wpmldev-8161, product
 * ruling 2026-09-04). The taxonomy table above it stays: it is a core setting.
 */
class TaxonomiesTranslationController implements PageRenderInterface {

  const LEGACY_PARTIAL = '/menu/_custom_types_translation.php';
  const SECTION_ID     = 'ml-content-setup-sec-8';

  private $tmAllowed;


  public function __construct( DashboardRequirements $tmAllowed ) {
    $this->tmAllowed = $tmAllowed;
  }


  public function render() {
    wp_enqueue_script( 'wpml-tm-mcs' );
    wp_enqueue_script( 'wpml-tm-mcs-translate-link-targets' );
    wp_enqueue_script( 'wpml-settings-flash' );

    SettingsPageChrome::printTitle(
      /* translators: Heading of the Taxonomy Translation section of WPML → Settings: translating categories, tags and other ways of grouping content. */
      __( 'Taxonomy Translation', 'wpml' ),
      __( 'Choose which taxonomies can have different terms per language, and how WPML should fall back when a translation is missing.', 'wpml' )
    );

    $html    = LegacySectionExtractor::captureLegacyPartial( ICL_PLUGIN_PATH . self::LEGACY_PARTIAL );
    $section = LegacySectionExtractor::extractSectionById( $html, self::SECTION_ID );

    if ( $section === '' ) {
      echo '<p>' . esc_html__( 'No translatable taxonomies are registered.', 'wpml' ) . '</p>';
    } else {
      $section = LegacySectionExtractor::promoteSectionHeadingsToH2( $section );
      echo LegacySectionExtractor::dissolveHiddenCollision( $section );
    }

    $this->renderTranslatedTaxonomiesCard();
  }


  /**
   * The "Show translated taxonomies in Translation Editor" card, only where a
   * Translation Editor exists (TM allowed). On a Blog license nothing is
   * printed — no card, no cross-link into the (hidden) Translated Documents
   * Options section.
   */
  public function renderTranslatedTaxonomiesCard(): void {
    if ( ! $this->tmAllowed->requirementsMet() ) {
      return;
    }

    TranslatedTaxonomiesToggle::renderCard(
      admin_url( 'admin.php?page=tm/menu/settings&section=translated-documents' ),
      __( 'Translated Documents Options', 'wpml' )
    );
  }


}
