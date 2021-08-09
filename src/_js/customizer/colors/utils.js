export const moveConnectedFields = ( oldSettings, from, to, ratio ) => {

  const settings = JSON.parse( JSON.stringify( oldSettings ) );

  if ( !! settings[from] && !! settings[to] ) {

    if ( ! settings[from].connected_fields ) {
      settings[from].connected_fields = [];
    }

    if ( ! settings[to].connected_fields ) {
      settings[to].connected_fields = [];
    }

    const oldFromConnectedFields = Object.values( settings[from]['connected_fields'] )
    const oldToConnectedFields = Object.values( settings[to]['connected_fields'] )
    const oldConnectedFields = oldToConnectedFields.concat( oldFromConnectedFields )
    const count = Math.round( ratio * oldConnectedFields.length )

    let newToConnectedFields = oldConnectedFields.slice( 0, count )
    const newFromConnectedFields = oldConnectedFields.slice( count )

    newToConnectedFields = Object.keys( newToConnectedFields ).map( function( key ) {
      return newToConnectedFields[key]
    } )
    newToConnectedFields = Object.keys( newToConnectedFields ).map( function( key ) {
      return newToConnectedFields[key]
    } )

    settings[to]['connected_fields'] = newToConnectedFields
    settings[from]['connected_fields'] = newFromConnectedFields
  }

  return settings
}

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
}
