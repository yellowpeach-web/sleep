<?php

namespace WPML\CustomFieldTranslation;

use function WPML\PHP\Logger\notice;

class CopiedFieldResets {

	const COPY_ACTION = 'wpml_after_copy_custom_field';

	private $translationId;

	private $originalId;

	private $valuesBeforeSync;

	private $resetFields = [];

	public function __construct( $translationId, $originalId, array $valuesBeforeSync ) {
		$this->translationId    = (int) $translationId;
		$this->originalId       = (int) $originalId;
		$this->valuesBeforeSync = $valuesBeforeSync;
	}

	public static function watch( $translationId, $originalId, callable $syncWithOriginal ) {
		$valuesBeforeSync = get_post_meta( (int) $translationId );
		$resets           = new self( $translationId, $originalId, is_array( $valuesBeforeSync ) ? $valuesBeforeSync : [] );

		add_action( self::COPY_ACTION, [ $resets, 'recordCopy' ], 10, 4 );
		try {
			$syncWithOriginal();
		} finally {
			remove_action( self::COPY_ACTION, [ $resets, 'recordCopy' ], 10 );
		}

		$resets->report();
	}

	public function recordCopy( $postIdFrom, $postIdTo, $metaKey, $valuesAfterSync = [] ) {
		if ( (int) $postIdTo !== $this->translationId ) {
			return;
		}

		$valuesBeforeSync = isset( $this->valuesBeforeSync[ $metaKey ] ) ? (array) $this->valuesBeforeSync[ $metaKey ] : [];
		if ( $this->hasValue( $valuesBeforeSync ) && array_values( $valuesBeforeSync ) !== array_values( (array) $valuesAfterSync ) ) {
			$this->resetFields[ $metaKey ] = $metaKey;
		}
	}

	public function report() {
		if ( ! $this->resetFields ) {
			return;
		}

		notice(
			sprintf(
				'WPML copied the original values of the Copy fields %s from post %d over the different values saved on its translation %d.',
				implode( ', ', $this->resetFields ),
				$this->originalId,
				$this->translationId
			)
		);

		$userId = get_current_user_id();
		if ( $userId ) {
			CopiedFieldResetNotice::remember( $userId, $this->translationId, $this->originalId, array_values( $this->resetFields ) );
		}
	}

	private function hasValue( array $metaValues ) {
		return (bool) array_filter(
			$metaValues,
			function ( $metaValue ) {
				return '' !== $metaValue;
			}
		);
	}
}
