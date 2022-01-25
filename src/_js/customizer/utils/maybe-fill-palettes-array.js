export const maybeFillPalettesArray = ( arr, minLength ) => {

  if ( Array.isArray( arr ) && !! arr.length ) {

    const userPalettes = arr.filter( palette => {
      const id = palette.id.toString();
      return id.indexOf( '_' ) !== 0;
    } );

    const userPalettesCount = userPalettes.length;

    if ( userPalettesCount < minLength ) {
      for ( let i = 0; i < minLength - userPalettesCount; i++ ) {
        const newPalette = JSON.parse( JSON.stringify( arr[ 0 ] ) );
        newPalette.id = userPalettesCount + i + 1;
        arr.splice( userPalettesCount + i, 0, newPalette );
      }
    }
  }
};
