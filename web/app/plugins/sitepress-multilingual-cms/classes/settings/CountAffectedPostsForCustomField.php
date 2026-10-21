<?php

namespace WPML\TM\Settings;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class CountAffectedPostsForCustomField implements IHandler {

	private $affectedPosts;

	private $preferenceReapply;

	public function __construct( AffectedPostsQuery $affectedPosts, PreferenceReapply $preferenceReapply ) {
		$this->affectedPosts     = $affectedPosts;
		$this->preferenceReapply = $preferenceReapply;
	}

	public function run( Collection $data ) {
		$changes = (array) $data->get( 'changes', [] );
		if ( $changes ) {
			return Either::of( $this->preferenceReapply->countAffectedForChanges( $changes ) );
		}

		$customFields = array_values( array_filter( array_map( 'strval', (array) $data->get( 'customFields', [] ) ) ) );

		return Either::of( $customFields ? $this->affectedPosts->countCarriers( $customFields ) : 0 );
	}
}
