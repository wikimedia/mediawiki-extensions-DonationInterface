<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use DonateGatewayResult;
use MediaWiki\Config\Config;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Extension\DonationInterface\Logging\LoggerFactory;
use MediaWiki\Extension\DonationInterface\Special\Donate;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use Psr\Log\LoggerInterface;
use SmashPig\Core\Context;

/**
 * Tags every queue message sent while serving the ComboWiki donation flow.
 *
 * A donation touches the queues from up to three separate requests: the
 * ComboWiki page itself, the donate API call, and the result page a donor
 * returns to from a processor. Each is a fresh SmashPig Context, so each needs
 * the source type set, and it has to be set before the gateway adapter is
 * built, since constructing the adapter can already push a
 * contribution-tracking message.
 */
class Hooks implements SpecialPageBeforeExecuteHook, ApiBeforeMainHook {

	protected ?DonationDetails $donationDetails = null;
	protected LoggerInterface $logger;

	public function __construct(
		private readonly Config $config,
		protected readonly LoggerFactory $loggerFactory
	) {
		$this->donationDetails = new DonationDetails();
		$this->logger = $this->loggerFactory->getLogger(
			Donate::IDENTIFIER,
			new ComboWikiLogPrefixProvider( $this->donationDetails )
		);
	}

	/**
	 * Covers the ComboWiki page and the result page donors return to.
	 *
	 * @inheritDoc
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( $special instanceof Donate || $special instanceof DonateGatewayResult ) {
			Context::get()->setSourceType( Donate::IDENTIFIER );
		}
	}

	/**
	 * Covers the donate API call. It is the only leg that cannot identify
	 * itself from the page being served, so the Vue app flags it with
	 * 'result_page'.
	 *
	 * @inheritDoc
	 */
	public function onApiBeforeMain( &$main ) {
		$request = $main->getRequest();
		if ( $request->getVal( 'result_page' ) === Donate::IDENTIFIER ) {
			Context::get()->setSourceType( Donate::IDENTIFIER );

			// Update the contribution tracking data on donation submit
			if ( str_starts_with( (string)$request->getVal( 'action' ), 'di_donate_' ) ) {
				$this->updateContributionTracking( $request );
			}
		}
	}

	/**
	 * Send an updated message to the contribution tracking queue with the
	 * values that have changed since page load
	 */
	private function updateContributionTracking( WebRequest $request ): void {
		$details = new DonationDetails();
		// Set current values
		$sessionData = $request->getSessionData( DataIntegrator::$DONATION_DETAILS_SESSION_KEY );
		if ( !is_array( $sessionData ) || !$sessionData ) {
			// Don't update on an empty session
			return;
		}
		$details->setData( $sessionData );

		// The fields that can be changed on the page
		$submitted = [];
		$fields = [
			'amount',
			'currency',
			'country',
			'gateway',
			'payment_method',
			'payment_submethod',
			'recurring',
			'variant',
			'utm_key',
		];

		foreach ( $fields as $field ) {
			$value = $request->getVal( $field );
			// Skip fields the browser didn't send, so session values are retained
			if ( $value !== null && $value !== '' ) {
				$submitted[$field] = $value;
			}
		}

		// Update the changed values
		$details->updateValues( $submitted, 'post' );
		( new DataNormalizer( $this->config, $this->logger ) )->normalize( $details );
		( new ContributionTrackingHelper( $request, $this->config, $this->logger ) )->handleTrackingData( $details );
	}
}
