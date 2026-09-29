<?php /** @noinspection ALL */

namespace MediaWiki\Extension\DonationInterface\Special;

use AdyenCheckoutAdapter;
use DonationInterface;
use GatewayAdapter;
use GravyAdapter;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
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
use MediaWiki\Extension\DonationInterface\Configuration\LoggerFactory;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use ResultPages;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;

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

	/** @var GatewayAdapter|null The gateway adapter, if a supported gateway was selected. */
	private ?GatewayAdapter $adapter = null;

	/** @var array Routing params derived from the request, computed once in execute(). */
	private array $routingParams = [];

	/** @var string|null The gateway chosen for this request, if any. */
	private ?string $selectedGateway = null;

	/** @var array[] Payment methods offered on this page, as [ 'method' => string, 'gateway' => string ] */
	private array $supportedPaymentMethods = [];

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
		$wmfConfig = $this->getConfig();
		$dataIntegrator = new DataIntegrator( $request, $this->dataObject, $this->logger );
		$this->dataObject = $dataIntegrator->getDataFromRequestAndSession();
		( new DataNormalizer( $wmfConfig, $this->logger ) )->normalize( $this->dataObject );
		( new ContributionTrackingHelper( $request, $wmfConfig, $this->logger ) )->handleTrackingData( $this->dataObject );
		( new OrderIdHandler( $request, $this->logger ) )->handleOrderId( $this->dataObject );

		$this->logger->info( __FUNCTION__ . ': Data has been processed from request' );
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
		];

		$this->supportedPaymentMethods = $this->gatewayRouter->getSupportedPaymentMethods(
			$wmfConfig->get( 'DonationInterfaceComboWikiGateways' ),
			$this->routingParams,
			$this->logger
		);

		$this->selectedGateway = $this->chooseGateway( $this->routingParams );

		if ( $this->selectedGateway !== $this->routingParams['gateway'] ) {
			$this->logger->info( __FUNCTION__ .
				': Selected gateway is ' . $this->selectedGateway . ' but requested ' . $this->routingParams['gateway']
			);
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
				$this->logger->error( __FUNCTION__ .
					": Failed to create adapter for gateway: {$this->selectedGateway}"
				);
			}
		}

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
		$this->addVueComponentModulesForVariants();
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

	private function addVueComponentModulesForVariants(): void {
		$out = $this->getOutput();
		if ( $this->dataObject->getValue( 'variant' ) == 'smsOptin' ) {
			$out->addModules( "ext.donationInterface.combowiki.smsoptin" );
		}
	}

	/**
	 * @return void
	 */
	public function addStylesScriptsAndViewport(): void {
		$out = $this->getOutput();

		$context = RequestContext::getMain();
		$scriptPath = $context->getConfig()->get( 'ScriptPath' );
		$assetsPath = $scriptPath .
			'/extensions/DonationInterface/modules/ext.donationInterface.comboWiki/assets';

		// Adding styles-only modules this way causes them to arrive ahead of page rendering.
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.comboWikiStyles'
		] );

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
		$vars['comboWiki'] = [
			'language' => $this->routingParams['language'],
			'params' => $this->routingParams,
			'gateway' => $this->selectedGateway,
			'paymentMethods' => $this->supportedPaymentMethods,
		];
		$this->addCountriesConfig( $vars );
		$vars['DonationInterfaceNoDecimalCurrencies'] = CurrencyRoundingHelper::$noDecimalCurrencies;

		if ( !$this->adapter ) {
			return;
		}

		// Generate the complete thank-you page URL using adapter state and request data
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );

		$vars['wgDonationInterfaceAmountRules'] = $this->adapter->getDonationRules();

		// Donor fields from the country_fields config, each true (required) or 'optional'.
		// Fields left out are not shown. Only the country is passed, since the donor
		// picks the payment method on the page.
		$vars['DonationInterfaceFormFields'] = $this->adapter->getFormFields(
			[ 'country' => $this->routingParams['country'] ]
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
			$this->logger->error( __FUNCTION__ .
				': Expected a GravyAdapter for the gravy gateway, got ' . get_debug_type( $adapter )
			);

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
			$this->logger->error( __FUNCTION__ .
				': Expected a AdyenCheckoutAdapter for the adyen gateway, got ' . get_debug_type( $adapter )
			);

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
		$this->logger->info( __FUNCTION__ . ': Data has been stored in session.' );
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
}
