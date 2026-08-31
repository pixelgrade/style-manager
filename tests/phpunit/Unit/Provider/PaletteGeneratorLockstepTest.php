<?php
/**
 * The chroma-js lockstep guard.
 *
 * The palette generator's whole architectural claim is "the browser and the CLI run the same
 * code". That is true of the plugin's own ~370 lines by construction — one shared module, two
 * bundlers — but it is NOT automatic for chroma-js, because the two sides get it from different
 * places: the customizer bundle lists `chroma-js` in webpack `externals` and resolves it at
 * runtime from the `pixelgrade_style_manager-chroma` handle (`vendor_js/chroma.js`), while the
 * Node artifact bundles the copy from `node_modules`. Pinning `package.json` pins only the Node
 * side; the browser never reads it.
 *
 * That gap is exactly the risk the W5 spike ranked "low likelihood, catastrophic impact": a float
 * change in `correctLightness()` would move the browser's ramp while the parity fixtures (static
 * files × an unchanged artifact) stayed green. So the versions are asserted equal here, and any
 * future one-sided bump fails this test instead of silently drifting.
 *
 * @package Style Manager
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Pixelgrade\StyleManager\Provider\PaletteGenerator;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class PaletteGeneratorLockstepTest extends TestCase {

	private const PLUGIN_DIR = __DIR__ . '/../../../..';

	/**
	 * The single version both sides must agree on: whatever `package.json` pins, exactly.
	 */
	private function pinned_chroma_version(): string {
		$package = json_decode( (string) file_get_contents( self::PLUGIN_DIR . '/package.json' ), true );
		$pinned  = (string) ( $package['devDependencies']['chroma-js'] ?? '' );

		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			$pinned,
			'chroma-js must be pinned to an exact version — a range lets `correctLightness()` float, and float is the parity contract.'
		);

		return $pinned;
	}

	public function test_the_browser_chroma_matches_the_pinned_version(): void {
		$pinned = $this->pinned_chroma_version();

		// The customizer/site-editor bundles do NOT bundle chroma; they take the global this
		// file defines (Provider\CustomizerAssets registers it as pixelgrade_style_manager-chroma).
		$vendored = (string) file_get_contents( self::PLUGIN_DIR . '/vendor_js/chroma.js' );

		$this->assertStringContainsString(
			"version = '" . $pinned . "'",
			$vendored,
			'vendor_js/chroma.js (what the browser actually runs) has drifted from the pinned chroma-js version.'
		);
	}

	public function test_the_minified_browser_chroma_matches_the_pinned_version(): void {
		$pinned = $this->pinned_chroma_version();

		// SCRIPT_DEBUG off ships the .min.js, so it is a second, independently rottable copy.
		$vendored = (string) file_get_contents( self::PLUGIN_DIR . '/vendor_js/chroma.min.js' );

		$this->assertStringContainsString(
			'version="' . $pinned . '"',
			$vendored,
			'vendor_js/chroma.min.js has drifted from the pinned chroma-js version.'
		);
	}

	public function test_the_node_artifact_matches_the_pinned_version(): void {
		$pinned   = $this->pinned_chroma_version();
		$artifact = self::PLUGIN_DIR . '/' . PaletteGenerator::ARTIFACT_RELATIVE_PATH;

		if ( ! is_readable( $artifact ) ) {
			$this->markTestSkipped( 'Build the Node palette generator first: `npm run compile:production`.' );
		}

		// Deliberately unminified, so the bundled dependency's own version marker survives.
		$this->assertStringContainsString(
			"version = '" . $pinned . "'",
			(string) file_get_contents( $artifact ),
			'dist/node/palette-generator.js bundles a different chroma-js than package.json pins — rebuild it.'
		);
	}
}
