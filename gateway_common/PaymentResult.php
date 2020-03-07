<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\FinalStatus;

/**
 * Contains donation workflow UI hints
 *
 * After each donation request or gateway response, the adapter produces
 * a PaymentResult which wraps one of the following:
 *
 *   - Success: Send donor to the Thank You page.
 *
 *   - Failure (unrecoverable): Send donor to the failure page.
 *
 *   - Refresh form: After validation or other recoverable errors, display the
 *     donation form again and give the donor a chance to correct any errors,
 *     usually with helpful notices.  This PaymentResult object will contain
 *     a map of field names to errors.
 *       If we're feel really feisty, we can make the form name dynamic, as
 *     well as other parameters to the view template--so one form may send the
 *     donor to a more appropriate form.
 *
 *   - Iframe: FIXME, this is almost a variation on refreshForm.
 *
 *   - Gateway redirect: Send donor to the gateway, usually with a ton of data
 *     in the URL's GET params.
 */
class PaymentResult {
	protected $iframe;
	protected $form;
	protected $redirect;
	protected $refresh = false;
	protected $errors = [];
	protected $formData = [];
	protected $failed = false;

	protected function __construct() {
	}

	public static function newIframe( $url, $formData = [] ) {
		$response = new PaymentResult();
		$response->iframe = $url;
		$response->formData = $formData;
		return $response;
	}

	public static function newForm( $name ) {
		$response = new PaymentResult();
		$response->form = $name;
		return $response;
	}

	public static function newRedirect( $url, $formData = [] ) {
		$response = new PaymentResult();
		$response->redirect = $url;
		$response->formData = $formData;
		return $response;
	}

	public static function newRefresh( $errors = [] ) {
		$response = new PaymentResult();
		$response->refresh = true;
		$response->errors = $errors;
		return $response;
	}

	public static function newSuccess() {
		$response = new PaymentResult();
		return $response;
	}

	public static function newFailure( $errors = [] ) {
		$response = new PaymentResult();
		$response->failed = true;
		$response->errors = $errors;
		return $response;
	}

	public static function newEmpty() {
		$response = new PaymentResult();
		$response->errors = [ new PaymentError(
			'internal-0000', 'Internal error: no results yet.', LogLevel::ERROR
		) ];
		$response->failed = true;
		return $response;
	}

	public function getIframe() {
		return $this->iframe;
	}

	public function getForm() {
		return $this->form;
	}

	public function getFormData() {
		return $this->formData;
	}

	public function getRedirect() {
		return $this->redirect;
	}

	public function getRefresh() {
		return $this->refresh;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function isFailed() {
		return $this->failed;
	}

	/**
	 * Build a PaymentResult object from adapter results
	 *
	 * @param PaymentTransactionResponse $response processed response object
	 * @param string $finalStatus final transaction status.
	 *
	 * @return PaymentResult
	 * TODO: rename to fromResponse
	 */
	public static function fromResults( PaymentTransactionResponse $response, $finalStatus ) {
		if ( $finalStatus === FinalStatus::FAILED
			|| $finalStatus === FinalStatus::CANCELLED ) {
			return self::newFailure( $response->getErrors() );
		}
		if ( !$response ) {
			return self::newEmpty();
		}
		if ( $response->getErrors() ) {
			// TODO: We will probably want the ability to refresh to a new form
			// and display errors at the same time.
			return self::newRefresh( $response->getErrors() );
		}
		if ( $response->getRedirect() ) {
			return self::newRedirect( $response->getRedirect() );
		}
		return self::newSuccess();
	}
}
