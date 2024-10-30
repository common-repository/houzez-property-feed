<?php

function get_houzez_property_feed_country_by_name($name)
{
	$countries = get_houzez_property_feed_countries();

	foreach ( $countries as $key => $value ) 
	{
        if ( strtolower($value['name']) === strtolower($name) ) 
        {
            return $key;
        }
        if ( isset($value['aliases']) && is_array($value['aliases']) && !empty($value['aliases']) )
        {
        	foreach ( $value['aliases'] as $alias )
        	{
        		if ( strtolower($alias) === strtolower($name) ) 
		        {
		            return $key;
		        }
        	}
        }
    }

    // No match found, see if there's a similar one (i.e. Russia instead of Russian Federation)
    foreach ( $countries as $key => $value ) 
	{
        if ( strpos(strtolower($value['name']), strtolower($name)) !== false ) 
        {
            return $key;
        }
        if ( isset($value['aliases']) && is_array($value['aliases']) && !empty($value['aliases']) )
        {
        	foreach ( $value['aliases'] as $alias )
        	{
        		if ( strpos(strtolower($alias), strtolower($name)) !== false ) 
		        {
		            return $key;
		        }
        	}
        }
    }

    return false;
}

function get_houzez_property_feed_countries()
{
	$countries = array(
	    'AF' => array(
			'name' => 'Afghanistan',
			'currency' => 'AFN',
		),
		'AL' => array(
			'name' => 'Albania',
			'currency' => 'ALL',
		),
		'DZ' => array(
			'name' => 'Algeria',
			'currency' => 'DZD',
		),
		'AS' => array(
			'name' => 'American Samoa',
			'currency' => 'USD',
		),
		'AD' => array(
			'name' => 'Andorra',
			'currency' => 'EUR',
		),
		'AO' => array(
			'name' => 'Angola',
			'currency' => 'AOA',
		),
		'AI' => array(
			'name' => 'Anguilla',
			'currency' => 'XCD',
		),
		'AQ' => array(
			'name' => 'Antarctica',
			'currency' => 'XXX',
		),
		'AG' => array(
			'name' => 'Antigua and Barbuda',
			'currency' => 'XCD',
		),
		'AR' => array(
			'name' => 'Argentina',
			'currency' => 'ARS',
		),
		'AM' => array(
			'name' => 'Armenia',
			'currency' => 'AMD',
		),
		'AW' => array(
			'name' => 'Aruba',
			'currency' => 'AWG',
		),
		'AU' => array(
			'name' => 'Australia',
			'currency' => 'AUD',
		),
		'AT' => array(
			'name' => 'Austria',
			'currency' => 'EUR',
		),
		'AZ' => array(
			'name' => 'Azerbaijan',
			'currency' => 'AZN',
		),
		'BS' => array(
			'name' => 'Bahamas',
			'currency' => 'BSD',
		),
		'BH' => array(
			'name' => 'Bahrain',
			'currency' => 'BHD',
		),
		'BD' => array(
			'name' => 'Bangladesh',
			'currency' => 'BDT',
		),
		'BB' => array(
			'name' => 'Barbados',
			'currency' => 'BBD',
		),
		'BY' => array(
			'name' => 'Belarus',
			'currency' => 'BYN',
		),
		'BE' => array(
			'name' => 'Belgium',
			'currency' => 'EUR',
		),
		'BZ' => array(
			'name' => 'Belize',
			'currency' => 'BZD',
		),
		'BJ' => array(
			'name' => 'Benin',
			'currency' => 'XOF',
		),
		'BM' => array(
			'name' => 'Bermuda',
			'currency' => 'BMD',
		),
		'BT' => array(
			'name' => 'Bhutan',
			'currency' => 'BTN',
		),
		'BO' => array(
			'name' => 'Bolivia',
			'currency' => 'BOB',
		),
		'BA' => array(
			'name' => 'Bosnia and Herzegovina',
			'currency' => 'BAM',
		),
		'BW' => array(
			'name' => 'Botswana',
			'currency' => 'BWP',
		),
		'BV' => array(
			'name' => 'Bouvet Island',
			'currency' => 'NOK',
		),
		'BR' => array(
			'name' => 'Brazil',
			'currency' => 'BRL',
		),
		'IO' => array(
			'name' => 'British Indian Ocean Territory',
			'currency' => 'USD',
		),
		'BN' => array(
			'name' => 'Brunei Darussalam',
			'currency' => 'BND',
		),
		'BG' => array(
			'name' => 'Bulgaria',
			'currency' => 'BGN',
		),
		'BF' => array(
			'name' => 'Burkina Faso',
			'currency' => 'XOF',
		),
		'BI' => array(
			'name' => 'Burundi',
			'currency' => 'BIF',
		),
		'CV' => array(
			'name' => 'Cabo Verde',
			'currency' => 'CVE',
		),
		'KH' => array(
			'name' => 'Cambodia',
			'currency' => 'KHR',
		),
		'CM' => array(
			'name' => 'Cameroon',
			'currency' => 'XAF',
		),
		'CA' => array(
			'name' => 'Canada',
			'currency' => 'CAD',
		),
		'KY' => array(
			'name' => 'Cayman Islands',
			'currency' => 'KYD',
		),
		'CF' => array(
			'name' => 'Central African Republic',
			'currency' => 'XAF',
		),
		'TD' => array(
			'name' => 'Chad',
			'currency' => 'XAF',
		),
		'CL' => array(
			'name' => 'Chile',
			'currency' => 'CLP',
		),
		'CN' => array(
			'name' => 'China',
			'currency' => 'CNY',
		),
		'CX' => array(
			'name' => 'Christmas Island',
			'currency' => 'AUD',
		),
		'CC' => array(
			'name' => 'Cocos (Keeling) Islands',
			'currency' => 'AUD',
		),
		'CO' => array(
			'name' => 'Colombia',
			'currency' => 'COP',
		),
		'KM' => array(
			'name' => 'Comoros',
			'currency' => 'KMF',
		),
		'CG' => array(
			'name' => 'Congo',
			'currency' => 'XAF',
		),
		'CD' => array(
			'name' => 'Congo, Democratic Republic of the',
			'currency' => 'CDF',
		),
		'CK' => array(
			'name' => 'Cook Islands',
			'currency' => 'NZD',
		),
		'CR' => array(
			'name' => 'Costa Rica',
			'currency' => 'CRC',
		),
		'CI' => array(
			'name' => 'Côte d\'Ivoire',
			'currency' => 'XOF',
		),
		'HR' => array(
			'name' => 'Croatia',
			'currency' => 'HRK',
		),
		'CU' => array(
			'name' => 'Cuba',
			'currency' => 'CUP',
		),
		'CY' => array(
			'name' => 'Cyprus',
			'currency' => 'EUR',
		),
		'CZ' => array(
			'name' => 'Czechia',
			'currency' => 'CZK',
		),
		'DK' => array(
			'name' => 'Denmark',
			'currency' => 'DKK',
		),
		'DJ' => array(
			'name' => 'Djibouti',
			'currency' => 'DJF',
		),
		'DM' => array(
			'name' => 'Dominica',
			'currency' => 'XCD',
		),
		'DO' => array(
			'name' => 'Dominican Republic',
			'currency' => 'DOP',
		),
		'EC' => array(
			'name' => 'Ecuador',
			'currency' => 'USD',
		),
		'EG' => array(
			'name' => 'Egypt',
			'currency' => 'EGP',
		),
		'SV' => array(
			'name' => 'El Salvador',
			'currency' => 'USD',
		),
		'GQ' => array(
			'name' => 'Equatorial Guinea',
			'currency' => 'XAF',
		),
		'ER' => array(
			'name' => 'Eritrea',
			'currency' => 'ERN',
		),
		'EE' => array(
			'name' => 'Estonia',
			'currency' => 'EUR',
		),
		'SZ' => array(
			'name' => 'Eswatini',
			'currency' => 'SZL',
		),
		'ET' => array(
			'name' => 'Ethiopia',
			'currency' => 'ETB',
		),
		'FK' => array(
			'name' => 'Falkland Islands (Malvinas)',
			'currency' => 'FKP',
		),
		'FO' => array(
			'name' => 'Faroe Islands',
			'currency' => 'DKK',
		),
		'FJ' => array(
			'name' => 'Fiji',
			'currency' => 'FJD',
		),
		'FI' => array(
			'name' => 'Finland',
			'currency' => 'EUR',
		),
		'FR' => array(
			'name' => 'France',
			'currency' => 'EUR',
		),
		'GF' => array(
			'name' => 'French Guiana',
			'currency' => 'EUR',
		),
		'PF' => array(
			'name' => 'French Polynesia',
			'currency' => 'XPF',
		),
		'TF' => array(
			'name' => 'French Southern Territories',
			'currency' => 'EUR',
		),
		'GA' => array(
			'name' => 'Gabon',
			'currency' => 'XAF',
		),
		'GM' => array(
			'name' => 'Gambia',
			'currency' => 'GMD',
		),
		'GE' => array(
			'name' => 'Georgia',
			'currency' => 'GEL',
		),
		'DE' => array(
			'name' => 'Germany',
			'currency' => 'EUR',
		),
		'GH' => array(
			'name' => 'Ghana',
			'currency' => 'GHS',
		),
		'GI' => array(
			'name' => 'Gibraltar',
			'currency' => 'GIP',
		),
		'GR' => array(
			'name' => 'Greece',
			'currency' => 'EUR',
		),
		'GL' => array(
			'name' => 'Greenland',
			'currency' => 'DKK',
		),
		'GD' => array(
			'name' => 'Grenada',
			'currency' => 'XCD',
		),
		'GP' => array(
			'name' => 'Guadeloupe',
			'currency' => 'EUR',
		),
		'GU' => array(
			'name' => 'Guam',
			'currency' => 'USD',
		),
		'GT' => array(
			'name' => 'Guatemala',
			'currency' => 'GTQ',
		),
		'GG' => array(
			'name' => 'Guernsey',
			'currency' => 'GBP',
		),
		'GN' => array(
			'name' => 'Guinea',
			'currency' => 'GNF',
		),
		'GW' => array(
			'name' => 'Guinea-Bissau',
			'currency' => 'XOF',
		),
		'GY' => array(
			'name' => 'Guyana',
			'currency' => 'GYD',
		),
		'HT' => array(
			'name' => 'Haiti',
			'currency' => 'HTG',
		),
		'HM' => array(
			'name' => 'Heard Island and McDonald Islands',
			'currency' => 'AUD',
		),
		'VA' => array(
			'name' => 'Holy See',
			'currency' => 'EUR',
		),
		'HN' => array(
			'name' => 'Honduras',
			'currency' => 'HNL',
		),
		'HK' => array(
			'name' => 'Hong Kong',
			'currency' => 'HKD',
		),
		'HU' => array(
			'name' => 'Hungary',
			'currency' => 'HUF',
		),
		'IS' => array(
			'name' => 'Iceland',
			'currency' => 'ISK',
		),
		'IN' => array(
			'name' => 'India',
			'currency' => 'INR',
		),
		'ID' => array(
			'name' => 'Indonesia',
			'currency' => 'IDR',
		),
		'IR' => array(
			'name' => 'Iran (Islamic Republic of)',
			'currency' => 'IRR',
		),
		'IQ' => array(
			'name' => 'Iraq',
			'currency' => 'IQD',
		),
		'IE' => array(
			'name' => 'Ireland',
			'currency' => 'EUR',
		),
		'IM' => array(
			'name' => 'Isle of Man',
			'currency' => 'GBP',
		),
		'IL' => array(
			'name' => 'Israel',
			'currency' => 'ILS',
		),
		'IT' => array(
			'name' => 'Italy',
			'currency' => 'EUR',
		),
		'JM' => array(
			'name' => 'Jamaica',
			'currency' => 'JMD',
		),
		'JP' => array(
			'name' => 'Japan',
			'currency' => 'JPY',
		),
		'JE' => array(
			'name' => 'Jersey',
			'currency' => 'GBP',
		),
		'JO' => array(
			'name' => 'Jordan',
			'currency' => 'JOD',
		),
		'KZ' => array(
			'name' => 'Kazakhstan',
			'currency' => 'KZT',
		),
		'KE' => array(
			'name' => 'Kenya',
			'currency' => 'KES',
		),
		'KI' => array(
			'name' => 'Kiribati',
			'currency' => 'AUD',
		),
		'KP' => array(
			'name' => 'Korea (Democratic People\'s Republic of)',
			'currency' => 'KPW',
		),
		'KR' => array(
			'name' => 'Korea, Republic of',
			'currency' => 'KRW',
		),
		'KW' => array(
			'name' => 'Kuwait',
			'currency' => 'KWD',
		),
		'KG' => array(
			'name' => 'Kyrgyzstan',
			'currency' => 'KGS',
		),
		'LA' => array(
			'name' => 'Lao People\'s Democratic Republic',
			'currency' => 'LAK',
		),
		'LV' => array(
			'name' => 'Latvia',
			'currency' => 'EUR',
		),
		'LB' => array(
			'name' => 'Lebanon',
			'currency' => 'LBP',
		),
		'LS' => array(
			'name' => 'Lesotho',
			'currency' => 'LSL',
		),
		'LR' => array(
			'name' => 'Liberia',
			'currency' => 'LRD',
		),
		'LY' => array(
			'name' => 'Libya',
			'currency' => 'LYD',
		),
		'LI' => array(
			'name' => 'Liechtenstein',
			'currency' => 'CHF',
		),
		'LT' => array(
			'name' => 'Lithuania',
			'currency' => 'EUR',
		),
		'LU' => array(
			'name' => 'Luxembourg',
			'currency' => 'EUR',
		),
		'MO' => array(
			'name' => 'Macao',
			'currency' => 'MOP',
		),
		'MG' => array(
			'name' => 'Madagascar',
			'currency' => 'MGA',
		),
		'MW' => array(
			'name' => 'Malawi',
			'currency' => 'MWK',
		),
		'MY' => array(
			'name' => 'Malaysia',
			'currency' => 'MYR',
		),
		'MV' => array(
			'name' => 'Maldives',
			'currency' => 'MVR',
		),
		'ML' => array(
			'name' => 'Mali',
			'currency' => 'XOF',
		),
		'MT' => array(
			'name' => 'Malta',
			'currency' => 'EUR',
		),
		'MH' => array(
			'name' => 'Marshall Islands',
			'currency' => 'USD',
		),
		'MQ' => array(
			'name' => 'Martinique',
			'currency' => 'EUR',
		),
		'MR' => array(
			'name' => 'Mauritania',
			'currency' => 'MRU',
		),
		'MU' => array(
			'name' => 'Mauritius',
			'currency' => 'MUR',
		),
		'YT' => array(
			'name' => 'Mayotte',
			'currency' => 'EUR',
		),
		'MX' => array(
			'name' => 'Mexico',
			'currency' => 'MXN',
		),
		'FM' => array(
			'name' => 'Micronesia (Federated States of)',
			'currency' => 'USD',
		),
		'MD' => array(
			'name' => 'Moldova (Republic of)',
			'currency' => 'MDL',
		),
		'MC' => array(
			'name' => 'Monaco',
			'currency' => 'EUR',
		),
		'MN' => array(
			'name' => 'Mongolia',
			'currency' => 'MNT',
		),
		'ME' => array(
			'name' => 'Montenegro',
			'currency' => 'EUR',
		),
		'MS' => array(
			'name' => 'Montserrat',
			'currency' => 'XCD',
		),
		'MA' => array(
			'name' => 'Morocco',
			'currency' => 'MAD',
		),
		'MZ' => array(
			'name' => 'Mozambique',
			'currency' => 'MZN',
		),
		'MM' => array(
			'name' => 'Myanmar',
			'currency' => 'MMK',
		),
		'NA' => array(
			'name' => 'Namibia',
			'currency' => 'NAD',
		),
		'NR' => array(
			'name' => 'Nauru',
			'currency' => 'AUD',
		),
		'NP' => array(
			'name' => 'Nepal',
			'currency' => 'NPR',
		),
		'NL' => array(
			'name' => 'Netherlands',
			'currency' => 'EUR',
		),
		'NC' => array(
			'name' => 'New Caledonia',
			'currency' => 'XPF',
		),
		'NZ' => array(
			'name' => 'New Zealand',
			'currency' => 'NZD',
		),
		'NI' => array(
			'name' => 'Nicaragua',
			'currency' => 'NIO',
		),
		'NE' => array(
			'name' => 'Niger',
			'currency' => 'XOF',
		),
		'NG' => array(
			'name' => 'Nigeria',
			'currency' => 'NGN',
		),
		'NU' => array(
			'name' => 'Niue',
			'currency' => 'NZD',
		),
		'NF' => array(
			'name' => 'Norfolk Island',
			'currency' => 'AUD',
		),
		'MK' => array(
			'name' => 'North Macedonia',
			'currency' => 'MKD',
		),
		'MP' => array(
			'name' => 'Northern Mariana Islands',
			'currency' => 'USD',
		),
		'NO' => array(
			'name' => 'Norway',
			'currency' => 'NOK',
		),
		'OM' => array(
			'name' => 'Oman',
			'currency' => 'OMR',
		),
		'PK' => array(
			'name' => 'Pakistan',
			'currency' => 'PKR',
		),
		'PW' => array(
			'name' => 'Palau',
			'currency' => 'USD',
		),
		'PS' => array(
			'name' => 'Palestine, State of',
			'currency' => 'ILS',
		),
		'PA' => array(
			'name' => 'Panama',
			'currency' => 'PAB',
		),
		'PG' => array(
			'name' => 'Papua New Guinea',
			'currency' => 'PGK',
		),
		'PY' => array(
			'name' => 'Paraguay',
			'currency' => 'PYG',
		),
		'PE' => array(
			'name' => 'Peru',
			'currency' => 'PEN',
		),
		'PH' => array(
			'name' => 'Philippines',
			'currency' => 'PHP',
		),
		'PN' => array(
			'name' => 'Pitcairn',
			'currency' => 'NZD',
		),
		'PL' => array(
			'name' => 'Poland',
			'currency' => 'PLN',
		),
		'PT' => array(
			'name' => 'Portugal',
			'currency' => 'EUR',
		),
		'PR' => array(
			'name' => 'Puerto Rico',
			'currency' => 'USD',
		),
		'QA' => array(
			'name' => 'Qatar',
			'currency' => 'QAR',
		),
		'RE' => array(
			'name' => 'Réunion',
			'currency' => 'EUR',
		),
		'RO' => array(
			'name' => 'Romania',
			'currency' => 'RON',
		),
		'RU' => array(
			'name' => 'Russian Federation',
			'currency' => 'RUB',
		),
		'RW' => array(
			'name' => 'Rwanda',
			'currency' => 'RWF',
		),
		'BL' => array(
			'name' => 'Saint Barthélemy',
			'currency' => 'EUR',
		),
		'SH' => array(
			'name' => 'Saint Helena, Ascension and Tristan da Cunha',
			'currency' => 'SHP',
		),
		'KN' => array(
			'name' => 'Saint Kitts and Nevis',
			'currency' => 'XCD',
		),
		'LC' => array(
			'name' => 'Saint Lucia',
			'currency' => 'XCD',
		),
		'MF' => array(
			'name' => 'Saint Martin (French part)',
			'currency' => 'EUR',
		),
		'PM' => array(
			'name' => 'Saint Pierre and Miquelon',
			'currency' => 'EUR',
		),
		'VC' => array(
			'name' => 'Saint Vincent and the Grenadines',
			'currency' => 'XCD',
		),
		'WS' => array(
			'name' => 'Samoa',
			'currency' => 'WST',
		),
		'SM' => array(
			'name' => 'San Marino',
			'currency' => 'EUR',
		),
		'ST' => array(
			'name' => 'Sao Tome and Principe',
			'currency' => 'STN',
		),
		'SA' => array(
			'name' => 'Saudi Arabia',
			'currency' => 'SAR',
		),
		'SN' => array(
			'name' => 'Senegal',
			'currency' => 'XOF',
		),
		'RS' => array(
			'name' => 'Serbia',
			'currency' => 'RSD',
		),
		'SC' => array(
			'name' => 'Seychelles',
			'currency' => 'SCR',
		),
		'SL' => array(
			'name' => 'Sierra Leone',
			'currency' => 'SLL',
		),
		'SG' => array(
			'name' => 'Singapore',
			'currency' => 'SGD',
		),
		'SX' => array(
			'name' => 'Sint Maarten (Dutch part)',
			'currency' => 'ANG',
		),
		'SK' => array(
			'name' => 'Slovakia',
			'currency' => 'EUR',
		),
		'SI' => array(
			'name' => 'Slovenia',
			'currency' => 'EUR',
		),
		'SB' => array(
			'name' => 'Solomon Islands',
			'currency' => 'SBD',
		),
		'SO' => array(
			'name' => 'Somalia',
			'currency' => 'SOS',
		),
		'ZA' => array(
			'name' => 'South Africa',
			'currency' => 'ZAR',
		),
		'GS' => array(
			'name' => 'South Georgia and the South Sandwich Islands',
			'currency' => 'GBP',
		),
		'SS' => array(
			'name' => 'South Sudan',
			'currency' => 'SSP',
		),
		'ES' => array(
			'name' => 'Spain',
			'currency' => 'EUR',
		),
		'LK' => array(
			'name' => 'Sri Lanka',
			'currency' => 'LKR',
		),
		'SD' => array(
			'name' => 'Sudan',
			'currency' => 'SDG',
		),
		'SR' => array(
			'name' => 'Suriname',
			'currency' => 'SRD',
		),
		'SJ' => array(
			'name' => 'Svalbard and Jan Mayen',
			'currency' => 'NOK',
		),
		'SE' => array(
			'name' => 'Sweden',
			'currency' => 'SEK',
		),
		'CH' => array(
			'name' => 'Switzerland',
			'currency' => 'CHF',
		),
		'SY' => array(
			'name' => 'Syrian Arab Republic',
			'currency' => 'SYP',
		),
		'TW' => array(
			'name' => 'Taiwan, Province of China',
			'currency' => 'TWD',
		),
		'TJ' => array(
			'name' => 'Tajikistan',
			'currency' => 'TJS',
		),
		'TZ' => array(
			'name' => 'Tanzania, United Republic of',
			'currency' => 'TZS',
		),
		'TH' => array(
			'name' => 'Thailand',
			'currency' => 'THB',
		),
		'TL' => array(
			'name' => 'Timor-Leste',
			'currency' => 'USD',
		),
		'TG' => array(
			'name' => 'Togo',
			'currency' => 'XOF',
		),
		'TK' => array(
			'name' => 'Tokelau',
			'currency' => 'NZD',
		),
		'TO' => array(
			'name' => 'Tonga',
			'currency' => 'TOP',
		),
		'TT' => array(
			'name' => 'Trinidad and Tobago',
			'currency' => 'TTD',
		),
		'TN' => array(
			'name' => 'Tunisia',
			'currency' => 'TND',
		),
		'TR' => array(
			'name' => 'Turkey',
			'currency' => 'TRY',
		),
		'TM' => array(
			'name' => 'Turkmenistan',
			'currency' => 'TMT',
		),
		'TC' => array(
			'name' => 'Turks and Caicos Islands',
			'currency' => 'USD',
		),
		'TV' => array(
			'name' => 'Tuvalu',
			'currency' => 'AUD',
		),
		'UG' => array(
			'name' => 'Uganda',
			'currency' => 'UGX',
		),
		'UA' => array(
			'name' => 'Ukraine',
			'currency' => 'UAH',
		),
		'AE' => array(
			'name' => 'United Arab Emirates',
			'currency' => 'AED',
			'aliases' => array('UAE'),
		),
		'GB' => array(
			'name' => 'United Kingdom of Great Britain and Northern Ireland',
			'currency' => 'GBP',
		),
		'US' => array(
			'name' => 'United States of America',
			'currency' => 'USD',
		),
		'UY' => array(
			'name' => 'Uruguay',
			'currency' => 'UYU',
		),
		'UZ' => array(
			'name' => 'Uzbekistan',
			'currency' => 'UZS',
		),
		'VU' => array(
			'name' => 'Vanuatu',
			'currency' => 'VUV',
		),
		'VE' => array(
			'name' => 'Venezuela (Bolivarian Republic of)',
			'currency' => 'VES',
		),
		'VN' => array(
			'name' => 'Viet Nam',
			'currency' => 'VND',
		),
		'WF' => array(
			'name' => 'Wallis and Futuna',
			'currency' => 'XPF',
		),
		'EH' => array(
			'name' => 'Western Sahara',
			'currency' => 'MAD',
		),
		'YE' => array(
			'name' => 'Yemen',
			'currency' => 'YER',
		),
		'ZM' => array(
			'name' => 'Zambia',
			'currency' => 'ZMW',
		),
		'ZW' => array(
			'name' => 'Zimbabwe',
			'currency' => 'ZWL',
		),
		'AX' => array(
			'name' => 'Åland Islands',
			'currency' => 'EUR',
		),
		'UM' => array(
			'name' => 'United States Minor Outlying Islands',
			'currency' => 'USD',
		),
		'AQ' => array(
			'name' => 'Antarctica',
			'currency' => 'XXX',
		),
		'BV' => array(
			'name' => 'Bouvet Island',
			'currency' => 'NOK',
		),
		'HM' => array(
			'name' => 'Heard Island and McDonald Islands',
			'currency' => 'AUD',
		),
		'GS' => array(
			'name' => 'South Georgia and the South Sandwich Islands',
			'currency' => 'GBP',
		),
		'TF' => array(
			'name' => 'French Southern Territories',
			'currency' => 'EUR',
		),
		'PS' => array(
			'name' => 'Palestine, State of',
			'currency' => 'ILS',
		),
		'XK' => array(
			'name' => 'Kosovo',
			'currency' => 'EUR',
		),
		'TW' => array(
			'name' => 'Taiwan',
			'currency' => 'TWD',
		),
		'HK' => array(
			'name' => 'Hong Kong',
			'currency' => 'HKD',
		),
		'MO' => array(
			'name' => 'Macao',
			'currency' => 'MOP',
		),
		'GG' => array(
			'name' => 'Guernsey',
			'currency' => 'GBP',
		),
		'JE' => array(
			'name' => 'Jersey',
			'currency' => 'GBP',
		),
		'IM' => array(
			'name' => 'Isle of Man',
			'currency' => 'GBP',
		),
		'CX' => array(
			'name' => 'Christmas Island',
			'currency' => 'AUD',
		),
		'CC' => array(
			'name' => 'Cocos (Keeling) Islands',
			'currency' => 'AUD',
		),
		'NF' => array(
			'name' => 'Norfolk Island',
			'currency' => 'AUD',
		),
		'NU' => array(
			'name' => 'Niue',
			'currency' => 'NZD',
		),
		'TK' => array(
			'name' => 'Tokelau',
			'currency' => 'NZD',
		),
		'NF' => array(
			'name' => 'Norfolk Island',
			'currency' => 'AUD',
		),
		'CX' => array(
			'name' => 'Christmas Island',
			'currency' => 'AUD',
		),
		'CC' => array(
			'name' => 'Cocos (Keeling) Islands',
			'currency' => 'AUD',
		),
		'PN' => array(
			'name' => 'Pitcairn',
			'currency' => 'NZD',
		),
		'HM' => array(
			'name' => 'Heard Island and McDonald Islands',
			'currency' => 'AUD',
		),
		'GS' => array(
			'name' => 'South Georgia and the South Sandwich Islands',
			'currency' => 'GBP',
		),
		'TF' => array(
			'name' => 'French Southern Territories',
			'currency' => 'EUR',
		),
		'BV' => array(
			'name' => 'Bouvet Island',
			'currency' => 'NOK',
		),
		'AQ' => array(
			'name' => 'Antarctica',
			'currency' => 'XXX',
		),
	);

	return apply_filters( 'houzez_property_feed_countries', $countries );
}