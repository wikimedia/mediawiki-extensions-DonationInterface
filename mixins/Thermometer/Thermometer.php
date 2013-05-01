<?php //namespace CentralNotice\Mixins;

class Thermometer implements IBannerMixin {
	protected $context = null;

	function register( MixinController $controller ) {
		$this->context = $controller->getContext();

		$controller->registerMagicWord( 'cumulative-amount', array( $this, "getDonationAmount" ) );
		$controller->registerMagicWord( 'daily-amount', array( $this, "getDailyDonationAmount" ) );
	}

	/**
	 * Pull the current amount raised during a fundraiser
	 * @throws SpecialBannerLoaderException
	 */
	function getDonationAmount( $arg1 ) {
		return "fake-out!: $arg1";

		global $wgNoticeCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( wfMemcKey( 'centralnotice', 'counter' ) ) );
		if ( !$count ) {
			// Pull from dynamic counter -- WHAT
			$counter_value = Http::get( $wgNoticeCounterSource );
			if( !$counter_value ) {
				throw new RemoteServerProblemException();
			}
			$count = intval( $counter_value );
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get(
					wfMemcKey( 'centralnotice', 'counter', 'fallback' ) ) );
				if ( !$count ) {
					throw new DonationAmountUnknownException();
				}
			}
			// Expire in 60 seconds
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter' ), $count, 60 );
			// No expiration
			$wgMemc->set( wfMemcKey( 'centralnotice', 'counter', 'fallback' ), $count );
		}

		$num = $this->toMillions( $count );
		return "{{{amount|$num}}}";
	}

	/**
	 * Pull the amount raised so far today during a fundraiser
	 * @throws SpecialBannerLoaderException
	 */
	function getDailyDonationAmount() {
		return "fake-out!";

		global $wgNoticeDailyCounterSource, $wgMemc;
		// Pull short-cached amount
		$count = intval( $wgMemc->get( wfMemcKey( 'centralnotice', 'dailycounter' ) ) );
		if ( !$count ) {
			// Pull from dynamic counter
			$counter_value = Http::get( $wgNoticeDailyCounterSource );
			if( !$counter_value ) {
				throw new RemoteServerProblemException();
			}
			$count = intval( $counter_value );
			if ( !$count ) {
				// Pull long-cached amount
				$count = intval( $wgMemc->get(
					wfMemcKey( 'centralnotice', 'dailycounter', 'fallback' ) ) );
				if ( !$count ) {
					throw new DonationAmountUnknownException();
				}
			}
			// Expire in 60 seconds
			$wgMemc->set( wfMemcKey( 'centralnotice', 'dailycounter' ), $count, 60 );
			// No expiration
			$wgMemc->set( wfMemcKey( 'centralnotice', 'dailycounter', 'fallback' ), $count );
		}

		$num = $this->toThousands( $count );
		return "{{{daily-amount|$num}}}";
	}

	/**
	 * Convert number of dollars to millions of dollars
	 */
	protected function toMillions( $num ) {
		$num = sprintf( "%.1f", $num / 1e6 );
		if ( substr( $num, - 2 ) == '.0' ) {
			$num = substr( $num, 0, - 2 );
		}
		return $this->context->getLanguage()->formatNum( $num );
	}

	/**
	 * Convert number of dollars to thousands of dollars
	 */
	protected function toThousands( $num ) {
		$num = sprintf( "%d", $num / 1000 );
		return $this->context->getLanguage()->formatNum( $num );
	}
}

class RemoteServerProblemException extends BannerLoaderException {
}

class DonationAmountUnknownException extends BannerLoaderException {
}
