<?php

class ArrayHelper {
	/**
	 * Create an array mirroring the given structure, but with values obtained
	 * from a callback. Like array_map, but with a few differences
	 *  - recurses when elements are themselves arrays
	 *  - if the callback returns '' or false, the element is skipped in the output
	 *  - if the element is a key with value null, the output element has no key
	 *
	 * @param callable $callback
	 * @param array $structure
	 * @return array
	 */
	public static function buildRequestArray( $callback, $structure ) {
		$data = [];
		foreach ( $structure as $key => $value ) {
			self::addArrayElement( $data, $structure, $key, $value, $callback );
		}
		return $data;
	}

	protected static function addArrayElement( &$targetElement, $structureElement, $key, $value, $callback ) {
		if ( is_numeric( $key ) ) {
			// it's just a value, not an associative array key
			$fieldName = $value;
			$fieldValue = $callback( $fieldName );
			if ( self::includeElement( $fieldValue ) ) {
				$targetElement[$fieldName] = $fieldValue;
			}
			return;
		}
		if ( is_array( $structureElement[$key] ) ) {
			$targetElement[$key] = [];
			foreach ( $structureElement[$key] as $subKey => $subValue ) {
				self::addArrayElement(
					$targetElement[$key],
					$structureElement[$key],
					$subKey,
					$subValue,
					$callback
				);
			}
			// TODO: If all children are skipped, remove $targetElement[$key] ?
		} elseif ( $structureElement[$key] === null ) {
			// HACK: needed a way to specify non-associative arrays in the output
			$fieldValue = $callback( $key );
			if ( self::includeElement( $fieldValue ) ) {
				$targetElement[] = $fieldValue;
			}
		}
	}

	protected static function includeElement( $value ) {
		// FIXME we should be able to pass 'false' as a parameter value
		return ( $value !== '' && $value !== false );
	}
}
