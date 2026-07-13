<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use function Pixelgrade\StyleManager\get_design_hub_fonts_url;

/**
 * Tests for the shared `get_design_hub_fonts_url()` helper.
 *
 * The Assistant-provided `pixassist_get_hub_url()` function is never defined
 * anywhere in this test process, so `function_exists( 'pixassist_get_hub_url' )`
 * reliably reports it as absent here -- exercising the "hub unavailable"
 * branch directly and truthfully. The "hub available" branch (the function
 * exists and its return value is used) can't be toggled mid test-suite the
 * way a global function definition works (it would leak into every other
 * test that runs afterwards), so it's covered instead via the protected
 * `get_hub_fonts_url()` seams on the consumer classes (GeneralAdmin,
 * Settings) -- see GeneralAdminLocalFontsNoticeTest and
 * SettingsLocalFontsStatusTest.
 *
 * @since 2.4.0
 */
class GetDesignHubFontsUrlTest extends TestCase {
	public function test_returns_empty_string_when_assistant_hub_helper_is_unavailable(): void {
		$this->assertSame( '', get_design_hub_fonts_url() );
	}
}
