<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Screen\Settings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class SettingsLocalFontsStatusTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		Functions\when( 'esc_html' )->alias( static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn( $text, ...$args ) => htmlspecialchars( (string) $text, ENT_QUOTES ) );
	}

	public function test_empty_manifest_reports_nothing_hosted_locally(): void {
		$this->assertSame(
			'<p>No cloud fonts hosted locally yet.</p>',
			Settings::build_local_fonts_status_html( [] )
		);
	}

	public function test_mixed_ok_and_failed_entries_are_listed_with_their_status(): void {
		$html = Settings::build_local_fonts_status_html( [
			'Uncut Sans' => [
				'family_display' => 'Uncut Sans',
				'status'         => 'ok',
			],
			'Quentin'    => [
				'family_display' => 'Quentin',
				'status'         => 'failed',
			],
		] );

		// The heading counts only successfully-hosted (status 'ok') entries,
		// even though both entries still appear in the list below.
		$this->assertStringContainsString( '1 font families hosted locally on this site.', $html );
		$this->assertStringContainsString( '<li>Uncut Sans &mdash; hosted locally</li>', $html );
		$this->assertStringContainsString( '<li>Quentin &mdash; download failed, will retry</li>', $html );
	}

	public function test_heading_counts_only_ok_entries_when_multiple_of_each_status(): void {
		$html = Settings::build_local_fonts_status_html( [
			'Font A' => [ 'family_display' => 'Font A', 'status' => 'ok' ],
			'Font B' => [ 'family_display' => 'Font B', 'status' => 'ok' ],
			'Font C' => [ 'family_display' => 'Font C', 'status' => 'failed' ],
		] );

		$this->assertStringContainsString( '2 font families hosted locally on this site.', $html );
	}

	public function test_heading_shows_zero_when_all_entries_failed(): void {
		$html = Settings::build_local_fonts_status_html( [
			'Font A' => [ 'family_display' => 'Font A', 'status' => 'failed' ],
			'Font B' => [ 'family_display' => 'Font B', 'status' => 'failed' ],
		] );

		$this->assertStringContainsString( '0 font families hosted locally on this site.', $html );
		$this->assertStringContainsString( '<li>Font A &mdash; download failed, will retry</li>', $html );
		$this->assertStringContainsString( '<li>Font B &mdash; download failed, will retry</li>', $html );
	}

	public function test_family_display_is_preferred_over_the_family_key_when_non_empty(): void {
		$html = Settings::build_local_fonts_status_html( [
			'uncut-sans-family-key' => [
				'family_display' => 'Uncut Sans',
				'status'         => 'ok',
			],
		] );

		$this->assertStringContainsString( '<li>Uncut Sans &mdash; hosted locally</li>', $html );
		$this->assertStringNotContainsString( 'uncut-sans-family-key', $html );
	}

	public function test_family_key_is_used_when_family_display_is_empty(): void {
		$html = Settings::build_local_fonts_status_html( [
			'Uncut Sans' => [
				'family_display' => '',
				'status'         => 'ok',
			],
		] );

		$this->assertStringContainsString( '<li>Uncut Sans &mdash; hosted locally</li>', $html );
	}

	public function test_family_name_with_special_characters_is_escaped(): void {
		$html = Settings::build_local_fonts_status_html( [
			'<script>alert(1)</script>' => [
				'family_display' => '<script>alert(1)</script>',
				'status'         => 'ok',
			],
		] );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
	}
}
