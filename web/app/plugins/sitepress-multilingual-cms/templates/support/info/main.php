<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_title = isset( $title ) ? $title : null;
$wpml_view_blocks = isset( $blocks ) ? $blocks : null;
?>
<div class="wrap">
	<h2><?php echo wpml_view_esc( $wpml_view_title ); ?></h2>

	<?php foreach ( is_array( $wpml_view_blocks ) ? $wpml_view_blocks : array() as $wpml_view_block ) : ?>
		<?php $wpml_view->partial( 'block.twig', is_array( $wpml_view_block ) ? $wpml_view_block : array() ); ?>
	<?php endforeach; ?>
</div>