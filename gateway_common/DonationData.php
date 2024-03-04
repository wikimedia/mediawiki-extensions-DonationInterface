<?php
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\SequenceGenerators;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;

/**
 * DonationData
 * This class is responsible for pulling all the data used by DonationInterface
 * from various sources. Once pulled, DonationData will then normalize and
 * sanitize the data for use by the various gateway adapters which connect to
 * the payment gateways, and through those gateway adapters, the forms that
 * provide the user interface.
 *
 * DonationData was not written to be instantiated by anything other than a
 * gateway adapter (or class descended from GatewayAdapter).
 *
 * @author khorn
 */
class DonationData implements LogPrefixProvider {
	protected $normalized = [];
	protected $dataSources = [];
	protected $gateway;
	protected $gatewayID;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * $fieldNames now just contains all the vars we want to
	 * get poked through to gateways in some form or other,
	 * from a get or post. We handle the actual
	 * normalization in normalize() helpers, below.
	 * @TODO: It would be really neat if the gateways kept
	 * track of all the things that ***only they will ever
	 * need***, and could interject those needs here...
	 * Then we could really clean up.
	 * @TODO also: Think about putting log alarms on the
	 * keys we want to see disappear forever, complete with
	 * referrer for easy total destruction.
	 * @var string[]
	 */
	protected static $fieldNames = [
		'amount',
		'amountGiven',
		'amountOther',
		'appeal',
		'color_depth', // device fingerprinting
		'contact_id',
		'contact_hash',
		'email',
		// @deprecated
		'emailAdd',
		'encrypted_card_number',
		'encrypted_expiry_month',
		'encrypted_expiry_year',
		'encrypted_security_code',
		'first_name',
		'first_name_phonetic',
		'gateway_session_id',
		'java_enabled', // device fingerprinting
		'last_name',
		'last_name_phonetic',
		'screen_height', // device fingerprinting
		'screen_width', // device fingerprinting
		'street_address',
		'supplemental_address_1',
		'time_zone_offset', // device fingerprinting
		'city',
		'state_province',
		'postal_code',
		'phone',
		'country',
		'card_num',
		'expiration',
		'cvv',
		'currency',
		'currency_code',
		'payment_method',
		'payment_submethod',
		'recurring_payment_token',
		'issuer_id',
		'order_id',
		'subscr_id',
		'referrer',
		'utm_source',
		'utm_source_id',
		'utm_medium',
		'utm_campaign',
		'utm_key',
		'wmf_source',
		'wmf_medium',
		'wmf_campaign',
		'wmf_key',
		'language',
		'uselang',
		'wmf_token',
		'data_hash',
		'action',
		'gateway',
		'descriptor',
		'account_name',
		'account_number',
		'authorization_id',
		'bank_check_digit',
		'bank_name',
		'bank_code',
		'branch_code',
		'country_code_bank',
		'date_collect',
		'direct_debit_text',
		'iban',
		'fiscal_number',
		'transaction_type',
		'processor_form',
		'recurring',
		'recurring_paypal',
		'redirect',
		'user_ip',
		'server_ip',
		'variant',
		'opt_in',
		'employer',
		'employer_id',
		'payment_token',
		'full_name',
		'upi_id',
		'initial_scheme_transaction_id',
		'device_data', // needed for braintree venom
		'user_name', // optional venmo name for their console
		'customer_id', // venmo customer_id if post MC declined then remove from vault
		'gateway_session_id', // venmo paymentContextId for retrieve customer info
		'encrypted_bank_account_number',
		'encrypted_bank_location_id',
		'bank_account_type', // adyen ach bank account type: saving or checking
	];

	/**
	 * DonationData constructor
	 * @param GatewayType $gateway
	 * @param mixed $data An optional array of donation data that will, if
	 * present, circumvent the usual process of gathering the data from various
	 * places in the request, or 'false' to gather the data the usual way.
	 * Default is false.
	 */
	public function __construct( GatewayType $gateway, $data = false ) {
		$this->gateway = $gateway;
		$this->gatewayID = $this->gateway->getIdentifier();
		$this->logger = DonationLoggerFactory::getLogger( $gateway, '', $this );
		$this->populateData( $data );
	}

	/**
	 * populateData, called on construct, pulls donation data from various
	 * sources. Once the data has been pulled, it will handle any session data
	 * if present, normalize the data regardless of the source, and handle the
	 * caching variables.
	 * @param mixed $external_data An optional array of donation data that will,
	 * if present, circumvent the usual process of gathering the data from
	 * various places in the request, or 'false' to gather the data the usual way.
	 * Default is false.
	 */
	protected function populateData( $external_data = false ) {
		$this->normalized = [];
		if ( is_array( $external_data ) ) {
			// I don't care if you're a test or not. At all.
			$this->normalized = $external_data;
			$this->dataSources = array_fill_keys( array_keys( $external_data ), 'external' );
		} else {
			foreach ( self::$fieldNames as $var ) {
				list( $val, $source ) = $this->sourceHarvest( $var );
				$this->normalized[$var] = $val;
				$this->dataSources[$var] = $source;
			}

			if ( !$this->wasPosted() ) {
				$this->setVal( 'posted', false );
			}
		}

		// if we have saved any donation data to the session, pull them in as well.
		$this->integrateDataFromSession();

		// We have some data, so normalize it.
		if ( $this->normalized ) {
			// As a side effect, this also saves contribution_tracking data.
			$this->normalize();

			// FIXME: This should be redundant now?
			$this->expungeNulls();
		}
	}

	public static function getFieldNames() {
		return self::$fieldNames;
	}

	/**
	 * Harvest a varname from its source - post, get, maybe even session eventually.
	 * @todo Provide a way that gateways can override default behavior here for individual keys.
	 * @param string $var The incoming var name we need to get a value for
	 * @return array First element is the final value of the var, or null if we don't actually have it.
	 *  Second element is the source of the value, null if nonexistant, get, or post
	 */
	protected function sourceHarvest( $var ) {
		if ( $this->gateway->isBatchProcessor() ) {
			return [ null, null ];
		}
		$ret = WmfFramework::getRequestValue( $var, null );
		$queryValues = WmfFramework::getQueryValues();
		// When a value is both on the QS and in POST, getRequestValue prefers POST
		// So if it's the same as the version from getQueryValues, say the source is
		// 'get', otherwise if there is any value at all say the source is 'post'
		if ( isset( $queryValues[$var] ) && $ret == $queryValues[$var] ) {
			$source = 'get';
		} elseif ( $ret !== null ) {
			$source = 'post';
		} else {
			$source = null;
		}
		return [ $ret, $source ];
	}

	/**
	 * populateData helper function
	 * If donor session data has been set, pull the fields in the session that
	 * are populated, and merge that with the data set we already have.
	 */
	protected function integrateDataFromSession() {
		if ( $this->gateway->isBatchProcessor() ) {
			return;
		}
		/**
		 * if the thing coming in from the session isn't already something,
		 * replace it.
		 * if it is: assume that the session data was meant to be replaced
		 * with better data.
		 * ...unless it's an explicit $overwrite
		 */
		$donorData = WmfFramework::getSessionValue( 'Donor' );
		if ( $donorData === null ) {
			return;
		}
		// fields that should always overwrite with their original values
		$overwrite = [ 'referrer', 'contribution_tracking_id' ];
		foreach ( $donorData as $key => $val ) {
			if ( !$this->isSomething( $key ) ) {
				$this->setVal( $key, $val );
				$this->dataSources[$key] = 'session';
			} else {
				if ( in_array( $key, $overwrite ) ) {
					$this->setVal( $key, $val );
				}
			}
		}
	}

	/**
	 * Returns the array of all normalized donation data.
	 *
	 * @return array
	 */
	public function getData() {
		return $this->normalized;
	}

	/**
	 * Returns the array of all normalized donation data.
	 *
	 * @return array
	 */
	public function getDataSources() {
		return $this->dataSources;
	}

	/**
	 * Tells you if a value in $this->normalized is something or not.
	 * @param string $key The field you would like to determine if it exists in
	 * a usable way or not.
	 * @return bool true if the field is something. False if it is null, or
	 * an empty string.
	 */
	public function isSomething( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			if ( $this->normalized[$key] === null || $this->normalized[$key] === '' ) {
				return false;
			}
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return the value at a key, or null if the key isn't populated.
	 *
	 * @param string $key The data field you would like to retrieve directly
	 * from $this->normalized.
	 * @return mixed The normalized value of that $key, or null if it isn't
	 * something.
	 */
	public function getVal( $key ) {
		if ( $this->isSomething( $key ) ) {
			return $this->normalized[$key];
		} else {
			return null;
		}
	}

	/**
	 * Sets a key in the normalized data array, to a new value.
	 * This function should only ever be used for keys that are not listed in
	 * DonationData::getCalculatedFields().
	 * TODO: If the $key is listed in DonationData::getCalculatedFields(), use
	 * DonationData::addData() instead. Or be a jerk about it and throw an
	 * exception. (Personally I like the second one)
	 * @param string $key The key you want to set.
	 * @param string $val The value you'd like to assign to the key.
	 */
	public function setVal( $key, $val ) {
		// Convert empty to null for consistency.
		if ( $val === '' ) {
			$val = null;
		}

		$this->normalized[$key] = (string)$val;

		// TODO: Set something dirty so that we're sure to normalize before
		// pulling data.
	}

	/**
	 * Removes a value from $this->normalized.
	 * @param string $key type
	 */
	public function expunge( $key ) {
		if ( array_key_exists( $key, $this->normalized ) ) {
			unset( $this->normalized[$key] );
		}
	}

	/**
	 * Returns an array of all the fields that get re-calculated during a
	 * normalize.
	 * This can be used on the outside when in the process of changing data,
	 * particularly if any of the recalculated fields need to be restaged by the
	 * gateway adapter.
	 * @return array An array of values matching all recalculated fields.
	 */
	public function getCalculatedFields() {
		$fields = [
			'utm_source',
			'amount',
			'order_id',
			'gateway',
			'anonymous',
			'language',
			'contribution_tracking_id', // sort of...
			'currency',
			'user_ip',
		];
		return $fields;
	}

	/**
	 * Normalizes the current set of data, just after it's been
	 * pulled (or re-pulled) from a data source.
	 * Care should be taken in the normalize helper functions to write code in
	 * such a way that running them multiple times on the same array won't cause
	 * the data to stroll off into the sunset: Normalize will definitely need to
	 * be called multiple times against the same array.
	 */
	protected function normalize() {
		// FIXME: there's a ghost invocation during DonationData construction.
		// This condition should actually be "did data come from anywhere?"
		if ( !empty( $this->normalized ) ) {
			// Cast all values to string.
			$toStringOrNull = static function ( $value ) {
				if ( $value === null || $value === '' ) {
					return null;
				}
				return (string)$value;
			};
			$this->normalized = array_map( $toStringOrNull, $this->normalized );

			$updateCtRequired = $this->handleContributionTrackingID(); // Before Order ID
			$this->setNormalizedOrderIDs();
			$this->setReferrer();
			$this->setIPAddresses();
			$this->setNormalizedRecurring();
			$this->setNormalizedPaymentMethod(); // need to do this before utm_source.
			$this->moveWmfFieldsToUtmFields();
			$this->setUtmSource();
			$this->setNormalizedAmount();
			$this->setGateway();
			$this->setLanguage();
			$this->setCountry(); // must do this AFTER setIPAddress...
			$this->setCurrencyCode(); // AFTER setCountry
			$this->setEmail();
			$this->setCardNum();
			$this->setAppeal();

			if ( $updateCtRequired ) {
				$this->saveContributionTrackingData();
			}
		}
	}

	/**
	 * normalize helper function
	 * Sets user_ip and server_ip.
	 */
	protected function setIPAddresses() {
		if ( !$this->gateway->isBatchProcessor() ) {
			// Refresh the IP from something authoritative, unless we're running
			// a batch process.
			$userIp = WmfFramework::getIP();
			if ( $userIp ) {
				$this->setVal( 'user_ip', $userIp );
			}
		}

		if ( array_key_exists( 'SERVER_ADDR', $_SERVER ) ) {
			$this->setVal( 'server_ip', $_SERVER['SERVER_ADDR'] );
		} else {
			// command line?
			$this->setVal( 'server_ip', '127.0.0.1' );
		}
	}

	/**
	 * normalize helper function
	 * Setting the country correctly. Country is... kinda important.
	 * If we have no country, or nonsense, we try to get something rational
	 * through GeoIP lookup.
	 */
	protected function setCountry() {
		$regen = true;
		$country = '';

		if ( $this->isSomething( 'country' ) ) {
			$country = strtoupper( $this->getVal( 'country' ) );
			if ( CountryValidation::isValidIsoCode( $country ) ) {
				$regen = false;
			} else {
				// check to see if it's one of those other codes that comes out of CN, for the logs
				// If this logs annoying quantities of nothing useful, go ahead and kill this whole else block later.
				// we're still going to try to regen.
				$near_countries = [ 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' ];
				if ( !in_array( $country, $near_countries ) ) {
					$this->logger->warning( __FUNCTION__ . ": $country is not a country, or a recognized placeholder." );
				}
			}
		} else {
			$this->logger->warning( __FUNCTION__ . ': Country not set.' );
		}

		// try to regenerate the country if we still don't have a valid one yet
		if ( $regen ) {
			if ( $this->gateway->isBatchProcessor() ) {
				$sessionCountry = null;
			} else {
				// If no valid country was passed, first check session.
				$sessionCountry = $this->gateway->session_getData( 'Donor', 'country' );
			}
			if ( CountryValidation::isValidIsoCode( $sessionCountry ) ) {
				$this->logger->info( "Using country code $sessionCountry from session" );
				$country = $sessionCountry;
			} else {
				// Then try to do GeoIP lookup using Maxmind's SDK
				$ip = $this->getVal( 'user_ip' );
				$country = CountryValidation::lookUpCountry( $ip );
				if ( $country && !CountryValidation::isValidIsoCode( $country ) ) {
					$this->logger->warning(
						"GeoIP lookup returned bogus code '$country'! No country available."
					);
				}
			}

			// still nothing good? Give up.
			if ( !CountryValidation::isValidIsoCode( $country ) ) {
				$country = 'XX';
			}
		}

		if ( $country != $this->getVal( 'country' ) ) {
			$this->setVal( 'country', $country );
		}
	}

	/**
	 * normalize helper function
	 * Setting the currency code correctly.
	 * Historically, this value could come in through 'currency' or
	 * 'currency_code'. After this fires, we will only have 'currency'.
	 */
	protected function setCurrencyCode() {
		// at this point, we can have either currency, or currency_code.
		// -->>currency has the authority!<<--
		$currency = false;

		if ( $this->isSomething( 'currency' ) ) {
			$currency = $this->getVal( 'currency' );
			$this->expunge( 'currency' );
			$this->logger->debug( "Got currency from 'currency', now: $currency" );
		} elseif ( $this->isSomething( 'currency_code' ) ) {
			$currency = $this->getVal( 'currency_code' );
			$this->logger->debug( "Got currency from 'currency_code', now: $currency" );
		}

		if ( $currency ) {
			$currency = strtoupper( $currency );
		}
		// If it's blank or not a currency code, guess it from the country.
		if ( !$currency || !array_key_exists( $currency, CurrencyRates::getCurrencyRates() ) ) {
			// TODO: This is going to fail miserably if there's no country yet.
			$currency = NationalCurrencies::getNationalCurrency( $this->getVal( 'country' ) );
			$this->logger->debug( "Got currency from 'country', now: $currency" );
		}

		$this->setVal( 'currency', $currency );
		$this->expunge( 'currency_code' );  // honestly, we don't want this.
	}

	/**
	 * normalize helper function.
	 * Assures that if no contribution_tracking_id is present, a row is created
	 * in the Contribution tracking table, and that row is assigned to the
	 * current contribution we're tracking.
	 * If a contribution tracking id is already present, no new rows will be
	 * assigned.
	 * If we're using the contribution tracking queue, get a contribution_tracking_id
	 * from the sequence generator. Return false because we're not creating a new record
	 * yet, so no need to update the db.
	 *
	 * @return bool True if a new record was created
	 */
	protected function handleContributionTrackingID() {
		if ( !$this->isSomething( 'contribution_tracking_id' ) ) {
			$this->setVal( 'contribution_tracking_id', $this->getIdFromSequenceGenerator() );
			return true;
		}
		return false;
	}

	/**
	 * normalize helper function.
	 * Takes all possible sources for the intended donation amount, and
	 * normalizes them into the 'amount' field.
	 */
	protected function setNormalizedAmount() {
		if ( $this->getVal( 'amount' ) === 'Other' ) {
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		}

		$amountIsNotValidSomehow = ( !( $this->isSomething( 'amount' ) ) ||
			!is_numeric( $this->getVal( 'amount' ) ) ||
			$this->getVal( 'amount' ) <= 0 );

		if ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountGiven' ) && is_numeric( $this->getVal( 'amountGiven' ) ) )
		) {
			$this->setVal( 'amount', $this->getVal( 'amountGiven' ) );
		} elseif ( $amountIsNotValidSomehow &&
			( $this->isSomething( 'amountOther' ) && is_numeric( $this->getVal( 'amountOther' ) ) )
		) {
			$this->setVal( 'amount', $this->getVal( 'amountOther' ) );
		}

		if ( !( $this->isSomething( 'amount' ) ) ) {
			$this->setVal( 'amount', '0.00' );
		}

		$this->expunge( 'amountGiven' );
		$this->expunge( 'amountOther' );

		// Database can't handle more than 10^18 units of any currency - drop bigger numbers
		// right away before they cause problems in e.g. contribution_tracking table.
		if ( !is_numeric( $this->getVal( 'amount' ) ) || $this->getVal( 'amount' ) > 1E18 ) {
			// fail validation later, log some things.
			// FIXME: Generalize this, be more careful with user_ip.
			$mess = 'Non-numeric or nonsense Amount.';
			$keys = [
				'amount',
				'utm_source',
				'utm_campaign',
				'email',
				'user_ip', // to help deal with fraudulent traffic.
			];
			foreach ( $keys as $key ) {
				$mess .= ' ' . $key . '=' . $this->getVal( $key );
			}
			$this->logger->debug( $mess );
			$this->setVal( 'amount', 'invalid' );
			return;
		}

		$this->setVal(
			'amount',
			Amount::round( $this->getVal( 'amount' ), $this->getVal( 'currency' ) )
		);
	}

	/**
	 * normalize helper function.
	 * Takes all possible names for recurring and normalizes them into the 'recurring' field.
	 */
	protected function setNormalizedRecurring() {
		if ( $this->isSomething( 'recurring_paypal' ) && ( $this->getVal( 'recurring_paypal' ) === '1' || $this->getVal( 'recurring_paypal' ) === 'true' ) ) {
			$this->setVal( 'recurring', true );
			$this->expunge( 'recurring_paypal' );
		}
		if ( $this->isSomething( 'recurring' ) && ( $this->getVal( 'recurring' ) === '1' || $this->getVal( 'recurring' ) === 'true' || $this->getVal( 'recurring' ) === true )
		) {
			$this->setVal( 'recurring', true );
		} else {
			$this->setVal( 'recurring', false );
		}
	}

	/**
	 * normalize helper function.
	 * Gets an appropriate orderID from the gateway class.
	 *
	 * @return null
	 */
	protected function setNormalizedOrderIDs(): void {
		$override = null;
		if ( $this->gateway->isBatchProcessor() ) {
			$override = $this->getVal( 'order_id' );
		}
		$this->setVal( 'order_id', $this->gateway->normalizeOrderID( $override, $this ) );

		// log the rare case where order_id is present but doesn't match the ct_id in session.
		// this is an ongoing issue T334905 as of 17/05/23
		$this->logOrderIdMismatchCheck();
	}

	/**
	 * normalize helper function.
	 * Collapses the various versions of payment method and submethod.
	 *
	 * @return null
	 */
	protected function setNormalizedPaymentMethod() {
		$method = '';
		$submethod = '';
		// payment_method and payment_submethod are currently preferred within DonationInterface
		if ( $this->isSomething( 'payment_method' ) ) {
			$method = $this->getVal( 'payment_method' );
		}

		if ( $this->isSomething( 'payment_submethod' ) ) {
			$submethod = $this->getVal( 'payment_submethod' );
		}

		$this->setVal( 'payment_method', $method );
		$this->setVal( 'payment_submethod', $submethod );
	}

	/**
	 * Sanitize user input.
	 *
	 * Intended to be used with something like array_walk.
	 *
	 * @param string &$value The value of the array
	 * @param string $key The key of the array
	 * @suppress SecurityCheck-DoubleEscaped
	 */
	protected function sanitizeInput( &$value, $key ) {
		$value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
	}

	/**
	 * normalize helper function.
	 * Sets the gateway to be the gateway that called this class in the first
	 * place.
	 */
	protected function setGateway() {
		// TODO: Hum. If we have some other gateway in the form data, should we go crazy here? (Probably)
		$gateway = $this->gatewayID;
		$this->setVal( 'gateway', $gateway );
	}

	/**
	 * normalize helper function.
	 * If the language has not yet been set or is not valid, pulls the language code
	 * from the current global language object.
	 */
	protected function setLanguage() {
		$language = false;

		if ( $this->isSomething( 'uselang' ) ) {
			$language = $this->getVal( 'uselang' );
		} elseif ( $this->isSomething( 'language' ) ) {
			$language = $this->getVal( 'language' );
		}

		if ( $language ) {
			$language = strtolower( $language );
		}

		if ( $language == false || !WmfFramework::isValidBuiltInLanguageCode( $language ) ) {
			$language = WmfFramework::getLanguageCode();
		}

		$this->setVal( 'language', $language );
		$this->expunge( 'uselang' );
	}

	/**
	 * Normalize email
	 * Check regular name, and horrible old name for values (preferring the
	 * reasonable name over the legacy version)
	 */
	protected function setEmail() {
		// Look at the old style value (because that's canonical if populated first)
		$email = $this->getVal( 'emailAdd' );
		if ( $email === null ) {
			$email = $this->getVal( 'email' );
		}

		// Also trim whitespace
		if ( $email ) {
			$email = trim( $email );
		}

		$this->setVal( 'email', $email );
		$this->expunge( 'emailAdd' );
	}

	/**
	 * Normalize card number by removing spaces if we have to.
	 */
	protected function setCardNum() {
		if ( $this->isSomething( 'card_num' ) ) {
			$this->setVal( 'card_num', str_replace( ' ', '', $this->getVal( 'card_num' ) ) );
		}
	}

	/**
	 * Normalize referrer either by passing on the original, or grabbing it in the first place.
	 */
	protected function setReferrer() {
		if ( !$this->isSomething( 'referrer' )
			&& !$this->gateway->isBatchProcessor()
		) {
			// Remove protocol and query strings to avoid tripping modsecurity
			// TODO it would be a lot more privacy respecting to omit path too.
			$referrer = '';
			$parts = parse_url( WmfFramework::getRequestHeader( 'referer' ) );
			if ( isset( $parts['host'] ) ) {
				$referrer = $parts['host'];
				if ( isset( $parts['path'] ) ) {
					$referrer .= $parts['path'];
				}
			}
			$this->setVal( 'referrer', $referrer );
		}
	}

	/**
	 * getLogMessagePrefix
	 * Constructs and returns the standard ctid:order_id log line prefix.
	 * The gateway function of identical name now calls this one, because
	 * DonationData always has fresher data.
	 * @return string "ctid:order_id "
	 */
	public function getLogMessagePrefix() {
		return $this->getVal( 'contribution_tracking_id' ) . ':' . $this->getVal( 'order_id' ) . ' ';
	}

	/**
	 * Browsers are stripping utm_* parameters, so we allow for a wmf_ version of each
	 * one that we care about. Internally we still refer to them all with the utm_ prefix.
	 * Here we map the wmf_ versions to utm_ versions and drop the wmf_ values.
	 * @return void
	 */
	protected function moveWmfFieldsToUtmFields() {
		foreach ( [ 'source', 'medium', 'campaign', 'key' ] as $suffix ) {
			$wmfFieldName = "wmf_$suffix";
			$utmFieldName = "utm_$suffix";
			if ( $this->isSomething( $wmfFieldName ) && !$this->isSomething( $utmFieldName ) ) {
				$this->setVal( $utmFieldName, $this->getVal( $wmfFieldName ) );
				$this->expunge( $wmfFieldName );
			}
		}
	}

	/**
	 * normalize helper function.
	 *
	 * the utm_source is structured as: banner.landing_page.payment_method_family
	 */
	protected function setUtmSource() {
		$utm_source = $this->getVal( 'utm_source' );
		$utm_source_id = $this->getVal( 'utm_source_id' );

		if ( $this->getVal( 'payment_method' ) ) {
			$method_object = PaymentMethod::newFromCompoundName(
				$this->gateway,
				$this->getVal( 'payment_method' ),
				$this->getVal( 'payment_submethod' ),
				$this->getVal( 'recurring' )
			);
			$utm_payment_method_family = $method_object->getUtmSourceName();
		} else {
			$utm_payment_method_family = '';
		}

		$recurring_str = var_export( $this->getVal( 'recurring' ), true );
		$this->logger->debug( __FUNCTION__ . ": Payment method is {$this->getVal( 'payment_method' )}, recurring = {$recurring_str}, utm_source = {$utm_payment_method_family}" );

		// App donations have the version coming on the utm_source eg 7.4.3.2822 and utm_campaign=iOS or Android
		// when there is no banner
		// TODO: Remove this once the apps teams have fixed it on their end T350919
		$utm_campaign = strtolower( $this->getVal( 'utm_campaign' ) );
		if ( $utm_campaign == 'ios' || $utm_campaign == 'android' ) {
			// set utm_source to appmenu if it starts with a number
			if ( preg_match( '/^\d/', $utm_source ) === 1 ) {
				$this->setVal( 'utm_source', 'appmenu.app.' . $utm_payment_method_family );
				return;
			}
		}

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// To distinguish between native (inapp) donations and web (app) donations
		// we are modifying the landing page middle parameter of the utm_source
		$utm_medium = strtolower( $this->getVal( 'utm_medium' ) );
		if ( $utm_medium == 'wikipediaapp' ) {
			$source_parts[1] = 'app';
		}

		// If we don't have the banner or any utm_source, set it to the empty string.
		if ( empty( $source_parts[0] ) ) {
			$source_parts[0] = '';
		}

		// If the utm_source_id is set, include that in the landing page
		// portion of the string.
		if ( $utm_source_id ) {
			$source_parts[1] = $utm_payment_method_family . $utm_source_id;
		} else {
			if ( empty( $source_parts[1] ) ) {
				$source_parts[1] = '';
			}
		}

		$source_parts[2] = $utm_payment_method_family;
		if ( empty( $source_parts[2] ) ) {
			$source_parts[2] = '';
		}

		// reconstruct, and set the value.
		$utm_source = implode( ".", $source_parts );
		$this->setVal( 'utm_source', $utm_source );
	}

	/**
	 * Set default appeal if unset, sanitize either way.
	 */
	protected function setAppeal() {
		if ( $this->isSomething( 'appeal' ) ) {
			$appeal = $this->getVal( 'appeal' );
		} else {
			$appeal = $this->gateway->getGlobal( 'DefaultAppeal' );
		}
		$this->setVal( 'appeal', MessageUtils::makeSafe( $appeal ) );
	}

	/**
	 * Clean array of tracking data to contain valid fields
	 *
	 * Compares tracking data array to list of valid tracking fields and
	 * removes any extra tracking fields/data.  Also sets empty values to
	 * 'null' values.
	 * @param bool $unset If set to true, empty values will be unset from the
	 * return array, rather than set to null. (default: false)
	 * @return array Clean tracking data
	 */
	public function getCleanTrackingData( $unset = false ) {
		// define valid tracking fields
		$tracking_fields = [
			'amount',
			'appeal',
			'country',
			'currency',
			'gateway',
			'language',
			'payment_method',
			'payment_submethod',
			'referrer',
			'ts',
			'utm_campaign',
			'utm_key',
			'utm_medium',
			'utm_source',
		];

		$tracking_data = [];

		foreach ( $tracking_fields as $value ) {
			if ( $this->isSomething( $value ) ) {
				$tracking_data[$value] = $this->getVal( $value );
			} else {
				if ( !$unset ) {
					$tracking_data[$value] = null;
				}
			}
		}

		// Add OS and browser, plus major version numbers of each if available
		$headers = RequestContext::getMain()->getRequest()->getAllHeaders();
		$parser = new WhichBrowser\Parser( $headers );
		$tracking_data = array_merge( $tracking_data, [
			'os' => $parser->os->getName(),
			// For versions, discard everything after the first non-digit
			'os_version' => preg_replace( '/[^0-9].*/', '', $parser->os->getVersion() ),
			'browser' => $parser->browser->getName(),
			'browser_version' => preg_replace( '/[^0-9].*/', '', $parser->browser->getVersion() ),
		] );

		// Variant is the new way to a/b test forms. Appeal is still used to
		// render wikitext at the side, but it's almost always JimmyQuote
		if ( $this->isSomething( 'variant' ) ) {
			$tracking_data['payments_form_variant'] = $this->getVal( 'variant' );
		}
		if ( $this->getVal( 'recurring' ) === '1' ) {
			$tracking_data['is_recurring'] = 1;
		}

		// TODO: remove form_amount and payments_form once we are sure we can stop
		// populating the legacy drupal.contribution_tracking table
		if ( $this->isSomething( 'currency' ) && $this->isSomething( 'amount' ) ) {
			$tracking_data['form_amount'] = $this->getVal( 'currency' ) . ' ' . $this->getVal( 'amount' );
		}
		$tracking_data['payments_form'] = $this->getVal( 'gateway' );
		if ( $this->isSomething( 'variant' ) ) {
			$tracking_data['payments_form'] .= '.v=' . $this->getVal( 'variant' );
		} elseif ( $this->isSomething( 'appeal' ) ) {
			$tracking_data['payments_form'] .= '.' . $this->getVal( 'appeal' );
		}

		return $tracking_data;
	}

	public function getIdFromSequenceGenerator( $generatorName = 'contribution-tracking' ) {
		$generator = SequenceGenerators\Factory::getSequenceGenerator( $generatorName );

		$id = $generator->getNext();

		return $id;
	}

	public function sendToContributionTrackingQueue( $tracking_data, $ctid = null, $queueName = 'contribution-tracking' ) {
		if ( !$ctid ) {
			$ctid = $this->getIdFromSequenceGenerator();
		}

		if ( !isset( $tracking_data['ts'] ) || !strlen( $tracking_data['ts'] ) ) {
			$tracking_data['ts'] = wfTimestamp( TS_MW );
		}

		$queueMessage = [
			'id' => $ctid,
			'ts' => $tracking_data['ts'],
			];

		$queueMessage = $queueMessage + $tracking_data;

		QueueWrapper::push( $queueName, $queueMessage );

		return $queueMessage['id'];
	}

	/**
	 * Inserts a new or updates a record in the contribution_tracking table.
	 *
	 * @return mixed Contribution tracking ID or false on failure
	 */
	public function saveContributionTrackingData() {
		if ( $this->gateway->isBatchProcessor() ) {
			// We aren't learning anything new about the donation, so just return.
			return false;
		}
		$ctid = $this->getVal( 'contribution_tracking_id' );
		$tracking_data = $this->getCleanTrackingData( true );
		$current_hash = sha1( serialize( $tracking_data ) );

		if ( $this->trackingDataUpdated( $current_hash ) ) {
			$ctid = $this->sendToContributionTrackingQueue( $tracking_data, $ctid );
			// Add a hash of the current tracking data to help prevent duplicate queue msgs
			WmfFramework::setSessionValue( 'ct_hash', $current_hash );
		}
		return $ctid;
	}

	/**
	 * Adds an array of data to the normalized array, and then re-normalizes it.
	 * NOTE: If any gateway is using this function, it should then immediately
	 * repopulate its own data set with the DonationData source, and then
	 * re-stage values as necessary.
	 *
	 * @param array $newdata An array of data to integrate with the existing
	 * data held by the DonationData object.
	 * @param string $source
	 */
	public function addData( $newdata, $source = 'internal' ) {
		if ( is_array( $newdata ) && !empty( $newdata ) ) {
			foreach ( $newdata as $key => $val ) {
				if ( !is_array( $val ) ) {
					$this->setVal( $key, $val );
					$this->dataSources[$key] = $source;
				}
			}
		}
		$this->normalize();
	}

	/**
	 * Returns an array of field names we typically send out in a queue
	 * message.
	 * @return array
	 */
	public static function getMessageFields() {
		return [
			'contribution_tracking_id',
			'anonymous',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'email',
			'first_name',
			'first_name_phonetic',
			'last_name',
			'last_name_phonetic',
			'street_address',
			'supplemental_address_1',
			'city',
			'state_province',
			'country',
			'postal_code',
			'gateway',
			'gateway_account',
			'gateway_txn_id',
			'order_id',
			'subscr_id',
			'recurring',
			'payment_method',
			'payment_submethod',
			'response',
			'currency',
			'amount',
			'user_ip',
			'date',
			'gateway_session_id',
			'recurring_payment_token',
			'processor_contact_id',
			'opt_in',
			'employer',
			'employer_id',
			'full_name',
			'fiscal_number',
			'initial_scheme_transaction_id',
			'encrypted_bank_account_number',
			'encrypted_bank_location_id',
			'bank_account_type',
		];
	}

	/**
	 * These fields relate to the donor contact info, not the donation.
	 * Used to build a message for the opt-in queue when a donation fails.
	 * @return string[] Fields relating to the donor's personal info
	 */
	public static function getContactFields() {
		return [
			'language',
			'email',
			'first_name',
			'full_name',
			'last_name',
			'street_address',
			'city',
			'state_province',
			'country',
			'postal_code',
		];
	}

	/**
	 * Returns an array of field names we need in order to retry a payment
	 * after the session has been destroyed by... overzealousness.
	 * @return string[] Fields to preserve when retrying a payment
	 */
	public static function getRetryFields() {
		$fields = [
			'contact_id',
			'contact_hash',
			'gateway',
			'country',
			'currency',
			'amount',
			'variant',
			'language',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'payment_method',
		];
		return $fields;
	}

	/**
	 * Returns an array of names of fields we store in session
	 * @return array
	 */
	public static function getSessionFields() {
		$fields = array_merge( self::getMessageFields(), [
			'order_id',
			'appeal',
			'variant',
			'processor_form',
			'referrer',
			'contact_id',
			'contact_hash',
			'utm_key'
		] );
		return $fields;
	}

	/**
	 * Basically, this is a wrapper for the WebRequest wasPosted function that
	 * won't give us notices if we weren't even a web request.
	 * I realize this is pretty lame.
	 * Notices, however, are more lame.
	 * @return bool
	 */
	public function wasPosted() {
		// Keeps track so we don't have to figure it out twice.
		static $posted = null;
		if ( $posted === null ) {
			$posted = ( array_key_exists( 'REQUEST_METHOD', $_SERVER ) && WmfFramework::isPosted() );
		}
		return $posted;
	}

	/**
	 * @param string $current_hash
	 * @return bool
	 */
	private function trackingDataUpdated( $current_hash ) {
		// Let's check that there's something new in $tracking_data to send to the queue
		$last_saved_hash = $this->gateway->session_getData( 'ct_hash' );
		$hasNewData = $current_hash !== $last_saved_hash;
		return $hasNewData;
	}

	private function expungeNulls() {
		foreach ( $this->normalized as $key => $val ) {
			if ( $val === null ) {
				$this->expunge( $key );
			}
		}
	}

	/**
	 * Log the rare case where order_id is present but doesn't match the ct_id in session.
	 *
	 * It's generally expected that the order_id will contain the ct_id as a prefix.
	 * The usual format is: "{contribution_tracking_id}.{sequence}". sequence is an incremented count of
	 * donation attempts by the same contribution_tracking_id.
	 *
	 * see: https://phabricator.wikimedia.org/T334905
	 * @return void
	 */
	protected function logOrderIdMismatchCheck(): void {
		$orderId = $this->getVal( 'order_id' );
		$contributionTrackingId = $this->getVal( 'contribution_tracking_id' );

		// Check that the order_id has the current ct_id as its prefix.
		if ( $orderId && $contributionTrackingId && strpos( $orderId, $contributionTrackingId ) !== 0 ) {
			$this->logger->debug( "order_id / ct_id mismatch detected" );
			$this->logger->debug( print_r( $this->getDataSources(), true ) );
		}
	}
}
