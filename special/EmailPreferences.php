<?php

use SmashPig\Core\DataStores\QueueWrapper;

class EmailPreferences extends UnlistedSpecialPage {

	const FALLBACK_COUNTRY = 'US';
	const FALLBACK_LANGUAGE = 'en_US';
	const FALLBACK_SUBPAGE = 'emailPreferences';

	// Note: Coordinate with Getpreferences.php in Civiproxy API, in wmf-civicrm extension.
	const CIVI_NO_RESULTS_ERROR = 'No result found';

	const BAD_DATA_SESSION_KEY = 'email-pref-bad_data';

	public function __construct() {
		parent::__construct( 'EmailPreferences' );
	}

	public function execute( $subpage ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles'
		] );

		$out->addModules( 'ext.donationInterface.emailPreferences' );
		$this->setPageTitle( $subpage );
		$params = $this->getRequest()->getValues();
		$posted = $this->getRequest()->wasPosted();
		if ( $posted && $this->wasCanceled( $params ) ) {
			$out->redirect(
				// FIXME switch this to a DonationInterface setting
				$this->getConfig()->get( 'FundraisingEmailUnsubscribeCancelUri' )
			);
			return;
		}
		if ( !$this->validate( $params, $posted ) ) {
			$this->renderError( $subpage );
			return;
		}
		if ( $posted ) {
			switch ( $subpage ) {
				case 'optin':
					$this->executeOptIn( $params );
					break;
				case 'unsubscribe':
					$this->executeUnsubscribe( $params );
					break;
				case 'emailPreferences':
					$this->executeEmailPreferences( $params );
					break;
				default:
					// TODO: need another form for bad url
					$this->renderError( 'optin' );
			}
		} else {
			if ( $subpage === 'emailPreferences' ) {
				$params += $this->paramsForPreferencesForm(
					$params[ 'checksum' ],
					$params[ 'contact_id' ]
				);
			}
			// if subpage null, we must have no checksum and contact id, so just render a default page
			$this->renderQuery( $subpage ?? self::FALLBACK_SUBPAGE, $params );
		}
	}

	protected function paramsForPreferencesForm( $checksum, $contact_id ) {
		$prefs = CiviproxyConnect::getEmailPreferences( $checksum, $contact_id );

		if ( $prefs[ 'is_error' ] ) {
			$logger = DonationLoggerFactory::getLoggerFromParams(
				'EmailPreferences', true, false, '', null );

			// If Civi returned no match for hash and contact_id, we still show the form,
			// but log a message and set a session flag to prevent a message being
			// placed on the queue.
			if ( $prefs[ 'error_message' ] == self::CIVI_NO_RESULTS_ERROR ) {
				$logger->warning(
					"No results for contact_id $contact_id with checksum $checksum" );

				WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, true );

			} else {
				$logger->error( 'Error from civiproxy: ' . $prefs[ 'error_message' ] );
				throw new RuntimeException( 'Error retrieving current e-mail preferences.' );
			}
		} else {
			WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, false );
		}

		$addedParams = [];
		$uiLang = $this->getLanguage()->getCode();

		// FIXME Correct country and language sorting by locale/proper diacritics ordering

		$addedParams[ 'countries' ] = [];
		$mediaWikCountries = CountryNames::getNames( $uiLang );
		asort( $mediaWikCountries );
		foreach ( $mediaWikCountries as $code => $name ) {
			$addedParams[ 'countries' ][] = [
				'code' => $code,
				'name' => $name,
				'selected' => $code === ( $prefs[ 'country' ] ?? self::FALLBACK_COUNTRY )
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
				'selected' => $code === ( $prefs[ 'preferred_language' ] ?? self::FALLBACK_LANGUAGE )
			];
		}

		$addedParams[ 'sendEmail' ] = $prefs[ 'sendEmail' ];
		$addedParams[ 'dontSendEmail' ] = !$prefs[ 'sendEmail' ];
		$addedParams[ 'first_name' ] = $prefs[ 'first_name' ];
		$addedParams[ 'email' ] = $prefs[ 'email' ];
		$addedParams[ 'snoozeDays' ] = $mwConfig->get( 'DonationInterfaceEmailPreferencesSnoozeDays' );
		return $addedParams;
	}

	public function setupOptIn( $params ) {
		$message = [
			'email' => $params['email'],
		];
		if ( !empty( $params['variant'] ) ) {
			$message['variant'] = $params['variant'];
		}
		if ( !empty( $params['contact_id'] ) && !empty( $params['checksum'] ) ) {
			$message['contact_id'] = $params['contact_id'];
			$message['checksum'] = $params['checksum'];
		}

		if ( !empty( $params['utm_source'] ) ) {
			$message['utm_source'] = $params['utm_source'];
		}
		if ( !empty( $params['utm_medium'] ) ) {
			$message['utm_medium'] = $params['utm_medium'];
		}
		if ( !empty( $params['utm_campaign'] ) ) {
			$message['utm_campaign'] = $params['utm_campaign'];
		}

		return $message;
	}

	protected function executeOptIn( $params ) {
		$message = $this->setupOptIn( $params );

		try {
			QueueWrapper::push( 'opt-in', $message );
			$this->renderSuccess( 'optin', $params );
		} catch ( Exception $e ) {
			$this->renderError( 'optin' );
		}
	}

	protected function executeUnsubscribe( $params ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new BadMethodCallException( 'Not implemented' );
	}

	protected function executeEmailPreferences( $params ) {
		// FIXME Also detect when the user made no changes, and send that info back
		// to the queue consumer?

		// Do nothing if we got bad preferences data to begin with.
		if ( WmfFramework::getSessionValue( self::BAD_DATA_SESSION_KEY ) ) {
			$this->renderSuccess( 'emailPreferences', $params );
			return;
		}

		$message = [
			'checksum' => $params['checksum'],
			'contact_id' => $params['contact_id'],
			'first_name' => $params['first_name'],
			'email' => $params['email'],
			'country' => $params['country'],
			'language' => $params['language'],
		];
		if ( in_array( $params['send_email'], [ 'true', 'false' ] ) ) {
			$message['send_email'] = $params['send_email'];
		} else {
			// selected snooze
			$snoozeDays = $this->getConfig()->get( 'DonationInterfaceEmailPreferencesSnoozeDays' );
			$snoozeDate = new DateTime( "+$snoozeDays days" );
			$message['snooze_date'] = $snoozeDate->format( 'Y-m-d' );
		}

		try {
			QueueWrapper::push( 'email-preferences', $message );
			$this->renderSuccess( 'emailPreferences', $params );
		} catch ( Exception $e ) {
			$this->renderError( 'emailPreferences' );
		}
	}

	protected function renderError( $subpage = 'optin' ) {
		$subpage .= 'Error';
		$this->renderQuery( $subpage, [] );
	}

	protected function renderSuccess( $subpage = 'optin', $params = [] ) {
		$subpage .= 'Success';
		$this->renderQuery( $subpage, $params );
	}

	protected function renderQuery( $subpage, array $params ) {
		$formObj = new EmailForm( $subpage, $params );
		$this->getOutput()->addHTML( $formObj->getForm() );
	}

	protected function validate( array $params, $posted ) {
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

	protected function validateEmail( array $params, $posted ) {
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

	protected function validateToken( array $params, $posted ) {
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

	protected function setPageTitle( $subpage ) {
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
			default:
				$title = $this->msg( 'donate_interface-error-msg-general' );
		}
		$this->getOutput()->setPageTitle( $title );
	}

	protected function wasCanceled( $params ) {
		return isset( $params['submit'] ) && ( $params['submit'] === 'cancel' );
	}
}
