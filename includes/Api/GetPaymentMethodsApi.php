<?php
namespace MediaWiki\Extension\DonationInterface\Api;

use DonationInterface;
use DonationLoggerFactory;
use MediaWiki\Api\ApiBase;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use Wikimedia\ParamValidator\ParamValidator;
use WmfFramework;

abstract class GetPaymentMethodsApi extends ApiBase {
	/**
	 * Gateway for which this API fetches the payment methods
	 * @var string
	 */
	public string $gateway = '';

	/**
	 * Fetches the payment method from the provider
	 * @param IPaymentProvider $provider
	 * @param array $params
	 * @return array
	 */
	abstract protected function getPaymentMethod( $provider, $params ): array;

	/**
	 * Sets the gateway property
	 * @return void
	 */
	abstract protected function setGateway(): void;

	public function execute() {
		if ( $this->getUser()->pingLimiter( 'getpaymentmethods' ) ) {
			return;
		}
		$this->setGateway();
		// Set up adyen
		DonationInterface::setSmashPigProvider( $this->gateway );

		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		$logger = DonationLoggerFactory::getLoggerForType(
			$className
		);
		$logger->info( 'Calling getPaymentMethods ' . WmfFramework::getIP() );

		// They need to make this call before they even display a form so we won't know amount
		$params = [
			'country' => $this->getParameter( 'country' ),
		];

		$provider = PaymentProviderFactory::getDefaultProvider();
		'@phan-var IPaymentProvider $provider';

		$this->getResult()->addValue( null, 'response', $this->getPaymentMethod( $provider, $params ) );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'country' => [ ParamValidator::PARAM_TYPE => 'string' ]
		];
	}

	/**
	 * This allows the api to be hit without being logged in
	 * @return false
	 */
	public function isReadMode() {
		return false;
	}

}
