<?php

class Subdivisions {

	public static function getByCountry ( $country ) {
		if ( isset( self::$list[$country] ) ) {
			$divisions = self::$list[$country];

			// Localize subdivisions where possible
			if ( isset( self::$keyBase[$country] ) ) {
				foreach ( $divisions as $abbr => $name ) {
					$key = self::$keyBase[$country] . $abbr;
					if ( WmfFramework::messageExists( $key ) ) {
						$divisions[$abbr] = WmfFramework::formatMessage( $key );
					}
				}
			}
			return $divisions;
		}
		return false;
	}

	private static $keyBase = array(
		'CA' => 'donate_interface-province-dropdown-',
		'US' => 'donate_interface-state_province-dropdown-',
	);

	private static $list = array(
		'AU' => array(
			'ACI' => 'Ashmore and Cartier Islands',
			'AAT' => 'Australian Antarctic Territory',
			'ACT' => 'Australian Capital Territory',
			'CI' => 'Christmas Island',
			'KI' => 'Cocos (Keeling) Islands',
			'CSI' => 'Coral Sea Islands',
			'HIMI' => 'Heard Island and McDonald Islands',
			'JB' => 'Jervis Bay Territory',
			'NSW' => 'New South Wales',
			'NI' => 'Norfolk Island',
			'NT' => 'Northern Territory',
			'QLD' => 'Queensland',
			'SA' => 'South Australia',
			'TAS' => 'Tasmania',
			'VIC' => 'Victoria',
			'WA' => 'Western Australia',
		),
		'CA' => array(
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NT' => 'Northwest Territories',
			'NS' => 'Nova Scotia',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		),
		'US' => array(
			'AK' => 'Alaska',
			'AL' => 'Alabama',
			'AR' => 'Arkansas',
			'AZ' => 'Arizona',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DC' => 'Washington D.C.',
			'DE' => 'Delaware',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'IA' => 'Iowa',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'MA' => 'Massachusetts',
			'MD' => 'Maryland',
			'ME' => 'Maine',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MO' => 'Missouri',
			'MS' => 'Mississippi',
			'MT' => 'Montana',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'NE' => 'Nebraska',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NV' => 'Nevada',
			'NY' => 'New York',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'PR' => 'Puerto Rico',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VA' => 'Virginia',
			'VT' => 'Vermont',
			'WA' => 'Washington',
			'WI' => 'Wisconsin',
			'WV' => 'West Virginia',
			'WY' => 'Wyoming',
			'AA' => 'AA',
			'AE' => 'AE',
			'AP' => 'AP',
		),
	);
}
