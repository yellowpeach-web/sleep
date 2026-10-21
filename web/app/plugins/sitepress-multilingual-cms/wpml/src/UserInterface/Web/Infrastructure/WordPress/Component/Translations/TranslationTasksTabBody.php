<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class TranslationTasksTabBody implements PageRenderInterface {


  public function render() {
    if ( $this->isEditorOpenRequest() && function_exists( 'wpml_translation_management' ) ) {
      $tm = wpml_translation_management();
      if (
        is_object( $tm )
        && method_exists( $tm, 'render_prepared_translation_queue_editor' )
        && $tm->render_prepared_translation_queue_editor()
      ) {
        return;
      }
    }

    $jobsUrl = admin_url( 'admin.php?page=tm/menu/main.php&tab=jobs' );

    $description = __(
      'Content waiting for your review or translation. Use it to start or continue your work.',
      'wpml'
    );

    echo '<p class="wpml-jobs-page-description translations-queue-description">';
    echo esc_html( $description );
    echo '</p>';

    if ( current_user_can( 'manage_translations' ) ) {
      echo '<p class="wpml-jobs-page-description">';
      printf(
        /* translators: %s is a link to Translation Jobs. */
        esc_html__( 'Need the full list for monitoring or troubleshooting? Go to %s.', 'wpml' ),
        /* translators: Name of the Translation Jobs tab of WPML → Translations, also used as link text pointing at it. */
        '<a href="' . esc_url( $jobsUrl ) . '">' . esc_html__( 'Translation Jobs', 'wpml' ) . '</a>'
      );
      echo '</p>';
    }

    echo '<div class="js-wpml-abort-review-dialog"></div>';
    echo '<div id="wpml-remote-jobs-container"></div>';
  }


  private function isEditorOpenRequest(): bool {
    return $this->hasPositiveIntegerQueryParam( 'job_id' )
      || $this->hasPositiveIntegerQueryParam( 'trid' );
  }


  private function hasPositiveIntegerQueryParam( string $key ): bool {
    if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
      return false;
    }

    return ctype_digit( $_GET[ $key ] ) && (int) $_GET[ $key ] > 0;
  }


}
