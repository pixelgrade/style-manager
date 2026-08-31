<?php
/**
 * The single Style Manager settings write path.
 *
 * Every caller that persists Style Manager settings outside the Customizer pane —
 * the Site Editor REST endpoint, WP-CLI, an ability — goes through this collaborator,
 * so the Pixelgrade Plus save gate, the post-save fan-out and the
 * `style_manager/settings_saved` signal can never be bypassed.
 *
 * @since   2.6.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\FontPalettes;

/**
 * Style Manager settings writer.
 *
 * §3.4 write policy lives here — `master_font_slot_ids()`, `find_ordering_conflict()` and
 * `apply_letter_spacing_policy()` — so the CLI, the REST endpoint and W7's abilities read the
 * font-order law from one place instead of each re-deriving it.
 *
 * Two of the three are enforced inside `save()`; the ordering-conflict check is **offered, not
 * enforced**, and that asymmetry is deliberate. The letter-spacing policy can only ever fix a
 * value or drop a single id, so every caller can inherit it safely. An ordering conflict, by
 * contrast, rejects the *whole* write — and the Site Editor legitimately produces that payload:
 * `pushEdits()` (src/_js/site-editor/index.js) PUTs the entire dirty set, so a user who picks a
 * font palette and also nudges one per-element font in the same session would have their save
 * refused. §3.4 scopes the law to "a single `set` invocation", so `Provider\CliCommands` (and any
 * future ability) calls `find_ordering_conflict()` explicitly; `save()` does not.
 *
 * @since 2.6.0
 */
class SettingsWriter {

	/**
	 * The generated palette output setting produced by the Color System builder.
	 */
	public const PALETTE_OUTPUT_SETTING_ID = 'sm_advanced_palette_output';

	/**
	 * Free Color System settings that justify saving a generated palette output.
	 */
	public const FREE_PALETTE_SETTING_IDS = [
		'sm_advanced_palette_source',
		ColorPalettes::SM_COLOR_PALETTE_OPTION_KEY,
		ColorPalettes::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY,
	];

	/**
	 * Closed `stripped[].reason` vocabulary (agent-surface contract §2).
	 */
	public const REASON_PLUS_LOCKED         = 'plus_locked';
	public const REASON_UNKNOWN_SETTING     = 'unknown_setting';
	public const REASON_INVALID_VALUE       = 'invalid_value';
	public const REASON_TIER_LOCKED_PALETTE = 'tier_locked_palette';

	/**
	 * The only letter-spacing unit the type system emits (§3.4). Every other unit —
	 * missing, `false`, or anything else — normalizes to this on write.
	 */
	public const LETTER_SPACING_UNIT = 'em';

	/**
	 * Headless Customizer.
	 *
	 * @var HeadlessCustomizer
	 */
	protected HeadlessCustomizer $headless_customizer;

	/**
	 * Font palettes.
	 *
	 * @var FontPalettes
	 */
	protected FontPalettes $font_palettes;

	/**
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param FontPalettes       $font_palettes       Font palettes.
	 */
	public function __construct(
		HeadlessCustomizer $headless_customizer,
		FontPalettes $font_palettes
	) {
		$this->headless_customizer = $headless_customizer;
		$this->font_palettes       = $font_palettes;
	}

	/**
	 * Persist a map of setting_id => value through the gate, the changeset and the fan-out.
	 *
	 * The Plus gate runs first. When it removes *every* requested id we deliberately do NOT
	 * call HeadlessCustomizer::save() — an empty map there returns a
	 * `style_manager_site_editor_nothing_to_save` WP_Error, which would report a gated write as
	 * a failure. Instead we return a successful result with an empty `saved` and a full
	 * `stripped` list, and let the caller decide how to surface it.
	 *
	 * @since 2.6.0
	 *
	 * @param array $values       setting_id => raw JS value.
	 * @param bool  $capture_diff Whether to snapshot before/after values and report
	 *                            `persisted` / `unchanged`. Off by default so the REST
	 *                            path keeps its existing read profile.
	 *
	 * @return array|\WP_Error {
	 *     @type string[] $saved              Setting IDs accepted for save.
	 *     @type string[] $skipped            Setting IDs unknown or not allowed.
	 *     @type array    $setting_validities Validity report from the changeset publish.
	 *     @type array[]  $stripped           [ id, reason, requested, current? ] per dropped id.
	 *     @type string[] $connected_fields   Connected field ids the post-save fan-out rewrote.
	 *     @type array    $persisted          Only with $capture_diff: id => on-disk value after the write.
	 *     @type string[] $unchanged          Only with $capture_diff: requested ids whose value did not move.
	 * }
	 */
	public function save( array $values, bool $capture_diff = false ) {
		$requested = $values;

		$gated  = $this->strip_locked_premium_settings( $values );
		$gated  = $this->strip_locked_premium_font_palette( $gated );

		$stripped = $this->map_gate_strips( $requested, $gated );

		// §3.4: normalize zero-valued unitless letter-spacings, strip the nonzero
		// ones that carry no usable unit. Runs after the gate so a premium id is
		// reported with the more actionable `plus_locked` reason.
		[ $gated, $letter_spacing_strips ] = $this->apply_letter_spacing_policy( $gated );
		$stripped                          = array_merge( $stripped, $letter_spacing_strips );

		$before = [];
		if ( $capture_diff ) {
			$before = $this->read_values( array_keys( $requested ) );
		}

		// Everything the caller asked for was gated away. Not an error — a finding.
		if ( empty( $gated ) ) {
			return $this->finish(
				[
					'saved'              => [],
					'skipped'            => [],
					'setting_validities' => [],
					'stripped'           => $stripped,
					'connected_fields'   => [],
				],
				$requested,
				$before,
				$capture_diff
			);
		}

		$result = $this->headless_customizer->save( $gated );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$stripped = array_merge( $stripped, $this->map_save_strips( $requested, $result ) );

		$connected_fields = $this->apply_post_save_side_effects( $result['saved'] );

		/**
		 * Fires after Style Manager settings have been successfully saved.
		 *
		 * Mirrors the Customizer's `customize_save_after` for consumers that need a
		 * "settings were just saved" signal outside the Customizer (e.g. local font
		 * mirroring, see Provider\LocalFonts). Fires exactly once per successful save,
		 * from whichever caller performed it (REST, WP-CLI, an ability).
		 *
		 * @since 2.4.0
		 *
		 * @param string[] $saved_setting_ids Setting IDs that were saved.
		 */
		do_action( 'style_manager/settings_saved', $result['saved'] );

		return $this->finish(
			[
				'saved'              => (array) ( $result['saved'] ?? [] ),
				'skipped'            => (array) ( $result['skipped'] ?? [] ),
				'setting_validities' => (array) ( $result['setting_validities'] ?? [] ),
				'stripped'           => $stripped,
				'connected_fields'   => $connected_fields,
			],
			$requested,
			$before,
			$capture_diff
		);
	}

	/**
	 * Predict what save() would do, without writing anything.
	 *
	 * Backs `--dry-run` (contract §3.6): same gate, same reason vocabulary, same
	 * exit-2-on-findings rule — only the changeset publish and the fan-out are skipped.
	 *
	 * @since 2.6.0
	 *
	 * Note on `saved`: a dry run reports **requested ids only**. A real `save()` can also
	 * list gate-injected derivatives the caller never asked for (e.g. the server-rebuilt
	 * `sm_font_sizing_baseline_v1` on a locked site), because those genuinely reach the
	 * changeset. That asymmetry is intentional, not drift.
	 *
	 * @param array $values setting_id => raw JS value.
	 *
	 * @return array Same shape as save(), plus `dry_run => true`.
	 */
	public function preview( array $values ): array {
		$gated    = $this->strip_locked_premium_settings( $values );
		$gated    = $this->strip_locked_premium_font_palette( $gated );
		$stripped = $this->map_gate_strips( $values, $gated );

		[ $gated, $letter_spacing_strips ] = $this->apply_letter_spacing_policy( $gated );
		$stripped                          = array_merge( $stripped, $letter_spacing_strips );

		// get_settings_values() already honours check_capabilities(), so an id that is
		// missing here is either unregistered or capability-denied — `unknown_setting`.
		$known     = $this->headless_customizer->get_settings_values();
		$persisted = [];
		$unchanged = [];

		foreach ( $gated as $setting_id => $value ) {
			$setting_id = (string) $setting_id;

			// The gate can inject a server-rebuilt derivative the caller never asked for.
			if ( ! array_key_exists( $setting_id, $values ) ) {
				continue;
			}

			if ( ! array_key_exists( $setting_id, $known ) ) {
				$stripped[] = [
					'id'        => $setting_id,
					'reason'    => self::REASON_UNKNOWN_SETTING,
					'requested' => $value,
				];
				continue;
			}

			$persisted[ $setting_id ] = $value;

			if ( $this->values_match( $known[ $setting_id ], $value ) ) {
				$unchanged[] = $setting_id;
			}
		}

		foreach ( $stripped as $index => $entry ) {
			$stripped[ $index ]['current'] = $known[ $entry['id'] ] ?? null;
		}

		return [
			'saved'              => array_keys( $persisted ),
			'skipped'            => [],
			'setting_validities' => [],
			'stripped'           => $stripped,
			'connected_fields'   => [],
			'persisted'          => $persisted,
			'unchanged'          => array_values( array_unique( $unchanged ) ),
			'dry_run'            => true,
		];
	}

	/**
	 * Attach the mandatory read-back diff (contract §3.5) when it was asked for.
	 *
	 * The read happens after HeadlessCustomizer::save() has already invalidated the
	 * caches, so a stale details cache can never manufacture a false "persisted".
	 *
	 * @param array $result       The result being assembled.
	 * @param array $requested    The originally requested id => value map.
	 * @param array $before       Pre-write values keyed by id.
	 * @param bool  $capture_diff Whether the diff was requested.
	 *
	 * @return array
	 */
	protected function finish( array $result, array $requested, array $before, bool $capture_diff ): array {
		if ( ! $capture_diff ) {
			return $result;
		}

		$after     = $this->read_values( array_keys( $requested ) );
		$persisted = [];
		$unchanged = [];

		/*
		 * `persisted`, `unchanged` and `stripped` are DISJOINT. A stripped id was never
		 * written, so "before == after" is trivially true for it — reporting it as
		 * `unchanged` would tell an agent "already applied" about a value the gate
		 * refused. An id can also appear in both `saved` and `stripped`
		 * (HeadlessCustomizer::save() lists an id in `saved` even when its own validation
		 * later reports it invalid), so the stripped set wins over both.
		 */
		$stripped_ids = array_flip( array_column( $result['stripped'], 'id' ) );
		foreach ( (array) ( $result['skipped'] ?? [] ) as $skipped_id ) {
			$stripped_ids[ (string) $skipped_id ] = true;
		}

		foreach ( array_keys( $requested ) as $setting_id ) {
			$setting_id = (string) $setting_id;

			if ( isset( $stripped_ids[ $setting_id ] ) ) {
				continue;
			}

			if ( in_array( $setting_id, $result['saved'], true ) ) {
				$persisted[ $setting_id ] = $after[ $setting_id ] ?? null;
			}

			if ( array_key_exists( $setting_id, $after )
				&& $this->values_match( $before[ $setting_id ] ?? null, $after[ $setting_id ] ) ) {
				$unchanged[] = $setting_id;
			}
		}

		// Fill in the `current` witness on every stripped entry now that we have a read.
		foreach ( $result['stripped'] as $index => $entry ) {
			$result['stripped'][ $index ]['current'] = $after[ $entry['id'] ] ?? null;
		}

		$result['persisted'] = $persisted;
		$result['unchanged'] = array_values( array_unique( $unchanged ) );

		return $result;
	}

	/**
	 * Read the current values of the given setting ids through the three-store resolver
	 * (contract §3.1 — never `wp option get`).
	 *
	 * @param string[] $setting_ids Setting ids.
	 *
	 * @return array id => value, for the ids that exist.
	 */
	public function read_values( array $setting_ids ): array {
		$all    = $this->headless_customizer->get_settings_values();
		$values = [];

		foreach ( $setting_ids as $setting_id ) {
			$setting_id = (string) $setting_id;
			if ( array_key_exists( $setting_id, $all ) ) {
				$values[ $setting_id ] = $all[ $setting_id ];
			}
		}

		return $values;
	}

	/**
	 * Structural value comparison — PHP array key order and int/string drift must not
	 * masquerade as a change.
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 *
	 * @return bool
	 */
	protected function values_match( $a, $b ): bool {
		return $this->canonicalize( $a ) === $this->canonicalize( $b );
	}

	/**
	 * Recursively sort array keys and normalize numeric-looking scalars.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	protected function canonicalize( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $item ) {
				$out[ $key ] = $this->canonicalize( $item );
			}
			ksort( $out );

			return $out;
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			return 0 + $value;
		}

		return $value;
	}

	/**
	 * Build the `stripped[]` entries for ids the Plus gate removed.
	 *
	 * @param array $requested The requested id => value map.
	 * @param array $gated     The map that survived the gate.
	 *
	 * @return array[]
	 */
	protected function map_gate_strips( array $requested, array $gated ): array {
		$font_palette_key = FontPalettes::SM_FONT_PALETTE_OPTION_KEY;
		$stripped         = [];

		foreach ( $requested as $setting_id => $value ) {
			$setting_id = (string) $setting_id;
			if ( array_key_exists( $setting_id, $gated ) ) {
				continue;
			}

			$stripped[] = [
				'id'        => $setting_id,
				'reason'    => $font_palette_key === $setting_id
					? self::REASON_TIER_LOCKED_PALETTE
					: self::REASON_PLUS_LOCKED,
				'requested' => $value,
			];
		}

		return $stripped;
	}

	/**
	 * Build the `stripped[]` entries the changeset publish reported: unknown/denied ids
	 * and values the setting's own validation rejected.
	 *
	 * @param array $requested The requested id => value map.
	 * @param array $result    The HeadlessCustomizer::save() result.
	 *
	 * @return array[]
	 */
	protected function map_save_strips( array $requested, array $result ): array {
		$stripped = [];

		foreach ( (array) ( $result['skipped'] ?? [] ) as $setting_id ) {
			$setting_id = (string) $setting_id;

			// Only report ids the caller actually asked for. The gate can inject a
			// server-rebuilt derivative; a stripped entry for an id nobody requested
			// would be noise the caller cannot act on.
			if ( ! array_key_exists( $setting_id, $requested ) ) {
				continue;
			}

			$stripped[] = [
				'id'        => $setting_id,
				'reason'    => self::REASON_UNKNOWN_SETTING,
				'requested' => $requested[ $setting_id ] ?? null,
			];
		}

		foreach ( (array) ( $result['setting_validities'] ?? [] ) as $setting_id => $is_valid ) {
			$setting_id = (string) $setting_id;
			if ( true === $is_valid || ! array_key_exists( $setting_id, $requested ) ) {
				continue;
			}

			$stripped[] = [
				'id'        => $setting_id,
				'reason'    => self::REASON_INVALID_VALUE,
				'requested' => $requested[ $setting_id ],
			];
		}

		return $stripped;
	}

	/*
	|--------------------------------------------------------------------------
	| §3.4 write policy — shared so the CLI, the REST endpoint and W7's
	| abilities all read the font-order law from one place instead of
	| re-deriving it.
	|--------------------------------------------------------------------------
	*/

	/**
	 * The master font slots. Writing any of them regenerates the **entire** per-element
	 * font defaults table, clobbering per-element overrides — which is what makes such a
	 * write destructive (§3.4/§3.6) and what the ordering law guards.
	 *
	 * @since 2.6.0
	 *
	 * @return string[]
	 */
	public static function master_font_slot_ids(): array {
		return [
			'sm_font_primary',
			'sm_font_secondary',
			'sm_font_body',
			'sm_font_accent',
			FontPalettes::SM_FONT_PALETTE_OPTION_KEY,
		];
	}

	/**
	 * Whether a payload carries a master font slot — i.e. whether it inherits destructive
	 * semantics and therefore §3.6's `--yes`.
	 *
	 * @since 2.6.0
	 *
	 * @param array $values setting_id => value map.
	 *
	 * @return string[] The master slot ids present, empty when none.
	 */
	public static function master_font_slots_in( array $values ): array {
		return array_values( array_intersect( array_map( 'strval', array_keys( $values ) ), self::master_font_slot_ids() ) );
	}

	/**
	 * The theme's connected per-element font field ids.
	 *
	 * Derived from the live Customize registry (the theme's Style Manager `fonts_section`),
	 * never from a name pattern: a `_font]`-suffix regex misses real members of the set —
	 * `anima_options[headline_lines_spacings]` on Anima LT, for one — and a missed member
	 * is exactly the clobber §3.4 exists to prevent. The suffix match is kept only as a
	 * belt-and-braces union for contexts where the registry cannot be booted.
	 *
	 * @since 2.6.0
	 *
	 * @param array $values setting_id => value map to test.
	 *
	 * @return string[] The connected per-element field ids present in $values.
	 */
	public function connected_font_field_ids_in( array $values ): array {
		$ids = array_map( 'strval', array_keys( $values ) );

		try {
			$derived = $this->headless_customizer->get_theme_font_target_setting_ids();
		} catch ( \Throwable $e ) {
			$derived = [];
		}

		$found = array_values( array_intersect( $ids, $derived ) );

		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $found, true ) && 1 === preg_match( '/\[[A-Za-z0-9_-]*_font\]$/', $id ) ) {
				$found[] = $id;
			}
		}

		return $found;
	}

	/**
	 * Detect a §3.4 ordering conflict: one write carrying **both** a master font slot and
	 * a connected per-element font field. The slot regenerates the whole defaults table,
	 * so the per-element value written in the same breath is clobbered by the fan-out.
	 *
	 * Returns the offending sets rather than raising, so each caller can surface it in its
	 * own idiom — the CLI as `code:"ordering_conflict"` exit 1, an ability as a validation
	 * error. **`save()` deliberately does not call this**: see the class docblock note.
	 *
	 * @since 2.6.0
	 *
	 * @param array $values setting_id => value map.
	 *
	 * @return array|null { master_slots: string[], per_element_fields: string[] } or null when clean.
	 */
	public function find_ordering_conflict( array $values ): ?array {
		$slots = self::master_font_slots_in( $values );
		if ( empty( $slots ) ) {
			return null;
		}

		$per_element = $this->connected_font_field_ids_in( $values );
		if ( empty( $per_element ) ) {
			return null;
		}

		return [
			'master_slots'       => $slots,
			'per_element_fields' => $per_element,
		];
	}

	/**
	 * Apply §3.4's letter-spacing rule to a value map.
	 *
	 * A unitless letter-spacing emits a CSS var that computes `letter-spacing: normal`
	 * silently (laws #7), so the unit must be repaired. Rejecting instead of repairing does
	 * not work: the browser-authored state this stack is meant to reproduce carries unitless
	 * letter-spacing at *any* value — the P1-a grist fixture has `{value: -0.04, unit: false}`
	 * on `super_display_font` and the same shape on 6 more of its 17 roles, and Anima LT's
	 * own shipped defaults use `{value: 0, unit: false}`. Rejecting those would make both the
	 * reference fixture and the plugin's own state un-round-trippable. So (v0.3.6):
	 *
	 * - **Unit normalization is unconditional.** Missing, `false` or otherwise invalid unit
	 *   becomes `'em'` at **any** value — repaired on write, never stripped, never rejected.
	 *   `em` is the only unit the type system emits, so this is render-identical to what the
	 *   browser produced.
	 * - **A non-numeric `value`** is the one real error: nothing sensible can be written, so
	 *   the setting is stripped with `reason:"invalid_value"` (exit 2 at the CLI).
	 *
	 * `export` does not run this — it reports what is on disk (§3.4).
	 *
	 * @since 2.6.0
	 *
	 * @param array $values setting_id => value map.
	 *
	 * @return array{0: array, 1: array[]} The normalized map, and the stripped entries.
	 */
	public function apply_letter_spacing_policy( array $values ): array {
		$normalized = [];
		$stripped   = [];

		foreach ( $values as $setting_id => $value ) {
			$setting_id = (string) $setting_id;
			$invalid    = false;

			$candidate = $this->normalize_letter_spacings( $value, $invalid );

			if ( $invalid ) {
				$stripped[] = [
					'id'        => $setting_id,
					'reason'    => self::REASON_INVALID_VALUE,
					'requested' => $value,
				];
				continue;
			}

			$normalized[ $setting_id ] = $candidate;
		}

		return [ $normalized, $stripped ];
	}

	/**
	 * Recursively normalize every `letter_spacing` sub-field of a setting value.
	 *
	 * @param mixed $value   Setting value.
	 * @param bool  $invalid Set to true when a letter-spacing carries a non-numeric value.
	 *
	 * @return mixed The normalized value.
	 */
	protected function normalize_letter_spacings( $value, bool &$invalid ) {
		$was_object = is_object( $value );
		if ( $was_object ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return $was_object ? (object) $value : $value;
		}

		foreach ( $value as $key => $item ) {
			if ( 'letter_spacing' !== $key ) {
				$value[ $key ] = $this->normalize_letter_spacings( $item, $invalid );
				continue;
			}

			$letter_spacing = is_object( $item ) ? (array) $item : $item;
			if ( ! is_array( $letter_spacing ) ) {
				continue;
			}

			// The only real error: no number to write.
			if ( array_key_exists( 'value', $letter_spacing ) && ! is_numeric( $letter_spacing['value'] ) ) {
				$invalid = true;
				continue;
			}

			if ( self::LETTER_SPACING_UNIT === ( $letter_spacing['unit'] ?? null ) ) {
				continue;
			}

			// Unconditional: `em` is the only unit the type system emits, so repairing the
			// unit at any value is render-identical to the browser-authored state and keeps
			// the P1 fixtures round-trippable.
			$letter_spacing['unit'] = self::LETTER_SPACING_UNIT;
			$value[ $key ]          = is_object( $item ) ? (object) $letter_spacing : $letter_spacing;
		}

		return $was_object ? (object) $value : $value;
	}

	/**
	 * The real Plus gate: when the advanced controls are locked (no Pixelgrade Plus entitlement),
	 * strip the premium palette-structure settings from what gets persisted, keeping every other
	 * setting. Free users can fully USE the controls as a live trial — they just can't SAVE the
	 * premium settings, so trial changes revert to last-saved on reload.
	 *
	 * Server-side enforcement is intrinsic — never client-trusted. The premium id list is the single
	 * source of truth shared with the UI gating (\Pixelgrade\StyleManager\plus_gated_setting_ids()).
	 *
	 * @since 2.2.15
	 *
	 * @param array $values Setting id => value map submitted by the caller.
	 *
	 * @return array The filtered values to persist.
	 */
	public function strip_locked_premium_settings( array $values ): array {
		if ( ! \Pixelgrade\StyleManager\plus_advanced_controls_locked() ) {
			return $values;
		}

		$original_values            = $values;
		$premium_ids                = \Pixelgrade\StyleManager\plus_gated_setting_ids();
		$has_premium_setting_change = false;

		foreach ( $premium_ids as $premium_id ) {
			if ( array_key_exists( $premium_id, $values ) ) {
				$has_premium_setting_change = true;
			}
			unset( $values[ $premium_id ] );
		}

		// The public baseline is editable client state. A locked client may have
		// previewed premium fine tuning, so never persist that submitted copy.
		// Rebuild it from pre-save server state before allowing the free named
		// Font Sizing choice to persist.
		unset( $values[ FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY ] );
		if ( array_key_exists( 'sm_font_sizing', $original_values ) ) {
			$safe_baseline = $this->font_palettes->prepare_locked_font_sizing_baseline();
			if ( ! empty( $safe_baseline['scales'] ) ) {
				$values[ FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY ] = $safe_baseline;
			} else {
				unset( $values['sm_font_sizing'] );
			}
		}

		if ( array_key_exists( self::PALETTE_OUTPUT_SETTING_ID, $values )
			&& ( $has_premium_setting_change || ! $this->has_free_palette_setting_change( $original_values ) ) ) {
			unset( $values[ self::PALETTE_OUTPUT_SETTING_ID ] );
		}

		return $values;
	}

	/**
	 * Whether the submitted values carry a free Color System change that justifies
	 * persisting a regenerated palette output.
	 *
	 * @param array $values Setting id => value map.
	 *
	 * @return bool
	 */
	protected function has_free_palette_setting_change( array $values ): bool {
		foreach ( self::FREE_PALETTE_SETTING_IDS as $setting_id ) {
			if ( array_key_exists( $setting_id, $values ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The save gate for pro font palettes: the control lets free users SELECT a
	 * pro palette and watch the live preview change, but the selection must not
	 * persist. When the submitted `sm_font_palette` pointer is a tier-locked
	 * (pro) palette, drop the pointer so the pick reverts to last-saved on reload.
	 *
	 * Dropping the pointer alone is sufficient: the per-element font values a
	 * palette diffuses into are already in plus_gated_setting_ids() and stripped
	 * by strip_locked_premium_settings(), and apply_post_save_side_effects()
	 * only re-applies a palette when its pointer is in the saved ids — which it
	 * no longer is here. Intrinsic server-side enforcement, never client-trusted.
	 *
	 * @since 2.4.0
	 *
	 * @param array $values Setting id => value map submitted by the caller.
	 *
	 * @return array The filtered values to persist.
	 */
	public function strip_locked_premium_font_palette( array $values ): array {
		$key = FontPalettes::SM_FONT_PALETTE_OPTION_KEY;

		if ( ! array_key_exists( $key, $values ) ) {
			return $values;
		}

		if ( $this->font_palettes->is_palette_tier_locked( (string) $values[ $key ] ) ) {
			unset( $values[ $key ] );
		}

		return $values;
	}

	/**
	 * Applies server-side effects that the Customizer UI used to perform.
	 *
	 * @since 2.3.0
	 *
	 * @param string[] $saved_setting_ids Setting IDs that were saved.
	 *
	 * @return string[] Connected field option ids the font-palette fan-out rewrote, if any.
	 */
	public function apply_post_save_side_effects( array $saved_setting_ids ): array {
		$connected_fields = [];

		if ( in_array( FontPalettes::SM_FONT_PALETTE_OPTION_KEY, $saved_setting_ids, true ) ) {
			$connected_fields = (array) $this->font_palettes->apply_current_font_palette_to_connected_fields();
		}

		$font_sizing_saved = in_array( 'sm_font_sizing', $saved_setting_ids, true );
		$baseline_saved    = in_array( FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY, $saved_setting_ids, true );
		if ( $font_sizing_saved || $baseline_saved ) {
			if ( $font_sizing_saved && \Pixelgrade\StyleManager\plus_advanced_controls_locked() ) {
				$this->font_palettes->apply_current_font_sizing_to_connected_fields();
			} elseif ( ! \Pixelgrade\StyleManager\plus_advanced_controls_locked() ) {
				$this->font_palettes->trust_current_font_sizing_baseline();
			}
		}

		return array_values( array_filter( array_map( 'strval', $connected_fields ) ) );
	}
}
