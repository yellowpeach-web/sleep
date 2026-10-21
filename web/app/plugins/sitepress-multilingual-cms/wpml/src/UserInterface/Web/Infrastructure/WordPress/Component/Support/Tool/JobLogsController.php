<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\TM\Jobs\Log\EntityTimelineView;
use WPML\TM\Jobs\Log\ViewFactory;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class JobLogsController implements PageRenderInterface {

  const WIDTH_CLASS = 'wpml:max-w-[1400px]';


  public function render() {
    $this->enqueueLegacyAssets();

    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'Translation job logs', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php esc_html_e( "Tracks the lifecycle of each translation job on your site: job created, assigned to a translator, sent to a service, status changes, and completion. Use this when a job doesn't arrive, gets assigned to the wrong person, or finishes but doesn't appear on your site.", 'wpml' ); ?>
      </p>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        printf(
          /* translators: Note above the job log on WPML → Support. %1$s: the button label "Download last 100", in bold, %2$s: the button label "Download all", in bold. */
          esc_html__( 'Logging stays on until you turn it off here — it doesn\'t auto-disable. Turn it on, reproduce the problem once, then share the log with support and turn it back off. Each entry shows the timestamp, the job ID, the action, and any relevant user or status fields. Use %1$s to grab the most recent requests as a file, or %2$s for the complete log.', 'wpml' ),
          '<strong>' . esc_html__( 'Download last 100', 'wpml' ) . '</strong>',
          /* translators: Button label on WPML → Support that saves the whole log as a file, also quoted in the note above the buttons. Verb phrase, imperative. */
          '<strong>' . esc_html__( 'Download all', 'wpml' ) . '</strong>'
        );
        ?>
      </p>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        printf(
          /* translators: Note on WPML → Support. %1$s: opening link tag to the WPML support forum, %2$s: closing link tag. "Download all" is a button label on the same screen. */
          esc_html__( 'In order to get help, please %1$sopen a ticket in our Forum Thread (Maiya)%2$s. Enable logging, reproduce the issue, then click "Download all" to get the log file and attach it to your support ticket.', 'wpml' ),
          '<a href="' . esc_url( \WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin::map( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL ) ) . '" target="_blank" rel="noopener noreferrer">',
          '</a>'
        );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5">
        <?php
        if ( $tab === 'by-post' ) {
          $this->renderByPostView();
        } else {
          $this->renderLegacyView();
        }
        ?>
      </div>
      <?php
  }


  private function enqueueLegacyAssets(): void {
    if ( ! defined( 'WPML_TM_VERSION' ) || ! defined( 'WPML_TM_URL' ) || ! defined( 'ICL_SITEPRESS_SCRIPT_VERSION' ) ) {
      return;
    }
    wp_enqueue_style(
      'wpml-tm-job-log',
      WPML_TM_URL . '/res/css/job-log.css',
      array(),
      ICL_SITEPRESS_SCRIPT_VERSION
    );
    wp_enqueue_script(
      'support-tm-logs',
      WPML_TM_URL . '/res/js/support-tm-logs.js',
      array( 'jquery' ),
      ICL_SITEPRESS_SCRIPT_VERSION,
      true
    );
    wp_localize_script(
      'support-tm-logs',
      'wpmlTmJobLog',
      array(
        'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
        'nonce'              => wp_create_nonce( 'wpml_tm_job_log' ),
        'confirmClearLogs'   => __( 'Are you sure you want to clear all job logs?', 'wpml' ),
        'logsClearedSuccess' => __( 'Logs cleared successfully', 'wpml' ),
        'logsClearedFailed'  => __( 'Failed to clear logs', 'wpml' ),
        'logsClearedError'   => __( 'Error clearing logs', 'wpml' ),
      )
    );
  }


  private function renderLegacyView(): void {
    if ( ! class_exists( ViewFactory::class ) ) {
      echo '<p class="wpml:text-xs wpml:text-gray-500 wpml:italic">' . esc_html__( 'Translation job logs are not available in this WPML build.', 'wpml' ) . '</p>';
      return;
    }
    $view = ( new ViewFactory() )->create();
    ob_start();
    $view->renderPage();
    $body = (string) ob_get_clean();
    echo $body;
  }


  private function renderByPostView(): void {
    if ( ! class_exists( EntityTimelineView::class ) ) {
      echo '<p class="wpml:text-xs wpml:text-gray-500 wpml:italic">' . esc_html__( 'Translation job logs are not available in this WPML build.', 'wpml' ) . '</p>';
      return;
    }
    ob_start();
    ( new EntityTimelineView() )->renderPage();
    $body = (string) ob_get_clean();
    echo $body;
  }


}
