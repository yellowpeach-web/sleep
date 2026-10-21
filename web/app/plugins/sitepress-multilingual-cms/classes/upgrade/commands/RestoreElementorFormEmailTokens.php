<?php

namespace WPML\Upgrade\Commands;

use WPML\PB\Elementor\Hooks\FormEmailPlaceholders;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\TranslateEverything\UntranslatedPosts;

class RestoreElementorFormEmailTokens implements \IWPML_Upgrade_Command {

	public const STATE_OPTION = 'wpml_restore_elementor_form_email_tokens';

	public const BATCH_SIZE = 500;

	public const MAX_RESTORES = 25;

	private const TIME_BUDGET = 2.0;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run_admin() {
		if ( $this->getState()['done'] ) {
			delete_option( self::STATE_OPTION );
			$this->result = true;

			return true;
		}

		add_action( 'wp_loaded', [ $this, 'runBatch' ] );

		return false;
	}

	public function runBatch(): bool {
		$state = $this->getState();

		if ( ! function_exists( 'icl_add_string_translation' ) ) {
			$state['done'] = true;
			$this->saveState( $state );

			return true;
		}

		$deadline      = microtime( true ) + self::TIME_BUDGET;
		$restored      = 0;
		$restoredPosts = [];

		do {
			$strings      = $this->getCandidates( $state['lastId'] );
			$translations = $this->getTranslations( $strings );

			foreach ( $strings as $string ) {
				if ( $restored >= self::MAX_RESTORES ) {
					break 2;
				}

				foreach ( $translations[ (int) $string->id ] ?? [] as $translation ) {
					if ( $this->lostAToken( $string, (string) $translation->value ) ) {
						icl_add_string_translation( (int) $string->id, $translation->language, $string->value, ICL_TM_NEEDS_UPDATE );
						$restored++;
						$restoredPosts[ (int) $string->post_id ][ $translation->language ] = true;
						$this->log( $string, $translation->language );
					}
				}

				$state['lastId'] = (int) $string->id;
			}

			$state['done'] = count( $strings ) < self::BATCH_SIZE;
		} while ( ! $state['done'] && microtime( true ) < $deadline );

		$this->markPostsNeedingUpdate( $restoredPosts );
		$this->saveState( $state );
		$this->result = $state['done'];

		return $this->result;
	}

	private function getCandidates( int $lastId ): array {
		$wpdb = $this->schema->get_wpdb();

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.id, s.name, s.value, p.post_id
				FROM {$wpdb->prefix}icl_strings s
				INNER JOIN {$wpdb->prefix}icl_string_packages p ON p.ID = s.string_package_id
				WHERE p.kind_slug = 'elementor'
					AND s.id > %d
					AND ( s.name LIKE %s OR s.name LIKE %s )
					AND ( s.value LIKE %s OR s.value LIKE %s )
				ORDER BY s.id
				LIMIT %d",
				$lastId,
				$wpdb->esc_like( 'email_' ) . '%',
				$wpdb->esc_like( 'redirect_to-' ) . '%',
				'%' . $wpdb->esc_like( '[all-fields]' ) . '%',
				'%' . $wpdb->esc_like( '[field' ) . '%',
				self::BATCH_SIZE
			)
		);
	}

	private function getTranslations( array $strings ): array {
		if ( ! $strings ) {
			return [];
		}

		$wpdb = $this->schema->get_wpdb();
		$ids  = array_map( 'intval', wp_list_pluck( $strings, 'id' ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT string_id, language, value
				FROM {$wpdb->prefix}icl_string_translations
				WHERE string_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				...$ids
			)
		);

		$byString = [];
		foreach ( $rows as $row ) {
			$byString[ (int) $row->string_id ][] = $row;
		}

		return $byString;
	}

	private function markPostsNeedingUpdate( array $posts ) {
		$markedLanguages = [];

		foreach ( $posts as $postId => $languages ) {
			$postType = get_post_type( $postId );
			if ( ! $postType ) {
				continue;
			}

			$elementType  = 'post_' . $postType;
			$trid         = apply_filters( 'wpml_element_trid', null, $postId, $elementType );
			$translations = $trid ? (array) apply_filters( 'wpml_get_element_translations', null, $trid, $elementType ) : [];

			foreach ( array_keys( $languages ) as $language ) {
				if (
					! empty( $translations[ $language ]->translation_id )
					&& $this->markNeedingUpdate( (int) $translations[ $language ]->translation_id )
				) {
					$markedLanguages[ $language ] = true;
				}
			}
		}

		if ( $markedLanguages && AutomaticTranslationCapabilities::shouldTranslateEverything() ) {
			$untranslatedPosts = \WPML\Container\make( UntranslatedPosts::class );
			$untranslatedPosts->markLanguagesAsUncompleted( array_keys( $markedLanguages ) );
			$untranslatedPosts->markSkippedTypesAsCompleted();
		}
	}

	private function markNeedingUpdate( int $translationId ): bool {
		$wpdb = $this->schema->get_wpdb();

		$status = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, status, needs_update FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translationId
			)
		);

		if ( ! $status || ICL_TM_COMPLETE !== (int) $status->status || (int) $status->needs_update ) {
			return false;
		}

		wpml_load_core_tm()->update_translation_status(
			[
				'translation_id' => $translationId,
				'needs_update'   => 1,
			],
			(int) $status->rid
		);

		return true;
	}

	private function lostAToken( \stdClass $string, string $translation ): bool {
		if ( '' === $translation || ! preg_match( FormEmailPlaceholders::STRING_NAME_PATTERN, (string) $string->name ) ) {
			return false;
		}

		foreach ( $this->getTokens( (string) $string->value ) as $token ) {
			if ( false === strpos( $translation, $token ) ) {
				return true;
			}
		}

		return false;
	}

	private function getTokens( string $text ): array {
		$tokens = [];

		foreach ( FormEmailPlaceholders::PLACEHOLDER_PATTERNS as $pattern ) {
			preg_match_all( $pattern, $text, $matches );
			$tokens = array_merge( $tokens, $matches[0] );
		}

		return array_unique( $tokens );
	}

	private function getState(): array {
		$state = get_option( self::STATE_OPTION, [] );

		return [
			'lastId' => (int) ( $state['lastId'] ?? 0 ),
			'done'   => ! empty( $state['done'] ),
		];
	}

	private function saveState( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	private function log( \stdClass $string, string $language ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$data = [
				'string_id' => (int) $string->id,
				'name'      => $string->name,
				'language'  => $language,
			];

			error_log( 'WPML: restored an Elementor form email setting ' . wp_json_encode( $data ) );
		}
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
