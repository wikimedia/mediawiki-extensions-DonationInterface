const scriptPromises = {};

/**
 * Removes a script added by loadScript so a later call injects it again.
 *
 * @param {string} src
 */
function unloadScript( src ) {
	delete scriptPromises[ src ];
	Array.prototype.forEach.call(
		document.body.querySelectorAll( 'script[src="' + src + '"]' ),
		( node ) => node.remove()
	);
}

/**
 * Injects a third-party script once and resolves when it has loaded.
 * Repeated calls for the same src share the same promise.
 *
 * @param {string} src
 * @return {Promise}
 */
function loadScript( src ) {
	if ( !scriptPromises[ src ] ) {
		scriptPromises[ src ] = new Promise( ( resolve, reject ) => {
			const node = document.createElement( 'script' );
			node.src = src;
			node.onload = resolve;
			node.onerror = ( e ) => {
				// Allow a retry after a network failure
				unloadScript( src );
				reject( e );
			};
			document.body.append( node );
		} );
	}
	return scriptPromises[ src ];
}

/**
 * Overrides what a payment method submits with what the donor entered in the details form.
 *
 * A wallet (Apple Pay, Google Pay) submits the donor's billing address too. Where the donor
 * also entered a field, their entry wins: e.g. the state code picked from the list, where
 * Apple Pay may send free text such as "New South Wales". A field the donor left empty keeps
 * the payment method's value.
 *
 * @param {Object} paymentMethodData What the payment method submits, e.g. a wallet's token and address
 * @param {Object} formEntries What the donor entered in the details form, keyed by server field name
 * @return {Object} paymentMethodData, with each non-empty form entry in place of its value
 */
function overrideWithFormEntries( paymentMethodData, formEntries ) {
	const result = Object.assign( {}, paymentMethodData );
	for ( const [ fieldName, enteredValue ] of Object.entries( formEntries ) ) {
		if ( enteredValue !== undefined && enteredValue !== null && enteredValue !== '' ) {
			result[ fieldName ] = enteredValue;
		}
	}
	return result;
}

/**
 * Given an amount in major currency units, e.g. dollars, returns the
 * amount in minor units for the currency, e.g. cents. For non-fractional
 * currencies just rounds the amount to the nearest whole number.
 *
 * @param {number} amount
 * @param {string} currency
 * @return {number} amount in minor units for specified currency
 */
function amountInMinorUnits( amount, currency ) {
	const threeDecimals = mw.config.get( 'DonationInterfaceThreeDecimalCurrencies' ),
		noDecimals = mw.config.get( 'DonationInterfaceNoDecimalCurrencies' );

	if ( noDecimals.includes( currency ) ) {
		return Math.round( amount );
	}
	if ( threeDecimals.includes( currency ) ) {
		return Math.round( amount * 1000 );
	}
	return Math.round( amount * 100 );
}

// Kept for the page's lifetime so every Adyen component on the page shares one checkout
let adyenCheckoutPromise = null;

/**
 * The locale and translations for Adyen's components. These are set on the checkout,
 * not on each component, so every Adyen component on the page shares them.
 *
 * @param {string} localeFromServer e.g. 'en-US'
 * @return {Object} With locale and translations, for AdyenCheckout
 */
function getAdyenLocaleAndTranslations( localeFromServer ) {
	const adyenSupportedLocale = [
			'zh-CN', 'zh-TW', 'hr-HR',
			'cs-CZ', 'da-DK', 'nl-NL',
			'en-US', 'fi-FI', 'fr-FR',
			'de-DE', 'el-GR', 'hu-HU', 'it-IT',
			'ja-JP', 'ko-KR', 'no-NO', 'pl-PL',
			'pt-BR', 'ro-RO', 'ru-RU', 'sk-SK',
			'sl-SL', 'es-ES', 'sv-SE'
		],
		baseLocaleFromServer = localeFromServer.slice( 0, 2 ),
		// We support Norwegian Bokmal (nb) but Adyen's components just support the generic 'no' Norwegian code
		locale = baseLocaleFromServer === 'nb' ? 'no-NO' : localeFromServer,
		translations = {};

	// Check if donor's language is unsupported by Adyen and we need to provide our own customized translation
	// Adyen supports ar as Arabic - International and doesn't check the country part
	if ( baseLocaleFromServer !== 'ar' && !adyenSupportedLocale.includes( locale ) ) {
		translations[ locale ] = {
			//title
			'creditCard.cardNumber.label': mw.msg( 'donate_interface-credit-card-number' ),
			'creditCard.expiryDate.label': mw.msg( 'donate_interface-credit-card-expiration' ),
			'creditCard.securityCode.label': mw.msg( 'donate_interface-cvv' ),
			//Adyen no longer uses placeholders in v6, they use contextualText instead
			'creditCard.expiryDate.contextualText': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
			'creditCard.securityCode.contextualText.3digits': mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
			'creditCard.securityCode.contextualText.4digits': mw.msg( 'donate_interface-cvv-placeholder-4-digits' ),
			//error
			'creditCard.numberField.invalid': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
			'creditCard.expiryDateField.invalid': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
			'error.va.gen.01': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
			'error.va.gen.02': mw.msg( 'donate_interface-error-msg-field-not-valid' ),
			'error.va.sf-cc-num.01': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
			'error.va.sf-cc-num.02': mw.msg( 'donate_interface-error-msg-card-number-do-not-match-card-brand' ),
			'error.va.sf-cc-num.03': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
			'error.va.sf-cc-dat.01': mw.msg( 'donate_interface-error-msg-card-too-old' ),
			'error.va.sf-cc-dat.02': mw.msg( 'donate_interface-error-msg-date-too-far-in-the-future' ),
			// Adyen v6 error codes
			// https://github.com/Adyen/adyen-web/blob/a05c1184195fa73ce7dc9e956fa080cb40638209/packages/server/translations/en-US.json
			'cc.num.900': mw.msg( 'donate_interface-error-msg-card-num' ),
			'cc.num.901': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
			'cc.num.902': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
			'cc.num.903': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
			'cc.num.904': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
			'cc.dat.910': mw.msg( 'donate_interface-error-msg-expiration' ),
			'cc.dat.911': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
			'cc.dat.912': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
			'cc.dat.913': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
			'cc.cvc.920': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
			'cc.cvc.921': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
			'cc.cvc.920.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
			'cc.cvc.921.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
			'err.gen.9100': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
			'err.gen.9101': mw.msg( 'donate_interface-error-msg-field-not-valid' )
		};
	} else if ( locale === 'nl-NL' ) {
		translations[ locale ] = {
			'idealIssuer.selectField.placeholder': mw.msg( 'donate_interface-rtbt-issuer_id' )
		};
	} else if ( baseLocaleFromServer === 'ja' ) {
		translations[ locale ] = {
			'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
			'creditCard.cvcField.placeholder.3digits': '',
			'creditCard.cvcField.placeholder.4digits': ''
		};
	} else {
		translations[ locale ] = {};
	}
	if ( mw.config.get( 'payment_submethod' ) === 'ach' ) {
		translations[ locale ] = {
			'error.va.sf-ach-loc.02': mw.msg( 'donate_interface-ach-invalid-routing-number-count' ),
			'error.va.sf-ach-num.02': mw.msg( 'donate_interface-ach-invalid-account-number-count' )
		};
	}

	// if sepa, update holder name to account holder name
	if ( mw.config.get( 'payment_submethod' ) === 'sepadirectdebit' ) {
		translations[ locale ][ 'sepa.ownerName' ] = mw.msg( 'donate_interface-bt-account_holder' );
	}
	return { locale, translations };
}

/**
 * The checkout holds only what every Adyen component on the page shares. Each
 * component sets its own callbacks (onSubmit, onChange, onError...) when created.
 *
 * @param {string} country The donation's country
 * @return {Promise<Object>} The Adyen checkout for the page, once Adyen's SDK has loaded
 */
function getAdyenCheckout( country ) {
	const adyenConfiguration = mw.config.get( 'adyenConfiguration' );
	if ( !adyenCheckoutPromise ) {
		adyenCheckoutPromise = loadScript( adyenConfiguration.script.src )
			.then( () => window.AdyenWeb.AdyenCheckout( Object.assign(
				{
					clientKey: adyenConfiguration.clientKey,
					environment: adyenConfiguration.environment,
					paymentMethodsResponse: adyenConfiguration.paymentMethodsResponse,
					countryCode: country
				},
				getAdyenLocaleAndTranslations( adyenConfiguration.locale )
			) ) )
			.catch( ( e ) => {
				// Not kept, so a later call can try again
				adyenCheckoutPromise = null;
				throw e;
			} );
	}
	return adyenCheckoutPromise;
}

/**
 * The Adyen ApplePay component config, without the callbacks. The readiness check
 * and AdyenApplePayComponent both build the component from it, so they can't disagree.
 *
 * @param {Object} donation
 * @return {Object}
 */
function getAdyenApplePayConfiguration( donation ) {
	const adyenConfiguration = mw.config.get( 'adyenConfiguration' );
	return {
		countryCode: donation.country,
		buttonLocale: adyenConfiguration.locale,
		buttonType: 'donate',
		showPayButton: true,
		requiredBillingContactFields: adyenConfiguration.appleRequiredBillingContactFields,
		requiredShippingContactFields: adyenConfiguration.appleRequiredShippingContactFields
	};
}

/**
 * Handles an error reported by an Adyen component, as the legacy Adyen form does (adyen.js onError).
 *
 * @param {Object|string} error From Adyen's onError, or our own error code
 * @param {Function} emitErrorCallback Called with the error when the donor needs to see it
 * @param {string} otherWaysUrl For the general error message
 */
function handleAdyenError( error, emitErrorCallback, otherWaysUrl ) {
	// Ignore blank errors - that means a previous error was cleared up
	if ( !error || ( typeof error.error === 'string' && error.error === '' ) ) {
		return;
	}
	// Send the donor to the cancel page on donor-initiated cancellations
	if ( error.cause && error.cause.statusCode === 'CANCELED' ) {
		location.assign( mw.config.get( 'DonationInterfaceCancelUrl' ) );
		return;
	}
	// The donor closed the Apple Pay sheet. Adyen reports that as a CANCEL error
	if ( error.name === 'CANCEL' ) {
		return;
	}
	if ( typeof error.error === 'string' && error.error.slice( 0, 8 ) === 'error.va' ) {
		// The card fields show their own validation errors.
		// T349600 Log validation error codes only if sf-cc-num.02 donate_interface-error-msg-card-number-do-not-match-card-brand)
		// with date time and time zone for adyen to investigate their card validation js issue
		if ( error.error === 'error.va.sf-cc-num.02' ) {
			error.error = 'Adyen error: ' + error.error + ' on ' + new Date().toString();
			emitErrorCallback( error );
		}
		return;
	}
	// handle component error
	emitErrorCallback( {
		type: 'validation',
		messages: [ mw.message( 'donate_interface-error-msg-general', otherWaysUrl ).plain() ]
	} );
}

/**
 * Makes the link in a message's HTML open in a new tab.
 * The <a> tags come from the message itself, so the template can't set target on them.
 * Other links, such as mailto:, are left alone.
 *
 * @param {string} html A message as returned by .text() or .parse()
 * @return {string} The same HTML, each http(s) link with target="_blank" and rel="noopener noreferrer"
 */
function openLinksInNewTab( html ) {
	const template = document.createElement( 'template' );
	template.innerHTML = html;
	Array.prototype.forEach.call(
		template.content.querySelectorAll( 'a[href^="http"]' ),
		( link ) => {
			link.setAttribute( 'target', '_blank' );
			link.setAttribute( 'rel', 'noopener noreferrer' );
		}
	);
	return template.innerHTML;
}

module.exports = {
	handleAdyenError,
	openLinksInNewTab,
	loadScript,
	unloadScript,
	overrideWithFormEntries,
	amountInMinorUnits,
	getAdyenCheckout,
	getAdyenApplePayConfiguration
};
