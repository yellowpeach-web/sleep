<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class TranslationJobsTabBody implements PageRenderInterface {


  public function render() {
    $queueUrl = admin_url( 'admin.php?page=tm/menu/main.php&tab=tasks' );

    $description = __(
      'Track all translation jobs on your site, along with status, translation method, and history.'
      . ' Use this page to monitor progress or cancel jobs.',
      'wpml'
    );

    echo '<p class="wpml-jobs-page-description">';
    echo esc_html( $description );
    echo '</p>';

    echo '<p class="wpml-jobs-page-description">';
    printf(
      /* translators: %s is a link to the Translation Queue. */
      esc_html__( 'Looking for items assigned to you for translation or review? Go to the %s.', 'wpml' ),
      /* translators: Name of the Translation Tasks tab of WPML → Translations, also used as link text pointing at it. */
      '<a href="' . esc_url( $queueUrl ) . '">' . esc_html__( 'Translation Tasks', 'wpml' ) . '</a>'
    );
    echo '</p>';

    echo '<div id="wpml-remote-jobs-container"></div>';
  }


}
