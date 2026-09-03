<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use Amount;
use CountryValidation;
use DonationLoggerFactory;
use LogPrefixProvider;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\MediaWikiServices;
use MessageUtils;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;

class DataNormalizer implements LogPrefixProvider {

	protected ?DonationDetails $dataObject;
	/**
	 * @var string Once defined, store value here for easy access in logger
	 */
	protected string $contributionTrackingId = "";
	/**
	 * This maps the key name to its value, excluding the source
	 * @var array<string, string> $normalized
	 */
	public array $normalized = [];

	protected Config $mwConfig;

	protected LoggerInterface $logger;

	protected array $sourcesToNormalize = [ 'get', 'post' ];

	/**
	 * @param Config $mwConfig WMF Default Donation Interface Config.
	 */
	public function __construct( Config $mwConfig ) {
		$this->mwConfig = $mwConfig;
		$this->logger = DonationLoggerFactory::getLoggerFromParams( 'ComboWiki', true, false, '', $this );
	}

	/**
	 * Normalizes the current set of data, just after it's been
	 * pulled (or re-pulled) from a data source.
	 * Care should be taken in the normalize helper functions to write code in
	 * such a way that running them multiple times on the same array won't cause
	 * the data to stroll off into the sunset: Normalize will definitely need to
	 * be called multiple times against the same array.
	 */
	public function normalize( DonationDetails $dataObject ): void {
		$this->dataObject = $dataObject;
		// If we have it from session, store it for logger prefix
		$this->contributionTrackingId = $this->dataObject->getValue( 'contribution_tracking_id' );

		// TODO: go through these and delete / update any legacy reference
		$this->normalizeLanguage();
		$this->normalizeRecurring();
		$this->normalizeUtmSource();
		$this->normalizeCurrency();
		$this->normalizeAmount();
		$this->normalizeIpCountry();
		$this->normalizeCountry();
		$this->normalizeAppeal();

		// TODO: Reconfirm we need to cast to string all values as done in DonationData
		foreach ( $this->dataObject->getData() as $key => $value ) {
			$this->dataObject->setValue( $key, (string)$value );
		}
	}

	/**
	 * Called in normalizing functions before doing any normalization.
	 *
	 * @param string $key key to check the source for
	 * @return bool True means the source is not 'get' or 'post' but also that the value is set.
	 * TODO: if not set already, we try to set some values: should we change their source?
	 */
	protected function skipNormalization( string $key ): bool {
		$valueIsSet = $this->dataObject->isValueSet( $key );
		$source = $this->dataObject->getSource( $key );
		$sourceToSkip = !in_array( $source, $this->sourcesToNormalize );
		return $valueIsSet && $sourceToSkip;
	}

	protected function normalizeIpCountry(): void {
		if ( $this->skipNormalization( 'ip_country' ) ) {
			return;
		}
		$ipCountry = $this->dataObject->getValue( 'ip_country' );
		if ( !$ipCountry ) {
			// Try to do GeoIP lookup using Maxmind's SDK
			$ip = $this->dataObject->getValue( 'user_ip' );
			$ipCountry = CountryValidation::lookUpCountry( $ip );
			if ( $ipCountry && !CountryValidation::isValidIsoCode( $ipCountry ) ) {
				$this->logger->warning(
					"GeoIP lookup returned bogus code '$ipCountry'! No country available."
				);
			}
			$this->dataObject->setValue( 'ip_country', $ipCountry );
		}
	}

	/**
	 * Validate country code valueset and set to a 'default' value if not valid.
	 */
	protected function normalizeCountry(): void {
		if ( $this->skipNormalization( 'country' ) ) {
			return;
		}
		if ( $this->dataObject->isValueSet( 'country' ) ) {
			$country = $this->dataObject->getValue( 'country' );
			$countryUppercase = strtoupper( $country );
			if ( CountryValidation::isValidIsoCode( $countryUppercase ) ) {
				// If we have a valid country code, we're done with validation.
				return;
			} else {
				// TODO: Is this logic still needed for logging only?
				// check to see if it's one of those other codes that comes out of CN, for the logs
				// If this logs annoying quantities of nothing useful, go ahead and kill this whole else block later.
				// we're still going to try to regen.
				$near_countries = [ 'XX', 'EU', 'AP', 'A1', 'A2', 'O1' ];
				if ( !in_array( $countryUppercase, $near_countries ) ) {
					$this->logger->warning( __FUNCTION__ . ": $countryUppercase is not a country, or a recognized placeholder." );
				}
			}
		}
		$this->logger->warning( __FUNCTION__ . ': Country not set in DonationDetails data object.' );
		// TODO: we used to set 'XX' as default country code if none found, should we restore that?
		$this->dataObject->setValue( 'country', '' );
	}

	/**
	 * Sets the currency code correctly by validating the matching country.
	 */
	protected function normalizeCurrency(): void {
		if ( $this->skipNormalization( 'currency' ) ) {
			return;
		}

		$currency = false;

		if ( $this->dataObject->isValueSet( 'currency' ) ) {
			$currency = $this->dataObject->getValue( 'currency' );
			$this->dataObject->remove( 'currency' );
			$this->logger->debug( "Got currency from 'currency', now: $currency" );
		}

		if ( $currency ) {
			$currency = strtoupper( $currency );
		}
		// If it's blank or not a currency code, guess it from the country.
		if ( !$currency || !array_key_exists( $currency, CurrencyRates::getCurrencyRates() ) ) {
			// If we have a valid country code, we use it as last resort to set a missing 'currency' value
			$country = $this->dataObject->getValue( 'country' );
			if ( CountryValidation::isValidIsoCode( $country ) ) {
				$currency = NationalCurrencies::getNationalCurrency( $country );
				$this->logger->debug( "Got currency from 'country', now: $currency" );
			}
		}
		$this->dataObject->setValue( 'currency', $currency );
	}

	/**
	 * Takes all possible sources for the intended donation amount and
	 * normalizes them into the 'amount' field.
	 */
	protected function normalizeAmount(): void {
		if ( $this->skipNormalization( 'amount' ) ) {
			return;
		}
		if ( $this->dataObject->getValue( 'amount' ) === 'Other' ) {
			$this->dataObject->setValue( 'amount', $this->dataObject->getValue( 'amountGiven' ) );
		}

		$amountIsNotValidSomehow = ( !( $this->dataObject->isValueSet( 'amount' ) ) ||
			!is_numeric( $this->dataObject->getValue( 'amount' ) ) ||
			$this->dataObject->getValue( 'amount' ) <= 0 );

		if ( $amountIsNotValidSomehow &&
			( $this->dataObject->isValueSet( 'amountGiven' ) && is_numeric( $this->dataObject->getValue( 'amountGiven' ) ) )
		) {
			$this->dataObject->setValue( 'amount', $this->dataObject->getValue( 'amountGiven' ) );
		} elseif ( $amountIsNotValidSomehow &&
			( $this->dataObject->isValueSet( 'amountOther' ) && is_numeric( $this->dataObject->getValue( 'amountOther' ) ) )
		) {
			$this->dataObject->setValue( 'amount', $this->dataObject->getValue( 'amountOther' ) );
		}

		if ( !( $this->dataObject->isValueSet( 'amount' ) ) ) {
			$this->dataObject->setValue( 'amount', '0.00' );
		}

		$this->dataObject->remove( 'amountGiven' );
		$this->dataObject->remove( 'amountOther' );

		// Database can't handle more than 10^18 units of any currency - drop bigger numbers
		// right away before they cause problems in e.g. contribution_tracking table.
		if ( !is_numeric( $this->dataObject->getValue( 'amount' ) ) || $this->dataObject->getValue( 'amount' ) > 1E18 ) {
			// fail validation later, log some things.
			// FIXME: Generalize this, be more careful with user_ip.
			$mess = 'Non-numeric or nonsense Amount.';
			$keys = [
				'amount',
				'email',
				'user_ip', // to help deal with fraudulent traffic.
				'utm_campaign',
				'utm_source',
			];
			foreach ( $keys as $key ) {
				$mess .= ' ' . $key . '=' . $this->dataObject->getValue( $key );
			}
			$this->logger->debug( $mess );
			$this->dataObject->setValue( 'amount', 'invalid' );
			return;
		}

		$this->dataObject->setValue(
			'amount',
			Amount::round( (float)$this->dataObject->getValue( 'amount' ), $this->dataObject->getValue( 'currency' ) )
		);
	}

	/**
	 * Takes all possible names for recurring and normalizes them into the 'recurring' field.
	 */
	protected function normalizeRecurring(): void {
		if ( $this->skipNormalization( 'recurring' ) ) {
			return;
		}
		$truthyRecurringRawValues = [ '1', 'true', true ];
		if ( in_array( $this->dataObject->getValue( 'recurring' ), $truthyRecurringRawValues ) ) {
			$this->dataObject->setValue( 'recurring', true );
		} else {
			$this->dataObject->setValue( 'recurring', false );
		}
		// Endowment donations must be one-time only, regardless of any
		// recurring flags arriving via URL params, form post, or session.
		if (
			$this->dataObject->getValue( 'utm_medium' ) === 'endowment'
		) {
			$this->dataObject->setValue( 'recurring', false );
			$this->dataObject->remove( 'frequency_unit' );
			$this->dataObject->remove( 'frequency_interval' );
		}
	}

	/**
	 * If the language has not yet been set or is not valid, pulls the language code
	 * from the current global language object.
	 */
	protected function normalizeLanguage(): void {
		if ( $this->skipNormalization( 'language' ) ) {
			return;
		}

		$language = false;

		if ( $this->dataObject->isValueSet( 'uselang' ) ) {
			$language = $this->dataObject->getValue( 'uselang' );
		} elseif ( $this->dataObject->isValueSet( 'language' ) ) {
			$language = $this->dataObject->getValue( 'language' );
		}

		if ( $language ) {
			$language = strtolower( $language );
		}

		if ( !$language || !MediaWikiServices::getInstance()->getLanguageNameUtils()->isValidBuiltInCode( $language ) ) {
			$language = RequestContext::getMain()->getLanguage()->getCode();
		}

		$this->dataObject->setValue( 'language', $language );
		$this->dataObject->remove( 'uselang' );
	}

	/**
	 * From Code Review: this function is confusing. And I think mostly obsolete.
	 * We can send the banner, landing page, and payment method to contribution_tracking in separate fields now.
	 * I think we just need to check if we're getting banner and landing page concatenated on the querystring
	 * and split them out.
	 * ---
	 *
	 * The utm_source is structured as: banner.landing_page.payment_method_family
	 */
	protected function normalizeUtmSource(): void {
		if ( $this->skipNormalization( 'utm_source' ) ) {
			return;
		}
		$utm_source = $this->dataObject->getValue( 'utm_source' );

		if ( $this->dataObject->getValue( 'payment_method' ) ) {
			$utm_payment_method_family = $this->dataObject->getValue( 'payment_method' );
			if ( $this->dataObject->getValue( 'recurring' ) ) {
				$utm_payment_method_family = 'r' . $utm_payment_method_family;
			}
		} else {
			$utm_payment_method_family = '';
		}

		$recurring_str = var_export( $this->dataObject->getValue( 'recurring' ), true );
		$this->logger->debug( __FUNCTION__ . ": Payment method is {$this->dataObject->getValue( 'payment_method' )}, recurring = {$recurring_str}, utm_source = {$utm_payment_method_family}" );

		// split the utm_source into its parts for easier manipulation
		$source_parts = explode( ".", $utm_source );

		// If we don't have the banner or any utm_source, set it to the empty string.
		if ( empty( $source_parts[0] ) ) {
			$source_parts[0] = '';
		}

		if ( empty( $source_parts[1] ) ) {
			$source_parts[1] = '';
		}

		$source_parts[2] = $utm_payment_method_family;
		if ( empty( $source_parts[2] ) ) {
			$source_parts[2] = '';
		}

		$this->dataObject->setValue( 'landing_page', $source_parts[1] );
		// reconstruct and set the value.
		$utm_source = implode( ".", $source_parts );
		$this->dataObject->setValue( 'utm_source', $utm_source );
	}

	/**
	 * Set default appeal if unset, sanitize either way.
	 */
	protected function normalizeAppeal(): void {
		if ( $this->skipNormalization( 'appeal' ) ) {
			return;
		}
		$appeal = $this->dataObject->getValue( 'appeal' );
		if ( !$this->dataObject->isValueSet( 'appeal' ) ) {
			if ( $this->mwConfig->has( 'DonationInterfaceDefaultAppeal' ) ) {
				$appeal = $this->mwConfig->get( 'DonationInterfaceDefaultAppeal' );
			}
		}
		$this->dataObject->setValue( 'appeal', MessageUtils::makeSafe( $appeal ) );
	}

	public function getLogMessagePrefix(): string {
		$thisClassName = ( new ReflectionClass( $this ) )->getShortName();
		$contributionTrackingId = $this->contributionTrackingId;
		return "$thisClassName:$contributionTrackingId ";
	}
}
