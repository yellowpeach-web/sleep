<?php

namespace WPML\TranslationPolicy\TranslateEverything;

use WPML\TM\API\Jobs;
use WPML\TranslationPolicy\Duplicate\CopyReader;
use WPML\TranslationPolicy\Policy\PolicyAnswer;
use WPML\TranslationPolicy\Policy\PolicyReaderInterface;
use WPML\TranslationPolicy\Policy\PolicySql;
use WPML\TranslationPolicy\Policy\PolicyTable;

class PolicyGate implements GateInterface {

	const CHOSEN_PTC = PolicyTable::STRENGTH_CHOICE . ':' . PolicyTable::METHOD_PTC;

	const OTHER_PTC = [
		PolicyTable::STRENGTH_LOCK . ':' . PolicyTable::METHOD_PTC,
		PolicyTable::STRENGTH_DEFAULT . ':' . PolicyTable::METHOD_PTC,
	];

	private $reader;

	private $sql;

	private $wpdb;

	private $answers = [];

	private $methodRows = [];

	private $primed = false;

	private $copyReader;

	private $copies = [];

	public function __construct( PolicyReaderInterface $reader, PolicySql $sql, \wpdb $wpdb, CopyReader $copyReader ) {
		$this->reader     = $reader;
		$this->sql        = $sql;
		$this->wpdb       = $wpdb;
		$this->copyReader = $copyReader;
	}

	public function sendsPost( int $postId, callable $todays ): bool {
		$answer = $this->answer( $postId );

		if ( $this->isSelf( $answer ) ) {
			return false;
		}

		return $answer->isPtcChosen() || PolicyTable::EDITOR_NATIVE !== $answer->editor();
	}

	public function sendsOverJob( int $postId, callable $todays ): bool {
		return self::CHOSEN_PTC === $this->methodRow( $postId ) || (bool) $todays();
	}

	public function sweepConditions( string $editorCondition, string $oldEditorCondition, string $postIdColumn, string $postTypeColumn ): array {
		$todays = '( ' . $editorCondition . ' ' . $oldEditorCondition . ' )';

		$cases = "WHEN '" . self::CHOSEN_PTC . "' THEN 1";
		foreach ( self::OTHER_PTC as $methodRow ) {
			$cases .= " WHEN '" . $methodRow . "' THEN " . $todays;
		}

		return [
			'( CASE ' . $this->sql->methodRowOf( $postIdColumn, $postTypeColumn ) . ' ' . $cases . ' ELSE 0 END ) = 1',
			'',
		];
	}

	public function opensOnClick( int $postId, callable $todays ): bool {
		$methodRow = $this->methodRow( $postId );

		if ( self::CHOSEN_PTC === $methodRow ) {
			return true;
		}

		return in_array( $methodRow, self::OTHER_PTC, true ) && (bool) $todays();
	}

	public function prime( callable $listPostIds ) {
		if ( $this->primed ) {
			return;
		}
		$this->primed = true;

		$postIds = array_values( array_unique( array_filter( array_map( 'intval', (array) $listPostIds() ) ) ) );
		foreach ( $this->sql->methodRowsForPosts( $postIds ) as $row ) {
			$this->methodRows[ (int) $row['id'] ] = (string) $row['method_row'];
		}
	}

	public function dropsAtSend( int $jobId, callable $todays ): bool {
		if ( ! $todays() ) {
			return false;
		}

		$originalId = $this->originalPostOfJob( $jobId );

		return ! ( $originalId && self::CHOSEN_PTC === $this->methodRow( $originalId ) );
	}

	public function copiesOf( int $postId, callable $todays ): array {
		if ( self::CHOSEN_PTC === $this->methodRow( $postId ) ) {
			return [];
		}

		if ( ! isset( $this->copies[ $postId ] ) ) {
			$this->copies[ $postId ] = $this->copiesInStore( $postId );
		}

		return $this->copies[ $postId ];
	}

	private function copiesInStore( int $postId ): array {
		$type         = apply_filters( 'wpml_element_type', get_post_type( $postId ) );
		$trid         = (int) apply_filters( 'wpml_element_trid', null, $postId, $type );
		$translations = $trid ? (array) apply_filters( 'wpml_get_element_translations', [], $trid, $type ) : [];

		$copies = [];
		foreach ( $translations as $language => $translation ) {
			$id = (int) ( $translation->element_id ?? 0 );
			if ( $id && $id !== $postId && $this->copyReader->isCopy( $id ) ) {
				$copies[ (string) $language ] = $id;
			}
		}

		return $copies;
	}

	private function isSelf( PolicyAnswer $answer ): bool {
		return PolicyTable::METHOD_PTC !== $answer->method();
	}

	private function methodRow( int $postId ): string {
		if ( ! isset( $this->methodRows[ $postId ] ) ) {
			$answer                      = $this->answer( $postId );
			$this->methodRows[ $postId ] = $answer->methodStrength() ? $answer->methodStrength() . ':' . $answer->method() : '';
		}

		return $this->methodRows[ $postId ];
	}

	private function answer( int $postId ): PolicyAnswer {
		if ( ! isset( $this->answers[ $postId ] ) ) {
			$this->answers[ $postId ] = $this->reader->forPost( $postId );
		}

		return $this->answers[ $postId ];
	}

	private function originalPostOfJob( int $jobId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT original.element_id
				FROM {$wpdb->prefix}icl_translate_job job
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status ON translation_status.rid = job.rid
				INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.translation_id = translation_status.translation_id
				INNER JOIN {$wpdb->prefix}icl_translations original
					ON original.trid = translation.trid AND original.source_language_code IS NULL
				WHERE job.job_id = %d
					AND job.sent_from IN ( %d, %d )
					AND LEFT( original.element_type, 5 ) = 'post_'
				LIMIT 1",
				$jobId,
				Jobs::SENT_AUTOMATICALLY,
				Jobs::SENT_VIA_DASHBOARD
			)
		);
	}
}
