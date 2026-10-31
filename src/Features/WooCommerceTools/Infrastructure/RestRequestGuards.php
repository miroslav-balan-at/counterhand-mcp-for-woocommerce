<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Infrastructure;

defined( 'ABSPATH' ) || exit;

/**
 * Changes how another plugin answers, for one REST request only.
 *
 * Each guard lasts for a request carrying its marker, which a descriptor forces, so
 * that plugin's own REST API and every other caller keep their behaviour. A client
 * sending a marker itself can only get a stricter answer.
 */
final class RestRequestGuards {

	/** ACF returns password fields in plaintext to anyone who can read the post. */
	public const WITHHOLD_PASSWORDS = 'counterhand_withhold_passwords';

	/**
	 * An administrator holds unfiltered_html, so CF7 and ACF store what they are sent
	 * as written, and a token's script would reach every visitor. A person typing in
	 * wp-admin has that right; a model acting on a token should not inherit it.
	 */
	public const NO_UNFILTERED_HTML = 'counterhand_no_unfiltered_html';

	/**
	 * CF7's Brevo module resets a form's settings on every save that does not come
	 * from its own admin form, because it reads them from $_POST.
	 */
	public const KEEP_FORM_INTEGRATIONS = 'counterhand_keep_form_integrations';

	private const NESTED_PASSWORD_VALUE = 'acf/rest/format_value_for_rest/type=password';

	private const FORM_INTEGRATION_PROPERTIES = [ 'sendinblue' ];

	/** @var array<string, mixed> */
	private array $form_integrations = [];

	public function register(): void {
		add_filter( 'rest_request_before_callbacks', [ $this, 'before' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'after' ] );
	}

	/**
	 * @param  mixed            $response
	 * @param  array<mixed>     $handler
	 * @param  \WP_REST_Request $request
	 * @return mixed
	 */
	public function before( mixed $response, array $handler, \WP_REST_Request $request ): mixed {
		if ( null !== $request->get_param( self::WITHHOLD_PASSWORDS ) ) {
			add_filter( 'acf/rest/get_fields', [ $this, 'without_passwords' ] );
			add_filter( self::NESTED_PASSWORD_VALUE, '__return_null' );
		}

		if ( null !== $request->get_param( self::NO_UNFILTERED_HTML ) ) {
			add_filter( 'map_meta_cap', [ $this, 'deny_unfiltered_html' ], 10, 2 );
		}

		if ( null !== $request->get_param( self::KEEP_FORM_INTEGRATIONS ) ) {
			add_action( 'wpcf7_save_contact_form', [ $this, 'remember_form_integrations' ], 9 );
			add_action( 'wpcf7_save_contact_form', [ $this, 'restore_form_integrations' ], 11 );
		}

		return $response;
	}

	/**
	 * @param  mixed $response
	 * @return mixed
	 */
	public function after( mixed $response ): mixed {
		remove_filter( 'acf/rest/get_fields', [ $this, 'without_passwords' ] );
		remove_filter( self::NESTED_PASSWORD_VALUE, '__return_null' );
		remove_filter( 'map_meta_cap', [ $this, 'deny_unfiltered_html' ] );
		remove_action( 'wpcf7_save_contact_form', [ $this, 'remember_form_integrations' ], 9 );
		remove_action( 'wpcf7_save_contact_form', [ $this, 'restore_form_integrations' ], 11 );

		return $response;
	}

	/**
	 * @param  mixed $fields ACF's field list.
	 * @return mixed
	 */
	public function without_passwords( mixed $fields ): mixed {
		return is_array( $fields )
			? array_values( array_filter( $fields, static fn ( mixed $field ): bool => ! is_array( $field ) || 'password' !== ( $field['type'] ?? null ) ) )
			: $fields;
	}

	/**
	 * @param  array<int, string> $capabilities
	 * @return array<int, string>
	 */
	public function deny_unfiltered_html( array $capabilities, string $capability ): array {
		return 'unfiltered_html' === $capability ? [ 'do_not_allow' ] : $capabilities;
	}

	public function remember_form_integrations( \WPCF7_ContactForm $form ): void {
		$this->form_integrations = [];

		foreach ( self::FORM_INTEGRATION_PROPERTIES as $property ) {
			$this->form_integrations[ $property ] = $form->prop( $property );
		}
	}

	public function restore_form_integrations( \WPCF7_ContactForm $form ): void {
		$form->set_properties( array_filter( $this->form_integrations, static fn ( mixed $value ): bool => null !== $value ) );
	}
}
