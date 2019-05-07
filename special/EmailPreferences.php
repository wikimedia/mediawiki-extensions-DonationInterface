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
		$this->getOutput()->addModules( 'donationInterface.skinOverride' );
		$this->setPageTitle( $subpage );
		$params = $this->getRequest()->getValues();
		$posted = $this->getRequest()->wasPosted();
		if ( $posted && $this->wasCanceled( $params ) ) {
			$this->getOutput()->redirect(
				$wgFundraisingEmailUnsubscribeCancelUri
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
				default:
					// TODO: need another form for bad url
					$this->renderError( 'optin' );
			}
		} else {
			$this->renderQuery( $subpage, $params );
		}
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
			case 'optin':
				$title = wfMessage( 'fundraisersubscribe' );
				break;
			case 'unsubscribe':
				$title = wfMessage( 'fundraiserunsubscribe' );
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
