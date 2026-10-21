<?php

class WPML_Menu_Sync_Display {
	private $menu_id;
	private $icl_ms;
	private $labels;

	public function __construct( $menu_id, $icl_ms ) {
		$this->menu_id = $menu_id;
		$this->icl_ms  = $icl_ms;

		$this->labels = array(
			/* translators: Row in the menu synchronization table proposing that a menu item be taken out. %s: the name of that menu item, in bold. */
			'del'             => array( esc_html__( 'Remove %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table proposing a new name for a menu item. %s: the new name, in bold. */
			'label_changed'   => array( esc_html__( 'Rename label to %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table proposing a new address for a menu item. %s: the new address, in bold. */
			'url_changed'     => array( esc_html__( 'Update URL to %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table for a menu item whose address has no translation. %s: that address, in bold. */
			'url_missing'     => array( esc_html__( 'Untranslated URL %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table proposing that a menu item be moved to another place in the menu. %s: the name of that menu item, in bold. */
			'mov'             => array( esc_html__( 'Change menu order for %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table proposing that a menu item be added. %s: the name of that menu item, in bold. */
			'add'             => array( esc_html__( 'Add %s', 'sitepress' ), '' ),
			/* translators: Row in the menu synchronization table proposing a new value for one setting of a menu item. %1$s: the name of the setting, in bold, %2$s: its new value, in bold. */
			'options_changed' => array( esc_html__( 'Update %1$s menu option to %2$s', 'sitepress' ), '' ),
		);

		if ( defined( 'WPML_ST_FOLDER' ) ) {
			$this->labels['label_missing'] = array(
				/* translators: Row in the menu synchronization table for a menu item whose name has no translation yet. %s: that name, in bold. */
				esc_html__( 'Untranslated string %s', 'sitepress' ),
				$this->print_label_missing_text(),
			);
		}

	}

	private function print_label_missing_text() {
		// `WPML_TM_FOLDER` ('tm') is undefined under a blog license (TM not
		$tm_folder   = defined( 'WPML_TM_FOLDER' ) ? WPML_TM_FOLDER : 'tm';
		$strings_url = 'admin.php?page=' . $tm_folder . '/menu/main.php&tab=strings';

		return '. ' . sprintf(
			/* translators: Sentence added to that row, pointing at the screen where such names are translated. %1$s: the opening tag of a link to the String Translation screen, %2$s: its closing tag. */
			esc_html__(
				'The selected strings can be translated using the %1$sString Translation%2$s page.',
				'sitepress'
			),
			'<a href="' . esc_url( $strings_url ) . '">',
			'</a>'
		);
	}

	public function print_sync_field( $index ) {
		$icl_menus_sync = $this->icl_ms;
		$menu_id        = $this->menu_id;
		if ( isset( $icl_menus_sync->sync_data[ $index ][ $menu_id ] ) ) {
			foreach ( $icl_menus_sync->sync_data[ $index ][ $menu_id ] as $item_id => $languages ) {
				foreach ( $languages as $lang_code => $name ) {
					$additional_data = $this->get_additional_data( $index, $name );
					$item_name       = $this->get_item_name( $index, $name );
					$language_name   = $this->get_language_display_name( $lang_code );
					$input_name      = esc_attr( sprintf( 'sync[%s][%s][%s][%s]%s', $index, $menu_id, $lang_code, $item_id, $additional_data ) );
					?>
					<tr>
						<th scope="row" class="check-column">
							<input type="checkbox"
								   class="wpml-checkbox-native"
								   name="<?php echo $input_name; ?>"
								   value="<?php echo esc_attr( $item_name ); ?>"/>
						</th>
						<td><?php echo esc_html( $language_name ); ?></td>
						<td><?php echo $this->get_action_label( $index, $item_name, $item_id ); ?> </td>
					</tr>
					<?php
				}
			}
		}
	}

	private function get_language_display_name( $lang_code ) {
		global $sitepress;

		$lang_details = $sitepress->get_language_details( $lang_code );

		return isset( $lang_details['display_name'] ) ? $lang_details['display_name'] : (string) $lang_code;
	}

	private function get_action_label( $index, $item_name, $item_id ) {
		$labels = $this->labels;
		if ( 'options_changed' !== $index ) {
			$argument = sprintf( $labels[ $index ][0], '<strong>' . esc_html( $item_name ) . '</strong>' );
		} else {
			$argument = sprintf(
				$labels[ $index ][0],
				'<strong>' . esc_html( $item_id ) . '</strong>',
				'<strong>' . esc_html( $item_name ? $item_name : '0' ) . '</strong>'
			);
		}

		return $this->hierarchical_prefix( $index, $item_id ) . $argument . $labels[ $index ][1];
	}

	private function get_additional_data( $index, $name ) {
		return 'mov' === $index ? '[' . key( $name ) . ']' : '';
	}

	private function get_item_name( $index, $name ) {
		return 'mov' === $index ? current( $name ) : $name;
	}

	private function hierarchical_prefix( $index, $item_id ) {
		$prefix = '';
		if ( in_array( $index, array( 'mov', 'add' ), true ) ) {
			$prefix = str_repeat( ' - ', $this->icl_ms->get_item_depth( $this->menu_id, $item_id ) );
		}

		return $prefix;
	}
}
