export const getUserPalettes = ( palettes = [] ) => (
  palettes.filter( ( palette ) => ! ( typeof palette?.id === 'string' && palette.id.charAt( 0 ) === '_' ) )
);

export const normalizePreviewIndex = ( index, siteVariation = 1 ) => {
  const parsedVariation = Number.parseInt( siteVariation, 10 );
  const variation = Number.isNaN( parsedVariation )
    ? 1
    : Math.min( Math.max( parsedVariation, 1 ), 12 );

  return ( index + variation - 1 + 12 ) % 12;
};

export const normalizeHexColor = ( value ) => {
  const color = typeof value === 'string' ? value.trim().toLowerCase() : '';

  return /^#[0-9a-f]{6}$/.test( color ) ? color : '';
};

export const getInitialHoverVariation = ( sourceIndex = 0 ) => {
  const parsed = Number.parseInt( sourceIndex, 10 );

  return ( Number.isNaN( parsed ) ? 0 : parsed ) + 1;
};

export const isSourceVariation = ( {
  variations = [],
  workingIndex = 0,
  source = [],
} ) => {
  const background = normalizeHexColor( variations[ workingIndex ]?.bg );

  if ( ! background || ! source.some( ( color ) => normalizeHexColor( color ) === background ) ) {
    return false;
  }

  return variations.findIndex( ( variation ) => normalizeHexColor( variation?.bg ) === background ) === workingIndex;
};
