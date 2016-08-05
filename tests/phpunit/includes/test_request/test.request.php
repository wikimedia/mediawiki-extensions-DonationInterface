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
 * TestingRequest object extends the FauxRequest object (which is auto-used in
 * unit testing), with some essential normal request object functions added back
 * in.
 */
class TestingRequest extends FauxRequest {

	public function getRequestURL() {
		// /me shrugs
		return "http://localhost";
	}

}

