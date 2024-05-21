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

trait TTestingAdapter {

	/** @var array */
	public static $fakeGlobals = [];

	/** @var string|false|null */
	public static $fakeIdentifier;

	public static function getIdentifier() {
		if ( static::$fakeIdentifier ) {
			return static::$fakeIdentifier;
		}
		return parent::getIdentifier();
	}

	public static function getGlobal( $name ) {
		if ( array_key_exists( $name, static::$fakeGlobals ) ) {
			return static::$fakeGlobals[$name];
		}
		return parent::getGlobal( $name );
	}

	/**
	 * @todo Get rid of this and the override mechanism as soon as you
	 * refactor the constructor into something reasonable.
	 */
	public function defineOrderIDMeta() {
		if ( isset( $this->order_id_meta ) ) {
			return;
		}
		parent::defineOrderIDMeta();
	}

	/**
	 * @todo That minFraud jerk needs its own isolated tests.
	 */
	public function runAntifraudFilters() {
		// now screw around with the batch settings to trick the fraud filters into triggering
		$is_batch = $this->isBatchProcessor();
		$this->batch = true;

		parent::runAntifraudFilters();

		$this->batch = $is_batch;
	}

}
