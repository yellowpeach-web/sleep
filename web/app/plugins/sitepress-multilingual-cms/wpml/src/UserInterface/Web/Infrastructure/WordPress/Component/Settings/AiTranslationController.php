<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

use WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteIDQueryInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application\TeaUpgradeFunnelProps;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AmsWidgetEmbed;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AteAutoRegister;
use WPML\TM\ATE\ClonedSites\ReconnectNotice;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Notices\TeaUpgrade\TeaUpgradeCompactNoticeRenderer;

class AiTranslationController implements PageRenderInterface {

  const SAVE_ROUTE = '/wpml/v1/save-automatic-translation-settings';

  private $languagesQuery;

  private $settingsRepository;

  private $teaUpgradeCompactNoticeRenderer;

  private $siteKeyQuery;

  private $siteIdQuery;


  public function __construct(
    LanguagesQueryInterface $languagesQuery,
    SettingsRepository $settingsRepository,
    TeaUpgradeCompactNoticeRenderer $teaUpgradeCompactNoticeRenderer,
    WpmlSiteKeyQueryInterface $siteKeyQuery,
    SiteIDQueryInterface $siteIdQuery
  ) {
    $this->languagesQuery               = $languagesQuery;
    $this->settingsRepository           = $settingsRepository;
    $this->teaUpgradeCompactNoticeRenderer = $teaUpgradeCompactNoticeRenderer;
    $this->siteKeyQuery                 = $siteKeyQuery;
    $this->siteIdQuery                  = $siteIdQuery;

    $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? $_GET['section'] : '';
    if ( $section !== 'ai-translation' ) {
      return;
    }

    AteAutoRegister::attempt();

    if ( self::ateEnabled() ) {
      add_action( 'admin_enqueue_scripts', array( AmsWidgetEmbed::class, 'enqueue' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'enqueueEngineSwitchScript' ) );
    } else {
      add_action( 'admin_enqueue_scripts', array( ClassicEditorActiveCallout::class, 'enqueueScript' ) );
    }
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueueSaveScript' ) );
  }


  public function render() {
    $this->teaUpgradeCompactNoticeRenderer->renderIfVisible(
      TeaUpgradeFunnelProps::SURFACE_AI_SETTINGS,
      self::ateEnabled()
    );

    wp_enqueue_script( 'wpml-settings-flash' );

    if ( wp_style_is( 'wpml-settings', 'registered' ) ) {
      wp_enqueue_style( 'wpml-settings' );
    }

    SettingsPageChrome::printTitle(
      /* translators: Name of the AI Translation section of WPML → Settings: its entry in the settings search list and its heading. */
      __( 'AI Translation', 'wpml' ),
      __( 'Tell the AI about your site and choose how automatic translation behaves.', 'wpml' )
    );

    if ( self::ateEnabled() ) {
      $codes = array_map(
        function ( LanguageDto $language ) {
          return $language->getCode();
        },
        $this->languagesQuery->getActive()
      );

      $languages       = implode( ',', $codes );
      $defaultLanguage = $this->languagesQuery->getDefaultCode();

      echo '<style>#wpml-ai-settings{display:none}'
        . '.wpml-ai-loading{display:flex;align-items:center;gap:10px;padding:24px 0;color:#50575e;font-size:13px}'
        . '.wpml-ai-card{background:#fff;border:1px solid #e5e7eb;'
        . 'border-radius:6px;padding:1.25rem;margin-bottom:1.25rem}'
        . '.wpml-ai-collapsible>summary{list-style:none;cursor:pointer}'
        . '.wpml-ai-collapsible>summary::-webkit-details-marker{display:none}'
        . '.wpml-ai-collapsible[open]>summary .wpml-ai-chevron{transform:rotate(180deg)}'
        . '.wpml-ai-chevron{transition:transform .2s ease}'
        . '.wpml-wpml__AiTranslationTitle{display:none!important}'
        . '</style>';
      echo '<noscript><style>#wpml-ai-settings{display:block!important}'
        . '#wpml-ai-loading{display:none!important}</style></noscript>';
      echo '<div id="wpml-ai-loading" class="wpml-ai-loading">'
        . '<span class="spinner is-active" style="float:none;margin:0"></span>'
        . esc_html__( 'Loading automatic translation settings…', 'wpml' )
        . '</div>';

      echo '<div id="wpml-ai-settings-reconnecting" style="display:none">'
        . wp_kses( ReconnectNotice::renderInlineFallback(), ReconnectNotice::allowedTags() )
        . '</div>';

      echo '<div id="wpml-ai-settings" data-wpml-ai-engine="loading">';



      echo '<div id="context" class="wpml-ai-card">';
      AmsWidgetEmbed::renderSettings( 'context', $languages, $defaultLanguage );
      $this->renderSaveButton();
      echo '</div>';

      echo '<div id="wpml-ai-engine-probe" style="display:none" aria-hidden="true">';
      AmsWidgetEmbed::renderSettings( 'engine-selector', $languages, $defaultLanguage );
      echo '</div>';

      echo '<div id="auto-translate" class="wpml-ai-card">';
      AmsWidgetEmbed::renderSettings( 'translation', $languages, $defaultLanguage );
      $this->renderDraftsCheckbox();
      echo '</div>';

      echo '<div id="after-translation" class="wpml-ai-card">';
      $this->renderReviewModeRadios();
      echo '</div>';

      AiTranslationEngineCard::render();

      echo '<details id="who-can-use" class="wpml-ai-card wpml-ai-collapsible">';
      echo '<summary style="display:flex;align-items:center;justify-content:space-between;gap:10px">';
      echo '<span>';
      echo '<h2 style="display:block;font-size:13px;font-weight:600;color:#111827;margin:0">'
        . esc_html__( 'Who can use Automatic Translation', 'wpml' )
        . '</h2>';
      echo '<span style="display:block;font-size:11px;color:#6b7280;margin-top:2px">'
        . esc_html__( 'Disable automatic translation for specific translators.', 'wpml' )
        . '</span>';
      echo '</span>';
      echo '<svg class="wpml-ai-chevron" width="16" height="16" fill="none"'
        . ' stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"'
        . ' style="color:#9ca3af;flex-shrink:0">'
        . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>'
        . '</svg>';
      echo '</summary>';
      echo '<div style="margin-top:1rem;border-top:1px solid #f3f4f6;padding-top:1rem">';
      AmsWidgetEmbed::renderElement( 'who-can-ta' );
      echo '</div>';
      echo '</details>';

      echo '<div id="automation-preferences" class="wpml-ai-card" style="display:none"'
        . ' data-wpml-ai-automation-card></div>';

      echo '</div>';

      AiTranslationEngineCard::renderModal();

      $this->printSettingsRevealScript();
    } else {
      ClassicEditorActiveCallout::render();
    }
  }


  public function enqueueEngineSwitchScript(): void {
    wp_enqueue_script( 'wpml-ai-engine-switch' );

    $codes = array_map(
      function ( LanguageDto $language ) {
        return $language->getCode();
      },
      $this->languagesQuery->getActive()
    );

    $origin   = EngineSwitchOrigin::fromQuery(
      array(
        EngineSwitchOrigin::PARAM_SOURCE  => filter_input( INPUT_GET, EngineSwitchOrigin::PARAM_SOURCE ),
        EngineSwitchOrigin::PARAM_SURFACE => filter_input( INPUT_GET, EngineSwitchOrigin::PARAM_SURFACE ),
      )
    );
    $tracking = array(
      'fromEngine' => '',
      'siteKey'    => $this->siteKeyQuery->get() ?: '',
      'ateSiteId'  => $this->siteIdQuery->get() ?: '',
      'source'     => $origin['source'],
      'surface'    => $origin['surface'],
      'teaActive'  => $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled(),
    );

    $config = array(
      'languages'       => implode( ',', $codes ),
      'defaultLanguage' => $this->languagesQuery->getDefaultCode(),
      'saveRoute'       => self::SAVE_ROUTE,
      'engineRoute'     => AiTranslationActiveEngineController::ROUTE,
      'tracking'        => $tracking,
      'i18n'            => array(
        'titleToPtc'    => sprintf(
          /* translators: %s: the PTC engine product name. */
          __( 'Switch to %s', 'wpml' ),
          AiTranslationEngineCard::PTC_NAME
        ),
        /* translators: Title of the dialog on WPML → Settings that moves the site off the PTC translation engine. PTC is a product name; keep it. */
        'titleToLegacy' => __( 'Switch away from PTC?', 'wpml' ),
        'saveToPtc'     => __( 'Switch to PTC', 'wpml' ),
        /* translators: Label of the button that confirms moving the site off the PTC translation engine. Verb, imperative. PTC is a product name; keep it. */
        'saveToLegacy'  => __( 'Switch away from PTC', 'wpml' ),
        /* translators: Label of the button that dismisses the dialog and leaves the site on the PTC translation engine. Verb, imperative. PTC is a product name; keep it. */
        'stayWithPtc'   => __( 'Stay with PTC', 'wpml' ),
        /* translators: Subline under the left column of the engine comparison table on WPML → Settings. That column is the engine the site translates with today. */
        'activeEngineSubline' => __( 'Your active engine', 'wpml' ),
        'titleActivatePtc' => sprintf(
          /* translators: Button label on WPML → Settings, and the title of the dialog it opens, for switching the site to a translation engine. Verb, imperative. %s: the name of the translation engine, for example PTC. */
          __( 'Activate %s', 'wpml' ),
          AiTranslationEngineCard::PTC_NAME
        ),
        /* translators: Button label on WPML → Settings that switches the site to the PTC translation engine. Verb, imperative. PTC is a product name and stays as it is. */
        'saveActivatePtc'  => __( 'Activate PTC', 'wpml' ),
        /* translators: Status shown next to the Save button on WPML → Settings while the settings are being stored. */
        'saving'        => __( 'Saving…', 'wpml' ),
        /* translators: Status shown next to the Save button on WPML → Settings once the settings have been stored. Past participle (they have been saved), not an instruction. */
        'saved'         => __( 'Saved', 'wpml' ),
        'errorLoad'     => __( "Couldn't load the translation engine settings — check your connection.", 'wpml' ),
        'errorSave'     => __( "Couldn't save — try again", 'wpml' ),
        'contextRequired' => __( 'Fill in your site context above before switching to PTC.', 'wpml' ),
        /* translators: Label of the button in the engine-switch dialog when the site's context is still missing. It takes the reader to the context fields. Verb, imperative. */
        'contextRequiredAction' => __( 'Fill in the context', 'wpml' ),
      ),
    );

    $encoded = wp_json_encode( $config );
    if ( $encoded === false ) {
      return;
    }

    wp_add_inline_script(
      'wpml-ai-engine-switch',
      'window.wpmlAiEngineSwitch = ' . $encoded . ';',
      'before'
    );
  }


  private static function printSaveStatusCssOnce(): void {
    static $emitted = false;
    if ( $emitted ) {
      return;
    }
    $emitted = true;
    ?>
    <style>
      .wpml-ai-save-status{display:inline-block;margin-left:8px;font-size:12px;
        opacity:0;transition:opacity .15s ease;vertical-align:middle}
      .wpml-ai-save-status.is-visible{opacity:1}
      .wpml-ai-save-status[data-kind="saving"]{color:#646970}
      .wpml-ai-save-status[data-kind="saved"]{color:#16a34a}
      /* A refusal now carries the server's own sentence (ATE-7564 D4), which is
         a line of prose rather than three words: give it a measure to wrap in
         and read it left to right, in a paragraph that is otherwise right
         aligned. */
      .wpml-ai-save-status[data-kind="error"]{color:#b91c1c;max-width:520px;
        text-align:left}
    </style>
    <?php
  }


  private function renderDraftsCheckbox(): void {
    self::printSaveStatusCssOnce();
    $translateDrafts = $this->settingsRepository->shouldTranslateAutomaticallyDrafts();
    ?>
    <p id="translate-everything-drafts" class="wpml-settings-translate-everything-drafts" style="margin-top:1rem">
      <label for="translate-everything-drafts-checkbox">
        <input type="checkbox" id="translate-everything-drafts-checkbox"
          class="wpml-checkbox-native"
          <?php checked( $translateDrafts ); ?> />
        <?php echo esc_html__( 'Translate drafts automatically', 'wpml' ); ?>
      </label>
      <span id="wpml-ai-drafts-status" class="wpml-ai-save-status" aria-live="polite"></span>
      <span class="description" style="display:block;color:#646970">
        <?php
        echo \wpml_bold_names(
          sprintf(
            /* translators: Explanation on WPML → Settings. "This" is translating drafts as they are saved. %1$s: opening bold tag, %2$s: closing bold tag. Keep the <b> tags around the setting name. */
            __( 'WPML translates each draft %1$swhen you save it%2$s. This requires <b>Translate Everything Automatically</b> to be on.', 'wpml' ),
            '<strong>',
            '</strong>'
          ),
          [ 'strong' => [] ]
        );
        ?>
      </span>
      <span class="description" style="display:block;color:#646970;margin-top:.5rem">
        <?php
        echo \wpml_bold_names(
          sprintf(
            /* translators: Message on WPML → Settings. %1$s: opening bold tag, %2$s: closing bold tag. Keep the <b> tags around the screen name. */
            __( 'Drafts saved before you enabled this option %1$sare not automatically translated%2$s. To translate them, save them again or manually send them for translation from the <b>Translation Dashboard</b>.', 'wpml' ),
            '<strong>',
            '</strong>'
          ),
          [ 'strong' => [] ]
        );
        ?>
      </span>
    </p>
    <?php
  }


  public static function reviewModeOptions(): array {
    return array(
      'no-review'          => array(
        'label'       => __( 'Publish without review', 'wpml' ),
        'description' => __( 'Translations go live immediately in the target language.', 'wpml' ),
        'recommended' => array(
          'ptc'   => array(
            /* translators: Label on the option WPML advises you to pick on WPML → Settings. Past participle used as a label (this is what we advise), not a verb. */
            'label' => __( 'Recommended', 'wpml' ),
            'tip'   => __( 'PTC provides Better Than Human Translations that rarely need improvements, so you can confidently skip the review process.', 'wpml' ),
          ),
          'none'  => array(
            'label' => __( 'Recommended with PTC', 'wpml' ),
            'tip'   => __( 'PTC provides Better Than Human Translations that rarely need improvements, so you can confidently skip the review process. For other translation engines, a human review is recommended to confirm accuracy.', 'wpml' ),
          ),
        ),
      ),
      'before-publish'     => array(
        'label'       => __( 'Wait for review before publishing', 'wpml' ),
        'description' => __( 'Translations stay as drafts until a translation manager approves them.', 'wpml' ),
        'recommended' => array(
          'other' => array(
            /* translators: Label on the option WPML advises you to pick on WPML → Settings. Past participle used as a label (this is what we advise), not a verb. */
            'label' => __( 'Recommended', 'wpml' ),
            'tip'   => __( 'For DeepL, Google, and Microsoft we recommend a human review to confirm they are correct. PTC provides Better Than Human Translations that understand context, so you can confidently skip the review process.', 'wpml' ),
          ),
        ),
      ),
      'publish-and-review' => array(
        'label'       => __( 'Publish and mark for review', 'wpml' ),
        'description' => __( "Translations go live but appear in a \"needs review\" queue.", 'wpml' ),
        'recommended' => array(),
      ),
    );
  }


  private function renderReviewModeRadios(): void {
    self::printSaveStatusCssOnce();
    self::printRecommendedBadgeCssOnce();
    $reviewMode    = $this->settingsRepository->getSettings()->getReviewMode();
    $currentReview = $reviewMode ? $reviewMode->getValue() : 'no-review';
    $options       = self::reviewModeOptions();
    ?>
    <h2 style="font-size:13px;font-weight:600;color:#111827;margin:0 0 4px 0">
      <?php echo esc_html__( 'After auto-translation', 'wpml' ); ?>
      <span id="wpml-ai-review-status" class="wpml-ai-save-status" aria-live="polite"></span>
    </h2>
    <p style="font-size:11px;color:#6b7280;margin:0 0 12px 0">
      <?php echo esc_html__( 'What happens to content once the AI has translated it.', 'wpml' ); ?>
    </p>
    <div class="wpml-ai-review-radios">
      <?php foreach ( $options as $value => $opt ) : ?>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;cursor:pointer">
          <input type="radio" name="wpml-ai-review-mode" value="<?php echo esc_attr( $value ); ?>"
            <?php checked( $currentReview, $value ); ?>
            class="wpml-radio-native"
            style="margin-top:3px;flex-shrink:0"/>
          <span>
            <span class="wpml-ai-review-mode-label">
              <span style="color:#1f2937"><?php echo esc_html( $opt['label'] ); ?></span>
              <?php foreach ( $opt['recommended'] as $variant => $rec ) : ?>
                <?php
                $classes = 'wpml-blue-badge with-icon wpml-ai-recommended'
                  . ' wpml-ai-recommended--' . $variant;
                ?>
                <span class="<?php echo esc_attr( $classes ); ?>" tabindex="0">
                  <?php echo esc_html( $rec['label'] ); ?>
                  <span class="wpml-ai-recommended-icon" aria-hidden="true">&#xF642;</span>
                  <span class="wpml-ai-recommended-tip" role="tooltip">
                    <?php echo esc_html( $rec['tip'] ); ?>
                  </span>
                </span>
              <?php endforeach; ?>
            </span>
            <span style="display:block;font-size:11px;color:#6b7280">
              <?php echo esc_html( $opt['description'] ); ?>
            </span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php
  }


  /**
   * Emit the `.wpml-ai-recommended` CSS once per page. Mirrors the
   * idempotent guard pattern used by `printSaveStatusCssOnce()`.
   *
   * The badge appearance — light-blue pill, `--wpml-color-blue` text,
   * `3px 12px` padding, `100px` radius, `13px / 1.5` typography, the
   * `inline-flex / 8px gap / otgs-icons ::after` chrome — comes from
   * the global `.wpml-blue-badge.with-icon` rules already loaded on
   * this page (defined in the Dashboard's compiled `app.scss` and in
   * `SharedKernel/PublicSrc/Style/_variables.scss` so the styles ship
   * with any IA page that enqueues the OTGS shared CSS, which Settings
   * does). We only add: (a) the `\f642` info glyph for `::after`
   * (TEA's `%review-option-label-wrapper-styles` placeholder sets the
   * same content; that placeholder doesn't apply outside Dashboard
   * markup so we emit it locally); (b) engine-class visibility
   * gating; (c) the CSS-only hover popover (no react-tooltip
   * dependency; native `title` has a long delay that hurts UX).
   *
   * Engine class is added to `#wpml-ai-settings` (the
   * loading-notice + atomic-reveal wrapper) by `aiTranslationEngineSwitch.ts`
   * from the engine answer — `--engine-ptc` reveals
   * `.wpml-ai-recommended--ptc`, `--engine-other` reveals
   * `.wpml-ai-recommended--other`, `--engine-none` reveals
   * `.wpml-ai-recommended--none`.
   */
  private static function printRecommendedBadgeCssOnce(): void {
    static $emitted = false;
    if ( $emitted ) {
      return;
    }
    $emitted = true;
    ?>
    <style>
      .wpml-ai-review-mode-label{display:flex;flex-wrap:wrap;
        align-items:center;gap:8px}
      #wpml-ai-settings .wpml-ai-recommended{display:none;
        position:relative;cursor:help;vertical-align:middle}
      #wpml-ai-settings.wpml-ai-settings--engine-ptc
        .wpml-ai-recommended--ptc,
      #wpml-ai-settings.wpml-ai-settings--engine-other
        .wpml-ai-recommended--other,
      #wpml-ai-settings.wpml-ai-settings--engine-none
        .wpml-ai-recommended--none{display:inline-flex}
      /* TEA's badge uses a `::after` pseudo-element for the info
         glyph. Tailwind v4 preflight (loaded here via tailwind.css)
         tangles the cascade on `::after { content }` enough that an
         override won't take effect cleanly — verified attempts:
         specificity bumps + `!important`, `@layer utilities`,
         setting `--tw-content` on the badge AND on `::after`. So we
         emit the glyph as a real `<span class="wpml-ai-recommended-
         icon">&#xF642;</span>` inside the badge instead. Visually
         identical (the badge is `inline-flex` with `gap:8px` from
         `.with-icon`); avoids the cascade fight. */
      .wpml-ai-recommended-icon{font-family:'otgs-icons';
        font-size:14px;line-height:1;color:inherit}
      .wpml-ai-recommended-tip{display:none;position:absolute;
        top:calc(100% + 6px);left:0;z-index:1000;
        width:320px;padding:8px 12px;
        background:#111827;color:#f9fafb;
        font-size:11px;font-weight:400;line-height:1.45;
        border-radius:4px;white-space:normal;
        box-shadow:0 4px 12px rgba(0,0,0,0.15)}
      .wpml-ai-recommended:hover .wpml-ai-recommended-tip,
      .wpml-ai-recommended:focus .wpml-ai-recommended-tip,
      .wpml-ai-recommended:focus-within .wpml-ai-recommended-tip{
        display:block}
    </style>
    <?php
  }


  private function renderSaveButton(): void {
    self::printSaveStatusCssOnce();
    $route = wp_json_encode( self::SAVE_ROUTE );
    ?>
    <p style="margin-top:1.5rem;text-align:right">
      <span id="wpml-ai-save-status" class="wpml-ai-save-status" aria-live="polite"
        style="margin-right:8px"></span>
      <button type="button" id="wpml-ai-save-btn" class="button-primary wpml-button base-btn">
        <?php echo /* translators: Button label on WPML → Settings. Verb phrase, imperative. */ esc_html__( 'Save settings', 'wpml' ); ?>
      </button>
    </p>
    <script>
    (function(){
      var btn = document.getElementById('wpml-ai-save-btn');
      var st  = document.getElementById('wpml-ai-save-status');
      if (!btn) return;
      var SAVE_ROUTE = <?php echo $route;  ?>;
      var S = <?php
              echo wp_json_encode(
                array(
                  /* translators: Status shown next to the Save button on WPML → Settings while the settings are being stored. */
                  'saving'   => __( 'Saving…', 'wpml' ),
                  /* translators: Status shown next to the Save button on WPML → Settings once the settings have been stored. Past participle (they have been saved), not an instruction. */
                  'saved'    => __( 'Saved', 'wpml' ),
                  /* translators: Status shown next to the Save button on WPML → Settings when something on the page was changed and not yet stored. */
                  'unsaved'  => __( 'Unsaved changes', 'wpml' ),
                  /* translators: Status shown next to the Save button on WPML → Settings when storing the settings failed. Verb, imperative. */
                  'failed'   => __( "Couldn't save. Try again.", 'wpml' ),
                )
              );
              ?>;
      function setStatus(kind, msg, hideAfter) {
        st.setAttribute('data-kind', kind);
        st.textContent = msg;
        st.classList.add('is-visible');
        if (st._t) { clearTimeout(st._t); st._t = null; }
        if (hideAfter) st._t = setTimeout(function(){ st.classList.remove('is-visible'); }, hideAfter);
      }

      // Host contract 2: nothing on this page persists on change, on
      // click or on blur. Every AMS element raises `dirty` and holds
      // its values; WPML's own controls do the same; ONE Save writes
      // them all. So the page has to say out loud that something is
      // waiting to be saved, and refuse to be left silently.
      // F502-S-04: WHICH surfaces are waiting, not merely that something was.
      // The page used to hold one boolean that only ever went up, from an
      // element's `dirty` event: the `dirty {dirty:false}` an element raises
      // after saving itself was ignored, and so was `saved`. The engine-switch
      // modal saves the page's OWN context element (`setEngine()` then
      // `save()` on it), so after a switch the page believed it was dirty for
      // ever — the status line read "Unsaved changes" with nothing left to
      // save, and the guard below held every navigation until the browser
      // prompted. Tracking the surfaces lets one that has saved itself say so.
      var dirtyHosts = [];
      var ownDirty = false;
      // While the page's own Save is in flight, the Save owns the status
      // line: every host answers `saved` (or `dirty {dirty:false}`) as it
      // persists, the one with nothing to write at once, and a "Saved" from
      // that answer would stand in for the whole Save while another host is
      // still refusing (e2e wave S2, R2-F1: "saved" then "error", never
      // "saving"). The click handler alone says how the Save ended.
      var saveInFlight = false;

      function indexOfHost(host) {
        for (var i = 0; i < dirtyHosts.length; i++) {
          if (dirtyHosts[i] === host) return i;
        }
        return -1;
      }
      function isDirty() {
        return ownDirty || dirtyHosts.length > 0;
      }
      function markDirty() {
        ownDirty = true;
        setStatus('unsaved', S.unsaved, 0);
      }
      function markHostDirty(host) {
        if (indexOfHost(host) === -1) dirtyHosts.push(host);
        setStatus('unsaved', S.unsaved, 0);
      }
      function markHostClean(host) {
        var at = indexOfHost(host);
        if (at === -1) return;
        dirtyHosts.splice(at, 1);
        // Only the page's own controls can still be waiting; if nothing is,
        // the page says so rather than leaving "Unsaved changes" standing.
        if (!saveInFlight && !isDirty()) setStatus('saved', S.saved, 1800);
      }
      function markClean() {
        ownDirty = false;
        dirtyHosts = [];
      }
      window.addEventListener('beforeunload', function (e) {
        if (!isDirty()) return undefined;
        e.preventDefault();
        // Every browser shows its own wording here; the string is
        // required for the prompt to appear at all.
        e.returnValue = '';
        return '';
      });

      // The settings surfaces of the PAGE. The hidden engine probe is a
      // read-out, not a surface, and the engine-switch modal hosts its
      // own element with its own Save — neither joins this one.
      function hosts() {
        var all = document.querySelectorAll('#wpml-ai-settings wpml-settings');
        var out = [];
        for (var i = 0; i < all.length; i++) {
          if (all[i].closest('#wpml-ai-engine-probe')) continue;
          out.push(all[i]);
        }
        return out;
      }

      function isPageSurface(target) {
        if (!target || !target.closest) return false;
        if (!target.closest('#wpml-ai-settings')) return false;
        return !target.closest('#wpml-ai-engine-probe');
      }

      // `dirty` is LEVEL-triggered (host contract 2 §2): the element re-emits
      // it whenever the value changes, so `false` is the element telling the
      // page it is holding nothing — after its own save, or after the client
      // undid their edit. Both directions are honoured.
      document.addEventListener('dirty', function (event) {
        if (!isPageSurface(event.target)) return;
        var detail = event.detail || {};
        if (detail.dirty) {
          markHostDirty(event.target);
        } else {
          markHostClean(event.target);
        }
      }, true);

      // ...and `saved` is the same statement made the other way round: that
      // scope has persisted, whoever asked it to — this page's Save, or the
      // engine-switch modal saving the page's context element.
      document.addEventListener('saved', function (event) {
        if (!isPageSurface(event.target)) return;
        if (indexOfHost(event.target) === -1) {
          // A surface saved by another hand (the engine-switch dialog
          // saves the page's context element itself) that the page never
          // heard dirty: the save still happened, and the line says so
          // unless something of the page's own is still waiting, or the
          // page's own Save is what asked and will say how it ended.
          if (!saveInFlight && !isDirty()) setStatus('saved', S.saved, 1800);
          return;
        }
        markHostClean(event.target);
      }, true);

      // ATE-7564 D4: a host that refuses a value says WHY. AMS sends the
      // reason with the refusal ("We can't email this address — our messages
      // to it bounced or were reported as spam. Use another address, or
      // resume it from your WPML account page.") and raises `save-error` with
      // it; the wizard's own path prints that sentence. This page dropped it
      // and printed "Couldn't save. Try again." instead — advice that fails
      // every time, on a refusal trying again can never lift.
      //
      // The message is held per element, because each one refuses for its own
      // reason, and it is read by the Save that asked for it: the click below
      // forgets what an earlier Save was told, so a stale sentence is never
      // printed over a later failure.
      var errorHosts = [];
      var errorMessages = [];
      function clearSaveErrors() {
        errorHosts = [];
        errorMessages = [];
      }
      function rememberSaveError(host, message) {
        for (var i = 0; i < errorHosts.length; i++) {
          if (errorHosts[i] === host) { errorMessages[i] = message; return; }
        }
        errorHosts.push(host);
        errorMessages.push(message);
      }
      function firstSaveError() {
        for (var i = 0; i < errorMessages.length; i++) {
          if (errorMessages[i]) return errorMessages[i];
        }
        return '';
      }
      document.addEventListener('save-error', function (event) {
        if (!isPageSurface(event.target)) return;
        var detail = event.detail || {};
        rememberSaveError(event.target, detail.message || '');
      }, true);

      // WPML's own controls join the same Save: they mark the page
      // dirty and their values ride the Save body below, instead of
      // each writing itself on change. Both render AFTER this script
      // (the drafts checkbox in the "Automatic translation behavior"
      // card, the review radios in "After auto-translation"), so they
      // are found when their event fires, never captured while the
      // page is still being parsed: a parse-time lookup found nothing,
      // the drafts value never reached the Save body and neither
      // control marked the page dirty (e2e wave S2, F1).
      function draftsBox() {
        return document.getElementById('translate-everything-drafts-checkbox');
      }
      document.addEventListener('change', function (event) {
        var t = event.target;
        if (!t) return;
        if (t.id === 'translate-everything-drafts-checkbox' || t.name === 'wpml-ai-review-mode') {
          markDirty();
        }
      }, true);

      // The store's effective engine mode, read from the hidden probe.
      // Same selector contract as the modal's `readActiveEngineMode()`
      // (AiTranslationEngineSwitch/helpers.ts FLIP_CONTROL_SELECTORS —
      // keep in sync): each flip control renders only while the store
      // is in the mode it flips AWAY from. null = inconclusive.
      // Host contract 2 renders button-less widgets: the flip controls are
      // gone, the notice CONTAINERS remain, one per store state (the
      // legacy-switch notice only while PTC is selected, the PTC promo only
      // while a legacy engine is). Without them the Save echoed the
      // page-load engine after the page card's "Reset to default" had
      // flipped the store to PTC (e2e bug-wpmldev-7255-page-card on the 5.1.0 line).
      function engineMode() {
        var probe = document.getElementById('wpml-ai-engine-probe');
        if (!probe) return null;
        if (probe.querySelector('.wpml-wpml__LegacyEngineSwitchNoticeLink')) return 'ptc';
        if (probe.querySelector('.AT-btn')) return 'legacy';
        if (probe.querySelector('.wpml-wpml__LegacyEngineSwitchNotice')) return 'ptc';
        if (probe.querySelector('.wpml-wpml__PtcEngineSwitchNotice')) return 'legacy';
        return null;
      }

      // "Automatically translate content when the Translation Editor opens",
      // for the settings-saved event only (AMS stores the setting itself).
      // Read through the data-wpml-setting attribute asked of AMS for its
      // checkbox (ate/ams AtbdSettings.tsx:42-47; until it ships, contract 2
      // reads null), then the id of the contract-1 radio pair; the same order as
      // AUTO_TRANSLATE_ON_OPEN_SELECTORS in AiTranslationEngineSwitch/helpers.ts
      // (keep in sync). Not found: the field stays out of the body and the
      // event records null, never a false the client did not choose
      // (wpmldev-10998: only the radio id was read, and the checkbox AMS
      // renders has none, so every save reported false).
      function autoTranslateWhenEditorOpens() {
        var selectors = [
          '#auto-translate [data-wpml-setting="auto_translate_when_editor_opens"]',
          '#auto-translate #option-translate-atbd_settings'
        ];
        for (var i = 0; i < selectors.length; i++) {
          var el = document.querySelector(selectors[i]);
          if (el && el.tagName === 'INPUT') return !!el.checked;
        }
        return null;
      }

      function saveBody(mode) {
        var ctx = document.getElementById('context') || document;
        var siteTopic    = ctx.querySelector('#about');
        var sitePurpose  = ctx.querySelector('#which');
        var siteAudience = ctx.querySelector('#audience');
        var body = {
          isPtcEngineSelected:          mode !== null ? (mode === 'ptc') : !!window.wpmlAiInitialPtc,
          websiteContext: {
            site_topic:    siteTopic    ? siteTopic.value    : '',
            site_purpose:  sitePurpose  ? sitePurpose.value  : '',
            site_audience: siteAudience ? siteAudience.value : ''
          }
        };
        var autoEditor = autoTranslateWhenEditorOpens();
        if (autoEditor !== null) body.autoTranslateWhenEditorOpens = autoEditor;
        var drafts = draftsBox();
        if (drafts) body.shouldTranslateAutomaticallyDrafts = drafts.checked;
        var picked = document.querySelector('input[name="wpml-ai-review-mode"]:checked');
        if (picked) body.reviewMode = picked.value;
        return body;
      }

      // One element's save: contract 2 answers with a promise of true;
      // a host that has not deployed it keeps today's fire-and-forget
      // `triggerSave`, which can only be assumed to have worked.
      // wpmldev-11148: the fallback is also the one case where the WPML
      // endpoint's engine echo below can race the widget's own write, so the
      // echo check is skipped when any host saved this way.
      var sawUnawaitedSave = false;
      function saveHost(el) {
        if (typeof el.save === 'function') {
          return Promise.resolve(el.save()).then(function (ok) { return ok !== false; });
        }
        sawUnawaitedSave = true;
        el.dispatchEvent(new Event('triggerSave'));
        return Promise.resolve(true);
      }

      // wpmldev-11148 — did the engine write actually take? The AMS bundle's
      // saver settles its save() promise on ANY answered POST, a refusal with
      // a JSON error body included, so a refused switch arrives here as
      // "saved". The endpoint flushes the engine cache and then resolves the
      // active engine, so its answer is the service's truth AFTER the save:
      // when it still names the mode the page was on, the write did not take
      // and the Save says so instead of syncing the page to an engine the
      // site is not on. Narrow on purpose: an echo that names no engine
      // (the read failed soft) is not a refusal. Mirrors
      // saveEchoContradicts() in AiTranslationEngineSwitch/helpers.ts
      // (keep in sync); PTC's engine slug is PtcEngineStatus::PTC_ENGINE_SLUG.
      var PTC_SLUG = <?php echo wp_json_encode( \WPML\Core\Component\ATE\Application\Service\PtcEngineStatus::PTC_ENGINE_SLUG ); ?>;
      function echoContradicts(response, mode) {
        var engine = response && typeof response === 'object' ? response.activeEngine : null;
        if (!engine || typeof engine !== 'object') return false;
        var code = typeof engine.codeName === 'string' ? engine.codeName : '';
        if (!code) return false;
        return (code === PTC_SLUG) !== (mode === 'ptc');
      }

      btn.addEventListener('click', function(){
        setStatus('saving', S.saving, 0);
        saveInFlight = true;
        btn.disabled = true;
        clearSaveErrors();

        var mode = engineMode();
        var engineChanged = mode !== null
          && ((mode === 'ptc') !== !!window.wpmlAiInitialPtc);

        var saves = hosts().map(function (el) {
          return saveHost(el).catch(function () { return false; });
        });

        // ...and WPML's own write, which also flushes the engine-list
        // cache unconditionally and records the analytics event. The answer
        // is kept: it carries the engine the service resolved AFTER the
        // flush — the reading the wpmldev-11148 refusal check below runs on.
        var own = (window.wp && wp.apiFetch)
          ? wp.apiFetch({
              path: SAVE_ROUTE,
              method: 'POST',
              data: saveBody(mode)
            }).then(
              function (response) { return { ok: true, response: response }; },
              function () { return { ok: false }; }
            )
          : Promise.resolve({ ok: true });

        Promise.all([Promise.all(saves), own]).then(function (results) {
          saveInFlight = false;
          btn.disabled = false;
          var everyHostSaved = results[0].every(function (ok) { return ok; });

          if (!everyHostSaved || !results[1].ok) {
            // The server's own sentence when the refusal came with one, and
            // the blanket line only when it did not (ATE-7564 D4).
            setStatus('error', firstSaveError() || S.failed, 0);
            return;
          }

          // wpmldev-11148: an engine CHANGE whose write the service refused
          // (the AMS saver swallows the refusal — see echoContradicts) must
          // not sync the page. Skipped when a host saved fire-and-forget:
          // the echo could then predate the widget's own write.
          if (engineChanged && !sawUnawaitedSave && echoContradicts(results[1].response, mode)) {
            setStatus('error', S.failed, 0);
            return;
          }

          markClean();

          // wpmldev-7255: a save that persisted an engine CHANGE (the
          // store flipped behind the page, via AMS's "Reset to
          // default") syncs the page in place through the modal
          // script's hook — same wrapper class, badges and baseline a
          // modal switch renders. Hook absent ⇒ reload with
          // `?flash=engine`: never leave a stale page.
          if (engineChanged) {
            if (typeof window.wpmlAiApplyEngineSwitch === 'function') {
              window.wpmlAiApplyEngineSwitch(mode);
            } else {
              var u = new URL(window.location.href);
              u.searchParams.set('flash', 'engine');
              window.location.assign(u.toString());
              return;
            }
          }

          setStatus('saved', S.saved, 1800);
        });
      });
    })();
    </script>
    <?php
  }


  public function enqueueSaveScript(): void {
    wp_enqueue_script( 'wp-api-fetch' );
  }


  private function printSettingsRevealScript() {
    $js = '(function(){'
      . "var box=document.getElementById('wpml-ai-settings');if(!box)return;"
      . "var ld=document.getElementById('wpml-ai-loading');"
      . 'var done=false,obs=null,iv=null;'
      . 'function flashDeepLink(){'
      . "var m=window.location.search.match(/[?&]flash=([\\w-]+)/);"
      . 'if(!m)return;'
      . 'var t=document.getElementById(decodeURIComponent(m[1]));'
      . 'if(!t)return;'
      . "if(t.tagName==='DETAILS')t.open=true;"
      . 'var lastH=0,stableSince=Date.now(),startedAt=Date.now();'
      . 'function settle(){'
      . 'var h=document.documentElement.scrollHeight;'
      . 'if(h!==lastH){lastH=h;stableSince=Date.now();}'
      . 'var stableFor=Date.now()-stableSince;'
      . 'var elapsed=Date.now()-startedAt;'
      . 'if(stableFor>=300||elapsed>=3000){'
      . "if(typeof window.wpmlSettingsFlash==='function'){window.wpmlSettingsFlash();return;}"
      . "t.scrollIntoView({behavior:'auto',block:'start'});"
      . "t.classList.add('wpml-flash');"
      . "setTimeout(function(){t.classList.remove('wpml-flash');},1500);"
      . 'return;}'
      . 'setTimeout(settle,100);}'
      . 'settle();}'
      . 'function show(){if(done)return;done=true;'
      . "box.style.display='block';"
      . 'if(ld&&ld.parentNode)ld.parentNode.removeChild(ld);'
      . 'flashDeepLink();}'
      . "var nt=document.getElementById('wpml-ai-settings-reconnecting');"
      . 'function stopWatch(){if(obs)obs.disconnect();if(iv)clearInterval(iv);}'
      . 'function hand(fn){var a=window.wpmlConnectionStatus;if(a){fn(a);}else{(window.__wpmlConnectionPending=window.__wpmlConnectionPending||[]).push(fn);}}'
      . 'function unavailable(){if(!nt)return;'
      . "nt.style.display='block';"
      . 'hand(function(a){a.scriptFailed(nt);});}'
      . 'function recovered(){if(nt&&nt.parentNode){var n=nt;hand(function(a){a.scriptRecovered(n);});nt.parentNode.removeChild(nt);nt=null;}}'
      . 'function onReady(){show();recovered();stopWatch();}'
      . 'function tlen(s){var e=document.querySelector(s);'
      . "return e?(e.textContent||'').trim().length:0;}"
      . 'function ready(){return tlen(\'#context wpml-settings[scope="context"]\')>20'
      . '&&tlen(\'#auto-translate wpml-settings[scope="translation"]\')>20;}'
      . 'if(ready()){onReady();return;}'
      . 'obs=new MutationObserver(function(){if(ready())onReady();});'
      . 'obs.observe(box,{childList:true,subtree:true,characterData:true});'
      . 'iv=setInterval(function(){if(ready())onReady();},300);'
      . "window.addEventListener('error',function(e){var t=e&&e.target;"
      . "if(t&&t.tagName==='SCRIPT'&&t.id==='eate_dashboard-js'){show();unavailable();}},true);"
      . 'setTimeout(function(){if(ready()){onReady();}else{show();unavailable();}},8000);'
      . '})();';
    echo '<script>' . $js . '</script>';
  }


  private static function ateEnabled(): bool {
    return \WPML_TM_ATE_Status::is_enabled();
  }


}
