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
		\Brain\Monkey\Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key ): mixed => '_form' === $key
				? '[email* your-email] [text your-subject]'
				: [
					'attachments'        => "uploads/brochure.pdf\n[your-file]",
					'recipient'          => 'sales@example.test',
					'additional_headers' => "Reply-To: [your-email]\nX-Team: sales",
				]
		);

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
		yield 'a stored path with the same people' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments'        => 'uploads/brochure.pdf',
					'recipient'          => 'sales@example.test',
					'additional_headers' => "Reply-To: [your-email]\r\nX-Team: sales",
				],
			],
			true,
		];
		yield 'a stored path with a new recipient' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments' => 'uploads/brochure.pdf',
					'recipient'   => 'attacker@example.test',
				],
			],
			false,
		];
		yield 'a stored path going to whoever submits' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments' => 'uploads/brochure.pdf',
					'recipient'   => '[your-email]',
				],
			],
			false,
		];
		yield 'a stored path with a new blind copy' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments'        => 'uploads/brochure.pdf',
					'additional_headers' => 'Bcc: attacker@example.test',
				],
			],
			false,
		];
		yield 'a recipient sent as something else' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments' => 'uploads/brochure.pdf',
					'recipient'   => [ 'attacker@example.test' ],
				],
			],
			false,
		];
		yield 'a new recipient with only uploads attached' => [
			[
				'id'   => 5,
				'mail' => [
					'attachments' => '[your-file]',
					'recipient'   => 'someone@example.test',
				],
			],
			true,
		];
		yield 'a stored path on a new form means nothing' => [ [ 'mail' => [ 'attachments' => 'uploads/brochure.pdf' ] ], false ];
		yield 'a default from the login' => [ [ 'form' => '[text n default:user_login]' ], true ];
		yield 'a default from the password hash' => [ [ 'form' => '[hidden h default:user_user_pass]' ], false ];
		yield 'any other user property' => [ [ 'form' => '[hidden h default:user_session_tokens]' ], false ];
		yield 'the same in capitals' => [ [ 'form' => '[hidden h default:USER_USER_PASS]' ], false ];
		yield 'the same with characters CF7 strips' => [ [ 'form' => '[hidden h default:user.user_pass]' ], true ];
		yield 'post meta' => [ [ 'form' => '[hidden _price default:post_meta]' ], false ];
		yield 'query values' => [ [ 'form' => '[text n default:get]' ], true ];
		yield 'a flamingo field from the stored form' => [
			[
				'id'                  => 5,
				'additional_settings' => 'flamingo_subject: [your-subject]',
			],
			true,
		];
		yield 'a flamingo field from the form being sent' => [
			[
				'form'                => '[text other-field]',
				'additional_settings' => 'flamingo_subject: [other-field]',
			],
			true,
		];
		yield 'a field the sent form no longer has' => [
			[
				'id'                  => 5,
				'form'                => '[text other-field]',
				'additional_settings' => 'flamingo_subject: [your-subject]',
			],
			false,
		];
		yield 'a flamingo field with plain text' => [ [ 'additional_settings' => 'flamingo_subject: "Enquiry"' ], true ];
		yield 'a setting that is not flamingo' => [ [ 'additional_settings' => 'demo_mode: [_remote_ip]' ], true ];
		yield 'a flamingo field from the IP' => [ [ 'additional_settings' => "demo_mode: off\nflamingo_subject: [_remote_ip]" ], false ];
		yield 'a flamingo field from the user agent' => [ [ 'additional_settings' => 'flamingo_name: [_user_agent]' ], false ];
		yield 'a dotted alias' => [ [ 'additional_settings' => 'flamingo_email: [wpcf7.remote_ip]' ], false ];
		yield 'a line hidden behind a bare carriage return' => [ [ 'additional_settings' => "a: b\rflamingo_email: [_remote_ip]" ], false ];
		yield 'a raw special tag' => [ [ 'additional_settings' => 'flamingo_email: [_raw_your-email]' ], false ];
		yield 'not a string' => [ [ 'mail' => [ 'attachments' => [ 'debug.log' ] ] ], false ];
	}
}
