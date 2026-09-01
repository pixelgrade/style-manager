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
 * The commands themselves are thin: parse the flags, STDIN and `--file` paths, run
 * §3.0's user check and §3.6's confirmation gate, then hand off to the SHARED
 * Provider\AgentCommands cores and print what comes back. Everything that decides an
 * outcome — the export scoping, the F6 all-or-nothing read, the F10 palette catalog
 * check, the §3.4 ordering law, the palette write payload, and the classification of a
 * write into ok/noop/plus_stripped — lives there, so `Provider\Abilities` reaches the
 * same rulings through the same code rather than a second copy of it (contract §4).
 *
 * What stays here is genuinely CLI-only: `--format`, `--quiet`, `--yes`, every
 * filesystem path flag (`--file`, `--from-file`, `--output=@file`, `--source=@file`,
 * `-` for STDIN), the table renderer, and the deprecated `wp style-manager flush-cache`
 * alias. An MCP client has no filesystem on the server and must not be handed one.
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
	 * The shared command cores — the SAME object the `pixelgrade/*` abilities call.
	 *
	 * This is the CLI's ONLY collaborator. Options, the headless Customizer, the settings
	 * writer, the font palettes and the palette generator are reached exclusively through
	 * it, so a command and an ability cannot classify the same outcome differently.
	 *
	 * @var AgentCommands
	 */
	protected AgentCommands $agent_commands;

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
	 * @param AgentCommands|null $agent_commands      Shared command cores. Built from the same
	 *                                                collaborators when omitted, so the CLI is
	 *                                                constructible without the container.
	 */
	public function __construct(
		Options $options,
		HeadlessCustomizer $headless_customizer,
		SettingsWriter $settings_writer,
		FontPalettes $font_palettes,
		PaletteGenerator $palette_generator,
		?AgentCommands $agent_commands = null
	) {
		$this->agent_commands = $agent_commands ?: new AgentCommands(
			$options,
			$headless_customizer,
			$settings_writer,
			$font_palettes,
			$palette_generator
		);
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

		$section = $this->flag( $assoc_args, 'section' );

		$this->emit_core(
			$assoc_args,
			$this->agent_commands->get_settings(
				array_map( 'strval', (array) $args ),
				$this->bool_flag( $assoc_args, 'all' ),
				is_string( $section ) ? $section : null,
				$this->bool_flag( $assoc_args, 'details' )
			)
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

		$this->emit_core(
			$assoc_args,
			$this->agent_commands->set_settings(
				$this->collect_set_payload( (array) $args, $assoc_args ),
				$this->bool_flag( $assoc_args, 'dry-run' ),
				$this->confirmation_gate( $assoc_args )
			)
		);
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

		$include = $this->flag( $assoc_args, 'include' );
		$include = ( is_string( $include ) && '' !== $include )
			? array_values( array_filter( array_map( 'trim', explode( ',', $include ) ) ) )
			: null;

		$core = $this->agent_commands->export( $include, $this->bool_flag( $assoc_args, 'all' ) );

		$file = $this->flag( $assoc_args, 'file' );
		if ( 0 === $core['exit'] && is_string( $file ) && '' !== $file ) {
			$this->write_export_file( $assoc_args, $file, $core['data'] );

			$core['data']['file'] = $file;
		}

		$this->emit_core( $assoc_args, $core );
	}

	/**
	 * Write the re-importable export payload to `--file`.
	 *
	 * Only the pinned `{meta, settings}` shape is written — `scope` is envelope-only
	 * reporting, so `set --from-file` never sees an unpinned key.
	 *
	 * @param array  $assoc_args Associative arguments.
	 * @param string $file       Destination path.
	 * @param array  $data       The export core's data payload.
	 */
	protected function write_export_file( array $assoc_args, string $file, array $data ): void {
		$payload = [
			'meta'     => $data['meta'] ?? [],
			'settings' => $data['settings'] ?? [],
		];

		$json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		if ( $this->bool_flag( $assoc_args, 'pretty' ) ) {
			$json_flags |= JSON_PRETTY_PRINT;
		}

		$written = @file_put_contents( $file, (string) wp_json_encode( $payload, $json_flags ) . "\n" );
		if ( false !== $written ) {
			return;
		}

		$last = error_get_last();
		$why  = ! empty( $last['message'] ) ? (string) $last['message'] : __( 'unknown error', '__plugin_txtd' );

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

		$section = $this->flag( $assoc_args, 'section' );

		$this->emit_core(
			$assoc_args,
			$this->agent_commands->get_structure(
				is_string( $section ) ? $section : null,
				$this->bool_flag( $assoc_args, 'with-html' )
			)
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

		$this->emit_core(
			$assoc_args,
			$this->agent_commands->apply_font_palette(
				isset( $args[0] ) ? (string) $args[0] : '',
				$this->bool_flag( $assoc_args, 'dry-run' ),
				$this->confirmation_gate( $assoc_args )
			)
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

		// Captured by both closures below: the `--output` destination the core resolves at the
		// point the flag matters, and the one the file write consults just before the save.
		$destination = [
			'kind' => 'none',
			'path' => '',
		];

		$this->emit_core(
			$assoc_args,
			$this->agent_commands->apply_color_palette(
				[
					'source'         => $this->collect_palette_source( $assoc_args ),
					'generator'      => $this->flag( $assoc_args, 'generator', 'node' ),
					'variation'      => $this->flag( $assoc_args, 'variation' ),
					'dry_run'        => $this->bool_flag( $assoc_args, 'dry-run' ),
					'confirm'        => $this->confirmation_gate( $assoc_args ),

					/*
					 * Parsed before the subprocess runs, so a malformed --output costs nothing and,
					 * more to the point, can never fail *after* the palette has been persisted.
					 */
					'resolve_output' => function ( string $mode ) use ( $assoc_args, &$destination ): array {
						if ( 'none' === $mode ) {
							return [
								'raw'  => $this->read_applied_palette_output( $assoc_args ),
								'echo' => false,
								'file' => '',
							];
						}

						$destination = $this->resolve_output_destination( $assoc_args );

						return [
							'raw'  => null,
							'echo' => 'json' === $destination['kind'],
							'file' => 'file' === $destination['kind'] ? $destination['path'] : '',
						];
					},

					/*
					 * Written before the save, so a failing file write fails the whole command with
					 * nothing persisted — rather than reporting exit 1 "nothing was done" over a
					 * site whose palette has in fact already changed.
					 */
					'before_save'    => function ( string $generated_json ) use ( $assoc_args, &$destination ): void {
						$this->write_output_file( $assoc_args, $destination, $generated_json );
					},
				]
			)
		);
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
	 * Read the bytes `--generator=none` applies verbatim: inline JSON or `@<path>`.
	 *
	 * Only the reading is CLI work. Whether the output is *required* and whether it is
	 * renderable are shared policy, and live in `AgentCommands::apply_color_palette()` —
	 * so returning `null` here is how "the caller gave us nothing" reaches that ruling.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return string|null
	 */
	protected function read_applied_palette_output( array $assoc_args ): ?string {
		$output = $this->flag( $assoc_args, 'output' );

		if ( ! is_string( $output ) || '' === trim( $output ) ) {
			return null;
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

		return (string) $raw;
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

		$this->emit_core( $assoc_args, $this->agent_commands->flush_cache() );
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
	 * The §3.6 confirmation gate, as the shared cores expect it.
	 *
	 * `confirm_destructive()` halts on a refusal, so this only ever returns `true`. The
	 * closure exists so the core can run the gate at exactly the point the command did —
	 * after `invalid_params` and `ordering_conflict`, before the save — which is what keeps
	 * the CLI and the abilities reporting the same code for the same mistake.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return callable
	 */
	protected function confirmation_gate( array $assoc_args ): callable {
		return function ( string $question ) use ( $assoc_args ): bool {
			$this->confirm_destructive( $assoc_args, $question );

			return true;
		};
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
	 * Emit a core result as the shared envelope (contract §2) and halt.
	 *
	 * This is the whole of the CLI's job once the flags are parsed: the exit code, the closed
	 * machine token, the summary, the payload, the warnings and the write diff all come from
	 * `AgentCommands`, so an ability and a command can never classify the same outcome
	 * differently.
	 *
	 * @param array $assoc_args Associative arguments.
	 * @param array $core       An `AgentCommands` result.
	 */
	protected function emit_core( array $assoc_args, array $core ): void {
		$write = $core['write'] ?? null;

		$this->emit(
			$assoc_args,
			(int) $core['exit'],
			(string) $core['code'],
			(string) $core['summary'],
			(array) $core['data'],
			(array) $core['warnings'],
			is_array( $write ) ? $write : []
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
			\WP_CLI::line( (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
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
