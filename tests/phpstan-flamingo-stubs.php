<?php

declare( strict_types=1 );

/** The parts of Flamingo 2.6 this plugin calls. Never loaded at runtime. */
class Flamingo_Inbound_Message {

	/** @var string */
	public $subject;

	/** @var string */
	public $from_name;

	/** @var string */
	public $from_email;

	/** @var string */
	public $channel;

	/** @var mixed */
	public $fields;

	/** @var mixed */
	public $consent;

	/** @param mixed $post */
	public function __construct( $post = null ) {}

	/**
	 * @param  array<string, mixed> $args
	 * @return list<self>
	 */
	public static function find( $args = '' ) {
		return [];
	}

	/** @param array<string, mixed>|string $args */
	public static function count( $args = '' ): int {
		return 0;
	}

	/** @return int */
	public function id() {
		return 0;
	}

	/** @return bool|null */
	public function trash() {
		return null;
	}
}
