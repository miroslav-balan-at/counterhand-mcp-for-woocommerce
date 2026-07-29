<?php
/**
 * Arguments WooCommerce registers for DELETE /wc/v3/coupons/{id}.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'id' => 
  array(
    'description' => 'Eindeutige Kennung für die Ressource.',
    'type' => 'integer',
  ),
  'force' => 
  array(
    'default' => false,
    'type' => 'boolean',
    'description' => 'Ob der Papierkorb übersprungen und unwiderruflich gelöscht werden soll.',
  ),
);
