<?php

use WPML\Plugins;

class WPML_Post_Status_Display {
	const ICON_TRANSLATION_EDIT          = 'otgs-ico-edit';
	const ICON_TRANSLATION_NEEDS_UPDATE  = 'otgs-ico-refresh';
	const ICON_TRANSLATION_ADD           = 'otgs-ico-add';
	const ICON_TRANSLATION_ADD_DISABLED  = 'otgs-ico-add-disabled';
	const ICON_TRANSLATION_EDIT_DISABLED = 'otgs-ico-edit-disabled';
	const ICON_TRANSLATION_IN_PROGRESS   = 'otgs-ico-in-progress';
	const ICON_TRANSLATION_IN_TRASH      = 'otgs-ico-trash';

	private $active_langs;

	public function __construct( $active_languages ) {
		$this->active_langs = $active_languages;
	}

	private function render_status_icon( $link, $text, $css_class ) {
		$icon = $this->get_action_icon( $css_class, $text );
		if ( strpos( $icon, 'disabled' ) ) {
			$link = null;
		}

		if ( $link ) {
			$icon_html = '<a href="' . esc_url( $link ) . '" class="js-wpml-translate-link">';
		} else {
			$icon_html = '<a class="js-wpml-translate-link">';
		}
		$icon_html .= $icon;
		$icon_html .= '</a>';

		return $icon_html;
	}

	private function get_action_icon( $css_class, $label ) {
		$label = (string) $label;

		return '<i class="' . $css_class . ' js-otgs-popover-tooltip" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '" data-original-title="' . esc_attr( $label ) . '"></i>'
			   . '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	}

	public function get_status_html( $post_id, $lang ) {
		list( $text, $link, $trid, $css_class, $status ) = $this->get_status_data( $post_id, $lang );

		if ( Plugins::isTMLoadedForRequest() ) {
			$wpml_tm_element_translations = wpml_tm_load_element_translations();
			$review_status = $wpml_tm_element_translations->get_translation_review_status( $trid, $lang );
		} else {
			// Blog License. Asked of the boot verdict rather than of
			$review_status = null;
		}

		if ( ! did_action( 'wpml_pre_status_icon_display' ) ) {
			do_action( 'wpml_pre_status_icon_display' );
		}

		$link = apply_filters( 'wpml_link_to_translation', $link, $post_id, $lang, $trid, $css_class, $status, $review_status );

		$text = apply_filters( 'wpml_text_to_translation', $text, $post_id, $lang, $trid, $css_class, $status, $review_status );

		$css_class = apply_filters( 'wpml_css_class_to_translation', $css_class, $post_id, $lang, $trid, $status, $review_status );

		$css_class = $this->map_old_icon_filter_to_css_class( $css_class, $post_id, $lang, $trid );

		return apply_filters(
			'wpml_post_status_display_html',
			$this->render_status_icon( $link, $text, $css_class ),
			$post_id,
			$lang,
			$trid
		);
	}

	private function map_old_icon_filter_to_css_class( $css_class, $post_id, $lang, $trid ) {
		$map = array(
			'edit_translation.png'          => self::ICON_TRANSLATION_EDIT,
			'needs-update.png'              => self::ICON_TRANSLATION_NEEDS_UPDATE,
			'add_translation.png'           => self::ICON_TRANSLATION_ADD,
			'in_progress.png'               => self::ICON_TRANSLATION_IN_PROGRESS,
			'add_translation_disabled.png'  => self::ICON_TRANSLATION_ADD_DISABLED,
			'edit_translation_disabled.png' => self::ICON_TRANSLATION_EDIT_DISABLED,
		);

		$old_icon = array_search( $css_class, $map, true );

		$old_icon = apply_filters( 'wpml_icon_to_translation', $old_icon, $post_id, $lang, $trid, $css_class );

		if ( $old_icon && array_key_exists( $old_icon, $map ) ) {
			$css_class = $map[ $old_icon ];
		}

		return $css_class;
	}

	public function get_status_data( $post_id, $lang ) {
		global $wpml_post_translations;

		$status_helper        = wpml_get_post_status_helper();
		$trid                 = $wpml_post_translations->get_element_trid( $post_id );
		$status               = $status_helper->get_status( false, $trid, $lang );
		$source_language_code = $wpml_post_translations->get_element_lang_code( $post_id );
		$correct_id           = $wpml_post_translations->element_id_in( $post_id, $lang );

		if ( $status && $correct_id && $this->is_in_trash( $correct_id ) ) {
			list( $text, $link, $css_class ) = $this->generate_trashed_data( $correct_id );
		} elseif ( $status && $correct_id ) {
			list( $text, $link, $css_class ) = $this->generate_edit_allowed_data( $correct_id, $status_helper->needs_update( $correct_id ) );
		} else {
			list( $text, $link, $css_class ) = $this->generate_add_data( $trid, $lang, $source_language_code, $post_id );
		}

		if ($status === ICL_TM_ATE_NEEDS_RETRY) {
			list( $text, $link, $css_class ) = $this->generate_retry_data();

		}

		return array( $text, $link, $trid, $css_class, $status );
	}

	private function generate_edit_allowed_data( $post_id, $update = false ) {
		global $wpml_post_translations;

		$lang_code    = $wpml_post_translations->get_element_lang_code( $post_id );
		$post_type    = $wpml_post_translations->get_type( $post_id );

		$css_class = self::ICON_TRANSLATION_EDIT;
		if ( $update && ! $wpml_post_translations->is_a_duplicate( $post_id ) ) {
			$css_class = self::ICON_TRANSLATION_NEEDS_UPDATE;
		}

		if ( $update ) {
			/* translators: Name of the icon in the list of content that opens a translation whose original has changed since. %s: the name of the language. */
			$text = __( 'Update %s translation', 'sitepress' );
		} else {
			/* translators: Name of the icon in the list of content that opens a translation for changing. %s: the name of the language. */
			$text = __( 'Edit the %s translation', 'sitepress' );
		}

		$text = sprintf( $text, $this->active_langs[ $lang_code ]['display_name'] );

		$link = 'post.php?' . http_build_query (
				array( 'lang'      => $lang_code,
				       'action'    => 'edit',
				       'post_type' => $post_type,
				       'post'      => $post_id
				)
			);

		return array( $text, $link, $css_class );
	}

	private function is_in_trash( $post_id ) {
		return 'trash' === get_post_status( $post_id );
	}

	private function generate_trashed_data( $post_id ) {
		global $wpml_post_translations;

		$lang_code = $wpml_post_translations->get_element_lang_code( $post_id );
		$post_type = $wpml_post_translations->get_type( $post_id );

		$link = 'edit.php?' . http_build_query(
			array(
				'lang'        => $lang_code,
				'post_status' => 'trash',
				'post_type'   => $post_type,
			)
		);

		return array(
			sprintf(
				/* translators: %s is the language name. */
				__( 'In the Trash in %s', 'sitepress' ),
				$this->active_langs[ $lang_code ]['display_name']
			),
			$link,
			self::ICON_TRANSLATION_IN_TRASH,
		);
	}

	private function generate_add_data( $trid, $lang_code, $source_language, $original_id ) {
		$link = 'post-new.php?' . http_build_query (
				array(
					'lang'        => $lang_code,
					'post_type'   => get_post_type ( $original_id ),
					'trid'        => $trid,
					'source_lang' => $source_language
				)
			);

		return array(
			/* translators: Name of the icon in the list of content that starts a translation in a language that has none yet. %s: the name of that language. */
			sprintf( __( 'Add translation to %s', 'sitepress' ), $this->active_langs[ $lang_code ]['display_name'] ),
			$link,
			self::ICON_TRANSLATION_ADD,
		);
	}

	/**
	 * The ATE needs-retry advisory (ICL_TM_ATE_NEEDS_RETRY): a no-link,
	 * in-progress icon. Client POV: what this means for their translation and
	 * what to do next - nothing, WPML retries on its own. Short and simple,
	 * so the hover (js-otgs-popover-tooltip) carries it, no popup.
	 *
	 * When Translation Management is loaded, `wpml_text_to_translation` replaces this label with
	 * a language-specific one (`WPML_TM_Translation_Status_Display::filter_status_text()`). That
	 * filter is not registered on a Blog license, so the label returned here is what the icon is
	 * left with - it must be a real one, not null, or the icon renders with no name at all.
	 *
	 * @return array
	 */
	private function generate_retry_data() {
		return array(
			__( 'Sending this content for automatic translation did not go through. WPML retries automatically - you don\'t need to do anything.', 'sitepress' ),
			null,
			self::ICON_TRANSLATION_IN_PROGRESS,
		);
	}
}
