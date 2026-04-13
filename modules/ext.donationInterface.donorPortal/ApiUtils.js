function apiPost( params ) {
	return ( new mw.Api() ).post( params );
}

const apiRecurringPostAction = function ( recurringContributionRecord, params, actionName ) {
	const donorData = mw.config.get( 'donorData' );
	let requestBody = {
		contact_id: Number( donorData.contact_id ),
		checksum: donorData.checksum,
		contribution_recur_id: Number( recurringContributionRecord.id ),
		action: actionName
	};
	requestBody = Object.assign( {}, requestBody, params );
	recurringContributionRecord.is_processing = true;
	return apiPost( requestBody );
};

function requestRecurringPause( recurringContributionRecord, params ) {
	return apiRecurringPostAction( recurringContributionRecord, params, 'requestPauseRecurring' );
}

function requestRecurringCancel( recurringContributionRecord, params ) {
	return apiRecurringPostAction( recurringContributionRecord, params, 'requestCancelRecurring' );
}

function requestRecurringUpdate( recurringContributionRecord, params ) {
	return apiRecurringPostAction( recurringContributionRecord, params, 'requestUpdateRecurring' );
}

function requestAnnualConversion( recurringContributionRecord, params ) {
	return apiRecurringPostAction( recurringContributionRecord, params, 'requestAnnualConversion' );
}

function requestNewChecksumLink( email, page, subpage ) {
	const params = {
		email: email,
		action: 'requestNewChecksumLink',
		page: page
	};
	if ( subpage ) {
		params.subpage = subpage;
	}
	return apiPost( params );
}

module.exports = {
	requestRecurringPause,
	requestRecurringCancel,
	requestRecurringUpdate,
	requestNewChecksumLink,
	requestAnnualConversion
};
