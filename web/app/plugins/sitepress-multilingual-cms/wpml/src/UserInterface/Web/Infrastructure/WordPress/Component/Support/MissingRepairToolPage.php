<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support;

use WPML\Core\SharedKernel\Component\Support\Application\Repository\RepairToolsPluginInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class MissingRepairToolPage implements PageRenderInterface {

  private $slug;

  private $plugin;


  public function __construct( string $slug, RepairToolsPluginInterface $plugin ) {
    $this->slug   = $slug;
    $this->plugin = $plugin;
  }


  public static function tools(): array {
    return [
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'ate-sync'                       => __( 'Synchronize with the Advanced Translation Editor', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'db-strings'                     => __( 'Database & strings maintenance', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'packages'                       => __( 'Package management', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'fix-language-data'              => __( 'Fix language data', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'fix-translations'               => __( 'Fix translations', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'internal-links'                 => __( 'Update internal links', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. WCML is a plugin name and stays as it is. */
      'wcml-fix-translations'          => __( 'Fix WCML translations', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. */
      'reset'                          => __( 'Reset WPML', 'wpml' ),
      /* translators: Heading of the WPML → Support tool page of that name, and the tool's name in the notice on it. WCML is a plugin name and stays as it is. */
      'wcml-translation-maintenance'   => __( 'WCML translation maintenance', 'wpml' ),
    ];
  }


  public static function knows( string $slug ): bool {
    return array_key_exists( $slug, self::tools() );
  }


  public function render() {
    $tools = self::tools();
    $title = $tools[ $this->slug ] ?? $this->slug;

    $canActivateInPlace = $this->plugin->isInstalled()
      && ! $this->plugin->isActive()
      && current_user_can( 'activate_plugins' );

    echo '<h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-5">' . esc_html( $title ) . '</h1>';

    echo '<div class="wpml-support-missing-plugin wpml:bg-amber-50 wpml:border wpml:border-amber-200 wpml:rounded-md wpml:p-5">';
    echo '<p class="wpml:text-sm wpml:text-gray-900" style="margin:0 0 1em 0">';
    if ( $this->plugin->isInstalled() ) {
      printf(
        /* translators: Notice on a WPML → Support tool page whose plugin is installed but inactive. %s: the tool's name in bold markup, e.g. "Package management". WPML Troubleshooting is a plugin name. */
        esc_html__( '%s is part of WPML Troubleshooting, a separate plugin that is installed but not active on this site.', 'wpml' ),
        '<strong>' . esc_html( $title ) . '</strong>'
      );
    } else {
      printf(
        /* translators: Notice on a WPML → Support tool page whose plugin is not installed. %s: the tool's name in bold markup, e.g. "Package management". WPML Troubleshooting is a plugin name. */
        esc_html__( '%s is part of WPML Troubleshooting, a separate plugin that is not installed on this site.', 'wpml' ),
        '<strong>' . esc_html( $title ) . '</strong>'
      );
    }
    echo '</p>';
    echo '<p id="wpml-support-activate-repair-tools-status" class="wpml:text-sm wpml:text-red-700" style="margin:0 0 1em 0; display:none"></p>';
    if ( $canActivateInPlace ) {
      echo '<button type="button" id="wpml-support-activate-repair-tools" class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:bg-blue wpml:hover:bg-blue-700 wpml:text-white wpml:text-sm wpml:font-medium wpml:px-4 wpml:py-1.5 wpml:rounded wpml:transition-colors wpml:cursor-pointer"'
        . ' data-rest-url="' . esc_attr( esc_url_raw( rest_url( 'wpml/v1/support/activate-repair-tools' ) ) ) . '"'
        . ' data-rest-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';
      /* translators: Button label on a WPML → Support tool page: verb, imperative (activate the installed WPML Troubleshooting plugin in place). WPML Troubleshooting is a plugin name. */
      echo esc_html__( 'Activate WPML Troubleshooting', 'wpml' );
      echo '</button>';
      $this->renderActivateScript();
    } elseif ( ! $this->plugin->isInstalled() ) {
      echo '<a href="' . esc_url( admin_url( 'admin.php?page=wpml-activate-update' ) ) . '" class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:bg-blue wpml:hover:bg-blue-700 wpml:text-white wpml:text-sm wpml:font-medium wpml:px-4 wpml:py-1.5 wpml:rounded wpml:transition-colors">';
      /* translators: Link or button text on WPML → Support and its tool pages that opens the WPML → Activate & Update screen. Verb phrase, imperative. "Activate & Update" is that screen's name. */
      echo esc_html__( 'Go to Activate & Update', 'wpml' );
      echo '</a>';
    }
    echo '</div>';
  }


  private function renderActivateScript(): void {
    $i18n = [
      /* translators: Button label on a WPML → Support tool page, replacing "Activate WPML Troubleshooting" while the plugin is being activated. */
      'activating' => __( 'Activating…', 'wpml' ),
      /* translators: Error text under the Activate button on a WPML → Support tool page. WPML Troubleshooting is a plugin name; "Activate & Update" is a screen's name. */
      'failed'     => __( 'WPML Troubleshooting could not be activated. Use Activate & Update instead.', 'wpml' ),
    ];
    ?>
    <script>
      (function () {
        var btn = document.getElementById('wpml-support-activate-repair-tools');
        var status = document.getElementById('wpml-support-activate-repair-tools-status');
        var i18n = <?php echo wp_json_encode( $i18n ); ?>;
        if (!btn) { return; }
        var label = btn.textContent;
        btn.addEventListener('click', function () {
          btn.disabled = true;
          btn.textContent = i18n.activating;
          status.style.display = 'none';
          fetch(btn.getAttribute('data-rest-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': btn.getAttribute('data-rest-nonce') },
            body: '{}'
          }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.success) { window.location.reload(); return; }
            throw new Error((json && json.data && json.data.error) || 'failed');
          }).catch(function () {
            btn.disabled = false;
            btn.textContent = label;
            status.textContent = i18n.failed;
            status.style.display = '';
          });
        });
      })();
    </script>
    <?php
  }
}
