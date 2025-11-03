const normalizeInput = {
	sanitize: ( raw ) => {
		if ( raw === '' || raw === null ) {
			return '';
		}
		// remove non-digits except dot
		let v = String( raw ).replace( /[^\d.]/g, '' );
		// keep only first dot
		const firstDot = v.indexOf( '.' );
		if ( firstDot !== -1 ) {
			v = v.slice( 0, firstDot + 1 ) + v.slice( firstDot + 1 ).replace( /\./g, '' );
		}
		// limit to 2 decimals but allow a trailing dot while typing (e.g. "1." or ".")
		if ( v.includes( '.' ) ) {
			const [ intPart, decPart ] = v.split( '.' );
			v = intPart + '.' + ( decPart ? decPart.slice( 0, 2 ) : '' );
		}
		// keep a leading dot (".5") and preserve single zero before dot ("0.5")
		// remove leading zeros only when followed by another digit (e.g. "00012" -> "12")
		if ( /^0+\d/.test( v ) ) {
			v = v.replace( /^0+/, '' );
		}
		// clamp to min/max only for a complete numeric value (not "." or trailing dot)
		const n = parseFloat( v );
		if ( !Number.isNaN( n ) && v !== '.' && !v.endsWith( '.' ) ) {
			// keep at most 2 decimals without forcing trailing zeros
			return String( Math.round( n * 100 ) / 100 );
		}
		return v;
	},
	getRecurringPriceRange: ( recurringContribution, currencyRateArray, max ) => {
		const getCurrencyRate = () => {
			const currency = recurringContribution.currency;
			if ( currency && currencyRateArray && typeof currencyRateArray === 'object' ) {
				return currencyRateArray[ currency ] || 1;
			}
		};

		let minAmount = Number( ( 1 * getCurrencyRate() ).toFixed( 2 ) );
		let maxAmount = Number( ( max * getCurrencyRate() ).toFixed( 2 ) );
		if ( typeof recurringContribution.donation_rules !== 'undefined' &&
			recurringContribution.donation_rules !== null ) {
			const amountRule = recurringContribution.donation_rules;
			// override min/max if specified in donation_rules for this currency
			if ( amountRule.currency === recurringContribution.currency ) {
				if ( amountRule.min ) {
					minAmount = amountRule.min;
				}
				if ( amountRule.max ) {
					maxAmount = amountRule.max;
				}
			} else {
				// Convert rules from their currency into the current currency
				minAmount = Number( ( amountRule.min * getCurrencyRate() ).toFixed( 2 ) );
				maxAmount = Number( ( amountRule.max * getCurrencyRate() ).toFixed( 2 ) );
			}
		}
		return [ minAmount, maxAmount ];
	}
};

module.exports = exports = normalizeInput;
