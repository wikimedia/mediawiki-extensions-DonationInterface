/**
 * Basic utility function for loading the payment form from GlobalCollect
 * into the DOM. Overridden by the test environment's interface.
 */

( function ( mw, $ ) {
	/**
	 * Overrides base, defined in gc.interface.js
	 */
	mw.generatePaymentForm = function ( data ) {
		var $form, $succeedBtn, $failBtn, returnUrl;

		// This is a test form environment, just dump in some dummy HTML

		$form = $( '<div>' ).attr( {
			id: 'globalcollectframe',
			name: 'globalcollectframe',
			width: '680',
			height: '300'
		} );

		$succeedBtn = $( '<button>' ).attr( {
			id: 'globalcollect_gateway-fakesucceed',
			type: 'button'
		} ).text( mw.msg( 'globalcollect_gateway-fakesucceed' ) );

		$failBtn = $( '<button>' ).attr( {
			id: 'globalcollect_gateway-fakefail',
			type: 'button'
		} ).text( mw.msg( 'globalcollect_gateway-fakefail' ) );

		returnUrl = new mw.Uri( data.result.returnurl );
		returnUrl.extend( {
			fake: true
		} );

		$succeedBtn.click( function () {
			window.location = returnUrl.toString();
		} );

		$failBtn.click( function () {
			returnUrl.extend( {
				fail: true
			} );
			window.location = returnUrl.toString();
		} );

		$form.append( $succeedBtn );
		$form.append( $failBtn );

		this.loadPaymentForm( $form );
	};
}( mediaWiki, jQuery ) );
