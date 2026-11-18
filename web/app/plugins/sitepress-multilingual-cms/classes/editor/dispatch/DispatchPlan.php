<?php

namespace WPML\TM\Editor\Dispatch;

interface DispatchPlan {

	const REFUSAL_NO_WORDS = 'no-words';

	const REFUSAL_IN_TRASH = 'in-trash';

	const REFUSAL_LANGUAGE_PAUSED = 'language-paused';

	public function translatesAutomatically( $trid, $postId, $targetLanguage );

	public function refusesNewAutomaticJob();

	public function refusesClick( $trid, $postId, $targetLanguage, $verb );

	public function opensClassicEditor( $jobObject, callable $getPreviousJob );

	public function pullsDelivery( $ateJobId );

	public function asksNativeEditUrl();

	public function opensRequestOnOpen();
}
