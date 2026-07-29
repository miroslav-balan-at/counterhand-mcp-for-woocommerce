<?php
/**
 * Arguments WooCommerce registers for GET /wc/v3/orders/{order_id}/notes.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'order_id' => 
  array(
    'description' => 'Die Bestellnummer.',
    'type' => 'integer',
  ),
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
  'type' => 
  array(
    'default' => 'any',
    'description' => 'Ergebnis auf Kunden oder interne Hinweise beschränken.',
    'type' => 'string',
    'enum' => 
    array(
      0 => 'any',
      1 => 'customer',
      2 => 'internal',
    ),
    'sanitize_callback' => 'sanitize_key',
    'validate_callback' => 'rest_validate_request_arg',
  ),
);
