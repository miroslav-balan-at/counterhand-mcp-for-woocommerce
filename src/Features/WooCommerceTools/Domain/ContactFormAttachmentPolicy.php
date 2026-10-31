<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses mail templates that attach a file by path.
 *
 * Contact Form 7 reads such a line relative to wp-content, and its feedback route
 * is public: a form attaching a log file, mailed to an outside address, would hand
 * that file to anyone who submits the form. A line starting with "[" names a file
 * the visitor uploaded, which is the normal use and stays allowed.
 */
final readonly class ContactFormAttachmentPolicy implements ArgumentPolicy {

	private const MAIL_ARGUMENTS = [ 'mail', 'mail_2' ];

	public function verdict( array $arguments ): Verdict {
		foreach ( self::MAIL_ARGUMENTS as $name ) {
			$attachments = is_array( $arguments[ $name ] ?? null ) ? ( $arguments[ $name ]['attachments'] ?? '' ) : '';

			if ( ! $this->names_only_uploads( $attachments ) ) {
				return Verdict::deny( 'A mail template may attach only files the visitor uploads, written as a form tag such as [your-file]. Attaching a file from the server by path is not allowed here; an administrator can set that up in Contact Form 7 directly.' );
			}
		}

		return Verdict::allow();
	}

	private function names_only_uploads( mixed $attachments ): bool {
		if ( ! is_string( $attachments ) ) {
			return false;
		}

		// Every line ending CF7 normalizes on save: a bare \r read as one line here
		// would become a second line, and a file path, in what it stores.
		foreach ( (array) preg_split( '/\r\n|\r|\n/', $attachments ) as $line ) {
			$line = trim( (string) $line );

			if ( '' !== $line && ! str_starts_with( $line, '[' ) ) {
				return false;
			}
		}

		return true;
	}
}
