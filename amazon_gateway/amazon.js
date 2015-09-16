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

	$( function() {
		// Add a couple divs to hold the widgets
		var container = $( '.submethods' ).parent();
		container.prepend( '<div id="walletWidget" />' );
		container.prepend( '<div id="amazonLogin" />' );
	});

	// Adapted from Amazon documentation, will get parameters from fragment as
	// well as querystring and accepts Amazon's custom delimiters
	function getURLParameter( name, source ) {
		var pattern = '[?&#]' + name + '=([^&;#]*)',
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

	function addErrorMessage( message ) {
		$( '#topError' ).append(
			$( '<p class="error">' + message + '</p>' )
		);
	}

	function showErrorAndLoginButton( message ) {
		if ( message ) {
			addErrorMessage( message );
		}
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

	function tokenExpired() {
		// Re-create widget so it displays timeout error message
		createWalletWidget();
		showErrorAndLoginButton();
	}

	accessToken = getURLParameter( 'access_token', location.hash );
	loginError = getURLParameter( 'error', location.search );

	// This will be called as soon as the login script is loaded
	window.onAmazonLoginReady = function() {
		var tokenLifetime;
		amazon.Login.setClientId( clientId );
		amazon.Login.setUseCookie( true );
		amazon.Login.setSandboxMode( sandbox );
		if ( loggedIn ) {
			tokenLifetime = parseInt( getURLParameter( 'expires_in', location.hash ), 10 );
			createWalletWidget();
			setTimeout( tokenLifetime * 1000, tokenExpired );
		} else {
			if ( loginError ) {
				showErrorAndLoginButton(
					getURLParameter( 'error_description', location.search )
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
		var params = {
			sellerId: sellerId,
			onReady: function( billingAgreement ) {
				// Will come in handy for recurring payments
				billingAgreementId = billingAgreement.getAmazonBillingAgreementId();
			},
			agreementType: 'OrderReference',
			onOrderReferenceCreate: function( orderReference ) {
				if ( orderReferenceId ) {
					// Redisplaying for an existing order, no need to continue
					return;
				}
				orderReferenceId = orderReference.getAmazonOrderReferenceId();
				$( '#paymentContinue' ).show();
				// FIXME: Unbind click handler from forms.js
				$( '#paymentContinueBtn' ).off( 'click' );
				$( '#paymentContinueBtn' ).click( submitPayment );
			},
			onPaymentSelect: function() {
				// In case we hid the button because of an invalid payment error
				$( '#paymentContinue' ).show();
			},
			design: {
				designMode: 'responsive'
			},
			onError: function( error ) {
				// Error message appears directly in widget
				showErrorAndLoginButton();
			}
		};
		// If we are refreshing the widget to display a correctable error,
		// we need to set the Amazon order reference ID for continuity
		if ( orderReferenceId ) {
			params.amazonOrderReferenceId = orderReferenceId;
		}
		new OffAmazonPayments.Widgets.Wallet( params ).bind( 'walletWidget' );
	}

	function handleErrors( errors ) {
		var code,
			refreshWallet = false;

		for ( code in errors ) {
			if ( !errors.hasOwnProperty( code ) ) {
				continue;
			}
			addErrorMessage( errors[code] );
			if ( code === 'InvalidPaymentMethod' ) {
				// Card declined, but they can try another
				refreshWallet = true;
			}
		}

		if ( refreshWallet ) {
			// Redisplay the widget to show an error and let the donor pick a different card
			$( '#paymentContinue' ).hide();
			createWalletWidget();
		}
	}

	function lockDonationAmount() {
		if ( $( '#amount_input' ).is( ':visible' ) ) {
			$( '#amount_input' ).hide();
			$( '#selected-amount' )
				.html( $( '#amount' ).val() + ' ' + $( '#currency_code' ).val() )
				.show();
		}
	}

	function submitPayment() {
		if ( !window.validateAmount() ) {
			return;
		}
		$( '#topError' ).html('');
		$( '#overlay' ).show();
		lockDonationAmount();
		var postdata = {
			action: 'di_amazon_bill',
			format: 'json',
			orderReferenceId: orderReferenceId,
			amount: $( '#amount' ).val(),
			currency_code: $( '#currency_code' ).val()
		};

		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				$( '#overlay' ).hide();
				if ( data.errors ) {
					handleErrors( data.errors );
				} else if ( data.redirect ) {
					location.href = data.redirect;
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
