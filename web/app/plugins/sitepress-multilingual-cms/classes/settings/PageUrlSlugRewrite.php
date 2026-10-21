<?php

namespace WPML\TM\Settings;

class PageUrlSlugRewrite {

	const FORCE_GENERATE = 'force-generate';
	const COPY_ENCODED   = 'copy-encoded';

	const TRANSITIONS = [ self::FORCE_GENERATE, self::COPY_ENCODED ];

	public static function isRewrite( $transition ) {
		return in_array( (string) $transition, self::TRANSITIONS, true );
	}

	public function plan( $originalId, $transition ) {
		$original = $this->post( (int) $originalId );
		if ( ! $original || ! self::isRewrite( $transition ) ) {
			return [];
		}

		$changes = [];
		foreach ( $this->translationsOf( (int) $originalId ) as $lang => $translationId ) {
			$translation = $this->post( (int) $translationId );
			if ( ! $translation ) {
				continue;
			}

			$change = $this->changeFor( $translation, (string) $lang, $original, $transition );
			if ( $change ) {
				$changes[] = $change;
			}
		}

		return $changes;
	}

	public function planTranslation( $translationId, $transition ) {
		if ( ! self::isRewrite( $transition ) ) {
			return null;
		}

		$translation = $this->post( (int) $translationId );
		$originalId  = $translation ? $this->originalOf( (int) $translationId ) : 0;
		$original    = $originalId ? $this->post( $originalId ) : null;
		if ( ! $translation || ! $original ) {
			return null;
		}

		return $this->changeFor( $translation, $this->languageOf( (int) $translationId ), $original, $transition );
	}

	public function apply( $originalId, $transition ) {
		$moved = 0;
		foreach ( $this->plan( $originalId, $transition ) as $change ) {
			$this->applyChange( $change );
			$moved++;
		}

		return $moved;
	}

	public function applyChange( array $change ) {
		$this->writeSlug( (int) $change['id'], (string) $change['to'] );
		$this->updateLinksTo( (int) $change['id'], (string) $change['from'] );
	}

	private function changeFor( $translation, $lang, $original, $transition ) {
		$to = self::COPY_ENCODED === $transition
			? $this->copiedSlug( $translation, $lang, $original )
			: $this->generatedSlug( $translation );

		if ( null === $to || $to === (string) $translation->post_name ) {
			return null;
		}

		return [
			'id'   => (int) $translation->ID,
			'lang' => (string) $lang,
			'from' => (string) $translation->post_name,
			'to'   => $to,
		];
	}

	protected function updateLinksTo( $postId, $nameBefore ) {
		$dic = isset( $GLOBALS['wpml_dic'] ) ? $GLOBALS['wpml_dic'] : null;
		if ( ! $dic || ! method_exists( $dic, 'make' ) ) {
			return;
		}

		try {
			$repository = $dic->make( \WPML\Infrastructure\WordPress\Component\Translation\Domain\Links\Repository::class );
			$listener   = $dic->make( \WPML\Core\Component\Translation\Application\Event\Links\ItemUpdateListener::class );
			$item       = $repository->get( (int) $postId, \WPML\Infrastructure\WordPress\Component\Translation\Domain\Links\Repository::TYPE_POST );
			$item->markLinkAsChanged( (string) $nameBefore );
			$listener->onItemSave( $item );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	protected function generatedSlug( $translation ) {
		$base = sanitize_title( (string) $translation->post_title );
		if ( '' === $base ) {
			return null;
		}

		return (string) wp_unique_post_slug(
			$base,
			(int) $translation->ID,
			(string) $translation->post_status,
			(string) $translation->post_type,
			(int) $translation->post_parent
		);
	}

	protected function copiedSlug( $translation, $lang, $original ) {
		if ( ! $this->languageEncodesUrls( $lang ) ) {
			return null;
		}

		$wanted = (string) $original->post_name;
		if ( '' === $wanted ) {
			return null;
		}

		$candidate = $wanted;
		$n         = 1;
		while ( $this->slugTakenInLanguage( $candidate, $lang, $translation ) ) {
			$n++;
			$candidate = $wanted . '-' . $n;
		}

		return $candidate;
	}

	protected function slugTakenInLanguage( $slug, $lang, $translation ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id
				 WHERE p.ID <> %d AND t.element_type = %s AND t.language_code = %s AND p.post_name = %s AND p.post_parent = %d
				 LIMIT 1",
				(int) $translation->ID,
				'post_' . $translation->post_type,
				$lang,
				$slug,
				(int) $translation->post_parent
			)
		);
	}

	protected function languageEncodesUrls( $lang ) {
		global $sitepress;

		$details = $sitepress ? $sitepress->get_language_details( $lang ) : null;

		return is_array( $details ) && ! empty( $details['encode_url'] );
	}

	protected function translationsOf( $originalId ) {
		global $wpml_post_translations;

		if ( ! $wpml_post_translations ) {
			return [];
		}

		$source = (string) $wpml_post_translations->get_element_lang_code( $originalId );
		$out    = [];
		foreach ( (array) $wpml_post_translations->get_element_translations( $originalId ) as $lang => $id ) {
			if ( (string) $lang !== $source && (int) $id ) {
				$out[ (string) $lang ] = (int) $id;
			}
		}

		return $out;
	}

	protected function originalOf( $translationId ) {
		global $wpml_post_translations;

		return $wpml_post_translations ? (int) $wpml_post_translations->get_original_element( $translationId ) : 0;
	}

	protected function languageOf( $translationId ) {
		global $wpml_post_translations;

		return $wpml_post_translations ? (string) $wpml_post_translations->get_element_lang_code( $translationId ) : '';
	}

	protected function post( $postId ) {
		$post = get_post( $postId );

		return $post ? $post : null;
	}

	protected function writeSlug( $postId, $slug ) {
		global $wpdb;

		$post = new \WPML_WP_Post( $wpdb, $postId );
		$post->update( [ 'post_name' => $slug ], true );
	}
}
