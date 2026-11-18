<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_entries = isset( $entries ) ? $entries : null;
$wpml_view_columns = isset( $columns ) ? $columns : null;
?>
<div class="wrap">
	<h2 id="xml-config-log"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></h2>
	<?php if ( $wpml_view_entries ) : ?>
		<p>
			<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'message' ) ); ?>

		</p>
		<h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'details' ) ); ?></h3>
		<table class="widefat wpml-xml-config-log">
			<thead>
			<tr>
				<?php foreach ( is_array( $wpml_view_columns ) ? $wpml_view_columns : array() as $wpml_view_column ) : ?>
					<th><?php echo wpml_view_esc( $wpml_view_column ); ?></th>
				<?php endforeach; ?>
			</tr>
			</thead>
			<tfoot>
			<tr>
				<?php foreach ( is_array( $wpml_view_columns ) ? $wpml_view_columns : array() as $wpml_view_column ) : ?>
					<th><?php echo wpml_view_esc( $wpml_view_column ); ?></th>
				<?php endforeach; ?>
			</tr>
			</tfoot>
			<tbody>
			<?php foreach ( is_array( $wpml_view_entries ) ? $wpml_view_entries : array() as $wpml_view_entry ) : ?>
				<tr>
					<?php foreach ( is_array( $wpml_view_entry ) ? $wpml_view_entry : array() as $wpml_view_entry_value ) : ?>
						<td>
							<?php $wpml_view->partial( 'iterable.twig', array( 'item' => $wpml_view_entry_value ) ); ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else: ?>
		<p><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'empty_log' ) ); ?></p>
	<?php endif; ?>
</div>