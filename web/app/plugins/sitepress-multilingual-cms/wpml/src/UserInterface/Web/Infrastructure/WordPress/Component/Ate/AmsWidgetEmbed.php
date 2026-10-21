<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate;

use WPML\TM\ATE\ClonedSites\ReconnectNotice;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\Dashboard\ATEDashboardLoader;
use function WPML\Container\make;

class AmsWidgetEmbed {


  public static function enqueue(): void {
    $loader = make( ATEDashboardLoader::class );
    $console = make( 'WPML_TM_AMS_ATE_Console_Section' );
    $params  = $console->get_ams_constructor();

    $encoded = wp_json_encode( $params );
    if ( $encoded === false ) {
      $encoded = '{}';
    }

    $loader->registerScript();
    wp_add_inline_script(
      ATEDashboardLoader::ATE_DASHBOARD_ID,
      self::getConfigSeedScript( $encoded ),
      'before'
    );
    self::initializeScriptWithSeededConfig();
  }


  private static function getConfigSeedScript( string $encoded ): string {
    return <<<JS
(function () {
  window.EateAppWidget = {$encoded};
  window.wpmlAttachAmsWidgetCallbacks = function () {
    var callbacks = window.ate_jobs_sync && window.ate_jobs_sync.ateCallbacks;

    if (!window.EateAppWidget || !callbacks) {
      return;
    }

    if (typeof callbacks.retranslation === 'function') {
      window.EateAppWidget.onGlossaryRetranslationStart = callbacks.retranslation;
    }

    if (typeof callbacks.invalidateCache === 'function') {
      window.EateAppWidget.onLanguageMappingChange = callbacks.invalidateCache;
    }

    if (typeof callbacks.variantDetected === 'function') {
      window.EateAppWidget.onLanguageVariantDetected = callbacks.variantDetected;
    }
  };

  // wpmldev-7217: ATE's Translation Improvement widget detects that a language
  // is really a regional variant (en -> en-gb) and pings the host page. The
  // completion-offer contract (wpmldesign-116; completionOfferEvents.ts) says the
  // host opens the offer by dispatching `wpml:switch-variant:open` on the
  // <wc-language-editor> element - so this callback is only that bridge. The
  // decision itself (what changes, URL handling, locale) is the offer modal's,
  // and a row with a staged Settings change refuses the offer there, so a save
  // the user is already confirming is never double-asked.
  window.ate_jobs_sync = window.ate_jobs_sync || {};
  window.ate_jobs_sync.ateCallbacks = window.ate_jobs_sync.ateCallbacks || {};
  if (typeof window.ate_jobs_sync.ateCallbacks.variantDetected !== 'function') {
    window.ate_jobs_sync.ateCallbacks.variantDetected = function (detection) {
      // Two catalogue codes, nothing derived (wpmldev-7217). `detectedCode` is
      // the pair's authored code; the editor resolves it against the published
      // catalogue. `detectedIso` is the pre-review spelling of the same field
      // and is still accepted so an older widget bundle keeps working.
      var detectedCode = detection && (detection.detectedCode || detection.detectedIso);
      if (!detection || !detection.sourceCode || !detectedCode) {
        return false;
      }
      var host = document.querySelector('wc-language-editor');
      var upgraded = !!(window.customElements && window.customElements.get('wc-language-editor'));
      if (!host || !upgraded) {
        // No editor on this screen, or a host element the editor bundle never
        // defined (bundle absent or failed to load — a dispatch there reaches
        // nothing): nothing may open the offer here. The ping is answered
        // false so ATE can re-offer on a screen that carries one.
        return false;
      }
      host.dispatchEvent(new CustomEvent('wpml:switch-variant:open', {
        // VERBATIM. The canonical form of a catalogue code is decided once, in
        // AMS, where the row is written; re-applying that rule here would be a
        // second spelling of it, free to drift. This bridge only carries the
        // strings across — the editor matches them case-insensitively and then
        // takes the identity from the catalogue row it matched, so what a site
        // ends up storing is always the catalogue's own value, whatever case the
        // caller sent.
        detail: {
          sourceCode: String(detection.sourceCode),
          detectedCode: String(detectedCode)
        }
      }));
      return true;
    };
  }
  window.wpmlAttachAmsWidgetCallbacks();
}());
JS;
  }


  private static function initializeScriptWithSeededConfig(): void {
    $initializerHandle = ATEDashboardLoader::ATE_DASHBOARD_ID . '-init';

    wp_register_script(
      $initializerHandle,
      '',
      [ ATEDashboardLoader::ATE_DASHBOARD_ID ],
      WPML_VERSION,
      true
    );
    wp_enqueue_script( $initializerHandle );

    wp_add_inline_script( $initializerHandle, self::getGuardedBootScript() );
  }


  private static function getGuardedBootScript(): string {
    return <<<JS
(function () {
  var attempts = 0;
  function boot() {
    if (typeof window.ateDashboard !== "function") {
      if (attempts++ < 100) {
        setTimeout(boot, 100);
      }
      return;
    }
    if (typeof window.wpmlAttachAmsWidgetCallbacks === "function") { window.wpmlAttachAmsWidgetCallbacks(); }
    window.ateDashboard(window.EateAppWidget);
    if (typeof window.wpmlAttachAmsWidgetCallbacks === "function") { window.wpmlAttachAmsWidgetCallbacks(); }
  }
  if (document.readyState === "complete") {
    boot();
  } else {
    window.addEventListener("load", boot);
  }
}());
JS;
  }


  public static function renderElement( string $route, array $attributes = array() ): void {
    $extra = '';
    foreach ( $attributes as $name => $value ) {
      if ( ! is_string( $name ) || preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $name ) !== 1 ) {
        continue;
      }
      $extra .= ' ' . $name . '="' . esc_attr( $value ) . '"';
    }
    echo '<wpml-ams-widget route="' . esc_attr( $route ) . '"' . $extra . '></wpml-ams-widget>';
  }


  public static function containerFitCss( string $route ): string {
    if ( preg_match( '/^[a-z][a-z0-9-]*$/', $route ) !== 1 ) {
      return '';
    }

    return 'wpml-ams-widget[route=' . $route . '] .wpml-AmsWidget__Container{box-sizing:border-box}';
  }


  public static function renderContainerFitStyle( string $route ): void {
    $css = self::containerFitCss( $route );
    if ( $css === '' ) {
      return;
    }

    echo '<style>' . esc_html( $css ) . '</style>';
  }


  public static function renderWithLoadingReveal(
    string $containerId,
    string $widgetSelector,
    string $loadingText,
    callable $renderWidget,
    int $threshold = 40,
    bool $showReconnectLine = false
  ): void {
    $loadingId = $containerId . '-loading';
    $noticeId  = $showReconnectLine ? $containerId . '-reconnecting' : null;

    echo '<style>'
      . '#' . esc_attr( $containerId ) . '{display:none}'
      . '.wpml-ai-loading{display:flex;align-items:center;gap:10px;'
      . 'padding:24px 0;color:#50575e;font-size:13px}'
      . '</style>';
    echo '<noscript><style>'
      . '#' . esc_attr( $containerId ) . '{display:block!important}'
      . '#' . esc_attr( $loadingId ) . '{display:none!important}'
      . '</style></noscript>';
    echo '<div id="' . esc_attr( $loadingId ) . '" class="wpml-ai-loading">'
      . '<span class="spinner is-active" style="float:none;margin:0"></span>'
      . esc_html( $loadingText )
      . '</div>';
    if ( $noticeId !== null ) {
      $open = ReconnectState::get() && ReconnectState::isNoAnswer() && ReconnectState::isReconnecting();
      if ( $open ) {
        echo '<style>#' . esc_attr( $loadingId ) . '{display:none}</style>';
        echo '<div id="' . esc_attr( $noticeId ) . '">';
      } else {
        echo '<div id="' . esc_attr( $noticeId ) . '" style="display:none">';
      }
      echo wp_kses( ReconnectNotice::renderInlineFallback(), ReconnectNotice::allowedTags() );
      echo '</div>';
    }
    echo '<div id="' . esc_attr( $containerId ) . '">';
    $renderWidget();
    echo '</div>';

    self::printRevealScript( $containerId, $loadingId, $widgetSelector, $threshold, $noticeId );
  }


  private static function printRevealScript(
    string $containerId,
    string $loadingId,
    string $widgetSelector,
    int $threshold,
    ?string $noticeId = null
  ): void {
    $cid = wp_json_encode( $containerId );
    $lid = wp_json_encode( $loadingId );
    $sel = wp_json_encode( $widgetSelector );
    $nid = $noticeId === null ? 'null' : wp_json_encode( $noticeId );
    $sid = wp_json_encode( ATEDashboardLoader::ATE_DASHBOARD_ID . '-js' );
    if ( $cid === false || $lid === false || $sel === false || $nid === false || $sid === false ) {
      return;
    }
    $js = '(function(){'
      . 'var box=document.getElementById(' . $cid . ');if(!box)return;'
      . 'var ld=document.getElementById(' . $lid . ');'
      . 'var nt=' . $nid . '&&document.getElementById(' . $nid . ');'
      . 'var done=false,obs=null,iv=null;'
      . 'function hand(fn){var a=window.wpmlConnectionStatus;if(a){fn(a);}else{(window.__wpmlConnectionPending=window.__wpmlConnectionPending||[]).push(fn);}}'
      . 'function show(){if(done)return;done=true;'
      . "box.style.display='block';"
      . 'if(ld&&ld.parentNode)ld.parentNode.removeChild(ld);'
      . 'if(nt){hand(function(a){a.scriptRecovered(nt);});if(nt.parentNode)nt.parentNode.removeChild(nt);}'
      . 'if(obs)obs.disconnect();if(iv)clearInterval(iv);}'
      . 'function unavailable(){if(done||!nt)return;'
      . "if(ld)ld.style.display='none';"
      . "nt.style.display='block';"
      . 'hand(function(a){a.scriptFailed(nt);});}'
      . 'function ready(){var e=document.querySelector(' . $sel . ');'
      . "return !!e&&(e.textContent||'').trim().length>" . $threshold . ';}'
      . 'if(ready()){show();return;}'
      . 'obs=new MutationObserver(function(){if(ready())show();});'
      . 'obs.observe(box,{childList:true,subtree:true,characterData:true});'
      . 'iv=setInterval(function(){if(ready())show();},300);'
      . 'if(nt){'
      . "window.addEventListener('error',function(e){"
      . 'var t=e&&e.target;'
      . "if(t&&t.tagName==='SCRIPT'&&t.id===" . $sid . ')unavailable();'
      . '},true);'
      . 'setTimeout(function(){if(ready()){show();}else{unavailable();}},8000);'
      . '}else{'
      . 'setTimeout(show,8000);'
      . '}'
      . '})();';
    echo '<script>' . $js . '</script>';
  }


  public static function currentUserAttributes(): array {
    if ( ! is_user_logged_in() ) {
      return array();
    }

    if ( current_user_can( 'manage_options' ) ) {
      $role = 'admin';
    } elseif ( current_user_can( 'manage_translations' ) ) {
      $role = 'manager';
    } elseif ( current_user_can( 'translate' ) ) {
      $role = 'translator';
    } else {
      $role = '';
    }

    $user = wp_get_current_user();

    return array(
      'userRole'  => $role,
      'userEmail' => $user->exists() ? $user->user_email : '',
    );
  }


  public static function renderSettings( string $scope, string $languages, string $defaultLanguage ): void {
    echo '<wpml-settings'
      . ' languages="' . esc_attr( $languages ) . '"'
      . ' defaultLanguage="' . esc_attr( $defaultLanguage ) . '"'
      . ' scope="' . esc_attr( $scope ) . '"></wpml-settings>';
  }


}
