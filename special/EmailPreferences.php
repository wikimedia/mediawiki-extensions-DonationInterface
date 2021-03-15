<?php

use SmashPig\Core\DataStores\QueueWrapper;

class EmailPreferences extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'EmailPreferences' );
	}

	public function execute( $subpage ) {
		// FIXME switch this to a DonationInterface setting
		global $wgFundraisingEmailUnsubscribeCancelUri;

		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->addModules( 'ext.donationInterface.emailPreferences' );
		$this->setPageTitle( $subpage );
		$params = $this->getRequest()->getValues();
		$posted = $this->getRequest()->wasPosted();
		if ( $posted && $this->wasCanceled( $params ) ) {
			$this->getOutput()->redirect(
				$wgFundraisingEmailUnsubscribeCancelUri
			);
			return;
		}
		if ( !$this->validate( $params, $posted, $subpage ) ) {
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
				$params += $this->paramsForPreferencesForm();
			}

			$this->renderQuery( $subpage, $params );
		}
	}

	protected function paramsForPreferencesForm() {
		global $wgDonationInterfaceEmailPrefCtrLanguages;

		// Stub for data to get from CiviProxy, added up in
		// https://gerrit.wikimedia.org/r/c/mediawiki/extensions/DonationInterface/+/677018
		$countryFromCivi = 'NZ';
		$shortLangFromCivi = 'fr';
		$fullLangFromCivi = 'fr-ca';
		$optedInFromCivi = true;

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
				'selected' => $code === $countryFromCivi
			];
		}

		$addedParams[ 'languages' ] = [];
		$languages = LanguageNames::getNames(
				$uiLang, LanguageNames::FALLBACK_NATIVE,
				LanguageNames::LIST_MW_AND_CLDR
		);

		# Only show languages as configured
		$displayLanguages = array_filter(
			$languages,
			function ( $code ) use ( $wgDonationInterfaceEmailPrefCtrLanguages ) {
				return in_array( $code, $wgDonationInterfaceEmailPrefCtrLanguages );
			},
			ARRAY_FILTER_USE_KEY
		);

		// If the user's exact requested language is available, use that, otherwise
		// try the more general code. If the general language is not available, add
		// the user's language to the form as the selected language.

		// FIXME Coordinate this logic with actual e-mail sends? Warn the user if we
		// actually never send e-mails in the language we want?

		// Exact language from Civi in MW and sendable to?
		if ( isset( $displayLanguages[ $fullLangFromCivi ] ) ) {
			$selectedLang = $fullLangFromCivi;

		// Exact language from Civi not in MW but still sendable to, and the general language
		// is in MW? Use their Civi lag code in the form but show the general language name.
		} elseif ( in_array( $fullLangFromCivi, $wgDonationInterfaceEmailPrefCtrLanguages ) &&
				!isset( $languages[ $fullLangFromCivi ] ) &&
				isset( $languages[ $shortLangFromCivi ] ) ) {
			$displayLanguages[ $fullLangFromCivi ] = $languages[ $shortLangFromCivi ];
			$selectedLang = $fullLangFromCivi;

		// General language from Civi in MW and sendable to
		} elseif ( isset( $displayLanguages[ $shortLangFromCivi ] ) ) {
			$selectedLang = $shortLangFromCivi;

		// General language from Civi not sendable to but in MW?
		// Use their Civi lag code in the form but show the general language name.
		} elseif ( isset( $languages[ $shortLangFromCivi ] ) ) {
			$displayLanguages[ $fullLangFromCivi ] = $languages[ $shortLangFromCivi ];
			$selectedLang = $fullLangFromCivi;

		} else {
			# FIXME Last-resort fallback?
			$selectedLang = null;
		}

		asort( $displayLanguages );

		foreach ( $displayLanguages as $code => $name ) {
			$addedParams[ 'languages' ][] = [
					'code' => $code,
					'name' => $name,
					'selected' => $code === $selectedLang
			];
		}

		$addedParams[ 'sendEmail' ] = $optedInFromCivi;
		$addedParams[ 'dontSendEmail' ] = !$optedInFromCivi;

		return $addedParams;
	}

	public function setupOptIn( $params ) {
		$message = [
			'email' => $params['email'],
		];
		if ( !empty( $params['variant'] ) ) {
			$message['variant'] = $params['variant'];
		}
		if ( !empty( $params['contact_id'] ) && !empty( $params['contact_hash'] ) ) {
			$message['contact_id'] = $params['contact_id'];
			$message['contact_hash'] = $params['contact_hash'];
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

		$message = [
			'contact_hash' => $params[ 'contact_hash'],
			'contact_id' => $params[ 'contact_id'],
			'country' => $params[ 'country'],
			'language' => $params[ 'language'],
			'send_email' => $params[ 'send_email']
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

	protected function validate( array $params, $posted, $subpage ) {
		if ( $subpage !== 'emailPreferences' &&
			!$this->validateEmail( $params, $posted ) ) {
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
				$title = wfMessage( 'fundraisersubscribe' );
				break;
			case 'unsubscribe':
				$title = wfMessage( 'fundraiserunsubscribe' );
				break;
			case 'emailPreferences':
				$title = wfMessage( 'emailpreferences-title' );
				break;
			default:
				$title = wfMessage( 'donate_interface-error-msg-general' );
		}
		$this->getOutput()->setPageTitle( $title );
	}

	protected function wasCanceled( $params ) {
		return isset( $params['submit'] ) && ( $params['submit'] === 'cancel' );
	}
}
