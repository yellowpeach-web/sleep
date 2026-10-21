<?php

namespace WPML\TaxonomyTermTranslation;

class TermDeleteInFlight {

	const FRAMES = 16;

	private $backtrace;

	public function __construct( $backtrace = null ) {
		$this->backtrace = is_callable( $backtrace )
			? $backtrace
			: function () {
				return debug_backtrace( 0, self::FRAMES );
			};
	}

	public function language( $taxonomy, \SitePress $sitepress ) {
		$termId = $this->termBeingDeleted( (string) $taxonomy );
		if ( ! $termId ) {
			return null;
		}

		$language = $sitepress->term_translations()->lang_code_by_termid( $termId );

		return is_string( $language ) && '' !== $language && $sitepress->is_active_language( $language )
			? $language
			: null;
	}

	private function termBeingDeleted( $taxonomy ) {
		$frames = array_values( (array) call_user_func( $this->backtrace ) );

		foreach ( $frames as $index => $frame ) {
			if ( ! $this->isFunction( $frame, 'get_option' ) ) {
				continue;
			}

			$caller = isset( $frames[ $index + 1 ] ) ? $frames[ $index + 1 ] : null;
			if (
				! $this->isFunction( $caller, 'wp_delete_term' )
				|| ! isset( $caller['args'][0], $caller['args'][1] )
				|| $taxonomy !== $caller['args'][1]
				|| ! is_numeric( $caller['args'][0] )
			) {
				return 0;
			}

			return (int) $caller['args'][0];
		}

		return 0;
	}

	private function isFunction( $frame, $function ) {
		return is_array( $frame )
			&& ! isset( $frame['class'] )
			&& isset( $frame['function'] )
			&& $function === $frame['function'];
	}
}
