import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// The Small-only rail (nova-blocks#655, H-S6) in the Site Editor live preview:
// the JS twin of style_manager_rail_scale_css_cb() emits Small alone while the
// Rail Scale is untouched, and moving sm_rail_small rewrites the rail tag.

const loadInitializePreview = context => {
  const source = fs.readFileSync( new URL( '../../src/_js/site-editor/preview.js', import.meta.url ), 'utf8' );
  const executable = source
    .replace( /^import .+;\n/gm, '' )
    .replace(
      '/* global WebFont */',
      `const _ = { debounce: cb => cb, includes: ( c, v ) => Array.isArray( c ) && c.includes( v ), isEmpty: v => ! v || ( Array.isArray( v ) && 0 === v.length ) };
      const getSettingCSS = () => '';
      const getCSSFromPalettes = () => '';
      const maybeFillPalettesArray = () => {};
      const getFontDetails = () => null;
      const determineFontType = () => '';
      const convertFontVariantToFVD = value => value;
      const standardizeToArray = value => Array.isArray( value ) ? value : [ value ];
      const getFontMobileScaleCSS = () => '';
      const createContentInsetExplicitTracker = () => () => false;
      const getContentInsetExplicitCSS = () => '';
      `
    )
    .replace( 'export const initializePreview', 'const initializePreview' );

  return vm.runInNewContext( `${ executable }\n( { initializePreview } );`, context ).initializePreview;
};

const setup = values => {
  const tag = { id: 'dynamic_style_sm_rail_scale', innerHTML: 'stale' };
  const window = { styleManager: { config: { settings: {} } }, matchMedia: () => ( { matches: false } ) };
  const document = {
    body: { classList: { add() {}, remove() {}, contains: () => false, toggle() {} } },
    querySelector: () => null,
    querySelectorAll: selector => ( '#dynamic_style_sm_rail_scale' === selector ? [ tag ] : [] ),
  };
  const initializePreview = loadInitializePreview( { window, document, WebFont: undefined } );
  initializePreview( id => ( id in values ? () => values[ id ] : undefined ), {} );
  const emit = () => [ '--sm-rail-small', '--sm-rail-medium', '--sm-rail-large' ]
    .map( property => window.sm_rail_scale_css_cb( values.sm_rail_scale, ':root', property ) )
    .filter( Boolean )
    .join( '\n' );
  return { window, tag, emit };
};

test( 'untouched rails emit nothing', () => {
  const { emit } = setup( { sm_rail_scale: '', sm_rail_pitch: '', sm_rail_small: '' } );
  assert.equal( emit(), '' );
} );

test( 'a Small-only value emits only the Small token', () => {
  const { emit } = setup( { sm_rail_scale: '', sm_rail_pitch: '', sm_rail_small: '180' } );
  assert.equal( emit(), ':root { --sm-rail-small: 180; }' );
} );

test( 'a touched Rail Scale ignores the Small-only value', () => {
  const { emit } = setup( { sm_rail_scale: 288, sm_rail_pitch: '', sm_rail_small: '180' } );
  assert.equal( emit(), ':root { --sm-rail-small: 288; }\n:root { --sm-rail-medium: 330; }\n:root { --sm-rail-large: 400; }' );
} );

test( 'moving the Small-only value rewrites the rail tag with Small alone', () => {
  const { window, tag } = setup( { sm_rail_scale: '', sm_rail_pitch: '', sm_rail_small: 200 } );
  assert.equal( window.sm_rail_small_css_cb( 200, ':root', '--sm-rail-small-sync' ), '' );
  assert.equal( tag.innerHTML, ':root { --sm-rail-small: 200; }\n' );
} );

test( 'moving Pitch still rewrites all three sizes', () => {
  const { window, tag } = setup( { sm_rail_scale: 288, sm_rail_pitch: '', sm_rail_small: 200 } );
  window.sm_rail_pitch_css_cb();
  assert.equal( tag.innerHTML, ':root { --sm-rail-small: 288; }\n:root { --sm-rail-medium: 330; }\n:root { --sm-rail-large: 400; }\n' );
} );
