<?php

/**
 * Gateway form rendering using Mustache
 */
class Gateway_Form_Mustache extends Gateway_Form {
	/**
	 * @var string Janky way to keep track of the template file path that will
	 * be used as the main entry point for rendering.
	 */
	protected $topLevelForm;

	// hack for l10n helper - it needs to be a static function
	static $country;

	/**
	 * @param GatewayAdapter $gateway The live adapter object that is used as
	 * the source for donor data and capabilities discovery.
	 */
	public function __construct( GatewayAdapter $gateway ) {
		parent::__construct( $gateway );

		// TODO: Don't hardcode like this.
		global $wgDonationInterfaceTemplate;
		$this->topLevelForm = $wgDonationInterfaceTemplate;
	}

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throw RuntimeException
	 */
	public function getForm() {
		$data = $this->gateway->getData_Unstaged_Escaped();
		self::$country = $data['country'];

		$config = RequestContext::getMain()->getConfig();

		$data['script_path'] = $config->get( 'ScriptPath' );
		$data['verisign_logo'] = $this->getSmallSecureLogo();
		$data['no_script'] = $this->getNoScript();

		$template = file_get_contents( $this->topLevelForm );
		if ( $template === false ) {
			throw new RuntimeException( "Template file unavailable: [{$this->topLevelForm}]" );
		}

		// TODO: Use MW-core implementation, once we're on REL1_25.
		$code = LightnCandy::compile(
			$template,
			array(
				'flags' => LightnCandy::FLAG_ERROR_EXCEPTION,
				'helpers' => array( 'l10n' => 'Gateway_Form_Mustache::l10n' ),
				'basedir' => array( __DIR__ . '/mustache' ),
				'fileext' => '.html.mustache',
			)
		);
		if ( !$code ) {
			throw new RuntimeException( 'Couldn\'t compile template!' );
		}
		if ( substr( $code, 0, 5 ) === '<?php' ) {
			$code = substr( $code, 5 );
		}
		$renderer = eval( $code );
		if ( !is_callable( $renderer ) ) {
			throw new RuntimeException( 'Can\'t run compiled template!' );
		}

		$html = call_user_func( $renderer, $data, array() );

		return $html;
	}

	/**
	 * Get a message value specific to the donor's country and language
	 * @param array $params first value is used as message key
	 * TODO: use the rest as message parameters
	 * @return string
	 */
	public static function l10n( $params ) {
		if ( !$params ) {
			throw new BadMethodCallException( 'Need at least one message key' );
		}
		$language = RequestContext::getMain()->getLanguage()->getCode();
		return MessageUtils::getCountrySpecificMessage(
			$params[0],
			self::$country,
			$language
		);
	}
}
