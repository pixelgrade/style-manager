<?php
/**
 * The agent-surface command cores.
 *
 * One public method per verb of Style Manager's slice of the Pixelgrade agent-surface
 * contract, each returning the §2 envelope pieces as a plain array. Both surfaces —
 * `Provider\CliCommands` (WP-CLI) and `Provider\Abilities` (the WordPress Abilities API)
 * — call these methods and do nothing but shape the result for their own transport.
 *
 * That is the whole point: contract §4 forbids a parallel second implementation, so the
 * classification of a write into `ok`/`noop`/`plus_stripped`, the export scoping, the
 * palette write payload, the F6 all-or-nothing read and the F10 palette-catalog check
 * all live here, once.
 *
 * The returned shape is:
 *
 *     array(
 *       'exit'     => int,          // 0 ok · 1 error · 2 completed with findings
 *       'code'     => string,       // the closed machine token
 *       'summary'  => string,       // one human line
 *       'data'     => array,        // the command's pinned data payload
 *       'warnings' => array,        // [ { code, message, ids } ]
 *       'write'    => array|null,   // writes only: persisted / unchanged / stripped
 *     )
 *
 * Exit 3 (`permission_denied`) is deliberately NOT produced here: each surface resolves
 * and checks its caller before it ever reaches a core (§3.0), because "who is asking"
 * is a transport question — `--user` for the CLI, `permission_callback` for an ability.
 *
 * Surface-only concerns are injected as callables rather than re-implemented:
 * `$confirm` runs §3.6's confirmation gate at exactly the point the CLI ran it, so the
 * ordering of `invalid_params` / `ordering_conflict` / `confirmation_required` is
 * identical on both surfaces; `resolve_output` and `before_save` let the CLI keep its
 * `--output=@file` filesystem behavior without teaching the core about files, which an
 * MCP client does not have.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.5.3
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\FontPalettes;

/**
 * The shared command cores behind `wp pixelgrade sm` and the `pixelgrade/*` abilities.
 *
 * @since 2.5.3
 */
class AgentCommands {

	/**
	 * The capability every verb in this surface requires.
	 *
	 * Kept identical to `CliCommands::CAPABILITY` so §4's promise — an ability is never
	 * more permissive than its command — is literally one constant.
	 */
	public const CAPABILITY = 'edit_theme_options';

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
	 * The design-system preview payload builder — the same object the REST GET serves.
	 *
	 * Nullable only so the CLI, which has no design-system verb, can build a core without
	 * dragging the endpoint's dependencies along. The container always wires it.
	 *
	 * @var DesignSystemPreviewEndpoint|null
	 */
	protected ?DesignSystemPreviewEndpoint $design_system_preview;

	/**
	 * Create the command cores.
	 *
	 * @since 2.5.3
	 *
	 * @param Options                         $options               Options provider.
	 * @param HeadlessCustomizer              $headless_customizer   Headless Customizer.
	 * @param SettingsWriter                  $settings_writer       Settings writer.
	 * @param FontPalettes                    $font_palettes         Font palettes.
	 * @param PaletteGenerator                $palette_generator     Palette generator.
	 * @param DesignSystemPreviewEndpoint|null $design_system_preview Design system preview endpoint.
	 */
	public function __construct(
		Options $options,
		HeadlessCustomizer $headless_customizer,
		SettingsWriter $settings_writer,
		FontPalettes $font_palettes,
		PaletteGenerator $palette_generator,
		?DesignSystemPreviewEndpoint $design_system_preview = null
	) {
		$this->options               = $options;
		$this->headless_customizer   = $headless_customizer;
		$this->settings_writer       = $settings_writer;
		$this->font_palettes         = $font_palettes;
		$this->palette_generator     = $palette_generator;
		$this->design_system_preview = $design_system_preview;
	}

	/*
	|--------------------------------------------------------------------------
	| Reads
	|--------------------------------------------------------------------------
	*/

	/**
	 * Read design settings through the three-store resolver (`sm get`).
	 *
	 * F6, all-or-nothing: any unrecognized id fails the WHOLE call with `invalid_params`
	 * and the offenders in `data.unknown`. A partial read returning success would be
	 * exactly the silent-empty-map hazard §3.0 exists to prevent.
	 *
	 * @since 2.5.3
	 *
	 * @param string[]    $ids     Setting ids to read.
	 * @param bool        $all     Return every readable setting.
	 * @param string|null $section Restrict to this Customizer section's controls.
	 * @param bool        $details Return the full settings data instead of an id => value map.
	 *
	 * @return array The core result.
	 */
	public function get_settings( array $ids, bool $all = false, ?string $section = null, bool $details = false ): array {
		$source = $details
			? $this->headless_customizer->get_settings_data()
			: $this->headless_customizer->get_settings_values();

		$ids = array_map( 'strval', $ids );
		if ( is_string( $section ) && '' !== $section ) {
			$ids = array_merge( $ids, $this->headless_customizer->get_section_setting_ids( $section ) );
		}
		$ids = array_values( array_unique( $ids ) );

		if ( empty( $ids ) && ! $all ) {
			return $this->result(
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
				return $this->result(
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

		return $this->result(
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
	 * Describe the Style Manager panels, sections and controls (`sm structure`).
	 *
	 * The `html` key is omitted unless asked for — the rendered control markup is heavy
	 * and no agent needs it to reason about the design system.
	 *
	 * @since 2.5.3
	 *
	 * @param string|null $section   Only describe this section.
	 * @param bool        $with_html Include the rendered control markup.
	 *
	 * @return array The core result.
	 */
	public function get_structure( ?string $section = null, bool $with_html = false ): array {
		$structure = $this->headless_customizer->get_structure();

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
				return $this->result(
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

		if ( ! $with_html ) {
			foreach ( $structure['sections'] as $section_index => $section_data ) {
				foreach ( (array) ( $section_data['controls'] ?? [] ) as $control_index => $control ) {
					unset( $structure['sections'][ $section_index ]['controls'][ $control_index ]['html'] );
				}
			}
		}

		return $this->result(
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
	 * Build the stamped, re-importable design-system payload (`sm export`).
	 *
	 * Scope is the Style Manager surface by default — the `sm_*` ids plus the theme's
	 * connected fields — so restoring a design system never silently rewrites the site
	 * title. Values are reported exactly as stored (§3.4: export passes shipped state
	 * through unmodified).
	 *
	 * @since 2.5.3
	 *
	 * @param string[]|null $include Narrow to these ids. `null` means no narrowing; an
	 *                               empty array narrows to nothing, which is what an
	 *                               `--include=,` on the CLI has always meant.
	 * @param bool          $all     Export the full Customizer settings map.
	 *
	 * @return array The core result.
	 */
	public function export( ?array $include = null, bool $all = false ): array {
		$settings = $this->headless_customizer->get_settings_values();
		$scope    = $all ? 'all' : 'style_manager';

		if ( 'style_manager' === $scope ) {
			$surface  = array_flip( $this->style_manager_surface_ids( $settings ) );
			$settings = array_intersect_key( $settings, $surface );
		}

		if ( null !== $include ) {
			$filtered = [];
			$missing  = [];
			foreach ( $include as $id ) {
				$id = (string) $id;
				if ( array_key_exists( $id, $settings ) ) {
					$filtered[ $id ] = $settings[ $id ];
				} else {
					$missing[] = $id;
				}
			}

			if ( ! empty( $missing ) ) {
				return $this->result(
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

		// The re-importable payload stays exactly the pinned `{meta, settings}` shape; `scope`
		// is envelope-only reporting so `set --from-file` never sees an unpinned key.
		$data          = $payload;
		$data['scope'] = $scope;

		return $this->result(
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
	 * The normalized design-system preview — the same payload `GET
	 * style_manager/v1/design-system-preview` serves, assembled by the same object.
	 *
	 * This is §4's single carve-out: a read-only ability mapping to a REST GET rather than
	 * to a command. The payload assembly is NOT copied here; it is delegated.
	 *
	 * @since 2.5.3
	 *
	 * @return array The core result.
	 */
	public function design_system_preview(): array {
		if ( null === $this->design_system_preview ) {
			return $this->result(
				1,
				'invalid_params',
				__( 'The design system preview is not available on this site.', '__plugin_txtd' )
			);
		}

		$payload = $this->design_system_preview->get_payload();

		$present = array_values(
			array_filter(
				[ 'colors', 'typography', 'spacing' ],
				static function ( string $section ) use ( $payload ): bool {
					return ! empty( $payload[ $section ] );
				}
			)
		);

		return $this->result(
			0,
			'ok',
			sprintf(
				/* translators: %s: comma separated list of design system sections. */
				__( 'Design system revision %1$s; sections: %2$s.', '__plugin_txtd' ),
				(string) ( $payload['revision'] ?? '' ),
				empty( $present ) ? __( 'none', '__plugin_txtd' ) : implode( ', ', $present )
			),
			$payload
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Writes
	|--------------------------------------------------------------------------
	*/

	/**
	 * Write design settings through the gated write path (`sm set`).
	 *
	 * One call is ONE `SettingsWriter::save()` (§3.12), and the §3.4 ordering law is
	 * enforced here because one invocation is exactly the law's scope. The law itself is
	 * never re-derived: `SettingsWriter::find_ordering_conflict()` is the shared policy.
	 *
	 * @since 2.5.3
	 *
	 * @param array         $values  Requested id => value map, typed.
	 * @param bool          $dry_run Report the predicted diff without writing.
	 * @param callable|null $confirm §3.6 gate, `fn( string $question ): bool`. It may halt
	 *                               (the CLI does); returning anything but `true` produces
	 *                               a `confirmation_required` result. `null` means the
	 *                               caller has already satisfied the gate.
	 *
	 * @return array The core result.
	 */
	public function set_settings( array $values, bool $dry_run = false, ?callable $confirm = null ): array {
		if ( empty( $values ) ) {
			return $this->result(
				1,
				'invalid_params',
				__( 'Nothing to write: pass <id>=<value> pairs, --from-file=<path>, or `-` for STDIN.', '__plugin_txtd' )
			);
		}

		$conflict = $this->settings_writer->find_ordering_conflict( $values );
		if ( null !== $conflict ) {
			return $this->result(
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

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			if ( SettingsWriter::master_font_slots_in( $values ) ) {
				$question = __( 'This payload carries a master font slot; saving it regenerates the entire per-element font defaults table and clobbers per-element overrides.', '__plugin_txtd' );

				$gated = $this->gate( $confirm, $question );
				if ( null !== $gated ) {
					return $gated;
				}
			}

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				return $this->result_from_wp_error( $result, $values );
			}
		}

		return $this->classify_write( $result, array_keys( $values ), $dry_run );
	}

	/**
	 * Apply a font palette and fan it out to the connected per-element font fields
	 * (`sm apply-font-palette`).
	 *
	 * F10: the id is validated against `FontPalettes::get_palettes_for_control()`, which
	 * lists free *and* pro palettes — a pro pick must reach the Plus gate to earn
	 * `tier_locked_palette` rather than being rejected up front. The default `system`
	 * value is not in that catalog, so returning to it is a `set`, not a palette apply.
	 *
	 * @since 2.5.3
	 *
	 * @param string        $palette_id The font palette id.
	 * @param bool          $dry_run    Report the predicted diff without writing.
	 * @param callable|null $confirm    §3.6 gate — see `set_settings()`.
	 *
	 * @return array The core result.
	 */
	public function apply_font_palette( string $palette_id, bool $dry_run = false, ?callable $confirm = null ): array {
		if ( '' === $palette_id ) {
			return $this->result( 1, 'invalid_params', __( 'Pass a font palette id.', '__plugin_txtd' ) );
		}

		$palettes = $this->font_palettes->get_palettes_for_control();
		if ( ! isset( $palettes[ $palette_id ] ) ) {
			return $this->result(
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

		$values = [ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => $palette_id ];

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			$question = sprintf(
				/* translators: %s: font palette id. */
				__( 'Applying the "%s" font palette rewrites every connected per-element font field.', '__plugin_txtd' ),
				$palette_id
			);

			$gated = $this->gate( $confirm, $question );
			if ( null !== $gated ) {
				return $gated;
			}

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				return $this->result_from_wp_error( $result, $values );
			}
		}

		return $this->classify_write(
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
	 * Regenerate — or apply verbatim — the Color System palette output
	 * (`sm apply-color-palette`).
	 *
	 * All three v0.3.10 rulings live here so neither surface can drift from them:
	 * `sm_site_color_variation` rides along ONLY when a variation is supplied (F-W5-1),
	 * `generator: none` requires an output and applies it verbatim (F-W5-2), and any
	 * source-based apply records the palette as custom (F-W5-3).
	 *
	 * @since 2.5.3
	 *
	 * @param array $params {
	 *     @type string        $source         Required. The palette source JSON.
	 *     @type string        $generator      `node` (default) or `none`.
	 *     @type mixed         $variation      Optional `sm_site_color_variation` (1-12).
	 *     @type bool          $dry_run        Report the predicted diff without writing.
	 *     @type callable|null $confirm        §3.6 gate — see `set_settings()`.
	 *     @type callable|null $resolve_output `fn( string $mode ): array{raw:?string, echo:bool, file:?string}`.
	 *                                         Invoked at exactly the point the CLI resolved
	 *                                         `--output`, so a malformed destination still
	 *                                         costs nothing and can never fail after the save.
	 *     @type callable|null $before_save    `fn( string $generated_json ): void`, run after the
	 *                                         confirmation and before the save. The CLI writes
	 *                                         its `--output=@file` here so a failing file write
	 *                                         leaves the palette unpersisted.
	 * }
	 *
	 * @return array The core result.
	 */
	public function apply_color_palette( array $params ): array {
		$source_json = ( array_key_exists( 'source', $params ) && null !== $params['source'] )
			? (string) $params['source']
			: null;

		if ( null === $source_json ) {
			return $this->result(
				1,
				'invalid_params',
				__( '--source is required: pass the palette source JSON, @<file>, or `-` for STDIN.', '__plugin_txtd' )
			);
		}

		$groups = PaletteGenerator::parse_source( $source_json );
		if ( is_wp_error( $groups ) ) {
			return $this->result( 1, 'invalid_params', (string) $groups->get_error_message() );
		}

		$mode = $params['generator'] ?? 'node';
		$mode = is_string( $mode ) ? strtolower( trim( $mode ) ) : 'node';
		if ( ! in_array( $mode, [ 'node', 'none' ], true ) ) {
			return $this->result(
				1,
				'invalid_params',
				__( '--generator must be `node` or `none`.', '__plugin_txtd' )
			);
		}

		$overrides = [];
		$variation = $params['variation'] ?? null;
		if ( null !== $variation && '' !== $variation ) {
			if ( ! is_numeric( $variation ) || (int) $variation < 1 || (int) $variation > 12 ) {
				return $this->result(
					1,
					'invalid_params',
					__( '--variation must be a whole number between 1 and 12.', '__plugin_txtd' )
				);
			}

			$overrides[ PaletteGenerator::VARIATION_SETTING_ID ] = (int) $variation;
		}

		$dry_run = ! empty( $params['dry_run'] );

		$resolve_output = $params['resolve_output'] ?? null;
		$destination    = is_callable( $resolve_output ) ? (array) $resolve_output( $mode ) : [];
		$output_raw     = $destination['raw'] ?? null;
		$echo_output    = ! empty( $destination['echo'] );
		$output_file    = isset( $destination['file'] ) ? (string) $destination['file'] : '';

		$options = null;
		if ( 'none' === $mode ) {
			if ( null === $output_raw || '' === trim( (string) $output_raw ) ) {
				return $this->result(
					1,
					'invalid_params',
					__( '--generator=none applies a pre-generated palette output, so --output=<json|@file> is required. Nothing was written.', '__plugin_txtd' )
				);
			}

			$applied = PaletteGenerator::validate_renderable( (string) $output_raw );
			if ( is_wp_error( $applied ) ) {
				return $this->result(
					1,
					'invalid_params',
					(string) $applied->get_error_message() . ' ' . __( 'Nothing was written.', '__plugin_txtd' )
				);
			}
		} else {
			$generated = $this->generate_palette_output( $source_json, $overrides, $options );
			if ( ! empty( $generated['__failed'] ) ) {
				return $generated['result'];
			}

			$applied = $generated['applied'];
		}

		$values = $this->palette_write_payload( $source_json, $applied['json'], $overrides );

		/*
		 * Read the diff BEFORE the write. Computing it afterwards would compare the new blob with
		 * itself — `changed` always false, `stored_generator_produced` describing what we just
		 * wrote — and the hand-authored-overwrite signal would exist only under a dry run, which
		 * is the one run that cannot destroy anything.
		 */
		$diff = $this->palette_output_diff( $applied['json'] );

		if ( $dry_run ) {
			$result = $this->settings_writer->preview( $values );
		} else {
			$gated = $this->gate(
				$params['confirm'] ?? null,
				__( 'Applying a color palette replaces the whole generated ramp, including any hand-authored palette output stored on this site.', '__plugin_txtd' )
			);
			if ( null !== $gated ) {
				return $gated;
			}

			// Run before the save, so a failing side effect fails the whole call with nothing
			// persisted — rather than reporting "nothing was done" over a site whose palette has
			// in fact already changed.
			$before_save = $params['before_save'] ?? null;
			if ( is_callable( $before_save ) ) {
				$before_save( $applied['json'] );
			}

			$result = $this->settings_writer->save( $values, true );
			if ( is_wp_error( $result ) ) {
				return $this->result_from_wp_error( $result, $values );
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

		if ( $echo_output ) {
			$extra['output'] = $applied['palettes'];
		} elseif ( '' !== $output_file && ! $dry_run ) {
			$extra['output_file'] = $output_file;
		}

		return $this->classify_write( $result, array_keys( $values ), $dry_run, $extra );
	}

	/**
	 * Invalidate Style Manager's cached Customizer config and option details
	 * (`sm flush-cache`).
	 *
	 * @since 2.5.3
	 *
	 * @return array The core result.
	 */
	public function flush_cache(): array {
		$this->options->invalidate_all_caches();

		return $this->result(
			0,
			'ok',
			__( 'Style Manager caches flushed (Customizer config, option details, opt-name).', '__plugin_txtd' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Shared policy
	|--------------------------------------------------------------------------
	*/

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
	public function style_manager_surface_ids( array $known ): array {
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
	 * Assemble the one batched palette write (§3.12 — one publish per process).
	 *
	 * Contract §1.1 names four settings. `sm_site_color_variation` is included **only when
	 * a variation was asked for**, and that is deliberate: the setting is a member of
	 * `ColorPalettes::FINE_TUNE_PALETTE_FIELDS`, hence Pixelgrade Plus-gated, and
	 * `SettingsWriter::strip_locked_premium_settings()` drops `sm_advanced_palette_output`
	 * whenever a premium id is present in the same payload. Sending it unconditionally would
	 * therefore make the verb strip its own output on every site without Plus.
	 *
	 * `sm_is_custom_color_palette` is always true: applying an arbitrary source *is* what makes
	 * a palette custom, whichever way its output was produced. It is written as the integer `1`
	 * rather than boolean `true` because that is the representation the option round-trips as —
	 * the Customizer reads it back as the string `'1'`, and a boolean would therefore never
	 * compare equal to what is on disk, costing the verb its `noop` on every re-run (§3.5).
	 *
	 * @param string $source_json The palette source as given.
	 * @param string $output_json The palette output being persisted.
	 * @param array  $overrides   Resolved option overrides (the variation).
	 *
	 * @return array setting_id => value.
	 */
	public function palette_write_payload( string $source_json, string $output_json, array $overrides ): array {
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
	 * What regenerating would do to the stored `sm_advanced_palette_output`.
	 *
	 * `stored_generator_produced` is the one a caller must read before a real run: a
	 * `false` there means the site carries a hand-authored palette blob (some gene-migration
	 * runs write the option directly) and this verb is about to replace it.
	 *
	 * @param string $generated_json The generated palette output.
	 *
	 * @return array
	 */
	public function palette_output_diff( string $generated_json ): array {
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
	 * Run the Node generator, or produce a result that never leaves a stale output behind.
	 *
	 * @param string     $source_json The palette source.
	 * @param array      $overrides   Resolved option overrides.
	 * @param array|null $options     Set to the resolved generator options on success.
	 *
	 * @return array{__failed?: bool, result?: array, applied?: array}
	 */
	protected function generate_palette_output( string $source_json, array $overrides, ?array &$options ): array {
		// Resolved before the availability probe, exactly as the CLI has always done it: the
		// eight tuning options come from the three-store resolver, not from the artifact.
		$options = $this->palette_generator->resolve_options( $overrides );

		if ( ! $this->palette_generator->is_available() ) {
			$looked_for = $this->palette_generator->looked_for();
			$options    = null;

			return [
				'__failed' => true,
				'result'   => $this->result(
					1,
					'generator_unavailable',
					sprintf(
						/* translators: %s: comma separated list of paths that were probed. */
						__( 'The bundled Node palette generator is not available; looked for: %s. Nothing was written.', '__plugin_txtd' ),
						implode( ', ', $looked_for )
					),
					[ 'looked_for' => $looked_for ]
				),
			];
		}

		$generated = $this->palette_generator->generate( $source_json, $options );

		if ( is_wp_error( $generated ) ) {
			$code = [
				'style_manager_generator_unavailable' => 'generator_unavailable',
				'style_manager_generator_timeout'     => 'generator_timeout',
			][ $generated->get_error_code() ] ?? 'invalid_params';

			$data               = (array) $generated->get_error_data();
			$data['error_code'] = (string) $generated->get_error_code();

			// The options never made it into a palette; do not report them as if they had.
			$options = null;

			return [
				'__failed' => true,
				'result'   => $this->result(
					1,
					$code,
					(string) $generated->get_error_message() . ' ' . __( 'Nothing was written.', '__plugin_txtd' ),
					$data
				),
			];
		}

		return [ 'applied' => $generated ];
	}

	/**
	 * Contract §3.6 — run the caller's confirmation gate.
	 *
	 * The CLI's gate halts on refusal; an ability's gate returns `false` and the caller gets
	 * a `confirmation_required` result. A `null` gate means the surface has already decided.
	 *
	 * @param callable|null $confirm  The gate.
	 * @param string        $question What is about to happen.
	 *
	 * @return array|null A `confirmation_required` result, or null to proceed.
	 */
	protected function gate( ?callable $confirm, string $question ): ?array {
		if ( null === $confirm || true === $confirm( $question ) ) {
			return null;
		}

		return $this->result( 1, 'confirmation_required', $question );
	}

	/**
	 * Turn a SettingsWriter result into the §2 envelope pieces.
	 *
	 * A non-empty `stripped[]` forces `code:"plus_stripped"` and exit 2 even when other ids
	 * saved successfully (v0.3.4, W1 F4) — one deterministic token to branch on, with the
	 * per-id truth in `stripped[].reason`.
	 *
	 * @param array    $result        SettingsWriter::save()/preview() result.
	 * @param string[] $requested_ids Ids the caller asked to write.
	 * @param bool     $dry_run       Whether this was a dry run.
	 * @param array    $extra_data    Extra `data` keys.
	 *
	 * @return array The core result.
	 */
	protected function classify_write( array $result, array $requested_ids, bool $dry_run, array $extra_data = [] ): array {
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

		return $this->result(
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
	 * Convert a WP_Error from the write path into a result.
	 *
	 * `style_manager_site_editor_nothing_to_save` means every id was unknown or
	 * capability-denied. Contract §2 makes that a finding, not an error: `unknown_setting`
	 * strips, exit 2 — never a silent drop and never exit 1. Any other write-path error is
	 * exit 1 with the original code preserved in `data.error_code`.
	 *
	 * @param \WP_Error $error  The error.
	 * @param array     $values The requested id => value map.
	 *
	 * @return array The core result.
	 */
	protected function result_from_wp_error( \WP_Error $error, array $values ): array {
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

			return $this->classify_write(
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

		return $this->result(
			1,
			'invalid_params',
			(string) $error->get_error_message(),
			[ 'error_code' => (string) $error->get_error_code() ]
		);
	}

	/**
	 * Build a core result.
	 *
	 * @param int        $exit     Exit code (0, 1 or 2).
	 * @param string     $code     Closed machine token.
	 * @param string     $summary  One human line.
	 * @param array      $data     Payload.
	 * @param array      $warnings Warning entries.
	 * @param array|null $write    Writes only: persisted / unchanged / stripped.
	 *
	 * @return array
	 */
	protected function result( int $exit, string $code, string $summary, array $data = [], array $warnings = [], ?array $write = null ): array {
		return [
			'exit'     => $exit,
			'code'     => $code,
			'summary'  => $summary,
			'data'     => $data,
			'warnings' => array_values( $warnings ),
			'write'    => $write,
		];
	}
}
