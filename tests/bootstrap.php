<?php

declare( strict_types=1 );

// Satisfy the ABSPATH guard in every plugin file.
define( 'ABSPATH', __DIR__ . '/../' );
define( 'CTRH_VERSION', 'test' );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/wp-schema-stubs.php';
require_once __DIR__ . '/wp-rest-stubs.php';
