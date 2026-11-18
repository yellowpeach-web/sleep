<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_item = isset( $item ) ? $item : null;
$wpml_view_child = isset( $child ) ? $child : null;
?>
<?php if ( is_array( $wpml_view_item ) || $wpml_view_item instanceof Traversable ) : ?>
	<ol>
		<?php foreach ( $wpml_view_item as $wpml_view_key => $wpml_view_value ) : ?>
			<li><?php echo wpml_view_esc( $wpml_view_key ); ?>: <?php $wpml_view->partial( 'iterable.twig', array( 'item' => $wpml_view_value, 'child' => true ) ); ?></li>
		<?php endforeach; ?>
	</ol>
<?php elseif ( $wpml_view_child ) : ?>
	<span class="child"><?php echo wpml_view_esc( $wpml_view_item ); ?></span>
<?php else: ?>
	<?php echo wpml_view_esc( $wpml_view_item ); ?>

<?php endif; ?>