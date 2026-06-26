<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

use function Pixelgrade\StyleManager\display_missing_dependencies_notice;

class MissingDependenciesNoticeTest extends TestCase {
	public function test_missing_dependencies_notice_outputs_error_with_documentation_link(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_kses' )->returnArg( 1 );

		ob_start();
		display_missing_dependencies_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'style-manager-compatibility-notice notice notice-error', $output );
		$this->assertStringContainsString( 'Style Manager is missing required dependencies.', $output );
		$this->assertStringContainsString( 'href="https://github.com/pixelgrade/style-manager"', $output );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $output );
	}
}
