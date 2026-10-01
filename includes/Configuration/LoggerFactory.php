<?php

namespace MediaWiki\Extension\DonationInterface\Configuration;

use DonationLoggerFactory;
use LogPrefixProvider;
use MediaWiki\Config\ServiceOptions;
use Psr\Log\LoggerInterface;

class LoggerFactory {

	public const CONSTRUCTOR_OPTIONS = [
		'DonationInterfaceUseSyslog',
		'DonationInterfaceLogDebug',
	];

	public function __construct( private readonly ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getLogger(
		string $identifier,
		?LogPrefixProvider $prefixer = null,
		string $suffix = ''
	): LoggerInterface {
		return DonationLoggerFactory::getLoggerFromParams(
			$identifier,
			$this->options->get( 'DonationInterfaceUseSyslog' ),
			$this->options->get( 'DonationInterfaceLogDebug' ),
			$suffix,
			$prefixer
		);
	}
}
