<?php
/**
 * Constants defined by the plugin bootstrap, for static analysis.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

defined( 'VERIFYCATE_VERSION' ) || define( 'VERIFYCATE_VERSION', '0.1.0' );
defined( 'VERIFYCATE_FILE' ) || define( 'VERIFYCATE_FILE', dirname( __DIR__ ) . '/verifycate.php' );
defined( 'VERIFYCATE_DIR' ) || define( 'VERIFYCATE_DIR', dirname( __DIR__ ) . '/' );
defined( 'VERIFYCATE_URL' ) || define( 'VERIFYCATE_URL', 'https://example.test/wp-content/plugins/verifycate/' );
defined( 'VERIFYCATE_BASENAME' ) || define( 'VERIFYCATE_BASENAME', 'verifycate/verifycate.php' );
