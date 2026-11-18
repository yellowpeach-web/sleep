<?php
$wpml_ls_cc          = wpml_ls_core_css_classes( isset( $backward_compatibility ) ? $backward_compatibility : null );
$css_classes_flag    = $wpml_ls_cc['flag'];
$css_classes_native  = $wpml_ls_cc['native'];
$css_classes_display = $wpml_ls_cc['display'];
$css_classes_bracket = $wpml_ls_cc['bracket'];
$css_classes_link    = trim( ( isset( $css_classes_link ) ? $css_classes_link : '' ) . ' ' );
$wpml_ls_names_current = true;
$wpml_ls_languages   = isset( $languages ) && is_array( $languages ) ? $languages : array();
?>

<div role="navigation" aria-label="<?php echo wpml_ls_esc( isset( $ls_aria_label ) ? $ls_aria_label : null ); ?>" class="<?php echo wpml_ls_esc( isset( $css_classes ) ? $css_classes : null ); ?> wpml-ls-legacy-list-vertical"<?php if ( ! empty( $backward_compatibility['css_id'] ) ) : ?> id="<?php echo wpml_ls_esc( $backward_compatibility['css_id'] ); ?>"<?php endif; ?>>
	<ul>

		<?php foreach ( $wpml_ls_languages as $code => $language ) : ?>
			<li class="<?php echo wpml_ls_esc( isset( $language['css_classes'] ) ? $language['css_classes'] : null ); ?> wpml-ls-item-legacy-list-vertical">
				<a href="<?php echo wpml_ls_esc( isset( $language['url'] ) ? $language['url'] : null ); ?>" hreflang="<?php echo wpml_ls_esc( isset( $language['hreflang'] ) ? $language['hreflang'] : null ); ?>" lang="<?php echo wpml_ls_esc( isset( $language['hreflang'] ) ? $language['hreflang'] : null ); ?>" class="<?php echo wpml_ls_esc( $css_classes_link ); ?>"<?php if ( ! empty( $language['is_current'] ) ) : ?> aria-current="page"<?php endif; ?> aria-label="<?php echo wpml_ls_esc( isset( $language['menu_item_label'] ) ? $language['menu_item_label'] : null ); ?>" title="<?php echo wpml_ls_esc( isset( $language['menu_item_label'] ) ? $language['menu_item_label'] : null ); ?>">
					<?php require dirname( __DIR__ ) . '/flag-names.php'; ?></a>
			</li>
		<?php endforeach; ?>

	</ul>
</div>
