<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\TM\ATE\API\ClientRestrictionState;

class LiveSignals {

	public function refresh( array $state ) {
		$restricted  = $this->isClientRestricted();
		$debtorBlock = $restricted ? $this->clientRestrictionDebtorBlock() : '';
		$spendCap    = $this->spendCap();

		$sameRestriction = $restricted === (bool) $state['client_restricted']
			&& $debtorBlock === (string) $state['client_restriction_debtor'];
		$sameCap = $spendCap === (array) ( isset( $state['spend_cap'] ) ? $state['spend_cap'] : [] );

		if ( $sameRestriction && $sameCap ) {
			return $state;
		}

		return State::update(
			[
				'client_restricted'         => $restricted,
				'client_restriction_debtor' => $debtorBlock,
				'spend_cap'                 => $spendCap,
			]
		);
	}

	protected function isClientRestricted() {
		return ClientRestrictionState::isRestricted();
	}

	protected function clientRestrictionDebtorBlock(): string {
		return ClientRestrictionState::debtorBlock();
	}

	protected function spendCap() {
		return Collector::spendCapForState();
	}
}
