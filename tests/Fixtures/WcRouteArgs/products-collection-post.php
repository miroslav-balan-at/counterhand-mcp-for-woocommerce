<?php
/**
 * Arguments WooCommerce registers for POST /wc/v3/products.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'name' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Produktname.',
    'type' => 'string',
  ),
  'slug' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Titelform des Produktes.',
    'type' => 'string',
  ),
  'date_created' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Das Datum, an dem das Produkt angelegt wurde (in der Zeitzone der Seite).',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'date_created_gmt' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Das Datum, an dem das Produkt erstellt wurde, als GMT.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'type' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'simple',
    'description' => 'Produkttyp.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'simple',
      1 => 'grouped',
      2 => 'external',
      3 => 'variable',
    ),
  ),
  'status' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'publish',
    'description' => 'Produktstatus (post status).',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'draft',
      1 => 'pending',
      2 => 'private',
      3 => 'publish',
      4 => 'future',
      5 => 'auto-draft',
      6 => 'trash',
    ),
  ),
  'featured' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Hervorgehobenes Produkt.',
    'type' => 'boolean',
  ),
  'catalog_visibility' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'visible',
    'description' => 'Sichtbarkeit im Katalog.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'visible',
      1 => 'catalog',
      2 => 'search',
      3 => 'hidden',
    ),
  ),
  'description' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Produktbeschreibung.',
    'type' => 'string',
  ),
  'short_description' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Kurzbeschreibung des Produkts.',
    'type' => 'string',
  ),
  'sku' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Artikelnummer.',
    'type' => 'string',
  ),
  'global_unique_id' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'GTIN, UPC, EAN oder ISBN.',
    'type' => 'string',
  ),
  'regular_price' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Regulärer Produktpreis.',
    'type' => 'string',
  ),
  'sale_price' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Produkt Angebotspreis.',
    'type' => 'string',
  ),
  'date_on_sale_from' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Startdatum des Angebotspreises, in der Zeitzone der Website.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'date_on_sale_from_gmt' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Startdatum des Angebotspreises, als GMT (Greenwich Mean Time).',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'date_on_sale_to' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Enddatum des Angebotspreises, in der Zeitzone der Website.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'date_on_sale_to_gmt' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Enddatum des Angebotspreises, in der Zeitzone der Website.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'virtual' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob das Produkt virtuell ist.',
    'type' => 'boolean',
  ),
  'downloadable' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob das Produkt herunterladbar ist.',
    'type' => 'boolean',
  ),
  'downloads' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der herunterladbaren Dateien.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Datei-ID.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Dateiname.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'file' => 
        array(
          'description' => 'Datei-URL.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
    ),
  ),
  'download_limit' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => -1,
    'description' => 'Wie oft die herunterladbaren Dateien nach dem Kauf heruntergeladen werden können.',
    'type' => 'integer',
  ),
  'download_expiry' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => -1,
    'description' => 'Anzahl der Tage, bis der Zugriff auf herunterladbare Dateien abläuft.',
    'type' => 'integer',
  ),
  'external_url' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Externe Produkt-URL. Nur für externe Produkte.',
    'type' => 'string',
    'format' => 'uri',
  ),
  'button_text' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Button-Text für externe Produkte. Nur relevant für externe Produkte.',
    'type' => 'string',
  ),
  'tax_status' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'taxable',
    'description' => 'Steuerstatus.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'taxable',
      1 => 'shipping',
      2 => 'none',
    ),
  ),
  'tax_class' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Steuerklasse.',
    'type' => 'string',
  ),
  'manage_stock' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Lagerverwaltung auf Produktebene.',
    'type' => 'boolean',
  ),
  'stock_quantity' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Lagerbestand.',
    'type' => 'integer',
  ),
  'stock_status' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'instock',
    'description' => 'Steuert den Lagerstatus des Produkts.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'instock',
      1 => 'outofstock',
      2 => 'onbackorder',
    ),
  ),
  'backorders' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'no',
    'description' => 'Falls die Lagerverwaltung aktiviert ist, können damit Lieferrückstände erlaubt werden.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'no',
      1 => 'notify',
      2 => 'yes',
    ),
  ),
  'low_stock_amount' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Geringe Lagermenge für dieses Produkt.',
    'type' => 
    array(
      0 => 'integer',
      1 => 'null',
    ),
  ),
  'sold_individually' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Den Kauf von nur maximal einem Stück pro Bestellung erlauben.',
    'type' => 'boolean',
  ),
  'weight' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Produktgewicht (kg).',
    'type' => 'string',
  ),
  'dimensions' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Abmessungen des Produkts.',
    'type' => 'object',
    'properties' => 
    array(
      'length' => 
      array(
        'description' => 'Produktlänge (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'width' => 
      array(
        'description' => 'Breite des Produkts (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'height' => 
      array(
        'description' => 'Höhe des Produkts (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'shipping_class' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Slug der Versandklasse.',
    'type' => 'string',
  ),
  'reviews_allowed' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => true,
    'description' => 'Kundenbewertungen zulassen.',
    'type' => 'boolean',
  ),
  'post_password' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Passwort für Beitrag.',
    'type' => 'string',
  ),
  'upsell_ids' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Produkt-IDs für Up-Selling.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'cross_sell_ids' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Produkt-IDs für Cross-Selling.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'parent_id' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Eltern-Produkt ID.',
    'type' => 'integer',
  ),
  'purchase_note' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Optionale Anmerkungen, die dem Kunden nach dem Kauf mitgeteilt wird.',
    'type' => 'string',
  ),
  'categories' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Kategorie-Liste.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Kategorie-ID.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Kategoriename.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'slug' => 
        array(
          'description' => 'Kategorie-Slug.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
      ),
    ),
  ),
  'brands' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Marken.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Marken-ID.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Markenname.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'slug' => 
        array(
          'description' => 'Marken-Titelform.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
      ),
    ),
  ),
  'tags' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Schlagwort-Liste.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Tag-ID.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Schlagwort-Name.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'slug' => 
        array(
          'description' => 'Tag-Slug.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
      ),
    ),
  ),
  'images' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Bilder. ',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Bild-ID.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'date_created' => 
        array(
          'description' => 'Das Datum, an dem das Bild hinzugefügt wurde (in der Zeitzone der Seite).',
          'type' => 
          array(
            0 => 'null',
            1 => 'string',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'date_created_gmt' => 
        array(
          'description' => 'Das Datum, an dem das Bild erstellt wurde, als GMT.',
          'type' => 
          array(
            0 => 'null',
            1 => 'string',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'date_modified' => 
        array(
          'description' => 'Das Datum, an dem das Bild zuletzt geändert wurde (in der Zeitzone der Seite).',
          'type' => 
          array(
            0 => 'null',
            1 => 'string',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'date_modified_gmt' => 
        array(
          'description' => 'Das Datum, an dem das Bild zuletzt geändert wurde, als GMT.',
          'type' => 
          array(
            0 => 'null',
            1 => 'string',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'src' => 
        array(
          'description' => 'Bild URL.',
          'type' => 'string',
          'format' => 'uri',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Bildname.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'alt' => 
        array(
          'description' => 'Alternativer Bildname.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
    ),
  ),
  'attributes' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Attribute. ',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'ID des Attributs.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Name des Attributs.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'position' => 
        array(
          'description' => 'Position des Attributs.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'visible' => 
        array(
          'description' => 'Bestimmt ob das Attribut im Tab „Zusätzliche Informationen“ auf der Produkt-Seite zu sehen ist.',
          'type' => 'boolean',
          'default' => false,
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'variation' => 
        array(
          'description' => 'Definiert ob das Attribut in Varianten verwendet werden kann.',
          'type' => 'boolean',
          'default' => false,
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'options' => 
        array(
          'description' => 'Liste aller verfügbaren Begriffe des Attributs.',
          'type' => 'array',
          'items' => 
          array(
            'type' => 'string',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
    ),
  ),
  'default_attributes' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Standard-Varianten-Attribute.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'ID des Attributs.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Name des Attributs.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'option' => 
        array(
          'description' => 'Name des ausgewählten Attribut-Begriffs.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
    ),
  ),
  'menu_order' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Menüreihenfolge (zur individuellen Sortierung der Produkte).',
    'type' => 'integer',
  ),
  'meta_data' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Metadaten.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Meta-ID.',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'key' => 
        array(
          'description' => 'Meta-Schlüssel.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'value' => 
        array(
          'description' => 'Meta-Wert.',
          'type' => 
          array(
            0 => 'null',
            1 => 'object',
            2 => 'string',
            3 => 'number',
            4 => 'boolean',
            5 => 'integer',
            6 => 'array',
          ),
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
    ),
  ),
  'customs_description' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Zollbeschreibung',
    'type' => 'string',
  ),
  'hs_code' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Zolltarifnummer (HS-Code, Zoll)',
    'type' => 'string',
  ),
  'mid_code' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'MID (Zoll)',
    'type' => 'string',
  ),
  'manufacture_country' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Herstellungsland (Zoll)',
    'type' => 'string',
  ),
  'shipping_weight' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Versandgewicht (kg).',
    'type' => 'string',
  ),
  'shipping_dimensions' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Versandmaße des Produkts.',
    'type' => 'object',
    'properties' => 
    array(
      'length' => 
      array(
        'description' => 'Versandlänge (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'width' => 
      array(
        'description' => 'Versandbreite (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'height' => 
      array(
        'description' => 'Versandhöhe (cm).',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'delivery_time' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Lieferzeit',
    'type' => 'object',
    'properties' => 
    array(
      'id' => 
      array(
        'description' => 'Lieferzeit ID',
        'type' => 'integer',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'name' => 
      array(
        'description' => 'Lieferzeit Name',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'slug' => 
      array(
        'description' => 'Lieferzeit Slug',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'html' => 
      array(
        'description' => 'Lieferzeit HTML',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
    ),
  ),
  'country_specific_delivery_times' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Länderspezifische Lieferzeiten',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'object',
      'properties' => 
      array(
        'id' => 
        array(
          'description' => 'Lieferzeit ID',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'name' => 
        array(
          'description' => 'Lieferzeit Name',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
        'country' => 
        array(
          'description' => 'ISO-Code des Landes.',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'slug' => 
        array(
          'description' => 'Lieferzeit Slug',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
        'html' => 
        array(
          'description' => 'Lieferzeit HTML',
          'type' => 'string',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
          'readonly' => true,
        ),
      ),
    ),
  ),
  'sale_price_label' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Preishinweis',
    'type' => 'object',
    'properties' => 
    array(
      'id' => 
      array(
        'description' => 'Preishinweis ID',
        'type' => 'integer',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'name' => 
      array(
        'description' => 'Preishinweis Name',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'slug' => 
      array(
        'description' => 'Preishinweis Slug',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'sale_price_regular_label' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Preishinweis',
    'type' => 'object',
    'properties' => 
    array(
      'id' => 
      array(
        'description' => 'Preishinweis ID',
        'type' => 'integer',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'name' => 
      array(
        'description' => 'Preishinweis Name',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'slug' => 
      array(
        'description' => 'Preishinweis Slug',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'manufacturer' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Hersteller',
    'type' => 'object',
    'properties' => 
    array(
      'id' => 
      array(
        'description' => 'Hersteller ID',
        'type' => 'integer',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'name' => 
      array(
        'description' => 'Hersteller Name',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'slug' => 
      array(
        'description' => 'Hersteller Slug',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'unit' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Einheit',
    'type' => 'object',
    'properties' => 
    array(
      'id' => 
      array(
        'description' => 'Einheit ID',
        'type' => 'integer',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'name' => 
      array(
        'description' => 'Einheit Name',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'slug' => 
      array(
        'description' => 'Einheit Slug',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'unit_price' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Grundpreis',
    'type' => 'object',
    'properties' => 
    array(
      'base' => 
      array(
        'description' => 'Grundpreis-Basis',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'product' => 
      array(
        'description' => 'Grundpreis-Produkt',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'price_auto' => 
      array(
        'description' => 'Grundpreis automatische Berechnung',
        'type' => 'boolean',
        'default' => false,
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'price' => 
      array(
        'description' => 'Aktueller Grundpreis',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'price_regular' => 
      array(
        'description' => 'Regulärer Grundpreis',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'price_sale' => 
      array(
        'description' => 'Angebotsgrundpreis',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'price_html' => 
      array(
        'description' => 'Grundpreis HTML',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
    ),
  ),
  'mini_desc' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Warenkorbkurzbeschreibung',
    'type' => 'string',
  ),
  'defect_description' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Mängelbeschreibung',
    'type' => 'string',
  ),
  'free_shipping' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Deaktiviert den „zzgl. Versandkosten“ Hinweis',
    'type' => 'boolean',
  ),
  'safety_attachment_ids' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'IDs der Dokumente zur Produktsicherheit',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'safety_instructions' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Sicherheitshinweise',
    'type' => 'string',
  ),
  'min_age' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => '',
    'description' => 'Mindestalter der Altersprüfung.',
    'type' => 'string',
    'enum' => 
    array(
      0 => '',
      1 => '12',
      2 => '16',
      3 => '18',
      4 => '21',
      5 => '25',
    ),
  ),
  'warranty_attachment_id' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => '',
    'description' => 'Medien-ID der Garantie (PDF)',
    'type' => 'string',
  ),
  'gtin' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => '',
    'description' => 'GTIN',
    'type' => 'string',
  ),
  'mpn' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => '',
    'description' => 'MPN',
    'type' => 'string',
  ),
  'service' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob dieses Produkt eine Dienstleistung ist oder nicht',
    'type' => 'boolean',
  ),
  'used_good' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob es sich bei diesem Produkt um Gebrauchtware handelt oder nicht',
    'type' => 'boolean',
  ),
  'defective_copy' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob es sich bei diesem Produkt um ein Mängelexemplar handelt oder nicht',
    'type' => 'boolean',
  ),
  'wireless_electronic_device' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob dieses Produkt ein elektronisches Gerät (Funk) ist oder nicht.',
    'type' => 'boolean',
  ),
  'device_contains_power_supply' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob dieses elektronische Gerät ein Netzteil enthält oder nicht.',
    'type' => 'boolean',
  ),
  'device_charging_supports_usb_pd' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob diese elektronische Gerät das Schnellladeprotokoll USB Power Delivery unterstützt oder nicht.',
    'type' => 'boolean',
  ),
  'device_charging_watt_min' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Mindestleistung zum Laden des Geräts.',
    'type' => 'text',
  ),
  'device_charging_watt_max' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Leistung, die benötigt wird, um das Gerät mit maximaler Ladegeschwindigkeit aufzuladen.',
    'type' => 'text',
  ),
  'photovoltaic_system' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob dieses Produkt eine Photovoltaikanlage ist oder nicht',
    'type' => 'boolean',
  ),
  'differential_taxation' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob dieses Produkt der Differenzbesteuerung unterliegt oder nicht',
    'type' => 'boolean',
  ),
  'is_food' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Ob es sich bei diesem Produkt um ein Lebensmittel handelt oder nicht',
    'type' => 'boolean',
  ),
  'food' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Lebensmittel Eigenschaften',
    'type' => 'object',
    'properties' => 
    array(
      'deposit_type' => 
      array(
        'description' => 'Pfandtyp',
        'type' => 'object',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'properties' => 
        array(
          'id' => 
          array(
            'description' => 'Pfandtyp ID',
            'type' => 'integer',
            'context' => 
            array(
              0 => 'view',
              1 => 'edit',
            ),
          ),
          'name' => 
          array(
            'description' => 'Pfandtyp Bezeichnung',
            'type' => 'string',
            'context' => 
            array(
              0 => 'view',
              1 => 'edit',
            ),
            'readonly' => true,
          ),
          'slug' => 
          array(
            'description' => 'Pfandtyp Titelform',
            'type' => 'string',
            'context' => 
            array(
              0 => 'view',
              1 => 'edit',
            ),
          ),
        ),
      ),
      'deposit_quantity' => 
      array(
        'description' => 'Pfand Anzahl',
        'type' => 'integer',
        'default' => 1,
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'absint',
        ),
      ),
      'deposit' => 
      array(
        'description' => 'Pfandbetrag',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'nutrient_reference_value' => 
      array(
        'description' => 'Nährwert Referenzmenge',
        'type' => 'string',
        'enum' => 
        array(
          0 => '',
          1 => '100g',
          2 => '100ml',
        ),
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'sanitize_title',
        ),
      ),
      'nutri_score' => 
      array(
        'description' => 'Nutri-Score',
        'type' => 'string',
        'enum' => 
        array(
          0 => '',
          1 => 'a',
          2 => 'b',
          3 => 'c',
          4 => 'd',
          5 => 'e',
        ),
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'sanitize_title',
        ),
      ),
      'drained_weight' => 
      array(
        'description' => 'Abtropfgewicht',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'net_filling_quantity' => 
      array(
        'description' => 'Nettofüllmenge',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'alcohol_content' => 
      array(
        'description' => 'Alkoholgehalt',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'is_non_alcoholic' => 
      array(
        'description' => 'Alkoholfrei?',
        'type' => 'boolean',
        'default' => false,
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'distributor' => 
      array(
        'description' => 'Lebensmittelunternehmer',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'wp_filter_post_kses',
        ),
      ),
      'place_of_origin' => 
      array(
        'description' => 'Herkunftsort',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'wp_filter_post_kses',
        ),
      ),
      'description' => 
      array(
        'description' => 'Lebensmittelbezeichnung',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'wp_filter_post_kses',
        ),
      ),
      'nutrient_ids' => 
      array(
        'description' => 'Nährwerte term ids',
        'type' => 'array',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'items' => 
        array(
          'type' => 'object',
          'properties' => 
          array(
            'term' => 
            array(
              'description' => 'Nährwerte (Slug oder term_id)',
              'type' => 
              array(
                0 => 'string',
                1 => 'number',
              ),
              'context' => 
              array(
                0 => 'view',
                1 => 'edit',
              ),
            ),
            'value' => 
            array(
              'description' => 'Nährwert',
              'type' => 'number',
              'context' => 
              array(
                0 => 'view',
                1 => 'edit',
              ),
            ),
            'ref_value' => 
            array(
              'description' => 'Nährwert Referenzmenge (für Vitamine und Mineralstoffe)',
              'type' => 'number',
              'context' => 
              array(
                0 => 'view',
                1 => 'edit',
              ),
            ),
          ),
        ),
      ),
      'allergen_ids' => 
      array(
        'description' => 'Allergene ids',
        'type' => 'array',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'items' => 
        array(
          'description' => 'Allergen term id',
          'type' => 'integer',
          'context' => 
          array(
            0 => 'view',
            1 => 'edit',
          ),
        ),
      ),
      'ingredients' => 
      array(
        'description' => 'Zutaten',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'arg_options' => 
        array(
          'sanitize_callback' => 'wp_filter_post_kses',
        ),
      ),
    ),
  ),
);
