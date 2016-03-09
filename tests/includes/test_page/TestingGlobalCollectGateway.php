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
 * TestingGlobalCollectGateway
 * ...first off, I hate the name of the form classes.
 *
 * Second: DANGER! If we ever change the class that GlobalCollectGateway extends
 * from, the tests will stop working correctly. But, the constructor is really
 * the only thing that we need to override.
 */
class TestingGlobalCollectGateway extends GlobalCollectGateway {

	protected $adapterClass = 'TestingGlobalCollectAdapter';

	public function __construct() {
		GatewayPage::__construct(); //DANGER: See main class comments.
		// Don't want untranslated 'TestingGlobalCollectGateway' to foul our tests,
		// don't want to waste translators' time
		$this->mName = 'GlobalCollectGateway';
	}

}
