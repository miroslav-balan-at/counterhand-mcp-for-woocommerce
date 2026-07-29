<?php
/**
 * Arguments WooCommerce registers for POST /wc/v3/coupons.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'code' => 
  array(
    'description' => 'Gutscheincode.',
    'required' => true,
    'type' => 'string',
  ),
  'amount' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Die Höhe des Rabatts (Rabattbetrag). Sollte immer numerisch sein, auch wenn es ein Prozentsatz ist.',
    'type' => 
    array(
      0 => 'number',
      1 => 'string',
    ),
  ),
  'status' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Der Status des Gutscheins. Sollte immer „Entwurf“, „veröffentlicht“ oder „Überprüfung ausstehend“ lauten',
    'type' => 'string',
  ),
  'discount_type' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => 'fixed_cart',
    'description' => 'Bestimmt die Rabattart, die angewendet wird.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'percent',
      1 => 'fixed_cart',
      2 => 'fixed_product',
    ),
  ),
  'description' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Beschreibung des Gutscheins.',
    'type' => 'string',
  ),
  'date_expires' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Das Datum, an dem der Gutschein abläuft, in der Zeitzone der Website.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'date_expires_gmt' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Das Datum, an dem der Gutschein abläuft, als GMT.',
    'type' => 
    array(
      0 => 'null',
      1 => 'string',
    ),
  ),
  'individual_use' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Wenn aktiv kann dieser Gutschein nur einzeln benutzt werden. Andere angewendete Gutscheine werden vom Warenkorb entfernt.',
    'type' => 'boolean',
  ),
  'product_ids' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Produkt-IDs, für die der Gutschein benutzt werden kann.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'excluded_product_ids' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Produkt-IDs, auf die der Gutschein nicht angewendet werden kann.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'usage_limit' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Wie oft der Gutschein insgesamt benutzt werden kann.',
    'type' => 'integer',
  ),
  'usage_limit_per_user' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Wie oft der Gutschein pro Kunde verwendet werden kann.',
    'type' => 'integer',
  ),
  'limit_usage_to_x_items' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Maximale Anzahl der Artikel auf die der Gutschein angewendet werden kann.',
    'type' => 'integer',
  ),
  'free_shipping' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Wenn aktiv und die kostenlose Versandmethode einen Gutschein benötigt, wird dieser Gutschein kostenlosen Versand aktivieren.',
    'type' => 'boolean',
  ),
  'product_categories' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste von Kategorie-IDs, auf die der Gutschein zutrifft.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'excluded_product_categories' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste von Kategorie-IDs, auf die der Gutschein nicht zutrifft.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'integer',
    ),
  ),
  'exclude_sale_items' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'default' => false,
    'description' => 'Wenn dies der Fall ist, wird dieser Gutschein nicht auf Produkte/Artikel mit Angebotspreisen (Sale) angewendet.',
    'type' => 'boolean',
  ),
  'minimum_amount' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Minimale Bestellmenge, die im Warenkorb enthalten sein muss, damit der Gutschein verwendet werden kann. ',
    'type' => 
    array(
      0 => 'number',
      1 => 'string',
    ),
  ),
  'maximum_amount' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Maximaler Bestellwert bei der dieser Gutschein noch benutzt werden darf.',
    'type' => 
    array(
      0 => 'number',
      1 => 'string',
    ),
  ),
  'email_restrictions' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der E-Mail-Adressen, mit denen dieser Gutschein benutzt werden kann.',
    'type' => 'array',
    'items' => 
    array(
      'type' => 'string',
    ),
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
);
