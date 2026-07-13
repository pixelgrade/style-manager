<?php
/**
 * Auto-mirrors used cloud fonts to local storage, retries failures via cron,
 * and periodically refreshes stale mirrors.
 *
 * @since   2.4.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\CloudFonts;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Keeps locally-mirrored cloud fonts in sync: mirrors newly used fonts right
 * after settings are saved (Customizer or Site Editor), retries failed
 * mirrors via a scheduled cron event with backoff, and refreshes stale
 * mirrors when the cloud's `last_modified` changes.
 *
 * Everything here is a no-op unless both the `typography_cloud_fonts` and
 * `typography_host_cloud_fonts_locally` settings are truthy.
 *
 * @since 2.4.0
 */
class LocalFonts extends AbstractHookProvider {

	/**
	 * The cron hook used to retry/schedule mirroring of specific font families.
	 *
	 * @since 2.4.0
	 */
	const CRON_HOOK = 'style_manager/mirror_local_fonts';

	/**
	 * The option holding the last time maybe_refresh_mirrors() ran, for throttling.
	 *
	 * @since 2.4.0
	 */
	const REFRESH_THROTTLE_OPTION = 'style_manager_local_fonts_refresh_ts';

	/**
	 * Stop retrying a family once its manifest `attempts` reaches this count.
	 *
	 * @since 2.4.0
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Local font store.
	 *
	 * @var LocalFontStore
	 */
	protected LocalFontStore $local_font_store;

	/**
	 * Style Manager Fonts.
	 *
	 * @var Fonts
	 */
	protected Fonts $sm_fonts;

	/**
	 * Cloud fonts.
	 *
	 * @var CloudFonts
	 */
	protected CloudFonts $cloud_fonts;

	/**
	 * Design assets.
	 *
	 * @var DesignAssets
	 */
	protected DesignAssets $design_assets;

	/**
	 * Plugin settings.
	 *
	 * @var PluginSettings
	 */
	protected PluginSettings $plugin_settings;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Per-request cache of family => cloud last_modified, built from the RAW
	 * cloud fonts payload.
	 *
	 * @var array<string, string>|null
	 */
	protected ?array $cloud_last_modified_cache = null;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 *
	 * @param LocalFontStore  $local_font_store Local font store.
	 * @param Fonts           $sm_fonts         Style Manager Fonts.
	 * @param CloudFonts      $cloud_fonts      Cloud fonts.
	 * @param DesignAssets    $design_assets    Design assets.
	 * @param PluginSettings  $plugin_settings  Plugin settings.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		LocalFontStore $local_font_store,
		Fonts $sm_fonts,
		CloudFonts $cloud_fonts,
		DesignAssets $design_assets,
		PluginSettings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->local_font_store = $local_font_store;
		$this->sm_fonts         = $sm_fonts;
		$this->cloud_fonts      = $cloud_fonts;
		$this->design_assets    = $design_assets;
		$this->plugin_settings  = $plugin_settings;
		$this->logger           = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * Everything is a no-op (no hooks added at all) unless both the
	 * `typography_cloud_fonts` and `typography_host_cloud_fonts_locally`
	 * settings are truthy.
	 *
	 * @since 2.4.0
	 */
	public function register_hooks() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// The Customizer save path.
		$this->add_action( 'customize_save_after', 'mirror_used_fonts', 10, 0 );

		// The Site Editor REST settings-save path (see Provider\SiteEditorEndpoints).
		$this->add_action( 'style_manager/settings_saved', 'mirror_used_fonts', 10, 0 );

		// Cron retry of specific families.
		$this->add_action( self::CRON_HOOK, 'mirror_families', 10, 1 );

		// Throttled periodic refresh of stale mirrors.
		$this->add_action( 'admin_init', 'maybe_refresh_mirrors', 10, 0 );
	}

	/**
	 * Whether font mirroring should do anything at all.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function is_enabled(): bool {
		return (bool) $this->plugin_settings->get( 'typography_cloud_fonts', 'yes' )
			&& (bool) $this->plugin_settings->get( 'typography_host_cloud_fonts_locally', 'yes' );
	}

	/**
	 * Mirror every currently-used cloud font family that isn't already healthy.
	 *
	 * Failures are collected and retried once via a single scheduled cron event
	 * (see mirror_families()).
	 *
	 * @since 2.4.0
	 */
	public function mirror_used_fonts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$failed = [];
		foreach ( $this->sm_fonts->get_used_cloud_font_families() as $family ) {
			if ( ! is_string( $family ) || '' === $family || $this->local_font_store->is_healthy( $family ) ) {
				continue;
			}

			if ( $this->mirror_family( $family ) ) {
				continue;
			}

			if ( ! $this->is_resolvable_from_cloud_config( $family ) ) {
				// Unresolvable from the cloud config (e.g. delisted): retrying on a
				// timer would never succeed and would never move an attempts
				// counter either, so it must not be scheduled at all.
				$this->logger->warning( sprintf( 'Style Manager: cannot mirror local font "%s": not found in the cloud font config.', $family ) );
				continue;
			}

			$failed[] = $family;
		}

		if ( ! empty( $failed ) ) {
			$this->schedule_retry( $failed, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Cron callback: retry mirroring the given families, honoring the attempts
	 * cap. Families still failing after the attempt are rescheduled with
	 * backoff at `time() + attempts * 30 * MINUTE_IN_SECONDS`.
	 *
	 * A family that is already healthy is normally skipped -- unless it was
	 * scheduled here because it is stale (the cloud's `last_modified` changed
	 * since it was mirrored, see maybe_refresh_mirrors()), in which case it is
	 * re-mirrored too. Without this, scheduled refreshes of healthy-but-stale
	 * families would never actually run.
	 *
	 * @since 2.4.0
	 *
	 * @param array $families Font family names to retry.
	 */
	public function mirror_families( array $families ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		foreach ( $families as $family ) {
			if ( ! is_string( $family ) || '' === $family ) {
				continue;
			}

			if ( $this->local_font_store->is_healthy( $family ) ) {
				$cloud_last_modified = $this->get_cloud_last_modified_map()[ $family ] ?? '';
				if ( ! $this->local_font_store->needs_refresh( $family, $cloud_last_modified ) ) {
					continue;
				}
			}

			$entry    = $this->local_font_store->get_entry( $family );
			$attempts = is_array( $entry ) ? (int) ( $entry['attempts'] ?? 0 ) : 0;
			if ( $attempts >= self::MAX_ATTEMPTS ) {
				// Give up retrying this family automatically; it needs manual attention.
				continue;
			}

			if ( $this->mirror_family( $family ) ) {
				continue;
			}

			if ( ! $this->is_resolvable_from_cloud_config( $family ) ) {
				// Same guard as mirror_used_fonts(): a family that is not (or no
				// longer) in the cloud config can never succeed here, so it must
				// not be rescheduled -- otherwise it retries every 30 minutes
				// forever with an attempts counter that never increments.
				$this->logger->warning( sprintf( 'Style Manager: cannot mirror local font "%s": not found in the cloud font config.', $family ) );
				continue;
			}

			$updated_entry    = $this->local_font_store->get_entry( $family );
			$updated_attempts = is_array( $updated_entry ) ? max( 1, (int) ( $updated_entry['attempts'] ?? 1 ) ) : 1;

			$this->schedule_retry( [ $family ], $updated_attempts * 30 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * `admin_init` callback, throttled to once per 12h: decides which families
	 * need a refresh -- healthy manifest families whose cloud `last_modified`
	 * has changed, plus any currently-used family that isn't healthy yet
	 * (attempts cap respected), whether or not it has ever been attempted
	 * before -- and schedules them all via the cron hook (see
	 * mirror_families()). This is how pre-existing sites get quietly migrated
	 * to local hosting in the background: no admin notice, just this
	 * throttled sweep picking up used-but-never-mirrored families over time.
	 * Never mirrors a family that is neither used nor already in the
	 * manifest -- an unused, never-attempted family is not this method's job.
	 *
	 * This method never mirrors inline: a stale-but-healthy mirror keeps
	 * working, so a refresh is never urgent enough to justify doing remote
	 * HTTP during an admin page load, which could stall the admin for
	 * several seconds. All mirroring happens out-of-band via the cron hook.
	 *
	 * @since 2.4.0
	 */
	public function maybe_refresh_mirrors(): void {
		if ( ! $this->is_enabled() || ! $this->should_run_refresh() ) {
			return;
		}

		update_option( self::REFRESH_THROTTLE_OPTION, time(), false );

		$last_modified_by_family = $this->get_cloud_last_modified_map();

		$to_refresh = [];
		foreach ( $this->local_font_store->get_manifest() as $family => $manifest_entry ) {
			if ( ! is_string( $family ) || '' === $family ) {
				continue;
			}

			$cloud_last_modified = $last_modified_by_family[ $family ] ?? '';
			if ( $this->local_font_store->needs_refresh( $family, $cloud_last_modified ) ) {
				$to_refresh[] = $family;
			}
		}

		foreach ( $this->sm_fonts->get_used_cloud_font_families() as $family ) {
			if ( ! is_string( $family ) || '' === $family || in_array( $family, $to_refresh, true ) ) {
				continue;
			}

			if ( $this->local_font_store->is_healthy( $family ) ) {
				continue;
			}

			// A used family absent from the manifest (never successfully
			// mirrored, e.g. added after cloud fonts were already enabled with
			// hosting toggled off) is scheduled here too -- this is the quiet
			// background migration path for pre-existing sites.
			$entry    = $this->local_font_store->get_entry( $family );
			$attempts = is_array( $entry ) ? (int) ( $entry['attempts'] ?? 0 ) : 0;
			if ( $attempts >= self::MAX_ATTEMPTS ) {
				continue;
			}

			$to_refresh[] = $family;
		}

		if ( empty( $to_refresh ) ) {
			return;
		}

		$this->schedule_retry( $to_refresh, MINUTE_IN_SECONDS );
	}

	/**
	 * Whether enough time has passed since the last maybe_refresh_mirrors() run.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function should_run_refresh(): bool {
		$last_run = (int) get_option( self::REFRESH_THROTTLE_OPTION, 0 );

		return ( time() - $last_run ) >= 12 * HOUR_IN_SECONDS;
	}

	/**
	 * Build a font_family => last_modified map from the RAW cloud fonts payload
	 * (DesignAssets::get_entry('cloud_fonts'), keyed by hashid with `font_family`
	 * and `last_modified` sub-entries) -- as opposed to CloudFonts::get_cloud_fonts()
	 * which is keyed by family and preprocessed (no `last_modified`). Cached per request.
	 *
	 * @since 2.4.0
	 *
	 * @return array<string, string>
	 */
	protected function get_cloud_last_modified_map(): array {
		if ( null !== $this->cloud_last_modified_cache ) {
			return $this->cloud_last_modified_cache;
		}

		$map = [];

		$raw = $this->design_assets->get_entry( 'cloud_fonts' );
		if ( is_array( $raw ) ) {
			foreach ( $raw as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['font_family'] ) ) {
					$map[ (string) $entry['font_family'] ] = (string) ( $entry['last_modified'] ?? '' );
				}
			}
		}

		$this->cloud_last_modified_cache = $map;

		return $map;
	}

	/**
	 * Mirror a single font family, using its current config from
	 * CloudFonts::get_cloud_fonts() -- preferring `remote_src` over `src` since
	 * the latter may already have been swapped to a local URL -- and the RAW
	 * cloud payload's `last_modified` for that family.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family name.
	 *
	 * @return bool True on success, false if the family is unknown or mirroring failed.
	 */
	protected function mirror_family( string $family ): bool {
		$font_config = $this->resolve_font_config( $family );
		if ( null === $font_config ) {
			return false;
		}

		$last_modified = $this->get_cloud_last_modified_map()[ $family ] ?? '';

		return $this->local_font_store->mirror_font( $font_config, $last_modified );
	}

	/**
	 * Whether a family is currently resolvable from CloudFonts::get_cloud_fonts()
	 * -- i.e. present and with a usable src -- as opposed to failing because the
	 * mirror attempt itself failed (network error, bad stylesheet, etc.).
	 *
	 * Used to distinguish "will never succeed, don't bother rescheduling" from
	 * "transient failure, worth retrying" in mirror_used_fonts() and
	 * mirror_families().
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family name.
	 *
	 * @return bool
	 */
	protected function is_resolvable_from_cloud_config( string $family ): bool {
		return null !== $this->resolve_font_config( $family );
	}

	/**
	 * Resolve a family's current, mirror-ready font config from
	 * CloudFonts::get_cloud_fonts() -- preferring `remote_src` over `src` since
	 * the latter may already have been swapped to a local URL.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family name.
	 *
	 * @return array|null The font config, or null if the family is unknown or has no usable src.
	 */
	protected function resolve_font_config( string $family ): ?array {
		$cloud_fonts = $this->cloud_fonts->get_cloud_fonts();
		if ( empty( $cloud_fonts[ $family ] ) || ! is_array( $cloud_fonts[ $family ] ) ) {
			return null;
		}

		$font_config = $cloud_fonts[ $family ];
		$src         = $font_config['remote_src'] ?? ( $font_config['src'] ?? '' );
		if ( empty( $src ) ) {
			return null;
		}

		$font_config['src'] = $src;
		unset( $font_config['remote_src'] );

		return $font_config;
	}

	/**
	 * Schedule a single retry event for the given families, guarding against
	 * scheduling an exact duplicate.
	 *
	 * @since 2.4.0
	 *
	 * @param array $families Font family names.
	 * @param int   $delay    Delay in seconds from now.
	 */
	protected function schedule_retry( array $families, int $delay ): void {
		if ( empty( $families ) ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, [ $families ] ) ) {
			return;
		}

		wp_schedule_single_event( time() + $delay, self::CRON_HOOK, [ $families ] );
	}
}
