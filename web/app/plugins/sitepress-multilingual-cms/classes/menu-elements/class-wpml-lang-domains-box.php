<?php

class WPML_Lang_Domains_Box extends WPML_SP_User {

	public function render() {
		$active_languages = $this->sitepress->get_active_languages();
		$default_language = $this->sitepress->get_default_language();
		$language_domains = $this->sitepress->get_setting( 'language_domains', array() );
		$default_home     = (string) $this->sitepress->convert_url( $this->sitepress->get_wp_api()->get_home_url(), $default_language );
		$home_schema      = wpml_parse_url( $default_home, PHP_URL_SCHEME ) . '://';
		$home_path        = wpml_parse_url( $default_home, PHP_URL_PATH );
		ob_start();
		?>
        <table class="language_domains sub-section">
			<?php
			foreach ( $active_languages as $code => $lang ) {
				$text_box_id = esc_attr( 'language_domain_' . $code );
				$url_code = \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::usableDisplayCode(
					isset( $lang['display_code'] ) ? $lang['display_code'] : null
				);
				$url_code = '' !== $url_code ? $url_code : $code;
				?>
                <tr>
                    <td>
                        <label
                                for="<?php echo $text_box_id; ?>">
							<?php echo esc_html( $lang['display_name'] ); ?>
                        </label>
                    </td>
					<?php
					if ( $code === $default_language ) {
						?>
                        <td id="icl_ln_home">
                            <code>
								<?php echo esc_url( $default_home ); ?>
                            </code>
                        </td>
                        <td>&nbsp;</td>
						<?php
					} else {
						?>
                        <td style="white-space: nowrap">
                            <code><?php echo esc_html( $home_schema ); ?></code>
                            <input
                                    type="text"
                                    id="<?php echo $text_box_id; ?>"
                                    name="language_domains[<?php echo esc_attr( $code ); ?>]"
                                    value="<?php echo esc_attr( $this->get_language_domain( $code, $url_code, $default_home, $language_domains ) ); ?>"
                                    data-language="<?php echo esc_attr( $code ); ?>"
									aria-description="<?php esc_attr_e( 'Edit to change the domain for the language', 'sitepress' ); ?>"
                                    size="30"/>
							<?php if ( isset( $home_path[1] ) && is_string( $home_path[1] ) ) { ?>
                                <code><?php echo esc_html( $home_path ); ?></code>
							<?php } ?>
                        </td>
                        <td>
                            <p style="white-space: nowrap"><input
                                        class="wpml-checkbox-native validate_language_domain"
                                        type="checkbox"
                                        id="validate_language_domains_<?php echo esc_attr( $code ); ?>"
                                        name="validate_language_domains[]"
                                        value="<?php echo esc_attr( $code ); ?>"
                                        checked="checked"/>
                                <label
                                        for="validate_language_domains_<?php echo esc_attr( $code ); ?>">
									<?php esc_html_e( 'Validate on save', 'sitepress' ); ?>
                                </label>
                            </p>
                            <p style="white-space: nowrap">
                                <span class="spinner spinner-<?php echo esc_attr( $code ); ?>"></span>
                                <span id="ajx_ld_<?php echo esc_attr( $code ); ?>"></span>
                            </p>
                        </td>
						<?php
					}
					?>
                </tr>
				<?php
			}
			?>
        </table>
		<?php

		return ob_get_clean();
	}

	private function get_language_domain( $code, $url_code, $default_home, $language_domains ) {
		$home_schema = wpml_parse_url( $default_home, PHP_URL_SCHEME ) . '://';
		$home_path   = wpml_parse_url( $default_home, PHP_URL_PATH );
		if ( isset( $language_domains[ $code ] ) ) {
			$pattern             = [
				'#^' . preg_quote( $home_schema, '#' ) . '#',
				'#' . preg_quote( (string) $home_path, '#' ) . '$#',
			];
			$language_domain_raw = preg_replace( $pattern, '', $language_domains[ $code ] );
		} else {
			$language_domain_raw = $this->render_suggested_url( $default_home, $url_code );
		}

		return (string) $language_domain_raw;
	}

	private function render_suggested_url( $home, $lang ) {
		return $lang . '.' . self::suggested_base_host( $home );
	}

	public static function suggested_base_host( $home ) {
		$url_parts = parse_url( $home );
		$host      = isset( $url_parts['host'] ) ? (string) $url_parts['host'] : '';
		$exp       = explode( '.', $host );
		array_shift( $exp );
		$base = count( $exp ) < 2 ? $host : implode( '.', $exp );

		if ( ! empty( $url_parts['port'] ) ) {
			$base .= ':' . (int) $url_parts['port'];
		}

		return $base;
	}
}
