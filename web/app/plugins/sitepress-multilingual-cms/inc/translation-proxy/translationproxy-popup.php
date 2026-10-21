<?php

define( 'ICL_LANGUAGE_NOT_SUPPORTED', 3 );
global $wpdb, $sitepress;

$target         = filter_input( INPUT_GET, 'target', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
$auto_resize    = filter_input( INPUT_GET, 'auto_resize', FILTER_VALIDATE_BOOLEAN | FILTER_NULL_ON_FAILURE  );
$unload_cb      = filter_input( INPUT_GET, 'unload_cb', FILTER_SANITIZE_FULL_SPECIAL_CHARS | FILTER_NULL_ON_FAILURE  );

if ( preg_match( '|^@select-translators;([^;]+);([^;]+)@|', $target, $matches ) ) {
	$source_language = $matches[1];
	$target_language = $matches[2];
	$project         = TranslationProxy::get_current_project();
	try {
		$lp_setting_index = 'language_pairs';
		$language_pairs   = $sitepress->get_setting( $lp_setting_index, array() );
		if ( ! isset( $language_pairs[ $source_language ][ $target_language ] ) || 0 === $language_pairs[ $source_language ][ $target_language ] ) {
			$language_pairs[ $source_language ][ $target_language ] = 1;
			TranslationProxy_Translator::update_language_pairs( $project, $language_pairs );
			$sitepress->set_setting( $lp_setting_index, $language_pairs, true );
		}
		$target = $project->select_translator_iframe_url( $source_language, $target_language );
	} catch ( Exception $e ) {
		if ( ICL_LANGUAGE_NOT_SUPPORTED === $e->getCode() ) {
			echo wp_kses_post(
				sprintf(
					/* translators: Message shown when the translation service does not handle the languages of the site. %1$s: the reason the service gave, %2$s: the address of the WPML support pages, filling the link tag that is already in the text. */
					__( '<p>Requested languages are not supported by the translation service (%1$s). Please <a target="_blank" href="%2$s">contact us</a> for support. </p>', 'sitepress' ),
					esc_html( $e->getMessage() ),
					esc_url( \WPML\OutboundLinks\OutboundLinks::to( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL, array( 'medium' => 'notice', 'campaign' => 'support' ) ) )
				)
			);
		} else {
			echo wp_kses_post(
				sprintf(
					/* translators: Message shown when languages could not be added at the translation service. %1$s: the address of the WPML support pages, %2$s: the address of the debug information screen; both fill link tags that are already in the text. */
					__( '<p>Could not add the requested languages. Please <a target="_blank" href="%1$s">contact us</a> for support. </p><p>Show <a href="%2$s">debug information</a>.</p>', 'sitepress' ),
					esc_url( \WPML\OutboundLinks\OutboundLinks::to( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL, array( 'medium' => 'notice', 'campaign' => 'support' ) ) ),
					esc_url( admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/support.php&tool=system-check' ) )
				)
			);
		}
		exit;
	}
}

$target .= ( strpos( $target, '?' ) === false ) ? '?' : '&';
$target .= "lc=" . $sitepress->get_admin_language();
?>

<iframe src="<?php echo esc_url( $target ); ?>" style="width:100%; height:92%" onload="    var TB_window = jQuery('#TB_window');
<?php if ( $auto_resize ): ?>
	TB_window.css('width','90%').css('margin-left', '-45%');
<?php endif; ?>
<?php
if ( $unload_cb ) {
	?>
	TB_window.unbind('unload').bind('tb_unload', function(){<?php echo esc_js( $unload_cb ); ?>});
<?php } ?>
	">
