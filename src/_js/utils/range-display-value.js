/**
 * Resolve the number a range control's slider AND its paired number field
 * should both display.
 *
 * pixelgrade/style-manager#215: several Layout settings (sm_rail_small,
 * sm_rail_scale, sm_rail_pitch) default to an empty-string sentinel so their
 * consumers keep built-in fallbacks until the control is touched (see
 * LayoutSection.php). Before this fix, NativeRange (native-controls.js) fed
 * that empty sentinel to RangeControl as a literal `undefined` value: the
 * component's own native <input type="range"> then rendered the browser's
 * HTML5 default-value fallback ((min+max)/2, snapped to step — see the
 * "range state (value)" algorithm in the HTML Standard) and visibly showed a
 * thumb position, while the paired number field stayed bound to the same
 * `undefined` prop and rendered blank.
 *
 * That midpoint is itself wrong for a setting whose consumer default ISN'T
 * the middle of the slider's range (style-manager#215 follow-up): "Rail Base
 * (Small)" renders 230 (or a saved Small-only value) while unset, not the
 * midpoint 260 — showing 260 advertises a width nothing applies, and the
 * first nudge would jump from the real width to ~261. `effectiveDefault` is
 * PHP-derived from the exact same contract as style_manager_rail_widths()
 * (see LayoutSection.php's `data-effective-default` input attr) and, when
 * given, wins outright. The (min+max)/2 midpoint remains only as a last-resort
 * fallback for a hypothetical empty-sentinel control that hasn't been wired
 * with an effective default — it is never correct on its own for a setting
 * whose real fallback differs from the range's middle.
 *
 * Either way, this computes ONE number for BOTH parts of the control, so they
 * stay in sync from the first render, with no change to the underlying
 * (still unset) setting — showing the value never saves it.
 *
 * Shared by NativeRange (site-editor/native-controls.js), which passes it
 * straight to RangeControl's `value`, and the Customizer's own range+number
 * pairing (customizer/fields/range/index.js), which applies it to the
 * PHP-rendered `<input type="range">` before cloning the number field from it
 * — the Customizer's native <input type="range"> default-value fallback is
 * the same browser algorithm, so it needs the same fix.
 *
 * @since 2.5.4
 *
 * @param {string|number|null|undefined} value            The setting's raw value ('' is
 *                                                         the untouched sentinel).
 * @param {number}                       min              The control's minimum.
 * @param {number}                       max              The control's maximum.
 * @param {number}                       step             The control's step.
 * @param {number|null|undefined}        [effectiveDefault] The real, contract-derived value to
 *                                                          show while `value` is the empty
 *                                                          sentinel (PHP-provided; 0 is a valid,
 *                                                          honoured value — only null/undefined
 *                                                          mean "none provided").
 *
 * @return {number} The number to display in both the slider and the number field.
 */
export const resolveRangeDisplayValue = ( value, min, max, step, effectiveDefault ) => {
  if ( '' === value || undefined === value || null === value ) {
    if ( undefined !== effectiveDefault && null !== effectiveDefault ) {
      return Number( effectiveDefault );
    }

    const resolvedStep = step || 1;
    const midpoint = ( min + max ) / 2;

    return Math.round( midpoint / resolvedStep ) * resolvedStep;
  }

  return Number( value );
};
