// Fee calculation ported from calculateFee() in the donate wiki's MediaWiki:DonationForm.js,
// so both forms suggest the same amount. Minimums are about 0.35 USD in each currency.
const FEE_MULTIPLIER = 0.04;
const DEFAULT_FEE_MINIMUM = 0.35;
const FEE_MINIMUMS = {
	DKK: 2,
	HUF: 100,
	ILS: 1.2,
	INR: 4,
	JPY: 35,
	KHR: 1000,
	MYR: 1,
	NOK: 3,
	PLN: 1.35,
	CZK: 7.5,
	RON: 1.5,
	SEK: 3,
	UAH: 10,
	ZAR: 5,
	BRL: 2,
	ARS: 415,
	CLP: 325,
	COP: 1450,
	MXN: 6.75,
	PEN: 1.28,
	UYU: 14.5
};
const BASE_USD_PRESETS = [ 2.75, 5, 10, 20, 30, 50, 100 ];

/**
 * @param {number|string|null} amount The donation amount, which may be typed into the other amount field
 * @param {string} currency
 * @param {Object} params The page params, for the currencies without cents
 * @return {number|null} The fee to suggest, or null until there is an amount
 */
function getSuggestedFee( amount, currency, params ) {
	const numericAmount = Number( amount );
	if ( !( numericAmount > 0 ) ) {
		return null;
	}

	const minimum = FEE_MINIMUMS[ currency ] || DEFAULT_FEE_MINIMUM;
	const fee = Math.max( numericAmount * FEE_MULTIPLIER, minimum );
	// Currencies with no minor unit, from SmashPig's CurrencyRoundingHelper.
	const noDecimalCurrencies = params.DonationInterfaceNoDecimalCurrencies || [];
	if ( noDecimalCurrencies.includes( currency ) ) {
		return Math.round( fee );
	}
	return Math.round( fee * 100 ) / 100;
}

/**
 * Converts an amount from the rule's currency into the donation currency, unrounded,
 * like Amount.php and the mustache forms' validate_input.js.
 *
 * @param {number} value An amount in the rule's currency
 * @param {Object} rules The amount rules, with the currency of their min and max
 * @param {string} currency The donation currency
 * @param {Object} params The page params, for the currency rates
 * @return {number}
 */
function convertRuleAmount( value, rules, currency, params ) {
	const rates = params.wgDonationInterfaceCurrencyRates || {};
	if ( currency === rules.currency || !rates[ currency ] ) {
		return value;
	}
	return ( value / rates[ rules.currency ] ) * rates[ currency ];
}

/**
 * The minimum the server accepts, unrounded, so 1 USD is 0.8677 EUR and 0.87 EUR passes.
 *
 * @param {string} currency
 * @param {Object} params The page params, for the amount rules and currency rates
 * @return {number} The minimum donation in the currency, or 0 when there is no rule
 */
function getMinAmount( currency, params ) {
	const rules = params.wgDonationInterfaceAmountRules;
	if ( !rules || !rules.min ) {
		return 0;
	}
	return convertRuleAmount( rules.min, rules, currency, params );
}

/**
 * @param {string} currency
 * @param {Object} params The page params, for the amount rules and currency rates
 * @return {number} The maximum donation in the currency, unrounded
 */
function getMaxAmount( currency, params ) {
	const rules = params.wgDonationInterfaceAmountRules;
	if ( !rules || !rules.max ) {
		return Infinity;
	}
	return convertRuleAmount( rules.max, rules, currency, params );
}

/**
 * Rounds a limit to an amount the donor can type in the currency: the minimum up and the
 * maximum down, so the rounded limits pass the same checks as the unrounded ones.
 *
 * @param {number} amount
 * @param {string} currency
 * @param {Object} params The page params, for the currencies without cents
 * @param {Function} roundingFunction Math.ceil for a minimum, Math.floor for a maximum
 * @return {number|undefined} undefined for no limit, so the input gets no attribute
 */
function roundLimitToCurrency( amount, currency, params, roundingFunction ) {
	if ( !isFinite( amount ) ) {
		return undefined;
	}
	const noDecimalCurrencies = params.DonationInterfaceNoDecimalCurrencies || [];
	const factor = noDecimalCurrencies.includes( currency ) ? 1 : 100;
	// toFixed first, so 0.29 * 100 = 28.999999999999996 isn't floored to 0.28
	return roundingFunction( Number( ( amount * factor ).toFixed( 6 ) ) ) / factor;
}

/**
 * Like the donate wiki, the fee isn't offered when it would push the donation over the maximum.
 *
 * @param {number|string|null} amount
 * @param {string} currency
 * @param {Object} params
 * @return {boolean}
 */
function canCoverFee( amount, currency, params ) {
	const fee = getSuggestedFee( amount, currency, params );
	return !!fee && Number( amount ) + fee <= getMaxAmount( currency, params );
}

/**
 * @param {Object} donation With amount, currency and payFee
 * @param {Object} params
 * @return {number} The fee to charge: 0 unless the donor opted in and the fee can be covered
 */
function getFeeAmount( donation, params ) {
	if ( !donation.payFee || !canCoverFee( donation.amount, donation.currency, params ) ) {
		return 0;
	}
	return getSuggestedFee( donation.amount, donation.currency, params );
}

function getFormattedAmount( amount, language, currency, country = 'US' ) {
	if ( !amount ) {
		return '';
	}

	const lang = ( language || 'en' ).split( '-' )[ 0 ];
	const locale = `${ lang }_${ country }`;

	try {
		return new Intl.NumberFormat( locale.replace( '_', '-' ), {
			style: 'currency',
			currency
		} ).format( amount );
	} catch ( e ) {
		return `${ currency }${ amount }`;
	}
}

function getPresetAmounts( currency, rates ) {
	if ( !currency || currency === 'USD' || !rates[ currency ] ) {
		return BASE_USD_PRESETS;
	}

	const rate = rates[ currency ];

	return BASE_USD_PRESETS.map( ( usdAmount ) => {
		const converted = usdAmount * rate;
		// Ensure the converted amount meets or exceeds 1 USD equivalent in value
		const minAmount = rates[ currency ];

		if ( converted < minAmount ) {
			return Math.ceil( minAmount );
		}

		// Round clean values based on scale (e.g. round to nearest integer or 5)
		if ( converted > 100 ) {
			return Math.ceil( converted / 5 ) * 5;
		}
		return Math.ceil( converted );
	} );
}
module.exports = {
	getSuggestedFee,
	getMinAmount,
	getMaxAmount,
	roundLimitToCurrency,
	canCoverFee,
	getFeeAmount,
	getFormattedAmount,
	getPresetAmounts
};
