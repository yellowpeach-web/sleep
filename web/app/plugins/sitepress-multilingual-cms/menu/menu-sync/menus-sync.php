<?php
global $icl_menus_sync, $sitepress;

$active_languages    = $sitepress->get_active_languages();
$def_lang_code       = $sitepress->get_default_language();
$def_lang            = $sitepress->get_language_details( $def_lang_code );
$secondary_languages = array();

foreach ( $active_languages as $code => $lang ) {
	if ( $code !== $def_lang_code ) {
		$secondary_languages[] = $lang;
	}
}
?>
<!--suppress HtmlFormInputWithoutLabel --><!--suppress HtmlUnknownAttribute -->
<?php   ?>
<div class="wrap wpml-tm-menus-sync">
<h2><?php esc_html_e( 'WP Menus Sync', 'sitepress' ); ?></h2>
<p>
	<?php
	printf(
		/* translators: %s is the default language name. */
		esc_html__( 'Menu synchronization will sync the menu structure from the default language of %s to the secondary languages.', 'sitepress' ),
		esc_html( $def_lang['display_name'] )
	);
	?>
</p>

<br/>

	<?php
	if ( $icl_menus_sync->is_preview ) {
		?>

		<form id="icl_msync_confirm_form" method="post">
		<input type="hidden" name="action" value="icl_msync_confirm"/>

		<table id="icl_msync_confirm" class="widefat icl_msync">
		<thead>
		<tr>
			<th scope="row" class="menu-check-all">
				<input aria-label="<?php esc_html_e( 'Select all items', 'sitepress' ); ?>" class="wpml-checkbox-native" type="checkbox"/>
			</th>
			<th><?php /* translators: Column heading and field label in the WPML admin, for the language of a piece of content. Noun, singular. */ esc_html_e( 'Language', 'sitepress' ); ?></th>
			<th><?php /* translators: Column heading in the menu synchronization table, and the word before the number of a step in the details of one request in the job log, as in "Action 1:". Noun: the thing that will be done. */ esc_html_e( 'Action', 'sitepress' ); ?></th>
		</tr>
		</thead>
		<tbody>

		<?php
		$menu_id = null;
		if ( empty( $icl_menus_sync->sync_data ) ) {
			?>
			<tr>
				<td align="center" colspan="3"><?php esc_html_e( 'Nothing to sync.', 'sitepress' ); ?></td>
			</tr>
			<?php
		} else {
			foreach ( $icl_menus_sync->menus as $menu_id => $menu ) {
				$menu_sync_display = new WPML_Menu_Sync_Display( $menu_id, $icl_menus_sync );
				?>
				<tr class="icl_msync_menu_title">
					<td colspan="3"><?php echo esc_html( $menu['name'] ); ?></td>
				</tr>

				<?php
				if ( isset( $icl_menus_sync->sync_data['menu_translations'], $icl_menus_sync->sync_data['menu_translations'][ $menu_id ] ) ) {
					foreach ( $icl_menus_sync->sync_data['menu_translations'][ $menu_id ] as $language => $name ) {
						$lang_details = $sitepress->get_language_details( $language );
						?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox"
										aria-label="<?php /* translators: Screen reader name of the checkbox that picks one row of the menu synchronization table. */ esc_attr_e( 'Select row', 'sitepress' ); ?>"
										class="wpml-checkbox-native"
										name="sync[menu_translation][<?php echo esc_attr( $menu_id ); ?>][<?php echo esc_attr( $language ); ?>]"
										value="<?php echo esc_attr( $name ); ?>"/>
							</th>
							<td><?php echo esc_html( $lang_details['display_name'] ); ?></td>
							<td><?php printf( /* translators: Row in the menu synchronization table saying a menu will get a translation. %s: the name of the menu, in bold. */ esc_html__( 'Add menu translation:  %s', 'sitepress' ), '<strong>' . esc_html( $name ) . '</strong>' ); ?> </td>
						</tr>
						<?php
					}
				}

				foreach (
					array(
						'add',
						'mov',
						'del',
						'label_changed',
						'url_changed',
						'label_missing',
						'url_missing',
						'options_changed',
					) as $sync_type
				) {
					$menu_sync_display->print_sync_field( $sync_type );
				}
			}
		}
		?>
		</tbody>
		</table>
		<?php
		$icl_menus_sync->render_circular_items_notice();
		?>
		<p class="submit">
			<?php
			$icl_menu_sync_submit_disabled = '';
			if ( empty( $icl_menus_sync->sync_data ) || ( empty( $icl_menus_sync->sync_data['mov'] ) && empty( $icl_menus_sync->sync_data['mov'][ $menu_id ] ) ) ) {
				$icl_menu_sync_submit_disabled = 'disabled="disabled"';
			}
			?>
			<input id="icl_msync_submit"
					class="button-primary wpml-button base-btn"
					type="button"
					value="<?php /* translators: Button label on the menu synchronization screen: carry out the listed changes. Verb, imperative. */ esc_attr_e( 'Apply changes', 'sitepress' ); ?>"
					data-message="<?php esc_attr_e( 'Syncing menus %1 of %2', 'sitepress' ); ?>"
					data-message-complete="<?php esc_attr_e( 'The selected menus have been synchonized.', 'sitepress' ); ?>"
				<?php echo $icl_menu_sync_submit_disabled; ?> />&nbsp;
			<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */ ?>
			<input id="icl_msync_cancel" class="button-secondary wpml-button base-btn wpml-button--outlined" type="button" value="<?php _e( 'Cancel', 'sitepress' ); ?>"/>
			<span id="icl_msync_message"></span>
		</p>
			<?php wp_nonce_field( '_icl_nonce_menu_sync', '_icl_nonce_menu_sync' ); ?>
		</form>
		<?php
	} else {
		$need_sync = 0;
		?>
		<form method="post" action="">
			<input type="hidden" name="action" value="icl_msync_preview"/>
			<?php
			if ( empty( $icl_menus_sync->menus ) ) {
				?>
				<table class="widefat icl_msync">
					<tbody>
					<tr>
						<td align="center" colspan="<?php echo count( $active_languages ); ?>"><?php esc_html_e( 'No menus found', 'sitepress' ); ?></td>
					</tr>
					</tbody>
				</table>
				<?php
			} else {
				foreach ( $icl_menus_sync->menus as $menu_id => $menu ) {
					?>
					<h2 class="wpml-menu-sync__menu-heading"><?php echo esc_html( $menu['name'] ); ?></h2>
					<table class="widefat icl_msync wpml-menu-sync__menu-table">
						<thead>
						<tr>
							<th><?php echo esc_html( $def_lang['display_name'] ); ?></th>
							<?php
							if ( ! empty( $secondary_languages ) ) {
								foreach ( $secondary_languages as $lang ) {
									?>
									<th><?php echo esc_html( $lang['display_name'] ); ?></th>
									<?php
								}
							}
							?>
						</tr>
						</thead>
						<tbody>
						<tr class="icl_msync_menu_title">
							<td><strong><?php echo esc_html( $menu['name'] ); ?></strong></td>
							<?php
							foreach ( $secondary_languages as $l ) {
								$input_name = sprintf( 'sync[menu_options][%s][%s][auto_add]', esc_attr( $menu_id ), esc_attr( $l['code'] ) );
								?>
								<td>
									<?php
									if ( isset( $menu['translations'][ $l['code'] ]['name'] ) ) {
										echo esc_html( $menu['translations'][ $l['code'] ]['name'] );
									} else {
										++$need_sync;
										?>
										<input type="text" class="icl_msync_add"
												<?php /* translators: Screen reader name of a column in the menu synchronization table. %s: the name of the language. */ ?>
												aria-label="<?php printf( esc_attr__( 'Menu translation in %s', 'sitepress' ), $l['display_name'] ); ?>"
												aria-describedby="input_desc_<?php echo esc_attr( $l['code'] ); ?>"
												name="sync[menu_translations][<?php echo esc_attr( $menu_id ); ?>][<?php echo esc_attr( $l['code'] ); ?>]"
												value="<?php echo esc_attr( $menu['name'] ) . ' - ' . esc_attr( $l['display_name'] ); ?>"
										/>
										<small id="input_desc_<?php echo esc_attr( $l['code'] ); ?>">
											<?php esc_html_e( 'Auto-generated title. Click to edit.', 'sitepress' ); ?>
										</small>
										<input type="hidden" value=""
												name="<?php echo $input_name; ?>"
										/>
										<?php
									}
									if ( isset( $menu['translations'][ $l['code'] ]['auto_add'] ) ) {
										?>
										<input type="hidden" name="<?php echo $input_name; ?>" value="<?php echo esc_attr( $menu['translations'][ $l['code'] ]['auto_add'] ); ?>"/>
										<?php
									}
									?>
								</td>
								<?php
							}
							?>
						</tr>
						<?php
						$need_sync += $icl_menus_sync->render_items_tree_default( $menu_id );
						?>
						</tbody>
					</table>
					<?php
				}
			}
			$icl_menus_sync->render_circular_items_notice();
			?>
			<p class="submit">
				<?php
				if ( $need_sync ) {
					?>
					<?php
					?>
					<input id="icl_msync_sync" type="submit" class="button-primary wpml-button base-btn"
							value="<?php esc_attr_e( 'Select items to sync', 'sitepress' ); ?>"
						<?php disabled( ! $need_sync ); ?>
					/>
					&nbsp;&nbsp;
					<span id="icl_msync_max_input_vars"
							style="display:none"
							class="icl-admin-message-warning"
							data-max_input_vars="
							<?php

							echo ini_get( 'max_input_vars' );

							?>
							">
						<?php
						printf(
							/* translators: Warning on the menu synchronization screen when the server accepts too few values at once. %1$s: the name of the server setting, in bold, %2$s: the number it should be raised to, in bold. */
							esc_html__( 'The menus on this page may not sync because it requires more input variables. Please modify the %1$s setting in your php.ini or .htaccess files to %2$s or more.', 'sitepress' ),
							'<strong>max_input_vars</strong>',
							'<strong>!NUM!</strong>'
						)
						?>
					</span>
					<?php
				} else {
					?>
					<input id="icl_msync_sync" type="submit" class="button-primary wpml-button base-btn"
							value="<?php /* translators: Label of the button on the menu synchronization screen when there is nothing to synchronize; the button is greyed out. It means that no change will be made. */ esc_attr_e( 'Nothing Sync', 'sitepress' ); ?>"<?php disabled( ! $need_sync ); ?>
					/>
					<?php
				}
				?>
			</p>
			<?php wp_nonce_field( '_icl_nonce_menu_sync', '_icl_nonce_menu_sync' ); ?>
		</form>

		<?php
		?>
		<div class="wpml-menu-sync__legend">
			<div class="wpml-menu-sync__legend-heading"><?php /* translators: Heading above the colour key of the menu synchronization table. */ esc_html_e( 'Legend', 'sitepress' ); ?></div>
			<div class="wpml-menu-sync__legend-items">
				<span class="wpml-menu-sync__legend-item">
					<span class="icl_msync_item icl_msync_add">
						<?php echo ICLMenusSync::chip_icon_svg( 'add' );  ?>
						<?php /* translators: Word in a small badge on the menu synchronization screen: this menu item was added. Past participle used as a state. */ esc_html_e( 'Added', 'sitepress' ); ?>
					</span>
					<span class="wpml-menu-sync__legend-text"><?php esc_html_e( 'New item — will be added to translated menus', 'sitepress' ); ?></span>
				</span>
				<span class="wpml-menu-sync__legend-item">
					<span class="icl_msync_item icl_msync_mov">
						<?php echo ICLMenusSync::chip_icon_svg( 'mov' );  ?>
						<?php /* translators: Word in a small badge on the menu synchronization screen: this menu item was moved to another place in the menu. Past participle used as a state. */ esc_html_e( 'Moved', 'sitepress' ); ?>
					</span>
					<span class="wpml-menu-sync__legend-text"><?php esc_html_e( 'Position changed since last sync', 'sitepress' ); ?></span>
				</span>
				<span class="wpml-menu-sync__legend-item">
					<span class="icl_msync_item icl_msync_label_changed">
						<?php echo ICLMenusSync::chip_icon_svg( 'stale' );  ?>
						<?php /* translators: Word in the colour key of the menu synchronization table: the menu item points at content that has changed since, so it is out of date. Adjective. */ esc_html_e( 'Stale', 'sitepress' ); ?>
					</span>
					<span class="wpml-menu-sync__legend-text"><?php esc_html_e( 'English string changed — translation needs review', 'sitepress' ); ?></span>
				</span>
				<span class="wpml-menu-sync__legend-item">
					<span class="icl_msync_item icl_msync_url_missing">
						<?php echo ICLMenusSync::chip_icon_svg( 'nourl' );  ?>
						<?php /* translators: Word in the colour key of the menu synchronization table: the menu item has no address to point at. */ esc_html_e( 'No URL', 'sitepress' ); ?>
					</span>
					<span class="wpml-menu-sync__legend-text"><?php esc_html_e( "Item's URL has no translation in this language", 'sitepress' ); ?></span>
				</span>
				<span class="wpml-menu-sync__legend-item">
					<span class="icl_msync_item icl_msync_options_changed">
						<?php echo ICLMenusSync::chip_icon_svg( 'options' );  ?>
						<?php /* translators: Word in the colour key of the menu synchronization table, for the settings of a menu item. */ esc_html_e( 'Options', 'sitepress' ); ?>
					</span>
					<span class="wpml-menu-sync__legend-text"><?php esc_html_e( 'Menu options (nav-menu settings) differ between languages', 'sitepress' ); ?></span>
				</span>
			</div>
		</div>
		<?php

		$icl_menus_sync->display_menu_links_to_string_translation();
	}
	do_action( 'icl_menu_footer' );
	?>
</div>
