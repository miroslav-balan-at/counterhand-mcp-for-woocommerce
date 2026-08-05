<?php
/**
 * Arguments WooCommerce registers for GET /wc/v3/coupons.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
	'context'         =>
	array(
		'description'       => 'Geltungsbereich der Anfrage; ermittelt in der Antwort vorhandene Felder.',
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_key',
		'validate_callback' => 'rest_validate_request_arg',
		'enum'              =>
		array(
			0 => 'view',
			1 => 'edit',
		),
		'default'           => 'view',
	),
	'page'            =>
	array(
		'description'       => 'Aktuelle Seite der Sammlung.',
		'type'              => 'integer',
		'default'           => 1,
		'sanitize_callback' => 'absint',
		'validate_callback' => 'rest_validate_request_arg',
		'minimum'           => 1,
	),
	'per_page'        =>
	array(
		'description'       => 'Maximale Anzahl an Einträgen, die in einer Ergebnismenge ausgegeben werden. ',
		'type'              => 'integer',
		'default'           => 10,
		'minimum'           => 1,
		'maximum'           => 100,
		'sanitize_callback' => 'absint',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'search'          =>
	array(
		'description'       => 'Zeigt nur die Ergebnisse an, die zu einer Zeichenkette passen. ',
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'after'           =>
	array(
		'description'       => 'Limitiert die Antwort auf Ressourcen welche nach einem bestimmten Datum (nach ISO8601-Standard) veröffentlicht wurden.',
		'type'              => 'string',
		'format'            => 'date-time',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'before'          =>
	array(
		'description'       => 'Limitiert die Antwort auf Ressourcen welche vor einem bestimmten Datum (nach ISO8601-Standard) veröffentlicht wurden.',
		'type'              => 'string',
		'format'            => 'date-time',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'modified_after'  =>
	array(
		'description'       => 'Antwort auf Ressourcen begrenzen, die nach einem bestimmten Datum (nach ISO8601-Standard) geändert wurden',
		'type'              => 'string',
		'format'            => 'date-time',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'modified_before' =>
	array(
		'description'       => 'Antwort auf Ressourcen begrenzen, die vor einem bestimmten Datum (nach ISO8601-Standard) geändert wurden',
		'type'              => 'string',
		'format'            => 'date-time',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'dates_are_gmt'   =>
	array(
		'description'       => 'Ob das GMT-Beitragsdatum berücksichtigt werden soll, wenn die Antwort durch das Veröffentlichungs- oder Änderungsdatum eingeschränkt wird',
		'type'              => 'boolean',
		'default'           => false,
		'validate_callback' => 'rest_validate_request_arg',
	),
	'exclude'         =>
	array(
		'description'       => 'Sicherstellen, dass das Ergebnis bestimmte IDs ausschließt.',
		'type'              => 'array',
		'items'             =>
		array(
			'type' => 'integer',
		),
		'default'           =>
		array(),
		'sanitize_callback' => 'wp_parse_id_list',
	),
	'include'         =>
	array(
		'description'       => 'Beschränkt das Ergebnis auf bestimmte IDs.',
		'type'              => 'array',
		'items'             =>
		array(
			'type' => 'integer',
		),
		'default'           =>
		array(),
		'sanitize_callback' => 'wp_parse_id_list',
	),
	'offset'          =>
	array(
		'description'       => 'Versieht die Ergebnismenge mit einem Offset.',
		'type'              => 'integer',
		'sanitize_callback' => 'absint',
		'validate_callback' => 'rest_validate_request_arg',
	),
	'order'           =>
	array(
		'description'       => 'Sortiert Attribute aufsteigend oder absteigend.',
		'type'              => 'string',
		'default'           => 'desc',
		'enum'              =>
		array(
			0 => 'asc',
			1 => 'desc',
		),
		'validate_callback' => 'rest_validate_request_arg',
	),
	'orderby'         =>
	array(
		'description'       => 'Sortiere Liste nach Objektattribut.',
		'type'              => 'string',
		'default'           => 'date',
		'enum'              =>
		array(
			0 => 'date',
			1 => 'id',
			2 => 'include',
			3 => 'title',
			4 => 'slug',
			5 => 'modified',
		),
		'validate_callback' => 'rest_validate_request_arg',
	),
	'code'            =>
	array(
		'description'       => 'Beschränkt Ergebnismenge auf Ressourcen mit einem bestimmten Code. ',
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'validate_callback' => 'rest_validate_request_arg',
	),
);
