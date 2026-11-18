<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
?>
<p><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></p>
<?php foreach ( isset( $lang_pairs ) && is_array( $lang_pairs ) ? $lang_pairs : array() as $lang_pair ) : ?>
	<p><?php echo wpml_view_esc( wpml_view_attr( $lang_pair, 'title' ) ); ?></p>
    <ul style="margin-top: -20px">
		<?php
		$wpml_view_elements = wpml_view_attr( $lang_pair, 'elements' );
		foreach ( is_array( $wpml_view_elements ) ? $wpml_view_elements : array() as $element ) :
			?>
			<?php if ( 'post' === wpml_view_attr( $element, 'type' ) ) : ?>
				<li><a href="<?php echo wpml_view_esc( wpml_view_attr( $element, 'original_link' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $element, 'original_text' ) ); ?></a>: <a href="<?php echo wpml_view_esc( wpml_view_attr( $element, 'start_translating_link' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'start_translating_text' ) ); ?></a></li>
			<?php else : ?>
				<li><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'strings_text' ) ); ?>: <a href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'strings_link' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'take' ) ); ?></a></li>
			<?php endif; ?>
		<?php endforeach; ?>
	</ul>
<?php endforeach; ?>

<?php if ( wpml_view_attr( $wpml_view_strings, 'closing_sentence' ) ) : ?>
	<p><?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'closing_sentence' ) ); ?></p>
<?php endif; ?>