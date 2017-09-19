<?php
use SmashPig\Core\DataStores\QueueWrapper;

/**
 * Processor for banner history log ID. Runs when gateway is constructed,
 * if DonationInterface global EnableBannerHistoryLog is true.
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

		$bannerHistoryId = WmfFramework::getRequestValue(
			self::BANNER_HISTORY_LOG_ID_PARAM, null );

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
			'banner_history_id' => $bannerHistoryId,
			'contribution_tracking_id' => $contributionTrackingId,
		);

		$this->logger->info( 'Pushing to banner-history queue.' );
		QueueWrapper::push( 'banner-history', $data );
	}

	/**
	 * This is the class's entry point.
	 *
	 * @param GatewayType $gatewayAdapter
	 */
	public static function onGatewayReady( GatewayType $gatewayAdapter ) {
		if ( $gatewayAdapter->getGlobal( 'EnableBannerHistoryLog' ) ) {
			self::singleton( $gatewayAdapter )->queueAssociationOfIds();
		}
	}
}
