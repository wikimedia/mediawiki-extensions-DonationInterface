<?php

use SmashPig\Core\DataStores\QueueWrapper;

class EmailPreferences extends UnlistedSpecialPage {

	const FALLBACK_COUNTRY = 'US';
	const FALLBACK_LANGUAGE = 'en';

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

			$this->renderQuery( $subpage, $params );
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
				throw new RuntimeException( 'Error retrieving curent e-mail preferences.' );
			}
		} else {
			WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, false );
		}

		$addedParams = [];
		$uiLang = $this->getLanguage()->getCode();

		// FIXME Correct country and language sorting by locale/proper diacritics ordering

		$addedParams[ 'countries' ] = [];
		$countries = CountryNames::getNames( $uiLang );
		asort( $countries );
		foreach ( $countries as $code => $name ) {
			$addedParams[ 'countries' ][] = [
				'code' => $code,
				'name' => $name,
				'selected' => $code === ( $prefs[ 'country' ] ?? self::FALLBACK_COUNTRY )
			];
		}

		$addedParams[ 'languages' ] = [];
		$languages = LanguageNames::getNames(
			$uiLang, LanguageNames::FALLBACK_NATIVE,
			LanguageNames::LIST_MW_AND_CLDR
		);

		// Only show languages configured in $wgDonationInterfaceEmailPrefCtrLanguages
		// (should be the languages we can send e-mails to)
		$emailPrefCtrLanguages = $this->getConfig()->get( 'DonationInterfaceEmailPrefCtrLanguages' );
		$displayLanguages = array_filter(
			$languages,
			static function ( $code ) use ( $emailPrefCtrLanguages ) {
				return in_array( $code, $emailPrefCtrLanguages );
			},
			ARRAY_FILTER_USE_KEY
		);

		// If the user's exact requested language is available, use that, otherwise
		// try the more general code. If the general language is not available, add
		// the user's language to the form as the selected language.

		// FIXME Coordinate this logic with actual e-mail sends? Warn the user if we
		// actually never send e-mails in the language we want?

		$prefsShortLang = ( $prefs[ 'shortLang' ] ?? self::FALLBACK_LANGUAGE );

		// Exact language from Civi in MW and sendable to (for example, in Civi it's fr-ca,
		// and that language is sendable to, and is in the list from MW)?
		if ( isset( $displayLanguages[ $prefs[ 'fullLang' ] ] ) ) {
			$selectedLang = $prefs[ 'fullLang' ];

		// Exact language from Civi not in MW but still sendable to, and the general language
		// is in MW (for example, in Civi it's fr-ca, and that language is sendable to,
		// but it's not in the list from MW, but fr is in that list)?
		// In that case, use their Civi lang code as the form value but associate that
		// value with the general language name.
		} elseif ( in_array( $prefs[ 'fullLang' ], $emailPrefCtrLanguages ) &&
				!isset( $languages[ $prefs[ 'fullLang' ] ] ) &&
				isset( $languages[ $prefsShortLang ] ) ) {
			$displayLanguages[ $prefs[ 'fullLang' ] ] = $languages[ $prefsShortLang ];
			$selectedLang = $prefs[ 'fullLang' ];

			// Also unset any entry there may be in $displayLanguages with the general language
			// to prevent possible multiple options with the same name in the UI.
			unset( $displayLanguages[ $prefsShortLang ] );

		// General language from Civi in MW and sendable to (for example, in Civi it's
		// fr-ca, but only fr is sendable to, and fr is in the list from MW)?
		} elseif ( isset( $displayLanguages[ $prefsShortLang ] ) ) {
			$selectedLang = $prefsShortLang;

		// General language from Civi not sendable to but is in MW (for example, in Civi it's
		// fr-ca, and fr is not sendable to, but is in the list from MW)?
		// In that case their Civi lang code in the form but show the general language name.
		} elseif ( isset( $languages[ $prefsShortLang ] ) ) {
			$displayLanguages[ $prefs[ 'fullLang' ] ] = $languages[ $prefsShortLang ];
			$selectedLang = $prefs[ 'fullLang' ];

		// FIXME This case occurs if neither language variant nor the general language
		// in Civi (for example, neither fr-ca nor fr) are sendable to or in the list
		// from MW. Here the form will just have the first option in the list selected. Maybe
		// instead we should have a fallback option based on country? Though this
		// seems unlikely to occur, since only users who did actually get an e-mail
		// should get here.
		} else {
			$selectedLang = $prefsShortLang;
			$logger = DonationLoggerFactory::getLoggerFromParams(
				'EmailPreferences', true, false, '', null );

			$logger->warning(
				'No display options available for language ' . $prefs[ 'fullLang' ] );
		}

		asort( $displayLanguages );

		foreach ( $displayLanguages as $code => $name ) {
			$addedParams[ 'languages' ][] = [
				'code' => $code,
				'name' => $name,
				'selected' => $code === $selectedLang
			];
		}

		$addedParams[ 'sendEmail' ] = $prefs[ 'sendEmail' ];
		$addedParams[ 'dontSendEmail' ] = !$prefs[ 'sendEmail' ];
		$addedParams[ 'first_name' ] = $prefs[ 'first_name' ];
		$addedParams[ 'email' ] = $prefs[ 'email' ];
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
			'send_email' => $params['send_email']
		];

		try {
			QueueWrapper::push( 'email-preferences', $message );
			$this->renderSuccess( 'emailPreferences', $params );
		} catch ( Exception $e ) {
			$this->renderError( 'optin' );
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
				// The title text to show is included in the mustache template, and the
				// title of the tab/window will be taken from global config variable,
				// $wgSitename.
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
