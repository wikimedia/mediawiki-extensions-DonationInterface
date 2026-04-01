const apiPostAction = function ( recurringContributionRecord, params, actionName ) {
	const api = new mw.Api();
	params.action = actionName;
	recurringContributionRecord.is_processing = true;

	return api.post( params );
};

/**
 * Wrapping this in a function to enable i18n in the tests
 *
 * @param {*} i18n
 * @return
 */
const errorMessageMapFunction = ( i18n ) => ( {
	'no-session': i18n( 'donorportal-error-no-session' ).text(),
	'bad-contact-id': i18n( 'donorportal-error-bad-contact-id', mw.config.get( 'help_email' ) ).text(),
	'bad-contribution-recur-id': i18n( 'donorportal-error-bad-contribution-recur-id', mw.config.get( 'help_email' ) ).text()
} );

module.exports = { apiPostAction, errorMessageMapFunction };
