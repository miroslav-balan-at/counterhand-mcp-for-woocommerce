<?php
/**
 * Arguments WooCommerce registers for GET /wc/v3/products.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'context' => 
  array(
    'description' => 'Geltungsbereich der Anfrage; ermittelt in der Antwort vorhandene Felder.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_key',
    'validate_callback' => 'rest_validate_request_arg',
    'enum' => 
    array(
      0 => 'view',
      1 => 'edit',
    ),
    'default' => 'view',
  ),
  'page' => 
  array(
    'description' => 'Aktuelle Seite der Sammlung.',
    'type' => 'integer',
    'default' => 1,
    'sanitize_callback' => 'absint',
    'validate_callback' => 'rest_validate_request_arg',
    'minimum' => 1,
  ),
  'per_page' => 
  array(
    'description' => 'Maximale Anzahl an Einträgen, die in einer Ergebnismenge ausgegeben werden. ',
    'type' => 'integer',
    'default' => 10,
    'minimum' => 1,
    'maximum' => 100,
    'sanitize_callback' => 'absint',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'search' => 
  array(
    'description' => 'Zeigt nur die Ergebnisse an, die zu einer Zeichenkette passen. ',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'after' => 
  array(
    'description' => 'Limitiert die Antwort auf Ressourcen welche nach einem bestimmten Datum (nach ISO8601-Standard) veröffentlicht wurden.',
    'type' => 'string',
    'format' => 'date-time',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'before' => 
  array(
    'description' => 'Limitiert die Antwort auf Ressourcen welche vor einem bestimmten Datum (nach ISO8601-Standard) veröffentlicht wurden.',
    'type' => 'string',
    'format' => 'date-time',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'modified_after' => 
  array(
    'description' => 'Antwort auf Ressourcen begrenzen, die nach einem bestimmten Datum (nach ISO8601-Standard) geändert wurden',
    'type' => 'string',
    'format' => 'date-time',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'modified_before' => 
  array(
    'description' => 'Antwort auf Ressourcen begrenzen, die vor einem bestimmten Datum (nach ISO8601-Standard) geändert wurden',
    'type' => 'string',
    'format' => 'date-time',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'dates_are_gmt' => 
  array(
    'description' => 'Ob das GMT-Beitragsdatum berücksichtigt werden soll, wenn die Antwort durch das Veröffentlichungs- oder Änderungsdatum eingeschränkt wird',
    'type' => 'boolean',
    'default' => false,
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'exclude' => 
  array(
    'description' => 'Sicherstellen, dass das Ergebnis bestimmte IDs ausschließt.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
    'default' => 
    array(
    ),
    'sanitize_callback' => 'wp_parse_id_list',
  ),
  'include' => 
  array(
    'description' => 'Beschränkt das Ergebnis auf bestimmte IDs.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
    'default' => 
    array(
    ),
    'sanitize_callback' => 'wp_parse_id_list',
  ),
  'offset' => 
  array(
    'description' => 'Versieht die Ergebnismenge mit einem Offset.',
    'type' => 'integer',
    'sanitize_callback' => 'absint',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'order' => 
  array(
    'description' => 'Sortiert Attribute aufsteigend oder absteigend.',
    'type' => 'string',
    'default' => 'desc',
    'enum' => 
    array(
      0 => 'asc',
      1 => 'desc',
    ),
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'orderby' => 
  array(
    'description' => 'Sortiere Liste nach Objektattribut.',
    'type' => 'string',
    'default' => 'date',
    'enum' => 
    array(
      0 => 'date',
      1 => 'id',
      2 => 'include',
      3 => 'title',
      4 => 'slug',
      5 => 'modified',
      6 => 'popularity',
      7 => 'rating',
      8 => 'post__in',
      9 => 'price',
      10 => 'sales',
      11 => 'menu_order',
      12 => 'random',
      13 => 'popularity',
      14 => 'rating',
      15 => 'menu_order',
      16 => 'price',
      17 => 'popularity',
      18 => 'rating',
    ),
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'parent' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf bestimmte Eltern-IDs. ',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
    'sanitize_callback' => 'wp_parse_id_list',
    'default' => 
    array(
    ),
  ),
  'parent_exclude' => 
  array(
    'description' => 'Ergebnissatz auf alle Elemente begrenzen, außer denen mit einer bestimmten Eltern-ID.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
    'sanitize_callback' => 'wp_parse_id_list',
    'default' => 
    array(
    ),
  ),
  'brand' => 
  array(
    'description' => 'Beschränke die Ergebnismenge auf Produkte, die einer bestimmten Marken-ID zugewiesen sind.',
    'type' => 'string',
    'sanitize_callback' => 'wp_parse_id_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'slug' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte mit einem bestimmten Slug.',
    'type' => 'string',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'status' => 
  array(
    'default' => 'any',
    'description' => 'Beschränkt Ergebnismenge auf Produkte, denen einen bestimmter Status zugeordnet wird.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'any',
      1 => 'future',
      2 => 'trash',
      3 => 'draft',
      4 => 'pending',
      5 => 'private',
      6 => 'publish',
    ),
    'sanitize_callback' => 'sanitize_key',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'type' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte eines bestimmten Typs.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'simple',
      1 => 'grouped',
      2 => 'external',
      3 => 'variable',
    ),
    'sanitize_callback' => 'sanitize_key',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'sku' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte mit bestimmten Artikelnummern. Verwende Kommas zum Trennen.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'featured' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf hervorgehobene Produkte.',
    'type' => 'boolean',
    'sanitize_callback' => 'wc_string_to_bool',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'category' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte einer bestimmten Kategorie-ID.',
    'type' => 'string',
    'sanitize_callback' => 'wp_parse_id_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'tag' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte, die einer bestimmten Tag-ID zugewiesen sind.',
    'type' => 'string',
    'sanitize_callback' => 'wp_parse_id_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'shipping_class' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte, denen eine bestimmte Versandklasse zugeordnet ist.',
    'type' => 'string',
    'sanitize_callback' => 'wp_parse_id_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'attribute' => 
  array(
    'description' => 'Ergebnismenge auf Produkte mit einem bestimmten Attribut begrenzen. Taxonomienamen/Attribut-Titelform nutzen.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'attribute_term' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte mit einem bestimmten Attributsbegriff (benötigt ein zugewiesenes Attribut). ',
    'type' => 'string',
    'sanitize_callback' => 'wp_parse_id_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'tax_class' => 
  array(
    'description' => 'Ergebnis auf Produkte mit einer bestimmten Steuerklasse einschränken.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'standard',
      1 => '0-preis',
      2 => 'reduced-rate',
      3 => 'reduzierter-preis',
      4 => 'steuerfreie',
      5 => 'virtual-rate',
      6 => 'virtual-reduced-rate',
      7 => 'zero-rate',
    ),
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'on_sale' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte im Angebot.',
    'type' => 'boolean',
    'sanitize_callback' => 'wc_string_to_bool',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'min_price' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte basierend auf dem Minimumpreis.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'max_price' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte basierend auf dem Höchstpreis.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'include_meta' => 
  array(
    'default' => 
    array(
    ),
    'description' => 'Begrenze „meta_data“ auf bestimmte Schlüssel.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
    ),
    'sanitize_callback' => 'wp_parse_list',
  ),
  'exclude_meta' => 
  array(
    'default' => 
    array(
    ),
    'description' => 'Stelle sicher, dass „meta_data“ bestimmte Schlüssel ausschließt.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
    ),
    'sanitize_callback' => 'wp_parse_list',
  ),
  'stock_status' => 
  array(
    'description' => 'Ergebnismenge auf Produkte mit einem bestimmten Lagerstatus beschränken.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'instock',
      1 => 'outofstock',
      2 => 'onbackorder',
    ),
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'search_sku' => 
  array(
    'description' => 'Schränke die Ergebnisse auf diejenigen mit einer Artikelnummer ein, die teilweise mit einer Zeichenfolge übereinstimmt. Dieses Argument hat Vorrang vor „sku“.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'search_name_or_sku' => 
  array(
    'description' => 'Schränke die Ergebnisse auf diejenigen mit einem Namen oder einer Artikelnummer ein, die teilweise mit einer Zeichenfolge übereinstimmen. Dieses Argument hat Vorrang vor „search“, „sku“ und „search_sku“.',
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'search_fields' => 
  array(
    'description' => 'Suche bei Verwendung mit Suchparameter auf bestimmte Felder begrenzen. Verfügbare Felder: name, sku, global_unique_id, description, short_description. Dieses Argument hat Vorrang vor allen anderen Suchparametern.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
      'enum' => 
      array(
        0 => 'name',
        1 => 'global_unique_id',
        2 => 'description',
        3 => 'short_description',
        4 => 'sku',
      ),
    ),
    'default' => 
    array(
    ),
    'sanitize_callback' => 'wp_parse_slug_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'include_status' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte mit einem der Status.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
      'enum' => 
      array(
        0 => 'any',
        1 => 'future',
        2 => 'trash',
        3 => 'draft',
        4 => 'pending',
        5 => 'private',
        6 => 'publish',
      ),
    ),
    'sanitize_callback' => 'wp_parse_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'exclude_status' => 
  array(
    'description' => 'Schließt Produkte mit einem der Status aus der Ergebnismenge aus.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
      'enum' => 
      array(
        0 => 'future',
        1 => 'trash',
        2 => 'draft',
        3 => 'pending',
        4 => 'private',
        5 => 'publish',
      ),
    ),
    'sanitize_callback' => 'wp_parse_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'include_types' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf Produkte mit einem der Typen.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
      'enum' => 
      array(
        0 => 'simple',
        1 => 'grouped',
        2 => 'external',
        3 => 'variable',
      ),
    ),
    'sanitize_callback' => 'wp_parse_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'exclude_types' => 
  array(
    'description' => 'Schließt Produkte mit einem der Typen aus der Ergebnismenge aus.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
      'enum' => 
      array(
        0 => 'simple',
        1 => 'grouped',
        2 => 'external',
        3 => 'variable',
      ),
    ),
    'sanitize_callback' => 'wp_parse_list',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'downloadable' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf herunterladbare Produkte.',
    'type' => 'boolean',
    'sanitize_callback' => 'rest_sanitize_boolean',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'virtual' => 
  array(
    'description' => 'Beschränkt Ergebnismenge auf virtuelle Produkte.',
    'type' => 'boolean',
    'sanitize_callback' => 'rest_sanitize_boolean',
    'validate_callback' => 'rest_validate_request_arg',
  ),
  'pos_products_only' => 
  array(
    'description' => 'Beschränkt die Ergebnismenge auf Produkte, die im Kassensystem sichtbar sind.',
    'type' => 'boolean',
    'sanitize_callback' => 'wc_string_to_bool',
    'validate_callback' => 'rest_validate_request_arg',
  ),
);
