<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * ACF stores a field's value under the field's own name. A field group on products with
 * a field named _price would make these tools write the price, past products:write,
 * and the same for every other protected key.
 */
final readonly class AcfFieldNamePolicy implements ArgumentPolicy {

	public function verdict( array $arguments ): Verdict {
		foreach ( array_keys( is_array( $arguments['acf'] ?? null ) ? $arguments['acf'] : [] ) as $name ) {
			if ( str_starts_with( (string) $name, '_' ) ) {
				return Verdict::deny( sprintf( '"%s" is stored under a protected name, which this tool cannot write. An administrator can change it in the editor.', $name ) );
			}
		}

		return Verdict::allow();
	}
}
