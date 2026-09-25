import test from 'node:test';
import assert from 'node:assert/strict';

import { createContentInsetExplicitTracker, getContentInsetExplicitCSS } from '../../src/_js/utils/content-inset-explicit.js';

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
  assert.equal( getContentInsetExplicitCSS( true, ':root', '--sm-content-inset-explicit' ), ':root { --sm-content-inset-explicit: 1; }\n' );
} );
