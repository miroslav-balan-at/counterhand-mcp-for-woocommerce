<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Doubles;

use Counterhand\Features\WooCommerceTools\Infrastructure\RestGatewayInterface;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestMethod;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestResult;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

/**
 * Records what a tool asked WooCommerce for and replays canned results.
 *
 * Note this deliberately does NOT bind or strip path params — that is
 * RestGateway's job, covered by RestRouteTest. Tools are asserted on the
 * route template plus the params they hand over.
 */
final class FakeRestGateway implements RestGatewayInterface {

	/** @var list<array{route: RestRoute, method: RestMethod, params: array}> */
	public array $calls = [];

	/** @var list<RestResult> */
	private array $queue = [];

	public function will_return( RestResult ...$results ): void {
		$this->queue = [ ...$this->queue, ...$results ];
	}

	public function dispatch( RestRoute $route, RestMethod $method, array $params = [] ): RestResult {
		$this->calls[] = [
			'route'  => $route,
			'method' => $method,
			'params' => $params,
		];

		return array_shift( $this->queue ) ?? new RestResult( [] );
	}

	/** @return array{route: RestRoute, method: RestMethod, params: array} */
	public function call( int $index = 0 ): array {
		return $this->calls[ $index ];
	}
}
