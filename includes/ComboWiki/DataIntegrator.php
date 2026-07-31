<?php
namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use DonationLoggerFactory;
use LogPrefixProvider;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * DataIntegrator
 *
 * This class is responsible for pulling all the data we need for ComboWiki
 * from the request and session and storing it in a DonationDetails object.
 *
 * @author lbarluzzi
 */
class DataIntegrator implements LogPrefixProvider {
	private static string $donationDetailsSessionKey = 'DonationDetails';

	public WebRequest $request;

	protected LoggerInterface $logger;

	/**
	 * This is our data object with getters and setters for all the data we need.
	 * We store here the data we pull from the request and session, and the
	 * sources of the data (post, get, session, or null) in the $dataSources array.
	 *
	 * @var DonationDetails
	 */
	protected DonationDetails $dataObject;

	/**
	 * TODO: Remove all the fieldNames we know we won't need for ComboWiki and add potential new ones
	 *
	 * fieldName should be the list of values we want to pull from the request and/or session
	 * currently is also the list of values we want to store in the DonationDetails object
	 * and in the DonationDetail object in the session.
	 *
	 * @var string[]
	 */
	protected static array $fieldNames = [
		'account_name',
		'account_number',
		'action',
		'amount',
		'amountGiven',
		'amountOther',
		'appeal',
		'authorization_id',
		'bank_account_type', // adyen ach bank account type: saving or checking
		'bank_check_digit',
		'bank_code',
		'bank_name',
		'bannerhistlog',
		'billing_email',
		'bin_hash',
		'branch_code',
		'card_scheme', // Gr4vy google pay: example VISA
		'card_suffix', // first 4 digits in card number
		'checksum',
		'city',
		'color_depth', // device fingerprinting
		'contact_hash', // deprecated in favor of 'checksum'
		'contact_id',
		'contribution_tracking_id', // TODO: Check why wasn't here in the list since we want to read it from session. Check the so-called computedFields
		'country',
		'country_code_bank',
		'currency',
		'customer_id', // venmo customer_id if post MC declined then remove from vault
		'cvv',
		'data_hash',
		'descriptor',
		'device_data', // needed for braintree venmo
		'direct_debit_text',
		'email',
		'employer',
		'employer_id',
		'encrypted_bank_account_number',
		'encrypted_bank_location_id',
		'encrypted_card_number',
		'encrypted_expiry_month',
		'encrypted_expiry_year',
		'encrypted_security_code',
		'expiration',
		'first_name',
		'first_name_phonetic',
		'fiscal_number',
		'frequency_interval',
		'frequency_unit',
		'full_name',
		'gateway',
		'gateway_session_id', // temporary ID for attempt used to retrieve customer info, e.g. venmo paymentContextId
		'iban',
		'initial_scheme_transaction_id',
		'ip_country',
		'issuer_id',
		'java_enabled', // device fingerprinting
		'landing_page', // previously concatenated into utm_source
		'language',
		'last_name',
		'last_name_phonetic',
		'opt_in',
		'order_id',
		'payment_method',
		'payment_submethod',
		'payment_token',
		'phone',
		'postal_code',
		'processor_form',
		'recipient_id',
		'recurring',
		'recurring_payment_token',
		'redirect',
		'referrer',
		'result_page',
		'screen_height', // device fingerprinting
		'screen_width', // device fingerprinting
		'server_ip',
		'sms_opt_in',
		'state_province',
		'street_address',
		'street_number', // for addresses in India
		'subscr_id',
		'supplemental_address_1',
		'time_zone_offset', // device fingerprinting
		'transaction_status',
		'transaction_type',
		'upi_id',
		'uselang',
		'user_ip',
		'user_name', // optional venmo name for their console
		'utm_campaign',
		'utm_medium',
		'utm_source',
		'variant',
		'wmf_campaign',
		'wmf_key',
		'wmf_medium',
		'wmf_source',
		'wmf_token',
	];

	/**
	 * @param WebRequest $request
	 * @param DonationDetails $dataObject instance for storing donation data details
	 * @param ?array $externalData An optional array of donation data that will, if
	 * present, circumvent the usual process of gathering the data from various
	 * places in the request. Defaults to null.
	 */
	public function __construct( WebRequest $request, DonationDetails $dataObject, ?array $externalData = null ) {
		$this->dataObject = $dataObject;
		$this->request = $request;
		$this->logger = DonationLoggerFactory::getLoggerFromParams( 'ComboWiki', true, false, '', $this );
		$this->populateData( $externalData );
	}

	/**
	 * populateData, called on construct, pulls donation data from various
	 * sources. Once the data has been pulled, it will handle any session data
	 * if present, normalize the data regardless of the source, and handle the
	 * caching variables.
	 * @param ?array $externalData An optional array of donation data that will,
	 * if present, circumvent the usual process of gathering the data from
	 * various places in the request. Defaults to null.
	 */
	protected function populateData( ?array $externalData ): void {
		if ( is_array( $externalData ) ) {
			try {
				$this->dataObject->setData( $externalData );
				// TODO: improve nested function call if possible with php 8.2 version support
				$this->dataObject->setDataSources( array_fill_keys( array_keys( $externalData ), 'external' ) );
			} catch ( \Exception $e ) {
				$this->logger->error( __FUNCTION__ . ": Error setting 'external_data': " . $e->getMessage() );
			}
		} else {
			try {
				foreach ( self::$fieldNames as $var ) {
					[ $val, $source ] = $this->integratedDataFromRequest( $var );
					$this->dataObject->setValue( $var, $val );
					$this->dataObject->setSource( $var, $source );
				}
			} catch ( \Exception $e ) {
				$this->logger->error( __FUNCTION__ . ": Error harvesting values from request get/post/header: " . $e->getMessage() );
			}
		}
		try {
			$this->integrateDataFromSession();
		} catch ( \Exception $e ) {
			$this->logger->error( __FUNCTION__ . ": Error integrating data from session: " . $e->getMessage() );
		}
		$this->logger->info( __FUNCTION__ . ": Data harvested from request and session" );
	}

	/**
	 * Harvest a $var from the Request's GET, POST, Headers, or Search Query params.
	 * @param string $var The incoming var name we need to get a value for
	 * @return array $example ['appeal', 'get'] First element is the final value of the var, or null if we don't actually have it.
	 *  Second element is the source of the value, null if nonexistent, get, or post
	 */
	protected function integratedDataFromRequest( string $var ): array {
		// First, harvest value from the Request (both POST and GET)
		$requestValue = $this->request->getText( $var );
		$sourceValue = isset( $requestValue ) && $requestValue !== '' ? 'post' : null;

		// Second, harvest value from request->getHeader()
		// Note: these values keep source 'post', should we change it to 'header'?
		if ( !$requestValue && $var === 'referrer' ) {
			$parts = parse_url( $this->request->getHeader( 'referer' ) );
			$host = $parts['host'] ?? '';
			$path = $parts['path'] ?? '';
			$requestValue = $host . $path;
		}
		// Third, harvest from request->getIP()
		// Note: should these values keep source 'post'?
		if ( !$requestValue && $var === 'user_ip' ) {
			try {
				$userIp = $this->request->getIP();
				if ( $userIp ) {
					$requestValue = $userIp;
				}
			} catch ( \Exception $e ) {
				$this->logger->error( __FUNCTION__ . ": Error handling IP address: " . $e->getMessage() );
			}
		}

		// Fourth, harvest from the web server global variable $_SERVER
		if ( !$requestValue && $var === 'server_ip' ) {
			$serverIp = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
			$requestValue = $serverIp;
		}

		// Fifth, override the source from 'post' to 'get' if the value
		// is not NULL, but it is present in the query string
		$queryValues = $this->request->getQueryValues();
		if ( isset( $queryValues[$var] ) && $requestValue == $queryValues[$var] ) {
			$sourceValue = 'get';
		}
		return [ $requestValue, $sourceValue ];
	}

	/**
	 * Check for session data to integrate the donation data in $this->data.
	 * We do write values from the session to $this->dataObject->data only in two conditions;
	 * if the value is not already set or if the value is explicitly meant to be overwritten
	 * by session data (see $overwrite array below).
	 */
	protected function integrateDataFromSession(): void {
		$donorData = $this->request->getSessionData( self::$donationDetailsSessionKey );
		if ( $donorData === null ) {
			return;
		}
		// fields that overwrite values pulled from other sources
		// if the session has these values, we set them
		$overwrite = [ 'referrer', 'contribution_tracking_id' ];
		foreach ( $donorData as $key => $val ) {
			if ( in_array( $key, $overwrite ) ) {
				$this->dataObject->setValue( $key, trim( (string)$val ) );
				$this->dataObject->setSource( $key, 'session' );
			} else {
				if ( !$this->dataObject->isValueSet( $key ) ) {
					// TODO: casting to str is copied from DonationData; should we do it in DataNormalizer?
					$this->dataObject->setValue( $key, trim( (string)$val ) );
					$this->dataObject->setSource( $key, 'session' );
				}
			}
		}
	}

	/**
	 * Returns a DonationDetails object containing the data from the request
	 * and session, and the sources of the data (post, get, session, or null)
	 * in the $dataSources array.
	 *
	 * @return DonationDetails
	 */
	public function getDataFromRequestAndSession(): DonationDetails {
		return $this->dataObject;
	}

	/**
	 * Automatically prefix a log message with the class name.
	 * Docs: https://www.php.net/manual/en/function.get-class.php
	 *
	 * @return string
	 */
	public function getLogMessagePrefix(): string {
		$thisClassName = ( new ReflectionClass( $this ) )->getShortName();
		$contributionTrackingId = $this->dataObject->getValue( 'contribution_tracking_id' );
		return "$thisClassName:$contributionTrackingId ";
	}
}
