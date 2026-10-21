<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class ClassicEditorActiveCallout {

  const BUTTON_ID   = 'wpml-ai-switch-to-ate';
  const STATUS_ID   = 'wpml-ai-switch-to-ate-status';
  const ENABLE_ROUTE = '/wpml/v1/enable-ate';


  public static function render() {
    $editorSettingsUrl = admin_url( 'admin.php?page=tm/menu/settings&section=translation-editor' );
    ?>
    <div class="wpml-section" id="wpml-ai-classic-editor-callout" role="status">
      <div class="wpml-section-header">
        <h2><?php echo \wpml_bold_names( __( 'The <b>Classic Translation Editor</b> is active.', 'wpml' ) ); ?></h2>
      </div>
      <div class="wpml-section-content">
      <p style="font-size:13px;color:#373737;margin:0 0 10px 0">
        <?php
        echo \wpml_bold_names(
          /* translators: Message on WPML → Settings → AI Translation while the older editor is active; a list of the newer editor's features follows. Keep the <b> tags around the editor name. */
          __( 'AI Translation runs on the <b>Advanced Translation Editor</b>, which also gives you:', 'wpml' )
        );
        ?>
      </p>
      <?php self::renderBenefits(); ?>
      <p style="margin:0 0 8px 0">
        <button type="button" id="<?php echo esc_attr( self::BUTTON_ID ); ?>" class="button-primary wpml-button base-btn">
          <?php
          /* translators: Button label on WPML → Settings → AI Translation that turns the Advanced Translation Editor on. Verb, imperative; the editor name is a product name, keep it. */
          echo esc_html__( 'Switch to Advanced Translation Editor', 'wpml' );
          ?>
        </button>
        <span id="<?php echo esc_attr( self::STATUS_ID ); ?>" class="wpml-ai-save-status" role="status" aria-live="polite"></span>
      </p>
      <p style="font-size:13px;color:#373737;margin:0">
        <?php
        echo wp_kses(
          sprintf(
            /* translators: Line under the Switch Now button on WPML → Settings → AI Translation. %1$s: opening link tag to the Translation Editor settings, %2$s: closing link tag. */
            __( 'You can also change the editor on the %1$sTranslation Editor settings page%2$s.', 'wpml' ),
            '<a href="' . esc_url( $editorSettingsUrl ) . '">',
            '</a>'
          ),
          [ 'a' => [ 'href' => [] ] ]
        );
        ?>
      </p>
      </div>
    </div>
    <?php
  }


  private static function renderBenefits() {
    $benefits = array(
      /* translators: One feature in the list in the admin notice about the Advanced Translation Editor: the store of earlier translations WPML reuses. */
      __( 'Translation Memory', 'wpml' ),
      /* translators: One feature in the list in the admin notice about the Advanced Translation Editor: it keeps the page's HTML markup safe while translating. */
      __( 'HTML Protection', 'wpml' ),
      /* translators: The WPML Glossary: the terms that must always be translated the same way. It names the Glossary tab of WPML → Translations and appears in the list of Advanced Translation Editor features. */
      __( 'Glossary', 'wpml' ),
      /* translators: One feature in the list in the admin notice about the Advanced Translation Editor: it checks the spelling of translations. */
      __( 'Spellchecker', 'wpml' ),
      /* translators: One feature in the list in the admin notice about the Advanced Translation Editor: help from artificial intelligence while translating. */
      __( 'AI Assistant', 'wpml' ),
      /* translators: One feature in the list in the admin notice about the Advanced Translation Editor: translation done by machine rather than by a person. */
      __( 'Automatic Translation', 'wpml' ),
    );

    echo '<ul class="wpml-ai-ate-benefits" style="list-style:none;margin:0 0 12px 0;padding:0">';
    foreach ( $benefits as $benefit ) {
      echo '<li style="display:flex;align-items:center;gap:6px;font-size:13px;color:#373737;margin:0 0 6px 0">';
      echo '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 14 14" aria-hidden="true">'
        . '<path fill="#16a34a" d="M11.7 3.3a1 1 0 0 1 0 1.4l-5.6 5.6a1 1 0 0 1-1.4 0L2.3 7.9a1 1 0 1 1 1.4-1.4l1.7 1.7 4.9-4.9a1 1 0 0 1 1.4 0z"/>'
        . '</svg>';
      echo esc_html( $benefit );
      echo '</li>';
    }
    echo '</ul>';
  }


  public static function enqueueScript() {
    wp_enqueue_script( 'wp-api-fetch' );
    $config = wp_json_encode(
      array(
        'button'    => self::BUTTON_ID,
        'status'    => self::STATUS_ID,
        'route'     => self::ENABLE_ROUTE,
        /* translators: Status next to the Switch Now button on WPML → Settings → AI Translation while the editor is being switched. Keep the ellipsis. */
        'switching' => __( 'Switching…', 'wpml' ),
        /* translators: Status next to the Switch Now button on WPML → Settings → AI Translation when the switch to the Advanced Translation Editor did not work. */
        'error'     => __( "Couldn't switch the editor — try again", 'wpml' ),
      )
    );
    $js = "(function(){"
      . "var C=$config;var f=window.wp&&wp.apiFetch;"
      . "function bind(){var b=document.getElementById(C.button);var s=document.getElementById(C.status);if(!b||!f)return;"
      . "b.addEventListener('click',function(){b.disabled=true;b.setAttribute('aria-busy','true');"
      . "if(s){s.setAttribute('data-kind','saving');s.textContent=C.switching;s.classList.add('is-visible');}"
      . "f({path:C.route,method:'POST'}).then(function(r){if(r&&r.success){window.location.reload();return;}throw new Error('not-enabled');})"
      . ".catch(function(){b.disabled=false;b.removeAttribute('aria-busy');"
      . "if(s){s.setAttribute('data-kind','error');s.textContent=C.error;}});});}"
      . "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',bind);}else{bind();}"
      . "})();";
    wp_add_inline_script( 'wp-api-fetch', $js );
  }


}
