// Mirror logic of server-side Utils\Fonts::getCSSProperties()
import $ from "jquery";
import _ from "lodash";

export const getFontFieldCSSProperties = ( cssValue, allowedProperties = false, prefix = '' ) => {
  let output = '';

  $.each( cssValue, function( property, propertyValue ) {
    // We don't want to output empty CSS rules.
    if ( '' === propertyValue || false === propertyValue ) {
      return
    }

    // If the property is not allowed, skip it.
    if ( !isCSSPropertyAllowed( property, allowedProperties ) ) {
      return
    }

    output += prefix + property + ': ' + propertyValue + ';\n'
  } );

  return output
};

export const isCSSPropertyAllowed = ( property, allowedProperties = false ) => {

  // Empty properties are not allowed.
  if ( _.isEmpty( property ) ) {
    return false
  }

  // Everything is allowed if nothing is specified.
  if ( _.isEmpty( allowedProperties ) ) {
    return true
  }

  // For arrays
  if ( _.includes( allowedProperties, property ) ) {
    return true
  }

  // For objects
  if ( _.has( allowedProperties, property ) && allowedProperties[ property ] ) {
    return true
  }

  return false
};
