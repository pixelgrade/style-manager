import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  CONTRAST_FLOOR_ROLES,
  getContrastFloorColor,
  getContrastFloorRoleColors,
  getQuietTextColor,
} from '../../src/_js/shared/contrast-floor.js';
import { getCSSFromPalettes } from '../../src/_js/customizer/utils/get-css-from-palettes.js';

// The quiet-text role (style-manager#214). PHP twin: tests/phpunit/Unit/QuietTextRoleTest.php.

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const corpus = JSON.parse( fs.readFileSync( path.join( root, 'tests/phpunit/fixtures/quiet-text/corpus.json' ), 'utf8' ) );
const readJson = ( file ) => JSON.parse( fs.readFileSync( path.join( root, file ), 'utf8' ) );

// An independent WCAG 2.x contrast implementation, so the floor is not checked by the code under test.
const wcagLuminance = ( hex ) => {
  const value = hex.replace( '#', '' );
  const full = value.length === 3 ? value.split( '' ).map( c => c + c ).join( '' ) : value;
  const [ r, g, b ] = [ 0, 2, 4 ].map( i => parseInt( full.slice( i, i + 2 ), 16 ) / 255 )
    .map( c => ( c <= 0.04045 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4 ) );
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const contrast = ( a, b ) => {
  const [ hi, lo ] = [ wcagLuminance( a ), wcagLuminance( b ) ].sort( ( x, y ) => y - x );
  return ( hi + 0.05 ) / ( lo + 0.05 );
};

test( 'the quiet-text role is registered as fg-muted, from fg1, with a 4.5:1 floor', () => {
  assert.deepEqual( CONTRAST_FLOOR_ROLES[ 'fg-muted' ], { source: 'fg1', minContrast: 4.5, maxMix: 1 } );
} );

test( 'the corpus covers many palettes and every role it pins', () => {
  assert.ok( corpus.length > 1500, `corpus has ${ corpus.length } variations` );
  corpus.forEach( ( entry ) => assert.deepEqual( Object.keys( entry.roles ), Object.keys( CONTRAST_FLOOR_ROLES ) ) );
} );

test( 'quiet text measures >= 4.5:1 on its own ground for every corpus variation', () => {
  const failures = corpus.filter( ( { bg, fg1 } ) => contrast( getQuietTextColor( bg, fg1 ), bg ) < 4.5 );
  assert.deepEqual( failures.slice( 0, 5 ), [] );
} );

test( 'quiet text holds the floor on all 12 light and dark variations of every generated palette', () => {
  const palettes = [ ...readJson( 'src/Customize/sm_advanced_palette_output.json' ) ];
  for ( const file of fs.readdirSync( path.join( root, 'tests/phpunit/fixtures/palette-parity' ) ) ) {
    if ( /output\.json$/.test( file ) ) {
      palettes.push( ...readJson( `tests/phpunit/fixtures/palette-parity/${ file }` ) );
    }
  }

  let checked = 0;
  palettes.forEach( ( palette ) => {
    [ 'variations', 'darkVariations' ].forEach( ( key ) => {
      assert.equal( palette[ key ].length, 12 );
      palette[ key ].forEach( ( variation, index ) => {
        const quiet = getQuietTextColor( variation.bg, variation.fg1 );
        const ratio = contrast( quiet, variation.bg );
        assert.ok( ratio >= 4.5, `palette ${ palette.id } ${ key }[${ index }] ${ quiet } on ${ variation.bg } = ${ ratio.toFixed( 2 ) }` );
        checked++;
      } );
    } );
  } );

  assert.ok( checked >= 24 * 8, `checked ${ checked } variations` );
} );

test( 'quiet text is softer than body text and as soft as the floor allows', () => {
  let softened = 0;

  corpus.forEach( ( { bg, fg1 } ) => {
    const quiet = getQuietTextColor( bg, fg1 );
    const bodyRatio = contrast( fg1, bg );
    const quietRatio = contrast( quiet, bg );

    if ( bodyRatio >= 4.5 ) {
      assert.ok( quietRatio <= bodyRatio + 1e-9, `${ quiet } is louder than ${ fg1 } on ${ bg }` );
      // One 1/200 step of the fg1 → bg walk moves the ratio by far less than 0.15 near the floor.
      if ( bodyRatio >= 4.8 ) {
        assert.ok( quietRatio < 4.65, `${ quiet } on ${ bg } stops at ${ quietRatio.toFixed( 3 ) }, short of the floor` );
        softened++;
      }
    }
  } );

  assert.ok( softened > 1000, `softened ${ softened } variations` );
} );

test( 'text below the floor is pushed away from the ground until it passes', () => {
  // A mid-grey ground whose fg1 fails: the role must still clear 4.5:1.
  const quiet = getQuietTextColor( '#777777', '#888888' );
  assert.ok( contrast( quiet, '#777777' ) >= 4.5, quiet );

  // The case from the issue: #8e9295 on white is 3.14:1.
  const fixed = getQuietTextColor( '#ffffff', '#8e9295' );
  assert.ok( contrast( fixed, '#ffffff' ) >= 4.5, fixed );
  assert.ok( contrast( fixed, '#ffffff' ) < 4.65, `${ fixed } darkens more than needed` );
} );

test( 'the role depends only on the ground and text colour, not on other grades', () => {
  const variation = { bg: '#f4efe6', fg1: '#1d1b19', fg2: '#3a3530', accent: '#b0472c' };
  const moved = { ...variation, fg2: '#000000', accent: '#1f6fd0', accent2: '#ffcc00' };

  assert.deepEqual( getContrastFloorRoleColors( variation ), getContrastFloorRoleColors( moved ) );
} );

test( 'non-hex values pass through as the source colour', () => {
  assert.equal( getContrastFloorColor( 'rgb(0,0,0)', '#ffffff', 4.5 ), '#ffffff' );
  assert.equal( getContrastFloorColor( '#000000', 'var(--x)', 4.5 ), 'var(--x)' );
  assert.deepEqual( getContrastFloorRoleColors( { fg1: '#000000' } ), {} );
} );

test( 'maxMix caps how far a role softens, for later roles such as rule colours', () => {
  const capped = getContrastFloorColor( '#ffffff', '#000000', 1.2, 0.5 );
  assert.equal( capped, '#808080' );
  assert.equal( getContrastFloorColor( '#ffffff', '#000000', 1.2, 0 ), '#000000' );
} );

test( 'the JS emitter matches the corpus that pins the PHP emitter', () => {
  const mismatches = corpus.filter( ( entry ) => {
    const roles = getContrastFloorRoleColors( entry );
    return JSON.stringify( roles ) !== JSON.stringify( entry.roles );
  } );
  assert.deepEqual( mismatches.slice( 0, 5 ), [] );
} );

test( 'getCSSFromPalettes emits --sm-fg-muted-color-N after the existing roles, leaving them unchanged', () => {
  const palettes = readJson( 'src/Customize/sm_advanced_palette_output.json' );
  const css = getCSSFromPalettes( palettes, 1 );
  const palette = palettes[ 0 ];

  for ( let index = 0; index < 12; index++ ) {
    const variation = palette.variations[ index ];
    const expected = getQuietTextColor( variation.bg, variation.fg1 );
    assert.match( css, new RegExp( `--sm-fg-muted-color-${ index + 1 }: ${ expected };` ) );
  }

  // Existing roles are still emitted exactly as before.
  const withoutRole = css.replace( /\n--sm-fg-muted-color-\d+: #[0-9a-f]{6};/g, '' );
  assert.doesNotMatch( withoutRole, /fg-muted/ );
  assert.match( withoutRole, new RegExp( `--sm-fg1-color-1: ${ palette.variations[ 0 ].fg1 };` ) );
  assert.match( withoutRole, new RegExp( `--sm-fg2-color-1: ${ palette.variations[ 0 ].fg2 };` ) );
} );

test( 'a stored fg-muted value cannot bypass the floor', () => {
  const palette = {
    id: 1,
    sourceIndex: 0,
    variations: Array.from( { length: 12 }, () => ( { bg: '#ffffff', fg1: '#111111', fg2: '#111111', accent: '#111111', 'fg-muted': '#eeeeee' } ) ),
    darkVariations: Array.from( { length: 12 }, () => ( { bg: '#111111', fg1: '#ffffff', fg2: '#ffffff', accent: '#ffffff' } ) ),
  };
  const css = getCSSFromPalettes( [ palette ], 1 );

  assert.doesNotMatch( css, /#eeeeee/ );
  assert.match( css, new RegExp( `--sm-fg-muted-color-1: ${ getQuietTextColor( '#ffffff', '#111111' ) };` ) );
} );

test( 'every palette variation class exposes the contextual quiet-text role', () => {
  const source = fs.readFileSync( path.join( root, 'src/_scss/sm-colors-custom-properties.scss' ), 'utf8' );
  const applyVariation = source.match( /@mixin apply-variation\(\$i\)\s*\{([\s\S]*?)\n\}/ )?.[ 1 ] ?? '';

  assert.match(
    applyVariation,
    /--sm-current-fg-muted-color:\s*var\(--sm-fg-muted-color-#\{ \$i \},\s*var\(--sm-current-fg1-color\)\);/
  );
} );
