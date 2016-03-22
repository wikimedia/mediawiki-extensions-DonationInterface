<?php

/**
 * Processor for banner history log ID. Handles the GatewayReady hook. (See
 * below.)
 */
class BannerHistoryLogIdProcessor {

	/**
	 * The URL parameter used to send the banner history log ID. Must correspond
	 * with Javascript used in banners.
	 */
	const BANNER_HISTORY_LOG_ID_PARAM = 'bannerhistlog';

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var GatewayType
	 */
	protected $gatewayAdapter;

	protected static function singleton( GatewayType $gatewayAdapter ) {
		static $instance;

		if ( !$instance ) {
			$instance = new BannerHistoryLogIdProcessor( $gatewayAdapter );
		}
		return $instance;
	}

	protected function __construct( GatewayType $gatewayAdapter ) {
		$this->gatewayAdapter = $gatewayAdapter;

		$this->logger = DonationLoggerFactory::getLogger(
			$gatewayAdapter, '_banner_history' );
	}

	/**
	 * Queue a message with the banner history ID sent on the URL, the
	 * contribution tracking ID from DonationData, and some additional data.
	 */
	protected function queueAssociationOfIds() {

		$this->logger->debug(
			'BannerHistoryLogIdProcessor::queueAssociationOfIds(): will ' .
			'push to banner-history queue if required info is available.' );

		$bannerHistoryId = $this->gatewayAdapter->getRequest()
			->getText( self::BANNER_HISTORY_LOG_ID_PARAM );

		// Campaigns may not have banner history enabled. For now, at least,
		// bow out silently if no banner history ID was sent.
		if ( !$bannerHistoryId ) {
			return;
		}

		$contributionTrackingId = $this->gatewayAdapter
			->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		if ( !$contributionTrackingId ) {
			$this->logger->info( 'No contribution tracking ID for ' .
					'banner-history queue ' . $bannerHistoryId . '.' );
			return;
		}

		$data = array(
			'freeform' => true,
			'banner_history_id' => $bannerHistoryId,
			'contribution_tracking_id' => $contributionTrackingId,
		);

		$this->logger->info( 'Pushing to banner-history queue.' );
		DonationQueue::instance()->push( $data, 'banner-history' );
	}

	/**
	 * Handler for the GatewayReady hook. This is the class's entry point.
	 *
	 * @param GatewayType $gatewayAdapter
	 * @return bool always true
	 */
	public static function onGatewayReady( GatewayType $gatewayAdapter ) {

		self::singleton( $gatewayAdapter )
			->queueAssociationOfIds();

		return true;
	}
}
