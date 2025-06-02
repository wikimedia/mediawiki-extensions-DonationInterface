( ( $ ) => {
	const $annualFundTabHeader = $( '#donorportal-tab-annual-fund' ),
		$annualFundTabContent = $( '#donorportal-tabcontent-annual-fund' ),
		$endowmentTabHeader = $( '#donorportal-tab-endowment' ),
		$endowmentTabContent = $( '#donorportal-tabcontent-endowment' );
	$( () => {
		$annualFundTabHeader.on( 'click', () => {
			$annualFundTabHeader.addClass( 'tab-active' );
			$annualFundTabContent.show();
			$endowmentTabHeader.removeClass( 'tab-active' );
			$endowmentTabContent.hide();
		} );
		$endowmentTabHeader.on( 'click', () => {
			$endowmentTabHeader.addClass( 'tab-active' );
			$endowmentTabContent.show();
			$annualFundTabHeader.removeClass( 'tab-active' );
			$annualFundTabContent.hide();
		} );
	} );
} )( jQuery );
