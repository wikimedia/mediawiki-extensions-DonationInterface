/*global amazon:true, OffAmazonPayments:true*/
( function( $, mw ) {
	var clientId = mw.config.get( 'wgAmazonGatewayClientID' ),
		sellerId = mw.config.get( 'wgAmazonGatewaySellerID' ),
		sandbox = mw.config.get( 'wgAmazonGatewaySandbox' ),
		returnUrl = mw.config.get( 'wgAmazonGatewayReturnURL' ),
		widgetScript = mw.config.get( 'wgAmazonGatewayWidgetScript' ),
		loginScript = mw.config.get( 'wgAmazonGatewayLoginScript' ),
		failPage = mw.config.get( 'wgAmazonGatewayFailPage' ),
		isRecurring = $( '#recurring' ).val(),
		loggedIn = false,
		loginError,
		accessToken,
		validTokenPattern = new RegExp( '^Atza' ),
		billingAgreementId,
		orderReferenceId,
		recurConsentGranted = false,
		cardSelected = false,
		cardSelectTimeout,
		// If no card selected after this long, show link to other ways to give
		// in case the donor has no cards registered with Amazon
		CARD_SELECT_DELAY = 5000;

	$( function() {
		// Add a couple divs to hold the widgets
		var container = $( '.submethods' ).parent();
		container.prepend( '<div id="consentWidget" />' );
		container.prepend( '<div id="walletWidget" />' );
		container.prepend( '<div id="amazonLogin" />' );
		// Set the click handler
		$( '#paymentSubmitBtn' ).click( submitPayment );
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
		$( '#errorReference' )
			.removeClass( 'errorMsgHide' )
			.addClass( 'errorMsg' );
	}

	function reloadPage() {
		var qsParams = $( '#payment-form' ).serializeArray();
		document.location.replace( mw.util.getUrl( 'Special:AmazonGateway', qsParams ) );
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
				authorization: reloadPage
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
			setTimeout( tokenExpired, tokenLifetime * 1000 );
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

	function showOtherWaysLink() {
		var url = mw.config.get( 'wgAmazonGatewayOtherWaysURL' ),
			text = mw.message( 'donate_interface-otherways-short' );
		addErrorMessage( '<a href="' + url + '">' + text + '</a>' );
	}

	function setSubmitVisibility() {
		var show = true;
		if ( !cardSelected ) {
			show = false;
		}
		if ( isRecurring && !recurConsentGranted ) {
			show = false;
		}
		if ( show ) {
			$( '#paymentSubmit' ).show();
		} else {
			$( '#paymentSubmit' ).hide();
		}
	}

	function createWalletWidget() {
		var params = {
			sellerId: sellerId,
			onReady: function( billingAgreement ) {
				if ( !cardSelected ) {
					cardSelectTimeout = setTimeout( showOtherWaysLink, CARD_SELECT_DELAY );
				}
				if ( !billingAgreementId ) {
					billingAgreementId = billingAgreement.getAmazonBillingAgreementId();
					if ( isRecurring ) {
						createConsentWidget();
					}
				}
			},
			agreementType: isRecurring ? 'BillingAgreement' : 'OrderReference',
			onOrderReferenceCreate: function( orderReference ) {
				if ( orderReferenceId ) {
					// Redisplaying for an existing order, no need to continue
					return;
				}
				orderReferenceId = orderReference.getAmazonOrderReferenceId();
			},
			onPaymentSelect: function() {
				if ( !cardSelected ) {
					cardSelected = true;
					setSubmitVisibility();
				}
				if ( cardSelectTimeout ) {
					clearTimeout( cardSelectTimeout );
					delete cardSelectTimeout;
				}
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
		if ( billingAgreementId ) {
			params.amazonBillingAgreementId = billingAgreementId;
		}
		new OffAmazonPayments.Widgets.Wallet( params ).bind( 'walletWidget' );
	}

	function handleConsentStatus( billingAgreementConsentStatus ) {
		// getConsentStatus returns a string for some reason
		recurConsentGranted =
			( billingAgreementConsentStatus.getConsentStatus() === 'true' );
		setSubmitVisibility();
	}

	function createConsentWidget() {
		var params = {
			sellerId: sellerId,
			amazonBillingAgreementId: billingAgreementId,
			design: {
				designMode: 'responsive'
			},
			onReady: handleConsentStatus,
			onConsent: handleConsentStatus,
			onError: function( error ) {
				showErrorAndLoginButton();
			}
		};
		$( '#consentWidget' ).show();
		new OffAmazonPayments.Widgets.Consent( params ).bind( 'consentWidget' );
	}

	function handleErrors( errors ) {
		var code,
			refreshWallet = false;

		for ( code in errors ) {
			if ( !errors.hasOwnProperty( code ) ) {
				continue;
			}
			if ( code === 'token-mismatch' ) {
				// Session has expired, we need to reload the whole page.
				// FIXME: something nicer than an alert box
				alert( errors[code] );
				reloadPage();
			}
			addErrorMessage( errors[code] );
			if ( code === 'InvalidPaymentMethod' ) {
				// Card declined, but they can try another
				refreshWallet = true;
			}
		}

		if ( refreshWallet ) {
			// Redisplay the widget to show an error and let the donor pick a different card
			cardSelected = false;
			setSubmitVisibility();
			createWalletWidget();
		}
	}

	function lockDonationAmount() {
		if ( $( '#amount_input' ).is( ':visible' ) ) {
			$( '#amount_input' ).hide();
			$( '#selected-amount' )
				.text( $( '#amount' ).val() + ' ' + $( '#currency' ).val() )
				.show();
		}
	}

	function submitPayment() {
		if ( !mw.donationInterface.validation.validateAmount() ) {
			return;
		}
		if ( !cardSelected ) {
			showOtherWays();
			return;
		}
		if ( isRecurring && !recurConsentGranted ) {
			//TODO: error message
			return;
		}
		$( '#topError' ).html('');
		$( '#errorReference' )
			.removeClass( 'errorMsg' )
			.addClass( 'errorMsgHide' );
		$( '#overlay' ).show();
		lockDonationAmount();
		var postdata = {
			action: 'di_amazon_bill',
			format: 'json',
			recurring: isRecurring,
			amount: $( '#amount' ).val(),
			currency: $( '#currency' ).val(),
			wmf_token: $( '#wmf_token' ).val()
		};

		if ( isRecurring ) {
			postdata.billingAgreementId = billingAgreementId;
		} else {
			postdata.orderReferenceId = orderReferenceId;
		}

		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				if ( data.errors ) {
					$( '#overlay' ).hide();
					handleErrors( data.errors );
				} else if ( data.redirect ) {
					location.href = data.redirect;
				} else {
					location.href = failPage;
				}
			},
			error: function () {
				location.href = failPage;
			}
		});
	}
} )( jQuery, mediaWiki );
