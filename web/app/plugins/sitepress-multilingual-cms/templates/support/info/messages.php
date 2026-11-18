<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_item = isset( $item ) ? $item : null;
$wpml_view_item_status = isset( $item_status ) ? $item_status : null;
?>
<?php if ( $wpml_view_item_status != 'passed' && is_array( $wpml_view_item ) && array_key_exists( 'messages', $wpml_view_item ) ) : ?>
	<?php if ( wpml_view_length( wpml_view_attr( $wpml_view_item, 'messages' ) ) > 1 ) : ?>
		<ol>
			<?php foreach ( is_array( wpml_view_attr( $wpml_view_item, 'messages' ) ) ? wpml_view_attr( $wpml_view_item, 'messages' ) : array() as $wpml_view_message => $wpml_view_more_url ) : ?>
				<li>
					<?php $wpml_view->partial( 'message.twig', array( 'message' => $wpml_view_message, 'more_url' => $wpml_view_more_url ) ); ?>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else: ?>
		<?php foreach ( is_array( wpml_view_attr( $wpml_view_item, 'messages' ) ) ? wpml_view_attr( $wpml_view_item, 'messages' ) : array() as $wpml_view_message => $wpml_view_more_url ) : ?>
			<?php $wpml_view->partial( 'message.twig', array( 'message' => $wpml_view_message, 'more_url' => $wpml_view_more_url ) ); ?>
		<?php endforeach; ?>
	<?php endif; ?>
<?php endif; ?>
