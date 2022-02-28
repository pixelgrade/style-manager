export const standardizeToArray = ( value ) => {
  if ( typeof value === 'string' || typeof value === 'number' ) {
    value = [ value ]
  } else if ( typeof value === 'object' ) {
    value = Object.values( value )
  }

  return value
};
