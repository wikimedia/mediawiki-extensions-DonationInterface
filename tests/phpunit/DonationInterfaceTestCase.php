<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */
use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingGlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;
use Wikimedia\TestingAccessWrapper;

/**
 * @group		Fundraising
 * @group		QueueHandling
 * @group		ClassMethod
 * @group		ListenerAdapter
 *
 * @category	UnitTesting
 * @package		Fundraising_QueueHandling
 */
abstract class DonationInterfaceTestCase extends MediaWikiTestCase {

	/**
	 * An array of the vars we expect to be set before people hit payments.
	 * @var array
	 */
	public static $initial_vars = array (
		'ffname' => 'testytest',
		'referrer' => 'www.yourmom.com', //please don't go there.
		'currency' => 'USD',
	);

	/**
	 * This will be set by a test method with the adapter object.
	 *
	 * @var GatewayAdapter	$gatewayAdapter
	 */
	protected $gatewayAdapter;

	protected $testAdapterClass = TESTS_ADAPTER_DEFAULT;
	protected $smashPigGlobalConfig;

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {

		//Just in case you got here without running the configuration...
		global $wgDonationInterfaceTest;
		$wgDonationInterfaceTest = true;

		parent::__construct( $name, $data, $dataName );
	}

	protected function setUp() {
		// TODO: Use SmashPig dependency injection instead.  Also override
		// SmashPig core logger.
		DonationLoggerFactory::$overrideLogger = new TestingDonationLogger();
		// Replace real SmashPig context with test version that lets us
		// override provider configurations that may be set in code
		$this->smashPigGlobalConfig = TestingGlobalConfiguration::create();
		TestingContext::init( $this->smashPigGlobalConfig );
		Context::get()->setSourceType( 'payments' );
		Context::get()->setSourceName( 'DonationInterface' );
		parent::setUp();
	}

	protected function tearDown() {
		$this->resetAllEnv();
		DonationLoggerFactory::$overrideLogger = null;
		parent::tearDown();
	}

	/**
	 * Set up a fake request with the given data. Returns the fake request.
	 * @param array $data
	 * @param array $session
	 * @return FauxRequest
	 */
	protected function setUpRequest( $data, $session = null ) {
		RequestContext::resetMain();
		$request = new FauxRequest( $data, false, $session );
		RequestContext::getMain()->setRequest( $request );
		return $request;
	}

	/**
	 * Set global language for the duration of the test
	 *
	 * @param string $language language code to force
	 */
	protected function setLanguage( $language ) {
		RequestContext::getMain()->setLanguage( $language );
		// BackCompat
		$this->setMwGlobals( 'wgLang', RequestContext::getMain()->getLanguage() );
	}

	/**
	 * @param string $provider
	 * @return TestingProviderConfiguration
	 */
	protected function setSmashPigProvider( $provider ) {
		$providerConfig = TestingProviderConfiguration::createForProvider(
			$provider, $this->smashPigGlobalConfig
		);
		TestingContext::get()->providerConfigurationOverride = $providerConfig;
		return $providerConfig;
	}

	/**
	 * buildRequestXmlForGlobalCollect
	 *
	 * @todo
	 * - there are many cases to this that need to be developed.
	 * - Do not consider this a complete test!
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 */
	public function buildRequestXmlForGlobalCollect( $optionsForTestData, $options ) {

		global $wgDonationInterfaceTest;

		$wgDonationInterfaceTest = true;

		$this->setUpRequest( $options );
		$this->gatewayAdapter = new TestingGlobalCollectAdapter();

		$this->gatewayAdapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');

		$exposed = TestingAccessWrapper::newFromObject( $this->gatewayAdapter );
		$request = trim( $exposed->buildRequestXML() );

		$this->setUpRequest( $options );
		$expected = $this->getExpectedXmlRequestForGlobalCollect( $optionsForTestData, $options );

		$this->assertEquals( $expected, $request, 'The constructed XML for payment_method [' . $optionsForTestData['payment_method'] . '] and payment_submethod [' . $optionsForTestData['payment_submethod'] . '] does not match our expected request.' );
	}

	/**
	 *
	 * @param string $country The country we want the test user to be from.
	 * @return array Donor data to use
	 * @throws OutOfBoundsException when there is no data available for the requested country
	 */
	public static function getDonorTestData( $country = '' ) {
		$donortestdata = array (
			'US' => array ( //default
				'city' => 'San Francisco',
				'state_province' => 'CA',
				'postal_code' => '94105',
				'currency' => 'USD',
				'street_address' => '123 Fake Street',
				'first_name' => 'Firstname',
				'last_name' => 'Surname',
				'amount' => '1.55',
				'language' => 'en',
				'email' => 'nobody@wikimedia.org',
			),
			'ES' => array (
				'city' => 'Barcelona',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 Calle Fake',
				'first_name' => 'Nombre',
				'last_name' => 'Apellido',
				'amount' => '1.55',
				'language' => 'es',
			),
			'Catalonia' => array (
				'city' => 'Barcelona',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 Calle Fake',
				'first_name' => 'Nombre',
				'last_name' => 'Apellido',
				'amount' => '1.55',
				'language' => 'ca',
			),
			'NO' => array (
				'city' => 'Oslo',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 Fake Gate',
				'first_name' => 'Fornavn',
				'last_name' => 'Etternavn',
				'amount' => '1.55',
				'language' => 'no',
			),
			'FR' => array (
				'city' => 'Versailles',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 Rue Faux',
				'first_name' => 'Prénom',
				'last_name' => 'Nom',
				'amount' => '1.55',
				'language' => 'fr',
			),
			// Fiji is configured as a snowflake to test special treatment for certain store IDs
			'FJ' => array (
				'city' => 'Suva',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 Fake Street',
				'first_name' => 'FirstName',
				'last_name' => 'LastName',
				'amount' => '1.55',
				'language' => 'en',
			),
			'NL' => array (
				'city' => 'Amsterdam',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 nep straat',
				'first_name' => 'Voornaam',
				'last_name' => 'Achternaam',
				'amount' => '1.55',
				'language' => 'nl',
			),
			'BE' => array (
				'city' => 'Antwerp',
				'state_province' => 'XX',
				'postal_code' => '0',
				'currency' => 'EUR',
				'street_address' => '123 nep straat',
				'first_name' => 'Voornaam',
				'last_name' => 'Achternaam',
				'amount' => '1.55',
				'language' => 'nl',
			),
			'IT' => array (
				'city' => 'Torino',
				'state_province' => 'TO',
				'postal_code' => '10123',
				'currency' => 'EUR',
				'street_address' => 'Via Falso 123',
				'first_name' => 'Nome',
				'last_name' => 'Cognome',
				'amount' => '1.55',
				'language' => 'it',
			),
			'CA' => array (
				'city' => 'Saskatoon',
				'state_province' => 'SK',
				'postal_code' => 'S7K 0J5',
				'currency' => 'CAD',
				'street_address' => '123 Fake Street',
				'first_name' => 'Firstname',
				'last_name' => 'Surname',
				'amount' => '1.55',
				'language' => 'en',
			),
			'BR' => array (
				'currency' => 'BRL',
				'fiscal_number' => '00003456789',
				'payment_submethod' => 'test_bank',
				'first_name' => 'Nome',
				'last_name' => 'Apelido',
				'amount' => '100',
				'language' => 'pt',
				'email' => 'nobody@example.org'
			),
			'MX' => array (
				'city' => 'Tuxtla Gutiérrez',
				'state_province' => 'CHP',
				'currency' => 'MXN',
				'street_address' => 'Calle Falso 123',
				'first_name' => 'Nombre',
				'last_name' => 'Apellido',
				'email' => 'pueblo@unido.coop',
				'amount' => '155',
				'language' => 'es',
			),
		);
		//default to US
		if ( $country === '' ) {
			$country = 'US';
		}

		if ( array_key_exists( $country, $donortestdata ) ) {
			$donortestdata = array_merge( self::$initial_vars, $donortestdata[$country] );
			$donortestdata['country'] = $country;
			return $donortestdata;
		}
		throw new OutOfBoundsException( __FUNCTION__ . ": No donor data for country '$country'" );
	}

	/**
	 * Supported languages for Belgium
	 */
	public function belgiumLanguageProvider() {
		return array(
			array( 'nl' ),
			array( 'de' ),
			array( 'fr' ),
		);
	}

	/**
	 * Supported languages for Canada
	 */
	public function canadaLanguageProvider() {
		return array(
			array( 'en' ),
			array( 'fr' ),
		);
	}

	/**
	 * Transaction codes for GC and GC orphan adapters not to be retried
	 * on pain of $1000+ fines by Mastercard
	 */
	public function mcNoRetryCodeProvider() {
		return array(
			array( '430260' ),
			array( '430306' ),
			array( '430330' ),
			array( '430354' ),
			array( '430357' ),
		);
	}
	
	public function benignNoRetryCodeProvider() {
		return array(
			array( '430285' ),
		);
	}

	/**
	 * Get the expected XML request from GlobalCollect
	 *
	 * @param $optionsForTestData
	 * @param array $options
	 * @return string    The expected XML request
	 */
	public function getExpectedXmlRequestForGlobalCollect( $optionsForTestData, $options = array() ) {
		global $wgDonationInterfaceThankYouPage;
		$request = RequestContext::getMain()->getRequest();

		$orderId = $this->gatewayAdapter->getData_Unstaged_Escaped( 'order_id' );
		$exposed = TestingAccessWrapper::newFromObject( $this->gatewayAdapter );
		$merchantref = $exposed->getData_Staged( 'contribution_tracking_id' );
		//@TODO: WHY IN THE NAME OF ZARQUON are we building XML in a STRING format here?!?!?!!!1one1!?. Great galloping galumphing giraffes.
		$expected  = '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
		$expected .= '<XML>';
		$expected .= 	'<REQUEST>';
		$expected .= 		'<ACTION>INSERT_ORDERWITHPAYMENT</ACTION>';
		$expected .= 		'<META><MERCHANTID>' . $exposed->account_config[ 'MerchantID' ] . '</MERCHANTID>';

		if ( isset( $request ) ) {
			$expected .=		'<IPADDRESS>' . $request->getIP() . '</IPADDRESS>';
		}
		
		$expected .=			'<VERSION>1.0</VERSION>';
		$expected .=		'</META>';
		$expected .= 		'<PARAMS>';
		$expected .= 			'<ORDER>';
		$expected .= 				'<ORDERID>' . $orderId . '</ORDERID>';
		$expected .= 				'<AMOUNT>' . $options['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['currency'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['country'] . '</COUNTRYCODE>';
		$expected .= '<MERCHANTREFERENCE>' . $merchantref . '</MERCHANTREFERENCE>';

		if ( isset( $request ) ) {
			$expected .=			'<IPADDRESSCUSTOMER>' . $request->getIP() . '</IPADDRESSCUSTOMER>';
		}

		$expected .=				'<EMAIL>' . TESTS_EMAIL . '</EMAIL>';
		$expected .= 			'</ORDER>';
		$expected .= 			'<PAYMENT>';
		$expected .= 				'<PAYMENTPRODUCTID>' . $optionsForTestData['payment_product_id'] . '</PAYMENTPRODUCTID>';
		$expected .= 				'<AMOUNT>' . $options['amount'] * 100 . '</AMOUNT>';
		$expected .= 				'<CURRENCYCODE>' . $options['currency'] . '</CURRENCYCODE>';
		$expected .= 				'<LANGUAGECODE>' . $options['language'] . '</LANGUAGECODE>';
		$expected .= 				'<COUNTRYCODE>' . $options['country'] . '</COUNTRYCODE>';
		$expected .= 				'<HOSTEDINDICATOR>1</HOSTEDINDICATOR>';
		$expected .= 				"<RETURNURL>{$wgDonationInterfaceThankYouPage}/{$options['language']}?country={$options['country']}</RETURNURL>";
		$expected .=				'<AUTHENTICATIONINDICATOR>0</AUTHENTICATIONINDICATOR>';
		$expected .= 				'<FIRSTNAME>' . $options['first_name'] . '</FIRSTNAME>';
		$expected .= 				'<SURNAME>' . $options['last_name'] . '</SURNAME>';
		$expected .= 				'<STREET>' . $options['street_address'] . '</STREET>';
		$expected .= 				'<CITY>' . $options['city'] . '</CITY>';
		$expected .= 				'<STATE>' . $options['state_province'] . '</STATE>';
		$expected .= 				'<ZIP>' . $options['postal_code'] . '</ZIP>';
		$expected .= '<EMAIL>' . TESTS_EMAIL . '</EMAIL>';

		// Set the issuer id if it is passed.
		if ( isset( $optionsForTestData['descriptor'] ) ) {
			$expected .= '<DESCRIPTOR>' . $optionsForTestData['descriptor'] . '</DESCRIPTOR>';
		}

		// Set the issuer id if it is passed.
		if ( isset( $optionsForTestData['issuer_id'] ) ) {
			$expected .= 				'<ISSUERID>' . $optionsForTestData['issuer_id'] . '</ISSUERID>';
		}


		// If we're doing Direct Debit...
		//@TODO: go ahead and split this out into a "Get the direct debit I_OWP XML block function" the second this gets even slightly annoying.
		if ( $optionsForTestData['payment_method'] === 'dd' ) {
			$expected .= '<ACCOUNTNAME>' . $optionsForTestData['account_name'] . '</ACCOUNTNAME>';
			$expected .= '<ACCOUNTNUMBER>' . $optionsForTestData['account_number'] . '</ACCOUNTNUMBER>';
			$expected .= '<BANKCODE>' . $optionsForTestData['bank_code'] . '</BANKCODE>';
			$expected .= '<BRANCHCODE>' . $optionsForTestData['branch_code'] . '</BRANCHCODE>';
			$expected .= '<BANKCHECKDIGIT>' . $optionsForTestData['bank_check_digit'] . '</BANKCHECKDIGIT>';
			$expected .= '<DATECOLLECT>' . gmdate( 'Ymd' ) . '</DATECOLLECT>'; //is this cheating? Probably.
			$expected .= '<DIRECTDEBITTEXT>' . $optionsForTestData['direct_debit_text'] . '</DIRECTDEBITTEXT>';
		}

		$expected .= 			'</PAYMENT>';
		$expected .= 		'</PARAMS>';
		$expected .= 	'</REQUEST>';
		$expected .= '</XML>';
		
		return $expected;
		
	}

	/**
	 * Get a fresh gateway object of the type specified in the variable
	 * $this->testAdapterClass.
	 * @param array $external_data If you want to shoehorn in some external
	 * data, do that here.
	 * @param array $setup_hacks An array of things that override stuff in
	 * the constructor of the gateway object that I can't get to without
	 * refactoring the whole thing. @TODO: Refactor the gateway adapter
	 * constructor.
	 * @return GatewayAdapter The new relevant gateway adapter object.
	 */
	function getFreshGatewayObject( $external_data = null, $setup_hacks = array() ) {
		$data = null;
		if ( !is_null( $external_data ) ) {
			$data = array (
				'external_data' => $external_data,
			);
		}

		if ( $setup_hacks ) {
			if ( !is_null( $data ) ) {
				$data = array_merge( $data, $setup_hacks );
			} else {
				$data = $setup_hacks;
			}
		}

		$class = $this->testAdapterClass;
		$gateway = new $class( $data );

		$classReflection = new ReflectionClass( $gateway );

		// FIXME: Find a more elegant way to hackity hacken hack.
		// We want to override any define- functions with hacky values.
		foreach ( $setup_hacks as $field => $value ) {
			if ( property_exists( $class, $field ) ) {
				$propertyReflection = $classReflection->getProperty( $field );
				$propertyReflection->setAccessible( true );
				$propertyReflection->setValue( $gateway, $value );
			}
		}

		return $gateway;
	}

	function resetAllEnv() {
		// TODO: reset PDO/sqlite queues
		$_SESSION = array ( );
		$_GET = array ( );
		$_POST = array ( );

		$_SERVER = array ( );
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = TESTS_HOSTNAME;
		$_SERVER['SERVER_NAME'] = TESTS_HOSTNAME;
		$_SERVER['SCRIPT_NAME'] = __FILE__;

		RequestContext::resetMain();

		// Wipe out the $instance of these classes to make sure they're
		// re-created with fresh gateway instances for the next test
		$singleton_classes = array(
			'Gateway_Extras_ConversionLog',
			'Gateway_Extras_CustomFilters',
			'Gateway_Extras_CustomFilters_Functions',
			'Gateway_Extras_CustomFilters_IP_Velocity',
			'Gateway_Extras_CustomFilters_MinFraud',
			'Gateway_Extras_CustomFilters_Referrer',
			'Gateway_Extras_CustomFilters_Source',
			'Gateway_Extras_SessionVelocityFilter',
		);
		foreach( $singleton_classes as $singleton_class ) {
			$unwrapped = TestingAccessWrapper::newFromClass( $singleton_class );
			$unwrapped->instance = null;
		}
		// Reset SmashPig context
		Context::set( null );
		// Clear out our HashBagOStuff, used for testing
		wfGetMainCache()->clear();
	}

	/**
	 * Instantiates the $special_page_class with supplied $initial_vars,
	 * yoinks the html output from the output buffer, loads that into a
	 * DomDocument and performs asserts on the results per the checks
	 * supplied in $perform_these_checks.
	 * Optional: Asserts that the gateway has logged nothing at ERROR level.
	 *
	 * @param string $special_page_class A testing descendant of GatewayPage
	 * @param array $initial_vars Array that will be loaded straight into a
	 * test version of the http request.
	 * @param array $perform_these_checks Array of checks to perform in the
	 * following format:
	 * $perform_these_checks[$element_id][$check_to_perform][$expected_result]
	 * So far, $check_to_perform can be either 'nodename' or 'innerhtml'
	 * @param boolean $fail_on_log_errors When true, this will fail the
	 * current test if there are entries in the gateway's error log.
	 * @param array $session pre-existing session data.
	 */
	function verifyFormOutput( $special_page_class, $initial_vars, $perform_these_checks, $fail_on_log_errors = false, $session = null ) {
		// Nasty hack to clear output from any previous tests.
		$mainContext = RequestContext::getMain();
		$newOutput = new OutputPage( $mainContext );
		$newRequest = new TestingRequest( $initial_vars, false, $session );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$mainContext->setRequest( $newRequest );
		$mainContext->setOutput( $newOutput );
		$mainContext->setTitle( $newTitle );

		$globals = array (
			'wgTitle' => $newTitle,
			'wgOut' => $newOutput,
		);

		$this->setMwGlobals( $globals );

		$this->setLanguage( $initial_vars['language'] );

		ob_start();
		$formpage = new $special_page_class();
		$formpage->execute( NULL );
		$formpage->getOutput()->output();
		$form_html = ob_get_contents();
		ob_end_clean();

		// In the event that something goes crazy, uncomment the next line for much easier local debugging
		// file_put_contents( '/tmp/xmlout.txt', $form_html );

		if ( $fail_on_log_errors ) {
			$this->verifyNoLogErrors();
		}

		$dom_thingy = new DomDocument();
		//// DEBUGGING, foo
		// if (property_exists($this, 'FOO')) {
		// 	error_log(var_export($formpage->getRequest()->response()->getheader('Location'), true));
		// 	error_log(var_export($form_html, true));
		// }

		if ( $form_html ) {
			// p.s. i'm SERIOUS about the character encoding.
			$dom_thingy->loadHTML( '<?xml encoding="UTF-8">' . $form_html );
			$dom_thingy->encoding = 'UTF-8';
		}

		foreach ( $perform_these_checks as $id => $checks ) {
			if ( $id == 'headers' ) {
				foreach ( $checks as $name => $expected ) {
					$actual = $formpage->getRequest()->response()->getheader( $name );
					$this->performCheck( $actual, $expected, "header '$name'");
					break;
				}
				continue;
			}
			unset( $perform_these_checks['headers'] );

			$input_node = $dom_thingy->getElementById( $id );
			$this->assertNotNull( $input_node, "Couldn't find the '$id' element in html. Log entries: \n" .
				print_r( DonationLoggerFactory::$overrideLogger->messages, true ) . "\n\nHTML:\n$form_html" );
			foreach ( $checks as $name => $expected ) {
				switch ( $name ) {
					case 'nodename':
						$this->performCheck( $input_node->nodeName, $expected, "name of node with id '$id'");
						break;
					case 'innerhtml':
						$actual_html = self::getInnerHTML( $input_node );
						// Strip comments
						$actual_html = preg_replace( '/<!--[^>]*-->/', '', $actual_html );
						$this->performCheck( $actual_html, $expected, "innerHTML of node '$id'");
						break;
					case 'innerhtmlmatches':
						$this->assertEquals( 1, preg_match( $expected, self::getInnerHTML( $input_node ) ), "Value of the node with id '$id' does not match pattern '$expected'. It has value " . self::getInnerHTML( $input_node ) );
						break;
					case 'value':
						$this->performCheck( $input_node->getAttribute('value'), $expected, "value of node with id '$id'");
						break;
					case 'selected':
						$selected = null;
						if ( $input_node->nodeName === 'select' ) {
							$options = $input_node->getElementsByTagName( 'option' );
							foreach ( $options as $option ) {
								if ( $option->hasAttribute( 'selected' ) ) {
									$selected = $option->getAttribute( 'value' );
									break;
								}
							}
							$this->performCheck( $selected, $expected, "selected option value of node with id '$id'");
						} else {
							$this->fail( "Attempted to test for selected value on non-select node, id '$id'" );
						}
						break;
				}
			}
		}

		// Are there untranslated boogers?
		if ( preg_match_all( '/&lt;[^<]+(&gt;|>)/', $form_html, $matches ) ) {
			$this->fail( 'Untranslated messages present: ' . implode( ', ', $matches[0] ) );
		}
	}

	/**
	 * Performs some sort of assertion on a value.
	 *
	 * @param string $value the value to test
	 * @param string|callable $check
	 *  if $check is callable, it is called with argument $value
	 *  otherwise, $value is asserted to be equal to $check
	 * @param string $label identifies the value in assertion failures
	 * @return void
	 */
	function performCheck( $value, $check, $label = 'Tested value' ) {
		if ( is_callable( $check ) ) {
			$check( $value );
			return;
		}
		$this->assertEquals( $check, $value, "Expected $label to be $check, found $value instead.");
	}
	/**
	 * Asserts that there are no log entries of LOG_ERR or worse.
	 */
	function verifyNoLogErrors( ) {
		$log = DonationLoggerFactory::$overrideLogger->messages;

		$this->assertTrue( is_array( $log ), "Missing the test log" );

		//for our purposes, an "error" is LOG_ERR or less.
		$checklogs = array (
			LogLevel::ERROR => "Oops: We've got LOG_ERRors.",
			LogLevel::CRITICAL => "Critical errors!",
			LogLevel::ALERT => "Log Alerts!",
			LogLevel::EMERGENCY => "Logs says the servers are actually on fire.",
		);

		$message = false;
		foreach ( $checklogs as $level => $levelmessage ) {
			if ( array_key_exists( $level, $log ) ) {
				$message = $levelmessage . ' ' . print_r( $log[$level], true ) . "\n";
			}
		}

		$this->assertFalse( $message, $message ); //ha
	}

	/**
	 * Finds a relevant line/lines in a gateway's log array
	 * @param string $log_level One of the constants in \Psr\Log\LogLevel
	 * @param string $match A regex to match against the log lines.
	 * @return array All log lines that match $match.
	 *     FIXME: Or false.  Return an empty array or throw an exception instead.
	 */
	public static function getLogMatches( $log_level, $match ) {
		$log = DonationLoggerFactory::$overrideLogger->messages;
		if ( !array_key_exists( $log_level, $log ) ) {
			return false;
		}
		$return = array ( );
		foreach ( $log[$log_level] as $line ) {
			if ( preg_match( $match, $line ) ) {
				$return[] = $line;
			}
		}
		return $return;
	}

	static function getInnerHTML( $node ) {
		$innerHTML = '';
		$children = $node->childNodes;
		foreach ( $children as $child ) {
			$innerHTML .= $child->ownerDocument->saveXML( $child );
		}
		return $innerHTML;
	}

	public static function unsetVariableFields( &$message ) {
		$fields = array(
			'date', 'source_enqueued_time', 'source_host', 'source_run_id', 'source_version', 'gateway_account'
		);
		foreach ( $fields as $field ) {
			unset( $message[$field] );
		}
	}
}
