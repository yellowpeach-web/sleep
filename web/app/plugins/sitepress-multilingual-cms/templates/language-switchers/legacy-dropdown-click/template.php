<?php
$wpml_ls_languages   = isset( $languages ) && is_array( $languages ) ? $languages : array();
$current_language    = isset( $current_language_code, $wpml_ls_languages[ $current_language_code ] ) ? $wpml_ls_languages[ $current_language_code ] : null;
$wpml_ls_names_current = false;
$language            = $current_language;
$wpml_ls_cc          = wpml_ls_core_css_classes( isset( $backward_compatibility ) ? $backward_compatibility : null );
$css_classes_flag    = $wpml_ls_cc['flag'];
$css_classes_native  = $wpml_ls_cc['native'];
$css_classes_display = $wpml_ls_cc['display'];
$css_classes_bracket = $wpml_ls_cc['bracket'];
$submenu_id          = wpml_ls_default( isset( $submenu_id ) ? $submenu_id : null, 'wpml-ls-submenu-click-' . wpml_ls_default( isset( $backward_compatibility['css_id'] ) ? $backward_compatibility['css_id'] : null, 'default' ) );
?>

<div role="navigation" aria-label="<?php echo wpml_ls_esc( isset( $ls_aria_label ) ? $ls_aria_label : null ); ?>" class="<?php echo wpml_ls_esc( isset( $css_classes ) ? $css_classes : null ); ?> wpml-ls-legacy-dropdown-click js-wpml-ls-legacy-dropdown-click"<?php if ( ! empty( $backward_compatibility['css_id'] ) ) : ?> id="<?php echo wpml_ls_esc( $backward_compatibility['css_id'] ); ?>"<?php endif; ?>>
	<ul>

		<li class="<?php echo wpml_ls_esc( isset( $current_language['css_classes'] ) ? $current_language['css_classes'] : null ); ?> wpml-ls-item-legacy-dropdown-click">

			<?php $current_language_name = wpml_ls_default( isset( $current_language['display_name'] ) ? $current_language['display_name'] : null, isset( $current_language['native_name'] ) ? $current_language['native_name'] : null ); ?>
			<?php $toggle_aria_label = $current_language_name ? ( $current_language_name . ' – ' . ( isset( $dropdown_click_ls_aria_label ) ? $dropdown_click_ls_aria_label : '' ) ) : ( isset( $dropdown_click_ls_aria_label ) ? $dropdown_click_ls_aria_label : null ); ?>
			<a href="#" hreflang="<?php echo wpml_ls_esc( isset( $current_language['hreflang'] ) ? $current_language['hreflang'] : null ); ?>" lang="<?php echo wpml_ls_esc( isset( $current_language['hreflang'] ) ? $current_language['hreflang'] : null ); ?>" class="<?php echo wpml_ls_esc( trim( 'js-wpml-ls-item-toggle wpml-ls-item-toggle ' . ( isset( $current_language['backward_compatibility']['css_classes_a'] ) ? $current_language['backward_compatibility']['css_classes_a'] : '' ) ) ); ?>" aria-current="page" aria-expanded="false" aria-controls="<?php echo wpml_ls_esc( $submenu_id ); ?>" aria-haspopup="true" aria-label="<?php echo wpml_ls_esc( $toggle_aria_label ); ?>" tabindex="0" role="button" title="<?php echo wpml_ls_esc( isset( $current_language['menu_item_label'] ) ? $current_language['menu_item_label'] : null ); ?>">
				<?php require dirname( __DIR__ ) . '/flag.php'; ?><?php if ( $current_language_name ) : ?><span class="<?php echo wpml_ls_esc( $css_classes_native ); ?>"><?php echo wpml_ls_esc( $current_language_name ); ?></span><?php endif; ?></a>

			<ul id="<?php echo wpml_ls_esc( $submenu_id ); ?>" class="js-wpml-ls-sub-menu wpml-ls-sub-menu">
				<?php foreach ( $wpml_ls_languages as $language ) : ?><?php if ( ! empty( $language['is_current'] ) ) { continue; } ?>

					<li class="<?php echo wpml_ls_esc( isset( $language['css_classes'] ) ? $language['css_classes'] : null ); ?>">
						<a href="<?php echo wpml_ls_esc( isset( $language['url'] ) ? $language['url'] : null ); ?>" hreflang="<?php echo wpml_ls_esc( isset( $language['hreflang'] ) ? $language['hreflang'] : null ); ?>" lang="<?php echo wpml_ls_esc( isset( $language['hreflang'] ) ? $language['hreflang'] : null ); ?>" class="<?php echo wpml_ls_esc( isset( $css_classes_link ) ? $css_classes_link : null ); ?>" aria-label="<?php echo wpml_ls_esc( isset( $language['menu_item_label'] ) ? $language['menu_item_label'] : null ); ?>" title="<?php echo wpml_ls_esc( isset( $language['menu_item_label'] ) ? $language['menu_item_label'] : null ); ?>">
							<?php require dirname( __DIR__ ) . '/flag-names.php'; ?></a>
					</li>

				<?php endforeach; ?>
			</ul>

		</li>

	</ul>
</div>
