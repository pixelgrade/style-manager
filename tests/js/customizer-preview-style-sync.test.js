import test from 'node:test';
import assert from 'node:assert/strict';

import { bindPreviewSettings } from '../../src/_js/customizer-preview/style-sync.js';

const createSetting = ( initialValue ) => {
  const listeners = [];

  const setting = () => setting.currentValue;
  setting.currentValue = initialValue;
  setting.bind = ( callback ) => {
    listeners.push( callback );
  };
  setting.emit = ( newValue ) => {
    setting.currentValue = newValue;
    listeners.forEach( callback => callback( newValue ) );
  };

  return setting;
};

test( 'bindPreviewSettings primes current values before waiting for later changes', () => {
  const displayFont = createSetting( { font_size: { value: 54 } } );
  const bodyFont = createSetting( { font_size: { value: 16 } } );
  const queuedUpdates = [];

  bindPreviewSettings( {
    properKeys: [ 'display_font', 'body_font' ],
    customize: ( settingID, callback ) => {
      const settings = {
        display_font: displayFont,
        body_font: bodyFont,
      };

      callback( settings[ settingID ] );
    },
    enqueue: ( settingID, value ) => {
      queuedUpdates.push( [ settingID, value ] );
    },
  } );

  assert.deepEqual(
    queuedUpdates,
    [
      [ 'display_font', { font_size: { value: 54 } } ],
      [ 'body_font', { font_size: { value: 16 } } ],
    ],
    'preview bootstrap should enqueue the current value for every bound setting'
  );

  displayFont.emit( { font_size: { value: 60 } } );

  assert.deepEqual(
    queuedUpdates,
    [
      [ 'display_font', { font_size: { value: 54 } } ],
      [ 'body_font', { font_size: { value: 16 } } ],
      [ 'display_font', { font_size: { value: 60 } } ],
    ],
    'preview bindings should keep forwarding later setting updates after the initial prime'
  );
} );
