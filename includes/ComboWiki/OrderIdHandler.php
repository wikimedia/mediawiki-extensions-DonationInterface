<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

class OrderIdHandler {
	private static string $contributionTrackingIdKey = 'contribution_tracking_id';
	private static string $SessionSequenceKey = 'sequence';

	private static string $orderIdKey = 'order_id';

	protected WebRequest $request;

	protected ?DonationDetails $dataObject = null;

	/**
	 * @var string Once defined, store value here for easy access in logger
	 */
	protected string $contributionTrackingId = "";

	public function __construct( WebRequest $request, protected readonly LoggerInterface $logger ) {
		$this->request = $request;
	}

	/**
	 * This function reads from session and DonationDetails object
	 * to see if a valid order_id is set and if it matches the contribution_tracking_id
	 * on file. If not found or mismatched, it tries to generate a new order_id.
	 *
	 * @param DonationDetails $dataObject
	 * @return void
	 */
	public function handleOrderId( DonationDetails $dataObject ): void {
		$this->dataObject = $dataObject;
		$sequence = $this->request->getSessionData( self::$SessionSequenceKey );
		if ( !$sequence ) {
			$sequence = 1;
			$this->request->setSessionData( self::$SessionSequenceKey, $sequence );
			$this->logger->info( __FUNCTION__ . ": No sequence found in session, starting at 1." );
		}

		$orderId = $this->dataObject->getValue( self::$orderIdKey );
		$contributionTrackingId = $this->dataObject->getValue( self::$contributionTrackingIdKey );
		$this->contributionTrackingId = $contributionTrackingId;

		if ( !$contributionTrackingId ) {
			$this->logger->error( __FUNCTION__ . ": Missing required contribution tracking ID." );
			throw new UnexpectedValueException( __FUNCTION__ . ": Contribution tracking ID is required to set order id but non is set" );
		}

		if ( $orderId && !str_starts_with( $orderId, $contributionTrackingId ) ) {
			$this->logger->warning( __FUNCTION__ . ": order_id '{$orderId}' and contribution_tracking_id '{$contributionTrackingId}' mismatch." );
		}

		if ( !$orderId ) {
			$this->logger->info( __FUNCTION__ . ": order_id not set, generating new one with ct '{$contributionTrackingId}'." );
			$orderId = $contributionTrackingId . '.' . $sequence;
			$this->dataObject->setValue( self::$orderIdKey, $orderId );
		}
	}
}
