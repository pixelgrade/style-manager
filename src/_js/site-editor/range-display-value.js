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
 * `undefined` prop and rendered blank. Computing that identical fallback here
 * and passing it to BOTH parts of the control keeps them in sync from the
 * first render, with no change to the underlying (still unset) setting.
 *
 * @since 2.5.4
 *
 * @param {string|number|null|undefined} value The setting's raw value ('' is
 *                                              the untouched sentinel).
 * @param {number}                       min   The control's minimum.
 * @param {number}                       max   The control's maximum.
 * @param {number}                       step  The control's step.
 *
 * @return {number} The number to display in both the slider and the number field.
 */
export const resolveRangeDisplayValue = ( value, min, max, step ) => {
  if ( '' === value || undefined === value || null === value ) {
    const resolvedStep = step || 1;
    const midpoint = ( min + max ) / 2;

    return Math.round( midpoint / resolvedStep ) * resolvedStep;
  }

  return Number( value );
};
