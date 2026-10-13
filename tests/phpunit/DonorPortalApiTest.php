<?php

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Session\Token;
use SmashPig\Core\DataStores\QueueWrapper;

/**
 * @group Database
 * @group DonationInterface
 * @covers \MediaWiki\Extension\DonationInterface\Api\ApiRecurringModifyBase
 * @covers \MediaWiki\Extension\DonationInterface\Api\ApiRequestLogout
 */
class DonorPortalApiTest extends DonationInterfaceApiTestCase {

	private const TOKEN_SECRET = 'donor-portal-test-secret';

	private function session( array $recurring = [] ): array {
		return [
			'wsTokenSecrets' => [ 'default' => self::TOKEN_SECRET ],
			DonorPortal::SESSION_KEY => [
				'id' => 123,
				'recurringContributions' => [
					$recurring + [ 'id' => 456, 'status' => 'In Progress', 'can_modify' => true ],
				],
			],
		];
	}

	private function token(): string {
		return ( new Token( self::TOKEN_SECRET, DonorPortal::SESSION_KEY ) )->toString();
	}

	private function updateRequest( array $changes = [] ): array {
		return $changes + [
			'action' => 'requestUpdateRecurring',
			'contact_id' => 123,
			'checksum' => 'abc',
			'contribution_recur_id' => 456,
			'amount' => '20',
			'txn_type' => 'recurring_upgrade',
			'donorportal_token' => $this->token(),
		];
	}

	private function anon() {
		return $this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' );
	}

	public function testUpdateWithToken(): void {
		$this->doApiRequest( $this->updateRequest(), $this->session(), false, $this->anon() );
		$message = QueueWrapper::getQueue( 'recurring-modify' )->pop();
		$this->assertSame( 'recurring_upgrade', $message['txn_type'] );
		$this->assertSame( '20', $message['amount'] );
	}

	public static function provideRejectedRequests(): array {
		return [
			'missing token' => [ [ 'donorportal_token' => '' ], [], 'missingparam' ],
			'token from another session' => [
				[ 'donorportal_token' => ( new Token( 'other', DonorPortal::SESSION_KEY ) )->toString() ],
				[],
				'bad-token',
			],
			'cancelled recurring' => [ [], [ 'status' => 'Cancelled' ], 'not-modifiable' ],
			'can_modify false' => [ [], [ 'can_modify' => false ], 'not-modifiable' ],
			'amount over maximum' => [ [ 'amount' => '9999' ], [], 'bad-amount' ],
			'zero amount' => [ [ 'amount' => '0' ], [], 'bad-amount' ],
			'other txn_type' => [ [ 'txn_type' => 'external_recurring_modification' ], [], 'badvalue' ],
		];
	}

	/**
	 * @dataProvider provideRejectedRequests
	 */
	public function testRejectedRequests( array $requestChanges, array $recurringChanges, string $errorCode ): void {
		// TODO: after 1.47 upgrade, use expectApiErrorCodeFromCallback, i.e.
		// $this->expectApiErrorCodeFromCallback( $errorCode, function () use ( $requestChanges, $recurringChanges ) {
		//     $this->doApiRequest( $this->updateRequest( $requestChanges ), $this->session( $recurringChanges ),
		//             false, $this->anon() );
		//     } );
		// $this->assertNull( QueueWrapper::getQueue( 'recurring-modify' )->pop() );
		try {
			$this->doApiRequest( $this->updateRequest( $requestChanges ), $this->session( $recurringChanges ),
				false, $this->anon() );
		} catch ( ApiUsageException $exception ) {
			$this->assertApiErrorCode( $errorCode, $exception, '', null );
			$this->assertNull( QueueWrapper::getQueue( 'recurring-modify' )->pop() );
			// rethrow, no further code in the test class can be executed
			$this->expectException( ApiUsageException::class );
			throw $exception;
		}
		$this->fail( 'Should have thrown an exception' );
	}

	public function testLogoutRemovesPortalSession(): void {
		$this->overrideConfigValue( 'DonationInterfaceCiviproxyURLBase', 'http://127.0.0.1:9' );
		[ , , $session ] = $this->doApiRequest( [
			'action' => 'requestLogout',
			'contact_id' => 123,
			'checksum' => 'abc',
			'donorportal_token' => $this->token(),
		], $this->session(), false, $this->anon() );
		$this->assertArrayNotHasKey( DonorPortal::SESSION_KEY, $session );
	}
}
