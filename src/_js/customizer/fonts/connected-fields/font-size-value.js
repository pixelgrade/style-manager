export const getNumericFontSize = value => {
  if ( value === false || value === null || typeof value === 'undefined' || value === '' ) {
    return null;
  }

  const numericValue = Number( value );

  return Number.isFinite( numericValue ) ? numericValue : null;
};

export const hasNumericFontSize = fontData => getNumericFontSize( fontData?.font_size?.value ) !== null;

export const resolveScalableFontSize = ( currentValue, baselineValue = null ) => {
  const currentSize = getNumericFontSize( currentValue );
  if ( currentSize === null ) {
    return null;
  }

  const baselineSize = getNumericFontSize( baselineValue );
  return baselineSize === null ? currentSize : baselineSize;
};
