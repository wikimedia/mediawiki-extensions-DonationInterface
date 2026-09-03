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
	// Using the same session key as the adapter to ensure values like the order_id
	// and contribution tracking are accessible in the adapter class
	public static string $DONATION_DETAILS_SESSION_KEY = 'Donor';

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

	protected static array $requestQueryFieldNames = [
		'amount',
		'appeal',
		'bannerhistlog',
		'checksum',
		'city',
		'contact_id',
		'country',
		'currency',
		'email',
		'employer',
		'employer_id',
		'first_name',
		'first_name_phonetic',
		'fiscal_number',
		'frequency_interval',
		'frequency_unit',
		'full_name',
		'gateway',
		'landing_page', // previously concatenated into utm_source
		'language',
		'last_name',
		'last_name_phonetic',
		'opt_in',
		'payment_method',
		'payment_submethod',
		'phone',
		'recurring',
		'state_province',
		'street_address',
		'street_number', // for addresses in India
		'transaction_status',
		'utm_campaign',
		'utm_medium',
		'utm_source',
		'variant',
		'wmf_token',
	];

	protected static array $requestPostFieldNames = [
		'amount',
		'authorization_id',
		'bank_account_type', // adyen ach bank account type: saving or checking
		'bank_check_digit',
		'bank_code',
		'bank_name',
		'branch_code',
		'card_scheme', // Gr4vy google pay: example VISA
		'card_suffix', // first 4 digits in card number
		'city',
		'color_depth', // device fingerprinting
		'contact_hash', // deprecated in favor of 'checksum'
		'country',
		'currency',
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
		'iban',
		'initial_scheme_transaction_id',
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
				$this->setDataFromQueryParameters();
				$this->setDataFromPostParameters();
				$this->setUserIp();
				$this->setServerIp();
				$this->setReferrer();
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

	protected function setDataFromQueryParameters(): void {
		$query_values = $this->request->getQueryValues();

		foreach ( self::$requestQueryFieldNames as $var ) {
			$value_name = $var;
			/**
			 * Browsers are stripping utm_* parameters, so we allow for a wmf_ version of each
			 * one that we care about. Internally we still refer to them all with the utm_ prefix.
			 * Here we map the wmf_ versions to utm_ versions and drop the wmf_ values.
			 */
			if ( str_starts_with( $var, 'utm_' ) ) {
				$value_name = 'wmf_' . substr( $var, 4 );
			}

			if ( isset( $query_values[ $value_name ] ) ) {
				$this->dataObject->setValue( $var, $query_values[ $value_name ] );
				$this->dataObject->setSource( $var, 'get' );
			}
		}
	}

	protected function setDataFromPostParameters(): void {
		$posted_values = json_decode( $this->request->getRawInput(), true );

		if ( !$posted_values ) {
			return;
		}

		foreach ( self::$requestPostFieldNames as $var ) {
			if ( isset( $posted_values[ $var ] ) ) {
				$this->dataObject->setValue( $var, $posted_values[ $var ] );
				$this->dataObject->setSource( $var, 'post' );
			}
		}
	}

	protected function setUserIp(): void {
		try {
			$userIp = $this->request->getIP();
			if ( $userIp ) {
				$this->dataObject->setValue( 'user_ip', $userIp );
				$this->dataObject->setSource( 'user_ip', 'header' );
			}
		} catch ( \Exception $e ) {
			$this->logger->error( __FUNCTION__ . ": Error handling IP address: " . $e->getMessage() );
		}
	}

	protected function setServerIp(): void {
		$serverIp = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
		$this->dataObject->setValue( 'server_ip', $serverIp );
		$this->dataObject->setSource( 'server_ip', 'header' );
	}

	protected function setReferrer(): void {
		$parts = parse_url( $this->request->getHeader( 'referer' ) );
		$host = $parts['host'] ?? '';
		$path = $parts['path'] ?? '';
		$referer = $host . $path;
		$this->dataObject->setValue( 'referrer', $referer );
		$this->dataObject->setSource( 'referrer', 'header' );
	}

	/**
	 * Check for session data to integrate the donation data in $this->data.
	 * We do write values from the session to $this->dataObject->data only in two conditions;
	 * if the value is not already set or if the value is explicitly meant to be overwritten
	 * by session data (see $overwrite array below).
	 */
	protected function integrateDataFromSession(): void {
		$donorData = $this->request->getSessionData( self::$DONATION_DETAILS_SESSION_KEY );
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
