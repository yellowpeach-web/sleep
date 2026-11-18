<?php

$different_translation_designs_link = \WPML\OutboundLinks\OutboundLinks::to(
	'https://wpml.org/documentation/translating-your-contents/using-different-translation-editors-for-different-pages/',
	array(
		'medium'   => 'settings',
		'campaign' => 'translation-editor',
	)
);
?>
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
