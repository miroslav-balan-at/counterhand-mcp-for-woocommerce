<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\ContactFormPolicy;
use Counterhand\Tests\Unit\TestCase;

final class ContactFormPolicyTest extends TestCase {

	/**
	 * @dataProvider verdicts
	 * @param array<string, mixed> $arguments
	 */
	public function test_only_a_visitor_upload_may_be_attached( array $arguments, bool $allowed ): void {
		\Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( [ 'attachments' => "uploads/brochure.pdf\n[your-file]" ] );

		$this->assertSame( $allowed, ( new ContactFormPolicy() )->verdict( $arguments )->allowed );
	}

	/** @return iterable<string, array{array<string, mixed>, bool}> */
	public static function verdicts(): iterable {
		yield 'no mail' => [ [ 'title' => 'x' ], true ];
		yield 'no attachments' => [ [ 'mail' => [ 'recipient' => 'a@example.test' ] ], true ];
		yield 'an upload tag' => [ [ 'mail' => [ 'attachments' => "[your-file]\n[other]" ] ], true ];
		yield 'a path' => [ [ 'mail' => [ 'attachments' => 'debug.log' ] ], false ];
		yield 'a path after a tag' => [ [ 'mail' => [ 'attachments' => "[your-file]\nuploads/wc-logs/a.log" ] ], false ];
		yield 'a path after a bare carriage return' => [ [ 'mail' => [ 'attachments' => "[your-file]\rdebug.log" ] ], false ];
		yield 'a path after a windows line ending' => [ [ 'mail' => [ 'attachments' => "[your-file]\r\ndebug.log" ] ], false ];
		yield 'tags on any line ending' => [ [ 'mail' => [ 'attachments' => "[one]\r[two]\r\n[three]" ] ], true ];
		yield 'a path on the second mail' => [ [ 'mail_2' => [ 'attachments' => 'debug.log' ] ], false ];
		yield 'a path the form already attaches' => [
			[
				'id'   => 5,
				'mail' => [ 'attachments' => "uploads/brochure.pdf\r\n[your-file]" ],
			],
			true,
		];
		yield 'a path it does not' => [
			[
				'id'   => 5,
				'mail' => [ 'attachments' => "uploads/brochure.pdf\nuploads/secret.log" ],
			],
			false,
		];
		yield 'a stored path on a new form means nothing' => [ [ 'mail' => [ 'attachments' => 'uploads/brochure.pdf' ] ], false ];
		yield 'a default from the login' => [ [ 'form' => '[text n default:user_login]' ], true ];
		yield 'a default from the password hash' => [ [ 'form' => '[hidden h default:user_user_pass]' ], false ];
		yield 'any other user property' => [ [ 'form' => '[hidden h default:user_session_tokens]' ], false ];
		yield 'the same in capitals' => [ [ 'form' => '[hidden h default:USER_USER_PASS]' ], false ];
		yield 'the same with characters CF7 strips' => [ [ 'form' => '[hidden h default:user.user_pass]' ], true ];
		yield 'post meta' => [ [ 'form' => '[hidden _price default:post_meta]' ], false ];
		yield 'query values' => [ [ 'form' => '[text n default:get]' ], true ];
		yield 'a flamingo field from the form' => [ [ 'additional_settings' => 'flamingo_subject: [your-subject]' ], true ];
		yield 'a flamingo field from the IP' => [ [ 'additional_settings' => "demo_mode: off\nflamingo_subject: [_remote_ip]" ], false ];
		yield 'a flamingo field from the user agent' => [ [ 'additional_settings' => 'flamingo_name: [_user_agent]' ], false ];
		yield 'not a string' => [ [ 'mail' => [ 'attachments' => [ 'debug.log' ] ] ], false ];
	}
}
