<?php
/**
 * This is the class that handles mirroring cloud fonts to local storage (uploads).
 *
 * @since   2.4.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Customize;

use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Mirrors cloud fonts (stylesheet + referenced font files) to the site's uploads
 * directory so visitors never need to connect to Pixelgrade Cloud to render them.
 *
 * @since 2.4.0
 */
class LocalFontStore {

	/**
	 * The option holding the local fonts manifest. Saved non-autoloaded.
	 *
	 * @since 2.4.0
	 */
	const OPTION_KEY = 'style_manager_local_fonts';

	/**
	 * The subdirectory (relative to the uploads basedir/baseurl) fonts are mirrored to.
	 *
	 * @since 2.4.0
	 */
	const BASE_SUBDIR = 'style-manager/fonts';

	/**
	 * File extensions allowed for anything downloaded from a stylesheet's
	 * `url()` references (relative or absolute). Anything else (e.g. `.php`)
	 * fails the whole mirror rather than being written verbatim into a
	 * web-reachable uploads directory.
	 *
	 * @since 2.4.0
	 */
	const ALLOWED_FILE_EXTENSIONS = [ 'woff2', 'woff', 'ttf', 'otf', 'eot', 'svg', 'css', 'txt' ];

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Per-request cache of health checks, keyed by font family.
	 *
	 * @var array<string, bool>
	 */
	protected static array $health_cache = [];

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Mirror a single (already preprocessed) cloud font to local storage.
	 *
	 * On success, the stylesheet and every font file it references are downloaded
	 * and saved under the uploads directory, and the manifest entry for the family
	 * is updated to `status = 'ok'`. On failure, nothing is written to disk and the
	 * manifest is updated to reflect the failure -- unless a previous successful
	 * mirror exists, in which case that working mirror is left untouched and only
	 * the attempts/last_error bookkeeping is updated.
	 *
	 * @since 2.4.0
	 *
	 * @param array  $font_config   Preprocessed font config (family, src, variants, category, fallback_stack, family_display).
	 * @param string $last_modified Optional. The cloud's `last_modified` value for this font, used for refresh diffing.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function mirror_font( array $font_config, string $last_modified = '' ): bool {
		$family = (string) ( $font_config['family'] ?? '' );
		if ( '' === $family || empty( $font_config['src'] ) ) {
			return false;
		}

		$url  = $this->normalize_to_https( (string) $font_config['src'] );
		$slug = $this->resolve_slug( self::derive_slug( $url ), $family, $url );

		$response = $this->remote_get( $url );
		if ( $this->is_error_response( $response ) ) {
			return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'Failed to fetch the font stylesheet.' );
		}

		$css = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $css ) || ( false === stripos( $css, '@font-face' ) && false === stripos( $css, 'url(' ) ) ) {
			return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'The stylesheet response does not look like CSS.' );
		}

		$slash_pos = strrpos( $url, '/' );
		$dir_url   = false !== $slash_pos ? substr( $url, 0, $slash_pos + 1 ) : $url;

		$downloads    = []; // Relative path (within the slug dir) => file contents.
		$css_rewrites = []; // Original absolute ref => rewritten (basename) ref.

		foreach ( self::extract_css_urls( $css ) as $ref ) {
			if ( $this->is_absolute_ref( $ref ) ) {
				$file_url = $this->normalize_to_https( $ref );
				$basename = basename( (string) wp_parse_url( $file_url, PHP_URL_PATH ) );
				if ( '' === $basename ) {
					return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'Could not resolve a filename for an absolute font URL.' );
				}
				$rel_path             = $basename;
				$css_rewrites[ $ref ] = $basename;
			} else {
				if ( '' === $ref || str_contains( $ref, '..' ) || str_starts_with( $ref, '/' ) ) {
					return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'Rejected an unsafe relative font path in the stylesheet.' );
				}
				// Download from the full original ref (query intact), but the
				// on-disk path never includes the query/fragment -- neither
				// affects the filesystem path a browser resolves to, and
				// keeping them would write literal filenames like `font.eot?#iefix`.
				$file_url = $dir_url . $ref;
				$rel_path = self::strip_query_and_fragment( $ref );
				if ( '' === $rel_path ) {
					return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'Rejected an unsafe relative font path in the stylesheet.' );
				}
			}

			if ( ! self::has_allowed_extension( $rel_path ) ) {
				return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, sprintf( 'Rejected a disallowed file extension: %s', $rel_path ) );
			}

			if ( array_key_exists( $rel_path, $downloads ) ) {
				// Already downloaded under this on-disk path (e.g. a `?#iefix`
				// ref alongside a plain ref to the same file).
				continue;
			}

			$file_response = $this->remote_get( $file_url );
			if ( $this->is_error_response( $file_response ) ) {
				return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, sprintf( 'Failed to fetch font file: %s', $file_url ) );
			}

			$file_body = (string) wp_remote_retrieve_body( $file_response );
			if ( '' === $file_body ) {
				return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, sprintf( 'Empty font file response: %s', $file_url ) );
			}

			$downloads[ $rel_path ] = $file_body;
		}

		foreach ( $css_rewrites as $original => $replacement ) {
			$css = str_replace( $original, $replacement, $css );
		}

		// Best-effort sibling license/attribution files. Never affect success.
		foreach ( [ 'LICENSE.txt', 'SOURCE.txt' ] as $sibling ) {
			$sibling_response = $this->remote_get( $dir_url . $sibling );
			if ( $this->is_error_response( $sibling_response ) ) {
				continue;
			}
			$sibling_body = (string) wp_remote_retrieve_body( $sibling_response );
			if ( '' !== $sibling_body ) {
				$downloads[ $sibling ] = $sibling_body;
			}
		}

		$base_rel_dir = self::BASE_SUBDIR . '/' . $slug;
		$upload_dir   = wp_get_upload_dir();
		$base_abs_dir = trailingslashit( (string) $upload_dir['basedir'] ) . $base_rel_dir;

		wp_mkdir_p( $base_abs_dir );

		$written_files = [];
		foreach ( $downloads as $rel_path => $contents ) {
			$absolute_path = $base_abs_dir . '/' . $rel_path;
			$sub_dir       = dirname( $absolute_path );
			if ( $sub_dir !== $base_abs_dir ) {
				wp_mkdir_p( $sub_dir );
			}
			if ( ! $this->write_file( $absolute_path, $contents ) ) {
				return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, sprintf( 'Failed to write font file: %s', $rel_path ) );
			}
			$written_files[] = $base_rel_dir . '/' . $rel_path;
		}

		$stylesheet_rel_path = $base_rel_dir . '/stylesheet.css';
		if ( ! $this->write_file( $base_abs_dir . '/stylesheet.css', $css ) ) {
			return $this->handle_mirror_failure( $family, $font_config, $last_modified, $url, $slug, 'Failed to write the stylesheet.' );
		}

		// Standard WP uploads-dir convention: guard the directory listing.
		// Best-effort -- never affects success, and deliberately not part of
		// $downloads (which is subject to the extension allowlist above) nor
		// of the manifest's tracked `files` list, since it isn't a font asset.
		$this->write_file( $base_abs_dir . '/index.php', "<?php // Silence is golden.\n" );

		$manifest            = $this->get_manifest();
		$manifest[ $family ] = [
			'slug'              => $slug,
			'source_stylesheet' => $url,
			'stylesheet_path'   => $stylesheet_rel_path,
			'files'             => $written_files,
			'variants'          => $font_config['variants'] ?? [],
			'category'          => $font_config['category'] ?? '',
			'fallback_stack'    => $font_config['fallback_stack'] ?? '',
			'family_display'    => $font_config['family_display'] ?? '',
			'last_modified'     => $last_modified,
			'mirrored_at'       => time(),
			'status'            => 'ok',
			'attempts'          => 0,
			'last_error'        => '',
		];

		update_option( self::OPTION_KEY, $manifest, false );
		unset( self::$health_cache[ $family ] );

		return true;
	}

	/**
	 * Get the local (uploads) URL for a font family's stylesheet, if it is healthy.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family.
	 *
	 * @return string|null
	 */
	public function get_local_src( string $family ): ?string {
		if ( ! $this->is_healthy( $family ) ) {
			return null;
		}

		$entry      = $this->get_entry( $family );
		$upload_dir = wp_get_upload_dir();

		return trailingslashit( (string) $upload_dir['baseurl'] ) . ltrim( (string) $entry['stylesheet_path'], '/' );
	}

	/**
	 * Whether a family has a healthy local mirror: manifest status 'ok' and the
	 * stylesheet file actually exists on disk. Cached per request.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family.
	 *
	 * @return bool
	 */
	public function is_healthy( string $family ): bool {
		if ( array_key_exists( $family, self::$health_cache ) ) {
			return self::$health_cache[ $family ];
		}

		$healthy = false;
		$entry   = $this->get_entry( $family );
		if ( null !== $entry && 'ok' === ( $entry['status'] ?? '' ) && ! empty( $entry['stylesheet_path'] ) ) {
			$upload_dir = wp_get_upload_dir();
			$absolute   = trailingslashit( (string) $upload_dir['basedir'] ) . ltrim( (string) $entry['stylesheet_path'], '/' );
			$healthy    = file_exists( $absolute );
		}

		self::$health_cache[ $family ] = $healthy;

		return $healthy;
	}

	/**
	 * Get the manifest entry for a font family.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family Font family.
	 *
	 * @return array|null
	 */
	public function get_entry( string $family ): ?array {
		$manifest = $this->get_manifest();

		return $manifest[ $family ] ?? null;
	}

	/**
	 * Get the entire local fonts manifest.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_manifest(): array {
		$manifest = get_option( self::OPTION_KEY, [] );

		return is_array( $manifest ) ? $manifest : [];
	}

	/**
	 * Whether a healthy, locally mirrored font should be refreshed because the
	 * cloud's `last_modified` value has changed.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family              Font family.
	 * @param string $cloud_last_modified The `last_modified` value currently reported by the cloud.
	 *
	 * @return bool
	 */
	public function needs_refresh( string $family, string $cloud_last_modified ): bool {
		if ( '' === $cloud_last_modified || ! $this->is_healthy( $family ) ) {
			return false;
		}

		$entry = $this->get_entry( $family );

		return ( $entry['last_modified'] ?? '' ) !== $cloud_last_modified;
	}

	/**
	 * Extract every `url( ... )` reference out of a CSS string.
	 *
	 * Handles single/double/no quotes, trims whitespace, dedupes, and skips
	 * `data:` URIs and empty refs. Refs are returned as found (relative or absolute).
	 *
	 * @since 2.4.0
	 *
	 * @param string $css CSS content.
	 *
	 * @return string[]
	 */
	public static function extract_css_urls( string $css ): array {
		if ( '' === trim( $css ) ) {
			return [];
		}

		if ( ! preg_match_all( '/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $css, $matches ) ) {
			return [];
		}

		$refs = [];
		foreach ( $matches[2] as $ref ) {
			$ref = trim( $ref );
			if ( '' === $ref || 0 === stripos( $ref, 'data:' ) ) {
				continue;
			}
			if ( ! in_array( $ref, $refs, true ) ) {
				$refs[] = $ref;
			}
		}

		return $refs;
	}

	/**
	 * Derive a filesystem-safe slug from a stylesheet URL: the last path segment
	 * before the filename (e.g. `.../cloud-fonts-v2/uncut-sans/stylesheet.css` =>
	 * `uncut-sans`). Falls back to an md5 hash of the URL if nothing usable is found.
	 *
	 * @since 2.4.0
	 *
	 * @param string $stylesheet_url Stylesheet URL (absolute or protocol-relative).
	 *
	 * @return string
	 */
	public static function derive_slug( string $stylesheet_url ): string {
		$url = $stylesheet_url;
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$segments = array_values( array_filter( explode( '/', $path ), static fn( string $segment ): bool => '' !== $segment ) );

		// Drop the filename segment (e.g. stylesheet.css); the slug is the segment before it.
		array_pop( $segments );
		$raw = ! empty( $segments ) ? (string) end( $segments ) : '';

		$slug = self::sanitize_slug( $raw );
		if ( '' === $slug ) {
			$slug = md5( $stylesheet_url );
		}

		return $slug;
	}

	/**
	 * Disambiguate a derived slug against the manifest so two different families
	 * whose stylesheet URLs happen to share the same last path segment (e.g. across
	 * cloud generations) never mirror into the same directory and clobber each
	 * other's files.
	 *
	 * The plain slug is kept when no other family's manifest entry already claims
	 * it, when the family re-mirrors itself, or when another family's entry uses
	 * the exact same source stylesheet URL. Otherwise the slug is suffixed with a
	 * short hash of the (normalized) URL so both mirrors coexist.
	 *
	 * @since 2.4.0
	 *
	 * @param string $slug   The slug derived from the stylesheet URL.
	 * @param string $family The font family being mirrored.
	 * @param string $url    The normalized (https) stylesheet URL for this family.
	 *
	 * @return string
	 */
	protected function resolve_slug( string $slug, string $family, string $url ): string {
		foreach ( $this->get_manifest() as $other_family => $entry ) {
			if ( $other_family === $family || ! is_array( $entry ) ) {
				continue;
			}

			$other_slug   = (string) ( $entry['slug'] ?? '' );
			$other_source = (string) ( $entry['source_stylesheet'] ?? '' );

			if ( $other_slug === $slug && $other_source !== $url ) {
				return $slug . '-' . substr( md5( $url ), 0, 8 );
			}
		}

		return $slug;
	}

	/**
	 * Sanitize a raw string into a slug: lowercase, alphanumerics/underscore/hyphen only.
	 *
	 * @since 2.4.0
	 *
	 * @param string $raw Raw string.
	 *
	 * @return string
	 */
	private static function sanitize_slug( string $raw ): string {
		$slug = strtolower( $raw );
		$slug = (string) preg_replace( '/[^a-z0-9_\-]+/', '-', $slug );

		return trim( $slug, '-' );
	}

	/**
	 * Whether a CSS `url()` ref is absolute (protocol-relative or http(s)).
	 *
	 * @since 2.4.0
	 *
	 * @param string $ref The ref as found in the stylesheet.
	 *
	 * @return bool
	 */
	protected function is_absolute_ref( string $ref ): bool {
		return (bool) preg_match( '#^(https?:)?//#i', $ref );
	}

	/**
	 * Strip the query string and/or fragment off a CSS `url()` ref, e.g.
	 * `font.eot?#iefix` => `font.eot`. Used when computing the on-disk relative
	 * path for a ref (and for deduping): the query/fragment are never part of
	 * the filesystem path a browser request resolves to, so keeping them would
	 * write literal filenames like `font.eot?#iefix` to disk. The full original
	 * ref (query intact) is still used for the actual download.
	 *
	 * @since 2.4.0
	 *
	 * @param string $ref The ref as found in the stylesheet.
	 *
	 * @return string
	 */
	protected static function strip_query_and_fragment( string $ref ): string {
		return substr( $ref, 0, strcspn( $ref, '?#' ) );
	}

	/**
	 * Whether a relative on-disk path's file extension is on the allowlist of
	 * font/text asset types. Guards against a malicious (or compromised)
	 * stylesheet referencing an arbitrary file (e.g. `url(backdoor.php)`) that
	 * would otherwise be written verbatim into a web-reachable uploads directory.
	 *
	 * @since 2.4.0
	 *
	 * @param string $rel_path The on-disk relative path (or any filename/ref) to check.
	 *
	 * @return bool
	 */
	protected static function has_allowed_extension( string $rel_path ): bool {
		$extension = strtolower( (string) pathinfo( $rel_path, PATHINFO_EXTENSION ) );

		return in_array( $extension, self::ALLOWED_FILE_EXTENSIONS, true );
	}

	/**
	 * Normalize a URL to https: protocol-relative (`//host/...`) or plain `http://`
	 * become `https://`. Anything else is returned unchanged.
	 *
	 * @since 2.4.0
	 *
	 * @param string $url URL to normalize.
	 *
	 * @return string
	 */
	protected function normalize_to_https( string $url ): string {
		if ( str_starts_with( $url, '//' ) ) {
			return 'https:' . $url;
		}
		if ( str_starts_with( $url, 'http://' ) ) {
			return 'https://' . substr( $url, 7 );
		}

		return $url;
	}

	/**
	 * Whether a remote response should be treated as a failure: a WP_Error, or a
	 * non-200 status code.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $response The return value of remote_get().
	 *
	 * @return bool
	 */
	protected function is_error_response( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return true;
		}

		return 200 !== (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Record a failed mirror attempt in the manifest.
	 *
	 * If a previous successful mirror exists for the family, it is left untouched
	 * (status stays 'ok', files/paths are preserved) and only attempts/last_error
	 * are updated -- a failed refresh must never break a working mirror. Otherwise
	 * the entry is marked 'failed'.
	 *
	 * @since 2.4.0
	 *
	 * @param string $family        Font family.
	 * @param array  $font_config   The font config passed to mirror_font().
	 * @param string $last_modified The `last_modified` value passed to mirror_font().
	 * @param string $url           The normalized (https) stylesheet URL.
	 * @param string $slug          The derived slug.
	 * @param string $error         Human-readable error message.
	 *
	 * @return false Always false, for convenient `return $this->handle_mirror_failure(...);` use.
	 */
	protected function handle_mirror_failure( string $family, array $font_config, string $last_modified, string $url, string $slug, string $error ): bool {
		$manifest = $this->get_manifest();
		$prior    = $manifest[ $family ] ?? null;

		if ( is_array( $prior ) && 'ok' === ( $prior['status'] ?? '' ) ) {
			$entry              = $prior;
			$entry['attempts']  = (int) ( $prior['attempts'] ?? 0 ) + 1;
			$entry['last_error'] = $error;
		} else {
			$entry = [
				'slug'              => is_array( $prior ) ? ( $prior['slug'] ?? $slug ) : $slug,
				'source_stylesheet' => $url,
				'stylesheet_path'   => is_array( $prior ) ? ( $prior['stylesheet_path'] ?? '' ) : '',
				'files'             => is_array( $prior ) ? ( $prior['files'] ?? [] ) : [],
				'variants'          => $font_config['variants'] ?? [],
				'category'          => $font_config['category'] ?? '',
				'fallback_stack'    => $font_config['fallback_stack'] ?? '',
				'family_display'    => $font_config['family_display'] ?? '',
				'last_modified'     => $last_modified,
				'mirrored_at'       => is_array( $prior ) ? ( $prior['mirrored_at'] ?? 0 ) : 0,
				'status'            => 'failed',
				'attempts'          => is_array( $prior ) ? ( (int) ( $prior['attempts'] ?? 0 ) + 1 ) : 1,
				'last_error'        => $error,
			];
		}

		$manifest[ $family ] = $entry;
		update_option( self::OPTION_KEY, $manifest, false );
		unset( self::$health_cache[ $family ] );

		$this->logger->warning( sprintf( 'Style Manager: failed to mirror local font "%s": %s', $family, $error ) );

		return false;
	}

	/**
	 * Write a file to an absolute path. Protected so unit tests can mock this seam
	 * instead of touching the real filesystem.
	 *
	 * @since 2.4.0
	 *
	 * @param string $absolute_path Absolute file path.
	 * @param string $contents      File contents.
	 *
	 * @return bool
	 */
	protected function write_file( string $absolute_path, string $contents ): bool {
		return false !== file_put_contents( $absolute_path, $contents );
	}

	/**
	 * Perform a remote GET request. Protected so unit tests can mock this seam
	 * instead of hitting the network.
	 *
	 * @since 2.4.0
	 *
	 * @param string $url URL to fetch.
	 *
	 * @return array|\WP_Error
	 */
	protected function remote_get( string $url ) {
		return wp_remote_get( $url, [ 'timeout' => 10 ] );
	}
}
