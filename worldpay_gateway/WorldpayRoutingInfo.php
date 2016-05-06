<?php

class WorldpayRoutingInfo
	implements StagingHelper
{
	/**
	 * @throws RuntimeException
	 */
	public function stage( GatewayType $adapter, $normalized, &$staged_data ) {
		$transaction = $adapter->getCurrentTransaction();

		switch ( $transaction ) {
			case 'QueryAuthorizeDeposit':
			case 'GenerateToken':
			case 'QueryTokenData':
				$merchantId = $adapter->getAccountConfig( 'TokenizingMerchantID' );
				$merchantIdTable = $adapter->getAccountConfig( 'MerchantIDs' );
				$merchantConfig = $merchantIdTable[$merchantId];
				$staged_data['wp_merchant_id'] = $merchantId;
				$staged_data['username'] = $merchantConfig['Username'];
				$staged_data['user_password'] = $merchantConfig['Password'];
				break;
			default:
				$submethod = $normalized['payment_submethod'];
				$country = $normalized['country'];
				$currency = $normalized['currency_code'];

				$merchantId = null;
				$storeId = null;
				foreach( $adapter->getAccountConfig( 'StoreIDs' ) as $storeConfig => $info ) {
					list( $storeSubmethod, $storeCountry, $storeCurrency ) = explode( '/', $storeConfig );
					if ( ( $submethod === $storeSubmethod || $storeSubmethod === '*' ) &&
						( $country === $storeCountry || $storeCountry === '*' ) &&
						$currency === $storeCurrency
					) {
						list( $merchantId, $storeId ) = $info;

						$logger = DonationLoggerFactory::getLogger( $adapter );
						$logger->info( "Using MID: {$merchantId}, SID: {$storeId} for " .
							"submethod: {$submethod}, country: {$country}, currency: {$currency}."
						);
						break;
					}
				}

				if ( !$merchantId ) {
					throw new RuntimeException( 'Could not find account information for ' .
						"submethod: {$submethod}, country: {$country}, currency: {$currency}." );
				} else {
					$merchantIdTable = $adapter->getAccountConfig( 'MerchantIDs' );
					$merchantConfig = $merchantIdTable[$merchantId];
					$staged_data['wp_merchant_id'] = $merchantId;
					$staged_data['username'] = $merchantConfig['Username'];
					$staged_data['user_password'] = $merchantConfig['Password'];
					$staged_data['wp_storeid'] = $storeId;
				}
				break;
		}
	}
}
