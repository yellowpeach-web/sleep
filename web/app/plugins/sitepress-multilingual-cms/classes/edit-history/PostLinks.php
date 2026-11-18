<?php

namespace WPML\EditHistory;

class PostLinks implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const NOTICE_ID = 'wpml-edit-history-update';

	private $items;

	public function __construct( PanelItems $items ) {
		$this->items = $items;
	}

	public function add_hooks() {
		add_filter( 'wpml_post_language_panel_data', [ $this, 'addToPanel' ], 10, 2 );
		add_action( 'icl_post_languages_options_before', [ $this, 'printInClassicBox' ] );
		add_filter( 'wpml_dashboard_initial_script_data', [ $this, 'addToDashboard' ] );
	}

	public function addToPanel( $data, $postId = 0 ) {
		if ( ! is_array( $data ) || empty( $data['is_translation'] ) ) {
			return $data;
		}

		$state = $this->listed( (int) $postId );
		if ( ! $state ) {
			return $data;
		}

		$data['notices']   = is_array( $data['notices'] ?? null ) ? $data['notices'] : [];
		$data['notices'][] = [
			'id'      => self::NOTICE_ID,
			'type'    => 'info',
			'text'    => $this->text( $state ),
			'actions' => [
				[
					'label' => self::linkLabel(),
					'url'   => self::url( $state->subject()->id() ),
				],
			],
		];

		return $data;
	}

	public function printInClassicBox( $postId = 0 ) {
		$state = $this->listed( (int) $postId );
		if ( ! $state ) {
			return;
		}

		echo '<div class="wpml-edit-update-link"><p>' . esc_html( $this->text( $state ) ) . '</p>';
		echo '<p><a href="' . esc_url( self::url( $state->subject()->id() ) ) . '">' . esc_html( self::linkLabel() ) . '</a></p></div>';
	}

	public function addToDashboard( $data ) {
		if ( ! is_array( $data ) || ! $this->items->hasAnyForCurrentUser() ) {
			return $data;
		}

		$data['translationUpdates'] = [
			'url'   => self::url(),
			/* translators: Link on the Translations Dashboard to the "Translation updates" screen, the history of updates to translations the client edited. */
			'label' => __( 'See the history of translation updates', 'sitepress' ),
		];

		return $data;
	}

	private function listed( int $postId ) {
		return $postId > 0 ? $this->items->listed( Subject::post( $postId ) ) : null;
	}

	private function text( State $state ): string {
		if ( ! $this->items->current( $state ) ) {
			/* translators: Line in a translation's Language box, next to a link to the "Translation updates" screen. The update can no longer be reverted. */
			return __( 'WPML updated this translation after your edits.', 'sitepress' );
		}

		if ( Delivery::REASON_UNCLEAR === $state->itemReason() ) {
			/* translators: Line in a translation's Language box, next to a link to the "Translation updates" screen. ATE is the automatic translation service. */
			return __( 'WPML updated this translation after your edits. ATE was not sure if you wanted to keep them. Your previous version is saved.', 'sitepress' );
		}

		/* translators: Line in a translation's Language box, next to a link to the "Translation updates" screen. */
		return __( 'WPML updated this translation after your edits. Your previous version is saved.', 'sitepress' );
	}

	private static function linkLabel(): string {
		/* translators: Link in a translation's Language box. It opens this translation's entry on the "Translation updates" screen. */
		return __( 'See the update', 'sitepress' );
	}

	public static function url( int $translationId = 0 ): string {
		$url = add_query_arg( 'page', PanelHooks::PAGE, admin_url( 'admin.php' ) );

		return $translationId ? $url . '#' . PanelPage::anchor( $translationId ) : $url;
	}
}
