<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DonationInterfaceApi
 * @group EmployerSearchApi
 * @group medium
 */
class EmployerSearchApiTest extends ApiTestCase {

	protected $csvDataSource;

	public function setUp() {
		parent::setUp();
		$this->csvDataSource = tmpfile();
		$this->setMwGlobals(
			'wgDonationInterfaceEmployersListDataFileLocation',
			stream_get_meta_data( $this->csvDataSource )['uri']
		);
		ObjectCache::getLocalClusterInstance()->delete( EmployerSearchAPI::CACHE_KEY );
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
			fputcsv( $this->csvDataSource, $csvLine );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '2', 'name' => 'ACME Inc' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
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
			fputcsv( $this->csvDataSource, $csvLine );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '2', 'name' => 'ACME Inc' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
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
			fputcsv( $this->csvDataSource, $csvLine );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '1', 'name' => 'Bills Sandwiches' ],
			[ 'id' => '3', 'name' => 'Bills Skateboards' ]
		];

		$this->assertEquals( 2, count( $apiResult[0]['result'] ) );
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
			fputcsv( $this->csvDataSource, $csvLine );
		}

		$apiResult = $this->doApiRequest( $params );
		$expected = [
			[ 'id' => '1', 'name' => 'ACME Subsidiary Company' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
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
			fputcsv( $this->csvDataSource, $csvLine );
		}

		$apiResult = $this->doApiRequest( $params );

		$this->assertEquals( 0, count( $apiResult[0]['result'] ) );
	}

	public function testAPIInvalidData() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		$apiResult = $this->doApiRequest( $params );
		// populate API data source with bogus data
		fwrite( $this->csvDataSource, '!"£$%^&*(' );

		$this->assertRegExp( '/^Employer data file is empty or can\'t be parsed.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListRetValInvalidData() {
		fwrite( $this->csvDataSource, '!"£$%^&*(' );

		$api = TestingAccessWrapper::newFromObject(
			new EmployerSearchAPI( new ApiMain(), null ) );

		$retVal = $api->getEmployersList();
		$this->assertFalse( $retVal );
	}

	public function testAPIInvalidDataLocation() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		$this->setMwGlobals(
			'wgDonationInterfaceEmployersListDataFileLocation',
			'/road/to/nowhere.csv'
		);

		$apiResult = $this->doApiRequest( $params );

		$this->assertRegExp( '/^Employer data file doesn\'t exist.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListInvalidDataLocation() {
		$this->setMwGlobals(
			'wgDonationInterfaceEmployersListDataFileLocation',
			'/road/to/nowhere.csv'
		);

		$api = TestingAccessWrapper::newFromObject(
			new EmployerSearchAPI( new ApiMain(), null ) );

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
		fputcsv( $this->csvDataSource, $testCSVDataLine );

		$apiResult = $this->doApiRequest( $params );

		$this->assertRegExp( '/^Wrong number of columns in a row of employer data file.*/',
			$apiResult[0]['error'] );
	}

	public function testGetEmployersListWrongNumberOfColumns() {
		$api = TestingAccessWrapper::newFromObject(
			new EmployerSearchAPI( new ApiMain(), null ) );

		// populate API data source
		$testCSVDataLine = [ '1', 'Bills Sandwiches', 'Yetch' ];
		fputcsv( $this->csvDataSource, $testCSVDataLine );

		$retVal = $api->getEmployersList();
		$this->assertFalse( $retVal );
	}
}
