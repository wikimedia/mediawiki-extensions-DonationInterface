<?php
/**
 * In this class we implement all the logic we need to make customizations
 * to the forms based on whether a donation is bound for the endowment.
 */
class EndowmentHooks {
	public static function onAlterPaymentFormData( &$data ) {
		if ( isset( $data['utm_medium'] ) && $data['utm_medium'] === 'endowment' ) {
			$data['faq_url'] = 'https://donate.wikimedia.org/wiki/FAQ#What_is_the_Wikimedia_Endowment?';
			$data['otherways_url'] = 'https://upload.wikimedia.org/wikipedia/donate/0/08/Wikimedia_Endowment_2020_Ways_to_Give.pdf';
		}
	}
}
