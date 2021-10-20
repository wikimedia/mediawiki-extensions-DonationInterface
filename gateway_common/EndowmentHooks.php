<?php
/**
 * In this class we implement all the logic we need to make customizations
 * to the forms based on whether a donation is bound for the endowment.
 */
class EndowmentHooks {
	const ENDOW_SESSION_KEY = 'isEndowmentDonation';

	public static function onAlterPaymentFormData( &$data ) {
		if ( isset( $data['utm_medium'] ) && $data['utm_medium'] === 'endowment' ) {
			$data['faq_url'] = 'https://donate.wikimedia.org/wiki/FAQ#What_is_the_Wikimedia_Endowment?';
			$data['otherways_url'] = 'https://wikimediaendowment.org/ways-to-give/';
		}
	}

	public static function onBeforePageDisplay( $outputPage, $skin ) {
		if ( self::isEndowment() ) {
			// FIXME: need to keep generated CSS in sync with
			// @see ResourceLoaderSkinModule::getStyles
			// html body is prepended to give us more-specific selectors
			$css = <<<EOT
html body .mw-wiki-logo {
  background-image: url(/images/4/49/Wikimedia_Endowment.png);
}
@media (-webkit-min-device-pixel-ratio: 1.5),
       (min--moz-device-pixel-ratio: 1.5),
       (min-resolution: 1.5dppx),
       (min-resolution: 144dpi) {
  html body .mw-wiki-logo {
    background-image: url(/images/4/49/Wikimedia_Endowment_1.5x.png);
    background-size: 135px auto;
  }
}
@media (-webkit-min-device-pixel-ratio: 2),
       (min--moz-device-pixel-ratio: 2),
       (min-resolution: 2dppx),
       (min-resolution: 192dpi) {
  html body .mw-wiki-logo {
    background-image: url(/images/8/85/Wikimedia_Endowment_2x.png);
    background-size: 135px auto;
  }
}
EOT;
			$outputPage->getOutput()->addInlineStyle( $css );
		}
	}

	protected static function isEndowment() {
		$request = RequestContext::getMain()->getRequest();
		$sessionIsEndowment = $request->getSessionData( self::ENDOW_SESSION_KEY );
		if ( !isset( $sessionIsEndowment ) ) {
			$sessionIsEndowment = ( $request->getVal( 'utm_medium' ) === 'endowment' );
			$request->setSessionData( self::ENDOW_SESSION_KEY, $sessionIsEndowment );
		}
		return $sessionIsEndowment;
	}
}
