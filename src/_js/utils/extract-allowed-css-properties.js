import _ from "lodash";

export const extractAllowedCSSPropertiesFromFontFields = ( subfields ) => {

  // Nothing is allowed by default.
  const allowedProperties = {
    'font-family': false,
    'font-weight': false,
    'font-style': false,
    'font-size': false,
    'line-height': false,
    'letter-spacing': false,
    'text-align': false,
    'text-transform': false,
    'text-decoration': false,
  };

  if ( _.isEmpty( subfields ) ) {
    return allowedProperties
  }

  // We will match the subfield keys with the CSS properties, but only those that properties that are allowed.
  // Maybe at some point some more complex matching would be needed here.
  _.each( subfields, function( value, key ) {
    if ( typeof allowedProperties[ key ] !== 'undefined' ) {
      // Convert values to boolean.
      allowedProperties[ key ] = !!value;

      // For font-weight we want font-style to go the same way,
      // since these two are generated from the same subfield: font-weight (actually holding the font variant value).
      if ( 'font-weight' === key ) {
        allowedProperties[ 'font-style' ] = allowedProperties[ key ]
      }
    }
  } );

  return allowedProperties
};
