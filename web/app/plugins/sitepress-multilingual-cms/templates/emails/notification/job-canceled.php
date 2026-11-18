<?php
defined( 'ABSPATH' ) || exit;
?>
<p><?php echo wpml_view_raw( isset( $message ) ? $message : null ); ?></p>
<p>
    <a href="<?php echo wpml_view_esc( isset( $translation_jobs_url ) ? $translation_jobs_url : null ); ?>"><?php echo wpml_view_esc( isset( $view_jobs_text ) ? $view_jobs_text : null ); ?></a>
</p>