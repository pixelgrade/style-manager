/**
 * Derive the Elevation/Pitch target interval RELATIVE to the source interval
 * (issue #203). At the neutral knob position — elevation 0, pitch 100 — the
 * target equals the source, so re-deriving connected fields is size-neutral:
 * font palettes change the voice, the sliders change the scale.
 *
 * - Elevation shifts the whole interval by a fraction of its own span
 *   ("change all the font sizes … by a fixed amount").
 * - Pitch stretches or compresses the span above the floor ("a flat 0 pitch
 *   makes all elements equal to the elevation").
 */
export const getRelativeFontSizeInterval = ( sourceInterval, elevation, pitch ) => {
  if ( ! Array.isArray( sourceInterval ) || sourceInterval.length !== 2 ) {
    return false;
  }

  const span = Math.max( sourceInterval[ 1 ] - sourceInterval[ 0 ], 0 );
  const min = sourceInterval[ 0 ] + span * ( elevation / 100 );
  const max = min + span * ( pitch / 100 );

  return [ min, max ];
};

/**
 * Move an already-derived interval from one absolute Elevation/Pitch state to
 * another. The previous state is first inverted back to its neutral interval,
 * then the next state is applied. This makes named Font Sizing choices
 * idempotent instead of compounding each other (issue #206).
 */
export const getTransitionFontSizeInterval = ( currentInterval, previousState, nextState ) => {
  if ( ! Array.isArray( currentInterval ) || currentInterval.length !== 2 ) {
    return false;
  }

  const previousElevation = Number( previousState?.elevation );
  const previousPitch = Number( previousState?.pitch );
  const nextElevation = Number( nextState?.elevation );
  const nextPitch = Number( nextState?.pitch );

  if ( ! Number.isFinite( previousElevation ) ||
       ! Number.isFinite( previousPitch ) ||
       ! Number.isFinite( nextElevation ) ||
       ! Number.isFinite( nextPitch ) ||
       previousPitch === 0 ) {
    return false;
  }

  const currentSpan = Math.max( currentInterval[ 1 ] - currentInterval[ 0 ], 0 );
  const neutralSpan = currentSpan / ( previousPitch / 100 );
  const neutralMin = currentInterval[ 0 ] - neutralSpan * ( previousElevation / 100 );
  const neutralInterval = [ neutralMin, neutralMin + neutralSpan ];
  const targetInterval = getRelativeFontSizeInterval( neutralInterval, nextElevation, nextPitch );

  return targetInterval.map( value => Number( value.toFixed( 6 ) ) );
};

export const remapFontSize = ( fontSize, sourceInterval, targetInterval, precision = 2 ) => {
  const numericFontSize = Number( fontSize );

  if ( ! Number.isFinite( numericFontSize ) ||
       ! Array.isArray( sourceInterval ) ||
       ! Array.isArray( targetInterval ) ||
       sourceInterval.length !== 2 ||
       targetInterval.length !== 2 ) {
    return null;
  }

  if ( sourceInterval[ 1 ] === sourceInterval[ 0 ] ) {
    return Math.max( targetInterval[ 0 ], Math.min( targetInterval[ 1 ], numericFontSize ) );
  }

  const mapped = ( numericFontSize - sourceInterval[ 0 ] ) *
    ( targetInterval[ 1 ] - targetInterval[ 0 ] ) /
    ( sourceInterval[ 1 ] - sourceInterval[ 0 ] ) +
    targetInterval[ 0 ];

  return Number( mapped.toFixed( precision ) );
};

export const applyFontSizeInterval = ( fontData, fontSize, fontSizeInterval, targetFontSizeInterval, precision = 2 ) => {

  if ( ! fontSizeInterval ) {
    return;
  }

  const ab = fontSizeInterval;
  const cd = targetFontSizeInterval;

  if ( ! Array.isArray( ab ) || ! Array.isArray( cd ) ) {
    return;
  }

  const mappedFontSize = remapFontSize( fontSize, ab, cd, precision );

  if ( mappedFontSize !== null ) {
    fontData.font_size.value = mappedFontSize;
  }
};
