import { getRelativeFontSizeInterval } from '../../src/_js/customizer/fonts/connected-fields/relative-font-size-interval.js';
import { applyFontSizeInterval } from '../../src/_js/customizer/fonts/connected-fields/relative-font-size-interval.js';

export const runRelativeFontSizeIntervalTests = async ( assert ) => {
  {
    // Neutral knobs are an identity: the target interval IS the source interval,
    // so palette application never rescales sizes (issue #203).
    assert.deepEqual(
      getRelativeFontSizeInterval( [ 15, 115 ], 0, 100 ),
      [ 15, 115 ],
      'elevation 0 / pitch 100 must reproduce the source interval exactly'
    );
  }

  {
    // Elevation shifts the whole interval by a fraction of its own span.
    assert.deepEqual(
      getRelativeFontSizeInterval( [ 20, 120 ], 25, 100 ),
      [ 45, 145 ],
      'elevation 25 must lift the interval by a quarter of its span'
    );
    assert.deepEqual(
      getRelativeFontSizeInterval( [ 20, 120 ], -25, 100 ),
      [ -5, 95 ],
      'negative elevation must lower the interval by the same rule'
    );
  }

  {
    // Pitch stretches or compresses the span above the floor.
    assert.deepEqual(
      getRelativeFontSizeInterval( [ 20, 120 ], 0, 50 ),
      [ 20, 70 ],
      'pitch 50 must halve the hierarchy span'
    );
    assert.deepEqual(
      getRelativeFontSizeInterval( [ 20, 120 ], 0, 0 ),
      [ 20, 20 ],
      'pitch 0 must flatten every element onto the elevation floor'
    );
  }

  {
    assert.equal(
      getRelativeFontSizeInterval( false, 0, 100 ),
      false,
      'a missing source interval must not produce a target interval'
    );
  }

  {
    // Remapping a CURRENT value through the neutral interval keeps it untouched —
    // the property that makes font palettes size-neutral end to end.
    const fontData = { font_size: { value: 17, unit: 'px' } };
    applyFontSizeInterval( fontData, 17, [ 15, 115 ], getRelativeFontSizeInterval( [ 15, 115 ], 0, 100 ) );
    assert.equal( fontData.font_size.value, 17, 'a fine-tuned size must survive a neutral re-derivation' );
  }

  {
    // Moving the knobs rescales the user's own values proportionally.
    const fontData = { font_size: { value: 66, unit: 'px' } };
    applyFontSizeInterval( fontData, 66, [ 16, 116 ], getRelativeFontSizeInterval( [ 16, 116 ], 0, 50 ) );
    assert.equal( fontData.font_size.value, 41, 'pitch 50 must compress a mid-hierarchy size toward the floor' );
  }
};

const isDirectRun = typeof process !== 'undefined'
  && process.argv?.[ 1 ]
  && import.meta.url.endsWith( process.argv[ 1 ].split( '/' ).pop() );

if ( isDirectRun ) {
  const { strict: assert } = await import( 'node:assert' );
  await runRelativeFontSizeIntervalTests( assert );
  console.log( 'relative-font-size-interval: all assertions passed' );
}
