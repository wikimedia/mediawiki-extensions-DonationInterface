<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * Suite containing all DonationInterface test cases
 *
 * @see DonationInterface_Adapter_AllTests
 */
class DonationInterface_AllTests extends PHPUnit_Framework_TestSuite {
	function __construct() {
		parent::__construct( 'DonationInterface test suite' );

		$suffixes = [
			'Test.php',
		];
		$fileIterator = new File_Iterator_Facade();
		$files = $fileIterator->getFilesAsArray( __DIR__, $suffixes );
		$this->addTestFiles( $files );
	}

	public static function suite() {
		return new self();
	}
}
