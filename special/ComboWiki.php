<?php /** @noinspection ALL */

namespace MediaWiki\Extension\DonationInterface\Special;

use AdyenCheckoutAdapter;
use DonationLoggerFactory;
use GatewayAdapter;
use GravyAdapter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CLDR\CountryNames;
use MediaWiki\Extension\DonationInterface\ComboWiki\ContributionTrackingHelper;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataIntegrator;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataNormalizer;
use MediaWiki\Extension\DonationInterface\ComboWiki\OrderIdHandler;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use ResultPages;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;
use Symfony\Component\Yaml\Yaml;

/**
 * ComboWiki: the single-page VueJS donation flow.
 *
 * Special page that sets up the ComboWiki Vue application.
 * It loads the Vue + styles ResourceLoader modules, sets up the
 * viewport, and exposes server-side configuration to the client through the
 * MakeGlobalVariablesScript hook using setClientVariables().
 */
class ComboWiki extends UnlistedSpecialPage {

	/**
	 * Identifies the ComboWiki donation flow.
	 */
	public const IDENTIFIER = 'combowiki';

	private LoggerInterface $logger;

	/** @var GatewayAdapter|null The gateway adapter, if a supported gateway was selected. */
	private ?GatewayAdapter $adapter = null;

	/** @var array Routing params derived from the request, computed once in execute(). */
	private array $routingParams = [];

	/** @var string|null The gateway chosen for this request, if any. */
	private ?string $selectedGateway = null;
	private DonationDetails $dataObject;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter', 'ComboWiki' );
		parent::__construct( 'ComboWiki' );
	}

	/**
	 * @param string|null $subPage
	 *
	 * @return void
	 */
	public function execute( $subPage ): void {
		$request = $this->getRequest();
		$wmfConfig = MediaWikiServices::getInstance()->getMainConfig();
		$dataIntegrator = new DataIntegrator( $request, new DonationDetails() );
		$this->dataObject = $dataIntegrator->getDataFromRequestAndSession();
		( new DataNormalizer( $wmfConfig ) )->normalize( $this->dataObject );
		( new ContributionTrackingHelper( $request, $wmfConfig ) )->handleTrackingData( $this->dataObject );
		( new OrderIdHandler( $request ) )->handleOrderId( $this->dataObject );
		if ( !$request->getVal( 'gateway' ) ) {
			$this->dataObject->setValue( 'gateway', null );
		}
		// $this->dataObject store more value, here we assigned only the exisiting value in routingParams / config shared with the frontend
		$this->routingParams = [
			'amount' => $this->dataObject->getValue( 'amount', '0' ),
			'country' => $this->dataObject->getValue( 'country', 'US' ),
			'currency' => $this->dataObject->getValue( 'currency', 'USD' ),
			'frequency_unit' => $this->dataObject->getValue( 'frequency_unit', '' ),
			'payment_method' => $this->dataObject->getValue( 'payment_method', 'cc' ),
			'payment_submethod' => $this->dataObject->getValue( 'payment_submethod' ),
			'recurring' => $this->dataObject->getValue( 'recurring' ),
			'variant' => $this->dataObject->getValue( 'variant' ),
			'language' => $this->dataObject->getValue( 'language', $this->getLanguage()->getCode() ),
			'gateway' => $this->dataObject->getValue( 'gateway' ),
		];

		$this->selectedGateway = $this->chooseGateway( $this->routingParams );

		// If we got gateway from the request/session, here we override with the
		// one found with chooseGateway(). Are we ok with that?
		$this->dataObject->setValue( 'gateway', $this->selectedGateway );
		$this->routingParams['gateway'] = $this->selectedGateway;

		// Store copy of the donation details in the session for later access
		$this->storeDonationDetailsInSession();

		if ( $this->selectedGateway ) {
			GatewayRouter::setSmashPigProviderForGateway( $this->selectedGateway );
			$this->adapter = GatewayRouter::createAdapterForGateway(
				$this->selectedGateway,
				[ 'variant' => $this->dataObject->getValue( 'variant', '' ) ]
			);
			if ( !$this->adapter ) {
				$this->logger->error(
					"Failed to create adapter for gateway: {$this->selectedGateway}"
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
		$this->addVueComponentModulesForVarients();
	}

	private function addVueComponentModulesForVarients(): void {
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
		// TODO: update since 'language' and 'gateway' are exposed in $this->routingParams now
		$vars['comboWiki'] = [
			'language' => $this->routingParams['language'],
			'params' => $this->routingParams,
			'gateway' => $this->selectedGateway,
		];
		$this->addCountriesConfig( $vars );

		// No gateway was selected, or its adapter could not be built. The Vue app
		// still gets the params above so it can show an error, but everything below
		// needs a live adapter. TODO: maybe set a fallback as gravy?
		if ( !$this->adapter ) {
			return;
		}

		$vars['wgDonationInterfaceAmountRules'] = $this->adapter->getDonationRules();
		if ( $this->adapter->showMonthlyConvert() ) {
			$vars['wgDonationInterfaceMonthlyConvertAmounts'] = $this->adapter->getMonthlyConvertAmounts();
		}

		$configMethod = 'add' . ucfirst( $this->selectedGateway ) . 'ClientConfig';
		if ( method_exists( $this, $configMethod ) ) {
			$this->$configMethod( $vars );
		}
	}

	private function chooseGateway( array $params ): ?string {
		$supportedGateways = GatewayRouter::getSupportedGateways(
			$params['country'],
			$params['currency'],
			$params['payment_method'],
			$params['payment_submethod'],
			(bool)$params['recurring'],
			$params['variant'],
			$this->getConfig()
		);

		if ( count( $supportedGateways ) === 0 ) {
			$this->logger->error( 'No supported gateway for parameters: ' . print_r( $params, true ) );

			return null;
		}

		if ( $params['gateway'] && in_array( $params['gateway'], $supportedGateways, true ) ) {
			return $params['gateway'];
		}

		if ( count( $supportedGateways ) === 1 ) {
			return $supportedGateways[0];
		}

		return GatewayRouter::chooseGatewayByPriority(
			$supportedGateways,
			$params,
			$this->getConfig(),
			$this->logger
		);
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
			$this->logger->error(
				'Expected a GravyAdapter for the gravy gateway, got ' . get_debug_type( $adapter )
			);

			return;
		}

		$vars['gravyConfiguration'] = $adapter->getGravyConfiguration();
		$vars['wmf_token'] = $adapter->token_getSaltedSessionToken();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $adapter );
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
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
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
			$this->logger->error(
				'Expected a AdyenCheckoutAdapter for the adyen gateway, got ' . get_debug_type( $adapter )
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
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $this->adapter );
	}

	/**
	 * @param array &$vars
	 * @return void
	 */
	private function addCountriesConfig( array &$vars ): void {
		$filePath = __DIR__ . "/../" . $this->selectedGateway . "_gateway/config/countries.yaml";
		$rawCountries = file_exists( $filePath ) ? Yaml::parseFile( $filePath ) : [];

		// Fetch the localised country-name map once, rather than per iteration.
		$countryNames = CountryNames::getNames( $this->routingParams['language'] );

		$countries = [];
		foreach ( $rawCountries as $key => $countryCode ) {
			// Look up the official national currency code using SmashPig
			$currency = NationalCurrencies::getNationalCurrency( $countryCode ) ?: 'USD';
			$countries[ $countryCode ] = [
				'currency' => $currency,
				'label' => $countryNames[$countryCode] ?? $countryCode,
				'value' => $countryCode
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
	}
}
