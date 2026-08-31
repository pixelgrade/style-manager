export const getColorsFromInputValue = ( value ) => {
  let colors;

  try {
    colors = JSON.parse( value );
  } catch( e ) {
    colors = [];
  }

  return stripTransientSourceColorState( colors );
};

export const stripTransientSourceColorState = ( colors ) => {
  if ( ! Array.isArray( colors ) ) {
    return colors;
  }

  return colors.map( group => {
    if ( ! group || typeof group !== 'object' || ! Array.isArray( group.sources ) ) {
      return group;
    }

    return {
      ...group,
      sources: group.sources.map( source => {
        if ( ! source || typeof source !== 'object' ) {
          return source;
        }

        const { showPicker, ...sourceData } = source;
        return sourceData;
      } ),
    };
  } );
};
