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
	static protected $specs = array();

	/**
	 * Register a list of payment methods
	 *
	 * FIXME: static ownership of gateway specs is bad
	 *
	 * @param array $methods_meta map of name => method specification
	 */
	static public function registerMethods( $methods_meta ) {
		// TODO: The registration needs to be reworked.  One of the more
		// important issues is that several processors implement similar-enough
		// methods (eg, "cc"), and they should each maintain separate metadata.

		foreach ( $methods_meta as $name => $meta ) {
			if ( !array_key_exists( $name, self::$specs ) ) {
				self::$specs[$name] = array();
			}
			self::$specs[$name] = $meta + self::$specs[$name];
		}
	}

	/**
	 * Get the specification for this payment method
	 *
	 * @param string $method
	 *
	 * @return array|null method specification data
	 */
	static protected function getMethodMeta( $method ) {
		if ( array_key_exists( $method, self::$specs ) ) {
			return self::$specs[$method];
		}
		return null;
	}

	/**
	 * Convert a unique payment method name into the method/submethod form
	 *
	 * The compound form should be deprecated in favor of 
	 *
	 * @param string $id unique method identifier
	 * @return list( $payment_method, $payment_submethod )
	 */
	static public function getCompoundMethod( $id ) {
		if ( !PaymentMethod::isCompletelySpecified( $id ) ) {
			$payment_method = $id;
			$payment_submethod = null;
		} elseif ( strpos( "_", $id ) !== false ) {
			// Use the first segment as the method, and the remainder as submethod
			$segments = explode( "_", $id );
			$payment_method = array_shift( $segments );

			// If the remainder is a valid method, use it as the submethod.
			// Otherwise, we want something like (dd, dd_fr), so reuse the whole id.
			$remainder = implode( "_", $segments );
			if ( PaymentMethod::getMethodMeta( $remainder ) ) {
				$payment_submethod = $remainder;
			} else {
				$payment_submethod = $id;
			}
		} else {
			$payment_method = $id;
			$payment_submethod = $id;
		}
		return array( $payment_method, $payment_submethod );
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
	static public function parseCompoundMethod( $bareMethod, $subMethod ) {
		$parts = explode( '_', $subMethod );
		array_unshift( $parts, $bareMethod );

		if ( $parts[0] === $parts[1] ) {
			array_shift( $parts );
		}

		return implode( '_', $parts );
	}

	/**
	 * TODO: implement this function
	 * @return true if this payment method is complete enough to begin a transaction
	 */
	static public function isCompletelySpecified( $id ) {
		if ( $id === 'cc' ) return false;
		return true;
	}

	/**
	 * @return true if the $method descends from a more general $ancestor method, or if they are equal.
	 */
	static public function isInstanceOf( $method, $ancestor ) {
		do {
			if ( $method === $ancestor ) {
				return true;
			}
		} while ( $method = PaymentMethod::getParent( $method ) );

		return false;
	}

	/**
	 * Get the high-level family for this method
	 *
	 * @return string the most general ancestor of a given payment $method
	 */
	static public function getFamily( $method ) {
		while ( $parent = PaymentMethod::getParent( $method ) ) {
			$method = $parent;
		}
		return $method;
	}

	/**
	 * @param string $method
	 *
	 * @return string|null parent method name
	 */
	static protected function getParent( $method ) {
		$meta = PaymentMethod::getMethodMeta( $method );
		if ( $meta and array_key_exists( 'group', $meta ) ) {
			return $meta['group'];
		}
		return null;
	}

	/**
	 * @param string $method
	 * @param boolean $recurring
	 *
	 * @return normalized utm_source payment method component
	 */
	static public function getUtmSourceName( $method, $recurring ) {
		$source = PaymentMethod::getFamily( $method );
		if ( $recurring ) {
			$source = "r" . $source;
		}
		return $source;
	}
}
