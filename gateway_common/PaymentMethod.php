<?php

/**
 * Describes payment methods.
 *
 * This currently bridges old and new code, by allowing payment methods
 * to be referenced by legacy (method, submethod) strings, or by a new,
 * unique name for each method.  Examples of how these will compare:
 *
 *     Compound (old)     Unique (new)   Family (new)
 *     ew, ew_yandex      ew_yandex      ew
 *     cc, visa           cc_visa        cc
 *     paypal, paypal     paypal         paypal
 *
 * We are deprecating the "submethod" distinction, dealing with methods
 * should be simplified by referring to a single PaymentMethod object.
 * The concept of a "family" becomes more important, this roughly maps
 * to the donor's intended payment experience, and is the field we use
 * for most reporting.
 */
class PaymentMethod {
	/**
	 * @var GatewayType
	 */
	protected $gateway;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var bool
	 */
	protected $is_recurring;

	/**
	 * Gateway definition for this payment method
	 * @var array
	 */
	protected $spec;

	/**
	 * Build a new PaymentMethod object from an name pair
	 *
	 * @param GatewayType $gateway
	 * @param string $method_name
	 * @param string|null $submethod_name
	 * @param bool $is_recurring
	 *
	 * @return PaymentMethod
	 */
	public static function newFromCompoundName(
		GatewayType $gateway, $method_name, $submethod_name, $is_recurring
	) {
		$method = new PaymentMethod();
		$method->gateway = $gateway;
		$method->name = self::parseCompoundMethod( $method_name, $submethod_name );
		$method->is_recurring = $is_recurring;

		try {
			// FIXME: I don't like that we're couple to the gateway already.
			$spec = [];
			if ( $method_name ) {
				$spec = $gateway->getPaymentMethodMeta( $method_name );
			}
			// When we have a more specific method, child metadata supercedes
			// parent metadata
			if ( $submethod_name ) {
				$spec = array_replace_recursive(
					$spec,
					$gateway->getPaymentSubmethodMeta( $submethod_name, $method_name )
				);
			}
			$method->spec = $spec;
		} catch ( Exception $ex ) {
			// Return empty method.
			$method->name = "none";
			$method->spec = [];
		}

		return $method;
	}

	/**
	 * Process an old-style payment method/submethod name into the unique form
	 *
	 * For now, this just eliminates duplicated method-submethods.
	 *
	 * @param string $bareMethod old-style payment method
	 * @param string $subMethod old-style payment submethod
	 *
	 * @return string unique method id
	 */
	public static function parseCompoundMethod( $bareMethod, $subMethod ) {
		$parts = [];
		if ( $subMethod ) {
			$parts = explode( '_', $subMethod );
		}
		array_unshift( $parts, $bareMethod );

		if ( count( $parts ) > 1 && $parts[0] === $parts[1] ) {
			array_shift( $parts );
		}

		return implode( '_', $parts );
	}

	/**
	 * Get the gateway's specification for this payment method
	 *
	 * @return array method specification data
	 */
	public function getMethodMeta() {
		return $this->spec;
	}

	/**
	 * TODO: implement this function
	 * @return bool if this payment method is complete enough to begin a transaction
	 */
	public function isCompletelySpecified() {
		if ( $this->name === 'cc' ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $ancestor
	 * @return bool if the $method descends from a more general $ancestor method, or if they are equal.
	 */
	public function isInstanceOf( $ancestor ) {
		$method = $this;
		do {
			if ( $method->name === $ancestor ) {
				return true;
			}
		} while ( $method = $method->getParent() );

		return false;
	}

	/**
	 * Get the high-level family for this method
	 *
	 * @return PaymentMethod the most general ancestor of this method
	 */
	public function getFamily() {
		$method = $this;
		while ( $parent = $method->getParent() ) {
			$method = $parent;
		}
		return $method;
	}

	/**
	 * @return PaymentMethod|null parent method or null if there is no parent
	 */
	protected function getParent() {
		if ( array_key_exists( 'group', $this->spec ) ) {
			return self::newFromCompoundName( $this->gateway, $this->spec['group'], null, $this->is_recurring );
		}
		return null;
	}

	/**
	 * @return string normalized utm_source payment method component
	 */
	public function getUtmSourceName() {
		$source = $this->getFamily()->name;
		if ( $this->is_recurring ) {
			$source = "r" . $source;
		}
		return $source;
	}
}
