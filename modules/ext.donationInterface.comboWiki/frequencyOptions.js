/**
 * Contains all the variants of one time, monthly , and annual/yearly
 *
 * donate_interface-onetime-short: One-time
 * combowiki-frequency-once: Once
 */
const FREQUENCIES = {
	once: {
		label: 'combowiki-frequency-once',
		amountHeading: 'combowiki-amount-heading-once'
	},
	month: {
		label: 'combowiki-frequency-monthly',
		amountHeading: 'combowiki-amount-heading-monthly'
	},
	year: {
		label: 'combowiki-frequency-annual',
		amountHeading: 'combowiki-amount-heading-annual'
	}
};

/**
 * Returns message for frequency
 *
 * @param {string} frequency
 * @param {string} type
 * @return {string}
 */
function getFrequencyMessage( frequency, type ) {
	const messages = FREQUENCIES[ frequency ];
	return messages ? mw.msg( messages[ type ] ) : '';
}

module.exports = {
	/**
	 * Returns the full set of frequency options
	 *
	 * @return {Array<{value: string, label: string}>}
	 */
	getFrequencyOptions() {
		return Object.keys( FREQUENCIES ).map( ( value ) => ( {
			value,
			label: getFrequencyMessage( value, 'label' )
		} ) );
	},

	/**
	 * Returns the localized label for a frequency
	 *
	 * @param {string} frequency One of 'once', 'monthly' or 'annual'
	 * @return {string} Empty string if the frequency is unknown
	 */
	getFrequencyLabel( frequency ) {
		return getFrequencyMessage( frequency, 'label' );
	},

	/**
	 * Returns the localized amount heading for a frequency
	 *
	 * @param {string} frequency One of 'once', 'monthly' or 'annual'
	 * @return {string} Empty string if the frequency is unknown
	 */
	getAmountHeading( frequency ) {
		return getFrequencyMessage( frequency, 'amountHeading' );
	}
};
