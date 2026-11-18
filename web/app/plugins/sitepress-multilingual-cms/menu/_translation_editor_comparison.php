<?php

$old_jobs_row      = $show_old_jobs_checkbox ?? true;
$table_classes     = [ 't_method__table' ];
$radio_label_texts = [ ICL_TM_TMETHOD_ATE => '', (string) ICL_TM_TMETHOD_EDITOR => '' ];
if ( ! $old_jobs_row ) {
	$table_classes[] = 't_method__table--closed';
	if ( $isATEEditor ) {
		$table_classes[] = 't_method__ate-editor';
	} elseif ( $isClassicEditor ) {
		$table_classes[] = 't_method__classic-editor';
	}
	$radio_label_texts = [
		ICL_TM_TMETHOD_ATE             => $isATEEditor ? __( 'Enabled', 'sitepress' ) : __( 'Choose', 'sitepress' ),
		(string) ICL_TM_TMETHOD_EDITOR => $isClassicEditor ? __( 'Enabled', 'sitepress' ) : __( 'Choose', 'sitepress' ),
	];
}

$editor_features = [
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
						<div class="wpml-section-content-inner">
							<div class="translation-method__table-container">
								<table class="<?php echo esc_attr( implode( ' ', $table_classes ) ); ?>">
									<thead>
									<tr>
										<th></th>
										<th>
											<span class="recommendation-badge ate-recommended"><?php /* translators: Badge next to the setting or action WPML advises: on the media translation and Translation Management settings screens, and in the Translation delivery box. Past participle used as a label: this is what WPML advises. */ esc_html_e( 'Recommended', 'sitepress' ); ?></span>
											<span class="column-heading"><?php /* translators: The name of WPML's translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */ esc_html_e( 'Advanced Translation Editor', 'sitepress' ); ?></span>
										</th>
										<th>
											<span class="recommendation-badge"><?php /* translators: Note on the older translation editor in the table that compares the editors: it is still there but is no longer the one WPML advises. */ esc_html_e( 'Legacy', 'sitepress' ); ?></span>
											<span class="column-heading"><?php /* translators: The name of WPML's older translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */ esc_html_e( 'Classic Translation Editor', 'sitepress' ); ?></span>
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
																										<i aria-label="<?php /* translators: Screen reader name of the tick in the table that compares the translation editors. %s is the name of the feature, as in "Includes Glossary Support". */ echo esc_attr( sprintf( __( 'Includes %s', 'sitepress' ), $editor_feature ) ); ?>" class="otgs-ico otgs-ico-ok"></i>
												</div>
											</td>
											<?php if ( $index < 2 ) { ?>
												<td class="check-td right">
													<div class="check-container">
																												<i aria-label="<?php /* translators: Screen reader name of the tick in the table that compares the translation editors. %s is the name of the feature, as in "Includes Glossary Support". */ echo esc_attr( sprintf( __( 'Includes %s', 'sitepress' ), $editor_feature ) ); ?>" class="otgs-ico otgs-ico-ok"></i>
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
													type="radio" name="t_method" value="<?php echo esc_attr( ICL_TM_TMETHOD_ATE ); ?>"
													<?php if ( $isATEEditor ) : ?>
														checked="checked"
													<?php endif; ?>
													<?php foreach ( $t_method_radio_attributes[ ICL_TM_TMETHOD_ATE ] ?? [] as $attribute_name => $attribute_value ) : ?>
														<?php echo esc_attr( $attribute_name ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
													<?php endforeach; ?>
												/>
												<span class="wpml-radio-label"><?php echo esc_html( $radio_label_texts[ ICL_TM_TMETHOD_ATE ] ); ?></span>
											</label>
										</td>
										<td class="choose-method right">
											<label class="wpml-radio wpml-radio-green">
												<input
													type="radio" name="t_method" value="<?php echo esc_attr( (string) ICL_TM_TMETHOD_EDITOR ); ?>"
													<?php if ( $isClassicEditor ) : ?>
														checked="checked"
													<?php endif; ?>
													<?php foreach ( $t_method_radio_attributes[ ICL_TM_TMETHOD_EDITOR ] ?? [] as $attribute_name => $attribute_value ) : ?>
														<?php echo esc_attr( $attribute_name ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
													<?php endforeach; ?>
												/>
												<span class="wpml-radio-label"><?php echo esc_html( $radio_label_texts[ (string) ICL_TM_TMETHOD_EDITOR ] ); ?></span>
											</label>
										</td>
									</tr>
									<?php if ( $old_jobs_row ) : ?>
									<tr>
										<td></td>
										<td class="old-translations">
												<label class="wpml-checkbox">
													<?php
											$default_editor_for_old_jobs = get_option( WPML_TM_Old_Jobs_Editor::OPTION_NAME, null );
													?>
													<input disabled="disabled" name="wpml-old-jobs-editor" type="checkbox" value="<?php echo esc_attr( WPML_TM_Editors::ATE ); ?>" <?php checked( $default_editor_for_old_jobs === WPML_TM_Editors::ATE ); ?> />
													<span><?php esc_html_e( 'Use also for old translations created with the classic editor', 'sitepress' ); ?></span>
												</label>
										</td>
										<td></td>
									</tr>
									<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
