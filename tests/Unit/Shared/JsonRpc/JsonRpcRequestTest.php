<?php

declare( strict_types=1 );

namespace AgentGateMcp\Tests\Unit\Shared\JsonRpc;

use AgentGateMcp\Shared\JsonRpc\JsonRpcEnvelopeException;
use AgentGateMcp\Shared\JsonRpc\JsonRpcErrorCode;
use AgentGateMcp\Shared\JsonRpc\JsonRpcRequest;
use AgentGateMcp\Tests\Unit\TestCase;

final class JsonRpcRequestTest extends TestCase {

	public function test_valid_request_parses(): void {
		$request = JsonRpcRequest::from_body( '{"jsonrpc":"2.0","id":7,"method":"tools/call","params":{"name":"ping"}}' );

		self::assertSame( 'tools/call', $request->method );
		self::assertSame( 7, $request->id );
		self::assertSame( [ 'name' => 'ping' ], $request->params );
		self::assertFalse( $request->is_notification );
	}

	public function test_missing_id_marks_notification(): void {
		$request = JsonRpcRequest::from_body( '{"jsonrpc":"2.0","method":"notifications/initialized"}' );

		self::assertTrue( $request->is_notification );
		self::assertNull( $request->id );
	}

	/** @dataProvider invalid_bodies */
	public function test_invalid_bodies_raise_expected_code( string $body, JsonRpcErrorCode $expected_code ): void {
		try {
			JsonRpcRequest::from_body( $body );
			self::fail( 'Expected JsonRpcEnvelopeException' );
		} catch ( JsonRpcEnvelopeException $exception ) {
			self::assertSame( $expected_code, $exception->error_code );
		}
	}

	public static function invalid_bodies(): array {
		return [
			'malformed json'   => [ '{broken', JsonRpcErrorCode::ParseError ],
			'empty body'       => [ '', JsonRpcErrorCode::ParseError ],
			'json scalar'      => [ '"hello"', JsonRpcErrorCode::ParseError ],
			'batch request'    => [ '[{"jsonrpc":"2.0","id":1,"method":"ping"}]', JsonRpcErrorCode::InvalidRequest ],
			'wrong version'    => [ '{"jsonrpc":"1.0","id":1,"method":"ping"}', JsonRpcErrorCode::InvalidRequest ],
			'missing version'  => [ '{"id":1,"method":"ping"}', JsonRpcErrorCode::InvalidRequest ],
			'missing method'   => [ '{"jsonrpc":"2.0","id":1}', JsonRpcErrorCode::InvalidRequest ],
			'non-string method' => [ '{"jsonrpc":"2.0","id":1,"method":42}', JsonRpcErrorCode::InvalidRequest ],
			'scalar params'    => [ '{"jsonrpc":"2.0","id":1,"method":"ping","params":"x"}', JsonRpcErrorCode::InvalidRequest ],
			'object id'        => [ '{"jsonrpc":"2.0","id":{},"method":"ping"}', JsonRpcErrorCode::InvalidRequest ],
		];
	}
}
