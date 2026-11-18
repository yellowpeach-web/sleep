<?php
?>
				<div
					class="wpml-js-warning-modal-ate-for-old-translations hidden"
					data-ok-btn-txt="<?php esc_attr_e( 'Yes, use Advanced Translation Editor for existing content', 'sitepress' ); ?>"
					data-close-btn-txt="<?php /* translators: Button label that closes a dialog without doing anything, or stops what is going on, for example in the dialog "Translate with PTC?" (keep the previous choice). Verb, imperative, not the noun "a cancellation". */ esc_attr_e( 'Cancel', 'sitepress' ); ?>"
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
