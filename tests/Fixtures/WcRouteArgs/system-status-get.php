<?php
/**
 * Arguments WooCommerce registers for GET /wc/v3/system_status.
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
    ),
    'default' => 'view',
  ),
);
