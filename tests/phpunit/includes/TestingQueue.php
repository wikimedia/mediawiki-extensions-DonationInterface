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

/*
FIXME: We can't reference the actual classes until vendor/ is provisioned for tests.

use PHPQueue\Backend\Base;
*/

/**
 * Queue backend that uses a simple static variable.  Supports all operations
 * and multiple queues, but (obviously) data is destroyed on application
 * shutdown.
 */
class TestingQueue /* extends Base implements FifoQueueStore */ {
	protected $queue_name;
	protected $queue;

	public static $queues = array();

	public function __construct( $options=array() ) {
		//parent::__construct();
		if ( !empty($options['queue'] ) ) {
			$this->queue_name = $options['queue'];
		} else {
			$this->queue_name = 'default';
		}
		// Make me specific.
		if ( !array_key_exists( $this->queue_name, self::$queues ) ) {
			self::$queues[$this->queue_name] = array();
		}
		$this->queue = &self::$queues[$this->queue_name];
	}

	public static function clearAll() {
		self::$queues = array();
	}

	public function push( $data ) {
		$this->queue[] = json_encode( $data );
		return count( $this->queue ) - 1;
	}

	public function pop() {
		if ( !count( $this->queue ) ) {
			return null;
		}
		return json_decode( array_shift( $this->queue ), true );
	}
}
