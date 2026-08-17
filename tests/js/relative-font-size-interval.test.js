import { getRelativeFontSizeInterval, getTransitionFontSizeInterval } from '../../src/_js/customizer/fonts/connected-fields/relative-font-size-interval.js';
import { applyFontSizeInterval } from '../../src/_js/customizer/fonts/connected-fields/relative-font-size-interval.js';
import { getFontSizingPresetConfig } from '../../src/_js/customizer/fonts/font-sizing-presets.js';
import { getNumericFontSize, hasNumericFontSize, resolveScalableFontSize } from '../../src/_js/customizer/fonts/connected-fields/font-size-value.js';

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

    const preciseFontData = { font_size: { value: 42.67, unit: 'px' } };
    applyFontSizeInterval( preciseFontData, 42.67, [ 17.07, 80 ], [ 17.07, 80 ] );
    assert.equal( preciseFontData.font_size.value, 42.67, 'an identity transition must preserve the configured precision' );
  }

  {
    // Moving the knobs rescales the user's own values proportionally.
    const fontData = { font_size: { value: 66, unit: 'px' } };
    applyFontSizeInterval( fontData, 66, [ 16, 116 ], getRelativeFontSizeInterval( [ 16, 116 ], 0, 50 ) );
    assert.equal( fontData.font_size.value, 41, 'pitch 50 must compress a mid-hierarchy size toward the floor' );
  }

  {
    assert.equal( getNumericFontSize( false ), null, 'a missing connected font size must not become a zero-size interval floor' );
    assert.equal( getNumericFontSize( undefined ), null, 'an undefined connected font size must be ignored' );
    assert.equal( getNumericFontSize( '18.13' ), 18.13, 'numeric font sizes should be normalized for interval math' );
    assert.equal( hasNumericFontSize( { font_size: { value: false } } ), false, 'inherited sizes must not receive size-dependent voice styles' );
    assert.equal( hasNumericFontSize( { font_size: { value: 18.13 } } ), true, 'explicit sizes may receive size-dependent voice styles' );
    assert.equal( resolveScalableFontSize( false, 20 ), null, 'a stale numeric baseline must not replace a current inherit sentinel' );
    assert.equal( resolveScalableFontSize( 18, 20 ), 20, 'a numeric current size may use its retained neutral baseline' );
  }

  {
    assert.deepEqual(
      getFontSizingPresetConfig( 'normal' ),
      {
        sm_font_primary: [ 0, 100 ],
        sm_font_secondary: [ 0, 100 ],
        sm_font_body: [ 0, 100 ],
      },
      'Normal must be the neutral identity established by issue #203'
    );
  }

  {
    const normalInterval = [ 14, 80 ];
    const smallerInterval = getTransitionFontSizeInterval(
      normalInterval,
      { elevation: 0, pitch: 100 },
      { elevation: 6, pitch: 40 }
    );

    assert.deepEqual(
      smallerInterval,
      [ 17.96, 44.36 ],
      'a sizing preset transition should derive the target from the neutral scale'
    );
    assert.deepEqual(
      getTransitionFontSizeInterval(
        smallerInterval,
        { elevation: 6, pitch: 40 },
        { elevation: 0, pitch: 100 }
      ),
      normalInterval,
      'Smaller -> Normal must restore the original interval instead of compounding Smaller'
    );

    const interiorFontData = { font_size: { value: 42.67, unit: 'px' } };
    applyFontSizeInterval( interiorFontData, 42.67, normalInterval, smallerInterval );
    applyFontSizeInterval(
      interiorFontData,
      interiorFontData.font_size.value,
      smallerInterval,
      normalInterval
    );
    assert.equal(
      interiorFontData.font_size.value,
      42.67,
      'an interior font size must round-trip exactly through Smaller and back to Normal'
    );
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
