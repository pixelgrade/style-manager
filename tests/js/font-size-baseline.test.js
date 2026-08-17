import assert from 'node:assert/strict';
import {
  createFontSizeBaselineEntry,
  deriveAbsoluteFontSizes,
  reconcileFontSizeBaselineEntry,
} from '../../src/_js/customizer/fonts/connected-fields/font-size-baseline.js';

export const runFontSizeBaselineTests = () => {
  const normalState = { elevation: 0, pitch: 100 };
  const smallerState = { elevation: 6, pitch: 40 };
  const flatState = { elevation: 0, pitch: 0 };

  const baseline = createFontSizeBaselineEntry( {
    currentInterval: [ 14, 80 ],
    currentSizes: { heading: 42.67, body: 18.13 },
    currentState: normalState,
    precision: 2,
  } );

  assert.deepEqual(
    deriveAbsoluteFontSizes( baseline, smallerState, 2 ).sizes,
    { heading: 29.43, body: 19.61 },
    'Smaller should derive a two-decimal view from the neutral baseline'
  );
  assert.deepEqual(
    deriveAbsoluteFontSizes( baseline, normalState, 2 ).sizes,
    { heading: 42.67, body: 18.13 },
    'Normal should restore the exact persisted neutral values after reload'
  );

  assert.deepEqual(
    deriveAbsoluteFontSizes(
      { interval: [ 14, 80 ], sizes: { heading: 42.67, inherited: false } },
      normalState,
      2
    ).sizes,
    { heading: 42.67 },
    'persisted inherit sentinels must not become zero-size baseline entries'
  );

  assert.deepEqual(
    deriveAbsoluteFontSizes( baseline, flatState, 2 ).sizes,
    { heading: 14, body: 14 },
    'Pitch zero should intentionally collapse the visible hierarchy'
  );
  assert.deepEqual(
    deriveAbsoluteFontSizes( baseline, normalState, 2 ).sizes,
    { heading: 42.67, body: 18.13 },
    'leaving Pitch zero should rebuild the hierarchy from the retained baseline'
  );

  const reconciled = reconcileFontSizeBaselineEntry(
    baseline,
    { heading: 30, body: 19.61 },
    smallerState,
    2
  );
  assert.equal( reconciled.changed, true, 'a fine-tuned derived value should update its neutral baseline' );
  assert.equal(
    deriveAbsoluteFontSizes( reconciled.entry, normalState, 2 ).sizes.heading,
    44.1,
    'fine-tuning at a derived size should survive the next absolute preset'
  );
  assert.equal(
    deriveAbsoluteFontSizes( reconciled.entry, normalState, 2 ).sizes.body,
    18.13,
    'unchanged derived values should keep their exact neutral baseline'
  );

  const inheritedReconciled = reconcileFontSizeBaselineEntry(
    { interval: [ 14, 80 ], sizes: { heading: 42.67, inherited: 20 } },
    { heading: 42.67 },
    normalState,
    2,
    [ 'heading', 'inherited' ]
  );
  assert.deepEqual(
    inheritedReconciled.entry.sizes,
    { heading: 42.67 },
    'a field changed from numeric to inherit must leave the retained baseline'
  );
};

runFontSizeBaselineTests();
console.log( 'font-size-baseline: all assertions passed' );
