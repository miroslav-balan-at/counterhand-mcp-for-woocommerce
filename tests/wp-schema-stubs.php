<?php
/**
 * WordPress functions the schema derivation calls, for the unit suite.
 *
 * Brain Monkey can fake a function's return value, but this one is not a
 * collaborator to be faked — SchemaFromArgs delegates to it precisely because
 * it is core's own definition of what a published schema may contain. A test
 * that stubbed it with a made-up list would prove nothing about the real
 * behaviour, so this is a faithful copy instead, verbatim from
 * wp-includes/rest-api.php.
 *
 * Guarded by function_exists() so it stays inert if the suite is ever run with
 * WordPress loaded.
 *
 * Copied from WordPress 6.9.1. If core adds a keyword, this list goes stale and
 * the tests keep passing against the old one — worth re-checking on a WordPress
 * major, though nothing breaks either way: production reads the real function.
 */

declare( strict_types=1 );

if ( ! function_exists( 'rest_get_allowed_schema_keywords' ) ) {
	/**
	 * Returns a list of allowed schema keywords.
	 *
	 * @return string[]
	 */
	function rest_get_allowed_schema_keywords(): array {
		return array(
			'title',
			'description',
			'default',
			'type',
			'format',
			'enum',
			'items',
			'properties',
			'additionalProperties',
			'patternProperties',
			'minProperties',
			'maxProperties',
			'minimum',
			'maximum',
			'exclusiveMinimum',
			'exclusiveMaximum',
			'multipleOf',
			'minLength',
			'maxLength',
			'pattern',
			'minItems',
			'maxItems',
			'uniqueItems',
			'anyOf',
			'oneOf',
		);
	}
}

/**
 * Faithful copy of core's protected-meta rule (wp-includes/meta.php).
 *
 * MetaKeyPolicy calls the real function so a store that filters it is honoured;
 * the unit suite has no WordPress, so it gets this. Kept literal, including the
 * filter call, so a test can exercise the filtered path exactly as a store would.
 */
if ( ! function_exists( 'is_protected_meta' ) ) {
	function is_protected_meta( $meta_key, $meta_type = '' ) {
		$sanitized_key = preg_replace( "/[^\x20-\x7E\p{L}]/", '', $meta_key );
		$protected     = strlen( $sanitized_key ) > 0 && ( '_' === $sanitized_key[0] );

		return apply_filters( 'is_protected_meta', $protected, $meta_key, $meta_type );
	}
}

/**
 * Faithful copy of core's serialized-data detector (wp-includes/functions.php).
 *
 * The policy rejects serialized values outright, so this has to agree with core
 * about what serialized looks like — an approximation here would let the tests
 * pass on payloads the real function classes differently.
 */
if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( ! str_contains( $data, '"' ) ) {
					return false;
				}
				// Or else fall through.
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}

		return false;
	}
}
