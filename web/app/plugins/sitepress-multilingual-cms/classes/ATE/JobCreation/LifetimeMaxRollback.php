<?php

namespace WPML\TM\ATE\JobCreation;

use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;

class LifetimeMaxRollback {

	const EVENT_RESTORED = 'lifetime_max_restored_after_unconfirmed_create';

	const EVENT_ADOPTED = 'lifetime_max_set_from_adopted_create';

	private $before = [];

	private $unconfirmedBefore = [];

	private $sent = [];

	private $confirmed = [];

	public function remember( array $jobIds ) {
		foreach ( $jobIds as $jobId ) {
			$jobId = (int) $jobId;
			if ( $jobId > 0 && ! array_key_exists( $jobId, $this->before ) ) {
				$this->before[ $jobId ]            = self::read( $jobId, 'wpml_words_to_translate_count_lifetime_max' );
				$this->unconfirmedBefore[ $jobId ] = self::read( $jobId, 'wpml_words_to_translate_count_unconfirmed_max' );
			}
		}
	}

	public function sent( array $jobIds ) {
		foreach ( $jobIds as $jobId ) {
			$this->sent[ (int) $jobId ] = true;
		}
	}

	public function confirmed( array $jobIds ) {
		foreach ( $jobIds as $jobId ) {
			$jobId                     = (int) $jobId;
			$this->confirmed[ $jobId ] = true;

			if ( isset( $this->unconfirmedBefore[ $jobId ] ) ) {
				Jobs::setWordsUnconfirmedMax( $jobId, null );
				$this->unconfirmedBefore[ $jobId ] = null;
			}
		}
	}

	public function finish() {
		foreach ( $this->before as $jobId => $before ) {
			if ( isset( $this->confirmed[ $jobId ] ) ) {
				continue;
			}

			$now = self::read( $jobId, 'wpml_words_to_translate_count_lifetime_max' );
			if ( $now === $before ) {
				continue;
			}

			if ( isset( $this->sent[ $jobId ] ) ) {
				if ( null !== $now && $now > (int) $before ) {
					Jobs::setWordsUnconfirmedMax( $jobId, $now );
				}
			} elseif ( empty( $this->unconfirmedBefore[ $jobId ] ) ) {
				Jobs::setWordsUnconfirmedMax( $jobId, 0 );
			}

			Jobs::restoreWordsLifetimeMax( $jobId, $before );

			JobLog::add(
				self::EVENT_RESTORED,
				[
					'job_id' => $jobId,
					'from'   => $now,
					'to'     => $before,
					'sent'   => isset( $this->sent[ $jobId ] ),
				]
			);
		}

		$this->before            = [];
		$this->unconfirmedBefore = [];
		$this->sent              = [];
		$this->confirmed         = [];
	}

	public static function adopted( array $jobIds ) {
		foreach ( $jobIds as $jobId ) {
			$jobId    = (int) $jobId;
			$declared = self::read( $jobId, 'wpml_words_to_translate_count_unconfirmed_max' );

			if ( null === $declared ) {
				continue;
			}

			if ( $declared > 0 ) {
				$now = self::read( $jobId, 'wpml_words_to_translate_count_lifetime_max' );
				if ( null === $now || $declared > $now ) {
					Jobs::setWordsLifetimeMax( $jobId, $declared );
					JobLog::add(
						self::EVENT_ADOPTED,
						[
							'job_id' => $jobId,
							'from'   => $now,
							'to'     => $declared,
						]
					);
				}
			}

			Jobs::setWordsUnconfirmedMax( $jobId, null );
		}
	}

	private static function read( $jobId, $column ) {
		global $wpdb;

		if ( 'wpml_words_to_translate_count_unconfirmed_max' === $column ) {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT wpml_words_to_translate_count_unconfirmed_max
					 FROM {$wpdb->prefix}icl_translate_job
					 WHERE job_id = %d",
					$jobId
				)
			);
		} else {
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT wpml_words_to_translate_count_lifetime_max
					 FROM {$wpdb->prefix}icl_translate_job
					 WHERE job_id = %d",
					$jobId
				)
			);
		}

		return null === $value ? null : (int) $value;
	}
}
