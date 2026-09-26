import test from 'node:test';
import assert from 'node:assert/strict';

import { resolveRangeDisplayValue } from '../../src/_js/utils/range-display-value.js';

// pixelgrade/style-manager#215: the Site Editor's NativeRange (native-controls.js)
// passed `value={ undefined }` to RangeControl for an untouched "empty sentinel"
// setting (sm_rail_scale, sm_rail_small, sm_rail_pitch all default to ''). The
// slider's own native <input type="range"> then falls back to the browser's
// HTML5 default-value algorithm ((min+max)/2, snapped to step) and visibly
// shows a thumb position, while the paired number field stayed bound to the
// raw `undefined` prop and rendered empty. resolveRangeDisplayValue() computes
// the SAME number for both the slider and the number field, so an untouched
// control always shows one consistent value (or a real saved value, verbatim).

test( 'an empty-sentinel value resolves to the (min+max)/2 midpoint, snapped to step', () => {
  // Rail Base (Small) / Small Rail: min 100, max 420, step 1.
  assert.equal( resolveRangeDisplayValue( '', 100, 420, 1 ), 260 );
} );

test( 'undefined resolves the same way as an empty string', () => {
  // Rail Pitch: min 0, max 45, step 1 -> 22.5 snaps up to 23 (matches the
  // browser's own native <input type="range"> default-value rendering).
  assert.equal( resolveRangeDisplayValue( undefined, 0, 45, 1 ), 23 );
} );

test( 'null resolves the same way as an empty string', () => {
  assert.equal( resolveRangeDisplayValue( null, 100, 420, 1 ), 260 );
} );

test( 'a fractional step snaps the midpoint to the nearest step', () => {
  // Rail Gap: min 1, max 5, step 0.25.
  assert.equal( resolveRangeDisplayValue( '', 1, 5, 0.25 ), 3 );
} );

test( 'a negative-to-positive range midpoints at zero', () => {
  assert.equal( resolveRangeDisplayValue( '', -50, 50, 1 ), 0 );
} );

test( 'a real saved string value passes through as a number, untouched', () => {
  assert.equal( resolveRangeDisplayValue( '300', 100, 420, 1 ), 300 );
} );

test( 'a real saved numeric value passes through unchanged', () => {
  assert.equal( resolveRangeDisplayValue( 180, 100, 420, 1 ), 180 );
} );

test( 'a real saved value of 0 is never treated as the empty sentinel', () => {
  assert.equal( resolveRangeDisplayValue( 0, 0, 1, 1 ), 0 );
} );

test( 'a real saved value of "0" (string) is never treated as the empty sentinel', () => {
  assert.equal( resolveRangeDisplayValue( '0', -1, 1, 1 ), 0 );
} );

// style-manager#215 (follow-up): the (min+max)/2 midpoint above advertises a
// width nothing actually applies (e.g. 260 for "Rail Base (Small)", when the
// frontend renders 230 or a saved Small-only value). PHP derives the REAL
// effective value from the same contract as style_manager_rail_widths() and
// passes it through `input_attrs['data-effective-default']`; when present it
// must win over the generic midpoint, for the slider AND the number field
// alike (they share this single resolved value).

test( 'a PHP-provided effective default wins over the (min+max)/2 midpoint', () => {
  // "Rail Base (Small)" with nothing saved: the frontend renders 230, not
  // the 260 midpoint.
  assert.equal( resolveRangeDisplayValue( '', 100, 420, 1, 230 ), 230 );
} );

test( 'the effective default applies the same way to undefined and null', () => {
  assert.equal( resolveRangeDisplayValue( undefined, 100, 420, 1, 230 ), 230 );
  assert.equal( resolveRangeDisplayValue( null, 100, 420, 1, 230 ), 230 );
} );

test( 'an effective default of 0 (Rail Pitch\'s documented Flat placeholder) is honoured, not treated as absent', () => {
  assert.equal( resolveRangeDisplayValue( '', 0, 45, 1, 0 ), 0 );
} );

test( 'without an effective default, the (min+max)/2 midpoint is still the fallback', () => {
  assert.equal( resolveRangeDisplayValue( '', 100, 420, 1, undefined ), 260 );
  assert.equal( resolveRangeDisplayValue( '', 100, 420, 1, null ), 260 );
} );

test( 'a real saved value ignores the effective default entirely', () => {
  assert.equal( resolveRangeDisplayValue( '342', 100, 420, 1, 230 ), 342 );
} );
