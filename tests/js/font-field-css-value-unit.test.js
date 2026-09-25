import test from 'node:test';
import assert from 'node:assert/strict';

// JS twin of Utils\Fonts::standardizeNumericalValue(): an empty unit falls back to the
// field config, so {value: -0.03, unit: false} tracking resolves to em instead of an
// invalid unitless value the browser drops (commit 034b7c6, issue #212).
globalThis.window = globalThis;
globalThis.styleManager = {
  config: {
    settings: {
      'anima_options[display_font]': {
        fields: {
          'letter-spacing': { unit: 'em' },
          'line-height': { unit: '' },
        },
      },
    },
  },
};

const { resolveFontSubfieldUnit } = await import( '../../src/_js/utils/get-font-subfield-unit.js' );

test( 'an empty unit is deduced from the field config', () => {
  assert.equal( resolveFontSubfieldUnit( 'anima_options[display_font]', 'letter-spacing', false ), 'em' );
  assert.equal( resolveFontSubfieldUnit( 'anima_options[display_font]', 'letter-spacing', '' ), 'em' );
  assert.equal( resolveFontSubfieldUnit( 'anima_options[display_font]', 'letter-spacing', undefined ), 'em' );
} );

test( 'fields configured without a unit stay unitless', () => {
  assert.equal( resolveFontSubfieldUnit( 'anima_options[display_font]', 'line-height', false ), false );
  assert.equal( resolveFontSubfieldUnit( 'anima_options[unknown_font]', 'line-height', false ), false );
} );

test( 'an explicit unit still wins', () => {
  assert.equal( resolveFontSubfieldUnit( 'anima_options[display_font]', 'letter-spacing', 'px' ), 'px' );
} );
