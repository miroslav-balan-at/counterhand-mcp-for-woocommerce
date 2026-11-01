<?php

declare( strict_types=1 );

namespace Counterhand\Features\McpServer;

defined( 'ABSPATH' ) || exit;

final readonly class PromptDefinition {

	/**
	 * @param list<PromptArgument> $arguments
	 * @param string               $template  What the person asks, with {name} for each argument.
	 */
	public function __construct(
		public string $name,
		public string $title,
		public string $description,
		public array $arguments,
		public string $template,
	) {}

	/**
	 * Why these values cannot be used, or null when they can.
	 *
	 * @param array<mixed> $values
	 */
	public function refusal( array $values ): ?string {
		foreach ( $this->arguments as $argument ) {
			$value = $values[ $argument->name ] ?? null;

			if ( null === $value || '' === $value ) {
				if ( $argument->required ) {
					return sprintf( 'Missing argument "%s".', $argument->name );
				}

				continue;
			}

			if ( ! is_string( $value ) || 1 !== preg_match( $argument->pattern, $value ) ) {
				return sprintf( 'Argument "%s" is not valid. %s', $argument->name, $argument->description );
			}
		}

		return null;
	}

	/** @param array<mixed> $values Already passed refusal(). */
	public function message( array $values ): string {
		$text = $this->template;

		foreach ( $this->arguments as $argument ) {
			$value = $values[ $argument->name ] ?? '';
			$text  = str_replace( '{' . $argument->name . '}', '' === $value ? $argument->default_value : (string) $value, $text );
		}

		return $text;
	}
}
