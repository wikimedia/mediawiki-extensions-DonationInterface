<?php

namespace MediaWiki\Extension\DonationInterface\Validation;

use ConfigurationReader;
use MediaWiki\Config\Config;

class AmountHelper {

	protected Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Gets the matching donation rule for a specific gateway and set of donation data,
	 * without needing a gateway instance.
	 *
	 * @param string $gateway
	 * @param array $donationData
	 * @return array
	 */
	public function getDonationRules( string $gateway, array $donationData ) {
		$configurationReader = ConfigurationReader::createForGateway( $gateway, null, $this->config );
		$fullConfiguration = $configurationReader->readConfiguration();
		return self::lookupMatchingDonationRules( $fullConfiguration['donation_rules'], $donationData );
	}

	/**
	 * Looks up the matching ruleset for specified donation data
	 *
	 * @param array $ruleConfiguration
	 * @param array $donationData
	 * @return array
	 */
	public static function lookupMatchingDonationRules( array $ruleConfiguration, array $donationData ): array {
		foreach ( $ruleConfiguration as $rule ) {
			// Do our $params match all the conditions for this rule?
			$ruleMatches = true;
			if ( isset( $rule['conditions'] ) ) {
				// Loop over all the conditions looking for any that don't match
				foreach ( $rule['conditions'] as $conditionName => $conditionValue ) {
					$realValue = $donationData[$conditionName] ?? null;
					// If the key of a condition is not in the params, the rule does not match like recurring
					if ( $realValue === null ) {
						$ruleMatches = false;
						break;
					}
					// Condition value is a scalar, just check it against the param value like country or payment_method
					if ( $realValue == $conditionValue ) {
						continue;
					} else {
						$ruleMatches = false;
						break;
					}
				}
			}
			if ( $ruleMatches ) {
				return $rule;
			}
		}
		return [];
	}
}
