<?php

class WPML_Default_Categories_Box extends WPML_SP_User {

	const FIELD = 'wpml_default_categories';

	const PREVIOUS = '_wpml_previous';

	public function sanitize( $submitted ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return get_option( self::FIELD );
		}

		$active_languages = $this->sitepress->get_active_languages();
		$valid            = array();
		$rejected         = array();

		$submitted = (array) $submitted;
		$previous  = isset( $submitted[ self::PREVIOUS ] ) ? (array) $submitted[ self::PREVIOUS ] : array();
		unset( $submitted[ self::PREVIOUS ] );

		foreach ( $submitted as $code => $term_id ) {
			$code = (string) $code;
			if ( ! isset( $active_languages[ $code ] ) ) {
				continue;
			}

			$term_id = (int) $term_id;

			if ( isset( $previous[ $code ] ) && (int) $previous[ $code ] === $term_id ) {
				continue;
			}
			if ( 0 === $term_id ) {
				continue;
			}

			$term_taxonomy_id = $this->category_ttid_of_term_id( $term_id );

			if ( ! $this->is_category_of_language( $term_taxonomy_id, $code ) ) {
				$rejected[] = $code;
				continue;
			}

			$valid[ $code ] = $term_taxonomy_id;
		}

		if ( $rejected ) {
			add_settings_error(
				self::FIELD,
				self::FIELD . '_rejected',
				sprintf(
					/* translators: %s is a comma-separated list of language names. */
					esc_html__( 'The default category was not changed for %s: pick a category that belongs to that language.', 'sitepress' ),
					esc_html( implode( ', ', $this->rejected_names( $rejected, $active_languages ) ) )
				),
				'error'
			);
		}

		if ( $valid ) {
			$stored = (array) $this->sitepress->get_setting( 'default_categories', array() );
			$merged = array_merge( $stored, $valid );

			if ( array_map( 'intval', $merged ) !== array_map( 'intval', $stored ) ) {
				$this->sitepress->set_default_categories( $merged );

				add_settings_error(
					self::FIELD,
					self::FIELD . '_updated',
					esc_html__( 'The default categories have been updated.', 'sitepress' ),
					'updated'
				);
			}
		}

		return get_option( self::FIELD );
	}

	public function render() {
		$active_languages   = $this->sitepress->get_active_languages();
		$default_categories = (array) $this->sitepress->get_setting( 'default_categories', array() );

		ob_start();
		?>
		<table class="wpml_default_categories sub-section">
			<?php
			foreach ( $active_languages as $code => $lang ) :
				$select_id  = 'wpml_default_category_' . $code;
				$categories = $this->get_categories_in( $code );
				$current = isset( $default_categories[ $code ] ) ? (int) $default_categories[ $code ] : 0;
				$current_term_id = 0;
				foreach ( $categories as $category ) {
					if ( (int) $category->term_taxonomy_id === $current ) {
						$current_term_id = (int) $category->term_id;
						break;
					}
				}
				?>
				<tr>
					<td>
						<label for="<?php echo esc_attr( $select_id ); ?>">
							<?php echo esc_html( $lang['display_name'] ); ?>
						</label>
					</td>
					<td>
						<?php if ( $categories ) : ?>
							<select
									id="<?php echo esc_attr( $select_id ); ?>"
									name="<?php echo esc_attr( self::FIELD ); ?>[<?php echo esc_attr( $code ); ?>]"
									data-language="<?php echo esc_attr( $code ); ?>">
								<?php  ?>
								<option value="0"><?php esc_html_e( '— Keep the current default —', 'sitepress' ); ?></option>
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category->term_id ); ?>"<?php selected( (int) $category->term_taxonomy_id, $current ); ?>><?php echo esc_html( $category->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php  ?>
							<input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>[<?php echo esc_attr( self::PREVIOUS ); ?>][<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( (string) $current_term_id ); ?>" />
						<?php else : ?>
							<select id="<?php echo esc_attr( $select_id ); ?>" disabled="disabled">
								<option><?php esc_html_e( 'No categories in this language', 'sitepress' ); ?></option>
							</select>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php

		return ob_get_clean();
	}

	private function category_ttid_of_term_id( $term_id ) {
		global $wpdb;

		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				     FROM {$wpdb->term_taxonomy}
				     WHERE term_id = %d
				     AND taxonomy = 'category'",
				$term_id
			)
		);
	}

	private function is_category_of_language( $term_taxonomy_id, $language_code ) {
		global $wpdb;

		if ( $term_taxonomy_id <= 0 ) {
			return false;
		}

		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				     FROM {$wpdb->term_taxonomy}
				     WHERE term_taxonomy_id = %d
				     AND taxonomy = 'category'",
				$term_taxonomy_id
			)
		);
		if ( ! $exists ) {
			return false;
		}

		$details = $this->sitepress->get_element_language_details( $term_taxonomy_id, 'tax_category' );

		return isset( $details->language_code ) && (string) $details->language_code === $language_code;
	}

	private function get_categories_in( $language_code ) {
		global $wpdb;

		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, t.term_id, t.name
				     FROM {$wpdb->terms} t
				     JOIN {$wpdb->term_taxonomy} tt
				         ON tt.term_id = t.term_id AND tt.taxonomy = 'category'
				     JOIN {$wpdb->prefix}icl_translations tr
				         ON tr.element_id = tt.term_taxonomy_id AND tr.element_type = 'tax_category'
				     WHERE tr.language_code = %s
				     ORDER BY t.name",
				$language_code
			)
		);

		return is_array( $categories ) ? $categories : array();
	}

	private function rejected_names( array $rejected, array $active_languages ) {
		$names = array();
		foreach ( array_unique( $rejected ) as $code ) {
			$names[] = isset( $active_languages[ $code ]['display_name'] )
				? $active_languages[ $code ]['display_name'] : $code;
		}

		return $names;
	}
}
