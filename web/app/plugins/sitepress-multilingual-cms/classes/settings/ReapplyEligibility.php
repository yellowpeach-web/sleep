<?php

namespace WPML\TM\Settings;

use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\LanguageEditor\TranslationPause;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\TranslateEverything\Cutoff;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class ReapplyEligibility {

	private $actions;

	public function __construct( $actions = null ) {
		$this->actions = $actions instanceof Actions ? $actions : null;
	}

	public function forPost( $postId ) {
		$source      = $this->sourceLanguage( (int) $postId );
		$translated  = $this->translatedLanguages( (int) $postId );
		$withinRange = $translated
			&& $this->isTranslateEverythingActive()
			&& $this->isWithinRange( (int) $postId )
			&& $this->passesSaveGates( (int) $postId );
		$sendable    = $withinRange
			? array_values( array_intersect( $translated, $this->eligibleTargetLanguages( $source ) ) )
			: [];

		return [
			'translated'  => $translated,
			'sendable'    => $sendable,
			'stale'       => $translated ? array_values( array_intersect( $translated, $this->staleLanguages( (int) $postId ) ) ) : [],
			'withinRange' => (bool) $withinRange,
			'source'      => $source,
			'elementType' => 'post_' . $this->postType( (int) $postId ),
		];
	}

	protected function staleLanguages( $postId ) {
		global $wpdb, $wpml_post_translations;

		if ( ! $wpdb || ! $wpml_post_translations ) {
			return [];
		}

		$trid = (int) $wpml_post_translations->get_element_trid( $postId );
		if ( ! $trid ) {
			return [];
		}

		return array_map(
			'strval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT t.language_code
					 FROM {$wpdb->prefix}icl_translations t
					 JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
					 WHERE t.trid = %d AND s.status = %d AND s.needs_update = 1",
					$trid,
					ICL_TM_COMPLETE
				)
			)
		);
	}

	protected function postType( $postId ) {
		$post = $this->postOf( $postId );

		return $post ? (string) $post->post_type : 'post';
	}

	protected function translatedLanguages( $postId ) {
		global $wpml_post_translations;

		if ( ! $wpml_post_translations ) {
			return [];
		}

		$source = $this->sourceLanguage( $postId );
		$langs  = [];
		foreach ( (array) $wpml_post_translations->get_element_translations( $postId ) as $lang => $id ) {
			if ( (string) $lang !== $source && (int) $id ) {
				$langs[] = (string) $lang;
			}
		}

		return $langs;
	}

	protected function sourceLanguage( $postId ) {
		global $wpml_post_translations;

		return $wpml_post_translations ? (string) $wpml_post_translations->get_element_lang_code( $postId ) : '';
	}

	protected function isTranslateEverythingActive() {
		return \WPML_TM_ATE_Status::is_enabled_and_activated() && Option::shouldTranslateEverything();
	}

	protected function isWithinRange( $postId ) {
		$post = $this->postOf( $postId );
		if ( ! $post || ! TranslateEverythingScope::isOffered( (string) $post->post_type ) ) {
			return false;
		}

		$this->repairEmptySinceDates();

		$sinceDate = $this->sinceDateOf( (string) $post->post_type );
		if ( Cutoff::isSkip( $sinceDate ) ) {
			return false;
		}

		return Cutoff::isPostWithin( $post, (string) $sinceDate );
	}

	protected function postOf( $postId ) {
		$post = get_post( $postId );

		return $post ? $post : null;
	}

	protected function sinceDateOf( $postType ) {
		return Option::getTranslateEverythingPostSinceDate( $postType );
	}

	protected function passesSaveGates( $postId ) {
		$actions = $this->actions();

		return $actions && method_exists( $actions, 'passesTranslateEverythingPostGates' )
			? (bool) $actions->passesTranslateEverythingPostGates( $postId )
			: false;
	}

	protected function repairEmptySinceDates() {
		$actions = $this->actions();

		if ( $actions && method_exists( $actions, 'repairEmptySinceDatesIfNeeded' ) ) {
			$actions->repairEmptySinceDatesIfNeeded();
		}
	}

	private function actions() {
		if ( null === $this->actions ) {
			try {
				$made          = \WPML\Container\make( Actions::class );
				$this->actions = $made instanceof Actions ? $made : false;
			} catch ( \Throwable $e ) {
				$this->actions = false;
			}
		}

		return $this->actions ?: null;
	}

	protected function eligibleTargetLanguages( $sourceLang ) {
		return array_values( TranslationPause::filterTranslatable( LanguageMappings::geCodesEligibleForAutomaticTranslations( $sourceLang ?: null ) ) );
	}
}
