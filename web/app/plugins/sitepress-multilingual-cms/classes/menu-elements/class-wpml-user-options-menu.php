<?php

class WPML_User_Options_Menu {

	private $current_user;
	private $sitepress;
	private $user_language;
	private $user_admin_def_lang;
	private $all_languages;

	public function __construct( SitePress $sitepress, WP_User $current_user ) {
		$this->sitepress           = $sitepress;
		$this->current_user        = $current_user;
		$this->user_language       = $this->sitepress->get_wp_api()->get_user_meta( $this->current_user->ID, 'icl_admin_language', true );
		$this->user_admin_def_lang = $this->sitepress->get_setting( 'admin_default_language' );
		$this->user_admin_def_lang = $this->user_admin_def_lang === '_default_' ? $this->sitepress->get_default_language() : $this->user_admin_def_lang;

		$user_language_for_all_languages = $this->user_admin_def_lang;
		if ( $this->user_language ) {
			$user_language_for_all_languages = $this->user_language;
		}
		$this->all_languages = $this->sitepress->get_languages( $user_language_for_all_languages );
	}

	public function render() {
		ob_start();

		$this->get_hidden_languages_options();

		do_action( 'wpml_user_profile_options', $this->current_user->ID );

		return ob_get_clean();
	}

	private function get_hidden_languages_options() {

		$show_hidden_languages_options = apply_filters(
			'wpml_show_hidden_languages_options',
			current_user_can( 'manage_options' )
		);

		if ( $show_hidden_languages_options ) {
			$hidden_languages         = $this->sitepress->get_setting( 'hidden_languages' );
			$display_hidden_languages = get_user_meta( $this->current_user->ID, 'icl_show_hidden_languages', true );
			?>
			<tr class="user-language-wrap">
				<th colspan="2"><h3><?php esc_html_e( 'WPML language settings', 'sitepress' ); ?></h3></th>
			</tr>

			<tr class="user-language-wrap">
				<th><?php /* translators: Label in front of the list of languages the visitors do not see, in the user's own settings. */ esc_html_e( 'Hidden languages:', 'sitepress' ); ?></th>
				<td>
					<p>
						<?php
						if ( ! empty( $hidden_languages ) ) {
							if ( 1 === count( $hidden_languages ) ) {
								/* translators: Notice saying that one language is kept from visitors. %s: the name of that language. */
								echo esc_html( sprintf( __( '%s is currently hidden to visitors.', 'sitepress' ), $this->all_languages[ end( $hidden_languages ) ]['display_name'] ) );
							} else {
								$hidden_languages_array = array();
								foreach ( (array) $hidden_languages as $l ) {
									$hidden_languages_array[] = $this->all_languages[ $l ]['display_name'];
								}
								$hidden_languages = implode( ', ', $hidden_languages_array );
								/* translators: Notice saying that several languages are kept from visitors. %s: the names of those languages, separated by commas. */
								echo esc_html( sprintf( __( '%s are currently hidden to visitors.', 'sitepress' ), $hidden_languages ) );
							}
						} else {
							esc_html_e( 'All languages are currently displayed. Choose what to do when site languages are hidden.', 'sitepress' );
						}
						?>
					</p>
					<p>
						<input id="icl_show_hidden_languages" name="icl_show_hidden_languages" type="checkbox" value="1" <?php checked( true, $display_hidden_languages ); ?> />
						<input id="icl_field_hidden_languages" name="icl_field_hidden_languages" type="hidden" value="1">
						&nbsp;<label for="icl_show_hidden_languages"><?php esc_html_e( 'Display hidden languages', 'sitepress' ); ?></label>
					</p>
				</td>
			</tr>
			<?php
		}
	}
}
