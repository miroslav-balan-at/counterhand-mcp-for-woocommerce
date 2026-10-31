<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Features\WooCommerceTools\Domain;

use Counterhand\Features\WooCommerceTools\Domain\ContactFormAttachmentPolicy;
use Counterhand\Tests\Unit\TestCase;

final class ContactFormAttachmentPolicyTest extends TestCase {

	/**
	 * @dataProvider verdicts
	 * @param array<string, mixed> $arguments
	 */
	public function test_only_a_visitor_upload_may_be_attached( array $arguments, bool $allowed ): void {
		$this->assertSame( $allowed, ( new ContactFormAttachmentPolicy() )->verdict( $arguments )->allowed );
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
		yield 'not a string' => [ [ 'mail' => [ 'attachments' => [ 'debug.log' ] ] ], false ];
	}
}
