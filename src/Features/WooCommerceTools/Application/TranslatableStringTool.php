<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Application;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\Tokens\Domain\ScopeGroupInterface;
use Counterhand\Features\Tokens\Domain\ScopeInterface;
use Counterhand\Features\WooCommerceTools\Domain\StringTranslationRules;
use Counterhand\Features\WooCommerceTools\Domain\TranslatableString;
use Counterhand\Features\WooCommerceTools\Infrastructure\TranslatableStringsInterface;
use Counterhand\Shared\Exception\ToolCallException;
use Counterhand\Shared\Tool\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WPML's translatable strings: email subjects, attribute labels, shipping method titles.
 *
 * Written by hand because String Translation has no REST route. That also means no REST
 * permission callback runs behind this, so the capability check here is the only one.
 */
final readonly class TranslatableStringTool implements ToolInterface {

	private const LIST_NAME = 'get_translatable_strings';

	private const SET_NAME = 'set_string_translation';

	private const DEFAULT_PER_PAGE = 50;

	private const MAX_PER_PAGE = 100;

	private function __construct(
		private string $tool_name,
		private TranslatableStringsInterface $strings,
		private IntegrationGroup $group,
	) {}

	/** @return list<self> */
	public static function both( TranslatableStringsInterface $strings, IntegrationGroup $group ): array {
		return [
			new self( self::LIST_NAME, $strings, $group ),
			new self( self::SET_NAME, $strings, $group ),
		];
	}

	public function name(): string {
		return $this->tool_name;
	}

	public function description(): string {
		return $this->is_read_only()
			? 'List strings WPML can translate, such as email subjects, attribute labels and shipping method titles, with their translation into one language. only_missing leaves out those already translated. Payment settings and other plugins\' settings are not included. Paginated: the response reports total and total_pages.'
			: 'Set the translation of one string, by id from get_translatable_strings. Customers see it at once, including in store emails. Every placeholder such as {order_number} or %s must stay exactly as it is, and empty text is refused. Have a person check legal wording, such as withdrawal or safety text.';
	}

	public function input_schema(): array {
		$language = [
			'type'        => 'string',
			'description' => 'Language code of the translation, such as en.',
		];

		if ( $this->is_read_only() ) {
			return [
				'type'                 => 'object',
				'properties'           => [
					'language'     => $language,
					'search'       => [
						'type'        => 'string',
						'description' => 'Matches the original text or the string\'s name.',
					],
					'only_missing' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'page'         => [
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					],
					'per_page'     => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => self::MAX_PER_PAGE,
						'default' => self::DEFAULT_PER_PAGE,
					],
				],
				'required'             => [ 'language' ],
				'additionalProperties' => false,
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => [
				'id'       => [
					'type'    => 'integer',
					'minimum' => 1,
				],
				'language' => $language,
				'value'    => [
					'type'        => 'string',
					'description' => 'The translated text.',
				],
			],
			'required'             => [ 'id', 'language', 'value' ],
			'additionalProperties' => false,
		];
	}

	public function required_scope(): ScopeInterface {
		return $this->is_read_only() ? $this->group->read_scope() : ( $this->group->write_scope() ?? $this->group->read_scope() );
	}

	public function group(): ScopeGroupInterface {
		return $this->group;
	}

	public function is_available(): bool {
		return $this->strings->is_installed() && $this->strings->user_can_manage();
	}

	public function requires_confirmation(): bool {
		return ! $this->is_read_only();
	}

	public function is_read_only(): bool {
		return self::LIST_NAME === $this->tool_name;
	}

	public function is_destructive(): bool {
		return ! $this->is_read_only();
	}

	public function is_idempotent(): bool {
		return true;
	}

	public function is_open_world(): bool {
		return false;
	}

	public function execute( array $arguments ): array {
		// Available when listed, but a token outlives the role its user held then.
		if ( ! $this->is_available() ) {
			throw new ToolCallException( 'This user cannot manage translations.' );
		}

		return $this->is_read_only() ? $this->list_strings( $arguments ) : $this->set_translation( $arguments );
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function list_strings( array $arguments ): array {
		$per_page = max( 1, min( self::MAX_PER_PAGE, (int) ( $arguments['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );
		$page     = $this->strings->page(
			$this->active_language( $arguments ),
			(string) ( $arguments['search'] ?? '' ),
			true === ( $arguments['only_missing'] ?? false ),
			$per_page,
			max( 1, (int) ( $arguments['page'] ?? 1 ) )
		);

		return [
			'strings'     => array_map( static fn ( TranslatableString $text ): array => $text->to_array(), $page->strings ),
			'count'       => count( $page->strings ),
			'total'       => $page->total,
			'total_pages' => (int) ceil( $page->total / $per_page ),
		];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @throws ToolCallException When the string is out of reach, is already in that language, or did not save.
	 */
	private function set_translation( array $arguments ): array {
		$language = $this->active_language( $arguments );
		$id       = (int) ( $arguments['id'] ?? 0 );
		$text     = $this->strings->find( $id, $language );

		if ( null === $text ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is emitted as JSON via wp_json_encode(), never HTML.
			throw new ToolCallException( sprintf( 'No string %d can be translated here. Use an id from get_translatable_strings.', $id ) );
		}

		if ( $language === $text->language ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
			throw new ToolCallException( sprintf( 'String %d is written in %s, so it has no translation into it.', $id, $language ) );
		}

		$value   = wp_kses_post( (string) ( $arguments['value'] ?? '' ) );
		$refusal = ( new StringTranslationRules() )->refusal( $text->value, $value );

		if ( null !== $refusal ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
			throw new ToolCallException( $refusal );
		}

		if ( ! $this->strings->save( $id, $language, $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
			throw new ToolCallException( sprintf( 'WPML did not save the translation of string %d.', $id ) );
		}

		return [
			'id'          => $id,
			'language'    => $language,
			'translation' => $value,
		];
	}

	/**
	 * @param  array<string, mixed> $arguments
	 * @throws ToolCallException When the language is not active.
	 */
	private function active_language( array $arguments ): string {
		$language = (string) ( $arguments['language'] ?? '' );

		if ( ! $this->strings->is_active_language( $language ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- as above.
			throw new ToolCallException( sprintf( '"%s" is not an active language on this store.', $language ) );
		}

		return $language;
	}
}
