<?php

/**
 * Interface RecurringConversion Should be implemented by adapters where it is
 * possible to convert a one-time donation into a series of monthly donations.
 */
interface RecurringConversion {

	/**
	 * If we have just made a one-time donation that is possible to convert to
	 * recurring, do the conversion. The PaymentResult will be in error if there
	 * is no eligible donation in session.
	 *
	 * @return PaymentResult
	 */
	public function doRecurringConversion(): PaymentResult;

	/**
	 * Should return an array of payment methods that support setting up a recurring
	 * donation after a one-time donation via this gateway.
	 * @return array
	 */
	public function getPaymentMethodsSupportingRecurringConversion(): array;
}
