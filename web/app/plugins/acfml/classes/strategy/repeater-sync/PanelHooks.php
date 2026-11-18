<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Strategy;
use WPML\LIB\WP\Hooks;

class PanelHooks implements \IWPML_Backend_Action {

	const HANDLE      = 'acfml-repeater-sync-panel';
	const SCRIPT_PATH = 'dist/js/repeater-sync-panel/app.js';
	const DATA_GLOBAL = 'acfmlRepeaterSync';

	private $shuffled;

	private $checkboxCondition;

	public function __construct(
		Strategy $shuffled,
		CheckboxCondition $checkboxCondition
	) {
		$this->shuffled          = $shuffled;
		$this->checkboxCondition = $checkboxCondition;
	}

	public function add_hooks() {
		Hooks::onAction( 'enqueue_block_editor_assets' )
			->then( [ $this, 'enqueuePanel' ] );
	}

	public function enqueuePanel() {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$fieldGroups = acf_get_field_groups( [ 'post_id' => $post->ID ] );
		if ( ! $this->checkboxCondition->isMet( $post->ID, $fieldGroups ) ) {
			return;
		}

		$trid = $this->shuffled->getTrid( $post->ID );
		if ( ! $trid ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			untrailingslashit( ACFML_PLUGIN_URL ) . '/' . self::SCRIPT_PATH,
			self::dependencies(),
			ACFML_VERSION,
			true
		);
		wp_add_inline_script(
			self::HANDLE,
			'var ' . self::DATA_GLOBAL . ' = ' . wp_json_encode(
				[
					'trid'    => (int) $trid,
					'enabled' => CheckboxUI::isChecked( $trid ),
					'strings' => self::strings(),
				]
			) . ';',
			'before'
		);
		wp_enqueue_script( self::HANDLE );
		wp_set_script_translations( self::HANDLE, 'acfml', ACFML_PLUGIN_PATH . '/languages' );
	}

	public static function strings() {
		return [
			/* translators: Title of the panel holding that checkbox in the post editor. "ACFML" is the plugin's short name and stays in English. */
			'title' => __( 'ACFML Synchronise translations', 'acfml' ),
			/* translators: Label of that checkbox; "repeater" and "flexible" are ACF field types. */
			'label' => __( 'Keep repeater and flexible sub-fields in the same order as the default language.', 'acfml' ),
			/* translators: Error shown in the editor sidebar when the synchronisation choice could not be stored. */
			'error' => __( 'The synchronisation setting could not be saved.', 'acfml' ),
		];
	}

	private static function dependencies() {
		global $wp_version;

		$editor = version_compare( (string) $wp_version, '6.6', '>=' ) ? 'wp-editor' : 'wp-edit-post';

		return [ 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n', $editor ];
	}
}
