const apiPostAction = function ( recurringContributionRecord, params, actionName ) {
	const api = new mw.Api();
	params.action = actionName;
	recurringContributionRecord.is_processing = true;

	return api.post( params );
};

module.exports = { apiPostAction };
