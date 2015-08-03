<?php

/**
 * List that can be iterated repeatedly, and changed from within the loop
 *
 * Used to track queue endpoints.
 */
class CyclicalArray {
	protected $queues;

	/**
	 * Can be initialized with a single object or an array.
	 */
	public function __construct( $queue_list ) {
		$this->queues = (array) $queue_list;
	}

	public function current() {
		return $this->queues[0];
	}

	public function isEmpty() {
		return empty( $this->queues );
	}

	public function rotate() {
		if ( count( $this->queues ) < 1 ) {
			return;
		}
		$rotate_elem = array_shift( $this->queues );
		array_push( $this->queues, $rotate_elem );
	}

	public function dropCurrent() {
		array_shift( $this->queues );
	}
}
