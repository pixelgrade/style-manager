<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Mockery;
use Pixelgrade\StyleManager\Customize\CloudFonts;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\LocalFonts;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class LocalFontsTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
	}

	// -----------------------------------------------------------------
	// register_hooks()
	// -----------------------------------------------------------------

	public function test_register_hooks_adds_nothing_when_cloud_fonts_setting_is_disabled(): void {
		$plugin_settings = $this->settings( [ 'typography_cloud_fonts' => false, 'typography_host_cloud_fonts_locally' => true ] );

		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )->never();

		$this->make_provider( null, null, null, null, $plugin_settings )->register_hooks();
		$this->addToAssertionCount( 1 );
	}

	public function test_register_hooks_adds_nothing_when_local_hosting_setting_is_disabled(): void {
		$plugin_settings = $this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => false ] );

		Functions\expect( 'add_action' )->never();
		Functions\expect( 'add_filter' )->never();

		$this->make_provider( null, null, null, null, $plugin_settings )->register_hooks();
		$this->addToAssertionCount( 1 );
	}

	public function test_register_hooks_wires_up_the_expected_hooks_when_both_settings_enabled(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		Filters\expectAdded( 'customize_save_after' )->once();
		Filters\expectAdded( 'style_manager/settings_saved' )->once();
		Filters\expectAdded( LocalFonts::CRON_HOOK )->once();
		Filters\expectAdded( 'admin_init' )->once();

		$plugin_settings = $this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] );

		$this->make_provider( null, null, null, null, $plugin_settings )->register_hooks();
		$this->addToAssertionCount( 4 );
	}

	// -----------------------------------------------------------------
	// mirror_used_fonts()
	// -----------------------------------------------------------------

	public function test_mirror_used_fonts_is_a_noop_when_disabled(): void {
		$plugin_settings = $this->settings( [ 'typography_cloud_fonts' => false, 'typography_host_cloud_fonts_locally' => true ] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->expects( $this->never() )->method( 'get_used_cloud_font_families' );

		$this->make_provider( null, $sm_fonts, null, null, $plugin_settings )->mirror_used_fonts();
	}

	public function test_mirror_used_fonts_skips_already_healthy_families(): void {
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->with( 'Uncut Sans' )->willReturn( true );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts )->mirror_used_fonts();
	}

	public function test_mirror_used_fonts_mirrors_unhealthy_used_family_preferring_remote_src_and_cloud_last_modified(): void {
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Uncut Sans' => [
				'family'         => 'Uncut Sans',
				'family_display' => 'Uncut Sans',
				'src'            => 'https://example.test/uploads/style-manager/fonts/uncut-sans/stylesheet.css',
				'remote_src'     => 'https://cloud.pixelgrade.com/cloud-fonts-v2/uncut-sans/stylesheet.css',
				'variants'       => [ '400' ],
				'category'       => 'sans-serif',
				'fallback_stack' => '',
			],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->with( 'cloud_fonts' )->willReturn( [
			'hash1' => [
				'font_family'   => 'Uncut Sans',
				'last_modified' => '2026-01-01 10:00:00',
			],
		] );

		$local_font_store
			->expects( $this->once() )
			->method( 'mirror_font' )
			->with(
				[
					'family'         => 'Uncut Sans',
					'family_display' => 'Uncut Sans',
					'src'            => 'https://cloud.pixelgrade.com/cloud-fonts-v2/uncut-sans/stylesheet.css',
					'variants'       => [ '400' ],
					'category'       => 'sans-serif',
					'fallback_stack' => '',
				],
				'2026-01-01 10:00:00'
			)
			->willReturn( true );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->mirror_used_fonts();
	}

	public function test_mirror_used_fonts_schedules_a_single_retry_event_with_failed_families(): void {
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Broken Font' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->method( 'mirror_font' )->willReturn( false );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Broken Font' => [ 'family' => 'Broken Font', 'src' => 'https://cloud.example.test/broken/stylesheet.css' ],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( LocalFonts::CRON_HOOK, [ [ 'Broken Font' ] ] )
			->andReturn( false );

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				LocalFonts::CRON_HOOK,
				[ [ 'Broken Font' ] ]
			)
			->andReturn( true );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->mirror_used_fonts();
		$this->addToAssertionCount( 1 );
	}

	public function test_mirror_used_fonts_does_not_schedule_a_retry_for_a_family_missing_from_the_cloud_config(): void {
		// The family was delisted from the cloud (e.g. removed from the palette
		// config) and is not resolvable at all -- this must not be treated the
		// same as a transient network failure, otherwise it gets rescheduled
		// every 30 minutes forever without the attempts counter ever moving
		// (see mirror_family(), which never touches the manifest for this case).
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Delisted Font' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' );

		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets, null, $logger )->mirror_used_fonts();
	}

	public function test_mirror_used_fonts_does_not_schedule_a_duplicate_retry_event(): void {
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Broken Font' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->method( 'mirror_font' )->willReturn( false );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Broken Font' => [ 'family' => 'Broken Font', 'src' => 'https://cloud.example.test/broken/stylesheet.css' ],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_next_scheduled' )->once()->andReturn( true );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->mirror_used_fonts();
		$this->addToAssertionCount( 1 );
	}

	// -----------------------------------------------------------------
	// mirror_families()
	// -----------------------------------------------------------------

	public function test_mirror_families_stops_retrying_a_family_once_the_attempts_cap_is_reached(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->method( 'get_entry' )->willReturn( [ 'attempts' => LocalFonts::MAX_ATTEMPTS ] );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, null, $cloud_fonts )->mirror_families( [ 'Exhausted Font' ] );
	}

	public function test_mirror_families_reschedules_a_still_failing_family_with_attempts_based_backoff(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store
			->method( 'get_entry' )
			->willReturnOnConsecutiveCalls(
				[ 'attempts' => 1 ], // Checked before attempting: under the cap.
				[ 'attempts' => 2 ]  // Re-read after the failed attempt, for backoff.
			);
		$local_font_store->method( 'mirror_font' )->willReturn( false );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Flaky Font' => [ 'family' => 'Flaky Font', 'src' => 'https://cloud.example.test/flaky/stylesheet.css' ],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_next_scheduled' )->once()->andReturn( false );
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				LocalFonts::CRON_HOOK,
				[ [ 'Flaky Font' ] ]
			)
			->andReturn( true );

		$this->make_provider( $local_font_store, null, $cloud_fonts, $design_assets )->mirror_families( [ 'Flaky Font' ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_mirror_families_does_not_reschedule_once_the_family_mirrors_successfully(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->method( 'get_entry' )->willReturn( [ 'attempts' => 1 ] );
		$local_font_store->method( 'mirror_font' )->willReturn( true );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Recovering Font' => [ 'family' => 'Recovering Font', 'src' => 'https://cloud.example.test/recovering/stylesheet.css' ],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, null, $cloud_fonts, $design_assets )->mirror_families( [ 'Recovering Font' ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_mirror_families_does_not_reschedule_a_family_missing_from_the_cloud_config(): void {
		// A family that was delisted from the cloud config between the previous
		// attempt and now (or whose local mirror later broke while it stayed
		// delisted) can never succeed via mirror_family() -- it must not be
		// rescheduled forever with an attempts counter that never increments.
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );
		$local_font_store->method( 'get_entry' )->willReturn( [ 'attempts' => 1 ] );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' );

		Functions\expect( 'wp_next_scheduled' )->never();
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, null, $cloud_fonts, $design_assets, null, $logger )->mirror_families( [ 'Delisted Font' ] );
	}

	public function test_mirror_families_re_mirrors_a_healthy_but_stale_family(): void {
		// A family scheduled by maybe_refresh_mirrors() because the cloud's
		// last_modified changed is healthy (still serving its old mirror) but
		// must still be re-mirrored -- otherwise scheduled refreshes never run.
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->with( 'Stale Font' )->willReturn( true );
		$local_font_store->method( 'needs_refresh' )->with( 'Stale Font', 'new' )->willReturn( true );
		$local_font_store->method( 'get_entry' )->willReturn( [ 'attempts' => 0 ] );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->method( 'get_cloud_fonts' )->willReturn( [
			'Stale Font' => [ 'family' => 'Stale Font', 'src' => 'https://cloud.example.test/stale/stylesheet.css' ],
		] );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->with( 'cloud_fonts' )->willReturn( [
			'hash1' => [ 'font_family' => 'Stale Font', 'last_modified' => 'new' ],
		] );

		$local_font_store->expects( $this->once() )->method( 'mirror_font' )->willReturn( true );

		$this->make_provider( $local_font_store, null, $cloud_fonts, $design_assets )->mirror_families( [ 'Stale Font' ] );
	}

	public function test_mirror_families_skips_a_healthy_family_that_does_not_need_refresh(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->with( 'Fresh Font' )->willReturn( true );
		$local_font_store->method( 'needs_refresh' )->willReturn( false );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, null, $cloud_fonts )->mirror_families( [ 'Fresh Font' ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_mirror_families_still_caps_attempts_for_a_healthy_stale_family(): void {
		// The attempts cap applies uniformly; a healthy-but-stale family whose
		// attempts already reached the cap (from prior failed refreshes) is left
		// serving its stale-but-working local mirror rather than retried forever.
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( true );
		$local_font_store->method( 'needs_refresh' )->willReturn( true );
		$local_font_store->method( 'get_entry' )->willReturn( [ 'attempts' => LocalFonts::MAX_ATTEMPTS ] );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, null, $cloud_fonts )->mirror_families( [ 'Capped Stale Font' ] );
		$this->addToAssertionCount( 1 );
	}

	// -----------------------------------------------------------------
	// maybe_refresh_mirrors()
	// -----------------------------------------------------------------

	public function test_maybe_refresh_mirrors_is_throttled_to_once_per_twelve_hours(): void {
		Functions\when( 'get_option' )->justReturn( time() - HOUR_IN_SECONDS ); // 1h ago, well under the 12h throttle.

		Functions\expect( 'update_option' )->never();

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->expects( $this->never() )->method( 'get_manifest' );

		$this->make_provider( $local_font_store )->maybe_refresh_mirrors();
	}

	public function test_maybe_refresh_mirrors_schedules_a_stale_family_when_last_modified_changed(): void {
		// admin_init only decides + schedules; it must never mirror inline
		// (remote HTTP during an admin page load could stall the admin for
		// seconds). The actual mirroring happens in the cron-driven
		// mirror_families().
		Functions\when( 'get_option' )->justReturn( 0 ); // Never run before -> not throttled.
		Functions\when( 'update_option' )->justReturn( true );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( [
			'Stale Font' => [ 'status' => 'ok', 'last_modified' => 'old' ],
		] );
		$local_font_store->method( 'needs_refresh' )->with( 'Stale Font', 'new' )->willReturn( true );
		$local_font_store->method( 'is_healthy' )->willReturn( true );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->with( 'cloud_fonts' )->willReturn( [
			'hash1' => [ 'font_family' => 'Stale Font', 'last_modified' => 'new' ],
		] );

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( LocalFonts::CRON_HOOK, [ [ 'Stale Font' ] ] )
			->andReturn( false );

		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				LocalFonts::CRON_HOOK,
				[ [ 'Stale Font' ] ]
			)
			->andReturn( true );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->maybe_refresh_mirrors();
		$this->addToAssertionCount( 1 );
	}

	public function test_maybe_refresh_mirrors_skips_a_family_when_last_modified_is_unchanged(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( [
			'Fresh Font' => [ 'status' => 'ok', 'last_modified' => 'same' ],
		] );
		$local_font_store->method( 'needs_refresh' )->willReturn( false );
		$local_font_store->method( 'is_healthy' )->willReturn( true );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [
			'hash1' => [ 'font_family' => 'Fresh Font', 'last_modified' => 'same' ],
		] );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->maybe_refresh_mirrors();
	}

	public function test_maybe_refresh_mirrors_schedules_every_stale_family_in_a_single_event_without_mirroring_inline(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		$stale_families = [ 'Font A', 'Font B', 'Font C', 'Font D', 'Font E' ];

		$manifest = [];
		foreach ( $stale_families as $family ) {
			$manifest[ $family ] = [ 'status' => 'ok', 'last_modified' => 'old' ];
		}

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( $manifest );
		$local_font_store->method( 'needs_refresh' )->willReturn( true );
		$local_font_store->method( 'is_healthy' )->willReturn( true );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_next_scheduled' )->once()->andReturn( false );
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				LocalFonts::CRON_HOOK,
				[ $stale_families ]
			)
			->andReturn( true );

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->maybe_refresh_mirrors();
	}

	public function test_maybe_refresh_mirrors_never_mirrors_a_used_family_that_was_never_previously_attempted(): void {
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'get_manifest' )->willReturn( [] );
		$local_font_store->expects( $this->never() )->method( 'mirror_font' );

		// The family is used, but never mirrored before (no manifest entry) --
		// that is mirror_used_fonts()'s job, not a "refresh".
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Never Mirrored Font' ] );
		$local_font_store->method( 'is_healthy' )->with( 'Never Mirrored Font' )->willReturn( false );
		$local_font_store->method( 'get_entry' )->with( 'Never Mirrored Font' )->willReturn( null );

		$cloud_fonts = $this->createMock( CloudFonts::class );
		$cloud_fonts->expects( $this->never() )->method( 'get_cloud_fonts' );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->make_provider( $local_font_store, $sm_fonts, $cloud_fonts, $design_assets )->maybe_refresh_mirrors();
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	private function settings( array $values ): PluginSettings {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnCallback(
			static function ( string $key, $default = null ) use ( $values ) {
				return $values[ $key ] ?? $default;
			}
		);

		return $plugin_settings;
	}

	private function make_provider(
		?LocalFontStore $local_font_store = null,
		?Fonts $sm_fonts = null,
		?CloudFonts $cloud_fonts = null,
		?DesignAssets $design_assets = null,
		?PluginSettings $plugin_settings = null,
		?LoggerInterface $logger = null
	): LocalFonts {
		return new LocalFonts(
			$local_font_store ?? $this->createMock( LocalFontStore::class ),
			$sm_fonts ?? $this->createMock( Fonts::class ),
			$cloud_fonts ?? $this->createMock( CloudFonts::class ),
			$design_assets ?? $this->createMock( DesignAssets::class ),
			$plugin_settings ?? $this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			$logger ?? $this->createMock( LoggerInterface::class )
		);
	}
}
