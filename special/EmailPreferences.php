<?php

use MediaWiki\Extension\CLDR\CountryNames;
use MediaWiki\Extension\CLDR\LanguageNames;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use SmashPig\Core\DataStores\QueueWrapper;

class EmailPreferences extends UnlistedSpecialPage {

	use RequestNewChecksumLinkTrait;

	const FALLBACK_COUNTRY = 'US';
	const FALLBACK_LANGUAGE = 'en_US';
	const FALLBACK_SUBPAGE = 'emailPreferences';

	// Note: Coordinate with Getpreferences.php in Civiproxy API, in wmf-civicrm extension.
	const CIVI_NO_RESULTS_ERROR = 'No result found';

	const BAD_DATA_SESSION_KEY = 'email-pref-bad_data';

	public function __construct() {
		parent::__construct( 'EmailPreferences' );
	}

	/** @inheritDoc */
	public function execute( $subpage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->setUpClientSideChecksumRequest( $subpage );
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.emailPreferences'
		] );
		$this->setPageTitle( $subpage );
		$requestParameters = $this->getRequest()->getValues();
		$requestParameters = $this->mapWmfToUtm( $requestParameters );
		$posted = $this->getRequest()->wasPosted();
		if ( $posted && $this->wasCanceled( $requestParameters ) ) {
			$out->redirect(
				// FIXME switch this to a DonationInterface setting
				$this->getConfig()->get( 'FundraisingEmailUnsubscribeCancelUri' )
			);
			return;
		}
		if ( !$this->validate( $requestParameters, $posted ) ) {
			$this->renderError( $subpage );
			return;
		}
		if ( $posted ) {
			switch ( $subpage ) {
				case 'optin':
					$this->executeOptIn( $requestParameters );
					break;
				case 'unsubscribe':
					$this->executeUnsubscribe( $requestParameters );
					break;
				case 'emailPreferences':
					// if the fallback unsubscribe epc, can only trigger unsubscribe since no other data from civi proxy provided
					if ( !empty( $requestParameters['type'] ) && $requestParameters['type'] === 'unsubscribe' ) {
						$this->executeUnsubscribe( $requestParameters );
					} else {
						$this->executeEmailPreferences( $requestParameters );
					}
					break;
				default:
					$this->renderError();
			}
		} else {
			if ( $subpage === 'confirmEmail' ) {
				// for verification, we need to check if checksum is valid, then proceed
				// validate if checksum is valid with epc
				// two part checksum, first part is for civiproxy, second part is for email
				$preferences = CiviproxyConnect::getEmailPreferences(
					$requestParameters[ 'checksum' ],
					$requestParameters[ 'contact_id' ]
				);
				if ( $preferences[ 'is_error' ] ) {
					$this->renderError( $subpage );
					return;
				}
				if ( $preferences['email'] === $requestParameters['email'] ) {
					// if email matches, seems like we had this setup already
					$this->renderSuccess( $subpage, $requestParameters );
					return;
				}
				// validate if email checksum valid
				if ( !$this->isEmailChecksumValid(
					$requestParameters['email'],
					$requestParameters['email_checksum'],
					$requestParameters['contact_id']
				) ) {
					$this->renderError( $subpage );
					return;
				}
				// if checksum is valid, proceed to set primary email
				$this->executeSetPrimaryEmail( $requestParameters );
				return;
			}
			if ( $subpage === 'emailPreferences' && !$this->isChecksumExpired() ) {
				$preferences = CiviproxyConnect::getEmailPreferences(
					$requestParameters[ 'checksum' ],
					$requestParameters[ 'contact_id' ]
				);
				if ( $preferences[ 'is_error' ] ) {
					$subpage = $this->errorHandling( $preferences['error_message'], $requestParameters );
					if ( $subpage === 'emailPreferences' ) {
						$this->renderError( $subpage );
						return;
					}
				} else {
					WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, false );
					$emailPreferenceParameters = $this->paramsForPreferencesForm( $preferences );
					$requestParameters = array_merge( $requestParameters, $emailPreferenceParameters );
				}
			}
			// if subpage null, we must have no checksum and contact id, so just render a default page
			$this->renderQuery( $subpage ?? self::FALLBACK_SUBPAGE, $requestParameters );
		}
	}

	protected function getSecretHashChecksum( string $email, string $contact_id ): string {
		$secretHashKey =
			$this->getConfig()->get( 'DonationInterfaceEmailUnsubscribeHashSecretKey' );
		return hash( 'sha256', $email . '_' . $contact_id . '_' . $secretHashKey );
	}

	protected function isEmailChecksumValid( string $email, string $email_checksum, string $contact_id ): bool {
		$computedHash = $this->getSecretHashChecksum( $email, $contact_id );
		if ( $email_checksum !== $computedHash ) {
			return false;
		}
		return true;
	}

	protected function errorHandling( string $errorMessage, array $requestParameters ): string {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'EmailPreferences', true, false, '', null
		);
		// if Civi proxy not live, use hash to validate url
		if ( $errorMessage === self::CIVI_NO_RESULTS_ERROR ) {
			// If Civi returned no match for hash and contact_id, we still show the form,
			// but log a message and set a session flag to prevent a message being
			// placed on the queue.
			$logger->warning(
				"No results for contact_id" . $requestParameters[ 'contact_id' ]
				. " with checksum " . $requestParameters[ 'checksum' ] );
			WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, true );
		} else {
			$logger->error( 'Error from civiproxy: ' . $errorMessage );
			// validate the hash then
			$isHashValid = $this->validateHash( $requestParameters );
			if ( $isHashValid && !$this->isChecksumExpired() ) {
				// render unsubscribe page instead if checksum is not expired
				return 'unsubscribe';
			}
		}
		return 'emailPreferences';
	}

	/**
	 * will depreciate this part if civi-proxy down, auto render unsubscribe link no need to validate it
	 */
	protected function validateHash( array $params ): bool {
		if ( !isset( $params[ 'email' ] ) || !isset( $params[ 'contact_id' ] ) || !isset( $params[ 'hash' ] ) ) {
			return false;
		}
		$hashSecretKey =
			$this->getConfig()->get( 'DonationInterfaceEmailUnsubscribeHashSecretKey' );

		$logger = DonationLoggerFactory::getLoggerFromParams(
			'EmailPreferences', true, false, '', null );

		$email = $params['email'];
		$contact_id = $params['contact_id'];
		$hash = strtolower( $params['hash'] );

		$computedHash = hash( 'sha1', $contact_id . $email . $hashSecretKey );
		if ( $computedHash != $hash ) {
			$logger->info( "Hash verification failed! Expected '$computedHash' got '$hash'." );
			return false;
		} else {
			$logger->info( "Hash verification success!" );
		}

		return true;
	}

	protected function paramsForPreferencesForm( array $preferences ): array {
		$addedParams = [];
		// find the uselang for targeted prefer language
		$context = RequestContext::getMain();
		if ( $preferences[ 'preferred_language' ] ) {
			switch ( $preferences[ 'preferred_language' ] ) {
				case 'zh_TW':
					$selectedLang = 'zh-hant';
					break;
				case 'zh_CN':
					$selectedLang = 'zh-hans';
					break;
				case 'fr_CA':
					$selectedLang = 'frc';
					break;
				case 'pt_BR':
					$selectedLang = 'pt-br';
					break;
				case 'es_PR':
					$selectedLang = 'es-formal';
					break;
				default:
					$selectedLang = explode( '_', $preferences[ 'preferred_language' ] )[0];
			}
			$context->setLanguage( $selectedLang );
		}
		$uiLang = $context->getLanguage()->getCode();
		$addedParams[ 'countries' ] = [];
		$mediaWikCountries = CountryNames::getNames( $uiLang );
		asort( $mediaWikCountries );
		foreach ( $mediaWikCountries as $code => $name ) {
			$addedParams[ 'countries' ][] = [
				'code' => $code,
				'name' => $name,
				'selected' => $code === ( $preferences[ 'country' ] ?? self::FALLBACK_COUNTRY )
			];
		}

		$addedParams[ 'languages' ] = [];
		$mediaWikiLanguages = LanguageNames::getNames(
			$uiLang, LanguageNames::FALLBACK_NATIVE,
			LanguageNames::LIST_MW_AND_CLDR
		);

		// Only show languages configured in $wgDonationInterfaceEmailPreferencesLanguages
		// (should be the languages we can send e-mails to)
		$mwConfig = $this->getConfig();
		$emailPreferencesLanguages = $mwConfig->get( 'DonationInterfaceEmailPreferencesLanguages' );
		$labels = [];
		foreach ( $emailPreferencesLanguages as $code ) {
			[ $language, $country ] = explode( '_', $code );
			$wikiStyle = $language . '-' . strtolower( $country );
			if ( in_array( $wikiStyle, $mediaWikiLanguages ) ) {
				$label = $mediaWikiLanguages[ $wikiStyle ];
			} else {
				$label = $mediaWikiLanguages[ $language ] . ' (' . $mediaWikCountries[ $country ] . ')';
			}
			$labels[] = $label;
		}
		$displayLanguages = array_combine( $emailPreferencesLanguages, $labels );

		asort( $displayLanguages );

		foreach ( $displayLanguages as $code => $name ) {
			$addedParams[ 'languages' ][] = [
				'code' => $code,
				'name' => $name,
				'selected' => $code === ( $preferences[ 'preferred_language' ] ?? self::FALLBACK_LANGUAGE )
			];
		}

		$addedParams[ 'sendEmail' ] = $preferences[ 'sendEmail' ];
		$addedParams[ 'dontSendEmail' ] = !$preferences[ 'sendEmail' ];
		$addedParams[ 'first_name' ] = $preferences[ 'first_name' ];
		$addedParams[ 'email' ] = $preferences[ 'email' ];
		$addedParams[ 'snoozeDays' ] = $mwConfig->get( 'DonationInterfaceEmailPreferencesSnoozeDays' );
		$addedParams[ 'isSnoozed' ] = $this->isSnoozed( $preferences[ 'snooze_date' ] );
		$addedParams[ 'hasPaypal' ] = $preferences[ 'has_paypal' ];
		return $addedParams;
	}

	public function setupQueueParams( array $params, string $queueName ): array {
		switch ( $queueName ) {
			case 'set-primary-email':
				$message = [
					'checksum' => $params['checksum'],
					'contact_id' => $params['contact_id'],
					'email' => $params['email']
				];
				break;
			case 'email-preferences':
				$message = [
					'checksum' => $params['checksum'],
					'contact_id' => $params['contact_id'],
					'email' => $params['email'],
					'country' => $params['country'] ?? null,
					'language' => $params['language'] ?? null,
					'email_checksum' => $this->getSecretHashChecksum( $params['email'], $params['contact_id'] )
				];
				if ( in_array( $params['send_email'], [ 'true', 'false' ] ) ) {
					$message['send_email'] = $params['send_email'];
				} else {
					// selected snooze
					$snoozeDays = $this->getConfig()->get( 'DonationInterfaceEmailPreferencesSnoozeDays' );
					$snoozeDate = new DateTime( "+$snoozeDays days" );
					$message['snooze_date'] = $snoozeDate->format( 'Y-m-d' );
				}
				break;
			case 'opt-in':
				$message = [
					'checksum' => $params['checksum'],
					'contact_id' => $params['contact_id'],
					'email' => $params['email'],
					'send_email' => 'true',
				];
				break;
			case 'unsubscribe':
				$message = [
					'checksum' => $params['checksum'],
					'contact_id' => $params['contact_id'],
					'email' => $params['email'],
					'send_email' => 'false',
				];
				break;
			default:
				$message = [];
		}

		return $message;
	}

	protected function executeSetPrimaryEmail( array $params ): void {
		$message = $this->setupQueueParams( $params, 'set-primary-email' );

		try {
			QueueWrapper::push( 'set-primary-email', $message );
			$this->renderSuccess( 'confirmEmail', $params );
		} catch ( Exception ) {
			$this->renderError( 'confirmEmail' );
		}
	}

	protected function executeOptIn( array $params ): void {
		$message = $this->setupQueueParams( $params, 'opt-in' );

		try {
			QueueWrapper::push( 'email-preferences', $message );
			$this->renderSuccess( 'optin', $params );
		} catch ( Exception ) {
			$this->renderError( 'optin' );
		}
	}

	protected function executeUnsubscribe( array $params ): void {
		$message = $this->setupQueueParams( $params, 'unsubscribe' );
		try {
			// treat unsubscribe as email-pref to double check checksum over there
			QueueWrapper::push( 'email-preferences', $message );
			$this->renderSuccess( 'unsubscribe', $params );
		} catch ( Exception ) {
			$this->renderError( 'unsubscribe' );
		}
	}

	protected function executeEmailPreferences( array $params ): void {
		// FIXME Also detect when the user made no changes, and send that info back
		// to the queue consumer?

		// Do nothing if we got bad preferences data to begin with.
		if ( WmfFramework::getSessionValue( self::BAD_DATA_SESSION_KEY ) ) {
			$this->renderSuccess( 'emailPreferences', $params );
			return;
		}
		$message = $this->setupQueueParams( $params, 'email-preferences' );
		try {
			QueueWrapper::push( 'email-preferences', $message );
			$this->renderSuccess( 'emailPreferences', $params );
		} catch ( Exception ) {
			$this->renderError( 'emailPreferences' );
		}
	}

	protected function renderError( string $subpage = 'general' ): void {
		$subpage .= 'Error';
		$this->renderQuery( $subpage, [] );
	}

	protected function renderSuccess( string $subpage = 'general', array $params = [] ): void {
		$subpage .= 'Success';
		$this->renderQuery( $subpage, $params );
	}

	protected function renderQuery( string $subpage, array $params ): void {
		$formObj = new EmailForm( $subpage, $params );
		$this->getOutput()->addHTML( $formObj->getForm() );
	}

	protected function validate( array $params, bool $posted ): bool {
		if ( !$this->validateEmail( $params, $posted ) ) {
			return false;
		}
		if ( !$this->validateToken( $params, $posted ) ) {
			return false;
		}
		// The rest of the parameters should just be alphanumeric, underscore, and hyphen
		foreach ( $params as $name => $value ) {
			if ( in_array( $name, [ 'email', 'token', 'title' ], true ) ) {
				continue;
			}
			if ( !preg_match( '/^[a-zA-Z0-9_-]*$/', $value ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateEmail( array $params, bool $posted ): bool {
		if ( empty( $params['email'] ) ) {
			// When we post back, we need an email
			if ( $posted ) {
				return false;
			}
		} else {
			if ( !filter_var( $params['email'], FILTER_VALIDATE_EMAIL ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateToken( array $params, bool $posted ): bool {
		if ( empty( $params['token'] ) ) {
			if ( $posted ) {
				return false;
			}
		} else {
			$session = RequestContext::getMain()->getRequest()->getSession();
			$token = $session->getToken();
			if ( !$token->match( $params['token'] ) ) {
				return false;
			}
		}
		return true;
	}

	protected function setPageTitle( string $subpage ): void {
		switch ( $subpage ) {
			# FIXME The messages for optin and unsubscribe only exist in the
			# FundraisingEmailUnsubscribe extension.
			case 'optin':
				$title = $this->msg( 'fundraisersubscribe' );
				break;
			case 'unsubscribe':
				$title = $this->msg( 'fundraiserunsubscribe' );
				break;
			case 'emailPreferences':
				$title = $this->msg( 'emailpreferences-title' );
				break;
			case 'confirmEmail':
				$title = $this->msg( 'emailpreferences-confirmemail-title' );
				break;
			default:
				$title = $this->msg( 'donate_interface-error-msg-general' );
		}
		$this->getOutput()->setPageTitleMsg( $title );
	}

	protected function wasCanceled( array $params ): bool {
		return isset( $params['submit'] ) && ( $params['submit'] === 'cancel' );
	}

	protected function isSnoozed( ?string $snoozeDate ): bool {
		return $snoozeDate && new DateTime( $snoozeDate ) > new DateTime();
	}

	/**
	 * Replaces incoming wmf_* parameters with utm_* parameters for internal use
	 *
	 * @param array $parameters
	 * @return array
	 */
	protected function mapWmfToUtm( array $parameters ): array {
		$mapped = [];
		foreach ( $parameters as $key => $value ) {
			$mapped[ str_replace( 'wmf_', 'utm_', $key ) ] = $value;
		}
		return $mapped;
	}
}
