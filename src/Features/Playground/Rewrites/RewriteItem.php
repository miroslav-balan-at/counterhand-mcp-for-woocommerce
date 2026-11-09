<?php

declare( strict_types=1 );

namespace Counterhand\Features\Playground\Rewrites;

use Counterhand\Features\Playground\Provider\TokenUsage;

defined( 'ABSPATH' ) || exit;

final readonly class RewriteItem {

	public function __construct(
		public int $id,
		public string $job_key,
		public int $user_id,
		public int $product_id,
		public RewriteField $field,
		public string $instruction,
		public RewriteStatus $status,
		public string $product_name,
		public string $original,
		public string $proposal,
		public string $message,
		public ?string $change_key,
		public TokenUsage $usage,
		public \DateTimeImmutable $created_at,
	) {}
}
