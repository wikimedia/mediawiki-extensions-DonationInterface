<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Special\Donate;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use Psr\Log\NullLogger;
use SmashPig\Core\Context;
use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group ComboWiki
 * @covers \DonateGatewayResult
 */
class DonateGatewayResultTest extends BaseGravyTestCase {

	/** Set by these tests, so they don't depend on the local thank you page config */
	private const THANK_YOU_PAGE = 'https://thankyou.example.org/wiki/Thank_You';

	protected function setUp(): void {
		parent::setUp();
		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->createMock( CardPaymentProvider::class )
		);
	}

	/**
	 * Donations that send the donor off to the processor are only finalised
	 * when they come back to the result page, so that leg needs the tag too.
	 */
	public function testTagsQueueMessagesAsComboWiki(): void {
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( [ 'gateway' => 'gravy' ], false ) );
		$context->setTitle( Title::newFromText( 'Special:DonateGatewayResult' ) );

		( new DonateGatewayResult() )->run( null );

		$this->assertSame( Donate::IDENTIFIER, Context::get()->getSourceType() );
	}

	public function testSendsDonorBackToDonateToOfferMonthlyConvert(): void {
		$redirect = $this->renderResultPageResponse( PaymentResult::newSuccess(), $this->getDonorBackup() );

		$this->assertStringContainsString( 'Special:Donate', $redirect );
		$this->assertStringContainsString( 'monthlyConvert=1', $redirect );
		$this->assertStringContainsString( 'order_id=123.1', $redirect );
		$this->assertStringContainsString( 'gateway=gravy', $redirect );
		$this->assertStringContainsString( 'uselang=en', $redirect );
		$this->assertStringContainsString( 'country=US', $redirect );
	}

	public function testSendsDonorBackToDonateWithErrorWhenPaymentFails(): void {
		$redirect = $this->renderResultPageResponse( PaymentResult::newFailure(), $this->getDonorBackup() );

		$this->assertStringContainsString( 'Special:Donate', $redirect );
		$this->assertStringContainsString( 'paymentFailed=1', $redirect );
		$this->assertStringNotContainsString( 'monthlyConvert', $redirect );
	}

	/**
	 * A failed token check on the return calls displayFailPage() directly,
	 * without a payment result, so it has to send the donor back too.
	 */
	public function testSendsDonorBackToDonateInsteadOfShowingLegacyErrorForm(): void {
		RequestContext::getMain()->setTitle( Title::newFromText( 'Special:DonateGatewayResult' ) );
		$resultPage = TestingAccessWrapper::newFromObject( new DonateGatewayResult() );
		$resultPage->adapter = new GravyAdapter( [ 'external_data' => $this->getDonorBackup() ] );
		$resultPage->logger = new NullLogger();

		$resultPage->displayFailPage();
		$redirect = $resultPage->getOutput()->getRedirect();

		$this->assertStringContainsString( 'Special:Donate', $redirect );
		$this->assertStringContainsString( 'paymentFailed=1', $redirect );
	}

	public function testSendsDonorToThankYouPageWhenDonationDoesNotQualifyForMonthlyConvert(): void {
		$redirect = $this->renderResultPageResponse(
			PaymentResult::newSuccess(),
			$this->getDonorBackup( [ 'amount' => '2.00' ] )
		);

		$this->assertStringStartsWith( self::THANK_YOU_PAGE . '/en', $redirect );
	}

	public function testSendsDonorOnWhenPaymentNeedsAnotherRedirect(): void {
		$redirect = $this->renderResultPageResponse(
			PaymentResult::newRedirect( 'https://example.org/next-step' ),
			$this->getDonorBackup()
		);

		$this->assertSame( 'https://example.org/next-step', $redirect );
	}

	/**
	 * The donor details the adapter backs up to Donor_BKUP after a one-time card donation
	 */
	private function getDonorBackup( array $overrides = [] ): array {
		return $overrides + [
			'amount' => '10.00',
			'contribution_tracking_id' => '123',
			'country' => 'US',
			'currency' => 'USD',
			'gateway' => 'gravy',
			'language' => 'en',
			'order_id' => '123.1',
			'payment_method' => 'cc',
			'recurring' => '',
		];
	}

	/**
	 * Call DonateGatewayResult::renderResponse() for a donation and return where it redirects
	 */
	private function renderResultPageResponse( PaymentResult $result, array $donorData ): string {
		$this->overrideConfigValues( [
			'DonationInterfaceMonthlyConvertCountries' => [ 'US' ],
			'DonationInterfaceThankYouPage' => self::THANK_YOU_PAGE,
		] );
		RequestContext::getMain()->setTitle( Title::newFromText( 'Special:DonateGatewayResult' ) );

		$resultPage = TestingAccessWrapper::newFromObject( new DonateGatewayResult() );
		$resultPage->adapter = new GravyAdapter( [ 'external_data' => $donorData ] );
		$resultPage->logger = new NullLogger();
		$resultPage->renderResponse( $result );

		return $resultPage->getOutput()->getRedirect();
	}
}
