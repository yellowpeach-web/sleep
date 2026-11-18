<?php

namespace WPML\TranslationPolicy\TranslateEverything;

interface GateInterface {

	public function sendsPost( int $postId, callable $todays ): bool;

	public function sendsOverJob( int $postId, callable $todays ): bool;

	public function sweepConditions( string $editorCondition, string $oldEditorCondition, string $postIdColumn, string $postTypeColumn ): array;

	public function opensOnClick( int $postId, callable $todays ): bool;

	public function prime( callable $listPostIds );

	public function dropsAtSend( int $jobId, callable $todays ): bool;

	public function copiesOf( int $postId, callable $todays ): array;
}
