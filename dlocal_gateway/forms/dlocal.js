(function ($, mw) {
	$(function (){
		if ( $('#country').val() === 'IN' ) {
			$( '#fiscal_number' ).after(
				$( '<input type="hidden" value="Mumbai" name="city" id="city">' +
					'<p style="font-size: 10px">' + mw.msg( 'donate_interface-donor-fiscal_number-explain-in' ) +
					'</p>' )
			);
		}

		// todo:: another task to make sure script load first both dlocal, Adyen and amazon
		var dlocalInstance = dlocal(mw.config.get('wgDlocalSmartFieldApiKey'));

		var fields = dlocalInstance.fields({
			locale: mapLang($('#language').val()),
			country: $('#country').val()
		});

		// https://docs.dlocal.com/reference/the-dlocal-object#dlocalfieldsoptions
		// Supported values are: es, en, pt, zh, cv, tr.
		function mapLang(wikiLang) {
			if(wikiLang === 'es-419'){
				return 'es';
			} else if(['es', 'en', 'pt', 'zh', 'cv', 'tr'].includes(wikiLang)){
				return wikiLang;
			} else {
				 // todo: maybe display an error, or just default en?
				return 'en';
			}
		}

		// Custom styling can be passed to options when creating a Smart Field.
		var commonStyle = {
			base: {
				fontSize: "14px",
				fontFamily: "sans-serif",
				lineHeight: '40px',
				fontSmoothing: 'antialiased',
				fontWeight: '500',
				color: "rgb(0, 17, 44)",
				'::placeholder': {
					color: "rgb(185, 196, 201)"
				},
			},
			focus: {
				iconColor: "#adbfd3",
				'::placeholder': {
					color: "#adbfd3"
				}
			},
			autofilled: {
				color: "#000000"
			}
		};

		// create card field
		var card = fields.create('pan', {
			style: commonStyle,
			placeholder: "4111 1111 1111 1111"
		});

		var expiration = fields.create('expiration', {
			style: commonStyle,
			placeholder: mw.msg( 'donate_interface-expiry-date-field-placeholder' )
		});

		var cvv = fields.create('cvv', {
			style: commonStyle,
			placeholder: "123"
		});

		// todo: onchange display error if card/cvv/expiration date info error
		// Show our standard 'Donate' button
		$( '#paymentSubmit' ).show();
		// Set the click handler
		$( '#paymentSubmitBtn' ).click(
			function(event) {
				event.preventDefault();
				// todo: another task to handle address for other countries like IN
				dlocalInstance.createToken(card, {
					name: $('#first_name').val() + ' ' + $('#last_name').val()
				}).then(function(result) {
					// Send the token to your server.
					mw.donationInterface.forms.callDonateApi(
						handleApiResult, {payment_token: result.token}, 'di_donate_dlocal'
					);
				}).catch(function (result) {
					if (result.error) {
						// todo: Inform the customer that there was an error.
						console.log(result.error);
					}
				});
			}
		);

		function handleApiResult( result ) {
			if ( result.isFailed ) {
				mw.donationInterface.validation.showErrors( {
					general: mw.msg( 'donate_interface-error-msg-general' )
				} );
				return;
			} else {
				document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
			}
		}

		// Drop in the dlocal card components placeholder
		$( '.submethods' ).before(
			'<div>' +
			'<label for="cardNumber">' +mw.message( 'donate_interface-donor-card-num' ) +'</label>' +
			'<div id="cardNumber" ></div>' +
			'</div>' +
			'<div>' +
			'<div class="halfwidth">' +
			'<label for="expiration">' + mw.message( 'donate_interface-donor-expiration' ) + '</label>' +
			'<div id="expiration" ></div>' +
			'</div>' +
			'<div class="halfwidth">' +
			'<label for="cvv">' + mw.message( 'donate_interface-cvv' ) + '</label>' +
			'<div id="cvv"></div>' +
			'</div>' +
			'</div>'
		);

		card.mount(document.getElementById('cardNumber'));
		expiration.mount(document.getElementById('expiration'));
		cvv.mount(document.getElementById('cvv'));
	});
} )( jQuery, mediaWiki );
