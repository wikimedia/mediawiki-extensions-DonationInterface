/*global amazon:true*/
( function( $, mw ) {
	var clientId = mw.config.get( 'wgAmazonGatewayClientID' ),
		sellerId = mw.config.get( 'wgAmazonGatewaySellerID' ),
		sandbox = mw.config.get( 'wgAmazonGatewaySandbox' ),
		returnUrl = mw.config.get( 'wgAmazonGatewayReturnURL' ),
		widgetScript = mw.config.get( 'wgAmazonGatewayWidgetScript' ),
		loginScript = mw.config.get( 'wgAmazonGatewayLoginScript' ),
		loggedIn = false,
		loginError,
		accessToken,
		validTokenPattern = new RegExp( '^Atza' ),
		billingAgreementId,
		orderReferenceId;

	// Adapted from Amazon documentation
	function getURLParameter( name, source ) {
		var pattern = '[?&#]' + name + '=' + '([^&;#]*)',
			matches = new RegExp( pattern ).exec( source ) || ['', ''],
			value = matches[1].replace( /\+/g, '%20' );

		return decodeURIComponent( value ) || null;
	}

	function loadScript( url ) {
		var a = document.createElement( 'script' );
		a.type = 'text/javascript';
		a.async = true;
		a.src = url;
		document.head.appendChild( a );
	}

	function redirectToLogin() {
		$( '#overlay' ).show();
		var loginOptions = {
			scope: 'payments:widget',
			popup: false
		};
		amazon.Login.authorize( loginOptions, returnUrl );
	}

	function showErrorAndLoginButton( message ) {
		$( '#topError' ).append(
			$( '<div class="error">' + message + '</div>' )
		);
		OffAmazonPayments.Button(
			'amazonLogin',
			sellerId,
			{
				type: 'PwA',
				color: 'Gold',
				size: 'large',
				authorization: redirectToLogin
			}
		);
	}

	accessToken = getURLParameter( 'access_token', location.hash );
	loginError = getURLParameter( 'error', location.search );

	// This will be called as soon as the login script is loaded
	window.onAmazonLoginReady = function() {
		amazon.Login.setClientId( clientId );
		amazon.Login.setUseCookie( true );
		amazon.Login.setSandboxMode( sandbox );
		if ( loggedIn ) {
			createWalletWidget();
		} else {
			if ( loginError ) {
				showErrorAndLoginButton(
					getURLParameter( 'error_description', location.search )
					// TODO: better error message with links to alternative donation methods
				);
			} else {
				redirectToLogin();
			}
		}
	};

	if ( typeof accessToken === 'string' && accessToken.match( validTokenPattern ) ) {
		// Payment widgets need this cookie
		document.cookie = "amazon_Login_accessToken=" + accessToken + ";secure";
		loggedIn = true;
		loadScript( widgetScript ); // This will load the login script for you
	} else {
		if ( loginError ) {
			// Load the full widget script to display a button
			loadScript( widgetScript );
		} else {
			// The first time around, only load the login script.
			loadScript( loginScript );
		}
	}

	function createWalletWidget() {
		new OffAmazonPayments.Widgets.Wallet( {
			sellerId: sellerId,
			onReady: function( billingAgreement ) {
				// Will come in handy for recurring payments
				billingAgreementId = billingAgreement.getAmazonBillingAgreementId();
			},
			agreementType: 'OrderReference',
			onOrderReferenceCreate: function( orderReference ) {
				orderReferenceId = orderReference.getAmazonOrderReferenceId();
				$( '#paymentContinue' ).show();
				$( '#paymentContinueBtn' ).off( 'click' );
				$( '#paymentContinueBtn' ).click( submitPayment );
			},
			design: {
				designMode: 'responsive'
			},
			onError: function( error ) {
				// Error message appears directly in widget
				showErrorAndLoginButton( '' );
			}
		} ).bind( 'walletWidget' );
	}

	function submitPayment() {
		$( '#overlay' ).show();
		var postdata = {
			action: 'di_amazon_bill',
			format: 'json',
			orderReferenceId: orderReferenceId
		};

		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				$( '#overlay' ).hide();
				if ( data.errors ) {
					// TODO: correctable error, let 'em correct it
				} else if ( data.success ) {
					// TODO: send donor to TY page, auth/capture money
				} else {
					// TODO: send donor to fail page
				}
			},
			error: function () {
				$( '#overlay' ).hide();
				// TODO: handle when client can't talk to our own API!
			}
		});
	}
} )( jQuery, mediaWiki );
