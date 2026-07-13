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
	 * How many stale mirrors maybe_refresh_mirrors() re-mirrors inline per run;
	 * the rest are scheduled via the cron hook.
	 *
	 * @since 2.4.0
	 */
	const MAX_INLINE_REFRESHES_PER_RUN = 3;

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

			if ( ! $this->mirror_family( $family ) ) {
				$failed[] = $family;
			}
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
	 * @since 2.4.0
	 *
	 * @param array $families Font family names to retry.
	 */
	public function mirror_families( array $families ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		foreach ( $families as $family ) {
			if ( ! is_string( $family ) || '' === $family || $this->local_font_store->is_healthy( $family ) ) {
				continue;
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

			$updated_entry    = $this->local_font_store->get_entry( $family );
			$updated_attempts = is_array( $updated_entry ) ? max( 1, (int) ( $updated_entry['attempts'] ?? 1 ) ) : 1;

			$this->schedule_retry( [ $family ], $updated_attempts * 30 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * `admin_init` callback, throttled to once per 12h: re-mirrors healthy
	 * manifest families whose cloud `last_modified` has changed, and retries
	 * previously-failed USED families (attempts cap respected). Never mirrors a
	 * family that is neither already in the manifest nor currently used -- no
	 * silent retroactive migration of fonts that were never requested.
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

			// Only retry families that were already attempted (present in the
			// manifest, e.g. from a previous mirror_used_fonts() failure). A
			// family that was never mirrored is not this method's job.
			$entry = $this->local_font_store->get_entry( $family );
			if ( null === $entry ) {
				continue;
			}

			if ( (int) ( $entry['attempts'] ?? 0 ) >= self::MAX_ATTEMPTS ) {
				continue;
			}

			$to_refresh[] = $family;
		}

		if ( empty( $to_refresh ) ) {
			return;
		}

		$inline    = array_slice( $to_refresh, 0, self::MAX_INLINE_REFRESHES_PER_RUN );
		$scheduled = array_slice( $to_refresh, self::MAX_INLINE_REFRESHES_PER_RUN );

		foreach ( $inline as $family ) {
			$this->mirror_family( $family );
		}

		$this->schedule_retry( $scheduled, MINUTE_IN_SECONDS );
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
		$cloud_fonts = $this->cloud_fonts->get_cloud_fonts();
		if ( empty( $cloud_fonts[ $family ] ) || ! is_array( $cloud_fonts[ $family ] ) ) {
			return false;
		}

		$font_config = $cloud_fonts[ $family ];
		$src         = $font_config['remote_src'] ?? ( $font_config['src'] ?? '' );
		if ( empty( $src ) ) {
			return false;
		}

		$font_config['src'] = $src;
		unset( $font_config['remote_src'] );

		$last_modified = $this->get_cloud_last_modified_map()[ $family ] ?? '';

		return $this->local_font_store->mirror_font( $font_config, $last_modified );
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
