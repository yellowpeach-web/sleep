<?php

use WPML\API\Settings;
use WPML\DataSharing\DataSharingSection;
use WPML\DocPage;
use WPML\TM\Menu\TranslationMethod\TranslationMethodSettings;
use WPML\LIB\WP\User;

class WPML_TM_Menus_Settings extends WPML_TM_Menus {

	private $translate_link_targets_ui;

	private $end_user_feature_enabled;

	private $mcsetup_sections = array();

	public function init() {
		$this->init_navigation_links();
	}

	private function init_navigation_links() {
		global $sitepress, $iclTranslationManagement;
		$is_admin = current_user_can( 'manage_options' );

		/* translators: Name of a section of the Translation Management settings: which editor is used to translate content. */
		$this->mcsetup_sections['ml-content-setup-sec-1'] = esc_html__( 'Translation Editor', 'sitepress' );

		if ( $is_admin ) {
			$this->mcsetup_sections['ml-content-setup-sec-2'] = esc_html__( 'Posts and pages synchronization', 'sitepress' );
			$this->mcsetup_sections['ml-content-setup-sec-3'] = esc_html__( 'Translated documents options', 'sitepress' );

			$this->mcsetup_sections['ml-content-setup-sec-wp-login'] = esc_html__( 'Login and registration pages', 'sitepress' );

			if ( wpml_is_st_loaded() ) {
				$this->mcsetup_sections['ml-content-setup-sec-4'] = esc_html__( 'Custom posts slug translation options', 'sitepress' );
			}

			if ( TranslationProxy::is_current_service_active_and_authenticated() ) {
				$this->mcsetup_sections['ml-content-setup-sec-5'] = esc_html__( 'Translation pickup mode', 'sitepress' );
			}
		}

		$this->mcsetup_sections['ml-content-setup-sec-5-1'] = esc_html__( 'XLIFF file options', 'sitepress' );

		if ( $is_admin ) {
			$this->mcsetup_sections['ml-content-setup-sec-cf']  = esc_html__( 'Custom Fields Translation', 'sitepress' );
			$this->mcsetup_sections['ml-content-setup-sec-tcf'] = esc_html__( 'Custom Term Meta Translation', 'sitepress' );

			$custom_posts     = array();
			$this->post_types = $sitepress->get_translatable_documents( true );

			foreach ( $this->post_types as $k => $v ) {
				$custom_posts[ $k ] = $v;
			}

			global $wp_taxonomies;
			$custom_taxonomies = array_diff(
                array_keys( (array) $wp_taxonomies ),
                array(
					'post_tag',
					'category',
					'nav_menu',
					'link_category',
					'post_format',
                )
            );

			if ( $custom_posts ) {
				$this->mcsetup_sections['ml-content-setup-sec-7'] = esc_html__( 'Post Types Translation', 'sitepress' );
			}

			if ( $custom_taxonomies ) {
				/* translators: Name of a section of the Translation Management settings, where the names of categories, tags and other groupings are translated; also the link text inside the sentence "Set it in Taxonomies Translation." It is a section name, so it keeps its capitals. */
				$this->mcsetup_sections['ml-content-setup-sec-8'] = esc_html__( 'Taxonomies Translation', 'sitepress' );
			}

			if ( ! empty( $iclTranslationManagement->admin_texts_to_translate ) && function_exists( 'icl_register_string' ) ) {
				$this->mcsetup_sections['ml-content-setup-sec-9'] = esc_html__( 'Admin Strings to Translate', 'sitepress' );
			}
			$this->mcsetup_sections[ DataSharingSection::SECTION_ID ] = esc_html( DataSharingSection::heading() );
		}

		$this->get_translate_link_targets_ui()->add_hooks();

		$this->mcsetup_sections = apply_filters( 'wpml_mcsetup_navigation_links', $this->mcsetup_sections, $sitepress, $iclTranslationManagement );
	}

	protected function render_main() {
		?>
		<div class="wrap">
			<h1><?php echo /* translators: Title of the settings screen, and the item in the WPML menu that opens it. */ esc_html__( 'Settings', 'sitepress' ); ?></h1>

			<?php
			do_action( 'icl_tm_messages' );
			$this->build_tab_items();
			$this->render_items();
			?>
		</div>
		<?php
	}

	protected function build_tab_items() {
		$this->build_mcs_item();
		$this->build_translation_notifications_item();

		$this->tab_items = apply_filters( 'wpml_tm_tab_items', $this->tab_items );
	}

	private function build_mcs_item() {
		global $sitepress;

		$this->tab_items['mcsetup']['caption'] = esc_html__( 'Multilingual Content Setup', 'sitepress' );
		$translate_link_targets                = new WPML_Translate_Link_Target_Global_State( $sitepress );
		if ( $translate_link_targets->is_rescan_required() ) {
			$this->tab_items['mcsetup']['caption'] = '<i class="otgs-ico-warning"></i>' . esc_html( $this->tab_items['mcsetup']['caption'] );
		}
		$this->tab_items['mcsetup']['callback']         = [ $this, 'build_content_mcs' ];
		$this->tab_items['mcsetup']['current_user_can'] = [
			User::CAP_MANAGE_TRANSLATIONS,
			User::CAP_ADMINISTRATOR,
		];
	}

	private function build_translation_notifications_item() {
		$this->tab_items['notifications'] = [
			/* translators: Name of a section of the Translation Management settings: the emails WPML sends about translation work. */
			'caption'          => esc_html__( 'Translation Notifications', 'sitepress' ),
			'current_user_can' => [ User::CAP_ADMINISTRATOR, User::CAP_MANAGE_TRANSLATIONS ],
			'callback'         => [ $this, 'build_content_translation_notifications' ],
		];
	}

	public function build_content_mcs() {
		global $sitepress, $sitepress_settings, $iclTranslationManagement;

		$translate_link_targets = new WPML_Translate_Link_Target_Global_State( $sitepress );
		if ( $translate_link_targets->is_rescan_required() ) {
			?>
			<div class="update-nag scan-links-notice otgs-notice notice info" role="alert">
				<div class="ant-alert-content">
					<p>
						<?php
						echo esc_html__(
							'New translations detected. Scan your content to update internal links so they point to the correct translated pages and posts.',
							'sitepress'
						);
						?>
					</p>
					<span class="ant-alert-description"><?php echo $this->get_navigation_link( $this->get_translate_link_targets_ui()->get_id() ); ?></span>
				</div>
			</div>
			<?php
		}

		$this->render_mcsetup_navigation_links();

		if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-1' ) ) :
			?>
			<div class="wpml-section" id="ml-content-setup-sec-1">
				<?php
				$doc_translation_method = Settings::pathOr(
                    ICL_TM_TMETHOD_MANUAL,
                    [
						'translation-management',
						'doc_translation_method',
					]
                );
				$isClassicEditor        = (string) ICL_TM_TMETHOD_EDITOR === (string) $doc_translation_method;
				$isATEEditor            = (string) ICL_TM_TMETHOD_ATE === (string) $doc_translation_method;
				$editor_features        = [
					__( 'Side-by-side editing', 'sitepress' ),
					__( 'Unique designs for translations', 'sitepress' ),
					/* translators: Name of a feature of the translation editor, in the table that compares the editors: a list of words with a fixed translation. */
					__( 'Glossary Support', 'sitepress' ),
					/* translators: Name of a feature of the translation editor, in the table that compares the editors: translation done by a machine. */
					__( 'Automatic Translation', 'sitepress' ),
					__( 'Use your free automatic translation quota', 'sitepress' ),
					/* translators: Name of a feature of the translation editor, in the table that compares the editors: it checks the spelling of the translation. */
					__( 'Spell Checker', 'sitepress' ),
					__( 'Safe HTML editing', 'sitepress' ),
					/* translators: Name of a feature of the translation editor, in the table that compares the editors: it remembers earlier translations and offers them again. */
					__( 'Translation Memory', 'sitepress' ),
				];

				?>

				<div class="wpml-section-content translation-method-content">

					<form id="icl_doc_translation_method" name="icl_doc_translation_method" action="">
						<?php wpml_nonce_field_with_id( 'icl_doc_translation_method_nonce', '_icl_nonce', 'icl_doc_translation_method_nonce' ); ?>

						<div class="wpml-section-content-inner">
							<div class="translation-method__table-container">
								<table class="t_method__table">
									<thead>
									<tr>
										<th></th>
										<th>
											<span class="recommendation-badge ate-recommended"><?php /* translators: Badge next to the setting WPML advises, on the media translation and Translation Management settings screens. Past participle used as a label: this is what WPML advises. */ esc_html_e( 'Recommended', 'sitepress' ); ?></span>
											<span class="column-heading"><?php esc_html_e( 'Advanced Translation Editor', 'sitepress' ); ?></span>
										</th>
										<th>
											<span class="recommendation-badge"><?php /* translators: Note on the older translation editor in the table that compares the editors: it is still there but is no longer the one WPML advises. */ esc_html_e( 'Legacy', 'sitepress' ); ?></span>
											<span class="column-heading"><?php esc_html_e( 'Classic Translation Editor', 'sitepress' ); ?></span>
										</th>
									</tr>
									</thead>
									<tbody>
									<?php
									foreach ( $editor_features as $index => $editor_feature ) {
										?>
										<tr class="
                                        <?php
                                        if ( $editor_feature === 'Translation Memory' ) :
											echo 'translation-memory-row';
endif;
										?>
                                        ">
											<th scope="row" class="row-heading"><span><?php echo esc_html( $editor_feature ); ?></span></th>
											<td class="check-td left">
												<div class="check-container">
													<?php /* translators: Screen reader name of the tick in the table that compares the translation editors; the name of the feature is added after it, as in "Includes Glossary Support". Keep the space at the end. */ ?>
													<i aria-label="<?php esc_html_e( 'Includes ' . $editor_feature, 'sitepress' ); ?>" class="otgs-ico otgs-ico-ok"></i>
												</div>
											</td>
											<?php if ( $index < 2 ) { ?>
												<td class="check-td right">
													<div class="check-container">
														<?php /* translators: Screen reader name of the tick in the table that compares the translation editors; the name of the feature is added after it, as in "Includes Glossary Support". Keep the space at the end. */ ?>
														<i aria-label="<?php esc_html_e( 'Includes ' . $editor_feature, 'sitepress' ); ?>" class="otgs-ico otgs-ico-ok"></i>
													</div>
												</td>
											<?php } else { ?>
												<td class="right"><div class="check-container"></div></td>
											<?php } ?>
										</tr>
									<?php } ?>
									<tr>
										<td></td>
										<td class="choose-method left">
											<label class="wpml-radio wpml-radio-green">
												<input
													type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_ATE; ?>"
													<?php if ( $isATEEditor ) : ?>
														checked="checked"
													<?php endif; ?>
												/>
												<span class="wpml-radio-label"></span>
											</label>
										</td>
										<td class="choose-method right">
											<label class="wpml-radio wpml-radio-green">
												<input
													type="radio" name="t_method" value="<?php echo ICL_TM_TMETHOD_EDITOR; ?>"
													<?php if ( $isClassicEditor ) : ?>
														checked="checked"
													<?php endif; ?>
												/>
												<span class="wpml-radio-label"></span>
											</label>
										</td>
									</tr>
									<tr>
										<td></td>
										<td class="old-translations">
												<label class="wpml-checkbox">
													<?php
											$default_editor_for_old_jobs = get_option( WPML_TM_Old_Jobs_Editor::OPTION_NAME, null );
											$different_translation_designs_link = \WPML\OutboundLinks\OutboundLinks::to(
														'https://wpml.org/documentation/translating-your-contents/using-different-translation-editors-for-different-pages/',
														array(
															'medium'   => 'settings',
															'campaign' => 'translation-editor',
												)
											);
													?>
													<input disabled="disabled" name="wpml-old-jobs-editor" type="checkbox" value="<?php echo WPML_TM_Editors::ATE; ?>" <?php checked( $default_editor_for_old_jobs === WPML_TM_Editors::ATE ); ?> />
													<span><?php esc_html_e( 'Use also for old translations created with the classic editor', 'sitepress' ); ?></span>
												</label>
										</td>
										<td></td>
									</tr>
									</tbody>
								</table>
							</div>
						</div>

						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_dtm"> </span>
							<input type="submit" class="button-primary" style="display: none;"
									value="<?php echo /* translators: Button label that keeps what was entered. Verb, imperative. */ esc_html__( 'Save', 'sitepress' ); ?>"/>
						</p>



						<div class="wpml-section-content-inner t_editor-faqs">
							<h3>
								<?php

								/* translators: heading shown for selecting the editor to use when updating content that was created with WPML's Classic Translation Editor */
								esc_html_e( 'Translation Editor FAQ', 'sitepress' );

								?>
							</h3>
							<div class="faqs">

								<div class="faq">
									<h4>
										<?php esc_html_e( 'Can I have completely different designs for translations?', 'sitepress' ); ?>
									</h4>
									<p>
										<?php
										/* translators: Answer in the questions and answers of the Translation Management settings. %1$s: the opening tag of a link to a page on wpml.org, %2$s: its closing tag. */
										printf( esc_html__( 'Yes, WPML allows you to use %1$scompletely different designs for translations%2$s.', 'sitepress' ), '<a href="' . esc_url( $different_translation_designs_link ) . '" target="_blank">', '</a>' );
										?>
									</p>
								</div>
								<div class="faq">
									<h4>
										<?php echo wpml_bold_names( __( 'Can I use my free automatic translation quota with the <b>Classic Translation Editor</b>?', 'sitepress' ) ); ?>
									</h4>
									<p>
										<?php esc_html_e( 'No. Automatic translation is only available via WPML’s Advanced Translator Editor, so you can only use your free quota using it.', 'sitepress' ); ?>
									</p>
								</div>
								<div class="faq">
									<h4>
										<?php echo wpml_bold_names( __( 'When should I use WPML’s <b>Classic Translation Editor</b>?', 'sitepress' ) ); ?>
									</h4>
									<p>
										<?php echo wpml_bold_names( __( 'We maintain WPML’s <b>Classic Translation Editor</b> as part of WPML for backward compatibility. You should only use it if you’ve started with the Classic Editor and are concerned about losing the translation history.', 'sitepress' ) ); ?>
									</p>
								</div>
							</div>
						</div>

						<?php do_action( 'wpml_doc_translation_method_below' ); ?>

					</form>
				</div>
				<!-- .wpml-section-content -->

				<div
					class="wpml-js-warning-modal-ate-for-old-translations hidden"
					data-ok-btn-txt="<?php esc_attr_e( 'Yes, use Advanced Translation Editor for existing content', 'sitepress' ); ?>"
					data-close-btn-txt="<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */ esc_attr_e( 'Cancel', 'sitepress' ); ?>"
				>
					<div class="wpml-modal-content">
						<div class="wpml-icon-wrap">
							<i class="otgs-ico otgs-ico-wpml-string-translation"></i>
						</div>
						<h4><?php echo wpml_bold_names( __( 'You are about to use <b>Advanced Translation Editor</b> for your existing translations', 'sitepress' ) ); ?></h4>
						<p>
							<?php
							echo wpml_bold_names(
								__(
									'Some of your content was translated in the <b>Classic Translation Editor</b>. When you retranslate it, WPML will use those existing translations as a base for the automatic translation, so the tone and style you established are preserved.',
									'sitepress'
								)
							);
							?>
						</p>
					</div>
				</div>

			</div><!-- #ml-content-setup-sec-1 -->
		<?php endif; ?>

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-2' ) ) : ?>
			<?php include ICL_PLUGIN_PATH . '/menu/_posts_sync_options.php'; ?>
		<?php endif; ?><!-- #ml-content-setup-sec-2 -->

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-3' ) ) : ?>
			<div class="wpml-section" id="ml-content-setup-sec-3">

				<div class="wpml-section-content">

					<form name="icl_tdo_options" id="icl_tdo_options" action="">
						<?php
						wp_nonce_field(
							'wpml-translated-document-options-nonce',
							WPML_TM_Options_Ajax::NONCE_TRANSLATED_DOCUMENT
						);
						?>

						<div class="wpml-section-content-inner">
							<h4>
								<?php echo esc_html__( 'When you receive completed translations', 'sitepress' ); ?>
							</h4>
							<ul>
								<li>
									<label>
										<input class="wpml-radio-native" type="radio" name="icl_translated_document_status" value="1"
											<?php
											checked(
												(bool) icl_get_setting( 'translated_document_status' ),
												true
											);
											?>
										/>
										<?php
										echo esc_html__(
											'Publish the translated post when original is also published (default)',
											'sitepress'
										)
										?>
									</label>
								</li>
								<li>
									<label>
										<input class="wpml-radio-native" type="radio" name="icl_translated_document_status" value="0"
											<?php
											checked(
												(bool) icl_get_setting( 'translated_document_status' ),
												false
											);
											?>
										/>
										<?php echo esc_html__( 'Save the translated post as a draft', 'sitepress' ); ?>
									</label>
								</li>
							</ul>
							<p class="explanation-text">
								<?php
								echo esc_html__(
									'Choose if translations should be published when received. Note: If Publish is selected, the translation will only be published if the original document is published when the translation is received.',
									'sitepress'
								)
								?>
							</p>
						</div>

						<div class="wpml-section-content-inner">
							<h4>
								<?php echo esc_html__( 'When you change the status of the original post', 'sitepress' ); ?>
							</h4>
							<ul>
								<li>
									<label>
										<input class="wpml-radio-native" type="radio" name="icl_translated_document_status_sync" value="1"
											<?php
											checked(
												(bool) icl_get_setting( 'translated_document_status_sync' ),
												true
											);
											?>
										/>
										<?php echo esc_html__( 'Translations get the same status as the original', 'sitepress' ); ?>
									</label>
								</li>
								<li>
									<label>
										<input class="wpml-radio-native" type="radio" name="icl_translated_document_status_sync" value="0"
											<?php
											checked(
												(bool) icl_get_setting( 'translated_document_status_sync' ),
												false
											);
											?>
										/>
										<?php
										echo esc_html__(
											'Translations keep their own status',
											'sitepress'
										)
										?>
									</label>
								</li>
							</ul>
							<p class="explanation-text">
								<?php
								echo esc_html__(
									'Translations follow when you publish, schedule, unpublish or make the original private. Translations waiting for review stay unpublished until you accept them. Translations in the trash do not change.',
									'sitepress'
								)
								?>
							</p>
						</div>

						<div class="wpml-section-content-inner">
							<h4>
								<?php echo /* translators: Heading of the setting that says how the address of a translated page is made, in the Translation Management settings. */ esc_html__( 'Page URL', 'sitepress' ); ?>
							</h4>
							<?php
							$change_slug_manually_link = \WPML\OutboundLinks\OutboundLinks::to(
								'https://wpml.org/documentation/getting-started-guide/translating-page-slugs/',
								array(
									'medium'   => 'settings',
									'campaign' => 'languages',
									'content'  => 'page-url-change-slug',
								)
							);
							?>
							<ul class="wpml-page-url-options">
								<li>
									<label><input class="wpml-radio-native" type="radio" name="icl_translated_document_page_url"
													value="auto-generate"
											<?php
											if ( empty( $sitepress_settings['translated_document_page_url'] )
												|| $sitepress_settings['translated_document_page_url']
												=== 'auto-generate' ) :

												?>
												checked="checked"<?php endif; ?> />
										<?php
										/* translators: Page URL option: the slug of a translation is made from its title once, when the translation is created. */
										echo esc_html__(
											'Auto-generate once from the title (WordPress default)',
											'sitepress'
										)
										?>
									</label>
									<p class="description">
										<?php
										/* translators: Under the "Auto-generate once" Page URL option: what happens to the slug later. */
										echo esc_html__( "Later title changes don't update the slug — best for SEO.", 'sitepress' );
										?>
										<a href="<?php echo esc_url( $change_slug_manually_link ); ?>" target="_blank" rel="noopener">
											<?php
											/* translators: Link under the "Auto-generate once" Page URL option, to the documentation page on editing a slug by hand. */
											echo esc_html__( 'Learn how to change a slug manually', 'sitepress' );
											?>
										</a>
									</p>
								</li>
								<li>
									<label><input class="wpml-radio-native" type="radio" name="icl_translated_document_page_url"
										value="force-generate"
											<?php
											if ( 'force-generate' === $sitepress_settings['translated_document_page_url'] ) :

												?>
												checked="checked"<?php endif; ?> />
										<?php
										/* translators: Page URL option: the slug of a translation is made from its title every time the title changes. */
										echo esc_html__(
											'Always auto-generate from the title',
											'sitepress'
										)
										?>
									</label>
									<p class="description">
										<?php
										/* translators: Under the "Always auto-generate" Page URL option: what happens to slugs that already exist. */
										echo esc_html__( 'Every title change rewrites the slug, also on existing translations.', 'sitepress' );
										?>
									</p>
								</li>
								<li>
									<label><input class="wpml-radio-native" type="radio" name="icl_translated_document_page_url" value="translate"
											<?php
											if ( $sitepress_settings['translated_document_page_url']
												=== 'translate' ) :

												?>
												checked="checked"<?php endif; ?> />
										<?php
										/* translators: Page URL option: the slug is a field of the translation, translated in the translation editor. */
										echo esc_html__(
											'Translate the slug in the translation editor',
											'sitepress'
										)
										?>
									</label>
									<p class="description">
										<?php
										/* translators: Under the "Translate the slug" Page URL option: what automatic translation does with the slug. */
										echo esc_html__( "The slug is a field of its own in the translation editor. Automatic translation keeps the original's slug. Translate it by hand in the editor.", 'sitepress' );
										?>
									</p>
								</li>
								<li>
									<label><input class="wpml-radio-native" type="radio" name="icl_translated_document_page_url"
													value="copy-encoded"
											<?php
											if ( $sitepress_settings['translated_document_page_url']
												=== 'copy-encoded' ) :

												?>
												checked="checked"<?php endif; ?> />
										<?php
										/* translators: Page URL option: languages whose URLs are encoded (non-Latin scripts) get the original's slug. */
										echo esc_html__(
											'Copy the original slug for languages with encoded URLs',
											'sitepress'
										)
										?>
									</label>
									<p class="description">
										<?php
										/* translators: Under the "Copy the original slug" Page URL option: what the other languages get. */
										echo esc_html__( 'Languages with encoded (non-Latin) URLs keep the original slug. Every other language auto-generates it from the title.', 'sitepress' );
										?>
									</p>
								</li>
							</ul>
						</div>

						<div class="wpml-section-content-inner">
							<h4>
								<?php echo /* translators: Heading above the list of taxonomies that are being translated, in the Translation Management settings. */ esc_html__( 'Translated taxonomies', 'sitepress' ); ?>
							</h4>

							<p id="tm_block_retranslating_terms">
								<label>
									<input
										class="wpml-checkbox-native"
										name="tm_block_retranslating_terms"
										value="1"
										<?php checked( wpml_get_setting( 'tm_block_retranslating_terms' ), '1' ); ?>
										type="checkbox"
									/>
									<?php echo esc_html__( "Don't show translated taxonomies in Translation Editor", 'sitepress' ); ?>
								</label>
							</p>
						</div>

						<div class="wpml-section-content-inner">
							<p class="buttons-wrap">
								<span class="icl_ajx_response" id="icl_ajx_response_tdo"> </span>
								<input id="js-translated_document-options-btn" type="button" class="button-primary wpml-button base-btn"
										value="
								<?php
										/* translators: Button label that keeps what was entered. Verb, imperative. */
										echo esc_attr__(
											'Save',
											'sitepress'
										)
								?>
																																					"/>
							</p>
						</div>

					</form>
				</div>
				<!-- .wpml-section-content -->
			</div><!-- #ml-content-setup-sec-3 -->
		<?php endif; ?>

		<?php
		if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-wp-login' ) ) {
			include ICL_PLUGIN_PATH . '/menu/_login_translation_options.php';
		}
		?>
		<!-- #ml-content-setup-sec-wp-login -->

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-4' ) ) : ?>
			<?php include WPML_ST_PATH . '/menu/_slug-translation-options.php'; ?><!-- #ml-content-setup-sec-4 -->
		<?php endif; ?>

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-5' ) ) : ?>
			<div class="wpml-section" id="ml-content-setup-sec-5">

				<div class="wpml-section-header">
					<h3><?php echo esc_html__( 'Translation pickup mode', 'sitepress' ); ?></h3>
				</div>

				<div class="wpml-section-content">

					<form id="icl_translation_pickup_mode" name="icl_translation_pickup_mode" action="">
						<?php
						wp_nonce_field(
							'wpml_save_translation_pickup_mode',
							WPML_TM_Pickup_Mode_Ajax::NONCE_PICKUP_MODE
						)
						?>

						<p>
							<?php
							echo esc_html__(
								'How should the site receive completed translations from Translation Service?',
								'sitepress'
							);
							?>
						</p>

						<p>
							<label>
								<input class="wpml-radio-native" type="radio" name="icl_translation_pickup_method"
										value="<?php echo ICL_PRO_TRANSLATION_PICKUP_XMLRPC; ?>"
									<?php
									if ( $sitepress_settings['translation_pickup_method']
										=== ICL_PRO_TRANSLATION_PICKUP_XMLRPC ) :

										?>
										checked="checked"<?php endif ?>/>
								<?php
								echo esc_html__(
									'Translation Service will deliver translations automatically using XML-RPC',
									'sitepress'
								);
								?>
							</label>
						</p>

						<p>
							<label>
								<input class="wpml-radio-native" type="radio" name="icl_translation_pickup_method"
										value="<?php echo ICL_PRO_TRANSLATION_PICKUP_POLLING; ?>"
									<?php
									if ( $sitepress_settings['translation_pickup_method']
										=== ICL_PRO_TRANSLATION_PICKUP_POLLING ) :

										?>
										checked="checked"<?php endif; ?> />
								<?php
								echo esc_html__(
									'The site will fetch translations manually',
									'sitepress'
								);
								?>
							</label>
						</p>


						<p class="buttons-wrap">
							<span class="icl_ajx_response" id="icl_ajx_response_tpm"> </span>
							<input
								id="translation-pickup-mode"
								class="button-primary wpml-button base-btn"
								name="save"
								value="<?php echo /* translators: Button label that keeps what was entered. Verb, imperative. */ esc_attr__( 'Save', 'sitepress' ); ?>"
								type="button"
							/>
						</p>

						<?php
						$this->build_content_dashboard_fetch_translations_box();
						?>
					</form>

					<?php do_action( 'wpml_tm_mcs_translation_pickup_mode' ); ?>

				</div>
				<!-- .wpml-section-content -->
			</div><!-- #ml-content-setup-sec-5 -->
		<?php endif; ?>

		<?php if ( defined( 'WPML_TM_VERSION' ) && $this->should_show_mcsetup_section( 'ml-content-setup-sec-5-1' ) ) : ?>
			<?php /* XLIFF options live in the TM module; on a blog license (TM not loaded, WPML_TM_VERSION undefined) there is nothing to include (wpmldev-7163). */ ?>
			<?php include WPML_TM_PATH . '/menu/xliff-options.php'; ?><!-- #ml-content-setup-sec-5-1 -->
		<?php endif; ?>

		<?php $this->build_content_mcs_custom_fields(); ?>

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-7' ) ) : ?>
			<?php include ICL_PLUGIN_PATH . '/menu/_custom_types_translation.php'; ?><!-- #ml-content-setup-sec-7 -->
		<?php endif; ?>

		<?php if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-9' ) ) : ?>
			<div class="wpml-section" id="ml-content-setup-sec-9">

				<div class="wpml-section-header">
					<h3><?php echo esc_html__( 'Admin Strings to Translate', 'sitepress' ); ?></h3>
				</div>

				<div class="wpml-section-content">
					<table class="widefat">
						<thead>
						<tr>
							<th colspan="3">
								<?php echo /* translators: Heading of the section about the texts of the WordPress admin, in the Translation Management settings. */ esc_html__( 'Admin Strings', 'sitepress' ); ?>
							</th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td>
								<?php
								foreach (
									$iclTranslationManagement->admin_texts_to_translate as $option_name =>
									$option_value
								) {
									$iclTranslationManagement->render_option_writes( $option_name, $option_value );
								}
								?>
								<br/>

								href="
								<?php
								echo admin_url(
									'admin.php?page='
									. WPML_ST_FOLDER
									. '/menu/string-translation.php'
								)
								?>
								">
								<?php
								echo esc_html__(
									'Edit translatable strings',
									'sitepress'
								)
								?>
								</a>
								</p>
							</td>
						</tr>
						</tbody>
					</table>

				</div>
				<!-- .wpml-section-content -->

			</div><!-- #ml-content-setup-sec-9 -->
		<?php endif; ?>

		<?php if ( $this->should_show_mcsetup_section( $this->get_translate_link_targets_ui()->get_id() ) ) : ?>
			<?php echo $this->get_translate_link_targets_ui()->render(); ?><!-- #ml-content-setup-sec-links-target -->
		<?php endif; ?>

		<?php if ( $this->should_show_mcsetup_section( DataSharingSection::SECTION_ID ) ) : ?>
			<?php
			DataSharingSection::render();
			?>
		<?php endif; ?>

		<?php
		wp_enqueue_script( 'wpml-tm-mcs' );
		wp_enqueue_script( 'wpml-tm-mcs-translate-link-targets' );
	}

	private function build_content_mcs_custom_fields() {
		global $wpdb;

		$factory = new WPML_TM_MCS_Custom_Field_Settings_Menu_Factory();

		if ( $this->should_show_mcsetup_section( 'ml-content-setup-sec-cf' ) ) {
			$menu_item_posts = $factory->create_post();
			$menu_item_posts->init_data();
			echo $menu_item_posts->render();
		}

		if ( ! empty( $wpdb->termmeta ) && $this->should_show_mcsetup_section( 'ml-content-setup-sec-tcf' ) ) {
			$menu_item_terms = $factory->create_term();
			$menu_item_terms->init_data();
			echo $menu_item_terms->render();
		}
	}

	public function build_content_translation_notifications() {
		?>
		<style id="wpml-translation-notifications-card-style">
			/*
			 * M4 follow-up (2026-05-21). The `translation-notifications.css`
			 * bundle is gated by `?sm=notifications` (legacy URL only) and
			 * never reaches the new-IA `?section=translators` page where
			 * the consolidated card actually lives, so the chrome rules
			 * ship inline next to the markup.
			 *
			 * `#translation-notifications` (id, specificity 100) beats
			 * `.wpml-section:first-of-type` and `:last-of-type`
			 * (specificity 20) — no `!important` needed. The card is BOTH
			 * first AND last `.wpml-section` inside the `<form>`, so the
			 * default `.wpml-section` rules strip its `margin-top` and
			 * border respectively; we restore both.
			 */
			#translation-notifications {
				margin-top: 30px;
				border: 1px solid #ededed;
			}

			/* Light divider between the translator + manager sub-blocks,
				mirroring `border-t border-gray-100 pt-5` in
				`New/translators.html`. */
			#translation-notifications .wpml-notifications-subsection + .wpml-notifications-subsection {
				margin-top: 20px;
				padding-top: 20px;
				border-top: 1px solid #f3f4f6;
			}

			#translation-notifications .wpml-notifications-subsection h4 {
				margin-top: 0;
			}

			/* Save button sits at the bottom of the card with a matching
				divider above so it reads as a footer separator. */
			#translation-notifications .submit {
				margin-top: 20px;
				padding-top: 20px;
				border-top: 1px solid #f3f4f6;
			}
		</style>

		<form name="translation-notifications" id="translation-notifications-form" action="">
			<?php
			do_action( 'wpml_tm_translation_notification_setting_after' );

			wp_nonce_field( 'save_notification_settings_nonce', 'save_notification_settings_nonce' );
			?>
		</form>

		<?php
	}

	protected function get_page_slug() {
		return WPML_Translation_Management::PAGE_SLUG_SETTINGS;
	}

	protected function get_default_tab() {
		return 'mcsetup';
	}

	private function render_mcsetup_navigation_links() {
		echo '<ul class="wpml-navigation-links js-wpml-navigation-links">';

		foreach ( $this->mcsetup_sections as $anchor => $title ) {
			echo '<li>' . $this->get_navigation_link( $anchor ) . '</li>';
		}

		echo '</ul>';
	}

	private function get_navigation_link( $anchor ) {
		if ( array_key_exists( $anchor, $this->mcsetup_sections ) ) {
			return '<a href="#' . $anchor . '">' . $this->mcsetup_sections[ $anchor ] . '</a>';
		}
	}

	private function should_show_mcsetup_section( $anchor ) {
		return array_key_exists( $anchor, $this->mcsetup_sections );
	}

	private function get_translate_link_targets_ui() {
		global $sitepress, $wpdb, $ICL_Pro_Translation;

		if ( ! $this->translate_link_targets_ui ) {
			$this->translate_link_targets_ui = new WPML_Translate_Link_Targets_UI(
				__( 'Update internal links', 'sitepress' ),
				$wpdb,
				$sitepress,
				$ICL_Pro_Translation
			);
		}

		return $this->translate_link_targets_ui;
	}
}
