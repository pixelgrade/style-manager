/**
 * Builds tests/phpunit/fixtures/quiet-text/corpus.json — variation colours and the contrast-floor
 * role colours generated for them (the quiet-text role of style-manager#214 and any later role).
 * The corpus pins the PHP and JS implementations to each other.
 *
 * The pairs come from every variation and dark variation of:
 * - the shipped default palette output and the palette-parity fixtures;
 * - palettes the bundled generator (dist/node/palette-generator.js) produces for 60 seeded
 *   brand colours under three option sets;
 * - a few hand-picked edge pairs (text below the floor, mid-grey grounds, short hex, and walks
 *   whose contrast is not monotonic).
 *
 * Run from the plugin root: `node tests/js/support/build-quiet-text-corpus.mjs`.
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

import { getContrastFloorRoleColors } from '../../../src/_js/shared/contrast-floor.js';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../../..' );
const generator = path.join( root, 'dist/node/palette-generator.js' );
const out = path.join( root, 'tests/phpunit/fixtures/quiet-text/corpus.json' );

const readJson = ( file ) => JSON.parse( fs.readFileSync( path.join( root, file ), 'utf8' ) );

const palettes = [];
palettes.push( ...readJson( 'src/Customize/sm_advanced_palette_output.json' ) );

for ( const file of fs.readdirSync( path.join( root, 'tests/phpunit/fixtures/palette-parity' ) ) ) {
  if ( /output\.json$/.test( file ) ) {
    palettes.push( ...readJson( `tests/phpunit/fixtures/palette-parity/${ file }` ) );
  }
}

// Deterministic brand colours (mulberry32).
let seed = 214;
const random = () => {
  seed |= 0;
  seed = ( seed + 0x6D2B79F5 ) | 0;
  let t = Math.imul( seed ^ ( seed >>> 15 ), 1 | seed );
  t = ( t + Math.imul( t ^ ( t >>> 7 ), 61 | t ) ) ^ t;
  return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
};
const randomHex = () => '#' + [ 0, 0, 0 ].map( () => Math.floor( random() * 256 ).toString( 16 ).padStart( 2, '0' ) ).join( '' );

const baseOptions = {
  sm_color_grades_number: 12,
  sm_potential_color_contrast: 1,
  sm_color_grade_balancer: 0,
  sm_site_color_variation: 1,
  sm_elements_color_contrast: 'normal',
  sm_color_promotion_brand: '',
  sm_color_promotion_white: true,
  sm_color_promotion_black: true,
};

const optionSets = [
  baseOptions,
  { ...baseOptions, sm_elements_color_contrast: 'maximum', sm_potential_color_contrast: 0.6, sm_color_grade_balancer: 0.5 },
  { ...baseOptions, sm_color_promotion_brand: '1', sm_color_promotion_white: false, sm_potential_color_contrast: 0.8, sm_color_grade_balancer: -0.5 },
];

optionSets.forEach( ( options ) => {
  const groups = Array.from( { length: 20 }, ( _, index ) => ( {
    sources: [ { value: randomHex(), label: `Brand ${ index + 1 }` } ],
  } ) );

  const request = JSON.stringify( { source: JSON.stringify( groups ), options } );
  const output = execFileSync( process.execPath, [ generator ], { input: request, cwd: root } ).toString();

  palettes.push( ...JSON.parse( output ) );
} );

const pairs = new Map();
const addPair = ( bg, fg1, fg2 = fg1 ) => {
  const key = `${ bg }|${ fg1 }|${ fg2 }`;

  if ( ! pairs.has( key ) ) {
    pairs.set( key, { bg, fg1, fg2, roles: getContrastFloorRoleColors( { bg, fg1, fg2 } ) } );
  }
};

palettes.forEach( ( palette ) => {
  [ ...( palette.variations || [] ), ...( palette.darkVariations || [] ) ].forEach( ( variation ) => addPair( variation.bg, variation.fg1, variation.fg2 ) );
} );

[
  [ '#ffffff', '#000000' ],
  [ '#000000', '#ffffff' ],
  [ '#ffffff', '#8e9295' ],
  [ '#777777', '#888888' ],
  [ '#767676', '#ffffff' ],
  [ '#fff', '#111' ],
  [ '#FFFFFF', '#222222' ],
  [ '#2e72d2', '#ffffff' ],
  [ '#ffcc00', '#111111' ],
  // Channels moving in opposite directions: contrast is not monotonic along the walk, so the
  // first failing step is not where a binary search would land.
  [ '#48e7c1', '#aa2307' ],
  [ '#06de40', '#1f4f79' ],
  [ '#8cf19d', '#9e03ce' ],
].forEach( ( [ bg, fg1 ] ) => addPair( bg, fg1 ) );

fs.mkdirSync( path.dirname( out ), { recursive: true } );
fs.writeFileSync( out, JSON.stringify( [ ...pairs.values() ], null, '\t' ) + '\n' );

console.log( `${ pairs.size } variations from ${ palettes.length } palettes -> ${ path.relative( root, out ) }` );
