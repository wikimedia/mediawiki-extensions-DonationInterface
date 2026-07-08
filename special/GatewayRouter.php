<?php

namespace MediaWiki\Extension\DonationInterface\Special;

use ConfigurationReader;
use GatewayAdapter;
use MediaWiki\Config\Config;
use Psr\Log\LoggerInterface;

/**
 * Gateway selection logic shared by GatewayChooser's redirect flow and
 * the ComboWiki single-page donation flow, which picks a gateway without
 * going through a redirect.
 */
class GatewayRouter {

	/**
	 * Get all the gateways supported for the provided inputs.
	 *
	 * @param string $country
	 * @param string|null $currency
	 * @param string $paymentMethod
	 * @param string|null $paymentSubmethod
	 * @param bool $recurring
	 * @param string|null $variant
	 * @param Config $mwConfig
	 *
	 * @return array
	 */
	public static function getSupportedGateways(
		string $country,
		?string $currency,
		string $paymentMethod,
		?string $paymentSubmethod,
		bool $recurring,
		?string $variant,
		Config $mwConfig
	): array {
		$possibleGateways = [];
		$enabledGateways = GatewayAdapter::getEnabledGateways( $mwConfig );

		// Loop over enabled gateways to find ones supported for these inputs
		foreach ( $enabledGateways as $enabledGateway ) {
			$gatewayConfig = ConfigurationReader::createForGateway( $enabledGateway, $variant, $mwConfig )
				->readConfiguration();

			// TODO Knowledge about configuration layout should be encapsulated somewhere
			// See https://phabricator.wikimedia.org/T291699

			// Check availability for country; config is a flat array, and
			// $country input and countries config are always expected.
			if ( !in_array( $country, $gatewayConfig['countries'] ) ) {
				continue;
			}

			// Check if we should include the gateway even if the currency is unsupported,
			// and, if not, check availability for this currency.
			// Currencies config is a flat array and is always expected.
			if (
				!$gatewayConfig['general']['gateway_chooser']['still_include_if_currency_is_not_supported'] &&
				$currency &&
				!in_array( $currency, $gatewayConfig['currencies'] )
			) {
				continue;
			}

			// Check availability for payment method, and, if requested, recurring;
			// in config, payment methods codes are keys of the outer array, though
			// payment_methods.yaml can also be empty.
			// $paymentMethod input is always expected.
			if ( !empty( $gatewayConfig['payment_methods'] ) ) {

				$supportedPaymentMethods = $gatewayConfig['payment_methods'];
				if ( !isset( $supportedPaymentMethods[$paymentMethod] ) ) {
					// Specified payment method not supported for this gateway
					continue;
				}

				// Check whether the payment method is restricted by country, and if so
				// skip when the donor's country is not on the list
				if (
					isset( $supportedPaymentMethods[$paymentMethod]['countries'] ) &&
					!in_array( $country, $supportedPaymentMethods[$paymentMethod]['countries'] )
				) {
					// Specified country not supported by payment method for this gateway
					continue;
				}

				// Recurring availability for the payment method is indicated by a key
				// on the associative array that is the value for the payment method
				if (
					$recurring && empty( $supportedPaymentMethods[$paymentMethod]['recurring'] )
				) {
					// Specified payment method does not support recurring for this gateway
					continue;
				}
			}

			// When a submethod is specified, check to see whether it is supported, then
			// whether it is restricted by country. If there are country restrictions, skip
			// the gateway when the donor's country is not on the list.
			if ( $paymentSubmethod && !empty( $gatewayConfig['payment_submethods'] ) ) {
				$supportedSubmethods = $gatewayConfig['payment_submethods'];
				if ( !isset( $supportedSubmethods[$paymentSubmethod] ) ) {
					// Specified submethod not supported by gateway
					continue;
				}
				$submethodConfig = $supportedSubmethods[$paymentSubmethod];
				if (
					isset( $submethodConfig['countries'] ) && !in_array( $country, $submethodConfig['countries'] )
				) {
					// Specified country not in submethod's country list for this gateway
					continue;
				}

				// Recurring availability is set at the method level but can be overridden
				// at the submethod level.
				if (
					$recurring && isset( $submethodConfig['recurring'] ) && !$submethodConfig['recurring']
				) {
					// Specified payment method does not support recurring for this gateway
					continue;
				}
			}

			$possibleGateways[] = $enabledGateway;
		}

		return $possibleGateways;
	}

	/**
	 * In here we're gonna check a predefined list of
	 * priority rules to see which of the supported gateways
	 * best fits the user parameters.
	 *
	 * Example rules would look like:
	 * $rules = [
	 *    [
	 *        'conditions' => [ 'utm_medium' => 'endowment' ],
	 *        'gateways' => [ 'gravy', 'paypal_ec' ]
	 *      ],
	 *    [
	 *      'conditions' => [
	 *        'payment_method' => 'cc',
	 *        'country' => [ 'NL', 'IL', 'FR' ]
	 *      ],
	 *      'gateways' => [ 'adyen', 'gravy' ]
	 *    ],
	 *    [
	 *        # No conditions, this is treated as default.
	 *        # Should be last in the list as it will always match.
	 *        'gateways' => [ 'gravy', 'adyen', 'paypal_ec', 'dlocal', 'braintree' ]
	 *      ]
	 * ];
	 *
	 * @param array $supportedGateways List of gateway codes assumed to
	 *  support the requested country / currency / payment_method
	 * @param array $params Query-string parameters
	 * @param Config $mwConfig
	 * @param LoggerInterface $logger
	 *
	 * @return string|null Selected gateway code
	 */
	public static function chooseGatewayByPriority(
		$supportedGateways,
		$params,
		Config $mwConfig,
		LoggerInterface $logger
	): ?string {
		$rules = $mwConfig->get( 'DonationInterfaceGatewayPriorityRules' );

		foreach ( $rules as $rule ) {
			// Do our $params match all the conditions for this rule?
			// A rule with no conditions will always be matched.
			$ruleMatches = true;
			if ( isset( $rule['conditions'] ) ) {
				// Loop over all the conditions looking for any that don't match
				foreach ( $rule['conditions'] as $conditionName => $conditionValue ) {
					// If the key of a condition is not in the params, the rule does not match
					if ( !isset( $params[$conditionName] ) ) {
						$ruleMatches = false;
						break;
					}
					// Condition value is a list, e.g. of countries
					if ( is_array( $conditionValue ) ) {
						if ( in_array( $params[$conditionName], $conditionValue ) ) {
							continue;
						} else {
							$ruleMatches = false;
							break;
						}
					}
					// Condition value is a scalar, just check it against the param value
					if ( $params[$conditionName] == $conditionValue ) {
						continue;
					} else {
						$ruleMatches = false;
						break;
					}
				}
			}
			if ( $ruleMatches ) {
				// Find the first in the rule's gateways list which is in $supportedGateways
				foreach ( $rule['gateways'] as $ruleGateway ) {
					if ( in_array( $ruleGateway, $supportedGateways ) ) {
						return $ruleGateway;
					}
				}
				// Complain, this is fishy. If for example a rule states that all endowment donations
				// should go to gateways X and Y, and we get to this point, it means an endowment
				// donation has come in for a method or country not supported by gateways X or Y.
				$conditionMessage = isset( $rule['conditions'] ) ? 'rule with conditions ' .
					print_r( $rule['conditions'], true ) : 'default rule';
				$logger->warning(
					'Matched ' .
					$conditionMessage .
					' ' .
					'and parameters ' .
					print_r( $params, true ) .
					', but rule gateway list includes ' .
					'none of supported gateways (' .
					implode( ',', $supportedGateways ) .
					')'
				);
			}
		}

		// We only had one supported gateway, but no rules matched or the matching rule didn't include
		// the supported gateway. Dealing with this here rather than at top of method, so that we hit
		// the code to log a warning if a matched rule points to an unsupported gateway.
		if ( count( $supportedGateways ) === 1 ) {
			return $supportedGateways[0];
		}
		// Multiple gateways supported, but no rule matched. Warn and return the first supported gateway.
		if ( count( $supportedGateways ) > 1 ) {
			$logger->warning(
				'No rules matched parameters ' .
				print_r( $params, true ) .
				'; arbitrarily ' .
				'choosing from supported gateways (' .
				implode( ',', $supportedGateways ) .
				'). ' .
				'Consider adding a default rule (one with no conditions) to the end of ' .
				'$wgDonationInterfaceGatewayPriorityRules'
			);

			return $supportedGateways[0];
		}

		// No gateways were supported in the first place - return null and trigger an error page
		return null;
	}
}
