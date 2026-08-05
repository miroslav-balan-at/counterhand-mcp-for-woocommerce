<?php

declare( strict_types=1 );

// Satisfy the ABSPATH guard in every plugin file.
define( 'ABSPATH', __DIR__ . '/../' );
define( 'COUNTERHAND_VERSION', 'test' );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-schema-stubs.php';
require_once __DIR__ . '/wp-rest-stubs.php';

// The SDK lives in /freemius, which the tests do not load. The same stub serves
// static analysis, so the declared surface cannot drift between the two.
require_once __DIR__ . '/freemius-stubs.php';
