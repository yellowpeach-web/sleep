<?php

if ( ! class_exists( '_WP_Editors', false ) ) {
	require ABSPATH . WPINC . '/class-wp-editor.php';
}

use WPML\TM\Editor\RichText;

class WPML_Translation_Editor extends WPML_WPDB_And_SP_User {

	private $job;

	public function __construct(
		&$sitepress,
		&$wpdb,
		$job
	) {
		parent::__construct( $wpdb, $sitepress );
		$this->job = $job;

		$this->add_hooks();
		$this->enqueue_js();
	}

	public function add_hooks() {
		add_filter( 'tiny_mce_before_init', [ $this, 'filter_original_editor_buttons' ], 10, 2 );
	}

	public function enqueue_js() {
		wp_enqueue_script( 'wpml-tm-editor-scripts' );
		wp_localize_script(
			'wpml-tm-editor-scripts',
			'tmEditorStrings',
			$this->get_translation_editor_strings()
		);
	}

	private function get_translation_editor_strings() {

		$translation_memory_endpoint = apply_filters( 'wpml_st_translation_memory_endpoint', '' );

		return array(
			'dontShowAgain'             => __(
				"Don't show this again.",
				'sitepress'
			),
			'learnMore'                 => __(
				'<p>The administrator has disabled term translation from the translation editor. </p>
<p>If your access permissions allow you can change this under "Translation Management" - "Multilingual Content Setup" - "Block translating taxonomy terms that already got translated". </p>
<p>Please note that editing terms from the translation editor will affect all posts that have the respective terms associated.</p>',
				'sitepress'
			),
			'warning'                   => __(
				"Please be advised that editing this term's translation here will change the value of the term in general. The changes made here, will not only affect this post!",
				'sitepress'
			),
			/* translators: Title of the notice in the translation editor shown when the site is set not to translate the names of categories, tags and other groupings. */
			'title'                     => __(
				'Terms translation is disabled',
				'sitepress'
			),
			'confirm'                   => __(
				'You have unsaved work. Are you sure you want to close without saving?',
				'sitepress'
			),
			/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
			'cancel'                    => __(
				'Cancel',
				'sitepress'
			),
			/* translators: Button label that keeps what was entered. Verb, imperative. */
			'save'                      => __(
				'Save',
				'sitepress'
			),
			/* translators: Label of the switch in the translation editor that leaves out the parts that are already translated. Verb, imperative. */
			'hide_translated'           => __(
				'Hide completed',
				'sitepress'
			),
			/* translators: Button label in the translation editor: keep the translation and leave the editor. Verb, imperative. */
			'save_and_close'            => __(
				'Save & Close',
				'sitepress'
			),
			'loading_url'               => ICL_PLUGIN_URL . '/res/img/ajax-loader.gif',
			/* translators: Text shown on the button of the translation editor while the translation is being saved; three dots show that it is still working. */
			'saving'                    => __(
				'Saving...',
				'sitepress'
			),
			'translation_complete'      => __(
				'Translation is complete',
				'sitepress'
			),
			'contentNonce'              => wp_create_nonce( 'wpml_save_job_nonce' ),
			'translationMemoryNonce'    => \WPML\LIB\WP\Nonce::create( $translation_memory_endpoint ),
			'translationMemoryEndpoint' => $translation_memory_endpoint,
			/* translators: Heading of the column of the translation editor that holds the text as it was written; the name of that language follows it. */
			'source_lang'               => __(
				'Original',
				'sitepress'
			),
			/* translators: Heading of the column of the translation editor that holds the translation; the name of the language being translated into follows it, so it ends without a full stop. */
			'target_lang'               => __(
				'Translation to',
				'sitepress'
			),
			'copy_all'                  => __(
				'Copy all fields from original',
				'sitepress'
			),
			/* translators: Button label in the translation editor: give up a translation job that was handed to you. Verb, imperative. It does not mean signing again. */
			'resign'                    => __(
				'Resign',
				'sitepress'
			),
			'resign_translation'        => __(
				'Are you sure you want to resign from this job?',
				'sitepress'
			),
			'resign_url'                => admin_url( 'admin-post.php?action=wpml_tm_resign_job&job_id=' . $this->job->get_id() . '&nonce=' . wp_create_nonce( 'wpml_tm_resign_job_' . $this->job->get_id() ) ),
			'confirmNavigate'           => __(
				'You have unsaved changes!',
				'sitepress'
			),
			'copy_from_original'        => __(
				'Copy from original',
				'sitepress'
			),
			/* translators: Button label in the translation editor: show what changed in the original since it was last translated. Verb, imperative. */
			'show_diff'                 => __( 'Show differences', 'sitepress' ),
		);
	}

	public function filter_original_editor_buttons( $config, $editor_id ) {
		if ( strpos( $editor_id, '_original' ) > 0 ) {
			$config['toolbar1'] = ' ';
			$config['toolbar2'] = ' ';
			$config['readonly'] = '1';
		}

		return $config;
	}

	public function output_editors( $field ) {
		echo '<div id="' . esc_attr( $field['field_type'] ) . '_original_editor" class="original_value mce_editor_origin">';
		wp_editor(
			RichText::sanitize( $field['field_data'] ),
			$field['field_type'] . '_original',
			array(
				'textarea_rows' => 4,
				'editor_class'  => 'wpml_content_tr original_value mce_editor_origin',
				'media_buttons' => false,
				'quicktags'     => array( 'buttons' => 'empty' ),
			)
		);
		echo '</div>';
		echo '<div id="' . esc_attr( $field['field_type'] ) . '_translated_editor" class="mce_editor translated_value">';
		wp_editor(
			RichText::sanitize( $field['field_data_translated'] ),
			$field['field_type'],
			array(
				'textarea_rows' => 4,
				'editor_class'  => 'wpml_content_tr translated_value',
				'media_buttons' => true,
				'textarea_name' => 'fields[' . $field['field_type'] . '][data]',
			)
		);
		echo '</div>';
	}
}

