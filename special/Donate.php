<?php /** @noinspection ALL */

namespace MediaWiki\Extension\DonationInterface\Special;

use AdyenCheckoutAdapter;
use DonationInterface;
use GatewayAdapter;
use GravyAdapter;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CLDR\CountryNames;
use MediaWiki\Extension\DonationInterface\ComboWiki\ComboWikiLogPrefixProvider;
use MediaWiki\Extension\DonationInterface\ComboWiki\ContributionTrackingHelper;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataIntegrator;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataNormalizer;
use MediaWiki\Extension\DonationInterface\ComboWiki\ForbiddenCountryRegistry;
use MediaWiki\Extension\DonationInterface\ComboWiki\OrderIdHandler;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayConfigurationFactory;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter;
use MediaWiki\Extension\DonationInterface\Logging\LoggerFactory;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use ResultPages;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;
use Subdivisions;

/**
 * ComboWiki: the single-page VueJS donation flow.
 *
 * Special page that sets up the ComboWiki Vue application.
 * It loads the Vue + styles ResourceLoader modules, sets up the
 * viewport, and exposes server-side configuration to the client through the
 * MakeGlobalVariablesScript hook using setClientVariables().
 */
class Donate extends UnlistedSpecialPage {

	/**
	 * Identifies the ComboWiki donation flow.
	 */
	public const IDENTIFIER = 'combowiki';

	protected ?Config $config = null;
	protected ?DonationDetails $dataObject = null;
	protected LoggerInterface $logger;

	/**
	 * Submethods the Vue form offers as their own payment option, so their
	 * country rules are checked on top of their method's.
	 */
	private const OFFERED_SUBMETHODS = [ 'ach', 'sepadirectdebit' ];

	/**
	 * Include all methods regardless of frequency restrictions. Because the frontend
	 * dynamically changes the frequency in the form, any filtering of payment methods
	 * (one-time, monthly, annual) should be done on the frontend.
	 */
	private const ONLY_INCLUDE_RECURRING = false;

	/**
	 * Payment methods, keyed by gateway, whose frontend form needs a checkout
	 * session before it can be set up (e.g. Gravy Secure Fields for card).
	 */
	private const CHECKOUT_SESSION_METHODS = [
		'gravy' => [ 'cc' ],
	];

	/**
	 * Fields that we don't want to be displayed in the payment method form (e.g. Card form fields).
	 * This list allows us to send a set a fields we care to filter only by country via country_fields.yaml
	 * While allowing for all the other fields to be filtered by payment_methods.yaml overrides as by design
	 * (e.g. showing postal code for US only in the Credit Card form and not in Venmo which doesn't need it)
	 */
	private const FIELDS_OUTSIDE_PAYMENT_METHOD_FORMS = [ 'sms_opt_in', 'phone', 'opt_in', 'employer' ];

	/** @var GatewayAdapter|null The gateway adapter, if a supported gateway was selected. */
	private ?GatewayAdapter $adapter = null;

	/** @var array Routing params derived from the request, computed once in execute(). */
	private array $routingParams = [];

	/** @var string|null The gateway chosen for this request, if any. */
	private ?string $selectedGateway = null;

	/**
	 * @var array[] Payment methods offered on this page, as [ 'method' => string, 'gateway' => string ],
	 *  plus a 'submethod' key for entries of OFFERED_SUBMETHODS
	 */
	private array $supportedPaymentMethods = [];

	/** @var bool True when showing monthly convert for a donation that has already been made */
	private bool $isMonthlyConvertReturn = false;

	public function __construct(
		protected readonly GatewayConfigurationFactory $gatewayConfigurationFactory,
		protected readonly GatewayRouter $gatewayRouter,
		protected readonly LoggerFactory $loggerFactory
	) {
		parent::__construct( 'Donate' );
		$this->dataObject = new DonationDetails();
		$this->logger = $this->loggerFactory->getLogger(
			self::IDENTIFIER,
			new ComboWikiLogPrefixProvider( $this->dataObject )
		);
	}

	/**
	 * @param string|null $subPage
	 *
	 * @return void
	 */
	public function execute( $subPage ): void {
		$request = $this->getRequest();
		if ( $request->getBool( 'monthlyConvert' ) ) {
			$this->executeMonthlyConvert();
			return;
		}

		$wmfConfig = $this->getConfig();
		$dataIntegrator = new DataIntegrator( $request, $this->dataObject, $this->logger );
		$this->dataObject = $dataIntegrator->getDataFromRequestAndSession();
		( new DataNormalizer( $wmfConfig, $this->logger ) )->normalize( $this->dataObject );
		( new ContributionTrackingHelper( $request, $wmfConfig, $this->logger ) )->handleTrackingData( $this->dataObject );
		( new OrderIdHandler( $request, $this->logger ) )->handleOrderId( $this->dataObject );

		$this->logger->debug( 'Data has been processed from request' );
		$country = $this->dataObject->getValue( 'country' );

		// Early guard: Check if the request originates from a forbidden or restricted country
		if ( ForbiddenCountryRegistry::isForbidden( $country ) ) {
			$this->renderForbiddenPage( $country );
			return;
		}

		if ( !$request->getVal( 'gateway' ) ) {
			$this->dataObject->setValue( 'gateway', null );
		}
		// $this->dataObject store more value, here we assigned only the exisiting value in routingParams / config shared with the frontend
		$this->routingParams = [
			'amount' => $this->dataObject->getValue( 'amount', '0' ),
			'country' => $country,
			'currency' => $this->dataObject->getValue( 'currency', 'USD' ),
			'frequency_unit' => $this->dataObject->getValue( 'frequency_unit', '' ),
			'order_id' => $this->dataObject->getValue( 'order_id' ),
			'payment_method' => $this->dataObject->getValue( 'payment_method', 'cc' ),
			'payment_submethod' => $this->dataObject->getValue( 'payment_submethod' ),
			'recurring' => $this->dataObject->getValue( 'recurring' ),
			'variant' => $this->dataObject->getValue( 'variant' ),
			'language' => $this->dataObject->getValue( 'language', $this->getLanguage()->getCode() ),
			'gateway' => $this->dataObject->getValue( 'gateway' ),
			'opt_in' => $this->dataObject->getValue( 'opt_in' ),
			'pay_the_fee' => $this->dataObject->getValue( 'pay_the_fee' ),
		];

		$this->supportedPaymentMethods = $this->gatewayRouter->getSupportedPaymentMethods(
			$wmfConfig->get( 'DonationInterfaceComboWikiGateways' ),
			$this->routingParams,
			self::OFFERED_SUBMETHODS,
			self::ONLY_INCLUDE_RECURRING,
			$this->logger
		);

		$this->selectedGateway = $this->chooseGateway( $this->routingParams );

		if ( $this->routingParams['gateway'] && $this->selectedGateway !== $this->routingParams['gateway'] ) {
			$this->logger->info( 'Selected gateway is ' . $this->selectedGateway . ' but requested ' . $this->routingParams['gateway'] );
		}

		// If we got gateway from the request/session, here we override with the
		// one found with chooseGateway(). Are we ok with that?
		$this->dataObject->setValue( 'gateway', $this->selectedGateway );
		$this->routingParams['gateway'] = $this->selectedGateway;

		// Store copy of the donation details in the session for later access
		$this->storeDonationDetailsInSession();

		if ( $this->selectedGateway ) {
			DonationInterface::setSmashPigProvider( $this->selectedGateway );
			$this->adapter = $this->createAdapterForGateway(
				$this->selectedGateway,
				[ 'variant' => $this->dataObject->getValue( 'variant', '' ) ]
			);
			if ( !$this->adapter ) {
				$this->logger->error( 'Failed to create adapter for gateway: ' . $this->selectedGateway );
			}
			$this->addGatewaySessionId();
		}

		$this->renderPage();
		$this->addVueComponentModulesForVariantsAndPaymentMethods();
	}

	/**
	 * Renders the restricted page view and halts standard payment initialization.
	 *
	 * @param string $countryCode
	 * @return void
	 */
	private function renderForbiddenPage( string $countryCode ): void {
		$viewType = ForbiddenCountryRegistry::getViewType( $countryCode );
		$out = $this->getOutput();

		$this->setHeaders();
		$this->outputHeader();
		$out->setPageTitleMsg( $this->msg( 'combowiki-title' ) );

		// Populate basic routing parameters needed by JS environment
		$this->routingParams = [
			'country' => $countryCode,
			'language' => $this->dataObject->getValue( 'language', 'en' ),
		];

		$out->addJsConfigVars( [
			'wgForbiddenCountry' => $countryCode,
			'wgForbiddenViewType' => $viewType,
		] );

		$this->getHookContainer()->register(
			'MakeGlobalVariablesScript',
			[ $this, 'setClientVariables' ]
		);

		$this->addStylesScriptsAndViewport();
	}

	private function addVueComponentModulesForVariantsAndPaymentMethods(): void {
		$out = $this->getOutput();
		if ( $this->dataObject->getValue( 'variant' ) == 'smsOptin' ) {
			$out->addModules( "ext.donationInterface.combowiki.smsoptin" );
		}
		if ( $this->dataObject->getValue( 'payment_method' ) == 'apple' ) {
			$out->addModules( "ext.donationInterface.applePayHelper" );
		}
	}

	/**
	 * Endowment links send wmf_medium=endowment, which DataIntegrator stores as utm_medium.
	 *
	 * @return bool
	 */
	private function isEndowment(): bool {
		return $this->dataObject->getValue( 'utm_medium' ) === 'endowment';
	}

	/**
	 * @return void
	 */
	public function addStylesScriptsAndViewport(): void {
		$out = $this->getOutput();

		$scriptPath = $this->getConfig()->get( 'ScriptPath' );
		$assetsPath = $scriptPath .
			'/extensions/DonationInterface/modules/ext.donationInterface.comboWiki/assets';

		// Adding styles-only modules this way causes them to arrive ahead of page rendering.
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.comboWikiStyles'
		] );
		if ( $this->isEndowment() ) {
			$out->addModuleStyles( 'ext.donationInterface.comboWikiEndowmentStyles' );
		}

		$out->addModules( [
			'ext.donationInterface.comboWiki'
		] );

		$out->addJsConfigVars( [
			'script_path' => $scriptPath,
			'assets_path' => $assetsPath
		] );

		$out->addHeadItem(
			'viewport',
			Html::element(
				'meta',
				[
					'name' => 'viewport',
					'content' => 'width=device-width, initial-scale=1',
				]
			)
		);

		$out->addLink( [
			'rel' => 'dns-prefetch',
			'href' => 'https://upload.wikimedia.org'
		] );
	}

	/**
	 * Set variables to be read in client-side JS code.
	 *
	 * @param array &$vars
	 *
	 * @return void
	 */
	public function setClientVariables( array &$vars ): void {
		$vars['comboWiki'] = array_merge(
		$this->routingParams,
		[
			'language' => $this->routingParams['language'],
			'gateway' => $this->selectedGateway,
			'paymentMethods' => $this->supportedPaymentMethods,
			'wmfParams' => [
				'utm_medium' => $this->dataObject->getValue( 'utm_medium' ),
			],
			'monthlyConvertReturn' => $this->isMonthlyConvertReturn,
		] );
		$this->addCountriesConfig( $vars );
		$vars['DonationInterfaceNoDecimalCurrencies'] = CurrencyRoundingHelper::$noDecimalCurrencies;

		if ( !$this->adapter ) {
			return;
		}

		// Generate the complete thank-you page URL using adapter state and request data
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );

		$vars['wgDonationInterfaceAmountRules'] = $this->adapter->getDonationRules();

		$vars['DonationInterfaceFormFields'] = $this->getFormFieldsWithOverrides(
			$this->routingParams['country'],
			$this->supportedPaymentMethods
		);
		$vars['DonationInterfaceStateProvinceOptions'] = $this->getStateProvinceOptions(
			$this->routingParams['country']
		);
		if ( $this->adapter->showMonthlyConvert() ) {
			$vars['wgDonationInterfaceMonthlyConvertAmounts'] = $this->adapter->getMonthlyConvertAmounts();
		}

		$configMethod = 'add' . ucfirst( $this->selectedGateway ) . 'ClientConfig';
		if ( method_exists( $this, $configMethod ) ) {
			$this->$configMethod( $vars );
		}

		$otherWaysURL = $this->getConfig()->get( 'DonationInterfaceOtherWaysURL' ) ?? '';
		$language = $this->routingParams['language'];
		$country = $this->routingParams['country'];
		$otherWaysURL = str_replace( '$language', $language, $otherWaysURL );
		$otherWaysURL = str_replace( '$country', $country, $otherWaysURL );
		$vars['DonationInterfaceOtherWaysURL'] = $otherWaysURL;
	}

	/**
	 * Choose the gateway whose client config this page loads.
	 *
	 * The page loads one gateway, so pick it from the payment methods on offer:
	 * the requested gateway if it handles any of them, otherwise the gateway of
	 * the requested payment method, otherwise the gateway for card.
	 *
	 * @param array $params
	 * @return string|null
	 */
	private function chooseGateway( array $params ): ?string {
		$gatewayByMethod = array_column( $this->supportedPaymentMethods, 'gateway', 'method' );

		if ( !$gatewayByMethod ) {
			$this->logger->error( 'No supported payment methods for parameters: ' . print_r( $params, true ) );
			return null;
		}

		if ( $params['gateway'] && in_array( $params['gateway'], $gatewayByMethod, true ) ) {
			return $params['gateway'];
		}

		return $gatewayByMethod[$params['payment_method']]
			?? $gatewayByMethod['cc']
			?? $this->supportedPaymentMethods[0]['gateway'];
	}

	/**
	 * For gateways and payment methods that need one, creates a checkout session and
	 * shares its ID with the frontend as routingParams['gateway_session_id'].
	 * Does the same as the di_checkoutsession_<gateway> API, using the page's adapter
	 * in place of one built from the request params the form would send.
	 *
	 * @return void
	 */
	private function addGatewaySessionId(): void {
		$gateway = $this->selectedGateway;
		$paymentMethod = $this->routingParams['payment_method'];
		if ( !in_array( $paymentMethod, self::CHECKOUT_SESSION_METHODS[$gateway] ?? [], true ) ) {
			return;
		}

		// getCheckoutSession() is specific to GravyAdapter, not GatewayAdapter
		$adapter = $this->adapter;
		if ( !$adapter instanceof GravyAdapter ) {
			$this->logger->error( 'Expected a GravyAdapter to create a checkout session, got ' . get_debug_type( $adapter ) );
			return;
		}

		// The session itself takes no donation details, the adapter only needs the
		// payment method to pick the provider.
		$adapter->addRequestData( [ 'payment_method' => $paymentMethod ] );

		try {
			$session = $adapter->getCheckoutSession();
		} catch ( \Exception $e ) {
			// Leave it to the form to create a session, rather than fail the page
			$this->logger->error( 'Creating checkout session failed: ' . $e->getMessage() );
			return;
		}
		if ( !$session->isSuccessful() ) {
			// The adapter has already logged the raw response
			return;
		}

		$this->routingParams['gateway_session_id'] = $session->getPaymentSession();
	}

	/**
	 * Share the Gravy Payments session ID, along with the gravy config, with the frontend.
	 * Uses the adapter constructed in execute().
	 *
	 * @param array &$vars
	 *
	 * @return void
	 */
	protected function addGravyClientConfig( array &$vars ): void {
		// getGravyConfiguration() is specific to GravyAdapter, not GatewayAdapter,
		// so narrow the type before reaching for it.
		$adapter = $this->adapter;
		if ( !$adapter instanceof GravyAdapter ) {
			$this->logger->error( 'Expected a GravyAdapter for the gravy gateway, got ' . get_debug_type( $adapter ) );
			return;
		}

		$vars['gravyConfiguration'] = $adapter->getGravyConfiguration();
		$vars['wmf_token'] = $adapter->token_getSaltedSessionToken();

		$applePayHref = $this->adapter->getAccountConfig( 'AppleScript' );
		$this->getOutput()->addLink( [
			'rel' => 'dns-prefetch',
			'href' => 'https://' . parse_url( $applePayHref, PHP_URL_HOST )
		] );
	}

	/**
	 * Add Dlocal-specific client configuration.
	 * Called when the selected gateway is 'dlocal'.
	 *
	 * @param array &$vars Client variables to expose
	 * @return void
	 */
	protected function addDlocalClientConfig( array &$vars ): void {
		$vars['wmf_token'] = $this->adapter->token_getSaltedSessionToken();
	}

	/**
	 * Add Adyen-specific client configuration.
	 * Called when the selected gateway is 'adyen'.
	 *
	 * @param array &$vars Client variables to expose
	 * @return void
	 */
	protected function addAdyenClientConfig( array &$vars ): void {
		$adapter = $this->adapter;
		if ( !$adapter instanceof AdyenCheckoutAdapter ) {
			$this->logger->error( 'Expected a AdyenCheckoutAdapter for the adyen gateway, got ' . get_debug_type( $adapter ) );

			return;
		}
		$vars['adyenConfiguration'] = $adapter->getCheckoutConfiguration(
			[
				'country' => $this->routingParams['country'],
				'currency' => $this->routingParams['currency'],
				'amount' => $this->routingParams['amount'],
				'language' => $this->routingParams['language'],
			]
		);
		$vars['wmf_token'] = $this->adapter->token_getSaltedSessionToken();
	}

	/**
	 * Build country list from all supported gateways and add to client config
	 * @param array &$vars
	 * @return void
	 */
	private function addCountriesConfig( array &$vars ): void {
		$countries = [];
		$rawCountries = [];

		$enabledConfigurations = $this->gatewayConfigurationFactory->getAllEnabledConfigurationsForVariant(
			$this->routingParams['variant'] ?? null
		);

		foreach ( $enabledConfigurations  as $gateway => $config ) {
			$rawCountries[] = $config['countries'];
		}

		$rawCountries = array_map(
			static fn ( $countryCode ) => strtoupper( trim( $countryCode ) ),
			array_merge( ...$rawCountries )
		);

		$rawCountries = array_values( array_unique( $rawCountries ) );

		$countryNames = CountryNames::getNames(
			$this->routingParams['language']
		);

		foreach ( $rawCountries as $countryCode ) {
			$currency = NationalCurrencies::getNationalCurrency( $countryCode ) ?: 'USD';

			$countries[$countryCode] = [
				'currency' => $currency,
				'label' => $countryNames[$countryCode] ?? $countryCode,
				'value' => $countryCode,
			];
		}

		$vars['wgDonationInterfaceCountries'] = $countries;
	}

	/**
	 * TODO: once we polish fieldNames in dataObject, we should re-evaluate if we should still
	 *   store all the fields or if we should store in session only a subset of them.
	 *
	 * Store a snapshot of the donation details in the session for later access.
	 *
	 * @return void
	 */
	protected function storeDonationDetailsInSession(): void {
		$session = $this->getRequest()->getSession();
		$session->persist();
		$session->set( DataIntegrator::$DONATION_DETAILS_SESSION_KEY, $this->dataObject->getData() );
		$this->logger->info( 'Data has been stored in session with session ID ' . $session->getId() );
	}

	/**
	 * Create an adapter instance for the given gateway name.
	 * Handles dynamic instantiation of any supported gateway adapter.
	 * TODO: factory class
	 *
	 * @param string $gatewayName Gateway identifier (e.g., 'gravy', 'dlocal', 'adyen')
	 * @param array $options Configuration options for the adapter (e.g., variant)
	 * @return GatewayAdapter|null The instantiated adapter, or null if gateway is not supported
	 */
	protected function createAdapterForGateway(
		string $gatewayName,
		array $options = []
	): ?GatewayAdapter {
		$enabledGateways = $this->gatewayConfigurationFactory->getAllEnabledGateways();
		// Check if gateway is enabled
		if ( !in_array( $gatewayName, $enabledGateways, true ) ) {
			return null;
		}

		$className = DonationInterface::getAdapterClassForGateway( $gatewayName );

		return new $className( $options );
	}

	/**
	 * Sets up the page and the Vue app.
	 */
	private function renderPage(): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->setPageTitleMsg( $this->msg( 'combowiki-title' ) );

		// Expose server-side config to the Vue app.
		$this->getHookContainer()->register(
			'MakeGlobalVariablesScript',
			[
				$this,
				'setClientVariables'
			]
		);

		$this->addStylesScriptsAndViewport();
	}

	/**
	 * Shows the monthly convert modal for a one-time donation that came back
	 * through DonateGatewayResult, e.g. after a 3DS challenge.
	 *
	 * The donation is already complete, so contribution tracking and order ID
	 * setup are skipped. The donation details come from the Donor_BKUP session
	 * backup, which is the same data di_recurring_convert uses.
	 */
	private function executeMonthlyConvert(): void {
		$request = $this->getRequest();
		$orderId = $request->getVal( 'order_id' );
		$backup = $request->getSessionData( GatewayAdapter::DONOR_BKUP );

		if ( !is_array( $backup ) || !$orderId || ( $backup['order_id'] ?? null ) !== $orderId ) {
			// Possibly a page reload, or a stale link. The donation
			// has already gone through, so thank the donor rather than show an empty form.
			$this->logger->info( "No donor backup for order $orderId, redirecting to the thank you page" );
			$this->redirectToThankYouPage( $this->createAdapterForGateway(
				$request->getVal( 'gateway', '' ),
				[ 'external_data' => [
					// Without a contribution_tracking_id, building the adapter takes a new ID
					// and pushes a tracking message. The order ID starts with it.
					'contribution_tracking_id' => strstr( $orderId ?? '', '.', true ) ?: null,
					'order_id' => $orderId,
					'language' => $this->getLanguage()->getCode(),
					'country' => $request->getVal( 'country', '' ),
				] ]
			) );
			return;
		}

		$this->dataObject->setData( $backup );
		$this->selectedGateway = $backup['gateway'] ?? '';
		DonationInterface::setSmashPigProvider( $this->selectedGateway );
		$this->adapter = $this->createAdapterForGateway(
			$this->selectedGateway,
			[ 'external_data' => $backup, 'variant' => $backup['variant'] ?? '' ]
		);
		if ( !$this->adapter || !$this->adapter->showMonthlyConvert() ) {
			$this->redirectToThankYouPage( $this->adapter );
			return;
		}

		$this->isMonthlyConvertReturn = true;
		$this->routingParams = [
			'amount' => $backup['amount'] ?? '0',
			'country' => $backup['country'] ?? '',
			'currency' => $backup['currency'] ?? 'USD',
			'frequency_unit' => $backup['frequency_unit'] ?? '',
			'order_id' => $orderId,
			'payment_method' => $backup['payment_method'] ?? 'cc',
			'payment_submethod' => $backup['payment_submethod'] ?? '',
			'recurring' => $backup['recurring'] ?? '',
			'variant' => $backup['variant'] ?? '',
			'language' => $backup['language'] ?? $this->getLanguage()->getCode(),
			'gateway' => $this->selectedGateway,
		];
		$this->logger->info( 'Showing monthly convert for a completed donation' );
		$this->renderPage();
	}

	private function redirectToThankYouPage( ?GatewayAdapter $adapter ): void {
		if ( $adapter ) {
			$url = ResultPages::getThankYouPage( $adapter );
		} else {
			$url = $this->getPageTitle()->getFullURL( [
				'uselang' => $this->getLanguage()->getCode(),
				'country' => $this->getRequest()->getVal( 'country', '' ),
			] );
		}
		$this->getOutput()->redirect( $url );
	}

	/*
	 * The subdivisions (states, provinces, territories) of the given country, for the state_province
	 * dropdown, the same list the Mustache forms use (see Mustache::setStateOptions).
	 *
	 * @param string $country The country code
	 *
	 * @return array<int, array{value: string, label: string}> The options, localized where possible,
	 *  or an empty list if the country has no subdivision list (state_province is then free text)
	 */
	protected function getStateProvinceOptions( string $country ): array {
		$options = [];
		foreach ( Subdivisions::getByCountry( $country ) ?: [] as $abbr => $name ) {
			$options[] = [ 'value' => (string)$abbr, 'label' => $name ];
		}
		return $options;
	}

	/**
	 * This function gets the form fields for the given country taking into consideration overrides from each
	 * payment method in payment_methods.yaml. The frontend will use this data to populate the form fields in the main
	 * form and for each payment method form as per the given $country rules.
	 *
	 * @param string $country The country code to get the specific form fields for
	 * @param array $paymentMethods The payment methods eligible for this country that might have specific field rules
	 *
	 * @return array{shared: array<string,bool|string>, method: array<string,array<string,bool|string>>} The form fields
	 * for the given country split by 'shared' and 'method' keys, with the 'shared' fields being the same for all
	 * payment methods and the 'method' fields being specific to each payment method.
	 *
	 * Reducted sample output for $country = 'US' and showign only 'cc' as supported payment_method:
	 * [
	 * 		'shared' => [
	 * 			'employer' => 'optional',
	 * ],
	 * 		'method' => [
	 * 			'cc' => [
	 * 				'country' => true,
	 * 				'first_name' => true,
	 * 				'last_name' => true,
	 * 				'email' => true,
	 * 				'street_address' => true,
	 * 				'postal_code' => true,
	 * 			],
	 * 		],
	 * ]
	 */
	protected function getFormFieldsWithOverrides( string $country, array $paymentMethods ): array {
		$fieldsOutsideMethodForms = array_flip( self::FIELDS_OUTSIDE_PAYMENT_METHOD_FORMS );

		// Get all the special fields for the given country and its supported payment methods
		$fieldsForMethods = [];
		foreach ( $paymentMethods as $paymentMethod ) {
			// Only the selected gateway's adapter is loaded, and it throws for methods it does not handle
			if ( $paymentMethod['gateway'] !== $this->selectedGateway ) {
				continue;
			}
			// Note: currently we do not set any field rule in payment_submethod.yaml so it can technically be ignored
			$fieldsForThisMethod = $this->adapter->getFormFields(
				[ 'country' => $country, 'payment_method' => $paymentMethod['method'] ]
			);
			$fieldsForThisMethod = array_diff_key( $fieldsForThisMethod, $fieldsOutsideMethodForms );
			// The frontend needs this information, so we should expose the submethod if it exists
			// since it is added only if in self::OFFERED_SUBMETHODS
			$paymentMethodKey = $paymentMethod['submethod'] ?? $paymentMethod['method'];
			$fieldsForMethods[ $paymentMethodKey ] = $fieldsForThisMethod;
		}

		// Get the fields ComboWiki treats as country specific and does not include in a payment method specific form
		$sharedFieldsFull = $this->adapter->getFormFields( [ 'country' => $country ] );
		$sharedFields = array_intersect_key( $sharedFieldsFull, $fieldsOutsideMethodForms );

		return [
			'shared' => $sharedFields,
			'method' => $fieldsForMethods,
		];
	}
}
