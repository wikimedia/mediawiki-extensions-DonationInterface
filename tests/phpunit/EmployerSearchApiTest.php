<?php

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
		global $wgDonationInterfaceEmployersListDataFileLocation;
		$wgDonationInterfaceEmployersListDataFileLocation = stream_get_meta_data( $this->csvDataSource )['uri'];
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
			[ 2 => 'ACME Inc' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
		$this->assertEquals( $expected, $apiResult[0]['result'] );
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
			[ 2 => 'ACME Inc' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
		$this->assertEquals( $expected, $apiResult[0]['result'] );
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
			[ 1 => 'Bills Sandwiches' ],
			[ 3 => 'Bills Skateboards' ]
		];

		$this->assertEquals( 2, count( $apiResult[0]['result'] ) );
		$this->assertEquals( $expected, $apiResult[0]['result'] );
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
			[ 1 => 'ACME Subsidiary Company' ]
		];

		$this->assertEquals( 1, count( $apiResult[0]['result'] ) );
		$this->assertEquals( $expected, $apiResult[0]['result'] );
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

	public function testInvalidApiDatasourceContent() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		$apiResult = $this->doApiRequest( $params );
		// populate API data source with bogus data
		fwrite( $this->csvDataSource, '!"£$%^&*(' );

		$this->assertRegExp( '/^Employer data file is either not a valid CSV or is empty: .*/', $apiResult[0]['error'] );
	}

	public function testInvalidApiDatasourceLocation() {
		// api action
		$params['action'] = 'employerSearch';
		// api employer search query
		$params['employer'] = 'unknown';

		global $wgDonationInterfaceEmployersListDataFileLocation;
		$wgDonationInterfaceEmployersListDataFileLocation = '/road/to/nowhere.csv';

		$apiResult = $this->doApiRequest( $params );

		$this->assertRegExp( '/^Invalid path for employer data file supplied: .*/', $apiResult[0]['error'] );
	}
}
