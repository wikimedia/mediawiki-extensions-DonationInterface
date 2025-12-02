<?php

namespace MediaWiki\Extension\DonationInterface\Tests;

use MaxMind\WebService\Http\CurlRequest;
use MaxMind\WebService\Http\RequestFactory;
use PHPUnit\Framework\MockObject\MockObject;

trait MinFraudTestTrait {

	/**
	 * @var MockObject
	 */
	protected $requestFactory;

	/**
	 * @var MockObject
	 */
	protected $minFraudRequest;

	/**
	 * Can only really use this trait on a TestCase subclass
	 * @param string $className
	 * @return MockObject
	 */
	abstract protected function createMock( string $className );

	protected function setUpMinFraudMocks(): void {
		$this->requestFactory = $this->createMock( RequestFactory::class );

		$this->minFraudRequest = $this->createMock( CurlRequest::class );

		$this->requestFactory->method( 'request' )->willReturn(
			$this->minFraudRequest
		);
	}

	protected function getMinFraudGlobalsWithoutPrefix(): array {
		return [
			'EnableMinFraud' => true,
			'MinFraudErrorScore' => 50,
			'MinFraudWeight' => 100,
			'MinFraudClientOptions' => [
				'host' => '0.0.0.0',
				'httpRequestFactory' => $this->requestFactory
			],
		];
	}

	protected function getMinFraudMockResponse(): array {
		return [
			200, 'application/json', file_get_contents(
				__DIR__ . '/../includes/Responses/minFraud/15points.json'
			)
		];
	}
}
