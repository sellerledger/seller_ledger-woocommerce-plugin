<?php
/**
 * PHP-Scoper configuration.
 *
 * Prefixes the bundled third-party libraries (Guzzle, PSR, etc.) under
 * SellerLedger\Vendor so they cannot collide with copies shipped by other
 * plugins. The plugin-facing SellerLedger\ client namespace is left global so
 * plugin code can keep calling SellerLedger\Client directly; references to the
 * prefixed libraries inside the client are rewritten automatically.
 *
 * @see https://github.com/humbug/php-scoper
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix'             => 'SellerLedger\\Vendor',

	'exclude-namespaces' => array(
		'SellerLedger',
	),

	'finders'            => array(
		Finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/composer\.(json|lock)$/' )
			->exclude( array( 'doc', 'docs', 'test', 'tests', 'Tests' ) )
			->in( 'vendor' ),
	),

	'patchers'           => array(),
);
