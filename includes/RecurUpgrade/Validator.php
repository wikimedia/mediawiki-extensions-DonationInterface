<?php

namespace MediaWiki\Extension\DonationInterface\RecurUpgrade;

use Config;
use MediaWiki\Session\Session;
use RecurUpgrade;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;

class Validator {

	protected Session $session;
	protected Config $config;

	/**
	 * @param Session $session used to find currency and token
	 * @param Config $config used to get configured maximum value
	 */
	public function __construct( Session $session, Config $config ) {
		$this->session = $session;
		$this->config = $config;
	}

	public function validate( array $params, bool $posted ): bool {
		if (
			empty( $params['checksum'] ) ||
			empty( $params['contact_id'] ) ||
			!is_numeric( $params['contact_id'] )
		) {
			return false;
		}
		if ( !$this->validateToken( $params, $posted ) ) {
			return false;
		}
		if ( !$this->validateAmount( $params, $posted ) ) {
			return false;
		}
		foreach ( $params as $name => $value ) {
			if ( in_array( $name, [ 'token', 'title', 'upgrade_amount', 'upgrade_amount_other' ], true ) ) {
				continue;
			}
			// The rest of the parameters should just be alphanumeric, underscore, and hyphen
			if ( !preg_match( '/^[a-zA-Z0-9_-]*$/', $value ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateToken( array $params, $posted ) {
		if ( empty( $params['token'] ) ) {
			if ( $posted ) {
				return false;
			}
		} else {
			$token = $this->session->getToken();
			if ( !$token->match( $params['token'] ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateAmount( array $params, bool $posted ): bool {
		if ( !$posted || ( isset( $params['submit'] ) && $params['submit'] === 'cancel' ) ) {
			// Not doing anything with the parameters unless we're posted, so don't worry about them
			return true;
		}
		if (
			empty( $params['upgrade_amount'] ) ||
			( $params['upgrade_amount'] === 'other' && empty( $params['upgrade_amount_other'] ) )
		) {
			return false;
		}
		if ( $params['upgrade_amount'] === 'other' ) {
			return $this->isNumberInBounds( $params['upgrade_amount_other'] );
		}
		return $this->isNumberInBounds( $params['upgrade_amount'] );
	}

	protected function isNumberInBounds( string $amount ): bool {
		if ( !is_numeric( $amount ) ) {
			return false;
		}
		$amount = floatval( $amount );
		// If the currency is in the session, use that to determine max upgrade amount
		$donorData = $this->session->get( RecurUpgrade::DONOR_DATA );
		$max = $this->getMaxInSelectedCurrency( $donorData );
		return ( $amount > 0 && $amount <= $max );
	}

	public function getMaxInSelectedCurrency( ?array $donorData ): float {
		$rates = CurrencyRates::getCurrencyRates();
		if (
			$donorData !== null &&
			!empty( $donorData['currency'] ) &&
			array_key_exists( $donorData['currency'], $rates )
		) {
			$rate = $rates[$donorData['currency']];
		} else {
			$rate = 1;
		}
		return $rate * $this->config->get( 'DonationInterfaceRecurringUpgradeMaxUSD' );
	}

	public static function isChecksumExpired( string $checksum ): bool {
		$parts = explode( '_', $checksum );
		if ( count( $parts ) !== 3 ) {
			throw new \InvalidArgumentException( 'Invalid checksum' );
		}
		$timestamp = (int)$parts[1];
		$hours = (int)$parts[2];
		return ( $timestamp + $hours * 3600 < time() );
	}
}
