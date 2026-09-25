import test from 'node:test';
import assert from 'node:assert/strict';

import {
  createConnectedFieldsPresetOwnership,
  resolvePaletteConnectedFieldsPreset,
} from '../../src/_js/customizer/font-palettes/utils.js';

// #204: a font palette applies its own hierarchy preset only while the user has
// not set one. JS twin of the FontPalettes server rule, shared by the Customizer
// and the Site Editor (both run customizer/font-palettes/index.js).

const presetConfig = {
  default: 'preset-2',
  choices: {
    'preset-1': {},
    'preset-2': {},
    'preset-2-5': {},
    'preset-3': {},
  },
};

// A tiny stand-in for the two wp.customize settings, wired like index.js:
// every preset change is reported to the ownership tracker.
const createEditor = ( { userSet = false, preset = 'preset-2', source = '' } = {} ) => {
  const state = { preset, source };
  const ownership = createConnectedFieldsPresetOwnership( { userSet } );
  const setSource = value => {
    state.source = value;
  };
  const setPreset = value => {
    if ( value === state.preset ) {
      return;
    }
    state.preset = value;
    ownership.onPresetChange( setSource );
  };

  return {
    state,
    ownership,
    userPicksPreset: setPreset,
    applyPalette: declared => ownership.applyPalette( declared, presetConfig, { setPreset, setSource } ),
  };
};

test( 'resolves the declared preset, the default for none, and ignores unknown presets', () => {
  assert.equal( resolvePaletteConnectedFieldsPreset( 'preset-2-5', presetConfig ), 'preset-2-5' );
  assert.equal( resolvePaletteConnectedFieldsPreset( '', presetConfig ), 'preset-2' );
  assert.equal( resolvePaletteConnectedFieldsPreset( 'preset-9', presetConfig ), '' );
  assert.equal( resolvePaletteConnectedFieldsPreset( '', {} ), '' );
  assert.equal( resolvePaletteConnectedFieldsPreset( 'preset-1', { default: 'preset-2' } ), 'preset-1', 'without choices, trust the palette' );
} );

test( 'an untouched site takes the palette hierarchy, recorded as the palette\'s', () => {
  const editor = createEditor();

  assert.equal( editor.applyPalette( 'preset-2-5' ), 'preset-2-5' );
  assert.deepEqual( editor.state, { preset: 'preset-2-5', source: 'palette' } );
  assert.equal( editor.ownership.isUserSet(), false, 'the palette writing the preset does not make it user-set' );
} );

test( 'a palette-owned hierarchy round-trips back to the default', () => {
  const editor = createEditor();

  editor.applyPalette( 'preset-2-5' );
  editor.applyPalette( '' );

  assert.deepEqual( editor.state, { preset: 'preset-2', source: 'palette' } );
} );

test( 'choosing a preset in the hierarchy control keeps it through later palettes', () => {
  const editor = createEditor();

  editor.applyPalette( 'preset-2-5' );
  editor.userPicksPreset( 'preset-3' );
  assert.deepEqual( editor.state, { preset: 'preset-3', source: 'user' } );

  assert.equal( editor.applyPalette( 'preset-1' ), '' );
  assert.equal( editor.applyPalette( '' ), '' );
  assert.deepEqual( editor.state, { preset: 'preset-3', source: 'user' } );
} );

test( 'a preset the server classified as user-set is kept', () => {
  const editor = createEditor( { userSet: true, preset: 'preset-3' } );

  assert.equal( editor.applyPalette( 'preset-2-5' ), '' );
  assert.deepEqual( editor.state, { preset: 'preset-3', source: '' } );
} );
