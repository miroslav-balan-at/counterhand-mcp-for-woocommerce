<?php

declare( strict_types=1 );

// Satisfy the ABSPATH guard in every plugin file.
define( 'ABSPATH', __DIR__ . '/../' );
define( 'COUNTERHAND_VERSION', 'test' );

// Core's time constants, for classes that size a transient TTL with them.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-schema-stubs.php';
require_once __DIR__ . '/wp-rest-stubs.php';
