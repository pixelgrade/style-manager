/**
 * Node entry for the shared color-generator — bundled by webpack (`target: 'node'`) into
 * the committed build artifact `dist/node/palette-generator.js`, which is the path
 * `Provider\PaletteGenerator` probes (agent-surface contract §3.11 / F13).
 *
 * Protocol — deliberately dumb, so PHP owns every policy decision:
 *
 *   stdin  : { "source": <string|array>, "options": { "sm_*": value, … }, "defaults": {…}? }
 *   stdout : the JSON array PHP persists as `sm_advanced_palette_output`
 *   stderr : a one-line diagnostic on failure
 *   exit   : 0 on success, 1 on any failure
 *
 * `options` is expected to be fully resolved by PHP through
 * `\Pixelgrade\StyleManager\get_option()` — the W5 spike proved that resolving these from
 * raw `wp option get` or from the JS config defaults reproduces 0 of 9 corpus fixtures,
 * while the PHP resolver reproduces them. `defaults` is an optional lower-precedence
 * fallback for keys `options` omits; nothing is ever read from a global here.
 */
import { getColorsFromInputValue } from './parse-source.js';
import { getPalettesFromColors } from './index.js';

const readStdin = () => new Promise( ( resolve, reject ) => {
  const chunks = [];

  process.stdin.on( 'data', chunk => chunks.push( chunk ) );
  process.stdin.on( 'end', () => resolve( Buffer.concat( chunks ).toString( 'utf8' ) ) );
  process.stdin.on( 'error', reject );
} );

const fail = ( message ) => {
  process.stderr.write( `palette-generator: ${ message }\n` );
  process.exitCode = 1;
};

const main = async () => {
  const raw = await readStdin();

  let input;
  try {
    input = JSON.parse( raw );
  } catch ( e ) {
    return fail( `could not parse the request JSON on stdin (${ e.message })` );
  }

  if ( ! input || typeof input !== 'object' || Array.isArray( input ) ) {
    return fail( 'the request must be a JSON object with a `source` key' );
  }

  const source = typeof input.source === 'string' ? input.source : JSON.stringify( input.source );
  const config = getColorsFromInputValue( source );

  if ( ! Array.isArray( config ) || ! config.length ) {
    return fail( '`source` did not parse into a non-empty array of color groups' );
  }

  let palettes;
  try {
    palettes = getPalettesFromColors( config, input.options || {}, input.defaults || null );
  } catch ( e ) {
    return fail( `the generator threw: ${ e && e.message ? e.message : e }` );
  }

  // JSON.stringify with no spacing: byte-for-byte what the Customizer's OutputUpdater
  // writes into the `sm_advanced_palette_output` setting.
  process.stdout.write( JSON.stringify( palettes ) );
};

main().catch( e => fail( e && e.message ? e.message : String( e ) ) );
