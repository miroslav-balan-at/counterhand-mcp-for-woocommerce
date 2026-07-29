<?php
/**
 * Arguments WooCommerce registers for POST /wc/v3/customers.
 *
 * Captured from WooCommerce 10.9.4 on WordPress 7.0.1.
 * Callables are replaced with marker strings; the schema whitelist drops those
 * keys either way.
 *
 * Generated — do not hand-edit. See regenerate.php in this directory.
 */

return array(
  'email' => 
  array(
    'required' => true,
    'type' => 'string',
    'description' => 'Neue Benutzer-E-Mail-Adresse.',
  ),
  'first_name' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'sanitize_text_field',
    'description' => 'Vorname des Kunden.',
    'type' => 'string',
  ),
  'last_name' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'sanitize_text_field',
    'description' => 'Nachname Kunde.',
    'type' => 'string',
  ),
  'username' => 
  array(
    'required' => false,
    'description' => 'Neuer Benutzername.',
    'type' => 'string',
  ),
  'password' => 
  array(
    'required' => true,
    'description' => 'Neues Benutzer-Passwort.',
    'type' => 'string',
  ),
  'billing' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Rechnungsadress-Daten.',
    'type' => 'object',
    'properties' => 
    array(
      'first_name' => 
      array(
        'description' => 'Vorname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'last_name' => 
      array(
        'description' => 'Nachname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'company' => 
      array(
        'description' => 'Firmenname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'address_1' => 
      array(
        'description' => 'Adresszeile 1',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'address_2' => 
      array(
        'description' => 'Adresszeile 2',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'city' => 
      array(
        'description' => 'Name der Stadt.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'state' => 
      array(
        'description' => 'ISO-Code oder Name des Staats, der Provinz oder des Distrikts.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'postcode' => 
      array(
        'description' => 'Postleitzahl.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'country' => 
      array(
        'description' => 'ISO-Ländercode.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'email' => 
      array(
        'description' => 'E-Mail-Adresse.',
        'type' => 'string',
        'format' => 'email',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'phone' => 
      array(
        'description' => 'Telefonnummer.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'title' => 
      array(
        'description' => 'Anrede',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'title_formatted' => 
      array(
        'description' => 'Formatierter Titel',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
        'readonly' => true,
      ),
      'vat_id' => 
      array(
        'description' => 'USt.-ID',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
  'shipping' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Liste der Versandadressen.',
    'type' => 'object',
    'properties' => 
    array(
      'first_name' => 
      array(
        'description' => 'Vorname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'last_name' => 
      array(
        'description' => 'Nachname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'company' => 
      array(
        'description' => 'Firmenname.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'address_1' => 
      array(
        'description' => 'Adresszeile 1',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'address_2' => 
      array(
        'description' => 'Adresszeile 2',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'city' => 
      array(
        'description' => 'Name der Stadt.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'state' => 
      array(
        'description' => 'ISO-Code oder Name des Staats, der Provinz oder des Distrikts.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'postcode' => 
      array(
        'description' => 'Postleitzahl.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'country' => 
      array(
        'description' => 'ISO-Ländercode.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'phone' => 
      array(
        'description' => 'Telefonnummer.',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'title' => 
      array(
        'description' => 'Anrede',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'title_formatted' => 
      array(
        'description' => 'Formatierter Titel',
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
  'direct_debit' => 
  array(
    'validate_callback' => 'rest_validate_request_arg',
    'sanitize_callback' => 'rest_sanitize_request_arg',
    'description' => 'Lastschrift',
    'type' => 'object',
    'properties' => 
    array(
      'holder' => 
      array(
        'description' => 'Kontoinhaber',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'iban' => 
      array(
        'description' => 'IBAN',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
      'bic' => 
      array(
        'description' => 'BIC/SWIFT',
        'type' => 'string',
        'context' => 
        array(
          0 => 'view',
          1 => 'edit',
        ),
      ),
    ),
  ),
);
