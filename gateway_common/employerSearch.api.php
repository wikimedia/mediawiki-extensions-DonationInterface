<?php

/**
 * MatchingGifts Employer Search API
 *
 * This API allows for the searching of companies which can then be
 * used to populate the Employer field across the paymentswiki forms.
 *
 * The datasource for this API is currently a CSV which contains the full
 * list of matchin gift employers available and is provided to us via a
 * third party. A separate process manages updating that datasource.
 *
 */
class EmployerSearchAPI extends ApiBase {

	/**
	 * @var string $apiError
	 */
	protected $apiError;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Read in the Employers file, transform it to an array and then filter
	 * results against the search query.
	 */
	public function execute() {
		$this->initLogger();
		$query = $this->getParameter( 'employer' );
		$results = '';
		// read in employers data file
		$employersFile = $this->readEmployersFile();
		if ( $employersFile ) {
			// transform the employer data into an array of id=>name items
			$employersList = $this->transformEmployerFileToArray( $employersFile );
			// filter the employers array to only return items that contain the search query
			$filteredEmployersList = array_filter( $employersList, function ( $value ) use ( $query ) {
				return stripos( array_values( $value )[0], $query ) !== false;
			} );
			// reset array keys
			$results = array_values( $filteredEmployersList );
		} else {
			$this->getResult()->addValue( null, 'error', $this->apiError );
		}
		$this->getResult()->addValue( null, 'result', $results );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [ 'employer' => [ ApiBase::PARAM_TYPE => 'string', ApiBase::PARAM_REQUIRED => true ] ];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [ 'action=employerSearch&employer=acme' => 'apihelp-employerSearch-example-1', ];
	}

	/**
	 * Read Employer list file.
	 *
	 * Currently expected to be a CSV and we try to confirm that with some funky
	 * validation checks.
	 *
	 * @return array|bool|false
	 */
	protected function readEmployersFile() {
		global $wgDonationInterfaceEmployersListDataFileLocation;
		if ( $wgDonationInterfaceEmployersListDataFileLocation && file_exists( $wgDonationInterfaceEmployersListDataFileLocation ) ) {
			$fileHandle = fopen( $wgDonationInterfaceEmployersListDataFileLocation, "r" );
			if ( fgetcsv( $fileHandle ) === false || fgetcsv( $fileHandle ) === null ) {
				$this->apiError = "Employer data file is either not a valid CSV or is empty: " . $wgDonationInterfaceEmployersListDataFileLocation;
				if ( $this->logger ) {
					$this->logger->error( 'employerSearch API error: ' . $this->apiError );
				}
				return false;
			}
			return file( $wgDonationInterfaceEmployersListDataFileLocation );
		} else {
			$this->apiError = "Invalid path for employer data file supplied: " . $wgDonationInterfaceEmployersListDataFileLocation;
			if ( $this->logger ) {
				$this->logger->error( 'employerSearch API error: ' . $this->apiError );
			}
			return false;
		}
	}

	/**
	 * Transform parsed Employer CSV into a useful array
	 *
	 * @param array $employersFile CSV file array
	 * @return array
	 */
	protected function transformEmployerFileToArray( array $employersFile ) {
		$employers = array_map( 'str_getcsv', $employersFile );
		$result = [];
		// iterate over each row and set the 1st el as key and 2nd el as val
		array_walk( $employers, function ( $row ) use ( &$result ) {
			$arr[$row[0]] = $row[1];
			$result[] = $arr;
		} );
		return $result;
	}

	/**
	 * Set up the Logger instance
	 *
	 * Note: It looks like we currently need a gateway to be set to use
	 * DonationInterface Logger.
	 */
	private function initLogger() {
		$sessionData = WmfFramework::getSessionValue( 'Donor' );
		if ( empty( $sessionData ) || empty( $sessionData['gateway'] ) ) {
			// Only log errors from ppl with a legitimate donation attempt
			return;
		}
		$gatewayName = $sessionData['gateway'];
		$gatewayClass = DonationInterface::getAdapterClassForGateway( $gatewayName );
		$gateway = new $gatewayClass();
		$this->logger = DonationLoggerFactory::getLogger( $gateway );
	}

	public function isReadMode() {
		return false;
	}

}
