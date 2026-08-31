<?php
/**
 * WP-CLI commands provider.
 *
 * Registers the `wp pixelgrade sm` subtree — Style Manager's slice of the Pixelgrade
 * agent-surface contract. Every command speaks the shared JSON envelope and the
 * 0/1/2/3 exit semantics, resolves a user before doing anything, and writes only
 * through Provider\SettingsWriter so the Pixelgrade Plus gate and the post-save
 * fan-out can never be bypassed.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.3.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Registers Style Manager's WP-CLI commands.
 *
 * @since 2.3.0
 */
class CliCommands extends AbstractHookProvider {

	/**
	 * The capability every command in this subtree requires.
	 */
	public const CAPABILITY = 'edit_theme_options';

	/**
	 * Master font slots. Writing any of them regenerates the entire per-element
	 * defaults table, so they carry destructive semantics (contract §3.4/§3.6).
	 */
	public const MASTER_FONT_SLOT_IDS = [
		'sm_font_primary',
		'sm_font_secondary',
		'sm_font_body',
		'sm_font_accent',
		FontPalettes::SM_FONT_PALETTE_OPTION_KEY,
	];

	/**
	 * Options provider.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Headless Customizer.
	 *
	 * @var HeadlessCustomizer
	 */
	protected HeadlessCustomizer $headless_customizer;

	/**
	 * The one settings write path.
	 *
	 * @var SettingsWriter
	 */
	protected SettingsWriter $settings_writer;

	/**
	 * Font palettes.
	 *
	 * @var FontPalettes
	 */
	protected FontPalettes $font_palettes;

	/**
	 * Create the WP-CLI commands provider.
	 *
	 * @since 2.3.0
	 *
	 * @param Options            $options             Options provider.
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param SettingsWriter     $settings_writer     Settings writer.
	 * @param FontPalettes       $font_palettes       Font palettes.
	 */
	public function __construct(
		Options $options,
		HeadlessCustomizer $headless_customizer,
		SettingsWriter $settings_writer,
		FontPalettes $font_palettes
	) {
		$this->options             = $options;
		$this->headless_customizer = $headless_customizer;
		$this->settings_writer     = $settings_writer;
		$this->font_palettes       = $font_palettes;
	}

	/**
	 * Register the commands.
	 *
	 * Style Manager registers its own `pixelgrade sm` subtree; WP-CLI composes the
	 * `pixelgrade` namespace from whatever subtrees the active plugins provide, so
	 * there is no root-command owner and no cross-plugin dependency.
	 *
	 * @since 2.3.0
	 */
	public function register_hooks() {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'pixelgrade sm get', [ $this, 'get' ] );
		\WP_CLI::add_command( 'pixelgrade sm set', [ $this, 'set' ] );
		\WP_CLI::add_command( 'pixelgrade sm export', [ $this, 'export' ] );
		\WP_CLI::add_command( 'pixelgrade sm structure', [ $this, 'structure' ] );
		\WP_CLI::add_command( 'pixelgrade sm apply-font-palette', [ $this, 'apply_font_palette' ] );
		\WP_CLI::add_command( 'pixelgrade sm apply-color-palette', [ $this, 'apply_color_palette' ] );
		\WP_CLI::add_command( 'pixelgrade sm flush-cache', [ $this, 'flush_cache' ] );

		// Deprecated alias of `wp pixelgrade sm flush-cache`. Same callable, same behavior.
		\WP_CLI::add_command( 'style-manager flush-cache', [ $this, 'flush_cache_deprecated' ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Commands
	|--------------------------------------------------------------------------
	*/

	/**
	 * Read Style Manager design settings through the three-store resolver.
	 *
	 * Values resolve exactly the way the Customizer resolves them (standalone option
	 * row, aggregated opt-name array, or theme_mod) — never `wp option get`.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Setting ids to read. Omit and pass --all or --section instead.
	 *
	 * [--all]
	 * : Return every registered setting the current user can read.
	 *
	 * [--section=<id>]
	 * : Restrict to the settings attached to this Customizer section's controls.
	 *
	 * [--details]
	 * : Return the full settings data (value, transport, dirty, type, connected_fields)
	 * instead of a plain id => value map.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok · 1 invalid_params · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm get --all --format=json --user=admin
	 *     wp pixelgrade sm get sm_font_primary sm_color_primary --format=json --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function get( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$details = $this->bool_flag( $assoc_args, 'details' );
		$section = $this->flag( $assoc_args, 'section' );
		$all     = $this->bool_flag( $assoc_args, 'all' );

		$source = $details
			? $this->headless_customizer->get_settings_data()
			: $this->headless_customizer->get_settings_values();

		$ids = array_map( 'strval', (array) $args );
		if ( is_string( $section ) && '' !== $section ) {
			$ids = array_merge( $ids, $this->headless_customizer->get_section_setting_ids( $section ) );
		}
		$ids = array_values( array_unique( $ids ) );

		if ( empty( $ids ) && ! $all ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( 'Nothing to read: pass one or more setting ids, --section=<id>, or --all.', '__plugin_txtd' )
			);
		}

		if ( $all && empty( $ids ) ) {
			$settings = $source;
		} else {
			$settings = [];
			$missing  = [];
			foreach ( $ids as $id ) {
				if ( array_key_exists( $id, $source ) ) {
					$settings[ $id ] = $source[ $id ];
				} else {
					$missing[] = $id;
				}
			}

			if ( ! empty( $missing ) ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: comma separated list of setting ids. */
						__( 'Unknown or capability-denied setting ids: %s.', '__plugin_txtd' ),
						implode( ', ', $missing )
					),
					[ 'unknown' => $missing ]
				);
			}
		}

		$this->emit(
			$assoc_args,
			0,
			'ok',
			sprintf(
				/* translators: %d: number of settings. */
				_n( 'Read %d setting.', 'Read %d settings.', count( $settings ), '__plugin_txtd' ),
				count( $settings )
			),
			[
				'details'  => $details,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Write Style Manager design settings through the gated write path.
	 *
	 * Runs the Pixelgrade Plus save gate, publishes a changeset with the Customizer's
	 * own sanitization, applies the post-save fan-out, flushes the caches and then
	 * RE-READS every requested id to report `persisted` / `unchanged` / `stripped`.
	 * Re-running an identical `set` reports every id as unchanged (`noop`, exit 0).
	 *
	 * Values are parsed as JSON when they parse as JSON, otherwise kept as strings —
	 * so `sm_font_primary='{"font_family":"Lato"}'` writes an object and
	 * `sm_font_sizing=smaller` writes a string. Quote a numeric string as '"12"'.
	 *
	 * ## OPTIONS
	 *
	 * [<assignment>...]
	 * : One or more `<setting-id>=<value>` pairs. A lone `-` reads a JSON map from STDIN.
	 *
	 * [--from-file=<path>]
	 * : Read the id => value map from a JSON file, or from STDIN with `-`. Accepts both
	 * a bare `{id: value}` map and a stamped `wp pixelgrade sm export` payload
	 * (the `settings` object is unwrapped when `meta` is present).
	 *
	 * [--dry-run]
	 * : Report the predicted diff without writing. Never prompts, never needs --yes.
	 *
	 * [--yes]
	 * : Required when the payload carries a master font slot (sm_font_primary|secondary|body|accent,
	 * sm_font_palette): such a write regenerates the whole per-element defaults table.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok|noop · 2 plus_stripped (anything requested but not persisted) ·
	 * 1 invalid_params|ordering_conflict · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm set sm_font_sizing=smaller --format=json --user=admin
	 *     wp pixelgrade sm set --from-file=design.json --yes --format=json --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function set( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$values = $this->collect_set_payload( (array) $args, $assoc_args );

		if ( empty( $values ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( 'Nothing to write: pass <id>=<value> pairs, --from-file=<path>, or `-` for STDIN.', '__plugin_txtd' )
			);
		}

		$this->assert_no_ordering_conflict( $values, $assoc_args );
		$this->assert_letter_spacing_units( $values, $assoc_args );

		$dry_run = $this->bool_flag( $assoc_args, 'dry-run' );

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			if ( array_intersect( array_keys( $values ), self::MASTER_FONT_SLOT_IDS ) ) {
				$this->confirm_destructive(
					$assoc_args,
					__( 'This payload carries a master font slot; saving it regenerates the entire per-element font defaults table and clobbers per-element overrides.', '__plugin_txtd' )
				);
			}

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				$this->fail_from_wp_error( $assoc_args, $result, $values );
			}
		}

		$this->emit_write_result( $assoc_args, $result, array_keys( $values ), $dry_run );
	}

	/**
	 * Export the whole design system as a stamped, re-importable JSON payload.
	 *
	 * The payload is `{ "meta": { plugin_version, theme, theme_version, exported_at },
	 * "settings": { "<id>": <value> } }`. Feed it straight back to
	 * `wp pixelgrade sm set --from-file=<path>` — that is the whole import story.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write the payload to this file instead of only returning it.
	 *
	 * [--include=<ids>]
	 * : Comma-separated setting ids to include. Default: every readable setting.
	 *
	 * [--pretty]
	 * : Pretty-print the payload written to --file.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok · 1 invalid_params · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm export --file=design.json --pretty --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$settings = $this->headless_customizer->get_settings_values();

		$include = $this->flag( $assoc_args, 'include' );
		if ( is_string( $include ) && '' !== $include ) {
			$wanted   = array_filter( array_map( 'trim', explode( ',', $include ) ) );
			$filtered = [];
			$missing  = [];
			foreach ( $wanted as $id ) {
				if ( array_key_exists( $id, $settings ) ) {
					$filtered[ $id ] = $settings[ $id ];
				} else {
					$missing[] = $id;
				}
			}

			if ( ! empty( $missing ) ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: comma separated list of setting ids. */
						__( 'Unknown or capability-denied setting ids: %s.', '__plugin_txtd' ),
						implode( ', ', $missing )
					),
					[ 'unknown' => $missing ]
				);
			}

			$settings = $filtered;
		}

		$theme   = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$payload = [
			'meta'     => [
				'plugin_version' => defined( '\Pixelgrade\StyleManager\VERSION' ) ? \Pixelgrade\StyleManager\VERSION : '',
				'theme'          => function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '',
				'theme_version'  => $theme ? (string) $theme->get( 'Version' ) : '',
				'exported_at'    => gmdate( 'c' ),
			],
			'settings' => $settings,
		];

		$data = $payload;
		$file = $this->flag( $assoc_args, 'file' );
		if ( is_string( $file ) && '' !== $file ) {
			$json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
			if ( $this->bool_flag( $assoc_args, 'pretty' ) ) {
				$json_flags |= JSON_PRETTY_PRINT;
			}

			$written = @file_put_contents( $file, (string) wp_json_encode( $payload, $json_flags ) . "\n" );
			if ( false === $written ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: file path. */
						__( 'Could not write the export to %s.', '__plugin_txtd' ),
						$file
					)
				);
			}

			$data['file'] = $file;
		}

		$this->emit(
			$assoc_args,
			0,
			'ok',
			sprintf(
				/* translators: %d: number of settings. */
				_n( 'Exported %d setting.', 'Exported %d settings.', count( $settings ), '__plugin_txtd' ),
				count( $settings )
			),
			$data
		);
	}

	/**
	 * Describe the Style Manager panels, sections and controls.
	 *
	 * Payload: `{ "panels": { "<id>": {id,title,description,priority} }, "sections": [
	 * {id,title,description,priority,panel,"controls":[{id,type,html?,active}]} ] }`.
	 * The `html` key is omitted unless --with-html — the control markup is heavy.
	 *
	 * ## OPTIONS
	 *
	 * [--section=<id>]
	 * : Only describe this section.
	 *
	 * [--with-html]
	 * : Include the rendered control markup.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok · 1 invalid_params · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm structure --format=json --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function structure( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$structure = $this->headless_customizer->get_structure();
		$section   = $this->flag( $assoc_args, 'section' );

		if ( is_string( $section ) && '' !== $section ) {
			$structure['sections'] = array_values(
				array_filter(
					$structure['sections'],
					static function ( $item ) use ( $section ) {
						return isset( $item['id'] ) && (string) $item['id'] === $section;
					}
				)
			);

			if ( empty( $structure['sections'] ) ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: Customizer section id. */
						__( 'Unknown Style Manager section: %s.', '__plugin_txtd' ),
						$section
					)
				);
			}
		}

		if ( ! $this->bool_flag( $assoc_args, 'with-html' ) ) {
			foreach ( $structure['sections'] as $section_index => $section_data ) {
				foreach ( (array) ( $section_data['controls'] ?? [] ) as $control_index => $control ) {
					unset( $structure['sections'][ $section_index ]['controls'][ $control_index ]['html'] );
				}
			}
		}

		$this->emit(
			$assoc_args,
			0,
			'ok',
			sprintf(
				/* translators: %d: number of sections. */
				_n( 'Described %d section.', 'Described %d sections.', count( $structure['sections'] ), '__plugin_txtd' ),
				count( $structure['sections'] )
			),
			$structure
		);
	}

	/**
	 * Apply a font palette and fan it out to every connected per-element font field.
	 *
	 * Destructive: the fan-out rewrites the per-element font defaults table, clobbering
	 * per-element overrides. A tier-locked (pro) palette on a site without the Pixelgrade
	 * Plus entitlement is dropped by the save gate and reported as
	 * `stripped[].reason: "tier_locked_palette"`, exit 2.
	 *
	 * ## OPTIONS
	 *
	 * <palette-id>
	 * : The font palette id, e.g. `julia`.
	 *
	 * [--dry-run]
	 * : Report the predicted diff without writing. Never prompts, never needs --yes.
	 *
	 * [--yes]
	 * : Confirm the destructive fan-out. Mandatory in any non-interactive context.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok|noop · 2 plus_stripped (tier-locked palette or stripped fields) ·
	 * 1 invalid_params · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm apply-font-palette julia --yes --format=json --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function apply_font_palette( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$palette_id = isset( $args[0] ) ? (string) $args[0] : '';
		if ( '' === $palette_id ) {
			$this->fail( $assoc_args, 1, 'invalid_params', __( 'Pass a font palette id.', '__plugin_txtd' ) );
		}

		$palettes = $this->font_palettes->get_palettes_for_control();
		if ( ! isset( $palettes[ $palette_id ] ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: 1: requested palette id, 2: comma separated list of known ids. */
					__( 'Unknown font palette "%1$s". Known palettes: %2$s.', '__plugin_txtd' ),
					$palette_id,
					implode( ', ', array_keys( $palettes ) )
				)
			);
		}

		$values  = [ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => $palette_id ];
		$dry_run = $this->bool_flag( $assoc_args, 'dry-run' );

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			$this->confirm_destructive(
				$assoc_args,
				sprintf(
					/* translators: %s: font palette id. */
					__( 'Applying the "%s" font palette rewrites every connected per-element font field.', '__plugin_txtd' ),
					$palette_id
				)
			);

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				$this->fail_from_wp_error( $assoc_args, $result, $values );
			}
		}

		$this->emit_write_result(
			$assoc_args,
			$result,
			array_keys( $values ),
			$dry_run,
			[
				'palette'          => $palette_id,
				'connected_fields' => $result['connected_fields'] ?? [],
			]
		);
	}

	/**
	 * Regenerate the Color System palette output from a palette source.
	 *
	 * Requires the bundled Node palette generator (contract §3.11) — the build artifact
	 * that runs the same `getPalettesFromColors()` module the Customizer bundle runs.
	 * Until that shim ships, this command registers but fails gracefully with
	 * `code: "generator_unavailable"`, exit 1. It never writes a stale palette output.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<source>]
	 * : The `sm_advanced_palette_source` JSON, `@<file>` or `-` for STDIN. Required.
	 *
	 * [--output=<output>]
	 * : Write the generated output to `<file>` instead of only persisting it.
	 *
	 * [--variation=<n>]
	 * : The `sm_site_color_variation` to persist alongside.
	 *
	 * [--custom]
	 * : Also set `sm_is_custom_color_palette`.
	 *
	 * [--generator=<generator>]
	 * : `node` (default) or `none`.
	 *
	 * [--dry-run]
	 * : Report the predicted diff without writing.
	 *
	 * [--yes]
	 * : Confirm the destructive regeneration. Mandatory in any non-interactive context.
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok|noop · 2 plus_stripped · 1 generator_unavailable|invalid_params · 3 permission_denied
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function apply_color_palette( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$looked_for = $this->palette_generator_candidates();

		$this->fail(
			$assoc_args,
			1,
			'generator_unavailable',
			sprintf(
				/* translators: %s: comma separated list of paths that were probed. */
				__( 'The bundled Node palette generator is not available; looked for: %s. Nothing was written.', '__plugin_txtd' ),
				implode( ', ', $looked_for )
			),
			[ 'looked_for' => $looked_for ]
		);
	}

	/**
	 * Flush Style Manager's cached Customizer config and option details.
	 *
	 * Use after changing option or section definitions in code so the cached
	 * config is rebuilt on the next request — instead of bumping the plugin
	 * version, waiting for the cache to expire, or deleting options by hand.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok · 3 permission_denied
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm flush-cache --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function flush_cache( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$this->options->invalidate_all_caches();

		$this->emit(
			$assoc_args,
			0,
			'ok',
			__( 'Style Manager caches flushed (Customizer config, option details, opt-name).', '__plugin_txtd' )
		);
	}

	/**
	 * Deprecated alias of `wp pixelgrade sm flush-cache`.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function flush_cache_deprecated( $args, $assoc_args ) {
		// STDERR, so `--format=json | jq` still sees only the envelope on STDOUT.
		\WP_CLI::warning( __( '`wp style-manager flush-cache` is deprecated; use `wp pixelgrade sm flush-cache`.', '__plugin_txtd' ) );

		$this->flush_cache( $args, $assoc_args );
	}

	/*
	|--------------------------------------------------------------------------
	| Contract plumbing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Contract §3.0 — resolve the user first; never auto-elevate.
	 *
	 * WP-CLI runs as no user unless `--user` is passed, and the wrapped internals then
	 * fail silently: an anonymous `get` returns an empty map that looks like success.
	 * So refuse loudly instead, naming the capability and the fix.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $capability Required capability.
	 */
	protected function require_user( array $assoc_args, string $capability = self::CAPABILITY ): void {
		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 ) {
			$this->fail(
				$assoc_args,
				3,
				'permission_denied',
				sprintf(
					/* translators: %s: capability name. */
					__( 'No user resolved. This command needs the `%s` capability — re-run with --user=<admin>.', '__plugin_txtd' ),
					$capability
				),
				[ 'capability' => $capability ]
			);
		}

		if ( ! current_user_can( $capability ) ) {
			$this->fail(
				$assoc_args,
				3,
				'permission_denied',
				sprintf(
					/* translators: 1: user id, 2: capability name. */
					__( 'User %1$d lacks the `%2$s` capability this command needs — re-run with --user=<admin>.', '__plugin_txtd' ),
					$user_id,
					$capability
				),
				[
					'capability' => $capability,
					'user_id'    => $user_id,
				]
			);
		}
	}

	/**
	 * Contract §3.4 — a single write may not carry both a master font slot and a
	 * connected per-element font field: the slot regenerates the whole defaults table
	 * and would clobber the per-element value written in the same breath.
	 *
	 * @param array $values     Requested id => value map.
	 * @param array $assoc_args Associative arguments.
	 */
	protected function assert_no_ordering_conflict( array $values, array $assoc_args ): void {
		$ids = array_map( 'strval', array_keys( $values ) );

		$slots = array_values( array_intersect( $ids, self::MASTER_FONT_SLOT_IDS ) );
		if ( empty( $slots ) ) {
			return;
		}

		$per_element = array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return 1 === preg_match( '/\[[A-Za-z0-9_-]*_font\]$/', $id );
				}
			)
		);

		if ( empty( $per_element ) ) {
			return;
		}

		$this->fail(
			$assoc_args,
			1,
			'ordering_conflict',
			sprintf(
				/* translators: 1: master slot ids, 2: per-element field ids. */
				__( 'Ordering conflict: writing the master slot(s) %1$s regenerates the whole per-element font defaults table and would clobber %2$s. Do it in two steps: first `set` the master slot(s), then `set` the per-element field(s).', '__plugin_txtd' ),
				implode( ', ', $slots ),
				implode( ', ', $per_element )
			),
			[
				'master_slots'        => $slots,
				'per_element_fields'  => $per_element,
			]
		);
	}

	/**
	 * Contract §3.4 — a `letter_spacing` sub-field must carry `unit: 'em'`. A
	 * `unit: false` letter-spacing is rejected, not silently written.
	 *
	 * @param array $values     Requested id => value map.
	 * @param array $assoc_args Associative arguments.
	 */
	protected function assert_letter_spacing_units( array $values, array $assoc_args ): void {
		$offenders = [];

		foreach ( $values as $setting_id => $value ) {
			foreach ( $this->collect_letter_spacings( $value ) as $letter_spacing ) {
				if ( ! is_array( $letter_spacing ) ) {
					continue;
				}

				$unit = $letter_spacing['unit'] ?? null;
				if ( 'em' !== $unit ) {
					$offenders[] = (string) $setting_id;
					break;
				}
			}
		}

		if ( empty( $offenders ) ) {
			return;
		}

		$this->fail(
			$assoc_args,
			1,
			'invalid_params',
			sprintf(
				/* translators: %s: comma separated list of setting ids. */
				__( 'letter_spacing must carry unit "em"; rejected in: %s.', '__plugin_txtd' ),
				implode( ', ', array_unique( $offenders ) )
			),
			[ 'invalid_letter_spacing' => array_values( array_unique( $offenders ) ) ]
		);
	}

	/**
	 * Walk a setting value collecting every `letter_spacing` sub-field.
	 *
	 * @param mixed $value Setting value.
	 *
	 * @return array
	 */
	protected function collect_letter_spacings( $value ): array {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$found = [];
		foreach ( $value as $key => $item ) {
			if ( 'letter_spacing' === $key ) {
				$found[] = is_object( $item ) ? (array) $item : $item;
				continue;
			}

			$found = array_merge( $found, $this->collect_letter_spacings( $item ) );
		}

		return $found;
	}

	/**
	 * Contract §3.6 — destructive verbs require `--yes`; `--dry-run` never prompts.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $question   What the caller is about to do.
	 */
	protected function confirm_destructive( array $assoc_args, string $question ): void {
		if ( $this->bool_flag( $assoc_args, 'yes' ) ) {
			return;
		}

		if ( $this->is_interactive() ) {
			\WP_CLI::confirm( $question . ' ' . __( 'Continue?', '__plugin_txtd' ), $assoc_args );

			return;
		}

		$this->fail(
			$assoc_args,
			1,
			'invalid_params',
			$question . ' ' . __( 'Re-run with --yes (mandatory in a non-interactive context) or --dry-run.', '__plugin_txtd' )
		);
	}

	/**
	 * Whether STDIN is an interactive terminal.
	 *
	 * @return bool
	 */
	protected function is_interactive(): bool {
		return function_exists( 'posix_isatty' ) && defined( 'STDIN' ) && @posix_isatty( STDIN );
	}

	/**
	 * Assemble the `set` payload from positional assignments, a file, or STDIN.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return array id => value.
	 */
	protected function collect_set_payload( array $args, array $assoc_args ): array {
		$values = [];

		$from_file = $this->flag( $assoc_args, 'from-file' );
		$positional = [];
		foreach ( $args as $arg ) {
			if ( '-' === (string) $arg ) {
				$from_file = '-';
				continue;
			}
			$positional[] = (string) $arg;
		}

		if ( is_string( $from_file ) && '' !== $from_file ) {
			$values = $this->read_settings_document( $from_file, $assoc_args );
		}

		foreach ( $positional as $assignment ) {
			$separator = strpos( $assignment, '=' );
			if ( false === $separator ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: the offending argument. */
						__( 'Expected `<setting-id>=<value>`, got: %s.', '__plugin_txtd' ),
						$assignment
					)
				);
			}

			$setting_id = trim( substr( $assignment, 0, $separator ) );
			if ( '' === $setting_id ) {
				$this->fail( $assoc_args, 1, 'invalid_params', __( 'Empty setting id in an assignment.', '__plugin_txtd' ) );
			}

			$values[ $setting_id ] = $this->parse_value( substr( $assignment, $separator + 1 ) );
		}

		return $values;
	}

	/**
	 * Read a settings document — a bare `{id: value}` map or a stamped export payload.
	 *
	 * @param string $path       File path, or `-` for STDIN.
	 * @param array  $assoc_args Associative arguments.
	 *
	 * @return array
	 */
	protected function read_settings_document( string $path, array $assoc_args ): array {
		if ( '-' === $path ) {
			$raw = defined( 'STDIN' ) ? stream_get_contents( STDIN ) : '';
		} else {
			$raw = is_readable( $path ) ? file_get_contents( $path ) : false;
			if ( false === $raw ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: file path. */
						__( 'Cannot read the settings file: %s.', '__plugin_txtd' ),
						$path
					)
				);
			}
		}

		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: %s: file path. */
					__( 'The settings document is not a JSON object: %s.', '__plugin_txtd' ),
					$path
				)
			);
		}

		// Unwrap a stamped `wp pixelgrade sm export` payload.
		if ( isset( $decoded['meta'] ) && isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ) {
			$decoded = $decoded['settings'];
		}

		return $decoded;
	}

	/**
	 * Parse a CLI value: JSON when it parses as JSON, otherwise the raw string.
	 *
	 * @param string $raw Raw value.
	 *
	 * @return mixed
	 */
	protected function parse_value( string $raw ) {
		$trimmed = trim( $raw );
		if ( '' === $trimmed ) {
			return '';
		}

		$decoded = json_decode( $trimmed, true );
		if ( JSON_ERROR_NONE === json_last_error() ) {
			return $decoded;
		}

		return $raw;
	}

	/**
	 * Where the W5 Node palette generator would live (contract §3.11).
	 *
	 * @return string[]
	 */
	protected function palette_generator_candidates(): array {
		$root = dirname( __DIR__, 2 );

		$binary = 'node (PATH)';
		if ( defined( 'PIXELGRADE_NODE_BINARY' ) ) {
			$binary = (string) constant( 'PIXELGRADE_NODE_BINARY' );
		} elseif ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'style_manager/node_binary', '' );
			if ( is_string( $filtered ) && '' !== $filtered ) {
				$binary = $filtered;
			}
		}

		return [
			$root . '/dist/node/palette-generator.js',
			$binary,
		];
	}

	/**
	 * Turn a SettingsWriter result into the envelope of contract §2.
	 *
	 * @param array    $assoc_args     Associative arguments.
	 * @param array    $result         SettingsWriter::save()/preview() result.
	 * @param string[] $requested_ids  Ids the caller asked to write.
	 * @param bool     $dry_run        Whether this was a dry run.
	 * @param array    $extra_data     Extra `data` keys.
	 */
	protected function emit_write_result( array $assoc_args, array $result, array $requested_ids, bool $dry_run, array $extra_data = [] ): void {
		$stripped  = array_values( (array) ( $result['stripped'] ?? [] ) );
		$persisted = (array) ( $result['persisted'] ?? [] );
		$unchanged = array_values( (array) ( $result['unchanged'] ?? [] ) );

		$warnings = [];
		$exit     = 0;
		$code     = 'ok';

		if ( ! empty( $stripped ) ) {
			$exit = 2;
			$code = 'plus_stripped';

			$reasons = array_values( array_unique( array_column( $stripped, 'reason' ) ) );
			$ids     = array_values( array_unique( array_column( $stripped, 'id' ) ) );

			$warnings[] = [
				'code'    => 'plus_stripped',
				'message' => sprintf(
					/* translators: 1: comma separated setting ids, 2: comma separated reasons. */
					__( 'Requested but not persisted: %1$s (%2$s).', '__plugin_txtd' ),
					implode( ', ', $ids ),
					implode( ', ', $reasons )
				),
				'ids'     => $ids,
			];
		} elseif ( ! empty( $requested_ids ) && count( array_intersect( $requested_ids, $unchanged ) ) === count( $requested_ids ) ) {
			$code = 'noop';
		}

		$summary = $dry_run
			? sprintf(
				/* translators: 1: number of settings that would persist, 2: number stripped. */
				__( 'Dry run: %1$d setting(s) would persist, %2$d stripped. Nothing was written.', '__plugin_txtd' ),
				count( $persisted ),
				count( $stripped )
			)
			: sprintf(
				/* translators: 1: number persisted, 2: number unchanged, 3: number stripped. */
				__( '%1$d persisted, %2$d unchanged, %3$d stripped.', '__plugin_txtd' ),
				count( $persisted ),
				count( $unchanged ),
				count( $stripped )
			);

		$data = array_merge(
			[
				'dry_run'   => $dry_run,
				'requested' => array_values( $requested_ids ),
				'saved'     => array_values( (array) ( $result['saved'] ?? [] ) ),
			],
			$extra_data
		);

		$this->emit(
			$assoc_args,
			$exit,
			$code,
			$summary,
			$data,
			$warnings,
			[
				'persisted' => $persisted,
				'unchanged' => $unchanged,
				'stripped'  => $stripped,
			]
		);
	}

	/**
	 * Convert a WP_Error from the write path into an envelope.
	 *
	 * `style_manager_site_editor_nothing_to_save` means every id was unknown or
	 * capability-denied. Contract §2 makes that a finding, not an error: `unknown_setting`
	 * strips, exit 2 — never a silent drop and never exit 1.
	 *
	 * @param array     $assoc_args Associative arguments.
	 * @param \WP_Error $error      The error.
	 * @param array     $values     The requested id => value map.
	 */
	protected function fail_from_wp_error( array $assoc_args, \WP_Error $error, array $values ): void {
		if ( 'style_manager_site_editor_nothing_to_save' === $error->get_error_code() ) {
			$data    = (array) $error->get_error_data();
			$skipped = ! empty( $data['skipped'] ) ? array_map( 'strval', (array) $data['skipped'] ) : array_map( 'strval', array_keys( $values ) );

			$stripped = [];
			foreach ( $skipped as $setting_id ) {
				$stripped[] = [
					'id'        => $setting_id,
					'reason'    => SettingsWriter::REASON_UNKNOWN_SETTING,
					'requested' => $values[ $setting_id ] ?? null,
					'current'   => null,
				];
			}

			$this->emit_write_result(
				$assoc_args,
				[
					'saved'     => [],
					'stripped'  => $stripped,
					'persisted' => [],
					'unchanged' => [],
				],
				array_keys( $values ),
				false
			);
		}

		$this->fail(
			$assoc_args,
			1,
			'invalid_params',
			(string) $error->get_error_message(),
			[ 'error_code' => (string) $error->get_error_code() ]
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Envelope I/O
	|--------------------------------------------------------------------------
	*/

	/**
	 * Emit a failure envelope and halt.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param int    $exit       Exit code (1 or 3).
	 * @param string $code       Machine code.
	 * @param string $summary    Human summary.
	 * @param array  $data       Payload.
	 */
	protected function fail( array $assoc_args, int $exit, string $code, string $summary, array $data = [] ): void {
		$this->emit( $assoc_args, $exit, $code, $summary, $data );
	}

	/**
	 * Emit the shared envelope (contract §2) and halt with the matching exit code.
	 *
	 * `ok` is bound to the exit code, not to the outcome: ok ⇔ exit 0 or 2.
	 * Under --format=json STDOUT carries the envelope and nothing else.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param int    $exit       Exit code.
	 * @param string $code       Machine code.
	 * @param string $summary    Human summary.
	 * @param array  $data       Payload.
	 * @param array  $warnings   Warning entries.
	 * @param array  $write      Optional `persisted`/`unchanged`/`stripped` write keys.
	 */
	protected function emit( array $assoc_args, int $exit, string $code, string $summary, array $data = [], array $warnings = [], array $write = [] ): void {
		$payload = [
			'ok'       => ( 0 === $exit || 2 === $exit ),
			'code'     => $code,
			'summary'  => $summary,
			'data'     => empty( $data ) ? new \stdClass() : $data,
			'warnings' => array_values( $warnings ),
		];

		if ( array_key_exists( 'persisted', $write ) ) {
			$payload['persisted'] = empty( $write['persisted'] ) ? new \stdClass() : $write['persisted'];
			$payload['unchanged'] = array_values( (array) ( $write['unchanged'] ?? [] ) );
			$payload['stripped']  = array_values( (array) ( $write['stripped'] ?? [] ) );
		}

		$format = $this->format( $assoc_args );

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		} elseif ( 'yaml' === $format ) {
			\WP_CLI::line( $this->to_yaml( $payload ) );
		} else {
			$this->render_table( $payload, $exit );
		}

		\WP_CLI::halt( $exit );
	}

	/**
	 * Resolve the output format. Default `table`, unconditionally — no TTY detection.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return string
	 */
	protected function format( array $assoc_args ): string {
		$format = $this->flag( $assoc_args, 'format', 'table' );
		$format = is_string( $format ) ? strtolower( $format ) : 'table';

		return in_array( $format, [ 'table', 'json', 'yaml' ], true ) ? $format : 'table';
	}

	/**
	 * Render the envelope as WP_CLI success/warning/log lines plus tables.
	 *
	 * @param array $payload Envelope.
	 * @param int   $exit    Exit code.
	 */
	protected function render_table( array $payload, int $exit ): void {
		foreach ( $payload['warnings'] as $warning ) {
			\WP_CLI::warning( (string) ( $warning['message'] ?? '' ) );
		}

		$data = is_array( $payload['data'] ) ? $payload['data'] : [];

		if ( ! empty( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$this->render_map( $data['settings'], 'setting', 'value' );
		}

		if ( ! empty( $data['sections'] ) && is_array( $data['sections'] ) ) {
			$rows = [];
			foreach ( $data['sections'] as $section ) {
				$rows[] = [
					'id'       => (string) ( $section['id'] ?? '' ),
					'title'    => (string) ( $section['title'] ?? '' ),
					'panel'    => (string) ( $section['panel'] ?? '' ),
					'controls' => (string) count( (array) ( $section['controls'] ?? [] ) ),
				];
			}
			$this->render_rows( $rows, [ 'id', 'title', 'panel', 'controls' ] );
		}

		if ( ! empty( $data['connected_fields'] ) && is_array( $data['connected_fields'] ) ) {
			\WP_CLI::log( sprintf( 'connected_fields: %s', implode( ', ', array_map( 'strval', $data['connected_fields'] ) ) ) );
		}

		if ( isset( $payload['persisted'] ) && is_array( $payload['persisted'] ) && ! empty( $payload['persisted'] ) ) {
			$this->render_map( $payload['persisted'], 'persisted', 'value' );
		}

		if ( ! empty( $payload['unchanged'] ) ) {
			\WP_CLI::log( sprintf( 'unchanged: %s', implode( ', ', array_map( 'strval', $payload['unchanged'] ) ) ) );
		}

		if ( ! empty( $payload['stripped'] ) ) {
			$rows = [];
			foreach ( $payload['stripped'] as $entry ) {
				$rows[] = [
					'id'     => (string) ( $entry['id'] ?? '' ),
					'reason' => (string) ( $entry['reason'] ?? '' ),
				];
			}
			$this->render_rows( $rows, [ 'id', 'reason' ] );
		}

		if ( 0 === $exit ) {
			\WP_CLI::success( (string) $payload['summary'] );
		} elseif ( 2 === $exit ) {
			\WP_CLI::log( (string) $payload['summary'] );
		} else {
			\WP_CLI::warning( sprintf( '[%s] %s', (string) $payload['code'], (string) $payload['summary'] ) );
		}
	}

	/**
	 * Render an id => value map as a two-column table.
	 *
	 * @param array  $map        The map.
	 * @param string $key_label  Left column label.
	 * @param string $value_label Right column label.
	 */
	protected function render_map( array $map, string $key_label, string $value_label ): void {
		$rows = [];
		foreach ( $map as $key => $value ) {
			$rows[] = [
				$key_label   => (string) $key,
				$value_label => $this->scalarize( $value ),
			];
		}

		$this->render_rows( $rows, [ $key_label, $value_label ] );
	}

	/**
	 * Render rows through WP-CLI's table formatter when it is available.
	 *
	 * @param array    $rows Rows.
	 * @param string[] $keys Column keys.
	 */
	protected function render_rows( array $rows, array $keys ): void {
		if ( empty( $rows ) ) {
			return;
		}

		if ( function_exists( '\WP_CLI\Utils\format_items' ) ) {
			\WP_CLI\Utils\format_items( 'table', $rows, $keys );

			return;
		}

		foreach ( $rows as $row ) {
			$parts = [];
			foreach ( $keys as $key ) {
				$parts[] = (string) ( $row[ $key ] ?? '' );
			}
			\WP_CLI::log( implode( "\t", $parts ) );
		}
	}

	/**
	 * Flatten a value for table output.
	 *
	 * @param mixed $value Value.
	 *
	 * @return string
	 */
	protected function scalarize( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return '';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Serialize the envelope as YAML, falling back to JSON when no dumper is around.
	 *
	 * @param array $payload Envelope.
	 *
	 * @return string
	 */
	protected function to_yaml( array $payload ): string {
		$normalized = json_decode( (string) wp_json_encode( $payload ), true );

		if ( class_exists( '\Spyc' ) ) {
			return (string) \Spyc::YAMLDump( $normalized, 2, 0 );
		}

		return (string) wp_json_encode( $normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Read an associative argument.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $key        Key.
	 * @param mixed  $default    Fallback.
	 *
	 * @return mixed
	 */
	protected function flag( array $assoc_args, string $key, $default = null ) {
		return array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $default;
	}

	/**
	 * Read a boolean associative argument.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $key        Key.
	 *
	 * @return bool
	 */
	protected function bool_flag( array $assoc_args, string $key ): bool {
		if ( ! array_key_exists( $key, $assoc_args ) ) {
			return false;
		}

		$value = $assoc_args[ $key ];

		return ! in_array( $value, [ false, null, 'false', '0', 0 ], true );
	}
}
