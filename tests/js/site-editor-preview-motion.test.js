import test from 'node:test';
import assert from 'node:assert/strict';

import {
  AUTO_OPEN_DELAY_MS,
  EXIT_MS,
  INTRO_SETTLE_MS,
  REDUCED_MOTION_FADE_MS,
  SWITCH_SETTLE_MS,
  clearOverlayRender,
  getExitMs,
  getIntroSettleMs,
  getSwitchSettleMs,
  prefersReducedMotion,
  resolveOverlayRender,
  schedulePreviewAutoOpen,
  settleOverlayRender,
} from '../../src/_js/site-editor/preview-motion.js';

test( 'opening from nothing enters', () => {
  const rendered = resolveOverlayRender( clearOverlayRender(), { mode: 'colors', context: null } );

  assert.deepEqual( rendered, { mode: 'colors', context: null, phase: 'entering' } );
} );

test( 'closing keeps the last mode rendered while exiting (deferred unmount)', () => {
  const open = { mode: 'colors', context: null, phase: 'open' };
  const rendered = resolveOverlayRender( open, { mode: null, context: null } );

  assert.deepEqual( rendered, { mode: 'colors', context: null, phase: 'exiting' } );
} );

test( 'closing while already exiting does not restart the exit', () => {
  const exiting = { mode: 'colors', context: null, phase: 'exiting' };
  const rendered = resolveOverlayRender( exiting, { mode: null, context: null } );

  assert.equal( rendered, exiting );
} );

test( 'reopening during the exit re-enters', () => {
  const exiting = { mode: 'colors', context: null, phase: 'exiting' };
  const rendered = resolveOverlayRender( exiting, { mode: 'colors', context: null } );

  assert.deepEqual( rendered, { mode: 'colors', context: null, phase: 'entering' } );
} );

test( 'a mode-to-mode switch cross-fades instead of replaying the entrance', () => {
  const open = { mode: 'site-frame', context: null, phase: 'open' };
  const rendered = resolveOverlayRender( open, { mode: 'fancy-titles', context: null } );

  assert.deepEqual( rendered, { mode: 'fancy-titles', context: null, phase: 'switching' } );
} );

test( 'a switch during the entrance keeps the entrance playing', () => {
  const entering = { mode: 'colors', context: null, phase: 'entering' };
  const rendered = resolveOverlayRender( entering, { mode: 'site', context: null } );

  assert.deepEqual( rendered, { mode: 'site', context: null, phase: 'entering' } );
} );

test( 'a second switch before the first settles stays in switching', () => {
  const switching = { mode: 'colors', context: null, phase: 'switching' };
  const rendered = resolveOverlayRender( switching, { mode: 'spacing', context: null } );

  assert.deepEqual( rendered, { mode: 'spacing', context: null, phase: 'switching' } );
} );

test( 'closing from a switch exits like any open board', () => {
  const switching = { mode: 'spacing', context: null, phase: 'switching' };
  const rendered = resolveOverlayRender( switching, { mode: null, context: null } );

  assert.deepEqual( rendered, { mode: 'spacing', context: null, phase: 'exiting' } );
} );

test( 'a context change while open swaps in place too', () => {
  const open = { mode: 'site', context: null, phase: 'open' };
  const rendered = resolveOverlayRender( open, { mode: 'site', context: 'motion' } );

  assert.deepEqual( rendered, { mode: 'site', context: 'motion', phase: 'open' } );
} );

test( 'a cleared store with nothing rendered stays cleared', () => {
  const cleared = clearOverlayRender();
  const rendered = resolveOverlayRender( cleared, { mode: null, context: null } );

  assert.equal( rendered, cleared );
} );

test( 'settleOverlayRender releases the entering and switching phases', () => {
  assert.deepEqual(
    settleOverlayRender( { mode: 'colors', context: null, phase: 'entering' } ),
    { mode: 'colors', context: null, phase: 'open' }
  );

  assert.deepEqual(
    settleOverlayRender( { mode: 'colors', context: null, phase: 'switching' } ),
    { mode: 'colors', context: null, phase: 'open' }
  );

  const exiting = { mode: 'colors', context: null, phase: 'exiting' };
  assert.equal( settleOverlayRender( exiting ), exiting );
} );

test( 'durations fall back to the full motion without a reduced-motion signal', () => {
  assert.equal( prefersReducedMotion(), false );
  assert.equal( getIntroSettleMs(), INTRO_SETTLE_MS );
  assert.equal( getSwitchSettleMs(), SWITCH_SETTLE_MS );
  assert.equal( getExitMs(), EXIT_MS );
} );

test( 'prefers-reduced-motion collapses both directions to the short fade', t => {
  globalThis.window = {
    matchMedia: query => ( { matches: '(prefers-reduced-motion: reduce)' === query } ),
  };
  t.after( () => {
    delete globalThis.window;
  } );

  assert.equal( prefersReducedMotion(), true );
  assert.equal( getIntroSettleMs(), REDUCED_MOTION_FADE_MS );
  assert.equal( getSwitchSettleMs(), REDUCED_MOTION_FADE_MS );
  assert.equal( getExitMs(), REDUCED_MOTION_FADE_MS );
} );

test( 'schedulePreviewAutoOpen fires after the debounce delay', t => {
  t.mock.timers.enable( { apis: [ 'setTimeout' ] } );

  let opened = 0;
  schedulePreviewAutoOpen( () => opened++ );

  t.mock.timers.tick( AUTO_OPEN_DELAY_MS - 1 );
  assert.equal( opened, 0 );

  t.mock.timers.tick( 1 );
  assert.equal( opened, 1 );
} );

test( 'schedulePreviewAutoOpen can be cancelled', t => {
  t.mock.timers.enable( { apis: [ 'setTimeout' ] } );

  let opened = 0;
  const cancel = schedulePreviewAutoOpen( () => opened++ );
  cancel();

  t.mock.timers.tick( AUTO_OPEN_DELAY_MS * 2 );
  assert.equal( opened, 0 );
} );
