<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;


class AiTranslationEngineCard {

  const PTC_NAME = 'Private Translation Cloud (PTC)';


  public static function render(): void {
    echo '<div id="engine">';

    self::renderLoadingState();
    self::renderUnavailableState();

    echo '<div class="wpml-ai-card wpml-ai-engine-variant--ptc">';
    echo '<h2 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px 0">'
      /* translators: Name of the card on WPML → Settings that shows which service translates the site: its heading and its entry in the settings search list. */
      . esc_html__( 'Translation engine', 'wpml' )
      . '</h2>';
    self::renderPtcVariant();
    echo '</div>';

    self::renderLegacyVariant();
    self::renderNoEngineVariant();

    self::renderPtcConfirmation();
    self::renderLegacyConfirmation();

    echo '</div>';
  }


  private static function renderLoadingState(): void {
    echo '<div class="wpml-ai-card wpml-ai-engine-state--loading" role="status" aria-live="polite">';
    echo '<h2 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px 0">'
      /* translators: Name of the card on WPML → Settings that shows which service translates the site: its heading and its entry in the settings search list. */
      . esc_html__( 'Translation engine', 'wpml' )
      . '</h2>';
    echo '<p class="wpml-ai-loading" style="padding:0;margin:0">'
      . '<span class="spinner is-active" style="float:none;margin:0"></span>'
      /* translators: Shown on WPML → Settings while the page is finding out which translation engine the site uses. */
      . esc_html__( 'Checking which translation engine is active…', 'wpml' )
      . '</p>';
    echo '</div>';
  }


  private static function renderUnavailableState(): void {
    echo '<div class="wpml-ai-card wpml-ai-engine-state--unavailable">';
    echo '<h2 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px 0">'
      /* translators: Name of the card on WPML → Settings that shows which service translates the site: its heading and its entry in the settings search list. */
      . esc_html__( 'Translation engine', 'wpml' )
      . '</h2>';
    echo '<p style="font-size:13px;color:#6b7280;margin:0 0 12px 0">'
      . esc_html__( "Couldn't load translation engine information.", 'wpml' )
      . '</p>';
    echo '<button type="button" id="wpml-ai-engine-retry" class="button-primary wpml-button base-btn">'
      /* translators: Button label shown after something failed, to repeat it. Verb phrase, imperative. */
      . esc_html__( 'Try again', 'wpml' )
      . '</button>';
    echo '</div>';
  }


  private static function renderPtcConfirmation(): void {
    echo '<div class="wpml-ai-card wpml-ai-engine-confirmation--ptc" role="status"'
      . ' style="display:none;background:rgba(39,173,149,.1);border-color:#1e8876">';
    echo '<h2 style="font-size:14px;font-weight:600;color:#373737;margin:0 0 4px 0">';
    printf(
      /* translators: %s: the PTC engine product name. */
      esc_html__( "You're now using %s", 'wpml' ),
      esc_html( self::PTC_NAME )
    );
    echo '</h2>';
    echo '<p style="font-size:13px;color:#373737;margin:4px 0 13px 0">'
      /* translators: Lead-in on WPML → Settings, followed by a bulleted list of what the translation engine adds. It ends with a colon on purpose. */
      . esc_html__( 'Your translations now come with:', 'wpml' )
      . '</p>';
    self::renderBenefits( '0' );
    echo '</div>';
  }


  private static function renderLegacyConfirmation(): void {
    list( $leftName, $leftSubline ) = self::genericLegacyColumn();

    echo '<div class="wpml-ai-card wpml-ai-engine-confirmation--legacy"'
      . ' style="display:none">';
    echo '<p role="status" style="font-size:14px;font-weight:600;color:#111827;margin:0 0 4px 0">'
      /* translators: Confirmation on WPML → Settings right after the client moved the site to another translation engine. */
      . esc_html__( 'Translation engine switched.', 'wpml' )
      . '</p>';

    self::renderComparison(
      $leftName,
      $leftSubline,
      /* translators: Subline under the PTC column of the engine comparison table on WPML → Settings. It names what PTC is. */
      __( "WPML's own AI engine", 'wpml' ),
      'switch-back'
    );
    echo '</div>';
  }


  private static function renderPtcVariant(): void {
    $switchLink = '<button type="button" id="wpml-ai-engine-switch-legacy"'
      . ' style="background:none;border:0;padding:0;margin:0;font:inherit;'
      . 'font-size:12px;color:#666;text-decoration:underline;cursor:pointer">'
      . esc_html__( 'Switch to a different engine', 'wpml' )
      . '</button>';

    echo '<p style="font-size:13px;color:#1f2937;margin:0">';
    printf(
      /* translators: Message on WPML → Settings about the translation engine in use. %1$s: the name of that engine, in bold, %2$s: a link reading "Switch to a different engine". */
      esc_html__( 'Currently using %1$s. %2$s if you prefer — no quality guarantee provided with other engines.', 'wpml' ),
      '<strong>' . esc_html( self::PTC_NAME ) . '</strong>',
      $switchLink
    );
    echo '</p>';

    self::renderQualityBar();
  }


  private static function renderLegacyVariant(): void {
    list( $leftName, $leftSubline ) = self::genericLegacyColumn();

    echo '<div class="wpml-ai-card wpml-ai-engine-variant--legacy"'
      . ' style="background:rgba(39,173,149,.1);border-color:#1e8876">';
    echo '<h2 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px 0">'
      /* translators: Name of the card on WPML → Settings that shows which service translates the site: its heading and its entry in the settings search list. */
      . esc_html__( 'Translation engine', 'wpml' )
      . '</h2>';

    self::renderComparison(
      $leftName,
      $leftSubline,
      /* translators: Subline under the PTC column of the engine comparison table on WPML → Settings. It names what PTC is. */
      __( "WPML's own AI engine", 'wpml' ),
      'switch'
    );
    echo '</div>';
  }


  private static function genericLegacyColumn(): array {
    return array(
      /* translators: Header of the left column of the engine comparison table on WPML → Settings, when the exact engine is not known yet. It stands for any of the older engines. */
      __( 'A legacy engine', 'wpml' ),
      /* translators: Subline under the left column of the engine comparison table on WPML → Settings, naming the older engines. These are product names; keep them. */
      __( 'DeepL, Google Translate or Microsoft Translator', 'wpml' ),
    );
  }


  private static function renderNoEngineVariant(): void {
    echo '<div class="wpml-ai-card wpml-ai-engine-variant--none"'
      . ' style="background:rgba(39,173,149,.1);border-color:#1e8876">';
    echo '<h2 style="font-size:14px;font-weight:600;color:#373737;margin:0 0 4px 0">'
      . esc_html__( 'No translation engine is active', 'wpml' )
      . '</h2>';

    self::renderComparison(
      /* translators: Header of the left column of the engine comparison table on WPML → Settings when the site has no translation engine turned on. */
      __( 'No engine', 'wpml' ),
      /* translators: Subline under the left column of the engine comparison table on WPML → Settings when the site has no translation engine turned on. */
      __( 'Automatic translation is off', 'wpml' ),
      /* translators: Subline under the PTC column of the engine comparison table on WPML → Settings. It names what PTC is. */
      __( "WPML's own AI engine", 'wpml' ),
      'activate',
      true
    );
    echo '</div>';
  }


  private static function renderQualityBar(): void {
    echo '<div class="wpml-ai-quality-bar">';
    echo '<span class="wpml-ai-quality-bar__label">'
      /* translators: Label in front of the translation-quality bar on WPML → Settings. It ends with a colon on purpose. */
      . esc_html__( 'Translation quality:', 'wpml' )
      . '</span>';
    echo '<div class="wpml-ai-quality-bar-mount"'
      . ' data-wpml-quality-bar'
      . ' data-engine-slug=""'
      . ' data-engine-name=""'
      . '></div>';
    echo '</div>';

    echo '<div class="wpml-ai-quality-disclaimer-mount"'
      . ' data-wpml-quality-disclaimer></div>';
  }


  private static function renderBenefits( string $bottomMargin = '13px' ): void {
    $benefits = array(
      sprintf(
        /* translators: Bullet point about the translation engine on WPML → Settings. %1$s: opening bold tag, %2$s: closing bold tag. */
        esc_html__( "%1\$sContextual translation%2\$s — understands your site's audience, tone and topic.", 'wpml' ),
        '<b>',
        '</b>'
      ),
      sprintf(
        /* translators: Bullet point about the translation engine on WPML → Settings. %1$s: opening bold tag, %2$s: closing bold tag. */
        esc_html__( '%1$sBest for SEO and branding%2$s — translations that fit your voice.', 'wpml' ),
        '<b>',
        '</b>'
      ),
      '<b>' . esc_html__( 'Backed by our translation quality guarantee', 'wpml' ) . '</b>',
    );

    echo '<ul style="list-style:none;margin:0 0 ' . esc_attr( $bottomMargin ) . ' 0;padding:0">';
    foreach ( $benefits as $benefit ) {
      echo '<li style="display:flex;align-items:center;gap:6px;'
        . 'font-size:13px;color:#373737;margin:0 0 6px 0">';
      echo '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 14 14"'
        . ' fill="none" aria-hidden="true" style="flex-shrink:0">'
        . '<path d="M13.8416 1.72946C13.6597 1.5475 13.3646 1.5475 13.1827 1.72946L5.23717'
        . ' 9.67498L0.99155 5.42936C0.809305 5.24712 0.513025 5.24792 0.330781 5.43016C0.148536'
        . ' 5.61241 0.147733 5.90869 0.329978 6.09093L4.89314 10.6541C4.95161 10.7126 5.0218'
        . ' 10.7522 5.09621 10.773C5.25766 10.8257 5.44217 10.7878 5.57047 10.6595L13.8416'
        . ' 2.38838C14.0236 2.20643 14.0236 1.91141 13.8416 1.72946Z" fill="#27ad95"></path>'
        . '</svg>';
      echo '<span>' . $benefit . '</span>';
      echo '</li>';
    }
    echo '</ul>';
  }


  private static function renderComparison(
    string $leftName,
    string $leftSubline,
    string $rightSubline,
    string $cta = '',
    bool $noEngine = false
  ): void {
    echo '<table class="wpml-ai-compare">';
    echo '<thead><tr>';
    echo '<td class="wpml-ai-compare__corner"></td>';
    echo '<th scope="col" class="wpml-ai-compare__head">'
      . '<span class="wpml-ai-compare__engine" data-wpml-ai-left-name>' . esc_html( $leftName ) . '</span>'
      . '<span class="wpml-ai-compare__sub" data-wpml-ai-left-sub>' . esc_html( $leftSubline ) . '</span>'
      . '</th>';
    echo '<th scope="col" class="wpml-ai-compare__head wpml-ai-compare__col--ptc">'
      . '<span class="wpml-ai-compare__engine">PTC</span>'
      . '<span class="wpml-ai-compare__sub">' . esc_html( $rightSubline ) . '</span>'
      . '</th>';
    echo '</tr></thead>';

    echo '<tbody>';
    foreach ( self::comparisonRows( $noEngine ) as $row ) {
      echo '<tr>';
      echo '<th scope="row" class="wpml-ai-compare__label">' . esc_html( $row['label'] ) . '</th>';
      echo '<td class="wpml-ai-compare__cell wpml-ai-compare__cell--off">'
        . '<span class="wpml-ai-compare__answer">'
        . '<span class="wpml-ai-compare__mark" aria-hidden="true">&#10007;</span>'
        . '<span>' . esc_html( $row['left'] ) . '</span></span></td>';
      echo '<td class="wpml-ai-compare__cell wpml-ai-compare__cell--on wpml-ai-compare__col--ptc">'
        . '<span class="wpml-ai-compare__answer">'
        . '<span class="wpml-ai-compare__mark" aria-hidden="true">&#10003;</span>'
        . '<span>' . esc_html( $row['right'] ) . '</span></span></td>';
      echo '</tr>';
    }

    if ( $cta !== '' ) {
      echo '<tr class="wpml-ai-compare__actions">';
      echo '<td></td><td></td>';
      echo '<td class="wpml-ai-compare__cta wpml-ai-compare__col--ptc">';
      self::renderCtaButton( $cta );
      echo '</td>';
      echo '</tr>';
    }
    echo '</tbody></table>';
  }


  private static function renderCtaButton( string $cta ): void {
    if ( $cta === 'switch' ) {
      echo '<button type="button" id="wpml-ai-engine-switch-ptc"'
        . ' class="button-primary wpml-button base-btn">'
        /* translators: Label of the button, and of the notice heading, that switch the site's automatic translation engine to Private Translation Cloud (PTC). Verb, imperative. PTC is a product name; keep it. */
        . esc_html__( 'Switch to PTC', 'wpml' )
        . '</button>';

      return;
    }

    if ( $cta === 'switch-back' ) {
      echo '<button type="button" id="wpml-ai-engine-switch-back-ptc"'
        . ' class="button-primary wpml-button base-btn">'
        /* translators: Button label on WPML → Settings that moves the site back to the PTC translation engine. Verb, imperative. PTC is a product name; keep it. */
        . esc_html__( 'Switch back to PTC', 'wpml' )
        . '</button>';

      return;
    }

    echo '<button type="button" id="wpml-ai-engine-activate-ptc"'
      . ' class="button-primary wpml-button base-btn">'
      /* translators: Button label on WPML → Settings that switches the site to the PTC translation engine. Verb, imperative. PTC is a product name and stays as it is. */
      . esc_html__( 'Activate PTC', 'wpml' )
      . '</button>';
  }


  private static function comparisonRows( bool $noEngine = false ): array {
    /* translators: The "available with PTC" answer in the engine comparison table on WPML → Settings. */
    $available = __( 'Available', 'wpml' );
    /* translators: The "not available with this engine" answer in the engine comparison table on WPML → Settings. */
    $notAvailable = __( 'Not available', 'wpml' );

    return array(
      array(
        /* translators: Row of the engine comparison table on WPML → Settings, about the engine reading the site's own context. */
        'label' => __( "Understands your site's topic, audience and tone", 'wpml' ),
        'left'  => $notAvailable,
        'right' => $available,
      ),
      array(
        /* translators: Row of the engine comparison table on WPML → Settings, about translation quality. */
        'label' => __( 'Publish-ready quality, backed by our guarantee', 'wpml' ),
        'left'  => $noEngine
          ? $notAvailable
          /* translators: Answer in the engine comparison table on WPML → Settings: with this engine the client has to read the translations before they go live. */
          : __( 'Review before publishing', 'wpml' ),
        'right' => $available,
      ),
      array(
        /* translators: Row of the engine comparison table on WPML → Settings, about what a translation costs. Keep the dash. */
        'label' => __( 'Pay once per page – only added words are charged', 'wpml' ),
        'left'  => $noEngine
          ? $notAvailable
          /* translators: Answer in the engine comparison table on WPML → Settings: this engine charges for every word it translates, including the words of a page that is translated again after an edit. */
          : __( 'Per word, changes included', 'wpml' ),
        'right' => $available,
      ),
    );
  }


  public static function renderModal(): void {
    ?>
    <style>
      /* The dialog fills the viewport and is the scroll container, so a
         dialog taller than the window scrolls at the viewport edge (like a
         page scroll) instead of a scrollbar inside the box. The visible card
         is the inner __box, centered with a top/bottom margin. */
      .wpml-ai-switch-modal{width:100%;max-width:none;height:100%;
        max-height:none;margin:0;padding:0;border:0;background:transparent;
        overflow-y:auto}
      .wpml-ai-switch-modal__box{position:relative;width:620px;max-width:92vw;
        margin:5vh auto;padding:1.25rem;background:#fff;border:1px solid #e5e7eb;
        border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.18)}
      .wpml-ai-switch-modal::backdrop{background:rgba(17,24,39,.5)}
      /* The dialog itself receives initial focus (tabindex="-1" +
         dialog.focus() on open) so showModal() doesn't land on the
         Cancel button and paint its dark :focus state. */
      .wpml-ai-switch-modal:focus{outline:none}
      /* Close X — same markup + look as the Dashboard React Modal's
         close button (app.scss `.modal-close`: absolute 16/16, icon in
         40% $wpml-blue fading to full on hover). That bundle isn't
         enqueued on Settings pages, so the rules are re-emitted here
         scoped to this dialog. */
      .wpml-ai-switch-modal .modal-close{position:absolute;top:16px;
        right:16px;padding:0;background:none;border:0;cursor:pointer;
        font-size:14px;line-height:1}
      .wpml-ai-switch-modal .modal-close .otgs-ico::before{
        color:rgba(47,125,146,.4);transition:color .3s ease-out}
      .wpml-ai-switch-modal .modal-close:hover .otgs-ico::before,
      .wpml-ai-switch-modal .modal-close:focus .otgs-ico::before{
        color:#2f7d92}
      /* Tailwind preflight overrides the .otgs-ico font on this page
         (same cascade fight as the Recommended badge glyph) — re-pin
         the icon font so the cancel glyph renders as the X. */
      .wpml-ai-switch-modal .modal-close .otgs-ico{font-family:'otgs-icons'!important}
      /* Centered action row + 24px gap — same as the Dashboard's TEA
         enable-modal `.actions` variant. */
      .wpml-ai-switch-modal-footer{display:flex;justify-content:center;
        align-items:center;gap:24px;margin-top:1rem;border-top:1px solid #f3f4f6;
        padding-top:1rem}
      .wpml-ai-switch-modal-error{color:#b91c1c;font-size:12px}
      /* r12b — the second answer is rendered on every open and shown only for
         the switch away from PTC. `hidden` alone does not hide it: the
         plugin's own `.wpml-button.base-btn` (res/css/style.css) opens with
         `all: unset`, which unsets the `display:none` the attribute relies
         on, and the walk found a small blank button beside "Fill in the
         context". An id selector out-specifies that rule, so the attribute
         means what it says again. */
      #wpml-ai-switch-modal-stay[hidden]{display:none}
      /* Validation message above the action — red box, same look as the
         dashboard modal's `.wpml-engine-switch__error`. */
      .wpml-ai-switch-context-error{margin:10px 0 12px;padding:10px 12px;
        border-radius:4px;background:#fcf0f1;color:#8a1f11;font-size:13px}
      /* Engine-card variant gating — same wrapper-class mechanism as
         the Recommended badges. After a successful switch the TS asset
         hides both state variants (inline display:none beats these
         rules) and shows the matching confirmation card instead. */
      .wpml-ai-engine-variant--ptc,
      .wpml-ai-engine-variant--legacy,
      .wpml-ai-engine-variant--none{display:none}
      #wpml-ai-settings.wpml-ai-settings--engine-ptc .wpml-ai-engine-variant--ptc,
      #wpml-ai-settings.wpml-ai-settings--engine-other .wpml-ai-engine-variant--legacy,
      #wpml-ai-settings.wpml-ai-settings--engine-none .wpml-ai-engine-variant--none{
        display:block}
      /* wpmldev-8174 — the two states the page passes through BEFORE an
         engine class exists: the answer in flight, and the answer being
         that the list cannot be read. Gated on the wrapper's
         data-wpml-ai-engine attribute, which the controller prints as
         "loading" and the TS asset replaces with the answer's state. */
      .wpml-ai-engine-state--loading,
      .wpml-ai-engine-state--unavailable{display:none}
      #wpml-ai-settings[data-wpml-ai-engine="loading"] .wpml-ai-engine-state--loading,
      #wpml-ai-settings[data-wpml-ai-engine="unavailable"] .wpml-ai-engine-state--unavailable{
        display:block}

      /* r12 — the engine comparison table (see renderComparison()). Fixed
         layout with the two engine columns pinned: the row labels then take
         whatever is left and wrap, so the table fits the admin's narrowest
         layout and the 620px dialog without a horizontal scroll. The PTC
         column carries a faint tint of the card's own green so the eye finds
         the answer column before it reads a word. */
      .wpml-ai-compare{width:100%;margin:8px 0 0;border-collapse:collapse;
        table-layout:fixed;font-size:13px;color:#373737}
      .wpml-ai-compare th,.wpml-ai-compare td{padding:8px 10px;text-align:left;
        vertical-align:top;border-bottom:1px solid rgba(30,136,118,.2)}
      /* Wide enough for the longest answer and for the CTA on one line. */
      .wpml-ai-compare thead th{width:168px;font-weight:600;color:#111827}
      .wpml-ai-compare__engine{display:block;line-height:1.3}
      .wpml-ai-compare__sub{display:block;margin-top:2px;font-size:12px;
        font-weight:400;line-height:1.3;color:#6b7280}
      .wpml-ai-compare__label{font-weight:400;color:#373737;line-height:1.4}
      /* The mark is its own column INSIDE the cell, so an answer that wraps
         keeps its second line under its first instead of under the glyph. */
      .wpml-ai-compare__answer{display:flex;gap:5px;align-items:flex-start;
        line-height:1.4}
      .wpml-ai-compare__cell--off{color:#6b7280}
      .wpml-ai-compare__cell--on{color:#1e8876}
      .wpml-ai-compare__mark{flex:0 0 auto}
      .wpml-ai-compare__col--ptc{background:rgba(39,173,149,.12)}
      .wpml-ai-compare tbody tr:last-child th,
      .wpml-ai-compare tbody tr:last-child td{border-bottom:0}
      .wpml-ai-compare__actions td{padding-top:12px}
      .wpml-ai-compare__cta{text-align:center}
      /* Narrow admin layout: tighter padding, and the engine columns keep
         their width -- they hold the CTA, which wraps to two lines the
         moment they give any of it back. The labels wrap instead, which is
         what a label column is for. */
      @media screen and (max-width:900px){
        .wpml-ai-compare th,.wpml-ai-compare td{padding:8px 7px}
      }

      /* The dialog's copy of the table (ruling 4). It sits above the AMS
         hosts and is revealed only for the switch AWAY from PTC. Its box is
         620px whatever the viewport is, and it carries no CTA column, so the
         engine columns are the narrow pair at every width. */
      #wpml-ai-switch-compare[hidden]{display:none}
      #wpml-ai-switch-compare .wpml-ai-compare{margin:0 0 4px}
      #wpml-ai-switch-compare .wpml-ai-compare thead th{width:140px}

      /* Label left of the track, aligned to the track itself rather than to
         the bar box (the box carries 24px of bottom padding for the marker
         labels, so plain centring would ride high). */
      .wpml-ai-quality-bar{display:flex;align-items:flex-start;gap:12px;margin:12px 0 0}
      .wpml-ai-quality-bar__label{flex:0 0 auto;margin-top:22px;font-size:13px;
        font-weight:600;color:#111827;white-space:nowrap}
      .wpml-ai-quality-bar-mount{flex:1 1 auto;min-width:0}

      /* wpmldev-7554 — sitepress's own admin CSS sets
         `body.wpml_page_tm-menu-settings a{text-decoration:none}` and
         `... p{font-size:14px}`, both of which out-specify the shared
         component's `.wpml-quality-disclaimer` rules and would leave the
         attribution at 14px with the link indistinguishable from plain text.
         Re-assert them page-scoped so the link still reads as a link — the
         same quiet 12px/#666 underline the "Switch to a different engine"
         control above uses. */
      body.wpml_page_tm-menu-settings .wpml-quality-disclaimer{font-size:12px}
      /* font-size is re-stated on the <a> too: the same rule targets links
         directly, so inheriting 12px from the <p> is not enough. */
      body.wpml_page_tm-menu-settings .wpml-quality-disclaimer a{
        font-size:12px;color:inherit;text-decoration:underline}
      body.wpml_page_tm-menu-settings .wpml-quality-disclaimer a:hover,
      body.wpml_page_tm-menu-settings .wpml-quality-disclaimer a:focus{
        color:#373737}

      /* wpmldev-7326 — PTC-active presentation of the shared Translation
         Quality bar (mockup `translation_quality.png`).

         The component (SharedKernel/.../QualityBar) draws two markers: the
         `--cluster` one, driven entirely by the props we pass and carrying
         the "Your engine" tag, plus a FIXED `--ptc` reference marker at 90.
         With PTC active we pass PTC as the active engine, so the cluster
         marker already renders "PTC · 90 · Your engine" at the right spot
         and the fixed `--ptc` marker becomes an exact duplicate sitting on
         top of it.

         So: drop the duplicate, and recolour the cluster's active look from
         amber to green — values taken from the `--ptc.is-active` rules in
         the component's own SCSS. No component change needed. */
      [data-wpml-quality-bar][data-engine-slug="llm"] .wpml-quality-bar__marker--ptc{
        display:none}
      [data-wpml-quality-bar][data-engine-slug="llm"]
        .wpml-quality-bar__marker--cluster.is-active .wpml-quality-bar__pin{
        border-color:#2f9a55;
        box-shadow:0 0 0 4px rgba(47,154,85,.2),0 1px 3px rgba(0,0,0,.3);
        width: 16px;
        height: 16px;
      }
      [data-wpml-quality-bar][data-engine-slug="llm"]
        .wpml-quality-bar__marker--cluster.is-active .wpml-quality-bar__score{
        color:#2f9a55}
      [data-wpml-quality-bar][data-engine-slug="llm"]
        .wpml-quality-bar__marker--cluster.is-active .wpml-quality-bar__tag{
        background:#e6f4ec;color:#2f7a4a}
    </style>
    <dialog id="wpml-ai-switch-modal" class="wpml-ai-switch-modal"
      tabindex="-1" aria-labelledby="wpml-ai-switch-modal-title">
      <div class="wpml-ai-switch-modal__box">
      <button type="button" id="wpml-ai-switch-close"
        class="wpml-button base-btn text-button modal-close" data-testid="modal-close"
        aria-label="<?php echo /* translators: Accessible label (screen readers) of the button that closes a dialog on WPML → Settings. Verb, imperative, not the adjective "near". */ esc_attr__( 'Close', 'wpml' ); ?>">
        <i class="otgs-ico otgs-ico-cancel" aria-hidden="true"></i>
      </button>
      <?php  ?>
      <h2 id="wpml-ai-switch-modal-title"
        style="font-size:18px;font-weight:600;color:#111827;margin:0 0 12px 0"></h2>
      <?php
      echo '<div id="wpml-ai-switch-compare" hidden>';
      self::renderComparison(
        /* translators: Header of the left column of the engine comparison table on WPML → Settings, when the exact engine is not known yet. It stands for any of the older engines. */
        __( 'A legacy engine', 'wpml' ),
        /* translators: Subline under the left column of the engine comparison table on WPML → Settings, naming the older engines. These are product names; keep them. */
        __( 'DeepL, Google Translate or Microsoft Translator', 'wpml' ),
        /* translators: Subline under the PTC column of the engine comparison table in the dialog that switches the site away from PTC. It says PTC is what the site uses at this moment. */
        __( 'Your engine now', 'wpml' )
      );
      echo '</div>';
      ?>
      <div id="wpml-ai-switch-modal-loading" class="wpml-ai-loading">
        <span class="spinner is-active" style="float:none;margin:0"></span>
        <?php echo /* translators: Shown while a dialog or a panel is still fetching what it has to show. */ esc_html__( 'Loading…', 'wpml' ); ?>
      </div>
      <div id="wpml-ai-switch-modal-body"></div>
      <p id="wpml-ai-switch-modal-error" class="wpml-ai-switch-modal-error" hidden>
        <span id="wpml-ai-switch-modal-error-text"></span>
        <button type="button" id="wpml-ai-switch-retry" class="button">
          <?php echo /* translators: Button label shown after something failed, to repeat it. Verb phrase, imperative. */ esc_html__( 'Try again', 'wpml' ); ?>
        </button>
      </p>
      <?php  ?>
      <?php  ?>
      <p id="wpml-ai-switch-context-error" class="wpml-ai-switch-context-error"
        role="alert" hidden></p>
      <?php  ?>
      <?php  ?>
      <?php  ?>
      <?php  ?>
      <?php  ?>
      <div class="wpml-ai-switch-modal-footer">
        <span id="wpml-ai-switch-status" class="wpml-ai-save-status" aria-live="polite"></span>
        <button type="button" id="wpml-ai-switch-save"
          class="button-primary wpml-button base-btn" disabled></button>
        <button type="button" id="wpml-ai-switch-modal-stay"
          class="button-primary wpml-button base-btn" hidden></button>
      </div>
      </div>
    </dialog>
    <?php
  }


}
