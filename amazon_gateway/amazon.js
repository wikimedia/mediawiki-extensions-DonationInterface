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
		accessToken;

	// Cribbed from Amazon documentation
	function getURLParameter( name, source ) {
		return decodeURIComponent( (
			new RegExp( '[?|&|#]' + name + '=' + '([^&;]+?)(&|#|;|$)' ).exec( source )
			|| [,""]
		)[1].replace( /\+/g, '%20' ) ) || null;
	}

	function loadScript( url ) {
		var a = document.createElement('script');
		a.type = 'text/javascript';
		a.async = true;
		a.src = url;
		document.head.appendChild(a);
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
		if ( !loggedIn ) {
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

	if ( typeof accessToken === 'string' && accessToken.match( /^Atza/ ) ) {
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
} )( jQuery, mediaWiki );
