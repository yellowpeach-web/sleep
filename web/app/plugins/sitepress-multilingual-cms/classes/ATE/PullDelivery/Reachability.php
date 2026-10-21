<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Core\SharedKernel\Component\ATE\Application\Repository\AteReachabilityRepositoryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\TM\ATE\ClonedSites\ApiCommunication;

class Reachability {

	public function recheck() {
		global $wpml_dic;

		if ( ! $wpml_dic || ! interface_exists( AtePingerInterface::class ) ) {
			return false;
		}

		if ( class_exists( ApiCommunication::class ) ) {
			ApiCommunication::forceProbe();
		}

		try {
			$pinger = $wpml_dic->make( AtePingerInterface::class );

			$verdict = $pinger->checkReachability( AtePingerInterface::TRIGGER_RECHECK );

			return null === $verdict ? null : (bool) $verdict;
		} catch ( \Throwable $e ) {
			return false;
		} finally {
			if ( class_exists( ApiCommunication::class ) ) {
				ApiCommunication::cancelForcedProbe();
			}
		}
	}

	public function isRecordedReachable() {
		return true === $this->recorded();
	}

	public function hasRecord() {
		return null !== $this->recorded();
	}

	public function isRecordedUnreachable() {
		return false === $this->recorded();
	}

	private function recorded() {
		global $wpml_dic;

		if ( ! $wpml_dic || ! interface_exists( AteReachabilityRepositoryInterface::class ) ) {
			return null;
		}

		try {
			$repository = $wpml_dic->make( AteReachabilityRepositoryInterface::class );

			$answer = $repository->get();

			return is_bool( $answer ) ? $answer : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
