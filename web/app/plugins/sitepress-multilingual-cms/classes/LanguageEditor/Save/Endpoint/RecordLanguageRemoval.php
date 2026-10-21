<?php

namespace WPML\LanguageEditor\Save\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\OperationRecord\Repository;
use WPML\Posts\TranslatedContentOfLanguages;

class RecordLanguageRemoval implements IHandler {

	const ERROR_STILL_ACTIVE = 'still_active';

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$codes = $this->codes( $data );

		if ( ! $codes ) {
			return Either::left( array( 'error' => 'missing_code' ) );
		}

		foreach ( $codes as $code ) {
			if ( ! $this->isRemoved( $code ) ) {
				return Either::left(
					array(
						'error' => self::ERROR_STILL_ACTIVE,
						'code'  => $code,
					)
				);
			}
		}

		$repository = new Repository();
		$records    = array();

		foreach ( $codes as $code ) {
			$id = $repository->start( Repository::KIND_LANGUAGE_REMOVAL, array( $code ) );

			$repository->finalize( $id, $this->keptCounts( $code ) );

			$records[] = array(
				'code'   => $code,
				'record' => (int) $id,
			);
		}

		return Either::right( array( 'records' => $records ) );
	}

	private function codes( Collection $data ) {
		$raw = $data->get( 'codes', array() );

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		if ( ! $raw ) {
			$raw = array( $data->get( 'code', '' ) );
		}

		$codes = array();

		foreach ( $raw as $code ) {
			$code = trim( (string) $code );

			if ( '' !== $code && ! in_array( $code, $codes, true ) ) {
				$codes[] = $code;
			}
		}

		return $codes;
	}

	private function keptCounts( $code ) {
		$counts = TranslatedContentOfLanguages::counts( array( $code ) );
		$patch  = array( 'counts_add' => array() );

		foreach ( $counts['types'] as $type ) {
			$slug = isset( $type['slug'] ) ? (string) $type['slug'] : '';

			if ( '' === $slug ) {
				continue;
			}

			$patch['counts_add'][ $slug ] = array( 'kept' => (int) $type['count'] );
		}

		return $patch;
	}

	private function isRemoved( $code ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM {$wpdb->prefix}icl_languages WHERE code = %s AND active <> 1",
				(string) $code
			)
		);

		return null !== $found && '' !== $found;
	}
}
