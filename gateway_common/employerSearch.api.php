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
	 * @var string
	 */
	protected $apiError;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Key for employers list in object cache
	 */
	const CACHE_KEY = 'EmployersList';

	/**
	 * TTL for employers list in object cache, in seconds (set to 1 hour)
	 */
	const CACHE_TTL = 3600;

	/**
	 * Maximum number of results to return
	 */
	const MAX_RESULTS = 10;

	/**
	 * Read in the Employers file, transform it to an array and then filter
	 * results against the search query.
	 */
	public function execute() {
		$this->initLogger();
		$query = $this->getParameter( 'employer' );
		$cache = ObjectCache::getLocalClusterInstance();

		// Get the employers list from the cache, or, if it's not there, read it from disk
		// and set the value in the cache. If the callback returns false, it indicates an
		// error reading or parsing the file, and nothing is stored in the cache.
		$employersList = $cache->getWithSetCallback(
			self::CACHE_KEY,
			self::CACHE_TTL,
			function () {
				return $this->getEmployersList();
			}
		);

		// A false value for $employersList indicates an issue reading or parsing the file.
		if ( $employersList === false ) {
			$this->getResult()->addValue( null, 'error', $this->apiError );
		} else {
			// Filter the list with query string
			$resultCount = 0;
			$filteredEmployersList = array_filter(
				$employersList,
				static function ( $value ) use ( $query, &$resultCount ) {
					return ( stripos( $value[ 'name' ], $query ) !== false &&
						( $resultCount++ < self::MAX_RESULTS ) );
				}
			);

			// Re-create the array with array_values to make it numerically indexed,
			// needed to output as an array in json.
			$filteredEmployersList = array_values( $filteredEmployersList );

			$this->getResult()->addValue( null, 'result', $filteredEmployersList );
		}
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
	 * Return an array of employers, where values are associative arrays with id and name
	 * keys. The data is based on the configured employer csv file. Returns false if there
	 * was a problem loading or parsing the file.
	 *
	 * @return array|bool
	 */
	protected function getEmployersList() {
		global $wgDonationInterfaceEmployersListDataFileLocation;

		// Check the employer data file exists
		if ( !file_exists( $wgDonationInterfaceEmployersListDataFileLocation ) ) {
			$this->setError( 'Employer data file doesn\'t exist: '
				. $wgDonationInterfaceEmployersListDataFileLocation );
			return false;
		}

		// Try to open the file
		$fileHandle = fopen( $wgDonationInterfaceEmployersListDataFileLocation, "r" );
		if ( !$fileHandle ) {
			$this->setError( 'Couldn\'t open employer data file: '
				. $wgDonationInterfaceEmployersListDataFileLocation );
			return false;
		}

		// Read in and parse the file
		$employerList = [];
		while ( ( $row = fgetcsv( $fileHandle ) ) !== false ) {
			if ( count( $row ) !== 2 ) {
				$this->setError( 'Wrong number of columns in a row of employer data file.' );
				fclose( $fileHandle );
				return false;
			}

			$employerList[] = [
				'id' => $row[ 0 ],
				'name' => $row[ 1 ]
			];
		}

		if ( empty( $employerList ) ) {
			$this->setError( 'Employer data file is empty or can\'t be parsed.' );
			fclose( $fileHandle );
			return false;
		}

		fclose( $fileHandle );
		return $employerList;
	}

	/**
	 * Set the API error string and log an error.
	 *
	 * @param string $errorMsg
	 */
	private function setError( $errorMsg ) {
		$this->apiError = $errorMsg;
		if ( $this->logger ) {
			$this->logger->error( 'employerSearch API error: ' . $errorMsg );
		}
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
