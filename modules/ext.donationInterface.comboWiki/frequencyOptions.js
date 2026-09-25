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
	monthly: {
		label: 'combowiki-frequency-monthly',
		amountHeading: 'combowiki-amount-heading-monthly'
	},
	annual: {
		label: 'combowiki-frequency-annual',
		amountHeading: 'combowiki-amount-heading-annual'
	}
};

function getFrequencyMessage( frequency, type ) {
	const messages = FREQUENCIES[ frequency ];
	return messages ? mw.msg( messages[ type ] ) : '';
}

module.exports = {
	/**
	 * Returns the full set of frequency options
	 */
	getFrequencyOptions() {
		return Object.keys( FREQUENCIES ).map( ( value ) => ( {
			value,
			label: getFrequencyMessage( value, 'label' )
		} ) );
	},
	getFrequencyLabel( frequency ) {
		return getFrequencyMessage( frequency, 'label' );
	},

	getAmountHeading( frequency ) {
		return getFrequencyMessage( frequency, 'amountHeading' );
	}
};
