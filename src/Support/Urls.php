<?php
/**
 * Canonical Verifycate URLs.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Production URLs used by runtime links.
 */
final class Urls {

	/**
	 * Verifycate application URL.
	 */
	public const APP_URL = 'https://app.verifycate.com';

	/**
	 * Verifycate marketing site URL.
	 */
	public const SITE_URL = 'https://verifycate.com';
}
