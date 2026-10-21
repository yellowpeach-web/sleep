<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\FP\Either;
use WPML\TM\ATE\API\AmsCredentialsStorage;
use WPML\TM\ATE\API\AmsRequestSigner;

class AttachedCopy {

	const NO_ANSWER = 'no_answer';

	const REFUSED = 'refused';

	const INCOMPLETE = 'incomplete';

	private $signer;

	private $endpoints;

	private $credentialsStorage;

	public function __construct(
		AmsRequestSigner $signer,
		\WPML_TM_ATE_AMS_Endpoints $endpoints,
		AmsCredentialsStorage $credentialsStorage
	) {
		$this->signer             = $signer;
		$this->endpoints          = $endpoints;
		$this->credentialsStorage = $credentialsStorage;
	}

	public function ask( string $sharedKey, string $websiteUuid, ?string $secret = null ): Either {
		$url = $this->endpoints->get_ams_copy_attached();

		MigrationLogger::copyRequestSent( $url );

		$response = $this->signer->send(
			$url,
			'POST',
			[
				'shared_key'                  => $sharedKey,
				'website_uuid'                => $websiteUuid,
				'respect_previous_disconnect' => 'true',
			],
			$secret
		);

		MigrationLogger::copyResponse( $response );

		$code = is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;

		if ( $code >= 400 && $code < 500 ) {
			return Either::left( self::REFUSED );
		}

		$body = 200 === $code ? json_decode( (string) ( $response['body'] ?? '' ), true ) : null;

		if ( ! is_array( $body ) ) {
			return Either::left( self::NO_ANSWER );
		}

		if ( ! isset( $body['new_shared_key'], $body['new_secret_key'], $body['new_website_uuid'] ) ) {
			MigrationLogger::copyResponseInvalid( $body );

			return Either::left( self::INCOMPLETE );
		}

		return Either::right( $body );
	}

	public function store( array $body ): bool {
		return $this->credentialsStorage->store( $body );
	}
}
