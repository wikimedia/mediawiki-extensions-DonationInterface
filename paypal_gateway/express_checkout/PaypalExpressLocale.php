<?php

/**
 * The supported locales are listed at
 * https://developer.paypal.com/docs/classic/api/locale_codes/ , however, we
 * might do as well simply sending language_country no matter what.  PayPal
 * will extract the country code from our locale code anyway.
 *
 * TODO: If this strategy is successful, then move to gateway_common as
 * SimpleCountryLocale
 */
class PaypalExpressLocale
	implements StagingHelper
{
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		if ( isset( $normalized['language'] ) && isset( $normalized['country'] ) ) {
			$parts = explode( '_', $normalized['language'] );
			$staged_data['language'] = "{$parts[0]}_{$normalized['country']}";
		}
	}
}
