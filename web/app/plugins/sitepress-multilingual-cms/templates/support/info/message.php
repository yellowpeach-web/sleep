<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_message = isset( $message ) ? $message : null;
$wpml_view_more_url = isset( $more_url ) ? $more_url : null;
?>
<?php if ( $wpml_view_more_url && $wpml_view_message != '' ) : ?>
	<a class="wpml-external-link" href="<?php echo wpml_view_esc( $wpml_view_more_url ); ?>" target="_blank">
		<span><?php echo wpml_view_esc( $wpml_view_message ); ?></span>
	</a>
<?php else: ?>
	<?php echo wpml_view_esc( $wpml_view_message ? $wpml_view_message : $wpml_view_more_url ); ?>

<?php endif; ?>
