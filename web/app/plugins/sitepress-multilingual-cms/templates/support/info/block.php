<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_data = isset( $data ) ? $data : null;
?>
<h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></h3>
<table class="widefat wpml-support-info">
	<tbody>
	<?php foreach ( is_array( $wpml_view_data ) ? $wpml_view_data : array() as $wpml_view_item ) : ?>
		<?php $wpml_view_item_status = 'passed'; ?>
		<?php if ( wpml_view_attr( $wpml_view_item, 'is_error' ) ) : ?>
			<?php $wpml_view_item_status = 'error'; ?>
		<?php elseif ( wpml_view_attr( $wpml_view_item, 'is_warning' ) ) : ?>
			<?php $wpml_view_item_status = 'warning'; ?>
		<?php endif; ?>
		<tr>
			<th>
				<?php if ( is_array( $wpml_view_item ) && array_key_exists( 'url', $wpml_view_item ) ) : ?>
					<a href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_item, 'url' ) ); ?>" target="_blank"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_item, 'label' ) ); ?></a>
				<?php else: ?>
					<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_item, 'label' ) ); ?>

				<?php endif; ?>
				<?php if ( is_array( $wpml_view_item ) && array_key_exists( 'icon', $wpml_view_item ) && is_array( $wpml_view_item ) && array_key_exists( 'tooltip', $wpml_view_item ) ) : ?>
					<span class="wpml-tooltip-icon" title="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_item, 'tooltip' ) ); ?>"></span>
				<?php endif; ?>
			</th>
			<td class="wpml-support-info-check-<?php echo wpml_view_esc( $wpml_view_item_status ); ?>">
				<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_item, 'value' ) ); ?>

			</td>
			<?php if ( is_array( $wpml_view_item ) && array_key_exists( 'messages', $wpml_view_item ) && wpml_view_length( wpml_view_attr( $wpml_view_item, 'messages' ) ) > 0 ) : ?>
				<td class="wpml-support-info-message">
					<?php $wpml_view->partial( 'messages.twig', array( 'messages' => wpml_view_attr( $wpml_view_item, 'messages' ), 'item' => $wpml_view_item, 'item_status' => $wpml_view_item_status ) ); ?>
				</td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>