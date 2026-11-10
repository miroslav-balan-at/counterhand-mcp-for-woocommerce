<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\Playground;

use Brain\Monkey\Functions;
use Counterhand\Features\McpServer\PromptArgument;
use Counterhand\Features\McpServer\PromptDefinition;
use Counterhand\Features\Playground\ChatStarter;
use Counterhand\Features\Playground\SavedPrompts;
use Counterhand\Features\Playbooks\PlaybookCatalog;
use Counterhand\Tests\Unit\TestCase;

final class ChatNotesTest extends TestCase {

	/** @var array<int, mixed> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_textarea_field' )->alias( static fn ( string $text ): string => strip_tags( $text ) );
		Functions\when( 'get_user_meta' )->alias( fn ( int $user_id ): mixed => $this->meta[ $user_id ] ?? '' );
		Functions\when( 'update_user_meta' )->alias(
			function ( int $user_id, string $key, mixed $value ): bool {
				$this->meta[ $user_id ] = $value;

				return true;
			}
		);
	}

	public function test_a_starter_carries_the_playbook_on_its_defaults_and_the_persons_locale(): void {
		$starter = ChatStarter::of( PlaybookCatalog::playbooks()[0]->prompt, 'de_DE' );

		self::assertNotNull( $starter );
		self::assertSame( 'Sales review', $starter->title );
		self::assertStringContainsString( 'over the last 7 days', $starter->message );
		self::assertStringEndsWith( 'the locale code de_DE.', $starter->message );
	}

	public function test_a_playbook_needing_a_value_with_no_default_is_not_a_starter(): void {
		$prompt = new PromptDefinition( 'needs_language', 'Needs a language', '', [ new PromptArgument( 'language', '', '/^[a-z]{2}$/', true ) ], 'Use {language}.' );

		self::assertNull( ChatStarter::of( $prompt, 'de_DE' ) );
	}

	public function test_saved_questions_are_per_person_kept_once_and_capped(): void {
		$prompts = new SavedPrompts();

		$prompts->add( 1, 'Orders today?' );
		$prompts->add( 1, 'Orders today?' );
		$prompts->add( 1, '<b>Bold</b> question' );

		self::assertSame( [ 'Bold question', 'Orders today?' ], $prompts->for_user( 1 ) );
		self::assertSame( [], $prompts->for_user( 2 ) );

		foreach ( range( 1, SavedPrompts::MAX_PROMPTS + 3 ) as $number ) {
			$prompts->add( 3, 'Question ' . $number );
		}

		self::assertCount( SavedPrompts::MAX_PROMPTS, $prompts->for_user( 3 ) );
		self::assertSame( 'Question ' . ( SavedPrompts::MAX_PROMPTS + 3 ), $prompts->for_user( 3 )[0] );
		self::assertSame( [ 'Orders today?' ], $prompts->remove( 1, 'Bold question' ) );
	}
}
