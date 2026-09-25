const isEmptyFontProperty = value => value === undefined || value === null || value === '' || value === false || value === 'false';

// The front end prints each role's saved font value, per-element overrides included
// (weight, transform, tracking, line height). Let those win over the palette-derived data,
// which only fills the properties the saved value leaves empty.
// The derived font size is the saved size already standardized to { value, unit },
// so keep it: the preview's CSS conversion expects that shape.
export const getEffectivePreviewFontData = ( savedFontData, derivedFontData ) => {
  const effective = { ...( derivedFontData || {} ) };

  Object.keys( savedFontData || {} ).forEach( key => {
    if ( key === 'font_size' && typeof effective.font_size !== 'undefined' ) {
      return;
    }

    if ( ! isEmptyFontProperty( savedFontData[ key ] ) ) {
      effective[ key ] = savedFontData[ key ];
    }
  } );

  return effective;
};
