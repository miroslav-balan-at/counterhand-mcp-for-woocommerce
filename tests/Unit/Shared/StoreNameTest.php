<?php

declare( strict_types=1 );

namespace Counterhand\Tests\Unit\Shared;

use Brain\Monkey\Functions;
use Counterhand\Shared\StoreName;
use Counterhand\Tests\Unit\TestCase;

final class StoreNameTest extends TestCase {

	public function test_the_name_wordpress_stores_escaped_goes_out_as_plain_text(): void {
		Functions\when( 'get_bloginfo' )->justReturn( 'Linden &amp; Co. &#039;Home&#039; Goods' );

		self::assertSame( "Linden & Co. 'Home' Goods", StoreName::plain() );
	}
}
