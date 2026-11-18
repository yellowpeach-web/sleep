<?php

namespace WPML\PB\Elementor\Hooks;

class FormFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const WIDGET = 'form';

	const ITEMS_FIELD = 'form_fields';

	const PRIORITY_AFTER_SERVED_CONFIG = 30;

	public function add_hooks() {
		add_filter( 'wpml_elementor_widgets_to_translate', [ $this, 'addMissingFormFields' ], self::PRIORITY_AFTER_SERVED_CONFIG );
	}

	public function addMissingFormFields( $widgets ) {
		if ( ! is_array( $widgets ) || ! isset( $widgets[ self::WIDGET ] ) || ! is_array( $widgets[ self::WIDGET ] ) ) {
			return $widgets;
		}

		$form = $widgets[ self::WIDGET ];

		$form['fields'] = $this->appendMissing(
			isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : [],
			$this->getFields()
		);

		$itemFields = isset( $form['fields_in_item'][ self::ITEMS_FIELD ] ) && is_array( $form['fields_in_item'][ self::ITEMS_FIELD ] )
			? $form['fields_in_item'][ self::ITEMS_FIELD ]
			: [];

		$form['fields_in_item'][ self::ITEMS_FIELD ] = $this->appendMissing( $itemFields, $this->getItemFields() );

		$widgets[ self::WIDGET ] = $form;

		return $widgets;
	}

	private function appendMissing( array $existing, array $additions ) {
		$present = [];
		foreach ( $existing as $field ) {
			if ( isset( $field['field'] ) ) {
				$present[ $field['field'] ] = true;
			}
		}

		foreach ( $additions as $field ) {
			if ( ! isset( $present[ $field['field'] ] ) ) {
				$existing[]                 = $field;
				$present[ $field['field'] ] = true;
			}
		}

		return $existing;
	}

	private function line( $field, $label ) {
		return [
			'field'       => $field,
			'type'        => $label,
			'editor_type' => 'LINE',
		];
	}

	private function getFields() {
		return [
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'server_message', __( 'Form: Server message', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'email_from_name_2', __( 'Form: Email from name 2', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'slack_pretext', __( 'Form: Slack pretext', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'slack_title', __( 'Form: Slack title', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'slack_text', __( 'Form: Slack text', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'slack_username', __( 'Form: Slack username', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'discord_title', __( 'Form: Discord title', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'discord_content', __( 'Form: Discord content', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'discord_username', __( 'Form: Discord username', 'sitepress' ) ),
		];
	}

	private function getItemFields() {
		return [
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'field_value', __( 'Form: Field default value', 'sitepress' ) ),
		];
	}
}
