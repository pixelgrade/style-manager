/**
 * Contrast-floor colour roles: per-variation colours derived from a variation's own colours and
 * guaranteed a minimum WCAG contrast against that variation's ground (bg).
 *
 * The first role is quiet text (style-manager#214): `--sm-fg-muted-color-N` per variation and
 * `--sm-current-fg-muted-color` in context, for secondary text (meta, dates, terms, captions)
 * that reads softer than body text and never drops below 4.5:1 on its own ground. Further roles
 * (for example the primary / secondary rule colours of nova-blocks#668) are one more entry in
 * `CONTRAST_FLOOR_ROLES` and in its PHP twin `style_manager_get_contrast_floor_roles()`.
 *
 * Generation rule for one role, identical to `style_manager_get_contrast_floor_color()` in
 * `src/sm-functions.php` (both pinned to `tests/phpunit/fixtures/quiet-text/corpus.json`):
 *
 * 1. Walk from the role's source colour (e.g. fg1) toward bg in 1/200 steps, at most `maxMix`
 *    of the way, and keep the last step that still measures >= `minContrast` against bg.
 *    The result depends only on bg and the source, never on the palette grades that paint other
 *    surfaces.
 * 2. When the source itself is below `minContrast`, walk from it away from bg (toward black or
 *    white, whichever contrasts more with bg) and keep the first step that reaches the floor.
 *    Black or white always clears 4.58:1, so any floor up to that is guaranteed.
 * 3. A value that is not a plain hex colour is passed through unchanged.
 *
 * Pure integer-channel sRGB math, no chroma-js, so PHP reproduces it byte for byte.
 */

export const CONTRAST_FLOOR_STEPS = 200;

/**
 * Role key => how it is generated. The key becomes `--sm-<key>-color-N`.
 *
 * - source:      the variation colour the role starts from.
 * - minContrast: the floor against the variation's bg.
 * - maxMix:      how far toward bg the role may soften (0 = the source, 1 = all the way).
 */
export const CONTRAST_FLOOR_ROLES = {
  'fg-muted': { source: 'fg1', minContrast: 4.5, maxMix: 1 },
};

const HEX_PATTERN = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

export const normalizeHex = ( color ) => {
  if ( typeof color !== 'string' || ! HEX_PATTERN.test( color.trim() ) ) {
    return null;
  }

  let hex = color.trim().toLowerCase().slice( 1 );

  if ( hex.length === 3 ) {
    hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
  }

  return `#${ hex }`;
};

const toChannels = ( hex ) => [
  parseInt( hex.slice( 1, 3 ), 16 ),
  parseInt( hex.slice( 3, 5 ), 16 ),
  parseInt( hex.slice( 5, 7 ), 16 ),
];

const toHex = ( channels ) => '#' + channels.map( channel => channel.toString( 16 ).padStart( 2, '0' ) ).join( '' );

// Same WCAG 2.0 formula (and 0.03928 threshold) as style_manager_hex_color_relative_luminance().
const luminance = ( channels ) => {
  const linear = channels.map( channel => {
    const value = channel / 255;
    return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
  } );

  return 0.2126 * linear[ 0 ] + 0.7152 * linear[ 1 ] + 0.0722 * linear[ 2 ];
};

const contrastFromLuminance = ( a, b ) => ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );

// `step` / CONTRAST_FLOOR_STEPS of the way from base to target, rounded half up in exact integer
// math: float rounding differs between JS Math.round() and PHP round() (which pre-rounds).
const mix = ( base, target, step ) => base.map( ( channel, index ) => {
  const numerator = channel * ( CONTRAST_FLOOR_STEPS - step ) + target[ index ] * step;
  return Math.floor( ( 2 * numerator + CONTRAST_FLOOR_STEPS ) / ( 2 * CONTRAST_FLOOR_STEPS ) );
} );

export const getContrastRatio = ( colorA, colorB ) => {
  const a = normalizeHex( colorA );
  const b = normalizeHex( colorB );

  if ( ! a || ! b ) {
    return NaN;
  }

  return contrastFromLuminance( luminance( toChannels( a ) ), luminance( toChannels( b ) ) );
};

const cache = new Map();

/**
 * The softest colour on the source → background line that keeps `minContrast` against the
 * background (see the file header for the full rule).
 *
 * @param {string} background  The variation's ground (bg).
 * @param {string} source      The colour the role starts from (e.g. fg1).
 * @param {number} minContrast The contrast floor against `background`.
 * @param {number} maxMix      How far toward `background` the colour may soften, 0–1.
 *
 * @return {string} A lowercase #rrggbb colour, or `source` unchanged when either input is not hex.
 */
export const getContrastFloorColor = ( background, source, minContrast, maxMix = 1 ) => {
  const bgHex = normalizeHex( background );
  const sourceHex = normalizeHex( source );

  if ( ! bgHex || ! sourceHex ) {
    return source;
  }

  const maxSteps = Math.min( CONTRAST_FLOOR_STEPS - 1, Math.round( Math.max( 0, Math.min( 1, maxMix ) ) * CONTRAST_FLOOR_STEPS ) );
  const key = `${ bgHex }|${ sourceHex }|${ minContrast }|${ maxSteps }`;

  if ( cache.has( key ) ) {
    return cache.get( key );
  }

  const bg = toChannels( bgHex );
  const from = toChannels( sourceHex );
  const bgLuminance = luminance( bg );
  const passes = ( channels ) => contrastFromLuminance( luminance( channels ), bgLuminance ) >= minContrast;

  let result = sourceHex;

  if ( passes( from ) ) {
    for ( let step = 1; step <= maxSteps; step++ ) {
      const candidate = mix( from, bg, step );

      if ( ! passes( candidate ) ) {
        break;
      }

      result = toHex( candidate );
    }
  } else {
    const extreme = contrastFromLuminance( 0, bgLuminance ) >= contrastFromLuminance( 1, bgLuminance ) ? [ 0, 0, 0 ] : [ 255, 255, 255 ];

    for ( let step = 1; step <= CONTRAST_FLOOR_STEPS; step++ ) {
      const candidate = mix( from, extreme, step );

      if ( passes( candidate ) ) {
        result = toHex( candidate );
        break;
      }
    }
  }

  // Customizer edits keep producing new grounds; keep the memo bounded.
  if ( cache.size > 5000 ) {
    cache.clear();
  }

  cache.set( key, result );

  return result;
};

/**
 * Every contrast-floor role colour for one variation.
 *
 * @param {Object} variation A palette variation ({ bg, fg1, fg2, accent, … }).
 * @param {Object} roles     Role definitions; defaults to CONTRAST_FLOOR_ROLES.
 *
 * @return {Object} role key => colour. Roles whose source or bg is missing are left out.
 */
export const getContrastFloorRoleColors = ( variation, roles = CONTRAST_FLOOR_ROLES ) => {
  const colors = {};

  if ( ! variation || ! variation.bg ) {
    return colors;
  }

  Object.keys( roles ).forEach( ( key ) => {
    const { source, minContrast, maxMix } = roles[ key ];

    if ( variation[ source ] ) {
      colors[ key ] = getContrastFloorColor( variation.bg, variation[ source ], minContrast, maxMix );
    }
  } );

  return colors;
};

/**
 * The quiet-text colour (style-manager#214) for one ground and text colour.
 *
 * @param {string} background The variation's bg.
 * @param {string} foreground The variation's fg1.
 *
 * @return {string}
 */
export const getQuietTextColor = ( background, foreground ) => {
  const { minContrast, maxMix } = CONTRAST_FLOOR_ROLES[ 'fg-muted' ];

  return getContrastFloorColor( background, foreground, minContrast, maxMix );
};
