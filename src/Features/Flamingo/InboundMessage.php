<?php

declare( strict_types=1 );

namespace Counterhand\Features\Flamingo;

defined( 'ABSPATH' ) || exit;

/**
 * One stored form submission. IP addresses, user agents and spam scores stay in
 * Flamingo, out of results and out of search: nothing an assistant does needs them.
 */
final readonly class InboundMessage {

	/**
	 * @param array<string, mixed> $fields What the person submitted, by field name.
	 * @param array<string, mixed> $consent Which consent boxes they ticked.
	 */
	public function __construct(
		public int $id,
		public string $subject,
		public string $from_name,
		public string $from_email,
		public string $channel,
		public string $date_gmt,
		public InboundMessageStatus $status,
		public array $fields,
		public array $consent,
	) {}

	/** @return array<string, mixed> */
	public function summary(): array {
		return [
			'id'         => $this->id,
			'subject'    => $this->subject,
			'from_name'  => $this->from_name,
			'from_email' => $this->from_email,
			'channel'    => $this->channel,
			'date_gmt'   => $this->date_gmt,
			'status'     => $this->status->value,
		];
	}

	/** @return array<string, mixed> */
	public function to_array(): array {
		return [
			...$this->summary(),
			'fields'  => $this->fields,
			'consent' => $this->consent,
		];
	}
}
