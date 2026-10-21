<?php

namespace WPML\Comments\Counts;

class AkismetSpamCount {

	const PRIORITY = 9;

	const CACHE_KEY = 'akismet_spam_count';

	const CACHE_GROUP = 'widget';

	public function forget() {
		if ( ! class_exists( 'Akismet_Admin', false ) ) {
			return;
		}

		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
	}
}
