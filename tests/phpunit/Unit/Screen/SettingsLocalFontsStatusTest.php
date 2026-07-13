<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Functions;
use Carbon_Fields\Datastore\Datastore;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Screen\Settings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class SettingsLocalFontsStatusTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		Functions\when( 'esc_html' )->alias( static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn( $text, ...$args ) => htmlspecialchars( (string) $text, ENT_QUOTES ) );
		Functions\when( '_n' )->alias( static function ( string $single, string $plural, $number ) {
			return 1 === (int) $number ? $single : $plural;
		} );
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
		// even though both entries still appear in the list below. A single
		// hosted family must use the singular form ("1 font family", not
		// "1 font families").
		$this->assertStringContainsString( '1 font family hosted locally on this site.', $html );
		$this->assertStringContainsString( '<li>Uncut Sans &mdash; hosted locally</li>', $html );
		$this->assertStringContainsString( '<li>Quentin &mdash; download failed, will retry</li>', $html );
	}

	public function test_heading_uses_singular_form_for_a_single_hosted_family(): void {
		$html = Settings::build_local_fonts_status_html( [
			'Uncut Sans' => [ 'family_display' => 'Uncut Sans', 'status' => 'ok' ],
		] );

		$this->assertStringContainsString( '1 font family hosted locally on this site.', $html );
		$this->assertStringNotContainsString( '1 font families', $html );
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

	// -----------------------------------------------------------------
	// get_local_fonts_status_html() -- instance wrapper + hub link
	// -----------------------------------------------------------------

	public function test_instance_html_appends_manage_in_hub_link_when_hub_url_is_available(): void {
		Functions\when( 'esc_url' )->returnArg( 1 );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( [] );

		$settings = new class(
			$this->createMock( Options::class ),
			$this->createMock( Datastore::class ),
			$local_font_store,
			$this->createMock( LoggerInterface::class )
		) extends Settings {
			protected function get_hub_fonts_url(): string {
				return 'https://example.test/wp-admin/admin.php?page=pixelgrade&tab=styles&section=fonts';
			}
		};

		$html = $settings->get_local_fonts_status_html();

		$this->assertStringContainsString( '<p>No cloud fonts hosted locally yet.</p>', $html );
		$this->assertStringContainsString( '<p><a href="https://example.test/wp-admin/admin.php?page=pixelgrade&tab=styles&section=fonts">Manage in Pixelgrade Design &rarr;</a></p>', $html );
	}

	public function test_instance_html_omits_manage_in_hub_link_when_hub_url_is_unavailable(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( [] );

		$settings = new class(
			$this->createMock( Options::class ),
			$this->createMock( Datastore::class ),
			$local_font_store,
			$this->createMock( LoggerInterface::class )
		) extends Settings {
			protected function get_hub_fonts_url(): string {
				return '';
			}
		};

		$html = $settings->get_local_fonts_status_html();

		$this->assertSame( '<p>No cloud fonts hosted locally yet.</p>', $html );
		$this->assertStringNotContainsString( 'Manage in Pixelgrade Design', $html );
	}

	public function test_get_hub_fonts_url_delegates_to_the_shared_namespaced_helper(): void {
		// Real behavior, unmocked: `pixassist_get_hub_url()` is never defined
		// in this test process, so the shared helper's absent-hub branch runs.
		$settings = new class(
			$this->createMock( Options::class ),
			$this->createMock( Datastore::class ),
			$this->createMock( LocalFontStore::class ),
			$this->createMock( LoggerInterface::class )
		) extends Settings {
			public function expose_get_hub_fonts_url(): string {
				return $this->get_hub_fonts_url();
			}
		};

		$this->assertSame( '', $settings->expose_get_hub_fonts_url() );
	}
}
