<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * What a token may not put into a Contact Form 7 form, each because the public
 * feedback route or the form's own HTML would then hand something to a visitor.
 *
 * - A mail attachment by path: CF7 reads it relative to wp-content, so a form
 *   attaching a log file would mail it to anyone who submits. A line starting with
 *   "[" is the visitor's own upload, and a path the form already has may stay, since
 *   templates are replaced whole and it would otherwise be lost on every edit. Only
 *   while it still goes where it went: a new recipient would receive that file.
 * - A default read from the logged-in visitor: `default:user_<anything>` calls
 *   $user->get() with no allowlist, so user_user_pass prints the password hash into
 *   the form. `default:post_meta` does the same with the embedding post's meta.
 * - IP or user agent as a Flamingo field: those settings run mail tags, which would
 *   copy what the Flamingo tools otherwise leave out into what they return. Only the
 *   form's own fields may be used, so no spelling of a special tag has to be known.
 */
final readonly class ContactFormPolicy implements ArgumentPolicy {

	private const MAIL_ARGUMENTS = [
		'mail'   => '_mail',
		'mail_2' => '_mail_2',
	];

	/** Every one of these decides who a mail reaches. */
	private const ADDRESSING = [ 'recipient', 'additional_headers' ];

	/** CF7's own grammar for a mail tag. */
	private const MAIL_TAG = '/\[[\t ]*([a-zA-Z_][0-9a-zA-Z:._-]*)/';

	/** The name after the type in [email* your-email ...]. A name starts with a letter. */
	private const FORM_FIELD = '/\[[\t ]*[a-zA-Z0-9_-]+\*?[\t ]+([a-zA-Z][0-9a-zA-Z:._-]*)/';

	private const FLAMINGO_SETTING = '/^flamingo_(?:email|name|subject)\s*:(.*)$/i';

	private const ALLOWED_USER_DEFAULTS = [
		'user_login',
		'user_email',
		'user_url',
		'user_first_name',
		'user_last_name',
		'user_nickname',
		'user_display_name',
	];

	public function verdict( array $arguments ): Verdict {
		foreach ( self::MAIL_ARGUMENTS as $name => $meta_key ) {
			$mail        = is_array( $arguments[ $name ] ?? null ) ? $arguments[ $name ] : [];
			$attachments = $mail['attachments'] ?? '';
			$id          = (int) ( $arguments['id'] ?? 0 );

			if ( ! $this->attaches_nothing_new( $attachments, $id, $meta_key ) ) {
				return Verdict::deny( 'A mail template may attach only files the visitor uploads, written as a form tag such as [your-file], and any path the form already attaches. Adding a file from the server by path is not allowed here; an administrator can set that up in Contact Form 7 directly.' );
			}

			if ( is_string( $attachments ) && [] !== self::server_paths( $attachments ) && ! $this->reaches_the_same_people( $mail, $id, $meta_key ) ) {
				return Verdict::deny( 'This mail attaches a file from the server, so its recipient and additional headers cannot change here: the new address would receive that file. Leave them as they are, or remove the attachment first.' );
			}
		}

		if ( is_string( $arguments['form'] ?? null ) && ! $this->reads_nothing_private( $arguments['form'] ) ) {
			return Verdict::deny( 'A form tag may take its default from the visitor only as user_login, user_email, user_url, user_first_name, user_last_name, user_nickname or user_display_name, and never from post_meta. Other defaults could print private account data into the form.' );
		}

		if ( is_string( $arguments['additional_settings'] ?? null ) && ! $this->flamingo_reads_only_the_form( $arguments ) ) {
			return Verdict::deny( 'flamingo_email, flamingo_name and flamingo_subject may use the form\'s own fields only, not special mail tags such as [_remote_ip] or [_user_agent].' );
		}

		return Verdict::allow();
	}

	private function attaches_nothing_new( mixed $attachments, int $id, string $meta_key ): bool {
		if ( ! is_string( $attachments ) ) {
			return false;
		}

		$paths = self::server_paths( $attachments );

		return [] === $paths || [] === array_diff( $paths, self::lines( (string) ( $this->stored_mail( $id, $meta_key )['attachments'] ?? '' ) ) );
	}

	/**
	 * CF7 stores an omitted key as empty, and a mail with no recipient reaches nobody.
	 *
	 * @param array<mixed> $mail
	 */
	private function reaches_the_same_people( array $mail, int $id, string $meta_key ): bool {
		$stored = $this->stored_mail( $id, $meta_key );

		foreach ( self::ADDRESSING as $key ) {
			if ( isset( $mail[ $key ] ) && ( ! is_string( $mail[ $key ] ) || self::lines( $mail[ $key ] ) !== self::lines( (string) ( $stored[ $key ] ?? '' ) ) ) ) {
				return false;
			}
		}

		return true;
	}

	/** @return array<mixed> */
	private function stored_mail( int $id, string $meta_key ): array {
		$mail = $id > 0 ? get_post_meta( $id, $meta_key, true ) : null;

		return is_array( $mail ) ? $mail : [];
	}

	/** @return list<string> */
	private static function server_paths( string $attachments ): array {
		return array_values( array_filter( self::lines( $attachments ), static fn ( string $line ): bool => ! str_starts_with( $line, '[' ) ) );
	}

	/** @param array<string, mixed> $arguments */
	private function flamingo_reads_only_the_form( array $arguments ): bool {
		$fields = null;

		foreach ( self::lines( $arguments['additional_settings'] ) as $line ) {
			if ( 1 !== preg_match( self::FLAMINGO_SETTING, $line, $setting ) || 0 === preg_match_all( self::MAIL_TAG, $setting[1], $tags ) ) {
				continue;
			}

			$fields ??= $this->form_fields( $arguments );

			if ( [] !== array_diff( $tags[1], $fields ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return list<string>
	 */
	private function form_fields( array $arguments ): array {
		$id   = (int) ( $arguments['id'] ?? 0 );
		$form = $arguments['form'] ?? ( $id > 0 ? get_post_meta( $id, '_form', true ) : '' );

		preg_match_all( self::FORM_FIELD, is_string( $form ) ? $form : '', $matches );

		return $matches[1];
	}

	/**
	 * Every line ending CF7 normalizes on save: a bare \r read as one line here would
	 * become a second line, and a file path, in what it stores.
	 *
	 * @return list<string>
	 */
	private static function lines( string $text ): array {
		return array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $text ) ), static fn ( string $line ): bool => '' !== $line ) );
	}

	private function reads_nothing_private( string $form ): bool {
		preg_match_all( '/\bdefault:([^\s\]]+)/i', $form, $matches );

		foreach ( $matches[1] as $option ) {
			// The way CF7 reads the option, so no spelling gets past this that would not get past it.
			$normalized = (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $option ) );

			if ( 'post_meta' === $normalized || ( str_starts_with( $normalized, 'user_' ) && ! in_array( $normalized, self::ALLOWED_USER_DEFAULTS, true ) ) ) {
				return false;
			}
		}

		return true;
	}
}
