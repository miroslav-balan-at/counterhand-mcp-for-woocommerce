<?php
/**
 * Minimal declarations for WordPress 7.0's AI Client and the php-ai-client SDK
 * it bundles.
 *
 * The plugin does not require WordPress 7.0 — CoreAiClientProvider is only
 * registered when core actually provides these symbols — so they do not exist
 * on the analysis baseline. Only the surface the provider touches is declared.
 *
 * @see https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
 */

declare( strict_types=1 );

namespace {

	/** @return \WP_AI_Client_Prompt_Builder */
	function wp_ai_client_prompt( ?string $prompt = null ): object {}

	/**
	 * WordPress 7.0 Connectors API.
	 *
	 * @return array<string, array{name: string, description: string, type: string, authentication: array<string, mixed>, plugin?: array<string, mixed>}>
	 */
	function wp_get_connectors(): array {}

	/**
	 * Core's WordPress-facing wrapper. Every method below is virtual, resolved
	 * by __call() and proxied to the SDK's PromptBuilder in camelCase.
	 */
	class WP_AI_Client_Prompt_Builder {

		public function with_text( string $text ): self {}

		public function with_message_parts( \WordPress\AiClient\Messages\DTO\MessagePart ...$parts ): self {}

		public function with_history( \WordPress\AiClient\Messages\DTO\Message ...$messages ): self {}

		public function using_system_instruction( string $instruction ): self {}

		public function using_max_tokens( int $max_tokens ): self {}

		/** @param string ...$models */
		public function using_model_preference( ...$models ): self {}

		public function using_function_declarations( \WordPress\AiClient\Tools\DTO\FunctionDeclaration ...$declarations ): self {}

		public function is_supported_for_text_generation(): bool {}

		/** @return \WordPress\AiClient\Results\DTO\GenerativeAiResult|\WP_Error */
		public function generate_text_result() {}
	}
}

namespace WordPress\AiClient\Messages\Enums {

	class MessageRoleEnum {

		public static function user(): self {}

		public static function model(): self {}
	}

	class MessagePartChannelEnum {

		public function isContent(): bool {}
	}
}

namespace WordPress\AiClient\Messages\DTO {

	class MessagePart {

		/** @param mixed $content */
		public function __construct( $content ) {}

		public function getChannel(): \WordPress\AiClient\Messages\Enums\MessagePartChannelEnum {}

		public function getText(): ?string {}

		public function getFunctionCall(): ?\WordPress\AiClient\Tools\DTO\FunctionCall {}

		public function getThoughtSignature(): ?string {}
	}

	class Message {

		/** @param list<MessagePart> $parts */
		public function __construct( \WordPress\AiClient\Messages\Enums\MessageRoleEnum $role, array $parts ) {}

		/** @return list<MessagePart> */
		public function getParts(): array {}

		public function getRole(): \WordPress\AiClient\Messages\Enums\MessageRoleEnum {}

		/** @return array<string, mixed> */
		public function toArray(): array {}

		/** @param array<string, mixed> $array */
		public static function fromArray( array $array ): self {}
	}
}

namespace WordPress\AiClient\Tools\DTO {

	class FunctionDeclaration {

		/** @param array<string, mixed>|null $parameters */
		public function __construct( string $name, string $description, ?array $parameters = null ) {}

		/** @return array<string, mixed> */
		public function toArray(): array {}

		/** @param array<string, mixed> $array */
		public static function fromArray( array $array ): self {}
	}

	class FunctionCall {

		public function getId(): ?string {}

		public function getName(): ?string {}

		/** @return mixed */
		public function getArgs() {}
	}

	class FunctionResponse {

		/** @param mixed $response */
		public function __construct( ?string $id, ?string $name, $response ) {}
	}
}

namespace WordPress\AiClient {

	class ProviderRegistry {

		public function hasProvider( string $id ): bool {}

		public function isProviderConfigured( string $id ): bool {}
	}

	class AiClient {

		public static function defaultRegistry(): ProviderRegistry {}
	}
}

namespace WordPress\AiClient\Results\DTO {

	class TokenUsage {

		public function getPromptTokens(): int {}

		public function getCompletionTokens(): int {}
	}

	class GenerativeAiResult {

		public function toMessage(): \WordPress\AiClient\Messages\DTO\Message {}

		public function getTokenUsage(): TokenUsage {}
	}
}
