<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use DonationLoggerFactory;
use LogPrefixProvider;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use UnexpectedValueException;

class OrderIdHandler implements LogPrefixProvider {

	private static string $contributionTrackingIdKey = 'contribution_tracking_id';
	private static string $SessionSequenceKey = 'sequence';

	private static string $orderIdKey = 'order_id';

	protected WebRequest $request;

	protected DonationDetails $dataObject;

	protected LoggerInterface $logger;
	/**
	 * @var string Once defined, store value here for easy access in logger
	 */
	protected string $contributionTrackingId = "";

	public function __construct( WebRequest $request ) {
		$this->request = $request;
		$this->logger = DonationLoggerFactory::getLoggerFromParams( 'ComboWiki', true, false, '', $this );
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
		}

		$orderId = $this->dataObject->getValue( self::$orderIdKey );
		$contributionTrackingId = $this->dataObject->getValue( self::$contributionTrackingIdKey );
		$this->contributionTrackingId = $contributionTrackingId;

		if ( !$contributionTrackingId ) {
			throw new UnexpectedValueException( __FUNCTION__ . ": Contribution tracking ID is required to set order id but non is set" );
		}

		if ( $orderId && !str_starts_with( $orderId, $contributionTrackingId ) ) {
			$this->logger->warning( __FUNCTION__ . ": order_id '{$orderId}' and contribution_tracking_id '{$contributionTrackingId}' mismatch." );
		}

		if ( !$orderId ) {
			$orderId = $contributionTrackingId . '.' . $sequence;

			$this->dataObject->setValue( self::$orderIdKey, $orderId );
		}
	}

	public function getLogMessagePrefix(): string {
		$thisClassName = ( new ReflectionClass( $this ) )->getShortName();
		$contributionTrackingId = $this->contributionTrackingId;
		return "$thisClassName:$contributionTrackingId ";
	}
}
