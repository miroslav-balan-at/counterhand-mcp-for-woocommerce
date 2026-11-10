<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground;

use Counterhand\Features\McpServer\PromptDefinition;
use Counterhand\Shared\ModelFacingText;

defined( 'ABSPATH' ) || exit;

/**
 * A playbook offered in the chat, the same one an MCP client lists as a prompt. The person
 * sees its title; the model gets the playbook, which is English.
 */
final readonly class ChatStarter {

	private function __construct(
		public string $title,
		public string $message,
	) {}

	/** Null for a playbook that cannot run on its defaults, such as one that needs a language. */
	public static function of( PromptDefinition $prompt, string $locale ): ?self {
		foreach ( $prompt->arguments as $argument ) {
			if ( $argument->required && '' === $argument->default_value ) {
				return null;
			}
		}

		// The playbook says to answer in the person's language, and as sent the person writes English.
		return new self(
			$prompt->title,
			$prompt->message( [] ) . ModelFacingText::in_english( static fn (): string => sprintf( ' The person is writing in the language with the locale code %s.', $locale ) )
		);
	}
}
