<?php

namespace WPML\TranslationPolicy\TranslateEverything;

class TodaysGate implements GateInterface {

	public function sendsPost( int $postId, callable $todays ): bool {
		return (bool) $todays();
	}

	public function sendsOverJob( int $postId, callable $todays ): bool {
		return (bool) $todays();
	}

	public function sweepConditions( string $editorCondition, string $oldEditorCondition, string $postIdColumn, string $postTypeColumn ): array {
		return [ $editorCondition, $oldEditorCondition ];
	}

	public function opensOnClick( int $postId, callable $todays ): bool {
		return (bool) $todays();
	}

	public function prime( callable $listPostIds ) {
	}

	public function dropsAtSend( int $jobId, callable $todays ): bool {
		return (bool) $todays();
	}

	public function copiesOf( int $postId, callable $todays ): array {
		return (array) $todays();
	}
}
