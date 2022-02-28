import { standardizeToArray } from '../standardize-to-array';

export const maybeExplodeList = ( str, delimiter = ',' ) => {

  if ( typeof str === 'object' ) {
    str = standardizeToArray( str )
  }

  // If by any chance we are given an array, just return it
  if ( Array.isArray( str ) ) {
    return str
  }

  // Anything else we coerce to a string
  if ( typeof str !== 'string' ) {
    str = String( str );
  }

  // Make sure we trim it
  str = str.trim();

  // Bail on empty string
  if ( !str.length ) {
    return [];
  }

  // Return the whole string as an element if the delimiter is missing
  if ( str.indexOf( delimiter ) === - 1 ) {
    return [ str ];
  }

  // Explode it and return it
  return explode( delimiter, str );
};

const explode = ( delimiter, string, limit ) => {
  //  discuss at: https://locutus.io/php/explode/
  // original by: Kevin van Zonneveld (https://kvz.io)
  //   example 1: explode(' ', 'Kevin van Zonneveld')
  //   returns 1: [ 'Kevin', 'van', 'Zonneveld' ]

  if ( arguments.length < 2 ||
       typeof delimiter === 'undefined' ||
       typeof string === 'undefined' ) {
    return null
  }
  if ( delimiter === '' ||
       delimiter === false ||
       delimiter === null ) {
    return false
  }
  if ( typeof delimiter === 'function' ||
       typeof delimiter === 'object' ||
       typeof string === 'function' ||
       typeof string === 'object' ) {
    return {
      0: ''
    }
  }
  if ( delimiter === true ) {
    delimiter = '1'
  }

  // Here we go...
  delimiter += '';
  string += '';

  let s = string.split( delimiter );

  if ( typeof limit === 'undefined' ) {
    return s;
  }

  // Support for limit
  if ( limit === 0 ) {
    limit = 1;
  }

  // Positive limit
  if ( limit > 0 ) {
    if ( limit >= s.length ) {
      return s
    }
    return s
    .slice( 0, limit - 1 )
    .concat( [
      s.slice( limit - 1 )
       .join( delimiter )
    ] )
  }

  // Negative limit
  if ( - limit >= s.length ) {
    return []
  }

  s.splice( s.length + limit );
  return s
};
