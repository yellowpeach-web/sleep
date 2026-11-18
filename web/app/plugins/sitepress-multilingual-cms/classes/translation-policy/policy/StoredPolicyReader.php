<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\Element\API\Translations;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;
use WPML\TranslationPolicy\TranslateEverything\GateInterface;
use WPML\TranslationPolicy\TranslateEverything\PolicyGate;

class StoredPolicyReader implements PolicyReaderInterface {

	const SEND_MODE_AUTO = \WPML_TM_Translation_Batch::MODE_AUTO;

	const RESOLVER_SCOPES = [
		PolicyTable::SCOPE_POST => SetEditorMode::MODE_FOR_THIS_POST,
		PolicyTable::SCOPE_TYPE => SetEditorMode::MODE_FOR_POST_TYPE,
		PolicyTable::SCOPE_SITE => SetEditorMode::MODE_FOR_GLOBAL,
	];

	private $siteAnswer;

	private $wantsAteAccount;

	private $resolverAnswers = [];

	private $listEditor;

	private $editorFilters;

	private $translateEverythingGate;

	private $sql;

	public function __construct( PolicySql $sql ) {
		$this->sql = $sql;
	}

	public function editorSql(): EditorSqlInterface {
		return new StoredEditorSql( $this->sql );
	}

	public function listEditor(): ListEditorInterface {
		if ( null === $this->listEditor ) {
			$this->listEditor = new StoredListEditor( $this );
		}

		return $this->listEditor;
	}

	public function editorFilters(): EditorFiltersInterface {
		if ( null === $this->editorFilters ) {
			$this->editorFilters = new StoredEditorFilters( $this->sql, new LegacyFilterLog() );
		}

		return $this->editorFilters;
	}

	public function readsClassicEditorHistory(): bool {
		return false;
	}

	public function editorSwitchKeepsTranslations(): bool {
		return true;
	}

	public function carriesEditsMadeOutsideWpml(): bool {
		return true;
	}

	public function translateEverythingGate(): GateInterface {
		global $wpdb;

		if ( ! $this->translateEverythingGate ) {
			$this->translateEverythingGate = new PolicyGate( $this, $this->sql, $wpdb, \WPML\TranslationPolicy\Duplicate\CopyReader::create() );
		}

		return $this->translateEverythingGate;
	}

	public function forPost( int $postId ): PolicyAnswer {
		$postType   = (string) get_post_type( $postId );
		$originalId = (int) Translations::getOriginalId( $postId, 'post_' . $postType ) ?: $postId;

		return $this->forPostAsGiven( $originalId, $postType );
	}

	public function resolverAnswer( int $postId, bool $findOriginal ) {
		$key = $postId . ( $findOriginal ? ':original' : ':given' );
		if ( ! isset( $this->resolverAnswers[ $key ] ) ) {
			$answer = $findOriginal ? $this->forPost( $postId ) : $this->forPostAsGiven( $postId, (string) get_post_type( $postId ) );

			$this->resolverAnswers[ $key ] = [
				self::resolverEditor( $answer ),
				self::RESOLVER_SCOPES[ $answer->editorScope() ] ?? SetEditorMode::MODE_FOR_GLOBAL,
			];
		}

		return $this->resolverAnswers[ $key ];
	}

	private static function resolverEditor( PolicyAnswer $answer ): string {
		if ( PolicyTable::EDITOR_NATIVE === $answer->editor() ) {
			return \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;
		}

		$isWpml = in_array( $answer->editorScope(), [ PolicyTable::SCOPE_POST, PolicyTable::SCOPE_TYPE ], true )
			|| ( PolicyTable::SCOPE_SITE === $answer->editorScope() && PolicyTable::EDITOR_WPML === $answer->rowEditor() );

		return $isWpml ? \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_WPML : \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_DASHBOARD;
	}

	private function forPostAsGiven( int $postId, string $postType ): PolicyAnswer {
		return Precedence::resolve( $this->sql->rowsForPost( $postId, $postType ), $postType, PolicyTable::fallbackEditor() );
	}

	public function forMany( array $postIds ): array {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );

		$answers = [];
		foreach ( $this->sql->answersForPosts( $postIds, PolicyTable::fallbackEditor() ) as $row ) {
			$answers[ (int) $row['id'] ] = new PolicyAnswer( (string) $row['editor'], (string) $row['method'] );
		}

		return $answers;
	}

	public function forType( string $postType ): PolicyAnswer {
		return Precedence::resolve( $this->sql->rowsForType( $postType ), $postType, PolicyTable::fallbackEditor() );
	}

	public function site(): PolicyAnswer {
		if ( null === $this->siteAnswer ) {
			$this->siteAnswer = Precedence::resolve( $this->sql->rowsForSite(), '', PolicyTable::fallbackEditor() );
		}

		return $this->siteAnswer;
	}

	public function jobSticksToClassicEditor( int $jobId, string $sendMode = '' ): bool {
		if ( self::SEND_MODE_AUTO === $sendMode ) {
			return false;
		}

		$job = Jobs::get( $jobId );
		if ( ! $this->isPostJob( $job ) ) {
			return (bool) wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobId );
		}

		$answer = $this->forPost( (int) Obj::prop( 'original_doc_id', $job ) );

		return PolicyTable::EDITOR_CTE === $answer->editor() && PolicyTable::METHOD_PTC !== $answer->method();
	}

	public function jobMode( int $jobId ): string {
		$job = Jobs::get( $jobId );
		if ( ! $this->isPostJob( $job ) ) {
			return Jobs::isEligibleForAutomaticTranslations( $jobId ) ? 'auto' : 'manual';
		}

		$isAuto = PolicyTable::METHOD_PTC === $this->forPost( (int) Obj::prop( 'original_doc_id', $job ) )->method()
			&& AutomaticTranslationCapabilities::isLanguageEligible(
				Obj::prop( 'language_code', $job ),
				Obj::prop( 'source_language_code', $job )
			);

		return $isAuto ? 'auto' : 'manual';
	}

	public function jobTypeIsAutomatic( int $jobId ): bool {
		$job = Jobs::get( $jobId );
		if ( ! $this->isPostJob( $job ) ) {
			return ( new LegacyPolicyReader() )->jobTypeIsAutomatic( $jobId );
		}

		return PolicyTable::METHOD_PTC === $this->forPost( (int) Obj::prop( 'original_doc_id', $job ) )->method();
	}

	private function isPostJob( $job ): bool {
		return $job && 0 === strpos( (string) Obj::prop( 'original_post_type', $job ), 'post_' );
	}

	public function isSiteEditorAte(): bool {
		return PolicyTable::EDITOR_ATE === $this->site()->editor();
	}

	public function wantsAteAccount(): bool {
		if ( null === $this->wantsAteAccount ) {
			$this->wantsAteAccount = $this->isSiteEditorAte()
				|| $this->sql->anyRowWantsAteAccount( PolicyTable::EDITOR_ATE === PolicyTable::fallbackEditor() );
		}

		return $this->wantsAteAccount;
	}

	public function automaticTranslationNeedsAccountOnly(): bool {
		return true;
	}

	public function isAccountUsable(): bool {
		return \WPML_TM_ATE_Status::is_active();
	}
}
