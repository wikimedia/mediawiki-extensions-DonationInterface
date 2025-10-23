<?php

namespace MediaWiki\Extension\DonationInterface\FraudFilters;

use MediaWiki\Session\Session;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;

class GenericVelocityFilter {

	/**
	 * Cache instance we use to store and retrieve scores
	 * @var BagOStuff
	 */
	protected BagOStuff $cache;
	protected Session $session;
	protected string $property;
	protected int $failScore;
	protected int $timeout;
	protected int $threshold;

	/**
	 * @param string $property
	 * @param int $threshold
	 * @param int $timeout
	 * @param int $failScore
	 */
	public function __construct( string $property, int $threshold, int $timeout, int $failScore ) {
		$this->property = $property;
		$this->threshold = $threshold;
		$this->timeout = $timeout;
		$this->failScore = $failScore;
	}

	/**
	 * Run the filter if we haven't for this session, and set a flag
	 * @param array &$riskScores
	 * @param array $transactionValues
	 * @param BagOStuff $cache
	 * @param Session $session
	 * @return void
	 */
	public function run(
		array &$riskScores, array $transactionValues, BagOStuff $cache, Session $session, LoggerInterface $logger
	) {
		$this->cache = $cache;
		$this->session = $session;
		// Only run once per session
		if ( $this->session->get( $this->makeSessionKey() ) ) {
			$logger->debug( "Already ran velocity filter for $this->property in this session, skipping" );
			return;
		}
		$propertyValue = $transactionValues[$this->property] ?? null;
		if ( !$propertyValue ) {
			$logger->debug( "$this->property is false-y, skipping velocity filter" );
			return;
		}
		if ( $this->isHitCountGreaterThanThreshold( $propertyValue, $logger ) ) {
			$score = $this->failScore;
		} else {
			$score = 0;
		}
		$this->addHitToCache( $propertyValue );
		$this->session->set( $this->makeSessionKey(), true );
		$riskScores[$this->makeFilterName()] = $score;
	}

	/**
	 * Make a key for the given value, that's legal to store in the cache
	 * @param string $value
	 * @return string
	 */
	protected function makeCacheKey( $value ) {
		return $this->cache->makeKey( 'VelocityFilter_' . $this->property, $value );
	}

	/**
	 * Filter name to store in fraud db
	 * @return string
	 */
	protected function makeFilterName() {
		return 'VelocityFilter_' . $this->property;
	}

	/**
	 * Key indicating this filter has already run in this session
	 * @return string
	 */
	protected function makeSessionKey() {
		return 'VelocityFilterRanInitial_' . $this->property;
	}

	/**
	 * Checks the attempt count in the cache. If the attempt count is greater
	 * than the threshold return true.
	 * @param string $propertyValue
	 * @return bool
	 */
	protected function isHitCountGreaterThanThreshold( string $propertyValue, LoggerInterface $logger ): bool {
		$stored = $this->getCachedValue( $propertyValue );

		if ( $stored ) {
			$count = count( $stored );
			$logger->debug( "Found $count hits for $propertyValue in cache" );
			if ( $count >= $this->threshold ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $propertyValue
	 * @return array
	 */
	protected function getCachedValue( string $propertyValue ): array {
		$stored = $this->cache->get( $this->makeCacheKey( $propertyValue ) );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Adds the hit to the local cache, recording another attempt.
	 * @param string $propertyValue
	 */
	protected function addHitToCache( string $propertyValue ) {
		$oldValue = $this->getCachedValue( $propertyValue );

		$this->cache->set(
			$this->makeCacheKey( $propertyValue ),
			self::addHitToVelocityData( $oldValue ),
			$this->timeout
		);
	}

	/**
	 * Add a new hit and filter out any expired hits from the stored array
	 * @param array $stored
	 * @return array
	 */
	protected function addHitToVelocityData( array $stored ): array {
		$newVelocityRecords = [];
		$now = time();
		foreach ( $stored as $timestamp ) {
			if ( $timestamp > ( $now - $this->timeout ) ) {
				$newVelocityRecords[] = $timestamp;
			}
		}
		$newVelocityRecords[] = $now;
		return $newVelocityRecords;
	}

}
