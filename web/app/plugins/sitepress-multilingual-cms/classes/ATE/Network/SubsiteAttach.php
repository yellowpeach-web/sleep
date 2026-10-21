<?php

namespace WPML\TM\ATE\Network;

use WPML\TM\ATE\ClonedSites\AttachedCopy;

class SubsiteAttach {

	const NOT_APPLICABLE = 'not_applicable';

	const ATTACHED = 'attached';

	const PENDING = 'pending';

	const OWN_ACCOUNT = 'own_account';

	private $attachedCopy;

	private $auth;

	public function __construct( AttachedCopy $attachedCopy, \WPML_TM_ATE_Authentication $auth ) {
		$this->attachedCopy = $attachedCopy;
		$this->auth         = $auth;
	}

	public function beforeRegistration(): string {
		if ( ! self::isSubsite() || $this->auth->has_keys() ) {
			return self::NOT_APPLICABLE;
		}

		if ( SubsiteAttachState::SEPARATE === SubsiteAttachState::status() ) {
			return self::OWN_ACCOUNT;
		}

		$main = self::mainSiteAccount();
		if ( ! $main ) {
			return self::NOT_APPLICABLE;
		}

		return $this->attachedCopy
			->ask( $main['shared'], $main['uuid'], $main['secret'] )
			->coalesce( fn( $failure ) => $this->onFailure( $failure ), fn( $body ) => $this->onCopy( $body ) )
			->get();
	}

	private function onFailure( string $failure ): string {
		if ( AttachedCopy::REFUSED === $failure ) {
			SubsiteAttachState::markRefused();

			return self::OWN_ACCOUNT;
		}

		SubsiteAttachState::recordFailure( $failure );

		return self::PENDING;
	}

	private function onCopy( array $body ): string {
		if ( ! $this->attachedCopy->store( $body ) ) {
			$this->auth->reset();

			return $this->onFailure( 'credentials_not_stored' );
		}

		SubsiteAttachState::markAttached();

		return self::ATTACHED;
	}

	public static function isSubsite(): bool {
		return is_multisite() && ! is_main_site();
	}

	public static function mainSiteAccount() {
		global $wpdb;

		$uuidKey        = \WPML_Site_ID::SITE_ID_KEY . ':' . \WPML_TM_ATE::SITE_ID_SCOPE;
		$previousBlogId = $wpdb->set_blog_id( get_main_site_id() );
		try {
			$values = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
					\WPML_TM_ATE_Authentication::AMS_DATA_KEY,
					$uuidKey
				),
				OBJECT_K
			);
		} finally {
			$wpdb->set_blog_id( $previousBlogId );
		}

		$data = maybe_unserialize( $values[ \WPML_TM_ATE_Authentication::AMS_DATA_KEY ]->option_value ?? null );
		$uuid = (string) ( $values[ $uuidKey ]->option_value ?? '' );

		if ( ! is_array( $data ) || empty( $data['shared'] ) || empty( $data['secret'] ) || '' === $uuid ) {
			return null;
		}

		return [
			'shared' => (string) $data['shared'],
			'secret' => (string) $data['secret'],
			'uuid'   => $uuid,
		];
	}
}
