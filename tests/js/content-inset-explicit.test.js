import test from 'node:test';
import assert from 'node:assert/strict';

import { createContentInsetExplicitTracker, getContentInsetExplicitCSS, contentInsetHasValue } from '../../src/_js/utils/content-inset-explicit.js';

// JS twin of style_manager_content_inset_explicit_css_cb(): the live preview must
// not flip the opt-in signal on load (the server already rendered the saved
// state) and must flip it for the rest of the session once the value moves.

test( 'loading the preview never emits the signal', () => {
  const track = createContentInsetExplicitTracker();
  assert.equal( track( 230 ), false );
  assert.equal( track( 230 ), false );
  assert.equal( getContentInsetExplicitCSS( false, ':root', '--sm-content-inset-explicit' ), '' );
} );

test( 'moving the control emits the signal, even back at the loaded value', () => {
  const track = createContentInsetExplicitTracker();
  track( '230' );
  assert.equal( track( 240 ), true );
  assert.equal( track( 230 ), true );
  assert.equal( getContentInsetExplicitCSS( true, ':root', '--sm-content-inset-explicit', 230 ), ':root { --sm-content-inset-explicit: 1; }\n' );
} );

// style-manager#220: the preview shows what saving would do. Saving a value
// marks it explicit; saving an empty value (a reset) removes the marker, so the
// preview must override a server-rendered `1` with a non-matching value.
test( 'a touched value previews the signal, a touched reset switches it off', () => {
  assert.equal( getContentInsetExplicitCSS( true, ':root', '--sm-content-inset-explicit', 140 ), ':root { --sm-content-inset-explicit: 1; }\n' );
  assert.equal( getContentInsetExplicitCSS( true, ':root', '--sm-content-inset-explicit', '187.5' ), ':root { --sm-content-inset-explicit: 1; }\n' );
  for ( const reset of [ '', null, undefined, 'wide', false ] ) {
    assert.equal( getContentInsetExplicitCSS( true, ':root', '--sm-content-inset-explicit', reset ), ':root { --sm-content-inset-explicit: 0; }\n', String( reset ) );
  }
} );

test( 'an untouched value leaves the server-rendered state alone', () => {
  assert.equal( getContentInsetExplicitCSS( false, ':root', '--sm-content-inset-explicit', 140 ), '' );
  assert.equal( getContentInsetExplicitCSS( false, ':root', '--sm-content-inset-explicit', '' ), '' );
} );

test( 'contentInsetHasValue matches the PHP rule', () => {
  for ( const v of [ 0, 140, '140', '187.5', 187.5 ] ) {
    assert.equal( contentInsetHasValue( v ), true, String( v ) );
  }
  for ( const v of [ '', ' ', null, undefined, false, true, 'wide', NaN ] ) {
    assert.equal( contentInsetHasValue( v ), false, String( v ) );
  }
} );

// The Customizer preview cannot import the module, so Screen\Customizer\Preview
// inlines a copy. Evaluate that copy and hold it to the same contract.
test( 'the Customizer preview inline twin matches the module', async () => {
  const { readFileSync } = await import( 'node:fs' );
  const php = readFileSync( new URL( '../../src/Screen/Customizer/Preview.php', import.meta.url ), 'utf8' );
  const start = php.indexOf( 'window.__smContentInsetTrack' );
  const end = php.indexOf( '" . PHP_EOL;', start );
  assert.ok( start > 0 && end > start, 'inline twin found' );
  // PHP double-quoted string: \\n is a literal backslash-n in JS source.
  const source = php.slice( start, end ).replace( /\\\\n/g, '\\n' );
  const window = {};
  const cb = new Function( 'window', `${ source }; return sm_content_inset_explicit_css_cb;` )( window );

  const sel = ':root', prop = '--sm-content-inset-explicit';
  assert.equal( cb( 230, sel, prop ), '' );
  assert.equal( cb( 230, sel, prop ), '' );
  assert.equal( cb( 140, sel, prop ), ':root { --sm-content-inset-explicit: 1; }\n' );
  assert.equal( cb( 230, sel, prop ), ':root { --sm-content-inset-explicit: 1; }\n' );
  assert.equal( cb( '', sel, prop ), ':root { --sm-content-inset-explicit: 0; }\n' );
} );
