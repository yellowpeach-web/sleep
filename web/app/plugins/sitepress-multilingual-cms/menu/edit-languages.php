<?php

use WPML\Element\API\Languages;
use WPML\FP\Obj;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;

class SitePress_EditLanguages {
	const ACCEPTED_MIME_TYPES = [
		'gif'          => 'image/gif',
		'jpg|jpeg|jpe' => 'image/jpg',
		'png'          => 'image/png',
		'svg'          => 'image/svg+xml',
	];

	public static $active_languages;
	public $upload_dir;
	public $is_writable        = false;
	private $error             = '';
	private $message           = '';
	private $max_locale_length = 35;

	private $wpml_flags;

	private $wpml_flag_files;

	public function __construct( WPML_Flags $wpml_flags ) {
		$this->wpml_flags = $wpml_flags;

		$this->wpml_flag_files = $this->wpml_flags->get_wpml_flags( array_keys( self::ACCEPTED_MIME_TYPES ) );

		wp_enqueue_script(
			'edit-languages',
			ICL_PLUGIN_URL . '/res/js/languages/edit-languages.js',
			[ 'jquery', 'sitepress-scripts' ],
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);

		$delete_language_id = $this->delete_language_request_id();
		if ( $delete_language_id ) {
			$this->delete_language( $delete_language_id );
		}

		$wp_upload_dir    = wp_upload_dir();
		$this->upload_dir = $wp_upload_dir['basedir'] . '/flags';

		if ( ! is_dir( $this->upload_dir ) ) {
			$this->is_writable = is_writable( $wp_upload_dir['basedir'] );
			if ( $this->is_writable ) {
				try {
					mkdir( $this->upload_dir );
				} catch ( Exception $ex ) {
					$this->set_errors( __( 'Upload directory cannot be created. Check your permissions.', 'sitepress' ) );
				}
			} else {
				$this->set_errors( __( 'Upload dir is not writable', 'sitepress' ) );
			}
		}
		$this->is_writable = is_writable( $this->upload_dir );

		$this->migrate();


		CachedLanguageMappings::clearCache();
	}

	function render() {
		$back_url = admin_url( 'admin.php?page=tm/menu/settings&section=languages' );
		?>
		<div class="wrap">
			<style>
				/* Mirror the IA `tailwind.css` rule (scoped under .wrap)
				   so the breadcrumb matches `← Settings` on the Settings
				   sub-pages. tailwind.css isn't enqueued on this legacy
				   page, so the rule has to live inline. */
				.wrap .wpml-settings-back a { color: #50575e; text-decoration: none; }
				.wrap .wpml-settings-back a:hover,
				.wrap .wpml-settings-back a:focus { color: #2F7D92; }
			</style>
			<p class="wpml-settings-back" style="margin:0 0 1em 0;">
				<a href="<?php echo esc_url( $back_url ); ?>">
					&larr;&nbsp;<?php /* translators: Name of the Languages screen: in the WPML menu, as the title of that screen, and as a column heading listing the languages of a piece of content. Plural noun. */ esc_html_e( 'Languages', 'sitepress' ); ?>
				</a>
			</p>
			<h2><?php echo esc_html_x( 'Edit Languages', 'Edit languages page: page title', 'sitepress' ); ?></h2>
			<div id="icl_edit_languages_info">
				<?php echo esc_html_x( 'This table shows the languages of your site. Each row represents a language.', 'Edit languages page: sentence #1', 'sitepress' ); ?>
				<br/><br/>
				<?php echo esc_html_x( 'Each language is described by the following information:', 'Edit languages page: sentence #2', 'sitepress' ); ?>
				<ul>
					<li>
						<strong><?php echo esc_html_x( 'Code:', 'Edit languages page: subtitle #1', 'sitepress' ); ?></strong> <?php echo /* translators: Explanation of the Code field in the bullet list at the top of the Edit Languages screen. It follows the bold word "Code:", so it starts in lower case. */ esc_html_x( 'a unique value that identifies the language. Once entered, the language code cannot be changed.', 'Edit languages page: subtitle #1, description', 'sitepress' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html_x( 'Translations:', 'Edit languages page: subtitle #2', 'sitepress' ); ?></strong> <?php echo /* translators: Explanation of the Translations field in the bullet list at the top of the Edit Languages screen. It follows the bold word "Translations:", so it starts in lower case. */ esc_html_x( 'the way the language name will be displayed in different languages.', 'Edit languages page: subtitle #2, description', 'sitepress' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html_x( 'Flag:', 'Edit languages page: subtitle #3', 'sitepress' ); ?></strong>
						<?php
						$flag_link = \WPML\OutboundLinks\OutboundLinks::to(
							'https://wpml.org/documentation/getting-started-guide/language-switcher-options/custom-language-flags/',
							array(
								'medium'   => 'settings',
								'campaign' => 'languages',
							)
						);
						echo (
							wp_kses_post(
								sprintf(
									/* translators: Explanation of the Flag field in the bullet list at the top of the Edit Languages screen. It follows the bold word "Flag:", so it starts in lower case. %s: the address of a page on wpml.org, inside the link tag that is already in the text. */
									_x(
										'the flag to display next to the language (optional). You can either upload your own flag or use one of WPML\'s built in flag images. Read more about <a href="%s" target="_blank" rel="noopener noreferrer">using flags</a>.',
										'Edit languages page: subtitle #3, description',
										'sitepress'
									),
									$flag_link
								)
							)
						);
						?>
					</li>
					<li>
						<strong><?php echo esc_html_x( 'Default locale:', 'Edit languages page: subtitle #4', 'sitepress' ); ?></strong> <?php echo /* translators: Explanation of the Default locale field in the bullet list at the top of the Edit Languages screen. It follows the bold words "Default locale:", so it starts in lower case. */ esc_html_x( 'this determines the locale value for this language. You should check the name of WordPress localization file to set this correctly.', 'Edit languages page: subtitle #4, description', 'sitepress' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html_x( 'Encode URLs:', 'Edit languages page: subtitle #5', 'sitepress' ); ?></strong> <?php echo /* translators: Explanation of the Encode URLs field in the bullet list at the top of the Edit Languages screen. It follows the bold words "Encode URLs:", so it starts in lower case, and "yes/no" names the two values of that field. */ esc_html_x( 'yes/no, determines if URLs in this language are encoded or use ASCII characters (leave ‘no’ if you are not sure).', 'Edit languages page: subtitle #5, description', 'sitepress' ); ?>
					</li>
					<li>
						<strong><?php echo esc_html_x( 'hreflang:', 'Edit languages page: subtitle #6', 'sitepress' ); ?></strong> <?php echo /* translators: Explanation of the hreflang field in the bullet list at the top of the Edit Languages screen. It follows the bold word "hreflang:", so it starts in lower case. */ esc_html_x( 'the code Google expects for this language. The hreflang should contain at least the language code (usually, made of two letters), or, if you want to specify the country/region, it sould be the same information as the locale name, but in a slightly different format. If the locale for Canadian French is fr_CA, the corresponding hreflang would be fr-CA. Instead of an underscore, use a dash (-).', 'Edit languages page: subtitle #6, description', 'sitepress' ); ?>
					</li>
				</ul>
			</div>
			<?php
			if ( $this->error ) {
				echo '	<div class="below-h2 error"><p>' . $this->error . '</p></div>';
			}

			if ( $this->message ) {
				echo '    <div class="below-h2 updated"><p>' . $this->message . '</p></div>';
			}

			?>
			<br/>
			<?php $this->edit_table(); ?>
		</div>
		<?php
	}

	function edit_table() {
		?>
		<div id="icl_edit_languages_form">
			<p class="wpml-edit-languages-readonly-note">
				<?php
				printf(
					/* translators: Note at the top of the read-only Edit Languages screen. %1$s: the opening tag of a link to Settings -> Languages, %2$s: its closing tag. */
					esc_html__( 'This screen shows how your languages are set up. To add, edit or remove a language, go to %1$sSettings → Languages%2$s.', 'sitepress' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=tm/menu/settings&section=languages' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<div id="icl_edit_languages_table__wrapper">
			<table id="icl_edit_languages_table" class="widefat" cellspacing="0">
				<thead>
				<tr>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen. */ esc_html_e( 'Language name', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the short code that stands for the language, for example de or fr. */ esc_html_e( 'Code', 'sitepress' ); ?></th>
					<?php foreach ( self::get_active_languages() as $lang ) { ?>
						<th><?php /* translators: Column heading in a table and a field label, for the translation of a piece of content. Noun. */ esc_html_e( 'Translation', 'sitepress' ); ?> (<?php echo esc_html( $this->get_language_label_for_current_admin( $lang ) ); ?>)</th>
					<?php } ?>
					<th><?php /* translators: Label of the checkbox that includes the flag image in the language switcher, and column heading in the table of languages. Noun: the flag picture of a country or language, never the verb "to flag". */ esc_html_e( 'Flag', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the code WordPress uses for this language, for example de_DE. */ esc_html_e( 'Default locale', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: whether addresses in this language keep their own letters instead of plain Latin ones. */ esc_html_e( 'Encode URLs', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the language code search engines expect. It is a technical name and stays as it is. */ esc_html_e( 'hreflang', 'sitepress' ); ?></th>

					<th>&nbsp;</th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen. */ esc_html_e( 'Language name', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the short code that stands for the language, for example de or fr. */ esc_html_e( 'Code', 'sitepress' ); ?></th>
					<?php foreach ( self::get_active_languages() as $lang ) { ?>
						<th><?php /* translators: Column heading in a table and a field label, for the translation of a piece of content. Noun. */ esc_html_e( 'Translation', 'sitepress' ); ?> (<?php echo esc_html( $this->get_language_label_for_current_admin( $lang ) ); ?>)</th>
					<?php } ?>
					<th><?php /* translators: Label of the checkbox that includes the flag image in the language switcher, and column heading in the table of languages. Noun: the flag picture of a country or language, never the verb "to flag". */ esc_html_e( 'Flag', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the code WordPress uses for this language, for example de_DE. */ esc_html_e( 'Default locale', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: whether addresses in this language keep their own letters instead of plain Latin ones. */ esc_html_e( 'Encode URLs', 'sitepress' ); ?></th>
					<th><?php /* translators: Column heading in the table of languages on the Edit Languages screen: the language code search engines expect. It is a technical name and stays as it is. */ esc_html_e( 'hreflang', 'sitepress' ); ?></th>

					<th>&nbsp;</th>
				</tr>
				</tfoot>
				<tbody>
				<?php
				foreach ( self::get_active_languages() as $lang ) {
					$this->table_row( $lang );
				}
				?>
				</tbody>
			</table>
			</div>
			<br/>
		</div>
		<?php
	}

	private function get_language_label_for_current_admin( array $lang ) {
		global $sitepress;

		$localized = $sitepress->get_display_language_name( $lang['code'], $sitepress->get_admin_language() );

		return ( null !== $localized && '' !== $localized ) ? $localized : $lang['english_name'];
	}

	private function table_row( $lang ) {
		global $sitepress;
		?>

		<tr>
			<td>
				<div class="read-only" id="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][english_name]">
					<?php echo esc_html( $this->get_language_label_for_current_admin( $lang ) ); ?>
				</div>
			</td>
			<td>
				<div class="read-only" id="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][code]"><?php echo esc_html( $lang['code'] ); ?></div>
			</td>
			<?php
			foreach ( self::get_active_languages() as $translation ) {
				?>
				<td>
					<input type="text"
						   name="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][translations][<?php echo esc_attr( $translation['code'] ); ?>]"
						   value="<?php echo esc_attr( $this->get_translations_data( $lang, $translation ) ); ?>"
						   readonly/>
				</td>
				<?php
			}
			?>
			<td>
				<?php
				echo wp_kses_post( $this->render_flag_preview( $this->wpml_flags->get_flag_url( $lang['code'] ), $lang['code'] ) );
				?>
			</td>
			<td>
				<div class="wpml-edit-languages-flag-use-field">
					<input type="text"
						   name="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][default_locale]"
						   value="<?php echo esc_attr( $lang['default_locale'] ); ?>"
						   maxlength="<?php echo esc_attr( $this->max_locale_length ); ?>"
						   style="width: auto; max-width: 5em;"
						   readonly/>
				</div>
			</td>
			<td>
				<select name="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][encode_url]" disabled>
					<option value="0"
						<?php
						if ( empty( $lang['encode_url'] ) ) :
							?>
						selected="selected"
											   <?php
					endif;
						?>
					><?php /* translators: Option in a dropdown, and the value shown in a table cell, meaning that the setting is turned off. */ esc_html_e( 'No', 'sitepress' ); ?></option>
					<option value="1"
						<?php
						if ( ! empty( $lang['encode_url'] ) ) :
							?>
						selected="selected"
											   <?php
					endif;
						?>
					><?php /* translators: Option in a dropdown, and the value shown in a table cell, meaning that the setting is turned on. */ esc_html_e( 'Yes', 'sitepress' ); ?></option>
				</select>
			</td>

			<td>
				<input type="text" name="icl_edit_languages[<?php echo esc_attr( $lang['id'] ); ?>][tag]" maxlength="<?php echo esc_attr( $this->max_locale_length ); ?>" value="<?php echo esc_attr( $lang['tag'] ); ?>"
					   style="width: auto; max-width: 5em;" readonly/>
			</td>

			<td>
				<?php
				if (
					! Obj::prop( 'built_in', $lang )
					&& $lang['code'] != $sitepress->get_default_language()
					&& count( self::get_active_languages() ) > 1
				) :
					?>
					<a href="
					<?php
					echo admin_url(
						'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/languages.php&amp;trop=1&amp;action=delete-language&amp;id=' .
						urlencode( $lang['id'] ) . '&amp;icl_nonce=' . wp_create_nonce( 'delete-language' . $lang['id'] )
					)
					?>
												   " title="
												   <?php
													/* translators: Name of the icon that removes a language from the site, used as its tooltip and as the text of its image. Verb, imperative. */
													esc_attr_e( 'Delete', 'sitepress' )
													?>
					" onclick="if(!confirm('
					<?php
					/* translators: Question asked before a language is removed from the site. %s: a line break, so the second sentence starts on a new line. */
					echo esc_js( sprintf( __( 'Are you sure you want to delete this language?%sALL the data associated with this language will be ERASED!', 'sitepress' ), "\n" ) )
					?>
					')) return false;"><img src="<?php echo ICL_PLUGIN_URL; ?>/res/img/close.png" alt="
															<?php
															/* translators: Name of the icon that removes a language from the site, used as its tooltip and as the text of its image. Verb, imperative. */
															esc_attr_e( 'Delete', 'sitepress' )
															?>
						" width="16" height="16"/></a>
				<?php endif; ?>
			</td>

		</tr>
		<?php
	}

	public static function get_active_languages() {
		if ( self::$active_languages ) {
			return self::$active_languages;
		}

		global $sitepress;
		$result = [];

		$active_languages = Languages::withFlags( Languages::getActive() );
		if ( \WPML_TM_ATE_Status::is_enabled_and_activated() ) {
			$active_languages = LanguageMappings::withCanBeTranslatedAutomatically( LanguageMappings::withMapping( $active_languages ) );
		}

		foreach ( $active_languages as $lang ) {
			$code = Obj::prop( 'code', $lang );

			$result[ $code ]                  = $lang;
			$result[ $code ]['flag']          = Obj::prop( 'flag_url', $lang );
			$result[ $code ]['from_template'] = Obj::prop( 'flag_from_template', $lang );

			foreach ( $active_languages as $lang_translation ) {
				$result[ $code ]['translation'][ Obj::prop( 'id', $lang_translation ) ] = $sitepress->get_display_language_name( $code, Obj::prop( 'code', $lang_translation ) );
			}
		}

        self::$active_languages = $result;

		return $result;
	}

	private function delete_language_request_id() {
		if (
			! isset( $_GET['action'], $_GET['id'], $_GET['icl_nonce'] )
			|| 'delete-language' !== sanitize_text_field( wp_unslash( $_GET['action'] ) )
		) {
			return 0;
		}

		$lang_id = (int) $_GET['id'];
		$nonce   = sanitize_text_field( wp_unslash( $_GET['icl_nonce'] ) );

		return wp_verify_nonce( $nonce, 'delete-language' . $lang_id ) ? $lang_id : 0;
	}

	function delete_language( $lang_id ) {
		global $wpdb, $sitepress;
		$lang = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_languages WHERE id=%d", $lang_id ) );
		if ( $lang ) {
			if ( in_array( $lang->code, array_values( icl_get_languages_codes() ), true ) ) {
				/* translators: Error message shown when the user tries to remove a language that comes with WPML. "This" is the language the user chose. */
				$error = __( "Error: This is a built in language. You can't delete it.", 'sitepress' );
			} else {
				$old_active_languages = $sitepress->get_active_languages();

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_languages WHERE id=%d", $lang_id ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_languages_translations WHERE language_code=%s", $lang->code ) );

				$translation_ids = $wpdb->get_col( $wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE language_code=%s", $lang->code ) );
				if ( $translation_ids ) {
					WPML_Translation_Records_Delete::jobs_by_translation_ids( $translation_ids );
				}

				$post_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE %s AND language_code=%s",
						[ wpml_like_escape( 'post_' ) . '%', $lang->code ]
					)
				);
				remove_action( 'delete_post', [ $sitepress, 'delete_post_actions' ] );
				foreach ( $post_ids as $post_id ) {
					wp_delete_post( $post_id, true );
				}
				add_action( 'delete_post', [ $sitepress, 'delete_post_actions' ] );

				remove_action( 'delete_term', [ $sitepress, 'delete_term' ], 1 );
				$tax_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE %s AND language_code=%s",
						[ wpml_like_escape( 'tax_' ) . '%', $lang->code ]
					)
				);
				foreach ( $tax_ids as $tax_id ) {
					$row = $wpdb->get_row( $wpdb->prepare( "SELECT term_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $tax_id ) );
					if ( $row ) {
						wp_delete_term( $row->term_id, $row->taxonomy );
					}
				}
				add_action( 'delete_term', [ $sitepress, 'delete_term' ], 1, 3 );

				global $IclCommentsTranslation;
				remove_action( 'delete_comment', [ $IclCommentsTranslation, 'delete_comment_actions' ] );
				foreach ( $post_ids as $post_id ) {
					wp_delete_post( $post_id, true );
				}
				add_action( 'delete_comment', [ $IclCommentsTranslation, 'delete_comment_actions' ] );

				do_action(
					'wpml_translation_update',
					[
						'type'     => 'before_language_delete',
						'language' => $lang->code,
					]
				);

				WPML_Translation_Records_Delete::translations_where( 'language_code = %s', array( $lang->code ) );

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_strings WHERE language=%s", $lang->code ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_string_translations WHERE language=%s", $lang->code ) );

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_locale_map WHERE code=%s", $lang->code ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_flags WHERE lang_code=%s", $lang->code ) );

				icl_cache_clear( false );

				$sitepress->get_translations_cache()->clear();
				$sitepress->clear_flags_cache();
				$sitepress->get_language_name_cache()->clear();
				wpml_reload_active_languages_setting( true );
				$sitepress->get_active_languages( true );
				self::$active_languages = null;
				do_action( 'wpml_update_active_languages', $old_active_languages );

				/* translators: Message shown after a language is removed from the site. %s: the code of that language, in bold. */
				$this->set_messages( sprintf( esc_html__( 'The language %s was deleted.', 'sitepress' ), '<strong>' . $lang->code . '</strong>' ) );
			}
		} else {
			$error = __( 'Error: Language not found.', 'sitepress' );
		}
		if ( ! empty( $error ) ) {
			$this->set_errors( $error );
		}
	}

	function get_errors() {
		return $this->error;
	}

	function set_errors( $str = false ) {
		$this->error .= $str . '<br />';
	}

	function get_messages() {
		return $this->message;
	}

	function set_messages( $str = false ) {
		$this->message .= $str . '<br />';
	}

	function migrate() {
		global $sitepress, $sitepress_settings;
		if ( ! isset( $sitepress_settings['edit_languages_flag_migration'] ) ) {
			foreach ( glob( get_stylesheet_directory() . '/flags/*' ) as $filename ) {
				rename( $filename, $this->upload_dir . '/' . basename( $filename ) );
			}
			$sitepress->save_settings( [ 'edit_languages_flag_migration' => 1 ] );
		}
	}

	private function get_translations_data( $lang, $translation ) {
		$value = isset( $lang['translation'][ $translation['id'] ] ) ? $lang['translation'][ $translation['id'] ] : '';

		return stripslashes_deep( $value );
	}

	private function has_flag_file_url( $flag_url ) {
		if ( ! $flag_url ) {
			return false;
		}

		$path = wp_parse_url( $flag_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === trim( $path, '/' ) ) {
			return false;
		}

		return 'flags' !== basename( untrailingslashit( $path ) );
	}

	private function render_flag_preview( $flag_url, $alt, $width = 18, array $attributes = [] ) {
		if ( ! $this->has_flag_file_url( $flag_url ) ) {
			return '<i class="otgs-ico-flag wpml-edit-languages-flag-fallback" aria-hidden="true" title="' . esc_attr( $alt ) . '" style="display:inline-block;width:' . (int) $width . 'px;text-align:center;"></i>';
		}

		$attributes = array_merge(
			[
				'src'   => esc_url( $flag_url ),
				'alt'   => esc_attr( $alt ),
				'width' => (int) $width,
			],
			$attributes
		);

		$serialized_attributes = [];
		foreach ( $attributes as $name => $value ) {
			$serialized_attributes[] = $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<img ' . implode( ' ', $serialized_attributes ) . ' />';
	}
}
