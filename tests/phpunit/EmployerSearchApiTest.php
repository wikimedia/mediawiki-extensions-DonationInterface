<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @group EmployerSearchApi
 * @group medium
 * @covers \EmployerSearchAPI
 */
class EmployerSearchApiTest extends ApiTestCase {

	/** @var resource */
	protected $csvDataSource;

	protected function setUp(): void {
		parent::setUp();
		$this->csvDataSource = tmpfile();
		$this->overrideConfigValue(
			'DonationInterfaceEmployersListDataFileLocation',
			stream_get_meta_data( $this->csvDataSource )['uri']
		);
		$this->getServiceContainer()->getObjectCacheFactory()->getLocalClusterInstance()
			->delete( EmployerSearchAPI::CACHE_KEY );
	}

	public function testSingleResultFromExactSearchLookup() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'ACME Inc';

		// populate API data source
		$testCSVData = [
			[ '1', 'Bills Sandwiches' ],
			[ '2', 'ACME Inc' ],
			[ '3', 'Bills Skateboards' ]
		];
		foreach ( $testCSVData as $csvLine ) {
			fputcsv( $this->csvDataSource, $csvLine, ',', '"', "\\" );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '2', 'name' => 'ACME Inc' ]
		];

		$this->assertCount( 1, $apiResult[0]['result'] );
		$this->assertArrayEquals( $expected, $apiResult[0]['result'], true );
	}

	public function testSingleResultFromPartialSearchLookup() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'AC';

		// populate API data source
		$testCSVData = [
			[ '1', 'Bills Sandwiches' ],
			[ '2', 'ACME Inc' ],
			[ '3', 'Bills Skateboards' ]
		];
		foreach ( $testCSVData as $csvLine ) {
			fputcsv( $this->csvDataSource, $csvLine, ',', '"', "\\" );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '2', 'name' => 'ACME Inc' ]
		];

		$this->assertCount( 1, $apiResult[0]['result'] );
		$this->assertArrayEquals( $expected, $apiResult[0]['result'], true );
	}

	public function testMultipleResultsFromPartialSearchLookup() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'Bills';

		// populate API data source
		$testCSVData = [
			[ '1', 'Bills Sandwiches' ],
			[ '2', 'ACME Inc' ],
			[ '3', 'Bills Skateboards' ]
		];
		foreach ( $testCSVData as $csvLine ) {
			fputcsv( $this->csvDataSource, $csvLine, ',', '"', "\\" );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '1', 'name' => 'Bills Sandwiches' ],
			[ 'id' => '3', 'name' => 'Bills Skateboards' ]
		];

		$this->assertCount( 2, $apiResult[0]['result'] );
		$this->assertArrayEquals( $expected, $apiResult[0]['result'], true );
	}

	public function testSubsidiaryAndParentCompanyCombinedResultSearch() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'ACME Subsidiary';

		// populate API data source
		$testCSVData = [
			[ '1', 'ACME Parent Company' ],
			[ '1', 'ACME Subsidiary Company' ], // the id of the subsidiary is the same as parent
		];
		foreach ( $testCSVData as $csvLine ) {
			fputcsv( $this->csvDataSource, $csvLine, ',', '"', "\\" );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '1', 'name' => 'ACME Subsidiary Company' ]
		];

		$this->assertCount( 1, $apiResult[0]['result'] );
		$this->assertArrayEquals( $expected, $apiResult[0]['result'], true );
	}

	public function testEmptyResultsSearch() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		// populate API data source
		$testCSVData = [
			[ '1', 'Bills Sandwiches' ],
			[ '2', 'ACME Inc' ],
			[ '3', 'Bills Skateboards' ]
		];
		foreach ( $testCSVData as $csvLine ) {
			fputcsv( $this->csvDataSource, $csvLine, ',', '"', "\\" );
		}

		$apiResult = $this->doApiRequest( $params );

		$this->assertCount( 0, $apiResult[0]['result'] );
	}

	public function testAPIInvalidData() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		$apiResult = $this->doApiRequest( $params );
		// populate API data source with bogus data
		fwrite( $this->csvDataSource, '!"£$%^&*(' );

		$this->assertRegExpTemp( '/^Employer data file is empty or can\'t be parsed.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListRetValInvalidData() {
		fwrite( $this->csvDataSource, '!"£$%^&*(' );

		$apiMain = new ApiMain();
		$api = TestingAccessWrapper::newFromObject(
			$apiMain->getModuleManager()->getModule( 'employerSearch' ) );

		$retVal = $api->getEmployersList();
		$this->assertFalse( $retVal );
	}

	public function testAPIInvalidDataLocation() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		$this->overrideConfigValue(
			'DonationInterfaceEmployersListDataFileLocation',
			'/road/to/nowhere.csv'
		);

		$apiResult = $this->doApiRequest( $params );

		$this->assertRegExpTemp( '/^Employer data file doesn\'t exist.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListInvalidDataLocation() {
		$this->overrideConfigValue(
			'DonationInterfaceEmployersListDataFileLocation',
			'/road/to/nowhere.csv'
		);

		$apiMain = new ApiMain();
		$api = TestingAccessWrapper::newFromObject(
			$apiMain->getModuleManager()->getModule( 'employerSearch' ) );

		$retVal = $api->getEmployersList();
		$this->assertFalse( $retVal );
	}

	public function testAPIWrongNumberOfColumns() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		// populate API data source
		$testCSVDataLine = [ '1', 'Bills Sandwiches', 'Yetch' ];
		fputcsv( $this->csvDataSource, $testCSVDataLine, ',', '"', "\\" );

		$apiResult = $this->doApiRequest( $params );

		$this->assertRegExpTemp( '/^Wrong number of columns in a row of employer data file.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListWrongNumberOfColumns() {
		$apiMain = new ApiMain();
		$api = TestingAccessWrapper::newFromObject(
			$apiMain->getModuleManager()->getModule( 'employerSearch' ) );

		// populate API data source
		$testCSVDataLine = [ '1', 'Bills Sandwiches', 'Yetch' ];
		fputcsv( $this->csvDataSource, $testCSVDataLine, ',', '"', "\\" );

		$retVal = $api->getEmployersList();
		$this->assertFalse( $retVal );
	}

	/**
	 * B/C: assertRegExp() is renamed in PHPUnit 9.x+
	 * @param string $pattern
	 * @param string $string
	 */
	protected function assertRegExpTemp( $pattern, $string ) {
		$method = method_exists( $this, 'assertMatchesRegularExpression' ) ?
		'assertMatchesRegularExpression' : 'assertRegExp';
		$this->$method( $pattern, $string );
	}
}
