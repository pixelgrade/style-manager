import test from 'node:test';
import assert from 'node:assert/strict';

import { getEffectivePreviewFontData } from '../../src/_js/customizer/components/typography-overlay/effective-font-data.js';

// The front end prints each role's SAVED font value (Fonts::getFontStyle), so the
// Typography preview must let that value win over the palette-derived one (issue #212).

const derived = {
  font_family: 'Palette Sans',
  font_size: { value: 77, unit: 'px' },
  font_variant: '400',
  letter_spacing: { value: 0, unit: 'em' },
  line_height: { value: 1.05, unit: '' },
};

test( 'per-element weight and transform overrides reach the preview', () => {
  const saved = {
    ...derived,
    font_variant: '900',
    text_transform: 'uppercase',
    letter_spacing: { value: 0.02, unit: 'em' },
  };

  const effective = getEffectivePreviewFontData( saved, derived );

  assert.equal( effective.font_variant, '900' );
  assert.equal( effective.text_transform, 'uppercase' );
  assert.deepEqual( effective.letter_spacing, { value: 0.02, unit: 'em' } );
  assert.equal( effective.font_family, 'Palette Sans' );
} );

test( 'without overrides the preview keeps the palette values', () => {
  assert.deepEqual( getEffectivePreviewFontData( { ...derived }, derived ), derived );
} );

test( 'palette values fill properties the saved value leaves empty, and the standardized size stays', () => {
  const effective = getEffectivePreviewFontData(
    { font_size: { value: 43, unit: 'px' }, font_variant: '', text_transform: false },
    { ...derived, text_transform: 'none' }
  );

  assert.equal( effective.font_family, 'Palette Sans' );
  assert.equal( effective.font_variant, '400' );
  assert.equal( effective.text_transform, 'none' );
  assert.deepEqual( effective.font_size, { value: 77, unit: 'px' } );
} );

test( 'a raw saved size falls back only when there is no derived size', () => {
  assert.deepEqual( getEffectivePreviewFontData( { font_size: 43 }, { font_family: 'Palette Sans' } ), { font_family: 'Palette Sans', font_size: 43 } );
} );

test( 'missing values on either side do not throw', () => {
  assert.deepEqual( getEffectivePreviewFontData( null, derived ), derived );
  assert.deepEqual( getEffectivePreviewFontData( { font_variant: '700' }, null ), { font_variant: '700' } );
  assert.deepEqual( getEffectivePreviewFontData( undefined, undefined ), {} );
} );
