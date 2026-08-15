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

export const applyFontSizeInterval = ( fontData, fontSize, fontSizeInterval, targetFontSizeInterval ) => {

  if ( ! fontSizeInterval ) {
    return;
  }

  const ab = fontSizeInterval;
  const cd = targetFontSizeInterval;

  if ( ! Array.isArray( ab ) || ! Array.isArray( cd ) ) {
    return;
  }

  if ( !! fontSize ) {

    if ( ab[1] === ab[0] ) {
      fontData.font_size.value = Math.max( cd[0], Math.min( cd[1], fontSize ) );
    } else {
      const newFontSize = ( fontSize - ab[0] ) * ( cd[1] - cd[0] ) / ( ab[1] - ab[0] ) + cd[0];
      fontData.font_size.value = Math.round( newFontSize * 10 ) / 10;
    }
  }
};
