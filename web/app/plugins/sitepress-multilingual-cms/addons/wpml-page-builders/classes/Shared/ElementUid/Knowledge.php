<?php

namespace WPML\PB\ElementUid;

class Knowledge {

	const ENTITY_KIND = 'post';

	const AVAILABLE = 'wpml_element_knowledge_available';
	const GET       = 'wpml_element_knowledge';
	const RECORDS   = 'wpml_element_knowledge_records';
	const SET       = 'wpml_set_element_knowledge';
	const DELETE    = 'wpml_delete_element_knowledge';

	private $available;

	public function isAvailable() {
		if ( null === $this->available ) {
			$this->available = (bool) apply_filters( self::AVAILABLE, false );
		}

		return $this->available;
	}

	public function records( $postId, $name ) {
		if ( ! $this->isAvailable() ) {
			return [];
		}

		$rows = apply_filters(
			self::RECORDS,
			[],
			[
				'entity_kind' => self::ENTITY_KIND,
				'entity_id'   => (int) $postId,
				'name'        => $name,
			]
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function get( $postId, $part, $name ) {
		if ( ! $this->isAvailable() ) {
			return null;
		}

		$value = apply_filters(
			self::GET,
			null,
			[
				'entity_kind' => self::ENTITY_KIND,
				'entity_id'   => (int) $postId,
				'part'        => $part,
				'name'        => $name,
			]
		);

		return is_string( $value ) ? $value : null;
	}

	public function set( $postId, $part, $name, $value ) {
		if ( ! $this->isAvailable() ) {
			return;
		}

		do_action(
			self::SET,
			[
				'entity_kind' => self::ENTITY_KIND,
				'entity_id'   => (int) $postId,
				'part'        => $part,
				'name'        => $name,
				'value'       => $value,
			]
		);
	}

	public function delete( $postId, $part, $name ) {
		if ( ! $this->isAvailable() ) {
			return;
		}

		do_action(
			self::DELETE,
			[
				'entity_kind' => self::ENTITY_KIND,
				'entity_id'   => (int) $postId,
				'part'        => $part,
				'name'        => $name,
			]
		);
	}
}
