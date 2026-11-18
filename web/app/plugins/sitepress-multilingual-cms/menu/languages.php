<?php
	global $sitepress, $sitepress_settings, $wpdb, $wpml_language_switcher;

use WPML\Core\WP\App\Resources;

if ( ! is_plugin_active( WPML_PLUGIN_BASENAME ) ) {
	?>
		<h2><?php /* translators: Heading of the setup step shown before WPML is configured, and the label of the link that opens it. */ esc_html_e( 'Setup WPML', 'sitepress' ); ?></h2>
		<div class="updated fade">
			<p style="line-height:1.5"><?php esc_html_e( 'The WPML Multilingual CMS plugin is not currently enabled.', 'sitepress' ); ?></p>
			<p style="line-height:1.5">
			<?php
				echo sprintf(
					/* translators: Notice shown when WPML is not turned on yet. %1$s: the opening tag of a link to the plugins screen, %2$s: its closing tag. */
					esc_html__( 'Please go to the %1$sPlugins%2$s page and enable the WPML Multilingual CMS plugin before trying to configure the plugin.', 'sitepress' ),
					'<a href="plugins.php">',
					'</a>'
				)
			?>
			</p>
		</div>
		<?php
		return;
}

if ( isset( $_GET['trop'] ) ) {
	require_once dirname( __FILE__ ) . '/edit-languages.php';
	global $icl_edit_languages;
	$flags_factory      = new WPML_Flags_Factory( $wpdb );
	$icl_edit_languages = new SitePress_EditLanguages( $flags_factory->create() );
	$icl_edit_languages->render();
	return;
}

	$sitepress_settings                 = get_option( 'icl_sitepress_settings' );
	$active_languages                   = $sitepress->get_active_languages();
	$hidden_languages                   = $sitepress->get_setting( 'hidden_languages' );
	$automatic_redirect                 = $sitepress->get_setting( 'automatic_redirect' );
	$setting_urls                       = $sitepress->get_setting( 'urls' );
	$existing_content_language_verified = $sitepress->get_setting( 'existing_content_language_verified' );
	$language_negotiation_type          = $sitepress->get_setting( 'language_negotiation_type' );
	$seo                                = $sitepress->get_setting( 'seo' );
	$default_language                   = $sitepress->get_default_language();
	$all_languages                      = $sitepress->get_languages( $sitepress->get_admin_language() );
	$sample_lang                        = false;
	$default_language_details           = false;
	$wp_api                             = $sitepress->get_wp_api();
	$should_hide_admin_language         = $wp_api->version_compare_naked( get_bloginfo( 'version' ), '4.7', '>=' );
	$encryptor                          = new WPML_Data_Encryptor();

if ( ! $existing_content_language_verified ) {
	$blog_current_lang = 0;
	if ( $blog_lang = get_option( 'WPLANG' ) ) {
		$exp               = explode( '_', $blog_lang );
		$blog_current_lang = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code= %s",
				$exp[0]
			)
		);
	}
	if ( ! $blog_current_lang && defined( 'WPLANG' ) && WPLANG != '' ) {
		$blog_current_lang = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE default_locale=%s", WPLANG ) );
		if ( ! $blog_current_lang ) {
			$blog_lang         = WPLANG;
			$exp               = explode( '_', $blog_lang );
			$blog_current_lang = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code= %s",
					$exp[0]
				)
			);
		}
	}
	if ( ! $blog_current_lang ) {
		$blog_current_lang = 'en';
	}
	$languages = $sitepress->get_languages( $blog_current_lang, false, true, false, 'display_name' );
} else {
	$languages = $sitepress->get_languages( $sitepress->get_admin_language(), false, true, false, 'display_name' );
	foreach ( $active_languages as $lang ) {
		if ( $lang['code'] != $default_language ) {
			$sample_lang = $lang;
			break;
		}
	}
	$default_language_details = $sitepress->get_language_details( $default_language );
}

$sample_lang_url_code = '';
if ( $sample_lang && class_exists( '\WPML\Language\ActiveLanguagesReadModel' ) ) {
	$sample_lang_url_map  = \WPML\Language\ActiveLanguagesReadModel::urlCodeMap();
	$sample_lang_url_code = isset( $sample_lang_url_map[ $sample_lang['code'] ] )
		? (string) $sample_lang_url_map[ $sample_lang['code'] ]
		: (string) $sample_lang['code'];
} elseif ( $sample_lang ) {
	$sample_lang_url_code = (string) $sample_lang['code'];
}
global $language_switcher_defaults, $language_switcher_defaults_alt;

$theme_wpml_config_file = WPML_Config::get_theme_wpml_config_file();

$resource = Resources::enqueueApp( 'wpmlLanguageSettings' );
$resource( [
	'name' => 'wpmlLanguageSettings',
	'data' => [
		'endpoints'        => [
			'setFileType' => \WPML\TM\Settings\Flags\Endpoints\SetFormat::class,
		],
		'loaderImage'      => esc_url( ICL_PLUGIN_URL ) . '/res/img/ajax-loader.gif',
		'flagsDefaultType' => [
			'selectedFormat' => \WPML\TM\Settings\Flags\Options::getFormat(),
			'allowedFormats' => \WPML\TM\Settings\Flags\Options::getAllowedFormats(),
		],
	],
] );

?>
<?php $sitepress->noscript_notice(); ?>

<div class="wrap wpml-settings-container">
	<?php /* translators: Heading of the setup step shown before WPML is configured, and the label of the link that opens it. */ ?>
	<h2><?php esc_html_e( 'Setup WPML', 'sitepress' ) ?></h2>

	<?php
		if ( count( $active_languages ) > 1 ): ?>
				<p>
					<strong><?php esc_html_e( 'This screen contains the language settings for your site.', 'sitepress' ); ?></strong>
				</p>
				<ul class="wpml-navigation-links js-wpml-navigation-links">
					<?php
					$navigation_items = array(
						/* translators: Name of the section that lists the languages of the site, in the menu of the Languages settings screen and as that section's heading. */
						'#lang-sec-2'   => __( 'Language URL format', 'sitepress' ),
						/* translators: Name of the section that sets the language of the WordPress admin, in the menu of the Languages settings screen and as that section's heading. */
						'#lang-sec-4'   => __( 'Admin language', 'sitepress' ),
						/* translators: Name of the section that hides languages from visitors, in the menu of the Languages settings screen and as that section's heading. */
						/* translators: Name of the section about making a theme ready for several languages, in the menu of the Languages settings screen and as that section's heading. */
						'#lang-sec-8'   => __( 'Make themes work multilingual', 'sitepress' ),
						/* translators: Name of the section about which language visitors see the site in, in the menu of the Languages settings screen and as that section's heading. */
						'#lang-sec-9'   => __( 'Visitor language', 'sitepress' ),
						/* translators: Heading of the section with the settings for search engines. */
						'#lang-sec-9-5' => __( 'SEO Options', 'sitepress' ),
						'#cookie'       => __( 'Language filtering for AJAX operations', 'sitepress' ),
					);

					if ( $should_hide_admin_language && array_key_exists( '#lang-sec-4', $navigation_items ) ) {
						unset( $navigation_items['#lang-sec-4'] );
					}

					$navigation_items = apply_filters( 'wpml_admin_languages_navigation_items', $navigation_items );

					foreach ( $navigation_items  as $link => $text ) {
						echo '<li><a href="' . esc_attr( $link ) . '">' . esc_html( $text ) . '</a></li>';
					}
					?>
				</ul>
			<?php endif; ?>

			<?php  ?>
			<div id="lang-sec-1" class="wpml-section wpml-section-languages" style="display:none">
				<div class="wpml-section-header">
					<?php /* translators: Name of the section that lists the languages of the site, in the menu of the Languages settings screen and as that section's heading. */ ?>
					<h3><?php _e( 'Site Languages', 'sitepress' ); ?></h3>
				</div>

				<div class="wpml-section-content">
					<div class="wpml-section-content-inner">

							<h4><?php _e('These languages are enabled for this site:','sitepress'); ?></h4>

							<?php do_action( 'wpml_before_active_languages_display' ); ?>

							<ul id="icl_enabled_languages" class="enabled-languages">
									<?php
									foreach ( $active_languages as $lang ) :
										$is_default = ( $default_language == $lang['code'] );
										?>
										<?php
										if ( ! empty( $hidden_languages ) && in_array( $lang['code'], $hidden_languages ) ) {
											/* translators: Word in brackets after the name of a language, saying that visitors do not see it. Adjective, written in lower case. */
											$hidden = '&nbsp<strong style="color:#f00">(' . esc_html__( 'hidden', 'sitepress' ) . ')</strong>';
										} else {
											$hidden = '';
										}
										?>

								<li
										<?php
										if ( $is_default ) :
											?>
 class="selected"<?php endif; ?>>
									<input id="default_language_<?php echo esc_attr( $lang['code'] ); ?>"
										   class="wpml-radio-native"
										   name="default_language" type="radio"
										   value="<?php echo esc_attr( $lang['code'] ); ?>" <?php checked( $is_default ); ?> />
									<label for="default_language_<?php echo esc_attr( $lang['code'] ); ?>">
										<?php echo esc_html( $lang['display_name'] ) . $hidden; ?>
										<?php
										if ( $is_default ) :
											?>
											(<?php /* translators: Word in brackets after the name of a language, saying that it is the language the site was built in. Adjective or noun, written in lower case, never a verb. */ esc_html_e( 'default', 'sitepress' ); ?>)<?php endif ?>
									</label>
								</li>
								<?php endforeach ?>
							</ul>
						<?php do_action( 'wpml_after_active_languages_display' ); ?>

						<?php wp_nonce_field('wpml_set_default_language', 'set_default_language_nonce'); ?>
						<p class="buttons-wrap">
							<button id="icl_cancel_default_button" class="button-secondary wpml-button base-btn wpml-button--outlined action"><?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on, for example in the dialog "Translate with PTC?" (keep the previous choice). Verb, imperative, not the noun "a cancellation". */ esc_html_e( 'Cancel', 'sitepress' ); ?></button>
							<button id="icl_save_default_button" class="button-primary wpml-button base-btn action"><?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_html_e( 'Save', 'sitepress' ); ?></button>
						</p>

                        <p>
                            <button id="icl_change_default_button"
                                    class=" button-secondary wpml-button base-btn wpml-button--outlined action <?php if ( count( $active_languages ) < 2 ): ?>hidden<?php endif ?>">
								<?php esc_html_e( 'Change default language', 'sitepress' ) ?>
                            </button>
                            <button id="icl_add_remove_button" class="button-secondary wpml-button base-btn wpml-button--outlined action">
								<?php esc_html_e( 'Add / Remove languages', 'sitepress' ) ?>
                            </button>
                        </p>
                        <p class="icl_ajx_response" id="icl_ajx_response"></p>

						<div id="icl_avail_languages_picker" class="hidden">
							<ul class="available-languages">
								<?php
								foreach ( $languages as $lang ) {
									$checked  = checked( '1', $lang['active'], false );
									$disabled = disabled( $default_language, $lang['code'], false );

									$language_item_classes = array();
									if ( (bool) $lang['active'] ) {
										$language_item_classes[] = 'wpml-selected';
									}
									?>
									<li class="<?php echo esc_attr( implode( ' ', $language_item_classes ) ); ?>">
										<label for="wpml-language-<?php echo esc_attr( $lang['code'] ); ?>">
											<input type="checkbox" id="wpml-language-<?php echo esc_attr( $lang['code'] ); ?>"
												   class="wpml-checkbox-native"
												   value="<?php echo esc_attr( $lang['code'] ); ?>" <?php echo $checked . ' ' . $disabled; ?>
												   <?php /* translators: Label of the checkbox that turns a language switcher on. */ ?>
												   aria-description="<?php echo __('Enable', 'sitepress'); ?>"
											/>
											<span role="presentation" aria-hidden="true"><?php echo $sitepress->get_flag_image($lang['code']) ?></span>
											<?php echo esc_html( $lang['display_name'] ); ?>
										</label>
									</li>
									<?php
								}
								?>
							</ul>

							<div class="buttons-wrap">
								<input id="icl_cancel_language_selection" type="button" class="button-secondary wpml-button base-btn wpml-button--outlined action"
									   value="<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on, for example in the dialog "Translate with PTC?" (keep the previous choice). Verb, imperative, not the noun "a cancellation". */ esc_attr_e( 'Cancel', 'sitepress' ); ?>" />
								<input id="icl_save_language_selection" type="button" class="button-primary action"
									   value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_attr_e( 'Save', 'sitepress' ); ?>" style="display:none"/>
								<div id="wpml_language_selection_save" style="display: inline-block"></div>

							</div>

						</div>


                        <p>
                            <a href="admin.php?page=<?php echo WPML_PLUGIN_FOLDER ?>/menu/languages.php&amp;trop=1">
								<?php /* translators: Link on the Languages settings screen that opens the screen where languages are edited. */ ?>
								<?php esc_html_e( 'Edit Languages', 'sitepress' ) ?>
                            </a>
                        </p>

					</div> <!-- wpml-section-content-inner -->

				</div> <!-- .wcml-section-content -->
			</div> <!-- .wpml-section-languages -->

			<?php
			if ( ! class_exists( 'WP_Http' ) ) {
				include_once ABSPATH . WPINC . '/class-http.php';
			}
			global $wpml_url_converter, $wpml_request_handler;

			$validator = wpml_get_langs_in_dirs_val( $wpml_url_converter );
			?>
			<?php if ( count( $active_languages ) > 1 ) : ?>
				<div class="wpml-section wpml-section-url-format" id="lang-sec-2">
					<div class="wpml-section-header">
						<h3><?php esc_html_e( 'Language URL format', 'sitepress' ); ?></h3>
					</div>
					<div class="wpml-section-content">
						<h4><?php /* translators: Heading above the choice of how the language shows in the address of a page. It ends without a full stop because the choices follow it. */ esc_html_e( 'Choose how to determine which language visitors see contents in', 'sitepress' ); ?></h4>
						<?php
						$url_options_doc_url = \WPML\OutboundLinks\OutboundLinks::to(
							'https://wpml.org/documentation/getting-started-guide/language-setup/language-url-options/',
							array(
								'medium'   => 'settings',
								'campaign' => 'languages',
							)
						);
						?>
						<p><a class="wpml-external-link" href="<?php echo esc_url( $url_options_doc_url ); ?>" target="_blank" rel="noopener"><?php /* translators: Link under the heading of the language URL format choice. It opens the documentation page about the URL formats in a new tab. */ esc_html_e( 'Learn more about Language URL Options', 'sitepress' ); ?></a></p>
						<form id="icl_save_language_negotiation_type" name="icl_save_language_negotiation_type" action="">
							<?php wp_nonce_field( 'save_language_negotiation_type', 'save_language_negotiation_type_nonce' ); ?>
							<ul>
								<?php

								$abs_home = $wpml_url_converter->get_abs_home();
								$icl_folder_url_enabled = $validator->validate_langs_in_dirs();
								?>
								<li>
									<label for="icl_language_negotiation_type_1">
										<input id="icl_language_negotiation_type_1" class="wpml-radio-native" type="radio" name="icl_language_negotiation_type" value="1" <?php checked( 1 == $language_negotiation_type ); ?><?php if ( \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::hasDirectoriesNote() ) : ?> aria-describedby="<?php echo esc_attr( \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::DIRECTORIES_NOTE_ID ); ?>"<?php endif; ?> />
										<?php esc_html_e( 'Different languages in directories', 'sitepress' ); ?>
										<?php
											$root = ! empty( $setting_urls['directory_for_default_language'] );
											echo $validator->print_explanation( $sample_lang['code'], $root );
										?>
									</label>

									<div id="icl_use_directory_wrap" style="
									<?php
									if ( $language_negotiation_type != 1 ) :
										?>
										display:none;<?php endif; ?>" >
										<p class="sub-section">
											<label for="icl_use_directory">
												<input class="wpml-checkbox-native" type="checkbox" name="use_directory" id="icl_use_directory" value="1"
													<?php checked( ! empty( $setting_urls['directory_for_default_language'] ) ); ?>
													<?php  ?>
													data-root-forgotten="<?php echo WPML_Root_Page_Actions::is_forgotten_root( $setting_urls ) ? '1' : '0'; ?>"<?php if ( \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::hasDefaultDirectoryNote() ) : ?> aria-describedby="<?php echo esc_attr( \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::DEFAULT_DIRECTORY_NOTE_ID ); ?>"<?php endif; ?> />
												<?php esc_html_e( 'Use directory for default language', 'sitepress' ); ?>
											</label>
										</p><?php \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::printDefaultDirectoryNote(); ?>


										<div id="icl_use_directory_details" class="sub-section"
										<?php
										if ( empty( $setting_urls['directory_for_default_language'] ) ) {
											echo ' style="display:none"';}
										?>
											 >

											<p><?php esc_html_e( 'What to show for the root url:', 'sitepress' ); ?>
												<a href="#" class="js-wpml-root-url-tooltip-open wpml-ls-tooltip-open otgs-ico-help" data-content="
													<?php
														echo esc_attr(
															wp_kses(
																__( "This is what visitors see when they open your site's root URL, which has no language directory. You can redirect them to the default language, or show an HTML file or a page.", 'sitepress' ),
																[
																	'strong' => [],
																]
															)
														)
													?>
												"></a>
											</p>

											<?php
											$root_url          = trailingslashit( $abs_home );
											$default_lang      = $sitepress->get_default_language();
											$default_url_codes = apply_filters( 'wpml_language_codes_map', [ $default_lang => $default_lang ] );
											$default_url_code  = ! empty( $default_url_codes[ $default_lang ] ) && is_string( $default_url_codes[ $default_lang ] ) ? $default_url_codes[ $default_lang ] : $default_lang;
											$default_home_url  = $root_url . $default_url_code . '/';
											?>
											<ul>
												<li>
													<label for="wpml_show_on_root_redirect">
														<input id="wpml_show_on_root_redirect" type="radio" name="show_on_root"
															class="wpml-radio-native<?php echo 'redirect' === $setting_urls['show_on_root'] ? ' active' : ''; ?>"
															value="redirect" <?php checked( 'redirect' === $setting_urls['show_on_root'] ); ?> />
														<?php /* translators: Label of the option that sends visitors of the site's root address to the home page of the default language, on the Languages settings screen; an explanation follows after a dash. */ esc_html_e( 'Redirect to the default language', 'sitepress' ); ?> &ndash;
														<span class="explanation-text">
															<?php
															echo esc_html(
																sprintf(
																	/* translators: %1$s is the site's root address, e.g. http://example.com/. %2$s is the home page of the default language, e.g. http://example.com/en/. */
																	__( 'Visitors who open %1$s go to %2$s.', 'sitepress' ),
																	$root_url,
																	$default_home_url
																)
															);
															?>
														</span>
													</label>
													<p id="wpml_show_on_root_redirect_details" class="explanation-text"<?php if ( 'redirect' !== $setting_urls['show_on_root'] ) : ?> style="display:none"<?php endif; ?>>
														<?php esc_html_e( 'Browsers and caches remember this redirect. Clear your site cache each time you change this setting.', 'sitepress' ); ?>
													</p>
												</li>
												<li>
													<label for="wpml_show_on_root_html_file">
														<input id="wpml_show_on_root_html_file" type="radio" name="show_on_root"
															<?php if($setting_urls['show_on_root'] === 'html_file'):?>
																class="wpml-radio-native active"
															<?php else:?>
																class="wpml-radio-native"
															<?php endif;?>
															   value="html_file" <?php checked( 'html_file' === $setting_urls['show_on_root'] ); ?> />
														<?php /* translators: Label of the option that puts a file of your own at the root address of the site, on the Languages settings screen; an explanation follows after a dash. */ esc_html_e( 'HTML file', 'sitepress' ); ?> &ndash;
														<span class="explanation-text">
															<?php
																echo wp_kses(
																	__( 'Path to an <strong>.html</strong>, <strong>.htm</strong>, or <strong>.php</strong> file inside your WordPress installation folder.', 'sitepress' ),
																	[
																		'strong' => [],
																	]
																)
															?>
														</span>
													</label>
													<p>
														<input type="text" id="root_html_file_path" name="root_html_file_path" value="<?php echo esc_attr( $setting_urls['root_html_file_path'] ); ?>" placeholder="wpml-index.html" />
														<label class="icl_error_text icl_error_1" for="root_html_file_path" style="display: none;">
															<?php esc_html_e( 'Please select what to show for the root url.', 'sitepress' ); ?>
														</label>
													</p>
												</li>

												<li>
													<label>
														<input id="wpml_show_on_root_page" type="radio" name="show_on_root" value="page"
	                                                      <?php if($setting_urls['show_on_root'] === 'page'):?>
		                                                    class="wpml-radio-native active"
	                                                      <?php else:?>
		                                                    class="wpml-radio-native"
	                                                      <?php endif;?>

														   <?php checked( 'page' === $setting_urls['show_on_root'] ); ?>
														   <?php
															if ( $setting_urls['show_on_root'] === 'page' ) :
																?>
 class="active"<?php endif; ?>
														/>
														<?php /* translators: Label of the option that puts one of the site's pages at the root address of the site, on the Languages settings screen. */ esc_html_e( 'A page', 'sitepress' ); ?>

														<span style="display: none;" id="wpml_show_page_on_root_x"><?php esc_html_e( 'Please save the settings first by clicking Save.', 'sitepress' ); ?></span>

														<span id="wpml_show_page_on_root_details"
														<?php
														if ( $setting_urls['show_on_root'] !== 'page' ) :
															?>
														style="display:none"<?php endif; ?>>
														<?php
														$rp        = ! empty( $setting_urls['root_page'] ) ? get_post( $setting_urls['root_page'] ) : null;
														$rp_state  = WPML_Root_Page_Actions::root_page_state( $rp );
														$rp_usable = WPML_Root_Page_Actions::ROOT_PAGE_USABLE === $rp_state;
														$rp_link   = WPML_Root_Page_Actions::settings_root_page_link( $rp );
														?>
														<?php if ( $rp_link['url'] ) : ?>
															<a href="<?php echo esc_url( $rp_link['url'] ); ?>">
																<?php echo esc_html( $rp_link['text'] ); ?>
															</a>
														<?php else : ?>
															<?php echo esc_html( $rp_link['text'] ); ?>
														<?php endif; ?>
														</span>

														<?php if ( ! $rp_usable ) : ?>
															<?php  ?>
															<p class="icl_error_text" id="wpml_show_page_on_root_missing" style="display: none; margin: 4px 0 0;">
																<?php echo esc_html( WPML_Root_Page_Actions::root_page_save_error( $rp ) ); ?>
															</p>
														<?php endif; ?>

														<p id="icl_hide_language_switchers" class="sub-section"
														<?php
														if ( $setting_urls['show_on_root'] !== 'page' ) :
															?>
															style="display:none"<?php endif; ?>>
														  <label>
															  <input class="wpml-checkbox-native" type="checkbox" name="hide_language_switchers" id="icl_hide_language_switchers"
																	 value="1" <?php checked( $setting_urls['hide_language_switchers'] ); ?> />
															  <?php esc_html_e( 'Hide language switchers on the root page', 'sitepress' ); ?>
														  </label>
													  </p>

													</label>
												</li>


											</ul>

										</div>
									</div>

									<?php if ( ! $icl_folder_url_enabled ): ?>
									<div class="icl_error_text" style="margin:10px;">
										<p>
											<?php /* translators: Warning under the language URL format choice. The site uses Plain permalinks (no permalink structure), so the option that puts each language in a directory of its own cannot work. */ esc_html_e( 'Languages in directories do not work with Plain permalinks.', 'sitepress' ); ?>
											<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php /* translators: Link after that warning. "Settings > Permalinks" is the WordPress admin screen where the permalink structure is chosen; use the names WordPress shows in your language. */ esc_html_e( 'Choose a different structure in Settings > Permalinks.', 'sitepress' ); ?></a>
										</p>
									</div>
									<?php endif; ?><?php \WPML\UrlHandling\SubsiteClash\UrlFormatBoxNotes::printDirectoriesNote(); ?>
								</li>
								<?php
								$icl_lnt_disabled = \WPML\Core\LanguageNegotiation::isDomainModeAvailable()
									? ''
									: 'disabled="disabled" ';
								?>
								<li>
									<label for="icl_lnt_domains">
										<input <?php echo $icl_lnt_disabled; ?>id="icl_lnt_domains" type="radio" class="wpml-radio-native" name="icl_language_negotiation_type"
											   value="2" <?php checked( 2 == $language_negotiation_type ); ?> />
										<?php esc_html_e( 'A different domain per language', 'sitepress' ); ?>
										<?php if ( $icl_lnt_disabled ) : ?>
											<span class="icl_error_text"><?php esc_html_e( 'This option is not yet available for Multisite installs', 'sitepress' ); ?></span>
										<?php endif; ?>
										<?php if ( defined( 'WPML_SUNRISE_MULTISITE_DOMAINS' ) && WPML_SUNRISE_MULTISITE_DOMAINS ) : ?>
											<span class="icl_error_text"><?php /* translators: Note next to an option on the Languages settings screen: the option is still being tried out and may change. */ esc_html_e( 'Experimental', 'sitepress' ); ?></span>
										<?php endif; ?>
									</label>
									<?php wp_nonce_field( 'language_domains_nonce', '_icl_nonce_ldom', false ); ?>
									<?php wp_nonce_field( 'validate_language_domain', 'validate_language_domain_nonce', false ); ?>
									<div id="icl_lnt_domains_box">
										<?php
										if ( (int) $language_negotiation_type === 2 ) :
											$domains_box = new WPML_Lang_Domains_Box( $sitepress );
											echo $domains_box->render();
										endif;
										?>
									</div>
									<div id="language_domain_xdomain_options" class="sub-section"
									<?php
									if ( $language_negotiation_type != 2 ) {
										echo ' style="display:none"';}
									?>
										>
										<p><?php esc_html_e( 'Pass session arguments between domains through the language switcher', 'sitepress' ); ?></p>
										<p>
											<label>
												<input class="wpml-radio-native" type="radio" name="icl_xdomain_data"
													   value="<?php echo WPML_XDOMAIN_DATA_GET; ?>"
													   <?php checked( WPML_XDOMAIN_DATA_GET === (int) $sitepress_settings['xdomain_data'] ); ?>/>
												<?php esc_html_e( 'Pass arguments via GET (the url)', 'sitepress' ); ?>
											</label>
										</p>
										<p>
											<label>
												<input class="wpml-radio-native" type="radio" name="icl_xdomain_data"
													   value="<?php echo WPML_XDOMAIN_DATA_POST; ?>"
													   <?php checked( WPML_XDOMAIN_DATA_POST === (int) $sitepress_settings['xdomain_data'] ); ?>/>
												<?php esc_html_e( 'Pass arguments via POST', 'sitepress' ); ?>
											</label>
										</p>
										<p>
											<label>
												<input class="wpml-radio-native" type="radio" name="icl_xdomain_data"
													   value="<?php echo WPML_XDOMAIN_DATA_OFF; ?>"
													   <?php checked( WPML_XDOMAIN_DATA_OFF === (int) $sitepress_settings['xdomain_data'] ); ?>/>
												<?php esc_html_e( 'Disable this feature', 'sitepress' ); ?>
											</label>
										</p>

										<?php $encryptor_library = $encryptor->get_crypt_library(); ?>
										<?php if ( $encryptor_library === 'openssl' ) : ?>
											<p>
											<?php
											printf(
												/* translators: Note under the option that passes data between the domains of the site. %s: the name of the method used to scramble the data, written as it is in the code. */
												esc_html__( 'The data will be encrypted with the %s algorithm.', 'sitepress' ),
												'AES-256-CTR + HMAC-SHA256'
											)
											?>
													</p>
										<?php else : ?>
											<p><?php esc_html_e( 'Your host does not support encryption (the openssl PHP extension is missing), so no data will be passed between the domains: the language switcher takes the visitor to the other domain without it.', 'sitepress' ); ?></p>
										<?php endif; ?>

										<?php
										$domains_data_url = \WPML\OutboundLinks\OutboundLinks::to(
											'https://wpml.org/documentation/getting-started-guide/language-setup/passing-session-data-between-languages-in-domains/',
											array(
												'medium'   => 'settings',
												'campaign' => 'languages',
											)
										);
										?>
										<p><a href="<?php echo esc_url( $domains_data_url ); ?>" target="_blank"><?php esc_html_e( 'Learn more about passing data between domains', 'sitepress' ); ?></a></p>


									</div>

								</li>
								<li>
									<label>
										<input type="radio" class="wpml-radio-native" name="icl_language_negotiation_type" value="3" <?php checked( 3 == $language_negotiation_type ); ?> />
										<?php esc_html_e( 'Language name added as a parameter', 'sitepress' ); ?>
										<span class="explanation-text">
											<?php printf( '(%s?lang=%s - %s)', esc_url( trailingslashit( get_home_url() ) ), esc_html( $sample_lang_url_code ), esc_html( $sample_lang['display_name'] ) ); ?>
										</span>
									</label>
								</li>
							</ul>
							<div class="wpml-form-message" style="display:none;"></div>
							<div class="wpml-form-errors icl_form_errors" style="display: none;"></div>
							<p class="buttons-wrap">
								<span class="icl_ajx_response" id="icl_ajx_response2"></span>
								<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_html_e( 'Save', 'sitepress' ); ?>" type="submit" data-type="submit-btn" data-content="
									<?php
										echo esc_attr(
											wp_kses(
												sprintf(
													/* translators: %s is the documentation URL for setting the site's root URL. */
													__( "To enable the <strong>Use directory for default language</strong> option, you need to <a href='%s' target='_blank'>specify what to show for your site's root URL</a>.", 'sitepress' ),
													esc_url(
														\WPML\OutboundLinks\OutboundLinks::to(
															'https://wpml.org/documentation/getting-started-guide/language-setup/directory-default-language/',
															array(
																'medium'   => 'settings',
																'campaign' => 'languages',
															)
														)
													)
												),
												[
													'a' => [
														'href'   => true,
														'target' => true,
													],
													'strong' => [],
												]
											)
										)
									?>
								"/>
							</p>
						</form>
					</div>
				</div> <!-- .wpml-section-url-format -->
			<?php endif; ?>

		<div class="wpml-section wpml-section-url-format" id="lang-sec-2-1" style="margin-top: 0">
			<div class="wpml-section-header">
				<h3><?php esc_html_e( 'Default flag format', 'sitepress' ); ?></h3>
			</div>
			<div class="wpml-section-content" id="wpml-default-flag-format">
			</div>
		</div>

		<?php do_action( 'wpml_admin_after_languages_url_format' ); ?>

		<?php if ( count( $all_languages ) > 1 && ! $should_hide_admin_language ): ?>
			<div class="wpml-section wpml-section-admin-language" id="lang-sec-4">
				<div class="wpml-section-header">
					<h3><?php /* translators: Name of the section that sets the language of the WordPress admin, in the menu of the Languages settings screen and as that section's heading. */ esc_html_e( 'Admin language', 'sitepress' ); ?></h3>
				</div>
				<div class="wpml-section-content">
					<form id="icl_admin_language_options" name="icl_admin_language_options" action="">
						<?php wpml_nonce_field_with_id( 'icl_admin_language_options_nonce', '_icl_nonce', 'icl_admin_language_options_nonce' ); ?>
						<?php if ( is_admin() ) : ?>
						<p>
							<label>
								<?php /* translators: Label in front of the dropdown that picks the language of the WordPress admin. The dropdown follows the colon, so keep the trailing space. */ ?>
								<?php _e( 'Default admin language: ', 'sitepress' ); ?>
								<?php $default_language_details = $sitepress->get_language_details( $default_language ); ?>
								<select name="icl_admin_default_language">
									<?php // translators: %s is the display name of the site's default language. ?>
									<option value="_default_"><?php printf( /* translators: First option in the dropdown that picks the language of the WordPress admin: follow the site's own language. %s: the name of that language. */ esc_html__( 'Default language (currently %s)', 'sitepress' ), esc_html( $default_language_details['display_name'] ) ); ?></option>
									<?php foreach ( $all_languages as $al ) : ?>
										<?php if ( $al['active'] ) : ?>
											<option value="<?php echo esc_attr( $al['code'] ); ?>" <?php selected( $sitepress->get_setting( 'admin_default_language' ) == $al['code'] ); ?>>
											<?php
											echo esc_html( $al['display_name'] );
											if ( $sitepress->get_admin_language() != $al['code'] ) {
												echo ' (' . esc_html( $al['native_name'] ) . ')';
											}
											?>
											&nbsp;
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
									<?php foreach ( $all_languages as $al ) : ?>
										<?php if ( ! $al['active'] ) : ?>
											<option value="<?php echo esc_attr( $al['code'] ); ?>" <?php selected( $sitepress->get_setting( 'admin_default_language' ) == $al['code'] ); ?>>
												<?php
												echo esc_html( $al['display_name'] );
												if ( $sitepress->get_admin_language() != $al['code'] ) {
													echo ' (' . esc_html( $al['native_name'] ) . ')';
												}
												?>
												&nbsp;
											</option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</label>
						</p>
						<?php endif; ?>
						<?php /* translators: Note under the admin language dropdown. %s: the address of the user's own profile page, inside the link tag that is already in the text. */ ?>
						<p><?php printf( __( 'Each user can choose the admin language. You can edit your language preferences by visiting your <a href="%s">profile page</a>.', 'sitepress' ), 'profile.php#wpml' ); ?></p>
						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_al"></span>
							<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_html_e( 'Save', 'sitepress' ); ?>" type="submit" />
						</p>
					</form>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( count( $active_languages ) > 1 ): ?>

			<?php  ?>
			<div class="wpml-section wpml-section-hide-languages" id="lang-sec-7" style="display:none">
				<div class="wpml-section-header">
					<h3><?php /* translators: Name of the section that hides languages from visitors, in the menu of the Languages settings screen and as that section's heading. */ esc_html_e( 'Hide languages', 'sitepress' ); ?></h3>
				</div>
				<div class="wpml-section-content">
					<p><?php esc_html_e( 'You can completely hide content in specific languages from visitors and search engines, but still view it yourself. This allows reviewing translations that are in progress.', 'sitepress' ); ?></p>
					<form id="icl_hide_languages" name="icl_hide_languages" action="">
						<?php wpml_nonce_field_with_id( 'icl_hide_languages_nonce', '_icl_nonce', 'icl_hide_languages_nonce' ); ?>
						<?php foreach ( $active_languages as $l ) : ?>
							<?php
							if ( isset( $default_language_details['code'] ) && $l['code'] === $default_language_details['code'] ) {
								continue;}
							?>
							<p>
								<label>
									<input class="wpml-checkbox-native" type="checkbox" name="icl_hidden_languages[]" value="<?php echo esc_attr( $l['code'] ); ?>"
										<?php checked( ! empty( $hidden_languages ) && in_array( $l['code'], $hidden_languages ) ); ?>  />
									<?php echo esc_html( $l['display_name'] ); ?>
								</label>
							</p>
						<?php endforeach; ?>
						<p id="icl_hidden_languages_status">
							<?php
							if ( ! empty( $hidden_languages ) ) {
								if ( 1 == count( $hidden_languages ) ) {
									if ( isset( $active_languages[ $hidden_languages[0] ] ) ) {
										/* translators: Notice saying that one language is kept from visitors. %s: the name of that language. */
										printf( esc_html__( '%s is currently hidden to visitors.', 'sitepress' ), esc_html( $active_languages[ $hidden_languages[0] ]['display_name'] ) );
										$hidden_languages[] = $hidden_languages[0];
									}
								} else {
									$_hlngs = array();
									foreach ( $hidden_languages as $l ) {
										if ( isset( $active_languages[ $l ] ) ) {
											$_hlngs[] = $active_languages[ $l ]['display_name'];
										}
									}
									$hlangs = implode( ', ', $_hlngs );
									/* translators: Notice saying that several languages are kept from visitors. %s: the names of those languages, separated by commas. */
									printf( esc_html__( '%s are currently hidden to visitors.', 'sitepress' ), esc_html( $hlangs ) );
								}

								$hidden_languages = array_unique( $hidden_languages );
								$sitepress->set_setting( 'hidden_languages', $hidden_languages );

								 echo '<p>';
								/* translators: Note shown after languages were hidden from visitors. "its/their" covers one hidden language or several. %s: the address of the user's own profile page, inside the link tag that is already in the text. */
								printf( __( 'You can enable its/their display for yourself, in your <a href="%s">profile page</a>.', 'sitepress' ), 'profile.php#wpml' );
								 echo '</p>';
							} else {
								esc_html_e( 'All languages are currently displayed.', 'sitepress' );
							}
							?>
						</p>
						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_hl"></span>
							<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_attr_e( 'Save', 'sitepress' ); ?>" type="submit" />
						</p>
					</form>
				</div>
			</div>

			<div class="wpml-section wpml-section-ml-themes" id="lang-sec-8">
				<div class="wpml-section-header">
					<h3><?php /* translators: Name of the section about making a theme ready for several languages, in the menu of the Languages settings screen and as that section's heading. */ esc_html_e( 'Make themes work multilingual', 'sitepress' ); ?></h3>
				</div>
				<div class="wpml-section-content">
						<form id="icl_adjust_ids" name="icl_adjust_ids" action="">
							<?php wpml_nonce_field_with_id( 'icl_adjust_ids_nonce', '_icl_nonce', 'icl_adjust_ids_nonce' ); ?>
							<p><?php esc_html_e( 'This feature turns themes into multilingual, without having to edit their PHP files.', 'sitepress' ); ?></p>
							<p>
								<label>
									<input class="wpml-checkbox-native" type="checkbox" value="1" name="icl_adjust_ids" <?php checked( $sitepress->get_setting( 'auto_adjust_ids' ) ); ?> />
									<?php esc_html_e( 'Adjust IDs for multilingual functionality', 'sitepress' ); ?>
								</label>
							</p>
							<p class="buttons-wrap">
								<span class="icl_ajx_response" id="icl_ajx_response_ai"></span>
								<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_attr_e( 'Save', 'sitepress' ); ?>" type="submit" />
							</p>
						</form>
				</div>
			</div>

			<?php
			$redirect_to_chosen_language = (bool) $sitepress->get_setting( 'redirect_to_chosen_language' );
			$chosen_language_days        = WPML_Browser_Redirect::sanitize_chosen_language_days(
				$sitepress->get_setting( 'chosen_language_days', WPML_Browser_Redirect::CHOSEN_LANGUAGE_DAYS_DEFAULT )
			);
			$first_visit_mode            = WPML_Browser_Redirect::get_first_visit_mode( $automatic_redirect, $sitepress->get_setting( 'suggest_browser_language' ) );
			$first_visit_redirects       = 'redirect' === $first_visit_mode;
			$redirect_missing_to_home    = 2 === (int) $automatic_redirect;
			$remember_language_mode      = 'bar' === $first_visit_mode ? 'bar' : 'redirect';
			$redirect_indexing_url       = \WPML\OutboundLinks\OutboundLinks::to(
				'https://wpml.org/documentation/getting-started-guide/language-setup/automatic-redirect-based-on-browser-language/how-browser-language-redirect-affects-google-indexing/',
				array(
					'medium'   => 'settings',
					'campaign' => 'languages',
				)
			);
			$remember_language_sentences = array(
				/* translators: How often the same visitor can be redirected by their browser language. %s: a small field where the number of hours is typed, so the words before and after it stay on the same line. */
				'redirect' => __( 'Redirect each visitor at most once every %s hours', 'sitepress' ),
				/* translators: How long the bar that suggests the page in the visitor's browser language stays hidden after the visitor closes it. %s: a small field where the number of hours is typed, so the words before and after it stay on the same line. */
				'bar'      => __( 'After a visitor closes the bar, hide it for %s hours', 'sitepress' ),
			);
			$remember_language_halves    = array();
			foreach ( $remember_language_sentences as $remember_language_sentence_mode => $remember_language_sentence ) {
				$remember_language_halves[ $remember_language_sentence_mode ] = preg_split( '/%(?:1\$)?s/', $remember_language_sentence, 2 ) + array( '', '' );
			}
			?>
			<div class="wpml-section wpml-section-redirect" id="lang-sec-9">
				<div class="wpml-section-header">
					<h3><?php /* translators: Name of the section about which language visitors see the site in, in the menu of the Languages settings screen and as that section's heading. */ esc_html_e( 'Visitor language', 'sitepress' ); ?></h3>
				</div>
				<div class="wpml-section-content">
					<form id="icl_automatic_redirect" name="icl_automatic_redirect" action="">
						<?php wpml_nonce_field_with_id( 'icl_automatic_redirect_nonce', '_icl_nonce', 'icl_automatic_redirect_nonce' ); ?>

						<h4><?php /* translators: Heading above the setting for visitors who already chose a language in the language switcher. */ esc_html_e( 'Visitors who picked a language before', 'sitepress' ); ?></h4>
						<p>
							<label for="icl_redirect_to_chosen_language">
								<input class="wpml-checkbox-native" type="checkbox" value="1" id="icl_redirect_to_chosen_language" name="icl_redirect_to_chosen_language" <?php checked( $redirect_to_chosen_language ); ?> />
								<?php esc_html_e( 'Show the site in the language they last picked in the language switcher', 'sitepress' ); ?>
							</label>
						</p>
						<p class="js-chosen-language-days"<?php echo $redirect_to_chosen_language ? '' : ' style="display: none;"'; ?>>
							<label for="icl_chosen_language_days">
								<?php
								printf(
									/* translators: How long the language a visitor picked is remembered. %s: a small field where the number of days is typed, so the words before and after it stay on the same line. */
									esc_html__( 'Remember their choice for %s days', 'sitepress' ),
									'<input size="3" type="number" min="1" max="' . (int) WPML_Browser_Redirect::CHOSEN_LANGUAGE_DAYS_MAX . '" step="1" value="' . (int) $chosen_language_days . '" id="icl_chosen_language_days" name="icl_chosen_language_days" />'
								);
								?>
							</label>
						</p>

						<h4><?php /* translators: Heading above the settings for visitors who have not chosen a language in the language switcher yet. */ esc_html_e( "Visitors who haven't picked a language yet", 'sitepress' ); ?></h4>
						<?php
						$visitor_language_legend_style = 'padding: 0; line-height: 1.5;';
						$visitor_language_list_style   = 'margin-bottom: 0;';
						?>
						<fieldset>
							<legend id="icl_first_visit_label" style="<?php echo esc_attr( $visitor_language_legend_style ); ?>"><?php /* translators: Question above the choices of what happens to a visitor whose browser language is not the language of the page they opened. It ends with a colon because the choices follow it. */ esc_html_e( 'When their browser uses a different language than the page:', 'sitepress' ); ?></legend>
							<ul style="<?php echo esc_attr( $visitor_language_list_style ); ?>">
								<li>
									<label for="icl_first_visit_keep">
										<input class="wpml-radio-native" type="radio" value="keep" id="icl_first_visit_keep" name="icl_first_visit" <?php checked( 'keep', $first_visit_mode ); ?> />
										<?php /* translators: Choice for a visitor whose browser uses another language: do not send them anywhere. */ esc_html_e( 'Keep them on the page they opened', 'sitepress' ); ?>
									</label>
								</li>
								<li>
									<label for="icl_first_visit_bar">
										<input class="wpml-radio-native" type="radio" value="bar" id="icl_first_visit_bar" name="icl_first_visit" aria-describedby="icl_first_visit_bar_help" <?php checked( 'bar', $first_visit_mode ); ?> />
										<?php /* translators: Choice for a visitor whose browser uses another language: show a bar on the page with a link to the same page in their language. The page itself does not change. */ esc_html_e( 'Show a bar that offers the page in their language', 'sitepress' ); ?>
									</label>
									<p class="explanation-text" id="icl_first_visit_bar_help">
										<?php /* translators: Help text under the choice that shows a bar offering the page in the visitor's language: the bar does not change the page, so search engines see the same page. */ esc_html_e( "Doesn't affect how Google indexes your site.", 'sitepress' ); ?>
									</p>
								</li>
								<li>
									<label for="icl_first_visit_redirect">
										<input class="wpml-radio-native" type="radio" value="redirect" id="icl_first_visit_redirect" name="icl_first_visit" aria-describedby="icl_first_visit_redirect_help" <?php checked( $first_visit_redirects ); ?> />
										<?php esc_html_e( 'Redirect them automatically to the page in their language', 'sitepress' ); ?>
									</label>
									<p class="explanation-text" id="icl_first_visit_redirect_help">
										<?php esc_html_e( 'Can affect how Google indexes your site.', 'sitepress' ); ?>
										<a href="<?php echo esc_url( $redirect_indexing_url ); ?>" target="_blank" rel="noopener"><?php /* translators: Link text that opens a page on wpml.org explaining the text before it. Verb phrase, imperative. */ esc_html_e( 'Learn more', 'sitepress' ); ?></a>
									</p>
								</li>
							</ul>
						</fieldset>

						<fieldset class="js-redirect-missing"<?php echo $first_visit_redirects ? '' : ' style="display: none;"'; ?>>
							<legend id="icl_redirect_missing_label" style="<?php echo esc_attr( $visitor_language_legend_style ); ?>"><?php /* translators: Question above the choices of where a redirected visitor goes when the page has no translation in their language. It ends with a colon because the choices follow it. */ esc_html_e( "If the page isn't translated:", 'sitepress' ); ?></legend>
							<ul style="<?php echo esc_attr( $visitor_language_list_style ); ?>">
								<li>
									<label for="icl_redirect_missing_stay">
										<input class="wpml-radio-native" type="radio" value="stay" id="icl_redirect_missing_stay" name="icl_redirect_missing" <?php checked( ! $redirect_missing_to_home ); ?> />
										<?php /* translators: Choice for a visitor whose browser uses another language: do not send them anywhere. */ esc_html_e( 'Keep them on the page they opened', 'sitepress' ); ?>
									</label>
								</li>
								<li>
									<label for="icl_redirect_missing_home">
										<input class="wpml-radio-native" type="radio" value="home" id="icl_redirect_missing_home" name="icl_redirect_missing" <?php checked( $redirect_missing_to_home ); ?> />
										<?php esc_html_e( 'Send them to the home page in their language', 'sitepress' ); ?>
									</label>
								</li>
							</ul>
						</fieldset>

						<p class="js-remember-language-hours"<?php echo 'keep' === $first_visit_mode ? ' style="display: none;"' : ''; ?>>
							<label for="icl_remember_language">
								<?php foreach ( $remember_language_halves as $remember_language_halves_mode => $remember_language_half ) : ?>
									<span class="js-remember-language-hours-<?php echo esc_attr( $remember_language_halves_mode ); ?>"<?php echo $remember_language_halves_mode === $remember_language_mode ? '' : ' style="display: none;"'; ?>><?php echo esc_html( $remember_language_half[0] ); ?></span>
								<?php endforeach; ?>
								<input size="2" type="number" min="24" value="<?php echo (int) $sitepress->get_setting( 'remember_language' ); ?>" id="icl_remember_language" name="icl_remember_language" />
								<?php foreach ( $remember_language_halves as $remember_language_halves_mode => $remember_language_half ) : ?>
									<span class="js-remember-language-hours-<?php echo esc_attr( $remember_language_halves_mode ); ?>"<?php echo $remember_language_halves_mode === $remember_language_mode ? '' : ' style="display: none;"'; ?>><?php echo esc_html( $remember_language_half[1] ); ?></span>
								<?php endforeach; ?>
							</label>
						</p>
						<?php
						global $wpml_request_handler;

						if ( ! $wpml_request_handler->get_cookie_lang() ) {
							?>
							<p class="icl_error_text"><?php esc_html_e( "Your browser doesn't seem to be allowing cookies to be set.", 'sitepress' ); ?></p>
							<?php
						}
						?>
						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_ar"></span>
							<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */ esc_attr_e( 'Save', 'sitepress' ); ?>" type="submit" />
						</p>
					</form>
				</div>
			</div>
		<?php endif; ?>



	<?php
	$request_get_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
	do_action( 'icl_extra_options_' . $request_get_page );

	$seo_ui = new WPML_SEO_HeadLangs( $sitepress );
	$seo_ui->render_menu();
	?>


	<?php
	do_action( 'wpml_after_settings', $theme_wpml_config_file );

	do_action( 'wpml_admin_after_wpml_love', $theme_wpml_config_file );

	do_action( 'icl_menu_footer' );
	do_action( 'wpml_menu_footer' );
	?>

</div> <!-- .wrap -->
<?php
$sitepress->save_settings();
