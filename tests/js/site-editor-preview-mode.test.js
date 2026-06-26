import test from 'node:test';
import assert from 'node:assert/strict';

import {
  getPreviewState,
  isPreviewEntryOpen,
  resetPreviewModeForTests,
  setPreviewMode,
  subscribePreviewMode,
} from '../../src/_js/site-editor/preview-mode.js';

test( 'subscribePreviewMode immediately replays the current mode and context', () => {
  resetPreviewModeForTests();
  setPreviewMode( 'site', 'motion' );

  let received = null;
  const unsubscribe = subscribePreviewMode( state => {
    received = state;
  } );

  assert.deepEqual( received, { mode: 'site', context: 'motion' } );

  unsubscribe();
  resetPreviewModeForTests();
} );

test( 'subscribePreviewMode notifies when only the preview context changes', () => {
  resetPreviewModeForTests();
  const received = [];
  const unsubscribe = subscribePreviewMode( state => {
    received.push( state );
  } );

  setPreviewMode( 'site' );
  setPreviewMode( 'site', 'motion' );

  assert.deepEqual( received, [
    { mode: null, context: null },
    { mode: 'site', context: null },
    { mode: 'site', context: 'motion' },
  ] );

  unsubscribe();
  resetPreviewModeForTests();
} );

test( 'isPreviewEntryOpen distinguishes previews that share a mode', () => {
  resetPreviewModeForTests();
  setPreviewMode( 'site' );

  assert.equal( isPreviewEntryOpen( { mode: 'site' } ), true );
  assert.equal( isPreviewEntryOpen( { mode: 'site', context: 'motion' } ), false );

  setPreviewMode( 'site', 'motion' );

  assert.equal( isPreviewEntryOpen( { mode: 'site' } ), false );
  assert.equal( isPreviewEntryOpen( { mode: 'site', context: 'motion' } ), true );
  assert.deepEqual( getPreviewState(), { mode: 'site', context: 'motion' } );

  resetPreviewModeForTests();
} );
