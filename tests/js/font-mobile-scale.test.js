import test from 'node:test';
import assert from 'node:assert/strict';

import { getFontMobileScaleCSS, getFontMobileScaleSlope } from '../../src/_js/utils/font-mobile-scale.js';

// JS twin of style_manager_font_mobile_scale_css_cb() — the Site Editor preview
// must emit the same string the server saves (tests/phpunit/Unit/FontMobileScaleCssTest.php).

test( 'unset value emits nothing so the theme slope stays', () => {
  assert.equal( getFontMobileScaleSlope( '' ), null );
  assert.equal( getFontMobileScaleSlope( undefined ), null );
  assert.equal( getFontMobileScaleSlope( 'large' ), null );
  assert.equal( getFontMobileScaleCSS( '', ':root', '--theme-font-size-slope-adjust' ), '' );
} );

test( 'value maps to the share of desktop size phones keep, clamped', () => {
  assert.equal( getFontMobileScaleSlope( 40 ), 0.6 );
  assert.equal( getFontMobileScaleSlope( 100 ), 0 );
  assert.equal( getFontMobileScaleSlope( 0 ), 1 );
  assert.equal( getFontMobileScaleSlope( '75' ), 0.25 );
  assert.equal( getFontMobileScaleSlope( -20 ), 1 );
  assert.equal( getFontMobileScaleSlope( 140 ), 0 );
} );

test( 'css matches the server output byte for byte', () => {
  assert.equal(
    getFontMobileScaleCSS( 65, ':root', '--theme-font-size-slope-adjust' ),
    '@media not screen and (min-width: 1440px) { :root { --theme-font-size-slope-adjust: 0.35; } }\n'
  );
} );
