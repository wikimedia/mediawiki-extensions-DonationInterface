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
 * Forces the languages of the world to fit into a teensy little codespace
 * so we can send them in ISO-8859-1 encoding.
 * Uses Transliterator if available to turn non-latin characters into something
 * meaningful. If not available, iconv will just replace em with question marks
 */
class EncodingMangler implements StagingHelper {
	protected $use_transliterator = false;
	protected $transliterator;

	public function __construct() {
		if ( class_exists( 'Transliterator' ) ) {
			$this->use_transliterator = true;
			// Use Any-Latin to munge Cyrillic, Kanji, etc
			// Then convert anything outside the ISO-8859-1 range to nearest ASCII
			$this->transliterator = Transliterator::create( 'Any-Latin; [^a-ÿ] Latin-ASCII' );
		}
	}

	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		foreach ( array_keys( $stagedData ) as $key ) {
			$stagedData[$key] = $this->transliterate( $stagedData[$key] );
		}
	}

	/**
	 * Forces string into ISO-8859-1 space
	 *
	 * @param string $value UTF-8
	 * @return string still UTF-8, but only includes chars from 00-ff
	 */
	public function transliterate( $value ) {
		if ( $this->use_transliterator ) {
			return $this->transliterator->transliterate( $value );
		}
		$iso = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $value );
		return iconv( 'ISO-8859-1', 'UTF-8', $iso );
	}
}
