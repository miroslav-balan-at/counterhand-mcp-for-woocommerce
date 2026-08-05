<?php
/**
 * Arguments WooCommerce registers for POST /wc/v3/orders.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
	'parent_id'                       =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'ID der übergeordneten Bestellung.',
		'type'              => 'integer',
	),
	'created_via'                     =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Zeigt an, wo die Bestellung erstellt wurde.',
		'type'              => 'string',
	),
	'status'                          =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'default'           => 'pending',
		'description'       => 'Bestellstatus.',
		'type'              => 'string',
		'enum'              =>
		array(
			0  => 'auto-draft',
			1  => 'angebot',
			2  => 'pending',
			3  => 'processing',
			4  => 'on-hold',
			5  => 'completed',
			6  => 'cancelled',
			7  => 'refunded',
			8  => 'failed',
			9  => 'pending-wdraw',
			10 => 'withdrawn',
			11 => 'checkout-draft',
		),
	),
	'currency'                        =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'default'           => 'EUR',
		'description'       => 'Währung, in der die Bestellung erstellt wurde (im ISO-Format).',
		'type'              => 'string',
		'enum'              =>
		array(
			0   => 'AED',
			1   => 'AFN',
			2   => 'ALL',
			3   => 'AMD',
			4   => 'ANG',
			5   => 'AOA',
			6   => 'ARS',
			7   => 'AUD',
			8   => 'AWG',
			9   => 'AZN',
			10  => 'BAM',
			11  => 'BBD',
			12  => 'BDT',
			13  => 'BGN',
			14  => 'BHD',
			15  => 'BIF',
			16  => 'BMD',
			17  => 'BND',
			18  => 'BOB',
			19  => 'BRL',
			20  => 'BSD',
			21  => 'BTC',
			22  => 'BTN',
			23  => 'BWP',
			24  => 'BYR',
			25  => 'BYN',
			26  => 'BZD',
			27  => 'CAD',
			28  => 'CDF',
			29  => 'CHF',
			30  => 'CLP',
			31  => 'CNY',
			32  => 'COP',
			33  => 'CRC',
			34  => 'CUC',
			35  => 'CUP',
			36  => 'CVE',
			37  => 'CZK',
			38  => 'DJF',
			39  => 'DKK',
			40  => 'DOP',
			41  => 'DZD',
			42  => 'EGP',
			43  => 'ERN',
			44  => 'ETB',
			45  => 'EUR',
			46  => 'FJD',
			47  => 'FKP',
			48  => 'GBP',
			49  => 'GEL',
			50  => 'GGP',
			51  => 'GHS',
			52  => 'GIP',
			53  => 'GMD',
			54  => 'GNF',
			55  => 'GTQ',
			56  => 'GYD',
			57  => 'HKD',
			58  => 'HNL',
			59  => 'HRK',
			60  => 'HTG',
			61  => 'HUF',
			62  => 'IDR',
			63  => 'ILS',
			64  => 'IMP',
			65  => 'INR',
			66  => 'IQD',
			67  => 'IRR',
			68  => 'IRT',
			69  => 'ISK',
			70  => 'JEP',
			71  => 'JMD',
			72  => 'JOD',
			73  => 'JPY',
			74  => 'KES',
			75  => 'KGS',
			76  => 'KHR',
			77  => 'KMF',
			78  => 'KPW',
			79  => 'KRW',
			80  => 'KWD',
			81  => 'KYD',
			82  => 'KZT',
			83  => 'LAK',
			84  => 'LBP',
			85  => 'LKR',
			86  => 'LRD',
			87  => 'LSL',
			88  => 'LYD',
			89  => 'MAD',
			90  => 'MDL',
			91  => 'MGA',
			92  => 'MKD',
			93  => 'MMK',
			94  => 'MNT',
			95  => 'MOP',
			96  => 'MRU',
			97  => 'MUR',
			98  => 'MVR',
			99  => 'MWK',
			100 => 'MXN',
			101 => 'MYR',
			102 => 'MZN',
			103 => 'NAD',
			104 => 'NGN',
			105 => 'NIO',
			106 => 'NOK',
			107 => 'NPR',
			108 => 'NZD',
			109 => 'OMR',
			110 => 'PAB',
			111 => 'PEN',
			112 => 'PGK',
			113 => 'PHP',
			114 => 'PKR',
			115 => 'PLN',
			116 => 'PRB',
			117 => 'PYG',
			118 => 'QAR',
			119 => 'RON',
			120 => 'RSD',
			121 => 'RUB',
			122 => 'RWF',
			123 => 'SAR',
			124 => 'SBD',
			125 => 'SCR',
			126 => 'SDG',
			127 => 'SEK',
			128 => 'SGD',
			129 => 'SHP',
			130 => 'SLL',
			131 => 'SOS',
			132 => 'SRD',
			133 => 'SSP',
			134 => 'STN',
			135 => 'SYP',
			136 => 'SZL',
			137 => 'THB',
			138 => 'TJS',
			139 => 'TMT',
			140 => 'TND',
			141 => 'TOP',
			142 => 'TRY',
			143 => 'TTD',
			144 => 'TWD',
			145 => 'TZS',
			146 => 'UAH',
			147 => 'UGX',
			148 => 'USD',
			149 => 'UYU',
			150 => 'UZS',
			151 => 'VEF',
			152 => 'VES',
			153 => 'VND',
			154 => 'VUV',
			155 => 'WST',
			156 => 'XAF',
			157 => 'XCD',
			158 => 'XOF',
			159 => 'XPF',
			160 => 'YER',
			161 => 'ZAR',
			162 => 'ZMW',
		),
	),
	'customer_id'                     =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'default'           => 0,
		'description'       => 'Benutzer-ID, die zur Bestellung gehört. 0 für Gäste.',
		'type'              => 'integer',
	),
	'customer_note'                   =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Notiz, die vom Kunden beim Bezahlvorgang hinterlassen wurde.',
		'type'              => 'string',
	),
	'billing'                         =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Rechnungsadresse.',
		'type'              => 'object',
		'properties'        =>
		array(
			'first_name'      =>
			array(
				'description' => 'Vorname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'last_name'       =>
			array(
				'description' => 'Nachname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'company'         =>
			array(
				'description' => 'Firmenname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'address_1'       =>
			array(
				'description' => 'Adresszeile 1',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'address_2'       =>
			array(
				'description' => 'Adresszeile 2',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'city'            =>
			array(
				'description' => 'Name der Stadt.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'state'           =>
			array(
				'description' => 'ISO-Code oder Name des Staats, der Provinz oder des Distrikts.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'postcode'        =>
			array(
				'description' => 'Postleitzahl.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'country'         =>
			array(
				'description' => 'Ländercode (nach ISO 3166-1 alpha-2).',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'email'           =>
			array(
				'description' => 'E-Mail-Adresse.',
				'type'        =>
				array(
					0 => 'string',
					1 => 'null',
				),
				'format'      => 'email',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'phone'           =>
			array(
				'description' => 'Telefonnummer.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'title'           =>
			array(
				'description' => 'Anrede',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'title_formatted' =>
			array(
				'description' => 'Formatierter Titel',
				'type'        => 'string',
				'readonly'    => true,
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'vat_id'          =>
			array(
				'description' => 'USt.-ID',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
		),
	),
	'shipping'                        =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Versandadresse.',
		'type'              => 'object',
		'properties'        =>
		array(
			'first_name'      =>
			array(
				'description' => 'Vorname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'last_name'       =>
			array(
				'description' => 'Nachname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'company'         =>
			array(
				'description' => 'Firmenname.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'address_1'       =>
			array(
				'description' => 'Adresszeile 1',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'address_2'       =>
			array(
				'description' => 'Adresszeile 2',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'city'            =>
			array(
				'description' => 'Name der Stadt.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'state'           =>
			array(
				'description' => 'ISO-Code oder Name des Staats, der Provinz oder des Distrikts.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'postcode'        =>
			array(
				'description' => 'Postleitzahl.',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'country'         =>
			array(
				'description' => 'Ländercode (nach ISO 3166-1 alpha-2).',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'title'           =>
			array(
				'description' => 'Anrede',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'title_formatted' =>
			array(
				'description' => 'Formatierter Titel',
				'type'        => 'string',
				'readonly'    => true,
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'vat_id'          =>
			array(
				'description' => 'USt.-ID',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
		),
	),
	'payment_method'                  =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Zahlungsmethoden-ID.',
		'type'              => 'string',
	),
	'payment_method_title'            =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'sanitize_text_field',
		'description'       => 'Zahlungsmethoden-Titel.',
		'type'              => 'string',
	),
	'transaction_id'                  =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Eindeutige Transaktions-ID.',
		'type'              => 'string',
	),
	'meta_data'                       =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Metadaten.',
		'type'              => 'array',
		'items'             =>
		array(
			'type'       => 'object',
			'properties' =>
			array(
				'id'    =>
				array(
					'description' => 'Meta-ID.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'key'   =>
				array(
					'description' => 'Meta-Schlüssel.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'value' =>
				array(
					'description' => 'Meta-Wert.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
			),
		),
	),
	'line_items'                      =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Bestellpositionen.',
		'type'              => 'array',
		'items'             =>
		array(
			'type'       => 'object',
			'properties' =>
			array(
				'id'               =>
				array(
					'description' => 'Bestellpositions-ID.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'name'             =>
				array(
					'description' => 'Produktname.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'parent_name'      =>
				array(
					'description' => 'Übergeordneter Produktname, wenn das Produkt eine Variation ist.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'product_id'       =>
				array(
					'description' => 'Produkt-ID.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'variation_id'     =>
				array(
					'description' => 'Varianten-ID (falls zutreffend).',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'quantity'         =>
				array(
					'description' => 'Bestellte Stückzahl.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'tax_class'        =>
				array(
					'description' => 'Steuerklasse des Produkts.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'subtotal'         =>
				array(
					'description' => 'Posten-Zwischensumme, ohne Steuern (vor Rabatten).',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'subtotal_tax'     =>
				array(
					'description' => 'Zwischensumme der Steuern (vor Preisnachlässen/Rabatten).',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'total'            =>
				array(
					'description' => 'Postensumme, ohne Steuern (nach Rabatten).',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'total_tax'        =>
				array(
					'description' => 'Gesamtsumme der Steuern (nach Preisnachlässen/Rabatten).',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'taxes'            =>
				array(
					'description' => 'Steuern.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'       =>
							array(
								'description' => 'Steuersatz ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'total'    =>
							array(
								'description' => 'Gesamtsumme der Steuern.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'subtotal' =>
							array(
								'description' => 'Zwischensumme der Steuern.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
						),
					),
				),
				'meta_data'        =>
				array(
					'description' => 'Metadaten.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'            =>
							array(
								'description' => 'Meta-ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'key'           =>
							array(
								'description' => 'Meta-Schlüssel.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'value'         =>
							array(
								'description' => 'Meta-Wert.',
								'type'        =>
								array(
									0 => 'null',
									1 => 'object',
									2 => 'string',
									3 => 'number',
									4 => 'boolean',
									5 => 'integer',
									6 => 'array',
								),
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'display_key'   =>
							array(
								'description' => 'Metaschlüssel für die UI-Anzeige.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'display_value' =>
							array(
								'description' => 'Metawert für die UI-Anzeige.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
						),
					),
				),
				'sku'              =>
				array(
					'description' => 'Produkt Artikelnummer.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'global_unique_id' =>
				array(
					'description' => 'GTIN, UPC, EAN oder ISBN.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'price'            =>
				array(
					'description' => 'Produktpreis.',
					'type'        => 'number',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'image'            =>
				array(
					'description' => 'Eigenschaften des Hauptproduktbildes.',
					'type'        => 'object',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
					'properties'  =>
					array(
						'id'  =>
						array(
							'description' => 'Bild-ID.',
							'type'        => 'integer',
							'context'     =>
							array(
								0 => 'view',
								1 => 'edit',
							),
						),
						'src' =>
						array(
							'description' => 'Bild URL.',
							'type'        => 'string',
							'format'      => 'uri',
							'context'     =>
							array(
								0 => 'view',
								1 => 'edit',
							),
						),
					),
				),
			),
		),
	),
	'shipping_lines'                  =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Versand-Daten.',
		'type'              => 'array',
		'items'             =>
		array(
			'type'       => 'object',
			'properties' =>
			array(
				'id'           =>
				array(
					'description' => 'Bestellpositions-ID.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'method_title' =>
				array(
					'description' => 'Name der Lieferart.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'method_id'    =>
				array(
					'description' => 'Versandart ID.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'instance_id'  =>
				array(
					'description' => 'Versandinstanz-ID.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'total'        =>
				array(
					'description' => 'Die Versandsumme, ohne Steuern.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'total_tax'    =>
				array(
					'description' => 'Steuern auf Versand insgesamt.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'taxes'        =>
				array(
					'description' => 'Steuern.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'    =>
							array(
								'description' => 'Steuersatz ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'total' =>
							array(
								'description' => 'Gesamtsumme der Steuern.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
						),
					),
				),
				'meta_data'    =>
				array(
					'description' => 'Metadaten.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'    =>
							array(
								'description' => 'Meta-ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'key'   =>
							array(
								'description' => 'Meta-Schlüssel.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'value' =>
							array(
								'description' => 'Meta-Wert.',
								'type'        =>
								array(
									0 => 'null',
									1 => 'object',
									2 => 'string',
									3 => 'number',
									4 => 'boolean',
									5 => 'integer',
									6 => 'array',
								),
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
						),
					),
				),
			),
		),
	),
	'fee_lines'                       =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Daten zu Gebühren.',
		'type'              => 'array',
		'items'             =>
		array(
			'type'       => 'object',
			'properties' =>
			array(
				'id'         =>
				array(
					'description' => 'Bestellpositions-ID.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'name'       =>
				array(
					'description' => 'Gebührenbezeichnung.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'tax_class'  =>
				array(
					'description' => 'Steuersatz der Gebühr.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'tax_status' =>
				array(
					'description' => 'Steuerstatus der Gebühr.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'enum'        =>
					array(
						0 => 'taxable',
						1 => 'none',
					),
				),
				'total'      =>
				array(
					'description' => 'Gebühr insgesamt, ohne Steuern.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'total_tax'  =>
				array(
					'description' => 'Steuern auf Gebühren insgesamt.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'taxes'      =>
				array(
					'description' => 'Steuern.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'       =>
							array(
								'description' => 'Steuersatz ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'total'    =>
							array(
								'description' => 'Gesamtsumme der Steuern.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'subtotal' =>
							array(
								'description' => 'Zwischensumme der Steuern.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
						),
					),
				),
				'meta_data'  =>
				array(
					'description' => 'Metadaten.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'    =>
							array(
								'description' => 'Meta-ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'key'   =>
							array(
								'description' => 'Meta-Schlüssel.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'value' =>
							array(
								'description' => 'Meta-Wert.',
								'type'        =>
								array(
									0 => 'null',
									1 => 'object',
									2 => 'string',
									3 => 'number',
									4 => 'boolean',
									5 => 'integer',
									6 => 'array',
								),
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
						),
					),
				),
			),
		),
	),
	'coupon_lines'                    =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Coupon-Daten.',
		'type'              => 'array',
		'items'             =>
		array(
			'type'       => 'object',
			'properties' =>
			array(
				'id'             =>
				array(
					'description' => 'Bestellpositions-ID.',
					'type'        => 'integer',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'code'           =>
				array(
					'description' => 'Gutscheincode.',
					'type'        =>
					array(
						0 => 'null',
						1 => 'object',
						2 => 'string',
						3 => 'number',
						4 => 'boolean',
						5 => 'integer',
						6 => 'array',
					),
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
				),
				'discount'       =>
				array(
					'description' => 'Rabatt gesamt.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'discount_tax'   =>
				array(
					'description' => 'Rabatt Gesamtsteuer.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'readonly'    => true,
				),
				'discount_type'  =>
				array(
					'description' => 'Rabattart.',
					'type'        => 'string',
					'context'     =>
					array(
						0 => 'view',
					),
					'readonly'    => true,
				),
				'nominal_amount' =>
				array(
					'description' => 'Rabattbetrag, wie im Gutschein definiert (absoluter Wert oder Prozentsatz, je nach Rabattart).',
					'type'        => 'number',
					'context'     =>
					array(
						0 => 'view',
					),
					'readonly'    => true,
				),
				'free_shipping'  =>
				array(
					'description' => 'Ob der Gutschein kostenlosen Versand beinhaltet.',
					'type'        => 'boolean',
					'context'     =>
					array(
						0 => 'view',
					),
					'readonly'    => true,
				),
				'meta_data'      =>
				array(
					'description' => 'Metadaten.',
					'type'        => 'array',
					'context'     =>
					array(
						0 => 'view',
						1 => 'edit',
					),
					'items'       =>
					array(
						'type'       => 'object',
						'properties' =>
						array(
							'id'    =>
							array(
								'description' => 'Meta-ID.',
								'type'        => 'integer',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
								'readonly'    => true,
							),
							'key'   =>
							array(
								'description' => 'Meta-Schlüssel.',
								'type'        => 'string',
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
							'value' =>
							array(
								'description' => 'Meta-Wert.',
								'type'        =>
								array(
									0 => 'null',
									1 => 'object',
									2 => 'string',
									3 => 'number',
									4 => 'boolean',
									5 => 'integer',
									6 => 'array',
								),
								'context'     =>
								array(
									0 => 'view',
									1 => 'edit',
								),
							),
						),
					),
				),
			),
		),
	),
	'set_paid'                        =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'default'           => false,
		'description'       => 'Lege fest, ob die Bestellung bezahlt ist. Das setzt den Status auf „In Bearbeitung“ und reduziert den Lagerbestand entsprechend.',
		'type'              => 'boolean',
	),
	'pickup_location_code'            =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Abholstation Nummer',
		'type'              => 'string',
	),
	'pickup_location_customer_number' =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Abholstation Kundennummer',
		'type'              => 'string',
	),
	'direct_debit'                    =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Lastschrift',
		'type'              => 'object',
		'properties'        =>
		array(
			'holder'     =>
			array(
				'description' => 'Kontoinhaber',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'iban'       =>
			array(
				'description' => 'IBAN',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'bic'        =>
			array(
				'description' => 'BIC/SWIFT',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
			'mandate_id' =>
			array(
				'description' => 'Mandat-Referenznummer',
				'type'        => 'string',
				'context'     =>
				array(
					0 => 'view',
					1 => 'edit',
				),
			),
		),
	),
	'needs_confirmation'              =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'description'       => 'Ob eine Bestellung manuell bestätigt werden muss oder nicht.',
		'type'              => 'boolean',
	),
	'manual_update'                   =>
	array(
		'validate_callback' => 'rest_validate_request_arg',
		'sanitize_callback' => 'rest_sanitize_request_arg',
		'default'           => false,
		'description'       => 'Lege die Aktion auf manuell fest, damit der Bestellhinweis „added by user" (vom Benutzer hinzugefügt) registriert wird.',
		'type'              => 'boolean',
	),
);
