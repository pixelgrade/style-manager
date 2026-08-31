<?php
/**
 * WordPress Abilities API provider.
 *
 * Registers Style Manager's eight `pixelgrade/*` abilities — the same eight verbs the
 * `wp pixelgrade sm` subtree exposes, reached through the SAME `Provider\AgentCommands`
 * cores. Nothing here re-implements a command: this file parses typed JSON input, runs
 * the §3.6 confirmation rule, and maps a core result onto the Abilities return channel.
 *
 * Contract references: §4 (naming, annotations, privacy, entitlement seam), §2 (the
 * envelope), §3.0 (never auto-elevate), §3.4 (the ordering law), §3.12 (one publish per
 * process), and §1.1's pinned `apply-color-palette` rulings.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.5.3
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Registers Style Manager's abilities.
 *
 * @since 2.5.3
 */
class Abilities extends AbstractHookProvider {

	/**
	 * The ability category every Pixelgrade plugin shares.
	 */
	public const CATEGORY = 'pixelgrade';

	/**
	 * The one filter through which the curated MCP server (hosted by Pixelgrade Assistant)
	 * declares which abilities it may expose. With Assistant absent it returns an empty
	 * array and every ability stays private — the correct default per contract §4.
	 */
	public const PUBLIC_ABILITIES_FILTER = 'pixelgrade/mcp/public_abilities';

	/**
	 * The shared entitlement seam. No ability declares an `entitlement` today (§4: Plus
	 * gating happens inside the write, as stripping) — the mechanism ships anyway so the
	 * first ability that needs it has nowhere to invent its own.
	 */
	public const ENTITLEMENT_FILTER = 'pixelgrade/has_entitlement';

	/**
	 * The shared command cores.
	 *
	 * @var AgentCommands
	 */
	protected AgentCommands $agent_commands;

	/**
	 * Create the abilities provider.
	 *
	 * @since 2.5.3
	 *
	 * @param AgentCommands $agent_commands The shared command cores.
	 */
	public function __construct( AgentCommands $agent_commands ) {
		$this->agent_commands = $agent_commands;
	}

	/**
	 * Register the hooks, when the Abilities API is present.
	 *
	 * Guarded exactly the way `CliCommands::register_hooks()` guards on `\WP_CLI`: the API
	 * is WordPress core only since 6.9, and `wp_register_ability()` hard-fails outside the
	 * `wp_abilities_api_init` action.
	 *
	 * @since 2.5.3
	 */
	public function register_hooks() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->add_action( 'wp_abilities_api_categories_init', 'register_category' );
		$this->add_action( 'wp_abilities_api_init', 'register_abilities' );
	}

	/**
	 * Register the shared `pixelgrade` category, defensively and idempotently.
	 *
	 * Each of the four Pixelgrade plugins does this, so it must work with any subset of
	 * them active and must never double-register.
	 *
	 * @since 2.5.3
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Pixelgrade', '__plugin_txtd' ),
				'description' => __( 'Design system, licensing, starter content and block operations for the Pixelgrade stack.', '__plugin_txtd' ),
			]
		);
	}

	/**
	 * Register every ability this plugin owns.
	 *
	 * @since 2.5.3
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$public = (array) apply_filters( self::PUBLIC_ABILITIES_FILTER, [] );

		foreach ( $this->descriptors() as $descriptor ) {
			$entitlement = $descriptor['entitlement'] ?? null;

			// §4 forward policy: a gated ability is ABSENT from the registry, not registered
			// and refusing. Deliberately reached by nothing today.
			if ( null !== $entitlement && ! $this->has_entitlement( (string) $entitlement ) ) {
				continue;
			}

			wp_register_ability(
				$descriptor['name'],
				[
					'label'               => $descriptor['label'],
					'description'         => $descriptor['description'],
					'category'            => self::CATEGORY,
					'input_schema'        => $descriptor['input_schema'],
					'output_schema'       => $descriptor['output_schema'],
					'execute_callback'    => $descriptor['execute_callback'],
					'permission_callback' => function () use ( $entitlement ) {
						return $this->check_permissions( null === $entitlement ? null : (string) $entitlement );
					},
					'meta'                => [
						'annotations' => $descriptor['annotations'],
						'mcp'         => [
							// Private by default; the curated server owns the one reviewed whitelist.
							'public' => in_array( $descriptor['name'], $public, true ),
						],
					],
				]
			);
		}
	}

	/**
	 * The capability gate — the same one every `wp pixelgrade sm` verb requires, never more
	 * permissive (§4), and never auto-elevated (§3.0).
	 *
	 * The entitlement check runs here as well as at registration because registration
	 * happens at `init` while entitlement state can change afterwards (a license activated
	 * mid-request, dev mode toggled).
	 *
	 * @since 2.5.3
	 *
	 * @param string|null $entitlement Optional entitlement key.
	 *
	 * @return true|\WP_Error
	 */
	public function check_permissions( ?string $entitlement = null ) {
		if ( ! current_user_can( AgentCommands::CAPABILITY ) ) {
			return new \WP_Error(
				'permission_denied',
				sprintf(
					/* translators: %s: capability name. */
					__( 'This ability requires the `%s` capability.', '__plugin_txtd' ),
					AgentCommands::CAPABILITY
				),
				[ 'status' => 403 ]
			);
		}

		if ( null !== $entitlement && ! $this->has_entitlement( $entitlement ) ) {
			return new \WP_Error(
				'permission_denied',
				sprintf(
					/* translators: %s: entitlement key. */
					__( 'This ability requires the `%s` entitlement.', '__plugin_txtd' ),
					$entitlement
				),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Whether the site holds an entitlement.
	 *
	 * @param string $entitlement Entitlement key.
	 *
	 * @return bool
	 */
	protected function has_entitlement( string $entitlement ): bool {
		return (bool) apply_filters( self::ENTITLEMENT_FILTER, false, $entitlement );
	}

	/*
	|--------------------------------------------------------------------------
	| Descriptors
	|--------------------------------------------------------------------------
	*/

	/**
	 * Every ability this plugin owns, as data.
	 *
	 * Public so the annotation and privacy rules can be asserted as data rather than
	 * inferred from a registry double.
	 *
	 * @since 2.5.3
	 *
	 * @return array[]
	 */
	public function descriptors(): array {
		return [
			[
				'name'              => 'pixelgrade/get-design-system',
				'label'             => __( 'Get design system', '__plugin_txtd' ),
				'description'       => __( 'Read the site\'s resolved design system — the representative color ramp, the typography roles with their real families and sizes, and the spacing scale — as one normalized snapshot with a `revision` hash. Reach for this first when you need to know what the site currently looks like before proposing or making a change; it is the same payload the Style Manager design-system preview endpoint serves. Read-only: it writes nothing. A subsystem that cannot be resolved comes back as null rather than failing the whole snapshot, so check for null sections before relying on them. For the raw setting ids behind these values use get-design-settings instead.', '__plugin_txtd' ),
				'annotations'       => [
					'readonly'   => true,
					'destructive' => false,
					'idempotent' => true,
				],
				'input_schema'      => $this->empty_input_schema(),
				'output_schema'     => $this->envelope_schema( $this->permissive_object_schema() ),
				'execute_callback'  => function ( array $input = [] ) {
					unset( $input );

					return $this->run( $this->agent_commands->design_system_preview() );
				},
			],
			[
				'name'             => 'pixelgrade/get-design-settings',
				'label'            => __( 'Get design settings', '__plugin_txtd' ),
				'description'      => __( 'Read Style Manager design settings by id, by Customizer section, or all of them, resolved exactly the way the Customizer resolves them (standalone option row, aggregated option array, or theme_mod) — never a raw option read, which is wrong for most ids. Use it to inspect current values before a write, or to confirm a write landed. Unknown or capability-denied ids fail the WHOLE call with `invalid_params` and list the offenders in `data.unknown`: there is no partial read, so an empty result always means "nothing matched", never "some ids were quietly dropped". Set `details: true` to also get each setting\'s transport, type and connected fields. Read-only.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'ids'     => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( 'Setting ids to read. Leave empty and pass `all` or `section` instead.', '__plugin_txtd' ),
							'default'     => [],
						],
						'all'     => [
							'type'        => 'boolean',
							'description' => __( 'Return every setting the current user can read.', '__plugin_txtd' ),
							'default'     => false,
						],
						'section' => [
							'type'        => [ 'string', 'null' ],
							'description' => __( 'Restrict to the settings attached to this Customizer section\'s controls.', '__plugin_txtd' ),
							'default'     => null,
						],
						'details' => [
							'type'        => 'boolean',
							'description' => __( 'Return the full settings data (value, transport, dirty, type, connected_fields) instead of an id => value map.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema(
					[
						'type'                 => 'object',
						'properties'           => [
							'details'  => [ 'type' => 'boolean' ],
							'settings' => $this->permissive_object_schema(),
						],
						'additionalProperties' => true,
					]
				),
				'execute_callback' => function ( array $input ) {
					$section = $input['section'] ?? null;

					return $this->run(
						$this->agent_commands->get_settings(
							array_map( 'strval', (array) ( $input['ids'] ?? [] ) ),
							! empty( $input['all'] ),
							is_string( $section ) && '' !== $section ? $section : null,
							! empty( $input['details'] )
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/get-design-structure',
				'label'            => __( 'Get design structure', '__plugin_txtd' ),
				'description'      => __( 'Describe the Style Manager Customizer surface: its panels, its sections, and the controls in each section with their ids and types. Use it to discover which setting ids exist and where they live before calling get-design-settings or set-design-settings. `with_html` is off by default and should stay off — the rendered control markup is heavy and tells a model nothing the control id does not. Naming an unknown section is `invalid_params`. Read-only.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'section'   => [
							'type'        => [ 'string', 'null' ],
							'description' => __( 'Only describe this section.', '__plugin_txtd' ),
							'default'     => null,
						],
						'with_html' => [
							'type'        => 'boolean',
							'description' => __( 'Include the rendered control markup. Heavy; leave off unless you are rendering controls.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema(
					[
						'type'                 => 'object',
						'properties'           => [
							'panels'   => $this->permissive_object_schema(),
							'sections' => [
								'type'  => 'array',
								'items' => $this->permissive_object_schema(),
							],
						],
						'additionalProperties' => true,
					]
				),
				'execute_callback' => function ( array $input ) {
					$section = $input['section'] ?? null;

					return $this->run(
						$this->agent_commands->get_structure(
							is_string( $section ) && '' !== $section ? $section : null,
							! empty( $input['with_html'] )
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/export-design-system',
				'label'            => __( 'Export design system', '__plugin_txtd' ),
				'description'      => __( 'Return the whole design system as a stamped, re-importable payload: `{meta: {plugin_version, theme, theme_version, exported_at}, settings: {id: value}}`. Feed that `settings` object straight back to set-design-settings to restore it — that is the entire import story, there is no separate import verb. Scope defaults to the Style Manager surface (the `sm_*` ids plus the theme\'s connected fields), so restoring a design system never silently rewrites core WordPress settings like the site title; pass `all: true` only if you truly want the full Customizer map. Values are reported exactly as stored. This ability NEVER writes a file — the payload comes back in `data`. Read-only.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'include' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => __( 'Narrow to these setting ids. An unknown id fails the whole call.', '__plugin_txtd' ),
							'default'     => [],
						],
						'all'     => [
							'type'        => 'boolean',
							'description' => __( 'Export the full Customizer settings map instead of the Style Manager surface.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema(
					[
						'type'                 => 'object',
						'properties'           => [
							'meta'     => $this->permissive_object_schema(),
							'settings' => $this->permissive_object_schema(),
							'scope'    => [ 'type' => 'string' ],
						],
						'additionalProperties' => true,
					]
				),
				'execute_callback' => function ( array $input ) {
					$include = (array) ( $input['include'] ?? [] );

					return $this->run(
						$this->agent_commands->export(
							empty( $include ) ? null : array_map( 'strval', $include ),
							! empty( $input['all'] )
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/set-design-settings',
				'label'            => __( 'Set design settings', '__plugin_txtd' ),
				'description'      => __( 'Write Style Manager design settings through the gated write path, then re-read them and report the diff. One call is exactly ONE Customizer publish, so batch everything you want to change into a single call — a second write in the same request fails. Values are typed JSON: numbers are numbers, objects are objects. A payload carrying BOTH a master font slot (sm_font_primary/secondary/body/accent, sm_font_palette) and a connected per-element font field is rejected with `ordering_conflict` and must be split into two calls, because the slot regenerates the whole per-element defaults table and would clobber the field you wrote alongside it. A slot-carrying payload also requires `confirm: true` for that same reason. Ids locked behind Pixelgrade Plus are reported in `stripped[]` with `reason: "plus_locked"` — never silently dropped — and a non-empty `stripped[]` means the call COMPLETED with findings you must inspect, not that it failed. Re-running an identical write reports every id as `unchanged` with `code: "noop"`. Use `dry_run: true` to see the predicted diff without writing; a dry run never needs `confirm`.', '__plugin_txtd' ),
				'annotations'      => [
					// §4 pins destructive:false. The slot caveat is behavioral (and disclosed
					// above plus enforced by the confirm rule), not an annotation change.
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'settings' => [
							'type'                 => 'object',
							'description'          => __( 'The id => value map to write, with real typed values.', '__plugin_txtd' ),
							'additionalProperties' => true,
						],
						'dry_run'  => [
							'type'        => 'boolean',
							'description' => __( 'Report the predicted diff without writing.', '__plugin_txtd' ),
							'default'     => false,
						],
						'confirm'  => [
							'type'        => 'boolean',
							'description' => __( 'Required when the payload carries a master font slot, unless dry_run.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'required'             => [ 'settings' ],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema( $this->write_data_schema(), true ),
				'execute_callback' => function ( array $input ) {
					return $this->run(
						$this->agent_commands->set_settings(
							(array) ( $input['settings'] ?? [] ),
							! empty( $input['dry_run'] ),
							$this->confirmation_gate( $input )
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/apply-font-palette',
				'label'            => __( 'Apply font palette', '__plugin_txtd' ),
				'description'      => __( 'Apply a Style Manager font palette and fan it out to every connected per-element font field, reporting the fields the fan-out rewrote in `data.connected_fields`. Destructive: it regenerates the per-element font defaults table and clobbers per-element overrides, so it needs `confirm: true` (or `dry_run: true` to preview). The id is validated against the control catalog, which lists free AND pro palettes on purpose: a pro pick on a site without the Pixelgrade Plus entitlement is not rejected up front, it is applied and then reported as `stripped[].reason: "tier_locked_palette"` with `code: "plus_stripped"` — the honest answer to "why did nothing change". A non-empty `stripped[]` means the call COMPLETED with findings you must inspect, not that it failed. An id that is in no catalog is `invalid_params`. Note that the default value `system` is NOT in the catalog, so returning a site to it is a set-design-settings write of `sm_font_palette`, not a palette apply. One call is one Customizer publish.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'palette_id' => [
							'type'        => 'string',
							'description' => __( 'The font palette id, e.g. `julia`.', '__plugin_txtd' ),
						],
						'dry_run'    => [
							'type'        => 'boolean',
							'description' => __( 'Report the predicted diff without writing.', '__plugin_txtd' ),
							'default'     => false,
						],
						'confirm'    => [
							'type'        => 'boolean',
							'description' => __( 'Required for a real apply; not needed under dry_run.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'required'             => [ 'palette_id' ],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema(
					$this->write_data_schema(
						[
							'palette'          => [ 'type' => 'string' ],
							'connected_fields' => [
								'type'  => 'array',
								'items' => [ 'type' => 'string' ],
							],
						]
					),
					true
				),
				'execute_callback' => function ( array $input ) {
					return $this->run(
						$this->agent_commands->apply_font_palette(
							(string) ( $input['palette_id'] ?? '' ),
							! empty( $input['dry_run'] ),
							$this->confirmation_gate( $input )
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/apply-color-palette',
				'label'            => __( 'Apply color palette', '__plugin_txtd' ),
				'description'      => __( 'Regenerate the Color System palette from an INLINE palette source (a JSON array of color groups passed in `source` — there is no file or stdin path here) and persist source, output and the custom-palette marker in ONE Customizer publish. Destructive: it replaces a derived ramp it cannot restore, including any hand-authored palette output the site carries, so it needs `confirm: true`; `dry_run: true` previews without writing anything and `data.diff.stored_generator_produced: false` warns you the blob about to be replaced was hand-authored. `data.grades` is counted off the ramp actually produced, which can be 11 where the option says 12. Three rules matter: (1) `variation` is written ONLY when you supply it, because sm_site_color_variation is Plus-gated and sending it on an unentitled site makes the gate strip the palette output too — omit it unless you mean to change it; (2) `generator: "none"` skips the Node generator and applies the pre-generated `output` you supply verbatim, and REQUIRES `output` — without it the call is `invalid_params`; (3) any source-based apply records the palette as custom, there is no flag to forget. With `generator: "node"` (the default) the freshly generated palettes come back inline in `data.output`. The result carries `persisted`/`unchanged`/`stripped`, and a non-empty `stripped[]` means the call COMPLETED with findings you must inspect, not that it failed. If the bundled generator or a Node binary is missing the call fails with `generator_unavailable` and writes nothing, rather than persisting a stale ramp.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				],
				'input_schema'     => [
					'type'                 => 'object',
					'properties'           => [
						'source'    => [
							'type'        => [ 'array', 'object' ],
							'description' => __( 'The palette source, inline: the `sm_advanced_palette_source` structure of color groups.', '__plugin_txtd' ),
						],
						'output'    => [
							'type'        => [ 'array', 'object', 'null' ],
							'description' => __( 'A pre-generated palette output. Required with generator "none", where it is applied verbatim; ignored with generator "node".', '__plugin_txtd' ),
							'default'     => null,
						],
						'variation' => [
							'type'        => [ 'integer', 'null' ],
							'description' => __( 'Set sm_site_color_variation (a whole number from 1 to 12) and generate against it. Omit to keep the stored value — this setting is Plus-gated and sending it on an unentitled site strips the palette output too.', '__plugin_txtd' ),
							'default'     => null,
						],
						'generator' => [
							'type'        => 'string',
							'description' => __( 'Either `node` (runs the bundled generator against the source) or `none` (applies the supplied `output` verbatim).', '__plugin_txtd' ),
							'default'     => 'node',
						],
						'dry_run'   => [
							'type'        => 'boolean',
							'description' => __( 'Report the predicted diff without writing.', '__plugin_txtd' ),
							'default'     => false,
						],
						'confirm'   => [
							'type'        => 'boolean',
							'description' => __( 'Required for a real apply; not needed under dry_run.', '__plugin_txtd' ),
							'default'     => false,
						],
					],
					'required'             => [ 'source' ],
					'additionalProperties' => false,
				],
				'output_schema'    => $this->envelope_schema(
					$this->write_data_schema(
						[
							'grades'    => [ 'type' => 'integer' ],
							'palettes'  => [ 'type' => 'integer' ],
							'generator' => [ 'type' => 'string' ],
							'verbatim'  => [ 'type' => 'boolean' ],
							'diff'      => $this->permissive_object_schema(),
							'options'   => $this->permissive_object_schema(),
							'output'    => [ 'type' => 'array' ],
						]
					),
					true
				),
				'execute_callback' => function ( array $input ) {
					return $this->run(
						$this->agent_commands->apply_color_palette(
							[
								'source'         => wp_json_encode( $input['source'] ?? null ),
								'generator'      => $input['generator'] ?? 'node',
								'variation'      => $input['variation'] ?? null,
								'dry_run'        => ! empty( $input['dry_run'] ),
								'confirm'        => $this->confirmation_gate( $input ),
								// No filesystem: a pre-generated output arrives inline, and a
								// generated one goes straight back into `data.output`.
								'resolve_output' => static function ( string $mode ) use ( $input ): array {
									$has_output = array_key_exists( 'output', $input ) && null !== $input['output'];

									return [
										'raw'  => ( 'none' === $mode && $has_output ) ? (string) wp_json_encode( $input['output'] ) : null,
										'echo' => 'node' === $mode,
										'file' => '',
									];
								},
							]
						)
					);
				},
			],
			[
				'name'             => 'pixelgrade/flush-design-cache',
				'label'            => __( 'Flush design cache', '__plugin_txtd' ),
				'description'      => __( 'Invalidate Style Manager\'s cached Customizer config, option details and option-name map so they are rebuilt on the next request. Reach for it when setting or section definitions changed in code and the design surface still reports the old shape — not as a routine step after a write, which already flushes what it needs. It touches caches only: no option, post or theme_mod a reader or visitor can observe changes, which is why it is annotated read-only.', '__plugin_txtd' ),
				'annotations'      => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'input_schema'     => $this->empty_input_schema(),
				'output_schema'    => $this->envelope_schema( $this->permissive_object_schema() ),
				'execute_callback' => function ( array $input = [] ) {
					unset( $input );

					return $this->run( $this->agent_commands->flush_cache() );
				},
			],
		];
	}

	/*
	|--------------------------------------------------------------------------
	| Envelope mapping
	|--------------------------------------------------------------------------
	*/

	/**
	 * Map a core result onto the Abilities return channel.
	 *
	 * `ok:true` (the command's machinery completed — exit 0 or 2) returns the §2 envelope
	 * whole, so a caller keeps `code`, the closed token it must branch on to notice an
	 * exit-2 finding. Everything else becomes a `WP_Error` carrying the same code, the
	 * same summary, and the payload under `data` — the nova-blocks idiom (map everything
	 * NOT 0/2 to `WP_Error`), adopted so this file cannot invert a denial into a success
	 * by singling out exit 1 alone. SM cores structurally never emit exit 3 today (there
	 * is no ruling that produces it), so this is defensive: a future or malformed core
	 * result can no longer fall through the "not exactly 1" gap and ship as `ok:true`.
	 *
	 * @param array $core An `AgentCommands` result.
	 *
	 * @return array|\WP_Error
	 */
	protected function run( array $core ) {
		$code    = (string) $core['code'];
		$summary = (string) $core['summary'];
		$exit    = (int) $core['exit'];

		if ( 0 !== $exit && 2 !== $exit ) {
			if ( 'confirmation_required' === $code ) {
				$summary .= ' ' . __( 'Repeat the call with confirm: true, or with dry_run: true to preview it first.', '__plugin_txtd' );
			}

			return new \WP_Error(
				$code,
				$summary,
				[
					'data'     => $core['data'],
					'warnings' => $core['warnings'],
				]
			);
		}

		$envelope = [
			'ok'       => true,
			'code'     => $code,
			'summary'  => $summary,
			'data'     => $core['data'],
			'warnings' => $core['warnings'],
		];

		if ( is_array( $core['write'] ?? null ) ) {
			$envelope['persisted'] = (array) ( $core['write']['persisted'] ?? [] );
			$envelope['unchanged'] = (array) ( $core['write']['unchanged'] ?? [] );
			$envelope['stripped']  = (array) ( $core['write']['stripped'] ?? [] );
		}

		return $envelope;
	}

	/**
	 * Contract §3.6 as an ability sees it.
	 *
	 * §3.6 binds confirmation to the output format, and under `--format=json` — the machine
	 * path an ability *is* — `--yes` is strictly required. So the ability mirrors that
	 * exactly: without `confirm: true` the core returns `confirmation_required`, which
	 * `run()` turns into a `WP_Error`. A dry run never reaches the gate.
	 *
	 * @param array $input Validated ability input.
	 *
	 * @return callable
	 */
	protected function confirmation_gate( array $input ): callable {
		$confirmed = ! empty( $input['confirm'] );

		return static function ( string $question ) use ( $confirmed ): bool {
			unset( $question );

			return $confirmed;
		};
	}

	/*
	|--------------------------------------------------------------------------
	| Schema helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * The schema for an ability that takes nothing.
	 *
	 * Always declared, never omitted: `execute_callback` receives the input array only when
	 * `input_schema` is non-empty, so an omitted schema would silently change the callback
	 * signature.
	 *
	 * @return array
	 */
	protected function empty_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
		];
	}

	/**
	 * An object we do not own the interior of.
	 *
	 * Deliberately permissive: a strict schema over a payload this plugin's other lanes
	 * shape would reject legitimate results as `ability_invalid_output`.
	 *
	 * @return array
	 */
	protected function permissive_object_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => true,
		];
	}

	/**
	 * The `data` schema every write shares, plus whatever the verb adds.
	 *
	 * @param array $extra Extra properties.
	 *
	 * @return array
	 */
	protected function write_data_schema( array $extra = [] ): array {
		return [
			'type'                 => 'object',
			'properties'           => array_merge(
				[
					'dry_run'   => [ 'type' => 'boolean' ],
					'requested' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'saved'     => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
				],
				$extra
			),
			'additionalProperties' => true,
		];
	}

	/**
	 * The §2 envelope as an output schema.
	 *
	 * §4 asks for "the envelope's `data` object … plus `warnings`/`stripped`"; declaring the
	 * whole envelope is a strict superset that additionally preserves `code` — the token an
	 * agent branches on to notice an exit-2 finding — and avoids colliding `data`'s own keys
	 * with `warnings`/`stripped` at the top level.
	 *
	 * @param array $data_schema The command's pinned `data` schema.
	 * @param bool  $write       Whether to declare the write keys.
	 *
	 * @return array
	 */
	protected function envelope_schema( array $data_schema, bool $write = false ): array {
		$properties = [
			'ok'       => [ 'type' => 'boolean' ],
			'code'     => [ 'type' => 'string' ],
			'summary'  => [ 'type' => 'string' ],
			'data'     => $data_schema,
			'warnings' => [
				'type'  => 'array',
				'items' => [
					'type'                 => 'object',
					'properties'           => [
						'code'    => [ 'type' => 'string' ],
						'message' => [ 'type' => 'string' ],
						'ids'     => [
							'type'  => 'array',
							'items' => [ 'type' => 'string' ],
						],
					],
					'additionalProperties' => true,
				],
			],
		];

		if ( $write ) {
			$properties['persisted'] = $this->permissive_object_schema();
			$properties['unchanged'] = [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			];
			$properties['stripped']  = [
				'type'  => 'array',
				'items' => $this->permissive_object_schema(),
			];
		}

		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => true,
		];
	}
}
