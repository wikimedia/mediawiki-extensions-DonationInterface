<?php

class ArrayHelper {

	/**
	 * Creates a lookup table from nested arrays
	 * @param array $sourceArray array containing the forward mapping
	 * @param string $lookupBy property of the original config file's elements
	 * TODO: could extend this to create nested arrays, e.g. to build method groups
	 * @return array
	 */
	public static function buildLookupTable( $sourceArray, $lookupBy ) {
		# PHP 5.4: $keys = array_column( $sourceArray, $lookupBy );
		$keys = array_map(
			function( $e ) use ( $lookupBy ) { return $e[$lookupBy]; },
			$sourceArray
		);
		$values = array_keys( $sourceArray );

		return array_combine( $keys, $values );
	}
}

