<?php

namespace WPML\TM\ATE\Review;

class TermSaveAcceptsReview implements \IWPML_Backend_Action, \IWPML_AJAX_Action {

	private $wpdb;

	private $reviewRules;

	public function __construct( ?\wpdb $wpdb = null, ?callable $reviewRules = null ) {
		$this->wpdb        = $wpdb ?: $GLOBALS['wpdb'];
		$this->reviewRules = $reviewRules ?: [ ReviewRulesFactory::class, 'create' ];
	}

	public function add_hooks() {
		add_action( 'edited_term', [ $this, 'onTermEdited' ], 10, 3 );
	}

	public function onTermEdited( $termId, $termTaxonomyId, $taxonomy ) {
		if ( ! self::isPersonsSaveOf( (int) $termId ) ) {
			return;
		}

		$termRid = $this->termRid( (int) $termTaxonomyId, (string) $taxonomy );
		if ( $termRid ) {
			call_user_func( $this->reviewRules )->acceptOnTermSave( $termRid );
		}
	}

	private static function isPersonsSaveOf( int $termId ): bool {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $termId ) ) {
			$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

			return 'editedtag' === $action && isset( $_POST['tag_ID'] ) && (int) $_POST['tag_ID'] === $termId;
		}

		if ( wp_doing_ajax() && isset( $_POST['_inline_edit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'taxinlineeditnonce' ) ) {
			$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

			return 'inline-save-tax' === $action && isset( $_POST['tax_ID'] ) && (int) $_POST['tax_ID'] === $termId;
		}

		return false;
	}

	private function termRid( int $termTaxonomyId, string $taxonomy ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ts.rid FROM {$wpdb->prefix}icl_translations t
					INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
					WHERE t.element_type = %s AND t.element_id = %d AND t.source_language_code IS NOT NULL",
				'tax_' . $taxonomy,
				$termTaxonomyId
			)
		);
	}
}
