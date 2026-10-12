<?php

namespace MediaWiki\Extension\DonationInterface\Configuration;

use MediaWiki\Config\ServiceOptions;
use Psr\Log\LoggerInterface;

/**
 * Gateway selection logic shared by GatewayChooser's redirect flow and
 * the ComboWiki single-page donation flow, which picks a gateway without
 * going through a redirect.
 */
class GatewayRouter {

	/**
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'DonationInterfaceGatewayPriorityRules'
	];

	public function __construct(
		protected readonly ServiceOptions $options,
		protected readonly GatewayConfigurationFactory $gatewayConfigurationFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Get all the gateways supported for the provided inputs.
	 *
	 * @param string $country
	 * @param string|null $currency
	 * @param string $paymentMethod
	 * @param string|null $paymentSubmethod
	 * @param bool $onlyIncludeRecurring
	 * @param string|null $variant
	 *
	 * @return array
	 */
	public function getSupportedGateways(
		string $country,
		?string $currency,
		string $paymentMethod,
		?string $paymentSubmethod,
		bool $onlyIncludeRecurring,
		?string $variant
	): array {
		$possibleGateways = [];
		$enabledGatewayConfigs = $this->gatewayConfigurationFactory->getAllEnabledConfigurationsForVariant( $variant );

		// Loop over enabled gateways to find ones supported for these inputs
		foreach ( $enabledGatewayConfigs as $enabledGateway => $gatewayConfig ) {

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

				// If submethod is also specified on the query string, merge that
				// submethod's country / recurring configuration in to the selected
				// method's configuration, with submethod taking precedence
				$fullMethodSpecification = self::getFullMethodSpecification(
					$supportedPaymentMethods[$paymentMethod],
					$gatewayConfig['payment_submethods'] ?? [],
					$paymentSubmethod
				);

				// Check whether the payment (sub)method is restricted by country, and if so
				// skip when the donor's country is not on the list
				if (
					isset( $fullMethodSpecification['countries'] ) &&
					!in_array( $country, $fullMethodSpecification['countries'] )
				) {
					// Specified country not supported by payment method for this gateway
					continue;
				}

				// Recurring availability for the payment (sub)method is indicated by a key
				// on the associative array that is the value for the payment method
				if (
					$onlyIncludeRecurring && empty( $fullMethodSpecification['recurring'] )
				) {
					// Specified payment (sub)method does not support recurring for this gateway
					continue;
				}
			}

			// When a submethod is specified, check to see whether it is supported.
			if ( $paymentSubmethod && !empty( $gatewayConfig['payment_submethods'] ) ) {
				$supportedSubmethods = $gatewayConfig['payment_submethods'];
				if ( !isset( $supportedSubmethods[$paymentSubmethod] ) ) {
					// Specified submethod not supported by gateway
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
	 * @param LoggerInterface $logger
	 *
	 * @return string|null Selected gateway code
	 */
	public function chooseGatewayByPriority(
		$supportedGateways,
		$params,
		LoggerInterface $logger
	): ?string {
		$rules = $this->options->get( 'DonationInterfaceGatewayPriorityRules' );

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

	/**
	 * Get the payment methods available for these routing params, and the
	 * gateway that would process each one.
	 *
	 * Methods are collected from every allowed gateway before any gateway is
	 * chosen, so the list does not depend on a payment method picked in advance.
	 * When more than one allowed gateway supports a method, the priority rules
	 * pick one.
	 *
	 * Submethods are only checked when listed in $submethods. Each one available
	 * gets its own entry, so its country rules in payment_submethods.yaml apply
	 * (e.g. sepadirectdebit under rtbt, which has no country rules of its own).
	 *
	 * @param string[] $allowedGateways Gateways the caller may use
	 * @param array $params Routing params. Must include country, currency and variant.
	 * @param string[] $submethods Submethods to check, in addition to the methods
	 * @param bool $onlyIncludeRecurring Whether to only include methods supporting recurring
	 * @param LoggerInterface $logger
	 *
	 * @return array[] List of [ 'method' => string, 'gateway' => string ], plus
	 *  [ 'method' => string, 'submethod' => string, 'gateway' => string ] for submethods
	 */
	public function getSupportedPaymentMethods(
		array $allowedGateways,
		array $params,
		array $submethods,
		bool $onlyIncludeRecurring,
		LoggerInterface $logger
	): array {
		// Collect each method once, and the methods each requested submethod belongs to,
		// across all allowed gateways
		$paymentMethods = [];
		$submethodsByMethod = [];
		$enabledGatewayConfigs = $this->gatewayConfigurationFactory->getAllEnabledConfigurationsForVariant(
			$params['variant']
		);
		foreach ( $enabledGatewayConfigs as $gateway => $gatewayConfig ) {
			if ( !in_array( $gateway, $allowedGateways, true ) ) {
				continue;
			}
			foreach ( array_keys( $gatewayConfig['payment_methods'] ?? [] ) as $paymentMethod ) {
				$paymentMethods[$paymentMethod] = true;
			}
			foreach ( $gatewayConfig['payment_submethods'] ?? [] as $submethod => $submethodConfig ) {
				if ( in_array( $submethod, $submethods, true ) && isset( $submethodConfig['group'] ) ) {
					$submethodsByMethod[$submethodConfig['group']][$submethod] = true;
				}
			}
		}

		$supportedPaymentMethods = [];
		foreach ( array_keys( $paymentMethods ) as $paymentMethod ) {
			$chosenGateway = $this->chooseAllowedGateway(
				$allowedGateways,
				$params,
				$paymentMethod,
				null,
				$onlyIncludeRecurring,
				$logger
			);
			if ( $chosenGateway === null ) {
				continue;
			}

			$supportedPaymentMethods[] = [
				'method' => $paymentMethod,
				'gateway' => $chosenGateway,
			];

			foreach ( array_keys( $submethodsByMethod[$paymentMethod] ?? [] ) as $submethod ) {
				$chosenSubmethodGateway = $this->chooseAllowedGateway(
					$allowedGateways,
					$params,
					$paymentMethod,
					$submethod,
					$onlyIncludeRecurring,
					$logger
				);
				if ( $chosenSubmethodGateway === null ) {
					continue;
				}

				$supportedPaymentMethods[] = [
					'method' => $paymentMethod,
					'submethod' => $submethod,
					'gateway' => $chosenSubmethodGateway,
				];
			}
		}

		return $supportedPaymentMethods;
	}

	/**
	 * Pick the gateway for a payment (sub)method among the allowed gateways
	 * that support it for these routing params.
	 *
	 * @param string[] $allowedGateways
	 * @param array $params Routing params. Must include country, currency and variant.
	 * @param string $paymentMethod
	 * @param string|null $paymentSubmethod
	 * @param bool $onlyIncludeRecurring
	 * @param LoggerInterface $logger
	 *
	 * @return string|null The gateway, or null when no allowed gateway supports it
	 */
	private function chooseAllowedGateway(
		array $allowedGateways,
		array $params,
		string $paymentMethod,
		?string $paymentSubmethod,
		bool $onlyIncludeRecurring,
		LoggerInterface $logger
	): ?string {
		$supportedGateways = array_values(
			array_intersect(
				$this->getSupportedGateways(
					$params['country'],
					$params['currency'],
					$paymentMethod,
					$paymentSubmethod,
					$onlyIncludeRecurring,
					$params['variant']
				),
				$allowedGateways
			)
		);

		if ( count( $supportedGateways ) === 0 ) {
			return null;
		}

		if ( !empty( $params['gateway'] ) && in_array( $params['gateway'], $supportedGateways, true ) ) {
			// An explicitly requested gateway wins over the priority rules
			return $params['gateway'];
		}
		if ( count( $supportedGateways ) === 1 ) {
			return $supportedGateways[0];
		}
		return $this->chooseGatewayByPriority(
			$supportedGateways,
			array_merge( $params, [
				'payment_method' => $paymentMethod,
				'payment_submethod' => $paymentSubmethod,
			] ),
			$logger
		);
	}

	/**
	 * Get the merged method / submethod configuration if submethod is specified,
	 * or just the method configuration otherwise.
	 *
	 * @param array $paymentMethodSpecification
	 * @param array $submethodConfig
	 * @param string|null $paymentSubmethod
	 * @return array
	 */
	protected static function getFullMethodSpecification(
		array $paymentMethodSpecification, array $submethodConfig, ?string $paymentSubmethod
	) {
		if ( !$submethodConfig || !$paymentSubmethod ) {
			return $paymentMethodSpecification;
		}
		if ( !array_key_exists( $paymentSubmethod, $submethodConfig ) ) {
			return $paymentMethodSpecification;
		}
		return array_merge( $paymentMethodSpecification, $submethodConfig[$paymentSubmethod] );
	}
}
