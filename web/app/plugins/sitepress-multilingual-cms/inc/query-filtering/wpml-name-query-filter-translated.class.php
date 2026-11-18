<?php

class WPML_Name_Query_Filter_Translated extends WPML_Name_Query_Filter {

	private $pages_to_langs = array();

	protected function select_best_match( $pages_with_name ) {
		$this->pages_to_langs = array();

		if ( ! empty( $pages_with_name['matching_ids'] ) ) {
			$matching_page = $this->get_matching_page_in_requested_lang( $pages_with_name['matching_ids'] );

			if ( $matching_page ) {
				$contested_page = $this->get_page_when_a_fallback_contests_the_requested_lang( $pages_with_name, $matching_page );

				return $contested_page ? $contested_page : $matching_page;
			}

			$display_as_translated_page = $this->get_matching_page_displayed_as_translated();

			if ( $display_as_translated_page ) {
				return $display_as_translated_page;
			}
		}

		if ( ! empty( $pages_with_name['related_ids'] ) ) {
			return $this->get_the_best_related_page_to_redirect( $pages_with_name['related_ids'] );
		}

		return null;
	}

	private function get_page_when_a_fallback_contests_the_requested_lang( array $pages_with_name, $matching_page ) {
		$requested_lang = array();
		$fallbacks      = array();
		$after_match    = false;
		foreach ( $pages_with_name['matching_ids'] as $page_id ) {
			$page_id = (int) $page_id;
			if ( $page_id === $matching_page ) {
				$after_match = true;
				$page_lang   = $this->sitepress->get_current_language();
			} elseif ( $after_match ) {
				$page_lang = $this->post_translation->get_element_lang_code( $page_id );
			} else {
				$page_lang = isset( $this->pages_to_langs[ $page_id ] ) ? $this->pages_to_langs[ $page_id ] : null;
			}
			if ( $this->sitepress->get_current_language() === $page_lang ) {
				$requested_lang[] = $page_id;
			} elseif ( $this->sitepress->get_default_language() === $page_lang ) {
				$fallbacks[] = $page_id;
			}
		}

		if ( ! $fallbacks ) {
			return null;
		}

		$parent = isset( $pages_with_name['requested_parent'] ) ? (int) $pages_with_name['requested_parent'] : null;

		if ( ( null === $parent || $this->get_pages_with_parent( array( $matching_page ), $parent ) ) && $this->is_readable( $matching_page ) ) {
			return null;
		}

		if ( ! $this->sitepress->is_display_as_translated_post_type( get_post_type( $fallbacks[0] ) ) ) {
			return null;
		}

		if ( null !== $parent ) {
			$page   = $this->get_readable_page_or_fallback(
				$this->get_pages_with_parent( $requested_lang, $parent ),
				$this->get_pages_with_parent( $fallbacks, $parent )
			);
			if ( $page ) {
				return $page;
			}
		}

		$page = $this->get_readable_page_or_fallback( $requested_lang, $fallbacks );

		return $page ? $page : $requested_lang[0];
	}

	private function get_readable_page_or_fallback( array $requested_lang, array $fallbacks ) {
		foreach ( $requested_lang as $page_id ) {
			if ( $this->is_readable( $page_id ) ) {
				return $page_id;
			}
		}

		foreach ( $fallbacks as $page_id ) {
			$fallback = (int) $this->post_translation->element_id_in( $page_id, $this->sitepress->get_current_language(), true );
			if ( $fallback && $this->is_readable( $fallback ) ) {
				return $fallback;
			}
		}

		return null;
	}

	private function get_pages_with_parent( array $page_ids, $parent ) {
		return array_values(
			array_filter(
				$page_ids,
				function ( $page_id ) use ( $parent ) {
					$post = get_post( $page_id );

					return $post && (int) $post->post_parent === $parent;
				}
			)
		);
	}

	private function is_readable( $page_id ) {
		$post = get_post( $page_id );

		return $post && ( 'publish' === $post->post_status || current_user_can( 'read_post', $page_id ) );
	}

	private function get_matching_page_in_requested_lang( array $matching_ids ) {
		foreach ( $matching_ids as $matching_id ) {
			$page_lang = $this->post_translation->get_element_lang_code( (int) $matching_id );

			if ( $this->sitepress->get_current_language() === $page_lang ) {
				return (int) $matching_id;
			}

			$this->pages_to_langs[ $matching_id ] = $page_lang;
		}

		return null;
	}

	private function get_matching_page_displayed_as_translated() {
		foreach ( $this->pages_to_langs as $page_id => $lang ) {
			if ( $lang === $this->sitepress->get_default_language()
				 && $this->sitepress->is_display_as_translated_post_type( get_post_type( $page_id ) )
			) {
				return $this->post_translation->element_id_in( $page_id, $this->sitepress->get_current_language(), true );
			}
		}

		return null;
	}

	private function get_the_best_related_page_to_redirect( array $related_page_ids ) {
		foreach ( $this->active_languages as $lang_code ) {
			foreach ( $related_page_ids as $related_page_id ) {
				$page_lang = $this->post_translation->get_element_lang_code( (int) $related_page_id );

				if ( $page_lang === $lang_code ) {
					return $related_page_id;
				}
			}
		}

		return null;
	}

}
