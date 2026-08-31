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
	 * Size ceiling for a `--from-file` / STDIN settings document (4 MB). A design system is
	 * kilobytes; anything larger is a mistake, and reading it unbounded would trade a clean
	 * envelope for a PHP memory fatal.
	 */
	public const MAX_DOCUMENT_BYTES = 4194304;

	/**
	 * Whether STDIN has already been drained for a settings payload — if so there is no
	 * operator left to prompt for confirmation.
	 *
	 * @var bool
	 */
	protected bool $stdin_consumed = false;

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
	 * The headless Color System palette generator.
	 *
	 * @var PaletteGenerator
	 */
	protected PaletteGenerator $palette_generator;

	/**
	 * Create the WP-CLI commands provider.
	 *
	 * @since 2.3.0
	 *
	 * @param Options            $options             Options provider.
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param SettingsWriter     $settings_writer     Settings writer.
	 * @param FontPalettes       $font_palettes       Font palettes.
	 * @param PaletteGenerator   $palette_generator   Palette generator.
	 */
	public function __construct(
		Options $options,
		HeadlessCustomizer $headless_customizer,
		SettingsWriter $settings_writer,
		FontPalettes $font_palettes,
		PaletteGenerator $palette_generator
	) {
		$this->options             = $options;
		$this->headless_customizer = $headless_customizer;
		$this->settings_writer     = $settings_writer;
		$this->font_palettes       = $font_palettes;
		$this->palette_generator   = $palette_generator;
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

		$dry_run = $this->bool_flag( $assoc_args, 'dry-run' );

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			if ( SettingsWriter::master_font_slots_in( $values ) ) {
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
	 * Scope: **Style Manager-owned settings only** by default — the `sm_*` ids plus the
	 * theme's connected fields, as resolved from the SM structure. An agent restoring a
	 * design system should not silently rewrite the site title, so core Customizer settings
	 * are out unless `--all` asks for them. Values are reported exactly as stored (§3.4:
	 * export passes shipped state through unmodified).
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write the payload to this file instead of only returning it.
	 *
	 * [--include=<ids>]
	 * : Comma-separated setting ids to include. Narrows whichever scope is in effect.
	 *
	 * [--all]
	 * : Export the full Customizer settings map (152+ ids on a stock site, including core
	 * settings like blogname/blogdescription) instead of the Style Manager surface.
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
		$scope    = $this->bool_flag( $assoc_args, 'all' ) ? 'all' : 'style_manager';

		if ( 'style_manager' === $scope ) {
			$surface  = array_flip( $this->style_manager_surface_ids( $settings ) );
			$settings = array_intersect_key( $settings, $surface );
		}

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

		// The file payload stays exactly the pinned `{meta, settings}` shape; `scope` is
		// envelope-only reporting so `set --from-file` never sees an unpinned key.
		$data          = $payload;
		$data['scope'] = $scope;

		$file = $this->flag( $assoc_args, 'file' );
		if ( is_string( $file ) && '' !== $file ) {
			$json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
			if ( $this->bool_flag( $assoc_args, 'pretty' ) ) {
				$json_flags |= JSON_PRETTY_PRINT;
			}

			$written = @file_put_contents( $file, (string) wp_json_encode( $payload, $json_flags ) . "\n" );
			if ( false === $written ) {
				$last  = error_get_last();
				$why   = ! empty( $last['message'] ) ? (string) $last['message'] : __( 'unknown error', '__plugin_txtd' );

				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: 1: file path, 2: underlying error message. */
						__( 'Could not write the export to %1$s: %2$s', '__plugin_txtd' ),
						$file,
						$why
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
	 * Runs the bundled Node palette generator (contract §3.11) — the `dist/node/palette-generator.js`
	 * build artifact that bundles the *same* `getPalettesFromColors()` module the Customizer
	 * bundle runs, which is why the regenerated `sm_advanced_palette_output` is byte-identical
	 * to what a browser session would have produced. When the artifact or a Node binary is
	 * missing the command fails gracefully with `code: "generator_unavailable"`, exit 1, and
	 * never writes a stale palette output.
	 *
	 * The generator's eight tuning options are resolved through the three-store resolver
	 * (`\Pixelgrade\StyleManager\get_option()`, §3.1), never `wp option get` — the W5 spike
	 * measured raw option reads reproducing 0 of 9 corpus fixtures.
	 *
	 * Destructive: it replaces a derived ramp it cannot restore, and some sites carry a
	 * hand-authored `sm_advanced_palette_output` that regeneration would silently overwrite.
	 * `--dry-run` reports whether the stored blob is generator-produced before you commit.
	 *
	 * `--generator=none` is the sanctioned path for applying an **already-produced** palette
	 * output — a browser-exported blob, or one hand-authored by a gene-migration run. It takes
	 * the output from `--output`, applies it **verbatim**, and never touches Node. That is why
	 * a hand-authored blob is not validated as generator-shaped: it legitimately carries no
	 * `colors` ramp and no echoed `options`, only the keys PHP's CSS generation reads.
	 *
	 * All settings are written in ONE `SettingsWriter::save()` (§3.12 — the Customizer
	 * manager holds a single changeset uuid, so a second publish in the same process fails).
	 * Applying any source makes the palette custom, so `sm_is_custom_color_palette` is always
	 * written as true.
	 *
	 * ## OPTIONS
	 *
	 * [--source=<source>]
	 * : The `sm_advanced_palette_source` JSON, `@<file>`, or `-` for STDIN. Required.
	 *
	 * [--output=<output>]
	 * : With `--generator=node` this is a DESTINATION for the generated output, and takes exactly
	 * `json` (echo it into the envelope's `data.output`) or `@<file>` (write it there). It is
	 * persisted either way, and under `--dry-run` no file is written — `--dry-run` has no side
	 * effects at all. With `--generator=none` this is the INPUT instead — the pre-generated
	 * palette output to apply verbatim, as `@<file>` or an inline JSON array — and it is REQUIRED.
	 *
	 * [--variation=<n>]
	 * : Set `sm_site_color_variation` (1-12) and generate against it. Omit to keep the stored
	 * value. Note this setting is Pixelgrade Plus-gated, so passing it on a free site is
	 * reported as `stripped[].reason: "plus_locked"`, exit 2.
	 *
	 * [--generator=<generator>]
	 * : `node` (the default) runs the bundled artifact against `--source`. `none` skips Node
	 * entirely and applies the pre-generated output given in `--output` verbatim. Any other value
	 * is `invalid_params`, exit 1 — validated in the command so the caller still gets an envelope.
	 *
	 * [--dry-run]
	 * : Report the predicted diff without writing anything — no settings, no `--output` file.
	 * Never prompts, never needs --yes.
	 *
	 * [--yes]
	 * : Confirm the destructive regeneration. Strictly required under --format=json|yaml.
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
	 * 0 ok|noop · 2 plus_stripped · 3 permission_denied · 1 for
	 * generator_unavailable|generator_timeout|invalid_params|confirmation_required
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm apply-color-palette --source=@palette-source.json --yes --format=json --user=admin
	 *     wp pixelgrade sm apply-color-palette --source=- --dry-run --format=json --user=admin < source.json
	 *     wp pixelgrade sm apply-color-palette --source=@source.json --generator=none --output=@palette-output.json --yes --user=admin
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function apply_color_palette( $args, $assoc_args ) {
		$this->require_user( $assoc_args );

		$source_json = $this->collect_palette_source( $assoc_args );
		$groups      = PaletteGenerator::parse_source( $source_json );
		if ( is_wp_error( $groups ) ) {
			$this->fail( $assoc_args, 1, 'invalid_params', (string) $groups->get_error_message() );
		}

		$mode = $this->flag( $assoc_args, 'generator', 'node' );
		$mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : 'node';
		if ( ! in_array( $mode, [ 'node', 'none' ], true ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( '--generator must be `node` or `none`.', '__plugin_txtd' )
			);
		}

		$overrides = $this->palette_option_overrides( $assoc_args );
		$dry_run   = $this->bool_flag( $assoc_args, 'dry-run' );

		$options = null;
		if ( 'none' === $mode ) {
			$destination = [ 'kind' => 'none' ];
			$applied     = $this->collect_applied_palette_output( $assoc_args );
		} else {
			// Parsed before the subprocess runs, so a malformed --output costs nothing and, more
			// to the point, can never fail *after* the palette has been persisted.
			$destination = $this->resolve_output_destination( $assoc_args );
			$options     = $this->palette_generator->resolve_options( $overrides );
			$applied     = $this->generate_palette_output( $assoc_args, $source_json, $options );
		}

		$values = $this->palette_write_payload( $source_json, $applied['json'], $overrides );

		/*
		 * Read the diff BEFORE the write. Computing it afterwards would compare the new blob with
		 * itself — `changed` always false, `stored_generator_produced` describing what we just
		 * wrote — and the hand-authored-overwrite signal would exist only under --dry-run, which
		 * is the one run that cannot destroy anything.
		 */
		$diff = $this->palette_output_diff( $applied['json'] );

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			$this->confirm_destructive(
				$assoc_args,
				__( 'Applying a color palette replaces the whole generated ramp, including any hand-authored palette output stored on this site.', '__plugin_txtd' )
			);

			// Written before the save, so a failing file write fails the whole command with
			// nothing persisted — rather than reporting exit 1 "nothing was done" over a site
			// whose palette has in fact already changed.
			$this->write_output_file( $assoc_args, $destination, $applied['json'] );

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				$this->fail_from_wp_error( $assoc_args, $result, $values );
			}
		}

		$extra = [
			'grades'    => PaletteGenerator::grade_count( $applied['palettes'] ),
			'palettes'  => count( $applied['palettes'] ),
			'generator' => $mode,
			'verbatim'  => ( 'none' === $mode ),
			'diff'      => $diff,
		];

		if ( null !== $options ) {
			$extra['options'] = $options;
		}

		if ( 'json' === $destination['kind'] ) {
			$extra['output'] = $applied['palettes'];
		} elseif ( 'file' === $destination['kind'] && ! $dry_run ) {
			$extra['output_file'] = $destination['path'];
		}

		$this->emit_write_result( $assoc_args, $result, array_keys( $values ), $dry_run, $extra );
	}

	/**
	 * Parse `--output` in its DESTINATION sense (the `--generator=node` path).
	 *
	 * Exactly two forms, per contract §1.1's `--output=<json|@file>`: the literal `json`, or
	 * `@<path>`. A bare path used to be silently accepted as a file, which made the same flag
	 * mean "a path" here and "inline JSON" on the verbatim path — an asymmetry nobody documented.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return array{kind: string, path: string}
	 */
	protected function resolve_output_destination( array $assoc_args ): array {
		$output = $this->flag( $assoc_args, 'output' );

		if ( ! is_string( $output ) || '' === trim( $output ) ) {
			return [
				'kind' => 'none',
				'path' => '',
			];
		}

		$output = trim( $output );

		if ( 'json' === strtolower( $output ) ) {
			return [
				'kind' => 'json',
				'path' => '',
			];
		}

		if ( 0 === strpos( $output, '@' ) && '' !== substr( $output, 1 ) ) {
			return [
				'kind' => 'file',
				'path' => substr( $output, 1 ),
			];
		}

		$this->fail(
			$assoc_args,
			1,
			'invalid_params',
			__( '--output takes `json` (echo the generated output into the envelope) or `@<file>` (write it there). Nothing was written.', '__plugin_txtd' )
		);

		return [
			'kind' => 'none',
			'path' => '',
		];
	}

	/**
	 * Write the generated output to `--output=@<file>`, before anything is persisted.
	 *
	 * @param array  $assoc_args     Associative arguments.
	 * @param array  $destination    Resolved `--output` destination.
	 * @param string $generated_json The generated palette output.
	 */
	protected function write_output_file( array $assoc_args, array $destination, string $generated_json ): void {
		if ( 'file' !== $destination['kind'] ) {
			return;
		}

		$written = @file_put_contents( $destination['path'], $generated_json );
		if ( false !== $written ) {
			return;
		}

		$last = error_get_last();

		$this->fail(
			$assoc_args,
			1,
			'invalid_params',
			sprintf(
				/* translators: 1: file path, 2: underlying error message. */
				__( 'Could not write the generated palette output to %1$s: %2$s Nothing was written.', '__plugin_txtd' ),
				$destination['path'],
				! empty( $last['message'] ) ? (string) $last['message'] : __( 'unknown error.', '__plugin_txtd' )
			)
		);
	}

	/**
	 * Run the Node generator, or fail with an envelope that never leaves a stale output behind.
	 *
	 * @param array  $assoc_args  Associative arguments.
	 * @param string $source_json The palette source.
	 * @param array  $options     Resolved generator options.
	 *
	 * @return array{json: string, palettes: array}
	 */
	protected function generate_palette_output( array $assoc_args, string $source_json, array $options ): array {
		if ( ! $this->palette_generator->is_available() ) {
			$looked_for = $this->palette_generator->looked_for();

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

		$generated = $this->palette_generator->generate( $source_json, $options );
		if ( is_wp_error( $generated ) ) {
			$code = [
				'style_manager_generator_unavailable' => 'generator_unavailable',
				'style_manager_generator_timeout'     => 'generator_timeout',
			][ $generated->get_error_code() ] ?? 'invalid_params';

			$data               = (array) $generated->get_error_data();
			$data['error_code'] = (string) $generated->get_error_code();

			$this->fail(
				$assoc_args,
				1,
				$code,
				(string) $generated->get_error_message() . ' ' . __( 'Nothing was written.', '__plugin_txtd' ),
				$data
			);
		}

		return $generated;
	}

	/**
	 * Read the pre-generated palette output `--generator=none` applies verbatim.
	 *
	 * Validated against what PHP's CSS generation actually consumes — `variations`,
	 * `darkVariations`, `sourceIndex` — and nothing more. A hand-authored blob from a
	 * gene-migration run carries no `colors` ramp and no echoed `options`, and demanding those
	 * would reject exactly the artifacts this path exists to apply.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return array{json: string, palettes: array}
	 */
	protected function collect_applied_palette_output( array $assoc_args ): array {
		$output = $this->flag( $assoc_args, 'output' );

		if ( ! is_string( $output ) || '' === trim( $output ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( '--generator=none applies a pre-generated palette output, so --output=<json|@file> is required. Nothing was written.', '__plugin_txtd' )
			);
		}

		$output = trim( $output );

		if ( 0 === strpos( $output, '@' ) ) {
			$path = substr( $output, 1 );
			$size = is_readable( $path ) ? filesize( $path ) : false;
			$raw  = ( false !== $size ) ? file_get_contents( $path, false, null, 0, self::MAX_DOCUMENT_BYTES + 1 ) : false;

			if ( false === $raw ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: file path. */
						__( 'Cannot read the palette output file: %s.', '__plugin_txtd' ),
						$path
					)
				);
			}
		} else {
			$raw = $output;
		}

		if ( strlen( (string) $raw ) > self::MAX_DOCUMENT_BYTES ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: %d: size limit in bytes. */
					__( 'The palette output exceeds the %d byte limit.', '__plugin_txtd' ),
					self::MAX_DOCUMENT_BYTES
				)
			);
		}

		$applied = PaletteGenerator::validate_renderable( (string) $raw );
		if ( is_wp_error( $applied ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				(string) $applied->get_error_message() . ' ' . __( 'Nothing was written.', '__plugin_txtd' )
			);
		}

		return $applied;
	}

	/**
	 * Read and validate `--variation`.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return array setting_id => value.
	 */
	protected function palette_option_overrides( array $assoc_args ): array {
		$variation = $this->flag( $assoc_args, 'variation' );

		if ( null === $variation || '' === $variation ) {
			return [];
		}

		if ( ! is_numeric( $variation ) || (int) $variation < 1 || (int) $variation > 12 ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( '--variation must be a whole number between 1 and 12.', '__plugin_txtd' )
			);
		}

		return [ PaletteGenerator::VARIATION_SETTING_ID => (int) $variation ];
	}

	/**
	 * Assemble the one batched write (§3.12 — one publish per process).
	 *
	 * Contract §1.1 names four settings. `sm_site_color_variation` is included **only when
	 * `--variation` asks for it**, and that is deliberate: the setting is a member of
	 * `ColorPalettes::FINE_TUNE_PALETTE_FIELDS`, hence Pixelgrade Plus-gated, and
	 * `SettingsWriter::strip_locked_premium_settings()` drops `sm_advanced_palette_output`
	 * whenever a premium id is present in the same payload. Sending it unconditionally would
	 * therefore make the command strip its own output on every site without Plus.
	 *
	 * `sm_is_custom_color_palette` is always true: applying an arbitrary source *is* what makes
	 * a palette custom, whichever way its output was produced. It is written as the integer `1`
	 * rather than boolean `true` because that is the representation the option round-trips as —
	 * the Customizer reads it back as the string `'1'`, and a boolean would therefore never
	 * compare equal to what is on disk, costing the command its `noop` on every re-run (§3.5).
	 *
	 * @param string $source_json  The palette source as given.
	 * @param string $output_json  The palette output being persisted.
	 * @param array  $overrides    Resolved option overrides (`--variation`).
	 *
	 * @return array setting_id => value.
	 */
	protected function palette_write_payload( string $source_json, string $output_json, array $overrides ): array {
		$values = [
			PaletteGenerator::SOURCE_SETTING_ID    => trim( $source_json ),
			PaletteGenerator::OUTPUT_SETTING_ID    => $output_json,
			PaletteGenerator::IS_CUSTOM_SETTING_ID => 1,
		];

		if ( array_key_exists( PaletteGenerator::VARIATION_SETTING_ID, $overrides ) ) {
			$values[ PaletteGenerator::VARIATION_SETTING_ID ] = $overrides[ PaletteGenerator::VARIATION_SETTING_ID ];
		}

		return $values;
	}

	/**
	 * Read `--source`: raw JSON, `@<path>`, or `-` for STDIN.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return string
	 */
	protected function collect_palette_source( array $assoc_args ): string {
		$source = $this->flag( $assoc_args, 'source' );

		if ( ! is_string( $source ) || '' === trim( $source ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				__( '--source is required: pass the palette source JSON, @<file>, or `-` for STDIN.', '__plugin_txtd' )
			);
		}

		$source = trim( $source );

		if ( '-' === $source ) {
			$this->stdin_consumed = true;
			$raw = defined( 'STDIN' ) ? stream_get_contents( STDIN, self::MAX_DOCUMENT_BYTES + 1 ) : '';
		} elseif ( 0 === strpos( $source, '@' ) ) {
			$path = substr( $source, 1 );
			$size = is_readable( $path ) ? filesize( $path ) : false;
			$raw  = ( false !== $size ) ? file_get_contents( $path, false, null, 0, self::MAX_DOCUMENT_BYTES + 1 ) : false;

			if ( false === $raw ) {
				$this->fail(
					$assoc_args,
					1,
					'invalid_params',
					sprintf(
						/* translators: %s: file path. */
						__( 'Cannot read the palette source file: %s.', '__plugin_txtd' ),
						$path
					)
				);
			}
		} else {
			$raw = $source;
		}

		if ( strlen( (string) $raw ) > self::MAX_DOCUMENT_BYTES ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: %d: size limit in bytes. */
					__( 'The palette source exceeds the %d byte limit.', '__plugin_txtd' ),
					self::MAX_DOCUMENT_BYTES
				)
			);
		}

		return (string) $raw;
	}

	/**
	 * What regenerating would do to the stored `sm_advanced_palette_output`.
	 *
	 * `stored_generator_produced` is the one an operator must read before a real run: a
	 * `false` there means the site carries a hand-authored palette blob (some gene-migration
	 * runs write the option directly) and this command is about to replace it.
	 *
	 * @param string $generated_json The generated palette output.
	 *
	 * @return array
	 */
	protected function palette_output_diff( string $generated_json ): array {
		$stored = $this->palette_generator->current_value( PaletteGenerator::OUTPUT_SETTING_ID );
		$stored = is_string( $stored ) ? $stored : (string) wp_json_encode( $stored );

		return [
			'stored_bytes'              => strlen( $stored ),
			'generated_bytes'           => strlen( $generated_json ),
			'stored_generator_produced' => PaletteGenerator::is_generator_produced( $stored ),
			'changed'                   => PaletteGenerator::canonical_json( $stored ) !== PaletteGenerator::canonical_json( $generated_json ),
		];
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
	 * Exempt from §3.0's user rule (contract v0.3.3): it runs without `--user`, exactly as
	 * it has since 2.3.0. When a user *is* resolved it must still hold `edit_theme_options`.
	 *
	 * ## EXIT CODES
	 *
	 * 0 ok · 3 permission_denied (only when a user is resolved and lacks the capability)
	 *
	 * ## EXAMPLES
	 *
	 *     wp pixelgrade sm flush-cache
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments.
	 */
	public function flush_cache( $args, $assoc_args ) {
		$this->require_user( $assoc_args, self::CAPABILITY, true );

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
	 * One exemption (v0.3.3, §3.0): `flush-cache` keeps its historic no-user behavior —
	 * cache invalidation discloses nothing and writes no state a visitor can observe, so
	 * enforcing §3.0 there would break a shipped command for no security gain. Its
	 * `edit_theme_options` row still applies once a user *is* resolved.
	 *
	 * @param array  $assoc_args      Associative arguments.
	 * @param string $capability      Required capability.
	 * @param bool   $allow_anonymous Whether a user-less invocation is permitted (flush-cache only).
	 */
	protected function require_user( array $assoc_args, string $capability = self::CAPABILITY, bool $allow_anonymous = false ): void {
		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 ) {
			if ( $allow_anonymous ) {
				return;
			}

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
	 * Contract §3.4 — a single `set` invocation may not carry both a master font slot and
	 * a connected per-element font field: the slot regenerates the whole defaults table and
	 * would clobber the per-element value written in the same breath.
	 *
	 * The detection itself lives in `SettingsWriter::find_ordering_conflict()` so W7's
	 * abilities enforce the identical law; this only translates the verdict to an envelope.
	 *
	 * @param array $values     Requested id => value map.
	 * @param array $assoc_args Associative arguments.
	 */
	protected function assert_no_ordering_conflict( array $values, array $assoc_args ): void {
		$conflict = $this->settings_writer->find_ordering_conflict( $values );
		if ( null === $conflict ) {
			return;
		}

		$this->fail(
			$assoc_args,
			1,
			'ordering_conflict',
			sprintf(
				/* translators: 1: master slot ids, 2: per-element field ids. */
				__( 'Ordering conflict: writing the master slot(s) %1$s regenerates the whole per-element font defaults table and would clobber %2$s. Do it in two steps: first `set` the master slot(s), then `set` the per-element field(s).', '__plugin_txtd' ),
				implode( ', ', $conflict['master_slots'] ),
				implode( ', ', $conflict['per_element_fields'] )
			),
			$conflict
		);
	}

	/**
	 * Contract §3.6 — destructive verbs require `--yes`; `--dry-run` never prompts.
	 *
	 * **Confirmation is bound to the output format, not to TTY detection.** Under
	 * `--format=json|yaml` a prompt would corrupt the machine contract, so `--yes` is
	 * strictly required and its absence emits `code:"confirmation_required"`, exit 1, with
	 * STDOUT still carrying nothing but the envelope. Only `--format=table` may prompt —
	 * and a *declined* prompt must not exit 0 silently (WP_CLI::confirm()'s stock `exit;`
	 * would report a refused destructive operation as success), so the prompt is handled
	 * here and a decline emits the same `confirmation_required` envelope, exit 1.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $question   What the caller is about to do.
	 */
	protected function confirm_destructive( array $assoc_args, string $question ): void {
		if ( $this->bool_flag( $assoc_args, 'yes' ) ) {
			return;
		}

		if ( 'table' === $this->format( $assoc_args ) && $this->is_interactive() ) {
			if ( $this->prompt_for_confirmation( $question . ' ' . __( 'Continue?', '__plugin_txtd' ) ) ) {
				return;
			}

			$this->fail(
				$assoc_args,
				1,
				'confirmation_required',
				__( 'Declined at the confirmation prompt. Nothing was written.', '__plugin_txtd' )
			);
		}

		$this->fail(
			$assoc_args,
			1,
			'confirmation_required',
			$question . ' ' . __( 'Re-run with --yes (strictly required under --format=json|yaml) or --dry-run.', '__plugin_txtd' )
		);
	}

	/**
	 * Ask for confirmation on an interactive terminal.
	 *
	 * Deliberately not `WP_CLI::confirm()`: that exits 0 on a decline, which would report a
	 * refused destructive operation as success to any caller reading exit codes.
	 *
	 * @param string $question The question.
	 *
	 * @return bool Whether the operator confirmed.
	 */
	protected function prompt_for_confirmation( string $question ): bool {
		fwrite( STDOUT, $question . ' [y/n] ' );

		$answer = fgets( STDIN );
		if ( false === $answer ) {
			return false;
		}

		return 'y' === strtolower( trim( $answer ) );
	}

	/**
	 * Whether STDIN is an interactive terminal. Consulted only inside table mode — the
	 * primary binding is the output format, never the TTY.
	 *
	 * @return bool
	 */
	protected function is_interactive(): bool {
		if ( $this->stdin_consumed ) {
			// STDIN already carried the payload; there is nobody left to ask.
			return false;
		}

		return function_exists( 'posix_isatty' ) && defined( 'STDIN' ) && @posix_isatty( STDIN );
	}

	/**
	 * The Style Manager-owned surface: every `sm_*` id plus every setting attached to a
	 * control in an SM section (which is where the theme's connected color/font targets
	 * live). Derived from the live registry, so it follows whatever the active theme
	 * registers rather than a hardcoded list.
	 *
	 * @param array $known The full id => value map to narrow.
	 *
	 * @return string[]
	 */
	protected function style_manager_surface_ids( array $known ): array {
		$ids = [];

		foreach ( array_keys( $known ) as $setting_id ) {
			if ( 0 === strpos( (string) $setting_id, 'sm_' ) ) {
				$ids[] = (string) $setting_id;
			}
		}

		foreach ( $this->headless_customizer->get_sm_section_ids() as $section_id ) {
			foreach ( $this->headless_customizer->get_section_setting_ids( (string) $section_id ) as $setting_id ) {
				$ids[] = (string) $setting_id;
			}
		}

		return array_values( array_unique( $ids ) );
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
			$this->stdin_consumed = true;
			// Bounded: a runaway pipe must produce a clean envelope, never a PHP OOM fatal
			// on STDERR that no `| jq` caller can parse.
			$raw = defined( 'STDIN' ) ? stream_get_contents( STDIN, self::MAX_DOCUMENT_BYTES + 1 ) : '';
		} else {
			$size = is_readable( $path ) ? filesize( $path ) : false;
			$raw  = ( false !== $size ) ? file_get_contents( $path, false, null, 0, self::MAX_DOCUMENT_BYTES + 1 ) : false;
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

		if ( strlen( (string) $raw ) > self::MAX_DOCUMENT_BYTES ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: 1: file path or `-`, 2: size limit in bytes. */
					__( 'The settings document %1$s exceeds the %2$d byte limit.', '__plugin_txtd' ),
					$path,
					self::MAX_DOCUMENT_BYTES
				)
			);
		}

		$decoded = json_decode( (string) $raw, true );
		// A JSON list ([1,2,3]) would silently become settings "0","1","2".
		if ( ! is_array( $decoded ) || ( ! empty( $decoded ) && array_is_list( $decoded ) ) ) {
			$this->fail(
				$assoc_args,
				1,
				'invalid_params',
				sprintf(
					/* translators: %s: file path. */
					__( 'The settings document is not a JSON object of setting ids: %s.', '__plugin_txtd' ),
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

			// emit_write_result() halts, but never rely on that for control flow: a second
			// envelope on STDOUT would break the machine contract.
			return;
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
			\WP_CLI::log(
				sprintf(
					/* translators: %s: comma separated list of connected field ids. */
					__( 'connected_fields: %s', '__plugin_txtd' ),
					implode( ', ', array_map( 'strval', $data['connected_fields'] ) )
				)
			);
		}

		if ( isset( $payload['persisted'] ) && is_array( $payload['persisted'] ) && ! empty( $payload['persisted'] ) ) {
			$this->render_map( $payload['persisted'], 'persisted', 'value' );
		}

		if ( ! empty( $payload['unchanged'] ) ) {
			\WP_CLI::log(
				sprintf(
					/* translators: %s: comma separated list of setting ids. */
					__( 'unchanged: %s', '__plugin_txtd' ),
					implode( ', ', array_map( 'strval', $payload['unchanged'] ) )
				)
			);
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
